<?php

namespace AwardWallet\Engine\aireuropa\Email;

use AwardWallet\Engine\MonthTranslate;

class BoardingPassPdf extends \TAccountChecker
{
    public $mailFiles = "aireuropa/it-15986542.eml, aireuropa/it-29738344.eml, aireuropa/it-5424743.eml, aireuropa/it-5427709.eml, aireuropa/it-5467348.eml, aireuropa/it-7487810.eml, aireuropa/it-7526697.eml, aireuropa/it-7649443.eml, aireuropa/it-7663677.eml, aireuropa/it-7749557.eml, aireuropa/it-7808373.eml, aireuropa/it-7843622.eml, aireuropa/it-8814982.eml, aireuropa/it-8832104.eml, aireuropa/it-9955627.eml";

    public $reSubject = [
        'es' => ['Su tarjeta de embarque Air Europa'],
        'it' => ['La sua carta dimbarco Air Europa'],
        'fr' => ['Carte Dembarquement Air Europa'],
        'nl' => ['Uw Air Europa instapkaart'],
        'de' => ['Sie Ihre Bordkarte Air Europa'],
        'en' => ['Your Air Europa boarding pass'],
    ];

    public $langDetectors = [
        'es' => ['Vuelo'],
        'it' => ['Dimensione Consentita'],
        'fr' => ['Heure de départ'],
        'nl' => ['Vertrektijd'],
        'de' => ['Abflugzeit'],
        'en' => ['Booking Reference'],
    ];

    public $pdfPattern = '[A-Z\d]+_[A-Z\s]+_[A-Z\s]+.pdf';

    public static $dictionary = [
        'es' => [],
        'it' => [
            'Localizador'       => 'Codice PNR',
            'Número de Billete' => 'Numero di biglietto',
            'Fecha Salida'      => ['Data di partenza', 'Data di'],
            'Fecha Llegada'     => 'Data di arrivo',
            'H. Llegada'        => 'Ora di arrivo',
            'Terminal Lleg.'    => 'Terminal di arrivo',
            'Vuelo'             => 'Volo',
            //			'Operado por:' => '',
            'Viajero Frecuente' => 'Frequent Flyer',
        ],
        'fr' => [
            'Localizador'       => 'Référence',
            'Número de Billete' => 'Numéro de billet',
            'Fecha Salida'      => "Date d'aller",
            'Fecha Llegada'     => "Date d'arrivée",
            'H. Llegada'        => "Heure d'arrivée",
            'Terminal Lleg.'    => "Terminal d'arrivée",
            'Vuelo'             => 'Vol',
            //			'Operado por:' => '',
            'Viajero Frecuente' => 'Frequent Flyer',
        ],
        'nl' => [
            'Localizador'       => 'Boekingscode',
            'Número de Billete' => 'Ticketnummer',
            'Fecha Salida'      => 'Vertrekdatum',
            'Fecha Llegada'     => 'Aankomstdatum',
            'H. Llegada'        => 'H. Aankomsttijd',
            'Terminal Lleg.'    => 'Aankomst terminal',
            'Vuelo'             => 'Vlucht',
            //			'Operado por:' => '',
            'Viajero Frecuente' => 'Frequent flyer no.',
        ],
        'de' => [
            'Localizador'       => 'Reservierungsnummer',
            'Número de Billete' => 'Ticketnummer',
            'Fecha Salida'      => 'Abflugdatum',
            'Fecha Llegada'     => 'Ankunftsdatum',
            'H. Llegada'        => 'Ankunftszeit',
            'Terminal Lleg.'    => 'Ankunftsterminal',
            'Vuelo'             => 'Flugnummer',
            'Operado por:'      => 'Operated by:',
            'Viajero Frecuente' => 'Vielfliegernummer',
        ],
        'en' => [
            'Localizador'       => 'Booking Reference',
            'Número de Billete' => 'Ticket Number',
            'Fecha Salida'      => 'Departure Date',
            'Fecha Llegada'     => 'Arrival Date',
            'H. Llegada'        => 'Arrival Time',
            'Terminal Lleg.'    => 'A. Terminal',
            'Vuelo'             => 'Flight',
            'Operado por:'      => 'Operated by:',
            'Viajero Frecuente' => 'Frequent Flyer',
        ],
    ];

    public $lang = '';

