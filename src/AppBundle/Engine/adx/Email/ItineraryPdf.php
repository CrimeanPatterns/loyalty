<?php

namespace AwardWallet\Engine\adx\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Common\Hotel;
use AwardWallet\Schema\Parser\Email\Email;

class ItineraryPdf extends \TAccountChecker
{
    public $mailFiles = "adx/it-147158875.eml, adx/it-148475651.eml, adx/it-171250587.eml, adx/it-171287745.eml, adx/it-187908243.eml, adx/it-192212071.eml, adx/it-193217159.eml, adx/it-195928410.eml, adx/it-200339880.eml, adx/it-200353438.eml, adx/it-204750981.eml, adx/it-205083298.eml, adx/it-206698739.eml, adx/it-237727137.eml, adx/it-265290709.eml, adx/it-375015789.eml, adx/it-686278729.eml";

    public $lang = '';
    public $date;
    public $year;
    public $flightsArray = [];
    public $travellers;
    public $status;

    public $lastHotelName;
    public $lastCheckIn;

    public static $dictionary = [
        'en' => [
            'Duration'          => 'Duration',
            'DATE'              => ['DATE', 'Date'],
            'TIME'              => ['TIME', 'Time'],
            'Check-in:'         => ['Check-in:', 'Check-in'],
            'Embark'            => ['Embark'],
            'Departure:'        => ['Departure:'],
        ],
    ];

