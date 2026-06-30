@props(['label'])

<div {{ $attributes->merge(['class' => 'admin-mobile-list-field']) }}>
    <dt>{{ $label }}</dt>
    <dd>{{ $slot }}</dd>
</div>
