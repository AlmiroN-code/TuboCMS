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
    const modal = document.getElementById('playlist-modal-' + videoId);
    const listContainer = document.getElementById('playlist-list-' + videoId);
    
    if (!modal || !listContainer) return;
    
    // Показываем модальное окно
    modal.style.display = 'flex';
    modal.classList.remove('hidden');
    
    // Показываем загрузку
    listContainer.innerHTML = '<div class="text-center py-4 text-gray-500 dark:text-gray-400">Loading...</div>';
    
    try {
        const response = await fetch('/api/playlists/my');
        if (!response.ok) throw new Error('Failed to load playlists');
        
        const playlists = await response.json();
        
        if (playlists.length === 0) {
            // Получаем переведённый текст из data-атрибута или используем дефолтный
            const noPlaylistsText = modal.dataset.noPlaylistsText || 'No playlists have been created yet.';
            
            listContainer.innerHTML = `
                <div class="text-center py-8 text-gray-500 dark:text-gray-400">
                    <svg class="w-16 h-16 mx-auto mb-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                    </svg>
                    <p>${noPlaylistsText}</p>
                </div>
            `;
        } else {
            const visibilityLabels = {
                'public': 'Public',
                'private': 'Private',
                'unlisted': 'Unlisted',
                'user_subscribers': 'My Subscribers',
                'channel_subscribers': 'Channel Subscribers'
            };
            
            listContainer.innerHTML = playlists.map(playlist => {
                const visibilityLabel = visibilityLabels[playlist.visibility] || playlist.visibility;
                return `
                <button type="button" 
                        onclick="addToPlaylist(${playlist.id}, ${videoId}); closePlaylistModal(${videoId});"
                        class="w-full flex items-center justify-between p-3 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-md transition">
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 bg-gray-200 dark:bg-gray-600 rounded flex items-center justify-center">
                            <svg class="w-6 h-6 text-gray-500 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                            </svg>
                        </div>
                        <div class="text-left">
                            <p class="font-medium text-gray-900 dark:text-gray-100">${playlist.title}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">${playlist.videosCount} videos • ${visibilityLabel}</p>
                        </div>
                    </div>
                    <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                    </svg>
                </button>
            `;
            }).join('');
        }
    } catch (error) {
        console.error('Error loading playlists:', error);
        listContainer.innerHTML = '<div class="text-center py-4 text-red-500">Error loading playlists</div>';
    }
};

/**
 * Закрывает модальное окно плейлиста
 */
window.closePlaylistModal = function(videoId) {
    const modal = document.getElementById('playlist-modal-' + videoId);
    if (modal) {
        modal.style.display = 'none';
        modal.classList.add('hidden');
    }
};

/**
 * Открывает модальное окно создания плейлиста
 */
window.openCreatePlaylistModal = function(videoId) {
    // Закрываем модальное окно выбора плейлиста
    closePlaylistModal(videoId);
    
    const modal = document.getElementById('create-playlist-modal-' + videoId);
    if (modal) {
        modal.style.display = 'flex';
        modal.classList.remove('hidden');
        
        // Добавляем обработчик формы
        const form = document.getElementById('create-playlist-form-' + videoId);
        if (form && !form.dataset.listenerAdded) {
            form.dataset.listenerAdded = 'true';
            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                await handleCreatePlaylist(videoId, form);
            });
        }
    }
};

/**
 * Закрывает модальное окно создания плейлиста
 */
window.closeCreatePlaylistModal = function(videoId) {
    const modal = document.getElementById('create-playlist-modal-' + videoId);
    if (modal) {
        modal.style.display = 'none';
        modal.classList.add('hidden');
        
        // Очищаем форму
        const form = document.getElementById('create-playlist-form-' + videoId);
        if (form) {
            form.reset();
        }
    }
};

/**
 * Обрабатывает создание плейлиста
 */
async function handleCreatePlaylist(videoId, form) {
    const formData = new FormData(form);
    const title = formData.get('title');
    const visibility = formData.get('visibility');
    
    if (!title) {
        window.showError('Введите название плейлиста');
        return;
    }
    
    try {
        const response = await fetch('/api/playlists/create', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                title: title,
                visibility: visibility,
                videoId: videoId
            })
        });
        
        const data = await response.json();
        
        if (response.ok) {
            window.showSuccess(data.message || 'Плейлист создан и видео добавлено');
            closeCreatePlaylistModal(videoId);
            
            // Перезагружаем список плейлистов
            if (typeof openPlaylistModal === 'function') {
                setTimeout(() => openPlaylistModal(videoId), 300);
            }
        } else {
            window.showError(data.error || 'Не удалось создать плейлист');
        }
    } catch (error) {
        console.error('Error creating playlist:', error);
        window.showError('Произошла ошибка при создании плейлиста');
    }
}

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
            // Проверяем, является ли это ошибкой дубликата
            if (data.isDuplicate) {
                window.showInfo(data.error || 'Видео уже добавлено в этот плейлист');
            } else {
                window.showError(data.error || 'Не удалось добавить видео в плейлист');
            }
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
    
    // Обновляем процент и эмодзи
    updateRatingDisplay(likes, dislikes);
}

/**
 * Обновляет отображение процента рейтинга и эмодзи
 */
function updateRatingDisplay(likes, dislikes) {
    const container = document.getElementById('like-buttons');
    if (!container) return;
    
    const ratingDisplay = container.querySelector('.rating-display');
    if (!ratingDisplay) return;
    
    const total = likes + dislikes;
    const percent = total > 0 ? Math.round((likes / total) * 100) : 50;
    
    // Определяем эмодзи
    let emoji = '😐'; // Нейтральный
    if (percent >= 80) {
        emoji = '😊'; // Радостный
    } else if (percent < 50) {
        emoji = '😞'; // Грустный
    }
    
    // Обновляем содержимое
    const emojiSpan = ratingDisplay.querySelector('.rating-emoji');
    const percentSpan = ratingDisplay.querySelector('.rating-percent');
    
    if (emojiSpan) emojiSpan.textContent = emoji;
    if (percentSpan) percentSpan.textContent = `${percent}%`;
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

/**
 * Закрытие модальных окон плейлистов при клике на фон
 */
document.addEventListener('click', function(e) {
    // Закрытие модального окна выбора плейлиста
    if (e.target.id && e.target.id.startsWith('playlist-modal-')) {
        const videoId = e.target.id.replace('playlist-modal-', '');
        closePlaylistModal(videoId);
    }
    
    // Закрытие модального окна создания плейлиста
    if (e.target.id && e.target.id.startsWith('create-playlist-modal-')) {
        const videoId = e.target.id.replace('create-playlist-modal-', '');
        closeCreatePlaylistModal(videoId);
    }
});

console.log('Interactions module loaded');

