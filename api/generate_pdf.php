<?php
/**
 * MODULE 7 — PDF Report Generator
 *
 * Generates a downloadable PDF interview report using FPDF.
 * Requires the user to be logged in and own the session.
 *
 * GET: /api/generate_pdf.php?session_id=X
 * Response: PDF file download
 */

ob_start();
ini_set('display_errors', '0');
error_reporting(E_ALL);

require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../libs/fpdf.php';

$sessionId = isset($_GET['session_id']) ? (int) $_GET['session_id'] : 0;
$userId    = (int) $_SESSION['user_id'];

if ($sessionId <= 0) {
    http_response_code(400);
    die('Invalid session ID.');
}

// -- Load session --
$stmt = $pdo->prepare(
    "SELECT s.*, c.name AS category_name, u.name AS user_name, u.email AS user_email
     FROM interview_sessions s
     JOIN categories c ON c.id = s.category_id
     JOIN users u ON u.id = s.user_id
     WHERE s.id = ? AND s.user_id = ?"
);
$stmt->execute([$sessionId, $userId]);
$session = $stmt->fetch();

if (!$session) {
    http_response_code(403);
    die('Session not found or access denied.');
}

// -- Load all Q+A with evaluation --
$qaStmt = $pdo->prepare(
    "SELECT q.question_text, q.question_order,
            a.answer_text, a.score, a.strengths, a.improvements,
            a.grammar_feedback, a.confidence_score, a.ai_summary,
            a.improved_answer
     FROM questions q
     LEFT JOIN answers a ON a.question_id = q.id
     WHERE q.session_id = ?
     ORDER BY q.question_order"
);
$qaStmt->execute([$sessionId]);
$results = $qaStmt->fetchAll();

// -- Aggregate stats --
$totalScore      = 0;
$scoredCount     = 0;
$totalWords      = 0;
$wordAnswers     = 0;
$allStrengths    = [];
$allImprovements = [];

foreach ($results as $r) {
    if ($r['score'] !== null) {
        $totalScore  += (float) $r['score'];
        $scoredCount++;
    }
    $text = trim((string)($r['answer_text'] ?? ''));
    if ($text !== '') {
        $totalWords += str_word_count(strip_tags($text));
        $wordAnswers++;
    }
    if (!empty($r['strengths']))    $allStrengths[]    = trim($r['strengths']);
    if (!empty($r['improvements'])) $allImprovements[] = trim($r['improvements']);
}

$avgScore      = $scoredCount > 0 ? round($totalScore / $scoredCount, 1) : 0;
$avgConfidence = $wordAnswers > 0 ? min(10, round(($totalWords / $wordAnswers) / 8, 1)) : 0;
$answeredCount = count(array_filter($results, fn($r) => !empty(trim((string)($r['answer_text'] ?? '')))));
$questionCount = count($results);

if ($avgScore >= 7.5)      { $perfLabel = 'Good';              $perfColor = [16, 149, 93]; }
elseif ($avgScore >= 5.0)  { $perfLabel = 'Average';           $perfColor = [217, 119, 6]; }
else                       { $perfLabel = 'Needs Improvement'; $perfColor = [225, 29, 72]; }

$dateLabel = date('d F Y', strtotime($session['completed_at'] ?? $session['started_at']));

// -- FPDF Custom Class --
class InterviewReportPDF extends FPDF
{
    public string $reportTitle = 'AI Interview Report';
    public string $userName    = '';
    public string $dateLabel   = '';

    // Color palette - Clean Executive White / Daylight Theme
    public array $clrBg        = [255, 255, 255]; // Pure white background
    public array $clrPrimary   = [79, 70, 229];   // Indigo #4f46e5 (rich, modern accent)
    public array $clrCyan      = [2, 132, 199];   // Deep sky blue #0284c7
    public array $clrText      = [15, 23, 42];    // Slate 900 #0f172a (high contrast dark text)
    public array $clrTextMuted = [71, 85, 105];  // Slate 600 #475569
    public array $clrMist      = [100, 116, 139]; // Slate 500 #64748b
    public array $clrCard      = [248, 250, 252]; // Slate 50 #f8fafc (clean subtle card surface)
    public array $clrBorder    = [226, 232, 240]; // Slate 200 #e2e8f0 (crisp card border)
    public array $clrGood      = [16, 149, 93];   // Forest emerald #10955d
    public array $clrAvg       = [217, 119, 6];   // Warm amber #d97706
    public array $clrBad       = [225, 29, 72];   // Rose red #e11d48

