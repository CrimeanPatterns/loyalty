<?php

namespace AwardWallet\Engine\airfrance\Email;

use AwardWallet\Engine\MonthTranslate;

class BoardingDocumentsPdf2 extends \TAccountChecker
{
    public $mailFiles = "airfrance/it-10165490.eml, airfrance/it-10631071.eml, airfrance/it-10631073.eml, airfrance/it-27694829.eml, airfrance/it-27772776.eml, airfrance/it-27835195.eml, airfrance/it-7704248.eml, airfrance/it-7779613.eml, airfrance/it-7820824.eml, airfrance/it-7872033.eml, airfrance/it-8371510.eml, airfrance/it-8496840.eml, airfrance/it-8535839.eml, airfrance/it-8535850.eml, airfrance/it-8676380.eml";

    public $reFrom = "@airfrance.fr";
    public $reSubject = [
        "en"  => "Your Air France boarding documents on",
        "fr"  => "Vos documents d’embarquement Air France",
        "es"  => "Sus documentos de embarque Air France",
        "it"  => "I suoi documenti d'imbarco Air France",
        "it2" => "I suoi documenti d’imbarco Air France",
        "ru"  => "Ваши документы на посадку в самолет Air France",
        "de"  => "Ihr Air France Boarding-Unterlagen",
        "zh"  => "的法航登机文件",
    ];
    public $reBody = 'AIR FRANCE';
    public $reBody2 = [
        "en" => ["Your baggage", "Folding instructions"],
        "fr" => "Ces informations incluent les avantages pour les voyageurs",
        "es" => "La franquicia de equipaje efectiva",
        "it" => "La franchigia effettiva può variare",
    ];
    public $pdfPattern = ".*.pdf";
    public $text;
    public $date;

    public static $dictionary = [
        "en" => [
            'Your baggage' => ['Your baggage', 'Folding instructions'],
            'Flight'       => 'Flight',
            'Departure'    => 'Departure',
        ],
        'fr' => [
            'Your baggage'       => ['Vos bagages', 'Ces informations incluent', 'Folding instructions'],
            'RESERVATION'        => 'RÉSERVATION',
            'Flight'             => 'Vol',
            'E-TICKET'           => 'BILLET N°',
            'After this time'    => 'Après cet horaire',
            'Seat'               => 'Siège',
            'Bag drop-off limit' => 'Fin dépose bagage',
            'Departure'          => 'Départ',
            'Arrival'            => 'Arrivée',
        ],
    ];

    public $lang = "en";

