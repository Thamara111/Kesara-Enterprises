<?php
/**
 * Model Layer - Server-Side MOQ Validation and Tier Resolution
 * 
 * This file implements the secure business logic that validates order quantities
 * against Minimum Order Quantities (MOQ) and resolves unit prices according to
 * database pricing tiers. This prevents client-side price/MOQ tampering.
 */

/**
 * Resolves the unit price for a product based on the quantity ordered and database tiers.
 * 
 * @param PDO $pdo The PDO connection
 * @param int $product_id The product ID
 * @param int $quantity The quantity ordered
 * @return float The resolved unit price
 */
function resolveTierPrice(PDO $pdo, int $product_id, int $quantity): float {
    // Fetch pricing tiers for the product sorted by min_qty ASC
    $stmt = $pdo->prepare("
        SELECT min_qty, max_qty, price 
        FROM pricing_tiers 
        WHERE product_id = ? 
        ORDER BY min_qty ASC
    ");
    $stmt->execute([$product_id]);
    $tiers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($tiers)) {
        // Fallback to the product's base price if no tiers are defined
        $stmt_base = $pdo->prepare("SELECT base_price FROM products WHERE id = ?");
        $stmt_base->execute([$product_id]);
        $base_price = $stmt_base->fetchColumn();
        return $base_price !== false ? (float)$base_price : 0.0;
    }

    // Find the matching tier based on quantity
    foreach ($tiers as $tier) {
        $min = (int)$tier['min_qty'];
        $max = $tier['max_qty'] !== null ? (int)$tier['max_qty'] : null;

        if ($quantity >= $min && ($max === null || $quantity <= $max)) {
            return (float)$tier['price'];
        }
    }

    // Fallback: Use the last (highest volume) tier price
    return (float)end($tiers)['price'];
}

/**
 * Validates order items against database MOQ rules and verifies prices.
 * 
 * @param PDO $pdo The PDO connection
 * @param array $items Array of order items from the input
 * @return array Tuple of array of errors, float calculated total, and validated items
 */
function validateOrderItems(PDO $pdo, array $items): array {
    $errors = [];
    $calculated_total = 0.0;
    $validated_items = [];

    // 1. Group quantities by product_id
    // This aggregates multiple cart lines for the same product (e.g. different colors/sizes)
    // so that the MOQ validation checks the TOTAL quantity ordered for that product, not just per line.
    $product_totals = [];
    foreach ($items as $item) {
        $pid = (int)($item['product_id'] ?? 0);
        $qty = (int)($item['quantity'] ?? 0);
        if (!isset($product_totals[$pid])) $product_totals[$pid] = 0;
        $product_totals[$pid] += $qty;
    }

    // 2. Validate MOQ and resolve tier price per product_id
    $product_metadata = [];
    foreach ($product_totals as $pid => $total_qty) {
        // Fetch the product's base information from the database
        $stmt = $pdo->prepare("SELECT name, moq FROM products WHERE id = ?");
        $stmt->execute([$pid]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$product) {
            $errors[] = "Product ID {$pid} does not exist.";
            continue;
        }

        // Compare the total aggregated quantity against the database Minimum Order Quantity
        $moq = (int)$product['moq'];
        if ($total_qty < $moq) {
            $errors[] = "{$product['name']} (ID: {$pid}) failed MOQ validation: ordered {$total_qty} total, but minimum is {$moq}.";
        }
        
        // Retrieve the authoritative tier price from the server based on the aggregated quantity
        $product_metadata[$pid] = resolveTierPrice($pdo, $pid, $total_qty);
    }

    // 3. Assemble final validated items using the correct volume tier price
    // If there were no validation errors, rebuild the items array with the server-verified prices
    if (empty($errors)) {
        foreach ($items as $index => $item) {
            $product_id = (int)($item['product_id'] ?? 0);
            $qty = (int)($item['quantity'] ?? 0);
            
            if (!isset($product_metadata[$product_id])) continue;
            
            $resolved_price = $product_metadata[$product_id];
            $item_subtotal = $resolved_price * $qty;
            $calculated_total += $item_subtotal; // Accumulate grand total securely

            $validated_items[] = [
                'product_id' => $product_id,
                'quantity' => $qty,
                'unit_price' => $resolved_price, // Use the server-side resolved price, ignoring any client price
                'color' => $item['color'] ?? '',
                'size' => $item['size'] ?? ''
            ];
        }
    }

    // Return the tuple of results
    return [
        'errors' => $errors,
        'calculated_total' => $calculated_total,
        'validated_items' => $validated_items
    ];
}
