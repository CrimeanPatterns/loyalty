<?php

namespace AwardWallet\Engine\amadeus\Email;

class It1865342 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?amadeus#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en,fr";
    public $typesCount = "1";
    public $reFrom = "#amadeus#i";
    public $reProvider = "#amadeus#i";
    public $caseReference = "";
    public $xPath = "";
    public $mailFiles = "amadeus/it-1865342.eml, amadeus/it-2022477.eml, amadeus/it-2022478.eml, amadeus/it-2022614.eml";
    public $pdfRequired = "0";
    public $isAggregator = "1";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $this->date = strtotime($this->parser->getHeader("date"));
                    $this->setDocument("plain", "text");

                    return splitter("#\n\s*((?:CAR|HOTEL)\s*\-)#");
                },

                "#^HOTEL#" => [
                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*CONFIRMATION\s*:\s*([A-Z\d\-]+)#");
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        return re("#^HOTEL\s*\-\s*([^\n]+)#");
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        return strtotime(re("#\n\s*(?:CHECK[\s-]+IN|ARRIVEE)\s*:\s*(\d+[A-Z]{3})#"), $this->date);
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        return strtotime(re("#\s+(?:CHECK[\s-]+OUT|DEPART)\s*:\s*(\d+[A-Z]{3})#"), $this->date);
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        $address = nice(glue(re("#\n\s*ADDR\s*:\s*(.*?)\s+CONFIRMATION\s*:#ims")));
                        detach("#\s+TELEPHONE\s*:\s*[\d\-\+\(\) ]+#", $address);
                        detach("#\s+TELECOP[\d\-\+\(\) ]+#", $address);

                        return $address;
                    },

                    "Phone" => function ($text = '', $node = null, $it = null) {
                        return re("#\s*TELEPHONE\s*:\s*([\d\-\(\)+ \\/]+)#");
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        return [re("#\s+([A-Z.,]+/[A-Z. ]+)\n#", $this->text())];
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*(\d+)\s+GUEST#");
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        return nice(re("#\n\s*(?:CANCELLATION POLICY|POLITIQUE D'ANNULATION)\s*:\s*(.*?)\s+(?:TAX\:|OR\s+TAXE)#ims"));
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*\d+\s+GUEST\s*\-\s*([^\n]+)#");
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        return totime(re("#\s{2,}DATE\s+(\d{1,2}[A-Z]+\d{2,4})#", $this->text()));
                    },
                ],

                "#^CAR#" => [
                    "Number" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*CONFIRMATION\s*:\s*([A-Z\d\-]+)#");
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "L";
                    },

                    "PickupLocation" => function ($text = '', $node = null, $it = null) {
                        return nice(re("#\n\s*PICK\-UP\s*:\s*\d+\s+\w{3}\s+\d+:\d+\s+(.*?)\s+(?:PICK\-UP|DROP\-OFF)#ims"));
                    },

                    "PickupDatetime" => function ($text = '', $node = null, $it = null) {
                        return strtotime(uberDateTime(re("#\n\s*PICK\-UP\s*:\s*([^\n]+)#")), $this->date);
                    },

                    "DropoffLocation" => function ($text = '', $node = null, $it = null) {
                        $off = nice(re("#\n\s*DROP-OFF\s*:\s*\d+\s+\w{3}\s+\d+:\d+\s+(.*?)\s+ESTIMATED TOTAL#ims"));

                        if (!$off) {
                            $off = $it['PickupLocation'];
                        }

                        return $off;
                    },

                    "DropoffDatetime" => function ($text = '', $node = null, $it = null) {
                        return strtotime(uberDateTime(re("#\n\s*DROP\-OFF\s*:\s*([^\n]+)#")), $this->date);
                    },

                    "PickupPhone" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*PICK-UP TELEPHONE\s*:\s*([^\n]+)#");
                    },

                    "CarType" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*VEHICLE INFORMATION\s*:\s*(.*?)\s+\-\s+#");
                    },

                    "RenterName" => function ($text = '', $node = null, $it = null) {
                        return re("#\s+([A-Z.,]+/[A-Z. ]+)\n#", $this->text());
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#\n\s*ESTIMATED TOTAL\s*:\s*([^\n]+)#"));
                    },

                    "Currency" => function ($text = '', $node = null, $it = null) {
                        return currency(re(1));
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        return strtotime(re("#\s+DATE\s+(\d{2}[A-Z]+\d{2})\s+#", $this->text()), $this->date);
                    },
                ],
            ],
        ];
    }

    public static function getEmailTypesCount()
    {
        return 1;
    }

    public static function getEmailLanguages()
    {
        return ["en", "fr"];
    }

    public function IsEmailAggregator()
    {
        return true;
    }
}
