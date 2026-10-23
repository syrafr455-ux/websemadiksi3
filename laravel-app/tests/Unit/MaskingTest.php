<?php

namespace Tests\Unit;

use App\Support\Masking;
use PHPUnit\Framework\TestCase;

class MaskingTest extends TestCase
{
    public function test_nim_shows_four_first_and_two_last_digits(): void
    {
        $this->assertSame('4330XXXX12', Masking::nim('4330024012'));
        $this->assertSame('2241XXXX01', Masking::nim('2241010001'));
    }

    public function test_name_shows_two_first_letters_of_every_word(): void
    {
        $this->assertSame('MuXXXXXX HiXXXX', Masking::nama('Muhammad Hildan'));
        $this->assertSame('SiXX', Masking::nama('Siti'));
        $this->assertSame('MuXXXXXX HiXXXX', Masking::nama('  Muhammad   Hildan '));
    }

    public function test_email_shows_two_first_and_two_last_characters_of_local_part(): void
    {
        $this->assertSame('43XXXXXX12@student.unusa.ac.id', Masking::email('4330024012@student.unusa.ac.id'));
        $this->assertSame('faXzi@student.unusa.ac.id', Masking::email('fauzi@student.unusa.ac.id'));
        $this->assertSame('-', Masking::email('bukan-email'));
    }

    public function test_phone_shows_four_first_and_two_last_digits(): void
    {
        $this->assertSame('0812XXXXXX90', Masking::telepon('081234567890'));
    }

    public function test_short_or_empty_values_never_reveal_more_than_they_should(): void
    {
        $this->assertSame('-', Masking::nim(null));
        $this->assertSame('-', Masking::telepon(''));
        $this->assertSame('1XX', Masking::nim('123'));
        $this->assertSame('aXX@x.id', Masking::email('abc@x.id'));
    }
}
