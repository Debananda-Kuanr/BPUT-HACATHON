/**
 * Swasthya Triage AI — App Controller
 * ════════════════════════════════════════════════════════════════
 * Handles real-time multilingual chat, multimodal file/report
 * uploads (PDF/PNG/JPG), dynamic voice talk (TTS & STT), and
 * structured report rendering.
 *
 * All AI questioning is fully dynamic — the Gemini model asks
 * clinically relevant questions based on what the patient says.
 * No predefined question scripts or symptom lists.
 *
 * Configuration is read from MEDGEM_AI_CONFIG (config/ai-config.js).
 * ════════════════════════════════════════════════════════════════
 */

(function () {
  'use strict';

  /* ─── State ─────────────────────────────────────────── */
  let currentReport = null;
  let isRecording = false;
  let recognition = null;
  let messageCount = 0;
  let usedVoiceForCurrentMessage = false;
  let speechSilenceTimer = null;
  let availableVoices = [];
  let currentAttachment = null;
  let conversationStarted = false;

  const cfg = (typeof MEDGEM_AI_CONFIG !== 'undefined') ? MEDGEM_AI_CONFIG : {};

  const $ = s => document.querySelector(s);
  const $$ = s => document.querySelectorAll(s);

  /* ─── Init ──────────────────────────────────────────── */

  function init() {
    checkApiStatus();
    bindEvents();
    setupSpeech();
    initVoices();
    autoResizeTextarea();
  }

  /* ─── API Status Check ──────────────────────────────── */

  function checkApiStatus() {
    const dot = $('#apiDot');
    const label = $('#apiLabel');
    const banner = $('#noKeyBanner');

    if (!triageAI.getActiveKey()) {
      // No API key configured
      if (dot) dot.className = 'api-dot offline';
      if (label) label.textContent = 'No API Key';
      if (banner) banner.classList.remove('hidden');

      // Disable input
      const chatInput = $('#chatInput');
      const sendBtn = $('#sendBtn');
      const voiceBtn = $('#voiceBtn');
      if (chatInput) chatInput.disabled = true;
      if (sendBtn) sendBtn.disabled = true;
      if (voiceBtn) voiceBtn.disabled = true;
      return;
    }

    // API key is set — validate it
    if (dot) dot.className = 'api-dot checking';
    if (label) label.textContent = 'Connecting...';

    triageAI.validateApiKey().then(r => {
      if (r.valid) {
        if (dot) dot.className = 'api-dot online';
        if (label) label.textContent = r.model || 'Connected';
        if (banner) banner.classList.add('hidden');
      } else {
        if (dot) dot.className = 'api-dot offline';
        if (label) label.textContent = r.error || 'Invalid key';
        if (banner) banner.classList.remove('hidden');
      }
    }).catch(() => {
      if (dot) dot.className = 'api-dot offline';
      if (label) label.textContent = 'Connection error';
    });
  }

  function initVoices() {
    if ('speechSynthesis' in window) {
      availableVoices = window.speechSynthesis.getVoices();
      window.speechSynthesis.onvoiceschanged = () => {
        availableVoices = window.speechSynthesis.getVoices();
      };
    }
  }

  /* ─── Event Bindings ────────────────────────────────── */

  function bindEvents() {
    // Chat input
    const input = $('#chatInput');
    input.addEventListener('input', () => {
      $('#sendBtn').disabled = !input.value.trim() && !currentAttachment;
      autoResizeTextarea();
      if (input.value.trim().length > 0 && !isRecording) {
        usedVoiceForCurrentMessage = false;
      }
    });

    input.addEventListener('keydown', e => {
      if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        usedVoiceForCurrentMessage = false;
        sendMessage();
      }
    });

    $('#sendBtn').addEventListener('click', () => {
      sendMessage();
    });

    // Voice button
    $('#voiceBtn').addEventListener('click', toggleVoice);

    // Attachment Upload
    $('#attachBtn').addEventListener('click', () => $('#reportFileInput').click());
    $('#reportFileInput').addEventListener('change', handleFileUpload);
    $('#removeFileBtn').addEventListener('click', removeAttachment);

    // New chat
    $('#newChatBtn').addEventListener('click', resetChat);

    // Generate report
    $('#generateReportBtn').addEventListener('click', generateReport);
    $('#regenerateBtn').addEventListener('click', generateReport);

    // Tabs
    $$('.tab').forEach(t => t.addEventListener('click', () => switchTab(t.dataset.tab)));

    // Referral
    $('#referralBtn').addEventListener('click', genReferral);
    $('#referralClose').addEventListener('click', () => $('#referralModal').classList.add('hidden'));
    $('#referralModal').addEventListener('click', e => { if (e.target === $('#referralModal')) $('#referralModal').classList.add('hidden'); });
    $('#copyReferralBtn').addEventListener('click', () => {
      navigator.clipboard.writeText($('#referralText').textContent).then(() => {
        $('#copyReferralBtn').textContent = 'Copied!';
        setTimeout(() => { $('#copyReferralBtn').textContent = 'Copy to Clipboard'; }, 2000);
      });
    });

    // Toast
    $('#toastClose').addEventListener('click', () => $('#toast').classList.add('hidden'));
  }

  /* ─── File Upload Handler ───────────────────────────── */

  function handleFileUpload(e) {
    const file = e.target.files?.[0];
    if (!file) return;

    if (file.size > 12 * 1024 * 1024) {
      toast('File size exceeds 12MB limit.');
      e.target.value = '';
      return;
    }

    const reader = new FileReader();
    reader.onload = () => {
      const base64Data = reader.result.split(',')[1];
      currentAttachment = {
        name: file.name,
        size: formatBytes(file.size),
        mimeType: file.type || (file.name.endsWith('.pdf') ? 'application/pdf' : 'image/jpeg'),
        base64: base64Data
      };

      $('#filePreviewName').textContent = file.name;
      $('#filePreviewSize').textContent = `(${currentAttachment.size})`;
      $('#filePreviewBar').classList.remove('hidden');
      $('#sendBtn').disabled = false;
      toast(`Attached: ${file.name}`);
    };

    reader.readAsDataURL(file);
    e.target.value = '';
  }

  function removeAttachment() {
    currentAttachment = null;
    $('#filePreviewBar').classList.add('hidden');
    const input = $('#chatInput');
    $('#sendBtn').disabled = !input.value.trim();
  }

  function formatBytes(bytes) {
    if (bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
  }

  /* ─── Get Selected Language ─────────────────────────── */

  function getSelectedLanguage() {
    const langSelect = $('#langSelect');
    return langSelect ? langSelect.value : (cfg.DEFAULT_LANGUAGE || 'English');
  }

  /* ─── Chat ──────────────────────────────────────────── */

  async function sendMessage() {
    const input = $('#chatInput');
    const text = input.value.trim();
    const attached = currentAttachment;

    if (!text && !attached) return;
    if (!triageAI.getActiveKey()) { toast('API key not configured. Open config/ai-config.js to set it up.'); return; }

    const isVoiceTurn = usedVoiceForCurrentMessage;
    const lang = getSelectedLanguage();

    if (isRecording) stopRecording();

    const welcome = $('#welcomeMsg');
    if (welcome) welcome.style.display = 'none';

    // Auto-start conversation with AI greeting on first message
    if (!conversationStarted) {
      conversationStarted = true;
    }

    // Add user message with attachment badge/card if present
    addMessage('user', text, attached);
    input.value = '';
    removeAttachment();
    input.dispatchEvent(new Event('input'));
    $('#sendBtn').disabled = true;

    usedVoiceForCurrentMessage = false;

    const typingId = showTyping();

    try {
      const reply = await triageAI.sendChatMessage(text, {
        age: $('#ageInput').value.trim(),
        gender: $('#genderSelect').value
      }, attached, lang);

      removeTyping(typingId);

      const isComplete = reply.includes('[TRIAGE_COMPLETE]');
      const cleanReply = reply.replace(/\[TRIAGE_COMPLETE\]/g, '').trim();

      const msgEl = addMessage('ai', cleanReply);
      messageCount++;

      if (isVoiceTurn) {
        try {
          speakResponse(cleanReply, msgEl, () => {
            if (!isComplete && cfg.AUTO_LISTEN_AFTER_SPEAK !== false) {
              startRecording();
            }
          });
        } catch (ttsErr) {
          console.warn('Voice auto-speak error:', ttsErr);
        }
      }

      $('#generateReportBtn').disabled = false;

      if (isComplete) {
        toast('Triage complete! Generating structured report...');
        setTimeout(() => {
          generateReport();
        }, 800);
      }
    } catch (err) {
      removeTyping(typingId);
      addMessage('error', err.message);
    }
  }

  function addMessage(type, text, attached = null) {
    const container = $('#chatMessages');
    const div = document.createElement('div');
    div.className = `msg msg-${type}`;

    if (type === 'user') {
      const isImg = attached && attached.mimeType && attached.mimeType.startsWith('image/') && attached.base64;
      div.innerHTML = `
        <div class="msg-bubble user-bubble">
          ${attached ? `
            <div class="msg-attached-card ${isImg ? 'has-image' : ''}">
              ${isImg ? `
                <div class="msg-attached-img-wrap">
                  <img src="data:${attached.mimeType};base64,${attached.base64}" alt="${escapeHtml(attached.name)}" class="msg-attached-thumb" />
                </div>
              ` : ''}
              <div class="msg-attached-info">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                <span class="msg-attached-name">${escapeHtml(attached.name)}</span>
                <span class="msg-attached-size">(${attached.size})</span>
              </div>
            </div>` : ''}
          ${text ? `<p class="user-text-content">${escapeHtml(text)}</p>` : ''}
        </div>
        <div class="msg-avatar user-avatar">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
        </div>`;
    } else if (type === 'ai') {
      div.innerHTML = `
        <div class="msg-avatar ai-avatar">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
        </div>
        <div class="msg-bubble ai-bubble">
          <div class="ai-msg-body">
            <p>${formatAIText(text)}</p>
          </div>
          <div class="msg-tools">
            <button type="button" class="btn-speak-msg" title="Listen to message">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14M15.54 8.46a5 5 0 0 1 0 7.07"/></svg>
            </button>
          </div>
        </div>`;

      const speakBtn = div.querySelector('.btn-speak-msg');
      if (speakBtn) {
        speakBtn.addEventListener('click', () => {
          speakResponse(text, div);
        });
      }
    } else if (type === 'error') {
      div.innerHTML = `
        <div class="msg-avatar err-avatar">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        </div>
        <div class="msg-bubble err-bubble"><p>${escapeHtml(text)}</p></div>`;
    }

    container.appendChild(div);
    container.scrollTop = container.scrollHeight;
    return div;
  }

  function showTyping() {
    const id = 'typing-' + Date.now();
    const container = $('#chatMessages');
    const div = document.createElement('div');
    div.className = 'msg msg-ai';
    div.id = id;
    div.innerHTML = `
      <div class="msg-avatar ai-avatar">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
      </div>
      <div class="msg-bubble ai-bubble typing-bubble">
        <span class="dot"></span><span class="dot"></span><span class="dot"></span>
      </div>`;
    container.appendChild(div);
    container.scrollTop = container.scrollHeight;
    return id;
  }

  function removeTyping(id) {
    const el = document.getElementById(id);
    if (el) el.remove();
  }

  function resetChat() {
    stopSpeaking();
    stopRecording();
    removeAttachment();
    triageAI.clearChat();
    messageCount = 0;
    currentReport = null;
    usedVoiceForCurrentMessage = false;
    conversationStarted = false;
    $('#chatMessages').innerHTML = '';

    const welcome = document.createElement('div');
    welcome.className = 'msg msg-system';
    welcome.id = 'welcomeMsg';
    welcome.innerHTML = `
      <div class="msg-content">
        <div class="welcome-icon"><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg></div>
        <h3>Welcome to Swasthya Triage AI</h3>
        <p>Start by speaking or typing your symptoms in your preferred language. You can also upload medical reports (PDF / PNG / JPG).</p>
        <p class="welcome-hindi">अपने लक्षण बताएं या पुरानी मेडिकल रिपोर्ट/पर्ची अपलोड करें।</p>
      </div>`;
    $('#chatMessages').appendChild(welcome);

    $('#reportEmpty').classList.remove('hidden');
    $('#reportContent').classList.add('hidden');
    $('#reportLoading').classList.add('hidden');
    $('#generateReportBtn').disabled = true;
  }

  /* ─── Text-to-Speech (AI Speaking) ──────────────────── */

  function detectHinglishOrHindi(text) {
    if (!text || typeof text !== 'string') return false;
    // 1. Delegate to triageAI if method exists
    if (typeof triageAI !== 'undefined' && typeof triageAI._detectHinglishOrHindi === 'function') {
      return triageAI._detectHinglishOrHindi(text);
    }
    // 2. Devanagari script check
    if (/[\u0900-\u097F]/.test(text)) return true;
    // 3. Fallback keywords check
    const markers = [
      'kripya', 'batayein', 'bataiye', 'bataye', 'batao', 'boliye',
      'aapko', 'aapka', 'aapki', 'aapke', 'mujhe', 'mera', 'meri', 'mere',
      'pareshani', 'takleef', 'mehsoos', 'bukhar', 'marod', 'dast', 'ulti',
      'chakkar', 'kamzori', 'khansi', 'zukham', 'sardi', 'jalan', 'soojan',
      'dawain', 'dawai', 'goli', 'ilaj', 'hai', 'hain', 'ho', 'hoon',
      'raha', 'rahi', 'rahe', 'mein', 'kya', 'nahi', 'nahin'
    ];
    const words = text.toLowerCase().replace(/[^a-z0-9\s]/g, ' ').split(/\s+/).filter(Boolean);
    return words.some(w => markers.includes(w));
  }

  function stopSpeaking() {
    if ('speechSynthesis' in window) {
      window.speechSynthesis.cancel();
    }
    $$('.ai-bubble.speaking').forEach(b => b.classList.remove('speaking'));
  }

  function speakResponse(text, bubbleEl = null, onEndCallback = null) {
    if (!('speechSynthesis' in window)) return;

    stopSpeaking();

    const cleanText = text.replace(/\*\*/g, '').replace(/<[^>]*>/g, '').trim();
    if (!cleanText) return;

    try {
      const utterance = new SpeechSynthesisUtterance(cleanText);
      const isHindi = detectHinglishOrHindi(cleanText);
      const voiceRate = cfg.VOICE_RATE || 0.9;
      const voicePitch = cfg.VOICE_PITCH || 1.0;

      if (isHindi) {
        utterance.lang = 'hi-IN';
        const hiVoice = availableVoices.find(v => v.lang === 'hi-IN' || v.lang.startsWith('hi'));
        if (hiVoice) utterance.voice = hiVoice;
        utterance.rate = voiceRate;
      } else {
        utterance.lang = 'en-IN';
        const enVoice = availableVoices.find(v => v.lang === 'en-IN' || v.lang === 'en-US' || v.lang.startsWith('en'));
        if (enVoice) utterance.voice = enVoice;
        utterance.rate = voiceRate + 0.1;
      }

      utterance.pitch = voicePitch;

      if (bubbleEl) {
        const b = bubbleEl.querySelector('.ai-bubble');
        if (b) b.classList.add('speaking');
      }

      utterance.onend = () => {
        if (bubbleEl) {
          const b = bubbleEl.querySelector('.ai-bubble');
          if (b) b.classList.remove('speaking');
        }
        if (typeof onEndCallback === 'function') {
          onEndCallback();
        }
      };

      utterance.onerror = () => {
        if (bubbleEl) {
          const b = bubbleEl.querySelector('.ai-bubble');
          if (b) b.classList.remove('speaking');
        }
      };

      window.speechSynthesis.speak(utterance);
    } catch (e) {
      console.warn('Speech synthesis error:', e);
      if (typeof onEndCallback === 'function') {
        onEndCallback();
      }
    }
  }

  /* ─── Speech Recognition (Voice Input) ──────────────── */

  function setupSpeech() {
    const SR = window.SpeechRecognition || window.webkitSpeechRecognition;
    if (!SR) {
      const voiceBtn = $('#voiceBtn');
      if (voiceBtn) {
        voiceBtn.disabled = true;
        voiceBtn.title = 'Speech recognition not supported in this browser';
      }
      return;
    }

    recognition = new SR();
    recognition.continuous = true;
    recognition.interimResults = true;

    const silenceTimeout = cfg.SILENCE_TIMEOUT_MS || 800;

    recognition.onresult = e => {
      let final = '', interim = '';
      for (let i = e.resultIndex; i < e.results.length; i++) {
        if (e.results[i].isFinal) final += e.results[i][0].transcript + ' ';
        else interim = e.results[i][0].transcript;
      }

      if (final) {
        const inp = $('#chatInput');
        inp.value = (inp.value + ' ' + final).trim();
        inp.dispatchEvent(new Event('input'));
        usedVoiceForCurrentMessage = true;

        clearTimeout(speechSilenceTimer);
        speechSilenceTimer = setTimeout(() => {
          if (inp.value.trim().length > 0) {
            sendMessage();
          }
        }, silenceTimeout);
      }

      if (interim) {
        $('#voiceStatus').textContent = interim;
        usedVoiceForCurrentMessage = true;
        clearTimeout(speechSilenceTimer);
      }
    };

    recognition.onend = () => {
      if (isRecording) {
        try { recognition.start(); } catch { stopRecording(); }
      }
    };

    recognition.onerror = e => {
      if (e.error === 'not-allowed') toast('Microphone access denied.');
      stopRecording();
    };
  }

  function toggleVoice() {
    if (isRecording) {
      stopRecording();
      const inp = $('#chatInput');
      if (inp.value.trim().length > 0) {
        usedVoiceForCurrentMessage = true;
        sendMessage();
      }
    } else {
      stopSpeaking();
      startRecording();
    }
  }

  function startRecording() {
    if (!recognition) return;
    stopSpeaking();

    // Set recognition language based on selected language
    const lang = getSelectedLanguage();
    const langMap = cfg.LANGUAGE_MAP || {};
    const langCfg = langMap[lang] || langMap.English || {};
    recognition.lang = langCfg.speechRecognitionLang || 'hi-IN';

    try {
      recognition.start();
      isRecording = true;
      usedVoiceForCurrentMessage = true;
      $('#voiceBtn').classList.add('recording');
      const statusEl = $('#voiceStatus');
      if (statusEl) statusEl.textContent = 'Listening...';
    } catch {
      // already active
    }
  }

  function stopRecording() {
    clearTimeout(speechSilenceTimer);
    if (recognition) {
      try { recognition.stop(); } catch {}
    }
    isRecording = false;
    const voiceBtn = $('#voiceBtn');
    if (voiceBtn) voiceBtn.classList.remove('recording');
    const statusEl = $('#voiceStatus');
    if (statusEl) statusEl.textContent = '';
  }

  /* ─── Report Generation ─────────────────────────────── */

  async function generateReport() {
    $('#reportEmpty').classList.add('hidden');
    $('#reportContent').classList.add('hidden');
    $('#reportLoading').classList.remove('hidden');

    try {
      const report = await triageAI.generateTriageReport({
        age: $('#ageInput').value.trim(),
        gender: $('#genderSelect').value
      });
      currentReport = report;
      renderReport(report);
    } catch (err) {
      toast('Report generation failed: ' + err.message);
      $('#reportEmpty').classList.remove('hidden');
      $('#reportLoading').classList.add('hidden');
    }
  }

  /* ─── Render Report ─────────────────────────────────── */

  function renderReport(d) {
    $('#reportLoading').classList.add('hidden');
    $('#reportContent').classList.remove('hidden');

    const urg = d.overall_urgency || 'low';
    const badge = $('#urgencyBadge');
    badge.textContent = { critical: 'CRITICAL', high: 'HIGH', medium: 'MEDIUM', low: 'LOW' }[urg] || urg.toUpperCase();
    badge.className = `urgency-badge urg-${urg}`;

    $('#tab-summary').innerHTML = buildSummary(d);
    $('#tab-symptoms').innerHTML = buildSymptoms(d.symptoms || []);
    $('#tab-timeline').innerHTML = buildTimeline(d.timeline || []);
    $('#tab-findings').innerHTML = buildFindings(d.report_findings || []);
    $('#tab-missing').innerHTML = buildMissing(d.missing_information || []);
    $('#tab-questions').innerHTML = buildQuestions(d.follow_up_questions || []);
    $('#tab-urgency').innerHTML = buildUrgency(d.urgency_signals || []);

    switchTab('summary');
  }

  function buildSummary(d) {
    return `
      <div class="r-section">
        <div class="r-meta">
          ${d.language_detected ? `<span class="chip">Language: ${d.language_detected === 'hi' ? 'Hindi' : d.language_detected === 'en' ? 'English' : d.language_detected}</span>` : ''}
          ${d.patient_info_extracted?.age ? `<span class="chip">Age: ${d.patient_info_extracted.age}</span>` : ''}
          ${d.patient_info_extracted?.gender ? `<span class="chip">Gender: ${d.patient_info_extracted.gender}</span>` : ''}
          ${d.information_completeness ? `<span class="chip">Completeness: ${d.information_completeness}</span>` : ''}
        </div>
      </div>
      <div class="r-section chief">
        <h4>Chief Complaint</h4>
        <p>${esc(d.chief_complaint || '-')}</p>
      </div>
      <div class="r-section">
        <h4>Summary (English)</h4>
        <p>${esc(d.summary_en || '-')}</p>
      </div>
      <div class="r-disclaimer">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
        <span>${esc(d.disclaimer || 'AI-generated triage support only. Not a medical diagnosis.')}</span>
      </div>`;
  }

  function buildSymptoms(symptoms) {
    if (!symptoms.length) return '<p class="empty-tab">No symptoms extracted.</p>';
    return symptoms.map(s => `
      <div class="symptom-card">
        <div class="sym-top">
          <span class="sym-name">${esc(s.symptom)}</span>
          <span class="sev sev-${s.severity || 'unknown'}">${s.severity || 'unknown'}</span>
        </div>
        <div class="sym-detail">
          ${s.duration ? `<span>Duration: ${esc(s.duration)}</span>` : ''}
          ${s.details ? `<span>${esc(s.details)}</span>` : ''}
        </div>
      </div>`).join('');
  }

  function buildTimeline(tl) {
    if (!tl.length) return '<p class="empty-tab">No timeline events.</p>';
    return `<div class="tl">${tl.map(t => `
      <div class="tl-item">
        <div class="tl-dot"></div>
        <div class="tl-line"></div>
        <div class="tl-body">
          <span class="tl-time">${esc(t.timepoint || '?')}</span>
          <p>${esc(t.event)}</p>
          ${t.clinical_relevance ? `<p class="tl-rel">${esc(t.clinical_relevance)}</p>` : ''}
        </div>
      </div>`).join('')}</div>`;
  }

  function buildFindings(findings) {
    if (!findings.length) {
      return '<p class="empty-tab ok">No abnormal laboratory findings or uploaded medical reports yet.</p>';
    }
    return findings.map(f => `
      <div class="finding-card">
        <div class="finding-top">
          <span class="finding-name">${esc(f.test_name)}</span>
          <div>
            <span class="finding-val">${esc(f.value)} ${esc(f.unit || '')}</span>
            ${f.flag ? `<span class="finding-flag flag-${f.flag}">${f.flag}</span>` : ''}
          </div>
        </div>
        ${f.reference_range ? `<p class="tl-rel">Ref: ${esc(f.reference_range)}</p>` : ''}
        ${f.interpretation ? `<p class="finding-interp">${esc(f.interpretation)}</p>` : ''}
      </div>`).join('');
  }

  function buildMissing(items) {
    if (!items.length) return '<p class="empty-tab ok">All essential info appears captured.</p>';
    return `<ul class="miss-list">${items.map(i => `
      <li>
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        <span>${esc(typeof i === 'string' ? i : JSON.stringify(i))}</span>
      </li>`).join('')}</ul>`;
  }

  function buildQuestions(qs) {
    if (!qs.length) return '<p class="empty-tab">No follow-up questions.</p>';
    return qs.map((q, i) => {
      const qe = typeof q === 'string' ? q : (q.question_en || q);
      const qh = typeof q === 'object' ? q.question_hi : null;
      const reason = typeof q === 'object' ? q.reason : null;
      return `
        <div class="q-card">
          <span class="q-num">Q${i + 1}</span>
          <div>
            <p class="q-text">${esc(qe)}</p>
            ${qh ? `<p class="hi">${esc(qh)}</p>` : ''}
            ${reason ? `<p class="q-reason">${esc(reason)}</p>` : ''}
          </div>
        </div>`;
    }).join('');
  }

  function buildUrgency(signals) {
    if (!signals.length) return '<p class="empty-tab ok">No urgency signals detected.</p>';
    return signals.map(s => `
      <div class="sig-card sig-${s.level || 'low'}">
        <div class="sig-top">
          <span class="sig-level sl-${s.level || 'low'}">${(s.level || 'LOW').toUpperCase()}</span>
          <span class="sig-name">${esc(s.signal)}</span>
        </div>
        ${s.reasoning ? `<p class="sig-reason">${esc(s.reasoning)}</p>` : ''}
        ${s.recommended_action ? `<p class="sig-action"><strong>Action:</strong> ${esc(s.recommended_action)}</p>` : ''}
      </div>`).join('');
  }

  /* ─── Tabs ──────────────────────────────────────────── */

  function switchTab(id) {
    $$('.tab').forEach(t => t.classList.toggle('active', t.dataset.tab === id));
    $$('.tab-body').forEach(b => b.classList.toggle('hidden', b.id !== `tab-${id}`));
  }

  /* ─── Referral ──────────────────────────────────────── */

  async function genReferral() {
    if (!currentReport) { toast('Generate report first.'); return; }
    $('#referralBtn').disabled = true;
    try {
      const note = await triageAI.generateReferralNote(currentReport);
      $('#referralText').textContent = note;
      $('#referralModal').classList.remove('hidden');
    } catch (e) { toast('Referral failed: ' + e.message); }
    $('#referralBtn').disabled = false;
  }

  /* ─── Utilities ─────────────────────────────────────── */

  function autoResizeTextarea() {
    const ta = $('#chatInput');
    if (!ta) return;
    ta.style.height = 'auto';
    ta.style.height = Math.min(ta.scrollHeight, 120) + 'px';
  }

  function formatAIText(text) {
    return escapeHtml(text)
      .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
      .replace(/\n/g, '<br>');
  }

  function escapeHtml(t) {
    if (typeof t !== 'string') return String(t);
    const d = document.createElement('div');
    d.textContent = t;
    return d.innerHTML;
  }
  const esc = escapeHtml;

  function toast(msg) {
    const toastMsg = $('#toastMsg');
    const toastEl = $('#toast');
    if (toastMsg) toastMsg.textContent = msg;
    if (toastEl) toastEl.classList.remove('hidden');
    setTimeout(() => { if (toastEl) toastEl.classList.add('hidden'); }, 5000);
  }

  document.addEventListener('DOMContentLoaded', init);
})();
