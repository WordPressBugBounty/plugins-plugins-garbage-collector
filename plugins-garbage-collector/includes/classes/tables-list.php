<?php
/*
 * Show database tables list with information to which plugin they belong
 * Author: Vladimir Garagulya
 * Author email: vladimir@shinephp.com
 * Author URI: http://shinephp.com
 * License: GPL v2+
 * 
*/
class PGC_Tables_List {
    
    private static function get_column_headers() {
      $html = '<tr>
              <th>'. esc_html__('Hide', 'plugins-garbage-collector').'</th>
              <th>'. esc_html__('Table Name', 'plugins-garbage-collector').'</th>
              <th>'. esc_html__('Records #', 'plugins-garbage-collector').'</th>
              <th>'. esc_html__('KBytes #', 'plugins-garbage-collector').'</th>
              <th>'. esc_html__('Plugin Name' ,'plugins-garbage-collector').'</th>
              <th>'. esc_html__('State', 'plugins-garbage-collector').'</th>
            </tr>';
    return $html;
    }
    // pgc_display_column_headers_none_wp()


    private static function translate_plugin_state( $state ) {

        if ($state === 'active') {
            $translated = esc_html__('active', 'plugins-garbage-collector');
        } else if ($state == 'inactive') {
            $translated = esc_html__('inactive', 'plugins-garbage-collector');
        } else if ($state === 'unused') {
            $translated = esc_html__('unused', 'plugins-garbage-collector');
        } else {
            $translated = esc_html( $state );
        }

        return $translated;
    }
    // end of pgc_translate_plugin_state()
    

