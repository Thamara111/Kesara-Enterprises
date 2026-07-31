<?php
/**
 * Server-side Order Validation and Tier Pricing Helper
 * Validates minimum order quantities (MOQ) and calculates tier pricing from database rules to ensure accurate cart totals.
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
    // Getting data -> Fetching pricing tiers for this product from database
    $stmt = $pdo->prepare("
        SELECT min_qty, max_qty, price 
        FROM pricing_tiers 
        WHERE product_id = ? 
        ORDER BY min_qty ASC
    ");
    $stmt->execute([$product_id]);
    $tiers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($tiers)) {
        // Fallback -> Getting product base price if no custom tiers exist
        $stmt_base = $pdo->prepare("SELECT base_price FROM products WHERE id = ?");
        $stmt_base->execute([$product_id]);
        $base_price = $stmt_base->fetchColumn();
        return $base_price !== false ? (float)$base_price : 0.0;
    }

    // Processing data -> Finding matching price tier for total quantity
    foreach ($tiers as $tier) {
        $min = (int)$tier['min_qty'];
        $max = $tier['max_qty'] !== null ? (int)$tier['max_qty'] : null;

        if ($quantity >= $min && ($max === null || $quantity <= $max)) {
            return (float)$tier['price'];
        }
    }

    // Fallback -> Using highest volume tier price if quantity exceeds all defined limits
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

    // Processing data -> Grouping and totaling quantities ordered for each product
    $product_totals = [];
    foreach ($items as $item) {
        $pid = (int)($item['product_id'] ?? 0);
        $qty = (int)($item['quantity'] ?? 0);
        if (!isset($product_totals[$pid])) $product_totals[$pid] = 0;
        $product_totals[$pid] += $qty;
    }

    // Checking data -> Validating minimum order quantities and calculating server price tiers
    $product_metadata = [];
    foreach ($product_totals as $pid => $total_qty) {
        // Getting data -> Fetching product name and minimum order quantity from database
        $stmt = $pdo->prepare("SELECT name, moq FROM products WHERE id = ?");
        $stmt->execute([$pid]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$product) {
            $errors[] = "Product ID {$pid} does not exist.";
            continue;
        }

        // Getting data -> Fetching minimum quantity from pricing tiers or product table
        $tier_stmt = $pdo->prepare("SELECT min_qty FROM pricing_tiers WHERE product_id = ? ORDER BY min_qty ASC LIMIT 1");
        $tier_stmt->execute([$pid]);
        $first_tier = $tier_stmt->fetch(PDO::FETCH_ASSOC);
        $moq = (!empty($first_tier) && isset($first_tier['min_qty'])) ? (int)$first_tier['min_qty'] : (int)$product['moq'];

        if ($total_qty < $moq) {
            $errors[] = "{$product['name']} (ID: {$pid}) failed MOQ validation: ordered {$total_qty} total, but minimum is {$moq}.";
        }
        
        // Getting data -> Getting server-side tier price for total ordered quantity
        $product_metadata[$pid] = resolveTierPrice($pdo, $pid, $total_qty);
    }

    // Formatting data -> Assembling verified order items with server-calculated unit prices
    if (empty($errors)) {
        foreach ($items as $index => $item) {
            $product_id = (int)($item['product_id'] ?? 0);
            $qty = (int)($item['quantity'] ?? 0);
            
            if (!isset($product_metadata[$product_id])) continue;
            
            $resolved_price = $product_metadata[$product_id];
            $item_subtotal = $resolved_price * $qty;
            $calculated_total += $item_subtotal; // Processing data -> Accumulating order grand total

            $validated_items[] = [
                'product_id' => $product_id,
                'quantity' => $qty,
                'unit_price' => $resolved_price, // Formatting data -> Assigning server-calculated unit price to order item
                'color' => $item['color'] ?? '',
                'size' => $item['size'] ?? ''
            ];
        }
    }

    // Response -> Returning validation errors, calculated grand total, and verified item list
    return [
        'errors' => $errors,
        'calculated_total' => $calculated_total,
        'validated_items' => $validated_items
    ];
}



/*
=============================================================================
 FILE DEPENDENCY & CROSS-REFERENCE MAP
=============================================================================
 FILE: api/model_validation.php (Self-Healing Schema & Field Validator API)

 CONNECTED / DEPENDENT FILES:
   - database/connection.php
   - database/schema.sql

 RELATED FILES TO UPDATE WHEN MODIFYING THIS FILE:
   - Database Table & Column structure self-healing checks
=============================================================================
*/

