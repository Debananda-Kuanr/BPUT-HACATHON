/**
 * MEDGem — AI-only healthcare triage engine
 * Browser-compatible JavaScript using the Google Gemini generateContent API.
 *
 * IMPORTANT:
 * - This engine does not contain scripted patient replies or fabricated clinical data.
 * - If Gemini fails, methods throw an error; the UI should show a Retry option.
 * - For production, call Gemini through a secure backend proxy. Do not expose API keys
 *   in public client-side code.
 * - AI output is decision support only, not a diagnosis or a substitute for clinicians.
 */

class MEDgem {
  constructor(config = {}) {
    const globalConfig =
      typeof MEDGEM_AI_CONFIG !== "undefined" ? MEDGEM_AI_CONFIG : {};

    this.config = { ...globalConfig, ...config };
    this.apiBase =
      this.config.API_BASE ||
      "https://generativelanguage.googleapis.com/v1beta/models";

    this.model = this.config.MODEL || "gemini-2.5-flash";
    this.fallbackModels = Array.isArray(this.config.FALLBACK_MODELS)
      ? this.config.FALLBACK_MODELS
      : ["gemini-2.5-flash-lite"];

    this.temperature = this.config.TEMPERATURE ?? 0.3;
    this.maxTokens = this.config.MAX_TOKENS ?? 500;
    this.reportMaxTokens = this.config.REPORT_MAX_TOKENS ?? 3000;
    this.timeoutMs = this.config.TIMEOUT_MS ?? 15000;

    this.apiKeys = [];
    this.apiKeyIndex = 0;
    this.chatHistory = [];
    this.attachedFiles = [];

    if (this.config.API_KEYS) this.setApiKey(this.config.API_KEYS);
    else if (this.config.API_KEY) this.setApiKey(this.config.API_KEY);
  }

  setApiKey(keyString) {
    this.apiKeys = String(keyString || "")
      .split(/[,;\n\r\s]+/)
      .map((key) => key.trim())
      .filter(Boolean);
    this.apiKeyIndex = 0;
  }

  getActiveKey() {
    return this.apiKeys[this.apiKeyIndex] || "";
  }

  rotateKey() {
    if (this.apiKeys.length > 1) {
      this.apiKeyIndex = (this.apiKeyIndex + 1) % this.apiKeys.length;
    }
  }

  setModel(model) {
    if (!model || typeof model !== "string") {
      throw new Error("A valid Gemini model name is required.");
    }
    this.model = model;
  }

  clearChat() {
    this.chatHistory = [];
    this.attachedFiles = [];
  }

  maskPII(text) {
    if (typeof text !== "string") return text;
    return text
      .replace(/(\+?91[\s-]?)?[6-9]\d{9}\b/g, "[REDACTED_PHONE]")
      .replace(/\b\d{4}[\s-]?\d{4}[\s-]?\d{4}\b/g, "[REDACTED_ID]")
      .replace(/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/g, "[REDACTED_EMAIL]");
  }

  sanitizePatientContext(patientInfo = {}) {
    return {
      trackId: patientInfo.trackId || patientInfo.track_id || "SYN-ANON",
      age: patientInfo.age ?? "",
      gender: patientInfo.gender || patientInfo.sex || ""
    };
  }

