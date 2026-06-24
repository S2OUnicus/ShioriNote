CREATE TABLE IF NOT EXISTS site_settings (
    setting_key VARCHAR(80) NOT NULL PRIMARY KEY,
    setting_value TEXT NOT NULL,
    updated_at_utc DATETIME(6) NOT NULL DEFAULT (UTC_TIMESTAMP(6))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS users (
    uid INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    login_id VARCHAR(64) NOT NULL UNIQUE,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_salt VARBINARY(32) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    nickname VARCHAR(80) NOT NULL DEFAULT '',
    avatar_path VARCHAR(255) NOT NULL DEFAULT '',
    bio TEXT NULL,
    link_url VARCHAR(500) NOT NULL DEFAULT '',
    timezone VARCHAR(64) NOT NULL DEFAULT 'Asia/Tokyo',
    role ENUM('developer','super_admin','admin','user','banned') NOT NULL DEFAULT 'user',
    status ENUM('active','suspended') NOT NULL DEFAULT 'active',
    last_login_at_utc DATETIME(6) NULL,
    created_at_utc DATETIME(6) NOT NULL DEFAULT (UTC_TIMESTAMP(6)),
    updated_at_utc DATETIME(6) NOT NULL DEFAULT (UTC_TIMESTAMP(6)),
    INDEX idx_users_login (login_id),
    INDEX idx_users_email (email),
    INDEX idx_users_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS books (
    book_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    author VARCHAR(255) NOT NULL DEFAULT '',
    publisher VARCHAR(255) NOT NULL DEFAULT '',
    cover_path VARCHAR(255) NOT NULL DEFAULT '',
    memo TEXT NULL,
    progress_unit ENUM('chapter','section','subsection') NOT NULL DEFAULT 'section',
    progress_time_bucket ENUM('daily','hourly') NOT NULL DEFAULT 'daily',
    created_by INT UNSIGNED NULL,
    created_at_utc DATETIME(6) NOT NULL DEFAULT (UTC_TIMESTAMP(6)),
    updated_at_utc DATETIME(6) NOT NULL DEFAULT (UTC_TIMESTAMP(6)),
    INDEX idx_books_title (title),
    INDEX idx_books_unit (progress_unit),
    CONSTRAINT fk_books_created_by FOREIGN KEY (created_by) REFERENCES users(uid) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS book_toc (
    toc_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    book_id INT UNSIGNED NOT NULL,
    parent_id INT UNSIGNED NULL,
    level TINYINT UNSIGNED NOT NULL COMMENT '1=chapter, 2=section, 3=subsection',
    sort_order INT NOT NULL DEFAULT 0,
    title VARCHAR(255) NOT NULL,
    created_at_utc DATETIME(6) NOT NULL DEFAULT (UTC_TIMESTAMP(6)),
    INDEX idx_toc_book_sort (book_id, sort_order),
    INDEX idx_toc_parent (parent_id),
    CONSTRAINT fk_toc_book FOREIGN KEY (book_id) REFERENCES books(book_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS user_book_progress (
    progress_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    uid INT UNSIGNED NOT NULL,
    book_id INT UNSIGNED NOT NULL,
    start_at_utc DATETIME(6) NULL,
    completed_at_utc DATETIME(6) NULL,
    created_at_utc DATETIME(6) NOT NULL DEFAULT (UTC_TIMESTAMP(6)),
    updated_at_utc DATETIME(6) NOT NULL DEFAULT (UTC_TIMESTAMP(6)),
    UNIQUE KEY uq_user_book (uid, book_id),
    CONSTRAINT fk_ubp_user FOREIGN KEY (uid) REFERENCES users(uid) ON DELETE CASCADE,
    CONSTRAINT fk_ubp_book FOREIGN KEY (book_id) REFERENCES books(book_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS user_toc_progress (
    uid INT UNSIGNED NOT NULL,
    book_id INT UNSIGNED NOT NULL,
    toc_id INT UNSIGNED NOT NULL,
    percent TINYINT UNSIGNED NOT NULL DEFAULT 0,
    is_checked TINYINT(1) NOT NULL DEFAULT 0,
    memo TEXT NULL,
    completed_at_utc DATETIME(6) NULL,
    updated_at_utc DATETIME(6) NOT NULL DEFAULT (UTC_TIMESTAMP(6)),
    PRIMARY KEY (uid, toc_id),
    INDEX idx_utp_book (uid, book_id),
    CONSTRAINT chk_utp_percent CHECK (percent BETWEEN 0 AND 100),
    CONSTRAINT fk_utp_user FOREIGN KEY (uid) REFERENCES users(uid) ON DELETE CASCADE,
    CONSTRAINT fk_utp_book FOREIGN KEY (book_id) REFERENCES books(book_id) ON DELETE CASCADE,
    CONSTRAINT fk_utp_toc FOREIGN KEY (toc_id) REFERENCES book_toc(toc_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS progress_logs (
    log_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    uid INT UNSIGNED NOT NULL,
    book_id INT UNSIGNED NOT NULL,
    bucket_type ENUM('daily','hourly') NOT NULL DEFAULT 'daily',
    bucket_at_utc DATETIME(6) NOT NULL,
    progress_delta DECIMAL(6,2) NOT NULL DEFAULT 0.00,
    total_percent_after DECIMAL(6,2) NOT NULL DEFAULT 0.00,
    created_at_utc DATETIME(6) NOT NULL DEFAULT (UTC_TIMESTAMP(6)),
    INDEX idx_logs_user_book_time (uid, book_id, bucket_type, bucket_at_utc),
    CONSTRAINT fk_logs_user FOREIGN KEY (uid) REFERENCES users(uid) ON DELETE CASCADE,
    CONSTRAINT fk_logs_book FOREIGN KEY (book_id) REFERENCES books(book_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS password_reset_tokens (
    token_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    uid INT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL UNIQUE,
    expires_at_utc DATETIME(6) NOT NULL,
    used_at_utc DATETIME(6) NULL,
    created_at_utc DATETIME(6) NOT NULL DEFAULT (UTC_TIMESTAMP(6)),
    INDEX idx_reset_user (uid),
    INDEX idx_reset_expire (expires_at_utc),
    CONSTRAINT fk_reset_user FOREIGN KEY (uid) REFERENCES users(uid) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
