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
    # 전 단계를 &&로 묶어 하나라도 실패하면 뒤 단계(특히 배포 완료 스탬프)를 건너뛴다
    $FORGE_PHP artisan migrate --force \
    && $FORGE_PHP artisan config:cache \
    && $FORGE_PHP artisan route:cache \
    && $FORGE_PHP artisan view:cache \
    && $FORGE_PHP artisan event:cache \
    && $FORGE_PHP artisan storage:link \
    && $FORGE_PHP artisan queue:restart `# 큐 worker가 새 코드를 쓰도록 재시작 신호 (worker가 없으면 아무 영향 없음)` \
    && $FORGE_PHP artisan deploy:stamp `# 배포 완료 스탬프 — 반드시 마지막. 열린 화면들이 이 값 변화로 '새로운 업데이트' 새로고침 안내를 띄운다` \
    || { echo 'Deploy step failed — 완료 스탬프를 남기지 않습니다'; exit 1; }
fi
