<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Tests\Support\Membership;

use CoreX\Tenancy\Membership\Transport\AccessTokenValidator;
use CoreX\Tenancy\Membership\Transport\ChannelBinding;
use CoreX\Tenancy\Membership\Transport\JwsVerifier;
use CoreX\Tenancy\Membership\Transport\MembershipQuery;
use CoreX\Tenancy\Membership\Transport\StrictTransport;
use CoreX\Tenancy\Membership\Transport\TriggerParser;
use OpenSSLAsymmetricKey;

/** Fixed isolated reference consumer: real signatures, fake HTTP, no deployment. */
final class TransportFixture
{
    public const IDENTITY = '11111111-1111-4111-8111-111111111111';

    public const ACCOUNT = '22222222-2222-4222-8222-222222222222';

    public const REQUEST = '33333333-3333-4333-8333-333333333333';

    public const MEMBERSHIP = '44444444-4444-4444-8444-444444444444';

    public const OTHER = '55555555-5555-4555-8555-555555555555';

    public const PRODUCT = 'reference:app';

    private static ?OpenSSLAsymmetricKey $sharedAccessKey = null;

    private static ?OpenSSLAsymmetricKey $sharedEventKey = null;

    public int $now;

    public string $directory;

    public ChannelBinding $binding;

    public OpenSSLAsymmetricKey $accessKey;

    public OpenSSLAsymmetricKey $eventKey;

    public JwsVerifier $verifier;

