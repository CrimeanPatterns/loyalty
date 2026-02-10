<?php

namespace AwardWallet\Engine\egencia\Email;

class ReceiptForPDF extends \TAccountCheckerExtended
{
    public $mailFiles = "egencia/it-3560884.eml, egencia/it-3560885.eml, egencia/it-8995739.eml";

    public $reSubject = [
        'en' => ['Receipt for'],
    ];

    private $lang = '';

    private $langDetectors = [
        'en' => ['Itinerary Receipt'],
    ];

    public function __construct()
    {
        parent::__construct();

        $this->processors = [
            "PDF" => function (&$itineraries) {
                $text = text($this->http->Response['body']);

                if (re("#Flights#", $text)) {
                    $it = [];
                    $it['Kind'] = 'T';

                    // RecordLocator
                    $it['RecordLocator'] = re('/Booking ID\s*:\s*([A-Z\d]{5,})/', $text);

                    // TripNumber
                    // Passengers
                    if (preg_match_all("#(.+?)[\s-]+Ticket\s+number:#i", $text, $m)) {
                        $it['Passengers'] = array_unique($m[1]);
                    }

                    if (preg_match_all("#Ticket\s+number:\s+(.+)#i", $text, $m)) {
                        $it['TicketNumbers'] = array_unique($m[1]);
                    }
                    // AccountNumbers
                    // Cancelled
                    // TotalCharge
                    $node = $this->getTotalCurrency(re("#Total\s+flight\s+charges\s+(.+)#", $text));

                    if (!empty($node['Total'])) {
                        $it['TotalCharge'] = $node['Total'];
                        $it['Currency'] = $node['Currency'];
                    }
                    // BaseFare
                    $node = $this->getTotalCurrency(re("#Base\s+fare\s+(.+)#", $text));

                    if (!empty($node['Total'])) {
                        $it['BaseFare'] = $node['Total'];
                        $it['Currency'] = $node['Currency'];
                    }
                    // Currency
                    // Tax
                    $node = $this->getTotalCurrency(re("#Taxes\s+&\s+airline\s+fees\s+(.+)#", $text));

                    if (!empty($node['Total'])) {
                        $it['Tax'] = $node['Total'];
                        $it['Currency'] = $node['Currency'];
                    }
                    // SpentAwards
                    // EarnedAwards
                    // Status

                    // ReservationDate
                    $it['ReservationDate'] = strtotime(re("#Flight purchase.+?(\w{3}\s+\d+,\s+\d{4})#", $text));

                    // NoItineraries
                    // TripCategory

                    preg_match_all("#\n\s*(?<AirlineName>.*?)\s+(?<FlightNumber>\d+)\s+\((?<DepDate>.*?)\)\s+-\s+(?<DepCode>[A-Z]{3})-(?<ArrCode>[A-Z]{3}),\s+(?<Cabin>\w+)\/Coach\s+Class\s+\((?<BookingClass>[A-Z]{1,2})\)#", $text, $segments, PREG_SET_ORDER);

                    $year = $this->http->FindSingleNode("//*[contains(text(),'BOOKING') and contains(text(),'DETAILS')]/ancestor::table[1]/following-sibling::table[3]//td[2]", null, true, "#(\d+)$#");

                    $created = [];

                    foreach ($segments as $segment) {
                        if (!isset($created[$segment['FlightNumber']])) {
                            $itsegment = [];

                            // AirlineName
                            $itsegment['AirlineName'] = $segment['AirlineName'];

                            // FlightNumber
                            $itsegment['FlightNumber'] = $segment['FlightNumber'];

                            // DepDate
                            // ArrDate
                            $itsegment['ArrDate'] = $itsegment['DepDate'] = strtotime($segment['DepDate']);

                            // DepCode
                            $itsegment['DepCode'] = $segment['DepCode'];

                            // ArrCode
                            $itsegment['ArrCode'] = $segment['ArrCode'];

                            // Aircraft
                            // TraveledMiles
                            // Cabin
                            $itsegment['Cabin'] = $segment['Cabin'];

                            // BookingClass
                            $itsegment['BookingClass'] = $segment['BookingClass'];

                            // PendingUpgradeTo
                            // Seats
                            // Duration
                            // Meal
                            // Smoking
                            // Stops
                            $it['TripSegments'][] = $itsegment;

                            $created[$segment['FlightNumber']] = 1;
                        }
                    }
                    $itineraries[] = $it;
                }

                if (re("#Hotels#", $text) && preg_match("#\n\s*(?<HotelName>.*?)\s*\n\s*(?<Address>.*?)\s*\n\s*Check\s+in\s*:\s*(?<CheckInDate>\w+\s+\w+\s+\d+,\s+\d{4})\s*\n\s*Check\s+out\s*:\s*(?<CheckOutDate>\w+\s+\w+\s+\d+,\s+\d{4})#", $text, $m)) {
                    $it = [];
                    $it['Kind'] = 'R';

                    // ConfirmationNumber
                    $it['ConfirmationNumber'] = re("#Itinerary\s+number\s*:\s*(\w+)#", $text);

                    // TripNumber
                    // ConfirmationNumbers

                    // HotelName
                    $it['HotelName'] = $m['HotelName'];

                    // 2ChainName

                    // CheckInDate
                    $it['CheckInDate'] = strtotime($m['CheckInDate']);

                    // CheckOutDate
                    $it['CheckOutDate'] = strtotime($m['CheckOutDate']);

                    // Address
                    $it['Address'] = $m['Address'];

                    // DetailedAddress

                    // Phone
                    // Fax
                    // GuestNames
                    $it['GuestNames'][] = re("#Hotel purchase.+?\n(.+)#", $text);
                    // Guests
                    // Kids
                    // Rooms
                    // Rate
                    $it['Rate'] = re("#\d{4}\s+(.+\s+per\s+night)#", $text);
                    // RateType
                    // CancellationPolicy
                    // RoomType
                    // RoomTypeDescription
                    // Cost
                    // Taxes
                    // Total
                    $node = $this->getTotalCurrency(re("#Total\s+hotel\s+charges\s+(.+)#", $text));

                    if (!empty($node['Total'])) {
                        $it['Total'] = $node['Total'];
                        $it['Currency'] = $node['Currency'];
                    }
                    // Currency
                    // SpentAwards
                    // EarnedAwards
                    // AccountNumbers
                    // Status
                    // Cancelled

                    // ReservationDate
                    $it['ReservationDate'] = strtotime(re("#Hotel purchase.+?(\w{3}\s+\d+,\s+\d{4})#", $text));

                    $itineraries[] = $it;
                }
            },
        ];
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, 'Egencia') !== false
            || stripos($from, '@egencia.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) === false) {
            return false;
        }

