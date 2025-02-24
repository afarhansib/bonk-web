<?php
namespace App\Models;

use App\Config\Database;
use PDO;

class Admin {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    public function getAllUsers() {
        $stmt = $this->db->query('
            SELECT u.*, q.bots_limit, q.usage_count,
                (SELECT COUNT(*) FROM bots WHERE user_id = u.id) as bot_count
            FROM users u
            LEFT JOIN quotas q ON u.id = q.user_id
            ORDER BY u.created_at DESC
        ');
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAllBots() {
        $stmt = $this->db->query('
            SELECT b.*, u.username as owner_username
            FROM bots b
            LEFT JOIN users u ON b.user_id = u.id
            ORDER BY b.created_at DESC
        ');
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateUserQuota($userId, $botsLimit) {
        $stmt = $this->db->prepare('
            UPDATE quotas 
            SET bots_limit = ? 
            WHERE user_id = ?
        ');
        return $stmt->execute([$botsLimit, $userId]);
    }

    public function deleteUser($userId) {
        try {
            $this->db->beginTransaction();

            // Delete user's bots
            $stmt = $this->db->prepare('DELETE FROM bots WHERE user_id = ?');
            $stmt->execute([$userId]);

            // Delete user's quota
            $stmt = $this->db->prepare('DELETE FROM quotas WHERE user_id = ?');
            $stmt->execute([$userId]);

            // Delete user
            $stmt = $this->db->prepare('DELETE FROM users WHERE id = ?');
            $stmt->execute([$userId]);

            $this->db->commit();
            return true;
        } catch (\PDOException $e) {
            $this->db->rollBack();
            return false;
        }
    }

    public function deleteBot($botId) {
        $stmt = $this->db->prepare('DELETE FROM bots WHERE bot_id = ?');
        return $stmt->execute([$botId]);
    }

    public function getStats() {
        $stats = [];
        
        // Total users
        $stmt = $this->db->query('SELECT COUNT(*) as count FROM users');
        $stats['total_users'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

        // Total bots
        $stmt = $this->db->query('SELECT COUNT(*) as count FROM bots');
        $stats['total_bots'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

        // Active bots
        $stmt = $this->db->query('SELECT COUNT(*) as count FROM bots WHERE is_active = 1');
        $stats['active_bots'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

        // Users at quota limit
        $stmt = $this->db->query('
            SELECT COUNT(*) as count 
            FROM quotas 
            WHERE usage_count >= bots_limit
        ');
        $stats['users_at_limit'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

        return $stats;
    }
}
