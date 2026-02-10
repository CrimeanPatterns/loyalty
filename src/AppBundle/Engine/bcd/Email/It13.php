<?php

namespace AwardWallet\Engine\bcd\Email;

class It13 extends \TAccountCheckerExtended
{
    public $reFrom = "#@BCDTRAVEL#i";
    public $reProvider = "#[.@]BCDTRAVELMEXICO.COM.MX#i";
    public $rePlain = "#(From|De):[^\n]*?(?:BCD\s*TRAVEL|sap.com)#i";
    public $rePlainRange = "";
    public $typesCount = "1";
    public $langSupported = "es";
    public $isAggregator = "1";
    public $reSubject = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $xPath = "";
    public $mailFiles = "bcd/it-1.eml, bcd/it-13.eml, bcd/it-14.eml, bcd/it-1595435.eml, bcd/it-16.eml, bcd/it-18.eml, bcd/it-4.eml";
    public $rePDF = "";
    public $rePDFRange = "";
    public $pdfRequired = "";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return splitter("#(\s*\w+,\s*\w{3}\s*\d+(?:\s*\-\s*\w+,\s*\w{3}\s*\d+)*\s+(?:Vuelos|Hotel y alojamiento))#msu");
                },

                "#Vuelos:#" => [
                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return orval(
                            re("#Confirmaci[^\n]*nea:\s*([\w\d]+)#i", $this->text()),
                            re("#C\w+digo de reservaci\w*n:\s*([\w\d]+)#ui", $this->text())
                        );
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return re("#\n[>\s]*([^\n]+)[>\s]+C\w+digo de reservaci\w+n#u", $this->text());
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return [$text];
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return re("#Vuelos:\s*.*?,\s*(\w{2}\s*\d+)#");
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*Desde:\s*[^\(\n]+\((\w{3})\)#");
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*Desde:\s*([^\(\n]+)#");
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $dateStr = re('#\w{3},(\s*\w{3}\s*\d+)#u');
                            $timeStr = re('#\s*Sale:\s*(\d+:\d+\s*\w{2})#');

                            return strtotime(en($dateStr) . ' ' . date("Y", strtotime($this->parser->getHeader('date'))) . ', ' . $timeStr);
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*Hasta:\s*[^\(\n]+\((\w{3})\)#");
                        },

                        "ArrName" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*Hasta:\s*([^\(\n]+)#");
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            $time = re("#\s*Llega:\s*(\d+:\d+\s*\w{2})(?:\s*\-\s*\w{3},\s*(\w{3}\s*\d+))*#ums", $text);
                            $date = re(2) ? re(2) : re("#\w{3},(\s*\w{3}\s*\d+)#u");

                            return strtotime(en($date) . ' ' . date("Y", strtotime($this->parser->getHeader('date'))) . ', ' . $time);
                        },

                        "AirlineName" => function ($text = '', $node = null, $it = null) {
                            return re("#Vuelos:\s*(.*?),\s*\w{2}\s*\d+#");
                        },

                        "Aircraft" => function ($text = '', $node = null, $it = null) {
                            return re("#Aeronave:\s*(.*?)\s+Millaje:\s*(\d+)#");
                        },

                        "TraveledMiles" => function ($text = '', $node = null, $it = null) {
                            return re("#Aeronave:\s*.*?\s+Millaje:\s*(\d+)#");
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            return trim(re("#Clase:\s*([^\n]+)#"));
                        },

                        "Duration" => function ($text = '', $node = null, $it = null) {
                            return trim(re("#\n\s*Duraci\w+n:\s*([^\n]+)#u"));
                        },

                        "Smoking" => function ($text = '', $node = null, $it = null) {
                            return re("#Se permite fumar:\s*(No)#") ? false : true;
                        },
                    ],
                ],

                "#Hotel y alojamiento#ms" => [
                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        return re("#\s*Confirmaci\w+n:\s*([\d\w]+)#ui");
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        return trim(re("#Hotel y alojamiento:\s*([^\n]+)#"));
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        return strtotime(en(re("#\s*Entrada:\s*\w+,\s*(\w{3}\s*\d+)#u")) . ' ' . date("Y", strtotime($this->parser->getHeader('date'))));
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        return strtotime(en(re("#\s*Salida:\s*[^,\n]+,\s*(\w{3}\s*\d+)#u")) . ' ' . date("Y", strtotime($this->parser->getHeader('date'))));
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        return re("#\s*Dirección:\s*(.*?)\s+Entrada:#") . ", " . re("#\n\s*(.*?)\s+Salida:#u");
                    },

                    "Phone" => function ($text = '', $node = null, $it = null) {
                        return re("#\s*Teléfono:\s*([\d\-\+ ]+)#");
                    },

                    "Fax" => function ($text = '', $node = null, $it = null) {
                        return re("#\s*FAX:\s*([\d\-\+ ]+)#");
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        return trim(re("#\n[>\s]*([^\n]+)[>\s]+Código de reservación#", $this->parser->getPlainBody()), "\t\r\n*,.");
                    },

                    "Rooms" => function ($text = '', $node = null, $it = null) {
                        return re("#\s*Habitación\(es\):\s*(\d+)#");
                    },

                    "Rate" => function ($text = '', $node = null, $it = null) {
                        return trim(re("#\s*Tarifa:\s*([^\n]+)#"));
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        return re("#\s*Cancelación:\s*([^\n]+)#");
                    },

                    "RoomTypeDescription" => function ($text = '', $node = null, $it = null) {
                        $desc = re("#Detalles de las habitaciones:\s*(.*?)(?:[,\s]+[\d.]+\s*TTL\s+TAX|APPROX. TTL PRICE)#");

                        return trim(clear("#\s*Habitación\(es\):\s*(\d+)#", $desc));
                    },

                    "Taxes" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#[,\s]+([\d.]+)\s*TTL\s+TAX#"));
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#[,\s]*([\d.]+\s*\w+)\s*APPROX\.\s*TTL\s*PRICE#"));
                    },

                    "Currency" => function ($text = '', $node = null, $it = null) {
                        return currency(re("#[,\s]*([\d.]+\s*\w+)\s*APPROX\.\s*TTL\s*PRICE#"));
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re("#\s*Estado:\s*(\w+)#");
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
        return ["es"];
    }

    public function IsEmailAggregator()
    {
        return true;
    }
}
