<?php
/*
Plugin Name: Plugins Garbage Collector (Database Cleanup)
Plugin URI: https://www.shinephp.com/plugins-garbage-collector-wordpress-plugin/
Description: Find and clear unused data from the deactivated or uninstalled plugins. Look at the list of database tables created and used by plugins with quantity of records, size and owner plugin name.
Version: 0.15
Requires at least:  4.6
Requires PHP:       7.4
Author: Vladimir Garagulya
Author URI: https://www.shinephp.com
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html
Text Domain: plugins-garbage-collector
Domain Path: /lang/
*/

/*
Copyright 2010-2026  Vladimir Garagulya  (email: vladimir@shinephp.com)
*/


defined( 'ABSPATH' ) || exit;

require_once( __DIR__ .'/includes/classes/bootstrap.php');

PGC_Bootstrap::init( __FILE__ );
    


