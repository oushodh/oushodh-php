<?php

declare(strict_types=1);

namespace Oushodh\Model;

/**
 * The best matches first, at most `limit`. `total` counts every match, including those past `limit`.
 */
final readonly class SearchResult
{
    /** @var list<Medicine> */
    public array $items;
    public int $total;

    /** @var array<string, mixed> */
    public array $raw;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(array $data)
    {
        $this->items = Data::objects($data, 'items', static fn(array $item) => new Medicine($item));
        $this->total = Data::int($data, 'total');
        $this->raw = $data;
    }
}
