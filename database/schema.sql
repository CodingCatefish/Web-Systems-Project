-- Bookstore schema from ER diagram (MySQL 8+ / InnoDB)
-- Create a database (adjust name to match your DB_NAME env / server config)
CREATE DATABASE IF NOT EXISTS bookstore
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE bookstore;

-- ---------------------------------------------------------------------------
-- Core entities
-- Users is the canonical auth/source-of-truth table.
-- Books and Reviews keep their current primary-key column names for compatibility.
-- ---------------------------------------------------------------------------

CREATE TABLE `Users` (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name          VARCHAR(80)     NOT NULL,
  email         VARCHAR(254)    NOT NULL,
  password_hash VARCHAR(255)    NOT NULL,
  role          VARCHAR(32)     NOT NULL DEFAULT 'customer',
  created_at    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_Users_email (email),
  KEY idx_Users_role (role)
) ENGINE=InnoDB;

CREATE TABLE `Books` (
  bookID            BIGINT        NOT NULL AUTO_INCREMENT,
  title             VARCHAR(512)  NOT NULL,
  price             DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  blurb             VARCHAR(2048) NULL,
  image             VARCHAR(1024) NULL,
  pdf_refrence_path VARCHAR(1024) NULL,
  vetted            TINYINT       NOT NULL DEFAULT 0,
  created_date      DATE          NOT NULL,
  PRIMARY KEY (bookID)
) ENGINE=InnoDB;

CREATE TABLE `Reviews` (
  reviewID     BIGINT        NOT NULL AUTO_INCREMENT,
  content      VARCHAR(2048) NOT NULL,
  rating       INT           NOT NULL,
  created_date DATE          NOT NULL,
  PRIMARY KEY (reviewID),
  CONSTRAINT chk_Reviews_rating CHECK (rating >= 1 AND rating <= 5)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------------
-- Associative / linking tables
-- ---------------------------------------------------------------------------

-- Purchases: Users <-> Books (many purchases per user/book over time)
CREATE TABLE `Transactions` (
  user_id          BIGINT UNSIGNED NOT NULL,
  bookID           BIGINT          NOT NULL,
  date_of_purchase DATE            NOT NULL,
  PRIMARY KEY (user_id, bookID, date_of_purchase),
  CONSTRAINT fk_Transactions_user_id
    FOREIGN KEY (user_id) REFERENCES `Users` (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT fk_Transactions_bookID
    FOREIGN KEY (bookID) REFERENCES `Books` (bookID)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB;

-- Books <-> author (Users row with author role)
CREATE TABLE `AuthorLists` (
  bookID    BIGINT          NOT NULL,
  author_id BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (bookID, author_id),
  CONSTRAINT fk_AuthorLists_bookID
    FOREIGN KEY (bookID) REFERENCES `Books` (bookID)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT fk_AuthorLists_author_id
    FOREIGN KEY (author_id) REFERENCES `Users` (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB;

-- Review author (Users) <-> Reviews
CREATE TABLE `UserReviews` (
  author_id BIGINT UNSIGNED NOT NULL,
  reviewID  BIGINT          NOT NULL,
  PRIMARY KEY (author_id, reviewID),
  CONSTRAINT fk_UserReviews_author_id
    FOREIGN KEY (author_id) REFERENCES `Users` (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT fk_UserReviews_reviewID
    FOREIGN KEY (reviewID) REFERENCES `Reviews` (reviewID)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB;

-- Reviews <-> Books
CREATE TABLE `ReviewBooks` (
  bookID   BIGINT NOT NULL,
  reviewID BIGINT NOT NULL,
  PRIMARY KEY (bookID, reviewID),
  CONSTRAINT fk_ReviewBooks_bookID
    FOREIGN KEY (bookID) REFERENCES `Books` (bookID)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT fk_ReviewBooks_reviewID
    FOREIGN KEY (reviewID) REFERENCES `Reviews` (reviewID)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------------
-- Authentication / account security tables
-- ---------------------------------------------------------------------------

CREATE TABLE `LoginAttempts` (
  email            VARCHAR(254) NOT NULL,
  ip_address       VARCHAR(45)  NOT NULL,
  attempt_count    INT UNSIGNED NOT NULL DEFAULT 0,
  first_attempt_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_attempt_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (email, ip_address),
  KEY idx_LoginAttempts_last_attempt_at (last_attempt_at)
) ENGINE=InnoDB;

CREATE TABLE `PasswordResets` (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id      BIGINT UNSIGNED NOT NULL,
  token_hash   CHAR(64)        NOT NULL,
  expires_at   TIMESTAMP       NOT NULL,
  requested_ip VARCHAR(45)     NOT NULL,
  user_agent   VARCHAR(255)    NOT NULL DEFAULT '',
  used_at      TIMESTAMP       NULL DEFAULT NULL,
  created_at   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_PasswordResets_token_hash (token_hash),
  KEY idx_PasswordResets_user_id (user_id),
  KEY idx_PasswordResets_expires_at (expires_at),
  CONSTRAINT fk_PasswordResets_user_id
    FOREIGN KEY (user_id) REFERENCES `Users` (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB;
