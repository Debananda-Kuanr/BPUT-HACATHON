<div align="center">

# 🏥 MEDGem
### AI-Driven Clinical Triage & Multimodal Patient Intake System

## MEDGem — Tech Stack Badges

[![PHP 8.x](https://img.shields.io/badge/PHP-8.x-777BB4?style=for-the-badge&logo=php&logoColor=white)](https://www.php.net/)
[![MySQL](https://img.shields.io/badge/MySQL-Database-4479A1?style=for-the-badge&logo=mysql&logoColor=white)](https://www.mysql.com/)
[![HTML5](https://img.shields.io/badge/HTML5-E34F26?style=for-the-badge&logo=html5&logoColor=white)](https://developer.mozilla.org/en-US/docs/Web/HTML)
[![CSS3](https://img.shields.io/badge/CSS3-1572B6?style=for-the-badge&logo=css3&logoColor=white)](https://developer.mozilla.org/en-US/docs/Web/CSS)
[![JavaScript](https://img.shields.io/badge/JavaScript-F7DF1E?style=for-the-badge&logo=javascript&logoColor=black)](https://developer.mozilla.org/en-US/docs/Web/JavaScript)
[![Tailwind CSS](https://img.shields.io/badge/Tailwind_CSS-06B6D4?style=for-the-badge&logo=tailwindcss&logoColor=white)](https://tailwindcss.com/)
[![Gemini AI](https://img.shields.io/badge/Gemini_AI-8E75B2?style=for-the-badge&logo=googlegemini&logoColor=white)](https://ai.google.dev/)
[![OCR](https://img.shields.io/badge/OCR-Text_Recognition-FF6F00?style=for-the-badge&logo=googlelens&logoColor=white)](https://en.wikipedia.org/wiki/Optical_character_recognition)
[![XAMPP](https://img.shields.io/badge/XAMPP-Local_Server-FB7A24?style=for-the-badge&logo=apachefriends&logoColor=white)](https://www.apachefriends.org/)
[![Project](https://img.shields.io/badge/Project-MEDGem-0F766E?style=for-the-badge)](https://github.com/Debananda-Kuanr/BPUT-HACATHON)
[![Status](https://img.shields.io/badge/Status-In_Development-FFA500?style=for-the-badge)](https://github.com/Debananda-Kuanr/BPUT-HACATHON)



<img width="1520" height="728" alt="image" src="https://github.com/user-attachments/assets/5e873cbc-bce3-4c1a-af04-ab5fb0685188" />


**MEDGem** is a state-of-the-art, production-ready intelligent triage platform engineered to streamline emergency room and outpatient clinic workflows. By bridging the gap between advanced Large Language Models (LLMs) and frontline healthcare workers, MEDGem automates patient intake, intelligently analyzes symptoms, and categorizes cases by medical urgency.

[Explore the Features](#-key-features) • [View Architecture](#-system-architecture) • [Installation Guide](#-installation--setup)

</div>

---

## 🌟 The Vision

Hospital emergency rooms are often overwhelmed, leading to delayed triage and patient fatigue. MEDGem solves this by offering an intuitive, multimodal patient interface (voice and text) that instantly processes symptoms, extracts vital clinical signals, and securely forwards an organized, prioritized brief to the nursing station. 

**Safety First:** The AI acts *strictly* as an analytical sorting mechanism to empower human medical staff. **Final medical urgency and clinical decisions are always determined by a human healthcare professional.**

---

## 🚀 Key Features

### 🗣️ Multimodal & Accessible Patient Intake
- **Voice-to-Text with Auto-Mic**: Patients can speak their symptoms naturally. The system features automatic microphone activation for seamless, hands-free conversational flow.
- **Text Interface**: Traditional chat interface for quiet environments or detailed text entry.
- **Multilingual Support**: Breaks down language barriers by conversing natively in English, Hindi, Odia, and other regional languages. 

### 🧠 Intelligent AI Triage (Zero-PII)
- **Clinical Summarization**: LLMs generate structured, reviewer-facing clinical summaries from raw conversation transcripts.
- **Smart Urgency Signals**: Highlights critical symptoms and generates warning signals (Red/Yellow/Green) to prioritize the Nurse's queue efficiently.
- **Privacy First**: Personally Identifiable Information (PII) is masked or stripped before reaching the AI engine. The LLM only processes anonymized Track IDs and clinical notes.

### 📄 Automated Report Analysis (OCR)
- **Smart Uploads**: Patients can upload lab reports (PDF/Images) directly during intake.
- **Data Extraction**: The system extracts key findings, flags abnormal values, and seamlessly incorporates them into the triage summary for the physician's review.

### 👨‍⚕️ Multi-Tier Glassmorphic Dashboards
- **Nurse Portal**: A comprehensive view of the waiting room, preliminary urgency scores, AI summaries, and the ability to record vitals and escalate cases.
- **Doctor Portal**: Deep-dive clinical reviews, timeline reconstructions, and one-click referral or discharge management.
- **Premium UI/UX**: Built with a modern glassmorphic aesthetic, ensuring a clean, responsive, mobile-first experience that looks and feels like a top-tier SaaS product.

---

## 📸 Interface Gallery

<div align="center">

| Patient Chat Interface | Nurse Triage Dashboard | Doctor Review Panel |
| :---: | :---: | :---: |
| <img width="358" height="634" alt="image" src="https://github.com/user-attachments/assets/c1630520-f559-4192-b986-6565dc5673b0" />| <img width="357" height="634" alt="image" src="https://github.com/user-attachments/assets/a2f89806-a598-4cf0-ad8e-5a9358d2c537" /> | <img width="358" height="636" alt="image" src="https://github.com/user-attachments/assets/54af05c5-197f-434d-9abe-75d5b79a736b" />|

</div>

---

## 🔄 System Architecture

<div align="center">

<img width="1024" height="768" alt="MEDGem healthcare triage flowchart" src="https://github.com/user-attachments/assets/15f8db5d-5303-4746-99b4-8d070a002426" />


</div>

### 🌊 The Workflow:
1. **Intake Phase**: The patient interacts with the AI via voice/text or uploads medical reports.
2. **Processing Phase**: Data is anonymized. The AI Engine analyzes symptoms and performs OCR on documents.
3. **Triage Generation**: The AI generates an English-only clinical summary, extracts vital signals, and assigns a preliminary urgency score.
4. **Nurse Assessment**: The Nurse reviews the AI summary, checks physical vitals, and makes an authoritative decision (Escalate, Review, Discharge).
5. **Doctor Intervention**: For escalated cases, the Doctor reviews the complete clinical picture, prescribes treatment, or refers the patient.

---

## 🛠️ Technology Stack

| Category | Technologies Used |
| :--- | :--- |
| **Frontend** | HTML5, Vanilla CSS (Glassmorphism), JavaScript (Web Speech API) |
| **Backend** | PHP 8.x |
| **Database** | MySQL (Relational schema for cases, triage, urgency signals) |
| **AI Integration** | Gemini API / MedGemma |
| **Design Pattern** | Mobile-First, Responsive, Multi-Tier Architecture |

---

## ⚙️ Installation & Setup

Set up MEDGem on your local development environment quickly.

### Prerequisites
- [XAMPP](https://www.apachefriends.org/index.html) (or any LAMP/WAMP stack with PHP 8.0+)
- MySQL Server

### 1. Clone the repository
```bash
git clone https://github.com/Debananda-Kuanr/BPUT-HACATHON.git
cd BPUT-HACATHON
```

### 2. Move to Server Directory
Move the cloned `BPUT-HACATHON` project folder into your web server's root directory.
*Example for XAMPP: `c:\xampp\htdocs\BPUT`*

### 3. Database Setup
1. Open phpMyAdmin (or your preferred MySQL client).
2. Create a new database named `medgem`.
3. Open your terminal in the project root directory and run the database seeder to populate realistic test data:
   ```bash
   php database/seed_5_cases.php
   ```

### 4. Configuration
- Update your database credentials in `includes/db.php` if they differ from the defaults (Root / No password).
- Configure your AI API keys securely in your environment or configuration files.

### 5. Run the Application
- Start the **Apache** and **MySQL** modules via the XAMPP Control Panel.
- Access the application in your browser at: `http://localhost/BPUT/` (or your configured local URL).

---

## 🧪 Demo Accounts

The database seeder (`seed_5_cases.php`) automatically creates a pristine environment with 5 distinct test cases and the following demo staff accounts:

**Nurse Portal 🩺**
- **Email:** `nurse@medgem.demo`
- **Password:** `password`

**Doctor Portal ⚕️**
- **Email:** `doctor@medgem.demo`
- **Password:** `password`

### Seeded Patient Test IDs:
Use these Track IDs in the search bar or track page to view specific case flows:
- 🔴 `SYN-1042` - **Urgent** (Chest Pain - Escalated)
- 🟡 `SYN-2085` - **Pending Review** (Dengue Fever)
- 🟡 `SYN-3190` - **Referred** (Lumbar Radiculopathy)
- 🟢 `SYN-4012` - **In Nurse Review** (Asthma Exacerbation)
- 🟢 `SYN-5501` - **Completed** (Gastroenteritis)

---

## MORE PICTURE OF PROJECT

| <img width="358" height="634" alt="image" src="https://github.com/user-attachments/assets/eb36e137-3398-4b4a-b9a2-89df9923849e" />| <img width="356" height="634" alt="image" src="https://github.com/user-attachments/assets/ee05fbe3-537e-45ad-9307-d7bfd5c1bc36" />| <img width="357" height="632" alt="image" src="https://github.com/user-attachments/assets/3c4425dd-ff15-4733-b86f-f5b99769442f" />|
| :---: | :---: | :---: |
| <img width="359" height="638" alt="image" src="https://github.com/user-attachments/assets/33b0e40c-bc66-45f7-8660-f399075dc21e" />| <img width="357" height="634" alt="image" src="https://github.com/user-attachments/assets/d4e437f3-c7b0-4dc6-a9a1-5cdaea7d5cf9" />|<img width="358" height="637" alt="image" src="https://github.com/user-attachments/assets/346cb0f4-d10e-47f4-9a2d-8aa3d7f1fff0" />|
|<img width="357" height="635" alt="image" src="https://github.com/user-attachments/assets/f20cc5ee-e635-4ff5-8997-3d50c0216308" />|<img width="358" height="636" alt="image" src="https://github.com/user-attachments/assets/6889c712-ff98-4421-8351-0fdeaa2e6463" />|<img width="355" height="637" alt="image" src="https://github.com/user-attachments/assets/42648a2e-ca5f-4d22-ba8b-3ac06c1264cd" />|

---

## 📄 License

This project is licensed under the MIT License - see the [LICENSE.md](LICENSE.md) file for details.

---
<div align="center">
<i>Built with ❤️ for a smarter, safer healthcare experience.</i>
</div>
