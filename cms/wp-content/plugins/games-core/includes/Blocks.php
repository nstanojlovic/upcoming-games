<?php

namespace GamesCore;

/**
 * Custom blocks and the "Game details" box in the editor.
 * Blocks are written without a build step (plain JS + block.json).
 */
final class Blocks {

	public static function register(): void {
		add_action( 'init', [ self::class, 'register_blocks' ] );
		add_action( 'add_meta_boxes_game', [ self::class, 'add_details_box' ] );
		add_action( 'save_post_game', [ self::class, 'save_details_box' ], 5 );
	}

	public static function register_blocks(): void {
		register_block_type( DIR . '/blocks/countdown' );
		register_block_type( DIR . '/blocks/trailer' );
	}

	/* ------------------------------------------------------------------
	 * "Game details" box: edit the facts the importer manages, plus the
	 * trailer, without needing a custom React sidebar.
	 * ---------------------------------------------------------------- */

	private const EDITABLE = [
		'featured'     => [ 'Feature on homepage', 'checkbox' ],
		'release_date' => [ 'Release date', 'date' ],
		'release_tba'  => [ 'Date not confirmed (TBA)', 'checkbox' ],
		'trailer_url'  => [ 'Trailer (YouTube URL)', 'url' ],
		'developer'    => [ 'Developer', 'text' ],
		'publisher'    => [ 'Publisher', 'text' ],
		'website'      => [ 'Official website', 'url' ],
	];

	public static function add_details_box(): void {
		add_meta_box( 'games-core-details', __( 'Game details', 'games-core' ), [ self::class, 'render_details_box' ], 'game', 'side', 'high' );
	}

	public static function render_details_box( \WP_Post $post ): void {
		wp_nonce_field( 'games_core_details', 'games_core_details_nonce' );

		foreach ( self::EDITABLE as $key => [ $label, $type ] ) {
			$value = get_post_meta( $post->ID, $key, true );
			$id    = "games-core-{$key}";

			echo '<p>';

			if ( $type === 'checkbox' ) {
				printf(
					'<label><input type="checkbox" id="%1$s" name="games_core[%2$s]" value="1" %3$s> %4$s</label>',
					esc_attr( $id ), esc_attr( $key ), checked( (bool) $value, true, false ), esc_html( $label )
				);
			} else {
				printf(
					'<label for="%1$s"><strong>%2$s</strong></label><br><input type="%3$s" id="%1$s" name="games_core[%4$s]" value="%5$s" class="widefat">',
					esc_attr( $id ), esc_html( $label ), esc_attr( $type ), esc_attr( $key ), esc_attr( $value )
				);
			}

			echo '</p>';
		}

		if ( $source = get_post_meta( $post->ID, 'source_url', true ) ) {
			printf(
				'<p class="description">%s <a href="%s" target="_blank" rel="noopener">IGDB</a>. %s</p>',
				esc_html__( 'Imported from', 'games-core' ),
				esc_url( $source ),
				esc_html__( 'The daily import keeps the date, platforms and cover in sync.', 'games-core' )
			);
		}
	}

	public static function save_details_box( int $post_id ): void {
		if (
			! isset( $_POST['games_core_details_nonce'] ) ||
			! wp_verify_nonce( sanitize_key( $_POST['games_core_details_nonce'] ), 'games_core_details' ) ||
			! current_user_can( 'edit_post', $post_id ) ||
			wp_is_post_autosave( $post_id )
		) {
			return;
		}

		$input = (array) wp_unslash( $_POST['games_core'] ?? [] );

		foreach ( self::EDITABLE as $key => [ , $type ] ) {
			$value = $input[ $key ] ?? '';

			$value = match ( $type ) {
				'checkbox' => (bool) $value,
				'url'      => esc_url_raw( $value ),
				'date'     => preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ? $value : '',
				default    => sanitize_text_field( $value ),
			};

			update_post_meta( $post_id, $key, $value );
		}
	}
}
