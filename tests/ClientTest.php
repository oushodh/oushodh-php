<?php

declare(strict_types=1);

namespace Oushodh\Tests;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Oushodh\Client;
use Oushodh\ErrorCode;
use Oushodh\Exception\AuthenticationException;
use Oushodh\Exception\BadRequestException;
use Oushodh\Exception\ConnectionException;
use Oushodh\Exception\NotFoundException;
use Oushodh\Exception\OushodhException;
use Oushodh\Exception\PlanException;
use Oushodh\Exception\RateLimitException;
use Oushodh\Exception\ServerException;
use Oushodh\MedicineType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

final class ClientTest extends TestCase
{
    private const KEY = 'oush_test_123';

    /** @var list<RequestInterface> */
    private array $history = [];

    /** @var list<int> */
    private array $sleeps = [];

    protected function tearDown(): void
    {
        putenv('OUSHODH_API_KEY');
    }

    public function testMetaSendsTheKeyAndUserAgent(): void
    {
        $client = $this->client([$this->json(['client' => 'acme', 'id_profile' => 'default', 'medicines' => 5000, 'last_import' => null])]);

        $meta = $client->meta();
        $request = $this->request(0);

        self::assertSame('GET', $request->getMethod());
        self::assertSame('https://api.oushodh.dev/v1/meta', (string) $request->getUri());
        self::assertSame('Bearer ' . self::KEY, $request->getHeaderLine('Authorization'));
        self::assertSame('oushodh-php/' . Client::VERSION, $request->getHeaderLine('User-Agent'));
        self::assertSame('acme', $meta->client);
        self::assertSame('default', $meta->idProfile);
        self::assertSame(5000, $meta->medicines);
        self::assertNull($meta->lastImport);
    }

    public function testListMedicinesSendsEveryParameter(): void
    {
        $client = $this->client([$this->json(['items' => [$this->medicine()], 'next' => 'abc', 'total' => 1])]);

        $page = $client->listMedicines(limit: 50, cursor: 'c1', updatedSince: '2026-11-08T09:30:00Z', q: 'napa');
        $request = $this->request(0);

        self::assertSame('/v1/medicines', $request->getUri()->getPath());
        self::assertSame(
            ['limit' => '50', 'cursor' => 'c1', 'updated_since' => '2026-11-08T09:30:00Z', 'q' => 'napa'],
            $this->query($request),
        );
        self::assertSame('abc', $page->next);
        self::assertSame(1, $page->total);
        self::assertSame('MED-012933', $page->items[0]->id);
    }

    public function testListMedicinesLeavesOutUnsetParameters(): void
    {
        $client = $this->client([$this->json(['items' => [], 'next' => null, 'total' => 0])]);

        $client->listMedicines();

        self::assertSame('', $this->request(0)->getUri()->getQuery());
    }

    public function testUpdatedSinceTakesADateTimeInUtc(): void
    {
        $client = $this->client([$this->json(['items' => [], 'next' => null, 'total' => 0])]);

        $client->listMedicines(updatedSince: new \DateTimeImmutable('2026-11-08T15:30:00+06:00'));

        self::assertSame('2026-11-08T09:30:00Z', $this->query($this->request(0))['updated_since']);
    }

    public function testMedicineFieldsAndUnknownFieldsAreKept(): void
    {
        $client = $this->client([$this->json(['items' => [$this->medicine(['new_field' => 'kept'])], 'next' => null, 'total' => 1])]);

        $medicine = $client->listMedicines()->items[0];

        self::assertSame('Napa 500 mg Tablet', $medicine->productName);
        self::assertSame(MedicineType::Allopathic, $medicine->medicineType);
        self::assertTrue($medicine->dgdaListed);
        self::assertSame(1.2, $medicine->price);
        self::assertSame('BDT', $medicine->prices->currency);
        self::assertSame(1.2, $medicine->prices->unit?->amount);
        self::assertSame(10.0, $medicine->prices->strip?->units);
        self::assertSame("30's pack", $medicine->prices->packs[0]->label);
        self::assertNull($medicine->prices->packs[0]->units);
        self::assertSame('15 gm in Tube Pack', $medicine->unitPrices[0]->size);
        self::assertSame('kept', $medicine->raw['new_field']);
    }

