@extends('admin.layout')

@section('title', 'کاربران')
@section('page_title', 'کاربران')

@section('content')
    <section class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-zinc-200">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-lg font-bold">کاربران پنل</h2>
                <p class="mt-1 text-sm text-zinc-500">{{ $tenant?->name ?? 'نمای کل پلتفرم' }}</p>
            </div>
        </div>

        <div class="mt-5 overflow-x-auto">
            <table class="w-full min-w-[42rem] text-right text-sm">
                <thead class="border-b border-zinc-200 text-xs text-zinc-500">
                    <tr>
                        <th class="py-3 font-bold">نام</th>
                        <th class="py-3 font-bold">موبایل</th>
                        <th class="py-3 font-bold">نقش</th>
                        <th class="py-3 font-bold">وضعیت</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100">
                    @forelse($users as $dashboardUser)
                        <tr>
                            <td class="py-3 font-bold">{{ $dashboardUser->name }}</td>
                            <td class="py-3 text-zinc-600">{{ $dashboardUser->phone_normalized ?? '-' }}</td>
                            <td class="py-3 text-zinc-600">{{ $dashboardUser->pivot?->role ?? $dashboardUser->role }}</td>
                            <td class="py-3 text-zinc-600">{{ $dashboardUser->pivot?->status ?? $dashboardUser->status }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="py-10 text-center text-zinc-500">کاربری برای نمایش وجود ندارد.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-5">{{ $users->links() }}</div>
    </section>
@endsection
