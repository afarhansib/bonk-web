class BotManager {
    constructor(nodeUrl, nodeWsUrl) {
        this.nodeUrl = nodeUrl;
        this.nodeWsUrl = nodeWsUrl;
        this.activeConnections = new Map();
        this.logSockets = new Map();
    }

    async startBot(botId, statusElement, logsElement) {
        try {
            console.log('Starting bot with ID:', botId); // Log the bot ID
            statusElement.textContent = 'Connecting...';
            statusElement.className = 'inline-flex items-center rounded-md px-2 py-1 text-xs font-medium bg-yellow-50 text-yellow-700 ring-1 ring-inset ring-yellow-600/20';

            const response = await fetch('/api/bot/toggle', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ action: 'start', botId: botId })
            });

            console.log(response); // Log the response from the server
            const result = await response.json();
            if (result.success) {
                await this.connectLogs(botId, statusElement, logsElement);
                return true;
            } else {
                throw new Error(result.error || 'Failed to start bot');
            }
        } catch (error) {
            console.error('Error starting bot:', error);
            statusElement.textContent = 'Error';
            statusElement.className = 'inline-flex items-center rounded-md px-2 py-1 text-xs font-medium bg-red-50 text-red-700 ring-1 ring-inset ring-red-600/20';
            this.addLog(logsElement, `Error: ${error.message}`);
            return false;
        }
    }

    async stopBot(botId, statusElement, logsElement) {
        try {
            const response = await fetch('/api/bot/toggle', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ action: 'stop', botId: botId })
            });

            const result = await response.json();
            console.log(result);
            if (result.success) {
                this.addLog(logsElement, 'Bot stopped successfully.');
                statusElement.textContent = 'Bot stopped successfully.'; 
                statusElement.className = 'inline-flex items-center rounded-md px-2 py-1 text-xs font-medium bg-green-50 text-green-700 ring-1 ring-inset ring-green-600/20';
                return true;
            } else {
                throw new Error(result.error || 'Failed to stop bot');
            }
        } catch (error) {
            console.error('Error stopping bot:', error);
            statusElement.textContent = 'Error stopping bot.'; 
            statusElement.className = 'inline-flex items-center rounded-md px-2 py-1 text-xs font-medium bg-red-50 text-red-700 ring-1 ring-inset ring-red-600/20';
            this.addLog(logsElement, `Error: ${error.message}`);
            return false;
        }
    }

    async restartBot(botId, statusElement, logsElement) {
        try {
            const response = await fetch('/api/bot/toggle', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ action: 'restart', botId: botId })
            });

            const result = await response.json();
            if (result.success) {
                this.addLog(logsElement, 'Bot restarted successfully.');
                return true;
            } else {
                throw new Error(result.error || 'Failed to restart bot');
            }
        } catch (error) {
            console.error('Error restarting bot:', error);
            statusElement.textContent = 'Error';
            statusElement.className = 'inline-flex items-center rounded-md px-2 py-1 text-xs font-medium bg-red-50 text-red-700 ring-1 ring-inset ring-red-600/20';
            this.addLog(logsElement, `Error: ${error.message}`);
            return false;
        }
    }

    async connectLogs(botId, statusElement, logsElement) {
        try {
            if (this.logSockets.has(botId)) {
                this.logSockets.get(botId).close();
            }

            // Get WebSocket token through our PHP proxy
            const token = await this.getWebSocketToken(botId);
            if (!token) {
                throw new Error('Failed to get WebSocket token');
            }

            const ws = new WebSocket(`${this.nodeWsUrl}/bot/logs?token=${token}`);

            ws.onopen = () => {
                statusElement.textContent = 'Connected';
                statusElement.className = 'inline-flex items-center rounded-md px-2 py-1 text-xs font-medium bg-green-50 text-green-700 ring-1 ring-inset ring-green-600/20';
                this.addLog(logsElement, 'Connected to WebSocket');
            };

            ws.onmessage = (event) => {
                const data = JSON.parse(event.data);
                
                if (data.type === 'status') {
                    const isConnected = data.status === 'Connected';
                    statusElement.className = `inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ${isConnected ? 
                        'bg-green-50 text-green-700 ring-1 ring-inset ring-green-600/20' : 
                        'bg-red-50 text-red-700 ring-1 ring-inset ring-red-600/20'}`;
                    statusElement.textContent = data.status;
                } else if (data.type === 'log') {
                    this.addLog(logsElement, data.message);
                } else if (data.type === 'history') {
                    // Handle history data type
                    console.log(data);  
                    data.logs.forEach(log => {
                        this.addLog(logsElement, log.message);
                    });
                }
            };

            ws.onclose = () => {
                console.warn('WebSocket connection closed.');
                statusElement.textContent = 'Disconnected';
                statusElement.className = 'inline-flex items-center rounded-md px-2 py-1 text-xs font-medium bg-red-50 text-red-700 ring-1 ring-inset ring-red-600/20';
                this.addLog(logsElement, 'Disconnected from WebSocket');
                this.logSockets.delete(botId);
            };

            ws.onerror = (error) => {
                console.error('WebSocket error:', error);
                this.addLog(logsElement, `WebSocket error: ${error.message}`);
            };

            this.logSockets.set(botId, ws);
        } catch (error) {
            console.error('Error connecting WebSocket:', error);
            this.addLog(logsElement, `Error: ${error.message}`);
            statusElement.textContent = 'Error';
            statusElement.className = 'inline-flex items-center rounded-md px-2 py-1 text-xs font-medium bg-red-50 text-red-700 ring-1 ring-inset ring-red-600/20';
        }
    }

    async getWebSocketToken(botId) {
        try {
            const response = await fetch('/api/bot/toggle', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ 
                    action: 'get_logs_token',
                    botId 
                })
            });

            if (!response.ok) {
                console.error('Server returned:', response.status, response.statusText);
                const text = await response.text();
                console.error('Response body:', text);
                throw new Error(`Server returned ${response.status}`);
            }

            const data = await response.json();
            if (!data.success) {
                throw new Error(data.error || 'Failed to get token');
            }
            return data.token;
        } catch (error) {
            console.error('Error getting WebSocket token:', error);
            return null;
        }
    }

    async sendChatMessage(botId) {
        const messageInput = document.getElementById(`chat-input-${botId}`);
        const message = messageInput.value.trim();

        if (!message) {
            alert('Please enter a message.');
            return;
        }

        try {
            const response = await fetch('/api/bot/toggle', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ action: 'send_chat', botId: botId, message: message })
            });

            const result = await response.json();
            console.log(result);
            if (result.success) {
                messageInput.value = ''; // Clear the input field after sending
                // this.addLog(document.getElementById(`logs-${botId}`), `You: ${message}`); // Optionally log the sent message
            } else {
                throw new Error(result.error || 'Failed to send message');
            }
        } catch (error) {
            console.error('Error sending chat message:', error);
            alert(`Error: ${error.message}`);
        }
    }

    addLog(logsElement, message) {
        const timestamp = new Date().toLocaleTimeString();
        const logEntry = document.createElement('div');
    
        // Convert URLs in the message to clickable links
        const urlRegex = /(https?:\/\/\S+|www\.\S+)/g;
        const formattedMessage = message.replace(urlRegex, (url) => {
            const href = url.startsWith('http') ? url : 'http://' + url;
            return `<a href="${href}" target="_blank" style="color: green; text-decoration: underline; cursor: pointer;">${url}</a>`;
        });
    
        logEntry.innerHTML = `[${timestamp}] ${formattedMessage}`;
        logEntry.className = 'text-sm text-gray-600'; // Ensure this line is included for styling
        logsElement.appendChild(logEntry);
        logsElement.scrollTop = logsElement.scrollHeight;
    }

    clearLogs(logsElement) {
        logsElement.innerHTML = '';
    }
}
