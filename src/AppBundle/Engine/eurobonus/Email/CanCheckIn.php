<?php

namespace AwardWallet\Engine\eurobonus\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class CanCheckIn extends \TAccountChecker
{
    public $mailFiles = "eurobonus/it-32473496.eml, eurobonus/it-32473591.eml, eurobonus/it-32649026.eml, eurobonus/it-33060701.eml, eurobonus/it-33924599.eml, eurobonus/it-34073201.eml, eurobonus/it-351606014.eml, eurobonus/it-671636489.eml, eurobonus/it-668969434.eml";

    public $lang = '';
    public static $dictionary = [
        "en" => [
            "BOOKING REFERENCE:" => "BOOKING REFERENCE:",
            "YOUR TRIP"          => "YOUR TRIP",
        ],
        "no" => [
            "BOOKING REFERENCE:" => "BESTILLINGSREFERANSE:",
            "YOUR TRIP"          => ["DIN REISE", "DERES REISE"],
        ],
        "sv" => [
            "BOOKING REFERENCE:" => "BOKNINGSREFERENS:",
            "YOUR TRIP"          => ["DIN RESA", "ER RESA"],
        ],
        "da" => [
            "BOOKING REFERENCE:" => "BOOKINGREFERENCE:",
            "YOUR TRIP"          => ["DIN REJSE", "JERES REJSE"],
        ],
    ];

    private $detectFrom = ["@sas.", "flysas.com"];

    private $detectSubject = [
        // en
        "You can now check in at sas",
        "Thank you for choosing to fly with SAS to",
        // no
        "Nå kan du sjekke inn via sas.no",
        "Takk for at du velger å fly med SAS til",
        "Tre dager igjen til din ",
        "Takk for at dere velger å fly med SAS til",
        // sv
        "Nu kan du checka in via sas.se",
        "Tack för att du väljer att flyga med SAS till",
        "Tre dagar kvar till din ",
        "-resan lite extra bekväm",
        // da
        "Nu kan du checke ind via sas.dk",
        "Tak for at du vælger at flyve med SAS til",
    ];

