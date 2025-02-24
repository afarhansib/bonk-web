<?php
namespace App\Models;

use App\Config\Database;
use PDO;

class User {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    public function register($username, $email, $password) {
        try {
            // Hash password
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            
            // Log the registration attempt
            error_log("Attempting to register user: $username with email: $email");
    
            $stmt = $this->db->prepare('INSERT INTO users (username, email, password) VALUES (?, ?, ?)');
            $stmt->execute([$username, $email, $hashedPassword]);
            
            // Create initial quota for user
            $userId = $this->db->lastInsertId();
            $stmt = $this->db->prepare('INSERT INTO quotas (user_id, bots_limit) VALUES (?, 3)');
            $stmt->execute([$userId]);
            
            return true;
        } catch (\PDOException $e) {
            // Log the error message
            error_log("Registration failed for user: $username. Error: " . $e->getMessage());
            return false;
        }
    }

    public function login($username, $password) {
        $stmt = $this->db->prepare('SELECT id, password FROM users WHERE username = ?');
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && password_verify($password, $user['password'])) {
            return ['id' => $user['id']];
        }
        return false;
    }

    public function getQuota($userId) {
        $stmt = $this->db->prepare('SELECT bots_limit, usage_count FROM quotas WHERE user_id = ?');
        $stmt->execute([$userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function updateQuota($userId, $botsLimit) {
        $stmt = $this->db->prepare('UPDATE quotas SET bots_limit = ? WHERE user_id = ?');
        return $stmt->execute([$botsLimit, $userId]);
    }
}
