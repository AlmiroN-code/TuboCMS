"""
Template tags for breadcrumbs.
"""
from django import template
from django.urls import reverse
from django.utils.safestring import mark_safe

register = template.Library()


@register.simple_tag(takes_context=True)
def breadcrumbs(context):
    """Generate breadcrumbs based on current URL."""
    request = context['request']
    url_name = request.resolver_match.url_name
    url_kwargs = request.resolver_match.kwargs
    
    breadcrumbs_list = []
    
    # Always start with home
    breadcrumbs_list.append({
        'name': 'Главная',
        'url': reverse('videos:home'),
        'active': url_name == 'home'
    })
    
    # Add specific breadcrumbs based on URL
    if url_name == 'video_detail':
        breadcrumbs_list.append({
            'name': 'Видео',
            'url': reverse('videos:video_list'),
            'active': False
        })
        if 'slug' in url_kwargs:
            # Get video title from context if available
            video = context.get('video')
            if video:
                breadcrumbs_list.append({
                    'name': video.title,
                    'url': None,
                    'active': True
                })
    
    elif url_name == 'video_list':
        breadcrumbs_list.append({
            'name': 'Все видео',
            'url': None,
            'active': True
        })
    
    elif url_name == 'category_list':
        breadcrumbs_list.append({
            'name': 'Категории',
            'url': None,
            'active': True
        })
    
    elif url_name == 'category_videos':
        breadcrumbs_list.append({
            'name': 'Категории',
            'url': reverse('videos:category_list'),
            'active': False
        })
        if 'category_slug' in url_kwargs:
            # Get category name from context if available
            category = context.get('category')
            if category:
                breadcrumbs_list.append({
                    'name': category.name,
                    'url': None,
                    'active': True
                })
    
    elif url_name == 'actor_list':
        breadcrumbs_list.append({
            'name': 'Актеры',
            'url': None,
            'active': True
        })
    
    elif url_name == 'actor_videos':
        breadcrumbs_list.append({
            'name': 'Актеры',
            'url': reverse('videos:actor_list'),
            'active': False
        })
        if 'actor_slug' in url_kwargs:
            # Get actor name from context if available
            actor = context.get('actor')
            if actor:
                breadcrumbs_list.append({
                    'name': actor.name,
                    'url': None,
                    'active': True
                })
    
    elif url_name == 'tag_videos':
        breadcrumbs_list.append({
            'name': 'Теги',
            'url': None,
            'active': False
        })
        if 'tag_slug' in url_kwargs:
            # Get tag name from context if available
            tag = context.get('tag')
            if tag:
                breadcrumbs_list.append({
                    'name': tag.name,
                    'url': None,
                    'active': True
                })
    
    elif url_name == 'watch_later':
        breadcrumbs_list.append({
            'name': 'Watch Later',
            'url': None,
            'active': True
        })
    
    elif url_name == 'playlist_list':
        breadcrumbs_list.append({
            'name': 'Плейлисты',
            'url': None,
            'active': True
        })
    
    elif url_name == 'playlist_detail':
        breadcrumbs_list.append({
            'name': 'Плейлисты',
            'url': reverse('videos:playlist_list'),
            'active': False
        })
        playlist = context.get('playlist')
        if playlist:
            breadcrumbs_list.append({
                'name': playlist.name,
                'url': None,
                'active': True
            })
    
    elif url_name == 'user_profile':
        breadcrumbs_list.append({
            'name': 'Профиль',
            'url': None,
            'active': True
        })
    
    # Generate HTML
    html = '<nav class="breadcrumbs"><ol class="breadcrumb-list">'
    
    for i, breadcrumb in enumerate(breadcrumbs_list):
        if breadcrumb['url']:
            html += f'<li class="breadcrumb-item"><a href="{breadcrumb["url"]}">{breadcrumb["name"]}</a></li>'
        else:
            html += f'<li class="breadcrumb-item active">{breadcrumb["name"]}</li>'
        
        # Add separator if not last item
        if i < len(breadcrumbs_list) - 1:
            html += '<li class="breadcrumb-separator"><i class="fas fa-chevron-right"></i></li>'
    
    html += '</ol></nav>'
    
    return mark_safe(html)
