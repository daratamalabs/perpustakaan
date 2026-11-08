-- Perpustakaan Digital Sekolah — skema MySQL / MariaDB (multi-sekolah)
-- Bisa diimpor manual lewat phpMyAdmin, atau otomatis oleh /install.

CREATE TABLE IF NOT EXISTS meta (
  k VARCHAR(60) NOT NULL PRIMARY KEY,
  v TEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS platform_admins (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(60) NOT NULL UNIQUE,
  name VARCHAR(120) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  last_login DATETIME NULL,
  created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tenants (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(60) NOT NULL UNIQUE,
  name VARCHAR(160) NOT NULL,
  short_name VARCHAR(80) NULL,
  city VARCHAR(80) NULL,
  contact_name VARCHAR(120) NULL,
  contact_phone VARCHAR(30) NULL,
  notes TEXT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settings (
  tenant_id INT UNSIGNED NOT NULL,
  k VARCHAR(60) NOT NULL,
  v MEDIUMTEXT NOT NULL,
  PRIMARY KEY (tenant_id, k),
  CONSTRAINT fk_settings_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NOT NULL,
  username VARCHAR(60) NOT NULL,
  name VARCHAR(120) NOT NULL,
  role ENUM('kepala','admin','piket') NOT NULL DEFAULT 'admin',
  password_hash VARCHAR(255) NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  last_login DATETIME NULL,
  created_at DATETIME NOT NULL,
  UNIQUE KEY uq_users_login (tenant_id, username),
  CONSTRAINT fk_users_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS members (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NOT NULL,
  type ENUM('siswa','guru','tendik') NOT NULL DEFAULT 'siswa',
  name VARCHAR(120) NOT NULL,
  nisn VARCHAR(20) NULL,
  nip VARCHAR(30) NULL,
  kelas VARCHAR(20) NULL,
  subject VARCHAR(80) NULL,
  gender CHAR(1) NULL,
  phone VARCHAR(20) NULL,
  email VARCHAR(120) NULL,
  photo VARCHAR(160) NULL,
  pin_hash VARCHAR(255) NULL,
  member_no VARCHAR(30) NULL,
  qr_token VARCHAR(64) NOT NULL,
  status ENUM('otp','pending','active','inactive','rejected','graduated') NOT NULL DEFAULT 'pending',
  phone_verified TINYINT(1) NOT NULL DEFAULT 0,
  otp_code VARCHAR(10) NULL,
  otp_expires DATETIME NULL,
  otp_attempts INT NOT NULL DEFAULT 0,
  otp_sent_at DATETIME NULL,
  valid_until DATE NULL,
  reject_reason VARCHAR(255) NULL,
  source VARCHAR(20) NOT NULL DEFAULT 'daftar',
  approved_at DATETIME NULL,
  approved_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uq_members_token (qr_token),
  UNIQUE KEY uq_members_nisn (tenant_id, nisn),
  UNIQUE KEY uq_members_no (tenant_id, member_no),
  KEY idx_members_status (tenant_id, status),
  KEY idx_members_kelas (tenant_id, kelas),
  CONSTRAINT fk_members_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS students_ref (
  tenant_id INT UNSIGNED NOT NULL,
  nisn VARCHAR(20) NOT NULL,
  name VARCHAR(120) NOT NULL,
  kelas VARCHAR(20) NULL,
  gender CHAR(1) NULL,
  imported_at DATETIME NOT NULL,
  PRIMARY KEY (tenant_id, nisn),
  CONSTRAINT fk_students_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS books (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NOT NULL,
  title VARCHAR(255) NOT NULL,
  author VARCHAR(255) NULL,
  publisher VARCHAR(160) NULL,
  year VARCHAR(10) NULL,
  isbn VARCHAR(20) NULL,
  language VARCHAR(40) NULL,
  category VARCHAR(60) NOT NULL DEFAULT 'Nonfiksi',
  ddc VARCHAR(30) NULL,
  call_number VARCHAR(60) NULL,
  shelf VARCHAR(40) NULL,
  collection_type ENUM('pinjam','baca','paket') NOT NULL DEFAULT 'pinjam',
  synopsis TEXT NULL,
  keywords VARCHAR(255) NULL,
  cover VARCHAR(160) NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  KEY idx_books_title (tenant_id, title(100)),
  KEY idx_books_isbn (tenant_id, isbn),
  KEY idx_books_cat (tenant_id, category),
  CONSTRAINT fk_books_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS copies (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NOT NULL,
  book_id INT UNSIGNED NOT NULL,
  code VARCHAR(40) NOT NULL,
  source VARCHAR(80) NULL,
  acquired_at DATE NULL,
  kondisi ENUM('baik','rusak_ringan','rusak_berat','hilang') NOT NULL DEFAULT 'baik',
  status ENUM('tersedia','dipinjam') NOT NULL DEFAULT 'tersedia',
  notes VARCHAR(255) NULL,
  created_at DATETIME NOT NULL,
  UNIQUE KEY uq_copies_code (tenant_id, code),
  KEY idx_copies_book (book_id),
  CONSTRAINT fk_copies_book FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE CASCADE,
  CONSTRAINT fk_copies_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS loans (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NOT NULL,
  copy_id INT UNSIGNED NOT NULL,
  member_id INT UNSIGNED NOT NULL,
  loaned_at DATETIME NOT NULL,
  due_date DATE NOT NULL,
  returned_at DATETIME NULL,
  extended TINYINT NOT NULL DEFAULT 0,
  fine_amount INT NOT NULL DEFAULT 0,
  fine_paid TINYINT(1) NOT NULL DEFAULT 0,
  staff_id INT UNSIGNED NULL,
  return_staff_id INT UNSIGNED NULL,
  reminded_at DATETIME NULL,
  KEY idx_loans_open (tenant_id, returned_at),
  KEY idx_loans_member (member_id),
  KEY idx_loans_copy (copy_id),
  CONSTRAINT fk_loans_copy FOREIGN KEY (copy_id) REFERENCES copies(id) ON DELETE CASCADE,
  CONSTRAINT fk_loans_member FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
  CONSTRAINT fk_loans_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS visits (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NOT NULL,
  member_id INT UNSIGNED NULL,
  name VARCHAR(120) NOT NULL,
  category ENUM('siswa','guru','tendik','tamu') NOT NULL,
  kelas VARCHAR(80) NULL,
  origin VARCHAR(120) NULL,
  phone VARCHAR(20) NULL,
  purpose VARCHAR(80) NULL,
  method ENUM('qr','manual','tamu','cari') NOT NULL,
  manual_reason VARCHAR(80) NULL,
  check_in DATETIME NOT NULL,
  check_out DATETIME NULL,
  staff_id INT UNSIGNED NULL,
  KEY idx_visits_in (tenant_id, check_in),
  KEY idx_visits_member (member_id),
  CONSTRAINT fk_visits_member FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE SET NULL,
  CONSTRAINT fk_visits_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS assets (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NOT NULL,
  code VARCHAR(40) NOT NULL,
  name VARCHAR(160) NOT NULL,
  category VARCHAR(60) NOT NULL DEFAULT 'Elektronik',
  location VARCHAR(80) NULL,
  spec VARCHAR(255) NULL,
  acquired_at DATE NULL,
  value BIGINT NULL,
  fund_source VARCHAR(80) NULL,
  kondisi ENUM('baik','rusak_ringan','rusak_berat','perbaikan','dihapus') NOT NULL DEFAULT 'baik',
  photo VARCHAR(160) NULL,
  notes TEXT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uq_assets_code (tenant_id, code),
  CONSTRAINT fk_assets_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS asset_logs (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NOT NULL,
  asset_id INT UNSIGNED NOT NULL,
  date DATE NOT NULL,
  text VARCHAR(255) NOT NULL,
  cost BIGINT NULL,
  staff_id INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  KEY idx_asset_logs (asset_id),
  CONSTRAINT fk_asset_logs_asset FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE CASCADE,
  CONSTRAINT fk_asset_logs_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
