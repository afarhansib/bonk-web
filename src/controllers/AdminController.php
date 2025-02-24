<?php
namespace App\Controllers;

use App\Models\Admin;

class AdminController {
    private $admin;

    public function __construct() {
        $this->admin = new Admin();
    }

    public function getAllUsers() {
        return $this->admin->getAllUsers();
    }

    public function getAllBots() {
        return $this->admin->getAllBots();
    }

    public function updateUserQuota($userId, $botsLimit) {
        if (!is_numeric($botsLimit) || $botsLimit < 0) {
            return ['error' => 'Invalid bot limit'];
        }
        
        if ($this->admin->updateUserQuota($userId, $botsLimit)) {
            return ['success' => 'Quota updated successfully'];
        }
        return ['error' => 'Failed to update quota'];
    }

    public function deleteUser($userId) {
        if ($this->admin->deleteUser($userId)) {
            return ['success' => 'User deleted successfully'];
        }
        return ['error' => 'Failed to delete user'];
    }

    public function deleteBot($botId) {
        if ($this->admin->deleteBot($botId)) {
            return ['success' => 'Bot deleted successfully'];
        }
        return ['error' => 'Failed to delete bot'];
    }

    public function getStats() {
        return $this->admin->getStats();
    }

    public function isAdmin() {
        // For simplicity, we'll use user ID 1 as admin
        // In a real application, you should have a proper admin role system
        return isset($_SESSION['user_id']) && $_SESSION['user_id'] === 1;
    }
}
