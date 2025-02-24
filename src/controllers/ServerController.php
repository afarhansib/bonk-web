<?php
namespace App\Controllers;

use App\Models\Server;

class ServerController {
    private $server;

    public function __construct() {
        $this->server = new Server();
    }

    public function getAll() {
        return $this->server->getAll();
    }

    public function create($data) {
        // Validate input
        if (empty($data['name']) || empty($data['ip']) || empty($data['port'])) {
            return ['error' => 'All fields are required'];
        }

        if (!filter_var($data['port'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]])) {
            return ['error' => 'Invalid port number'];
        }

        if ($this->server->create($data)) {
            return ['success' => 'Server added successfully'];
        }
        return ['error' => 'Failed to add server'];
    }

    public function update($id, $data) {
        // Validate input
        if (empty($data['name']) || empty($data['ip']) || empty($data['port'])) {
            return ['error' => 'All fields are required'];
        }

        if (!filter_var($data['port'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]])) {
            return ['error' => 'Invalid port number'];
        }

        if ($this->server->update($id, $data)) {
            return ['success' => 'Server updated successfully'];
        }
        return ['error' => 'Failed to update server'];
    }

    public function delete($id) {
        if ($this->server->delete($id)) {
            return ['success' => 'Server deleted successfully'];
        }
        return ['error' => 'Failed to delete server'];
    }
}
