<?php

namespace AwardWallet\Engine\cheapnl\Email;

class It5939596 extends \TAccountChecker
{
    public $mailFiles = "cheapnl/it-12049233.eml, cheapnl/it-12232281.eml, cheapnl/it-12234202.eml, cheapnl/it-4974093.eml, cheapnl/it-5875802.eml, cheapnl/it-5876724.eml, cheapnl/it-5876730.eml, cheapnl/it-5906998.eml, cheapnl/it-5939596.eml, cheapnl/it-6099029.eml";

    public static $dictionary = [
        "de" => [
            //			"Passagiername:" => "",
            //			"Referenznummer" => "",
            //			"Flugticketnummer:" => "",
            //			"Datum" => "",
            //			"Von / Nach" => "",
            //			"Flugnummer:" => "",
            //			"Terminal:" => "",
            //			"Buchungsklasse:" => "",
            //			"mit Code:" => "",
            //			"Alle Zeitangaben sind Ortszeiten" => "",
        ],
        "en" => [
            "Passagiername:"                   => "Passenger name:",
            "Referenznummer"                   => "Reservation number",
            "Flugticketnummer:"                => "Ticket number:",
            "Datum"                            => "Date",
            "Von / Nach"                       => "From / to",
            "Flugnummer:"                      => "Flight number:",
            "Terminal:"                        => "Terminal:",
            "Buchungsklasse:"                  => "Class:",
            "mit Code:"                        => "booking code:",
            "Alle Zeitangaben sind Ortszeiten" => "All times are local",
        ],
        "fr" => [
            "Passagiername:"                   => "Nom du passager:",
            "Referenznummer"                   => "Numéro de référence",
            "Flugticketnummer:"                => "Numéro de billet:",
            "Datum"                            => "Date",
            "Von / Nach"                       => "De / Vers",
            "Flugnummer:"                      => "Numéro de vol:",
            "Terminal:"                        => "Terminal:",
            "Buchungsklasse:"                  => "Classe:",
            "mit Code:"                        => "réservation:",
            "Alle Zeitangaben sind Ortszeiten" => "Tous les horaires indiqués sont",
        ],
        "nl" => [
            "Passagiername:"                   => "Naam passagier:",
            "Referenznummer"                   => "Referentienummer",
            "Flugticketnummer:"                => "Ticketnummer:",
            "Datum"                            => "Datum",
            "Von / Nach"                       => "Van/Naar",
            "Flugnummer:"                      => "Vluchtnummer:",
            "Terminal:"                        => "Vertrek terminal:",
            "Buchungsklasse:"                  => "Klasse:",
            "mit Code:"                        => "boekingscode:",
            "Alle Zeitangaben sind Ortszeiten" => "Alle genoemde tijden zijn ",
        ],
    ];

    public $lang = 'de';

    private $froms = [
        'cheapnl'   => 'noreply@cheaptickets',
        'flugladen' => '@flugladen.de',
        'dreizen'   => 'd-reizen', //@d-reizen.nl, d-reizen@airtrade.nl
    ];

    private $reSubject = [
        "de"=> "E-Ticket für",
        "en"=> "E-Ticket for",
        "fr"=> "Billet électronique pour",
        "nl"=> "E-Ticket naar",
    ];

    private $reBodies = [
        'cheapnl' => [
            'CheapTickets.ch',
        ],
        'flugladen' => [
            'Flugladen.de',
        ],
        'dreizen' => [
            'd-reizen.nl',
        ],
    ];
    private $reBody2 = [
        "de"=> "Elektronisches Ticket",
        "en"=> "Passenger name:",
        "fr"=> "Nom du passager:",
        "nl"=> "Naam passagier:",
    ];
    private $code;
    private $pdfPattern = 'E-ticket[\-\d_]+.pdf';

    public static function getEmailProviders()
    {
        return ['cheapnl', 'flugladen', 'dreizen'];
    }

