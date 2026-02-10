<?php

namespace AwardWallet\Engine\amadeus\Email;

class It1643340 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s]*(?:De|Von)\s*:[^\n]*?amadeus|REFERENCE DU DOSSIER#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "fr";
    public $typesCount = "1";
    public $reFrom = "#amadeus#i";
    public $reProvider = "#amadeus#i";
    public $caseReference = "";
    public $xPath = "";
    public $mailFiles = "amadeus/it-1643340.eml, amadeus/it-1672025.eml";
    public $pdfRequired = "0";
    public $isAggregator = "1";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return [$text];
                },

                "#.*?#" => [
                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return re("#\s+REFERENCE DU DOSSIER\s+([A-Z\d\-]+)#");
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return trim(re("#[A-Z .,]+/[A-Z., ]+#", substr($text, 0, 140)));
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#MONTANT TOTAL DES BILLETS:\s*([\d.,]+\s*\w+)#"));
                    },

                    "Currency" => function ($text = '', $node = null, $it = null) {
                        return currency(re("#MONTANT TOTAL DES BILLETS:\s*([\d.,]+\s*\w+)#"));
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        return strtotime(en(re("#\s+DATE\s+(\d+)([^\d]+)(\d+)#") . ' ' . re(2) . ' ' . re(3)));
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        $text = clear("#MONTANT TOTAL DES BILLETS.+#msi", $text);

                        return splitter("#(\n\s*[^\n]*?\s*\-\s*[A-Z\d]{2}\s+\d+\s+[A-Z]{3})#", $text);
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return [
                                'AirlineName'  => re("#\-\s*([A-Z\d]{2})\s*(\d+)#"),
                                'FlightNumber' => re(2),
                            ];
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            $anchorDate = strtotime(en(re("#\s+DATE\s+(\d+)([^\d]+)(\d+)#", $this->text()) . ' ' . re(2) . ' ' . re(3)));
                            re("#\n\s*[A-Z]{3}\s+(\d+[A-Z]{3})\s{2,}([^\n]*?)\s{2,}([^\n]*?)\s{2,}(\d+)\s{2,}(\d+)#");

                            $depDate = strtotime(re(1) . $this->getEmailYear() . ', ' . re(4));
                            $arrDate = strtotime(re(1) . $this->getEmailYear() . ', ' . re(5));

                            correctDates($depDate, $arrDate, $anchorDate);

                            return [
                                'DepName' => re(2),
                                'ArrName' => re(3),
                                'DepDate' => $depDate,
                                'ArrDate' => $arrDate,
                            ];
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "Aircraft" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*EQUIPEMENT:\s*([^\n]+)#");
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            return [
                                'BookingClass' => re("#RESERVATION CONFIRMEE\s*\-\s*([A-Z])\s+([^\n]+)#"),
                                'Cabin'        => re(2),
                            ];
                        },

                        "Duration" => function ($text = '', $node = null, $it = null) {
                            return re("#\s+DUREE\s+(\d+:\d+)#");
                        },

                        "Smoking" => function ($text = '', $node = null, $it = null) {
                            return re("#VOL NON FUMEUR#") ? false : null;
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
        return ["fr"];
    }

    public function IsEmailAggregator()
    {
        return true;
    }
}
