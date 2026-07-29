<?php

namespace Tests\Feature;

use App\Models\Plan;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Verifies lossless persistence of large plan prices.
 *
 * @since 2.0.3
 */
class PlanPricePersistenceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Ensure PDO receives a numeric string while Eloquent exposes an integer.
     *
     * @return void No value is returned.
     *
     * @since 2.0.3
     */
    public function test_large_plan_price_is_stored_as_a_numeric_string_and_read_as_an_integer(): void
    {
        $plan = new Plan;
        $plan->fill([
            'name' => 'پلن با قیمت بزرگ',
            'price' => 4_000_000_000,
            'currency' => 'ریال',
        ]);

        $this->assertSame('4000000000', $plan->getAttributes()['price']);

        $plan->save();

        $this->assertSame(
            '4000000000',
            (string) DB::table('plans')->where('id', $plan->id)->value('price')
        );

        $plan->refresh();

        $this->assertIsInt($plan->price);
        $this->assertSame(4_000_000_000, $plan->price);
    }

    /**
     * Ensure seeded large plan prices pass through the model mutator unchanged.
     *
     * @return void No value is returned.
     *
     * @since 2.0.3
     */
    public function test_plan_seeder_preserves_large_prices(): void
    {
        $this->seed(PlanSeeder::class);

        $starterPlan = Plan::query()->where('name', 'پلن شروع')->firstOrFail();
        $hairPlan = Plan::query()->where('name', 'پلن شروع کاشت مو')->firstOrFail();

        $this->assertIsInt($starterPlan->price);
        $this->assertSame(4_000_000_000, $starterPlan->price);
        $this->assertIsInt($hairPlan->price);
        $this->assertSame(3_500_000_000, $hairPlan->price);

        $this->assertSame(
            '4000000000',
            (string) DB::table('plans')->where('id', $starterPlan->id)->value('price')
        );
        $this->assertSame(
            '3500000000',
            (string) DB::table('plans')->where('id', $hairPlan->id)->value('price')
        );
    }
}
