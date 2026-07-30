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
(3, 'Ladies Hipster', 'KL-003', 4, 'Soft touch hipster briefs for maximum comfort.', 50, 115.00, 'In Stock'),
(4, 'Kids Trunk Set', 'KC-012', 5, 'Comfy cotton trunks for children in fun colors.', 60, 98.00, 'In Stock'),
(5, 'Modal Trunk', 'KB-015', 3, 'Luxury modal fabric trunks with dynamic waistband.', 100, 260.00, 'In Stock'),
(6, 'Sports Brief', 'KB-022', 1, 'Breathable sports mesh brief with moisture-wicking technology.', 50, 175.00, 'In Stock'),
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
(1, 7, 'Default', 'Default', 600, 200),
(2, 10, 'XL', 'Navy', 380, 200),
(3, 1, 'M', 'Standard', 1320, 200),
(4, 2, 'M', 'Standard', 1440, 200),
(5, 3, 'M', 'Standard', 1560, 200),
(6, 4, 'M', 'Standard', 1680, 200),
(7, 5, 'M', 'Standard', 1200, 200),
(8, 6, 'M', 'Standard', 1320, 200),
(9, 8, 'M', 'Standard', 1560, 200),
(10, 9, 'M', 'Standard', 1680, 200),
(11, 10, 'XS', 'White', 300, 50),
(12, 10, 'S', 'White', 780, 50),
(13, 10, 'M', 'White', 1440, 50),
(14, 10, 'L', 'White', 1140, 50),
(15, 10, 'XL', 'White', 500, 50),
(16, 10, 'XXL', 'White', 220, 50),
(17, 10, 'XS', 'Black', 250, 50),
(18, 10, 'S', 'Black', 650, 50),
(19, 10, 'M', 'Black', 1200, 50),
(20, 10, 'L', 'Black', 950, 50),
(21, 10, 'XL', 'Black', 420, 50),
(22, 10, 'XXL', 'Black', 180, 50),
(23, 10, 'XS', 'Grey', 210, 50),
(24, 10, 'S', 'Grey', 550, 50),
(25, 10, 'M', 'Grey', 1020, 50),
(26, 10, 'L', 'Grey', 810, 50),
(27, 10, 'XL', 'Grey', 360, 50),
(28, 10, 'XXL', 'Grey', 150, 50),
(29, 10, 'XS', 'Blue', 180, 50),
(30, 10, 'S', 'Blue', 460, 50),
(31, 10, 'M', 'Blue', 840, 50),
(32, 10, 'L', 'Blue', 670, 50),
(33, 10, 'XL', 'Blue', 290, 50),
(34, 10, 'XXL', 'Blue', 130, 50),
(35, 10, 'XS', 'Red', 130, 50),
(36, 10, 'S', 'Red', 330, 50),
(37, 10, 'M', 'Red', 600, 50),
(38, 10, 'L', 'Red', 480, 50),
(39, 10, 'XL', 'Red', 210, 50),
(40, 10, 'XXL', 'Red', 90, 50),
(41, 10, 'XS', 'Pink', 80, 50),
(42, 10, 'S', 'Pink', 200, 50),
(43, 10, 'M', 'Pink', 360, 50),
(44, 10, 'L', 'Pink', 290, 50),
(45, 10, 'XL', 'Pink', 130, 50),
(46, 10, 'XXL', 'Pink', 50, 50),
(47, 10, 'XS', 'Navy', 230, 50),
(48, 10, 'S', 'Navy', 590, 50),
(49, 10, 'M', 'Navy', 1080, 50),
(50, 10, 'L', 'Navy', 860, 50),
(51, 10, 'XXL', 'Navy', 160, 50),
(52, 9, 'XS', 'White', 420, 50),
(53, 9, 'S', 'White', 1090, 50),
(54, 9, 'M', 'White', 2020, 50),
(55, 9, 'L', 'White', 1600, 50),
(56, 9, 'XL', 'White', 710, 50),
(57, 9, 'XXL', 'White', 300, 50),
(58, 9, 'XS', 'Black', 350, 50),
(59, 9, 'S', 'Black', 910, 50),
(60, 9, 'M', 'Black', 1680, 50),
(61, 9, 'L', 'Black', 1330, 50),
(62, 9, 'XL', 'Black', 590, 50),
(63, 9, 'XXL', 'Black', 250, 50),
(64, 9, 'XS', 'Grey', 300, 50),
(65, 9, 'S', 'Grey', 770, 50),
(66, 9, 'M', 'Grey', 1430, 50),
(67, 9, 'L', 'Grey', 1130, 50),
(68, 9, 'XL', 'Grey', 500, 50),
(69, 9, 'XXL', 'Grey', 210, 50),
(70, 9, 'XS', 'Blue', 250, 50),
(71, 9, 'S', 'Blue', 640, 50),
(72, 9, 'M', 'Blue', 1180, 50),
(73, 9, 'L', 'Blue', 930, 50),
(74, 9, 'XL', 'Blue', 410, 50),
(75, 9, 'XXL', 'Blue', 180, 50),
(76, 9, 'XS', 'Red', 180, 50),
(77, 9, 'S', 'Red', 460, 50),
(78, 9, 'M', 'Red', 840, 50),
(79, 9, 'L', 'Red', 670, 50),
(80, 9, 'XL', 'Red', 290, 50),
(81, 9, 'XXL', 'Red', 130, 50),
(82, 9, 'XS', 'Pink', 110, 50),
(83, 9, 'S', 'Pink', 270, 50),
(84, 9, 'M', 'Pink', 500, 50),
(85, 9, 'L', 'Pink', 400, 50),
(86, 9, 'XL', 'Pink', 180, 50),
(87, 9, 'XXL', 'Pink', 80, 50),
(88, 9, 'XS', 'Navy', 320, 50),
(89, 9, 'S', 'Navy', 820, 50),
(90, 9, 'M', 'Navy', 1510, 50),
(91, 9, 'L', 'Navy', 1200, 50),
(92, 9, 'XL', 'Navy', 530, 50),
(93, 9, 'XXL', 'Navy', 230, 50),
(94, 8, 'XS', 'White', 390, 50),
(95, 8, 'S', 'White', 1010, 50),
(96, 8, 'M', 'White', 1870, 50),
(97, 8, 'L', 'White', 1480, 50),
(98, 8, 'XL', 'White', 660, 50),
(99, 8, 'XXL', 'White', 280, 50),
(100, 8, 'XS', 'Black', 330, 50),
(101, 8, 'S', 'Black', 850, 50),
(102, 8, 'M', 'Black', 1560, 50),
(103, 8, 'L', 'Black', 1240, 50),
(104, 8, 'XL', 'Black', 550, 50),
(105, 8, 'XXL', 'Black', 230, 50),
(106, 8, 'XS', 'Grey', 280, 50),
(107, 8, 'S', 'Grey', 720, 50),
(108, 8, 'M', 'Grey', 1330, 50),
(109, 8, 'L', 'Grey', 1050, 50),
(110, 8, 'XL', 'Grey', 460, 50),
(111, 8, 'XXL', 'Grey', 200, 50),
(112, 8, 'XS', 'Blue', 230, 50),
(113, 8, 'S', 'Blue', 590, 50),
(114, 8, 'M', 'Blue', 1090, 50),
(115, 8, 'L', 'Blue', 870, 50),
(116, 8, 'XL', 'Blue', 380, 50),
(117, 8, 'XXL', 'Blue', 160, 50),
(118, 8, 'XS', 'Red', 160, 50),
(119, 8, 'S', 'Red', 420, 50),
(120, 8, 'M', 'Red', 780, 50),
(121, 8, 'L', 'Red', 620, 50),
(122, 8, 'XL', 'Red', 270, 50),
(123, 8, 'XXL', 'Red', 120, 50),
(124, 8, 'XS', 'Pink', 100, 50),
(125, 8, 'S', 'Pink', 250, 50),
(126, 8, 'M', 'Pink', 470, 50),
(127, 8, 'L', 'Pink', 370, 50),
(128, 8, 'XL', 'Pink', 160, 50),
(129, 8, 'XXL', 'Pink', 70, 50),
(130, 8, 'XS', 'Navy', 290, 50),
(131, 8, 'S', 'Navy', 760, 50),
(132, 8, 'M', 'Navy', 1400, 50),
(133, 8, 'L', 'Navy', 1110, 50),
(134, 8, 'XL', 'Navy', 490, 50),
(135, 8, 'XXL', 'Navy', 210, 50),
(136, 7, 'XS', 'White', 360, 50),
(137, 7, 'S', 'White', 940, 50),
(138, 7, 'M', 'White', 1730, 50),
(139, 7, 'L', 'White', 1370, 50),
(140, 7, 'XL', 'White', 610, 50),
(141, 7, 'XXL', 'White', 260, 50),
(142, 7, 'XS', 'Black', 300, 50),
(143, 7, 'S', 'Black', 780, 50),
(144, 7, 'M', 'Black', 1440, 50),
(145, 7, 'L', 'Black', 1140, 50),
(146, 7, 'XL', 'Black', 500, 50),
(147, 7, 'XXL', 'Black', 220, 50),
(148, 7, 'XS', 'Grey', 260, 50),
(149, 7, 'S', 'Grey', 660, 50),
(150, 7, 'M', 'Grey', 1220, 50),
(151, 7, 'L', 'Grey', 970, 50),
(152, 7, 'XL', 'Grey', 430, 50),
(153, 7, 'XXL', 'Grey', 180, 50),
(154, 7, 'XS', 'Blue', 210, 50),
(155, 7, 'S', 'Blue', 550, 50),
(156, 7, 'M', 'Blue', 1010, 50),
(157, 7, 'L', 'Blue', 800, 50),
(158, 7, 'XL', 'Blue', 350, 50),
(159, 7, 'XXL', 'Blue', 150, 50),
(160, 7, 'XS', 'Red', 150, 50),
(161, 7, 'S', 'Red', 390, 50),
(162, 7, 'M', 'Red', 720, 50),
(163, 7, 'L', 'Red', 570, 50),
(164, 7, 'XL', 'Red', 250, 50),
(165, 7, 'XXL', 'Red', 110, 50),
(166, 7, 'XS', 'Pink', 90, 50),
(167, 7, 'S', 'Pink', 230, 50),
(168, 7, 'M', 'Pink', 430, 50),
(169, 7, 'L', 'Pink', 340, 50),
(170, 7, 'XL', 'Pink', 150, 50),
(171, 7, 'XXL', 'Pink', 70, 50),
(172, 7, 'XS', 'Navy', 270, 50),
(173, 7, 'S', 'Navy', 700, 50),
(174, 7, 'M', 'Navy', 1300, 50),
(175, 7, 'L', 'Navy', 1030, 50),
(176, 7, 'XL', 'Navy', 450, 50),
(177, 7, 'XXL', 'Navy', 190, 50),
(178, 1, 'XS', 'White', 330, 50),
(179, 1, 'S', 'White', 860, 50),
(180, 1, 'M', 'White', 1580, 50),
(181, 1, 'L', 'White', 1250, 50),
(182, 1, 'XL', 'White', 550, 50),
(183, 1, 'XXL', 'White', 240, 50),
(184, 1, 'XS', 'Black', 280, 50),
(185, 1, 'S', 'Black', 720, 50),
(186, 1, 'M', 'Black', 1320, 50),
(187, 1, 'L', 'Black', 1050, 50),
(188, 1, 'XL', 'Black', 460, 50),
(189, 1, 'XXL', 'Black', 200, 50),
(190, 1, 'XS', 'Grey', 230, 50),
(191, 1, 'S', 'Grey', 610, 50),
(192, 1, 'M', 'Grey', 1120, 50),
(193, 1, 'L', 'Grey', 890, 50),
(194, 1, 'XL', 'Grey', 390, 50),
(195, 1, 'XXL', 'Grey', 170, 50),
(196, 1, 'XS', 'Blue', 190, 50),
(197, 1, 'S', 'Blue', 500, 50),
(198, 1, 'M', 'Blue', 920, 50),
(199, 1, 'L', 'Blue', 730, 50),
(200, 1, 'XL', 'Blue', 320, 50),
(201, 1, 'XXL', 'Blue', 140, 50),
(202, 1, 'XS', 'Red', 140, 50),
(203, 1, 'S', 'Red', 360, 50),
(204, 1, 'M', 'Red', 660, 50),
(205, 1, 'L', 'Red', 520, 50),
(206, 1, 'XL', 'Red', 230, 50),
(207, 1, 'XXL', 'Red', 0, 50),
(208, 1, 'XS', 'Pink', 0, 50),
(209, 1, 'S', 'Pink', 220, 50),
(210, 1, 'M', 'Pink', 400, 50),
(211, 1, 'L', 'Pink', 310, 50),
(212, 1, 'XL', 'Pink', 140, 50),
(213, 1, 'XXL', 'Pink', 0, 50),
(214, 1, 'XS', 'Navy', 250, 50),
(215, 1, 'S', 'Navy', 640, 50),
(216, 1, 'M', 'Navy', 1190, 50),
(217, 1, 'L', 'Navy', 940, 50),
(218, 1, 'XL', 'Navy', 420, 50),
(219, 1, 'XXL', 'Navy', 180, 50),
(220, 2, 'XS', 'White', 360, 50),
(221, 2, 'S', 'White', 940, 50),
(222, 2, 'M', 'White', 1730, 50),
(223, 2, 'L', 'White', 1370, 50),
(224, 2, 'XL', 'White', 610, 50),
(225, 2, 'XXL', 'White', 260, 50),
(226, 2, 'XS', 'Black', 300, 50),
(227, 2, 'S', 'Black', 780, 50),
(228, 2, 'M', 'Black', 1440, 50),
(229, 2, 'L', 'Black', 1140, 50),
(230, 2, 'XL', 'Black', 500, 50),
(231, 2, 'XXL', 'Black', 220, 50),
(232, 2, 'XS', 'Grey', 260, 50),
(233, 2, 'S', 'Grey', 660, 50),
(234, 2, 'M', 'Grey', 1220, 50),
(235, 2, 'L', 'Grey', 970, 50),
(236, 2, 'XL', 'Grey', 430, 50),
(237, 2, 'XXL', 'Grey', 180, 50),
(238, 2, 'XS', 'Blue', 210, 50),
(239, 2, 'S', 'Blue', 550, 50),
(240, 2, 'M', 'Blue', 1010, 50),
(241, 2, 'L', 'Blue', 800, 50),
(242, 2, 'XL', 'Blue', 350, 50),
(243, 2, 'XXL', 'Blue', 150, 50),
(244, 2, 'XS', 'Red', 150, 50),
(245, 2, 'S', 'Red', 390, 50),
(246, 2, 'M', 'Red', 720, 50),
(247, 2, 'L', 'Red', 570, 50),
(248, 2, 'XL', 'Red', 250, 50),
(249, 2, 'XXL', 'Red', 110, 50),
(250, 2, 'XS', 'Pink', 90, 50),
(251, 2, 'S', 'Pink', 230, 50),
(252, 2, 'M', 'Pink', 430, 50),
(253, 2, 'L', 'Pink', 340, 50),
(254, 2, 'XL', 'Pink', 150, 50),
(255, 2, 'XXL', 'Pink', 70, 50),
(256, 2, 'XS', 'Navy', 270, 50),
(257, 2, 'S', 'Navy', 700, 50),
(258, 2, 'M', 'Navy', 1300, 50),
(259, 2, 'L', 'Navy', 1030, 50),
(260, 2, 'XL', 'Navy', 450, 50),
(261, 2, 'XXL', 'Navy', 190, 50),
(262, 3, 'XS', 'White', 390, 50),
(263, 3, 'S', 'White', 1010, 50),
(264, 3, 'M', 'White', 1870, 50),
(265, 3, 'L', 'White', 1480, 50),
(266, 3, 'XL', 'White', 660, 50),
(267, 3, 'XXL', 'White', 280, 50),
(268, 3, 'XS', 'Black', 330, 50),
(269, 3, 'S', 'Black', 850, 50),
(270, 3, 'M', 'Black', 1560, 50),
(271, 3, 'L', 'Black', 1240, 50),
(272, 3, 'XL', 'Black', 550, 50),
(273, 3, 'XXL', 'Black', 230, 50),
(274, 3, 'XS', 'Grey', 280, 50),
(275, 3, 'S', 'Grey', 720, 50),
(276, 3, 'M', 'Grey', 1330, 50),
(277, 3, 'L', 'Grey', 1050, 50),
(278, 3, 'XL', 'Grey', 460, 50),
(279, 3, 'XXL', 'Grey', 200, 50),
(280, 3, 'XS', 'Blue', 230, 50),
(281, 3, 'S', 'Blue', 590, 50),
(282, 3, 'M', 'Blue', 1090, 50),
(283, 3, 'L', 'Blue', 870, 50),
(284, 3, 'XL', 'Blue', 380, 50),
(285, 3, 'XXL', 'Blue', 160, 50),
(286, 3, 'XS', 'Red', 160, 50),
(287, 3, 'S', 'Red', 420, 50),
(288, 3, 'M', 'Red', 780, 50),
(289, 3, 'L', 'Red', 620, 50),
(290, 3, 'XL', 'Red', 270, 50),
(291, 3, 'XXL', 'Red', 120, 50),
(292, 3, 'XS', 'Pink', 100, 50),
(293, 3, 'S', 'Pink', 250, 50),
(294, 3, 'M', 'Pink', 470, 50),
(295, 3, 'L', 'Pink', 370, 50),
(296, 3, 'XL', 'Pink', 160, 50),
(297, 3, 'XXL', 'Pink', 70, 50),
(298, 3, 'XS', 'Navy', 290, 50),
(299, 3, 'S', 'Navy', 760, 50),
(300, 3, 'M', 'Navy', 1400, 50),
(301, 3, 'L', 'Navy', 1110, 50),
(302, 3, 'XL', 'Navy', 490, 50),
(303, 3, 'XXL', 'Navy', 210, 50),
(304, 4, 'XS', 'White', 420, 50),
(305, 4, 'S', 'White', 1090, 50),
(306, 4, 'M', 'White', 2020, 50),
(307, 4, 'L', 'White', 1600, 50),
(308, 4, 'XL', 'White', 710, 50),
(309, 4, 'XXL', 'White', 300, 50),
(310, 4, 'XS', 'Black', 350, 50),
(311, 4, 'S', 'Black', 910, 50),
(312, 4, 'M', 'Black', 1680, 50),
(313, 4, 'L', 'Black', 1330, 50),
(314, 4, 'XL', 'Black', 590, 50),
(315, 4, 'XXL', 'Black', 250, 50),
(316, 4, 'XS', 'Grey', 300, 50),
(317, 4, 'S', 'Grey', 770, 50),
(318, 4, 'M', 'Grey', 1430, 50),
(319, 4, 'L', 'Grey', 1130, 50),
(320, 4, 'XL', 'Grey', 500, 50),
(321, 4, 'XXL', 'Grey', 210, 50),
(322, 4, 'XS', 'Blue', 250, 50),
     (323, 4, 'S', 'Blue', 640, 50),
