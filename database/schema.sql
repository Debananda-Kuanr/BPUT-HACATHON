-- MEDGem Database Schema
-- Multimodal Healthcare Triage Assistant

CREATE DATABASE IF NOT EXISTS medgem CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE medgem;

-- Staff users (nurses and doctors)
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('NURSE', 'DOCTOR') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Patient cases
CREATE TABLE IF NOT EXISTS cases (
    id INT AUTO_INCREMENT PRIMARY KEY,
    track_id VARCHAR(20) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    age INT NOT NULL,
    contact_number VARCHAR(20),
    sex ENUM('Male', 'Female', 'Other') NOT NULL,
    preferred_language ENUM('English', 'Hindi', 'Odia') DEFAULT 'English',
    consent TINYINT(1) DEFAULT 0,
    status ENUM(
        'NEW',
        'PROCESSING',
        'PENDING_NURSE_REVIEW',
        'IN_NURSE_REVIEW',
        'REVIEWED',
        'ESCALATED',
        'DOCTOR_REVIEW',
        'COMPLETED',
        'REFERRED'
    ) DEFAULT 'NEW',
    communication_mode ENUM('TEXT', 'VOICE') DEFAULT 'TEXT',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_track_id (track_id),
    INDEX idx_status (status)
) ENGINE=InnoDB;

-- Conversation messages
CREATE TABLE IF NOT EXISTS conversations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    case_id INT NOT NULL,
    mode ENUM('TEXT', 'VOICE') NOT NULL,
    speaker ENUM('PATIENT', 'MEDGEM') NOT NULL,
    message TEXT NOT NULL,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (case_id) REFERENCES cases(id) ON DELETE CASCADE,
    INDEX idx_case_id (case_id)
) ENGINE=InnoDB;

-- Uploaded medical reports
CREATE TABLE IF NOT EXISTS reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    case_id INT NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    ocr_text TEXT,
    extracted_values JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (case_id) REFERENCES cases(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- AI-generated triage results
CREATE TABLE IF NOT EXISTS triage_results (
    id INT AUTO_INCREMENT PRIMARY KEY,
    case_id INT NOT NULL,
    summary TEXT,
    symptoms JSON,
    timeline JSON,
    missing_information JSON,
    followup_questions JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (case_id) REFERENCES cases(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Safety/urgency signals
CREATE TABLE IF NOT EXISTS urgency_signals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    case_id INT NOT NULL,
    rule_id VARCHAR(20) NOT NULL,
    `signal` VARCHAR(255) NOT NULL,
    `source` VARCHAR(100) NOT NULL,
    reason TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (case_id) REFERENCES cases(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Nurse reviews
CREATE TABLE IF NOT EXISTS nurse_reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    case_id INT NOT NULL,
    nurse_id INT NOT NULL,
    verified_summary TEXT,
    notes TEXT,
    answered_questions JSON,
    decision ENUM('REVIEWED', 'ESCALATED', 'REQUEST_INFO') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (case_id) REFERENCES cases(id) ON DELETE CASCADE,
    FOREIGN KEY (nurse_id) REFERENCES users(id)
) ENGINE=InnoDB;

-- Doctor reviews
CREATE TABLE IF NOT EXISTS doctor_reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    case_id INT NOT NULL,
    doctor_id INT NOT NULL,
    notes TEXT,
    decision ENUM('COMPLETED', 'REFERRED', 'REQUEST_INFO'),
    referral_note TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (case_id) REFERENCES cases(id) ON DELETE CASCADE,
    FOREIGN KEY (doctor_id) REFERENCES users(id)
) ENGINE=InnoDB;

-- Case Notes (Timeline/Comments by staff)
CREATE TABLE IF NOT EXISTS case_notes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    case_id INT NOT NULL,
    user_id INT NOT NULL,
    user_role ENUM('NURSE', 'DOCTOR') NOT NULL,
    user_name VARCHAR(100) NOT NULL,
    note TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (case_id) REFERENCES cases(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_case_notes (case_id),
    INDEX idx_notes_created (created_at)
) ENGINE=InnoDB;

-- Audit logs
CREATE TABLE IF NOT EXISTS audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    case_id INT,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (case_id) REFERENCES cases(id) ON DELETE SET NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_case_audit (case_id),
    INDEX idx_timestamp (created_at)
) ENGINE=InnoDB;

-- Seed demo staff accounts
INSERT INTO users (name, email, password, role) VALUES
('Nurse Priya Sharma', 'nurse@medgem.demo', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'NURSE'),
('Dr. Rajesh Kumar', 'doctor@medgem.demo', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'DOCTOR');
-- Default password for both: password
