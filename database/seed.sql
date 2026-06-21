-- Seed data for Kesara Enterprises
-- Generated: 2026-06-09
USE kesara_db;

-- Clear existing data (in reverse dependency order to avoid foreign key errors)
SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE delivery_issues;
TRUNCATE TABLE delivery_runs;
TRUNCATE TABLE delivery_assignments;
TRUNCATE TABLE goods_received_notes;
TRUNCATE TABLE purchase_order_items;
TRUNCATE TABLE inventory_log;
TRUNCATE TABLE order_items;
TRUNCATE TABLE order_status_log;
TRUNCATE TABLE personnel_zones;
TRUNCATE TABLE purchase_orders;
TRUNCATE TABLE supplier_products;
TRUNCATE TABLE supplier_items;
TRUNCATE TABLE inventory;
TRUNCATE TABLE orders;
TRUNCATE TABLE pricing_tiers;
TRUNCATE TABLE password_resets;
TRUNCATE TABLE delivery_personnel;
TRUNCATE TABLE suppliers;
TRUNCATE TABLE admins;
TRUNCATE TABLE products;
TRUNCATE TABLE categories;
TRUNCATE TABLE users;
SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================================
-- 1. SEED CATEGORIES
-- ============================================================================
INSERT INTO categories (id, name, slug, icon, description) VALUES
(1, 'Briefs', 'briefs', 'ti-shirt', 'Comfortable everyday briefs in multiple colors and premium fabrics.'),
(2, 'Boxers', 'boxers', 'ti-shirt', 'Loose and stretch fit boxers crafted with breathable cotton.'),
(3, 'Trunks', 'trunks', 'ti-shirt', 'Modern trunk cuts combining stretch support and classic style.'),
(4, 'Ladies', 'ladies', 'ti-heart', 'Soft-touch ladies hipster briefs, seamless, and lace wear.'),
(5, 'Children', 'children', 'ti-star', 'Comfortable innerwear for boys and girls made from pure combed cotton.');

-- ============================================================================
-- 2. SEED PRODUCTS
-- ============================================================================
INSERT INTO products (id, name, sku, category_id, description, moq, base_price, status) VALUES
(1, 'Classic Cotton Brief', 'KB-001', 1, 'Classic cut men`s brief. Suitable for all-day wear. Ideal for retail bundles.', 50, 95.00, 'In Stock'),
(2, 'Stretch Boxer', 'KB-008', 2, 'Premium stretch cotton boxers with reinforced stitching.', 100, 155.00, 'In Stock'),
(3, 'Ladies Hipster', 'KL-003', 4, 'Soft touch hipster briefs for maximum comfort.', 50, 115.00, 'Low Stock'),
(4, 'Kids Trunk Set', 'KC-012', 5, 'Comfy cotton trunks for children in fun colors.', 60, 98.00, 'In Stock'),
(5, 'Modal Trunk', 'KB-015', 3, 'Luxury modal fabric trunks with dynamic waistband.', 100, 260.00, 'In Stock'),
(6, 'Sports Brief', 'KB-022', 1, 'Breathable sports mesh brief with moisture-wicking technology.', 50, 175.00, 'Low Stock'),
(7, 'Cotton Trunk', 'KB-034', 3, 'Everyday standard combed cotton trunks.', 80, 210.00, 'In Stock'),
(8, 'Seamless Brief', 'KL-009', 4, 'Invisible seamless briefs for women.', 50, 180.00, 'In Stock');

-- ============================================================================
-- 3. SEED PRICING TIERS
-- ============================================================================
INSERT INTO pricing_tiers (product_id, min_qty, max_qty, price) VALUES
(1, 50, 99, 120.00),
(1, 100, 499, 108.00),
(1, 500, NULL, 95.00),
(2, 100, 499, 185.00),
(2, 500, NULL, 155.00),
(3, 50, 199, 135.00),
(3, 200, NULL, 115.00),
(5, 100, 499, 280.00),
(5, 500, NULL, 260.00);

-- ============================================================================
-- 4. SEED ADMINS
-- ============================================================================
INSERT INTO admins (id, username, password, email, role) VALUES
(1, 'admin', '$2y$12$Q/0Y7VPGzKXvlWDfN4qaGOstFR98pJGKDhGVlZalbNmg6vuvRQdre', 'admin@kesara.lk', 'admin'),
(2, 'finance', '$2y$12$Y7ol8pO1GXpTUkhLd9b9HuN6zpIechPDKUZM1gLB.kYicvllMoVY.', 'finance@kesara.lk', 'finance_manager'), -- password is 'finance123'
(3, 'supplier', '$2y$12$UxusbvvJ1NrrLyc9e/03r.uLUNhg2qNI3ADtCaWIvSfVzhCOVqyuS', 'supplier@kesara.lk', 'supplier_manager'), -- password is 'supplier123'
(4, 'delivery', '$2y$12$cXnc6mGHKU4IkFMYmAH50.Pvk6XsGOcR2YqKxE6blY.4BB9vPYMBu', 'delivery@kesara.lk', 'delivery_manager'); -- password is 'delivery123'

