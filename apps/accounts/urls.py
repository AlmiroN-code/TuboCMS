"""
URLs for the Accounts app.
"""
from django.urls import path, include
from django.contrib.auth import views as auth_views
from . import views

app_name = 'accounts'

urlpatterns = [
    path('login/', auth_views.LoginView.as_view(template_name='registration/login.html'), name='login'),
    path('logout/', auth_views.LogoutView.as_view(), name='logout'),
    path('signup/', views.SignUpView.as_view(), name='signup'),
    path('profile/', views.UserProfileView.as_view(), name='profile'),
    path('profile/edit/', views.ProfileEditView.as_view(), name='profile_edit'),
    path('password/change/', views.PasswordChangeView.as_view(), name='password_change'),
    path('notifications/', views.NotificationSettingsView.as_view(), name='notification_settings'),
    path('privacy/', views.PrivacySettingsView.as_view(), name='privacy_settings'),
    path('upload-avatar/', views.upload_avatar, name='upload_avatar'),
]
