<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/Database.php';

class SupportTicket
{
    public static function list(array $opts = []): array {
        $pdo = Database::getConnection();
        $status = $opts['status'] ?? null;
        $search = trim($opts['search'] ?? '');
        $params = [];
        $where = [];

        if ($status && in_array($status, ['Open','Pending','Closed','Resolved'], true)) {
            $where[] = 't.status = :st';
            $params[':st'] = $status;
        }

        if ($search !== '') {
            $where[] = '(t.ticket_id LIKE :q OR t.subject LIKE :q OR t.name LIKE :q OR t.email LIKE :q)';
            $params[':q'] = '%' . $search . '%';
        }

        $sql = "SELECT t.ticket_id, t.user_id, t.name, t.email, t.subject, t.message, t.status,
                       t.created_at, t.updated_at,
                       u.role AS user_role
                FROM support_tickets t
                LEFT JOIN users u ON u.user_id = t.user_id";
        if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
        $sql .= ' ORDER BY t.created_at DESC LIMIT 200'; // simple cap

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function find(string $ticket_id): ?array {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT t.*, u.role AS user_role
                               FROM support_tickets t
                               LEFT JOIN users u ON u.user_id = t.user_id
                               WHERE t.ticket_id = ? LIMIT 1");
        $stmt->execute([$ticket_id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function updateStatus(string $ticket_id, string $status): bool {
        $allowed = ['Open','Pending','Closed','Resolved'];
        if (!in_array($status, $allowed, true)) return false;
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("UPDATE support_tickets SET status = :st, updated_at = NOW() WHERE ticket_id = :id LIMIT 1");
        return $stmt->execute([':st'=>$status, ':id'=>$ticket_id]);
    }

    public static function delete(string $ticket_id): bool {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("DELETE FROM support_tickets WHERE ticket_id = ? LIMIT 1");
        return $stmt->execute([$ticket_id]);
    }
}