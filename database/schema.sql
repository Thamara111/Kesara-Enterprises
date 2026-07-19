-- Kesara Enterprises Database Schema
-- Generated: 2026-06-09

CREATE DATABASE IF NOT EXISTS kesara_db;
USE kesara_db;

-- ==========================================
-- 1. BASE TABLES (No Foreign Keys)
-- ==========================================

-- Users Table (Wholesale Buyers)
CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  first_name VARCHAR(50) NOT NULL,
  last_name VARCHAR(50) NOT NULL,
  email VARCHAR(100) NOT NULL UNIQUE,
  phone VARCHAR(20) NOT NULL,
  whatsapp_number VARCHAR(20) DEFAULT NULL,
  password VARCHAR(255) NOT NULL,
  business_name VARCHAR(100) NOT NULL,
  br_number VARCHAR(50) NOT NULL,
  business_type VARCHAR(50) NOT NULL,
  address TEXT NOT NULL,
  status ENUM('pending','approved','rejected','suspended') DEFAULT 'pending',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Categories Table (For dynamic catalog browsing)
CREATE TABLE categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(50) NOT NULL UNIQUE,
  slug VARCHAR(50) NOT NULL UNIQUE,
  icon VARCHAR(50) DEFAULT 'ti-shirt',
  description TEXT,
  image VARCHAR(255) DEFAULT NULL,
  deleted_at DATETIME DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Products Table
