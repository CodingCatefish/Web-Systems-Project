-- Bookstore schema from ER diagram (MySQL 8+ / InnoDB)
-- Create a database (adjust name to match your DB_NAME env / server config)
CREATE DATABASE IF NOT EXISTS bookstore
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE bookstore;

-- ---------------------------------------------------------------------------
-- Core entities
-- ---------------------------------------------------------------------------

CREATE TABLE `User` (
  userID   BIGINT       NOT NULL AUTO_INCREMENT,
  username VARCHAR(255) NOT NULL,
  password VARCHAR(255) NOT NULL,
  role     VARCHAR(64)  NOT NULL,
  PRIMARY KEY (userID),
  UNIQUE KEY uk_user_username (username)
) ENGINE=InnoDB;

CREATE TABLE Book (
  bookID             BIGINT        NOT NULL AUTO_INCREMENT,
  title              VARCHAR(512)  NOT NULL,
  price              BIGINT        NOT NULL DEFAULT 0,
  blurb              VARCHAR(2048) NULL,
  image              VARCHAR(1024) NULL,
  pdf_refrence_path  VARCHAR(1024) NULL,
  vetted             TINYINT(1)    NOT NULL DEFAULT 0,
  created_date       DATE          NOT NULL,
  PRIMARY KEY (bookID)
) ENGINE=InnoDB;

CREATE TABLE Review (
  reviewID      BIGINT        NOT NULL AUTO_INCREMENT,
  reviewer_name VARCHAR(80)   NOT NULL,
  book_title    VARCHAR(255)  NOT NULL,
  content       VARCHAR(2048) NOT NULL,
  rating        INT           NOT NULL,
  created_date  DATE          NOT NULL,
  PRIMARY KEY (reviewID),
  CONSTRAINT chk_review_rating CHECK (rating >= 1 AND rating <= 5)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------------
-- Associative / linking tables
-- ---------------------------------------------------------------------------

-- Purchases: User ↔ Book (many purchases per user/book over time)
CREATE TABLE `Transactions` (
  userID           BIGINT NOT NULL,
  bookID           BIGINT NOT NULL,
  date_of_purchase DATE   NOT NULL,
  PRIMARY KEY (userID, bookID, date_of_purchase),
  CONSTRAINT fk_transactions_user
    FOREIGN KEY (userID) REFERENCES `User` (userID)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT fk_transactions_book
    FOREIGN KEY (bookID) REFERENCES Book (bookID)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB;

-- Book ↔ author (User with author role)
CREATE TABLE AuthorList (
  bookID   BIGINT NOT NULL,
  authorID BIGINT NOT NULL,
  PRIMARY KEY (bookID, authorID),
  CONSTRAINT fk_authorlist_book
    FOREIGN KEY (bookID) REFERENCES Book (bookID)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT fk_authorlist_user
    FOREIGN KEY (authorID) REFERENCES `User` (userID)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB;

-- Review author (User) ↔ Review
CREATE TABLE User_Review (
  authorID BIGINT NOT NULL,
  reviewID BIGINT NOT NULL,
  PRIMARY KEY (authorID, reviewID),
  CONSTRAINT fk_user_review_user
    FOREIGN KEY (authorID) REFERENCES `User` (userID)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT fk_user_review_review
    FOREIGN KEY (reviewID) REFERENCES Review (reviewID)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB;

-- Review ↔ Book
CREATE TABLE Review_Book (
  bookID   BIGINT NOT NULL,
  reviewID BIGINT NOT NULL,
  PRIMARY KEY (bookID, reviewID),
  CONSTRAINT fk_review_book_book
    FOREIGN KEY (bookID) REFERENCES Book (bookID)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT fk_review_book_review
    FOREIGN KEY (reviewID) REFERENCES Review (reviewID)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------------
-- Application auth table used by the implemented PHP/MySQL login flow
-- ---------------------------------------------------------------------------

CREATE TABLE Users (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name          VARCHAR(80)     NOT NULL,
  email         VARCHAR(254)    NOT NULL,
  password_hash VARCHAR(255)    NOT NULL,
  role          VARCHAR(32)     NOT NULL DEFAULT 'customer',
  created_at    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_users_email (email),
  KEY idx_users_role (role)
) ENGINE=InnoDB;

CREATE TABLE LoginAttempts (
  email            VARCHAR(254) NOT NULL,
  ip_address       VARCHAR(45)  NOT NULL,
  attempt_count    INT UNSIGNED NOT NULL DEFAULT 0,
  first_attempt_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_attempt_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (email, ip_address),
  KEY idx_login_attempts_last_attempt_at (last_attempt_at)
) ENGINE=InnoDB;

CREATE TABLE PasswordResets (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id      BIGINT UNSIGNED NOT NULL,
  token_hash   CHAR(64)        NOT NULL,
  expires_at   TIMESTAMP       NOT NULL,
  requested_ip VARCHAR(45)     NOT NULL,
  user_agent   VARCHAR(255)    NOT NULL DEFAULT '',
  used_at      TIMESTAMP       NULL DEFAULT NULL,
  created_at   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_password_resets_token_hash (token_hash),
  KEY idx_password_resets_user_id (user_id),
  KEY idx_password_resets_expires_at (expires_at),
  CONSTRAINT fk_password_resets_user
    FOREIGN KEY (user_id) REFERENCES Users (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB;
