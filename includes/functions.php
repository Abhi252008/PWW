<?php
/**
 * Shared helper functions for Module 2 (Authentication) — and reused by
 * every later module. Include this after config/app_config.php and after
 * a session has been started.
 */

/**
 * Shorthand for htmlspecialchars() — use this around every piece of
 * user-supplied data that gets echoed into HTML, to prevent XSS.
 */
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Returns the CSRF token for the current session, generating one the
 * first time it's called. Print this into a hidden field on every form:
 *   <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
 */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Checks a submitted csrf_token field against the one in the session.
 * hash_equals() is used instead of === to avoid timing-attack leaks.
 */
function verify_csrf(?string $submittedToken): bool
{
    return !empty($_SESSION['csrf_token'])
        && !empty($submittedToken)
        && hash_equals($_SESSION['csrf_token'], $submittedToken);
}

/**
 * Stores a one-time flash message in the session (survives exactly one
 * redirect). $type should be one of: success, error, info.
 */
function set_flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

/**
 * Fetches and clears the pending flash message, if any. Called once by
 * includes/flash.php on every page render.
 */
function get_flash(): ?array
{
    if (empty($_SESSION['flash'])) {
        return null;
    }
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
}

/**
 * Sends a Location header to a BASE_URL-relative path and stops execution.
 * Always call this BEFORE any HTML has been echoed / before header.php
 * is included, since headers can't be sent after output has started.
 */
function redirect(string $path): void
{
    header('Location: ' . BASE_URL . $path);
    exit;
}

/**
 * Simple password strength check: at least 8 characters, at least one
 * letter and one number. Good enough for a student placement-prep
 * project without being annoying to type on a phone.
 */
function is_strong_password(string $password): bool
{
    return strlen($password) >= 8
        && preg_match('/[A-Za-z]/', $password)
        && preg_match('/[0-9]/', $password);
}

/**
 * Returns 1-2 uppercase initials from a full name, for the fallback
 * avatar circle shown when a user hasn't uploaded a profile picture.
 */
function initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name));
    $initials = strtoupper(substr($parts[0] ?? '', 0, 1) . substr($parts[count($parts) - 1] ?? '', 0, 1));
    return $initials !== '' ? $initials : '?';
}

/**
 * Renders either the user's uploaded profile picture or a fallback
 * initials circle. $profilePicture is the DB value (may be the default
 * placeholder path, which is never actually written to disk).
 */
function render_avatar(?string $profilePicture, string $name, string $size = '44px'): string
{
    $isRealUpload = $profilePicture
        && $profilePicture !== 'images/default-avatar.png'
        && file_exists(__DIR__ . '/../' . $profilePicture);

    if ($isRealUpload) {
        return '<img src="' . BASE_URL . '/' . e($profilePicture) . '" alt="Profile picture" '
            . 'style="width:' . $size . ';height:' . $size . ';border-radius:50%;object-fit:cover;">';
    }

    return '<div class="avatar-circle" style="width:' . $size . ';height:' . $size . ';font-size:calc(' . $size . ' * 0.4);">'
        . e(initials($name)) . '</div>';
}

/**
 * Generic, safe file-upload handler: checks the upload succeeded, the
 * MIME type is on the allow-list, and the size is under the limit, then
 * moves it into $destDir under uploads/ with a random, collision-proof
 * filename (never trusts the client's original filename).
 *
 * @param string $fieldName   Name of the <input type="file"> field.
 * @param array  $allowedMime Map of allowed MIME type => file extension, e.g. ['image/png' => 'png'].
 * @param int    $maxBytes    Maximum allowed file size in bytes.
 * @param string $destDir     Absolute path to the destination directory (must already exist).
 * @param string $publicPrefix Path prefix to store in the DB / use in URLs, e.g. 'uploads/profiles'.
 * @return array ['success' => bool, 'path' => string|null, 'error' => string|null]
 */
function handle_upload(string $fieldName, array $allowedMime, int $maxBytes, string $destDir, string $publicPrefix): array
{
    if (!isset($_FILES[$fieldName]) || $_FILES[$fieldName]['error'] === UPLOAD_ERR_NO_FILE) {
        return ['success' => false, 'path' => null, 'error' => null]; // nothing submitted — not an error
    }

    $file = $_FILES[$fieldName];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'path' => null, 'error' => 'Upload failed. Please try again.'];
    }

    if ($file['size'] > $maxBytes) {
        return ['success' => false, 'path' => null, 'error' => 'File is too large (max ' . round($maxBytes / 1048576, 1) . ' MB).'];
    }

    // Verify the ACTUAL file content type, never trust the client-sent one.
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!isset($allowedMime[$mime])) {
        return ['success' => false, 'path' => null, 'error' => 'Unsupported file type.'];
    }

    $extension = $allowedMime[$mime];
    $filename = bin2hex(random_bytes(16)) . '.' . $extension;
    $destPath = rtrim($destDir, '/') . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        return ['success' => false, 'path' => null, 'error' => 'Could not save the uploaded file.'];
    }

    return ['success' => true, 'path' => rtrim($publicPrefix, '/') . '/' . $filename, 'error' => null];
}

/**
 * MODULE 5 — AI Mock Interview Engine
 *
 * Generates all of a session's questions in one Gemini call and inserts
 * them into the `questions` table. Called once, the first time a user
 * opens user/interview.php for a session that has no questions yet.
 *
 * @return array ['success' => bool, 'error' => string|null]
 */
function generate_questions_for_session(PDO $pdo, int $sessionId, string $categoryName, string $difficulty, int $totalQuestions, string $companyName = 'General'): array
{
    require_once __DIR__ . '/../config/gemini_config.php';

    // 1. Optionally check if admin has curated questions in question_bank for this category
    $adminQuestions = [];
    try {
        $catStmt = $pdo->prepare("SELECT id FROM categories WHERE name = ? LIMIT 1");
        $catStmt->execute([$categoryName]);
        $catId = $catStmt->fetchColumn();
        if ($catId) {
            $qbStmt = $pdo->prepare("SELECT question_text FROM question_bank WHERE category_id = ? ORDER BY RAND() LIMIT ?");
            $qbStmt->bindValue(1, (int)$catId, PDO::PARAM_INT);
            $qbStmt->bindValue(2, min(2, $totalQuestions), PDO::PARAM_INT);
            $qbStmt->execute();
            $adminQuestions = $qbStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
        }
    } catch (\Throwable $e) { /* ignore if table missing */ }

    // 2. Use the dedicated GeminiPrompts engine with company intelligence
    $prompt    = GeminiPrompts::buildQuestionPrompt($categoryName, $difficulty, $totalQuestions, $companyName);
    $result    = callGemini($prompt, 90, 2, GeminiPrompts::getSystemInstruction(), 0.7);
    $questions = null;

    if ($result['success']) {
        $questions = extractJsonFromGemini($result['text']);
    }

    // Blend admin questions if available
    if (!empty($adminQuestions) && is_array($questions)) {
        $questions = array_merge($adminQuestions, $questions);
        $questions = array_unique($questions);
    }

    // Resilient Fallback: If AI API experiences downtime or high demand, use curated questions
    if (!is_array($questions) || count($questions) < $totalQuestions) {
        $fallback = get_fallback_questions($categoryName, $difficulty, $totalQuestions, $companyName);
        if (!is_array($questions) || empty($questions)) {
            $questions = !empty($adminQuestions) ? array_merge($adminQuestions, $fallback) : $fallback;
        } else {
            $needed = $totalQuestions - count($questions);
            $questions = array_merge($questions, array_slice($fallback, 0, $needed));
        }
    }

    // Always shuffle questions array so order is fresh and randomized across sessions
    shuffle($questions);

    $stmt = $pdo->prepare(
        'INSERT INTO questions (session_id, question_text, question_order) VALUES (?, ?, ?)'
    );

    $order = 1;
    foreach (array_slice($questions, 0, $totalQuestions) as $q) {
        $stmt->execute([$sessionId, trim((string) $q), $order]);
        $order++;
    }

    return ['success' => true, 'error' => null];
}

/**
 * Curated, diverse fallback question banks to guarantee uninterrupted mock interviews
 * with rich shuffling, company-specific banks, and difficulty-aware question pools.
 */
