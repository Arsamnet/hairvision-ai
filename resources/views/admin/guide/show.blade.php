@extends('admin.layout')

@section('title', __('guide.user_guide_title'))
@section('page_title', __('guide.user_guide_title'))

@section('content')
    @php
        $canManageGuide = auth()->user()?->hasPermission('guide.manage', $tenant?->id) ?? false;
    @endphp

    <div class="mx-auto max-w-6xl space-y-5">
        <x-admin.card class="overflow-hidden">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="flex min-w-0 items-start gap-4">
                    <span class="grid h-12 w-12 shrink-0 place-items-center rounded-xl bg-teal-50 text-teal-700 ring-1 ring-teal-100">
                        <x-admin.icon name="guide" class="h-6 w-6" />
                    </span>
                    <div class="min-w-0">
                        <h2 class="text-xl font-bold text-zinc-900">{{ $guide['title'] }}</h2>
                        <div class="admin-rich-text-content mt-2 max-w-3xl text-sm leading-7 text-zinc-600">{!! $guide['introduction'] !!}</div>
                    </div>
                </div>

                <div class="flex flex-wrap gap-2">
                    <x-admin.button :href="route('admin.guide.faq')" variant="secondary" icon="faq">
                        {{ __('guide.open_faqs') }}
                    </x-admin.button>
                    @if($canManageGuide)
                        <x-admin.button :href="route('admin.system.guide.edit')" variant="secondary" icon="edit">
                            {{ __('guide.edit_content') }}
                        </x-admin.button>
                    @endif
                </div>
            </div>
        </x-admin.card>

        <nav id="guide-index" aria-labelledby="guide-index-title" class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-zinc-200">
            <div class="flex items-start gap-3">
                <x-admin.icon name="bookmark" class="mt-0.5 h-5 w-5 text-teal-700" />
                <div>
                    <h2 id="guide-index-title" class="font-bold text-zinc-900">{{ __('guide.table_of_contents') }}</h2>
                    <p class="mt-1 text-sm leading-6 text-zinc-500">{{ __('guide.table_of_contents_hint') }}</p>
                </div>
            </div>

            <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                @foreach($guide['sections'] as $sectionIndex => $section)
                    <a href="#guide-section-{{ $sectionIndex + 1 }}" class="admin-guide-toc-link">
                        <span class="admin-guide-toc-number">{{ $sectionIndex + 1 }}</span>
                        <span class="min-w-0">
                            <span class="block font-bold text-zinc-900">{{ $section['title'] }}</span>
                            <span class="mt-1 block text-xs text-zinc-500">{{ __('guide.step_count', ['count' => count($section['steps'])]) }}</span>
                        </span>
                    </a>
                @endforeach

                <a href="{{ route('admin.guide.faq') }}" class="admin-guide-toc-link">
                    <span class="admin-guide-toc-number">
                        <x-admin.icon name="faq" class="h-4 w-4" />
                    </span>
                    <span class="min-w-0">
                        <span class="block font-bold text-zinc-900">{{ __('guide.frequently_asked_questions') }}</span>
                        <span class="mt-1 block text-xs text-zinc-500">{{ __('guide.item_count', ['count' => $faqCount]) }}</span>
                    </span>
                </a>
            </div>
        </nav>

        @foreach($guide['sections'] as $sectionIndex => $section)
            <article id="guide-section-{{ $sectionIndex + 1 }}" class="admin-guide-section scroll-mt-5 rounded-lg bg-white p-5 shadow-sm ring-1 ring-zinc-200">
                <header class="flex flex-wrap items-start justify-between gap-3 border-b border-zinc-100 pb-4">
                    <div class="flex min-w-0 items-start gap-3">
                        <span class="admin-guide-section-number">{{ $sectionIndex + 1 }}</span>
                        <div class="min-w-0">
                            <h2 class="text-lg font-bold text-zinc-900">{{ $section['title'] }}</h2>
                            <div class="admin-rich-text-content mt-2 text-sm leading-7 text-zinc-600">{!! $section['description'] !!}</div>
                        </div>
                    </div>
                    @if(filled($section['audience']))
                        <span class="rounded-full bg-teal-50 px-3 py-1.5 text-xs font-bold text-teal-700 ring-1 ring-teal-100">
                            {{ $section['audience'] }}
                        </span>
                    @endif
                </header>

                <ol class="mt-5 space-y-4">
                    @foreach($section['steps'] as $stepIndex => $step)
                        <li class="flex items-start gap-3">
                            <span class="admin-guide-step-number">{{ $stepIndex + 1 }}</span>
                            <div class="min-w-0 pt-0.5">
                                <h3 class="font-bold text-zinc-900">{{ $step['title'] }}</h3>
                                <div class="admin-rich-text-content mt-1 text-sm leading-7 text-zinc-600">{!! $step['body'] !!}</div>
                            </div>
                        </li>
                    @endforeach
                </ol>

                <div class="mt-5 flex justify-end">
                    <a href="#guide-index" class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-xs font-bold text-teal-700 outline-none hover:bg-teal-50 focus:ring-2 focus:ring-teal-500">
                        <x-admin.icon name="arrow-up" class="h-4 w-4" />
                        {{ __('guide.back_to_top') }}
                    </a>
                </div>
            </article>
        @endforeach

    </div>
@endsection