    private static function get_table_row( $row_class, $table, $checked, &$show_delete_tables_button ) {
                
        $table_name = esc_attr( $table->name );
        $html = '<tr ' . $row_class . ' id="' . $table_name . '" >
              <td style="width:50px;">';
        $html .= '<input type="checkbox" name="hidden_' . $table_name . '" id="hidden_' . $table_name . '" onclick="pgc_hide_table(this, \'' . esc_js( $table->name ) . '\')" ' . $checked . ' />
          <img id="ajax_' . $table_name . '" class="ajax_processing" src="' . admin_url('images/loading.gif') . '" alt="ajax request processing..." title="AJAX request processing..."/>';
            $html .= '</td>
        <td style="vertical-align:top;width:300px;" >';
            $delete_check_box = '';
            if ( empty( $table->plugin_name ) || $table->plugin_state == 'unused') {
                $color = 'red';
                $delete_check_box = '<input type="checkbox" name="delete_' . $table_name . '" />';
                $show_delete_tables_button = true;
            } else if ($table->plugin_state === 'active') {
                $color = 'green';
            } else {
                $color = 'blue';
            }
            $html .= $delete_check_box . ' <span style="color:' . $color . ';">' . esc_html( $table->name ) . '</span>';
            $html .= '
        </td>
        <td style="width:100px;text-align: right;">
          <span style="color:' . $color . ';">' . esc_html( $table->records ) . '</span>
        </td>
        <td style="width:100px;text-align: right;">
          <span style="color:' . $color . ';">' . esc_html( $table->kbytes ) . '</span>
        </td>
        <td>';
            if ( $table->plugin_name ) {
                $html .= '<span style="color:' . $color . ';">' . esc_html( $table->plugin_name ) . '</span>';
            } else {
                $html .= '<span style="color:red;">Unknown</span>';
            }
            $plugin_state = self::translate_plugin_state( $table->plugin_state );
            $html .= '
        </td>
        <td>
          <span style="color:' . $color . ';">' . $plugin_state . '</span>
        </td>
      </tr>';
      
      return $html;
      
    }
    // end get_table_row()
    
    
    private static function get_tables( $tables, $show_hidden_tables ) {                
        
        $html = esc_html__('Let\'s see what tables in your database do not belong to the core WordPress installation:', 'plugins-garbage-collector');
        $pgc_settings = get_option('pgc_settings');
        $html .= '
       <table id="pgc_plugin_tables" class="widefat" style="clear:none;" cellpadding="0" cellspacing="0">
          <thead>';
        $html .= self::get_column_headers();
        $html .= '
          </thead>
          <tbody>';

        $show_delete_tables_button = false;
        $hidden_table_exists = false;
        $i = 0;
        foreach ( $tables as $table ) {
            if ($i & 1) {
                $row_class = 'class="pgc_odd"';
            } else {
                $row_class = 'class="pgc_even"';
            }
            $hidden_table = isset( $pgc_settings['hidden'][$table->name] );
            if ( $hidden_table && ! $show_hidden_tables ) {  // skip this table
                $hidden_table_exists = true;
                continue;
            }
            if ($hidden_table) {
                $checked = 'checked="checked"';
            } else {
                $checked = '';
            }
            $i++;
            $html .= self::get_table_row( $row_class, $table, $checked, $show_delete_tables_button );
        }
        $html .= '
            </tbody>
            <tfoot>';
        $html .= self::get_column_headers();
        $html .= '
      </tfoot>
  </table>';
        if ( $hidden_table_exists ) {
            $html .= '<span style="color: #bbb; font-size: 0.8em;">' . esc_html__('Some tables are hidden by you. Turn on "Show hidden DB tables" option and click "Scan" button again to show them.', 'plugins-garbage-collector') . '</span>';
        }
        if ( $show_delete_tables_button) {
            $html .= '
      <table>
        <tr>
          <td>
            <div class="submit">
              <input type="button" class="button" name="drop_table_action" value="' . esc_html__('Delete selected tables', 'plugins-garbage-collector') . '" onclick="pgc_delete_selected_tables();" />
              <input type="hidden" name="action" id="action" value="delete_selected_tables" />
            </div>
          </td>
          <td>
            <div style="padding-left: 10px;"><span style="color: red; font-weight: bold;">' . esc_html__('Attention!', 'plugins-garbage-collector') . '</span> ' .
                        esc_html__('Operation rollback is not possible. Consider to make database backup first. Please double think before click "Delete selected tables" button.', 'plugins-garbage-collector') . '
            </div>
          </td>
        </tr>
      </table>';
            }

        return $html;        
    }
    // end of get_tables()
    
    
    private static function show_db_is_clean() {
        $html = '<br><br><br><br><br><div class="postbox">'. PHP_EOL;
  	$html .= '<div class="inside">'. PHP_EOL;
        $html .= '<span style="color: green; text-align: center; font-size: 1.2em;">' . PHP_EOL;
        $html .= esc_html__('Congratulations! It seems that your WordPress database is clean.', 'plugins-garbage-collector') . PHP_EOL; 
        $html .= '</span>'. PHP_EOL;
        $html .= '</div>' . PHP_EOL;
	$html .= '</div>'. PHP_EOL;

        return $html;
    }
    // end of show_db_is_clean()
    
    
    public static function show( $show_hidden_tables ) {

        if ( isset( $_SESSION['plugins-garbage-collector'] ) && 
             isset( $_SESSION['plugins-garbage-collector']['tables'] ) && 
             is_array( $_SESSION['plugins-garbage-collector']['tables'] ) &&   
             count( $_SESSION['plugins-garbage-collector']['tables'] ) > 0 ) {
            // sanitize_key keeps only a-z, 0-9, _, and -
            $sanitized_tables0 = map_deep( $_SESSION['plugins-garbage-collector']['tables'], 'sanitize_text_field' );    
            // Remove any empty items that failed sanitization
            $sanitized_tables = array_filter( $sanitized_tables0 );
            $html = self::get_tables( $sanitized_tables, $show_hidden_tables );
        } else {
            $html = self::show_db_is_clean();
        }

        unset( $_SESSION['plugins-garbage-collector']['tables'] );
        
        return $html;
    }
    // end of show()
    
}
// end of PGC_Tables_List class