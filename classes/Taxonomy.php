<?php
/**
 * Taxonomy helper class.
 * Central place for enumerations / canonicalization.
 */
class Taxonomy
{
    /**
     * Return array of standardized education levels (display values).
     * Order IMPORTANT (ascending).
     */
    public static function educationLevels(): array
    {
        return [
            'Elementary',
            'High School',
            'Senior High School',
            'Vocational',
            'Associate',
            'College',
            'Bachelor',
            'Post Graduate',
            'Masteral',
            'Doctorate'
        ];
    }

    /**
     * Internal map (lowercased => canonical display) so flexible inputs still match.
     */
    private static function educationCanonicalMap(): array
    {
        $map = [];
        foreach (self::educationLevels() as $lvl) {
            $map[mb_strtolower($lvl)] = $lvl;
        }

        // Extra synonyms / aliases
        $map['masters']        = 'Masteral';
        $map['master']         = 'Masteral';
        $map['masters degree'] = 'Masteral';
        $map['bachelor degree'] = 'Bachelor';
        $map['college graduate'] = 'College';
        $map['postgraduate']   = 'Post Graduate';
        $map['post graduate']  = 'Post Graduate';
        $map['hs']             = 'High School';
        $map['shs']            = 'Senior High School';
        $map['elementary school'] = 'Elementary';
        $map['phd']            = 'Doctorate';
        $map['doctorate degree'] = 'Doctorate';

        return $map;
    }

    /**
     * Canonicalize a user / form input for education level.
     * Returns canonical string OR null if cannot map.
     * Empty string => treated as "Any" in job requirement context.
     */
    public static function canonicalizeEducation(?string $input): ?string
    {
        if ($input === null) return null;
        $trim = trim($input);
        if ($trim === '') return '';
        $lk = mb_strtolower($trim);
        $map = self::educationCanonicalMap();
        return $map[$lk] ?? null;
    }

    /**
     * Ranking map for education comparison in scoring.
     * Higher number => higher education.
     * Keys are LOWERCASE canonical values.
     */
    public static function educationRankMap(): array
    {
        $levels = self::educationLevels();
        $rank = [];
        $i = 1;
        foreach ($levels as $lvl) {
            $rank[mb_strtolower($lvl)] = $i++;
        }
        // Ensure aliases map to same rank
        $rank['masters'] = $rank['masteral'] ?? ($rank['masteral'] = $rank['masteral'] ?? $i);
        $rank['master']  = $rank['masteral'];
        $rank['postgraduate'] = $rank['post graduate'] = $rank['post graduate'] ?? ($rank['post graduate'] = $rank['post graduate'] ?? ($rank['post graduate'] = $rank['post graduate'] ?? ($rank['post graduate'] = $rank['post graduate'] ?? 0)));
        if (isset($rank['post graduate'])) {
            $rank['postgraduate'] = $rank['post graduate'];
        }
        $rank['phd'] = $rank['doctorate'] ?? ($rank['doctorate'] = $rank['doctorate'] ?? $i);
        return $rank;
    }

    /**
     * Employment types list.
     */
    public static function employmentTypes(): array
    {
        return [
            'Full time',
            'Part time',
            'Contract',
            'Freelance',
            'Internship',
            'Temporary'
        ];
    }

    /**
     * Accessibility tags list (extend freely).
     */
    public static function accessibilityTags(): array
    {
        return [
            'PWD-Friendly',
            'Screen Reader Friendly',
            'Flexible Hours',
            'Wheelchair Accessible',
            'Assistive Tech Provided',
            'Internet Allowance',
            'Asynchronous',
            'Work From Home'
        ];
    }

    /**
     * (Legacy placeholder if you had an allowedSkills list earlier.)
     * Keeping for backward compatibility – can be trimmed if unused.
     */
    public static function allowedSkills(): array
    {
        return [
            'PHP','JavaScript','Python','Java','C#','C++','SQL',
            'HTML/CSS','React','Laravel','Git','Django'
        ];
    }

    /**
     * OPTIONAL general (soft) skills centralization.
     * Currently logic in pages defines them; kept here for future re-use.
     */
    public static function generalSkills(): array
    {
        return [
            '70+ WPM Typing',
            'Flexible Schedule',
            'Team Player',
            'Professional Attitude',
            'Strong Communication',
            'Adaptable / Quick Learner'
        ];
    }
}