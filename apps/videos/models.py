"""
Video models for the Video Portal.
"""
import os
from django.db import models
from django.utils import timezone
from django.core.validators import FileExtensionValidator
from apps.core.models import TimeStampedModel, SlugModel, PublishableModel, ViewableModel, RatingModel
from apps.core.managers import PublishedManager, PopularManager, RecentManager, TrendingManager


class Category(TimeStampedModel, SlugModel):
    """Video category model."""
    
    name = models.CharField(max_length=255, verbose_name='Название')
    description = models.TextField(blank=True, verbose_name='Описание')
    thumbnail = models.ImageField(upload_to='categories/', blank=True, null=True, verbose_name='Обложка')
    is_active = models.BooleanField(default=True, verbose_name='Активна')
    sort_order = models.PositiveIntegerField(default=0, verbose_name='Порядок сортировки')
    
    # Managers
    objects = models.Manager()
    active = models.Manager()
    
    class Meta:
        verbose_name = 'Категория'
        verbose_name_plural = 'Категории'
        ordering = ['sort_order', 'name']
    
    def __str__(self):
        return self.name
    
    @property
    def video_count(self) -> int:
        """Get count of published videos in this category."""
        return self.videos.filter(is_published=True).count()


class Tag(TimeStampedModel, SlugModel):
    """Video tag model."""
    
    name = models.CharField(max_length=100, verbose_name='Название')
    color = models.CharField(max_length=7, default='#8AA398', verbose_name='Цвет')
    
    class Meta:
        verbose_name = 'Тег'
        verbose_name_plural = 'Теги'
        ordering = ['name']
    
    def __str__(self):
        return self.name


class Actor(TimeStampedModel, SlugModel):
    """Actor model."""
    
    name = models.CharField(max_length=255, verbose_name='Имя')
    avatar = models.ImageField(upload_to='actors/', blank=True, null=True, verbose_name='Аватар')
    bio = models.TextField(blank=True, verbose_name='Биография')
    birth_date = models.DateField(null=True, blank=True, verbose_name='Дата рождения')
    nationality = models.CharField(max_length=100, blank=True, verbose_name='Национальность')
    is_active = models.BooleanField(default=True, verbose_name='Активен')
    
    class Meta:
        verbose_name = 'Актер'
        verbose_name_plural = 'Актеры'
        ordering = ['name']
    
    def __str__(self):
        return self.name
    
    @property
    def video_count(self) -> int:
        """Get count of published videos with this actor."""
        return self.videos.filter(is_published=True).count()


class Video(TimeStampedModel, SlugModel, PublishableModel, ViewableModel, RatingModel):
    """Main video model."""
    
    title = models.CharField(max_length=255, verbose_name='Название')
    description = models.TextField(blank=True, verbose_name='Описание')
    video_file = models.FileField(
        upload_to='videos/',
        validators=[FileExtensionValidator(allowed_extensions=['mp4', 'avi', 'mov', 'wmv', 'flv', 'webm'])],
        verbose_name='Видео файл'
    )
    poster = models.ImageField(upload_to='posters/', blank=True, null=True, verbose_name='Постер')
    preview_video = models.FileField(upload_to='previews/', blank=True, null=True, verbose_name='Превью видео')
    duration = models.PositiveIntegerField(default=0, verbose_name='Длительность (секунды)')
    file_size = models.PositiveBigIntegerField(default=0, verbose_name='Размер файла (байты)')
    
    # Relationships
    category = models.ForeignKey(Category, on_delete=models.SET_NULL, null=True, related_name='videos', verbose_name='Категория')
    tags = models.ManyToManyField(Tag, blank=True, related_name='videos', verbose_name='Теги')
    actors = models.ManyToManyField(Actor, blank=True, related_name='videos', verbose_name='Актеры')
    author = models.ForeignKey('accounts.User', on_delete=models.CASCADE, related_name='videos', verbose_name='Автор')
    
    # Processing status
    is_processing = models.BooleanField(default=False, verbose_name='Обрабатывается')
    processing_status = models.CharField(
        max_length=50,
        choices=[
            ('pending', 'Ожидает'),
            ('processing', 'Обрабатывается'),
            ('completed', 'Завершено'),
            ('failed', 'Ошибка'),
        ],
        default='pending',
        verbose_name='Статус обработки'
    )
    
    # Managers
    objects = models.Manager()
    published = PublishedManager()
    popular = PopularManager()
    recent = RecentManager()
    trending = TrendingManager()
    
    class Meta:
        verbose_name = 'Видео'
        verbose_name_plural = 'Видео'
        ordering = ['-created_at']
    
    def __str__(self):
        return self.title
    
    def save(self, *args, **kwargs):
        """Override save to set published_at."""
        if self.is_published and not self.published_at:
            self.published_at = timezone.now()
        super().save(*args, **kwargs)
    
    @property
    def duration_formatted(self) -> str:
        """Get formatted duration string."""
        hours, remainder = divmod(self.duration, 3600)
        minutes, seconds = divmod(remainder, 60)
        
        if hours > 0:
            return f"{hours:02d}:{minutes:02d}:{seconds:02d}"
        return f"{minutes:02d}:{seconds:02d}"
    
    @property
    def file_size_formatted(self) -> str:
        """Get formatted file size string."""
        for unit in ['B', 'KB', 'MB', 'GB']:
            if self.file_size < 1024.0:
                return f"{self.file_size:.1f} {unit}"
            self.file_size /= 1024.0
        return f"{self.file_size:.1f} TB"
    
    @property
    def thumbnail_url(self) -> str:
        """Get thumbnail URL."""
        if self.poster:
            return self.poster.url
        return '/static/img/default-poster.jpg'


