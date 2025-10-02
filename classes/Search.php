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
                        return $out;
        } catch (Throwable $e) {
            return [];
        }
    }
}
?>
