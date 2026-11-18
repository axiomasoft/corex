<?php

declare(strict_types=1);

namespace CoreX\Intl\Locale\Internal;

use CoreX\Intl\Locale\Contracts\LocaleResolver;
use CoreX\Intl\Locale\Data\LocaleInputs;
use CoreX\Intl\Locale\Data\LocalePolicy;
use CoreX\Intl\Locale\Data\LocaleResolution;
use CoreX\Intl\Locale\Exceptions\InvalidLocaleException;
use Override;
use Packman\Attributes\Internal;

#[Internal]
final readonly class DefaultLocaleResolver implements LocaleResolver
{
    public function __construct(
        private Bcp47Validator $validator,
    ) {}

    #[Override]
    public function resolve(LocaleInputs $inputs, LocalePolicy $policy): LocaleResolution
    {
        $requested = null;
        $origin = 'fallback';
        $candidate = null;

        if ($inputs->projectedUserTag !== null && $inputs->projectedUserTag !== '') {
            $candidate = $this->parseTag(tag: $inputs->projectedUserTag);
            $origin = 'user';
        } elseif ($inputs->accountSettingsTag !== null && $inputs->accountSettingsTag !== '') {
            $candidate = $this->parseTag(tag: $inputs->accountSettingsTag);
            $origin = 'settings';
        }

        if ($candidate === null) {
            $candidate = $policy->appTag;
            $origin = 'app';
        } else {
            $requested = $candidate;
        }

        [$used, $fallbackChain, $wasFallback] = $this->negotiate(
            candidate: $candidate,
            policy: $policy,
        );

        if ($requested === null && $used !== $policy->appTag) {
            $wasFallback = true;
        }

        $fallbackChain = array_values(array_unique([
            ...$fallbackChain,
            ...$policy->explicitFallbackTags,
        ]));

        return new LocaleResolution(
            requested: $requested,
            used: $used,
            origin: $origin,
            fallbackChain: $fallbackChain,
            wasFallback: $wasFallback,
            policyHash: $policy->policyHash,
            icuVersion: defined('INTL_ICU_VERSION') ? INTL_ICU_VERSION : null,
        );
    }

    private function parseTag(string $tag): ?string
    {
        try {
            return $this->validator->canonicalizeTag(tag: $tag);
        } catch (InvalidLocaleException) {
            return null;
        }
    }

    /**
     * @return array{0: string, 1: list<string>, 2: bool}
     */
    private function negotiate(string $candidate, LocalePolicy $policy): array
    {
        $chain = [$candidate];

        if ($this->validator->isSupported(tag: $candidate)) {
            return [$this->validator->canonicalize(tag: $candidate), $chain, false];
        }

        if ($policy->allowParent) {
            foreach ($this->validator->parentChain(tag: $candidate) as $parent) {
                $chain[] = $parent;

                if ($this->validator->isSupported(tag: $parent)) {
                    return [$this->validator->canonicalize(tag: $parent), $chain, true];
                }
            }
        }

        foreach ([$policy->appTag, ...$policy->explicitFallbackTags] as $fallback) {
            $chain[] = $fallback;

            if ($this->validator->isSupported(tag: $fallback)) {
                return [$this->validator->canonicalize(tag: $fallback), $chain, true];
            }
        }

        return [$policy->explicitFallbackTags[0] ?? $policy->appTag, $chain, true];
    }
}
