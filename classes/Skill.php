<?php
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Helpers.php';

/**
 * Skill management:
 *  - Each unique skill name stored once in skills table
 *  - job_skills (job_skill_id, job_id, skill_id)
 *  - application_skills (application_skill_id, application_id, skill_id)
 *  - user_skills (optional usage)
 */
class Skill {

    /* Ensure a set of names exist in the skills table.
       Returns map: lowercase original => skill_id */
    public static function ensureSkills(array $names): array {
        $pdo = Database::getConnection();
        $clean = [];
        foreach ($names as $n) {
            $n = trim($n);
            if ($n === '') continue;
            $k = mb_strtolower($n);
            if (!isset($clean[$k])) $clean[$k] = $n;
        }
        if (!$clean) return [];

        // fetch existing
        $in = implode(',', array_fill(0, count($clean), '?'));
        $stmt = $pdo->prepare("SELECT skill_id, name FROM skills WHERE LOWER(name) IN ($in)");
        $stmt->execute(array_keys($clean));
        $existing = [];
        while ($row = $stmt->fetch()) {
            $existing[mb_strtolower($row['name'])] = $row['skill_id'];
        }

        // insert missing
        $insertStmt = $pdo->prepare("INSERT INTO skills (skill_id, name) VALUES (?, ?)");
        foreach ($clean as $lk => $orig) {
            if (!isset($existing[$lk])) {
                $sid = Helpers::generateSmartId('SKL');
                $insertStmt->execute([$sid, $orig]);
                $existing[$lk] = $sid;
            }
        }
        return $existing; // lowercase -> id
    }

    // Assign a complete set of skills to a job (replace existing)
    public static function assignSkillsToJob(string $job_id, array $skillNames): void {
        $pdo = Database::getConnection();
        $pdo->prepare("DELETE FROM job_skills WHERE job_id = ?")->execute([$job_id]);
        if (!$skillNames) return;
        $map = self::ensureSkills($skillNames);
        if (!$map) return;
        $stmt = $pdo->prepare("INSERT INTO job_skills (job_skill_id, job_id, skill_id) VALUES (?, ?, ?)");
        foreach ($map as $skill_id) {
            $stmt->execute([Helpers::generateSmartId('JSK'), $job_id, $skill_id]);
        }
    }

    // Application: filter selected skill IDs to those valid for the job
    public static function assignSkillIdsToApplication(string $application_id, string $job_id, array $skillIds): void {
        $pdo = Database::getConnection();
        $allowed = self::getJobSkillIds($job_id);
        $allowedSet = array_flip($allowed);

        $valid = [];
        foreach ($skillIds as $sid) {
            $sid = trim((string)$sid);
            if ($sid !== '' && isset($allowedSet[$sid])) $valid[] = $sid;
        }
        $valid = array_values(array_unique($valid));

        $pdo->prepare("DELETE FROM application_skills WHERE application_id = ?")->execute([$application_id]);
        if (!$valid) return;

        $stmt = $pdo->prepare("INSERT INTO application_skills (application_skill_id, application_id, skill_id) VALUES (?, ?, ?)");
        foreach ($valid as $skill_id) {
            $stmt->execute([Helpers::generateSmartId('ASK'), $application_id, $skill_id]);
        }
    }

    public static function getSkillsForUser(string $user_id): array {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("
            SELECT s.skill_id, s.name 
            FROM user_skills us 
            JOIN skills s ON us.skill_id = s.skill_id 
            WHERE us.user_id = ?
            ORDER BY s.name
        ");
        $stmt->execute([$user_id]);
        return $stmt->fetchAll();
    }

    public static function getSkillsForJob(string $job_id): array {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("
            SELECT s.skill_id, s.name 
            FROM job_skills js 
            JOIN skills s ON js.skill_id = s.skill_id 
            WHERE js.job_id = ?
            ORDER BY s.name
        ");
        $stmt->execute([$job_id]);
        return $stmt->fetchAll();
    }

    public static function getJobSkillIds(string $job_id): array {
        $rows = self::getSkillsForJob($job_id);
        return array_column($rows, 'skill_id');
    }

    public static function getSkillsForApplication(string $application_id): array {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("
            SELECT s.skill_id, s.name 
            FROM application_skills ajs 
            JOIN skills s ON ajs.skill_id = s.skill_id 
            WHERE ajs.application_id = ?
            ORDER BY s.name
        ");
        $stmt->execute([$application_id]);
        return $stmt->fetchAll();
    }

    public static function allSkills(): array {
        $pdo = Database::getConnection();
        $stmt = $pdo->query("SELECT * FROM skills ORDER BY name");
        return $stmt->fetchAll();
    }

    public static function getSkillNamesForUser(string $user_id): string {
        $skills = self::getSkillsForUser($user_id);
        return implode(', ', array_column($skills, 'name'));
    }
}