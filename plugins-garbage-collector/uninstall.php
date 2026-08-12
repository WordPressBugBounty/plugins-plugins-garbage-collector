<?php
/*
 * Uninstall script for Database Cleanup (former Plugins Garbage Collector)
 * Author: Vladimir Garagulya
 * Author email: vladimir@shinephp.com
 * Author URI: http://shinephp.com
 * License: GPL v2+
 *
 * Runs when the plugin is deleted from the "Plugins" screen (never on deactivation) and
 * removes everything it left behind:
 *  - the "pgc_settings" option (on every site, if this is a multisite network)
 *  - the wp-content/uploads/pgc-data/ cache directory
 */

// Guards against direct access/execution; WordPress only defines this constant right before
// including an uninstall.php it invoked itself from wp-admin/includes/plugin.php.
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

require_once __DIR__ . '/includes/classes/bootstrap.php';
require_once __DIR__ . '/includes/classes/settings.php';


function pgc_uninstall_delete_settings_option() {

    delete_option( PGC_Settings::OPTION_ID );
}
// end of pgc_uninstall_delete_settings_option()


function pgc_uninstall_delete_settings_option_everywhere() {

    if ( ! is_multisite() ) {
        pgc_uninstall_delete_settings_option();
        return;
    }

    $site_ids = get_sites( array( 'fields' => 'ids' ) );
    foreach ( $site_ids as $site_id ) {
        switch_to_blog( $site_id );
        pgc_uninstall_delete_settings_option();
        restore_current_blog();
    }
}
// end of pgc_uninstall_delete_settings_option_everywhere()


function pgc_uninstall_delete_data_dir() {

    $upload_dir = wp_upload_dir();
    if ( empty( $upload_dir['basedir'] ) || ! empty( $upload_dir['error'] ) ) {
        return;
    }

    $data_dir = trailingslashit( $upload_dir['basedir'] ) . PGC_Bootstrap::DATA_DIR_NAME;
    if ( ! is_dir( $data_dir ) ) {
        return;
    }

    require_once ABSPATH . 'wp-admin/includes/file.php';
    WP_Filesystem();
    global $wp_filesystem;
    if ( $wp_filesystem ) {
        $wp_filesystem->delete( $data_dir, true, 'd' );
    }
}
// end of pgc_uninstall_delete_data_dir()


pgc_uninstall_delete_settings_option_everywhere();
pgc_uninstall_delete_data_dir();
