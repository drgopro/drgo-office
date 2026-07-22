{{-- 에디터 툴바 상단 고정 — 글이 길어져 스크롤해도 툴바가 화면 상단에 유지 --}}
<script>
(function(){
    const bar=document.getElementById('toolbar');
    const wrap=bar?bar.closest('.tiptap-wrap'):null;
    if(!bar||!wrap) return;
    let ph=null;
    function apply(){
        if(wrap.offsetParent===null){ release(); return; } // 에디터 숨김(보기 모드) 시 해제
        const r=wrap.getBoundingClientRect();
        // 에디터 상단이 화면 위로 지나갔고, 에디터 하단이 아직 툴바보다 아래에 있을 때만 고정
        const should=r.top<0 && r.bottom>bar.offsetHeight+40;
        if(should){
            if(!ph){
                ph=document.createElement('div');
                ph.style.height=bar.offsetHeight+'px';
                wrap.insertBefore(ph,bar);
                bar.style.position='fixed'; bar.style.top='0'; bar.style.zIndex='500';
                bar.style.borderRadius='0'; bar.style.boxShadow='0 4px 14px rgba(0,0,0,0.18)';
            }
            bar.style.left=(r.left+wrap.clientLeft)+'px';
            bar.style.width=wrap.clientWidth+'px';
        } else { release(); }
    }
    function release(){
        if(!ph) return;
        ph.remove(); ph=null;
        bar.style.position=''; bar.style.top=''; bar.style.left=''; bar.style.width='';
        bar.style.zIndex=''; bar.style.borderRadius=''; bar.style.boxShadow='';
    }
    addEventListener('scroll',apply,{passive:true});
    addEventListener('resize',apply);
})();
</script>
