<?php
require_once __DIR__ . '/Taxonomy.php';

class Skill {
    public string $skill_id;
    public string $name;

    public function __construct(array $row = []) {
        $this->skill_id = $row['skill_id'] ?? '';
        $this->name = $row['name'] ?? '';
    }

    // Create/ensure skills exist for canonical, allowed names only. Returns map: name(lower) => skill_id
    public static function ensureSkills(array $names): array {
        $pdo = Database::getConnection();
        $map = [];

        // Canonicalize + filter to allowed list
        $canonical = Taxonomy::canonicalizeSkills($names);
        if (!$canonical) return $map;

        // Fetch existing by LOWER(name)
        $placeholders = implode(',', array_fill(0, count($canonical), '?'));
        $lowerNames = array_map(fn($v)=>strtolower($v), $canonical);
        $stmt = $pdo->prepare("SELECT * FROM skills WHERE LOWER(name) IN ($placeholders)");
        $stmt->execute($lowerNames);
        $existing = $stmt->fetchAll();
        foreach ($existing as $ex) {
            $map[strtolower($ex['name'])] = $ex['skill_id'];
        }

        // Insert missing (only allowed canonical names reach here)
        foreach ($canonical as $n) {
            $ln = strtolower($n);
            if (!isset($map[$ln])) {
                $skill_id = Helpers::generateSmartId('SKL');
                $ins = $pdo->prepare("INSERT INTO skills (skill_id, name) VALUES (?, ?)");
                $ins->execute([$skill_id, $n]);
                $map[$ln] = $skill_id;
            }
        }
        return $map;
    }

    public static function assignSkillsToUser(string $user_id, array $skillNames): void {
        $pdo = Database::getConnection();
        $pdo->prepare("DELETE FROM user_skills WHERE user_id = ?")->execute([$user_id]);
        if (!$skillNames) return;
        $map = self::ensureSkills($skillNames);
        if (!$map) return;
        $stmt = $pdo->prepare("INSERT INTO user_skills (user_skill_id, user_id, skill_id) VALUES (?, ?, ?)");
        foreach ($map as $skill_id) {
            $stmt->execute([Helpers::generateSmartId('USK'), $user_id, $skill_id]);
        }
    }

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

    // Application skills: accept chosen skill IDs but ensure they belong to the job's required skills
    public static function assignSkillIdsToApplication(string $application_id, string $job_id, array $skillIds): void {
        $pdo = Database::getConnection();

        // Fetch allowed skill_ids for this job
        $allowed = self::getJobSkillIds($job_id);
        $allowedSet = array_flip($allowed);

        // Filter submitted IDs against allowed
        $valid = [];
        foreach ($skillIds as $sid) {
            $sid = trim((string)$sid);
            if ($sid !== '' && isset($allowedSet[$sid])) {
                $valid[] = $sid;
            }
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
        $stmt = $pdo->prepare("SELECT s.skill_id, s.name FROM user_skills us JOIN skills s ON us.skill_id = s.skill_id WHERE us.user_id = ? ORDER BY s.name");
        $stmt->execute([$user_id]);
        return $stmt->fetchAll();
    }

    public static function getSkillsForJob(string $job_id): array {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT s.skill_id, s.name FROM job_skills js JOIN skills s ON js.skill_id = s.skill_id WHERE js.job_id = ? ORDER BY s.name");
        $stmt->execute([$job_id]);
        return $stmt->fetchAll();
    }

    public static function getJobSkillIds(string $job_id): array {
        $rows = self::getSkillsForJob($job_id);
        return array_column($rows, 'skill_id');
    }

    public static function getSkillsForApplication(string $application_id): array {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT s.skill_id, s.name FROM application_skills ajs JOIN skills s ON ajs.skill_id = s.skill_id WHERE ajs.application_id = ? ORDER BY s.name");
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

    public static function getSkillNamesForJob(string $job_id): string {
        $skills = self::getSkillsForJob($job_id);
        return implode(', ', array_column($skills, 'name'));
    }
}