<?php
/*
 * Main class of Plugins Garbage Collector WordPress plugin
 * Author: Vladimir Garagulya
 * Author email: vladimir@shinephp.com
 * Author URI: http://shinephp.com
 * License: GPL v2+
 * 
*/

class Plugins_Garbage_Collector {
    
    static ?Plugins_Garbage_Collector $instance = null;
    
        
    /**
     * Singleton template.
     *
     * @return user_switching User Switching instance.
     */
    public static function get_instance(): Plugins_Garbage_Collector {
        

        if ( self::$instance===null ) {
            self::$instance = new Plugins_Garbage_Collector();
        }

        return self::$instance;
    }
    // end of get_instance()            
    
    /*
     * Do not instantiate class directly, via get_instance() only
     */
    private function __construct() {
                        
    }
    // end of __construct()
    
    
    public function run() {
                                
        add_action( 'admin_init', [$this, 'init'], 1);

        // add menu item
        add_action( 'admin_menu', [$this, 'plugin_menu'] );
        
        // set AJAX requests processing hook
        add_action( 'wp_ajax_plugins_garbage_collector', ['PGC_Ajax', 'dispatch'] );
        
    }
    // end of run()
           
    
    public function init() {
                                
        // add a Settings link in the installed plugins page
        add_filter( 'plugin_action_links', array($this, 'plugin_action_links'), 10, 2 );
        add_filter( 'plugin_row_meta', array($this, 'plugin_row_meta'), 10, 2 );

        // Add the translation
        load_plugin_textdomain( 'plugins-garbage-collector', false, basename( dirname( PGC_Bootstrap::get_plugin_file() ) ) .'/lang/' );
        
    }
    // end of init()
    
            
    public function plugin_action_links( $links, $file ) {
        
        if ( $file === PGC_Bootstrap::get_plugin_basename() ) {
            $settings_link = '<a href="tools.php?page=plugins-garbage-collector.php">'. esc_html__('Scan','plugins-garbage-collector') .'</a>';
            array_unshift( $links, $settings_link );
        }
        
        return $links;
        
    }
    // end of plugin_action_links()
    
    
    public function plugin_row_meta( $links, $file ) {
    
        if ( $file === PGC_Bootstrap::get_plugin_basename() ) {
            $links[] = '<a target="_blank" href="https://www.shinephp.com/plugins-garbage-collector-wordpress-plugin/#changelog">'. esc_html__('Changelog', 'plugins-garbage-collector').'</a>';
        }
        
        return $links;
        
    }
    // end of plugin_row_meta()