(324, 4, 'M', 'Blue', 1180, 50),
(325, 4, 'L', 'Blue', 930, 50),
(326, 4, 'XL', 'Blue', 410, 50),
(327, 4, 'XXL', 'Blue', 180, 50),
(328, 4, 'XS', 'Red', 180, 50),
(329, 4, 'S', 'Red', 460, 50),
(330, 4, 'M', 'Red', 840, 50),
(331, 4, 'L', 'Red', 670, 50),
(332, 4, 'XL', 'Red', 290, 50),
(333, 4, 'XXL', 'Red', 130, 50),
(334, 4, 'XS', 'Pink', 110, 50),
(335, 4, 'S', 'Pink', 270, 50),
(336, 4, 'M', 'Pink', 500, 50),
(337, 4, 'L', 'Pink', 400, 50),
(338, 4, 'XL', 'Pink', 180, 50),
(339, 4, 'XXL', 'Pink', 80, 50),
(340, 4, 'XS', 'Navy', 320, 50),
(341, 4, 'S', 'Navy', 820, 50),
(342, 4, 'M', 'Navy', 1510, 50),
(343, 4, 'L', 'Navy', 1200, 50),
(344, 4, 'XL', 'Navy', 530, 50),
(345, 4, 'XXL', 'Navy', 230, 50),
(346, 5, 'XS', 'White', 300, 50),
(347, 5, 'S', 'White', 780, 50),
(348, 5, 'M', 'White', 1440, 50),
(349, 5, 'L', 'White', 1140, 50),
(350, 5, 'XL', 'White', 500, 50),
(351, 5, 'XXL', 'White', 220, 50),
(352, 5, 'XS', 'Black', 250, 50),
(353, 5, 'S', 'Black', 650, 50),
(354, 5, 'M', 'Black', 1200, 50),
(355, 5, 'L', 'Black', 950, 50),
(356, 5, 'XL', 'Black', 420, 50),
(357, 5, 'XXL', 'Black', 180, 50),
(358, 5, 'XS', 'Grey', 210, 50),
(359, 5, 'S', 'Grey', 550, 50),
(360, 5, 'M', 'Grey', 1020, 50),
(361, 5, 'L', 'Grey', 810, 50),
(362, 5, 'XL', 'Grey', 360, 50),
(363, 5, 'XXL', 'Grey', 150, 50),
(364, 5, 'XS', 'Blue', 180, 50),
(365, 5, 'S', 'Blue', 460, 50),
(366, 5, 'M', 'Blue', 840, 50),
(367, 5, 'L', 'Blue', 670, 50),
(368, 5, 'XL', 'Blue', 290, 50),
(369, 5, 'XXL', 'Blue', 130, 50),
(370, 5, 'XS', 'Red', 130, 50),
(371, 5, 'S', 'Red', 330, 50),
(372, 5, 'M', 'Red', 600, 50),
(373, 5, 'L', 'Red', 480, 50),
(374, 5, 'XL', 'Red', 210, 50),
(375, 5, 'XXL', 'Red', 90, 50),
(376, 5, 'XS', 'Pink', 80, 50),
(377, 5, 'S', 'Pink', 200, 50),
(378, 5, 'M', 'Pink', 360, 50),
(379, 5, 'L', 'Pink', 290, 50),
(380, 5, 'XL', 'Pink', 130, 50),
(381, 5, 'XXL', 'Pink', 50, 50),
(382, 5, 'XS', 'Navy', 230, 50),
(383, 5, 'S', 'Navy', 590, 50),
(384, 5, 'M', 'Navy', 1080, 50),
(385, 5, 'L', 'Navy', 860, 50),
(386, 5, 'XL', 'Navy', 380, 50),
(387, 5, 'XXL', 'Navy', 160, 50),
(388, 6, 'XS', 'White', 330, 50),
(389, 6, 'S', 'White', 860, 50),
(390, 6, 'M', 'White', 1580, 50),
(391, 6, 'L', 'White', 1250, 50),
(392, 6, 'XL', 'White', 550, 50),
(393, 6, 'XXL', 'White', 240, 50),
(394, 6, 'XS', 'Black', 280, 50),
(395, 6, 'S', 'Black', 720, 50),
(396, 6, 'M', 'Black', 1320, 50),
(397, 6, 'L', 'Black', 1050, 50),
(398, 6, 'XL', 'Black', 460, 50),
(399, 6, 'XXL', 'Black', 200, 50),
(400, 6, 'XS', 'Grey', 230, 50),
(401, 6, 'S', 'Grey', 610, 50),
(402, 6, 'M', 'Grey', 1120, 50),
(403, 6, 'L', 'Grey', 890, 50),
(404, 6, 'XL', 'Grey', 390, 50),
(405, 6, 'XXL', 'Grey', 170, 50),
(406, 6, 'XS', 'Blue', 190, 50),
(407, 6, 'S', 'Blue', 500, 50),
(408, 6, 'M', 'Blue', 920, 50),
(409, 6, 'L', 'Blue', 730, 50),
(410, 6, 'XL', 'Blue', 320, 50),
(411, 6, 'XXL', 'Blue', 140, 50),
(412, 6, 'XS', 'Red', 140, 50),
(413, 6, 'S', 'Red', 360, 50),
(414, 6, 'M', 'Red', 660, 50),
(415, 6, 'L', 'Red', 520, 50),
(416, 6, 'XL', 'Red', 230, 50),
(417, 6, 'XXL', 'Red', 100, 50),
(418, 6, 'XS', 'Pink', 80, 50),
(419, 6, 'S', 'Pink', 220, 50),
(420, 6, 'M', 'Pink', 400, 50),
(421, 6, 'L', 'Pink', 310, 50),
(422, 6, 'XL', 'Pink', 140, 50),
(423, 6, 'XXL', 'Pink', 60, 50),
(424, 6, 'XS', 'Navy', 250, 50),
(425, 6, 'S', 'Navy', 640, 50),
(426, 6, 'M', 'Navy', 1190, 50),
(427, 6, 'L', 'Navy', 940, 50),
(428, 6, 'XL', 'Navy', 420, 50),
(429, 6, 'XXL', 'Navy', 180, 50),
(430, 2, 'Default', 'Default', 600, 200),
(431, 1, 'Default', 'Default', 550, 200),
(432, 8, 'Default', 'Default', 650, 200),
(433, 3, 'Default', 'Default', 650, 200);

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
INSERT INTO delivery_personnel (id, name, phone, nic, licence_class, licence_expiry, vehicle_type, vehicle_number, assigned_area, status, joined_date) VALUES
(1, 'Rohan Jayaratne', '+94 77 111 2222', '198422301980', 'Heavy/Light', '2030-10-15', 'van', 'WP DA-4859', 'Colombo', 'available', '2020-01-15'),
(2, 'Sunil Perera', '+94 77 333 4444', '199011204859', 'Light Vehicle', '2029-05-20', 'motorbike', 'WP BZ-9842', 'Gampaha', 'available', '2022-03-10'),
(3, 'Nihal Silva', '+94 71 888 9999', '197945009842', 'Heavy Vehicle', '2028-11-12', 'lorry', 'WP LK-8521', 'Kandy', 'available', '2015-06-01');

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
