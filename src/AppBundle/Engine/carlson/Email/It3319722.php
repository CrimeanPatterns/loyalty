<?php

namespace AwardWallet\Engine\carlson\Email;

class It3319722 extends \TAccountCheckerExtended
{
    public $mailFiles = "carlson/it-3321796.eml, carlson/it-3321797.eml, carlson/it-3321804.eml";
    public $reBody = "Carlson";
    public $reBody2 = "Dit bekræftelsesnummer er";
    public $reBody3 = "Bekreftelsesnummeret ditt er";
    public $reFrom = "carlsonhotels@email.carlsonhotels.com";
    public $reSubject = "Din reservasjonsbekreftelse";

    public function __construct()
    {
        parent::__construct();

        $this->processors = [
            $this->reBody => function (&$itineraries) {
                $text = text($this->http->Response["body"]);
                $it = [];

                $it['Kind'] = "R";

                // ConfirmationNumber
                $it['ConfirmationNumber'] = re("#(?:Bekreftelsesnummeret\s+ditt\s+er|Dit\s+bekræftelsesnummer\s+er)\s+(\w+)#", $text);

                // TripNumber
                // ConfirmationNumbers

                // Hotel Name
                $it['HotelName'] = $this->http->FindSingleNode("//img[contains(@src, '/images/bcast_')]/ancestor::td[1]/preceding-sibling::td[1]/table/tbody/tr[1]");

                // 2ChainName

                // CheckInDate
                $it['CheckInDate'] = strtotime(en($this->getField("Ankomstdato:")) . ', ' . $this->getField(["Innsjekkingstid:", "Indtjekningstidspunkt:", "Check-in:"]));

                // CheckOutDate
                $it['CheckOutDate'] = strtotime(en($this->getField(["Afrejsedato:", "Avreisedato"])) . ', ' . $this->getField(["Utsjekkingstid:", "Udtjekningstidspunkt:", "Check-out:"]));

                // Address
                $it['Address'] = nice(implode(" ", $this->http->FindNodes("(//img[contains(@src, '/images/bcast_')]/ancestor::td[1]/preceding-sibling::td[1]/table/tbody/tr[2]//text()[normalize-space(.)])[position()<last()]")));

                // DetailedAddress

                // Phone
                $it['Phone'] = nice(implode(" ", $this->http->FindNodes("(//img[contains(@src, '/images/bcast_')]/ancestor::td[1]/preceding-sibling::td[1]/table/tbody/tr[2]//text()[normalize-space(.)])[position()=last()]")));

                // Fax
                // GuestNames
                $it['GuestNames'] = [$this->getField("Fornavn:")];

                // Guests
                $it['Guests'] = $this->getField(["Antal voksne:", "Antall voksne:"]);

                // Kids
                $it['Kids'] = $this->getField(["Antal børn:", "Antall barn:"]);

                // Rooms
                // Rate
                // RateType

                // CancellationPolicy
                $it['CancellationPolicy'] = $this->http->FindSingleNode("//tr[normalize-space(.)='Avbestillingsregler' or normalize-space(.)='Afbestillingspolitik']/following-sibling::tr[1]");

                // RoomType
                $it['RoomType'] = trim($this->http->FindSingleNode("//td[normalize-space(.)='Pristype:']/ancestor::tr[2]/preceding-sibling::tr[1]", null, true, "#.*?,\s*([^,]+)$#"));

                // RoomTypeDescription
                $it['RoomTypeDescription'] = trim($this->http->FindSingleNode("//td[normalize-space(.)='Pristype:']/ancestor::tr[2]/preceding-sibling::tr[1]", null, true, "#(.*?)\s*,\s*([^,]+)$#"));

                // Cost
                // Taxes
                // Total
                $it['Total'] = cost($this->getField(["Anslået samlet pris*:", "Beregnet total pris*:"]));

                // Currency
                $it['Currency'] = currency($this->getField(["Anslået samlet pris*:", "Beregnet total pris*:"]));

                // SpentAwards
                // EarnedAwards
                // AccountNumbers
                // Status
                // Cancelled
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
        );
    }

    public function detectEmailByHeaders(array $headers)
    {
        return strpos($headers["from"], $this->reFrom) !== false && strpos($headers["subject"], $this->reSubject) !== false;
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
        return ["da", "no"];
    }

    private function getField($str)
    {
        if (!is_array($str)) {
            $str = [$str];
        }
        $rule = implode(" or ", array_map(function ($s) { return "normalize-space(.)='{$s}'"; }, $str));

        return $this->http->FindSingleNode("//td[{$rule}]/following-sibling::td[1]");
    }
}
