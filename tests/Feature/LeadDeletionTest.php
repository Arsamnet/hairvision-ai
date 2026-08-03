<?php

namespace Tests\Feature;

use App\Jobs\ProcessGenerationAttempt;
use App\Jobs\ProcessGenerationJob;
use App\Models\AiModelCategory;
use App\Models\GeneratedAsset;
use App\Models\GenerationRequest;
use App\Models\Lead;
use App\Models\LeadProfile;
use App\Models\LeadSourceImage;
use App\Models\Permission;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\Upload;
use App\Models\User;
use App\Services\Auth\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Verifies permission-backed lead deletion in clinic and system dashboards.
 *
 * @since 2.1.0
 */
class LeadDeletionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Ensure queued transports are discarded after terminal lead data is deleted.
     *
     * @return void No value is returned.
     *
     * @since 2.1.0
     */
    public function test_generation_jobs_discard_transports_with_deleted_models(): void
    {
        $this->assertTrue((new \ReflectionClass(ProcessGenerationJob::class))
            ->getDefaultProperties()['deleteWhenMissingModels']);
        $this->assertTrue((new \ReflectionClass(ProcessGenerationAttempt::class))
            ->getDefaultProperties()['deleteWhenMissingModels']);
    }

    /**
     * Ensure an authorized clinic administrator deletes lead data and files.
     *
     * @return void No value is returned.
     *
     * @since 2.1.0
     */
    public function test_clinic_administrator_can_delete_tenant_lead_and_private_files(): void
    {
        Storage::fake('local');

        $tenant = $this->createTenant('Clinic One', 'clinic-one');
        $user = $this->createClinicUser($tenant, 'clinic_admin', '09121111111');
        $this->activateSubscription($tenant, $user);
        $lead = $this->createLead($tenant, '09120000001');

        $uploadPath = 'uploads/'.$lead->uuid.'/source.jpg';
        $sourcePath = 'tenants/'.$tenant->uuid.'/leads/'.$lead->uuid.'/source-images/current.jpg';
        $assetPath = 'tenants/'.$tenant->uuid.'/leads/'.$lead->uuid.'/results/completed.jpg';
        $residualPath = 'tenants/'.$tenant->uuid.'/leads/'.$lead->uuid.'/temporary/residual.jpg';

        foreach ([$uploadPath, $sourcePath, $assetPath, $residualPath] as $path) {
            Storage::disk('local')->put($path, 'private-image');
        }

        $upload = Upload::query()->create([
            'tenant_id' => $tenant->id,
            'lead_id' => $lead->id,
            'original_disk' => 'local',
            'original_path' => $uploadPath,
            'mime' => 'image/jpeg',
            'size' => 13,
            'status' => 'validated',
        ]);
        $profile = LeadProfile::query()->create([
            'tenant_id' => $tenant->id,
            'lead_id' => $lead->id,
        ]);
        $sourceImage = LeadSourceImage::query()->create([
            'tenant_id' => $tenant->id,
            'lead_id' => $lead->id,
            'lead_profile_id' => $profile->id,
            'upload_id' => $upload->id,
            'version' => 1,
            'is_current' => true,
            'disk' => 'local',
            'path' => $sourcePath,
            'mime' => 'image/jpeg',
            'size' => 13,
            'status' => 'validated',
        ]);
        $asset = GeneratedAsset::query()->create([
            'tenant_id' => $tenant->id,
            'lead_id' => $lead->id,
            'lead_source_image_id' => $sourceImage->id,
            'period' => 'final_result',
            'version' => 1,
            'is_current' => true,
            'disk' => 'local',
            'path' => $assetPath,
            'mime' => 'image/jpeg',
            'size' => 13,
        ]);

        $this->assertTrue($user->hasPermission('leads.delete', $tenant->id));

        $this->actingAs($user)
            ->withSession(['admin_active_tenant_id' => $tenant->id])
            ->forHost('hairvision-ai.test')
            ->get(route('admin.leads.index', absolute: false))
            ->assertOk()
            ->assertSee('action="'.route('admin.leads.destroy', $lead).'"', false)
            ->assertSee(__('leads.confirm_delete'));

        $this->actingAs($user)
            ->withSession(['admin_active_tenant_id' => $tenant->id])
            ->forHost('hairvision-ai.test')
            ->get(route('admin.leads.show', $lead, false))
            ->assertOk()
            ->assertSee('action="'.route('admin.leads.destroy', $lead).'"', false);

        $this->actingAs($user)
            ->withSession(['admin_active_tenant_id' => $tenant->id])
            ->forHost('hairvision-ai.test')
            ->delete(route('admin.leads.destroy', $lead, false))
            ->assertRedirect(route('admin.leads.index'))
            ->assertSessionHas('status', __('leads.deleted'));

        $this->assertDatabaseMissing('leads', ['id' => $lead->id]);
        $this->assertDatabaseMissing('uploads', ['id' => $upload->id]);
        $this->assertDatabaseMissing('lead_profiles', ['id' => $profile->id]);
        $this->assertDatabaseMissing('lead_source_images', ['id' => $sourceImage->id]);
        $this->assertDatabaseMissing('generated_assets', ['id' => $asset->id]);

        foreach ([$uploadPath, $sourcePath, $assetPath, $residualPath] as $path) {
            Storage::disk('local')->assertMissing($path);
        }
    }

    /**
     * Ensure a system-scoped role with lead permissions can delete any lead.
     *
     * @return void No value is returned.
     *
     * @since 2.1.0
     */
    public function test_authorized_system_user_can_delete_lead_without_tenant_selection(): void
    {
        $permissions = collect([
            'system.dashboard',
            'leads.list',
            'leads.view',
            'leads.delete',
        ])->map(fn (string $code): Permission => Permission::query()->create([
            'code' => $code,
            'name' => $code,
            'group' => str_starts_with($code, 'leads.') ? 'leads' : 'system',
        ]));

        $role = Role::query()->create([
            'code' => 'lead_manager',
            'scope' => 'platform',
            'name' => 'مدیر لیدها',
            'is_system' => false,
            'status' => 'active',
        ]);
        $role->permissions()->sync($permissions->pluck('id'));
        app(PermissionService::class)->forgetRole($role->code);

        $user = User::factory()->create([
            'access_scope' => 'system',
            'role' => $role->code,
            'status' => 'active',
        ]);
        $tenant = $this->createTenant('Clinic One', 'clinic-one');
        $lead = $this->createLead($tenant, '09120000002');

        $this->actingAs($user)
            ->withSession(['admin_all_tenants' => true])
            ->forHost('hairvision-ai.test')
            ->get(route('admin.leads.index', absolute: false))
            ->assertOk()
            ->assertSee($lead->phone_normalized)
            ->assertSee('action="'.route('admin.leads.destroy', $lead).'"', false);

        $this->actingAs($user)
            ->withSession(['admin_all_tenants' => true])
            ->forHost('hairvision-ai.test')
            ->delete(route('admin.leads.destroy', $lead, false))
            ->assertRedirect(route('admin.leads.index'));

        $this->assertDatabaseMissing('leads', ['id' => $lead->id]);
    }

    /**
     * Ensure users without permission and users from another clinic are denied.
     *
     * @return void No value is returned.
     *
     * @since 2.1.0
     */
    public function test_unauthorized_and_cross_tenant_users_cannot_delete_leads(): void
    {
        $tenantA = $this->createTenant('Clinic One', 'clinic-one');
        $tenantB = $this->createTenant('Clinic Two', 'clinic-two');
        $editor = $this->createClinicUser($tenantA, 'editor', '09121111112');
        $clinicAdmin = $this->createClinicUser($tenantA, 'clinic_admin', '09121111113');
        $this->activateSubscription($tenantA, $editor);
        $leadA = $this->createLead($tenantA, '09120000003');
        $leadB = $this->createLead($tenantB, '09120000004');

        $this->actingAs($editor)
            ->withSession(['admin_active_tenant_id' => $tenantA->id])
            ->forHost('hairvision-ai.test')
            ->get(route('admin.leads.index', absolute: false))
            ->assertOk()
            ->assertDontSee('action="'.route('admin.leads.destroy', $leadA).'"', false);

        $this->actingAs($editor)
            ->withSession(['admin_active_tenant_id' => $tenantA->id])
            ->forHost('hairvision-ai.test')
            ->delete(route('admin.leads.destroy', $leadA, false))
            ->assertForbidden();

        $this->actingAs($clinicAdmin)
            ->withSession(['admin_active_tenant_id' => $tenantA->id])
            ->forHost('hairvision-ai.test')
            ->delete(route('admin.leads.destroy', $leadB, false))
            ->assertForbidden();

        $this->assertDatabaseHas('leads', ['id' => $leadA->id]);
        $this->assertDatabaseHas('leads', ['id' => $leadB->id]);
    }

    /**
     * Ensure deletion is blocked while independent generation remains active.
     *
     * @return void No value is returned.
     *
     * @since 2.1.0
     */
    public function test_lead_with_active_generation_cannot_be_deleted(): void
    {
        $tenant = $this->createTenant('Clinic One', 'clinic-one');
        $user = $this->createClinicUser($tenant, 'clinic_admin', '09121111114');
        $this->activateSubscription($tenant, $user);
        $lead = $this->createLead($tenant, '09120000005');
        $profile = LeadProfile::query()->create([
            'tenant_id' => $tenant->id,
            'lead_id' => $lead->id,
        ]);
        $sourceImage = LeadSourceImage::query()->create([
            'tenant_id' => $tenant->id,
            'lead_id' => $lead->id,
            'lead_profile_id' => $profile->id,
            'version' => 1,
            'is_current' => true,
            'disk' => 'local',
            'path' => 'tenants/'.$tenant->uuid.'/leads/'.$lead->uuid.'/source-images/current.jpg',
            'mime' => 'image/jpeg',
            'size' => 13,
            'status' => 'validated',
        ]);
        $category = AiModelCategory::query()->create([
            'code' => 'deletion_test',
            'name' => 'آزمون حذف',
            'status' => 'active',
            'sort_order' => 1,
        ]);
        $output = $category->outputs()->create([
            'code' => 'result',
            'name' => 'نتیجه',
            'prompt' => 'Safe test prompt',
            'sort_order' => 1,
            'credit_cost' => 1,
            'is_active' => true,
        ]);
        GenerationRequest::query()->create([
            'tenant_id' => $tenant->id,
            'lead_id' => $lead->id,
            'lead_source_image_id' => $sourceImage->id,
            'ai_model_category_id' => $category->id,
            'ai_model_category_output_id' => $output->id,
            'status' => 'queued',
            'idempotency_key' => 'lead-deletion-active-generation',
            'requested_at' => now(),
        ]);

        $showRoute = route('admin.leads.show', $lead, false);

        $this->actingAs($user)
            ->withSession(['admin_active_tenant_id' => $tenant->id])
            ->forHost('hairvision-ai.test')
            ->from($showRoute)
            ->delete(route('admin.leads.destroy', $lead, false))
            ->assertRedirect($showRoute)
            ->assertSessionHasErrors([
                'lead' => __('leads.delete_blocked_active_generation'),
            ]);

        $this->assertDatabaseHas('leads', ['id' => $lead->id]);
    }

    /**
     * Create an active clinic tenant.
     *
     * @param  string  $name  Clinic name.
     * @param  string  $slug  Clinic slug.
     * @return Tenant Created tenant.
     *
     * @since 2.1.0
     */
    private function createTenant(string $name, string $slug): Tenant
    {
        return Tenant::query()->create([
            'name' => $name,
            'slug' => $slug,
            'status' => 'active',
        ]);
    }

    /**
     * Create a dashboard user assigned to one clinic.
     *
     * @param  Tenant  $tenant  Assigned clinic.
     * @param  string  $role  Clinic role code.
     * @param  string  $phone  Normalized phone number.
     * @return User Created user.
     *
     * @since 2.1.0
     */
    private function createClinicUser(Tenant $tenant, string $role, string $phone): User
    {
        $user = User::factory()->create([
            'access_scope' => 'clinic',
            'phone_normalized' => $phone,
            'role' => $role,
            'status' => 'active',
        ]);

        $tenant->users()->attach($user->id, [
            'role' => $role,
            'status' => 'active',
        ]);

        return $user;
    }

    /**
     * Create a verified lead in one tenant.
     *
     * @param  Tenant  $tenant  Owning clinic.
     * @param  string  $phone  Normalized phone number.
     * @return Lead Created lead.
     *
     * @since 2.1.0
     */
    private function createLead(Tenant $tenant, string $phone): Lead
    {
        return Lead::query()->create([
            'tenant_id' => $tenant->id,
            'full_name' => 'لید تست',
            'phone' => $phone,
            'phone_normalized' => $phone,
            'source' => 'landing_page',
            'phone_verified_at' => now(),
            'status' => 'new',
        ]);
    }

    /**
     * Create an active base subscription for clinic dashboard access.
     *
     * @param  Tenant  $tenant  Owning clinic.
     * @param  User  $user  Subscribed dashboard user.
     * @return Subscription Created subscription.
     *
     * @since 2.1.0
     */
    private function activateSubscription(Tenant $tenant, User $user): Subscription
    {
        $plan = Plan::query()->create([
            'name' => 'پلن تست حذف لید '.$user->id,
            'description' => 'پلن تست',
            'plan_type' => 'base_subscription',
            'price' => 1000,
            'currency' => 'IRR',
            'duration_days' => 30,
            'usage_limit' => 100,
            'status' => 'active',
        ]);

        return Subscription::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => now()->addDays(30),
            'usage_limit' => 100,
            'usage_used' => 0,
        ]);
    }

    /**
     * Configure the request host used by administrator routes.
     *
     * @param  string  $host  HTTP host.
     * @return self Current test case.
     *
     * @since 2.1.0
     */
    private function forHost(string $host): self
    {
        $this->app['url']->forceRootUrl('http://'.$host);

        return $this->withServerVariables([
            'HTTP_HOST' => $host,
            'SERVER_NAME' => $host,
        ]);
    }
}
