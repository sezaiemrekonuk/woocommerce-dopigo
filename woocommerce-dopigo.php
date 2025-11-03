<?php
/**
 * Plugin Name: WooCommerce Dopigo Integration
 * Plugin URI: https://yoursite.com
 * Description: Complete integration between Dopigo marketplace and WooCommerce. Sync products, manage inventory, and automate order processing.
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://yoursite.com
 * Text Domain: woocommerce-dopigo
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.0
 * WC requires at least: 3.0
 * WC tested up to: 8.0
 *
 * @package WooCommerce_Dopigo
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}
// Define plugin constants
define( 'WOOCOMMERCE_DOPIGO_VERSION', '1.0.0' );
define( 'WOOCOMMERCE_DOPIGO_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WOOCOMMERCE_DOPIGO_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WOOCOMMERCE_DOPIGO_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
/**
 * Check if WooCommerce is active
 */
function woocommerce_dopigo_check_woocommerce() {
    if ( ! class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', 'woocommerce_dopigo_missing_wc_notice' );
        return false;
    }
    return true;
}
/**
 * Display notice if WooCommerce is not active
 */
function woocommerce_dopigo_missing_wc_notice() {
    ?>
    <div class="error">
        <p>
            <?php _e( '<strong>WooCommerce Dopigo Integration</strong> requires WooCommerce to be installed and activated.', 'woocommerce-dopigo' ); ?>
        </p>
    </div>
    <?php
}
/**
 * Initialize the plugin
 */
function woocommerce_dopigo_init() {
    // Check if WooCommerce is active
    if ( ! woocommerce_dopigo_check_woocommerce() ) {
        return;
    }
    // Load plugin textdomain
    load_plugin_textdomain( 'woocommerce-dopigo', false, dirname( WOOCOMMERCE_DOPIGO_PLUGIN_BASENAME ) . '/languages' );
    // Include core files
    require_once WOOCOMMERCE_DOPIGO_PLUGIN_DIR . 'src/dopigo.php';
    require_once WOOCOMMERCE_DOPIGO_PLUGIN_DIR . 'src/class-dopigo-product-mapper.php';
    
    // Include admin files only in admin
if ( is_admin() ) {
        require_once WOOCOMMERCE_DOPIGO_PLUGIN_DIR . 'src/class-dopigo-admin.php';
    }
    // Initialize hooks
    add_action( 'init', 'woocommerce_dopigo_register_post_types' );
    
    // Add settings link to plugins page
    add_filter( 'plugin_action_links_' . WOOCOMMERCE_DOPIGO_PLUGIN_BASENAME, 'woocommerce_dopigo_action_links' );
}
add_action( 'plugins_loaded', 'woocommerce_dopigo_init' );
/**
 * Register custom post types if needed
 */
function woocommerce_dopigo_register_post_types() {
    // Register custom post types here if needed
}
/**
 * Add plugin action links
 */
function woocommerce_dopigo_action_links( $links ) {
    $action_links = array(
        'settings' => '<a href="' . admin_url( 'admin.php?page=dopigo-settings' ) . '">' . __( 'Settings', 'woocommerce-dopigo' ) . '</a>',
        'import'   => '<a href="' . admin_url( 'admin.php?page=dopigo-import' ) . '">' . __( 'Import Products', 'woocommerce-dopigo' ) . '</a>',
    );
    return array_merge( $action_links, $links );
}
/**
 * Plugin activation hook
 */
