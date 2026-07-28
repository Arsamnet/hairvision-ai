@props([
    'name',
    'value' => '',
    'label',
    'placeholder' => '',
    'required' => false,
    'maxlength' => 20000,
])

@php
    $safeValue = app(\App\Services\Guides\GuideContentService::class)->richText((string) $value);
    $editorId = 'rich-text-'.str_replace('-', '', (string) \Illuminate\Support\Str::uuid());
@endphp

<div {{ $attributes->merge(['class' => 'admin-rich-text-composer grid gap-1 text-sm']) }} data-admin-rich-text data-required="{{ $required ? 'true' : 'false' }}" data-maxlength="{{ $maxlength }}">
    <span class="font-bold">{{ $label }}</span>
    <div class="admin-rich-text-toolbar" role="toolbar" aria-label="{{ __('guide.editor.toolbar') }}" hidden>
        <x-admin.button type="button" variant="secondary-muted" size="icon-sm" data-rich-text-command="bold" title="{{ __('guide.editor.bold') }}" aria-label="{{ __('guide.editor.bold') }}" aria-pressed="false">
            <strong>B</strong>
        </x-admin.button>
        <x-admin.button type="button" variant="secondary-muted" size="icon-sm" data-rich-text-command="italic" title="{{ __('guide.editor.italic') }}" aria-label="{{ __('guide.editor.italic') }}" aria-pressed="false">
            <em>I</em>
        </x-admin.button>
        <x-admin.button type="button" variant="secondary-muted" size="icon-sm" data-rich-text-command="underline" title="{{ __('guide.editor.underline') }}" aria-label="{{ __('guide.editor.underline') }}" aria-pressed="false">
            <u>U</u>
        </x-admin.button>
        <x-admin.button type="button" variant="secondary-muted" size="icon-sm" data-rich-text-command="insertUnorderedList" title="{{ __('guide.editor.bullets') }}" aria-label="{{ __('guide.editor.bullets') }}" aria-pressed="false">
            <span>•</span>
        </x-admin.button>
        <x-admin.button type="button" variant="secondary-muted" size="icon-sm" data-rich-text-command="insertOrderedList" title="{{ __('guide.editor.numbers') }}" aria-label="{{ __('guide.editor.numbers') }}" aria-pressed="false">
            <span>1.</span>
        </x-admin.button>
        <x-admin.button type="button" variant="secondary-muted" size="icon-sm" data-rich-text-command="formatBlock" data-rich-text-command-value="blockquote" title="{{ __('guide.editor.blockquote') }}" aria-label="{{ __('guide.editor.blockquote') }}" aria-pressed="false">
            <span>❝</span>
        </x-admin.button>
        <x-admin.button type="button" variant="secondary-muted" size="icon-sm" icon="link" data-rich-text-link title="{{ __('guide.editor.link') }}" aria-label="{{ __('guide.editor.link') }}" aria-expanded="false" />
        <x-admin.button type="button" variant="secondary-muted" size="icon-sm" data-rich-text-command="unlink" title="{{ __('guide.editor.unlink') }}" aria-label="{{ __('guide.editor.unlink') }}">
            <span class="text-xs">×</span>
        </x-admin.button>
        <x-admin.button type="button" variant="secondary-muted" size="icon-sm" data-rich-text-command="removeFormat" title="{{ __('guide.editor.clear_format') }}" aria-label="{{ __('guide.editor.clear_format') }}">
            <span class="text-xs">Tx</span>
        </x-admin.button>
        <x-admin.button
            type="button"
            variant="secondary-muted"
            size="compact-sm"
            data-rich-text-source-toggle
            data-source-label="{{ __('guide.editor.source') }}"
            data-visual-label="{{ __('guide.editor.visual') }}"
            title="{{ __('guide.editor.source') }}"
            aria-label="{{ __('guide.editor.source') }}"
        >
            <span data-rich-text-source-toggle-label>{{ __('guide.editor.source') }}</span>
        </x-admin.button>
    </div>
    <div class="admin-rich-text-link-panel" data-rich-text-link-panel hidden>
        <label class="min-w-0 flex-1">
            <span class="sr-only">{{ __('guide.editor.link_address') }}</span>
            <input type="url" inputmode="url" dir="ltr" data-rich-text-link-input placeholder="https://example.com" aria-label="{{ __('guide.editor.link_address') }}">
        </label>
        <x-admin.button type="button" size="compact-sm" data-rich-text-link-apply>{{ __('guide.editor.apply_link') }}</x-admin.button>
        <x-admin.button type="button" variant="secondary" size="compact-sm" data-rich-text-link-cancel>{{ __('messages.cancel') }}</x-admin.button>
        <span class="w-full text-xs font-normal text-zinc-500">{{ __('guide.editor.link_hint') }}</span>
    </div>
    <div
        id="{{ $editorId }}"
        class="admin-rich-text-editor admin-rich-text-content"
        contenteditable="true"
        dir="rtl"
        spellcheck="true"
        role="textbox"
        aria-multiline="true"
        aria-label="{{ __('guide.editor.visual_label', ['label' => $label]) }}"
        data-rich-text-editor
        data-placeholder="{{ $placeholder }}"
        aria-describedby="{{ $editorId }}-hint {{ $editorId }}-error"
        hidden
    >{!! $safeValue !!}</div>
    <textarea
        name="{{ $name }}"
        class="admin-rich-text-source"
        rows="8"
        spellcheck="false"
        aria-label="{{ __('guide.editor.source_label', ['label' => $label]) }}"
        data-rich-text-source
        data-rich-text-input
        maxlength="{{ $maxlength }}"
        @required($required)
    >{{ $safeValue }}</textarea>
    <div class="flex flex-wrap items-center justify-between gap-2 text-xs font-normal leading-6 text-zinc-500">
        <span id="{{ $editorId }}-hint">{{ __('guide.rich_text_hint') }}</span>
        <span data-rich-text-counter aria-live="polite"></span>
    </div>
    <span id="{{ $editorId }}-error" class="hidden text-xs font-normal text-rose-600" data-rich-text-error>{{ __('guide.errors.empty_rich_text') }}</span>
</div>
