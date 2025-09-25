"""
Local development settings for Video Portal project.
"""
from .base import *

DEBUG = True
ALLOWED_HOSTS = ['localhost', '127.0.0.1', '0.0.0.0']

# Database for local development
DATABASES = {
    'default': {
        'ENGINE': 'django.db.backends.sqlite3',
        'NAME': BASE_DIR / 'db.sqlite3',
    }
}

# Disable cache for development
CACHES = {
    'default': {
        'BACKEND': 'django.core.cache.backends.dummy.DummyCache',
    }
}

# Use database sessions instead of cache sessions
SESSION_ENGINE = 'django.contrib.sessions.backends.db'

# Authentication settings
LOGIN_REDIRECT_URL = '/'  # Redirect to home page after login
LOGOUT_REDIRECT_URL = '/'  # Redirect to home page after logout
LOGIN_URL = '/accounts/login/'  # URL for login page

# Email backend for development
EMAIL_BACKEND = 'django.core.mail.backends.console.EmailBackend'

# Disable Celery for development (optional)
# CELERY_TASK_ALWAYS_EAGER = True
# CELERY_TASK_EAGER_PROPAGATES = True

# Development-specific settings
INTERNAL_IPS = [
    '127.0.0.1',
]

# Django Debug Toolbar (disabled for now)
# if DEBUG:
#     try:
#         import debug_toolbar
#         INSTALLED_APPS += ['debug_toolbar']
#         MIDDLEWARE += ['debug_toolbar.middleware.DebugToolbarMiddleware']
#     except ImportError:
#         pass
