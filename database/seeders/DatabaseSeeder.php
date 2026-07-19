<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
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