    public function parsePdf(&$itineraries)
    {
        $text = $this->text;

        // split by dotted line
        $pos = [0, mb_strlen($this->re("#\n(.*)" . $this->opt($this->t("Fecha Salida")) . "#", $text), 'UTF-8')];
        $rows = explode("\n", $text);
        arsort($pos);
        $parts = [];

        foreach ($rows as $row) {
            foreach ($pos as $k => $p) {
                $parts[$k][] = mb_substr($row, $p, null, 'UTF-8');
                $row = mb_substr($row, 0, $p, 'UTF-8');
            }
        }
        ksort($parts);

        foreach ($parts as &$part) {
            $part = implode("\n", $part);
        }

        $it = [];
        $it['Kind'] = 'T';

        // RecordLocator
        $it['RecordLocator'] = $this->re("#" . $this->t("Localizador") . "\s+(\w+)#ms", $parts[1]);

        // Passengers
        $passengerRow = str_replace("\n", " ", $this->re("#\n(\S.*?)\n\n#ms", $parts[1]));
        $passengerRowParts = preg_split('/[ ]{2,}/', $passengerRow);
        $it['Passengers'][] = $passengerRowParts[0];

        // TicketNumbers
        $it['TicketNumbers'][] = $this->re("#" . $this->t("Número de Billete") . "\s+(\w+)#", $parts[1]);

        $itsegment = [];

        $depTableText = '';
        $arrTableText = '';

        // AirlineName
        // FlightNumber
        $pattern = '/'
            . $this->t('Vuelo') . '[^\n]*$'
            . '\s*^[ ]*(?<airline>[A-Z][A-Z\d]|[A-Z\d][A-Z])(?<flightNumber>\d+)(?:$|[\w\s\n]+$)'
            . '\s+^(.+?)$'
            . '\s+^([ ]*' . $this->opt(array_merge((array) $this->t("Fecha Llegada"), (array) $this->t("H. Llegada"), (array) $this->t("Terminal Lleg."))) . '[^\n]*$'
            . '\s+^.+?)$'
            . '\s+^[ ]*' . $this->t("Número de Billete")
            . '/ms';

        if (preg_match($pattern, $parts[1], $matches)) {
            $itsegment['AirlineName'] = $matches['airline'];
            $itsegment['FlightNumber'] = $matches['flightNumber'];
            $depTableText = $matches[3];
            $arrTableText = $matches[4];
        }

        //parse dep table
        $rows = explode("\n", $depTableText);

        if (count($rows) < 2) {
            $this->logger->info("incorrect dep rows count");

            return;
        }

        $pos = array_unique(array_merge($this->TableHeadPos($rows[0]), $this->TableHeadPos($rows[1])));
        sort($pos);
        $pos = array_merge([], $pos);
        unset($rows[0], $rows[1]);
        $depTable = $this->splitCols(implode("\n", $rows), $pos);

        if (count($depTable) < 6) {
            $this->logger->info("incorrect depTable parse");

            return;
        }

        //parse arr table
        $rows = explode("\n", $arrTableText);

        if (count($rows) < 2) {
            $this->logger->info("incorrect arr rows count");

            return;
        }

        $pos = array_unique(array_merge($this->TableHeadPos($rows[0]), $this->TableHeadPos($rows[1])));
        sort($pos);
        $pos = array_merge([], $pos);
        unset($rows[0], $rows[1]);
        $arrTable = $this->splitCols(implode("\n", $rows), $pos);

        if (count($arrTable) < 3) {
            $this->logger->info("incorrect arrTable parse");

            return;
        }

        // DepCode
        $itsegment['DepCode'] = $this->re("#\n([A-Z]{3})\s+[A-Z]{3}\n#", $parts[1]);

        // DepName
        // DepartureTerminal
        if (preg_match('/^\s*([A-Z\d]+|-)/', $depTable[3], $matches)) {
            $itsegment['DepartureTerminal'] = $matches[1];
        } elseif (preg_match('/\d{1,2}:\d{2}\s+([A-Z\d]+|-)/', $depTable[2], $matches)) {
            $itsegment['DepartureTerminal'] = $matches[1];
        } elseif (preg_match('/\s+([A-Z\d]+|-)\s+Terminal/', $depTable[3], $matches)) {
            $itsegment['DepartureTerminal'] = $matches[1];
        } elseif (preg_match('/terminal\s+([A-Z\d]+|-)\s+\w+/', $depTable[3], $matches)) {
            $itsegment['DepartureTerminal'] = $matches[1];
        }

        //		if(!$itsegment['DepartureTerminal'] = trim($depTable[3]))
        //			$itsegment['DepartureTerminal'] = $this->re("#\d+:\d+\s+([A-Z\d]+)#", $depTable[2]);

        // DepDate
        $itsegment['DepDate'] = strtotime($this->normalizeDate(trim($depTable[0]) . ', ' . trim($depTable[2])));

        // ArrCode
        $itsegment['ArrCode'] = $this->re("#\n[A-Z]{3}\s+([A-Z]{3})\n#", $parts[1]);

        // ArrName
        // ArrivalTerminal
        if (preg_match('/^\s*([A-Z\d]+|-)/', $arrTable[2], $matches)) {
            $itsegment['ArrivalTerminal'] = $matches[1];
        } elseif (preg_match('/\d{1,2}:\d{2}\s+([A-Z\d]+|-)/', $arrTable[1], $matches)) {
            $itsegment['ArrivalTerminal'] = $matches[1];
        }

        //		if(!$itsegment['ArrivalTerminal'] = trim($arrTable[2])) {
        //			$itsegment['ArrivalTerminal'] = $this->re("#\d+:\d+\s+([A-Z\d]+)#", $arrTable[1]);
        //		}

        // ArrDate
        if (preg_match("#(\d{1,2}:\d{2})#", $arrTable[1], $m)) {
            $itsegment['ArrDate'] = strtotime($this->normalizeDate(trim($arrTable[0]) . ', ' . trim($m[1])));
        }

        // Operator
        if ($operator = $this->re("#" . $this->t("Operado por:") . "\s*([^)]+?)(?:[ ]{2}|\)|$)#m", $parts[1])) {
            $itsegment['Operator'] = $operator;
        }

        // Cabin
        $itsegment['Cabin'] = $this->re("#" . $this->t("Viajero Frecuente") . ".*?([^\d\s]+)\n#", $parts[1]);

        // Seats
        $itsegment['Seats'][] = trim($depTable[5]);

        $finded = false;

        foreach ($itineraries as $key => $iter) {
            if (isset($it['RecordLocator']) && $iter['RecordLocator'] == $it['RecordLocator']) {
                if (isset($it['Passengers'])) {
                    if (isset($iter['Passengers'])) {
                        $itineraries[$key]['Passengers'] = array_merge($iter['Passengers'], $it['Passengers']);
                    } else {
                        $itineraries[$key]['Passengers'] = $it['Passengers'];
                    }
                }

                if (isset($it['TicketNumbers'])) {
                    if (isset($iter['TicketNumbers'])) {
                        $itineraries[$key]['TicketNumbers'] = array_merge($iter['TicketNumbers'], $it['TicketNumbers']);
                    } else {
                        $itineraries[$key]['TicketNumbers'] = $it['TicketNumbers'];
                    }
                }
                $finded2 = false;

                foreach ($iter['TripSegments'] as $key2 => $value) {
                    if ($itsegment['FlightNumber'] == $value['FlightNumber'] && $itsegment['DepDate'] == $value['DepDate']) {
                        $itineraries[$key]['TripSegments'][$key2]['Seats'] = array_merge($value['Seats'], $itsegment['Seats']);
                        $finded2 = true;
                    }
                }

                if ($finded2 == false) {
                    $itineraries[$key]['TripSegments'] = $itsegment;
                }
                $finded = true;
            }
        }

        if ($finded == false) {
            $it['TripSegments'][] = $itsegment;
            $itineraries[] = $it;
        }
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@air-europa.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->reSubject as $phrases) {
            foreach ($phrases as $phrase) {
                if (stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
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

        $from = $parser->getHeader('from');
        $subject = $parser->getHeader('subject');

        $condition1 = self::detectEmailFromProvider($from) || self::detectEmailByHeaders(['from' => $from, 'subject' => $subject]);

        if (stripos($text, 'aireuropa.com') === false && $condition1 === false) {
            return false;
        }

        return $this->assignLang($text);
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getHeader('date'));

        $this->http->FilterHTML = false;
        $itineraries = [];

        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        if (!isset($pdfs[0])) {
            return false;
        }

        foreach ($pdfs as $pdf) {
            if (($this->text = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
                continue;
            }

            if ($this->assignLang($this->text) === false) {
                continue;
            }

            $this->parsePdf($itineraries);
        }

        $result = [
            'emailType'  => 'BoardingPassPdf' . ucfirst($this->lang),
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

    private function assignLang($text)
    {
        foreach ($this->langDetectors as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if (strpos($text, $phrase) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function t($word)
    {
        // $this->logger->info($word);
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($str)
    {
        // $this->logger->info($str);
        $year = date("Y", $this->date);
        $in = [
            "#^(\d+)([^\d\s]+),\s+(\d+:\d+)(\s+\d)?$#", // 02jul, 13:30 1
            "#^(\d+)([^\d\s]+)\s*\d+:\d+,\s+(\d+:\d+)$#", // 02jul 13:30, 13:30
        ];
        $out = [
            "$1 $2 $year, $3",
            "$1 $2 $year, $3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            } elseif ($en = MonthTranslate::translate($m[1], 'es')) {
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

    private function TableHeadPos($row)
    {
        $head = array_filter(array_map('trim', explode("|", preg_replace(["#\s{2,}#", "#(\s[a-z]+)\s([A-Z])#"], ["|", "$1|$2"], $row))));
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

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) { return preg_quote($s, '/'); }, $field)) . ')';
    }
}
