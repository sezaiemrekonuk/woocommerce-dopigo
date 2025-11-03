<?php
/**
 * Dopigo Admin Settings Page
 *
 * @package WooCommerce_Dopigo
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

?>
<div class="wrap">
    <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
    
    <?php settings_errors(); ?>

    <form method="post" action="options.php">
        <?php
        settings_fields( 'dopigo_settings_group' );
        do_settings_sections( 'dopigo-settings' );
        submit_button();
        ?>
    </form>

    <hr />

    <h2><?php _e( 'Connection Status', 'woocommerce-dopigo' ); ?></h2>
    <div id="dopigo-connection-status" style="padding: 15px; background: #f9f9f9; border-left: 4px solid #ddd; margin-top: 10px;">
        <?php $this->render_connection_status(); ?>
    </div>

    <?php $this->render_cron_status(); ?>
</div>
