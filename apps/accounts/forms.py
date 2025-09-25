"""
Forms for the Accounts app.
"""
from django import forms
from django.contrib.auth.forms import UserChangeForm
from .models import User, Profile


class ProfileForm(forms.ModelForm):
    """Profile editing form."""
    
    first_name = forms.CharField(
        max_length=150,
        required=False,
        widget=forms.TextInput(attrs={
            'class': 'form-control',
            'placeholder': 'Введите имя'
        }),
        label='Имя'
    )
    
    last_name = forms.CharField(
        max_length=150,
        required=False,
        widget=forms.TextInput(attrs={
            'class': 'form-control',
            'placeholder': 'Введите фамилию'
        }),
        label='Фамилия'
    )
    
    email = forms.EmailField(
        widget=forms.EmailInput(attrs={
            'class': 'form-control',
            'placeholder': 'Введите email'
        }),
        label='Email'
    )
    
    class Meta:
        model = Profile
        fields = ['avatar', 'bio', 'website', 'location', 'birth_date']
        widgets = {
            'bio': forms.Textarea(attrs={
                'class': 'form-control',
                'rows': 4,
                'placeholder': 'Расскажите о себе...'
            }),
            'website': forms.URLInput(attrs={
                'class': 'form-control',
                'placeholder': 'https://example.com'
            }),
            'location': forms.TextInput(attrs={
                'class': 'form-control',
                'placeholder': 'Город, страна'
            }),
            'birth_date': forms.DateInput(attrs={
                'class': 'form-control',
                'type': 'date'
            }),
        }
    
    def __init__(self, *args, **kwargs):
        super().__init__(*args, **kwargs)
        if self.instance and self.instance.user:
            self.fields['first_name'].initial = self.instance.user.first_name
            self.fields['last_name'].initial = self.instance.user.last_name
            self.fields['email'].initial = self.instance.user.email
    
    def save(self, commit=True):
        """Save profile and user data."""
        profile = super().save(commit=False)
        
        if commit:
            # Update user data
            user = profile.user
            user.first_name = self.cleaned_data['first_name']
            user.last_name = self.cleaned_data['last_name']
            user.email = self.cleaned_data['email']
            user.save()
            
            # Save profile
            profile.save()
        
        return profile


class NotificationSettingsForm(forms.ModelForm):
    """Notification settings form."""
    
    class Meta:
        model = Profile
        fields = [
            'email_notifications',
            'comment_notifications',
            'like_notifications',
            'subscription_notifications'
        ]
        widgets = {
            'email_notifications': forms.CheckboxInput(attrs={
                'class': 'form-check-input'
            }),
            'comment_notifications': forms.CheckboxInput(attrs={
                'class': 'form-check-input'
            }),
            'like_notifications': forms.CheckboxInput(attrs={
                'class': 'form-check-input'
            }),
            'subscription_notifications': forms.CheckboxInput(attrs={
                'class': 'form-check-input'
            }),
        }
        labels = {
            'email_notifications': 'Email уведомления',
            'comment_notifications': 'Уведомления о комментариях',
            'like_notifications': 'Уведомления о лайках',
            'subscription_notifications': 'Уведомления о подписках',
        }


class PrivacySettingsForm(forms.ModelForm):
    """Privacy settings form."""
    
    class Meta:
        model = Profile
        fields = [
            'is_public',
            'show_email',
            'show_location'
        ]
        widgets = {
            'is_public': forms.CheckboxInput(attrs={
                'class': 'form-check-input'
            }),
            'show_email': forms.CheckboxInput(attrs={
                'class': 'form-check-input'
            }),
            'show_location': forms.CheckboxInput(attrs={
                'class': 'form-check-input'
            }),
        }
        labels = {
            'is_public': 'Публичный профиль',
            'show_email': 'Показывать email',
            'show_location': 'Показывать местоположение',
        }


class AvatarUploadForm(forms.Form):
    """Avatar upload form."""
    
    avatar = forms.ImageField(
        widget=forms.FileInput(attrs={
            'class': 'form-control',
            'accept': 'image/*'
        }),
        label='Выберите аватар'
    )
