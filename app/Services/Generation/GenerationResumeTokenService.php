<?php

namespace App\Services\Generation;

use App\Models\GenerationRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps generation resume credentials in the verified browser session.
 *
 * Only a SHA-256 digest is persisted with the generation. The raw token lives
 * in the server session and may also be mirrored by the UI in sessionStorage.
 *
 * @since 2.0.0
 */
class GenerationResumeTokenService
{
    private const SESSION_KEY = 'generation_resume_tokens';

    /**
     * Issue or refresh the resume credential for a generation.
     *
     * @param  Request  $request  Current verified visitor request.
     * @param  GenerationRequest  $generation  Tenant/lead-scoped generation.
     * @return string Raw token for this browser session.
     *
     * @since 2.0.0
     */
    public function remember(Request $request, GenerationRequest $generation): string
    {
        $token = (string) $request->session()->get(self::SESSION_KEY.'.'.$generation->uuid, '');

        if ($token === '' || ! $this->matches($generation, $token)) {
            $token = Str::random(64);
            $generation->forceFill(['resume_token_hash' => hash('sha256', $token)])->save();
        }

        $generation->forceFill([
            'resumable_until' => now()->addMinutes(
                max(1, (int) config('hairvision.image_queue.active_session_retention_minutes', 120)),
            ),
        ])->save();
        $request->session()->put(self::SESSION_KEY.'.'.$generation->uuid, $token);

        return $token;
    }

    /**
     * Require both the verified lead session and its generation resume token.
     *
     * @param  Request  $request  Current verified visitor request.
     * @param  GenerationRequest  $generation  Tenant/lead-scoped generation.
     * @return void No value is returned.
     *
     * @since 2.0.0
     */
    public function authorize(Request $request, GenerationRequest $generation): void
    {
        $token = trim((string) $request->header('X-HairVision-Resume-Token'));

        if ($token === '') {
            $token = (string) $request->session()->get(self::SESSION_KEY.'.'.$generation->uuid, '');
        }

        if ($generation->resumable_until?->isPast() || ! $this->matches($generation, $token)) {
            abort(Response::HTTP_NOT_FOUND);
        }
    }

    /**
     * Return UUID/token pairs currently retained by the server session.
     *
     * @param  Request  $request  Current verified visitor request.
     * @return array<string, string> Generation UUIDs keyed to raw session tokens.
     *
     * @since 2.0.0
     */
    public function sessionTokens(Request $request): array
    {
        $tokens = $request->session()->get(self::SESSION_KEY, []);

        return is_array($tokens)
            ? array_filter($tokens, fn (mixed $token, mixed $uuid): bool => is_string($uuid) && is_string($token) && $token !== '', ARRAY_FILTER_USE_BOTH)
            : [];
    }

    /**
     * Return the valid raw token already stored for this browser session.
     *
     * @param  Request  $request  Current verified visitor request.
     * @param  GenerationRequest  $generation  Generation being serialized.
     * @return string|null Raw token or null when this session does not own it.
     *
     * @since 2.0.0
     */
    public function tokenFor(Request $request, GenerationRequest $generation): ?string
    {
        $token = (string) $request->session()->get(self::SESSION_KEY.'.'.$generation->uuid, '');

        return $this->matches($generation, $token) ? $token : null;
    }

    /**
     * Compare one raw session credential to its persisted digest.
     *
     * @param  GenerationRequest  $generation  Generation being accessed.
     * @param  string  $token  Raw token supplied by the verified session.
     * @return bool Whether the credential is valid.
     *
     * @since 2.0.0
     */
    private function matches(GenerationRequest $generation, string $token): bool
    {
        return $token !== ''
            && is_string($generation->resume_token_hash)
            && $generation->resume_token_hash !== ''
            && hash_equals($generation->resume_token_hash, hash('sha256', $token));
    }
}
