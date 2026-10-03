<?php

declare(strict_types=1);

namespace CoreX\Webhooks\Internal;

use CurlHandle;

final class FakeCurlHandle
{
    /** @var array<int, mixed> */
    public array $options = [];
}

final class CurlRuntimeFixture
{
    public static bool $enabled = false;

    public static int $status = 202;

    public static string $peerAddress = '93.184.216.34';

    /** @var list<string> */
    public static array $responseHeaders = ["HTTP/1.1 202 Accepted\r\n", "Content-Length: 0\r\n", "\r\n"];

    public static string $responseBody = '';

    public static int $errno = 0;

    public static int $executions = 0;

    /** @var array<int, mixed> */
    public static array $capturedOptions = [];

    public static function reset(): void
    {
        self::$enabled = true;
        self::$status = 202;
        self::$peerAddress = '93.184.216.34';
        self::$responseHeaders = ["HTTP/1.1 202 Accepted\r\n", "Content-Length: 0\r\n", "\r\n"];
        self::$responseBody = '';
        self::$errno = 0;
        self::$executions = 0;
        self::$capturedOptions = [];
    }
}

function curl_init(?string $url = null): CurlHandle|FakeCurlHandle|false
{
    if (! CurlRuntimeFixture::$enabled) {
        return \curl_init($url);
    }

    return new FakeCurlHandle;
}

/** @param array<int, mixed> $options */
function curl_setopt_array(CurlHandle|FakeCurlHandle $handle, array $options): bool
{
    if ($handle instanceof CurlHandle) {
        return \curl_setopt_array($handle, $options);
    }

    $handle->options = $options;
    CurlRuntimeFixture::$capturedOptions = $options;

    return true;
}

function curl_exec(CurlHandle|FakeCurlHandle $handle): string|bool
{
    if ($handle instanceof CurlHandle) {
        return \curl_exec($handle);
    }

    CurlRuntimeFixture::$executions++;
    $headerCallback = $handle->options[CURLOPT_HEADERFUNCTION];
    $writeCallback = $handle->options[CURLOPT_WRITEFUNCTION];

    foreach (CurlRuntimeFixture::$responseHeaders as $line) {
        if ($headerCallback($handle, $line) !== strlen($line)) {
            CurlRuntimeFixture::$errno = CURLE_WRITE_ERROR;

            return false;
        }
    }

    if (CurlRuntimeFixture::$responseBody !== ''
        && $writeCallback($handle, CurlRuntimeFixture::$responseBody) !== strlen(CurlRuntimeFixture::$responseBody)) {
        CurlRuntimeFixture::$errno = CURLE_WRITE_ERROR;

        return false;
    }

    return true;
}

function curl_getinfo(CurlHandle|FakeCurlHandle $handle, ?int $option = null): mixed
{
    if ($handle instanceof CurlHandle) {
        return $option === null ? \curl_getinfo($handle) : \curl_getinfo($handle, $option);
    }

    return match ($option) {
        CURLINFO_RESPONSE_CODE => CurlRuntimeFixture::$status,
        CURLINFO_PRIMARY_IP => CurlRuntimeFixture::$peerAddress,
        default => null,
    };
}

function curl_errno(CurlHandle|FakeCurlHandle $handle): int
{
    return $handle instanceof CurlHandle ? \curl_errno($handle) : CurlRuntimeFixture::$errno;
}

function curl_close(CurlHandle|FakeCurlHandle $handle): void
{
    if ($handle instanceof CurlHandle) {
        \curl_close($handle);
    }
}

namespace CoreX\Webhooks\Tests\Feature;

use CoreX\Webhooks\Data\ValidatedWebhookRequest;
use CoreX\Webhooks\Enums\WebhookFailureReason;
use CoreX\Webhooks\Internal\CurlRuntimeFixture;
use CoreX\Webhooks\Internal\CurlWebhookTransport;
use CoreX\Webhooks\Tests\TestCase;

