<?php
namespace App\Models;

use App\Config\Database;
use PDO;

class Bot {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    public function create($userId, $data) {
        try {
            // Check user's bot quota
            $quotaStmt = $this->db->prepare('
                SELECT bots_limit, usage_count 
                FROM quotas 
                WHERE user_id = ?
            ');
            $quotaStmt->execute([$userId]);
            $quota = $quotaStmt->fetch(PDO::FETCH_ASSOC);

            if ($quota['usage_count'] >= $quota['bots_limit']) {
                return false;
            }

            // Generate unique bot_id
            $botId = uniqid('bonk_');

            $stmt = $this->db->prepare('
                INSERT INTO bots (user_id, bot_id, name, server_ip, server_port, username)
                VALUES (?, ?, ?, ?, ?, ?)
            ');

            $success = $stmt->execute([
                $userId,
                $botId,
                $data['name'],
                $data['server_ip'],
                $data['server_port'],
                $data['username']
            ]);

            if ($success) {
                // Update quota usage
                $this->db->prepare('
                    UPDATE quotas 
                    SET usage_count = usage_count + 1 
                    WHERE user_id = ?
                ')->execute([$userId]);
            }

            return $success ? $botId : false;
        } catch (\PDOException $e) {
            return false;
        }
    }

    public function update($botId, $userId, $data) {
        try {
            $stmt = $this->db->prepare('
                UPDATE bots 
                SET name = ?, server_ip = ?, server_port = ?, username = ?
                WHERE bot_id = ? AND user_id = ?
            ');

            $result = $stmt->execute([
                $data['name'],
                $data['server_ip'],
                $data['server_port'],
                $data['username'],
                $botId,
                $userId
            ]);

            if ($result && $stmt->rowCount() > 0) {
                return true;
            }
            return false;
            
        } catch (\PDOException $e) {
            return false;
        }
    }

    public function getBot($botId, $userId) {
        $stmt = $this->db->prepare('
            SELECT * FROM bots WHERE bot_id = ? AND user_id = ?
        ');
        $stmt->execute([$botId, $userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getUserBots($userId) {
        $stmt = $this->db->prepare('
            SELECT * FROM bots 
            WHERE user_id = ? 
            ORDER BY created_at DESC
        ');
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getUserQuota($userId) {
        $stmt = $this->db->prepare('
            SELECT bots_limit, usage_count 
            FROM quotas 
            WHERE user_id = ?
        ');
        $stmt->execute([$userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function updateQuotaUsage($userId) {
        $stmt = $this->db->prepare('
            UPDATE quotas 
            SET usage_count = usage_count + 1 
            WHERE user_id = ?
        ');
        return $stmt->execute([$userId]);
    }

    public function updateStatus($botId, $isActive) {
        $stmt = $this->db->prepare('
            UPDATE bots 
            SET is_active = ? 
            WHERE bot_id = ?
        ');
        return $stmt->execute([$isActive ? 1 : 0, $botId]);
    }
}
