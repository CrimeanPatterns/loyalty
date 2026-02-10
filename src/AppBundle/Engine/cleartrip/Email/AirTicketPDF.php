<?php

namespace AwardWallet\Engine\cleartrip\Email;

class AirTicketPDF extends \TAccountChecker
{
    public $mailFiles = "cleartrip/it-1794452.eml, cleartrip/it-335356584.eml, cleartrip/it-4322714.eml, cleartrip/it-4370886.eml, cleartrip/it-5475180.eml, cleartrip/it-5475185.eml, cleartrip/it-6322282.eml, cleartrip/it-6395144.eml, cleartrip/it-8541350.eml";
    protected $lang = '';

    protected $langDetectors = [
        'en' => ['AIRLINE PNR', 'Airline PNR'],
    ];

    protected static $dict = [
        'en' => [],
    ];

    protected $tripNumber = '';
    protected $travellers = [];

    protected $patterns = [
        'code' => '[A-Z]{3}',
        'time' => '\d{1,2}:\d{2}(?:[ ]*[AaPp][Mm])?',
        'date' => '[^,.\d ]{2,}, \d{1,2} [^,.\d ]{3,} \d{4}',
    ];

    private $providerCode = '';

    // Standard Methods

    public function detectEmailFromProvider($from)
    {
        return strpos($from, 'Cleartrip Bookings') !== false
            || stripos($from, '@cleartrip.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (strpos($headers['from'], 'Cleartrip Bookings') === false && stripos($headers['from'], 'reply@cleartrip.com') === false) {
            return false;
        }

        return stripos($headers['subject'], 'Ticket for') !== false && stripos($headers['subject'], 'Trip ID') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (empty($textPdf)) {
                continue;
            }

            if ($this->assignProvider($textPdf, $parser->getHeaders()) !== true) {
                continue;
            }

            if ($this->assignLang($textPdf)) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        foreach ($parser->searchAttachmentByName('.*pdf') as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (empty($textPdf)) {
                continue;
            }

            if ($this->assignLang($textPdf) === false) {
                continue;
            }
            $this->assignProvider($textPdf, $parser->getHeaders());

            return $this->parsePdf($textPdf);
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    public static function getEmailProviders()
    {
        return ['cleartrip', 'amextravel'];
    }

    protected function t($phrase)
    {
        if (!isset(self::$dict[$this->lang]) || !isset(self::$dict[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dict[$this->lang][$phrase];
    }

    protected function parsePdf($textPdf)
    {
        $result['providerCode'] = $this->providerCode;
        $result['emailType'] = 'AirTicketPdf' . ucfirst($this->lang);

        $its = [];
        $currency = '';
        $amount = 0.0;

        $textPdfParts = [];
        $textPdfPartsAll = preg_split('/(Total fare:[ ]+\D[^\d]* \d[,\d]*)$/mi', $textPdf, null, PREG_SPLIT_DELIM_CAPTURE);
        array_pop($textPdfPartsAll);

        for ($i = 0; $i < count($textPdfPartsAll) - 1; $i += 2) {
            $textPdfParts[] = $textPdfPartsAll[$i] . $textPdfPartsAll[$i + 1];
        }

        $segCount = 0;

        foreach ($textPdfParts as $textPdfPart) {
            $pdfPartData = $this->parsePdfPart($textPdfPart);

            if ($pdfPartData === false) {
                continue;
            }

            foreach ($pdfPartData['Itineraries'] as $itFlight) {
                if (($key = $this->recordLocatorInArray($itFlight['RecordLocator'], $its)) !== false) {
                    $its[$key]['Passengers'] = array_merge($its[$key]['Passengers'], $itFlight['Passengers']);
                    $its[$key]['Passengers'] = array_unique($its[$key]['Passengers']);

                    if (!empty($itFlight['TicketNumbers'][0])) {
                        if (!empty($its[$key]['TicketNumbers'][0])) {
                            $its[$key]['TicketNumbers'] = array_merge($its[$key]['TicketNumbers'], $itFlight['TicketNumbers']);
                            $its[$key]['TicketNumbers'] = array_unique($its[$key]['TicketNumbers']);
                        } else {
                            $its[$key]['TicketNumbers'] = $itFlight['TicketNumbers'];
                        }
                    }
                    $its[$key]['TripSegments'] = array_merge($its[$key]['TripSegments'], $itFlight['TripSegments']);
                } else {
                    $its[] = $itFlight;
                }
            }

            if (!empty($pdfPartData['TotalCharge']['Currency'])) {
                $its[$segCount]['Currency'] = $pdfPartData['TotalCharge']['Currency'];
                $its[$segCount]['TotalCharge'] = $pdfPartData['TotalCharge']['Amount'];
            }
            $its[$segCount]['BaseFare'] = $pdfPartData['TotalCharge']['BaseFare'];

            if (isset($pdfPartData['TotalCharge']['Fees']) && count($pdfPartData['TotalCharge']['Fees']) > 0) {
                $its[$segCount]['Fees'] = $pdfPartData['TotalCharge']['Fees'];
            }

            if (isset($pdfPartData['TotalCharge']['Discount'])) {
                $its[$segCount]['Discount'] = $pdfPartData['TotalCharge']['Discount'];
            }

            $segCount++;

            /*if ( !empty($pdfPartData['TotalCharge']['Currency']) ) {
                if ( empty($currency) || $currency === $pdfPartData['TotalCharge']['Currency'] ) {
                    $currency = $pdfPartData['TotalCharge']['Currency'];
                    $amount += (float)$pdfPartData['TotalCharge']['Amount'];

                }
            }*/
        }

        /*if ( !empty($currency) ) {
            $result['parsedData']['TotalCharge']['Currency'] = $currency;
            $result['parsedData']['TotalCharge']['Amount'] = $amount;
            if (count($its)===1){
                $its[0]['Currency'] = $currency;
                $its[0]['TotalCharge'] = $amount;
            }
        }*/

        $result['parsedData']['Itineraries'] = $its;

        return $result;
    }

    protected function parsePdfPart($textPdfPart)
    {
        $result = [
            'Itineraries' => [],
            'TotalCharge' => [],
        ];

        $its = [];

        $text = implode("\n", $this->splitText($textPdfPart, '/^(.*Trip ID[ ]*:[ ]*[A-Z\d]{5,}.*)$/m', true));

        // TripNumber
        if (preg_match('/Trip ID[ ]*:[ ]*([A-Z\d]{5,})/', $text, $matches)) {
            $this->tripNumber = $matches[1];
        }

        // Passengers
        // RecordLocator
        // TicketNumbers
        $textTravellers = $this->sliceText($text, 'TRAVELLERS');

        if (!$textTravellers) {
            $textTravellers = $this->sliceText($text, 'Travellers');
        }

        if (!$textTravellers) {
            return false;
        }

        $prefixes = ['Mstr', 'Mrs', 'Ms', 'Mr', 'Miss'];
        preg_match_all('/^[ ]{0,15}((?:' . implode('|', $prefixes) . ')\.? .+\s+ [A-Z\d]{5,}.*)$/m', $textTravellers, $travellerMatches);

        $this->travellers = [];

        foreach ($travellerMatches[1] as $travellerRow) {
            $travellerParts = preg_split('/\s{2,}/', $travellerRow);

            if (isset($travellerParts[1]) && preg_match('/^[,A-Z\d ]{5,}$/', $travellerParts[1])) {
                $travellerPNRs = explode(',', $travellerParts[1]);
                $travellerPNRs = array_map(function ($s) { return trim($s, ', '); }, $travellerPNRs);
                $travellerPNRs = array_values(array_filter($travellerPNRs));
                $this->travellers[$travellerParts[0]]['PNRs'] = $travellerPNRs;
            }
            $ticketNO = $travellerParts[count($travellerParts) - 1];

            if (preg_match('/^\d+[- ]*\d{4,}$/', $ticketNO)) {
                $this->travellers[$travellerParts[0]]['TicketNumbers'] = [$ticketNO];
            }

//            if (preg_match('/^[\d\D]{6}$/', $ticketNO)) {
//                $this->travellers[$travellerParts[0]]['TicketNumbers'] = [$ticketNO];
//            }

            if (preg_match('/^[\d\D]+,\s+[\d\-\s?]+$/', $ticketNO)) {
                $this->travellers[$travellerParts[0]]['TicketNumbers'] = [$this->re("/^[\d\D]+,\s+([\d\-\s?]+)$/", $ticketNO)];
            }
        }

        if (count($this->travellers) === 0) {
            return false;
        }

        // TripSegments
        $textSegments = $this->sliceText($text, 0, 'TRAVELLERS');

        if (!$textSegments) {
            $textSegments = $this->sliceText($text, 0, 'Travellers');
        }

        if (!$textSegments) {
            return false;
        }

        // example: it-5475185.eml
        $travelSegments = [];
        $flights = $this->splitText($textSegments, '/^[ ]*\w[\w ]+\w to \w[\w ]+\w[ ]{2,}[^,.\d ]{2,}, \d{1,2} [^,.\d ]{3,} \d{4}/mu');

        foreach ($flights as $flight) {
            $travelSegments = array_merge($travelSegments, preg_split('/^[ ]*Layover[ ]*:[ ]*[\d hmin]{2,}$/mi', $flight));
        }

        foreach ($travelSegments as $i => $travelSegment) {
            $itFlights = $this->parseFlight($travelSegment, $i);

            foreach ($itFlights as $itFlight) {
                if (($key = $this->recordLocatorInArray($itFlight['RecordLocator'], $its)) !== false) {
                    $its[$key]['Passengers'] = array_merge($its[$key]['Passengers'], $itFlight['Passengers']);
                    $its[$key]['Passengers'] = array_unique($its[$key]['Passengers']);

                    if (!empty($itFlight['TicketNumbers'][0])) {
                        if (!empty($its[$key]['TicketNumbers'][0])) {
                            $its[$key]['TicketNumbers'] = array_merge($its[$key]['TicketNumbers'], $itFlight['TicketNumbers']);
                            $its[$key]['TicketNumbers'] = array_unique($its[$key]['TicketNumbers']);
                        } else {
                            $its[$key]['TicketNumbers'] = $itFlight['TicketNumbers'];
                        }
                    }
                    $its[$key]['TripSegments'] = array_merge($its[$key]['TripSegments'], $itFlight['TripSegments']);
                } else {
                    $its[] = $itFlight;
                }
            }
        }

        if (empty($its[0]['RecordLocator'])) {
            return false;
        }

        foreach ($its as $key => $it) {
            $its[$key] = $this->uniqueTripSegments($it);
        }

        $result['Itineraries'] = $its;

        // Currency
        // Amount
        $textFare = $this->sliceText($text, 'FARE BREAK UP');

        if (!$textFare) {
            $textFare = $this->sliceText($text, 'Fare break up');
        }

        if (preg_match('/Total fare:[ ]+(\D[^\d]*) (\d[,\d]*)$/mi', $textFare, $matches)) {
            $result['TotalCharge']['Currency'] = str_replace('Rs.', 'INR', trim($matches[1]));
            $result['TotalCharge']['Amount'] = $this->normalizePrice($matches[2]);
        }

        if (preg_match('/Base fare:[ ]+(\D[^\d]*) (\d[,\d]*)$/mi', $textFare, $matches)) {
            $result['TotalCharge']['BaseFare'] = $this->normalizePrice($matches[2]);
        }

        if (preg_match('/Discounts & Cashbacks:[ ]+(\S+)\s+([\d\-\,\.]+)/mi', $textFare, $matches)) {
            $result['Discount'] = $this->normalizePrice($matches[2]);
        }
        $feesText = $this->sliceText($text, 'Base fare:', 'Total fare:');

        if (!empty($this->re("/Base fare:.+\n(\s+)/", $feesText)) && preg_match_all("/\s{3,}([\w\s\.?\&?\)\(]+\:\s+\S+\s+[\d\.\,]+)/", $feesText, $m)) {
            $i = 0;
            $fees = [];

            foreach ($m[1] as $match) {
                $fees[$i]['Name'] = $this->re("/([A-Za-z]+[\w\s\(\)]{2,})\:/", $match);
                $fees[$i]['Charge'] = $this->normalizePrice($this->re("/\:\s+\S+\s+([\d\,\.]+)/", $match));

                $i++;
            }

            $result['TotalCharge']['Fees'] = $fees;
        }

        if (empty($this->re("/Base fare:.+\n(\s+)/", $feesText)) && preg_match_all("/(\D+:\s+\S+\s[\d\,\.]+)/", $feesText, $m)) {
            $i = 0;
            $fees = [];

            foreach ($m[1] as $match) {
                if ($i == 0) {
                    $i++;

                    continue;
                }
                $fees[$i]['Name'] = $this->re("/([A-Za-z]+[\w\s]{2,})\:/", $match);
                $fees[$i]['Charge'] = $this->normalizePrice($this->re("/\:\s+\S+\s+([\d\,\.]+)/", $match));

                $i++;
            }

            $result['TotalCharge']['Fees'] = $fees;
        }

        if (preg_match('/Base fare:[ ]+(\D[^\d]*) (\d[,\d]*)$/mi', $textFare, $matches)) {
            $result['TotalCharge']['BaseFare'] = $this->normalizePrice($matches[2]);
        }

        return $result;
    }

    protected function parseFlight($text, $i)
    {
        $its = [];

        $it = [];
        $it['Kind'] = 'T';
        $it['TripNumber'] = $this->tripNumber;

        $it['TripSegments'] = [];
        /** @var \AwardWallet\ItineraryArrays\AirTripSegment $seg */
        $seg = [];
        $timeDep = null;
        $timeArr = null;
        $dateDep = null;
        $dateArr = null;

        // AirlineName
        // FlightNumber
        if (preg_match('/^\s*([A-Z][A-Z\d]|[A-Z\d][A-Z]) ?\W ?(\d+)(?:$|[ ]{2,})/m', $text, $matches)) {
            $seg['AirlineName'] = $matches[1];
            $seg['FlightNumber'] = $matches[2];
        } elseif (preg_match('/^.+ — ([A-Z][A-Z\d]|[A-Z\d][A-Z]) ?\W ?(\d+)$/m', $text, $matches)) {
            $seg['AirlineName'] = $matches[1];
            $seg['FlightNumber'] = $matches[2];
        }

        // DepCode
        // ArrCode
        if (preg_match('/(' . $this->patterns['code'] . ') (' . $this->patterns['time'] . ')[ ]{2,}(' . $this->patterns['time'] . ') (' . $this->patterns['code'] . ')/m', $text, $matches)) {
            $seg['DepCode'] = $matches[1];
            $timeDep = $matches[2];
            $timeArr = $matches[3];
            $seg['ArrCode'] = $matches[4];
        }

        $startArrCol = strpos(trim($text), $timeArr);
        $arrCells = [];
        $depCells = [];
        $lines = explode("\n", $text);

        foreach ($lines as $line) {
            $arrCells[] = substr($line, $startArrCol);
            $depCells[] = substr($line, 0, $startArrCol - 1);
        }
        $arrText = implode("\n", array_filter($arrCells));
        $depText = implode("\n", array_filter($depCells));
        // Duration
        if (preg_match('/(' . $this->patterns['date'] . ')[ ]{2,}([\d hmin]{2,})?(' . $this->patterns['date'] . ')/m', $text, $matches)) {
            $dateDep = $matches[1];

            if (!empty($matches[2])) {
                $seg['Duration'] = trim($matches[2]);
            }
            $dateArr = $matches[3];
        }

        if (preg_match('/(?:Terminal ([A-Z\d]{1,3})\s+(?:Airport)?\s*Terminal ([A-Z\d]{1,3}))/', $text, $m)) {
            $seg['DepartureTerminal'] = $m[1];
            $seg['ArrivalTerminal'] = $m[2];
        }

        if (empty($seg['DepartureTerminal']) && preg_match('/Terminal\s+(?:Economy)?\s*\b([A-Z\d]{1,3})\b/', $depText, $m)) {
            $seg['DepartureTerminal'] = $m[1];
        }

        if (empty($seg['ArrivalTerminal']) && preg_match('/Terminal\s+\b([A-Z\d]{1,3})\b/', $arrText, $m)) {
            $seg['ArrivalTerminal'] = $m[1];
        }

        if (stripos($text, 'Economy') !== false) {
            $seg['Cabin'] = 'Economy';
        }

        // DepDate
        if ($timeDep && $dateDep) {
            $seg['DepDate'] = strtotime($dateDep . ', ' . $timeDep);
        }

        // ArrDate
        if ($timeArr && $dateArr) {
            $seg['ArrDate'] = strtotime($dateArr . ', ' . $timeArr);
        }

        $it['TripSegments'][] = $seg;

        foreach ($this->travellers as $traveller => $travellerData) {
            $it['Passengers'] = [$traveller];

            if (empty($travellerData['PNRs'][$i])) {
                $it['RecordLocator'] = $travellerData['PNRs'][0];
            } else {
                $it['RecordLocator'] = $travellerData['PNRs'][$i];
            }

            if (!empty($travellerData['TicketNumbers'][0])) {
                $it['TicketNumbers'] = $travellerData['TicketNumbers'];
            }
            $its[] = $it;
        }

        return $its;
    }

    protected function recordLocatorInArray($recordLocator, $array)
    {
        foreach ($array as $key => $value) {
            if ($value['Kind'] === 'T') {
                if ($value['RecordLocator'] === $recordLocator) {
                    return $key;
                }
            }
        }

        return false;
    }

    protected function sliceText($textSource = '', $textStart, $textEnd = '')
    {
        if (empty($textSource)) {
            return false;
        }

        if (is_numeric($textStart)) {
            $start = $textStart;
        } else {
            $start = strpos($textSource, $textStart);
        }

        if (empty($textEnd)) {
            return substr($textSource, $start);
        }
        $end = strpos($textSource, $textEnd, $start);

        if ($start === false || $end === false) {
            return false;
        }

        return substr($textSource, $start, $end - $start);
    }

    protected function splitText($textSource = '', $pattern = '', $saveDelimiter = false)
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

    protected function uniqueTripSegments($it)
    {
        if ($it['Kind'] !== 'T') {
            return $it;
        }
        $uniqueSegments = [];

        foreach ($it['TripSegments'] as $segment) {
            foreach ($uniqueSegments as $key => $uniqueSegment) {
                if ($segment['FlightNumber'] === $uniqueSegment['FlightNumber'] && $segment['DepDate'] === $uniqueSegment['DepDate']) {
                    if (!empty($segment['Seats'][0])) {
                        if (!empty($uniqueSegments[$key]['Seats'][0])) {
                            $uniqueSegments[$key]['Seats'] = array_merge($uniqueSegments[$key]['Seats'], $segment['Seats']);
                            $uniqueSegments[$key]['Seats'] = array_unique($uniqueSegments[$key]['Seats']);
                        } else {
                            $uniqueSegments[$key]['Seats'] = $segment['Seats'];
                        }
                    }

                    continue 2;
                }
            }
            $uniqueSegments[] = $segment;
        }
        $it['TripSegments'] = $uniqueSegments;

        return $it;
    }

    protected function normalizePrice($string = '')
    {
        if (empty($string)) {
            return $string;
        }
        $string = preg_replace('/\s+/', '', $string);			// 11 507.00	->	11507.00
        $string = preg_replace('/[,.](\d{3})/', '$1', $string);	// 2,790		->	2790		or	4.100,00	->	4100,00
        $string = preg_replace('/,(\d{2})$/', '.$1', $string);	// 18800,00		->	18800.00

        return $string;
    }

    protected function assignLang($text)
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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function cutText(string $start, $end, string $text)
    {
        if (empty($start) || empty($end) || empty($text)) {
            return false;
        }

        if (is_array($end)) {
            $begin = stristr($text, $start);

            foreach ($end as $e) {
                if (stristr($begin, $e, true) !== false) {
                    return stristr($begin, $e, true);
                }
            }
        }

        return stristr(stristr($text, $start), $end, true);
    }

    private function assignProvider($textPdf, $headers): bool
    {
        if (self::detectEmailFromProvider($headers['from']) === true
            || stripos($textPdf, 'cleartrip.com/support') !== false || stripos($textPdf, 'with Cleartrip about') !== false || stripos($textPdf, 'Cleartrip website') !== false || stripos($textPdf, 'Cleartrip Pvt Ltd') !== false
        ) {
            $this->providerCode = 'cleartrip';

            return true;
        }

        if (stripos($headers['from'], '@amexindiatravel.com') !== false
            || stripos($textPdf, 'your tickets with Amex Customer Care') !== false
        ) {
            $this->providerCode = 'amextravel';

            return true;
        }

        return false;
    }
}
