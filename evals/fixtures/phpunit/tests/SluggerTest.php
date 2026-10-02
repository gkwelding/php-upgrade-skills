<?php

declare(strict_types=1);

namespace Tests;

use App\Slugger;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Slugger
 * @group text
 */
final class SluggerTest extends TestCase
{
    /** @test */
    public function it_lowercases_and_hyphenates(): void
    {
        $this->assertSame('hello-world', (new Slugger())->slug('Hello World'));
    }

    /** @test */
    public function it_replaces_accented_letters(): void
    {
        $this->assertSame('creme-brulee', (new Slugger())->slug('Crème Brûlée'));
    }

    /** @test */
    public function it_collapses_repeated_separators(): void
    {
        $this->assertSame('a-b-c', (new Slugger())->slug('  a -- b __ c  '));
    }

    /**
     * @test
     * @testWith [""]
     *           ["!!!"]
     */
    public function it_falls_back_when_nothing_is_left(string $title): void
    {
        $this->assertSame('n-a', (new Slugger())->slug($title));
    }
}