function get_fallback_questions(string $categoryName, string $difficulty, int $totalQuestions, string $companyName = 'General'): array
{
    $cat  = strtolower($categoryName);
    $diff = strtolower($difficulty);
    $comp = strtolower(trim($companyName));
    $isBeginner  = in_array($diff, ['beginner', 'entry', 'junior'], true);
    $isDifficult = in_array($diff, ['advanced', 'senior', 'expert', 'difficult', 'hard'], true);

    $isGoogle  = str_contains($comp, 'google');
    $isAmazon  = str_contains($comp, 'amazon');
    $isMsft    = str_contains($comp, 'microsoft');
    $isService = str_contains($comp, 'tcs') || str_contains($comp, 'infosys') || str_contains($comp, 'wipro') || str_contains($comp, 'accenture') || str_contains($comp, 'cognizant');

    // 1. Aptitude & Logical Reasoning Category
    if (str_contains($cat, 'aptitude') || str_contains($cat, 'logic')) {
        if ($isBeginner) {
            if ($isService) {
                // TCS, Infosys, Wipro, Accenture placement pattern
                $bank = [
                    "A train 180 meters long is running at a speed of 72 km/h. How many seconds will it take to pass an electric pole?",
                    "A sum of money doubles itself in 5 years at simple interest. In how many years will it become 4 times itself at the same rate?",
                    "If the cost price of a pen is $10 and it is sold for $12, what is the percentage profit?",
                    "Find the next number in the arithmetic sequence: 5, 11, 17, 23, __?",
                    "In a row of 25 students facing North, Rohan is 10th from the left. What is his position from the right?",
                    "The average of five numbers is 20. If one number is removed, the average becomes 18. What was the removed number?",
                    "If A can complete a piece of work in 10 days and B can do it in 15 days, in how many days can they complete it working together?",
                    "If 12 pens cost $36, how much will 20 pens cost at the same rate?",
                    "A father is currently four times as old as his son. If the son is 8 years old, what is the father's age?",
                    "What is 15% of 240?"
                ];
            } elseif ($isGoogle) {
                $bank = [
                    "What is the probability of obtaining a total sum greater than 8 when two standard six-sided dice are rolled?",
                    "A car travels 120 km in 2 hours. What is its speed in m/s?",
                    "If an investment increases by 10% in the first year and 20% in the second year, what is the total percentage gain?",
                    "Find the next term in the geometric series: 3, 6, 12, 24, __?",
                    "In a class of 50 students, 30 like Math, 25 like Coding, and 10 like both. How many students like neither?",
                    "If five machines take 5 minutes to make 5 widgets, how long would it take 100 machines to make 100 widgets?",
                    "A container has 3 red and 2 green balls. If one ball is picked at random, what is the probability that it is red?"
                ];
            } elseif ($isAmazon) {
                $bank = [
                    "A merchant marks an item at $100 and offers a 15% promotional discount. What is the final customer checkout price?",
                    "Worker A packs 30 packages per hour and Worker B packs 45 packages per hour. How many total packages do they pack together in 4 hours?",
                    "If 8 warehouse forklifts can unload a cargo shipment in 6 hours, how many hours will 12 forklifts take at the same rate?",
                    "A delivery van travels 60 km at 30 km/h and the next 60 km at 60 km/h. What is the total travel time in hours?",
                    "A store buys inventory for $80 and sells it for $100. What is the profit percentage?",
                    "The average weight of 4 parcels is 15 kg. A 5th parcel weighing 25 kg is added. What is the new average weight?"
                ];
            } elseif ($isMsft) {
                $bank = [
                    "Pointing to a photograph, a woman says: 'He is the son of my only brother.' How is the woman related to the boy?",
                    "A person walks 10 meters North, turns right and walks 15 meters, then turns right again and walks 10 meters. How far is he from his start position?",
                    "If 'CAT' is coded as '3-1-20', how will 'DOG' be coded?",
                    "Find the odd one out from the numbers: 3, 5, 7, 9, 11, 13.",
                    "If the day before yesterday was Thursday, what day of the week will it be the day after tomorrow?",
                    "Find the next number in the pattern: 2, 5, 10, 17, 26, __?"
                ];
            } else {
                $bank = [
                    "A train 180 meters long is running at a speed of 72 km/h. How many seconds will it take to pass an electric pole?",
                    "A sum of money doubles itself in 5 years at simple interest. In how many years will it become 4 times itself at the same rate?",
                    "If the cost price of a pen is $10 and it is sold for $12, what is the percentage profit?",
                    "Find the next number in the arithmetic sequence: 5, 11, 17, 23, __?",
                    "In a row of 25 students facing North, Rohan is 10th from the left. What is his position from the right?",
                    "If A can complete a piece of work in 10 days and B can do it in 15 days, in how many days can they complete it working together?",
                    "A person walks 10 meters North, turns right and walks 15 meters, then turns right again and walks 10 meters. How far is he from his start position?"
                ];
            }
        } elseif ($isDifficult) {
            // Difficult: only a little more difficult than medium
            if ($isService) {
                $bank = [
                    "The difference between compound interest and simple interest on a sum of money for 2 years at 10% per annum is $40. What is the principal sum?",
                    "Two trains running in opposite directions at 54 km/h and 72 km/h cross each other in 12 seconds. If one train is 220 meters long, what is the length of the other train?",
                    "A and B can do a work in 12 days and 18 days respectively. They started working together, but A left 3 days before the completion. How many total days did it take?",
                    "In a container, milk and water are in the ratio 7:3. If 10 liters of water is added, the ratio becomes 7:5. What was the initial quantity of milk?",
                    "In how many different ways can the letters of the word 'LEADER' be arranged such that all vowels always come together?",
                    "A clock gains 5 minutes every 24 hours. If it is set correctly at 12:00 noon on Monday, what time will it show at 6:00 PM on the following Wednesday?",
                    "Six people A, B, C, D, E, and F are sitting around a circular table facing the center. A sits second to the left of C, and B sits between A and D. Who sits opposite to C?",
                    "Pipe A can fill a tank in 10 hours, Pipe B in 15 hours, and Pipe C can empty it in 20 hours. If all three pipes are opened together, how long will it take to fill the tank?",
                    "A merchant professes to sell his goods at cost price but uses a false weight of 900 grams instead of 1 kilogram. What is his actual profit percentage?"
                ];
            } elseif ($isGoogle) {
                $bank = [
                    "Two fair dice are thrown simultaneously. What is the conditional probability that the sum is 10, given that at least one die shows a 5?",
                    "In a circular track of 400 meters, Runner A runs at 8 m/s and Runner B runs in opposite direction at 12 m/s. After how many seconds will they meet for the first time?",
                    "In how many ways can 5 people be seated in a row if two particular individuals insist on sitting next to each other?",
                    "A diagnostic medical test is 95% accurate for diseased patients and 90% accurate for healthy ones. If 1% of the population has the disease, what is the probability a tested positive patient is actually diseased?",
                    "Three workers A, B, and C can complete a task in 6, 8, and 12 days respectively. If they work on alternate days starting with A, how many days will it take to complete the work?"
                ];
            } elseif ($isAmazon) {
                $bank = [
                    "A retailer offers two successive discounts of 20% and 10% on a marked price of $500. What is the single equivalent discount percentage and selling price?",
                    "In an investment partnership, A invests $10,000 for 12 months and B invests $15,000 for 8 months. If the total annual profit is $4,400, what is B's profit share?",
                    "A tank has two inlet pipes filling it in 15 hours and 20 hours, and a bottom leak emptying it in 30 hours. How many hours will it take to fill the empty tank if all are open?",
                    "Two delivery trucks start simultaneously from points 300 km apart and travel towards each other at 60 km/h and 90 km/h. How many hours will elapse before they pass each other?",
                    "A container has 40 liters of milk. 4 liters of milk is taken out and replaced with water. This process is repeated once more. How much pure milk remains?"
                ];
            } elseif ($isMsft) {
                $bank = [
                    "Six colleagues P, Q, R, S, T, and U sit around a circular conference table facing the center. P is opposite to S, Q is to the immediate right of P, and R is between P and T. Who is sitting to the immediate left of S?",
                    "Statements: All laptops are devices. Some devices are phones. Conclusions: I. Some laptops are phones. II. Some devices are laptops. Which conclusion logically follows?",
                    "In a multi-story building, 5 engineers live on floors 1 to 5. A lives above B but below C. D lives on the top floor and E lives on the bottom floor. Who lives on floor 3?",
                    "If January 1, 2024 was a Monday, what day of the week will January 1, 2025 fall on?",
                    "In how many distinct ways can the letters of the word 'SYSTEM' be arranged such that the two S's never appear together?"
                ];
            } else {
                $bank = [
                    "The difference between compound interest and simple interest on a sum of money for 2 years at 10% per annum is $40. What is the principal sum?",
                    "Two trains running in opposite directions at 54 km/h and 72 km/h cross each other in 12 seconds. If one train is 220 meters long, what is the length of the other train?",
                    "A and B can do a work in 12 days and 18 days respectively. They started working together, but A left 3 days before the completion. How many total days did it take?",
                    "In a container, milk and water are in the ratio 7:3. If 10 liters of water is added, the ratio becomes 7:5. What was the initial quantity of milk?",
                    "Pipe A can fill a tank in 10 hours, Pipe B in 15 hours, and Pipe C can empty it in 20 hours. If all three pipes are opened together, how long will it take to fill the tank?"
                ];
            }
        } else {
            // Medium: only a little more difficult than beginner
            if ($isService) {
                $bank = [
                    "A shopkeeper marks an article 40% above the cost price and allows a discount of 20% on the marked price. What is the overall profit percentage?",
                    "Pipe A can fill a tank in 12 hours, while Pipe B can empty it in 18 hours. If both pipes are opened simultaneously, how long will it take to fill the tank completely?",
                    "In a group of 60 students, 40 like Mathematics, 30 like Science, and 15 like both. How many students like neither subject?",
                    "A boat travels 24 km downstream in 2 hours and takes 4 hours to travel the same distance upstream. What is the speed of the water stream in km/h?",
                    "The average score of 5 batsmen is 64 runs. If a 6th batsman joins and the new average becomes 60, how many runs did the 6th batsman score?",
                    "A clock shows 3:15. What is the angle between the hour hand and the minute hand in degrees?",
                    "A car covers the first half of a distance at 40 km/h and the second half at 60 km/h. What is the average speed of the car for the entire journey?",
                    "If 15 men can build a wall in 20 days, how many men will be required to build the same wall in 12 days?",
                    "A father is currently three times as old as his son. In 12 years, he will be twice as old as his son. What is the son's current age?"
                ];
            } elseif ($isGoogle) {
                $bank = [
                    "A box contains 5 red, 4 blue, and 3 green marbles. If 2 marbles are drawn at random without replacement, what is the probability that both are blue?",
                    "A clock shows 4:20. What is the acute angle between the hour hand and the minute hand in degrees?",
                    "In a group of 80 engineers, 50 write Go, 45 write Python, and 25 write both. How many engineers write neither language?",
                    "Person A can complete a software module in 8 days and Person B can complete it in 12 days. If they work on alternate days starting with A, in how many days will the module finish?",
                    "What is the expected value of rolling a single fair 6-sided die where you win dollars equal to the face value?"
                ];
            } elseif ($isAmazon) {
                $bank = [
                    "An e-commerce item marked at $200 is sold with two successive discounts of 10% and 5%. What is the final customer price?",
                    "A logistics center receives shipments every 6 days, 8 days, and 12 days respectively. If all shipments arrive today, in how many days will they arrive together again?",
                    "Delivery Driver A travels at 50 km/h and Driver B travels at 70 km/h along the same highway route. If Driver A starts 1 hour earlier, how many hours will Driver B take to overtake Driver A?",
                    "A warehouse can store 1,200 crates. If small crates take 2 m³ and large crates take 5 m³, and total volume is 3,900 m³, how many large crates are in storage?",
                    "A product cost $50 to manufacture and is marked up by 50%. A seasonal discount of 20% is then applied. What is the profit earned?"
                ];
            } elseif ($isMsft) {
                $bank = [
                    "If 'A + B' means A is the brother of B, and 'A * B' means A is the father of B, how is P related to R in the expression: P * Q + R?",
                    "Find the next number in the sequence: 4, 12, 36, 108, __?",
                    "In a row of students facing North, Rohan is 14th from the left and 17th from the right. How many total students are in the row?",
                    "A clock shows 3:15. What is the angle between the hour hand and the minute hand in degrees?",
                    "If the word 'LEADER' is coded as '20-13-9-12-13-26', how will the word 'LIGHT' be coded in the same system?",
                    "Five friends are sitting on a bench. Alex is next to Bob, Cindy is next to Dave, Dave is not next to Alex. If Bob is on the left end, who sits in the middle?"
                ];
            } else {
                $bank = [
                    "A shopkeeper marks an article 40% above the cost price and allows a discount of 20% on the marked price. What is the overall profit percentage?",
                    "Pipe A can fill a tank in 12 hours, while Pipe B can empty it in 18 hours. If both pipes are opened simultaneously, how long will it take to fill the tank completely?",
                    "A boat travels 24 km downstream in 2 hours and takes 4 hours to travel the same distance upstream. What is the speed of the water stream in km/h?",
                    "A clock shows 3:15. What is the angle between the hour hand and the minute hand in degrees?",
                    "A car covers the first half of a distance at 40 km/h and the second half at 60 km/h. What is the average speed of the car for the entire journey?"
                ];
            }
        }
    }
    // 2. HR & Behavioral Interview Category
    elseif (str_contains($cat, 'hr') || str_contains($cat, 'behavioral')) {
        if ($isBeginner) {
            if ($isGoogle) {
                $bank = [
                    "Walk me through your resume and what specifically excites you about Google's engineering mission.",
                    "Tell me about a college or personal project you worked on recently where you took initiative and learned independently.",
                    "How do you approach learning an unfamiliar technology, programming language, or library when working on an assignment?",
                    "Describe a time you collaborated in a team project with students from diverse backgrounds. How did you coordinate?",
                    "Can you share an experience where you received constructive feedback on your code or presentation? How did you respond?",
                    "What do you consider your greatest technical strength, and what is one skill you are actively working to develop?",
                    "How do you prioritize your time and maintain focus when multiple course deadlines happen in the same week?"
                ];
            } elseif ($isAmazon) {
                $bank = [
                    "Walk me through your background and what motivated you to pursue an engineering role at Amazon.",
                    "Customer Obsession: Tell me about a time you went above and beyond to help a user, classmate, or peer resolve a problem.",
                    "Ownership: Describe a project where you took full end-to-end responsibility, even when parts of it weren't strictly your job.",
                    "Learn and Be Curious: Describe a new programming framework, tool, or concept you explored purely out of curiosity.",
                    "Bias for Action: Tell me about a time you had to make a decision or start a project with incomplete guidelines or limited time.",
                    "Deliver Results: Share an experience where you had to meet a tight deadline on a software project under pressure.",
                    "Earn Trust: Describe a situation where you had to coordinate with a teammate whose working style was different from yours."
                ];
            } elseif ($isMsft) {
                $bank = [
                    "Walk me through your resume and what inspired you to pursue a software engineering career at Microsoft.",
                    "Growth Mindset: Tell me about a technical concept or tool you initially found very difficult. How did you master it?",
                    "Customer Empathy: Tell me about a project where feedback from peers or end-users significantly changed your feature design.",
                    "Describe a time you helped a classmate or teammate who was stuck on a challenging bug or falling behind.",
                    "Share an experience where a project did not go according to plan. What happened and what did you learn from the setback?",
                    "How do you handle critical or constructive feedback during code reviews or team evaluations?"
                ];
            } elseif ($isService) {
                $bank = [
                    "Walk me through your educational background and what inspired you to pursue a career in the IT industry.",
                    "Why are you interested in starting your software engineering career with our company?",
                    "Are you open to working across different technology domains, programming stacks, and geographic locations as project needs arise?",
                    "Tell me about a final-year or semester college project you worked on. What was your individual role and contribution?",
                    "How do you manage stress and prioritize your tasks when juggling academic exams and project deadlines?",
                    "What are your greatest professional strengths, and what is one area you are actively working to improve?"
                ];
            } else {
                $bank = [
                    "Walk me through your resume and what inspired you to pursue a career in technology.",
                    "Tell me about a college or personal project you worked on recently. What was your role?",
                    "Describe a time you had to learn an unfamiliar programming language or tool for a coursework assignment.",
                    "How do you prioritize your tasks when you have multiple assignments or deadlines at the same time?",
                    "Tell me about a time you worked in a team. How did you coordinate with your teammates?",
                    "Can you share an experience where you received constructive feedback from a mentor or peer? How did you respond?",
                    "What do you consider your greatest strength, and what is one area you are actively working to improve?",
                    "Why are you interested in starting your career with " . ($comp !== 'general' ? ucfirst($comp) : 'our company') . "?"
                ];
            }
        } elseif ($isDifficult) {
            // Difficult: only a little more difficult than medium
            if ($isGoogle) {
                $bank = [
                    "Navigating Ambiguity: Tell me about a project where requirements were vague or undefined. How did you structure your work and drive clarity?",
                    "Technical Disagreement: Describe a situation where you strongly disagreed with a peer or technical lead on architecture. How did you resolve it respectfully?",
                    "Balancing Speed vs Quality: How do you decide when software is ready to ship versus when it requires further refactoring under tight deadlines?",
                    "Project Post-Mortem: Describe a technical mistake or failed project approach in your past. What was the root cause and what changes did you adopt?",
                    "Proactive Innovation: Tell me about a time you noticed an inefficiency in a team workflow or codebase and took initiative to fix it without being asked."
                ];
            } elseif ($isAmazon) {
                $bank = [
                    "Deep Dive: Tell me about a time you investigated a subtle, intermittent bug or performance issue to its exact root cause.",
                    "Insist on the Highest Standards: Describe a situation where you refused to compromise on software quality despite severe delivery pressure.",
                    "Have Backbone; Disagree and Commit: Tell me about a technical decision you disagreed with. How did you voice your concern, and how did you execute once the decision was made?",
                    "Frugality: Describe how you accomplished a major project milestone with limited computing resources, time, or tools.",
                    "Earn Trust: Tell me about a situation where you made a mistake that affected teammates or users. How did you communicate and rebuild trust?"
                ];
            } elseif ($isMsft) {
                $bank = [
                    "Collaborative Leadership: Describe a time you aligned team members who had sharply conflicting technical viewpoints toward a common goal.",
                    "Inclusive Software: Tell me about a project where you ensured the software design was accessible or accounted for users with diverse needs.",
                    "Technical Trade-off: Tell me about a time you chose long-term software maintainability and clean design over a quick temporary hack.",
                    "Customer Empathy: Describe an instance where you advocated for the end-user's needs against initial engineering preferences.",
                    "Mentorship: Share an experience where you mentored or guided a less-experienced teammate through a complex technical roadblock."
                ];
            } elseif ($isService) {
                $bank = [
                    "Handling Client Pressure: Describe a time project specifications or deliverable requirements changed unexpectedly near a deadline. How did you respond?",
                    "Conflict Resolution: Tell me about an interpersonal disagreement within a project team and how you resolved it professionally.",
                    "Multi-Tasking: Describe an experience where you had to balance multiple demanding responsibilities simultaneously without dropping quality.",
                    "Adaptability: How did you adapt when assigned to a technology stack or project domain that was not your first preference?",
                    "Commitment: Tell me about a time you put in extra effort and dedication to ensure an important project milestone was delivered on time."
                ];
            } else {
                $bank = [
                    "Tell me about a time when you had to make a difficult trade-off between project delivery deadlines and software quality.",
                    "Describe a situation where you had a strong disagreement with a team member or technical lead. How did you resolve it?",
                    "Tell me about a project that did not achieve its intended results or failed. What happened and what did you learn?",
                    "Describe an experience where you had to lead a project or deliver results with ambiguous or incomplete requirements.",
                    "How do you handle high-pressure deadlines or sudden unexpected production bugs?",
                    "Tell me about a time you advocated for a technical improvement, refactor, or best practice that met resistance from teammates."
                ];
            }
        } else {
            // Medium: only a little more difficult than beginner
            if ($isGoogle) {
                $bank = [
                    "Tell me about a time you had to adapt quickly when the scope or timeline of a project changed midway through development.",
                    "Googlyness: Describe a situation where you noticed a classmate or colleague was overwhelmed and stepped in to support them.",
                    "How do you handle receiving critical code review feedback on an approach you spent days designing?",
                    "Describe an instance where you had to explain a complex algorithmic or system concept to someone without a technical background.",
                    "Tell me about a time you identified a bug or performance bottleneck in a codebase and the process you followed to diagnose it."
                ];
            } elseif ($isAmazon) {
                $bank = [
                    "Ownership: Tell me about a time you saw a problem or missing test case outside your assigned responsibilities and proactively fixed it.",
                    "Customer Obsession: Describe how direct user or peer feedback altered the feature set or user interface of an application you built.",
                    "Earn Trust: Describe a situation where you had to admit an error to your team lead and how you handled the remediation.",
                    "Bias for Action: Tell me about a project decision you had to make when full data was unavailable. What was the outcome?",
                    "Deliver Results: Describe an unexpected roadblock that threatened your project timeline and the concrete steps you took to overcome it."
                ];
            } elseif ($isMsft) {
                $bank = [
                    "Growth Mindset: Describe a situation where an unexpected bug or failed test led to a valuable software engineering insight.",
                    "Collaboration: Tell me about an experience working with a teammate whose working style or background was very different from yours.",
                    "Customer Empathy: How did feedback from real users or peers improve the reliability or interface of a project you built?",
                    "Adapting to Change: Describe how you responded when course guidelines or project priorities shifted abruptly.",
                    "Giving Feedback: Tell me about an instance where you provided constructive, actionable feedback on a peer's code or design."
                ];
            } elseif ($isService) {
                $bank = [
                    "Describe a situation where you had to quickly pick up an assigned programming language or tool for a project deliverable.",
                    "Tell me about a time you worked with a difficult team member and successfully delivered the project on schedule.",
                    "How do you ensure high attention to detail, robust testing, and zero defects in your code before submitting it?",
                    "Describe how you stay motivated and maintain consistent quality when working on routine or repetitive programming tasks.",
                    "Tell me about an experience where you had to present project status and technical milestones to an instructor or team leader."
                ];
            } else {
                $bank = [
                    "Tell me about a time you took ownership of a challenging task or project from beginning to end.",
                    "Describe a situation where you faced an unexpected technical roadblock in a project. How did you overcome it?",
                    "Give an example of a time you worked with a teammate who had a very different perspective or working style.",
                    "Tell me about a mistake you made on a project or assignment. How did you fix it and prevent it from recurring?",
                    "Describe a situation where project requirements or priorities changed midway through development. How did you adapt?",
                    "How do you stay motivated and continue learning new technologies outside of your regular duties?"
                ];
            }
        }
    }
    // 3. Coding & DSA Category
    elseif (str_contains($cat, 'coding') || str_contains($cat, 'dsa') || str_contains($cat, 'algorithm')) {
        $isGoogle  = str_contains($comp, 'google');
        $isAmazon  = str_contains($comp, 'amazon');
        $isMsft    = str_contains($comp, 'microsoft');
        $isService = str_contains($comp, 'tcs') || str_contains($comp, 'infosys') || str_contains($comp, 'wipro') || str_contains($comp, 'accenture') || str_contains($comp, 'cognizant');

        if ($isBeginner) {
            if ($isGoogle) {
                $bank = [
                    "Valid Palindrome: Given a string s, determine if it reads the same backward as forward, ignoring casing and non-alphanumeric characters.\nInput Format: A string s.\nOutput Format: Boolean true or false.\nExample 1: Input: s = 'race a car' -> Output: false\nExample 2: Input: s = 'A man, a plan, a canal: Panama' -> Output: true\nConstraints: O(N) time and O(1) space.",
                    "Find Pivot Index: Given an array of integers nums, calculate the pivot index where the sum of numbers to the left equals the sum of numbers to the right.\nInput Format: An array of integers nums.\nOutput Format: The 0-based pivot index or -1.\nExample 1: Input: nums = [1,7,3,6,5,6] -> Output: 3\nExample 2: Input: nums = [1,2,3] -> Output: -1\nConstraints: O(N) time.",
                    "Move Zeroes: Given an integer array nums, move all 0's to the end while maintaining the relative order of non-zero elements.\nInput Format: An array of integers nums.\nOutput Format: The modified array with zeroes at end.\nExample 1: Input: nums = [0,1,0,3,12] -> Output: [1,3,12,0,0]\nConstraints: In-place O(N) time and O(1) space.",
                    "Length of Last Word: Given a string s consisting of words and spaces, return the length of the last word in the string.\nInput Format: A string s.\nOutput Format: Integer length.\nExample 1: Input: s = 'Hello World' -> Output: 5\nExample 2: Input: s = '   fly me   to   the moon  ' -> Output: 4\nConstraints: O(N) time.",
                    "Intersection of Two Arrays: Given two integer arrays nums1 and nums2, return an array of their unique common elements.\nInput Format: Two integer arrays nums1 and nums2.\nOutput Format: Array of unique common elements.\nExample 1: Input: nums1 = [1,2,2,1], nums2 = [2,2] -> Output: [2]\nConstraints: O(N + M) time using Hash Set.",
                    "Reverse a String: Write a function reverseString(s) that reverses a given string in-place or returns the reversed string.\nInput Format: A string s containing ASCII characters.\nOutput Format: The reversed string.\nExample 1: Input: s = 'hello' -> Output: 'olleh'\nConstraints: O(N) time.",
                    "Running Sum of 1D Array: Given an array nums, define a running sum of an array as runningSum[i] = sum(nums[0]…nums[i]). Return the running sum.\nInput Format: An array of integers nums.\nOutput Format: The running sum array.\nExample 1: Input: nums = [1,2,3,4] -> Output: [1,3,6,10]\nConstraints: O(N) time.",
                    "Plus One: Given a large integer represented as an integer array digits, increment the large integer by one and return the resulting array of digits.\nInput Format: An array of integer digits.\nOutput Format: Array of incremented digits.\nExample 1: Input: digits = [1,2,3] -> Output: [1,2,4]\nConstraints: O(N) time.",
                    "Richest Customer Wealth: You are given an m x n integer grid accounts where accounts[i][j] is the money the i-th customer has in the j-th bank. Return the wealth that the richest customer has.\nInput Format: 2D integer matrix accounts.\nOutput Format: Maximum wealth integer.\nExample 1: Input: accounts = [[1,2,3],[3,2,1]] -> Output: 6\nConstraints: O(M * N) time.",
                    "Jewels and Stones: You're given strings jewels representing the types of stones that are jewels, and stones representing the stones you have. How many of the stones you have are also jewels?\nInput Format: Two strings jewels and stones.\nOutput Format: Integer count of jewels.\nExample 1: Input: jewels = 'aA', stones = 'aAAbbbb' -> Output: 3\nConstraints: O(N + M) time."
                ];
            } elseif ($isAmazon) {
                $bank = [
                    "Two Sum: Given an array of integers nums and an integer target, return indices of the two numbers such that they add up to target.\nInput Format: An array of integers nums, integer target.\nOutput Format: [index1, index2]\nExample 1: Input: nums = [2,7,11,15], target = 9 -> Output: [0,1]\nConstraints: O(N) time complexity using a Hash Map.",
                    "Best Time to Buy and Sell Stock: Return maximum profit achievable by choosing a single day to buy and a future day to sell.\nInput Format: Array prices where prices[i] is price on day i.\nOutput Format: Maximum profit integer.\nExample 1: Input: prices = [7,1,5,3,6,4] -> Output: 5\nConstraints: Single pass O(N) time and O(1) space.",
                    "Single Number: Given a non-empty array of integers nums, every element appears twice except for one. Find that single one.\nInput Format: An array of integers nums.\nOutput Format: The unique integer.\nExample 1: Input: nums = [4,1,2,1,2] -> Output: 4\nConstraints: Linear O(N) time and O(1) space using XOR.",
                    "Valid Anagram: Given two strings s and t, return true if t is an anagram of s, and false otherwise.\nInput Format: Two lowercase strings s and t.\nOutput Format: Boolean true or false.\nExample 1: Input: s = 'anagram', t = 'nagaram' -> Output: true\nConstraints: O(N) time.",
                    "Remove Duplicates from Sorted Array: Given a sorted array nums, remove duplicate values in-place and return the count of unique elements.\nInput Format: Sorted integer array nums.\nOutput Format: Integer count of unique elements.\nExample 1: Input: nums = [1,1,2] -> Output: 2\nConstraints: O(N) time and O(1) space.",
                    "FizzBuzz: Write a function fizzBuzz(n) returning strings from 1 to n where multiples of 3 are 'Fizz', 5 are 'Buzz', both are 'FizzBuzz'.\nInput Format: An integer n.\nOutput Format: Array of strings from 1 to n.\nExample 1: Input: n = 5 -> Output: ['1','2','Fizz','4','Buzz']\nConstraints: O(N) time.",
                    "Contains Duplicate: Given an integer array nums, return true if any value appears at least twice in the array, and false if every element is distinct.\nInput Format: An array of integers nums.\nOutput Format: Boolean true or false.\nExample 1: Input: nums = [1,2,3,1] -> Output: true\nConstraints: O(N) time using Hash Set.",
                    "Number of Good Pairs: Given an array of integers nums, return the number of good pairs (i, j) where nums[i] == nums[j] and i < j.\nInput Format: An array of integers nums.\nOutput Format: Count of good pairs.\nExample 1: Input: nums = [1,2,3,1,1,3] -> Output: 4\nConstraints: O(N) time with frequency map.",
                    "Roman to Integer: Convert a given Roman numeral string (e.g., 'III', 'IV', 'IX', 'LVIII') into its corresponding integer value.\nInput Format: String s representing a valid Roman numeral.\nOutput Format: The converted integer value.\nExample 1: Input: s = 'LVIII' -> Output: 58\nConstraints: O(N) time.",
                    "Maximum Units on a Truck: Given boxTypes where boxTypes[i] = [numberOfBoxes_i, numberOfUnitsPerBox_i] and truckSize, return maximum units that can be put on truck.\nInput Format: Array of pairs boxTypes, integer truckSize.\nOutput Format: Maximum units integer.\nExample 1: Input: boxTypes = [[1,3],[2,2],[3,1]], truckSize = 4 -> Output: 8\nConstraints: Greedy sorting in O(N log N)."
                ];
            } elseif ($isMsft) {
                $bank = [
                    "Reverse Words in a String: Given an input string s, reverse the order of the words while reducing multiple spaces to single space.\nInput Format: A string s.\nOutput Format: The reversed words string.\nExample 1: Input: s = 'the sky is blue' -> Output: 'blue is sky the'\nConstraints: O(N) time.",
                    "Valid Parentheses: Given a string containing '(', ')', '{', '}', '[' and ']', determine if the input string is valid.\nInput Format: A string s.\nOutput Format: Boolean true or false.\nExample 1: Input: s = '()[]{}' -> Output: true\nConstraints: O(N) time and O(N) space using a Stack.",
                    "Palindrome Number: Given an integer x, return true if x is a palindrome, and false otherwise.\nInput Format: An integer x.\nOutput Format: Boolean true or false.\nExample 1: Input: x = 121 -> Output: true\nConstraints: O(log10(N)) time without string conversion.",
                    "Majority Element: Given an array nums of size n, return the majority element that appears more than n/2 times.\nInput Format: An array of integers nums.\nOutput Format: The majority integer.\nExample 1: Input: nums = [3,2,3] -> Output: 3\nConstraints: O(N) time and O(1) space.",
                    "Reverse a String: Write a function reverseString(s) that reverses a given string in-place or returns the reversed string.\nInput Format: A string s containing ASCII characters.\nOutput Format: The reversed string.\nExample 1: Input: s = 'PrepPro' -> Output: 'orPperP'\nConstraints: O(N) time.",
                    "Find Second Largest: Given an array of distinct integers nums, find the second largest element.\nInput Format: An array of integers nums of length >= 2.\nOutput Format: The second largest integer.\nExample 1: Input: nums = [12, 35, 1, 10, 34, 1] -> Output: 34\nConstraints: Single pass O(N) time.",
                    "Pascal's Triangle: Given an integer numRows, return the first numRows of Pascal's triangle.\nInput Format: Integer numRows >= 1.\nOutput Format: 2D array representing Pascal's triangle.\nExample 1: Input: numRows = 5 -> Output: [[1],[1,1],[1,2,1],[1,3,3,1],[1,4,6,4,1]]\nConstraints: O(numRows^2) time.",
                    "Power of Two: Given an integer n, return true if it is a power of two. Otherwise, return false.\nInput Format: Integer n.\nOutput Format: Boolean true or false.\nExample 1: Input: n = 16 -> Output: true\nConstraints: O(1) time using bitwise (n & (n - 1)) == 0.",
                    "Excel Sheet Column Number: Given a string columnTitle that represents the column title as appears in an Excel sheet, return its corresponding column number.\nInput Format: String columnTitle.\nOutput Format: Integer column number.\nExample 1: Input: columnTitle = 'AB' -> Output: 28\nConstraints: O(N) time.",
                    "Longest Common Prefix: Write a function to find the longest common prefix string amongst an array of strings. If none, return ''.\nInput Format: An array of strings strs.\nOutput Format: Common prefix string.\nExample 1: Input: strs = ['flower','flow','flight'] -> Output: 'fl'\nConstraints: O(N * M) time."
                ];
            } elseif ($isService) {
                $bank = [
                    "Check Prime Number: Write a function isPrime(n) that checks whether a positive integer n is a prime number.\nInput Format: An integer n >= 1.\nOutput Format: Boolean true or false.\nExample 1: Input: n = 11 -> Output: true\nConstraints: Time complexity O(sqrt(N)).",
                    "Armstrong Number: Check whether an integer n is an Armstrong number (sum of each digit raised to power of count equals n).\nInput Format: An integer n > 0.\nOutput Format: Boolean true or false.\nExample 1: Input: n = 153 -> Output: true (1^3 + 5^3 + 3^3 = 153)\nConstraints: O(log10(N)) time.",
                    "Fibonacci Sequence: Write a function fibonacci(n) that returns the nth Fibonacci number.\nInput Format: An integer n >= 0.\nOutput Format: The nth Fibonacci number.\nExample 1: Input: n = 6 -> Output: 8\nConstraints: O(N) time and O(1) space.",
                    "Factorial of N: Write a function factorial(n) that computes n! (n factorial) for non-negative integer n.\nInput Format: An integer n (0 <= n <= 20).\nOutput Format: The factorial product as an integer.\nExample 1: Input: n = 5 -> Output: 120\nConstraints: O(N) time.",
                    "Check Leap Year: Write a function isLeapYear(year) determining if year is leap (divisible by 4, not 100 unless divisible by 400).\nInput Format: Integer year > 0.\nOutput Format: Boolean true or false.\nExample 1: Input: year = 2024 -> Output: true\nConstraints: O(1) time.",
                    "Sum of Digits: Given a positive integer n, compute the sum of its individual decimal digits.\nInput Format: An integer n >= 0.\nOutput Format: Sum of digits as an integer.\nExample 1: Input: n = 1234 -> Output: 10 (1+2+3+4)\nConstraints: O(log10(N)) time.",
                    "Count Vowels and Consonants: Given a lowercase string s, return an object or pair with the counts of vowels and consonants.\nInput Format: A lowercase alphabetic string s.\nOutput Format: [vowelCount, consonantCount]\nExample 1: Input: s = 'interview' -> Output: [4, 5]\nConstraints: O(N) time.",
                    "Find Second Largest: Given an array of distinct integers nums, find the second largest element.\nInput Format: An array of integers nums of length >= 2.\nOutput Format: The second largest integer.\nExample 1: Input: nums = [12, 35, 1, 10, 34, 1] -> Output: 34\nConstraints: Single pass O(N) time.",
                    "Greatest Common Divisor: Write a function gcd(a, b) that returns the greatest common divisor using Euclidean algorithm.\nInput Format: Two positive integers a and b.\nOutput Format: The GCD integer.\nExample 1: Input: a = 48, b = 18 -> Output: 6\nConstraints: O(log(min(a, b))) time.",
                    "Perfect Number: Check whether an integer n is a perfect number (sum of proper positive divisors equals n).\nInput Format: Integer n > 1.\nOutput Format: Boolean true or false.\nExample 1: Input: n = 28 -> Output: true (1 + 2 + 4 + 7 + 14 = 28)\nConstraints: O(sqrt(N)) time.",
                    "Harshad Number: An integer divisible by the sum of its digits is called a Harshad number. Determine if n is Harshad.\nInput Format: Integer n > 0.\nOutput Format: Boolean true or false.\nExample 1: Input: n = 18 -> Output: true (18 is divisible by 1+8=9)\nConstraints: O(log10(N)) time.",
                    "Binary to Decimal: Given a binary string s, return its equivalent decimal integer representation.\nInput Format: Binary string s.\nOutput Format: Decimal integer value.\nExample 1: Input: s = '1011' -> Output: 11\nConstraints: O(N) time."
                ];
            } else {
                $bank = [
                    "Reverse a String: Write a function reverseString(s) that reverses a given string in-place or returns the reversed string.\nInput Format: A string s containing ASCII characters.\nOutput Format: The reversed string.\nExample 1: Input: s = 'hello' -> Output: 'olleh'\nConstraints: Aim for O(N) time complexity and O(1) auxiliary space.",
                    "Valid Palindrome: Given a string s, determine if it reads the same backward as forward, ignoring casing.\nInput Format: A string s.\nOutput Format: Boolean true or false.\nExample 1: Input: s = 'racecar' -> Output: true\nConstraints: O(N) time and O(1) space.",
                    "Find Maximum in Array: Given an array of integers nums, find and return the maximum value.\nInput Format: An array of integers nums.\nOutput Format: The maximum integer.\nExample 1: Input: nums = [3, 7, 2, 9, 5] -> Output: 9\nConstraints: O(N) time and O(1) space.",
                    "Check Prime Number: Write a function isPrime(n) that checks whether a positive integer n is a prime number.\nInput Format: An integer n >= 1.\nOutput Format: Boolean true or false.\nExample 1: Input: n = 11 -> Output: true\nConstraints: Time complexity O(sqrt(N)).",
                    "Factorial of N: Write a function factorial(n) that computes n! (n factorial) for a non-negative integer n.\nInput Format: An integer n (0 <= n <= 20).\nOutput Format: The factorial product as an integer.\nExample 1: Input: n = 5 -> Output: 120\nConstraints: O(N) time.",
                    "Fibonacci Sequence: Write a function fibonacci(n) that returns the nth Fibonacci number.\nInput Format: An integer n >= 0.\nOutput Format: The nth Fibonacci number.\nExample 1: Input: n = 6 -> Output: 8\nConstraints: O(N) time and O(1) space.",
                    "Sum of Array Elements: Write a function sumArray(nums) that calculates the total sum of all elements in an integer array.\nInput Format: An array of integers nums.\nOutput Format: An integer sum.\nExample 1: Input: nums = [1, 2, 3, 4, 5] -> Output: 15\nConstraints: O(N) time.",
                    "Count Vowels and Consonants: Given a lowercase string s, return an object or pair with the counts of vowels and consonants.\nInput Format: A lowercase alphabetic string s.\nOutput Format: [vowelCount, consonantCount]\nExample 1: Input: s = 'interview' -> Output: [4, 5]\nConstraints: O(N) time.",
                    "Find Second Largest: Given an array of distinct integers nums, find the second largest element.\nInput Format: An array of integers nums of length >= 2.\nOutput Format: The second largest integer.\nExample 1: Input: nums = [12, 35, 1, 10, 34, 1] -> Output: 34\nConstraints: Single pass O(N) time without sorting.",
                    "Armstrong Number: Check whether an integer n is an Armstrong number.\nInput Format: An integer n > 0.\nOutput Format: Boolean true or false.\nExample 1: Input: n = 153 -> Output: true\nConstraints: O(log10(N)) time."
                ];
            }
        } elseif ($isDifficult) {
            // Difficult: only a little more difficult than medium level (solvable standard LeetCode Mediums)
            if ($isGoogle) {
                $bank = [
                    "3Sum: Given an integer array nums, return all unique triplets [nums[i], nums[j], nums[k]] such that i != j != k and nums[i] + nums[j] + nums[k] == 0.\nExample 1: Input: nums = [-1,0,1,2,-1,-4] -> Output: [[-1,-1,2],[-1,0,1]]\nConstraints: O(N^2) time complexity with sorting and Two Pointers.",
                    "Container With Most Water: Given n non-negative integers height, find two lines that form a container containing the most water.\nExample 1: Input: height = [1,8,6,2,5,4,8,3,7] -> Output: 49\nConstraints: O(N) time using Two Pointers.",
                    "Search in Rotated Sorted Array: Given array nums sorted in ascending order that has been rotated, return index of target in O(log N) time.\nExample 1: Input: nums = [4,5,6,7,0,1,2], target = 0 -> Output: 4\nConstraints: O(log N) runtime.",
                    "Longest Substring Without Repeating Characters: Given a string s, find the length of the longest substring without repeating characters.\nExample 1: Input: s = 'abcabcbb' -> Output: 3\nConstraints: O(N) time using Sliding Window.",
                    "Validate Binary Search Tree: Given root of binary tree, determine if it is a valid binary search tree (BST).\nExample 1: Input: root = [2,1,3] -> Output: true\nConstraints: O(N) time using in-order traversal.",
                    "Daily Temperatures: Given an array of temperatures, return an array answer such that answer[i] is days until a warmer temperature.\nExample 1: Input: temperatures = [73,74,75,71,69,72,76,73] -> Output: [1,1,4,2,1,1,0,0]\nConstraints: O(N) time using Monotonic Stack."
                ];
            } elseif ($isAmazon) {
                $bank = [
                    "Top K Frequent Elements: Given an integer array nums and integer k, return the k most frequent elements.\nExample 1: Input: nums = [1,1,1,2,2,3], k = 2 -> Output: [1,2]\nConstraints: O(N log k) using Min-Heap or O(N) using Bucket Sort.",
                    "Merge Intervals: Given an array of intervals, merge all overlapping intervals.\nExample 1: Input: intervals = [[1,3],[2,6],[8,10],[15,18]] -> Output: [[1,6],[8,10],[15,18]]\nConstraints: O(N log N) time complexity due to sorting.",
                    "Subarray Sum Equals K: Given an array of integers nums and an integer k, return total count of continuous subarrays whose sum equals k.\nExample 1: Input: nums = [1,1,1], k = 2 -> Output: 2\nConstraints: O(N) time using Prefix Sums with a Hash Map.",
                    "House Robber: Cannot rob two adjacent houses. Given integer array nums of money, return max amount you can rob tonight.\nExample 1: Input: nums = [1,2,3,1] -> Output: 4 (1 + 3)\nConstraints: O(N) time and O(1) space with 1D DP.",
                    "Coin Change: Given coin denominations and total amount, return fewest number of coins needed to make up amount. If impossible, return -1.\nExample 1: Input: coins = [1,2,5], amount = 11 -> Output: 3 (5 + 5 + 1)\nConstraints: O(amount * n) time using Dynamic Programming.",
                    "Min Stack: Design a stack that supports push, pop, top, and retrieving the minimum element in constant time O(1).\nExample 1: push(-2), push(0), push(-3), getMin() -> -3, pop(), top() -> 0, getMin() -> -2\nConstraints: O(1) time for all operations."
                ];
            } elseif ($isMsft) {
                $bank = [
                    "Product of Array Except Self: Return array answer such that answer[i] is product of all elements except nums[i] without using division.\nExample 1: Input: nums = [1,2,3,4] -> Output: [24,12,8,6]\nConstraints: O(N) time and O(1) auxiliary output space.",
                    "Spiral Matrix: Given an m x n matrix, return all elements of the matrix in spiral order.\nExample 1: Input: matrix = [[1,2,3],[4,5,6],[7,8,9]] -> Output: [1,2,3,6,9,8,7,4,5]\nConstraints: O(M * N) time and O(1) extra space.",
                    "Lowest Common Ancestor of BST: Given a binary search tree (BST) and two nodes p and q, find their lowest common ancestor (LCA).\nExample 1: Input: root = [6,2,8,0,4,7,9], p = 2, q = 8 -> Output: 6\nConstraints: O(H) time where H is tree height.",
                    "Sort Colors: Given array nums with n objects colored red, white, or blue (0, 1, 2), sort them in-place so objects of same color are adjacent.\nExample 1: Input: nums = [2,0,2,1,1,0] -> Output: [0,0,1,1,2,2]\nConstraints: One-pass O(N) Dutch National Flag algorithm.",
                    "Climbing Stairs: You are climbing a staircase of n steps. Each time you can climb 1 or 2 steps. In how many distinct ways can you climb to top?\nExample 1: Input: n = 3 -> Output: 3\nConstraints: O(N) time and O(1) space with Dynamic Programming.",
                    "Longest Consecutive Sequence: Given unsorted array nums, return length of longest consecutive elements sequence.\nExample 1: Input: nums = [100,4,200,1,3,2] -> Output: 4\nConstraints: O(N) time using Hash Set."
                ];
            } elseif ($isService) {
                $bank = [
                    "Subarray with Given Sum: Given an unsorted array of non-negative integers, find a continuous subarray which adds to a given sum S.\nExample 1: Input: nums = [1,2,3,7,5], S = 12 -> Output: [2, 4] (1-based indices for [2,3,7])\nConstraints: O(N) time using Sliding Window.",
                    "Longest Common Prefix: Write a function to find the longest common prefix string amongst an array of strings.\nExample 1: Input: strs = ['flower','flow','flight'] -> Output: 'fl'\nConstraints: O(N * M) time.",
                    "Next Greater Element: Given an array nums, find the next greater element for each element in the array.\nExample 1: Input: nums = [4, 5, 2, 25] -> Output: [5, 25, 25, -1]\nConstraints: O(N) time using a Stack.",
                    "Kth Smallest Element: Given an array nums and an integer k, find the kth smallest element in the array.\nExample 1: Input: nums = [7, 10, 4, 3, 20, 15], k = 3 -> Output: 7\nConstraints: O(N log K) time with a Max-Heap.",
                    "Kadane's Algorithm: Find contiguous subarray with largest sum and return its maximum sum.\nExample 1: Input: nums = [-2,1,-3,4,-1,2,1,-5,4] -> Output: 6\nConstraints: Single pass O(N) time and O(1) space.",
                    "Count and Say: Generate the nth term of the count-and-say sequence where 1 -> '1', 2 -> '11', 3 -> '21', 4 -> '1211'.\nExample 1: Input: n = 4 -> Output: '1211'\nConstraints: O(2^N) bounded for small n."
                ];
            } else {
                $bank = [
                    "3Sum: Given an integer array nums, return all unique triplets [nums[i], nums[j], nums[k]] such that i != j != k and nums[i] + nums[j] + nums[k] == 0.\nExample 1: Input: nums = [-1,0,1,2,-1,-4] -> Output: [[-1,-1,2],[-1,0,1]]\nConstraints: O(N^2) time complexity with sorting and Two Pointers.",
                    "Container With Most Water: Given n non-negative integers height, find two lines that form a container containing the most water.\nExample 1: Input: height = [1,8,6,2,5,4,8,3,7] -> Output: 49\nConstraints: O(N) time using Two Pointers.",
                    "Product of Array Except Self: Return array answer such that answer[i] is product of all elements except nums[i] without division.\nExample 1: Input: nums = [1,2,3,4] -> Output: [24,12,8,6]\nConstraints: O(N) time and O(1) auxiliary space.",
                    "Search in Rotated Sorted Array: Given rotated sorted array nums, return index of target in O(log N) time.\nExample 1: Input: nums = [4,5,6,7,0,1,2], target = 0 -> Output: 4\nConstraints: O(log N) runtime.",
                    "Merge Intervals: Given an array of intervals, merge all overlapping intervals.\nExample 1: Input: intervals = [[1,3],[2,6],[8,10],[15,18]] -> Output: [[1,6],[8,10],[15,18]]\nConstraints: Target O(N log N) time complexity.",
                    "Subarray Sum Equals K: Given array nums and integer k, return total count of continuous subarrays with sum k.\nExample 1: Input: nums = [1,1,1], k = 2 -> Output: 2\nConstraints: O(N) time using Prefix Sums with Hash Map.",
                    "House Robber: Cannot rob two adjacent houses. Return max amount you can rob tonight.\nExample 1: Input: nums = [1,2,3,1] -> Output: 4\nConstraints: O(N) time and O(1) space with Dynamic Programming."
                ];
            }
        } else {
            // Medium: only a little more difficult than beginner level
            if ($isGoogle) {
                $bank = [
                    "Binary Search: Given a sorted array nums and target, return index if found or -1.\nExample 1: Input: nums = [-1,0,3,5,9,12], target = 9 -> Output: 4\nConstraints: O(log N) time.",
                    "Middle of the Linked List: Given head of singly linked list, return middle node.\nExample 1: Input: head = [1,2,3,4,5] -> Output: Node with value 3\nConstraints: O(N) time using fast and slow pointers.",
                    "Squares of a Sorted Array: Given sorted integer array nums, return array of squares of each number sorted in non-decreasing order.\nExample 1: Input: nums = [-4,-1,0,3,10] -> Output: [0,1,9,16,100]\nConstraints: O(N) time using Two Pointers.",
                    "First Bad Version: Find first bad version in versions [1..n] using API isBadVersion(version).\nExample 1: Input: n = 5, bad = 4 -> Output: 4\nConstraints: O(log N) time using Binary Search.",
                    "Invert Binary Tree: Given root of binary tree, invert the tree, and return its root.\nExample 1: Input: root = [4,2,7,1,3,6,9] -> Output: [4,7,2,9,6,3,1]\nConstraints: O(N) time.",
                    "Move Zeroes: Move all 0's to the end while maintaining relative order of non-zero elements.\nExample 1: Input: nums = [0,1,0,3,12] -> Output: [1,3,12,0,0]\nConstraints: In-place O(N) time with O(1) extra space."
                ];
            } elseif ($isAmazon) {
                $bank = [
                    "Two Sum: Given an array of integers nums and target, return indices of two numbers that add up to target.\nExample 1: Input: nums = [2,7,11,15], target = 9 -> Output: [0,1]\nConstraints: O(N) time complexity using a Hash Map.",
                    "Best Time to Buy and Sell Stock: Return maximum profit achievable by buying and selling a stock once.\nExample 1: Input: prices = [7,1,5,3,6,4] -> Output: 5\nConstraints: Single pass O(N) time and O(1) space.",
                    "Contains Duplicate: Return true if any value appears at least twice in array, false if distinct.\nExample 1: Input: nums = [1,2,3,1] -> Output: true\nConstraints: O(N) time using Hash Set.",
                    "Intersection of Two Arrays II: Given two integer arrays nums1 and nums2, return an array of their intersection with duplicate counts preserved.\nExample 1: Input: nums1 = [1,2,2,1], nums2 = [2,2] -> Output: [2,2]\nConstraints: O(N + M) time using Hash Map.",
                    "Majority Element: Given an array nums of size n, return majority element appearing more than n/2 times.\nExample 1: Input: nums = [3,2,3] -> Output: 3\nConstraints: O(N) time and O(1) space using Boyer-Moore Voting Algorithm.",
                    "Reverse a Singly Linked List: Given head of singly linked list, reverse list and return head.\nExample 1: Input: head = [1,2,3,4,5] -> Output: [5,4,3,2,1]\nConstraints: O(N) time and O(1) auxiliary space."
                ];
            } elseif ($isMsft) {
                $bank = [
                    "Valid Parentheses: Determine if string with '(', ')', '{', '}', '[' and ']' is valid.\nExample 1: Input: s = '()[]{}' -> Output: true\nConstraints: O(N) time and O(N) space using a Stack.",
                    "Maximum Subarray (Kadane's): Find contiguous subarray with largest sum and return its sum.\nExample 1: Input: nums = [-2,1,-3,4,-1,2,1,-5,4] -> Output: 6\nConstraints: O(N) time with O(1) space.",
                    "Merge Two Sorted Lists: Merge two sorted linked lists into one sorted list.\nExample 1: Input: list1 = [1,2,4], list2 = [1,3,4] -> Output: [1,1,2,3,4,4]\nConstraints: O(N + M) time and O(1) extra space.",
                    "Search Insert Position: Given sorted array and target, return index if found or index where it would be inserted.\nExample 1: Input: nums = [1,3,5,6], target = 5 -> Output: 2\nConstraints: O(log N) runtime using Binary Search.",
                    "First Unique Character: Find first non-repeating character in string and return index. If none, -1.\nExample 1: Input: s = 'leetcode' -> Output: 0\nConstraints: O(N) time with frequency counting.",
                    "Remove Duplicates from Sorted Array II: Each unique element may appear at most twice. Return new length.\nExample 1: Input: nums = [1,1,1,2,2,3] -> Output: 5\nConstraints: In-place O(N) time and O(1) space."
                ];
            } elseif ($isService) {
                $bank = [
                    "Missing Number in Array: Given array nums containing n distinct numbers in range [0, n], return the only number missing.\nExample 1: Input: nums = [3,0,1] -> Output: 2\nConstraints: O(N) time and O(1) space using Gauss sum formula.",
                    "Rotate Array by K Steps: Given an integer array nums, rotate the array to the right by k steps.\nExample 1: Input: nums = [1,2,3,4,5,6,7], k = 3 -> Output: [5,6,7,1,2,3,4]\nConstraints: O(N) time and O(1) space using three reversals.",
                    "Subarray with 0 Sum: Given an array of integers nums, determine whether there is a subarray with sum equal to 0.\nExample 1: Input: nums = [4, 2, -3, 1, 6] -> Output: true (from [2, -3, 1])\nConstraints: O(N) time using Prefix Sums and Hash Set.",
                    "Frequency of Elements: Count frequency of each distinct element in an array and return pairs.\nExample 1: Input: nums = [1, 2, 2, 3, 3, 3] -> Output: {1: 1, 2: 2, 3: 3}\nConstraints: O(N) time using Hash Map.",
                    "Check Anagram: Given two strings s and t, determine if t is an anagram of s.\nExample 1: Input: s = 'listen', t = 'silent' -> Output: true\nConstraints: O(N) time.",
                    "Reverse a Singly Linked List: Reverse a given singly linked list and return new head.\nExample 1: Input: head = [1,2,3,4,5] -> Output: [5,4,3,2,1]\nConstraints: O(N) time and O(1) space."
                ];
            } else {
                $bank = [
                    "Two Sum: Given an array of integers nums and an integer target, return indices of two numbers that add up to target.\nExample 1: Input: nums = [2,7,11,15], target = 9 -> Output: [0,1]\nConstraints: O(N) time complexity using a Hash Map.",
                    "Valid Parentheses: Determine if input bracket string is valid.\nExample 1: Input: s = '()[]{}' -> Output: true\nConstraints: O(N) time and O(N) space using a Stack.",
                    "Maximum Subarray (Kadane's): Given integer array nums, find contiguous subarray with largest sum.\nExample 1: Input: nums = [-2,1,-3,4,-1,2,1,-5,4] -> Output: 6\nConstraints: O(N) time with O(1) space.",
                    "Best Time to Buy and Sell Stock: Return maximum profit achievable by buying and selling once.\nExample 1: Input: prices = [7,1,5,3,6,4] -> Output: 5\nConstraints: Single pass O(N) time and O(1) space.",
                    "Reverse a Singly Linked List: Given head of singly linked list, reverse list and return head.\nExample 1: Input: head = [1,2,3,4,5] -> Output: [5,4,3,2,1]\nConstraints: O(N) time with O(1) auxiliary space.",
                    "Binary Search: Given sorted array nums and target, return index if found or -1.\nExample 1: Input: nums = [-1,0,3,5,9,12], target = 9 -> Output: 4\nConstraints: O(log N) runtime.",
                    "Merge Two Sorted Lists: Merge two sorted linked lists into one sorted list.\nExample 1: Input: list1 = [1,2,4], list2 = [1,3,4] -> Output: [1,1,2,3,4,4]\nConstraints: O(N + M) time and O(1) extra space."
                ];
            }
        }
    }
    // 4. Technical Core Category (Default)
    else {
        if ($isBeginner) {
            if ($isGoogle) {
                $bank = [
                    "What is the fundamental difference between a Process and a Thread in modern operating systems?",
                    "Explain the computer memory hierarchy (L1/L2/L3 CPU caches, RAM, and Disk) in terms of speed and capacity.",
                    "What happens at the network layer during the standard TCP 3-way handshake when establishing a connection?",
                    "What is DNS (Domain Name System) and how does your browser resolve 'www.google.com' into an IP address?",
                    "Explain the difference between bitwise operations (AND, OR, XOR) and logical operations with simple examples.",
                    "What is the difference between Stack memory allocation and Heap memory allocation in compiled languages?",
                    "What is the purpose of Git version control, and what is the difference between 'git pull' and 'git fetch'?"
                ];
            } elseif ($isAmazon) {
                $bank = [
                    "Explain client-server architecture and how a browser communicates with an online store's backend servers.",
                    "What are the primary HTTP methods (GET, POST, PUT, DELETE) and when should each be used in RESTful web services?",
                    "What is the difference between a Primary Key and a Foreign Key in relational databases?",
                    "What is the difference between relational tables (SQL) and key-value document stores (NoSQL)?",
                    "Explain the difference between synchronous and asynchronous operations in high-volume web applications.",
                    "What is a cookie versus a session, and how do web applications remember a logged-in user?",
                    "What do common HTTP status codes (200, 400, 401, 404, 500) indicate to an API consumer?"
                ];
            } elseif ($isMsft) {
                $bank = [
                    "Explain the four foundational principles of Object-Oriented Programming (OOP) with clear, real-world examples.",
                    "What is the difference between a Class and an Object, and how are instances instantiated in memory?",
                    "What is the difference between an Abstract Class and an Interface, and when would you choose one over the other?",
                    "What is the purpose of a constructor in OOP, and can a class have multiple overloaded constructors?",
                    "Explain the difference between Value types (primitives on Stack) and Reference types (objects on Heap).",
                    "What is the purpose of SQL SELECT statements, and how do WHERE clauses filter result sets?",
                    "What is Exception Handling (try-catch-finally) and why should raw exceptions never crash an enterprise service?"
                ];
            } elseif ($isService) {
                // TCS, Infosys, Wipro, Accenture placement pattern
                $bank = [
                    "Explain the difference between JDK, JRE, and JVM in the Java execution environment (or C vs C++ pointers).",
                    "What is the difference between Method Overloading and Method Overriding with a short code example?",
                    "What is the difference between DDL (Data Definition Language) and DML (Data Manipulation Language) commands in SQL?",
                    "What is the difference between a Primary Key and a Unique Key in database management systems?",
                    "Explain the concept of 'Pass by Value' versus 'Pass by Reference' in programming languages.",
                    "What is the role of the 'static' keyword in Object-Oriented programming languages?",
                    "What are the differences between an Array and a Linked List, and what are their respective retrieval time complexities?"
                ];
            } else {
                $bank = [
                    "Explain the four main principles of Object-Oriented Programming (OOP) with brief real-world examples.",
                    "What is the difference between an Array and a Linked List, and what are their time complexities for random access?",
                    "What is the difference between SQL and NoSQL databases, and when would you choose each?",
                    "What is the difference between a process and a thread in an operating system?",
                    "What is an API, and what is the difference between GET, POST, PUT, and DELETE HTTP methods?",
                    "What is the difference between a Primary Key and a Foreign Key in relational database management?",
                    "Explain the difference between client-side cookies and server-side sessions.",
                    "What is the purpose of Git version control, and what is the difference between 'git pull' and 'git fetch'?",
                    "Explain the difference between Stack memory and Heap memory in programming languages.",
                    "What is the difference between synchronous and asynchronous code execution?",
                    "What are the common HTTP status codes (200, 400, 404, 500) and what do they indicate?",
                    "What is the role of the DNS (Domain Name System) in web communication?"
                ];
            }
        } elseif ($isDifficult) {
            // Difficult: only a little more difficult than medium
            if ($isGoogle) {
                $bank = [
                    "Explain the transition between User Space and Kernel Space during operating system system calls (e.g., read, write, fork).",
                    "What is a Translation Lookaside Buffer (TLB), and how does the OS handle page faults in virtual memory management?",
                    "Explain how TCP congestion control mechanisms (Slow Start, Congestion Avoidance, Fast Retransmit) prevent network saturation.",
                    "Explain the CAP Theorem (Consistency, Availability, Partition Tolerance) and how distributed storage systems make trade-offs.",
                    "What is Write-Ahead Logging (WAL) in database storage engines and how does it guarantee durability across crashes?",
                    "Explain Consistent Hashing and why it is indispensable when distributing keys across dynamic caches or cluster nodes."
                ];
            } elseif ($isAmazon) {
                $bank = [
                    "What are the architectural trade-offs between a monolithic system and a distributed microservices architecture?",
                    "Explain why database connection pooling and read replicas are critical for handling sudden peak traffic spikes in e-commerce.",
                    "What is API Idempotency, and how do payment and checkout services prevent duplicate charges during network timeouts?",
                    "Explain the difference between optimistic concurrency control and pessimistic locking in relational inventory databases.",
                    "How do distributed message queues (e.g., AWS SQS or Kafka) enable asynchronous decoupled processing and handle dead-letter queues?",
                    "Explain common caching strategies (like Cache-Aside vs Write-Through) and how cache eviction policies like LRU operate."
                ];
            } elseif ($isMsft) {
                $bank = [
                    "Explain the five SOLID design principles in Object-Oriented Programming with practical enterprise software examples.",
                    "Explain database transaction isolation levels (Read Uncommitted, Read Committed, Repeatable Read, Serializable) and dirty reads.",
                    "How does Garbage Collection operate in managed runtimes (like .NET CLR or Java JVM), and what causes stop-the-world pauses?",
                    "Explain how the Async/Await pattern functions under the hood using state machines and synchronization contexts.",
                    "What is Dependency Injection (Inversion of Control) and how does it improve software maintainability and testability?",
                    "How does HTTPS secure communication over the web using public key infrastructure (PKI) and SSL/TLS cryptographic handshakes?"
                ];
            } elseif ($isService) {
                $bank = [
                    "Explain the difference between 3NF (Third Normal Form) and BCNF (Boyce-Codd Normal Form) with functional dependency examples.",
                    "What is the performance difference between a SQL Subquery and a SQL JOIN, and when does the query optimizer favor joins?",
                    "What is a race condition in multithreaded applications, and how do mutex locks or synchronized blocks resolve it?",
                    "What are the advantages and disadvantages of using Database Stored Procedures versus application-level business logic?",
                    "Explain the four conditions necessary for an Operating System deadlock (Mutual Exclusion, Hold & Wait, No Preemption, Circular Wait).",
                    "What is the difference between optimistic and pessimistic concurrency control in high-volume banking transactions?"
                ];
            } else {
                $bank = [
                    "Explain database transaction isolation levels (such as Read Committed vs Serializable) and what dirty reads or phantom reads mean.",
                    "What is the difference between optimistic concurrency control and pessimistic locking in database systems?",
                    "Explain common caching strategies (like Cache-Aside vs Write-Through) and how cache eviction policies like LRU operate.",
                    "What is the difference between horizontal and vertical scaling, and how does a Load Balancer distribute traffic across servers?",
                    "Explain why database connection pooling is essential in high-throughput backend applications.",
                    "Explain the SOLID design principles in Object-Oriented Programming with a practical software engineering example.",
                    "How does HTTPS secure communication over the web using the SSL/TLS cryptographic handshake?",
                    "What is the role of message queues (such as RabbitMQ, Kafka, or SQS) in decoupled backend architectures?",
                    "How does Garbage Collection operate in managed runtimes (like the Java JVM or .NET CLR), and what causes stop-the-world pauses?",
                    "What is the difference between monolithic architecture and microservices architecture, and what are the trade-offs?"
                ];
            }
        } else {
            // Medium: only a little more difficult than beginner
            if ($isGoogle) {
                $bank = [
                    "Explain how Virtual Memory and Paging work, and what occurs when a page fault happens in the OS.",
                    "Compare CPU scheduling algorithms: Round Robin vs First-Come-First-Served (FCFS) vs Shortest Job First (SJF).",
                    "Explain the fundamental differences between TCP and UDP protocols and when a streaming or gaming app would choose UDP.",
                    "What is a deadlock in an operating system, and what four conditions are necessary for deadlock to happen?",
                    "How does a Hash Map work under the hood, and how are hash collisions resolved (e.g., separate chaining vs open addressing)?",
                    "What is CORS (Cross-Origin Resource Sharing) and why do modern web browsers enforce it on cross-domain fetch requests?"
                ];
            } elseif ($isAmazon) {
                $bank = [
                    "Explain how database indexing works, and what is the difference between a Clustered and Non-Clustered index.",
                    "What is the difference between horizontal scaling and vertical scaling, and how does an Elastic Load Balancer balance traffic?",
                    "What are the ACID properties in database management systems and why are they critical for financial transactions?",
                    "Explain the role of asynchronous message queues (like AWS SQS or RabbitMQ) in decoupling order checkout from billing.",
                    "What is the difference between authentication and authorization in application security (e.g., OAuth 2.0 vs JWT tokens)?",
                    "What is the purpose of Docker and containerization compared to running applications in traditional virtual machines?"
                ];
            } elseif ($isMsft) {
                $bank = [
                    "What is the difference between an Abstract Class and an Interface in OOP, and when should you design with each?",
                    "Explain the Single Responsibility Principle and Open-Closed Principle from the SOLID guidelines with code examples.",
                    "Explain the difference between SQL INNER JOIN, LEFT JOIN, RIGHT JOIN, and FULL OUTER JOIN with result set comparisons.",
                    "What are the ACID properties in database management systems and why are they critical for transactions?",
                    "Explain the Model-View-Controller (MVC) architectural pattern in web and enterprise application development.",
                    "What is the difference between synchronous and asynchronous code execution, and how do Promises/Tasks prevent UI freezes?"
                ];
            } elseif ($isService) {
                $bank = [
                    "What is database normalization, and why are 1NF, 2NF, and 3NF important for relational data integrity?",
                    "Explain the difference between Clustered and Non-Clustered indexes in SQL Server or MySQL.",
                    "What is the difference between the 'GROUP BY' and 'HAVING' clauses in SQL queries?",
                    "Explain runtime polymorphism through method overriding and dynamic method dispatch in OOP.",
                    "How does basic Garbage Collection automatically reclaim unused memory in Java or .NET?",
                    "What is the difference between a Checked Exception and an Unchecked Exception in software development?"
                ];
            } else {
                $bank = [
                    "Explain how database indexing works, and what is the difference between a Clustered and Non-Clustered index.",
                    "What are the ACID properties in database management systems and why are they critical for transactions?",
                    "What is the difference between an Abstract Class and an Interface in OOP, and when should you use each?",
                    "Explain the fundamental differences between TCP and UDP protocols and their respective use cases.",
                    "What is a deadlock in an operating system, and what four conditions are necessary for deadlock to happen?",
                    "What are common HTTP status codes (200, 201, 400, 401, 403, 404, 500, 502) and what does each signify?",
                    "What is CORS (Cross-Origin Resource Sharing) and why do modern web browsers enforce it?",
                    "Explain the Model-View-Controller (MVC) architectural pattern in web application development.",
                    "What is the difference between authentication and authorization in application security?",
                    "How does a Hash Map work under the hood, and how are hash collisions resolved (e.g., chaining vs open addressing)?",
                    "What is database normalization, and why are 1NF, 2NF, and 3NF important for relational data integrity?",
                    "What is the purpose of Docker and containerization compared to running applications in traditional virtual machines?"
                ];
            }
        }
    }

    shuffle($bank);
    return array_slice($bank, 0, $totalQuestions);
}