    public function parseEmail(Email $email): void
    {
        $patterns = [
            'time' => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.
        ];

        $f = $email->add()->flight();

        // General
        $confirmation = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t("BOOKING REFERENCE:"))}][1]/following::text()[normalize-space()][1]", null, true, '/^[A-Z\d]{5,7}$/');

        if ($confirmation) {
            $confirmationTitle = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t("BOOKING REFERENCE:"))}][1]", null, true, '/^(.+?)[\s:：]*$/u');
            $f->general()->confirmation($confirmation, $confirmationTitle);
        } elseif (preg_match("/^({$this->opt($this->t("BOOKING REFERENCE:"))})[:\s]*([A-Z\d]{5,7})$/", $this->http->FindSingleNode("descendant::text()[{$this->starts($this->t("BOOKING REFERENCE:"))}][1]"), $m)) {
            $f->general()->confirmation($m[2], rtrim($m[1], ': '));
        }

        // Segments
        $xpathAirportCode = 'translate(normalize-space(),"ABCDEFGHIJKLMNOPQRSTUVWXYZ","∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆")="∆∆∆"';
        $xpathCodes = "descendant::text()[{$this->contains($this->t("BOOKING REFERENCE:"))}][1]/preceding::tr[not(.//tr) and normalize-space()][1]";
        $airportCodesVal = $this->http->FindSingleNode($xpathCodes);

        if (preg_match("/^([A-Z]{3})\s*([A-Z]{3})$/", $airportCodesVal, $m)) {
            // KEF     OSL
            $codeDep = $m[1];
            $codeArr = $m[2];
        } elseif (preg_match("/^[A-Z]{3}$/", $airportCodesVal)) {
            // KEF

            if ($this->http->XPath->query($xpathCodes . "/descendant::img/preceding::text()[normalize-space()][1][{$xpathAirportCode}]")->length > 0) {
                $codeDep = $airportCodesVal;
                $codeArr = null;
            } else {
                $codeDep = null;
                $codeArr = $airportCodesVal;
            }
        } else {
            $codeDep = $codeArr = null;
        }

        $segFragments = [];
        $segRows = $this->http->XPath->query("descendant::text()[{$this->contains($this->t("BOOKING REFERENCE:"))}][1]/ancestor::tr[ following-sibling::tr[normalize-space()] ][1]/following::tr[not(.//tr) and normalize-space()]");

        foreach ($segRows as $row) {
            if ($this->http->XPath->query("descendant::a[normalize-space() and @href]", $row)->length > 0) {
                break;
            }
            $segFragments[] = $this->htmlToText($this->http->FindHTMLByXpath('.', null, $row));
        }

        /*
            24MAY23
            11:05 REYKJAVIK (SK4788)
            15:45 OSLO
            Direct: SAS Go
        */

        $segTexts = preg_split("/[ ]*\n+[ ]*/", implode("\n", $segFragments));

        if (count($segTexts) < 2) {
            $this->logger->debug('Flight segment not found!');

            return;
        }

        $s = $f->addSegment();

        $date = $this->normalizeDate($segTexts[0]);

        $timeDep = $nameDep = $airlineName = $flightNumber = null;

        if (preg_match("/^(?<time>{$patterns['time']})\s+(?<airport>\S.+\S)\s*\(\s*(?<airlineName>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<flightNumber>\d+)\s*\)$/", $segTexts[1], $m)) {
            // 13:40 NCE (SK 4704)
            $timeDep = $m['time'];
            $nameDep = $m['airport'];
            $airlineName = $m['airlineName'];
            $flightNumber = $m['flightNumber'];
        } elseif (preg_match("/^(?<time>{$patterns['time']})\s*\(\s*(?<airlineName>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<flightNumber>\d+)\s*\)$/", $segTexts[1], $m)) {
            // 06:00 (SK1758)
            $timeDep = $m['time'];
            $airlineName = $m['airlineName'];
            $flightNumber = $m['flightNumber'];
        } elseif (preg_match("/^[(\s]*(?<airlineName>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<flightNumber>\d+)[\s)]*$/", $segTexts[1], $m)) {
            // (SK849)
            $airlineName = $m['airlineName'];
            $flightNumber = $m['flightNumber'];
        }

        if (preg_match("/^[A-Z]{3}$/", $nameDep)) {
            $codeDep = $nameDep;
            $nameDep = null;
        }

        $timeArr = $overnight = $nameArr = null;

        if (count($segTexts) > 2 && preg_match("/^(?<time>{$patterns['time']})(?:\s*\(\s*(?<overnight>[-+]\s*\d{1,3})\s*\)\s*|\s+)(?<airport>\S.+\S)$/", $segTexts[2], $m)) {
            // 16:35 OSL    |    16:35 (+1) OSL
            $timeArr = $m['time'];
            $nameArr = $m['airport'];

            if (!empty($m['overnight'])) {
                $overnight = $m['overnight'];
            }
        } elseif (count($segTexts) > 2 && preg_match("/^[:]+\s*(?<airport>\S.+\S)$/", $segTexts[2], $m)) {
            // : Copenhagen
            $nameArr = $m['airport'];
        }

        if (preg_match("/^[A-Z]{3}$/", $nameArr)) {
            $codeArr = $nameArr;
            $nameArr = null;
        }

        $stops = null;

        if (count($segTexts) > 3 && preg_match("/^([\w\s]+?):/", $segTexts[3], $matches)) {
            if (preg_match("/^(\d{1,3})\s*[[:alpha:]]+$/u", $matches[1], $m)) {
                // 1 Stop
                $stops = $m[1];
            } elseif (preg_match("/^[[:alpha:]]+$/u", $matches[1], $m)) {
                // Direct
                $stops = 0;
            }
        }

        $s->airline()->name($airlineName)->number($flightNumber);
        $s->extra()->stops($stops, false, true);

        if ($codeDep) {
            $s->departure()->code($codeDep);
        } elseif ($nameDep) {
            $s->departure()->noCode();
        }

        if ($codeArr) {
            $s->arrival()->code($codeArr);
        } elseif ($nameArr) {
            $s->arrival()->noCode();
        }

        if ($nameDep) {
            $s->departure()->name($nameDep);
        }

        if ($nameArr) {
            $s->arrival()->name($nameArr);
        }

        if (!$date) {
            $this->logger->debug('Segment date is wrong!');

            return;
        }

        $dateDep = $date;

        if ($overnight) {
            $dateArr = strtotime($overnight . ' days', $date);
        } else {
            $dateArr = $date;
        }

        if ($timeDep) {
            $s->departure()->date(strtotime($timeDep, $dateDep));
        } elseif (!$timeDep) {
            $s->departure()->day($dateDep)->noDate();
        }

        if ($timeArr) {
            $s->arrival()->date(strtotime($timeArr, $dateArr));
        } elseif (!$timeArr) {
            if ($dateArr !== $dateDep) {
                $s->arrival()->day($dateArr);
            }

            $s->arrival()->noDate();
        }
    }

    public function parseStatement(Email $email): void
    {
        $xpath = "//img[contains(@src,'SAS-logo-header.png')]/ancestor::tr[normalize-space()][1]";
        $nodes = implode("\n", $this->http->FindNodes($xpath . "//td[not(.//td)][normalize-space()]"));

        if (empty($nodes)) {
            return;
        }

        if (preg_match("/^\s*(?<name>[[:alpha:] \-]+)\s*\n(?:\s*[[:alpha:] \-]+\n)?\s*(?<number>[A-Z]{3} \d{5,})\s*\n\s*([A-Z]{2})\s*\n\s*(?<points>\d[\d ]*) [[:alpha:]]+\s*\n\s*[[:alpha:] ]+ (?<date>\d{1,2}\.\d{2}\.\d{4})\s*$/u", $nodes, $m)) {
            $st = $email->add()->statement();

            $st
                ->setBalance(str_replace(' ', '', $m['points']))
                ->setBalanceDate(strtotime($m['date']))
                ->setNumber($m['number'])
                ->setLogin($m['number'])
                ->addProperty('Name', $m['name'])
            ;
            $levelText = $this->http->FindSingleNode($xpath . "//*[contains(@background, '/header-circle-')]/@background", null, true, "/\\/header-circle-(\w+)\./");

            switch ($levelText) {
                case 'blue': $level = 'Member';

break;

                case 'silver': $level = 'Silver';

break;

                case 'gold': $level = 'Gold';

break;

                case 'diamond': $level = 'Diamond';

break;
            }

            if (!empty($level)) {
                $st
                    ->addProperty('Level', $level)
                ;
            }

            $email->getItineraries()[0]->addTraveller($m['name'], true);
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict['YOUR TRIP']) && !empty($dict['BOOKING REFERENCE:'])
                && !empty($this->http->FindSingleNode("//text()[" . $this->eq($dict['YOUR TRIP']) . "][following::tr[not(.//tr)][normalize-space()][2][" . $this->starts($dict['BOOKING REFERENCE:']) . "]]/following::tr[not(.//tr)][normalize-space()][1]",
                    null, true, "/^\s*[A-Z]{3}\s*[A-Z]{3}\s*$/"))
            ) {
                $this->lang = $lang;

                break;
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $this->parseEmail($email);

        if (!empty($email->getItineraries())) {
            $this->parseStatement($email);
        }

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->detectFrom as $dFrom) {
            if (strpos($from, $dFrom) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers["from"]) || empty($headers["subject"])) {
            return false;
        }