    public function parsePdf(&$its, $text)
    {
        // RecordLocator
        if (isset($this->code) && isset($this->reBodies[$this->code][0])) {
            $rlDefault = $this->re("#" . $this->reBodies[$this->code][0] . ":[ ]*([A-Z\d]+)#", $text);
        } else {
            $rlDefault = $this->re("#" . $this->t("Referenznummer") . ":[ ]*([A-Z\d]+)#", $text);
        }

        // TripNumber
        // Passengers
        $passengers = trim($this->re("#" . $this->t("Passagiername:") . "[ ]+(.+?)(?:[ ]{3,}|" . $this->t("Referenznummer") . "|\n)#", $text));

        //TicketNumbers
        $ticketNumbers = $this->re("#" . $this->t("Flugticketnummer:") . "[ ]+([\d\-]{5,}?)(?:[ ]{3,}|\n)#", $text);

        $posEnd = stripos($text, $this->t("Alle Zeitangaben sind Ortszeiten"));

        if (!empty($posEnd)) {
            $segmentsText = substr($text, 0, $posEnd);
        } else {
            $segmentsText = $text;
        }
        $segments = $this->split("#\n([ ]*" . $this->preg_implode($this->t("Datum")) . "[ ]+" . $this->preg_implode($this->t("Von / Nach")) . ")#", $segmentsText);

        foreach ($segments as $stext) {
            $seg = [];
            $textArr = explode("\n", $stext);

            if (count($textArr) < 3) {
                $this->logger->info('empty segment');

                return null;
            }
            $posHead = $this->TableHeadPos($textArr[0]);

            if (count($posHead) != 4) {
                $this->logger->info('not detected table');

                return false;
            }

            if (preg_match("#^(.+ )\w+[ ]+\d+:\d+ #u", $textArr[1], $m)) {
                $posHead[2] = mb_strlen($m[1]);
            }
            $posHead[3] = $posHead[3] - 1;
            $table = $this->SplitCols($stext, $posHead);

            if (preg_match("#[^\n]+\n(.+?)\(([A-Z]{3})\)\s*\n(.+?)\(([A-Z]{3})\)#s", $table[1], $m)) {
                // DepCode
                $seg['DepCode'] = $m[2];
                // DepName
                $seg['DepName'] = trim(str_replace("\n", " ", $m[1]));
                // ArrCode
                $seg['ArrCode'] = $m[4];
                // ArrName
                $seg['ArrName'] = trim(str_replace("\n", " ", $m[3]));
            }

            if (preg_match("#\n([\d\/]+)\s+([\d\/]+)#", $table[0], $m)) {
                $date = ["dep" => $m[1], "arr" => $m[2]];
            }

            if (!empty($date) && preg_match_all("#\n.+?[ ]+(\d+:\d+)#", $table[2], $m)) {
                if (count($m[1]) == 2) {
                    // DepDate
                    $seg['DepDate'] = strtotime(str_replace('/', '.', $date['dep'] . ' ' . $m[1][0]));
                    // ArrDate
                    $seg['ArrDate'] = strtotime(str_replace('/', '.', $date['arr'] . ' ' . $m[1][1]));
                }
            }

            if (preg_match("#\n" . $this->preg_implode($this->t("Flugnummer:")) . "[ ]*([A-Z\d]{2})[ ]*(\d{1,5})\b#", $table[3], $m)) {
                // AirlineName
                $seg['AirlineName'] = $m[1];
                // FlightNumber
                $seg['FlightNumber'] = $m[2];
            }

            // DepartureTerminal
            if (preg_match("#\n" . $this->preg_implode($this->t("Terminal:")) . "[ ]*(.+)\b#", $table[3], $m)) {
                $seg['DepartureTerminal'] = $m[1];
            }

            // BookingClass
            if (preg_match("#\n" . $this->preg_implode($this->t("Buchungsklasse:")) . "[ ]*([A-Z]{1,2})\b#", $table[3], $m)) {
                $seg['BookingClass'] = $m[1];
            }

            if (preg_match("#\n" . $this->preg_implode($this->t("mit Code:")) . "[ ]*([A-Z\d]{5,})\b#", $table[3], $m)) {
                $rl = $m[1];
            } else {
                $rl = $rlDefault;
            }

            if (empty($seg['AirlineName']) && empty($seg['FlightNumber']) && empty($seg['DepDate'])) {
                return null;
            }
            $finded = false;

            foreach ($its as $key => $it) {
                if (isset($rl) && $it['RecordLocator'] == $rl) {
                    if (isset($passengers)) {
                        $its[$key]['Passengers'][] = $passengers;
                        $its[$key]['Passengers'] = array_unique($its[$key]['Passengers']);
                    }

                    if (isset($ticketNumbers)) {
                        $its[$key]['TicketNumbers'][] = $ticketNumbers;
                        $its[$key]['TicketNumbers'] = array_unique($its[$key]['TicketNumbers']);
                    }

                    $finded2 = false;

                    foreach ($it['TripSegments'] as $key2 => $value) {
                        if (isset($seg['AirlineName']) && $seg['AirlineName'] == $value['AirlineName']
                                && isset($seg['FlightNumber']) && $seg['FlightNumber'] == $value['FlightNumber']
                                && isset($seg['DepDate']) && $seg['DepDate'] == $value['DepDate']) {
                            //							$its[$key]['TripSegments'][$key2]['Seats'] = array_unique(array_filter(array_merge($value['Seats'], $seg['Seats'])));
                            $finded2 = true;
                        }
                    }

                    if ($finded2 == false) {
                        $its[$key]['TripSegments'][] = $seg;
                    }
                    $finded = true;
                }
            }

            unset($it);

            if ($finded == false) {
                $it['Kind'] = 'T';

                if (isset($rl)) {
                    $it['RecordLocator'] = $rl;
                }

                if (isset($passengers)) {
                    $it['Passengers'][] = $passengers;
                }

                if (isset($ticketNumbers)) {
                    $it['TicketNumbers'][] = $ticketNumbers;
                }
                $it['TripSegments'][] = $seg;
                $its[] = $it;
            }
        }
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->froms as $code => $value) {
            if (stripos($from, $value) !== false) {
                $this->code = $code;

                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        $finded = false;

        foreach ($this->froms as $code => $value) {
            if (stripos($headers['from'], $value) !== false) {
                $this->code = $code;
                $finded = true;
            }
        }

        if ($finded == false) {
            return false;
        }

        foreach ($this->reSubject as $value) {
            if (strpos($headers['subject'], $value) !== false) {
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

        foreach ($pdfs as $pdf) {
            if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
                continue;
            }
            $finded = false;

            foreach ($this->reBodies as $code => $reBody) {
                foreach ($reBody as $re) {
                    if (strpos($text, $re) !== false) {
                        $this->code = $code;
                        $finded = true;
                    }
                }
            }

            if ($finded == false) {
                continue;
            }

            foreach ($this->reBody2 as $re) {
                if (strpos($text, $re) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getHeader('date'));

        $this->http->FilterHTML = false;
        $its = [];

        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        foreach ($pdfs as $pdf) {
            if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
                continue;
            }
            $finded = false;

            foreach ($this->reBodies as $code => $reBody) {
                foreach ($reBody as $re) {
                    if (strpos($text, $re) !== false) {
                        $this->code = $code;
                        $finded = true;
                    }
                }
            }

            if ($finded == false) {
                continue;
            }

            foreach ($this->reBody2 as $lang => $re) {
                if (strpos($text, $re) !== false) {
                    $this->lang = $lang;
                }
            }
            $this->parsePdf($its, $text);
        }

        $result = [
            'emailType'  => 'It5939596_' . ucfirst($this->lang),
            'parsedData' => [
                'Itineraries' => $its,
            ],
        ];

        if (!empty($this->code)) {
            $result['providerCode'] = $this->code;
        }

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

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($str)
    {
        $in = [
            //			"#^(\d+)/(\d+)/(\d{4}),(\d+:\d+)$#",
        ];
        $out = [
            //			"$1.$2.$3, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
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

    private function split($re, $text)
    {
        $r = preg_split($re, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        $ret = [];

        if (count($r) > 1) {
            array_shift($r);

            for ($i = 0; $i < count($r) - 1; $i += 2) {
                $ret[] = $r[$i] . $r[$i + 1];
            }
        } elseif (count($r) == 1) {
            $ret[] = reset($r);
        }

        return $ret;
    }

    private function TableHeadPos($row)
    {
        $head = array_filter(array_map('trim', explode("|", preg_replace("#\s{2,}#", "|", $row))));
        $pos = [];
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }

    private function SplitCols($text, $pos = false)
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->TableHeadPos($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k=>$p) {
                $cols[$k][] = trim(mb_substr($row, $p, null, 'UTF-8'));
                $row = mb_substr($row, 0, $p, 'UTF-8');
            }
        }
        ksort($cols);

        foreach ($cols as &$col) {
            $col = implode("\n", $col);
        }

        return $cols;
    }

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map('preg_quote', $field)) . ')';
    }
}