    public function Header()
    {
        // Clean white header background
        $this->SetFillColor(255, 255, 255);
        $this->Rect(0, 0, 210, 26, 'F');

        // Gradient stripe at very top
        $this->SetFillColor(...$this->clrPrimary);
        $this->Rect(0, 0, 105, 3, 'F');
        $this->SetFillColor(...$this->clrCyan);
        $this->Rect(105, 0, 105, 3, 'F');

        // Brand
        $this->SetFont('Helvetica', 'B', 12);
        $this->SetTextColor(...$this->clrText);
        $this->SetXY(14, 8);
        $this->Cell(80, 6, 'AI Interview Prep', 0, 0, 'L');

        // Right metadata
        $this->SetFont('Helvetica', 'B', 8);
        $this->SetTextColor(...$this->clrPrimary);
        $this->SetXY(110, 7.5);
        $this->Cell(86, 4, $this->reportTitle, 0, 0, 'R');

        $this->SetFont('Helvetica', '', 8);
        $this->SetTextColor(...$this->clrMist);
        $this->SetXY(110, 12.5);
        $this->Cell(86, 4, $this->dateLabel, 0, 0, 'R');

        // Subtle divider under header
        $this->SetDrawColor(...$this->clrBorder);
        $this->SetLineWidth(0.3);
        $this->Line(14, 21, 196, 21);

        $this->Ln(12);
    }

    public function Footer()
    {
        $this->SetY(-14);
        $this->SetFillColor(255, 255, 255);
        $this->Rect(0, $this->GetY() - 2, 210, 20, 'F');

        // Divider above footer
        $this->SetDrawColor(...$this->clrBorder);
        $this->SetLineWidth(0.3);
        $this->Line(14, $this->GetY() - 2, 196, $this->GetY() - 2);

        // Gradient stripe at very bottom
        $this->SetFillColor(...$this->clrPrimary);
        $this->Rect(0, 295, 105, 2, 'F');
        $this->SetFillColor(...$this->clrCyan);
        $this->Rect(105, 295, 105, 2, 'F');

        $this->SetFont('Helvetica', 'I', 7.5);
        $this->SetTextColor(...$this->clrMist);
        $this->Cell(0, 10, 'AI Interview Prep Platform  |  Page ' . $this->PageNo() . '  |  Confidential Report', 0, 0, 'C');
    }

    public function PageBackground()
    {
        $this->SetFillColor(255, 255, 255);
        $this->Rect(0, 0, 210, 297, 'F');
    }

    public function RoundedRect($x, $y, $w, $h, $r, $style = '')
    {
        $k  = $this->k;
        $hp = $this->h;
        $op = ($style === 'F') ? 'f' : (($style === 'FD' || $style === 'DF') ? 'B' : 'S');
        $arc = 4 / 3 * (M_SQRT2 - 1);
        $this->_out(sprintf('%.2F %.2F m', ($x + $r) * $k, ($hp - $y) * $k));
        $xc = $x + $w - $r; $yc = $y + $r;
        $this->_out(sprintf('%.2F %.2F l', $xc * $k, ($hp - $y) * $k));
        $this->_ArcR($xc + $r * $arc, $yc - $r, $xc + $r, $yc - $r * $arc, $xc + $r, $yc);
        $xc = $x + $w - $r; $yc = $y + $h - $r;
        $this->_out(sprintf('%.2F %.2F l', ($x + $w) * $k, ($hp - $yc) * $k));
        $this->_ArcR($xc + $r, $yc + $r * $arc, $xc + $r * $arc, $yc + $r, $xc, $yc + $r);
        $xc = $x + $r; $yc = $y + $h - $r;
        $this->_out(sprintf('%.2F %.2F l', $xc * $k, ($hp - ($y + $h)) * $k));
        $this->_ArcR($xc - $r * $arc, $yc + $r, $xc - $r, $yc + $r * $arc, $xc - $r, $yc);
        $xc = $x + $r; $yc = $y + $r;
        $this->_out(sprintf('%.2F %.2F l', $x * $k, ($hp - $yc) * $k));
        $this->_ArcR($xc - $r, $yc - $r * $arc, $xc - $r * $arc, $yc - $r, $xc, $yc - $r);
        $this->_out($op);
    }

