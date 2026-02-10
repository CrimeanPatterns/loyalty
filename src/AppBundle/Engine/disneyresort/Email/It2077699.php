<?php

namespace AwardWallet\Engine\disneyresort\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class It2077699 extends \TAccountChecker
{
    public $mailFiles = "disneyresort/it-2077699.eml";
    public $detectFrom = "@disneyholidays.co.uk";
    public $detectBody = [
        'We hope you have a magical time at Walt Disney World'
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {

        $class = explode('\\', __CLASS__);
        $email->setType(end($class));

        $this->parseEmail($email);

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//*[contains(normalize-space(),'Walt Disney World®')]")->length > 0) {
            foreach ($this->detectBody as $dBody) {
                if ($this->http->XPath->query("//*[{$this->contains($dBody)}]")->length > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers['subject'], 'Walt Disney World') !== false && stripos($headers['subject'], 'Booking Confirmation (#') !== false) {
            // Walt Disney World 2022 Booking Confirmation (#22161403)
            return true;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    private function parseEmail(Email $email)
    {
        $h = $email->add()->hotel();

        // General
        $h->general()
            ->confirmation($this->http->FindSingleNode("//text()[contains(., 'Booking Confirmation')]/ancestor::td[1][starts-with(normalize-space(), 'Walt Disney World')]",
                null, true, "/\(#(\d{5,})\)/"))
        ;

        $text = implode("\n", $this->http->FindNodes("(//tr[normalize-space()='Passengers']/following-sibling::tr/descendant-or-self::tr[not(.//tr)][normalize-space()])[position() < 10]"));
//        $this->logger->debug('$text = '.print_r( $text,true));
        if (preg_match("/^(?<travellers>(?:[[:alpha:] \-]+\n)+)(?<name>.*Disney.*)\n(?<ddate>.+) - (?<adate>.+)\n(?<roomType>.*Room.*)\n(?<guests>.*adult.*)?/i", $text, $m)) {
            $h->general()
                ->travellers(explode("\n", trim($m['travellers'])));

            $h->hotel()
                ->name($m['name'])
                ->noAddress();

            $h->booked()
                ->checkIn(strtotime($m['ddate']))
                ->checkOut(strtotime($m['adate']))
            ;

            $h->addRoom()
                ->setType($m['roomType']);

            if (preg_match("/\d+ adult/", $m['guests'] ?? '', $mat)) {
                $h->booked()
                    ->guests($mat[1]);
            }
        }

        $total = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Total Price:')]", null, true, "/:(.+)/");
        if (preg_match("#^\s*(?<curr>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $total, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<curr>[^\d\s]{1,5})\s*$#", $total, $m)
        ){
            $currency = $this->currency($m['curr']);
            $h->price()
                ->total($this->amount($m['amount'], $currency))
                ->currency($currency)
            ;
        }
    }

    public static function getEmailTypesCount()
    {
        return 1;
    }

    public static function getEmailLanguages()
    {
        return ["en"];
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
                $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

                return 'normalize-space(' . $node . ')=' . $s;
            }, $field)) . ')';
    }

    private function starts($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
                $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

                return 'starts-with(normalize-space(' . $node . '),' . $s . ')';
            }, $field)) . ')';
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
                $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

                return 'contains(normalize-space(' . $node . '),' . $s . ')';
            }, $field)) . ')';
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
                return str_replace(' ', '\s+', preg_quote($s));
            }, $field)) . ')';
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);
        if(isset($m[$c])) return $m[$c];
        return null;
    }
    private function amount($price, $currency)
    {
        $price = PriceHelper::parse($price, $currency);
        if (is_numeric($price)) {
            return (float)$price;
        }
        return null;
    }

    private function currency($s)
    {
        if ($code = $this->re("#^\s*([A-Z]{3})\s*$#", $s)) return $code;
        $sym = [
            '£' => 'GBP',
//            '€' => 'EUR',
//            '$' => 'USD',
        ];
        foreach($sym as $f => $r)
            if ($s == $f) return $r;
        return null;
    }
}
