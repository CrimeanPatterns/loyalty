<?php

namespace AwardWallet\Engine\byojet\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ETicket extends \TAccountChecker
{
    public $mailFiles = "byojet/it-59415595.eml, byojet/it-59512467.eml";
    public static $dict = [
        'en' => [],
    ];

    private $detectFrom = "@byojet.com";
    private $detectSubject = [
        // BOOKING ITINERARY / eTicket #BYO11252339 - Flights from London to Manila
        // BOOKING ITINERARY #BYO11252339 - Flights from London to Manila
        'BOOKING ITINERARY',
    ];
    private $detectCompany = 'BYOjet Reservation';

    private $detectBody = [
        'en' => ['Booking itinerary', 'Booking itinerary / eTicket'],
    ];
    private $lang = 'en';

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $this->parseFlight($email);

        return $email;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (stripos($body, $this->detectCompany) === false) {
            return false;
        }

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

    private function parseFlight(Email $email)
    {
        // Travel Agency
        $email->ota()
            ->confirmation($this->http->FindSingleNode("//text()[" . $this->eq('BYOjet Reservation') . "]/following::text()[normalize-space()][1]", null, true, "#^\s*([A-Z\d]{5,})\s*$#"),
                'BYOjet Reservation');

        // Flight
        $f = $email->add()->flight();

        // General
        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[" . $this->eq('Airline Reference') . "]/following::text()[normalize-space()][1]", null, true, "#^\s*([A-Z\d]{5,})\s*$#"),
                'Airline Reference')
        ;

        $travellerCol = count($this->http->FindNodes("(//td[" . $this->eq('Passengers') . "])[1]/preceding-sibling::td"));

        if (!empty($this->http->FindSingleNode("(//td[" . $this->eq('Passengers') . "])[1]"))) {
            $travellerCol++;
        }
        $f->general()
            ->travellers(array_unique($this->http->FindNodes("//td[" . $this->eq('Passengers') . "]/ancestor::tr[1]/following-sibling::tr/td[" . $travellerCol . "]/descendant::text()[normalize-space()][1]", null, "#^\s*(?:(?:MR|MISS|MRS|DR) )?(.+)#")),
                true)
        ;

        // Issued
        $tickets = array_unique(array_filter($this->http->FindNodes("//text()[" . $this->starts('eTicket ') . "]", null, "#eTicket\s+([\d\-]{10,})\s*$#")));

        if (!empty($tickets)) {
            $f->issued()->tickets($tickets, false);
        }

        // Program
        $ffCol = count($this->http->FindNodes("(//td[" . $this->eq('Frequent flyer') . "])[1]/preceding-sibling::td"));

        if (!empty($this->http->FindSingleNode("(//td[" . $this->eq('Frequent flyer') . "])[1]"))) {
            $ffCol++;
            $accounts = array_unique(array_filter($this->http->FindNodes("//td[" . $this->eq('Frequent flyer') . "]/ancestor::tr[1]/following-sibling::tr/td[" . $ffCol . "]/descendant::text()[normalize-space()][1]", null, "#^\s*(\w+\d\w+)\s*$#i")));

            if (!empty($accounts)) {
                $f->program()
                    ->accounts($accounts, false);
            }
        }

        // Price
        $total = $this->http->FindSingleNode("//td[" . $this->eq("Total") . " and not(.//td)]/following::td[normalize-space()][1]", null, true, "#Paid\s*(.+)#");

        if (!empty($total) && (preg_match("#^\s*(?<curr>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $total, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<curr>[^\d\s]{1,5})\s*$#", $total, $m))) {
            $f->price()
                ->total($this->amount($m['amount']))
                ->currency($this->currency($m['curr']))
            ;
        }

        $xpath = "//table[not(normalize-space()) and count(.//img) = 2and .//img[@width=20]]/ancestor::*[1]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            // Airline
            $text = $this->http->FindSingleNode("./*[normalize-space()][2]/descendant::tr[1]/td[normalize-space()][1]", $root);

