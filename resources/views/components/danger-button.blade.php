<button {{ $attributes->merge(['type' => 'submit', 'class' => 'ui-btn']) }}>
    {{ $slot }}
</button>
