<?php
/**
 * MEDGem — Text Chat Interface
 * 100% AI-Powered Dynamic Conversation (No Predefined Questions)
 * Uses MEDgem engine with Gemini for real-time clinical intake.
 */
require_once __DIR__ . '/../includes/security.php';
setSecurityHeaders();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Chat — MEDGem</title>
    <link rel="stylesheet" href="/BPUT/assets/css/medgem.css">
    <link rel="stylesheet" href="/BPUT/assets/css/patient.css">
    <style>
        body { background: var(--neutral-0); }
        .chat-page { display: flex; flex-direction: column; height: 100vh; height: 100dvh; }
        .chat-page .prototype-banner ~ * { /* Account for banner height */ }
        .api-error-banner {
            display: none;
            background: var(--danger-50);
            border: 1px solid var(--danger-200);
            color: var(--danger-700);
            padding: 10px 16px;
            font-size: var(--text-sm);
            text-align: center;
            border-radius: var(--radius-lg);
            margin: 8px 12px 0;
        }
        .api-error-banner.visible { display: block; }
        .chat-bubble--bot .ai-badge {
            display: inline-flex;
            align-items: center;
            gap: 3px;
            background: var(--primary-100);
            color: var(--primary-700);
            font-size: 10px;
            font-weight: 600;
            padding: 1px 6px;
            border-radius: 4px;
            margin-left: 6px;
            vertical-align: middle;
        }
    </style>
</head>
<body>

<div class="prototype-banner">
    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline;vertical-align:-2px;margin-right:4px"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
    Synthetic Data &middot; Educational Prototype
</div>

<div class="chat-page" style="padding-top: 28px;">
    <!-- Chat Header -->
    <div class="chat-header">
        <a href="/BPUT/patient/mode.php" style="color:var(--neutral-500);display:flex;align-items:center;margin-right:var(--space-2)">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
        </a>
        <div class="chat-header__avatar">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
        </div>
        <div>
            <div style="font-weight:700;font-size:var(--text-base);color:var(--neutral-900)">MEDGem</div>
            <div style="font-size:var(--text-xs);color:var(--primary-600);font-weight:500" id="chatStatus">Health Assistant</div>
        </div>
        <div style="margin-left:auto">
            <button class="btn btn--ghost btn--sm" id="endChatBtn" onclick="endConversation()" style="color:var(--danger-500)">End Chat</button>
        </div>
    </div>

    <!-- API Error Banner -->
    <div class="api-error-banner" id="apiErrorBanner"></div>

    <!-- Messages -->
    <div class="chat-messages" id="chatMessages">
        <!-- Messages will be inserted here dynamically -->
    </div>

    <!-- Input Area -->
    <div class="chat-input-area">
        <!-- Image Preview Bar (Appears when medical report image is attached) -->
        <div id="imagePreviewContainer" style="display:none;padding:8px 12px;background:var(--neutral-100);border-radius:12px;margin-bottom:8px;align-items:center;gap:10px">
            <div style="position:relative;display:inline-block">
                <img id="imagePreviewThumb" src="" style="width:44px;height:44px;object-fit:cover;border-radius:8px;border:1px solid var(--neutral-300)" />
                <button type="button" onclick="clearSelectedImage()" style="position:absolute;top:-6px;right:-6px;background:var(--danger-500);color:#fff;border:none;border-radius:50%;width:18px;height:18px;font-size:11px;font-weight:bold;cursor:pointer;display:flex;align-items:center;justify-content:center;line-height:1">&times;</button>
            </div>
            <div style="min-width:0;flex:1">
                <div style="font-size:12px;font-weight:600;color:var(--neutral-800);overflow:hidden;text-overflow:ellipsis;white-space:nowrap" id="imagePreviewName"></div>
                <div style="font-size:10px;color:var(--primary-600);font-weight:500">Image attached &middot; Ready for AI Vision analysis</div>
            </div>
        </div>
        <div class="chat-input-wrapper">
            <button class="chat-attach-btn" id="attachBtn" type="button" onclick="triggerImageSelect()" title="Attach Medical Report Image (JPG, PNG, WEBP)">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
            </button>
            <input type="file" id="chatImageInput" accept="image/jpeg,image/png,image/webp,image/jpg" style="display:none" onchange="handleChatImageSelected(event)">
            <input type="text" class="chat-input" id="chatInput" placeholder="Type your response..." autocomplete="off" autofocus>
            <button class="chat-send-btn" id="sendBtn" onclick="sendMessage()" disabled>
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
            </button>
        </div>
    </div>
