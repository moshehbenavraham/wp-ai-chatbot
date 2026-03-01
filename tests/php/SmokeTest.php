<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

final class SmokeTest extends TestCase
{
	public function testTrueIsTrue(): void
	{
		$this->assertTrue(true);
	}
}
