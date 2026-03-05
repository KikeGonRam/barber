@props(['active'])

@php
$classes = ($active ?? false)
            ? 'block w-full rounded-lg border border-[#7a7a7a] bg-gradient-to-b from-[#585858] to-[#4b4b4b] px-3 py-2 text-start text-base font-medium text-[#f2f2f2] shadow-[inset_0_1px_0_rgba(255,255,255,0.12)] transition'
            : 'block w-full rounded-lg border border-transparent px-3 py-2 text-start text-base font-medium text-[#d7d7d7] transition hover:border-[#666] hover:bg-[#4a4a4a] hover:text-[#f2f2f2]';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
