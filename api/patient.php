<?php
/**
 * MEDGem — Patient API
 * Create and update patient cases
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/security.php';
setSecurityHeaders();
header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    // Create new case
    $name = trim($_POST['name'] ?? '');
    $age = (int) ($_POST['age'] ?? 0);
    $contactNumber = trim($_POST['contact_number'] ?? '');
    $sex = trim($_POST['sex'] ?? '');
    $language = trim($_POST['preferred_language'] ?? 'English');
    $mode = trim($_POST['communication_mode'] ?? 'TEXT');
    
    // Validate
    $errors = [];
    if (empty($name)) $errors[] = 'Name is required';
    if ($age <= 0 || $age > 150) $errors[] = 'Valid age is required';
    if (!in_array($sex, ['Male', 'Female', 'Other'])) $errors[] = 'Valid sex is required';
    
    if (!empty($errors)) {
        jsonResponse(['success' => false, 'error' => implode(', ', $errors)], 400);
    }
    
    $result = createCase([
        'name' => $name,
        'age' => $age,
        'contact_number' => $contactNumber,
        'sex' => $sex,
        'preferred_language' => $language,
        'communication_mode' => $mode
    ]);
    
    jsonResponse([
        'success' => true,
        'case_id' => $result['id'],
        'track_id' => $result['track_id']
    ]);
    
} elseif ($method === 'PUT') {
    // Update case (status, mode)
    $input = json_decode(file_get_contents('php://input'), true);
    $caseId = (int) ($input['case_id'] ?? 0);
    
    if ($caseId <= 0) {
        jsonResponse(['success' => false, 'error' => 'Invalid case ID'], 400);
    }
    
    $db = getDB();
    
    if (isset($input['status'])) {
        updateCaseStatus($caseId, $input['status']);
    }
    
    if (isset($input['communication_mode'])) {
        $stmt = $db->prepare("UPDATE cases SET communication_mode = ? WHERE id = ?");
        $stmt->execute([$input['communication_mode'], $caseId]);
    }
    
    jsonResponse(['success' => true]);
    
} else {
    jsonResponse(['error' => 'Method not allowed'], 405);
}
