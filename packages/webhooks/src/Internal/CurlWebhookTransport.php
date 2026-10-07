<?php

declare(strict_types=1);

namespace CoreX\Webhooks\Internal;

use CoreX\Webhooks\Contracts\WebhookHttpTransport;
use CoreX\Webhooks\Data\ValidatedWebhookRequest;
use CoreX\Webhooks\Data\WebhookHttpResult;
use CoreX\Webhooks\Enums\WebhookFailureReason;
use Throwable;

final class CurlWebhookTransport implements WebhookHttpTransport
{
    private const MAX_RESPONSE_BODY_BYTES = 64 * 1024;

    private const MAX_RESPONSE_HEADER_BYTES = 16 * 1024;

    public function send(ValidatedWebhookRequest $request): WebhookHttpResult
    {
        $startedAt = hrtime(true);

        if (! function_exists('curl_init')) {
            return $this->failure(errorCode: WebhookFailureReason::TransportFailure, startedAt: $startedAt);
        }

        if ($request->url === '' || ! WebhookUrlPolicy::allowsPublicAddress($request->address)) {
            return $this->failure(errorCode: WebhookFailureReason::AddressNotAllowed, startedAt: $startedAt);
        }

        $handle = curl_init();

        if ($handle === false) {
            return $this->failure(errorCode: WebhookFailureReason::TransportFailure, startedAt: $startedAt);
        }

        $responseBodyBytes = 0;
        $responseHeaderBytes = 0;
        $bodyOverflow = false;
        $headerOverflow = false;
        $retryAfter = null;

        $write = static function (mixed $handle, string $chunk) use (&$responseBodyBytes, &$bodyOverflow): int {
            $responseBodyBytes += strlen($chunk);

            if ($responseBodyBytes > self::MAX_RESPONSE_BODY_BYTES) {
                $bodyOverflow = true;

                return 0;
            }

            return strlen($chunk);
        };
        $headers = static function (mixed $handle, string $line) use (&$responseHeaderBytes, &$headerOverflow, &$retryAfter): int {
            $responseHeaderBytes += strlen($line);

            if ($responseHeaderBytes > self::MAX_RESPONSE_HEADER_BYTES) {
                $headerOverflow = true;

                return 0;
            }

            if (str_starts_with(strtolower($line), 'retry-after:')) {
                $candidate = trim(substr($line, strlen('retry-after:')));

                if ($candidate !== ''
                    && strlen($candidate) <= 255
                    && preg_match('/[\x00-\x1f\x7f]/', $candidate) !== 1) {
                    $retryAfter = $candidate;
                }
            }

            return strlen($line);
        };
        $address = str_contains($request->address, ':')
            ? '['.$request->address.']'
            : $request->address;

        try {
            $configured = curl_setopt_array($handle, [
                CURLOPT_URL => $request->url,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $request->body,
                CURLOPT_HTTPHEADER => $this->requestHeaders($request),
                CURLOPT_RESOLVE => ["{$request->canonicalHost}:443:{$address}"],
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_MAXREDIRS => 0,
                CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
                CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
                CURLOPT_PROXY => '',
                CURLOPT_NOPROXY => '*',
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_CONNECTTIMEOUT_MS => 3000,
                CURLOPT_TIMEOUT_MS => $request->timeoutSeconds * 1000,
                CURLOPT_NOSIGNAL => true,
                CURLOPT_RETURNTRANSFER => false,
                CURLOPT_HEADER => false,
                CURLOPT_WRITEFUNCTION => $write,
                CURLOPT_HEADERFUNCTION => $headers,
            ]);

            if (! $configured) {
                return $this->failure(errorCode: WebhookFailureReason::TransportFailure, startedAt: $startedAt);
            }

            $executed = curl_exec($handle);
            $status = curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
            $peerAddress = curl_getinfo($handle, CURLINFO_PRIMARY_IP);

            if ($peerAddress !== '' && ! $this->sameAddress(actual: $peerAddress, expected: $request->address)) {
                return $this->failure(errorCode: WebhookFailureReason::AddressNotAllowed, startedAt: $startedAt, status: $status);
            }

            if ($bodyOverflow) {
                return $this->failure(errorCode: WebhookFailureReason::PayloadTooLarge, startedAt: $startedAt, status: $status);
            }

            if ($headerOverflow) {
                return $this->failure(errorCode: WebhookFailureReason::PayloadTooLarge, startedAt: $startedAt, status: $status);
            }

            if ($executed === false) {
                return $this->failure(errorCode: $this->curlErrorCode(curl_errno($handle)), startedAt: $startedAt);
            }

            if (! $this->sameAddress(actual: $peerAddress, expected: $request->address)) {
                return $this->failure(errorCode: WebhookFailureReason::AddressNotAllowed, startedAt: $startedAt, status: $status);
            }

            return new WebhookHttpResult(
                status: $status > 0 ? $status : null,
                retryAfter: $retryAfter,
                errorCode: null,
                durationMs: $this->durationMs($startedAt),
            );
        } catch (Throwable) {
            return $this->failure(errorCode: WebhookFailureReason::TransportFailure, startedAt: $startedAt);
        } finally {
            curl_close($handle);
        }
    }

    /** @return list<string> */
    private function requestHeaders(ValidatedWebhookRequest $request): array
    {
        return [
            'Content-Type: application/cloudevents+json',
            'Webhook-Id: '.$request->signatureHeaders['webhook-id'],
            'Webhook-Timestamp: '.$request->signatureHeaders['webhook-timestamp'],
            'Webhook-Signature: '.$request->signatureHeaders['webhook-signature'],
        ];
    }

    private function curlErrorCode(int $errno): WebhookFailureReason
    {
        return match ($errno) {
            CURLE_OPERATION_TIMEDOUT => WebhookFailureReason::NetworkFailure,
            CURLE_COULDNT_CONNECT, CURLE_COULDNT_RESOLVE_HOST => WebhookFailureReason::NetworkFailure,
            CURLE_SSL_CONNECT_ERROR, CURLE_SSL_CACERT => WebhookFailureReason::NetworkFailure,
            default => WebhookFailureReason::NetworkFailure,
        };
    }

    private function sameAddress(string $actual, string $expected): bool
    {
        $actualPacked = @inet_pton($actual);
        $expectedPacked = @inet_pton($expected);

        return $actualPacked !== false
            && $expectedPacked !== false
            && hash_equals($expectedPacked, $actualPacked);
    }

    private function failure(WebhookFailureReason $errorCode, int $startedAt, ?int $status = null): WebhookHttpResult
    {
        return new WebhookHttpResult(
            status: $status !== null && $status > 0 ? $status : null,
            retryAfter: null,
            errorCode: $errorCode,
            durationMs: $this->durationMs($startedAt),
        );
    }

    private function durationMs(int $startedAt): int
    {
        return max(0, (int) floor((hrtime(true) - $startedAt) / 1_000_000));
    }
}
