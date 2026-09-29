<?php

declare(strict_types=1);

namespace Flex\Crm\Context;

use Illuminate\Validation\ValidationException;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;

/** @internal International input is explicit: no inferred country/consumer locale. */
final class Channels
{
    /**
     * @param  array<mixed>  $input
     * @return list<array{value: string, norm: string, type: ?string, is_primary: bool}>
     */
    public static function normalize(array $input, bool $phone): array
    {
        if (! array_is_list($input)) {
            self::invalid();
        }
        $result = [];
        $seen = [];
        $primary = false;
        foreach ($input as $channel) {
            if (! is_array($channel) || array_diff(array_keys($channel), ['value', 'type', 'is_primary', 'norm']) !== [] || ! is_string($channel['value'] ?? null)) {
                self::invalid();
            }
            $value = trim($channel['value']);
            $type = $channel['type'] ?? null;
            $isPrimary = $channel['is_primary'] ?? false;

            if ($value === '' || ! is_bool($isPrimary) || ($type !== null && (! is_string($type) || trim($type) === '' || mb_strlen($type) > 64))) {
                self::invalid();
            }

            if ($phone) {
                if (! str_starts_with($value, '+') || preg_match('/^\+[0-9 ().-]+$/D', $value) !== 1) {
                    self::invalid();
                }

                try {
                    $util = PhoneNumberUtil::getInstance();
                    $number = $util->parse($value, null);

                    if (! $util->isValidNumber($number)) {
                        self::invalid();
                    }
                    $norm = $util->format($number, PhoneNumberFormat::E164);
                } catch (NumberParseException) {
                    self::invalid();
                }
            } else {
                if (filter_var($value, FILTER_VALIDATE_EMAIL) === false || strlen($value) > 254) {
                    self::invalid();
                }
                $norm = mb_strtolower($value);
            }

            if (isset($seen[$norm]) || ($isPrimary && $primary)) {
                self::invalid();
            }
            $seen[$norm] = true;
            $primary = $primary || $isPrimary;
            $result[] = ['value' => $value, 'norm' => $norm, 'type' => $type === null ? null : trim($type), 'is_primary' => $isPrimary];
        }

        return $result;
    }

    private static function invalid(): never
    {
        throw ValidationException::withMessages(['channels' => 'Channels require valid values, unique normalized values and at most one primary per channel kind. Phones require international +country format.']);
    }
}
