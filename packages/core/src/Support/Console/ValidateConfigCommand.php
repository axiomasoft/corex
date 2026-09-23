<?php

declare(strict_types=1);

namespace CoreX\Support\Console;

use CoreX\Enums\PubSubDriver;
use CoreX\PubSub\RedisPubSub;
use CoreX\Support\CoreOptions;
use CoreX\Support\InvalidConfiguration;
use CoreX\Support\SecretRef;
use Illuminate\Console\Command;
use Throwable;

/**
 * Prints effective drivers and adapter availability without secret values.
 *
 * @internal spec: P7-design §12, D40
 */
final class ValidateConfigCommand extends Command
{
    private const INTL_CURSOR_SECRET_DEFAULT = '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef';

    private const WAKEUP_PACKAGE = 'CoreX\\Wakeup\\WakeupServiceProvider';

    /** @var array<class-string, list<string>> */
    private const OPTION_SOURCES = [
        CoreOptions::class => ['corex'],
        'CoreX\\Tenancy\\Support\\TenancyOptions' => ['tenancy', 'corex-tenancy'],
        'CoreX\\Auth\\Support\\AuthOptions' => ['corex-auth'],
        'CoreX\\Modules\\Support\\ModulesOptions' => ['corex-modules'],
        'CoreX\\Media\\Support\\MediaOptions' => ['corex-media'],
    ];

    /** @var list<string> */
    private const SECRET_KEYS = [
        'tenancy.oidc.client_secret',
        'corex-intl.reference.cursor_secret',
        'corex-search.cursor_secret',
        'corex-search-meilisearch.key',
    ];

    protected $signature = 'corex:config:validate {--format=text}';

    protected $description = 'Validate bound Options, print effective drivers and adapter availability, and never print secret values.';

    /** @var list<string> */
    private array $redactions = [];

    /** @var list<string> */
    private array $errors = [];

    private bool $intlDefaultWarning = false;

    public function handle(): int
    {
        $format = $this->option('format');

        if ($format !== 'text') {
            $this->writeError('format must be text');

            return self::FAILURE;
        }

        $this->collectSecrets();
        $this->validateBoundOptions();
        $this->writeDrivers();
        $this->writeAdapters();
        $this->writeSecrets();

        foreach ($this->errors as $error) {
            $this->writeError($error);
        }

        return $this->errors === [] ? self::SUCCESS : self::FAILURE;
    }

    private function collectSecrets(): void
    {
        foreach (self::SECRET_KEYS as $key) {
            $plain = $this->plaintext(config($key));

            if ($plain === null) {
                continue;
            }

            $this->redactions[] = $plain;

            if ($key === 'corex-intl.reference.cursor_secret' && $plain === self::INTL_CURSOR_SECRET_DEFAULT) {
                $this->intlDefaultWarning = true;
            }
        }
    }

    private function validateBoundOptions(): void
    {
        foreach (self::OPTION_SOURCES as $class => $keys) {
            if (! class_exists($class) || ! $this->laravel->bound($class) || ! method_exists($class, 'fromArray')) {
                continue;
            }

            $arguments = array_map($this->configArray(...), $keys);

            try {
                $class::fromArray(...$arguments);
            } catch (InvalidConfiguration $exception) {
                foreach ($exception->violations as $violation) {
                    $this->errors[] = $this->redact($violation);
                }
            } catch (Throwable $exception) {
                $this->errors[] = $this->redact($exception->getMessage());
            }
        }
    }

    private function writeDrivers(): void
    {
        $this->writeLine('driver pubsub.driver='.$this->scalar(config('corex.pubsub.driver')));
        $this->writeLine('driver tenancy.wakeup.driver='.$this->scalar(config('corex-tenancy.wakeup.driver')));
        $this->writeLine('driver tenancy.oidc.enabled='.$this->scalar(config('tenancy.oidc.enabled')));
    }

    private function writeAdapters(): void
    {
        $pubsub = config('corex.pubsub.driver');
        $redisSelected = $pubsub === PubSubDriver::Redis || $pubsub === PubSubDriver::Redis->value;

        if ($redisSelected) {
            $available = class_exists(RedisPubSub::class);
            $this->writeLine('adapter pubsub.redis='.($available ? 'available' : 'missing'));

            if (! $available) {
                $this->errors[] = 'adapter pubsub.redis missing';
            }
        } else {
            $this->writeLine('adapter pubsub.redis=not-selected');
        }

        $wakeup = config('corex-tenancy.wakeup.driver');

        if ($wakeup === 'root_registry') {
            $available = class_exists(self::WAKEUP_PACKAGE);
            $this->writeLine('adapter wakeup.root_registry='.($available ? 'available' : 'missing'));

            if (! $available) {
                $this->errors[] = 'adapter wakeup.root_registry missing';
            }
        } else {
            $this->writeLine('adapter wakeup.root_registry=not-selected');
        }
    }

    private function writeSecrets(): void
    {
        foreach (self::SECRET_KEYS as $key) {
            $present = $this->plaintext(config($key)) !== null;
            $this->writeLine('secret '.$key.'='.($present ? 'present' : 'absent'));

            if ($key === 'corex-intl.reference.cursor_secret' && $this->intlDefaultWarning) {
                $this->writeWarning('WARNING [secret-default] '.$key);
            }
        }
    }

    private function plaintext(mixed $value): ?string
    {
        if ($value instanceof SecretRef) {
            return $value->isPresent() ? $value->reveal() : null;
        }

        if (is_string($value) && $value !== '') {
            return $value;
        }

        return null;
    }

    /** @return array<string, mixed> */
    private function configArray(string $key): array
    {
        $value = config($key, []);

        return is_array($value) ? $value : [];
    }

    private function scalar(mixed $value): string
    {
        if ($value instanceof PubSubDriver) {
            return $value->value;
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_string($value) || is_int($value)) {
            return $this->redact((string) $value);
        }

        return 'absent';
    }

    private function redact(string $text): string
    {
        foreach ($this->redactions as $secret) {
            if ($secret !== '') {
                $text = str_replace($secret, 'present', $text);
            }
        }

        return $text;
    }

    private function writeLine(string $line): void
    {
        $this->line($this->redact($line));
    }

    private function writeWarning(string $line): void
    {
        $this->warn($this->redact($line));
    }

    private function writeError(string $line): void
    {
        $this->output->getErrorStyle()->writeln($this->redact($line));
    }
}
