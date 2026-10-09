<?php
/**
 * MEDGem — Patient Information Form
 * Step 1: Collect basic patient information with Language Selection
 * Clean, minimal, modern UI.
 */
require_once __DIR__ . '/../includes/security.php';
setSecurityHeaders();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>Patient Information — MEDGem</title>
    <meta name="description" content="Provide your basic information to start a health assessment with MEDGem.">
    <link rel="stylesheet" href="/BPUT/assets/css/medgem.css">
    <link rel="stylesheet" href="/BPUT/assets/css/patient.css">
</head>
<body class="mobile-fit-body">

<!-- Compact Header Navbar -->
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
        
        <!-- Topbar After Navbar (Back button + Progress Bar) -->
        <div class="patient-flow-topbar">
            <a href="/BPUT/" class="patient-back-btn" aria-label="Go Back">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
                <span>Back</span>
            </a>

            <!-- Step Progress Bar -->
            <nav class="patient-progress-compact" aria-label="Assessment Progress">
                <div class="patient-progress-compact__step active" id="stepBadge1">
                    <span class="patient-progress-compact__dot"></span>
                    <span class="patient-progress-compact__label" id="stepLabel1">Information</span>
                </div>
                <div class="patient-progress-compact__line"></div>
                <div class="patient-progress-compact__step" id="stepBadge2">
                    <span class="patient-progress-compact__dot"></span>
                    <span class="patient-progress-compact__label" id="stepLabel2">Consent</span>
                </div>
                <div class="patient-progress-compact__line"></div>
                <div class="patient-progress-compact__step" id="stepBadge3">
                    <span class="patient-progress-compact__dot"></span>
                    <span class="patient-progress-compact__label" id="stepLabel3">Assessment</span>
                </div>
            </nav>
        </div>

        <!-- Main Information Card -->
        <div class="patient-card-compact fade-up">
            
            <div class="patient-card-compact__header">
                <h1 class="patient-card-compact__title" id="cardTitle">Patient Information</h1>
                <p class="patient-card-compact__subtitle" id="cardSubtitle">Please provide your details to begin the assessment.</p>
            </div>

            <form id="patientInfoForm" class="patient-form-compact" method="POST" action="/BPUT/api/patient.php" novalidate>
                <?php echo csrfField(); ?>
                
                <!-- Preferred Language Toggle -->
                <div class="form-group-compact lang-first-group">
                    <label class="form-label-compact" id="langLabel">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                        <span id="langPromptText">Preferred Language</span>
                    </label>

                    <div class="lang-segmented" role="radiogroup" aria-label="Preferred Language">
                        <input type="radio" id="langEn" name="preferred_language" value="English" checked class="lang-segmented__input">
                        <label for="langEn" class="lang-segmented__btn" onclick="selectLanguage('English')">
                            <span>English</span>
                        </label>

                        <input type="radio" id="langHi" name="preferred_language" value="Hindi" class="lang-segmented__input">
                        <label for="langHi" class="lang-segmented__btn" onclick="selectLanguage('Hindi')">
                            <span>हिन्दी</span>
                        </label>

                        <input type="radio" id="langOr" name="preferred_language" value="Odia" class="lang-segmented__input">
                        <label for="langOr" class="lang-segmented__btn" onclick="selectLanguage('Odia')">
                            <span>ଓଡ଼ିଆ</span>
                        </label>
                    </div>
                </div>

                <!-- Full Name -->
                <div class="form-group-compact">
                    <label class="form-label-compact" for="fullName" id="nameLabel">Full Name</label>
                    <input type="text" id="fullName" name="name" class="form-input-compact" placeholder="Enter your full name" required autocomplete="name">
                </div>

                <!-- Age & Contact Row -->
                <div class="form-row-compact">
                    <div class="form-group-compact">
                        <label class="form-label-compact" for="age" id="ageLabel">Age</label>
                        <input type="number" id="age" name="age" class="form-input-compact" placeholder="Your age" min="0" max="150" required inputmode="numeric">
                    </div>
                    <div class="form-group-compact">
                        <label class="form-label-compact" for="contact" id="contactLabel">Contact Number</label>
                        <input type="tel" id="contact" name="contact_number" class="form-input-compact" placeholder="+91 XXXXX XXXXX" autocomplete="tel" inputmode="tel">
                    </div>
                </div>

                <!-- Sex -->
                <div class="form-group-compact">
                    <label class="form-label-compact" id="sexLabel">Sex</label>
                    <div class="form-radio-group-compact">
                        <input type="radio" id="sexMale" name="sex" value="Male" class="form-radio-compact" required>
                        <label for="sexMale" class="form-radio-label-compact">
                            <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="10" cy="14" r="5"/><line x1="19" y1="5" x2="13.6" y2="10.4"/><line x1="15" y1="5" x2="19" y2="5"/><line x1="19" y1="5" x2="19" y2="9"/></svg>
                            <span id="sexMaleLabel">Male</span>
                        </label>

                        <input type="radio" id="sexFemale" name="sex" value="Female" class="form-radio-compact">
                        <label for="sexFemale" class="form-radio-label-compact">
                            <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="5"/><line x1="12" y1="13" x2="12" y2="21"/><line x1="9" y1="18" x2="15" y2="18"/></svg>
                            <span id="sexFemaleLabel">Female</span>
                        </label>

                        <input type="radio" id="sexOther" name="sex" value="Other" class="form-radio-compact">
                        <label for="sexOther" class="form-radio-label-compact">
                            <span id="sexOtherLabel">Other</span>
                        </label>
                    </div>
                </div>

                <!-- Inline Error Container -->
                <div id="formErrors" class="form-errors-compact hidden"></div>

                <!-- Submit Button -->
                <button type="submit" class="btn btn--primary btn-compact-submit" id="continueBtn">
                    <span id="continueBtnText">Continue</span>
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                </button>
            </form>
        </div>
    </div>
