"""
Management command to create sample data for development.
"""
from django.core.management.base import BaseCommand
from django.contrib.auth import get_user_model
from django.utils import timezone
from django.utils.text import slugify
from apps.videos.models import Video, Category, Tag, Actor
from apps.accounts.models import Profile
import random
from datetime import timedelta

User = get_user_model()


class Command(BaseCommand):
    """Create sample data for development."""
    
    help = 'Create sample categories, tags, actors, and videos for development'
    
    def add_arguments(self, parser):
        """Add command arguments."""
        parser.add_argument(
            '--clear',
            action='store_true',
            help='Clear existing data before creating new data',
        )
    
    def handle(self, *args, **options):
        """Handle the command."""
        if options['clear']:
            self.stdout.write('Clearing existing data...')
            Video.objects.all().delete()
            Category.objects.all().delete()
            Tag.objects.all().delete()
            Actor.objects.all().delete()
            User.objects.filter(is_superuser=False).delete()
        
        self.stdout.write('Creating sample data...')
        
        # Create categories
        categories_data = [
            {'name': 'Развлечения', 'description': 'Развлекательный контент'},
            {'name': 'Образование', 'description': 'Образовательные видео'},
            {'name': 'Музыка', 'description': 'Музыкальные видео'},
            {'name': 'Спорт', 'description': 'Спортивные видео'},
            {'name': 'Технологии', 'description': 'Технологические видео'},
            {'name': 'Кулинария', 'description': 'Кулинарные видео'},
            {'name': 'Путешествия', 'description': 'Видео о путешествиях'},
            {'name': 'Автомобили', 'description': 'Автомобильные видео'},
            {'name': 'Игры', 'description': 'Игровые видео'},
            {'name': 'Фильмы', 'description': 'Кино и сериалы'},
        ]
        
        categories = []
        for cat_data in categories_data:
            slug = slugify(cat_data['name'])
            category, created = Category.objects.get_or_create(
                slug=slug,
                defaults=cat_data
            )
            categories.append(category)
            if created:
                self.stdout.write(f'Created category: {category.name}')
        
        # Create tags
        tags_data = [
            {'name': 'популярное', 'color': '#FF6B6B'},
            {'name': 'новое', 'color': '#4ECDC4'},
            {'name': 'тренд', 'color': '#45B7D1'},
            {'name': 'вирусное', 'color': '#96CEB4'},
            {'name': 'образовательное', 'color': '#FFEAA7'},
            {'name': 'развлекательное', 'color': '#DDA0DD'},
            {'name': 'музыкальное', 'color': '#98D8C8'},
            {'name': 'спортивное', 'color': '#F7DC6F'},
            {'name': 'технологическое', 'color': '#BB8FCE'},
            {'name': 'кулинарное', 'color': '#85C1E9'},
        ]
        
        tags = []
        for tag_data in tags_data:
            slug = slugify(tag_data['name'])
            tag, created = Tag.objects.get_or_create(
                slug=slug,
                defaults=tag_data
            )
            tags.append(tag)
            if created:
                self.stdout.write(f'Created tag: {tag.name}')
        
        # Create actors
        actors_data = [
            {'name': 'Алексей Иванов', 'bio': 'Популярный блогер'},
            {'name': 'Мария Петрова', 'bio': 'Музыкальный исполнитель'},
            {'name': 'Дмитрий Сидоров', 'bio': 'Технический эксперт'},
            {'name': 'Елена Козлова', 'bio': 'Кулинарный блогер'},
            {'name': 'Сергей Волков', 'bio': 'Спортивный комментатор'},
            {'name': 'Анна Морозова', 'bio': 'Путешественница'},
            {'name': 'Михаил Лебедев', 'bio': 'Автомобильный эксперт'},
            {'name': 'Ольга Соколова', 'bio': 'Геймер'},
            {'name': 'Павел Новиков', 'bio': 'Кинорежиссер'},
            {'name': 'Татьяна Федорова', 'bio': 'Образовательный контент'},
        ]
        
        actors = []
        for actor_data in actors_data:
            slug = slugify(actor_data['name'])
            actor, created = Actor.objects.get_or_create(
                slug=slug,
                defaults=actor_data
            )
            actors.append(actor)
            if created:
                self.stdout.write(f'Created actor: {actor.name}')
        
        # Create users
        users_data = [
            {'username': 'user1', 'email': 'user1@example.com', 'first_name': 'Иван', 'last_name': 'Петров'},
            {'username': 'user2', 'email': 'user2@example.com', 'first_name': 'Мария', 'last_name': 'Иванова'},
            {'username': 'user3', 'email': 'user3@example.com', 'first_name': 'Алексей', 'last_name': 'Сидоров'},
            {'username': 'user4', 'email': 'user4@example.com', 'first_name': 'Елена', 'last_name': 'Козлова'},
            {'username': 'user5', 'email': 'user5@example.com', 'first_name': 'Сергей', 'last_name': 'Волков'},
        ]
        
        users = []
        for user_data in users_data:
            user, created = User.objects.get_or_create(
                username=user_data['username'],
                defaults=user_data
            )
            if created:
                user.set_password('password123')
                user.save()
                
                # Create profile
                Profile.objects.create(
                    user=user,
                    bio=f'Профиль пользователя {user.first_name}',
                    location='Москва, Россия'
                )
                
                self.stdout.write(f'Created user: {user.username}')
            users.append(user)
        
        # Create videos
        video_titles = [
            'Как приготовить идеальный борщ',
            'Обзор новейших технологий 2024',
            'Топ-10 музыкальных хитов',
            'Путешествие по Камчатке',
            'Ремонт автомобиля своими руками',
            'Играем в новую RPG',
            'Кинообзор: лучшие фильмы года',
            'Уроки программирования на Python',
            'Спортивные тренировки дома',
            'Кулинарные секреты от шеф-повара',
            'Путешествие по Европе',
            'Технологии будущего',
            'Музыкальная терапия',
            'Экстремальные виды спорта',
            'Автомобильные гонки',
        ]
        
        video_descriptions = [
            'Подробный рецепт приготовления борща с секретными ингредиентами.',
            'Обзор самых интересных технологических новинок этого года.',
            'Подборка лучших музыкальных композиций для любого настроения.',
            'Удивительное путешествие по дикой природе Камчатки.',
            'Пошаговая инструкция по ремонту автомобиля в домашних условиях.',
            'Прохождение новой ролевой игры с подробными комментариями.',
            'Анализ лучших фильмов года и их влияние на кинематограф.',
            'Базовый курс программирования для начинающих.',
            'Эффективные упражнения для поддержания формы дома.',
            'Профессиональные советы от опытного шеф-повара.',
            'Культурное путешествие по европейским столицам.',
            'Прогнозы развития технологий в ближайшие годы.',
            'Как музыка влияет на настроение и самочувствие.',
            'Экстремальные виды спорта для смелых людей.',
            'Захватывающие автомобильные гонки и их история.',
        ]
        
        for i, title in enumerate(video_titles):
            # Create a dummy video file (in real scenario, this would be an actual file)
            slug = slugify(title)
            video, created = Video.objects.get_or_create(
                slug=slug,
                defaults={
                    'title': title,
                    'description': video_descriptions[i % len(video_descriptions)],
                    'author': random.choice(users),
                    'category': random.choice(categories),
                    'duration': random.randint(60, 3600),  # 1 minute to 1 hour
                    'file_size': random.randint(10 * 1024 * 1024, 500 * 1024 * 1024),  # 10MB to 500MB
                    'view_count': random.randint(0, 10000),
                    'like_count': random.randint(0, 1000),
                    'dislike_count': random.randint(0, 100),
                    'is_published': True,
                    'published_at': timezone.now() - timedelta(days=random.randint(1, 365)),
                    'processing_status': 'completed',
                }
            )
            
            if created:
                # Add random tags and actors
                video.tags.set(random.sample(tags, random.randint(1, 4)))
                video.actors.set(random.sample(actors, random.randint(0, 3)))
                
                self.stdout.write(f'Created video: {video.title}')
        
        self.stdout.write(
            self.style.SUCCESS(
                f'Successfully created sample data:\n'
                f'- {len(categories)} categories\n'
                f'- {len(tags)} tags\n'
                f'- {len(actors)} actors\n'
                f'- {len(users)} users\n'
                f'- {Video.objects.count()} videos'
            )
        )
