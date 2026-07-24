<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

/**
 * Provides plan seeder behavior.
 *
 * @since 1.0.0
 */
class PlanSeeder extends Seeder
{
    /**
     * Execute the plan seeder operation.
     *
     * @return void No value is returned.
     *
     * @since 1.0.0
     */
    public function run(): void
    {
        $this->seedPlan(
            'پلن شروع',
            [
                'description' => 'پلن آغازین کاشت مو و ابرو',
                'plan_type' => 'base_subscription',
                'price' => 4000000000,
                'duration_days' => 365,
                'credits' => [
                    'hair_transplant' => 600,
                    'eyebrow_transplant' => 600,
                ],
                'features_json' => $this->starterPlanFeatures(),
            ]
        );

        $this->seedPlan(
            'پلن شروع کاشت مو',
            [
                'description' => 'پلن آغازین کاشت مو',
                'plan_type' => 'base_subscription',
                'price' => 3500000000,
                'duration_days' => 365,
                'credits' => [
                    'hair_transplant' => 600,
                ],
                'features_json' => $this->starterPlanFeatures(),
            ]
        );

        $this->seedPlan(
            'افزودن کاشت ابرو',
            [
                'description' => 'سرویس افزوده کاشت ابرو برای پلن شروع کاشت مو',
                'plan_type' => 'service_add_on',
                'price' => 500000000,
                'duration_days' => null,
                'credits' => [
                    'eyebrow_transplant' => 600,
                ],
            ]
        );

        $this->seedPlan(
            'بسته افزایش اعتبار کاشت مو',
            [
                'description' => 'بسته ۶۰۰ اعتبار افزوده کاشت مو',
                'plan_type' => 'credit_top_up',
                'price' => 500000000,
                'duration_days' => null,
                'credits' => [
                    'hair_transplant' => 600,
                ],
            ]
        );

        $this->seedPlan(
            'بسته افزایش اعتبار کاشت ابرو',
            [
                'description' => 'بسته ۶۰۰ اعتبار افزوده کاشت ابرو',
                'plan_type' => 'credit_top_up',
                'price' => 500000000,
                'duration_days' => null,
                'credits' => [
                    'eyebrow_transplant' => 600,
                ],
            ]
        );

        $this->seedPlan(
            'بسته افزایش اعتبار کاشت مو و ابرو',
            [
                'description' => 'بسته ۶۰۰ اعتبار افزوده کاشت مو و ۶۰۰ اعتبار افزوده کاشت ابرو',
                'plan_type' => 'credit_top_up',
                'price' => 1000000000,
                'duration_days' => null,
                'credits' => [
                    'hair_transplant' => 600,
                    'eyebrow_transplant' => 600,
                ],
            ]
        );
    }

    /**
     * Execute the starter plan features operation.
     *
     * @return array<string, bool>
     *
     * @since 1.0.0
     */
    private function starterPlanFeatures(): array
    {
        return [
            'ai_generation' => true,
            'ai_models' => true,
            'billing' => true,
            'crm' => true,
            'leads' => true,
            'reports' => true,
            'settings' => true,
            'sms' => true,
            'team' => true,
            'uploads' => true,
        ];
    }

    /**
     * Execute the seed plan operation.
     *
     * @param  array{description: string, plan_type: string, price: int, duration_days: int|null, credits: array<string, int>, features_json?: array<string, bool>}  $data
     * @param  string  $name  The name value.
     * @return void No value is returned.
     *
     * @since 1.0.0
     */
    private function seedPlan(string $name, array $data): void
    {
        $credits = $data['credits'];

        $plan = Plan::query()->updateOrCreate(
            ['name' => $name],
            [
                'description' => $data['description'],
                'plan_type' => $data['plan_type'],
                'service_key' => count($credits) === 1 ? array_key_first($credits) : null,
                'price' => $data['price'],
                'currency' => 'ریال',
                'duration_days' => $data['duration_days'],
                'usage_limit' => array_sum($credits),
                'features_json' => $data['features_json'] ?? [],
                'is_public' => $data['is_public'] ?? true,
                'status' => 'active',
            ]
        );

        foreach ($credits as $serviceKey => $credit) {
            $plan->serviceEntitlements()->updateOrCreate(
                ['service_key' => $serviceKey],
                [
                    'credit_amount' => $credit,
                    'is_active' => true,
                ]
            );
        }

        $plan->serviceEntitlements()
            ->whereNotIn('service_key', array_keys($credits))
            ->update(['is_active' => false]);
    }
}