function woocommerce_dopigo_activate() {
    // Check if WooCommerce is active
    if ( ! class_exists( 'WooCommerce' ) ) {
        deactivate_plugins( WOOCOMMERCE_DOPIGO_PLUGIN_BASENAME );
        wp_die( __( 'WooCommerce Dopigo Integration requires WooCommerce to be installed and activated.', 'woocommerce-dopigo' ) );
    }
    // Set default options
    add_option( 'dopigo_api_key', '' );
    add_option( 'dopigo_username', '' );
    add_option( 'dopigo_password', '' );
    add_option( 'dopigo_auto_sync', false );
    add_option( 'dopigo_sync_interval', 'hourly' );
    add_option( 'dopigo_sync_skip_images', false ); // Import images by default
    add_option( 'dopigo_category_map', array() );
    add_option( 'dopigo_sync_history', array() );
    // Add activation timestamp
    update_option( 'dopigo_activated_at', time() );
    // Flush rewrite rules
    flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'woocommerce_dopigo_activate' );
/**
 * Plugin deactivation hook
 */
function woocommerce_dopigo_deactivate() {
    // Unschedule cron jobs
    wp_clear_scheduled_hook( 'dopigo_sync_products' );
    
    // Flush rewrite rules
    flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'woocommerce_dopigo_deactivate' );
/**
 * Plugin uninstall hook
 */
function woocommerce_dopigo_uninstall() {
    // Note: This function is registered in uninstall.php
}
/**
 * Add custom cron schedules
 */
function woocommerce_dopigo_cron_schedules( $schedules ) {
    $schedules['every_15_minutes'] = array(
        'interval' => 900,
        'display'  => __( 'Every 15 Minutes', 'woocommerce-dopigo' )
    );
    
    $schedules['every_30_minutes'] = array(
        'interval' => 1800,
        'display'  => __( 'Every 30 Minutes', 'woocommerce-dopigo' )
    );
    
    return $schedules;
}
add_filter( 'cron_schedules', 'woocommerce_dopigo_cron_schedules' );
/**
 * Schedule sync task if auto sync is enabled
 */
function woocommerce_dopigo_schedule_sync() {
    $auto_sync = get_option( 'dopigo_auto_sync', false );
    $interval = get_option( 'dopigo_sync_interval', 'hourly' );
    // Always clear existing schedule first
    wp_clear_scheduled_hook( 'dopigo_sync_products' );
    // Schedule new task if auto sync is enabled
    if ( $auto_sync ) {
        // Calculate proper next run time based on interval
        $next_run_time = woocommerce_dopigo_get_next_run_time( $interval );
        $current_time = time();
        
        // Safety check: ensure next run is in the future
        if ( $next_run_time <= $current_time ) {
             
                
            // Add interval to current time as fallback
            $interval_seconds = 900; // 15 minutes default
            if ( $interval === 'every_30_minutes' ) {
                $interval_seconds = 1800; // 30 minutes
            }
            $next_run_time = $current_time + $interval_seconds;
        }
        
        // Schedule the event
        wp_schedule_event( $next_run_time, $interval, 'dopigo_sync_products' );
        
        $next_run = wp_next_scheduled( 'dopigo_sync_products' );
        $time_until = $next_run ? $next_run - $current_time : 0;            
    }
}
/**
 * Calculate next run time for custom intervals
 */
