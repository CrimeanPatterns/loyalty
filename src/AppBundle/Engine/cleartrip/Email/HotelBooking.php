<?php

namespace AwardWallet\Engine\cleartrip\Email;

use AwardWallet\Schema\Parser\Email\Email;

class HotelBooking extends \TAccountChecker
{
    public $mailFiles = "cleartrip/it-27337751.eml, cleartrip/it-27483753.eml";

    public static $dict = [
        'en' => [
            'Rate Breakup' => ['Rate Breakup', 'Rate breakup', 'RATE BREAKUP'],
            'Hotel Taxes'  => ['Hotel Taxes', 'Hotel Taxes & Fees'],
        ],
    ];

    private $detectFrom = "@cleartrip.com";
    private $detectSubject = [
        'en'  => "Booking Confirmation for",
        'en2' => "Booking voucher for ",
    ];

    private $detectBody = [
        'en' => ['A comfortable room will be ready and waiting for you'],
    ];

    private $lang = 'en';

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $body = html_entity_decode($parser->getHTMLBody());

        foreach ($this->detectBody as $lang => $detectBody) {
            foreach ($detectBody as $dBody) {
                if (strpos($body, $dBody) !== false) {
                    $this->lang = $lang;

                    break;
                }
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $this->parseEmail($email);

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        foreach ($this->detectBody as $detectBody) {
            foreach ($detectBody as $dBody) {
                if (strpos($body, $dBody) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && isset($headers["subject"])) {
            if (stripos($headers['from'], $this->detectFrom) === false) {
                return false;
            }

            foreach ($this->detectSubject as $reSubject) {
                if (stripos($headers["subject"], $reSubject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseEmail(Email $email): void
    {
        $email->ota()
            ->confirmation($this->http->FindSingleNode('//text()[starts-with(normalize-space(), "Your trip ID is")]/following-sibling::*[normalize-space()][1]', null, true, "#^\s*(\d+)\s*$#"));

        $h = $email->add()->hotel();

        $h->general()
            ->noConfirmation()
            ->traveller($this->http->FindSingleNode('//text()[normalize-space() = "Primary traveller in this trip"]/ancestor::tr[1]/following-sibling::tr[1]'));

        $h->hotel()
            ->name($this->http->FindSingleNode('//text()[normalize-space() = "Hotel details"]/ancestor::tr[1]/following-sibling::tr[1]/descendant::text()[normalize-space()][1]'))
            ->address($this->http->FindSingleNode('//text()[normalize-space() = "Hotel details"]/ancestor::tr[1]/following-sibling::tr[1]/descendant::text()[normalize-space()][2]'))
        ;

        $dates = $this->http->FindSingleNode("//text()[normalize-space() = 'Booking details']/ancestor::tr[1]/following::text()[starts-with(normalize-space(), 'From')][1]");

        if (preg_match("#From (.+) to (.+)#", $dates, $m)) {
            $h->booked()
                ->checkIn(strtotime($m[1]))
                ->checkOut(strtotime($m[2]))
            ;
        }

        $checkInTime = $this->http->FindSingleNode("//tr[normalize-space()='Checking In On']/following-sibling::tr[normalize-space()][1]", null, true, "/(?:^|,)\s*(\d{1,2}(?:[:]\d{2})?(?:\s*[AaPp]\.?[Mm]\.?)?)$/");

        if (!empty($h->getCheckInDate()) && $checkInTime) {
            $h->booked()->checkIn(strtotime($checkInTime, $h->getCheckInDate()));
        }

        $h->booked()
            ->rooms($this->http->FindSingleNode("//text()[normalize-space() = 'Booking details']/ancestor::tr[1]/following::text()[normalize-space()][1]", null, true, "#^\s*(\d+) room#"))
        ;

        $room = $h->addRoom();
        $room->setType($this->http->FindSingleNode("//text()[normalize-space()='Room details']/ancestor::tr[1]/following::text()[normalize-space()][1]", null, true, '/^\s*\d+\s*[Xx]\s*(.+)/'));

        $xpathPayment = "//text()[{$this->eq($this->t('Rate Breakup'))}]";

        $total = $this->http->FindSingleNode("//text()[normalize-space() = 'Payment to be made at the hotel' or normalize-space() = 'Total charge']/following::text()[normalize-space()][1]");

        if (preg_match('/^\s*(?<currency>[^\d\s]+)\s*(?<amount>\d[,.\'\d ]*)\s*$/', $total, $m)
            || preg_match('/^\s*(?<amount>\d[,.\'\d ]*)\s*(?<currency>[^\d\s]+)\s*$/', $total, $m)
        ) {
            $h->price()
                ->currency($this->currency($m['currency']))
                ->total($this->amount($m['amount']));

            $roomRate = $this->http->FindSingleNode($xpathPayment . "/following::td[normalize-space()='Room Rate']/following-sibling::td[normalize-space()][1]");

            if (preg_match('/^\s*(?:' . preg_quote($m['currency'], '/') . ')?\s*(?<amount>\d[,.\'\d ]*)\s*$/', $roomRate, $matches)
                || preg_match('/^\s*(?<amount>\d[,.\'\d ]*)\s*(?:' . preg_quote($m['currency'], '/') . ')?\s*$/', $roomRate, $matches)
            ) {
                $h->price()->cost($this->amount($matches['amount']));
            }

            $taxes = $this->http->FindSingleNode($xpathPayment . "/following::td[{$this->eq($this->t('Hotel Taxes'))}]/following-sibling::td[normalize-space()][1]");

            if (preg_match('/^\s*(?:' . preg_quote($m['currency'], '/') . ')?\s*(?<amount>\d[,.\'\d ]*)\s*$/', $taxes, $matches)
                || preg_match('/^\s*(?<amount>\d[,.\'\d ]*)\s*(?:' . preg_quote($m['currency'], '/') . ')?\s*$/', $taxes, $matches)
            ) {
                $h->price()->tax($this->amount($matches['amount']));
            }

            $convenienceFee = $this->http->FindSingleNode($xpathPayment . "/following::td[{$this->starts($this->t('Convenience Fee'))}]/ancestor::tr[1]");

            if (preg_match("/(?<name>{$this->opt($this->t('Convenience Fee'))})[*\s]*(?:" . preg_quote($m['currency'], '/') . ")?\s*(?<charge>\d[,.\'\d ]*)\s*$/", $convenienceFee, $matches)
                || preg_match("/(?<name>{$this->opt($this->t('Convenience Fee'))})[*\s]*(?<charge>\d[,.\'\d ]*)\s*(?:" . preg_quote($m['currency'], '/') . ")?\s*$/", $convenienceFee, $matches)
            ) {
                $h->price()->fee($matches['name'], $this->amount($matches['charge']));
            }

            $discount = $this->http->FindSingleNode($xpathPayment . "/following::td[{$this->starts($this->t('Discount'))}]/following-sibling::td[normalize-space()][1]");

            if (preg_match("/^\s*(?:" . preg_quote($m['currency'], '/') . ")?\s*(?<amount>\d[,.\'\d ]*)\s*$/", $discount, $matches)
                || preg_match("/^\s*(?<amount>\d[,.\'\d ]*)\s*(?:" . preg_quote($m['currency'], '/') . ")?\s*$/", $discount, $matches)
            ) {
                $h->price()->discount($this->amount($matches['amount']));
            }
        }
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function amount($s)
    {
        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $this->re("#(\d[\d,.]*)#", $s)));
    }

    private function currency($s)
    {
        $sym = [
            '€'  => 'EUR',
            '$'  => 'USD',
            '£'  => 'GBP',
            '₹'  => 'INR',
            'Rs.'=> 'INR',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }

        foreach ($sym as $f=>$r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        return null;
    }

    private function t(string $phrase)
    {
        if (!isset(self::$dict, $this->lang) || empty(self::$dict[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dict[$this->lang][$phrase];
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ')="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'starts-with(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }
}
