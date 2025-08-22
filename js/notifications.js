/**
 * Real-time Notifications System
 * Manages SSE connection and notification display
 */

class NotificationManager {
    constructor() {
        this.eventSource = null;
        this.isConnected = false;
        this.reconnectAttempts = 0;
        this.maxReconnectAttempts = 5;
        this.notifications = [];
        this.unreadCount = 0;
        
        // Callbacks
        this.onNotification = null;
        this.onConnectionChange = null;
        this.onUnreadCountChange = null;
        
        this.initializeUI();
    }

    /**
     * Initialize notification UI elements
     */
    initializeUI() {
        // Create notification container if it doesn't exist
        if (!document.getElementById('notificationContainer')) {
            const container = document.createElement('div');
            container.id = 'notificationContainer';
            container.className = 'notification-container';
            document.body.appendChild(container);
        }

        // Add notification styles
        if (!document.getElementById('notificationStyles')) {
            const styles = document.createElement('style');
            styles.id = 'notificationStyles';
            styles.textContent = `
                .notification-container {
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    z-index: 10000;
                    max-width: 400px;
                    pointer-events: none;
                }

                .notification-toast {
                    background: rgba(255,255,255,0.95);
                    backdrop-filter: blur(20px);
                    border-radius: 12px;
                    padding: 16px;
                    margin-bottom: 12px;
                    box-shadow: 0 4px 20px rgba(0,0,0,0.15);
                    border-left: 4px solid #007bff;
                    color: #333;
                    pointer-events: auto;
                    animation: slideInRight 0.3s ease-out;
                    position: relative;
                    overflow: hidden;
                }

                .notification-toast.success {
                    border-left-color: #28a745;
                }

                .notification-toast.warning {
                    border-left-color: #ffc107;
                }

                .notification-toast.error {
                    border-left-color: #dc3545;
                }

                .notification-header {
                    display: flex;
                    justify-content: space-between;
                    align-items: flex-start;
                    margin-bottom: 8px;
                }

                .notification-title {
                    font-weight: 600;
                    font-size: 0.95rem;
                    flex: 1;
                }

                .notification-close {
                    background: none;
                    border: none;
                    color: #666;
                    cursor: pointer;
                    padding: 0;
                    font-size: 1.2rem;
                    line-height: 1;
                    margin-left: 8px;
                }

                .notification-message {
                    font-size: 0.9rem;
                    color: #666;
                    margin-bottom: 8px;
                }

                .notification-actions {
                    display: flex;
                    gap: 8px;
                    margin-top: 12px;
                }

                .notification-btn {
                    padding: 6px 12px;
                    border: none;
                    border-radius: 6px;
                    font-size: 0.8rem;
                    font-weight: 600;
                    cursor: pointer;
                    transition: all 0.2s ease;
                }

                .notification-btn-primary {
                    background: #007bff;
                    color: white;
                }

                .notification-btn-secondary {
                    background: rgba(0,0,0,0.1);
                    color: #333;
                }

                .notification-time {
                    font-size: 0.75rem;
                    color: #999;
                    margin-top: 4px;
                }

                .notification-icon {
                    width: 20px;
                    height: 20px;
                    margin-right: 8px;
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                }

                @keyframes slideInRight {
                    from {
                        transform: translateX(100%);
                        opacity: 0;
                    }
                    to {
                        transform: translateX(0);
                        opacity: 1;
                    }
                }

                @keyframes slideOutRight {
                    from {
                        transform: translateX(0);
                        opacity: 1;
                    }
                    to {
                        transform: translateX(100%);
                        opacity: 0;
                    }
                }

                .notification-toast.removing {
                    animation: slideOutRight 0.3s ease-out forwards;
                }

                /* Connection status indicator */
                .connection-status {
                    position: fixed;
                    top: 10px;
                    left: 50%;
                    transform: translateX(-50%);
                    background: rgba(0,0,0,0.8);
                    color: white;
                    padding: 8px 16px;
                    border-radius: 20px;
                    font-size: 0.8rem;
                    z-index: 10001;
                    opacity: 0;
                    transition: opacity 0.3s ease;
                }

                .connection-status.show {
                    opacity: 1;
                }

                .connection-status.connected {
                    background: rgba(40,167,69,0.9);
                }

                .connection-status.disconnected {
                    background: rgba(220,53,69,0.9);
                }

                /* Notification badge */
                .notification-badge {
                    background: #dc3545;
                    color: white;
                    border-radius: 50%;
                    padding: 2px 6px;
                    font-size: 0.7rem;
                    font-weight: 600;
                    min-width: 18px;
                    height: 18px;
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    position: absolute;
                    top: -8px;
                    right: -8px;
                }
            `;
            document.head.appendChild(styles);
        }

        // Create connection status indicator
        if (!document.getElementById('connectionStatus')) {
            const status = document.createElement('div');
            status.id = 'connectionStatus';
            status.className = 'connection-status';
            document.body.appendChild(status);
        }
    }

