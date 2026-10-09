<?php
/**
 * MEDGem — Clean & Seed Exactly 5 Distinct Clinical Cases
 * Purges all previous case data and populates exactly 5 realistic medical cases.
 */

require_once __DIR__ . '/../includes/db.php';

$db = getDB();

// Disable foreign key checks for clean purge
$db->exec("SET FOREIGN_KEY_CHECKS = 0");
$db->exec("TRUNCATE TABLE cases");
$db->exec("TRUNCATE TABLE triage_results");
$db->exec("TRUNCATE TABLE urgency_signals");
$db->exec("TRUNCATE TABLE nurse_reviews");
$db->exec("TRUNCATE TABLE doctor_reviews");
$db->exec("TRUNCATE TABLE case_notes");
$db->exec("TRUNCATE TABLE reports");
$db->exec("TRUNCATE TABLE conversations");
$db->exec("TRUNCATE TABLE audit_logs");
$db->exec("SET FOREIGN_KEY_CHECKS = 1");

echo "All previous cases purged cleanly.\n";

// Ensure report files directory exists
$reportsDir = __DIR__ . '/../uploads/reports';
if (!is_dir($reportsDir)) {
    mkdir($reportsDir, 0777, true);
}

// Ensure default Nurse & Doctor staff accounts exist in `users` table
$nurseId = $db->query("SELECT id FROM users WHERE role = 'NURSE' LIMIT 1")->fetchColumn();
if (!$nurseId) {
    $stmt = $db->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'NURSE')");
    $stmt->execute(['Nurse Priya Sharma', 'nurse@medgem.demo', password_hash('password', PASSWORD_DEFAULT)]);
    $nurseId = $db->lastInsertId();
}

$doctorId = $db->query("SELECT id FROM users WHERE role = 'DOCTOR' LIMIT 1")->fetchColumn();
if (!$doctorId) {
    $stmt = $db->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'DOCTOR')");
    $stmt->execute(['Dr. Rajesh Kumar', 'doctor@medgem.demo', password_hash('password', PASSWORD_DEFAULT)]);
    $doctorId = $db->lastInsertId();
}

