<?php
namespace App\Models;

use App\Config\Database;
use PDO;

class Server {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->createTable();
    }

    private function createTable() {
        $this->db->exec('
            CREATE TABLE IF NOT EXISTS servers (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                ip TEXT NOT NULL,
                port INTEGER NOT NULL,
                description TEXT,
                is_promoted BOOLEAN DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ');
    }

    public function create($data) {
        $stmt = $this->db->prepare('
            INSERT INTO servers (name, ip, port, description, is_promoted)
            VALUES (?, ?, ?, ?, ?)
        ');
        return $stmt->execute([
            $data['name'],
            $data['ip'],
            $data['port'],
            $data['description'] ?? '',
            $data['is_promoted'] ?? 0
        ]);
    }

    public function getAll() {
        $stmt = $this->db->query('
            SELECT * FROM servers 
            ORDER BY is_promoted DESC, created_at DESC
        ');
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function update($id, $data) {
        $stmt = $this->db->prepare('
            UPDATE servers 
            SET name = ?, ip = ?, port = ?, description = ?, is_promoted = ?
            WHERE id = ?
        ');
        return $stmt->execute([
            $data['name'],
            $data['ip'],
            $data['port'],
            $data['description'] ?? '',
            $data['is_promoted'] ?? 0,
            $id
        ]);
    }

    public function delete($id) {
        $stmt = $this->db->prepare('DELETE FROM servers WHERE id = ?');
        return $stmt->execute([$id]);
    }
}
