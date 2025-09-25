"""
User and profile models for the Video Portal.
"""
from django.contrib.auth.models import AbstractUser
from django.db import models
from django.utils import timezone
from apps.core.models import TimeStampedModel, SlugModel


class User(AbstractUser):
    """Custom user model."""
    
    email = models.EmailField(unique=True, verbose_name='Email')
    first_name = models.CharField(max_length=150, verbose_name='Имя')
    last_name = models.CharField(max_length=150, verbose_name='Фамилия')
    is_actor = models.BooleanField(default=False, verbose_name='Актер')
    is_verified = models.BooleanField(default=False, verbose_name='Верифицирован')
    
    USERNAME_FIELD = 'email'
    REQUIRED_FIELDS = ['username']
    
    class Meta:
        verbose_name = 'Пользователь'
        verbose_name_plural = 'Пользователи'
    
    def __str__(self):
        return self.username
    
    @property
    def full_name(self) -> str:
        """Get user's full name."""
        return f"{self.first_name} {self.last_name}".strip()


class Profile(TimeStampedModel):
    """User profile model."""
    
    user = models.OneToOneField(User, on_delete=models.CASCADE, related_name='profile')
    avatar = models.ImageField(upload_to='avatars/', blank=True, null=True, verbose_name='Аватар')
    bio = models.TextField(blank=True, verbose_name='О себе')
    website = models.URLField(blank=True, verbose_name='Веб-сайт')
    location = models.CharField(max_length=255, blank=True, verbose_name='Местоположение')
    birth_date = models.DateField(null=True, blank=True, verbose_name='Дата рождения')
    
    # Privacy settings
    is_public = models.BooleanField(default=True, verbose_name='Публичный профиль')
    show_email = models.BooleanField(default=False, verbose_name='Показывать email')
    show_location = models.BooleanField(default=True, verbose_name='Показывать местоположение')
    
    # Notification settings
    email_notifications = models.BooleanField(default=True, verbose_name='Email уведомления')
    comment_notifications = models.BooleanField(default=True, verbose_name='Уведомления о комментариях')
    like_notifications = models.BooleanField(default=True, verbose_name='Уведомления о лайках')
    subscription_notifications = models.BooleanField(default=True, verbose_name='Уведомления о подписках')
    
    class Meta:
        verbose_name = 'Профиль'
        verbose_name_plural = 'Профили'
    
    def __str__(self):
        return f"Profile of {self.user.username}"


class Subscription(TimeStampedModel):
    """User subscription model."""
    
    subscriber = models.ForeignKey(User, on_delete=models.CASCADE, related_name='subscriptions')
    subscribed_to = models.ForeignKey(User, on_delete=models.CASCADE, related_name='subscribers')
    
    class Meta:
        verbose_name = 'Подписка'
        verbose_name_plural = 'Подписки'
        unique_together = ['subscriber', 'subscribed_to']
    
    def __str__(self):
        return f"{self.subscriber.username} subscribes to {self.subscribed_to.username}"


class WatchLater(TimeStampedModel):
    """Watch later model."""
    
    user = models.ForeignKey(User, on_delete=models.CASCADE, related_name='watch_later')
    video = models.ForeignKey('videos.Video', on_delete=models.CASCADE, related_name='watch_later_users')
    
    class Meta:
        verbose_name = 'Посмотреть позже'
        verbose_name_plural = 'Посмотреть позже'
        unique_together = ['user', 'video']
    
    def __str__(self):
        return f"{self.user.username} - {self.video.title}"


class Playlist(TimeStampedModel, SlugModel):
    """User playlist model."""
    
    user = models.ForeignKey(User, on_delete=models.CASCADE, related_name='playlists')
    name = models.CharField(max_length=255, verbose_name='Название')
    description = models.TextField(blank=True, verbose_name='Описание')
    is_public = models.BooleanField(default=True, verbose_name='Публичный плейлист')
    thumbnail = models.ImageField(upload_to='playlists/', blank=True, null=True, verbose_name='Обложка')
    
    class Meta:
        verbose_name = 'Плейлист'
        verbose_name_plural = 'Плейлисты'
    
    def __str__(self):
        return self.name


class PlaylistVideo(TimeStampedModel):
    """Playlist video relationship model."""
    
    playlist = models.ForeignKey(Playlist, on_delete=models.CASCADE, related_name='videos')
    video = models.ForeignKey('videos.Video', on_delete=models.CASCADE, related_name='playlists')
    position = models.PositiveIntegerField(default=0, verbose_name='Позиция')
    
    class Meta:
        verbose_name = 'Видео в плейлисте'
        verbose_name_plural = 'Видео в плейлистах'
        unique_together = ['playlist', 'video']
        ordering = ['position']
    
    def __str__(self):
        return f"{self.playlist.name} - {self.video.title}"


class ViewHistory(TimeStampedModel):
    """User view history model."""
    
    user = models.ForeignKey(User, on_delete=models.CASCADE, related_name='view_history')
    video = models.ForeignKey('videos.Video', on_delete=models.CASCADE, related_name='view_history')
    watched_duration = models.PositiveIntegerField(default=0, verbose_name='Просмотрено секунд')
    
    class Meta:
        verbose_name = 'История просмотров'
        verbose_name_plural = 'История просмотров'
        unique_together = ['user', 'video']
        ordering = ['-created_at']
    
    def __str__(self):
        return f"{self.user.username} - {self.video.title}"
