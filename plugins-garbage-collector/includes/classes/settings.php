<?php
/*
 * Project: Database Cleanup (former Plugins Garbage Collector)
 * Saves to the show/hide flag to the plugin options record
 * Author: Vladimir Garagulya
 * Author email: vladimir@shinephp.com
 * Author URI: http://shinephp.com
 * License: GPL v2+
 * 
*/

class PGC_Settings {

    const OPTION_ID = 'pgc_settings';
    
    public static function get_option() {
        $data = get_option( self::OPTION_ID );
        if ( !$data ) {
            $data = array();
            $data['hidden'] = array();
        }
        
        return $data;
    }
    // end of get_option()
    
    public static function show_table( $table_name ) {
        
        $data = self::get_option();
        if ( isset($data['hidden'][$table_name] ) ) {
            unset( $data['hidden'][$table_name] );
        }
        update_option( self::OPTION_ID, $data);
        $answer = array('result'=>'success', 'message'=>esc_html__('Table was shown', 'plugins-garbage-collector'));
        
        return $answer;
    }
    // end of pgc_show_table()    


    public static function hide_table( $table_name ) {

        $data = self::get_option();        
        $data['hidden'][$table_name] = 1;
        update_option( self::OPTION_ID, $data);
        $answer = array('result'=>'success', 'message'=> esc_html__('Table was hidden', 'plugins-garbage-collector'));
        
        return $answer;
    }
    // end of hide_table()

    
    public static function update_hidden_tables_list( $tables ) {

        $data = self::get_option();
        if ( empty( $data['hidden'] ) ) {
            return;
        }
        
        $update_needed = false;
        foreach ( array_keys( $data['hidden'] ) as $table_name ) {
            $found = false;
            foreach ( $tables as $table ) {
                if ( $table->name === $table_name ) {
                    $found = true;
                    break;
                }
            }
            if ( !$found ) {
                unset( $data['hidden'][$table_name] );
                $update_needed = true;
            }
        }
        if ($update_needed) {
            update_option( self::OPTION_ID, $data );
        }
    }
    // end of update_hidden_tables_list()
    
}
// end of PGC_Settings class
