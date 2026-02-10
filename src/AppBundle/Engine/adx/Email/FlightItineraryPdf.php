<?php

namespace AwardWallet\Engine\adx\Email;

use AwardWallet\Schema\Parser\Common\FlightSegment;
use AwardWallet\Schema\Parser\Email\Email;

class FlightItineraryPdf extends \TAccountChecker
{
    public $mailFiles = "adx/it-80881082.eml, adx/it-80927818.eml, adx/it-84639656.eml";

    public $lang = '';
    public $date;

    public static $dictionary = [
        'en' => [
            'YOUR AGENT'       => ['YOUR AGENT', 'YOUR ADVISOR'],
            'Cabin:'           => ['Cabin:'],
            'Aircraft:'        => ['Aircraft:'],
            'HOTEL OVERVIEW'   => ['HOTEL OVERVIEW'],
            'HOW TO GET THERE' => ['HOW TO GET THERE', 'RATE DESCRIPTION'],
            'airEnd'           => ['Frequent Flyer Numbers', 'Reminder', 'Fare Rules', 'Airfare Brand Information'],
            'hotelEnd'         => ['WEBSITE', 'PHONE'],
            'tripTypes'        => ['Round-Trip', 'One-way', 'Multi-City'],
            'statusVariants'   => ['OFFERED', 'TICKETED', 'BOOKED'],
        ],
    ];

    private $subjects = [
        'en' => ['Round Trip Flights:', 'One Way Flight:'],
    ];
    private $travellers = [];

    private $patterns = [
        'travellerName' => '[[:alpha:]][-–.\'’[:alpha:] ]*[[:alpha:]]',
    ];

    private $namePrefixes = ['Mr.', 'Mrs.', 'Dr.', 'Miss ', 'Ms.'];

