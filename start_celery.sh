#!/bin/bash

# Start Celery worker for video processing
cd /media/almiron/F6360756360716F1/Django+HTMX

# Activate virtual environment
source venv/bin/activate

# Set Django settings
export DJANGO_SETTINGS_MODULE=config.settings.local

# Start Celery worker
python3 -m celery -A config worker --loglevel=info
