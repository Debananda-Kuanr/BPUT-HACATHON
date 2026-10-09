<?php
/**
 * MEDGem — Comprehensive Case Tracking Portal
 * Allows patients and healthcare staff to track the full lifecycle of a case:
 * AI Triage Assessment, Multimodal Lab Reports (with relevance check),
 * Nurse Intake Verification (including answered questions), and Doctor Clinical Review.
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/security.php';
setSecurityHeaders();

$trackId = trim($_GET['track_id'] ?? '');
$case = null;
if ($trackId) {
    $case = getCaseByTrackId(sanitize($trackId));
}

$triage = $case['triage'] ?? null;
$signals = $case['signals'] ?? [];
$reports = $case['reports'] ?? [];
$nurseReviews = $case['nurse_reviews'] ?? [];
$doctorReviews = $case['doctor_reviews'] ?? [];
$auditLogs = $case['audit_logs'] ?? [];
$caseNotes = $case['notes'] ?? [];

$latestNurse = !empty($nurseReviews) ? $nurseReviews[0] : null;
$latestDoctor = !empty($doctorReviews) ? $doctorReviews[0] : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $case ? "Case " . sanitize($case['track_id']) : "Track Case"; ?> — MEDGem</title>
    <link rel="stylesheet" href="/BPUT/assets/css/medgem.css">
    <link rel="stylesheet" href="/BPUT/assets/css/patient.css">
    <style>
        .track-section {
            background: #ffffff;
            border: 1px solid var(--neutral-200);
            border-radius: var(--radius-xl);
            padding: 24px;
            margin-bottom: 20px;
            box-shadow: var(--shadow-sm);
        }
        .track-section__header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 16px;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--neutral-150);
            flex-wrap: wrap;
            gap: 10px;
        }
        .track-section__title {
            font-size: var(--text-base);
            font-weight: 700;
            color: var(--neutral-900);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .meta-chip {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: #f1f5f9;
            color: #475569;
            padding: 3px 10px;
            border-radius: var(--radius-full);
            font-size: 12px;
            font-weight: 600;
        }
        .nurse-answer-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: var(--radius-md);
            padding: 12px;
            margin-bottom: 10px;
        }
        .nurse-answer-q {
            font-size: var(--text-xs);
            color: var(--neutral-500);
            font-weight: 600;
            margin-bottom: 4px;
        }
        .nurse-answer-a {
            font-size: var(--text-sm);
            color: var(--primary-900);
            font-weight: 600;
        }
        .status-callout {
            padding: 16px;
            border-radius: var(--radius-lg);
            margin-bottom: 20px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }
        .status-callout.completed {
            background: #ecfdf5;
            border: 1px solid #a7f3d0;
            color: #065f46;
        }
        .status-callout.referred {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            color: #1e40af;
        }
        .status-callout.pending {
            background: #fffbeb;
            border: 1px solid #fde68a;
            color: #92400e;
        }
    </style>
</head>
<body>

<div class="prototype-banner">
    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline;vertical-align:-2px;margin-right:4px"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
    Synthetic Data &middot; Educational Prototype &middot; Patient Case Tracking
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
            <a href="/BPUT/" class="btn btn--secondary btn--sm">&larr; Home</a>
            <a href="/BPUT/patient/information.php" class="btn btn--primary btn--sm">+ New Intake</a>
        </div>
    </div>
</nav>

<main class="patient-flow pt-header">
    <div class="container container--md">

        <?php if (!$trackId): ?>
        <!-- Search form -->
        <div class="patient-card fade-up" style="text-align:center;max-width:540px;margin:0 auto">
            <div style="width:64px;height:64px;border-radius:20px;background:var(--primary-50);color:var(--primary-600);display:flex;align-items:center;justify-content:center;margin:0 auto var(--space-4)">
                <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            </div>
            <h1 class="heading-3">Track Your Medical Case</h1>
            <p class="text-muted mt-2">Enter your Track ID to view the full progress of your case, including AI triage, nurse verification notes, and doctor review results.</p>
            
            <form method="GET" style="margin-top:var(--space-6)">
                <div class="form-group">
                    <input type="text" name="track_id" class="form-input" placeholder="e.g. SYN-1042" style="text-align:center;font-weight:700;font-size:var(--text-lg);font-family:var(--font-mono);letter-spacing:0.05em" required autofocus>
                </div>
                <button type="submit" class="btn btn--primary btn--lg btn--full">
                    Track Case Details
                </button>
            </form>

            <div style="margin-top:var(--space-6);padding-top:var(--space-4);border-top:1px dashed var(--neutral-200);text-align:left">
                <p class="text-xs text-muted" style="font-weight:700;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:var(--space-2)">Try Seeded Clinical Cases:</p>
                <div style="display:flex;flex-direction:column;gap:var(--space-2)">
                    <a href="?track_id=SYN-1042" class="btn btn--ghost btn--sm" style="justify-content:flex-start;font-size:var(--text-xs)">
                        <span style="font-family:var(--font-mono);font-weight:700;color:var(--danger-600)">SYN-1042</span>
                        <span class="text-muted ml-2">&middot; Soubhagya Rout (52F &middot; Acute Chest Pain &middot; Escalated)</span>
                    </a>
                    <a href="?track_id=SYN-2085" class="btn btn--ghost btn--sm" style="justify-content:flex-start;font-size:var(--text-xs)">
                        <span style="font-family:var(--font-mono);font-weight:700;color:var(--warning-600)">SYN-2085</span>
                        <span class="text-muted ml-2">&middot; Rohan Mohanty (28M &middot; Dengue Fever 103°F &middot; Pending Nurse)</span>
                    </a>
                    <a href="?track_id=SYN-3190" class="btn btn--ghost btn--sm" style="justify-content:flex-start;font-size:var(--text-xs)">
                        <span style="font-family:var(--font-mono);font-weight:700;color:#7c3aed">SYN-3190</span>
                        <span class="text-muted ml-2">&middot; Sunita Devi (46F &middot; Sciatica &middot; Referred by Doctor)</span>
                    </a>
                    <a href="?track_id=SYN-4012" class="btn btn--ghost btn--sm" style="justify-content:flex-start;font-size:var(--text-xs)">
                        <span style="font-family:var(--font-mono);font-weight:700;color:#0284c7">SYN-4012</span>
                        <span class="text-muted ml-2">&middot; Aarav Sharma (12M &middot; Asthma Exacerbation &middot; In Nurse Review)</span>
                    </a>
                    <a href="?track_id=SYN-5501" class="btn btn--ghost btn--sm" style="justify-content:flex-start;font-size:var(--text-xs)">
                        <span style="font-family:var(--font-mono);font-weight:700;color:#166534">SYN-5501</span>
                        <span class="text-muted ml-2">&middot; Priyanka Das (34F &middot; Gastroenteritis &middot; Completed &amp; Discharged)</span>
                    </a>
                </div>
            </div>
        </div>

        <?php elseif (!$case): ?>
        <!-- Not found -->
        <div class="patient-card fade-up" style="text-align:center;max-width:540px;margin:0 auto">
            <div style="width:64px;height:64px;border-radius:50%;background:var(--danger-50);color:var(--danger-500);display:flex;align-items:center;justify-content:center;margin:0 auto var(--space-4)">
                <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
            </div>
            <h1 class="heading-3">Case Not Found</h1>
            <p class="text-muted mt-2">No active record found for Track ID: <strong><?php echo sanitize($trackId); ?></strong></p>
            <a href="/BPUT/patient/track.php" class="btn btn--primary btn--lg mt-6">Enter Another ID</a>
        </div>

        <?php else: ?>
        <!-- Case found — Complete Dossier View for Patient -->

        <!-- Top Header Card -->
        <div class="track-section fade-up">
            <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:16px">
                <div>
                    <span style="font-size:12px;font-weight:700;color:var(--neutral-400);text-transform:uppercase;letter-spacing:0.06em">CASE TRACKING</span>
                    <h1 class="heading-3" style="font-family:var(--font-mono);margin:2px 0 6px"><?php echo sanitize($case['track_id']); ?></h1>
                    <div style="display:flex;flex-wrap:wrap;gap:8px">
                        <span class="meta-chip">Patient: <?php echo sanitize($case['name']); ?></span>
                        <span class="meta-chip"><?php echo $case['age']; ?> yrs / <?php echo sanitize($case['sex']); ?></span>
                        <span class="meta-chip">Language: <?php echo sanitize($case['preferred_language']); ?></span>
                        <span class="meta-chip">Created: <?php echo timeAgo($case['created_at']); ?></span>
                    </div>
                </div>
                <div style="text-align:right">
                    <span class="badge badge--<?php echo in_array($case['status'], ['COMPLETED', 'REVIEWED']) ? 'success' : (in_array($case['status'], ['ESCALATED', 'DOCTOR_REVIEW']) ? 'danger' : 'primary'); ?>" style="font-size:13px;padding:6px 14px">
                        <span class="badge__dot"></span>
                        <?php echo formatStatus($case['status']); ?>
                    </span>
                    <div style="margin-top:6px">
                        <a href="/BPUT/patient/track.php" class="text-xs" style="color:var(--primary-600);font-weight:600">Track Another Case &rarr;</a>
                    </div>
                </div>
            </div>

            <!-- Milestone Progress Steps -->
            <div class="progress-steps" style="margin-top:24px">
                <?php
                $statusOrder = ['NEW', 'PROCESSING', 'PENDING_NURSE_REVIEW', 'IN_NURSE_REVIEW', 'REVIEWED', 'ESCALATED', 'DOCTOR_REVIEW', 'COMPLETED', 'REFERRED'];
                $currentIdx = array_search($case['status'], $statusOrder);

                $steps = [
                    ['key' => 'NEW', 'title' => 'Intake Submitted', 'desc' => 'Clinical details recorded'],
                    ['key' => 'IN_NURSE_REVIEW', 'title' => 'Nurse Verification', 'desc' => $latestNurse ? 'Verified by ' . $latestNurse['nurse_name'] : 'In verification queue'],
                    ['key' => 'DOCTOR_REVIEW', 'title' => 'Physician Evaluation', 'desc' => $latestDoctor ? 'Evaluated by ' . $latestDoctor['doctor_name'] : ($case['status'] === 'REVIEWED' ? 'Routine Case Verified' : 'Awaiting Doctor Review')],
                    ['key' => 'COMPLETED', 'title' => in_array($case['status'], ['COMPLETED', 'REFERRED']) ? ($case['status'] === 'REFERRED' ? 'Specialist Referral' : 'Case Completed') : 'Final Decision', 'desc' => in_array($case['status'], ['COMPLETED', 'REFERRED']) ? 'Review finished' : 'Pending final orders']
                ];

                foreach ($steps as $i => $st):
                    $stepIdx = array_search($st['key'], $statusOrder);
                    $done = $currentIdx >= $stepIdx;
                    $active = ($case['status'] === $st['key']);
                    $cls = $done ? 'progress-step--completed' : ($active ? 'progress-step--active' : '');
                ?>
                <div class="progress-step <?php echo $cls; ?>">
                    <div class="progress-step__indicator">
                        <div class="progress-step__dot">
                            <?php if ($done): ?>
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
                            <?php endif; ?>
                        </div>
                        <?php if ($i < count($steps) - 1): ?>
                        <div class="progress-step__line"></div>
                        <?php endif; ?>
                    </div>
                    <div class="progress-step__content">
                        <div class="progress-step__title"><?php echo $st['title']; ?></div>
                        <div class="progress-step__subtitle"><?php echo $st['desc']; ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- 1. DOCTOR REVIEW DETAILS (If doctor has reviewed) -->
        <?php if ($latestDoctor): ?>
        <div class="track-section fade-up" style="border: 2px solid var(--primary-400); background:#f0fdfa;">
            <div class="track-section__header">
                <span class="track-section__title" style="color:var(--primary-800)">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                    Doctor Review &amp; Clinical Orders
                </span>
                <span class="badge badge--success" style="font-size:11px">
                    <?php echo $latestDoctor['decision'] === 'REFERRED' ? 'Specialist Referral Approved' : 'Doctor Evaluation Completed'; ?>
                </span>
            </div>

            <div style="margin-bottom:12px">
                <div style="font-size:12px;color:var(--neutral-500)">Examining Physician:</div>
                <strong style="font-size:var(--text-base);color:var(--neutral-900)"><?php echo htmlspecialchars($latestDoctor['doctor_name']); ?></strong>
                <span style="font-size:12px;color:var(--neutral-400);margin-left:8px"><?php echo timeAgo($latestDoctor['created_at']); ?></span>
            </div>

            <div style="margin-top:14px;background:#ffffff;padding:16px;border-radius:var(--radius-lg);border:1px solid #ccfbf1">
                <div style="font-size:11px;font-weight:700;color:var(--primary-700);text-transform:uppercase;margin-bottom:4px">Physician Notes &amp; Advice:</div>
                <p style="font-size:var(--text-base);color:var(--neutral-800);line-height:1.7;margin:0"><?php echo nl2br(sanitize($latestDoctor['notes'])); ?></p>
            </div>

            <?php if (!empty($latestDoctor['referral_note'])): ?>
            <div style="margin-top:14px;background:#eff6ff;padding:16px;border-radius:var(--radius-lg);border:1px solid #bfdbfe">
                <div style="font-size:11px;font-weight:700;color:#1e40af;text-transform:uppercase;margin-bottom:4px">Formal Referral Summary:</div>
                <pre style="font-size:var(--text-xs);color:#1e3a8a;white-space:pre-wrap;font-family:var(--font-mono);margin:0"><?php echo sanitize($latestDoctor['referral_note']); ?></pre>
            </div>
            <?php endif; ?>
        </div>
        <?php elseif ($case['status'] === 'REVIEWED'): ?>
        <div class="status-callout completed">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            <div>
                <strong>Routine Triage Verified by Healthcare Worker</strong>
                <p style="font-size:var(--text-xs);margin:4px 0 0;line-height:1.5">Your symptoms were verified as routine priority by the nursing team. No urgent doctor escalation was needed. Follow standard health advice below.</p>
            </div>
        </div>
        <?php else: ?>
        <div class="status-callout pending">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            <div>
                <strong>Awaiting Physician Clinical Review</strong>
                <p style="font-size:var(--text-xs);margin:4px 0 0;line-height:1.5">Your case dossier and nurse notes are currently queued for doctor evaluation. Please check back shortly or stay nearby in the clinic.</p>
            </div>
        </div>
        <?php endif; ?>

        <!-- 2. NURSE INTAKE & VERIFICATION DETAILS -->
        <?php if ($latestNurse): ?>
        <div class="track-section fade-up">
            <div class="track-section__header">
                <span class="track-section__title">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    Nurse Intake Verification
                </span>
                <span class="badge badge--info" style="font-size:11px">
                    Verified by <?php echo htmlspecialchars($latestNurse['nurse_name']); ?>
                </span>
            </div>

            <div style="font-size:12px;color:var(--neutral-500);margin-bottom:12px">
                Verified at: <strong><?php echo date('M j, Y — g:i A', strtotime($latestNurse['created_at'])); ?></strong> &middot; Decision: <strong><?php echo sanitize($latestNurse['decision']); ?></strong>
            </div>

            <?php if (!empty($latestNurse['notes'])): ?>
            <div style="background:#f8fafc;padding:14px;border-radius:var(--radius-lg);border:1px solid #e2e8f0;margin-bottom:16px">
                <div style="font-size:11px;font-weight:700;color:var(--neutral-500);text-transform:uppercase;margin-bottom:4px">Nurse Clinical Observations:</div>
                <p style="font-size:var(--text-sm);color:var(--neutral-800);margin:0;line-height:1.6"><?php echo nl2br(sanitize($latestNurse['notes'])); ?></p>
            </div>
            <?php endif; ?>

            <!-- Answered Questions -->
            <?php if (!empty($latestNurse['answered_questions'])): ?>
            <div style="margin-top:14px">
                <div style="font-size:12px;font-weight:700;color:var(--neutral-700);margin-bottom:8px">Questions Answered during Nurse Review:</div>
                <?php foreach ($latestNurse['answered_questions'] as $qa): ?>
                <div class="nurse-answer-box">
                    <div class="nurse-answer-q">Q: <?php echo sanitize($qa['question']); ?></div>
                    <div class="nurse-answer-a">A: <?php echo sanitize($qa['answer']); ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- 2.5 CLINICAL CASE NOTES & UPDATES -->
        <?php if (!empty($caseNotes)): ?>
        <div class="track-section fade-up">
            <div class="track-section__header">
                <span class="track-section__title">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                    Clinical Case Notes &amp; Updates
                </span>
                <span class="meta-chip"><?php echo count($caseNotes); ?> Note<?php echo count($caseNotes) !== 1 ? 's' : ''; ?></span>
            </div>

            <div style="display:flex;flex-direction:column;gap:12px">
                <?php foreach ($caseNotes as $cn):
                    $cnRole = strtoupper($cn['user_role'] ?? 'NURSE');
                    $cnRoleLabel = $cnRole === 'DOCTOR' ? 'Doctor' : 'Nurse';
                    $cnColor = $cnRole === 'DOCTOR' ? '#7c3aed' : '#1e40af';
                    $cnBg    = $cnRole === 'DOCTOR' ? '#f5f3ff' : '#eff6ff';
                    $cnBorder= $cnRole === 'DOCTOR' ? '#ddd6fe' : '#bfdbfe';
                ?>
                <div style="background:<?php echo $cnBg; ?>;border:1px solid <?php echo $cnBorder; ?>;border-radius:var(--radius-lg);padding:14px 16px">
                    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:6px;margin-bottom:8px">
                        <div style="display:flex;align-items:center;gap:8px">
                            <div style="width:26px;height:26px;border-radius:50%;background:<?php echo $cnColor; ?>;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                            </div>
                            <div>
                                <strong style="font-size:13px;color:#0f172a"><?php echo htmlspecialchars($cn['user_name'] ?? $cnRoleLabel); ?></strong>
                                <span style="font-size:10px;font-weight:700;text-transform:uppercase;color:<?php echo $cnColor; ?>;margin-left:4px">(<?php echo $cnRoleLabel; ?>)</span>
                            </div>
                        </div>
                        <span style="font-size:11px;color:#94a3b8;display:flex;align-items:center;gap:4px">
                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                            <?php echo date('M j, Y — g:i A', strtotime($cn['created_at'])); ?>
                        </span>
                    </div>
                    <p style="font-size:var(--text-sm);color:#334155;line-height:1.6;margin:0;white-space:pre-wrap"><?php echo htmlspecialchars($cn['note']); ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- 3. MEDICAL REPORTS & MULTIMODAL EVALUATION -->
        <?php if (!empty($reports)): ?>
        <div class="track-section fade-up">
            <div class="track-section__header">
                <span class="track-section__title">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="9" y1="9" x2="15" y2="9"/><line x1="9" y1="13" x2="15" y2="13"/><line x1="9" y1="17" x2="11" y2="17"/></svg>
                    Uploaded Medical Reports (AI Evaluated)
                </span>
                <span class="meta-chip"><?php echo count($reports); ?> File<?php echo count($reports) > 1 ? 's' : ''; ?></span>
            </div>

            <?php foreach ($reports as $rep): 
                $ev = $rep['extracted_values'] ?? null;
                $isRelevant = $ev['is_relevant'] ?? true;
            ?>
            <div style="border:1px solid #e2e8f0;border-left:4px solid <?php echo $isRelevant ? 'var(--success-500)' : 'var(--warning-500)'; ?>;background:<?php echo $isRelevant ? '#ffffff' : '#fffdf5'; ?>;border-radius:var(--radius-lg);padding:14px;margin-bottom:12px">
                <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px">
                    <div>
                        <strong style="font-size:var(--text-sm);color:var(--neutral-900)"><?php echo sanitize($rep['file_name']); ?></strong>
                        <?php if (!empty($ev['document_date'])): ?>
                        <span style="font-size:11px;color:var(--neutral-500);margin-left:6px">(Dated: <?php echo sanitize($ev['document_date']); ?>)</span>
                        <?php endif; ?>
                    </div>
                    <div>
                        <?php if (!empty($ev['nurse_verified'])): ?>
                            <span class="badge badge--success" style="font-size:10px">✓ Clinically Verified by Nurse</span>
                        <?php endif; ?>
                        <?php if ($isRelevant): ?>
                            <span class="badge badge--success" style="font-size:10px">RELEVANT TO CURRENT CONDITION</span>
                        <?php else: ?>
                            <span class="badge badge--warning" style="font-size:10px">IGNORED &middot; OUTDATED / UNRELATED</span>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (!empty($ev['nurse_note'])): ?>
                <div style="margin:6px 0;padding:6px 10px;background:#f0fdfa;border:1px solid #ccfbf1;border-radius:var(--radius-md);font-size:12px;color:#0f766e">
                    <strong>Nurse Clinical Note:</strong> <?php echo sanitize($ev['nurse_note']); ?>
                </div>
                <?php endif; ?>

                <?php if (!empty($ev['relevance_reason'])): ?>
                <div style="margin:6px 0;font-size:12px;color:<?php echo $isRelevant ? '#15803d' : '#b45309'; ?>">
                    <strong>Assessment:</strong> <?php echo sanitize($ev['relevance_reason']); ?>
                </div>
                <?php endif; ?>

                <?php if (!empty($ev['key_findings']) && is_array($ev['key_findings'])): ?>
                <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:8px">
                    <?php foreach ($ev['key_findings'] as $kf): 
                        $flag = strtoupper($kf['flag'] ?? 'NORMAL');
                        $flagBg = in_array($flag, ['HIGH', 'CRITICAL']) ? '#fee2e2' : ($flag === 'LOW' ? '#fef3c7' : '#f1f5f9');
                        $flagColor = in_array($flag, ['HIGH', 'CRITICAL']) ? '#b91c1c' : ($flag === 'LOW' ? '#b45309' : '#334155');
                    ?>
                    <span style="background:<?php echo $flagBg; ?>;color:<?php echo $flagColor; ?>;border:1px solid rgba(0,0,0,0.08);padding:3px 8px;border-radius:6px;font-size:11px">
                        <strong><?php echo sanitize($kf['test_name'] ?? ''); ?>:</strong> <?php echo sanitize($kf['value'] ?? ''); ?> <?php echo sanitize($kf['unit'] ?? ''); ?> [<?php echo $flag; ?>]
                    </span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- 4. AI TRIAGE SUMMARY -->
        <?php if ($triage): ?>
        <div class="track-section fade-up">
            <div class="track-section__header">
                <span class="track-section__title">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                    Clinical Triage Summary
                </span>
                <span class="meta-chip">Multimodal Intake</span>
            </div>

            <div style="margin-bottom:12px">
                <div style="font-size:11px;font-weight:700;color:var(--neutral-400);text-transform:uppercase">Chief Complaint:</div>
                <div style="font-size:var(--text-base);font-weight:700;color:var(--neutral-900)"><?php echo !empty($triage['symptoms']) ? implode(', ', $triage['symptoms']) : 'Under review'; ?></div>
            </div>

            <div style="margin-bottom:12px">
                <div style="font-size:11px;font-weight:700;color:var(--neutral-400);text-transform:uppercase">Summary (English Dossier):</div>
                <p style="font-size:var(--text-sm);color:var(--neutral-700);line-height:1.7;margin:4px 0 0"><?php echo nl2br(sanitize($triage['summary'])); ?></p>
            </div>

            <?php if (!empty($signals)): ?>
            <div style="margin-top:14px;padding:12px;background:#fef2f2;border:1px solid #fecaca;border-radius:var(--radius-md)">
                <div style="font-weight:700;font-size:12px;color:#991b1b;margin-bottom:4px">Urgency Signals Detected:</div>
                <?php foreach ($signals as $sig): ?>
                <div style="font-size:12px;color:#7f1d1d">&bull; <?php echo sanitize($sig['signal']); ?> — <?php echo sanitize($sig['reason']); ?></div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <div style="text-align:center;margin-top:28px">
            <a href="/BPUT/" class="btn btn--ghost btn--sm" style="color:var(--neutral-500)">
                &larr; Return to Home Portal
            </a>
        </div>

        <?php endif; ?>

    </div>
</main>

<script src="/BPUT/assets/js/app.js"></script>
</body>
</html>
