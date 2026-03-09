@props(['active'])

@php
$classes = ($active ?? false)
            ? 'flex items-center gap-2 rounded-xl border border-gold/30 bg-gold/10 px-3 py-2.5 text-sm font-bold text-gold shadow-[0_0_15px_rgba(212,175,55,0.1)] transition-all'
            : 'flex items-center gap-2 rounded-xl border border-transparent px-3 py-2.5 text-sm font-bold text-muted transition-all hover:bg-white/5 hover:text-white';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
