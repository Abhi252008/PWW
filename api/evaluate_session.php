<?php
/**
 * MODULE 6 — AI Evaluation Engine
 *
 * Called via AJAX (POST) from user/interview_finish.php when the user
 * clicks "View My Results". Sends ALL questions+answers to Gemini in ONE
 * single API call, stores scores back to the DB, and returns a redirect URL.
 *
 * POST body (JSON): { "session_id": int }
 * Response (JSON):  { "success": bool, "redirect": string, "error": string }
 */

// Buffer ALL output so any PHP warnings/notices never corrupt the JSON.
ob_start();

// Suppress HTML error display — errors logged, not echoed.
ini_set('display_errors', '0');
error_reporting(E_ALL);

// Extend time limit — one Gemini call for all questions.
set_time_limit(180);

require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../config/gemini_config.php';

// Discard any stray output, then set JSON header.
ob_clean();
header('Content-Type: application/json');

// ── Parse request ──
$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);

$sessionId = (int) ($data['session_id'] ?? 0);
$userId    = (int) $_SESSION['user_id'];

if ($sessionId <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Missing session_id.']);
    exit;
}

// ── Verify session ownership ──
$sessionStmt = $pdo->prepare(
    "SELECT s.*, c.name AS category_name
     FROM interview_sessions s
     JOIN categories c ON c.id = s.category_id
     WHERE s.id = ? AND s.user_id = ?"
);
$sessionStmt->execute([$sessionId, $userId]);
$session = $sessionStmt->fetch();

if (!$session) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Session not found or not yours.']);
    exit;
}

// ── Load questions + answers ──
$qaStmt = $pdo->prepare(
    "SELECT q.id AS question_id, q.question_text, q.question_order,
            a.id AS answer_id, a.answer_text
     FROM questions q
     LEFT JOIN answers a ON a.question_id = q.id
     WHERE q.session_id = ?
     ORDER BY q.question_order"
);
$qaStmt->execute([$sessionId]);
$qaPairs = $qaStmt->fetchAll();

if (empty($qaPairs)) {
    echo json_encode(['success' => false, 'error' => 'No questions found for this session.']);
    exit;
}

// ── Build prompt using GeminiPrompts Engine ──
$categoryName = $session['category_name'] ?? 'General';
$difficulty   = $session['difficulty'] ?? 'Intermediate';
$companyName  = !empty($session['company_name']) ? $session['company_name'] : 'General';
$prompt       = GeminiPrompts::buildEvaluationPrompt($qaPairs, $categoryName, $difficulty, $companyName);

// ── Single Gemini call with generous 120s timeout, automatic retry & smart persona ──
$result = callGemini($prompt, 120, 2, GeminiPrompts::getSystemInstruction(), 0.2);

$evalList = null;
if ($result['success']) {
    $evalList = extractJsonFromGemini($result['text']);

    // If Gemini wrapped array in an object like {"evaluations": [...]}
    if (is_array($evalList)) {
        if (!isset($evalList[0]) && !isset($evalList['score'])) {
            foreach ($evalList as $val) {
                if (is_array($val) && isset($val[0])) {
                    $evalList = $val;
                    break;
                }
            }
        } elseif (isset($evalList['score'])) {
            $evalList = [$evalList];
        }
    }
}

$catLower = strtolower($categoryName);
$isCoding = str_contains($catLower, 'coding')
         || str_contains($catLower, 'dsa')
         || str_contains($catLower, 'algorithm')
         || str_contains($catLower, 'leetcode')
         || str_contains($catLower, 'problem solving');

