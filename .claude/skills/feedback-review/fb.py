#!/usr/bin/env python3
"""피드백 AI 검수 API 클라이언트 — 토큰은 ~/.claude/agent-dashboard.env (작업 현황 훅과 같은 설정)에서 읽는다.

사용:
  fb.py list review|approved            검수 대상 / 승인되어 작업할 글 (JSON)
  fb.py attachment <첨부ID> <저장경로>    첨부 원본 내려받기 (스크린샷 확인용)
  fb.py review <글ID> <json파일>         검수 등록 {category, effort?, summary, proposal?}
  fb.py result <글ID> <json파일>         반영 결과 기록 {result_note, done?}
"""
import json
import os
import sys
import urllib.error
import urllib.request

ENV_FILE = os.path.expanduser('~/.claude/agent-dashboard.env')


def load_env():
    cfg = {}
    try:
        with open(ENV_FILE, encoding='utf-8') as f:
            for line in f:
                line = line.strip()
                if line and not line.startswith('#') and '=' in line:
                    k, v = line.split('=', 1)
                    cfg[k.strip()] = v.strip().strip('"\'')
    except OSError:
        sys.exit(f'설정 파일이 없습니다: {ENV_FILE}')
    url, token = cfg.get('AGENT_EVENTS_URL', ''), cfg.get('AGENT_EVENTS_TOKEN', '')
    if not url or not token:
        sys.exit('AGENT_EVENTS_URL / AGENT_EVENTS_TOKEN 이 설정되어 있지 않습니다.')
    # https://office.drgo.pro/api/agent-events/ingest → https://office.drgo.pro
    return url.split('/api/')[0], token


def call(method, path, body=None, raw=False):
    base, token = load_env()
    data = json.dumps(body).encode() if body is not None else None
    req = urllib.request.Request(base + path, data=data, method=method, headers={
        'X-Agent-Token': token, 'Accept': 'application/json', 'Content-Type': 'application/json',
    })
    try:
        with urllib.request.urlopen(req, timeout=30) as res:
            payload = res.read()
    except urllib.error.HTTPError as e:
        sys.exit(f'HTTP {e.code}: {e.read().decode(errors="replace")[:500]}')
    except urllib.error.URLError as e:
        sys.exit(f'연결 실패: {e.reason}')
    return payload if raw else json.loads(payload)


def main(argv):
    if len(argv) == 3 and argv[1] == 'list' and argv[2] in ('review', 'approved'):
        out = call('GET', f'/api/agent/feedback?scope={argv[2]}')
    elif len(argv) == 4 and argv[1] == 'attachment':
        data = call('GET', f'/api/agent/feedback-attachments/{int(argv[2])}', raw=True)  # 받은 뒤에 파일을 연다 (실패 시 기존 파일 보존)
        with open(argv[3], 'wb') as f:
            f.write(data)
        out = {'saved': argv[3]}
    elif len(argv) == 4 and argv[1] in ('review', 'result'):
        with open(argv[3], encoding='utf-8') as f:
            out = call('POST', f'/api/agent/feedback/{int(argv[2])}/{argv[1]}', json.load(f))
    else:
        sys.exit(__doc__)
    print(json.dumps(out, ensure_ascii=False, indent=2))


if __name__ == '__main__':
    main(sys.argv)
