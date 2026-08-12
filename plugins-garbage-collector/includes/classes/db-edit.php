<?php

/*
 * Database Edit methods container class of Plugins Garbage Collector WordPress plugin
 * Author: Vladimir Garagulya
 * Author email: vladimir@shinephp.com
 * Author URI: http://shinephp.com
 * License: GPL v2+
 * 
*/

class PGC_DB_Edit {
    
    private static function get_tables_to_delete( $post_tables ) {
        global $wpdb;
        
        $wp_tables = $wpdb->tables('all', true );        
        $tables = array();
        foreach ( $post_tables as $table) {
            // do not touch WordPress built-in tables
            if ( !in_array( $table, $wp_tables, true ) ) {
                $tables[] = $table;
            }
        }

        return $tables;
    }
    // end of get_tables_to_delete()


    private static function switch_off_foreigh_key_check() {
        global $wpdb;

// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Static query with no variables/placeholders; $wpdb->prepare() is not required and would trigger _doing_it_wrong().
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- PGC requires direct database access for table inspection and cleanup. Backtick-escaped db table name identifier.
        $result = $wpdb->get_row( "SHOW VARIABLES LIKE 'FOREIGN_KEY_CHECKS'" );
        $fkc_value = $result->Value;
        if ( $fkc_value == 'ON' ) {
// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Static query with no variables/placeholders; $wpdb->prepare() is not required and would trigger _doing_it_wrong().            
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- PGC requires direct database access for table inspection and cleanup. Backtick-escaped db table name identifier.            
            $wpdb->query( 'SET FOREIGN_KEY_CHECKS=0' );
        }

        return $fkc_value;
    }
    // end of pgc_switch_off_foreign_key_check()


    private static function restore_foreign_key_check( $old_fkc_value ) {
        global $wpdb;

        if ($old_fkc_value == 'ON' || $old_fkc_value == 1) {
// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Static query with no variables/placeholders; $wpdb->prepare() is not required and would trigger _doing_it_wrong().            
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- PGC requires direct database access for table inspection and cleanup. Backtick-escaped db table name identifier.                        
            $wpdb->query( 'SET FOREIGN_KEY_CHECKS=1' );
        }
    }
    // end of restore_foreign_key_check()
    

    public static function delete_unused_db_tables( $tables ) {
        global $wpdb;

        $tables = self::get_tables_to_delete( $tables );
        if ( empty( $tables ) ) {
            return;
        }

        $old_fkc_value = self::switch_off_foreigh_key_check();
        $action_result = '';
        foreach ( $tables as $table ) {
            if ( $action_result ) {
                $action_result .= ', ';
            }
// Manually escape backticks for legacy WP compatibility
            $safe_table_name = '`' . str_replace( '`', '``', $table ) . '`';            
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter  -- PGC requires direct database access for table inspection and cleanup. Db tables which don't belong to WP deletion is the PGC main task.  Backtick-escaped db table name identifier. Backtick-escaped DB table name identifier. Escaped via sanitize_text_field( wp_unslash() ) earlier.
            $wpdb->query( "DROP TABLE {$safe_table_name}" );
            if ( $wpdb->last_error ) {
                if ( $action_result ) {
                    $action_result = esc_html__('Tables are deleted: ', 'plugins-garbage-collector') . $action_result;
                }
                self::restore_foreign_key_check( $old_fkc_value );
                return $action_result . ' ' . $wpdb->last_error;
            }
            $action_result .= ' ' . $table;
        }

        self::restore_foreign_key_check( $old_fkc_value );

        return esc_html__('Tables are deleted successfully: ', 'plugins-garbage-collector') . esc_html( $action_result );
    }
    // end of delete_unused_db_tables()


    public static function delete_extra_columns_from_wp_tables() {

        $message = esc_html__('This feature is still under development and will be realized in the future version', 'plugins-garbage-collector');
  
        return $message;
    }
    // end of deleteExtraColumnsFromWPTables()

    
}
// end of PGC_DB_Edit class
