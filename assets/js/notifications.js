/**
 * Универсальная система всплывающих уведомлений (Toast Notifications)
 */

class NotificationManager {
    constructor() {
        this.container = null;
        // Инициализируем когда DOM готов
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this.init());
        } else {
            this.init();
        }
    }

    init() {
        // Проверяем, не создан ли уже контейнер
        if (document.getElementById('toast-container')) {
            this.container = document.getElementById('toast-container');
            return;
        }
        
        // Создаем контейнер для уведомлений
        this.container = document.createElement('div');
        this.container.id = 'toast-container';
        this.container.className = 'fixed top-4 right-4 z-50 flex flex-col gap-2 pointer-events-none';
        document.body.appendChild(this.container);
    }

    ensureContainer() {
        if (!this.container || !document.body.contains(this.container)) {
            this.init();
        }
    }

    /**
     * Показывает уведомление
     * @param {string} message - Текст сообщения
     * @param {string} type - Тип: success, error, warning, info
     * @param {number} duration - Длительность показа в мс (0 = бесконечно)
     */
    show(message, type = 'info', duration = 3000) {
        this.ensureContainer();
        
        const toast = this.createToast(message, type);
        this.container.appendChild(toast);

        // Анимация появления
        requestAnimationFrame(() => {
            toast.classList.remove('translate-x-full', 'opacity-0');
            toast.classList.add('translate-x-0', 'opacity-100');
        });

        // Автоматическое скрытие
        if (duration > 0) {
            setTimeout(() => this.hide(toast), duration);
        }

        return toast;
    }

    /**
     * Создает элемент уведомления
     */
    createToast(message, type) {
        const toast = document.createElement('div');
        toast.className = `
            toast-notification
            pointer-events-auto
            flex items-center gap-3
            px-4 py-3 rounded-lg shadow-lg
            transform translate-x-full opacity-0
            transition-all duration-300 ease-out
            max-w-md
            ${this.getTypeClasses(type)}
        `.trim().replace(/\s+/g, ' ');

        const icon = this.getIcon(type);
        const closeBtn = this.createCloseButton();

        toast.innerHTML = `
            <div class="flex-shrink-0">
                ${icon}
            </div>
            <div class="flex-1 text-sm font-medium">
                ${message}
            </div>
        `;
        
        toast.appendChild(closeBtn);

        // Обработчик закрытия
        closeBtn.addEventListener('click', () => this.hide(toast));

        return toast;
    }

    /**
     * Скрывает уведомление
     */
    hide(toast) {
        toast.classList.remove('translate-x-0', 'opacity-100');
        toast.classList.add('translate-x-full', 'opacity-0');

        setTimeout(() => {
            if (toast.parentNode) {
                toast.parentNode.removeChild(toast);
            }
        }, 300);
    }

    /**
     * Возвращает CSS классы для типа уведомления
     */
    getTypeClasses(type) {
        const classes = {
            success: 'bg-green-500 text-white',
            error: 'bg-red-500 text-white',
            warning: 'bg-yellow-500 text-white',
            info: 'bg-blue-500 text-white'
        };
        return classes[type] || classes.info;
    }

    /**
     * Возвращает SVG иконку для типа уведомления
     */
    getIcon(type) {
        const icons = {
            success: `
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
            `,
            error: `
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            `,
            warning: `
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                </svg>
            `,
            info: `
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            `
        };
        return icons[type] || icons.info;
    }

    /**
     * Создает кнопку закрытия
     */
    createCloseButton() {
        const btn = document.createElement('button');
        btn.className = 'flex-shrink-0 hover:opacity-75 transition-opacity';
        btn.innerHTML = `
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
        `;
        return btn;
    }

    // Удобные методы для разных типов
    success(message, duration = 3000) {
        return this.show(message, 'success', duration);
    }

    error(message, duration = 4000) {
        return this.show(message, 'error', duration);
    }

    warning(message, duration = 3500) {
        return this.show(message, 'warning', duration);
    }

    info(message, duration = 3000) {
        return this.show(message, 'info', duration);
    }
}

// Создаем глобальный экземпляр
console.log('Creating NotificationManager...');
window.notificationManager = new NotificationManager();
console.log('NotificationManager created:', window.notificationManager);

// Удобные глобальные функции
window.showNotification = (message, type = 'info', duration = 3000) => {
    console.log('showNotification called:', message, type);
    return window.notificationManager.show(message, type, duration);
};

window.showSuccess = (message, duration) => {
    console.log('showSuccess called:', message);
    return window.notificationManager.success(message, duration);
};
window.showError = (message, duration) => {
    console.log('showError called:', message);
    return window.notificationManager.error(message, duration);
};
window.showWarning = (message, duration) => window.notificationManager.warning(message, duration);
window.showInfo = (message, duration) => {
    console.log('showInfo called:', message);
    return window.notificationManager.info(message, duration);
};

console.log('Notification functions registered:', typeof window.showSuccess);

// Экспорт для использования в модулях
export default NotificationManager;
