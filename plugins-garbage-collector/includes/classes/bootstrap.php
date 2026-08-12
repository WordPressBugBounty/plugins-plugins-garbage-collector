<?php

defined( 'ABSPATH' ) || exit;

/*
 * Bootstrap class of Database Cleanup (former Plugins Garbage Collector) WordPress plugin
 * Author: Vladimir Garagulya
 * Author email: vladimir@shinephp.com
 * Author URI: http://shinephp.com
 * License: GPL v2+
 * 
*/

final class PGC_Bootstrap {
    public const VERSION = '0.15';
    public const MIN_PHP_VERSION = '7.4';
    public const MIN_WP_VERSION = '4.6';
    public const DATA_VERSION_URL = 'https://database-cleanup.s3.us-east-1.amazonaws.com/version.json';
    public const DATA_SKIP_LIST_URL = 'https://database-cleanup.s3.us-east-1.amazonaws.com/skip-list.json';
    public const DATA_PLUGINS_URL = 'https://database-cleanup.s3.us-east-1.amazonaws.com/plugins.json';
    public const DATA_DB_TABLES_URL = 'https://database-cleanup.s3.us-east-1.amazonaws.com/db-tables.json';
    public const CAPABILITY = 'activate_plugins';
    public const DATA_DIR_NAME = 'pgc-data';
    public const DATA_FILE_NAMES = array( 'skip-list.json', 'plugins.json', 'db-tables.json', 'version.json' );

    private static ?string $data_version_path = null;
    private static ?string $data_dir = null;
    private static ?string $plugin_file = null;
    private static ?string $plugin_dir = null;
    private static ?string $plugin_url = null;
    private static ?string $plugin_basename = null;
    
    
        public static function get_plugin_file() {
        
        return self::$plugin_file;
    }
    // end of get_plugin_file()
    
    
    public static function get_plugin_dir() {
        
        if ( self::$plugin_dir===null) {
            self::$plugin_dir = plugin_dir_path( self::$plugin_file );
        }
        
        return self::$plugin_dir;
    }
    // end of get_plugin_dir()
    
    
    public static function get_plugin_url() {
        
        if ( self::$plugin_url===null) {
            self::$plugin_url = plugin_dir_url( self::$plugin_file );
        }
        
        return self::$plugin_url;
    }
    // end of get_plugin_dir()
    
    
    public static function get_plugin_basename() {
        
        if ( self::$plugin_basename===null) {
            self::$plugin_basename = plugin_basename( self::$plugin_file );
        }
        
        return self::$plugin_basename;
    }
    // end of get_plugin_basename()


    private static function check_versions() {
        global $wp_version;
        
        if ( version_compare( PHP_VERSION, self::MIN_PHP_VERSION, '<' ) ) {
            deactivate_plugins( self::get_plugin_file() );
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                $exit_msg = 'Plugins Garbage Collector requires PHP version ' . self::MIN_PHP_VERSION . ' or newer.' .
                    ' <a href="https://wordpress.org/about/requirements/"> Please update!</a>';
// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Only executes when WP_DEBUG is enabled.                
                error_log( $exit_msg );
            }
            return false;
        }

        if ( version_compare( $wp_version, self::MIN_WP_VERSION, '<') ) {
            deactivate_plugins( self::get_plugin_file() );
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                $exit_msg = 'Plugins Garbage Collector requires WordPress ' . self::MIN_WP_VERSION . ' or newer.' .
                ' <a href="https://codex.wordpress.org/Upgrading_WordPress"> Please update!</a>';
// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Only executes when WP_DEBUG is enabled.                
                error_log( $exit_msg );
            }
            return false;
        }
        
        return true;
    }
    // end of check_versions()
    
    
    public static function get_data_version_path() {

        if ( self::$data_version_path===null ) {
            self::$data_version_path = self::get_data_dir() . 'version.json';
        }

        return self::$data_version_path;
    }
    // end of get_data_version_path()


    /*
     * Returns the writable directory the plugin caches its auto-refreshed data files in:
     * wp-content/uploads/pgc-data/, seeded on first use from the shipped ./data defaults.
     * Falls back to the plugin's own data/ directory (as before) if uploads isn't usable -
     * that keeps the plugin working, though it won't be able to persist refreshed data there
     * on hosts where the plugin directory itself isn't writable.
     */
    public static function get_data_dir() {

        if ( self::$data_dir===null ) {
            self::$data_dir = self::resolve_data_dir();
        }

        return self::$data_dir;
    }
    // end of get_data_dir()


    private static function resolve_data_dir() {

        $upload_dir = wp_upload_dir();
        if ( empty( $upload_dir['basedir'] ) || ! empty( $upload_dir['error'] ) ) {
            return self::get_plugin_dir() . 'data/';
        }

        $dir = trailingslashit( $upload_dir['basedir'] ) . self::DATA_DIR_NAME . '/';
        if ( ! wp_mkdir_p( $dir ) ) {
            return self::get_plugin_dir() . 'data/';
        }

        self::seed_data_dir( $dir );

        return $dir;
    }
    // end of resolve_data_dir()


    /*
     * Copies the plugin's shipped default data files into the writable data dir the first
     * time it's used, so a fresh install has working data before the first remote refresh.
     * Never overwrites files already present (e.g. ones a previous refresh already wrote).
     */
    private static function seed_data_dir( $dir ) {

        global $wp_filesystem;
        if ( ! $wp_filesystem || ! method_exists( $wp_filesystem, 'exists' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            WP_Filesystem();
        }
        if ( ! $wp_filesystem ) {
            return;
        }

        foreach ( self::DATA_FILE_NAMES as $file_name ) {
            $target = $dir . $file_name;
            if ( $wp_filesystem->exists( $target ) ) {
                continue;
            }

            $source = self::get_plugin_dir() . 'data/' . $file_name;
            if ( $wp_filesystem->exists( $source ) ) {
                $wp_filesystem->copy( $source, $target, false, 0664 );
            }
        }
    }
    // end of seed_data_dir()

            
    public static function init( $plugin_file ) {
        
        if ( self::$plugin_file===null ) {
            self::$plugin_file = $plugin_file;
        }
        
        if ( ! self::check_versions() ) {
            return;
        }
        
        require_once( self::get_plugin_dir() . 'includes/classes/ajax.php');
        require_once( self::get_plugin_dir() . 'includes/classes/settings.php');
        require_once( self::get_plugin_dir() . 'includes/classes/known-plugins.php');
        require_once( self::get_plugin_dir() . 'includes/classes/db-edit.php');
        require_once( self::get_plugin_dir() . 'includes/classes/tables-list.php');
        require_once( self::get_plugin_dir() . 'includes/classes/tables-list-wpdb.php');
        require_once( self::get_plugin_dir() . 'includes/classes/scanner.php');
        require_once( self::get_plugin_dir() . 'includes/classes/scanner-wpdb.php');
        require_once( self::get_plugin_dir() . 'includes/classes/plugins-garbage-collector.php');

        Plugins_Garbage_Collector::get_instance()->run();
        
    }
    // end of init()                   
        
}
// end of PGC_Bootstrap class
