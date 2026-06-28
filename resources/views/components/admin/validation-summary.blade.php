@props(['for' => null])

@php
    $shouldShow = $errors->any() && ($for === null || old('_form') === $for || old('_form') === null);
@endphp

@if($shouldShow)
    <div {{ $attributes->merge(['class' => 'rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-bold text-rose-700']) }}>
        <ul class="space-y-1">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
