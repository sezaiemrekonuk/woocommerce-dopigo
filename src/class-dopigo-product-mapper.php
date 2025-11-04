<?php
/**
 * Dopigo Product Mapper
 * 
 * Utility class to convert between Dopigo and WooCommerce product data structures
 * 
 * @package WooCommerce_Dopigo
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Dopigo_Product_Mapper {

    /**
     * Convert Dopigo product to WooCommerce product
     * 
     * @param array $dopigo_product Dopigo product data
     * @param WC_Product|null $wc_product Existing WooCommerce product to update (optional)
     * @param bool $skip_images Skip downloading images (faster import)
     * @return WC_Product|WC_Product_Variable Created or updated WooCommerce product
     * @throws WC_Data_Exception
     */
    public static function dopigo_to_woocommerce( $dopigo_product, $wc_product = null, $skip_images = false ) {
        $has_variations = count( $dopigo_product['products'] ) > 1;
        
        // Determine if we need a variable product or simple product
        if ( $has_variations ) {
            $product = $wc_product instanceof WC_Product_Variable ? $wc_product : new WC_Product_Variable();
        } else {
            $product = $wc_product instanceof WC_Product_Simple ? $wc_product : new WC_Product_Simple();
        }

        // Set basic product data from meta
        $product->set_name( sanitize_text_field( $dopigo_product['name'] ) );
        $product->set_description( wp_kses_post( $dopigo_product['description'] ) );
        $product->set_status( $dopigo_product['active'] ? 'publish' : 'draft' );  
        
        // Set short description from subheading if available
        if ( ! empty( $dopigo_product['subheading'] ) ) {
            $product->set_short_description( sanitize_text_field( $dopigo_product['subheading'] ) );
        }

        // Set category if provided
        if ( ! empty( $dopigo_product['category'] ) ) {
            $wc_category_id = self::map_dopigo_category_to_woocommerce( $dopigo_product['category'] );
            if ( $wc_category_id ) {
                $product->set_category_ids( array( $wc_category_id ) );
            }
        }

        // Store Dopigo meta_id for reference
        $product->update_meta_data( '_dopigo_meta_id', $dopigo_product['meta_id'] );
        $product->update_meta_data( '_dopigo_vat', $dopigo_product['vat'] );

        if ( $has_variations ) {
            // Handle variable product
            $product = self::create_variable_product( $product, $dopigo_product, $skip_images );
        } else {
            // Handle simple product with single variant
            $product = self::populate_simple_product( $product, $dopigo_product['products'][0], $skip_images );
        }

        // Save the product
        $product->save();

        return $product;
    }

    /**
     * Populate simple product data from Dopigo variant
     * 
     * @param WC_Product_Simple $product WooCommerce product
     * @param array $dopigo_variant Dopigo product variant
     * @param bool $skip_images Skip downloading images
     * @return WC_Product_Simple
     */
    private static function populate_simple_product( $product, $dopigo_variant, $skip_images = false ) {
        // Set SKU
        if ( ! empty( $dopigo_variant['sku'] ) ) {
            $product->set_sku( sanitize_text_field( $dopigo_variant['sku'] ) );
        }

        // Set prices
        if ( ! empty( $dopigo_variant['listing_price'] ) ) {
            $product->set_regular_price( $dopigo_variant['listing_price'] );
        }
        
        if ( ! empty( $dopigo_variant['price'] ) && $dopigo_variant['price'] < $dopigo_variant['listing_price'] ) {
            $product->set_sale_price( $dopigo_variant['price'] );
        } elseif ( ! empty( $dopigo_variant['price'] ) ) {
            $product->set_regular_price( $dopigo_variant['price'] );
        }

        // Set stock management
        $product->set_manage_stock( true );
        $product->set_stock_quantity( intval( $dopigo_variant['available_stock'] ) );
        $product->set_stock_status( $dopigo_variant['available_stock'] > 0 ? 'instock' : 'outofstock' );

        // Set weight if available
        if ( ! empty( $dopigo_variant['weight'] ) ) {
            $product->set_weight( $dopigo_variant['weight'] );
        }

        // Store Dopigo variant ID for reference
        $product->update_meta_data( '_dopigo_product_id', $dopigo_variant['id'] );
        $product->update_meta_data( '_dopigo_barcode', $dopigo_variant['barcode'] );
        
        if ( ! empty( $dopigo_variant['purchase_price'] ) ) {
            $product->update_meta_data( '_dopigo_purchase_price', $dopigo_variant['purchase_price'] );
        }

        // Handle images
        if ( ! empty( $dopigo_variant['images'] ) ) {
            // Always extract and store current image URLs
            $image_urls = array();
            foreach ( $dopigo_variant['images'] as $image ) {
                $image_urls[] = $image['absolute_url'];
            }
            
            // Get stored URLs for comparison
            $stored_urls = $product->get_meta( '_dopigo_image_urls' );
            if ( ! is_array( $stored_urls ) ) {
                $stored_urls = array();
            }
            
            // Store original URLs if this is first time (for comparison on future syncs)
            $original_urls = $product->get_meta( '_dopigo_image_urls_original' );
            if ( empty( $original_urls ) || ! is_array( $original_urls ) ) {
                $product->update_meta_data( '_dopigo_image_urls_original', $image_urls );
            }
            
            if ( $skip_images ) {
                // Just store image URLs in meta for later processing
                $product->update_meta_data( '_dopigo_image_urls', $image_urls );
            } else {
                // Process images: always process to ensure images are attached
                $image_ids = self::process_dopigo_images( $dopigo_variant['images'], $stored_urls, $dopigo_variant );
                
                if ( ! empty( $image_ids ) ) {
                    // Set featured image
                    $product->set_image_id( $image_ids[0] );
                    // Set gallery images if more than one
                    if ( count( $image_ids ) > 1 ) {
                        $product->set_gallery_image_ids( array_slice( $image_ids, 1 ) );
                    }
                } else {
                    // No images found, clear any existing images
                    $product->set_image_id( '' );
                    $product->set_gallery_image_ids( array() );
                }
                
                // Always store current URLs for next comparison
                $product->update_meta_data( '_dopigo_image_urls', $image_urls );
            }
        }

        return $product;
    }

    /**
     * Create variable product with variations
     * 
     * @param WC_Product_Variable $product WooCommerce variable product
     * @param array $dopigo_product Dopigo product data
     * @param bool $skip_images Skip downloading images
     * @return WC_Product_Variable
     */
    private static function create_variable_product( $product, $dopigo_product, $skip_images = false ) {
        // First, save the parent product to get an ID
        $product->save();

        // Collect all unique attributes from variants
        $attributes_data = self::collect_attributes_from_variants( $dopigo_product['products'] );
        
        // Create and set attributes
        $attributes = array();
        foreach ( $attributes_data as $attribute_name => $values ) {
            $attribute = new WC_Product_Attribute();
            $attribute->set_name( $attribute_name );
            $attribute->set_options( $values );
            $attribute->set_visible( true );
            $attribute->set_variation( true );
            $attributes[] = $attribute;
        }
        
        $product->set_attributes( $attributes );
        $product->save();

        // Create variations
        $variation_ids = array();
        foreach ( $dopigo_product['products'] as $dopigo_variant ) {
            $variation = self::create_product_variation( $product->get_id(), $dopigo_variant, $skip_images );
            $variation_ids[] = $variation->get_id();
        }

        return $product;
    }

    /**
     * Create product variation from Dopigo variant
     * 
     * @param int $parent_id Parent product ID
     * @param array $dopigo_variant Dopigo variant data
     * @param bool $skip_images Skip downloading images
     * @return WC_Product_Variation
     */
    private static function create_product_variation( $parent_id, $dopigo_variant, $skip_images = false ) {
        $variation = new WC_Product_Variation();
        $variation->set_parent_id( $parent_id );

        // Set variation attributes
        if ( ! empty( $dopigo_variant['custom_attributes'] ) ) {
            $variation_attributes = array();
            foreach ( $dopigo_variant['custom_attributes'] as $custom_attr ) {
                $attr_name = sanitize_title( $custom_attr['attribute']['name'] );
                $attr_value = $custom_attr['value']['name'];
                $variation_attributes[ 'attribute_' . $attr_name ] = $attr_value;
            }
            $variation->set_attributes( $variation_attributes );
        }

        // Set SKU
        if ( ! empty( $dopigo_variant['sku'] ) ) {
            $variation->set_sku( sanitize_text_field( $dopigo_variant['sku'] ) );
        }

        // Set prices
        if ( ! empty( $dopigo_variant['listing_price'] ) ) {
            $variation->set_regular_price( $dopigo_variant['listing_price'] );
        }
        
        if ( ! empty( $dopigo_variant['price'] ) && $dopigo_variant['price'] < $dopigo_variant['listing_price'] ) {
            $variation->set_sale_price( $dopigo_variant['price'] );
        } elseif ( ! empty( $dopigo_variant['price'] ) ) {
            $variation->set_regular_price( $dopigo_variant['price'] );
        }

        // Set stock
        $variation->set_manage_stock( true );
        $variation->set_stock_quantity( intval( $dopigo_variant['available_stock'] ) );
        $variation->set_stock_status( $dopigo_variant['available_stock'] > 0 ? 'instock' : 'outofstock' );

        // Store Dopigo data
        $variation->update_meta_data( '_dopigo_product_id', $dopigo_variant['id'] );
        $variation->update_meta_data( '_dopigo_barcode', $dopigo_variant['barcode'] );
        
        if ( ! empty( $dopigo_variant['purchase_price'] ) ) {
            $variation->update_meta_data( '_dopigo_purchase_price', $dopigo_variant['purchase_price'] );
        }

        // Handle images for variation
        if ( ! empty( $dopigo_variant['images'] ) ) {
            // Always extract and store current image URLs
            $image_urls = array();
            foreach ( $dopigo_variant['images'] as $image ) {
                $image_urls[] = $image['absolute_url'];
            }
            
            // Get stored URLs for comparison
            $stored_urls = $variation->get_meta( '_dopigo_image_urls' );
            if ( ! is_array( $stored_urls ) ) {
                $stored_urls = array();
            }
            
            // Store original URLs if this is first time (for comparison on future syncs)
            $original_urls = $variation->get_meta( '_dopigo_image_urls_original' );
            if ( empty( $original_urls ) || ! is_array( $original_urls ) ) {
                $variation->update_meta_data( '_dopigo_image_urls_original', $image_urls );
            }
            
            if ( $skip_images ) {
                // Just store image URLs in meta for later processing
                $variation->update_meta_data( '_dopigo_image_urls', $image_urls );
            } else {
                // Process images: always process to ensure images are attached
                $image_ids = self::process_dopigo_images( $dopigo_variant['images'], $stored_urls, $dopigo_variant );
                
                if ( ! empty( $image_ids ) ) {
                    // Set variation image
                    $variation->set_image_id( $image_ids[0] );
                } else {
                    // No images found, clear any existing image
                    $variation->set_image_id( '' );
                }
                
                // Always store current URLs for next comparison
                $variation->update_meta_data( '_dopigo_image_urls', $image_urls );
            }
        }

        $variation->save();

        return $variation;
    }

    /**
     * Collect unique attributes from all variants
     * 
     * @param array $variants Dopigo variants
     * @return array Attributes map
     */
    private static function collect_attributes_from_variants( $variants ) {
        $attributes = array();

        foreach ( $variants as $variant ) {
            if ( ! empty( $variant['custom_attributes'] ) ) {
                foreach ( $variant['custom_attributes'] as $custom_attr ) {
                    $attr_name = sanitize_title( $custom_attr['attribute']['name'] );
                    $attr_value = $custom_attr['value']['name'];
                    
                    if ( ! isset( $attributes[ $attr_name ] ) ) {
                        $attributes[ $attr_name ] = array();
                    }
                    
                    if ( ! in_array( $attr_value, $attributes[ $attr_name ] ) ) {
                        $attributes[ $attr_name ][] = $attr_value;
                    }
                }
            }
        }

        return $attributes;
    }

    /**
     * Process Dopigo images and upload to WordPress media library
     * 
     * @param array $dopigo_images Dopigo images array
     * @param array $stored_urls Previously stored image URLs to compare against
     * @return array Array of WordPress attachment IDs
     */
    private static function process_dopigo_images( $dopigo_images, $stored_urls = array(), $dopigo_variant = array() ) {
        $image_ids = array();
        $current_urls = array();

        // Sort images by order
        usort( $dopigo_images, function( $a, $b ) {
            return $a['order'] - $b['order'];
        });

        foreach ( $dopigo_images as $image ) {
            $image_url = $image['absolute_url'];
            if ( empty( $image_url ) || ! filter_var( $image_url, FILTER_VALIDATE_URL ) ) {
                $image_url = $image['source_url'];
            }
            $current_urls[] = $image_url;
            
            // First, check if image already exists in media library (by URL)
            $existing_id = self::get_attachment_id_by_url( $image_url );
            
            if ( $existing_id ) {
                // Image exists in media library, reuse it
                $image_ids[] = $existing_id;
            } else {
                // Image doesn't exist in media library
                // Check if URL has changed compared to stored URLs
                $url_changed = ! empty( $stored_urls ) && ! in_array( $image_url, $stored_urls );
                
                if ( $url_changed ) {
                    // URL changed - download the new image
                    $attachment_id = self::upload_image_from_url( $image_url, $dopigo_variant );
                    if ( $attachment_id ) {
                        $image_ids[] = $attachment_id;
                    }
                } elseif ( empty( $stored_urls ) ) {
                    // No stored URLs (new product or restored after permanent deletion)
                    // Check if we can find images by original URLs stored in meta
                    // If not found, download it
                    $attachment_id = self::upload_image_from_url( $image_url, $dopigo_variant );
                    if ( $attachment_id ) {
                        $image_ids[] = $attachment_id;
                    }
                } else {
                    // URL hasn't changed but image doesn't exist in media library
                    // This handles the case where product was deleted and images were deleted too
                    // Try to find by any stored URL (in case URL structure changed slightly)
                    $found = false;
                    foreach ( $stored_urls as $stored_url ) {
                        $stored_id = self::get_attachment_id_by_url( $stored_url );
                        if ( $stored_id ) {
                            $image_ids[] = $stored_id;
                            $found = true;
                            break;
                        }
                    }
                    
                    // If still not found, download it (images were deleted)
                    if ( ! $found ) {
                        $attachment_id = self::upload_image_from_url( $image_url, $dopigo_variant );
                        if ( $attachment_id ) {
                            $image_ids[] = $attachment_id;
                        }
                    }
                }
            }
        }

        return $image_ids;
    }

    /**
     * Upload image from URL to WordPress media library
     * 
     * @param string $image_url Image URL
     * @return int|false Attachment ID or false on failure
     */
    private static function upload_image_from_url( $image_url, $product_data = array() ) {
        require_once( ABSPATH . 'wp-admin/includes/file.php' );
        require_once( ABSPATH . 'wp-admin/includes/media.php' );
        require_once( ABSPATH . 'wp-admin/includes/image.php' );

        // Validate URL
        if ( empty( $image_url ) || ! filter_var( $image_url, FILTER_VALIDATE_URL ) ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'Dopigo: Invalid image URL: ' . $image_url );
            }
            return false;
        }

        $tmp = download_url( $image_url );

        if ( is_wp_error( $tmp ) ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'Dopigo: Error downloading image from ' . $image_url . ': ' . $tmp->get_error_message() );
            }
            return false;
        }

        // Create a more descriptive filename
        $sku = isset( $product_data['sku'] ) ? sanitize_file_name( $product_data['sku'] ) : 'no-sku';
        $filename = 'dopigo-' . $sku . '-' . time() . '-' . basename( parse_url( $image_url, PHP_URL_PATH ) );

        $file_array = array(
            'name'     => $filename,
            'tmp_name' => $tmp
        );

        $id = media_handle_sideload( $file_array, 0 );

        if ( is_wp_error( $id ) ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'Dopigo: Error uploading image ' . $image_url . ': ' . $id->get_error_message() );
            }
            @unlink( $file_array['tmp_name'] );
            return false;
        }

        // Store the original URL in postmeta for future reference
        if ( $id ) {
            update_post_meta( $id, '_dopigo_image_url', $image_url );
        }

        return $id;
    }

    /**
     * Get attachment ID by URL
     * Checks multiple methods to find existing images
     * 
     * @param string $url Image URL
     * @return int|false Attachment ID or false if not found
     */
    private static function get_attachment_id_by_url( $url ) {
        global $wpdb;
        
        // Method 1: Check by GUID (exact match)
        $attachment_id = $wpdb->get_var( $wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} WHERE guid = %s AND post_type = 'attachment' LIMIT 1",
            $url
        ) );
        
        if ( $attachment_id ) {
            return (int) $attachment_id;
        }
        
        // Method 2: Check by postmeta (stored URL)
        $attachment_id = $wpdb->get_var( $wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_dopigo_image_url' AND meta_value = %s LIMIT 1",
            $url
        ) );
        
        if ( $attachment_id ) {
            return (int) $attachment_id;
        }
        
        return false;
    }

    /**
     * Convert WooCommerce product to Dopigo format
     * 
     * @param WC_Product $wc_product WooCommerce product
     * @return array Dopigo product data structure
     */
    public static function woocommerce_to_dopigo( $wc_product ) {
        $dopigo_data = array(
            'meta_id'     => $wc_product->get_meta( '_dopigo_meta_id', true ),
            'name'        => $wc_product->get_name(),
            'active'      => $wc_product->get_status() === 'publish',
            'description' => $wc_product->get_description(),
            'subheading'  => $wc_product->get_short_description(),
            'category'    => self::get_dopigo_category_from_woocommerce( $wc_product ),
            'vat'         => $wc_product->get_meta( '_dopigo_vat', true ) ?: 20,
            'labels'      => null,
            'products'    => array(),
            'archived'    => false
        );

        if ( $wc_product->is_type( 'variable' ) ) {
            // Handle variable product
            $variations = $wc_product->get_available_variations();
            
            foreach ( $variations as $variation_data ) {
                $variation = wc_get_product( $variation_data['variation_id'] );
                $dopigo_data['products'][] = self::variation_to_dopigo( $variation, $dopigo_data );
            }
        } else {
            // Handle simple product
            $dopigo_data['products'][] = self::simple_product_to_dopigo( $wc_product, $dopigo_data );
        }

        return $dopigo_data;
    }

    /**
     * Convert simple product to Dopigo variant
     * 
     * @param WC_Product $product WooCommerce product
     * @param array $meta_data Product meta data
     * @return array Dopigo variant data
     */
    private static function simple_product_to_dopigo( $product, $meta_data ) {
        return array(
            'id'                      => $product->get_meta( '_dopigo_product_id', true ),
            'meta_id'                 => $meta_data['meta_id'],
            'sku'                     => $product->get_sku(),
            'foreign_sku'             => null,
            'second_foreign_sku'      => null,
            'barcode'                 => $product->get_meta( '_dopigo_barcode', true ),
            'weight'                  => $product->get_weight(),
            'stock'                   => $product->get_stock_quantity(),
            'available_stock'         => $product->get_stock_quantity(),
            'price'                   => $product->get_sale_price() ?: $product->get_regular_price(),
            'price_currency'          => get_woocommerce_currency(),
            'price_local'             => $product->get_sale_price() ?: $product->get_regular_price(),
            'listing_price'           => $product->get_regular_price(),
            'purchase_price'          => $product->get_meta( '_dopigo_purchase_price', true ),
            'buyer_pays_shipping'     => false,
            'images'                  => self::get_product_images_dopigo_format( $product ),
            'custom_attributes'       => array(),
            'is_primary'              => true,
            'custom_preparation_days' => null,
            'buyer_shipment_cost'     => null,
            'meta'                    => array(
                'vat'         => $meta_data['vat'],
                'name'        => $meta_data['name'],
                'description' => $meta_data['description'],
                'active'      => $meta_data['active'],
                'archived'    => $meta_data['archived']
            ),
            'invoice_name'            => null
        );
    }

    /**
     * Convert variation to Dopigo variant
     * 
     * @param WC_Product_Variation $variation WooCommerce variation
     * @param array $meta_data Product meta data
     * @return array Dopigo variant data
     */
    private static function variation_to_dopigo( $variation, $meta_data ) {
        $variant = self::simple_product_to_dopigo( $variation, $meta_data );
        
        // Add custom attributes from variation
        $attributes = $variation->get_attributes();
        $custom_attributes = array();
        
        foreach ( $attributes as $attr_name => $attr_value ) {
            $clean_name = str_replace( 'attribute_', '', $attr_name );
            $custom_attributes[] = array(
                'attribute' => array(
                    'name' => $clean_name
                ),
                'value' => array(
                    'name' => $attr_value
                )
            );
        }
        
        $variant['custom_attributes'] = $custom_attributes;
        
        return $variant;
    }

    /**
     * Get product images in Dopigo format
     * 
     * @param WC_Product $product WooCommerce product
     * @return array Dopigo images array
     */
    private static function get_product_images_dopigo_format( $product ) {
        $images = array();
        $order = 0;

        // Main image
        $image_id = $product->get_image_id();
        if ( $image_id ) {
            $images[] = array(
                'absolute_url' => wp_get_attachment_url( $image_id ),
                'source_url'   => null,
                'order'        => $order++
            );
        }

        // Gallery images
        $gallery_ids = $product->get_gallery_image_ids();
        foreach ( $gallery_ids as $gallery_id ) {
            $images[] = array(
                'absolute_url' => wp_get_attachment_url( $gallery_id ),
                'source_url'   => null,
                'order'        => $order++
            );
        }

        return $images;
    }

    /**
     * Map Dopigo category ID to WooCommerce category ID
     * 
     * @param int $dopigo_category_id Dopigo category ID
     * @return int|null WooCommerce category ID
     */
    private static function map_dopigo_category_to_woocommerce( $dopigo_category_id ) {
        // Check if mapping exists in options
        $category_map = get_option( 'dopigo_category_map', array() );
        
        if ( isset( $category_map[ $dopigo_category_id ] ) ) {
            return $category_map[ $dopigo_category_id ];
        }

        return null;
    }

    /**
     * Get Dopigo category ID from WooCommerce product
     * 
     * @param WC_Product $product WooCommerce product
     * @return int|null Dopigo category ID
     */
    private static function get_dopigo_category_from_woocommerce( $product ) {
        $category_ids = $product->get_category_ids();
        
        if ( empty( $category_ids ) ) {
            return null;
        }

        // Get the first category and find its Dopigo mapping
        $wc_category_id = $category_ids[0];
        $category_map = get_option( 'dopigo_category_map', array() );
        
        // Reverse lookup
        $dopigo_category_id = array_search( $wc_category_id, $category_map );
        
        return $dopigo_category_id ?: null;
    }

    /**
     * Batch convert Dopigo products to WooCommerce
     * 
     * @param array $dopigo_products Array of Dopigo products
     * @param bool $skip_images Skip downloading images (faster import)
     * @param string $progress_key Optional progress tracking key for real-time updates
     * @return array Array of created/updated WooCommerce product IDs
     */
    public static function batch_import_from_dopigo( $dopigo_products, $skip_images = false, $progress_key = null ) {
        $product_ids = array();
        $total_count = count( $dopigo_products );
        $success_count = 0;
        $error_count = 0;
        $debug = defined( 'WP_DEBUG' ) && WP_DEBUG;
        
        if ( $debug ) {
            error_log( sprintf( 'Dopigo Mapper: Starting batch import of %d products...', $total_count ) );
        }
        
        // Initialize progress tracking
        if ( $progress_key ) {
            self::update_progress( $progress_key, array(
                'total' => $total_count,
                'processed' => 0,
                'success' => 0,
                'errors' => 0,
                'current_product' => '',
                'status' => 'running',
                'start_time' => time(),
                'recent_products' => array()
            ) );
        }
        
        foreach ( $dopigo_products as $index => $dopigo_product ) {
            $product_num = $index + 1;
            
            // Update progress
            if ( $progress_key ) {
                $product_name = isset( $dopigo_product['name'] ) ? $dopigo_product['name'] : 'Unknown Product';
                $progress = get_transient( 'dopigo_progress_' . $progress_key );
                $recent_products = isset( $progress['recent_products'] ) ? $progress['recent_products'] : array();
                
                // Add current product to recent list (keep last 10)
                $recent_products[] = array(
                    'name' => $product_name,
                    'meta_id' => isset( $dopigo_product['meta_id'] ) ? $dopigo_product['meta_id'] : '',
                    'status' => 'processing',
                    'time' => time()
                );
                if ( count( $recent_products ) > 10 ) {
                    $recent_products = array_slice( $recent_products, -10 );
                }
                
                self::update_progress( $progress_key, array(
                    'processed' => $product_num,
                    'success' => $success_count,
                    'errors' => $error_count,
                    'current_product' => $product_name,
                    'current_meta_id' => isset( $dopigo_product['meta_id'] ) ? $dopigo_product['meta_id'] : '',
                    'status' => 'running',
                    'recent_products' => $recent_products
                ) );
            }
            
            try {
                // Validate product data
                if ( ! isset( $dopigo_product['meta_id'] ) ) {
                    throw new Exception( sprintf( 'Product #%d: Missing meta_id', $product_num ) );
                }
                
                if ( ! isset( $dopigo_product['products'] ) || ! is_array( $dopigo_product['products'] ) || empty( $dopigo_product['products'] ) ) {
                    throw new Exception( sprintf( 'Product #%d (meta_id: %s): Missing or empty products array', $product_num, $dopigo_product['meta_id'] ) );
                }
                
                if ( $debug && ( $product_num % 10 === 0 || $product_num === 1 ) ) {
                    error_log( sprintf( 'Dopigo Mapper: Processing product %d/%d (meta_id: %s)', 
                        $product_num, 
                        $total_count, 
                        $dopigo_product['meta_id'] 
                    ) );
                }
                
                // Check if product already exists by meta_id
                $existing_product = self::find_product_by_dopigo_meta_id( $dopigo_product['meta_id'] );
                
                // If not found, check if it's in trash and restore it
                if ( ! $existing_product ) {
                    $trashed_id = self::find_trashed_product_by_dopigo_meta_id( $dopigo_product['meta_id'] );
                    
                    if ( $trashed_id ) {
                        // Restore from trash
                        wp_untrash_post( $trashed_id );
                        $existing_product = wc_get_product( $trashed_id );
                        
                        if ( $debug ) {
                            error_log( sprintf( 'Dopigo Mapper: Restored product from trash (meta_id: %s, WC ID: %d)', 
                                $dopigo_product['meta_id'], 
                                $trashed_id 
                            ) );
                        }
                    }
                }
                
                if ( $existing_product ) {
                    if ( $debug && $product_num <= 5 ) {
                        error_log( sprintf( 'Dopigo Mapper: Product meta_id %s exists, updating (WC ID: %d)', 
                            $dopigo_product['meta_id'], 
                            $existing_product->get_id() 
                        ) );
                    }
                } else {
                    if ( $debug && $product_num <= 5 ) {
                        error_log( sprintf( 'Dopigo Mapper: Product meta_id %s not found, creating new', 
                            $dopigo_product['meta_id'] 
                        ) );
                    }
                }
                
                $wc_product = self::dopigo_to_woocommerce( $dopigo_product, $existing_product, $skip_images );
                $product_ids[] = $wc_product->get_id();
                $success_count++;
                
                // Update recent products with success status
                if ( $progress_key && isset( $dopigo_product['meta_id'] ) ) {
                    $progress = get_transient( 'dopigo_progress_' . $progress_key );
                    $recent_products = isset( $progress['recent_products'] ) ? $progress['recent_products'] : array();
                    if ( ! empty( $recent_products ) ) {
                        $last_index = count( $recent_products ) - 1;
                        if ( isset( $recent_products[ $last_index ]['meta_id'] ) && 
                             $recent_products[ $last_index ]['meta_id'] === $dopigo_product['meta_id'] ) {
                            $recent_products[ $last_index ]['status'] = 'success';
                            $recent_products[ $last_index ]['wc_id'] = $wc_product->get_id();
                            self::update_progress( $progress_key, array( 
                                'recent_products' => $recent_products,
                                'success' => $success_count
                            ) );
                        }
                    }
                }
                
                if ( $debug && $product_num <= 5 ) {
                    error_log( sprintf( 'Dopigo Mapper: Successfully imported product meta_id %s (WC ID: %d)', 
                        $dopigo_product['meta_id'], 
                        $wc_product->get_id() 
                    ) );
                }
                
            } catch ( Exception $e ) {
                $error_count++;
                $error_message = sprintf( 'Dopigo Import Error (Product #%d, meta_id: %s): %s', 
                    $product_num,
                    isset( $dopigo_product['meta_id'] ) ? $dopigo_product['meta_id'] : 'unknown',
                    $e->getMessage() 
                );
                if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                    error_log( $error_message );
                }
                
                if ( $debug ) {
                    error_log( 'Dopigo Import Error Stack: ' . $e->getTraceAsString() );
                }
                
                // Update recent products with error status
                if ( $progress_key && isset( $dopigo_product['meta_id'] ) ) {
                    $progress = get_transient( 'dopigo_progress_' . $progress_key );
                    $recent_products = isset( $progress['recent_products'] ) ? $progress['recent_products'] : array();
                    if ( ! empty( $recent_products ) ) {
                        $last_index = count( $recent_products ) - 1;
                        if ( isset( $recent_products[ $last_index ]['meta_id'] ) && 
                             $recent_products[ $last_index ]['meta_id'] === $dopigo_product['meta_id'] ) {
                            $recent_products[ $last_index ]['status'] = 'error';
                            $recent_products[ $last_index ]['error'] = $e->getMessage();
                            self::update_progress( $progress_key, array( 
                                'recent_products' => $recent_products,
                                'errors' => $error_count
                            ) );
                        }
                    }
                }
                
                continue;
            }
        }
        
        if ( $debug ) {
            error_log( sprintf( 'Dopigo Mapper: Batch import complete. Success: %d, Errors: %d, Total: %d', 
                $success_count, 
                $error_count, 
                $total_count 
            ) );
        }
        
        // Final progress update
        if ( $progress_key ) {
            self::update_progress( $progress_key, array(
                'processed' => $total_count,
                'success' => $success_count,
                'errors' => $error_count,
                'status' => 'completed',
                'end_time' => time(),
                'product_ids' => $product_ids
            ) );
        }
        
        return $product_ids;
    }
    
    /**
     * Update progress for real-time tracking
     */
    public static function update_progress( $key, $data ) {
        $progress = get_transient( 'dopigo_progress_' . $key );
        if ( ! $progress ) {
            $progress = array();
        }
        
        $progress = array_merge( $progress, $data );
        set_transient( 'dopigo_progress_' . $key, $progress, 600 ); // 10 minutes
    }
    
    /**
     * Get progress status
     */
    public static function get_progress( $key ) {
        return get_transient( 'dopigo_progress_' . $key );
    }
    
    /**
     * Clear progress
     */
    public static function clear_progress( $key ) {
        delete_transient( 'dopigo_progress_' . $key );
    }

    /**
     * Find WooCommerce product by Dopigo meta ID
     * 
     * @param int $dopigo_meta_id Dopigo meta ID
     * @return WC_Product|null
     */
    private static function find_product_by_dopigo_meta_id( $dopigo_meta_id ) {
        global $wpdb;
        
        // Join with posts table to ensure post exists and isn't trashed
        $post_id = $wpdb->get_var( $wpdb->prepare(
            "SELECT p.ID 
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
             WHERE pm.meta_key = '_dopigo_meta_id' 
             AND pm.meta_value = %s
             AND p.post_type IN ('product', 'product_variation')
             AND p.post_status != 'trash'
             LIMIT 1",
            $dopigo_meta_id
        ) );
        
        if ( $post_id ) {
            $product = wc_get_product( $post_id );
            // Double check the product is valid
            if ( $product && $product->get_id() > 0 ) {
                return $product;
            }
        }
        
        return null;
    }
    
    /**
     * Find trashed product by Dopigo meta ID
     * 
     * @param int $dopigo_meta_id Dopigo meta ID
     * @return int|null Post ID of trashed product
     */
    private static function find_trashed_product_by_dopigo_meta_id( $dopigo_meta_id ) {
        global $wpdb;
        
        $post_id = $wpdb->get_var( $wpdb->prepare(
            "SELECT p.ID 
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
             WHERE pm.meta_key = '_dopigo_meta_id' 
             AND pm.meta_value = %s
             AND p.post_type IN ('product', 'product_variation')
             AND p.post_status = 'trash'
             LIMIT 1",
            $dopigo_meta_id
        ) );
        
        return $post_id ? intval( $post_id ) : null;
    }

    /**
     * Sync stock from Dopigo to WooCommerce
     * 
     * @param int $dopigo_product_id Dopigo product ID
     * @param int $stock_quantity Stock quantity
     * @return bool Success status
     */
    public static function sync_stock_from_dopigo( $dopigo_product_id, $stock_quantity ) {
        global $wpdb;
        
        $post_id = $wpdb->get_var( $wpdb->prepare(
            "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '_dopigo_product_id' AND meta_value = %s LIMIT 1",
            $dopigo_product_id
        ) );
        
        if ( ! $post_id ) {
            return false;
        }
        
        $product = wc_get_product( $post_id );
        
        if ( ! $product ) {
            return false;
        }
        
        $product->set_stock_quantity( intval( $stock_quantity ) );
        $product->set_stock_status( $stock_quantity > 0 ? 'instock' : 'outofstock' );
        $product->save();
        
        return true;
    }
}