</main>

<script src="/BPUT/assets/js/app.js"></script>
<script>
/**
 * Multilingual Translation Matrix
 */
const i18n = {
    English: {
        step1: 'Information',
        step2: 'Consent',
        step3: 'Assessment',
        title: 'Patient Information',
        subtitle: 'Please provide your details to begin the assessment.',
        langPrompt: 'Preferred Language',
        nameLabel: 'Full Name',
        namePlaceholder: 'Enter your full name',
        ageLabel: 'Age',
        agePlaceholder: 'Your age',
        contactLabel: 'Contact Number',
        contactPlaceholder: '+91 XXXXX XXXXX',
        sexLabel: 'Sex',
        male: 'Male',
        female: 'Female',
        other: 'Other',
        btnText: 'Continue',
        savingText: 'Saving...',
        errName: 'Full name is required',
        errAge: 'Valid age is required (0-150)',
        errSex: 'Please select your sex',
        errGeneral: 'Something went wrong. Please try again.'
    },
    Hindi: {
        step1: 'जानकारी',
        step2: 'सहमति',
        step3: 'मूल्यांकन',
        title: 'मरीज़ की जानकारी',
        subtitle: 'मूल्यांकन शुरू करने के लिए अपना विवरण दर्ज करें।',
        langPrompt: 'अपनी पसंद की भाषा चुनें',
        nameLabel: 'पूरा नाम',
        namePlaceholder: 'अपना पूरा नाम दर्ज करें',
        ageLabel: 'उम्र (वर्ष)',
        agePlaceholder: 'आपकी उम्र',
        contactLabel: 'मोबाइल नंबर',
        contactPlaceholder: '+91 XXXXX XXXXX',
        sexLabel: 'लिंग (Sex)',
        male: 'पुरुष (Male)',
        female: 'महिला (Female)',
        other: 'अन्य (Other)',
        btnText: 'आगे बढ़ें',
        savingText: 'सहेजा जा रहा है...',
        errName: 'कृपया अपना पूरा नाम दर्ज करें',
        errAge: 'कृपया मान्य उम्र दर्ज करें (0-150)',
        errSex: 'कृपया अपना लिंग चुनें',
        errGeneral: 'कुछ गलत हो गया। कृपया पुन: प्रयास करें।'
    },
    Odia: {
        step1: 'ସୂଚନା',
        step2: 'ସମ୍ମତି',
        step3: 'ମୂଲ୍ୟାଙ୍କନ',
        title: 'ରୋଗୀଙ୍କ ସୂଚନା',
        subtitle: 'ମୂଲ୍ୟାଙ୍କନ ଆରମ୍ଭ କରିବା ପାଇଁ ବିବରଣୀ ପ୍ରଦାନ କରନ୍ତୁ।',
        langPrompt: 'ପସନ୍ଦର ଭାଷା ବାଛନ୍ତୁ',
        nameLabel: 'ସମ୍ପୂର୍ଣ୍ଣ ନାମ',
        namePlaceholder: 'ଆପଣଙ୍କ ପୂରା ନାମ ଲେଖନ୍ତୁ',
        ageLabel: 'ବୟସ (ବର୍ଷ)',
        agePlaceholder: 'ଆପଣଙ୍କ ବୟସ',
        contactLabel: 'ମୋବାଇଲ୍ ନମ୍ବର',
        contactPlaceholder: '+91 XXXXX XXXXX',
        sexLabel: 'ଲିଙ୍ଗ (Sex)',
        male: 'ପୁରୁଷ (Male)',
        female: 'ମହିଳା (Female)',
        other: 'ଅନ୍ୟାନ୍ୟ (Other)',
        btnText: 'ଆଗକୁ ବଢ଼ନ୍ତୁ',
        savingText: 'ସେଭ୍ ହେଉଛି...',
        errName: 'ଦୟାକରି ଆପଣଙ୍କ ପୂରା ନାମ ଲେଖନ୍ତୁ',
        errAge: 'ଦୟାକରି ସଠିକ୍ ବୟସ ଲେଖନ୍ତୁ (0-150)',
        errSex: 'ଦୟାକରି ଲିଙ୍ଗ ଚୟନ କରନ୍ତୁ',
        errGeneral: 'କିଛି ତ୍ରୁଟି ଘଟିଲା। ପୁଣି ଚେଷ୍ଟା କରନ୍ତୁ।'
    }
};

