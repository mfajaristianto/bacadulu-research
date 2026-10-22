<?php

namespace Tests\Unit;

use App\Services\Documents\PrintableText;
use PHPUnit\Framework\TestCase;

class PrintableDocumentTextTest extends TestCase
{
    public function test_heading_and_common_bold_markers_are_not_printed(): void
    {
        $value = "## PENDAHULUAN\nHasil **penelitian** dengan DOI:10.1234/test";
        $this->assertSame(
            "PENDAHULUAN\nHasil penelitian dengan DOI:10.1234/test",
            PrintableText::clean($value)
        );
    }
}
