// Video preview hover functionality
function initializeVideoPreview() {
    const videoCards = document.querySelectorAll('.video-card');
    
    videoCards.forEach((card, index) => {
        // Skip if already initialized
        if (card.dataset.videoPreviewInitialized) {
            return;
        }
        
        const previewVideo = card.querySelector('.preview-video');
        const posterImage = card.querySelector('.poster-image');
        const progressBar = card.querySelector('.preview-progress-bar');
        const progressFill = card.querySelector('.progress-fill');
        
        if (!previewVideo) {
            return;
        }
        
        // Mark as initialized
        card.dataset.videoPreviewInitialized = 'true';
        
        let isPlaying = false;
        let isLoaded = false;
        let progressInterval = null;
        let hoverTimeout = null;
        
        // Show progress bar and start loading animation
        function startProgressAnimation() {
            if (progressBar && progressFill) {
                progressBar.style.opacity = '1';
                progressFill.style.width = '0%';
                
                // Animate progress bar over 250ms
                let progress = 0;
                progressInterval = setInterval(() => {
                    progress += 8; // 8% every 20ms = 250ms total
                    if (progress >= 100) {
                        progress = 100;
                        clearInterval(progressInterval);
                        progressInterval = null;
                    }
                    progressFill.style.width = progress + '%';
                }, 20);
            }
        }
        
        // Hide progress bar
        function hideProgressBar() {
            if (progressBar) {
                progressBar.style.opacity = '0';
            }
            if (progressInterval) {
                clearInterval(progressInterval);
                progressInterval = null;
            }
            if (progressFill) {
                progressFill.style.width = '0%';
            }
        }
        
        // Load video on first hover
        card.addEventListener('mouseenter', function() {
            // Clear any existing timeout
            if (hoverTimeout) {
                clearTimeout(hoverTimeout);
            }
            
            // Start progress animation immediately
            startProgressAnimation();
            
            // Load video if not loaded yet
            if (!isLoaded && previewVideo.dataset.lazyVideo) {
                previewVideo.src = previewVideo.dataset.lazyVideo;
                isLoaded = true;
            }
            
            // Wait 250ms before starting video
            hoverTimeout = setTimeout(() => {
                if (previewVideo && !isPlaying) {
                    previewVideo.currentTime = 0;
                    
                    // Show video element (poster stays visible underneath)
                    previewVideo.style.opacity = '1';
                    
                    // Hide progress bar when video starts
                    hideProgressBar();
                    
                    // Try to play the video
                    previewVideo.play().then(() => {
                        isPlaying = true;
                    }).catch(e => {
                        // Autoplay prevented, try again
                        setTimeout(() => {
                            previewVideo.play().then(() => {
                                isPlaying = true;
                            }).catch(() => {});
                        }, 100);
                    });
                }
            }, 500);
        });
        
        card.addEventListener('mouseleave', function() {
            // Clear timeout if mouse leaves before video starts
            if (hoverTimeout) {
                clearTimeout(hoverTimeout);
                hoverTimeout = null;
            }
            
            // Hide progress bar
            hideProgressBar();
            
            if (previewVideo) {
                previewVideo.pause();
                previewVideo.currentTime = 0;
                isPlaying = false;
                
                // Hide video element
                previewVideo.style.opacity = '0';
            }
        });
    });
}

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', initializeVideoPreview);

// Re-initialize on Turbo navigation
document.addEventListener('turbo:load', initializeVideoPreview);
document.addEventListener('turbo:render', initializeVideoPreview);