</div>

<!-- Load AI Config & Engine -->
<script src="/BPUT/config/ai-config.js"></script>
<script src="/BPUT/aiModel/js/ai-engine.js"></script>
<script src="/BPUT/assets/js/app.js"></script>
<script>
// ─── Session Data ─────────────────────────────────────────────
const caseId = sessionStorage.getItem('medgem_case_id');
const trackId = sessionStorage.getItem('medgem_track_id') || 'SYN-ANON';
const patientAge = sessionStorage.getItem('medgem_patient_age') || '';
const patientSex = sessionStorage.getItem('medgem_patient_sex') || '';
const language = sessionStorage.getItem('medgem_language') || 'English';

if (!caseId) {
    window.location.href = '/BPUT/patient/information.php';
}

// ─── DOM References ──────────────────────────────────────────
const messagesContainer = document.getElementById('chatMessages');
const chatInput = document.getElementById('chatInput');
const sendBtn = document.getElementById('sendBtn');
const chatStatus = document.getElementById('chatStatus');
const apiErrorBanner = document.getElementById('apiErrorBanner');
const endBtnEl = document.getElementById('endChatBtn');

// ─── Localization ────────────────────────────────────────────
const chatLocale = {
    English: {
        status: 'Health Assistant',
        placeholder: 'Type your response...',
        endText: 'End Chat',
        processing: 'Preparing your case...',
        errorNoKey: 'AI configuration missing. Please contact the administrator.',
        errorGeneric: 'Something went wrong. Please try again.',
        endMsg: 'Thank you for sharing this information. Your case is being prepared in English for healthcare-worker review.'
    },
    Hindi: {
        status: 'स्वास्थ्य सहायक',
        placeholder: 'अपना उत्तर यहाँ लिखें...',
        endText: 'चैट समाप्त करें',
        processing: 'आपकी रिपोर्ट तैयार हो रही है...',
        errorNoKey: 'AI कॉन्फ़िगरेशन उपलब्ध नहीं। कृपया व्यवस्थापक से संपर्क करें।',
        errorGeneric: 'कुछ गलत हो गया। कृपया पुनः प्रयास करें।',
        endMsg: 'जानकारी साझा करने के लिए धन्यवाद। डॉक्टर और नर्स की समीक्षा के लिए रिपोर्ट अंग्रेज़ी में तैयार की जा रही है।'
    },
    Odia: {
        status: 'ସ୍ୱାସ୍ଥ୍ୟ ସହାୟକ',
        placeholder: 'ଆପଣଙ୍କ ଉତ୍ତର ଏଠାରେ ଲେଖନ୍ତୁ...',
        endText: 'ଚାଟ୍ ସମାପ୍ତ',
        processing: 'ଆପଣଙ୍କ ରିପୋର୍ଟ ପ୍ରସ୍ତୁତ ହେଉଛି...',
        errorNoKey: 'AI ସେଟିଂ ଉପಲବ୍ଧ ନାହିଁ। ଦୟାକରି ପ୍ରଶାସକଙ୍କୁ ସମ୍ପର୍କ କରନ୍ତୁ।',
        errorGeneric: 'କିଛି ତ୍ରୁଟି ଘଟିଲା। ପୁଣି ଚେଷ୍ଟା କରନ୍ତୁ।',
        endMsg: 'ସୂଚନା ଦେଇଥିବାରୁ ଧନ୍ୟବାଦ। ଡାକ୍ତର ଓ ନର୍ସଙ୍କ ଯାଞ୍ଚ ପାଇଁ ରିପୋର୍ଟ ଇଂରାଜୀରେ ପ୍ରସ୍ତୁତ କରାଯାଉଛି।'
    }
};

const curLocale = chatLocale[language] || chatLocale.English;
chatStatus.textContent = curLocale.status;
chatInput.placeholder = curLocale.placeholder;
if (endBtnEl) endBtnEl.textContent = curLocale.endText;

// ─── State ───────────────────────────────────────────────────
let conversationHistory = [];
let isConversationEnded = false;
let turnCount = 0;
const MAX_TURNS_HARD_LIMIT = 15; // Absolute safety net — auto-finalize after this many turns

// ─── Initialize AI Engine ────────────────────────────────────
const ai = triageAI; // Global instance from ai-engine.js
ai.clearChat();