    public function testSearchSendsQAndLimit(): void
    {
        $client = $this->client([$this->json(['items' => [$this->medicine()], 'total' => 7])]);

        $result = $client->search('napa', 5);
        $request = $this->request(0);

        self::assertSame('/v1/medicines/search', $request->getUri()->getPath());
        self::assertSame(['q' => 'napa', 'limit' => '5'], $this->query($request));
        self::assertSame(7, $result->total);
        self::assertCount(1, $result->items);
    }

    public function testGetMedicineReadsTheMonograph(): void
    {
        $monograph = ['source' => 'source_a', 'indications' => 'Fever', 'composition' => null, 'pharmacology' => null,
            'dosage' => null, 'interactions' => null, 'contraindications' => null, 'side_effects' => null,
            'pregnancy_lactation' => null, 'precautions' => null, 'overdose' => null, 'storage' => null];
        $client = $this->client([$this->json($this->medicine(['monograph' => $monograph]))]);

        $medicine = $client->getMedicine('MED 1/2');

        self::assertSame('/v1/medicines/MED%201%2F2', $this->request(0)->getUri()->getPath());
        self::assertSame('source_a', $medicine->monograph?->source);
        self::assertSame('Fever', $medicine->monograph->indications);
    }

    public function testGetMedicineWithNoMonograph(): void
    {
        $client = $this->client([$this->json($this->medicine(['monograph' => null]))]);

        self::assertNull($client->getMedicine('MED-012933')->monograph);
    }

    public function testMedicinesPagesToTheEnd(): void
    {
        $client = $this->client([
            $this->json(['items' => [$this->medicine(['id' => 'A']), $this->medicine(['id' => 'B'])], 'next' => 'p2', 'total' => 3]),
            $this->json(['items' => [$this->medicine(['id' => 'C'])], 'next' => null, 'total' => 3]),
        ]);

        $ids = [];

        foreach ($client->medicines(limit: 2, updatedSince: '2026-11-08T09:30:00Z') as $medicine) {
            $ids[] = $medicine->id;
        }

        self::assertSame(['A', 'B', 'C'], $ids);
        self::assertCount(2, $this->history);
        self::assertArrayNotHasKey('cursor', $this->query($this->request(0)));
        self::assertSame(
            ['limit' => '2', 'cursor' => 'p2', 'updated_since' => '2026-11-08T09:30:00Z'],
            $this->query($this->request(1)),
        );
    }

    /**
     * @param class-string<OushodhException> $class
     */
    #[DataProvider('errors')]
    public function testErrorFamilies(int $status, string $code, string $class): void
    {
        $client = $this->client([$this->error($status, $code)], maxRetries: 0);

        try {
            $client->getMedicine('MED-1');
            self::fail('No exception');
        } catch (OushodhException $e) {
            self::assertInstanceOf($class, $e);
            self::assertSame($status, $e->status);
            self::assertSame(ErrorCode::from($code), $e->errorCode);
            self::assertSame('Something about ' . $code, $e->getMessage());
        }
    }

    /**
     * @return iterable<string, array{int, string, class-string<OushodhException>}>
     */
    public static function errors(): iterable
    {
        yield '400' => [400, 'bad_request', BadRequestException::class];
        yield '401' => [401, 'unauthorized', AuthenticationException::class];
        yield '403 plan_expired' => [403, 'plan_expired', PlanException::class];
        yield '403 plan_limit' => [403, 'plan_limit', PlanException::class];
        yield '404' => [404, 'not_found', NotFoundException::class];
        yield '429' => [429, 'rate_limited', RateLimitException::class];
        yield '500' => [500, 'internal', ServerException::class];
    }

    public function testAnErrorBodyThatIsNotJson(): void
    {
        $client = $this->client([new Response(502, [], '<html>Bad gateway</html>')], maxRetries: 0);

        try {
            $client->meta();
            self::fail('No exception');
        } catch (ServerException $e) {
            self::assertSame(502, $e->status);
            self::assertNull($e->errorCode);
        }
    }

    public function testRateLimitWaitsRetryAfterThenSucceeds(): void
    {
        $client = $this->client([
            $this->error(429, 'rate_limited', ['Retry-After' => '3']),
            $this->json(['client' => 'acme', 'id_profile' => 'default', 'medicines' => 1, 'last_import' => null]),
        ]);

        self::assertSame('acme', $client->meta()->client);
        self::assertSame([3000], $this->sleeps);
        self::assertCount(2, $this->history);
    }

