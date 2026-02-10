<?php

namespace AwardWallet\Engine\ana\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;

class TicketAndConfirmationPdf extends \TAccountChecker
{
    public $mailFiles = "ana/it-13218635.eml";

    private $langDetectors = [
        'en' => ['Departure/Arrival'],
    ];
    private $lang = '';
    private static $dict = [
        'en' => [],
    ];

    private $textPdf = '';
    private $textPdf2 = '';

    // Standard Methods

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['subject'], 'ANA Ticket and Confirmation') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $detectProvider = false;
        $detectLanguage = false;

        if ($this->http->XPath->query('//node()[contains(normalize-space(.),"Please see attached your ANA reservation")]')->length > 0) {
            $detectProvider = true;
        }

        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $this->textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (!$detectProvider) {
                $detectProvider = $this->assignProviderPdf($this->textPdf);
            }

            $detectLanguage = $this->assignLangPdf($this->textPdf);

            if ($detectProvider && $detectLanguage) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        if (self::detectEmailByBody($parser) !== true) {
            return null;
        }

        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (preg_match('/^[ ]*INVOICE$/m', $textPdf)) {
                $this->textPdf2 = $textPdf;
            }
        }

        $it = $this->parsePdf();

        return [
            'parsedData' => [
                'Itineraries' => [$it],
            ],
            'emailType' => 'TicketAndConfirmationPdf' . ucfirst($this->lang),
        ];
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parsePdf()
    {
        $dateDocument = 0;

        if (preg_match('/^\s*(\d{1,2}\/\d{1,2}\/\d{2,4})\b/', $this->textPdf, $matches)) { // 6/23/2015
            $dateDocument = strtotime($matches[1]);
        }

        $it = [];
        $it['Kind'] = 'T';

        // Passengers
        $passenger = $this->http->FindSingleNode('//text()[starts-with(normalize-space(.),"Hi ")]', null, true, '/^Hi\s+([A-z][-.\'A-z ]*[.A-z])\s*,\s*$/'); // Hi Lengxin,

        if ($passenger) {
            $it['Passengers'] = [$passenger];
        }

        if (!preg_match('/^\s*Reservation Details$(.+?)^\s*Itinerary$/ms', $this->textPdf, $matches)) {
            $this->http->Log('Reservation Details not found!');

            return null;
        }
        $reservationDetailsText = $matches[1];

        // RecordLocator
        if (preg_match('/^([ ]*Reservation Number)/m', $reservationDetailsText, $matches)) {
            $reservationDetailsTable = $this->splitCols($reservationDetailsText, [0, mb_strlen($matches[1])]);

            if (preg_match('/Reservation Number\s+([A-Z\d]{5,})/', $reservationDetailsTable[0], $m)) {
                $it['RecordLocator'] = $m[1];
            }
        }

        // TripSegments
        $it['TripSegments'] = [];

        if (!preg_match('/^\s*Itinerary$(.+?)^\s*Baggage policy$/ms', $this->textPdf, $matches)) {
            $this->http->Log('Itinerary Segments not found!');

            return null;
        }
        $itineraryText = $matches[1];

        $tableHeadersPos = [0];

        if (!preg_match('/^((((([ ]+)' . implode('[ ]{2,})', ['Departure\/Arrival', 'Flight', 'Seat', 'Class', 'Status']) . '/m', $itineraryText, $matches)) {
            $this->http->Log('Itinerary Table Headers not found!');

            return null;
        }
        unset($matches[0]);
        asort($matches);

        foreach ($matches as $textHeaders) {
            $tableHeadersPos[] = mb_strlen($textHeaders);
        }

        // Jul 3                   17:05         Tokyo(Narita)                      NH006
        $pattern = '/^(.*\d{1,2}:\d{2}[ ]{2,}\w.*[ ]{2,}(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\d+)(?:[ ]{2,}|$)/m';
        $segments = $this->splitText($itineraryText, $pattern, true);
        $date = 0;

        foreach ($segments as $segment) {
            $segmentTable = $this->splitCols($segment, $tableHeadersPos);

            if (count($segmentTable) < 5) {
                continue;
            }

            $seg = [];

            if (preg_match('/^[ ]*([^\d\W]{3,}[ ]+\d{1,2})/u', $segmentTable[0], $matches) && $dateDocument) {
                $date = EmailDateHelper::parseDateRelative($matches[1], $dateDocument);
            }

            // DepDate
            // ArrDate
            // DepName
            // ArrName
            // DepCode
            // ArrCode
            if (
                preg_match_all('/^[ ]*(?<time>\d{1,2}:\d{2}(?:[ ]*[AaPp][Mm])?)[ ]{2,}(?<airport>\w.+?)[ ]*$/mu', $segmentTable[1], $timeMatches, PREG_SET_ORDER)
                && count($timeMatches) === 2
            ) {
                $seg['DepDate'] = strtotime($timeMatches[0]['time'], $date);
                $seg['ArrDate'] = strtotime($timeMatches[1]['time'], $date);
                $seg['DepName'] = $timeMatches[0]['airport'];
                $seg['ArrName'] = $timeMatches[1]['airport'];
                $seg['ArrCode'] = $seg['DepCode'] = TRIP_CODE_UNKNOWN;
            }

            // AirlineName
            // FlightNumber
            if (preg_match('/^\s*(?<airline>[A-Z][A-Z\d]|[A-Z\d][A-Z])?\s*(?<flightNumber>\d+)\b/', $segmentTable[2], $matches)) {
                if (!empty($matches['airline'])) {
                    $seg['AirlineName'] = $matches['airline'];
                }
                $seg['FlightNumber'] = $matches['flightNumber'];
            }

            // Seats
            if (preg_match('/^\s*(\d{1,2}[A-Z])[ ]*(?:\n|$)/', $segmentTable[3], $matches)) {
                $seg['Seats'] = [$matches[1]];
            }

            // Cabin
            if (preg_match('/^\s*(\w[\w\s]+?)[ ]*(?:\n|$)/u', $segmentTable[4], $matches)) {
                $seg['Cabin'] = $matches[1];
            }

            $it['TripSegments'][] = $seg;
        }

        // Currency
        // TotalCharge
        if (preg_match('/^[ ]*Total[: ]+(\D+)(\d[,.\d]*)$/m', $this->textPdf2, $matches)) { // Total $1,795.00
            $it['Currency'] = $this->normalizeCurrency($matches[1]);
            $it['TotalCharge'] = $this->normalizePrice($matches[2]);
        }

        return $it;
    }

    private function normalizeCurrency($string = '')
    {
        if (empty($string)) {
            return $string;
        }
        $currences = [
            'GBP' => ['£'],
            'EUR' => ['€'],
            'USD' => ['$', 'US$'],
        ];
        $string = trim($string);

        foreach ($currences as $currencyCode => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $currencyCode;
                }
            }
        }

        return $string;
    }

    private function normalizePrice($string = '')
    {
        if (empty($string)) {
            return $string;
        }
        $string = preg_replace('/\s+/', '', $string);           // 11 507.00    ->    11507.00
        $string = preg_replace('/[,.](\d{3})/', '$1', $string); // 2,790        ->    2790    |    4.100,00    ->    4100,00
        $string = preg_replace('/,(\d{2})$/', '.$1', $string);  // 18800,00     ->    18800.00

        return $string;
    }

    private function rowColsPos($row): array
    {
        $head = array_filter(array_map('trim', explode("|", preg_replace("#\s{2,}#", "|", $row))));
        $pos = [];
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }

    private function colsPos($table, $correct = 5): array
    {
        $pos = [];
        $rows = explode("\n", $table);

        foreach ($rows as $row) {
            $pos = array_merge($pos, $this->rowColsPos($row));
        }
        $pos = array_unique($pos);
        sort($pos);
        $pos = array_merge([], $pos);

        foreach ($pos as $i => $p) {
            for ($j = $i - 1; $j >= 0; $j = $j - 1) {
                if (isset($pos[$j])) {
                    if (isset($pos[$i])) {
                        if ($pos[$i] - $pos[$j] < $correct) {
                            unset($pos[$i]);
                        }
                    }

                    break;
                }
            }
        }
        sort($pos);
        $pos = array_merge([], $pos);

        return $pos;
    }

    private function splitCols($text, $pos = false): array
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->rowColsPos($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k => $p) {
                $cols[$k][] = trim(mb_substr($row, $p, null, 'UTF-8'));
                $row = mb_substr($row, 0, $p, 'UTF-8');
            }
        }
        ksort($cols);

        foreach ($cols as &$col) {
            $col = implode("\n", $col);
        }

        return $cols;
    }

    private function splitText($textSource = '', $pattern = '', $saveDelimiter = false)
    {
        $result = [];

        if ($saveDelimiter) {
            $textFragments = preg_split($pattern, $textSource, null, PREG_SPLIT_DELIM_CAPTURE);
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

    private function t($phrase)
    {
        if (!isset(self::$dict[$this->lang]) || !isset(self::$dict[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dict[$this->lang][$phrase];
    }

    private function assignProviderPdf($text)
    {
        if (
            stripos($text, '.ana.co.jp') !== false
            || strpos($text, ' ANA My Choice') !== false
            || strpos($text, '[ANA Privacy Policy]') !== false
        ) {
            return true;
        }

        return false;
    }

    private function assignLangPdf($text): bool
    {
        foreach ($this->langDetectors as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if (strpos($text, $phrase) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }
}
