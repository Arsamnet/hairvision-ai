@extends('admin.layout')

@section('title', __('guide.faq_management_title'))
@section('page_title', __('guide.faq_management_title'))

@section('content')
    <div class="mx-auto max-w-6xl space-y-5">
        <x-admin.validation-summary />

        <x-admin.card>
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="flex items-start gap-3">
                    <span class="grid h-11 w-11 shrink-0 place-items-center rounded-lg bg-teal-50 text-teal-700 ring-1 ring-teal-100"><x-admin.icon name="faq" class="h-5 w-5" /></span>
                    <div><h2 class="text-lg font-bold text-zinc-900">{{ __('guide.faq_management_title') }}</h2><p class="mt-1 max-w-3xl text-sm leading-7 text-zinc-500">{{ __('guide.faq_management_description') }}</p><span class="mt-3 inline-flex rounded-full bg-zinc-100 px-3 py-1.5 text-xs font-bold text-zinc-600">{{ __('guide.item_count', ['count' => $page->faqs->count()]) }}</span></div>
                </div>
                <div class="flex flex-wrap gap-2"><x-admin.button :href="route('admin.system.guide.edit')" variant="secondary" icon="guide">{{ __('guide.manage_guide') }}</x-admin.button><x-admin.button :href="route('admin.guide.faq')" variant="secondary" icon="eye">{{ __('guide.view_faqs') }}</x-admin.button></div>
            </div>
        </x-admin.card>

        <x-admin.card>
            <div class="mb-4"><h2 class="font-bold text-zinc-900">{{ __('guide.faq_page_identity') }}</h2><p class="mt-1 text-sm leading-6 text-zinc-500">{{ __('guide.faq_page_identity_hint') }}</p></div>
            <form method="POST" action="{{ route('admin.system.faq.update') }}" class="grid gap-4">
                @csrf @method('PUT')<input type="hidden" name="_form" value="faq_page">
                <label class="grid gap-1 text-sm font-bold"><span>{{ __('guide.guide_title') }}</span><input name="title" value="{{ old('_form') === 'faq_page' ? old('title') : $page->title }}" required maxlength="150" class="h-11 rounded-lg border border-zinc-200 px-3 outline-none focus:border-teal-500"></label>
                <x-admin.rich-text-editor name="introduction" :value="old('_form') === 'faq_page' ? old('introduction') : $page->introduction_html" :label="__('guide.introduction')" :placeholder="__('guide.placeholders.faq_introduction')" required :maxlength="10000" />
                <div class="flex justify-end"><x-admin.button type="submit" icon="check">{{ __('guide.save_page') }}</x-admin.button></div>
            </form>
        </x-admin.card>

        @if($editingFaq)
            <x-admin.card id="faq-content-editor" class="scroll-mt-24 ring-2 ring-teal-200">
                <div class="mb-4 flex items-center justify-between gap-3"><div><h2 class="font-bold text-zinc-900">{{ __('guide.edit_faq') }}</h2><p class="mt-1 text-sm text-zinc-500">{{ $editingFaq->question }}</p></div><x-admin.button :href="route('admin.system.faq.edit').'#managed-faq-'.$editingFaq->id" variant="secondary" size="compact-sm">{{ __('messages.cancel') }}</x-admin.button></div>
                <form method="POST" action="{{ route('admin.system.faq.items.update', $editingFaq) }}" class="grid gap-4">
                    @csrf @method('PUT')<input type="hidden" name="_form" value="faq_{{ $editingFaq->id }}">
                    @include('admin.system.guide.partials.faq-fields', ['faq' => $editingFaq])
                    <div class="flex justify-end"><x-admin.button type="submit" icon="check">{{ __('guide.save_faq') }}</x-admin.button></div>
                </form>
            </x-admin.card>
        @endif

        <section class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-zinc-200">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div><h2 class="text-lg font-bold text-zinc-900">{{ __('guide.faqs_heading') }}</h2><p class="mt-1 text-sm leading-6 text-zinc-500">{{ __('guide.faq_manager_hint') }}</p></div>
                <x-admin.modal id="create-guide-faq" :label="__('guide.add_faq')" :title="__('guide.add_faq')" icon="plus" :open="old('_form') === 'create_faq'">
                    <form method="POST" action="{{ route('admin.system.faq.items.store') }}" class="grid gap-4">@csrf <input type="hidden" name="_form" value="create_faq">@include('admin.system.guide.partials.faq-fields', ['faq' => null])<div class="flex justify-end"><x-admin.button type="submit" icon="plus">{{ __('guide.create_faq') }}</x-admin.button></div></form>
                </x-admin.modal>
            </div>

            <ol class="mt-5 space-y-3">
                @forelse($page->faqs as $faqIndex => $faq)
                    <li id="managed-faq-{{ $faq->id }}" class="scroll-mt-24 rounded-xl border border-zinc-200 bg-zinc-50/60 p-4">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="flex min-w-0 items-start gap-3"><span class="admin-guide-section-number">{{ $faqIndex + 1 }}</span><div class="min-w-0"><h3 class="font-bold leading-7 text-zinc-900">{{ $faq->question }}</h3><p class="mt-1 line-clamp-2 text-sm leading-7 text-zinc-500">{{ \Illuminate\Support\Str::limit(html_entity_decode(strip_tags($faq->answer_html), ENT_QUOTES | ENT_HTML5, 'UTF-8'), 180) }}</p></div></div>
                            <div class="flex gap-2">
                                <x-admin.button :href="route('admin.system.faq.edit', ['faq' => $faq->id]).'#faq-content-editor'" variant="secondary" size="icon-sm" icon="edit" aria-label="{{ __('guide.edit_faq') }}" />
                                <form method="POST" action="{{ route('admin.system.faq.items.move', $faq) }}">@csrf @method('PATCH')<input type="hidden" name="direction" value="up"><x-admin.button type="submit" variant="secondary" size="icon-sm" icon="arrow-up" aria-label="{{ __('guide.move_up') }}" :disabled="$loop->first" /></form>
                                <form method="POST" action="{{ route('admin.system.faq.items.move', $faq) }}">@csrf @method('PATCH')<input type="hidden" name="direction" value="down"><x-admin.button type="submit" variant="secondary" size="icon-sm" icon="arrow-down" aria-label="{{ __('guide.move_down') }}" :disabled="$loop->last" /></form>
                                <form method="POST" action="{{ route('admin.system.faq.items.destroy', $faq) }}" data-confirm-form data-confirm-message="{{ __('guide.confirm_delete_faq') }}">@csrf @method('DELETE')<x-admin.button type="submit" variant="danger-outline" size="icon-sm" icon="trash" aria-label="{{ __('guide.remove') }}" /></form>
                            </div>
                        </div>
                    </li>
                @empty
                    <li class="rounded-lg border border-dashed border-zinc-300 px-5 py-10 text-center"><x-admin.icon name="faq" class="mx-auto h-8 w-8 text-zinc-400" /><p class="mt-3 text-sm text-zinc-500">{{ __('guide.no_faqs') }}</p></li>
                @endforelse
            </ol>
        </section>
    </div>
@endsection
