<?php

namespace AwardWallet\Engine\alitalia\Email;

class It2100959 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*Da\s*:[^\n]*?alitalia#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "it";
    public $typesCount = "1";
    public $reFrom = "#noreply@alitalia.com#i";
    public $reProvider = "#alitalia#i";
    public $caseReference = "";
    public $isAggregator = "0";
    public $xPath = "";
    public $mailFiles = "";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return CONFNO_UNKNOWN;
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return re("#Nome/Cognome:\s*([^\n]+)#");
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        $split = preg_split("#Numero e-ticket#", $text);
                        unset($split[0]);
                        $this->segmentscount = count($split);

                        return $split;
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return uberAir(re("#Numero volo:\s*([A-Z\d-\s*]+)\n#"));
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            $node = re("#Tratta:\s*([^\n]+)\n#");
                            $node = explode("-", $node);
                            $depcode = uberAircode($node[0]);
                            $arrcode = uberAircode($node[1]);
                            $depname = re("#\)([^\n]+)#", $node[0]);
                            $arrname = re("#\)([^\n]+)#", $node[1]);

                            return [
                                'DepCode' => $depcode,
                                'ArrCode' => $arrcode,
                                'DepName' => trim($depname),
                                'ArrName' => trim($arrname),
                            ];
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $date = re("#Data:\s*([^\n]+)\n#");
                            $date = \DateTime::createFromFormat("d/m/Y H.i", $date);

                            if ($date) {
                                $date = $date->getTimestamp();
                            }

                            return $date;
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            $date = re("#Data:\s*([^\n]+)\n#");
                            $time = re("#Orario di arrivo:\s*([\d.]+)#");
                            $date = uberDate($date);
                            $date = $date . " " . $time;
                            $date = \DateTime::createFromFormat("d/m/Y H.i", $date);

                            if ($date) {
                                $date = $date->getTimestamp();
                            }

                            return $date;
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            $this->setDocument("application/pdf", 'simpletable');

                            if (isset($this->segment)) {
                                $this->segment++;
                            } else {
                                $this->segment = 1;
                            }
                            $cabin = "(//*[contains(text(), 'FLIGHT')]/ancestor-or-self::tr[1]/following-sibling::tr[" . $this->segment . "]/td[46])[1]";
                            $cabin = node($cabin);
                            $seat = node("(//*[contains(text(), 'FLIGHT')]/ancestor-or-self::tr[1]/following-sibling::tr[" . $this->segment . "]/td[last()])[1]");

                            return [
                                'Cabin' => trim($cabin),
                                'Seats' => $seat,
                            ];
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
        return ["it"];
    }

    public function IsEmailAggregator()
    {
        return false;
    }
}
