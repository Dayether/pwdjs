<?php
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Taxonomy.php';
require_once __DIR__ . '/Skill.php';
require_once __DIR__ . '/Application.php';
require_once __DIR__ . '/User.php';
require_once __DIR__ . '/Job.php';

class Matching {
    // Policy toggles (can be moved to config.php if desired)
    // Defaults; can be overridden by defines in config.php
    public const HARD_LOCK = true;           // if true, block apply when below thresholds
    public const SKILL_MIN_PCT = 0.6;        // require at least 60% of job skills
    public const ENFORCE_EDU = true;         // require candidate education >= required

    public static function hardLock(): bool {
        return defined('MATCH_HARD_LOCK') ? (bool)constant('MATCH_HARD_LOCK') : self::HARD_LOCK;
    }
    public static function skillMinPct(): float {
        $v = defined('MATCH_SKILL_MIN_PCT') ? (float)constant('MATCH_SKILL_MIN_PCT') : self::SKILL_MIN_PCT;
        if ($v < 0) $v = 0; if ($v > 1) $v = 1; return $v;
    }
    public static function enforceEdu(): bool {
        return defined('MATCH_ENFORCE_EDU') ? (bool)constant('MATCH_ENFORCE_EDU') : self::ENFORCE_EDU;
    }

    public static function userSkillIds(string $userId): array {
        $rows = Skill::getSkillsForUser($userId); // skill_id, name
        return array_values(array_filter(array_map(fn($r)=>$r['skill_id'] ?? null, $rows)));
    }

    public static function jobSkillIds(string $jobId): array {
        return Skill::getJobSkillIds($jobId);
    }

    public static function eduRanks(?string $candidate, ?string $required): array {
        $map = Taxonomy::educationRankMap();
        $candCanon = Taxonomy::canonicalizeEducation($candidate ?? '');
        if ($candCanon === null) $candCanon = '';
        $reqCanon  = Taxonomy::canonicalizeEducation($required ?? '');
        if ($reqCanon === null) $reqCanon = '';
        $cr = $map[mb_strtolower($candCanon)] ?? 0;
        $rr = $map[mb_strtolower($reqCanon)] ?? 0;
        return [$cr, $rr];
    }

    public static function pwdApplicable(?string $userDisabilityType, ?string $jobApplicableCsv): bool {
        $csv = trim((string)$jobApplicableCsv);
        if ($csv === '') return true; // open to all
        $list = array_filter(array_map('trim', explode(',', $csv)));
        if (!$list) return true;
        $user = trim((string)$userDisabilityType);
        if ($user === '') return false; // job targets specific categories but user has none
        // case-insensitive compare
        foreach ($list as $opt) {
            if (mb_strtolower($opt) === mb_strtolower($user)) return true;
        }
        return false;
    }

    public static function skillsMatchPct(array $userSkillIds, array $jobSkillIds): float {
        $need = array_values(array_unique(array_map('strval', $jobSkillIds)));
        $have = array_flip(array_values(array_unique(array_map('strval', $userSkillIds))));
        if (count($need) === 0) return 1.0; // no required skills => fully open
        $matched = 0;
        foreach ($need as $sid) if (isset($have[$sid])) $matched++;
        return $matched / max(1, count($need));
    }

    // Compute score using existing Application scoring with user's profile
    public static function score(User $user, Job $job): float {
        $userSkillIds = self::userSkillIds($user->user_id);
        $userEdu = $user->education_level ?: $user->education ?: '';
        $years = (int)($user->experience ?? 0);
        return Application::calculateMatchScoreFromInput($job, $years, $userSkillIds, Taxonomy::canonicalizeEducation($userEdu) ?? '');
    }

    public static function canApply(User $user, Job $job): array {
        $reasons = [];
        // 1) PWD applicability
        if (!self::pwdApplicable($user->disability_type ?? '', $job->applicable_pwd_types ?? '')) {
            $reasons[] = 'This job targets specific PWD categories that don\'t match your profile.';
        }
        // 2) Education
        if (self::enforceEdu()) {
            [$cr,$rr] = self::eduRanks($user->education_level ?: $user->education ?: '', $job->required_education ?? '');
            if (($job->required_education ?? '') !== '' && $cr < $rr) {
                $reasons[] = 'Your education level is below the required level for this job.';
            }
        }
        // 3) Skills
        $pct = self::skillsMatchPct(self::userSkillIds($user->user_id), self::jobSkillIds($job->job_id));
        if ($pct < self::skillMinPct()) {
            $reasons[] = 'You don\'t have enough of the required skills in your profile.';
        }

        $ok = empty($reasons) || !self::hardLock() ? true : false;
        return [
            'ok' => $ok,
            'reasons' => $reasons,
            'skill_pct' => $pct,
            'score' => self::score($user, $job)
        ];
    }
}
