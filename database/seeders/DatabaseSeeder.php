<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Provides database seeder behavior.
 *
 * @since 1.0.0
 */
class DatabaseSeeder extends Seeder
{
    /**
     * Execute the database seeder operation.
     *
     * @return void No value is returned.
     *
     * @since 1.0.0
     */
    public function run(): void
    {
        $this->call([
            AccessControlSeeder::class,
            SystemSettingSeeder::class,
            SmsGatewaySeeder::class,
            PaymentGatewaySeeder::class,
            PlanSeeder::class,
            UserSeeder::class,
            TenantSeeder::class,
            AiProviderSeeder::class,
            AiModelAccessSeeder::class,
            TemplateSeeder::class,
            TenantThemeSettingSeeder::class,
            TenantUserSeeder::class,
            SubscriptionSeeder::class,
            LeadSeeder::class,
        ]);
    }
}
