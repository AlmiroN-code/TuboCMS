#!/bin/bash
set -e

echo "🚀 RexTube Production Setup Script"
echo "=================================="
echo ""

# Проверка что мы в правильной директории
if [ ! -f "composer.json" ]; then
    echo "❌ Error: composer.json not found. Run this script from project root."
    exit 1
fi

echo "📁 Current directory: $(pwd)"
echo "👤 Current user: $(whoami)"
echo ""

# Проверка Redis
echo "🔍 Checking Redis..."
if ! php -m | grep -q redis; then
    echo "⚠️  Redis PHP extension not found!"
    echo ""
    echo "Please install Redis first:"
    echo "  sudo apt update"
    echo "  sudo apt install -y redis-server php8.4-redis"
    echo "  sudo systemctl start redis-server"
    echo "  sudo systemctl enable redis-server"
    echo "  sudo systemctl restart php8.4-fpm"
    echo ""
    read -p "Continue anyway? (y/N) " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        exit 1
    fi
else
    echo "✅ Redis extension found"
fi
echo ""

# Генерация APP_SECRET
echo "🔐 Generating APP_SECRET..."
APP_SECRET=$(php -r "echo bin2hex(random_bytes(16));")
echo "Generated: $APP_SECRET"
echo ""

# Создание .env.local из .env.production
echo "📝 Creating .env.local..."
if [ -f ".env.production" ]; then
    cp .env.production .env.local
    # Замена APP_SECRET
    sed -i "s/GENERATE_NEW_SECRET_HERE/$APP_SECRET/" .env.local
    echo "✅ .env.local created"
else
    echo "⚠️  .env.production not found, skipping .env.local creation"
fi
echo ""

# Установка зависимостей
echo "📦 Installing PHP dependencies..."
composer install --no-dev --optimize-autoloader --no-interaction
echo ""

echo "📦 Installing Node.js dependencies..."
npm install
echo ""

echo "🏗️  Building frontend assets..."
npm run build
echo ""

# Настройка прав
echo "🔒 Setting permissions..."
chmod -R 755 .
chmod -R 775 var/cache var/log 2>/dev/null || true
chmod 600 .env.local 2>/dev/null || true

# Создание директорий для медиа
mkdir -p public/media/{avatars,covers,posters,previews,videos,site}
chmod -R 775 public/media
echo ""

# Очистка кеша
echo "🧹 Clearing cache..."
php bin/console cache:clear --env=prod --no-warmup
php bin/console cache:warmup --env=prod
echo ""

# База данных
echo "💾 Setting up database..."
php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration
echo ""

echo "📬 Setting up Messenger transports..."
php bin/console messenger:setup-transports
echo ""

# Установка ассетов
echo "📦 Installing assets..."
php bin/console assets:install public --symlink --relative
echo ""

# Проверка конфигурации
echo "🔍 Checking configuration..."
php bin/console about
echo ""

echo "✅ Setup completed successfully!"
echo ""
echo "📋 Next steps:"
echo "   1. Configure Nginx document root to: $(pwd)/public"
echo "   2. Setup Supervisor for Messenger workers (see DEPLOYMENT.md)"
echo "   3. Configure SSL certificate via HestiaCP"
echo "   4. Test the site: https://rextube.online"
echo ""
echo "🔧 Useful commands:"
echo "   - Check logs: tail -f var/log/prod.log"
echo "   - Clear cache: php bin/console cache:clear --env=prod"
echo "   - Run migrations: php bin/console doctrine:migrations:migrate"
echo "   - Check workers: sudo supervisorctl status rextube-messenger:*"
echo ""
