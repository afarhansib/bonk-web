<?php
require_once __DIR__ . '/../vendor/autoload.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use App\Controllers\BotController;

$request = $_SERVER['REQUEST_URI'];
$basePath = '/';

// Remove query string from request
$request = strtok($request, '?');

// Remove trailing slash if present
$request = rtrim($request, '/');

// If empty request, set to root
if (empty($request)) {
    $request = '/';
}

// Handle API routes
if (strpos($request, '/api/') === 0) {
    header('Content-Type: application/json');
    
    $endpoint = substr($request, 5); // Remove /api/
    $endpoint = rtrim($endpoint, '.php'); // Remove .php if present
    
    switch ($endpoint) {
        case 'bot/toggle':
            $data = json_decode(file_get_contents('php://input'), true);
            if (!isset($data['action'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Action is required']);
                exit;
            }
            
            $botController = new BotController();
            echo json_encode($botController->handleRequest());
            break;
            
        default:
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Endpoint not found']);
    }
    exit;
}

// Handle web routes
switch ($request) {
    case '/':
        require __DIR__ . '/../src/views/login.php';
        break;
    case '/login':
        require __DIR__ . '/../src/views/login.php';
        break;
    case '/register':
        require __DIR__ . '/../src/views/register.php';
        break;
    case '/dashboard':
        require __DIR__ . '/../src/views/dashboard.php';
        break;
    case '/admin':
        require __DIR__ . '/../src/views/admin.php';
        break;
    case '/logout':
        require __DIR__ . '/../src/views/logout.php';
        break;
    default:
        http_response_code(404);
        echo '404 Not Found';
        break;
}