    public function __construct()
    {
        $this->now = time();
        $this->directory = sys_get_temp_dir().'/corex-membership-fixture-'.bin2hex(random_bytes(8));
        mkdir($this->directory, 0700);
        file_put_contents($this->directory.'/openssl.cnf', "[req]\ndistinguished_name=req_distinguished_name\n[req_distinguished_name]\n");
        $options = ['config' => $this->directory.'/openssl.cnf', 'private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA];
        $this->accessKey = self::$sharedAccessKey ??= openssl_pkey_new($options);
        $this->eventKey = self::$sharedEventKey ??= openssl_pkey_new($options);
        $csr = openssl_csr_new(['commonName' => 'membership-fixture'], $this->accessKey, $options);
        $certificate = openssl_csr_sign($csr, null, $this->accessKey, 1, $options);
        openssl_x509_export_to_file($certificate, $this->directory.'/certificate.pem');
        openssl_pkey_export_to_file($this->accessKey, $this->directory.'/key.pem', options: $options);
        copy($this->directory.'/certificate.pem', $this->directory.'/ca.pem');
        chmod($this->directory.'/key.pem', 0600);
        $this->now = time();
        $thumbprint = rtrim(strtr(base64_encode(openssl_x509_fingerprint($certificate, 'sha256', true)), '+/', '-_'), '=');
        $this->binding = new ChannelBinding(readUri: 'https://central.example.test/reference/state', revokeUri: 'https://central.example.test/reference/revoke', issuer: 'https://issuer.example.test', resource: 'https://resource.example.test', clientId: 'fixture-client', subject: 'service:fixture-client', accountId: self::ACCOUNT, product: self::PRODUCT, certificatePath: $this->directory.'/certificate.pem', keyPath: $this->directory.'/key.pem', caPath: $this->directory.'/ca.pem', certificateThumbprint: $thumbprint, certificateAcceptedUntil: $this->now + 600, deduplicationQualified: true);
        $this->verifier = new JwsVerifier(['access' => ['pem' => openssl_pkey_get_details($this->accessKey)['key'], 'purpose' => 'access'], 'event' => ['pem' => openssl_pkey_get_details($this->eventKey)['key'], 'purpose' => 'trigger']]);
    }

    public function cleanup(): void
    {
        foreach (['certificate.pem', 'key.pem', 'ca.pem', 'openssl.cnf'] as $file) {
            unlink($this->directory.'/'.$file);
        }
        rmdir($this->directory);
    }

    /** @param array<string, mixed> $overrides */
    public function binding(array $overrides = []): ChannelBinding
    {
        return new ChannelBinding(...[...get_object_vars($this->binding), ...$overrides]);
    }

    public function query(string $request = self::REQUEST): MembershipQuery
    {
        return new MembershipQuery(identityId: self::IDENTITY, accountId: self::ACCOUNT, product: self::PRODUCT, requestId: $request);
    }

    /** @return array<string, mixed> */
    public function accessClaims(): array
    {
        return ['iss' => $this->binding->issuer, 'aud' => $this->binding->resource, 'sub' => $this->binding->subject, 'client_id' => $this->binding->clientId, 'iat' => $this->now, 'exp' => $this->now + 300, 'jti' => self::REQUEST, 'scope' => 'membership.read membership.revoke', 'account_id' => self::ACCOUNT, 'product' => self::PRODUCT, 'cnf' => ['x5t#S256' => $this->binding->certificateThumbprint]];
    }

    /** @param array<string, mixed>|null $claims */
    public function client(?array $claims = null, ?ChannelBinding $binding = null, bool $permission = true, string $contextAccount = self::ACCOUNT, string $contextProduct = self::PRODUCT): StrictTransport
    {
        return new StrictTransport(binding: $binding ?? $this->binding, accessToken: $this->sign($claims ?? $this->accessClaims()), tokenValidator: new AccessTokenValidator($this->verifier), clock: fn (): int => $this->now, context: fn (): array => ['accountId' => $contextAccount, 'product' => $contextProduct], removalPermission: fn (): bool => $permission);
    }

    /** @param array<string, mixed> $claims
     *  @param array<string, mixed> $header */
    public function sign(array $claims, array $header = [], bool $event = false): string
    {
        $header = [...['alg' => 'RS256', 'typ' => $event ? 'corex-membership-trigger+jwt' : 'at+jwt', 'kid' => $event ? 'event' : 'access'], ...$header];
        $encoded = self::encode(json_encode($header, JSON_THROW_ON_ERROR)).'.'.self::encode(json_encode($claims, JSON_THROW_ON_ERROR));
        openssl_sign($encoded, $signature, $event ? $this->eventKey : $this->accessKey, OPENSSL_ALGO_SHA256);

        return $encoded.'.'.self::encode($signature);
    }

    public static function encode(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    /** @return array<string, mixed> */
    public function snapshot(string $request = self::REQUEST): array
    {
        return ['profile_version' => 'corex-membership/1', 'request_id' => $request, 'identity_id' => self::IDENTITY, 'account_id' => self::ACCOUNT, 'product' => self::PRODUCT, 'membership_id' => self::MEMBERSHIP, 'state' => 'active', 'revision' => '10', 'revoke_generation' => '3', 'read_started_at' => '2026-09-13T00:00:00Z', 'observed_at' => '2026-09-13T00:00:01Z'];
    }

    /** @return array<string, mixed> */
    public function receipt(): array
    {
        return ['profile_version' => 'corex-membership/1', 'request_id' => self::REQUEST, 'identity_id' => self::IDENTITY, 'account_id' => self::ACCOUNT, 'product' => self::PRODUCT, 'commit_ref' => 'commit:fixture-10', 'revision' => '10', 'revoke_generation' => '3', 'committed_at' => '2026-09-13T00:00:01Z'];
    }

    /** @return array<string, string> */
    public static function headers(): array
    {
        return ['Cache-Control' => 'no-store', 'Content-Type' => 'application/json', 'X-CoreX-Membership-Profile' => 'corex-membership/1'];
    }

    /** @return array<string, mixed> */
    public function eventClaims(): array
    {
        return ['iss' => $this->binding->issuer, 'aud' => 'https://consumer.example.test/triggers', 'event_id' => self::REQUEST, 'jti' => self::OTHER, 'iat' => $this->now, 'exp' => $this->now + 300, 'profile_version' => 'corex-membership/1', 'identity_id' => self::IDENTITY, 'account_id' => self::ACCOUNT, 'product' => self::PRODUCT, 'kind' => 'identity.membership.granted', 'commit_ref' => 'commit:fixture-9', 'reason' => 'policy', 'acting_identity_id' => null, 'occurred_at' => '2026-09-13T00:00:00Z'];
    }

    public function parser(): TriggerParser
    {
        return new TriggerParser(verifier: $this->verifier, issuer: $this->binding->issuer, audience: 'https://consumer.example.test/triggers', accountId: self::ACCOUNT, product: self::PRODUCT);
    }
}
