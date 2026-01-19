/**
 * Глобальные функции для взаимодействия с контентом
 * (закладки, плейлисты, подписки, рейтинги)
 */

/**
 * Переключает закладку для видео
 */
window.toggleBookmark = async function(videoId) {
    try {
        const btn = document.getElementById('bookmark-btn');
        const svg = btn?.querySelector('svg');
        
        const response = await fetch(`/bookmarks/video/${videoId}`, {
            method: 'POST',
            headers: { 'Accept': 'application/json' }
        });

        if (response.ok) {
            const data = await response.json();
            
            if (data.isBookmarked) {
                svg?.setAttribute('fill', 'currentColor');
                btn?.classList.add('text-yellow-500');
                btn?.classList.remove('text-gray-600', 'dark:text-gray-400');
                window.showSuccess(data.message || 'Добавлено в избранное');
            } else {
                svg?.setAttribute('fill', 'none');
                btn?.classList.remove('text-yellow-500');
                btn?.classList.add('text-gray-600', 'dark:text-gray-400');
                window.showInfo(data.message || 'Удалено из избранного');
            }
        } else {
            window.showError('Не удалось обновить закладку');
        }
    } catch (error) {
        console.error('Error toggling bookmark:', error);
        window.showError('Произошла ошибка при обработке запроса');
    }
};

/**
 * Переключает закладку в карточке видео
 */
window.toggleCardBookmark = async function(button, videoId) {
    try {
        const response = await fetch(`/bookmarks/video/${videoId}`, {
            method: 'POST',
            headers: { 'Accept': 'application/json' }
        });

        if (response.ok) {
            const data = await response.json();
            
            if (data.isBookmarked) {
                button.classList.add('text-yellow-400');
                button.classList.remove('text-white');
                button.title = 'Удалить из избранного';
                window.showSuccess(data.message || 'Добавлено в избранное');
            } else {
                button.classList.remove('text-yellow-400');
                button.classList.add('text-white');
                button.title = 'Добавить в избранное';
                window.showInfo(data.message || 'Удалено из избранного');
            }
        } else {
            window.showError('Не удалось обновить закладку');
        }
    } catch (error) {
        console.error('Error toggling bookmark:', error);
        window.showError('Произошла ошибка при обработке запроса');
    }
};

/**
 * Открывает модальное окно для добавления в плейлист
 */
window.openPlaylistModal = async function(videoId) {
    // Получаем плейлисты пользователя
    try {
        const response = await fetch('/playlists?format=json', {
            headers: { 'Accept': 'application/json' }
        });
        
        if (response.ok) {
            const playlists = await response.json();
            if (playlists.length === 0) {
                if (confirm('У вас нет плейлистов. Создать новый?')) {
                    window.location.href = '/playlists/create';
                }
            } else {
                // Простой выбор через prompt
                let options = playlists.map((p, i) => `${i + 1}. ${p.title}`).join('\n');
                let choice = prompt(`Выберите плейлист (введите номер):\n${options}`);
                if (choice) {
                    let idx = parseInt(choice) - 1;
                    if (idx >= 0 && idx < playlists.length) {
                        await window.addToPlaylist(playlists[idx].id, videoId);
                    }
                }
            }
        }
    } catch (e) {
        console.error('Error loading playlists:', e);
        window.location.href = '/playlists';
    }
};

/**
 * Переключает видимость dropdown плейлистов
 */
window.togglePlaylistDropdown = function() {
    const dropdown = document.getElementById('playlist-dropdown');
    const menu = dropdown?.querySelector('.playlist-menu');
    
    if (menu) {
        menu.classList.toggle('hidden');
    }
};

/**
 * Добавляет видео в плейлист
 */
window.addToPlaylist = async function(playlistId, videoId) {
    try {
        const response = await fetch(`/playlists/${playlistId}/videos/${videoId}`, {
            method: 'POST',
            headers: { 'Accept': 'application/json' }
        });

        const data = await response.json();

        if (response.ok) {
            window.showSuccess(data.message || 'Видео добавлено в плейлист');
            
            // Закрываем dropdown
            const menu = document.querySelector('.playlist-menu');
            if (menu) {
                menu.classList.add('hidden');
            }
        } else {
            window.showError(data.message || 'Не удалось добавить видео в плейлист');
        }
    } catch (error) {
        console.error('Error adding to playlist:', error);
        window.showError('Произошла ошибка при добавлении в плейлист');
    }
};

/**
 * Удаляет видео из плейлиста
 */
