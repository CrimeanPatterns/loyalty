<?php

namespace AwardWallet\Engine\bcd\Email;

class It2091350 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?bcdtravel#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "es";
    public $typesCount = "1";
    public $reFrom = "#bcdtravel#i";
    public $reProvider = "";
    public $caseReference = "6734";
    public $isAggregator = "1";
    public $xPath = "";
    public $mailFiles = "bcd/it-2091350.eml";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return xpath("
						//*[contains(text(), 'CONFIRMACION DE SERVICIOS')]/ancestor::table[1]//text()[contains(., 'COMIDA')]/ancestor::table[1] |
						//*[contains(text(), 'CONFIRMACION DE SERVICIOS')]/ancestor::table[1]//text()[contains(., 'HABITACION')]/ancestor::table[1]
					");
                },

                ".//text()[contains(., 'HABITACION')]" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        return node("following-sibling::table[1]//tr[2]/td[1]", $node, true, "#^([A-Z\d-]+)#");
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        return node(".//tr[2]/td[1]");
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $year = re("#\s+VIGENTE PARA EL DIA\s*:\s*\d+/\d+/(\d{4})#x", $this->text());

                        return totime(node(".//tr[2]/td[3]", $node, true, "#IN:\s*(\d+[A-Z]{3})#") . $year);
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        $year = re("#\s+VIGENTE PARA EL DIA\s*:\s*\d+/\d+/(\d{4})#x", $this->text());

                        return totime(node(".//tr[2]/td[3]", $node, true, "#OUT:\s*(\d+[A-Z]{3})#") . $year);
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        $addr = text(xpath(".//tr[2]/td[2]"));

                        return [
                            'Phone'   => detach("#\n\s*TEL:\s*([\d\(\)+\- ]+)#", $addr),
                            'Address' => nice($addr, ','),
                        ];
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        return node("following-sibling::table[1]//tr[2]/td[1]", $node, true, "#^[A-Z\d-]+\s+(.+)#");
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        return text(xpath(".//tr[2]/td[5]"));
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#\n\s*VALOR\s*:\s*([^\n]+)#"));
                    },

                    "Currency" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*MONEDA\s*:\s*([A-Z]{3})#");
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        return totime(uberDateTime(re("#\s+FECHA GENERACION\s*:\s*([^\n]+)#x", $this->text())));
                    },
                ],

                ".//text()[contains(., 'AEROLINEA')]" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return node("(//*[contains(text(), 'RECORD')]/ancestor::tr[1]/following-sibling::tr[1]/td[10])[1]", null, true, "#^([A-Z\d-]+)$#");
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return node("(//*[contains(text(), 'CONFIRMACION DE SERVICIOS')]/ancestor::tr[1]/following-sibling::tr[1][contains(., '/')])[1]");
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return cost(node("ancestor::table[1]//*[contains(text(), 'VALOR TOTAL')]/ancestor::tr[1]/following-sibling::tr[1]/td[last()]"));
                    },

                    "BaseFare" => function ($text = '', $node = null, $it = null) {
                        return cost(node("ancestor::table[1]//*[contains(text(), 'VALOR TOTAL')]/ancestor::tr[1]/following-sibling::tr[1]/td[3]"));
                    },

                    "Currency" => function ($text = '', $node = null, $it = null) {
                        return currency(node("ancestor::table[1]//*[contains(text(), 'VALOR TOTAL')]/ancestor::tr[1]/following-sibling::tr[1]/td[2]"));
                    },

                    "Tax" => function ($text = '', $node = null, $it = null) {
                        return cost(node("ancestor::table[1]//*[contains(text(), 'VALOR TOTAL')]/ancestor::tr[1]/following-sibling::tr[1]/td[7]"));
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        return totime(clear("#/#", re("#\s+VIGENTE PARA EL DIA\s*:\s*([^\n]+)#x", $this->text()), '-'));
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath(".//tr[string-length(normalize-space(.))>1][position()>1]");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return node("td[2]", $node, true, "#^\d+$#");
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            $info = text(xpath("td[4]"));

                            return re("#(?:^|\n)\s*DE:\s*([^\n]+)\s+A:#", $info);
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $anchor = totime(clear("#/#", re("#DIA:\s*([^\n]+)#", $this->text()), '-'));

                            $dep = node('td[3]') . ',' . node('td[5]');
                            $arr = node('td[3]') . ',' . node('td[6]');

                            correctDates($dep, $arr, $anchor);

                            return [
                                'DepDate' => $dep,
                                'ArrDate' => $arr,
                            ];
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "ArrName" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*A:\s*(.+)#", text(xpath("td[4]")));
                        },

                        "AirlineName" => function ($text = '', $node = null, $it = null) {
                            return node("td[1]", $node, true, "#/\s*(.+)#");
                        },

                        "Aircraft" => function ($text = '', $node = null, $it = null) {
                            return node('td[9]');
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            return node("td[11]", $node, true, "#\-(.+)#");
                        },

                        "BookingClass" => function ($text = '', $node = null, $it = null) {
                            return node("td[11]", $node, true, "#^([A-Z])\s*\-#");
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            return node("td[13]", $node, true, "#^\d+[A-Z]+$#");
                        },

                        "Duration" => function ($text = '', $node = null, $it = null) {
                            return node("td[7]");
                        },

                        "FlightLocator" => function ($text = '', $node = null, $it = null) {
                            return node("td[10]", $node, true, "#^([A-Z\d-]+)$#");
                        },
                    ],
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
        return ["es"];
    }

    public function IsEmailAggregator()
    {
        return true;
    }
}