let currentLang = sessionStorage.getItem('medgem_language') || 'English';

function selectLanguage(lang) {
    currentLang = lang;
    sessionStorage.setItem('medgem_language', lang);
    
    // Check radio button
    const radio = document.querySelector(`input[name="preferred_language"][value="${lang}"]`);
    if (radio) radio.checked = true;
    
    // Apply translations
    const dict = i18n[lang] || i18n.English;
    const setTxt = (id, val) => {
        const el = document.getElementById(id);
        if (el) el.textContent = val;
    };
    const setPh = (id, val) => {
        const el = document.getElementById(id);
        if (el) el.placeholder = val;
    };

    setTxt('stepLabel1', dict.step1);
    setTxt('stepLabel2', dict.step2);
    setTxt('stepLabel3', dict.step3);
    setTxt('cardTitle', dict.title);
    setTxt('cardSubtitle', dict.subtitle);
    setTxt('langPromptText', dict.langPrompt);
    setTxt('nameLabel', dict.nameLabel);
    setPh('fullName', dict.namePlaceholder);
    setTxt('ageLabel', dict.ageLabel);
    setPh('age', dict.agePlaceholder);
    setTxt('contactLabel', dict.contactLabel);
    setPh('contact', dict.contactPlaceholder);
    setTxt('sexLabel', dict.sexLabel);
    setTxt('sexMaleLabel', dict.male);
    setTxt('sexFemaleLabel', dict.female);
    setTxt('sexOtherLabel', dict.other);
    setTxt('continueBtnText', dict.btnText);
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', () => {
    sessionStorage.removeItem('medgem_case_id');
    sessionStorage.removeItem('medgem_track_id');
    sessionStorage.removeItem('medgem_patient_name');
    sessionStorage.removeItem('medgem_patient_age');
    sessionStorage.removeItem('medgem_patient_sex');
    selectLanguage(currentLang);
});

// Form Submission
document.getElementById('patientInfoForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const btn = document.getElementById('continueBtn');
    const errorsDiv = document.getElementById('formErrors');
    errorsDiv.classList.add('hidden');
    
    const dict = i18n[currentLang] || i18n.English;
    
    const name = document.getElementById('fullName').value.trim();
    const age = document.getElementById('age').value;
    const sex = document.querySelector('input[name="sex"]:checked');
    
    const errors = [];
    if (!name) errors.push(dict.errName);
    if (!age || age < 0 || age > 150) errors.push(dict.errAge);
    if (!sex) errors.push(dict.errSex);
    
    if (errors.length > 0) {
        errorsDiv.innerHTML = errors.map(err => `<p>${err}</p>`).join('');
        errorsDiv.classList.remove('hidden');
        return;
    }
    
    // Store in session
    sessionStorage.setItem('medgem_patient_name', name);
    sessionStorage.setItem('medgem_patient_age', age);
    sessionStorage.setItem('medgem_patient_sex', sex.value);
    sessionStorage.setItem('medgem_language', currentLang);
    
    btn.disabled = true;
    const originalBtnHtml = btn.innerHTML;
    btn.innerHTML = `<span class="spinner spinner--sm" style="margin-right:8px"></span> ${dict.savingText}`;
    
    try {
        const formData = new FormData(this);
        formData.set('preferred_language', currentLang);
        formData.set('sex', sex.value);
        
        const response = await fetch('/BPUT/api/patient.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            sessionStorage.setItem('medgem_case_id', data.case_id);
            sessionStorage.setItem('medgem_track_id', data.track_id);
            window.location.href = '/BPUT/patient/consent.php';
        } else {
            errorsDiv.innerHTML = `<p>${data.error || dict.errGeneral}</p>`;
            errorsDiv.classList.remove('hidden');
            btn.disabled = false;
            btn.innerHTML = originalBtnHtml;
        }
    } catch (err) {
        errorsDiv.innerHTML = `<p>${dict.errGeneral}</p>`;
        errorsDiv.classList.remove('hidden');
        btn.disabled = false;
        btn.innerHTML = originalBtnHtml;
    }
});
</script>
</body>
</html>
