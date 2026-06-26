<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SmsProviderScope;
use App\Http\Controllers\Controller;
use App\Models\SmsDeliveryLog;
use App\Models\SmsProviderAccount;
use App\Models\User;
use App\Support\JalaliDate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * Displays SMS send logs and aggregate statistics.
 *
 * @since 1.2.0
 */
class SmsDeliveryLogController extends Controller
{
    public function index(Request $request): View
    {
        $query = SmsDeliveryLog::query()->with(['user', 'providerAccount.type']);

        if (! $request->user()->isSuperAdmin()) {
            $query->where('user_id', $request->user()->id);
        }

        $from = $this->dateFilter($request->input('from'));
        $to = $this->dateFilter($request->input('to'));

        $query->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->when($request->filled('provider'), fn ($query) => $query->where('sms_provider_account_id', $request->integer('provider')))
            ->when($request->user()->isSuperAdmin() && $request->filled('user_id'), fn ($query) => $query->where('user_id', $request->integer('user_id')))
            ->when($from, fn ($query) => $query->whereDate('created_at', '>=', $from))
            ->when($to, fn ($query) => $query->whereDate('created_at', '<=', $to));

        return view('admin.sms.logs.index', [
            'tenant' => $request->attributes->get('adminTenant'),
            'availableTenants' => $request->attributes->get('adminTenants'),
            'logs' => $query->latest()->get(),
            'providers' => $this->visibleProviderQuery($request)->orderBy('name')->get(),
            'users' => $request->user()->isSuperAdmin() ? User::query()->orderBy('name')->get() : collect(),
        ]);
    }

    public function show(Request $request, SmsDeliveryLog $log): View
    {
        abort_unless($request->user()->isSuperAdmin() || $log->user_id === $request->user()->id, 404);

        return view('admin.sms.logs.show', [
            'tenant' => $request->attributes->get('adminTenant'),
            'availableTenants' => $request->attributes->get('adminTenants'),
            'log' => $log->load(['user', 'providerAccount.type', 'providerType']),
        ]);
    }

    public function statistics(Request $request): View
    {
        $base = SmsDeliveryLog::query();

        if (! $request->user()->isSuperAdmin()) {
            $base->where('user_id', $request->user()->id);
        }

        $total = (clone $base)->count();
        $sent = (clone $base)->where('status', 'sent')->count();
        $failed = (clone $base)->where('status', 'failed')->count();

        return view('admin.sms.statistics.index', [
            'tenant' => $request->attributes->get('adminTenant'),
            'availableTenants' => $request->attributes->get('adminTenants'),
            'total' => $total,
            'sent' => $sent,
            'failed' => $failed,
            'successRate' => $total > 0 ? round(($sent / $total) * 100, 1) : 0,
            'byProvider' => (clone $base)
                ->selectRaw('sms_provider_account_id, count(*) as total')
                ->with('providerAccount')
                ->groupBy('sms_provider_account_id')
                ->orderByDesc('total')
                ->limit(10)
                ->get(),
            'byUser' => $request->user()->isSuperAdmin()
                ? (clone $base)->selectRaw('user_id, count(*) as total')->with('user')->groupBy('user_id')->orderByDesc('total')->limit(10)->get()
                : collect(),
        ]);
    }

    private function dateFilter(mixed $value): ?string
    {
        try {
            return JalaliDate::toGregorianDate(is_string($value) ? $value : null);
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    /**
     * Scope provider filter options to accounts visible to the current user.
     */
    private function visibleProviderQuery(Request $request): Builder
    {
        $query = SmsProviderAccount::query();

        if (! $request->user()->isSuperAdmin()) {
            $query->where(function ($query) use ($request): void {
                $query->where('scope', SmsProviderScope::System->value)
                    ->orWhere('owner_user_id', $request->user()->id);
            });
        }

        return $query;
    }
}
