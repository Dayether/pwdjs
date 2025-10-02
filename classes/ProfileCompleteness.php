<?php
require_once __DIR__ . '/Database.php';

class ProfileCompleteness {

    public static function compute(string $userId): int {
        $pdo = Database::getConnection();
        $user = self::fetchUser($pdo, $userId);
        if (!$user) return 0;

        $score = 0;
        $has = fn($f) => !empty($user[$f]);

        // Basic
        if ($has('name')) $score += 5;
        if ($has('date_of_birth')) $score += 5;
        if ($has('gender')) $score += 3;
        if ($has('phone')) $score += 5;
        if ($has('region') && $has('province') && $has('city')) $score += 10;

        // Disability
        if ($has('disability_type')) $score += 10;
        if ($has('disability_severity')) $score += 5;
        if ($has('assistive_devices')) $score += 2;
        if ($has('pwd_id_last4')) $score += 5;

        // Education
        if ($has('education') || $has('education_level')) $score += 10;

        // Documents
        if ($has('resume')) $score += 10;
        if ($has('video_intro')) $score += 10;

        // Skills (user_skills >=3)
        if (self::countUserSkills($pdo, $userId) >= 3) $score += 10;

        // Experience
        if (self::countExperiences($pdo, $userId) >= 1) $score += 5;

        // Certifications
        if (self::countCerts($pdo, $userId) >= 1) $score += 5;

        // Summary
        if ($has('primary_skill_summary')) $score += 10;

    // Preferences / Mini resume
    if (!empty($user['preferred_work_setup'])) $score += 5;
    if (!empty($user['preferred_location'])) $score += 3;
    if (!empty($user['interests'])) $score += 5;
    if (!empty($user['expected_salary_min']) || !empty($user['expected_salary_max'])) $score += 5;

    // Cap to 100
    if ($score > 100) $score = 100;

        $upd = $pdo->prepare("
            UPDATE users
            SET profile_completeness=?, profile_last_calculated=NOW()
            WHERE user_id=?
        ");
        $upd->execute([$score, $userId]);

        return $score;
    }

    private static function fetchUser(PDO $pdo, string $userId): ?array {
        $q = $pdo->prepare("SELECT * FROM users WHERE user_id=? LIMIT 1");
        $q->execute([$userId]);
        $r = $q->fetch(PDO::FETCH_ASSOC);
        return $r ?: null;
    }

    private static function countUserSkills(PDO $pdo, string $userId): int {
        try {
            $q = $pdo->prepare("SELECT COUNT(*) FROM user_skills WHERE user_id=?");
            $q->execute([$userId]);
            return (int)$q->fetchColumn();
        } catch(Throwable $e) { return 0; }
    }

    private static function countExperiences(PDO $pdo, string $userId): int {
        try {
            $q = $pdo->prepare("SELECT COUNT(*) FROM user_experiences WHERE user_id=?");
            $q->execute([$userId]);
            return (int)$q->fetchColumn();
        } catch(Throwable $e) { return 0; }
    }

    private static function countCerts(PDO $pdo, string $userId): int {
        try {
            $q = $pdo->prepare("SELECT COUNT(*) FROM user_certifications WHERE user_id=?");
            $q->execute([$userId]);
            return (int)$q->fetchColumn();
        } catch(Throwable $e) { return 0; }
    }
}