<?php
require_once __DIR__ . '/../../vendor/autoload.php';

use App\Controllers\AdminController;
use App\Controllers\AuthController;
use App\Controllers\BotController;
use App\Controllers\ServerController;

$auth = new AuthController();
$admin = new AdminController();
$botController = new BotController();
$serverController = new ServerController();

// Check if user is logged in
if (!$auth->isLoggedIn()) {
    header('Location: /login');
    exit;
}

$userId = $auth->getCurrentUserId();
$bots = $botController->getUserBots($userId);
$servers = $serverController->getAll();

// Handle bot creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_bot') {
    $result = $botController->createBot($userId, [
        'name' => $_POST['name'],
        'username' => $_POST['username'],
        'server_ip' => $_POST['server_ip'],
        'server_port' => $_POST['server_port']
    ]);

    if (isset($result['success'])) {
        header('Location: /dashboard');
        exit;
    }
}

// Handle bot update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_bot') {
    $result = $botController->updateBot($userId, [
        'bot_id' => $_POST['bot_id'],
        'name' => $_POST['name'],
        'username' => $_POST['username'],
        'server_ip' => $_POST['server_ip'],
        'server_port' => $_POST['server_port']
    ]);

    header('Content-Type: application/json');
    echo json_encode($result);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Bonk</title>
    <link href="/css/style.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="/images/icon.png">
    <script src="/js/bot-manager.js"></script>
    <script>
        let botManager;

        window.addEventListener('load', () => {
            <?php $nodeConfig = require __DIR__ . '/../../config/node-server.php'; ?>
            botManager = new BotManager(
                '<?php echo $nodeConfig['url']; ?>',
                '<?php echo $nodeConfig['ws_url']; ?>'
            );

            // Connect to existing active bots
            <?php foreach ($bots as $bot): ?>
                <?php if ($bot['is_active']): ?>
                    botManager.connectLogs(
                        '<?php echo htmlspecialchars($bot['bot_id']); ?>',
                        document.getElementById('status-<?php echo htmlspecialchars($bot['bot_id']); ?>'),
                        document.getElementById('logs-<?php echo htmlspecialchars($bot['bot_id']); ?>')
                    );
                <?php endif; ?>
            <?php endforeach; ?>
        });

        function toggleBot(botId) {
            botManager.toggleBot(
                botId,
                document.getElementById(`status-${botId}`),
                document.getElementById(`logs-${botId}`)
            );
        }

        function clearLogs(botId) {
            botManager.clearLogs(document.getElementById(`logs-${botId}`));
        }

        function fillServerDetails(ip, port) {
            document.getElementById('server_ip').value = ip;
            document.getElementById('server_port').value = port;
        }

        function openEditModal(botId) {
            const bot = botData.find(b => b.bot_id === botId);
            if (bot) {
                document.getElementById('edit_bot_id').value = bot.bot_id;
                document.getElementById('edit_name').value = bot.name;
                document.getElementById('edit_username').value = bot.username;
                document.getElementById('edit_server_ip').value = bot.server_ip;
                document.getElementById('edit_server_port').value = bot.server_port;
                editModal.classList.remove('hidden');
            }
        }

        function closeEditModal() {
            editModal.classList.add('hidden');
        }
    </script>
</head>