    /**
     * Start SSE connection
     */
    connect() {
        const authToken = localStorage.getItem('auth_token');
        if (!authToken) {
            console.error('No auth token available for notifications');
            return;
        }

        if (this.eventSource) {
            this.disconnect();
        }

        try {
            this.eventSource = new EventSource(`api/notifications/stream.php?token=${authToken}`);
            
            this.eventSource.onopen = () => {
                this.isConnected = true;
                this.reconnectAttempts = 0;
                this.showConnectionStatus('Conectado às notificações', 'connected');
                
                if (this.onConnectionChange) {
                    this.onConnectionChange(true);
                }
            };

            this.eventSource.onerror = (error) => {
                console.error('SSE Error:', error);
                this.isConnected = false;
                
                if (this.onConnectionChange) {
                    this.onConnectionChange(false);
                }
                
                this.showConnectionStatus('Conexão perdida', 'disconnected');
                this.attemptReconnect();
            };

            this.eventSource.addEventListener('connected', (event) => {
                const data = JSON.parse(event.data);
                console.log('Connected to notifications:', data);
            });

            this.eventSource.addEventListener('notification', (event) => {
                const notification = JSON.parse(event.data);
                this.handleNotification(notification);
            });

            this.eventSource.addEventListener('heartbeat', (event) => {
                const data = JSON.parse(event.data);
                this.updateUnreadCount(data.unread_count);
            });

            this.eventSource.addEventListener('error', (event) => {
                const data = JSON.parse(event.data);
                console.error('Server error:', data.message);
                this.showToast('Erro de Conexão', data.message, 'error');
            });

        } catch (error) {
            console.error('Failed to create EventSource:', error);
            this.attemptReconnect();
        }
    }

    /**
     * Disconnect SSE
     */
    disconnect() {
        if (this.eventSource) {
            this.eventSource.close();
            this.eventSource = null;
        }
        this.isConnected = false;
        
        if (this.onConnectionChange) {
            this.onConnectionChange(false);
        }
    }

    /**
     * Attempt to reconnect with exponential backoff
     */
    attemptReconnect() {
        if (this.reconnectAttempts >= this.maxReconnectAttempts) {
            console.error('Max reconnection attempts reached');
            this.showToast('Conexão Perdida', 'Não foi possível reconectar às notificações', 'error');
            return;
        }

        const delay = Math.pow(2, this.reconnectAttempts) * 1000; // Exponential backoff
        this.reconnectAttempts++;

        console.log(`Attempting to reconnect in ${delay}ms (attempt ${this.reconnectAttempts})`);
        
        setTimeout(() => {
            if (!this.isConnected) {
                this.connect();
            }
        }, delay);
    }

    /**
     * Handle incoming notification
     */
    handleNotification(notification) {
        console.log('New notification:', notification);
        
        this.notifications.unshift(notification);
        this.updateUnreadCount(this.unreadCount + 1);
        
        // Show toast notification
        this.showNotificationToast(notification);
        
        // Call callback if set
        if (this.onNotification) {
            this.onNotification(notification);
        }

        // Play notification sound (optional)
        this.playNotificationSound();
    }

