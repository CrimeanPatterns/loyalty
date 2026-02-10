<?php

namespace AwardWallet\Engine\aeroplan\Email;

use AwardWallet\Engine\MonthTranslate;

class ETicketPdf extends \TAccountChecker
{
    public $mailFiles = "aeroplan/it-1.eml, aeroplan/it-11564543.eml, aeroplan/it-1681600.eml, aeroplan/it-2204519.eml, aeroplan/it-2263316.eml, aeroplan/it-2454023.eml, aeroplan/it-3130279.eml, aeroplan/it-5780629.eml, aeroplan/it-6166424.eml, aeroplan/it-6166436.eml, aeroplan/it-6166449.eml, aeroplan/it-6212068.eml, aeroplan/it-6407632.eml, aeroplan/it-6660104.eml, aeroplan/it-6710635.eml, aeroplan/it-6764943.eml, aeroplan/it-6766098.eml, aeroplan/it-6941580.eml, aeroplan/it-6941597.eml, aeroplan/it-6941599.eml, aeroplan/it-6941601.eml, aeroplan/it-8497392.eml";

    public $reFrom = "Communication@aircanada.ca";
    public $reSubject = [
        "fr"=> "Modification d'Itinéraire - Information importante",
        "en"=> "Electronic Ticket Itinerary/Receipt",
    ];

    public $langDetectorsPdf = [
        "fr" => ["Nouvel itinéraire"],
        "fr1"=> ["Itinéraire"],
        "en" => ["Flight Itinerary"],
    ];
    public $pdfPattern = '.*pdf';

    public static $dictionary = [
        "fr" => [
            "Booking reference:"                     => ["Numéro de réservation:", "Numéro de référence compagnie aérienne:"],
            "Air Canada Vacations booking reference:"=> "Numéro de référence Vacances Air Canada:",
            "Passenger[^\n]+\s*Name:"                => "(?:Passager :\s+\d+|Passager[^\n]+\s*Nom:)",
            "Ticket number:"                         => "Numéro de billet:",
            "Program number:"                        => "Numéro de membre:",
            "Total in"                               => "Grand total en",
            "Flight"                                 => "Vol",
            "From"                                   => "De",
            "To"                                     => "A   ",
            "OPEN"                                   => "NOTTRANSLATED",
            "Aircraft"                               => "Appareil",
            "Booking class"                          => ["Cabine", "réservation"],
            "Operated by:"                           => "Statut",
            "Seat number\(s\) requested:"            => "Place\(s\) sélectionnée\(s\):",
            "Cabin"                                  => "Catégorie de",
            "Flight Itinerary"                       => "Itinéraire de vol",
            "Passenger Information"                  => "Renseignements sur les passagers",
        ],
        "en" => [
            "Booking reference:"     => ["Booking reference:", "Airline reference number:"],
            "Passenger[^\n]+\s*Name:"=> "(?:Passenger[^\n]+\s*Name:|Passenger:\s+\d+)",
            'Total in'               => ['Total in', 'Total Fare in'],
        ],
    ];

    public $lang = '';

