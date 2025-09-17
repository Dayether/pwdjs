<?php
class Taxonomy {
    // Canonical lists
    public static function educationLevels(): array {
        return [
            'Any',
            'High School',
            'Vocational/Technical',
            'Associate',
            'Bachelor’s',
            'Master’s',
            'Doctorate',
        ];
    }

    public static function employmentTypes(): array {
        return ['Full time','Part time','Contract','Temporary','Internship'];
    }

    // Kept for compatibility, but the public site enforces WFH
    public static function remoteOptions(): array {
        return ['On-site','Hybrid','Work From Home'];
    }

    public static function accessibilityTags(): array {
        return [
            'PWD-Friendly',
            'Wheelchair Accessible',
            'Screen Reader Friendly',
            'Flexible Hours',
            'Assistive Tech Provided',
            'Internet Allowance',
            'Asynchronous',
            'Work From Home', // legacy tag safety
        ];
    }

    public static function allowedSkills(): array {
        return [
            'Customer Support','JavaScript','PHP','Python','C#','Java',
            'SQL','HTML/CSS','React','Laravel','Django','Git',
        ];
    }

    // Helpers used by Application.php, Job.php, Skill.php

    // Normalize education to canonical level or null (null means Any/Unknown)
    public static function canonicalizeEducation(?string $input): ?string {
        if ($input === null) return null;
        $s = trim(mb_strtolower($input));
        if ($s === '' || $s === 'any' || $s === 'n/a' || $s === 'na') return null;

        $has = fn($needle) => mb_strpos($s, $needle) !== false;

        if ($has('phd') || $has('doctor') || $has('doctoral')) return 'Doctorate';
        if ($has('master') || $has("master's") || $has('msc') || $has('ms ') || $has('ma ')) return 'Master’s';
        if ($has('bachelor') || $has("bachelor's") || $has('bs ') || $has('ba ') || $has('bsc') || $has('b.a') || $has('b.s')) return 'Bachelor’s';
        if ($has('associate') || $has('aas') || $has('a.a') || $has('a.s')) return 'Associate';
        if ($has('vocational') || $has('technical') || $has('voc-tech') || $has('tesda')) return 'Vocational/Technical';
        if ($has('high school') || $has('highschool') || $has('shs') || $has('k-12') || $has('k12')) return 'High School';

        $canonals = [
            'doctorate' => 'Doctorate',
            'masters'   => 'Master’s',
            "master's"  => 'Master’s',
            'bachelors' => 'Bachelor’s',
            "bachelor's"=> 'Bachelor’s',
            'associate' => 'Associate',
            'vocational/technical' => 'Vocational/Technical',
            'vocational technical' => 'Vocational/Technical',
            'technical-vocational' => 'Vocational/Technical',
            'high school' => 'High School',
        ];
        if (isset($canonals[$s])) return $canonals[$s];

        return null;
    }

    // Higher number = more advanced
    public static function educationRank(?string $canonicalLevel): int {
        if ($canonicalLevel === null || $canonicalLevel === '' || mb_strtolower($canonicalLevel) === 'any') return 0;
        static $rank = [
            'High School'          => 1,
            'Vocational/Technical' => 2,
            'Associate'            => 3,
            'Bachelor’s'           => 4,
            'Master’s'             => 5,
            'Doctorate'            => 6,
        ];
        return $rank[$canonicalLevel] ?? 0;
    }

    // Normalize/filter skills to the allowed set
    public static function canonicalizeSkills(array $names): array {
        $allowed = self::allowedSkills();
        $map = [];
        foreach ($allowed as $a) { $map[mb_strtolower($a)] = $a; }

        $syn = [
            'js' => 'javascript',
            'node' => 'javascript',
            'node.js' => 'javascript',
            'nodejs' => 'javascript',
            'reactjs' => 'react',
            'react.js' => 'react',
            'html' => 'html/css',
            'css'  => 'html/css',
        ];

        $out = [];
        $seen = [];
        foreach ($names as $n) {
            $n = trim((string)$n);
            if ($n === '') continue;
            $k = mb_strtolower($n);
            if (isset($syn[$k])) $k = $syn[$k];
            if (!isset($map[$k])) continue;
            $canon = $map[$k];
            if (!isset($seen[$canon])) {
                $out[] = $canon;
                $seen[$canon] = true;
            }
        }
        return $out;
    }
}