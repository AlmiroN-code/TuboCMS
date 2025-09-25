#!/bin/bash

# Development server startup script for Video Portal

echo "🚀 Starting Video Portal Development Environment"
echo "================================================"

# Check if virtual environment exists
if [ ! -d "venv" ]; then
    echo "❌ Virtual environment not found. Creating..."
    python3 -m venv venv
    echo "✅ Virtual environment created"
fi

# Activate virtual environment
echo "🔧 Activating virtual environment..."
source venv/bin/activate

# Install dependencies
echo "📦 Installing dependencies..."
pip install -r requirements/local.txt

# Check if .env file exists
if [ ! -f ".env" ]; then
    echo "⚠️  .env file not found. Copying from example..."
    cp env.example .env
    echo "✅ .env file created. Please edit it with your settings."
fi

# Run migrations
echo "🗄️  Running database migrations..."
python manage.py migrate

# Create superuser if it doesn't exist
echo "👤 Checking for superuser..."
python manage.py shell -c "
from django.contrib.auth import get_user_model
User = get_user_model()
if not User.objects.filter(is_superuser=True).exists():
    print('Creating superuser...')
    User.objects.create_superuser('admin', 'admin@example.com', 'admin123')
    print('Superuser created: admin/admin123')
else:
    print('Superuser already exists')
"

# Create sample data
echo "📊 Creating sample data..."
python manage.py create_sample_data

# Create demo user
echo "👥 Creating demo user..."
python manage.py create_demo_user

# Collect static files
echo "📁 Collecting static files..."
python manage.py collectstatic --noinput

echo ""
echo "🎉 Setup complete!"
echo ""
echo "📋 Available users:"
echo "   Admin: admin / admin123"
echo "   Demo:  demo  / demo123"
echo ""
echo "🌐 URLs:"
echo "   Main site: http://localhost:8000"
echo "   Admin:     http://localhost:8000/admin"
echo ""
echo "🔄 To start the development server:"
echo "   python manage.py runserver"
echo ""
echo "⚡ To start Celery worker (in another terminal):"
echo "   celery -A config worker --loglevel=info"
echo ""
echo "📊 To start Celery beat (in another terminal):"
echo "   celery -A config beat --loglevel=info"
echo ""
echo "Happy coding! 🚀"
