<?php

namespace App\Support\Analytics;

/**
 * Resolves privacy-preserving identities for landing performance aggregation.
 *
 * @since 2.1.1
 */
final class LandingVisitorIdentity
{
    /**
     * Resolve one stable report key from a landing visit row or model.
     *
     * A linked lead is authoritative. Anonymous visits use their already-hashed
     * IP and user-agent pair, and rows without either value fall back to the
     * visit id so unrelated unknown visitors are never collapsed together.
     *
     * @param  object  $visit  Visit with id, lead_id, ip_hash, and user_agent_hash values.
     * @return string Stable key used only while aggregating reports.
     *
     * @since 2.1.1
     */
    public static function key(object $visit): string
    {
        if ($visit->lead_id !== null) {
            return 'lead:'.(int) $visit->lead_id;
        }

        $ipHash = is_string($visit->ip_hash) ? $visit->ip_hash : '';
        $userAgentHash = is_string($visit->user_agent_hash) ? $visit->user_agent_hash : '';

        if ($ipHash !== '' || $userAgentHash !== '') {
            return 'anonymous:'.$ipHash.':'.$userAgentHash;
        }

        return 'visit:'.(int) $visit->id;
    }
}
