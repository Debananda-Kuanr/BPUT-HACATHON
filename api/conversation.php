<?php
/**
 * MEDGem — Conversation API
 * Save conversation messages
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/security.php';
setSecurityHeaders();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);

$caseId = (int) ($input['case_id'] ?? 0);
$mode = $input['mode'] ?? 'TEXT';
$speaker = $input['speaker'] ?? '';
$message = $input['message'] ?? '';

if ($caseId <= 0 || empty($speaker) || empty($message)) {
    jsonResponse(['success' => false, 'error' => 'Missing required fields'], 400);
}

if (!in_array($mode, ['TEXT', 'VOICE'])) $mode = 'TEXT';
if (!in_array($speaker, ['PATIENT', 'MEDGEM'])) $speaker = 'PATIENT';

$messageId = addConversationMessage($caseId, $mode, $speaker, $message);

jsonResponse([
    'success' => true,
    'message_id' => $messageId
]);
