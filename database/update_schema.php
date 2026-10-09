<?php
require_once __DIR__ . '/../includes/db.php';

try {
    $db = getDB();
    
    // Check if answered_questions column exists in nurse_reviews
    $stmt = $db->query("SHOW COLUMNS FROM nurse_reviews LIKE 'answered_questions'");
    if (!$stmt->fetch()) {
        $db->exec("ALTER TABLE nurse_reviews ADD COLUMN answered_questions JSON NULL AFTER notes");
        echo "Added answered_questions to nurse_reviews\n";
    } else {
        echo "answered_questions already exists in nurse_reviews\n";
    }

    // Check if extracted_values is in reports
    $stmt = $db->query("SHOW COLUMNS FROM reports LIKE 'extracted_values'");
    if (!$stmt->fetch()) {
        $db->exec("ALTER TABLE reports ADD COLUMN extracted_values JSON NULL AFTER ocr_text");
        echo "Added extracted_values to reports\n";
    } else {
        echo "extracted_values already exists in reports\n";
    }

    // Check if case_notes table exists
    $db->exec("
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
        ) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    ");
    echo "case_notes table verified/created\n";

    // Migrate any existing nurse_reviews notes to case_notes if not already there
    $stmt = $db->query("
        SELECT nr.*, u.name as user_name 
        FROM nurse_reviews nr 
        JOIN users u ON nr.nurse_id = u.id 
        WHERE nr.notes IS NOT NULL AND TRIM(nr.notes) != ''
    ");
    $existing = $stmt->fetchAll();
    foreach ($existing as $row) {
        $check = $db->prepare("SELECT id FROM case_notes WHERE case_id = ? AND note = ? LIMIT 1");
        $check->execute([$row['case_id'], $row['notes']]);
        if (!$check->fetch()) {
            $ins = $db->prepare("INSERT INTO case_notes (case_id, user_id, user_role, user_name, note, created_at) VALUES (?, ?, 'NURSE', ?, ?, ?)");
            $ins->execute([$row['case_id'], $row['nurse_id'], $row['user_name'], $row['notes'], $row['created_at']]);
            echo "Migrated note for case {$row['case_id']}\n";
        }
    }

    echo "Schema update complete!\n";
} catch (Exception $e) {
    echo "Migration error: " . $e->getMessage() . "\n";
}
