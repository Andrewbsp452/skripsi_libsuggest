USE db_libsuggest;

CREATE TABLE IF NOT EXISTS local_accounts (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username      VARCHAR(50)  NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    display_name  VARCHAR(150) NOT NULL,
    role          ENUM('pengusul','admin') NOT NULL DEFAULT 'pengusul',
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS proposal_messages (
    id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    proposal_id  INT UNSIGNED NOT NULL,
    sender_uid   VARCHAR(100) NOT NULL,
    sender_name  VARCHAR(255) NOT NULL,
    sender_role  ENUM('pengusul','admin') NOT NULL,
    message      TEXT NOT NULL,
    read_at      DATETIME DEFAULT NULL,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (proposal_id) REFERENCES proposals(id) ON DELETE CASCADE,
    INDEX idx_proposal_created (proposal_id, created_at),
    INDEX idx_unread (proposal_id, sender_role, read_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
