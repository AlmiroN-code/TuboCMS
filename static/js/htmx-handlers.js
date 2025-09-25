// HTMX Handlers for Video Portal

document.addEventListener('DOMContentLoaded', function() {
    // Video preview on hover functionality
    initializeVideoPreviews();
    
    // HTMX event listeners
    initializeHTMXHandlers();
    
    // Theme toggle functionality
    initializeThemeToggle();
    
    // Width toggle functionality
    initializeWidthToggle();
    
    // Mobile menu toggle
    initializeMobileMenu();
    
    // Sidebar expand functionality
    initializeSidebarExpand();
});

// Video Preview on Hover
function initializeVideoPreviews() {
    const thumbnails = document.querySelectorAll('.thumbnail[data-preview-id]');
    
    thumbnails.forEach(thumbnail => {
        const previewVideo = thumbnail.querySelector('.preview-video');
        if (!previewVideo) return;
        
        let hoverTimeout;
        
        thumbnail.addEventListener('mouseenter', function() {
            clearTimeout(hoverTimeout);
            hoverTimeout = setTimeout(() => {
                thumbnail.classList.add('preview-play');
                previewVideo.play().catch(e => {
                    console.log('Video preview play failed:', e);
                });
            }, 300); // 300ms delay before starting preview
        });
        
        thumbnail.addEventListener('mouseleave', function() {
            clearTimeout(hoverTimeout);
            thumbnail.classList.remove('preview-play');
            previewVideo.pause();
            previewVideo.currentTime = 0;
        });
    });
}

// HTMX Event Handlers
function initializeHTMXHandlers() {
    // HTMX configuration
    htmx.config.globalViewTransitions = true;
    
    // Handle HTMX requests
    document.body.addEventListener('htmx:beforeRequest', function(evt) {
        // Show loading indicator if needed
        const target = evt.target;
        if (target.classList.contains('btn-loading')) {
            target.classList.add('loading');
            target.disabled = true;
        }
    });
    
    document.body.addEventListener('htmx:afterRequest', function(evt) {
        // Hide loading indicator
        const target = evt.target;
        if (target.classList.contains('btn-loading')) {
            target.classList.remove('loading');
            target.disabled = false;
        }
        
        // Reinitialize video previews for new content
        if (evt.detail.successful) {
            initializeVideoPreviews();
        }
    });
    
    // Handle video likes
    document.body.addEventListener('click', function(e) {
        if (e.target.closest('[hx-post*="like_video"]')) {
            e.preventDefault();
            const button = e.target.closest('[hx-post*="like_video"]');
            const videoId = button.dataset.videoId;
            toggleVideoLike(videoId, button);
        }
    });
    
    // Handle comment likes
    document.body.addEventListener('click', function(e) {
        if (e.target.closest('.comment-action-btn')) {
            e.preventDefault();
            const button = e.target.closest('.comment-action-btn');
            const action = button.dataset.action;
            const commentId = button.dataset.commentId;
            
            if (action === 'like' || action === 'dislike') {
                toggleCommentLike(commentId, action, button);
            }
            }
        });
    }

// Video Like Toggle
function toggleVideoLike(videoId, button) {
    const isLiked = button.classList.contains('liked');
    const likeCount = button.querySelector('.like-count');
    const currentCount = parseInt(likeCount.textContent) || 0;
    
    // Optimistic update
    if (isLiked) {
        button.classList.remove('liked');
        likeCount.textContent = Math.max(0, currentCount - 1);
    } else {
        button.classList.add('liked');
        likeCount.textContent = currentCount + 1;
    }
    
    // Make HTMX request
    htmx.ajax('POST', `/videos/like/${videoId}/`, {
        values: { action: isLiked ? 'unlike' : 'like' },
        target: button,
        swap: 'outerHTML'
    }).catch(() => {
        // Revert optimistic update on error
        if (isLiked) {
            button.classList.add('liked');
            likeCount.textContent = currentCount;
        } else {
            button.classList.remove('liked');
            likeCount.textContent = Math.max(0, currentCount - 1);
        }
        showNotification('Ошибка при обновлении лайка', 'error');
    });
}

