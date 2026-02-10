<?php

namespace AwardWallet\Engine\businesscl\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class BookingConfirmation extends \TAccountChecker
{
    public $mailFiles = "businesscl/it-157420148.eml";

    public $detectSubject = [
        // en
        'Booking Confirmation - '
    ];
    public $detectBody = [
        'en'  => ['Itinerary details'],
    ];

    public $emailDate = null;

    public $lang = 'en';
    public static $dictionary = [
        'en' => [
            'Booking reference number' => ['Booking reference number', 'Processing ID'],
            'PASSENGER(S) DETAILS' => ['PASSENGER(S) DETAILS', 'Passenger(s) details'],
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
//        foreach ($this->detectBody as $lang => $dBody) {
//            if ($this->http->XPath->query("//*[".$this->contains($dBody)."]")->length > 0) {
//                $this->lang = $lang;
//                break;
//            }
//        }

        $year = $this->http->FindSingleNode("//text()[contains(., 'Business-class.com. All rights reserved')]",
            null, true, "/^\s*@\s*(\d{4})\D+/");
        if (!empty($year)) {
            $this->emailDate = strtotime("1 Jan " . $year);
        }
        if (empty($this->emailDate)) {
            $this->emailDate = strtotime("- 5 day", strtotime($parser->getDate()));
        }

        $this->parseEmail($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[contains(normalize-space(),'Business-class.com. All rights reserved') or contains(@src,'@business-class.com')]")->length === 0) {
            return false;
        }

        foreach ($this->detectBody as $lang => $dBody) {
            if ($this->http->XPath->query("//*[".$this->contains($dBody)."]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers['from']) || empty($headers['subject'])) {
            return false;
        }

        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers['subject'], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'reservations@business-class.com') !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function parseEmail(Email $email)
    {
        $email->ota()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->starts($this->t('Booking reference number'))}]", null, false, "/{$this->opt($this->t('Booking reference number'))}[\s\W]+([A-Z\d]{5,})\s*$/"))
        ;

        $f = $email->add()->flight();

        // General
        $f->general()
            ->noConfirmation()
            ->travellers($this->http->FindNodes("//tr[".$this->eq($this->t("PASSENGER(S) DETAILS"))."]/following-sibling::tr[normalize-space()]",
                null, "/^\s*\d+\s*(.+?)\([\d\\/ ]+\)/"), true)
        ;

        // Price
        $total = $this->http->FindSingleNode("//td[".$this->eq($this->t("Total Price"))."]/following-sibling::td[normalize-space()][1]");
        if (preg_match("#^\s*(?<currency>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $total, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d\s]{1,5})\s*$#", $total, $m)
        ) {
            $currencyCode = $this->currency($m['currency']);

            $f->price()
                ->total(PriceHelper::parse($m['amount'], $currencyCode))
                ->currency($currencyCode);
        }
        $cost = $this->http->FindSingleNode("//td[".$this->eq($this->t("Fare"))."]/following-sibling::td[normalize-space()][1]");
        if (preg_match("#^\s*(?<currency>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $cost, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d\s]{1,5})\s*$#", $cost, $m)
        ) {
            $f->price()
                ->cost(PriceHelper::parse($m['amount'], $currencyCode));
        }
        $tax = $this->http->FindSingleNode("//td[".$this->eq($this->t("Taxes & Fees"))."]/following-sibling::td[normalize-space()][1]");
        if (preg_match("#^\s*(?<currency>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $tax, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d\s]{1,5})\s*$#", $tax, $m)
        ) {
            $f->price()
                ->tax(PriceHelper::parse($m['amount'], $currencyCode));
        }

        // Segments
        $xpath = "//text()[".$this->contains($this->t(" - Flight"))."]/ancestor::table[1]";
//        $this->logger->debug('$xpath = '.print_r( $xpath,true));
        $nodes = $this->http->XPath->query($xpath);
        foreach ($nodes as $root) {

            $s = $f->addSegment();

            $date = null;
            $dateStr = $this->http->FindSingleNode("preceding::td[".$this->starts($this->t("Total Time"))."][1]/preceding-sibling::td[normalize-space()][last()]",
                $root, false, "/^\s*[A-Z]{3}\s*-\s*[A-Z]{3}\s*,\s*(.+)$/");

            if (!empty($dateStr) && !empty($this->emailDate)) {
                $date = EmailDateHelper::parseDateRelative($dateStr, $this->emailDate);
            }

            // Airline
            $flight = $this->http->FindSingleNode(".//td[".$this->contains($this->t(" - Flight"))."]", $root);
            if (preg_match("/^\s*(.+)\s*{$this->opt($this->t(" - Flight"))}\s*(\d{1,5})\s*-\s*(.+)$/", $flight, $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2]);

                $s->extra()
                    ->cabin($m[3]);
            }

            // Departure
            $tr = "descendant::tr[not(.//tr)][2]/";
            $s->departure()
                ->code($this->http->FindSingleNode($tr . "descendant::text()[normalize-space()][2]", $root))
                ->name($this->http->FindSingleNode($tr . "descendant::text()[normalize-space()][3]", $root))
                ->date((!empty($date))? strtotime($this->http->FindSingleNode($tr . "descendant::text()[normalize-space()][1]", $root), $date) : null)
            ;

            // Arrival
            $tr = "descendant::tr[not(.//tr)][3]/";
            $s->arrival()
                ->code($this->http->FindSingleNode($tr . "descendant::text()[normalize-space()][2]", $root))
                ->name($this->http->FindSingleNode($tr . "descendant::text()[normalize-space()][3]", $root))
                ->date((!empty($date))? strtotime($this->http->FindSingleNode($tr . "descendant::text()[normalize-space()][1]", $root), $date) : null)
            ;
        }

        return true;
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) {
                return "normalize-space(.)=\"{$s}\"";
            }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) {
                return "starts-with(normalize-space(.), \"{$s}\")";
            }, $field)) . ')';
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
                return str_replace(' ', '\s+', preg_quote($s));
            }, $field)) . ')';
    }

    private function currency($s)
    {
        if (preg_match("#^\s*([A-Z]{3})\s*$#", $s)) {
            return $s;
        }
        $sym = [
            '€'=> 'EUR',
            '$'=> 'USD',
            '£'=> 'GBP',
        ];

        foreach ($sym as $f => $r) {
            if ($s == $f) {
                return $r;
            }
        }

        return null;
    }
}