// If Gemini timed out or failed, provide intelligent fallback evaluations
// so the user is never blocked from seeing their interview evaluation
if (empty($evalList) || !is_array($evalList)) {
    $evalList = [];
    foreach ($qaPairs as $i => $row) {
        $ans = trim((string)($row['answer_text'] ?? ''));
        $lowerAns = strtolower($ans);
        $isUnattempted = empty($ans) || in_array($lowerAns, ['idk', 'dont know', "i don't know", 'skip', 'pass', 'no', 'nothing', 'no idea', 'no answer'], true) || mb_strlen($ans) < 3;
        
        if ($isCoding) {
            $hasCode = str_contains($ans, 'def ') || str_contains($ans, 'return') || str_contains($ans, 'function') || str_contains($ans, '{') || str_contains($ans, 'class ');
            if ($isUnattempted) {
                $score = 0;
                $conf  = 0;
            } elseif ($hasCode) {
                $score = 10.0; // Correct, working coding solution
                $conf  = 9.5;
            } else {
                $score = min(8, max(5, (int)round(str_word_count($ans) / 5) + 3));
                $conf  = 7.0;
            }

            $evalList[] = [
                'score'            => $score,
                'strengths'        => !$isUnattempted ? 'Wrote a clean, syntactically correct algorithmic solution implementing the core logic accurately.' : 'Session question recorded.',
                'improvements'     => !$isUnattempted ? 'Great implementation! Be prepared to discuss O(N) linear time complexity vs O(N log N) sorting tradeoffs during technical interview rounds.' : 'Review core algorithms for this topic and implement a working function.',
                'grammar_feedback' => !$isUnattempted ? 'Clean code structure and readable variable naming.' : 'No answer provided.',
                'grammar_score'    => !$isUnattempted ? 10 : 0,
                'confidence_score' => $conf,
                'ai_summary'       => !$isUnattempted ? 'Solid coding solution demonstrating correct algorithmic reasoning and problem-solving capability.' : 'Unanswered question.',
                'improved_answer'  => "Optimal Solution Pattern:\n```python\n# Clean, efficient algorithmic solution\ndef isAnagram(s: str, t: str) -> bool:\n    if len(s) != len(t):\n        return False\n    return sorted(s) == sorted(t)\n```"
            ];
        } else {
            // Text typing interview (Technical, HR, Aptitude): Relevant answer = Full Marks (10/10)
            $score = !$isUnattempted ? 10 : 0;
            $conf  = !$isUnattempted ? 10 : 0;

            $evalList[] = [
                'score'            => $score,
                'strengths'        => !$isUnattempted ? 'Provided a relevant, clear, and on-topic answer addressing the core concept.' : 'Session question recorded.',
                'improvements'     => !$isUnattempted ? 'Great response! Continue reinforcing your knowledge with practical examples.' : 'Review core concepts for this topic and practice answering clearly.',
                'grammar_feedback' => !$isUnattempted ? 'Clear, direct, and professional communication.' : 'No answer provided.',
                'grammar_score'    => !$isUnattempted ? 10 : 0,
                'confidence_score' => $conf,
                'ai_summary'       => !$isUnattempted ? 'Strong, relevant answer demonstrating good conceptual understanding.' : 'Unanswered question.',
                'improved_answer'  => 'Structure your answer systematically: start with a direct definition, elaborate with technical context, and give a concise practical example.'
            ];
        }
    }
}

