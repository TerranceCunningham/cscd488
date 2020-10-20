<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use classes\Math;

/**
 * @coversDefaultClass \classes\Math
 */
class MathTest extends TestCase {
    protected $Math;

    public function setUp(): void {
        $this->Math = new Math();
    }

    /**
     * @covers ::add
     */
    public function testAdd(): void {
        $numbers = [3,2];
        $this->assertEquals(5, $this->Math->add($numbers));
    }

    /**
     * @covers ::sub
     */
    public function testSub(): void {
        $numbers = [3,2];
        $this->assertEquals(1, $this->Math->sub($numbers));
    }

    /**
     * @covers ::mul
     */
    public function testMul(): void {
        $numbers = [3,2];
        $this->assertEquals(6, $this->Math->mul($numbers));
    }
}