window.removeFromPlaylist = async function(playlistId, videoId) {
    if (!confirm('Удалить видео из плейлиста?')) {
        return;
    }

    try {
        const response = await fetch(`/playlists/${playlistId}/videos/${videoId}`, {
            method: 'DELETE',
            headers: { 'Accept': 'application/json' }
        });

        const data = await response.json();

        if (response.ok) {
            window.showSuccess(data.message || 'Видео удалено из плейлиста');
            
            // Удаляем элемент из DOM
            const videoCard = document.querySelector(`[data-video-id="${videoId}"]`)?.closest('.video-card');
            if (videoCard) {
                videoCard.style.opacity = '0';
                setTimeout(() => videoCard.remove(), 300);
            }
        } else {
            window.showError(data.message || 'Не удалось удалить видео');
        }
    } catch (error) {
        console.error('Error removing from playlist:', error);
        window.showError('Произошла ошибка при удалении из плейлиста');
    }
};

/**
 * Подписка/отписка на канал
 */
window.toggleSubscription = async function(channelId, button) {
    try {
        const response = await fetch(`/subscription/toggle/${channelId}`, {
            method: 'POST',
            headers: { 'Accept': 'application/json' }
        });

        if (response.ok) {
            const data = await response.json();
            const isSubscribed = data.isSubscribed;
            
            // Обновляем кнопку
            button.dataset.subscribed = isSubscribed ? 'true' : 'false';
            
            if (isSubscribed) {
                button.classList.remove('bg-primary-600', 'text-white', 'hover:bg-primary-700');
                button.classList.add('bg-gray-200', 'dark:bg-gray-700', 'text-gray-700', 'dark:text-gray-300', 'hover:bg-gray-300', 'dark:hover:bg-gray-600');
                button.querySelector('.btn-text').textContent = 'Отписаться';
                window.showSuccess(data.message);
            } else {
                button.classList.add('bg-primary-600', 'text-white', 'hover:bg-primary-700');
                button.classList.remove('bg-gray-200', 'dark:bg-gray-700', 'text-gray-700', 'dark:text-gray-300', 'hover:bg-gray-300', 'dark:hover:bg-gray-600');
                button.querySelector('.btn-text').textContent = 'Подписаться';
                window.showInfo(data.message);
            }
        } else {
            window.showError('Не удалось изменить подписку');
        }
    } catch (error) {
        console.error('Error toggling subscription:', error);
        window.showError('Произошла ошибка');
    }
};

/**
 * Голосование за видео (лайк/дизлайк)
 */
window.toggleVideoLike = async function(videoId, type, button) {
    try {
        const url = type === 'like' 
            ? `/like/video/${videoId}/like`
            : `/like/video/${videoId}/dislike`;
            
        const response = await fetch(url, {
            method: 'POST',
            headers: { 'Accept': 'application/json' }
        });

        if (response.ok) {
            const data = await response.json();
            
            // Обновляем счётчики
            const container = document.getElementById('like-buttons');
            const likesCount = container.querySelector('.likes-count');
            const dislikesCount = container.querySelector('.dislikes-count');
            const likeBtn = container.querySelector('.like-btn');
            const dislikeBtn = container.querySelector('.dislike-btn');
            
            if (likesCount) likesCount.textContent = data.likesCount;
            if (dislikesCount) dislikesCount.textContent = data.dislikesCount;
            
            // Обновляем стили кнопок
            updateLikeButtonStyles(likeBtn, dislikeBtn, data.userLike);
            
            // Обновляем progress bar
            updateLikeProgressBar(data.likesCount, data.dislikesCount);
            
            window.showSuccess(data.message);
        } else {
            window.showError('Не удалось проголосовать');
        }
    } catch (error) {
        console.error('Error voting:', error);
        window.showError('Произошла ошибка при голосовании');
    }
};

/**
 * Голосование за модель (лайк/дизлайк)
 */
window.toggleModelLike = async function(modelId, type, button) {
    try {
        const response = await fetch(`/model/like/${modelId}/${type}`, {
            method: 'POST',
            headers: { 'Accept': 'application/json' }
        });

        if (response.ok) {
            const data = await response.json();
            
            // Обновляем счётчики
            const container = document.getElementById(`model-like-buttons-${modelId}`);
            const likesCount = container.querySelector('.likes-count');
            const dislikesCount = container.querySelector('.dislikes-count');
            const likeBtn = container.querySelector('.model-like-btn');
            const dislikeBtn = container.querySelector('.model-dislike-btn');
            
            if (likesCount) likesCount.textContent = data.likesCount;
            if (dislikesCount) dislikesCount.textContent = data.dislikesCount;
            
            // Обновляем стили кнопок
            updateModelLikeButtonStyles(likeBtn, dislikeBtn, data.userLike);
            
            // Обновляем progress bar
            updateModelLikeProgressBar(modelId, data.likesCount, data.dislikesCount);
            
            window.showSuccess(data.message);
        } else {
            window.showError('Не удалось проголосовать');
        }
    } catch (error) {
        console.error('Error voting:', error);
        window.showError('Произошла ошибка при голосовании');
    }
};

/**
 * Обновляет стили кнопок лайка/дизлайка видео
 */
