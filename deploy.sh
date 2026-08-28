#!/bin/bash
# ═══════════════════════════════════════════════════════════════
# HOMI DEVELOPER CRM — DEPLOYMENT SCRIPT
# Jalankan via SSH Terminal di cPanel
# ═══════════════════════════════════════════════════════════════

set -e

echo "🚀 Deploying Homi Developer CRM..."

# 1. Pull kode terbaru & bersihkan cache lama
echo "📦 Pulling latest code..."
git pull origin main
php artisan optimize:clear

# 2. Install dependencies PHP (tanpa dev)
echo "📚 Installing PHP dependencies..."
composer install --no-dev --optimize-autoloader --no-interaction

# 3. Run migrasi database saja (PENTING: JANGAN PERNAH RUN db:seed ATAU migrate:fresh AGAR DATA PROD TIDAK TERHAPUS)
echo "🗄️ Running database migrations..."
php artisan migrate --force

# 4. Cache config, route, dan views untuk performa tinggi
echo "⚡ Optimizing for production..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 6. Link storage
echo "🔗 Linking storage..."
php artisan storage:link

echo ""
echo "✅ Deployment selesai!"
echo "🌐 Buka https://crm.homi.id untuk mengakses CRM."
