import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = [
        'video', 'container', 'controls', 'playBtn', 'playIcon', 'pauseIcon',
        'progress', 'progressBar', 'progressBuffered', 'progressHover',
        'currentTime', 'duration', 'volume', 'volumeSlider', 'volumeIcon',
        'qualityBtn', 'qualityMenu', 'qualityList', 'currentQuality',
        'speedBtn', 'speedMenu', 'fullscreenBtn', 'pipBtn', 'settingsBtn',
        'settingsMenu', 'loader', 'bigPlayBtn', 'tooltip', 'chaptersList',
        'chapterItem'
    ];

    static values = {
        sources: Array,
        poster: String,
        autoplay: Boolean,
        videoId: Number
    };

    connect() {
        console.log('[VideoPlayer] Controller connected');
        console.log('[VideoPlayer] Sources:', this.sourcesValue);
        console.log('[VideoPlayer] Video target:', this.hasVideoTarget);
        
        this.isPlaying = false;
        this.isMuted = false;
        this.isFullscreen = false;
        this.currentQualityIndex = 0;
        this.currentSpeed = 1;
        this.hideControlsTimeout = null;
        this.lastRecordedTime = 0;
        this.recordInterval = 30;
        this.chapters = [];
        this.currentChapterIndex = -1;

        this.initPlayer();
        this.bindEvents();
        this.loadSavedPreferences();
        this.loadChapters();
        
        console.log('[VideoPlayer] Initialization complete');
    }

    initPlayer() {
        // Set initial source
        if (this.sourcesValue.length > 0) {
            // Find primary source or use first
            const primaryIndex = this.sourcesValue.findIndex(s => s.primary);
            this.currentQualityIndex = primaryIndex >= 0 ? primaryIndex : 0;
            this.setSource(this.currentQualityIndex);
            this.updateQualityMenu();
        }

        // Update duration when metadata loaded
        this.videoTarget.addEventListener('loadedmetadata', () => {
            this.durationTarget.textContent = this.formatTime(this.videoTarget.duration);
            // Render chapter markers after duration is known
            if (this.chapters.length > 0) {
                this.renderChapterMarkers();
            }
        });
    }

    bindEvents() {
        // Video events
        this.videoTarget.addEventListener('play', () => this.onPlay());
        this.videoTarget.addEventListener('pause', () => this.onPause());
        this.videoTarget.addEventListener('timeupdate', () => this.onTimeUpdate());
        this.videoTarget.addEventListener('progress', () => this.onProgress());
        this.videoTarget.addEventListener('waiting', () => this.showLoader());
        this.videoTarget.addEventListener('canplay', () => this.hideLoader());
        this.videoTarget.addEventListener('ended', () => this.onEnded());
        this.videoTarget.addEventListener('volumechange', () => this.onVolumeChange());

        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => this.handleKeyboard(e));

        // Fullscreen change
        document.addEventListener('fullscreenchange', () => this.onFullscreenChange());

        // Hide controls on mouse idle
        this.containerTarget.addEventListener('mousemove', () => this.showControls());
        this.containerTarget.addEventListener('mouseleave', () => this.scheduleHideControls());

        // Progress bar hover
        this.progressTarget.addEventListener('mousemove', (e) => this.onProgressHover(e));
        this.progressTarget.addEventListener('mouseleave', () => this.hideProgressTooltip());
    }

    loadSavedPreferences() {
        // Load saved volume
        const savedVolume = localStorage.getItem('playerVolume');
        if (savedVolume !== null) {
            this.videoTarget.volume = parseFloat(savedVolume);
            this.volumeSliderTarget.value = savedVolume * 100;
        }

        // Load saved quality preference
        const savedQuality = localStorage.getItem('playerQuality');
        if (savedQuality) {
            const index = this.sourcesValue.findIndex(s => s.quality === savedQuality);
            if (index >= 0) {
                this.currentQualityIndex = index;
                this.setSource(index);
            }
        }

        // Load saved speed
        const savedSpeed = localStorage.getItem('playerSpeed');
        if (savedSpeed) {
            this.currentSpeed = parseFloat(savedSpeed);
            this.videoTarget.playbackRate = this.currentSpeed;
        }
    }

    // Playback controls
    togglePlay() {
        console.log('[VideoPlayer] togglePlay called, paused:', this.videoTarget.paused);
        if (this.videoTarget.paused) {
            this.videoTarget.play();
        } else {
            this.videoTarget.pause();
        }
    }

    onPlay() {
        this.isPlaying = true;
        this.playIconTarget.classList.add('hidden');
        this.pauseIconTarget.classList.remove('hidden');
        this.bigPlayBtnTarget.classList.add('hidden');
        this.scheduleHideControls();
    }

    onPause() {
        this.isPlaying = false;
        this.playIconTarget.classList.remove('hidden');
        this.pauseIconTarget.classList.add('hidden');
        this.bigPlayBtnTarget.classList.remove('hidden');
        this.showControls();
    }

    onEnded() {
        this.bigPlayBtnTarget.classList.remove('hidden');
        this.recordWatchHistory();
    }

    // Time & Progress
    onTimeUpdate() {
        const current = this.videoTarget.currentTime;
        const duration = this.videoTarget.duration;
        
        this.currentTimeTarget.textContent = this.formatTime(current);
        
        const percent = (current / duration) * 100;
        this.progressBarTarget.style.width = `${percent}%`;

        // Update current chapter
        this.updateCurrentChapter();

        // Record watch history
        const currentSec = Math.floor(current);
        if (currentSec > 0 && (currentSec - this.lastRecordedTime >= this.recordInterval)) {
            this.recordWatchHistory();
            this.lastRecordedTime = currentSec;
        }
    }

    onProgress() {
        if (this.videoTarget.buffered.length > 0) {
            const buffered = this.videoTarget.buffered.end(this.videoTarget.buffered.length - 1);
            const duration = this.videoTarget.duration;
            const percent = (buffered / duration) * 100;
            this.progressBufferedTarget.style.width = `${percent}%`;
        }
    }

    seek(e) {
        const rect = this.progressTarget.getBoundingClientRect();
        const percent = (e.clientX - rect.left) / rect.width;
        this.videoTarget.currentTime = percent * this.videoTarget.duration;
    }

    onProgressHover(e) {
        const rect = this.progressTarget.getBoundingClientRect();
        const percent = (e.clientX - rect.left) / rect.width;
        const time = percent * this.videoTarget.duration;
        
        this.progressHoverTarget.style.width = `${percent * 100}%`;
        this.tooltipTarget.textContent = this.formatTime(time);
        this.tooltipTarget.style.left = `${e.clientX - rect.left}px`;
        this.tooltipTarget.classList.remove('hidden');
    }

    hideProgressTooltip() {
        this.tooltipTarget.classList.add('hidden');
        this.progressHoverTarget.style.width = '0%';
    }

    skip(seconds) {
        this.videoTarget.currentTime = Math.max(0, Math.min(
            this.videoTarget.currentTime + seconds,
            this.videoTarget.duration
        ));
    }

    skipBackward() {
        this.skip(-10);
    }

    skipForward() {
        this.skip(10);
    }

    // Volume
    toggleMute() {
        this.videoTarget.muted = !this.videoTarget.muted;
    }

    setVolume(e) {
        const volume = e.target.value / 100;
        this.videoTarget.volume = volume;
        this.videoTarget.muted = volume === 0;
        localStorage.setItem('playerVolume', volume);
    }

    onVolumeChange() {
        const volume = this.videoTarget.muted ? 0 : this.videoTarget.volume;
        this.volumeSliderTarget.value = volume * 100;
        this.updateVolumeIcon(volume);
    }

    updateVolumeIcon(volume) {
        const icon = this.volumeIconTarget;
        if (volume === 0 || this.videoTarget.muted) {
            icon.innerHTML = this.getMuteIcon();
        } else if (volume < 0.5) {
            icon.innerHTML = this.getVolumeLowIcon();
        } else {
            icon.innerHTML = this.getVolumeHighIcon();
        }
    }

    // Quality
    toggleQualityMenu() {
        this.qualityMenuTarget.classList.toggle('hidden');
        this.speedMenuTarget.classList.add('hidden');
        this.settingsMenuTarget.classList.add('hidden');
    }

    setQuality(e) {
        const index = parseInt(e.currentTarget.dataset.index);
        if (index === this.currentQualityIndex) return;

        const currentTime = this.videoTarget.currentTime;
        const wasPlaying = !this.videoTarget.paused;

        this.currentQualityIndex = index;
        this.setSource(index);

        this.videoTarget.addEventListener('loadedmetadata', () => {
            this.videoTarget.currentTime = currentTime;
            if (wasPlaying) this.videoTarget.play();
        }, { once: true });

        // Save preference
        localStorage.setItem('playerQuality', this.sourcesValue[index].quality);
        
        this.updateQualityMenu();
        this.qualityMenuTarget.classList.add('hidden');
    }

    setSource(index) {
        const source = this.sourcesValue[index];
        this.videoTarget.src = source.url;
        this.currentQualityTarget.textContent = source.quality;
    }

    updateQualityMenu() {
        this.qualityListTarget.innerHTML = this.sourcesValue.map((source, index) => `
            <button 
                data-action="click->video-player#setQuality"
                data-index="${index}"
                class="w-full px-4 py-2 text-left text-sm hover:bg-gray-700 flex items-center justify-between ${index === this.currentQualityIndex ? 'text-primary-400' : 'text-white'}">
                <span>${source.quality}</span>
                ${source.resolution ? `<span class="text-gray-400 text-xs">${source.resolution}</span>` : ''}
            </button>
        `).join('');
    }

    // Speed
    toggleSpeedMenu() {
        this.speedMenuTarget.classList.toggle('hidden');
        this.qualityMenuTarget.classList.add('hidden');
        this.settingsMenuTarget.classList.add('hidden');
    }

    setSpeed(e) {
        const speed = parseFloat(e.currentTarget.dataset.speed);
        this.currentSpeed = speed;
        this.videoTarget.playbackRate = speed;
        localStorage.setItem('playerSpeed', speed);
        
        this.speedBtnTarget.querySelector('span').textContent = speed === 1 ? '1x' : `${speed}x`;
        this.speedMenuTarget.classList.add('hidden');
    }

    // Fullscreen
    toggleFullscreen() {
        if (!document.fullscreenElement) {
            this.containerTarget.requestFullscreen();
        } else {
            document.exitFullscreen();
        }
    }

    onFullscreenChange() {
        this.isFullscreen = !!document.fullscreenElement;
        this.fullscreenBtnTarget.innerHTML = this.isFullscreen ? this.getExitFullscreenIcon() : this.getFullscreenIcon();
    }

    // Picture-in-Picture
    async togglePiP() {
        try {
            if (document.pictureInPictureElement) {
                await document.exitPictureInPicture();
            } else if (document.pictureInPictureEnabled) {
                await this.videoTarget.requestPictureInPicture();
            }
        } catch (error) {
            console.error('PiP error:', error);
        }
    }

    // Settings
    toggleSettings() {
        this.settingsMenuTarget.classList.toggle('hidden');
        this.qualityMenuTarget.classList.add('hidden');
        this.speedMenuTarget.classList.add('hidden');
    }

    // Controls visibility
    showControls() {
        this.controlsTarget.classList.remove('opacity-0');
        this.containerTarget.style.cursor = 'default';
        clearTimeout(this.hideControlsTimeout);
        
        if (this.isPlaying) {
            this.scheduleHideControls();
        }
    }

    scheduleHideControls() {
        clearTimeout(this.hideControlsTimeout);
        this.hideControlsTimeout = setTimeout(() => {
            if (this.isPlaying) {
                this.controlsTarget.classList.add('opacity-0');
                this.containerTarget.style.cursor = 'none';
            }
        }, 3000);
    }

    showLoader() {
        this.loaderTarget.classList.remove('hidden');
    }

    hideLoader() {
        this.loaderTarget.classList.add('hidden');
    }

    // Keyboard shortcuts
    handleKeyboard(e) {
        if (!this.containerTarget.contains(document.activeElement) && document.activeElement.tagName !== 'BODY') return;

        switch (e.key.toLowerCase()) {
            case ' ':
            case 'k':
                e.preventDefault();
                this.togglePlay();
                break;
            case 'f':
                e.preventDefault();
                this.toggleFullscreen();
                break;
            case 'm':
                e.preventDefault();
                this.toggleMute();
                break;
            case 'arrowleft':
            case 'j':
                e.preventDefault();
                this.skip(-10);
                break;
            case 'arrowright':
            case 'l':
                e.preventDefault();
                this.skip(10);
                break;
            case 'arrowup':
                e.preventDefault();
                this.videoTarget.volume = Math.min(1, this.videoTarget.volume + 0.1);
                break;
            case 'arrowdown':
                e.preventDefault();
                this.videoTarget.volume = Math.max(0, this.videoTarget.volume - 0.1);
                break;
            case '0':
            case '1':
            case '2':
            case '3':
            case '4':
            case '5':
            case '6':
            case '7':
            case '8':
            case '9':
                e.preventDefault();
                this.videoTarget.currentTime = (parseInt(e.key) / 10) * this.videoTarget.duration;
                break;
        }
    }

    // Watch history
    async recordWatchHistory() {
        if (!this.videoIdValue) return;
        
        try {
            await fetch('/history/record', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    videoId: this.videoIdValue,
                    seconds: Math.floor(this.videoTarget.currentTime)
                })
            });
        } catch (e) {
            console.error('Error recording watch history:', e);
        }
    }

    // Utility
    formatTime(seconds) {
        if (isNaN(seconds)) return '0:00';
        
        const h = Math.floor(seconds / 3600);
        const m = Math.floor((seconds % 3600) / 60);
        const s = Math.floor(seconds % 60);

        if (h > 0) {
            return `${h}:${m.toString().padStart(2, '0')}:${s.toString().padStart(2, '0')}`;
        }
        return `${m}:${s.toString().padStart(2, '0')}`;
    }

    // Icons
    getFullscreenIcon() {
        return `<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"/>
        </svg>`;
    }

    getExitFullscreenIcon() {
        return `<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 9V4H4m0 0l5 5M9 15v5H4m0 0l5-5m6-6V4h5m0 0l-5 5m5 6v5h-5m0 0l5-5"/>
        </svg>`;
    }

    getMuteIcon() {
        return `<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z"/>
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2"/>
        </svg>`;
    }

    getVolumeLowIcon() {
        return `<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.536 8.464a5 5 0 010 7.072M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z"/>
        </svg>`;
    }

    getVolumeHighIcon() {
        return `<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.536 8.464a5 5 0 010 7.072m2.828-9.9a9 9 0 010 12.728M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z"/>
        </svg>`;
    }

    // Chapters
    async loadChapters() {
        if (!this.videoIdValue) return;
        
        try {
            const response = await fetch(`/api/video/${this.videoIdValue}/chapters`);
            if (!response.ok) return;
            
            const data = await response.json();
            this.chapters = data.chapters || [];
            
            if (this.chapters.length > 0) {
                this.renderChapterMarkers();
                this.updateCurrentChapter();
            }
        } catch (error) {
            console.error('[VideoPlayer] Error loading chapters:', error);
        }
    }

    renderChapterMarkers() {
        if (!this.chapters.length || !this.videoTarget.duration) return;
        
        // Удаляем старые маркеры
        this.progressTarget.querySelectorAll('.chapter-marker').forEach(el => el.remove());
        
        // Добавляем новые маркеры
        this.chapters.forEach(chapter => {
            const percent = (chapter.timestamp / this.videoTarget.duration) * 100;
            const marker = document.createElement('div');
            marker.className = 'chapter-marker absolute h-full w-0.5 bg-white/60 hover:bg-white transition-colors cursor-pointer';
            marker.style.left = `${percent}%`;
            marker.title = chapter.title;
            marker.dataset.timestamp = chapter.timestamp;
            marker.addEventListener('click', (e) => {
                e.stopPropagation();
                this.seekToChapter(chapter.timestamp);
            });
            this.progressTarget.appendChild(marker);
        });
    }

    seekToChapter(timestamp) {
        this.videoTarget.currentTime = timestamp;
        if (this.videoTarget.paused) {
            this.videoTarget.play();
        }
    }

    updateCurrentChapter() {
        if (!this.chapters.length) return;
        
        const currentTime = this.videoTarget.currentTime;
        let newChapterIndex = -1;
        
        // Находим текущую главу
        for (let i = this.chapters.length - 1; i >= 0; i--) {
            if (currentTime >= this.chapters[i].timestamp) {
                newChapterIndex = i;
                break;
            }
        }
        
        // Обновляем UI только если глава изменилась
        if (newChapterIndex !== this.currentChapterIndex) {
            this.currentChapterIndex = newChapterIndex;
            this.highlightCurrentChapter();
        }
    }

    highlightCurrentChapter() {
        if (!this.hasChaptersListTarget) return;
        
        // Убираем выделение со всех глав
        this.chapterItemTargets.forEach((item, index) => {
            if (index === this.currentChapterIndex) {
                item.classList.add('bg-orange-50', 'dark:bg-orange-900/20', 'border-orange-500');
                item.classList.remove('border-gray-200', 'dark:border-gray-700');
            } else {
                item.classList.remove('bg-orange-50', 'dark:bg-orange-900/20', 'border-orange-500');
                item.classList.add('border-gray-200', 'dark:border-gray-700');
            }
        });
    }

    jumpToChapter(e) {
        const timestamp = parseInt(e.currentTarget.dataset.timestamp);
        this.seekToChapter(timestamp);
    }

    disconnect() {
        clearTimeout(this.hideControlsTimeout);
        this.recordWatchHistory();
    }
}