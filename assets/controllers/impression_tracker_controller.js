import { Controller } from '@hotwired/stimulus';

/**
 * Stimulus контроллер для отслеживания показов видео карточек
 * Использует Intersection Observer для определения когда карточка видна
 */
export default class extends Controller {
    static values = {
        videoId: Number,
        tracked: { type: Boolean, default: false }
    }

    connect() {
        if (this.trackedValue) return;
        
        // Создаём observer для отслеживания видимости
        this.observer = new IntersectionObserver(
            (entries) => this.handleIntersection(entries),
            { threshold: 0.5 } // 50% карточки должно быть видно
        );
        
        this.observer.observe(this.element);
    }

    disconnect() {
        if (this.observer) {
            this.observer.disconnect();
        }
    }

    handleIntersection(entries) {
        entries.forEach(entry => {
            if (entry.isIntersecting && !this.trackedValue) {
                this.trackImpression();
            }
        });
    }

    trackImpression() {
        this.trackedValue = true;
        this.observer.disconnect();
        
        // Добавляем в очередь для batch отправки
        window.impressionQueue = window.impressionQueue || [];
        window.impressionQueue.push(this.videoIdValue);
        
        // Debounce отправки - ждём 500ms для сбора всех видимых карточек
        clearTimeout(window.impressionTimeout);
        window.impressionTimeout = setTimeout(() => this.sendImpressions(), 500);
    }

    sendImpressions() {
        const videoIds = window.impressionQueue || [];
        if (videoIds.length === 0) return;
        
        window.impressionQueue = [];
        
        fetch('/videos/track-impressions', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ video_ids: videoIds })
        }).catch(err => console.error('Failed to track impressions:', err));
    }
}
