#!/bin/bash
# ═══════════════════════════════════════════════════════════════
# HOMI DEVELOPER CRM — DEPLOYMENT SCRIPT
# Jalankan via SSH Terminal di cPanel
# ═══════════════════════════════════════════════════════════════

set -e

echo "🚀 Deploying Homi Developer CRM..."

# 1. Pull kode terbaru
echo "📦 Pulling latest code..."
git pull origin master

# 2. Install dependencies PHP (tanpa dev)
echo "📚 Installing PHP dependencies..."
composer install --no-dev --optimize-autoloader --no-interaction

# 3. Cache config, route, dan views
echo "⚡ Optimizing for production..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 4. Run migrasi database
echo "🗄️ Running database migrations..."
php artisan migrate --force

# 5. Link storage
echo "🔗 Linking storage..."
php artisan storage:link

echo ""
echo "✅ Deployment selesai!"
echo "🌐 Buka https://crm.homi.id untuk mengakses CRM."