// Apply config
if (typeof MEDGEM_AI_CONFIG !== 'undefined') {
    ai.setApiKey(MEDGEM_AI_CONFIG.API_KEYS);
    if (MEDGEM_AI_CONFIG.MODEL) ai.setModel(MEDGEM_AI_CONFIG.MODEL);
    if (MEDGEM_AI_CONFIG.FALLBACK_MODELS && MEDGEM_AI_CONFIG.FALLBACK_MODELS.length > 0) {
        ai.fallbackModels = MEDGEM_AI_CONFIG.FALLBACK_MODELS;
    }
}

// Zero-PII Context: Only Track ID & clinical parameters are passed to LLM backend
const patientInfo = {
    trackId: trackId,
    age: patientAge,
    gender: patientSex
};

// ─── Start Conversation with AI (Unstoppable Protocol) ───────
async function initConversation() {
    addTypingIndicator();
    chatStatus.textContent = 'Typing...';

    try {
        // Send initial message to AI to get its greeting — zero PII transmitted
        const greeting = await ai.sendChatMessage(
            `[System: New patient intake. Track ID: ${trackId}, Age: ${patientInfo.age || 'Not provided'}, Clinical Sex: ${patientInfo.gender || 'Not provided'}. The patient has chosen ${language} as their preferred language. Please greet them warmly in ${language} without asking for their legal name or contact number, and ask about their health symptoms.]`,
            patientInfo,
            null,
            language
        );

        removeTypingIndicator();
        chatStatus.textContent = curLocale.status;
        addBotMessage(cleanAIResponse(greeting));
    } catch (err) {
        removeTypingIndicator();
        chatStatus.textContent = curLocale.status;
        showError(err.message);
    }
}

initConversation();

// ─── UI Functions ────────────────────────────────────────────
// ─── Image Attachment State ─────────────────────────────────
let selectedImageFile = null;
let selectedImageBase64 = null;

function triggerImageSelect() {
    if (isConversationEnded) return;
    document.getElementById('chatImageInput').click();
}

function handleChatImageSelected(e) {
    const file = e.target.files[0];
    if (!file) return;

    const allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/jpg'];
    if (!allowedTypes.includes(file.type.toLowerCase())) {
        showError('Only image formats (JPG, PNG, WEBP) are supported for report attachment in chat.');
        e.target.value = '';
        return;
    }

    if (file.size > 10 * 1024 * 1024) {
        showError('Selected image is too large (max 10MB).');
        e.target.value = '';
        return;
    }

    selectedImageFile = file;
    const reader = new FileReader();
    reader.onload = (evt) => {
        selectedImageBase64 = evt.target.result.split(',')[1];
        document.getElementById('imagePreviewThumb').src = evt.target.result;
        document.getElementById('imagePreviewName').textContent = file.name;
        document.getElementById('imagePreviewContainer').style.display = 'flex';
        document.getElementById('attachBtn').classList.add('has-file');
        sendBtn.disabled = false;
    };
    reader.readAsDataURL(file);
}

function clearSelectedImage() {
    selectedImageFile = null;
    selectedImageBase64 = null;
    document.getElementById('chatImageInput').value = '';
    document.getElementById('imagePreviewContainer').style.display = 'none';
    document.getElementById('attachBtn').classList.remove('has-file');
    sendBtn.disabled = (!chatInput.value.trim() && !selectedImageFile) || isConversationEnded;
}

// ─── UI Functions ────────────────────────────────────────────
chatInput.addEventListener('input', () => {
    sendBtn.disabled = (!chatInput.value.trim() && !selectedImageFile) || isConversationEnded;
});

chatInput.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' && !sendBtn.disabled) {
        sendMessage();
    }
});

function addBotMessage(text) {
    const div = document.createElement('div');
    div.className = 'chat-bubble chat-bubble--bot';
    div.innerHTML = `<div class="chat-bubble__sender">MEDGem <span class="ai-badge"><svg xmlns="http://www.w3.org/2000/svg" width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg> AI</span></div>${text.replace(/\n/g, '<br>')}`;
    messagesContainer.appendChild(div);
    scrollToBottom();
    
    conversationHistory.push({ speaker: 'MEDGEM', message: text });
    saveMessage('MEDGEM', text);
}

