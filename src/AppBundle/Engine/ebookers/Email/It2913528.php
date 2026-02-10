<?php

namespace AwardWallet\Engine\ebookers\Email;

class It2913528 extends \TAccountCheckerExtended
{
    public $mailFiles = "ebookers/it-2913528.eml";
    public $reBody = "ebookers.";
    public $reBody2 = "Hotels";
    public $reSubject = "Ihre Reiseunterlagen";
    public $reFrom = "ebch_de@ebookers.com";

    public function __construct()
    {
        parent::__construct();

        $this->processors = [
            $this->reBody => function (&$itineraries) {
                $it = [];

                $it['Kind'] = "R";

                // ConfirmationNumber
                $it['ConfirmationNumber'] = $this->http->FindSingleNode("(//text()[normalize-space(.)='Hotelbestätigungsnummer:']/following::text()[string-length(normalize-space(.))>1][1])[1]");

                // TripNumber
                // ConfirmationNumbers

                // HotelName
                $it['HotelName'] = $this->http->FindSingleNode("(//*[contains(text(), 'Hotels')])[1]/ancestor::tr[2]/following-sibling::tr[1]//tr[1]");

                // 2ChainName

                // CheckInDate
                $it['CheckInDate'] = strtotime(en($this->http->FindSingleNode("//*[contains(text(), 'Anreise:')]/ancestor-or-self::td[1]", null, true, "#Anreise\s*:\s*\w+,\s+(.+)#")));

                // CheckOutDate
                $it['CheckOutDate'] = strtotime(en($this->http->FindSingleNode("//*[contains(text(), 'Abreise:')]/ancestor-or-self::td[1]", null, true, "#Abreise\s*:\s*\w+,\s+(.+)#")));

                // Address
                $it['Address'] = $this->http->FindSingleNode("(//*[contains(text(), 'Hotels')])[1]/ancestor::tr[2]/following-sibling::tr[2]//table//td/a");

                // DetailedAddress

                // Phone
                $phone = $this->http->FindSingleNode("(//*[contains(text(), 'Hotels')])[1]/ancestor::tr[2]/following-sibling::tr[2]//table//td");

                if (preg_match('#Telefon\s*:\s*([\+\-\d\s\(\)]+)#', $phone, $m)) {
                    $it['Phone'] = trim($m[1]);
                }
                // Fax
                $fax = $this->http->FindSingleNode("(//*[contains(text(), 'Hotels')])[1]/ancestor::tr[2]/following-sibling::tr[2]//table//td");

                if (preg_match("#Fax\s*:\s*([\+\-\d\s\)\(]{3,})#", $fax, $m)) {
                    $it['Fax'] = trim($m[1]);
                }

                // GuestNames
                $it['GuestNames'] = [$this->http->FindSingleNode("//*[contains(text(), 'Hotelreservierung')]/following-sibling::*[1]")];

                // Guests
                // Kids
                // Rooms
                $it['Rooms'] = (int) $this->http->FindSingleNode("//*[contains(text(), 'Reservierung:')]/ancestor-or-self::td[1]", null, true, "#(\d+)#");

                // Rate
                // RateType

                // CancellationPolicy
                $it['CancellationPolicy'] = implode(' ', $this->http->FindNodes("//*[contains(text(), 'Stornierung')]/ancestor-or-self::p/following-sibling::ul/li"));

                // RoomType
                $it['RoomType'] = $this->http->FindSingleNode("//*[contains(text(), 'Zimmerbeschreibung:')]/ancestor::tr[1]", null, true, "#Zimmerbeschreibung\s*:\s*(.*?)(?:,|$)#");

                // RoomTypeDescription
                // Cost
                // Taxes
                // Total
                $it = array_merge($it, total($this->http->FindSingleNode("//*[contains(text(), 'Gesamtpreis') or contains(text(), 'Gesamtreisepreis')]/following-sibling::*"), 'Total'));

                // Currency
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

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        return strpos($body, $this->reBody) !== false && strpos($body, $this->reBody2) !== false;
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
}
