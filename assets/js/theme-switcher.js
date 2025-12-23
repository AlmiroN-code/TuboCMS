/**
 * Theme Switcher
 * Управление темной и светлой темой
 */

class ThemeSwitcher {
    constructor() {
        this.init();
    }

    init() {
        // Получаем сохраненную тему или используем системную
        this.currentTheme = this.getSavedTheme() || this.getSystemTheme();
        
        // Применяем тему при загрузке
        this.applyTheme(this.currentTheme);
        
        // Слушаем изменения системной темы
        this.watchSystemTheme();
        
        // Инициализируем переключатели
        this.initSwitchers();
    }

    getSavedTheme() {
        return localStorage.getItem('theme');
    }

    getSystemTheme() {
        return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    }

    saveTheme(theme) {
        localStorage.setItem('theme', theme);
    }

    applyTheme(theme) {
        const html = document.documentElement;
        
        if (theme === 'dark') {
            html.classList.add('dark');
        } else {
            html.classList.remove('dark');
        }
        
        this.currentTheme = theme;
        this.updateSwitchers();
        
        // Диспатчим событие для других компонентов
        window.dispatchEvent(new CustomEvent('themeChanged', { 
            detail: { theme } 
        }));
    }

    toggleTheme() {
        const newTheme = this.currentTheme === 'dark' ? 'light' : 'dark';
        this.setTheme(newTheme);
    }

    setTheme(theme) {
        this.saveTheme(theme);
        this.applyTheme(theme);
    }

    watchSystemTheme() {
        const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
        
        mediaQuery.addEventListener('change', (e) => {
            // Только если пользователь не выбрал тему вручную
            if (!this.getSavedTheme()) {
                this.applyTheme(e.matches ? 'dark' : 'light');
            }
        });
    }

    initSwitchers() {
        // Находим все переключатели тем
        const switchers = document.querySelectorAll('[data-theme-toggle]');
        
        switchers.forEach(switcher => {
            switcher.addEventListener('click', (e) => {
                e.preventDefault();
                this.toggleTheme();
            });
        });

        // Обновляем состояние переключателей
        this.updateSwitchers();
    }

    updateSwitchers() {
        // Используем setTimeout чтобы убедиться что DOM обновился
        setTimeout(() => {
            const switchers = document.querySelectorAll('[data-theme-toggle]');
            
            switchers.forEach(switcher => {
                const lightIcon = switcher.querySelector('[data-theme-icon="light"]');
                const darkIcon = switcher.querySelector('[data-theme-icon="dark"]');
                
                if (lightIcon && darkIcon) {
                    // В темной теме показываем иконку солнца (переключить на светлую)
                    // В светлой теме показываем иконку луны (переключить на темную)
                    if (this.currentTheme === 'dark') {
                        lightIcon.style.display = 'block';
                        darkIcon.style.display = 'none';
                        console.log('Dark theme: showing sun icon');
                    } else {
                        lightIcon.style.display = 'none';
                        darkIcon.style.display = 'block';
                        console.log('Light theme: showing moon icon');
                    }
                }
                
                // Обновляем aria-label для доступности
                switcher.setAttribute('aria-label', 
                    this.currentTheme === 'dark' 
                        ? 'Переключить на светлую тему' 
                        : 'Переключить на темную тему'
                );
            });
            
            console.log('Theme switchers updated, current theme:', this.currentTheme);
        }, 50);
    }

    getCurrentTheme() {
        return this.currentTheme;
    }

    setAuto() {
        // Удаляем сохраненную тему, чтобы использовать системную
        localStorage.removeItem('theme');
        const systemTheme = this.getSystemTheme();
        this.applyTheme(systemTheme);
    }
}

// Инициализируем переключатель тем
function initThemeSwitcher() {
    if (!window.themeSwitcher) {
        window.themeSwitcher = new ThemeSwitcher();
        console.log('Theme switcher initialized');
    }
}

// Инициализируем при загрузке DOM
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initThemeSwitcher);
} else {
    // DOM уже загружен
    initThemeSwitcher();
}

// Также инициализируем при загрузке Turbo (для SPA навигации)
document.addEventListener('turbo:load', () => {
    console.log('Turbo load event - reinitializing theme switcher');
    initThemeSwitcher();
});

// Дополнительная инициализация при рендере Turbo
document.addEventListener('turbo:render', () => {
    console.log('Turbo render event - updating theme switchers');
    if (window.themeSwitcher) {
        window.themeSwitcher.initSwitchers();
    }
});

// Экспортируем для использования в других модулях
export default ThemeSwitcher;