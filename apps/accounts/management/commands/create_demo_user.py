"""
Management command to create a demo user for testing.
"""
from django.core.management.base import BaseCommand
from django.contrib.auth import get_user_model
from apps.accounts.models import Profile

User = get_user_model()


class Command(BaseCommand):
    """Create a demo user for testing."""
    
    help = 'Create a demo user with username "demo" and password "demo123"'
    
    def handle(self, *args, **options):
        """Handle the command."""
        username = 'demo'
        email = 'demo@example.com'
        password = 'demo123'
        
        # Check if user already exists
        if User.objects.filter(username=username).exists():
            self.stdout.write(
                self.style.WARNING(f'User "{username}" already exists')
            )
            return
        
        # Create user
        user = User.objects.create_user(
            username=username,
            email=email,
            password=password,
            first_name='Demo',
            last_name='User'
        )
        
        # Create profile
        Profile.objects.create(
            user=user,
            bio='Демонстрационный пользователь для тестирования',
            location='Москва, Россия',
            is_public=True
        )
        
        self.stdout.write(
            self.style.SUCCESS(
                f'Successfully created demo user:\n'
                f'Username: {username}\n'
                f'Password: {password}\n'
                f'Email: {email}'
            )
        )