    private function _ArcR($x1, $y1, $x2, $y2, $x3, $y3)
    {
        $h = $this->h;
        $this->_out(sprintf('%.2F %.2F %.2F %.2F %.2F %.2F c',
            $x1 * $this->k, ($h - $y1) * $this->k,
            $x2 * $this->k, ($h - $y2) * $this->k,
            $x3 * $this->k, ($h - $y3) * $this->k));
    }

    public function Dot($x, $y, $r, array $color)
    {
        $this->SetFillColor(...$color);
        $arc = $r * 4 / 3 * (M_SQRT2 - 1);
        $k   = $this->k;
        $h   = $this->h;
        $this->_out(sprintf('%.2F %.2F m', ($x + $r) * $k, ($h - $y) * $k));
        $this->_out(sprintf('%.2F %.2F %.2F %.2F %.2F %.2F c', ($x+$r)*$k,($h-($y-$arc))*$k, ($x+$arc)*$k,($h-($y-$r))*$k, $x*$k,($h-($y-$r))*$k));
        $this->_out(sprintf('%.2F %.2F %.2F %.2F %.2F %.2F c', ($x-$arc)*$k,($h-($y-$r))*$k, ($x-$r)*$k,($h-($y-$arc))*$k, ($x-$r)*$k,($h-$y)*$k));
        $this->_out(sprintf('%.2F %.2F %.2F %.2F %.2F %.2F c', ($x-$r)*$k,($h-($y+$arc))*$k, ($x-$arc)*$k,($h-($y+$r))*$k, $x*$k,($h-($y+$r))*$k));
        $this->_out(sprintf('%.2F %.2F %.2F %.2F %.2F %.2F c', ($x+$arc)*$k,($h-($y+$r))*$k, ($x+$r)*$k,($h-($y+$arc))*$k, ($x+$r)*$k,($h-$y)*$k));
        $this->_out('f');
    }

    public function ProgressBar($x, $y, $w, $score, $max = 10)
    {
        $h   = 3.5;
        $pct = min(1.0, max(0.0, ($max > 0 ? $score / $max : 0)));
        $this->SetFillColor(...$this->clrBorder);
        $this->RoundedRect($x, $y, $w, $h, 1.5, 'F');
        if ($pct > 0) {
            if ($score >= 7)     $this->SetFillColor(...$this->clrGood);
            elseif ($score >= 5) $this->SetFillColor(...$this->clrAvg);
            else                 $this->SetFillColor(...$this->clrBad);
            $this->RoundedRect($x, $y, max(3, $w * $pct), $h, 1.5, 'F');
        }
    }

    public function SectionHeading(string $text)
    {
        $this->Ln(3.5);
        $this->SetFillColor(...$this->clrPrimary);
        $this->RoundedRect(14, $this->GetY() + 0.8, 3.8, 5.2, 1, 'F');
        $this->SetFont('Helvetica', 'B', 11);
        $this->SetTextColor(...$this->clrText);
        $this->SetX(21.5);
        $this->Cell(0, 6.5, $text, 0, 1, 'L');
        $this->Ln(1);
    }

