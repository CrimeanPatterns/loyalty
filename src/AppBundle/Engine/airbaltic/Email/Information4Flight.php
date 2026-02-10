<?php

namespace AwardWallet\Engine\airbaltic\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Information4Flight extends \TAccountChecker
{
    public $mailFiles = "airbaltic/it-4527283.eml, airbaltic/it-4999896.eml, airbaltic/it-5021401.eml, airbaltic/it-6119555.eml, airbaltic/it-6171636.eml, airbaltic/it-8561049.eml, airbaltic/it-8708519.eml, airbaltic/it-8827710.eml";

    public $langDetectors = [
        'en' => ['Your flight', 'Your upcoming trip', 'Review booking'],
        'de' => ['für Ihren Flug', 'Buchungsnummer'],
        'ru' => ['Ваш полет уже скоро', 'НОМЕР РЕЗЕРВАЦИИ'],
    ];
    public $lang = '';
    public $date;
    public $textSubject;
    public static $dict = [
        "en" => [
            "passengerRegexp"   => "^([^\d]{4,}),\s*(?:your flight is booked|you’re flying to|your flight [A-Z\d]{2}\d+ is|it’s time to check in for your flight)",
            "Outbound"          => ["Outbound", "Return"],
            'Review booking'    => ['Review booking', 'REVIEW BOOKING'],
            'Booking reference' => ['Booking reference', 'BOOKING REFERENCE'],
        ],
        "de" => [
            "Booking reference"=> ["Buchungsnummer"],
            "passengerRegexp"  => "(.*?), (es ist Zeit für den Check-In Ihres Fluges|Ihr Flug nach)",
            "FLIGHT"           => "Flug",
            "Review booking"   => "Buchungsdetails ansehen",
            "Passengers"       => "Fluggäste",
            "Flights"          => "Flüge",
            'Outbound'         => ["Hinflug", "Rückflug"], // to check "Rückflug"
            '(Fare)'           => '(Flugpreis)',
            'TOTAL'            => 'INSGESAMT',
            'Seat'             => 'Sitz', // to check
        ],
        "ru" => [
            "Booking reference"=> ["НОМЕР РЕЗЕРВАЦИИ"],
            //            "passengerRegexp"  => "(.*?), (es ist Zeit für den Check-In Ihres Fluges|Ihr Flug nach)",
            "FLIGHT"           => "НОМЕР РЕЙСА",
            //            "Review booking"   => "Buchungsdetails ansehen",
            //            "Passengers"       => "Fluggäste",
            //            "Flights"          => "Flüge",
            //            'Outbound'         => ["Hinflug", "Rückflug"], // to check "Rückflug"
            //            '(Fare)'           => '(Flugpreis)',
            //            'TOTAL'            => 'INSGESAMT',
            //            'Seat'             => 'Sitz', // to check
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $type = '';
        $this->date = strtotime($parser->getHeader('date'));
        $this->textSubject = $parser->getSubject();
        $this->AssignLang();

        $emailBody = $this->http->Response['body'];

        $url = $this->http->FindSingleNode("//a[(" . $this->eq($this->t("Review booking")) . ") and contains(@href, 'tickets.airbaltic.com')]/@href");

        if (!empty($url)) {
            $res = $this->http->GetURL($url);
            $newUrl = $this->http->FindSingleNode("//form[contains(@action, '/retrieve?')]/@action");
            $xpath = "//form/input";
            $nodes = $this->http->XPath->query($xpath);
            $params = [];

            foreach ($nodes as $root) {
                $name = $this->http->FindSingleNode("./@name", $root);
                $value = $this->http->FindSingleNode("./@value", $root);

                if (!empty($name) && !empty($value)) {
                    $params[] = $name . '=' . rawurlencode($value);
                }
            }

            if (!empty($newUrl) && !empty($params)) {
                $params = implode('&', $params);
                $newUrl .= '&' . $params;
                $this->http->GetURL('https://tickets.airbaltic.com' . $newUrl);
                $type = 'Url';
                $this->flightUrl($email);
            }
        }

        if (empty($email->getItineraries())) {
            $this->http->SetEmailBody($emailBody);
            $type = 'Email';
            $this->flight($email);
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . $type . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query('//node()[contains(.,"AS Air Baltic Corporation")]')->length > 0
            && $this->http->XPath->query('//a[contains(@href,"//links.airbaltic.com/")]')->length > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['from'], 'flights@info.airbaltic.com') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'info.airbaltic.com') !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function flight(Email $email)
    {
        $f = $email->add()->flight();

        if (preg_match("#" . $this->t('passengerRegexp') . "#ui", $this->textSubject, $matches)) {
            $f->general()->traveller(preg_replace('/^fwd?:?\s*/i', '', $matches[1]), true);
        }
        $f->general()->confirmation($this->http->FindSingleNode("//text()[" . $this->eq($this->t('Booking reference')) . "]/following::text()[normalize-space(.)][1]"), is_array($t = $this->t('Booking reference')) ? $t[0] : $t);

        $pattern1 = '/(?:(.+?)\s*\(([A-Z]{3})\)|(.+))\s+(\d{1,2}\/\d{1,2}\/\d{2,4},?\s+\d{1,2}:\d{2})/';
        $pattern2 = '/(?:(.+?)\s*\(([A-Z]{3})\)|(.+))\s*.*\s+(\d{1,2}:\d{2})/';

        $s = $f->addSegment();
        $flightPattern = [
            "en" => [
                '#Useful\s+Information\s+for\s+Your\s+Flight\s+(?<al>[A-Z\d]{2})\s*(?<fn>\d+)\b#i',
                '#your flight\s+(?<al>[A-Z\d]{2})\s*(?<fn>\d+)\s+is \d+ weeks away#',
                '#it’s time to check in for your flight\s+(?<al>[A-Z\d]{2})\s*(?<fn>\d+)\s+on#',
            ],
            "de" => [
                '#es ist Zeit für den Check-In Ihres Fluges\s+(?<al>[A-Z\d]{2})\s*(?<fn>\d+)\s+am#',
            ],
        ];

        if (!empty($node = $this->http->FindSingleNode("(//text()[" . $this->eq($this->t('Booking reference')) . "]/ancestor::table[1]/following-sibling::table[1]/descendant::td[1][starts-with(normalize-space(.),'" . $this->t('FLIGHT') . "')]//text()[normalize-space(.)!=''])[last()]")) && preg_match('/([A-Z\d]{2})\s*(\d+)/i', $node, $matches)) {
            $s->airline()
                ->name($matches[1])
                ->number($matches[2]);
        } else {
            if (!empty($this->lang) && !empty($flightPattern[$this->lang]) && !empty($this->textSubject)) {
                foreach ($flightPattern[$this->lang] as $value) {
                    if (preg_match($value, $this->textSubject, $matches)) {
                        $s->airline()
                            ->name($matches[1])
                            ->number($matches[2]);

                        break;
                    }
                }
            }

            if (empty($s->getAirlineName()) && empty($s->getFlightNumber())) {
                $s->airline()
                    ->noName()
                    ->noNumber();
            }
        }
        $departure = $this->http->FindSingleNode('(//text()[' . $this->eq($this->t("Booking reference")) . ']/ancestor::table[1]/following-sibling::table[normalize-space()][1]//td[(contains(.,"(") or contains(.,"/")) and contains(.,":") and not(.//td)])[1]');

        if (preg_match($pattern1, $departure, $matches)) {
            $s->departure()
                ->name($matches[1] ? trim($matches[1]) : $matches[3])
                ->date(strtotime(str_replace('/', '.', str_replace(',', '', $matches[4]))));

            if (!empty($matches[2])) {
                $s->departure()
                    ->code($matches[2]);
            } else {
                $s->departure()
                    ->noCode();
            }
        }
        $arrival = $this->http->FindSingleNode('(//text()[' . $this->eq($this->t("Booking reference")) . ']/ancestor::table[1]/following-sibling::table[normalize-space()][1]//td[(contains(.,"(") or contains(.,"/")) and contains(.,":") and not(.//td)])[2]');

        if (preg_match($pattern1, $arrival, $matches)) {
            $s->arrival()
                ->name($matches[1] ? trim($matches[1]) : $matches[3])
                ->date(strtotime(str_replace('/', '.', str_replace(',', '', $matches[4]))));

            if (!empty($matches[2])) {
                $s->arrival()
                    ->code($matches[2]);
            } else {
                $s->arrival()
                    ->noCode();
            }
        } elseif (preg_match($pattern2, $arrival, $matches)) {
            $s->arrival()
                ->name($matches[1] ? trim($matches[1]) : $matches[3])
                ->date(strtotime($matches[4], $s->getDepDate()));

            if (!empty($matches[2])) {
                $s->arrival()
                    ->code($matches[2]);
            } else {
                $s->arrival()
                    ->noCode();
            }
        }

        return $email;
    }

    private function flightUrl(Email $email)
    {
        if (empty($this->http->FindSingleNode("//text()[(" . $this->eq($this->t("Flights")) . ") and not(./ancestor::a)]/ancestor::tr[2]/following-sibling::tr[normalize-space()][1]"))) {
            return null;
        }

        $f = $email->add()->flight();

        $f->general()->confirmation($this->http->FindSingleNode("//text()[" . $this->eq($this->t('Booking reference')) . "][1]", null, true, "#:\s*([A-Z\d]{5,7})\s*$#"), is_array($t = $this->t('Booking reference')) ? $t[0] : $t);

        $f->general()->travellers($this->http->FindNodes("//text()[(" . $this->eq($this->t("Passengers")) . ") and not(./ancestor::a)]/ancestor::tr[2]/following-sibling::tr[normalize-space()][1]/descendant::table[not(.//table) and string-length(normalize-space(.))>5][2]/ancestor::tr[1]/ancestor::*[1]/tr/td[1][normalize-space()]/descendant::td[1]"), true);
        $accounts = array_filter($this->http->FindNodes("//text()[(" . $this->eq($this->t("Passengers")) . ") and not(./ancestor::a)]/ancestor::tr[2]/following-sibling::tr[normalize-space()][1]/descendant::table[not(.//table) and string-length(normalize-space(.))>5][2]/ancestor::tr[1]/ancestor::*[1]/tr/td[1][normalize-space()]/descendant::td[2]", null, "#PINS\s*(\d{5,})#"));

        if (!empty($accounts)) {
            $f->program()->accounts($accounts, false);
        }

        // Price
        $costs = array_filter($this->http->FindNodes("//text()[contains(normalize-space(.), '" . $this->t('(Fare)') . "')]/ancestor::tr[1]", null, "#^\s*(\d[\d .,]+)\s*[A-Z]{3}\s*\(#"));
        $cost = null;

        foreach ($costs as $value) {
            $cost = (!empty($cost)) ? $cost + $this->amount($value) : $this->amount($value);
        }
        $currency = $this->http->FindSingleNode("(//text()[contains(normalize-space(.), '" . $this->t('(Fare)') . "')])[1]/ancestor::tr[1]", null, true, "#^\s*\d[\d .,]+\s*([A-Z]{3})\s*\(#");

        if (empty($currency)) {
            $currency = $this->http->FindSingleNode("//text()[contains(normalize-space(.), '" . $this->t('(Fare)') . "')][1]/following::text()[" . $this->contains($this->t('TOTAL')) . "]", null, true, "#^\s*\d[\d .,]+\s*([A-Z]{3})\b#");
        }
        $f->price()
            ->cost($cost)
            ->currency($currency)
            ->total($this->amount($this->http->FindSingleNode("//text()[contains(normalize-space(.), '" . $this->t('(Fare)') . "')][1]/following::text()[" . $this->contains($this->t('TOTAL')) . "]", null, true, "#^\s*(\d[\d .,]+)\s*[A-Z]{3}#")));

        $xpath = "//text()[contains(normalize-space(.), '" . $this->t('(Fare)') . "')]/ancestor::tr[1]/following-sibling::tr[normalize-space()]";
        $nodes = $this->http->XPath->query($xpath);
        $fees = [];

        foreach ($nodes as $root) {
            $name = trim($this->http->FindSingleNode(".", $root, true, "#\((.+)\)\s*$#"));

            if (empty($name)) {
                continue;
            }

            if (isset($fees[$name])) {
                $fees[$name] += $this->amount($this->http->FindSingleNode(".", $root, true, "#^\s*(\d[\d .,]+)#"));
            } else {
                $fees[$name] = $this->amount($this->http->FindSingleNode(".", $root, true, "#^\s*(\d[\d .,]+)#"));
            }
        }

        foreach ($fees as $key => $fee) {
            $f->price()->fee($key, $fee);
        }

        $seatsText = implode("\n", $this->http->FindNodes("//text()[(" . $this->eq($this->t("Passengers")) . ") and not(./ancestor::a)]/ancestor::tr[2]/following-sibling::tr[normalize-space()][1]//td[not(.//td)]"));
        $segments = $this->split("#.+ - .+(\([A-Z\d]{2}\d{1,5}\))#", $seatsText);
        $seats = [];

        foreach ($segments as $value) {
            $airline = $this->re("#^\(([A-Z\d]{2}\d{1,5})\)#", $value);

            if (!empty($airline) && preg_match_all("#" . $this->preg_implode($this->t('Seat')) . "[ ]*(\d{1,3}[A-Z])\b#", $value, $m)) {
                $seats[$airline] = $m[1];
            }
        }

        $xpath = "//text()[(" . $this->eq($this->t("Flights")) . ") and not(./ancestor::a)]/ancestor::tr[2]/following-sibling::tr[normalize-space()][1]//text()";

        $flightText = implode("\n", $this->http->FindNodes($xpath));

        $pattern = "#\n\s*(?<all>(?<dTime>\d{1,2}:\d{2})\s+(?<dName>[\s\S]+?)\s*-\s*(?<aTime>\d{1,2}:\d{2})\s+(?<aName>[\s\S]+?)\((?<an>[A-Z\d]{2})(?<fn>\d{1,5})\))#";
        preg_match_all($pattern, $flightText, $flights);

        if (empty($flights[1])) {
            $email->removeItinerary($f);

            return $email;
        }

        foreach ($flights[1] as $key => $value) {
            $s = $f->addSegment();

            $s->airline()
                ->name($flights['an'][$key])
                ->number($flights['fn'][$key]);

            if (preg_match("#^(.+?)Terminal\s*\*\s*(.+)$#", $flights['dName'][$key], $m)) {
                $s->departure()
                    ->noCode()
                    ->name(trim($m[1]))
                    ->terminal(trim($m[2]));
            } else {
                $s->departure()
                    ->noCode()
                    ->name($flights['dName'][$key]);
            }

            if (preg_match("#^(.+?)Terminal\s*\*\s*(.+)$#", $flights['aName'][$key], $m)) {
                $s->arrival()
                    ->noCode()
                    ->name(trim($m[1]))
                    ->terminal(trim($m[2]));
            } else {
                $s->arrival()
                    ->noCode()
                    ->name($flights['aName'][$key]);
            }

            $date = $this->normalizeDate($this->re("#.*" . $this->preg_implode($this->t("Outbound")) . "\s*(.+)[\s\S]*?" . preg_quote($flights[1][$key]) . "#", $flightText));

            if (!empty($date)) {
                $s->departure()->date(strtotime($flights['dTime'][$key], $date));
                $s->arrival()->date(strtotime($flights['aTime'][$key], $date));
            }

            if (isset($seats[$s->getAirlineName() . $s->getFlightNumber()])) {
                $s->extra()->seats($seats[$s->getAirlineName() . $s->getFlightNumber()]);
            }
        }

        return $email;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function AssignLang()
    {
        foreach ($this->langDetectors as $lang => $rows) {
            foreach ($rows as $row) {
                if ($this->http->XPath->query('//node()[contains(.,"' . $row . '")]')->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function normalizeDate($instr)
    {
        $year = date('Y', $this->date);
        $in = [
            "#^\s*([^\d\s]+),\s*([^\d\s]+)\s+(\d{1,2})\s*$#", // Thu, Jul 05
        ];
        $out = [
            "$1, $3 $2",
        ];
        $str = preg_replace($in, $out, $instr);

        if (preg_match("#\d+\s+([^\d\s]+)#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        if (preg_match("#(?<week>[^\d\s]+),\s+(?<date>.+)#", $str, $m)) {
            $week = WeekTranslate::number1($m['week'], $this->lang);

            if (empty($week)) {
                return false;
            }
            $str = EmailDateHelper::parseDateUsingWeekDay($m['date'] . ' ' . $year, $week);
        } else {
            $str = strtotime($str);
        }

        return $str;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
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

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'starts-with(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'contains(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
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

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
    }

    private function amount($s)
    {
        return (float) str_replace(",", ".", preg_replace("#[., ](\d{3})#", "$1", $this->re("#(\d[\d\,\. ]+)#", $s)));
    }

    private function currency($s)
    {
        $sym = [
            '€'=> 'EUR',
            '$'=> 'USD',
            '£'=> 'GBP',
        ];

        if ($code = $this->re("#(?:^|\s|\d)([A-Z]{3})(?:$|\s|\d)#", $s)) {
            return $code;
        }
        $s = $this->re("#([^\d\,\. ]+)#", $s);

        foreach ($sym as $f=> $r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        return null;
    }
}
