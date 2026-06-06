<?php

namespace App\Data;

class ParsedCommand
{
    /**
     * @param  list<string>  $aliases
     * @param  array<string, mixed>  $parameters
     */
    public function __construct(
        public readonly string $name,
        public readonly string $slug,
        public readonly ?string $syntax,
        public readonly ?string $description,
        public readonly array $aliases = [],
        public readonly array $parameters = [],
        public readonly ?string $category = null,
    ) {}
}
