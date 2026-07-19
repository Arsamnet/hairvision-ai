<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClinicNotificationRecipient;
use App\Models\Tenant;
use App\Support\Pagination\PaginationParameters;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Clinic dashboard inbox for delivered platform notifications.
 *
 * @since 1.0.0
 */
class ClinicNotificationController extends Controller
{
    public function index(Request $request): View
    {
        $tenant = $this->tenant($request);
        abort_unless($request->user()?->hasPermission('notifications.view', $tenant->id), Response::HTTP_FORBIDDEN);

        $pagination = PaginationParameters::fromRequest(
            $request,
            ['delivered_at', 'read_at'],
            'delivered_at',
            'desc'
        );

        $query = $this->baseInboxQuery($tenant)
            ->with(['notification', 'readByUser']);

        if ($pagination->search !== null) {
            $query->whereHas('notification', function (Builder $query) use ($pagination): void {
                $query
                    ->where('title', 'like', '%'.$pagination->search.'%')
                    ->orWhere('body', 'like', '%'.$pagination->search.'%');
            });
        }

        $query
            ->when($request->input('read_state') === 'read', fn ($query) => $query->whereNotNull('read_at'))
            ->when($request->input('read_state') === 'unread', fn ($query) => $query->whereNull('read_at'))
            ->when($request->filled('type'), fn ($query) => $query->whereHas('notification', fn ($query) => $query->where('type', $request->string('type')->toString())))
            ->when($request->filled('importance'), fn ($query) => $query->whereHas('notification', fn ($query) => $query->where('importance', $request->string('importance')->toString())));

        $recipients = $query
            ->orderBy($pagination->sort, $pagination->direction)
            ->paginate($pagination->perPage, ['*'], 'page', $pagination->page)
            ->withQueryString();

        return view('admin.notifications.index', [
            'tenant' => $tenant,
            'availableTenants' => $request->attributes->get('adminTenants'),
            'recipients' => $recipients,
            'types' => __('notifications.types'),
            'importances' => __('notifications.importances'),
        ]);
    }

    public function show(Request $request, ClinicNotificationRecipient $notification): View
    {
        $tenant = $this->tenant($request);
        abort_unless($request->user()?->hasPermission('notifications.view', $tenant->id), Response::HTTP_FORBIDDEN);
        abort_unless((int) $notification->tenant_id === (int) $tenant->id, Response::HTTP_NOT_FOUND);

        $notification->load('notification', 'readByUser');
        abort_unless($notification->notification?->status === 'published', Response::HTTP_NOT_FOUND);

        $notification->markReadBy($request->user());
        $notification->refresh()->load('notification', 'readByUser');

        return view('admin.notifications.show', [
            'tenant' => $tenant,
            'availableTenants' => $request->attributes->get('adminTenants'),
            'recipient' => $notification,
        ]);
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $tenant = $this->tenant($request);
        abort_unless($request->user()?->hasPermission('notifications.view', $tenant->id), Response::HTTP_FORBIDDEN);

        $user = $request->user();

        $this->baseInboxQuery($tenant)
            ->unread()
            ->currentlyVisible()
            ->update([
                'read_by_user_id' => $user->id,
                'read_by_user_name' => $user->name,
                'read_at' => now(),
                'updated_at' => now(),
            ]);

        return back()->with('status', __('notifications.messages.all_read'));
    }

    /**
     * @return Builder<ClinicNotificationRecipient>
     */
    private function baseInboxQuery(Tenant $tenant): Builder
    {
        return ClinicNotificationRecipient::query()
            ->forTenant($tenant->id)
            ->whereHas('notification', function (Builder $query): void {
                $query
                    ->published()
                    ->where(fn (Builder $query) => $query->whereNull('visible_from')->orWhere('visible_from', '<=', now()));
            });
    }

    private function tenant(Request $request): Tenant
    {
        $tenant = $request->attributes->get('adminTenant');

        abort_unless($tenant instanceof Tenant, Response::HTTP_NOT_FOUND);

        return $tenant;
    }
}