function addUserMessage(text, imageSrc = null) {
    const div = document.createElement('div');
    div.className = 'chat-bubble chat-bubble--user';
    let content = '';
    if (imageSrc) {
        content += `<img src="${imageSrc}" style="max-width:200px;max-height:200px;border-radius:10px;display:block;margin-bottom:6px;border:1px solid rgba(255,255,255,0.3);object-fit:cover" />`;
    }
    if (text) {
        content += `<div>${text.replace(/\n/g, '<br>')}</div>`;
    } else if (imageSrc) {
        content += `<div style="font-size:12px;opacity:0.9">📷 Medical Report Image Attached</div>`;
    }
    div.innerHTML = content;
    messagesContainer.appendChild(div);
    scrollToBottom();
    
    const savedMsg = text ? (imageSrc ? `[Image attached: ${selectedImageFile ? selectedImageFile.name : 'Report'}] ${text}` : text) : `[Image attached: ${selectedImageFile ? selectedImageFile.name : 'Report'}]`;
    conversationHistory.push({ speaker: 'PATIENT', message: savedMsg });
    saveMessage('PATIENT', savedMsg);
}

function addTypingIndicator() {
    const div = document.createElement('div');
    div.className = 'chat-typing';
    div.id = 'typingIndicator';
    div.innerHTML = '<span class="chat-typing__dot"></span><span class="chat-typing__dot"></span><span class="chat-typing__dot"></span>';
    messagesContainer.appendChild(div);
    scrollToBottom();
}

function removeTypingIndicator() {
    const el = document.getElementById('typingIndicator');
    if (el) el.remove();
}

function scrollToBottom() {
    messagesContainer.scrollTop = messagesContainer.scrollHeight;
}

function showError(msg) {
    apiErrorBanner.textContent = msg || curLocale.errorGeneric;
    apiErrorBanner.classList.add('visible');
    setTimeout(() => apiErrorBanner.classList.remove('visible'), 8000);
}

function cleanAIResponse(text) {
    // Remove the [TRIAGE_COMPLETE] tag from display text
    return text.replace(/\[TRIAGE_COMPLETE\]/g, '').trim();
}

// ─── Send Message to Real AI ─────────────────────────────────
async function sendMessage() {
    const text = chatInput.value.trim();
    const currentImageFile = selectedImageFile;
    const currentImageBase64 = selectedImageBase64;
    const imagePreviewSrc = currentImageFile ? document.getElementById('imagePreviewThumb').src : null;

    if ((!text && !currentImageFile) || isConversationEnded) return;
    
    addUserMessage(text, imagePreviewSrc);
    
    // Reset input states
    chatInput.value = '';
    clearSelectedImage();
    sendBtn.disabled = true;
    
    turnCount++;
    
    // Show typing
    chatStatus.textContent = 'Typing...';
    addTypingIndicator();
    
    let attachedPayload = null;
    if (currentImageFile && currentImageBase64) {
        attachedPayload = {
            name: currentImageFile.name,
            mimeType: currentImageFile.type || 'image/jpeg',
            base64: currentImageBase64
        };
        // Upload report to server database asynchronously
        uploadChatReportToServer(currentImageFile, currentImageBase64);
    }

    // Build message — append a silent hint at turn 5+ so AI knows to wrap up soon
    let messageToSend = text;
    if (turnCount >= 5 && turnCount < MAX_TURNS_HARD_LIMIT) {
        messageToSend = text + `\n[System: Patient has sent ${turnCount} messages. If you have collected the chief complaint, duration, severity and relevant history, please close the conversation warmly and append [TRIAGE_COMPLETE] at the very end of your response.]`;
    } else if (turnCount >= MAX_TURNS_HARD_LIMIT) {
        messageToSend = text + `\n[System: Max intake turns reached. You MUST close the conversation now with a brief thank-you message and append [TRIAGE_COMPLETE] at the very end.]`;
    }

    try {
        const aiResponse = await ai.sendChatMessage(messageToSend, patientInfo, attachedPayload, language);
        
        removeTypingIndicator();
        chatStatus.textContent = curLocale.status;
        
        // Check if AI has decided the conversation is complete
        const isComplete = aiResponse.includes('[TRIAGE_COMPLETE]');
        
        // Hard limit safety net — always finalize after MAX_TURNS_HARD_LIMIT
        const forceEnd = turnCount >= MAX_TURNS_HARD_LIMIT;
        
        addBotMessage(cleanAIResponse(aiResponse));
        
        if (isComplete || forceEnd) {
            // Auto-proceed: generate triage report and redirect
            await finalizeConversation();
        } else {
            chatInput.focus();
        }
    } catch (err) {
        removeTypingIndicator();
        chatStatus.textContent = curLocale.status;
        showError(err.message);
        chatInput.focus();
    }
}

