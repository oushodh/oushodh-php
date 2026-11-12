# Oushodh PHP SDK

The official PHP client for the Oushodh API.

[![CI](https://github.com/oushodh/oushodh-php/actions/workflows/ci.yml/badge.svg?branch=main)](https://github.com/oushodh/oushodh-php/actions/workflows/ci.yml)
[![Licence: MIT](https://img.shields.io/github/license/oushodh/oushodh-php)](LICENSE)
[![PHP 8.2+](https://img.shields.io/badge/php-8.2%2B-777bb4)](https://www.php.net/)

## What Oushodh is

Oushodh serves Bangladesh medicine data over a REST API: brands, generics, prices and makers. Make a key in the member
portal at [app.oushodh.dev](https://app.oushodh.dev), and read the docs at [oushodh.dev/docs](https://oushodh.dev/docs).

The SDK needs PHP 8.2 or newer. Its one runtime dependency is Guzzle 8.

## Install

Install it with Composer from Packagist:

```sh
composer require oushodh/oushodh-php
```

## Quickstart

Make a key on the Keys page of the member portal. A key starts with `oush_` and is shown once. Keep it on your
server: never put it in a web page or an app.

```sh
export OUSHODH_API_KEY=oush_...
```

```php
use Oushodh\Client;

$oushodh = new Client(); // reads OUSHODH_API_KEY

$result = $oushodh->search('paracetamol', limit: 5);

foreach ($result->items as $medicine) {
    echo $medicine->id, ' ', $medicine->productName, "\n";
}

$medicine = $oushodh->getMedicine('MED-012933');

echo $medicine->productName, ': ', $medicine->prices->unit?->amount, " BDT a unit\n";
```

`new Client('oush_...')` takes the key directly. With no key and no `OUSHODH_API_KEY`, it throws an
`InvalidArgumentException`.

| Method                                              | Route                           | Returns                   |
| --------------------------------------------------- | ------------------------------- | ------------------------- |
| `meta()`                                            | `GET /v1/meta`                  | `Meta`                    |
| `listMedicines(limit:, cursor:, updatedSince:, q:)` | `GET /v1/medicines`             | `Page`                    |
| `medicines(limit:, cursor:, updatedSince:, q:)`     | `GET /v1/medicines`, every page | `Generator` of `Medicine` |
| `search($q, limit:)`                                | `GET /v1/medicines/search`      | `SearchResult`            |
| `getMedicine($ref)`                                 | `GET /v1/medicines/{ref}`       | `MedicineDetail`          |
| `imageUrl($file)`                                   | none                            | the picture's address     |
| `getImage($file)`                                   | `GET /v1/images/{file}`         | the JPEG bytes            |

`meta()` names your account and counts the medicines your plan reads. Call it to check that a key works.

`getMedicine()` takes an ID in any profile, an old ID or a UUID. The answer always carries the medicine's current ID,
and its `monograph`.

Fields keep their API names in camel case: `product_name` is `$medicine->productName`. A value no source gives is
`null`. Every model also has `raw`, the object as the API sent it, so a field added after this version is still
there: `$medicine->raw['new_field']`.

## Paging

`listMedicines()` returns one page: `items`, `next` and `total`. Pass `next` back as `cursor` for the next page.
`next` is `null` on the last page. `limit` is 1 to 500, and 100 when you leave it out.

```php
$page = $oushodh->listMedicines(limit: 500);

while (true) {
    foreach ($page->items as $medicine) {
        // ...
    }

    if ($page->next === null) {
        break;
    }

    $page = $oushodh->listMedicines(limit: 500, cursor: $page->next);
}
```

`medicines()` does the same for you. It yields every medicine and follows `next` to the end:

```php
foreach ($oushodh->medicines(limit: 500) as $medicine) {
    echo $medicine->id, ' ', $medicine->productName, "\n";
}
```

Search has no cursor. To page through every match, use `medicines(q: ...)`.

## Keeping a copy in sync

Copy every medicine once, then fetch only what changed.

1. The first sync pages through every medicine and keeps the newest `lastUpdated`.
2. Each later sync passes that time as `updatedSince` and pages to the end. It gets the medicines changed at or after
   that time, oldest change first.

```php
$since = loadLastSync(); // null on the first sync

foreach ($oushodh->medicines(limit: 500, updatedSince: $since) as $medicine) {
    saveMedicine($medicine->uuid, $medicine);

    if ($since === null || $medicine->lastUpdated > $since) {
        $since = $medicine->lastUpdated;
    }
}

saveLastSync($since);
```

- `updatedSince` takes a string such as `2026-11-08T09:30:00Z`, or a `DateTimeInterface`. The client sends a
  `DateTimeInterface` in UTC, to the whole second.
- "At or after" means a change in the same second is never missed. The medicine that set the time comes back once
  more, so save by `uuid`.
- Store `uuid` as the key: it never changes. Show `id` to people.
- Medicines are never deleted and IDs are never reused, so a sync never has to remove one.

## Images

`productImage` is the address of the medicine's picture, or `null`. `images` lists every picture, `productImage`
first. An address under `/v1/images/` is a copy Oushodh keeps. It needs no key, so an `<img>` tag can show it, and it
never changes. Any other address belongs to the source, and it can move or be removed.

`getImage()` takes the file name, not the whole address, and sends no key:

```php
$url = $medicine->productImage;

if ($url !== null && str_starts_with($url, $oushodh->imageUrl(''))) {
    $jpeg = $oushodh->getImage(basename($url));
}
```

`imageUrl($file)` builds the address of a file, such as
`50d858e0985ecc7f60418aaf0cc5ab587f42c2570a884095a9e8ccacd0f6545c.jpg`.

## Errors

Every error is an `Oushodh\Exception\OushodhException`. It has `status`, the HTTP status, and `errorCode`, an
`Oushodh\ErrorCode`. Match on the class or on `errorCode`, never on the message: the message is for people and may
change.

| Class                     | Status | `errorCode`                      |
| ------------------------- | ------ | -------------------------------- |
| `BadRequestException`     | 400    | `BadRequest`                     |
| `AuthenticationException` | 401    | `Unauthorized`                   |
| `PlanException`           | 403    | `PlanExpired` or `PlanLimit`     |
| `NotFoundException`       | 404    | `NotFound`                       |
| `RateLimitException`      | 429    | `RateLimited` or `QuotaExceeded` |
| `ServerException`         | 5xx    | `Internal`                       |
| `ConnectionException`     | none   | none: no answer came back        |

When an error body is not JSON, `errorCode` is `null` and the message is `HTTP <status>`. The key never appears in an
error message.

```php
use Oushodh\ErrorCode;
use Oushodh\Exception\NotFoundException;
use Oushodh\Exception\PlanException;

try {
    $medicine = $oushodh->getMedicine('MED-999999');
} catch (NotFoundException) {
    // No medicine has that ID.
} catch (PlanException $e) {
    if ($e->errorCode === ErrorCode::PlanLimit) {
        // The medicine is beyond the medicines your plan reads.
    }
}
```

## Retries and rate limits

Your plan sets how many requests each key may make in a minute (60 a minute on Developer). It also sets how many
requests your account may make in a month. See [Rate limits](https://oushodh.dev/docs/rate-limits).

- Over the minute's limit, the API answers `429 rate_limited`. The `Retry-After` header says how many seconds to wait.
  The client waits that long and tries again, up to `maxRetries` times. After that it throws a `RateLimitException`
  with the seconds in `retryAfter`.
- When the month's requests are used up, the API answers `429 quota_exceeded` until the next month. The client never
  retries it. It throws a `RateLimitException` at once, and `retryAfter` counts the seconds to the next month.
- After a `5xx` or a network error, the client backs off and tries again, up to `maxRetries` times. It waits 0.5 s,
  then 1 s, and so on, plus a random part.
- The client never retries a `400`, `401`, `403` or `404`: the same request gets the same answer.

Every route is a `GET`, so a retry is always safe.

## Plan limits

Developer reads the first 5,000 medicines and Team the first 20,000, in the order of their permanent number. Agency
and Custom read all of them.

Lists and search leave out the medicines beyond your plan's number. A lookup by ID of such a medicine answers
`403 plan_limit`: the client throws a `PlanException` with `ErrorCode::PlanLimit`. An account with no active plan gets
`403 plan_expired` (`ErrorCode::PlanExpired`) on every call.

## Configuration

Pass options to the constructor as named arguments:

```php
$oushodh = new Client(apiKey: 'oush_...', timeout: 10.0, maxRetries: 3);
```

| Option       | Default                   | What it does                                                         |
| ------------ | ------------------------- | -------------------------------------------------------------------- |
| `apiKey`     | `OUSHODH_API_KEY`         | Your API key.                                                        |
| `baseUrl`    | `https://api.oushodh.dev` | Where the API is.                                                    |
| `timeout`    | `30.0`                    | Seconds to wait for one answer.                                      |
| `maxRetries` | `2`                       | Retries after a `429 rate_limited`, a `5xx` or a network error.      |
| `http`       | a new Guzzle client       | Your own Guzzle client, or a Guzzle handler such as a `MockHandler`. |

In your own tests, pass a Guzzle `MockHandler` as `http`, so no request leaves your machine:

```php
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;

$mock = new MockHandler([
    new Response(200, [], '{"client":"acme","id_profile":"default","medicines":1,"last_import":null}'),
]);
$oushodh = new Client('oush_test_123', http: $mock);
```

## Development

The dev tools need PHP 8.4 or newer.

```sh
composer install
composer test
composer analyse
composer lint
```

`composer test` runs PHPUnit, `composer analyse` runs PHPStan and `composer lint` runs PHP CS Fixer. The tests use a
Guzzle `MockHandler`. They never call the API.

## Licence

MIT. Copyright Oushodh. See [LICENSE](LICENSE).
