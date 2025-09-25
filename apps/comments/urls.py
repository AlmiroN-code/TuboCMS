"""
URLs for the Comments app.
"""
from django.urls import path
from . import views

app_name = 'comments'

urlpatterns = [
    path('add/', views.add_comment, name='add_comment'),
    path('like/<int:comment_id>/', views.like_comment, name='like_comment'),
]
