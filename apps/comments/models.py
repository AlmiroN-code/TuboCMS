"""
Comment models for the Video Portal.
"""
from django.db import models
from django.utils import timezone
from apps.core.models import TimeStampedModel, RatingModel


class Comment(TimeStampedModel, RatingModel):
    """Comment model."""
    
    user = models.ForeignKey('accounts.User', on_delete=models.CASCADE, related_name='comments')
    video = models.ForeignKey('videos.Video', on_delete=models.CASCADE, related_name='comments')
    content = models.TextField(verbose_name='Содержание')
    parent = models.ForeignKey('self', on_delete=models.CASCADE, null=True, blank=True, related_name='replies')
    is_approved = models.BooleanField(default=True, verbose_name='Одобрен')
    is_pinned = models.BooleanField(default=False, verbose_name='Закреплен')
    
    class Meta:
        verbose_name = 'Комментарий'
        verbose_name_plural = 'Комментарии'
        ordering = ['-is_pinned', '-created_at']
    
    def __str__(self):
        return f"{self.user.username} - {self.content[:50]}..."
    
    @property
    def is_reply(self) -> bool:
        """Check if this is a reply to another comment."""
        return self.parent is not None
    
    @property
    def reply_count(self) -> int:
        """Get count of replies to this comment."""
        return self.replies.filter(is_approved=True).count()


class CommentLike(TimeStampedModel):
    """Comment like/dislike model."""
    
    user = models.ForeignKey('accounts.User', on_delete=models.CASCADE, related_name='comment_likes')
    comment = models.ForeignKey(Comment, on_delete=models.CASCADE, related_name='likes')
    is_like = models.BooleanField(verbose_name='Лайк')
    
    class Meta:
        verbose_name = 'Лайк комментария'
        verbose_name_plural = 'Лайки комментариев'
        unique_together = ['user', 'comment']
    
    def __str__(self):
        action = 'liked' if self.is_like else 'disliked'
        return f"{self.user.username} {action} comment {self.comment.id}"
