<div class="grid gap-4 sm:grid-cols-2" data-sms-provider-form>
    <label class="space-y-2">
        <span class="text-sm font-bold text-zinc-700">نوع سرویس</span>
        <select name="sms_provider_type_id" data-sms-provider-type class="admin-select" @disabled($provider)>
            @foreach($providerTypes as $type)
                <option value="{{ $type->id }}" data-provider-key="{{ $type->key }}" @selected(old('sms_provider_type_id', $provider?->sms_provider_type_id) == $type->id)>{{ $type->name }}</option>
            @endforeach
        </select>
    </label>
    <label class="space-y-2">
        <span class="text-sm font-bold text-zinc-700">سطح دسترسی</span>
        <select name="scope" class="admin-select" @disabled($provider)>
            @foreach($scopes as $value => $label)
                <option value="{{ $value }}" @selected(old('scope', $provider?->scope->value ?? 'user') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </label>
    <label class="space-y-2 sm:col-span-2">
        <span class="text-sm font-bold text-zinc-700">نام نمایشی</span>
        <input name="name" value="{{ old('name', $provider?->name) }}" class="admin-input">
    </label>
    <label class="space-y-2" data-provider-keys="kavenegar ippanel sms_ir melli_payamak">
        <span class="text-sm font-bold text-zinc-700">API Key / Access Token</span>
        <input name="credentials[api_key]" value="" autocomplete="new-password" class="admin-input" placeholder="{{ $provider ? 'برای تغییر، مقدار جدید وارد کنید' : '' }}">
    </label>
    <label class="space-y-2">
        <span class="text-sm font-bold text-zinc-700">شماره ارسال‌کننده</span>
        <input name="sender_number" value="{{ old('sender_number', $provider?->sender_number) }}" class="admin-input">
    </label>
    <label class="space-y-2" data-provider-keys="melli_payamak">
        <span class="text-sm font-bold text-zinc-700">نام کاربری ملی پیامک</span>
        <input name="credentials[username]" value="" autocomplete="new-password" class="admin-input">
    </label>
    <label class="space-y-2" data-provider-keys="melli_payamak">
        <span class="text-sm font-bold text-zinc-700">رمز عبور ملی پیامک</span>
        <input name="credentials[password]" type="password" value="" autocomplete="new-password" class="admin-input">
    </label>
    <label class="space-y-2">
        <span class="text-sm font-bold text-zinc-700">اولویت</span>
        <input name="priority" type="number" min="0" value="{{ old('priority', $provider?->priority) }}" class="admin-input">
    </label>
    <div class="flex items-center gap-4 pt-7">
        <label class="flex items-center gap-2 text-sm font-bold text-zinc-700">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $provider?->is_active ?? false)) class="admin-checkbox">
            فعال
        </label>
        <label class="flex items-center gap-2 text-sm font-bold text-zinc-700">
            <input type="checkbox" name="is_default" value="1" @checked(old('is_default', $provider?->is_default ?? false)) class="admin-checkbox">
            پیش‌فرض
        </label>
    </div>
</div>
