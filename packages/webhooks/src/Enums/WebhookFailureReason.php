<?php

declare(strict_types=1);

namespace CoreX\Webhooks\Enums;

use CoreX\Attributes\Api;

#[Api(since: '1.0')]
enum WebhookFailureReason: string
{
    case EndpointChanged = 'webhook_endpoint_changed';
    case AckUnknown = 'ack_unknown';
    case EndpointGone = 'endpoint_gone';
    case RedirectForbidden = 'redirect_forbidden';
    case HttpPermanent = 'http_permanent';
    case UrlForbidden = 'url_forbidden';
    case AddressNotAllowed = 'address_not_allowed';
    case EgressDenied = 'egress_denied';
    case TopicUnavailable = 'topic_unavailable';
    case PayloadTooLarge = 'payload_too_large';
    case Http429 = 'http_429';
    case Http5xx = 'http_5xx';
    case Http408 = 'http_408';
    case Http425 = 'http_425';
    case DnsUnavailable = 'dns_unavailable';
    case NetworkFailure = 'network_failure';
    case TransportFailure = 'transport_failure';
    case SigningUnavailable = 'signing_unavailable';
    case RetryExhausted = 'retry_exhausted';
    case RetryHorizonExhausted = 'retry_horizon_exhausted';

    public function isPermanent(): bool
    {
        return match ($this) {
            self::EndpointChanged, self::EndpointGone, self::RedirectForbidden,
            self::HttpPermanent, self::UrlForbidden, self::AddressNotAllowed,
            self::EgressDenied, self::TopicUnavailable, self::PayloadTooLarge => true,
            default => false,
        };
    }

    public function isTerminal(): bool
    {
        return $this->isPermanent()
            || $this === self::RetryExhausted
            || $this === self::RetryHorizonExhausted;
    }

    public static function fromHttpStatus(int $status): ?self
    {
        return match (true) {
            $status >= 200 && $status < 300 => null,
            $status === 410 => self::EndpointGone,
            $status >= 300 && $status < 400 => self::RedirectForbidden,
            $status === 408 => self::Http408,
            $status === 425 => self::Http425,
            $status === 429 => self::Http429,
            $status >= 400 && $status < 500 => self::HttpPermanent,
            $status >= 500 => self::Http5xx,
            default => null,
        };
    }
}