final class WebhookTransportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        CurlRuntimeFixture::reset();
    }

    protected function tearDown(): void
    {
        CurlRuntimeFixture::$enabled = false;

        parent::tearDown();
    }

    public function test_local_receiver_fixture_observes_exact_wire_bytes_and_pinned_tls_host(): void
    {
        CurlRuntimeFixture::$responseHeaders = [
            "HTTP/1.1 202 Accepted\r\n",
            "Retry-After: 30\r\n",
            "Content-Length: 2\r\n",
            "\r\n",
        ];
        CurlRuntimeFixture::$responseBody = 'ok';

        $result = (new CurlWebhookTransport)->send($this->request());
        $options = CurlRuntimeFixture::$capturedOptions;

        $this->assertSame(202, $result->status);
        $this->assertSame('30', $result->retryAfter);
        $this->assertNull($result->errorCode);
        $this->assertGreaterThanOrEqual(0, $result->durationMs);
        $this->assertSame(1, CurlRuntimeFixture::$executions);
        $this->assertSame('https://hooks.example.com/deliver', $options[CURLOPT_URL]);
        $this->assertSame('{"event":"created"}', $options[CURLOPT_POSTFIELDS]);
        $this->assertSame([
            'Content-Type: application/cloudevents+json',
            'Webhook-Id: delivery_123',
            'Webhook-Timestamp: 1700000000',
            'Webhook-Signature: v1,YWFhYWFhYWFhYWFhYWFhYWFhYWFhYWFhYWFhYWFhYWE=',
        ], $options[CURLOPT_HTTPHEADER]);
        $this->assertSame(['hooks.example.com:443:93.184.216.34'], $options[CURLOPT_RESOLVE]);
        $this->assertTrue($options[CURLOPT_SSL_VERIFYPEER]);
        $this->assertSame(2, $options[CURLOPT_SSL_VERIFYHOST]);
        $this->assertSame(CURLPROTO_HTTPS, $options[CURLOPT_PROTOCOLS]);
        $this->assertSame('', $options[CURLOPT_PROXY]);
        $this->assertSame('*', $options[CURLOPT_NOPROXY]);
        $this->assertSame(3000, $options[CURLOPT_CONNECTTIMEOUT_MS]);
        $this->assertSame(10_000, $options[CURLOPT_TIMEOUT_MS]);
        $this->assertFalse($options[CURLOPT_FOLLOWLOCATION]);
    }

    public function test_loopback_tls_receiver_preserves_hostname_and_exact_wire_body(): void
    {
        $directory = sys_get_temp_dir().'/corex-webhook-'.bin2hex(random_bytes(6));
        $this->assertTrue(mkdir($directory, 0700));
        $configPath = $directory.'/openssl.cnf';
        $certificatePath = $directory.'/receiver.pem';
        file_put_contents($configPath, <<<'CONF'
[req]
distinguished_name = subject
prompt = no
req_extensions = extensions
x509_extensions = extensions

[subject]
CN = hooks.example.com

[extensions]
subjectAltName = DNS:hooks.example.com
basicConstraints = critical,CA:TRUE
keyUsage = critical,digitalSignature,keyEncipherment,keyCertSign
CONF);

        $key = openssl_pkey_new([
            'config' => $configPath,
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        $this->assertNotFalse($key);
        $csr = openssl_csr_new(
            ['commonName' => 'hooks.example.com'],
            $key,
            ['config' => $configPath, 'req_extensions' => 'extensions', 'digest_alg' => 'sha256'],
        );
        $this->assertNotFalse($csr);
        $certificate = openssl_csr_sign(
            $csr,
            null,
            $key,
            1,
            ['config' => $configPath, 'x509_extensions' => 'extensions', 'digest_alg' => 'sha256'],
            1,
        );
        $this->assertNotFalse($certificate);
        $this->assertTrue(openssl_x509_export($certificate, $certificatePem));
        $this->assertTrue(openssl_pkey_export($key, $keyPem, null, ['config' => $configPath]));
        file_put_contents($certificatePath, $certificatePem.$keyPem);

        $reservation = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
        $this->assertIsResource($reservation, $error);
        $address = stream_socket_get_name($reservation, false);
        $this->assertIsString($address);
        $port = (int) substr(strrchr($address, ':'), 1);
        fclose($reservation);

        $serverScript = <<<'PHP'
$context = stream_context_create(['ssl' => [
    'local_cert' => $argv[1],
    'verify_peer' => false,
    'allow_self_signed' => true,
    'crypto_method' => STREAM_CRYPTO_METHOD_TLS_SERVER,
]]);
$server = stream_socket_server('tls://127.0.0.1:'.$argv[2], $errno, $error, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN, $context);
if ($server === false) {
    fwrite(STDERR, $error);
    exit(2);
}
echo "READY\n";
flush();
$connection = stream_socket_accept($server, 5);
if ($connection === false) {
    fwrite(STDERR, 'accept timeout');
    exit(3);
}
stream_set_timeout($connection, 5);
$request = '';
while (!str_contains($request, "\r\n\r\n")) {
    $chunk = fread($connection, 4096);
    if ($chunk === false || $chunk === '') {
        break;
    }
    $request .= $chunk;
}
[$head] = explode("\r\n\r\n", $request, 2);
preg_match('/\r\nContent-Length:\s*(\d+)/i', "\r\n".$head, $matches);
$length = (int) ($matches[1] ?? 0);
$body = substr($request, strlen($head) + 4);
while (strlen($body) < $length) {
    $chunk = fread($connection, $length - strlen($body));
    if ($chunk === false || $chunk === '') {
        break;
    }
    $body .= $chunk;
}
fwrite($connection, "HTTP/1.1 202 Accepted\r\nContent-Length: 0\r\nConnection: close\r\n\r\n");
fclose($connection);
fclose($server);
echo base64_encode($head."\r\n\r\n".$body);
PHP;
        $process = proc_open(
            [PHP_BINARY, '-r', $serverScript, $certificatePath, (string) $port],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
        );
        $this->assertIsResource($process);

        try {
            fclose($pipes[0]);
            $this->assertSame("READY\n", fgets($pipes[1]));
            $handle = \curl_init('https://hooks.example.com/deliver');
            $this->assertInstanceOf(\CurlHandle::class, $handle);
            \curl_setopt_array($handle, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => '{"event":"created"}',
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/cloudevents+json',
                    'Webhook-Id: delivery_123',
                ],
                CURLOPT_CONNECT_TO => ["hooks.example.com:443:127.0.0.1:{$port}"],
                CURLOPT_CAINFO => $certificatePath,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_PROXY => '',
                CURLOPT_NOPROXY => '*',
                CURLOPT_CONNECTTIMEOUT_MS => 3000,
                CURLOPT_TIMEOUT_MS => 5000,
                CURLOPT_RETURNTRANSFER => true,
            ]);

            $this->assertSame('', \curl_exec($handle), \curl_error($handle));
            $this->assertSame(202, \curl_getinfo($handle, CURLINFO_RESPONSE_CODE));
            \curl_close($handle);

            $captured = base64_decode(stream_get_contents($pipes[1]), true);
            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $this->assertSame(0, proc_close($process), $stderr);
            $this->assertIsString($captured);
            $this->assertStringStartsWith("POST /deliver HTTP/1.1\r\nHost: hooks.example.com", $captured);
            $this->assertStringContainsString("\r\nWebhook-Id: delivery_123\r\n", $captured);
            $this->assertStringEndsWith("\r\n\r\n{\"event\":\"created\"}", $captured);
        } finally {
            if (is_resource($process)) {
                $status = proc_get_status($process);

                if ($status['running']) {
                    proc_terminate($process);
                }

                proc_close($process);
            }

            @unlink($certificatePath);
            @unlink($configPath);
            @rmdir($directory);
        }
    }

    public function test_redirect_is_returned_once_and_never_followed(): void
    {
        CurlRuntimeFixture::$status = 302;
        CurlRuntimeFixture::$responseHeaders = [
            "HTTP/1.1 302 Found\r\n",
            "Location: https://127.0.0.1/private\r\n",
            "Content-Length: 0\r\n",
            "\r\n",
        ];

        $result = (new CurlWebhookTransport)->send($this->request());

        $this->assertSame(302, $result->status);
        $this->assertNull($result->errorCode);
        $this->assertSame(1, CurlRuntimeFixture::$executions);
        $this->assertFalse(CurlRuntimeFixture::$capturedOptions[CURLOPT_FOLLOWLOCATION]);
        $this->assertSame(0, CurlRuntimeFixture::$capturedOptions[CURLOPT_MAXREDIRS]);
    }

    public function test_response_body_and_headers_have_hard_caps(): void
    {
        CurlRuntimeFixture::$responseBody = str_repeat('x', 64 * 1024 + 1);

        $bodyResult = (new CurlWebhookTransport)->send($this->request());

        $this->assertSame(WebhookFailureReason::PayloadTooLarge, $bodyResult->errorCode);

        CurlRuntimeFixture::reset();
        CurlRuntimeFixture::$responseHeaders = [
            "HTTP/1.1 202 Accepted\r\n",
            'X-Oversized: '.str_repeat('x', 16 * 1024)."\r\n",
            "\r\n",
        ];

        $headerResult = (new CurlWebhookTransport)->send($this->request());

        $this->assertSame(WebhookFailureReason::PayloadTooLarge, $headerResult->errorCode);
    }

    public function test_socket_peer_must_equal_the_prevalidated_pin(): void
    {
        CurlRuntimeFixture::$peerAddress = '93.184.216.35';

        $result = (new CurlWebhookTransport)->send($this->request());

        $this->assertSame(WebhookFailureReason::AddressNotAllowed, $result->errorCode);
    }

    public function test_wrong_peer_takes_precedence_over_response_overflow(): void
    {
        CurlRuntimeFixture::$peerAddress = '93.184.216.35';
        CurlRuntimeFixture::$responseBody = str_repeat('x', 64 * 1024 + 1);
        $this->assertSame(WebhookFailureReason::AddressNotAllowed, (new CurlWebhookTransport)->send($this->request())->errorCode);
    }

    public function test_missing_curl_fails_closed_without_attempting_dns_or_network(): void
    {
        $root = dirname(__DIR__, 2);
        $script = <<<'PHP'
require $argv[1].'/src/Data/ValidatedWebhookRequest.php';
require $argv[1].'/src/Enums/WebhookFailureReason.php';
require $argv[1].'/src/Data/WebhookHttpResult.php';
require $argv[1].'/src/Contracts/WebhookHttpTransport.php';
require $argv[1].'/src/Internal/WebhookUrlPolicy.php';
require $argv[1].'/src/Internal/CurlWebhookTransport.php';
$request = new CoreX\Webhooks\Data\ValidatedWebhookRequest(
    'hooks.example.com',
    'https://hooks.example.com/deliver',
    '93.184.216.34',
    '{}',
    ['webhook-id' => 'delivery_123', 'webhook-timestamp' => '1700000000', 'webhook-signature' => 'v1,YWFhYWFhYWFhYWFhYWFhYWFhYWFhYWFhYWFhYWFhYWE='],
);
echo (new CoreX\Webhooks\Internal\CurlWebhookTransport)->send($request)->errorCode->value;
PHP;
        $process = proc_open(
            [PHP_BINARY, '-d', 'disable_functions=curl_init', '-r', $script, $root],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
        );

        $this->assertIsResource($process);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        $this->assertSame(0, $exitCode, $stderr);
        $this->assertSame('transport_failure', $stdout);
    }

    private function request(): ValidatedWebhookRequest
    {
        return new ValidatedWebhookRequest(
            canonicalHost: 'hooks.example.com',
            url: 'https://hooks.example.com/deliver',
            address: '93.184.216.34',
            body: '{"event":"created"}',
            signatureHeaders: [
                'webhook-id' => 'delivery_123',
                'webhook-timestamp' => '1700000000',
                'webhook-signature' => 'v1,YWFhYWFhYWFhYWFhYWFhYWFhYWFhYWFhYWFhYWFhYWE=',
            ],
        );
    }
}
