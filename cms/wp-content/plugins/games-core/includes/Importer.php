<?php

namespace GamesCore;

use Throwable;

/**
 * Daily import of upcoming releases from IGDB into `game` posts.
 *
 * - New games are created with IGDB's summary as a starting point for editors.
 * - Existing games only get their facts refreshed (date, platforms, cover,
 *   anticipation). Anything an editor wrote in the content is never touched.
 */
final class Importer {

	public const HOOK = 'games_core_daily_import';

	public static function register(): void {
		add_action( self::HOOK, [ self::class, 'run_scheduled' ] );
		add_action( 'admin_menu', [ self::class, 'admin_page' ] );
		add_action( 'admin_post_games_core_import', [ self::class, 'handle_admin_import' ] );
	}

	/* Tools → Games import: run the import by hand and see the last result. */

	public static function admin_page(): void {
		add_management_page( 'Games import', 'Games import', 'manage_options', 'games-import', function () {
			$last = get_option( 'games_core_last_import' );
			$next = wp_next_scheduled( self::HOOK );
			?>
			<div class="wrap">
				<h1>Games import</h1>
				<p>Pulls the next 90 days of releases from IGDB, most followed first. Runs daily on its own.</p>
				<?php if ( isset( $_GET['done'] ) ) : ?>
					<div class="notice notice-success"><p><?php echo esc_html( sanitize_text_field( wp_unslash( $_GET['done'] ) ) ); ?></p></div>
				<?php elseif ( isset( $_GET['error'] ) ) : ?>
					<div class="notice notice-error"><p><?php echo esc_html( sanitize_text_field( wp_unslash( $_GET['error'] ) ) ); ?></p></div>
				<?php endif; ?>
				<p>
					<?php if ( $last ) : ?>
						Last run: <?php echo esc_html( human_time_diff( $last['time'] ) ); ?> ago
						(<?php echo (int) $last['created']; ?> created, <?php echo (int) $last['updated']; ?> updated, <?php echo (int) $last['failed']; ?> failed).
					<?php else : ?>
						Not run yet.
					<?php endif; ?>
					<?php if ( $next ) : ?>
						Next scheduled run: <?php echo esc_html( wp_date( 'j M, H:i', $next ) ); ?>.
					<?php endif; ?>
				</p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="games_core_import">
					<?php wp_nonce_field( 'games_core_import' ); ?>
					<?php submit_button( 'Run import now', 'primary', 'submit', false ); ?>
				</form>
			</div>
			<?php
		} );
	}