    public function ScoreBadge($x, $y, string $label, string $value, string $outof, array $color)
    {
        $w = 43; $h = 28;

        // Card surface & crisp border
        $this->SetFillColor(...$this->clrCard);
        $this->SetDrawColor(...$this->clrBorder);
        $this->SetLineWidth(0.35);
        $this->RoundedRect($x, $y, $w, $h, 3.5, 'DF');

        // Top accent bar
        $this->SetFillColor(...$color);
        $this->RoundedRect($x, $y, $w, 3, 1.5, 'F');

        // Auto-scale font for long values
        $fontSize = 15;
        if (strlen($value) > 12)     $fontSize = 8.5;
        elseif (strlen($value) > 7)  $fontSize = 10.5;

        $this->SetFont('Helvetica', 'B', $fontSize);
        $this->SetTextColor(...$color);
        $valueY = $fontSize < 11 ? $y + 4.5 : $y + 5;
        $this->SetXY($x, $valueY);
        $this->Cell($w, 8, $value, 0, 0, 'C');

        if ($outof) {
            $this->SetFont('Helvetica', '', 7.5);
            $this->SetTextColor(...$this->clrMist);
            $this->SetXY($x, $y + 13.5);
            $this->Cell($w, 4, '/' . $outof, 0, 0, 'C');
        }

        $this->SetFont('Helvetica', 'B', 6.5);
        $this->SetTextColor(...$this->clrTextMuted);
        $this->SetXY($x, $y + 19.5);
        $this->Cell($w, 5, strtoupper($label), 0, 0, 'C');
    }

    public function GetMultiCellHeight(float $w, float $lineHeight, string $text): float
    {
        $s = str_replace("\r", '', (string)$text);
        if ($s === '') return $lineHeight;

        if (isset($this->CurrentFont['cw'])) {
            $cw = &$this->CurrentFont['cw'];
            if ($w == 0) $w = $this->w - $this->rMargin - $this->x;
            $wmax = ($w - 2 * $this->cMargin) * 1000 / max(1, $this->FontSize);
            $nb = strlen($s);
            if ($nb > 0 && $s[$nb - 1] == "\n") $nb--;
            $sep = -1;
            $i = 0; $j = 0; $l = 0; $nl = 1;
            while ($i < $nb) {
                $c = $s[$i];
                if ($c == "\n") {
                    $i++;
                    $sep = -1;
                    $j = $i;
                    $l = 0;
                    $nl++;
                    continue;
                }
                if ($c == ' ') $sep = $i;
                $l += $cw[$c] ?? 500;
                if ($l > $wmax) {
                    if ($sep == -1) {
                        if ($i == $j) $i++;
                    } else {
                        $i = $sep + 1;
                    }
                    $sep = -1;
                    $j = $i;
                    $l = 0;
                    $nl++;
                } else {
                    $i++;
                }
            }
            return $nl * $lineHeight;
        }

        // Fallback approximation
        $fontSize = max(1, $this->FontSizePt ?: 9);
        $approxCharWidth = $fontSize * 0.18;
        $usableWidth = max(20, $w - 4);
        $charsPerLine = max(10, floor($usableWidth / $approxCharWidth));
        $lines = max(1, ceil(strlen($s) / $charsPerLine));
        return $lines * $lineHeight;
    }

    public function SafeText(string $text): string
    {
        return iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', strip_tags($text)) ?: preg_replace('/[^\x20-\x7E]/', '?', $text);
    }
}

// -- Build PDF --
$pdf = new InterviewReportPDF('P', 'mm', 'A4');
$pdf->reportTitle = 'Interview Evaluation Report';
$pdf->userName    = $session['user_name'];
$pdf->dateLabel   = $dateLabel;
$pdf->SetAuthor('AI Interview Prep Platform');
$pdf->SetTitle('Interview Report - ' . $session['category_name']);
$pdf->SetSubject('AI Evaluation Report Session #' . $sessionId);
$pdf->SetCreator('AI Interview Prep');
$pdf->SetCompression(true);
$pdf->SetMargins(0, 0, 0);
$pdf->SetAutoPageBreak(true, 20);

$pdf->AddPage();
$pdf->PageBackground();

// -- Hero --
$pdf->SetY(28);
$pdf->SetFont('Helvetica', 'B', 19);
$pdf->SetTextColor(...$pdf->clrText);
$pdf->Cell(0, 9, 'Interview Evaluation Report', 0, 1, 'C');

$pdf->SetFont('Helvetica', 'B', 9);
$pdf->SetTextColor(...$pdf->clrPrimary);
$companySuffix = (!empty($session['company_name']) && strcasecmp($session['company_name'], 'General') !== 0)
    ? '  |  Target: ' . $pdf->SafeText($session['company_name'])
    : '';
$pdf->Cell(0, 6, $pdf->SafeText($session['category_name']) . $companySuffix . '  |  Session #' . $sessionId . '  |  ' . $dateLabel, 0, 1, 'C');