// Comment Like Toggle
function toggleCommentLike(commentId, action, button) {
    const isActive = button.classList.contains('active');
    const countElement = button.querySelector('.count');
    const currentCount = parseInt(countElement.textContent) || 0;
    
    // Optimistic update
    if (isActive) {
        button.classList.remove('active');
        countElement.textContent = Math.max(0, currentCount - 1);
    } else {
        button.classList.add('active');
        countElement.textContent = currentCount + 1;
        
        // Remove active state from opposite button
        const oppositeAction = action === 'like' ? 'dislike' : 'like';
        const oppositeButton = button.parentElement.querySelector(`[data-action="${oppositeAction}"]`);
        if (oppositeButton && oppositeButton.classList.contains('active')) {
            const oppositeCount = oppositeButton.querySelector('.count');
            const oppositeCurrentCount = parseInt(oppositeCount.textContent) || 0;
            oppositeButton.classList.remove('active');
            oppositeCount.textContent = Math.max(0, oppositeCurrentCount - 1);
        }
    }
        
        // Make HTMX request
    htmx.ajax('POST', `/comments/like/${commentId}/`, {
        values: { action: isActive ? 'un' + action : action },
        target: button.parentElement,
        swap: 'outerHTML'
        }).catch(() => {
        // Revert optimistic update on error
        if (isActive) {
            button.classList.add('active');
            countElement.textContent = currentCount;
        } else {
            button.classList.remove('active');
            countElement.textContent = Math.max(0, currentCount - 1);
        }
        showNotification('Ошибка при обновлении лайка', 'error');
    });
}

// Theme Toggle
function initializeThemeToggle() {
    const themeToggle = document.querySelector('.theme-toggle');
    if (!themeToggle) return;
    
    // Load saved theme
    const savedTheme = localStorage.getItem('theme');
    if (savedTheme) {
        document.body.classList.toggle('dark-mode', savedTheme === 'dark');
        updateThemeIcon(themeToggle, savedTheme);
    }
    
    themeToggle.addEventListener('click', function() {
        const isDark = document.body.classList.toggle('dark-mode');
        const theme = isDark ? 'dark' : 'light';
        
        localStorage.setItem('theme', theme);
        updateThemeIcon(themeToggle, theme);
        
        // Trigger theme change event
        document.dispatchEvent(new CustomEvent('themeChanged', { 
            detail: { theme: theme } 
        }));
    });
}

function updateThemeIcon(button, theme) {
    const icon = button.querySelector('i');
    if (icon) {
        icon.className = theme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
    }
}

// Width Toggle
function initializeWidthToggle() {
    const widthToggle = document.querySelector('.width-toggle');
    if (!widthToggle) return;
    
    // Load saved width setting
    const savedWidth = localStorage.getItem('width');
    if (savedWidth) {
        document.body.classList.toggle('full-width', savedWidth === 'full');
        updateWidthIcon(widthToggle, savedWidth);
    }
    
    widthToggle.addEventListener('click', function() {
        const isFullWidth = document.body.classList.toggle('full-width');
        const width = isFullWidth ? 'full' : 'normal';
        
        localStorage.setItem('width', width);
        updateWidthIcon(widthToggle, width);
    });
}

function updateWidthIcon(button, width) {
    const icon = button.querySelector('i');
    if (icon) {
        icon.className = width === 'full' ? 'fas fa-compress' : 'fas fa-expand';
    }
}

// Mobile Menu Toggle
function initializeMobileMenu() {
    const menuToggle = document.querySelector('.menu-toggle');
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.querySelector('.sidebar-overlay');
    
    if (!menuToggle || !sidebar) return;
    
    menuToggle.addEventListener('click', function() {
        sidebar.classList.toggle('active');
        if (overlay) {
            overlay.classList.toggle('active');
        }
        document.body.classList.toggle('menu-open');
    });
    
    if (overlay) {
        overlay.addEventListener('click', function() {
            sidebar.classList.remove('active');
            overlay.classList.remove('active');
            document.body.classList.remove('menu-open');
        });
    }
}

// Notification System
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    notification.textContent = message;
    
    document.body.appendChild(notification);
    
    // Trigger animation
    setTimeout(() => {
        notification.classList.add('show');
    }, 100);
    
    // Remove after 3 seconds
    setTimeout(() => {
        notification.classList.remove('show');
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 300);
    }, 3000);
}

// Search functionality
function initializeSearch() {
    const searchInput = document.querySelector('.search-input');
    const searchForm = document.querySelector('.search-form');
    
    if (!searchInput || !searchForm) return;
    
    let searchTimeout;
    
    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        const query = this.value.trim();
        
        if (query.length >= 2) {
            searchTimeout = setTimeout(() => {
                // Make HTMX request for search suggestions
                htmx.ajax('GET', `/videos/search-suggestions/?q=${encodeURIComponent(query)}`, {
                    target: '.search-suggestions',
            swap: 'innerHTML'
                });
            }, 300);
        } else {
            // Clear suggestions
            const suggestions = document.querySelector('.search-suggestions');
            if (suggestions) {
                suggestions.innerHTML = '';
            }
        }
    });
    
    searchForm.addEventListener('submit', function(e) {
        const query = searchInput.value.trim();
        if (query.length < 2) {
            e.preventDefault();
            showNotification('Введите минимум 2 символа для поиска', 'warning');
        }
    });
}