    public function ParseFlightSegment(FlightSegment $s, array $table, $date, array &$travellers, array &$usedAirlines): void
    {
        if (preg_match("/^\s*(?<name>[\s\S]+)\n+[ ]*{$this->opt($this->t('Flight'))}\s+(?<number>\d+)(?:\n|$)/", $table[0], $m)) {
            $airline = $this->re("/\n\s*(\D+)\nFlight\s*{$m['number']}/u", $table[0]);

            if (empty($airline)) {
                $airline = $this->re("/^\s*(\D+)\nFlight\s*{$m['number']}/u", $table[0]);
            }
            $s->airline()
                ->name($airline)
                ->number($m['number']);
            $usedAirlines[] = $airline;
        }

        if (preg_match("/^[ ]*{$this->opt($this->t('Cabin'))}[ ]*:[ ]*([\s\S]+?)(?:[ ]*,|\n+.+:)/m", $table[0], $m)) {
            $s->extra()->cabin(preg_replace('/\s+/', ' ', $m[1]));
        }

        if (preg_match("/\b{$this->opt($this->t('Class'))}[ ]*:[ ]*([A-Z]{1,2})$/m", $table[0], $m)) {
            $s->extra()->bookingCode($m[1]);
        }

        if (preg_match("/^[ ]*{$this->opt($this->t('Aircraft'))}[ ]*:[ ]*([A-Za-z\s\d\/\-]+\d(?:\s+^[^\n:]+$)*)/m", $table[0], $m)) {
            $s->extra()->aircraft(preg_replace('/\s+/', ' ', $m[1]));
        }

        if (preg_match("/^[ ]*{$this->opt($this->t('Meal'))}[ ]*:[ ]*(.+(?:\s+^[^\n:]+$)*)/m", $table[0], $m)) {
            $s->extra()->meal(preg_replace('/\s+/', ' ', $m[1]));
        }

        /*
            Chicago
            ORD
            08:35 AM
            Terminal 3
        */
        $pattern = "/^\s*"
            . "(?<name>.{3,}?)\n+"
            . "[ ]*(?<code>[A-Z]{3})\n+"
            . "[ ]*(?<time>\d{1,2}:\d{2}[^\n]*)\n+"
            . "[ ]*T?e?r?m?i?n?a?l?\s+(?:[-–]|(?<terminal>\S+))"
            . "/su";

        if (preg_match($pattern, $table[1], $m)) {
            $s->departure()
                ->name(preg_replace('/\s+/', ' ', $m['name']))
                ->code($m['code']);

            if ($date) {
                $s->departure()->date(strtotime($m['time'], $date));
            }

            if (!empty($m['terminal'])) {
                $s->departure()->terminal($m['terminal']);
            }
        }

        if (preg_match($pattern, $table[3], $m)) {
            $s->arrival()
                ->name(preg_replace('/\s+/', ' ', $m['name']))
                ->code($m['code']);

            if ($date) {
                if (preg_match("/A?P?M\s*[+]\s*[1]/", $m['time'])) {
                    $this->date = strtotime('+1 day', $date);
                    $m['time'] = str_replace("+1", "", $m['time']);
                }

                $s->arrival()->date(strtotime($m['time'], $this->date));
            }

            if (!empty($m['terminal'])) {
                $s->arrival()->terminal($m['terminal']);
            }
        }

        if (preg_match("/^[ ]*(\d[\d hm]+)/im", $table[2], $m)) {
            $s->extra()->duration($m[1]);
        }

        if (!empty($table[4])) {
            $table[4] = preg_replace('/[– ]+$/m', '', $table[4]);

            $travellers_temp = $this->splitText($table[4], "/^({$this->opt($this->namePrefixes)}\s*{$this->patterns['travellerName']})$/mu", true);

            if (count($travellers_temp) == 0) {
                $table[4] = preg_replace('/\n\n\n\n\n/m', ';', $table[4]);
                $table[4] = preg_replace('/\n\s*/m', ' ', $table[4]);
                $travellers_temp = array_filter(array_unique(explode(';', $table[4])));
            }

            foreach ($travellers_temp as $tName) {
                $tName = preg_replace('/\s+/', ' ', trim($tName));

                if (preg_match("/^{$this->patterns['travellerName']}$/u", $tName)) {
                    $travellers[] = str_replace(['Mr.', 'Mrs.', 'Dr.', 'Miss ', 'Ms.'], '', $tName);
                }
            }
        }

        if (!empty($table[5])) {
            $table[5] = preg_replace('/^[ ]+/m', '', $table[5]);

            if (preg_match_all("/^\d+[A-Z]$/m", $table[5], $seatMatches)) {
                $s->extra()->seats($seatMatches[0]);
            }
        }
    }

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
        $detectProvider = $this->detectEmailFromProvider($parser->getHeader('from')) === true;

        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (!$textPdf) {
                continue;
            }

            if ($detectProvider === false && stripos($textPdf, 'clients.adxtravel.com') === false
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
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (!$textPdf) {
                continue;
            }

            if ($this->assignLang($textPdf)) {
                if (preg_match("/^[ ]*({$this->opt($this->t('TRIP REFERENCE'))})[ ]+([-A-z\d]{5,})(?:[ ]{2}|{$this->opt($this->t('YOUR AGENT'))}|$)/m", $textPdf, $m)) {
                    $email->ota()->confirmation($m[2], $m[1]);
                }

                if (preg_match("/\n([ ]*{$this->opt($this->t('TRAVELERS'))}\s+.+?)\n\n\n\n{$this->opt($this->t('SERVICES'))}/s", $textPdf, $m)) {
                    $tablePos = [0];

                    if (preg_match("/^([ ]*{$this->opt($this->t('TRIP REFERENCE'))} .+ ){$this->opt($this->t('YOUR AGENT'))}/m", $textPdf, $matches)) {
                        $tablePos[] = mb_strlen($matches[1]);
                    }
                    $table = $this->splitCols($m[1], $tablePos);
                    $table[0] = preg_replace("/^[ ]*{$this->opt($this->t('TRAVELERS'))}\s+/", '', $table[0]);
                    $travellers_temp = $this->splitText($table[0], "/^([ ]*{$this->opt($this->namePrefixes)}\s*{$this->patterns['travellerName']})$/mu", true);

                    foreach ($travellers_temp as $tName) {
                        $tName = preg_replace('/\s+/', ' ', trim($tName));

                        if (preg_match("/^{$this->patterns['travellerName']}$/u", $tName)) {
                            $this->travellers[] = $tName;
                        }
                    }
                }

                if (preg_match("/(?:^|\n)[ ]*({$this->opt($this->t('AIR'))} [^\n]{6,}\n\n.+?)\n[ ]*{$this->opt($this->t('airEnd'))}/s", $textPdf, $m)) {
                    $this->parseFlight($email, $textPdf, $m[1]);
                }

                if (preg_match("/(?:^|\n)[ ]*({$this->opt($this->t('HOTEL'))} [^\n]{6,}\n\n.+\n[ ]*{$this->opt($this->t('hotelEnd'))}\n+[^\n]{4,})/s", $textPdf, $m)) {
                    $this->parseHotel($email, $textPdf, $m[1]);
                }

                if (preg_match("/\n[ ]*{$this->opt($this->t('GRAND TOTAL'))}[ ]{2,}(?<currency1>[A-Z]{3})[ ]*(?<currency2>[^\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*)(?:\n|$)/", $textPdf, $m)) {
                    $email->price()
                        ->currency($m['currency1'])
                        ->total($this->normalizeAmount($m['amount']));
                }
            }
        }

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        $email->setType('FlightItineraryPdf' . ucfirst($this->lang));

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

