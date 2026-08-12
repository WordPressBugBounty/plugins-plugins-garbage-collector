<?php
/*
 * Show WordPress database tables list which structure was modified
 * Author: Vladimir Garagulya
 * Author email: vladimir@shinephp.com
 * Author URI: http://shinephp.com
 * License: GPL v2+
 * 
*/
class PGC_Tables_List_WPDB {
    
    private static function show_column_headers() {
        $html = '<tr>
              <th>'.esc_html__('Hide','plugins-garbage-collector').'</th>
              <th>'.esc_html__('Table Name','plugins-garbage-collector').'</th>
              <th>'.esc_html__('Extra Field','plugins-garbage-collector').'</th>
              <th>'.esc_html__('Plugin Name','plugins-garbage-collector').'</th>
              <th>'.esc_html__('Plugin State','plugins-garbage-collector').'</th>
            </tr>';
        return $html;

    }
    // end of show_column_headers()
    
    private static function show_row( $row_class, $table_name, $column, $plugin, $hidden_table, &$show_delete_button) {
        $table_name_esc = esc_attr( $table_name );
        $html = '<tr '.$row_class.' id="'.$table_name_esc.'" >
                      <td>';
          if ($plugin->plugin_state=='active') {
            if ( $hidden_table ) {
              $checked = 'checked="checked"';
            } else {
              $checked = '';
            }
            $html .= '<input type="checkbox" name="hidden_'. $table_name_esc .'" id="hidden_'. $table_name_esc .'" onclick="pgc_hide_table(this, \''. esc_js( $table_name ) .'\')" '.$checked.' />
                      <img id="ajax_'. $table_name_esc .'" class="ajax_processing" src="'. admin_url('images/loading.gif') .'" alt="ajax request processing..." title="AJAX request processing..."/>';
          }
          $html .= '</td>
                <td style="vertical-align:top;width:300px;" >';
          $delete_check_box = '';
          if ( !$plugin->plugin_name ) {
            $color = 'red';
            $delete_check_box = '<input type="checkbox" name="delete_'. $table_name_esc .'" />';
            $show_delete_button = true;
          } else if ( $plugin->plugin_state=='active') {
            $color = 'green';
          } else {
            $color = 'blue';
          }
          $html .= $delete_check_box.' <span style="color:'.$color.';">'. esc_html( $table_name ) .'</span>';
          $html .= '
                </td>
                <td><span style="color:'.$color.';">'. esc_html( $column ).'</span></td><td>';
          if ( $plugin->plugin_name ) {
            $html .= '<span style="color:'. $color .';">'. esc_html( $plugin->plugin_name ) .'</span>';
          } else {
            $html .= '<span style="color:red;">unknown</span>';
          }
          $html .= '</td>
                <td><span style="color:'.$color.';">'. esc_html( $plugin->plugin_state ) .'</span></td>
              </tr>';
          
          return $html;
    }
    // end of show_row()


    private static function show_delete_extra_column() {
        $html = '
          <table>
            <tr>
              <td>
                <div class="submit">
                  <input class="button" type="button" name="delete_extra_columns_action" value="'. esc_html__('Delete Extra Columns', 'plugins-garbage-collector'). '" onclick="pgc_delete_extra_columns();"/>
                  <input type="hidden" name="action" id="action" value="delete_extra_columns" />
                </div>
              </td>
              <td>
                <div style="padding-left: 10px;"><span style="color: red; font-weight: bold;">'.esc_html__('Attention!','plugins-garbage-collector').'</span> '.
                  esc_html__('Operation rollback is not possible. Consider to make database backup first. Please double think before click <code>Delete Extra Columns</code> button.','plugins-garbage-collector').'
                </div>
              </td>
            </tr>
          </table>';        
        
        return $html;
    }
    // end of show_delete_extra_column()
    
    
    private static function show_tables( $changed_tables, $show_hidden_tables ) {
        $html = '
           <table id="pgc_plugin_tables" class="widefat" style="clear:none;" cellpadding="0" cellspacing="0">
              <thead>'
          . self::show_column_headers() .
              '</thead>
              <tbody>';
        $settings = PGC_Settings::get_option();
        $show_delete_button = false;
        $hidden_table_exists  = false;
        $i = 0;
        foreach ( $changed_tables as $table_name => $column_data ) {
            foreach ($column_data as $column => $plugin) {
                $hidden_table = isset($settings['hidden'][$table_name]);
                if ( $hidden_table && !$show_hidden_tables ) {
                    // skip this table
                    $hidden_table_exists = true;
                    continue;
                }
                $row_class = ($i & 1) ? 'class="pgc_odd"' : 'class="pgc_even"';
                $i++;
                $html .= self::show_row( $row_class, $table_name, $column, $plugin, $hidden_table, $show_delete_button );
            }
        }
        $html .= '</tbody>
              <tfoot>'
        . self::show_column_headers() .
              '</tfoot>
          </table>';
        if ( $hidden_table_exists ) {
          $html .= '<span style="color: #bbb; font-size: 0.8em;">'.esc_html__('Some tables are hidden by you. Turn on "Show hidden DB tables" option and click "Scan" button again to show them.', 'plugins-garbage-collector').'</span>';
        }
        if ( $show_delete_button ) {
          $html .= self::show_delete_extra_column();
        }
    
        return $html;
    }
    // end of $show_tables()
    
    
    private static function show_db_clean_message() {
        $html = '
    <span style="color: green; text-align: center; font-size: 1.2em;">'.
      esc_html__('Congratulations! It seems that your WordPress database tables structure is not changed','plugins-garbage-collector').'
    </span>';
        
        return $html;
    }
    // end of show_db_clean_message()
    
    
    public static function show( $changed_tables, $show_hidden_tables ) {
        if ( count( $changed_tables )>0 ) {
            $html = self::show_tables( $changed_tables, $show_hidden_tables );
        } else {
            $html = self::show_db_clean_message();
        }
        $data = ['result'=>'success', 'html'=>$html];
        
        return $data;
    }
    // end of show()
    
}
// end of PGC_Tables_List_WPDB class
