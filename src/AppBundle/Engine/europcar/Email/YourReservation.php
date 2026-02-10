<?php

namespace AwardWallet\Engine\europcar\Email;

class YourReservation extends \TAccountCheckerExtended
{
    public $rePlain = "#Thank you for reserving your rental car with Europcar|Thank you for making your reservation with Europcar|Nous vous remercions de votre réservation avec Europcar#i";
    public $rePlainRange = "/1";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "#EUROPCAR\s*: (?:Your reservation|Votre réservation)#i";
    public $langSupported = "en, fr";
    public $typesCount = "1";
    public $reFrom = "#(?:Reservation_Fr|europcar)@mail.europcar.com#i";
    public $reProvider = "#mail.europcar.com#i";
    public $caseReference = "";
    public $xPath = "";
    public $mailFiles = "europcar/it-1942351.eml, europcar/it-1950421.eml, europcar/it-3.eml";
    public $pdfRequired = "";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return [$text];
                },

                "#.*?#" => [
                    "Number" => function ($text = '', $node = null, $it = null) {
                        return re('#(?:Reservation number|NUMERO DE RESERVATION)\s*: (\d+)#i');
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "L";
                    },

                    "PickupLocation" => function ($text = '', $node = null, $it = null) {
                        $res = null;

                        foreach (['Pickup' => '(?:Pick-up|Départ)', 'Dropoff' => '(?:Return|Retour)'] as $key => $value) {
                            $regex = '#';
                            $regex .= $value . ':\s+(.*)\s+';
                            $regex .= '(?:Address|Adresse):?\s+((?s).*?)\s+';
                            $regex .= '(?:Tél|Tel)\s*:\s*(.*)\s+';
                            $regex .= '(?:Fax\s*:\s*(.*)\s+)?\s+';
                            $regex .= '(?:Horaires d\'ouverture|Opening\s+hours)\s*:\s+((?s).*?)\s*\n\s*\n';
                            $regex .= '#i';

                            if (preg_match($regex, $text, $m)) {
                                $res[$key . 'Location'] = $m[1] . ', ' . nice($m[2], ',');
                                $res[$key . 'Phone'] = $m[3];
                                $res[$key . 'Fax'] = ($m[4]) ? $m[4] : null;
                                $res[$key . 'Hours'] = $m[5];
                            }
                        }

                        return $res;
                    },

                    "PickupDatetime" => function ($text = '', $node = null, $it = null) {
                        $subj = orval(
                            re('#Date\s+/\s+Time:\s+(.*)\s+Pick-up:#i'),
                            re('#(?:Pick-up|Départ)(?:(?s).*?)Add?ress(?:(?s).*?)Date\s+/\s+(?:Time|Heure)\s*:\s+(.*)#i')
                        );

                        if (preg_match('#(.*?)\s+/\s+(\d+)[:h](\d+\s*(?:am|pm)?)#i', $subj, $m)) {
                            $dateStr = str_replace('/', '.', $m[1]);
                            $timeStr1 = $m[2];
                            $timeStr2 = $m[3];

                            return strtotime("$dateStr $timeStr1:$timeStr2");
                        }
                    },

                    "DropoffDatetime" => function ($text = '', $node = null, $it = null) {
                        $subj = orval(
                            re('#(?:Return|Retour)(?:(?s).*?)Date\s+/\s+(?:Time|Heure)\s*:\s+(.*)#i'),
                            re('#Date\s+/\s+Time:\s+(.*)\s+Return:#i')
                        );

                        if (preg_match('#(.*?)\s+/\s+(\d+)[:h](\d+\s*(?:am|pm)?)#i', $subj, $m)) {
                            $dateStr = str_replace('/', '.', $m[1]);
                            $timeStr1 = $m[2];
                            $timeStr2 = $m[3];

                            return strtotime("$dateStr $timeStr1:$timeStr2");
                        }
                    },

                    "RentalCompany" => function ($text = '', $node = null, $it = null) {
                        return re("#Numéro ID ([^:]*)#");
                    },

                    "CarType" => function ($text = '', $node = null, $it = null) {
                        if (preg_match('#Vehicle Category:\s+(?P<Model>.*)\s+\((?P<Type>\w{4})\)#i', $text, $m)
                                or preg_match('#Vehicle Category:\s+(?P<Model>.*)\s+-\s+(?P<Type>.*)#i', $text, $m)
                                or preg_match('#Catégorie de véhicule:\s+(?P<Type>.*):\s+(?P<Model>.*)#i', $text, $m)) {
                            return [
                                'CarModel' => $m['Model'],
                                'CarType'  => $m['Type'],
                            ];
                        }
                    },

                    "RenterName" => function ($text = '', $node = null, $it = null) {
                        $regex = '#(?:First\s+Name|Prénom)\s*:\s+(.*)\s+(?:Last\s+Name|Nom)\s*:\s+(.*)#';

                        if (preg_match($regex, $text, $m)) {
                            return $m[1] . ' ' . $m[2];
                        }
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return total(re('#(?:Total Price|Tarif total TTC)\s*: (.*)#'));
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        if (preg_match('#Réservation effectuée : \S+ ([0-9]+) (\S+) ([0-9]+)#', $text, $m)) {
                            $day = $m[1];
                            $month = en($m[2]);
                            $year = $m[3];

                            return strtotime("$month $day $year");
                        } else {
                            return null;
                        }
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
}