function woocommerce_dopigo_get_next_run_time( $interval ) {
    $current_time = time();
    $current_minute = (int) date( 'i', $current_time );
    $current_hour = (int) date( 'H', $current_time );
    $current_day = (int) date( 'd', $current_time );
    $current_month = (int) date( 'm', $current_time );
    $current_year = (int) date( 'Y', $current_time );
    
    switch ( $interval ) {
        case 'every_15_minutes':
            // Calculate next 15-minute mark (00, 15, 30, 45)
            $next_minute = ( (int) ( $current_minute / 15 ) + 1 ) * 15;
            
            // If we've passed 45, go to next hour at 00
            if ( $next_minute >= 60 ) {
                $next_minute = 0;
                $next_hour = $current_hour + 1;
                
                // Handle day/month rollover
                if ( $next_hour >= 24 ) {
                    $next_hour = 0;
                    $next_day = $current_day + 1;
                    $days_in_month = (int) date( 't', $current_time );
                    if ( $next_day > $days_in_month ) {
                        $next_day = 1;
                        $next_month = $current_month + 1;
                        if ( $next_month > 12 ) {
                            $next_month = 1;
                            $next_year = $current_year + 1;
                        } else {
                            $next_year = $current_year;
                        }
                    } else {
                        $next_month = $current_month;
                        $next_year = $current_year;
                    }
                } else {
                    $next_day = $current_day;
                    $next_month = $current_month;
                    $next_year = $current_year;
                }
            } else {
                $next_hour = $current_hour;
                $next_day = $current_day;
                $next_month = $current_month;
                $next_year = $current_year;
            }
            
            $next_time = mktime( $next_hour, $next_minute, 0, $next_month, $next_day, $next_year );
            
            // Ensure it's at least 1 minute in the future
            if ( $next_time - $current_time < 60 ) {
                $next_time += 15 * 60; // Add 15 minutes
            }
            
            return $next_time;
            
        case 'every_30_minutes':
            // Calculate next 30-minute mark (00, 30)
            $next_minute = $current_minute < 30 ? 30 : 0;
            
            if ( $next_minute == 0 ) {
                $next_hour = $current_hour + 1;
                if ( $next_hour >= 24 ) {
                    $next_hour = 0;
                    $next_day = $current_day + 1;
                    $days_in_month = (int) date( 't', $current_time );
                    if ( $next_day > $days_in_month ) {
                        $next_day = 1;
                        $next_month = $current_month + 1;
                        $next_year = $next_month > 12 ? $current_year + 1 : $current_year;
                        $next_month = $next_month > 12 ? 1 : $next_month;
                    } else {
                        $next_month = $current_month;
                        $next_year = $current_year;
                    }
                } else {
                    $next_day = $current_day;
                    $next_month = $current_month;
                    $next_year = $current_year;
                }
            } else {
                $next_hour = $current_hour;
                $next_day = $current_day;
                $next_month = $current_month;
                $next_year = $current_year;
            }
            
            $next_time = mktime( $next_hour, $next_minute, 0, $next_month, $next_day, $next_year );
            
            // Ensure it's at least 1 minute in the future
            if ( $next_time - $current_time < 60 ) {
                $next_time += 30 * 60; // Add 30 minutes
            }
            
            return $next_time;
            
        default:
            // For standard intervals (hourly, daily, etc.), schedule 1 minute from now
            // WordPress will handle the recurring schedule
            return $current_time + 60;
    }
}
add_action( 'update_option_dopigo_auto_sync', 'woocommerce_dopigo_schedule_sync' );
add_action( 'update_option_dopigo_sync_interval', 'woocommerce_dopigo_schedule_sync' );

/**
 * Sync products cron job
 */