  getChatSystemPrompt(patientInfo = {}, patientLanguage = "English") {
    const patient = this.sanitizePatientContext(patientInfo);
    const languages = {
      English: "Reply in clear, natural English.",
      Hindi: "Reply in natural Hindi. Match the patient's use of Devanagari or Hinglish when practical.",
      Hinglish: "Reply in natural conversational Hinglish using Roman script.",
      Odia: "Reply in natural Odia using Odia script."
    };

    return `
You are MEDGem, a compassionate AI healthcare intake assistant for a hospital triage system.
Your role is ONLY to collect the patient's health concerns through conversation and build a
clinical intake record. Nurses and doctors on the system will review the record internally —
you do NOT need to tell the patient to contact anyone or seek any permission. Your job ends
when you have gathered enough information.

LANGUAGE: ${languages[patientLanguage] || languages.English}

ANONYMOUS CONTEXT:
Track ID: ${patient.trackId}
Age: ${patient.age || "Not provided"}
Clinical sex/gender: ${patient.gender || "Not provided"}

CONVERSATION RULES:
- Generate every response from the patient's actual message and relevant conversation history.
- Do not use scripted questions or a fixed question sequence.
- Ask at most ONE focused follow-up question per turn.
- Acknowledge information already provided; never repeat the same question.
- Adapt naturally to any symptom or concern the patient mentions.
- Keep responses concise, warm, and easy to understand.
- Do not invent symptoms, history, lab values, or report findings.
- When discussing an uploaded report, only mention values that are actually visible in it.
- Never ask for the patient's full name, phone number, address, or government ID.
- Do not provide any diagnosis, prescribe medicines, or recommend self-treatment.
- EMERGENCY RULE: If the patient describes severe chest pain, difficulty breathing, loss of
  consciousness, stroke symptoms, or uncontrolled bleeding, immediately and clearly tell them
  to call emergency services (108/112) right now. Do not ask more questions.

AUTO-COMPLETION RULE (CRITICAL — YOU MUST FOLLOW THIS):
- After collecting the chief complaint, symptom duration, severity, and any relevant history
  (usually after 5–8 patient messages, or sooner if the picture is clear), send a warm closing
  message thanking the patient and letting them know their information has been recorded and
  will be reviewed by the medical team shortly.
- At the VERY END of that closing message — after the last sentence — append the exact token:
  [TRIAGE_COMPLETE]
- Do NOT append [TRIAGE_COMPLETE] in any message before the final closing message.
- Do NOT tell the patient they need to "contact a doctor", "take permission", or "see a
  specialist" — the system handles that automatically. Simply thank them and close.
- Example closing (translate to the patient's language as needed):
  "Thank you for sharing your health information. Your case has been recorded and our medical
   team will review it shortly. Please keep your Track ID safe for follow-up.
   [TRIAGE_COMPLETE]"
`;
  }

  async _request(contents, options = {}) {
    const key = this.getActiveKey();
    if (!key) {
      throw new Error("MEDGem API key is missing. Configure it before starting a conversation.");
    }

    const models = [options.model || this.model];
    if (options.allowFallback !== false) {
      for (const model of this.fallbackModels) {
        if (model && !models.includes(model)) models.push(model);
      }
    }

    let lastError = null;

    for (const model of models) {
      const controller = typeof AbortController !== "undefined"
        ? new AbortController()
        : null;
      const timeout = controller
        ? setTimeout(() => controller.abort(), options.timeoutMs || this.timeoutMs)
        : null;

      try {
        const response = await fetch(
          `${this.apiBase}/${encodeURIComponent(model)}:generateContent?key=${encodeURIComponent(this.getActiveKey())}`,
          {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            signal: controller ? controller.signal : undefined,
            body: JSON.stringify({
              contents,
              ...(options.systemInstruction
                ? { systemInstruction: { parts: [{ text: options.systemInstruction }] } }
                : {}),
              generationConfig: {
                temperature: options.temperature ?? this.temperature,
                maxOutputTokens: options.maxOutputTokens ?? this.maxTokens,
                ...(options.responseMimeType
                  ? { responseMimeType: options.responseMimeType }
                  : {})
              }
            })
          }
        );

        const payload = await response.json().catch(() => ({}));
        if (!response.ok) {
          const message = payload?.error?.message || `Gemini request failed (${response.status}).`;
          const error = new Error(message);
          error.status = response.status;

          if (response.status === 429) this.rotateKey();
          if (response.status === 400 || response.status === 401 || response.status === 403) {
            throw error;
          }
          lastError = error;
          continue;
        }

        const text = payload?.candidates?.[0]?.content?.parts
          ?.map((part) => part.text || "")
          .join("")
          .trim();

        if (!text) {
          lastError = new Error("MEDGem received an empty response from the AI service.");
          continue;
        }

        this.model = model;
        return text;
      } catch (error) {
        lastError = error;
        if (error.name === "AbortError") {
          lastError = new Error("MEDGem timed out. Please retry.");
        }
        if ([400, 401, 403].includes(error.status)) throw error;
      } finally {
        if (timeout) clearTimeout(timeout);
      }
    }

    throw lastError || new Error("MEDGem could not connect to the AI service. Please retry.");
  }

  _toGeminiContents(history, currentParts) {
    const contents = [];
    for (const message of history) {
      if (!message || !["user", "model"].includes(message.role)) continue;
      if (!message.parts || !message.parts.length) continue;
      const previous = contents[contents.length - 1];

      // Gemini conversation contents must alternate user/model roles.
      if (previous && previous.role === message.role) {
        previous.parts.push(...message.parts);
      } else {
        contents.push({ role: message.role, parts: [...message.parts] });
      }
    }

    const last = contents[contents.length - 1];
    if (last && last.role === "user") {
      last.parts.push(...currentParts);
    } else {
      contents.push({ role: "user", parts: currentParts });
    }
    return contents;
  }

