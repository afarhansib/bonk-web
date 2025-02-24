<?php
namespace App\Controllers;

use App\Models\Bot;

class BotController {
    private $bot;
    private $config;

    public function __construct() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $this->bot = new Bot();
        $this->config = require __DIR__ . '/../../config/node-server.php';
    }

    public function getUserBots($userId) {
        return $this->bot->getUserBots($userId);
    }

    public function createBot($userId, $data) {
        // Validate input
        if (empty($data['name']) || empty($data['username'])) {
            return ['error' => 'Bot name and username are required'];
        }

        // Validate server port if provided
        if (!empty($data['server_port'])) {
            $port = (int)$data['server_port'];
            if ($port < 1 || $port > 65535) {
                return ['error' => 'Invalid server port'];
            }
        }
        $botId = $this->bot->create($userId, $data);
        if ($botId) {
            return ['success' => true, 'botId' => $botId];
        }
        return ['success' => false, 'error' => 'Failed to create bot'];
    }

    public function getBotById($botId) {
        error_log("getBotById " . $botId);  
        error_log("user " . ($_SESSION['user_id'] ?? 'not set'));  
        if (!isset($_SESSION['user_id'])) {
            return null;
        }
        return $this->bot->getBot($botId, $_SESSION['user_id']);
    }

    public function updateBotStatus($botId, $isActive) {
        return $this->bot->updateStatus($botId, $isActive);
    }

    public function toggleBot($botId) {
        // Get current bot status
        $bot = $this->getBotById($botId);
        if (!$bot) {
            return ['success' => false, 'error' => 'Bot not found'];
        }

        // Determine endpoint based on current status
        $endpoint = $bot['is_active'] ? '/bot/stop' : '/bot/start';
        $url = $this->config['url'] . $endpoint;
        error_log("Attempting to toggle bot $botId at URL: $url (Current status: " . ($bot['is_active'] ? 'active' : 'inactive') . ")");

        $postData = json_encode([
            'botId' => $botId,
            'serverIp' => $bot['server_ip'],
            'serverPort' => (int)$bot['server_port'],
            'botUsername' => $bot['username']
        ]);
        error_log("Request payload: $postData");

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'x-api-key: ' . $this->config['api_key']
            ],
            CURLOPT_POSTFIELDS => $postData,
            CURLOPT_VERBOSE => true
        ]);

        // Capture CURL debug output
        $verbose = fopen('php://temp', 'w+');
        curl_setopt($ch, CURLOPT_STDERR, $verbose);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        // Log curl errors if any
        if ($error = curl_error($ch)) {
            error_log("cURL Error: " . $error);
        }

        // Get verbose information
        rewind($verbose);
        $verboseLog = stream_get_contents($verbose);
        error_log("Curl verbose output: " . $verboseLog);
        
        error_log("Response HTTP Code: $httpCode");
        error_log("Raw response: " . print_r($response, true));

        curl_close($ch);
        fclose($verbose);

        if ($httpCode === 200) {
            $result = json_decode($response, true);
            error_log("Decoded response: " . print_r($result, true));
            
            // Update bot status in database
            if (isset($result['status'])) {
                $this->updateBotStatus($botId, !$bot['is_active']);
                return ['success' => true];
            }
            return ['success' => false, 'error' => $result['error'] ?? 'Failed to toggle bot'];
        }
        
        return ['success' => false, 'error' => "Failed to connect to bot server (HTTP $httpCode)"];
    }

    public function getLogsToken($botId) {
        if (!isset($_SESSION['user_id'])) {
            return ['success' => false, 'error' => 'Unauthorized'];
        }

        // Verify bot belongs to user
        $bot = $this->getBotById($botId);
        if (!$bot) {
            return ['success' => false, 'error' => 'Bot not found'];
        }

        $url = $this->config['url'] . '/bot/logs/token';
        error_log("Getting WebSocket token from: $url");

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'x-api-key: ' . $this->config['api_key']
            ],
            CURLOPT_POSTFIELDS => json_encode(['botId' => $botId])
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if ($error = curl_error($ch)) {
            error_log("cURL Error: " . $error);
            return ['success' => false, 'error' => 'Failed to get WebSocket token'];
        }

        curl_close($ch);

        if ($httpCode === 200) {
            $result = json_decode($response, true);
            if (isset($result['token'])) {
                return ['success' => true, 'token' => $result['token']];
            }
        }
        
        return ['success' => false, 'error' => "Failed to get WebSocket token (HTTP $httpCode)"];
    }

    public function startBot($botId) {
        $bot = $this->getBotById($botId);
        if (!$bot) {
            return ['success' => false, 'error' => 'Bot not found'];
        }

        error_log(json_encode($bot));
        // Call the Node.js API to start the bot
        $url = $this->config['url'] . '/bot/start';
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'x-api-key: ' . $this->config['api_key']
            ],
            CURLOPT_POSTFIELDS => json_encode(['botId' => $botId, 'data' => $bot])
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            $this->bot->updateStatus($botId, true);
            return ['success' => true];
        } else if ($httpCode === 400) {
            $responseData = json_decode($response, true);
            if (isset($responseData['error']) && $responseData['error'] === 'Bot already running') {
                return ['success' => true]; // Treat as success if bot is already running
            }
        }
        return ['success' => false, 'error' => 'Failed to start bot'];
    }

    public function stopBot($botId) {
        $bot = $this->getBotById($botId);
        if (!$bot) {
            return ['success' => false, 'error' => 'Bot not found'];
        }

        // Call the Node.js API to stop the bot
        // Implement the logic to stop the bot here
        // Assuming the endpoint is /bot/stop

        $url = $this->config['url'] . '/bot/stop';
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'x-api-key: ' . $this->config['api_key']
            ],
            CURLOPT_POSTFIELDS => json_encode(['botId' => $botId])
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            $this->bot->updateStatus($botId, false);
            return ['success' => true];
        }
        return ['success' => false, 'error' => 'Failed to stop the bot. Response Code: ' . $httpCode];
    }

    public function restartBot($botId) {
        $stopResult = $this->stopBot($botId);
        if (!$stopResult['success']) {
            return $stopResult;
        }

        return $this->startBot($botId);
    }

    public function sendChat($botId, $message) {
        $bot = $this->getBotById($botId);
        if (!$bot) {
            return ['success' => false, 'error' => 'Bot not found'];
        }

        // Call the Node.js API to send the chat message
        $url = $this->config['url'] . '/bot/chat';
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'x-api-key: ' . $this->config['api_key']
            ],
            CURLOPT_POSTFIELDS => json_encode(['botId' => $botId, 'message' => $message])
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            return ['success' => true];
        }
        return ['success' => false, 'error' => 'Failed to send chat message. Response Code: ' . $httpCode];
    }

    public function handleRequest() {
        $data = json_decode(file_get_contents('php://input'), true);
        error_log("Received request: " . print_r($data, true));

        if (!isset($data['action'])) {
            return ['success' => false, 'error' => 'No action specified'];
        }

        switch ($data['action']) {
            case 'toggle':
                if (!isset($data['botId'])) {
                    return ['success' => false, 'error' => 'No bot ID specified'];
                }
                return $this->toggleBot($data['botId']);
            
            case 'get_logs_token':
                if (!isset($data['botId'])) {
                    return ['success' => false, 'error' => 'No bot ID specified'];
                }
                return $this->getLogsToken($data['botId']);

            case 'start':
                if (!isset($data['botId'])) {
                    return ['success' => false, 'error' => 'No bot ID specified'];
                }
                return $this->startBot($data['botId']);

            case 'stop':
                if (!isset($data['botId'])) {
                    return ['success' => false, 'error' => 'No bot ID specified'];
                }
                return $this->stopBot($data['botId']);

            case 'restart':
                if (!isset($data['botId'])) {
                    return ['success' => false, 'error' => 'No bot ID specified'];
                }
                return $this->restartBot($data['botId']);

            case 'update_bot':
                return $this->updateBot($data);

            case 'send_chat':
                if (!isset($data['botId']) || !isset($data['message'])) {
                    return ['success' => false, 'error' => 'No bot ID or message specified'];
                }
                return $this->sendChat($data['botId'], $data['message']);

            default:
                return ['success' => false, 'error' => 'Invalid action'];
        }
    }

    public function updateBot($userId, $data) {
        // Validate input
        if (empty($data['bot_id']) || empty($data['name']) || empty($data['username']) || empty($data['server_ip']) || empty($data['server_port'])) {
            return ['success' => false, 'error' => 'All fields are required'];
        }

        // Update the bot details in the database or data structure
        // Assuming you have a method in your Bot model to update the bot details
        $result = $this->bot->update($data['bot_id'], $userId, [
            'name' => $data['name'],
            'server_ip' => $data['server_ip'],
            'server_port' => $data['server_port'],
            'username' => $data['username'],
        ]);

        if ($result) {
            return ['success' => true];
        }
        return ['success' => false, 'error' => 'Failed to update bot details'];
    }
}