    private $subjects = [
        'en' => ['Round Trip Flights:', 'One Way Flight:'],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@adxtravel.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (is_string($phrase) && stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('.*\.pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (!$textPdf) {
                continue;
            }

            if (stripos($textPdf, 'clients.adxtravel.com') === false
                && stripos($textPdf, 'www.adxtravel.com') === false
            ) {
                continue;
            }

            if ($this->assignLang($textPdf)) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName('.*\.pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (!$textPdf) {
                continue;
            }

            if ($this->assignLang($textPdf)) {
                $typePDF = 'full';
                $mainTable = '';

                if (preg_match("/\n {0,10}DATE {3,}TIME .*([\s\S]+?)\n {0,10}(?:Pricing|Terms *& *Conditions)\n/", $textPdf, $m)
                    && preg_match_all("/ (Page *\d+ *of *\d+)\n/", $m[1], $pageMatches)
                    && count($pageMatches[1]) === 1
                ) {
                    // данные по резервациям есть только в таблице
                    // DATE     TIME    EVENT   DESCRIPTION
                    $typePDF = 'mainTable';
                    $mainTable = $m[1];
                    $mainTable = preg_replace("/\n(\n*\s*Page.++\nPrepared\sby:.+\nMulti\-City\s*Flight\:.+\n+)/", "\n", $mainTable);
                    $mainTable = preg_replace("/\n *\S.+ {3,}Page *\d+ *of *\d+\n *\S.+\n/", "\n", $mainTable);
                    $mainTable = preg_replace("/\n {20,}Page *\d+ *of *\d+\n *Prepared by: .*\n *\S.+\n/", "\n", $mainTable);
                    $mainTable = preg_replace("/(.+)\n+ {0,10}DATE {3,}TIME .*\n+/", "$1\n\n", $mainTable);
                }

                $re = $this->re("/\n {20,}Page *\d+ *of *\d+\n *Prepared by: .*\n( *\S.+\n *\S.* {70}.*\S\n)/", $textPdf);
                $re2 = $this->re("/\n *Prepared by: .* {5,}Page *\d+ *of *\d+ *\n( *\S.+\n *\S.* {70}.*\S\n)/", $textPdf);

                if ($re && preg_match_all("/" . preg_replace("/ {2,}/", ' {2,}', preg_quote($re, '/')) . "/", $textPdf, $matches) && count($matches[0]) > 1) {
//                                                                                                                                                            Page 2 of 5
                    // Prepared by: Essentialist Operations, Ref. 4ZS1BU
                    // Round Trip Flights: Fort Lauderdale - Salt Lake                   for Mr. Robert Cleveland, Mrs. Jillian Cleveland, Mstr Charles Cleveland, Mstr Benjamin Cleveland, et
                    // City                                                                                                                                                                al.
//
                    $textPdf = preg_replace("/\n {20,}Page *\d+ *of *\d+\n *Prepared by: .*\n *\S.+\n.+\n/", "\n\n", $textPdf);
                } elseif ($re2 && preg_match_all("/" . preg_replace("/ {2,}/", ' {2,}', preg_quote($re2, '/')) . "/", $textPdf, $matches) && count($matches[0]) > 1) {
                    // Prepared by: Essentialist Operations, Ref. 4ZS1BU                                                                                                          Page 2 of 5
                    // Round Trip Flights: Fort Lauderdale - Salt Lake                   for Mr. Robert Cleveland, Mrs. Jillian Cleveland, Mstr Charles Cleveland, Mstr Benjamin Cleveland, et
                    // City                                                                                                                                                                al.
//
                    $textPdf = preg_replace("/\n *Prepared by: .* {5,}Page *\d+ *of *\d+ *\n *\S.+\n.+\n/", "\n\n", $textPdf);
                } else {
                    $textPdf = preg_replace("/\n {20,}Page *\d+ *of *\d+\n *Prepared by: .*\n *\S.+\n/", "\n\n", $textPdf);
                    $textPdf = preg_replace("/\n *Prepared by: .* {5,}Page *\d+ *of *\d+ *\n *\S.+\n/", "\n\n", $textPdf);
                }

                // Travel Agency
                $email->obtainTravelAgency();

                if (preg_match("/\n *Prepared by: .*, (Ref\.) +([A-z\d]{5,7})(\s{3,}|\n|$)/m", $textPdf, $m)) {
                    $email->ota()->confirmation($m[2], $m[1]);
                }

                if (preg_match("/\n.*\b\d{4}\b.*\n\s*For (.+(?:\n+.+)?)\n+\s*DATE +TIME +EVENT +/", $textPdf, $m)) {
                    $this->travellers = array_filter(explode(",", $m[1]));
                }

                if (empty($this->travellers) && preg_match("/\n[ ]*{$this->opt($this->t('Travelers:'))}(?: {5,}Prepared by: *)?\n+\s*(.+?)\n\n\n\n/s", $textPdf, $m)) {
                    $this->travellers = preg_replace('/^ {0,30}(\S.+?) {2,}\S.*$/',"$1",
                        array_filter(explode("\n", $m[1])));
                }

                if (!empty($this->travellers)) {
                    $this->travellers = preg_replace(["/^\s*(Mr\.|Mrs\.|Dr\.|Miss|Ms\.|Mstr) +/", '/\s*,$/'], "", $this->travellers);
                    $this->travellers = preg_replace("/\s+/", " ", $this->travellers);
                    $this->travellers = array_map('trim', $this->travellers);
                }

                if (preg_match("/Itinerary Status\s+(.+)\n/i", $textPdf, $m)) {
                    $m[1] = strtolower(str_replace(' ', '', $m[1]));

                    switch ($m[1]) {
                        case 'quote': $this->status = 'Quote';

break;

                        case 'partiallybooked': $this->status = 'Partially Booked';

break;

                        case 'travelready': $this->status = 'Travel Ready';

break;
                    }
                }

                if (preg_match("/\n[ ]*Total[ ]{3,}(?<cost>(?:\S ?)+)[ ]{3,}(?<tax>(?:\S ?)+)[ ]{3,}(?<total>(?:\S ?)+)/u", $textPdf, $m)) {
                    if (preg_match("/^\s*\\$?(?<amount>\d[\d., ]*)(?<currency>[A-Z]{3})\s*$/u", $m['total'], $mat)
                        || preg_match("/(?<amount>\d[\d., ]*)\s*(?<currency>[A-Z]{3})\s*$/u", $m['total'], $mat)) {
                        $email->price()
                            ->currency($mat['currency'])
                            ->total(PriceHelper::parse(trim($mat['amount']), $mat['currency']));

                        if (preg_match("/^\s*\\$?(?<amount>\d[\d., ]*)" . $mat['currency'] . "\s*$/", $m['cost'], $m2)
                            || preg_match("/(?<amount>\d[\d., ]*)\s*(?<currency>[A-Z]{3})\s*$/u", $m['cost'], $m2)) {
                            $email->price()
                                ->cost(PriceHelper::parse($m2['amount'], $mat['currency']));
                        }

                        if (preg_match("/^\s*\\$?(?<amount>\d[\d., ]*)" . $mat['currency'] . "\s*$/", $m['tax'], $m2)
                            || preg_match("/(?<amount>\d[\d., ]*)\s*(?<currency>[A-Z]{3})\s*$/u", $m['tax'], $m2)) {
                            $email->price()
                                ->tax(PriceHelper::parse($m2['amount'], $mat['currency']));
                        }
                    }
                }

                if ($typePDF === 'mainTable') {
                    $startDate = null;

                    if (preg_match("/ +Itinerary Status\s*.+\s*\n\s*(.+?)(?: [^\w\s,.] |\n)/u", $textPdf, $m)) {
                        $this->year = $this->re("/(\d{4})$/", $m[1]);
                        $startDate = strtotime($m[1]);

                        if (!empty($startDate)) {
                            $startDate = strtotime("-1 day", $startDate);
                        }
                    }

                    $segmentsByDate = $this->splitText($mainTable, "/\n( {0,10}[A-Z]{3,4}(?: {2,}.*)?\n+(?: {10,}.*\n)? {0,10}\d{1,2})/", true);

                    if (count($segmentsByDate) > 0) {
                        if (preg_match("/^( {0,10}[A-Z]{3,4}(?: {2,}.*)?\n+(?: {10,}.*\n)? {0,10}\d{1,2}.*\n+)(?: {10,}.+\n+)+/", $segmentsByDate[count($segmentsByDate) - 1] ?? '', $m)) {
                            $segmentsByDate[count($segmentsByDate) - 1] = $m[0];
                        }
                        $segmentsByDate[count($segmentsByDate) - 1] = preg_replace("/\n +(?:VIP SUPPORT|INSURANCE|Essentialist Operations)\n[\s\S]+$/", '', $segmentsByDate[count($segmentsByDate) - 1]);
                        $segmentsByDate[count($segmentsByDate) - 1] = preg_replace("/\n{5,}[\s\S]+$/", '', $segmentsByDate[count($segmentsByDate) - 1]);
                    }

                    $airSegments = [];
                    $hotelsSegments = [];
                    $rentalsSegments = [];

                    foreach ($segmentsByDate as $sdText) {
                        $segDate = null;

                        if (!empty($startDate) && preg_match("/^\n* {0,10}(?<month>[A-Z]{3,4})(?: {2,}.*)?\n+(?: {10,}.*\n)? {0,10}(?<day>\d{1,2})(?:\s+|$)/", $sdText, $m)) {
                            $segDate = EmailDateHelper::parseDateRelative($m['day'] . ' ' . $m['month'], $startDate);
                        }

                        $segments = $this->splitText($sdText, "/(?:^|\n)(.{10,} {2,}(?:Departure:|Arrival:|Check-in:|Check-out:|Stay:|Pick-up:|Drop-off:|Embark|At Sea|In-port))/", true);

                        $sCount = count($segments);

                        for ($i = 0; $i < $sCount; $i++) {
                            if (!isset($segments[$i])) {
                                continue;
                            }

                            if (stripos($segments[$i], 'Departure:') !== false) {
                                if (isset($segments[$i + 1]) && stripos($segments[$i], 'Arrival:') === false && stripos($segments[$i + 1], 'Arrival:') !== false) {
                                    $segments[$i] .= "\n" . $segments[$i + 1];
                                    unset($segments[$i + 1]);
                                }
                                $airSegments[] = ['date' => $segDate, 'segment' => $segments[$i]];
                            } elseif (stripos($segments[$i], 'Arrival:') !== false) {
                                $airSegments[] = ['date' => $segDate, 'segment' => $segments[$i]];
                            }

                            if (stripos($segments[$i], 'Stay:') !== false) {
                                continue;
                            }

                            if (stripos($segments[$i], 'Check-in:') !== false || stripos($segments[$i], 'Check-out:') !== false) {
                                $hotelsSegments[] = ['date' => $segDate, 'segment' => $segments[$i]];
                            }

                            if (stripos($segments[$i], 'Pick-up:') !== false || stripos($segments[$i], 'Drop-off:') !== false) {
                                $rentalsSegments[] = ['date' => $segDate, 'segment' => $segments[$i]];
                            }
                        }
                    }

                    if (!empty($airSegments)) {
                        $this->parseShortFlights($email, $airSegments);
                    }

                    if (!empty($hotelsSegments)) {
                        $this->parseShortHotels($email, $hotelsSegments);
                    }

                    if (!empty($rentalsSegments)) {
                        $this->parseShortRentals($email, $rentalsSegments);
                    }
                } else {
                    if (stripos($textPdf, 'Aircraft:')) {
                        $this->parseFlight($email, $textPdf);
                    }

                    if (stripos($textPdf, 'Check-in:')) {
                        $this->parseHotel($email, $textPdf);
                    }

                    if (stripos($textPdf, 'Pick-up:')) {
                        $this->parseRental($email, $textPdf);
                    }

                    if (stripos($textPdf, 'Embark') && stripos($textPdf, 'In-Port')) {
                        $this->parseCruise($email, $textPdf);
                    }
                }
            }
        }

        $email->setType('ItineraryPdf' . ucfirst($this->lang));

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

    private function parseFlight(Email $email, string $text): void
    {
        $tickets = [];

        $f = $email->add()->flight();

//        if (preg_match("/Item.+\n+\s*(?:Round\-Trip|One\-way)\D+[ ]{10,}\S(?<cost>[\d\.\,]+)\s*(?<currency>[A-Z]{3})\s+\D(?<tax>[\d\.\,]+)\s*[A-Z]{3}\s*\S(?<total>[\d\.\,]+)\s*[A-Z]{3}\n+/", $text, $m)) {
//            $f->price()
//                ->total(PriceHelper::parse($m['total'], $m['currency']))
//                ->currency($m['currency'])
//                ->cost(PriceHelper::parse($m['cost'], $m['currency']))
//                ->tax(PriceHelper::parse($m['tax'], $m['currency']));
//        }

        if (!empty($this->travellers)) {
            $f->general()
                ->travellers(str_replace("\n", " ", $this->travellers));
        }

        if (!empty($this->status)) {
            $f->general()
                ->status($this->status);
        }

        $tickets = [];

        if (preg_match("/\n.*[ ]{2,}Traveler: {2,}Ticket Number:.*\n((.*\n)+?)\n\n/", $text, $m)) {
            if (preg_match_all("/^.* {2,}(?<name>[[:alpha:]]+(?: ?[^\d\s])+) +(?<ticket>\d{13}.*?)(?:\n|$| {2,}.*)$/um", $m[1], $mat)) {
                $mat['name'] = preg_replace("/^\s*(Mr|Miss|Ms|Mrs|Mstr|Dr)\.? /", '', $mat['name']);

                foreach ($mat['ticket'] as $i => $v) {
                    $tickets[$v] = in_array($mat['name'][$i], $this->travellers) ? $mat['name'][$i] : null;
                }
            }
        }

        $airlineConfirmation = [];

        if (preg_match("/\n.*[ ]{2,}Airline Confirmation Number.*\n((.*\n)+?)\n\n/", $text, $m)) {
            if (preg_match_all("/ {2,}(\S(?: ?\S)+) – ([A-Z\d]{5,7})\s*$/um", $m[1], $mat)) {
                foreach ($mat[1] as $i => $v) {
                    $airlineConfirmation[$v] = $mat[2][$i];
                }
            }
        }

        $date = null;

        if (preg_match("/ +Itinerary Status\s*.+\s*\n\s*(.+?)(?: [^\w\s,.] |\n)/u", $text, $m)) {
            $this->year = $this->re("/(\d{4})$/", $m[1]);
            $date = strtotime($m[1]);

            if (!empty($date)) {
                $date = strtotime("-1 day", $date);
            }
        }

        $regexp = "/\n(?: *\S.{1,10} {3,})? +([\\/]{2} .+? Flight \d{1,5}(?: {3,}|[ ]*(?i)Reference:|\n))/";

        $segments = $this->splitText($text, $regexp, true);
        $segmentType = '';

        if (empty($segments)) {
            $regexp = "/\n(?: {0,10}[[:alpha:]]+,\s*[[:alpha:]]+\s+\d{1,2},\s+\d{4}\s+(?:.*\n){1,4})?([\\/]{2}\s+.+? Flight \d{1,5}(?: {3,}|[ ]*(?i)Reference:|\n))/";
            $segments = $this->splitText($text, $regexp, true);

            if (!empty($segments)) {
                $segmentType = 'notTable';
            }
        }

        $confs = [];
        $noConf = true;
        $accounts = [];

        foreach ($segments as $key => $sText) {
            //remove junk from segment
            $deteleText = $this->re("/Flight\s*\d{2,4}\n(.*?)\n\s+[A-Z]{3} {2,}[A-Z]{3}\s*/us", $sText);
            $sText = str_replace($deteleText, '', $sText);

            $tableText = $this->re("/^\s*[\\/]{2}\s+.+\s*\n((?:.*\n)+?)\s*(?:Operated by:|Cabin:)/", $sText);

            $tableText = preg_replace("/^(\n*.+\n*Prepared.+\n*Round Trip.+\n+)/", "", $tableText);
            $tableText = preg_replace("/^(Prepared.+\n*.*for.+\n+)/", "", $tableText);

            $accountText = $this->re("/Frequent Flyer Numbers\n(.+(?:\n.*){1,})See Full Fare Rules & Restrictions/u", $sText);

            if (preg_match_all("/\:\s*([A-Z\d]{6,})(?:\s*\,|\n)/", $accountText, $match)) {
                $accounts = array_unique(array_merge($accounts, $match[1]));
            }

            if (preg_match("/\n.*[ ]{2,}Traveler: {2,}Ticket Number:.*\n((.*\n)+?)\n\n/", $sText, $m)) {
                if (preg_match_all("/^.* {2,}(?<name>[[:alpha:]]+(?: ?[^\d\s]+)+) +(?<ticket>\d{13}.*?)(?:\n|$| {2,}.*)$/um", $m[1], $mat)) {
                    $mat['name'] = preg_replace("/^\s*(Mr|Miss|Ms|Mrs|Mstr|Dr)\.? /", '', $mat['name']);

                    foreach ($mat['ticket'] as $i => $v) {
                        $tickets[$v] = in_array($mat['name'][$i], $this->travellers) ? $mat['name'][$i] : null;
                    }
                }
            }

            if ($segmentType === 'notTable'
                && preg_match("/^(?<col1>\s*[A-Z]{3}\n(?:.+\n+){1,3}?\s*\d{1,2}:\d{2}.*\n\s*(.+\n+){1,3})(?<col2>\s*[A-Z]{3}\n\s*(?:.+\n+){1,3}?\s*\d{1,2}:\d{2}.*\n\s*(.+\n+){1,3})\s*(?<col3>Duration\s*[\S\s]+)/", $tableText, $m)
            ) {
                $table = [$m['col1'], $m['col2'], $m['col3']];
            } else {
                $table = $this->splitCols($tableText);
            }

            $s = $f->addSegment();

            if (preg_match_all("/ {2,}(?<name>[[:alpha:]]+(?: ?[^\d\s])+)[ ]{3,}\-?[ ]{3,}(?<seat>\d{1,2}[A-Z]\n)/", $sText, $m)) {
                $travellersText = implode("\n", $this->travellers);

                foreach ($m['seat'] as $i => $v) {
                    if (preg_match_all("/^\s*" . str_replace(' ', '(?: [[:alpha:]]+( ?[^\d\s])* | )', preg_quote($m['name'][$i])) . "\s*$/m", $travellersText, $mat)
                        && count($mat[0]) === 1
                    ) {
                        $s->extra()->seat($v, true, true, $mat[0][0]);
                    } else {
                        $s->extra()->seat($v);
                    }
                }
            }

            // Airline
            if (preg_match("/^\s*\W{2}\s*(?<name>.+) +Flight +(?<number>\d+)(?:\n|(?<ref>[ ]*(?i)Reference:.*| {2,}.+))/", $sText, $m)) {
                $s->airline()
                    ->name($m['name'])
                    ->number($m['number']);

                if (isset($airlineConfirmation[$m['name']])) {
                    $s->airline()
                        ->confirmation($airlineConfirmation[$m['name']]);
                }

                if (!empty($m['ref']) && preg_match("/^\s*[[:alpha:] ]+: *([A-Z\d]{5,7})\s*$/u", $m['ref'], $mat)) {
                    $confs[] = $mat[1];
                    $noConf = false;
                } elseif (empty($m['ref']) && $noConf === true) {
                    $noConf = true;
                } else {
                    $noConf = false;
                }
            } else {
                $noConf = false;
            }

            if (preg_match("/\n\s*Operated by:(.+\n(?: {0,50}\S[^:]+(?: {3,}|\n))?)/", $sText, $m)) {
                $operator = preg_replace(["/ *(\S.+?) {3}.+/m", '/ DBA .*/', '/^\\/\s*/', '/\s+/'], ['$1', '', '', ' '], trim($m[1]));

                if (strlen($operator) > 50) {
                    $operator = preg_replace("/(FOR.+)$/", "", $operator);
                    $operator = preg_replace("/^Eurowings Discover and Airbus Transport International$/", "Eurowings Discover", $operator);
                }
                $s->airline()
                    ->operator($operator);
            }

            $regexp = "/^\s*(?<code>[A-Z]{3})\s+(?<name>\S[\S\s]{2,}?)?\s*\n*\s*(?<time>\d{1,2}:\d{2}[^\n]*)\n+(?<date>.+)(?:\s*\n\s*Terminal\s+(?<terminal>\S+.*))?/ui";
            // Departure
            if (preg_match($regexp, $table[0], $m)) {
                $s->departure()
                    ->code($m['code']);

                if (!empty($m['name'])) {
                    $s->departure()
                        ->name($m['name']);
                }

                if ($date) {
                    $s->departure()->date(strtotime($m['time'], EmailDateHelper::parseDateRelative($m['date'], $date)));
                }

                if (preg_match("/Terminal\s+(.+)\s+Terminal/u", $tableText, $match)) {
                    $s->departure()->terminal($match[1]);
                } elseif (!empty($m['terminal'])) {
                    $s->departure()->terminal($m['terminal']);
                }
            }

            // Arrival
            if (preg_match($regexp, $table[1] ?? '', $m)) {
                if (isset($m['name']) && !empty($m['name'])) {
                    $s->arrival()
                        ->name(preg_replace('/\s+/', ' ', $m['name']));
                }

                $s->arrival()
                    ->code($m['code']);

                if ($date) {
                    $s->arrival()->date(strtotime($m['time'], EmailDateHelper::parseDateRelative($m['date'], $date)));
                }

                if (preg_match("/Terminal\s*.+\s+Terminal\s*(\S+)/i", $tableText, $match)) {
                    $s->arrival()->terminal($match[1]);
                } elseif (!empty($m['terminal'])) {
                    $s->arrival()->terminal($m['terminal']);
                }
            }

            if (preg_match("/^[ ]*{$this->opt($this->t('Duration'))} *\n? *(.+)/", $table[2] ?? '', $m)) {
                $s->extra()->duration($m[1]);
            }

            if (preg_match("/\n\s*Cabin: *(.*?), *Class: ?([A-Z]{1,2})( {2,}|\n)/", $sText, $m)) {
                $s->extra()
                    ->cabin($m[1], true, true)
                    ->bookingCode($m[2]);
            }

            if (preg_match("/\n\s*Aircraft: ?(\S.+?)( {2,}|\n)/", $sText, $m)) {
                $s->extra()->aircraft(preg_replace('/\s+/', ' ', $m[1]));
            }

            $flight = $s->getFlightNumber() . '/' . $s->getDepCode() . '/' . $s->getDepDate();

            if (in_array($flight, $this->flightsArray)) {
                $f->removeSegment($s);
            } else {
                $this->flightsArray[] = $flight;
            }
        }

        if (count($accounts) > 0) {
            $f->setAccountNumbers($accounts, false);
        }

        foreach ($tickets as $number => $name) {
            $f->issued()
                ->ticket($number, false, $name);
        }

        if (!empty($confs)) {
            $confs = array_unique($confs);

            foreach ($confs as $conf) {
                $f->general()
                    ->confirmation($conf);
            }
        } elseif ($noConf === true) {
            $f->general()
                ->noConfirmation();
        }
    }

    private function parseHotel(Email $email, string $text): void
    {
        $text = preg_replace("/\n+ {0,10}DATE {3,}TIME .*\n+/", "\n\n", $text);
        $hotelsTitle = $this->splitText($text, "/(\n.* {3,}Check-in:.*Address:.*\n)/", true);
        $hotels = [];
        $hotelsNamesCount = [];

        foreach ($hotelsTitle as $ht) {
            if (preg_match("/\n(.* {3,}Check-in:.*Address:(?:.*\n+){1,5}?)(?:.+ {3,}(Departure|Arrival|Stay|Check-out|Check-in|Drop-off|Pick-up):|$)/", $ht . "\n", $m)) {
                $table = $this->splitCols($m[1]);

                if (preg_match("/Check-in:(.+)/s", $table[count($table) - 2], $mName)
                    && preg_match("/Address:(.+?)(?:\n[\w ]+:|$)/s", $table[count($table) - 1], $mAddr)
                ) {
                    $name = preg_replace('/\s+/', ' ', trim($mName[1]));
                    $hotelsNames[] = $name;

                    if (isset($hotelsNamesCount[$name])) {
                        $hotelsNamesCount[$name]++;
                    } else {
                        $hotelsNamesCount[$name] = 1;
                    }
                    $hotels[] = [
                        'name'    => $name,
                        'address' => preg_replace('/\s+/', ' ', trim($mAddr[1])),
                    ];
                } else {
                    $hotelsNamesCount[''] = isset($hotelsNamesCount['']) ? $hotelsNamesCount['']++ : 1;
                    $hotels[] = [
                        'name'    => '',
                        'address' => '',
                    ];
                }
            } else {
                $hotelsNamesCount[''] = isset($hotelsNamesCount['']) ? $hotelsNamesCount['']++ : 1;
                $hotels[] = [
                    'name'    => '',
                    'address' => '',
                ];
            }
        }

        $hotelsNames = array_unique($hotelsNames);

        unset($checkInPrecedingTexts, $checkOutPrecedingTexts);
        $hotelName = str_replace(' ', '(?:\s*|\n+ +)', $this->opt($hotelsNames));
        $reHotelname = "/\n(.* {2,}" . $hotelName . "\n+)/";
        $parts = $this->splitText($text, $reHotelname, true, false);
        $ciCurrentKey = 0;
        $coCurrentKey = 0;

        $checkInTexts = [];
        $checkOutTexts = [];
        // $this->logger->debug('$parts = '.print_r( $parts,true));
        foreach ($parts as $i => $part) {
            if ($i == 0) {
                $checkInTexts[$ciCurrentKey] = $part;
                $checkOutTexts[$coCurrentKey] = $part;

                continue;
            }

            if (preg_match("/^.* {2,}" . $hotelName . "\n+(?:.*\n+){1,4}? +Phone:.+\n+(?:.+\n)? *(?:[\\/]{2})?\s+.+\n(?:.*\n+){1,3}? +Check-in +Stay/", $part)
                || preg_match("/^.* {2,}" . $hotelName . "\n+(?:.*\n)?.* {2,}Check-in\n+ +\d{1,2}:\d{2}\D+\n/", $part)) {
                $ciCurrentKey++;
            }

            if (!isset($checkInTexts[$ciCurrentKey])) {
                $checkInTexts[$ciCurrentKey] = '';
            }
            $checkInTexts[$ciCurrentKey] .= "\n" . $part;

            if (preg_match("/^.* {2,}" . $hotelName . "\n+(?:.*\n)?.{10,20} {2,}Check-out\n+.{10,20} {2,}\d{1,2}:\d{2}\D+\n/", $part)
                || preg_match("/^.* {2,}" . $hotelName . "\n+(?:.*\n)?.{10,20} {2,}Check-out\n+.{10,20} {2,}\w+ \d+, \d{4}\n/", $part)
            ) {
                $coCurrentKey++;
            }

            if (!isset($checkOutTexts[$coCurrentKey])) {
                $checkOutTexts[$coCurrentKey] = '';
            }
            $checkOutTexts[$coCurrentKey] .= "\n" . $part;
        }
        $checkInPrecedingTexts = $checkInTexts;
        array_shift($checkInTexts);

        $checkOutPrecedingTexts = $checkOutTexts;
        array_shift($checkOutTexts);

        if (count($hotels) != count($checkInTexts) || count($hotels) != count($checkOutTexts)) {
            $this->logger->debug('something went wrong');
            $email->add()->hotel();
        }

        $countHotels = count($hotels);

        // $this->logger->debug('$hotels = '.print_r( $hotels,true));
        foreach ($hotels as $i => $hotel) {
            $checkIn = null;
            $checkInTime = null;
            $checkOut = null;
            $checkOutTime = null;
            $checkIn = $this->re("/{$this->opt($this->t('Check-in:'))}\s*Stay.*\n\s*(\w+\s*\d+\,\s*\d{4})\b/",
                $checkInTexts[$i] ?? '');

            if (empty($checkIn)) {
                if (preg_match("/^\n*(?: {10,}.*\n+)*(( {0,5}\S.*\n+){1,5})( {10,}.*\n+)+.*/", $checkInTexts[$i] ?? '', $m)
                    && preg_match("/^\s*\w+\s*,\s*\w+\s+\d{1,2}\s*,\s*\d{4}\s*$/",
                        preg_replace("/^ {0,5}(\S.*?)(?: {3,}.*)?$/m", '$1', $m[1]), $mat)
                ) {
                    $checkIn = $mat[0];
                } elseif (preg_match("/[\s\S]+\n\n(( {0,5}\S.*\n+){1,5})( {10,}.*\n+)+.*$/", $checkInPrecedingTexts[$i] ?? '', $m)
                    && preg_match("/^\s*\w+\s*,\s*\w+\s+\d{1,2}\s*,\s*\d{4}\s*$/",
                        preg_replace("/^ {0,5}(\S.*?)(?: {3,}.*)$/m", '$1', $m[1]), $mat)
                ) {
                    $checkIn = $mat[0];
                }

                if (preg_match("/^(?:.*\n+){1,5}.* {5,}Check-in\n.+ {3,}(\d{1,2}:\d{2}(?: *[ap]m)?)\n/i", $checkInTexts[$i] ?? '', $m)) {
                    $checkInTime = $m[1];
                }
            }

            if ($hotelsNamesCount[$hotel['name']] === 1) {
                $checkOut = $this->re("/\n.* {2,}" . preg_quote($hotel['name'], '/') . "\n+(?:.*\n+)?.* {2,}Check-out\n+ +(\w+ \d+, \d{4})\b/", $text);

                if (!empty($checkOut) && preg_match("/ {5,}Check-out\n.+ {3,}(\d{1,2}:\d{2}(?: *[ap]m)?)\n/i", $text, $m)) {
                    $checkOutTime = $m[1];
                }

                preg_match("/\n\n( {0,5}(\S.*\n)* {0,5}\S.* {2,}" . preg_quote($hotel['name'], '/') . "\n+(?:.*\n)?.* {2,}Check-out\n+ +\d{1,2}:\d{2}\D+)/", $text, $m);

                if (empty($checkOut) && preg_match("/\n\n( {0,5}(\S.*\n)* {0,5}\S.* {2,}" . preg_quote($hotel['name'], '/') . "\n+(?:.*\n)?.* {2,}Check-out\n+ +(?<time>\d{1,2}:\d{2}[^\d\n]+))\n/", $text, $m)
                    && preg_match("/^\s*\w+\s*,\s*\w+\s+\d{1,2}\s*,\s*\d{4}\s*$/",
                        preg_replace(["/^ {0,5}(\S.*?)(?: {3,}.*)?$/m", '/^ {15,}.*/m'], ['$1', ''], $m[1]), $mat)
                ) {
                    $checkOut = $mat[0];
                    $checkOutTime = $m['time'];
                } elseif (empty($checkOut)) {
                    foreach ($checkOutPrecedingTexts as $k => $ptext) {
                        if (preg_match("/[\s\S]+\n\n( {0,5}\S.* {2,}" . preg_quote($hotel['name'], '/') . "\n+( {0,5}\S.*\n+){1,5})( {10,}.*\n+)+.*$/",
                                $ptext, $m)
                            && preg_match("/^\s*\w+\s*,\s*\w+\s+\d{1,2}\s*,\s*\d{4}\s*$/",
                                preg_replace("/^ {0,5}(\S.*?)(?: {3,}.*)$/m", '$1', $m[1]), $mat)
                        ) {
                            $checkOut = $mat[0];

                            if (preg_match("/ {5,}Check-out\n.+ {3,}(\d{1,2}:\d{2}(?: *[ap]m)?)\n/i", $ptext, $m)) {
                                $checkOutTime = $m[1];
                            }

                            break;
                        }
                    }
                }
            } elseif ($hotelsNamesCount[$hotel['name']] > 1) {
                if (!isset($hotels[$i]['checkOut'])) {
                    $checkOuts = [];

                    foreach ($checkOutTexts as $k => $ptext) {
                        if (preg_match("/" . preg_replace('/ +/', ' {0,1}', $this->opt($hotel['name'])) . "/", $ptext)) {
                            $ptext = preg_replace("/" . preg_replace('/ +/', ' {0,1}', $this->opt($hotel['name'])) . "/", $hotel['name'], $ptext);
                        } else {
                            continue;
                        }
                        $ptext = preg_replace("/({$this->opt($hotel['name'])}.*(?:\n+.*){0,5})[\s\S]+/", '$1', $ptext);
                        $ptext = preg_replace("/[\s\S]+\n\n((( {0,5}\S.*\n+){1,5})( {10,}.*\n+)+.*)/", '$1', $ptext);
                        // $this->logger->debug('$ptext = '.print_r( $ptext,true));
                        if (preg_match("/\n.* {2,}" . $this->opt($hotel['name']) . "\n+(?:.*\n+)?.* {2,}Check-out\n+ +(\w+ \d+, \d{4})( {3,}|\n)/", $ptext, $m)) {
                            $checkOuts[] = $m[1];
                        } elseif (preg_match("/^\n*(?: {10,}.*\n+)*(( {0,5}\S.*\n+){1,5})( {10,}.*\n+)+.*/", $ptext, $m)
                            && preg_match("/^\s*\w+\s*,\s*\w+\s+\d{1,2}\s*,\s*\d{4}\s*$/",
                                preg_replace("/^ {0,5}(\S.*?)(?: {3,}.*)?$/m", '$1', $m[1]), $mat)
                        ) {
                            $checkOuts[] = $mat[0];
                        } elseif (preg_match("/[\s\S]+\n\n(( {0,5}\S.*\n+){1,5})( {10,}.*\n+)+.*$/", $checkOutPrecedingTexts[$k] ?? '', $m)
                            && preg_match("/^\s*\w+\s*,\s*\w+\s+\d{1,2}\s*,\s*\d{4}\s*$/",
                                preg_replace("/^ {0,5}(\S.*?)(?: {3,}.*)$/m", '$1', $m[1]), $mat)
                        ) {
                            $checkOuts[] = $mat[0];
                        }
                    }

                    if (count(array_unique($checkOuts)) === 1) {
                        $checkOut = array_shift($checkOuts);
                    } elseif (count($checkOuts) > 1 && count($checkOuts) === $hotelsNamesCount[$hotel['name']]) {
                        // если отели с одним название идут друг за другом

                        $hotelIds = [];
                        $checkIns = [];

                        for ($l = 0; $l < $countHotels; $l++) {
                            if (isset($hotels[$l]) && $hotel['name'] === $hotels[$l]['name']) {
                                $hotelIds[] = $l;
                                $checkIns[] = ($l == $i) ? strtotime($checkIn) : $hotels[$l]['checkIn'] ?? null;
                            }
                        }
                        $checkOutsToTime = array_map('strtotime', $checkOuts);

                        if ($hotelsNamesCount[$hotel['name']] === count($checkIns)) {
                            $error = false;

                            for ($hi = 0; $hi < count($checkIns); $hi++) {
                                if (isset($checkIns[$hi]) && isset($checkOutsToTime[$hi]) && $checkIns[$hi] < $checkOutsToTime[$hi]) {
                                    $hotelIds[] = $l;
                                } else {
                                    $error = true;
                                }
                            }
                        }

                        if ($error === false) {
                            foreach ($checkIns as $ci => $v) {
                                $hotels[$hotelIds[$ci]]['checkOut'] = $checkOutsToTime[$ci];
                            }
                        }
                    } elseif (count($checkOuts) > 1 && count($checkOuts) !== $hotelsNamesCount[$hotel['name']]) {
                        //265290709.eml
                        $checkOuts = $this->res("/\n.* {2,}" . $this->opt($hotel['name']) . "\n+(?:.*\n+)?.* {2,}Check-out\n+ +(\w+ \d+, \d{4})\b/", $text);

                        if (count($checkOuts) > 1 && count($checkOuts) == $hotelsNamesCount[$hotel['name']]) {
                            $hotelIds = [];
                            $checkIns = [];
                            $error = false;

                            for ($l = 0; $l < $countHotels; $l++) {
                                if (isset($hotels[$l]) && $hotel['name'] === $hotels[$l]['name']) {
                                    $hotelIds[] = $l;
                                    $checkIns[] = strtotime($checkIn);
                                }
                            }
                            $checkOutsToTime = array_map('strtotime', $checkOuts);

                            if ($hotelsNamesCount[$hotel['name']] === count($checkIns)) {
                                for ($hi = 0; $hi < count($checkIns); $hi++) {
                                    if (isset($checkIns[$hi]) && isset($checkOutsToTime[$hi]) && $checkIns[$hi] < $checkOutsToTime[$hi]) {
                                        if (isset($checkOutsToTime[$hi - 1])) {
                                            if ($checkOutsToTime[$hi - 1] > $checkIns[$hi]) {
                                                $error = true;
                                            }
                                        }
                                        $hotelIds[] = $l;
                                    }
                                }
                            }

                            if ($error === false) {
                                foreach ($checkIns as $ci => $v) {
                                    $hotels[$hotelIds[$ci]]['checkOut'] = $checkOutsToTime[$ci];
                                }
                            }
                        }
                    }
                }
            }

            $hotels[$i]['checkIn'] = null;

            if (!empty($checkIn)) {
                $checkIn = preg_replace("/\s+/", ' ', $checkIn);
                $hotels[$i]['checkIn'] = strtotime($checkIn);
            }

            if (!empty($hotels[$i]['checkIn']) && !empty($checkInTime)) {
                $hotels[$i]['checkIn'] = strtotime($checkInTime, $hotels[$i]['checkIn']);
            }

            if (empty($hotels[$i]['checkOut'])) {
                $hotels[$i]['checkOut'] = null;

                if (!empty($checkOut)) {
                    $checkOut = preg_replace("/\s+/", ' ', $checkOut);
                    $hotels[$i]['checkOut'] = strtotime($checkOut);
                }
            }

            if (!empty($hotels[$i]['checkOut']) && !empty($checkOutTime)) {
                $hotels[$i]['checkOut'] = strtotime($checkOutTime, $hotels[$i]['checkOut']);
            }

            $hotels[$i]['phone'] = $this->re("/Phone: {0,5}([\+\-\d\(\)].+?)( {2,}|\n)/", $checkInTexts[$i] ?? '');

            $roomType = $this->re("/Room Type:(.+)/", $checkInTexts[$i] ?? '');

            if (empty($roomType)) {
                if (preg_match("/[ ]{10,}\/\/\s*(?<type>.+)[ ]{30,}(?<rooms>\d+)\s*Room\s*\((?<guests>\d+)\s*Adults?\)/", $checkInTexts[$i] ?? '', $m)) {
                    $roomType = $m['type'];
                    $hotels[$i]['guests'] = $m['guests'];
                    $hotels[$i]['room'] = $m['rooms'];
                }
            }

            if (empty($roomType)) {
                $roomType = $this->re("/\n +[\\/]{2} *(.+)\s+Check-in/", $checkInTexts[$i] ?? '');
            }

            if (empty($roomType)) {
                $roomType = trim(preg_replace(['/^(.{40,}) {3,}\S.{0,15}$/m', "/\s+[\\/]{2}\s*/", '/\s+/'], ['$1', ' ', ' '],
                    $this->re("/\n\n+(.*\n *[\\/]{2}\s+.+\n(?: {15}.*\n+){1,3}) +Check-in +Stay/", $checkInTexts[$i] ?? '')));
            }

            if (empty($roomType)) {
                $roomType = preg_replace(['/^(.{40,}) {3,}\S.{0,15}$/m', "/\s*\n\s*/"], ['$1', " "],
                    $this->re("#//\n\s*(.*\n+\s*\w+)\n+\s*Check\-in\s*Stay#", $checkInTexts[$i] ?? ''));
            }

            $roomDescription = $this->re("/{$this->opt($this->t('Rate Description'))}\n\s*(.+)\n\s*{$this->opt($this->t('How to get there'))}/s", $checkInTexts[$i]);

            $hotels[$i]['description'][] = trim($roomDescription);

            $hotels[$i]['rooms'][] = trim($roomType);

            $hotels[$i]['cancellation'] = preg_replace("/(\n\s*)/", " ", $this->re("/(?:Cancelation Policy|Vendor Refund and Cancellation Terms:|Cancellation Policy)\n\s*((.+\n){1,3}?)(?:\n{2,}|$|\s*CANCELS)/u",
                $checkInTexts[$i] ?? ''));

            $hotels[$i]['confirmation'] = $this->re("/Confirmation Number: {0,5}([\w]+[\w\-]{4,})( {2,}|\n)/", $checkInTexts[$i] ?? '');

            $hotels[$i]['notes'][] = str_replace("\n", " ", $this->re("/{$this->opt($this->t('How to get there'))}\n\s*(.+)\n\s*{$this->opt($this->t('Other Information'))}/s", $checkInTexts[$i]));
        }

        // $this->logger->debug('$hotels = '.print_r( $hotels,true));

        foreach ($hotels as $hotel) {
            $h = $email->add()->hotel();

            // General
            if (!empty($hotel['confirmation'])) {
                $h->general()
                    ->confirmation($hotel['confirmation'])
                ;
            } else {
                $h->general()
                    ->noConfirmation()
                ;
            }

            if (!empty($this->travellers)) {
                $h->general()
                    ->travellers($this->travellers)
                    ->cancellation($hotel['cancellation'])
                ;
            }

            if (!empty($hotel['cancellation'])) {
                $h->general()
                    ->cancellation($hotel['cancellation'])
                ;
            }

            if (!empty($this->status)) {
                $h->general()
                    ->status($this->status);
            }

            // Hotel
            $h->hotel()
                ->name($hotel['name'])
                ->address($hotel['address'])
                ->phone($hotel['phone'])
            ;

            // Booked
            $h->booked()
                ->checkIn($hotel['checkIn'] ?? null)
                ->checkOut($hotel['checkOut'] ?? null);

            if (isset($hotel['guests']) && !empty($hotel['guests'])) {
                $h->booked()
                    ->guests($hotel['guests']);
            }

            if (isset($hotel['room']) && !empty($hotel['room'])) {
                $h->booked()
                    ->rooms($hotel['room']);
            }

            foreach ($hotel['rooms'] as $key => $roomType) {
                $notes = $hotel['notes'][$key];

                if (!empty($notes)) {
                    $h->setNotes(preg_replace("/\s+/", " ", $notes));
                }

                $description = preg_replace("/(\n\s*)/s", " ", $hotel['description'][$key]);

                if (!empty($roomType) || !empty($description)) {
                    $room = $h->addRoom();

                    if (!empty($description) && preg_match("/{$this->opt($description)}/iu", $roomType)) {
                        $room->setDescription($description);
                    } else {
                        if (!empty($description)) {
                            $room->setDescription($description);
                        }

                        if (!empty($roomType) && strlen($roomType) < 250) {
                            $room->setType($roomType);
                        } elseif (!empty($roomType) && strlen($roomType) > 250 && empty($description)) {
                            $room->setDescription($description);
                        }
                    }
                }
            }
            $this->detectDeadLine($h);
        }
    }

    private function parseRental(Email $email, string $text): void
    {
        $rentalSegments = $this->splitText($text, "/(\n\n(?:.*\n{1,2}){1,3}.* {3,}(?:Drop-off|Pick-up)\s*\n.* {3,}\d{1,2}:\d{2})/", true, false);

        $error = false;

        foreach ($rentalSegments as $i => $rSeg) {
            if ($i == 0) {
                continue;
            }

            $date = $dateSeg = null;
            $dateText = $this->re("/\n\n( {0,5}(?:.*\n+){1,3}.* {3,}(?:Drop-off|Pick-up)\s*\n.* {3,}\d{1,2}:\d{2})/", $rSeg);

            if (!empty($dateText)) {
                $date = trim(preg_replace(["/^ {0,5}(\S.*?)(?: {3,}.*)?$/m", "/^ {15,}.*/m"], ['$1', ''], $dateText));
            }

            if (empty($date) && isset($rentalSegments[$i - 1])) {
                $dateText = $this->re("/[\s\S]+\n\n(( {0,5}\S.*\n+){1,5})( {10,}.*\n+)+.*$/", $rentalSegments[$i - 1]);

                if (!empty($dateText)) {
                    $date = trim(preg_replace(["/^ {0,5}(\S.*?)(?: {3,}.*)?$/m", "/^ {15,}.*/m"], ['$1', ''], $dateText));
                }
            }

            if ($date && preg_match("/^\s*\w+\s*,\s*\w+\s+\d{1,2}\s*,\s*\d{4}\s*$/", $date, $m)
            ) {
                $dateSeg = strtotime(preg_replace('/\s+/', ' ', $date));
            }

            $info = $this->re("/\n\n((?:.*\n+){1,3}.* {3,}(?:Drop-off|Pick-up)\s*\n.* {3,}\d{1,2}:\d{2}(?:.+\n+){5,20}?.*or similar.*\n(?:.*\n)+?(?:\n\n\n|$))/", $rSeg);

            $conf = $this->re("/Confirmation Number: *([\w\-]{5,})\s*(?:\n|$)/", $info);

            if (preg_match("/Pick-up\s*\n.* {3,}(?<time>\d{1,2}:\d{2}.+)\s*\n(?<location>(?:.*\n){1,3})\n/", $info, $m)) {
                if (isset($rentals[$conf]['puLocation'], $rentals[$conf]['puDate'])) {
                    $rentals = [];
                    $error = true;
                    $this->logger->debug('parse rental segment error 1');

                    break;
                }
                $m['location'] = preg_replace("/\s+/", '', $m['location']);
                $rentals[$conf]['puLocation'] = $m['location'];

                if (!empty($dateSeg)) {
                    $rentals[$conf]['puDate'] = strtotime(trim($m['time']), $dateSeg);
                }

                if (preg_match("/.+ {3,}(.+) or similar/i", $info, $m)) {
                    $rentals[$conf]['car'] = $m[1];
                }

                if (preg_match("/\s+Phone: *([\d\+\-\(\) \.]{5,})\s*(?: {3,}|\n)/i", $info, $m)) {
                    $rentals[$conf]['puPhone'] = $m[1];
                }
            } elseif (preg_match("/Drop-off\s*\n.* {3,}(?<time>\d{1,2}:\d{2}.+)\s*\n(?<location>(?:.*\n){1,3})\n/", $info, $m)) {
                if (isset($rentals[$conf]['doLocation'], $rentals[$conf]['doDate'])) {
                    $rentals = [];
                    $error = true;
                    $this->logger->debug('parse rental segment error 2 ');

                    break;
                }
                $m['location'] = preg_replace("/\s+/", '', $m['location']);
                $rentals[$conf]['doLocation'] = $m['location'];

                if (!empty($dateSeg)) {
                    $rentals[$conf]['doDate'] = strtotime(trim($m['time']), $dateSeg);
                }

                if (preg_match("/.+ {3,}(.+) or similar/i", $info, $m)) {
                    $rentals[$conf]['car'] = $m[1];
                }

                if (preg_match("/\s+Phone: *([\d\+\-\(\) \.]{5,})\s*(?: {3,}|\n)/i", $info, $m)) {
                    $rentals[$conf]['doPhone'] = $m[1];
                }
            } else {
                $rentals = [];
                $error = true;
                $this->logger->debug('parse rental segment error 3');

                break;
            }
        }

        if (empty($rentals) && $error == true) {
            $email->add()->rental();

            return;
        }

        foreach ($rentals as $conf => $rsegment) {
            $r = $email->add()->rental();

            // General
            $r->general()
                ->confirmation($conf)
            ;

            if (!empty($this->travellers)) {
                $r->general()
                    ->travellers($this->travellers)
                ;
            }

            if (!empty($this->status)) {
                $r->general()
                    ->status($this->status);
            }

            // Pick Up
            $r->pickup()
                ->location($rsegment['puLocation'])
                ->date($rsegment['puDate'])
                ->phone($rsegment['puPhone'])
            ;

            // Drop Off
            $r->dropoff()
                ->location($rsegment['doLocation'])
                ->date($rsegment['doDate'])
                ->phone($rsegment['doPhone'])
            ;

            // Car

            $r->car()
                ->model($rsegment['car'] ?? '', true, true);
        }
    }

    private function parseCruise(Email $email, string $text): void
    {
        $c = $email->add()->cruise();

        $c->general()
            ->noConfirmation();

        $c->setShip($this->re("/SHIP\:\s*(.+)\n/", $text));

        $class = $this->re("/Cabin Category\:\s*(.+)[ ]{3,}/", $text);

        if (empty($class)) {
            $class = $this->re("/Cabin Category\:\s*(.+)\n/", $text);
        }

        if (!empty($class)) {
            $c->setClass($class);
        }

        $deck = $this->re("/Deck\:\s*(.+)\n/", $text);

        if (!empty($deck)) {
            $c->setDeck($deck);
        }

        $voyageNumber = $this->re("/Cabin\s*[\#\:]+\s*(\d+)/", $text);

        if (!empty($voyageNumber)) {
            $c->setVoyageNumber($voyageNumber);
        }

        $rentalSegments = $this->splitText($text, "/\n{3,}(\w+\,\s*\D+\n\w+\s*\d+\,\n\d{4}\s*(?:Embark|In\-Port|Disembark|Debark))/i", true);

        foreach ($rentalSegments as $rSeg) {
            $s = $c->addSegment();

            $segTable = $this->splitCols($rSeg, [0, 25]);

            $date = str_replace("\n", " ", $this->re("/^(\w+\,\n\w+\s*\d+\,\n\d{4})/", $segTable[0]));

            if (preg_match("/^\s*.+\n+\s*(?:Embark)\n+\s*(?<portName>\D+)\n+\s*(?<aBoard>[\d\:]+\s*a?p?m)\n+/i", $segTable[1], $m)) {
                $s->setName($m['portName']);
                $s->setAboard(strtotime($date . ', ' . $m['aBoard']));
            } elseif (preg_match("/^\s*(?<ship>.+)\n+\s*(?:In\-Port|Disembark)\n+\s*(?<portName>\D+)\n+\s*(?<aShore>[\d\:]+\s*a?p?m)[\-\s]+(?<aBoard>[\d\:]+\s*a?p?m)\n+(?:\s+(?:Cabin\s*Category\:\s*(?<class>.+)))?(?:\s*Deck\:\s*(?<desk>.+)\n+)?(?:\s*Cabin\s*[#\:]+\s*(?<voyageNumber>\d+))?/iu", $segTable[1], $m)) {
                $s->setName($m['portName']);
                $s->setAboard(strtotime($date . ', ' . $m['aBoard']));
                $s->setAshore(strtotime($date . ', ' . $m['aShore']));
            } elseif (preg_match("/^\s*.+\n+\s*(?:Debark)\n+\s*(?<portName>\D+)\n+\s*(?<aShore>[\d\:]+\s*a?p?m)\n+/", $segTable[1], $m)) {
                $s->setName($m['portName']);
                $s->setAshore(strtotime($date . ', ' . $m['aShore']));
            } elseif (preg_match("/^\s*.+\n+\s*(?:In-port)\n+\s*(?<portName>\D+)\n+\s*\n+\s*Cabin\s*Category\:/", $segTable[1])) {
                $c->removeSegment($s);
            }
        }
    }

    private function parseShortFlights(Email $email, array $airs): void
    {
        $this->logger->debug(__METHOD__);
        $f = $email->add()->flight();

        $f->general()
            ->noConfirmation();

        if (!empty($this->travellers)) {
            $f->general()
                ->travellers($this->travellers);
        }

        if (!empty($this->status)) {
            $f->general()
                ->status($this->status);
        }

        foreach ($airs as $key => $sText) {
            $table = $this->splitCols($sText['segment'], $this->rowColsPos($this->inOneRow($sText['segment'])));

            if (preg_match("/^\s*[A-Z]{3,4}\s+\d{1,2}\s*$/", $table[0])) {
                unset($table[0]);
                $table = array_values($table);
            }

            $s = $f->addSegment();

            if (count($table) !== 3) {
                continue;
            }
            $transfer = null;

            if (preg_match("/^ *Via:(.+)/m", $table[1], $m)) {
                $transfer = trim($m[1]);

                $s1 = $f->addSegment();

                $s1->airline()
                    ->noName()
                    ->noNumber()
                ;
            }

            // Airline
            if (preg_match("/^\s*[^\n:]+ *\((?<name>[A-Z\d][A-Z]|[A-Z][A-Z\d])\) *(?<number>\d{1,5}), *(?<cabin>.+)/", $table[2], $m)) {
                $s->airline()
                    ->name($m['name'])
                    ->number($m['number']);

                $s->extra()
                    ->cabin($m['cabin']);
            } else {
                $s->airline()
                    ->noName()
                    ->noNumber()
                ;
            }

            if (preg_match("/(?:^|\n)\s*Operated By:(.+)/", $table[2], $m)) {
                $s->airline()
                    ->operator(preg_replace(['/ DBA .* /', '/.* AS /'], '', trim($m[1])));
            }

            if (preg_match("/Confirmation Number: *\((?:[A-Z\d][A-Z]|[A-Z][A-Z\d])\) *([A-Z\d]{5,7})\s+/", $table[2], $m)) {
                $s->airline()
                    ->confirmation($m[1]);
            }

            // Departure
            if (preg_match("/Departure: (?<name>.+?) \((?<code>[A-Z]{3})\)/", $table[1], $m)) {
                $s->departure()
                    ->name($m['name'])
                    ->code($m['code']);
            }

            if (preg_match("/Departure: .+? \([A-Z]\) *, *(?<terminal>[^\n,]*Terminal[^\n,]*)/", $table[1], $m)) {
                $s->departure()->terminal(trim(preg_replace("/\s*\bTerminal\b\s*/i", ' ', $m['terminal'])));
            }

            // Arrival
            if (preg_match("/Arrival: (?<name>.+?) \((?<code>[A-Z]{3})\)/", $table[1], $m)) {
                if (!empty($transfer)) {
                    $s->arrival()
                        ->name($transfer)
                        ->noCode();
                    $s1->departure()
                        ->name($transfer)
                        ->noCode();
                    $s1->arrival()
                        ->name($m['name'])
                        ->code($m['code']);

                    if (preg_match("/Arrival: .+? \([A-Z]\) *, *(?<terminal>[^\n,]*Terminal[^\n,]*)/", $table[1], $m)) {
                        $s1->arrival()->terminal(trim(preg_replace("/\s*\bTerminal\b\s*/i", ' ', $m['terminal'])));
                    }
                } else {
                    $s->arrival()
                        ->name($m['name'])
                        ->code($m['code']);

                    if (preg_match("/Arrival: .+? \([A-Z]\) *, *(?<terminal>[^\n,]*Terminal[^\n,]*)/", $table[1], $m)) {
                        $s->arrival()->terminal(trim(preg_replace("/\s*\bTerminal\b\s*/i", ' ', $m['terminal'])));
                    }
                }
            }

            if (preg_match("/^\s*(?<d1>\d{1,2}:\d{1,2}(?: *[ap]m)?)\s*\n\s*(?<d2>\d{1,2}:\d{1,2}(?: *[ap]m)?)\s*$/i", $table[0], $m)) {
                $s->departure()
                    ->date((!empty($sText['date'])) ? strtotime($m['d1'], $sText['date']) : null);

                if (!empty($transfer)) {
                    $s->arrival()
                        ->noDate();
                    $s1->departure()
                        ->noDate();
                    $s1->arrival()
                        ->date((!empty($sText['date'])) ? strtotime($m['d2'], $sText['date']) : null);
                } else {
                    $s->arrival()
                        ->date((!empty($sText['date'])) ? strtotime($m['d2'], $sText['date']) : null);
                }
            }
        }
    }

    private function parseShortHotels(Email $email, array $hotelSegments): void
    {
        foreach ($hotelSegments as $hs) {
            $table = $this->splitCols($hs['segment']);

            if ((preg_match("/Check-in:(.+)/s", $table[count($table) - 2], $mNameCi)
                    || preg_match("/Check-out:(.+)/s", $table[count($table) - 2], $mNameCo)
                ) && preg_match("/Address:(.+?)(?:\n[\w ]+:|$)/s", $table[count($table) - 1], $mAddr)
            ) {
                if (!empty($mNameCi[1])) {
                    $name = preg_replace('/\s+/', ' ', trim($mNameCi[1]));
                    $hotels[$name]['name'] = $name;
                    $hotels[$name]['address'] = preg_replace('/\s+/', ' ', trim($mAddr[1]));

                    if (isset($hotels[$name], $hotels[$name]['checkIns'])) {
                        $hotels[$name]['checkIns'] = array_merge($hotels[$name]['checkIns'], [$hs['date']]);
                    }
                    $hotels[$name]['checkIns'] = [$hs['date']];

                    if (preg_match("/^\s*(\d{1,2}:\d{2}(?: *[ap]m)?)\s*$/i", $table[count($table) - 3], $mt)) {
                        $hotels[$name]['checkInsTime'] = [$mt[1]];
                    } else {
                        $hotels[$name]['checkInsTime'] = [];
                    }
                    $hotels[$name]['checkOuts'] = $hotels[$name]['checkOuts'] ?? [];
                    $hotels[$name]['checkOutsTime'] = $hotels[$name]['checkOutsTime'] ?? [];
                }

                if (!empty($mNameCo[1])) {
                    $name = preg_replace('/\s+/', ' ', trim($mNameCo[1]));
                    $hotels[$name]['name'] = $name;
                    $hotels[$name]['address'] = preg_replace('/\s+/', ' ', trim($mAddr[1]));

                    if (isset($hotels[$name], $hotels[$name]['checkOuts'])) {
                        $hotels[$name]['checkOuts'] = array_merge($hotels[$name]['checkOuts'], [$hs['date']]);
                    }
                    $hotels[$name]['checkOuts'] = [$hs['date']];

                    if (preg_match("/^\s*(\d{1,2}:\d{2}(?: *[ap]m)?)\s*$/i", $table[count($table) - 3], $mt)) {
                        $hotels[$name]['checkOutsTime'] = [$mt[1]];
                    } else {
                        $hotels[$name]['checkOutsTime'] = [];
                    }
                    $hotels[$name]['checkIns'] = $hotels[$name]['checkIns'] ?? [];
                    $hotels[$name]['checkInsTime'] = $hotels[$name]['checkInsTime'] ?? [];
                }
            } else {
                $hotels[] = [
                    'name'          => '',
                    'address'       => '',
                    'checkIns'      => [],
                    'checkInsTime'  => [],
                    'checkOuts'     => [],
                    'checkOutsTime' => [],
                ];
            }
        }

        foreach ($hotels as $hotelArray) {
            if (count(array_unique($hotelArray['checkIns'])) == 1 && count(array_unique($hotelArray['checkOuts'])) == 1) {
                $h = $email->add()->hotel();

                // General
                if (!empty($hotelArray['confirmation'])) {
                    $h->general()
                        ->confirmation($hotelArray['confirmation'])
                    ;
                } else {
                    $h->general()
                        ->noConfirmation()
                    ;
                }

                if (!empty($this->travellers)) {
                    $h->general()
                        ->travellers($this->travellers)
                    ;
                }

                if (!empty($this->status)) {
                    $h->general()
                        ->status($this->status);
                }

                // Hotel
                $h->hotel()
                    ->name($hotelArray['name'])
                    ->address($hotelArray['address'])
                ;

                // Booked
                $dt = $hotelArray['checkIns'][0] ?? null;

                if (!empty($dt) && !empty($hotelArray['checkInsTime'][0])) {
                    $dt = strtotime($hotelArray['checkInsTime'][0], $dt);
                }
                $h->booked()
                    ->checkIn($dt);

                $dt = $hotelArray['checkOuts'][0] ?? null;

                if (!empty($dt) && !empty($hotelArray['checkOutsTime'][0])) {
                    $dt = strtotime($hotelArray['checkOutsTime'][0], $dt);
                }
                $h->booked()
                    ->checkOut($dt);
            } else {
                $h = $email->add()->hotel();
            }
        }
    }

    private function parseShortRentals(Email $email, array $rentalSegments): void
    {
        $rentals = [];
        $error = false;

        foreach ($rentalSegments as $hs) {
            $table = $this->splitCols($hs['segment']);
            $conf = $this->re("/Confirmation number: *([\w\-]{5,})\s*(?:\n|$)/", $table[count($table) - 1]);

            if (preg_match("/Pick-up:(.+)/s", $table[count($table) - 2], $m)) {
                if (isset($rentals[$conf]['puLocation'], $rentals[$conf]['puDate'])) {
                    $rentals = [];
                    $error = true;
                    $this->logger->debug('parse rental segment error');

                    break;
                }
                $m[1] = preg_replace("/\s+/", '', $m[1]);
                $rentals[$conf]['puLocation'] = $m[1];

                if (!empty($hs['date']) && preg_match("/^\s*(\d{1,2}:\d{2}(?: *[ap]m)?)\s*$/i", $table[count($table) - 3], $m)) {
                    $rentals[$conf]['puDate'] = strtotime(trim($table[count($table) - 3]), $hs['date']);
                }

                if (preg_match("/(.+) or similar/i", $table[count($table) - 1], $m)) {
                    $rentals[$conf]['car'] = $m[1];
                }

                if (preg_match("/Phone number: *([\d\+\-\(\) \.]{5,})\s*(?:$|\n)/i", $table[count($table) - 1], $m)) {
                    $rentals[$conf]['puPhone'] = $m[1];
                }
            } elseif (preg_match("/Drop-off:(.+)/s", $table[count($table) - 2], $m)) {
                if (isset($rentals[$conf]['doLocation'], $rentals[$conf]['doDate'])) {
                    $rentals = [];
                    $error = true;
                    $this->logger->debug('parse rental segment error');

                    break;
                }
                $m[1] = preg_replace("/\s+/", '', $m[1]);
                $rentals[$conf]['doLocation'] = $m[1];

                if (!empty($hs['date']) && preg_match("/^\s*(\d{1,2}:\d{2}(?: *[ap]m)?)\s*$/i", $table[count($table) - 3], $m)) {
                    $rentals[$conf]['doDate'] = strtotime(trim($table[count($table) - 3]), $hs['date']);
                }

                if (preg_match("/Phone number: *([\d\+\-\(\) \.]{5,})\s*(?:$|\n)/i", $table[count($table) - 1], $m)) {
                    $rentals[$conf]['doPhone'] = $m[1];
                }
            } else {
                $rentals = [];
                $error = true;
            }
        }

        if (empty($rentals) && $error == true) {
            $email->add()->rental();

            return;
        }

        foreach ($rentals as $conf => $rsegment) {
            $r = $email->add()->rental();

            // General
            $r->general()
                ->confirmation($conf)
            ;

            if (!empty($this->travellers)) {
                $r->general()
                    ->travellers($this->travellers)
                ;
            }

            if (!empty($this->status)) {
                $r->general()
                    ->status($this->status);
            }

            // Pick Up
            $r->pickup()
                ->location($rsegment['puLocation'])
                ->date($rsegment['puDate'])
                ->phone($rsegment['puPhone'])
            ;

            // Drop Off
            $r->dropoff()
                ->location($rsegment['doLocation'])
                ->date($rsegment['doDate'])
                ->phone($rsegment['doPhone'])
            ;

            // Car

            $r->car()
                ->model($rsegment['car'] ?? '', true, true);
        }
    }

    private function assignLang($text): bool
    {
        if (empty($text) || !isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang)) {
                continue;
            }

            if ($this->strposArray($text, $phrases['DATE']) !== false
                && $this->strposArray($text, $phrases['TIME']) !== false
                && ($this->strposArray($text, $phrases['Duration']) !== false || $this->strposArray($text, $phrases['Check-in:']) !== false || $this->strposArray($text, $phrases['Embark']) || $this->strposArray($text, $phrases['Departure:']) !== false)
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function strposArray($text, $phrases)
    {
        if (empty($text)) {
            return false;
        }

        foreach ((array) $phrases as $phrase) {
            if (!is_string($phrase)) {
                continue;
            }
            $result = strpos($text, $phrase);

            if ($result !== false) {
                return $result;
            }
        }

        return false;
    }

    private function t(string $phrase)
    {
        if (!isset(self::$dictionary, $this->lang) || empty(self::$dictionary[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$this->lang][$phrase];
    }

    private function splitText(?string $textSource, string $pattern, bool $saveDelimiter = false, $deleteFirst = true): array
    {
        $result = [];

        if ($saveDelimiter) {
            $textFragments = preg_split($pattern, $textSource, -1, PREG_SPLIT_DELIM_CAPTURE);

            if ($deleteFirst === false) {
                $result[] = array_shift($textFragments);
            } else {
                array_shift($textFragments);
            }

            for ($i = 0; $i < count($textFragments) - 1; $i += 2) {
                $result[] = $textFragments[$i] . $textFragments[$i + 1];
            }
        } else {
            $result = preg_split($pattern, $textSource);
            array_shift($result);
        }

        return $result;
    }

    private function rowColsPos(?string $row): array
    {
        $pos = [];
        $head = preg_split('/\s{2,}/', $row, -1, PREG_SPLIT_NO_EMPTY);
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }

    private function splitCols(?string $text, ?array $pos = null): array
    {
        $cols = [];

        if ($text === null) {
            return $cols;
        }
        $rows = explode("\n", $text);

        if ($pos === null || count($pos) === 0) {
            $pos = $this->rowColsPos($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k => $p) {
                $cols[$k][] = rtrim(mb_substr($row, $p, null, 'UTF-8'));
                $row = mb_substr($row, 0, $p, 'UTF-8');
            }
        }
        ksort($cols);

        foreach ($cols as &$col) {
            $col = implode("\n", $col);
        }

        return $cols;
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return str_replace(' ', '\s*', preg_quote($s, '/'));
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

    private function res($re, $str, $c = 1)
    {
        preg_match_all($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function inOneRow($text)
    {
        $textRows = array_filter(explode("\n", $text));
        $length = [];

        foreach ($textRows as $key => $row) {
            $length[] = mb_strlen($row);
        }
        $length = max($length);
        $oneRow = '';

        for ($l = 0; $l < $length; $l++) {
            $notspace = false;

            foreach ($textRows as $key => $row) {
                $sym = mb_substr($row, $l, 1);

                if ($sym !== false && trim($sym) !== '') {
                    $notspace = true;
                    $oneRow[$l] = 'a';
                }
            }

            if ($notspace == false) {
                $oneRow[$l] = ' ';
            }
        }

        return $oneRow;
    }

    private function detectDeadLine(Hotel $h): void
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (preg_match("/Refundable (\d+) Days before Arrival/u", $cancellationText, $m)) {
            $h->booked()
                ->deadlineRelative($m[1] . ' days');
        }

        $cancellationText = str_replace('12:00:00 AM', '12:00', $cancellationText);

        if (preg_match("/Refundable before ([\d\/]+\s*[\d\:]+) CXL 0100 HTL TIME ON /u", $cancellationText, $m)) {
            $h->booked()
                ->deadline(strtotime($m[1]));
        }

        if (preg_match("#Refundable\s*before\s*([\/\d]+)\s*(\d+\:\d+)#u", $cancellationText, $m)) {
            $h->booked()
                ->deadline(strtotime($m[1] . ',' . $m[2]));
        }

        if (preg_match("/If cancellation is made on or after (\d{4})\-(\d{2})\-(\d{2})/u", $cancellationText, $m)) {
            $h->booked()
                ->deadline(strtotime($m[3] . '.' . $m[2] . '.' . $m[1]));
        }
    }
}