  async sendChatMessage(
    userMessage,
    patientInfo = {},
    attachedFile = null,
    patientLanguage = "English"
  ) {
    const cleanMessage = this.maskPII(String(userMessage || "").trim());

    if (!cleanMessage && !attachedFile) {
      throw new Error("Enter a message or attach a medical report.");
    }

    const userParts = [];
    if (attachedFile) {
      if (!attachedFile.base64 || !attachedFile.mimeType) {
        throw new Error("The attached file is missing valid file data.");
      }
      userParts.push({
        inline_data: {
          mime_type: attachedFile.mimeType,
          data: attachedFile.base64
        }
      });
      userParts.push({
        text: `Please examine the attached medical document carefully. Describe only values and text that are actually visible, mention any uncertainty, and do not invent missing results. Explain that a qualified clinician must interpret the report.`
      });
    }
    if (cleanMessage) userParts.push({ text: cleanMessage });
    if (!userParts.length) throw new Error("No usable message or attachment was provided.");

    const requestHistory = this.chatHistory.map((item) => ({
      role: item.role,
      parts: [{ text: item.text }]
    }));
    const contents = this._toGeminiContents(requestHistory, userParts);

    const responseText = await this._request(contents, {
      systemInstruction: this.getChatSystemPrompt(patientInfo, patientLanguage),
      maxOutputTokens: attachedFile ? 1200 : this.maxTokens,
      temperature: this.temperature
    });

    // Save the turn only after the AI response succeeds, so failed requests can be retried.
    const userHistoryText = cleanMessage ||
      `[Patient attached a medical document: ${this.maskPII(attachedFile.name || "attachment")}]`;
    this.chatHistory.push({ role: "user", text: userHistoryText });
    this.chatHistory.push({ role: "model", text: responseText });

    if (attachedFile) this.attachedFiles.push(attachedFile);
    return responseText;
  }

  _parseJSON(rawText) {
    const text = String(rawText || "").trim();
    try {
      return JSON.parse(text);
    } catch (_) {
      const match = text.match(/\{[\s\S]*\}/);
      if (!match) throw new Error("MEDGem returned invalid JSON. Please retry report generation.");
      try {
        return JSON.parse(match[0]);
      } catch (_) {
        throw new Error("MEDGem returned malformed JSON. Please retry report generation.");
      }
    }
  }

  async generateTriageReport(patientInfo = {}) {
    if (!this.chatHistory.some((message) => message.role === "user")) {
      throw new Error("There is not enough conversation to generate a triage report.");
    }

    const patient = this.sanitizePatientContext(patientInfo);
    const transcript = this.chatHistory.map((message) => {
      const role = message.role === "user" ? "Patient" : "MEDGem";
      return `${role}: ${this.maskPII(message.text)}`;
    }).join("\n\n");

    const parts = [{
      text: `
Create a structured, reviewer-facing triage summary from this transcript.
ALL text values in the JSON output (including summary_en, chief_complaint, symptoms, timeline, etc.) MUST be translated and written STRICTLY in English, regardless of the transcript language. Do NOT output any other language.
Use only information explicitly present in the conversation or attached report.
Do not infer or invent symptoms, measurements, lab values, medication history, or diagnoses.
Unknown fields must be empty strings, empty arrays, or "unknown" as appropriate.
Urgency is preliminary queue support only; a nurse/doctor must determine actual urgency.
Return valid JSON only, matching this schema:
{
  "language_detected": "English/Hindi/Hinglish/Odia/unknown",
  "chief_complaint": "",
  "patient_info_extracted": {
    "track_id": "",
    "age": "",
    "gender": "",
    "relevant_history": []
  },
  "symptoms": [
    {
      "symptom": "",
      "duration": "",
      "severity": "mild/moderate/severe/critical/unknown",
      "details": ""
    }
  ],
  "timeline": [{"timepoint": "", "event": ""}],
  "report_findings": [
    {
      "test_name": "",
      "value": "",
      "unit": "",
      "reference_range": "",
      "flag": "normal/abnormal/high/low/unknown",
      "interpretation": ""
    }
  ],
  "missing_information": [],
  "follow_up_questions": [{"question": "", "reason": ""}],
  "urgency_signals": [{"signal": "", "level": "critical/high/medium/low/unknown", "reasoning": ""}],
  "overall_urgency": "critical/high/medium/low/unknown",
  "summary_en": "",
  "information_completeness": "unknown",
  "disclaimer": "AI-assisted intake summary only. Not a diagnosis or prescription; qualified healthcare professionals must review."
}

Anonymous patient context:
Track ID: ${patient.trackId}
Age: ${patient.age || "Not provided"}
Gender: ${patient.gender || "Not provided"}

Conversation transcript:
${transcript}
`
    }];

    for (const file of this.attachedFiles) {
      if (file?.base64 && file?.mimeType) {
        parts.push({
          inline_data: { mime_type: file.mimeType, data: file.base64 }
        });
        parts.push({
          text: `Review this attached file as additional evidence. Extract only legible, actually visible information. File name: ${this.maskPII(file.name || "attachment")}.`
        });
      }
    }

    const raw = await this._request([{ role: "user", parts }], {
      systemInstruction:
        "You are MEDGem, an AI assistant preparing a cautious clinical intake summary for human review. Never fabricate information. Return valid JSON only.",
      temperature: 0.1,
      maxOutputTokens: this.reportMaxTokens,
      responseMimeType: "application/json"
    });

    return this._parseJSON(raw);
  }

