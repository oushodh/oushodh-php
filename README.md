# Oushodh PHP SDK

The PHP client for the [Oushodh API](https://oushodh.dev/docs): medicine data for Bangladesh, checked against the
DGDA register. It reads the public `/v1` API.

- PHP 8.2 or newer.
- One runtime dependency: Guzzle 7.

## Install

Install it from GitHub for now. Add the repo to your `composer.json`, then require the package:

```json
{
    "repositories": [{ "type": "vcs", "url": "https://github.com/oushodh/oushodh-php" }],
    "require": { "oushodh/oushodh-php": "dev-main" }
}
```

```sh
composer update oushodh/oushodh-php
```

The repo is private for now, so Composer needs a GitHub token that can read it.

Install from Packagist (`composer require oushodh/oushodh-php`): coming soon.

## Quickstart

Make a key on the Keys page of the member portal at [app.oushodh.dev](https://app.oushodh.dev). A key starts with
`oush_` and is shown once. Keep it on your server: never put it in an app or a web page.

```php
use Oushodh\Client;

$oushodh = new Client('oush_...');

$meta = $oushodh->meta();

echo $meta->client, ' reads ', $meta->medicines, " medicines\n";

$napa = $oushodh->getMedicine('MED-012933');

echo $napa->productName, ': ', $napa->prices->unit?->amount, " BDT a unit\n";
```

With no key, the client reads the `OUSHODH_API_KEY` env var. With neither, `new Client()` throws an
`InvalidArgumentException`.

### Options

| Option       | Default                   | What it does                                                  |
| ------------ | ------------------------- | ------------------------------------------------------------- |
| `apiKey`     | `OUSHODH_API_KEY`         | Your key                                                      |
| `baseUrl`    | `https://api.oushodh.dev` | Where the API is                                              |
| `timeout`    | `30.0`                    | Seconds to wait for one answer                                |
| `maxRetries` | `2`                       | Retries after a `429 rate_limited`, a `5xx` or a network error |
| `http`       | a new Guzzle client       | Your own Guzzle client, or a Guzzle handler such as a `MockHandler` |

```php
$oushodh = new Client(apiKey: 'oush_...', timeout: 10.0, maxRetries: 3);
```

## Methods

| Method                                                   | Route                          | Returns                |
| -------------------------------------------------------- | ------------------------------ | ---------------------- |
| `meta()`                                                 | `GET /v1/meta`                 | `Meta`                 |
| `listMedicines(limit:, cursor:, updatedSince:, q:)`      | `GET /v1/medicines`            | `Page`                 |
| `medicines(limit:, cursor:, updatedSince:, q:)`          | `GET /v1/medicines`, every page | `Generator` of `Medicine` |
| `search($q, limit:)`                                     | `GET /v1/medicines/search`     | `SearchResult`         |
| `getMedicine($ref)`                                      | `GET /v1/medicines/{ref}`      | `MedicineDetail`       |
| `imageUrl($file)`                                        | none                           | the picture's address  |
| `getImage($file)`                                        | `GET /v1/images/{file}`        | the JPEG bytes         |

Fields keep their API names in camel case: `product_name` is `$medicine->productName`. Every model also has `raw`,
the object as the API sent it, so a field added after this version is still there: `$medicine->raw['new_field']`.

## Paging

`listMedicines()` returns one page. Pass its `next` back as `cursor` for the next page. `next` is `null` on the last
page. `limit` is 1 to 500, and 100 when you leave it out.

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

## Sync

Copy the whole database once, then fetch only what changed.

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

- Store `uuid` as the key: it never changes. Show `id` to people.
- "At or after" means a change in the same second is never missed. The medicine that set the time comes back once
  more, so save by `uuid`.
- Medicines are never deleted and IDs are never reused, so a sync never has to remove one.
- `updatedSince` takes a string such as `2026-11-08T09:30:00Z`, or a `DateTimeInterface`, which the client sends in
  UTC.

## Errors

Every error is an `Oushodh\Exception\OushodhException`. It has `status`, the HTTP status, and `errorCode`, an
`Oushodh\ErrorCode`. Match on `errorCode` or on the class, never on the message: the message is for people and may
change.

| Class                     | Status | `errorCode`                    |
| ------------------------- | ------ | ------------------------------ |
| `BadRequestException`     | 400    | `BadRequest`                   |
| `AuthenticationException` | 401    | `Unauthorized`                 |
| `PlanException`           | 403    | `PlanExpired` or `PlanLimit`   |
| `NotFoundException`       | 404    | `NotFound`                     |
| `RateLimitException`      | 429    | `RateLimited` or `QuotaExceeded` |
| `ServerException`         | 5xx    | `Internal`                     |
| `ConnectionException`     | none   | none: no answer came back      |

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
        // The medicine is beyond your plan's medicines.
    }
}
```

A `400`, `401`, `403` or `404` gives the same answer if you send the same request again, so the client never retries
them. The key never appears in an error message.

## Rate limits

Your plan sets how many requests each key may make in a minute, and how many your account may make in a month. See
[Rate limits](https://oushodh.dev/docs/rate-limits).

- Over the minute's limit, the API answers `429 rate_limited`. The `Retry-After` header says how many seconds to wait.
  The client waits that long and tries again, up to `maxRetries` times. After that it throws a `RateLimitException`
  with the seconds in `retryAfter`.
- When the month's requests are used up, the API answers `429 quota_exceeded` until the next month. The client throws
  at once: `retryAfter` counts the seconds to the next month.
- After a `5xx` or a network error, the client waits a little longer each time (0.5 s, then 1 s, and so on, plus a
  random part) and tries again, up to `maxRetries` times.

Every route is a `GET`, so a retry is always safe.

## Plan limits

Developer reads the first 5,000 medicines and Team the first 20,000, in the order of their permanent number. Lists
and search leave out the medicines beyond your plan's number. A lookup by ID of such a medicine throws a
`PlanException` with `ErrorCode::PlanLimit`. An account with no active plan gets `ErrorCode::PlanExpired`.

## Images

`productImage` is the address of the medicine's picture, and `images` lists every picture, `productImage` first.
Pictures at `/v1/images` need no key, so an `<img>` tag can show them, and they never change.

```php
$url = $oushodh->imageUrl('50d858e0985ecc7f60418aaf0cc5ab587f42c2570a884095a9e8ccacd0f6545c.jpg');
$jpeg = $oushodh->getImage('50d858e0985ecc7f60418aaf0cc5ab587f42c2570a884095a9e8ccacd0f6545c.jpg');
```

`getImage()` sends no key.

## Tests

Pass a Guzzle `MockHandler` as `http`, so no request leaves your machine:

```php
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;

$mock = new MockHandler([new Response(200, [], '{"client":"acme","id_profile":"default","medicines":1,"last_import":null}')]);
$oushodh = new Client('oush_test_123', http: $mock);
```

## Develop

```sh
composer install
composer test
composer analyse
composer lint
```

## Docs

The full API reference is at [oushodh.dev/docs](https://oushodh.dev/docs).

## License

MIT. See [LICENSE](LICENSE).
