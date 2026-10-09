<?php
/**
 * MEDGem — Consent Page
 * Step 2: Patient consent before proceeding
 * Localized dynamically to English, Hindi, or Odia.
 */
require_once __DIR__ . '/../includes/security.php';
setSecurityHeaders();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>Consent — MEDGem</title>
    <link rel="stylesheet" href="/BPUT/assets/css/medgem.css">
    <link rel="stylesheet" href="/BPUT/assets/css/patient.css">
</head>
<body class="mobile-fit-body">

<header class="patient-compact-header">
    <div class="patient-compact-header__inner" style="justify-content:flex-start;">
        <a href="/BPUT/" class="patient-compact-logo">
            <span class="patient-compact-logo__icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
            </span>
            <span class="patient-compact-logo__text">MEDGem<span style="color:#1c58ef">Care</span></span>
        </a>
    </div>
</header>

<main class="patient-flow-compact">
    <div class="patient-flow-compact__container">
        
        <nav class="patient-progress-compact" aria-label="Progress">
            <div class="patient-progress-compact__step">
                <span class="patient-progress-compact__dot" style="background:var(--primary-500)"></span>
                <span class="patient-progress-compact__label" id="stepLabel1">Information</span>
            </div>
            <div class="patient-progress-compact__line" style="background:var(--primary-300)"></div>
            <div class="patient-progress-compact__step active">
                <span class="patient-progress-compact__dot"></span>
                <span class="patient-progress-compact__label" id="stepLabel2">Consent</span>
            </div>
            <div class="patient-progress-compact__line"></div>
            <div class="patient-progress-compact__step">
                <span class="patient-progress-compact__dot"></span>
                <span class="patient-progress-compact__label" id="stepLabel3">Assessment</span>
            </div>
        </nav>

        <div class="patient-card-compact fade-up">
            <div class="patient-card-compact__header">
                <div style="display:flex;align-items:center;gap:10px;margin-bottom:6px">
                    <div style="width:34px;height:34px;border-radius:10px;background:var(--warning-100);display:flex;align-items:center;justify-content:center;color:var(--warning-600);flex-shrink:0">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                    </div>
                    <div>
                        <h1 class="patient-card-compact__title" id="consentTitle">Before You Continue</h1>
                        <p class="patient-card-compact__subtitle" id="consentSubtitle">MEDGem is an educational healthcare triage prototype.</p>
                    </div>
                </div>
            </div>

            <div class="consent-list" style="margin:10px 0;gap:8px">
                <div class="consent-list__item" style="font-size:12.5px;line-height:1.4">
                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    <span id="consentRule1">MEDGem does <strong>not</strong> diagnose diseases</span>
                </div>
                <div class="consent-list__item" style="font-size:12.5px;line-height:1.4">
                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    <span id="consentRule2">MEDGem does <strong>not</strong> prescribe medicines</span>
                </div>
                <div class="consent-list__item" style="font-size:12.5px;line-height:1.4">
                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    <span id="consentRule3">MEDGem does <strong>not</strong> replace healthcare professionals</span>
                </div>
            </div>

            <p id="consentExplanation" style="font-size:11.5px;color:var(--neutral-600);line-height:1.4;margin:8px 0;background:var(--neutral-50);padding:8px 10px;border-radius:10px;border:1px solid var(--neutral-150)">
                Your information will be organized and presented for healthcare-worker review. A nurse will verify your details, and a doctor will make the professional assessment in English.
            </p>

            <div class="clinical-assurance-pill" style="margin-bottom:8px">
                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                <span id="consentStaffNote">Clinical notes & doctor reports will be generated in English</span>
            </div>

            <!-- Standardized Triage Rule & PII Protection Notice -->
            <div style="font-size:11px;line-height:1.45;color:var(--neutral-700);background:#eff4ff;padding:8px 10px;border-radius:8px;border-left:3px solid #396cf0;margin-bottom:8px">
                <strong>Standardized Triage Protocol:</strong> The AI’s color-coded prioritization (Red/Yellow/Green) is merely a sorting mechanism for the nurse's queue. It does not dictate final medical urgency, which is always overwritten by the human nurse's physical assessment.
            </div>
            <div style="font-size:11px;line-height:1.45;color:var(--neutral-700);background:#eff6ff;padding:8px 10px;border-radius:8px;border-left:3px solid #2563eb;margin-bottom:10px">
                <strong>PII Protection Guarantee:</strong> MEDGem strips or masks PII (Personally Identifiable Information) before passing data to the LLM backend (like Gemini/MedGemma). The nurse and doctor can see it locally, but the AI engine receives only an anonymous Track ID and clinical notes.
            </div>

            <label class="consent-box" id="consentBox" for="consentCheck" style="margin:6px 0 10px;padding:8px 12px;border-radius:10px">
                <input type="checkbox" id="consentCheck">
                <span class="consent-text" id="consentCheckLabel" style="font-size:12.5px;font-weight:600">I understand and agree to continue</span>
            </label>

            <button class="btn btn--primary btn-compact-submit" id="consentBtn" disabled onclick="proceedToMode()">
                <span id="consentBtnText">Continue</span>
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
            </button>
        </div>
    </div>
</main>

<script src="/BPUT/assets/js/app.js"></script>
<script>
const lang = sessionStorage.getItem('medgem_language') || 'English';