        foreach ($this->reSubject as $phrases) {
            foreach ($phrases as $phrase) {
                if (stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (strpos($textPdf, 'through Egencia') === false && strpos($textPdf, 'Egencia fee charge') === false && strpos($textPdf, 'Egencia fee refund') === false) {
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
        $this->http->FilterHTML = false;

        $pdfs = $parser->searchAttachmentByName('.*pdf');

        if (isset($pdfs[0])) {
            $pdf = $pdfs[0];

            if (($htmlPdf = \PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_SIMPLE)) !== null) {
                $htmlPdf = str_replace([' ', '&#160;', '&nbsp;', '  '], ' ', $htmlPdf);
            } else {
                return false;
            }
        } else {
            return false;
        }

        if ($this->assignLang($htmlPdf) === false) {
            return false;
        }

        $this->http->SetEmailBody($htmlPdf);

        $itineraries = [];
        $processor = $this->processors['PDF'];
        $processor($itineraries);

        $result = [
            'emailType'  => 'ReceiptForPDF_' . $this->lang,
            'parsedData' => [
                'Itineraries' => $itineraries,
            ],
        ];

        return $result;
    }

    private function assignLang($text)
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

    private function getTotalCurrency($node)
    {
        $node = str_replace("$", "USD", $node);
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m) || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m) || preg_match("#(?<c>\-*?)(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)) {
            $m['t'] = preg_replace("#,(\d{3})$#", '$1', $m['t']);

            if (strpos($m['t'], ',') !== false && strpos($m['t'], '.') !== false) {
                if (strpos($m['t'], ',') < strpos($m['t'], '.')) {
                    $m['t'] = str_replace(',', '', $m['t']);
                } else {
                    $m['t'] = str_replace('.', '', $m['t']);
                    $m['t'] = str_replace(',', '.', $m['t']);
                }
            }
            $tot = str_replace(",", ".", str_replace(' ', '', $m['t']));
            $cur = $m['c'];
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }
}
