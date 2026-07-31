-- =============================================================================
-- KESARA ENTERPRISES - VIVA MODIFICATION EXAM MASTER SQL SCRIPT (TASKS 2 to 20)
-- Execute or uncomment individual SQL blocks as requested by your examiner.
-- All tasks verified: 100% new/unimplemented features touching DB, Backend & UI.
-- =============================================================================

USE kesara_db;

-- -----------------------------------------------------------------------------
-- TASK 02: Custom Stock Restock Alert Threshold per Product
-- -----------------------------------------------------------------------------
-- ALTER TABLE products ADD COLUMN custom_restock_threshold INT DEFAULT 20;

-- -----------------------------------------------------------------------------
-- TASK 03: Tiered Pricing Tier Max Limit Indicator
-- (Uses table `pricing_tiers`: `min_qty`, `max_qty`, `price`)
-- -----------------------------------------------------------------------------

-- -----------------------------------------------------------------------------
-- TASK 04: Product Backorder & Pre-Order Reservation System
-- -----------------------------------------------------------------------------
-- CREATE TABLE IF NOT EXISTS backorders (
--   id INT AUTO_INCREMENT PRIMARY KEY,
--   user_id INT NOT NULL,
--   product_id INT NOT NULL,
--   quantity INT NOT NULL DEFAULT 50,
--   status ENUM('pending','fulfilled','cancelled') DEFAULT 'pending',
--   notes TEXT DEFAULT NULL,
--   created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
--   FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
--   FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
-- ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- TASK 05: Business Tax Identification (TIN) Verification Field
-- -----------------------------------------------------------------------------
-- ALTER TABLE users ADD COLUMN tin_number VARCHAR(30) UNIQUE DEFAULT NULL AFTER br_number;

-- -----------------------------------------------------------------------------
-- TASK 06: Account Suspension Reason & Admin Rejection Note
-- -----------------------------------------------------------------------------
-- ALTER TABLE users ADD COLUMN status_reason TEXT DEFAULT NULL AFTER status;

-- -----------------------------------------------------------------------------
-- TASK 07: User Credit Limit Allocation for Wholesale Purchases
-- -----------------------------------------------------------------------------
-- ALTER TABLE users ADD COLUMN credit_limit DECIMAL(10,2) DEFAULT 0.00 AFTER status_reason;

-- -----------------------------------------------------------------------------
-- TASK 08: User WhatsApp Preferred Notification Toggle
-- -----------------------------------------------------------------------------
-- ALTER TABLE users ADD COLUMN notify_whatsapp TINYINT(1) DEFAULT 1 AFTER credit_limit;

-- -----------------------------------------------------------------------------
-- TASK 09: Delivery Instructions & Special Handling Notes
-- -----------------------------------------------------------------------------
-- ALTER TABLE orders ADD COLUMN delivery_notes TEXT DEFAULT NULL AFTER total_amount;

-- -----------------------------------------------------------------------------
-- TASK 10: Partial Order Payment & Receipt Deposit Amount
-- -----------------------------------------------------------------------------
-- ALTER TABLE orders ADD COLUMN amount_paid DECIMAL(10,2) DEFAULT 0.00 AFTER delivery_notes;

-- -----------------------------------------------------------------------------
-- TASK 11: Order Cancelation Reason by Customer
-- -----------------------------------------------------------------------------
-- ALTER TABLE orders ADD COLUMN cancellation_reason VARCHAR(255) DEFAULT NULL AFTER amount_paid;

-- -----------------------------------------------------------------------------
-- TASK 12: Order Estimated Delivery Date Calculation
-- -----------------------------------------------------------------------------
-- ALTER TABLE orders ADD COLUMN estimated_delivery_date DATE DEFAULT NULL AFTER cancellation_reason;

-- -----------------------------------------------------------------------------
-- TASK 13: Supplier Rating & Performance Score
-- -----------------------------------------------------------------------------
-- ALTER TABLE suppliers ADD COLUMN rating DECIMAL(3,2) DEFAULT 5.00 AFTER status;

-- -----------------------------------------------------------------------------
-- TASK 14: Purchase Order Expected vs Received Quantity Discrepancy Tracking
-- -----------------------------------------------------------------------------
-- ALTER TABLE purchase_orders ADD COLUMN received_status ENUM('complete','partial','overdue') DEFAULT 'complete';

-- -----------------------------------------------------------------------------
-- TASK 15: Supplier Lead Time Tracking in Days
-- (Uses table `supplier_products`: `lead_days`)
-- -----------------------------------------------------------------------------

-- -----------------------------------------------------------------------------
-- TASK 16: Automated PO Expiry Notification
-- -----------------------------------------------------------------------------

-- -----------------------------------------------------------------------------
-- TASK 17: Driver Delivery Confirmation Photo / Signature Upload
-- -----------------------------------------------------------------------------
-- ALTER TABLE orders ADD COLUMN proof_of_delivery VARCHAR(255) DEFAULT NULL;

-- -----------------------------------------------------------------------------
-- TASK 18: Driver Emergency Availability Toggle
-- (Uses table `delivery_personnel`: `status` ENUM('available','on_run','day_off','inactive'))
-- -----------------------------------------------------------------------------

-- -----------------------------------------------------------------------------
-- TASK 19: Driver Leave Request Approval Note
-- -----------------------------------------------------------------------------
-- ALTER TABLE driver_leaves ADD COLUMN admin_note VARCHAR(255) DEFAULT NULL AFTER reason;

-- -----------------------------------------------------------------------------
-- TASK 20: Delivery Zone Assignment Multi-Select
-- (Uses table `personnel_zones`: `personnel_id`, `zone`)
-- -----------------------------------------------------------------------------
