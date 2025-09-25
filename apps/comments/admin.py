"""
Admin configuration for Comments app.
"""
from django.contrib import admin
from .models import Comment, CommentLike


@admin.register(Comment)
class CommentAdmin(admin.ModelAdmin):
    """Comment admin."""
    
    list_display = [
        'content_preview', 'user', 'video', 'parent', 
        'is_approved', 'is_pinned', 'like_count', 'dislike_count', 'created_at'
    ]
    list_filter = ['is_approved', 'is_pinned', 'created_at']
    search_fields = ['content', 'user__username', 'video__title']
    readonly_fields = ['like_count', 'dislike_count', 'created_at', 'updated_at']
    ordering = ['-created_at']
    
    fieldsets = (
        ('Основная информация', {
            'fields': ('user', 'video', 'content', 'parent')
        }),
        ('Модерация', {
            'fields': ('is_approved', 'is_pinned')
        }),
        ('Статистика', {
            'fields': ('like_count', 'dislike_count'),
            'classes': ('collapse',)
        }),
        ('Даты', {
            'fields': ('created_at', 'updated_at'),
            'classes': ('collapse',)
        }),
    )
    
    def content_preview(self, obj):
        """Get content preview."""
        return obj.content[:50] + '...' if len(obj.content) > 50 else obj.content
    content_preview.short_description = 'Содержание'


@admin.register(CommentLike)
class CommentLikeAdmin(admin.ModelAdmin):
    """Comment like admin."""
    
    list_display = ['user', 'comment', 'is_like', 'created_at']
    list_filter = ['is_like', 'created_at']
    search_fields = ['user__username', 'comment__content']
    ordering = ['-created_at']
