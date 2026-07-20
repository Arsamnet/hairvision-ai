<?php

namespace Tests\Feature;

use App\Models\AiModelAccess;
use App\Models\Plan;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NumericInputPersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_grouped_persian_and_arabic_numbers_are_persisted_as_normalized_values(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::query()->where('role', 'super_admin')->firstOrFail();
        $model = AiModelAccess::query()
            ->where('usage_category_key', 'hair_transplant')
            ->where('is_demo', true)
            ->firstOrFail();

        $this->actingAs($admin)
            ->withServerVariables(['HTTP_HOST' => 'hairvision-ai.test'])
            ->post('/admin/system/plans', [
                'name' => 'پلن با ارقام چندزبانه',
                'plan_type' => 'base_subscription',
                'price' => '۴٬۵۰۰٬۰۰۰',
                'currency' => 'ریال',
                'duration_days' => '۳۰',
                'usage_limit' => '٥٠',
                'status' => 'active',
                'service_keys' => ['hair_transplant'],
                'ai_model_access_ids' => [$model->id],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $plan = Plan::query()->where('name', 'پلن با ارقام چندزبانه')->firstOrFail();

        $this->assertSame(4500000, $plan->price);
        $this->assertSame(30, $plan->duration_days);
        $this->assertSame(50, $plan->usage_limit);
    }
}
