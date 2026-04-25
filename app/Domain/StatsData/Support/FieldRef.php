<?php

namespace App\Domain\StatsData\Support;

final class FieldRef
{
    public function __construct(
        public readonly string $alias,
        public readonly string $field,
    ) {}

    public static function parse(string $ref): ?self
    {
        $ref = trim($ref);
        if ($ref === '' || ! str_contains($ref, '.')) {
            return null;
        }
        [$alias, $field] = explode('.', $ref, 2);
        $alias = trim($alias);
        $field = trim($field);
        if ($alias === '' || $field === '') {
            return null;
        }

        return new self($alias, $field);
    }
}

