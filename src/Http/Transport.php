<?php

declare(strict_types=1);

namespace Oushodh\Http;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\TransferException;
use Oushodh\ErrorCode;
use Oushodh\Exception\AuthenticationException;
use Oushodh\Exception\BadRequestException;
use Oushodh\Exception\ConnectionException;
use Oushodh\Exception\NotFoundException;
use Oushodh\Exception\OushodhException;
use Oushodh\Exception\PlanException;
use Oushodh\Exception\RateLimitException;
use Oushodh\Exception\ServerException;
use Psr\Http\Message\ResponseInterface;

/**
 * Sends each GET with the key, the User-Agent and the timeout, and retries it.
 *
 * @internal
 */
final class Transport
{
    /**
     * @param \Closure(int): void $sleep waits that many milliseconds before a retry
     */
    public function __construct(
        private ClientInterface $http,
        #[\SensitiveParameter]
        private string $apiKey,
        private string $userAgent,
        private float $timeout,
        private int $maxRetries,
        private \Closure $sleep,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return ['timeout' => $this->timeout, 'maxRetries' => $this->maxRetries];
    }

    /**
     * @param array<string, int|string|null> $query
     */
    public function get(string $url, array $query, bool $withKey): ResponseInterface
    {
        $headers = ['User-Agent' => $this->userAgent, 'Accept' => 'application/json'];

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
                    ($this->sleep)($this->backoff($attempt));

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

            ($this->sleep)($wait);
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
