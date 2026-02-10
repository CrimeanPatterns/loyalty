<?php

namespace AwardWallet\Engine\cdsgroupe\Email;

class It3633300 extends \TAccountCheckerExtended
{
    public $reBody = "cdsgroupe.com";
    public $reBody2 = "Hotel Voucher";
    public $reBody3 = "Bon d'échange";
    public $reBody4 = "Confirmation de réservation";
    public $reFrom = "bookings@cdsgroupe.com";
    public $reSubject = "Votre reservation est annulée";
    public $reSubject2 = "Suivi de votre réservation";

    public function __construct()
    {
        parent::__construct();

        $this->processors = [
            $this->reBody => function (&$itineraries) {
                $text = text($this->http->Response["body"]);
                $it = [];

                $it['Kind'] = "R";

                // ConfirmationNumber
                $it['ConfirmationNumber'] = orval(
                    re("#Your\s+Reservation\s+Ref\.\s*:\s*(\w+)#", $text),
                    re("#Votre\s+Réservation\s+Réf\.\s*:\s*(\w+)#", $text)
                );

                // TripNumber
                // ConfirmationNumbers

                // Hotel Name
                $it['HotelName'] = $this->http->FindSingleNode("//img[contains(@src, 'maps.googleapis.com')]/ancestor::div[1]/preceding-sibling::div[1]");

                // 2ChainName

                // CheckInDate
                $it['CheckInDate'] = strtotime(en(re("#\d+-\w+-\d+#", en($this->getField([
                    "Arrival :",
                    "Arrivée :",
                ])))));

                // CheckOutDate
                $it['CheckOutDate'] = strtotime(en(re("#\d+-\w+-\d+#", en($this->getField([
                    "Departure :",
                    "Départ :",
                ])))));

                // Address
                $it['Address'] = $this->getField([
                    "Address :",
                    "Adresse :",
                ]);

                // DetailedAddress

                // Phone
                $it['Phone'] = $this->getField([
                    "Phone :",
                    "Téléphone :",
                ]);

                // Fax
                // GuestNames
                $it['GuestNames'] = [$this->getField(["First Name :", "Prénom :"]) . ' ' . $this->getField(["Name :", "Nom :"])];

                // Guests
                $it['Guests'] = $this->getField([
                    "Person :",
                    "Personne :",
                ]);

                // Kids
                // Rooms
                $it['Rooms'] = re("#^\d+#", $this->getField([
                    "Room :",
                    "Chambre :",
                ]));

                // Rate
                // RateType

                // CancellationPolicy
                $it['CancellationPolicy'] = $this->getField([
                    "Cancellation :",
                    "Annulation :",
                ]);

                // RoomType
                $it['RoomType'] = re("#(.*?)\s+[-\(]#", $this->getField([
                    "Room :",
                    "Chambre :",
                ]));

                // RoomTypeDescription
                $it['RoomTypeDescription'] = trim(re("#\((.*?)\)#", $this->getField([
                    "Room :",
                    "Chambre :",
                ])));

                // Cost
                // Taxes
                // Total
                $it['Total'] = cost($this->getField("Total :"));

                // Currency
                $it['Currency'] = currency($this->getField("Total :"));

                // SpentAwards
                // EarnedAwards
                // AccountNumbers
                // Status
                $it['Status'] = orval(
                    re("#Hotel\s+Voucher\s+Booking\s+(\w+)#", $text),
                    re("#Hotel\s+Voucher\s+(\w+)\s+Booking#", $text),
                    re("#Bon\s+d'échange\s+(\w+)\s+de\s+réservation#", $text)
                );

                // Cancelled
                if ($it['Status'] == 'Cancellation' || $it['Status'] == 'Annulation') {
                    $it['Cancelled'] = true;
                }

                // ReservationDate
                // NoItineraries
                $itineraries[] = $it;
            },
        ];
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        return strpos($body, $this->reBody) !== false && (
            strpos($body, $this->reBody2) !== false
            || strpos($body, $this->reBody3) !== false
            || strpos($body, $this->reBody4) !== false
        );
    }

    public function detectEmailByHeaders(array $headers)
    {
        return strpos($headers["from"], $this->reFrom) !== false && (
            strpos($headers["subject"], $this->reSubject) !== false
            || strpos($headers["subject"], $this->reSubject2) !== false
        );
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $itineraries = [];

        $body = $parser->getHTMLBody();

        if (empty($body)) {
            $body = $parser->getPlainBody();
            $this->http->SetBody($body);
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

    public static function getEmailLanguages()
    {
        return ["en", "fr"];
    }

    private function getField($str, $n = 1)
    {
        if (!is_array($str)) {
            $str = [$str];
        }
        $rules = implode(" or ", array_map(function ($s) { return "normalize-space(.)='{$s}'"; }, $str));

        return $this->http->FindSingleNode("(//td[{$rules}]/following-sibling::td[1])[{$n}]");
    }
}
