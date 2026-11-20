<?php

namespace Tests\Unit;

use App\Support\PersonName;
use PHPUnit\Framework\TestCase;

class PersonNameTest extends TestCase
{
    public function test_formats_names_as_proper_nouns(): void
    {
        $this->assertSame('Jose Antonio', PersonName::format('JOSE ANTONIO'));
        $this->assertSame('María de los Ángeles', PersonName::format('maría de los ángeles'));
        $this->assertSame('De la Rosa', PersonName::format('DE LA ROSA'));
        $this->assertSame('Ana Karen', PersonName::format('  ana   karen '));
        $this->assertSame('Gutiérrez-Pérez', PersonName::format('GUTIÉRREZ-PÉREZ'));
        $this->assertSame('Ñuñez', PersonName::format('ÑUÑEZ'));
        $this->assertNull(PersonName::format(null));
    }
}
