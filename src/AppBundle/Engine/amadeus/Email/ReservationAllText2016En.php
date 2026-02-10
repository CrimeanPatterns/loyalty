<?php

namespace AwardWallet\Engine\amadeus\Email;

class ReservationAllText2016En extends \TAccountChecker
{
    use \DateTimeTools;

    public $mailFiles = "amadeus/it-4845980.eml";

    protected $result = [];

    protected $lang = [
        'hotel'       => ['HOTEL', 'HOTEL RESERVATION'],
        'flight'      => 'FLIGHT',
        'hotelRef'    => 'HOTEL BOOKING REF:',
        'checkIn'     => 'CHECK-IN:',
        'checkOut'    => 'CHECK-OUT:',
        'location'    => 'LOCATION:',
        'reservation' => 'RESERVATION',
        'chainName'   => 'HOTEL CHAIN NAME:',
        'roomType'    => 'ROOM TYPE:',
        'tel'         => 'TELEPHONE:',
        'fax'         => 'FAX:',
        'total'       => 'TOTAL RATE',
        'departure'   => 'DEPARTURE:',
        'arrival'     => 'ARRIVAL:',
        'flightRef'   => 'FLIGHT BOOKING REF:',
        'seat'        => 'SEAT:',
        'sex'         => 'MR|MIS|MRS',
        'status'      => 'RESERVATION',
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        if (!$this->detectEmailByBody($parser)) {
            $this->http->Log('file not recognized, check detectEmailByHeaders or detectEmailByBody method', LOG_LEVEL_ERROR);

            return false;
        }

        if (empty($textBody = $parser->getPlainBody())) {
            $textBody = $this->htmlToText($parser->getHTMLBody());
        }
        $textBody = str_replace(['&nbsp;', chr(194) . chr(160), '&#160;'], ' ', $textBody);
        $this->http->SetEmailBody($textBody);

        $this->parseSegments($textBody);

        return [
            'emailType'  => 'Reservation "T","R" format TEXT from 2016 in "en"',
            'parsedData' => ['Itineraries' => $this->result],
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], 'itinerary@amadeus.com') !== false
                // SEDOGO/LEOPOLD MR 24MAR2016 SUV
                && preg_match('/.+?\s+\d+\w+\s+[A-Z]{3}/u', $headers['subject']);
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return strpos($parser->getHTMLBody(), 'CHECK-IN:') !== false
                && strpos($parser->getHTMLBody(), 'GENERAL INFORMATION') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@amadeus.com') !== false;
    }

    public function IsEmailAggregator()
    {
        return true;
    }

    public static function getEmailTypesCount()
    {
        return 2;
    }

    protected function parseSegments($text)
    {
        $T = $R = [];

        $segments = preg_split('/(?:\n[ >]*){2,}/', $text, -1, PREG_SPLIT_NO_EMPTY);

        foreach ($segments as $segment) {
            if (preg_match("/{$this->opt($this->lang['hotel'])}.+?\d+\s*[^\d\W]+\s*\d+/u", $segment) && stripos($segment, $this->lang['location']) !== false) {
                // HOTEL      HOLIDAY INN SUVA    THU 24 MARCH 2016
                $R[] = $this->parseHotelR($segment);
            } elseif (preg_match("/{$this->lang['flight']}\s+(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])?\s*\d+/", $segment) && stripos($segment, $this->lang['flightRef']) !== false) {
                // FLIGHT     FJ 853 - FIJI AIRWAYS
                $s = $this->parseAirT($segment);
                $T[$s['RecordLocator']][] = $s;
            }
        }

        $this->result = $R;
        $this->joinAirT($T);
    }

    //====================================
    // HOTEL
    //====================================
    protected function parseHotelR($text)
    {
        $rsrv = [];
        $rsrv['Kind'] = 'R';

        if (preg_match("/{$this->lang['hotelRef']}\s*([A-Z\d]+)/", $text, $matches)) {
            $rsrv['ConfirmationNumber'] = $matches[1];
        } else {
            $rsrv['ConfirmationNumber'] = CONFNO_UNKNOWN;
        }

        if (preg_match("/{$this->opt($this->lang['hotel'])}(?:[ ]{2,}(\S.+?\S))?[ ]{2,}(?:[^\d\W]+ )?(\d+\s+[^\d\W]+\s+\d+)[ ]*$/mu", $text, $matches)) {
            if (!empty($matches[1])) {
                $hotelName = $matches[1];
            }
            $date = $matches[2];
        }

        if (isset($date) && preg_match("/{$this->lang['location']}\s*(.*?)\s+{$this->lang['checkIn']}\s*(\d+ \w+)\s+.*?{$this->lang['checkOut']}\s*(\d+ \w+)/", $text, $matches)) {
            $rsrv['Address'] = $matches[1];
            $date = $this->increaseDate($date, $this->dateStringToEnglish($matches[2]), $this->dateStringToEnglish($matches[3]));
            $rsrv['CheckInDate'] = $date['DepDate'];
            $rsrv['CheckOutDate'] = $date['ArrDate'];
        }

        if (preg_match("/{$this->lang['reservation']} (\w+)/", $text, $matches)) {
            $rsrv['Status'] = $matches[1];
        }

        if (preg_match("/(?:XX\s+|{$this->lang['chainName']})\s*(.+)/", $text, $matches)) {
            $rsrv['HotelName'] = $matches[1];
        } elseif (isset($hotelName)) {
            $rsrv['HotelName'] = $hotelName;
        }

        if (preg_match("/{$this->lang['roomType']}\s*(.+)/", $text, $matches)) {
            $rsrv['RoomType'] = $matches[1];
        }

        $patterns['phone'] = '[+)(\d][-.\s\d)(]{5,}[\d)(]'; // +377 (93) 15 48 52    |    713.680.2992

        if (preg_match("#{$this->lang['tel']}[ ]*({$patterns['phone']})[>\s]*{$this->lang['fax']}[ ]*({$patterns['phone']})#", $text, $matches)) {
            $rsrv['Phone'] = preg_replace('/[^\d+-]/', '-', $matches[1]);
            $rsrv['Fax'] = preg_replace('/[^\d+-]/', '-', $matches[2]);
        }

        if (preg_match("/([\d.]+)\s*([A-Z]{2,3})\s*{$this->lang['total']}/", $text, $matches)) {
            $rsrv['Total'] = cost($matches[1]);
            $rsrv['Currency'] = currency($matches[2]);
        }

        if (preg_match("/{$this->lang['status']}\s+(\w+)\s*\(/", $text, $matches)) {
            $rsrv['Status'] = $matches[1];
        }

        return $rsrv;
    }

    //====================================
    // TRIP AIR
    //====================================
    protected function joinAirT($T)
    {
        foreach ($T as $reservation) {
            $result = [];

            foreach ($reservation as $value) {
                $result['Kind'] = 'T';
                $result['RecordLocator'] = $value['RecordLocator'];

                if (isset($value['Passengers'])) {
                    $result['Passengers'][] = trim($value['Passengers']);
                }

                $result['TripSegments'][] = $value['TripSegments'];
            }
            $result['Passengers'] = array_unique($result['Passengers']);
            $this->result[] = $result;
        }
    }

    protected function parseAirT($text)
    {
        $reservation = $segment = [];

        if (preg_match("/{$this->lang['flight']}\s+([A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(\d+).*?(\d+\s*\w+\s*\d+)/", $text, $matches)) {
            $segment['AirlineName'] = $matches[1];
            $segment['FlightNumber'] = $matches[2];
            $date = $matches[3];
        }

        $pregx = '\s+(.+?)[\s-]*(\d+\s*\w+)\s*(\d+:\d+(?:[AP]M)?)';

        if (isset($date) && preg_match("/{$this->lang['departure']}{$pregx}/", $text, $matches1) && preg_match("/{$this->lang['arrival']}{$pregx}/", $text, $matches2)) {
            $segment['DepName'] = $matches1[1];
            $segment['ArrName'] = $matches2[1];
            $segment += $this->increaseDate($date, $matches1[2] . ',' . $matches1[3], $matches2[2] . ',' . $matches2[3]);
        }

        if (preg_match("/{$this->lang['reservation']} \w+, (\w+) \((\w+)\)/", $text, $matches)) {
            $segment['Cabin'] = $matches[1];
            $segment['BookingClass'] = $matches[2];
        }

        if (preg_match("#{$this->lang['flightRef']}\s*([A-Z\d/]+)#", $text, $matches)) {
            $reservation['RecordLocator'] = $matches[1];
        }

        if (preg_match("#{$this->lang['seat']}\s*([A-Z\d/]+)+\s+.*?FOR\s+(.*)(?:{$this->lang['sex']})#", $text, $matches)) {
            $segment['Seats'] = $matches[1];
            $reservation['Passengers'] = $matches[2];
        }

        $segment['ArrCode'] = $segment['DepCode'] = TRIP_CODE_UNKNOWN;

        return $reservation + ['TripSegments' => $segment];
    }

    protected function increaseDate($dateSegment, $depTime, $arrTime)
    {
        $depDate = strtotime($depTime, strtotime($this->dateStringToEnglish($dateSegment)));
        $arrDate = strtotime($arrTime, $depDate);

        while ($depDate > $arrDate) {
            $arrDate = strtotime('+1 day', $arrDate);
        }

        return [
            'DepDate' => $depDate,
            'ArrDate' => $arrDate,
        ];
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) { return preg_quote($s, '/'); }, $field)) . ')';
    }

    private function htmlToText($s = '', $brConvert = true): string
    {
        if ($brConvert) {
            $s = str_replace("\n", '', $s);
            $s = preg_replace('/<[Bb][Rr]\b[ ]*\/?>/', "\n", $s); // only <br> tags
        }
        $s = preg_replace('/<[A-z]+\b.*?\/?>/', '', $s); // opening tags
        $s = preg_replace('/<\/[A-z]+\b[ ]*>/', '', $s); // closing tags
        $s = html_entity_decode($s);
        $s = str_replace(chr(194) . chr(160), ' ', $s); // NBSP to SPACE

        return trim($s);
    }

    private function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = \AwardWallet\Engine\MonthTranslate::translate($monthNameOriginal, 'es')) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }
}