$pdf->SetFont('Helvetica', '', 8);
$pdf->SetTextColor(...$pdf->clrTextMuted);
$pdf->Cell(0, 5, 'Prepared for: ' . $pdf->SafeText($session['user_name']) . '   (' . $pdf->SafeText($session['user_email']) . ')', 0, 1, 'C');
$pdf->Ln(4);

// Divider
$pdf->SetDrawColor(...$pdf->clrBorder);
$pdf->SetLineWidth(0.3);
$pdf->Line(14, $pdf->GetY(), 196, $pdf->GetY());
$pdf->Ln(5);

// -- Score Badges --
$bw = 43; $gap = 2.33; $startX = 14;
$badgeY = $pdf->GetY();
$oColor = $avgScore >= 7.5 ? $pdf->clrGood : ($avgScore >= 5 ? $pdf->clrAvg : $pdf->clrBad);
$pdf->ScoreBadge($startX,                    $badgeY, 'Overall Score', number_format($avgScore, 1), '10',          $oColor);
$pdf->ScoreBadge($startX + $bw + $gap,       $badgeY, 'Performance',   $perfLabel,                  '',            $perfColor);
$pdf->ScoreBadge($startX + ($bw+$gap) * 2,  $badgeY, 'Confidence',    number_format($avgConfidence,1), '10',     $pdf->clrCyan);
$pdf->ScoreBadge($startX + ($bw+$gap) * 3,  $badgeY, 'Answered',      $answeredCount . '/' . $questionCount, 'Qs',$pdf->clrPrimary);
$pdf->SetY($badgeY + 31);

// -- AI Suggestions --
$pdf->SectionHeading('AI Suggestions');
$aiSuggestions = array_filter(array_column($results, 'ai_summary'));
$suggText = !empty($aiSuggestions)
    ? $pdf->SafeText(implode(' ', array_slice($aiSuggestions, 0, 3)))
    : 'Review your detailed feedback below and practice regularly to improve your interview performance.';

$cardW = 182;
$textW = 170;
$lineH = 4.2;
$pdf->SetFont('Helvetica', 'I', 8.5);
$calcH = $pdf->GetMultiCellHeight($textW, $lineH, $suggText);
$boxH  = max(11, $calcH + 5);

$y = $pdf->GetY();
if ($y + $boxH > 270) {
    $pdf->AddPage();
    $pdf->PageBackground();
    $y = $pdf->GetY();
}

// Background & purple border
$pdf->SetFillColor(248, 247, 255); // Soft lavender tint #f8f7ff
$pdf->SetDrawColor(221, 214, 254); // Subtle purple border #ddd6fe
$pdf->SetLineWidth(0.3);
$pdf->RoundedRect(14, $y, $cardW, $boxH, 3, 'DF');

// Left accent stripe
$pdf->SetFillColor(...$pdf->clrPrimary);
$pdf->RoundedRect(14, $y, 3.5, $boxH, 1.5, 'F');

// Text
$pdf->SetFont('Helvetica', 'I', 8.5);
$pdf->SetTextColor(30, 41, 59); // Crisp readable dark slate
$pdf->SetXY(21.5, $y + 2.5);
$pdf->MultiCell($textW, $lineH, $suggText, 0, 'L');
$pdf->SetY($y + $boxH + 3);

