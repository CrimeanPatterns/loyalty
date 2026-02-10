<?php

namespace AwardWallet\Engine\eva\Email;

class EVAAirElectronicTicketServiceInformation extends \TAccountCheckerExtended
{
    public $rePlain = "#[>\s]*From\s*:\s*(Fwd:)*EVA Air#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "#EVA Air#i";
    public $langSupported = "";
    public $typesCount = "";
    public $reFrom = "#[@.]evaair.com#i";
    public $reProvider = "#[@.]evaair.com#i";
    public $xPath = "";
    public $mailFiles = "eva/it-10841308.eml, eva/it-1924168.eml, eva/it-3.eml, eva/it-5212391.eml";
    public $pdfRequired = "";

    private $date = 0;

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $this->accountNumbers = str_replace("\n", ', ', cell('Frequent flyer number', +2));

                    if (!$this->accountNumbers) {
                        $this->accountNumbers = null;
                    }

                    return splitter("#((?:Outbound|Inbound|Aller|Retour|FLIGHTS\s*\d+)\s*:?\s*(?:From|Au départ de))#");
                },

                "#(Outbound|Inboun|Aller|Retour|FLIGHTS\s*\d+)#" => [
                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return re("#(?:Booking reference:|Référence de la réservation).*?\b([A-Z\d]+)\b\s+(?:We have sent a confirmation|Nous avons envoyé)#ms", $this->text());
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $names = [];
                        re("#\s+([^\n]+)\n\s+(?:Ticket Number|Numéro du billet)#ms", function ($m) use (&$names) {
                            $names[trim($m[1])] = 1;
                        }, $this->text());

                        return array_keys($names);
                    },

                    "TicketNumbers" => function ($text = '', $node = null, $it = null) {
                        if (preg_match_all("#(?:Ticket Number|Numéro du billet)\s*:?\s*([\d\-]{5,})#ms", $this->text(), $m)) {
                            return array_unique(array_filter(array_map('trim', $m[1])));
                        }

                        return null;
                    },

                    "AccountNumbers" => function ($text = '', $node = null, $it = null) {
                        return $this->accountNumbers;
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#(?:Total price|Prix total)\s+([^\n]+)#", $this->text()));
                    },

                    "Currency" => function ($text = '', $node = null, $it = null) {
                        return currency(clear("#$#", re("#(?:Total price|Prix total)\s+([^\n]+)#", $this->text())));
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return [$text];
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return re("#[A-Z]{2}(\d+)#");
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            $name = re("#\d+:\d+\s*(.+)(?:\s+\d+:\d+)#ms");

                            if (preg_match("#([\s\S]+)\n(.*Terminal.*)#", $name, $m)) {
                                return ["DepName" => trim($m[1]), "DepartureTerminal" => trim($m[2])];
                            } else {
                                return trim($name);
                            }
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $date = en(str_replace('.', '', re("#\w{3}\.?\s+(\d+\s+\w{3}\.?\s*\d{4})#")));
                            $depDate = strtotime($date . ', ' . re("#(\d+:\d+)\s*.*?\d+:\d+#ms"), $this->date);
                            $arrDate = strtotime($date . ', ' . re("#\d+:\d+\s*.*?(\d+:\d+)#ms"), $this->date);

                            while ($depDate > $arrDate) {
                                $arrDate = strtotime('+1 day', $arrDate);
                            }

                            return ['DepDate' => $depDate, 'ArrDate' => $arrDate];
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "ArrName" => function ($text = '', $node = null, $it = null) {
                            $name = re("#\d+:\d+\s*.*?\d+:\d+\s*(.*?)\s*Cabin#ms");

                            if (preg_match("#([\s\S]+)\n(.*Terminal.*)#", $name, $m)) {
                                return ["ArrName" => trim($m[1]), "ArrivalTerminal" => trim($m[2])];
                            } else {
                                return trim($name);
                            }
                        },

                        "AirlineName" => function ($text = '', $node = null, $it = null) {
                            return re("#([A-Z]{2})\d+#");
                        },

                        "Aircraft" => function ($text = '', $node = null, $it = null) {
                            return trim(re("#(?:Aircraft|Appareil)\s*:?\s*([^\n]+)#"));
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            return re("#(?:Cabin\s*:|Cabine)\s*(\w+)#");
                        },

                        "BookingClass" => function ($text = '', $node = null, $it = null) {
                            return re('#(?:RBD|Booking\s+Class|Classe de réservation)\s*:\s*(\w)\s+#i');
                        },

                        "Duration" => function ($text = '', $node = null, $it = null) {
                            return re("#(?:Total duration\s*:|Durée totole)\s*(\d+h\s*\d+m*)#");
                        },
                    ],
                ],

                "PostProcessing" => function ($text = '', $node = null, $it = null) {
                    return uniteAirSegments($it);
                },
            ],
        ];
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getHeader('Date'));
        $result = parent::ParsePlanEmail($parser);

        return $result;
    }

    public static function getEmailTypesCount()
    {
        return 2;
    }

    public static function getEmailLanguages()
    {
        return ['en', 'fr'];
    }
}
