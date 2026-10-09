<div align="center">

# 🏥 MEDGem
### AI-Driven Clinical Triage & Multimodal Patient Intake System

[![PHP 8.x](https://img.shields.io/badge/PHP-8.x-777BB4?style=for-the-badge&logo=php&logoColor=white)](https://php.net/)
[![MySQL](https://img.shields.io/badge/MySQL-00000F?style=for-the-badge&logo=mysql&logoColor=white)](https://www.mysql.com/)
[![HTML5](https://img.shields.io/badge/HTML5-E34F26?style=for-the-badge&logo=html5&logoColor=white)](https://html.com/)
[![CSS3](https://img.shields.io/badge/CSS3-1572B6?style=for-the-badge&logo=css3&logoColor=white)](https://www.w3.org/Style/CSS/)
[![Gemini AI](https://img.shields.io/badge/Gemini_AI-8E75B2?style=for-the-badge&logo=google&logoColor=white)](https://deepmind.google/technologies/gemini/)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg?style=for-the-badge)](https://opensource.org/licenses/MIT)

![MEDGem Hero Image](path/to/your/hero-image.png)
> *Placeholder: Add a high-resolution banner image of the MEDGem dashboard or landing page here.*

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

| Patient Voice Interface | Nurse Triage Dashboard | Doctor Review Panel |
| :---: | :---: | :---: |
| ![Voice UI](path/to/voice-ui-screenshot.png) | ![Nurse Dashboard](path/to/nurse-dashboard-screenshot.png) | ![Doctor Panel](path/to/doctor-panel-screenshot.png) |

> *Placeholder: Add screenshots of your various interfaces above.*

</div>

---

## 🔄 System Architecture

<div align="center">

![MEDGem Architecture Flow](path/to/architecture-flow-diagram.png)
> *Placeholder: Add your system architecture or user flow diagram here.*

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

## 📄 License

This project is licensed under the MIT License - see the [LICENSE.md](LICENSE.md) file for details.

---
<div align="center">
<i>Built with ❤️ for a smarter, safer healthcare experience.</i>
</div>
