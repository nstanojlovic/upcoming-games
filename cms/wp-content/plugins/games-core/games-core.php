<?php
/**
 * Plugin Name:       Games Core
 * Description:       Game post type, IGDB importer and blocks for the headless upcoming-games site.
 * Version:           0.1.0
 * Requires at least: 6.6
 * Requires PHP:      8.1
 * Author:            Nemanja Stanojlović
 * Author URI:        https://anonemus.dev
 * License:           GPL-2.0-or-later
 * Text Domain:       games-core
 */

namespace GamesCore;

defined( 'ABSPATH' ) || exit;

const VERSION = '0.1.0';
const DIR     = __DIR__;

require_once DIR . '/includes/PostTypes.php';
require_once DIR . '/includes/Igdb.php';
require_once DIR . '/includes/Importer.php';
require_once DIR . '/includes/Rebuild.php';
require_once DIR . '/includes/Blocks.php';

PostTypes::register();
Importer::register();
Rebuild::register();
Blocks::register();

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once DIR . '/includes/Cli.php';
	\WP_CLI::add_command( 'games', Cli::class );
}

register_activation_hook( __FILE__, function () {
	PostTypes::register_types();
	flush_rewrite_rules();
	Importer::schedule();
} );

register_deactivation_hook( __FILE__, function () {
	Importer::unschedule();
} );