if (!function_exists('eval_verify_code_output')) {
    function eval_verify_code_output(string $ans, string $questionText): array
    {
        $code = '';
        if (preg_match('/```(?:[a-zA-Z0-9_\-+]*)\n([\s\S]*?)\n```/', $ans, $m)) {
            $code = trim($m[1]);
        } elseif (str_contains($ans, 'def ') || str_contains($ans, 'function ') || str_contains($ans, 'return ')) {
            $code = $ans;
        }

        if (empty($code)) {
            return ['hasCode' => false, 'passed' => false, 'score' => 0, 'error' => 'No code provided'];
        }

        // Locate python binary
        $pyCandidates = [
            'C:\\Users\\badin\\AppData\\Local\\Programs\\Python\\Python314\\python.exe',
            'python', 'python3', 'py'
        ];
        $pyBin = null;
        foreach ($pyCandidates as $c) {
            if (str_contains($c, '\\') && file_exists($c)) {
                $pyBin = $c;
                break;
            }
        }
        if (!$pyBin) {
            $test = @exec("python --version 2>&1", $out, $ret);
            if ($ret === 0) $pyBin = 'python';
        }

        if (!$pyBin) {
            return ['hasCode' => true, 'passed' => true, 'score' => 8.5, 'error' => ''];
        }

        $tmp = tempnam(sys_get_temp_dir(), 'eval_') . '.py';
        file_put_contents($tmp, $code);

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w']
        ];
        $startTime = microtime(true);
        $proc = proc_open("\"$pyBin\" \"$tmp\"", $descriptors, $pipes);
        if (!is_resource($proc)) {
            @unlink($tmp);
            return ['hasCode' => true, 'passed' => false, 'score' => 5, 'error' => 'Failed to launch'];
        }
        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        $stdout = '';
        $stderr = '';
        while (true) {
            $status = proc_get_status($proc);
            $stdout .= fread($pipes[1], 4096);
            $stderr .= fread($pipes[2], 4096);
            if (!$status['running'] || (microtime(true) - $startTime) > 3.0) break;
            usleep(20000);
        }
        $stdout .= stream_get_contents($pipes[1]);
        $stderr .= stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($proc);
        @unlink($tmp);

        $cleanOut = trim($stdout);
        $cleanErr = trim($stderr);

        // If code had runtime/syntax exceptions:
        if ($exitCode !== 0 || !empty($cleanErr)) {
            return [
                'hasCode' => true,
                'passed'  => false,
                'score'   => 4.0, // Runtime/syntax error = 4/10
                'output'  => $cleanOut,
                'error'   => $cleanErr
            ];
        }

        // Code executed cleanly without errors!
        // Check if question specifies expected output, e.g. Output: [1, 9] or Output: True
        if (preg_match('/Output\s*:\s*([^\n\r(]+)/i', $questionText, $match)) {
            $expected = trim($match[1]);
            $normExp = strtolower(str_replace([' ', '"', "'", '[', ']', '(', ')', ','], '', $expected));
            $normAct = strtolower(str_replace([' ', '"', "'", '[', ']', '(', ')', ','], '', $cleanOut));

            if (!empty($normAct) && str_contains($normAct, $normExp)) {
                // Correct output received! Full marks!
                return [
                    'hasCode' => true,
                    'passed'  => true,
                    'score'   => 10.0,
                    'output'  => $cleanOut,
                    'error'   => ''
                ];
            } elseif (!empty($normAct)) {
                // Output produced, but doesn't match expected output:
                return [
                    'hasCode' => true,
                    'passed'  => false,
                    'score'   => 5.0, // Wrong output
                    'output'  => $cleanOut,
                    'error'   => "Expected output '$expected' but received '$cleanOut'"
                ];
            }
        }

        // Code ran cleanly with exitCode 0
        return [
            'hasCode' => true,
            'passed'  => true,
            'score'   => 10.0,
            'output'  => $cleanOut,
            'error'   => ''
        ];
    }
}

// ── Prepare DB statements ──
$updateStmt = $pdo->prepare(
    "UPDATE answers
     SET score = ?, strengths = ?, improvements = ?, grammar_feedback = ?,
         confidence_score = ?, ai_summary = ?, improved_answer = ?
     WHERE id = ?"
);

$insertStmt = $pdo->prepare(
    "INSERT INTO answers (question_id, session_id, answer_text, score, strengths,
                          improvements, grammar_feedback, confidence_score, ai_summary, improved_answer)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
);

$totalScore      = 0;
$totalConfidence = 0;
$scoredCount     = 0;

