<?php
/**
 * MEDGem — Nurse / Staff Notes API
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/security.php';
setSecurityHeaders();
header('Content-Type: application/json');

// Check authentication for staff (Nurse or Doctor)
$user = getCurrentUser();
if (!$user) {
    jsonResponse(['success' => false, 'error' => 'Unauthorized'], 401);
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input && !empty($_POST)) {
    $input = $_POST;
}

$action = $input['action'] ?? '';
$caseId = (int) ($input['case_id'] ?? 0);

if ($caseId <= 0) {
    jsonResponse(['success' => false, 'error' => 'Invalid case ID'], 400);
}

// Fetch case to verify status
$case = getFullCase($caseId);
if (!$case) {
    jsonResponse(['success' => false, 'error' => 'Case not found'], 404);
}

$isCompleted = in_array(strtoupper($case['status']), ['COMPLETED', 'REFERRED', 'CLEARED']);

// If case is complete and user is a NURSE, deny all modifying actions
// Doctors can still add follow-up notes even on completed cases
if ($isCompleted && $user['role'] === 'NURSE' && ($input['action'] ?? '') !== 'add_note') {
    jsonResponse(['success' => false, 'error' => 'Case is marked as Complete. Nurses cannot make modifications to finalized cases.'], 403);
}

// Nurses cannot add notes on completed cases either
if ($isCompleted && $user['role'] === 'NURSE' && ($input['action'] ?? '') === 'add_note') {
    jsonResponse(['success' => false, 'error' => 'Case is finalized. Nurses cannot add notes to completed cases.'], 403);
}

$db = getDB();

if ($action === 'add_note' || $action === 'save_notes') {
    $noteText = trim($input['note'] ?? $input['notes'] ?? '');
    if ($noteText === '') {
        jsonResponse(['success' => false, 'error' => 'Note content cannot be empty'], 422);
    }
    
    $noteId = addCaseNote($caseId, $user['id'], $user['role'], $user['name'], $noteText);
    
    if ($noteId > 0) {
        $now = date('Y-m-d H:i:s');
        jsonResponse([
            'success' => true,
            'note' => [
                'id' => $noteId,
                'case_id' => $caseId,
                'user_id' => $user['id'],
                'user_role' => $user['role'],
                'user_name' => $user['name'],
                'note' => $noteText,
                'created_at' => $now,
                'time_ago' => 'Just now',
                'formatted_date' => date('d M Y, h:i A')
            ]
        ]);
    } else {
        jsonResponse(['success' => false, 'error' => 'Failed to save note'], 500);
    }
}

jsonResponse(['error' => 'Invalid action'], 400);
