<?php
require_once __DIR__.'/Database.php';

class Search {
    public static function saveQuery(string $userId, string $query): bool {
        $q = trim($query);
        if ($q === '') return false;
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("INSERT INTO search_history (user_id, query) VALUES (?, ?) ON DUPLICATE KEY UPDATE created_at = NOW()");
            return $stmt->execute([$userId, $q]);
        } catch (Throwable $e) {
            return false;
        }
    }

    public static function getHistory(string $userId, int $limit = 8): array {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("SELECT query FROM search_history WHERE user_id=? ORDER BY created_at DESC LIMIT ?");
            $stmt->bindValue(1, $userId);
            $stmt->bindValue(2, $limit, PDO::PARAM_INT);
            $stmt->execute();
            return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'query');
        } catch (Throwable $e) {
            return [];
        }
    }

        public static function suggest(string $q, int $limit = 8): array {
        $q = trim($q);
        if ($q === '' || strlen($q) < 2) return [];
        try {
            $pdo = Database::getConnection();
            // Suggest titles and companies from approved, WFH jobs matching the query
                        $like = '%'.$q.'%';
                        // Top matching titles with counts
                        $sqlTitles = "
                            SELECT j.title AS s, COUNT(*) AS c
                            FROM jobs j
                            JOIN users u ON u.user_id = j.employer_id
                            WHERE u.role='employer' AND u.employer_status='Approved' AND j.remote_option='Work From Home' AND j.title LIKE ?
                            GROUP BY j.title
                            ORDER BY c DESC, MAX(j.created_at) DESC
                            LIMIT ?
                        ";
                        $st1 = $pdo->prepare($sqlTitles);
                        $st1->bindValue(1, $like, PDO::PARAM_STR);
                        $st1->bindValue(2, $limit, PDO::PARAM_INT);
                        $st1->execute();
                        $rows1 = $st1->fetchAll(PDO::FETCH_ASSOC) ?: [];

                        // Top matching companies with counts
                        $sqlCo = "
                            SELECT u.company_name AS s, COUNT(*) AS c
                            FROM jobs j
                            JOIN users u ON u.user_id = j.employer_id
                            WHERE u.role='employer' AND u.employer_status='Approved' AND j.remote_option='Work From Home' AND u.company_name LIKE ?
                            GROUP BY u.company_name
                            ORDER BY c DESC, MAX(j.created_at) DESC
                            LIMIT ?
                        ";
                        $st2 = $pdo->prepare($sqlCo);
                        $st2->bindValue(1, $like, PDO::PARAM_STR);
                        $st2->bindValue(2, $limit, PDO::PARAM_INT);
                        $st2->execute();
                        $rows2 = $st2->fetchAll(PDO::FETCH_ASSOC) ?: [];

                        // Merge with uniqueness by label (case-insensitive), and cap total to limit
                        $out = [];
                        $seen = [];
                        foreach (array_merge($rows1, $rows2) as $r) {
                                $label = trim((string)($r['s'] ?? ''));
                                $count = (int)($r['c'] ?? 0);
                                if ($label === '') continue;
                                $key = mb_strtolower($label);
                                if (isset($seen[$key])) continue;
                                $seen[$key] = true;
                                $out[] = ['text' => $label, 'count' => $count];
                                if (count($out) >= $limit) break;
                        }

                        // Fallbacks when suggestions are empty or only mirror the exact query
                        if (count($out) < $limit) {
                            // Build tokens from the query for a broader OR search
                            $qLower = mb_strtolower($q);
                            $rawTokens = preg_split('/[^a-z0-9]+/i', $qLower) ?: [];
                            $stop = ['and','the','for','with','from','work','home','wfh','remote','role','job','jobs','jr','junior','sr','senior','of','to','in','on','at','by','a','an'];
                            $tokens = [];
                            foreach ($rawTokens as $t) {
                                $t = trim($t);
                                if ($t === '' || mb_strlen($t) < 3) continue;
                                if (in_array($t, $stop, true)) continue;
                                $tokens[] = $t;
                            }
                            // De-dup tokens and limit to at most 4 meaningful ones
                            $tokens = array_values(array_unique($tokens));
                            if ($tokens) $tokens = array_slice($tokens, 0, 4);

                            if (!empty($tokens)) {
                                // Dynamic OR LIKEs by token
                                $conds = [];
                                $i = 0; $binds = [];
                                foreach ($tokens as $t) {
                                    $i++;
                                    $param = ':t'.$i;
                                    $conds[] = "j.title LIKE $param";
                                    $binds[$param] = '%'.$t.'%';
                                }
                                $sqlTok = "
                                    SELECT j.title AS s, COUNT(*) AS c
                                    FROM jobs j
                                    JOIN users u ON u.user_id = j.employer_id
                                    WHERE u.role='employer' AND u.employer_status='Approved' AND j.remote_option='Work From Home'
                                      AND (".implode(' OR ', $conds).")
                                    GROUP BY j.title
                                    ORDER BY c DESC, MAX(j.created_at) DESC
                                    LIMIT :lim
                                ";
                                $stTok = $pdo->prepare($sqlTok);
                                foreach ($binds as $k=>$v) { $stTok->bindValue($k, $v, PDO::PARAM_STR); }
                                $stTok->bindValue(':lim', $limit, PDO::PARAM_INT);
                                $stTok->execute();
                                $rowsTok = $stTok->fetchAll(PDO::FETCH_ASSOC) ?: [];
                                foreach ($rowsTok as $r) {
                                    $label = trim((string)($r['s'] ?? ''));
                                    $count = (int)($r['c'] ?? 0);
                                    if ($label === '') continue;
                                    $key = mb_strtolower($label);
                                    if (isset($seen[$key])) continue;
                                    $seen[$key] = true;
                                    $out[] = ['text' => $label, 'count' => $count];
                                    if (count($out) >= $limit) break;
                                }
                            }

                            // Prefix-based expansion: titles starting with the first meaningful token (e.g., "Virtual %")
                            if (count($out) < $limit) {
                                // Extract first word (from original query) with >=3 chars
                                $firstWord = '';
                                $parts = preg_split('/[^a-z0-9]+/i', $q) ?: [];
                                foreach ($parts as $w) { if (mb_strlen($w) >= 3) { $firstWord = $w; break; } }
                                if ($firstWord !== '' && !in_array(mb_strtolower($firstWord), $stop, true)) {
                                    try {
                                        $stP = $pdo->prepare("\n                                            SELECT j.title AS s, COUNT(*) AS c\n                                            FROM jobs j\n                                            JOIN users u ON u.user_id = j.employer_id\n                                            WHERE u.role='employer' AND u.employer_status='Approved' AND j.remote_option='Work From Home'\n                                              AND j.title LIKE :prefix\n                                            GROUP BY j.title\n                                            ORDER BY c DESC, MAX(j.created_at) DESC\n                                            LIMIT :lim\n                                        ");
                                        $stP->bindValue(':prefix', $firstWord.'%');
                                        $stP->bindValue(':lim', $limit, PDO::PARAM_INT);
                                        $stP->execute();
                                        $rowsP = $stP->fetchAll(PDO::FETCH_ASSOC) ?: [];
                                        foreach ($rowsP as $r) {
                                            $label = trim((string)($r['s'] ?? ''));
                                            $count = (int)($r['c'] ?? 0);
                                            if ($label === '') continue;
                                            $key = mb_strtolower($label);
                                            if (isset($seen[$key])) continue;
                                            $seen[$key] = true;
                                            $out[] = ['text' => $label, 'count' => $count];
                                            if (count($out) >= $limit) break;
                                        }
                                    } catch (Throwable $e) { /* ignore */ }
                                }
                            }

                            // Variant suggestion: remove parentheses and remote qualifiers
                            if (count($out) < $limit) {
                                $variant = preg_replace('/\([^\)]*\)/', ' ', $q);
                                $variant = preg_replace('/\b(remote|work\s*from\s*home|wfh|virtual)\b/i', ' ', $variant);
                                $variant = trim(preg_replace('/\s+/', ' ', (string)$variant));
                                if ($variant !== '' && mb_strtolower($variant) !== mb_strtolower($q)) {
                                    try {
                                        $stV = $pdo->prepare("SELECT COUNT(*) FROM jobs j JOIN users u ON u.user_id=j.employer_id WHERE u.role='employer' AND u.employer_status='Approved' AND j.remote_option='Work From Home' AND j.title LIKE ?");
                                        $stV->execute(['%'.$variant.'%']);
                                        $cntV = (int)$stV->fetchColumn();
                                        $key = mb_strtolower($variant);
                                        if (!isset($seen[$key])) {
                                            $seen[$key] = true;
                                            $out[] = ['text' => $variant, 'count' => $cntV];
                                        }
                                    } catch (Throwable $e) { /* ignore */ }
                                }
                            }
                        }

                        // Cap to limit
                        if (count($out) > $limit) $out = array_slice($out, 0, $limit);
                        return $out;
        } catch (Throwable $e) {
            return [];
        }
    }
}
?>
