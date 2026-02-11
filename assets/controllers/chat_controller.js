import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['messages', 'input', 'form'];
    static values = {
        roomId: String,
        pollInterval: { type: Number, default: 3000 }
    };

    connect() {
        this.lastMessageId = 0;
        this.isPolling = false;
        this.startPolling();
        this.loadRecentMessages();
    }

    disconnect() {
        this.stopPolling();
    }

    async loadRecentMessages() {
        try {
            const response = await fetch(`/api/chat/rooms/${this.roomIdValue}/recent?limit=50`);
            const data = await response.json();
            
            if (data.messages && data.messages.length > 0) {
                this.messagesTarget.innerHTML = '';
                data.messages.forEach(msg => this.appendMessage(msg));
                this.lastMessageId = Math.max(...data.messages.map(m => m.id));
                this.scrollToBottom();
            }
        } catch (error) {
            console.error('Failed to load messages:', error);
        }
    }

    startPolling() {
        if (this.isPolling) return;
        this.isPolling = true;
        this.pollTimer = setInterval(() => this.pollNewMessages(), this.pollIntervalValue);
    }

    stopPolling() {
        if (this.pollTimer) {
            clearInterval(this.pollTimer);
            this.pollTimer = null;
        }
        this.isPolling = false;
    }

    async pollNewMessages() {
        try {
            const response = await fetch(`/api/chat/rooms/${this.roomIdValue}/messages?limit=10&offset=0`);
            const data = await response.json();
            
            if (data.messages && data.messages.length > 0) {
                const newMessages = data.messages.filter(msg => msg.id > this.lastMessageId);
                newMessages.reverse().forEach(msg => {
                    this.appendMessage(msg);
                    this.lastMessageId = Math.max(this.lastMessageId, msg.id);
                });
                
                if (newMessages.length > 0) {
                    this.scrollToBottom();
                }
            }
        } catch (error) {
            console.error('Failed to poll messages:', error);
        }
    }

    async submit(event) {
        event.preventDefault();
        
        const message = this.inputTarget.value.trim();
        if (!message) return;

        try {
            const response = await fetch(`/api/chat/rooms/${this.roomIdValue}/messages`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ message })
            });

            if (response.ok) {
                const data = await response.json();
                this.inputTarget.value = '';
                this.appendMessage(data.message);
                this.lastMessageId = Math.max(this.lastMessageId, data.message.id);
                this.scrollToBottom();
            } else {
                const error = await response.json();
                alert(error.error || 'Failed to send message');
            }
        } catch (error) {
            console.error('Failed to send message:', error);
            alert('Failed to send message');
        }
    }

    appendMessage(message) {
        const messageEl = document.createElement('div');
        messageEl.className = 'flex gap-3 p-3 hover:bg-gray-50 rounded';
        messageEl.dataset.messageId = message.id;
        
        const time = new Date(message.createdAt).toLocaleTimeString('ru-RU', { 
            hour: '2-digit', 
            minute: '2-digit' 
        });

        messageEl.innerHTML = `
            <div class="flex-shrink-0">
                ${message.user.avatar 
                    ? `<img src="/media/avatars/${message.user.avatar}" alt="${message.user.username}" class="w-10 h-10 rounded-full object-cover">`
                    : `<div class="w-10 h-10 rounded-full bg-gray-300 flex items-center justify-center text-gray-600 font-semibold">${message.user.username.charAt(0).toUpperCase()}</div>`
                }
            </div>
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 mb-1">
                    <span class="font-semibold text-sm text-gray-900">${this.escapeHtml(message.user.username)}</span>
                    ${message.user.isVerified ? '<svg class="w-4 h-4 text-blue-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>' : ''}
                    <span class="text-xs text-gray-500">${time}</span>
                </div>
                <p class="text-sm text-gray-700 break-words">${this.escapeHtml(message.message)}</p>
            </div>
        `;

        this.messagesTarget.appendChild(messageEl);
    }

    scrollToBottom() {
        this.messagesTarget.scrollTop = this.messagesTarget.scrollHeight;
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}