    public function testRateLimitGivesUpAfterMaxRetries(): void
    {
        $client = $this->client([
            $this->error(429, 'rate_limited', ['Retry-After' => '1']),
            $this->error(429, 'rate_limited', ['Retry-After' => '1']),
            $this->error(429, 'rate_limited', ['Retry-After' => '7']),
        ]);

        try {
            $client->meta();
            self::fail('No exception');
        } catch (RateLimitException $e) {
            self::assertSame(7, $e->retryAfter);
            self::assertSame(ErrorCode::RateLimited, $e->errorCode);
        }

        self::assertSame([1000, 1000], $this->sleeps);
        self::assertCount(3, $this->history);
    }

    public function testQuotaExceededIsNotRetried(): void
    {
        $client = $this->client([$this->error(429, 'quota_exceeded', ['Retry-After' => '86400'])]);

        try {
            $client->meta();
            self::fail('No exception');
        } catch (RateLimitException $e) {
            self::assertSame(ErrorCode::QuotaExceeded, $e->errorCode);
            self::assertSame(86400, $e->retryAfter);
        }

        self::assertSame([], $this->sleeps);
        self::assertCount(1, $this->history);
    }

    public function testServerErrorIsRetriedThenSucceeds(): void
    {
        $client = $this->client([
            $this->error(500, 'internal'),
            $this->error(503, 'internal'),
            $this->json(['client' => 'acme', 'id_profile' => 'default', 'medicines' => 1, 'last_import' => null]),
        ]);

        self::assertSame('acme', $client->meta()->client);
        self::assertCount(2, $this->sleeps);
        self::assertGreaterThanOrEqual(500, $this->sleeps[0]);
        self::assertLessThanOrEqual(750, $this->sleeps[0]);
        self::assertGreaterThanOrEqual(1000, $this->sleeps[1]);
        self::assertLessThanOrEqual(1250, $this->sleeps[1]);
    }

    public function testNotFoundIsNotRetried(): void
    {
        $client = $this->client([$this->error(404, 'not_found')]);

        $this->expectException(NotFoundException::class);

        try {
            $client->getMedicine('MED-999999');
        } finally {
            self::assertCount(1, $this->history);
            self::assertSame([], $this->sleeps);
        }
    }

    public function testNetworkErrorIsRetriedThenThrown(): void
    {
        $fail = static fn(RequestInterface $request) => new ConnectException('Connection timed out', $request);
        $client = $this->client([$fail, $fail, $fail]);

        try {
            $client->meta();
            self::fail('No exception');
        } catch (ConnectionException $e) {
            self::assertNull($e->status);
            self::assertStringContainsString('Connection timed out', $e->getMessage());
        }

        self::assertCount(3, $this->history);
        self::assertCount(2, $this->sleeps);
    }

    public function testImagesAreFetchedWithoutTheKey(): void
    {
        $file = str_repeat('a', 64) . '.jpg';
        $client = $this->client([new Response(200, ['Content-Type' => 'image/jpeg'], "\xFF\xD8\xFFjpeg")]);

        $bytes = $client->getImage($file);
        $request = $this->request(0);

        self::assertSame("\xFF\xD8\xFFjpeg", $bytes);
        self::assertSame('https://api.oushodh.dev/v1/images/' . $file, (string) $request->getUri());
        self::assertFalse($request->hasHeader('Authorization'));
        self::assertSame('https://api.oushodh.dev/v1/images/' . $file, $client->imageUrl($file));
    }

    public function testMissingImageIsNotFound(): void
    {
        $client = $this->client([$this->error(404, 'not_found')]);

        $this->expectException(NotFoundException::class);

        $client->getImage('missing.jpg');
    }

    public function testTheKeyNeverAppearsInErrorText(): void
    {
        $echo = new Response(400, [], (string) json_encode(['error' => ['code' => 'bad_request', 'message' => 'Bad key ' . self::KEY]]));
        $fail = static fn(RequestInterface $request) => new ConnectException('Failed for ' . self::KEY, $request);
        $client = $this->client([$echo, $fail], maxRetries: 0);

        foreach ([fn() => $client->meta(), fn() => $client->meta()] as $call) {
            try {
                $call();
                self::fail('No exception');
            } catch (OushodhException $e) {
                self::assertStringNotContainsString(self::KEY, $e->getMessage());
                self::assertStringNotContainsString(self::KEY, (string) $e);
                self::assertNull($e->getPrevious());
            }
        }

        self::assertStringNotContainsString(self::KEY, print_r($client, true));
    }

