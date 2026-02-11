/**
 * Toast Notifications System
 * Современная система уведомлений с поддержкой звуков и desktop notifications
 */

class ToastNotifications {
    constructor() {
        this.container = null;
        this.sounds = {
            success: null,
            error: null,
            warning: null,
            info: null
        };
        this.soundEnabled = localStorage.getItem('toast_sound_enabled') !== 'false';
        this.desktopEnabled = false;
        
        this.init();
    }

    init() {
        this.createContainer();
        this.loadSounds();
        this.requestDesktopPermission();
        this.processFlashMessages();
        
        // Слушаем кастомные события для показа toast
        document.addEventListener('showToast', (e) => {
            this.show(e.detail.message, e.detail.type, e.detail.duration);
        });
    }

    createContainer() {
        if (this.container) return;
        
        this.container = document.createElement('div');
        this.container.id = 'toast-container';
        this.container.className = 'fixed top-4 right-4 z-[9999] flex flex-col gap-2 pointer-events-none';
        this.container.style.maxWidth = '400px';
        document.body.appendChild(this.container);
    }

    loadSounds() {
        // Создаем аудио элементы для звуков (можно заменить на реальные файлы)
        // Используем Data URLs для простых звуковых сигналов
        this.sounds.success = this.createBeep(800, 0.1, 'sine');
        this.sounds.error = this.createBeep(400, 0.2, 'sawtooth');
        this.sounds.warning = this.createBeep(600, 0.15, 'triangle');
        this.sounds.info = this.createBeep(700, 0.1, 'sine');
    }

    createBeep(frequency, duration, type = 'sine') {
        // Создаем простой звуковой сигнал используя Web Audio API
        return () => {
            if (!this.soundEnabled) return;
            
            try {
                const audioContext = new (window.AudioContext || window.webkitAudioContext)();
                const oscillator = audioContext.createOscillator();
                const gainNode = audioContext.createGain();
                
                oscillator.connect(gainNode);
                gainNode.connect(audioContext.destination);
                
                oscillator.frequency.value = frequency;
                oscillator.type = type;
                
                gainNode.gain.setValueAtTime(0.3, audioContext.currentTime);
                gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + duration);
                
                oscillator.start(audioContext.currentTime);
                oscillator.stop(audioContext.currentTime + duration);
            } catch (e) {
                console.warn('Audio not supported:', e);
            }
        };
    }

    async requestDesktopPermission() {
        if (!('Notification' in window)) {
            console.log('Desktop notifications not supported');
            return;
        }

        if (Notification.permission === 'granted') {
            this.desktopEnabled = true;
        } else if (Notification.permission !== 'denied') {
            const permission = await Notification.requestPermission();
            this.desktopEnabled = permission === 'granted';
        }
    }

    processFlashMessages() {
        // Обрабатываем существующие flash messages и конвертируем их в toast
        const flashContainers = document.querySelectorAll('[data-flash-message]');
        
        flashContainers.forEach(container => {
            const type = container.dataset.flashType || 'info';
            const message = container.textContent.trim();
            
            if (message) {
                this.show(message, type);
                container.remove();
            }
        });
    }

    show(message, type = 'info', duration = 5000) {
        const toast = this.createToast(message, type);
        this.container.appendChild(toast);
        
        // Анимация появления
        requestAnimationFrame(() => {
            toast.style.transform = 'translateX(0)';
            toast.style.opacity = '1';
        });
        
        // Воспроизводим звук
        if (this.sounds[type]) {
            this.sounds[type]();
        }
        
        // Показываем desktop notification
        this.showDesktopNotification(message, type);
        
        // Автоматическое скрытие
        if (duration > 0) {
            setTimeout(() => {
                this.hide(toast);
            }, duration);
        }
        
        return toast;
    }

    createToast(message, type) {
        const toast = document.createElement('div');
        toast.className = `toast-notification pointer-events-auto transform translate-x-full opacity-0 transition-all duration-300 ease-out`;
        
        const colors = {
            success: 'bg-green-50 dark:bg-green-900 border-green-200 dark:border-green-700 text-green-800 dark:text-green-200',
            error: 'bg-red-50 dark:bg-red-900 border-red-200 dark:border-red-700 text-red-800 dark:text-red-200',
            warning: 'bg-yellow-50 dark:bg-yellow-900 border-yellow-200 dark:border-yellow-700 text-yellow-800 dark:text-yellow-200',
            info: 'bg-blue-50 dark:bg-blue-900 border-blue-200 dark:border-blue-700 text-blue-800 dark:text-blue-200'
        };
        
        const icons = {
            success: `<svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>`,
            error: `<svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path></svg>`,
            warning: `<svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path></svg>`,
            info: `<svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path></svg>`
        };
        
        toast.innerHTML = `
            <div class="flex items-start gap-3 p-4 rounded-lg border shadow-lg ${colors[type] || colors.info}">
                <div class="flex-shrink-0">
                    ${icons[type] || icons.info}
                </div>
                <div class="flex-1 text-sm font-medium">
                    ${this.escapeHtml(message)}
                </div>
                <button type="button" class="flex-shrink-0 ml-2 hover:opacity-70 transition-opacity" onclick="this.closest('.toast-notification').remove()">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                    </svg>
                </button>
            </div>
        `;
        
        return toast;
    }

    hide(toast) {
        toast.style.transform = 'translateX(100%)';
        toast.style.opacity = '0';
        
        setTimeout(() => {
            if (toast.parentNode) {
                toast.remove();
            }
        }, 300);
    }

    showDesktopNotification(message, type) {
        if (!this.desktopEnabled || !('Notification' in window)) {
            return;
        }

        const titles = {
            success: '✓ Успешно',
            error: '✗ Ошибка',
            warning: '⚠ Предупреждение',
            info: 'ℹ Информация'
        };

        try {
            new Notification(titles[type] || titles.info, {
                body: message,
                icon: '/favicon.ico',
                badge: '/favicon.ico',
                tag: 'toast-notification',
                renotify: false
            });
        } catch (e) {
            console.warn('Desktop notification failed:', e);
        }
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    toggleSound(enabled) {
        this.soundEnabled = enabled;
        localStorage.setItem('toast_sound_enabled', enabled);
    }

    isSoundEnabled() {
        return this.soundEnabled;
    }
}

// Глобальные функции для удобного использования
window.showToast = function(message, type = 'info', duration = 5000) {
    if (!window.toastNotifications) {
        window.toastNotifications = new ToastNotifications();
    }
    return window.toastNotifications.show(message, type, duration);
};

window.showSuccess = function(message, duration = 5000) {
    return window.showToast(message, 'success', duration);
};

window.showError = function(message, duration = 7000) {
    return window.showToast(message, 'error', duration);
};

window.showWarning = function(message, duration = 6000) {
    return window.showToast(message, 'warning', duration);
};

window.showInfo = function(message, duration = 5000) {
    return window.showToast(message, 'info', duration);
};

// Инициализация
function initToastNotifications() {
    if (!window.toastNotifications) {
        window.toastNotifications = new ToastNotifications();
    }
}

// Инициализируем при загрузке DOM
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initToastNotifications);
} else {
    initToastNotifications();
}

// Также инициализируем при загрузке Turbo
document.addEventListener('turbo:load', initToastNotifications);

export default ToastNotifications;
