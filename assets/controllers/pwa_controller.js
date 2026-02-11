import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = {
        vapidPublicKey: String
    }

    connect() {
        this.registerServiceWorker();
        this.checkNotificationPermission();
    }

    async registerServiceWorker() {
        if (!('serviceWorker' in navigator)) {
            console.log('Service Worker not supported');
            return;
        }

        try {
            const registration = await navigator.serviceWorker.register('/sw.js', {
                scope: '/'
            });

            console.log('Service Worker registered:', registration);

            // Check for updates
            registration.addEventListener('updatefound', () => {
                const newWorker = registration.installing;
                console.log('Service Worker update found');

                newWorker.addEventListener('statechange', () => {
                    if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                        // New service worker available
                        this.showUpdateNotification();
                    }
                });
            });

            // Store registration for push notifications
            this.registration = registration;

        } catch (error) {
            console.error('Service Worker registration failed:', error);
        }
    }

    showUpdateNotification() {
        if (confirm('Доступна новая версия приложения. Обновить?')) {
            window.location.reload();
        }
    }

    checkNotificationPermission() {
        if (!('Notification' in window)) {
            console.log('Notifications not supported');
            return;
        }

        if (Notification.permission === 'granted') {
            this.element.querySelector('[data-pwa-target="notificationBtn"]')?.classList.add('hidden');
        }
    }

    async requestNotificationPermission() {
        if (!('Notification' in window)) {
            alert('Ваш браузер не поддерживает уведомления');
            return;
        }

        try {
            const permission = await Notification.requestPermission();
            
            if (permission === 'granted') {
                console.log('Notification permission granted');
                this.subscribeToPushNotifications();
                this.element.querySelector('[data-pwa-target="notificationBtn"]')?.classList.add('hidden');
            } else {
                console.log('Notification permission denied');
            }
        } catch (error) {
            console.error('Error requesting notification permission:', error);
        }
    }

    async subscribeToPushNotifications() {
        if (!this.registration || !this.hasVapidPublicKeyValue) {
            console.log('Cannot subscribe: missing registration or VAPID key');
            return;
        }

        try {
            const subscription = await this.registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: this.urlBase64ToUint8Array(this.vapidPublicKeyValue)
            });

            // Send subscription to server
            await fetch('/api/push/subscribe', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(subscription)
            });

            console.log('Push subscription successful');
        } catch (error) {
            console.error('Push subscription failed:', error);
        }
    }

    urlBase64ToUint8Array(base64String) {
        const padding = '='.repeat((4 - base64String.length % 4) % 4);
        const base64 = (base64String + padding)
            .replace(/\-/g, '+')
            .replace(/_/g, '/');

        const rawData = window.atob(base64);
        const outputArray = new Uint8Array(rawData.length);

        for (let i = 0; i < rawData.length; ++i) {
            outputArray[i] = rawData.charCodeAt(i);
        }
        return outputArray;
    }

    async installApp() {
        if (!this.deferredPrompt) {
            alert('Установка недоступна');
            return;
        }

        this.deferredPrompt.prompt();
        const { outcome } = await this.deferredPrompt.userChoice;
        
        console.log(`User response to install prompt: ${outcome}`);
        this.deferredPrompt = null;
    }

    // Store install prompt
    initialize() {
        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault();
            this.deferredPrompt = e;
            this.element.querySelector('[data-pwa-target="installBtn"]')?.classList.remove('hidden');
        });

        window.addEventListener('appinstalled', () => {
            console.log('PWA installed');
            this.deferredPrompt = null;
            this.element.querySelector('[data-pwa-target="installBtn"]')?.classList.add('hidden');
        });
    }
}
