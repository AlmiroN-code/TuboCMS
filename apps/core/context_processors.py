"""
Context processors for the Video Portal.
"""
from apps.videos.models import Category


def categories(request):
    """Add categories to template context."""
    categories_list = Category.objects.filter(is_active=True)[:20]
    return {
        'categories': categories_list
    }
