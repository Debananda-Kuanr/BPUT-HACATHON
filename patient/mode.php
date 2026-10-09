<?php
/**
 * MEDGem — Communication Mode Selection
 * Step 3: Choose text or voice
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
    <title>Choose Method — MEDGem</title>
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
            <div class="patient-progress-compact__step">
                <span class="patient-progress-compact__dot" style="background:var(--primary-500)"></span>
                <span class="patient-progress-compact__label" id="stepLabel2">Consent</span>
            </div>
            <div class="patient-progress-compact__line" style="background:var(--primary-300)"></div>
            <div class="patient-progress-compact__step active">
                <span class="patient-progress-compact__dot"></span>
                <span class="patient-progress-compact__label" id="stepLabel3">Assessment</span>
            </div>
        </nav>

        <div class="patient-card-compact fade-up">
            <div class="patient-card-compact__header" style="text-align:center">
                <h1 class="patient-card-compact__title" id="modeTitle">How would you like to describe your symptoms?</h1>
                <p class="patient-card-compact__subtitle" id="modeSubtitle">Choose the method that feels most comfortable for you.</p>
            </div>

            <div class="clinical-assurance-pill" style="margin-bottom:12px;justify-content:center">
                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                <span id="modeStaffNote">You can speak or type in your language — staff records will be in English</span>
            </div>

            <div class="mode-cards" style="gap:10px;margin-bottom:12px">
                <div class="mode-card" onclick="selectMode('TEXT')" id="modeText" style="padding:16px 14px">
                    <div class="mode-card__icon mode-card__icon--text" style="width:48px;height:48px;margin-bottom:8px">
                        <svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                    </div>
                    <h2 class="mode-card__title" id="textTitle" style="font-size:1rem;margin-bottom:2px">Text Chat</h2>
                    <p class="mode-card__desc" id="textDesc" style="font-size:0.75rem">Chat with MEDGem by typing your responses</p>
                </div>

                <div class="mode-divider" style="font-size:11px">OR</div>

                <div class="mode-card" onclick="selectMode('VOICE')" id="modeVoice" style="padding:16px 14px">
                    <div class="mode-card__icon mode-card__icon--voice" style="width:48px;height:48px;margin-bottom:8px">
                        <svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/><line x1="12" y1="19" x2="12" y2="23"/><line x1="8" y1="23" x2="16" y2="23"/></svg>
                    </div>
                    <h2 class="mode-card__title" id="voiceTitle" style="font-size:1rem;margin-bottom:2px">Voice Interview</h2>
                    <p class="mode-card__desc" id="voiceDesc" style="font-size:0.75rem">Talk naturally with MEDGem using your voice</p>
                </div>
            </div>
        </div>
    </div>
</main>

<script src="/BPUT/assets/js/app.js"></script>
<script>
const lang = sessionStorage.getItem('medgem_language') || 'English';

const modeI18n = {
    English: {
        step1: 'Information',
        step2: 'Consent',
        step3: 'Assessment',
        title: 'How would you like to describe your symptoms?',
        subtitle: 'Choose the method that feels most comfortable for you.',
        staffNote: 'Speak or type in your language • Staff records will be in English',
        textTitle: 'Text Chat',
        textDesc: 'Chat with MEDGem by typing your responses',
        voiceTitle: 'Voice Interview',
        voiceDesc: 'Talk naturally with MEDGem using your voice'
    },
    Hindi: {
        step1: 'जानकारी',
        step2: 'सहमति',
        step3: 'मूल्यांकन',
        title: 'आप अपने लक्षण कैसे बताना चाहेंगे?',
        subtitle: 'जो तरीका आपके लिए सबसे आसान हो उसे चुनें।',
        staffNote: 'अपनी भाषा में बोलें या टाइप करें • डॉक्टर/नर्स के लिए अंग्रेज़ी में तैयार होगा',
        textTitle: 'टेक्स्ट चैट (Text Chat)',
        textDesc: 'टाइप करके MEDGem के सवालों के जवाब दें',
        voiceTitle: 'वॉयस इंटरव्यू (Voice Interview)',
        voiceDesc: 'बोलकर स्वाभाविक रूप से बातचीत करें'
    },
    Odia: {
        step1: 'ସୂଚନା',
        step2: 'ସମ୍ମତି',
        step3: 'ମୂଲ୍ୟାଙ୍କନ',
        title: 'ଆପଣ ନିଜ ଲକ୍ଷଣ କିପରି ଜଣାଇବାକୁ ଚାହାଁନ୍ତି?',
        subtitle: 'ଯେଉଁ ଉପାୟ ଆପଣଙ୍କ ପାଇଁ ସୁବିଧା ତାହା ବାଛନ୍ତୁ।',
        staffNote: 'ଆପଣଙ୍କ ଭାଷାରେ କୁହନ୍ତୁ ବା ଲେଖନ୍ତୁ • ଡାକ୍ତର/ନର୍ସଙ୍କ ପାଇଁ ଇଂରାଜୀରେ ହେବ',
        textTitle: 'ଟେକ୍ସଟ୍ ଚାଟ୍ (Text Chat)',
        textDesc: 'ଟାଇପ୍ କରି ସହଜରେ ଉତ୍ତର ଦିଅନ୍ତୁ',
        voiceTitle: 'ଭଏସ୍ ଇଣ୍ଟରଭ୍ୟୁ (Voice Interview)',
        voiceDesc: 'କଥା କହି MEDGem ସହିତ କଥାବାର୍ତ୍ତା କରନ୍ତୁ'
    }
};

const dict = modeI18n[lang] || modeI18n.English;
document.getElementById('stepLabel1').textContent = dict.step1;
document.getElementById('stepLabel2').textContent = dict.step2;
document.getElementById('stepLabel3').textContent = dict.step3;
document.getElementById('modeTitle').textContent = dict.title;
document.getElementById('modeSubtitle').textContent = dict.subtitle;
document.getElementById('modeStaffNote').textContent = dict.staffNote;
document.getElementById('textTitle').textContent = dict.textTitle;
document.getElementById('textDesc').textContent = dict.textDesc;
document.getElementById('voiceTitle').textContent = dict.voiceTitle;
document.getElementById('voiceDesc').textContent = dict.voiceDesc;

function selectMode(mode) {
    document.querySelectorAll('.mode-card').forEach(c => c.classList.remove('selected'));
    document.getElementById('mode' + mode.charAt(0) + mode.slice(1).toLowerCase()).classList.add('selected');
    
    sessionStorage.setItem('medgem_mode', mode);
    
    fetch('/BPUT/api/patient.php', {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            case_id: sessionStorage.getItem('medgem_case_id'),
            communication_mode: mode
        })
    }).then(() => {
        window.location.href = mode === 'VOICE' ? '/BPUT/patient/voice.php' : '/BPUT/patient/text.php';
    }).catch(() => {
        window.location.href = mode === 'VOICE' ? '/BPUT/patient/voice.php' : '/BPUT/patient/text.php';
    });
}
</script>
</body>
</html>
