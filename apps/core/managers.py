"""
Custom managers for core models.
"""
from django.db import models


class PublishedManager(models.Manager):
    """Manager for published objects."""
    
    def get_queryset(self):
        return super().get_queryset().filter(is_published=True)


class PopularManager(models.Manager):
    """Manager for popular objects based on view count."""
    
    def get_queryset(self):
        return super().get_queryset().order_by('-view_count')


class RecentManager(models.Manager):
    """Manager for recent objects."""
    
    def get_queryset(self):
        return super().get_queryset().order_by('-created_at')


class TrendingManager(models.Manager):
    """Manager for trending objects based on rating."""
    
    def get_queryset(self):
        return super().get_queryset().order_by('-like_count', '-created_at')