    public function testTheKeyComesFromTheEnvironment(): void
    {
        putenv('OUSHODH_API_KEY=oush_test_env');
        $client = $this->client([$this->json(['client' => 'acme', 'id_profile' => 'default', 'medicines' => 1, 'last_import' => null])], key: null);

        $client->meta();

        self::assertSame('Bearer oush_test_env', $this->request(0)->getHeaderLine('Authorization'));
    }

    public function testNoKeyFailsAtConstruction(): void
    {
        putenv('OUSHODH_API_KEY');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('OUSHODH_API_KEY');

        new Client();
    }

    public function testBaseUrlAndAGuzzleClient(): void
    {
        $mock = new MockHandler([$this->json(['client' => 'acme', 'id_profile' => 'default', 'medicines' => 1, 'last_import' => null])]);
        $stack = HandlerStack::create($mock);

        $stack->push($this->recorder());

        $client = new Client(self::KEY, 'http://localhost:8788/', http: new \GuzzleHttp\Client(['handler' => $stack]));

        $client->meta();

        self::assertSame('http://localhost:8788/v1/meta', (string) $this->request(0)->getUri());
    }

    /**
     * @param list<Response|callable> $responses
     */
    private function client(array $responses, int $maxRetries = 2, ?string $key = self::KEY): Client
    {
        $stack = HandlerStack::create(new MockHandler($responses));

        $stack->push($this->recorder());

        $record = function (int $milliseconds): void {
            $this->sleeps[] = $milliseconds;
        };

        return new class ($key, $maxRetries, $stack, $record) extends Client {
            public function __construct(?string $key, int $maxRetries, HandlerStack $http, private \Closure $record)
            {
                parent::__construct($key, maxRetries: $maxRetries, http: $http);
            }

            protected function sleep(int $milliseconds): void
            {
                ($this->record)($milliseconds);
            }
        };
    }

    private function recorder(): callable
    {
        return Middleware::tap(function (RequestInterface $request): void {
            $this->history[] = $request;
        });
    }

    private function request(int $index): RequestInterface
    {
        return $this->history[$index];
    }

    /**
     * @return array<string, string>
     */
    private function query(RequestInterface $request): array
    {
        parse_str($request->getUri()->getQuery(), $query);

        /** @var array<string, string> $query */
        return $query;
    }

    /**
     * @param array<string, mixed> $body
     */
    private function json(array $body): Response
    {
        return new Response(200, ['Content-Type' => 'application/json'], (string) json_encode($body));
    }

    /**
     * @param array<string, string> $headers
     */
    private function error(int $status, string $code, array $headers = []): Response
    {
        $body = ['error' => ['code' => $code, 'message' => 'Something about ' . $code]];

        return new Response($status, ['Content-Type' => 'application/json'] + $headers, (string) json_encode($body));
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private function medicine(array $overrides = []): array
    {
        return $overrides + [
            'id' => 'MED-012933',
            'uuid' => '01a1ba6f-f4d6-7143-ac84-9376f520eef8',
            'brand' => 'Napa',
            'category' => null,
            'product_name' => 'Napa 500 mg Tablet',
            'product_image' => null,
            'images' => [],
            'unit' => 'Strip',
            'size' => '10 Tablets',
            'price' => 1.2,
            'price_per' => 'unit',
            'price_source' => 'dgda_price',
            'unit_prices' => [['size' => '15 gm in Tube Pack', 'price' => 110]],
            'prices' => [
                'currency' => 'BDT',
                'unit' => ['amount' => 1.2, 'source' => 'dgda_price'],
                'sizes' => [],
                'strip' => ['amount' => 12, 'units' => 10, 'source' => 'dgda_price'],
                'packs' => [['amount' => 36, 'units' => null, 'label' => "30's pack", 'source' => 'dgda_price']],
            ],
            'description_en' => null,
            'description_bn' => null,
            'dosage_form' => 'Tablet',
            'strength' => '500 mg',
            'generic_name' => 'Paracetamol',
            'manufacturer' => null,
            'source_url' => null,
            'last_updated' => '2026-11-09T15:27:34Z',
            'dgda_listed' => true,
            'medicine_type' => 'allopathic',
        ];
    }
}
