<?php
/**
 * MEDGem — Doctor API (for AJAX actions)
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/security.php';
setSecurityHeaders();
header('Content-Type: application/json');

requireAuth('DOCTOR');
$user = getCurrentUser();
$input = json_decode(file_get_contents('php://input'), true);

$action = $input['action'] ?? '';
$caseId = (int) ($input['case_id'] ?? 0);

if ($caseId <= 0) jsonResponse(['success' => false, 'error' => 'Invalid case'], 400);

$db = getDB();

if ($action === 'generate_referral') {
    // Generate a draft referral note
    $case = getFullCase($caseId);
    if (!$case) jsonResponse(['success' => false, 'error' => 'Case not found'], 404);
    
    $triage = $case['triage'];
    $signals = $case['signals'];
    $nurseReviews = $case['nurse_reviews'];
    
    $draft = "REFERRAL-SUPPORT NOTE — DRAFT\n\n";
    $draft .= "Patient ID: " . $case['track_id'] . "\n";
    $draft .= "Patient: " . $case['name'] . ", " . $case['age'] . " yrs, " . $case['sex'] . "\n\n";
    
    $draft .= "Presenting Information:\n";
    if ($triage && !empty($triage['symptoms'])) {
        $draft .= implode(', ', $triage['symptoms']) . "\n\n";
    }
    
    if (!empty($signals)) {
        $draft .= "Safety Signals:\n";
        foreach ($signals as $s) $draft .= "• " . $s['signal'] . "\n";
        $draft .= "\n";
    }
    
    if (!empty($nurseReviews)) {
        $draft .= "Nurse Notes:\n";
        foreach ($nurseReviews as $r) $draft .= $r['notes'] . "\n";
    }
    
    jsonResponse(['success' => true, 'draft' => $draft]);
}

jsonResponse(['error' => 'Invalid action'], 400);
