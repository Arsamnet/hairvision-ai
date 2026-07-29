<?php

namespace Tests\Feature;

use App\Models\AiModelAccess;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentGateway;
use App\Models\Plan;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class NumericInputPersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_large_grouped_monetary_values_are_persisted_across_billing_records(): void
    {
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            foreach ([
                'plans' => 'price',
                'invoices' => 'amount',
                'payments' => 'amount',
            ] as $tableName => $columnName) {
                $columnType = strtolower(Schema::getColumnType($tableName, $columnName, true));

                $this->assertStringContainsString('bigint', $columnType);
                $this->assertStringContainsString('unsigned', $columnType);
            }
        }

        $this->seed(DatabaseSeeder::class);

        $admin = User::query()->where('role', 'super_admin')->firstOrFail();
        $model = AiModelAccess::query()
            ->where('usage_category_key', 'hair_transplant')
            ->where('is_demo', true)
            ->firstOrFail();

        $this->actingAs($admin)
            ->withServerVariables(['HTTP_HOST' => 'hairvision-ai.test'])
            ->post('/admin/finance/plans', [
                'name' => 'پلن با ارقام چندزبانه',
                'plan_type' => 'base_subscription',
                'price' => '۴٬۵۰۰٬۰۰۰٬۰۰۰',
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
        $amount = 4_500_000_000;

        $this->assertSame($amount, $plan->price);
        $this->assertSame(30, $plan->duration_days);
        $this->assertSame(50, $plan->usage_limit);

        $invoice = Invoice::query()->create([
            'plan_id' => $plan->id,
            'number' => 'LARGE-AMOUNT-1',
            'status' => 'pending',
            'amount' => $amount,
            'currency' => 'ریال',
        ]);

        $payment = Payment::query()->create([
            'invoice_id' => $invoice->id,
            'payment_gateway_id' => PaymentGateway::query()->firstOrFail()->id,
            'status' => 'pending',
            'amount' => $amount,
            'currency' => 'ریال',
        ]);

        $this->assertSame($amount, $invoice->refresh()->amount);
        $this->assertSame($amount, $payment->refresh()->amount);
    }
}
