<?php

declare(strict_types=1);

namespace Oushodh\Model;

use Oushodh\Exception\OushodhException;

/**
 * Typed reads from a decoded JSON object.
 *
 * @internal
 */
final class Data
{
    /**
     * @param array<array-key, mixed> $data
     */
    public static function string(array $data, string $key): string
    {
        $value = $data[$key] ?? null;

        if (!\is_string($value)) {
            throw self::unexpected($key);
        }

        return $value;
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function nullableString(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        if ($value !== null && !\is_string($value)) {
            throw self::unexpected($key);
        }

        return $value;
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function number(array $data, string $key): float
    {
        $value = $data[$key] ?? null;

        if (!\is_int($value) && !\is_float($value)) {
            throw self::unexpected($key);
        }

        return (float) $value;
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function nullableNumber(array $data, string $key): ?float
    {
        if (($data[$key] ?? null) === null) {
            return null;
        }

        return self::number($data, $key);
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function int(array $data, string $key): int
    {
        $value = $data[$key] ?? null;

        if (!\is_int($value)) {
            throw self::unexpected($key);
        }

        return $value;
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function bool(array $data, string $key): bool
    {
        $value = $data[$key] ?? null;

        if (!\is_bool($value)) {
            throw self::unexpected($key);
        }

        return $value;
    }

    /**
     * @param array<array-key, mixed> $data
     *
     * @return array<string, mixed>
     */
    public static function object(array $data, string $key): array
    {
        $value = $data[$key] ?? null;

        if (!\is_array($value)) {
            throw self::unexpected($key);
        }

        /** @var array<string, mixed> $value */
        return $value;
    }

    /**
     * @param array<array-key, mixed> $data
     *
     * @return array<string, mixed>|null
     */
    public static function nullableObject(array $data, string $key): ?array
    {
        if (($data[$key] ?? null) === null) {
            return null;
        }

        return self::object($data, $key);
    }

    /**
     * @param array<array-key, mixed> $data
     *
     * @return list<mixed>
     */
    public static function list(array $data, string $key): array
    {
        $value = $data[$key] ?? null;

        if (!\is_array($value) || !array_is_list($value)) {
            throw self::unexpected($key);
        }

        return $value;
    }

    /**
     * @param array<array-key, mixed> $data
     *
     * @return list<string>
     */
    public static function strings(array $data, string $key): array
    {
        $values = self::list($data, $key);

        foreach ($values as $value) {
            if (!\is_string($value)) {
                throw self::unexpected($key);
            }
        }

        /** @var list<string> $values */
        return $values;
    }

    /**
     * @template T
     *
     * @param array<array-key, mixed>          $data
     * @param callable(array<string, mixed>): T $make
     *
     * @return list<T>
     */
    public static function objects(array $data, string $key, callable $make): array
    {
        $items = [];

        foreach (self::list($data, $key) as $item) {
            if (!\is_array($item)) {
                throw self::unexpected($key);
            }

            /** @var array<string, mixed> $item */
            $items[] = $make($item);
        }

        return $items;
    }

    private static function unexpected(string $key): OushodhException
    {
        return new OushodhException(\sprintf('The API answered with an unexpected value for "%s".', $key));
    }
}
