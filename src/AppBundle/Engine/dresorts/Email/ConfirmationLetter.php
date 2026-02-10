<?php

namespace AwardWallet\Engine\dresorts\Email;

class ConfirmationLetter extends \TAccountCheckerExtended
{
    public $mailFiles = "dresorts/it-11210773.eml, dresorts/it-2028899.eml";
    public $reBody = "Diamond Resorts";
    public $reBody2 = "Congratulations on selecting";
    public $reSubject = "Confirmation Letter";
    public $reFrom = "Confirmation@diamondresorts.com";

    public function __construct()
    {
        parent::__construct();

        $this->processors = [
            $this->reBody => function (&$itineraries) {
                $it = [];

                $it['Kind'] = "R";

                // ConfirmationNumber
                //$it['ConfirmationNumber'] = $this->getField("Reservation Number");
                $it['ConfirmationNumber'] = $this->getField("Reservation Number");

                // TripNumber
                // ConfirmationNumbers

                // Hotel Name
                $it['HotelName'] = $this->http->FindSingleNode("//table//tr[2]");

                // 2ChainName

                // CheckInDate
                $it['CheckInDate'] = strtotime($this->getField(["Check-in Date", "Arrival Date"]));

                // CheckOutDate
                $it['CheckOutDate'] = strtotime($this->getField(["Check-out Date", "Depart Date"]));

                // Address
                $it['Address'] = $this->http->FindSingleNode("//text()[{$this->eq(['Resort:', 'Hotel:'])}]/following::span[1]", null, true, "#{$it['HotelName']}\s*(.*?)\s*Tel:#");

                // DetailedAddress

                // Phone
                $it['Phone'] = $this->getField(["Phone", "Tel"], 'Presentation Site');

                // Fax
                // GuestNames
                $it['GuestNames'][] = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.),'Reservation Information')]/ancestor::td[1]/preceding::td[1]/descendant::text()[normalize-space(.)!=''][2]");

                // ReservationDate
                $it['ReservationDate'] = strtotime($this->http->FindSingleNode("//text()[starts-with(normalize-space(.),'Reservation Information')]/ancestor::td[1]/preceding::td[1]/descendant::text()[normalize-space(.)!=''][1]"));
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
        return strpos($headers["subject"], $this->reSubject) !== false && strpos($headers["from"], $this->reFrom) !== false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        if (!$this->detectEmailByBody($parser)) {
            return null;
        }

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
            'emailType'  => 'ConfirmationLetter',
            'parsedData' => [
                'Itineraries' => $itineraries,
            ],
        ];

        return $result;
    }

    private function getField($str, $blockWord = null)
    {
        $xpath = $this->starts($str);

        if (empty($blockWord)) {
            return $this->http->FindSingleNode("//text()[{$xpath}]", null, true, "#:\s+(.+)#");
        }

        $preXpath = $this->contains($blockWord);

        return $this->http->FindSingleNode("//text()[{$preXpath}]/following::text()[{$xpath}][1]", null, true, "#:\s+(.+)#");
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field));
    }
}
