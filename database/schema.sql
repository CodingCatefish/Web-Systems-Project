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
  reviewID     BIGINT       NOT NULL AUTO_INCREMENT,
  content      VARCHAR(2048) NOT NULL,
  rating       INT          NOT NULL,
  created_date DATE         NOT NULL,
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
