import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['form', 'duration', 'sort'];
    static values = {
        url: String
    };

    connect() {
        // Initialize from URL params
        const params = new URLSearchParams(window.location.search);
        
        if (this.hasDurationTarget) {
            this.durationTarget.value = params.get('duration') || '';
        }
        if (this.hasSortTarget) {
            this.sortTarget.value = params.get('sort') || 'newest';
        }
    }

    filter(event) {
        const params = new URLSearchParams(window.location.search);
        
        // Update params from form
        if (this.hasDurationTarget) {
            const duration = this.durationTarget.value;
            if (duration) {
                params.set('duration', duration);
            } else {
                params.delete('duration');
            }
        }
        
        if (this.hasSortTarget) {
            const sort = this.sortTarget.value;
            if (sort && sort !== 'newest') {
                params.set('sort', sort);
            } else {
                params.delete('sort');
            }
        }

        // Reset to page 1 when filtering
        params.delete('page');

        // Navigate to filtered URL
        const newUrl = `${window.location.pathname}?${params.toString()}`;
        window.location.href = newUrl;
    }

    reset() {
        window.location.href = window.location.pathname;
    }
}
