<?php

defined( 'ABSPATH' ) || exit;

/*
 * Database Cleanup (former Plugins Garbage Collector) WordPress plugin
 * Author: Vladimir Garagulia
 * Email: vladimir@shinephp.com
 * License: GPLv2 or later
 */


/**
 * Process AJAX request from Database Cleanup
 *
 * @author Vladimir Gargulia
 */
final class PGC_Ajax {
    
    private static ?string $action = null;
    private static array $allowed_actions = [
        'get-plugins-list',
        'scan-plugin-for-db-tables-use',
        'get-scan-results',
        'check-wp-tables-structure',
        'hide-table',
        'show-table'
    ];
    
    /**
     * Returns value by name from GET/POST/REQUEST. Minimal type checking is provided
     * 
     * @param string $var_name  Variable name to return
     * @param string $var_type  variable type to provide value checking
     * @return mix variable value from request
     */
    private static function get_post_var( $var_name, $var_type = 'string') {

        $result = 0;
// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified in self::dispatch()
        if ( isset($_POST[$var_name] ) ) {
            if ($var_type != 'checkbox') {
// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified in self::dispatch()                        
                $result = sanitize_text_field( wp_unslash( $_POST[$var_name] ) );
            } else {
                $result = 1;
            }
        }

        if ( $result ) {
            if ( $var_type === 'int' && !is_numeric( $result ) ) {
                $result = 0;
            }
            if ( $var_type !== 'int') {
                $result = esc_attr( $result );
            }
        }

        return $result;
    }
    // end of get_request_var()
    
    
    private static function get_action() {
        
        $action = self::get_post_var( 'subaction' );
         
        if ( !in_array( $action, self::$allowed_actions, true ) ) {
            $action = false;
        }
        
        return $action;            
    }
    // end of get_action()
    
    
    private static function valid_nonce() {

        // check_ajax_referer() is used (with $stop=false) rather than check_admin_referer()
        // because the latter always calls wp_die() itself on a failed nonce for a named action,
        // which would return WordPress's generic HTML "link expired" page instead of the JSON
        // error response below - breaking JSON.parse() on the JS side.
        if ( !check_ajax_referer( 'plugins-garbage-collector', '_ajax_nonce', false ) ) {
            echo wp_json_encode( array('result'=>'error', 'message'=>esc_html__('Database Cleanup: Wrong or expired request', 'plugins-garbage-collector' ) ) );
            return false;
        }
        
        return true;                
    }
    // end of check_nonce()
    
    
    private static function user_can() {
                
        if ( !current_user_can( PGC_Bootstrap::CAPABILITY ) ) {
            echo wp_json_encode( array('result'=>'error', 'message'=>esc_html__('Database Cleanup: Insufficient permissions', 'plugins-garbage-collector' ) ) );
            return false;
        }                
        
        return true;        
    }
    // end of user_can()

    
    private static function switch_table_visibility( bool $make_visible ) {
        
        $table_name = self::get_post_var('table_name' );
        if ( empty( $table_name ) ) {
            $answer = array('result'=>'error', 'message'=>esc_html__('Wrong request - required parameter table_name is missed.','plugins-garbage-collector') );
            return $answer;
        }
        
        if ( $make_visible ) {
            $answer = PGC_Settings::show_table( $table_name );
        } else {
            $answer = PGC_Settings::hide_table( $table_name );
        }
        
        return $answer;
    }
    // end of show_table()
                
    
    private static function get_session_tables() {
        if ( isset( $_SESSION['plugins-garbage-collector'] ) && 
             isset( $_SESSION['plugins-garbage-collector']['tables'] ) && 
             is_array( $_SESSION['plugins-garbage-collector']['tables'] ) &&   
             count( $_SESSION['plugins-garbage-collector']['tables'] ) > 0 ) {
            // sanitize_key keeps only a-z, 0-9, _, and -
            $sanitized_tables0 = map_deep( $_SESSION['plugins-garbage-collector']['tables'], 'sanitize_text_field' );    
            // Remove any empty items that failed sanitization
            $sanitized_tables = array_filter( $sanitized_tables0 );
        } else {
            $sanitized_tables = array();
        }
        
        return $sanitized_tables;
    }
    // end of get_session_tables()
    
    
    private static function scan_plugin_for_db_tables_use() {
// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified in self::dispatch()        
        if ( !isset( $_POST['plugin'] ) || empty( $_POST['plugin'] ) ) {
            $answer = ['result' => 'error', 'message' => esc_html__('Invalid request - missed plugin parameter', 'plugins-garbage-collector' )];
            return $answer;
        }
// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified in self::dispatch()
        if ( !isset( $_POST['plugin']['key'] ) || !isset( $_POST['plugin']['title'] ) || !isset( $_POST['plugin']['version'] ) ) {
            $answer = ['result' => 'error', 'message' => esc_html__('Invalid request - wrong plugin parameter value', 'plugins-garbage-collector') ];
            return $answer;
        }
        $plugin = [];
// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified in self::dispatch()        
        $plugin['key'] = sanitize_text_field( wp_unslash( $_POST['plugin']['key'] ) );
// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified in self::dispatch()                
        $plugin['title'] = sanitize_text_field( wp_unslash( $_POST['plugin']['title'] ) );
// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified in self::dispatch()                
        $plugin['version'] = sanitize_text_field( wp_unslash( $_POST['plugin']['version'] ) );
        
        $current_file = self::get_post_var( 'current_file', 'int' );        
        $tables = self::get_session_tables();        
        
        $answer = PGC_Scanner::scan_plugin_for_db_tables_use( $plugin, $current_file, $tables );
        
        return $answer;
    }
    // end of scan_plugin_for_db_table_use()
    
    
    private static function get_scan_results() {
        
        $tables = self::get_session_tables();        
        $show_hidden_tables = self::get_post_var('show_hidden_tables', 'checkbox' );
        $answer = PGC_Scanner::get_scan_results( $tables, $show_hidden_tables );
        
        return $answer;
    }
    // end of get_scan_results()
    
    
    private static function get_wpdb_scan_results() {
        
        $show_hidden_tables = self::get_post_var('show_hidden_tables', 'checkbox' );
        $answer = PGC_Scanner_WPDB::get_scan_results( $show_hidden_tables );
        
        return $answer;
    }
    // end of get_scan_results()
    
    
    private static function process() {
        
        switch (self::$action) {
            case 'get-plugins-list': {
                    $answer = PGC_Scanner::get_plugins_list();
                    break;
                }
            case 'scan-plugin-for-db-tables-use': {
                    $answer = self::scan_plugin_for_db_tables_use();
                    break;
                }
            case 'get-scan-results': {
                    $answer = self::get_scan_results();
                    break;
                }
            case 'check-wp-tables-structure': {
                    $answer = self::get_wpdb_scan_results();
                    break;
                }
            case 'hide-table': {
                    $answer = self::switch_table_visibility( false );
                    break;
                }
            case 'show-table': {
                    $answer = self::switch_table_visibility( true );
                    break;
                }
            default: {
                    $answer = ['result' => 'error', 'message' => esc_html__('Unknown action', 'plugins-garbage-collector') ];
                }
        }   // end of switch
        
        return $answer;
        
    }
    // end of process()
    

    public static function dispatch() {
                
        self::$action = self::get_action();
        if ( !self::$action ) {
            echo wp_json_encode( array('result' => 'error', 'message' => esc_html__('Unknown action', 'plugins-garbage-collector') ) );
            wp_die();
        }
        
        if ( !self::valid_nonce() || !self::user_can() ) {
            wp_die();
        }


        if ( session_status() !== PHP_SESSION_ACTIVE ) {
            session_start();
        }

        if ( function_exists( 'set_time_limit' ) && ! ini_get( 'safe_mode' ) ) {
// phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged -- Needed for plugin files scan / database cleanup process; safely guarded by function_exists() and limited by 90 seconds only.
            @set_time_limit( 90 ); // 90 seconds
        }
        $data = self::process();
        echo wp_json_encode( $data );
        wp_die();
    }
    // end of dispatch()
}
// end of PGC_Ajax class