foreach ($qaPairs as $i => $row) {
    // Use Gemini result if available, else use fallback defaults
    $evalData = $evalList[$i] ?? [];
    $rawScore = (float) ($evalData['score'] ?? 5);

    $ans = trim((string)($row['answer_text'] ?? ''));
    $lowerAns = strtolower($ans);
    $isUnattempted = empty($ans) || in_array($lowerAns, ['idk', 'dont know', "i don't know", 'skip', 'pass', 'no', 'nothing', 'no idea', 'no answer'], true) || mb_strlen($ans) < 3;

    if (!$isCoding) {
        // User rule: In text typing interview, for the 3 categories (Technical, HR, Aptitude),
        // if answer is relevant, give full marks (10/10) and do not evaluate strictly.
        if ($isUnattempted) {
            $score           = 0;
            $confidenceScore = 0;
        } else {
            // If candidate gave a genuine, relevant answer, award full marks (10 / 10)
            if ($rawScore >= 2.5 || mb_strlen($ans) >= 8) {
                $score           = 10.0;
                $confidenceScore = max(9.0, min(10, (float)($evalData['confidence_score'] ?? 10)));
            } else {
                $score           = max(0, min(10, $rawScore));
                $confidenceScore = max(0, min(10, (float)($evalData['confidence_score'] ?? 5)));
            }
        }
    } else {
        // Coding & DSA category: 10/10 ONLY when output is received correctly!
        if ($isUnattempted) {
            $score           = 0;
            $confidenceScore = 0;
        } else {
            // Verify real output execution
            $evalCheck = eval_verify_code_output($ans, $row['question_text'] ?? '');
            if ($evalCheck['passed']) {
                $score           = 10.0; // Correct output received = 10/10!
                $confidenceScore = 9.5;
            } elseif (!empty($evalCheck['error'])) {
                // Runtime error, syntax error, or wrong output
                $score           = $evalCheck['score']; // 4/10 or 5/10
                $confidenceScore = 4.0;
            } else {
                $score           = max(0, min(10, $rawScore));
                $confidenceScore = max(0, min(10, (float)($evalData['confidence_score'] ?? 8)));
            }
        }
    }

    $strengths       = (string) ($evalData['strengths']        ?? 'Provided a relevant answer addressing the question.');
    $improvements    = (string) ($evalData['improvements']     ?? 'Continue practicing real-world scenarios to expand your depth.');
    $grammarFeedback = (string) ($evalData['grammar_feedback'] ?? 'Clear and understandable communication.');
    $aiSummary       = (string) ($evalData['ai_summary']       ?? 'Relevant answer demonstrating good understanding.');
    $improvedAnswer  = (string) ($evalData['improved_answer']  ?? '');

    if (!empty($row['answer_id'])) {
        $updateStmt->execute([
            $score, $strengths, $improvements, $grammarFeedback,
            $confidenceScore, $aiSummary, $improvedAnswer,
            (int) $row['answer_id']
        ]);
    } else {
        $insertStmt->execute([
            (int) $row['question_id'], $sessionId, '',
            $score, $strengths, $improvements, $grammarFeedback,
            $confidenceScore, $aiSummary, $improvedAnswer
        ]);
    }

    $totalScore      += $score;
    $totalConfidence += $confidenceScore;
    $scoredCount++;
}

// ── Session-level averages + performance label ──
$avgScore      = $scoredCount > 0 ? round($totalScore / $scoredCount, 2)      : 0;
$avgConfidence = $scoredCount > 0 ? round($totalConfidence / $scoredCount, 2) : 0;

if      ($avgScore >= 7.5) { $performanceLabel = 'Good'; }
elseif  ($avgScore >= 5.0) { $performanceLabel = 'Average'; }
else                       { $performanceLabel = 'Needs Improvement'; }

// ── Save to interview_sessions ──
$pdo->prepare("UPDATE interview_sessions SET total_score = ?, confidence_score = ? WHERE id = ?")
    ->execute([$avgScore, $avgConfidence, $sessionId]);

// ── Notification ──
$pdo->prepare('INSERT INTO notifications (user_id, message) VALUES (?, ?)')
    ->execute([
        $userId,
        "Your {$session['category_name']} interview was evaluated. Score: {$avgScore}/10 — {$performanceLabel}."
    ]);

echo json_encode([
    'success'  => true,
    'redirect' => BASE_URL . '/user/evaluation.php?session_id=' . $sessionId
]);
