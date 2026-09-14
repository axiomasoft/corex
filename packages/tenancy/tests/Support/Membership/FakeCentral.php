<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Tests\Support\Membership;

use CoreX\Tenancy\Central\MembershipQuery;
use CoreX\Tenancy\Membership\Internal\Transport\StrictJson;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/** Disposable fake: no endpoint/central writer is installed by this fixture. */
final class FakeCentral
{
    /** @var array<string, array{digest: string, receipt: array<string, mixed>}> */
    private array $receipts = [];

    public int $transitions = 0;

    public function __construct(private readonly TransportFixture $fixture) {}

    public function __invoke(Request $request): mixed
    {
        if ($request->method() === 'GET') {
            $query = MembershipQuery::fromWireQuery((string) parse_url($request->url(), PHP_URL_QUERY));

            if ($request->body() !== '' || ! $request->hasHeader('Cache-Control', 'no-store') || ! $request->hasHeader('X-CoreX-Membership-Profile', 'corex-membership/1')) {
                return Http::response(status: 400);
            }

            return Http::response([...$this->fixture->snapshot($query->requestId), 'state' => 'revoked', 'membership_id' => null], headers: TransportFixture::headers());
        }
        $p = StrictJson::object($request->body());
        $id = $p['request_id'];
        $digest = hash('sha256', json_encode($p, JSON_THROW_ON_ERROR));

        if (isset($this->receipts[$id])) {
            return $this->receipts[$id]['digest'] === $digest
                ? Http::response($this->receipts[$id]['receipt'], headers: TransportFixture::headers())
                : Http::response(status: 409);
        }
        $this->transitions++;
        $receipt = [...$this->fixture->receipt(), 'request_id' => $id, 'commit_ref' => 'commit:fixture-'.$this->transitions];
        $this->receipts[$id] = ['digest' => $digest, 'receipt' => $receipt];

        return Http::response($receipt, headers: TransportFixture::headers());
    }
}