    public function parsePdf(&$itineraries, $text)
    {
        $it = [];
        $it['Kind'] = "T";

        // Status
        if (stripos($text, 'Your booking is confirmed.') !== false) {
            $it['Status'] = 'confirmed';
        }

        // RecordLocator
        $it['RecordLocator'] = $this->re("#(?:" . $this->opt($this->t("Booking reference:")) . ")\s+(\w+)#", $text);

        // TripNumber
        $tripNumber = $this->re('/' . $this->opt($this->t("Air Canada Vacations booking reference:")) . '\s+(\w+)/', $text);

        if ($tripNumber) {
            $it['TripNumber'] = $tripNumber;
        }

        // Passengers
        if (preg_match_all("#" . $this->t("Passenger[^\n]+\s*Name:") . "\s{2,}(.*?)(?:\n|\s{2,})#", $text, $passengers)) {
            $it['Passengers'] = array_unique($passengers[1]);
        }

        // TicketNumbers
        preg_match_all("/" . $this->t("Ticket number:") . "\s+(\d[-\d\s]+\d)/", $text, $passengers);
        $it['TicketNumbers'] = array_unique(array_map(function ($s) { return preg_replace("#\s+#ms", " ", $s); }, $passengers[1]));

        // AccountNumbers
        preg_match_all('/' . $this->t("Program number:") . '\s+([A-Z\d]{1,4}\d{5,})/', $text, $passengers); // AC0913269106
        $it['AccountNumbers'] = array_unique($passengers[1]);

        // Currency
        // TotalCharge
        if (preg_match('/' . $this->opt($this->t("Total in")) . '\s+(?<currency>.*?)\s*:\s+(?<amount>.+)/', $text, $matches)) {
            $it['Currency'] = $this->currency($matches['currency']);
            $it['TotalCharge'] = $this->amount($matches['amount']);

            if (empty($it['TotalCharge'])) {
                unset($it['TotalCharge']);
            }

            if (empty($it['Currency'])) {
                unset($it['Currency']);
            }
        }

        if (($p = strpos($text, 'Nouvel itinéraire')) !== false) {
            $fl = substr($text, $p, strpos($text, 'Ancien itinéraire')) . "\n\n\n\n";
        } elseif (($p = strpos($text, 'Updated Flight Itinerary')) !== false) {
            $fl = substr($text, $p, strpos($text, 'Previous Flight Itinerary') - $p) . "\n\n\n\n";
        } elseif (($p = strpos($text, $this->t('Flight Itinerary'))) !== false) {
            $fl = strstr(substr($text, $p), $this->t('Passenger Information'), true) . "\n\n\n\n";
        } elseif (($p = mb_strpos($text, 'Itinéraire')) !== false) {
            $fl = strstr(mb_substr($text, $p), 'Passagers', true) . "\n\n\n\n";
        } else {
            $fl = $text;
        }

        if ($str = strstr($fl, $this->t('Passenger Information'), true)) {
            $fl = $str;
        }

        $pages = $this->split("#\n([^\n\S]*" . $this->t("Flight") . "\s+" . $this->t("From") . "[^\n]+\n)#", $fl);

        foreach ($pages as $fl) {
            $posCabin = mb_strlen($this->re('/^(.*? )' . $this->t("Cabin") . '(?: |\()/i', $fl), 'UTF-8');
            $posBookingClass = mb_strlen($this->re('/^(.*?)\(?' . $this->opt($this->t("Booking class")) . "(?: |\n|\))/i",
                $fl), 'UTF-8');

            if ($posCabin !== 0 && $posBookingClass !== 0) {
                $posCol4 = $posCabin < $posBookingClass ? $posCabin : $posBookingClass;
            } else {
                $posCol4 = $posCabin === 0 ? $posBookingClass : $posCabin;
            }
            $posCol3 = mb_strlen($this->re("#^(.*?)" . $this->t("Aircraft") . "#", $fl), 'UTF-8');
            $segments = $this->split("#\n([^\n\S]*(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])(?:\d+|" . $this->t("OPEN") . ")\s{2,})#",
                preg_replace("#\s*" . $this->t("Flight") . "\s+" . $this->t("From") . ".+#", "", $fl));

            foreach ($segments as $stext) {
                $stextShorted = preg_replace('/^(.+)' . $this->opt($this->t("Seat number\(s\) requested:")) . '.*$/s',
                    '$1', $stext);

                $pos = [
                    0,
                    ($p = mb_strlen($this->re("#\n(.*?)\d+:\d+\s+\d+:\d+#", $stext),
                        'UTF-8')) != 0 ? $p : mb_strlen($this->re("#^(.*?)" . $this->t("From") . "#", $fl), 'UTF-8'),
                    ($p = mb_strlen($this->re("#\n(.*?\d+:\d+\s+)\d+:\d+#", $stext),
                        'UTF-8')) != 0 ? $p : mb_strlen($this->re("#^(.*?)" . $this->t("To") . "#", $fl), 'UTF-8'),
                    $posCol3,
                    $posCol4,
                    mb_strlen($this->re("#(.*?)\S+\n#", $stext), 'UTF-8') - 2,
                ];
                $table = $this->SplitCols($stextShorted, $pos);

                if (count($table) < 5) {
                    $this->logger->info("incorrect table parse!");

                    return;
                }

                $itsegment = [];

                // FlightNumber
                $itsegment['FlightNumber'] = $this->re("#^(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])(\d+|" . $this->t("OPEN") . ")#",
                    $table[0]);

                if ($itsegment['FlightNumber'] === $this->t("OPEN")) {
                    $itsegment['FlightNumber'] = FLIGHT_NUMBER_UNKNOWN;
                }

                // DepCode
                $itsegment['DepCode'] = $this->re("#\(([A-Z]{3})\)#", $table[1]);

                // DepName
                $itsegment['DepName'] = $this->re("#(.*?)\s+\(([A-Z]{3})\)#", $table[1]);

                // DepartureTerminal
                $itsegment['DepartureTerminal'] = $this->re("#TERMINAL (.+)#", $table[1]);

                // DepDate
                $itsegment['DepDate'] = strtotime($this->normalizeDate($this->re("#\([A-Z]{3}\)\s+(.*?\d+:\d+)#ms",
                    $table[1])));

                // ArrCode
                $itsegment['ArrCode'] = $this->re("#\(([A-Z]{3})\)#", $table[2]);

                // ArrName
                $itsegment['ArrName'] = $this->re("#(.*?)\s+\(([A-Z]{3})\)#", $table[2]);

                // ArrivalTerminal
                $itsegment['ArrivalTerminal'] = $this->re("#TERMINAL (.+)#", $table[2]);

                // ArrDate
                $itsegment['ArrDate'] = strtotime($this->normalizeDate($this->re("#\([A-Z]{3}\)\s+(.*?\d+:\d+)#ms",
                    $table[2])));

                // AirlineName
                $itsegment['AirlineName'] = $this->re("#^([A-Z][A-Z\d]|[A-Z\d][A-Z])(?:\d+|." . $this->t("OPEN") . ")#",
                    $table[0]);

                // Operator
                if (!$itsegment['Operator'] = trim(str_replace("\n", " ",
                    $this->re("#" . $this->t("Operated by:") . "\s+(.*?)(?:Seat number|$)#s", $table[0])))
                ) {
                    $itsegment['Operator'] = trim(str_replace("\n", " ",
                        $this->re("#" . $this->t("Operated by:") . "\s+(.+)#s", $table[1])));
                }

                // Aircraft
                $itsegment['Aircraft'] = $this->re('/^\s*(.+?)(?:[ ]{2}|$)/', trim($table[3]));

                // Cabin
                $itsegment['Cabin'] = $this->re("#(\S{2,}.*?)(?:\s+\(|$)#", $table[4]);

                // BookingClass
                $itsegment['BookingClass'] = $this->re("#(?:^|\()(\w)(?:\)|$)#", trim($table[4]));

                // Seats
                $itsegment['Seats'] = array_filter([
                    $this->re("#" . $this->t("Seat number\(s\) requested:") . "\s+(.+)#", $stext),
                ]);

                $it['TripSegments'][] = $itsegment;
            }
        }

        $itineraries[] = $it;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true && strpos($headers['subject'], 'Air Canada') === false) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            if (stripos($headers['subject'], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if ($textPdf === null) {
                continue;
            }

            if (stripos($textPdf, 'Thank you for choosing Air Canada') === false
                && stripos($textPdf, 'please visit Air Canada') === false
                && stripos($textPdf, 'Learn more about Air Canada') === false
                && stripos($textPdf, 'www.aeroplan.com') === false
                && stripos($textPdf, 'www.aircanada.com') === false) {
                continue;
            }

            if ($this->assignLangPdf($textPdf)) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getHeader('date'));
        $this->http->FilterHTML = false;

        $textPdfFull = '';

        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if ($textPdf === null) {
                continue;
            }

            if ($this->assignLangPdf($textPdf)) {
                $textPdfFull .= $textPdf;
            }
        }

