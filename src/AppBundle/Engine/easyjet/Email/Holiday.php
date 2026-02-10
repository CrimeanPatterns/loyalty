<?php

namespace AwardWallet\Engine\easyjet\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Holiday extends \TAccountChecker
{
    public $mailFiles = "easyjet/it-32211020.eml, easyjet/it-33032266.eml, easyjet/it-33434029.eml";

    public $lang = "en";
    public static $dictionary = [
        "en" => [
            //            "Holiday package booking reference:" => "",
            //            "Flight booking reference:" => "",
            //            "Booking date:" => "",
            //            "Accommodation details" => "",
            "#peopleRe#" => "#\n\s*(?<guests>\d+)\s*people staying in (?<rooms>\d+) room\(s\)#",
            //            "night(s)" => "",
            //            "from" => "",
            //            "Flight details" => "",
            //            "Seats" => "",
            //            "Passenger #" => " #", // '#' instead of numbers
            //            "Total holiday price" => "",
        ],
        "de" => [
            "Holiday package booking reference:" => "Buchungsnummer Ihrer Pauschalreise:",
            "Flight booking reference:"          => "Buchungsnummer Ihres Fluges:",
            "Booking date:"                      => "Datum der Buchung:",
            "Accommodation details"              => "Angaben zur unterkunft",
            "#peopleRe#"                         => "#\n\s*(?<guests>\d+)\s*personen in (?<rooms>\d+) Zimmer\(s\)#",
            "night(s)"                           => "Nächte(s)",
            "from"                               => "vom",
            "Flight details"                     => "Angaben zum flug",
            "Seats"                              => "Sitzplätze",
            "Passenger #"                        => "Reisender #", // '#' instead of numbers
            "Total holiday price"                => "Gesamturlaubspreis",
        ],
        "nl" => [
            "Holiday package booking reference:" => "Vakantieboekingsreferentie:",
            "Flight booking reference:"          => "Vluchtreferentie:",
            "Booking date:"                      => "Boekingsdatum:",
            "Accommodation details"              => "Gegevens van de accommodatie",
            "#peopleRe#"                         => "#\n\s*(?<guests>\d+)\s*personen verblijven in (?<rooms>\d+) kamers\(s\)#",
            "night(s)"                           => "nachten(s)",
            "from"                               => "in",
            "Flight details"                     => "Vluchtgegevens",
            //            "Seats" => "",
            "Passenger #"         => "Reiziger #", // '#' instead of numbers
            "Total holiday price" => "Totale prijs",
        ],
    ];

    private $detectFrom = "@holiday.easyjet.com";
    private $detectSubject = [
        "en" => "Your reservation confirmation:",
        "de" => "Ihre Reservierungsbestätigung:",
        "nl" => "Uw bevestiging van de reservering:",
    ];

    private $detectCompanyHtml = 'easyJet holidays';
    private $detectBodyHtml = [
        "en" => "Your Holiday",
        "de" => "Ihr urlaub",
        "nl" => "Uw vakantie",
    ];

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (strpos($headers["from"], $this->detectFrom) === false) {
            return false;
        }

