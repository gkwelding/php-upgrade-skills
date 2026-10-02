<?php

declare(strict_types=1);

namespace App;

final class Slugger
{
    private const ACCENTS = ['à' => 'a', 'á' => 'a', 'â' => 'a', 'ä' => 'a', 'ç' => 'c', 'è' => 'e', 'é' => 'e',
        'ê' => 'e', 'ë' => 'e', 'í' => 'i', 'ï' => 'i', 'ñ' => 'n', 'ó' => 'o', 'ö' => 'o', 'ú' => 'u', 'û' => 'u', 'ü' => 'u'];

    public function slug(string $title): string
    {
        $slug = strtr(mb_strtolower($title), self::ACCENTS);
        $slug = trim((string) preg_replace('/[^a-z0-9]+/', '-', $slug), '-');

        return $slug === '' ? 'n-a' : $slug;
    }
}
