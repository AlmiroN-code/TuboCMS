import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['message'];
    static values = {
        url: String
    };

    copyLink(event) {
        event.preventDefault();
        const button = event.currentTarget;
        const url = button.dataset.shareUrlValue || this.urlValue || window.location.href;
        
        navigator.clipboard.writeText(url).then(() => {
            this.showCopiedFeedback(event.currentTarget);
        }).catch(() => {
            // Fallback for older browsers
            this.fallbackCopy(url);
            this.showCopiedFeedback(event.currentTarget);
        });
    }

    fallbackCopy(text) {
        const textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.style.position = 'fixed';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
    }

    showCopiedFeedback(button) {
        const messageTarget = button.querySelector('[data-share-target="message"]');
        const originalText = messageTarget ? messageTarget.textContent : null;
        
        if (messageTarget) {
            messageTarget.textContent = '✓';
        }
        button.classList.add('text-green-500');
        
        setTimeout(() => {
            if (messageTarget && originalText) {
                messageTarget.textContent = originalText;
            }
            button.classList.remove('text-green-500');
        }, 2000);
    }

    openShare(event) {
        const network = event.currentTarget.dataset.network;
        const url = event.currentTarget.dataset.url;
        
        if (url) {
            window.open(url, '_blank', 'width=600,height=400');
        }
    }
}
