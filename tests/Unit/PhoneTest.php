<?php

namespace Tests\Unit;

use App\Support\Phone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PhoneTest extends TestCase
{
    #[DataProvider('numbers')]
    public function test_a_number_typed_any_common_way_is_kept_in_one_form(?string $typed, ?string $kept): void
    {
        $this->assertSame($kept, Phone::normalise($typed));
    }

    /** @return array<string, array{0: string|null, 1: string|null}> */
    public static function numbers(): array
    {
        return [
            'local with spaces' => ['0712 345 678', '254712345678'],
            'local plain' => ['0712345678', '254712345678'],
            'new 01 prefix' => ['0110 123 456', '254110123456'],
            'international with plus' => ['+254 712 345 678', '254712345678'],
            'international plain' => ['254712345678', '254712345678'],
            'with dashes and brackets' => ['(0712) 345-678', '254712345678'],
            'without the leading zero' => ['712345678', '254712345678'],
            'a landline' => ['020 1234567', null],
            'too short' => ['07123', null],
            'too long' => ['07123456789', null],
            'another country' => ['+256 712 345 678', null],
            'words' => ['call me', null],
            'empty' => ['', null],
            'nothing' => [null, null],
        ];
    }

    public function test_a_kept_number_is_shown_the_way_people_read_it(): void
    {
        $this->assertSame('0712 345 678', Phone::display('254712345678'));
    }

    public function test_a_search_finds_a_number_however_staff_type_it(): void
    {
        $this->assertSame('712345678', Phone::searchDigits('0712345678'));
        $this->assertSame('712', Phone::searchDigits('0712'));
        $this->assertSame('254712345678', Phone::searchDigits('+254 712 345678'));
        $this->assertSame('000', Phone::searchDigits('000'));
        $this->assertSame('345', Phone::searchDigits('345'));
    }

    public function test_a_search_word_that_is_no_part_of_a_number_is_not_looked_for_among_phones(): void
    {
        $this->assertNull(Phone::searchDigits('Wanjiru'));
        $this->assertNull(Phone::searchDigits('SB-123456'));
        $this->assertNull(Phone::searchDigits('A2'));
        $this->assertNull(Phone::searchDigits('12'));
    }
}