  async analyzeMedicalReport(fileData, patientContext = {}) {
    if (!fileData?.base64 || !fileData?.mimeType) {
      throw new Error("A valid medical report file is required.");
    }

    const patient = this.sanitizePatientContext(patientContext);
    const prompt = `
You are MEDGem. Analyze this uploaded medical document for a healthcare professional's review.
ALL text values in the JSON output MUST be translated and written STRICTLY in English, regardless of the original document language. Do NOT output any other language.
Patient age: ${patient.age || "Not provided"}
Patient gender: ${patient.gender || "Not provided"}
Reported concern: ${this.maskPII(patientContext.chiefComplaint || patientContext.symptoms || "Not provided")}

Return valid JSON with this structure:
{
  "document_title": "",
  "document_date": "",
  "document_type": "",
  "is_relevant": true,
  "relevance_status": "RELEVANT/IGNORED/UNCERTAIN",
  "relevance_reason": "",
  "key_findings": [
    {"test_name": "", "value": "", "unit": "", "reference_range": "", "flag": "NORMAL/HIGH/LOW/ABNORMAL/UNKNOWN", "interpretation": ""}
  ],
  "doctor_impression": "",
  "summary_text": "",
  "limitations": []
}

Rules:
- Read only text and values actually visible in the document.
- Never infer a date from the current date or a filename.
- If a value, date, unit, or reference range is unreadable, say so.
- Do not invent results or claim that a document is relevant if evidence is insufficient.
- Do not diagnose or prescribe. The final interpretation belongs to a qualified clinician.
`;
    const raw = await this._request([{
      role: "user",
      parts: [
        { inline_data: { mime_type: fileData.mimeType, data: fileData.base64 } },
        { text: prompt }
      ]
    }], {
      systemInstruction: "You are MEDGem, a careful medical-document analysis assistant. Never fabricate findings.",
      temperature: 0.1,
      maxOutputTokens: 1800,
      responseMimeType: "application/json"
    });

    const result = this._parseJSON(raw);
    this.attachedFiles.push(fileData);
    return result;
  }

  async generateReferralNote(triageData) {
    if (!triageData || typeof triageData !== "object") {
      throw new Error("Valid triage data is required to generate a referral note.");
    }

    return this._request([{
      role: "user",
      parts: [{
        text: `
Create a concise professional referral summary for a qualified clinician using the supplied triage data.
Do not add facts that are not present. Clearly mark missing information and preserve uncertainty.
Do not diagnose or prescribe. Include a disclaimer that this is AI-assisted and requires clinician review.

Triage data:
${JSON.stringify(triageData, null, 2)}
`
      }]
    }], {
      systemInstruction: "You are MEDGem. Draft accurate clinical handoff notes without inventing facts.",
      temperature: 0.1,
      maxOutputTokens: 1800
    });
  }

  async validateApiKey() {
    if (!this.getActiveKey()) {
      return { valid: false, model: null, error: "Enter a Gemini API key." };
    }

    try {
      const response = await this._request([{
        role: "user",
        parts: [{ text: "Reply with OK." }]
      }], {
        maxOutputTokens: 10,
        temperature: 0,
        allowFallback: false,
        timeoutMs: Math.min(this.timeoutMs, 8000)
      });

      return { valid: true, model: this.model, error: null, response };
    } catch (error) {
      return { valid: false, model: null, error: error.message };
    }
  }
}

const triageAI = new MEDgem();

if (typeof window !== "undefined") {
  window.MEDGem = triageAI;
  window.triageAI = triageAI; // Backward compatibility with existing UI code.
}

if (typeof module !== "undefined" && module.exports) {
  module.exports = { MEDgem, triageAI };
}
