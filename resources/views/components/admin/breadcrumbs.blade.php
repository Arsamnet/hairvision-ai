@props([
    'crumbs',
])

@php
    $lastIndex = count($crumbs) - 1;
@endphp

<section {{ $attributes->merge(['class' => 'admin-breadcrumbs']) }} aria-labelledby="admin-breadcrumb-title" data-admin-breadcrumbs>
    <div class="admin-breadcrumbs-inner">
        <nav class="admin-breadcrumbs-nav" aria-label="{{ __('dashboard.breadcrumbs.label') }}">
            <h2 id="admin-breadcrumb-title" class="sr-only">{{ __('dashboard.breadcrumbs.label') }}</h2>
            <ol class="admin-breadcrumbs-list" itemscope itemtype="https://schema.org/BreadcrumbList">
                @foreach($crumbs as $index => $crumb)
                    @php $isLast = $index === $lastIndex; @endphp
                    <li class="admin-breadcrumbs-list-item" itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">
                        @if($index > 0)
                            <x-admin.icon name="chevron-left" class="admin-breadcrumbs-separator h-4 w-4" />
                        @endif

                        @if(! $isLast && filled($crumb['url']))
                            <a href="{{ $crumb['url'] }}" class="admin-breadcrumbs-link" itemprop="item">
                                @if($crumb['icon'])
                                    <x-admin.icon :name="$crumb['icon']" class="h-4 w-4" />
                                @endif
                                <span itemprop="name">{{ $crumb['label'] }}</span>
                            </a>
                        @else
                            <span class="admin-breadcrumbs-current" @if($isLast) aria-current="page" @endif itemprop="name">
                                @if($crumb['icon'])
                                    <x-admin.icon :name="$crumb['icon']" class="h-4 w-4" />
                                @endif
                                <span>{{ $crumb['label'] }}</span>
                            </span>
                        @endif
                        <meta itemprop="position" content="{{ $index + 1 }}">
                    </li>
                @endforeach
            </ol>
        </nav>

    </div>
</section>
