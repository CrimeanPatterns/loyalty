<?php

namespace AwardWallet\Engine\eurobonus\Email;

class CustomerReceiptPdf2016En extends \TAccountChecker
{
    public $mailFiles = "eurobonus/it-10.eml, eurobonus/it-11.eml, eurobonus/it-11074126.eml, eurobonus/it-12233039.eml, eurobonus/it-13.eml, eurobonus/it-14.eml, eurobonus/it-1649721.eml, eurobonus/it-1665895.eml, eurobonus/it-2.eml, eurobonus/it-3.eml, eurobonus/it-4.eml, eurobonus/it-4244215.eml, eurobonus/it-4849767.eml, eurobonus/it-5.eml, eurobonus/it-5674404.eml, eurobonus/it-6.eml, eurobonus/it-7.eml, eurobonus/it-8.eml, eurobonus/it-9.eml";

    public $reBody = 'SAS';
    public $reBody2 = [
        'en' => ['Electronic Ticket Itinerary and Receipt', 'Electronic Ticket Itinerary/Receipt'],
    ];

    protected $result = [];

    public function increaseDate($dateSegment, $depTime, $arrTime)
    {
        $depDate = strtotime($depTime, strtotime($dateSegment, $this->result['ReservationDate']));

        if ($this->result['ReservationDate'] > $depDate) {
            $depDate = strtotime('+1 year', $depDate);
        }
        $arrDate = strtotime($arrTime, $this->result['ReservationDate']);

        while ($depDate > $arrDate) {
            $arrDate = strtotime('+1 day', $arrDate);
        }

        return [
            'DepDate' => $depDate,
            'ArrDate' => $arrDate,
        ];
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $pdf = $parser->searchAttachmentByName($this->getPdfName());

        if (empty($pdf)) {
            $this->logger->info('PDF empty');

            return false;
        }

        $pdfBody = $parser->getAttachmentBody(array_shift($pdf));
        $pdf = str_replace(' ', ' ', \PDF::convertToText($pdfBody));

        return [
            'parsedData' => ['Itineraries' => $this->parseEmail($pdf)],
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && (
                stripos($headers['from'], 'noreply@sas.') !== false
                || stripos($headers['from'], 'no-reply@flysas.com') !== false
                )
                && isset($headers['subject']) && (
                stripos($headers['subject'], 'Customer Receipt Copy') !== false
                || stripos($headers['subject'], 'Electronic Ticket Itinerary and Receipt from SAS - Booking reference') !== false
                );
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        if (!isset($pdfs[0])) {
            return false;
        }
        $pdf = $pdfs[0];

        if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
            return false;
        }

        if (strpos($text, $this->reBody) === false) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if (is_array($re) && (stripos($text, $re[0]) !== false || stripos($text, $re[1]) !== false)) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@sas.se') !== false
            || stripos($from, '@flysas.com') !== false;
    }

    protected function parseEmail($pdfText)
    {
        $this->result['Kind'] = 'T';
        $this->parsePassengers($pdfText);
        $this->parsePayment($pdfText);
        $this->parseSegments(join($this->findСutSectionAll($pdfText, '  Allowance', ['Ticket Number:', 'Fare'])));

        return [$this->result];
    }

    protected function parsePassengers($text)
    {
//         $this->logger->debug('$text = '.print_r( $text,true));
        if (preg_match('/Booking Reference:\s*([A-Z\d]{5,6})/', $text, $matches)) {
            $this->result['RecordLocator'] = $matches[1];
        } elseif (preg_match('/Booking Reference:(?: {30,}.*)?\n {0,20}Frequent {2,30}([A-Z\d]{5,7})(?: {30,}.*)?\n +Flyer Number: {0,10}([\dA-Z]+?)(?:\s{2,}|\n)/', $text, $matches)) {
            //    Booking Reference:
            //    Frequent               QPBRDR
            //             Flyer Number: SKEBB713880268                                                          IATA Number: 81494792

            $this->result['RecordLocator'] = $matches[1];
            $this->result['AccountNumbers'][] = $matches[2];
        }

        if (preg_match_all('/(?:Mr|Mrs|Ms) (\S.*?)\s{2,}/', $text, $matches)) {
            $this->result['Passengers'] = $matches[1];
        }

        if (preg_match_all('/\n\s*(\S.*?) (?:Mr|Mrs|Ms)\s{2,}/', $text, $matches)) {
            $this->result['Passengers'] = (isset($this->result['Passengers'])) ? array_merge($this->result['Passengers'], $matches[1]) : $matches[1];
        }

        if (preg_match_all('/Ticket Number:\s*(.+?)\s{2,}/', $text, $matches)) {
            $this->result['TicketNumbers'] = array_map(function ($value) { return str_replace(' ', '', $value); }, $matches[1]);
        }

        if (preg_match_all('/Frequent Flyer Number:\s*([\dA-Z]+?)\s{2,}/', $text, $matches)) {
            $this->result['AccountNumbers'] = array_merge($this->result['AccountNumbers'] ?? [], $matches[1]);
        }

        if (preg_match('/Date of Issue:\s*(\d+\w+\d+)/', $text, $matches)) {
            $this->result['ReservationDate'] = strtotime($matches[1]);
        }
    }

