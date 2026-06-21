-- Operational Queries & Transactions for Kesara Enterprises
-- Generated: 2026-06-09
USE kesara_db;

-- ============================================================================
-- 1. OPERATIONAL QUERIES
-- ============================================================================

-- Mark POs as Overdue (Daily Job)
-- Runs regularly to transition sent purchase orders to overdue if they exceed expected delivery.
UPDATE purchase_orders
SET status = 'overdue'
WHERE status = 'sent'
  AND expected_at < CURDATE();


-- Get Customer Lifetime Metrics
-- Retrieves overall order counts and total spending for a specific customer.
-- Parameter 1: user_id (int)
SELECT COUNT(*) as total_orders, SUM(total_amount) as total_spent
FROM orders
WHERE user_id = ? AND status != 'cancelled';


-- Top Products by Revenue (Reports Page)
-- Aggregates sales and revenue performance by product in a given date range.
-- Parameter 1: start_date (datetime)
-- Parameter 2: end_date (datetime)
SELECT p.name, p.sku,
  SUM(oi.quantity) AS units_sold,
  SUM(oi.quantity * oi.unit_price) AS revenue
FROM order_items oi
JOIN products p ON oi.product_id = p.id
JOIN orders o ON oi.order_id = o.id
WHERE o.created_at BETWEEN ? AND ?
  AND o.status != 'cancelled'
GROUP BY p.id
ORDER BY revenue DESC
LIMIT 10;


-- Get Categories for Shop By Category Section (with dynamic Style/Product Count)
-- Retrieves the list of categories, their icons, and the count of styles (products) in each category.
SELECT c.id, c.name, c.slug, c.icon, c.description, COUNT(p.id) AS style_count
FROM categories c
LEFT JOIN products p ON p.category_id = c.id
GROUP BY c.id;


-- ============================================================================
-- 2. CRITICAL TRANSACTION: GOODS RECEIVED NOTE (GRN) CONFIRMATION
-- ============================================================================

-- Starts an atomic transaction to process incoming goods, adjust product stock levels,
-- log stock adjustments, and automatically calculate and set the status of the Purchase Order.

START TRANSACTION;

-- 1. Insert GRN record
-- Parameters: po_id, received_by, note
INSERT INTO goods_received_notes (po_id, received_by, received_at, note) 
VALUES (?, ?, NOW(), ?);

-- 2. Update PO line item quantities
-- Parameters: qty_received_addition, po_id, item_name
UPDATE purchase_order_items 
SET qty_received = qty_received + ? 
WHERE po_id = ? AND item_name = ?;

-- 3. Add to inventory
-- Parameters: qty_received_addition, inventory_id
UPDATE inventory 
SET quantity = quantity + ? 
WHERE id = ?;

-- 4. Write inventory adjustment log
-- Parameters: inventory_id, qty_before, qty_after, note, admin_id
INSERT INTO inventory_log (inventory_id, adj_type, qty_before, qty_after, note, admin_id) 
VALUES (?, 'add', ?, ?, ?, ?);

-- 5. Update PO status (partial or received)
-- Parameters: po_id_check (for subquery), po_id_target
-- Note: Computes status = 'received' if all items are fully received, otherwise status = 'partial'.
UPDATE purchase_orders 
SET status = CASE 
  WHEN NOT EXISTS (
    SELECT 1 
    FROM purchase_order_items 
    WHERE po_id = ? AND qty_ordered > qty_received
  ) THEN 'received' 
  ELSE 'partial' 
END 
WHERE id = ?;

COMMIT;
