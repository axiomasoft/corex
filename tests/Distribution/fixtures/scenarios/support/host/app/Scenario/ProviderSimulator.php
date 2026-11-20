<?php

declare(strict_types=1);

namespace App\Scenario;

use CurlHandle;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use SQLite3;

final readonly class ProviderSimulator
{
    public function __construct(private ScenarioClock $clock) {}

    /** @param list<string> $headers */
    public function send(string $owner, string $key, string $body, array $headers = []): array
    {
        $headers[] = 'X-Scenario-Response: '.(getenv('COREX_SCENARIO_PROVIDER_RESPONSE') ?: 'reply');
        $headers[] = 'X-Scenario-Lookup-Delay: '.(getenv('COREX_SCENARIO_PROVIDER_LOOKUP_DELAY') ?: '0');
        $headers[] = 'X-Scenario-Idempotent: '.(self::idempotent() ? '1' : '0');
        $barrier = getenv('COREX_SCENARIO_PROVIDER_BARRIER');

        if ($barrier !== false) {
            $headers[] = 'X-Scenario-Barrier: '.$barrier;
        }

        return $this->request($owner, $key, $headers, $body);
    }

    public function lookup(string $owner, string $key): array
    {
        return $this->request($owner, $key, []);
    }

    public static function idempotent(): bool
    {
        return getenv('COREX_SCENARIO_PROVIDER_IDEMPOTENT') !== '0';
    }

    public static function lookupEnabled(): bool
    {
        return getenv('COREX_SCENARIO_PROVIDER_LOOKUP') !== '0';
    }

    public static function holdLostResponse(string $owner, string $key): void
    {
        $barrier = getenv('COREX_SCENARIO_PROVIDER_WORKER_BARRIER');

        if ($barrier === false) {
            return;
        }
        $proof = new Scenario;
        $journal = self::evidence($owner, $key);
        $proof->same(1, count($journal['requests']), 'The worker saw one provider request before the lost response');
        $proof->same(1, count($journal['effects']), 'The provider committed its effect before the worker lost the response');
        $proof->report(DB::connection(), [$key], [], ['provider' => $journal]);
        Scenario::barrier($barrier, ['owner' => $owner, 'effect_key' => $key]);
    }

    public static function evidence(string $owner, ?string $key = null): array
    {
        $barriers = getenv('COREX_SCENARIO_BARRIERS');
        $journal = new SQLite3(dirname((string) $barriers).'/provider-journal.sqlite', SQLITE3_OPEN_READONLY);
        $journal->enableExceptions(true);
        $journal->busyTimeout(5000);
        $records = [];

        try {
            foreach (['requests', 'effects', 'lookups'] as $table) {
                $statement = $journal->prepare("SELECT * FROM {$table} WHERE owner = :owner AND (:key IS NULL OR effect_key = :key) ORDER BY id");
                $statement->bindValue(':owner', $owner, SQLITE3_TEXT);
                $statement->bindValue(':key', $key, $key === null ? SQLITE3_NULL : SQLITE3_TEXT);
                $rows = $statement->execute();
                $records[$table] = [];
                while (($row = $rows->fetchArray(SQLITE3_ASSOC)) !== false) {
                    $records[$table][] = $row;
                }
                $rows->finalize();
                $statement->close();
            }
        } finally {
            $journal->close();
        }

        return $records;
    }

    /** @param list<string> $headers */
    private function request(string $owner, string $key, array $headers, ?string $body = null): array
    {
        $port = Scenario::barrierRead('provider-ready')['port'] ?? null;

        if (! is_int($port) || $port < 1 || $port > 65535) {
            throw new RuntimeException('The provider simulator has no loopback port.');
        }
        $handle = curl_init('http://127.0.0.1:'.$port.'/effects/'.rawurlencode($owner).'/'.rawurlencode($key));

        if (! $handle instanceof CurlHandle) {
            throw new RuntimeException('Cannot start the fixture provider request.');
        }
        $options = [
            CURLOPT_HTTPHEADER => [...$headers, 'X-Scenario-Clock: '.$this->clock->now()->getTimestamp()],
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 85,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_PROXY => '',
        ];

        if ($body !== null) {
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = $body;
        }
        curl_setopt_array($handle, $options);
        $result = curl_exec($handle);
        $status = curl_getinfo($handle, CURLINFO_RESPONSE_CODE);

        if ($result === false) {
            if (curl_errno($handle) !== CURLE_GOT_NOTHING) {
                throw new RuntimeException('Fixture provider transport failed. '.curl_error($handle));
            }

            return ['status' => 0, 'reference' => null];
        }
        $data = json_decode((string) $result, true, 512, JSON_THROW_ON_ERROR);

        if (! in_array($status, [200, 404], true)) {
            throw new RuntimeException('Fixture provider rejected the request with status '.$status.'.');
        }

        return ['status' => $status, 'reference' => $data['reference'] ?? null];
    }
}