        foreach ($this->detectSubject as $re) {
            if (stripos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//*[contains(.,'{$this->detectCompanyHtml}')]")->length === 0) {
            return false;
        }

        $body = $this->http->Response['body'];

        foreach ($this->detectBodyHtml as $detectBody) {
            if (strpos($body, $detectBody) !== false) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $body = $this->http->Response['body'];

        foreach ($this->detectBodyHtml as $lang => $detectBody) {
            if (strpos($body, $detectBody) !== false) {
                $this->lang = $lang;

                break;
            }
        }
        $this->parseHtml($email);

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

    private function parseHtml(Email $email)
    {
        // Travel Agency
        $email->ota()
            ->confirmation($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Holiday package booking reference:")) . "]/following::text()[normalize-space()][1]", null, true, "#^\s*([A-Z\d]{6,})\s*$#"));

        // Price
        $total = $this->http->FindSingleNode("//td[" . $this->eq($this->t("Total holiday price")) . "]/following::td[normalize-space()][1]");

        if (preg_match("#^\s*(?<curr>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $total, $m)
                || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<curr>[^\d\s]{1,5})\s*$#", $total, $m)) {
            $email->price()
                ->total($this->amount($m['amount']))
                ->currency($this->currency($m['curr']))
            ;
        }

        $travellers = $this->http->FindNodes("//text()[" . $this->starts($this->t('Passenger #'), "translate(normalize-space(.), '0123456789', '##########')") . "]/following::text()[contains(normalize-space(), ' ')][1]", null, "#(.+?),#");
        $bookingDate = $this->normalizeDate($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Booking date:")) . "]/following::text()[normalize-space()][1]"));
        // FLIGHT
        $f = $email->add()->flight();

        // General
        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Flight booking reference:")) . "]/following::text()[normalize-space()][1]", null, true, "#^\s*([A-Z\d]{6,})\s*$#"))
            ->travellers($travellers)
            ->date($bookingDate)
        ;

        // Segments
        $text = $this->re("#\n" . $this->opt($this->t("Flight details")) . "\n(.+)#s", implode("\n", $this->http->FindNodes("//text()[" . $this->eq($this->t("Flight details")) . "]/ancestor::td[1]//text()[normalize-space()]")));
        $regexp = "#(.+\s*-\s*.+\n.+,\s*\d+:\d+\s*-\s*\d+:\d+\n)#";
        $segments = $this->split($regexp, $text);

        foreach ($segments as $stext) {
            $s = $f->addSegment();
            $sRegexp = "#(?<dName>.+)\s*-\s*(?<aName>.+)\n(?<date>.+),\s*(?<dTime>\d+:\d+)\s*-\s*(?<aTime>\d+:\d+)\n(?<fn>\d{1,5})(\n" . $this->opt($this->t("Seats")) . "\s*(?<seats>.+))?(\n|$)?#";

            if (preg_match($sRegexp, $stext, $m)) {
                // Airline
                $s->airline()
                    ->name('U2')
                    ->number($m['fn']);

                // Departure
                $s->departure()
                    ->noCode()
                    ->name($m['dName'])
                    ->date($this->normalizeDate($m['date'] . ' ' . $m['dTime']))
                ;

                // Arrival
                $s->arrival()
                    ->noCode()
                    ->name($m['aName'])
                    ->date($this->normalizeDate($m['date'] . ' ' . $m['aTime']))
                ;

                // Extra
                // no examples for 2 or more seats
                if (!empty($m['seats']) && preg_match_all("#(?:^|\s|,)(\d{1,3}[A-Z])(?:\s|,|$)#", $m['seats'], $mat)) {
                    $s->extra()->seats($mat[1]);
                }
            }
        }

        // HOTEL
        $text = $this->re("#" . $this->opt($this->t("Accommodation details")) . "\n(.+?)\n" . $this->opt($this->t("Flight details")) . "#s", implode("\n", $this->http->FindNodes("//text()[" . $this->eq($this->t("Accommodation details")) . "]/ancestor::td[1]//text()[normalize-space()]")));
        $regexp = "#((.+)\n(.+)\n\s*\d+ " . trim($this->t("#peopleRe#"), '#') . ")#";
        $segments = $this->split($regexp, $text);

        foreach ($segments as $htext) {
            $h = $email->add()->hotel();

            // General
            $h->general()
                ->noConfirmation()
                ->travellers($travellers)
                ->date($bookingDate)
            ;

            // Hotel
            $h->hotel()
                ->name($this->re("#^\s*(.+)#", $htext))
                ->address($this->re("#^\s*.+\n(.+)#", $htext))
            ;

            // Booked
            $h->booked()
                ->checkIn($this->normalizeDate($this->re("#\n\s*\d+\s*" . $this->opt($this->t("night(s)")) . ", " . $this->opt($this->t("from")) . "\s*(.+)#", $htext)))
            ;

            if (!empty($h->getCheckInDate())) {
                $h->booked()
                    ->checkOut(strtotime('+' . $this->re("#\n\s*(\d+)\s*" . $this->opt($this->t("night(s)")) . ", " . $this->opt($this->t("from")) . "#", $htext) . ' days', $h->getCheckInDate()));
            }

            if (preg_match($this->t("#peopleRe#"), $htext, $m)) {
                $h->booked()
                    ->guests($m['guests'] ?? null, true, true)
                    ->rooms($m['rooms'] ?? null, true, true);
            }

            // Rooms
            $h->addRoom()
                ->setType($this->re("#\n\s*\d+\s*" . $this->opt($this->t("night(s)")) . ", " . $this->opt($this->t("from")) . "\s*.+\s+(.+)#", $htext));
        }

        return $email;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($str)
    {
//        $this->http->log($str);
        $in = [
            "#^[^\s\d]+\s+(\d+) ([^\s\d]+) (\d{4})$#", //Sat 02 Mar 2019
        ];
        $out = [
            "$1 $2 $3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function split($re, $text)
    {
        $r = preg_split($re, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        $ret = [];

        if (count($r) > 1) {
            array_shift($r);

            for ($i = 0; $i < count($r) - 1; $i += 2) {
                $ret[] = $r[$i] . $r[$i + 1];
            }
        } elseif (count($r) == 1) {
            $ret[] = reset($r);
        }

        return $ret;
    }

    private function opt($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
    }

    private function amount($s)
    {
        if (($s = $this->re("#([\d\,\.]+)#", $s)) === null) {
            return null;
        }

        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $s));
    }

    private function currency($s)
    {
        $sym = [
            '€'=> 'EUR',
            '$'=> 'USD',
            '£'=> 'GBP',
            '₹'=> 'INR',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }
        $s = $this->re("#([^\d\,\.]+)#", $s);

        foreach ($sym as $f=> $r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        return null;
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    }

    private function starts($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) { return 'starts-with(' . $text . ',"' . $s . '")'; }, $field)) . ')';
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'contains(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }
}
