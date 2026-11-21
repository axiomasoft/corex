<?php

declare(strict_types=1);

namespace CoreX\Search\Query\Internal;

use CoreX\Data\Data\DataScope;
use CoreX\Search\Errors\Exceptions\InvalidSearchCursor;
use CoreX\Search\Exceptions\InvalidSearchConfiguration;
use CoreX\Search\Query\Data\SearchQuery;
use Packman\Attributes\Internal;
use RuntimeException;

#[Internal]
final readonly class SearchCursor
{
    private string $key;

    public function __construct(string $secret)
    {
        if (strlen($secret) < 32) {
            throw InvalidSearchConfiguration::weakCursorSecret();
        }
        $this->key = hash('sha256', 'corex-search/query/v2:'.$secret, true);
    }

    public static function identity(DataScope $scope, SearchQuery $query): string
    {
        $shape = get_object_vars($query);
        unset($shape['cursor']);

        return hash('sha256', json_encode([2, $scope, $shape], JSON_THROW_ON_ERROR));
    }

    public function encode(string $identity, string $mapping, string $generation, string $witness, string $position): string
    {
        $nonce = random_bytes(12);
        $tag = '';
        $encrypted = openssl_encrypt('m'.$mapping.$generation.$witness.$position, 'aes-256-gcm', $this->key, OPENSSL_RAW_DATA, $nonce, $tag, $identity);

        if ($encrypted === false) {
            throw new RuntimeException('Search cursor encryption failed.');
        }

        return 'q2.'.self::encode64($nonce.$tag.$encrypted);
    }

    /** @return array{mapping:string,generation:string,witness:string,position:string} */
    public function decode(string $identity, string $cursor): array
    {
        if (! str_starts_with($cursor, 'q2.')) {
            throw InvalidSearchCursor::invalid();
        }
        $encoded = substr($cursor, 3);
        $raw = base64_decode(strtr($encoded, '-_', '+/'), true);

        if ($raw === false || strlen($raw) < 29 || self::encode64($raw) !== $encoded) {
            throw InvalidSearchCursor::invalid();
        }
        $plain = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', $this->key, OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16), $identity);

        if ($plain === false || strlen($plain) <= 193 || strlen($plain) > 8385 || $plain[0] !== 'm') {
            throw InvalidSearchCursor::invalid();
        }

        return ['mapping' => substr($plain, 1, 64), 'generation' => substr($plain, 65, 64),
            'witness' => substr($plain, 129, 64), 'position' => substr($plain, 193)];
    }

    private static function encode64(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }
}