    private function is_it_right_post() {
        
        $result = ['result'=>false, 'action'=>'', 'message'=>''];
        if ( !isset( $_POST['action'] ) ) {
            // It's not our turn this time
            return $result;
        }                        
        
        if ( !check_admin_referer( 'plugins-garbage-collector-options', '_wpnonce' ) ) {
            $result['message'] = esc_html__('Database Cleanup: Wrong or expired request', 'plugins-garbage-collector' );
            return $result;
        }
     
        if ( !current_user_can( PGC_Bootstrap::CAPABILITY ) ) {
            $result['message'] = esc_html__('You do not have sufficient permissions.', 'plugins-garbage-collector' );
            return $result;
        }

        $action = sanitize_text_field( wp_unslash( $_POST['action'] ) );
        if ( !in_array( $action, ['delete_selected_tables', 'delete_extra_columns'], true ) ) {
            $result['message'] = esc_html__('Database Cleanup: Wrong action!', 'plugins-garbage-collector');
            return $result;
        }

        if ( $action==='delete_selected_tables' ) {
            $post_keys = array_keys( $_POST );
            $tables_esc = [];
            foreach( $post_keys as $value ) {
                $val_sec = trim( sanitize_text_field( wp_unslash( $value ) ) );
                if ( strpos( $val_sec, 'delete_' ) === 0 ) {
                    $table_name = strtolower( substr( $val_sec, 7 ) );
                    // Only accept well-formed MySQL identifiers - reject anything crafted to break out of this shape.
                    if ( preg_match( '/^[a-zA-Z0-9_]+$/', $table_name ) ) {
                        $tables_esc[] = $table_name;
                    }
                }
            }
            $result['tables'] = $tables_esc;
        } else {
            // Process input parameters for the 'delete_extra_columns' action here
        }
        $result['result'] = true;
        $result['action'] = $action;
                
        return $result;
    } 
    // end of is_id_right_post()
    
    
    private function process_post_actions() {
        
        $result = $this->is_it_right_post();
        if ( $result['result']===false ) {
            return $result['message'];
        }
                                
        if ( $result['action']==='delete_selected_tables' ) {
            
            $mess = PGC_DB_Edit::delete_unused_db_tables( $result['tables'] );
        } else if ( $result['action']==='delete_extra_columns' ) {
            $mess = PGC_DB_Edit::delete_extra_columns_from_wp_tables();
        } else {
            $mess = '';
        }

        return $mess;        
    }
    // end of process_post_actions()
    
    
    public function actions_page() {
                                               
        PGC_Known_Plugins::refresh_data();        
        $mess = $this->process_post_actions();
        
        require_once( PGC_Bootstrap::get_plugin_dir() . 'includes/options.php' );

    }
    // end of actions_page()
        
    
    public function admin_css() {

        wp_enqueue_style( 
            'pgc_jquery_ui', 
            PGC_Bootstrap::get_plugin_url() .'css/vendors/jquery-ui/jquery-ui.min.css', 
            array(), 
            PGC_Bootstrap::VERSION, 
            'screen' 
            );
        wp_enqueue_style( 
            'pgc_admin_css', 
            PGC_Bootstrap::get_plugin_url() .'css/pgc-admin.css', 
            array(), 
            PGC_Bootstrap::VERSION, 
            'screen' 
            );

    }
    // end of admin_css()

    
    function admin_scripts() {

        wp_enqueue_script( 
                'pgc_js_script', 
                PGC_Bootstrap::get_plugin_url() . 'js/pgc.js', 
                ['jquery', 'jquery-form', 'jquery-ui-core', 'jquery-ui-progressbar'], 
                PGC_Bootstrap::VERSION,
                true
                );
        wp_localize_script(
                'pgc_js_script', 
                'pgcSettings', 
                ['plugin_url' => PGC_Bootstrap::get_plugin_url(),
                'ajax_nonce' => wp_create_nonce('plugins-garbage-collector'),
                'ajax_url' => admin_url('admin-ajax.php'),
                'redirect_url' => admin_url('/tools.php?page=plugins-garbage-collector.php&action='),
                'turn_on_cb_before_scan' => esc_html__('Turn on at least one Search checkbox before start Scan process!', 'plugins-garbage-collector'),
                'receive_plugins_list' => esc_html__('Receive plugins list', 'plugins-garbage-collector'),
                'scanning' => esc_html__('Scanning', 'plugins-garbage-collector'),
                'checking_plugin' => esc_html__('Checking plugin', 'plugins-garbage-collector'),
                'take_some_time' => esc_html__('will take some time. Please confirm to continue', 'plugins-garbage-collector'),
                'select_table_before_delete' => esc_html__('Select at least one table before click on Delete button', 'plugins-garbage-collector'),
                'confirm_before_tables_deletion1' => esc_html__('These tables will be permanently deleted. Continue?', 'plugins-garbage-collector'),
                'confirm_before_tables_deletion2' => esc_html__('Delete database tables last confirmation: Click "Cancel" if you have any doubt.', 'plugins-garbage-collector'),
                'confirm_before_column_deletion1' => esc_html__('These columns will be permanently deleted. Continue?', 'plugins-garbage-collector'),
                'confirm_before_column_deletion2' => esc_html__('Delete database table columns last confirmation: Click "Cancel" if you have any doubt.', 'plugins-garbage-collector'),    
                'work_done' => esc_html('Done', 'plugins-garbage-collector')
                ]);
        
    }
    // end of admin_scripts()


    public function plugin_menu() {        

        if ( !function_exists( 'add_management_page' ) ) {
            return;
        }
        
        $pgc_page = add_management_page( 
                __('Cleanup Database', 'plugins-garbage-collector'), 
                __('Cleanup Database', 'plugins-garbage-collector'), 
                PGC_Bootstrap::CAPABILITY,
                'plugins-garbage-collector',
                [$this, 'actions_page'] );
        add_action( "admin_print_styles-$pgc_page", array($this, 'admin_css') );
        add_action( "admin_print_scripts-$pgc_page", array($this, 'admin_scripts') );
    
    }
    // end of plugin_menu()
     

}
// end of Plugins_Garbage_Collector
