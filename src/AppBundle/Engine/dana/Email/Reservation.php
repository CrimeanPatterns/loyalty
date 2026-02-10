<?php

namespace AwardWallet\Engine\dana\Email;

// TODO: delete what not use
use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class Reservation extends \TAccountChecker
{
    public $mailFiles = "dana/it-168940746.eml";

    public $lang;
    public static $dictionary = [
        'en' => [
            'Passengers'  => 'Passengers',
            'Flight Info' => 'Flight Info',
        ],
    ];

    private $detectFrom = 'noreply@flydanaair.com';
    private $detectSubject = [
        // en
        'Dana Air RESERVATION, PNR:',
    ];
    private $detectBody = [
        'en' => [
            'below are the details of your itinerary',
            'At the request of the ticket holder, the booking below has been sent to you.',
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true && stripos($headers["subject"], 'Dana Air') === false) {
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
            $this->http->XPath->query("//a[{$this->contains(['flydanaair.com'], '@href')}]")->length === 0
        ) {
            return false;
        }

        foreach ($this->detectBody as $detectBody) {
            if ($this->http->XPath->query("//*[{$this->contains($detectBody)}]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug("can't determine a language");

            return $email;
        }
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
        // TODO check count types
        return count(self::$dictionary);
    }

    private function parseEmailHtml(Email $email)
    {
        $f = $email->add()->flight();

        // General
        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Booking #")) . "]/following::text()[normalize-space()][1]",
                null, true, "/^\s*[A-Z\d]{5,7}\s*$/"))
            ->travellers($this->http->FindNodes("//text()[" . $this->eq($this->t("Passengers")) . "]/following::tr[not(.//tr)][normalize-space()][2][not(*[" . $this->eq($this->t("Name")) . "])]/ancestor::*[1]/tr/*[1]"))
        ;

        if ($this->http->XPath->query("//text()[normalize-space()='Ticket Details']/following::table[1][starts-with(normalize-space(), 'Ticket')]")->length > 0) {
            $tickets = $this->http->FindNodes("//text()[normalize-space()='Ticket Details']/following::table[1][contains(normalize-space(), 'Ticket')]/descendant::tr/td[normalize-space()][not(contains(normalize-space(), 'Total'))][1]");
            $f->setTicketNumbers($tickets, false);
        }

        $dateRelative = $this->normalizeDate($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Reserved On:")) . "]/following::text()[normalize-space()][1]",
            null, true, "/.*\b\d{4}\b.*/"));

        $xpath = "//tr[*[" . $this->eq($this->t("Flight Info")) . "] and *[" . $this->eq($this->t("Flight Number")) . "] ]/following::tr[not(.//tr)][normalize-space()][1]/ancestor::*[1]/tr[not(*[" . $this->eq($this->t("Flight Info")) . "])]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            // Airline
            $flight = implode("\n", $this->http->FindNodes("*[4]//text()[normalize-space()]", $root));

            if (preg_match("/^\s*(?<al>[A-Z\d][A-Z]|[A-Z][A-Z\d])\s*-\s*(?<fn>\d{1,5})\s+(?<cabin>[[:alpha:] ]+)\s*$/", $flight, $m)) {
                $s->airline()
                    ->name($m['al'])
                    ->number($m['fn'])
                ;

                $s->extra()
                    ->cabin($m['cabin']);
            }

            $date = $this->normalizeDate(implode(' ', $this->http->FindNodes("*[1]//text()[normalize-space()]", $root)), $dateRelative);

            $times = $this->http->FindNodes("*[2]//text()[normalize-space()]", $root);

            if (!empty($date) && count($times) == 2) {
                $s->departure()
                    ->date(strtotime($times[0], $date));
                $s->arrival()
                    ->date(strtotime($times[1], $date));
            }

            $routes = implode(' ', $this->http->FindNodes("*[3]//text()[normalize-space()]", $root));

            if (preg_match("/Departs (?<dn>.+?)\s*\(\s*(?<dc>[A-Z]{3})\s*\)\s*Lands in (?<an>.+?)\s*\(\s*(?<ac>[A-Z]{3})\s*\)\s*$/", $routes, $m)) {
                $s->departure()
                    ->code($m['dc'])
                    ->name($m['dn'])
                ;
                $s->arrival()
                    ->code($m['ac'])
                    ->name($m['an'])
                ;
            }
        }

        // Price
        $total = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Total Amount:")) . "]/ancestor::tr[1]");
        $currency = null;

        if (preg_match("/:\s*([A-Z]{3})\s*(\d[\d, .]*)\b/", $total, $m)) {
            $currency = $m[1];
            $f->price()
                ->currency($m[1])
                ->total(PriceHelper::parse($m[2], $m[1]));
        }
        $cost = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Base Fare")) . "]/ancestor::tr[1]");

        if (preg_match("/Base Fare\s*([A-Z]{3})\s*(\d[\d, .]*)\b/", $cost, $m) && $currency === $m[1]) {
            $f->price()
                ->cost(PriceHelper::parse($m[2], $m[1]));
        }

        $feeNodes = $this->http->XPath->query("//tr[*[1][" . $this->eq($this->t("Base Fare")) . "]]/following-sibling::tr");

        foreach ($feeNodes as $fNode) {
            if ($this->http->FindSingleNode("*[2]", $fNode) == $currency) {
                $f->price()
                    ->fee($this->http->FindSingleNode("*[5]", $fNode), PriceHelper::parse($this->http->FindSingleNode("*[3]", $fNode), $currency));
            }
        }

        return true;
    }

    private function assignLang()
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (isset($dict["Flight Info"], $dict["Passengers"])) {
                if ($this->http->XPath->query("//*[{$this->contains($dict['Flight Info'])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($dict['Passengers'])}]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
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

    private function normalizeDate(?string $date, $dateRelative = null)
    {
//        $this->logger->debug('date begin = ' . print_r( $date, true));
        if (empty($date) || (empty($dateRelative)) && !preg_match("/\b\d{4}\b/", $date)) {
            return null;
        }

        $year = date('Y', $dateRelative);
        $in = [
            //            // Sunday 31-Jul
            '/^\s*(\w+)\s+(\d+)-(\w+)\s*$/iu',
            // 17-Jun-2022 15:14
            '/^\s*(\d+)-(\w+)-(\d{4})\s+(\d{1,2}:\d{2}(?:\s*[ap]m)?)\s*$/ui',
        ];
        $out = [
            '$1, $3 $2 ' . $year,
            '$1 $2 $3, $4',
        ];

        $date = preg_replace($in, $out, $date);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#u", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        if (preg_match("#^(?<week>\w+), (?<date>\d+ \w+ .+)#u", $date, $m)) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($m['week'], $this->lang));
            $date = EmailDateHelper::parseDateUsingWeekDay($m['date'], $weeknum);
        } elseif (preg_match("#\b\d{4}\b#", $date)) {
            $date = strtotime($date);
        } else {
            $date = null;
        }

//        $this->logger->debug('date end = ' . print_r( $date, true));

        return $date;
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
