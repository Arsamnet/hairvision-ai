<label class="grid gap-1 text-sm font-bold">
    <span>{{ __('guide.question') }}</span>
    <input name="question" value="{{ old('question', $faq?->question) }}" required maxlength="255" class="h-11 rounded-lg border border-zinc-200 px-3 outline-none focus:border-teal-500" placeholder="{{ __('guide.placeholders.question') }}">
</label>
<x-admin.rich-text-editor name="answer" :value="old('answer', $faq?->answer_html)" :label="__('guide.answer')" :placeholder="__('guide.placeholders.answer')" required :maxlength="20000" />
