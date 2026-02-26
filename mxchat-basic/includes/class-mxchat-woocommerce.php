<?php

if (!defined('ABSPATH')) {
    exit;
}

class MxChat_WooCommerce {

    public static function init() {
        add_action('wp_ajax_mxchat_fetch_user_orders', array(__CLASS__, 'mxchat_fetch_user_orders'));
        add_action('wp_ajax_nopriv_mxchat_fetch_user_orders', array(__CLASS__, 'mxchat_fetch_user_orders'));
        
        add_action('wp_ajax_mxchat_add_to_cart', array(__CLASS__, 'mxchat_add_to_cart'));
add_action('wp_ajax_nopriv_mxchat_add_to_cart', array(__CLASS__, 'mxchat_add_to_cart'));

    }
    
    // New function to handle add to cart requests
public static function mxchat_add_to_cart() {
    // Validate nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'mxchat_nonce')) {
        wp_send_json_error('Invalid nonce.');
        wp_die();
    }

    $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
    if (!$product_id) {
        wp_send_json_error('Invalid product ID.');
        wp_die();
    }

    // Add to WooCommerce cart
    $added = WC()->cart->add_to_cart($product_id);
    if ($added) {
        $product = wc_get_product($product_id);
        wp_send_json_success(['message' => "The product '{$product->get_name()}' has been added to your cart."]);
    } else {
        wp_send_json_error('Error adding product to cart.');
    }
    wp_die();
}


public static function mxchat_fetch_user_orders_details($type = 'all') {
    $user_id = get_current_user_id();
    if (!$user_id && isset(WC()->session)) {
        $user_id = WC()->session->get_customer_id();
    }

    if (!$user_id) {
        return [];
    }

    $args = array(
        'customer_id' => $user_id,
        'limit'       => ($type === 'last') ? 1 : 5, // Limit to last 5 orders for 'all'
        'orderby'     => 'date',
        'order'       => 'DESC',
    );

    $orders = wc_get_orders($args);
    
    if (empty($orders)) {
        return [];
    }

    $order_details = [];
    foreach ($orders as $order) {
        $items = [];
        foreach ($order->get_items() as $item) {
            $items[] = [
                'name' => $item->get_name(),
                'total' => $order->get_formatted_line_subtotal($item),
                'license_key' => $item->get_meta('_license_key'), // Get specific meta if needed
                'quantity' => $item->get_quantity()
            ];
        }

        $order_details[] = [
            'order_id' => $order->get_id(),
            'date' => $order->get_date_created()->date('F j, Y'),
            'status' => wc_get_order_status_name($order->get_status()),
            'total' => $order->get_total(),
            'formatted_total' => $order->get_formatted_order_total(),
            'items' => $items
        ];

        if ($type === 'last') {
            break;
        }
    }

    return $order_details;
}


    // Check if there are items in the cart
    public static function cart_has_items() {
        return WC()->cart && WC()->cart->get_cart_contents_count() > 0;
    }

/**
 * Extract product ID from message with advanced matching.
 *
 * @param string $message The search message
 * @return int|null Product ID if found, null if no match
 */
public static function mxchat_extract_product_id_from_message($message) {
    if (!function_exists('wc_get_products')) {
        error_log("WooCommerce is not active or not available.");
        return null;
    }

    // Clean and normalize input
    $message = sanitize_text_field(strtolower($message));
    error_log("Product extraction message: " . $message);
    
    // Get all published products
    $products = wc_get_products([
        'status' => 'publish',
        'limit' => -1,
        'return' => 'objects'
    ]);

    error_log("Total products to search: " . count($products));

    // Store all matches with their scores
    $matches = [];
    
    // Search terms (original message and name-only version)
    $search_terms = explode(' ', $message);
    error_log("Search terms: " . implode(', ', $search_terms));

    foreach ($products as $product) {
        $score = 0;
        $product_name = strtolower($product->get_name());
        $product_slug = strtolower($product->get_slug());
        $product_sku = strtolower($product->get_sku());

        // Score calculation
        $matched_terms = 0;
        $consecutive_matches = 0;
        $max_consecutive = 0;

        // Check for consecutive term matches
        for ($i = 0; $i < count($search_terms); $i++) {
            $term = $search_terms[$i];
            
            // Skip common words
            if (strlen($term) <= 2 || in_array($term, ['the', 'and', 'or', 'for', 'to', 'in', 'on', 'at', 'of'])) {
                continue;
            }

            // Check name, slug, and SKU
            if (strpos($product_name, $term) !== false || 
                strpos($product_slug, $term) !== false || 
                strpos($product_sku, $term) !== false) {
                
                $matched_terms++;
                $consecutive_matches++;
                $max_consecutive = max($max_consecutive, $consecutive_matches);
            } else {
                $consecutive_matches = 0;
            }
        }

        // Calculate final score
        if ($matched_terms > 0) {
            // Base score from matched terms
            $score = $matched_terms / count(array_filter($search_terms, function($term) {
                return strlen($term) > 2;
            }));

            // Bonus for consecutive matches
            $score += ($max_consecutive > 1) ? 0.3 : 0;

            // Bonus for exact matches
            if (strpos($product_name, implode(' ', $search_terms)) !== false) {
                $score += 0.5;
            }

            // Store match if score is above zero
            if ($score > 0) {
                $matches[] = [
                    'product_id' => $product->get_id(),
                    'score' => $score,
                    'name' => $product_name,
                    'consecutive_matches' => $max_consecutive
                ];

                error_log(sprintf(
                    "Product '%s' scored %f with %d consecutive matches",
                    $product_name,
                    $score,
                    $max_consecutive
                ));
            }
        }
    }

    // Sort matches by score
    usort($matches, function($a, $b) {
        if ($b['score'] === $a['score']) {
            // If scores are equal, prefer the one with more consecutive matches
            return $b['consecutive_matches'] - $a['consecutive_matches'];
        }
        return $b['score'] <=> $a['score'];
    });

    // Return the best match if we found any
    if (!empty($matches)) {
        $best_match = $matches[0];
        error_log(sprintf(
            "Selected best match: '%s' (ID: %d) with score %f and %d consecutive matches",
            $best_match['name'],
            $best_match['product_id'],
            $best_match['score'],
            $best_match['consecutive_matches']
        ));
        return $best_match['product_id'];
    }

    error_log("No product matches found");
    return null;
}



    // Store the product ID in session when discussed
    public static function store_last_discussed_product($product_id) {
        set_transient('mxchat_last_product', $product_id, 12 * HOUR_IN_SECONDS);
    }

    // Retrieve the last discussed product ID
    public static function get_last_discussed_product() {
        return get_transient('mxchat_last_product');
    }

}

MxChat_WooCommerce::init();