const consentI18n = {
    English: {
        step1: 'Information',
        step2: 'Consent',
        step3: 'Assessment',
        title: 'Before You Continue',
        subtitle: 'MEDGem is an educational healthcare triage prototype.',
        rule1: 'MEDGem does <strong>not</strong> diagnose diseases',
        rule2: 'MEDGem does <strong>not</strong> prescribe medicines',
        rule3: 'MEDGem does <strong>not</strong> replace healthcare professionals',
        explanation: 'Your information will be organized and presented for healthcare-worker review. A nurse will verify your details, and a doctor will make the professional assessment in English.',
        staffNote: 'Clinical notes & doctor reports will be generated in English',
        checkLabel: 'I understand and agree to continue',
        btnText: 'Continue'
    },
    Hindi: {
        step1: 'जानकारी',
        step2: 'सहमति',
        step3: 'मूल्यांकन',
        title: 'आगे बढ़ने से पहले',
        subtitle: 'MEDGem एक शैक्षिक स्वास्थ्य ट्राइएज-सहायता प्रोटोटाइप है।',
        rule1: 'MEDGem बीमारियों का <strong>निदान नहीं</strong> करता',
        rule2: 'MEDGem दवाइयां <strong>नहीं लिखता</strong>',
        rule3: 'MEDGem डॉक्टरों की <strong>जगह नहीं लेता</strong>',
        explanation: 'आपकी जानकारी स्वास्थ्य कर्मियों की समीक्षा के लिए व्यवस्थित की जाएगी। नर्स सत्यापन करेंगी और डॉक्टर अंग्रेज़ी (English) में पेशेवर निर्णय लेंगे।',
        staffNote: 'डॉक्टर और नर्स के लिए रिपोर्ट अंग्रेज़ी (English) में तैयार होगी',
        checkLabel: 'मैं समझता/समझती हूँ और आगे बढ़ने के लिए सहमत हूँ',
        btnText: 'आगे बढ़ें'
    },
    Odia: {
        step1: 'ସୂଚନା',
        step2: 'ସମ୍ମତି',
        step3: 'ମୂଲ୍ୟାଙ୍କନ',
        title: 'ଆଗକୁ ବଢ଼ିବା ପୂର୍ବରୁ',
        subtitle: 'MEDGem ଏକ ଶିକ୍ଷଣୀୟ ସ୍ୱାସ୍ଥ୍ୟ ଟ୍ରାଏଜ୍-ସହାୟତା ପ୍ରୋଟୋଟାଇପ୍।',
        rule1: 'MEDGem ରୋଗ <strong>ନିର୍ଣ୍ଣୟ କରେ ନାହିଁ</strong>',
        rule2: 'MEDGem ଔଷଧ <strong>ଲେଖେ ନାହିଁ</strong>',
        rule3: 'MEDGem ଡାକ୍ତରଙ୍କ <strong>ବିକଳ୍ପ ନୁହେଁ</strong>',
        explanation: 'ଆପଣଙ୍କ ତଥ୍ୟ ସ୍ୱାସ୍ଥ୍ୟ କର୍ମୀଙ୍କ ଯାଞ୍ଚ ପାଇଁ ସଜାଡ଼ି ରଖାଯିବ। ନର୍ସ ଏହା ଯାଞ୍ଚ କରିବେ ଏବଂ ଡାକ୍ତର ଇଂରାଜୀ (English) ରେ ଚିକିତ୍ସା ନିଷ୍ପତ୍ତି ନେବେ।',
        staffNote: 'ଡାକ୍ତର ଓ ନର୍ସଙ୍କ ପାଇଁ ରିପୋର୍ଟ ଇଂରାଜୀ (English) ରେ ପ୍ରସ୍ତୁତ ହେବ',
        checkLabel: 'ମୁଁ ବୁଝିଲି ଏବଂ ଆଗକୁ ବଢ଼ିବାକୁ ସହମତ',
        btnText: 'ଆଗକୁ ବଢ଼ନ୍ତୁ'
    }
};

const dict = consentI18n[lang] || consentI18n.English;
document.getElementById('stepLabel1').textContent = dict.step1;
document.getElementById('stepLabel2').textContent = dict.step2;
document.getElementById('stepLabel3').textContent = dict.step3;
document.getElementById('consentTitle').textContent = dict.title;
document.getElementById('consentSubtitle').textContent = dict.subtitle;
document.getElementById('consentRule1').innerHTML = dict.rule1;
document.getElementById('consentRule2').innerHTML = dict.rule2;
document.getElementById('consentRule3').innerHTML = dict.rule3;
document.getElementById('consentExplanation').textContent = dict.explanation;
document.getElementById('consentStaffNote').textContent = dict.staffNote;
document.getElementById('consentCheckLabel').textContent = dict.checkLabel;
document.getElementById('consentBtnText').textContent = dict.btnText;

const consentCheck = document.getElementById('consentCheck');
const consentBtn = document.getElementById('consentBtn');
const consentBox = document.getElementById('consentBox');

consentCheck.addEventListener('change', function() {
    consentBtn.disabled = !this.checked;
    if (this.checked) {
        consentBox.classList.add('checked');
    } else {
        consentBox.classList.remove('checked');
    }
});

function proceedToMode() {
    if (!consentCheck.checked) return;
    window.location.href = '/BPUT/patient/mode.php';
}
</script>
</body>
</html>
