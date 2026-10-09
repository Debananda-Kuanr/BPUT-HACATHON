<?php
/**
 * MEDGem — AI Processing API
 * Save triage results and safety signals
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
$action = $input['action'] ?? '';

if ($action === 'save_triage') {
    $caseId = (int) ($input['case_id'] ?? 0);
    
    if ($caseId <= 0) {
        jsonResponse(['success' => false, 'error' => 'Invalid case ID'], 400);
    }
    
    // Save triage results
    saveTriageResults($caseId, [
        'summary' => $input['summary'] ?? '',
        'symptoms' => $input['symptoms'] ?? [],
        'timeline' => $input['timeline'] ?? [],
        'missing_information' => $input['missing_information'] ?? [],
        'followup_questions' => $input['followup_questions'] ?? []
    ]);
    
    // Save safety signals
    $signals = $input['safety_signals'] ?? [];
    foreach ($signals as $signal) {
        saveUrgencySignal(
            $caseId,
            $signal['rule'] ?? 'RULE-000',
            $signal['signal'] ?? '',
            $signal['source'] ?? 'Patient statement',
            $signal['reason'] ?? ''
        );
    }
    
    // Update status to processing
    updateCaseStatus($caseId, 'PROCESSING');
    
    jsonResponse(['success' => true]);
    
} else {
    jsonResponse(['error' => 'Invalid action'], 400);
}
