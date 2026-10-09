<?php
/**
 * MEDGem — Processing Animation Page
 * Shows AI processing steps before case completion
 */
require_once __DIR__ . '/../includes/security.php';
setSecurityHeaders();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Processing — MEDGem</title>
    <link rel="stylesheet" href="/BPUT/assets/css/medgem.css">
    <link rel="stylesheet" href="/BPUT/assets/css/patient.css">
</head>
<body>

<div class="prototype-banner">
    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline;vertical-align:-2px;margin-right:4px"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
    Synthetic Data &middot; Educational Prototype
</div>

<main class="patient-flow" style="display:flex;align-items:center;justify-content:center;min-height:100vh;padding-top:28px">
    <div class="container container--sm">
        <div class="patient-card fade-up" style="text-align:center">
            <div style="margin-bottom:var(--space-6)">
                <div class="spinner spinner--lg" style="margin:0 auto"></div>
            </div>
            <h1 class="heading-3">Preparing Your Case</h1>
            <p class="text-muted mt-2">MEDGem is organizing your information for healthcare-worker review.</p>

            <div class="processing-steps" id="processingSteps">
                <div class="processing-step" id="step1">
                    <div class="processing-step__icon">
                        <span class="spinner" style="width:18px;height:18px;border-width:2px"></span>
                    </div>
                    <span>Patient information</span>
                </div>
                <div class="processing-step" id="step2">
                    <div class="processing-step__icon"></div>
                    <span>Symptom extraction</span>
                </div>
                <div class="processing-step" id="step3">
                    <div class="processing-step__icon"></div>
                    <span>Timeline generation</span>
                </div>
                <div class="processing-step" id="step4">
                    <div class="processing-step__icon"></div>
                    <span>Missing information check</span>
                </div>
                <div class="processing-step" id="step5">
                    <div class="processing-step__icon"></div>
                    <span>Safety rule evaluation</span>
                </div>
                <div class="processing-step" id="step6">
                    <div class="processing-step__icon"></div>
                    <span>Generating summary</span>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
const caseId = sessionStorage.getItem('medgem_case_id');
if (!caseId) window.location.href = '/BPUT/patient/information.php';

const checkSvg = '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--primary-600)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>';
const spinnerHtml = '<span class="spinner" style="width:18px;height:18px;border-width:2px"></span>';

async function animateSteps() {
    for (let i = 1; i <= 6; i++) {
        const step = document.getElementById('step' + i);
        step.classList.add('active');
        step.querySelector('.processing-step__icon').innerHTML = spinnerHtml;
        
        await new Promise(r => setTimeout(r, 600 + Math.random() * 400));
        
        step.classList.remove('active');
        step.classList.add('done');
        step.querySelector('.processing-step__icon').innerHTML = checkSvg;
    }
    
    // Update case status
    try {
        await fetch('/BPUT/api/patient.php', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ case_id: caseId, status: 'PENDING_NURSE_REVIEW' })
        });
    } catch(e) {}
    
    setTimeout(() => {
        window.location.href = '/BPUT/patient/success.php';
    }, 800);
}

animateSteps();
</script>
</body>
</html>