class VideoLike(TimeStampedModel):
    """Video like/dislike model."""
    
    user = models.ForeignKey('accounts.User', on_delete=models.CASCADE, related_name='video_likes')
    video = models.ForeignKey(Video, on_delete=models.CASCADE, related_name='likes')
    is_like = models.BooleanField(verbose_name='Лайк')
    
    class Meta:
        verbose_name = 'Лайк видео'
        verbose_name_plural = 'Лайки видео'
        unique_together = ['user', 'video']
    
    def __str__(self):
        action = 'liked' if self.is_like else 'disliked'
        return f"{self.user.username} {action} {self.video.title}"


class VideoView(TimeStampedModel):
    """Video view tracking model."""
    
    user = models.ForeignKey('accounts.User', on_delete=models.CASCADE, related_name='video_views', null=True, blank=True)
    video = models.ForeignKey(Video, on_delete=models.CASCADE, related_name='views')
    ip_address = models.GenericIPAddressField(verbose_name='IP адрес')
    user_agent = models.TextField(blank=True, verbose_name='User Agent')
    referrer = models.URLField(blank=True, verbose_name='Реферер')
    
    class Meta:
        verbose_name = 'Просмотр видео'
        verbose_name_plural = 'Просмотры видео'
    
    def __str__(self):
        user = self.user.username if self.user else 'Anonymous'
        return f"{user} viewed {self.video.title}"


class SiteSettings(TimeStampedModel):
    """Site settings model."""
    
    site_name = models.CharField(
        max_length=100, 
        default='Моя домашняя страница', 
        verbose_name='Название сайта'
    )
    site_description = models.CharField(
        max_length=200, 
        default='Видео портал', 
        verbose_name='Описание сайта'
    )
    site_keywords = models.CharField(
        max_length=500, 
        default='видео, портал, развлечения', 
        verbose_name='Ключевые слова'
    )
    site_short_name = models.CharField(
        max_length=50, 
        default='МДС', 
        verbose_name='Краткое название сайта'
    )
    videos_per_page = models.PositiveIntegerField(
        default=12, 
        verbose_name='Количество видео на страницу'
    )
    videos_per_page_categories = models.PositiveIntegerField(
        default=12, 
        verbose_name='Количество видео в категориях'
    )
    popular_videos_count = models.PositiveIntegerField(
        default=8, 
        verbose_name='Количество популярных видео'
    )
    new_videos_count = models.PositiveIntegerField(
        default=8, 
        verbose_name='Количество новых видео'
    )
    random_videos_count = models.PositiveIntegerField(
        default=8, 
        verbose_name='Количество случайных видео'
    )
    similar_videos_count = models.PositiveIntegerField(
        default=6, 
        verbose_name='Количество похожих видео'
    )
    enable_caching = models.BooleanField(
        default=True, 
        verbose_name='Включить кеширование'
    )
    is_active = models.BooleanField(
        default=True, 
        verbose_name='Активен'
    )
    
    class Meta:
        verbose_name = 'Настройки сайта'
        verbose_name_plural = 'Настройки сайта'
    
    def __str__(self):
        return f"Настройки: {self.site_name}"
    
    @classmethod
    def get_active_settings(cls):
        """Get active site settings or create default ones."""
        try:
            return cls.objects.filter(is_active=True).first() or cls.objects.create()
        except cls.DoesNotExist:
            return cls.objects.create()
    
    def save(self, *args, **kwargs):
        """Override save to ensure only one active settings."""
        if self.is_active:
            # Deactivate all other settings
            SiteSettings.objects.filter(is_active=True).exclude(pk=self.pk).update(is_active=False)
        super().save(*args, **kwargs)


