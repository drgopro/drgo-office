#!/usr/bin/env bash
set -euo pipefail

# 오픈소스 delivery-tracker 셀프호스팅 설치 (Forge 서버에서 실행)
# ─────────────────────────────────────────────────────────────────
# 목적: 유료 호스팅 API(apis.tracker.delivery) 대신 같은 엔진의 오픈소스
#       추적 서버를 이 서버 안에서 직접 돌린다 (외부 API 비용 0원).
#
# 오피스 앱은 이미 셀프호스팅을 지원한다 (DeliveryTrackerClient):
#   - DELIVERY_TRACKER_CLIENT_ID/SECRET 이 있으면 → 유료 호스팅 사용
#   - 없고 DELIVERY_TRACKER_URL 만 있으면     → 셀프호스팅 사용
#
# 설치 후 Forge env 변경:
#   1) DELIVERY_TRACKER_CLIENT_ID / DELIVERY_TRACKER_CLIENT_SECRET 삭제
#   2) DELIVERY_TRACKER_URL=http://127.0.0.1:8150/graphql 추가
#   3) Deploy Now → 배송 정보 창에서 '배송상태 새로고침'으로 확인
#
# 사용법 (Forge 서버 SSH):
#   bash install-delivery-tracker.sh
# ─────────────────────────────────────────────────────────────────

PORT=8150

if command -v docker >/dev/null 2>&1; then
    echo "[1/3] Docker 발견 — 이미지 빌드로 설치합니다."
    rm -rf /tmp/delivery-tracker-src
    git clone --depth 1 https://github.com/shlee322/delivery-tracker /tmp/delivery-tracker-src
    docker build -t delivery-tracker /tmp/delivery-tracker-src

    echo "[2/3] 기존 컨테이너 정리 후 기동 (127.0.0.1:${PORT} — 외부 비공개)"
    docker rm -f delivery-tracker 2>/dev/null || true
    docker run -d --name delivery-tracker --restart unless-stopped \
        -p 127.0.0.1:${PORT}:8080 delivery-tracker

    echo "[3/3] 동작 확인"
    sleep 3
    curl -s -X POST "http://127.0.0.1:${PORT}/graphql" \
        -H 'Content-Type: application/json' \
        -d '{"query":"query{__typename}"}' && echo
    echo
    echo "완료 — Forge env에서 DELIVERY_TRACKER_URL=http://127.0.0.1:${PORT}/graphql 설정 후"
    echo "DELIVERY_TRACKER_CLIENT_ID/SECRET 을 삭제하고 Deploy 하세요."
else
    cat <<'MSG'
Docker가 없습니다. 두 가지 중 선택하세요:

  A) Docker 설치 후 이 스크립트 재실행 (권장 — 관리 간단):
       curl -fsSL https://get.docker.com | sudo sh

  B) Node로 직접 실행 (Forge Daemon 등록):
       git clone https://github.com/shlee322/delivery-tracker ~/delivery-tracker
       cd ~/delivery-tracker
       corepack enable && pnpm install && pnpm -r build
       # Forge > Daemons 에 등록: 디렉터리 ~/delivery-tracker,
       # 명령은 packages/http 의 서버 시작 스크립트 (README/package.json 확인)
       # 포트가 8150이 아니면 DELIVERY_TRACKER_URL 포트를 맞추세요.
MSG
    exit 1
fi
