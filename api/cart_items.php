<?php
require_once __DIR__ . '/../database/connection.php';

header('Content-Type: application/json');

try {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!isset($input['product_ids']) || !is_array($input['product_ids']) || empty($input['product_ids'])) {
        echo json_encode(['status' => 'success', 'data' => []]);
        exit;
    }

    $ids = array_map('intval', $input['product_ids']);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    
    // Fetch products
    $stmt = $pdo->prepare("SELECT id, name, sku FROM products WHERE id IN ($placeholders) AND deleted_at IS NULL");
    $stmt->execute($ids);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $result = [];
    foreach ($products as $p) {
        $product_id = $p['id'];
        
        // Fetch MOQ from pricing_tiers (using lowest min_qty)
        $tier_stmt = $pdo->prepare("SELECT min_qty, max_qty, price FROM pricing_tiers WHERE product_id = ? ORDER BY min_qty ASC");
        $tier_stmt->execute([$product_id]);
        $tiers_raw = $tier_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $moq = 1;
        $tiers = [];
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
            // Fallback tier if none found
            $tiers = [['min' => 1, 'max' => null, 'price' => 0]];
        }

        $result[] = [
            'id' => (int)$product_id,
            'name' => $p['name'],
            'meta' => 'SKU ' . $p['sku'],
            'moq' => (int)$moq,
            'tiers' => $tiers
        ];
    }

    echo json_encode(['status' => 'success', 'data' => $result]);
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Failed to fetch cart items.']);
}
