<label class="grid gap-1 text-sm font-bold">
    <span>{{ __('guide.section_title') }}</span>
    <input name="title" value="{{ old('title', $section?->title) }}" required maxlength="150" class="h-11 rounded-lg border border-zinc-200 px-3 outline-none focus:border-teal-500" placeholder="{{ __('guide.placeholders.section_title') }}">
</label>
<label class="grid gap-1 text-sm font-bold">
    <span>{{ __('guide.audience') }} <span class="font-normal text-zinc-400">({{ __('guide.optional') }})</span></span>
    <input name="audience" value="{{ old('audience', $section?->audience) }}" maxlength="150" class="h-11 rounded-lg border border-zinc-200 px-3 outline-none focus:border-teal-500" placeholder="{{ __('guide.placeholders.audience') }}">
</label>
<x-admin.rich-text-editor name="description" :value="old('description', $section?->description_html)" :label="__('guide.section_description')" :placeholder="__('guide.placeholders.section_description')" required :maxlength="10000" />
