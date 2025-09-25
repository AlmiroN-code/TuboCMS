"""
Views for the Accounts app.
"""
from django.shortcuts import render, redirect
from django.contrib.auth import login, update_session_auth_hash
from django.contrib.auth.decorators import login_required
from django.contrib.auth.mixins import LoginRequiredMixin
from django.contrib.auth.forms import PasswordChangeForm
from django.views.generic import CreateView, TemplateView, UpdateView
from django.urls import reverse_lazy, reverse
from django.contrib import messages
from django.db import transaction
from django.http import JsonResponse
from django.views.decorators.http import require_POST
from django.views.decorators.csrf import csrf_exempt
from django.utils.decorators import method_decorator
from .models import User, Profile
from .forms import ProfileForm, NotificationSettingsForm, PrivacySettingsForm


class SignUpView(CreateView):
    """User registration view."""
    
    model = User
    template_name = 'registration/signup.html'
    fields = ['username', 'email', 'first_name', 'last_name', 'password']
    success_url = reverse_lazy('videos:home')
    
    def form_valid(self, form):
        """Handle valid form submission."""
        with transaction.atomic():
            user = form.save(commit=False)
            user.set_password(form.cleaned_data['password'])
            user.save()
            
            # Create user profile
            Profile.objects.create(user=user)
            
            # Log user in
            login(self.request, user)
            
            messages.success(
                self.request, 
                f'Добро пожаловать, {user.first_name}! Ваш аккаунт успешно создан.'
            )
        
        return super().form_valid(form)


class UserProfileView(LoginRequiredMixin, TemplateView):
    """User profile view."""
    
    template_name = 'accounts/profile.html'
    
    def get_context_data(self, **kwargs):
        """Add profile data to context."""
        context = super().get_context_data(**kwargs)
        user = self.request.user
        
        try:
            profile = user.profile
        except Profile.DoesNotExist:
            # Create profile if it doesn't exist
            profile = Profile.objects.create(user=user)
        
        context['profile'] = profile
        
        # Add statistics
        from apps.videos.models import Video
        from apps.accounts.models import Playlist, WatchLater
        
        context['video_count'] = Video.objects.filter(author=user).count()
        context['playlist_count'] = Playlist.objects.filter(user=user).count()
        context['watch_later_count'] = WatchLater.objects.filter(user=user).count()
        context['recent_videos'] = Video.objects.filter(author=user).order_by('-created_at')[:6]
        
        return context


class ProfileEditView(LoginRequiredMixin, UpdateView):
    """Profile editing view."""
    
    model = Profile
    form_class = ProfileForm
    template_name = 'accounts/profile_edit.html'
    
    def get_object(self):
        """Get user's profile."""
        try:
            return self.request.user.profile
        except Profile.DoesNotExist:
            return Profile.objects.create(user=self.request.user)
    
    def get_success_url(self):
        """Redirect to profile page after successful update."""
        messages.success(self.request, 'Профиль успешно обновлен!')
        return reverse('accounts:profile')


class PasswordChangeView(LoginRequiredMixin, TemplateView):
    """Password change view."""
    
    template_name = 'accounts/password_change.html'
    
    def get_context_data(self, **kwargs):
        """Add password change form to context."""
        context = super().get_context_data(**kwargs)
        context['form'] = PasswordChangeForm(self.request.user)
        return context
    
    def post(self, request, *args, **kwargs):
        """Handle password change form submission."""
        form = PasswordChangeForm(request.user, request.POST)
        
        if form.is_valid():
            user = form.save()
            update_session_auth_hash(request, user)
            messages.success(request, 'Пароль успешно изменен!')
            return redirect('accounts:profile')
        else:
            messages.error(request, 'Ошибка при изменении пароля.')
            return self.render_to_response({'form': form})


class NotificationSettingsView(LoginRequiredMixin, UpdateView):
    """Notification settings view."""
    
    model = Profile
    form_class = NotificationSettingsForm
    template_name = 'accounts/notification_settings.html'
    
    def get_object(self):
        """Get user's profile."""
        try:
            return self.request.user.profile
        except Profile.DoesNotExist:
            return Profile.objects.create(user=self.request.user)
    
    def get_success_url(self):
        """Redirect to profile page after successful update."""
        messages.success(self.request, 'Настройки уведомлений обновлены!')
        return reverse('accounts:profile')


class PrivacySettingsView(LoginRequiredMixin, UpdateView):
    """Privacy settings view."""
    
    model = Profile
    form_class = PrivacySettingsForm
    template_name = 'accounts/privacy_settings.html'
    
    def get_object(self):
        """Get user's profile."""
        try:
            return self.request.user.profile
        except Profile.DoesNotExist:
            return Profile.objects.create(user=self.request.user)
    
    def get_success_url(self):
        """Redirect to profile page after successful update."""
        messages.success(self.request, 'Настройки приватности обновлены!')
        return reverse('accounts:profile')


@login_required
@require_POST
def upload_avatar(request):
    """Handle avatar upload via AJAX."""
    try:
        profile = request.user.profile
    except Profile.DoesNotExist:
        profile = Profile.objects.create(user=request.user)
    
    if 'avatar' in request.FILES:
        profile.avatar = request.FILES['avatar']
        profile.save()
        return JsonResponse({
            'success': True,
            'message': 'Аватар успешно загружен!',
            'avatar_url': profile.avatar.url if profile.avatar else None
        })
    
    return JsonResponse({
        'success': False,
        'message': 'Ошибка при загрузке аватара'
    })
