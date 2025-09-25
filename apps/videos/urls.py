"""
URLs for the Video Portal.
"""
from django.urls import path
from . import views

app_name = 'videos'

urlpatterns = [
    # Home page
    path('', views.HomeView.as_view(), name='home'),
    
    # Video views
    path('videos/', views.VideoListView.as_view(), name='video_list'),
    path('videos/<slug:slug>/', views.VideoDetailView.as_view(), name='video_detail'),
    path('upload/', views.VideoUploadView.as_view(), name='video_upload'),
    
    # Category views
    path('categories/', views.CategoryListView.as_view(), name='category_list'),
    path('categories/<slug:category_slug>/', views.VideoListView.as_view(), name='category_videos'),
    
    # Actor views
    path('actors/', views.ActorListView.as_view(), name='actor_list'),
    path('actors/<slug:actor_slug>/', views.VideoListView.as_view(), name='actor_videos'),
    
    # Tag views
    path('tags/<slug:tag_slug>/', views.VideoListView.as_view(), name='tag_videos'),
    
    # User features
    path('watch-later/', views.WatchLaterView.as_view(), name='watch_later'),
    path('playlists/', views.PlaylistListView.as_view(), name='playlist_list'),
    path('playlists/<int:pk>/', views.PlaylistDetailView.as_view(), name='playlist_detail'),
    path('profile/', views.UserProfileView.as_view(), name='user_profile'),
    
    # HTMX endpoints
    path('like/<int:video_id>/', views.like_video, name='like_video'),
    path('toggle-watch-later/<int:video_id>/', views.toggle_watch_later, name='toggle_watch_later'),
    path('create-playlist/', views.create_playlist, name='create_playlist'),
    path('add-to-playlist/<int:playlist_id>/<int:video_id>/', views.add_to_playlist, name='add_to_playlist'),
    path('get-playlists/', views.get_user_playlists, name='get_user_playlists'),
    path('search-suggestions/', views.search_suggestions, name='search_suggestions'),
    path('load-more/', views.load_more_videos, name='load_more_videos'),
]
