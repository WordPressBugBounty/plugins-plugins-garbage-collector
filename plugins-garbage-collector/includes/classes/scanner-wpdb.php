<?php
/*
 * Scan WordPress database tables for structure changes made comparing to the initial state
 * Author: Vladimir Garagulya
 * Author email: vladimir@shinephp.com
 * Author URI: http://shinephp.com
 * License: GPL v2+
 * 
*/

class PGC_Scanner_WPDB {
    
    /*
     *  Get all of the field names in the query from between the parens
     */

    private static function extract_field_names($query) {

        $columns = array();
        $match2 = array();
        preg_match("|\((.*)\)|ms", strtolower($query), $match2);
        $line = trim($match2[1]);
        // Separate field lines into an array
        $fields = explode("\n", $line);
        // For every field line specified in the query
        foreach ($fields as $field) {
            $validfield = true;
            $field = trim($field);
            // Extract the field name
            $fvalue = array();
            preg_match("|^([^ ]*)|", $field, $fvalue);
            $fieldname = strtolower(trim($fvalue[1], '`'));
            // Verify the found field name
            switch ($fieldname) {
                case '':
                case 'primary':
                case 'index':
                case 'fulltext':
                case 'unique':
                case 'key':
                    $validfield = false;
                    break;
            }

            // If it's a valid field, add it to the field array
            if ($validfield) {
                $columns[$fieldname] = 1;
            }
        }

        return $columns;
    }
    // end of extract_field_names()


    public static function check_wp_tables_structure() {

        global $wpdb;

        $admin_path = str_replace(get_bloginfo('url') . '/', ABSPATH, get_admin_url());
        require_once($admin_path . 'includes/schema.php');

        $wp_db_schema = wp_get_db_schema('all');
// Separate individual queries into an array
        $queries = explode(';', $wp_db_schema);
        if ('' == $queries[count($queries) - 1]) {
            array_pop($queries);
        }

        $wp_tables_list = array();
        $matches = [];
        foreach ( $queries as $query ) {
            if ( preg_match("|CREATE TABLE ([^ ]*)|", $query, $matches ) ) {
                $wp_tables_list[ trim( strtolower( $matches[1] ), '`') ] = $query;
            }
        }

        $changed_tables = array();
        $i = 1;
        foreach ( $wp_tables_list as $table => $create_query) {
            // orginal structure columns list
            $orig_columns = self::extract_field_names( $create_query );

// Manually escape backticks for legacy WP compatibility
            $safe_table_name = '`' . str_replace( '`', '``', $table ) . '`';
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter  -- PGC requires direct database access for table inspection and cleanup. Backtick-escaped db table name identifier, which went through sanitize_text_field( wp_unslash() ) earlier.                   
            $fact_columns = $wpdb->get_results( "DESCRIBE {$safe_table_name}" );
            foreach ( $fact_columns as $fact_column ) {
                if ( !isset( $orig_columns[strtolower( $fact_column->Field)] ) ) {
                    if ( !isset( $changed_tables[$table] ) ) {
                        $changed_tables[$table] = [];
                    }
                    $changed_tables[$table][$fact_column->Field] = new stdClass();
                    $changed_tables[$table][$fact_column->Field]->plugin_name = '';
                    $changed_tables[$table][$fact_column->Field]->plugin_state = '';
                }
            }
        }

        return $changed_tables;        
    }
    // end of check_wp_tables_structure()

    
    public static function get_scan_results( $show_hidden_tables ) {
        
        $changed_tables = self::check_wp_tables_structure();
        $data = PGC_Tables_List_WPDB::show( $changed_tables, $show_hidden_tables );
        
        return $data;
    }
    // end of get_scan_results()

        
}
// end of PGC_Scanner_WP
