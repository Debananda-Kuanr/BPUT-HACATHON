<?php
/**
 * MEDGem — Doctor Clinical Case Review
 * Physician evaluation cockpit featuring comprehensive tabbed clinical dossier:
 * 1. FIRST: Patient Demographics & Registration Overview
 * 2. SECOND: Attached Medical Images & Reports Gallery (with Fullscreen Lightbox & Nurse Verification)
 * 3. THIRD: Paginated Clinical Dossier with Left/Right arrows & Prev/Next controls
 * 4. Strictly READ-ONLY Intake & Nurse Records
 * 5. Physician Clinical Disposition (Complete vs Specialist Referral)
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/security.php';
setSecurityHeaders();
requireAuth('DOCTOR');

$user = getCurrentUser();
$caseId = (int) ($_GET['id'] ?? 0);

if ($caseId <= 0) {
    header('Location: /BPUT/doctor/dashboard.php');
    exit;
}

$case = getFullCase($caseId);
if (!$case) {
    header('Location: /BPUT/doctor/dashboard.php');
    exit;
}

// Update status to DOCTOR_REVIEW if arriving from ESCALATED
if ($case['status'] === 'ESCALATED') {
    updateCaseStatus($caseId, 'DOCTOR_REVIEW', $user['id']);
    $case['status'] = 'DOCTOR_REVIEW';
}

// Handle POST actions (Physician decisions only — cannot modify intake/nurse answers)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $notes = trim($_POST['notes'] ?? '');
    $referralNote = trim($_POST['referral_note'] ?? '');
    $db = getDB();
    
    if ($action === 'complete') {
        if ($notes === '') {
            $notes = 'Clinical evaluation completed by attending physician.';
        }
        $stmt = $db->prepare("INSERT INTO doctor_reviews (case_id, doctor_id, notes, decision) VALUES (?, ?, ?, 'COMPLETED')");
        $stmt->execute([$caseId, $user['id'], $notes]);
        updateCaseStatus($caseId, 'COMPLETED', $user['id']);
        addAuditLog($caseId, $user['id'], 'DOCTOR_COMPLETED', 'Doctor evaluated case and issued clinical directives');
        header('Location: /BPUT/doctor/dashboard.php');
        exit;
    }
    
    if ($action === 'refer') {
        if ($notes === '') {
            $notes = 'Specialist referral ordered by attending physician.';
        }
        $stmt = $db->prepare("INSERT INTO doctor_reviews (case_id, doctor_id, notes, decision, referral_note) VALUES (?, ?, ?, 'REFERRED', ?)");
        $stmt->execute([$caseId, $user['id'], $notes, $referralNote]);
        updateCaseStatus($caseId, 'REFERRED', $user['id']);
        addAuditLog($caseId, $user['id'], 'DOCTOR_REFERRED', 'Doctor approved referral for specialist care');
        header('Location: /BPUT/doctor/dashboard.php');
        exit;
    }
}

$triage = $case['triage'] ?? [];
$signals = $case['signals'] ?? [];
$conversations = $case['conversations'] ?? [];
$reports = $case['reports'] ?? [];
$nurseReviews = $case['nurse_reviews'] ?? [];
$auditLogs = $case['audit_logs'] ?? [];
$caseNotes = $case['notes'] ?? getCaseNotes($caseId);

// Case completion state (doctor can always add notes, even on completed cases)
$isCaseComplete = in_array(strtoupper($case['status']), ['COMPLETED', 'REFERRED', 'CLEARED']);

// Latest nurse review if available
$latestNurseReview = !empty($nurseReviews) ? $nurseReviews[0] : null;

// Collect answered questions from nurse
$nurseAnswers = [];
if ($latestNurseReview && !empty($latestNurseReview['answered_questions'])) {
    $nurseAnswers = $latestNurseReview['answered_questions'];
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
    <title>Doctor Review — Case <?php echo sanitize($case['track_id']); ?> — MEDGem</title>
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
        .ro-tag {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 11px;
            font-weight: 700;
            background: #e2e8f0;
            color: #475569;
            padding: 4px 10px;
            border-radius: var(--radius-full);
            text-transform: uppercase;
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
    Synthetic Clinical Data &middot; Educational Prototype &middot; Physician Workspace
</div>

<nav class="navbar">
    <div class="navbar__inner">
        <a href="/BPUT/" class="navbar__logo">
            <span class="navbar__logo-icon" style="background:linear-gradient(135deg, #396cf0 0%, #1c58ef 100%);border-radius:10px">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
            </span>
            <span style="font-weight:800;font-family:var(--font-heading, 'Raleway', sans-serif);color:#0f172a">MEDGem<span style="color:#396cf0">Care</span></span>
        </a>
        <div class="flex items-center gap-3">
            <span class="navbar__link hide-mobile" style="cursor:default;font-weight:600;color:var(--neutral-800)">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline;vertical-align:-2px;margin-right:4px"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                <?php echo htmlspecialchars($user['name']); ?> (Physician)
            </span>
            <a href="/BPUT/staff/login.php?logout=1" class="btn btn--sm" style="color:var(--danger-600);background:var(--danger-50);border:1px solid var(--danger-200);font-weight:600;display:inline-flex;align-items:center;gap:6px">
                <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                Logout
            </a>
        </div>
    </div>
</nav>

<main class="dashboard-main" style="margin-left:0;padding-top:100px;padding-bottom:60px">
    <div class="container container--xl">
        
        <a href="/BPUT/doctor/dashboard.php" class="back-link" style="margin-bottom:16px;display:inline-flex;align-items:center;gap:6px">
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
                    <span class="ro-tag" title="Physicians cannot edit patient intake or nurse records">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                        Intake Read-Only
                    </span>
                </div>
                <p class="dash-header__subtitle" style="margin-top:6px">
                    <?php echo sanitize($case['name']); ?> &middot; <?php echo $case['age']; ?> yrs &middot; <?php echo $case['sex']; ?> &middot; <?php echo $case['preferred_language']; ?>
                </p>
            </div>
            <div style="display:flex;gap:var(--space-2);align-items:center;flex-wrap:wrap">
                <span class="badge badge--<?php echo !empty($signals) ? 'danger' : 'info'; ?>">
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
                    <span>1. Patient Demographics &amp; Intake Overview</span>
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
                <div class="label">Primary Chief Complaint &middot; Intake Baseline</div>
                <div class="text"><?php echo sanitize($complaintText); ?></div>
            </div>
        </div>

        <!-- ====================================================================
             2. SECOND: ALL ATTACHED IMAGES & MEDICAL REPORTS (WITH NURSE VERIFICATION)
             ==================================================================== -->
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
                    Nurse verified reports &amp; clinical findings for physician evaluation
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
                    <div class="attached-img-wrap" onclick="openDoctorImageModal('/BPUT/<?php echo htmlspecialchars($fPath); ?>', '<?php echo htmlspecialchars(addslashes($rep['file_name'])); ?>')">
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
                                <span class="nurse-verify-pill-relevant">✓ Verified by Nurse (<?php echo sanitize($ev['nurse_verified_by'] ?? 'Nurse'); ?>)</span>
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

                        <!-- Nurse Note if present -->
                        <?php if (!empty($ev['nurse_note'])): ?>
                        <div style="margin:8px 0;padding:8px 12px;background:#f0fdfa;border:1px solid #ccfbf1;border-radius:var(--radius-md);font-size:12px;color:#0f766e">
                            <strong>Nurse Verification Note:</strong> <?php echo sanitize($ev['nurse_note']); ?>
                        </div>
                        <?php endif; ?>

                        <!-- Lab Findings Table -->
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
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div style="padding:24px;background:#f8fafc;border-radius:var(--radius-lg);text-align:center;color:var(--neutral-500)">
                <div style="font-size:var(--text-sm);font-weight:600;color:var(--neutral-700)">No medical documents or report images uploaded</div>
                <div style="font-size:var(--text-xs);margin-top:4px">Patient did not attach external test slips for this episode.</div>
            </div>
            <?php endif; ?>
        </div>

        <!-- ====================================================================
             3. THIRD: PAGINATED CLINICAL DOSSIER & PHYSICIAN REVIEW
             ==================================================================== -->
        <div class="review-grid">
            <!-- Left Column: Comprehensive Clinical Dossier with Tabs -->
            <div>
                <!-- Standardized Triage Rule Notice Banner -->
                <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-left:4px solid #0b8a63;border-radius:var(--radius-md);padding:12px 16px;margin-bottom:var(--space-4);font-size:var(--text-xs);line-height:1.5;color:#166534">
                    <div style="font-weight:700;display:flex;align-items:center;gap:6px;margin-bottom:2px;text-transform:uppercase;letter-spacing:0.5px">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                        Triage Safety Protocol (Red / Yellow / Green)
                    </div>
                    <span>The AI’s color-coded prioritization (Red/Yellow/Green) is merely a sorting mechanism for the nurse's queue. It does not dictate final medical urgency, which is always overwritten by the human nurse's physical assessment.</span>
                </div>

                <!-- Safety Signals Banner if Present -->
                <?php if (!empty($signals)): ?>
                <?php foreach ($signals as $signal): ?>
                <div class="signal-card" style="margin-bottom:var(--space-4)">
                    <div class="signal-card__header">
                        <div class="signal-card__icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                        </div>
                        <span class="signal-card__title">Safety Signal &middot; <?php echo sanitize($signal['rule_id']); ?></span>
                    </div>
                    <div class="signal-card__body"><?php echo sanitize($signal['signal']); ?></div>
                    <dl class="signal-card__meta">
                        <dt>Source</dt><dd><?php echo sanitize($signal['source']); ?></dd>
                        <dt>Clinical Reason</dt><dd><?php echo sanitize($signal['reason']); ?></dd>
                    </dl>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>

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
                            <button type="button" class="tab" data-tab="missing" onclick="switchReportTab('missing')">Missing Info &amp; Nurse Answers</button>
                            <button type="button" class="tab" data-tab="questions" onclick="switchReportTab('questions')">Questions</button>
                            <button type="button" class="tab" data-tab="urgency" onclick="switchReportTab('urgency')">Urgency</button>
                        </nav>

                        <!-- Tab 1: Summary -->
                        <div id="tab-summary" class="tab-body">
                            <div class="r-section">
                                <div class="r-meta">
                                    <span class="chip">Language: <?php echo htmlspecialchars($case['preferred_language']); ?></span>
                                    <span class="chip">Age: <?php echo $case['age']; ?></span>
                                    <span class="chip">Gender: <?php echo htmlspecialchars($case['sex']); ?></span>
                                    <span class="chip">Intake Mode: <?php echo htmlspecialchars($case['communication_mode'] ?? 'TEXT'); ?></span>
                                </div>
                            </div>
                            <div class="r-section chief">
                                <h4>CHIEF COMPLAINT</h4>
                                <p><strong><?php echo sanitize($complaintText); ?></strong></p>
                            </div>
                            <div class="r-section">
                                <h4>CLINICAL SUMMARY (ENGLISH)</h4>
                                <p><?php echo nl2br(sanitize($triage['summary'] ?? 'Standard intake completed.')); ?></p>
                            </div>

                            <!-- Nurse Notes Callout in Summary if verified -->
                            <?php if ($latestNurseReview): ?>
                            <div style="margin-top:14px;padding:12px 16px;background:#f0fdfa;border:1px solid #ccfbf1;border-radius:10px">
                                <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px">
                                    <span class="finding-flag flag-normal" style="font-size:10px;margin-left:0">&check; Nurse Verified</span>
                                    <strong style="font-size:12px;color:#0f766e"><?php echo htmlspecialchars($latestNurseReview['nurse_name']); ?></strong>
                                    <span style="font-size:11px;color:#64748b"><?php echo timeAgo($latestNurseReview['created_at']); ?></span>
                                </div>
                                <p style="font-size:13px;color:#0f766e;margin:0;line-height:1.5"><?php echo nl2br(sanitize($latestNurseReview['notes'])); ?></p>
                            </div>
                            <?php endif; ?>

                            <div class="r-disclaimer">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                                <span>AI-assisted triage support only. Not a definitive medical diagnosis. Must be reviewed by qualified medical personnel.</span>
                            </div>
                        </div>

                        <!-- Tab 2: Symptoms -->
                        <div id="tab-symptoms" class="tab-body hidden">
                            <?php if (!empty($symptomList)): ?>
                                <?php foreach ($symptomList as $s): 
                                    $sName = is_array($s) ? ($s['name'] ?? $s['symptom'] ?? 'Symptom') : (string)$s;
                                    $sSev = is_array($s) ? ($s['severity'] ?? 'MODERATE') : 'MODERATE';
                                    $sDur = is_array($s) ? ($s['duration'] ?? 'Reported at intake') : 'Active';
                                    $sNotes = is_array($s) ? ($s['notes'] ?? $s['details'] ?? 'Patient reported symptom.') : 'Patient reported symptom.';
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
                                <p class="empty-tab">No structured symptoms detected.</p>
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
                                    $isRelevant = $ev['is_relevant'] ?? true;
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
                                    <?php if ($isRelevant): ?>
                                    &middot; <span style="color:#0f766e;font-weight:600">&check; Relevant</span>
                                    <?php else: ?>
                                    &middot; <span style="color:#b45309;font-weight:600">&cross; Outdated / Ignored</span>
                                    <?php endif; ?>
                                    <?php if (!empty($ev['nurse_note'])): ?>
                                    &middot; <span style="color:#0f766e">Nurse Note: <?php echo sanitize($ev['nurse_note']); ?></span>
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
                            <p class="empty-tab ok">No laboratory findings or medical reports uploaded.</p>
                            <?php endif; ?>
                        </div>

                        <!-- Tab 5: Missing Info & Nurse Answers -->
                        <div id="tab-missing" class="tab-body hidden">
                            <!-- Show Overall Nurse Assessment Notes First -->
                            <?php if ($latestNurseReview): ?>
                            <div style="margin-bottom:16px;padding:12px 16px;background:#f0fdfa;border:1px solid #ccfbf1;border-radius:10px">
                                <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px">
                                    <span class="finding-flag flag-normal" style="font-size:10px;margin-left:0">&check; Primary Nurse Assessment</span>
                                    <strong style="font-size:12px;color:#0f766e"><?php echo htmlspecialchars($latestNurseReview['nurse_name']); ?></strong>
                                    <span style="font-size:11px;color:#64748b"><?php echo timeAgo($latestNurseReview['created_at']); ?></span>
                                </div>
                                <p style="font-size:13px;color:#0f766e;margin:0;line-height:1.5"><?php echo nl2br(sanitize($latestNurseReview['notes'])); ?></p>
                            </div>
                            <?php endif; ?>

                            <?php if (!empty($nurseAnswers)): ?>
                            <div style="margin-bottom:12px;font-size:12px;font-weight:700;color:#0f766e;display:flex;align-items:center;gap:6px">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                                Follow-up answers gathered directly from patient by attending nurse:
                            </div>
                            <ul class="miss-list" style="margin-bottom:16px">
                                <?php foreach ($nurseAnswers as $qa): ?>
                                <li style="flex-direction:column;align-items:flex-start;gap:4px">
                                    <div style="display:flex;align-items:center;gap:6px;font-weight:600;color:#1e293b">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                                        <span><?php echo sanitize($qa['question'] ?? ''); ?></span>
                                    </div>
                                    <div style="padding:6px 10px;background:#f8fafc;border-left:3px solid #0f766e;border-radius:4px;color:#0f766e;font-size:12px;width:100%">
                                        <strong>Answer:</strong> <?php echo !empty($qa['answer']) ? sanitize($qa['answer']) : '<em style="color:#94a3b8">Unanswered</em>'; ?>
                                    </div>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                            <?php elseif (!empty($missingItems)): ?>
                            <ul class="miss-list">
                                <?php foreach ($missingItems as $mi): ?>
                                <li>
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                                    <span><?php echo sanitize(is_string($mi) ? $mi : json_encode($mi)); ?></span>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                            <?php else: ?>
                            <p class="empty-tab ok">All clinical details accounted for. No missing information flagged.</p>
                            <?php endif; ?>
                        </div>

                        <!-- Tab 6: Questions -->
                        <div id="tab-questions" class="tab-body hidden">
                            <?php 
                            $questionsList = $triage['followup_questions'] ?? $triage['follow_up_questions'] ?? $triage['recommended_questions'] ?? $missingItems;
                            if (!empty($questionsList)):
                                foreach ($questionsList as $qIdx => $q):
                                    $qText = is_string($q) ? $q : ($q['question_en'] ?? $q['question'] ?? 'Question');
                                    $qReason = is_array($q) ? ($q['reason'] ?? '') : 'Differential diagnosis & risk stratifying inquiry';
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
                            <p class="empty-tab">No follow-up questions formulated.</p>
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
                                    <p class="sig-action"><strong>Source:</strong> <?php echo sanitize($sig['source']); ?> &middot; Prompt physician intervention required.</p>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                            <p class="empty-tab ok">No acute urgency signals triggered.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </section>
            </div>

            <!-- Right Column: Physician Clinical Decision -->
            <div class="action-sidebar">
                <form method="POST">
                    <?php echo csrfField(); ?>
                    
                    <div class="action-card">
                        <div class="action-card__title">Physician Evaluation &amp; Orders</div>

                        <!-- Optional Specialist Referral Note -->
                        <div class="form-group" id="referralNoteGroup" style="display:none;margin-top:14px;padding:14px;background:#fef2f2;border:1px solid #fecaca;border-radius:var(--radius-lg)">
                            <label class="form-label" style="color:var(--danger-700);font-weight:700">Specialist Referral Note (Patient visible)</label>
                            <textarea class="form-textarea" 
                                      id="referralNote"
                                      name="referral_note" 
                                      placeholder="Reason for specialist referral, suggested specialty (e.g. Cardiology, Neurology), urgent flags..." 
                                      style="min-height:90px;font-size:var(--text-xs);width:100%"></textarea>
                        </div>

                        <div class="action-buttons">
                            <button type="submit" name="action" value="complete" class="btn btn--primary btn--full" style="padding:12px;font-weight:700">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                Complete Case (Discharge / Advice)
                            </button>
                            <button type="button" id="toggleReferBtn" onclick="toggleReferral()" class="btn btn--outline btn--full" style="border-color:var(--danger-400);color:var(--danger-600);padding:12px;font-weight:700">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="17 11 12 6 7 11"/><polyline points="17 18 12 13 7 18"/></svg>
                                Refer to Specialist
                            </button>
                        </div>
                    </div>
                </form>

                <!-- ── Clinical Notes Stream (inside sidebar) ── -->
                <div id="notesStreamSection" class="action-card" style="padding:0;overflow:hidden;border:1px solid #e2e8f0;margin-top:16px">
                        <!-- Header -->
                        <div style="display:flex;align-items:center;justify-content:space-between;padding:14px 16px;border-bottom:1px solid #f1f5f9;background:#f8fafc">
                            <div style="display:flex;align-items:center;gap:8px">
                                <div style="background:linear-gradient(135deg,#7c3aed,#5b21b6);border-radius:8px;width:28px;height:28px;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                                </div>
                                <div>
                                    <div style="font-size:13px;font-weight:800;color:#0f172a">Clinical Notes</div>
                                    <div style="font-size:10px;color:#64748b">Timestamped case record</div>
                                </div>
                            </div>
                            <span style="background:#f3e8ff;color:#7c3aed;border:1px solid #ddd6fe;font-size:10px;font-weight:700;padding:3px 8px;border-radius:99px">
                                <?php echo count($caseNotes); ?> Note<?php echo count($caseNotes) !== 1 ? 's' : ''; ?>
                            </span>
                        </div>

                        <!-- Notes list -->
                        <div id="docNotesList" style="display:flex;flex-direction:column;gap:8px;padding:12px;max-height:320px;overflow-y:auto">
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
                            <div id="docNoNotesMsg" style="text-align:center;padding:16px;color:#94a3b8">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="opacity:0.4;margin-bottom:6px"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                                <div style="font-size:11px;font-weight:600;color:#64748b">No notes yet</div>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Add Note Form -->
                        <div style="padding:12px;border-top:1px solid #f1f5f9;background:#fafbfc">
                            <?php if ($isCaseComplete): ?>
                            <div style="background:#fef9c3;border:1px solid #fde047;border-radius:6px;padding:6px 10px;font-size:10px;color:#713f12;margin-bottom:8px;display:flex;align-items:center;gap:4px">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                                Case completed — follow-up notes allowed.
                            </div>
                            <?php endif; ?>
                            <form id="docAddNoteForm" onsubmit="docSubmitNote(event)">
                                <textarea id="docNoteInput"
                                          rows="2"
                                          placeholder="Physician note (e.g. ECG reviewed — sinus rhythm...)" 
                                          style="width:100%;border:1px solid #e2e8f0;border-radius:7px;padding:8px 10px;font-size:12px;font-family:inherit;resize:vertical;color:#334155;outline:none;transition:border-color 0.2s;box-sizing:border-box"
                                          onfocus="this.style.borderColor='#7c3aed'" onblur="this.style.borderColor='#e2e8f0'"></textarea>
                                <div style="display:flex;justify-content:flex-end;margin-top:8px">
                                    <button type="submit" id="docSubmitNoteBtn" style="background:linear-gradient(135deg,#7c3aed,#5b21b6);color:#fff;border:none;font-size:12px;font-weight:700;padding:7px 18px;border-radius:7px;cursor:pointer;display:flex;align-items:center;gap:5px">
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                                        Post Note
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
            </div>
        </div>

    </div>

    </div>
</main>

<!-- Lightbox Modal for Attached Image Inspection -->
<div class="medgem-modal" id="docImageModal" onclick="closeDoctorImageModal(event)">
    <div class="medgem-modal__dialog" onclick="event.stopPropagation()">
        <div class="medgem-modal__header">
            <span class="medgem-modal__title" id="docImageModalTitle">Medical Report Preview</span>
            <button type="button" class="medgem-modal__close" onclick="closeDoctorImageModal()">&times;</button>
        </div>
        <div class="medgem-modal__body">
            <img src="" id="docImageModalImg" class="medgem-modal__img" alt="Enlarged Report">
        </div>
    </div>
</div>

<!-- Referral Note Modal (aiModel style) -->
<div id="referralModal" class="modal-overlay hidden">
  <div class="modal-box">
    <div class="modal-head">
      <h3>Referral Note</h3>
      <button type="button" class="btn-close" onclick="closeReferralModal()">&times;</button>
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

// Lightbox modal for attached images
function openDoctorImageModal(imgSrc, title) {
    const modal = document.getElementById('docImageModal');
    const img = document.getElementById('docImageModalImg');
    const titleElem = document.getElementById('docImageModalTitle');
    if (modal && img) {
        img.src = imgSrc;
        if (titleElem) titleElem.textContent = title || 'Medical Report Preview';
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
}

function closeDoctorImageModal() {
    const modal = document.getElementById('docImageModal');
    if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = '';
    }
}

document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closeDoctorImageModal();
});

let isReferralOpen = false;
function toggleReferral() {
    const grp = document.getElementById('referralNoteGroup');
    const noteArea = document.getElementById('referralNote');
    const btn = document.getElementById('toggleReferBtn');
    
    isReferralOpen = !isReferralOpen;
    if (isReferralOpen) {
        grp.style.display = 'block';
        btn.innerHTML = `<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg> Submit Specialist Referral`;
        btn.className = 'btn btn--danger btn--full';
        btn.type = 'submit';
        btn.name = 'action';
        btn.value = 'refer';
        
        if (!noteArea.value.trim()) {
            noteArea.value = `REFERRAL NOTE\nCase ID: <?php echo sanitize($case['track_id']); ?>\nPatient: <?php echo sanitize($case['name']); ?> (<?php echo $case['age']; ?>y / <?php echo $case['sex']; ?>)\nReason for Referral: Specialized clinical investigation & care.\nSummary: <?php echo sanitize(substr($triage['summary'] ?? '', 0, 160)); ?>\nUrgency: <?php echo !empty($signals) ? 'Urgent' : 'Routine'; ?>`;
        }
        noteArea.focus();
    } else {
        grp.style.display = 'none';
        btn.innerHTML = `<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="17 11 12 6 7 11"/></svg> Refer to Specialist`;
        btn.className = 'btn btn--outline btn--full';
        btn.type = 'button';
        btn.name = '';
    }
}

// ── Doctor AJAX Note Submission ──
function docSubmitNote(e) {
    e.preventDefault();
    const textarea = document.getElementById('docNoteInput');
    const noteText = textarea.value.trim();
    if (!noteText) { textarea.focus(); return; }

    const btn = document.getElementById('docSubmitNoteBtn');
    btn.disabled = true;
    btn.textContent = 'Posting...';

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
            docAppendNote(data.note);
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

function docAppendNote(note) {
    const list = document.getElementById('docNotesList');
    const noMsg = document.getElementById('docNoNotesMsg');
    if (noMsg) noMsg.remove();

    const roleLabel = note.user_role === 'DOCTOR' ? 'Doctor' : 'Nurse';
    const color  = note.user_role === 'DOCTOR' ? '#7c3aed' : '#1e40af';
    const bg     = note.user_role === 'DOCTOR' ? '#f5f3ff' : '#eff6ff';
    const border = note.user_role === 'DOCTOR' ? '#ddd6fe' : '#bfdbfe';

    const div = document.createElement('div');
    div.style.cssText = `background:${bg};border:1px solid ${border};border-radius:12px;padding:14px 16px;animation:docFadeIn 0.3s ease`;
    div.innerHTML = `
        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:6px;margin-bottom:8px">
            <div style="display:flex;align-items:center;gap:8px">
                <div style="width:30px;height:30px;border-radius:50%;background:${color};display:flex;align-items:center;justify-content:center;flex-shrink:0">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                </div>
                <div>
                    <div style="font-size:13px;font-weight:700;color:#0f172a">${docEscHtml(note.user_name || roleLabel)}</div>
                    <div style="font-size:10px;font-weight:700;text-transform:uppercase;color:${color};letter-spacing:0.5px">${roleLabel}</div>
                </div>
            </div>
            <div style="font-size:11px;color:#94a3b8;display:flex;align-items:center;gap:4px">
                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                ${docEscHtml(note.formatted_date || 'Just now')}
            </div>
        </div>
        <p style="font-size:13px;color:#334155;line-height:1.6;margin:0;white-space:pre-wrap">${docEscHtml(note.note)}</p>
    `;
    list.appendChild(div);
    div.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

function docEscHtml(str) {
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
</script>
<style>
@keyframes docFadeIn {
    from { opacity: 0; transform: translateY(8px); }
    to   { opacity: 1; transform: translateY(0); }
}
</style>

<script src="/BPUT/assets/js/app.js"></script>
</body>
</html>
