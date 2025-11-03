<?php
/**
 * Dopigo Settings Panel
 * 
 * @package DopigoSync
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class Dopigo_Settings {

    /**
     * Option name for storing settings
     */
    private $option_name = 'dopigo_settings';

    /**
     * Initialize the settings
     */
    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_options_page(
            __( 'Dopigo Settings', 'woocommerce-dopigo' ),
            __( 'Dopigo Sync', 'woocommerce-dopigo' ),
            'manage_options',
            'dopigo-settings',
            array( $this, 'render_settings_page' )
        );
    }

    /**
     * Register settings
     */
    public function register_settings() {
        register_setting(
            'dopigo_settings_group',
            $this->option_name,
            array( $this, 'sanitize_settings' )
        );

        // API Credentials Section
        add_settings_section(
            'dopigo_api_section',
            __( 'API Credentials', 'woocommerce-dopigo' ),
            array( $this, 'render_api_section_description' ),
            'dopigo-settings'
        );

        add_settings_field(
            'username',
            __( 'Username / Email', 'woocommerce-dopigo' ),
            array( $this, 'render_username_field' ),
            'dopigo-settings',
            'dopigo_api_section'
        );

        add_settings_field(
            'password',
            __( 'Password', 'woocommerce-dopigo' ),
            array( $this, 'render_password_field' ),
            'dopigo-settings',
            'dopigo_api_section'
        );

        // Sync Settings Section
        add_settings_section(
            'dopigo_sync_section',
            __( 'Sync Settings', 'woocommerce-dopigo' ),
            array( $this, 'render_sync_section_description' ),
            'dopigo-settings'
        );

        add_settings_field(
            'auto_sync',
            __( 'Auto Sync', 'woocommerce-dopigo' ),
            array( $this, 'render_auto_sync_field' ),
            'dopigo-settings',
            'dopigo_sync_section'
        );

        add_settings_field(
            'sync_interval',
            __( 'Sync Interval (minutes)', 'woocommerce-dopigo' ),
            array( $this, 'render_sync_interval_field' ),
            'dopigo-settings',
            'dopigo_sync_section'
        );

        add_settings_field(
            'update_existing',
            __( 'Update Existing Products', 'woocommerce-dopigo' ),
            array( $this, 'render_update_existing_field' ),
            'dopigo-settings',
            'dopigo_sync_section'
        );
    }

    /**
     * Sanitize settings
     */
    public function sanitize_settings( $input ) {
        $sanitized = array();

        if ( isset( $input['username'] ) ) {
            $sanitized['username'] = sanitize_email( $input['username'] );
        }

        if ( isset( $input['password'] ) ) {
            $sanitized['password'] = sanitize_text_field( $input['password'] );
        }

        if ( isset( $input['auto_sync'] ) ) {
            $sanitized['auto_sync'] = isset( $input['auto_sync'] ) ? 1 : 0;
        }

        if ( isset( $input['sync_interval'] ) ) {
            $sanitized['sync_interval'] = absint( $input['sync_interval'] );
        }

        if ( isset( $input['update_existing'] ) ) {
            $sanitized['update_existing'] = isset( $input['update_existing'] ) ? 1 : 0;
        }

        return $sanitized;
    }

    /**
     * Render API section description
     */
    public function render_api_section_description() {
        echo '<p>' . __( 'Enter your Dopigo API credentials to connect to the platform.', 'woocommerce-dopigo' ) . '</p>';
    }

    /**
     * Render sync section description
     */
    public function render_sync_section_description() {
        echo '<p>' . __( 'Configure product synchronization settings.', 'woocommerce-dopigo' ) . '</p>';
    }

    /**
     * Render username field
     */
    public function render_username_field() {
        $settings = $this->get_settings();
        $value = isset( $settings['username'] ) ? esc_attr( $settings['username'] ) : '';
        ?>
        <input type="email" name="<?php echo esc_attr( $this->option_name ); ?>[username]" value="<?php echo $value; ?>" class="regular-text" />
        <p class="description"><?php _e( 'Enter your Dopigo account email address.', 'woocommerce-dopigo' ); ?></p>
        <?php
    }

    /**
     * Render password field
     */
    public function render_password_field() {
        $settings = $this->get_settings();
        $value = isset( $settings['password'] ) ? esc_attr( $settings['password'] ) : '';
        ?>
        <input type="password" name="<?php echo esc_attr( $this->option_name ); ?>[password]" value="<?php echo $value; ?>" class="regular-text" />
        <p class="description"><?php _e( 'Enter your Dopigo account password.', 'woocommerce-dopigo' ); ?></p>
        <?php
    }

    /**
     * Render auto sync field
     */
    public function render_auto_sync_field() {
        $settings = $this->get_settings();
        $value = isset( $settings['auto_sync'] ) ? $settings['auto_sync'] : 0;
        ?>
        <label>
            <input type="checkbox" name="<?php echo esc_attr( $this->option_name ); ?>[auto_sync]" value="1" <?php checked( $value, 1 ); ?> />
            <?php _e( 'Enable automatic product synchronization', 'woocommerce-dopigo' ); ?>
        </label>
        <?php
    }

    /**
     * Render sync interval field
     */
    public function render_sync_interval_field() {
        $settings = $this->get_settings();
        $value = isset( $settings['sync_interval'] ) ? absint( $settings['sync_interval'] ) : 60;
        ?>
        <input type="number" name="<?php echo esc_attr( $this->option_name ); ?>[sync_interval]" value="<?php echo $value; ?>" min="1" step="1" class="small-text" />
        <p class="description"><?php _e( 'How often to sync products (in minutes).', 'woocommerce-dopigo' ); ?></p>
        <?php
    }

    /**
     * Render update existing field
     */
    public function render_update_existing_field() {
        $settings = $this->get_settings();
        $value = isset( $settings['update_existing'] ) ? $settings['update_existing'] : 1;
        ?>
        <label>
            <input type="checkbox" name="<?php echo esc_attr( $this->option_name ); ?>[update_existing]" value="1" <?php checked( $value, 1 ); ?> />
            <?php _e( 'Update existing products when syncing', 'woocommerce-dopigo' ); ?>
        </label>
        <?php
    }

    /**
     * Render settings page
     */
    public function render_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        // Handle test connection
        $test_result = null;
        if ( isset( $_POST['test_connection'] ) && check_admin_referer( 'test_dopigo_connection' ) ) {
            $test_result = $this->test_connection();
        }

        // Handle get all products
        $products_result = null;
        if ( isset( $_POST['get_products'] ) && check_admin_referer( 'get_dopigo_products' ) ) {
            $products_result = $this->get_all_products();
        }

        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            
            <form action="options.php" method="post">
                <?php
                settings_fields( 'dopigo_settings_group' );
                do_settings_sections( 'dopigo-settings' );
                submit_button( __( 'Save Settings', 'woocommerce-dopigo' ) );
                ?>
            </form>

            <hr class="wp-header-end">

            <div class="dopigo-test-section" style="margin-top: 30px;">
                <h2><?php _e( 'Test Connection', 'woocommerce-dopigo' ); ?></h2>
                <p><?php _e( 'Test your API credentials by clicking the button below.', 'woocommerce-dopigo' ); ?></p>
                
                <form method="post" action="">
                    <?php wp_nonce_field( 'test_dopigo_connection' ); ?>
                    <input type="submit" name="test_connection" class="button button-secondary" value="<?php esc_attr_e( 'Test Connection', 'woocommerce-dopigo' ); ?>" />
                </form>

                <?php if ( $test_result !== null ) : ?>
                    <div class="notice notice-<?php echo $test_result['success'] ? 'success' : 'error'; ?>" style="margin-top: 15px;">
                        <p><strong><?php echo $test_result['success'] ? __( 'Success!', 'woocommerce-dopigo' ) : __( 'Error!', 'woocommerce-dopigo' ); ?></strong></p>
                        <p><?php echo esc_html( $test_result['message'] ); ?></p>
                        <?php if ( $test_result['success'] && isset( $test_result['auth_token'] ) ) : ?>
                            <p><code><?php _e( 'Auth Token:', 'woocommerce-dopigo' ); ?> <?php echo esc_html( substr( $test_result['auth_token'], 0, 20 ) ) . '...'; ?></code></p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="dopigo-test-section" style="margin-top: 30px;">
                <h2><?php _e( 'Get All Products', 'woocommerce-dopigo' ); ?></h2>
                <p><?php _e( 'Fetch all products from Dopigo API and display as JSON.', 'woocommerce-dopigo' ); ?></p>
                
                <form method="post" action="">
                    <?php wp_nonce_field( 'get_dopigo_products' ); ?>
                    <input type="submit" name="get_products" class="button button-secondary" value="<?php esc_attr_e( 'Get All Products', 'woocommerce-dopigo' ); ?>" />
                </form>

                <?php if ( $products_result !== null ) : ?>
                    <div class="notice notice-<?php echo $products_result['success'] ? 'success' : 'error'; ?>" style="margin-top: 15px;">
                        <p><strong><?php echo $products_result['success'] ? __( 'Success!', 'woocommerce-dopigo' ) : __( 'Error!', 'woocommerce-dopigo' ); ?></strong></p>
                        <p><?php echo esc_html( $products_result['message'] ); ?></p>
                        <?php if ( $products_result['success'] && isset( $products_result['products'] ) ) : ?>
                            <div style="margin-top: 15px;">
                                <h3><?php _e( 'Products JSON:', 'woocommerce-dopigo' ); ?></h3>
                                <pre style="background: #1e1e1e; color: #d4d4d4; padding: 15px; border-radius: 4px; overflow-x: auto; max-height: 600px; overflow-y: auto;"><?php echo esc_html( wp_json_encode( $products_result['products'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ); ?></pre>
                                <p style="margin-top: 10px;">
                                    <strong><?php _e( 'Total Products:', 'woocommerce-dopigo' ); ?></strong> 
                                    <?php echo is_array( $products_result['products'] ) ? count( $products_result['products'] ) : 0; ?>
                                </p>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="dopigo-info-section" style="margin-top: 30px; padding: 15px; background: #f0f0f1; border-left: 4px solid #2271b1;">
                <h3><?php _e( 'Settings Information', 'woocommerce-dopigo' ); ?></h3>
                <?php
                $settings = $this->get_settings();
                ?>
                <table class="widefat">
                    <tbody>
                        <tr>
                            <td><strong><?php _e( 'Username:', 'woocommerce-dopigo' ); ?></strong></td>
                            <td><?php echo isset( $settings['username'] ) && $settings['username'] ? esc_html( $settings['username'] ) : '<em>' . __( 'Not set', 'woocommerce-dopigo' ) . '</em>'; ?></td>
                        </tr>
                        <tr>
                            <td><strong><?php _e( 'Password:', 'woocommerce-dopigo' ); ?></strong></td>
                            <td><?php echo isset( $settings['password'] ) && $settings['password'] ? str_repeat( '*', strlen( $settings['password'] ) ) : '<em>' . __( 'Not set', 'woocommerce-dopigo' ) . '</em>'; ?></td>
                        </tr>
                        <tr>
                            <td><strong><?php _e( 'Auto Sync:', 'woocommerce-dopigo' ); ?></strong></td>
                            <td><?php echo isset( $settings['auto_sync'] ) && $settings['auto_sync'] ? __( 'Enabled', 'woocommerce-dopigo' ) : __( 'Disabled', 'woocommerce-dopigo' ); ?></td>
                        </tr>
                        <tr>
                            <td><strong><?php _e( 'Sync Interval:', 'woocommerce-dopigo' ); ?></strong></td>
                            <td><?php echo isset( $settings['sync_interval'] ) ? esc_html( $settings['sync_interval'] ) . ' ' . __( 'minutes', 'woocommerce-dopigo' ) : '<em>' . __( 'Not set', 'woocommerce-dopigo' ) . '</em>'; ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }

    /**
     * Test connection to Dopigo API
     */
    private function test_connection() {
        $settings = $this->get_settings();

        if ( empty( $settings['username'] ) || empty( $settings['password'] ) ) {
            return array(
                'success' => false,
                'message' => __( 'Please enter your username and password first.', 'woocommerce-dopigo' ),
            );
        }

        // Try to get auth token
        if ( ! function_exists( 'get_auth_token' ) ) {
            require_once plugin_dir_path( dirname( __FILE__ ) ) . 'dopigo.php';
        }

        try {
            $auth_token = get_auth_token( $settings['username'], $settings['password'] );

            if ( $auth_token ) {
                return array(
                    'success' => true,
                    'message' => __( 'Connection successful! Your credentials are valid.', 'woocommerce-dopigo' ),
                    'auth_token' => $auth_token,
                );
            } else {
                return array(
                    'success' => false,
                    'message' => __( 'Failed to get authentication token. Please check your credentials.', 'woocommerce-dopigo' ),
                );
            }
        } catch ( Exception $e ) {
            return array(
                'success' => false,
                'message' => sprintf( __( 'Error: %s', 'woocommerce-dopigo' ), $e->getMessage() ),
            );
        }
    }

    /**
     * Get all products from Dopigo API
     */
    private function get_all_products() {
        $settings = $this->get_settings();

        if ( empty( $settings['username'] ) || empty( $settings['password'] ) ) {
            return array(
                'success' => false,
                'message' => __( 'Please enter your username and password first.', 'woocommerce-dopigo' ),
            );
        }

        // Ensure functions are loaded
        if ( ! function_exists( 'get_auth_token' ) || ! function_exists( 'get_all_dopigo_products' ) ) {
            require_once plugin_dir_path( dirname( __FILE__ ) ) . 'dopigo.php';
        }

        try {
            // First get auth token
            $auth_token = get_auth_token( $settings['username'], $settings['password'] );
            
            if ( ! $auth_token ) {
                return array(
                    'success' => false,
                    'message' => __( 'Failed to get authentication token. Please check your credentials.', 'woocommerce-dopigo' ),
                );
            }

            // Get all products
            $products = get_all_dopigo_products( $auth_token );
            
            if ( $products !== false ) {
                return array(
                    'success' => true,
                    'message' => __( 'Products fetched successfully!', 'woocommerce-dopigo' ),
                    'products' => $products,
                );
            } else {
                return array(
                    'success' => false,
                    'message' => __( 'Failed to fetch products. Please check your API connection and try again.', 'woocommerce-dopigo' ),
                );
            }
        } catch ( Exception $e ) {
            return array(
                'success' => false,
                'message' => sprintf( __( 'Error: %s', 'woocommerce-dopigo' ), $e->getMessage() ),
            );
        }
    }

    /**
     * Get settings
     */
    public function get_settings() {
        return get_option( $this->option_name, array() );
    }

    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts( $hook ) {
        if ( 'settings_page_dopigo-settings' !== $hook ) {
            return;
        }

        // Add any custom CSS or JS if needed
        wp_add_inline_style( 'wp-admin', '
            .dopigo-test-section {
                background: #fff;
                padding: 20px;
                border: 1px solid #ccd0d4;
                box-shadow: 0 1px 1px rgba(0,0,0,.04);
                margin-bottom: 20px;
            }
            .dopigo-info-section table {
                margin-top: 10px;
            }
            .dopigo-info-section td {
                padding: 10px;
            }
            .dopigo-test-section pre {
                font-family: "Courier New", Courier, monospace;
                font-size: 12px;
                line-height: 1.5;
                word-wrap: break-word;
                white-space: pre-wrap;
            }
        ' );
    }
}

