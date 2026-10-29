<?php
/**
 * Centralized AI Prompt Engine for Gemini.
 * 
 * Defines elite personas, difficulty-calibrated rubrics, and structured
 * prompts for question generation, interview evaluation, and career coaching.
 */

class GeminiPrompts
{
    /**
     * Primary system instruction that sets the AI's core persona and standards.
     *
     * @param string|null $context Additional contextual domain (e.g., 'technical', 'behavioral', 'hr')
     * @return string
     */
    public static function getSystemInstruction(?string $context = null): string
    {
        return <<<SYS
You are ApexPrep AI, an elite Senior Technical Hiring Manager and Executive Interview Coach with over 15 years of industry experience across Tier-1 tech companies and fast-paced engineering organizations.

Your core objectives:
1. Conduct realistic, rigorous, and industry-calibrated mock interviews.
2. Demand practical depth, architectural understanding, and trade-off awareness over rote textbook memorization.
3. Provide constructive, high-value feedback that shows candidates precisely how to level up to the top 1% standard.
4. Value structured communication (e.g., STAR framework for situational questions, first-principles reasoning for technical questions).
5. Always strictly adhere to requested output schemas (such as raw JSON without conversational filler or markdown wrapping).
SYS;
    }

    /**
     * Build company-specific interview blueprint guidelines.
     */
    public static function getCompanyGuideline(string $companyName, string $categoryName, string $difficulty = 'Intermediate'): string
    {
        $comp = trim($companyName);
        if ($comp === '' || strcasecmp($comp, 'General') === 0) {
            return "";
        }

        $cLower    = strtolower($comp);
        $catLower  = strtolower($categoryName);
        $diffLower = strtolower($difficulty);
        $isBeginner = in_array($diffLower, ['beginner', 'entry', 'junior'], true);
        $isAdvanced = in_array($diffLower, ['advanced', 'senior', 'expert', 'difficult', 'hard'], true);
        $isMedium   = !$isBeginner && !$isAdvanced;

        $specifics = "";

        if ($isBeginner) {
            // Level 1: Beginner (Foundational & Accessible)
            if (str_contains($cLower, 'google')) {
                $specifics = <<<COMP
TARGET COMPANY FOCUS: GOOGLE (BEGINNER LEVEL)
- Target Standard: Entry-level / campus placement standard.
- Technical & Coding: Foundational programming logic on basic structures (arrays, strings, simple loops, conditionals).
- Behavioral / HR: "Googlyness" at entry level — eagerness to learn, curiosity, intellectual humility, and teamwork.
- Forbidden: DO NOT ask planetary-scale distributed systems, multi-datacenter architecture, complex dynamic programming, or intricate graph algorithms.
COMP;
            } elseif (str_contains($cLower, 'amazon')) {
                $specifics = <<<COMP
TARGET COMPANY FOCUS: AMAZON (BEGINNER LEVEL)
- Target Standard: SDE-1 / entry-level campus standard.
- Technical & Coding: Core programming basics (arrays, strings, basic OOPs principles, simple hash map lookups, standard SQL queries, REST basics).
- Behavioral / HR: Approachable student-level STAR questions on Amazon Leadership Principles (Customer Obsession: helping a user or peer; Ownership: taking responsibility for a project; Learn and Be Curious: learning a new framework).
- Forbidden: DO NOT ask 50,000 QPS flash sale checkout architectures, distributed rate-limiters, or complex microservices chaos engineering.
COMP;
            } elseif (str_contains($cLower, 'microsoft')) {
                $specifics = <<<COMP
TARGET COMPANY FOCUS: MICROSOFT (BEGINNER LEVEL)
- Target Standard: College placement and entry-level junior standard.
- Technical & Coding: Core OOP principles (Inheritance, Polymorphism, Encapsulation, Abstraction), basic C#/Java/Python syntax, simple data structures (Lists, Arrays, Stacks).
- Behavioral / HR: Growth mindset, customer empathy, teamwork across diverse groups, and learning from project setbacks.
- Forbidden: DO NOT ask real-time collaborative document editors or complex distributed cloud architectures.
COMP;
            } elseif (str_contains($cLower, 'tcs') || str_contains($cLower, 'tata') || str_contains($cLower, 'infosys') || str_contains($cLower, 'wipro') || str_contains($cLower, 'accenture') || str_contains($cLower, 'deloitte') || str_contains($cLower, 'cognizant') || str_contains($cLower, 'ibm')) {
                $title = strtoupper($comp);
                $specifics = <<<COMP
TARGET COMPANY FOCUS: $title (BEGINNER / CAMPUS RECRUITMENT)
- Core Focus: Standard campus placement assessment and foundational technical interview rounds.
- Technical: Foundational CS concepts: OOPs 4 pillars, basic SQL (SELECT, WHERE, JOINs), Operating Systems (process vs thread), Computer Networks (OSI layers, TCP vs UDP), and basic programming.
- Behavioral / HR: Adaptability, college project coordination, willingness to learn new technologies.
- Aptitude: Clean, straightforward calculations using simple whole numbers that candidates can comfortably solve on paper.
COMP;
            } else {
                $title = strtoupper($comp);
                $specifics = <<<COMP
TARGET COMPANY FOCUS: $title (BEGINNER LEVEL)
- Foundational concepts, basic programming logic, college/personal projects, and clear communication without complex architecture traps.
COMP;
            }
        } elseif ($isMedium) {
            // Level 2: Medium / Intermediate (Little more difficult than Beginner)
            if (str_contains($cLower, 'google')) {
                $specifics = <<<COMP
TARGET COMPANY FOCUS: GOOGLE (MEDIUM / INTERMEDIATE LEVEL)
- CALIBRATION: Only a LITTLE more difficult than Beginner. Practical everyday engineering questions.
- Technical & Coding: Clean algorithmic design using standard structures (arrays, strings, two pointers, sliding window, hash maps, simple stacks). Focus on readable code and edge case awareness.
- Behavioral / HR: Standard "Googlyness" — collaborative problem solving, handling project ambiguity, teamwork, and learning from mistakes.
- Forbidden: DO NOT ask multi-datacenter distributed systems, complex dynamic programming, or obscure math puzzles.
COMP;
            } elseif (str_contains($cLower, 'amazon')) {
                $specifics = <<<COMP
TARGET COMPANY FOCUS: AMAZON (MEDIUM / INTERMEDIATE LEVEL)
- CALIBRATION: Only a LITTLE more difficult than Beginner. Practical software development questions.
- Technical & Coding: Everyday data structures (hash tables, two pointers, stacks, basic linked lists), practical OOP design, basic database query optimization (joins, indexing), and standard REST APIs.
- Behavioral / HR: Standard STAR format questions on Amazon Leadership Principles (Customer Obsession, Ownership, Bias for Action, Deliver Results, Earn Trust).
- Forbidden: DO NOT ask 50,000 QPS flash sale checkout, distributed caching clusters, or chaos engineering.
COMP;
            } elseif (str_contains($cLower, 'microsoft')) {
                $specifics = <<<COMP
TARGET COMPANY FOCUS: MICROSOFT (MEDIUM / INTERMEDIATE LEVEL)
- CALIBRATION: Only a LITTLE more difficult than Beginner.
- Technical & Coding: Practical OOP design patterns, modular architecture, SQL joins and normalization basics, synchronous vs asynchronous programming, and basic memory management.
- Behavioral / HR: Growth Mindset, collaborating with teammates, handling changing project requirements, and constructive feedback.
- Forbidden: DO NOT ask complex distributed cloud infrastructure or high-concurrency lock-free algorithms.
COMP;
            } elseif (str_contains($cLower, 'tcs') || str_contains($cLower, 'tata') || str_contains($cLower, 'infosys') || str_contains($cLower, 'wipro') || str_contains($cLower, 'accenture') || str_contains($cLower, 'deloitte') || str_contains($cLower, 'cognizant') || str_contains($cLower, 'ibm')) {
                $title = strtoupper($comp);
                $specifics = <<<COMP
TARGET COMPANY FOCUS: $title (MEDIUM / INTERMEDIATE LEVEL)
- Core Focus: Practical enterprise application engineering and placement assessment standards.
- Technical: OOPs design, DBMS (SQL joins, normalization, indexing, ACID properties), Operating Systems (processes, threads, deadlock conditions), and Java/C++/Python coding.
- Behavioral / HR: Adaptability, project team coordination, and client communication.
- Aptitude: Standard quantitative problems (percentages, speed-distance-time, work-time, probability).
COMP;
            } else {
                $title = strtoupper($comp);
                $specifics = <<<COMP
TARGET COMPANY FOCUS: $title (MEDIUM / INTERMEDIATE LEVEL)
- CALIBRATION: Only a LITTLE more difficult than Beginner. Practical, standard software development concepts and teamwork.
COMP;
            }
        } else {
            // Level 3: Difficult / Advanced (Little more difficult than Medium)
            if (str_contains($cLower, 'google')) {
                $specifics = <<<COMP
TARGET COMPANY FOCUS: GOOGLE (DIFFICULT / ADVANCED LEVEL)
- CALIBRATION: Only a LITTLE more difficult than Medium. Test solid algorithmic intuition and clean tradeoffs without obscure trivia.
- Technical & Coding: Solvable LeetCode Medium challenges (binary search, intervals, tree traversal, monotonic stacks, simple greedy/DP). Require clear Big-O time and space complexity analysis.
- Behavioral / HR: Navigating complex technical tradeoffs, leading team initiatives, resolving differing technical viewpoints, and handling setbacks.
- Strictly Forbidden: DO NOT ask planetary load architectures, 50k QPS chaos engineering, distributed consensus, compiler internals, or LeetCode Hard esoteric math.
COMP;
            } elseif (str_contains($cLower, 'amazon')) {
                $specifics = <<<COMP
TARGET COMPANY FOCUS: AMAZON (DIFFICULT / ADVANCED LEVEL)
- CALIBRATION: Only a LITTLE more difficult than Medium.
- Technical & Coding: Core data structures (BSTs, heaps, binary search, intervals, simple dynamic programming), database transactions (ACID properties, indexing tradeoffs), and REST service design.
- Behavioral / HR: Deep STAR questions on Amazon Leadership Principles (Have Backbone; Disagree and Commit, Dive Deep, Customer Obsession under deadlines, Ownership in high-priority projects).
- Strictly Forbidden: DO NOT ask 50,000 QPS flash sale checkout architectures, multi-region distributed rate-limiters, or chaos engineering.
COMP;
            } elseif (str_contains($cLower, 'microsoft')) {
                $specifics = <<<COMP
TARGET COMPANY FOCUS: MICROSOFT (DIFFICULT / ADVANCED LEVEL)
- CALIBRATION: Only a LITTLE more difficult than Medium.
- Technical & Coding: Clean Object-Oriented design, interface decoupling, concurrency basics (locks vs deadlocks), database transaction isolation, and asynchronous pipelines.
- Behavioral / HR: Demonstrating a Growth Mindset during difficult technical challenges, cross-functional collaboration, and mentoring teammates.
- Strictly Forbidden: DO NOT ask complex distributed document editors or multi-datacenter cloud pipelines.
COMP;
            } elseif (str_contains($cLower, 'tcs') || str_contains($cLower, 'tata') || str_contains($cLower, 'infosys') || str_contains($cLower, 'wipro') || str_contains($cLower, 'accenture') || str_contains($cLower, 'deloitte') || str_contains($cLower, 'cognizant') || str_contains($cLower, 'ibm')) {
                $title = strtoupper($comp);
                $specifics = <<<COMP
TARGET COMPANY FOCUS: $title (DIFFICULT / ADVANCED LEVEL)
- Core Focus: Advanced placement assessment & senior junior engineer hiring patterns.
- Technical: In-depth OOP principles, complex SQL queries (subqueries, window functions, indexing), OS deadlocks, socket connections, and algorithmic problem solving.
- Behavioral / HR: Project leadership, resolving team conflicts, and delivering quality code under tight deadlines.
- Aptitude: Multi-step quantitative puzzles, relative speed, conditional probability, and logical seating deductions.
COMP;
            } else {
                $title = strtoupper($comp);
                $specifics = <<<COMP
TARGET COMPANY FOCUS: $title (DIFFICULT / ADVANCED LEVEL)
- CALIBRATION: Only a LITTLE more difficult than Medium. Solid algorithmic tradeoffs, system fundamentals, and leadership under pressure.
COMP;
            }
        }

        return "\n--- COMPANY TARGETING DIRECTIVE ---\n" . $specifics . "\n";
    }

