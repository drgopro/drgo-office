<!DOCTYPE html>
<html lang="ko" data-theme="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>방송 세팅 에디터 - 닥터고블린 오피스</title>
<script>(function(){var t=localStorage.getItem('drgo_theme');if(t)document.documentElement.setAttribute('data-theme',t);})();</script>
<style>
:root{--color-background-primary:#1c1c1c;--color-background-secondary:#272727;--color-background-tertiary:#111;--color-text-primary:#f0ebe2;--color-text-secondary:#a09890;--color-text-tertiary:#6b6560;--color-border-primary:#555;--color-border-secondary:#3a3a3a;--color-border-tertiary:#2a2a2a}
[data-theme="light"]{--color-background-primary:#fff;--color-background-secondary:#f4f5f7;--color-background-tertiary:#eceef2;--color-text-primary:#1a1e28;--color-text-secondary:#5a6070;--color-text-tertiary:#8a8e98;--color-border-primary:#999;--color-border-secondary:#b8bcc8;--color-border-tertiary:#d0d5dd}
*{box-sizing:border-box;margin:0;padding:0}
#app{display:flex;flex-direction:column;height:100vh;min-height:640px;position:relative}
#toolbar{display:flex;align-items:center;gap:3px;padding:5px 8px;border-bottom:0.5px solid var(--color-border-tertiary);flex-wrap:wrap;background:var(--color-background-primary);position:relative;z-index:1}
.tb-btn{display:flex;align-items:center;gap:3px;padding:4px 7px;border:0.5px solid var(--color-border-secondary);border-radius:7px;background:transparent;color:var(--color-text-primary);font-size:11px;cursor:pointer;white-space:nowrap}
.tb-btn:hover{background:var(--color-background-secondary)}
.tb-btn.active{background:var(--color-background-secondary);border-color:var(--color-border-primary)}
.tb-btn.blue{border-color:#185FA5;color:#185FA5}.tb-btn.blue:hover{background:#E6F1FB}
.tb-btn.green{border-color:#3B6D11;color:#3B6D11}.tb-btn.green:hover{background:#EAF3DE}
.tb-btn.blue-f{border-color:#0C447C;color:#0C447C;background:#E6F1FB}.tb-btn.blue-f:hover{background:#B5D4F4}
.tb-btn.green-f{border-color:#27500A;color:#27500A;background:#EAF3DE}.tb-btn.green-f:hover{background:#C0DD97}
.tb-btn.danger{border-color:#A32D2D;color:#A32D2D}.tb-btn.danger:hover{background:#FCEBEB}
.tb-sep{width:0.5px;height:20px;background:var(--color-border-tertiary);margin:0 2px;flex-shrink:0}
.tb-group{display:flex;flex-direction:column;align-items:center;gap:2px}
.tb-group-label{font-size:9px;color:var(--color-text-tertiary);letter-spacing:.04em;white-space:nowrap}
.tb-row{display:flex;gap:3px}
#mode-badge{font-size:11px;color:var(--color-text-secondary);padding:3px 7px;background:var(--color-background-secondary);border-radius:8px}
#main{display:flex;flex:1;overflow:hidden}
#sidebar{width:188px;flex-shrink:0;border-right:0.5px solid var(--color-border-tertiary);display:flex;flex-direction:column;background:var(--color-background-primary)}
#sb-header{display:flex;align-items:center;justify-content:space-between;padding:7px 9px;border-bottom:0.5px solid var(--color-border-tertiary)}
#sb-header span{font-size:11px;font-weight:500;color:var(--color-text-secondary);text-transform:uppercase;letter-spacing:.06em}
.sb-header-btns{display:flex;gap:4px}
#sb-add-btn{padding:3px 7px;border:0.5px solid var(--color-border-secondary);border-radius:6px;background:transparent;color:var(--color-text-primary);font-size:11px;cursor:pointer}
#sb-add-btn:hover{background:var(--color-background-secondary)}
#sb-reset-btn{padding:3px 7px;border:0.5px solid #A32D2D;border-radius:6px;background:transparent;color:#A32D2D;font-size:11px;cursor:pointer}
#sb-reset-btn:hover{background:#FCEBEB}
#sb-list{flex:1;overflow-y:auto;padding:6px}
.sb-cat-row{display:flex;align-items:center;justify-content:space-between;padding:5px 4px 2px}
.sb-cat-label{font-size:10px;font-weight:500;color:var(--color-text-secondary);text-transform:uppercase;letter-spacing:.07em}
.sb-cat-add{font-size:10px;padding:2px 6px;border:0.5px solid var(--color-border-tertiary);border-radius:5px;background:transparent;color:var(--color-text-secondary);cursor:pointer}
.sb-cat-add:hover{background:var(--color-background-secondary);color:var(--color-text-primary)}
.sb-item{display:flex;align-items:center;gap:7px;padding:6px 7px;border-radius:8px;border:0.5px solid var(--color-border-tertiary);margin-bottom:3px;cursor:grab;background:var(--color-background-primary);user-select:none;position:relative}
.sb-item:hover{background:var(--color-background-secondary);border-color:var(--color-border-secondary)}
.sb-item:hover .sb-item-actions{opacity:1}
.sb-icon-box{width:26px;height:26px;border-radius:6px;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:14px}
.sb-item-name{flex:1;font-size:12px;color:var(--color-text-primary);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.sb-item-actions{opacity:0;display:flex;gap:2px;transition:opacity .15s}
.sb-act-btn{width:20px;height:20px;border:none;border-radius:4px;background:transparent;color:var(--color-text-secondary);font-size:11px;cursor:pointer;display:flex;align-items:center;justify-content:center}
.sb-act-btn:hover{background:var(--color-background-secondary);color:var(--color-text-primary)}
.sb-act-btn.del:hover{background:#FCEBEB;color:#A32D2D}
#canvas-wrap{flex:1;overflow:auto;background:var(--color-background-tertiary)}
#canvas{position:relative;width:1400px;height:900px;background:#f8f7f4}
#canvas.grid-on{background-image:linear-gradient(to right,rgba(100,95,85,0.18) 1px,transparent 1px),linear-gradient(to bottom,rgba(100,95,85,0.18) 1px,transparent 1px);background-size:20px 20px}
.device{position:absolute;cursor:move;user-select:none}
.device-inner{border:1.5px solid var(--color-border-secondary);border-radius:10px;padding:8px 12px;min-width:128px;text-align:center;transition:border-color .15s}
.device.selected .device-inner{border-color:#185FA5;box-shadow:0 0 0 2px #B5D4F4}
.device.builtin-device .device-inner{border:1.5px dashed #EF9F27;background:#fffdf7}
.device.builtin-device.selected .device-inner{border:1.5px dashed #185FA5;box-shadow:0 0 0 2px #B5D4F4}
.builtin-badge{display:inline-block;margin-top:3px;font-size:9px;font-weight:600;color:#854F0B;background:#FAEEDA;border:0.5px solid #EF9F27;border-radius:4px;padding:1px 5px}
.device-icon{font-size:22px;line-height:1;margin-bottom:4px}
.device-label{font-size:11px;font-weight:500;color:var(--color-text-primary);line-height:1.3}
.device-sublabel{font-size:10px;color:var(--color-text-secondary);margin-top:2px}
.port{width:10px;height:10px;border-radius:50%;border:1.5px solid var(--color-border-primary);position:absolute;cursor:crosshair;z-index:10;transition:all .1s;display:none}
.port:hover,.port.active{background:#185FA5;border-color:#185FA5;transform:scale(1.4)}
.port.output{right:-5px;top:50%;transform:translateY(-50%)}
.port.output:hover,.port.output.active{transform:translateY(-50%) scale(1.4)}
.port.input{left:-5px;top:50%;transform:translateY(-50%)}
.port.input:hover,.port.input.active{transform:translateY(-50%) scale(1.4)}
.port.top{top:-5px;left:50%;transform:translateX(-50%)}
.port.top:hover,.port.top.active{transform:translateX(-50%) scale(1.4)}
.port.bottom{bottom:-5px;left:50%;transform:translateX(-50%)}
.port.bottom:hover,.port.bottom.active{transform:translateX(-50%) scale(1.4)}
#svg-layer{position:absolute;top:0;left:0;width:100%;height:100%;pointer-events:none;overflow:visible}
.cable{pointer-events:stroke;cursor:pointer;stroke-width:2.5;fill:none;stroke-linecap:round}
.cable:hover{stroke-width:4}
.cable-builtin-line{fill:none;stroke-linecap:round;stroke-dasharray:6 4;stroke-width:1.5;opacity:.7}
#prop-panel{width:208px;flex-shrink:0;border-left:0.5px solid var(--color-border-tertiary);background:var(--color-background-primary);padding:12px;overflow-y:auto}
.prop-title{font-size:11px;font-weight:500;color:var(--color-text-secondary);margin-bottom:8px;text-transform:uppercase;letter-spacing:.06em}
.prop-row{margin-bottom:10px}
.prop-row label{display:block;font-size:11px;color:var(--color-text-secondary);margin-bottom:3px}
.prop-row input,.prop-row select{width:100%;padding:5px 7px;border:0.5px solid var(--color-border-secondary);border-radius:8px;background:var(--color-background-primary);color:var(--color-text-primary);font-size:12px}
.color-row{display:flex;gap:5px;flex-wrap:wrap;margin-top:5px}
.cswatch{width:20px;height:20px;border-radius:50%;cursor:pointer;border:2px solid transparent;flex-shrink:0}
.cswatch.active{border-color:var(--color-text-primary)}
.ccswatch{width:20px;height:20px;border-radius:50%;cursor:pointer;border:2.5px solid transparent;flex-shrink:0;transition:transform .1s}
.ccswatch:hover{transform:scale(1.15)}
.ccswatch.active{border-color:var(--color-text-primary);transform:scale(1.15)}
.del-btn{width:100%;padding:6px;border:0.5px solid #A32D2D;border-radius:8px;background:transparent;color:#A32D2D;font-size:12px;cursor:pointer;margin-top:8px}
.del-btn:hover{background:#FCEBEB}
.no-sel{color:var(--color-text-tertiary);font-size:12px;text-align:center;padding:20px 0}
#status-bar{padding:4px 12px;font-size:11px;color:var(--color-text-secondary);border-top:0.5px solid var(--color-border-tertiary);background:var(--color-background-primary);display:flex;align-items:center;justify-content:space-between;position:relative;z-index:1}
#current-name{font-size:11px;color:var(--color-text-tertiary);font-style:italic}
.prop-divider{height:0.5px;background:var(--color-border-tertiary);margin:10px 0}
.toggle-row{display:flex;align-items:center;gap:8px;padding:5px 0}
.toggle-row label{font-size:11px;color:var(--color-text-secondary);cursor:pointer;user-select:none}
.toggle-row input[type=checkbox]{width:14px;height:14px;cursor:pointer;accent-color:#185FA5}
.modal-wrap{position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);z-index:9999;width:340px;max-height:82vh;overflow-y:auto;border-radius:12px;border:1px solid #c0bdb5;background:#ffffff;color:#1a1a18;box-shadow:0 8px 32px rgba(0,0,0,.28);padding:20px}
.modal-wrap h3{font-size:14px;font-weight:600;margin-bottom:4px;color:#1a1a18}
.modal-sub{font-size:11px;color:#6b6b67;margin-bottom:14px;line-height:1.5}
.modal-row{margin-bottom:10px}
.modal-row label{display:block;font-size:11px;color:#6b6b67;margin-bottom:3px}
.modal-row input,.modal-row select{width:100%;padding:6px 8px;border:1px solid #c0bdb5;border-radius:8px;background:#fff;color:#1a1a18;font-size:13px}
.modal-row input:focus{outline:none;border-color:#185FA5}
.modal-btns{display:flex;gap:8px;margin-top:16px}
.modal-btns button{flex:1;padding:8px;border-radius:8px;font-size:12px;cursor:pointer;border:1px solid #c0bdb5;background:#f5f5f3;color:#1a1a18;font-weight:500}
.modal-btns button:hover{background:#e8e7e2}
.modal-btns button.ok{background:#185FA5;color:#fff;border-color:#185FA5}
.modal-btns button.ok:hover{background:#0C447C}
.modal-btns button.ok-green{background:#3B6D11;color:#fff;border-color:#3B6D11}
.modal-btns button.ok-green:hover{background:#27500A}
.modal-btns button.ok-red{background:#A32D2D;color:#fff;border-color:#A32D2D}
.modal-btns button.ok-red:hover{background:#791F1F}
.emoji-grid{display:grid;grid-template-columns:repeat(7,1fr);gap:4px;margin-top:6px;max-height:120px;overflow-y:auto}
.emoji-opt{width:30px;height:30px;border-radius:6px;border:0.5px solid #d3d1c7;background:#fff;font-size:16px;cursor:pointer;display:flex;align-items:center;justify-content:center}
.emoji-opt:hover,.emoji-opt.sel{background:#eeecea;border-color:#888}
.save-list{display:flex;flex-direction:column;gap:6px;margin-bottom:4px;max-height:250px;overflow-y:auto}
.save-item{display:flex;align-items:center;gap:8px;padding:9px 10px;border:1px solid #d3d1c7;border-radius:8px;cursor:pointer;background:#fff}
.save-item:hover{background:#f5f5f3}
.save-item.sel{border-color:#185FA5;background:#E6F1FB}
.save-item.sel-green{border-color:#3B6D11;background:#EAF3DE}
.save-item-badge{font-size:9px;padding:1px 5px;border-radius:4px;font-weight:600;flex-shrink:0}
.save-item-badge.diagram{background:#E6F1FB;color:#0C447C;border:0.5px solid #185FA5}
.save-item-badge.catalog{background:#EAF3DE;color:#27500A;border:0.5px solid #3B6D11}
.save-item-del{width:22px;height:22px;border:none;border-radius:5px;background:transparent;color:#888;font-size:13px;cursor:pointer;flex-shrink:0}
.save-item-del:hover{background:#FCEBEB;color:#A32D2D}
.empty-state{text-align:center;padding:20px 0;color:#9c9a92;font-size:12px}
.modal-dimmer{position:absolute;top:0;left:0;right:0;bottom:0;z-index:9998;background:transparent}
</style>

<div id="app">
<div id="toolbar">
  <span style="font-size:13px;font-weight:500;color:var(--color-text-primary);margin-right:3px">방송 세팅 에디터</span>
  <div class="tb-sep"></div>
  <button class="tb-btn active" id="btn-select" onclick="setMode('select')">
    <svg width="12" height="12" viewBox="0 0 13 13" fill="currentColor"><path d="M2 1v10l3-3 2 5 2-1-2-5h4z"/></svg>선택
  </button>
  <button class="tb-btn" id="btn-connect" onclick="setMode('connect')">
    <svg width="12" height="12" viewBox="0 0 13 13" fill="none"><circle cx="2.5" cy="6.5" r="1.8" stroke="currentColor" stroke-width="1.3"/><circle cx="10.5" cy="6.5" r="1.8" stroke="currentColor" stroke-width="1.3"/><line x1="4.3" y1="6.5" x2="8.7" y2="6.5" stroke="currentColor" stroke-width="1.3"/></svg>케이블
  </button>
  <div class="tb-sep"></div>
  <button class="tb-btn active" id="btn-grid" onclick="toggleGrid()">
    <svg width="12" height="12" viewBox="0 0 13 13" fill="none"><rect x="1" y="1" width="4" height="4" rx="0.5" stroke="currentColor" stroke-width="1.2"/><rect x="8" y="1" width="4" height="4" rx="0.5" stroke="currentColor" stroke-width="1.2"/><rect x="1" y="8" width="4" height="4" rx="0.5" stroke="currentColor" stroke-width="1.2"/><rect x="8" y="8" width="4" height="4" rx="0.5" stroke="currentColor" stroke-width="1.2"/></svg>격자
  </button>
  <div class="tb-sep"></div>
  <button class="tb-btn" onclick="clearCanvas()">캔버스 초기화</button>
  <button class="tb-btn" onclick="loadPreset()">예시</button>
  <div class="tb-sep"></div>
  <div class="tb-group">
    <span class="tb-group-label">연결도</span>
    <div class="tb-row">
      <button class="tb-btn blue" onclick="openSaveModal('diagram')">저장</button>
      <button class="tb-btn blue" onclick="openLoadModal('diagram')">불러오기</button>
      <button class="tb-btn blue-f" onclick="exportJSON('diagram')" title="JSON 파일로 내보내기">파일↓</button>
      <button class="tb-btn blue-f" onclick="document.getElementById('import-diagram').click()" title="JSON 파일 가져오기">파일↑</button>
    </div>
  </div>
  <input type="file" id="import-diagram" accept=".json" style="display:none" onchange="importJSON(event,'diagram')">
  <div class="tb-sep"></div>
  <div class="tb-group">
    <span class="tb-group-label">장비 목록</span>
    <div class="tb-row">
      <button class="tb-btn green" onclick="openSaveModal('catalog')">저장</button>
      <button class="tb-btn green" onclick="openLoadModal('catalog')">불러오기</button>
      <button class="tb-btn green-f" onclick="exportJSON('catalog')" title="JSON 파일로 내보내기">파일↓</button>
      <button class="tb-btn green-f" onclick="document.getElementById('import-catalog').click()" title="JSON 파일 가져오기">파일↑</button>
    </div>
  </div>
  <input type="file" id="import-catalog" accept=".json" style="display:none" onchange="importJSON(event,'catalog')">
  <div class="tb-sep"></div>
  <button class="tb-btn blue" onclick="exportPNG()">
    <svg width="11" height="11" viewBox="0 0 13 13" fill="none"><path d="M6.5 1v7M4 5.5l2.5 2.5 2.5-2.5M2 10h9" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>PNG
  </button>
  <div class="tb-sep"></div>
  <div class="tb-sep"></div>
  <button class="tb-btn" onclick="openHistoryModal()">📋 수정내역</button>
  <div class="tb-sep"></div>
  <button class="tb-btn green" id="btn-wiki-save" onclick="saveWikiDiagram()" style="display:none">💾 위키에 저장</button>
  <span id="mode-badge">선택</span>
</div>

<div id="main">
  <div id="sidebar">
    <div id="sb-header">
      <span>장비 목록</span>
      <div class="sb-header-btns">
        <button id="sb-add-btn" onclick="openAddModal()">+ 추가</button>
        <button id="sb-reset-btn" onclick="resetCatalog()" title="장비 목록 초기화">↺ 초기화</button>
      </div>
    </div>
    <div id="sb-list"></div>
  </div>
  <div id="canvas-wrap" ondragover="event.preventDefault()" ondrop="dropDevice(event)">
    <div id="canvas" class="grid-on"><svg id="svg-layer"></svg></div>
  </div>
  <div id="prop-panel">
    <div class="prop-title">속성</div>
    <div id="prop-content"><div class="no-sel">장비를 선택하세요</div></div>
  </div>
</div>
<div id="status-bar">
  <span id="status-msg">준비 | 장비 드래그 배치 → 케이블 연결 모드에서 포트 클릭</span>
  <span id="current-name"></span>
</div>
</div>

<script>
const DB_DIAGRAM='bcast_diagram_db';
const DB_CATALOG='bcast_catalog_db';
const CATALOG_PERSIST_KEY='bcast_catalog_live'; // 장비목록 실시간 누적 저장

const EMOJIS=['📷','🎥','🔭','📸','🎚️','🎤','🔊','🎧','📺','💻','🖥️','📡','📻','🗄️','📜','💡','🎨','📹','🔌','🖨️','⌨️','🖱️','💾','📟','📠','🔋','🔦','📲','🖲️','🎬','🎞️','📽️','🎙️','🔈','🎛️','📶','🛰️','🔒','⚙️','🎮'];
const BG_COLORS=['#E6F1FB','#EAF3DE','#FAEEDA','#EEEDFE','#FAECE7','#F1EFE8','#FBEAF0','#E1F5EE'];
const CABLE_PALETTE=['#185FA5','#0F6E56','#B85C1A','#534AB7','#A32D2D','#BA7517','#993556','#5F5E5A','#1a7a4a','#2c6e8a','#7a1a6e','#4a3010','#1a3a7a','#8a2020','#3a6a1a'];
const DEF_COL={SDI:'#185FA5',HDMI:'#0F6E56',XLR:'#B85C1A',NDI:'#534AB7','USB-C':'#2c6e8a','USB-A':'#BA7517','광케이블':'#7a1a6e','기타':'#5F5E5A'};
const CABLE_TYPES=['SDI','HDMI','XLR','NDI','USB-C','USB-A','광케이블','기타'];

const DEFAULT_CATALOG=[
  {cat:'컴퓨터',items:[{id:'pc1',name:'송출용 컴퓨터',icon:'💻',bg:'#EEEDFE',sub:'Streaming PC'},{id:'pc2',name:'게임용 컴퓨터',icon:'🖥️',bg:'#EEEDFE',sub:'Gaming PC'}]},
  {cat:'카메라',items:[{id:'c1',name:'메인 카메라',icon:'📷',bg:'#E6F1FB',sub:'EOS RP'},{id:'c2',name:'서브 카메라',icon:'🎥',bg:'#E6F1FB',sub:'Sub Camera'},{id:'c3',name:'PTZ 카메라',icon:'🔭',bg:'#E6F1FB',sub:'PTZ'},{id:'c4',name:'웹캠',icon:'📸',bg:'#E6F1FB',sub:'Webcam'}]},
  {cat:'오디오',items:[{id:'a1',name:'오디오 인터페이스',icon:'🎚️',bg:'#EAF3DE',sub:'Yamaha AG03 MK2'},{id:'a2',name:'마이크',icon:'🎤',bg:'#EAF3DE',sub:'Microphone'},{id:'a3',name:'스피커',icon:'🔊',bg:'#EAF3DE',sub:'Speaker'},{id:'a4',name:'헤드폰',icon:'🎧',bg:'#EAF3DE',sub:'Headphone'}]},
  {cat:'스위처/캡처',items:[{id:'s1',name:'내장 캡처보드',icon:'📺',bg:'#FAEEDA',sub:'AVerMedia 4K 2.1'},{id:'s2',name:'외장 캡처보드',icon:'📹',bg:'#FAEEDA',sub:'AVerMedia CamStream BU113G2'},{id:'s3',name:'비디오 스위처',icon:'🎬',bg:'#FAEEDA',sub:'Video Switcher'},{id:'s4',name:'HDMI 스플리터',icon:'🔌',bg:'#FAEEDA',sub:'HDMI Splitter'}]},
  {cat:'모니터링',items:[{id:'m1',name:'모니터 1',icon:'🖥️',bg:'#E6F1FB',sub:'Monitor'},{id:'m2',name:'모니터 2',icon:'🖥️',bg:'#E6F1FB',sub:'Monitor'}]},
  {cat:'네트워크',items:[{id:'n1',name:'라우터/스위치',icon:'📡',bg:'#FBEAF0',sub:'Router'},{id:'n2',name:'무선 송수신기',icon:'📻',bg:'#FBEAF0',sub:'Transmitter'},{id:'n3',name:'미디어 서버',icon:'🗄️',bg:'#FBEAF0',sub:'Media Server'}]},
  {cat:'기타',items:[{id:'x1',name:'조명',icon:'💡',bg:'#F1EFE8',sub:'Lighting'},{id:'x2',name:'그래픽 시스템',icon:'🎨',bg:'#F1EFE8',sub:'Graphics'},{id:'x3',name:'텔레프롬프터',icon:'📜',bg:'#F1EFE8',sub:'Teleprompter'}]},
];

/* ── 장비목록 영속 저장/불러오기 ── */
function saveCatalogLive(){
  try{localStorage.setItem(CATALOG_PERSIST_KEY,JSON.stringify({catalog,catCount}));}catch{}
}
function loadCatalogLive(){
  try{
    const raw=localStorage.getItem(CATALOG_PERSIST_KEY);
    if(raw){const d=JSON.parse(raw);catalog=d.catalog;catCount=d.catCount||0;return true;}
  }catch{}
  return false;
}

let catalog=JSON.parse(JSON.stringify(DEFAULT_CATALOG)); // 초기값
let devices={},cables=[],selectedId=null,mode='select';
let connecting=null,connectingPort=null,tempLine=null;
let devCount=0,catCount=0,dragSrc=null,gridOn=true,currentDiagramName='';

/* ── DB (연결도/장비목록 named saves) ── */
const dbLoad=k=>{try{return JSON.parse(localStorage.getItem(k)||'{}');}catch{return{};}};
const dbSave=(k,db)=>{try{localStorage.setItem(k,JSON.stringify(db));}catch{setStatus('저장 실패');}};
const dbList=k=>Object.values(dbLoad(k)).sort((a,b)=>b.savedAt-a.savedAt);
const dbPut=(k,n,data)=>{const db=dbLoad(k);db[n]={name:n,savedAt:Date.now(),data};dbSave(k,db);};
const dbGet=(k,n)=>dbLoad(k)[n]||null;
const dbDel=(k,n)=>{const db=dbLoad(k);delete db[n];dbSave(k,db);};

/* ── JSON 파일 내보내기/가져오기 ── */
function exportJSON(type){
  const isD=type==='diagram';
  const data=isD
    ?{_type:'broadcast_diagram',_ver:1,_at:new Date().toISOString(),name:currentDiagramName,devices,cables,catalog,devCount,catCount}
    :{_type:'broadcast_catalog',_ver:1,_at:new Date().toISOString(),catalog,catCount};
  const fname=(isD?(currentDiagramName||'연결도'):'장비목록')+'_'+new Date().toISOString().slice(0,10)+'.json';
  const a=document.createElement('a');a.href=URL.createObjectURL(new Blob([JSON.stringify(data,null,2)],{type:'application/json'}));
  a.download=fname;a.click();URL.revokeObjectURL(a.href);setStatus(`"${fname}" 내보내기 완료`);
}
function importJSON(e,type){
  const file=e.target.files[0];if(!file)return;
  const reader=new FileReader();
  reader.onload=ev=>{
    try{
      const data=JSON.parse(ev.target.result);
      const et=type==='diagram'?'broadcast_diagram':'broadcast_catalog';
      if(data._type!==et){alert(`올바른 ${type==='diagram'?'연결도':'장비목록'} 파일이 아닙니다.`);return;}
      if(type==='diagram')applyDiagramData(data,data.name||file.name.replace('.json',''));
      else{catalog=data.catalog;catCount=data.catCount||0;saveCatalogLive();renderSidebar();}
      setStatus(`"${file.name}" 가져오기 완료`);
    }catch{alert('파일을 읽을 수 없습니다.');}
  };
  reader.readAsText(file);e.target.value='';
}

/* ── 장비목록 초기화 ── */
function resetCatalog(){
  showModal(`<h3>🗂 장비 목록 초기화</h3>
    <p class="modal-sub">장비 목록을 기본값으로 되돌립니다.<br>추가하거나 편집한 장비가 모두 사라집니다.</p>
    <div class="modal-btns"><button onclick="removeModal()">취소</button><button class="ok-red" onclick="doResetCatalog()">초기화</button></div>`);
}
function doResetCatalog(){
  catalog=JSON.parse(JSON.stringify(DEFAULT_CATALOG));catCount=0;
  saveCatalogLive();renderSidebar();removeModal();setStatus('장비 목록이 기본값으로 초기화되었습니다.');
}

const getCDT=c=>c.type==='기타'&&c.customName?c.customName:c.type;
const getCableColor=c=>c.customColor||DEF_COL[c.type]||'#5F5E5A';
const getCableLines=c=>({line1:[getCDT(c),...(c.length!=null&&c.length!==''?[c.length+'m']:[])].join(' '),line2:(c.label&&c.label.trim())||null});
function cubicBP(p0,p1,p2,p3,t){const u=1-t;return{x:u*u*u*p0.x+3*u*u*t*p1.x+3*u*t*t*p2.x+t*t*t*p3.x,y:u*u*u*p0.y+3*u*u*t*p1.y+3*u*t*t*p2.y+t*t*t*p3.y};}
function assignLP(){
  const used=[];cables.forEach(c=>{
    if(!devices[c.from]||!devices[c.to]){c._t=0.5;return;}
    const p1=getPortXY(c.from,c.fs),p2=getPortXY(c.to,c.ts);
    const cp1={x:p1.x+(c.fs==='r'?60:c.fs==='l'?-60:0),y:p1.y},cp2={x:p2.x+(c.ts==='r'?60:c.ts==='l'?-60:0),y:p2.y};
    let bestT=0.5,bestD=-1;
    for(const t of[0.5,0.35,0.65,0.25,0.75,0.15,0.85]){
      const pt=cubicBP(p1,cp1,cp2,p2,t);let md=Infinity;
      for(const u of used){const dx=pt.x-u.x,dy=pt.y-u.y;md=Math.min(md,Math.sqrt(dx*dx+dy*dy));}
      if(md>bestD){bestD=md;bestT=t;}
    }
    c._t=bestT;used.push(cubicBP(p1,cp1,cp2,p2,bestT));
  });
}

function toggleGrid(){gridOn=!gridOn;document.getElementById('canvas').classList.toggle('grid-on',gridOn);document.getElementById('btn-grid').classList.toggle('active',gridOn);setStatus(gridOn?'격자 켜짐':'격자 꺼짐');}

function renderSidebar(){
  const list=document.getElementById('sb-list');list.innerHTML='';
  catalog.forEach((cat,ci)=>{
    const row=document.createElement('div');row.className='sb-cat-row';
    row.innerHTML=`<span class="sb-cat-label">${cat.cat}</span><button class="sb-cat-add" onclick="openAddModalInCat(${ci})">+ 추가</button>`;
    list.appendChild(row);
    cat.items.forEach((item,ii)=>{
      const el=document.createElement('div');el.className='sb-item';el.draggable=true;
      el.innerHTML=`<div class="sb-icon-box" style="background:${item.bg}">${item.icon}</div><span class="sb-item-name">${item.name}</span><div class="sb-item-actions"><button class="sb-act-btn" onclick="openEditModal(${ci},${ii});event.stopPropagation()">✏️</button><button class="sb-act-btn del" onclick="delCatalogItem(${ci},${ii});event.stopPropagation()">✕</button></div>`;
      el.addEventListener('dragstart',e=>{dragSrc={ci,ii};e.dataTransfer.effectAllowed='copy';});
      list.appendChild(el);
    });
  });
}
function delCatalogItem(ci,ii){
  catalog[ci].items.splice(ii,1);if(!catalog[ci].items.length)catalog.splice(ci,1);
  saveCatalogLive();renderSidebar(); // 삭제 즉시 저장
}

/* ── 모달 ── */
let selLoadName=null;
function removeModal(){document.getElementById('modal-card')?.remove();document.getElementById('modal-dimmer')?.remove();selLoadName=null;}
function showModal(html){
  removeModal();
  const dimmer=document.createElement('div');dimmer.className='modal-dimmer';dimmer.id='modal-dimmer';
  dimmer.addEventListener('mousedown',removeModal);document.getElementById('app').appendChild(dimmer);
  const card=document.createElement('div');card.className='modal-wrap';card.id='modal-card';
  card.innerHTML=html;card.addEventListener('mousedown',e=>e.stopPropagation());
  document.getElementById('app').appendChild(card);
}

function openSaveModal(type){
  const isD=type==='diagram';
  showModal(`<h3>${isD?'📡 연결도 저장':'🗂 장비 목록 저장'}</h3>
    <p class="modal-sub">${isD?'브라우저에 연결도를 저장합니다.':'현재 장비 목록을 이름을 붙여 브라우저에 저장합니다.'}</p>
    <div class="modal-row"><label>저장 이름</label><input id="save-name" value="${isD?currentDiagramName:''}" placeholder="이름을 입력하세요" style="background:#fff;color:#1a1a18"></div>
    <div class="modal-btns"><button onclick="removeModal()">취소</button><button class="${isD?'ok':'ok-green'}" onclick="doSave('${type}')">저장</button></div>`);
  setTimeout(()=>{const i=document.getElementById('save-name');i?.focus();i?.select();},50);
  document.getElementById('save-name').addEventListener('keydown',e=>{if(e.key==='Enter')doSave(type);if(e.key==='Escape')removeModal();});
}
function doSave(type){
  const name=document.getElementById('save-name')?.value.trim();if(!name){alert('이름을 입력하세요.');return;}
  if(type==='diagram'){
    dbPut(DB_DIAGRAM,name,{devices,cables,catalog,devCount,catCount});
    currentDiagramName=name;document.getElementById('current-name').textContent='연결도: '+name;
    removeModal();setStatus(`연결도 "${name}" 저장 완료`);
  } else {
    dbPut(DB_CATALOG,name,{catalog,catCount});
    removeModal();setStatus(`장비 목록 "${name}" 저장 완료`);
  }
}
function openLoadModal(type){
  selLoadName=null;const isD=type==='diagram';
  const selC=isD?'sel':'sel-green',okC=isD?'ok':'ok-green';
  const list=dbList(isD?DB_DIAGRAM:DB_CATALOG);
  const listHtml=list.length===0?`<div class="empty-state">저장된 ${isD?'연결도':'장비 목록'}가 없습니다</div>`
    :list.map(it=>{
      const d=new Date(it.savedAt);
      const ds=`${d.getFullYear()}.${String(d.getMonth()+1).padStart(2,'0')}.${String(d.getDate()).padStart(2,'0')} ${String(d.getHours()).padStart(2,'0')}:${String(d.getMinutes()).padStart(2,'0')}`;
      const badge=isD?'<span class="save-item-badge diagram">연결도</span>':'<span class="save-item-badge catalog">장비목록</span>';
      const esc=it.name.replace(/\\/g,'\\\\').replace(/'/g,"\\'");
      return`<div class="save-item" onclick="selItem('${esc}','${selC}',this)">
        <div style="flex:1;overflow:hidden"><div style="display:flex;align-items:center;gap:6px;margin-bottom:2px">${badge}<span style="font-size:13px;font-weight:500;color:#1a1a18;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${it.name}</span></div><div style="font-size:10px;color:#888">${ds}</div></div>
        <button class="save-item-del" onclick="delSaveItem('${type}','${esc}',event)">✕</button></div>`;
    }).join('');
  showModal(`<h3>${isD?'📡 연결도 불러오기':'🗂 장비 목록 불러오기'}</h3>
    <p class="modal-sub">${isD?'저장된 연결도를 선택하세요.':'저장된 장비 목록을 선택하세요.'}</p>
    <div class="save-list">${listHtml}</div>
    ${list.length?`<div class="modal-btns"><button onclick="removeModal()">취소</button><button class="${okC}" id="load-ok" onclick="doLoad('${type}')" disabled style="opacity:.4">불러오기</button></div>`:`<div class="modal-btns"><button onclick="removeModal()">닫기</button></div>`}`);
}
function selItem(name,cls,el){selLoadName=name;document.querySelectorAll('.save-item').forEach(e=>e.classList.remove('sel','sel-green'));el.classList.add(cls);const b=document.getElementById('load-ok');if(b){b.disabled=false;b.style.opacity='1';}}
function delSaveItem(type,name,e){e.stopPropagation();if(!confirm(`"${name}" 삭제?`))return;dbDel(type==='diagram'?DB_DIAGRAM:DB_CATALOG,name);if(selLoadName===name)selLoadName=null;openLoadModal(type);}
function doLoad(type){
  if(!selLoadName)return;const rec=dbGet(type==='diagram'?DB_DIAGRAM:DB_CATALOG,selLoadName);if(!rec){alert('불러오기 실패');return;}
  if(type==='diagram')applyDiagramData(rec.data,selLoadName);
  else{catalog=rec.data.catalog;catCount=rec.data.catCount||0;saveCatalogLive();renderSidebar();}
  setStatus(`"${selLoadName}" 불러오기 완료`);removeModal();
}
function applyDiagramData(data,name){
  Object.keys(devices).forEach(id=>document.getElementById(id)?.remove());
  devices={};cables=[];document.getElementById('svg-layer').innerHTML='';
  /* 연결도 불러오기 시 catalog는 유지 (장비목록 독립) */
  devCount=data.devCount||0;catCount=data.catCount||catCount;
  Object.values(data.devices||{}).forEach(d=>{devices[d.id]=d;renderDevice(d.id,true);});
  cables=data.cables||[];redrawCables();
  currentDiagramName=name||'';document.getElementById('current-name').textContent=name?'연결도: '+name:'';
  document.getElementById('prop-content').innerHTML='<div class="no-sel">장비를 선택하세요</div>';selectedId=null;
}

/* ── 장비 편집 모달 ── */
let mCatIdx=null,mItemIdx=null,mEmoji='📷',mBg='#E6F1FB';
function openAddModal(){openAddModalInCat(catalog.length-1);}
function openAddModalInCat(ci){mCatIdx=ci;mItemIdx=null;mEmoji='📷';mBg='#E6F1FB';showDevModal('새 장비 추가','','');}
function openEditModal(ci,ii){mCatIdx=ci;mItemIdx=ii;const it=catalog[ci].items[ii];mEmoji=it.icon;mBg=it.bg;showDevModal('장비 편집',it.name,it.sub||'');}
function showDevModal(title,name,sub){
  const catOpts=catalog.map((c,i)=>`<option value="${i}"${i===mCatIdx?' selected':''}>${c.cat}</option>`).join('');
  const emojiHtml=EMOJIS.map(e=>`<button class="emoji-opt${e===mEmoji?' sel':''}" onclick="pickEmoji('${e}',this)">${e}</button>`).join('');
  const bgHtml=BG_COLORS.map(c=>`<div class="cswatch${c===mBg?' active':''}" style="background:${c};width:20px;height:20px;border-radius:50%;cursor:pointer;border:2px solid ${c===mBg?'#1a1a18':'transparent'}" onclick="pickBg('${c}',this)"></div>`).join('');
  showModal(`<h3>${title}</h3>
    <div class="modal-row"><label>카테고리</label><select id="mc" style="background:#fff;color:#1a1a18">${catOpts}<option value="new">+ 새 카테고리</option></select></div>
    <div id="mc-new" class="modal-row" style="display:none"><label>새 카테고리명</label><input id="mc-name" style="background:#fff;color:#1a1a18"></div>
    <div class="modal-row"><label>장비명</label><input id="mn" value="${name}" style="background:#fff;color:#1a1a18"></div>
    <div class="modal-row"><label>설명 (선택)</label><input id="ms" value="${sub}" placeholder="모델명 등" style="background:#fff;color:#1a1a18"></div>
    <div class="modal-row"><label>아이콘</label><div class="emoji-grid">${emojiHtml}</div></div>
    <div class="modal-row"><label>배경색</label><div style="display:flex;gap:5px;flex-wrap:wrap;margin-top:4px" id="mbg">${bgHtml}</div></div>
    <div class="modal-btns"><button onclick="removeModal()">취소</button><button class="ok" onclick="saveDevModal()">저장</button></div>`);
  document.getElementById('mc').addEventListener('change',function(){document.getElementById('mc-new').style.display=this.value==='new'?'block':'none';});
}
function pickEmoji(e,btn){mEmoji=e;document.querySelectorAll('.emoji-opt').forEach(b=>b.classList.remove('sel'));btn.classList.add('sel');}
function pickBg(c,el){mBg=c;document.querySelectorAll('#mbg .cswatch').forEach(s=>{s.classList.remove('active');s.style.borderColor='transparent';});el.classList.add('active');el.style.borderColor='#1a1a18';}
function saveDevModal(){
  const n=document.getElementById('mn').value.trim();if(!n){alert('장비명 입력');return;}
  const sub=document.getElementById('ms').value.trim(),cv=document.getElementById('mc').value;
  let tCat;if(cv==='new'){const nc=document.getElementById('mc-name').value.trim();if(!nc){alert('카테고리명 입력');return;}catalog.push({cat:nc,items:[]});tCat=catalog.length-1;}else tCat=parseInt(cv);
  const item={id:'u'+(++catCount),name:n,icon:mEmoji,bg:mBg,sub};
  if(mItemIdx===null)catalog[tCat].items.push(item);
  else{if(tCat===mCatIdx)catalog[mCatIdx].items[mItemIdx]=item;else{catalog[mCatIdx].items.splice(mItemIdx,1);if(!catalog[mCatIdx].items.length)catalog.splice(mCatIdx,1);catalog[tCat].items.push(item);}}
  saveCatalogLive(); // 장비 추가/편집 즉시 저장
  removeModal();renderSidebar();
}

function dropDevice(e){
  e.preventDefault();if(!dragSrc)return;
  const item=catalog[dragSrc.ci].items[dragSrc.ii];
  const rect=document.getElementById('canvas').getBoundingClientRect(),wrap=document.getElementById('canvas-wrap');
  addDevice(item.name,item.icon,item.bg,Math.max(0,e.clientX-rect.left+wrap.scrollLeft-65),Math.max(0,e.clientY-rect.top+wrap.scrollTop-45),item.sub,undefined,false,null);dragSrc=null;
}
function addDevice(label,icon,bg,x,y,sub,id,builtin,builtinHost){
  id=id||('d'+(++devCount));devices[id]={id,label,icon,bg:bg||'#fff',x,y,sub:sub||'',builtin:!!builtin,builtinHost:builtinHost||null};renderDevice(id);
}
function renderDevice(id,skip){
  const d=devices[id];document.getElementById(id)?.remove();
  const hostName=d.builtin&&d.builtinHost&&devices[d.builtinHost]?devices[d.builtinHost].label:'';
  const builtinHtml=d.builtin?`<div class="builtin-badge">내장${hostName?` · ${hostName}`:''}</div>`:'';
  const el=document.createElement('div');el.className='device'+(d.builtin?' builtin-device':'');el.id=id;el.style.cssText=`left:${d.x}px;top:${d.y}px`;
  el.innerHTML=`<div class="device-inner" style="background:${d.builtin?'#fffdf7':d.bg}"><div class="device-icon">${d.icon}</div><div class="device-label">${d.label}</div><div class="device-sublabel">${d.sub||''}</div>${builtinHtml}</div>
  <div class="port output" data-s="r"></div><div class="port input" data-s="l"></div><div class="port top" data-s="t"></div><div class="port bottom" data-s="b"></div>`;
  el.addEventListener('mousedown',ev=>{
    if(mode!=='select'||ev.target.classList.contains('port'))return;ev.stopPropagation();selectDevice(id);
    const ox=ev.clientX-d.x,oy=ev.clientY-d.y;
    function mm(e2){d.x=e2.clientX-ox;d.y=e2.clientY-oy;el.style.left=d.x+'px';el.style.top=d.y+'px';redrawCables();}
    function mu(){document.removeEventListener('mousemove',mm);document.removeEventListener('mouseup',mu);}
    document.addEventListener('mousemove',mm);document.addEventListener('mouseup',mu);
  });
  el.querySelectorAll('.port').forEach(p=>p.addEventListener('mousedown',ev=>{if(mode!=='connect')return;ev.stopPropagation();handlePort(id,p.dataset.s,p);}));
  document.getElementById('canvas').appendChild(el);updatePorts();if(!skip&&mode==='select')selectDevice(id);
}
function updatePorts(){document.querySelectorAll('.port').forEach(p=>p.style.display=mode==='connect'?'block':'none');}
function setMode(m){
  mode=m;document.querySelectorAll('.tb-btn').forEach(b=>b.classList.remove('active'));document.getElementById('btn-'+m)?.classList.add('active');if(gridOn)document.getElementById('btn-grid').classList.add('active');
  document.getElementById('mode-badge').textContent=m==='select'?'선택':'케이블';if(m!=='connect'){connecting=null;connectingPort=null;removeTempLine();}updatePorts();
}
function getPortXY(id,s){
  const el=document.getElementById(id);if(!el)return{x:0,y:0};
  const d=devices[id],inner=el.querySelector('.device-inner'),w=inner.offsetWidth,h=inner.offsetHeight;
  if(s==='r')return{x:d.x+w,y:d.y+h/2};if(s==='l')return{x:d.x,y:d.y+h/2};if(s==='t')return{x:d.x+w/2,y:d.y};if(s==='b')return{x:d.x+w/2,y:d.y+h};return{x:d.x+w/2,y:d.y+h/2};
}
function handlePort(id,s,portEl){
  if(!connecting){connecting=id;connectingPort=s;portEl.classList.add('active');document.getElementById('canvas').addEventListener('mousemove',onTempLine);setStatus('연결 시작 → 다른 장비 포트 클릭');}
  else{
    if(connecting===id&&connectingPort===s){connecting=null;connectingPort=null;document.getElementById('canvas').removeEventListener('mousemove',onTempLine);removeTempLine();document.querySelectorAll('.port.active').forEach(p=>p.classList.remove('active'));return;}
    cables.push({from:connecting,fs:connectingPort,to:id,ts:s,type:'HDMI',label:'',length:'',customColor:null,customName:''});
    document.querySelectorAll('.port.active').forEach(p=>p.classList.remove('active'));document.getElementById('canvas').removeEventListener('mousemove',onTempLine);removeTempLine();connecting=null;connectingPort=null;redrawCables();setStatus('케이블 연결 완료');
  }
}
function onTempLine(e){
  const rect=document.getElementById('canvas').getBoundingClientRect();const mx=e.clientX-rect.left,my=e.clientY-rect.top;const p=getPortXY(connecting,connectingPort);
  if(!tempLine){tempLine=document.createElementNS('http://www.w3.org/2000/svg','path');tempLine.setAttribute('stroke','#aaa');tempLine.setAttribute('stroke-width','2');tempLine.setAttribute('fill','none');tempLine.setAttribute('stroke-dasharray','6 4');document.getElementById('svg-layer').appendChild(tempLine);}
  const cp=p.x+(connectingPort==='r'?55:connectingPort==='l'?-55:0);tempLine.setAttribute('d',`M${p.x},${p.y} C${cp},${p.y} ${mx},${my} ${mx},${my}`);
}
function removeTempLine(){if(tempLine){tempLine.remove();tempLine=null;}}
function drawHT(g,x,y,text,col,fs){
  const h=document.createElementNS('http://www.w3.org/2000/svg','text');[['x',x],['y',y],['text-anchor','middle'],['dominant-baseline','central'],['font-size',fs],['font-weight','700'],['font-family','sans-serif'],['stroke','#f8f7f4'],['stroke-width','3.5'],['stroke-linejoin','round'],['fill','none'],['paint-order','stroke']].forEach(([a,v])=>h.setAttribute(a,v));h.textContent=text;g.appendChild(h);
  const t=document.createElementNS('http://www.w3.org/2000/svg','text');[['x',x],['y',y],['text-anchor','middle'],['dominant-baseline','central'],['font-size',fs],['font-weight','700'],['font-family','sans-serif'],['fill',col]].forEach(([a,v])=>t.setAttribute(a,v));t.textContent=text;g.appendChild(t);
}
function redrawCables(){
  const svg=document.getElementById('svg-layer');svg.querySelectorAll('.cg').forEach(g=>g.remove());
  let defs=svg.querySelector('defs');if(!defs){defs=document.createElementNS('http://www.w3.org/2000/svg','defs');svg.prepend(defs);}
  Object.values(devices).forEach(d=>{
    if(!d.builtin||!d.builtinHost||!devices[d.builtinHost])return;
    if(cables.some(c=>(c.from===d.builtinHost&&c.to===d.id)||(c.from===d.id&&c.to===d.builtinHost)))return;
    const p1=getPortXY(d.builtinHost,'r'),p2=getPortXY(d.id,'l'),col='#EF9F27';
    const g=document.createElementNS('http://www.w3.org/2000/svg','g');g.classList.add('cg');const cx=(p1.x+p2.x)/2;
    const path=document.createElementNS('http://www.w3.org/2000/svg','path');path.setAttribute('d',`M${p1.x},${p1.y} C${cx},${p1.y} ${cx},${p2.y} ${p2.x},${p2.y}`);path.setAttribute('stroke',col);path.setAttribute('stroke-width','1.5');path.setAttribute('fill','none');path.setAttribute('stroke-dasharray','5 4');path.setAttribute('stroke-linecap','round');path.classList.add('cable-builtin-line');
    g.appendChild(path);drawHT(g,(p1.x+p2.x)/2,(p1.y+p2.y)/2,'내장',col,'9');svg.appendChild(g);
  });
  assignLP();
  cables.forEach((c,i)=>{
    if(!devices[c.from]||!devices[c.to])return;
    const p1=getPortXY(c.from,c.fs),p2=getPortXY(c.to,c.ts),col=getCableColor(c);
    const cx1=p1.x+(c.fs==='r'?60:c.fs==='l'?-60:0),cx2=p2.x+(c.ts==='r'?60:c.ts==='l'?-60:0);
    const cp1={x:cx1,y:p1.y},cp2={x:cx2,y:p2.y};
    const g=document.createElementNS('http://www.w3.org/2000/svg','g');g.classList.add('cg');
    const mkId='mk'+i;defs.querySelector('#'+mkId)?.remove();
    const mk=document.createElementNS('http://www.w3.org/2000/svg','marker');mk.id=mkId;mk.setAttribute('markerWidth','7');mk.setAttribute('markerHeight','7');mk.setAttribute('refX','5');mk.setAttribute('refY','3.5');mk.setAttribute('orient','auto');
    const ap=document.createElementNS('http://www.w3.org/2000/svg','path');ap.setAttribute('d','M0,0 L0,7 L7,3.5 Z');ap.setAttribute('fill',col);mk.appendChild(ap);defs.appendChild(mk);
    const path=document.createElementNS('http://www.w3.org/2000/svg','path');path.setAttribute('d',`M${p1.x},${p1.y} C${cx1},${p1.y} ${cx2},${p2.y} ${p2.x},${p2.y}`);path.setAttribute('stroke',col);path.setAttribute('stroke-width','2.5');path.setAttribute('fill','none');path.setAttribute('stroke-linecap','round');path.setAttribute('marker-end','url(#'+mkId+')');path.classList.add('cable');
    path.addEventListener('click',()=>{if(mode==='select')selectCable(i);});g.appendChild(path);
    const lp=cubicBP(p1,cp1,cp2,p2,c._t||0.5);const{line1,line2}=getCableLines(c);
    const lineH=13,total=line2?2:1,startY=lp.y-(total-1)*lineH/2;
    drawHT(g,lp.x,startY,line1,col,'10');if(line2)drawHT(g,lp.x,startY+lineH,line2,col,'10');svg.appendChild(g);
  });
}
function selectDevice(id){
  document.querySelectorAll('.device').forEach(d=>d.classList.remove('selected'));document.getElementById(id)?.classList.add('selected');selectedId={type:'dev',id};
  const d=devices[id];
  const hostOpts=`<option value="">── 선택 없음 ──</option>`+Object.values(devices).filter(x=>x.id!==id&&!x.builtin).map(x=>`<option value="${x.id}"${d.builtinHost===x.id?' selected':''}>${x.label}</option>`).join('');
  document.getElementById('prop-content').innerHTML=`
    <div class="prop-row"><label>장비명</label><input value="${d.label}" oninput="devices['${id}'].label=this.value;document.querySelector('#${id} .device-label').textContent=this.value"></div>
    <div class="prop-row"><label>설명</label><input value="${d.sub||''}" oninput="devices['${id}'].sub=this.value;document.querySelector('#${id} .device-sublabel').textContent=this.value"></div>
    <div class="prop-row"><label>배경색</label><div class="color-row">${BG_COLORS.map(c=>`<div class="cswatch${d.bg===c?' active':''}" style="background:${c}" onclick="updBg('${id}','${c}',this)"></div>`).join('')}</div></div>
    <div class="prop-divider"></div>
    <div class="toggle-row"><input type="checkbox" id="builtin-chk" ${d.builtin?'checked':''} onchange="updBuiltin('${id}',this.checked)"><label for="builtin-chk">내장 장치로 표시</label></div>
    <div id="builtin-host-row" class="prop-row" style="display:${d.builtin?'block':'none'}"><label>장착된 컴퓨터</label><select id="builtin-host-sel" onchange="updBuiltinHost('${id}',this.value)">${hostOpts}</select></div>
    <button class="del-btn" onclick="deleteDevice('${id}')">장비 삭제</button>`;
}
function updBuiltin(id,val){devices[id].builtin=val;if(!val)devices[id].builtinHost=null;document.getElementById('builtin-host-row').style.display=val?'block':'none';renderDevice(id,true);document.getElementById(id)?.classList.add('selected');redrawCables();selectDevice(id);}
function updBuiltinHost(id,val){devices[id].builtinHost=val||null;renderDevice(id,true);document.getElementById(id)?.classList.add('selected');redrawCables();selectDevice(id);}
function selectCable(i){
  selectedId={type:'cable',i};const c=cables[i];document.querySelectorAll('.device').forEach(d=>d.classList.remove('selected'));
  const col=getCableColor(c);const sw=CABLE_PALETTE.map(cl=>`<div class="ccswatch${col===cl?' active':''}" style="background:${cl}" data-color="${cl}"></div>`).join('');
  document.getElementById('prop-content').innerHTML=`
    <div class="prop-row"><label>케이블 종류</label><select id="cp-type">${CABLE_TYPES.map(t=>`<option${c.type===t?' selected':''}>${t}</option>`).join('')}</select></div>
    <div id="cp-custom-row" class="prop-row" style="display:${c.type==='기타'?'block':'none'}"><label>케이블 이름 직접 입력</label><input id="cp-customname" value="${c.customName||''}" placeholder="예: 3.5mm, TRS..."></div>
    <div class="prop-row"><label>레이블 (선택)</label><input id="cp-label" value="${c.label||''}" placeholder="2행에 표시"></div>
    <div class="prop-row"><label>길이 (m)</label><input id="cp-length" type="number" min="0" step="0.5" value="${c.length!==''&&c.length!=null?c.length:''}"></div>
    <div class="prop-divider"></div>
    <div class="prop-row"><label>케이블 색상</label><div style="display:flex;gap:5px;flex-wrap:wrap;margin-bottom:8px">${sw}</div>
      <div style="display:flex;align-items:center;gap:6px"><span style="font-size:11px;color:var(--color-text-secondary)">직접 입력</span><input id="cp-color" type="color" value="${col}" style="width:36px;height:24px;padding:1px 2px;border-radius:6px;cursor:pointer"></div></div>
    <div class="prop-divider"></div>
    <div class="prop-row" style="font-size:11px;color:var(--color-text-secondary)">${devices[c.from]?.label||c.from} → ${devices[c.to]?.label||c.to}</div>
    <button class="del-btn" id="cp-del">케이블 삭제</button>`;
  function apply(){cables[i].type=document.getElementById('cp-type').value;cables[i].label=document.getElementById('cp-label').value;cables[i].customName=document.getElementById('cp-customname')?.value||'';const v=document.getElementById('cp-length').value;cables[i].length=v!==''?parseFloat(v):'';redrawCables();}
  document.getElementById('cp-type').addEventListener('change',function(){document.getElementById('cp-custom-row').style.display=this.value==='기타'?'block':'none';apply();});
  document.getElementById('cp-label').addEventListener('input',apply);document.getElementById('cp-customname')?.addEventListener('input',apply);document.getElementById('cp-length').addEventListener('input',apply);
  document.querySelectorAll('#prop-content .ccswatch').forEach(s=>s.addEventListener('click',()=>{cables[i].customColor=s.dataset.color;document.querySelectorAll('#prop-content .ccswatch').forEach(x=>x.classList.remove('active'));s.classList.add('active');document.getElementById('cp-color').value=s.dataset.color;redrawCables();}));
  document.getElementById('cp-color').addEventListener('input',function(){cables[i].customColor=this.value;document.querySelectorAll('#prop-content .ccswatch').forEach(x=>x.classList.remove('active'));redrawCables();});
  document.getElementById('cp-del').addEventListener('click',()=>{cables.splice(i,1);redrawCables();document.getElementById('prop-content').innerHTML='<div class="no-sel">장비를 선택하세요</div>';selectedId=null;});
}
function updBg(id,v,el){devices[id].bg=v;document.querySelector('#'+id+' .device-inner').style.background=v;document.querySelectorAll('#prop-content .cswatch').forEach(s=>s.classList.remove('active'));el.classList.add('active');}
function deleteDevice(id){cables=cables.filter(c=>c.from!==id&&c.to!==id);document.getElementById(id)?.remove();delete devices[id];document.getElementById('prop-content').innerHTML='<div class="no-sel">장비를 선택하세요</div>';selectedId=null;redrawCables();}

/* clearAll → 캔버스만 초기화, 장비목록은 유지 */
function clearCanvas(){
  Object.keys(devices).forEach(id=>document.getElementById(id)?.remove());
  devices={};cables=[];devCount=0;document.getElementById('svg-layer').innerHTML='';
  document.getElementById('prop-content').innerHTML='<div class="no-sel">장비를 선택하세요</div>';
  selectedId=null;currentDiagramName='';document.getElementById('current-name').textContent='';
  setStatus('캔버스 초기화 완료 (장비 목록은 유지됩니다)');
}

function loadPreset(){
  clearCanvas();
  const p=[
    {id:'gamepc',  name:'게임용 컴퓨터',    icon:'🖥️',bg:'#EEEDFE',x:40,  y:60,  sub:'Gaming PC',                  builtin:false,builtinHost:null},
    {id:'camera',  name:'카메라',            icon:'📷',bg:'#E6F1FB',x:40,  y:300, sub:'Canon EOS RP',                builtin:false,builtinHost:null},
    {id:'mic',     name:'마이크',            icon:'🎤',bg:'#EAF3DE',x:40,  y:510, sub:'Condenser Mic',               builtin:false,builtinHost:null},
    {id:'cap1',    name:'내장 캡처보드',     icon:'📺',bg:'#FAEEDA',x:330, y:60,  sub:'AVerMedia 4K 2.1',            builtin:true, builtinHost:'streampc'},
    {id:'cap2',    name:'외장 캡처보드',     icon:'📹',bg:'#FAEEDA',x:330, y:300, sub:'AVerMedia CamStream BU113G2', builtin:false,builtinHost:null},
    {id:'audioif', name:'오디오 인터페이스', icon:'🎚️',bg:'#EAF3DE',x:330, y:510, sub:'Yamaha AG03 MK2',             builtin:false,builtinHost:null},
    {id:'streampc',name:'송출용 컴퓨터',    icon:'💻',bg:'#EEEDFE',x:640, y:220, sub:'Streaming PC',                builtin:false,builtinHost:null},
    {id:'monitor', name:'모니터',            icon:'🖥️',bg:'#E6F1FB',x:890, y:140, sub:'방송 모니터',                 builtin:false,builtinHost:null},
    {id:'headphone',name:'헤드폰',           icon:'🎧',bg:'#EAF3DE',x:890, y:360, sub:'모니터링용',                   builtin:false,builtinHost:null},
  ];
  p.forEach(d=>addDevice(d.name,d.icon,d.bg,d.x,d.y,d.sub,d.id,d.builtin,d.builtinHost));
  cables.push({from:'gamepc',  fs:'r',to:'cap1',     ts:'l',type:'HDMI', label:'게임 영상',length:1.5,customColor:null,customName:''});
  cables.push({from:'camera',  fs:'r',to:'cap2',     ts:'l',type:'HDMI', label:'카메라',   length:2,  customColor:null,customName:''});
  cables.push({from:'cap2',    fs:'r',to:'streampc', ts:'l',type:'USB-C',label:'영상',     length:1,  customColor:null,customName:''});
  cables.push({from:'gamepc',  fs:'b',to:'audioif',  ts:'t',type:'기타', label:'게임 소리',length:1.5,customColor:'#BA7517',customName:'3.5mm'});
  cables.push({from:'mic',     fs:'r',to:'audioif',  ts:'l',type:'XLR',  label:'',         length:3,  customColor:null,customName:''});
  cables.push({from:'audioif', fs:'r',to:'streampc', ts:'l',type:'USB-C',label:'오디오',   length:1,  customColor:null,customName:''});
  cables.push({from:'streampc',fs:'r',to:'monitor',  ts:'l',type:'HDMI', label:'',         length:1.5,customColor:null,customName:''});
  cables.push({from:'streampc',fs:'b',to:'headphone',ts:'l',type:'USB-A',label:'모니터링', length:1,  customColor:null,customName:''});
  redrawCables();currentDiagramName='예시 세팅';document.getElementById('current-name').textContent='연결도: 예시 세팅';setStatus('예시 세팅 불러오기 완료');
}
async function exportPNG(){
  setStatus('PNG 내보내는 중...');const s=document.createElement('script');s.src='https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js';document.head.appendChild(s);
  s.onload=async()=>{const c=await html2canvas(document.getElementById('canvas'),{backgroundColor:'#f8f7f4',scale:1.5,useCORS:true});const a=document.createElement('a');a.download=(currentDiagramName||'방송세팅')+'.png';a.href=c.toDataURL('image/png');a.click();setStatus('PNG 저장 완료');};
}
function setStatus(msg){document.getElementById('status-msg').textContent=msg;}
document.getElementById('canvas').addEventListener('mousedown',e=>{
  if(e.target===document.getElementById('canvas')||e.target===document.getElementById('svg-layer')){document.querySelectorAll('.device').forEach(d=>d.classList.remove('selected'));selectedId=null;document.getElementById('prop-content').innerHTML='<div class="no-sel">장비를 선택하세요</div>';}
});

/* ── 수정이력 (최근 5건 자동 저장) ── */
const HISTORY_KEY='bcast_history';
const MAX_HISTORY=5;

function getHistory(){try{return JSON.parse(localStorage.getItem(HISTORY_KEY)||'[]');}catch{return[];}}
function saveHistory(name){
  const hist=getHistory();
  hist.unshift({name:name||currentDiagramName||'미저장',savedAt:Date.now(),data:{devices:JSON.parse(JSON.stringify(devices)),cables:JSON.parse(JSON.stringify(cables)),catalog,devCount,catCount}});
  while(hist.length>MAX_HISTORY) hist.pop();
  try{localStorage.setItem(HISTORY_KEY,JSON.stringify(hist));}catch{}
}

// 케이블 연결/삭제/장비 이동 시 자동 저장
const _origRedraw=redrawCables;
redrawCables=function(){_origRedraw();saveHistory();};

function openHistoryModal(){
  const hist=getHistory();
  if(!hist.length){showModal('<h3>📋 수정내역</h3><div class="empty-state">수정 내역이 없습니다.</div><div class="modal-btns"><button onclick="removeModal()">닫기</button></div>');return;}
  const listHtml=hist.map((h,i)=>{
    const d=new Date(h.savedAt);
    const ds=`${d.getFullYear()}.${String(d.getMonth()+1).padStart(2,'0')}.${String(d.getDate()).padStart(2,'0')} ${String(d.getHours()).padStart(2,'0')}:${String(d.getMinutes()).padStart(2,'0')}:${String(d.getSeconds()).padStart(2,'0')}`;
    const devCnt=Object.keys(h.data.devices||{}).length;
    const cabCnt=(h.data.cables||[]).length;
    return `<div class="save-item" onclick="loadHistory(${i});removeModal();">
      <div style="flex:1"><div style="font-size:13px;font-weight:500;color:var(--color-text-primary);">${h.name||'미저장'}</div><div style="font-size:10px;color:var(--color-text-secondary);margin-top:2px;">${ds} · 장비 ${devCnt}개 · 케이블 ${cabCnt}개</div></div>
    </div>`;
  }).join('');
  showModal(`<h3>📋 수정내역 불러오기 (최근 ${MAX_HISTORY}건)</h3>
    <p class="modal-sub">클릭하면 해당 시점의 연결도로 복원됩니다.</p>
    <div class="save-list">${listHtml}</div>
    <div class="modal-btns"><button onclick="removeModal()">닫기</button></div>`);
}

function loadHistory(idx){
  const hist=getHistory();
  if(!hist[idx])return;
  applyDiagramData(hist[idx].data, hist[idx].name||'수정내역 복원');
  setStatus('수정내역에서 복원 완료');
}

/* ── 위키 연결도 연동 ── */
const WIKI_ID = new URLSearchParams(location.search).get('wiki_id');
const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]')?.content;

async function loadWikiDiagram() {
  if (!WIKI_ID) return false;
  try {
    const res = await fetch(`/api/wiki/${WIKI_ID}/diagram`, {headers:{'Accept':'application/json'}});
    if (!res.ok) return false;
    const data = await res.json();
    if (data.diagram && data.diagram.devices) {
      applyDiagramData(data.diagram, '위키 연결도');
      setStatus('위키 연결도를 불러왔습니다.');
      return true;
    }
  } catch(e) {}
  return false;
}

async function saveWikiDiagram() {
  if (!WIKI_ID) { alert('위키 문서와 연결되지 않았습니다. 위키 문서에서 연결도를 열어주세요.'); return; }
  try {
    const res = await fetch(`/api/wiki/${WIKI_ID}/diagram`, {
      method:'POST',
      headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF_TOKEN,'Accept':'application/json'},
      body:JSON.stringify({diagram:{devices,cables,catalog,devCount,catCount}}),
    });
    if (res.ok) setStatus('위키 연결도가 저장되었습니다.');
    else alert('저장 실패');
  } catch(e) { alert('저장 오류'); }
}

/* ── 시작 시: wiki_id가 있으면 위키 데이터 로드, 없으면 빈 캔버스 ── */
if(!loadCatalogLive()){
  saveCatalogLive();
}
renderSidebar();

(async()=>{
  if (WIKI_ID) {
    const loaded = await loadWikiDiagram();
    if (!loaded) {
      clearCanvas();
      setStatus('새 연결도 — 장비를 드래그하여 배치하세요.');
    }
  } else {
    clearCanvas();
    setStatus('위키와 연결되지 않은 독립 모드 — 장비를 드래그하여 배치하세요.');
  }
  if (WIKI_ID) document.getElementById('btn-wiki-save').style.display='';
})();
</script>
</body>
</html>
