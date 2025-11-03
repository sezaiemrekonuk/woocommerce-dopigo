<?php
/**
 * WooCommerce Dopigo Integration Uninstall
 *
 * Uninstalling WooCommerce Dopigo Integration deletes options and scheduled tasks.
 *
 * @package WooCommerce_Dopigo
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Delete plugin options
delete_option( 'dopigo_api_key' );
delete_option( 'dopigo_username' );
delete_option( 'dopigo_password' );
delete_option( 'dopigo_auto_sync' );
delete_option( 'dopigo_sync_interval' );
delete_option( 'dopigo_category_map' );
delete_option( 'dopigo_sync_history' );
delete_option( 'dopigo_activated_at' );

// Clear scheduled tasks
wp_clear_scheduled_hook( 'dopigo_sync_products' );

// Optional: Remove Dopigo metadata from products
// Uncomment if you want to remove all Dopigo-related metadata on uninstall
/*
global $wpdb;
$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_dopigo_%'" );
*/

