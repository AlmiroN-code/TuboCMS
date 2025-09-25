"""
Core models and mixins for the Video Portal.
"""
from django.db import models


class TimeStampedModel(models.Model):
    """Abstract base class with self-updating created and modified fields."""
    
    created_at = models.DateTimeField(auto_now_add=True, verbose_name='Создано')
    updated_at = models.DateTimeField(auto_now=True, verbose_name='Обновлено')
    
    class Meta:
        abstract = True


class SlugModel(models.Model):
    """Abstract base class with slug field."""
    
    slug = models.SlugField(max_length=255, unique=True, verbose_name='URL-адрес')
    
    class Meta:
        abstract = True


class PublishableModel(models.Model):
    """Abstract base class for publishable content."""
    
    is_published = models.BooleanField(default=True, verbose_name='Опубликовано')
    published_at = models.DateTimeField(null=True, blank=True, verbose_name='Дата публикации')
    
    class Meta:
        abstract = True


class ViewableModel(models.Model):
    """Abstract base class for content with view count."""
    
    view_count = models.PositiveIntegerField(default=0, verbose_name='Количество просмотров')
    
    class Meta:
        abstract = True
    
    def increment_view_count(self):
        """Increment view count."""
        self.view_count += 1
        self.save(update_fields=['view_count'])


class RatingModel(models.Model):
    """Abstract base class for content with rating system."""
    
    like_count = models.PositiveIntegerField(default=0, verbose_name='Лайки')
    dislike_count = models.PositiveIntegerField(default=0, verbose_name='Дизлайки')
    
    class Meta:
        abstract = True
    
    @property
    def total_rating(self) -> int:
        """Total rating (likes - dislikes)."""
        return self.like_count - self.dislike_count
    
    @property
    def rating_percentage(self) -> float:
        """Rating percentage (0-100)."""
        total = self.like_count + self.dislike_count
        if total == 0:
            return 50.0  # Neutral rating
        return (self.like_count / total) * 100
    
    def get_rating_emoji(self) -> str:
        """Get rating emoji based on percentage."""
        percentage = self.rating_percentage
        if percentage >= 80:
            return '😊'  # Positive
        elif percentage >= 40:
            return '😐'  # Neutral
        else:
            return '😞'  # Negative
