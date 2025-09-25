"""
Views for the Video Portal.
"""
from django.shortcuts import render, get_object_or_404, redirect
from django.contrib.auth.decorators import login_required
from django.contrib import messages
from django.http import JsonResponse, HttpResponse
from django.core.paginator import Paginator
from django.db.models import Q, F, Count
from django.db import models
from django.views.decorators.http import require_http_methods
from django.views.decorators.csrf import csrf_exempt
from django.utils.decorators import method_decorator
from django.views.generic import ListView, DetailView, CreateView, TemplateView
from django.urls import reverse_lazy, reverse
from django.contrib.auth.mixins import LoginRequiredMixin
from .models import Video, Category, Tag, Actor, VideoLike, VideoView, SiteSettings, PlaylistVideo
from apps.accounts.models import WatchLater, Playlist
from .tasks import process_video
from .forms import VideoUploadForm


class HomeView(ListView):
    """Home page view."""
    
    model = Video
    template_name = 'videos/home.html'
    context_object_name = 'videos'
    paginate_by = 20
    
    def get_queryset(self):
        """Get popular videos for home page."""
        return Video.published.select_related('category', 'author').prefetch_related('tags', 'actors')
    
    def get_context_data(self, **kwargs):
        """Add extra context data."""
        context = super().get_context_data(**kwargs)
        
        # Get site settings
        site_settings = SiteSettings.get_active_settings()
        
        # Get different video sections using site settings
        context['popular_videos'] = Video.popular.filter(is_published=True)[:site_settings.popular_videos_count]
        context['recent_videos'] = Video.recent.filter(is_published=True)[:site_settings.new_videos_count]
        context['trending_videos'] = Video.trending.filter(is_published=True)[:site_settings.random_videos_count]
        
        return context


class VideoListView(ListView):
    """Video list view with filtering."""
    
    model = Video
    template_name = 'videos/video_list.html'
    context_object_name = 'videos'
    
    def get_paginate_by(self, queryset):
        """Get pagination size from site settings."""
        site_settings = SiteSettings.get_active_settings()
        return site_settings.videos_per_page
    
    def get_queryset(self):
        """Get filtered videos."""
        queryset = Video.published.select_related('category', 'author').prefetch_related('tags', 'actors')
        
        # Filter by category
        category_slug = self.kwargs.get('category_slug')
        if category_slug:
            category = get_object_or_404(Category, slug=category_slug)
            queryset = queryset.filter(category=category)
        
        # Filter by tag
        tag_slug = self.kwargs.get('tag_slug')
        if tag_slug:
            tag = get_object_or_404(Tag, slug=tag_slug)
            queryset = queryset.filter(tags=tag)
        
        # Filter by actor
        actor_slug = self.kwargs.get('actor_slug')
        if actor_slug:
            actor = get_object_or_404(Actor, slug=actor_slug)
            queryset = queryset.filter(actors=actor)
        
        # Search
        search_query = self.request.GET.get('q')
        if search_query:
            queryset = queryset.filter(
                Q(title__icontains=search_query) |
                Q(description__icontains=search_query) |
                Q(tags__name__icontains=search_query) |
                Q(actors__name__icontains=search_query)
            ).distinct()
        
        # Sort
        sort_by = self.request.GET.get('sort', 'recent')
        if sort_by == 'popular':
            queryset = queryset.order_by('-view_count')
        elif sort_by == 'trending':
            queryset = queryset.order_by('-like_count', '-created_at')
        elif sort_by == 'rating':
            queryset = queryset.order_by('-like_count')
        else:  # recent
            queryset = queryset.order_by('-created_at')
        
        return queryset
    
    def get_context_data(self, **kwargs):
        """Add extra context data."""
        context = super().get_context_data(**kwargs)
        
        # Get categories for sidebar
        context['categories'] = Category.active.all()[:20]
        
        # Current filter info
        category_slug = self.kwargs.get('category_slug')
        if category_slug:
            context['category'] = get_object_or_404(Category, slug=category_slug)
        
        tag_slug = self.kwargs.get('tag_slug')
        if tag_slug:
            context['tag'] = get_object_or_404(Tag, slug=tag_slug)
        
        actor_slug = self.kwargs.get('actor_slug')
        if actor_slug:
            context['actor'] = get_object_or_404(Actor, slug=actor_slug)
        
        context['search_query'] = self.request.GET.get('q', '')
        context['sort_by'] = self.request.GET.get('sort', 'recent')
        
        return context


