<?php

declare(strict_types=1);

namespace CoreX\Webhooks\Data;

use CoreX\Attributes\Api;
use CoreX\Operations\Validation\Laravel\JsonCanonical;
use InvalidArgumentException;

/** Immutable projection of one declared message contract onto a webhook topic. */
#[Api(since: '1.0')]
final readonly class WebhookTopic
{
    public const array CLASSES = ['none', 'pseudonymous', 'personal', 'restricted', 'secret'];

    /** @param  array<string, string>  $schema */
    public function __construct(public string $name, public int $version, public string $major, public array $schema, public string $schemaHash, public string $piiClass, public string $projectorService)
    {
        self::validateInput($name, $version, $major, $schema, $schemaHash, $piiClass, $projectorService);
    }

    public function id(): string
    {
        return "{$this->name}.v{$this->version}";
    }

    public function sensitive(): bool
    {
        return $this->piiClass === 'personal' || $this->piiClass === 'restricted';
    }

    public function secret(): bool
    {
        return $this->piiClass === 'secret';
    }

    public function rank(): int
    {
        return match ($this->piiClass) {
            'none' => 0,
            'pseudonymous' => 1,
            'personal' => 2,
            'restricted' => 3,
            'secret' => 4,
            default => throw new InvalidArgumentException('Webhook topic PII class is not declared.'),
        };
    }

    /** Runtime validation also rejects values from untyped integration callers.
     * @param  array<array-key, mixed>  $schema
     */
    private static function validateInput(
        /** Runtime input. */
        string $name,
        /** Runtime input. */
        int $version,
        /** Runtime input. */
        string $major,
        /** Runtime input. */
        array $schema,
        /** Runtime input. */
        string $schemaHash,
        /** Runtime input. */
        string $piiClass,
        /** Runtime input. */
        string $projectorService,
    ): void {
        if (preg_match('/\A[a-z][a-z0-9]*(?:\.[a-z][a-z0-9]*){1,3}\z/D', $name) !== 1 || str_contains($name, '\\')) {
            throw new InvalidArgumentException('Webhook topic name must be a declared contract name.');
        }

        if ($version < 1) {
            throw new InvalidArgumentException('Webhook topic version must be positive.');
        }

        if (preg_match('/\Av[1-9][0-9]*\z/D', $major) !== 1) {
            throw new InvalidArgumentException('Webhook topic major must be a public API major.');
        }

        if ($schema === [] || array_is_list($schema)) {
            throw new InvalidArgumentException('Webhook topic schema must be a non-empty field map.');
        }

        foreach ($schema as $field => $type) {
            if (! is_string($field) || preg_match('/\A[a-z][a-z0-9_]*\z/D', $field) !== 1 || ! is_string($type) || $type === '') {
                throw new InvalidArgumentException('Webhook topic schema fields must be named scalar types.');
            }
        }

        if (! hash_equals(hash('sha256', JsonCanonical::encode($schema)), $schemaHash)) {
            throw new InvalidArgumentException('Webhook topic schema hash does not match the declared schema.');
        }

        if (! in_array($piiClass, self::CLASSES, true)) {
            throw new InvalidArgumentException('Webhook topic PII class is not declared.');
        }

        if (preg_match('/\A[a-z][a-z0-9]*(?:\.[a-z][a-z0-9]*)*\z/D', $projectorService) !== 1) {
            throw new InvalidArgumentException('Webhook projector must be an installed service id.');
        }
    }
}
