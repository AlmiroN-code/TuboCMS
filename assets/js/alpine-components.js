// Alpine.js компоненты для использования в проекте

// Компонент для модальных окон
export function modal() {
    return {
        show: false,
        open() {
            this.show = true;
            document.body.style.overflow = 'hidden';
        },
        close() {
            this.show = false;
            document.body.style.overflow = '';
        }
    };
}

// Компонент для dropdown меню
export function dropdown() {
    return {
        open: false,
        toggle() {
            this.open = !this.open;
        },
        close() {
            this.open = false;
        }
    };
}

// Компонент для tabs
export function tabs(defaultTab = 0) {
    return {
        activeTab: defaultTab,
        setTab(index) {
            this.activeTab = index;
        },
        isActive(index) {
            return this.activeTab === index;
        }
    };
}

// Компонент для accordion
export function accordion() {
    return {
        openItems: [],
        toggle(id) {
            const index = this.openItems.indexOf(id);
            if (index > -1) {
                this.openItems.splice(index, 1);
            } else {
                this.openItems.push(id);
            }
        },
        isOpen(id) {
            return this.openItems.includes(id);
        }
    };
}

// Компонент для копирования в буфер обмена
export function clipboard() {
    return {
        copied: false,
        async copy(text) {
            try {
                await navigator.clipboard.writeText(text);
                this.copied = true;
                setTimeout(() => {
                    this.copied = false;
                }, 2000);
            } catch (err) {
                console.error('Failed to copy:', err);
            }
        }
    };
}

// Компонент для показа/скрытия пароля
export function passwordToggle() {
    return {
        show: false,
        toggle() {
            this.show = !this.show;
        }
    };
}

// Компонент для фильтров
export function filters(initialFilters = {}) {
    return {
        filters: initialFilters,
        updateFilter(key, value) {
            this.filters[key] = value;
        },
        clearFilters() {
            Object.keys(this.filters).forEach(key => {
                this.filters[key] = '';
            });
        },
        hasActiveFilters() {
            return Object.values(this.filters).some(value => value !== '' && value !== null);
        }
    };
}

// Компонент для массового выбора
export function bulkSelect() {
    return {
        selected: [],
        selectAll(items) {
            this.selected = items.map(item => item.id || item);
        },
        deselectAll() {
            this.selected = [];
        },
        toggle(id) {
            const index = this.selected.indexOf(id);
            if (index > -1) {
                this.selected.splice(index, 1);
            } else {
                this.selected.push(id);
            }
        },
        isSelected(id) {
            return this.selected.includes(id);
        },
        get count() {
            return this.selected.length;
        },
        get hasSelection() {
            return this.selected.length > 0;
        }
    };
}

// Компонент для toast уведомлений
export function toast() {
    return {
        messages: [],
        show(message, type = 'info', duration = 3000) {
            const id = Date.now();
            this.messages.push({ id, message, type });
            
            if (duration > 0) {
                setTimeout(() => {
                    this.remove(id);
                }, duration);
            }
        },
        remove(id) {
            const index = this.messages.findIndex(m => m.id === id);
            if (index > -1) {
                this.messages.splice(index, 1);
            }
        },
        success(message, duration = 3000) {
            this.show(message, 'success', duration);
        },
        error(message, duration = 5000) {
            this.show(message, 'error', duration);
        },
        warning(message, duration = 4000) {
            this.show(message, 'warning', duration);
        },
        info(message, duration = 3000) {
            this.show(message, 'info', duration);
        }
    };
}

// Регистрация глобальных компонентов
if (window.Alpine) {
    // Магические свойства для удобного использования
    window.Alpine.magic('modal', () => modal);
    window.Alpine.magic('dropdown', () => dropdown);
    window.Alpine.magic('tabs', () => tabs);
    window.Alpine.magic('accordion', () => accordion);
    window.Alpine.magic('clipboard', () => clipboard);
    window.Alpine.magic('passwordToggle', () => passwordToggle);
    window.Alpine.magic('filters', () => filters);
    window.Alpine.magic('bulkSelect', () => bulkSelect);
    window.Alpine.magic('toast', () => toast);
}
