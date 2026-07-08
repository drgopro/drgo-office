{{-- 모바일 PWA: 아래로 당겨 새로고침 (standalone 모드에선 네이티브 당김이 없어 직접 구현) --}}
<style>
    #ptrIndicator { position:fixed; top:0; left:50%; z-index:99998; width:36px; height:36px; margin-left:-18px; border-radius:50%; background:var(--surface,#1c1c1c); border:1px solid var(--border,#333); box-shadow:0 4px 14px rgba(0,0,0,0.3); display:flex; align-items:center; justify-content:center; opacity:0; transform:translateY(-100%); pointer-events:none; }
    #ptrIndicator .ptr-spinner { width:18px; height:18px; border-radius:50%; border:2px solid var(--text-muted,#888); border-top-color:transparent; }
    #ptrIndicator.ready .ptr-spinner { border-color:var(--accent,#c8b08a); border-top-color:transparent; }
    #ptrIndicator.refreshing .ptr-spinner { border-color:var(--accent,#c8b08a); border-top-color:transparent; animation:ptrSpin 0.7s linear infinite; }
    @keyframes ptrSpin { to { transform:rotate(360deg); } }
</style>
<div id="ptrIndicator" aria-hidden="true"><div class="ptr-spinner"></div></div>
<script>
(function(){
    if(!('ontouchstart' in window)) return;
    const ind=document.getElementById('ptrIndicator');
    if(!ind) return;
    const spinner=ind.querySelector('.ptr-spinner');
    const THRESHOLD=70, MAX=120, DAMP=0.5;
    let startY=0, pulling=false, dist=0, ready=false;

    function pageTop(){ return window.scrollY || document.scrollingElement?.scrollTop || document.documentElement.scrollTop || 0; }
    // 라이트박스/모달이 열려 있거나, 손가락 아래 내부 스크롤이 위로 밀려 있으면 당김 무시
    function blocked(target){
        if(document.querySelector('.lightbox.open')) return true;
        let el=target;
        while(el && el!==document.body && el!==document.documentElement){
            if(el.scrollHeight>el.clientHeight+2){
                const oy=getComputedStyle(el).overflowY;
                if((oy==='auto'||oy==='scroll') && el.scrollTop>0) return true;
            }
            el=el.parentElement;
        }
        return false;
    }
    function setInd(){
        const p=Math.min(1, dist/THRESHOLD);
        ind.style.opacity=String(p);
        ind.style.transform='translateY('+(dist-40)+'px)';
        spinner.style.transform='rotate('+(dist*4)+'deg)';
        ind.classList.toggle('ready', ready);
    }
    function reset(){
        ind.style.opacity='0';
        ind.style.transform='translateY(-100%)';
        ind.classList.remove('ready');
    }

    window.addEventListener('touchstart',e=>{
        if(e.touches.length!==1 || pageTop()>0 || blocked(e.target)){ pulling=false; return; }
        startY=e.touches[0].clientY; pulling=true; dist=0; ready=false;
    },{passive:true});

    window.addEventListener('touchmove',e=>{
        if(!pulling) return;
        const dy=e.touches[0].clientY-startY;
        if(dy<=0 || pageTop()>0){ pulling=false; reset(); return; }
        if(e.cancelable) e.preventDefault(); // 네이티브 오버스크롤/스크롤 방지
        dist=Math.min(MAX, dy*DAMP);
        ready=dist>=THRESHOLD;
        setInd();
    },{passive:false});

    window.addEventListener('touchend',()=>{
        if(!pulling) return;
        pulling=false;
        if(ready){
            ind.classList.add('refreshing');
            ind.style.opacity='1';
            ind.style.transform='translateY(20px)';
            location.reload();
        } else {
            reset();
        }
        ready=false;
    },{passive:true});
})();
</script>
