import { Controller } from '@hotwired/stimulus';

/**
 * Infinite Scroll Controller
 * Автоматически загружает следующую страницу при достижении конца списка
 */
export default class extends Controller {
    static targets = ['container', 'sentinel', 'loader'];
    
    static values = {
        url: String,
        page: { type: Number, default: 1 },
        sort: { type: String, default: 'newest' },
        hasMore: { type: Boolean, default: true },
        threshold: { type: Number, default: 200 } // px от конца страницы
    };

    connect() {
        console.log('[InfiniteScroll] Connected');
        this.loading = false;
        this.setupObserver();
    }

    setupObserver() {
        // Используем Intersection Observer для определения видимости sentinel элемента
        const options = {
            root: null, // viewport
            rootMargin: `${this.thresholdValue}px`,
            threshold: 0.1
        };

        this.observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting && this.hasMoreValue && !this.loading) {
                    this.loadMore();
                }
            });
        }, options);

        // Наблюдаем за sentinel элементом
        if (this.hasSentinelTarget) {
            this.observer.observe(this.sentinelTarget);
        }
    }

    async loadMore() {
        if (this.loading || !this.hasMoreValue) return;

        this.loading = true;
        this.showLoader();

        try {
            const nextPage = this.pageValue + 1;
            const url = new URL(this.urlValue, window.location.origin);
            url.searchParams.set('page', nextPage);
            url.searchParams.set('sort', this.sortValue);

            console.log('[InfiniteScroll] Loading page:', nextPage);

            const response = await fetch(url.toString(), {
                headers: {
                    'Accept': 'text/html',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const html = await response.text();
            
            // Парсим HTML для извлечения данных
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            
            // Извлекаем видео карточки
            const newItems = doc.querySelectorAll('.video-card');
            
            if (newItems.length > 0) {
                // Добавляем новые элементы
                newItems.forEach(item => {
                    this.containerTarget.appendChild(item.cloneNode(true));
                });

                // Обновляем страницу
                this.pageValue = nextPage;

                // Проверяем, есть ли ещё страницы
                const hasMoreIndicator = doc.querySelector('[data-has-more]');
                this.hasMoreValue = hasMoreIndicator ? 
                    hasMoreIndicator.dataset.hasMore === 'true' : false;

                console.log('[InfiniteScroll] Loaded', newItems.length, 'items. Has more:', this.hasMoreValue);

                // Если больше нет страниц, удаляем sentinel
                if (!this.hasMoreValue && this.hasSentinelTarget) {
                    this.observer.unobserve(this.sentinelTarget);
                    this.sentinelTarget.remove();
                }

                // Обрабатываем новые элементы с помощью HTMX если доступен
                if (window.htmx) {
                    htmx.process(this.containerTarget);
                }
            } else {
                // Нет новых элементов
                this.hasMoreValue = false;
                if (this.hasSentinelTarget) {
                    this.observer.unobserve(this.sentinelTarget);
                    this.sentinelTarget.remove();
                }
            }

        } catch (error) {
            console.error('[InfiniteScroll] Error loading more:', error);
            this.showError();
        } finally {
            this.loading = false;
            this.hideLoader();
        }
    }

    showLoader() {
        if (this.hasLoaderTarget) {
            this.loaderTarget.classList.remove('hidden');
        }
    }

    hideLoader() {
        if (this.hasLoaderTarget) {
            this.loaderTarget.classList.add('hidden');
        }
    }

    showError() {
        // Можно добавить отображение ошибки
        console.error('[InfiniteScroll] Failed to load more items');
    }

    disconnect() {
        if (this.observer) {
            this.observer.disconnect();
        }
    }
}