-- ============================================================================
-- 5. SEED USERS (Wholesale Buyers)
-- ============================================================================
INSERT INTO users (id, first_name, last_name, email, phone, password, business_name, br_number, business_type, address, status) VALUES
(1, 'Kamal', 'Perera', 'kamal@abc.lk', '+94 77 123 4567', '$2y$10$Uv0V3xJ1E6yJ6bWq9oO8eFvP2T6vF8lV1n8F2E4sHjO7I6iO5WmW', 'ABC Garments (Pvt) Ltd', 'PV 12345', 'Retailer', 'No. 12, Main Street, Colombo 03', 'approved'),
(2, 'John', 'Doe', 'john@seylan.lk', '+94 77 987 6543', '$2y$10$Uv0V3xJ1E6yJ6bWq9oO8eFvP2T6vF8lV1n8F2E4sHjO7I6iO5WmW', 'Seylan Stores', 'PV 67890', 'Distributor', 'No. 45, Galle Road, Colombo 04', 'approved'),
(3, 'Nimali', 'Fonseka', 'nimali@fashion.lk', '+94 77 555 4444', '$2y$10$Uv0V3xJ1E6yJ6bWq9oO8eFvP2T6vF8lV1n8F2E4sHjO7I6iO5WmW', 'Fashion Hub', 'PV 11223', 'Supermarket', 'No. 102, Kandy Road, Kurunegala', 'approved'),
(4, 'Aruni', 'Jayasinghe', 'aruni@arunishop.lk', '+94 71 222 3333', '$2y$10$Uv0V3xJ1E6yJ6bWq9oO8eFvP2T6vF8lV1n8F2E4sHjO7I6iO5WmW', 'Aruni Boutique', 'BR 44332', 'Retailer', 'No. 88, Beach Road, Galle', 'pending');

-- ============================================================================
-- 6. SEED SUPPLIERS
-- ============================================================================
INSERT INTO suppliers (id, name, email, contact_person, phone, address, payment_terms, category, status, hold_reason, hold_since) VALUES
(1, 'Sri Lanka Cotton Mills', 'slcm@cottonmills.lk', 'Mr. Roshan Silva', '+94 11 456 7890', 'Colombo 10, WP', 'Net 30', 'Fabric', 'preferred', NULL, NULL),
(2, 'Kandy Textiles', 'info@kandytex.lk', 'Ms. Priya Weerakoon', '+94 81 234 5678', 'Kandy, CP', 'Net 45', 'Fabric', 'active', NULL, NULL),
(3, 'Premium Elastic Co.', 'sales@premiumelastic.lk', 'Mr. Nishantha Kumar', '+94 77 345 6789', 'Gampaha, WP', 'Net 15', 'Elastic / Trims', 'preferred', NULL, NULL),
(4, 'Pacific Packaging', 'orders@pacpkg.lk', 'Mr. Saman Dias', '+94 11 789 0123', 'Colombo 15, WP', 'Net 30', 'Packaging', 'on_hold', 'Quality review pending', '2026-03-14'),
(5, 'Galle Fabric House', 'gfh@gallefabric.lk', 'Mr. Channa Perera', '+94 91 234 5678', 'Galle, SP', 'COD', 'Fabric', 'inactive', 'Inactive for over 6 months', '2025-11-20');

-- ============================================================================
-- 7. SEED SUPPLIER ITEMS & PRODUCTS
-- ============================================================================
INSERT INTO supplier_items (supplier_id, item_name) VALUES
(1, 'Combed cotton fabric'),
(1, 'Modal fabric'),
(1, 'Spandex blend'),
(2, 'Cotton fabric'),
(2, 'Polyester blend'),
(3, 'Branded elastic'),
(3, 'Plain elastic'),
(3, 'Labels'),
(4, 'Polybags'),
(4, 'Cartons'),
(5, 'Cotton fabric');

INSERT INTO supplier_products (supplier_id, product_id, unit_cost, lead_days, is_primary) VALUES
(1, 1, 65.00, 7, TRUE),
(1, 2, 110.00, 7, TRUE),
(2, 1, 68.00, 10, FALSE),
(3, 3, 80.00, 5, TRUE);

