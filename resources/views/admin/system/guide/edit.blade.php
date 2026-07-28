@extends('admin.layout')

@section('title', __('guide.management_title'))
@section('page_title', __('guide.management_title'))

@section('content')
    <div class="mx-auto max-w-6xl space-y-5">
        <x-admin.validation-summary />

        <x-admin.card>
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="flex items-start gap-3">
                    <span class="grid h-11 w-11 shrink-0 place-items-center rounded-lg bg-teal-50 text-teal-700 ring-1 ring-teal-100"><x-admin.icon name="guide" class="h-5 w-5" /></span>
                    <div>
                        <h2 class="text-lg font-bold text-zinc-900">{{ __('guide.management_title') }}</h2>
                        <p class="mt-1 max-w-3xl text-sm leading-7 text-zinc-500">{{ __('guide.management_description') }}</p>
                        <div class="mt-3 flex flex-wrap gap-2 text-xs font-bold text-zinc-600">
                            <span class="rounded-full bg-zinc-100 px-3 py-1.5">{{ __('guide.section_count', ['count' => $page->sections->count()]) }}</span>
                            <span class="rounded-full bg-zinc-100 px-3 py-1.5">{{ __('guide.step_count', ['count' => $page->sections->sum(fn ($section) => $section->steps->count())]) }}</span>
                        </div>
                    </div>
                </div>
                <div class="flex flex-wrap gap-2">
                    <x-admin.button :href="route('admin.system.faq.edit')" variant="secondary" icon="faq">{{ __('guide.manage_faqs') }}</x-admin.button>
                    <x-admin.button :href="route('admin.guide.show')" variant="secondary" icon="eye">{{ __('guide.view_guide') }}</x-admin.button>
                </div>
            </div>
        </x-admin.card>

        <x-admin.card>
            <div class="mb-4"><h2 class="font-bold text-zinc-900">{{ __('guide.page_identity') }}</h2><p class="mt-1 text-sm leading-6 text-zinc-500">{{ __('guide.page_identity_hint') }}</p></div>
            <form method="POST" action="{{ route('admin.system.guide.update') }}" class="grid gap-4">
                @csrf @method('PUT')
                <input type="hidden" name="_form" value="guide_page">
                <label class="grid gap-1 text-sm font-bold"><span>{{ __('guide.guide_title') }}</span><input name="title" value="{{ old('_form') === 'guide_page' ? old('title') : $page->title }}" required maxlength="150" class="h-11 rounded-lg border border-zinc-200 px-3 outline-none focus:border-teal-500"></label>
                <x-admin.rich-text-editor name="introduction" :value="old('_form') === 'guide_page' ? old('introduction') : $page->introduction_html" :label="__('guide.introduction')" :placeholder="__('guide.placeholders.introduction')" required :maxlength="10000" />
                <div class="flex justify-end"><x-admin.button type="submit" icon="check">{{ __('guide.save_page') }}</x-admin.button></div>
            </form>
        </x-admin.card>

        @if($editingSection)
            <x-admin.card id="content-editor" class="scroll-mt-24 ring-2 ring-teal-200">
                <div class="mb-4 flex items-center justify-between gap-3"><div><h2 class="font-bold text-zinc-900">{{ __('guide.edit_section') }}</h2><p class="mt-1 text-sm text-zinc-500">{{ $editingSection->title }}</p></div><x-admin.button :href="route('admin.system.guide.edit').'#guide-section-'.$editingSection->id" variant="secondary" size="compact-sm">{{ __('messages.cancel') }}</x-admin.button></div>
                <form method="POST" action="{{ route('admin.system.guide.sections.update', $editingSection) }}" class="grid gap-4">
                    @csrf @method('PUT')<input type="hidden" name="_form" value="section_{{ $editingSection->id }}">
                    @include('admin.system.guide.partials.section-fields', ['section' => $editingSection])
                    <div class="flex justify-end"><x-admin.button type="submit" icon="check">{{ __('guide.save_section') }}</x-admin.button></div>
                </form>
            </x-admin.card>
        @elseif($editingStep)
            <x-admin.card id="content-editor" class="scroll-mt-24 ring-2 ring-teal-200">
                <div class="mb-4 flex items-center justify-between gap-3"><div><h2 class="font-bold text-zinc-900">{{ __('guide.edit_step') }}</h2><p class="mt-1 text-sm text-zinc-500">{{ $editingStep->section->title }}</p></div><x-admin.button :href="route('admin.system.guide.edit').'#guide-step-'.$editingStep->id" variant="secondary" size="compact-sm">{{ __('messages.cancel') }}</x-admin.button></div>
                <form method="POST" action="{{ route('admin.system.guide.steps.update', $editingStep) }}" class="grid gap-4">
                    @csrf @method('PUT')<input type="hidden" name="_form" value="step_{{ $editingStep->id }}">
                    @include('admin.system.guide.partials.step-fields', ['step' => $editingStep])
                    <div class="flex justify-end"><x-admin.button type="submit" icon="check">{{ __('guide.save_step') }}</x-admin.button></div>
                </form>
            </x-admin.card>
        @elseif($addStepSection)
            <x-admin.card id="content-editor" class="scroll-mt-24 ring-2 ring-teal-200">
                <div class="mb-4 flex items-center justify-between gap-3"><div><h2 class="font-bold text-zinc-900">{{ __('guide.add_step_to', ['section' => $addStepSection->title]) }}</h2><p class="mt-1 text-sm text-zinc-500">{{ __('guide.new_step_hint') }}</p></div><x-admin.button :href="route('admin.system.guide.edit').'#guide-section-'.$addStepSection->id" variant="secondary" size="compact-sm">{{ __('messages.cancel') }}</x-admin.button></div>
                <form method="POST" action="{{ route('admin.system.guide.steps.store', $addStepSection) }}" class="grid gap-4">
                    @csrf <input type="hidden" name="_form" value="create_step_{{ $addStepSection->id }}">
                    @include('admin.system.guide.partials.step-fields', ['step' => null])
                    <div class="flex justify-end"><x-admin.button type="submit" icon="plus">{{ __('guide.create_step') }}</x-admin.button></div>
                </form>
            </x-admin.card>
        @endif

        <section class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-zinc-200">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div><h2 class="text-lg font-bold text-zinc-900">{{ __('guide.sections_heading') }}</h2><p class="mt-1 text-sm leading-6 text-zinc-500">{{ __('guide.section_manager_hint') }}</p></div>
                <x-admin.modal id="create-guide-section" :label="__('guide.add_section')" :title="__('guide.add_section')" icon="plus" :open="old('_form') === 'create_section'">
                    <form method="POST" action="{{ route('admin.system.guide.sections.store') }}" class="grid gap-4">@csrf <input type="hidden" name="_form" value="create_section">@include('admin.system.guide.partials.section-fields', ['section' => null])<div class="flex justify-end"><x-admin.button type="submit" icon="plus">{{ __('guide.create_section') }}</x-admin.button></div></form>
                </x-admin.modal>
            </div>

            <div class="mt-5 space-y-4">
                @forelse($page->sections as $sectionIndex => $section)
                    <article id="guide-section-{{ $section->id }}" class="scroll-mt-24 rounded-xl border border-zinc-200 bg-zinc-50/60 p-4">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="flex min-w-0 items-start gap-3"><span class="admin-guide-section-number">{{ $sectionIndex + 1 }}</span><div class="min-w-0"><h3 class="font-bold text-zinc-900">{{ $section->title }}</h3><p class="mt-1 text-xs text-zinc-500">{{ $section->audience ?: __('guide.audience_not_set') }} · {{ __('guide.step_count', ['count' => $section->steps->count()]) }}</p></div></div>
                            <div class="flex flex-wrap items-center gap-2">
                                <x-admin.button :href="route('admin.system.guide.edit', ['section' => $section->id]).'#content-editor'" variant="secondary" size="icon-sm" icon="edit" aria-label="{{ __('guide.edit_section') }}" />
                                <form method="POST" action="{{ route('admin.system.guide.sections.move', $section) }}">@csrf @method('PATCH')<input type="hidden" name="direction" value="up"><x-admin.button type="submit" variant="secondary" size="icon-sm" icon="arrow-up" aria-label="{{ __('guide.move_up') }}" :disabled="$loop->first" /></form>
                                <form method="POST" action="{{ route('admin.system.guide.sections.move', $section) }}">@csrf @method('PATCH')<input type="hidden" name="direction" value="down"><x-admin.button type="submit" variant="secondary" size="icon-sm" icon="arrow-down" aria-label="{{ __('guide.move_down') }}" :disabled="$loop->last" /></form>
                                <form method="POST" action="{{ route('admin.system.guide.sections.destroy', $section) }}" data-confirm-form data-confirm-message="{{ __('guide.confirm_delete_section') }}">@csrf @method('DELETE')<x-admin.button type="submit" variant="danger-outline" size="icon-sm" icon="trash" aria-label="{{ __('guide.remove') }}" /></form>
                            </div>
                        </div>
                        <p class="mt-3 text-sm leading-7 text-zinc-600">{{ \Illuminate\Support\Str::limit(html_entity_decode(strip_tags($section->description_html), ENT_QUOTES | ENT_HTML5, 'UTF-8'), 240) }}</p>

                        <div class="mt-4 rounded-lg bg-white p-3 ring-1 ring-zinc-200">
                            <div class="flex flex-wrap items-center justify-between gap-3"><h4 class="text-sm font-bold text-zinc-900">{{ __('guide.section_steps') }}</h4><x-admin.button :href="route('admin.system.guide.edit', ['add_step' => $section->id]).'#content-editor'" variant="secondary" size="compact-sm" icon="plus">{{ __('guide.add_step') }}</x-admin.button></div>
                            <ol class="mt-3 space-y-2">
                                @forelse($section->steps as $stepIndex => $step)
                                    <li id="guide-step-{{ $step->id }}" class="scroll-mt-24 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-zinc-200 bg-zinc-50 px-3 py-2.5">
                                        <div class="flex min-w-0 items-center gap-2"><span class="admin-guide-step-number">{{ $stepIndex + 1 }}</span><span class="truncate text-sm font-bold text-zinc-800">{{ $step->title }}</span></div>
                                        <div class="flex gap-2">
                                            <x-admin.button :href="route('admin.system.guide.edit', ['step' => $step->id]).'#content-editor'" variant="secondary" size="icon-sm" icon="edit" aria-label="{{ __('guide.edit_step') }}" />
                                            <form method="POST" action="{{ route('admin.system.guide.steps.move', $step) }}">@csrf @method('PATCH')<input type="hidden" name="direction" value="up"><x-admin.button type="submit" variant="secondary" size="icon-sm" icon="arrow-up" aria-label="{{ __('guide.move_up') }}" :disabled="$loop->first" /></form>
                                            <form method="POST" action="{{ route('admin.system.guide.steps.move', $step) }}">@csrf @method('PATCH')<input type="hidden" name="direction" value="down"><x-admin.button type="submit" variant="secondary" size="icon-sm" icon="arrow-down" aria-label="{{ __('guide.move_down') }}" :disabled="$loop->last" /></form>
                                            <form method="POST" action="{{ route('admin.system.guide.steps.destroy', $step) }}" data-confirm-form data-confirm-message="{{ __('guide.confirm_delete_step') }}">@csrf @method('DELETE')<x-admin.button type="submit" variant="danger-outline" size="icon-sm" icon="trash" aria-label="{{ __('guide.remove') }}" /></form>
                                        </div>
                                    </li>
                                @empty
                                    <li class="rounded-lg border border-dashed border-zinc-300 px-4 py-5 text-center text-sm text-zinc-500">{{ __('guide.no_steps') }}</li>
                                @endforelse
                            </ol>
                        </div>
                    </article>
                @empty
                    <div class="rounded-lg border border-dashed border-zinc-300 px-5 py-10 text-center"><x-admin.icon name="guide" class="mx-auto h-8 w-8 text-zinc-400" /><p class="mt-3 text-sm text-zinc-500">{{ __('guide.no_sections') }}</p></div>
                @endforelse
            </div>
        </section>
    </div>
@endsection
