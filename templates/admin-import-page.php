<?php
/**
 * Dopigo Admin Import Page
 *
 * @package WooCommerce_Dopigo
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$api_key = get_option( 'dopigo_api_key', '' );
?>
<div class="wrap">
    <h1><?php _e( 'Import Products from Dopigo', 'woocommerce-dopigo' ); ?></h1>

    <?php if ( empty( $api_key ) ) : ?>
        <div class="notice notice-error">
            <p><?php _e( 'Please configure your API Key in settings first.', 'woocommerce-dopigo' ); ?></p>
            <p><a href="<?php echo admin_url( 'admin.php?page=dopigo-settings' ); ?>" class="button button-primary">
                <?php _e( 'Go to Settings', 'woocommerce-dopigo' ); ?>
            </a></p>
        </div>
    <?php else : ?>
        <div class="card" style="max-width: 800px;">
            <h2><?php _e( 'Fetch Products from Dopigo', 'woocommerce-dopigo' ); ?></h2>
            <p><?php _e( 'Click the button below to fetch all products from your Dopigo account.', 'woocommerce-dopigo' ); ?></p>
            
            <button type="button" id="dopigo-fetch-products" class="button button-primary button-large">
                <span class="dashicons dashicons-download" style="margin-top: 3px;"></span>
                <?php _e( 'Get All Products', 'woocommerce-dopigo' ); ?>
            </button>

            <div id="dopigo-products-result" style="margin-top: 20px;"></div>
        </div>

        <div class="card" style="max-width: 800px; margin-top: 30px;">
            <h2><?php _e( 'Import Categories from Dopigo', 'woocommerce-dopigo' ); ?></h2>
            <p><?php _e( 'Upload a Dopigo XML export or let the plugin fetch the XML from the configured feed URL. Categories will be created using the full hierarchy (e.g. Bilgisayar &gt; Bilgisayar Bileşenleri &gt; Soğutucu &amp; Fan).', 'woocommerce-dopigo' ); ?></p>

            <div style="margin-bottom: 15px;">
                <label for="dopigo-category-xml" class="screen-reader-text"><?php _e( 'Category XML', 'woocommerce-dopigo' ); ?></label>
                <input type="file" id="dopigo-category-xml" accept="text/xml,application/xml" />
            </div>

            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <button type="button" id="dopigo-import-categories" class="button button-primary button-large">
                    <span class="dashicons dashicons-category" style="margin-top: 3px;"></span>
                    <?php _e( 'Import Categories', 'woocommerce-dopigo' ); ?>
                </button>
                <button type="button" id="dopigo-fetch-category-xml" class="button">
                    <span class="dashicons dashicons-download" style="margin-top: 3px;"></span>
                    <?php _e( 'Fetch from Feed URL', 'woocommerce-dopigo' ); ?>
                </button>
            </div>

            <div id="dopigo-categories-result" style="margin-top: 20px;"></div>

            <p class="description" style="margin-top: 15px;">
                <?php _e( 'If no file is selected, the importer will automatically download categories from the feed URL set on the Dopigo settings page.', 'woocommerce-dopigo' ); ?>
            </p>
        </div>

        <div id="dopigo-products-preview" style="margin-top: 30px; display: none;">
            <h2><?php _e( 'Products Preview', 'woocommerce-dopigo' ); ?></h2>
            <p><?php _e( 'Review the products before importing them to WooCommerce.', 'woocommerce-dopigo' ); ?></p>
            
            <div id="dopigo-products-list"></div>

            <button type="button" id="dopigo-import-products" class="button button-primary button-large" style="margin-top: 20px;">
                <span class="dashicons dashicons-upload" style="margin-top: 3px;"></span>
                <?php _e( 'Import Selected Products', 'woocommerce-dopigo' ); ?>
            </button>
        </div>
    <?php endif; ?>
</div>
