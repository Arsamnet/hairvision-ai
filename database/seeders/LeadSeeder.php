<?php

namespace Database\Seeders;

use App\Models\Lead;
use App\Models\LeadNote;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Provides lead seeder behavior.
 *
 * @since 1.0.0
 * @since 2.1.0 Behavior updated for the 2.1.0 release.
 */
class LeadSeeder extends Seeder
{
    /**
     * Execute the lead seeder operation.
     *
     * @return void No value is returned.
     *
     * @since 1.0.0
     * @since 2.1.0 Behavior updated for the 2.1.0 release.
     */
    public function run(): void
    {
        $tenant = Tenant::query()->where('slug', 'arya')->firstOrFail();
        $admin = User::query()->where('phone_normalized', '09217359491')->firstOrFail();
        $editor = User::query()->where('phone_normalized', '09120000001')->firstOrFail();

        foreach ($this->demoLeads($admin, $editor) as $leadData) {
            $lead = Lead::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'phone_normalized' => $leadData['phone']],
                [
                    'full_name' => $leadData['full_name'],
                    'age' => $leadData['age'],
                    'city' => $leadData['city'],
                    'phone' => $leadData['phone'],
                    'source' => $leadData['source'],
                    'phone_verified_at' => now(),
                    'status' => $leadData['status'],
                    'crm_last_note_at' => $leadData['created_at']->copy()->addMinutes(30),
                    'assigned_user_id' => $leadData['assigned_user_id'],
                ]
            );

            $lead->forceFill([
                'created_at' => $leadData['created_at'],
                'updated_at' => $leadData['created_at']->copy()->addMinutes(30),
            ])->saveQuietly();

            $note = LeadNote::query()->firstOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'lead_id' => $lead->id,
                    'note' => $leadData['note'],
                ],
                [
                    'user_id' => $leadData['assigned_user_id'] ?? $admin->id,
                    'type' => $leadData['note_type'],
                    'status' => $leadData['status'],
                    'communication_channel' => $leadData['communication_channel'],
                    'next_follow_up_at' => $leadData['next_follow_up_at'] ?? null,
                    'completed_at' => $leadData['completed_at'] ?? null,
                ]
            );

            $note->forceFill([
                'created_at' => $leadData['created_at']->copy()->addMinutes(30),
                'updated_at' => $leadData['created_at']->copy()->addMinutes(30),
            ])->saveQuietly();
        }
    }

    /**
     * Execute the demo leads operation.
     *
     * @param  User  $admin  The admin value.
     * @param  User  $editor  The editor value.
     * @return array<int, array<string, mixed>>
     *
     * @since 1.0.0
     * @since 2.1.0 Behavior updated for the 2.1.0 release.
     */
    private function demoLeads(User $admin, User $editor): array
    {
        return [
            [
                'full_name' => 'مراجع آزمایشی',
                'phone' => '09217359491',
                'age' => 34,
                'city' => 'تهران',
                'source' => 'landing_page',
                'status' => 'needs_follow_up',
                'assigned_user_id' => $admin->id,
                'created_at' => now()->subDays(1),
                'note' => 'این لید برای پیگیری مدیر کلینیک آماده شده است.',
                'note_type' => 'follow_up',
                'communication_channel' => 'phone',
                'next_follow_up_at' => now()->addDay(),
            ],
            [
                'full_name' => 'نیما رضایی',
                'phone' => '09125550101',
                'age' => 29,
                'city' => 'کرج',
                'source' => 'instagram',
                'status' => 'new',
                'assigned_user_id' => $editor->id,
                'created_at' => now()->subHours(4),
                'note' => 'درخواست مشاوره اولیه از کمپین شبکه اجتماعی ثبت شد.',
                'note_type' => 'admin_note',
                'communication_channel' => 'sms',
            ],
            [
                'full_name' => 'سارا محمدی',
                'phone' => '09125550102',
                'age' => 38,
                'city' => 'تهران',
                'source' => 'clinic_referral',
                'status' => 'needs_follow_up',
                'assigned_user_id' => $admin->id,
                'created_at' => now()->subDays(3),
                'note' => 'زمان مشاوره حضوری برای هفته آینده هماهنگ شد.',
                'note_type' => 'follow_up',
                'communication_channel' => 'in_person',
                'next_follow_up_at' => now()->addDays(3),
            ],
            [
                'full_name' => 'کامران احمدی',
                'phone' => '09125550103',
                'age' => 42,
                'city' => 'قم',
                'source' => 'google_search',
                'status' => 'successful',
                'assigned_user_id' => $editor->id,
                'created_at' => now()->subDays(8),
                'note' => 'پس از مشاهده نمونه نتایج، برای شروع فرآیند درمان اعلام آمادگی کرد.',
                'note_type' => 'admin_note',
                'communication_channel' => 'whatsapp',
                'completed_at' => now()->subDays(7),
            ],
            [
                'full_name' => 'مهسا کریمی',
                'phone' => '09125550104',
                'age' => 31,
                'city' => 'قزوین',
                'source' => 'phone_call',
                'status' => 'canceled',
                'assigned_user_id' => null,
                'created_at' => now()->subDays(14),
                'note' => 'فعلا تمایلی به ادامه مشاوره ندارد و برای پیگیری آینده آرشیو شد.',
                'note_type' => 'admin_note',
                'communication_channel' => 'other',
                'completed_at' => now()->subDays(13),
            ],
            [
                'full_name' => 'آرش نادری',
                'phone' => '09125550105',
                'age' => 36,
                'city' => 'تهران',
                'source' => 'website_form',
                'status' => 'needs_follow_up',
                'assigned_user_id' => $editor->id,
                'created_at' => now()->subDays(2),
                'note' => 'تماس اولیه انجام شد و اطلاعات پایه برای مشاوره ارسال شد.',
                'note_type' => 'follow_up',
                'communication_channel' => 'phone',
            ],
            [
                'full_name' => 'الهام صادقی',
                'phone' => '09125550106',
                'age' => 27,
                'city' => 'رشت',
                'source' => 'instagram',
                'status' => 'no_answer',
                'assigned_user_id' => $admin->id,
                'created_at' => now()->subDays(5),
                'note' => 'برای پیگیری تماس گرفته شد اما پاسخ نداد.',
                'note_type' => 'follow_up',
                'communication_channel' => 'phone',
                'next_follow_up_at' => now()->addDays(2),
            ],
            [
                'full_name' => 'پویا شریفی',
                'phone' => '09125550107',
                'age' => 45,
                'city' => 'تهران',
                'source' => 'direct',
                'status' => 'canceled',
                'assigned_user_id' => $admin->id,
                'created_at' => now()->subDays(10),
                'note' => 'درخواست مشاوره توسط مراجعه‌کننده لغو شد.',
                'note_type' => 'admin_note',
                'communication_channel' => 'sms',
                'completed_at' => now()->subDays(9),
            ],
        ];
    }
}