// Infinite scroll for videos - only on video list pages
function initializeInfiniteScroll() {
    // Check if we're on a page that should have infinite scroll
    const currentPath = window.location.pathname;
    const isVideoListPage = (currentPath === '/videos/' || currentPath.startsWith('/videos/')) && 
                           !currentPath.match(/\/videos\/[^\/]+\/$/) && // Not video detail (has slug)
                           currentPath !== '/' && // Not home page
                           !currentPath.includes('/categories/') &&
                           !currentPath.includes('/actors/') &&
                           !currentPath.includes('/tags/');
    
    // Only initialize infinite scroll on appropriate pages
    if (!isVideoListPage) {
        console.log('Infinite scroll not needed on this page');
        return;
    }
    
    let loading = false;
    let page = 2;
    let hasMoreContent = true;
    
    function handleScroll() {
        if (loading || !hasMoreContent) return;
        
        if ((window.innerHeight + window.scrollY) >= document.body.offsetHeight - 1000) {
            loading = true;
            
            htmx.ajax('GET', `/load-more/?page=${page}`, {
                target: '.video-grid',
                swap: 'beforeend'
            }).then((response) => {
                // Check if response contains video cards
                const tempDiv = document.createElement('div');
                tempDiv.innerHTML = response;
                const videoCards = tempDiv.querySelectorAll('.video-card');
                
                if (videoCards.length > 0) {
                    page++;
                    // Reinitialize video previews for new content
                    initializeVideoPreviews();
                } else {
                    // No more content, stop infinite scroll
                    console.log('No more videos to load');
                    hasMoreContent = false;
                }
                loading = false;
            }).catch(() => {
                loading = false;
            });
        }
    }
    
    window.addEventListener('scroll', handleScroll);
}

// Initialize all functionality when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    initializeSearch();
    initializeInfiniteScroll();
    initializeSidebarExpand();
    initializeVideoPreviews();
});

        // Sidebar Expand Functionality
        function initializeSidebarExpand() {
            const toggleButton = document.querySelector('.toggle-button');
            if (!toggleButton) return;
            
            const hiddenCategories = document.querySelectorAll('.sidebar-category.hidden');
            let isExpanded = false;
            
            toggleButton.addEventListener('click', function() {
                isExpanded = !isExpanded;
                
                hiddenCategories.forEach(category => {
                    if (isExpanded) {
                        category.classList.remove('hidden');
                        category.classList.add('visible');
    } else {
                        category.classList.remove('visible');
                        category.classList.add('hidden');
                    }
                });
                
                // Update button text
                toggleButton.textContent = isExpanded ? 'Collapse' : 'Expand';
            });
        }

        // Video Preview on Hover
        function initializeVideoPreviews() {
            const videoCards = document.querySelectorAll('.video-card');
            
            videoCards.forEach(card => {
                const thumbnail = card.querySelector('.thumbnail');
                const poster = card.querySelector('.video-poster');
                const preview = card.querySelector('.video-preview');
                
                if (!thumbnail || !poster || !preview) return;
                
                let hoverTimeout;
                let isPlaying = false;
                
                thumbnail.addEventListener('mouseenter', function() {
                    hoverTimeout = setTimeout(() => {
                        if (!isPlaying) {
                            // Hide poster and show preview
                            poster.style.opacity = '0';
                            preview.style.display = 'block';
                            preview.style.opacity = '1';
                            
                            // Play preview
                            preview.play().then(() => {
                                isPlaying = true;
                            }).catch(error => {
                                console.log('Preview play failed:', error);
                            });
                        }
                    }, 300); // 300ms delay before starting preview
                });
                
                thumbnail.addEventListener('mouseleave', function() {
                    clearTimeout(hoverTimeout);
                    
                    if (isPlaying) {
                        // Pause preview
                        preview.pause();
                        preview.currentTime = 0;
                        isPlaying = false;
                        
                        // Show poster and hide preview
                        preview.style.opacity = '0';
                        preview.style.display = 'none';
                        poster.style.opacity = '1';
                    }
                });
                
                // Handle video end
                preview.addEventListener('ended', function() {
                    this.currentTime = 0;
                    this.play();
                });
            });
        }

// Export functions for global access
window.VideoPortal = {
    showNotification,
    toggleVideoLike,
    toggleCommentLike
};
// Playlist functionality
function togglePlaylistDropdown(button) {
    const menu = button.parentElement.querySelector('.playlist-menu');
    const isVisible = menu.classList.contains('show');
    
    // Close all other dropdowns
    document.querySelectorAll('.playlist-menu.show').forEach(m => {
        m.classList.remove('show');
    });
    
    if (!isVisible) {
        menu.classList.add('show');
    }
}

