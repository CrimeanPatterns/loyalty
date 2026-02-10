<?php


namespace Tests\Unit\Service;


use AwardWallet\Common\Parser\Util\PriceHelper;
use Codeception\TestCase\Test;

class PriceHelperTest extends Test
{

    public function testParsePrice()
    {
        foreach($this->getData() as $row) {
            $this->assertSame($row[1], PriceHelper::parse($row[0], $row[2] ?? null), 'param: ' . $row[0] . ' ' . ($row[2] ?? ''));
        }
    }

    private function getData()
    {
        return [
            [null, null],
            [0, 0],
            [1.23, 1.23],
            [12345, 12345],
            ['0', '0'],
            ['12345', '12345'],
            ['123.45', '123.45'],
            ['123,45', '123.45'],
            ['1234,56', '1234.56'],
            ['1 234,56', '1234.56'],
            ['123,456.7', '123456.7'],
            ['1234,567', '1234.567'],
            ['1,234,567.89', '1234567.89'],
            ['1234,567', '1234.567', 'JOD'],
            ['1 234,567', '1234.567', 'JOD'],
            ['23,456', '23.456', 'JOD'],
            ['23,456', '23456', 'USD'],
            ['23,456', '23456'],
            ['.123', '0.123'],
            [' 123.456,789', '123456.789'],
            ['1234,567', null, 'USD'],
            ['1,234.567', null, 'USD'],
            ['123.', null],
            ['1 234.', null],
            ['1.234,456.78', null],
            ['1.234,567,89', null],
            ['1234,567.1', null],
            ['1,23.45', null],
            ['1,23.456', null, 'JOD'],
            ['12 345,678.90', null],
            ['$12', null],
            ['$12.34', null],
            ['$12.34,56', null],
            ['123.456.78', null],
        ];
    }

}