	public static function handle_admin_import(): void {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'games_core_import' ) ) {
			wp_die( 'Not allowed.' );
		}

		set_time_limit( 300 );
		$url = admin_url( 'tools.php?page=games-import' );

		try {
			$stats = ( new self( Igdb::from_config() ) )->run();
			$url   = add_query_arg( 'done', rawurlencode( "Imported: {$stats['created']} created, {$stats['updated']} updated, {$stats['failed']} failed." ), $url );
		} catch ( Throwable $e ) {
			$url = add_query_arg( 'error', rawurlencode( $e->getMessage() ), $url );
		}

		wp_safe_redirect( $url );
		exit;
	}

	public static function schedule(): void {
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( strtotime( 'tomorrow 05:00' ), 'daily', self::HOOK );
		}
	}

	public static function unschedule(): void {
		wp_clear_scheduled_hook( self::HOOK );
	}

	public static function run_scheduled(): void {
		try {
			( new self( Igdb::from_config() ) )->run();
		} catch ( Throwable $e ) {
			error_log( '[games-core] import failed: ' . $e->getMessage() );
		}
	}

	public function __construct( private readonly Igdb $igdb ) {}

	/**
	 * @param callable|null $log Receives one progress line at a time (used by WP-CLI).
	 * @return array{created:int, updated:int, failed:int}
	 */
	public function run( int $days = 90, int $max = 200, ?callable $log = null ): array {
		$log   ??= static fn () => null;
		$stats   = [ 'created' => 0, 'updated' => 0, 'failed' => 0 ];
		$from    = gmdate( 'Y-m-d' );
		$to      = gmdate( 'Y-m-d', strtotime( "+{$days} days" ) );

		Rebuild::pause();

		foreach ( $this->igdb->upcoming( $from, $to, $max ) as $game ) {
			try {
				$existing = $this->find( $game['id'] );
				$existing ? $this->update( $existing, $game ) : $this->create( $game );
				$stats[ $existing ? 'updated' : 'created' ]++;
				$log( ( $existing ? 'updated ' : 'created ' ) . $game['name'] );
			} catch ( Throwable $e ) {
				$stats['failed']++;
				$log( "failed {$game['name']}: {$e->getMessage()}" );
			}
		}

		Rebuild::resume();

		// One frontend rebuild for the whole batch instead of one per post.
		if ( $stats['created'] || $stats['updated'] ) {
			Rebuild::trigger( 'import' );
		}

		update_option( 'games_core_last_import', [ 'time' => time() ] + $stats, false );

		return $stats;
	}

	private function find( int $source_id ): ?int {
		$ids = get_posts( [
			'post_type'      => 'game',
			'post_status'    => 'any',
			'meta_key'       => 'source_id',
			'meta_value'     => $source_id,
			'fields'         => 'ids',
			'posts_per_page' => 1,
		] );

		return $ids[0] ?? null;
	}

	private function create( array $game ): void {
		$post_id = wp_insert_post( [
			'post_type'    => 'game',
			'post_status'  => defined( 'GAMES_IMPORT_STATUS' ) ? GAMES_IMPORT_STATUS : 'publish',
			'post_title'   => $game['name'],
			'post_name'    => $game['slug'],
			'post_excerpt' => wp_trim_words( $game['summary'], 30, '…' ),
			'post_content' => $this->content( $game['summary'] ),
		], true );

		if ( is_wp_error( $post_id ) ) {
			throw new \RuntimeException( $post_id->get_error_message() );
		}

		// Set once; after that these belong to the editor.
		update_post_meta( $post_id, 'developer', sanitize_text_field( $game['developer'] ) );
		update_post_meta( $post_id, 'publisher', sanitize_text_field( $game['publisher'] ) );
		update_post_meta( $post_id, 'website', esc_url_raw( $game['website'] ) );

		$this->sync_facts( $post_id, $game );
	}

	private function update( int $post_id, array $game ): void {
		$this->sync_facts( $post_id, $game );
	}

	/** Fields that come from IGDB and may change over time. */
	private function sync_facts( int $post_id, array $game ): void {
		update_post_meta( $post_id, 'source_id', $game['id'] );
		update_post_meta( $post_id, 'source_url', esc_url_raw( $game['url'] ) );
		update_post_meta( $post_id, 'release_date', $game['release_date'] );
		update_post_meta( $post_id, 'cover_url', esc_url_raw( $game['cover_url'] ) );
		update_post_meta( $post_id, 'anticipation', $game['anticipation'] );
		update_post_meta( $post_id, 'stores', array_map(
			fn ( $s ) => [ 'store' => sanitize_key( $s['store'] ), 'url' => esc_url_raw( $s['url'] ) ],
			$game['stores']
		) );

		// Fill the trailer from IGDB, but never replace one an editor picked.
		if ( $game['trailer_url'] && ! get_post_meta( $post_id, 'trailer_url', true ) ) {
			update_post_meta( $post_id, 'trailer_url', esc_url_raw( $game['trailer_url'] ) );
		}

		wp_set_object_terms( $post_id, $game['platforms'], 'platform' );
		wp_set_object_terms( $post_id, $game['genres'], 'genre' );
	}

	/** IGDB's summary as paragraph blocks, so editors can rewrite it in Gutenberg. */
	private function content( string $summary ): string {
		$blocks = [ '<!-- wp:games-core/countdown /-->', '<!-- wp:games-core/trailer /-->' ];

		foreach ( array_filter( array_map( 'trim', preg_split( "/\n+/", $summary ) ) ) as $paragraph ) {
			$blocks[] = "<!-- wp:paragraph -->\n<p>" . esc_html( $paragraph ) . "</p>\n<!-- /wp:paragraph -->";
		}

		return implode( "\n\n", $blocks );
	}
}
