import { Controller } from "@hotwired/stimulus"

// Connects to data-controller="theme"
export default class extends Controller {
    static targets = ["toggle", "lightIcon", "darkIcon"]
    static values = { 
        current: String,
        auto: Boolean 
    }

    connect() {
        // Слушаем изменения темы
        window.addEventListener('themeChanged', this.handleThemeChange.bind(this))
        
        // Обновляем состояние при подключении
        this.updateState()
    }

    disconnect() {
        window.removeEventListener('themeChanged', this.handleThemeChange.bind(this))
    }

    toggle() {
        if (window.themeSwitcher) {
            window.themeSwitcher.toggleTheme()
        }
    }

    setLight() {
        if (window.themeSwitcher) {
            window.themeSwitcher.setTheme('light')
        }
    }

    setDark() {
        if (window.themeSwitcher) {
            window.themeSwitcher.setTheme('dark')
        }
    }

    setAuto() {
        if (window.themeSwitcher) {
            localStorage.removeItem('theme')
            const systemTheme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'
            window.themeSwitcher.applyTheme(systemTheme)
        }
    }

    handleThemeChange(event) {
        this.currentValue = event.detail.theme
        this.updateState()
    }

    updateState() {
        const isDark = this.currentValue === 'dark'
        
        // Обновляем иконки
        if (this.hasLightIconTarget && this.hasDarkIconTarget) {
            this.lightIconTarget.style.display = isDark ? 'block' : 'none'
            this.darkIconTarget.style.display = isDark ? 'none' : 'block'
        }
        
        // Обновляем aria-label
        if (this.hasToggleTarget) {
            this.toggleTarget.setAttribute('aria-label', 
                isDark ? 'Переключить на светлую тему' : 'Переключить на темную тему'
            )
        }
    }
}