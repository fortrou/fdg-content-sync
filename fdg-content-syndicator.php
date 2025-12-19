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
use FdgSync\FdgSyndicatorQueue;
use FdgSync\FdgApp;
require_once "config.php";
require_once "functions.php";
register_activation_hook(__FILE__, function() {
    $uploads_dir = trailingslashit( wp_upload_dir()['basedir'] ) . 'fdg-syndicator-meta';
    wp_mkdir_p( $uploads_dir );
    FdgSyndicatorQueue::instance()->install_table();
});

$app = new FdgApp();