class VideoDetailView(DetailView):
    """Video detail view."""
    
    model = Video
    template_name = 'videos/video_detail.html'
    context_object_name = 'video'
    
    def get_queryset(self):
        """Get published videos only."""
        return Video.published.select_related('category', 'author').prefetch_related('tags', 'actors', 'comments__user')
    
    def get_context_data(self, **kwargs):
        """Add extra context data."""
        context = super().get_context_data(**kwargs)
        video = self.get_object()
        
        # Get related videos
        context['related_videos'] = Video.published.filter(
            category=video.category
        ).exclude(id=video.id)[:6]
        
        # Get comments
        context['comments'] = video.comments.filter(is_approved=True, parent=None)[:50]
        
        # Track view
        self.track_view(video)
        
        return context
    
    def track_view(self, video):
        """Track video view."""
        # Get client IP
        x_forwarded_for = self.request.META.get('HTTP_X_FORWARDED_FOR')
        if x_forwarded_for:
            ip = x_forwarded_for.split(',')[0]
        else:
            ip = self.request.META.get('REMOTE_ADDR')
        
        # Create or update view record
        view, created = VideoView.objects.get_or_create(
            video=video,
            ip_address=ip,
            defaults={
                'user': self.request.user if self.request.user.is_authenticated else None,
                'user_agent': self.request.META.get('HTTP_USER_AGENT', ''),
                'referrer': self.request.META.get('HTTP_REFERER', ''),
            }
        )
        
        # Increment view count
        video.increment_view_count()


class VideoUploadView(LoginRequiredMixin, CreateView):
    """Video upload view."""
    
    model = Video
    form_class = VideoUploadForm
    template_name = 'videos/video_upload.html'
    success_url = reverse_lazy('videos:home')
    
    def form_valid(self, form):
        """Handle valid form submission."""
        form.instance.author = self.request.user
        response = super().form_valid(form)
        
        # Start video processing
        process_video.delay(self.object.id)
        
        messages.success(self.request, 'Видео загружено! Обработка началась.')
        return response


@require_http_methods(["POST"])
@login_required
def like_video(request, video_id):
    """Like/dislike video via HTMX."""
    video = get_object_or_404(Video, id=video_id)
    is_like = request.POST.get('action') == 'like'
    
    # Get or create like/dislike
    like, created = VideoLike.objects.get_or_create(
        user=request.user,
        video=video,
        defaults={'is_like': is_like}
    )
    
    if not created:
        # Update existing like/dislike
        like.is_like = is_like
        like.save()
    
    # Update video like/dislike counts
    video.like_count = VideoLike.objects.filter(video=video, is_like=True).count()
    video.dislike_count = VideoLike.objects.filter(video=video, is_like=False).count()
    video.save(update_fields=['like_count', 'dislike_count'])
    
    # Return updated rating info
    context = {
        'video': video,
        'user_like': like if like.is_like else None,
        'user_dislike': like if not like.is_like else None,
    }
    
    return render(request, 'videos/partials/video_rating.html', context)


@require_http_methods(["GET"])
def search_suggestions(request):
    """Get search suggestions via HTMX."""
    query = request.GET.get('q', '').strip()
    
    if len(query) < 2:
        return HttpResponse('')
    
    # Search videos, tags, actors
    videos = Video.published.filter(title__icontains=query)[:5]
    tags = Tag.objects.filter(name__icontains=query)[:5]
    actors = Actor.objects.filter(name__icontains=query)[:5]
    
    context = {
        'videos': videos,
        'tags': tags,
        'actors': actors,
        'query': query,
    }
    
    return render(request, 'videos/partials/search_suggestions.html', context)