    /**
     * Generate an intelligent prompt for creating interview questions.
     *
     * @param string $categoryName Topic or role (e.g., "Full Stack Web Development", "Python", "Data Science")
     * @param string $difficulty   "Beginner", "Intermediate", or "Advanced"
     * @param int    $totalQuestions Number of questions to produce
     * @param string $companyName  Target Company (e.g. "Google", "Amazon", "Microsoft", "TCS", "General")
     * @param string|null $roleTarget Optional specific role target
     * @return string
     */
    public static function buildQuestionPrompt(string $categoryName, string $difficulty, int $totalQuestions, string $companyName = 'General', ?string $roleTarget = null): string
    {
        $diffLower = strtolower($difficulty);
        $catLower  = strtolower($categoryName);
        $companyDirectives = self::getCompanyGuideline($companyName, $categoryName, $difficulty);
        $companyHeader = (!empty($companyName) && strcasecmp($companyName, 'General') !== 0)
            ? "Target Company: " . trim($companyName) . "\n"
            : "";
        $isCoding = str_contains($catLower, 'coding')
                 || str_contains($catLower, 'dsa')
                 || str_contains($catLower, 'algorithm')
                 || str_contains($catLower, 'leetcode')
                 || str_contains($catLower, 'data structure')
                 || str_contains($catLower, 'problem solving');

        $randomSeed = substr(md5(uniqid((string)mt_rand(), true)), 0, 8);

        // Dedicated, strict prompt for Coding & DSA (LeetCode / HackerRank style problems)
        if ($isCoding) {
            $compLower = strtolower(trim($companyName));
            $isGoogle = str_contains($compLower, 'google');
            $isAmazon = str_contains($compLower, 'amazon');
            $isMsft   = str_contains($compLower, 'microsoft');
            $isService = str_contains($compLower, 'tcs') || str_contains($compLower, 'infosys') || str_contains($compLower, 'wipro') || str_contains($compLower, 'accenture') || str_contains($compLower, 'cognizant');

            if (in_array($diffLower, ['beginner', 'entry', 'junior'], true)) {
                $targetTitle = 'Beginner-Level Candidate (Basic logic, loops, strings, arrays)';
                $targetReqs = "- Test basic programming concepts using loops, conditions, strings, or arrays.\n- Do NOT require advanced algorithms or complex data structures.";
                if (!empty($companyName) && strcasecmp($companyName, 'General') !== 0) {
                    $targetReqs .= "\n- REDUCE COMPLEXITY FOR PRACTICE: Even though the target company is {$companyName}, the candidate selected BEGINNER level. Strictly select an entry-level problem (such as basic array manipulation, string reversal, palindrome, finding min/max, or counting occurrences). DO NOT generate LeetCode Hard, complex trees, graphs, or dynamic programming questions.";
                }

                if ($isGoogle) {
                    $topicPool = [
                        'Valid Palindrome: check if string is palindrome ignoring non-alphanumeric and case',
                        'Find Pivot Index where sum of numbers to left equals sum of numbers to right',
                        'Length of Last Word in a sentence string',
                        'Move Zeroes to end of array in-place maintaining relative order',
                        'Intersection of Two Arrays returning unique common elements',
                        'Plus One: increment large integer represented as array of digits',
                        'Find Missing Number in array containing numbers from 0 to N',
                        'Reverse String in-place without built-in helper functions',
                        'Check Power of Two: determine if integer is a power of 2',
                        'Check Sorted Array: verify if array is sorted in ascending order',
                        'Defanging an IP Address: replace every dot with [.]',
                        'Running Sum of 1D Array: calculate prefix running sums in-place',
                        'Richest Customer Wealth: find maximum wealth sum across customer bank accounts',
                        'Jewels and Stones: count how many stones in hand are also jewels',
                        'Shuffle the Array: rearrange array in alternating form [x1, y1, x2, y2]'
                    ];
                } elseif ($isAmazon) {
                    $topicPool = [
                        'Two Sum: find indices of two numbers that add up to target',
                        'Best Time to Buy and Sell Stock: single day transaction for maximum profit',
                        'Single Number: find the element that appears only once using XOR',
                        'Valid Anagram: check if two strings are anagrams of each other',
                        'Remove Duplicates from Sorted Array in-place returning unique count',
                        'Reverse a String in-place using two pointers',
                        'Roman to Integer conversion',
                        'FizzBuzz: return Fizz for multiples of 3, Buzz for 5, FizzBuzz for both',
                        'Count Vowels and Consonants in a given string',
                        'Find Maximum and Minimum element in an integer array',
                        'Contains Duplicate: check if any integer appears at least twice using Hash Set',
                        'Number of Good Pairs: count pairs (i, j) where nums[i] == nums[j] and i < j',
                        'Maximum Units on a Truck: greedy packaging of box units',
                        'Find Smallest Letter Greater Than Target using character comparison',
                        'Kids With the Greatest Number of Candies: boolean comparison array'
                    ];
                } elseif ($isMsft) {
                    $topicPool = [
                        'Reverse Words in a String sentence while maintaining single space separation',
                        'Valid Parentheses: check if brackets () [] {} are closed in correct order',
                        'Palindrome Number: check if integer reads same forward and backward without string conversion',
                        'Majority Element: find element appearing more than n/2 times',
                        'Pascal\'s Triangle: generate first n rows of Pascal\'s triangle',
                        'Power of Two: check if n == 2^k using bitwise operations',
                        'Reverse String in-place without auxiliary space',
                        'Find Second Largest element in array in single pass',
                        'Excel Sheet Column Number: convert column title string like AB to 28',
                        'Valid Perfect Square: check if integer is a perfect square without sqrt()',
                        'Merge Sorted Array into nums1 in-place in O(M + N) time',
                        'Remove Element: remove all occurrences of val in-place',
                        'Longest Common Prefix among an array of strings',
                        'Count Segments in String separated by whitespace',
                        'Self Dividing Numbers: find numbers divisible by every digit they contain'
                    ];
                } elseif ($isService) {
                    $topicPool = [
                        'Check Prime Number: determine if integer N is prime in O(sqrt(N))',
                        'Armstrong Number: check if sum of digits raised to power of count equals N',
                        'Fibonacci Sequence: generate Fibonacci numbers up to N terms',
                        'Factorial of N: calculate N! for non-negative integer N',
                        'Check Leap Year: determine if given year is a leap year',
                        'String Palindrome: check if string reads the same backward as forward',
                        'Sum of Digits: calculate sum of all digits of positive integer N',
                        'Count Vowels and Consonants in a given string',
                        'Find Second Largest element in array without full sorting',
                        'Greatest Common Divisor (GCD / HCF) of two numbers using Euclidean algorithm',
                        'Find LCM of Two Numbers using GCD formula',
                        'Perfect Number: check if sum of all proper positive divisors equals N',
                        'Harshad (Niven) Number: check if number is divisible by sum of its digits',
                        'Strong Number: check if sum of factorials of each digit equals N',
                        'Binary to Decimal: convert binary string representation to decimal integer',
                        'Count Occurrences of an Element in an integer array'
                    ];
                } else {
                    $topicPool = [
                        'Reverse a string in-place without built-in helpers',
                        'Check whether a string or integer is a palindrome',
                        'Find the largest and smallest element in an integer array',
                        'Find the second largest number in an array without full sorting',
                        'Check whether an integer N is a prime number',
                        'Find factorial of a non-negative integer N',
                        'Generate Fibonacci sequence up to N terms',
                        'Calculate the sum and average of elements in an array',
                        'Count the number of vowels and consonants in a string',
                        'Check if a given number is an Armstrong (narcissistic) number',
                        'Remove duplicate elements from a sorted integer array',
                        'Calculate the frequency of each character in a given string',
                        'FizzBuzz generator: return specific strings for multiples of 3 and 5',
                        'Check whether two strings are valid anagrams of each other',
                        'Capitalize the first letter of each word in a sentence',
                        'Filter and return all even numbers from an array',
                        'Compute the sum of all individual digits in an integer',
                        'Find the intersection (common elements) of two unsorted arrays',
                        'Linear search: return index of target value or -1 if absent',
                        'Swap two integer variables without using a third temporary variable',
                        'Calculate power of a number x raised to exponent n (x^n)',
                        'Check whether an array of integers is sorted in ascending order',
                        'Find the missing number in an array containing numbers from 0 to N',
                        'Count the occurrences of a specific element in an array',
                        'Convert temperature from Celsius to Fahrenheit',
                        'Find all divisors and factors of a given positive integer',
                        'Determine whether a given year is a leap year',
                        'Calculate running cumulative prefix sum of an array',
                        'Check if a number is a Perfect Number',
                        'Count total number of words in a sentence string',
                        'Check whether an integer is a power of two using bitwise or loop',
                        'Find greatest common divisor (GCD / HCF) of two numbers',
                        'Count uppercase, lowercase, numbers, and special characters in a string',
                        'Rotate an array to the right by one position',
                        'Find the difference between maximum and minimum elements in an array'
                    ];
                }
            } elseif (in_array($diffLower, ['advanced', 'senior', 'expert', 'difficult', 'hard'], true)) {
                $targetTitle = 'Difficult / Advanced Candidate (Solvable Standard Interview Challenges: Intervals, 3Sum, BST, Simple DP)';
                $targetReqs = "- Test algorithmic tradeoffs that are only a LITTLE more difficult than Medium.\n- Use standard, solvable LeetCode Medium interview problems (intervals, two pointers, binary search variations, simple 1D DP, binary search trees).\n- Strictly FORBIDDEN: Do NOT ask LeetCode Hard, planetary scale, multi-threaded algorithms, Tarjan's bridges, or complex 2D DP.";

                if ($isGoogle) {
                    $topicPool = [
                        'Search in Rotated Sorted Array in O(log N) runtime',
                        'Product of Array Except Self without division in O(N) time',
                        'Longest Consecutive Sequence in unsorted array using Hash Set in O(N)',
                        'Lowest Common Ancestor of two nodes in Binary Search Tree',
                        'Container With Most Water using Two Pointers in O(N)',
                        '3Sum: find all unique triplets that sum to 0 using sorting and two pointers',
                        'Spiral Matrix: spiral traversal of m x n matrix',
                        'Sort Colors (Dutch National Flag) in-place in O(N) time',
                        'Daily Temperatures: days until warmer temperature using Monotonic Stack',
                        'Find Peak Element in O(log N) using Binary Search',
                        'Subarray Sum Equals K using Prefix Sums with Hash Map',
                        'Validate Binary Search Tree using in-order recursion',
                        'Longest Substring Without Repeating Characters using Sliding Window',
                        'Reorganize String so that no two adjacent characters are same',
                        'Evaluate Reverse Polish Notation using Stack',
                        'Merge Overlapping Intervals in array of intervals in O(N log N)'
                    ];
                } elseif ($isAmazon) {
                    $topicPool = [
                        'Merge Overlapping Intervals in array of intervals in O(N log N)',
                        'Daily Temperatures: days until warmer temperature using Monotonic Stack',
                        'Subarray Sum Equals K using Prefix Sums with Hash Map in O(N)',
                        'House Robber: maximum money robbed without triggering adjacent alarm using 1D DP',
                        'Coin Change: minimum coins needed to make up amount using 1D DP',
                        'Min Stack: stack with constant time O(1) minimum retrieval',
                        'Word Break: determine if string can be segmented into dictionary words',
                        'Top K Frequent Elements using Min-Heap or Bucket Sort',
                        'Group Anagrams together using Hash Map with sorted string keys',
                        'Rotten Oranges: minimum minutes until all fresh oranges rot (BFS simulation)',
                        'Asteroid Collision: stack simulation of colliding directional asteroids',
                        'Kth Largest Element in an Array using Quickselect or Min-Heap',
                        'Partition Labels: greedy partition so each letter appears in at most one part',
                        'Product of Array Except Self without division in O(N) time'
                    ];
                } elseif ($isMsft) {
                    $topicPool = [
                        '3Sum: unique triplets that sum to 0 using sorting and two pointers',
                        'Search in Rotated Sorted Array in O(log N) time',
                        'Spiral Matrix: traverse m x n matrix in spiral order',
                        'Sort Colors: sort array with 0, 1, 2 in-place in single pass',
                        'Implement Queue using Stacks with amortized O(1) operations',
                        'Climbing Stairs: distinct ways to climb n stairs using 1D DP',
                        'Validate Binary Search Tree using recursion and range bounds',
                        'Lowest Common Ancestor in a Binary Search Tree',
                        'Product of Array Except Self without division in O(N)',
                        'Reverse Linked List II: reverse sublist from position left to right',
                        'Set Matrix Zeroes in-place without auxiliary 2D space',
                        'Simplify Path: canonical Unix path resolution using Stack',
                        'Letter Combinations of a Phone Number using Backtracking',
                        'Binary Tree Zigzag Level Order Traversal using BFS Queue'
                    ];
                } elseif ($isService) {
                    $topicPool = [
                        'Subarray with Given Sum: find continuous subarray adding to target sum',
                        'Rotate Array to Right by K Steps in-place in O(N) time',
                        'Longest Common Prefix among an array of strings',
                        'Balanced Parentheses: verify balanced brackets using a Stack',
                        'Find Pair with Target Sum in sorted array using Two Pointers',
                        'Matrix Diagonal Sum: sum of primary and secondary diagonals in square matrix',
                        'Kth Largest Element in an array without full sorting',
                        'Next Greater Element for each array item using a Stack',
                        'Kadane\'s Algorithm returning maximum contiguous subarray sum and indices',
                        'Count and Say: generate the nth term of the sequence',
                        'Longest Palindromic Substring in a string using expand around center',
                        'Inversion Count of an Array using modified merge sort',
                        'Maximum Product Subarray: find contiguous subarray with largest product'
                    ];
                } else {
                    $topicPool = [
                        '3Sum: unique triplets that sum to zero using sorting and two pointers',
                        'Container With Most Water using Two Pointers in O(N) time',
                        'Product of Array Except Self without division in O(N) time',
                        'Search in Rotated Sorted Array in O(log N) time',
                        'Merge Overlapping Intervals in an array of intervals in O(N log N)',
                        'Subarray Sum Equals K using Prefix Sums with Hash Map in O(N)',
                        'Longest Consecutive Sequence in unsorted array in O(N) using Hash Set',
                        'Top K Frequent Elements using Min-Heap or Bucket Sort',
                        'Min Stack: stack with constant time O(1) minimum retrieval',
                        'Climbing Stairs: distinct ways to climb n stairs using 1D DP',
                        'Lowest Common Ancestor of two nodes in Binary Search Tree',
                        'Spiral Matrix traversal in 2D array',
                        'Sort Colors (Dutch National Flag) in-place in O(N) time',
                        'Daily Temperatures: days to warmer temperature using Monotonic Stack',
                        'House Robber: maximum money robbed using 1D Dynamic Programming',
                        'Coin Change: minimum coins needed using 1D Dynamic Programming',
                        'Letter Combinations of a Phone Number using Backtracking',
                        'Word Break: segment string into dictionary words using 1D DP',
                        'Decode String with nested repetition patterns like 3[a2[c]] using Stack',
                        'Validate Binary Search Tree using recursion and range bounds',
                        'Kth Largest Element in an array using Quickselect or Min-Heap',
                        'Rotate Array to the right by k steps in O(N) time and O(1) space',
                        'Find First and Last Position of element in sorted array in O(log N)',
                        'Implement Queue using two Stacks with amortized O(1) operations'
                    ];
                }
            } else {
                $targetTitle = 'Medium / Intermediate Candidate (Two Pointers, Sliding Window, Hash Maps, Stacks, Basic Lists)';
                $targetReqs = "- Test practical, accessible problem-solving that is only a LITTLE more difficult than Beginner.\n- Standard interview questions: two pointers, simple hash map lookup, sliding window, basic linked list, or basic stack.\n- Require clear time and space complexity bounds.";

                if ($isGoogle) {
                    $topicPool = [
                        'Longest Substring Without Repeating Characters using Sliding Window',
                        'Find First and Last Position of element in sorted array in O(log N)',
                        'Container With Most Water using Two Pointers in O(N)',
                        '3Sum: unique triplets summing to zero',
                        'Valid Parentheses bracket verification using a Stack',
                        'Binary Search in sorted array in O(log N) time',
                        'Move Zeroes to end of array in-place maintaining relative order',
                        'Reverse a Singly Linked List iteratively',
                        'Middle of the Linked List using slow and fast pointers',
                        'Squares of a Sorted Array using Two Pointers in O(N)',
                        'First Bad Version: binary search on version API',
                        'Invert Binary Tree recursively',
                        'Peak Index in a Mountain Array in O(log N)',
                        'Remove Nth Node From End of List using Two Pointers',
                        'Maximum Average Subarray I with sliding window of length k'
                    ];
                } elseif ($isAmazon) {
                    $topicPool = [
                        'Top K Frequent Elements in an array using Hash Map and Min-Heap',
                        'Group Anagrams together from an array of strings',
                        'Kth Largest Element in an array using Quickselect or Min-Heap',
                        'Maximum Subarray sum using Kadane\'s algorithm in O(N)',
                        'Merge Two Sorted Linked Lists into one sorted list',
                        'Reverse a Singly Linked List iteratively',
                        'Binary Search in sorted array in O(log N)',
                        'Two Sum using Hash Map for O(N) lookup',
                        'Search Insert Position in sorted array',
                        'Contains Duplicate: return boolean if array has duplicates',
                        'Intersection of Two Arrays II preserving element frequency',
                        'Majority Element in array using Boyer-Moore Voting algorithm',
                        'Best Time to Buy and Sell Stock with single transaction',
                        'Product of Array Except Self without using division',
                        'String to Integer (atoi) with whitespace and sign handling'
                    ];
                } elseif ($isMsft) {
                    $topicPool = [
                        'Reverse a Singly Linked List iteratively in O(N) time and O(1) space',
                        'Binary Search in sorted array in O(log N) time',
                        'Merge Two Sorted Linked Lists into one sorted list',
                        'Invert Binary Tree: invert left and right child nodes recursively',
                        'Maximum Depth of Binary Tree using recursion',
                        'Middle of the Linked List using two pointers',
                        'Valid Parentheses bracket matching using a Stack',
                        'Maximum Subarray sum using Kadane\'s algorithm',
                        'Remove Duplicates from Sorted Array II: at most twice per element',
                        'Search Insert Position in sorted array in O(log N)',
                        'First Unique Character in a String using frequency count',
                        'Intersection of Two Linked Lists: find intersecting node',
                        'Path Sum: check if root-to-leaf path sums to targetSum',
                        'Symmetric Tree: check whether binary tree is a mirror of itself'
                    ];
                } elseif ($isService) {
                    $topicPool = [
                        'Character Frequency Count in a given string',
                        'Remove Duplicates from Array returning unique count',
                        'Reverse Words in a Sentence string',
                        'Find Second Largest element in array in single pass',
                        'Swap Two Variables without using a third temporary variable',
                        'Binary Search in a sorted array in O(log N) time',
                        'Rotate Array to Right by One Position',
                        'Linear Search: find index of target in array',
                        'Missing Number in Array: find missing number from 0 to N using sum',
                        'Rotate Array to Right by K Steps using three array reversals',
                        'Subarray with 0 Sum: detect if prefix sums repeat',
                        'Check Anagram of two strings using sorting or frequency array',
                        'Palindrome Linked List: check if singly linked list is palindrome'
                    ];
                } else {
                    $topicPool = [
                        'Two Sum using Hash Map for O(N) time lookup',
                        'Valid Parentheses bracket matching using a Stack',
                        'Maximum Subarray sum using Kadane\'s algorithm in O(N) time',
                        'Valid Palindrome alphanumeric verification using Two Pointers',
                        'Longest Substring Without Repeating Characters using Sliding Window',
                        'Reverse a Singly Linked List iteratively',
                        'Best Time to Buy and Sell Stock with single transaction in O(N)',
                        'Merge Two Sorted Linked Lists into one sorted list',
                        'Binary Search in sorted array in O(log N) time',
                        'Remove Duplicates from Sorted Array in-place',
                        'Move Zeroes to End of array in-place maintaining relative order',
                        'Intersection of Two Arrays returning unique common elements',
                        'Plus One: increment large integer represented as digits array',
                        'Single Number: find element appearing once using bitwise XOR',
                        'Majority Element in an array (Boyer-Moore voting)',
                        'Contains Duplicate in array using Hash Set',
                        'Roman to Integer conversion',
                        'Sqrt(x) integer square root using Binary Search',
                        'Find Pivot Index where sum of numbers to left equals sum to right',
                        'Implement Stack using Queues with basic operations'
                    ];
                }
            }

            // Shuffle topic pool dynamically in PHP for guaranteed session variety
            shuffle($topicPool);
            $selectedTopics = array_slice($topicPool, 0, max(12, $totalQuestions + 4));
            $topicBankList = "";
            foreach ($selectedTopics as $idx => $t) {
                $num = $idx + 1;
                $topicBankList .= "{$num}. {$t}\n";
            }

            return <<<PROMPT
Role: Elite Technical Coding Interviewer & Competitive Programming Specialist.
Category: $categoryName
{$companyHeader}Difficulty Level: $difficulty
Session Randomization Seed: #$randomSeed

Target Profile: $targetTitle
$targetReqs
$companyDirectives

CRITICAL ANTI-REPETITION DIRECTIVE:
1. The candidate is taking a fresh practice session. You MUST select and generate $totalQuestions completely distinct, hands-on coding challenges.
2. DO NOT repeat the same default questions (e.g. do NOT always generate Two Sum or Reverse String).
3. Draw problems randomly from the freshly shuffled topic candidate pool below, choosing different problem families:
$topicBankList
4. SHUFFLE the order of the generated questions so each session has a unique sequence.

STRICT MANDATORY RULES FOR EACH CODING CHALLENGE:
1. Every question MUST test hands-on algorithmic coding where the candidate writes an executable function.
2. ABSOLUTELY NO CONVERSATIONAL, ESSAY, DISCUSSION, OR TRIVIA QUESTIONS (e.g. NEVER ask "Explain how...", "How would you design...", "Imagine you are writing a function... How should you handle...", "What are the trade-offs...").
3. DO NOT provide the solution or implementation code.
4. Each question string in the JSON array MUST be self-contained and formatted cleanly with:
   [Problem Name]: [Clear problem statement explaining the task].
   Input Format: [Exact parameters and types]
   Output Format: [Expected return value and format]
   Example 1: Input: [sample input] -> Output: [expected output] (Explanation: [concise reason])
   Example 2: Input: [sample input] -> Output: [expected output]
   Constraints: [Target Time Complexity like O(N) or O(N log N), Space Complexity, and bounds].

Output Format:
Return ONLY a valid JSON array of strings containing exactly $totalQuestions coding challenges.
Do NOT include markdown formatting fences (such as ```json), preambles, or commentary.
PROMPT;
        }

        // Exactly 4 Interview Categories: Technical Core, Coding & DSA (handled above), HR & Behavioral, Aptitude & Logic
        $isHR = str_contains($catLower, 'hr') || str_contains($catLower, 'behavioral');
        $isAptitude = str_contains($catLower, 'aptitude') || str_contains($catLower, 'logic');
        $isBeginner = in_array($diffLower, ['beginner', 'entry', 'junior'], true);
        $isAdvanced = in_array($diffLower, ['advanced', 'senior', 'expert', 'difficult', 'hard'], true);
        $isMedium   = !$isBeginner && !$isAdvanced;

        $compLower = strtolower(trim($companyName));
        $isGoogle  = str_contains($compLower, 'google');
        $isAmazon  = str_contains($compLower, 'amazon');
        $isMsft    = str_contains($compLower, 'microsoft');
        $isService = str_contains($compLower, 'tcs') || str_contains($compLower, 'infosys') || str_contains($compLower, 'wipro') || str_contains($compLower, 'accenture') || str_contains($compLower, 'cognizant');

        $topicPool = [];

        if ($isHR) {
            $compNote = (!empty($companyName) && strcasecmp($companyName, 'General') !== 0) ? " for $companyName" : "";
            if ($isBeginner) {
                $levelInstructions = "BEGINNER LEVEL: Formulate approachable, encouraging behavioral questions (e.g. tell me about yourself, college or personal projects, teamwork, managing deadlines, learning new technologies, strengths and areas to improve).";
                if ($isGoogle) {
                    $topicPool = [
                        'Walk through your resume and what excites you about Google engineering culture',
                        'Describe a college or personal project where you took initiative and learned independently',
                        'How do you approach learning an unfamiliar framework or programming language',
                        'Describe a time you collaborated effectively in a diverse, cross-functional team',
                        'Tell me about an instance where you received constructive feedback and how you acted on it',
                        'What do you consider your greatest technical strength and one area you are working to develop',
                        'How do you prioritize academic deadlines when multiple assignments are due at once'
                    ];
                } elseif ($isAmazon) {
                    $topicPool = [
                        'Walk through your background and describe why you want to start your career at Amazon',
                        'Customer Obsession: describe a time you went out of your way to help a user, classmate, or peer',
                        'Ownership: tell me about a project where you took full responsibility for the outcome',
                        'Learn and Be Curious: describe a new framework, tool, or technology you explored on your own',
                        'Bias for Action: describe a situation where you had to make a decision quickly with limited time',
                        'Deliver Results: tell me about a time you met an aggressive academic deadline under pressure',
                        'Earn Trust: describe a situation where you worked effectively with someone of a different working style'
                    ];
                } elseif ($isMsft) {
                    $topicPool = [
                        'Walk through your resume and what draws you to Microsoft\'s engineering mission',
                        'Growth Mindset: describe a difficult concept or framework you struggled with and how you mastered it',
                        'Customer Empathy: tell me about a project where user feedback significantly changed your design',
                        'Describe a time you helped a peer or teammate who was falling behind or stuck on a bug',
                        'Share an experience where a project did not go as planned and what you learned from the setback',
                        'How do you handle constructive criticism on your code during peer review'
                    ];
                } elseif ($isService) {
                    $topicPool = [
                        'Tell me about yourself, your educational background, and why you chose computer science',
                        'Why do you want to start your IT career with our company and what do you know about our work',
                        'Are you comfortable working across different geographic locations and technology domains',
                        'Describe a team project you completed in college and your specific contributions',
                        'How do you manage stress and prioritize tasks during heavy examination or project schedules',
                        'What are your key strengths and what steps do you take to improve your weaknesses'
                    ];
                } else {
                    $topicPool = [
                        'Walk me through your resume and what inspired you to pursue software engineering',
                        'Tell me about a college or personal project you worked on recently and your primary role',
                        'Describe a time you had to learn an unfamiliar programming language or tool quickly',
                        'How do you organize your work when facing competing assignments or deadlines',
                        'Describe a time you worked in a team and how you coordinated with teammates',
                        'Can you share an experience where you received constructive feedback and how you responded',
                        'What do you consider your greatest strength and an area you are actively improving'
                    ];
                }
            } elseif ($isMedium) {
                $levelInstructions = "MEDIUM LEVEL (Only a LITTLE more difficult than Beginner): Practical workplace/project scenarios (e.g. adapting to changing requirements, handling tight delivery timelines, constructive feedback, resolving misunderstandings with teammates, learning a framework quickly).";
                if ($isGoogle) {
                    $topicPool = [
                        'Describe a situation where project requirements or specifications changed midway and how you adapted',
                        'Googlyness: tell me about a time you went above and beyond to help a teammate overcome a blocker',
                        'How do you handle receiving critical architectural or code feedback on a pull request',
                        'Describe a time you had to make a choice between delivering quickly versus writing clean code',
                        'Tell me about an instance where you identified an inefficiency in a team workflow and fixed it'
                    ];
                } elseif ($isAmazon) {
                    $topicPool = [
                        'Ownership: tell me about a time you identified a bug or problem outside your assigned area and fixed it',
                        'Customer Obsession: describe how direct user or candidate feedback altered your feature design',
                        'Earn Trust: describe a situation where you made a mistake on a project and how you communicated it',
                        'Bias for Action: describe a project decision you had to make when complete information was unavailable',
                        'Deliver Results: tell me about an unexpected roadblock that threatened your timeline and how you solved it'
                    ];
                } elseif ($isMsft) {
                    $topicPool = [
                        'Growth Mindset: describe how you turned an unexpected project bug or error into a positive learning experience',
                        'Collaboration: tell me about a time you worked with someone whose working style was very different from yours',
                        'Customer Empathy: describe how user feedback improved the usability of an application you built',
                        'Adapting to Change: describe how you responded when project priorities shifted unexpectedly',
                        'Giving Feedback: tell me about a time you gave constructive, actionable feedback to a peer'
                    ];
                } elseif ($isService) {
                    $topicPool = [
                        'Describe a situation where you had to quickly pick up an assigned programming language for a client project',
                        'Tell me about a time you worked with a difficult team member and still completed the project deliverable',
                        'How do you ensure zero defects and high attention to detail in your code and documentation',
                        'Describe how you handle routine or repetitive tasks while maintaining consistent quality',
                        'Tell me about a time you communicated project progress clearly to an instructor or team lead'
                    ];
                } else {
                    $topicPool = [
                        'Tell me about a time you took ownership of a challenging task or project from beginning to end',
                        'Describe a situation where you faced an unexpected technical roadblock and how you resolved it',
                        'Give an example of a time you worked with a teammate who had a very different perspective',
                        'Tell me about a mistake you made on a project or assignment. How did you fix and prevent it',
                        'Describe a situation where project requirements changed midway through development'
                    ];
                }
            } else {
                $levelInstructions = "DIFFICULT LEVEL (Only a LITTLE more difficult than Medium): Thoughtful situational scenarios (e.g. resolving strong technical disagreements with colleagues, taking proactive project ownership with minimal guidance, handling unexpected delays, maintaining code quality under delivery pressure).";
                if ($isGoogle) {
                    $topicPool = [
                        'Navigating Ambiguity: describe a project where the requirements were vague and how you drove clarity',
                        'Technical Disagreement: tell me about a strong disagreement with a peer on an approach and how you reached consensus',
                        'Balancing Speed vs Quality: how do you decide when software is ready to release under tight deadlines',
                        'Project Post-Mortem: describe a technical project failure or bug you caused and the lessons learned',
                        'Proactive Leadership: describe a time you introduced a creative technical solution without being asked'
                    ];
                } elseif ($isAmazon) {
                    $topicPool = [
                        'Deep Dive: describe a time you investigated a subtle bug or edge case to its root cause',
                        'Insist on the Highest Standards: describe a time you refused to compromise on quality despite delivery pressure',
                        'Have Backbone; Disagree and Commit: describe a technical decision you opposed but fully committed to execute',
                        'Frugality: describe how you accomplished a major project goal with constrained resources or limited tools',
                        'Earn Trust: describe a situation where you had to repair a working relationship after a misunderstanding'
                    ];
                } elseif ($isMsft) {
                    $topicPool = [
                        'Collaborative Leadership: tell me about a time you aligned team members who had conflicting viewpoints',
                        'Inclusive Software: describe how you ensured your application was accessible or considered diverse user needs',
                        'Technical Trade-off: describe a time you chose long-term software maintainability over an easy quick hack',
                        'Customer Empathy: tell me about a time you advocated for the end-user against initial engineering preferences',
                        'Mentorship: describe an experience where you helped an unconfident teammate navigate a complex blocker'
                    ];
                } elseif ($isService) {
                    $topicPool = [
                        'Handling Client Pressure: describe a time project deliverables changed near a deadline and how you responded',
                        'Conflict Resolution: tell me about an interpersonal disagreement in a project team and how you resolved it professionally',
                        'Multi-Tasking: describe an experience where you had to balance multiple demanding responsibilities simultaneously',
                        'Adaptability: describe how you adapted when assigned to a technology stack that was not your preferred choice',
                        'Commitment: describe a time you put in extra effort to ensure a team milestone was successfully delivered'
                    ];
                } else {
                    $topicPool = [
                        'Tell me about a time when you had to make a difficult trade-off between project delivery deadlines and software quality',
                        'Describe a situation where you had a strong disagreement with a team member. How did you resolve it',
                        'Tell me about a project that did not achieve its intended results or failed. What happened and what did you learn',
                        'Describe an experience where you had to deliver results with ambiguous or incomplete requirements',
                        'How do you handle high-pressure deadlines or sudden unexpected production bugs'
                    ];
                }
            }

            $categoryGuide = <<<GUIDE
Category: HR & Behavioral Interview$compNote
{$companyHeader}Difficulty Level: $difficulty
- Target Audience: Job-seeking candidates, students, and professionals practicing for interviews.
- $levelInstructions
- If a target company is specified ($companyName), tailor questions to reflect that company's cultural values (e.g. Amazon Leadership Principles, Google Googlyness, Microsoft Growth Mindset) within the chosen difficulty level.
- Avoid tricky, stressful, or convoluted psychological traps, and avoid high-stakes executive firing/budget dilemmas.
- Keep each question direct, friendly, and clear (1 to 2 sentences).
GUIDE;
        } elseif ($isAptitude) {
            $compNote = (!empty($companyName) && strcasecmp($companyName, 'General') !== 0) ? " ($companyName pattern)" : "";
            if ($isBeginner) {
                $levelInstructions = "BEGINNER LEVEL: Clean whole-number problems that can be solved in 1 minute on scratch paper (simple percentages, train passing pole, simple interest doubling, basic number series).";
                if ($isService) {
                    // TCS, Infosys, Wipro, Accenture NQT / Placement pattern
                    $topicPool = [
                        'Train passing an electric pole: distance = speed * time with unit conversion (km/h to m/s)',
                        'Simple interest: sum of money doubling in 5 years, finding time to quadruple',
                        'Basic percentage profit when cost price and selling price of an item are known',
                        'Arithmetic number series: finding next term in series with constant common difference',
                        'Seating position from left and right in a single row of students',
                        'Average replacement: average of five numbers changing when one number is removed',
                        'Time and work: person A completes work in 10 days, B in 15 days, working together',
                        'Direction sense test: walking North, turning right, turning right again, finding displacement',
                        'Odd one out among a set of numbers based on prime or parity properties',
                        'Direct proportion unitary method: cost of 12 items known, finding cost of 20 items'
                    ];
                } elseif ($isGoogle) {
                    $topicPool = [
                        'Basic probability: probability of obtaining a sum greater than 8 on two six-sided dice',
                        'Simple speed-distance-time calculation with clean integer values',
                        'Percentage increase and decrease in consecutive periods',
                        'Geometric number series with constant multiplier',
                        'Venn diagram: students who like Math vs Coding vs both in a class of 50',
                        'Basic logic deduction: truth-teller and liar single-step deduction',
                        'Ratio and proportion: dividing an amount among three partners in ratio 2:3:5'
                    ];
                } elseif ($isAmazon) {
                    $topicPool = [
                        'Profit and loss: single item bought at wholesale discount and sold at markup',
                        'Work and time: two warehouse workers packing boxes at different rates',
                        'Unitary cost and batch pricing calculations',
                        'Simple distance, speed, and delivery travel time',
                        'Percentage discount on marked price of products',
                        'Average item weight calculation across different shipments',
                        'Simple linear series of inventory growth over consecutive months'
                    ];
                } elseif ($isMsft) {
                    $topicPool = [
                        'Arithmetic and geometric series pattern identification',
                        'Blood relations: single-step family tree deduction (son of my only brother)',
                        'Direction sense: navigating a 2D grid of city blocks',
                        'Coding-decoding: letter substitution cypher with shift key',
                        'Ratio of ages of father and son given current difference',
                        'Simple work and efficiency calculation between two engineers'
                    ];
                } else {
                    $topicPool = [
                        'Speed, distance, and time with unit conversion',
                        'Simple interest and principal doubling period',
                        'Percentage profit and loss calculation',
                        'Arithmetic sequence next term deduction',
                        'Position ranking in a single row of students',
                        'Average calculation when one number is removed or added',
                        'Time and work done by two people together',
                        'Direction sense and displacement calculation'
                    ];
                }
            } elseif ($isMedium) {
                $levelInstructions = "MEDIUM LEVEL (Only a LITTLE more difficult than Beginner): Standard placement quantitative questions (work and time with two workers, two pipes filling a tank, boat downstream/upstream, profit and loss with discounts, average replacement).";
                if ($isService) {
                    $topicPool = [
                        'Profit and loss with marked price markup of 40% and discount of 20%',
                        'Pipes and cisterns: Pipe A filling tank in 12h, Pipe B emptying in 18h, both open',
                        'Venn diagrams: set of students liking Math, Science, or both',
                        'Boats and streams: downstream speed and upstream speed, finding stream speed',
                        'Geometric sequence: finding next term with multiplication pattern',
                        'Coded blood relations: expression A + B * C relationships',
                        'Average replacement: batting average calculation when new innings score is added',
                        'Probability: drawing two marbles without replacement from colored box',
                        'Clock angle between hour hand and minute hand at a specific time (e.g. 3:15)',
                        'Average speed of round trip journey covering half distance at 40 km/h and half at 60 km/h'
                    ];
                } elseif ($isGoogle) {
                    $topicPool = [
                        'Expected value calculation on a fair 6-sided die game',
                        'Conditional probability: probability that second card is Ace given first is King',
                        'Pipes and cisterns: filling rate with a continuous pipe and periodic drain',
                        'Clock angle calculation between hands at 4:20 or 8:30',
                        'Combinatorics: number of ways to seat 5 people in a line with 2 specific friends together',
                        'Work rate: A works twice as fast as B, finding time to complete project together'
                    ];
                } elseif ($isAmazon) {
                    $topicPool = [
                        'Successive discounts: overall discount of 20% followed by 10% on marked price',
                        'Mixture and alligation: mixing two varieties of coffee or grains at different prices',
                        'Two delivery vehicles traveling towards each other on a highway, meeting time',
                        'Pipes and cisterns: tank filling with leak at bottom',
                        'Compound interest annually compounded for 2 years',
                        'Partnership profit sharing based on investment capital and active months'
                    ];
                } elseif ($isMsft) {
                    $topicPool = [
                        'Syllogism deductions: statements (All A are B, Some B are C), verifying conclusions',
                        'Linear arrangement of 6 people on a bench with adjacent constraints',
                        'Clock angle between hands at 3:15 or 7:20',
                        'Calendar calculation: finding day of week for a given date in the near future',
                        'Work and time: person A works for 4 days then leaves, B finishes remainder',
                        'Number coding with complex offset cyphers'
                    ];
                } else {
                    $topicPool = [
                        'Marked price, cost price, and discount profit percentage',
                        'Pipes and cisterns filling and emptying rates',
                        'Boat downstream and upstream speed analysis',
                        'Average speed of round trip journey',
                        'Probability of drawing items without replacement',
                        'Clock angle between hour and minute hands',
                        'Time and work where one person leaves before completion'
                    ];
                }
            } else {
                $levelInstructions = "DIFFICULT LEVEL (Only a LITTLE more difficult than Medium): Slightly more involved logical problems (relative speed of trains passing each other, conditional probability, clock angle between hands, age ratio linear equations, seating arrangements).";
                if ($isService) {
                    $topicPool = [
                        'Difference between Compound Interest and Simple Interest for 2 years at 10% per annum',
                        'Two trains running in opposite directions crossing each other with lengths and speeds',
                        'Time and work where worker A leaves 3 days before project completion',
                        'Mixture ratio: container with milk and water where water is added to change ratio',
                        'Probability: sum greater than 9 on two simultaneously thrown dice',
                        'Permutations: arranging letters of word LEADER with vowels always together',
                        'Faulty clock: clock gaining 5 minutes every 24 hours, finding true time',
                        'Circular seating arrangement: six people around circular table facing center',
                        'Three pipes A, B, and C filling and emptying tank simultaneously',
                        'False weight: merchant using 900g instead of 1kg, actual profit percentage'
                    ];
                } elseif ($isGoogle) {
                    $topicPool = [
                        'Conditional probability and Bayes rule in practical diagnostic test scenario',
                        'Circular arrangement with 6 people where some face inward and some outward',
                        'Game theory / logic puzzle: Knights and Knaves truth-telling deduction',
                        'Relative speed of two cyclists traveling around circular track meeting points',
                        'Probability of coin tosses resulting in specific streak pattern',
                        'Rate of work with 3 workers taking alternating turns'
                    ];
                } elseif ($isAmazon) {
                    $topicPool = [
                        'Compound interest vs Simple interest difference formula for 3 years',
                        'Mixtures and alligation: repeated dilution of wine/milk with water',
                        'Partnership investment with capital withdrawal after 6 months',
                        'Two trains crossing in same direction with length and relative speed',
                        'Warehouse worker scheduling with non-overlapping time windows',
                        'Faulty weighing scale calculation with trade profit'
                    ];
                } elseif ($isMsft) {
                    $topicPool = [
                        'Multi-attribute grid logic puzzle: 5 professionals in different colored houses',
                        'Circular seating arrangement of 6 people with opposite and neighbor constraints',
                        'Permutations of word letters with specific consonants separated',
                        'Probability of drawing cards with specific suits in sequence',
                        'Work and wage distribution proportional to work completed by 3 people',
                        'Calendar problems across leap year century boundaries'
                    ];
                } else {
                    $topicPool = [
                        'Compound interest vs simple interest difference for 2 years',
                        'Two trains crossing in opposite directions with relative speed',
                        'Time and work where one person leaves near completion',
                        'Mixture and alligation replacement ratios',
                        'Permutations with vowels or specific items kept together',
                        'Circular seating arrangement logic puzzle',
                        'Faulty clock gaining or losing time'
                    ];
                }
            }

            $categoryGuide = <<<GUIDE
Category: Aptitude & Logical Reasoning$compNote
{$companyHeader}Difficulty Level: $difficulty
- Target Audience: Job-seeking candidates and college students.
- $levelInstructions
- If a target company is specified (like TCS, Infosys, Deloitte, Accenture), calibrate to their real placement assessment pattern.
- Use friendly, clean numbers that candidates can comfortably calculate and explain without a scientific calculator.
- Keep each problem clearly stated in 1 to 2 sentences.
GUIDE;
        } else {
            // Technical Core (Technical Interview)
            $compNote = (!empty($companyName) && strcasecmp($companyName, 'General') !== 0) ? " ($companyName)" : "";
            if ($isBeginner) {
                $levelInstructions = "BEGINNER LEVEL: Formulate clear, foundational questions on fundamental concepts and key differences (e.g. OOPs 4 core principles, REST API methods GET/POST, SQL vs NoSQL, process vs thread, primary vs foreign key, Git pull vs fetch, Stack vs Heap memory).";
                if ($isGoogle) {
                    $topicPool = [
                        'Operating Systems: difference between a process and a thread, and how context switching works',
                        'Memory Management: difference between Stack memory and Heap memory allocations',
                        'Linux Command Line: common commands like grep, ps, and chmod and their practical purposes',
                        'Networking Basics: what happens when you type a URL into a browser and press Enter (DNS resolution)',
                        'Data Structures: difference between an Array and a Linked List with random access time complexity',
                        'HTTP Basics: difference between HTTP GET and POST methods and safe vs idempotent requests',
                        'Git Version Control: difference between git pull and git fetch in everyday development'
                    ];
                } elseif ($isAmazon) {
                    $topicPool = [
                        'Cloud Fundamentals: difference between IaaS, PaaS, and SaaS with real-world examples',
                        'Databases: fundamental differences between Relational (SQL) and Non-Relational (NoSQL) databases',
                        'Storage: difference between object storage (like AWS S3) and local block file storage',
                        'Web APIs: difference between GET, POST, PUT, and DELETE HTTP methods in RESTful services',
                        'Object-Oriented Programming: the four core principles of OOP with real-world software examples',
                        'Database Keys: difference between a Primary Key and a Foreign Key in relational tables',
                        'HTTP Status Codes: meaning of common status codes (200 OK, 400 Bad Request, 404 Not Found, 500 Server Error)'
                    ];
                } elseif ($isMsft) {
                    $topicPool = [
                        'Object-Oriented Programming: explain Encapsulation, Inheritance, Polymorphism, and Abstraction',
                        'OOP Design: difference between an Abstract Class and an Interface and when to choose each',
                        'Language Fundamentals: difference between Value types and Reference types in programming',
                        'Database Keys: difference between a Primary Key and a Foreign Key in relational schema design',
                        'Web Architecture: the Model-View-Controller (MVC) architectural pattern components and roles',
                        'Memory Management: Stack memory versus Heap memory and how local variables are stored',
                        'Data Structures: difference between an Array and a Linked List for search and insertion'
                    ];
                } elseif ($isService) {
                    $topicPool = [
                        'Programming Languages: fundamental differences between C, C++, and Java',
                        'Java Architecture: differences between JVM, JRE, and JDK in the Java ecosystem',
                        'SQL Language: difference between DDL (Data Definition) and DML (Data Manipulation) SQL commands',
                        'OOP Concepts: difference between Method Overloading and Method Overriding with examples',
                        'Database Basics: what is Database Normalization and why are 1NF and 2NF important',
                        'Pointers and References: concept of a pointer or reference and why memory addresses matter',
                        'Object-Oriented Programming: the four fundamental principles of OOP with brief examples'
                    ];
                } else {
                    $topicPool = [
                        'The four main principles of Object-Oriented Programming (OOP) with real-world examples',
                        'Difference between an Array and a Linked List with random access time complexities',
                        'Difference between SQL and NoSQL databases and when to choose each',
                        'Difference between a Process and a Thread in modern operating systems',
                        'Difference between GET, POST, PUT, and DELETE HTTP methods in REST APIs',
                        'Difference between a Primary Key and a Foreign Key in relational databases',
                        'Difference between client-side cookies and server-side sessions for user tracking',
                        'Difference between Stack memory and Heap memory in programming languages'
                    ];
                }
            } elseif ($isMedium) {
                $levelInstructions = "MEDIUM LEVEL (Only a LITTLE more difficult than Beginner): Practical everyday software engineering questions (e.g. database indexing and B-Trees, synchronous vs asynchronous execution, SQL JOIN types, HTTP status codes 401 vs 403, MVC architecture, CORS browser security, authentication vs authorization).";
                if ($isGoogle) {
                    $topicPool = [
                        'Operating Systems: concept of Virtual Memory, Paging, and how page faults are resolved',
                        'Networking: fundamental differences between TCP (3-way handshake) and UDP and use cases',
                        'Databases: how database indexing operates under the hood using B-Trees or B+ Trees',
                        'Concurrency: what is a race condition in multithreading and how Mutexes prevent data corruption',
                        'Web Architecture: difference between a Reverse Proxy and a Forward Proxy in infrastructure',
                        'Security: difference between Authentication and Authorization in web applications',
                        'Web Protocols: what is CORS (Cross-Origin Resource Sharing) and why do browsers enforce it'
                    ];
                } elseif ($isAmazon) {
                    $topicPool = [
                        'Databases: what are the ACID properties in database management and why are they critical',
                        'System Scaling: difference between Horizontal scaling and Vertical scaling for web servers',
                        'Asynchronous Processing: role of message queues (like SQS or RabbitMQ) in decoupling services',
                        'Caching: why in-memory caches like Redis or Memcached are placed in front of databases',
                        'Containerization: what is Docker and how do containers differ from traditional Virtual Machines',
                        'Concurrency: difference between Optimistic Concurrency Control and Pessimistic Locking',
                        'Web APIs: difference between HTTP status code 401 Unauthorized and 403 Forbidden'
                    ];
                } elseif ($isMsft) {
                    $topicPool = [
                        'Software Design: explain the SOLID design principles in Object-Oriented Programming with practical examples',
                        'SQL Queries: differences between Inner Join, Left Outer Join, Right Outer Join, and Full Outer Join',
                        'Runtime Management: how Garbage Collection operates in managed runtimes (JVM or .NET CLR)',
                        'Asynchronous Code: difference between synchronous execution and asynchronous execution using async/await',
                        'Web Security: difference between Authentication and Authorization and role of JWT tokens',
                        'Architecture: difference between Monolithic architecture and Microservices architecture',
                        'Database Design: database normalization and why 1NF, 2NF, and 3NF prevent data redundancy'
                    ];
                } elseif ($isService) {
                    $topicPool = [
                        'SQL Queries: difference between SQL Subqueries and SQL Joins with performance trade-offs',
                        'Java Language: purpose of the static keyword for variables, methods, and blocks',
                        'Exception Handling: difference between Checked and Unchecked exceptions and try-catch-finally behavior',
                        'Operating Systems: what is an OS Deadlock and what four conditions are necessary for deadlock',
                        'Web Development: differences between client-side cookies and server-side sessions',
                        'Software Engineering: differences between the Waterfall model and Agile Scrum methodology',
                        'Databases: difference between a Clustered Index and a Non-Clustered Index in SQL Server / MySQL'
                    ];
                } else {
                    $topicPool = [
                        'How database indexing works, and the difference between Clustered and Non-Clustered index',
                        'The ACID properties in database management systems and why they are critical',
                        'Difference between an Abstract Class and an Interface in OOP and when to use each',
                        'Fundamental differences between TCP and UDP protocols and their respective use cases',
                        'What a Deadlock is in an operating system and the four Coffman conditions required',
                        'What CORS (Cross-Origin Resource Sharing) is and why modern web browsers enforce it',
                        'The Model-View-Controller (MVC) architectural pattern in web development',
                        'Difference between Authentication and Authorization in application security'
                    ];
                }
            } else {
                $levelInstructions = "DIFFICULT LEVEL (Only a LITTLE more difficult than Medium): Solid technical tradeoffs (e.g. ACID properties in transactions, operating system deadlocks and the 4 Coffman conditions, optimistic vs pessimistic locking, cache strategies and LRU concept, database normalization 1NF-3NF, SSL/TLS handshake basics).";
                if ($isGoogle) {
                    $topicPool = [
                        'Distributed Systems: concept of Consistent Hashing and how it distributes keys across servers',
                        'Caching Strategies: difference between Cache-Aside, Write-Through, and Write-Back policies and LRU eviction',
                        'Database Scaling: difference between Database Sharding and Database Replication with read/write trade-offs',
                        'Distributed Fundamentals: the CAP Theorem (Consistency, Availability, Partition Tolerance) in simple terms',
                        'Load Balancing: algorithms used by load balancers (Round Robin, Least Connections, IP Hash)',
                        'Security: how HTTPS secures web traffic using the SSL/TLS cryptographic handshake'
                    ];
                } elseif ($isAmazon) {
                    $topicPool = [
                        'Database Transactions: transaction isolation levels (Read Committed vs Serializable) and phantom reads',
                        'High Availability: why database connection pooling is essential in high-throughput backend services',
                        'API Design: concept of Idempotency and why it is critical for payment and order processing endpoints',
                        'Database Architecture: Read Replicas vs Master database in write-heavy vs read-heavy systems',
                        'Microservices: service discovery and how microservices communicate synchronously vs asynchronously',
                        'Deployment Strategies: difference between Blue-Green deployment and Canary release'
                    ];
                } elseif ($isMsft) {
                    $topicPool = [
                        'Database Transactions: transaction isolation levels (Read Committed vs Serializable) and dirty reads',
                        'Operating Systems: deadlock avoidance and detection techniques (such as resource ordering)',
                        'Software Architecture: Dependency Injection and Inversion of Control in enterprise backends',
                        'Testing Practices: difference between Unit Testing, Integration Testing, and Mocking in CI/CD',
                        'API Gateway: role of an API Gateway in microservices for rate-limiting, routing, and token validation',
                        'Security: how HTTPS secures communication over the web using SSL/TLS cryptographic handshake'
                    ];
                } elseif ($isService) {
                    $topicPool = [
                        'Database Normalization: difference between 3NF and BCNF (Boyce-Codd Normal Form) with example',
                        'Database Indexing: internal structure of B-Trees in database indexing and performance impact',
                        'Operating Systems: what causes stop-the-world pauses in Garbage Collection and how to minimize them',
                        'Database Features: advantages and disadvantages of Database Triggers and Stored Procedures',
                        'Version Control: practical difference between Git Merge and Git Rebase in software teams',
                        'Web Security: how SSL/TLS encryption works during client-server handshake over HTTPS'
                    ];
                } else {
                    $topicPool = [
                        'Database transaction isolation levels (Read Committed vs Serializable) and dirty or phantom reads',
                        'Difference between optimistic concurrency control and pessimistic locking in databases',
                        'Common caching strategies (Cache-Aside vs Write-Through) and LRU cache eviction',
                        'Difference between horizontal and vertical scaling and how Load Balancers distribute traffic',
                        'Why database connection pooling is essential in high-throughput backend applications',
                        'The SOLID design principles in Object-Oriented Programming with a practical software example',
                        'How HTTPS secures communication over the web using the SSL/TLS cryptographic handshake',
                        'Role of message queues (RabbitMQ, Kafka, or SQS) in decoupled backend architectures'
                    ];
                }
            }

            $categoryGuide = <<<GUIDE
Category: Technical Interview ($categoryName)$compNote
{$companyHeader}Difficulty Level: $difficulty
- Target Audience: Job-seeking candidates, students, and professionals practicing for technical interviews.
- $levelInstructions
- If target company is specified ($companyName), formulate questions reflecting core concepts asked in interviews for $companyName at $difficulty level.
- STRICTLY FORBIDDEN: Avoid overly difficult distributed system architecture, planetary load, 50,000 QPS scalability, deep theoretical proofs, or obscure multi-phase production outage traps.
- Keep each question concise, direct, and straightforward (1 to 2 sentences).
GUIDE;
        }

        // Shuffle topic pool dynamically in PHP for guaranteed session variety
        shuffle($topicPool);
        $selectedTopics = array_slice($topicPool, 0, max(8, $totalQuestions + 2));
        $topicBankList = "";
        foreach ($selectedTopics as $idx => $t) {
            $num = $idx + 1;
            $topicBankList .= "{$num}. {$t}\n";
        }

        return <<<PROMPT
Role: Supportive and Clear Interviewer in "$categoryName".
{$companyHeader}Difficulty Level: $difficulty.
Session Seed: #$randomSeed
Task: Formulate exactly $totalQuestions distinct, clear interview questions with REDUCED COMPLEXITY so that maximum candidates can comfortably understand and answer them.

$categoryGuide
$companyDirectives

CRITICAL RANDOMIZED TOPIC POOL (Drawn randomly for this session):
$topicBankList

MANDATORY RULES:
1. High Answerability: Keep question complexity accessible and foundational so maximum users can answer with confidence.
2. Direct Phrasing: State each question clearly in 1 to 2 sentences without confusing multi-paragraph situational traps.
3. Practical & Common: Test standard, high-yield interview concepts that everyday candidates prepare for.
4. Company Alignment: If a target company is specified, align questions to the signature topics and rounds of that company.
5. Variety & Diversity (Seed #$randomSeed): Select a fresh, randomized mix of questions from the candidate topic pool above so that different sessions do not receive identical question sets.

Output Format:
Return ONLY a valid JSON array of strings containing exactly $totalQuestions questions.
Do NOT include markdown formatting fences (such as ```json), preambles, or commentary.

Example output:
[
  "First clear interview question...",
  "Second clear interview question..."
PROMPT;
    }

    /**
     * Generate an intelligent evaluation prompt for candidate answers.
     *
     * @param array  $qaPairs Array of ['question_text' => ..., 'answer_text' => ...]
     * @param string $categoryName Category/topic of the interview
     * @param string $difficulty Difficulty level of the interview
     * @param string $companyName Target company
     * @return string
     */
    public static function buildEvaluationPrompt(array $qaPairs, string $categoryName = 'General', string $difficulty = 'Intermediate', string $companyName = 'General'): string
    {
        $total = count($qaPairs);
        $promptParts = '';

        foreach ($qaPairs as $i => $row) {
            $num = $i + 1;
            $q   = addslashes(trim((string)($row['question_text'] ?? '')));
            $a   = addslashes(trim((string)($row['answer_text'] ?? '')));
            if ($a === '') {
                $a = '[No answer provided / candidate left blank]';
            }
            $promptParts .= "\n--- Question {$num} ---\nQuestion: \"{$q}\"\nCandidate Answer: \"{$a}\"\n";
        }

        $companyEvaluationDirective = "";
        if (!empty($companyName) && strcasecmp($companyName, 'General') !== 0) {
            $companyEvaluationDirective = <<<COMP
--- TARGET COMPANY HIRING BAR ($companyName) ---
- The candidate is interviewing for $companyName at $difficulty level.
- In your "strengths", "improvements", and "ai_summary", note how well their answer matches $companyName's specific technical/cultural expectations (e.g. Amazon Leadership Principles, Google Googlyness/problem-solving, TCS/Infosys core CS rigor).
- CALIBRATE TO $difficulty LEVEL: For Beginner candidates, be encouraging and reward clear foundational understanding. Do NOT penalize for lack of senior system design or enterprise-scale knowledge.
COMP;
        }

        $catLower = strtolower($categoryName);
        $isCoding = str_contains($catLower, 'coding')
                 || str_contains($catLower, 'dsa')
                 || str_contains($catLower, 'algorithm')
                 || str_contains($catLower, 'leetcode')
                 || str_contains($catLower, 'problem solving');

        if ($isCoding) {
            $rubricInstructions = <<<CRUBRIC
--- Scoring Rubric for Coding & DSA (0 to 10 scale) ---
CRITICAL CODING ACCURACY RULE:
- A candidate receives 10/10 ONLY when their code is verified to be completely correct and produces the exact right output for the problem!
- 10 out of 10: The code is 100% logically correct, compiles/executes without errors, and produces the exact expected output matching all test examples and constraints.
- 7 to 8: Code passes the primary examples and produces the correct output, but has minor complexity inefficiencies (e.g. O(N log N) sorting vs O(N) linear scan) or misses an edge case.
- 5 to 6: Working brute-force approach, but fails certain test cases or gives incorrect output on some inputs.
- 3 to 4: Significant logic bugs, syntax errors, runtime exceptions, or produces incorrect output.
- 0 to 2: Blank answer, unattempted, or completely unrelated.
CRUBRIC;
        } else {
            // Text typing interview (Technical, HR, Aptitude): Lenient & Relevant Grading Rule
            $rubricInstructions = <<<TRUBRIC
--- Lenient & Relevant Evaluation Rubric (Text Typing Interview: Technical, HR, Aptitude) ---
CRITICAL MANDATORY INSTRUCTIONS:
1. RELEVANT ANSWER = FULL MARKS (9 to 10 out of 10):
   - If the candidate's typed answer is RELEVANT to the question (touches on the core concept, understands the main premise, explains the intuition correctly, mentions key terms, or provides a valid real-world example), YOU MUST AWARD FULL MARKS (9 or 10 out of 10)!
   - Mark the verdict positively and celebrate their correct understanding.

2. DO NOT EVALUATE STRICTLY:
   - Do NOT deduct marks for brief, short, or informal answers.
   - Do NOT require textbook definitions, comprehensive academic checklists, or obscure edge cases.
   - As long as the candidate's typed answer is on-topic and demonstrates relevant understanding, give full marks (9 or 10 / 10).

3. PARTIAL / DEVELOPING (6 to 8 out of 10):
   - Award 6 to 8 only if the answer is partially related but missing the core point or slightly vague.

4. UNATTEMPTED / WRONG (0 to 2 out of 10):
   - Only award 0 to 2 if the answer was left blank, skipped ("idk", "I don't know", "skip", "no answer"), or is completely incorrect/unrelated gibberish.

5. COMMUNICATION & CONFIDENCE:
   - For any relevant answer, award a high grammar_score (9 to 10) and confidence_score (9 to 10).
TRUBRIC;
        }

        $companyHeader = (!empty($companyName) && strcasecmp($companyName, 'General') !== 0) ? " targeting \"{$companyName}\"" : "";

        return <<<PROMPT
You are evaluating candidate answers for "$categoryName"$companyHeader (Difficulty: $difficulty).
Analyze ALL {$total} candidate answers below.

$promptParts

$companyEvaluationDirective

$rubricInstructions

--- Required Output Format ---
Return ONLY a valid JSON array with exactly {$total} objects (one per question, strictly matching the question order).
Each object must have these exact keys:
{
  "score": <integer from 0 to 10 according to the rubric above>,
  "strengths": "<1-2 sentences highlighting specifically what the candidate got right, noting good terminology or reasoning>",
  "improvements": "<1-2 sentences on practical advice or helpful tips to expand their knowledge>",
  "grammar_feedback": "<1 sentence on communication style, structure, and clarity>",
  "grammar_score": <integer from 0 to 10 measuring communication clarity>,
  "confidence_score": <integer from 0 to 10 measuring clear, direct expression>,
  "ai_summary": "<One crisp, punchy sentence delivering the verdict for this question>",
  "improved_answer": "<The model answer (2-3 crisp sentences) demonstrating how to answer this question concisely and clearly>"
}

Important Instructions:
- For text typing interview questions (Technical, HR, Aptitude), if the candidate's answer is relevant and addresses the core concept, GIVE FULL MARKS (9 to 10). Do NOT evaluate strictly.
- If the candidate wrote nothing or gave a non-answer (e.g. blank, 'idk', 'dont know'), assign a score of 0 or 1, explain what was expected in "improvements", and write an exemplary "improved_answer".
- Do NOT wrap in markdown fences (like ```json). Return pure, valid JSON only.
PROMPT;
    }

    /**
     * Generate an AI career roadmap and personalized tips based on past interview performance.
     *
     * @param string $category
     * @param float  $avgScore
     * @param array  $weakAreas
     * @return string
     */
    public static function buildCareerTipPrompt(string $category, float $avgScore, array $weakAreas = []): string
    {
        $weakList = !empty($weakAreas) ? implode(', ', $weakAreas) : 'General technical depth';

        return <<<PROMPT
Candidate Profile:
- Track: $category
- Current Interview Average Score: $avgScore / 10
- Identified Growth Areas: $weakList

Provide a focused, high-impact career coaching plan for this candidate.
Return a valid JSON object with:
{
  "readiness_level": "<Junior / Mid-Level / Senior Ready>",
  "key_strengths": ["<strength 1>", "<strength 2>"],
  "critical_gap_areas": ["<gap 1>", "<gap 2>"],
  "recommended_action_plan": [
    "<Step 1: specific topic or project to master>",
    "<Step 2: architectural or hands-on practice>",
    "<Step 3: interview communication refinement>"
  ],
  "motivational_verdict": "<1-2 inspiring yet realistic sentences from a senior tech coach>"
}
Return ONLY valid JSON.
PROMPT;
    }

    /**
     * MODULE 13 — Code Sandbox AI Analysis Prompt
     *
     * Analyzes candidate code for time/space complexity, edge cases,
     * code cleanliness, and optimization suggestions.
     *
     * @param string $code
     * @param string $language
     * @param string $questionText
     * @return string
     */
    public static function buildCodeAnalysisPrompt(string $code, string $language, string $questionText): string
    {
        return <<<PROMPT
Role: Elite Staff Software Engineer & Technical Interview Reviewer.
Task: Perform an in-depth code review and complexity analysis on the candidate's solution.

Interview Question / Problem:
$questionText

Programming Language: $language
Candidate's Solution Code:
```$language
$code
```

Provide a strict, professional technical code analysis.
Return ONLY a valid JSON object matching this exact structure:
{
  "time_complexity": "O(...)",
  "time_explanation": "Brief explanation of time complexity",
  "space_complexity": "O(...)",
  "space_explanation": "Brief explanation of auxiliary space",
  "code_quality_score": 8, // Integer 1-10
  "clean_code_verdict": "Clear, concise assessment of naming, idiomatic patterns, readability",
  "edge_cases": [
    {
      "case": "Empty or single-element input",
      "status": "Handled" // or "Missed" / "Partial"
    },
    {
      "case": "Negative or boundary values",
      "status": "Handled"
    }
  ],
  "optimizations": [
    "Specific suggestion 1 for better performance or cleaner logic",
    "Specific suggestion 2"
  ],
  "optimal_snippet": "Optional cleaner or more optimal snippet in $language (only if substantial improvement exists)"
}
Return pure JSON only. Do not wrap in markdown or conversational text.
PROMPT;
    }

    /**
     * MODULE 13 — Virtual Compiler & Real-time Execution Simulation Prompt
     *
     * Used for compiled/server-agnostic languages (Java, C++, SQL) or when
     * evaluating automated test cases.
     */
    public static function buildVirtualCompilerPrompt(string $code, string $language, string $stdin = '', array $testCases = []): string
    {
        $testCasesJson = !empty($testCases) ? json_encode($testCases, JSON_PRETTY_PRINT) : 'None';
        return <<<PROMPT
Role: Deterministic Compiler & Runtime Execution Engine ($language).
Task: Accurately compile and execute the provided $language source code, trace its exact stdout/stderr output, and verify any test cases.

Language: $language
Standard Input (stdin): $stdin
Candidate Source Code:
```$language
$code
```

Test Cases to evaluate:
$testCasesJson

Instructions:
1. First, check for compilation or syntax errors (e.g., missing semicolons, unknown types, undeclared variables).
   - If compilation fails: set "compiled": false, "exit_code": 1, and in "compiler_errors" output standard compiler error messages with line numbers.
2. If compilation succeeds:
   - Trace program execution precisely and deterministically.
   - Capture exact standard output in "stdout".
   - If runtime exception occurs (e.g., NullPointerException, IndexOutOfBounds, Segmentation Fault, Division by zero): set "exit_code": 1, "stderr": exception message.
   - For each test case, execute the code/function with the input, capture the actual result, and check whether actual matches expected.
3. Return ONLY valid JSON matching this exact structure:
{
  "compiled": true,
  "compiler_errors": "",
  "stdout": "exact printed output",
  "stderr": "",
  "exit_code": 0,
  "test_case_results": [
    {
      "caseNum": 0,
      "passed": true,
      "input": "...",
      "expected": "...",
      "actual": "..."
    }
  ]
}
Return pure JSON only without markdown formatting.
PROMPT;
    }
}
