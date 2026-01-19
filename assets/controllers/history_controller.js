import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = {
        videoId: Number,
        interval: { type: Number, default: 10000 }
    };

    connect() {
        this.currentTime = 0;
        this.startTracking();
    }

    disconnect() {
        this.stopTracking();
        this.sendProgress();
    }

    startTracking() {
        this.timer = setInterval(() => {
            this.sendProgress();
        }, this.intervalValue);
    }

    stopTracking() {
        if (this.timer) {
            clearInterval(this.timer);
        }
    }

    updateTime(event) {
        this.currentTime = Math.floor(event.target.currentTime);
    }

    async sendProgress() {
        if (this.currentTime < 10) return;

        try {
            await fetch('/history/record', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    videoId: this.videoIdValue,
                    seconds: this.currentTime
                })
            });
        } catch (e) {
            // Ignore errors
        }
    }
}
