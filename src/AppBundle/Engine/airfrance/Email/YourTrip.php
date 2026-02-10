<?php

namespace AwardWallet\Engine\airfrance\Email;

class YourTrip extends \TAccountCheckerExtended
{
    public $reHtml = "#(?:AIR\s+FRANCE|FLYING\s+BLUE)\s+is\s+pleased\s+to\s+send\s+you\s+information\s+concerning\s+your\s+trip|(?:AIR\s+FRANCE|FLYING\s+BLUE)\s+a\s+le\s+plaisir\s+de\s+vous\s+adresser\s+les\s+informations\s+relatives\s+à\s+votre\s+voyage#i";
    public $reHtmlRange = "/1";

    public $reSubject = "Air France";

    public $reProvider = "/airfrance\.fr/i";

    public $mailFiles = "airfrance/it-1.eml, airfrance/it-11085633.eml, airfrance/it-1681473.eml, airfrance/it-1797718.eml, airfrance/it-1926406.eml, airfrance/it-1927390.eml, airfrance/it-1927392.eml, airfrance/it-1927393.eml, airfrance/it-1927398.eml, airfrance/it-2.eml, airfrance/it-2316420.eml, airfrance/it-3.eml, airfrance/it-4011965.eml, airfrance/it-4263290.eml, airfrance/it-4305666.eml, airfrance/it-5.eml, airfrance/it-5014552.eml, airfrance/it-6.eml, airfrance/it-6222339.eml, airfrance/it-6222503.eml";
    private $pdftext;

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $this->pdftext = $this->getDocument('application/pdf', 'text');

                    if (!$this->year = re('#Subject:.*?\s+\d+/\d+/(\d+)#i')) {
                        $this->year = date('Y', strtotime($this->parser->getHeader("date")));
                    }

                    if (strlen($this->year) === 2) {
                        $this->year = '20' . $this->year;
                    }

                    return [$text];
                },

                "#.*?#" => [
                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        $pref = '(?:Booking\s+Ref[\.]?|Numéro\s+de\s+réservation|Référence\s*de\s*votre\s*réservation)';

                        return orval(
                            re("#$pref\s*:\s*([\w\-]+)#iu"),
                            re("/[Rr]éférence\s+de\s+votre\s+réservation\s*\/\s*[Yy]our\s+[Bb]ooking\s+[Rr]ef\s*:(?:\s+'GRUPO\s+CUBATRAVEL'|)\s+([A-Z\d]{5,7})/"),
                            CONFNO_UNKNOWN
                        );
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return 'T';
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $rows = orval(
                            nodes('//*[contains(text(),"Passenger") or contains(.,"Passager")]/ancestor-or-self::tr[1]/following-sibling::tr/td[1]'),
                            nodes('//tr[not(.//tr) and contains(.,"Nom-Prénom")]/following::tr/td[normalize-space(.)!=""][1]')
                        );
                        $tickets = nodes('//*[contains(text(),"Passenger") or contains(.,"Passager")]/ancestor-or-self::tr[1]/following-sibling::tr/td[2]', null, "#^[\d ]+$#");
                        $r = ['Passengers' => [], 'TicketNumbers' => []];

                        foreach ($rows as $row) {
                            if (!$row || re("#(?:From|De)\s*:#", $row)) {
                                break;
                            }
                            $r['Passengers'][] = str_ireplace(['(ADT)', '(CHD)'], ['', ''], $row);
                        }

                        foreach ($tickets as $ticket) {
                            if (!$ticket || re("#A\s*:#", $ticket)) {
                                break;
                            }
                            $r['TicketNumbers'][] = $ticket;
                        }

                        return $r;
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        $x = reni('Total cost  : (\w+ [\d.,]+)', $this->pdftext);

                        return total($x);
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        $regexEn = '/From\s*:.*\s+To\s*:(?:(?s).*?)(?:Baggage|Class.+)/i';
                        $regexFr = '/De(?:\s*\/From|)\s*:.*\s+A(?:\s*\/To|)\s*:(?:(?s).*?)(?:Bagages\s*:|Classe\s*\/Class)[^\n]*/i';
                        $regex = '#';
                        $regex .= '(?:';
                        $regex .= '(?:AIR\s+FRANCE|FLYING\s+BLUE)\s+is\s+pleased\s+to\s+send\s+you\s+information\s+concerning\s+your\s+trip';
                        $regex .= '|';
                        $regex .= '(?:AIR\s+FRANCE|FLYING\s+BLUE)\s+a\s+le\s+plaisir\s+de\s+vous\s+adresser\s+les\s+informations\s+relatives\s+à\s+votre\s+voyage';
                        $regex .= '|';
                        $regex .= 'Det er en Amadeus reservations nummer';
                        $regex .= ')';
                        $regex .= '.*';
                        $regex .= '#is';

                        $subj = re($regex);

                        if (preg_match_all($regexEn, $subj, $m)) {
                            return $m[0];
                        } elseif (preg_match_all($regexFr, $subj, $m)) {
                            return $m[0];
                        }
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            if (preg_match('#(?:Flight|Vol)\s*:\s*(\w+?)(\d+)#i', $text, $m)) {
                                return [
                                    'AirlineName'  => $m[1],
                                    'FlightNumber' => $m[2],
                                ];
                            }
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            return nice(re('/(?:From|De|De\s*\/From)\s*:\s*([^\n\t]+)/'));
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $res = null;

                            if (preg_match('#(?:Departure|Départ)\s*:\s+(\d+)(\w+),\s+(\d+:\d+)#i', $text, $m)) {
                                $dateStr = $m[1] . ' ' . $m[2] . ' ' . $this->year;
                                $timeStr = $m[3];
                                $res['DepDate'] = strtotime($dateStr . ', ' . $timeStr);
                                $regex = '/(?:Arrival|Arrivée)\s*:\s+(\d+:\d+)\s*(?:\((?:Arrival\s+day|Arrivée\s+Jour)\s*([\+-]\d+)\))?/i';

                                if (preg_match($regex, $text, $m)) {
                                    $subj = $dateStr . ((isset($m[2])) ? ' ' . $m[2] . ' day' : '') . ', ' . $m[1];
                                    $res['ArrDate'] = strtotime($subj);
                                } else {
                                    // it-4263290.eml
                                    $res['ArrDate'] = MISSING_DATE;
                                }
                            } elseif (preg_match('#(?:Departure|Départ)\s*:\s+(\d+)(\w+)\s+Classe?#i', $text, $m)) {
                                // it-4305666.eml
                                // TODO: make to object and collect depDay
                                $res['DepDate'] = $res['ArrDate'] = MISSING_DATE;
                            }

                            return $res;
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "ArrName" => function ($text = '', $node = null, $it = null) {
                            return nice(re('/[\n\t]\s*(?:To|A|A\s*\/To)\s*:\s*([^\n\t]+)/'));
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            if (preg_match('/\s*Classe?\s+(\w)\s*(?::\s*(.*))?/', $text, $m)) {
                                return [
                                    'BookingClass' => $m[1],
                                    'Cabin'        => ((isset($m[2]) && $m[2] != 'Access to the Premium/Travel Saver cardholder counter.') ? nice($m[2]) : null),
                                ];
                            }
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            return re('/(?:Seat|Siège)\s*:\s+(\d+\w)/');
                        },
                    ],
                ],
            ],
        ];
    }

    public static function getEmailLanguages()
    {
        return ['en', 'fr', 'nl'];
    }

    public static function getEmailTypesCount()
    {
        return 3;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'airfrance') !== false;
    }
}
