<?php
/**
 * MEDGem — Core Utility Functions
 * Shared helpers for case management, track IDs, audit logs, etc.
 */

require_once __DIR__ . '/db.php';

/**
 * Generate a unique track ID like SYN-1042
 */
function generateTrackID(): string {
    $db = getDB();
    do {
        $num = rand(1000, 9999);
        $trackId = 'SYN-' . $num;
        $stmt = $db->prepare("SELECT id FROM cases WHERE track_id = ?");
        $stmt->execute([$trackId]);
    } while ($stmt->fetch());
    return $trackId;
}

/**
 * Create a new patient case
 */
function createCase(array $data): array {
    $db = getDB();
    $trackId = generateTrackID();
    
    $stmt = $db->prepare("
        INSERT INTO cases (track_id, name, age, contact_number, sex, preferred_language, consent, status, communication_mode)
        VALUES (?, ?, ?, ?, ?, ?, 1, 'NEW', ?)
    ");
    $stmt->execute([
        $trackId,
        $data['name'],
        $data['age'],
        $data['contact_number'] ?? '',
        $data['sex'],
        $data['preferred_language'] ?? 'English',
        $data['communication_mode'] ?? 'TEXT'
    ]);
    
    $caseId = $db->lastInsertId();
    addAuditLog($caseId, null, 'CASE_CREATED', 'Patient submitted case');
    
    return [
        'id'       => $caseId,
        'track_id' => $trackId
    ];
}

/**
 * Get case by track ID
 */
function getCaseByTrackId(string $trackId): ?array {
    $db = getDB();
    $stmt = $db->prepare("SELECT id FROM cases WHERE track_id = ?");
    $stmt->execute([$trackId]);
    $row = $stmt->fetch();
    if (!$row) return null;
    return getFullCase((int)$row['id']);
}

/**
 * Get case by ID with all related data
 */
function getFullCase(int $caseId): ?array {
    $db = getDB();
    
    // Base case
    $stmt = $db->prepare("SELECT * FROM cases WHERE id = ?");
    $stmt->execute([$caseId]);
    $case = $stmt->fetch();
    if (!$case) return null;
    
    // Conversations
    $stmt = $db->prepare("SELECT * FROM conversations WHERE case_id = ? ORDER BY timestamp ASC");
    $stmt->execute([$caseId]);
    $case['conversations'] = $stmt->fetchAll();
    
    // Reports
    $stmt = $db->prepare("SELECT * FROM reports WHERE case_id = ? ORDER BY created_at ASC");
    $stmt->execute([$caseId]);
    $reports = $stmt->fetchAll();
    foreach ($reports as &$rep) {
        if (!empty($rep['extracted_values']) && is_string($rep['extracted_values'])) {
            $rep['extracted_values'] = json_decode($rep['extracted_values'], true);
        }
        $rep = normalizeReportData($rep);
    }
    unset($rep);
    $case['reports'] = $reports;
    
    // Triage results
    $stmt = $db->prepare("SELECT * FROM triage_results WHERE case_id = ? ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$caseId]);
    $triage = $stmt->fetch();
    if ($triage) {
        $triage['symptoms'] = json_decode($triage['symptoms'], true);
        $triage['timeline'] = json_decode($triage['timeline'], true);
        $triage['missing_information'] = json_decode($triage['missing_information'], true);
        $triage['followup_questions'] = json_decode($triage['followup_questions'], true);
    }
    $case['triage'] = $triage;
    
    // Urgency signals
    $stmt = $db->prepare("SELECT * FROM urgency_signals WHERE case_id = ?");
    $stmt->execute([$caseId]);
    $case['signals'] = $stmt->fetchAll();
    
    // Nurse reviews
    $stmt = $db->prepare("
        SELECT nr.*, u.name as nurse_name 
        FROM nurse_reviews nr 
        JOIN users u ON nr.nurse_id = u.id 
        WHERE nr.case_id = ? 
        ORDER BY nr.created_at DESC
    ");
    $stmt->execute([$caseId]);
    $nurseReviews = $stmt->fetchAll();
    foreach ($nurseReviews as &$nr) {
        if (!empty($nr['answered_questions']) && is_string($nr['answered_questions'])) {
            $nr['answered_questions'] = json_decode($nr['answered_questions'], true);
        }
    }
    unset($nr);
    $case['nurse_reviews'] = $nurseReviews;
    
    // Doctor reviews
    $stmt = $db->prepare("
        SELECT dr.*, u.name as doctor_name 
        FROM doctor_reviews dr 
        JOIN users u ON dr.doctor_id = u.id 
        WHERE dr.case_id = ? 
        ORDER BY dr.created_at DESC
    ");
    $stmt->execute([$caseId]);
    $case['doctor_reviews'] = $stmt->fetchAll();
    
    // Audit logs
    $stmt = $db->prepare("
        SELECT al.*, u.name as user_name 
        FROM audit_logs al 
        LEFT JOIN users u ON al.user_id = u.id 
        WHERE al.case_id = ? 
        ORDER BY al.created_at ASC
    ");
    $stmt->execute([$caseId]);
    $case['audit_logs'] = $stmt->fetchAll();
    
    // Multiple Case Notes (Comments / Timeline with Auto-Time)
    $case['notes'] = getCaseNotes($caseId);
    
    return $case;
}

/**
 * Update case status
 */
function updateCaseStatus(int $caseId, string $status, ?int $userId = null): void {
    $db = getDB();
    $stmt = $db->prepare("UPDATE cases SET status = ? WHERE id = ?");
    $stmt->execute([$status, $caseId]);
    addAuditLog($caseId, $userId, 'STATUS_CHANGE', "Status changed to $status");
}

/**
 * Add a conversation message
 */
function addConversationMessage(int $caseId, string $mode, string $speaker, string $message): int {
    $db = getDB();
    $stmt = $db->prepare("INSERT INTO conversations (case_id, mode, speaker, message) VALUES (?, ?, ?, ?)");
    $stmt->execute([$caseId, $mode, $speaker, $message]);
    return (int) $db->lastInsertId();
}

/**
 * Save triage results
 */
function saveTriageResults(int $caseId, array $data): void {
    $db = getDB();
    $stmt = $db->prepare("
        INSERT INTO triage_results (case_id, summary, symptoms, timeline, missing_information, followup_questions)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $caseId,
        $data['summary'] ?? '',
        json_encode($data['symptoms'] ?? []),
        json_encode($data['timeline'] ?? []),
        json_encode($data['missing_information'] ?? []),
        json_encode($data['followup_questions'] ?? [])
    ]);
    addAuditLog($caseId, null, 'TRIAGE_COMPLETED', 'MEDGem processing completed');
}

/**
 * Save urgency signal
 */
function saveUrgencySignal(int $caseId, string $ruleId, string $signal, string $source, string $reason): void {
    $db = getDB();
    $stmt = $db->prepare("
        INSERT INTO urgency_signals (case_id, rule_id, signal, source, reason)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([$caseId, $ruleId, $signal, $source, $reason]);
    addAuditLog($caseId, null, 'SAFETY_SIGNAL', "Safety signal generated: $signal");
}

/**
 * Add audit log entry
 */
function addAuditLog(?int $caseId, ?int $userId, string $action, string $description): void {
    $db = getDB();
    $stmt = $db->prepare("INSERT INTO audit_logs (case_id, user_id, action, description) VALUES (?, ?, ?, ?)");
    $stmt->execute([$caseId, $userId, $action, $description]);
}

/**
 * Get dashboard stats for nurse
 */
function getNurseStats(): array {
    $db = getDB();
    
    $stats = [
        'total'     => 0,
        'urgent'    => 0,
        'pending'   => 0,
        'completed' => 0
    ];
    
    // Total registered cases
    $stmt = $db->query("SELECT COUNT(*) as c FROM cases");
    $stats['total'] = (int) $stmt->fetch()['c'];
    
    // Urgent (safety red flags)
    $stmt = $db->query("
        SELECT COUNT(DISTINCT c.id) as c 
        FROM cases c 
        JOIN urgency_signals us ON c.id = us.case_id 
        WHERE c.status IN ('PENDING_NURSE_REVIEW', 'IN_NURSE_REVIEW', 'ESCALATED')
    ");
    $stats['urgent'] = (int) $stmt->fetch()['c'];
    
    // Awaiting nurse review
    $stmt = $db->query("SELECT COUNT(*) as c FROM cases WHERE status IN ('PENDING_NURSE_REVIEW', 'IN_NURSE_REVIEW')");
    $stats['pending'] = (int) $stmt->fetch()['c'];

    // Reviewed / Dispatched
    $stmt = $db->query("SELECT COUNT(*) as c FROM cases WHERE status IN ('REVIEWED', 'ESCALATED', 'COMPLETED', 'REFERRED')");
    $stats['completed'] = (int) $stmt->fetch()['c'];
    
    return $stats;
}

/**
 * Add a clinical note / comment to a case with auto timestamp
 */
function addCaseNote(int $caseId, int $userId, string $userRole, string $userName, string $note): int {
    $db = getDB();
    $note = trim($note);
    if ($note === '') return 0;
    
    $stmt = $db->prepare("
        INSERT INTO case_notes (case_id, user_id, user_role, user_name, note, created_at)
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([$caseId, $userId, strtoupper($userRole), $userName, $note]);
    $noteId = (int) $db->lastInsertId();
    
    addAuditLog($caseId, $userId, strtoupper($userRole) . '_NOTE_ADDED', "Clinical note added by $userName ($userRole)");
    return $noteId;
}

/**
 * Get all clinical notes / comments for a case in chronological order
 */
function getCaseNotes(int $caseId): array {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT cn.*, u.email as user_email
        FROM case_notes cn
        LEFT JOIN users u ON cn.user_id = u.id
        WHERE cn.case_id = ?
        ORDER BY cn.created_at ASC
    ");
    $stmt->execute([$caseId]);
    $notes = $stmt->fetchAll();
    
    // Add formatted time strings
    foreach ($notes as &$n) {
        $n['time_ago'] = timeAgo($n['created_at']);
        $n['formatted_date'] = date('d M Y, h:i A', strtotime($n['created_at']));
    }
    unset($n);
    
    return $notes;
}

/**
 * Get dashboard stats for doctor
 */
function getDoctorStats(): array {
    $db = getDB();
    
    $stats = [
        'total'     => 0,
        'urgent'    => 0,
        'in_review' => 0,
        'completed' => 0
    ];
    
    // Total registered patient cases in the facility
    $stmt = $db->query("SELECT COUNT(*) as c FROM cases");
    $stats['total'] = (int) $stmt->fetch()['c'];
    
    // Urgent cases with safety signals (active)
    $stmt = $db->query("
        SELECT COUNT(DISTINCT c.id) as c 
        FROM cases c 
        JOIN urgency_signals us ON c.id = us.case_id 
        WHERE c.status NOT IN ('COMPLETED', 'REFERRED')
    ");
    $stats['urgent'] = (int) $stmt->fetch()['c'];
    
    // Cases currently under review or escalated
    $stmt = $db->query("SELECT COUNT(*) as c FROM cases WHERE status IN ('ESCALATED', 'DOCTOR_REVIEW', 'IN_NURSE_REVIEW', 'PENDING_NURSE_REVIEW')");
    $stats['in_review'] = (int) $stmt->fetch()['c'];

    // Completed or referred
    $stmt = $db->query("SELECT COUNT(*) as c FROM cases WHERE status IN ('COMPLETED', 'REFERRED')");
    $stats['completed'] = (int) $stmt->fetch()['c'];
    
    return $stats;
}

/**
 * Get cases list for nurse queue
 */
function getNurseCaseQueue(string $filter = 'all', string $search = ''): array {
    $db = getDB();
    $params = [];
    
    $sql = "
        SELECT c.*, 
            (SELECT COUNT(*) FROM urgency_signals WHERE case_id = c.id) as signal_count,
            GROUP_CONCAT(DISTINCT us.signal SEPARATOR ', ') as signals,
            (SELECT note FROM case_notes WHERE case_id = c.id ORDER BY created_at DESC LIMIT 1) as latest_note
        FROM cases c
        LEFT JOIN urgency_signals us ON c.id = us.case_id
        WHERE 1=1
    ";
    
    if ($filter === 'urgent') {
        $sql .= " AND c.id IN (SELECT case_id FROM urgency_signals)";
    } elseif ($filter === 'routine') {
        $sql .= " AND c.id NOT IN (SELECT case_id FROM urgency_signals)";
    } elseif ($filter === 'new') {
        $sql .= " AND c.status IN ('NEW', 'PROCESSING', 'PENDING_NURSE_REVIEW')";
    } elseif ($filter === 'reviewed') {
        $sql .= " AND c.status IN ('IN_NURSE_REVIEW', 'NURSE_REVIEWED', 'ESCALATED', 'DOCTOR_REVIEW')";
    } elseif ($filter === 'completed') {
        $sql .= " AND c.status IN ('COMPLETED', 'CLEARED', 'REFERRED')";
    }

    $search = trim($search);
    if ($search !== '') {
        $sql .= " AND (c.track_id LIKE ? OR c.name LIKE ? OR c.id = ?)";
        $like = "%$search%";
        $params[] = $like;
        $params[] = $like;
        $params[] = is_numeric($search) ? (int)$search : 0;
    }
    
    $sql .= " GROUP BY c.id ORDER BY (CASE WHEN c.status IN ('PENDING_NURSE_REVIEW', 'IN_NURSE_REVIEW') THEN 0 ELSE 1 END), c.created_at DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Get cases list for doctor queue
 * Doctors can see all cases, including those not yet reviewed by a nurse
 */
function getDoctorCaseQueue(string $filter = 'all', string $search = ''): array {
    $db = getDB();
    $params = [];

    $sql = "
        SELECT c.*, 
            (SELECT COUNT(*) FROM urgency_signals WHERE case_id = c.id) as signal_count,
            GROUP_CONCAT(DISTINCT us.signal SEPARATOR ', ') as signals,
            (SELECT note FROM case_notes WHERE case_id = c.id ORDER BY created_at DESC LIMIT 1) as latest_note,
            (SELECT notes FROM nurse_reviews WHERE case_id = c.id ORDER BY created_at DESC LIMIT 1) as nurse_notes,
            (SELECT COUNT(*) FROM nurse_reviews WHERE case_id = c.id) as nurse_review_count
        FROM cases c
        LEFT JOIN urgency_signals us ON c.id = us.case_id
        WHERE 1=1
    ";
    if ($filter === 'urgent') {
        $sql .= " AND c.id IN (SELECT case_id FROM urgency_signals)";
    } elseif ($filter === 'routine') {
        $sql .= " AND c.id NOT IN (SELECT case_id FROM urgency_signals)";
    } elseif ($filter === 'new' || $filter === 'unreviewed') {
        $sql .= " AND (c.status IN ('NEW', 'PROCESSING', 'PENDING_NURSE_REVIEW', 'IN_NURSE_REVIEW', 'ESCALATED', 'DOCTOR_REVIEW') OR c.id NOT IN (SELECT case_id FROM nurse_reviews))";
    } elseif ($filter === 'reviewed') {
        $sql .= " AND (c.status IN ('REVIEWED', 'ESCALATED', 'DOCTOR_REVIEW') OR c.id IN (SELECT case_id FROM nurse_reviews))";
    } elseif ($filter === 'completed') {
        $sql .= " AND c.status IN ('COMPLETED', 'REFERRED')";
    }

    $search = trim($search);
    if ($search !== '') {
        $sql .= " AND (c.track_id LIKE ? OR c.name LIKE ? OR c.id = ?)";
        $like = "%$search%";
        $params[] = $like;
        $params[] = $like;
        $params[] = is_numeric($search) ? (int)$search : 0;
    }

    $sql .= " GROUP BY c.id ORDER BY (CASE WHEN c.status IN ('ESCALATED', 'DOCTOR_REVIEW') THEN 0 WHEN (SELECT COUNT(*) FROM urgency_signals WHERE case_id = c.id) > 0 THEN 1 WHEN c.status IN ('PENDING_NURSE_REVIEW', 'IN_NURSE_REVIEW', 'NEW') THEN 2 ELSE 3 END), c.created_at DESC";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Format status for display
 */
function formatStatus(string $status): string {
    $map = [
        'NEW'                  => 'New',
        'PROCESSING'           => 'Processing',
        'PENDING_NURSE_REVIEW' => 'Pending Nurse Review',
        'IN_NURSE_REVIEW'      => 'In Nurse Review',
        'REVIEWED'             => 'Reviewed',
        'ESCALATED'            => 'Escalated to Doctor',
        'DOCTOR_REVIEW'        => 'Doctor Review',
        'COMPLETED'            => 'Completed',
        'REFERRED'             => 'Referred',
    ];
    return $map[$status] ?? $status;
}

/**
 * Get status color class
 */
function statusColor(string $status): string {
    $map = [
        'NEW'                  => 'status-new',
        'PROCESSING'           => 'status-processing',
        'PENDING_NURSE_REVIEW' => 'status-pending',
        'IN_NURSE_REVIEW'      => 'status-active',
        'REVIEWED'             => 'status-success',
        'ESCALATED'            => 'status-warning',
        'DOCTOR_REVIEW'        => 'status-active',
        'COMPLETED'            => 'status-success',
        'REFERRED'             => 'status-info',
    ];
    return $map[$status] ?? 'status-default';
}

/**
 * Sanitize input
 */
function sanitize(string $input): string {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Time ago helper
 */
function timeAgo(string $datetime): string {
    $now = new DateTime();
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);
    
    if ($diff->y > 0) return $diff->y . ' year' . ($diff->y > 1 ? 's' : '') . ' ago';
    if ($diff->m > 0) return $diff->m . ' month' . ($diff->m > 1 ? 's' : '') . ' ago';
    if ($diff->d > 0) return $diff->d . ' day' . ($diff->d > 1 ? 's' : '') . ' ago';
    if ($diff->h > 0) return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
    if ($diff->i > 0) return $diff->i . ' min' . ($diff->i > 1 ? 's' : '') . ' ago';
    return 'Just now';
}

/**
 * Normalizes and verifies medical report data
 * Ensures clean structured parameters, flags, reference ranges, and verification status
 */
function normalizeReportData(array $rep): array {
    $ev = $rep['extracted_values'] ?? [];
    if (is_string($ev)) {
        $ev = json_decode($ev, true) ?: [];
    }

    $isRelevant = isset($ev['is_relevant']) ? (bool)$ev['is_relevant'] : true;
    $status = $ev['relevance_status'] ?? ($isRelevant ? 'RELEVANT' : 'IGNORED');
    $reason = $ev['relevance_reason'] ?? '';
    $docDate = $ev['document_date'] ?? date('Y-m-d', strtotime($rep['created_at']));
    $nurseVerified = !empty($ev['nurse_verified']);
    $nurseNote = $ev['nurse_note'] ?? '';

    // If report is outdated or ignored, clear key findings so they do not contaminate the active clinical summary
    if (!$isRelevant || $status === 'IGNORED' || $status === 'OUTDATED') {
        $rep['extracted_values'] = [
            'document_title' => $ev['document_title'] ?? $rep['file_name'],
            'document_date' => $docDate,
            'document_type' => $ev['document_type'] ?? 'Lab Report',
            'is_relevant' => false,
            'relevance_status' => 'IGNORED',
            'relevance_reason' => $reason ?: 'Excluded as outdated or unrelated historical document',
            'nurse_verified' => $nurseVerified,
            'nurse_note' => $nurseNote,
            'key_findings' => [],
            'summary_text' => 'Outdated report excluded from active clinical dossier.'
        ];
        return $rep;
    }

    // Standard clinical test reference ranges & flag computing
    $refMap = [
        'Hemoglobin' => ['ref' => '13.0 - 17.0 g/dL', 'low' => 12.0, 'high' => 17.5],
        'Hb' => ['ref' => '13.0 - 17.0 g/dL', 'low' => 12.0, 'high' => 17.5],
        'WBC' => ['ref' => '4,000 - 11,000 /µL', 'low' => 4000, 'high' => 11000],
        'Total Leukocyte Count (WBC)' => ['ref' => '4,000 - 11,000 /µL', 'low' => 4000, 'high' => 11000],
        'Total Leucocyte Count (TLC)' => ['ref' => '4,000 - 11,000 /µL', 'low' => 4000, 'high' => 11000],
        'Platelets' => ['ref' => '1.5 - 4.5 Lakh /µL', 'low' => 150000, 'high' => 450000],
        'Platelet Count' => ['ref' => '1.5 - 4.5 Lakh /µL', 'low' => 150000, 'high' => 450000],
        'RBC' => ['ref' => '4.5 - 5.5 million /µL', 'low' => 4.2, 'high' => 5.8],
        'ESR' => ['ref' => '0 - 15 mm/hr', 'low' => 0, 'high' => 15],
        'C-Reactive Protein (CRP)' => ['ref' => '< 6.0 mg/L', 'low' => 0, 'high' => 6.0],
        'CRP' => ['ref' => '< 6.0 mg/L', 'low' => 0, 'high' => 6.0],
        'Fasting Blood Glucose (FBS)' => ['ref' => '70.0 - 100.0 mg/dL', 'low' => 70, 'high' => 100],
        'FBS' => ['ref' => '70.0 - 100.0 mg/dL', 'low' => 70, 'high' => 100],
        'Glycated Hemoglobin (HbA1c)' => ['ref' => '4.0 - 5.6 %', 'low' => 4.0, 'high' => 5.6],
        'HbA1c' => ['ref' => '4.0 - 5.6 %', 'low' => 4.0, 'high' => 5.6],
        'Serum Creatinine' => ['ref' => '0.5 - 1.1 mg/dL', 'low' => 0.5, 'high' => 1.2],
        'Total Cholesterol' => ['ref' => '125.0 - 200.0 mg/dL', 'low' => 125, 'high' => 200],
        'Triglycerides' => ['ref' => '40.0 - 150.0 mg/dL', 'low' => 40, 'high' => 150],
        'Hematocrit (PCV)' => ['ref' => '40.0 - 52.0 %', 'low' => 40, 'high' => 52],
    ];

    $keyFindings = [];

    // Case A: key_findings is already structured list
    if (!empty($ev['key_findings']) && is_array($ev['key_findings'])) {
        foreach ($ev['key_findings'] as $kf) {
            $tName = $kf['test_name'] ?? 'Test';
            $tVal = (string)($kf['value'] ?? '');
            $tUnit = $kf['unit'] ?? '';
            $tRef = !empty($kf['reference_range']) && $kf['reference_range'] !== '—' ? $kf['reference_range'] : ($refMap[$tName]['ref'] ?? 'Standard');
            $tFlag = strtoupper($kf['flag'] ?? 'NORMAL');
            $keyFindings[] = [
                'test_name' => $tName,
                'value' => $tVal,
                'unit' => $tUnit,
                'reference_range' => $tRef,
                'flag' => $tFlag
            ];
        }
    } 
    // Case B: key_findings is dictionary or extracted_values has test names as keys
    else {
        foreach ($ev as $k => $v) {
            if (in_array($k, ['document_title', 'document_date', 'document_type', 'is_relevant', 'relevance_status', 'relevance_reason', 'summary_text', 'nurse_verified', 'nurse_note', 'verified_by', 'key_findings'])) {
                continue;
            }
            if (is_scalar($v)) {
                $valStr = (string)$v;
                $flag = 'NORMAL';
                $refInfo = $refMap[$k] ?? null;
                $tRef = $refInfo['ref'] ?? 'Standard';

                if (stripos($valStr, 'high') !== false || stripos($valStr, 'elevated') !== false) {
                    $flag = 'HIGH';
                } elseif (stripos($valStr, 'low') !== false || stripos($valStr, 'decreased') !== false) {
                    $flag = 'LOW';
                } elseif ($k === 'ESR' && floatval($valStr) > 15) {
                    $flag = 'HIGH';
                } elseif (($k === 'Hemoglobin' || $k === 'Hb') && floatval($valStr) < 12.0) {
                    $flag = 'LOW';
                } elseif (($k === 'CRP' || stripos($k, 'Reactive') !== false) && floatval($valStr) > 6.0) {
                    $flag = 'HIGH';
                }

                $cleanVal = trim(preg_replace('/\s*\[(HIGH|LOW|NORMAL)\]\s*/i', '', $valStr));

                $keyFindings[] = [
                    'test_name' => $k,
                    'value' => $cleanVal,
                    'unit' => '',
                    'reference_range' => $tRef,
                    'flag' => $flag
                ];
            }
        }
    }

    // Case C: If still empty, parse OCR text
    if (empty($keyFindings) && !empty($rep['ocr_text'])) {
        $lines = explode("\n", $rep['ocr_text']);
        foreach ($lines as $line) {
            if (preg_match('/^([A-Za-z0-9\s\(\)]+)\s*[:=]\s*([0-9\.,]+)\s*([A-Za-z%\/µmcL]*)/i', trim($line), $m)) {
                $tName = trim($m[1]);
                $tVal = trim($m[2]);
                $tUnit = trim($m[3]);
                $refInfo = $refMap[$tName] ?? null;
                $tRef = $refInfo['ref'] ?? 'Standard';
                $flag = 'NORMAL';
                if ($tName === 'ESR' && floatval($tVal) > 15) $flag = 'HIGH';
                elseif (($tName === 'Hemoglobin' || $tName === 'Hb') && floatval($tVal) < 12.0) $flag = 'LOW';
                $keyFindings[] = [
                    'test_name' => $tName,
                    'value' => $tVal,
                    'unit' => $tUnit,
                    'reference_range' => $tRef,
                    'flag' => $flag
                ];
            }
        }
    }

    $rep['extracted_values'] = [
        'document_title' => $ev['document_title'] ?? $rep['file_name'],
        'document_date' => $docDate,
        'document_type' => $ev['document_type'] ?? 'Lab Report',
        'is_relevant' => $isRelevant,
        'relevance_status' => $status,
        'relevance_reason' => $reason ?: ($isRelevant ? 'Verified relevant to acute presentation' : 'Excluded as outdated historical test'),
        'nurse_verified' => $nurseVerified,
        'nurse_note' => $nurseNote,
        'key_findings' => $keyFindings,
        'summary_text' => $ev['summary_text'] ?? ''
    ];

    return $rep;
}
