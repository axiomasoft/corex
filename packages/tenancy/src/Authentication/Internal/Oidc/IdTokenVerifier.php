<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Authentication\Internal\Oidc;

use CoreX\Tenancy\Authentication\Exceptions\OidcAuthenticationException;
use Firebase\JWT\JWT;
use Packman\Attributes\Internal;
use Throwable;

/**
 * Verifies one `id_token` in the ID-token validation sequence: header (`alg` allow-list + `kid` present) BEFORE `JWT::decode()` —
 * an `alg: none`/HS256-with-public-key-as-secret forgery must never reach
 * the decoder — then signature, then `iss`/`aud`/`nonce`/`sub`. Membership
 * (`acct`/`root_identity_accounts.status`) and the `imp` gate are NOT this
 * class's job (CoreX-specific, not generic OIDC) — the controller does
 * those against the claims this returns.
 *
 * @internal
 */
#[Internal]
final readonly class IdTokenVerifier
{
    /**
     * @param  list<string>  $allowedAlgs
     */
    public function __construct(
        private JwksKeySet $jwks,
        private string $issuer,
        private string $clientId,
        private array $allowedAlgs,
        private int $leewaySeconds,
    ) {}

    /**
     * @return array<string, mixed> the verified claims
     */
    public function verify(string $idToken, string $jwksUri, string $nonce): array
    {
        $header = $this->decodeHeader($idToken);

        $alg = $header['alg'] ?? null;

        if (! is_string($alg) || ! in_array($alg, $this->allowedAlgs, true)) {
            throw OidcAuthenticationException::invalidHeader('alg');
        }

        $kid = $header['kid'] ?? null;

        if (! is_string($kid) || $kid === '') {
            throw OidcAuthenticationException::invalidHeader('kid');
        }

        $keys = $this->jwks->keysForVerification(jwksUri: $jwksUri, kid: $kid);

        $previousLeeway = JWT::$leeway;
        JWT::$leeway = $this->leewaySeconds;

        try {
            $decoded = JWT::decode($idToken, $keys);
        } catch (Throwable) {
            throw OidcAuthenticationException::invalidSignature();
        } finally {
            JWT::$leeway = $previousLeeway;
        }

        /** @var array<string, mixed> $claims */
        $claims = (array) $decoded;

        if (($claims['iss'] ?? null) !== $this->issuer) {
            throw OidcAuthenticationException::claimMismatch('iss');
        }

        $aud = $claims['aud'] ?? null;
        $audience = is_array($aud) ? $aud : [$aud];

        if (! in_array($this->clientId, $audience, true)) {
            throw OidcAuthenticationException::claimMismatch('aud');
        }

        if (($claims['nonce'] ?? null) !== $nonce) {
            throw OidcAuthenticationException::claimMismatch('nonce');
        }

        $sub = $claims['sub'] ?? null;

        if (! is_string($sub) || $sub === '') {
            throw OidcAuthenticationException::claimMismatch('sub');
        }

        return $claims;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeHeader(string $idToken): array
    {
        $segments = explode('.', $idToken);

        if (count($segments) !== 3) {
            throw OidcAuthenticationException::malformedIdToken();
        }

        $decoded = json_decode($this->base64UrlDecode($segments[0]), true);

        return is_array($decoded)
            ? array_filter($decoded, is_string(...), ARRAY_FILTER_USE_KEY)
            : [];
    }

    private function base64UrlDecode(string $data): string
    {
        $remainder = strlen($data) % 4;

        if ($remainder > 0) {
            $data .= str_repeat('=', 4 - $remainder);
        }

        $decoded = base64_decode(strtr($data, '-_', '+/'), true);

        if ($decoded === false) {
            throw OidcAuthenticationException::malformedIdToken();
        }

        return $decoded;
    }
}
