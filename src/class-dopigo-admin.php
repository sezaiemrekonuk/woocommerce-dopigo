<?php
/**
 * Dopigo Admin Settings
 * 
 * Handles admin interface, settings page, and API key management
 * 
 * @package WooCommerce_Dopigo
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Dopigo_Admin {

    /**
     * Constructor
     */
    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
        add_action( 'wp_ajax_dopigo_get_all_products', array( $this, 'ajax_get_all_products' ) );
        add_action( 'wp_ajax_dopigo_test_connection', array( $this, 'ajax_test_connection' ) );
        add_action( 'wp_ajax_dopigo_import_products', array( $this, 'ajax_import_products' ) );
        add_action( 'wp_ajax_dopigo_import_categories', array( $this, 'ajax_import_categories' ) );
        add_action( 'wp_ajax_dopigo_generate_token_ajax', array( $this, 'ajax_generate_token' ) );
        add_action( 'wp_ajax_dopigo_trigger_sync', array( $this, 'ajax_trigger_sync' ) );
        add_action( 'wp_ajax_dopigo_reschedule_sync', array( $this, 'ajax_reschedule_sync' ) );
        add_action( 'wp_ajax_dopigo_get_sync_progress', array( $this, 'ajax_get_sync_progress' ) );
        
        // Ensure schedule is correct when loading settings page
        add_action( 'admin_init', array( $this, 'ensure_schedule_on_settings_page' ) );
    }
    
    /**
     * Ensure schedule is correct on settings page load
     */
    public function ensure_schedule_on_settings_page() {
        // Only run on Dopigo settings page
        if ( ! isset( $_GET['page'] ) || $_GET['page'] !== 'dopigo-settings' ) {
            return;
        }
        
        $auto_sync = get_option( 'dopigo_auto_sync', false );
        $next_run = wp_next_scheduled( 'dopigo_sync_products' );
        
        // If auto sync is enabled but no schedule exists or it's in the past, reschedule
        if ( $auto_sync && ( ! $next_run || $next_run < time() ) ) {
            woocommerce_dopigo_schedule_sync();
        }
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __( 'Dopigo Settings', 'woocommerce-dopigo' ),
            __( 'Dopigo', 'woocommerce-dopigo' ),
            'manage_woocommerce',
            'dopigo-settings',
            array( $this, 'render_settings_page' ),
            'dashicons-cart',
            56
        );

        add_submenu_page(
            'dopigo-settings',
            __( 'Settings', 'woocommerce-dopigo' ),
            __( 'Settings', 'woocommerce-dopigo' ),
            'manage_woocommerce',
            'dopigo-settings',
            array( $this, 'render_settings_page' )
        );

        add_submenu_page(
            'dopigo-settings',
            __( 'Import Products', 'woocommerce-dopigo' ),
            __( 'Import Products', 'woocommerce-dopigo' ),
            'manage_woocommerce',
            'dopigo-import',
            array( $this, 'render_import_page' )
        );

        add_submenu_page(
            'dopigo-settings',
            __( 'Sync Status', 'woocommerce-dopigo' ),
            __( 'Sync Status', 'woocommerce-dopigo' ),
            'manage_woocommerce',
            'dopigo-sync',
            array( $this, 'render_sync_page' )
        );
    }

    /**
     * Register settings
     */
    public function register_settings() {
        // Register settings
        register_setting( 'dopigo_settings_group', 'dopigo_api_key', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => ''
        ) );

        register_setting( 'dopigo_settings_group', 'dopigo_username', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => ''
        ) );

        register_setting( 'dopigo_settings_group', 'dopigo_password', array(
            'type'              => 'string',
            'sanitize_callback' => array( $this, 'sanitize_password' ),
            'default'           => ''
        ) );

        register_setting( 'dopigo_settings_group', 'dopigo_auto_sync', array(
            'type'              => 'boolean',
            'sanitize_callback' => 'rest_sanitize_boolean',
            'default'           => false
        ) );

        register_setting( 'dopigo_settings_group', 'dopigo_sync_interval', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => 'hourly'
        ) );

        register_setting( 'dopigo_settings_group', 'dopigo_category_map', array(
            'type'              => 'array',
            'sanitize_callback' => array( $this, 'sanitize_category_map' ),
            'default'           => array()
        ) );

        register_setting( 'dopigo_settings_group', 'dopigo_sync_skip_images', array(
            'type'              => 'boolean',
            'sanitize_callback' => 'rest_sanitize_boolean',
            'default'           => false // Default to importing images
        ) );

        register_setting( 'dopigo_settings_group', 'dopigo_xml_feed_url', array(
            'type'              => 'string',
            'sanitize_callback' => 'esc_url_raw',
            'default'           => ''
        ) );

        // Add settings sections
        add_settings_section(
            'dopigo_auth_section',
            __( 'Authentication Settings', 'woocommerce-dopigo' ),
            array( $this, 'render_auth_section' ),
            'dopigo-settings'
        );

        add_settings_section(
            'dopigo_sync_section',
            __( 'Synchronization Settings', 'woocommerce-dopigo' ),
            array( $this, 'render_sync_section' ),
            'dopigo-settings'
        );

        // Add settings fields
        add_settings_field(
            'dopigo_api_key',
            __( 'API Key / Auth Token', 'woocommerce-dopigo' ),
            array( $this, 'render_api_key_field' ),
            'dopigo-settings',
            'dopigo_auth_section'
        );

        add_settings_field(
            'dopigo_username',
            __( 'Username (Optional)', 'woocommerce-dopigo' ),
            array( $this, 'render_username_field' ),
            'dopigo-settings',
            'dopigo_auth_section'
        );

        add_settings_field(
            'dopigo_password',
            __( 'Password (Optional)', 'woocommerce-dopigo' ),
            array( $this, 'render_password_field' ),
            'dopigo-settings',
            'dopigo_auth_section'
        );

        add_settings_field(
            'dopigo_auto_sync',
            __( 'Enable Auto Sync', 'woocommerce-dopigo' ),
            array( $this, 'render_auto_sync_field' ),
            'dopigo-settings',
            'dopigo_sync_section'
        );

        add_settings_field(
            'dopigo_sync_interval',
            __( 'Sync Interval', 'woocommerce-dopigo' ),
            array( $this, 'render_sync_interval_field' ),
            'dopigo-settings',
            'dopigo_sync_section'
        );

        add_settings_field(
            'dopigo_sync_skip_images',
            __( 'Skip Images During Sync', 'woocommerce-dopigo' ),
            array( $this, 'render_skip_images_field' ),
            'dopigo-settings',
            'dopigo_sync_section'
        );

        add_settings_field(
            'dopigo_xml_feed_url',
            __( 'Dopigo XML Feed URL', 'woocommerce-dopigo' ),
            array( $this, 'render_xml_feed_url_field' ),
            'dopigo-settings',
            'dopigo_sync_section'
        );
    }

    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts( $hook ) {
        if ( strpos( $hook, 'dopigo' ) === false ) {
            return;
        }

        wp_enqueue_style( 'dopigo-admin-css', plugin_dir_url( dirname( __FILE__ ) ) . 'assets/css/admin.css', array(), '1.0.0' );
        wp_enqueue_script( 'dopigo-admin-js', plugin_dir_url( dirname( __FILE__ ) ) . 'assets/js/admin.js', array( 'jquery' ), '1.0.0', true );
        
        // Add inline styles for progress display
        wp_add_inline_style( 'dopigo-admin-css', '
            .dopigo-status-badge {
                display: inline-block;
                padding: 4px 12px;
                border-radius: 3px;
                font-size: 12px;
                font-weight: 600;
                text-transform: uppercase;
            }
            .dopigo-status-badge.running {
                background: #2271b1;
                color: #fff;
            }
            .dopigo-status-badge.completed {
                background: #46b450;
                color: #fff;
            }
            .dopigo-status-badge.error {
                background: #dc3232;
                color: #fff;
            }
        ' );

        wp_localize_script( 'dopigo-admin-js', 'dopigoAdmin', array(
            'ajaxurl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'dopigo_admin_nonce' ),
            'strings' => array(
                'testing'       => __( 'Testing connection...', 'woocommerce-dopigo' ),
                'fetching'      => __( 'Fetching products...', 'woocommerce-dopigo' ),
                'importing'     => __( 'Importing products...', 'woocommerce-dopigo' ),
                'success'       => __( 'Success!', 'woocommerce-dopigo' ),
                'error'         => __( 'Error occurred', 'woocommerce-dopigo' ),
                'confirm'       => __( 'Are you sure you want to import all products?', 'woocommerce-dopigo' ),
                'fetchingCategories' => __( 'Fetching categories...', 'woocommerce-dopigo' ),
                'importingCategories' => __( 'Importing categories...', 'woocommerce-dopigo' ),
                'importCategoriesSuccess' => __( '✓ Successfully created %created% new categories and updated %updated% existing categories.', 'woocommerce-dopigo' ),
                'pendingCategories' => __( 'The following Dopigo category IDs still need mapping: %pending%. Please import categories again once they are available.', 'woocommerce-dopigo' ),
                'readFileError' => __( 'Could not read the selected file. Please try again.', 'woocommerce-dopigo' ),
            )
        ) );
    }

    /**
     * Sanitize password
     */
    public function sanitize_password( $password ) {
        // Encrypt password before saving
        if ( ! empty( $password ) ) {
            return base64_encode( $password );
        }
        return '';
    }

    /**
     * Sanitize category map
     */
    public function sanitize_category_map( $map ) {
        if ( ! is_array( $map ) ) {
            return array();
        }

        $sanitized = array();
        foreach ( $map as $dopigo_cat => $wc_cat ) {
            $sanitized[ intval( $dopigo_cat ) ] = intval( $wc_cat );
        }

        return $sanitized;
    }

    /**
     * Render auth section
     */
    public function render_auth_section() {
        echo '<p>' . __( 'Enter your Dopigo API credentials. You can either provide an API Key/Token directly, or enter username and password to generate a token.', 'woocommerce-dopigo' ) . '</p>';
    }

    /**
     * Render sync section
     */
    public function render_sync_section() {
        echo '<p>' . __( 'Configure automatic synchronization settings.', 'woocommerce-dopigo' ) . '</p>';
    }

    /**
     * Render API key field
     */
    public function render_api_key_field() {
        $api_key = get_option( 'dopigo_api_key', '' );
        ?>
        <input type="text" 
               name="dopigo_api_key" 
               id="dopigo_api_key" 
               value="<?php echo esc_attr( $api_key ); ?>" 
               class="regular-text" 
               placeholder="<?php esc_attr_e( 'Enter your API Key or Auth Token', 'woocommerce-dopigo' ); ?>" />
        <p class="description">
            <?php _e( 'Your Dopigo API authentication token. If you don\'t have one, enter username and password below to generate it.', 'woocommerce-dopigo' ); ?>
        </p>
        <button type="button" id="dopigo-test-connection" class="button button-secondary" style="margin-top: 10px;">
            <?php _e( 'Test Connection', 'woocommerce-dopigo' ); ?>
        </button>
        <span id="dopigo-test-result" style="margin-left: 10px;"></span>
        <?php
    }

    /**
     * Render username field
     */
    public function render_username_field() {
        $username = get_option( 'dopigo_username', '' );
        ?>
        <input type="text" 
               name="dopigo_username" 
               id="dopigo_username" 
               value="<?php echo esc_attr( $username ); ?>" 
               class="regular-text" 
               placeholder="<?php esc_attr_e( 'Dopigo username', 'woocommerce-dopigo' ); ?>" />
        <p class="description">
            <?php _e( 'Optional: Used to generate API token if not provided above.', 'woocommerce-dopigo' ); ?>
        </p>
        <?php
    }

    /**
     * Render password field
     */
    public function render_password_field() {
        $password = get_option( 'dopigo_password', '' );
        $decrypted = ! empty( $password ) ? base64_decode( $password ) : '';
        ?>
        <input type="password" 
               name="dopigo_password" 
               id="dopigo_password" 
               value="<?php echo esc_attr( $decrypted ); ?>" 
               class="regular-text" 
               placeholder="<?php esc_attr_e( 'Dopigo password', 'woocommerce-dopigo' ); ?>" />
        <p class="description">
            <?php _e( 'Optional: Used to generate API token if not provided above.', 'woocommerce-dopigo' ); ?>
        </p>
        <button type="button" id="dopigo-generate-token" class="button button-secondary" style="margin-top: 10px;">
            <?php _e( 'Generate Token from Credentials', 'woocommerce-dopigo' ); ?>
        </button>
        <?php
    }

    /**
     * Render auto sync field
     */
    public function render_auto_sync_field() {
        $auto_sync = get_option( 'dopigo_auto_sync', false );
        ?>
        <label>
            <input type="checkbox" 
                   name="dopigo_auto_sync" 
                   id="dopigo_auto_sync" 
                   value="1" 
                   <?php checked( $auto_sync, true ); ?> />
            <?php _e( 'Enable automatic product synchronization', 'woocommerce-dopigo' ); ?>
        </label>
        <?php
    }

    /**
     * Render sync interval field
     */
    public function render_sync_interval_field() {
        $interval = get_option( 'dopigo_sync_interval', 'hourly' );
        ?>
        <select name="dopigo_sync_interval" id="dopigo_sync_interval">
            <option value="every_15_minutes" <?php selected( $interval, 'every_15_minutes' ); ?>><?php _e( 'Every 15 Minutes', 'woocommerce-dopigo' ); ?></option>
            <option value="every_30_minutes" <?php selected( $interval, 'every_30_minutes' ); ?>><?php _e( 'Every 30 Minutes', 'woocommerce-dopigo' ); ?></option>
            <option value="hourly" <?php selected( $interval, 'hourly' ); ?>><?php _e( 'Hourly', 'woocommerce-dopigo' ); ?></option>
            <option value="twicedaily" <?php selected( $interval, 'twicedaily' ); ?>><?php _e( 'Twice Daily', 'woocommerce-dopigo' ); ?></option>
            <option value="daily" <?php selected( $interval, 'daily' ); ?>><?php _e( 'Daily', 'woocommerce-dopigo' ); ?></option>
        </select>
        <?php
    }

    /**
     * Render skip images field
     */
    public function render_skip_images_field() {
        $skip_images = get_option( 'dopigo_sync_skip_images', false ); // Default to false so images are imported
        ?>
        <label>
            <input type="checkbox" 
                   name="dopigo_sync_skip_images" 
                   id="dopigo_sync_skip_images" 
                   value="1" 
                   <?php checked( $skip_images, true ); ?> />
            <?php _e( 'Skip downloading images during sync (much faster)', 'woocommerce-dopigo' ); ?>
        </label>
        <p class="description">
            <?php _e( 'When enabled, images will be skipped during sync to speed up the process. Image URLs will be stored in product meta and can be downloaded later. <strong>Uncheck this to import images with your products.</strong>', 'woocommerce-dopigo' ); ?>
        </p>
        <?php
    }

    /**
     * Render XML feed URL field
     */
    public function render_xml_feed_url_field() {
        $feed_url = get_option( 'dopigo_xml_feed_url', '' );
        ?>
        <input type="url"
               name="dopigo_xml_feed_url"
               id="dopigo_xml_feed_url"
               value="<?php echo esc_attr( $feed_url ); ?>"
               class="regular-text"
               placeholder="<?php esc_attr_e( 'https://itbox.dopigo.com/api/products/xml/?page=1&page_size=100', 'woocommerce-dopigo' ); ?>" />
        <p class="description">
            <?php _e( 'This feed is used to fetch categories automatically during category import. Leave blank to upload XML files manually.', 'woocommerce-dopigo' ); ?>
        </p>
        <?php
    }

    /**
     * Render settings page
     */
    public function render_settings_page() {
        require_once WOOCOMMERCE_DOPIGO_PLUGIN_DIR . 'templates/admin-settings-page.php';
    }

    /**
     * Render import page
     */
    public function render_import_page() {
        require_once WOOCOMMERCE_DOPIGO_PLUGIN_DIR . 'templates/admin-import-page.php';
    }

    /**
     * Render sync page
     */
    public function render_sync_page() {
        require_once WOOCOMMERCE_DOPIGO_PLUGIN_DIR . 'templates/admin-sync-page.php';
    }

    /**
     * Render connection status
     */
    private function render_connection_status() {
        $api_key = get_option( 'dopigo_api_key', '' );
        
        if ( empty( $api_key ) ) {
            echo '<p style="color: #dc3232;"><span class="dashicons dashicons-warning"></span> ' . __( 'Not Connected - API Key not configured', 'woocommerce-dopigo' ) . '</p>';
            return;
        }

        echo '<p><span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span> ' . __( 'API Key configured', 'woocommerce-dopigo' ) . '</p>';
        echo '<p><small>' . __( 'API Key:', 'woocommerce-dopigo' ) . ' ' . substr( $api_key, 0, 10 ) . '...' . substr( $api_key, -5 ) . '</small></p>';
    }

    /**
     * Render synced products list
     */
    private function render_synced_products_list() {
        global $wpdb;

        $synced_products = $wpdb->get_results( "
            SELECT p.ID, p.post_title, pm.meta_value as dopigo_id
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'product'
            AND pm.meta_key = '_dopigo_meta_id'
            ORDER BY p.post_date DESC
            LIMIT 50
        " );

        if ( empty( $synced_products ) ) {
            echo '<p>' . __( 'No synchronized products found.', 'woocommerce-dopigo' ) . '</p>';
            return;
        }

        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr>';
        echo '<th>' . __( 'Product ID', 'woocommerce-dopigo' ) . '</th>';
        echo '<th>' . __( 'Product Name', 'woocommerce-dopigo' ) . '</th>';
        echo '<th>' . __( 'Dopigo Meta ID', 'woocommerce-dopigo' ) . '</th>';
        echo '<th>' . __( 'Actions', 'woocommerce-dopigo' ) . '</th>';
        echo '</tr></thead>';
        echo '<tbody>';

        foreach ( $synced_products as $product ) {
            echo '<tr>';
            echo '<td>' . esc_html( $product->ID ) . '</td>';
            echo '<td><a href="' . get_edit_post_link( $product->ID ) . '">' . esc_html( $product->post_title ) . '</a></td>';
            echo '<td>' . esc_html( $product->dopigo_id ) . '</td>';
            echo '<td><a href="' . get_permalink( $product->ID ) . '" class="button button-small" target="_blank">' . __( 'View', 'woocommerce-dopigo' ) . '</a></td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
    }

    /**
     * Render sync history
     */
    private function render_sync_history() {
        $history = get_option( 'dopigo_sync_history', array() );

        if ( empty( $history ) ) {
            echo '<p>' . __( 'No sync history available.', 'woocommerce-dopigo' ) . '</p>';
            return;
        }

        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr>';
        echo '<th>' . __( 'Date', 'woocommerce-dopigo' ) . '</th>';
        echo '<th>' . __( 'Type', 'woocommerce-dopigo' ) . '</th>';
        echo '<th>' . __( 'Status', 'woocommerce-dopigo' ) . '</th>';
        echo '<th>' . __( 'Products', 'woocommerce-dopigo' ) . '</th>';
        echo '<th>' . __( 'Message', 'woocommerce-dopigo' ) . '</th>';
        echo '</tr></thead>';
        echo '<tbody>';

        foreach ( array_slice( array_reverse( $history ), 0, 20 ) as $log ) {
            echo '<tr>';
            echo '<td>' . esc_html( date( 'Y-m-d H:i:s', $log['timestamp'] ) ) . '</td>';
            echo '<td>' . esc_html( $log['type'] ) . '</td>';
            echo '<td>' . esc_html( $log['status'] ) . '</td>';
            echo '<td>' . esc_html( $log['products_count'] ) . '</td>';
            echo '<td>' . esc_html( $log['message'] ) . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
    }

    /**
     * AJAX: Get all products
     */
    public function ajax_get_all_products() {
        check_ajax_referer( 'dopigo_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied', 'woocommerce-dopigo' ) ) );
        }

        $api_key = get_option( 'dopigo_api_key', '' );

        if ( empty( $api_key ) ) {
            wp_send_json_error( array( 'message' => __( 'API Key not configured', 'woocommerce-dopigo' ) ) );
        }

        // Use the paginated function with debug enabled
        require_once WOOCOMMERCE_DOPIGO_PLUGIN_DIR . 'src/dopigo.php';
        
        $debug = defined( 'WP_DEBUG' ) && WP_DEBUG;
        if ( $debug ) {
            error_log( 'Dopigo: Starting AJAX fetch of all products with pagination...' );
        }
        
        $products = get_all_dopigo_products_paginated( $api_key, $debug );

        if ( $products === false ) {
            if ( $debug ) {
                error_log( 'Dopigo: Failed to fetch products from Dopigo API' );
            }
            wp_send_json_error( array( 'message' => __( 'Failed to fetch products from Dopigo', 'woocommerce-dopigo' ) ) );
        }

        $count = isset( $products['count'] ) ? $products['count'] : count( $products['results'] );
        $total_count = isset( $products['total_count'] ) ? $products['total_count'] : $count;
        
        if ( $debug ) {
            error_log( sprintf( 'Dopigo: Successfully fetched %d products (expected: %d)', $count, $total_count ) );
        }

        wp_send_json_success( array(
            'products' => $products,
            'count'    => $count,
            'total_count' => $total_count
        ) );
    }

    /**
     * AJAX: Test connection
     */
    public function ajax_test_connection() {
        check_ajax_referer( 'dopigo_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied', 'woocommerce-dopigo' ) ) );
        }

        $api_key = get_option( 'dopigo_api_key', '' );

        if ( empty( $api_key ) ) {
            wp_send_json_error( array( 'message' => __( 'API Key not configured', 'woocommerce-dopigo' ) ) );
        }

        // Test the connection
        $response = wp_remote_get( 'https://panel.dopigo.com/api/v1/products/all/', array(
            'headers' => array(
                'Authorization' => 'Token ' . $api_key
            ),
            'timeout' => 15
        ) );

        if ( is_wp_error( $response ) ) {
            wp_send_json_error( array( 'message' => $response->get_error_message() ) );
        }

        $code = wp_remote_retrieve_response_code( $response );

        if ( $code === 200 ) {
            wp_send_json_success( array( 'message' => __( 'Connection successful!', 'woocommerce-dopigo' ) ) );
        } else {
            wp_send_json_error( array( 'message' => sprintf( __( 'Connection failed with status code: %d', 'woocommerce-dopigo' ), $code ) ) );
        }
    }

    /**
     * AJAX: Import products
     */
    public function ajax_import_products() {
        check_ajax_referer( 'dopigo_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied', 'woocommerce-dopigo' ) ) );
        }

        $products = isset( $_POST['products'] ) ? json_decode( stripslashes( $_POST['products'] ), true ) : array();
        
        // Handle both direct array and paginated response structure
        if ( isset( $products['results'] ) && is_array( $products['results'] ) ) {
            $products = $products['results'];
        } elseif ( ! is_array( $products ) || empty( $products ) ) {
            wp_send_json_error( array( 'message' => __( 'No products to import', 'woocommerce-dopigo' ) ) );
        }

        $product_count = count( $products );
        
        // Increase execution time and memory limit for import
        @set_time_limit( 600 ); // 10 minutes
        @ini_set( 'max_execution_time', 600 );
        @ini_set( 'memory_limit', '512M' );
        
        $debug = defined( 'WP_DEBUG' ) && WP_DEBUG;
        if ( $debug ) {
            error_log( sprintf( 'Dopigo: Starting import of %d products...', $product_count ) );
        }

        require_once WOOCOMMERCE_DOPIGO_PLUGIN_DIR . 'src/class-dopigo-product-mapper.php';
        require_once WOOCOMMERCE_DOPIGO_PLUGIN_DIR . 'src/class-dopigo-category-mapper.php';

        try {
            // Check if images should be skipped during manual import
            $skip_images = get_option( 'dopigo_sync_skip_images', false ); // Default to false to import images
            $start_time = microtime( true );
            $product_ids = Dopigo_Product_Mapper::batch_import_from_dopigo( $products, $skip_images );
            $end_time = microtime( true );
            $duration = round( $end_time - $start_time, 2 );

            $imported_count = count( $product_ids );
            if ( $debug ) {
                error_log( sprintf( 'Dopigo: Successfully imported %d/%d products in %s seconds', 
                    $imported_count, 
                    $product_count, 
                    $duration 
                ) );
            }

            // Log the sync
            $this->log_sync( 'import', 'success', $imported_count, 
                sprintf( __( 'Imported %d/%d products in %s seconds', 'woocommerce-dopigo' ), 
                    $imported_count, 
                    $product_count, 
                    $duration 
                ) 
            );

            wp_send_json_success( array(
                'message'     => sprintf( __( 'Successfully imported %d/%d products', 'woocommerce-dopigo' ), $imported_count, $product_count ),
                'product_ids' => $product_ids,
                'imported'    => $imported_count,
                'total'       => $product_count,
                'duration'    => $duration,
                'pending_categories' => Dopigo_Category_Mapper::get_pending_categories(),
            ) );

        } catch ( Exception $e ) {
            $error_message = $e->getMessage();
            if ( $debug ) {
                error_log( sprintf( 'Dopigo: Import error: %s', $error_message ) );
                error_log( 'Dopigo: Import error stack trace: ' . $e->getTraceAsString() );
            }
            $this->log_sync( 'import', 'error', 0, $error_message );
            wp_send_json_error( array( 
                'message' => $error_message,
                'error_type' => get_class( $e )
            ) );
        }
    }

    /**
     * AJAX: Generate token from username/password
     */
    public function ajax_generate_token() {
        check_ajax_referer( 'dopigo_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied', 'woocommerce-dopigo' ) ) );
        }

        $username = isset( $_POST['username'] ) ? sanitize_text_field( $_POST['username'] ) : '';
        $password = isset( $_POST['password'] ) ? $_POST['password'] : '';

        if ( empty( $username ) || empty( $password ) ) {
            wp_send_json_error( array( 'message' => __( 'Username and password are required', 'woocommerce-dopigo' ) ) );
        }

        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'dopigo.php';
        
        $token = get_auth_token( $username, $password );

        if ( $token === false ) {
            wp_send_json_error( array( 'message' => __( 'Failed to generate token. Please check your credentials.', 'woocommerce-dopigo' ) ) );
        }

        wp_send_json_success( array(
            'token'   => $token,
            'message' => __( 'Token generated successfully!', 'woocommerce-dopigo' )
        ) );
    }

    /**
     * AJAX: Import categories from XML
     */
    public function ajax_import_categories() {
        check_ajax_referer( 'dopigo_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied', 'woocommerce-dopigo' ) ) );
        }

        $raw_xml    = isset( $_POST['xml'] ) ? wp_unslash( $_POST['xml'] ) : '';
        $use_feed   = ! empty( $_POST['use_feed'] );
        $force_fetch = ! empty( $_POST['force_fetch'] );

        if ( empty( $raw_xml ) ) {
            if ( $use_feed ) {
                $raw_xml = $this->download_category_xml( $force_fetch );

                if ( is_wp_error( $raw_xml ) ) {
                    wp_send_json_error( array( 'message' => $raw_xml->get_error_message() ) );
                }
            } else {
                wp_send_json_error( array( 'message' => __( 'No XML provided and feed URL not requested.', 'woocommerce-dopigo' ) ) );
            }
        }

        libxml_use_internal_errors( true );
        $xml = simplexml_load_string( $raw_xml, 'SimpleXMLElement', LIBXML_NOCDATA );

        if ( false === $xml ) {
            $errors = array();
            foreach ( libxml_get_errors() as $error ) {
                $errors[] = trim( $error->message );
            }
            libxml_clear_errors();
            wp_send_json_error( array( 'message' => __( 'Failed to parse XML.', 'woocommerce-dopigo' ), 'details' => $errors ) );
        }

        $created = 0;
        $updated = 0;
        $skipped = array();

        foreach ( $xml->xpath( '//list-item' ) as $item ) {
            $category_node = isset( $item->category ) ? $item->category : null;
            if ( ! $category_node ) {
                continue;
            }

            $dopigo_id = isset( $category_node->id ) ? intval( $category_node->id ) : 0;
            if ( $dopigo_id <= 0 ) {
                continue;
            }

            $full_path = isset( $item->full_category_path ) ? trim( (string) $item->full_category_path ) : '';

            if ( empty( $full_path ) ) {
                $segments = array();
                if ( isset( $category_node->root->name ) ) {
                    $segments[] = trim( (string) $category_node->root->name );
                }
                if ( isset( $category_node->name ) ) {
                    $segments[] = trim( (string) $category_node->name );
                }
                $full_path = implode( ' > ', array_filter( $segments ) );
            }

            if ( empty( $full_path ) ) {
                Dopigo_Category_Mapper::add_pending_category( $dopigo_id );
                $skipped[] = $dopigo_id;
                continue;
            }

            $existing_id = Dopigo_Category_Mapper::get_wc_category_id( $dopigo_id );
            $term_id     = Dopigo_Category_Mapper::ensure_category_from_path( $dopigo_id, $full_path );

            if ( $term_id ) {
                if ( $existing_id ) {
                    $updated++;
                } else {
                    $created++;
                }
            } else {
                Dopigo_Category_Mapper::add_pending_category( $dopigo_id );
                $skipped[] = $dopigo_id;
            }
        }

        $pending = Dopigo_Category_Mapper::get_pending_categories();

        wp_send_json_success( array(
            'created' => $created,
            'updated' => $updated,
            'pending' => $pending,
            'skipped' => $skipped,
        ) );
    }

    /**
     * Download category XML from configured feed URL.
     *
     * @param bool $force_fetch Optional flag (currently unused, reserved for caching strategies).
     *
     * @return string|WP_Error
     */
    private function download_category_xml( $force_fetch = false ) {
        $feed_url = get_option( 'dopigo_xml_feed_url', '' );

        if ( empty( $feed_url ) ) {
            return new WP_Error( 'dopigo_missing_feed', __( 'No XML feed URL configured. Please set it in the Dopigo settings.', 'woocommerce-dopigo' ) );
        }

        $response = wp_remote_get( $feed_url, array(
            'timeout' => 30,
            'headers' => array(
                'Accept' => 'application/xml,text/xml;q=0.9,*/*;q=0.8',
            ),
        ) );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        if ( 200 !== $code ) {
            return new WP_Error( 'dopigo_bad_feed_status', sprintf( __( 'Failed to fetch feed. HTTP status: %d', 'woocommerce-dopigo' ), $code ) );
        }

        $body = wp_remote_retrieve_body( $response );

        if ( empty( $body ) ) {
            return new WP_Error( 'dopigo_empty_feed', __( 'Feed returned empty response.', 'woocommerce-dopigo' ) );
        }

        return $body;
    }

    /**
     * Render cron status
     */
    private function render_cron_status() {
        $next_run = wp_next_scheduled( 'dopigo_sync_products' );
        $auto_sync = get_option( 'dopigo_auto_sync', false );
        $interval = get_option( 'dopigo_sync_interval', 'hourly' );
        $wp_cron_disabled = defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON;
        $site_url = site_url();
        
        echo '<div class="card" style="margin-top: 20px;">';
        echo '<h2>' . __( 'Cron Job Status', 'woocommerce-dopigo' ) . '</h2>';
        
        // WP_CRON Status
        if ( $wp_cron_disabled ) {
            echo '<div style="padding: 10px; background: #d4edda; border-left: 4px solid #46b450; margin-bottom: 15px;">';
            echo '<p style="margin: 0;"><strong>' . __( '✓ Real Cron Detected', 'woocommerce-dopigo' ) . '</strong></p>';
            echo '<p style="margin: 5px 0 0 0; font-size: 12px;">' . __( 'WP_CRON is disabled. Real system cron is recommended for reliable scheduling.', 'woocommerce-dopigo' ) . '</p>';
            echo '</div>';
        } else {
            echo '<div style="padding: 10px; background: #fff3cd; border-left: 4px solid #f0a500; margin-bottom: 15px;">';
            echo '<p style="margin: 0;"><strong>' . __( '⚠ Pseudo-Cron Active', 'woocommerce-dopigo' ) . '</strong></p>';
            echo '<p style="margin: 5px 0 0 0; font-size: 12px;">' . __( 'WordPress pseudo-cron only runs when someone visits your site. For reliable execution, set up real system cron.', 'woocommerce-dopigo' ) . '</p>';
            echo '<details style="margin-top: 10px;">';
            echo '<summary style="cursor: pointer; color: #2271b1; font-weight: 600;">' . __( 'Show Setup Instructions', 'woocommerce-dopigo' ) . '</summary>';
            echo '<div style="margin-top: 10px; padding: 10px; background: #f9f9f9; border-radius: 4px;">';
            echo '<h4>' . __( 'Step 1: Disable WordPress Cron', 'woocommerce-dopigo' ) . '</h4>';
            echo '<p>' . __( 'Add this line to your wp-config.php file (before "That\'s all, stop editing!"):', 'woocommerce-dopigo' ) . '</p>';
            echo '<pre style="background: #fff; padding: 10px; border: 1px solid #ddd; border-radius: 4px; overflow-x: auto;"><code>define(\'DISABLE_WP_CRON\', true);</code></pre>';
            
            echo '<h4>' . __( 'Step 2: Set Up System Cron', 'woocommerce-dopigo' ) . '</h4>';
            echo '<p>' . __( 'Add one of these commands to your server\'s crontab (runs every 15 minutes):', 'woocommerce-dopigo' ) . '</p>';
            
            // Using curl
            echo '<p><strong>' . __( 'Option 1: Using curl', 'woocommerce-dopigo' ) . '</strong></p>';
            echo '<pre style="background: #fff; padding: 10px; border: 1px solid #ddd; border-radius: 4px; overflow-x: auto;"><code>*/15 * * * * curl -s ' . esc_url( $site_url ) . '/wp-cron.php?doing_wp_cron > /dev/null</code></pre>';
            
            // Using wget
            echo '<p><strong>' . __( 'Option 2: Using wget', 'woocommerce-dopigo' ) . '</strong></p>';
            echo '<pre style="background: #fff; padding: 10px; border: 1px solid #ddd; border-radius: 4px; overflow-x: auto;"><code>*/15 * * * * wget -q -O - ' . esc_url( $site_url ) . '/wp-cron.php?doing_wp_cron > /dev/null</code></pre>';
            
            // Using WP-CLI
            $wp_path = ABSPATH;
            echo '<p><strong>' . __( 'Option 3: Using WP-CLI', 'woocommerce-dopigo' ) . '</strong></p>';
            echo '<pre style="background: #fff; padding: 10px; border: 1px solid #ddd; border-radius: 4px; overflow-x: auto;"><code>*/15 * * * * cd ' . esc_html( $wp_path ) . ' && wp cron event run --due-now</code></pre>';
            
            echo '<h4>' . __( 'How to Edit Crontab', 'woocommerce-dopigo' ) . '</h4>';
            echo '<ol>';
            echo '<li>' . __( 'SSH into your server', 'woocommerce-dopigo' ) . '</li>';
            echo '<li>' . __( 'Run: <code>crontab -e</code>', 'woocommerce-dopigo' ) . '</li>';
            echo '<li>' . __( 'Add one of the commands above', 'woocommerce-dopigo' ) . '</li>';
            echo '<li>' . __( 'Save and exit (usually: press Esc, then type :wq and press Enter)', 'woocommerce-dopigo' ) . '</li>';
            echo '</ol>';
            
            echo '<p><strong>' . __( 'Note:', 'woocommerce-dopigo' ) . '</strong> ' . __( 'Replace the interval (*/15) with your preferred schedule. Common intervals:', 'woocommerce-dopigo' ) . '</p>';
            echo '<ul>';
            echo '<li><code>*/5 * * * *</code> - ' . __( 'Every 5 minutes', 'woocommerce-dopigo' ) . '</li>';
            echo '<li><code>*/15 * * * *</code> - ' . __( 'Every 15 minutes', 'woocommerce-dopigo' ) . '</li>';
            echo '<li><code>*/30 * * * *</code> - ' . __( 'Every 30 minutes', 'woocommerce-dopigo' ) . '</li>';
            echo '<li><code>0 * * * *</code> - ' . __( 'Every hour', 'woocommerce-dopigo' ) . '</li>';
            echo '</ul>';
            
            echo '</div>';
            echo '</details>';
            echo '</div>';
        }
        
        if ( $next_run ) {
            echo '<p style="color: #46b450;">';
            echo '<span class="dashicons dashicons-yes-alt"></span> ';
            echo __( 'Cron job is scheduled', 'woocommerce-dopigo' );
            echo '</p>';
            echo '<p><strong>' . __( 'Next run:', 'woocommerce-dopigo' ) . '</strong> ' . date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $next_run ) . '</p>';
            echo '<p><strong>' . __( 'Time until next run:', 'woocommerce-dopigo' ) . '</strong> ' . human_time_diff( time(), $next_run ) . '</p>';
        } else {
            echo '<p style="color: #dc3232;">';
            echo '<span class="dashicons dashicons-warning"></span> ';
            echo __( 'Cron job is NOT scheduled', 'woocommerce-dopigo' );
            echo '</p>';
        }
        
        echo '<p><strong>' . __( 'Auto sync enabled:', 'woocommerce-dopigo' ) . '</strong> ' . ( $auto_sync ? __( 'Yes', 'woocommerce-dopigo' ) : __( 'No', 'woocommerce-dopigo' ) ) . '</p>';
        echo '<p><strong>' . __( 'Sync interval:', 'woocommerce-dopigo' ) . '</strong> ' . $interval . '</p>';
        
        // Manual trigger button
        echo '<button type="button" id="dopigo-trigger-sync" class="button button-primary" style="margin-top: 10px;">';
        echo __( 'Trigger Sync Now (Test)', 'woocommerce-dopigo' );
        echo '</button>';
        echo '<span id="dopigo-trigger-result" style="margin-left: 10px;"></span>';
        
        // Progress display container
        echo '<div id="dopigo-sync-progress-container" style="display: none; margin-top: 20px; padding: 20px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px;">';
        echo '<h3>' . __( 'Sync Progress', 'woocommerce-dopigo' ) . '</h3>';
        echo '<div id="dopigo-sync-progress-details"></div>';
        echo '</div>';
        
        // Reschedule button
        echo '<div style="margin-top: 15px;">';
        echo '<button type="button" id="dopigo-reschedule-sync" class="button button-secondary">';
        echo __( 'Reschedule Sync', 'woocommerce-dopigo' );
        echo '</button>';
        echo '<span id="dopigo-reschedule-result" style="margin-left: 10px;"></span>';
        echo '<p class="description" style="margin-top: 5px;">' . __( 'Use this if the scheduled time seems incorrect. This will reschedule the sync based on your current interval settings.', 'woocommerce-dopigo' ) . '</p>';
        echo '</div>';
        
        echo '</div>';
    }

    /**
     * AJAX: Trigger sync manually
     */
    public function ajax_trigger_sync() {
        check_ajax_referer( 'dopigo_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied', 'woocommerce-dopigo' ) ) );
        }

        // Generate unique progress key
        $progress_key = 'sync_' . time() . '_' . wp_generate_password( 8, false );

        // Start sync in background
        $this->start_sync_with_progress( $progress_key );

        // Return progress key immediately
        wp_send_json_success( array(
            'progress_key' => $progress_key,
            'message' => __( 'Sync started!', 'woocommerce-dopigo' )
        ) );
    }
    
    /**
     * Start sync with progress tracking
     */
    private function start_sync_with_progress( $progress_key ) {
        // Schedule the sync to run immediately in background
        wp_schedule_single_event( time(), 'dopigo_sync_products_progress', array( $progress_key ) );
        
        // Trigger cron immediately
        spawn_cron();
    }
    
    /**
     * AJAX: Get sync progress
     */
    public function ajax_get_sync_progress() {
        check_ajax_referer( 'dopigo_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied', 'woocommerce-dopigo' ) ) );
        }

        $progress_key = isset( $_POST['progress_key'] ) ? sanitize_text_field( $_POST['progress_key'] ) : '';
        
        if ( empty( $progress_key ) ) {
            wp_send_json_error( array( 'message' => __( 'Progress key missing', 'woocommerce-dopigo' ) ) );
        }

        require_once WOOCOMMERCE_DOPIGO_PLUGIN_DIR . 'src/class-dopigo-product-mapper.php';
        
        $progress = Dopigo_Product_Mapper::get_progress( $progress_key );
        
        if ( ! $progress ) {
            wp_send_json_error( array( 'message' => __( 'Progress not found', 'woocommerce-dopigo' ) ) );
        }

        wp_send_json_success( $progress );
    }

    /**
     * AJAX: Reschedule sync
     */
    public function ajax_reschedule_sync() {
        check_ajax_referer( 'dopigo_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied', 'woocommerce-dopigo' ) ) );
        }

        // Call the reschedule function
        woocommerce_dopigo_schedule_sync();

        // Get new schedule info
        $next_run = wp_next_scheduled( 'dopigo_sync_products' );
        $auto_sync = get_option( 'dopigo_auto_sync', false );
        $interval = get_option( 'dopigo_sync_interval', 'hourly' );

        if ( $next_run ) {
            wp_send_json_success( array(
                'message' => __( 'Sync rescheduled successfully!', 'woocommerce-dopigo' ),
                'next_run' => date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $next_run ),
                'time_until' => human_time_diff( time(), $next_run ),
                'interval' => $interval
            ) );
        } else {
            wp_send_json_error( array(
                'message' => __( 'Sync could not be scheduled. Make sure auto sync is enabled.', 'woocommerce-dopigo' )
            ) );
        }
    }

    /**
     * Log sync activity
     */
    private function log_sync( $type, $status, $products_count, $message ) {
        $history = get_option( 'dopigo_sync_history', array() );

        $history[] = array(
            'timestamp'      => time(),
            'type'           => $type,
            'status'         => $status,
            'products_count' => $products_count,
            'message'        => $message
        );

        // Keep only last 100 entries
        $history = array_slice( $history, -100 );

        update_option( 'dopigo_sync_history', $history );
    }
}

// Initialize
new Dopigo_Admin();