CREATE TABLE products (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  sku VARCHAR(50) NOT NULL UNIQUE,
  category_id INT,
  description TEXT,
  moq INT NOT NULL DEFAULT 50,
  base_price DECIMAL(10,2) NOT NULL,
  status VARCHAR(20) DEFAULT 'In Stock',
  images VARCHAR(255) DEFAULT NULL,
  deleted_at DATETIME DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Admins Table
CREATE TABLE admins (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  email VARCHAR(100) NOT NULL UNIQUE,
  role ENUM('admin', 'finance_manager', 'supplier_manager', 'delivery_manager') DEFAULT 'admin',
  failed_attempts INT DEFAULT 0,
  locked_until DATETIME NULL,
  deleted_at DATETIME DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Suppliers Table
CREATE TABLE suppliers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(100) NOT NULL,
  contact_person VARCHAR(100),
  phone VARCHAR(20),
  address VARCHAR(255),
  payment_terms VARCHAR(50),
  category VARCHAR(50),
  status ENUM('active','preferred','on_hold','inactive') DEFAULT 'active',
  hold_reason VARCHAR(255),
  hold_since DATE,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Delivery Personnel Table
CREATE TABLE delivery_personnel (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  phone VARCHAR(20),
  nic VARCHAR(20),
  licence_class VARCHAR(20),
  licence_expiry DATE,
  vehicle_type ENUM('motorbike','van','lorry'),
  vehicle_number VARCHAR(20),
  status ENUM('available','on_run','day_off','inactive') DEFAULT 'available',
  joined_date DATE,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ==========================================
-- 2. CHILD TABLES (Level 1 Dependencies)
-- ==========================================

-- Password Reset (Forgot Password Flow)
CREATE TABLE password_resets (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  token VARCHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Pricing Tiers Table (Dynamic Wholesale Pricing by Quantity)
CREATE TABLE pricing_tiers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  product_id INT NOT NULL,
  min_qty INT NOT NULL,
  max_qty INT,  -- NULL = no upper limit
  price DECIMAL(10,2) NOT NULL,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Orders Table
CREATE TABLE orders (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  status ENUM('pending','processing','shipped','delivered','cancelled') DEFAULT 'pending',
  total_amount DECIMAL(10,2) NOT NULL,
  deleted_at DATETIME DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Inventory (Per-Variant Stock)
CREATE TABLE inventory (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  product_id   INT NOT NULL,
  size         VARCHAR(10),
  colour       VARCHAR(30),
  quantity     INT NOT NULL DEFAULT 0,
  restock_min  INT NOT NULL DEFAULT 200,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Supplier Items (Free-Text Items Supplied)
CREATE TABLE supplier_items (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  supplier_id INT NOT NULL,
  item_name   VARCHAR(100) NOT NULL,
  FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Supplier Products (Many-to-Many with Cost & Lead Time)
CREATE TABLE supplier_products (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  supplier_id   INT NOT NULL,
  product_id    INT NOT NULL,
  unit_cost     DECIMAL(10,2),
  lead_days     INT,
  is_primary    BOOLEAN DEFAULT FALSE,
  FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Purchase Orders
CREATE TABLE purchase_orders (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  supplier_id   INT NOT NULL,
  status        ENUM('draft','sent','partial','received','overdue') DEFAULT 'draft',
  ordered_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
  expected_at   DATE,
  received_at   DATE,
  total         DECIMAL(10,2),
  FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Personnel Zones (Many-to-Many)
CREATE TABLE personnel_zones (
  personnel_id INT NOT NULL,
  zone         VARCHAR(50) NOT NULL,
  PRIMARY KEY (personnel_id, zone),
  FOREIGN KEY (personnel_id) REFERENCES delivery_personnel(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ==========================================
-- 3. CHILD TABLES (Level 2 Dependencies)
-- ==========================================

-- Order Status Log (Timeline)
CREATE TABLE order_status_log (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NOT NULL,
  status ENUM('pending','processing','shipped','delivered','cancelled'),
  note VARCHAR(255),
  changed_by INT,
  changed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Order Items (Line Items)
CREATE TABLE order_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NOT NULL,
  product_id INT NOT NULL,
  quantity INT NOT NULL,
  unit_price DECIMAL(10,2) NOT NULL,
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Inventory Log (Adjustment History)
CREATE TABLE inventory_log (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  inventory_id INT NOT NULL,
  adj_type     ENUM('add','remove','set'),
  qty_before   INT,
  qty_after    INT,
  note         VARCHAR(255),
  admin_id     INT,
  created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (inventory_id) REFERENCES inventory(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Purchase Order Items (Line Items)
CREATE TABLE purchase_order_items (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  po_id         INT NOT NULL,
  product_id    INT NOT NULL,
  item_name     VARCHAR(100), -- Snapshot/historical name reference (referenced in confirmation transaction)
  qty_ordered   INT,
  qty_received  INT DEFAULT 0,
  unit_cost     DECIMAL(10,2),
  FOREIGN KEY (po_id) REFERENCES purchase_orders(id) ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Goods Received Notes (GRN)
CREATE TABLE goods_received_notes (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  po_id        INT NOT NULL,
  received_by  VARCHAR(100),
  received_at  DATETIME,
  note         VARCHAR(255),
  FOREIGN KEY (po_id) REFERENCES purchase_orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Delivery Assignments
CREATE TABLE delivery_assignments (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  order_id     INT NOT NULL,
  personnel_id INT NOT NULL,
  assigned_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
  status       ENUM('pending','in_progress','completed','failed') DEFAULT 'pending',
  notes        VARCHAR(255),
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  FOREIGN KEY (personnel_id) REFERENCES delivery_personnel(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ==========================================
-- 4. CHILD TABLES (Level 3 Dependencies)
-- ==========================================

-- Delivery Runs (Individual Delivery Attempts)
CREATE TABLE delivery_runs (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  assignment_id INT NOT NULL,
  departed_at  DATETIME,
  delivered_at DATETIME,
  recipient_name VARCHAR(100),
  outcome      ENUM('completed','failed'),
  FOREIGN KEY (assignment_id) REFERENCES delivery_assignments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Delivery Issues (Failure Tracking)
CREATE TABLE delivery_issues (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  assignment_id INT NOT NULL,
  issue_type   VARCHAR(50),
  description  VARCHAR(255),
  reported_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
  resolved     BOOLEAN DEFAULT FALSE,
  FOREIGN KEY (assignment_id) REFERENCES delivery_assignments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================================
-- 5. OTHER TABLES
-- ==========================================

-- Inquiries Table (For contact form submissions)
CREATE TABLE IF NOT EXISTS inquiries (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  business_name VARCHAR(100),
  email VARCHAR(100) NOT NULL,
  phone VARCHAR(20),
  inquiry_type VARCHAR(50),
  message TEXT NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- WhatsApp Mock Messages
CREATE TABLE IF NOT EXISTS mock_whatsapp_messages (
  id INT AUTO_INCREMENT PRIMARY KEY,
  customer_id INT NULL,
  phone VARCHAR(50) NOT NULL,
  message TEXT NOT NULL,
  status VARCHAR(50) DEFAULT 'delivered',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