            if (preg_match("# - (?<al>[A-Z][A-Z\d]|[A-Z][A-Z\d]) ?(?<fn>\d{1,5})\s*$#", $text, $m)) {
                $s->airline()
                    ->name($m['al'])
                    ->number($m['fn'])
                ;
            }

            $regexp = "#(?<name>[\s\S]+)\n(?<terminal>.*\bTerminal\b.*)\n(?<date>.+)#";
            // Departure
            $text = implode("\n", $this->http->FindNodes("./*[normalize-space()][1]//tr[not(.//tr)]/td[1]", $root));

            if (preg_match($regexp, $text, $m)) {
                $s->departure()
                    ->noCode()
                    ->name(preg_replace("#\s*\n\s*#", ', ', trim($m['name'])))
                    ->date($this->normalizeDate($m['date']))
                    ->terminal(trim(preg_replace("#\bTerminal( TBD)?\s*#i", '', $m['terminal'])), true, true)
                ;
            }

            // Arrival
            $text = implode("\n", $this->http->FindNodes("./*[normalize-space()][1]//tr[not(.//tr)]/td[2]", $root));

            if (preg_match($regexp, $text, $m)) {
                $s->arrival()
                    ->noCode()
                    ->name(preg_replace("#\s*\n\s*#", ', ', trim($m['name'])))
                    ->date($this->normalizeDate($m['date']))
                    ->terminal(trim(preg_replace("#\bTerminal( TBD)?\s*#i", '', $m['terminal'])), true, true)
                ;
            }

            // Extra
            $aircraft = $this->http->FindSingleNode("./*[normalize-space()][2]/descendant::tr[1]/td[normalize-space()][position()>1][.//img[@alt='plane']]", $root);

            if (empty($aircraft)) {
                $aircraft = $this->http->FindSingleNode("./*[normalize-space()][2]/descendant::tr[1]/td[normalize-space()][2][not(.//img[string-length(@alt) > 1]) and not(contains(., 'Class'))]", $root);
            }
            $cabin = $this->http->FindSingleNode("./*[normalize-space()][2]/descendant::tr[1]/td[normalize-space()][position()>1][.//img[@alt='ticket']]", $root);

            if (empty($cabin)) {
                $cabin = $this->http->FindSingleNode("./*[normalize-space()][2]/descendant::tr[1]/td[normalize-space()][position()>1][contains(., 'Class')][last()]", $root);
            }
            $s->extra()
                ->aircraft($aircraft, true, true)
                ->cabin(preg_replace("#\s*Class\s*#i", '', $cabin))
            ;

            $seatsCol = count($this->http->FindNodes(".//td[" . $this->eq('Seats') . "]/preceding-sibling::td", $root));

            if (!empty($this->http->FindSingleNode(".//td[" . $this->eq('Seats') . "]", $root))) {
                $seatsCol++;
                $seats = array_filter($this->http->FindNodes(".//td[" . $this->eq('Seats') . "]/ancestor::tr[1]/following-sibling::tr/td[" . $seatsCol . "]/descendant::text()[normalize-space()][1]", $root, "#^\s*(\d{1,3}[A-Z])\s*#"));

                if (!empty($seats)) {
                    $s->extra()->seats($seats);
                }
            }
        }

        return true;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function normalizeDate($date)
    {
        $in = [
            // 5:50 PM - Thursday, 13 February 2020
            '#^\s*(\d{1,2}:\d{2}(?:\s*[ap]m)?)\s*-\s*\w+[, ]+(\d+)\s+(\w+)\s+(\d{4})\s*$#iu',
        ];
        $out = [
            '$2 $3 $4, $1',
        ];

        $date = preg_replace($in, $out, $date);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        return strtotime($date);
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function amount($price)
    {
        $price = str_replace([',', ' '], '', $price);

        if (is_numeric($price)) {
            return (float) $price;
        }

        return null;
    }

    private function currency($s)
    {
        if ($code = $this->re("#^\s*([A-Z]{3})\s*$#", $s)) {
            return $code;
        }
        $sym = [
            '€' => 'EUR',
            '$' => 'USD',
            '£' => 'GBP',
        ];

        foreach ($sym as $f => $r) {
            if ($s == $f) {
                return $r;
            }
        }

        return null;
    }
}
