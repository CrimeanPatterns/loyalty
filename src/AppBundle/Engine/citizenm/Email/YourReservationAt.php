<?php

namespace AwardWallet\Engine\citizenm\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class YourReservationAt extends \TAccountChecker
{
    public $mailFiles = "citizenm/it-324439029.eml";

    public $lang;
    public static $dictionary = [
        'en' => [
            'here are the details' => 'here are the details',
            'confirmation number'  => 'confirmation number',
            'your name'            => ['your name', 'name'],
        ],
    ];

    private $detectFrom = "@citizenm.com";
    private $detectSubject = [
        // en
        //  Our home is now your home - Your reservation at citizenM Rotterdam
        'Your reservation at citizenM',
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]citizenm.com/', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers["from"], $this->detectFrom) === false) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers["subject"], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if (
            $this->http->XPath->query("//a[{$this->contains(['.citizenm.com'], '@href')}]")->length === 0
        ) {
            return false;
        }

        foreach (self::$dictionary as $dict) {
            if (!empty($dict['here are the details']) && !empty($dict['confirmation number'])
                && $this->http->XPath->query("//*[{$this->contains($dict['here are the details'])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->contains($dict['confirmation number'])}]")->length > 0
            ) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        $this->parseEmailHtml($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function assignLang()
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (isset($dict["here are the details"])
                && $this->http->XPath->query("//*[{$this->contains($dict['here are the details'])}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function parseEmailHtml(Email $email)
    {
        $h = $email->add()->hotel();

        // General
        $h->general()
            ->confirmation($this->nextTd($this->t('confirmation number'), "/^\s*([A-Z\d]{5,})\s*$/"))
            ->traveller($this->nextTd($this->t('your name')))
        ;

        // Hotel
        $h->hotel()
            ->name($this->nextTd($this->t('hotel')))
        ;
        $address = $this->http->FindNodes("//td[not(.//td)][{$this->eq($this->t('address'))}]/following::text()[normalize-space()][1]/ancestor::tr[2]/descendant::text()[normalize-space()]");

        if (preg_match("/^\S.+\n([\S\s]+?)\s*more info\s*$/", implode("\n", $address), $m)) {
            $h->hotel()
                ->address(preg_replace("/\s*\n\s*/", ', ', $m[1]));
        }

        // Booked
        $h->booked()
            ->checkIn($this->normalizeDate($this->nextTd($this->t('arrival date'))))
            ->checkOut($this->normalizeDate($this->nextTd($this->t('departure date'))))
            ->guests($this->nextTd($this->t('number of citizen(s)'), '/^\s*(\d+)\s*[[:alpha:]]+/'))
            ->rooms($this->nextTd($this->t('number of rooms'), '/^\s*(\d+)\s*[[:alpha:]]+/'))
        ;

        // Price
        $total = $this->getTotal($this->http->FindSingleNode("//tr[not(.//tr)][descendant::text()[normalize-space()][1][{$this->eq($this->t('total stay'))}]]/*[normalize-space()][2]"));
        $h->price()
            ->total($total['amount'])
            ->currency($total['currency'])
        ;

        return true;
    }

    private function getTotal($text)
    {
        $result = ['amount' => null, 'currency' => null];

        if (preg_match("#^\s*(?<currency>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $text, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d\s]{1,5})\s*$#", $text, $m)
            // $232.83 USD
            || preg_match("#^\s*\D{1,5}(?<amount>\d[\d\., ]*)\s*(?<currency>[A-Z]{3})\s*$#", $text, $m)
        ) {
            $m['currency'] = $this->currency($m['currency']);
            $m['amount'] = PriceHelper::parse($m['amount']);

            if (is_numeric($m['amount'])) {
                $m['amount'] = (float) $m['amount'];
            } else {
                $m['amount'] = null;
            }
            $result = ['amount' => $m['amount'], 'currency' => $m['currency']];
        }

        return $result;
    }

    private function currency($s)
    {
        $s = trim($s);
        $sym = [
            '€'=> 'EUR',
            '$'=> 'USD',
            '£'=> 'GBP',
        ];

        if (preg_match("/^[A-Z]{3}$/", $s)) {
            return $s;
        }

        foreach ($sym as $f => $r) {
            if ($s == $f) {
                return $r;
            }
        }

        if ($s = 'kr.') {
            if ($this->http->XPath->query("//text()[contains(normalize-space(), 'at Copenhagen')]")->length > 0) {
                return 'DKK';
            }
        }

        return null;
    }

    private function nextTd($field, $regexp = null)
    {
        return $this->http->FindSingleNode("//tr[count(*[normalize-space()]) = 2][*[normalize-space()][1][{$this->eq($field)}]]/*[normalize-space()][2]",
            null, true, $regexp);
    }

    private function t($s)
    {
        if (!isset($this->lang, self::$dictionary[$this->lang], self::$dictionary[$this->lang][$s])) {
            return $s;
        }

        return self::$dictionary[$this->lang][$s];
    }

    // additional methods

    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) {
            return 'contains(' . $text . ',"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function normalizeDate(?string $date): ?int
    {
//        $this->logger->debug('date begin = ' . print_r($date, true));
        $in = [
            //            '/^\s*\w+\s+(\d+)\s+([[:alpha:]]+)\s+(\d{4})\s*\n\s*\D*\b(\d{1,2}:\d{2}(?:\s*[ap]m)?)\s*(\n[\S\s]+)?\s*$/ui',
        ];
        $out = [
            //            '$1 $2 $3, $4',
        ];

        $date = preg_replace($in, $out, $date);
//        $this->logger->debug('date end = ' . print_r($date, true));

        return strtotime($date);
    }

    private function opt($field, $delimiter = '/')
    {
        $field = (array) $field;

        if (empty($field)) {
            $field = ['false'];
        }

        return '(?:' . implode("|", array_map(function ($s) use ($delimiter) {
            return str_replace(' ', '\s+', preg_quote($s, $delimiter));
        }, $field)) . ')';
    }
}
