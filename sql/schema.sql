-- Attendance webapp schema (MariaDB / MySQL)
CREATE DATABASE IF NOT EXISTS attendance CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE attendance;

CREATE TABLE IF NOT EXISTS admins (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    username      VARCHAR(64) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS employees (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    emp_no      VARCHAR(32) NOT NULL UNIQUE,   -- maps to device employeeNoString
    name        VARCHAR(128) NOT NULL,
    department  VARCHAR(128) DEFAULT NULL,
    card_no     VARCHAR(64) DEFAULT NULL,
    photo_path  VARCHAR(255) DEFAULT NULL,
    on_device   TINYINT(1) NOT NULL DEFAULT 0, -- pushed to terminal?
    active      TINYINT(1) NOT NULL DEFAULT 1,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX (emp_no)
) ENGINE=InnoDB;

-- Raw recognition events as received from the terminal
CREATE TABLE IF NOT EXISTS events (
    id              BIGINT AUTO_INCREMENT PRIMARY KEY,
    emp_no          VARCHAR(32) DEFAULT NULL,
    name            VARCHAR(128) DEFAULT NULL,
    event_time      DATETIME NOT NULL,
    major_event     INT DEFAULT NULL,
    sub_event       INT DEFAULT NULL,
    verify_mode     VARCHAR(64) DEFAULT NULL,
    image_path      VARCHAR(255) DEFAULT NULL,
    raw_json        MEDIUMTEXT,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX (emp_no),
    INDEX (event_time)
) ENGINE=InnoDB;

-- Computed daily attendance (one row per employee per day)
CREATE TABLE IF NOT EXISTS attendance (
    id            BIGINT AUTO_INCREMENT PRIMARY KEY,
    emp_no        VARCHAR(32) NOT NULL,
    work_date     DATE NOT NULL,
    check_in      DATETIME DEFAULT NULL,
    check_out     DATETIME DEFAULT NULL,
    work_minutes  INT NOT NULL DEFAULT 0,
    status        VARCHAR(16) NOT NULL DEFAULT 'present', -- present | late | absent
    updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_emp_day (emp_no, work_date),
    INDEX (work_date)
) ENGINE=InnoDB;