//        $foundFrom = false;
//        foreach ($this->detectFrom as $dFrom) {
//            if (strpos($headers["from"], $dFrom) !== false) {
//                $foundFrom = true;
//            }
//        }
//        if ($foundFrom === false) {
//            return false;
//        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers["subject"], $dSubject) !== false
                && (preg_match("/\bsas\b/i", $dSubject) !== false || $this->containsText($headers["from"], $this->detectFrom))) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//*[contains(., 'Scandinavian Airlines ©')] | //a[contains(@href, 'flysas.com')]")->length === 0) {
            return false;
        }

        foreach (self::$dictionary as $dict) {
            if (!empty($dict['YOUR TRIP']) && !empty($dict['BOOKING REFERENCE:'])
                && !empty($this->http->FindSingleNode("//text()[" . $this->eq($dict['YOUR TRIP']) . "][following::tr[not(.//tr)][normalize-space()][2][" . $this->starts($dict['BOOKING REFERENCE:']) . "]]/following::tr[not(.//tr)][normalize-space()][1]",
                    null, true, "/^\s*[A-Z]{3}\s*[A-Z]{3}\s*$/"))
            ) {
                return true;
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary) * 2;
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
//        $this->logger->debug('$str = '.print_r( $str,true));
        $in = [
            // 21JAN19
            "#^\s*(\d{1,2})([^\s\d\.\,]+)(\d{2})\s*$#",
            // Lørdag 19 mars 2022
            "#^\s*[[:alpha:]]+[,\s]+(\d{1,2})\s+([[:alpha:]]+)\s+(\d{4})\s*$#u",
        ];
        $out = [
            '$1 $2 20$3',
            '$1 $2 $3',
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) { return "normalize-space(.)=\"{$s}\""; }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) { return "starts-with(normalize-space(.), \"{$s}\")"; }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) { return "contains(normalize-space(.), \"{$s}\")"; }, $field));
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

    private function containsText($text, $needle)
    {
        if (empty($text) || empty($needle)) {
            return false;
        }

        if (is_array($needle)) {
            foreach ($needle as $n) {
                $p = stripos($text, $n);

                if ($p !== false) {
                    return $p;
                }
            }
        } elseif (is_string($needle)) {
            return stripos($text, $needle);
        }

        return false;
    }

    private function htmlToText(?string $s, bool $brConvert = true): string
    {
        if (!is_string($s) || $s === '') {
            return '';
        }
        $s = str_replace("\r", '', $s);
        $s = preg_replace('/<!--.*?-->/s', '', $s); // comments

        if ($brConvert) {
            $s = preg_replace('/\s+/', ' ', $s);
            $s = preg_replace('/<[Bb][Rr]\b.*?\/?>/', "\n", $s); // only <br> tags
        }
        $s = preg_replace('/<[A-z][A-z\d:]*\b.*?\/?>/', '', $s); // opening tags
        $s = preg_replace('/<\/[A-z][A-z\d:]*\b[ ]*>/', '', $s); // closing tags
        $s = html_entity_decode($s);
        $s = str_replace(chr(194) . chr(160), ' ', $s); // NBSP to SPACE

        return trim($s);
    }
}
