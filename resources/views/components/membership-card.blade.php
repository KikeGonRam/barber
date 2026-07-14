@props([
    'nivel' => 'nuevo',
    'label' => '',
    'puntos' => 0,
    'nombre' => '',
    'numero' => '',
    'desde' => '',
])
@php
    $tierClass = [
        'nuevo' => 'mc-nuevo',
        'regular' => 'mc-regular',
        'vip' => 'mc-vip',
        'leyenda' => 'mc-leyenda',
    ][$nivel] ?? 'mc-vip';
@endphp

<div class="mc-stage">
    <div class="mc-card {{ $tierClass }} mc-sweep mc-tilt" data-points="{{ (int) $puntos }}">
        @if($nivel === 'leyenda')<div class="mc-holo"></div>@endif
        <div class="mc-noise"></div>
        <div class="mc-glare"></div>
        <div class="mc-inner">
            <div class="mc-row">
                <div class="mc-brand">
                    <svg viewBox="0 0 100 116" aria-hidden="true"><path fill="none" stroke="#d4af37" stroke-width="4" d="M50 8 87 24v31c0 24-16 41-37 50C29 96 13 79 13 55V24L50 8Z"/><path fill="#d4af37" d="M30 52h40l-7 6H37zM33 44l10-6 22 3-9 6z"/><circle cx="50" cy="74" r="4.5" fill="#d4af37"/></svg>
                    <span class="mc-wm">Urban<span>Blade</span></span>
                </div>
                <span class="mc-badge">{{ $label }}</span>
            </div>
            <div class="mc-chip"></div>
            <div class="mc-foot">
                <div class="mc-holder">{{ $nombre }}</div>
                <div class="mc-num">{{ $numero }}</div>
                <div class="mc-meta">
                    <div>
                        <div class="mc-lab">Miembro desde</div>
                        <div class="mc-val">{{ $desde }}</div>
                    </div>
                    <div style="text-align:right">
                        <div class="mc-lab">Puntos</div>
                        <div class="mc-val mc-gold" data-count>0</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .mc-stage{perspective:1200px}
    .mc-card{position:relative;width:100%;aspect-ratio:1.586/1;border-radius:18px;padding:22px 24px;overflow:hidden;
        transform-style:preserve-3d;transition:transform .12s ease;cursor:pointer;
        border:1px solid rgba(255,255,255,.08);box-shadow:0 24px 55px rgba(0,0,0,.6),0 2px 0 rgba(255,255,255,.05) inset;
        font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif}
    .mc-card::before{content:"";position:absolute;inset:-2px;z-index:2;pointer-events:none;
        background:linear-gradient(115deg,transparent 35%,rgba(255,255,255,.20) 48%,transparent 62%);
        background-size:250% 250%;background-position:150% 0}
    .mc-sweep::before{animation:mcSweep 6s ease-in-out infinite}
    @keyframes mcSweep{0%,62%{background-position:150% 0}100%{background-position:-50% 0}}
    .mc-glare{position:absolute;inset:0;z-index:3;pointer-events:none;opacity:0;transition:opacity .2s;mix-blend-mode:soft-light;
        background:radial-gradient(220px circle at var(--mx,50%) var(--my,50%),rgba(255,255,255,.28),transparent 60%)}
    .mc-card:hover .mc-glare{opacity:1}
    .mc-noise{position:absolute;inset:0;z-index:1;opacity:.5;pointer-events:none;
        background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='120' height='120'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='.85' numOctaves='2'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='.045'/%3E%3C/svg%3E")}
    .mc-inner{position:relative;z-index:4;display:flex;flex-direction:column;height:100%}
    .mc-row{display:flex;justify-content:space-between;align-items:flex-start}
    .mc-brand{display:flex;align-items:center;gap:8px}
    .mc-brand svg{width:26px;height:26px}
    .mc-wm{font-weight:900;text-transform:uppercase;letter-spacing:-.02em;font-size:15px;color:#f4f4f2}
    .mc-wm span{color:#d4af37}
    .mc-badge{font-size:9px;font-weight:800;letter-spacing:.14em;text-transform:uppercase;padding:5px 10px;border-radius:999px;border:1px solid currentColor}
    .mc-chip{position:relative;width:38px;height:29px;border-radius:6px;margin:16px 0 0;
        background:linear-gradient(135deg,#e9d9a0,#b9962f);box-shadow:0 1px 2px rgba(0,0,0,.4) inset,0 1px 1px rgba(255,255,255,.3)}
    .mc-chip::after{content:"";position:absolute;inset:5px;border-radius:3px;
        background:linear-gradient(90deg,transparent 46%,rgba(0,0,0,.35) 46%,rgba(0,0,0,.35) 54%,transparent 54%),
        linear-gradient(0deg,transparent 44%,rgba(0,0,0,.35) 44%,rgba(0,0,0,.35) 56%,transparent 56%)}
    .mc-foot{margin-top:auto}
    .mc-holder{font-size:17px;font-weight:800;letter-spacing:.02em;text-transform:uppercase;margin-top:12px;color:#fff;line-height:1.15}
    .mc-num{font-family:ui-monospace,'Roboto Mono',Menlo,Consolas,monospace;font-size:12px;letter-spacing:.16em;color:rgba(255,255,255,.72);margin-top:3px}
    .mc-meta{display:flex;justify-content:space-between;align-items:flex-end;margin-top:11px}
    .mc-lab{font-size:7.5px;font-weight:800;letter-spacing:.18em;text-transform:uppercase;color:rgba(255,255,255,.5)}
    .mc-val{font-size:13px;font-weight:800;color:#fff;font-variant-numeric:tabular-nums}
    .mc-gold{color:#d4af37}
    /* Acabados por nivel */
    .mc-nuevo{background:linear-gradient(145deg,#20222a,#0d0e12)}
    .mc-nuevo .mc-badge{color:#c9ccd4}
    .mc-regular{background:linear-gradient(145deg,#182433,#0b1320)}
    .mc-regular .mc-badge{color:#7fb0e6}
    .mc-vip{background:linear-gradient(145deg,#2b2415 0%,#171207 55%,#241d0e 100%);
        box-shadow:0 24px 55px rgba(0,0,0,.6),0 0 0 1px rgba(212,175,55,.25) inset,0 2px 0 rgba(255,255,255,.06) inset}
    .mc-vip .mc-badge{color:#d4af37}
    .mc-leyenda{background:#0c0b10}
    .mc-leyenda .mc-badge{color:#f0abfc}
    .mc-holo{position:absolute;inset:0;z-index:1;opacity:.5;pointer-events:none;mix-blend-mode:color-dodge;
        background:conic-gradient(from 0deg,#e879f9,#60a5fa,#34d399,#f5d87a,#e879f9);background-size:300% 300%;
        filter:blur(2px) saturate(1.2);animation:mcHolo 8s linear infinite}
    @keyframes mcHolo{0%{background-position:0% 50%}100%{background-position:300% 50%}}
    @media (prefers-reduced-motion:reduce){.mc-sweep::before,.mc-holo{animation:none}}
</style>

<script>
(function(){
    var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    document.querySelectorAll('.mc-tilt').forEach(function(card){
        if(card.dataset.mcInit) return; card.dataset.mcInit = '1';
        var max = 9;
        card.addEventListener('pointermove', function(e){
            if(reduce) return;
            var r = card.getBoundingClientRect();
            var px = (e.clientX - r.left)/r.width, py = (e.clientY - r.top)/r.height;
            card.style.transform = 'rotateY('+((px-.5)*max*2)+'deg) rotateX('+(-(py-.5)*max*2)+'deg) translateY(-3px)';
            var g = card.querySelector('.mc-glare');
            if(g){ g.style.setProperty('--mx', px*100+'%'); g.style.setProperty('--my', py*100+'%'); }
        });
        card.addEventListener('pointerleave', function(){ card.style.transform=''; });
        var el = card.querySelector('[data-count]');
        var target = parseInt(card.dataset.points || '0', 10) || 0;
        if(el){
            if(reduce){ el.textContent = target.toLocaleString(); }
            else {
                var cur = 0, step = Math.max(1, Math.ceil(target/45));
                var t = setInterval(function(){ cur = Math.min(target, cur+step); el.textContent = cur.toLocaleString(); if(cur>=target) clearInterval(t); }, 22);
            }
        }
    });
})();
</script>
