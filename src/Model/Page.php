<?php

declare(strict_types=1);

namespace Oushodh\Model;

use Oushodh\Data;

/**
 * One page of medicines. Pass `next` back as the cursor for the next page; it is null on the last page.
 * `total` is how many medicines match, across all pages.
 */
final readonly class Page
{
    /** @var list<Medicine> */
    public array $items;
    public ?string $next;
    public int $total;

    /** @var array<string, mixed> */
    public array $raw;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(array $data)
    {
        $this->items = Data::objects($data, 'items', static fn(array $item) => new Medicine($item));
        $this->next = Data::nullableString($data, 'next');
        $this->total = Data::int($data, 'total');
        $this->raw = $data;
    }
}
