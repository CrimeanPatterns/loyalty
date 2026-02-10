<?php

namespace AwardWallet\Engine\booking\Email;

class EnglishPDF extends \TAccountCheckerExtended
{
    public $mailFiles = "booking/it-1728484.eml";
    public $reBody = "booking.com";

    public $rePDF = "Your modified booking is now confirmed";

    private $isPDF = false;
    private $simple = "";

    public function __construct()
    {
        parent::__construct();

        $this->processors = [
            $this->reBody => function (&$itineraries) {
                $subj = $this->getField("Subject:");
                $text = text($this->http->Response['body']);

                $it = [];

                $it['Kind'] = "R";

                // ConfirmationNumber
                $it['ConfirmationNumber'] = $this->getField(["Booking number:", "Booking number", "Booking Number", "booking.com booking number", "Booking.com reservation number", "Reservation number"]);

                if (!$it['ConfirmationNumber']) {
                    return;
                }

                // TripNumber
                // ConfirmationNumbers

                // Hotel Name
                $it['HotelName'] = re("#Your\s+(?:modified\s+|)booking\s+at\s+(.*?)(\s+has been updated|$)#i", $subj);

                if (!$it['HotelName']) {
                    return;
                }

                // 2ChainName

                // CheckInDate
                $date = $this->getField(["Check-in:", "Check-in", "Check in", "Check in:"], false, "(.)[1]");
                $time = $this->getField(["Check-in:", "Check-in", "Check in", "Check in:"], false, "(.)[1]/following-sibling::p[1]");

                if (!$date) {
                    return;
                }
                $it['CheckInDate'] = strtotime(uberDate($date) . ', ' . re("#(\d+:\d+(?:\s*[ap]m|))#i", $time));

                // CheckOutDate
                $date = $this->getField(["Check-out:", "Check-out", "Check out", "Check out:"], false, "(.)[1]");
                $time = $this->getField(["Check-out:", "Check-out", "Check out", "Check out:"], false, "(.)[1]/following-sibling::p[1]");

                if (!$date) {
                    return;
                }
                $it['CheckOutDate'] = strtotime(uberDate($date) . ', ' . re("#(\d+:\d+(?:\s*[ap]m|))\)*$#i", $time));

                // Address
                $addr = $this->getField(["Address:", "Address", "Address :"], true, ".//text()[string-length(normalize-space(.))>1]");
                $it['Address'] = nice(orval(
                    is_array($addr) ? implode(", ", $addr) : null,
                    $this->getField("Address:"),
                    re("#(.*?)(?:\s+Phone|$)#i", $this->http->FindSingleNode("//*[normalize-space(text())='{$it['HotelName']}']/ancestor::td[1]/*[2]")),
                    $it['HotelName']
                ));

                // DetailedAddress

                // Phone
                $it['Phone'] = orval(
                    $this->getField(["Phone:", "Phone"]),
                    re("#Phone\s*:\s*([\+\-\d]+)#i", $this->http->FindSingleNode("//*[normalize-space(text())='{$it['HotelName']}']/ancestor::td[1]/*[2]"))
                );

                // Fax
                $it['Fax'] = orval(
                    $this->getField(["Fax:", "Fax"]),
                    re("#Fax\s*:\s*([\+\-\d]+)#i", $this->http->FindSingleNode("//*[normalize-space(text())='{$it['HotelName']}']/ancestor::td[1]/*[2]"))
                );

                // GuestNames
                $GuestNames = $this->getField(["Guest name:", "Guest name", "Guest Name:", "Guest Name"], true);
                $it['GuestNames'] = $this->getField(["Guest name:", "Guest name", "Guest Name:", "Guest Name"], true);

                // Guests
                $it['Guests'] = $this->getField("Number of guests", false, false, "#(\d+)\s+(?:person|people|adult)#i");

                // Kids
                $it['Kids'] = $this->getField("Number of guests", false, false, "#(\d+)\s+(?:children)#i");

                // Rooms
                $it['Rooms'] = $this->getField(["Your reservation:", "Your reservation", "Quantity", "Your reservation:"], false, false, "#(\d+)\s+(?:room|apartment)#i");

                // Rate
                // RateType

                // CancellationPolicy
                $it['CancellationPolicy'] = orval(
                    $this->getField("Cancellation policy", false, "./following-sibling::p[1]")
                );

                // RoomType
                $it['RoomType'] = $this->http->FindSingleNode("//*[contains(text(), ' for guest')]", null, true, "#(.*?)\s+for guest#");

                // RoomTypeDescription
                $it['RoomTypeDescription'] = $this->getField("Room Details");

                // Cost
                $totalHeaders = ["Total Price", "Total price", "Total Room Price", "Total room price"];
                $it['Cost'] = cost(preg_replace("#,(\d{3})#", "$1", $this->getField($totalHeaders, false, "./ancestor::tr[1]/preceding-sibling::tr[contains(./td[1], '{$it['RoomType']}') or contains(./td[1], ' room')][1]/td[2]")));

                // Taxes
                $it['Taxes'] = cost(preg_replace("#,(\d{3})#", "$1", orval(
                    $this->getField($totalHeaders, false, "./ancestor::tr[1]/preceding-sibling::tr[contains(., 'VAT') and string-length(normalize-space(./td[2]))>0][1]/td[2]"),
                    $this->getField($totalHeaders, false, "./ancestor::tr[1]/preceding-sibling::tr[contains(., 'Tax') and string-length(normalize-space(./td[2]))>0][1]/td[2]"),
                    $this->getField($totalHeaders, false, "./ancestor::tr[1]/preceding-sibling::tr[contains(., 'tax') and string-length(normalize-space(./td[2]))>0][1]/td[2]")
                )));

                // Total

                $it['Total'] = cost(preg_replace("#,(\d{3})#", "$1", $this->getField($totalHeaders, false, "(.//text()[string-length(normalize-space(.))>0])[1]")));

                // Currency
                $it['Currency'] = currency($this->getField($totalHeaders, false, "(.//text()[string-length(normalize-space(.))>0])[1]"));

                // SpentAwards
                // EarnedAwards
                // AccountNumbers
                // Status
                $it['Status'] = orval(
                    re("#Your\s+(?:modified\s+|)(?:reservation|booking)\s+is\s+now\s+(\w+)#", $text),
                    re("#You\s+have\s+now\s+(\w+)#", $text),
                    re("#your\s+reservation\s+has\s+been\s+(\w+)#i", $text),
                    re("#Your\s+reservation\s+at\s+.*?\s+is\s+now\s+(\w+)#", $text),
                    re("#Your\s+reservation\s+at\s+.*?\s+has\s+now\s+been\s+(\w+)#", $text),
                    re("#We\s+hereby\s+(\w+)#", $text),
                    ($this->http->FindSingleNode("//*[normalize-space(text())='Cancellation']") ? "Cancelled" : null)
                );

                // Cancelled
                if (strpos(strtolower($it['Status']), 'cancel') !== false) {
                    $it['Cancelled'] = true;
                }

                // ReservationDate
                $date = orval(
                    $this->getField(["Booking first made on", "Reservation first made on"]),
                    re('#Booking\s+first\s+made\s+on\s*:?\s*([^\n]+)#msi', $text),
                    re('#Reservation\s+first\s+made\s+on\s*:?\s*([^\n]+)#msi', $text)
                );

                if ($date) {
                    $it['ReservationDate'] = strtotime(uberDateTime($date));
                }

                // PostProcess
                if ($it['RoomType'] == 'rooms') {
                    $it['RoomType'] = null;
                }

                // NoItineraries
                $itineraries[] = $it;
            },
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('\d+-\d+-\d+\s+-\s+.*?\s+-\s+\(CP\)\.pdf');

        if (isset($pdfs[0]) && $pdf = $pdfs[0]) {
            if (($html = \PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_COMPLEX)) !== null) {
                return strpos(text($html), $this->rePDF) !== false;
            }
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->parser = $parser;
        $itineraries = [];

        $body = "";

        $pdfs = $parser->searchAttachmentByName('\d+-\d+-\d+\s+-\s+.*?\s+-\s+\(CP\)\.pdf');

        if (isset($pdfs[0]) && $pdf = $pdfs[0]) {
            if (($body = \PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_COMPLEX)) !== null) {
                $this->http->SetBody($body);
            }

            if (($html = \PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_COMPLEX)) !== null) {
                $this->simple = $html;
            }
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

    //# header - string/array - row header ( Booking number: )
    //# many - bool - one node / many nodes
    //# inner - xpath - get node(s) into field
    //# re - regExp
    public function getField($header, $many = false, $inner = false, $re = "#.+#")
    {
        if (is_array($header)) {
            foreach ($header as &$s) {
                $l = strlen($s);
                $s = "substring(normalize-space(text()), 1, {$l})='{$s}'";
            }
            $str = implode(' or ', $header);
        } else {
            $l = strlen($header);
            $str = "substring(normalize-space(text()), 1, {$l})='{$header}'";
        }
        $xpath = "//*[{$str}]/ancestor-or-self::p[1]/following-sibling::p[1]";

        $http = $this->http;

        if (!$many) {
            // One node
            if (!$inner) {
                return $http->FindSingleNode($xpath, null, true, $re);
            } else {
                // Get inner node
                $nodes = $this->http->XPath->query($xpath);

                if ($nodes->length == 0) {
                    return null;
                }

                return $http->FindSingleNode($inner, $nodes->item(0), true, $re);
            }
        } else {
            // Many nodes
            if (!$inner) {
                return $http->FindNodes($xpath, null, $re);
            } else {
                // Get inner nodes
                $nodes = $this->http->XPath->query($xpath);

                if ($nodes->length == 0) {
                    return null;
                }
                $res = [];

                foreach ($nodes as $node) {
                    $res = array_merge($res, $http->FindNodes($inner, $node, $re));
                }

                return $res;
            }
        }
    }

    public static function getEmailLanguages()
    {
        return ["en"];
    }

    public static function getEmailTypesCount()
    {
        return 1;
    }
}
