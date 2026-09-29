<?php

declare(strict_types=1);

namespace CoreX\Config\Internal\Commands;

use CoreX\Config\Contracts\ConfigReportSection;
use CoreX\Config\Exceptions\InvalidConfiguration;
use CoreX\Config\SecretRef;
use Illuminate\Console\Command;
use Throwable;
use UnexpectedValueException;

/**
 * Prints effective drivers and adapter availability without secret values.
 *
 * @internal spec: P7-design §12, D40
 */
final class ValidateConfigCommand extends Command
{
    /** @var list<ConfigReportSection> */
    private array $sections = [];

    protected $signature = 'corex:config:validate {--format=text}';

    protected $description = 'Validate bound Options, print effective drivers and adapter availability, and never print secret values.';

    /** @var list<string> */
    private array $redactions = [];

    /** @var list<string> */
    private array $errors = [];

    /** @var array<string, true> */
    private array $defaultWarnings = [];

    public function handle(): int
    {
        $this->redactions = [];
        $this->errors = [];
        $this->defaultWarnings = [];
        $sections = [];
        foreach ($this->laravel->tagged(ConfigReportSection::class) as $section) {
            if (! $section instanceof ConfigReportSection) {
                throw new UnexpectedValueException('Configuration report contributor must implement ConfigReportSection.');
            }
            $sections[] = $section;
        }
        $this->sections = $sections;
        usort($this->sections, static fn (ConfigReportSection $a, ConfigReportSection $b): int => $a->order() <=> $b->order());

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
        foreach ($this->secretKeys() as $key) {
            $plain = $this->plaintext(config($key));

            if ($plain === null) {
                continue;
            }

            $this->redactions[] = $plain;

            if ($plain === ($this->secretDefaults()[$key] ?? null)) {
                $this->defaultWarnings[$key] = true;
            }
        }
    }

    private function validateBoundOptions(): void
    {
        foreach ($this->optionSources() as $class => $keys) {
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
        foreach ($this->sections as $section) {
            foreach ($section->drivers() as $line) {
                $this->writeLine($line);
            }
        }
    }

    private function writeAdapters(): void
    {
        foreach ($this->sections as $section) {
            foreach ($section->adapters() as $adapter) {
                $this->writeLine($adapter['line']);

                if ($adapter['error'] !== null) {
                    $this->errors[] = $adapter['error'];
                }
            }
        }
    }

    /** @return list<string> */
    private function secretKeys(): array
    {
        return array_merge([], ...array_map(static fn (ConfigReportSection $section): array => $section->secretKeys(), $this->sections));
    }

    /** @return array<string, string> */
    private function secretDefaults(): array
    {
        return array_merge([], ...array_map(static fn (ConfigReportSection $section): array => $section->secretDefaults(), $this->sections));
    }

    /** @return array<class-string, list<string>> */
    private function optionSources(): array
    {
        return array_merge([], ...array_map(static fn (ConfigReportSection $section): array => $section->options(), $this->sections));
    }

    private function writeSecrets(): void
    {
        foreach ($this->secretKeys() as $key) {
            $present = $this->plaintext(config($key)) !== null;
            $this->writeLine('secret '.$key.'='.($present ? 'present' : 'absent'));

            if (isset($this->defaultWarnings[$key])) {
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
