<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Provides user seeder behavior.
 *
 * @since 1.0.0
 */
class UserSeeder extends Seeder
{
    /**
     * Execute the user seeder operation.
     *
     * @return void No value is returned.
     *
     * @since 1.0.0
     */
    public function run(): void
    {
        foreach ($this->users() as $user) {
            $this->upsertUser($user['name'], $user['email'], $user['phone'], $user['role']);
        }
    }

    /**
     * Execute the users operation.
     *
     * @return array<int, array{name: string, email: string, phone: string, role: string}>
     *
     * @since 1.0.0
     */
    private function users(): array
    {
        return [
            [
                'name' => config('hairvision.super_admin.name'),
                'email' => config('hairvision.super_admin.email'),
                'phone' => config('hairvision.super_admin.phone'),
                'role' => 'super_admin',
            ],
            [
                'name' => 'مدیر کلینیک آریا',
                'email' => 'test@example.com',
                'phone' => '09217359491',
                'role' => 'clinic_admin',
            ],
            [
                'name' => 'کارشناس پذیرش آریا',
                'email' => 'editor@example.com',
                'phone' => '09120000001',
                'role' => 'editor',
            ],
            [
                'name' => 'گزارش‌گیر آریا',
                'email' => 'viewer@example.com',
                'phone' => '09120000002',
                'role' => 'viewer',
            ],
            [
                'name' => 'مهسا فرهمند',
                'email' => 'mahsa.demo@example.com',
                'phone' => '09004666673',
                'role' => 'clinic_admin',
            ],
            [
                'name' => 'کامران فضلی',
                'email' => 'kamran.fazli.demo@example.com',
                'phone' => '09127072327',
                'role' => 'clinic_admin',
            ],
        ];
    }

    /**
     * Execute the upsert user operation.
     *
     * @param  string  $name  The name value.
     * @param  string  $email  The email value.
     * @param  string  $phone  The phone value.
     * @param  string  $role  The role value.
     * @return User Resolved operation result.
     *
     * @since 1.0.0
     */
    private function upsertUser(string $name, string $email, string $phone, string $role): User
    {
        $user = User::query()
            ->where('phone_normalized', $phone)
            ->orWhere('email', $email)
            ->when($role === 'super_admin', fn ($query) => $query->orWhere('role', 'super_admin'))
            ->first();

        if (! $user) {
            $user = new User([
                'password' => Hash::make(Str::random(40)),
            ]);
        }

        $user->forceFill([
            'name' => $name,
            'email' => $email,
            'phone_normalized' => $phone,
            'access_scope' => $role === 'super_admin' ? 'system' : 'clinic',
            'role' => $role,
            'status' => 'active',
        ])->save();

        return $user;
    }
}
