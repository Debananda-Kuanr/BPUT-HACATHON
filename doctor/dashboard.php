<?php
/**
 * MEDGem — Doctor Dashboard
 * Clean MediLink-Inspired Minimal Interface
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/security.php';
setSecurityHeaders();
requireAuth('DOCTOR');

$user = getCurrentUser();
$stats = getDoctorStats();
$filter = $_GET['filter'] ?? 'all';
$search = trim($_GET['search'] ?? '');
$allCases = getDoctorCaseQueue($filter, $search);

// Pagination logic
$perPage = 6;
$totalCases = count($allCases);
$totalPages = max(1, (int)ceil($totalCases / $perPage));
$page = max(1, min($totalPages, (int)($_GET['page'] ?? 1)));
$offset = ($page - 1) * $perPage;
$cases = array_slice($allCases, $offset, $perPage);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Physician Station — MEDGem</title>
    <link rel="stylesheet" href="/BPUT/assets/css/medgem.css">
    <link rel="stylesheet" href="/BPUT/assets/css/dashboard.css">
</head>
<body>

<div class="prototype-banner">
    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
    Synthetic Data &middot; Educational Clinical Prototype
</div>

<nav class="navbar">
    <div class="navbar__inner" style="flex-wrap:nowrap">
        <a href="/BPUT/" class="navbar__logo" style="flex-shrink:0">
            <span class="navbar__logo-icon" style="background:linear-gradient(135deg, #1c58ef 0%, #1444c4 100%);border-radius:10px">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
            </span>
            <span style="font-weight:800;font-family:var(--font-heading, 'Raleway', sans-serif);color:#0f172a">MEDGem<span style="color:#1c58ef">Care</span></span>
        </a>
        <div class="flex items-center gap-2" style="flex-shrink:0">
            <span class="navbar__link hide-mobile" style="cursor:default;font-weight:600;color:var(--neutral-800);white-space:nowrap">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:inline;vertical-align:-2px;margin-right:4px"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                Dr. <?php echo htmlspecialchars($user['name']); ?>
            </span>
            <a href="/BPUT/staff/login.php?logout=1" class="btn btn--sm" style="color:var(--danger-600);background:var(--danger-50);border:1px solid var(--danger-200);font-weight:600;padding:6px 12px;white-space:nowrap;display:inline-flex;align-items:center;gap:6px">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                Logout
            </a>
        </div>
    </div>
</nav>

<main class="dashboard-main" style="margin-left:0;padding-top:120px;padding-bottom:60px">
    <div class="container container--lg">
        <!-- Minimal Dashboard Header -->
        <div class="dash-header" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px">
            <div>
                <h1 class="dash-header__title" style="font-size:1.65rem;margin:0">Physician Case Review</h1>
                <p class="dash-header__subtitle" style="margin:2px 0 0 0;font-size:0.875rem">Clinical evaluations, differential diagnosis, and prescription management</p>
            </div>
            <div style="display:flex;align-items:center;gap:10px">
                <span class="badge badge--success" style="font-size:11px;padding:4px 10px">
                    <span class="badge__dot"></span> Attending Active
                </span>
            </div>
        </div>

        <!-- 4 Small Stat Cards in Single Line -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-card__icon stat-card__icon--primary">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="17 11 12 6 7 11"/><polyline points="17 18 12 13 7 18"/></svg>
                </div>
                <div class="stat-card__content">
                    <div class="stat-card__value"><?php echo $stats['total']; ?></div>
                    <div class="stat-card__label">Total Cases</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-card__icon stat-card__icon--danger">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                </div>
                <div class="stat-card__content">
                    <div class="stat-card__value"><?php echo $stats['urgent']; ?></div>
                    <div class="stat-card__label">Urgent (Red)</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-card__icon stat-card__icon--warning">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                </div>
                <div class="stat-card__content">
                    <div class="stat-card__value"><?php echo $stats['in_review']; ?></div>
                    <div class="stat-card__label">In Doctor Review</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-card__icon stat-card__icon--info">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                </div>
                <div class="stat-card__content">
                    <div class="stat-card__value"><?php echo $stats['completed']; ?></div>
                    <div class="stat-card__label">Completed / Referred</div>
                </div>
            </div>
        </div>

        <!-- Case Queue Header & Filters -->
        <div class="queue-header" style="margin-bottom:16px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:14px">
            <div>
                <h2 class="queue-title" style="margin:0;font-size:1.15rem">Review Queue</h2>
            </div>

            <!-- Search Bar for Searching Case by ID or Name -->
            <form method="GET" action="/BPUT/doctor/dashboard.php" style="display:flex;align-items:center;gap:8px;max-width:380px;width:100%">
                <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
                <div style="position:relative;width:100%">
                    <input type="text" 
                           name="search" 
                           class="form-input" 
                           placeholder="Search Case by ID (e.g. SYN-1042)..." 
                           value="<?php echo htmlspecialchars($search); ?>" 
                           style="padding-left:36px;height:38px;font-size:13px;border-radius:8px;width:100%;border:1px solid #cbd5e1;box-shadow:0 1px 3px rgba(0,0,0,0.05)">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#64748b" stroke-width="2" style="position:absolute;left:11px;top:50%;transform:translateY(-50%)"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                </div>
                <button type="submit" class="btn btn--primary btn--sm" style="height:38px;padding:0 16px;white-space:nowrap;font-weight:700">Search</button>
                <?php if ($search !== ''): ?>
                <a href="/BPUT/doctor/dashboard.php?filter=<?php echo htmlspecialchars($filter); ?>" class="btn btn--ghost btn--sm" style="height:38px;padding:0 10px;color:#64748b;white-space:nowrap">Clear</a>
                <?php endif; ?>
            </form>

            <div class="queue-filters">
                <a href="?filter=all<?php echo $search ? '&search='.urlencode($search) : ''; ?>" class="queue-filter <?php echo $filter === 'all' ? 'active' : ''; ?>">All (<?php echo $stats['total']; ?>)</a>
                <a href="?filter=new<?php echo $search ? '&search='.urlencode($search) : ''; ?>" class="queue-filter <?php echo $filter === 'new' ? 'active' : ''; ?>">New</a>
                <a href="?filter=urgent<?php echo $search ? '&search='.urlencode($search) : ''; ?>" class="queue-filter <?php echo $filter === 'urgent' ? 'active' : ''; ?>">Urgent (<?php echo $stats['urgent']; ?>)</a>
                <a href="?filter=routine<?php echo $search ? '&search='.urlencode($search) : ''; ?>" class="queue-filter <?php echo $filter === 'routine' ? 'active' : ''; ?>">Routine</a>
                <a href="?filter=reviewed<?php echo $search ? '&search='.urlencode($search) : ''; ?>" class="queue-filter <?php echo $filter === 'reviewed' ? 'active' : ''; ?>">Reviewed</a>
                <a href="?filter=completed<?php echo $search ? '&search='.urlencode($search) : ''; ?>" class="queue-filter <?php echo $filter === 'completed' ? 'active' : ''; ?>">Completed</a>
            </div>
        </div>

        <?php if ($search !== ''): ?>
        <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;padding:8px 14px;font-size:12px;color:#1e40af;margin-bottom:16px;display:flex;align-items:center;justify-content:space-between">
            <span>Showing search results for: <strong>"<?php echo htmlspecialchars($search); ?>"</strong> (<?php echo count($allCases); ?> matching case<?php echo count($allCases) !== 1 ? 's' : ''; ?>)</span>
            <a href="/BPUT/doctor/dashboard.php?filter=<?php echo htmlspecialchars($filter); ?>" style="color:#1d4ed8;font-weight:700;text-decoration:none">&times; Reset Search</a>
        </div>
        <?php endif; ?>

        <!-- Case Cards List -->
        <?php if (empty($cases)): ?>
        <div class="empty-state" style="background:#ffffff;border:1px solid #e2e8f0;border-radius:14px;padding:48px 24px">
            <div class="empty-state__icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1" ry="1"/></svg>
            </div>
            <div class="empty-state__title" style="font-size:1.1rem;margin-top:8px">No escalated cases</div>
            <p style="color:#64748b;font-size:0.875rem">No patient records match the selected filter.</p>
        </div>
        <?php else: ?>
        <div style="display:flex;flex-direction:column;gap:12px">
            <?php foreach ($cases as $case): ?>
            <a href="/BPUT/doctor/case.php?id=<?php echo $case['id']; ?>" style="text-decoration:none;color:inherit;display:block">
                <div class="case-card <?php echo $case['signal_count'] > 0 ? 'case-card--urgent' : ''; ?>" style="margin-bottom:0">
                    <div class="case-card__header">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="case-card__id" style="font-size:1rem;color:#1c58ef"><?php echo sanitize($case['track_id']); ?></span>
                                <span style="font-size:0.75rem;color:#94a3b8">&bull;</span>
                                <span style="font-weight:600;color:#0f172a;font-size:0.95rem"><?php echo sanitize($case['name']); ?></span>
                            </div>
                            <div class="case-card__patient" style="margin-top:2px;font-size:0.825rem">
                                <?php echo $case['age']; ?> yrs &middot; <?php echo $case['sex']; ?> &middot; Arrived <?php echo timeAgo($case['created_at']); ?>
                            </div>
                        </div>
                        <span class="badge badge--<?php echo $case['signal_count'] > 0 ? 'danger' : (in_array($case['status'], ['DOCTOR_REVIEW', 'ESCALATED']) ? 'warning' : 'primary'); ?>" style="font-size:11px;padding:4px 9px">
                            <span class="badge__dot"></span>
                            <?php echo formatStatus($case['status']); ?>
                        </span>
                    </div>

                    <?php if ($case['signals']): ?>
                    <div class="case-card__symptoms" style="margin:8px 0 0 0">
                        <?php foreach (explode(', ', $case['signals']) as $signal): ?>
                        <span class="symptom-tag symptom-tag--urgent" style="font-size:11px;padding:2px 8px"><?php echo sanitize($signal); ?></span>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <?php if ($case['nurse_notes']): ?>
                    <div style="font-size:0.8rem;color:#475569;margin-top:8px;background:#f8fafc;padding:6px 10px;border-radius:6px;border-left:2px solid #cbd5e1">
                        <strong style="color:#1e293b">Nurse Handover:</strong> <?php echo sanitize(substr($case['nurse_notes'], 0, 110)); ?><?php echo strlen($case['nurse_notes']) > 110 ? '...' : ''; ?>
                    </div>
                    <?php endif; ?>

                    <div class="case-card__footer" style="margin-top:12px;padding-top:10px">
                        <span class="case-card__time" style="font-size:0.8rem">
                            Priority: <strong style="color:<?php echo $case['signal_count'] > 0 ? '#ef4444' : '#1c58ef'; ?>;font-weight:600"><?php echo $case['signal_count'] > 0 ? 'Urgent Attention' : 'Standard Clinical'; ?></strong>
                        </span>
                        <span class="btn btn--ghost btn--sm" style="pointer-events:none;padding:4px 10px;font-size:0.8rem;gap:4px">
                            Open Clinical Chart
                            <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
                        </span>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>

        <!-- Clean Pagination Controls -->
        <?php if ($totalPages > 1): ?>
        <div class="dash-pagination">
            <div class="dash-pagination__info">
                Showing <strong><?php echo $offset + 1; ?>&ndash;<?php echo min($offset + $perPage, $totalCases); ?></strong> of <strong><?php echo $totalCases; ?></strong> cases
            </div>
            <div class="dash-pagination__controls">
                <!-- Previous Button -->
                <a href="?filter=<?php echo urlencode($filter); ?>&page=<?php echo max(1, $page - 1); ?>" 
                   class="dash-page-btn <?php echo $page <= 1 ? 'disabled' : ''; ?>"
                   aria-label="Previous Page">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
                </a>

                <!-- Numbered Page Buttons -->
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a href="?filter=<?php echo urlencode($filter); ?>&page=<?php echo $i; ?>" 
                   class="dash-page-btn <?php echo $i === $page ? 'active' : ''; ?>">
                    <?php echo $i; ?>
                </a>
                <?php endfor; ?>

                <!-- Next Button -->
                <a href="?filter=<?php echo urlencode($filter); ?>&page=<?php echo min($totalPages, $page + 1); ?>" 
                   class="dash-page-btn <?php echo $page >= $totalPages ? 'disabled' : ''; ?>"
                   aria-label="Next Page">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
                </a>
            </div>
        </div>
        <?php endif; ?>

        <?php endif; ?>
    </div>
</main>

<script src="/BPUT/assets/js/app.js"></script>
</body>
</html>