@require_http_methods(["GET"])
def load_more_videos(request):
    """Load more videos via HTMX infinite scroll."""
    page = int(request.GET.get('page', 1))
    category_slug = request.GET.get('category')
    sort_by = request.GET.get('sort', 'recent')
    
    # Get videos
    queryset = Video.published.select_related('category', 'author').prefetch_related('tags', 'actors')
    
    if category_slug:
        category = get_object_or_404(Category, slug=category_slug)
        queryset = queryset.filter(category=category)
    
    # Sort
    if sort_by == 'popular':
        queryset = queryset.order_by('-view_count')
    elif sort_by == 'trending':
        queryset = queryset.order_by('-like_count', '-created_at')
    elif sort_by == 'rating':
        queryset = queryset.order_by('-like_count')
    else:
        queryset = queryset.order_by('-created_at')
    
    # Paginate
    paginator = Paginator(queryset, 12)
    page_obj = paginator.get_page(page)
    
    context = {
        'videos': page_obj,
        'has_next': page_obj.has_next(),
        'next_page': page + 1 if page_obj.has_next() else None,
    }
    
    return render(request, 'videos/partials/video_grid.html', context)


class CategoryListView(ListView):
    """Category list view."""
    
    model = Category
    template_name = 'videos/category_list.html'
    context_object_name = 'categories'
    
    def get_queryset(self):
        """Get active categories."""
        return Category.active.all()


class ActorListView(ListView):
    """Actor list view."""
    
    model = Actor
    template_name = 'videos/actor_list.html'
    context_object_name = 'actors'
    
    def get_paginate_by(self, queryset):
        """Get pagination size from site settings."""
        site_settings = SiteSettings.get_active_settings()
        return site_settings.videos_per_page
    
    def get_queryset(self):
        """Get active actors."""
        return Actor.objects.filter(is_active=True)


class WatchLaterView(LoginRequiredMixin, ListView):
    """Watch Later view."""
    
    model = WatchLater
    template_name = 'videos/watch_later.html'
    context_object_name = 'watch_later_items'
    paginate_by = 20
    
    def get_queryset(self):
        """Get user's watch later items."""
        return WatchLater.objects.filter(user=self.request.user).select_related('video', 'video__category', 'video__author').prefetch_related('video__tags', 'video__actors')


class PlaylistListView(LoginRequiredMixin, ListView):
    """User playlists view."""
    
    model = Playlist
    template_name = 'videos/playlist_list.html'
    context_object_name = 'playlists'
    paginate_by = 20
    
    def get_queryset(self):
        """Get user's playlists."""
        return Playlist.objects.filter(user=self.request.user).prefetch_related('playlist_videos__video').order_by('-created_at')


class PlaylistDetailView(LoginRequiredMixin, DetailView):
    """Playlist detail view."""
    
    model = Playlist
    template_name = 'videos/playlist_detail.html'
    context_object_name = 'playlist'
    
    def get_queryset(self):
        """Get user's playlists or public playlists."""
        return Playlist.objects.filter(
            models.Q(user=self.request.user) | models.Q(is_public=True)
        ).prefetch_related('playlist_videos__video')


class UserProfileView(LoginRequiredMixin, TemplateView):
    """User profile view."""
    
    template_name = 'accounts/profile.html'
    
    def get_context_data(self, **kwargs):
        """Add user data to context."""
        context = super().get_context_data(**kwargs)
        user = self.request.user
        
        # Get user statistics
        context['user'] = user
        context['video_count'] = Video.objects.filter(author=user).count()
        context['playlist_count'] = Playlist.objects.filter(user=user).count()
        context['watch_later_count'] = WatchLater.objects.filter(user=user).count()
        
        # Get recent videos
        context['recent_videos'] = Video.objects.filter(author=user).order_by('-created_at')[:5]
        
        return context


