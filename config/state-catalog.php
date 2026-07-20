<?php

return [
    'tenants' => [
        'statuses' => [
            'active' => 'system.statuses.active',
            'inactive' => 'system.statuses.inactive',
        ],
    ],

    'tenant_domains' => [
        'types' => [
            'tenant_subdomain' => 'clinics.domain_types.tenant_subdomain',
            'custom_domain' => 'clinics.domain_types.custom_domain',
            'primary_domain' => 'clinics.domain_types.primary_domain',
            'platform_subdomain' => 'clinics.domain_types.platform_subdomain',
        ],
        'statuses' => [
            'active' => 'clinics.statuses.active',
            'inactive' => 'clinics.statuses.inactive',
            'pending' => 'clinics.statuses.pending',
        ],
    ],

    'users' => [
        'access_scopes' => [
            'system' => 'system.user_access_scopes.system',
            'clinic' => 'system.user_access_scopes.clinic',
        ],
        'statuses' => [
            'active' => 'system.statuses.active',
            'inactive' => 'system.statuses.inactive',
        ],
    ],

    'roles' => [
        'scopes' => [
            'platform' => 'system.role_scopes.platform',
            'clinic' => 'system.role_scopes.clinic',
        ],
        'statuses' => [
            'active' => 'system.statuses.active',
            'inactive' => 'system.statuses.inactive',
        ],
    ],

    'leads' => [
        'statuses' => [
            'new' => 'leads.statuses.new',
            'verified' => 'leads.statuses.verified',
            'contacted' => 'leads.statuses.contacted',
            'qualified' => 'leads.statuses.qualified',
            'archived' => 'leads.statuses.archived',
        ],
        'crm_stages' => [
            'new_lead' => 'leads.crm_stages.new_lead',
            'contacted' => 'leads.crm_stages.contacted',
            'no_answer' => 'leads.crm_stages.no_answer',
            'needs_follow_up' => 'leads.crm_stages.needs_follow_up',
            'consultation_booked' => 'leads.crm_stages.consultation_booked',
            'converted' => 'leads.crm_stages.converted',
            'lost' => 'leads.crm_stages.lost',
            'canceled' => 'leads.crm_stages.canceled',
        ],
    ],

    'lead_notes' => [
        'types' => [
            'admin_note' => 'crm.note_types.admin_note',
            'follow_up' => 'crm.note_types.follow_up',
        ],
        'communication_channels' => [
            'phone' => 'crm.channels.phone',
            'sms' => 'crm.channels.sms',
            'whatsapp' => 'crm.channels.whatsapp',
            'in_person' => 'crm.channels.in_person',
            'other' => 'crm.channels.other',
        ],
    ],

    'uploads' => [
        'statuses' => [
            'uploaded' => 'leads.upload_statuses.uploaded',
            'validated' => 'leads.upload_statuses.validated',
            'rejected' => 'leads.upload_statuses.rejected',
        ],
    ],

    'generation' => [
        'statuses' => [
            'pending' => 'ai.generation.statuses.pending',
            'queued' => 'ai.generation.statuses.queued',
            'dispatched' => 'ai.generation.statuses.dispatched',
            'processing' => 'ai.generation.statuses.processing',
            'completed' => 'ai.generation.statuses.completed',
            'failed' => 'ai.generation.statuses.failed',
            'cancelled' => 'ai.generation.statuses.cancelled',
        ],
        'periods' => [
            'two_weeks' => 'leads.periods.two_weeks',
            'intermediate_regrowth' => 'leads.periods.intermediate_regrowth',
            'final_result' => 'leads.periods.final_result',
            '3_months' => 'leads.periods.3_months',
            '6_months' => 'leads.periods.6_months',
            '12_months' => 'leads.periods.12_months',
        ],
    ],

    'ai_providers' => [
        'types' => [
            'local' => 'ai_models.provider_types.local',
            'external' => 'ai_models.provider_types.external',
        ],
        'auth_types' => [
            'bearer' => 'ai_models.auth_types.bearer',
        ],
    ],

    'ai_model_accesses' => [
        'statuses' => [
            'active' => 'ai_models.statuses.active',
            'inactive' => 'ai_models.statuses.inactive',
        ],
    ],

    'ai_model_usage_logs' => [
        'statuses' => [
            'started' => 'ai_models.statuses.started',
            'completed' => 'ai_models.statuses.completed',
            'failed' => 'ai_models.statuses.failed',
        ],
    ],

    'sms_gateways' => [
        'owner_scopes' => [
            'system' => 'system.owners.system',
            'tenant' => 'system.owners.tenant',
        ],
        'statuses' => [
            'enabled' => 'system.statuses.enabled',
            'disabled' => 'system.statuses.disabled',
        ],
    ],

    'payment_gateways' => [
        'statuses' => [
            'enabled' => 'system.statuses.enabled',
            'disabled' => 'system.statuses.disabled',
        ],
    ],

    'plans' => [
        'statuses' => [
            'active' => 'plans.statuses.active',
            'inactive' => 'plans.statuses.inactive',
        ],
        'types' => [
            'base_subscription' => 'plans.types.base_subscription',
            'service_add_on' => 'plans.types.service_add_on',
            'credit_top_up' => 'plans.types.credit_top_up',
        ],
    ],

    'invoices' => [
        'statuses' => [
            'pending' => 'payments.invoice_statuses.pending',
            'failed' => 'payments.invoice_statuses.failed',
            'paid' => 'payments.invoice_statuses.paid',
            'canceled' => 'payments.invoice_statuses.canceled',
        ],
    ],

    'payments' => [
        'statuses' => [
            'pending' => 'payments.payment_statuses.pending',
            'failed' => 'payments.payment_statuses.failed',
            'paid' => 'payments.payment_statuses.paid',
            'canceled' => 'payments.payment_statuses.canceled',
        ],
    ],

    'subscriptions' => [
        'statuses' => [
            'active' => 'payments.subscription_statuses.active',
            'expired' => 'payments.subscription_statuses.expired',
            'replaced' => 'payments.subscription_statuses.replaced',
            'cancelled' => 'payments.subscription_statuses.cancelled',
        ],
    ],

    'subscription_service_entitlements' => [
        'sources' => [
            'base_plan' => 'subscriptions.sources.base_plan',
            'add_on' => 'subscriptions.sources.add_on',
        ],
    ],

    'credit_transactions' => [
        'types' => [
            'grant' => 'credits.transaction_types.grant',
            'use' => 'credits.transaction_types.use',
            'refund' => 'credits.transaction_types.refund',
            'adjustment' => 'credits.transaction_types.adjustment',
            'top_up' => 'credits.transaction_types.top_up',
            'reservation' => 'credits.transaction_types.reservation',
            'release' => 'credits.transaction_types.release',
        ],
    ],

    'plan_access' => [
        'statuses' => [
            'exempt' => 'subscriptions.plan_access_statuses.exempt',
            'active' => 'subscriptions.plan_access_statuses.active',
            'inactive' => 'subscriptions.plan_access_statuses.inactive',
            'expired' => 'subscriptions.plan_access_statuses.expired',
            'credit_finished' => 'subscriptions.plan_access_statuses.credit_finished',
            'plan_disabled' => 'subscriptions.plan_access_statuses.plan_disabled',
        ],
    ],
];
