<?php
class Report {
    public static function create(string $job_id, string $reporter_user_id, string $reason, string $details): bool {
        $pdo = Database::getConnection();
        $report_id = Helpers::generateSmartId('RPT');
        $stmt = $pdo->prepare("INSERT INTO job_reports (report_id, job_id, reporter_user_id, reason, details, status)
                               VALUES (?, ?, ?, ?, ?, 'Open')");
        return $stmt->execute([$report_id, $job_id, $reporter_user_id, $reason, $details]);
    }

    public static function listOpen(): array {
        $pdo = Database::getConnection();
        $sql = "SELECT r.*, j.title, j.employer_id, u2.name AS reporter_name, u.name AS employer_name
                FROM job_reports r
                JOIN jobs j ON r.job_id = j.job_id
                JOIN users u2 ON r.reporter_user_id = u2.user_id
                JOIN users u ON j.employer_id = u.user_id
                WHERE r.status = 'Open'
                ORDER BY r.created_at DESC";
        return $pdo->query($sql)->fetchAll();
    }

    public static function listAll(int $limit = 200): array {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT r.*, j.title, u2.name AS reporter_name
                               FROM job_reports r
                               LEFT JOIN jobs j ON r.job_id = j.job_id
                               LEFT JOIN users u2 ON r.reporter_user_id = u2.user_id
                               ORDER BY r.created_at DESC
                               LIMIT ?");
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    }

    public static function resolve(string $report_id): bool {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("UPDATE job_reports SET status = 'Resolved' WHERE report_id = ?");
        return $stmt->execute([$report_id]);
    }
}