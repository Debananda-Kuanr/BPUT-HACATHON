<?php
/**
 * MEDGem — Success Page
 * Case created, display Track ID
 */
require_once __DIR__ . '/../includes/security.php';
setSecurityHeaders();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Case Created — MEDGem</title>
    <link rel="stylesheet" href="/BPUT/assets/css/medgem.css">
    <link rel="stylesheet" href="/BPUT/assets/css/patient.css">
</head>
<body>

<div class="prototype-banner">
    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline;vertical-align:-2px;margin-right:4px"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
    Synthetic Data &middot; Educational Prototype
</div>

<nav class="navbar">
    <div class="navbar__inner">
        <a href="/BPUT/" class="navbar__logo">
            <span class="navbar__logo-icon" style="background:linear-gradient(135deg, #396cf0 0%, #1c58ef 100%);border-radius:10px">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
            </span>
            <span style="font-weight:800;font-family:var(--font-heading, 'Raleway', sans-serif);color:#0f172a">MEDGem<span style="color:#396cf0">Care</span></span>
        </a>
    </div>
</nav>

<main class="patient-flow pt-header" style="display:flex;align-items:center;justify-content:center">
    <div class="container container--sm">
        <div class="patient-card fade-up" style="text-align:center">
            
            <div class="success-icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
            </div>

            <h1 class="heading-3">Your case has been created</h1>
            <p class="text-muted mt-2">A healthcare worker will review your information. Save your Track ID to check status later.</p>

            <div class="track-id-display">
                <div class="track-id-label">Track ID</div>
                <div class="track-id-value" id="trackIdValue">SYN-0000</div>
            </div>

            <div style="display:flex;gap:var(--space-3)">
                <button class="btn btn--secondary btn--lg" style="flex:1" onclick="copyTrackId()">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                    Copy ID
                </button>
                <a href="/BPUT/patient/track.php" class="btn btn--primary btn--lg" style="flex:1">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    Track Case
                </a>
            </div>

            <div class="divider"></div>

            <p style="font-size:var(--text-sm);color:var(--neutral-500)">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline;vertical-align:-2px;margin-right:4px"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                Your information will be reviewed by a healthcare professional. This is not a diagnosis.
            </p>
        </div>
    </div>
</main>

<script src="/BPUT/assets/js/app.js"></script>
<script>
const trackId = sessionStorage.getItem('medgem_track_id') || 'SYN-0000';
document.getElementById('trackIdValue').textContent = trackId;

function copyTrackId() {
    navigator.clipboard.writeText(trackId).then(() => {
        showToast('Track ID copied to clipboard');
    }).catch(() => {
        // Fallback
        const el = document.createElement('textarea');
        el.value = trackId;
        document.body.appendChild(el);
        el.select();
        document.execCommand('copy');
        document.body.removeChild(el);
        showToast('Track ID copied');
    });
}
</script>
</body>
</html>
