<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Get authentication token from Dopigo API
 * 
 * @param string $username Username or email
 * @param string $password Password
 * @return string|false Auth token on success, false on failure
 */
function get_auth_token($username, $password) {
    /**
     * curl --request POST \
     *--url https://panel.dopigo.com/users/get_auth_token/ \
     *--header 'accept: application/json' \
     *--header 'content-type: application/form-data'
     */
    $url = 'https://panel.dopigo.com/users/get_auth_token/';
    $headers = [
        'accept: application/json',
        'content-type: application/x-www-form-urlencoded',
    ];
    $data = [
        'username' => $username,
        'password' => $password,
    ];
    
    $response = wp_remote_post($url, [
        'headers' => $headers,
        'body' => $data,
        'timeout' => 30,
    ]);
    
    // Check for errors
    if ( is_wp_error( $response ) ) {
        return false;
    }
    
    $response_code = wp_remote_retrieve_response_code( $response );
    if ( $response_code !== 200 ) {
        return false;
    }
    
    $body = wp_remote_retrieve_body($response);
    $body = json_decode($body, true);
    
    if ( ! isset( $body['token'] ) ) {
        return false;
    }

    return $body['token'];
    
}

/**
 * Get all products from Dopigo API
 * 
 * @param string|null $auth_token Optional auth token. If not provided, will try to get from settings.
 * @param int $limit Limit number of products per request
 * @param int $offset Offset for pagination
 * @return array|false Products array on success, false on failure
 */
function get_all_dopigo_products($auth_token = null, $limit = 100, $offset = 0) {
    /**
     * curl --request GET \
     *--url https://panel.dopigo.com/api/v1/products/all/ \
     *--header 'accept: application/json' \
     *--header 'Authorization: Token YOUR_TOKEN'
     */
    
    if ( ! $auth_token ) {
        // Try to get from settings
        $auth_token = get_option( 'dopigo_api_key', '' );
    }
    
    if ( ! $auth_token ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'Dopigo: No auth token provided' );
        }
        return false;
    }
    
    $url = add_query_arg( array(
        'limit' => $limit,
        'offset' => $offset
    ), 'https://panel.dopigo.com/api/v1/products/all/' );
    
    $response = wp_remote_get($url, array(
        'headers' => array(
            'Authorization' => 'Token ' . $auth_token,
            'Accept' => 'application/json'
        ),
        'timeout' => 60,
    ));
    
    // Check for errors
    if ( is_wp_error( $response ) ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'Dopigo API Error: ' . $response->get_error_message() );
        }
        return false;
    }
    
    $response_code = wp_remote_retrieve_response_code( $response );

    if ( $response_code !== 200 ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'Dopigo API Error: HTTP ' . $response_code );
            $body = wp_remote_retrieve_body( $response );
            error_log( 'Dopigo API Response: ' . $body );
        }
        return false;
    }

    $body = wp_remote_retrieve_body($response);
    $products = json_decode($body, true);
    
    if ( ! is_array( $products ) ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'Dopigo API Error: Invalid JSON response' );
        }
        return false;
    }
    
    return $products;
}

/**
 * Get all products with pagination handling
 * 
 * @param string|null $auth_token Optional auth token
 * @param bool $debug Enable debug logging
 * @return array|false All products or false on failure
 */
function get_all_dopigo_products_paginated($auth_token = null, $debug = false) {
    $all_products = array();
    $offset = 0;
    $limit = 100;
    $has_more = true;
    $page = 1;
    $total_count = 0;
    
    if ( $debug ) {
        error_log( 'Dopigo: Starting paginated fetch...' );
    }
    
    while ( $has_more ) {
        if ( $debug ) {
            error_log( sprintf( 'Dopigo: Fetching page %d (offset: %d, limit: %d)', $page, $offset, $limit ) );
        }
        
        $response = get_all_dopigo_products( $auth_token, $limit, $offset );
        
        if ( $response === false ) {
            if ( $debug ) {
                error_log( 'Dopigo: Failed to fetch products at offset ' . $offset );
            }
            return false;
        }
        
        // Check response structure
        if ( isset( $response['results'] ) && is_array( $response['results'] ) ) {
            $products_count = count( $response['results'] );
            $all_products = array_merge( $all_products, $response['results'] );
            
            // Get total count from first page
            if ( $page === 1 && isset( $response['count'] ) ) {
                $total_count = intval( $response['count'] );
                if ( $debug ) {
                    error_log( sprintf( 'Dopigo: Total products available: %d', $total_count ) );
                }
            }
            
            if ( $debug ) {
                error_log( sprintf( 'Dopigo: Page %d returned %d products. Total collected: %d', $page, $products_count, count( $all_products ) ) );
            }
            
            // Check if there are more products
            // Check both 'next' field and if we got fewer results than requested
            $has_more = ! empty( $response['next'] ) && $products_count === $limit;
            
            if ( $debug ) {
                error_log( sprintf( 'Dopigo: Has more products: %s (next: %s, products_count: %d, limit: %d)', 
                    $has_more ? 'yes' : 'no',
                    ! empty( $response['next'] ) ? 'yes' : 'no',
                    $products_count,
                    $limit
                ) );
            }
            
            // If we got fewer products than requested, we're done
            if ( $products_count < $limit ) {
                $has_more = false;
                if ( $debug ) {
                    error_log( sprintf( 'Dopigo: Received %d products (less than limit %d), stopping pagination', $products_count, $limit ) );
                }
            }
            
            $offset += $limit;
            $page++;
            
        } else {
            // If results are directly in the response (not paginated)
            if ( $debug ) {
                error_log( 'Dopigo: Response is not paginated, using all results' );
            }
            $all_products = is_array( $response ) ? $response : array( $response );
            $has_more = false;
        }
        
        // Safety break to prevent infinite loops
        if ( $offset > 10000 ) {
            if ( $debug ) {
                error_log( 'Dopigo: Pagination limit reached (10000 products)' );
            }
            break;
        }
        
        // Safety break if we've collected more than total count
        if ( $total_count > 0 && count( $all_products ) >= $total_count ) {
            if ( $debug ) {
                error_log( sprintf( 'Dopigo: Collected all products (%d >= %d)', count( $all_products ), $total_count ) );
            }
            break;
        }
    }
    
    if ( $debug ) {
        error_log( sprintf( 'Dopigo: Pagination complete. Total products collected: %d (expected: %d)', 
            count( $all_products ), 
            $total_count > 0 ? $total_count : 'unknown'
        ) );
    }
    
    return array(
        'count' => count( $all_products ),
        'total_count' => $total_count > 0 ? $total_count : count( $all_products ),
        'results' => $all_products
    );
}