// Close dropdown when clicking outside
document.addEventListener('click', function(event) {
    if (!event.target.closest('.playlist-dropdown')) {
        document.querySelectorAll('.playlist-menu.show').forEach(menu => {
            menu.classList.remove('show');
        });
    }
});

// Load playlists function
function loadPlaylists(element) {
    const playlistList = element.closest('.playlist-list');
    
    // Show loading state
    playlistList.innerHTML = '<div class="playlist-item"><i class="fas fa-spinner fa-spin"></i> Загрузка...</div>';
    
    // Make HTMX request to load playlists
    htmx.ajax('GET', '/get-playlists/', {
        target: playlistList,
        swap: 'innerHTML'
    }).catch(() => {
        playlistList.innerHTML = '<div class="playlist-item">Ошибка загрузки плейлистов</div>';
    });
}

// Add to playlist function
function addToPlaylist(playlistId, element) {
    // Get video ID from the video card
    const videoCard = element.closest('.video-card');
    const videoId = videoCard.dataset.videoId || videoCard.querySelector('[data-preview-id]')?.dataset.previewId;
    
    if (!videoId) {
        showNotification('Ошибка: не удалось определить ID видео', 'error');
        return;
    }
    
    // Make HTMX request to add video to playlist
    htmx.ajax('POST', `/add-to-playlist/${playlistId}/${videoId}/`, {
        headers: {
            'X-CSRFToken': document.querySelector('[name=csrfmiddlewaretoken]')?.value || 
                          document.querySelector('meta[name=csrf-token]')?.content
        },
        target: '#notification-area',
        swap: 'innerHTML'
    }).then(() => {
        // Close dropdown
        const dropdown = element.closest('.playlist-dropdown');
        const menu = dropdown.querySelector('.playlist-menu');
        menu.classList.remove('show');
    });
}

// HTMX event handlers for playlists
document.body.addEventListener('htmx:afterRequest', function(event) {
    if (event.detail.xhr.status === 200) {
        // Check if response is JSON by looking at content-type or trying to parse
        const contentType = event.detail.xhr.getResponseHeader('Content-Type');
        
        // Only try to parse JSON if content-type indicates JSON or if it's a playlist operation
        if (contentType && contentType.includes('application/json') || 
            event.detail.pathInfo.requestPath.includes('playlist') ||
            event.detail.pathInfo.requestPath.includes('watch-later')) {
            
            try {
                const response = JSON.parse(event.detail.xhr.responseText);
                
                if (response.status === 'success') {
                    // Show success message
                    showNotification(response.message, 'success');
                    
                    // Close modal if it's a playlist creation
                    if (event.detail.pathInfo.requestPath.includes('create-playlist')) {
                        hideCreatePlaylistModal();
                        // Reload page to show new playlist
                        setTimeout(() => {
                            window.location.reload();
                        }, 1000);
                    }
                } else if (response.status === 'error') {
                    showNotification(response.error, 'error');
                }
            } catch (e) {
                // If JSON parsing fails, it's probably HTML response - ignore
                console.log('Response is not JSON, skipping JSON processing');
            }
        }
    }
});

// Watch Later toggle handler
document.body.addEventListener('htmx:afterRequest', function(event) {
    if (event.detail.pathInfo.requestPath.includes('toggle-watch-later')) {
        try {
            const response = JSON.parse(event.detail.xhr.responseText);
            
            if (response.status) {
                // Replace button HTML if provided
                if (response.html) {
                    const button = event.target;
                    button.outerHTML = response.html;
                }
                
                // Show notification
                showNotification(response.message, 'success');
            }
        } catch (e) {
            console.log('Watch later response is not JSON, skipping processing');
        }
    }
});

// Notification system
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.textContent = message;
    
    // Add styles
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 1rem 1.5rem;
        border-radius: 4px;
        color: white;
        font-weight: 500;
        z-index: 10000;
        animation: slideIn 0.3s ease;
    `;
    
    // Set background color based on type
    switch(type) {
        case 'success':
            notification.style.backgroundColor = '#4CAF50';
            break;
        case 'error':
            notification.style.backgroundColor = '#f44336';
            break;
        case 'warning':
            notification.style.backgroundColor = '#FF9800';
            break;
        default:
            notification.style.backgroundColor = '#2196F3';
    }
    
    document.body.appendChild(notification);
    
    // Remove after 3 seconds
    setTimeout(() => {
        notification.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => {
                document.body.removeChild(notification);
        }, 300);
    }, 3000);
}

// Add CSS animations
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    
    @keyframes slideOut {
        from { transform: translateX(0); opacity: 1; }
        to { transform: translateX(100%); opacity: 0; }
    }
`;
document.head.appendChild(style);
