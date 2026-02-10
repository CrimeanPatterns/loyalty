<?php

namespace AwardWallet\Engine\alitalia\Email;

use AwardWallet\Engine\MonthTranslate;

class BoardingPassTablePdf extends \TAccountChecker
{
    public $mailFiles = "alitalia/it-10465004.eml, alitalia/it-38785524.eml, alitalia/it-4271945.eml, alitalia/it-4876802.eml, alitalia/it-5502816.eml, alitalia/it-7761676.eml, alitalia/it-8263600.eml, alitalia/it-8263603.eml, alitalia/it-9946756.eml";

    public $reFrom = "@ito.it";
    public $reSubject = [
        "it"=> "carta d'imbarco",
        "es"=> "obtener tarjeta de embarque",
    ];
    public $reBody2 = [
        "it"=> "Numero biglietto",
        "es"=> "Número del billete",
    ];

    public $pdfPattern = "(.*\.pdf|Carta d\'imbarco.*)";

    public static $dictionary = [
        "it" => [],
        "es" => [
            "Nome"             => "Nombre",
            "Numero biglietto" => "Número del billete",
        ],
    ];

    public $lang = "it";
    private $providerCode = '';
    private $names = [
        "Praga",
    ];
    private $text = '';

    public function parsePdf(&$itineraries): void
    {
        $text = $this->text;
        $it = [];

        $it['Kind'] = "T";

        // RecordLocator
        $it['RecordLocator'] = $this->http->FindSingleNode('//text()[contains(normalize-space(.), "(PNR)") or contains(normalize-space(.), "(pnr)")]/following::text()[normalize-space(.)][1]', null, false, '#^\s*[A-Z\d]{5,6}\s*$#');

        if (empty($it['RecordLocator'])) {
            $it['RecordLocator'] = CONFNO_UNKNOWN;
        }

        // TripNumber
        // Passengers
        preg_match_all("#" . $this->t("Nome") . "\s+(.*?)\n\S#ms", $text, $travellerMatches);

        $it['Passengers'] = array_unique(array_map(function ($s) {
            return implode(" ", array_map(function ($g) {
                preg_match("#(\S.*?)(\s{2,}|$)#", $g, $ms);

                return $ms[1];
            }, explode("\n", $s)));
        }, $travellerMatches[1]));

        // TicketNumbers
        preg_match_all("#" . $this->t("Numero biglietto") . "\s+(.+)#", $text, $ticketMatches);
        $it['TicketNumbers'] = array_unique($ticketMatches[1]);

        // AccountNumbers
        // Cancelled
        // TotalCharge
        // BaseFare
        // Currency
        // Tax
        // SpentAwards
        // EarnedAwards
        // Status
        // ReservationDate
        // NoItineraries
        // TripCategory
        $it['TripSegments'] = [];

        preg_match_all("#\n([^\n\S]*" . $this->t("FLIGHT") . "\s+" . $this->t("TERMINAL") . "[^\n]+\n\s*\S.*?)\n\n#ms", $text, $segments);
        $uniq = [];

        foreach ($segments[1] as $stext) {
            $itsegment = [];
            $rows = explode("\n", $stext);

            if (count($rows) < 2) {
                $this->logger->info("incorrect rows count");

                return;
            }
            $head = array_shift($rows);

            if (preg_match("#^\s*" . $this->t("Volo operato da") . " (.+)#", $rows[count($rows) - 1], $m)) {
                $itsegment['Operator'] = $m[1];
                unset($rows[count($rows) - 1]);
            }

            $cols = ["FLIGHT", "TERMINAL", "DATE", "FROM", "TO", "DEPARTURE", "GATE", "BOARDING", "CLASS", "SEAT"];
            $pos = [];

            foreach ($cols as $col) {
                $pos[] = strpos($head, $col);
            }

            $table = $this->splitCols(implode("\n", $rows), $pos, false, true);

            if (count($table) != 10) {
                $this->logger->info("incorrect table parse");

                return;
            }

            $date = strtotime($this->normalizeDate($this->re("#^\s*(\d+[^\s\d]+)#", $table[2])));

            // FlightNumber
            $itsegment['FlightNumber'] = $this->re("#^\w{2}(\d+)$#", trim($table[0]));

            if (isset($uniq[$itsegment['FlightNumber']])) {
                $seat = [];

                if (preg_match("#(\d{1,3}[A-Z])#", $table[9], $m)) {
                    $seat[] = $m[1];
                }

                if (empty($seat) && empty(trim($table[9])) && preg_match("#^\s*.+\s(\d{1,3}[A-Z])\s*$#", $table[8], $m)) {
                    $seat[] = $m[1];
                }
                $it['TripSegments'][$uniq[$itsegment['FlightNumber']]]['Seats'] = array_unique(
                    array_filter(array_merge(
                        $it['TripSegments'][$uniq[$itsegment['FlightNumber']]]['Seats'],
                        $seat
                    ))
                );

                continue;
            }
            $uniq[$itsegment['FlightNumber']] = count($it['TripSegments']);

            // DepCode
            // DepName
            if (preg_match("#^\s*(.+?)\s*\(([A-Z]{3})\)\s*$#", trim($table[3]), $m)) {
                $itsegment['DepCode'] = $m[2];
                $itsegment['DepName'] = (strlen($m[1]) > 2) ? $m[1] : '';
            } else {
                $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;
                $itsegment['DepName'] = trim($table[3]);
            }

            // DepartureTerminal
            $itsegment['DepartureTerminal'] = trim($table[1]);

            // DepDate
            $itsegment['DepDate'] = strtotime($table[5], $date);

            // ArrCode
            $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;

            // ArrName
            if (preg_match("#^\s*(.+?)\s*\(([A-Z]{3})\)\s*$#", trim($table[4]), $m)) {
                $itsegment['ArrCode'] = $m[2];
                $itsegment['ArrName'] = $m[1];
            } else {
                $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;
                $itsegment['ArrName'] = trim($table[4]);
            }

            if (!trim($table[4])) {
                if ($name = $this->re("#\s+(" . implode("|", $this->names) . ")$#", $itsegment['DepName'])) {
                    $itsegment['DepName'] = $this->re("#(.*?)\s+" . implode("|", $this->names) . "$#", $itsegment['DepName']);
                    $itsegment['ArrName'] = $name;
                }
            }

            if (preg_match("#(.+)\s(\d{1,2}[:.]\d{1,2})$#", $itsegment['ArrName'], $m)) {
                $itsegment['DepDate'] = strtotime($m[2], $date);
                $itsegment['ArrName'] = trim($m[1]);
            }

            // ArrivalTerminal
            // ArrDate
            $itsegment['ArrDate'] = MISSING_DATE;

            // AirlineName
            $itsegment['AirlineName'] = $this->re("#^(\w{2})\d+#", trim($table[0]));

            // Operator
            // Aircraft
            // TraveledMiles
            // AwardMiles

            // Cabin
            // BookingClass
            if (preg_match("#^\s*([A-Z]{1,2})\s+(.+)#", trim($table[8]), $m)) {
                $itsegment['BookingClass'] = $m[1];
                $itsegment['Cabin'] = $m[2];
            } elseif (preg_match("#^\s*([A-Z]{1,2})\s*$#", trim($table[8]), $m)) {
                $itsegment['BookingClass'] = $m[1];
            } else {
                $itsegment['Cabin'] = trim($table[8]);
            }

            // PendingUpgradeTo
            // Seats
            $itsegment['Seats'] = [];

            if (preg_match("#(\d{1,3}[A-Z])#", $table[9], $m)) {
                $itsegment['Seats'][] = $m[1];
            }

            if (empty(trim($table[9])) && preg_match("#^\s*(.+)\s(\d{1,3}[A-Z])\s*$#", $itsegment['Cabin'], $m)) {
                $itsegment['Seats'][] = $m[2];
            }
            // Duration
            // Meal
            // Smoking
            // Stops
            $it['TripSegments'][] = $itsegment;
        }

        $itineraries[] = $it;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (strpos($headers["from"], $this->reFrom) === false) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            if (stripos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        if (!isset($pdfs[0])) {
            return false;
        }
        $pdf = $pdfs[0];

        if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
            return false;
        }

        if ($this->assignProvider($text, $parser->getHeaders()) !== true) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if (strpos($text, $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getHeader('date'));

        $this->http->FilterHTML = false;
        $itineraries = [];

        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        foreach ($pdfs as $pdf) {
            if (($this->text = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
                return null;
            }

            foreach ($this->reBody2 as $lang=>$re) {
                if (strpos($this->text, $re) !== false) {
                    $this->lang = $lang;
                    $this->assignProvider($this->text, $parser->getHeaders());
                    $this->parsePdf($itineraries);

                    break;
                }
            }
        }

        $classParts = explode('\\', __CLASS__);
        $result = [
            'emailType'    => end($classParts) . ucfirst($this->lang),
            'providerCode' => $this->providerCode,
            'parsedData'   => [
                'Itineraries' => $itineraries,
            ],
        ];

        return $result;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    public static function getEmailProviders()
    {
        return ['itaairways', 'alitalia'];
    }

    private function assignProvider(string $text, array $headers): bool
    {
        if (stripos($text, 'sito ita-airways.com') !== false) {
            $this->providerCode = 'itaairways';

            return true;
        }

        if (self::detectEmailFromProvider($headers['from']) === true
            || strpos($text, 'www.alitalia.com') !== false
        ) {
            $this->providerCode = 'alitalia';

            return true;
        }

        return false;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate(?string $str): string
    {
        $year = date("Y", $this->date);
        $in = [
            "#^(\d+)([^\s\d]+)$#", // 28Lug
        ];
        $out = [
            "$1 $2 $year",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return $str;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function SplitCols($text, array $pos = [], bool $trim = true, bool $correct = false): array
    {
        if (count($pos) === 0) {
            return [];
        }

        $ds = 5;
        $cols = [];
        $rows = explode("\n", $text);
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k=>$p) {
                if ($correct == true) {
                    if ($k != 0 && (!empty(trim(mb_substr($row, $p - 1, 1))) || !empty(trim(mb_substr($row, $p + 1, 1))))) {
                        $str = mb_substr($row, $p - $ds, $ds, 'UTF-8');

                        if (preg_match("#(\S*)\s{2,}(.*)#", $str, $m)) {
                            $cols[$k][] = trim($m[2] . mb_substr($row, $p, null, 'UTF-8'));
                            $row = mb_substr($row, 0, $p - strlen($m[2]) - 1, 'UTF-8');
                            $pos[$k] = $p - strlen($m[2]) - 1;

                            continue;
                        } else {
                            $str = mb_substr($row, $p, $ds, 'UTF-8');

                            if (preg_match("#(\S*)\s{2,}(.*)#", $str, $m)) {
                                $cols[$k][] = trim($m[2] . mb_substr($row, $p + $ds, null, 'UTF-8'));
                                $row = mb_substr($row, 0, $p, 'UTF-8') . $m[1];
                                $pos[$k] = $p + strlen($m[1]) + 1;

                                continue;
                            } elseif (preg_match("#^\s+(.*)#", $str, $m)) {
                                $cols[$k][] = trim($m[1] . mb_substr($row, $p + $ds, null, 'UTF-8'));
                                $row = mb_substr($row, 0, $p, 'UTF-8');

                                continue;
                            } elseif (!empty($str)) {
                                $str = mb_substr($row, $p - $ds, $ds, 'UTF-8');

                                if (preg_match("#(\S*)\s+(\S*)$#", $str, $m)) {
                                    $cols[$k][] = trim($m[2] . mb_substr($row, $p, null, 'UTF-8'));
                                    $row = mb_substr($row, 0, $p - strlen($m[2]) - 1, 'UTF-8');
                                    $pos[$k] = $p - strlen($m[2]) - 1;

                                    continue;
                                }
                            }
                        }
                    }
                }

                if ($trim === true) {
                    $cols[$k][] = trim(mb_substr($row, $p, null, 'UTF-8'));
                } else {
                    $cols[$k][] = mb_substr($row, $p, null, 'UTF-8');
                }
                $row = mb_substr($row, 0, $p, 'UTF-8');
            }
        }
        ksort($cols);

        foreach ($cols as &$col) {
            $col = implode("\n", $col);
        }

        return $cols;
    }
}
