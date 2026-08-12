<?php

class PGC_Known_Plugins {    
    
    static private ?string $version_path = null;
    
    // List of plugins which create/use own database tables and known to the PGC plugins
    static private $plugins = null;    

    // List of plugins, which do not create own database tables, not need to scan/check them
    static private $skip_list = null;

    // Database tables which belong to plugins and/or themes
    static private $db_tables = null;


    /*
     * Returns an initialized WP_Filesystem instance, initializing it on first use only
     */
    static private function get_wp_filesystem() {

        global $wp_filesystem;

        if ( ! $wp_filesystem || ! method_exists( $wp_filesystem, 'get_contents' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            WP_Filesystem();
        }

        return $wp_filesystem;
    }
    // end of get_wp_filesystem()


    static private function get_data_local_version() {

        $version = 0;
        $fs = self::get_wp_filesystem();
        $data = $fs ? $fs->get_contents( PGC_Bootstrap::get_data_version_path() ) : false;
        $object = json_decode( $data );
        if ( isset( $object->version ) ) {
            $version = $object->version;
        }

        return $version;
    }
    // end of get_data_local_version()


    static private function get_data_remote_version() {

        $version = 0;
        $fs = self::get_wp_filesystem();
        $mtime = $fs ? $fs->mtime( PGC_Bootstrap::get_data_version_path() ) : false;
        $file_date = gmdate( 'Ymd', $mtime ? $mtime : 0 );
        $current_date = gmdate("Ymd");
        if ( $file_date==$current_date ) {
            return $version;
        }

        $answer = wp_remote_get( PGC_Bootstrap::DATA_VERSION_URL, array('timeout' => 5 ) );
        if ( is_wp_error( $answer ) ) {
            $error_message = $answer->get_error_message();
        } else {
            if ($answer['response']['code']==200) {
                $obj = json_decode( $answer['body'] );
                $version = $obj->version;
            } else {
                $error_message = $answer['response']['code'] .' '. $answer['response']['message'];
            }
        }

        return $version;
    }
    // end of get_data_remote_version()


    static private function get_remote_data( $url, $file_name ) {

        $result = -1;
        $answer = wp_remote_get( $url, array('timeout' => 5 ) );
        if ( is_wp_error( $answer ) ) {
            $error_message = $answer->get_error_message();
        } else {
            if ($answer['response']['code']==200) {
                $fs = self::get_wp_filesystem();
                if ( $fs ) {
                    wp_delete_file( $file_name );
                    $fs->put_contents( $file_name, $answer['body'], 0664 );
                    $result = 1;
                }
            } else {
                $error_message = $answer['response']['code'] .' '. $answer['response']['message'];
            }
        }

        return $result;
    }
    // end of get_remote_data()


    static private function update_version( $version ) {

        $object = new stdClass();
        $object->version = $version;
        $json_data = wp_json_encode( $object );
        $fs = self::get_wp_filesystem();
        if ( $fs ) {
            wp_delete_file( PGC_Bootstrap::get_data_version_path() );
            $fs->put_contents( PGC_Bootstrap::get_data_version_path(), $json_data, 0664 );
        }

    }
    // end of update_version()
    
    
    static private function _refresh_data( $version ) {
        
        $result1 = self::get_remote_data( PGC_Bootstrap::DATA_SKIP_LIST_URL, PGC_Bootstrap::get_data_dir() . 'skip-list.json');
        $result2 = self::get_remote_data( PGC_Bootstrap::DATA_PLUGINS_URL, PGC_Bootstrap::get_data_dir() . 'plugins.json');
        $result3 = self::get_remote_data( PGC_Bootstrap::DATA_DB_TABLES_URL, PGC_Bootstrap::get_data_dir() . 'db-tables.json');
        
        $result = $result1 + $result2 + $result3;
        if ( $result!=3 ) {    // Something is wrong. We will try again later.
            return;
        }
        
        self::update_version( $version );
        
    }
    // end of _refresh_data()
    
    
    static public function refresh_data() {
    
        $local_version = self::get_data_local_version();
        $remote_version = self::get_data_remote_version();
        if ( $local_version>=$remote_version ) {
            return;
        }
        
        self::_refresh_data( $remote_version );
        
    }
    // end of refresh_data()
    
    
/*
 * Read the own list of plugins which don't create own database tables
 */    
    static private function init_skip_list() {
        
        if ( !empty( self::$skip_list ) ) {
            return;
        }
        
        $fs = self::get_wp_filesystem();
        $data = $fs ? $fs->get_contents( PGC_Bootstrap::get_data_dir() . 'skip-list.json' ) : false;
        self::$skip_list = json_decode( $data, true );
        
    }
    // end of init_skip_list()
    
    
    static private function init_plugins() {
        
        if ( !empty( self::$plugins ) ) {
            return;
        }
        
        $fs = self::get_wp_filesystem();
        $data = $fs ? $fs->get_contents( PGC_Bootstrap::get_data_dir() . 'plugins.json' ) : false;
        self::$plugins = json_decode( $data, true );
        
    }
    // end of init_plugins
    
    
    /*
     * Adds to the skip list the list of plugins which own db tables are known
     * and returns as the list of plugins for which no need to scan plugin files
     */
    static public function get_skip_list() {
        
        self::init_skip_list();
        self::init_plugins();
        
        $checked = self::$skip_list;
        foreach( self::$plugins as $plugin ) {
            $checked[] = $plugin['file'];
        }
        
        
        return $checked;
    }
    // end of get_skip_list()
    
    
    static private function init_db_tables() {
        
        if ( !empty( self::$db_tables ) ) {
            return;
        }
        self::init_plugins();
        
        $fs = self::get_wp_filesystem();
        $data = $fs ? $fs->get_contents( PGC_Bootstrap::get_data_dir() . 'db-tables.json' ) : false;
        self::$db_tables = json_decode( $data, true );
        if ( !is_multisite() ) {
            // Global WordPress table for multisite. It's created by BuddyPress for WP single site
            self::$db_tables['signups'] = 'buddypress';
        }
        
    }
    // end of init_db_tables()
    

    static public function fill_data( stdClass &$table ) {

        self::init_db_tables();
        $name_lc = strtolower( $table->name_without_prefix );
        if ( !isset( self::$db_tables[$name_lc] ) || !isset( self::$plugins[self::$db_tables[$name_lc]] ) ) {
            return false;
        }

        $plugin = self::$plugins[self::$db_tables[$name_lc]];
        $table->plugin_name = $plugin['name'];
        $table->plugin_file = $plugin['file'];
        $table->state = 'have used';
        
        return true;
    }
    // end of fill_data()
    
}
// end of PGC_Known_Plugins class