<body class="bg-gray-100">
    <div class="min-h-screen">
        <!-- Navigation -->
        <nav class="bg-white shadow-lg">
            <div class="max-w-7xl mx-auto px-4">
                <div class="flex justify-between h-16">
                    <div class="flex items-center space-x-4">
                        <img src="/images/icon.png" class="w-8" alt="Bonk Logo">
                        <span class="text-xl font-bold">Bonk</span>
                        <?php if ($admin->isAdmin()): ?>
                            <a href="/admin" class="text-gray-600 hover:text-gray-900">Admin</a>
                        <?php endif; ?>
                    </div>
                    <div class="flex items-center space-x-4">
                        <form action="/logout" method="POST">
                            <button type="submit" class="text-gray-600 hover:text-gray-900">Logout</button>
                        </form>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Main Content -->
        <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
            <!-- Create Bot Form -->
            <div class="bg-white shadow-sm ring-1 ring-gray-900/5 sm:rounded-xl md:col-span-2">
                <form method="POST" class="bg-white shadow-sm ring-1 ring-gray-900/5 sm:rounded-xl md:col-span-2">
                    <input type="hidden" name="action" value="create_bot">
                    <div class="px-4 py-6 sm:p-8">
                        <div class="grid grid-cols-1 gap-x-6 gap-y-4 md:grid-cols-2">
                            <div>
                                <label for="name" class="block text-sm font-medium leading-6 text-gray-900">Bot Name</label>
                                <div class="mt-2">
                                    <input type="text" name="name" id="name" required
                                        class="block w-full rounded-md border-0 px-3 py-2 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6">
                                </div>
                            </div>

                            <div>
                                <label for="username" class="block text-sm font-medium leading-6 text-gray-900">Bot Username</label>
                                <div class="mt-2">
                                    <input type="text" name="username" id="username" required
                                        class="block w-full rounded-md border-0 px-3 py-2 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6">
                                </div>
                            </div>

                            <div>
                                <label for="server_ip" class="block text-sm font-medium leading-6 text-gray-900">Server IP</label>
                                <div class="mt-2">
                                    <input type="text" name="server_ip" id="server_ip" required placeholder="e.g., play.example.com"
                                        class="block w-full rounded-md border-0 px-3 py-2 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6">
                                </div>
                            </div>

                            <div>
                                <label for="server_port" class="block text-sm font-medium leading-6 text-gray-900">Server Port</label>
                                <div class="mt-2">
                                    <input type="number" name="server_port" id="server_port" required min="1" max="65535" placeholder="19132"
                                        class="block w-full rounded-md border-0 px-3 py-2 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Server List -->
                    <?php if (!empty($servers)): ?>
                    <div class="px-4 py-6 sm:p-8 border-t border-gray-900/10">
                        <h4 class="text-sm font-medium text-gray-900 mb-4">Available Servers / or Input Any Bedrock server above!</h4>
                        <div class="grid grid-cols-1 gap-3">
                            <?php foreach ($servers as $server): ?>
                            <button type="button" 
                                onclick="fillServerDetails('<?php echo htmlspecialchars($server['ip']); ?>', <?php echo htmlspecialchars($server['port']); ?>)"
                                class="relative flex flex-col gap-x-4 p-4 text-left hover:bg-gray-50 rounded-lg <?php echo $server['is_promoted'] ? 'border-2 border-yellow-300 bg-yellow-50' : 'border border-gray-200'; ?>">
                                <div class="flex items-center justify-between gap-x-4">
                                    <div class="min-w-0">
                                        <div class="flex items-center gap-x-2">
                                            <span class="truncate text-sm font-medium leading-6 text-gray-900"><?php echo htmlspecialchars($server['name']); ?></span>
                                            <?php if ($server['is_promoted']): ?>
                                            <span class="inline-flex items-center rounded-md bg-yellow-50 px-2 py-1 text-xs font-medium text-yellow-800 ring-1 ring-inset ring-yellow-600/20">
                                                Promoted
                                            </span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="mt-1 flex items-center gap-x-2 text-xs leading-5 text-gray-500">
                                            <p class="truncate"><?php echo htmlspecialchars($server['description']); ?></p>
                                        </div>
                                    </div>
                                    <div class="flex flex-none items-center gap-x-4">
                                        <div class="hidden sm:flex sm:flex-col sm:items-end">
                                            <p class="text-sm leading-6 text-gray-900"><?php echo htmlspecialchars($server['ip']); ?>:<?php echo htmlspecialchars($server['port']); ?></p>
                                        </div>
                                    </div>
                                </div>
                            </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="flex items-center justify-end gap-x-6 border-t border-gray-900/10 px-4 py-4 sm:px-8">
                        <button type="submit"
                            class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                            Create Bot
                        </button>
                    </div>
                </form>
            </div>

            <!-- Bots List -->
            <div class="mt-8 bg-white shadow-sm ring-1 ring-gray-900/5 sm:rounded-xl">
                <div class="border-b border-gray-900/10 px-4 py-6 sm:px-8">
                    <h2 class="text-base font-semibold leading-7 text-gray-900">Your Bots</h2>
                </div>
                <div class="grid grid-cols-1 gap-6">
                    <?php foreach ($bots as $bot): ?>
                    <div class="px-4 py-6 sm:px-8">
                        <div class="flex justify-between items-start mb-4 gap-4 flex-col sm:flex-row">
                            <div>
                                <h3 class="text-sm font-medium leading-6 text-gray-900"><?php echo htmlspecialchars($bot['name']); ?></h3>
                                <div class="mt-1 flex flex-col gap-y-1 text-xs leading-5 text-gray-500">
                                    <p>ID: <?php echo htmlspecialchars($bot['bot_id']); ?></p>
                                    <p>Server: <?php echo htmlspecialchars($bot['server_ip']); ?>:<?php echo htmlspecialchars($bot['server_port']); ?></p>
                                </div>
                            </div>
                            <div class="flex items-center space-x-4">
                                <span id="status-<?php echo htmlspecialchars($bot['bot_id']); ?>" 
                                    class="inline-flex items-center rounded-md px-2 py-1 text-xs font-medium <?php echo $bot['is_active'] ? 'bg-green-50 text-green-700 ring-1 ring-inset ring-green-600/20' : 'bg-red-50 text-red-700 ring-1 ring-inset ring-red-600/20'; ?>">
                                    <?php echo $bot['is_active'] ? 'Connected' : 'Disconnected'; ?>
                                </span>
                                <button onclick="botManager.startBot('<?php echo htmlspecialchars($bot['bot_id']); ?>', document.getElementById('status-<?php echo htmlspecialchars($bot['bot_id']); ?>'), document.getElementById('logs-<?php echo htmlspecialchars($bot['bot_id']); ?>'))"
                                    class="rounded-md bg-white px-2.5 py-1.5 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                                    Start
                                </button>
                                <button onclick="botManager.stopBot('<?php echo htmlspecialchars($bot['bot_id']); ?>', document.getElementById('status-<?php echo htmlspecialchars($bot['bot_id']); ?>'), document.getElementById('logs-<?php echo htmlspecialchars($bot['bot_id']); ?>'))"
                                    class="rounded-md bg-white px-2.5 py-1.5 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                                    Stop
                                </button>
                                <!-- <button onclick="botManager.restartBot('<?php echo htmlspecialchars($bot['bot_id']); ?>', document.getElementById('status-<?php echo htmlspecialchars($bot['bot_id']); ?>'), document.getElementById('logs-<?php echo htmlspecialchars($bot['bot_id']); ?>'))"
                                    class="rounded-md bg-white px-2.5 py-1.5 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                                    Restart
                                </button> -->
                                <button onclick="openEditModal('<?php echo htmlspecialchars($bot['bot_id']); ?>')"
                                    class="rounded-md bg-white px-2.5 py-1.5 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                                    Edit
                                </button>
                            </div>
                        </div>
                        <div id="logs-<?php echo htmlspecialchars($bot['bot_id']); ?>" 
                            class="font-mono text-xs bg-gray-50 px-3 py-2 h-48 overflow-y-auto rounded-md ring-1 ring-inset ring-gray-300">
                            <!-- Logs will be populated here -->
                        </div>
                        <div class="mt-2 flex items-center gap-2">
                            <input type="text" id="chat-input-<?php echo htmlspecialchars($bot['bot_id']); ?>" class="block w-full rounded-md border-0 px-3 py-2 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6" placeholder="Type your message..." onkeydown="if(event.key === 'Enter') { botManager.sendChatMessage('<?php echo htmlspecialchars($bot['bot_id']); ?>'); }">
                            <button onclick="botManager.sendChatMessage('<?php echo htmlspecialchars($bot['bot_id']); ?>')" class="rounded-md bg-indigo-600 px-3 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">Send</button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Bot Modal -->
    <div id="editBotModal" class="hidden fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center">
        <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4">
            <div class="px-4 py-5 sm:p-6">
                <h3 class="text-lg font-semibold leading-6 text-gray-900 mb-4">Edit Bot</h3>
                <form id="editBotForm" method="POST" action="/dashboard">
                    <input type="hidden" name="action" value="update_bot">
                    <input type="hidden" name="bot_id" id="edit_bot_id">
                    
                    <div class="space-y-4">
                        <div>
                            <label for="edit_name" class="block text-sm font-medium leading-6 text-gray-900">Bot Name</label>
                            <div class="mt-2">
                                <input type="text" name="name" id="edit_name" required
                                    class="block w-full rounded-md border-0 px-3 py-2 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6">
                            </div>
                        </div>

                        <div>
                            <label for="edit_username" class="block text-sm font-medium leading-6 text-gray-900">Bot Username</label>
                            <div class="mt-2">
                                <input type="text" name="username" id="edit_username" required
                                    class="block w-full rounded-md border-0 px-3 py-2 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6">
                            </div>
                        </div>

                        <div>
                            <label for="edit_server_ip" class="block text-sm font-medium leading-6 text-gray-900">Server IP</label>
                            <div class="mt-2">
                                <input type="text" name="server_ip" id="edit_server_ip" required
                                    class="block w-full rounded-md border-0 px-3 py-2 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6">
                            </div>
                        </div>

                        <div>
                            <label for="edit_server_port" class="block text-sm font-medium leading-6 text-gray-900">Server Port</label>
                            <div class="mt-2">
                                <input type="number" name="server_port" id="edit_server_port" required min="1" max="65535"
                                    class="block w-full rounded-md border-0 px-3 py-2 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6">
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end gap-x-4">
                        <button type="button" onclick="closeEditModal()"
                            class="rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                            Cancel
                        </button>
                        <button type="submit"
                            class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                            Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        const editModal = document.getElementById('editBotModal');
        const editForm = document.getElementById('editBotForm');
        const botData = <?php echo json_encode($bots); ?>;

        // Handle form submission
        editForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(editForm);
            
            try {
                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                if (result.success) {
                    window.location.reload();
                } else {
                    alert(result.error || 'Failed to update bot');
                }
            } catch (error) {
                alert('An error occurred while updating the bot');
            }
        });

        function closeEditModal() {
            editModal.classList.add('hidden');
        }

        // Close modal when clicking outside
        editModal.addEventListener('click', (e) => {
            if (e.target === editModal) {
                closeEditModal();
            }
        });
    </script>
</body>

</html>