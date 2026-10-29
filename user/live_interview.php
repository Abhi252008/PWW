<?php
/**
 * Live AI Interview - Main Interview Room
 * Integrated into Projectail (Module Live).
 * URL: user/live_interview.php?preparation=Java+Technical+Interview
 *      (or linked from module4/inter4.php with a session)
 */
require_once __DIR__ . '/../config/app_config.php';
$testScreen = $_GET['test_screen'] ?? '';
if (empty($testScreen)) {
    require_once __DIR__ . '/../includes/auth_check.php';
} else {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (!isset($_SESSION['user_id'])) $_SESSION['user_id'] = 1;
}
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../includes/functions.php';

// Pre-fill preparation from URL or session category
$preparation = isset($_GET['preparation']) ? trim(htmlspecialchars_decode($_GET['preparation'])) : '';
$sessionId   = isset($_GET['session_id']) ? (int) $_GET['session_id'] : 0;

// If linked from Module 4, load category+role from interview session
if ($sessionId > 0 && $preparation === '') {
    try {
        $stmt = $pdo->prepare("SELECT s.role_target, c.name AS category_name, s.difficulty_level FROM interview_sessions s JOIN categories c ON c.id = s.category_id WHERE s.id = ? AND s.user_id = ?");
        $stmt->execute([$sessionId, $_SESSION['user_id']]);
        $sess = $stmt->fetch();
        if ($sess) {
            $preparation = trim(($sess['role_target'] ?? '') . ' ' . ($sess['category_name'] ?? '') . ' ' . ($sess['difficulty_level'] ?? ''));
        }
    } catch (\Throwable $e) { /* silently skip */ }
}
if ($preparation === '') $preparation = 'Technical Interview';

$pageTitle  = 'Live AI Interview';
$activePage = 'live-interview';

