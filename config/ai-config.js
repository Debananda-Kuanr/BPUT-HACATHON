/**
 * MEDGem — AI Configuration File
 * ════════════════════════════════════════════════════════════════
 * This is the SINGLE SOURCE OF TRUTH for all AI settings.
 *
 * All AI-powered pages (Text Chat, Voice Interview, and the
 * standalone Triage AI tool) read their configuration from here.
 *
 * HOW TO SET UP:
 * 1. Get a free Gemini API key from https://aistudio.google.com/apikey
 * 2. Paste the key in the API_KEYS field below
 * 3. Save the file — all pages will pick it up automatically
 *
 * SECURITY NOTE: In production, API keys should be proxied
 * through a backend endpoint. This client-side config is
 * suitable for internal / prototype deployments only.
 * ════════════════════════════════════════════════════════════════
 */

const MEDGEM_AI_CONFIG = {

  /* ─── API Keys ─────────────────────────────────────────────
     Paste your Google Gemini API key(s) below.
     Multiple keys can be separated by commas for auto-rotation
     when rate-limited.

     Example (single key):
       API_KEYS: 'AIzaSyB1_yourKeyGoesHere',

     Example (multiple keys for rotation):
       API_KEYS: 'AIzaSyB1_key1, AIzaSyB2_key2, AIzaSyB3_key3',
     ─────────────────────────────────────────────────────────── */
  API_KEYS: '',

  /* ─── Preferred Model ──────────────────────────────────────
     Endpoint: v1beta
     URL: https://generativelanguage.googleapis.com/v1beta/models/{model}

     CONFIRMED 200 OK models:
       'gemini-flash-lite-latest'    — Blazing fast (<2s), high quota, 200 OK
       'gemini-flash-latest'         — High quality vision, 200 OK
       'gemini-3.5-flash-lite'       — Direct model alias, 200 OK
       'gemini-3.8-flash'            — High demand fallback
     ─────────────────────────────────────────────────────────── */
  MODEL: 'gemini-flash-lite-latest',

  /* ─── Fallback Model Chain ─────────────────────────────────
     Engine races active keys × top models in parallel.
     First successful response wins in ~1-2 seconds.
     ─────────────────────────────────────────────────────────── */
  FALLBACK_MODELS: [
    'gemini-flash-lite-latest',
    'gemini-flash-latest',
    'gemini-1.5-flash',
    'gemini-1.5-flash-latest',
    'gemini-1.5-pro',
    'gemini-2.0-flash'
  ],

  /* ─── Conversation Behavior ────────────────────────────────
     MAX_TURNS: Maximum number of patient responses before the
       AI wraps up the interview. Set 0 for unlimited (AI
       decides when to end via [TRIAGE_COMPLETE] tag).
     TEMPERATURE: Controls creativity (0.0 = deterministic,
       1.0 = creative). Lower is better for clinical intake.
     MAX_TOKENS: Maximum output tokens per AI response.
     ─────────────────────────────────────────────────────────── */
  MAX_TURNS: 0,
  TEMPERATURE: 0.35,
  MAX_TOKENS: 800,

  /* ─── Report Generation ────────────────────────────────────
     REPORT_TEMPERATURE: Lower = more factual report extraction.
     REPORT_MAX_TOKENS: Structured JSON reports can be large.
     ─────────────────────────────────────────────────────────── */
  REPORT_TEMPERATURE: 0.1,
  REPORT_MAX_TOKENS: 4096,

  /* ─── Voice Settings ───────────────────────────────────────
     VOICE_RATE: Speech synthesis speed (0.5 = slow, 1.5 = fast).
     VOICE_PITCH: Speech pitch (0.5 = deep, 1.5 = high).
     AUTO_LISTEN_AFTER_SPEAK: If true, microphone activates
       automatically after AI finishes speaking (voice mode).
     SILENCE_TIMEOUT_MS: Milliseconds of silence before auto-
       sending the voice input (standalone triage tool).
     ─────────────────────────────────────────────────────────── */
  VOICE_RATE: 0.9,
  VOICE_PITCH: 1.0,
  AUTO_LISTEN_AFTER_SPEAK: true,
  SILENCE_TIMEOUT_MS: 800,

  /* ─── Language Mapping ─────────────────────────────────────
     Maps the patient's selected language to the language code
     used in the AI system prompt, and to Web Speech API locale
     codes for voice recognition and synthesis.
     ─────────────────────────────────────────────────────────── */
  LANGUAGE_MAP: {
    English: {
      aiLangCode: 'en',
      aiLangName: 'English',
      speechRecognitionLang: 'en-IN',
      speechSynthesisLang: 'en-US'
    },
    Hindi: {
      aiLangCode: 'hi',
      aiLangName: 'Hindi',
      speechRecognitionLang: 'hi-IN',
      speechSynthesisLang: 'hi-IN'
    },
    Odia: {
      aiLangCode: 'or',
      aiLangName: 'Odia',
      speechRecognitionLang: 'hi-IN',   // Browser fallback (Odia STT limited)
      speechSynthesisLang: 'hi-IN'      // Browser fallback (Odia TTS limited)
    }
  },

  /* ─── Safety ───────────────────────────────────────────────
     Gemini safety thresholds. BLOCK_NONE is required for
     medical content to avoid false blocks on clinical terms.
     Options: BLOCK_NONE, BLOCK_LOW_AND_ABOVE,
              BLOCK_MEDIUM_AND_ABOVE, BLOCK_ONLY_HIGH
     ─────────────────────────────────────────────────────────── */
  SAFETY_THRESHOLD: 'BLOCK_NONE',

  /* ─── Standalone Triage Tool Settings ──────────────────────
     These settings only apply to the standalone /aiModel/ page.

     SHOW_REPORT_PANEL: Show/hide the right-side report panel.
     DEFAULT_LANGUAGE: Default conversation language when
       no patient language is set via session.
     ─────────────────────────────────────────────────────────── */
  SHOW_REPORT_PANEL: true,
  DEFAULT_LANGUAGE: 'English'
};
