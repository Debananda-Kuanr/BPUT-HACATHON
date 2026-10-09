<?php
/**
 * MEDGem — Nurse Case Review & Clinical Intake Verification
 * 
 * Clinical Workflow Requirements:
 * 1. Nurse CANNOT edit AI Summary (Strictly Read-Only Baseline).
 * 2. Visual Sequence:
 *    - FIRST: All Patient Information (Demographics, Contact, Mode, Urgency, Completeness).
 *    - SECOND: All Attached Medical Images & Reports (Thumbnails, Fullscreen Viewer, Nurse Verification).
 * 3. Clinical Report Verification:
 *    - Nurse reviews and explicitly verifies each input report (Relevance toggle + Nurse clinical note).
 *    - Structured lab findings table with Reference Ranges and High/Low/Normal flags.
 * 4. Paginated Dossier Navigation:
 *    - Mobile & desktop responsive pagination (< > scroll arrows, tab strips, and prev/next page controls).
 *    - Tabs: Summary (Read-Only), Symptoms, Timeline, Lab Reports, Patient Statement.
 * 5. Interactive Nurse Question Answering:
 *    - Input fields for Nurse to ask patient missing clinical questions and record responses.
 * 6. Nurse Clinical Vitals & Escalation Controls.
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/security.php';
setSecurityHeaders();
requireAuth('NURSE');

$user = getCurrentUser();
$caseId = (int) ($_GET['id'] ?? 0);

if ($caseId <= 0) {
    header('Location: /BPUT/nurse/dashboard.php');
    exit;
}

$case = getFullCase($caseId);
if (!$case) {
    header('Location: /BPUT/nurse/dashboard.php');
    exit;
}

// Check if case is completed / referred (read-only for nurse)
$isCaseComplete = in_array(strtoupper($case['status']), ['COMPLETED', 'REFERRED', 'CLEARED']);

// Update status to IN_NURSE_REVIEW only if still pending and NOT completed
if ($case['status'] === 'PENDING_NURSE_REVIEW' && !$isCaseComplete) {
    updateCaseStatus($caseId, 'IN_NURSE_REVIEW', $user['id']);
    $case['status'] = 'IN_NURSE_REVIEW';
}

$triage = $case['triage'] ?? [];
$signals = $case['signals'] ?? [];
$conversations = $case['conversations'] ?? [];
$reports = $case['reports'] ?? [];
$nurseReviews = $case['nurse_reviews'] ?? [];
$doctorReviews = $case['doctor_reviews'] ?? [];
$auditLogs = $case['audit_logs'] ?? [];
$caseNotes = $case['notes'] ?? getCaseNotes($caseId);

// Pre-fill existing nurse answers if previously saved
$existingAnswers = [];
if (!empty($nurseReviews) && !empty($nurseReviews[0]['answered_questions'])) {
    foreach ($nurseReviews[0]['answered_questions'] as $qa) {
        $existingAnswers[$qa['question']] = $qa['answer'];
    }
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // If case is complete, Nurse CANNOT do anything (strictly read-only)
    if ($isCaseComplete) {
        header('Location: /BPUT/nurse/case.php?id=' . $caseId);
        exit;
    }

    $action = $_POST['action'] ?? '';
    $db = getDB();
    
    // Action: Add standalone clinical note (comment style with auto timestamp)
    if ($action === 'add_note') {
        $singleNote = trim($_POST['single_note'] ?? '');
        if ($singleNote !== '') {
            addCaseNote($caseId, $user['id'], 'NURSE', $user['name'], $singleNote);
        }
        header('Location: /BPUT/nurse/case.php?id=' . $caseId . '#notesStreamSection');
        exit;
    }
    
    $notes = trim($_POST['notes'] ?? '');
    if ($notes !== '') {
        addCaseNote($caseId, $user['id'], 'NURSE', $user['name'], $notes);
    }
    
    // IMPORTANT: AI Summary CANNOT be edited by Nurse.
    $verifiedSummary = $triage['summary'] ?? '';
    
    // 1. Process Report Verifications by Nurse
    $reportVerifications = $_POST['report_verifications'] ?? [];
    if (is_array($reportVerifications)) {
        foreach ($reportVerifications as $rId => $rv) {
            $rId = (int)$rId;
            $isRel = ($rv['status'] ?? 'RELEVANT') === 'RELEVANT';
            $nNote = trim($rv['note'] ?? '');
            
            $stmtF = $db->prepare("SELECT extracted_values FROM reports WHERE id = ? AND case_id = ?");
            $stmtF->execute([$rId, $caseId]);
            $rawEv = $stmtF->fetchColumn();
            $evArr = json_decode($rawEv, true) ?: [];
            
            $evArr['is_relevant'] = $isRel;
            $evArr['relevance_status'] = $isRel ? 'RELEVANT' : 'IGNORED';
            $evArr['nurse_verified'] = true;
            $evArr['nurse_verified_by'] = $user['name'];
            $evArr['nurse_verified_at'] = date('Y-m-d H:i:s');
            $evArr['nurse_note'] = $nNote;
            
            $stmtU = $db->prepare("UPDATE reports SET extracted_values = ? WHERE id = ?");
            $stmtU->execute([json_encode($evArr), $rId]);
        }
    }
    
    // 2. Process Answered Missing Questions from Nurse
    $rawAnswers = $_POST['answered_questions'] ?? [];
    $cleanedAnswers = [];
    if (is_array($rawAnswers)) {
        foreach ($rawAnswers as $qa) {
            $q = trim($qa['question'] ?? '');
            $a = trim($qa['answer'] ?? '');
            if ($q !== '') {
                $cleanedAnswers[] = [
                    'question' => $q,
                    'answer' => $a
                ];
            }
        }
    }
    
    // Check for custom nurse question added
    $customQ = trim($_POST['custom_question'] ?? '');
    $customA = trim($_POST['custom_answer'] ?? '');
    if ($customQ !== '' && $customA !== '') {
        $cleanedAnswers[] = [
            'question' => $customQ,
            'answer' => $customA
        ];
    }
    
    $jsonAnswers = !empty($cleanedAnswers) ? json_encode($cleanedAnswers) : null;
    
    if ($action === 'mark_reviewed') {
        $stmt = $db->prepare("
            INSERT INTO nurse_reviews (case_id, nurse_id, verified_summary, notes, answered_questions, decision) 
            VALUES (?, ?, ?, ?, ?, 'REVIEWED')
        ");
        $stmt->execute([$caseId, $user['id'], $verifiedSummary, $notes, $jsonAnswers]);
        
        updateCaseStatus($caseId, 'REVIEWED', $user['id']);
        addAuditLog($caseId, $user['id'], 'NURSE_REVIEWED', 'Nurse verified patient reports, answered missing questions, and confirmed routine priority');
        header('Location: /BPUT/nurse/dashboard.php');
        exit;
    }
    
    if ($action === 'escalate') {
        $stmt = $db->prepare("
            INSERT INTO nurse_reviews (case_id, nurse_id, verified_summary, notes, answered_questions, decision) 
            VALUES (?, ?, ?, ?, ?, 'ESCALATED')
        ");
        $stmt->execute([$caseId, $user['id'], $verifiedSummary, $notes, $jsonAnswers]);
        
        updateCaseStatus($caseId, 'ESCALATED', $user['id']);
        addAuditLog($caseId, $user['id'], 'NURSE_ESCALATED', 'Nurse verified patient reports, answered questions, and escalated to physician');
        header('Location: /BPUT/nurse/dashboard.php');
        exit;
    }
}

$patientStatements = array_filter($conversations, fn($m) => $m['speaker'] === 'PATIENT');
$originalStatement = implode("\n", array_map(fn($m) => $m['message'], $patientStatements));

$imageExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

// Determine Symptoms & Chief Complaint
$symptomList = is_array($triage['symptoms'] ?? null) ? $triage['symptoms'] : [];
$chiefComplaints = [];
foreach ($symptomList as $s) {
    if (is_string($s)) $chiefComplaints[] = $s;
    elseif (is_array($s) && !empty($s['name'])) $chiefComplaints[] = $s['name'];
    elseif (is_array($s) && !empty($s['symptom'])) $chiefComplaints[] = $s['symptom'];
}
$complaintText = !empty($chiefComplaints) ? implode(', ', $chiefComplaints) : (!empty($triage['chief_complaint']) ? $triage['chief_complaint'] : 'Clinical symptoms under evaluation');

// Determine Overall Urgency Level
$urgencyLevel = 'LOW';
if (!empty($signals) || strtolower($case['status'] ?? '') === 'urgent' || strtolower($case['urgency'] ?? '') === 'urgent') {
    $urgencyLevel = 'CRITICAL';
} elseif (strtolower($case['urgency'] ?? '') === 'high') {
    $urgencyLevel = 'HIGH';
} elseif (strtolower($case['urgency'] ?? '') === 'moderate' || strtolower($case['urgency'] ?? '') === 'medium') {
    $urgencyLevel = 'MEDIUM';
} elseif (!empty($triage['overall_urgency'])) {
    $urgencyLevel = strtoupper($triage['overall_urgency']);
} elseif (!empty($triage['urgency'])) {
    $urgencyLevel = strtoupper($triage['urgency']);
}

// Prepare Clinical Referral Note
$referralNoteLines = [];
$referralNoteLines[] = "================================================================================";
$referralNoteLines[] = "                    PATIENT REFERRAL & CLINICAL TRIAGE NOTE";
$referralNoteLines[] = "================================================================================";
$referralNoteLines[] = "Facility: Primary Health Care Triage Unit";
$referralNoteLines[] = "Tracking ID: " . $case['track_id'];
$referralNoteLines[] = "Date: " . date('d M Y, h:i A');
$referralNoteLines[] = "Patient Demographics: " . $case['name'] . " | Age: " . $case['age'] . " | Gender: " . $case['sex'] . " | Lang: " . $case['preferred_language'];
$referralNoteLines[] = "Triage Urgency: " . strtoupper($urgencyLevel);
$referralNoteLines[] = "";
$referralNoteLines[] = "1. CHIEF COMPLAINT:";
$referralNoteLines[] = "   " . $complaintText;
$referralNoteLines[] = "";
$referralNoteLines[] = "2. PRESENTING SYMPTOMS:";
if (!empty($symptomList)) {
    foreach ($symptomList as $s) {
        $sName = is_array($s) ? ($s['name'] ?? $s['symptom'] ?? 'Symptom') : (string)$s;
        $sSev = is_array($s) ? ($s['severity'] ?? 'MODERATE') : 'MODERATE';
        $sDur = is_array($s) ? ($s['duration'] ?? 'Active') : 'Active';
        $sNotes = is_array($s) ? ($s['notes'] ?? $s['details'] ?? '-') : '-';
        $referralNoteLines[] = "   - " . $sName . " [Severity: " . strtoupper($sSev) . " | Duration: " . $sDur . "] : " . $sNotes;
    }
} else {
    $referralNoteLines[] = "   None recorded";
}
$referralNoteLines[] = "";
$referralNoteLines[] = "3. LAB / REPORT FINDINGS:";
$hasLabFindings = false;
foreach ($reports as $rep) {
    $ev = $rep['extracted_values'] ?? [];
    if (!empty($ev['key_findings'])) {
        foreach ($ev['key_findings'] as $kf) {
            $hasLabFindings = true;
            $referralNoteLines[] = "   - " . $kf['test_name'] . ": " . $kf['value'] . " " . ($kf['unit'] ?? '') . " [" . strtoupper($kf['flag'] ?? 'NORMAL') . "] (Ref: " . ($kf['reference_range'] ?? '-') . ")";
        }
    }
}
if (!$hasLabFindings) {
    $referralNoteLines[] = "   No attached lab abnormalities detected.";
}
$referralNoteLines[] = "";
$referralNoteLines[] = "4. CLINICAL TIMELINE:";
$timeline = $triage['timeline'] ?? [];
if (!empty($timeline)) {
    foreach ($timeline as $t) {
        $timeStr = is_array($t) ? ($t['time'] ?? $t['timepoint'] ?? 'Recent') : (string)$t;
        $eventStr = is_array($t) ? ($t['event'] ?? '') : '';
        $referralNoteLines[] = "   [" . strtoupper($timeStr) . "] " . ($eventStr ?: $timeStr);
    }
} else {
    $referralNoteLines[] = "   Recent symptom onset recorded at triage.";
}
$referralNoteLines[] = "";
$referralNoteLines[] = "5. RECOMMENDED NEXT STEPS & INVESTIGATIONS:";
$referralNoteLines[] = "   - Attending Physician comprehensive evaluation & physical examination";
$referralNoteLines[] = "   - Routine complete blood count & indicated diagnostics";
$referralNoteLines[] = "";
$referralNoteLines[] = "6. DISCLAIMER:";
$referralNoteLines[] = "   AI-assisted clinical triage summary for qualified medical reviewer use only.";
$referralNoteLines[] = "   Not a medical diagnosis or prescription.";
$referralNoteLines[] = "================================================================================";
$referralNoteText = implode("\n", $referralNoteLines);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <title>Nurse Review — Case <?php echo sanitize($case['track_id']); ?> — MEDGem</title>
    <link rel="stylesheet" href="/BPUT/assets/css/medgem.css">
    <link rel="stylesheet" href="/BPUT/assets/css/dashboard.css">
    <style>
        .case-title-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 24px;
        }
        .case-title-group {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        .nurse-badge-locked {
            background: #f1f5f9;
            color: #475569;
            border: 1px solid #cbd5e1;
            font-size: 11px;
            font-weight: 700;
            padding: 4px 10px;
            border-radius: var(--radius-full);
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .nurse-badge-role {
            background: #dbeafe;
            color: #1e40af;
            border: 1px solid #bfdbfe;
            font-size: 11px;
            font-weight: 700;
            padding: 4px 10px;
            border-radius: var(--radius-full);
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .report-verify-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: var(--radius-xl);
            padding: 20px;
            margin-bottom: 18px;
            box-shadow: 0 2px 6px rgba(15, 23, 42, 0.04);
        }
        .report-verify-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 14px;
        }
        .findings-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        .findings-table th {
            text-align: left;
            padding: 8px 10px;
            background: #f8fafc;
            color: #64748b;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            border-bottom: 1px solid #e2e8f0;
        }
        .findings-table td {
            padding: 8px 10px;
            border-bottom: 1px solid #f1f5f9;
            color: var(--neutral-800);
        }
    </style>
</head>
<body>

<div class="prototype-banner">
    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline;vertical-align:-2px;margin-right:4px"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
    Synthetic Clinical Data &middot; Educational Prototype &middot; Nurse Intake Verification
</div>

<nav class="navbar">
    <div class="navbar__inner">
        <a href="/BPUT/" class="navbar__logo">
            <span class="navbar__logo-icon" style="background:linear-gradient(135deg, #396cf0 0%, #1c58ef 100%);border-radius:10px">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
            </span>
            <span style="font-weight:800;font-family:var(--font-heading, 'Raleway', sans-serif);color:#0f172a">MEDGem<span style="color:#396cf0">Care</span></span>
        </a>
        <div class="navbar__links">
            <span class="navbar__link" style="cursor:default;font-weight:600;color:var(--neutral-800)">
                <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:inline;vertical-align:-2px;margin-right:4px"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                <?php echo htmlspecialchars($user['name']); ?> (Nurse)
            </span>
            <a href="/BPUT/staff/login.php?logout=1" class="btn btn--ghost btn--sm" style="color:var(--danger-500)">Logout</a>
        </div>
    </div>
</nav>

<main class="dashboard-main" style="margin-left:0;padding-top:100px;padding-bottom:60px">
    <div class="container container--xl">
        
        <a href="/BPUT/nurse/dashboard.php" class="back-link" style="margin-bottom:16px;display:inline-flex;align-items:center;gap:6px">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
            Back to Dashboard
        </a>

        <!-- Case Header -->
        <div class="case-title-row">
            <div>
                <div class="case-title-group">
                    <h1 class="dash-header__title" style="font-family:var(--font-mono);margin:0;font-size:clamp(20px, 3vw, 28px)">
                        CASE <?php echo sanitize($case['track_id']); ?>
                    </h1>
                    <span class="nurse-badge-role">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                        Nurse Review
                    </span>
                    <span class="nurse-badge-locked" title="The AI Summary is locked as an official intake baseline">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                        AI Summary Locked
                    </span>
                </div>
                <p class="dash-header__subtitle" style="margin-top:6px">
                    <?php echo sanitize($case['name']); ?> &middot; <?php echo $case['age']; ?> yrs &middot; <?php echo $case['sex']; ?> &middot; <?php echo $case['preferred_language']; ?>
                </p>
            </div>
            <div style="display:flex;gap:var(--space-2);align-items:center;flex-wrap:wrap">
                <span class="badge badge--<?php echo !empty($signals) ? 'danger' : 'primary'; ?>">
                    <span class="badge__dot"></span>
                    <?php echo formatStatus($case['status']); ?>
                </span>
                <?php if (!empty($signals)): ?>
                <span class="badge badge--danger">
                    <?php echo count($signals); ?> Urgency Signal<?php echo count($signals) > 1 ? 's' : ''; ?>
                </span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Case Completed Notice Banner (If case is completed/referred, nurse has read-only access) -->
        <?php if ($isCaseComplete): ?>
        <div class="case-completed-banner">
            <div class="case-completed-banner__icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            </div>
            <div style="flex:1">
                <div class="case-completed-banner__title">Case Finalized &amp; Completed (Read-Only Mode)</div>
                <p class="case-completed-banner__desc">
                    This patient case has been officially finalized by the attending physician (Status: <strong><?php echo formatStatus($case['status']); ?></strong>). 
                    In compliance with clinical workflow protocols, all intake summaries, lab report verifications, and clinical assessments are locked in read-only archive mode. Nurses cannot make further edits.
                </p>
            </div>
            <span class="badge badge--success" style="font-size:12px;padding:6px 14px;white-space:nowrap">
                ✓ <?php echo formatStatus($case['status']); ?>
            </span>
        </div>
        <?php endif; ?>

        <!-- ====================================================================
             1. FIRST: ALL INFORMATION OF PATIENT
             ==================================================================== -->
        <div class="patient-overview-card">
            <div class="patient-overview-header">
                <div class="patient-overview-title">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="var(--primary-600)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                        <circle cx="12" cy="7" r="4"/>
                    </svg>
                    <span>1. Patient Demographics &amp; Registration Details</span>
                </div>
                <span class="badge badge--neutral" style="font-size:12px">
                    Tracking ID: <strong><?php echo sanitize($case['track_id']); ?></strong>
                </span>
            </div>

            <div class="patient-overview-grid">
                <div class="patient-info-field">
                    <div class="patient-info-label">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        Full Name
                    </div>
                    <div class="patient-info-val"><?php echo sanitize($case['name']); ?></div>
                </div>

                <div class="patient-info-field">
                    <div class="patient-info-label">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                        Age &amp; Sex
                    </div>
                    <div class="patient-info-val"><?php echo $case['age']; ?> Years &middot; <?php echo sanitize($case['sex']); ?></div>
                </div>

                <div class="patient-info-field">
                    <div class="patient-info-label">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                        Contact Number
                    </div>
                    <div class="patient-info-val"><?php echo sanitize($case['contact_number'] ?: 'Not Provided'); ?></div>
                </div>

                <div class="patient-info-field">
                    <div class="patient-info-label">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                        Preferred Language
                    </div>
                    <div class="patient-info-val"><?php echo sanitize($case['preferred_language']); ?></div>
                </div>

                <div class="patient-info-field">
                    <div class="patient-info-label">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14M15.54 8.46a5 5 0 0 1 0 7.07"/></svg>
                        Intake Mode
                    </div>
                    <div class="patient-info-val">
                        <span class="source-tag source-tag--<?php echo strtolower($case['communication_mode'] ?? 'text'); ?>">
                            <?php echo sanitize($case['communication_mode'] ?? 'TEXT'); ?> Mode
                        </span>
                    </div>
                </div>

                <div class="patient-info-field">
                    <div class="patient-info-label">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                        Intake Timestamp
                    </div>
                    <div class="patient-info-val"><?php echo date('d M Y, h:i A', strtotime($case['created_at'])); ?></div>
                </div>
            </div>

            <!-- Chief Complaint Highlight -->
            <?php 
            $symptomList = is_array($triage['symptoms'] ?? null) ? $triage['symptoms'] : [];
            $chiefComplaints = [];
            foreach ($symptomList as $s) {
                if (is_string($s)) $chiefComplaints[] = $s;
                elseif (is_array($s) && !empty($s['name'])) $chiefComplaints[] = $s['name'];
            }
            $complaintText = !empty($chiefComplaints) ? implode(', ', $chiefComplaints) : 'Clinical symptoms under evaluation';
            ?>
            <div class="patient-chief-complaint-box">
                <div class="label">Primary Chief Complaint &middot; Triage Baseline</div>
                <div class="text"><?php echo sanitize($complaintText); ?></div>
            </div>
        </div>

        <!-- ====================================================================
             START NURSE REVIEW FORM (WRAPS BOTH REPORT VERIFICATION & CLINICAL INPUTS)
             ==================================================================== -->
        <form method="POST">
            <?php echo csrfField(); ?>

            <!-- ================================================================
                 2. SECOND: ALL ATTACHED IMAGES & MEDICAL REPORTS (WITH NURSE VERIFICATION)
                 ================================================================ -->
            <div class="attached-gallery-card">
                <div class="attached-gallery-header">
                    <div class="attached-gallery-title">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="var(--primary-600)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                            <circle cx="8.5" cy="8.5" r="1.5"/>
                            <polyline points="21 15 16 10 5 21"/>
                        </svg>
                        <span>2. Attached Medical Images &amp; Lab Documents</span>
                        <span class="badge badge--neutral" style="font-size:12px"><?php echo count($reports); ?> File<?php echo count($reports) !== 1 ? 's' : ''; ?></span>
                    </div>
                    <div style="font-size:12px;color:var(--neutral-500)">
                        Nurse must verify document relevance &amp; test findings below
                    </div>
                </div>

                <?php if (!empty($reports)): ?>
                <div class="attached-gallery-grid">
                    <?php foreach ($reports as $rIdx => $rep): 
                        $fPath = $rep['file_path'];
                        $ext = strtolower(pathinfo($fPath, PATHINFO_EXTENSION));
                        $isImage = in_array($ext, $imageExtensions);
                        $ev = $rep['extracted_values'] ?? [];
                        $isRelevant = $ev['is_relevant'] ?? true;
                        $isNurseVerified = !empty($ev['nurse_verified']);
                    ?>
                    <div class="attached-item-card">
                        <?php if ($isImage): ?>
                        <div class="attached-img-wrap" onclick="openImageModal('/BPUT/<?php echo htmlspecialchars($fPath); ?>', '<?php echo htmlspecialchars(addslashes($rep['file_name'])); ?>')">
                            <img src="/BPUT/<?php echo htmlspecialchars($fPath); ?>" alt="<?php echo htmlspecialchars($rep['file_name']); ?>" class="attached-img-thumb" loading="lazy">
                            <div class="attached-img-overlay">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/><line x1="11" y1="8" x2="11" y2="14"/><line x1="8" y1="11" x2="14" y2="11"/></svg>
                                Click to Expand
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="attached-doc-placeholder">
                            <svg width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                            <span style="font-size:12px;font-weight:600;text-transform:uppercase"><?php echo strtoupper($ext ?: 'DOC'); ?> Document</span>
                        </div>
                        <?php endif; ?>

                        <div class="attached-item-body">
                            <div>
                                <div class="attached-item-name" title="<?php echo htmlspecialchars($rep['file_name']); ?>">
                                    <?php echo sanitize($rep['file_name']); ?>
                                </div>
                                <div class="attached-item-meta">
                                    Uploaded: <?php echo date('d M Y, h:i A', strtotime($rep['created_at'])); ?>
                                </div>
                            </div>

                            <!-- Verification Badges -->
                            <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;margin:8px 0">
                                <?php if ($isNurseVerified): ?>
                                    <span class="nurse-verify-pill-relevant">✓ Nurse Verified</span>
                                <?php else: ?>
                                    <span class="nurse-verify-pill-pending">⚡ Pending Nurse Verification</span>
                                <?php endif; ?>

                                <?php if ($isRelevant): ?>
                                    <span class="lab-badge-relevant">RELEVANT</span>
                                <?php else: ?>
                                    <span class="lab-badge-ignored">IGNORED / OUTDATED</span>
                                <?php endif; ?>

                                <a href="/BPUT/<?php echo htmlspecialchars($fPath); ?>" target="_blank" class="btn btn--secondary btn--sm" style="font-size:11px;padding:3px 8px">
                                    View File &rarr;
                                </a>
                            </div>

                            <!-- Extracted Lab Parameters Table -->
                            <?php if (!empty($ev['key_findings']) && is_array($ev['key_findings'])): ?>
                            <div class="table-responsive">
                                <table class="findings-table">
                                    <thead>
                                        <tr>
                                            <th>Test</th>
                                            <th>Value</th>
                                            <th>Ref Range</th>
                                            <th>Flag</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($ev['key_findings'] as $kf): 
                                            $flag = strtoupper($kf['flag'] ?? 'NORMAL');
                                            $flagClass = in_array($flag, ['HIGH', 'CRITICAL']) ? 'danger' : ($flag === 'LOW' ? 'warning' : 'neutral');
                                        ?>
                                        <tr>
                                            <td style="font-weight:600"><?php echo sanitize($kf['test_name']); ?></td>
                                            <td><?php echo sanitize($kf['value']); ?> <?php echo sanitize($kf['unit']); ?></td>
                                            <td style="color:#64748b;font-size:11px"><?php echo sanitize($kf['reference_range']); ?></td>
                                            <td>
                                                <span class="badge badge--<?php echo $flagClass; ?>" style="font-size:10px;padding:1px 6px">
                                                    <?php echo sanitize($flag); ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php endif; ?>

                            <!-- Nurse Clinical Verification Controls -->
                            <div class="nurse-report-verify-box">
                                <div class="nurse-verify-title">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                                    Verify This Report For Doctor Review:
                                </div>
                                <div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap;margin-bottom:8px">
                                    <label style="font-size:12px;font-weight:600;display:inline-flex;align-items:center;gap:5px;cursor:<?php echo $isCaseComplete ? 'not-allowed' : 'pointer'; ?>">
                                        <input type="radio" name="report_verifications[<?php echo $rep['id']; ?>][status]" value="RELEVANT" <?php echo ($isRelevant ? 'checked' : ''); ?> <?php echo $isCaseComplete ? 'disabled' : ''; ?>>
                                        <span style="color:#15803d">✓ Verified Relevant (Current Episode)</span>
                                    </label>
                                    <label style="font-size:12px;font-weight:600;display:inline-flex;align-items:center;gap:5px;cursor:<?php echo $isCaseComplete ? 'not-allowed' : 'pointer'; ?>">
                                        <input type="radio" name="report_verifications[<?php echo $rep['id']; ?>][status]" value="IGNORED" <?php echo (!$isRelevant ? 'checked' : ''); ?> <?php echo $isCaseComplete ? 'disabled' : ''; ?>>
                                        <span style="color:#b45309">✗ Outdated / Unrelated</span>
                                    </label>
                                </div>
                                <input type="text" 
                                       name="report_verifications[<?php echo $rep['id']; ?>][note]" 
                                       class="nurse-q-input" 
                                       style="font-size:12px;padding:6px 10px" 
                                       placeholder="Nurse clinical verification note (e.g., 'Checked today CBC slip: Hb 11.2, Platelets 2.1L normal, ESR 22 mild inflammation')..." 
                                       value="<?php echo htmlspecialchars($ev['nurse_note'] ?? ''); ?>"
                                       <?php echo $isCaseComplete ? 'disabled readonly' : ''; ?>>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div style="padding:24px;background:#f8fafc;border-radius:var(--radius-lg);text-align:center;color:var(--neutral-500)">
                    <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="margin-bottom:8px;opacity:0.6"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                    <div style="font-size:var(--text-sm);font-weight:600;color:var(--neutral-700)">No medical documents or report images uploaded</div>
                    <div style="font-size:var(--text-xs);margin-top:4px">Patient did not attach any external test slips for this episode.</div>
                </div>
                <?php endif; ?>
            </div>

            <!-- ================================================================
                 3. NURSE INTAKE WORKFLOW & PAGINATED CLINICAL DOSSIER
                 ================================================================ -->
            <div class="review-grid">
                <!-- Left Main Column: Interactive Questions & Paginated Dossier -->
                <div>
                    <!-- Standardized Triage Rule Notice Banner -->
                    <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-left:4px solid #0b8a63;border-radius:var(--radius-md);padding:12px 16px;margin-bottom:var(--space-4);font-size:var(--text-xs);line-height:1.5;color:#166534">
                        <div style="font-weight:700;display:flex;align-items:center;gap:6px;margin-bottom:2px;text-transform:uppercase;letter-spacing:0.5px">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                            Triage Safety Protocol (Red / Yellow / Green)
                        </div>
                        <span>The AI’s color-coded prioritization (Red/Yellow/Green) is merely a sorting mechanism for the nurse's queue. It does not dictate final medical urgency, which is always overwritten by the human nurse's physical assessment.</span>
                    </div>

                    <!-- Safety Signals Alert (if present) -->
                    <?php if (!empty($signals)): ?>
                    <?php foreach ($signals as $signal): ?>
                    <div class="signal-card" style="margin-bottom:var(--space-4)">
                        <div class="signal-card__header">
                            <div class="signal-card__icon">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                            </div>
                            <span class="signal-card__title">Urgency Signal — <?php echo sanitize($signal['rule_id']); ?></span>
                        </div>
                        <div class="signal-card__body"><?php echo sanitize($signal['signal']); ?></div>
                        <dl class="signal-card__meta">
                            <dt>Source</dt><dd><?php echo sanitize($signal['source']); ?></dd>
                            <dt>Reason</dt><dd><?php echo sanitize($signal['reason']); ?></dd>
                        </dl>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>

                    <!-- SECTION: MISSING QUESTIONS WITH INPUT FIELDS -->
                    <div class="review-section" style="border: 2px solid var(--primary-200); background: #ffffff; margin-bottom: 24px;">
                        <div class="review-section__header" style="justify-content:space-between; flex-wrap:wrap; gap:8px">
                            <span class="review-section__title" style="color:var(--primary-800)">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                                Missing Questions &mdash; Ask Patient &amp; Record Answers
                            </span>
                            <span class="badge badge--warning" style="font-size:11px">Nurse Input Required</span>
                        </div>
                        <p style="font-size:var(--text-xs);color:var(--neutral-600);margin-bottom:16px;line-height:1.5">
                            The AI identified these specific missing clinical points during intake. Ask the patient these questions and enter their answers in the fields below. These answers will be recorded into the patient's verified file for the examining doctor.
                        </p>

                        <?php 
                        $missingItems = $triage['missing_information'] ?? [];
                        if (!empty($missingItems)):
                            foreach ($missingItems as $idx => $qText):
                                $prevAnswer = $existingAnswers[$qText] ?? '';
                        ?>
                        <div class="nurse-question-item">
                            <div class="nurse-question-header">
                                <span class="nurse-q-badge"><?php echo $idx + 1; ?></span>
                                <span><?php echo sanitize($qText); ?></span>
                            </div>
                            <input type="hidden" name="answered_questions[<?php echo $idx; ?>][question]" value="<?php echo htmlspecialchars($qText); ?>">
                            <input type="text" 
                                   name="answered_questions[<?php echo $idx; ?>][answer]" 
                                   class="nurse-q-input" 
                                   placeholder="Enter patient's response (e.g. '101°F since 2 days', 'No chills', 'Took paracetamol')..." 
                                   value="<?php echo htmlspecialchars($prevAnswer); ?>">
                        </div>
                        <?php 
                            endforeach;
                        else:
                        ?>
                        <div style="padding:14px;background:#f0fdf4;border-radius:var(--radius-md);color:#166534;font-size:var(--text-xs);margin-bottom:12px">
                            No missing questions were flagged by AI. The patient intake provided complete standard initial parameters.
                        </div>
                        <?php endif; ?>

                        <!-- Custom Question Added by Nurse -->
                        <div style="margin-top:16px;padding-top:14px;border-top:1px dashed var(--neutral-200)">
                            <div style="font-size:var(--text-xs);font-weight:700;color:var(--neutral-700);margin-bottom:8px">
                                + Add Additional Question Asked by Attending Nurse:
                            </div>
                            <div style="display:flex;flex-direction:column;gap:8px">
                                <input type="text" name="custom_question" class="nurse-q-input" style="font-size:var(--text-xs)" placeholder="Custom Question (e.g. Any known medication allergies or current prescriptions?)">
                                <input type="text" name="custom_answer" class="nurse-q-input" style="font-size:var(--text-xs)" placeholder="Patient's Answer (e.g. Allergic to Sulfa drugs, taking Amlodipine 5mg)">
                            </div>
                        </div>
                    </div>

                    <!-- ─── Report Panel ─── -->
                    <section class="report-panel" id="reportPanel">
                        <div class="report-content">
                            <div class="report-top">
                                <h3 class="report-title">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                                    Structured Triage Note
                                </h3>
                                <span id="urgencyBadge" class="urgency-badge urg-<?php echo strtolower($urgencyLevel); ?>">
                                    <?php echo strtoupper($urgencyLevel); ?>
                                </span>
                            </div>

                            <div class="report-actions">
                                <button type="button" id="referralBtn" class="btn-action" onclick="openReferralModal()">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                                    Referral Note
                                </button>
                                <button type="button" id="printReportBtn" class="btn-action" onclick="window.print()">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                                    Print / PDF
                                </button>
                            </div>

                            <!-- Tabs -->
                            <nav class="tabs-nav">
                                <button type="button" class="tab active" data-tab="summary" onclick="switchReportTab('summary')">Summary</button>
                                <button type="button" class="tab" data-tab="symptoms" onclick="switchReportTab('symptoms')">Symptoms</button>
                                <button type="button" class="tab" data-tab="timeline" onclick="switchReportTab('timeline')">Timeline</button>
                                <button type="button" class="tab" data-tab="findings" onclick="switchReportTab('findings')">Lab / Reports</button>
                                <button type="button" class="tab" data-tab="missing" onclick="switchReportTab('missing')">Missing Info</button>
                                <button type="button" class="tab" data-tab="questions" onclick="switchReportTab('questions')">Questions</button>
                                <button type="button" class="tab" data-tab="urgency" onclick="switchReportTab('urgency')">Urgency</button>
                            </nav>


                            <!-- Tab 1: Summary -->
                            <div id="tab-summary" class="tab-body">
                                <div class="r-section">
                                    <div class="r-meta">
                                        <span class="chip">Language: <?php echo sanitize($case['preferred_language'] ?: 'English'); ?></span>
                                        <span class="chip">Age: <?php echo $case['age']; ?></span>
                                        <span class="chip">Gender: <?php echo sanitize($case['sex']); ?></span>
                                        <span class="chip">Completeness: <?php echo !empty($missingItems) ? '65%' : '90%'; ?></span>
                                    </div>
                                </div>
                                <div class="r-section chief">
                                    <h4>Chief Complaint</h4>
                                    <p><strong><?php echo sanitize($complaintText); ?></strong></p>
                                </div>
                                <div class="r-section">
                                    <h4>Clinical Summary (English)</h4>
                                    <p><?php echo nl2br(sanitize($triage['summary'] ?? 'Standard intake completed.')); ?></p>
                                </div>
                                <div class="r-disclaimer">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                                    <span>AI-assisted triage support only. Not a medical diagnosis. Must be reviewed by qualified medical personnel.</span>
                                </div>
                            </div>

                            <!-- Tab 2: Symptoms -->
                            <div id="tab-symptoms" class="tab-body hidden">
                                <?php if (!empty($symptomList)): ?>
                                    <?php foreach ($symptomList as $s): 
                                        $sName = is_array($s) ? ($s['name'] ?? $s['symptom'] ?? 'Symptom') : (string)$s;
                                        $sSev = is_array($s) ? ($s['severity'] ?? 'MODERATE') : 'MODERATE';
                                        $sDur = is_array($s) ? ($s['duration'] ?? 'Reported at intake') : 'Active';
                                        $sNotes = is_array($s) ? ($s['notes'] ?? $s['details'] ?? 'Patient reported symptom during clinical intake.') : 'Patient reported symptom.';
                                    ?>
                                    <div class="symptom-card">
                                        <div class="sym-top">
                                            <span class="sym-name"><?php echo sanitize($sName); ?></span>
                                            <span class="sev sev-<?php echo strtolower($sSev); ?>"><?php echo sanitize($sSev); ?></span>
                                        </div>
                                        <div class="sym-detail">
                                            <span>Duration: <?php echo sanitize($sDur); ?></span>
                                            <span>&bull;</span>
                                            <span><?php echo sanitize($sNotes); ?></span>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p class="empty-tab">No structured symptoms extracted.</p>
                                <?php endif; ?>
                            </div>

                            <!-- Tab 3: Timeline -->
                            <div id="tab-timeline" class="tab-body hidden">
                                <?php if (!empty($timeline)): ?>
                                <div class="tl">
                                    <?php foreach ($timeline as $t): 
                                        $timeStr = is_array($t) ? ($t['time'] ?? $t['timepoint'] ?? 'Recent') : (string)$t;
                                        $eventStr = is_array($t) ? ($t['event'] ?? '') : '';
                                    ?>
                                    <div class="tl-item">
                                        <div class="tl-dot"></div>
                                        <div class="tl-line"></div>
                                        <div class="tl-body">
                                            <span class="tl-time"><?php echo strtoupper(sanitize($timeStr)); ?></span>
                                            <p><?php echo sanitize($eventStr ?: $timeStr); ?></p>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php else: ?>
                                    <div class="tl">
                                        <div class="tl-item">
                                            <div class="tl-dot"></div>
                                            <div class="tl-body">
                                                <span class="tl-time">RECENT</span>
                                                <p>Patient registered initial symptoms via clinical intake.</p>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Tab 4: Lab / Reports (Findings) -->
                            <div id="tab-findings" class="tab-body hidden">
                                <?php 
                                $anyFindingsRendered = false;
                                if (!empty($reports)):
                                    foreach ($reports as $rep):
                                        $ev = $rep['extracted_values'] ?? [];
                                        $kfs = $ev['key_findings'] ?? [];
                                        if (!empty($kfs) && is_array($kfs)):
                                            foreach ($kfs as $kf):
                                                $anyFindingsRendered = true;
                                                $flag = strtoupper($kf['flag'] ?? 'NORMAL');
                                                $flagClass = in_array($flag, ['HIGH', 'CRITICAL']) ? 'high' : ($flag === 'LOW' ? 'low' : ($flag === 'ABNORMAL' ? 'abnormal' : 'normal'));
                                ?>
                                <div class="finding-card">
                                    <div class="finding-top">
                                        <span class="finding-name"><?php echo sanitize($kf['test_name']); ?></span>
                                        <div>
                                            <span class="finding-val"><?php echo sanitize($kf['value']); ?> <?php echo sanitize($kf['unit'] ?? ''); ?></span>
                                            <span class="finding-flag flag-<?php echo $flagClass; ?>"><?php echo sanitize($flag); ?></span>
                                        </div>
                                    </div>
                                    <?php if (!empty($kf['reference_range'])): ?>
                                    <p class="tl-rel">Ref: <?php echo sanitize($kf['reference_range']); ?></p>
                                    <?php endif; ?>
                                    <p class="finding-interp">
                                        Source: <strong><?php echo sanitize($rep['file_name']); ?></strong>
                                        <?php if (!empty($ev['nurse_verified'])): ?>
                                        &middot; <span style="color:#0f766e;font-weight:600">&check; Nurse Verified</span>
                                        <?php endif; ?>
                                    </p>
                                </div>
                                <?php 
                                            endforeach;
                                        endif;
                                    endforeach;
                                endif; 
                                if (!$anyFindingsRendered):
                                ?>
                                <p class="empty-tab ok">No abnormal laboratory findings or uploaded medical reports yet.</p>
                                <?php endif; ?>
                            </div>

                            <!-- Tab 5: Missing Info -->
                            <div id="tab-missing" class="tab-body hidden">
                                <?php if (!empty($missingItems)): ?>
                                <ul class="miss-list">
                                    <?php foreach ($missingItems as $mi): ?>
                                    <li>
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                                        <span><?php echo sanitize(is_string($mi) ? $mi : json_encode($mi)); ?></span>
                                    </li>
                                    <?php endforeach; ?>
                                </ul>
                                <?php else: ?>
                                <p class="empty-tab ok">All essential info appears captured.</p>
                                <?php endif; ?>
                            </div>

                            <!-- Tab 6: Questions -->
                            <div id="tab-questions" class="tab-body hidden">
                                <?php 
                                $questionsList = $triage['follow_up_questions'] ?? $triage['recommended_questions'] ?? $missingItems;
                                if (!empty($questionsList)):
                                    foreach ($questionsList as $qIdx => $q):
                                        $qText = is_string($q) ? $q : ($q['question_en'] ?? $q['question'] ?? 'Question');
                                        $qReason = is_array($q) ? ($q['reason'] ?? '') : 'Clinical risk assessment & differential screening';
                                ?>
                                <div class="q-card">
                                    <span class="q-num">Q<?php echo $qIdx + 1; ?></span>
                                    <div>
                                        <p class="q-text"><?php echo sanitize($qText); ?></p>
                                        <p class="q-reason"><?php echo sanitize($qReason); ?></p>
                                    </div>
                                </div>
                                <?php 
                                    endforeach;
                                else:
                                ?>
                                <p class="empty-tab">No follow-up questions.</p>
                                <?php endif; ?>
                            </div>

                            <!-- Tab 7: Urgency -->
                            <div id="tab-urgency" class="tab-body hidden">
                                <?php if (!empty($signals)): ?>
                                    <?php foreach ($signals as $sig): 
                                        $sigLevel = strtolower($sig['level'] ?? 'medium');
                                        if (in_array(strtolower($sig['rule_id'] ?? ''), ['red-flags', 'critical', 'vital-crisis'])) $sigLevel = 'critical';
                                    ?>
                                    <div class="sig-card sig-<?php echo $sigLevel; ?>">
                                        <div class="sig-top">
                                            <span class="sig-level sl-<?php echo $sigLevel; ?>"><?php echo strtoupper($sigLevel); ?></span>
                                            <span class="sig-name"><?php echo sanitize($sig['signal']); ?></span>
                                        </div>
                                        <p class="sig-reason"><?php echo sanitize($sig['reason']); ?></p>
                                        <p class="sig-action"><strong>Source:</strong> <?php echo sanitize($sig['source']); ?> &middot; Prompt triage escalation recommended.</p>
                                    </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                <p class="empty-tab ok">No urgency signals detected.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </section>
                </div>

                <!-- Right Action Column: Attending Nurse Observations & Verification -->
                <div class="action-sidebar">
                    <div class="action-card">
                        <div class="action-card__title">Nurse Intake Verification</div>
                        
                        <?php if ($isCaseComplete): ?>
                        <div style="background:#fef9c3;border:1px solid #fde047;border-radius:8px;padding:10px 14px;font-size:12px;color:#713f12;margin-bottom:12px;display:flex;align-items:center;gap:8px">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                            Case finalized. Nurse intake actions are locked.
                        </div>
                        <?php endif; ?>

                        <?php if (!$isCaseComplete): ?>
                        <div class="action-buttons">
                            <button type="submit" name="action" value="mark_reviewed" class="btn btn--primary btn--full" style="padding:12px;font-weight:700">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                                Mark Verified (Routine)
                            </button>
                            <button type="submit" name="action" value="escalate" class="btn btn--outline btn--full" style="border-color:var(--warning-500);color:var(--warning-700);padding:12px;font-weight:700">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="17 11 12 6 7 11"/><polyline points="17 18 12 13 7 18"/></svg>
                                Escalate to Doctor (Urgent)
                            </button>
                        </div>
                        <?php else: ?>
                        <div style="background:#f1f5f9;border-radius:8px;padding:10px 14px;text-align:center;font-size:12px;color:#64748b;font-weight:600">
                            Case is in read-only mode. No further actions available.
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="action-card" style="background:var(--neutral-50)">
                        <p style="font-size:var(--text-xs);color:var(--neutral-600);line-height:1.6;margin:0">
                            <strong style="color:var(--neutral-800)">Clinical Role Separation:</strong> The AI Summary is locked as an immutable baseline. As attending nurse, you verify patient reports, record physical vitals, and answer clinical questions. The examining physician reviews your verified dossier in read-only mode.
                        </p>
                    </div>
                </form>

                <!-- ── Clinical Notes Stream (inside sidebar) ── -->
                <div id="notesStreamSection" class="action-card" style="padding:0;overflow:hidden;border:1px solid #e2e8f0;margin-top:16px">
                        <!-- Header -->
                        <div style="display:flex;align-items:center;justify-content:space-between;padding:14px 16px;border-bottom:1px solid #f1f5f9;background:#f8fafc">
                            <div style="display:flex;align-items:center;gap:8px">
                                <div style="background:linear-gradient(135deg,#396cf0,#1c58ef);border-radius:8px;width:28px;height:28px;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                                </div>
                                <div>
                                    <div style="font-size:13px;font-weight:800;color:#0f172a">Clinical Notes</div>
                                    <div style="font-size:10px;color:#64748b">Timestamped — permanent record</div>
                                </div>
                            </div>
                            <span id="notesCountBadge" style="background:#dbeafe;color:#1e40af;border:1px solid #bfdbfe;font-size:10px;font-weight:700;padding:3px 8px;border-radius:99px">
                                <?php echo count($caseNotes); ?> Note<?php echo count($caseNotes) !== 1 ? 's' : ''; ?>
                            </span>
                        </div>

                        <!-- Notes list (scrollable) -->
                        <div id="notesList" style="display:flex;flex-direction:column;gap:8px;padding:12px;max-height:320px;overflow-y:auto">
                            <?php if (!empty($caseNotes)): ?>
                                <?php foreach ($caseNotes as $cn):
                                    $cnRole = strtoupper($cn['user_role'] ?? 'NURSE');
                                    $cnRoleLabel = $cnRole === 'DOCTOR' ? 'Doctor' : 'Nurse';
                                    $cnColor = $cnRole === 'DOCTOR' ? '#7c3aed' : '#1e40af';
                                    $cnBg    = $cnRole === 'DOCTOR' ? '#f5f3ff' : '#eff6ff';
                                    $cnBorder= $cnRole === 'DOCTOR' ? '#ddd6fe' : '#bfdbfe';
                                ?>
                                <div class="note-bubble" style="background:<?php echo $cnBg; ?>;border:1px solid <?php echo $cnBorder; ?>;border-radius:10px;padding:10px 12px">
                                    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:4px;margin-bottom:6px">
                                        <div style="display:flex;align-items:center;gap:6px">
                                            <div style="width:22px;height:22px;border-radius:50%;background:<?php echo $cnColor; ?>;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                                                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                                            </div>
                                            <div>
                                                <div style="font-size:11px;font-weight:700;color:#0f172a"><?php echo htmlspecialchars($cn['user_name'] ?? $cnRoleLabel); ?></div>
                                                <div style="font-size:9px;font-weight:700;text-transform:uppercase;color:<?php echo $cnColor; ?>;letter-spacing:0.4px"><?php echo $cnRoleLabel; ?></div>
                                            </div>
                                        </div>
                                        <div style="font-size:9px;color:#94a3b8"><?php echo date('d M, h:i A', strtotime($cn['created_at'])); ?></div>
                                    </div>
                                    <p style="font-size:12px;color:#334155;line-height:1.5;margin:0;white-space:pre-wrap"><?php echo htmlspecialchars($cn['note']); ?></p>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                            <div id="noNotesMsg" style="text-align:center;padding:16px;color:#94a3b8">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="opacity:0.4;margin-bottom:6px"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                                <div style="font-size:11px;font-weight:600;color:#64748b">No notes yet</div>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Add note input -->
                        <?php if (!$isCaseComplete): ?>
                        <div style="padding:12px;border-top:1px solid #f1f5f9;background:#fafbfc">
                            <form id="addNoteForm" onsubmit="submitNote(event)">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                                <textarea id="noteInput"
                                          name="single_note"
                                          rows="2"
                                          placeholder="Add a clinical note (e.g. BP: 128/82, SpO2: 97%...)" 
                                          style="width:100%;border:1px solid #e2e8f0;border-radius:7px;padding:8px 10px;font-size:12px;font-family:inherit;resize:vertical;color:#334155;outline:none;transition:border-color 0.2s;box-sizing:border-box"
                                          onfocus="this.style.borderColor='#396cf0'" onblur="this.style.borderColor='#e2e8f0'"></textarea>
                                <div style="display:flex;justify-content:flex-end;margin-top:8px">
                                    <button type="submit" id="submitNoteBtn" style="background:linear-gradient(135deg,#396cf0,#1c58ef);color:#fff;border:none;font-size:12px;font-weight:700;padding:7px 18px;border-radius:7px;cursor:pointer;display:flex;align-items:center;gap:5px">
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                                        Post Note
                                    </button>
                                </div>
                            </form>
                        </div>
                        <?php else: ?>
                        <div style="padding:10px 14px;background:#f8fafc;border-top:1px solid #f1f5f9;text-align:center;font-size:11px;color:#94a3b8;display:flex;align-items:center;justify-content:center;gap:5px">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                            Notes archived &amp; locked
                        </div>
                        <?php endif; ?>
                    </div>

                </div>
            </div>

    </div>
</main>

<script>
// ── AJAX Note Submission ──
function submitNote(e) {
    e.preventDefault();
    const textarea = document.getElementById('noteInput');
    const noteText = textarea.value.trim();
    if (!noteText) { textarea.focus(); return; }

    const btn = document.getElementById('submitNoteBtn');
    btn.disabled = true;
    btn.innerHTML = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="animation:spin 1s linear infinite"><line x1="12" y1="2" x2="12" y2="6"/><line x1="12" y1="18" x2="12" y2="22"/><line x1="4.93" y1="4.93" x2="7.76" y2="7.76"/><line x1="16.24" y1="16.24" x2="19.07" y2="19.07"/></svg> Posting...';

    fetch('/BPUT/api/nurse.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'add_note',
            case_id: <?php echo $caseId; ?>,
            note: noteText
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success && data.note) {
            appendNoteToStream(data.note);
            textarea.value = '';
        } else {
            alert(data.error || 'Failed to post note. Please try again.');
        }
    })
    .catch(() => alert('Network error. Please check connection.'))
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg> Post Note';
    });
}

function appendNoteToStream(note) {
    const list = document.getElementById('notesList');
    const noMsg = document.getElementById('noNotesMsg');
    if (noMsg) noMsg.remove();

    const roleLabel = note.user_role === 'DOCTOR' ? 'Doctor' : 'Nurse';
    const color  = note.user_role === 'DOCTOR' ? '#7c3aed' : '#1e40af';
    const bg     = note.user_role === 'DOCTOR' ? '#f5f3ff' : '#eff6ff';
    const border = note.user_role === 'DOCTOR' ? '#ddd6fe' : '#bfdbfe';

    const div = document.createElement('div');
    div.className = 'note-bubble';
    div.style.cssText = `background:${bg};border:1px solid ${border};border-radius:12px;padding:14px 16px;animation:fadeSlideIn 0.3s ease`;
    div.innerHTML = `
        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:6px;margin-bottom:8px">
            <div style="display:flex;align-items:center;gap:8px">
                <div style="width:30px;height:30px;border-radius:50%;background:${color};display:flex;align-items:center;justify-content:center;flex-shrink:0">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                </div>
                <div>
                    <div style="font-size:13px;font-weight:700;color:#0f172a">${escapeHtml(note.user_name || roleLabel)}</div>
                    <div style="font-size:10px;font-weight:700;text-transform:uppercase;color:${color};letter-spacing:0.5px">${roleLabel}</div>
                </div>
            </div>
            <div style="font-size:11px;color:#94a3b8;display:flex;align-items:center;gap:4px">
                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                ${escapeHtml(note.formatted_date || 'Just now')}
            </div>
        </div>
        <p style="font-size:13px;color:#334155;line-height:1.6;margin:0;white-space:pre-wrap">${escapeHtml(note.note)}</p>
    `;
    list.appendChild(div);
    div.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

function escapeHtml(str) {
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
</script>
<style>
@keyframes fadeSlideIn {
    from { opacity: 0; transform: translateY(8px); }
    to   { opacity: 1; transform: translateY(0); }
}
@keyframes spin {
    from { transform: rotate(0deg); }
    to   { transform: rotate(360deg); }
}
</style>

<!-- Lightbox Modal for Attached Image Inspection -->
<div class="medgem-modal" id="imageModal" onclick="closeImageModal(event)">
    <div class="medgem-modal__dialog" onclick="event.stopPropagation()">
        <div class="medgem-modal__header">
            <span class="medgem-modal__title" id="imageModalTitle">Medical Report Preview</span>
            <button type="button" class="medgem-modal__close" onclick="closeImageModal()">&times;</button>
        </div>
        <div class="medgem-modal__body">
            <img src="" id="imageModalImg" class="medgem-modal__img" alt="Enlarged Report">
        </div>
    </div>
</div>

<!-- ═══ Referral Modal ═══ -->
<div id="referralModal" class="modal-overlay hidden" onclick="if(event.target === this) closeReferralModal()">
  <div class="modal-box">
    <div class="modal-head">
      <h3>Referral Summary Note</h3>
      <button type="button" id="referralClose" class="modal-close-btn" onclick="closeReferralModal()">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="modal-body"><pre id="referralText"><?php echo htmlspecialchars($referralNoteText); ?></pre></div>
    <div class="modal-foot">
      <button type="button" id="copyReferralBtn" class="btn-copy" onclick="copyReferralNote()">Copy to Clipboard</button>
    </div>
  </div>
</div>

<script>
// Report Panel Tab Controller (aiModel style)
function switchReportTab(tabKey) {
    document.querySelectorAll('#reportPanel .tab').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.tab === tabKey);
    });
    document.querySelectorAll('#reportPanel .tab-body').forEach(body => {
        body.classList.toggle('hidden', body.id !== ('tab-' + tabKey));
    });
}

function openReferralModal() {
    const modal = document.getElementById('referralModal');
    if (modal) modal.classList.remove('hidden');
}

function closeReferralModal() {
    const modal = document.getElementById('referralModal');
    if (modal) modal.classList.add('hidden');
}

function copyReferralNote() {
    const text = document.getElementById('referralText').innerText;
    navigator.clipboard.writeText(text).then(() => {
        const btn = document.getElementById('copyReferralBtn');
        if (btn) {
            const old = btn.textContent;
            btn.textContent = 'Copied to Clipboard!';
            setTimeout(() => { btn.textContent = old; }, 2200);
        }
    });
}

// Lightbox Modal for Attached Images
function openImageModal(imgSrc, title) {
    const modal = document.getElementById('imageModal');
    const modalImg = document.getElementById('imageModalImg');
    const modalTitle = document.getElementById('imageModalTitle');
    
    if (modal && modalImg) {
        modalImg.src = imgSrc;
        if (modalTitle) modalTitle.textContent = title || 'Medical Report Preview';
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
}

function closeImageModal() {
    const modal = document.getElementById('imageModal');
    if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = '';
    }
}

document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        closeImageModal();
        closeReferralModal();
    }
});
</script>

<script src="/BPUT/assets/js/app.js"></script>
</body>
</html>