    public function parsePdf(&$itineraries)
    {
        $text = $this->text;

        $it = [];
        $it['Kind'] = "T";

        // RecordLocator
        $it['RecordLocator'] = $this->re("#" . $this->t('RESERVATION') . "\s*:\s*(.+)#", $text);

        // TicketNumbers
        if (preg_match_all("#" . $this->t('E-TICKET') . "\s*:\s*(.+)#", $text, $m)) {
            $it['TicketNumbers'] = array_unique($m[1]);
        }
        // TripNumber
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
        $bps = $this->split("#(?:^|\n)([ ]*Boarding pass(?:[ ]{10,}|\n))#", $text);
        $it['TripSegments'] = [];
        $uniq = [];

        foreach ($bps as $bpPage) {
            $column2Pos = 0;

            if (is_array($this->t("Your baggage"))) {
                $column2PosArr = [];

                foreach ($this->t("Your baggage") as $value) {
                    $column2PosArr[] = mb_strlen($this->re("#(?:\n|^)([^\n]*)" . $value . "#", $bpPage), 'UTF-8');
                }
                $column2PosArr = array_filter($column2PosArr);

                if (!empty($column2PosArr)) {
                    $column2Pos = min(array_filter($column2PosArr));
                }
            } else {
                $column2Pos = mb_strlen($this->re("#\n([^\n]*)" . $this->t('Your baggage') . "#", $bpPage), 'UTF-8');
            }

            $pos = [0, $column2Pos];
            $mainTable = $this->splitCols($bpPage, $pos);

            // Passengers
            if (preg_match("#Boarding pass[\s\S]*?\n[ ]+SEC\..+\n\s*(.+)#", $mainTable[0], $m)) {
                $it['Passengers'][] = $m[1];
                $it['Passengers'] = array_unique($it['Passengers']);
            }

            if (preg_match("#(\n[^\n]*" . $this->t('Flight') . ".*?)" . $this->t('After this time') . "#ms", $mainTable[0], $segment)) {
                $stext = $segment[1];
            } else {
                $this->http->log("incorrect segment parse");

                return;
            }

            $table = $this->splitCols($this->re("#\n([^\n]*" . $this->t('Flight') . ".*?)([ ]*\n){3}#ms", $stext), [], true);

            if (count($table) < 3) {
                $this->http->log("incorrect table parse");

                return;
            }

            $itsegment = [];
            // FlightNumber
            $itsegment['FlightNumber'] = $this->re("#\n[A-Z\d]{2}\s*(\d{1,5})(?:\n|[ ]*/[ ]*[A-Z\d]{2}\s*\d{1,5}\s*\n)#", $table[0]);

            if (isset($uniq[$itsegment['FlightNumber']])) {
                $seg = &$it['TripSegments'][$uniq[$itsegment['FlightNumber']]];

                if ($this->re("#" . $this->t('Seat') . "\s+(\d+\w)#", $stext)) {
                    $seg['Seats'][] = $this->re("#" . $this->t('Seat') . "\s+(\d+\w)#", $stext);
                }

                continue;
            }
            $uniq[$itsegment['FlightNumber']] = count($it['TripSegments']);

            // DepCode
            $itsegment['DepCode'] = $this->re("#\n\s*([A-Z]{3})\s*\n#", $table[1]);

            // DepName
            // DepartureTerminal
            $itsegment['DepartureTerminal'] = trim($this->re("#" . $this->t('Bag drop-off limit') . "\s+\d+:\d+\s+Terminal\s*(.+)#", $stext));

            if (empty($itsegment['DepartureTerminal'])) {
                $itsegment['DepartureTerminal'] = trim(preg_replace("#\s+#", " ", $this->re("#Terminal[ ]+(.+)\n\s*Boarding\s+\d+:\d+#", $stext)));
            }

            // DepDate
            $itsegment['DepDate'] = strtotime($this->normalizeDate(trim($this->re("#" . $this->t('Departure') . "\s+(.+)#m", $table[0]))));

            // ArrCode
            $itsegment['ArrCode'] = $this->re("#\n([A-Z]{3})\n#", $table[2]);

            // ArrName
            // ArrivalTerminal
            $itsegment['ArrivalTerminal'] = $this->re("#" . $this->t('Arrival') . "\s+\d+:\d+\s*(?:/.+?)?\s+Terminal\s*(.+)#", $stext);

            // ArrDate
            $nextDay = trim($this->re("#" . $this->t('Arrival') . "\s+(\d+:\d+\s*/.+?)\s*(Terminal|\n)#", $stext));

            if (!empty($nextDay)) {
                $itsegment['ArrDate'] = strtotime($this->normalizeDate($nextDay));
            } else {
                $itsegment['ArrDate'] = strtotime($this->re("#" . $this->t('Arrival') . "\s+(\d+:\d+)#", $stext), $itsegment['DepDate']);
            }
            // AirlineName
            $itsegment['AirlineName'] = $this->re("#\n([A-Z\d]{2})\s*\d{1,5}(?:\n|[ ]*/[ ]*[A-Z\d]{2}\s*\d{1,5}\s*\n)#", $table[0]);

            // Operator
            $itsegment['Operator'] = $this->re("#\n[A-Z\d]{2}\s*\d{1,5}[ ]*/[ ]*([A-Z\d]{2})\s*\d{1,5}\s*\n#", $table[0]);

            // Aircraft
            // TraveledMiles
            // AwardMiles
            // Cabin
            $itsegment['Cabin'] = $this->re("#" . $this->t('Seat') . "\s+\d+\w\s+(.*)\s*" . $this->t('Arrival') . "#", $stext);

            // BookingClass
            // PendingUpgradeTo
            // Seats
            $itsegment['Seats'][] = $this->re("#" . $this->t('Seat') . "\s+(\d+\w)#", $stext);

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
        if (isset($headers['from']) && stripos($headers["from"], $this->reFrom) === false) {
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
        $text = preg_replace("#[^\w\d,. \t\r\n_/+:;\"'’<>«»?~`!@\#$%^&*\[\]=\(\)\-–{}£¥₣₤₧€\$\|]#imsu", ' ', $text);

        if (stripos($text, $this->reBody) === false && stripos($text, 'SEC. AF') === false && stripos($text, 'SEC. KL') === false && stripos($text, 'FRANCE, HOP! or KLM') === false) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            $re = (array) $re;

            foreach ($re as $r) {
                if ($this->striposArr($text, $r)) {
                    return $this->assignLang($text);
                }
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

        if (!isset($pdfs[0])) {
            $this->logger->debug("pdf not found");

            return null;
        }

        foreach ($pdfs as $pdf) {
            if (($this->text = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
                continue;
            }

            $checkFormat = false;

            foreach ($this->reBody2 as $lang => $re) {
                $re = (array) $re;

                foreach ($re as $r) {
                    if (stripos($this->text, $r) !== false) {
                        $this->lang = $lang;
                        $checkFormat = true;

                        break 2;
                    }
                }
            }

            if (!$checkFormat) {
                continue;
            }

            $this->assignLang($this->text);

            $this->parsePdf($itineraries);
        }
        $result = [
            'emailType'  => 'BoardingDocumentsPdf2' . ucfirst($this->lang),
            'parsedData' => [
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

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function assignLang($body)
    {
        foreach (self::$dictionary as $lang => $words) {
            if (isset($words["Flight"], $words["Departure"])) {
                if (stripos($body, $words["Flight"]) !== false && stripos($body, $words["Departure"]) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function normalizeDate($str)
    {
//        $this->http->log('$str = '.print_r( $str,true));
        $year = date("Y", $this->date);
        $in = [
            "#^(\d+:\d+)\s*/\s*(\d+\s+[^\d\s]+)$#", //18:50 / 23 JUL
        ];
        $out = [
            "$1 $year, $2",
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

    private function SplitCols($text, $pos = false, $trim = false)
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (empty($pos)) {
            $pos = $this->TableHeadPos($rows[0]);
            $pos = array_merge($pos, $this->TableHeadPos($rows[1]));
            $pos = array_unique($pos);
            sort($pos);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k=>$p) {
                if ($trim == true) {
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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", $field) . ')';
    }

    private function amount($s)
    {
        return (float) str_replace(",", ".", preg_replace("#[.,\s](\d{3})#", "$1", $s));
    }

    private function striposArr($haystack, $arrayNeedle)
    {
        $arrayNeedle = (array) $arrayNeedle;

        foreach ($arrayNeedle as $needle) {
            if (stripos($haystack, $needle) !== false) {
                return true;
            }
        }

        return false;
    }
}
