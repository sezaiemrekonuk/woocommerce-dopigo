<?php
/**
 * Dopigo Category Mapper
 *
 * Handles mapping between Dopigo category IDs and WooCommerce product categories.
 *
 * @package WooCommerce_Dopigo
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Dopigo_Category_Mapper {

    const CATEGORY_MAP_OPTION   = 'dopigo_category_map';
    const PENDING_OPTION        = 'dopigo_category_pending';
    const TERM_META_KEY         = '_dopigo_category_id';
    const TAXONOMY              = 'product_cat';

    /**
     * Ensure a WooCommerce category exists for the provided Dopigo ID using the full category path.
     *
     * @param int    $dopigo_id Dopigo category ID.
     * @param string $full_path Full category path (e.g. "Bilgisayar > Bilgisayar Bileşenleri > Soğutucu & Fan").
     *
     * @return int|null WooCommerce term ID or null on failure.
     */
    public static function ensure_category_from_path( $dopigo_id, $full_path ) {
        $dopigo_id = intval( $dopigo_id );

        if ( $dopigo_id <= 0 || empty( $full_path ) ) {
            return null;
        }

        $existing = self::get_wc_category_id( $dopigo_id );
        if ( $existing ) {
            self::remove_pending_category( $dopigo_id );
            return $existing;
        }

        $segments = array_filter( array_map( 'trim', explode( '>', $full_path ) ) );

        if ( empty( $segments ) ) {
            return null;
        }

        $parent_id = 0;
        $term_id   = null;

        foreach ( $segments as $segment ) {
            $term_id = self::get_term_id_by_name_and_parent( $segment, $parent_id );

            if ( ! $term_id ) {
                $result = wp_insert_term( $segment, self::TAXONOMY, array( 'parent' => $parent_id ) );

                if ( is_wp_error( $result ) ) {
                    return null;
                }

                $term_id = $result['term_id'];
            }

            $parent_id = $term_id;
        }

        if ( $term_id ) {
            self::register_mapping( $dopigo_id, $term_id );
            self::remove_pending_category( $dopigo_id );
        }

        return $term_id;
    }

    /**
     * Get WooCommerce category ID mapped to a Dopigo category ID.
     *
     * @param int $dopigo_id Dopigo category ID.
     *
     * @return int|null
     */
    public static function get_wc_category_id( $dopigo_id ) {
        $dopigo_id = intval( $dopigo_id );

        if ( $dopigo_id <= 0 ) {
            return null;
        }

        $map = get_option( self::CATEGORY_MAP_OPTION, array() );

        if ( isset( $map[ $dopigo_id ] ) && term_exists( intval( $map[ $dopigo_id ] ), self::TAXONOMY ) ) {
            return intval( $map[ $dopigo_id ] );
        }

        $term_id = self::lookup_term_id_by_meta( $dopigo_id );

        if ( $term_id ) {
            self::register_mapping( $dopigo_id, $term_id );
            return $term_id;
        }

        return null;
    }

    /**
     * Register a mapping between Dopigo category ID and WooCommerce term ID.
     *
     * @param int $dopigo_id Dopigo category ID.
     * @param int $term_id   WooCommerce category ID.
     */
    public static function register_mapping( $dopigo_id, $term_id ) {
        $dopigo_id = intval( $dopigo_id );
        $term_id   = intval( $term_id );

        if ( $dopigo_id <= 0 || $term_id <= 0 ) {
            return;
        }

        update_term_meta( $term_id, self::TERM_META_KEY, $dopigo_id );

        $map              = get_option( self::CATEGORY_MAP_OPTION, array() );
        $map[ $dopigo_id ] = $term_id;
        update_option( self::CATEGORY_MAP_OPTION, $map );
    }

    /**
     * Mark a Dopigo category ID as pending mapping.
     *
     * @param int $dopigo_id Dopigo category ID.
     */
    public static function add_pending_category( $dopigo_id ) {
        $dopigo_id = intval( $dopigo_id );

        if ( $dopigo_id <= 0 ) {
            return;
        }

        $pending = get_option( self::PENDING_OPTION, array() );

        if ( ! in_array( $dopigo_id, $pending, true ) ) {
            $pending[] = $dopigo_id;
            update_option( self::PENDING_OPTION, $pending );
        }
    }

    /**
     * Remove a Dopigo category ID from the pending queue.
     *
     * @param int $dopigo_id Dopigo category ID.
     */
    public static function remove_pending_category( $dopigo_id ) {
        $dopigo_id = intval( $dopigo_id );

        if ( $dopigo_id <= 0 ) {
            return;
        }

        $pending = get_option( self::PENDING_OPTION, array() );

        if ( empty( $pending ) ) {
            return;
        }

        $updated = array_filter( $pending, function( $value ) use ( $dopigo_id ) {
            return intval( $value ) !== $dopigo_id;
        } );

        if ( count( $updated ) !== count( $pending ) ) {
            update_option( self::PENDING_OPTION, array_values( $updated ) );
        }
    }

    /**
     * Get all pending Dopigo category IDs.
     *
     * @return array
     */
    public static function get_pending_categories() {
        $pending = get_option( self::PENDING_OPTION, array() );
        return array_map( 'intval', $pending );
    }

    /**
     * Helper: Find WooCommerce term ID by name and parent.
     *
     * @param string $name      Category name.
     * @param int    $parent_id Parent term ID.
     *
     * @return int|null
     */
    private static function get_term_id_by_name_and_parent( $name, $parent_id = 0 ) {
        $terms = get_terms( array(
            'taxonomy'   => self::TAXONOMY,
            'hide_empty' => false,
            'name'       => $name,
            'parent'     => $parent_id,
            'number'     => 1,
        ) );

        if ( is_wp_error( $terms ) || empty( $terms ) ) {
            return null;
        }

        return intval( $terms[0]->term_id );
    }

    /**
     * Helper: Lookup term ID by stored Dopigo ID in term meta.
     *
     * @param int $dopigo_id Dopigo category ID.
     *
     * @return int|null
     */
    private static function lookup_term_id_by_meta( $dopigo_id ) {
        $terms = get_terms( array(
            'taxonomy'   => self::TAXONOMY,
            'hide_empty' => false,
            'meta_query' => array(
                array(
                    'key'   => self::TERM_META_KEY,
                    'value' => $dopigo_id,
                ),
            ),
            'number'     => 1,
        ) );

        if ( is_wp_error( $terms ) || empty( $terms ) ) {
            return null;
        }

        return intval( $terms[0]->term_id );
    }
}


