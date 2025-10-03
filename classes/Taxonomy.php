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
        // Synonyms map to their canonical level ranks
        $rank['masters']         = $rank['masteral'] ?? ($rank['masteral'] = end($rank));
        $rank['master']          = $rank['masteral'];
        $rank['masters degree']  = $rank['masteral'];
        $rank['bachelor degree'] = $rank['bachelor'] ?? ($rank['bachelor'] = $rank['college'] ?? 0);
        $rank['college graduate']= $rank['college'] ?? 0;
        $rank['postgraduate']    = $rank['post graduate'] ?? ($rank['post graduate'] = $rank['post graduate'] ?? 0);
        $rank['phd']             = $rank['doctorate'] ?? 0;
        $rank['hs']              = $rank['high school'] ?? 0;
        $rank['shs']             = $rank['senior high school'] ?? 0;
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
            'Flexible Hours',
            'Night Shift Option',
            'Training Provided',
            'Internet Allowance',
            'Equipment Provided'
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

    /**
     * Standardized PWD disability categories (11 generalized types used across the app)
     */
    public static function disabilityCategories(): array
    {
        // Registration-driven set (align employer job targeting to these)
        return [
            'Learning disability',
            'Vision impairment',
            'Communication disorder',
            'Intellectual disability',
            'Orthopedic disability',
            'Chronic illness',
            'Hearing loss',
            'Speech impairment',
            'Hearing disability',
            'Physical disability'
        ];
    }

    /**
     * Canonicalize disability labels to one of disabilityCategories().
     * Returns canonical string or null if it cannot map.
     */
    public static function canonicalizeDisability(?string $input): ?string
    {
        if ($input === null) return null;
        $t = trim($input);
        if ($t === '') return '';
        $lk = mb_strtolower($t);
        static $map = null;
        if ($map === null) {
            $map = [];
            foreach (self::disabilityCategories() as $c) {
                $map[mb_strtolower($c)] = $c; // exact labels allowed
            }
            // Synonyms / legacy labels from earlier employer list → registration labels
            // Visual
            $map['visual impairment']     = 'Vision impairment';
            $map['vision impairment']     = 'Vision impairment';
            $map['visual disability']     = 'Vision impairment';
            // Hearing
            $map['hearing impairment']    = 'Hearing disability'; // collapse to registration bucket
            // keep explicit ones too (already exact: hearing loss, hearing disability)
            // Speech / communication
            $map['speech impairment']     = 'Speech impairment';
            $map['speech disorder']       = 'Speech impairment';
            $map['communication disorder']= 'Communication disorder';
            // Mobility / physical / orthopedic / limb
            $map['mobility impairment']   = 'Physical disability';
            $map['upper limb impairment'] = 'Physical disability';
            $map['lower limb impairment'] = 'Physical disability';
            $map['orthopedic disability'] = 'Orthopedic disability';
            $map['physical disability']   = 'Physical disability';
            // Neuro / psychosocial
            $map['psychosocial disability'] = 'Chronic illness';
            $map['autism spectrum']         = 'Communication disorder';
            $map['asd']                     = 'Communication disorder';
            $map['autism']                  = 'Communication disorder';
        }
        return $map[$lk] ?? null;
    }
}