"""
Forms for the Video Portal.
"""
from django import forms
from django.core.exceptions import ValidationError
from .models import Video, Category, Tag, Actor


class VideoUploadForm(forms.ModelForm):
    """Form for uploading videos."""
    
    class Meta:
        model = Video
        fields = ['title', 'description', 'video_file', 'category', 'tags', 'actors']
        widgets = {
            'title': forms.TextInput(attrs={
                'class': 'input-field',
                'placeholder': 'Название видео',
                'maxlength': 255,
            }),
            'description': forms.Textarea(attrs={
                'class': 'input-field',
                'placeholder': 'Описание видео',
                'rows': 4,
            }),
            'video_file': forms.FileInput(attrs={
                'class': 'input-field',
                'accept': '.mp4,.avi,.mov,.wmv,.flv,.webm',
                'id': 'video-file-input',
            }),
            'category': forms.Select(attrs={
                'class': 'input-field',
            }),
            'tags': forms.SelectMultiple(attrs={
                'class': 'input-field',
                'multiple': True,
            }),
            'actors': forms.SelectMultiple(attrs={
                'class': 'input-field',
                'multiple': True,
            }),
        }
    
    def __init__(self, *args, **kwargs):
        super().__init__(*args, **kwargs)
        
        # Customize querysets
        self.fields['category'].queryset = Category.active.all()
        self.fields['tags'].queryset = Tag.objects.all()
        self.fields['actors'].queryset = Actor.objects.filter(is_active=True)
        
        # Make category required
        self.fields['category'].required = True
        self.fields['tags'].required = False
        self.fields['actors'].required = False
    
    def clean_video_file(self):
        """Validate video file."""
        video_file = self.cleaned_data.get('video_file')
        
        if not video_file:
            raise ValidationError('Выберите видео файл.')
        
        # Check file extension
        allowed_extensions = ['mp4', 'avi', 'mov', 'wmv', 'flv', 'webm']
        file_extension = video_file.name.split('.')[-1].lower()
        
        if file_extension not in allowed_extensions:
            raise ValidationError(
                f'Неподдерживаемый формат файла. '
                f'Разрешенные форматы: {", ".join(allowed_extensions)}'
            )
        
        # Check file size (500MB limit)
        max_size = 500 * 1024 * 1024  # 500MB
        if video_file.size > max_size:
            raise ValidationError('Размер файла не должен превышать 500MB.')
        
        return video_file
    
    def clean_title(self):
        """Validate title."""
        title = self.cleaned_data.get('title')
        
        if not title or len(title.strip()) < 3:
            raise ValidationError('Название должно содержать минимум 3 символа.')
        
        return title.strip()


class VideoEditForm(forms.ModelForm):
    """Form for editing videos."""
    
    class Meta:
        model = Video
        fields = ['title', 'description', 'category', 'tags', 'actors', 'is_published']
        widgets = {
            'title': forms.TextInput(attrs={
                'class': 'input-field',
                'maxlength': 255,
            }),
            'description': forms.Textarea(attrs={
                'class': 'input-field',
                'rows': 4,
            }),
            'category': forms.Select(attrs={
                'class': 'input-field',
            }),
            'tags': forms.SelectMultiple(attrs={
                'class': 'input-field',
                'multiple': True,
            }),
            'actors': forms.SelectMultiple(attrs={
                'class': 'input-field',
                'multiple': True,
            }),
            'is_published': forms.CheckboxInput(attrs={
                'class': 'form-check-input',
            }),
        }
    
    def __init__(self, *args, **kwargs):
        super().__init__(*args, **kwargs)
        
        # Customize querysets
        self.fields['category'].queryset = Category.active.all()
        self.fields['tags'].queryset = Tag.objects.all()
        self.fields['actors'].queryset = Actor.objects.filter(is_active=True)


class SearchForm(forms.Form):
    """Search form."""
    
    q = forms.CharField(
        max_length=255,
        required=False,
        widget=forms.TextInput(attrs={
            'class': 'search-input',
            'placeholder': 'Поиск видео...',
            'autocomplete': 'off',
        })
    )
    
    category = forms.ModelChoiceField(
        queryset=Category.active.all(),
        required=False,
        empty_label="Все категории",
        widget=forms.Select(attrs={
            'class': 'input-field',
        })
    )
    
    sort = forms.ChoiceField(
        choices=[
            ('recent', 'Новые'),
            ('popular', 'Популярные'),
            ('trending', 'Трендовые'),
            ('rating', 'По рейтингу'),
        ],
        required=False,
        initial='recent',
        widget=forms.Select(attrs={
            'class': 'input-field',
        })
    )


class CommentForm(forms.Form):
    """Comment form."""
    
    content = forms.CharField(
        widget=forms.Textarea(attrs={
            'class': 'input-field',
            'placeholder': 'Добавить комментарий...',
            'rows': 3,
            'maxlength': 1000,
        }),
        max_length=1000,
        min_length=1,
    )
    
    parent_id = forms.IntegerField(
        required=False,
        widget=forms.HiddenInput()
    )
