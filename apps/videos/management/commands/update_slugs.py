from django.core.management.base import BaseCommand
from apps.videos.models import Category, Tag, Actor, Video


class Command(BaseCommand):
    help = 'Update slugs for all models'

    def handle(self, *args, **options):
        # Update categories
        categories = Category.objects.filter(slug__isnull=True)
        for category in categories:
            category.save()  # This will trigger slug generation
            self.stdout.write(f'Updated category: {category.name} -> {category.slug}')
        
        # Update tags
        tags = Tag.objects.filter(slug__isnull=True)
        for tag in tags:
            tag.save()
            self.stdout.write(f'Updated tag: {tag.name} -> {tag.slug}')
        
        # Update actors
        actors = Actor.objects.filter(slug__isnull=True)
        for actor in actors:
            actor.save()
            self.stdout.write(f'Updated actor: {actor.name} -> {actor.slug}')
        
        # Update videos
        videos = Video.objects.filter(slug__isnull=True)
        for video in videos:
            video.save()
            self.stdout.write(f'Updated video: {video.title} -> {video.slug}')
        
        self.stdout.write(self.style.SUCCESS('Slug update completed!'))
