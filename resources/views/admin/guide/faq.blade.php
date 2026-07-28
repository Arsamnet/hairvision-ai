@extends('admin.layout')

@section('title', __('guide.faq_page_title'))
@section('page_title', __('guide.faq_page_title'))

@section('content')
    <div class="mx-auto max-w-5xl space-y-5">
        <x-admin.card>
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="flex min-w-0 items-start gap-4">
                    <span class="grid h-12 w-12 shrink-0 place-items-center rounded-xl bg-teal-50 text-teal-700 ring-1 ring-teal-100">
                        <x-admin.icon name="faq" class="h-6 w-6" />
                    </span>
                    <div class="min-w-0">
                        <h2 class="text-xl font-bold text-zinc-900">{{ $faq['title'] }}</h2>
                        <div class="admin-rich-text-content mt-2 max-w-3xl text-sm leading-7 text-zinc-600">{!! $faq['introduction'] !!}</div>
                    </div>
                </div>
                <x-admin.button :href="route('admin.guide.show')" variant="secondary" icon="guide">
                    {{ __('guide.open_guide') }}
                </x-admin.button>
            </div>
        </x-admin.card>

        <nav id="faq-index" aria-labelledby="faq-index-title" class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-zinc-200">
            <div class="flex items-start gap-3">
                <x-admin.icon name="bookmark" class="mt-0.5 h-5 w-5 text-teal-700" />
                <div>
                    <h2 id="faq-index-title" class="font-bold text-zinc-900">{{ __('guide.table_of_contents') }}</h2>
                    <p class="mt-1 text-sm leading-6 text-zinc-500">{{ __('guide.table_of_contents_hint') }}</p>
                </div>
            </div>
            <div class="mt-4 grid gap-3 lg:grid-cols-2">
                @foreach($faq['faqs'] as $faqIndex => $faqItem)
                    <a href="#faq-item-{{ $faqIndex + 1 }}" class="admin-guide-toc-link">
                        <span class="admin-guide-toc-number">{{ $faqIndex + 1 }}</span>
                        <span class="font-bold text-zinc-900">{{ $faqItem['question'] }}</span>
                    </a>
                @endforeach
            </div>
        </nav>

        <section aria-labelledby="faq-list-title" class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-zinc-200">
            <div class="flex items-center gap-3">
                <span class="grid h-10 w-10 place-items-center rounded-lg bg-teal-50 text-teal-700 ring-1 ring-teal-100">
                    <x-admin.icon name="faq" class="h-5 w-5" />
                </span>
                <h2 id="faq-list-title" class="text-lg font-bold text-zinc-900">{{ __('guide.frequently_asked_questions') }}</h2>
            </div>

            <div class="mt-5 space-y-3">
                @foreach($faq['faqs'] as $faqIndex => $faqItem)
                    <details id="faq-item-{{ $faqIndex + 1 }}" class="admin-guide-faq scroll-mt-5 rounded-lg border border-zinc-200 bg-zinc-50/60" @if($faqIndex === 0) open @endif>
                        <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-4 py-3 font-bold text-zinc-900 outline-none">
                            <span>{{ $faqItem['question'] }}</span>
                            <x-admin.icon name="chevron-down" class="admin-guide-faq-caret h-4 w-4 shrink-0 text-teal-700" />
                        </summary>
                        <div class="admin-guide-faq-panel">
                            <div class="admin-guide-faq-panel-inner">
                                <div class="admin-rich-text-content border-t border-zinc-200 px-4 py-4 text-sm leading-7 text-zinc-600">{!! $faqItem['answer'] !!}</div>
                            </div>
                        </div>
                    </details>
                @endforeach
            </div>
        </section>
    </div>
@endsection
