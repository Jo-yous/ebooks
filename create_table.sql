-- Run this in phpMyAdmin → globalmedia_ebooks → SQL tab
-- Creates the table that stores all daily eBook submissions

CREATE TABLE IF NOT EXISTS `ebook_submissions` (
  `id`              INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `submitted_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `submission_date` DATE         NOT NULL,
  `writer_name`     VARCHAR(150) NOT NULL,
  `phone`           VARCHAR(50)  NOT NULL,
  `total_books`     TINYINT      NOT NULL DEFAULT 0,
  `book_01`         VARCHAR(255) DEFAULT NULL,
  `book_02`         VARCHAR(255) DEFAULT NULL,
  `book_03`         VARCHAR(255) DEFAULT NULL,
  `book_04`         VARCHAR(255) DEFAULT NULL,
  `book_05`         VARCHAR(255) DEFAULT NULL,
  `book_06`         VARCHAR(255) DEFAULT NULL,
  `book_07`         VARCHAR(255) DEFAULT NULL,
  `book_08`         VARCHAR(255) DEFAULT NULL,
  `book_09`         VARCHAR(255) DEFAULT NULL,
  `book_10`         VARCHAR(255) DEFAULT NULL,
  `files_uploaded`  TINYINT      NOT NULL DEFAULT 0,
  `file_names`      TEXT         DEFAULT NULL,
  `folder_path`     VARCHAR(500) DEFAULT NULL,
  `notes`           TEXT         DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