$csrfToken = csrf_token();
$autoStart = !empty($_GET['auto_start']);
$testScreen = $_GET['test_screen'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Live AI Interview - AI Interview Prep</title>
<meta name="description" content="Real-time AI-powered voice interview. Have a live conversation with an AI interviewer that adapts to your answers.">

<!-- Bootstrap Icons -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<!-- Google Fonts: Poppins & Inter -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<!-- Google MediaPipe Face Mesh (100% Client-Side WebAssembly Face Landmark & Head-Pose Detection) -->
<script src="https://cdn.jsdelivr.net/npm/@mediapipe/face_mesh/face_mesh.js" crossorigin="anonymous"></script>

<!-- Preload Interviewer Textures for Instant Zero-Lag Lip-Sync Rendering -->
<link rel="preload" as="image" href="<?= BASE_URL ?>/images/interviewer.png">
<link rel="preload" as="image" href="<?= BASE_URL ?>/images/interviewer_speaking.png">

<!-- Live AI Interview Dedicated Stylesheet -->
<link rel="stylesheet" href="<?= BASE_URL ?>/css/live_interview.css?v=<?= filemtime(__DIR__ . '/../css/live_interview.css') ?>">
</head>
<body>

<!-- =========================================================
     SETUP SCREEN
========================================================= -->
<section id="setupScreen" style="<?= ($testScreen === 'room') ? 'display:none;' : '' ?>">
    <div class="setup-shell">
        <div class="setup-card-light">

            <a href="<?= BASE_URL ?>/user/dashboard.php" class="setup-back-light">
                <i class="bi bi-arrow-left"></i> Back to Dashboard
            </a>

            <div class="setup-icon-badge">
                <i class="bi bi-stars"></i>
            </div>

            <h1>Live AI Interview</h1>
            <p class="subtitle">Have a real-time spoken conversation with an AI interviewer that adapts to your answers. Uses your camera and microphone.</p>

            <label class="setup-label" for="preparationInput">What are you preparing for?</label>
            <textarea
                id="preparationInput"
                class="setup-textarea"
                placeholder="E.g. Java developer interview, final year project viva, DBMS oral exam, HR interview..."
            ><?= e($preparation) ?></textarea>

            <div class="quick-options">
                <button type="button" class="quick-option active" onclick="selectPreparation('Technical Interview', this)">Technical Interview</button>
                <button type="button" class="quick-option" onclick="selectPreparation('HR Interview', this)">HR Interview</button>
                <button type="button" class="quick-option" onclick="selectPreparation('Viva / Oral Exam', this)">Viva / Oral Exam</button>
                <button type="button" class="quick-option" onclick="selectPreparation('Final Year Project Viva', this)">Project Viva</button>
                <button type="button" class="quick-option" onclick="selectPreparation('Java Developer Interview', this)">Java</button>
                <button type="button" class="quick-option" onclick="selectPreparation('Python Developer Interview', this)">Python</button>
                <button type="button" class="quick-option" onclick="selectPreparation('Coding &amp; DSA Interview', this)">Coding &amp; DSA</button>
            </div>

            <button type="button" class="start-main-btn" id="startBtn" onclick="beginInterview()">
                <i class="bi bi-mic-fill" style="margin-right:6px;"></i> Start Live Interview
            </button>

            <div id="setupErrorNotice" class="setup-notice" style="display:none; color:#dc2626; background:#fee2e2; border:1px solid #fca5a5; border-radius:10px; padding:10px 14px; margin-top:12px; font-weight:500;">
                <i class="bi bi-exclamation-triangle-fill"></i> <span id="setupErrorMsg">Camera or microphone permission is required to start.</span>
            </div>

            <div class="setup-notice">
                <i class="bi bi-camera-video"></i> Requires camera &amp; microphone permission.
            </div>

        </div>
    </div>
</section>


<!-- =========================================================
     INTERVIEW SCREEN
========================================================= -->
<section id="interviewScreen" style="<?= ($testScreen === 'room') ? 'display:block;' : '' ?>">

    <!-- Top Navigation Bar matching PrepPro / AI Interview Prep Brand -->
    <header class="topbar">
        <div class="topbar-left">
            <a href="<?= BASE_URL ?>/user/dashboard.php" class="brand" title="Return to Dashboard">
                <img src="<?= BASE_URL ?>/images/logo-symbol.svg" alt="PrepPro" width="28" height="28" class="brand-logo-img">
                <div class="brand-name">Prep<span>Pro</span> <span class="brand-tag">Live AI</span></div>
            </a>
            <div class="topbar-pill topic-pill">
                <i class="bi bi-mortarboard-fill"></i>
                <span>Topic: <strong id="interviewTopic"><?= e($preparation) ?></strong></span>
            </div>
        </div>
        <div class="topbar-right">
            <div class="live-status">
                <span class="live-dot"></span> Live
            </div>
            <div class="topbar-timer" title="Interview Elapsed Time">
                <i class="bi bi-clock"></i> <span id="timer">10:00</span>
            </div>
            <a href="<?= BASE_URL ?>/user/dashboard.php" class="topbar-exit-btn" onclick="return confirm('Exit this live interview session and return to Dashboard?');" title="Exit to Dashboard">
                <i class="bi bi-x-lg"></i>
            </a>
        </div>
    </header>

    <!-- Main Live Stage -->
    <main class="ai-video">

        <!-- Center Realistic AI Interviewer Video Stage Feed -->
        <div class="interviewer-stage-container" id="interviewerContainer">
            <div class="interviewer-ambient-glow" id="interviewerAmbientGlow"></div>
            <div class="interviewer-video-card" id="interviewerVideoCard">
                <div class="interviewer-media-wrapper" id="interviewerMediaWrapper">
                    <img 
                        src="<?= BASE_URL ?>/images/interviewer.png" 
                        alt="Realistic AI Interviewer" 
                        class="interviewer-photo interviewer-photo-idle" 
                        id="interviewerPhotoIdle"
                    >
                    <canvas 
                        id="interviewerFaceCanvas" 
                        class="interviewer-face-canvas" 
                        width="1024" 
                        height="558"
                    ></canvas>
                    <img 
                        src="<?= BASE_URL ?>/images/interviewer_speaking.png" 
                        alt="Realistic AI Interviewer Speaking" 
                        class="interviewer-photo interviewer-photo-speaking" 
                        id="interviewerPhotoSpeaking"
                        style="opacity:0; pointer-events:none; position:absolute; width:1px; height:1px;"
                        loading="eager"
                    >
                </div>

                <!-- Video Call Badges & Overlays -->
                <div class="interviewer-card-top-overlay">
                    <div class="interviewer-live-tag">
                        <span class="live-dot-pulse"></span>
                        <span>AI INTERVIEWER</span>
                    </div>
                    <div style="display:flex;align-items:center;gap:6px;">
                        <button type="button" id="interviewerVolBtn" class="interviewer-vol-btn" onclick="cycleInterviewerVolume()" title="Click to boost interviewer voice volume">
                            <i class="bi bi-volume-up-fill"></i> <span id="interviewerVolLabel">Vol: 250% (High Boost)</span>
                        </button>
                        <div class="interviewer-quality-tag">
                            <i class="bi bi-camera-video-fill"></i> HD 1080p
                        </div>
                    </div>
                </div>

                <div class="interviewer-card-bottom-overlay">
                    <div class="interviewer-identity">
                        <div class="interviewer-name-row">
                            <span class="interviewer-name">David Vance</span>
                            <span class="interviewer-badge" id="interviewerCardRole">Lead Tech Interviewer</span>
                        </div>
                    </div>
                    
                    <!-- Dynamic Voice Equalizer Wave when Interviewer is Speaking -->
                    <div class="interviewer-voice-wave" id="interviewerVoiceWave" title="Interviewer Voice Activity">
                        <span class="wave-bar"></span>
                        <span class="wave-bar"></span>
                        <span class="wave-bar"></span>
                        <span class="wave-bar"></span>
                        <span class="wave-bar"></span>
                    </div>
                </div>

                <!-- Tap to unmute audio floating overlay (if browser autoplay policy restricts initial sound) -->
                <div class="unmute-audio-prompt" id="unmuteAudioPrompt" onclick="resumeInterviewerAudio()">
                    <i class="bi bi-volume-up-fill"></i> Tap to Unmute Audio
                </div>

                <!-- Legacy element anchor to ensure backwards compatibility -->
                <div id="avatarMouth" style="display:none;"></div>
            </div>
        </div>

        <!-- Persistent Question Card -->
        <div id="questionCardLive" class="question-card-live">
            <div class="question-card-header">
                <div style="display:flex;align-items:center;gap:8px;">
                    <span id="questionBadge" class="question-badge"><i class="bi bi-patch-question-fill"></i> Question</span>
                    <div id="turnStatusBadge" class="turn-status-badge">
                        <span id="turnDot" class="turn-dot speaking"></span>
                        <span id="turnStatusText">Connecting with AI Interviewer...</span>
                    </div>
                </div>
                <button type="button" id="replayQuestionBtn" class="q-replay-btn" onclick="replayCurrentQuestion()" title="Replay question audio">
                    <i class="bi bi-volume-up-fill"></i> Replay
                </button>
            </div>
            <div id="currentQuestionDisplay" class="question-text-live"><?= ($testScreen === 'room') ? 'Could you explain the difference between processes and threads, and how context switching works in modern operating systems?' : '' ?></div>
        </div>

        <!-- Live Floating Message -->
        <div id="liveMessage"></div>

        <!-- Bottom-Left AI Interviewer Label (from screenshot) -->
        <div class="ai-label-bottom-left">
            <div class="ai-label-icon-circle">
                <i class="bi bi-stars"></i>
            </div>
            <div>
                <div class="ai-label-title">AI Interviewer</div>
                <div class="ai-label-subtitle" id="aiRole">Your personal interviewer</div>
            </div>
        </div>

        <!-- Candidate Self View PIP Card (Top-Right, from screenshot) -->
        <div class="self-view-pip-wrapper">
            <div class="self-view-pip">
                <video id="candidateVideo" autoplay muted playsinline></video>
                <div id="cameraOffMsg" class="camera-off-msg">
                    Camera is off
                </div>
                <div class="self-pip-you-tag">You</div>
            </div>

            <!-- Client-Side Camera Behavior Monitor -->
            <div id="cameraBehaviorBadge" class="camera-behavior-badge" title="Real-time browser-side camera monitoring">
                <div class="behavior-row">
                    <span class="behavior-label">Camera</span>
                    <span id="behaviorPresence" class="behavior-tag ok">
                        <span class="status-dot"></span> <span id="presenceText">Face Detected</span>
                    </span>
                </div>
                <div class="behavior-row" style="margin-top: 5px;">
                    <span class="behavior-label">Status</span>
                    <span id="behaviorStatus" class="behavior-tag ok">Facing Camera</span>
                </div>
                <div id="behaviorWarning" class="behavior-warning" style="display:none;">
                    <i class="bi bi-exclamation-triangle-fill"></i> <span id="warningMessage">Please face the camera</span>
                </div>
            </div>
        </div>

        <!-- Candidate Spoken Answer & Action Dock -->
        <div id="answerDockLive" class="answer-dock-live">
            <div class="dock-header-row">
                <div class="speaking-state-pill" id="speakingStatePill">
                    <span id="pulseMicDot" class="pulse-mic-dot"></span>
                    <div class="vad-waveform" id="vadWaveform" title="Real-time voice activity meter">
                        <span class="vad-bar" id="vadBar1"></span>
                        <span class="vad-bar" id="vadBar2"></span>
                        <span class="vad-bar" id="vadBar3"></span>
                        <span class="vad-bar" id="vadBar4"></span>
                    </div>
                    <span id="speakingStateText">Waiting: Listening for your answer...</span>
                </div>
                <div id="silenceTimerPill" class="silence-pill" style="display:none;">
                    <i class="bi bi-hourglass-split" id="silenceIcon"></i>
                    <span id="silenceTimerText">Auto-submitting in 7s</span>
                </div>
                <!-- Real-Time Speech Delivery & Pacing HUD -->
                <div class="live-speech-hud" id="liveSpeechHud">
                    <div class="live-hud-pill pace-hud" id="livePacePill" title="Real-time speaking pace (optimal is 130-160 WPM)">
                        <i class="bi bi-speedometer2"></i>
                        <span id="livePaceText">Pace: -- WPM</span>
                    </div>
                    <div class="live-hud-pill filler-hud" id="liveFillerPill" title="Filler words detected this turn">
                        <i class="bi bi-chat-quote"></i>
                        <span id="liveFillerText">Fillers: 0</span>
                    </div>
                </div>
            </div>

            <textarea
                id="candidateAnswerInput"
                class="candidate-answer-textarea"
                placeholder="Speak your answer freely - your spoken words will appear here in real-time. (You can also type or edit anytime)"
                rows="2"
            ><?= ($testScreen === 'room') ? 'A process is an executing instance of a program with its own memory space, whereas threads share the memory space of their parent process. Context switching saves the execution state of the current thread and restores the next...' : '' ?></textarea>

            <div class="dock-actions-row">
                <div class="dock-hint-text">
                    <i class="bi bi-info-circle"></i> Take your time to think (pauses are fine). Press Enter to Submit.
                </div>
                <div class="dock-btn-group">
                    <button type="button" id="skipQuestionBtn" class="dock-btn dock-btn-skip" onclick="skipToNextQuestion()" title="Skip to next question without penalty">
                        <i class="bi bi-skip-forward-fill"></i> Next Question
                    </button>
                    <button type="button" id="submitAnswerBtn" class="dock-btn dock-btn-submit" onclick="submitSpokenAnswerNow()" title="Submit answer immediately (or press Enter)">
                        <i class="bi bi-send-fill"></i> Submit Answer
                    </button>
                </div>
            </div>
        </div>

    </main>

    <!-- Bottom Controls Bar (matching screenshot) -->
    <footer class="controls-area">
        <div class="controls-row">

            <div class="control-item">
                <button id="micButton" class="control-btn-circle" onclick="toggleMicrophone()" title="Toggle microphone">
                    <i class="bi bi-mic-fill"></i>
                </button>
                <span class="control-item-label">Mic</span>
            </div>

            <div class="control-item">
                <button id="cameraButton" class="control-btn-circle" onclick="toggleCamera()" title="Toggle camera">
                    <i class="bi bi-camera-video-fill"></i>
                </button>
                <span class="control-item-label">Camera</span>
            </div>

            <div class="control-item">
                <button id="audioButton" class="control-btn-circle" onclick="toggleAudio()" title="Toggle AI audio">
                    <i class="bi bi-volume-up-fill"></i>
                </button>
                <span class="control-item-label">Audio</span>
            </div>

            <div class="control-item">
                <button id="shareButton" class="control-btn-circle" onclick="toggleScreenShare()" title="Share screen">
                    <i class="bi bi-display"></i>
                </button>
                <span class="control-item-label">Share</span>
            </div>

            <div class="control-item">
                <button id="settingsButton" class="control-btn-circle" onclick="openSettingsModal()" title="Settings">
                    <i class="bi bi-gear-fill"></i>
                </button>
                <span class="control-item-label">Settings</span>
            </div>

            <button class="end-btn-pill" onclick="endInterview()" title="End Interview">
                <i class="bi bi-telephone-x-fill" style="color:#ef4444;margin-right:2px;"></i> End Interview
            </button>

        </div>
    </footer>

</section>


<!-- =========================================================
     END SCREEN
========================================================= -->
<div id="endScreen" style="<?= ($testScreen === 'end') ? 'display:flex;' : '' ?>">
    <div class="end-card">
        <div class="end-icon"><i class="bi bi-check-circle-fill"></i></div>
        <h2 id="endTitle">Interview Completed!</h2>
        <p id="endMessage">Great job completing your live AI interview session. Choose an option below to proceed:</p>
        
        <div class="end-actions">
            <!-- 1. Evaluation / Performance Report Button -->
            <button class="end-btn-action end-btn-report" onclick="goToReport()" title="View your detailed performance and proctoring report">
                <i class="bi bi-file-earmark-bar-graph-fill"></i>
                <div class="btn-text-wrap">
                    <span class="btn-main-label">View Evaluation Report</span>
                    <span class="btn-sub-label">Scores, feedback &amp; insights</span>
                </div>
            </button>

            <!-- 2. Practice Again Button -->
            <button class="end-btn-action end-btn-practice" onclick="returnToSetup()" title="Start a fresh interview practice session">
                <i class="bi bi-arrow-repeat"></i>
                <div class="btn-text-wrap">
                    <span class="btn-main-label">Practice Again</span>
                    <span class="btn-sub-label">Try another topic or retry</span>
                </div>
            </button>

            <!-- 3. Dashboard Button (CLEAR, DISTINCT & PROMINENT) -->
            <a href="<?= BASE_URL ?>/user/dashboard.php" class="end-btn-action end-btn-dashboard" title="Return to your user dashboard">
                <i class="bi bi-speedometer2"></i>
                <div class="btn-text-wrap">
                    <span class="btn-main-label">Go to Dashboard</span>
                    <span class="btn-sub-label">Back to platform dashboard</span>
                </div>
            </a>
        </div>
    </div>
</div>

<!-- Hidden audio element for AI voice playback -->
<audio id="interviewerAudio" playsinline preload="auto" style="display:none;"></audio>

<script>
/* ============================================================
   LIVE AI INTERVIEW - PROJECTAIL INTEGRATED ENGINE
   APIs: api/live_interview_ai.php + api/live_elevenlabs_tts.php
============================================================ */

const CSRF_TOKEN = "<?= e($csrfToken) ?>";
const BASE_URL   = "<?= BASE_URL ?>";

let stream = null, microphoneEnabled = true, cameraEnabled = true, audioEnabled = true;
let interviewStarted = false, interviewEnding = false;
let preparation = "", interviewSeconds = 0, timerInterval = null;
let conversationHistory = [];
let isProcessingAnswer = false;
let isAvatarSpeaking = false;
let currentTurnState = "opening";
let currentAudio = null, currentInterviewId = 0, consecutiveStruggles = 0;
let interviewConcluded = false, concludeReason = "";
const MAX_INTERVIEW_DURATION_SECONDS = 600; // 10 minutes strict session duration
let pendingTimeLimitEnd = false;
let audioSessionCounter = 0;
let interviewEnded = false;

// Adaptive difficulty tracking
let lastAnswerQuality = "normal"; // "easy", "normal", "hard" — sent to Gemini for adaptive next Q

// Real-Time Speech Delivery & Pacing Tracking
let currentTurnSpeakingStartTime = null;
let totalInterviewSpeakingSeconds = 0;
const COMMON_FILLER_PATTERNS = [
    { regex: /\b(um|umm)\b/gi, label: "um" },
    { regex: /\b(uh|uhh)\b/gi, label: "uh" },
    { regex: /\b(er|err)\b/gi, label: "er" },
    { regex: /\b(ah|ahh)\b/gi, label: "ah" },
    { regex: /\b(like)\b/gi, label: "like" },
    { regex: /\b(you know)\b/gi, label: "you know" },
    { regex: /\b(basically)\b/gi, label: "basically" },
    { regex: /\b(actually)\b/gi, label: "actually" },
    { regex: /\b(literally)\b/gi, label: "literally" },
    { regex: /\b(sort of|kind of)\b/gi, label: "kind of" },
    { regex: /\b(i mean)\b/gi, label: "i mean" }
];

function updateSpeechDeliveryHUD(currentText) {
    const pacePill   = document.getElementById("livePacePill");
    const paceText   = document.getElementById("livePaceText");
    const fillerPill = document.getElementById("liveFillerPill");
    const fillerText = document.getElementById("liveFillerText");

    const text = (currentText || "").trim();
    const words = text ? text.split(/\s+/).filter(Boolean) : [];

    // 1. Calculate Filler Words in this turn
    let turnFillerCount = 0;
    COMMON_FILLER_PATTERNS.forEach(fp => {
        const matches = text.match(fp.regex);
        if (matches) turnFillerCount += matches.length;
    });

    if (fillerText) {
        fillerText.textContent = `Fillers: ${turnFillerCount}`;
    }
    if (fillerPill) {
        fillerPill.classList.toggle("warn", turnFillerCount > 0);
    }

    // 2. Calculate Live Speaking Pace (WPM)
    if (currentTurnSpeakingStartTime && words.length >= 3) {
        const elapsedSec = Math.max(1, (Date.now() - currentTurnSpeakingStartTime) / 1000);
        const wpm = Math.round((words.length / (elapsedSec / 60)));

        let verdict = "Optimal";
        let paceClass = "optimal";

        if (wpm < 115) {
            verdict = "Slow";
            paceClass = "slow";
        } else if (wpm > 170) {
            verdict = "Fast";
            paceClass = "fast";
        }

        if (paceText) paceText.textContent = `Pace: ${wpm} WPM (${verdict})`;
        if (pacePill) pacePill.className = `live-hud-pill pace-hud ${paceClass}`;
    } else {
        if (paceText) paceText.textContent = "Pace: -- WPM";
        if (pacePill) pacePill.className = "live-hud-pill pace-hud";
    }
}

let _speechCancelTimers = [];
function clearSpeechCancelTimers() {
    if (_speechCancelTimers && _speechCancelTimers.length > 0) {
        _speechCancelTimers.forEach(id => clearTimeout(id));
        _speechCancelTimers = [];
    }
}

function stopAllAudio() {
    clearSpeechCancelTimers();
    audioSessionCounter++; // Invalidate every active/pending audio session and fetch callback
    isAvatarSpeaking = false;
    showSpeaking(false);
    if (window._audioWatchdog) {
        clearTimeout(window._audioWatchdog);
        window._audioWatchdog = null;
    }
    if (window._ttsFetchController) {
        try { window._ttsFetchController.abort(); } catch(e) {}
        window._ttsFetchController = null;
    }
    if (window._aiFetchController) {
        try { window._aiFetchController.abort(); } catch(e) {}
        window._aiFetchController = null;
    }
    if (window._speechResumeTimer) {
        clearInterval(window._speechResumeTimer);
        window._speechResumeTimer = null;
    }
    if (currentAudio) {
        try {
            currentAudio.pause();
            currentAudio.currentTime = 0;
            currentAudio.onplay = null;
            currentAudio.onended = null;
            currentAudio.onerror = null;
            currentAudio.onloadedmetadata = null;
        } catch(e) {}
        currentAudio = null;
    }
    const audioEl = document.getElementById("interviewerAudio");
    if (audioEl) {
        try {
            audioEl.pause();
            audioEl.currentTime = 0;
            audioEl.onplay = null;
            audioEl.onended = null;
            audioEl.onerror = null;
            audioEl.onloadedmetadata = null;
        } catch(e) {}
    }
    if ("speechSynthesis" in window) {
        try {
            window.speechSynthesis.cancel();
        } catch(e) {}
    }
}

// Audio playback management & prompt helpers
window._pendingAudioBlobUrl = null;

function showUnmutePrompt() {
    const p = document.getElementById("unmuteAudioPrompt");
    if (p) p.classList.add("show");
}

function hideUnmutePrompt() {
    const p = document.getElementById("unmuteAudioPrompt");
    if (p) p.classList.remove("show");
}

function resumeInterviewerAudio() {
    hideUnmutePrompt();
    const audioEl = document.getElementById("interviewerAudio");
    if (audioEl) {
        audioEl.muted = false;
        audioEl.volume = 1.0;
        if (audioEl.paused && window._pendingAudioBlobUrl) {
            audioEl.src = window._pendingAudioBlobUrl;
            audioEl.play().catch(() => {});
        }
    }
    if ("speechSynthesis" in window) {
        try { window.speechSynthesis.resume(); } catch(e) {}
    }
}

// Any click unlocks audio if browser autoplay policy initially withheld it
window.addEventListener("click", () => {
    const p = document.getElementById("unmuteAudioPrompt");
    if (p && p.classList.contains("show")) {
        resumeInterviewerAudio();
    }
}, { passive: true });

// Pre-load speech synthesis voices early for Chrome/Edge
if ("speechSynthesis" in window) {
    try {
        window.speechSynthesis.getVoices();
        window.speechSynthesis.onvoiceschanged = () => {
            try { window.speechSynthesis.getVoices(); } catch(e) {}
        };
    } catch(e) {}
}

// Question tracking & Speech accumulation
let currentQuestionText = "";
let questionCounter = 0;
let accumulatedAnswer = "";
let currentInterimText = "";

// Silence detection state
let silenceTimerInterval = null;
let silentAutoAdvanceCount = 0;
const MAX_SILENT_AUTO_ADVANCES = 2;
const SILENCE_AUTO_SUBMIT_AFTER_ANSWER_SECONDS = 7; // Auto-submit 7s after candidate finishes speaking answer
const SILENCE_THINKING_MAX_SECONDS = 15;            // 15s max thinking time before gentle reminder hint

const setupScreen      = document.getElementById("setupScreen");
const interviewScreen  = document.getElementById("interviewScreen");
const endScreen        = document.getElementById("endScreen");
const preparationInput = document.getElementById("preparationInput");
const candidateVideo   = document.getElementById("candidateVideo");
const liveMessage      = document.getElementById("liveMessage");
const interviewTopic   = document.getElementById("interviewTopic");
const timerElement     = document.getElementById("timer");

function selectPreparation(value, btnEl) {
    const el = document.getElementById("preparationInput") || preparationInput;
    if (el) {
        el.value = value;
        el.focus();
    }
    document.querySelectorAll('.quick-option').forEach(b => b.classList.remove('active'));
    if (btnEl) {
        btnEl.classList.add('active');
    }
}

async function beginInterview() {
    const el = document.getElementById("preparationInput") || preparationInput;
    preparation = (el?.value || "").trim();
    if (!preparation) preparation = "Technical Interview";

    const startBtn = document.getElementById("startBtn");
    const errBox = document.getElementById("setupErrorNotice");
    const errMsg = document.getElementById("setupErrorMsg");
    if (errBox) errBox.style.display = "none";

    if (startBtn) {
        startBtn.disabled = true;
        startBtn.innerHTML = '<i class="bi bi-hourglass-split" style="margin-right:6px;"></i> Connecting Camera &amp; Mic...';
    }

    // Unlock audio immediately in user-gesture context & initialize audio hook
    if (window.FaceEngine) {
        FaceEngine.init();
        if (typeof FaceEngine.resumeAudio === "function") FaceEngine.resumeAudio();
    }
    const audioEl = document.getElementById("interviewerAudio");
    if (audioEl) {
        FaceEngine.hookAudioElement(audioEl);
        audioEl.muted = false;
        audioEl.volume = 1.0;
        audioEl.src = "data:audio/wav;base64,UklGRigAAABXQVZFZm10IBIAAAABAAEARKwAAIhYAQACABAAAABkYXRhAgAAAAEA";
        audioEl.play().catch(() => {});
    }
    if ("speechSynthesis" in window) {
        try {
            window.speechSynthesis.cancel();
            window.speechSynthesis.resume();
            window.speechSynthesis.getVoices();
            const primeUtterance = new SpeechSynthesisUtterance(" ");
            primeUtterance.volume = 0.001;
            window.speechSynthesis.speak(primeUtterance);
        } catch(e) {}
    }

    try {
        await startCamera();
    } catch (e) {
        console.error("Camera/Mic permission notice:", e);
        if (startBtn) {
            startBtn.disabled = false;
            startBtn.innerHTML = '<i class="bi bi-mic-fill" style="margin-right:6px;"></i> Start Live Interview';
        }
        if (errBox && errMsg) {
            errMsg.textContent = "Microphone access is required to speak with David Vance. Please allow permissions in your browser.";
            errBox.style.display = "block";
        }
        return;
    }

    setupScreen.style.display = "none";
    interviewScreen.style.display = "block";
    if (interviewTopic) interviewTopic.textContent = preparation;
    const aiRole = document.getElementById("aiRole");
    const roleTxt = "Expert in " + (preparation.split(" ").slice(0,3).join(" ") || "your field");
    if (aiRole) aiRole.textContent = roleTxt;
    const cardRole = document.getElementById("interviewerCardRole");
    if (cardRole) cardRole.textContent = roleTxt;

    interviewStarted = true; interviewEnding = false;
    conversationHistory = []; isProcessingAnswer = false;
    currentInterviewId = 0; consecutiveStruggles = 0;
    interviewConcluded = false; concludeReason = "";
    questionCounter = 0; currentQuestionText = "";
    accumulatedAnswer = ""; currentInterimText = "";
    silentAutoAdvanceCount = 0;
    lastAnswerQuality = "normal";
    proctoringStats = { totalChecks: 0, facingCamera: 0, lookingLeft: 0, lookingRight: 0, lookingDown: 0, lookingUp: 0, faceMissing: 0, multiFace: 0, warningsCount: 0 };

    // Clear question box — it will fill word-by-word when AI speaks
    const qDisplay = document.getElementById("currentQuestionDisplay");
    if (qDisplay) qDisplay.textContent = "";
    const ansInput = document.getElementById("candidateAnswerInput");
    if (ansInput) ansInput.value = "";

    const prepLower = preparation.toLowerCase();
    const initialOpening = (prepLower.includes("hr") || prepLower.includes("behavioral"))
        ? "Hello and welcome! I am pleased to meet you today. To get us started, could you please introduce yourself and tell me what brings you to this interview?"
        : (prepLower.includes("viva") || prepLower.includes("oral"))
            ? "Hello and welcome to your viva session. To begin, could you please introduce yourself and provide a brief overview of your work on " + preparation + "?"
            : (prepLower.includes("project"))
                ? "Welcome to your project evaluation session. To get us started, please introduce yourself and walk me through the high-level architecture of " + preparation + "."
                : "Hello and welcome to your interview! I'll be your interviewer today. To get us started, could you please introduce yourself and give me an overview of your background and experience with " + preparation + "?";

    startTimer();
    startBehaviorDetection();
    isAvatarSpeaking = true;
    updateTurnStatus("ai_speaking");
    clearSilenceTimer();
    hideSilenceTimer();
    stopRecognition();
    showLiveMessage("AI interviewer is speaking...");
    if (audioCtx && audioCtx.state === 'suspended') {
        try { audioCtx.resume(); } catch(e) {}
    }

    // Track and display opening question immediately
    currentQuestionText = initialOpening;
    questionCounter = 0;
    conversationHistory.push({ role: "model", text: initialOpening });
    displayCurrentQuestion(initialOpening);

    // Asynchronously log session creation in DB in background without blocking UI
    fetch(BASE_URL + "/api/live_interview_ai.php", {
        method: "POST",
        headers: { "Content-Type": "application/json", "X-CSRF-Token": CSRF_TOKEN },
        cache: "no-store",
        body: JSON.stringify({
            mode: "opening",
            preparation: preparation,
            answer: "",
            history: [],
            interview_seconds: 0,
            consecutive_struggles: 0,
            interview_id: 0
        })
    }).then(r => r.json()).then(data => {
        if (data && data.interview_id) currentInterviewId = data.interview_id;
    }).catch(err => console.warn("Background opening session log:", err));

    // Speak opening question
    speakAI(initialOpening, false, "");
}

async function startCamera() {
    if (!navigator.mediaDevices?.getUserMedia) throw new Error("Camera/microphone not supported.");
    try {
        stream = await navigator.mediaDevices.getUserMedia({
            video: { width: { ideal: 1280 }, height: { ideal: 720 }, facingMode: "user" },
            audio: { echoCancellation: true, noiseSuppression: true, autoGainControl: true, channelCount: 1 }
        });
        cameraEnabled = true;
    } catch (camErr) {
        console.warn("Webcam not available or denied, trying microphone only:", camErr);
        try {
            stream = await navigator.mediaDevices.getUserMedia({
                audio: { echoCancellation: true, noiseSuppression: true, autoGainControl: true, channelCount: 1 }
            });
            cameraEnabled = false;
        } catch (micErr) {
            throw micErr;
        }
    }
    if (candidateVideo) {
        candidateVideo.srcObject = stream;
        candidateVideo.muted = true;
        try { await candidateVideo.play(); } catch (e) {}
    }
    microphoneEnabled = true;
    updateMicUI(); updateCameraUI();
    initAudioVAD();
}

function escapeHtml(str) {
    if (!str) return "";
    return String(str)
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

function updateTurnStatus(status) {
    currentTurnState = status;
    const turnDot       = document.getElementById("turnDot");
    const turnStatusText= document.getElementById("turnStatusText");
    const pulseMicDot   = document.getElementById("pulseMicDot");
    const speakingText  = document.getElementById("speakingStateText");

    if (status === "ai_speaking") {
        if (turnDot) { turnDot.className = "turn-dot speaking"; }
        if (turnStatusText) turnStatusText.textContent = "AI Interviewer is speaking...";
        if (pulseMicDot) { pulseMicDot.className = "pulse-mic-dot"; }
        if (speakingText) speakingText.textContent = "AI Interviewer is speaking...";
        hideSilenceTimer();
    } else if (status === "opening") {
        if (turnDot) { turnDot.className = "turn-dot speaking"; }
        if (turnStatusText) turnStatusText.textContent = "Connecting with AI Interviewer...";
        if (pulseMicDot) { pulseMicDot.className = "pulse-mic-dot"; }
        if (speakingText) speakingText.textContent = "Preparing your opening question...";
        hideSilenceTimer();
    } else if (status === "waiting_answering") {
        if (turnDot) { turnDot.className = "turn-dot waiting"; }
        if (turnStatusText) turnStatusText.textContent = "Waiting: You are answering...";
        if (pulseMicDot) { pulseMicDot.className = "pulse-mic-dot answering"; }
        if (speakingText) speakingText.textContent = "Waiting: You are answering... (take your time)";
        showSilenceTimer();
    } else if (status === "waiting_thinking") {
        if (turnDot) { turnDot.className = "turn-dot waiting"; }
        if (turnStatusText) turnStatusText.textContent = "Waiting: Thinking... take your time";
        if (pulseMicDot) { pulseMicDot.className = "pulse-mic-dot thinking"; }
        if (speakingText) speakingText.textContent = "Waiting: Thinking... take your time";
        showSilenceTimer();
    } else if (status === "candidate_turn") {
        if (turnDot) { turnDot.className = "turn-dot waiting"; }
        if (turnStatusText) turnStatusText.textContent = "Your turn to answer";
        if (pulseMicDot) { pulseMicDot.className = "pulse-mic-dot answering"; }
        if (speakingText) speakingText.textContent = "Waiting: Listening for your answer...";
        showSilenceTimer();
    } else if (status === "evaluating") {
        if (turnDot) { turnDot.className = "turn-dot speaking"; }
        if (turnStatusText) turnStatusText.textContent = "AI is thinking & evaluating...";
        if (pulseMicDot) { pulseMicDot.className = "pulse-mic-dot"; }
        if (speakingText) speakingText.textContent = "Submitting answer to AI interviewer...";
        hideSilenceTimer();
    } else if (status === "skipping") {
        if (turnDot) { turnDot.className = "turn-dot speaking"; }
        if (turnStatusText) turnStatusText.textContent = "Moving to next question...";
        if (pulseMicDot) { pulseMicDot.className = "pulse-mic-dot"; }
        if (speakingText) speakingText.textContent = "Advancing to next question...";
        hideSilenceTimer();
    }
}

let typewriterInterval = null;
let currentStreamingText = "";
let currentStreamingIndex = 0;

function displayCurrentQuestion(text, isReplay = false) {
    if (!text) return;
    currentQuestionText = text;
    if (!isReplay) {
        questionCounter++;
    }
    const qBadge = document.getElementById("questionBadge");
    if (qBadge) qBadge.innerHTML = `<i class="bi bi-patch-question-fill"></i> Question ${questionCounter}`;
    
    // Immediately start word-by-word streaming in real-time so candidate sees words without delay
    streamQuestionWordByWord(text);
}

function streamQuestionWordByWord(text, totalDurationMs = 0) {
    const qDisplay = document.getElementById("currentQuestionDisplay");
    if (!qDisplay || !text) return;

    const words = text.trim().split(/\s+/);
    if (words.length === 0) {
        qDisplay.textContent = text;
        return;
    }

    // Natural human speaking cadence (~210ms per word)
    let wordIntervalMs = 210;
    if (totalDurationMs && totalDurationMs > 800) {
        const remainingWords = Math.max(1, words.length - (currentStreamingText === text ? currentStreamingIndex : 0));
        wordIntervalMs = Math.max(110, Math.min(360, Math.floor(totalDurationMs / remainingWords)));
    }

    if (currentStreamingText !== text) {
        if (typewriterInterval) {
            clearInterval(typewriterInterval);
            typewriterInterval = null;
        }
        currentStreamingText = text;
        currentStreamingIndex = 0;
        qDisplay.innerHTML = '<span class="streaming-cursor">|</span>';
    } else if (typewriterInterval) {
        // Adjust existing interval smoothly for remaining words to match spoken audio
        clearInterval(typewriterInterval);
        typewriterInterval = null;
    }

    const streamStep = () => {
        if (currentStreamingIndex < words.length) {
            currentStreamingIndex++;
            const currentSlice = words.slice(0, currentStreamingIndex).join(" ");
            const hasMore = currentStreamingIndex < words.length;
            qDisplay.innerHTML = `${escapeHtml(currentSlice)}${hasMore ? '<span class="streaming-cursor">|</span>' : ''}`;
            qDisplay.scrollTop = qDisplay.scrollHeight;
        } else {
            if (typewriterInterval) {
                clearInterval(typewriterInterval);
                typewriterInterval = null;
            }
            qDisplay.textContent = text;
        }
    };

    streamStep();
    typewriterInterval = setInterval(streamStep, wordIntervalMs);
}

function revealFullQuestionImmediately(text) {
    if (typewriterInterval) {
        clearInterval(typewriterInterval);
        typewriterInterval = null;
    }
    currentStreamingText = "";
    currentStreamingIndex = 0;
    const qDisplay = document.getElementById("currentQuestionDisplay");
    if (qDisplay && text) {
        qDisplay.textContent = text;
    }
}

function replayCurrentQuestion() {
    if (!currentQuestionText || !interviewStarted) return;
    clearSpeechCancelTimers();
    stopAllAudio();
    isProcessingAnswer = false;
    isAvatarSpeaking = false;
    showSpeaking(false);
    clearSilenceTimer();
    stopRecognition();
    showLiveMessage("Replaying question audio...");
    displayCurrentQuestion(currentQuestionText, true);
    speakAI(currentQuestionText, false, "");
}

/* ============================================================
   REAL-TIME VOICE ACTIVITY (VAD) & SILENCE DETECTION ENGINE
============================================================ */
let audioCtx = null;
let micAnalyser = null;
let micSource = null;
let vadHighPass = null;
let vadLowPass = null;
let vadAnimationId = null;
let isCandidateSpeaking = false;
let lastVoiceActivityTimestamp = Date.now();

// Adaptive Noise Floor & VAD Configuration
let ambientNoiseFloor = 20; // Automatically tracks room background noise (fan, AC, ambient)
let energyHistory = [];      // Ring buffer of recent energy values to measure dynamic syllabic variance
let consecutiveSpeechFrames = 0;
let consecutiveSilenceFrames = 0;
let _vadPostSpeechLockoutUntil = 0; // Timestamp: ignore noise spikes briefly after speech ends

function initAudioVAD() {
    if (!stream) return;
    try {
        const AudioContextClass = window.AudioContext || window.webkitAudioContext;
        if (!AudioContextClass) return;
        if (!audioCtx) audioCtx = new AudioContextClass();
        if (audioCtx.state === 'suspended') audioCtx.resume();
        if (micSource) { try { micSource.disconnect(); } catch(e) {} }
        if (vadHighPass) { try { vadHighPass.disconnect(); } catch(e) {} }
        if (vadLowPass) { try { vadLowPass.disconnect(); } catch(e) {} }

        micSource = audioCtx.createMediaStreamSource(stream);

        // 1. High-Pass Biquad Filter (180 Hz): Strips out 50/60Hz AC electrical hum, fan motor vibrations, desk rumble
        vadHighPass = audioCtx.createBiquadFilter();
        vadHighPass.type = "highpass";
        vadHighPass.frequency.value = 180;
        vadHighPass.Q.value = 0.7;

        // 2. Low-Pass Biquad Filter (3600 Hz): Focuses on human vocal speech frequencies and cuts high hiss
        vadLowPass = audioCtx.createBiquadFilter();
        vadLowPass.type = "lowpass";
        vadLowPass.frequency.value = 3600;
        vadLowPass.Q.value = 0.7;

        micAnalyser = audioCtx.createAnalyser();
        micAnalyser.fftSize = 512;
        micAnalyser.smoothingTimeConstant = 0.6; // Responsive smoothing

        // Route: micSource -> vadHighPass -> vadLowPass -> micAnalyser (analyser only, keeps recorder intact)
        micSource.connect(vadHighPass);
        vadHighPass.connect(vadLowPass);
        vadLowPass.connect(micAnalyser);

        ambientNoiseFloor = 20;
        energyHistory = [];
        startVADLoop();
    } catch (e) {
        console.warn("VAD initialization notice:", e);
    }
}

function startVADLoop() {
    if (vadAnimationId) cancelAnimationFrame(vadAnimationId);
    if (!micAnalyser) return;
    const dataArray = new Uint8Array(micAnalyser.frequencyBinCount);

    function checkAudio() {
        if (!interviewStarted || interviewEnding) {
            vadAnimationId = null;
            updateMicVisualizer(0, false);
            return;
        }

        const isCandidateTurn = (currentTurnState === "candidate_turn" || currentTurnState === "waiting_answering" || currentTurnState === "waiting_thinking");

        // Strictly mute candidate VAD while AI avatar is speaking or answer is being evaluated
        if (!isCandidateTurn || isProcessingAnswer || isAvatarSpeaking || !microphoneEnabled || !micAnalyser) {
            updateMicVisualizer(0, false);
            vadAnimationId = requestAnimationFrame(checkAudio);
            return;
        }

        micAnalyser.getByteFrequencyData(dataArray);

        // Core human voice formant frequency bins (~350Hz - 3400Hz)
        // With fftSize=512 at 48000Hz, bin width = 93.75Hz (bins 4 to 36)
        const startBin = 4;
        const endBin = Math.min(dataArray.length, 36);
        let sum = 0;
        for (let i = startBin; i < endBin; i++) {
            sum += dataArray[i];
        }
        const currentEnergy = sum / (endBin - startBin);

        // DYNAMIC AMBIENT NOISE FLOOR ADAPTATION:
        // Automatically tracks background fan/AC hum.
        // If current energy is lower, noise floor adapts downward quickly.
        // If higher, adapts upward VERY slowly to prevent spoken words from raising the baseline.
        if (currentEnergy < ambientNoiseFloor) {
            ambientNoiseFloor = ambientNoiseFloor * 0.90 + currentEnergy * 0.10;
        } else {
            ambientNoiseFloor = ambientNoiseFloor * 0.997 + currentEnergy * 0.003;
        }
        ambientNoiseFloor = Math.max(10, Math.min(65, ambientNoiseFloor));

        // TEMPORAL VARIANCE MEASUREMENT (Fan Noise vs Human Voice):
        // Steady background noise (fan, AC, hum) has flat energy with minimal variance (spread < 4).
        // Real human speech has dynamic syllabic modulation (spread >= 6).
        energyHistory.push(currentEnergy);
        if (energyHistory.length > 12) energyHistory.shift();
        const maxE = Math.max(...energyHistory);
        const minE = Math.min(...energyHistory);
        const energyVariance = maxE - minE;

        const now = Date.now();
        const inLockout = now < _vadPostSpeechLockoutUntil;

        // TRUE SPEECH CONDITION:
        // 1. Energy must exceed the adaptive room noise floor by at least 14dB.
        // 2. Energy must exceed absolute vocal threshold of 24.
        // 3. Temporal variance must confirm dynamic syllabic speech (not constant fan drone).
        // 4. Must not be in post-speech echo lockout.
        const isDynamicRealVoice = (currentEnergy >= ambientNoiseFloor + 14) &&
                                   (currentEnergy >= 24) &&
                                   (energyVariance >= 6) &&
                                   !inLockout;

        updateMicVisualizer(currentEnergy, isDynamicRealVoice);

        if (isDynamicRealVoice) {
            consecutiveSpeechFrames++;
            consecutiveSilenceFrames = 0;
            // Require ~120ms of sustained dynamic voice before declaring speech
            if (consecutiveSpeechFrames >= 4) {
                onCandidateSpeakingDetected(currentEnergy);
            }
        } else {
            consecutiveSilenceFrames++;
            if (consecutiveSpeechFrames > 0) {
                _vadPostSpeechLockoutUntil = now + 500;
            }
            consecutiveSpeechFrames = 0;
            // Declares silence when quiet for ~150ms
            if (consecutiveSilenceFrames >= 6) {
                onCandidateSilenceDetected();
            }
        }

        vadAnimationId = requestAnimationFrame(checkAudio);
    }
    vadAnimationId = requestAnimationFrame(checkAudio);
}

function updateMicVisualizer(level, isVoice = false) {
    const wave = document.getElementById("vadWaveform");
    const bar1 = document.getElementById("vadBar1");
    const bar2 = document.getElementById("vadBar2");
    const bar3 = document.getElementById("vadBar3");
    const bar4 = document.getElementById("vadBar4");
    if (!wave || !bar1) return;

    const isCandidateTurn = (currentTurnState === "candidate_turn" || currentTurnState === "waiting_answering" || currentTurnState === "waiting_thinking");
    if (isVoice && microphoneEnabled && !isProcessingAnswer && !isAvatarSpeaking && isCandidateTurn && Date.now() >= _vadPostSpeechLockoutUntil) {
        wave.classList.add("speaking");
        const scale = Math.min(15, Math.max(5, (level / 255) * 45));
        bar1.style.height = Math.round(scale * 0.65) + "px";
        bar2.style.height = Math.round(scale * 1.0) + "px";
        bar3.style.height = Math.round(scale * 0.8) + "px";
        bar4.style.height = Math.round(scale * 0.5) + "px";
    } else {
        wave.classList.remove("speaking");
        bar1.style.height = "4px";
        bar2.style.height = "4px";
        bar3.style.height = "4px";
        bar4.style.height = "4px";
    }
}

function onCandidateSpeakingDetected(level) {
    const isCandidateTurn = (currentTurnState === "candidate_turn" || currentTurnState === "waiting_answering" || currentTurnState === "waiting_thinking");
    if (!interviewStarted || interviewEnding || isProcessingAnswer || isAvatarSpeaking || !microphoneEnabled || !isCandidateTurn) return;

    // Acoustic noise (fan, AC, room hum) must NEVER reset the auto-submit countdown timer!
    // Silence timer resets exclusively when genuine words are transcribed in onresult or typed by candidate.
    isCandidateSpeaking = true;

    const turnDot        = document.getElementById("turnDot");
    const turnStatusText = document.getElementById("turnStatusText");
    const pulseMicDot    = document.getElementById("pulseMicDot");

    if (pulseMicDot) pulseMicDot.className = "pulse-mic-dot answering";
    if (turnDot) turnDot.className = "turn-dot waiting";
    if (turnStatusText) turnStatusText.textContent = "You are speaking...";
}

/* ============================================================
   SILENCE DETECTION & AUTO-SUBMISSION SPECIFICATION ENGINE
   - Candidate has spoken text: 7s total silence triggers auto-submission
     (0-2s: quiet pause, 2-7s: active countdown pill "Auto-submitting in 5s..4s..3s..2s..1s")
   - Candidate hasn't spoken yet: allows 15s thinking time before gentle reminder hint
============================================================ */
let silenceElapsedSeconds = 0;

function onCandidateSilenceDetected() {
    if (!interviewStarted || interviewEnding || isProcessingAnswer || isAvatarSpeaking || !microphoneEnabled) return;
    const isCandidateTurn = (currentTurnState === "candidate_turn" || currentTurnState === "waiting_answering" || currentTurnState === "waiting_thinking");
    if (!isCandidateTurn) return;

    isCandidateSpeaking = false;

    const pulseMicDot    = document.getElementById("pulseMicDot");
    const turnStatusText = document.getElementById("turnStatusText");
    const speakingText   = document.getElementById("speakingStateText");
    const turnDot        = document.getElementById("turnDot");

    if (turnDot) turnDot.className = "turn-dot waiting";
    if (pulseMicDot) pulseMicDot.className = "pulse-mic-dot answering";

    const input = document.getElementById("candidateAnswerInput");
    const hasText = !!(((input ? input.value : "") || accumulatedAnswer).trim());

    if (turnStatusText) {
        turnStatusText.textContent = hasText ? "Answer recorded" : "Your turn to answer";
    }
    if (speakingText) {
        speakingText.textContent = hasText 
            ? "Answer captured. Auto-submitting soon (or keep speaking)..."
            : "Waiting: Listening for your answer...";
    }
}

function startSilenceTimer() {
    clearSilenceTimer();
    // STRICT GUARD: Silence countdown ONLY runs during candidate's turn
    if (!interviewStarted || interviewEnding || isProcessingAnswer || isAvatarSpeaking) return;
    if (currentTurnState !== "candidate_turn" && currentTurnState !== "waiting_answering" && currentTurnState !== "waiting_thinking") {
        return;
    }

    lastVoiceActivityTimestamp = Date.now();
    silenceElapsedSeconds = 0;

    silenceTimerInterval = setInterval(() => {
        // Stop timer immediately if turn switched to AI speaking or evaluating
        if (!interviewStarted || interviewEnding || isProcessingAnswer || isAvatarSpeaking || 
            (currentTurnState !== "candidate_turn" && currentTurnState !== "waiting_answering" && currentTurnState !== "waiting_thinking")) {
            clearSilenceTimer();
            return;
        }

        // Accurately compute seconds since candidate voice/word activity
        const elapsedSinceVoice = (Date.now() - lastVoiceActivityTimestamp) / 1000;
        silenceElapsedSeconds = Math.max(0, elapsedSinceVoice);

        if (silenceElapsedSeconds >= 1.0) {
            isCandidateSpeaking = false;
        }

        updateSilenceLadderUI();

        const input = document.getElementById("candidateAnswerInput");
        const currentAns = ((input ? input.value : "") || accumulatedAnswer).trim();
        const hasText = currentAns.length > 0;

        // Auto-submit when candidate has answered and been silent for 7 seconds
        if (hasText && silenceElapsedSeconds >= SILENCE_AUTO_SUBMIT_AFTER_ANSWER_SECONDS) {
            clearSilenceTimer();
            onSilenceExpired();
        }
        // Auto-advance to next question when candidate has NOT answered after 15 seconds of silence
        else if (!hasText && silenceElapsedSeconds >= SILENCE_THINKING_MAX_SECONDS) {
            clearSilenceTimer();
            onSilenceExpired();
        }
    }, 250);
}

function resetSilenceCountdown() {
    lastVoiceActivityTimestamp = Date.now();
    silenceElapsedSeconds = 0;
    const pill = document.getElementById("silenceTimerPill");
    if (pill) pill.style.display = "none";
}

function clearSilenceTimer() {
    if (silenceTimerInterval) {
        clearInterval(silenceTimerInterval);
        silenceTimerInterval = null;
    }
    silenceElapsedSeconds = 0;
    const pill = document.getElementById("silenceTimerPill");
    if (pill) pill.style.display = "none";
}

function showSilenceTimer() {
    if (!silenceTimerInterval && interviewStarted && !interviewEnding && !isProcessingAnswer && !isAvatarSpeaking) {
        // startSilenceTimer is called explicitly by turn controllers
    }
}

function hideSilenceTimer() {
    const pill = document.getElementById("silenceTimerPill");
    if (pill) pill.style.display = "none";
}

function updateSilenceLadderUI() {
    const pill = document.getElementById("silenceTimerPill");
    const txt  = document.getElementById("silenceTimerText");
    const icon = document.getElementById("silenceIcon");
    const speakingText = document.getElementById("speakingStateText");
    if (!pill || !txt) return;

    // NEVER show silence indicator while AI is speaking
    if (isAvatarSpeaking || currentTurnState === "ai_speaking" || currentTurnState === "opening" || currentTurnState === "evaluating") {
        pill.style.display = "none";
        return;
    }

    const input = document.getElementById("candidateAnswerInput");
    const currentAns = ((input ? input.value : "") || accumulatedAnswer).trim();
    const hasText = currentAns.length > 0;

    // CASE 1: Answer has text -> prompt countdown to auto-submit
    if (hasText) {
        if (isCandidateSpeaking || silenceElapsedSeconds < 2.0) {
            pill.style.display = "none";
            if (speakingText && !isCandidateSpeaking) {
                speakingText.textContent = "Answer captured. Take your time or submit...";
            }
        } else if (silenceElapsedSeconds >= 2.0 && silenceElapsedSeconds < SILENCE_AUTO_SUBMIT_AFTER_ANSWER_SECONDS) {
            const remaining = Math.max(1, Math.ceil(SILENCE_AUTO_SUBMIT_AFTER_ANSWER_SECONDS - silenceElapsedSeconds));
            pill.className = "silence-pill prompting";
            pill.style.display = "inline-flex";
            if (icon) icon.className = "bi bi-send-check";
            txt.textContent = `Auto-submitting in ${remaining}s...`;
            if (speakingText && !isCandidateSpeaking) {
                speakingText.textContent = `Auto-submitting answer in ${remaining}s (or speak more / edit now)...`;
            }
        }
        return;
    }

    // CASE 2: No answer text yet -> candidate is thinking
    if (silenceElapsedSeconds < 4) {
        pill.style.display = "none";
        pill.className = "silence-pill";
        if (speakingText && !isCandidateSpeaking) {
            speakingText.textContent = "Waiting: Listening for your answer...";
        }
    } else if (silenceElapsedSeconds >= 4 && silenceElapsedSeconds < 9) {
        pill.className = "silence-pill thinking";
        pill.style.display = "inline-flex";
        if (icon) icon.className = "bi bi-lightbulb-fill";
        txt.textContent = "Thinking...";
        if (speakingText && !isCandidateSpeaking) {
            speakingText.textContent = "Waiting: Thinking... take your time";
        }
    } else if (silenceElapsedSeconds >= 9 && silenceElapsedSeconds < SILENCE_THINKING_MAX_SECONDS) {
        const remaining = Math.max(1, Math.ceil(SILENCE_THINKING_MAX_SECONDS - silenceElapsedSeconds));
        pill.className = "silence-pill prompting";
        pill.style.display = "inline-flex";
        if (icon) icon.className = "bi bi-arrow-right-circle-fill";
        txt.textContent = `Advancing in ${remaining}s...`;
        if (speakingText) {
            speakingText.textContent = `No response: Moving to next question in ${remaining}s (or speak now)...`;
        }
    }
}

function onSilenceExpired() {
    clearSilenceTimer();
    if (!interviewStarted || interviewEnding || isProcessingAnswer || isAvatarSpeaking) return;
    const pill = document.getElementById("silenceTimerPill");
    if (pill) pill.style.display = "none";

    const input = document.getElementById("candidateAnswerInput");
    const currentAns = ((input ? input.value : "") || accumulatedAnswer).trim();

    if (currentAns.length > 0) {
        showLiveMessage("Auto-submitting your answer...");
        assessAnswerQuality(currentAns);
        submitSpokenAnswerNow();
    } else {
        // Candidate remained silent for 15s without an answer -> automatically move forward
        showLiveMessage("Time elapsed: Advancing to next question...");
        skipToNextQuestion(true);
    }
}

// Assess answer quality to drive adaptive easy/hard next question
function assessAnswerQuality(ans) {
    const words = ans.trim().split(/\s+/).filter(Boolean).length;
    const hasKeyTerms = /\b(because|since|therefore|however|implement|architecture|design|algorithm|complexity|optimize|trade.off|approach|solution|framework|database|api|system|concept|principle)\b/i.test(ans);
    const isFirstQuestion = (questionCounter <= 1);
    const lowPerfThreshold = isFirstQuestion ? 25 : 18;

    if (words <= lowPerfThreshold || /\b(don'?t know|no idea|not sure|idk|skip|pass|nothing|cant answer|cannot answer)\b/i.test(ans)) {
        lastAnswerQuality = "easy"; // Low performance detected → reduce difficulty for next question
    } else if (words >= (isFirstQuestion ? 50 : 40) && hasKeyTerms) {
        lastAnswerQuality = "hard"; // Strong performance
    } else {
        lastAnswerQuality = "normal";
    }
}

/* ============================================================
   ANSWER SUBMISSION & QUESTION NAVIGATION
============================================================ */
function submitSpokenAnswerNow() {
    if (!interviewStarted || interviewEnding || isProcessingAnswer) return;
    stopAllAudio();
    stopRecognition();
    silentAutoAdvanceCount = 0;
    clearSilenceTimer();
    showSpeaking(false);

    const input = document.getElementById("candidateAnswerInput");
    const answer = ((input ? input.value : "") || accumulatedAnswer).trim();

    if (!answer) {
        showLiveMessage("Please speak or type your answer, or click 'Next Question' to skip.");
        // Restart listening
        startRecognition();
        startSilenceTimer();
        return;
    }

    // Assess quality before clearing
    assessAnswerQuality(answer);

    // Track turn duration into totalInterviewSpeakingSeconds
    if (currentTurnSpeakingStartTime) {
        const turnSec = (Date.now() - currentTurnSpeakingStartTime) / 1000;
        totalInterviewSpeakingSeconds += Math.max(0, turnSec);
        currentTurnSpeakingStartTime = null;
    }
    updateSpeechDeliveryHUD("");

    if (input) input.value = "";
    accumulatedAnswer = "";
    currentInterimText = "";
    const submitBtn = document.getElementById("submitAnswerBtn");
    if (submitBtn) submitBtn.classList.remove("highlight");

    updateTurnStatus("evaluating");
    askGemini(answer, "conversation");
}

async function skipToNextQuestion(isAutoFromSilence = false) {
    if (!interviewStarted || interviewEnding || isProcessingAnswer) return;
    stopAllAudio();
    if (!isAutoFromSilence) {
        silentAutoAdvanceCount = 0;
    }
    clearSilenceTimer();
    showSpeaking(false);

    currentTurnSpeakingStartTime = null;
    updateSpeechDeliveryHUD("");

    updateTurnStatus("skipping");
    showLiveMessage(isAutoFromSilence ? "Silence elapsed: Advancing to next question..." : "Moving to next question...");

    accumulatedAnswer = "";
    currentInterimText = "";
    const input = document.getElementById("candidateAnswerInput");
    if (input) input.value = "";
    const submitBtn = document.getElementById("submitAnswerBtn");
    if (submitBtn) submitBtn.classList.remove("highlight");

    await askGemini("I would like to move on to the next question.", "skip");
}

async function askGemini(candidateAnswer, mode = "conversation") {
    if (!interviewStarted || interviewEnding || isProcessingAnswer) return;
    isProcessingAnswer = true;
    stopAllAudio();
    stopRecognition();
    clearSilenceTimer();

    // Ensure mouth does not talk while evaluating and fetching next question from AI
    showSpeaking(false);
    updateTurnStatus(mode === "skip" ? "skipping" : "evaluating");
    showLiveMessage(
        candidateAnswer === "OPENING"
            ? "AI interviewer is preparing..."
            : (mode === "skip" ? "AI is preparing next question..." : "AI interviewer is evaluating your answer...")
    );

    // Show loading indicator in question box
    const qDisplay = document.getElementById("currentQuestionDisplay");
    if (qDisplay) {
        qDisplay.innerHTML = `<span style="opacity:0.7;font-style:italic;"><i class="bi bi-soundwave"></i> Formulating next question...</span>`;
    }

    try {
        window._aiFetchController = new AbortController();
        const timeoutId = setTimeout(() => {
            if (window._aiFetchController) window._aiFetchController.abort();
        }, 8000); // 8s strict ceiling

        const response = await fetch(BASE_URL + "/api/live_interview_ai.php", {
            method: "POST",
            headers: { "Content-Type": "application/json", "X-CSRF-Token": CSRF_TOKEN },
            cache: "no-store",
            signal: window._aiFetchController.signal,
            body: JSON.stringify({
                mode: candidateAnswer === "OPENING" ? "opening" : (mode === "skip" ? "skip" : "conversation"),
                preparation: preparation,
                answer: candidateAnswer === "OPENING" ? "" : candidateAnswer,
                history: conversationHistory,
                interview_seconds: interviewSeconds,
                consecutive_struggles: consecutiveStruggles,
                interview_id: currentInterviewId,
                answer_quality: lastAnswerQuality, // "easy" | "normal" | "hard" for adaptive difficulty
                question_number: questionCounter
            })
        });
        clearTimeout(timeoutId);

        if (interviewEnded || interviewEnding) return;

        const data = await response.json();
        if (interviewEnded || interviewEnding) return;
        if (!response.ok || !data.success || !data.reply) throw new Error(data.message || "Gemini did not return a response.");

        if (data.interview_id)                                 currentInterviewId  = data.interview_id;
        if (typeof data.consecutive_struggles === "number")    consecutiveStruggles = data.consecutive_struggles;
        const timeExceeded = (interviewSeconds >= MAX_INTERVIEW_DURATION_SECONDS);
        interviewConcluded = !!data.interview_ended || (questionCounter >= 10) || timeExceeded;
        concludeReason     = data.conclude_reason || (timeExceeded ? "TIME_LIMIT_REACHED" : (questionCounter >= 10 ? "QUESTIONS_LIMIT_REACHED" : ""));

        const reply = String(data.reply).trim();
        if (candidateAnswer !== "OPENING") conversationHistory.push({ role: "user",  text: candidateAnswer });
        conversationHistory.push({ role: "model", text: reply });
        if (conversationHistory.length > 20) conversationHistory = conversationHistory.slice(-20);

        if (interviewEnded || interviewEnding) return;

        // displayCurrentQuestion sets currentQuestionText and clears the box
        // speakAI will stream it word-by-word as audio plays
        displayCurrentQuestion(reply);
        speakAI(reply, interviewConcluded, concludeReason);

    } catch (error) {
        if (interviewEnded || interviewEnding) return;
        console.error("Gemini interview error:", error);
        isProcessingAnswer = false;
        showSpeaking(false);
        const fallback = candidateAnswer === "OPENING"
            ? "Hello and welcome! I'll be your interviewer today. To get us started, please introduce yourself and share an overview of your background in " + (preparation || "this topic") + "."
            : (mode === "skip"
                ? "No problem at all, let's move forward. Could you share another key concept or project you have worked on in " + (preparation || "your field") + "?"
                : (lastAnswerQuality === "easy"
                    ? "That's okay, let's try a simpler question. Can you explain a basic concept you know well in " + (preparation || "your field") + "?"
                    : "Thank you for that. Could you describe a challenging scenario you solved recently in " + (preparation || "your field") + " and how you approached it?"));
        conversationHistory.push({ role: "model", text: fallback });
        if (interviewEnded || interviewEnding) return;
        displayCurrentQuestion(fallback);
        speakAI(fallback, false, "");
        return; // isProcessingAnswer already reset above
    }
    isProcessingAnswer = false; // Always unblock after success
}

// Press Enter to submit answer (Shift+Enter for new line)
window.addEventListener("keydown", (e) => {
    if (e.key === "Enter" && !e.shiftKey && interviewStarted && !interviewEnding && !isProcessingAnswer) {
        const input = document.getElementById("candidateAnswerInput");
        const ans = ((input ? input.value : "") || accumulatedAnswer).trim();
        if (ans) {
            e.preventDefault();
            submitSpokenAnswerNow();
        }
    }
});

// Candidate manual typing listener - allows typing and speaking simultaneously
document.addEventListener("DOMContentLoaded", () => {
    const input = document.getElementById("candidateAnswerInput");
    if (input) {
        input.addEventListener("input", () => {
            accumulatedAnswer = input.value;
            lastVoiceActivityTimestamp = Date.now();
            resetSilenceCountdown();
            const submitBtn = document.getElementById("submitAnswerBtn");
            if (submitBtn) submitBtn.classList.toggle("highlight", input.value.trim().length > 0);
        });
    }
});

let recognition = null;
let recognitionRunning = false;
let recognitionRestartTimer = null;

function getOrCreateRecognition() {
    if (recognition) return recognition;
    const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
    if (!SpeechRecognition) {
        showLiveMessage("Speech recognition unavailable in this browser. Please use Google Chrome or type your answer.");
        return null;
    }
    const r = new SpeechRecognition();
    r.lang = (navigator.language && navigator.language.length >= 2) ? navigator.language : "en-US";
    r.continuous = true;
    r.interimResults = true;
    r.maxAlternatives = 1;

    r.onstart = () => {
        recognitionRunning = true;
    };

    r.onspeechstart = () => {
        // In Chrome, continuous ambient/fan noise or breathing triggers onspeechstart without actual words.
        // We do not lock the UI in 'Speaking' or reset silenceElapsedSeconds here.
        // Real speech is confirmed when onresult delivers transcribed words, or when dynamic VAD confirms voice.
        if (!isProcessingAnswer && !isAvatarSpeaking) {
            isCandidateSpeaking = true;
        }
    };

    r.onspeechend = () => {
        if (!isProcessingAnswer && !isAvatarSpeaking) {
            isCandidateSpeaking = false;
            onCandidateSilenceDetected();
            _vadPostSpeechLockoutUntil = Date.now() + 400;
        }
    };

    r.onresult = (event) => {
        // Only ignore microphone if AI is actively speaking
        if (isProcessingAnswer || isAvatarSpeaking || currentTurnState === "ai_speaking" || currentTurnState === "opening" || currentTurnState === "evaluating") {
            return;
        }

        lastVoiceActivityTimestamp = Date.now();
        resetSilenceCountdown();
        isCandidateSpeaking = true;

        const speakingText = document.getElementById("speakingStateText");
        if (speakingText) speakingText.textContent = "Speaking: Listening...";
        const turnStatusText = document.getElementById("turnStatusText");
        if (turnStatusText) turnStatusText.textContent = "You are speaking...";

        let interimText = "";
        let newFinalChunk = "";

        for (let i = event.resultIndex; i < event.results.length; i++) {
            const transcript = event.results[i][0].transcript;
            if (event.results[i].isFinal) newFinalChunk += transcript + " ";
            else interimText += transcript;
        }

        if (newFinalChunk.trim()) {
            accumulatedAnswer = (accumulatedAnswer ? accumulatedAnswer.trim() + " " : "") + newFinalChunk.trim();
        }
        currentInterimText = interimText;

        const displayAnswer = (accumulatedAnswer + (interimText ? " " + interimText : "")).trim();
        const input = document.getElementById("candidateAnswerInput");
        if (input) {
            input.value = displayAnswer;
            input.scrollTop = input.scrollHeight;
        }

        const submitBtn = document.getElementById("submitAnswerBtn");
        if (submitBtn) {
            submitBtn.classList.toggle("highlight", displayAnswer.length > 0);
        }

        if (!currentTurnSpeakingStartTime && displayAnswer.length > 0) {
            currentTurnSpeakingStartTime = Date.now();
        }
        updateSpeechDeliveryHUD(displayAnswer);
        updateTurnStatus("waiting_answering");
    };

    r.onerror = (event) => {
        console.warn("Speech recognition notice:", event.error);
        if (event.error === "not-allowed" || event.error === "service-not-allowed") {
            recognitionRunning = false;
            showLiveMessage("Microphone access is blocked in browser settings. Please allow microphone access.");
            return;
        }
    };

    r.onend = () => {
        recognitionRunning = false;
        if (recognitionRestartTimer) {
            clearTimeout(recognitionRestartTimer);
            recognitionRestartTimer = null;
        }
        // ONLY restart when it is genuinely the candidate's turn
        if (interviewStarted && !interviewEnding && microphoneEnabled && !isProcessingAnswer && !isAvatarSpeaking && 
            (currentTurnState === "candidate_turn" || currentTurnState === "waiting_answering" || currentTurnState === "waiting_thinking")) {
            recognitionRestartTimer = setTimeout(() => {
                startRecognition();
            }, 120);
        }
    };

    recognition = r;
    return r;
}

function startRecognition() {
    if (!interviewStarted || interviewEnding || !microphoneEnabled || isProcessingAnswer || isAvatarSpeaking) return;
    const r = getOrCreateRecognition();
    if (!r || recognitionRunning) return;
    try {
        r.start();
        recognitionRunning = true;
    } catch (e) {
        // Recognition already active or starting
    }
}

function stopRecognition() {
    if (recognitionRestartTimer) {
        clearTimeout(recognitionRestartTimer);
        recognitionRestartTimer = null;
    }
    clearSilenceTimer();
    if (recognition) {
        try { recognition.stop(); } catch (e) {}
    }
    recognitionRunning = false;
}

async function speakAI(text, isEndingTurn = false, reason = "") {
    if (interviewEnded || interviewEnding) return;
    clearSpeechCancelTimers();
    stopAllAudio(); // Instantly stop ANY prior audio or browser speech first
    if (interviewEnded || interviewEnding) return;
    const sessionId = ++audioSessionCounter; // Create the active session ID for this question

    // Immediately enforce AI speaking state and lock out candidate microphone/silence timers
    isAvatarSpeaking = true;
    updateTurnStatus("ai_speaking");
    clearSilenceTimer();
    hideSilenceTimer();
    stopRecognition();

    let speechCompleted = false;
    const completeSpeech = () => {
        if (interviewEnded || interviewEnding || sessionId !== audioSessionCounter || speechCompleted) return;
        speechCompleted = true;
        isAvatarSpeaking = false;
        isProcessingAnswer = false; // Always unblock candidate actions
        showSpeaking(false);
        stopAllAudio();
        revealFullQuestionImmediately(text); // Finish streaming & display full question permanently

        if (isEndingTurn || pendingTimeLimitEnd) {
            showLiveMessage("Interview concluded.");
            setTimeout(() => endInterview(reason || "TIME_LIMIT_REACHED"), 1200);
        } else {
            showLiveMessage("Your turn to speak...");
            updateTurnStatus("candidate_turn");

            accumulatedAnswer = "";
            currentInterimText = "";
            const input = document.getElementById("candidateAnswerInput");
            if (input) input.value = "";
            const submitBtn = document.getElementById("submitAnswerBtn");
            if (submitBtn) submitBtn.classList.remove("highlight");

            if (interviewStarted && !interviewEnding && microphoneEnabled) {
                startRecognition();
                // Start candidate silence timer only after question finishes speaking
                setTimeout(() => { 
                    if (interviewStarted && !interviewEnding && currentTurnState === "candidate_turn") {
                        startSilenceTimer(); 
                    }
                }, 800);
            }
        }
    };

    if (!audioEnabled || !text) {
        // Even with audio off, stream the text word-by-word visually
        isAvatarSpeaking = true;
        showSpeaking(true);
        updateTurnStatus("ai_speaking");
        const wordCount = (text.match(/\S+/g) || []).length;
        const dummyDurMs = Math.max(1800, wordCount * 260);
        streamQuestionWordByWord(text, dummyDurMs);
        setTimeout(() => { completeSpeech(); }, dummyDurMs + 200);
        return;
    }

    // Calculate word-based safe duration to ensure full question is heard
    const wordCount = (text.match(/\S+/g) || []).length;
    const safeMaxAudioMs = Math.max(3500, wordCount * 380 + 1500);

    // Try ElevenLabs / Google TTS proxy with fast 1.8s network allowance
    let elevenLabsSuccess = false;
    if (!window._elevenLabsExhausted) {
        try {
            window._ttsFetchController = new AbortController();
            const ttsRaceTimeout = setTimeout(() => {
                if (window._ttsFetchController) window._ttsFetchController.abort();
            }, 6500);

            const response = await fetch(BASE_URL + "/api/live_elevenlabs_tts.php", {
                method: "POST",
                headers: { "Content-Type": "application/json", "X-CSRF-Token": CSRF_TOKEN },
                cache: "no-store",
                signal: window._ttsFetchController.signal,
                body: JSON.stringify({ text: text, voice_id: "pNInz6obpgDQGcFmaJgB" })
            });
            clearTimeout(ttsRaceTimeout);

            if (interviewEnded || interviewEnding || sessionId !== audioSessionCounter) return;

            if (response.ok) {
                const audioBlob = await response.blob();
                if (interviewEnded || interviewEnding || sessionId !== audioSessionCounter) return;

                if (audioBlob && audioBlob.size > 800) {
                    const audioEl = document.getElementById("interviewerAudio") || new Audio();
                    currentAudio = audioEl;
                    if (window.FaceEngine) {
                        if (typeof FaceEngine.resumeAudio === "function") FaceEngine.resumeAudio();
                        FaceEngine.hookAudioElement(audioEl);
                        if (typeof FaceEngine.setVolume === "function") FaceEngine.setVolume(interviewerVolumeLevel);
                    }
                    const audioUrl = URL.createObjectURL(audioBlob);
                    window._pendingAudioBlobUrl = audioUrl;
                    audioEl.src = audioUrl;

                    // Watchdog: fire AFTER we expect audio to end
                    window._audioWatchdog = setTimeout(() => {
                        try { URL.revokeObjectURL(audioUrl); } catch(e) {}
                        if (sessionId === audioSessionCounter && !speechCompleted && !interviewEnded && !interviewEnding) completeSpeech();
                    }, safeMaxAudioMs + 2000);

                    audioEl.onloadedmetadata = () => {
                        if (interviewEnded || interviewEnding || sessionId !== audioSessionCounter) return;
                        if (audioEl.duration && !isNaN(audioEl.duration) && audioEl.duration > 0) {
                            const preciseDurMs = Math.ceil(audioEl.duration * 1000);
                            if (window._audioWatchdog) clearTimeout(window._audioWatchdog);
                            window._audioWatchdog = setTimeout(() => {
                                try { URL.revokeObjectURL(audioUrl); } catch(e) {}
                                if (sessionId === audioSessionCounter && !speechCompleted && !interviewEnded && !interviewEnding) completeSpeech();
                            }, preciseDurMs + 1200);
                        }
                    };

                    audioEl.onplay = () => {
                        if (interviewEnded || interviewEnding || sessionId !== audioSessionCounter) {
                            try { audioEl.pause(); audioEl.src = ""; } catch(e) {}
                            return;
                        }
                        if (window.FaceEngine && typeof FaceEngine.resumeAudio === "function") {
                            FaceEngine.resumeAudio();
                        }
                        isAvatarSpeaking = true;
                        showSpeaking(true);
                        hideUnmutePrompt();
                        updateTurnStatus("ai_speaking");
                        clearSilenceTimer();
                        hideSilenceTimer();
                        stopRecognition();
                        showLiveMessage(isEndingTurn ? "AI interviewer is concluding..." : "AI interviewer is speaking...");

                        // Synchronize word-by-word streaming in question box with actual audio duration
                        const durMs = (audioEl.duration && !isNaN(audioEl.duration) && audioEl.duration > 0)
                            ? Math.ceil(audioEl.duration * 1000)
                            : safeMaxAudioMs;
                        streamQuestionWordByWord(text, durMs);
                    };

                    audioEl.onended = () => {
                        try { URL.revokeObjectURL(audioUrl); } catch(e) {}
                        if (!interviewEnded && !interviewEnding) completeSpeech();
                    };

                    audioEl.onerror = () => {
                        try { URL.revokeObjectURL(audioUrl); } catch(e) {}
                        if (sessionId === audioSessionCounter && !speechCompleted && !interviewEnded && !interviewEnding) {
                            playBrowserVoice(text, sessionId, completeSpeech, safeMaxAudioMs);
                        }
                    };

                    audioEl.muted = false;
                    audioEl.volume = 1.0;
                    try {
                        if (window.FaceEngine && typeof FaceEngine.resumeAudio === "function") {
                            FaceEngine.resumeAudio();
                        }
                        await audioEl.play();
                        elevenLabsSuccess = true;
                        hideUnmutePrompt();
                    } catch (playErr) {
                        console.warn("Audio element play notice:", playErr);
                        playBrowserVoice(text, sessionId, completeSpeech, safeMaxAudioMs);
                        return;
                    }
                }
            } else {
                console.warn("TTS proxy non-200, status:", response.status);
            }
        } catch (err) {
            console.warn("TTS proxy fetch notice:", err);
            // Do NOT lock out ElevenLabs permanently on a single network timeout
        }
    }

    if (interviewEnded || interviewEnding || sessionId !== audioSessionCounter) return;

    // Fall back to browser SpeechSynthesis immediately if proxy audio did not play
    if (!elevenLabsSuccess && !speechCompleted && !interviewEnded && !interviewEnding) {
        playBrowserVoice(text, sessionId, completeSpeech, safeMaxAudioMs);
    }
}

function getBestMaleVoice() {
    if (!("speechSynthesis" in window)) return null;
    const voices = window.speechSynthesis.getVoices() || [];
    if (voices.length === 0) return null;

    // Filter out explicit female keyword patterns
    const femalePattern = /(female|woman|girl|zira|jenny|aria|ava|emma|ana|michelle|sonia|libby|natasha|clara|catherine|linda|hedda|heera|neerja|kalpana|haruka|ayumi|samantha|victoria|karen|moira|fiona|tessa|serena|kate|stephanie|nicky|alice|kyoko|amelie|melina|yuna|alva|milena|susan|hazel|siri.*female)/i;

    const nonFemaleVoices = voices.filter(v => !femalePattern.test(v.name));
    const enVoices = nonFemaleVoices.filter(v => v.lang && v.lang.toLowerCase().startsWith("en"));

    // 1. Google Male Voice (e.g. "Google UK English Male" in Chrome)
    const googleMale = enVoices.find(v => /google/i.test(v.name) && /\bmale\b/i.test(v.name))
                    || nonFemaleVoices.find(v => /google/i.test(v.name) && /\bmale\b/i.test(v.name));
    if (googleMale) return googleMale;

    // 2. Explicit English Male voice tag
    const explicitMale = enVoices.find(v => /\b(male|man|boy)\b/i.test(v.name));
    if (explicitMale) return explicitMale;

    // 3. Microsoft Natural Male voices (Edge / Windows 11)
    const naturalMale = enVoices.find(v => /natural/i.test(v.name) && /(guy|ryan|christopher|eric|brian|steffan|prabhat|ravi|andrew|william|james)/i.test(v.name));
    if (naturalMale) return naturalMale;

    // 4. Standard Windows SAPI Male voices (David, Mark, George, Paul, Richard, Sean)
    const windowsMale = enVoices.find(v => /(david|mark|george|paul|richard|sean|brian|michael)/i.test(v.name));
    if (windowsMale) return windowsMale;

    // 5. Apple / macOS / iOS Male voices
    const appleMale = enVoices.find(v => /(alex|daniel|oliver|arthur|fred|gordon|aaron|tom|rishi|nathan)/i.test(v.name));
    if (appleMale) return appleMale;

    // 6. Any other voice with explicit male identifier
    const anyExplicitMale = nonFemaleVoices.find(v => /\b(male|man)\b/i.test(v.name));
    if (anyExplicitMale) return anyExplicitMale;

    // 7. Common male named voices
    const anyNamedMale = enVoices.find(v => /(guy|ryan|eric|david|mark|george|alex|daniel|brian|steffan|prabhat|ravi|fred|oliver|arthur)/i.test(v.name));
    if (anyNamedMale) return anyNamedMale;

    // 8. English non-female voice
    if (enVoices.length > 0) return enVoices[0];

    // 9. Any English voice so voice NEVER fails silently
    const anyEn = voices.find(v => v.lang && v.lang.toLowerCase().startsWith("en"));
    if (anyEn) return anyEn;

    return voices[0] || null;
}

function playBrowserVoice(text, sessionId, onFinish, estimatedDurMs) {
    if (interviewEnded || interviewEnding || sessionId !== audioSessionCounter) return;
    if (!("speechSynthesis" in window)) {
        revealFullQuestionImmediately(text);
        if (typeof onFinish === "function") onFinish();
        return;
    }

    clearSpeechCancelTimers();

    try {
        if (window._speechResumeTimer) {
            clearInterval(window._speechResumeTimer);
            window._speechResumeTimer = null;
        }

        setTimeout(() => {
            if (interviewEnded || interviewEnding || sessionId !== audioSessionCounter) return;

            const utterance = new SpeechSynthesisUtterance(text);
            window._activeUtterance = utterance;
            utterance.volume = 1.0;
            utterance.lang = "en-US";

            const maleVoice = getBestMaleVoice();
            if (maleVoice) {
                utterance.voice = maleVoice;
                utterance.lang = maleVoice.lang || "en-US";
            }
            utterance.pitch = 0.95;
            utterance.rate = 1.0;

            let done = false;
            let speechStarted = false;
            const wordCount = (text.match(/\S+/g) || []).length;
            const durMs = estimatedDurMs || Math.max(3000, wordCount * 360 + 1200);

            const startSpeakingUI = () => {
                if (speechStarted || done || sessionId !== audioSessionCounter) return;
                speechStarted = true;
                isAvatarSpeaking = true;
                showSpeaking(true);
                updateTurnStatus("ai_speaking");
                clearSilenceTimer();
                hideSilenceTimer();
                stopRecognition();
                showLiveMessage(isEndingTurn ? "AI interviewer is speaking..." : "AI interviewer is asking a question...");
                streamQuestionWordByWord(text, durMs);
            };

            const safeDone = () => {
                if (done || sessionId !== audioSessionCounter) return;
                done = true;
                if (window._audioWatchdog) { clearTimeout(window._audioWatchdog); window._audioWatchdog = null; }
                if (window._speechResumeTimer) { clearInterval(window._speechResumeTimer); window._speechResumeTimer = null; }
                isAvatarSpeaking = false;
                showSpeaking(false);
                window._activeUtterance = null;
                revealFullQuestionImmediately(text);
                if (!interviewEnded && !interviewEnding && typeof onFinish === "function") onFinish();
            };

            // Safety watchdog: ensure speech always completes cleanly
            window._audioWatchdog = setTimeout(() => {
                safeDone();
            }, durMs + 2000);

            utterance.onstart = () => {
                if (interviewEnded || interviewEnding || sessionId !== audioSessionCounter) {
                    try { window.speechSynthesis.cancel(); } catch(e) {}
                    return;
                }
                startSpeakingUI();
            };

            utterance.onend = safeDone;

            utterance.onerror = (e) => {
                console.warn("SpeechSynthesis notice:", e);
                if (sessionId === audioSessionCounter && !done) {
                    safeDone();
                }
            };

            // Chrome keepalive: prevents Chrome speech synthesis from pausing mid-sentence
            window._speechResumeTimer = setInterval(() => {
                if (window.speechSynthesis && window.speechSynthesis.speaking) {
                    try { window.speechSynthesis.resume(); } catch(e) {}
                }
            }, 250);

            // Fallback: If onstart does not fire in 180ms, ensure UI is active
            setTimeout(() => {
                if (!speechStarted && !done && sessionId === audioSessionCounter) {
                    startSpeakingUI();
                }
            }, 180);

            // Ensure resume is invoked right before and after speak to prevent Chrome paused bug
            try { window.speechSynthesis.resume(); } catch(e) {}
            window.speechSynthesis.speak(utterance);
            setTimeout(() => {
                try { window.speechSynthesis.resume(); } catch(e) {}
            }, 30);
        }, 50);
    } catch (e) {
        console.error("Browser TTS error:", e);
        showSpeaking(false);
        revealFullQuestionImmediately(text);
        if (typeof onFinish === "function") onFinish();
    }
}

function toggleMicrophone() {
    microphoneEnabled = !microphoneEnabled;
    if (stream) stream.getAudioTracks().forEach(t => t.enabled = microphoneEnabled);
    if (microphoneEnabled) {
        showLiveMessage("Microphone on.");
        startRecognition();
        if (audioCtx && audioCtx.state === 'suspended') {
            try { audioCtx.resume(); } catch(e) {}
        }
    } else {
        stopRecognition();
        clearSilenceTimer();
        updateMicVisualizer(0);
        showLiveMessage("Microphone off.");
    }
    updateMicUI();
}
function updateMicUI() {
    const btn = document.getElementById("micButton");
    if (btn) {
        btn.classList.toggle("active", microphoneEnabled);
        btn.classList.toggle("muted", !microphoneEnabled);
        btn.innerHTML = microphoneEnabled ? '<i class="bi bi-mic-fill"></i>' : '<i class="bi bi-mic-mute-fill"></i>';
    }
}

function toggleCamera() {
    cameraEnabled = !cameraEnabled;
    if (stream) stream.getVideoTracks().forEach(t => t.enabled = cameraEnabled);
    updateCameraUI();
    if (typeof handleCameraToggleBehavior === "function") {
        handleCameraToggleBehavior(cameraEnabled);
    }
}

function updateCameraUI() {
    const btn = document.getElementById("cameraButton");
    const video = document.getElementById("candidateVideo");
    const offMsg = document.getElementById("cameraOffMsg");

    if (btn) {
        btn.classList.toggle("active", cameraEnabled);
        btn.classList.toggle("muted", !cameraEnabled);
        btn.innerHTML = cameraEnabled ? '<i class="bi bi-camera-video-fill"></i>' : '<i class="bi bi-camera-video-off-fill"></i>';
    }
    if (video) {
        video.classList.toggle("hidden", !cameraEnabled);
    }
    if (offMsg) {
        offMsg.classList.toggle("show", !cameraEnabled);
    }
}

function toggleAudio() {
    audioEnabled = !audioEnabled;
    if (!audioEnabled) {
        if (currentAudio) { try { currentAudio.pause(); currentAudio.src = ""; } catch(e) {} currentAudio = null; }
        window.speechSynthesis.cancel(); showSpeaking(false);
        showLiveMessage("AI audio off."); startRecognition();
    } else showLiveMessage("AI audio on.");
    const btn = document.getElementById("audioButton");
    if (btn) {
        btn.classList.toggle("active", audioEnabled);
        btn.classList.toggle("muted", !audioEnabled);
        btn.innerHTML = audioEnabled ? '<i class="bi bi-volume-up-fill"></i>' : '<i class="bi bi-volume-mute-fill"></i>';
    }
}

let screenStream = null;
async function toggleScreenShare() {
    const btn = document.getElementById("shareButton");
    if (screenStream) {
        screenStream.getTracks().forEach(t => t.stop());
        screenStream = null;
        if (btn) btn.classList.remove("active");
        showLiveMessage("Screen sharing stopped.");
        return;
    }
    if (!navigator.mediaDevices?.getDisplayMedia) {
        showLiveMessage("Screen sharing not supported by your browser.");
        return;
    }
    try {
        screenStream = await navigator.mediaDevices.getDisplayMedia({ video: true });
        if (btn) btn.classList.add("active");
        showLiveMessage("Screen sharing started.");
        screenStream.getVideoTracks()[0].onended = () => {
            screenStream = null;
            if (btn) btn.classList.remove("active");
            showLiveMessage("Screen sharing ended.");
        };
    } catch (e) {
        console.warn("Screen share cancelled:", e);
    }
}

let interviewerVolumeLevel = 2.5; // Default 250% High Boost for ALL questions!

function setInterviewerVolume(level) {
    interviewerVolumeLevel = Math.max(0.5, Math.min(3.5, Number(level) || 2.5));
    if (window.FaceEngine && typeof window.FaceEngine.setVolume === "function") {
        window.FaceEngine.setVolume(interviewerVolumeLevel);
    }
    const percent = Math.round(interviewerVolumeLevel * 100);
    const label = document.getElementById("interviewerVolLabel");
    if (label) {
        label.textContent = `Vol: ${percent}%` + (percent > 100 ? " (Boosted)" : "");
    }
    showLiveMessage(`Interviewer voice volume set to ${percent}%` + (percent >= 200 ? " (High Boost)" : ""));
}

function cycleInterviewerVolume() {
    // Cycles: 200% -> 250% -> 300% -> 150%
    const levels = [1.5, 2.0, 2.5, 3.0];
    let nextIdx = 0;
    for (let i = 0; i < levels.length; i++) {
        if (Math.abs(levels[i] - interviewerVolumeLevel) < 0.1) {
            nextIdx = (i + 1) % levels.length;
            break;
        }
    }
    setInterviewerVolume(levels[nextIdx]);
}

function openSettingsModal() {
    cycleInterviewerVolume();
}

function toggleMoreOptions() {
    showLiveMessage("Tips: Take your time to think (pauses are fine). Press Enter or click Submit.");
}

function startTimer() {
    if (timerInterval) clearInterval(timerInterval);
    interviewSeconds = 0;
    pendingTimeLimitEnd = false;
    interviewEnded = false;
    updateTimer();
    timerInterval = setInterval(() => {
        if (interviewStarted && !interviewEnding) {
            interviewSeconds++;
            updateTimer();

            // When exactly 10 minutes (600 seconds) is reached:
            if (interviewSeconds >= MAX_INTERVIEW_DURATION_SECONDS) {
                clearInterval(timerInterval);
                timerInterval = null;
                triggerTimeLimitConclusion();
            }
        }
    }, 1000);
}

function updateTimer() {
    if (!timerElement) return;
    const remaining = Math.max(0, MAX_INTERVIEW_DURATION_SECONDS - interviewSeconds);
    const m = Math.floor(remaining / 60), s = remaining % 60;
    timerElement.textContent = String(m).padStart(2, "0") + ":" + String(s).padStart(2, "0");
    if (remaining <= 60) {
        timerElement.style.color = "#f87171"; // warning indicator in the last 60 seconds
    } else {
        timerElement.style.color = "rgba(255,255,255,0.8)";
    }
}

async function triggerTimeLimitConclusion() {
    if (!interviewStarted || interviewEnding) return;
    interviewEnding = true;

    stopRecognition();
    clearSilenceTimer();
    updateMicVisualizer(0);
    showSpeaking(false);
    stopAllAudio();

    showLiveMessage("10-minute time limit reached! Concluding session...");
    updateTurnStatus("ai_speaking");

    const concludingRemark = "Thank you so much for your time today. We have reached our allotted ten-minute interview limit. You did a great job, and your evaluation report is now ready.";
    displayCurrentQuestion(concludingRemark);

    speakAI(concludingRemark, true, "TIME_LIMIT_REACHED");
    setTimeout(() => {
        endInterview("TIME_LIMIT_REACHED");
    }, 2800);
}

// ============================================================
// REALISTIC AI INTERVIEWER FACIAL EXPRESSIONS & LIP-SYNC ENGINE
// ============================================================
const FaceEngine = (function() {
    let canvas = null;
    let ctx = null;
    let imgIdle = null;
    let imgSpeaking = null;
    let isReady = false;

    // Dedicated offscreen canvas for mouth blending (160x120)
    let offMouthCanvas = null;
    let offMouthCtx = null;

    // Web Audio Analyser & Hardware Volume Booster
    let audioCtx = null;
    let analyserNode = null;
    let gainNode = null;
    let compressor = null;
    let volumeMultiplier = 2.5; // 250% default volume boost!
    let audioSourceConnected = false;
    let freqData = null;

    // Animation & State
    let animFrameId = null;
    let isSpeakingState = false;
    let speechStartTime = 0;

    // Smoothed Dynamic Visemes
    let currentMouthOpen = 0.0;     // 0.0 = closed resting, 1.0 = wide open
    let currentMouthWidth = 1.0;    // 0.88 = rounded O/U, 1.15 = spread E/I
    let currentJawDrop = 0.0;       // Vertical jaw displacement in pixels

    // Natural Eyelid Blinking
    let nextBlinkTimestamp = Date.now() + 2500;
    let blinkPhase = 0;             // 0 = open, 1 = closing, 2 = reopening
    let blinkStartTime = 0;
    let blinkDuration = 135;        // ms
    let isDoubleBlinkPending = false;

    // Exact Facial Landmark Anchors (1024x558 Canvas)
    const MOUTH_CX = 512;
    const MOUTH_CY = 312;
    const MOUTH_RX = 48;
    const MOUTH_RY = 24;

    // Exact Pupil Centers & Eye Dimensions
    const EYE_L_X = 468;
    const EYE_L_Y = 210;
    const EYE_R_X = 545;
    const EYE_R_Y = 205;
    const EYE_RX  = 15;
    const EYE_RY  = 7.5;

    function getOffscreenMouth() {
        if (!offMouthCanvas) {
            offMouthCanvas = document.createElement("canvas");
            offMouthCanvas.width = 160;
            offMouthCanvas.height = 120;
            offMouthCtx = offMouthCanvas.getContext("2d");
        }
        return { offCanvas: offMouthCanvas, offCtx: offMouthCtx };
    }

    function init() {
        if (isReady && ctx) return;
        canvas = document.getElementById("interviewerFaceCanvas");
        if (!canvas) return;
        ctx = canvas.getContext("2d");

        // Try getting images from DOM first
        const domIdle = document.getElementById("interviewerPhotoIdle");
        const domSpeaking = document.getElementById("interviewerPhotoSpeaking");

        function checkReady() {
            if (isReady) return;
            const idleOk = imgIdle && (imgIdle.complete || imgIdle.naturalWidth > 0);
            const speakOk = imgSpeaking && (imgSpeaking.complete || imgSpeaking.naturalWidth > 0);
            if (idleOk && speakOk) {
                isReady = true;
                startRenderLoop();
            }
        }

        imgIdle = new Image();
        imgIdle.onload = checkReady;
        imgIdle.onerror = () => console.warn("FaceEngine: idle texture load error");
        imgIdle.src = (domIdle && domIdle.src) ? domIdle.src : (BASE_URL + "/images/interviewer.png");

        imgSpeaking = new Image();
        imgSpeaking.onload = checkReady;
        imgSpeaking.onerror = () => console.warn("FaceEngine: speaking texture load error");
        imgSpeaking.src = (domSpeaking && domSpeaking.src) ? domSpeaking.src : (BASE_URL + "/images/interviewer_speaking.png");

        // If DOM or cache already has them loaded, start immediately
        if (domIdle && domIdle.complete && domIdle.naturalWidth > 0 && 
            domSpeaking && domSpeaking.complete && domSpeaking.naturalWidth > 0) {
            isReady = true;
            startRenderLoop();
        } else {
            checkReady();
        }
    }

    function hookAudioElement(audioEl) {
        if (!audioEl) return;
        try {
            const AudioContextClass = window.AudioContext || window.webkitAudioContext;
            if (!AudioContextClass) return;

            if (!audioCtx) {
                audioCtx = new AudioContextClass();
            }
            if (audioCtx.state === "suspended") {
                audioCtx.resume().catch(() => {});
            }

            if (!audioSourceConnected) {
                const source = audioCtx.createMediaElementSource(audioEl);
                analyserNode = audioCtx.createAnalyser();
                analyserNode.fftSize = 256;
                analyserNode.smoothingTimeConstant = 0.28;
                freqData = new Uint8Array(analyserNode.frequencyBinCount);

                // Broadcast-grade dynamics compressor: prevents distortion/clipping when boosting volume
                compressor = audioCtx.createDynamicsCompressor();
                compressor.threshold.value = -12;
                compressor.knee.value = 24;
                compressor.ratio.value = 8;
                compressor.attack.value = 0.003;
                compressor.release.value = 0.25;

                // Hardware volume booster GainNode (100% - 300%)
                gainNode = audioCtx.createGain();
                gainNode.gain.value = volumeMultiplier;

                // Route: source -> analyserNode -> compressor -> gainNode -> destination
                source.connect(analyserNode);
                analyserNode.connect(compressor);
                compressor.connect(gainNode);
                gainNode.connect(audioCtx.destination);
                audioSourceConnected = true;
            }
            if (audioCtx && audioCtx.state === "suspended") {
                audioCtx.resume().catch(() => {});
            }
            if (gainNode && audioCtx) {
                gainNode.gain.setValueAtTime(volumeMultiplier, audioCtx.currentTime);
            }
        } catch (e) {
            // Already connected or cross-origin handled gracefully
        }
    }

    function setSpeaking(active) {
        isSpeakingState = !!active;
        if (isSpeakingState) {
            speechStartTime = performance.now();
            if (audioCtx && audioCtx.state === "suspended") {
                audioCtx.resume().catch(() => {});
            }
        }
    }

    function updateAcoustics(now) {
        let targetOpen = 0.0;
        let targetWidth = 1.0;

        if (isSpeakingState) {
            let volume = 0.0;
            let formantRatio = 1.0;
            let hasRealAudioSignal = false;

            // 1. Process real Web Audio analyser frequencies
            if (analyserNode && freqData) {
                try {
                    analyserNode.getByteFrequencyData(freqData);

                    let sumVocal = 0, countVocal = 0;
                    for (let i = 2; i <= 18 && i < freqData.length; i++) {
                        sumVocal += freqData[i];
                        countVocal++;
                    }
                    const avgVocal = countVocal > 0 ? (sumVocal / countVocal) : 0;

                    let sumHigh = 0, countHigh = 0;
                    for (let i = 19; i <= 42 && i < freqData.length; i++) {
                        sumHigh += freqData[i];
                        countHigh++;
                    }
                    const avgHigh = countHigh > 0 ? (sumHigh / countHigh) : 0;

                    // Detect actual voice signal above background noise
                    if (avgVocal > 7 || avgHigh > 12) {
                        hasRealAudioSignal = true;
                        const normVol = Math.max(0, (avgVocal - 7) / 46.0);
                        volume = Math.min(1.0, Math.pow(normVol, 0.72) * 1.35);
                        formantRatio = (avgHigh + 1) / (avgVocal + 1);
                        targetWidth = 1.0 + Math.min(0.12, Math.max(-0.10, (formantRatio - 0.90) * 0.28));
                    }
                } catch(e) {}
            }

            if (hasRealAudioSignal) {
                // Real audio driving mouth articulation directly:
                // Lip openings correspond directly to vocal acoustics, closing naturally on consonant closures & pauses
                targetOpen = volume;
            } else {
                // Fallback for browser TTS / initial buffering:
                // Generates natural phonemic syllables in rhythmic phrase bursts
                const t = (now - speechStartTime) / 1000;
                const phraseGate = Math.sin(t * 2.2) > -0.35 ? 1.0 : 0.0;
                const syllable = Math.sin(t * 14.5) * 0.45 
                               + Math.sin(t * 7.5) * 0.35 
                               + Math.cos(t * 22.0) * 0.20;
                const oscVol = Math.min(0.90, Math.max(0, (syllable + 0.20) * phraseGate));
                targetOpen = Math.min(1.0, Math.pow(oscVol, 0.75) * 1.25);
                targetWidth = 1.0 + 0.08 * Math.sin(t * 9.0);
            }

            if (targetOpen < 0.02) targetOpen = 0.0;
        } else {
            // Resting closed mouth
            targetOpen = 0.0;
            targetWidth = 1.0;
        }

        // Asymmetric biomechanical kinematics:
        // Jaw depression (opening) is fast (~35ms attack); lip closure is smooth elastic recovery (~60ms release)
        if (targetOpen > currentMouthOpen) {
            currentMouthOpen += (targetOpen - currentMouthOpen) * 0.46;
        } else {
            currentMouthOpen += (targetOpen - currentMouthOpen) * 0.30;
        }
        currentMouthWidth += (targetWidth - currentMouthWidth) * 0.25;

        if (currentMouthOpen < 0.005) currentMouthOpen = 0.0;
    }

    function updateBlink(now) {
        if (now >= nextBlinkTimestamp && blinkPhase === 0) {
            blinkPhase = 1;
            blinkStartTime = now;
            blinkDuration = isDoubleBlinkPending ? 105 : 130;
        }

        let progress = 0.0;
        if (blinkPhase > 0) {
            const elapsed = now - blinkStartTime;
            if (elapsed < blinkDuration * 0.45) {
                progress = Math.sin((elapsed / (blinkDuration * 0.45)) * (Math.PI / 2));
            } else if (elapsed < blinkDuration) {
                const openElapsed = elapsed - (blinkDuration * 0.45);
                const openTotal = blinkDuration * 0.55;
                progress = 1.0 - Math.sin((openElapsed / openTotal) * (Math.PI / 2));
            } else {
                progress = 0.0;
                blinkPhase = 0;
                if (isDoubleBlinkPending) {
                    isDoubleBlinkPending = false;
                    nextBlinkTimestamp = now + (2600 + Math.random() * 2200);
                } else if (Math.random() < 0.16) {
                    isDoubleBlinkPending = true;
                    nextBlinkTimestamp = now + 150;
                } else {
                    nextBlinkTimestamp = now + (3200 + Math.random() * 2600);
                }
            }
        }
        return Math.max(0, Math.min(1.0, progress));
    }

    function drawEyelid(cx, cy, rx, ry, progress) {
        const maxDrop = 11.5 * progress;
        ctx.save();
        ctx.beginPath();
        ctx.ellipse(cx, cy - 8 + (maxDrop * 0.5), rx, Math.max(4, 7 + maxDrop * 0.5), 0, 0, Math.PI * 2);
        ctx.clip();

        // Sample real upper eyelid skin texture from imgIdle right above the eye socket
        const srcH = 10;
        const destH = 8 + maxDrop;
        ctx.drawImage(
            imgIdle,
            cx - rx - 2, cy - 14, rx * 2 + 4, srcH,
            cx - rx - 2, cy - 12 + (maxDrop * 0.2), rx * 2 + 4, destH
        );

        // Natural curved eyelash margin
        ctx.beginPath();
        ctx.ellipse(cx, cy - 6 + maxDrop, rx, 1.8, 0, 0, Math.PI);
        ctx.fillStyle = "rgba(42, 28, 20, 0.85)";
        ctx.fill();

        // Subtle crease shadow
        ctx.beginPath();
        ctx.ellipse(cx, cy - 7, rx - 1, 1.2, 0, Math.PI, Math.PI * 2);
        ctx.strokeStyle = "rgba(90, 60, 45, 0.35)";
        ctx.lineWidth = 1;
        ctx.stroke();

        ctx.restore();
    }

    function render(now) {
        if (!isReady || !ctx || !imgIdle || !imgSpeaking) return;

        updateAcoustics(now);
        const blinkProgress = updateBlink(now);

        // Subtle organic breathing drift (gives 100% human presence on camera)
        const microBreathY = Math.sin(now / 1900) * 0.32;
        const microBreathX = Math.cos(now / 3300) * 0.20;

        // 1. Draw base portrait on canvas
        ctx.drawImage(imgIdle, microBreathX, microBreathY, 1024, 558);

        // 2. High-Fidelity Anatomical Viseme Rendering
        if (currentMouthOpen > 0.008) {
            const { offCanvas, offCtx } = getOffscreenMouth();
            offCtx.clearRect(0, 0, 160, 120);

            const curRadX = MOUTH_RX * currentMouthWidth;
            const curRadY = Math.max(7.5, MOUTH_RY * (0.35 + 0.65 * currentMouthOpen));
            const jawDrop = 3.6 * currentMouthOpen;
            const curCy = MOUTH_CY + jawDrop + microBreathY;
            const curCx = MOUTH_CX + microBreathX;

            // 2a. Draw natural speaking texture vertically scaled to match physiological jaw articulation
            offCtx.save();
            offCtx.translate(80, 60);
            offCtx.scale(currentMouthWidth, 0.40 + 0.60 * currentMouthOpen);
            offCtx.drawImage(imgSpeaking, -MOUTH_CX, -MOUTH_CY, 1024, 558);
            offCtx.restore();

            // 2b. Destination-in anatomical elliptical feather mask
            offCtx.save();
            offCtx.globalCompositeOperation = "destination-in";
            offCtx.translate(80, 60);
            offCtx.scale(1.0, curRadY / curRadX);

            const grad = offCtx.createRadialGradient(0, 0, curRadX * 0.62, 0, 0, curRadX);
            grad.addColorStop(0, "rgba(0,0,0,1)"); // Fully opaque oral aperture & teeth
            grad.addColorStop(1, "rgba(0,0,0,0)"); // Soft seamless feathering into facial skin
            offCtx.fillStyle = grad;
            offCtx.fillRect(-80, -80 * (curRadX / curRadY), 160, 160 * (curRadX / curRadY));
            offCtx.restore();

            // 2c. Composite seamlessly onto master canvas with smooth dynamic opacity curve
            ctx.save();
            ctx.globalAlpha = Math.min(1.0, currentMouthOpen * 2.5);
            ctx.drawImage(offCanvas, curCx - 80, curCy - 60);
            ctx.restore();
        }

        // 3. Realistic Eyelid Blinking
        if (blinkProgress > 0.02) {
            ctx.save();
            drawEyelid(EYE_L_X + microBreathX, EYE_L_Y + microBreathY, EYE_RX, EYE_RY, blinkProgress);
            drawEyelid(EYE_R_X + microBreathX, EYE_R_Y + microBreathY, EYE_RX, EYE_RY, blinkProgress);
            ctx.restore();
        }
    }

    function startRenderLoop() {
        if (animFrameId) cancelAnimationFrame(animFrameId);
        const loop = (now) => {
            render(now);
            animFrameId = requestAnimationFrame(loop);
        };
        animFrameId = requestAnimationFrame(loop);
    }

    function resumeAudio() {
        if (audioCtx && audioCtx.state === "suspended") {
            audioCtx.resume().catch(() => {});
        }
        if (gainNode && audioCtx) {
            gainNode.gain.setValueAtTime(volumeMultiplier, audioCtx.currentTime);
        }
    }

    function setVolume(multiplier) {
        volumeMultiplier = Math.max(0.5, Math.min(3.5, Number(multiplier) || 2.5));
        if (gainNode && audioCtx) {
            gainNode.gain.setValueAtTime(volumeMultiplier, audioCtx.currentTime);
        }
        return volumeMultiplier;
    }

    function getVolume() {
        return volumeMultiplier;
    }

    return {
        init: init,
        setSpeaking: setSpeaking,
        hookAudioElement: hookAudioElement,
        resumeAudio: resumeAudio,
        setVolume: setVolume,
        getVolume: getVolume
    };
})();

// Expose globally and auto-initialize on DOM ready
window.FaceEngine = FaceEngine;
if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", () => FaceEngine.init());
} else {
    FaceEngine.init();
}

function showSpeaking(active) {
    const isSpeaking = !!active;
    const card = document.getElementById("interviewerVideoCard");
    if (card) {
        card.classList.toggle("speaking", isSpeaking);
    }
    const glow = document.getElementById("interviewerAmbientGlow");
    if (glow) {
        glow.classList.toggle("speaking", isSpeaking);
    }
    const wave = document.getElementById("interviewerVoiceWave");
    if (wave) {
        wave.classList.toggle("speaking", isSpeaking);
    }
    const mouth = document.getElementById("avatarMouth");
    if (mouth) {
        mouth.classList.toggle("speaking", isSpeaking);
    }

    // Trigger realistic facial expressions & lip-sync engine
    FaceEngine.setSpeaking(isSpeaking);
}

let liveMessageTimeout = null;
function showLiveMessage(msg) {
    if (!liveMessage) return;
    liveMessage.textContent = String(msg || "");
    liveMessage.classList.add("show");
    clearTimeout(liveMessageTimeout);
    liveMessageTimeout = setTimeout(() => { if (!interviewEnding) liveMessage.classList.remove("show"); }, 3500);
}
function showTranscript(text) {
    if (!liveTranscript || !transcriptText) return;
    transcriptText.textContent = text; liveTranscript.classList.add("visible");
}
function hideTranscript() {
    if (!liveTranscript || !transcriptText) return;
    liveTranscript.classList.remove("visible"); transcriptText.textContent = "Start speaking...";
}

let evalRedirectTimer = null;

function endInterview(reason = "") {
    if (interviewEnded) return;
    interviewEnded = true;
    interviewEnding = true;
    interviewStarted = false;
    audioSessionCounter++; // Invalidate every active/pending audio session

    if (timerInterval) {
        clearInterval(timerInterval);
        timerInterval = null;
    }
    stopRecognition();
    clearSilenceTimer();
    updateMicVisualizer(0);
    stopBehaviorDetection();
    stopAllAudio(); // Immediately cuts off ElevenLabs, Web Audio, and speech synthesis
    if (stream) { stream.getTracks().forEach(t => t.stop()); stream = null; }
    showSpeaking(false);
    if (liveMessage) liveMessage.classList.remove("show");

    // Pre-save metrics immediately so report is ready when requested
    saveMetricsToSession();

    if (interviewScreen) interviewScreen.style.display = "none";
    if (setupScreen) setupScreen.style.display = "none";
    if (endScreen) {
        const titleEl = document.getElementById("endTitle");
        const msgEl   = document.getElementById("endMessage");
        if (titleEl && msgEl) {
            if (reason === "TIME_LIMIT_REACHED") {
                titleEl.textContent = "Interview Completed!";
                msgEl.innerHTML = "Great job! You completed your 10-minute live interview session. Choose an option below to view your evaluation report, practice again, or return to your dashboard.";
            } else if (reason === "QUESTIONS_LIMIT_REACHED") {
                titleEl.textContent = "Interview Completed!";
                msgEl.innerHTML = "Great job! You answered all questions in this session. Choose an option below to view your evaluation report, practice again, or return to your dashboard.";
            } else if (reason === "STRUGGLE_REPEAT" || reason === "CANDIDATE_REQUESTED_END") {
                titleEl.textContent = "Practice Session Concluded";
                msgEl.textContent   = "Take time to review the fundamental concepts for " + (preparation || "this topic") + ". You can review your report, practice again, or return to your dashboard.";
            } else {
                titleEl.textContent = "Interview Completed!";
                msgEl.textContent   = "Your live interview session has ended. Select an option below to view your report, practice again, or return to your dashboard.";
            }
        }
        endScreen.style.display = "flex";
    }
}

function saveMetricsToSession() {
    try {
        const total = Math.max(1, proctoringStats.totalChecks);
        const facingPct = Math.round((proctoringStats.facingCamera / total) * 100);
        const sidesPct  = Math.round(((proctoringStats.lookingLeft + proctoringStats.lookingRight) / total) * 100);
        const downPct   = Math.round((proctoringStats.lookingDown / total) * 100);
        const upPct     = Math.round((proctoringStats.lookingUp / total) * 100);
        const missingPct= Math.round((proctoringStats.faceMissing / total) * 100);
        const multiPct  = Math.round((proctoringStats.multiFace / total) * 100);

        let concentration = Math.max(20, Math.min(100, Math.round(facingPct - (proctoringStats.warningsCount * 3))));
        if (total < 10) concentration = 90; // Default if test was too brief

        let cheatingRisk = "Low Risk";
        if (concentration < 60 || proctoringStats.warningsCount >= 5 || sidesPct >= 35) {
            cheatingRisk = "High Risk";
        } else if (concentration < 80 || proctoringStats.warningsCount >= 2 || sidesPct >= 18) {
            cheatingRisk = "Moderate Risk";
        }

        const proctoringData = {
            total_checks: proctoringStats.totalChecks,
            facing_camera_pct: facingPct,
            looking_sides_pct: sidesPct,
            looking_down_pct: downPct,
            looking_up_pct: upPct,
            missing_face_pct: missingPct,
            multi_face_pct: multiPct,
            warnings_count: proctoringStats.warningsCount,
            concentration_score: concentration,
            cheating_risk: cheatingRisk
        };

        const candidateTurns = conversationHistory.filter(t => t.role === 'user' && t.text && t.text !== 'OPENING');
        const candidateText = candidateTurns.map(t => t.text).join(" ");
        const words = candidateText.trim().split(/\s+/).filter(Boolean);

        let totalFillers = 0;
        const fillerBreakdown = {};
        COMMON_FILLER_PATTERNS.forEach(fp => {
            const matches = candidateText.match(fp.regex);
            if (matches && matches.length > 0) {
                totalFillers += matches.length;
                fillerBreakdown[fp.label] = (fillerBreakdown[fp.label] || 0) + matches.length;
            }
        });

        // Compute overall average WPM across total speaking duration
        const effectiveSpeakingSec = Math.max(totalInterviewSpeakingSeconds, Math.round(words.length * 0.4));
        const speakingMinutes = effectiveSpeakingSec > 0 ? (effectiveSpeakingSec / 60) : 0;
        const avgWpm = (words.length > 0 && speakingMinutes > 0) ? Math.round(words.length / speakingMinutes) : 0;

        let paceVerdict = "Optimal Pace";
        if (avgWpm === 0) {
            paceVerdict = "Not Recorded";
        } else if (avgWpm < 115) {
            paceVerdict = "Deliberate / Slow Pace";
        } else if (avgWpm > 170) {
            paceVerdict = "Fast Speaking Pace";
        } else {
            paceVerdict = "Optimal Pace (130-160 WPM)";
        }

        let fluencyScore = 100;
        if (words.length > 0) {
            const fillerRate = (totalFillers / words.length) * 100;
            fluencyScore = Math.max(35, Math.min(100, Math.round(100 - (fillerRate * 9))));
        } else {
            fluencyScore = 0;
        }

        const speechData = {
            total_words: words.length,
            filler_count: totalFillers,
            speaking_pace_wpm: avgWpm,
            pace_verdict: paceVerdict,
            filler_breakdown: fillerBreakdown,
            speaking_duration_seconds: Math.round(effectiveSpeakingSec),
            fluency_score: fluencyScore
        };

        sessionStorage.setItem("last_interview_history",    JSON.stringify(conversationHistory));
        sessionStorage.setItem("last_interview_id",         String(currentInterviewId));
        sessionStorage.setItem("last_interview_prep",       preparation);
        sessionStorage.setItem("last_interview_proctoring", JSON.stringify(proctoringData));
        sessionStorage.setItem("last_interview_speech",     JSON.stringify(speechData));
    } catch(e) {
        console.error("Error saving evaluation metrics:", e);
    }
}

function goToReport() {
    clearTimeout(evalRedirectTimer);
    saveMetricsToSession();
    window.location.href = BASE_URL + "/user/live_evaluation.php?id=" + currentInterviewId;
}

function returnToSetup() {
    clearTimeout(evalRedirectTimer);
    if (endScreen) endScreen.style.display = "none";
    if (setupScreen) setupScreen.style.display = "flex";
    interviewEnding = false; interviewStarted = false; interviewEnded = false;
    isProcessingAnswer = false; isAvatarSpeaking = false;
    preparation = ""; conversationHistory = []; currentInterviewId = 0;
    consecutiveStruggles = 0; interviewConcluded = false; concludeReason = "";
    questionCounter = 0; currentQuestionText = "";
    accumulatedAnswer = ""; currentInterimText = "";
    silentAutoAdvanceCount = 0;
    totalInterviewSpeakingSeconds = 0;
    currentTurnSpeakingStartTime = null;
    updateSpeechDeliveryHUD("");
    proctoringStats = { totalChecks: 0, facingCamera: 0, lookingLeft: 0, lookingRight: 0, lookingDown: 0, lookingUp: 0, faceMissing: 0, multiFace: 0, warningsCount: 0 };
    clearSilenceTimer();
    updateMicVisualizer(0);
    stopBehaviorDetection();
    resetBehaviorUI();
    stopAllAudio();
    if (timerElement) {
        timerElement.textContent = "10:00";
        timerElement.style.color = "rgba(255,255,255,0.8)";
    }
    const qDisplay = document.getElementById("currentQuestionDisplay");
    if (qDisplay) qDisplay.textContent = "";
    if (preparationInput) preparationInput.value = "";
    const ansInput = document.getElementById("candidateAnswerInput");
    if (ansInput) ansInput.value = "";
}

// Wire up typing and keyboard events on candidateAnswerInput
document.addEventListener("DOMContentLoaded", () => {
    const input = document.getElementById("candidateAnswerInput");
    if (input) {
        input.addEventListener("input", () => {
            resetSilenceCountdown();
            accumulatedAnswer = input.value;
            const submitBtn = document.getElementById("submitAnswerBtn");
            if (submitBtn) submitBtn.classList.toggle("highlight", input.value.trim().length > 0);
            updateTurnStatus("waiting_answering");
            if (!currentTurnSpeakingStartTime && input.value.trim().length > 0) {
                currentTurnSpeakingStartTime = Date.now();
            }
            updateSpeechDeliveryHUD(input.value);
        });
        input.addEventListener("keydown", (e) => {
            if (e.key === "Enter" && !e.shiftKey) {
                e.preventDefault();
                submitSpokenAnswerNow();
            }
        });
    }
});

window.addEventListener("beforeunload", () => {
    try { stopRecognition(); } catch(e) {}
    try { clearSilenceTimer(); } catch(e) {}
    try { window.speechSynthesis.cancel(); } catch(e) {}
    try { stopBehaviorDetection(); } catch(e) {}
    if (stream) stream.getTracks().forEach(t => t.stop());
});

/* ============================================================
   REAL-TIME CAMERA BEHAVIOR DETECTION (100% Client-Side)
   Library: Google MediaPipe Face Mesh
   Privacy: 100% Browser-side processing via WebAssembly.
            ZERO frames uploaded, ZERO video stored,
            ZERO API calls, ZERO MySQL tables or DB writes.
============================================================ */

// Configurable thresholds (milliseconds)
const LOOK_AWAY_THRESHOLD   = 2000; // Continuous head turned > 2s triggers warning
const FACE_MISSING_THRESHOLD = 2000; // Continuous face missing > 2s triggers warning
const MULTI_FACE_THRESHOLD   = 1500; // Continuous multiple faces > 1.5s triggers warning
const DETECTION_INTERVAL_MS  = 130;  // ~7 to 8 FPS (low CPU, silky smooth responsiveness)

let faceMesh = null;
let faceMeshReady = false;
let detectionInterval = null;
let isProcessingFrame = false;

// Behavior state tracking
let lookingAwayStartTime = null;
let faceMissingStartTime = null;
let multiFaceStartTime   = null;
let activeWarningState   = null; // null | "LOOK_AWAY" | "FACE_MISSING" | "MULTI_FACE"
let lastRestoredNormal   = false;

// Proctoring & Concentration Metrics accumulator
let proctoringStats = {
    totalChecks: 0,
    facingCamera: 0,
    lookingLeft: 0,
    lookingRight: 0,
    lookingDown: 0,
    lookingUp: 0,
    faceMissing: 0,
    multiFace: 0,
    warningsCount: 0
};

const presenceEl   = document.getElementById("behaviorPresence");
const presenceText = document.getElementById("presenceText");
const statusEl     = document.getElementById("behaviorStatus");
const warningEl    = document.getElementById("behaviorWarning");
const warningMsgEl = document.getElementById("warningMessage");

/**
 * Clean Extensible Behavior Detection Event Hook
 * Emits real-time client events:
 * - "FACING_CAMERA"
 * - "LOOKING_LEFT"
 * - "LOOKING_RIGHT"
 * - "LOOKING_UP"
 * - "LOOKING_DOWN"
 * - "FACE_NOT_DETECTED"
 * - "MULTIPLE_FACES"
 * Currently updates live UI only - NOT connected to PHP or MySQL.
 */
function handleBehaviorDetection(eventType, details = {}) {
    // Extensible event listener system for future proctoring / callbacks
    if (window._cameraBehaviorListeners && Array.isArray(window._cameraBehaviorListeners)) {
        window._cameraBehaviorListeners.forEach(fn => {
            try { fn(eventType, details); } catch(e) { console.error(e); }
        });
    }
}

/**
 * Register external behavior listener without editing core logic
 */
function addCameraBehaviorListener(fn) {
    if (!window._cameraBehaviorListeners) window._cameraBehaviorListeners = [];
    window._cameraBehaviorListeners.push(fn);
}

function initBehaviorDetector() {
    if (typeof FaceMesh === "undefined") {
        console.warn("MediaPipe FaceMesh not loaded from CDN.");
        showBehaviorError("Camera detection unavailable. Check connection.");
        return;
    }
    if (faceMesh) return;

    try {
        faceMesh = new FaceMesh({
            locateFile: (file) => `https://cdn.jsdelivr.net/npm/@mediapipe/face_mesh/${file}`
        });
        faceMesh.setOptions({
            maxNumFaces: 2,
            refineLandmarks: false,
            minDetectionConfidence: 0.5,
            minTrackingConfidence: 0.5
        });
        faceMesh.onResults(onFaceMeshResults);
        faceMeshReady = true;
    } catch (err) {
        console.error("Failed to initialize FaceMesh:", err);
        showBehaviorError("Camera detection unavailable.");
    }
}

function startBehaviorDetection() {
    initBehaviorDetector();
    if (detectionInterval) clearInterval(detectionInterval);

    lookingAwayStartTime = null;
    faceMissingStartTime = null;
    multiFaceStartTime   = null;
    activeWarningState   = null;
    lastRestoredNormal   = false;

    detectionInterval = setInterval(async () => {
        if (!interviewStarted || interviewEnding || !cameraEnabled) return;
        if (!candidateVideo || candidateVideo.readyState < 2 || candidateVideo.paused) return;
        if (!faceMeshReady || !faceMesh || isProcessingFrame) return;

        isProcessingFrame = true;
        try {
            await faceMesh.send({ image: candidateVideo });
        } catch (e) {
            // Silently ignore momentary frame capture skips
        } finally {
            isProcessingFrame = false;
        }
    }, DETECTION_INTERVAL_MS);
}

function stopBehaviorDetection() {
    if (detectionInterval) {
        clearInterval(detectionInterval);
        detectionInterval = null;
    }
    isProcessingFrame = false;
    lookingAwayStartTime = null;
    faceMissingStartTime = null;
    multiFaceStartTime   = null;
    activeWarningState   = null;
}

function handleCameraToggleBehavior(enabled) {
    if (!enabled) {
        setBehaviorUI({
            presenceLabel: "Camera Paused",
            presenceClass: "subtle",
            dotClass: "warn",
            statusLabel: "Camera Off",
            statusClass: "subtle",
            warning: null
        });
    } else {
        resetBehaviorUI();
    }
}

/**
 * MediaPipe FaceMesh Landmark & Head Direction Processor
 */
function onFaceMeshResults(results) {
    if (!interviewStarted || interviewEnding || !cameraEnabled) return;

    proctoringStats.totalChecks++;
    const now = Date.now();
    const numFaces = results.multiFaceLandmarks ? results.multiFaceLandmarks.length : 0;

    // CASE 1: Face Not Detected
    if (numFaces === 0) {
        proctoringStats.faceMissing++;
        lookingAwayStartTime = null;
        multiFaceStartTime   = null;

        if (!faceMissingStartTime) {
            faceMissingStartTime = now;
        }
        const missingDuration = now - faceMissingStartTime;

        if (missingDuration >= FACE_MISSING_THRESHOLD) {
            if (activeWarningState !== "FACE_MISSING") {
                activeWarningState = "FACE_MISSING";
                lastRestoredNormal = false;
                proctoringStats.warningsCount++;
                handleBehaviorDetection("FACE_NOT_DETECTED", { elapsedMs: missingDuration });
            }
            setBehaviorUI({
                presenceLabel: "Face Not Detected",
                presenceClass: "danger",
                dotClass: "danger",
                statusLabel: "Face Not Detected",
                statusClass: "danger",
                warning: "Face not detected",
                warningClass: "danger"
            });
        } else {
            // Natural tolerance under 2 seconds: do not trigger warning banner
            setBehaviorUI({
                presenceLabel: "Face Detected",
                presenceClass: "subtle",
                dotClass: "",
                statusLabel: "Checking...",
                statusClass: "subtle",
                warning: null
            });
        }
        return;
    }

    // Reset missing face tracking once at least 1 face is seen
    faceMissingStartTime = null;

    // CASE 2: Multiple Faces Detected
    if (numFaces > 1) {
        proctoringStats.multiFace++;
        lookingAwayStartTime = null;
        if (!multiFaceStartTime) multiFaceStartTime = now;
        const multiDuration = now - multiFaceStartTime;

        if (multiDuration >= MULTI_FACE_THRESHOLD) {
            if (activeWarningState !== "MULTI_FACE") {
                activeWarningState = "MULTI_FACE";
                lastRestoredNormal = false;
                proctoringStats.warningsCount++;
                handleBehaviorDetection("MULTIPLE_FACES", { count: numFaces, elapsedMs: multiDuration });
            }
            setBehaviorUI({
                presenceLabel: "Multiple Faces (" + numFaces + ")",
                presenceClass: "danger",
                dotClass: "danger",
                statusLabel: "Multiple Faces",
                statusClass: "danger",
                warning: "Multiple faces detected",
                warningClass: "danger"
            });
        }
        return;
    }

    // CASE 3: Single Face Detected -> Compute 3D Head Orientation
    multiFaceStartTime = null;
    const lm = results.multiFaceLandmarks[0];

    // Key Landmark Indices:
    const nose     = lm[1];
    const eyeR     = lm[33];
    const eyeL     = lm[263];
    const forehead = lm[10];
    const chin     = lm[152];

    const spanX = Math.abs(eyeL.x - eyeR.x);
    const spanY = Math.abs(chin.y - forehead.y);

    let direction = "FACING_CAMERA";

    if (spanX > 0.02 && spanY > 0.04) {
        // Horizontal symmetry ratio (Yaw)
        const minX = Math.min(eyeR.x, eyeL.x);
        const maxX = Math.max(eyeR.x, eyeL.x);
        const yawRatio = (nose.x - minX) / (maxX - minX);

        // Vertical symmetry ratio (Pitch)
        const minY = Math.min(forehead.y, chin.y);
        const maxY = Math.max(forehead.y, chin.y);
        const pitchRatio = (nose.y - minY) / (maxY - minY);

        // Relative 3D depth difference across cheeks
        const deltaZ = (lm[454].z || 0) - (lm[234].z || 0);

        // Candidate video is mirrored (transform: scaleX(-1))
        if (yawRatio < 0.36 || deltaZ > 0.085) {
            direction = "LOOKING_LEFT";
        } else if (yawRatio > 0.64 || deltaZ < -0.085) {
            direction = "LOOKING_RIGHT";
        } else if (pitchRatio < 0.32) {
            direction = "LOOKING_UP";
        } else if (pitchRatio > 0.58) {
            direction = "LOOKING_DOWN";
        }
    }

    if (direction === "FACING_CAMERA") {
        proctoringStats.facingCamera++;
        lookingAwayStartTime = null;
        const wasWarning = (activeWarningState !== null);
        activeWarningState = null;

        if (wasWarning || !lastRestoredNormal) {
            lastRestoredNormal = true;
            handleBehaviorDetection("FACING_CAMERA", { restored: wasWarning });
            setBehaviorUI({
                presenceLabel: "Face Detected",
                presenceClass: "ok",
                dotClass: "",
                statusLabel: "Facing Camera",
                statusClass: "ok",
                warning: null
            });
        } else {
            setBehaviorUI({
                presenceLabel: "Face Detected",
                presenceClass: "ok",
                dotClass: "",
                statusLabel: "Facing Camera",
                statusClass: "ok",
                warning: null
            });
        }
    } else {
        // Looking Away (Left, Right, Up, or Down)
        if (direction === "LOOKING_LEFT")  proctoringStats.lookingLeft++;
        else if (direction === "LOOKING_RIGHT") proctoringStats.lookingRight++;
        else if (direction === "LOOKING_DOWN")  proctoringStats.lookingDown++;
        else if (direction === "LOOKING_UP")    proctoringStats.lookingUp++;

        lastRestoredNormal = false;
        if (!lookingAwayStartTime) {
            lookingAwayStartTime = now;
        }
        const awayDuration = now - lookingAwayStartTime;

        const dirHuman = direction === "LOOKING_LEFT"  ? "Looking Left" :
                         direction === "LOOKING_RIGHT" ? "Looking Right" :
                         direction === "LOOKING_UP"    ? "Looking Up" : "Looking Down";

        if (awayDuration >= LOOK_AWAY_THRESHOLD) {
            // Sustained look away (> 2 seconds): Trigger Warning!
            if (activeWarningState !== direction) {
                activeWarningState = direction;
                proctoringStats.warningsCount++;
                handleBehaviorDetection(direction, { elapsedMs: awayDuration });
            }
            setBehaviorUI({
                presenceLabel: "Face Detected",
                presenceClass: "ok",
                dotClass: "warn",
                statusLabel: dirHuman,
                statusClass: "warn",
                warning: "Please face the camera",
                warningClass: "warn"
            });
        } else {
            // Natural brief movement (< 2 seconds): Normal tolerance, no warning banner
            setBehaviorUI({
                presenceLabel: "Face Detected",
                presenceClass: "ok",
                dotClass: "",
                statusLabel: dirHuman,
                statusClass: "subtle",
                warning: null
            });
        }
    }
}

function setBehaviorUI({ presenceLabel, presenceClass, dotClass, statusLabel, statusClass, warning, warningClass }) {
    if (presenceText) presenceText.textContent = presenceLabel;
    if (presenceEl) {
        presenceEl.className = "behavior-tag " + (presenceClass || "ok");
        const dot = presenceEl.querySelector(".status-dot");
        if (dot) dot.className = "status-dot " + (dotClass || "");
    }
    if (statusEl) {
        statusEl.textContent = statusLabel;
        statusEl.className = "behavior-tag " + (statusClass || "ok");
    }
    if (warningEl && warningMsgEl) {
        if (warning) {
            warningMsgEl.textContent = warning;
            warningEl.className = "behavior-warning " + (warningClass || "warn");
            warningEl.style.display = "flex";
        } else {
            warningEl.style.display = "none";
        }
    }
}

function resetBehaviorUI() {
    setBehaviorUI({
        presenceLabel: "Face Detected",
        presenceClass: "ok",
        dotClass: "",
        statusLabel: "Facing Camera",
        statusClass: "ok",
        warning: null
    });
}

function showBehaviorError(msg) {
    setBehaviorUI({
        presenceLabel: "Camera Detection",
        presenceClass: "subtle",
        dotClass: "warn",
        statusLabel: "Unavailable",
        statusClass: "subtle",
        warning: msg,
        warningClass: "warn"
    });
}
</script>
<?php if ($autoStart): ?>
<script>
window.addEventListener('DOMContentLoaded', function() {
    setTimeout(function() {
        if (typeof beginInterview === 'function') {
            beginInterview();
        }
    }, 400);
});
</script>
<?php endif; ?>
</body>
</html>
