<?php
/**
 * MEDGem — Nurse Dashboard
 * Clean MediLink-Inspired Minimal Interface
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/security.php';
setSecurityHeaders();
requireAuth('NURSE');

$user = getCurrentUser();
$stats = getNurseStats();
$filter = $_GET['filter'] ?? 'all';
$search = trim($_GET['search'] ?? '');
$allCases = getNurseCaseQueue($filter, $search);

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
    <title>Nurse Station — MEDGem</title>
    <link rel="stylesheet" href="/BPUT/assets/css/medgem.css">
    <link rel="stylesheet" href="/BPUT/assets/css/dashboard.css">
</head>
<body>

<div class="prototype-banner">
    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
    Synthetic Data &middot; Educational Clinical Prototype
</div>

<nav class="navbar">
    <div class="navbar__inner">
        <a href="/BPUT/" class="navbar__logo">
            <span class="navbar__logo-icon" style="background:linear-gradient(135deg, #1c58ef 0%, #1444c4 100%);border-radius:10px">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
            </span>
            <span style="font-weight:800;font-family:var(--font-heading, 'Raleway', sans-serif);color:#0f172a">MEDGem<span style="color:#1c58ef">Care</span></span>
        </a>
        <div class="flex items-center gap-3">
            <span class="navbar__link hide-mobile" style="cursor:default;color:var(--neutral-800);font-weight:600">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:inline;vertical-align:-2px;margin-right:4px"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                <?php echo htmlspecialchars($user['name']); ?>
            </span>
            <a href="/BPUT/staff/login.php?logout=1" class="btn btn--sm" style="color:var(--danger-600);background:var(--danger-50);border:1px solid var(--danger-200);font-weight:600;display:inline-flex;align-items:center;gap:6px">
                <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
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
                <h1 class="dash-header__title" style="font-size:1.65rem;margin:0">Nurse Intake &amp; Triage Station</h1>
                <p class="dash-header__subtitle" style="margin:2px 0 0 0;font-size:0.875rem">Review triage assessments, verify patient reports, and route cases</p>
            </div>
            <div style="display:flex;align-items:center;gap:10px">
                <span class="badge badge--success" style="font-size:11px;padding:4px 10px">
                    <span class="badge__dot"></span> Station Active
                </span>
            </div>
        </div>

        <!-- 4 Small Stat Cards in Single Line -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-card__icon stat-card__icon--primary">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
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
                    <div class="stat-card__value"><?php echo $stats['pending']; ?></div>
                    <div class="stat-card__label">Pending Review</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-card__icon stat-card__icon--info">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                </div>
                <div class="stat-card__content">
                    <div class="stat-card__value"><?php echo $stats['completed']; ?></div>
                    <div class="stat-card__label">Reviewed / Cleared</div>
                </div>
            </div>
        </div>

        <!-- Minimal Clinical Safety Rule Note -->
        <div style="background:#ffffff;border:1px solid #e2e8f0;border-left:3px solid #1c58ef;border-radius:10px;padding:10px 16px;margin-bottom:24px;display:flex;align-items:center;gap:12px">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#1c58ef" stroke-width="2" style="flex-shrink:0"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
            <div style="font-size:0.825rem;color:#475569">
                <strong style="color:#0f172a">Clinical Safety Protocol:</strong> AI triage sorting (Red/Yellow/Green) assists queue organization. Physical nurse clinical assessment always takes final precedence.
            </div>
        </div>

        <!-- Case Queue Header & Filters -->
        <div class="queue-header" style="margin-bottom:16px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:14px">
            <div>
                <h2 class="queue-title" style="margin:0;font-size:1.15rem">Intake Queue</h2>
            </div>

            <!-- Search Bar for Searching Case by ID or Name -->
            <form method="GET" action="/BPUT/nurse/dashboard.php" style="display:flex;align-items:center;gap:8px;max-width:380px;width:100%">
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
                <a href="/BPUT/nurse/dashboard.php?filter=<?php echo htmlspecialchars($filter); ?>" class="btn btn--ghost btn--sm" style="height:38px;padding:0 10px;color:#64748b;white-space:nowrap">Clear</a>
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
            <a href="/BPUT/nurse/dashboard.php?filter=<?php echo htmlspecialchars($filter); ?>" style="color:#1d4ed8;font-weight:700;text-decoration:none">&times; Reset Search</a>
        </div>
        <?php endif; ?>

        <!-- Case Cards List -->
        <?php if (empty($cases)): ?>
        <div class="empty-state" style="background:#ffffff;border:1px solid #e2e8f0;border-radius:14px;padding:48px 24px">
            <div class="empty-state__icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
            </div>
            <div class="empty-state__title" style="font-size:1.1rem;margin-top:8px">No cases in this queue</div>
            <p style="color:#64748b;font-size:0.875rem">No patient records match the selected filter.</p>
        </div>
        <?php else: ?>
        <div style="display:flex;flex-direction:column;gap:12px">
            <?php foreach ($cases as $c): ?>
            <a href="/BPUT/nurse/case.php?id=<?php echo $c['id']; ?>" style="text-decoration:none;color:inherit;display:block">
                <div class="case-card <?php echo $c['signal_count'] > 0 ? 'case-card--urgent' : ''; ?>" style="margin-bottom:0">
                    <div class="case-card__header">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="case-card__id" style="font-size:1rem;color:#1c58ef"><?php echo sanitize($c['track_id']); ?></span>
                                <span style="font-size:0.75rem;color:#94a3b8">&bull;</span>
                                <span style="font-weight:600;color:#0f172a;font-size:0.95rem"><?php echo sanitize($c['name']); ?></span>
                            </div>
                            <div class="case-card__patient" style="margin-top:2px;font-size:0.825rem">
                                <?php echo $c['age']; ?> yrs &middot; <?php echo $c['sex']; ?> &middot; Arrived <?php echo timeAgo($c['created_at']); ?>
                            </div>
                        </div>
                        <span class="badge badge--<?php echo $c['signal_count'] > 0 ? 'danger' : (in_array($c['status'], ['PENDING_NURSE_REVIEW', 'IN_NURSE_REVIEW']) ? 'warning' : 'primary'); ?>" style="font-size:11px;padding:4px 9px">
                            <span class="badge__dot"></span>
                            <?php echo formatStatus($c['status']); ?>
                        </span>
                    </div>

                    <?php if (!empty($c['signals'])): ?>
                    <div class="case-card__symptoms" style="margin:8px 0 0 0">
                        <?php foreach (explode(', ', $c['signals']) as $sig): ?>
                        <span class="symptom-tag symptom-tag--urgent" style="font-size:11px;padding:2px 8px"><?php echo sanitize($sig); ?></span>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <div class="case-card__footer" style="margin-top:12px;padding-top:10px">
                        <span class="case-card__time" style="font-size:0.8rem">
                            Mode: <strong style="color:#475569;font-weight:600"><?php echo htmlspecialchars($c['intake_mode'] ?? 'Online'); ?></strong>
                        </span>
                        <span class="btn btn--ghost btn--sm" style="pointer-events:none;padding:4px 10px;font-size:0.8rem;gap:4px">
                            Open Assessment
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