function woocommerce_dopigo_sync_products_cron() {
    // Increase execution time and memory limit for sync
    @set_time_limit( 600 ); // 10 minutes
    @ini_set( 'max_execution_time', 600 );
    @ini_set( 'memory_limit', '512M' );
    
    $api_key = get_option( 'dopigo_api_key', '' );
    $debug = defined( 'WP_DEBUG' ) && WP_DEBUG;
    
    if ( empty( $api_key ) ) {
        $message = 'Dopigo Sync: API key not configured';
        
        
        // Log to history
        $history = get_option( 'dopigo_sync_history', array() );
        $history[] = array(
            'timestamp'      => time(),
            'type'           => 'auto_sync',
            'status'         => 'error',
            'products_count' => 0,
            'message'        => $message
        );
        $history = array_slice( $history, -100 );
        update_option( 'dopigo_sync_history', $history );
        
        return;
    }
    require_once WOOCOMMERCE_DOPIGO_PLUGIN_DIR . 'src/dopigo.php';
    require_once WOOCOMMERCE_DOPIGO_PLUGIN_DIR . 'src/class-dopigo-product-mapper.php';
    $start_time = microtime( true );
    try {
        
        // Get all products from Dopigo with pagination and debug logging
        $response = get_all_dopigo_products_paginated( $api_key, $debug );
        if ( $response === false ) {
            throw new Exception( 'Failed to fetch products from Dopigo API' );
        }
        $products = isset( $response['results'] ) ? $response['results'] : array();
        $total_count = isset( $response['total_count'] ) ? $response['total_count'] : count( $products );
        $fetched_count = count( $products );
        
        if ( empty( $products ) ) {
            throw new Exception( 'No products returned from Dopigo API' );
        }
        
        // Import products - check if images should be skipped
        $skip_images = get_option( 'dopigo_sync_skip_images', false ); // Default to false to import images
        $import_start = microtime( true );
        $product_ids = Dopigo_Product_Mapper::batch_import_from_dopigo( $products, $skip_images );
        $import_duration = round( microtime( true ) - $import_start, 2 );
        $imported_count = count( $product_ids );
        $total_duration = round( microtime( true ) - $start_time, 2 );
        
        // Log success
        $history = get_option( 'dopigo_sync_history', array() );
        $history[] = array(
            'timestamp'      => time(),
            'type'           => 'auto_sync',
            'status'         => 'success',
            'products_count' => $imported_count,
            'message'        => sprintf( 
                __( 'Auto-synced %d/%d products in %s seconds', 'woocommerce-dopigo' ), 
                $imported_count, 
                $fetched_count,
                $total_duration
            )
        );
        $history = array_slice( $history, -100 );
        update_option( 'dopigo_sync_history', $history );
        
        } catch ( Exception $e ) {
        $error_message = $e->getMessage();
        $total_duration = round( microtime( true ) - $start_time, 2 );
        
        // Log error with full details
        
        
        // Log stack trace if debug is enabled
        
        
        $history = get_option( 'dopigo_sync_history', array() );
        $history[] = array(
            'timestamp'      => time(),
            'type'           => 'auto_sync',
            'status'         => 'error',
            'products_count' => 0,
            'message'        => $error_message
        );
        $history = array_slice( $history, -100 );
        update_option( 'dopigo_sync_history', $history );
    }
}
add_action( 'dopigo_sync_products', 'woocommerce_dopigo_sync_products_cron' );
add_action( 'dopigo_sync_products_manual', 'woocommerce_dopigo_sync_products_cron' );
add_action( 'dopigo_sync_products_progress', 'woocommerce_dopigo_sync_products_with_progress' );
/**
 * Sync products with progress tracking
 */