// -- Key Strengths --
$pdf->SectionHeading('Key Strengths');
if (!empty($allStrengths)) {
    foreach (array_slice($allStrengths, 0, 4) as $s) {
        $safeS = $pdf->SafeText($s);
        $textW = 165;
        $lineH = 4.2;
        $pdf->SetFont('Helvetica', '', 8.5);
        $calcH = $pdf->GetMultiCellHeight($textW, $lineH, $safeS);
        $boxH  = max(8.5, $calcH + 4);

        if ($pdf->GetY() + $boxH > 270) {
            $pdf->AddPage();
            $pdf->PageBackground();
        }

        $y = $pdf->GetY();
        $pdf->SetFillColor(240, 253, 244); // Very soft green tint #f0fdf4
        $pdf->SetDrawColor(187, 247, 208); // Light green border #bbf7d0
        $pdf->SetLineWidth(0.3);
        $pdf->RoundedRect(14, $y, 182, $boxH, 3, 'DF');

        // Green dot indicator
        $dotY = $calcH > $lineH ? $y + 4.5 : $y + ($boxH / 2);
        $pdf->Dot(20.5, $dotY, 1.6, $pdf->clrGood);

        // Text
        $pdf->SetFont('Helvetica', '', 8.5);
        $pdf->SetTextColor(20, 83, 45); // Dark forest slate - high contrast
        $pdf->SetXY(25, $y + 2);
        $pdf->MultiCell($textW, $lineH, $safeS, 0, 'L');

        $pdf->SetY($y + $boxH + 2);
    }
} else {
    $pdf->SetFont('Helvetica', 'I', 8.5);
    $pdf->SetTextColor(...$pdf->clrMist);
    $pdf->SetX(18);
    $pdf->Cell(0, 5, 'No strengths data available.', 0, 1);
}
$pdf->Ln(2);

// -- Areas to Improve --
$pdf->SectionHeading('Areas to Improve');
if (!empty($allImprovements)) {
    foreach (array_slice($allImprovements, 0, 4) as $s) {
        $safeS = $pdf->SafeText($s);
        $textW = 165;
        $lineH = 4.2;
        $pdf->SetFont('Helvetica', '', 8.5);
        $calcH = $pdf->GetMultiCellHeight($textW, $lineH, $safeS);
        $boxH  = max(8.5, $calcH + 4);

        if ($pdf->GetY() + $boxH > 270) {
            $pdf->AddPage();
            $pdf->PageBackground();
        }

        $y = $pdf->GetY();
        $pdf->SetFillColor(255, 251, 235); // Soft warm amber tint #fffbeb
        $pdf->SetDrawColor(254, 240, 138); // Light amber border #fef08a
        $pdf->SetLineWidth(0.3);
        $pdf->RoundedRect(14, $y, 182, $boxH, 3, 'DF');

        // Amber dot indicator
        $dotY = $calcH > $lineH ? $y + 4.5 : $y + ($boxH / 2);
        $pdf->Dot(20.5, $dotY, 1.6, $pdf->clrAvg);

        // Text
        $pdf->SetFont('Helvetica', '', 8.5);
        $pdf->SetTextColor(120, 53, 15); // Dark amber/brown slate - crystal clear contrast
        $pdf->SetXY(25, $y + 2);
        $pdf->MultiCell($textW, $lineH, $safeS, 0, 'L');

        $pdf->SetY($y + $boxH + 2);
    }
} else {
    $pdf->SetFont('Helvetica', 'I', 8.5);
    $pdf->SetTextColor(...$pdf->clrMist);
    $pdf->SetX(18);
    $pdf->Cell(0, 5, 'No improvement data available.', 0, 1);
}
$pdf->Ln(3);

// -- Tips Page --
if ($pdf->GetY() + 65 > 260) {
    $pdf->AddPage();
    $pdf->PageBackground();
}

$pdf->SectionHeading('Next Steps & Recommendations');

$tips = [];
if ($avgScore < 5) {
    $tips[] = ['Study the Fundamentals',    'Review core concepts. Focus on understanding the "why" behind each topic, not just memorizing answers.', $pdf->clrBad];
    $tips[] = ['Practice Daily',            'Schedule 30 min of daily mock interview practice. Consistency beats marathon sessions.', $pdf->clrAvg];
    $tips[] = ['Use Model Answers',         'Study the model answers provided on the platform and practice delivering them in your own words.', $pdf->clrCyan];
} elseif ($avgScore < 7.5) {
    $tips[] = ['Deepen Your Knowledge',     'Your answers are good but need more depth. Add examples, metrics, and technical specifics.', $pdf->clrAvg];
    $tips[] = ['Improve Communication',     'Use the STAR method (Situation, Task, Action, Result) to structure behavioral answers.', $pdf->clrGood];
    $tips[] = ['Target Weak Areas',         'Focus on questions where you scored below 6/10 and practice those topics intensively.', $pdf->clrCyan];
} else {
    $tips[] = ['Maintain Excellence',       'Great performance! Keep refining with more industry-specific examples.', $pdf->clrGood];
    $tips[] = ['Practice Edge Cases',       'Try harder questions and different interview categories to broaden your skills.', $pdf->clrPrimary];
    $tips[] = ['Company-Specific Mode',     'Try Company Specific interview mode on the platform to prepare for your target company.', $pdf->clrCyan];
}
$tips[] = ['Clear Communication',         'Read answers aloud before interviews. Structured communication is as important as knowledge.', [109, 40, 217]];
$tips[] = ['Retake This Interview',        'Use AI Interview Prep to retake this category and track your improvement over time.', $pdf->clrPrimary];

