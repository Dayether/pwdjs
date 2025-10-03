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

    public static function pwdApplicable(?string $userDisabilityType, ?string $jobApplicableCsv, ?string $userGeneralDisability = null): bool {
        $csv = trim((string)$jobApplicableCsv);
        if ($csv === '') return true; // open to all
        $list = array_filter(array_map('trim', explode(',', $csv)));
        if (!$list) return true;
        // Canonicalize job list
        $canonList = [];
        foreach ($list as $opt) {
            $canon = Taxonomy::canonicalizeDisability($opt);
            if ($canon !== null && $canon !== '') $canonList[mb_strtolower($canon)] = true;
        }
        if (!$canonList) return true;
        // Prefer specific type, then general field
        $cand = Taxonomy::canonicalizeDisability($userDisabilityType ?? '') ?? Taxonomy::canonicalizeDisability($userGeneralDisability ?? '');
        if ($cand === null || $cand === '') return false; // specific targeting but user has none/unknown
        return isset($canonList[mb_strtolower($cand)]);
    }

    public static function skillsMatchPct(array $userSkillIds, array $jobSkillIds): float {
        $need = array_values(array_unique(array_map('strval', $jobSkillIds)));
        $have = array_flip(array_values(array_unique(array_map('strval', $userSkillIds))));
        if (count($need) === 0) return 1.0; // no required skills => fully open
        $matched = 0;
        foreach ($need as $sid) if (isset($have[$sid])) $matched++;
        return $matched / max(1, count($need));
    }

    // Fuzzy coverage when names are close but not exact IDs
    protected static function fuzzySkillMatchPct(User $user, Job $job): float {
        $jobRows = Skill::getSkillsForJob($job->job_id);
        $userRows = Skill::getSkillsForUser($user->user_id);
        if (!$jobRows || !$userRows) return 0.0;

        $normalize = function(string $s): string {
            $t = mb_strtolower(trim($s));
            // common aliases
            $rep = [
                'microsoft excel' => 'excel',
                'ms excel' => 'excel',
                'javascript' => 'javascript', // keep
                'js' => 'javascript',
                'photoshop' => 'adobe photoshop',
                'adobe ps' => 'adobe photoshop',
                'wordpress' => 'wordpress',
                'wp' => 'wordpress',
            ];
            if (isset($rep[$t])) $t = $rep[$t];
            // strip punctuation
            $t = preg_replace('/[^a-z0-9 +]/u', ' ', $t);
            $t = preg_replace('/\s+/u', ' ', $t);
            return trim($t);
        };

        $jobNames = array_values(array_unique(array_map(fn($r)=>$normalize($r['name']), $jobRows)));
        $userNames = array_values(array_unique(array_map(fn($r)=>$normalize($r['name']), $userRows)));
        if (!$jobNames || !$userNames) return 0.0;

        $fuzzyMatches = 0; $total = count($jobNames);
        foreach ($jobNames as $jn) {
            $hit = false;
            foreach ($userNames as $un) {
                if ($jn === $un) { $hit = true; break; }
                if (str_contains($un, $jn) || str_contains($jn, $un)) { $hit = true; break; }
                $sim = 0.0; similar_text($jn, $un, $sim);
                if ($sim >= 80.0) { $hit = true; break; }
            }
            if ($hit) $fuzzyMatches++;
        }
        return $total ? ($fuzzyMatches / $total) : 0.0;
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
        if (!self::pwdApplicable($user->disability_type ?? '', $job->applicable_pwd_types ?? '', $user->disability ?? null)) {
            $reasons[] = 'This job targets specific PWD categories that don\'t match your profile.';
        }
        // 2) Education
        if (self::enforceEdu()) {
            [$cr,$rr] = self::eduRanks($user->education_level ?: $user->education ?: '', $job->required_education ?? '');
            if (($job->required_education ?? '') !== '' && $cr < $rr) {
                $reasons[] = 'Your education level is below the required level for this job.';
            }
        }
        // 3) Skills (strict by ID), then fuzzy by names if needed
        $pctStrict = self::skillsMatchPct(self::userSkillIds($user->user_id), self::jobSkillIds($job->job_id));
        $pct = $pctStrict;
        if ($pct < self::skillMinPct()) {
            $pctFuzzy = self::fuzzySkillMatchPct($user, $job);
            $pct = max($pct, $pctFuzzy);
        }
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

    /**
     * Detailed breakdown of the score and missing skills by ID
     * Returns array: {
     *   total: float, exp: float, skills: float, edu: float,
     *   job_required_years: int, user_years: int,
     *   job_required_education: string, user_education: string,
     *   job_skill_ids: string[], user_skill_ids: string[], missing_skill_ids: string[]
     * }
     */
    public static function breakdown(User $user, Job $job): array {
        $userYears = (int)($user->experience ?? 0);
        $jobReqYears = max(0, (int)$job->required_experience);

        // Experience (40)
        $expScore = ($jobReqYears <= 0)
            ? 40.0
            : 40.0 * (min($userYears, $jobReqYears) / max(1, $jobReqYears));

        // Skills (40)
        $userSkillIds = self::userSkillIds($user->user_id);
        $jobSkillIds  = self::jobSkillIds($job->job_id);
        $jobSet = array_flip(array_map('strval', $jobSkillIds));
        $haveSet = array_flip(array_map('strval', $userSkillIds));
        $matched = 0; $missing = [];
        foreach ($jobSkillIds as $sid) {
            $k = (string)$sid;
            if (isset($haveSet[$k])) $matched++; else $missing[] = $k;
        }
        $skillsScore = count($jobSkillIds) ? 40.0 * ($matched / count($jobSkillIds)) : 40.0;

        // Education (20)
        $userEduRaw = $user->education_level ?: ($user->education ?: '');
        $userEduCanon = Taxonomy::canonicalizeEducation($userEduRaw) ?? '';
        $jobEduCanon  = Taxonomy::canonicalizeEducation($job->required_education ?? '') ?? '';
        $map = Taxonomy::educationRankMap();
        $ur = $map[mb_strtolower($userEduCanon)] ?? 0;
        $jr = $map[mb_strtolower($jobEduCanon)] ?? 0;
        $eduScore = ($jobEduCanon === '' || $jr <= 0)
            ? 20.0
            : ($ur >= $jr ? 20.0 : 20.0 * ($ur / max(1, $jr)));

        $total = round(max(0.0, min(100.0, $expScore + $skillsScore + $eduScore)), 2);

        return [
            'total' => $total,
            'exp' => round($expScore,2),
            'skills' => round($skillsScore,2),
            'edu' => round($eduScore,2),
            'job_required_years' => $jobReqYears,
            'user_years' => $userYears,
            'job_required_education' => $jobEduCanon,
            'user_education' => $userEduCanon,
            'job_skill_ids' => array_values(array_map('strval',$jobSkillIds)),
            'user_skill_ids' => array_values(array_map('strval',$userSkillIds)),
            'missing_skill_ids' => $missing,
        ];
    }
}