    private function parseHotel(Email $email, string $text, string $hotelText): void
    {
        $hotels = $this->splitText($hotelText, "/(?:^|\n)[ ]*({$this->opt($this->t('HOTEL'))} .{6,}(?:\n+.+){1,4}{$this->opt($this->t('Reference'))}[: ]+[-A-Z\d]{5,}\n)/", true);

        foreach ($hotels as $hText) {
            $h = $email->add()->hotel();

            if (preg_match("/HOTEL\s*(?<arrDate>[[:alpha:]]+\s*\d{1,2})\s*—\s*(?<depDate>[[:alpha:]]+\s*\d{1,2}),\s*(?<year>\d{4})\s*(?<hotelName>\D+)\s+(?<confirmationTitle>Reference)[ ]*:\s*(?<confirmation>[A-Z\d]+).+RATE DESCRIPTION\s+(?<rate>\D+)\n?.+CANCELATION POLICY(?<cancellation>.+?)(?:GUARANTEE REQUIREMENTS|OTHER INFORMATION).+ADDRESS\s*(?<address>.{3,}?)\s+PHONE\s*(?<phone>[+(\d][-. \d)(]{5,}[\d)])(?:\n|$)/su", $hText, $m)) {
                $h->general()
                    ->confirmation($m['confirmation'], $m['confirmationTitle'])
                    ->cancellation(preg_replace('/\s+/', ' ', $m['cancellation']));

                if (preg_match("/.+[ ]+{$m['confirmation']}[ ]+({$this->opt($this->t('statusVariants'))})$/m", $text, $matches)) {
                    $h->general()->status($matches[1]);
                }

                $h->hotel()
                    ->name($m['hotelName'])
                    ->address(preg_replace('/\s+/', ' ', $m['address']))
                    ->phone($m['phone']);

                $h->booked()
                    ->checkIn(strtotime($m['arrDate'] . ' ' . $m['year']))
                    ->checkOut(strtotime($m['depDate'] . ' ' . $m['year']));

                if (isset($m['rate']) && !empty($m['rate'])) {
                    $room = $h->addRoom();
                    $room->setRateType(str_replace("\n", " ", $m['rate']));
                }

                $priceText = preg_match("/\n[ ]*Hotel:.+{$m['confirmation']}.*\n+([\s\S]+?\n+[ ]*Total[ ]*\([ ]*for[ ]*\d{1,3}[ ]*Room\).+)/", $text, $m) ? $m[1] : null;

                if (preg_match("/\n[ ]*Total[ ]*\([ ]*for[ ]*(?<rooms>\d{1,3})[ ]*Room\)[ ]{2,}(?<currency1>[A-Z]{3})[ ]*(?<currency2>[^\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*)$/", $priceText, $m)) {
                    // USD $5,065.02
                    $h->booked()->rooms($m['rooms']);
                    $h->price()
                        ->currency($m['currency1'])
                        ->total($this->normalizeAmount($m['amount']));

                    if (preg_match('/\n[ ]*' . $this->opt($this->t('Subtotal')) . '[ ]{2,}(?:' . preg_quote($m['currency1'], '/') . ')?[ ]*(?:' . preg_quote($m['currency2'], '/') . ')?[ ]*(?<amount>\d[,.\'\d ]*)\n/', $priceText, $matches)) {
                        $h->price()->cost($this->normalizeAmount($matches['amount']));
                    }

                    $feeRows = preg_match("/\n[ ]*{$this->opt($this->t('Taxes and Fees'))}[ ]{2}([\s\S]+?)\n+[ ]*{$this->opt($this->t('Total per Room'))}/", $priceText, $matches)
                        ? explode("\n", $matches[1]) : [];

                    foreach ($feeRows as $feeRow) {
                        if (preg_match('/^[ ]*\([ ]*(?<name>[^\d)(]+?)[ ]*\)[ ]*(?:' . preg_quote($m['currency1'], '/') . ')?[ ]*(?:' . preg_quote($m['currency2'], '/') . ')?[ ]*(?<amount>\d[,.\'\d ]*)$/', $feeRow, $matches)) {
                            $h->price()->fee($matches['name'], $this->normalizeAmount($matches['amount']));
                        }
                    }
                }
            }

            if (count($this->travellers)) {
                $h->general()->travellers($this->travellers);
            }
        }
    }