async function uploadChatReportToServer(file, base64Data) {
    try {
        const formData = new FormData();
        formData.append('report', file);
        formData.append('case_id', caseId);
        formData.append('ocr_text', `Image report '${file.name}' attached directly in chat intake session.`);
        await fetch('/BPUT/api/report.php', { method: 'POST', body: formData });
    } catch (e) {
        console.warn('Failed to upload chat image report:', e);
    }
}

// ─── Save Message to Backend ─────────────────────────────────
async function saveMessage(speaker, message) {
    try {
        await fetch('/BPUT/api/conversation.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                case_id: caseId,
                mode: 'TEXT',
                speaker: speaker,
                message: message
            })
        });
    } catch(e) {
        console.warn('Failed to save message:', e);
    }
}

// ─── End Conversation (Manual or AI-triggered) ───────────────
async function endConversation() {
    if (isConversationEnded) return;
    await finalizeConversation();
}

async function finalizeConversation() {
    isConversationEnded = true;
    chatInput.disabled = true;
    sendBtn.disabled = true;
    if (endBtnEl) endBtnEl.disabled = true;
    
    chatStatus.textContent = curLocale.processing;
    addTypingIndicator();
    
    try {
        // Generate structured triage report via AI
        const triageReport = await ai.generateTriageReport(patientInfo);
        
        removeTypingIndicator();
        
        // Save the AI-generated structured triage to backend
        await saveTriageToBackend(triageReport);
        
        // Redirect to report upload page
        setTimeout(() => {
            window.location.href = '/BPUT/patient/report.php';
        }, 1500);
    } catch (err) {
        removeTypingIndicator();
        console.warn('AI report generation failed, using conversation fallback:', err);
        
        // Fallback: Save basic conversation data
        await saveFallbackTriage();
        
        setTimeout(() => {
            window.location.href = '/BPUT/patient/report.php';
        }, 1500);
    }
}

// ─── Save AI-Generated Triage Report ─────────────────────────
async function saveTriageToBackend(report) {
    // Extract data from structured AI report
    const symptoms = (report.symptoms || []).map(s => s.symptom || s.symptom_en || 'Unknown');
    const timeline = (report.timeline || []).map(t => ({
        time: t.timepoint || 'Unknown',
        event: t.event || t.event_en || ''
    }));
    const safetySignals = (report.urgency_signals || []).map(u => ({
        rule: `RULE-AI-${u.level || 'unknown'}`.toUpperCase(),
        signal: u.signal || '',
        source: `AI Analysis (${language})`,
        reason: u.reasoning || u.recommended_action || ''
    }));
    
    const summary = report.summary_en || report.chief_complaint || 
        `Patient intake completed in ${language}. AI-generated triage report compiled for clinical review.`;
    
    try {
        await fetch('/BPUT/api/ai.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'save_triage',
                case_id: caseId,
                summary: summary,
                symptoms: symptoms,
                timeline: timeline,
                missing_information: report.missing_information || [],
                followup_questions: (report.follow_up_questions || []).map(q => q.question_en || q),
                safety_signals: safetySignals
            })
        });
    } catch(e) {
        console.warn('Failed to save triage:', e);
    }
}

// ─── Fallback Triage (if AI report generation fails) ─────────
async function saveFallbackTriage() {
    const patientMessages = conversationHistory
        .filter(m => m.speaker === 'PATIENT')
        .map(m => m.message)
        .join(' ');
    
    const summary = `Patient completed text intake in ${language}. ${conversationHistory.length} messages exchanged. Full conversation transcript available for manual clinical review. AI-generated structured report could not be produced — manual review recommended.`;
    
    try {
        await fetch('/BPUT/api/ai.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'save_triage',
                case_id: caseId,
                summary: summary,
                symptoms: ['Requires manual review — AI report unavailable'],
                timeline: [{ time: 'Current session', event: 'Text intake completed' }],
                missing_information: ['AI-structured triage report could not be generated', 'Manual clinical review required'],
                followup_questions: ['Review full conversation transcript for clinical details'],
                safety_signals: []
            })
        });
    } catch(e) {
        console.warn('Failed to save fallback triage:', e);
    }
}
</script>
</body>
</html>