function woocommerce_dopigo_sync_products_with_progress( $progress_key ) {
    // Increase execution time and memory limit for sync
    @set_time_limit( 600 ); // 10 minutes
    @ini_set( 'max_execution_time', 600 );
    @ini_set( 'memory_limit', '512M' );
    
    $api_key = get_option( 'dopigo_api_key', '' );
    $debug = defined( 'WP_DEBUG' ) && WP_DEBUG;
    if ( empty( $api_key ) ) {
        require_once WOOCOMMERCE_DOPIGO_PLUGIN_DIR . 'src/class-dopigo-product-mapper.php';
        Dopigo_Product_Mapper::update_progress( $progress_key, array(
            'status' => 'error',
            'message' => 'API key not configured'
        ) );
        return;
    }
    require_once WOOCOMMERCE_DOPIGO_PLUGIN_DIR . 'src/dopigo.php';
    require_once WOOCOMMERCE_DOPIGO_PLUGIN_DIR . 'src/class-dopigo-product-mapper.php';
    $start_time = microtime( true );
    try {
        // Get all products from Dopigo with pagination and debug logging
        $response = get_all_dopigo_products_paginated( $api_key, $debug );
        if ( $response === false ) {
            throw new Exception( 'Failed to fetch products from Dopigo API' );
        }
        $products = isset( $response['results'] ) ? $response['results'] : array();
        $total_count = isset( $response['total_count'] ) ? $response['total_count'] : count( $products );
        $fetched_count = count( $products );
        if ( empty( $products ) ) {
            throw new Exception( 'No products returned from Dopigo API' );
        }
        // Import products with progress tracking
        $skip_images = get_option( 'dopigo_sync_skip_images', false ); // Default to false to import images
        $import_start = microtime( true );
        $product_ids = Dopigo_Product_Mapper::batch_import_from_dopigo( $products, $skip_images, $progress_key );
        $import_duration = round( microtime( true ) - $import_start, 2 );
        $imported_count = count( $product_ids );
        $total_duration = round( microtime( true ) - $start_time, 2 );
        // Log success
        $history = get_option( 'dopigo_sync_history', array() );
        $history[] = array(
            'timestamp'      => time(),
            'type'           => 'manual_sync',
            'status'         => 'success',
            'products_count' => $imported_count,
            'message'        => sprintf( 
                __( 'Synced %d/%d products in %s seconds', 'woocommerce-dopigo' ), 
                $imported_count, 
                $fetched_count,
                $total_duration
            )
        );
        $history = array_slice( $history, -100 );
        update_option( 'dopigo_sync_history', $history );
        // Final progress update
        Dopigo_Product_Mapper::update_progress( $progress_key, array(
            'status' => 'completed',
            'message' => sprintf( 'Successfully synced %d products', $imported_count ),
            'duration' => $total_duration
        ) );
    } catch ( Exception $e ) {
        $error_message = $e->getMessage();
        
        // Update progress with error
        Dopigo_Product_Mapper::update_progress( $progress_key, array(
            'status' => 'error',
            'message' => $error_message
        ) );
        
        // Log error
        $history = get_option( 'dopigo_sync_history', array() );
        $history[] = array(
            'timestamp'      => time(),
            'type'           => 'manual_sync',
            'status'         => 'error',
            'products_count' => 0,
            'message'        => $error_message
        );
        $history = array_slice( $history, -100 );
        update_option( 'dopigo_sync_history', $history );
    }
}
/**
 * Add REST API endpoints
 */
function woocommerce_dopigo_register_rest_routes() {
    register_rest_route( 'dopigo/v1', '/webhook/stock-update', array(
        'methods'             => 'POST',
        'callback'            => 'woocommerce_dopigo_webhook_stock_update',
        'permission_callback' => 'woocommerce_dopigo_verify_webhook',
    ) );
}
add_action( 'rest_api_init', 'woocommerce_dopigo_register_rest_routes' );
/**
 * Verify webhook signature
 */
function woocommerce_dopigo_verify_webhook( WP_REST_Request $request ) {
    // Implement webhook signature verification
    // For now, return true - implement proper security in production
    return true;
}
/**
 * Handle stock update webhook
 */
function woocommerce_dopigo_webhook_stock_update( WP_REST_Request $request ) {
    $params = $request->get_json_params();
    if ( ! isset( $params['product_id'] ) || ! isset( $params['available_stock'] ) ) {
        return new WP_REST_Response( 
            array( 'error' => 'Missing required parameters' ), 
            400 
        );
    }
    require_once WOOCOMMERCE_DOPIGO_PLUGIN_DIR . 'src/class-dopigo-product-mapper.php';
    $success = Dopigo_Product_Mapper::sync_stock_from_dopigo(
        $params['product_id'],
        $params['available_stock']
    );
    if ( $success ) {
        return new WP_REST_Response( 
            array( 'success' => true, 'message' => 'Stock updated' ), 
            200 
        );
    } else {
        return new WP_REST_Response( 
            array( 'error' => 'Product not found' ), 
            404 
        );
    }
}
/**
 * Get plugin version
 */
function woocommerce_dopigo_get_version() {
    return WOOCOMMERCE_DOPIGO_VERSION;
}


