{{-- 공용 이미지 뷰어 — 캘린더 앨범과 동일한 확대/축소·스와이프·넘김 (window.drgoViewer.open) --}}
<style>
    .dvw { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.92); z-index:99990; align-items:center; justify-content:center; flex-direction:column; gap:12px; touch-action:none; overscroll-behavior:contain; }
    .dvw.open { display:flex; }
    .dvw-wrap { position:absolute; inset:0; display:flex; align-items:center; justify-content:center; overflow:visible; touch-action:none; }
    .dvw-wrap.dragging { cursor:grabbing; }
    .dvw-wrap.zoomed { cursor:grab; }
    .dvw-wrap img { max-width:90vw; max-height:80vh; border-radius:8px; object-fit:contain; box-shadow:0 4px 32px rgba(0,0,0,0.5); transform-origin:center center; transition:transform 0.15s ease; user-select:none; -webkit-user-drag:none; pointer-events:auto; }
    .dvw-close { position:fixed; top:16px; right:16px; background:rgba(0,0,0,0.45); border:1px solid rgba(255,255,255,0.35); color:#fff; width:40px; height:40px; border-radius:50%; cursor:pointer; font-size:18px; display:flex; align-items:center; justify-content:center; z-index:20; }
    .dvw-close:hover { background:rgba(255,255,255,0.3); }
    .dvw-nav { position:fixed; top:50%; transform:translateY(-50%); background:rgba(255,255,255,0.15); border:none; color:#fff; width:44px; height:44px; border-radius:50%; cursor:pointer; font-size:20px; display:flex; align-items:center; justify-content:center; z-index:10; }
    .dvw-nav:hover { background:rgba(255,255,255,0.3); }
    .dvw-nav.prev { left:16px; }
    .dvw-nav.next { right:16px; }
    .dvw-zoominfo { position:absolute; bottom:60px; left:50%; transform:translateX(-50%); background:rgba(0,0,0,0.6); color:#fff; padding:4px 12px; border-radius:20px; font-size:11px; opacity:0; transition:opacity 0.3s; pointer-events:none; }
    .dvw-zoominfo.show { opacity:1; }
    .dvw-filename { position:absolute; bottom:34px; left:50%; transform:translateX(-50%); color:rgba(255,255,255,0.7); font-size:12px; max-width:80vw; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; pointer-events:none; }
    .dvw-count { position:absolute; top:18px; left:50%; transform:translateX(-50%); color:rgba(255,255,255,0.7); font-size:12px; pointer-events:none; }
    .dvw-hint { position:absolute; bottom:14px; left:50%; transform:translateX(-50%); color:rgba(255,255,255,0.4); font-size:11px; pointer-events:none; }
</style>
<div class="dvw" id="dvw" aria-hidden="true">
    <button type="button" class="dvw-close" onclick="drgoViewer.close()">✕</button>
    <button type="button" class="dvw-nav prev" onclick="drgoViewer.nav(-1)">‹</button>
    <button type="button" class="dvw-nav next" onclick="drgoViewer.nav(1)">›</button>
    <div class="dvw-wrap" id="dvwWrap"><img id="dvwImg" src="" alt=""></div>
    <div class="dvw-count" id="dvwCount"></div>
    <div class="dvw-zoominfo" id="dvwZoomInfo">100%</div>
    <div class="dvw-filename" id="dvwFilename"></div>
    <div class="dvw-hint">스크롤·핀치: 확대/축소 · 더블클릭: 원본 크기 · 드래그: 이동 · 좌우: 넘김</div>
</div>
<script>
window.drgoViewer=(function(){
    const MIN=0.5, MAX=8;
    let images=[], idx=0, zoom=1, panX=0, panY=0, dragging=false, startX=0, startY=0;
    let hist=false, consuming=false;
    const loaded=new Set();
    const el=id=>document.getElementById(id);

    function updateTransform(){
        const img=el('dvwImg');
        img.style.transform=`translate(${panX}px,${panY}px) scale(${zoom})`;
        img.style.transition=dragging?'none':'transform 0.15s ease';
        el('dvwWrap').classList.toggle('zoomed',zoom>1.05);
    }
    function resetZoom(){ zoom=1; panX=0; panY=0; updateTransform(); }
    function showZoomInfo(){
        const info=el('dvwZoomInfo');
        info.textContent=Math.round(zoom*100)+'%';
        info.classList.add('show');
        clearTimeout(info._t);
        info._t=setTimeout(()=>info.classList.remove('show'),800);
    }
    function preload(i){
        const it=images[i];
        if(!it||loaded.has(it.src)) return;
        const im=new Image(); im.onload=()=>loaded.add(it.src); im.src=it.src;
    }
    function show(){
        const it=images[idx];
        if(!it) return;
        el('dvwFilename').textContent=it.filename||'';
        el('dvwCount').textContent=images.length>1?`${idx+1} / ${images.length}`:'';
        const img=el('dvwImg');
        if(loaded.has(it.src) || !it.thumb || it.thumb===it.src){
            img.src=it.src;
        } else {
            img.src=it.thumb; // 썸네일 즉시 표시 → 원본 로드되면 교체
            const full=new Image(), my=idx;
            full.onload=()=>{ loaded.add(it.src); if(idx===my) img.src=it.src; };
            full.src=it.src;
        }
        if(images.length>1){ preload((idx+1)%images.length); preload((idx-1+images.length)%images.length); }
    }
    // 닫기 버튼을 실제 보이는 화면(비주얼 뷰포트) 우상단에 고정 — 핀치 줌에도 안 움직임
    function pinClose(){
        const btn=document.querySelector('#dvw .dvw-close'); if(!btn) return;
        const vv=window.visualViewport;
        if(!vv){ btn.style.top='16px'; btn.style.right='16px'; return; }
        btn.style.right='auto';
        btn.style.top=(vv.offsetTop+12)+'px';
        btn.style.left=(vv.offsetLeft+vv.width-52)+'px';
    }
    let pinBound=false;
    function bindPin(on){
        const vv=window.visualViewport; if(!vv) return;
        if(on&&!pinBound){ vv.addEventListener('resize',pinClose); vv.addEventListener('scroll',pinClose); pinBound=true; }
        else if(!on&&pinBound){ vv.removeEventListener('resize',pinClose); vv.removeEventListener('scroll',pinClose); pinBound=false; }
    }

    /**
     * open(images, startIdx)
     * images: [{src, thumb?, filename?}] 또는 문자열 URL 배열 (단일 문자열도 허용)
     */
    function open(imgs, startIdx){
        if(typeof imgs==='string') imgs=[imgs];
        images=(imgs||[]).map(it=>typeof it==='string'?{src:it}:it).filter(it=>it&&it.src);
        if(!images.length) return;
        idx=Math.min(Math.max(0,startIdx||0),images.length-1);
        resetZoom(); show();
        el('dvw').classList.add('open');
        document.querySelector('#dvw .dvw-nav.prev').style.display=images.length>1?'':'none';
        document.querySelector('#dvw .dvw-nav.next').style.display=images.length>1?'':'none';
        pinClose(); bindPin(true);
        if(!hist){ try{ history.pushState({dvw:1},''); hist=true; }catch(e){} }
    }
    function close(){
        el('dvw').classList.remove('open'); resetZoom(); bindPin(false);
        const btn=document.querySelector('#dvw .dvw-close');
        if(btn){ btn.style.top=''; btn.style.left=''; btn.style.right=''; }
        if(hist){ hist=false; consuming=true; try{ history.back(); }catch(e){ consuming=false; } }
    }
    function nav(dir){
        if(images.length<2) return;
        idx=(idx+dir+images.length)%images.length;
        resetZoom(); show();
    }

    window.addEventListener('popstate',()=>{
        if(consuming){ consuming=false; return; }
        if(hist){ hist=false; close(); }
    });

    document.addEventListener('DOMContentLoaded',()=>{
        const box=el('dvw'), wrap=el('dvwWrap');
        if(!box) return;
        // 휠 줌
        box.addEventListener('wheel',e=>{
            if(!box.classList.contains('open')) return;
            e.preventDefault();
            const delta=e.deltaY>0?-0.15:0.15;
            zoom=Math.min(MAX,Math.max(MIN,zoom+delta*zoom));
            if(zoom<1.05){panX=0;panY=0;}
            updateTransform(); showZoomInfo();
        },{passive:false});
        // 더블클릭 줌 토글
        wrap.addEventListener('dblclick',e=>{
            e.preventDefault();
            if(zoom>1.05){resetZoom();}
            else{zoom=3;panX=0;panY=0;updateTransform();}
            showZoomInfo();
        });
        // 마우스 드래그 팬
        wrap.addEventListener('mousedown',e=>{
            if(zoom<=1.05) return;
            e.preventDefault(); dragging=true; startX=e.clientX-panX; startY=e.clientY-panY;
            wrap.classList.add('dragging');
        });
        document.addEventListener('mousemove',e=>{
            if(!dragging) return;
            panX=e.clientX-startX; panY=e.clientY-startY; updateTransform();
        });
        document.addEventListener('mouseup',()=>{
            if(!dragging) return;
            dragging=false; wrap.classList.remove('dragging');
        });
        // 터치: 핀치 줌 + 팬 + (미확대 시) 좌우 스와이프 넘김
        let mode=null, pinchDist=0, pinchZoom=1, tX=0, tY=0, pX0=0, pY0=0;
        let swipe=false, sX=0, sY=0;
        const dist=t=>Math.hypot(t[0].clientX-t[1].clientX, t[0].clientY-t[1].clientY);
        wrap.addEventListener('touchstart',e=>{
            swipe=false;
            if(e.touches.length===2){ mode='pinch'; pinchDist=dist(e.touches); pinchZoom=zoom; e.preventDefault(); }
            else if(e.touches.length===1 && zoom>1.05){ mode='pan'; tX=e.touches[0].clientX; tY=e.touches[0].clientY; pX0=panX; pY0=panY; }
            else if(e.touches.length===1){ mode=null; swipe=true; sX=e.touches[0].clientX; sY=e.touches[0].clientY; }
            else{ mode=null; }
        },{passive:false});
        wrap.addEventListener('touchmove',e=>{
            if(mode==='pinch' && e.touches.length===2){
                e.preventDefault();
                const d=dist(e.touches);
                if(pinchDist>0){
                    zoom=Math.min(MAX,Math.max(MIN,pinchZoom*(d/pinchDist)));
                    if(zoom<1.05){panX=0;panY=0;}
                    updateTransform(); showZoomInfo();
                }
            }else if(mode==='pan' && e.touches.length===1){
                e.preventDefault();
                panX=pX0+(e.touches[0].clientX-tX);
                panY=pY0+(e.touches[0].clientY-tY);
                updateTransform();
            }
        },{passive:false});
        wrap.addEventListener('touchend',e=>{
            if(swipe && e.touches.length===0 && e.changedTouches.length){
                const dx=e.changedTouches[0].clientX-sX, dy=e.changedTouches[0].clientY-sY;
                if(images.length>1 && Math.abs(dx)>50 && Math.abs(dx)>Math.abs(dy)*1.5){ nav(dx<0?1:-1); }
                swipe=false;
            }
            if(e.touches.length===0){ mode=null; }
            else if(e.touches.length===1 && zoom>1.05){ mode='pan'; tX=e.touches[0].clientX; tY=e.touches[0].clientY; pX0=panX; pY0=panY; }
        },{passive:false});
        // 배경 클릭 닫기 (미확대 시)
        box.addEventListener('click',e=>{ if(e.target===box&&zoom<=1.05) close(); });
        // 키보드
        document.addEventListener('keydown',e=>{
            if(!box.classList.contains('open')) return;
            if(e.key==='Escape') close();
            if(e.key==='ArrowLeft') nav(-1);
            if(e.key==='ArrowRight') nav(1);
            if(e.key==='+'||e.key==='='){zoom=Math.min(MAX,zoom*1.3);updateTransform();showZoomInfo();}
            if(e.key==='-'){zoom=Math.max(MIN,zoom/1.3);if(zoom<1.05){panX=0;panY=0;}updateTransform();showZoomInfo();}
            if(e.key==='0'){resetZoom();showZoomInfo();}
        });
    });

    return { open, close, nav, isOpen:()=>el('dvw')?.classList.contains('open') };
})();
</script>