    /**
     * Show notification toast
     */
    showNotificationToast(notification) {
        const toast = document.createElement('div');
        toast.className = `notification-toast ${this.getNotificationClass(notification.type)}`;
        
        const actions = this.getNotificationActions(notification);
        
        toast.innerHTML = `
            <div class="notification-header">
                <div class="notification-title">
                    ${this.getNotificationIcon(notification.type)}
                    ${notification.title}
                </div>
                <button class="notification-close" onclick="this.closest('.notification-toast').remove()">×</button>
            </div>
            <div class="notification-message">${notification.message}</div>
            ${actions ? `<div class="notification-actions">${actions}</div>` : ''}
            <div class="notification-time">${this.formatTimeAgo(notification.created_at)}</div>
        `;

        document.getElementById('notificationContainer').appendChild(toast);

        // Auto-remove after 5 seconds for non-critical notifications
        if (!this.isCriticalNotification(notification.type)) {
            setTimeout(() => {
                this.removeToast(toast);
            }, 5000);
        }
    }

    /**
     * Get notification CSS class based on type
     */
    getNotificationClass(type) {
        const typeClasses = {
            'new_request': 'success',
            'new_bid': 'success',
            'bid_accepted': 'success',
            'bid_rejected': 'warning',
            'trip_started': 'success',
            'trip_completed': 'success',
            'trip_cancelled': 'error'
        };
        
        return typeClasses[type] || '';
    }

    /**
     * Get notification icon
     */
    getNotificationIcon(type) {
        const icons = {
            'new_request': '<i class="fas fa-bell notification-icon"></i>',
            'new_bid': '<i class="fas fa-hand-holding-usd notification-icon"></i>',
            'bid_accepted': '<i class="fas fa-check-circle notification-icon"></i>',
            'bid_rejected': '<i class="fas fa-times-circle notification-icon"></i>',
            'trip_started': '<i class="fas fa-route notification-icon"></i>',
            'trip_completed': '<i class="fas fa-flag-checkered notification-icon"></i>',
            'trip_cancelled': '<i class="fas fa-exclamation-triangle notification-icon"></i>'
        };
        
        return icons[type] || '<i class="fas fa-info-circle notification-icon"></i>';
    }

    /**
     * Get notification action buttons
     */
    getNotificationActions(notification) {
        switch (notification.type) {
            case 'new_request':
                return `
                    <button class="notification-btn notification-btn-primary" onclick="window.location.href='driver/available-requests.html'">
                        Ver Solicitações
                    </button>
                `;
            
            case 'new_bid':
                return `
                    <button class="notification-btn notification-btn-primary" onclick="window.location.href='trip-proposals.html?trip_request_id=${notification.trip_request_id}'">
                        Ver Propostas
                    </button>
                `;
            
            case 'bid_accepted':
                return `
                    <button class="notification-btn notification-btn-primary" onclick="window.location.href='trip-tracking.html?trip_id=${notification.active_trip_id}'">
                        Ver Viagem
                    </button>
                `;
            
            default:
                return null;
        }
    }

    /**
     * Check if notification is critical (shouldn't auto-dismiss)
     */
    isCriticalNotification(type) {
        const criticalTypes = ['bid_accepted', 'trip_started', 'trip_cancelled'];
        return criticalTypes.includes(type);
    }

    /**
     * Remove toast with animation
     */
    removeToast(toast) {
        toast.classList.add('removing');
        setTimeout(() => {
            if (toast.parentNode) {
                toast.parentNode.removeChild(toast);
            }
        }, 300);
    }

