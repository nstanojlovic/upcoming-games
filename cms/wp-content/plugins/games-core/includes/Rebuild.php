<?php

namespace GamesCore;

/**
 * Tells the static Astro site to rebuild when games or news posts change,
 * by calling a deploy webhook (e.g. Forge's deployment trigger URL).
 *
 * Set in wp-config.php:  define( 'GAMES_REBUILD_HOOK', 'https://forge.laravel.com/servers/.../deploy/http?token=...' );
 */
final class Rebuild {

	private static bool $paused = false;

	public static function register(): void {
		add_action( 'transition_post_status', [ self::class, 'on_status_change' ], 10, 3 );
		add_action( 'save_post_game', [ self::class, 'on_save' ], 10, 2 );
		add_action( 'save_post_post', [ self::class, 'on_save' ], 10, 2 ); // News
		add_action( 'games_core_deferred_rebuild', fn () => self::trigger( 'deferred' ) );
	}

	/** Used by the importer to send one rebuild for a whole batch. */
	public static function pause(): void {
		self::$paused = true;
	}

	public static function resume(): void {
		self::$paused = false;
	}

	public static function on_status_change( string $new, string $old, \WP_Post $post ): void {
		// Publishing or unpublishing a game or news post changes the site either way.
		if ( in_array( $post->post_type, [ 'game', 'post' ], true ) && ( $new === 'publish' || $old === 'publish' ) && $new !== $old ) {
			self::trigger( "status {$old} → {$new}" );
		}
	}

	public static function on_save( int $post_id, \WP_Post $post ): void {
		if ( $post->post_status === 'publish' && ! wp_is_post_revision( $post_id ) && ! wp_is_post_autosave( $post_id ) ) {
			self::trigger( 'updated' );
		}
	}

	public static function trigger( string $reason ): void {
		if ( self::$paused || ! defined( 'GAMES_REBUILD_HOOK' ) || ! GAMES_REBUILD_HOOK ) {
			return;
		}

		// At most one rebuild per minute. A change inside that window schedules one
		// follow-up rebuild, so the last edit always makes it to the live site.
		if ( get_transient( 'games_core_rebuild_lock' ) ) {
			if ( ! wp_next_scheduled( 'games_core_deferred_rebuild' ) ) {
				wp_schedule_single_event( time() + 2 * MINUTE_IN_SECONDS, 'games_core_deferred_rebuild' );
			}

			return;
		}

		set_transient( 'games_core_rebuild_lock', 1, MINUTE_IN_SECONDS );

		wp_remote_post( GAMES_REBUILD_HOOK, [
			'blocking' => false,
			'timeout'  => 5,
			'body'     => [ 'reason' => $reason ],
		] );
	}
}
