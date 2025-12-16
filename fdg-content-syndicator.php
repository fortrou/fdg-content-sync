<?php
/*
 * Plugin Name: Content syndication
 * Plugin URI: https://fortrou.dev
 * Description: Content syndication for environments.
 * Author: Serhii Nechyporenko
 * Author URI: https://fortrou.dev
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Version: 0.1.0
 * Requires at least: 6.1
 * Requires PHP: 7.4
 */

require_once "config.php";
require_once "classes/fdg-app.php";
require_once "functions.php";

require_once FDG_CONTENT_SYNDICATOR_PLUGIN_PATH . 'classes/fdg-syndicator-queue.php';

register_activation_hook(__FILE__, function() {
    $uploads_dir = trailingslashit( wp_upload_dir()['basedir'] ) . 'fdg-syndicator-meta';
    wp_mkdir_p( $uploads_dir );
    FDG_Syndicator_Queue::instance()->install_table();
});

new FDG_App();