    /**
     * Show general toast message
     */
    showToast(title, message, type = 'info', duration = 3000) {
        const toast = document.createElement('div');
        toast.className = `notification-toast ${type}`;
        
        toast.innerHTML = `
            <div class="notification-header">
                <div class="notification-title">${title}</div>
                <button class="notification-close" onclick="this.closest('.notification-toast').remove()">×</button>
            </div>
            <div class="notification-message">${message}</div>
        `;

        document.getElementById('notificationContainer').appendChild(toast);

        if (duration > 0) {
            setTimeout(() => {
                this.removeToast(toast);
            }, duration);
        }
    }

    /**
     * Show connection status
     */
    showConnectionStatus(message, type) {
        const status = document.getElementById('connectionStatus');
        status.textContent = message;
        status.className = `connection-status ${type} show`;
        
        setTimeout(() => {
            status.classList.remove('show');
        }, 2000);
    }

    /**
     * Update unread count
     */
    updateUnreadCount(count) {
        this.unreadCount = count;
        
        if (this.onUnreadCountChange) {
            this.onUnreadCountChange(count);
        }
        
        // Update any notification badges on the page
        const badges = document.querySelectorAll('.notification-badge');
        badges.forEach(badge => {
            if (count > 0) {
                badge.textContent = count > 99 ? '99+' : count;
                badge.style.display = 'inline-flex';
            } else {
                badge.style.display = 'none';
            }
        });
    }

    /**
     * Play notification sound
     */
    playNotificationSound() {
        try {
            // Create a subtle notification sound
            const audioContext = new (window.AudioContext || window.webkitAudioContext)();
            const oscillator = audioContext.createOscillator();
            const gainNode = audioContext.createGain();
            
            oscillator.connect(gainNode);
            gainNode.connect(audioContext.destination);
            
            oscillator.frequency.setValueAtTime(800, audioContext.currentTime);
            oscillator.frequency.setValueAtTime(600, audioContext.currentTime + 0.1);
            
            gainNode.gain.setValueAtTime(0, audioContext.currentTime);
            gainNode.gain.linearRampToValueAtTime(0.1, audioContext.currentTime + 0.01);
            gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.2);
            
            oscillator.start(audioContext.currentTime);
            oscillator.stop(audioContext.currentTime + 0.2);
        } catch (error) {
            // Silently fail if audio context is not available
        }
    }

    /**
     * Format time ago
     */
    formatTimeAgo(datetime) {
        const time = Date.now() - new Date(datetime).getTime();
        const seconds = Math.floor(time / 1000);
        
        if (seconds < 60) {
            return 'Agora';
        } else if (seconds < 3600) {
            const minutes = Math.floor(seconds / 60);
            return `${minutes} min atrás`;
        } else if (seconds < 86400) {
            const hours = Math.floor(seconds / 3600);
            return `${hours} h atrás`;
        } else {
            const days = Math.floor(seconds / 86400);
            return `${days} dia${days > 1 ? 's' : ''} atrás`;
        }
    }

    /**
     * Mark notification as read
     */
    async markAsRead(notificationId) {
        try {
            const response = await fetch('api/notifications/mark_read.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': 'Bearer ' + (localStorage.getItem('auth_token') || '')
                },
                body: JSON.stringify({
                    notification_id: notificationId
                })
            });

            const data = await response.json();
            
            if (data.success) {
                this.updateUnreadCount(Math.max(0, this.unreadCount - 1));
            }
            
            return data.success;
        } catch (error) {
            console.error('Error marking notification as read:', error);
            return false;
        }
    }

    /**
     * Mark all notifications as read
     */
    async markAllAsRead() {
        try {
            const response = await fetch('api/notifications/mark_read.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': 'Bearer ' + (localStorage.getItem('auth_token') || '')
                },
                body: JSON.stringify({
                    mark_all: true
                })
            });

            const data = await response.json();
            
            if (data.success) {
                this.updateUnreadCount(0);
            }
            
            return data.success;
        } catch (error) {
            console.error('Error marking all notifications as read:', error);
            return false;
        }
    }
}

// Global notification manager instance
window.notificationManager = new NotificationManager();