@require_http_methods(["POST"])
@login_required
def toggle_watch_later(request, video_id):
    """Toggle video in watch later."""
    try:
        video = Video.objects.get(id=video_id)
        watch_later, created = WatchLater.objects.get_or_create(
            user=request.user,
            video=video
        )
        
        if not created:
            watch_later.delete()
            message = "Удалено из 'Смотреть позже'"
            status = "removed"
            icon_class = "far fa-heart"
            title = "Add to Watch Later"
        else:
            message = "Добавлено в 'Смотреть позже'"
            status = "added"
            icon_class = "fas fa-heart"
            title = "Remove from Watch Later"
        
        # Return HTML for the button instead of JSON
        button_html = f'''
        <button class="action-btn watch-later-btn {'active' if status == 'added' else ''}" 
                hx-post="{reverse('videos:toggle_watch_later', args=[video_id])}"
                hx-target="this"
                hx-swap="outerHTML"
                hx-headers='{{"X-CSRFToken": "{request.META.get('CSRF_COOKIE', '')}"}}'
                title="{title}"
                data-status="{status}">
            <i class="{icon_class}"></i>
        </button>
        '''
        
        # Return both HTML and JSON for compatibility
        response_data = {
            'status': status,
            'message': message,
            'count': WatchLater.objects.filter(user=request.user).count(),
            'html': button_html
        }
        
        return JsonResponse(response_data)
    
    except Video.DoesNotExist:
        return JsonResponse({'error': 'Видео не найдено'}, status=404)
    except Exception as e:
        return JsonResponse({'error': str(e)}, status=500)


@require_http_methods(["POST"])
@login_required
def create_playlist(request):
    """Create new playlist."""
    try:
        name = request.POST.get('name')
        description = request.POST.get('description', '')
        is_public = request.POST.get('is_public') == 'true'
        
        if not name:
            return JsonResponse({'error': 'Название плейлиста обязательно'}, status=400)
        
        playlist = Playlist.objects.create(
            name=name,
            description=description,
            is_public=is_public,
            user=request.user
        )
        
        return JsonResponse({
            'status': 'success',
            'message': 'Плейлист создан',
            'playlist_id': playlist.id,
            'playlist_name': playlist.name
        })
    
    except Exception as e:
        return JsonResponse({'error': str(e)}, status=500)


@require_http_methods(["POST"])
@login_required
def add_to_playlist(request, playlist_id, video_id):
    """Add video to playlist."""
    try:
        playlist = Playlist.objects.get(id=playlist_id, user=request.user)
        video = Video.objects.get(id=video_id)
        
        playlist_video, created = PlaylistVideo.objects.get_or_create(
            playlist=playlist,
            video=video
        )
        
        if not created:
            return JsonResponse({'error': 'Видео уже в плейлисте'}, status=400)
        
        return JsonResponse({
            'status': 'success',
            'message': f'Видео добавлено в плейлист "{playlist.name}"'
        })
    
    except Playlist.DoesNotExist:
        return JsonResponse({'error': 'Плейлист не найден'}, status=404)
    except Video.DoesNotExist:
        return JsonResponse({'error': 'Видео не найдено'}, status=404)
    except Exception as e:
        return JsonResponse({'error': str(e)}, status=500)


@require_http_methods(["GET"])
@login_required
def get_user_playlists(request):
    """Get user playlists for HTMX dropdown."""
    playlists = Playlist.objects.filter(user=request.user).order_by('-created_at')
    
    html = ''
    for playlist in playlists:
        html += f'''
        <div class="playlist-item" onclick="addToPlaylist({playlist.id}, this)">
            <i class="fas fa-list"></i>
            <span>{playlist.name}</span>
            <small>({playlist.playlist_videos.count()} видео)</small>
        </div>
        '''
    
    if not playlists:
        html = '<div class="playlist-item">Нет плейлистов</div>'
    
    return HttpResponse(html)
