<?php

namespace AwardWallet\Engine\airbaltic\Email;

class It1827233 extends \TAccountCheckerExtended
{
    public $rePlain = "#airbaltic[.]com#i";
    public $rePlainRange = "-1000";
    public $reHtml = "#(?:Your additional item purchased has been successfully registered on|Your itinerary has been successfully registered on|Ihre Buchung wurde erfolgreich vorgenommen am|Matkakuvauksesi tallentaminen onnistui)#";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en, de, fi";
    public $typesCount = "3";
    public $reFrom = "#[@]airbaltic[.]com#i";
    public $reProvider = "#[@.]airbaltic[.]com#i";
    public $caseReference = "";
    public $xPath = "";
    public $mailFiles = "airbaltic/it-1827233.eml, airbaltic/it-1827508.eml, airbaltic/it-1876680.eml, airbaltic/it-1880082.eml, airbaltic/it-1907792.eml, airbaltic/it-1975197.eml, airbaltic/it-4584542.eml, airbaltic/it-4714881.eml, airbaltic/it-4971331.eml";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $pat = preg_replace('/\s+/', '\s*', '(?:Your additional item purchased has been successfully registered on|Your itinerary has been successfully registered on|Ihre Buchung wurde erfolgreich vorgenommen am|Matkakuvauksesi tallentaminen onnistui)');
                    $date_pat = '\w+,\s*\d+[.]\d+[.]\d+';
                    $time_pat = '\d+:\d+\s*[(]\w+[)]';
                    $pat = "/$pat\s*($date_pat\s*$time_pat)[.]/";

                    $dt = re($pat);

                    $dt = uberDateTime($dt);
                    $dt = strtotime(en($dt));
                    $this->reserv = $dt;

                    return [$text];
                },

                "#.*?#" => [
                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return re("#(?:BOOKING\s*REFERENCE|RESERVIERUNGSNUMMER \(BOOKING REFERENCE\)|VARAUSTUNNUS)\s*([\w-]+)#i");
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $ppl = re('/(?:ADULT\/-S|ERWACHSENE\/R|AIKUINEN):\s*(.+?)\s*(?:OUTBOUND|HINFLUG|MENOLENTO)/is');

                        if (preg_match_all('/^(.+)(?:,\s+PINS-numero|$)/m', $ppl, $ms)) {
                            return $ms[1];
                        }
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        $tot = re("#(?:Price\s*for\s*your\s*purchase|Gesamtpreis|Kokonaishinta):\s*([^\n]+)#is");

                        return total($tot);
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        return $this->reserv;
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        $q = '(?:Date|Datum|Päivämäärä): \w{2,3} \d+/\d+';

                        return splitter("#($q)#i");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $fl = re('/(?:Flight\s*No\.|Flug-Nr\.|Lennon nro):\s*([^\n]+)/is');

                            return uberAir($fl);
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            $date = re('/(?:Date|Datum|Päivämäärä):\s*\w+\s+(\d+\/\d+)/is');

                            $seg = re('/(?:Outbound|Departure|Abflug|Lähtö):\s*([^\n]+)/is');
                            $pat = white('(?P<time1>\d+:\d+) (?P<dep>.*?) - (?:Arrival|Ankunft|Saapuminen): (?P<time2>\d+:\d+) (?P<arr>.+)');

                            if (preg_match("/$pat/i", $seg, $ms)) {
                                $time1 = $ms['time1'];
                                $dep = $ms['dep'];
                                $time2 = $ms['time2'];
                                $arr = $ms['arr'];
                            } else {
                                return;
                            }
                            $year = date('Y', $this->reserv);

                            $dt1 = "$date/$year, $time1";
                            $dt2 = "$date/$year, $time2";

                            $fmt = 'd/m/Y, H:i';
                            $dt1 = \DateTime::createFromFormat($fmt, $dt1);
                            $dt2 = \DateTime::createFromFormat($fmt, $dt2);
                            $dt1 = $dt1 ? $dt1->getTimestamp() : null;
                            $dt2 = $dt2 ? $dt2->getTimestamp() : null;

                            if ($dt2 < $dt1) {
                                $dt2 = strtotime('+1 day', $dt2);
                            }

                            if ($dt1 < $this->reserv) {
                                $dt1 = strtotime('+1 year', $dt1);
                            }

                            if ($dt2 < $this->reserv) {
                                $dt2 = strtotime('+1 year', $dt2);
                            }

                            return [
                                'DepName' => $dep,
                                'DepDate' => $dt1,
                                'ArrName' => $arr,
                                'ArrDate' => $dt2,
                            ];
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "Aircraft" => function ($text = '', $node = null, $it = null) {
                            return nice(re("#(?:Aircraft\s*type|Flugzeugtyp|Konetyyppi):\s*([^\n]+)#is"));
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            $class = re('/(?:Class|Tickettyp|Lipputyyppiä):\s*([^\n]+)/is');

                            if (preg_match('/\s*(.+)\s*,\s*(.+)\s*/', $class, $ms)) {
                                return [
                                    'Cabin'        => nice($ms[1]),
                                    'BookingClass' => nice($ms[2]),
                                ];
                            }

                            return $class;
                        },
                    ],
                ],
            ],
        ];
    }

    public static function getEmailTypesCount()
    {
        return 3;
    }

    public static function getEmailLanguages()
    {
        return ["en", "de", "fi"];
    }
}
