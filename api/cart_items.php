<?php
/**
 * REST API - Cart Items Fetcher
 * This endpoint receives an array of product IDs and returns their details,
 * including dynamic pricing tiers and minimum order quantities (MOQ).
 */
require_once __DIR__ . '/../database/connection.php';

// Set response type to JSON
header('Content-Type: application/json');

try {
    // Read and decode the JSON payload from the request body
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validate input: ensure product_ids exists and is a non-empty array
    if (!isset($input['product_ids']) || !is_array($input['product_ids']) || empty($input['product_ids'])) {
        echo json_encode(['status' => 'success', 'data' => []]);
        exit;
    }

    // Sanitize input by casting all IDs to integers to prevent SQL injection
    $ids = array_map('intval', $input['product_ids']);
    
    // Create placeholders (e.g., ?,?,?) for the IN clause based on the number of IDs
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    
    // Prepare and execute the query to fetch basic product details (ignoring soft-deleted items)
    $stmt = $pdo->prepare("SELECT id, name, sku, images FROM products WHERE id IN ($placeholders) AND deleted_at IS NULL");
    $stmt->execute($ids);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Initialize the results array
    $result = [];
    
    // Iterate over each fetched product to gather its specific pricing tiers
    foreach ($products as $p) {
        $product_id = $p['id'];
        
        // Fetch pricing tiers for the current product, ordered by minimum quantity ascending
        // This is used to determine the lowest possible Minimum Order Quantity (MOQ)
        $tier_stmt = $pdo->prepare("SELECT min_qty, max_qty, price FROM pricing_tiers WHERE product_id = ? ORDER BY min_qty ASC");
        $tier_stmt->execute([$product_id]);
        $tiers_raw = $tier_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $moq = 1;
        $tiers = [];
        
        // If pricing tiers exist, parse them and extract the overall MOQ (the lowest min_qty)
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
            // Fallback tier definition if the product has no explicit pricing tiers set up
            $tiers = [['min' => 1, 'max' => null, 'price' => 0]];
        }

        // Decode the JSON images array and pick the first image to display in the cart
        $images = json_decode($p['images'] ?? '[]', true) ?: [];
        $image = !empty($images) ? $images[0] : null;

        // Construct the structured product array to be returned to the frontend
        $result[] = [
            'id' => (int)$product_id,
            'name' => $p['name'],
            'meta' => 'SKU ' . $p['sku'],
            'image' => $image,
            'moq' => (int)$moq,
            'tiers' => $tiers
        ];
    }

    // Return the successfully constructed array of product data
    echo json_encode(['status' => 'success', 'data' => $result]);
} catch (\Exception $e) {
    // Catch any database or logic exceptions, log them for debugging, and return a 500 error
    error_log("Cart API Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Failed to fetch cart items: ' . $e->getMessage()]);
}
