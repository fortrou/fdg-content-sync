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
new FDG_App();