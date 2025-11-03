<?php
/**
 * Dopigo Admin Sync Page
 *
 * @package WooCommerce_Dopigo
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

?>
<div class="wrap">
    <h1><?php _e( 'Sync Status', 'woocommerce-dopigo' ); ?></h1>

    <div class="card">
        <h2><?php _e( 'Synchronized Products', 'woocommerce-dopigo' ); ?></h2>
        <?php $this->render_synced_products_list(); ?>
    </div>

    <div class="card" style="margin-top: 20px;">
        <h2><?php _e( 'Sync History', 'woocommerce-dopigo' ); ?></h2>
        <?php $this->render_sync_history(); ?>
    </div>
</div>
