<?php

namespace AwardWallet\Engine\cosmohotels\Email;

class It1648458 extends \TAccountCheckerExtended
{
    public $mailFiles = "cosmohotels/it-1648458.eml, cosmohotels/it-3074431.eml"; // +1 bcdtravel(html)[en]

    public $reBody = 'Thank you for choosing The Cosmopolitan';

    public function __construct()
    {
        parent::__construct();

        $this->processors = [
            $this->reBody => function (&$itineraries) {
                $patterns = [
                    'time' => '\d{1,2}(?::\d{2})?(?:\s*[AaPp]\.?[Mm]\.?|\s*noon)?', // 4:19PM    |    2:00 p.m.    |    3pm    |    12 noon
                ];

                $this->text = text($this->http->Response['body']);

                $it = [];
                $it['Kind'] = 'R';

                // ConfirmationNumber
                $it['ConfirmationNumber'] = orval(
                    $this->getField("Confirmation Number"),
                    $this->getField("Online Confirmation"),
                    $this->getField("Confirmation:")
                );

                // Hotel Name
                $it['HotelName'] = preg_replace("#\s+#", ' ', re('/Thank you for choosing\s+(.*?)\./s', $this->text));

                // ReservationDate
                $dateBooked = $this->getField("Date Booked");

                if ($dateBooked) {
                    $it['ReservationDate'] = strtotime($dateBooked);
                }

                // CheckInDate
                $it['CheckInDate'] = strtotime($this->getField("Arrival Date"));
                $timeCheckIn = $this->getField("Check-In Begins");

                if (empty($timeCheckIn)) {
                    $timeCheckIn = $this->http->FindSingleNode('//text()[contains(normalize-space(.),"Check-in time is")]', null, true, "/Check-in time is\s*({$patterns['time']})/");
                }

                if (!empty($it['CheckInDate']) && !empty($timeCheckIn)) {
                    $it['CheckInDate'] = strtotime($timeCheckIn, $it['CheckInDate']);
                }

                // CheckOutDate
                $it['CheckOutDate'] = strtotime($this->getField("Departure Date"));
                $timeCheckOut = $this->getField("Check-Out");

                if (empty($timeCheckOut)) {
                    $timeCheckOut = $this->http->FindSingleNode('//text()[contains(normalize-space(.),"check-out time is")]', null, true, "/check-out time is\s*({$patterns['time']})/");
                }

                if (!empty($it['CheckOutDate']) && !empty($timeCheckOut)) {
                    $it['CheckOutDate'] = strtotime($timeCheckOut, $it['CheckOutDate']);
                }

                // Address
                $it['Address'] = orval(
                    $this->http->FindSingleNode("//*[normalize-space(text())='Follow Us']/preceding-sibling::td[1]/text()[2]"),
                    $it['HotelName']
                );

                // Phone
                $phone = $this->http->FindSingleNode("//*[normalize-space(text())='Follow Us']/preceding-sibling::td[1]/a[2]");

                if ($phone) {
                    $it['Phone'] = preg_replace('/(\d)\.(\d)/', '$1-$2', $phone);
                }

                // GuestNames
                $it['GuestNames'] = [$this->getField(["Guest Name", "Reservation Name"])];

                // Guests
                $it['Guests'] = $this->getField("Number of Guests");

                if (empty($it['Guests'])) {
                    unset($it['Guests']);
                }

                // Rooms
                $it['Rooms'] = $this->getField("Number of Rooms");

                if (empty($it['Rooms'])) {
                    unset($it['Rooms']);
                }

                // CancellationPolicy
                $cancelPolicy = $this->http->FindSingleNode("//td[normalize-space(.)='Cancel Policy:']/following-sibling::td[1]");

                if ($cancelPolicy) {
                    if (mb_strlen($cancelPolicy) > 1000) {
                        for ($i = 0; $i < 20; $i++) {
                            $cancelPolicy = preg_replace('/^(.+\w\s*\.).+?\.$/s', '$1', $cancelPolicy);

                            if (mb_strlen($cancelPolicy) < 1001) {
                                break;
                            }
                        }
                    }
                    $it['CancellationPolicy'] = $cancelPolicy;
                }

                // RoomType
                $it['RoomType'] = $this->getField("Room Type");

                // Total
                $it['Total'] = cost($this->getField(["Estimated Reservation Total", "Total Charge"]));

                // Currency
                $it['Currency'] = currency($this->getField("Estimated Reservation Total"));

                $itineraries[] = $it;
            },
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        return strpos($body, $this->reBody) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers['from'], '@thecosmopolitanoflasvegas.com') === false && stripos($headers['subject'], 'The Cosmopolitan of Las Vegas') === false) {
            return false;
        }

        return stripos($headers['subject'], 'Reservation Confirmation') !== false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $itineraries = [];

        $body = $parser->getHTMLBody();

        if (empty($body)) {
            $body = $parser->getPlainBody();
            $this->http->SetEmailBody($body);
        }

        foreach ($this->processors as $re => $processor) {
            if (stripos($body, $re)) {
                $processor($itineraries);

                break;
            }
        }
        $result = [
            'emailType'  => 'Reservations',
            'parsedData' => [
                'Itineraries' => $itineraries,
            ],
        ];

        return $result;
    }

    private function getField($str)
    {
        if (!is_array($str)) {
            $str = [$str];
        }
        $str = implode("|", array_map(function ($s) { return white($s); }, $str));

        return trim(re("#(?:{$str})\s*:?\s*([^\n]+)#", $this->text));
    }
}