foreach ($tips as [$title, $desc, $color]) {
    $safeTitle = $pdf->SafeText($title);
    $safeDesc  = $pdf->SafeText($desc);
    $textW     = 168;
    $lineH     = 4.0;
    $pdf->SetFont('Helvetica', '', 8);
    $calcH     = $pdf->GetMultiCellHeight($textW, $lineH, $safeDesc);
    $boxH      = max(15, 6 + $calcH + 4);

    if ($pdf->GetY() + $boxH > 270) {
        $pdf->AddPage();
        $pdf->PageBackground();
    }

    $tipY = $pdf->GetY();
    $pdf->SetFillColor(248, 250, 252); // Crisp soft slate #f8fafc
    $pdf->SetDrawColor(226, 232, 240); // #e2e8f0
    $pdf->SetLineWidth(0.3);
    $pdf->RoundedRect(14, $tipY, 182, $boxH, 3.5, 'DF');

    // Left indicator stripe
    $pdf->SetFillColor(...$color);
    $pdf->RoundedRect(14, $tipY, 3.5, $boxH, 1.5, 'F');

    // Title
    $pdf->SetFont('Helvetica', 'B', 8.5);
    $pdf->SetTextColor(...$color);
    $pdf->SetXY(22, $tipY + 2.5);
    $pdf->Cell(0, 5, $safeTitle, 0, 1, 'L');

    // Desc
    $pdf->SetFont('Helvetica', '', 8);
    $pdf->SetTextColor(51, 65, 85); // Slate 700 - high contrast
    $pdf->SetX(22);
    $pdf->MultiCell($textW, $lineH, $safeDesc, 0, 'L');

    $pdf->SetY($tipY + $boxH + 2.5);
}

$pdf->Ln(3);

// CTA block
if ($pdf->GetY() + 32 > 270) {
    $pdf->AddPage();
    $pdf->PageBackground();
}

$ctaY = $pdf->GetY();
$pdf->SetFillColor(245, 243, 255); // Soft lavender/indigo #f5f3ff
$pdf->SetDrawColor(199, 210, 254); // Subtle border #c7d2fe
$pdf->SetLineWidth(0.35);
$pdf->RoundedRect(14, $ctaY, 182, 28, 5, 'DF');

// Top stripe
$pdf->SetFillColor(...$pdf->clrPrimary);
$pdf->RoundedRect(14, $ctaY, 182, 3, 1.5, 'F');

$pdf->SetFont('Helvetica', 'B', 11.5);
$pdf->SetTextColor(...$pdf->clrText);
$pdf->SetXY(14, $ctaY + 6.5);
$pdf->Cell(182, 6, 'Ready for another round? Start your next session!', 0, 1, 'C');

$pdf->SetFont('Helvetica', '', 8.5);
$pdf->SetTextColor(...$pdf->clrTextMuted);
$pdf->SetX(14);
$pdf->Cell(182, 5, 'Visit AI Interview Prep and keep improving your skills daily.', 0, 1, 'C');

$pdf->SetFont('Helvetica', 'B', 9);
$pdf->SetTextColor(...$pdf->clrPrimary);
$pdf->SetX(14);
$siteUrl = defined('BASE_URL') ? BASE_URL : 'AI Interview Prep Platform';
$pdf->Cell(182, 7, $pdf->SafeText($siteUrl), 0, 1, 'C');

// -- Output --
ob_clean();
$filename = 'Interview_Report_Session' . $sessionId . '_' . date('Ymd') . '.pdf';
$pdf->Output('D', $filename);
exit;
