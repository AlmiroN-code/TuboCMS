import { Controller } from '@hotwired/stimulus';
import Sortable from 'sortablejs';

export default class extends Controller {
    static targets = ['list', 'modal', 'form'];
    static values = {
        playlistId: Number
    };

    connect() {
        if (this.hasListTarget) {
            this.initSortable();
        }
    }

    initSortable() {
        this.sortable = Sortable.create(this.listTarget, {
            animation: 150,
            handle: '.drag-handle',
            onEnd: (evt) => this.onReorder(evt)
        });
    }

    async onReorder(evt) {
        const items = this.listTarget.querySelectorAll('[data-video-id]');
        const videoIds = Array.from(items).map(item => parseInt(item.dataset.videoId));

        await fetch(`/playlists/${this.playlistIdValue}/reorder`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({ videoIds })
        });
    }

    openModal() {
        if (this.hasModalTarget) {
            this.modalTarget.classList.remove('hidden');
        }
    }

    closeModal() {
        if (this.hasModalTarget) {
            this.modalTarget.classList.add('hidden');
        }
    }

    async addVideo(event) {
        const videoId = event.currentTarget.dataset.videoId;
        const playlistId = event.currentTarget.dataset.playlistId;

        const response = await fetch(`/playlists/${playlistId}/videos/${videoId}`, {
            method: 'POST',
            headers: { 'Accept': 'application/json' }
        });

        if (response.ok) {
            event.currentTarget.textContent = 'Добавлено';
            event.currentTarget.disabled = true;
        }
    }

    async removeVideo(event) {
        const videoId = event.currentTarget.dataset.videoId;
        const playlistId = event.currentTarget.dataset.playlistId;

        const response = await fetch(`/playlists/${playlistId}/videos/${videoId}`, {
            method: 'DELETE',
            headers: { 'Accept': 'application/json' }
        });

        if (response.ok) {
            event.currentTarget.closest('[data-video-id]')?.remove();
        }
    }
}
