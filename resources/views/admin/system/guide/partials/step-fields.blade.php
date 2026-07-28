<label class="grid gap-1 text-sm font-bold">
    <span>{{ __('guide.step_title') }}</span>
    <input name="title" value="{{ old('title', $step?->title) }}" required maxlength="150" class="h-11 rounded-lg border border-zinc-200 px-3 outline-none focus:border-teal-500" placeholder="{{ __('guide.placeholders.step_title') }}">
</label>
<x-admin.rich-text-editor name="body" :value="old('body', $step?->body_html)" :label="__('guide.step_description')" :placeholder="__('guide.placeholders.step_description')" required :maxlength="20000" />
