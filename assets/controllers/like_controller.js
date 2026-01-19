import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['likeBtn', 'dislikeBtn', 'likeCount', 'dislikeCount'];
    static values = {
        videoId: Number,
        userReaction: String // 'like', 'dislike', or ''
    };

    async like() {
        await this.sendReaction('like');
    }

    async dislike() {
        await this.sendReaction('dislike');
    }

    async sendReaction(type) {
        const url = `/like/video/${this.videoIdValue}/${type}`;
        
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'text/html'
            }
        });

        if (response.ok) {
            const html = await response.text();
            this.element.outerHTML = html;
        }
    }
}