class VideoProcessingSettings(TimeStampedModel):
    """Video processing settings model."""
    
    poster_width = models.PositiveIntegerField(
        default=250, 
        verbose_name='Ширина постера'
    )
    poster_height = models.PositiveIntegerField(
        default=150, 
        verbose_name='Высота постера'
    )
    poster_quality = models.PositiveIntegerField(
        default=2, 
        verbose_name='Качество постера (1-31)'
    )
    preview_width = models.PositiveIntegerField(
        default=250, 
        verbose_name='Ширина превью'
    )
    preview_height = models.PositiveIntegerField(
        default=150, 
        verbose_name='Высота превью'
    )
    preview_duration = models.PositiveIntegerField(
        default=12, 
        verbose_name='Длительность превью (секунды)'
    )
    preview_segment_duration = models.PositiveIntegerField(
        default=2, 
        verbose_name='Длительность сегмента (секунды)'
    )
    preview_crf = models.PositiveIntegerField(
        default=28, 
        verbose_name='CRF для превью (0-51)'
    )
    preview_preset = models.CharField(
        max_length=20,
        choices=[
            ('ultrafast', 'Ultrafast'),
            ('superfast', 'Superfast'),
            ('veryfast', 'Veryfast'),
            ('faster', 'Faster'),
            ('fast', 'Fast'),
            ('medium', 'Medium'),
            ('slow', 'Slow'),
            ('slower', 'Slower'),
            ('veryslow', 'Veryslow'),
        ],
        default='fast',
        verbose_name='Пресет FFmpeg'
    )
    is_active = models.BooleanField(
        default=True, 
        verbose_name='Активен'
    )
    
    class Meta:
        verbose_name = 'Настройки обработки видео'
        verbose_name_plural = 'Настройки обработки видео'
    
    def __str__(self):
        return f"Настройки обработки видео (активен: {self.is_active})"
    
    @classmethod
    def get_active_settings(cls):
        """Get active processing settings or create default ones."""
        try:
            return cls.objects.filter(is_active=True).first() or cls.objects.create()
        except cls.DoesNotExist:
            return cls.objects.create()
    
    def save(self, *args, **kwargs):
        """Override save to ensure only one active settings."""
        if self.is_active:
            # Deactivate all other settings
            VideoProcessingSettings.objects.filter(is_active=True).exclude(pk=self.pk).update(is_active=False)
        super().save(*args, **kwargs)


class PlaylistVideo(TimeStampedModel):
    """Playlist video model."""
    
    playlist = models.ForeignKey(
        'accounts.Playlist', 
        on_delete=models.CASCADE, 
        related_name='playlist_videos',
        verbose_name='Плейлист'
    )
    video = models.ForeignKey(
        Video, 
        on_delete=models.CASCADE, 
        related_name='playlist_videos',
        verbose_name='Видео'
    )
    order = models.PositiveIntegerField(
        default=0, 
        verbose_name='Порядок'
    )
    created_at = models.DateTimeField(
        auto_now_add=True,
        verbose_name='Создано'
    )
    updated_at = models.DateTimeField(
        auto_now=True,
        verbose_name='Обновлено'
    )
    
    class Meta:
        verbose_name = 'Видео в плейлисте'
        verbose_name_plural = 'Видео в плейлистах'
        ordering = ['order', '-created_at']
        unique_together = ['playlist', 'video']
    
    def __str__(self):
        return f"{self.playlist.name} - {self.video.title}"
