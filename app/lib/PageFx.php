<?php
/**
 * 页面切换加载特效(顶部进度条 + 内容淡入)
 * 传统多页应用: 点击站内链接/提交表单时顶部滑出进度条, 新页面内容以 yf-enter 淡入
 * render($from, $to) 在 </body> 前输出, 颜色随上下文主题传入(默认靛蓝)
 * 尊重 prefers-reduced-motion: 减少动态时仅保留进度条、关闭淡入
 */
class PageFx
{
    /** 输出特效代码, $from/$to 为进度条渐变两端色值(#RGB/#RRGGBB) */
    public static function render(?string $from = null, ?string $to = null): string
    {
        $from = self::color($from, '#6366f1');
        $to = self::color($to, '#a855f7');
        return <<<HTML
<style>
#yf-pbar{position:fixed;top:0;left:0;z-index:2147483647;height:3px;width:0;border-radius:0 2px 2px 0;
background:linear-gradient(90deg,{$from},{$to});box-shadow:0 0 10px {$from}99;opacity:0;pointer-events:none;transition:opacity .25s}
#yf-pbar.on{opacity:1}
.yf-enter{animation:yf-enter .3s cubic-bezier(.22,.61,.36,1) both}
@keyframes yf-enter{from{opacity:0}to{opacity:1}}
@media (prefers-reduced-motion:reduce){.yf-enter{animation:none}}
</style>
<script>
(function(){
var b=document.createElement('div');b.id='yf-pbar';document.documentElement.appendChild(b);
var t=null,armed=false;
function go(){if(armed)return;armed=true;
b.style.transition='none';b.style.width='8%';
requestAnimationFrame(function(){b.classList.add('on');b.style.transition='width .2s ease';b.style.width='14%';});
var w=14;(function step(){if(w>=88)return;w+=Math.max(.35,(88-w)*.07);b.style.width=w.toFixed(2)+'%';t=setTimeout(step,140+Math.random()*220);})();}
function sameDoc(u){return u.origin===location.origin&&!(u.pathname===location.pathname&&u.search===location.search&&u.hash);}
addEventListener('click',function(e){
if(e.defaultPrevented||e.button!==0||e.metaKey||e.ctrlKey||e.shiftKey||e.altKey)return;
var a=e.target&&e.target.closest?e.target.closest('a'):null;
if(!a||a.hasAttribute('download')||(a.target&&a.target!=='_self'))return;
var h=a.getAttribute('href')||'';
if(!h||h.charAt(0)==='#'||/^(javascript|mailto|tel):/i.test(h))return;
var u;try{u=new URL(a.href,location.href);}catch(x){return;}
if(sameDoc(u))go();
});
addEventListener('submit',function(e){
if(e.defaultPrevented)return;
var f=e.target;
if(!f||f.hasAttribute('data-ajax')||f.hasAttribute('data-nopg')||(f.target&&f.target!=='_self'))return;
go();
});
addEventListener('pageshow',function(e){
if(e.persisted){armed=false;clearTimeout(t);b.classList.remove('on');b.style.transition='none';b.style.width='0';}
});
})();
</script>
HTML;
    }

    /** 颜色白名单校验, 非法值回落默认 */
    private static function color(?string $v, string $def): string
    {
        return is_string($v) && preg_match('/^#[0-9a-fA-F]{3,8}$/', $v) ? $v : $def;
    }
}
