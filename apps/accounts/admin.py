"""
Admin configuration for Accounts app.
"""
from django.contrib import admin
from django.contrib.auth.admin import UserAdmin as BaseUserAdmin
from django.utils.html import format_html
from .models import User, Profile, Subscription, WatchLater, Playlist, PlaylistVideo, ViewHistory


class ProfileInline(admin.StackedInline):
    """Profile inline admin."""
    
    model = Profile
    can_delete = False
    verbose_name_plural = 'Профиль'


@admin.register(User)
class UserAdmin(BaseUserAdmin):
    """Custom user admin."""
    
    inlines = (ProfileInline,)
    list_display = ['username', 'email', 'first_name', 'last_name', 'is_actor', 'is_verified', 'is_staff', 'date_joined']
    list_filter = ['is_actor', 'is_verified', 'is_staff', 'is_active', 'date_joined']
    search_fields = ['username', 'email', 'first_name', 'last_name']
    ordering = ['-date_joined']
    
    fieldsets = BaseUserAdmin.fieldsets + (
        ('Дополнительная информация', {
            'fields': ('is_actor', 'is_verified')
        }),
    )
    
    add_fieldsets = BaseUserAdmin.add_fieldsets + (
        ('Дополнительная информация', {
            'fields': ('email', 'first_name', 'last_name', 'is_actor', 'is_verified')
        }),
    )


@admin.register(Profile)
class ProfileAdmin(admin.ModelAdmin):
    """Profile admin."""
    
    list_display = ['user', 'location', 'is_public', 'email_notifications', 'created_at']
    list_filter = ['is_public', 'email_notifications', 'created_at']
    search_fields = ['user__username', 'user__email', 'bio', 'location']
    readonly_fields = ['created_at', 'updated_at']
    
    fieldsets = (
        ('Основная информация', {
            'fields': ('user', 'avatar', 'bio', 'website', 'location', 'birth_date')
        }),
        ('Настройки приватности', {
            'fields': ('is_public', 'show_email', 'show_location')
        }),
        ('Уведомления', {
            'fields': (
                'email_notifications', 'comment_notifications', 
                'like_notifications', 'subscription_notifications'
            )
        }),
        ('Даты', {
            'fields': ('created_at', 'updated_at'),
            'classes': ('collapse',)
        }),
    )


@admin.register(Subscription)
class SubscriptionAdmin(admin.ModelAdmin):
    """Subscription admin."""
    
    list_display = ['subscriber', 'subscribed_to', 'created_at']
    list_filter = ['created_at']
    search_fields = ['subscriber__username', 'subscribed_to__username']
    ordering = ['-created_at']


@admin.register(WatchLater)
class WatchLaterAdmin(admin.ModelAdmin):
    """Watch later admin."""
    
    list_display = ['user', 'video', 'created_at']
    list_filter = ['created_at']
    search_fields = ['user__username', 'video__title']
    ordering = ['-created_at']


@admin.register(Playlist)
class PlaylistAdmin(admin.ModelAdmin):
    """Playlist admin."""
    
    list_display = ['name', 'user', 'is_public', 'video_count', 'created_at']
    list_filter = ['is_public', 'created_at']
    search_fields = ['name', 'description', 'user__username']
    prepopulated_fields = {'slug': ('name',)}
    ordering = ['-created_at']
    
    def video_count(self, obj):
        """Get video count for playlist."""
        return obj.videos.count()
    video_count.short_description = 'Количество видео'


@admin.register(PlaylistVideo)
class PlaylistVideoAdmin(admin.ModelAdmin):
    """Playlist video admin."""
    
    list_display = ['playlist', 'video', 'position', 'created_at']
    list_filter = ['created_at']
    search_fields = ['playlist__name', 'video__title']
    ordering = ['playlist', 'position']


@admin.register(ViewHistory)
class ViewHistoryAdmin(admin.ModelAdmin):
    """View history admin."""
    
    list_display = ['user', 'video', 'watched_duration', 'created_at']
    list_filter = ['created_at']
    search_fields = ['user__username', 'video__title']
    ordering = ['-created_at']
