<?php

declare(strict_types=1);

namespace Oushodh;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\TransferException;
use Oushodh\Exception\AuthenticationException;
use Oushodh\Exception\BadRequestException;
use Oushodh\Exception\ConnectionException;
use Oushodh\Exception\NotFoundException;
use Oushodh\Exception\OushodhException;
use Oushodh\Exception\PlanException;
use Oushodh\Exception\RateLimitException;
use Oushodh\Exception\ServerException;
use Oushodh\Model\Medicine;
use Oushodh\Model\MedicineDetail;
use Oushodh\Model\Meta;
use Oushodh\Model\Page;
use Oushodh\Model\SearchResult;
use Psr\Http\Message\ResponseInterface;

/**
 * A client for the Oushodh API.
 */
class Client
{
    public const VERSION = '0.1.0';
    public const DEFAULT_BASE_URL = 'https://api.oushodh.dev';

    private string $apiKey;
    private string $baseUrl;
    private ClientInterface $http;

    /**
     * @param string|null                   $apiKey     your key; when null, the OUSHODH_API_KEY env var
     * @param float                         $timeout    seconds to wait for one answer
     * @param int                           $maxRetries retries after a 429, a 5xx or a network error
     * @param ClientInterface|callable|null $http       a Guzzle client, or a Guzzle handler, such as a MockHandler
     */
    public function __construct(
        #[\SensitiveParameter]
        ?string $apiKey = null,
        string $baseUrl = self::DEFAULT_BASE_URL,
        private float $timeout = 30.0,
        private int $maxRetries = 2,
        ClientInterface|callable|null $http = null,
    ) {
        $key = $apiKey ?? getenv('OUSHODH_API_KEY');

        if (!\is_string($key) || $key === '') {
            throw new \InvalidArgumentException('No API key. Pass apiKey, or set the OUSHODH_API_KEY env var.');
        }

        $this->apiKey = $key;
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->http = match (true) {
            $http instanceof ClientInterface => $http,
            $http !== null => new GuzzleClient(['handler' => $http]),
            default => new GuzzleClient(),
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return ['baseUrl' => $this->baseUrl, 'timeout' => $this->timeout, 'maxRetries' => $this->maxRetries];
    }

    /**
     * The account the key belongs to. Use it to check that a key works.
     */
    public function meta(): Meta
    {
        return new Meta($this->json('/v1/meta'));
    }

    /**
     * One page of medicines, in the order of their permanent number.
     *
     * @param int|null                      $limit        medicines on one page, 1 to 500
     * @param string|null                   $cursor       the `next` value from the page before
     * @param \DateTimeInterface|string|null $updatedSince only medicines changed at or after this time
     * @param string|null                   $q            only medicines whose brand, generic or manufacturer starts with these words
     */
    public function listMedicines(
        ?int $limit = null,
        ?string $cursor = null,
        \DateTimeInterface|string|null $updatedSince = null,
        ?string $q = null,
    ): Page {
        if ($updatedSince instanceof \DateTimeInterface) {
            $updatedSince = \DateTimeImmutable::createFromInterface($updatedSince)
                ->setTimezone(new \DateTimeZone('UTC'))
                ->format('Y-m-d\TH:i:s\Z');
        }

        $query = ['limit' => $limit, 'cursor' => $cursor, 'updated_since' => $updatedSince, 'q' => $q];

        return new Page($this->json('/v1/medicines', $query));
    }

    /**
     * Every medicine, page by page, following `next` to the last page.
     *
     * @return \Generator<int, Medicine>
     */
    public function medicines(
        ?int $limit = null,
        ?string $cursor = null,
        \DateTimeInterface|string|null $updatedSince = null,
        ?string $q = null,
    ): \Generator {
        do {
            $page = $this->listMedicines($limit, $cursor, $updatedSince, $q);

            foreach ($page->items as $medicine) {
                yield $medicine;
            }

            $cursor = $page->next;
        } while ($cursor !== null);
    }

    /**
     * The best matches for a person typing a name, best match first.
     *
     * @param int|null $limit most medicines to return, 1 to 50
     */
    public function search(string $q, ?int $limit = null): SearchResult
    {
        return new SearchResult($this->json('/v1/medicines/search', ['q' => $q, 'limit' => $limit]));
    }

    /**
     * One medicine, with its monograph, by its ID in any profile, an old ID or its UUID.
     */
    public function getMedicine(string $ref): MedicineDetail
    {
        return new MedicineDetail($this->json('/v1/medicines/' . rawurlencode($ref)));
    }

    /**
     * The address of a picture, such as `<sha256>.jpg`. It needs no key.
     */
    public function imageUrl(string $file): string
    {
        return $this->baseUrl . '/v1/images/' . rawurlencode($file);
    }

    /**
     * The bytes of a picture (a JPEG). The request sends no key.
     */
    public function getImage(string $file): string
    {
        return (string) $this->send($this->imageUrl($file), [], false)->getBody();
    }

    /**
     * Waits before a retry. Tests override it.
     */
    protected function sleep(int $milliseconds): void
    {
        usleep($milliseconds * 1000);
    }

    /**
     * @param array<string, int|string|null> $query
     *
     * @return array<string, mixed>
     */
    private function json(string $path, array $query = []): array
    {
        $body = (string) $this->send($this->baseUrl . $path, $query, true)->getBody();

        try {
            $data = json_decode($body, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new OushodhException('The API answered with a body that is not JSON.', null, null, $e);
        }

        if (!\is_array($data)) {
            throw new OushodhException('The API answered with a body that is not a JSON object.');
        }

        /** @var array<string, mixed> $data */
        return $data;
    }

    /**
     * @param array<string, int|string|null> $query
     */
    private function send(string $url, array $query, bool $withKey): ResponseInterface
    {
        $headers = ['User-Agent' => 'oushodh-php/' . self::VERSION, 'Accept' => 'application/json'];

        if ($withKey) {
            $headers['Authorization'] = 'Bearer ' . $this->apiKey;
        }

        $options = [
            'headers' => $headers,
            'query' => array_filter($query, static fn($value) => $value !== null),
            'timeout' => $this->timeout,
            'http_errors' => false,
        ];

        for ($attempt = 0; ; $attempt++) {
            $canRetry = $attempt < $this->maxRetries;

            try {
                $response = $this->http->request('GET', $url, $options);
            } catch (TransferException $e) {
                if ($canRetry) {
                    $this->sleep($this->backoff($attempt));

                    continue;
                }

                throw new ConnectionException($this->redact('No answer from the Oushodh API: ' . $e->getMessage()));
            }

            $status = $response->getStatusCode();

            if ($status < 400) {
                return $response;
            }

            $error = $this->error($response);
            $retryable = $status >= 500
                || ($error instanceof RateLimitException && $error->errorCode === ErrorCode::RateLimited);

            if (!$canRetry || !$retryable) {
                throw $error;
            }

            $wait = $error instanceof RateLimitException && $error->retryAfter !== null
                ? $error->retryAfter * 1000
                : $this->backoff($attempt);

            $this->sleep($wait);
        }
    }

    private function backoff(int $attempt): int
    {
        return 500 * 2 ** $attempt + random_int(0, 250);
    }

    private function error(ResponseInterface $response): OushodhException
    {
        $status = $response->getStatusCode();
        $data = json_decode((string) $response->getBody(), true);
        $error = \is_array($data) && \is_array($data['error'] ?? null) ? $data['error'] : [];
        $code = \is_string($error['code'] ?? null) ? ErrorCode::tryFrom($error['code']) : null;
        $message = \is_string($error['message'] ?? null) ? $error['message'] : 'HTTP ' . $status;
        $message = $this->redact($message);

        if ($status === 429) {
            $retryAfter = $response->getHeaderLine('Retry-After');

            return new RateLimitException($message, $code, ctype_digit($retryAfter) ? (int) $retryAfter : null);
        }

        return match (true) {
            $status === 400 => new BadRequestException($message, $status, $code),
            $status === 401 => new AuthenticationException($message, $status, $code),
            $status === 403 => new PlanException($message, $status, $code),
            $status === 404 => new NotFoundException($message, $status, $code),
            $status >= 500 => new ServerException($message, $status, $code),
            default => new OushodhException($message, $status, $code),
        };
    }

    private function redact(string $text): string
    {
        return str_replace($this->apiKey, '[redacted]', $text);
    }
}
