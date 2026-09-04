#!/bin/bash
# Forge Deployment Script
# Copy this script content into Forge > Site > Deployments > Deploy Script

cd /home/forge/your-domain.com

git pull origin $FORGE_SITE_BRANCH

$FORGE_COMPOSER install --no-dev --no-interaction --prefer-dist --optimize-autoloader

# npm 빌드 없음 — 이 앱은 Vite 산출물을 쓰지 않는다 (전 화면 인라인 CSS/CDN).
# 프론트엔드 빌드를 도입하기 전까지 npm ci / npm run build를 넣지 말 것 (배포 5분 → 1분).

( flock -w 10 9 || exit 1
    echo 'Restarting FPM...'; sudo -S service $FORGE_PHP_FPM reload ) 9>/tmp/fpmlock

if [ -f artisan ]; then
    $FORGE_PHP artisan migrate --force
    $FORGE_PHP artisan config:cache
    $FORGE_PHP artisan route:cache
    $FORGE_PHP artisan view:cache
    $FORGE_PHP artisan event:cache
    $FORGE_PHP artisan storage:link
fi
