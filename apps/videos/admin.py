"""
Admin configuration for Video Portal.
"""
from django.contrib import admin
from django.utils.html import format_html
from .models import Video, Category, Tag, Actor, VideoLike, VideoView, VideoProcessingSettings, SiteSettings, PlaylistVideo


@admin.register(Category)
class CategoryAdmin(admin.ModelAdmin):
    """Category admin."""
    
    list_display = ['name', 'slug', 'is_active', 'video_count', 'sort_order']
    list_filter = ['is_active', 'created_at']
    search_fields = ['name', 'description']
    prepopulated_fields = {'slug': ('name',)}
    ordering = ['sort_order', 'name']
    
    def video_count(self, obj):
        """Get video count for category."""
        return obj.video_count
    video_count.short_description = 'Количество видео'


@admin.register(Tag)
class TagAdmin(admin.ModelAdmin):
    """Tag admin."""
    
    list_display = ['name', 'slug', 'color_preview', 'video_count']
    list_filter = ['created_at']
    search_fields = ['name']
    prepopulated_fields = {'slug': ('name',)}
    ordering = ['name']
    
    def color_preview(self, obj):
        """Show color preview."""
        return format_html(
            '<div style="width: 20px; height: 20px; background-color: {}; border-radius: 3px;"></div>',
            obj.color
        )
    color_preview.short_description = 'Цвет'
    
    def video_count(self, obj):
        """Get video count for tag."""
        return obj.videos.count()
    video_count.short_description = 'Количество видео'


@admin.register(Actor)
class ActorAdmin(admin.ModelAdmin):
    """Actor admin."""
    
    list_display = ['name', 'slug', 'is_active', 'video_count', 'created_at']
    list_filter = ['is_active', 'created_at']
    search_fields = ['name', 'bio']
    prepopulated_fields = {'slug': ('name',)}
    ordering = ['name']
    
    def video_count(self, obj):
        """Get video count for actor."""
        return obj.video_count
    video_count.short_description = 'Количество видео'


@admin.register(Video)
class VideoAdmin(admin.ModelAdmin):
    """Video admin."""
    
    list_display = [
        'title', 'author', 'category', 'duration_formatted', 
        'view_count', 'rating_percentage', 'is_published', 
        'processing_status', 'processing_progress_display', 'created_at'
    ]
    list_filter = [
        'is_published', 'processing_status', 'category', 
        'created_at', 'published_at'
    ]
    search_fields = ['title', 'description', 'author__username']
    prepopulated_fields = {'slug': ('title',)}
    readonly_fields = [
        'duration', 'file_size', 'view_count', 'like_count', 
        'dislike_count', 'created_at', 'updated_at', 'published_at'
    ]
    filter_horizontal = ['tags', 'actors']
    ordering = ['-created_at']
    
    fieldsets = (
        ('Основная информация', {
            'fields': ('title', 'slug', 'description', 'author', 'category')
        }),
        ('Файлы', {
            'fields': ('video_file', 'poster', 'preview_video')
        }),
        ('Метаданные', {
            'fields': ('duration', 'file_size', 'tags', 'actors')
        }),
        ('Статистика', {
            'fields': ('view_count', 'like_count', 'dislike_count'),
            'classes': ('collapse',)
        }),
        ('Обработка видео', {
            'fields': ('is_processing', 'processing_status'),
            'classes': ('collapse',)
        }),
        ('Публикация', {
            'fields': ('is_published', 'published_at')
        }),
        ('Даты', {
            'fields': ('created_at', 'updated_at'),
            'classes': ('collapse',)
        }),
    )
    
    def duration_formatted(self, obj):
        """Get formatted duration."""
        return obj.duration_formatted
    duration_formatted.short_description = 'Длительность'
    
    def rating_percentage(self, obj):
        """Get rating percentage."""
        return f"{obj.rating_percentage:.1f}%"
    rating_percentage.short_description = 'Рейтинг'
    
    def processing_progress_display(self, obj):
        """Display processing progress as a progress bar."""
        if obj.processing_status == 'completed':
            return format_html(
                '<div style="width: 100px; background-color: #e0e0e0; border-radius: 3px;">'
                '<div style="width: 100%; background-color: #4caf50; height: 20px; border-radius: 3px; text-align: center; color: white; line-height: 20px;">100%</div>'
                '</div>'
            )
        elif obj.processing_status == 'failed':
            return format_html(
                '<div style="width: 100px; background-color: #e0e0e0; border-radius: 3px;">'
                '<div style="width: 100%; background-color: #f44336; height: 20px; border-radius: 3px; text-align: center; color: white; line-height: 20px;">Ошибка</div>'
                '</div>'
            )
        elif obj.processing_status == 'processing':
            return format_html(
                '<div style="width: 100px; background-color: #e0e0e0; border-radius: 3px;">'
                '<div style="width: 50%; background-color: #2196f3; height: 20px; border-radius: 3px; text-align: center; color: white; line-height: 20px;">50%</div>'
                '</div>'
            )
        else:
            return format_html(
                '<div style="width: 100px; background-color: #e0e0e0; border-radius: 3px;">'
                '<div style="width: 0%; background-color: #ff9800; height: 20px; border-radius: 3px; text-align: center; color: white; line-height: 20px;">Ожидает</div>'
                '</div>'
            )
    processing_progress_display.short_description = 'Прогресс'
    
    def get_queryset(self, request):
        """Optimize queryset."""
        return super().get_queryset(request).select_related('author', 'category').prefetch_related('tags', 'actors')


