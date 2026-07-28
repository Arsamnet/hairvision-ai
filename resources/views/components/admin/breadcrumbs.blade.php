@props([
    'crumbs',
])

@php
    $lastIndex = count($crumbs) - 1;
@endphp

<nav {{ $attributes->merge(['class' => 'admin-breadcrumbs']) }} aria-label="{{ __('dashboard.breadcrumbs.label') }}" data-admin-breadcrumbs>
    <ol class="admin-breadcrumbs-list" itemscope itemtype="https://schema.org/BreadcrumbList">
        @foreach($crumbs as $index => $crumb)
            @php $isLast = $index === $lastIndex; @endphp
            <li class="admin-breadcrumbs-list-item" itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">
                @if($index > 0)
                    <x-admin.icon name="chevron-left" class="admin-breadcrumbs-separator h-4 w-4" />
                @endif

                @if(! $isLast && filled($crumb['url'] ?? null))
                    <a href="{{ $crumb['url'] }}" class="admin-breadcrumbs-link" itemprop="item">
                        @if(filled($crumb['icon'] ?? null))
                            <x-admin.icon :name="$crumb['icon']" class="h-4 w-4" />
                        @endif
                        <span itemprop="name">{{ $crumb['label'] }}</span>
                    </a>
                @else
                    <span class="admin-breadcrumbs-current" @if($isLast) aria-current="page" @endif>
                        @if(filled($crumb['icon'] ?? null))
                            <x-admin.icon :name="$crumb['icon']" class="h-4 w-4" />
                        @endif
                        <span itemprop="name">{{ $crumb['label'] }}</span>
                    </span>
                @endif
                <meta itemprop="position" content="{{ $index + 1 }}">
            </li>
        @endforeach
    </ol>
</nav>
