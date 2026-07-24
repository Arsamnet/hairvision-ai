<?php

namespace App\Services\Admin;

use App\Models\Lead;
use App\Models\LeadNote;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Handles CRM follow-up mutations for tenant-owned leads.
 *
 * @since 1.0.0
 */
class LeadCrmService
{
    /**
     * Create a standalone follow-up entry without editing the lead form.
     *
     * @param  array<string, mixed>  $data
     * @param  Lead  $lead  Lead in the current tenant scope.
     * @param  User  $user  Authenticated user performing the operation.
     * @return LeadNote Resolved operation result.
     *
     * @since 1.0.0
     */
    public function createFollowUp(Lead $lead, User $user, array $data): LeadNote
    {
        return DB::transaction(function () use ($lead, $user, $data): LeadNote {
            $note = LeadNote::create([
                'tenant_id' => $lead->tenant_id,
                'lead_id' => $lead->id,
                'user_id' => $user->id,
                'type' => 'follow_up',
                'status' => $data['status'],
                'communication_channel' => $data['communication_channel'] ?? null,
                'note' => $data['note'],
                'next_follow_up_at' => $data['next_follow_up_at'] ?? null,
                'completed_at' => ! empty($data['is_completed']) ? now() : null,
            ]);

            $lead->forceFill([
                'crm_stage' => $data['status'],
                'crm_last_note_at' => now(),
            ])->save();

            return $note;
        });
    }

    /**
     * Update a follow-up entry and keep the lead stage aligned when provided.
     *
     * @param  array<string, mixed>  $data
     * @param  Lead  $lead  Lead in the current tenant scope.
     * @param  LeadNote  $note  The note value.
     * @return LeadNote Resolved operation result.
     *
     * @since 1.0.0
     */
    public function updateFollowUp(Lead $lead, LeadNote $note, array $data): LeadNote
    {
        return DB::transaction(function () use ($lead, $note, $data): LeadNote {
            $updates = [
                'status' => $data['status'] ?? $note->status,
                'communication_channel' => $data['communication_channel'] ?? $note->communication_channel,
                'note' => $data['note'] ?? $note->note,
                'next_follow_up_at' => $data['next_follow_up_at'] ?? $note->next_follow_up_at,
            ];

            if (array_key_exists('is_completed', $data)) {
                $updates['completed_at'] = $data['is_completed'] ? ($note->completed_at ?? now()) : null;
            }

            $note->forceFill($updates)->save();

            if (isset($data['status'])) {
                $lead->forceFill([
                    'crm_stage' => $data['status'],
                    'crm_last_note_at' => now(),
                ])->save();
            }

            return $note->refresh();
        });
    }
}
