// Video preview hover functionality
function initializeVideoPreview() {
    const videoCards = document.querySelectorAll('.video-card');
    
    console.log('Video preview initialized. Found cards:', videoCards.length);
    
    videoCards.forEach((card, index) => {
        // Skip if already initialized
        if (card.dataset.videoPreviewInitialized) {
            return;
        }
        
        const previewVideo = card.querySelector('.preview-video');
        
        if (!previewVideo) {
            console.log(`Card ${index}: No preview video found`);
            return;
        }
        
        console.log(`Card ${index}: Preview video found, src:`, previewVideo.src);
        
        // Mark as initialized
        card.dataset.videoPreviewInitialized = 'true';
        
        let isPlaying = false;
        
        // Ensure video is ready
        previewVideo.addEventListener('loadedmetadata', function() {
            console.log(`Card ${index}: Video metadata loaded`);
        });
        
        // Handle video load errors
        previewVideo.addEventListener('error', function(e) {
            console.log(`Card ${index}: Video load error:`, e);
        });
        
        card.addEventListener('mouseenter', function() {
            console.log(`Card ${index}: Mouse enter`);
            
            if (previewVideo && !isPlaying) {
                previewVideo.currentTime = 0;
                
                // Try to play the video
                previewVideo.play().then(() => {
                    isPlaying = true;
                    console.log(`Card ${index}: Video started playing`);
                }).catch(e => {
                    console.log(`Card ${index}: Video autoplay prevented:`, e);
                    
                    // Try to play again after a short delay
                    setTimeout(() => {
                        previewVideo.play().then(() => {
                            isPlaying = true;
                            console.log(`Card ${index}: Video started playing after delay`);
                        }).catch(() => {
                            console.log(`Card ${index}: Video still cannot play`);
                        });
                    }, 100);
                });
            }
        });
        
        card.addEventListener('mouseleave', function() {
            console.log(`Card ${index}: Mouse leave`);
            
            if (previewVideo && isPlaying) {
                previewVideo.pause();
                previewVideo.currentTime = 0;
                isPlaying = false;
            }
        });
    });
}

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', initializeVideoPreview);

// Re-initialize on Turbo navigation
document.addEventListener('turbo:load', initializeVideoPreview);
document.addEventListener('turbo:render', initializeVideoPreview);
