<?php

namespace AwardWallet\Engine\easyjet\Email;

use PlancakeEmailParser;

class YourBooking extends \TAccountCheckerExtended
{
    public $reFrom = "#easyjet#i";

    public $mailFiles = "easyjet/it-1898952.eml, easyjet/it-1994882.eml, easyjet/it-2134727.eml, easyjet/it-2134770.eml, easyjet/it-2134781.eml, easyjet/it-2135059.eml, easyjet/it-2135137.eml, easyjet/it-4128793.eml, easyjet/it-4217131.eml, easyjet/it-4248520.eml, easyjet/it-4249508.eml, easyjet/it-4309529.eml, easyjet/it-4326940.eml";

    private $detects = [
        'email from easyJet',
        'BON VOYAGE! YOUR TRAVEL ITINERARY',
        'IMPORTANT INFORMATION FROM',
        'NEARLY TIME TO GO',
    ];

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
                        return orval(
                            re('#(?:Your\s+booking|Ihre\s+Buchung|La tua prenotazione|Uw boeking|Réservation)\s+([\w\-]+)#i', $this->parser->getSubject()),
                            CONFNO_UNKNOWN
                        );
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        // echo $text;
                        $subj = re('#(?:TRAVEL\s+ITINERARY|YOUR\s+FLIGHT\s+DETAILS|IHR\s+REISEPLAN|TU ITINERARIO DE VIAJE|IL TUO ITINERARIO DI VIAGGIO|UW REISSCHEMA|Prêt\s+à\s+vous\s+envoler\s+\?)\s+.*#s');

                        return splitter('#(\S[^\n]+\s+(?:to|nach|a|naar|à destination de)\s+.*\s+(?:Dep(?:art)?|Abflug|Vuelo con salida a las|Part\.|Vertrek op|Dép\.)\s+\d+(?:(?s).*?)\s+(?:Flight|Flug|Volo|Vluchtnr\.|Vol)?\s+\w{3}\d+)#i', $subj);
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $regex = '#';
                            $regex .= '(.*)\s+(?:to|nach|a|naar|à destination de)\s+(.*)\s+';
                            $regex .= '(?:Dep(?:art)?|Abflug|Vuelo con salida a las|Part\.|Vertrek op|Dép\.)\s+([^\n]+)\s+';
                            $regex .= '(?:(?:Arr(?:ive)?|Ankunft|y llegada a las|Arr\.|Aankomst op|Arr\.)\s+([^\n]+)\s+)?';
                            $regex .= '(?:Flight|Flug|Volo|Vluchtnr\.|Vol)?\s*([A-Z\d]{3})(\d+)';
                            $regex .= '#i';

                            if (preg_match($regex, $text, $m)) {
                                $result = [
                                    'DepName'      => nice($m[1]),
                                    'ArrName'      => nice($m[2]),
                                    'DepDate'      => strtotime(en(trim(str_replace('Flight', '', $m[3])))),
                                    'ArrDate'      => ($m[4]) ? strtotime(en($m[4])) : MISSING_DATE,
                                    'AirlineName'  => $m[5],
                                    'FlightNumber' => $m[6],
                                ];

                                if (preg_match("#(.+)\(\s*(?:Term |T)(.+)\)#", $result['DepName'], $m)) {
                                    $result['DepName'] = trim($m[1]);
                                    $result['DepartureTerminal'] = trim($m[2]);
                                }

                                if (preg_match("#(.+)\(\s*(?:Term |T)(.+)\)#", $result['ArrName'], $m)) {
                                    $result['ArrName'] = trim($m[1]);
                                    $result['ArrivalTerminal'] = trim($m[2]);
                                }

                                return $result;
                            }
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },
                    ],
                ],
            ],
        ];
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody() . '\n' . $parser->getPlainBody();

        foreach ($this->detects as $detect) {
            if (
                (false !== stripos($body, $detect) || 0 < $this->http->XPath->query("//node()[contains(normalize-space(.), '{$detect}')]")->length)
                && 0 < $this->http->XPath->query("//a[contains(@href, 'email.easyjet.com')]")->length
            ) {
                return true;
            }
        }

        return false;
    }

    public static function getEmailTypesCount()
    {
        return 6;
    }

    public static function getEmailLanguages()
    {
        return ["en", "de", "es", "it", "nl", "fr"];
    }
}
