// Enhanced Lazy Loading для изображений и видео
document.addEventListener('DOMContentLoaded', function() {
    // Создаем placeholder для изображений
    const placeholder = 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1 1"%3E%3Crect width="1" height="1" fill="%23f3f4f6"/%3E%3C/svg%3E';
    
    // Находим все изображения с data-lazy-src
    const lazyImages = document.querySelectorAll('img[data-lazy-src]');
    // Находим все видео с data-lazy-video
    const lazyVideos = document.querySelectorAll('video[data-lazy-video]');
    
    // Обрабатываем изображения
    lazyImages.forEach(img => {
        const originalSrc = img.getAttribute('data-lazy-src');
        img.setAttribute('data-original-src', originalSrc);
        
        // Для постеров видео не заменяем на placeholder, оставляем оригинальный src
        if (!img.classList.contains('poster-image')) {
            img.src = placeholder;
            img.classList.add('lazy-image');
        }
        
        // Добавляем обработчик ошибок
        img.onerror = function() {
            this.classList.add('error');
        };
    });
    
    // Обрабатываем видео
    lazyVideos.forEach(video => {
        const originalSrc = video.getAttribute('data-lazy-video');
        video.setAttribute('data-original-video', originalSrc);
        video.removeAttribute('src'); // Убираем src чтобы не загружалось сразу
        video.classList.add('lazy-video');
    });
    
    // Проверяем поддержку Intersection Observer
    if ('IntersectionObserver' in window) {
        const mediaObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const element = entry.target;
                    
                    if (element.tagName === 'IMG') {
                        const src = element.getAttribute('data-original-src');
                        
                        if (src) {
                            // Для постеров видео просто добавляем класс loaded
                            if (element.classList.contains('poster-image')) {
                                element.classList.add('loaded');
                                element.removeAttribute('data-lazy-src');
                                element.removeAttribute('data-original-src');
                            } else {
                                // Для других изображений делаем предзагрузку
                                const newImg = new Image();
                                
                                newImg.onload = function() {
                                    element.src = src;
                                    element.classList.add('loaded');
                                    element.removeAttribute('data-lazy-src');
                                    element.removeAttribute('data-original-src');
                                };
                                
                                newImg.onerror = function() {
                                    element.classList.add('error');
                                };
                                
                                newImg.src = src;
                            }
                            
                            observer.unobserve(element);
                        }
                    } else if (element.tagName === 'VIDEO') {
                        const src = element.getAttribute('data-original-video');
                        
                        if (src) {
                            element.src = src;
                            element.preload = 'metadata';
                            element.classList.add('loaded');
                            element.removeAttribute('data-lazy-video');
                            element.removeAttribute('data-original-video');
                            
                            observer.unobserve(element);
                        }
                    }
                }
            });
        }, {
            // Начинаем загрузку за 100px до появления в viewport
            rootMargin: '100px 0px',
            threshold: 0.01
        });

        // Наблюдаем за всеми lazy элементами
        [...lazyImages, ...lazyVideos].forEach(element => {
            mediaObserver.observe(element);
        });
    } else {
        // Fallback для старых браузеров
        lazyImages.forEach(img => {
            const src = img.getAttribute('data-original-src');
            if (src && !img.classList.contains('poster-image')) {
                img.src = src;
            }
            img.classList.add('loaded');
            img.removeAttribute('data-lazy-src');
            img.removeAttribute('data-original-src');
        });
        
        lazyVideos.forEach(video => {
            const src = video.getAttribute('data-original-video');
            if (src) {
                video.src = src;
                video.preload = 'metadata';
            }
            video.classList.add('loaded');
            video.removeAttribute('data-lazy-video');
            video.removeAttribute('data-original-video');
        });
    }
});

// Функция для принудительной загрузки изображения (для динамически добавленного контента)
window.loadLazyImage = function(img) {
    const src = img.getAttribute('data-original-src') || img.getAttribute('data-lazy-src');
    if (src) {
        img.src = src;
        img.classList.add('loaded');
        img.removeAttribute('data-lazy-src');
        img.removeAttribute('data-original-src');
    }
};