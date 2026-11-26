<?php

namespace GamesCore;

use WP_CLI;

/**
 * Manage the upcoming-games data.
 */
final class Cli {

	/**
	 * Import upcoming releases from IGDB.
	 *
	 * ## OPTIONS
	 *
	 * [--days=<days>]
	 * : How far ahead to look.
	 * ---
	 * default: 90
	 * ---
	 *
	 * [--max=<max>]
	 * : Maximum number of games to import, most anticipated first.
	 * ---
	 * default: 200
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp games import
	 *     wp games import --days=30 --max=50
	 */
	public function import( array $args, array $assoc ): void {
		$importer = new Importer( Igdb::from_config() );

		$stats = $importer->run(
			(int) $assoc['days'],
			(int) $assoc['max'],
			fn ( string $line ) => WP_CLI::log( $line )
		);

		WP_CLI::success( sprintf( '%d created, %d updated, %d failed.', $stats['created'], $stats['updated'], $stats['failed'] ) );
	}

	/**
	 * Trigger a frontend rebuild now.
	 */
	public function rebuild(): void {
		delete_transient( 'games_core_rebuild_lock' );
		Rebuild::trigger( 'manual' );
		WP_CLI::success( defined( 'GAMES_REBUILD_HOOK' ) ? 'Rebuild requested.' : 'GAMES_REBUILD_HOOK is not set, nothing to call.' );
	}
}