        if (!$textPdfFull) {
            return null;
        }

        $itineraries = [];
        $this->parsePdf($itineraries, $textPdfFull);
        $result = [
            'emailType'  => $a[count($a = explode('\\', __CLASS__)) - 1] . ucfirst($this->lang),
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

    private function assignLangPdf($text = ''): bool
    {
        foreach ($this->langDetectorsPdf as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if (stripos($text, $phrase) !== false) {
                    $this->lang = substr($lang, 0, 2);

                    return true;
                }
            }
        }

        return false;
    }

    private function t($word)
    {
        // $this->http->log($word);
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($str)
    {
        //		 $this->logger->info($str);
        $year = date("Y", $this->date);
        $in = [
            "#^[^\s\d]+ (\d+)-([^\s\d]+)\s+(\d{4})\s+(\d+:\d+)$#", //Tue 13-May 2014\n23:53
        ];
        $out = [
            "$1 $2 $3, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }
        // $this->logger->info($str);
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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", $field) . ')';
    }

    private function amount($s)
    {
        return (float) str_replace(",", ".", preg_replace("#[.,\s](\d{3})#", "$1", $s));
    }

    private function currency($s)
    {
        $sym = [
            'US dollars'       => 'USD',
            'Canadian dollars' => 'CAD',
            'dollars canadiens'=> 'CAD',
        ];

        foreach ($sym as $f=>$r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        return null;
    }
}
