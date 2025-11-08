-- ------------------------------------------------------------
-- Caffora bootstrap DB
-- Aman di-run berulang: drop & create
-- ------------------------------------------------------------
DROP DATABASE IF EXISTS caffora_db;
CREATE DATABASE caffora_db CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE caffora_db;

-- -----------------------
-- Users
-- -----------------------
CREATE TABLE users (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(120) NOT NULL,
  email         VARCHAR(160) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role          ENUM('admin','staff','customer') NOT NULL DEFAULT 'customer',
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- sample users (password hash kosong contoh; ganti hash-mu sendiri)
INSERT INTO users (name,email,password_hash,role) VALUES
('Admin','admin@caffora.local','$2y$10$replace_with_bcrypt_hash','admin'),
('Barista','staff@caffora.local','$2y$10$replace_with_bcrypt_hash','staff'),
('Zenik','zenik@caffora.local','$2y$10$replace_with_bcrypt_hash','customer');

-- -----------------------
-- Menu
-- -----------------------
CREATE TABLE menu (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  name       VARCHAR(120)  NOT NULL,
  category   ENUM('food','drink','pastry') NOT NULL DEFAULT 'food',
  price_int  INT NOT NULL DEFAULT 0,           -- harga dalam rupiah (integer)
  image_url  VARCHAR(300) NOT NULL,
  status     ENUM('Ready','Hidden','Out') NOT NULL DEFAULT 'Ready',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- seed contoh (sesuaikan path gambar kamu)
INSERT INTO menu (name,category,price_int,image_url) VALUES
('Soto','food',70000,'/uploads/menu/soto.jpg'),
('Es Teh','drink',30000,'/uploads/menu/esteh.jpg'),
('Steak After Breakup','food',10000,'/uploads/menu/steak.jpg');

-- -----------------------
-- Orders (sesuai screenshot + tambahan service_type/table_no)
-- -----------------------
CREATE TABLE orders (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  user_id         INT NULL,                                   -- nullable untuk guest
  invoice_no      VARCHAR(50) NOT NULL UNIQUE,
  customer_name   VARCHAR(150) NOT NULL,
  service_type    ENUM('dine_in','takeaway') NOT NULL DEFAULT 'dine_in',
  table_no        VARCHAR(10) NULL,                           -- boleh NULL untuk takeaway
  total           DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  order_status    ENUM('new','processing','ready','completed','cancelled') NOT NULL DEFAULT 'new',
  payment_status  ENUM('pending','paid','failed','refunded','overdue') NOT NULL DEFAULT 'pending',
  payment_method  ENUM('cash','bank_transfer','qris','ewallet') NULL,
  status          ENUM('pending','paid','overdue','done') NOT NULL DEFAULT 'pending', -- jika masih dipakai di admin
  created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_orders_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;

-- -----------------------
-- Order Items (detail pesanan)
-- -----------------------
CREATE TABLE order_items (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  order_id   INT NOT NULL,
  menu_id    INT NULL,                    -- boleh NULL jika item custom / sudah terhapus di katalog
  name       VARCHAR(150) NOT NULL,       -- snapshot nama saat order
  price      INT NOT NULL DEFAULT 0,      -- snapshot harga saat order (rupiah)
  qty        INT NOT NULL DEFAULT 1,
  subtotal   INT GENERATED ALWAYS AS ((price * qty)) STORED,
  CONSTRAINT fk_items_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  CONSTRAINT fk_items_menu  FOREIGN KEY (menu_id)  REFERENCES menu(id)
) ENGINE=InnoDB;

-- -----------------------
-- Invoices (opsional, kalau modul invoice dipakai)
-- -----------------------
CREATE TABLE invoices (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  order_id    INT NOT NULL,
  invoice_no  VARCHAR(50) NOT NULL UNIQUE,
  amount      DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  status      ENUM('unpaid','paid','void') NOT NULL DEFAULT 'unpaid',
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_invoice_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- index yang membantu pencarian admin
CREATE INDEX idx_orders_created   ON orders (created_at DESC);
CREATE INDEX idx_orders_status    ON orders (order_status, payment_status);
CREATE INDEX idx_items_order      ON order_items (order_id);