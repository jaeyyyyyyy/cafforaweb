-- Buat database
CREATE DATABASE IF NOT EXISTS caffora_db
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_general_ci;

USE caffora_db;

-- Tabel users (SESUIKAN dengan register.php & login.php)
DROP TABLE IF EXISTS users;
CREATE TABLE users (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name            VARCHAR(100)      NOT NULL,
  email           VARCHAR(150)      NOT NULL UNIQUE,
  password        VARCHAR(255)      NOT NULL,           -- hash bcrypt
  otp             VARCHAR(6)        DEFAULT NULL,       -- kode OTP aktif
  otp_expires_at  DATETIME          DEFAULT NULL,       -- masa berlaku OTP
  status          ENUM('pending','active') DEFAULT 'pending', -- pending sampai OTP benar
  created_at      TIMESTAMP         DEFAULT CURRENT_TIMESTAMP,
  updated_at      TIMESTAMP         DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_email (email),
  INDEX idx_status (status),
  INDEX idx_otp_exp (otp_expires_at)
) ENGINE=InnoDB;

-- (Opsional) tabel sessions kalau nanti perlu session token sendiri
-- Tidak dipakai oleh login.php saat ini, boleh dilewati
-- DROP TABLE IF EXISTS sessions;
-- CREATE TABLE sessions (
--   id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
--   user_id       INT UNSIGNED NOT NULL,
--   session_token VARCHAR(255) NOT NULL,
--   created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
--   expires_at    DATETIME,
--   FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
--   INDEX idx_token (session_token),
--   INDEX idx_expires (expires_at)
-- ) ENGINE=InnoDB;

-- (Opsional) seed admin (GANTI password hash sesuai kebutuhanmu)
-- Password contoh di bawah = 'admin123' (hash bcrypt). Ganti kalau perlu.
INSERT INTO users (name, email, password, status)
VALUES (
  'Admin Caffora',
  'admin@caffora.cafe',
  '$2y$10$7bV4oQx9cYc7g3oWQqgW4eV22xw8w8nq3mKJ6sS0I4M9ZkQ7b1GxO',
  'active'
);