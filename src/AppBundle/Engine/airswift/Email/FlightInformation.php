<?php

namespace AwardWallet\Engine\airswift\Email;

// TODO: delete what not use
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class FlightInformation extends \TAccountChecker
{
    public $mailFiles = "airswift/it-621586037.eml, airswift/it-622890490.eml, airswift/it-623019751.eml, airswift/it-721849221.eml";

    public $lang = 'en';
    public static $dictionary = [
        'en' => [
            'Flight Itinerary' => 'Flight Itinerary',
            'Additional Items' => 'Additional Items',
            'Booking Code:'    => ['Booking Code:', 'Booking reference:'],
        ],
    ];

    private $detectFrom = "itinerary@air-swift.com";
    private $detectSubject = [
        // en
        'AirSWIFT Flight Information',
    ];

    // Main Detects Methods
    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@.]air-swift\.com$/", $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers["from"], $this->detectFrom) === false
            && strpos($headers["subject"], 'AirSWIFT') === false
        ) {
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
            $this->http->XPath->query("//a[{$this->contains(['air-swift.com/'], '@href')}]")->length === 0
            && $this->http->XPath->query("//*[{$this->contains(['Email: info@air-swift.com'])}]")->length === 0
        ) {
            return false;
        }

        foreach (self::$dictionary as $dict) {
            if (!empty($dict['Flight Itinerary']) && $this->http->XPath->query("//*[{$this->contains($dict['Flight Itinerary'])}]")->length > 0
                && !empty($dict['Additional Items']) && $this->http->XPath->query("//*[{$this->contains($dict['Additional Items'])}]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        // $this->assignLang();
        // if (empty($this->lang)) {
        //     $this->logger->debug("can't determine a language");
        //     return $email;
        // }

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
            if (!empty($dict["Flight Itinerary"])) {
                if ($this->http->XPath->query("//*[{$this->contains($dict['Flight Itinerary'])}]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function parseEmailHtml(Email $email)
    {
        $f = $email->add()->flight();

        // General
        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking Code:'))}]/ancestor::td[1]",
                null, true, "/^\s*{$this->opt($this->t('Booking Code:'))}\s*([A-Z\d]{5,7})\s*$/"))
        ;

        // Travellers, Issued, seats
        $row = $this->http->FindNodes("//tr[*[1][{$this->eq($this->t('Passenger'))}]]/*");
        $ticketKeys = array_keys(array_intersect($row, (array) $this->t('E-ticket Numbers')));
        $ticketKeys = array_map(function ($v) {
            return is_integer($v) ? ($v + 1) : null;
        }, $ticketKeys);

        $seatsKeys = array_keys(array_intersect($row, (array) $this->t('Seat No.')));
        $seatsKeys = array_map(function ($v) {
            return is_integer($v) ? ($v + 1) : null;
        }, $seatsKeys);

        $seats = [];
        $tickets = [];

        $trNodes = $this->http->XPath->query("//tr[*[1][{$this->eq($this->t('Passenger'))}]]/following-sibling::*[normalize-space()]");

        foreach ($trNodes as $trRoot) {
            $traveller = $this->http->FindSingleNode("*[1]", $trRoot);
            $traveller = preg_replace(['/\s*(MR|MS|MRS|MISS|MSTR)$/', '/^\s*(\S.+?)\s*\\/\s*(\S.+?)\s*$/'], ['', '$2 $1'], $traveller);

            $f->general()
                ->traveller($traveller, true);

            foreach ($seatsKeys as $i => $key) {
                $seat = $this->http->FindSingleNode("*[position() = {$key}]", $trRoot, true, "/^\s*(\d{1,3}[A-Z])\s*$/");

                if (!empty($seat)) {
                    $seats[$i][] = ['seat' => $seat, 'travellers' => $traveller];
                } else {
                    $seats[$i][] = [];
                }
            }

            foreach ($ticketKeys as $key) {
                $ticket = $this->http->FindSingleNode("*[position() = {$key}]", $trRoot, true, "/^\s*(.+?)\/\d+\s*$/");

                if (!empty($ticket)) {
                    $tickets[$ticket][] = $traveller;
                }
            }
        }

        foreach ($tickets as $ticket => $travellers) {
            foreach (array_unique($travellers) as $tr) {
                $f->issued()
                    ->ticket($ticket, false, $tr);
            }
        }

        // Segments
        $xpath = "//tr[*[1][{$this->eq($this->t('From'))}] and *[3][{$this->eq($this->t('Flight'))}]]/following-sibling::*[normalize-space()][count(*) > 1]";
        $nodes = $this->http->XPath->query($xpath);

        if (count($seats) !== $nodes->length) {
            $seats = [];
        }

        foreach ($nodes as $si => $root) {
            $s = $f->addSegment();

            // Airline
            $airline = $this->http->FindSingleNode("*[3]", $root);

            if (preg_match("/^\s*(?<al>[A-Z][A-Z\d]|[A-Z\d][A-Z]) ?(?<fn>\d+)\s*$/", $airline, $m)) {
                $s->airline()
                    ->name($m['al'])
                    ->number($m['fn']);
            }

            $date = $this->http->FindSingleNode("*[4]", $root);

            if (!empty($airline) && !empty($date)) {
                $text = $this->http->FindSingleNode("//text()[contains(., '{$airline}') and contains(., '{$date}')]",
                    null, true, "/^\s*([A-Z]{3}\s+[A-Z]{3})\s+{$airline}\s*\/\s*{$date}\s*$/");

                if (!empty($text)) {
                    $s->departure()
                        ->code($this->re("/^\s*([A-Z]{3})\s+[A-Z]{3}\s*$/", $text));
                    $s->arrival()
                        ->code($this->re("/^\s*[A-Z]{3}\s+([A-Z]{3})\s*$/", $text));
                }
            }

            // Departure
            $addDep = $this->http->FindSingleNode("following-sibling::tr[normalize-space()][1][{$this->starts($this->t('Departure:'))}]/descendant::text()[normalize-space()][{$this->starts($this->t('Departure:'))}]", $root, true, "/^{$this->opt($this->t('Departure:'))}\s*(\S.+)\s*/");

            if (preg_match("/^\s*(\S.+?)\s*Terminal (.+?)\s*$/i", $addDep, $m)) {
                $s->departure()
                    ->terminal($m[2]);
                $addDep = $m[1];
            }

            $time = $this->http->FindSingleNode("*[5]", $root);
            $s->departure()
                ->name($this->http->FindSingleNode("*[1]", $root) . ((!empty($addDep)) ? ', ' . $addDep : ''))
                ->date((!empty($date) ?? !empty($date)) ? $this->normalizeDate($date . ', ' . $time) : null)
            ;

            if (!empty($s->getDepName()) && empty($s->getDepCode())) {
                $s->departure()
                    ->noCode();
            }
            // Arrival
            $addArr = $this->http->FindSingleNode("following-sibling::tr[normalize-space()][1][{$this->starts($this->t('Departure:'))}]/descendant::text()[normalize-space()][{$this->starts($this->t('Arrival:'))}]",
                $root, true, "/^{$this->opt($this->t('Arrival:'))}\s*(\S.+)\s*/");

            if (preg_match("/^\s*(\S.+?)\s*Terminal (.+?)\s*$/i", $addArr, $m)) {
                $s->arrival()
                    ->terminal($m[2]);
                $addArr = $m[1];
            }

            $time = $this->http->FindSingleNode("*[6]", $root);
            $s->arrival()
                ->name($this->http->FindSingleNode("*[2]", $root) . ((!empty($addArr)) ? ', ' . $addArr : ''))
                ->date((!empty($date) ?? !empty($date)) ? $this->normalizeDate($date . ', ' . $time) : null)
            ;

            if (!empty($s->getArrName()) && empty($s->getArrCode())) {
                $s->arrival()
                    ->noCode();
            }

            // Extra
            if (isset($seats[$si])) {
                $seats[$si] = array_filter($seats[$si]);

                foreach ($seats[$si] as $st) {
                    $s->extra()
                        ->seat($st['seat'], true, true, $st['travellers']);
                }
            }
        }

        // Price
        if ($this->http->XPath->query("//node()[{$this->eq($this->t('Additional Items'))} or {$this->eq($this->t('Fare'))} or {$this->eq($this->t('Total'))}]")->length > 0
            && !empty($this->http->FindSingleNode("//node()[{$this->eq($this->t('Fare'))}]/ancestor::table[.//*[{$this->eq($this->t('Total'))}]][1]", null, true, "/(\d+.*\d+)/"))
        ) {
            $currency = $this->http->FindSingleNode("//tr[count(*) = 3 and *[1][{$this->eq($this->t('Total'))}]]/*[2]");
            $f->price()
                ->cost(PriceHelper::parse($this->http->FindSingleNode("//tr[count(*) = 3 and *[1][{$this->eq($this->t('Fare'))}]]/*[3]"),
                    $currency))
                ->total(PriceHelper::parse($this->http->FindSingleNode("//tr[count(*) = 3 and *[1][{$this->eq($this->t('Total'))}]]/*[3]"),
                    $currency))
                ->currency($currency);

            $fxpath = "//tr[count(*) = 3 and *[1][{$this->eq($this->t('Fare'))}]]/following-sibling::tr[normalize-space()]";
            $fnodes = $this->http->XPath->query($fxpath);

            foreach ($fnodes as $froot) {
                $name = $this->http->FindSingleNode("*[1]", $froot);
                $value = PriceHelper::parse($this->http->FindSingleNode("*[3]", $froot), $currency);

                if (preg_match("/^\s*{$this->opt($this->t('Total'))}\s*$/", $name)) {
                    break;
                }
                $f->price()->fee($name, PriceHelper::parse($value, $currency));
            }
        }

        return true;
    }

    private function t($s)
    {
        if (!isset($this->lang, self::$dictionary[$this->lang], self::$dictionary[$this->lang][$s])) {
            return $s;
        }

        return self::$dictionary[$this->lang][$s];
    }

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
        // $this->logger->debug('date begin = ' . print_r( $date, true));

        $in = [
            // 30 Dec 23, 17:00
            '/^\s*(\d+)\s+[[:alpha:]]\s+(\d{2})\s*,\s*\s+(\d{1,2}:\d{2}(?:\s*[ap]m)?)\s*$/ui',
        ];
        $out = [
            '$1 $2 20$3, $4',
        ];

        $date = preg_replace($in, $out, $date);

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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }
}