    private function parseFlight(Email $email, string $text, string $textAir): void
    {
        $f = $email->add()->flight();

        $referenceAir = null;

        if (preg_match("/\n[ ]*{$this->opt($this->t('tripTypes'))}.{3,}?{$this->opt($this->t('to'))}.{3,}?[ ]{2,}(.+)\n/", $textAir, $matches)) {
            if (preg_match("/^({$this->opt($this->t('Reference'))})[: ]*([-A-Z\d]{5,})(?:[ ]{2}|$)/", $matches[1], $m)) {
                $referenceAir = $m[2];
                $f->general()->confirmation($m[2], $m[1]);
            }

            if (preg_match("/(?:^|[ ]{2})({$this->opt($this->t('statusVariants'))})$/i", $matches[1], $m)) {
                $f->general()->status($m[1]);
            }
        }

        // Sun Apr 4 2021 – Seattle (SEA) to Honolulu (HNL)
        $patterns['segHeader'] = ".{6,} [-–] .+\([A-Z]{3}\)[ ]+{$this->opt($this->t('to'))}[ ]+.+\([A-Z]{3}\)";

        $pTableText = preg_match("/^([ ]*{$this->opt($this->t('PASSENGER NAME'))}[ ]{2,}{$this->opt($this->t('TICKET NUMBER'))}[^\n]*\n+[\s\S]+?)\n+[ ]*{$patterns['segHeader']}/mu", $textAir, $m) ? $m[1] : null;
        $pTablePos = [0];

        if (preg_match("/^(([ ]*{$this->opt($this->t('PASSENGER NAME'))}[ ]{2,}){$this->opt($this->t('TICKET NUMBER'))}[ ]{2,}){$this->opt($this->t('CHECKED BAGGAGE'))}/m", $textAir, $m)) {
            unset($m[0]);

            foreach (array_reverse($m) as $textHeaders) {
                $pTablePos[] = mb_strlen($textHeaders);
            }
        }
        $pTable = $this->splitCols($pTableText, $pTablePos);

        if (count($pTable) > 2) {
            if (preg_match_all("/^[ ]*(\d{3}(?: | ?- ?)?\d{5,}(?: | ?- ?)?\d{1,3})$/m", $pTable[1], $ticketMatches)) {
                $f->issued()->tickets($ticketMatches[1], false);
            }
            preg_match_all("/[ ]{2}(?<airline>.+?) [-–] (?<pnr>[A-Z\d]{5,})$/mu", $pTable[2], $locatorMatches, PREG_SET_ORDER);

            foreach ($locatorMatches as $record) {
                if (!in_array($record['pnr'], $f->getConfirmationNumbers()[0])) {
                    $f->general()->confirmation($record['pnr'], $record['airline']);
                }
            }
        }

        $travellers = [];
        $usedAirlines = [];

        $textAir = str_replace("Travel Advisory: Terminal Info Unknown", "", $textAir);
        $segments = $this->splitText($textAir, "/^[ ]*({$patterns['segHeader']})/mu", true);

        foreach ($segments as $key => $sText) {
            if (!preg_match("/^(.+)\n+([\s\S]+)/", $sText, $m)) {
                $this->logger->debug("Wrong segment-{$key}!");

                continue;
            }
            $sHeader = $m[1];
            $sText = $m[2];

            $dateValue = preg_match("/^(.{3,}?\d{4}) [-–] /u", $sHeader, $m) ? $m[1] : null;
            $this->date = strtotime($dateValue);

            $tablePos = [0];

            if (preg_match("/^(((.+[ ]{2})[A-Z]{3}[ ]{2,})\s\d[\d HhMm]+[ ]{2,})[A-Z]{3}(?:[ ]{2}|\n)/m", $sText, $matches)) {
                $tablePos[] = mb_strlen($matches[3]);
                $tablePos[] = mb_strlen($matches[2]);
                $tablePos[] = mb_strlen($matches[1]);
            }

            if (preg_match("/^(.+?[ ]{2})(?:{$this->opt($this->t('Adult'))}|{$this->opt($this->t('Child'))})/m", $sText, $matches)) {
                $tablePos[] = mb_strlen($matches[1]);
            } elseif (preg_match_all("/^(.+[ ]{2}){$this->opt($this->namePrefixes)}/m", $sText, $td4Matches)) {
                $tablePos4 = 999;

                foreach ($td4Matches[1] as $m) {
                    $tablePos4_temp = mb_strlen($m);

                    if ($tablePos4_temp < $tablePos4 && $tablePos4_temp > $tablePos[count($tablePos) - 1]) {
                        $tablePos4 = $tablePos4_temp;
                    }
                }

                if ($tablePos4 !== 999) {
                    $tablePos[] = $tablePos4;

                    if (preg_match_all("/^(.+[ ]{2})\d+[A-Z]$/m", $sText, $td5Matches)) {
                        $tablePos5 = 999;

                        foreach ($td5Matches[1] as $m) {
                            $tablePos5_temp = mb_strlen($m);

                            if ($tablePos5_temp < $tablePos5 && $tablePos5_temp > $tablePos4) {
                                $tablePos5 = $tablePos5_temp;
                            }
                        }

                        if ($tablePos5 !== 999) {
                            $tablePos[] = $tablePos5;
                        }
                    }
                }
            } elseif (preg_match("/^((.+[ ]{2}[A-Z]{3}[ ]{2,}\d[\d HhMm]+[ ]{2,}[A-Z]{3}(?:[ ]{2,}))([[:alpha:]][-–.'’[:alpha:] ]*[[:alpha:]])?\s{2,}\S+\s{2})/m", $sText, $matches)) {
                $tablePos[] = mb_strlen($matches[2]);
                $tablePos[] = mb_strlen($matches[1]);
            }

            $partText = preg_split("/\d+h\s*\d+m\s*Layover\s*in\s*\S+\s*\n/", $sText);

            if (count($partText) >= 2) {
                foreach ($partText as $pText) {
                    $s = $f->addSegment();
                    $sText = $pText;
                    $table = $this->splitCols($sText, $tablePos);

                    if (count($table) < 4) {
                        $this->logger->debug("Wrong segment-{$key}!");

                        continue;
                    }

                    $this->ParseFlightSegment($s, $table, $this->date, $travellers, $usedAirlines);
                }
            } else {
                $s = $f->addSegment();
                $table = $this->splitCols($sText, $tablePos);

                if (count($table) < 4) {
                    $this->logger->debug("Wrong segment-{$key}!");

                    continue;
                }

                $this->ParseFlightSegment($s, $table, $this->date, $travellers, $usedAirlines);
            }
        }

        $textAirPayment = '';

        if (preg_match("/\n[ ]*({$this->opt($this->t('tripTypes'))}.{3,}?{$this->opt($this->t('to'))}.{3,}?)(?:[ ]{2}|\n)/", $textAir, $m)
            && preg_match("/\n[ ]*{$this->opt($this->t('Air'))}[ ]*:[ ]*{$this->opt($m[1])}[^\n]*\n+(.+?\n[ ]*{$this->opt($this->t('GRAND TOTAL'))} [^\n]+)/s", $text, $m2)
        ) {
            $textAirPayment = $m2[1];
        }

        if (preg_match("/\n[ ]*{$this->opt($this->t('Total'))}[ ]*x[ ]*\d{1,3}[ ]*{$this->opt($this->t('Traveler(s)'))}[ ]+(.+)/", $textAirPayment, $m)
            && count($totalPayments = preg_split('/[ ]{2,}/', $m[1])) === 3
        ) {
            if (preg_match("/^(?<currency1>[A-Z]{3})[ ]*(?<currency2>[^\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*)$/", $totalPayments[2], $m)) {
                // USD $5,065.02
                $f->price()
                    ->currency($m['currency1'])
                    ->total($this->normalizeAmount($m['amount']));

                if (preg_match('/^(?:' . preg_quote($m['currency1'], '/') . ')?[ ]*(?:' . preg_quote($m['currency2'], '/') . ')?[ ]*(?<amount>\d[,.\'\d ]*)$/', $totalPayments[0], $matches)) {
                    $f->price()->cost($this->normalizeAmount($matches['amount']));
                }

                if (preg_match('/^(?:' . preg_quote($m['currency1'], '/') . ')?[ ]*(?:' . preg_quote($m['currency2'], '/') . ')?[ ]*(?<amount>\d[,.\'\d ]*)$/', $totalPayments[1], $matches)) {
                    $f->price()->tax($this->normalizeAmount($matches['amount']));
                }
            }
        }

        if (count($travellers)) {
            $f->general()->travellers(array_unique($travellers));

            if (count($usedAirlines) && preg_match_all("/[ ]*(?i){$this->opt($travellers)}[ ]+{$this->opt($usedAirlines)}(?-i)[ ]*:[ ]*([-A-Z\d]{5,})$/m", $text, $ffNumberMatches)) {
                $f->program()->accounts($ffNumberMatches[1], false);
            }
        }

        if ($referenceAir === null) {
            $f->general()->noConfirmation();
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

            if ($this->strposArray($text, $phrases['Cabin:']) !== false
                && $this->strposArray($text, $phrases['Aircraft:']) !== false
                || $this->strposArray($text, $phrases['HOTEL OVERVIEW']) !== false
                && $this->strposArray($text, $phrases['HOW TO GET THERE']) !== false
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

    /**
     * Formatting over 3 steps:
     * 11 507.00  ->  11507.00
     * 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
     * 18800,00   ->  18800.00  |  2777,0    ->  2777.0.
     *
     * @param string|null $s Unformatted string with amount
     * @param string|null $decimals Symbols floating-point when non-standard decimals (example: 1,258.943)
     */
    private function normalizeAmount(?string $s, ?string $decimals = null): ?float
    {
        if (!empty($decimals) && preg_match_all('/(?:\d+|' . preg_quote($decimals, '/') . ')/', $s, $m)) {
            $s = implode('', $m[0]);

            if ($decimals !== '.') {
                $s = str_replace($decimals, '.', $s);
            }
        } else {
            $s = preg_replace('/\s+/', '', $s);
            $s = preg_replace('/[,.\'](\d{3})/', '$1', $s);
            $s = preg_replace('/,(\d{1,2})$/', '.$1', $s);
        }

        return is_numeric($s) ? (float) $s : null;
    }

    private function splitText(?string $textSource, string $pattern, bool $saveDelimiter = false): array
    {
        $result = [];

        if ($saveDelimiter) {
            $textFragments = preg_split($pattern, $textSource, -1, PREG_SPLIT_DELIM_CAPTURE);
            array_shift($textFragments);

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
            return preg_quote($s, '/');
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