function updateLikeButtonStyles(likeBtn, dislikeBtn, userLike) {
    // Сбрасываем стили
    likeBtn?.classList.remove('bg-green-600', 'text-white');
    likeBtn?.classList.add('bg-gray-200', 'dark:bg-gray-700', 'text-gray-700', 'dark:text-gray-300');
    likeBtn?.querySelector('svg')?.setAttribute('fill', 'none');
    
    dislikeBtn?.classList.remove('bg-red-600', 'text-white');
    dislikeBtn?.classList.add('bg-gray-200', 'dark:bg-gray-700', 'text-gray-700', 'dark:text-gray-300');
    dislikeBtn?.querySelector('svg')?.setAttribute('fill', 'none');
    
    // Применяем активный стиль
    if (userLike === 'like') {
        likeBtn?.classList.add('bg-green-600', 'text-white');
        likeBtn?.classList.remove('bg-gray-200', 'dark:bg-gray-700', 'text-gray-700', 'dark:text-gray-300');
        likeBtn?.querySelector('svg')?.setAttribute('fill', 'currentColor');
    } else if (userLike === 'dislike') {
        dislikeBtn?.classList.add('bg-red-600', 'text-white');
        dislikeBtn?.classList.remove('bg-gray-200', 'dark:bg-gray-700', 'text-gray-700', 'dark:text-gray-300');
        dislikeBtn?.querySelector('svg')?.setAttribute('fill', 'currentColor');
    }
}

/**
 * Обновляет стили кнопок лайка/дизлайка модели
 */
function updateModelLikeButtonStyles(likeBtn, dislikeBtn, userLike) {
    // Сбрасываем стили
    likeBtn?.classList.remove('bg-green-600', 'text-white');
    likeBtn?.classList.add('bg-gray-200', 'dark:bg-gray-700', 'text-gray-700', 'dark:text-gray-300');
    
    dislikeBtn?.classList.remove('bg-red-600', 'text-white');
    dislikeBtn?.classList.add('bg-gray-200', 'dark:bg-gray-700', 'text-gray-700', 'dark:text-gray-300');
    
    // Применяем активный стиль
    if (userLike === 'like') {
        likeBtn?.classList.add('bg-green-600', 'text-white');
        likeBtn?.classList.remove('bg-gray-200', 'dark:bg-gray-700', 'text-gray-700', 'dark:text-gray-300');
    } else if (userLike === 'dislike') {
        dislikeBtn?.classList.add('bg-red-600', 'text-white');
        dislikeBtn?.classList.remove('bg-gray-200', 'dark:bg-gray-700', 'text-gray-700', 'dark:text-gray-300');
    }
}

/**
 * Обновляет progress bar лайков видео
 */
function updateLikeProgressBar(likes, dislikes) {
    const progress = document.getElementById('like-progress');
    if (!progress) return;
    
    const total = likes + dislikes;
    const likePercent = total > 0 ? (likes / total * 100) : 50;
    
    let likeBar = progress.querySelector('.like-bar');
    let dislikeBar = progress.querySelector('.dislike-bar');
    
    if (total > 0) {
        progress.classList.remove('bg-gray-300', 'dark:bg-gray-600');
        
        if (!likeBar) {
            progress.innerHTML = `
                <div class="like-bar bg-green-500 h-full transition-all duration-300" style="width: ${likePercent}%"></div>
                <div class="dislike-bar bg-red-500 h-full transition-all duration-300" style="width: ${100 - likePercent}%"></div>
            `;
        } else {
            likeBar.style.width = `${likePercent}%`;
            dislikeBar.style.width = `${100 - likePercent}%`;
        }
    }
}

/**
 * Обновляет progress bar лайков модели
 */
function updateModelLikeProgressBar(modelId, likes, dislikes) {
    const progress = document.getElementById(`model-like-progress-${modelId}`);
    if (!progress) return;
    
    const total = likes + dislikes;
    const likePercent = total > 0 ? (likes / total * 100) : 50;
    
    let likeBar = progress.querySelector('.like-bar');
    let dislikeBar = progress.querySelector('.dislike-bar');
    
    if (total > 0) {
        progress.classList.remove('bg-gray-300', 'dark:bg-gray-600');
        
        if (!likeBar) {
            progress.innerHTML = `
                <div class="like-bar bg-green-500 h-full transition-all duration-300" style="width: ${likePercent}%"></div>
                <div class="dislike-bar bg-red-500 h-full transition-all duration-300" style="width: ${100 - likePercent}%"></div>
            `;
        } else {
            likeBar.style.width = `${likePercent}%`;
            dislikeBar.style.width = `${100 - likePercent}%`;
        }
    }
}

/**
 * Закрытие dropdown при клике вне его
 */
document.addEventListener('click', function(event) {
    const dropdown = document.getElementById('playlist-dropdown');
    if (dropdown && !dropdown.contains(event.target)) {
        const menu = dropdown.querySelector('.playlist-menu');
        if (menu && !menu.classList.contains('hidden')) {
            menu.classList.add('hidden');
        }
    }
});

console.log('Interactions module loaded');
