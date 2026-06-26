@extends('admin.layout')

@section('title', 'کاربران')
@section('page_title', 'کاربران')

@section('content')
    <section class="admin-panel">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-lg font-bold">کاربران پنل</h2>
                <p class="mt-1 text-sm text-zinc-500">{{ $tenant?->name ?? 'نمای کل پلتفرم' }}</p>
            </div>
        </div>

        @if(auth()->user()?->isSuperAdmin())
            <form method="GET" class="admin-filter-panel mt-5 grid gap-3 sm:grid-cols-[minmax(14rem,22rem)_8rem]">
                @include('admin.partials.tenant-filter-select')
                <button class="admin-button admin-button-primary h-11 self-end">اعمال</button>
            </form>
        @endif

        <div class="admin-table-shell mt-5">
            <div class="admin-table-scroll">
            <table data-admin-datatable class="admin-table min-w-[42rem] text-sm">
                <thead>
                    <tr>
                        <th class="py-3 font-bold">نام</th>
                        <th class="py-3 font-bold">موبایل</th>
                        <th class="py-3 font-bold">نقش</th>
                        <th class="py-3 font-bold">وضعیت</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100">
                    @foreach($users as $dashboardUser)
                        <tr>
                            <td class="py-3 font-bold">{{ $dashboardUser->name }}</td>
                            <td class="py-3 text-zinc-600">{{ \App\Support\AdminDashboardFormat::phone($dashboardUser->phone_normalized) }}</td>
                            <td class="py-3 text-zinc-600">{{ $dashboardUser->pivot?->role ?? $dashboardUser->role }}</td>
                            <td class="py-3 text-zinc-600">{{ $dashboardUser->pivot?->status ?? $dashboardUser->status }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            </div>
        </div>
    </section>
@endsection
