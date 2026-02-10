<?php

namespace AwardWallet\Engine\alatur\Email;

class It2791760 extends \TAccountCheckerExtended
{
    public $mailFiles = "alatur/it-2791760.eml";
    public $reBody = "ALATUR";
    public $reBody2 = "Voucher de Hotel";
    public $reSubject = "Voucher de Hotel";
    public $reFrom = "alatur.com";

    public function __construct()
    {
        parent::__construct();

        $this->processors = [
            // "it-2791760.eml"
            $this->reBody2 => function (&$itineraries) {
                $it = [];

                $it['Kind'] = "R";

                // ConfirmationNumber
                $it['ConfirmationNumber'] = $this->http->FindSingleNode("//*[contains(text(),'INFORMAÃÃES ADICIONAIS')]/ancestor::tr[1]/following::tr[1]//tr[2]/td[3]");

                // TripNumber
                // ConfirmationNumbers

                // Hotel Name
                $it['HotelName'] = $this->http->FindSingleNode("//*[contains(text(),'HOTEL')]/ancestor::tr[1]/following::tr[1]//tr[1]//tr[1]");

                // 2ChainName

                // CheckInDate
                $it['CheckInDate'] = strtotime(str_replace('/', '.', $this->http->FindSingleNode("//*[contains(text(),'HOTEL')]/ancestor::tr[1]/following-sibling::tr[2]//table//tr[1]//table//tr[2]/td[1]")));

                // CheckOutDate
                $it['CheckOutDate'] = strtotime(str_replace('/', '.', $this->http->FindSingleNode("//*[contains(text(),'HOTEL')]/ancestor::tr[1]/following-sibling::tr[2]//table//tr[1]//table//tr[2]/td[2]")));

                // Address
                $it['Address'] = $this->http->FindSingleNode("//*[contains(text(),'HOTEL')]/ancestor::tr[1]/following::tr[1]//tr[2]");

                // DetailedAddress

                // Phone
                $it['Phone'] = $this->http->FindSingleNode("//*[contains(text(),'HOTEL')]/ancestor::tr[1]/following::tr[1]//tr[3]", null, true, "#Telefone:\s+(.*?)\s+/#");

                // Fax
                $it['Fax'] = $this->http->FindSingleNode("//*[contains(text(),'HOTEL')]/ancestor::tr[1]/following::tr[1]//tr[3]", null, true, "#Fax:\s+(.+)#");

                // GuestNames
                // Guests
                // Kids
                // Rooms
                // Rate
                // RateType

                // CancellationPolicy
                // RoomType
                // RoomTypeDescription
                // Cost
                $it['Cost'] = (float) str_replace(',', '.', $this->http->FindSingleNode("//*[contains(text(),'HOTEL')]/ancestor::tr[1]/following-sibling::tr[2]//table//tr[4]/td[2]", null, true, "#([\d,]+)#"));

                // Taxes
                $it['Taxes'] = (float) str_replace(',', '.', $this->http->FindSingleNode("//*[contains(text(),'HOTEL')]/ancestor::tr[1]/following-sibling::tr[2]//table//tr[4]/td[5]", null, true, "#([\d,]+)#"));

                // Total
                $it['Total'] = (float) str_replace(',', '.', $this->http->FindSingleNode("//*[contains(text(),'HOTEL')]/ancestor::tr[1]/following-sibling::tr[2]//table//tr[4]/td[6]", null, true, "#([\d,]+)#"));

                // Currency
                $it['Currency'] = $this->http->FindSingleNode("//*[contains(text(),'HOTEL')]/ancestor::tr[1]/following-sibling::tr[2]//table//tr[4]/td[6]", null, true, "#([^\d\s,]+)#");

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
        return strpos($headers["from"], $this->reFrom) !== false && strpos($headers["subject"], $this->reSubject);
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
        return ["pt"];
    }
}