// Prepared Statements
$stmtCase = $db->prepare("
    INSERT INTO cases (track_id, name, age, contact_number, sex, preferred_language, consent, status, communication_mode, created_at)
    VALUES (?, ?, ?, ?, ?, ?, 1, ?, ?, ?)
");

$stmtConv = $db->prepare("INSERT INTO conversations (case_id, mode, speaker, message, timestamp) VALUES (?, ?, ?, ?, ?)");

$stmtRep = $db->prepare("INSERT INTO reports (case_id, file_name, file_path, ocr_text, extracted_values) VALUES (?, ?, ?, ?, ?)");

$stmtTriage = $db->prepare("
    INSERT INTO triage_results (case_id, summary, symptoms, timeline, missing_information, followup_questions)
    VALUES (?, ?, ?, ?, ?, ?)
");

$stmtSig = $db->prepare("INSERT INTO urgency_signals (case_id, rule_id, `signal`, `source`, reason) VALUES (?, ?, ?, ?, ?)");

$stmtNurse = $db->prepare("
    INSERT INTO nurse_reviews (case_id, nurse_id, verified_summary, notes, answered_questions, decision, created_at)
    VALUES (?, ?, ?, ?, ?, ?, ?)
");

$stmtDoc = $db->prepare("
    INSERT INTO doctor_reviews (case_id, doctor_id, notes, decision, referral_note, created_at)
    VALUES (?, ?, ?, ?, ?, ?)
");

$stmtNote = $db->prepare("
    INSERT INTO case_notes (case_id, user_id, user_role, user_name, note, created_at)
    VALUES (?, ?, ?, ?, ?, ?)
");

$stmtAudit = $db->prepare("INSERT INTO audit_logs (case_id, user_id, action, description, created_at) VALUES (?, ?, ?, ?, ?)");


// ============================================================================
// CASE 1: SYN-1042 — Soubhagya Rout (52F) | Status: ESCALATED (Urgent Chest Pain)
// ============================================================================
$file1 = $reportsDir . '/soubhagya_rout_metabolic_panel.txt';
file_put_contents($file1, "========================================================\nMETROPOLIS CLINICAL LABORATORY\nPatient: Soubhagya Rout (52F) | Ref: Dr. Rajesh Kumar\nFasting Blood Glucose: 242.0 mg/dL [HIGH] (70-100)\nHbA1c: 9.2 % [HIGH] (4.0-5.6)\nSerum Creatinine: 1.08 mg/dL (0.5-1.1)\nTotal Cholesterol: 228.0 mg/dL [HIGH] (125-200)\n========================================================\n");

$stmtCase->execute(['SYN-1042', 'Soubhagya Rout', 52, '+91 98765 43210', 'Female', 'English', 'ESCALATED', 'TEXT', date('Y-m-d H:i:s', strtotime('-45 minutes'))]);
$c1 = $db->lastInsertId();

$stmtConv->execute([$c1, 'TEXT', 'PATIENT', 'Hello, for the past 4 hours I have a heavy squeezing sensation in the middle of my chest. It is spreading down to my left shoulder and arm. I broke out into a cold sweat.', date('Y-m-d H:i:s', strtotime('-40 minutes'))]);
$stmtConv->execute([$c1, 'TEXT', 'MEDGEM', 'Thank you for sharing, Soubhagya. I have noted the chest pressure radiating to your left arm and cold sweats. Are you experiencing any breathlessness?', date('Y-m-d H:i:s', strtotime('-38 minutes'))]);
$stmtConv->execute([$c1, 'TEXT', 'PATIENT', 'Yes, walking even 10 steps makes me breathless. I took an antacid 2 hours ago thinking it was gas, but it did not help. I am a known diabetic for 8 years.', date('Y-m-d H:i:s', strtotime('-35 minutes'))]);

$stmtRep->execute([
    $c1, 'soubhagya_rout_metabolic_panel.pdf', 'uploads/reports/soubhagya_rout_metabolic_panel.txt',
    "Fasting Blood Glucose: 242 mg/dL [HIGH]\nHbA1c: 9.2% [HIGH]\nSerum Creatinine: 1.08 mg/dL\nTotal Cholesterol: 228 mg/dL",
    json_encode([
        'document_title' => 'Fasting Blood Sugar & Metabolic Panel',
        'document_date' => date('Y-m-d', strtotime('-2 days')),
        'document_type' => 'Biochemistry Lab Report',
        'is_relevant' => true,
        'relevance_status' => 'RELEVANT',
        'relevance_reason' => 'Severe uncontrolled hyperglycemia (242 mg/dL) & HbA1c 9.2% elevated cardiac risk in acute retrosternal distress.',
        'key_findings' => [
            ['test_name' => 'Fasting Blood Glucose', 'value' => '242', 'unit' => 'mg/dL', 'reference_range' => '70 - 99 mg/dL', 'flag' => 'HIGH'],
            ['test_name' => 'HbA1c', 'value' => '9.2', 'unit' => '%', 'reference_range' => '< 5.7%', 'flag' => 'HIGH'],
            ['test_name' => 'Total Cholesterol', 'value' => '228', 'unit' => 'mg/dL', 'reference_range' => '< 200 mg/dL', 'flag' => 'HIGH']
        ]
    ])
]);

$stmtTriage->execute([
    $c1,
    "52-year-old female presenting with acute retrosternal chest pressure radiating to left arm and shoulder for 4 hours, accompanied by cold diaphoresis and dyspnea on minimal exertion.\nType 2 Diabetes mellitus for 8 years with recent HbA1c 9.2% and fasting blood glucose 242 mg/dL. Antacid trial yielded no relief. Predefined acute coronary syndrome safety rules triggered.",
    json_encode(['Retrosternal chest pressure', 'Left shoulder & arm radiation', 'Cold diaphoresis', 'Exertional dyspnea', 'Type 2 Diabetes Mellitus']),
    json_encode([
        ['time' => '4 hours ago', 'event' => 'Onset of deep retrosternal heaviness and discomfort'],
        ['time' => '3 hours ago', 'event' => 'Radiation down left arm; profuse cold sweating'],
        ['time' => '35 mins ago', 'event' => 'Breathlessness noted while walking; reported to triage desk']
    ]),
    json_encode(['Triage vitals (BP, HR, SpO2)', 'STAT 12-lead Electrocardiogram (ECG)', 'Serum Troponin I/T levels']),
    json_encode(['Does the pressure change with deep breathing?', 'Have you felt dizzy or faint today?'])
]);

$stmtSig->execute([$c1, 'R01', 'Chest pressure radiating to left arm & shoulder', 'Patient Intake (Text)', 'Classic ischemic symptom pattern concerning for acute coronary event.']);
$stmtSig->execute([$c1, 'R03', 'Dyspnea / breathlessness on minimal exertion', 'Patient Intake (Text)', 'Respiratory distress coinciding with acute retrosternal pain.']);
$stmtSig->execute([$c1, 'R07', 'Uncontrolled Hyperglycemia (242 mg/dL) & HbA1c 9.2%', 'Lab Report', 'High metabolic risk factor for acute vascular compromise.']);

$stmtNurse->execute([$c1, $nurseId, "Soubhagya Rout (52F). Vitals: BP 156/94, HR 96, SpO2 96% room air, Capillary Glucose 238 mg/dL. STAT ECG uploaded.", "ECG shows 1.5mm ST depression in V4-V6. O2 2L via nasal cannula started. Immediate physician escalation done.", json_encode([['question' => 'Triage Vitals', 'answer' => 'BP 156/94 | HR 96 | SpO2 96% | Temp 98.4°F'], ['question' => 'STAT 12-lead ECG', 'answer' => 'ST depression 1.5mm in leads V4-V6']]), 'ESCALATED', date('Y-m-d H:i:s', strtotime('-20 minutes'))]);

$stmtNote->execute([$c1, $nurseId, 'NURSE', 'Nurse Priya Sharma', 'STAT 12-lead ECG completed. Patient placed on nasal O2 2L/min. Escalated to attending physician.', date('Y-m-d H:i:s', strtotime('-20 minutes'))]);
$stmtNote->execute([$c1, $doctorId, 'DOCTOR', 'Dr. Rajesh Kumar', 'ECG reviewed — ST depression in V4-V6. Aspirin 300mg & Clopidogrel 300mg administered STAT. Cardiology consult requested.', date('Y-m-d H:i:s', strtotime('-10 minutes'))]);

$stmtAudit->execute([$c1, null, 'CASE_CREATED', 'Patient self-intake completed', date('Y-m-d H:i:s', strtotime('-45 minutes'))]);
$stmtAudit->execute([$c1, $nurseId, 'NURSE_ESCALATED', 'Nurse Priya Sharma escalated case to physician', date('Y-m-d H:i:s', strtotime('-20 minutes'))]);


// ============================================================================
// CASE 2: SYN-2085 — Rohan Mohanty (28M) | Status: PENDING_NURSE_REVIEW (Dengue Fever)
// ============================================================================
$file2 = $reportsDir . '/rohan_mohanty_cbc.txt';
file_put_contents($file2, "========================================================\nEASTERN PATHOLOGY LABORATORY\nPatient: Rohan Mohanty (28M) | Test: CBC\nHemoglobin: 15.2 g/dL (13.5-17.5)\nTotal Leukocytes (WBC): 3,400 /mcL [LOW] (4,000-11,000)\nPlatelet Count: 92,000 /mcL [LOW] (150,000-450,000)\nHematocrit (PCV): 44.5 %\n========================================================\n");

$stmtCase->execute(['SYN-2085', 'Rohan Mohanty', 28, '+91 94371 88204', 'Male', 'Odia', 'PENDING_NURSE_REVIEW', 'VOICE', date('Y-m-d H:i:s', strtotime('-2 hours'))]);
$c2 = $db->lastInsertId();

$stmtConv->execute([$c2, 'VOICE', 'PATIENT', 'Mo deha re 4 dina hela prachanda jwara achi (103°F). Akhi pachhapate bhari bindhuchi au sarba sare bindha heuchi. Platelet kami jaichi.', date('Y-m-d H:i:s', strtotime('-115 minutes'))]);
$stmtConv->execute([$c2, 'VOICE', 'MEDGEM', 'Namaskar Rohan. Apana 103 degree fever au platelet kami jiba bishaye kahile. Bleeding kichhi heichi ki?', date('Y-m-d H:i:s', strtotime('-114 minutes'))]);
$stmtConv->execute([$c2, 'VOICE', 'PATIENT', 'Na, bleeding heini. Kintu bhari bhalalia laguchi, khali banti banti laguchi.', date('Y-m-d H:i:s', strtotime('-112 minutes'))]);

$stmtRep->execute([
    $c2, 'rohan_mohanty_cbc.pdf', 'uploads/reports/rohan_mohanty_cbc.txt',
    "WBC: 3,400 /mcL [LOW]\nPlatelets: 92,000 /mcL [LOW]\nHb: 15.2 g/dL",
    json_encode([
        'document_title' => 'Complete Blood Count (CBC)',
        'document_date' => date('Y-m-d', strtotime('-1 day')),
        'document_type' => 'Hematology Report',
        'is_relevant' => true,
        'relevance_status' => 'RELEVANT',
        'relevance_reason' => 'Thrombocytopenia (92,000/mcL) & Leucopenia corroborates vector-borne viral febrile illness.',
        'key_findings' => [
            ['test_name' => 'Platelet Count', 'value' => '92,000', 'unit' => '/mcL', 'reference_range' => '150,000 - 450,000', 'flag' => 'LOW'],
            ['test_name' => 'WBC Count', 'value' => '3,400', 'unit' => '/mcL', 'reference_range' => '4,000 - 11,000', 'flag' => 'LOW']
        ]
    ])
]);

$stmtTriage->execute([
    $c2,
    "28-year-old male with 4-day history of high-grade fever peaking at 103°F, intense retro-orbital headache, generalized body aches, and persistent nausea.\nCBC demonstrates leucopenia (3,400/mcL) and moderate thrombocytopenia (92,000/mcL), suggestive of acute vector-borne Dengue fever syndrome. No spontaneous bleeding reported.",
    json_encode(['High-grade fever (103.2°F, Day 4)', 'Retro-orbital eye pain', 'Severe body ache', 'Thrombocytopenia (Platelets 92k)']),
    json_encode([
        ['time' => 'Day 1 (4 days ago)', 'event' => 'Sudden onset fever spike to 102°F with chills'],
        ['time' => 'Day 4 (Today)', 'event' => 'Platelet count dropped to 92,000 /mcL; reported to clinic']
    ]),
    json_encode(['Dengue NS1 Antigen & IgM serology', 'Hydration & 24h urine volume assessment', 'Tourniquet test for capillary fragility']),
    json_encode(['Have you noticed any red pinhead spots on skin?', 'Are you able to take oral fluids regularly?'])
]);

$stmtSig->execute([$c2, 'R06', 'High-grade fever (103°F) for 4 consecutive days', 'Voice Intake (Odia)', 'Persistent high fever with severe myalgias indicates viral infection risk.']);
$stmtSig->execute([$c2, 'R08', 'Moderate Thrombocytopenia (92,000 /mcL) & Leucopenia', 'CBC Report', 'Sub-100k platelet count in acute febrile illness warrants close monitoring.']);

$stmtNote->execute([$c2, $nurseId, 'NURSE', 'Nurse Priya Sharma', 'Patient in waiting hall. Vitals being recorded. Oral rehydration solution (ORS) started.', date('Y-m-d H:i:s', strtotime('-1 hour'))]);

$stmtAudit->execute([$c2, null, 'CASE_CREATED', 'Voice intake in Odia completed', date('Y-m-d H:i:s', strtotime('-2 hours'))]);


// ============================================================================
// CASE 3: SYN-3190 — Sunita Devi (46F) | Status: REFERRED (Lumbar Radiculopathy)
// ============================================================================
$file3 = $reportsDir . '/sunita_devi_lumbar_xray.txt';
file_put_contents($file3, "========================================================\nRADIODIAGNOSIS CENTER\nPatient: Sunita Devi (46F) | Digital X-Ray LS Spine\nFindings: L4-L5 disc space narrowing with anterior marginal osteophytes.\nImpression: Lumbar spondylosis with degenerative disc disease at L4-L5 level.\n========================================================\n");

$stmtCase->execute(['SYN-3190', 'Sunita Devi', 46, '+91 91234 56789', 'Female', 'Hindi', 'REFERRED', 'TEXT', date('Y-m-d H:i:s', strtotime('-1 day'))]);
$c3 = $db->lastInsertId();

$stmtConv->execute([$c3, 'TEXT', 'PATIENT', 'Mujhe 6 hafton se kamar ke nichle hisse mein tez dard hai jo dahine pair ke pichhe se hota hua pindli tak jata hai. Jhukne par dard badh jata hai.', date('Y-m-d H:i:s', strtotime('-23 hours'))]);
$stmtConv->execute([$c3, 'TEXT', 'MEDGEM', 'Namaste Sunita ji. Kamar dard jo pair tak failta hai ko note kar liya gaya hai. Kya pair mein sunn-pan hai?', date('Y-m-d H:i:s', strtotime('-22 hours'))]);

$stmtRep->execute([
    $c3, 'sunita_devi_lumbar_xray.pdf', 'uploads/reports/sunita_devi_lumbar_xray.txt',
    "L4-L5 disc space narrowing & osteophytosis noted on digital X-ray LS Spine.",
    json_encode([
        'document_title' => 'Digital X-Ray Lumbosacral Spine',
        'document_date' => date('Y-m-d', strtotime('-5 days')),
        'document_type' => 'Radiology Report',
        'is_relevant' => true,
        'relevance_status' => 'RELEVANT',
        'relevance_reason' => 'Demonstrates L4-L5 intervertebral disc narrowing accounting for Sciatica symptoms.',
        'key_findings' => [
            ['test_name' => 'L4-L5 Disc Space', 'value' => 'Narrowed', 'unit' => '', 'reference_range' => 'Normal height', 'flag' => 'ABNORMAL']
        ]
    ])
]);

$stmtTriage->execute([
    $c3,
    "46-year-old female presenting with 6-week subacute history of lower back pain radiating down the posterior right thigh to calf (L5 dermatomal sciatica).\nAggravated by bending forward. Absence of bowel/bladder dysfunction or foot drop. Digital X-ray confirms L4-L5 disc space narrowing.",
    json_encode(['Subacute lower back pain (6 weeks)', 'Right sciatica radiating down leg', 'Tingling in right lateral calf']),
    json_encode([['time' => '6 weeks ago', 'event' => 'Onset of lower back stiffness'], ['time' => '3 weeks ago', 'event' => 'Radiation shooting down right leg']]),
    json_encode(['SLR angle test', 'Deep tendon reflexes', 'MRI LS Spine']),
    json_encode(['Can you walk on your heels and toes without difficulty?'])
]);

$stmtNurse->execute([$c3, $nurseId, "Sunita Devi (46F). Right SLR positive at 45 degrees. Motor power 5/5 bilaterally. No sphincter dysfunction.", "Vitals stable (BP 126/80). Forwarded to physician for specialist orthopedic referral.", json_encode([['question' => 'SLR Test', 'answer' => 'Right SLR positive at 45 degrees']]), 'REVIEWED', date('Y-m-d H:i:s', strtotime('-20 hours'))]);

$stmtDoc->execute([
    $c3, $doctorId,
    "Clinical impression: Right Lumbar Radiculopathy secondary to L4-L5 disc degeneration. Conservative management & MRI recommended.",
    'REFERRED',
    "REFERRAL SUMMARY LETTER\nTo: Department of Orthopedic Spine Surgery, BPUT Health System\nPatient: Sunita Devi (46F) | Case ID: SYN-3190\nClinical Presentation: 6-week right L5 sciatica. Right SLR +ve at 45 deg. X-ray shows L4-L5 disc narrowing.\nPlan: Non-contrast MRI LS Spine, physical therapy, and NSAIDs.",
    date('Y-m-d H:i:s', strtotime('-18 hours'))
]);

$stmtNote->execute([$c3, $nurseId, 'NURSE', 'Nurse Priya Sharma', 'Right SLR positive at 45 deg. Referred for orthopedic evaluation.', date('Y-m-d H:i:s', strtotime('-20 hours'))]);
$stmtNote->execute([$c3, $doctorId, 'DOCTOR', 'Dr. Rajesh Kumar', 'Formal referral letter issued for Orthopedic Spine Department & MRI LS Spine.', date('Y-m-d H:i:s', strtotime('-18 hours'))]);

$stmtAudit->execute([$c3, $doctorId, 'DOCTOR_REFERRED', 'Specialist referral letter issued', date('Y-m-d H:i:s', strtotime('-18 hours'))]);


// ============================================================================
// CASE 4: SYN-4012 — Aarav Sharma (12M) | Status: IN_NURSE_REVIEW (Asthma Exacerbation)
// ============================================================================
$file4 = $reportsDir . '/aarav_sharma_spirometry.txt';
file_put_contents($file4, "========================================================\nPULMONARY FUNCTION TEST & SPIROMETRY\nPatient: Aarav Sharma (12M) | Ref: Pediatrics OPD\nFEV1: 64 % predicted [LOW] (Normal > 80%)\nFEV1/FVC Ratio: 68 % [REDUCED]\nPost-Bronchodilator Reversibility: +16% improvement in FEV1 [POSITIVE FOR ASTHMA]\n========================================================\n");

$stmtCase->execute(['SYN-4012', 'Aarav Sharma', 12, '+91 98112 33445', 'Male', 'English', 'IN_NURSE_REVIEW', 'TEXT', date('Y-m-d H:i:s', strtotime('-3 hours'))]);
$c4 = $db->lastInsertId();

$stmtConv->execute([$c4, 'TEXT', 'PATIENT', 'My 12 year old son Aarav has been coughing continuously since last night with whistling sound (wheezing) when breathing. He had a mild cold 2 days ago.', date('Y-m-d H:i:s', strtotime('-175 minutes'))]);
$stmtConv->execute([$c4, 'TEXT', 'MEDGEM', 'I understand your concern. Is Aarav able to speak full sentences without stopping for air, or is his chest tight?', date('Y-m-d H:i:s', strtotime('-170 minutes'))]);
$stmtConv->execute([$c4, 'TEXT', 'PATIENT', 'He can speak 4-5 words at a time. His chest is tight and his neck muscles are straining a bit when he breathes.', date('Y-m-d H:i:s', strtotime('-165 minutes'))]);

$stmtRep->execute([
    $c4, 'aarav_sharma_spirometry.pdf', 'uploads/reports/aarav_sharma_spirometry.txt',
    "FEV1: 64% predicted [LOW]\nFEV1/FVC: 68%\nPost-bronchodilator response: +16% FEV1 improvement.",
    json_encode([
        'document_title' => 'Pediatric Spirometry & PFT Report',
        'document_date' => date('Y-m-d', strtotime('-10 days')),
        'document_type' => 'Pulmonary Function Test',
        'is_relevant' => true,
        'relevance_status' => 'RELEVANT',
        'relevance_reason' => 'Diagnostic confirmation of reactive airway disease with positive bronchodilator reversibility (+16%).',
        'key_findings' => [
            ['test_name' => 'FEV1 % Predicted', 'value' => '64', 'unit' => '%', 'reference_range' => '> 80%', 'flag' => 'LOW'],
            ['test_name' => 'Bronchodilator Response', 'value' => '+16', 'unit' => '%', 'reference_range' => '< 12%', 'flag' => 'HIGH']
        ]
    ])
]);

$stmtTriage->execute([
    $c4,
    "12-year-old male presenting with acute bronchial asthma exacerbation. Symptoms include dry nocturnal cough, diffuse expiratory wheezing, chest tightness, and accessory muscle use following viral upper respiratory infection.\nSpirometry report shows reduced FEV1 (64% predicted) with +16% post-salbutamol reversibility. Mild dyspnea speaking in short phrases.",
    json_encode(['Expiratory wheezing', 'Nocturnal coughing spells', 'Chest tightness', 'Intercostal straining / dyspnea', 'Known Pediatric Asthma']),
    json_encode([['time' => '2 days ago', 'event' => 'Viral cold & runny nose started'], ['time' => 'Last night', 'event' => 'Frequent coughing & audible wheeze developed']]),
    json_encode(['Oxygen saturation (SpO2)', 'Peak Expiratory Flow Rate (PEFR)', 'Current inhaler technique']),
    json_encode(['When did he last use his rescue (blue) Salbutamol inhaler?'])
]);

$stmtSig->execute([$c4, 'R04', 'Acute expiratory wheezing with respiratory muscle straining', 'Patient Intake (Text)', 'Pediatric bronchospasm requires prompt bronchodilator nebulization & SpO2 check.']);

$stmtNurse->execute([$c4, $nurseId, "Aarav Sharma (12M). Vitals: SpO2 94% room air, HR 110, RR 26/min. Bilateral polyphonic wheeze heard on auscultation.", "Nebulization with Salbutamol 2.5mg + Ipratropium initiated immediately. SpO2 re-check planned in 15 mins.", json_encode([['question' => 'SpO2 & Respiratory Rate', 'answer' => 'SpO2 94% | RR 26/min | HR 110 bpm']]), 'IN_NURSE_REVIEW', date('Y-m-d H:i:s', strtotime('-1 hour'))]);

$stmtNote->execute([$c4, $nurseId, 'NURSE', 'Nurse Priya Sharma', 'Nebulization with Salbutamol 2.5mg STAT administered. Patient comfortable in upright position.', date('Y-m-d H:i:s', strtotime('-1 hour'))]);

$stmtAudit->execute([$c4, null, 'CASE_CREATED', 'Patient intake recorded for pediatric asthma', date('Y-m-d H:i:s', strtotime('-3 hours'))]);


// ============================================================================
// CASE 5: SYN-5501 — Priyanka Das (34F) | Status: COMPLETED (Gastroenteritis)
// ============================================================================
$file5 = $reportsDir . '/priyanka_das_stool_routine.txt';
file_put_contents($file5, "========================================================\nCENTRAL CLINICAL LABORATORY\nPatient: Priyanka Das (34F) | Stool Microscopy & Culture\nConsistency: Watery / Loose\nPus Cells: 8-10 /HPF [ELEVATED]\nRBCs: 2-4 /HPF\nParasites / Ova: None seen\nCulture: Growth of E. Coli sensitive to Ciprofloxacin & Azithromycin.\n========================================================\n");

$stmtCase->execute(['SYN-5501', 'Priyanka Das', 34, '+91 97760 12345', 'Female', 'English', 'COMPLETED', 'TEXT', date('Y-m-d H:i:s', strtotime('-1 day 4 hours'))]);
$c5 = $db->lastInsertId();

$stmtConv->execute([$c5, 'TEXT', 'PATIENT', 'I have watery diarrhea 6-7 times since yesterday with crampy abdominal pain and mild fever (99.8°F). I ate street food 2 days back.', date('Y-m-d H:i:s', strtotime('-27 hours'))]);
$stmtConv->execute([$c5, 'TEXT', 'MEDGEM', 'Hello Priyanka. I have noted the watery loose stools, abdominal cramping, and low fever following food ingestion. Are you able to keep liquids down?', date('Y-m-d H:i:s', strtotime('-26 hours'))]);
$stmtConv->execute([$c5, 'TEXT', 'PATIENT', 'Yes, I am drinking coconut water and ORS. No vomiting today, just abdominal cramps before passing stool.', date('Y-m-d H:i:s', strtotime('-25 hours'))]);

$stmtRep->execute([
    $c5, 'priyanka_das_stool_routine.pdf', 'uploads/reports/priyanka_das_stool_routine.txt',
    "Stool Microscopy: Pus cells 8-10/HPF, watery consistency. Culture: E. Coli sensitive to Ciprofloxacin & Azithromycin.",
    json_encode([
        'document_title' => 'Stool Microscopy & Routine Examination',
        'document_date' => date('Y-m-d', strtotime('-1 day')),
        'document_type' => 'Microbiology Report',
        'is_relevant' => true,
        'relevance_status' => 'RELEVANT',
        'relevance_reason' => 'Confirms acute bacterial enteritis with mild inflammatory exudate (pus cells 8-10/HPF).',
        'key_findings' => [
            ['test_name' => 'Stool Pus Cells', 'value' => '8-10', 'unit' => '/HPF', 'reference_range' => '0 - 2 /HPF', 'flag' => 'HIGH'],
            ['test_name' => 'Stool Consistency', 'value' => 'Watery', 'unit' => '', 'reference_range' => 'Formed', 'flag' => 'ABNORMAL']
        ]
    ])
]);

$stmtTriage->execute([
    $c5,
    "34-year-old female presenting with acute bacterial gastroenteritis following contaminated food consumption.\nSymptoms: watery diarrhea (6-7 episodes/day), periumbilical colic, and low-grade fever (99.8°F). Hydration intact; maintaining oral fluids. Stool report shows 8-10 pus cells/HPF with E. Coli sensitivity.",
    json_encode(['Watery diarrhea (6-7 episodes/day)', 'Abdominal cramps / colic', 'Low-grade fever (99.8°F)', 'History of eating street food']),
    json_encode([['time' => '2 days ago', 'event' => 'Consumed street food'], ['time' => 'Yesterday', 'event' => 'Abdominal cramping & loose stools began']]),
    json_encode(['Hydration status & skin turgor', 'Abdominal palpation for tenderness / guarding']),
    json_encode(['Are you experiencing any blood in stool or high fever?'])
]);

$stmtNurse->execute([$c5, $nurseId, "Priyanka Das (34F). Vitals: BP 118/76, HR 78, Temp 99.2°F, SpO2 99%. Mucous membranes moist; capillary refill < 2s.", "Mild dehydration managed with ORS. Stool report verified. Forwarded for doctor discharge advice.", json_encode([['question' => 'Hydration status', 'answer' => 'Moist mucous membranes, skin turgor normal, Cap refill < 2s']]), 'REVIEWED', date('Y-m-d H:i:s', strtotime('-22 hours'))]);

$stmtDoc->execute([
    $c5, $doctorId,
    "Diagnosis: Acute Bacterial Gastroenteritis (mild to moderate). Hydration stable. Prescribed Tab Ciprofloxacin 500mg BD x 5 days, Tab Dicyclomine 20mg PRN for cramps, ORS sachet 1L daily, and soft bland diet.",
    'COMPLETED',
    '',
    date('Y-m-d H:i:s', strtotime('-20 hours'))
]);

$stmtNote->execute([$c5, $nurseId, 'NURSE', 'Nurse Priya Sharma', 'Oral rehydration therapy administered. Vitals stable.', date('Y-m-d H:i:s', strtotime('-22 hours'))]);
$stmtNote->execute([$c5, $doctorId, 'DOCTOR', 'Dr. Rajesh Kumar', 'Patient evaluated, oral antibiotic & ORS prescription issued. Case completed & discharged.', date('Y-m-d H:i:s', strtotime('-20 hours'))]);

$stmtAudit->execute([$c5, $doctorId, 'DOCTOR_COMPLETED', 'Doctor evaluated case and issued discharge orders', date('Y-m-d H:i:s', strtotime('-20 hours'))]);


echo "\n======================================================\n";
echo "SUCCESS! Cleaned database & created EXACTLY 5 cases:\n";
echo "1. SYN-1042 | Soubhagya Rout (52F) | ESCALATED (Chest Pain & Diabetes)\n";
echo "2. SYN-2085 | Rohan Mohanty (28M)  | PENDING_NURSE_REVIEW (Dengue / Fever 103°F)\n";
echo "3. SYN-3190 | Sunita Devi (46F)    | REFERRED (Sciatica / Specialist Referral)\n";
echo "4. SYN-4012 | Aarav Sharma (12M)   | IN_NURSE_REVIEW (Asthma Exacerbation)\n";
echo "5. SYN-5501 | Priyanka Das (34F)   | COMPLETED (Acute Gastroenteritis)\n";
echo "======================================================\n";
