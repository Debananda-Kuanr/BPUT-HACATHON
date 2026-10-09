<?php
require_once __DIR__ . '/../includes/db.php';

$db = getDB();

$db->exec("
    CREATE TABLE IF NOT EXISTS case_notes (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        case_id     INT NOT NULL,
        user_id     INT,
        user_role   VARCHAR(20)  NOT NULL DEFAULT 'NURSE',
        user_name   VARCHAR(120) NOT NULL DEFAULT '',
        note        TEXT         NOT NULL,
        created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_case_notes_case (case_id),
        INDEX idx_case_notes_time (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

echo "case_notes table: OK\n";

// Check row count
$stmt = $db->query("SELECT COUNT(*) AS c FROM case_notes");
$row  = $stmt->fetch();
echo "Existing rows: " . $row['c'] . "\n";
echo "Done.\n";
