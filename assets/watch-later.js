// Watch Later functionality

/**
 * Переключает состояние "Смотреть позже" для видео
 */
window.toggleWatchLater = async function(button, videoId) {
    try {
        const response = await fetch(`/watch-later/toggle/${videoId}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        });

        const data = await response.json();

        if (data.success) {
            // Обновляем иконку и цвет кнопки
            updateWatchLaterButton(button, data.added);
            
            // Показываем уведомление через новую систему
            if (data.added) {
                window.showSuccess(data.message);
            } else {
                window.showInfo(data.message);
            }
        } else {
            window.showError(data.message || 'Произошла ошибка');
        }
    } catch (error) {
        console.error('Watch Later error:', error);
        window.showError('Произошла ошибка при обработке запроса');
    }
};

/**
 * Обновляет внешний вид кнопки "Смотреть позже"
 */
function updateWatchLaterButton(button, isAdded) {
    if (isAdded) {
        button.classList.add('text-blue-400');
        button.classList.remove('text-white');
        button.title = 'Удалить из "Смотреть позже"';
    } else {
        button.classList.remove('text-blue-400');
        button.classList.add('text-white');
        button.title = 'Добавить в "Смотреть позже"';
    }
}

/**
 * Проверяет статус "Смотреть позже" для всех видео на странице
 */
async function checkWatchLaterStatus() {
    // Проверяем, есть ли на странице data-атрибут с ID видео в watch later
    const watchLaterIds = document.body.dataset.watchLaterIds;
    
    if (!watchLaterIds) {
        return;
    }
    
    const ids = JSON.parse(watchLaterIds);
    const buttons = document.querySelectorAll('.watch-later-btn');
    
    buttons.forEach(button => {
        const videoId = parseInt(button.dataset.videoId);
        
        if (ids.includes(videoId)) {
            updateWatchLaterButton(button, true);
        }
    });
}

// Инициализация при загрузке страницы
document.addEventListener('DOMContentLoaded', () => {
    checkWatchLaterStatus();
});

// Поддержка Turbo (для SPA-навигации)
document.addEventListener('turbo:load', () => {
    checkWatchLaterStatus();
});
