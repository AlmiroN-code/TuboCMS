"""
Celery tasks for video processing.
"""
import os
import subprocess
import logging
from pathlib import Path
from django.conf import settings
from django.core.files import File
from celery import shared_task
from .models import Video

logger = logging.getLogger(__name__)


def get_processing_settings():
    """Get active video processing settings."""
    # Return default settings
    class ProcessingSettings:
        def __init__(self):
            self.poster_width = 250
            self.poster_height = 150
            self.poster_quality = 2
            self.preview_width = 250
            self.preview_height = 150
            self.preview_duration = 12
            self.preview_segment_duration = 2
            self.preview_crf = 28
            self.preview_preset = 'fast'
    
    return ProcessingSettings()


@shared_task(bind=True)
def process_video(self, video_id: int) -> str:
    """
    Process uploaded video file.
    
    Args:
        video_id: ID of the video to process
        
    Returns:
        str: Processing result message
    """
    try:
        logger.info(f"Starting video processing for video ID: {video_id}")
        
        video = Video.objects.get(id=video_id)
        video.is_processing = True
        video.processing_status = 'processing'
        video.processing_progress = 0
        video.processing_message = 'Начало обработки'
        video.celery_task_id = self.request.id
        video.save(update_fields=['is_processing', 'processing_status', 'processing_progress', 'processing_message', 'celery_task_id'])
        
        # Get video file path
        video_path = video.video_file.path
        logger.info(f"Processing video file: {video_path}")
        
        # Extract video information
        video.processing_message = 'Извлечение информации о видео'
        video.processing_progress = 10
        video.save(update_fields=['processing_message', 'processing_progress'])
        self.update_state(state='PROGRESS', meta={'current': 10, 'total': 100, 'status': 'Extracting video info'})
        
        # Add delay for demonstration
        import time
        time.sleep(2)
        
        duration, file_size = extract_video_info(video_path)
        video.duration = duration
        video.file_size = file_size
        logger.info(f"Video info extracted - Duration: {duration}s, Size: {file_size} bytes")
        
        # Update task progress
        video.processing_message = 'Создание постера'
        video.processing_progress = 30
        video.save(update_fields=['processing_message', 'processing_progress'])
        self.update_state(state='PROGRESS', meta={'current': 30, 'total': 100, 'status': 'Generating poster'})
        
        # Add delay for demonstration
        time.sleep(2)
        
        # Get processing settings
        processing_settings = get_processing_settings()
        
        # Generate poster
        poster_path = generate_poster(video_path, video_id, processing_settings)
        if poster_path and os.path.exists(poster_path):
            with open(poster_path, 'rb') as f:
                video.poster.save(
                    f'post_{video_id:08x}.jpg',
                    File(f),
                    save=True
                )
            logger.info(f"Poster generated: {poster_path}")
        else:
            logger.warning(f"Failed to generate poster for video {video_id}")
        
        # Update task progress
        video.processing_message = 'Создание превью видео'
        video.processing_progress = 60
        video.save(update_fields=['processing_message', 'processing_progress'])
        self.update_state(state='PROGRESS', meta={'current': 60, 'total': 100, 'status': 'Generating preview video'})
        
        # Add delay for demonstration
        time.sleep(2)
        
        # Generate preview video
        preview_path = generate_preview_video(video_path, video_id, processing_settings)
        if preview_path and os.path.exists(preview_path):
            with open(preview_path, 'rb') as f:
                video.preview_video.save(
                    f'preview_{video_id:08x}.mp4',
                    File(f),
                    save=True
                )
            logger.info(f"Preview video generated: {preview_path}")
        else:
            logger.warning(f"Failed to generate preview video for video {video_id}")
        
        # Update task progress
        video.processing_message = 'Завершение обработки'
        video.processing_progress = 90
        video.save(update_fields=['processing_message', 'processing_progress'])
        self.update_state(state='PROGRESS', meta={'current': 90, 'total': 100, 'status': 'Finalizing'})
        
        # Update video status
        video.is_processing = False
        video.processing_status = 'completed'
        video.processing_progress = 100
        video.processing_message = 'Обработка завершена'
        video.save(update_fields=['is_processing', 'processing_status', 'processing_progress', 'processing_message'])
        
        # Clean up temporary files
        if poster_path and os.path.exists(poster_path):
            os.remove(poster_path)
        if preview_path and os.path.exists(preview_path):
            os.remove(preview_path)
        
        logger.info(f"Video {video_id} processed successfully")
        return f"Video {video_id} processed successfully"
        
    except Video.DoesNotExist:
        logger.error(f"Video {video_id} not found")
        return f"Video {video_id} not found"
    except Exception as e:
        logger.error(f"Error processing video {video_id}: {str(e)}")
        # Update video status to failed
        try:
            video = Video.objects.get(id=video_id)
            video.is_processing = False
            video.processing_status = 'failed'
            video.processing_message = f'Ошибка: {str(e)}'
            video.save(update_fields=['is_processing', 'processing_status', 'processing_message'])
        except Video.DoesNotExist:
            pass
        
        return f"Error processing video {video_id}: {str(e)}"


