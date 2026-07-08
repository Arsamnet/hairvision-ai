<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $starterPlanCredits = $this->starterPlanServiceCredits();

        $starterPlan = Plan::query()->updateOrCreate(
            ['name' => 'پلن شروع'],
            [
                'description' => 'پلن فعال کلینیک آریا برای استفاده کامل از امکانات HairVision 1.0.0.',
                'plan_type' => 'base_subscription',
                'service_key' => count($starterPlanCredits) === 1 ? array_key_first($starterPlanCredits) : null,
                'price' => 4000000000,
                'currency' => 'IRR',
                'duration_days' => 30,
                'usage_limit' => array_sum($starterPlanCredits),
                'features_json' => $this->starterPlanFeatures(),
                'status' => 'active',
            ]
        );

        foreach ($starterPlanCredits as $serviceKey => $credit) {
            $starterPlan->serviceEntitlements()->updateOrCreate(
                ['service_key' => $serviceKey],
                [
                    'credit_amount' => $credit,
                    'is_active' => true,
                ]
            );
        }

        $starterPlan->serviceEntitlements()
            ->whereNotIn('service_key', array_keys($starterPlanCredits))
            ->update(['is_active' => false]);
    }

    /**
     * @return array<string, int>
     */
    private function starterPlanServiceCredits(): array
    {
        $serviceKeys = array_keys(config('hairvision.ai_services', [])) ?: ['hair_transplant'];

        return collect($serviceKeys)
            ->mapWithKeys(fn (string $serviceKey): array => [$serviceKey => 100])
            ->all();
    }

    /**
     * @return array<string, bool>
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
}