    protected function parsePayment($pdfText)
    {
        if (preg_match_all('/(?:Taxes, Fees, Other Charges|Additional Taxes)\s*([\d\.,]+)\s+([A-Z]{3})/', $pdfText, $matches)) {
            $this->result['Tax'] = 0;

            foreach ($matches[0] as $key => $value) {
                $this->result['Tax'] += cost($matches[1][$key]);
                $this->result['Currency'] = currency($matches[2][$key]);
            }
        }

        if (preg_match_all('/International Surcharge\s*([\d\.,]+)\s+([A-Z]{3})/', $pdfText, $matches)) {
            $this->result['Fees'][0] = ["Name" => "International Surcharge", "Charge" => 0];

            foreach ($matches[0] as $key => $value) {
                $this->result['Fees'][0]["Charge"] += cost($matches[1][$key]);
            }
        }

        if (preg_match_all('/(?:Total Amount|Additional Charges)\s*:?\s*([\d\.,]+)/', $pdfText, $matches)) {
            $this->result['TotalCharge'] = 0;

            foreach ($matches[0] as $key => $value) {
                $this->result['TotalCharge'] += cost($matches[1][$key]);
            }
        }

        if (preg_match_all('/Fare\s*([\d\.,]+)/', $pdfText, $matches)) {
            $this->result['BaseFare'] = 0;

            foreach ($matches[0] as $key => $value) {
                $this->result['BaseFare'] += cost($matches[1][$key]);
            }
        }
    }

    protected function parseSegments($pdfText)
    {
        foreach (preg_split('/\n{2,}/', trim($pdfText), -1, PREG_SPLIT_NO_EMPTY) as $value) {
            $seg = $this->segment($value);

            if (empty($this->result['TripSegments'])) {
                $this->result['TripSegments'][] = $seg;

                continue;
            }
            $finded = false;

            foreach ($this->result['TripSegments'] as $key => $tripSegments) {
                if (isset($seg['AirlineName']) && $seg['AirlineName'] == $tripSegments['AirlineName']
                        && isset($seg['FlightNumber']) && $seg['FlightNumber'] == $tripSegments['FlightNumber']
                        && isset($seg['DepDate']) && $seg['DepDate'] == $tripSegments['DepDate']) {
                    $finded = true;

                    continue 2;
                }
            }

            if ($finded == false) {
                $this->result['TripSegments'][] = $seg;
            }
        }
    }

    protected function segment($text)
    {
        $segment = [];

        // SK 248 / 17DEC Bergen - Oslo Gardermoen  08:25  09:20  07:55  1PC  X / Confirmed  Food And Beverages For Purchase 00:55
        // SK907 / 18JUL16    Oslo - New York City NY    11:10    13:15    1 PC
        $regular = '(?<al>[A-Z]{2})?\s*(?<fl>\d+)\s*/\s*(?<date>\d+\w+\d?)\s+';
        $regular .= '(?<dname>.+?)\s*-\s*(?<aname>.+?)\s+(?<dtime>\d+:\d+)\s+(?<atime>\d+:\d+)';
        $regular .= '.*?(?<seat>\d+\s*[A-Z]+)\s+(?<bc>[A-Z])(?:\s*/\s*(\w+).*?(?<duration>\d+:\d+))?';

        $regular2 = "(?<al>[A-Z]{2})?\s*(?<fl>\d+)\s*\/\s*(?<date>\d+\w+\d?)\s+(?<dname>.+?)\s*-\s*";
        $regular2 .= "(?<aname>.+?)\s+(?<dtime>\d+:\d+)\s+(?<atime>\d+:\d+).*?(?<bc>[A-Z])(?:\s*\/\s*(\w+).*?(?<duration>\d+:\d+))?";

        if (preg_match("#{$regular}#s", $text, $matches) || preg_match("#{$regular2}#s", $text, $matches)) {
            $segment['AirlineName'] = $matches['al'];
            $segment['FlightNumber'] = $matches['fl'];
            $segment['DepName'] = trim($matches['dname']);
            $segment['ArrName'] = trim($matches['aname']);
            $segment += $this->increaseDate($matches['date'], $matches['dtime'], $matches['atime']);
            //			$segment['Seats'] = $matches['seat];
            $segment['BookingClass'] = $matches['bc'];

            if (!empty($matches['duration'])) {
                $segment['Duration'] = $matches['duration'];
            }
            $segment['ArrCode'] = $segment['DepCode'] = TRIP_CODE_UNKNOWN;
        }

        if (preg_match("#Terminal[ ](.+)#", $text, $m)) {
            $segment['DepartureTerminal'] = trim(explode("  ", $m[1])[0]);
        }

        if (preg_match("#Operated by[ ]*(.+?)(?:[ ]{2,}|\n)#", $text, $m)) {
            $segment['Operator'] = $m[1];
        }

        return $segment;
    }

    /**
     * Quick change preg_match. 1000 iterations takes 0.0008 seconds,
     * while similar <i>preg_match('/LEFT(.*)RIGHT')</i> 0.300 sec.
     * <p>
     * Example:
     * <b>LEFT</b> <i>cut text</i> <b>RIGHT</b>
     * <b>LEFT</b> <i>cut text2</i> <b>RIGHT2</b>.
     */
    protected function findСutSectionAll($input, $searchStart, $searchFinish)
    {
        $array = [];

        while (empty($input) !== true) {
            $right = mb_strstr($input, $searchStart);

            foreach ($searchFinish as $value) {
                $left = mb_strstr($right, $value, true);

                if (!empty($left)) {
                    $input = mb_strstr($right, $value);
                    $array[] = mb_substr($left, mb_strlen($searchStart));

                    break;
                }
            }

            if (empty($left)) {
                $input = false;
            }
        }

        return $array;
    }

    protected function getPdfName()
    {
        return '(\w+_CustomerReceiptCopy|.+?Receipt|\w+|.+itinerary)(?: *\(\d+\))?\.pdf';
    }
}
