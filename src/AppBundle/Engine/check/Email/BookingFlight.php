<?php

namespace AwardWallet\Engine\check\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
// use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Common\Flight;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BookingFlight extends \TAccountChecker
{
    public $mailFiles = "check/it-29743916-de.eml, check/it-30573592-de.eml, check/it-709444937-de.eml, check/it-707045350-de.eml";
    public static $dictionary = [
        "de" => [
            // 'Gesamtbetrag:' => '',
            'Ihr Buchungsauftrag' => ['Ihre E-Ticketnummern zur Buchung', 'Ihre E-Ticketnummer zur Buchung', 'Bestätigung Ihrer Flugbuchung', 'Ihr Buchungsauftrag'],
            'Passagiere'          => ['Passagiere', 'Passagiere und Gepäck'],
            'ticketsHeader'       => ['Tickets und Passagiere', 'E-Ticketnummern', 'E-Ticketnummer'],
            // 'Ticketnummer:' => '',
            // 'Buchungsnummer' => '',
            // 'bei' => '',
            // 'Klasse:' => '',
            'operatedBy' => ['durchgeführt von', 'Durchgeführt von'],
            // 'Buchungscode:' => '',
            'direction' => ['Hinflug ', 'Rückflug'],
            // '. Flug ' => '',
            // 'Flughafen' => '',
            'H|M' => ['Std', 'Min'],
            // 'Flug nach' => '',
            // 'nach' => '',
        ],
    ];

    private $detectFrom = "check24.de";

    private $detectSubject = [
        "de"  => "Ihr Buchungsauftrag",
        "de2" => "E-Ticketnummer zu Ihrer Flugbuchung ",
        "de3" => "Bestätigung Ihrer Flugbuchung ",
    ];
    private $detectCompany = "CHECK24";
    private $detectBody = [
        "de" => "Ihre Flugdaten",
    ];

    private $date;
    private $lang = "de";

    private $xpath = [
        'time' => 'starts-with(translate(.,"0123456789： ","∆∆∆∆∆∆∆∆∆∆:"),"∆∆:∆∆")',
    ];

    private $patterns = [
        'date'          => '\b(?:[-[:alpha:]]+\s*\.\s*)?\d{1,2}\.\d{1,2}\.\d{4}\b', // 29.07.2024    |    Mo. 29.07.2024
        'time'          => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.
        'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        'eTicket'       => '\d{3}(?: | ?- ?)?\d{5,}(?: | ?- ?)?\d{1,3}', // 075-2345005149-02    |    0167544038003-004
    ];

    public function parseEmail(Email $email): void
    {
        // Price
        $totalPrice = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Gesamtbetrag:'))}]", null, true, "/^{$this->preg_implode($this->t('Gesamtbetrag:'))}[:\s]*(.*\d.*)$/")
            ?? $this->http->FindSingleNode("//text()[{$this->eq($this->t('Gesamtbetrag:'))}]/following::text()[normalize-space()][1]", null, true, "/^[:\s]*(.*\d.*)$/");

        if (preg_match('/^(?<amount>\d[,.‘\'\d ]*?)[ ]*(?<currency>[^\-\d)(]+)$/u', $totalPrice, $matches)) {
            // 9350,30 €
            $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
            $email->price()->total(PriceHelper::parse($matches['amount'], $currencyCode))->currency($matches['currency']);
        }

        $email->ota()
            ->confirmation($this->http->FindSingleNode("descendant::text()[{$this->starts($this->t('Ihr Buchungsauftrag'))}][1]", null, true, "/{$this->preg_implode($this->t('Ihr Buchungsauftrag'))}[:\s]*([A-Z\d]{5,25})\b/"));

        $f = $email->add()->flight();

        // General
        $namePrefixes = "(?:Frau|Herr|Kind|Baby)";

        $travellers = array_values(array_filter($this->http->FindNodes("//text()[{$this->eq($this->t('Passagiere'))}]/ancestor::table[1]//text()[normalize-space()]", null, "/^\s*{$namePrefixes}?\s*({$this->patterns['travellerName']})\s+\d{1,2}\.\d{1,2}/u")));

        if (count($travellers) === 0) {
            $travellers = array_values(array_filter($this->http->FindNodes("//*[{$this->eq($this->t('Passagiere'))}]/following-sibling::*[normalize-space()][1]/descendant::tr[normalize-space() and not(.//tr[normalize-space()])]", null, "/^\s*{$namePrefixes}?\s*({$this->patterns['travellerName']})\s*,\s*\d{1,2}\.\d/u")));
        }

        if (count($travellers) === 0) {
            $travellers = array_values(array_filter($this->http->FindNodes("//text()[{$this->eq($this->t('Ticketnummer:'))}]/preceding::text()[normalize-space()][1]", null, "/^\s*{$namePrefixes}\s*({$this->patterns['travellerName']})\s*$/u")));
        }

        $f->general()->travellers($travellers, true);

        $ticketRows = $this->http->XPath->query("//*[ *[normalize-space()][2] and *[{$this->eq($this->t('Ticketnummer:'))}] ]");

        foreach ($ticketRows as $tktRow) {
            // it-30573592-de.eml
            $passengerName = $this->http->FindSingleNode("*[{$this->eq($this->t('Ticketnummer:'))}]/preceding-sibling::*[normalize-space()]", $tktRow, true, "/^\s*{$namePrefixes}?\s*({$this->patterns['travellerName']})$/u");
            $ticket = $this->http->FindSingleNode("*[{$this->eq($this->t('Ticketnummer:'))}]/following-sibling::*[normalize-space()]", $tktRow, true, "/^{$this->patterns['eTicket']}$/");

            if ($ticket) {
                $f->issued()->ticket($ticket, false, $passengerName);
            }
        }

        if ($ticketRows->length === 0) {
            $ticketRows = $this->http->XPath->query("//*/*[normalize-space()][1][{$this->eq($this->t('ticketsHeader'))}]/following-sibling::*[normalize-space()]");

            foreach ($ticketRows as $tktRow) {
                // it-707045350-de.eml
                $passengerName = $this->http->FindSingleNode("*[normalize-space()][2]", $tktRow, true, "/^[\s(]*{$namePrefixes}?\s*({$this->patterns['travellerName']})[)\s]*$/u");
                $ticket = $this->http->FindSingleNode("*[normalize-space()][1]", $tktRow, true, "/^{$this->patterns['eTicket']}$/");

                if ($ticket) {
                    $f->issued()->ticket($ticket, false, $passengerName);
                }
            }
        }

        // it-30573592-de.eml
        $confs = array_unique(array_filter($this->http->FindNodes("//text()[{$this->starts($this->t('Buchungscode:'))}]", null, "/:\s*(?:[A-Z\d]+_)?([A-Z\d]{5,7})\b/")));

        foreach ($confs as $conf) {
            $f->general()->confirmation($conf);
        }

        if (count($confs) === 0) {
            if (preg_match("/\(\s*{$this->preg_implode($this->t('bei'))}\s+(.{2,}?)\s+([A-Z\d]{5,7})\s*\)$/", $this->http->FindSingleNode("//text()[{$this->eq($this->t('Buchungsnummer'))}]/ancestor::*[ descendant::text()[normalize-space()][3] ][1]"), $m)) {
                // it-707045350-de.eml
                $f->general()->confirmation($m[2], $m[1]);
            } elseif ($this->http->XPath->query("//text()[{$this->eq($this->t('Buchungsnummer'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->eq($this->t('Buchungsnummer'))}]/following::text()[normalize-space()][position()<3][{$this->contains($this->t('bei'))} or contains(.,'(')]")->length === 0
            ) {
                // it-709444937-de.eml
                $f->general()->noConfirmation();
            }
        }

        // Segments
        $xpath = "//tr[ count(*)=3 and *[1][normalize-space()=''] and *[2]/descendant::text()[normalize-space()][1][{$this->xpath['time']}] and *[3][normalize-space()=''] ]";
        $segments = $this->http->XPath->query($xpath);

        if ($segments->length > 0) {
            $this->parseSegments1($f, $segments);

            return;
        }

        $xpath = "//text()[{$this->starts($this->t('Klasse:'))} or {$this->starts($this->t('operatedBy'))} or {$this->starts($this->t('Buchungscode:'))}]/ancestor::tr/preceding-sibling::tr[{$this->xpath['time']}][1]";
        $segments = $this->http->XPath->query($xpath);

        if ($segments->length > 0) {
            $this->parseSegments2($f, $segments);

            return;
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        //		$body = html_entity_decode($this->http->Response["body"]);
        //		foreach($this->detectBody as $lang => $dBody){
        //			if (stripos($body, $dBody) !== false) {
        //				$this->lang = $lang;
        //				break;
        //			}
        //		}

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $this->date = strtotime($parser->getHeader('date'));

        $this->parseEmail($email);

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers["from"]) || empty($headers["subject"])) {
            return false;
        }

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

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $this->http->Response['body'];

        if (strpos($body, $this->detectCompany) === false) {
            return false;
        }

        foreach ($this->detectBody as $detectBody) {
            if (strpos($body, $detectBody) !== false) {
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
        return count(self::$dictionary);
    }

    private function parseSegments1(Flight $f, \DOMNodeList $segments): void
    {
        // examples: it-709444937-de.eml, it-707045350-de.eml

        foreach ($segments as $root) {
            $s = $f->addSegment();

            $departure = implode(' ', $this->http->FindNodes("descendant::text()[normalize-space()]", $root));

            if (preg_match($pattern = "/^(?<time>{$this->patterns['time']}).*?\s*\(\s*(?<date>{$this->patterns['date']})\s*\)\s*(?<airport>.{2,})$/", $departure, $m)) {
                // 05:25 Uhr (Mo. 29.07.2024) São Paulo Guarulhos Airport (GRU)
                $dateDep = $this->normalizeDate($m['date']);
                $s->departure()->date(strtotime($m['time'], $dateDep));

                if (preg_match("/^(?<name>.{2,}?)\s*\(\s*(?<code>[A-Z]{3})\s*\)$/", $m['airport'], $m2)) {
                    $s->departure()->name($m2['name'])->code($m2['code']);
                } else {
                    $s->departure()->name($m['airport']);
                }
            }

            $flight = implode(' ', $this->http->FindNodes("following-sibling::tr[normalize-space()][1]/descendant::text()[normalize-space()]", $root));

            if (preg_match("/^(?<duration>(?:\s*\d{1,3}\s*{$this->preg_implode($this->t('H|M'))}[.\s]*?)+)\s+(?<airlineFull>.{2,}?)\s*\(\s*(?-i)(?<airline>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<flightNumber>\d+)\s*\)\s*(?<cabin>.*)$/i", $flight, $m)) {
                // 0 Std. 55 Min. Swiss International Air Lines (LX 1111) Economy
                $s->extra()->duration($m['duration']);
                $s->airline()->name($m['airline'])->number($m['flightNumber']);

                if (!empty($m['cabin'])) {
                    if (preg_match("/^{$this->preg_implode($this->t('operatedBy'))}\s+(?<operator>.+?)\s+(?<cabin>{$this->preg_implode(['Economy', 'Business'])})$/i", $m['cabin'], $m2)) {
                        $s->airline()->operator($m2['operator']);
                        $s->extra()->cabin($m2['cabin']);
                    } else {
                        $s->extra()->cabin($m['cabin']);
                    }
                }
            }

            $arrival = implode(' ', $this->http->FindNodes("following-sibling::tr[normalize-space()][2]/descendant::text()[normalize-space()]", $root));

            if (preg_match($pattern, $arrival, $m)) {
                $dateArr = $this->normalizeDate($m['date']);
                $s->arrival()->date(strtotime($m['time'], $dateArr));

                if (preg_match("/^(?<name>.{2,}?)\s*\(\s*(?<code>[A-Z]{3})\s*\)$/", $m['airport'], $m2)) {
                    $s->arrival()->name($m2['name'])->code($m2['code']);
                } else {
                    $s->arrival()->name($m['airport']);
                }
            }

            $seatsText = '';

            if (!empty($s->getDepName()) && !empty($s->getArrName())) {
                $routeVariants = [];

                foreach ((array) $this->t('nach') as $item) {
                    $routeVariants[] = $s->getDepName() . ' ' . $item . ' ' . $s->getArrName();
                }

                $seatsText = implode("\n", $this->http->FindNodes("//table[ preceding::text()[normalize-space()][1][{$this->eq($routeVariants)}] ]/descendant::text()[normalize-space()]"));
            }

            $directionHeader = $this->http->FindSingleNode("preceding::tr[not(.//tr[normalize-space()]) and {$this->starts($this->t('Flug nach'))}][1]", $root);

            if (empty($seatsText) && $directionHeader) {
                $seatsText = implode("\n", $this->http->FindNodes("//table[ preceding::text()[normalize-space()][1][{$this->eq($directionHeader)}] ]/descendant::text()[normalize-space()]"));
            }

            if (preg_match("/((?:(?:\s*[,\n]\s*)+\d+[A-Z])+)$/", $seatsText, $m)) {
                $s->extra()->seats(preg_split('/(?:\s*,\s*)+/', trim($m[1])));
            }
        }
    }

    private function parseSegments2(Flight $f, \DOMNodeList $segments): void
    {
        // examples: it-29743916-de.eml, it-30573592-de.eml

        foreach ($segments as $root) {
            $s = $f->addSegment();

            $date = $this->normalizeDate($this->http->FindSingleNode("preceding-sibling::tr[{$this->starts($this->t('direction'))}][1]/descendant::td[not(.//tr) and normalize-space()][1]", $root, true, "/{$this->preg_implode($this->t('direction'))}\s*(.+)/"));

            if (empty($date)) {
                $date = $this->normalizeDate($this->http->FindSingleNode("preceding-sibling::tr[{$this->contains($this->t('. Flug '))}][1]/descendant::td[not(.//tr) and normalize-space()][1]", $root, true, "/{$this->preg_implode($this->t('. Flug '))}\s*(.+)/"));
            }

            if (empty($date)) {
                $this->logger->debug("not detect date");

                return;
            }

            // Airline
            $flight = $this->http->FindSingleNode("following-sibling::tr[normalize-space()][1]", $root);

            if (preg_match("/\((?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<number>\d+)\)/", $flight, $m)) {
                $s->airline()->name($m['name'])->number($m['number']);
            }

            $operator = $this->http->FindSingleNode("following-sibling::tr[normalize-space()][1]//td[not(.//tr) and {$this->contains($this->t('operatedBy'))}]", $root, true, "/{$this->preg_implode($this->t('operatedBy'))}\s*(.+)/");
            $s->airline()->operator($operator, false, true);

            // Departure
            $airport = implode("\n", $this->http->FindNodes("./descendant::td[not(.//td) and normalize-space(.)][2]//text()[normalize-space()]", $root));

            if (preg_match("#\s*.* ([A-Z]{3})(\s|$)#", $airport, $m)) {
                $s->departure()->code($m[1]);
            }

            if (preg_match("/\n\s*{$this->preg_implode($this->t('Flughafen'))}\s+(.+)/", $airport, $m)) {
                $s->departure()->name($m[1]);
            }
            $time = $this->http->FindSingleNode("./descendant::td[not(.//td) and normalize-space(.)][1]", $root);

            if (!empty($time)) {
                if (preg_match("#(.+?)\s*([\+\-]\d+)\s*$#", $time, $m)) {
                    $s->departure()->date(strtotime($time, $date));
                    $s->departure()->date(strtotime($m[2] . " days", $s->getDepDate()));
                } else {
                    $s->departure()->date(strtotime($time, $date));
                }
            }

            // Arrival
            $airport = implode("\n", $this->http->FindNodes("following-sibling::tr[{$this->xpath['time']}][1]/descendant::td[not(.//tr) and normalize-space()][2]//text()[normalize-space()]", $root));

            if (preg_match("#\s*.* ([A-Z]{3})(\s|$)#", $airport, $m)) {
                $s->arrival()->code($m[1]);
            }

            if (preg_match("/\n\s*{$this->preg_implode($this->t('Flughafen'))}\s+(.+)/", $airport, $m)) {
                $s->arrival()->name($m[1]);
            }

            $time = $this->http->FindSingleNode("following-sibling::tr[{$this->xpath['time']}][1]/descendant::td[not(.//tr) and normalize-space()][1]", $root);

            if (!empty($time)) {
                if (preg_match("#(.+?)\s*([\+\-]\d+)\s*$#", $time, $m)) {
                    $s->arrival()->date(strtotime($time, $date));
                    $s->arrival()->date(strtotime($m[2] . " days", $s->getArrDate()));
                } else {
                    $s->arrival()->date(strtotime($time, $date));
                }
            }

            // Extras
            $s->extra()
                ->cabin($this->http->FindSingleNode("./following-sibling::tr[position()<4][{$this->contains($this->t('Klasse:'))}][1]//td[not(.//td) and {$this->contains($this->t('Klasse:'))}]", $root, true, "/{$this->preg_implode($this->t('Klasse:'))}\s*(\D+)$/"))
                ->duration($this->http->FindSingleNode("(./following-sibling::tr[normalize-space(.)][1]//td[not(.//td) and normalize-space()])[last()]", $root, true, "#^\s*\d+.*#"));
        }
    }

    private function t(string $phrase, string $lang = '')
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return $phrase;
        }

        if ($lang === '') {
            $lang = $this->lang;
        }

        if (empty(self::$dictionary[$lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$lang][$phrase];
    }

    private function normalizeDate($str)
    {
        $year = date("Y", $this->date);
        $in = [
            '/^(?:[-[:alpha:]]+\s*\.\s*)?(\d{1,2})\.(\d{1,2})\.(\d{4})$/', // 29.07.2024    |    Mo. 29.07.2024
            "#^\s*([^\s\d\.\,]+)[,.\s]+(\d{1,2}\.\d{1,2}\.)\s*$#", //Fr., 08.03.
        ];
        $out = [
            '$1.$2.$3',
            '$1, ${2}' . $year,
        ];
        $str = preg_replace($in, $out, $str);
        //		if(preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)){
        //			if($en = MonthTranslate::translate($m[1], $this->lang))
        //				$str = str_replace($m[1], $en, $str);
        //		}

        if (preg_match("#^\s*(?<week>[^\s\d\.\,]+), (?<date>.*\d{4}.*)\s*$#", $str, $m)) {
            $weekDayNumber = WeekTranslate::number1($m['week'], $this->lang);

            return EmailDateHelper::parseDateUsingWeekDay($m['date'], $weekDayNumber);
        }

        return strtotime($str);
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

    private function preg_implode($field): string
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '/'); }, $field)) . ')';
    }
}
