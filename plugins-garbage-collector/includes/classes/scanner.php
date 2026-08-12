<?php
/*
 * Scan installed plugins files to know if they use own database tables
 * Author: Vladimir Garagulya
 * Author email: vladimir@shinephp.com
 * Author URI: http://shinephp.com
 * License: GPL v2+
 * 
*/
class PGC_Scanner {
    
    private static function get_blog_ids() {
        global $wpdb;

        if ( !is_multisite() ) {
            return null;
        }

        $network = get_current_site();
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- PGC requires direct database access for table inspection and cleanup.        
        $blog_ids = $wpdb->get_col(  
                $wpdb->prepare(
                "SELECT blog_id FROM {$wpdb->blogs}
                    WHERE site_id=%d ORDER BY blog_id ASC",
                [$network->id] )
        );

        return $blog_ids;
    }
    // end of get_blog_ids()

    
    private static function table_from_current_blog($table_name, $blog_ids) {
        global $wpdb;

        $current_blog_id = get_current_blog_id();
        $current_blog_prefix = $wpdb->get_blog_prefix();
        if ( substr( $table_name, 0, strlen( $current_blog_prefix ) ) !== $current_blog_prefix ) {    //  wp_1 != $wp_2 
            return false;
        }

        // Exclude wp_11, wp_12 and similar to leave wp_1 only
        foreach ( $blog_ids as $blog_id ) {
            if ( $blog_id == $current_blog_id ) {
                continue;
            }
            $prefix = $wpdb->base_prefix . $blog_id . '_';
            if ( substr( $table_name, 0, strlen( $prefix ) ) === $prefix ) {  
                // table from other blog detected
                return false;
            }
        }

        return true;
    }
    // end of table_from_current_blog()


    private static function get_db_table_info( $table_name ) {
        global $wpdb;
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- PGC requires direct database access for table inspection and cleanup.
        $result = $wpdb->get_results(
                $wpdb->prepare(
                        "SHOW TABLE STATUS LIKE %s",
                        $wpdb->esc_like( $table_name ) )
                );

        $table = new stdClass;
        $table->name = $table_name;
        $table->name_without_prefix = substr_replace( $table_name, '', 0, strlen( $wpdb->prefix ) );
        $table->records = isset( $result[0]->Rows ) ? $result[0]->Rows : 0;
        $table->kbytes = isset( $result[0]->Data_length ) ? ROUND( ( $result[0]->Data_length + $result[0]->Index_length ) / 1024, 2) : 0;
        if ( !PGC_Known_Plugins::fill_data( $table ) ) {
            $table->plugin_name = '';
            $table->plugin_file = '';
            $table->plugin_state = '';
        }

        return $table;
    }
    // end of get_db_table_info()


    /*
     * Returns the list of DB tables which don't belong to WordPress itself
     */
    private static function get_not_wp_tables() {
        global $wpdb;

        $all_plugins = get_plugins();
        $blog_ids = self::get_blog_ids();
        $wp_tables = $wpdb->tables('all', true);
        $not_wp_tables = [];
// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Static query with no variables/placeholders; $wpdb->prepare() is not required and would trigger _doing_it_wrong().        
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- PGC requires direct database access for table inspection and cleanup.                
        $db_tables = $wpdb->get_col( 'SHOW TABLES' );
        foreach ( $db_tables as $table_name ) {
            if ( in_array( $table_name, $wp_tables, true ) ||
                    strpos( $table_name, $wpdb->prefix, 0 ) === false) {
                continue;
            }
            if ( is_multisite() && ! self::table_from_current_blog( $table_name, $blog_ids ) ) {
                continue;
            }

            $table = self::get_db_table_info( $table_name );
            if ( !empty( $table->plugin_file ) && isset( $all_plugins[$table->plugin_file] ) ) {
                $plugin_data = $all_plugins[$table->plugin_file];
                $table->plugin_name .= ' ' . $plugin_data['Version'];
            }
            $not_wp_tables[] = $table;
        }

        PGC_Settings::update_hidden_tables_list( $not_wp_tables );

        return $not_wp_tables;
    }
    // end of get_not_wp_tables()


    /* 
     * Returns installed plugins list to scan for working with own database tables
     */
    public static function get_plugins_list() {

        $_SESSION['plugins-garbage-collector'] = null;
        $_SESSION['plugins-garbage-collector']['tables'] = self::get_not_wp_tables();
        $plugins = get_plugins();
        $skip_list = PGC_Known_Plugins::get_skip_list();
        $plugins_list = [];
        foreach ($plugins as $key => $plugin) {
            $key_lc = strtolower( $key );
            if ( in_array( $key_lc, $skip_list, true ) ) {
                continue;
            }
            $plugin_short = new stdClass();
            $plugin_short->key = $key;
            $plugin_short->title = $plugin['Title'];
            $plugin_short->version = $plugin['Version'];
            $plugins_list[] = $plugin_short;
        }

        $data = array('result' => 'success', 'plugins_list' => $plugins_list );
        
        return $data;
    }
    // end of get_plugins_list()