def extract_video_info(video_path: str) -> tuple:
    """
    Extract video duration and file size using ffprobe.
    
    Args:
        video_path: Path to the video file
        
    Returns:
        tuple: (duration in seconds, file size in bytes)
    """
    try:
        # Get file size
        file_size = os.path.getsize(video_path)
        logger.info(f"Video file size: {file_size} bytes")
        
        # Get duration using ffprobe
        cmd = [
            settings.FFPROBE_PATH,
            '-v', 'quiet',
            '-show_entries', 'format=duration',
            '-of', 'csv=p=0',
            video_path
        ]
        
        logger.info(f"Running ffprobe command: {' '.join(cmd)}")
        result = subprocess.run(cmd, capture_output=True, text=True, check=True)
        
        duration_str = result.stdout.strip()
        if duration_str:
            duration = int(float(duration_str))
            logger.info(f"Video duration: {duration} seconds")
        else:
            logger.warning("No duration found in ffprobe output")
            duration = 0
        
        return duration, file_size
        
    except subprocess.CalledProcessError as e:
        logger.error(f"FFprobe error extracting video info: {e.stderr}")
        return 0, 0
    except Exception as e:
        logger.error(f"Error extracting video info: {e}")
        return 0, 0


def generate_poster(video_path: str, video_id: int, processing_settings=None):
    """
    Generate poster from video using ffmpeg.
    Creates a poster from the middle of the video with specified dimensions.
    
    Args:
        video_path: Path to the video file
        video_id: Video ID for unique filename
        processing_settings: VideoProcessingSettings instance
        
    Returns:
        str: Path to generated poster or None if failed
    """
    try:
        # Use provided settings or get default
        if processing_settings is None:
            processing_settings = get_processing_settings()
        
        # Create posters directory
        posters_dir = Path(settings.MEDIA_ROOT) / 'posters'
        posters_dir.mkdir(exist_ok=True)
        
        # Generate poster filename
        poster_filename = f"post_{video_id:08x}.jpg"
        poster_path = posters_dir / poster_filename
        
        # Get video duration first to extract frame from middle
        duration, _ = extract_video_info(video_path)
        if duration == 0:
            logger.warning(f"Video duration is 0, using 5 seconds as middle time")
            middle_time = 5
        else:
            middle_time = max(5, duration // 2)
        
        logger.info(f"Extracting poster from {middle_time}s of video {video_id}")
        
        # Extract frame from middle of video with specific dimensions
        cmd = [
            settings.FFMPEG_PATH,
            '-i', video_path,
            '-ss', str(middle_time),
            '-vframes', '1',
            '-vf', f'scale={processing_settings.poster_width}:{processing_settings.poster_height}:force_original_aspect_ratio=decrease,pad={processing_settings.poster_width}:{processing_settings.poster_height}:(ow-iw)/2:(oh-ih)/2:black',
            '-q:v', str(processing_settings.poster_quality),
            '-y',  # Overwrite output file
            str(poster_path)
        ]
        
        result = subprocess.run(cmd, capture_output=True, text=True, check=True)
        logger.info(f"Poster generation command output: {result.stdout}")
        
        # Verify the poster was created and has correct dimensions
        if os.path.exists(poster_path):
            # Check file size (should be > 0)
            if os.path.getsize(poster_path) > 0:
                logger.info(f"Poster generated successfully: {poster_path}")
                return str(poster_path)
            else:
                logger.error(f"Generated poster file is empty: {poster_path}")
                return None
        else:
            logger.error(f"Poster file was not created: {poster_path}")
            return None
        
    except subprocess.CalledProcessError as e:
        logger.error(f"FFmpeg error generating poster: {e.stderr}")
        return None
    except Exception as e:
        logger.error(f"Error generating poster: {e}")
        return None


def generate_preview_video(video_path: str, video_id: int, processing_settings=None):
    """
    Generate preview video using ffmpeg.
    Creates a preview with specified duration and segments, resized to specified dimensions.
    
    Args:
        video_path: Path to the video file
        video_id: Video ID for unique filename
        processing_settings: VideoProcessingSettings instance
        
    Returns:
        str: Path to generated preview or None if failed
    """
    try:
        # Use provided settings or get default
        if processing_settings is None:
            processing_settings = get_processing_settings()
        
        # Create previews directory
        previews_dir = Path(settings.MEDIA_ROOT) / 'previews'
        previews_dir.mkdir(exist_ok=True)
        
        # Generate preview filename
        preview_filename = f"preview_{video_id:08x}.mp4"
        preview_path = previews_dir / preview_filename
        
        # Get video duration first
        duration, _ = extract_video_info(video_path)
        if duration == 0:
            logger.warning(f"Video duration is 0, cannot generate preview")
            return None
        
        logger.info(f"Generating preview for video {video_id} with duration {duration}s")
        
        # Calculate segments for preview
        segment_duration = processing_settings.preview_segment_duration
        total_duration = processing_settings.preview_duration
        segment_count = total_duration // segment_duration
        
        # If video is shorter than total duration, use the whole video
        if duration < total_duration:
            logger.info(f"Video is shorter than {total_duration}s ({duration}s), using whole video for preview")
            cmd = [
                settings.FFMPEG_PATH,
                '-i', video_path,
                '-vf', f'scale={processing_settings.preview_width}:{processing_settings.preview_height}:force_original_aspect_ratio=decrease,pad={processing_settings.preview_width}:{processing_settings.preview_height}:(ow-iw)/2:(oh-ih)/2:black',
                '-c:v', 'libx264',
                '-preset', processing_settings.preview_preset,
                '-crf', str(processing_settings.preview_crf),
                '-an',  # No audio
                '-y',  # Overwrite output file
                str(preview_path)
            ]
        else:
            # Create filter for extracting segments
            filter_parts = []
            for i in range(segment_count):
                start_time = i * (duration // segment_count)
                filter_parts.append(f'[0:v]trim=start={start_time}:duration={segment_duration},setpts=PTS-STARTPTS[v{i}]')
            
            # Concatenate segments
            concat_filter = ''.join([f'[v{i}]' for i in range(segment_count)]) + f'concat=n={segment_count}:v=1:a=0[out]'
            
            # Generate preview with segments
            cmd = [
                settings.FFMPEG_PATH,
                '-i', video_path,
                '-filter_complex', ';'.join(filter_parts) + ';' + concat_filter + f';[out]scale={processing_settings.preview_width}:{processing_settings.preview_height}:force_original_aspect_ratio=decrease,pad={processing_settings.preview_width}:{processing_settings.preview_height}:(ow-iw)/2:(oh-ih)/2:black[final]',
                '-map', '[final]',
                '-c:v', 'libx264',
                '-preset', processing_settings.preview_preset,
                '-crf', str(processing_settings.preview_crf),
                '-an',  # No audio
                '-y',  # Overwrite output file
                str(preview_path)
            ]
        
        logger.info(f"Running FFmpeg command: {' '.join(cmd)}")
        result = subprocess.run(cmd, capture_output=True, text=True, check=True)
        logger.info(f"Preview generation command output: {result.stdout}")
        
        # Verify the preview was created
        if os.path.exists(preview_path):
            if os.path.getsize(preview_path) > 0:
                logger.info(f"Preview video generated successfully: {preview_path}")
                return str(preview_path)
            else:
                logger.error(f"Generated preview file is empty: {preview_path}")
                return None
        else:
            logger.error(f"Preview file was not created: {preview_path}")
            return None
        
    except subprocess.CalledProcessError as e:
        logger.error(f"FFmpeg error generating preview: {e.stderr}")
        return None
    except Exception as e:
        logger.error(f"Error generating preview video: {e}")
        return None
