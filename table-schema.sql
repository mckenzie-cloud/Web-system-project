CREATE DATABASE bella_vista CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE bella_vista;

-- 1. Physical tables: live status set by staff
CREATE TABLE tables (
  table_id VARCHAR(10) PRIMARY KEY,                 -- 'S1', 'F2', 'VIP1'
  status ENUM('available','occupied','cleanup') DEFAULT 'available'
);

-- 2. Staff accounts (admin / kitchen login)
CREATE TABLE staff (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,              -- from password_hash()
  role ENUM('manager','kitchen') NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- 3. Reservations: guest booking + reservation fee payment
CREATE TABLE reservations (
  id INT AUTO_INCREMENT PRIMARY KEY,

  name VARCHAR(100) NOT NULL,
  phone VARCHAR(20) NOT NULL,
  email VARCHAR(100) NOT NULL,

  table_id VARCHAR(10) NOT NULL,
  guests TINYINT UNSIGNED NOT NULL,
  reservation_date DATE NOT NULL,
  reservation_start_time TINYINT UNSIGNED NOT NULL,   -- 10..20
  reservation_end_time TINYINT UNSIGNED NOT NULL,     -- start + 2
  reserved_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  reference_code CHAR(8) NOT NULL UNIQUE,
  cancelled_at DATETIME NULL,                         -- NULL = active

  payment_method ENUM('online','onsite') NOT NULL,
  payment_status ENUM('unpaid','downpayment','paid','refunded','forfeited') DEFAULT 'unpaid',
  total_amount DECIMAL(10,2) DEFAULT 0,                -- credited toward the food bill
  amount_paid DECIMAL(10,2) DEFAULT 0,                 -- credited toward the food bill

  FOREIGN KEY (table_id) REFERENCES tables(table_id),
  INDEX (table_id, reservation_date),
  INDEX (reservation_date)
);

-- 4. Menu
CREATE TABLE menu_categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(50) NOT NULL
);

CREATE TABLE menu_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  category_id INT NOT NULL,
  name VARCHAR(100) NOT NULL,
  description VARCHAR(255),
  price DECIMAL(10,2) NOT NULL,
  is_available TINYINT(1) DEFAULT 1,
  FOREIGN KEY (category_id) REFERENCES menu_categories(id)
);

-- 5. Orders: pre-orders, walk-in orders and table orders
CREATE TABLE orders (
  id INT AUTO_INCREMENT PRIMARY KEY,
  table_id VARCHAR(10) NOT NULL,
  reservation_id INT NULL,                           -- NULL for walk-ins
  status ENUM('pending','preparing','served') DEFAULT 'pending',
  total DECIMAL(10,2) NOT NULL,
  payment_method ENUM('online','cash','card') NULL,
  paid_at DATETIME NULL,                             -- NULL = unpaid
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (table_id) REFERENCES tables(table_id),
  FOREIGN KEY (reservation_id) REFERENCES reservations(id),
  INDEX idx_status_created (status, created_at),
  INDEX idx_created (created_at)
);

CREATE TABLE order_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NOT NULL,
  menu_item_id INT NOT NULL,
  quantity INT NOT NULL,
  unit_price DECIMAL(10,2) NOT NULL,                 -- price when ordered
  FOREIGN KEY (order_id) REFERENCES orders(id),
  FOREIGN KEY (menu_item_id) REFERENCES menu_items(id)
);