    /* 
     * Return the list of PHP files included into the plugin
     */
    private static function get_plugin_php_files( $plugin ) {

        $all_files = get_plugin_files( $plugin['key'] );
        if ( empty( $all_files ) ) {
            $answer = ['result' => 'error', 'message' => esc_html__('Invalid request - unexisted plugin ', 'plugins-garbage-collector') . $plugin['key'] ];
            return $answer;
        }

        // Extract PHP files only
        $php_files = [];
        foreach ( $all_files as $plugin_file ) {
            $ext = pathinfo( $plugin_file, PATHINFO_EXTENSION );
            if ( strtolower( $ext ) !== 'php') {
                continue;
            }
            $php_files[] = $plugin_file;
        }

        return $php_files;
    }
    // end of get_plugin_php_files()

    
    /*
     *  returns text file content as array with elements line by line
     */
    private static function read_file( $file ) {
        global $wp_filesystem;

        if ( ! $wp_filesystem || ! method_exists( $wp_filesystem, 'get_contents' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            WP_Filesystem();
        }

        if ( !$wp_filesystem || !method_exists($wp_filesystem, 'get_contents') ) {
            return false;
        }
        
        $file_path = WP_PLUGIN_DIR . '/' . $file;
        if ( !$wp_filesystem->exists( $file_path ) ) {        
            return false;
        }
        
        $content = $wp_filesystem->get_contents($file_path);
        if ( $content === false ) {
            // Failed to read file
            return false;
        }

        // Normalize raw endings and split into raws
        $normalized0 = str_replace("\r\n", "\n", $content );
        $normalized1 = str_replace("\r", "\n", $normalized0 );
        $raws = explode("\n", $normalized1 );

        return $raws;
    }
    // end of read_file()

    
    private static function is_session_data_available() {
        
        if ( !isset( $_SESSION['plugins-garbage-collector'] ) || 
             !isset( $_SESSION['plugins-garbage-collector']['tables'] ) || 
             !is_array( $_SESSION['plugins-garbage-collector']['tables'] ) ) {
            return false;
        }
        
        return true;
    }
    // end of is_session_data_available()
    
    
    private static function scan_file( $file, $plugin, $tables ) {
        
        if ( !self::is_session_data_available() ) {
            return;
        }
        
        $raws = self::read_file( $file );
        if ( $raws === false ) {
            return;
        }
        
        
        foreach ($raws as $key=>$raw) {
            $raw = strtolower( rtrim( $raw, "\n\r") );
            foreach( $tables as $table ) {
                if ( !empty( $table->plugin_name ) ) {
                    continue;
                }
                if ( strpos( $raw, $table->name_without_prefix ) !== false ) {
                    $table->plugin_name = $plugin['title'] . ' ' . $plugin['version'];
                    $table->plugin_file = $plugin['key'];
                }
            } // foreach()
        }

    }
    // end of scan_file()


    /*
     * Scan plugin PHP files if it uses own database tables
     */
    public static function scan_plugin_for_db_tables_use( $plugin, $current_file, $tables ) {                

        $php_files = self::get_plugin_php_files( $plugin );
        if ( isset( $php_files['result'] ) ) {
            // Invalid request - unexisted plugin
            return $php_files;
        }

        $cf = empty( $current_file ) ? 0 : $current_file;
        $files_to_process = 500;
        for ( $i = $cf; $i < count( $php_files ); $i++ ) {
            self::scan_file( $php_files[$i], $plugin, $tables );
            $files_to_process--;
            if ( $files_to_process < 1 ) {
                break;
            }
        }

        $total_files = count( $php_files );
        // $i is the index of the last file actually processed (loop breaks before its own increment runs);
        // resume from the next one so a batch limit doesn't re-scan the same file twice.
        $cf = $i + 1;
        $answer = [
            'result' => 'success',
            'current_file' => $cf,
            'total_files' => $total_files,
            'message' => $plugin['title'] . ' ' . esc_html__(' checked', 'plugins-garbage-collector')
        ];
        
        return $answer;
    }
    // end of scan_plugin_for_db_tables_use()
    
    
    private static function get_active_plugins() {

        $list = (array) get_option('active_plugins', [] );
        if ( is_multisite() ) {
            $list2 = get_site_option('active_sitewide_plugins', [] );
            if ( !empty( $list2 ) ) {
                $list = array_merge( $list, array_keys( $list2 ) );
            }
        }

        return $list;
    }
    // end of get_active_plugins()


    private static function get_plugin_state( $all_plugins, $active_plugins, $plugin_file ) {

        $plugin_active = false;
        foreach ( $active_plugins as $active_plugin ) {
            if ( $plugin_file === $active_plugin ) {
                $plugin_active = true;
                break;
            }
        }
        if ( $plugin_active ) {
            $plugin_state = 'active';
        } else {
            if ( isset( $all_plugins[$plugin_file] ) ) {
                $plugin_state = 'inactive';
            } else {
                $plugin_state = 'unused';
            }
        }

        return $plugin_state;
    }
    // end of get_plugin_state()


    private static function set_plugin_state_for_tables( $tables ) {
        
        if ( !self::is_session_data_available() ) {
            return;
        }
        
        $all_plugins = get_plugins();
        $active_plugins = self::get_active_plugins();
        foreach ( $tables as $table ) {
            if ( $table->plugin_file ) {
                $table->plugin_state = self::get_plugin_state( $all_plugins, $active_plugins, $table->plugin_file );
            } else {
                $table->plugin_state = 'unused';
            }
        }
    }
    // end of set_plugin_state_for_tables()



    public static function get_scan_results( $tables, $show_hidden_tables ) {

        self::set_plugin_state_for_tables( $tables );
        $html = PGC_Tables_List::show( $show_hidden_tables );
        $answer = ['result' => 'success', 'html' => $html];

        return $answer;
    }
    // end of pgc_scan_db_tables()
    
}
// end of PGC_Scanner class