@admin.register(VideoLike)
class VideoLikeAdmin(admin.ModelAdmin):
    """Video like admin."""
    
    list_display = ['user', 'video', 'is_like', 'created_at']
    list_filter = ['is_like', 'created_at']
    search_fields = ['user__username', 'video__title']
    ordering = ['-created_at']


@admin.register(VideoView)
class VideoViewAdmin(admin.ModelAdmin):
    """Video view admin."""
    
    list_display = ['video', 'user', 'ip_address', 'created_at']
    list_filter = ['created_at']
    search_fields = ['video__title', 'user__username', 'ip_address']
    ordering = ['-created_at']


@admin.register(VideoProcessingSettings)
class VideoProcessingSettingsAdmin(admin.ModelAdmin):
    """Video processing settings admin."""
    
    list_display = ['is_active', 'poster_width', 'poster_height', 'preview_width', 'preview_height', 'preview_duration', 'updated_at']
    list_filter = ['is_active', 'created_at']
    ordering = ['-updated_at']
    
    fieldsets = (
        ('Настройки постера', {
            'fields': ('poster_width', 'poster_height', 'poster_quality'),
            'description': 'Настройки для генерации постера из видео'
        }),
        ('Настройки превью', {
            'fields': ('preview_width', 'preview_height', 'preview_duration', 'preview_segment_duration', 'preview_crf', 'preview_preset'),
            'description': 'Настройки для генерации превью видео'
        }),
        ('Общие настройки', {
            'fields': ('is_active',),
            'description': 'Активные настройки будут использоваться для обработки новых видео'
        }),
    )
    
    def has_add_permission(self, request):
        """Allow adding only if no active settings exist."""
        return not VideoProcessingSettings.objects.filter(is_active=True).exists()
    
    def has_delete_permission(self, request, obj=None):
        """Prevent deletion of active settings."""
        if obj and obj.is_active:
            return False
        return True


@admin.register(SiteSettings)
class SiteSettingsAdmin(admin.ModelAdmin):
    """Site settings admin."""
    
    list_display = ['site_name', 'site_short_name', 'videos_per_page', 'enable_caching', 'is_active', 'updated_at']
    list_filter = ['is_active', 'enable_caching', 'created_at']
    ordering = ['-updated_at']
    
    fieldsets = (
        ('Основная информация сайта', {
            'fields': ('site_name', 'site_description', 'site_keywords', 'site_short_name'),
            'description': 'Основные настройки сайта для SEO и отображения'
        }),
        ('Настройки пагинации', {
            'fields': ('videos_per_page', 'videos_per_page_categories'),
            'description': 'Количество видео на страницах сайта'
        }),
        ('Блоки главной страницы', {
            'fields': ('popular_videos_count', 'new_videos_count', 'random_videos_count'),
            'description': 'Количество видео в различных блоках на главной странице'
        }),
        ('Настройки страницы видео', {
            'fields': ('similar_videos_count',),
            'description': 'Настройки для страницы просмотра видео'
        }),
        ('Производительность', {
            'fields': ('enable_caching',),
            'description': 'Настройки кеширования для улучшения производительности'
        }),
        ('Системные настройки', {
            'fields': ('is_active',),
            'description': 'Активные настройки будут использоваться на сайте',
            'classes': ('collapse',)
        }),
    )
    
    def has_add_permission(self, request):
        """Allow adding only if no active settings exist."""
        return not SiteSettings.objects.filter(is_active=True).exists()
    
    def has_delete_permission(self, request, obj=None):
        """Prevent deletion of active settings."""
        if obj and obj.is_active:
            return False
        return True


@admin.register(PlaylistVideo)
class PlaylistVideoAdmin(admin.ModelAdmin):
    """Playlist Video admin."""
    
    list_display = ['playlist', 'video', 'order', 'created_at']
    list_filter = ['created_at', 'playlist']
    search_fields = ['playlist__name', 'video__title']
    ordering = ['playlist', 'order', '-created_at']