-- ============================================================================
-- 8. SEED INVENTORY
-- ============================================================================
INSERT INTO inventory (id, product_id, size, colour, quantity, restock_min) VALUES
(1, 1, 'M', 'White', 500, 200),
(2, 1, 'L', 'White', 420, 200),
(3, 2, 'L', 'Black', 180, 200),
(4, 3, 'S', 'Pink', 80, 100),
(5, 5, 'M', 'Grey', 350, 200);

-- ============================================================================
-- 9. SEED ORDERS & ITEMS & LOGS
-- ============================================================================
-- Orders
INSERT INTO orders (id, user_id, status, total_amount, created_at) VALUES
(1, 1, 'pending', 71933.00, '2026-05-12 09:14:00'),
(2, 2, 'processing', 48600.00, '2026-05-11 14:30:00'),
(3, 3, 'shipped', 124000.00, '2026-05-10 10:00:00');

-- Order Items
INSERT INTO order_items (order_id, product_id, quantity, unit_price) VALUES
(1, 1, 120, 108.00), -- Classic Brief
(1, 3, 100, 115.00), -- Ladies Hipster
(1, 2, 200, 155.00), -- Stretch Boxer
(2, 1, 200, 108.00),
(2, 2, 100, 185.00),
(3, 5, 500, 260.00);

-- Order Status Log
INSERT INTO order_status_log (order_id, status, note, changed_by, changed_at) VALUES
(1, 'pending', 'Order Placed. Bank transfer pending.', 1, '2026-05-12 09:14:00'),
(2, 'pending', 'Order Placed.', 2, '2026-05-11 14:30:00'),
(2, 'processing', 'Payment Confirmed. Packing in progress.', 1, '2026-05-12 08:00:00'),
(3, 'processing', 'Processing completed.', 1, '2026-05-11 10:00:00'),
(3, 'shipped', 'Shipped. Tracking: SL-984421', 1, '2026-05-11 11:30:00');

-- ============================================================================
-- 10. SEED PURCHASE ORDERS
-- ============================================================================
INSERT INTO purchase_orders (id, supplier_id, status, ordered_at, expected_at, received_at, total) VALUES
(1, 1, 'received', '2026-04-20 09:00:00', '2026-04-27', '2026-04-27', 15000.00),
(2, 1, 'sent', '2026-05-03 10:00:00', '2026-05-10', NULL, 24000.00),
(3, 3, 'overdue', '2026-05-15 11:00:00', '2026-05-20', NULL, 8000.00);

-- Purchase Order Items
INSERT INTO purchase_order_items (id, po_id, product_id, item_name, qty_ordered, qty_received, unit_cost) VALUES
(1, 1, 1, 'Classic Cotton Brief', 100, 100, 65.00),
(2, 2, 1, 'Classic Cotton Brief', 200, 0, 65.00),
(3, 2, 2, 'Stretch Boxer', 100, 0, 110.00),
(4, 3, 3, 'Ladies Hipster', 100, 0, 80.00);

-- ============================================================================
-- 11. SEED DELIVERY PERSONNEL
-- ============================================================================
INSERT INTO delivery_personnel (id, name, phone, nic, licence_class, licence_expiry, vehicle_type, vehicle_number, status, joined_date) VALUES
(1, 'Rohan Jayaratne', '+94 77 111 2222', '198422301980', 'Heavy/Light', '2030-10-15', 'van', 'WP DA-4859', 'available', '2020-01-15'),
(2, 'Sunil Perera', '+94 77 333 4444', '199011204859', 'Light Vehicle', '2029-05-20', 'motorbike', 'WP BZ-9842', 'on_run', '2022-03-10'),
(3, 'Nihal Silva', '+94 71 888 9999', '197945009842', 'Heavy Vehicle', '2028-11-12', 'lorry', 'WP LK-8521', 'day_off', '2015-06-01');

-- Zones
INSERT INTO personnel_zones (personnel_id, zone) VALUES
(1, 'Colombo 1-15'),
(1, 'Gampaha'),
(2, 'Colombo 1-15'),
(2, 'Dehiwala'),
(3, 'Gampaha'),
(3, 'Kandy');

-- ============================================================================
-- 12. SEED DELIVERIES
-- ============================================================================
-- Delivery Assignments
INSERT INTO delivery_assignments (id, order_id, personnel_id, assigned_at, status, notes) VALUES
(1, 3, 1, '2026-05-11 11:30:00', 'completed', 'Standard delivery to Kurunegala Hub');

-- Runs
INSERT INTO delivery_runs (id, assignment_id, departed_at, delivered_at, recipient_name, outcome) VALUES
(1, 1, '2026-05-11 12:00:00', '2026-05-11 15:30:00', 'Nimali Fonseka', 'completed');
