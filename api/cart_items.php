<?php
/**
 * Cart Items Helper API
 * Takes a list of product IDs and gets their details like pricing tiers, discounts, images, and minimum order quantities for the cart.
 */
require_once __DIR__ . '/../database/connection.php';

// Response format -> Setting content type to JSON
header('Content-Type: application/json');

try {
    // Getting data -> Reading JSON request body
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Checking data -> Checking if product IDs list exists and is not empty
    if (!isset($input['product_ids']) || !is_array($input['product_ids']) || empty($input['product_ids'])) {
        echo json_encode(['status' => 'success', 'data' => []]);
        exit;
    }

    // Checking data -> Converting all product IDs to numbers for safety
    $ids = array_map('intval', $input['product_ids']);
    
    // Preparing query -> Building SQL placeholders for the product IDs
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    
    // Getting data -> Fetching active product details from database
    $stmt = $pdo->prepare("SELECT id, name, sku, images, discount, discount_start, discount_end, base_price FROM products WHERE id IN ($placeholders) AND deleted_at IS NULL");
    $stmt->execute($ids);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Getting data -> Creating empty list for response items
    $result = [];
    
    // Processing data -> Calculating discounts and pricing tiers for each product
    foreach ($products as $p) {
        $product_id = $p['id'];
        $discount_pct = isset($p['discount']) ? (float)$p['discount'] : 0;
        $d_start = !empty($p['discount_start']) ? $p['discount_start'] : null;
        $d_end   = !empty($p['discount_end'])   ? $p['discount_end']   : null;
        $today_str = date('Y-m-d');
        $discount = 0;
        if ($discount_pct > 0) {
            $valid_s = empty($d_start) || ($today_str >= $d_start);
            $valid_e = empty($d_end)   || ($today_str <= $d_end);
            if ($valid_s && $valid_e) {
                $discount = $discount_pct;
            }
        }
        $base_price = isset($p['base_price']) ? (float)$p['base_price'] : 0;
        
        // Getting data -> Fetching wholesale pricing tiers and minimum order quantity for this product
        $tier_stmt = $pdo->prepare("SELECT min_qty, max_qty, price FROM pricing_tiers WHERE product_id = ? ORDER BY min_qty ASC");
        $tier_stmt->execute([$product_id]);
        $tiers_raw = $tier_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $moq = 1;
        $tiers = [];
        
        // Processing data -> Reading tier prices and setting minimum order quantity
        if (count($tiers_raw) > 0) {
            $moq = $tiers_raw[0]['min_qty'];
            foreach ($tiers_raw as $t) {
                $tiers[] = [
                    'min' => (int)$t['min_qty'],
                    'max' => $t['max_qty'] ? (int)$t['max_qty'] : null,
                    'price' => (float)$t['price']
                ];
            }
        } else {
            // Fallback -> Using base price tier if no custom pricing tiers exist
            $tiers = [['min' => 1, 'max' => null, 'price' => $base_price]];
        }

        // Getting image -> Extracting product main image from JSON images array
        $images = json_decode($p['images'] ?? '[]', true) ?: [];
        $image = !empty($images) ? $images[0] : null;

        // Formatting data -> Building formatted product item for cart display
        $result[] = [
            'id' => (int)$product_id,
            'name' => $p['name'],
            'meta' => 'SKU ' . $p['sku'],
            'image' => $image,
            'moq' => (int)$moq,
            'discount' => $discount,
            'tiers' => $tiers
        ];
    }

    // Response -> Returning product cart data as JSON
    echo json_encode(['status' => 'success', 'data' => $result]);
} catch (\Exception $e) {
    // Handling errors -> Logging exception and returning error response
    error_log("Cart API Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Failed to fetch cart items: ' . $e->getMessage()]);
}



/*
=============================================================================
 FILE DEPENDENCY & CROSS-REFERENCE MAP
=============================================================================
 FILE: api/cart_items.php (Session & DB Cart Synchronizer API)

 CONNECTED / DEPENDENT FILES:
   - database/connection.php
   - cart.php
   - checkout.php

 RELATED FILES TO UPDATE WHEN MODIFYING THIS FILE:
   - Shopping Cart UI (cart.php) and Checkout Order summary (checkout.php)
=============================================================================
*/

