"""
Views for the Comments app.
"""
from django.shortcuts import render, get_object_or_404, redirect
from django.contrib.auth.decorators import login_required
from django.http import JsonResponse, HttpResponse
from django.views.decorators.http import require_http_methods
from django.contrib import messages
from .models import Comment, CommentLike


@require_http_methods(["POST"])
@login_required
def add_comment(request):
    """Add a new comment via HTMX."""
    video_id = request.POST.get('video_id')
    parent_id = request.POST.get('parent_id')
    content = request.POST.get('content', '').strip()
    
    if not content:
        return HttpResponse('<div class="error">Комментарий не может быть пустым</div>')
    
    # Get video (assuming it exists)
    from apps.videos.models import Video
    video = get_object_or_404(Video, id=video_id)
    
    # Create comment
    comment = Comment.objects.create(
        user=request.user,
        video=video,
        content=content,
        parent_id=parent_id if parent_id else None
    )
    
    # Return comment HTML
    return render(request, 'comments/comment_item.html', {'comment': comment})


@require_http_methods(["POST"])
@login_required
def like_comment(request, comment_id):
    """Like/dislike a comment via HTMX."""
    comment = get_object_or_404(Comment, id=comment_id)
    is_like = request.POST.get('action') == 'like'
    
    # Get or create like/dislike
    like, created = CommentLike.objects.get_or_create(
        user=request.user,
        comment=comment,
        defaults={'is_like': is_like}
    )
    
    if not created:
        # Update existing like/dislike
        like.is_like = is_like
        like.save()
    
    # Update comment like/dislike counts
    comment.like_count = CommentLike.objects.filter(comment=comment, is_like=True).count()
    comment.dislike_count = CommentLike.objects.filter(comment=comment, is_like=False).count()
    comment.save(update_fields=['like_count', 'dislike_count'])
    
    # Return updated comment
    return render(request, 'comments/comment_item.html', {'comment': comment})
