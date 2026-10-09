<?php
/**
 * MEDGem — Voice Interview Interface
 * 100% AI-Powered Dynamic Voice Conversation with Auto-Mic Activation & Responsive Design
 * Uses MEDGem engine with Gemini + Web Speech API.
 */
require_once __DIR__ . '/../includes/security.php';
setSecurityHeaders();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Voice Intake — MEDGem</title>
    <link rel="stylesheet" href="/BPUT/assets/css/medgem.css">
    <link rel="stylesheet" href="/BPUT/assets/css/patient.css">
    <style>
        :root {
            --orb-size: 110px;
        }
        body {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .voice-wrapper {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 80px 16px 32px;
            width: 100%;
        }
        .voice-card {
            background: rgba(255, 255, 255, 0.92);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.8);
            box-shadow: 0 20px 50px -15px rgba(15, 23, 42, 0.12), 0 4px 16px rgba(15, 23, 42, 0.04);
            border-radius: 28px;
            width: 100%;
            max-width: 540px;
            padding: clamp(24px, 5vw, 40px);
            text-align: center;
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .voice-topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            width: 100%;
            margin-bottom: 24px;
        }
        .voice-back-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: var(--neutral-600);
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
            padding: 6px 14px;
            background: #f1f5f9;
            border-radius: 99px;
            transition: all 0.2s ease;
        }
        .voice-back-btn:hover {
            background: #e2e8f0;
            color: var(--neutral-900);
        }
        .voice-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: 4px 12px;
            border-radius: 99px;
            background: #eff6ff;
            color: #1d4ed8;
            border: 1px solid #bfdbfe;
        }
        .voice-badge__dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: #2563eb;
            animation: blink 1.5s infinite;
        }
        @keyframes blink { 0%, 100% { opacity: 1; } 50% { opacity: 0.3; } }

        /* Orb Area */
        .orb-container {
            position: relative;
            width: var(--orb-size);
            height: var(--orb-size);
            margin: 20px auto;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .voice-orb-button {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            background: linear-gradient(135deg, #1c58ef 0%, #3b82f6 100%);
            border: none;
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            position: relative;
            z-index: 2;
            box-shadow: 0 12px 30px rgba(28, 88, 239, 0.35);
            transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1), background 0.3s ease, box-shadow 0.3s ease;
            outline: none;
            -webkit-tap-highlight-color: transparent;
        }
        .voice-orb-button:active {
            transform: scale(0.94);
        }

        /* Orb Ripple Rings */
        .orb-ring {
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            border-radius: 50%;
            border: 2px solid rgba(28, 88, 239, 0.4);
            pointer-events: none;
            z-index: 1;
            opacity: 0;
            transition: all 0.3s ease;
        }

        /* Speaking State */
        .voice-orb-button.speaking {
            background: linear-gradient(135deg, #7c3aed 0%, #3b82f6 100%);
            box-shadow: 0 12px 36px rgba(124, 58, 237, 0.45);
            animation: orbBounce 2s infinite ease-in-out;
        }
        .voice-orb-button.speaking .orb-ring {
            animation: ripplePulse 1.8s infinite ease-out;
            border-color: rgba(124, 58, 237, 0.5);
        }
        @keyframes orbBounce {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.06); }
        }

        /* Listening State (Auto-Mic On) */
        .voice-orb-button.listening {
            background: linear-gradient(135deg, #dc2626 0%, #f43f5e 100%);
            box-shadow: 0 12px 36px rgba(220, 38, 38, 0.5);
            animation: listeningPulse 1.2s infinite ease-in-out;
        }
        .voice-orb-button.listening + .orb-ring {
            opacity: 1;
            animation: ripplePulse 1.4s infinite ease-out;
            border-color: rgba(220, 38, 38, 0.6);
        }
        @keyframes listeningPulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.08); }
        }
        @keyframes ripplePulse {
            0% { transform: scale(1); opacity: 0.8; }
            100% { transform: scale(1.6); opacity: 0; }
        }

        /* Status Banner */
        .voice-status-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 18px;
            border-radius: 99px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            font-size: 13px;
            font-weight: 700;
            color: var(--neutral-800);
            margin-top: 8px;
            margin-bottom: 16px;
            transition: all 0.3s ease;
        }
        .voice-status-pill.listening {
            background: #fef2f2;
            border-color: #fecaca;
            color: #dc2626;
        }
        .voice-status-pill.speaking {
            background: #f5f3ff;
            border-color: #ddd6fe;
            color: #7c3aed;
        }

        /* Transcript Card */
        .transcript-box {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 16px 20px;
            width: 100%;
            min-height: 80px;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            font-size: 14px;
            line-height: 1.6;
            color: #334155;
            font-weight: 500;
            margin-bottom: 20px;
            box-shadow: inset 0 2px 4px rgba(0,0,0,0.02);
            transition: all 0.3s ease;
        }

        /* Conversation Log */
        .conversation-log {
            width: 100%;
            max-height: 180px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 10px;
            padding: 10px 4px;
            scroll-behavior: smooth;
        }
        .log-bubble {
            padding: 10px 14px;
            border-radius: 14px;
            font-size: 13px;
            line-height: 1.5;
            max-width: 85%;
            word-wrap: break-word;
            animation: fadeIn 0.3s ease;
        }
        .log-bubble--bot {
            align-self: flex-start;
            background: #f1f5f9;
            color: #1e293b;
            border-bottom-left-radius: 4px;
        }
        .log-bubble--user {
            align-self: flex-end;
            background: #1c58ef;
            color: #ffffff;
            border-bottom-right-radius: 4px;
        }
        .log-speaker {
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            margin-bottom: 4px;
            opacity: 0.8;
            letter-spacing: 0.5px;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(6px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Error Banner */
        .api-error-banner {
            display: none;
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #991b1b;
            padding: 10px 16px;
            font-size: 12px;
            border-radius: 12px;
            width: 100%;
            margin-bottom: 16px;
            text-align: center;
        }
        .api-error-banner.visible { display: block; }

        /* Thinking animation */
        .ai-thinking {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: #7c3aed;
            font-weight: 600;
        }
        .ai-thinking .dot {
            width: 7px; height: 7px;
            border-radius: 50%;
            background: #7c3aed;
            animation: dotPulse 1.2s infinite ease-in-out;
        }
        .ai-thinking .dot:nth-child(2) { animation-delay: 0.2s; }
        .ai-thinking .dot:nth-child(3) { animation-delay: 0.4s; }
        @keyframes dotPulse {
            0%, 80%, 100% { opacity: 0.3; transform: scale(0.7); }
            40% { opacity: 1; transform: scale(1.2); }
               .voice-wrapper {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 16px;
            width: 100%;
            min-height: calc(100vh - 40px);
            box-sizing: border-box;
        }
        .voice-card {
            background: rgba(255, 255, 255, 0.94);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.8);
            box-shadow: 0 20px 50px -15px rgba(15, 23, 42, 0.12), 0 4px 16px rgba(15, 23, 42, 0.04);
            border-radius: 28px;
            width: 100%;
            max-width: 480px;
            padding: clamp(24px, 5vw, 40px);
            text-align: center;
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: center;
            margin: auto;
        }

        /* Hide transcript and typed message text below mic as requested */
        .transcript-box,
        .conversation-log {
            display: none !important;
        }

        /* Mobile responsive centering */
        @media (max-width: 640px) {
            body {
                justify-content: center;
            }
            .voice-wrapper {
                padding: 16px 12px !important;
                min-height: calc(100vh - 36px) !important;
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
            }
            .voice-card {
                padding: 28px 20px !important;
                border-radius: 24px !important;
                margin: auto !important;
                max-width: 100% !important;
                box-shadow: 0 12px 36px rgba(15, 23, 42, 0.1) !important;
            }
            :root {
                --orb-size: 100px;
            }
            .orb-container {
                margin: 24px auto !important;
            }
        }
    </style>
</head>
<body>

<div class="prototype-banner">
    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline;vertical-align:-2px;margin-right:4px"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
    Synthetic Data &middot; Educational Prototype
</div>

<main class="voice-wrapper">
    <div class="voice-card">
        <!-- Top Bar -->
        <div class="voice-topbar">
            <a href="/BPUT/patient/mode.php" class="voice-back-btn">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
                Back
            </a>
            <div class="voice-badge">
                <span class="voice-badge__dot"></span>
                AI Voice Intake
            </div>
        </div>

        <div style="margin-bottom:8px">
            <h1 style="font-size:1.35rem;font-weight:800;color:#0f172a;margin:0">MEDGem AI Assistant</h1>
            <p style="font-size:12px;color:#64748b;margin:4px 0 0">Interactive Voice Intake &amp; Triage</p>
        </div>

        <!-- API Error Banner -->
        <div class="api-error-banner" id="apiErrorBanner"></div>

        <!-- Voice Orb Container -->
        <div class="orb-container">
            <button class="voice-orb-button" id="voiceOrb" onclick="toggleRecording()" aria-label="Toggle Microphone">
                <svg xmlns="http://www.w3.org/2000/svg" width="42" height="42" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" id="micIcon"><path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/><line x1="12" y1="19" x2="12" y2="23"/><line x1="8" y1="23" x2="16" y2="23"/></svg>
            </button>
            <div class="orb-ring"></div>
        </div>

        <!-- Status Pill -->
        <div class="voice-status-pill" id="voiceStatus">
            <span>Initializing Voice Assistant...</span>
        </div>

        <!-- Hidden DOM elements to prevent JS errors while keeping UI clean -->
        <div class="transcript-box" id="voiceTranscript" style="display:none !important"></div>
        <div class="conversation-log" id="voiceLog" style="display:none !important"></div>

        <!-- Action Footer -->
        <div style="margin-top:24px;width:100%">
            <button class="btn btn--danger btn--full" id="endVoiceBtn" onclick="endVoiceConversation()" style="display:none;padding:12px;font-weight:700;border-radius:12px">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="6" y="4" width="4" height="16"/><rect x="14" y="4" width="4" height="16"/></svg>
                Finish &amp; Generate Report
            </button>
        </div>
    </div>
</main>

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
const voiceOrb = document.getElementById('voiceOrb');
const voiceStatus = document.getElementById('voiceStatus');
const voiceTranscript = document.getElementById('voiceTranscript');
const voiceLog = document.getElementById('voiceLog');
const endVoiceBtn = document.getElementById('endVoiceBtn');
const apiErrorBanner = document.getElementById('apiErrorBanner');

// ─── State ───────────────────────────────────────────────────
let isRecording = false;
let recognition = null;
let speechSynth = window.speechSynthesis;
let conversationHistory = [];
let isConversationEnded = false;
let turnCount = 0;
let isSpeaking = false;
let autoMicTimeout = null;

// ─── Localization ────────────────────────────────────────────
const voiceLocale = {
    English: {
        speaking: 'MEDGem is speaking...',
        listening: 'Listening... Speak now',
        yourTurn: 'Your turn to speak...',
        processing: 'Compiling structured report in English...',
        farewell: 'Thank you. Your clinical case is being prepared for healthcare staff review.',
        endLabel: 'Finish & Generate Report'
    },
    Hindi: {
        speaking: 'MEDGem बोल रहा है...',
        listening: 'सुन रहा है... अब बोलिए',
        yourTurn: 'अब आपकी बोलने की बारी है...',
        processing: 'रिपोर्ट तैयार हो रही है...',
        farewell: 'धन्यवाद। आपकी रिपोर्ट डॉक्टर और नर्स के लिए तैयार की जा रही है।',
        endLabel: 'समाप्त करें'
    },
    Odia: {
        speaking: 'MEDGem କହୁଛନ୍ତି...',
        listening: 'ଶୁଣୁଛି... କୁହନ୍ତୁ',
        yourTurn: 'ଆପଣ କହିବାର ସମୟ...',
        processing: 'ରିପୋର୍ଟ ପ୍ରସ୍ତୁତ ହେଉଛି...',
        farewell: 'ଧନ୍ୟବାଦ। ଡାକ୍ତର ଓ ନର୍ସଙ୍କ ଯାଞ୍ଚ ପାଇଁ ରିପୋର୍ଟ ପ୍ରସ୍ତୁତ କରାଯାଉଛି।',
        endLabel: 'ସମାପ୍ତ କରନ୍ତୁ'
    }
};

const curVoice = voiceLocale[language] || voiceLocale.English;

// ─── Language Config ──────────────────────────────────────────
const langConfig = (typeof MEDGEM_AI_CONFIG !== 'undefined' && MEDGEM_AI_CONFIG.LANGUAGE_MAP) 
    ? (MEDGEM_AI_CONFIG.LANGUAGE_MAP[language] || MEDGEM_AI_CONFIG.LANGUAGE_MAP.English) 
    : { speechRecognitionLang: 'en-IN', speechSynthesisLang: 'en-US' };

// ─── Initialize AI Engine ────────────────────────────────────
const ai = triageAI;
ai.clearChat();

if (typeof MEDGEM_AI_CONFIG !== 'undefined') {
    ai.setApiKey(MEDGEM_AI_CONFIG.API_KEYS);
    if (MEDGEM_AI_CONFIG.MODEL) ai.setModel(MEDGEM_AI_CONFIG.MODEL);
    if (MEDGEM_AI_CONFIG.FALLBACK_MODELS && MEDGEM_AI_CONFIG.FALLBACK_MODELS.length > 0) {
        ai.fallbackModels = MEDGEM_AI_CONFIG.FALLBACK_MODELS;
    }
}

const patientInfo = {
    trackId: trackId,
    age: patientAge,
    gender: patientSex
};

// ─── Check Browser Support ──────────────────────────────────
if (!('webkitSpeechRecognition' in window) && !('SpeechRecognition' in window)) {
    updateStatus('Voice not supported in this browser', '');
    voiceTranscript.textContent = 'Please use Chrome, Edge, or Safari for voice intake, or switch to Text Mode.';
    voiceOrb.style.opacity = '0.5';
    voiceOrb.style.cursor = 'not-allowed';
    voiceOrb.onclick = null;
} else {
    setTimeout(() => startConversation(), 600);
}

// ─── Start Conversation ─────────────────────────────────────
async function startConversation() {
    endVoiceBtn.style.display = 'block';
    updateStatus(curVoice.speaking, 'speaking');

    try {
        const greeting = await ai.sendChatMessage(
            `[System: New patient voice intake. Track ID: ${trackId}, Age: ${patientInfo.age || 'Not provided'}, Sex: ${patientInfo.gender || 'Not provided'}. Preferred language: ${language}. Greet them warmly in ${language} without asking for legal name or PII, and ask about their health symptoms. Keep your response short, natural, and conversational for voice playback.]`,
            patientInfo,
            null,
            language
        );
        
        const cleanGreeting = cleanAIResponse(greeting);
        speakText(cleanGreeting);
    } catch (err) {
        updateStatus('Error connecting to AI', '');
        voiceTranscript.textContent = err.message;
        showError(err.message);
    }
}

// ─── Speak AI Response & AUTO-TURN MIC ON ────────────────────
function speakText(text) {
    if (isConversationEnded) return;
    
    // Stop recording while AI speaks
    if (isRecording) {
        stopRecording();
    }
    
    isSpeaking = true;
    updateStatus(curVoice.speaking, 'speaking');
    voiceTranscript.textContent = `"${text}"`;
    
    addToLog('MEDGem', text);
    saveMessage('MEDGEM', text);
    conversationHistory.push({ speaker: 'MEDGEM', message: text });
    
    speechSynth.cancel();
    
    const utterance = new SpeechSynthesisUtterance(text);
    utterance.rate = 0.95;
    utterance.pitch = 1.0;
    utterance.lang = langConfig.speechSynthesisLang;
    
    // AUTO-TURN ON MIC WHEN AI SPEECH ENDS
    utterance.onend = () => {
        isSpeaking = false;
        if (!isConversationEnded) {
            updateStatus(curVoice.listening, 'listening');
            voiceTranscript.textContent = curVoice.yourTurn;
            
            // Auto start recording seamlessly
            autoMicTimeout = setTimeout(() => {
                if (!isSpeaking && !isConversationEnded && !isRecording) {
                    startRecording();
                }
            }, 350);
        }
    };
    
    utterance.onerror = () => {
        isSpeaking = false;
        if (!isConversationEnded) {
            updateStatus(curVoice.listening, 'listening');
            voiceTranscript.textContent = curVoice.yourTurn;
            autoMicTimeout = setTimeout(() => {
                if (!isSpeaking && !isConversationEnded && !isRecording) {
                    startRecording();
                }
            }, 400);
        }
    };
    
    speechSynth.speak(utterance);
}

// ─── Recording Controls ─────────────────────────────────────
function toggleRecording() {
    if (isSpeaking || isConversationEnded) return;
    
    if (isRecording) {
        stopRecording();
    } else {
        startRecording();
    }
}

function startRecording() {
    if (isRecording || isSpeaking || isConversationEnded) return;
    
    const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
    if (!SpeechRecognition) return;
    
    try {
        if (recognition) {
            try { recognition.abort(); } catch(e){}
        }
        
        recognition = new SpeechRecognition();
        recognition.lang = langConfig.speechRecognitionLang;
        recognition.interimResults = true;
        recognition.maxAlternatives = 1;
        
        recognition.onstart = () => {
            isRecording = true;
            updateStatus(curVoice.listening, 'listening');
            voiceTranscript.textContent = 'Listening... Speak now';
        };
        
        recognition.onresult = (event) => {
            let transcript = '';
            for (let i = event.resultIndex; i < event.results.length; i++) {
                transcript += event.results[i][0].transcript;
            }
            voiceTranscript.textContent = transcript;
            
            if (event.results[event.results.length - 1].isFinal) {
                processPatientResponse(transcript);
            }
        };
        
        recognition.onerror = (event) => {
            isRecording = false;
            if (event.error === 'no-speech') {
                // Auto restart recording if user was quiet brief moment
                if (!isSpeaking && !isConversationEnded) {
                    updateStatus('Listening for your response...', 'listening');
                    setTimeout(() => {
                        if (!isSpeaking && !isConversationEnded && !isRecording) {
                            startRecording();
                        }
                    }, 800);
                }
            } else if (event.error !== 'aborted') {
                updateStatus('Tap mic to respond', '');
            }
        };
        
        recognition.onend = () => {
            isRecording = false;
            if (!isSpeaking && !isConversationEnded && voiceStatus.classList.contains('listening')) {
                // Keep visually ready
            }
        };
        
        recognition.start();
    } catch(e) {
        isRecording = false;
        updateStatus('Tap mic to respond', '');
    }
}

function stopRecording() {
    if (recognition) {
        try { recognition.stop(); } catch(e){}
    }
    isRecording = false;
    if (!isSpeaking && !isConversationEnded) {
        updateStatus('Tap mic to speak', '');
    }
}

// ─── Process Patient Response ──────────────────────────────
async function processPatientResponse(text) {
    if (!text.trim() || isConversationEnded) return;
    
    stopRecording();
    
    addToLog('You', text);
    saveMessage('PATIENT', text);
    conversationHistory.push({ speaker: 'PATIENT', message: text });
    
    turnCount++;
    
    // AI Thinking State
    updateStatus('AI is processing...', 'speaking');
    voiceTranscript.innerHTML = '<div class="ai-thinking"><div class="dot"></div><div class="dot"></div><div class="dot"></div><span>MEDGem is thinking...</span></div>';
    
    try {
        const aiResponse = await ai.sendChatMessage(text, patientInfo, null, language);
        const cleanResponse = cleanAIResponse(aiResponse);
        
        const isComplete = aiResponse.includes('[TRIAGE_COMPLETE]');
        const maxTurns = (typeof MEDGEM_AI_CONFIG !== 'undefined' && MEDGEM_AI_CONFIG.MAX_TURNS > 0) 
            ? MEDGEM_AI_CONFIG.MAX_TURNS 
            : 0;
        const forceEnd = maxTurns > 0 && turnCount >= maxTurns;
        
        if (isComplete || forceEnd) {
            speakText(cleanResponse);
            const checkDone = setInterval(() => {
                if (!speechSynth.speaking && !isSpeaking) {
                    clearInterval(checkDone);
                    setTimeout(() => finalizeVoiceConversation(), 600);
                }
            }, 300);
        } else {
            speakText(cleanResponse);
        }
    } catch (err) {
        updateStatus('Error: ' + err.message, '');
        voiceTranscript.textContent = 'Tap microphone to try again.';
        showError(err.message);
    }
}

// ─── Status & UI Helpers ────────────────────────────────────
function updateStatus(text, state) {
    voiceStatus.textContent = text;
    voiceStatus.className = 'voice-status-pill ' + (state || '');
    voiceOrb.className = 'voice-orb-button ' + (state || '');
}

function addToLog(speaker, text) {
    const div = document.createElement('div');
    div.className = `log-bubble log-bubble--${speaker === 'MEDGem' ? 'bot' : 'user'}`;
    div.innerHTML = `<div class="log-speaker">${speaker}</div>${escapeHtml(text)}`;
    voiceLog.appendChild(div);
    voiceLog.scrollTop = voiceLog.scrollHeight;
}

function escapeHtml(str) {
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

function cleanAIResponse(text) {
    return text.replace(/\[TRIAGE_COMPLETE\]/g, '').trim();
}

function showError(msg) {
    apiErrorBanner.textContent = msg;
    apiErrorBanner.classList.add('visible');
    setTimeout(() => apiErrorBanner.classList.remove('visible'), 8000);
}

// ─── Save Message to Backend ────────────────────────────────
async function saveMessage(speaker, message) {
    try {
        await fetch('/BPUT/api/conversation.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ case_id: caseId, mode: 'VOICE', speaker, message })
        });
    } catch(e) {}
}

// ─── End Voice Conversation ─────────────────────────────────
async function endVoiceConversation() {
    if (isConversationEnded) return;
    clearTimeout(autoMicTimeout);
    speechSynth.cancel();
    if (recognition) { try { recognition.stop(); } catch(e){} }
    await finalizeVoiceConversation();
}

async function finalizeVoiceConversation() {
    if (isConversationEnded) return;
    isConversationEnded = true;
    clearTimeout(autoMicTimeout);
    speechSynth.cancel();
    if (recognition) { try { recognition.stop(); } catch(e){} }
    
    updateStatus(curVoice.processing, '');
    voiceOrb.style.opacity = '0.5';
    voiceOrb.onclick = null;
    endVoiceBtn.style.display = 'none';
    
    try {
        const triageReport = await ai.generateTriageReport(patientInfo);
        await saveTriageToBackend(triageReport);
    } catch (err) {
        console.warn('AI report generation fallback:', err);
        await saveFallbackTriage();
    }
    
    const farewell = new SpeechSynthesisUtterance(curVoice.farewell);
    farewell.rate = 0.95;
    farewell.lang = langConfig.speechSynthesisLang;
    farewell.onend = () => {
        setTimeout(() => { window.location.href = '/BPUT/patient/report.php'; }, 1200);
    };
    farewell.onerror = () => {
        setTimeout(() => { window.location.href = '/BPUT/patient/report.php'; }, 1200);
    };
    speechSynth.speak(farewell);
    
    voiceTranscript.textContent = 'Preparing your case in English for nurse & doctor review...';
}

// ─── Save AI Triage Report ──────────────────────────────────
async function saveTriageToBackend(report) {
    const symptoms = (report.symptoms || []).map(s => s.symptom || s.symptom_en || 'Unknown');
    const timeline = (report.timeline || []).map(t => ({
        time: t.timepoint || 'Unknown',
        event: t.event || t.event_en || ''
    }));
    const safetySignals = (report.urgency_signals || []).map(u => ({
        rule: `RULE-AI-${u.level || 'unknown'}`.toUpperCase(),
        signal: u.signal || '',
        source: `AI Voice Analysis (${language})`,
        reason: u.reasoning || u.recommended_action || ''
    }));
    
    const summary = report.summary_en || report.chief_complaint || 
        `Patient completed voice intake in ${language}. AI-generated triage report compiled for clinical review.`;
    
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
    } catch(e) {}
}

async function saveFallbackTriage() {
    const summary = `Patient completed voice intake in ${language}. ${conversationHistory.length} messages exchanged. Manual review recommended.`;
    
    try {
        await fetch('/BPUT/api/ai.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'save_triage',
                case_id: caseId,
                summary: summary,
                symptoms: ['Requires manual review — AI report unavailable'],
                timeline: [{ time: 'Current session', event: 'Voice intake completed' }],
                missing_information: ['AI-structured triage report could not be generated'],
                followup_questions: ['Review full conversation transcript'],
                safety_signals: []
            })
        });
    } catch(e) {}
}
</script>
</body>
</html>
