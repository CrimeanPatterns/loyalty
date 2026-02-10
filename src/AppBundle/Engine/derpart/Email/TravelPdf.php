<?php

namespace AwardWallet\Engine\derpart\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;

class TravelPdf extends \TAccountChecker
{
    public $mailFiles = "derpart/it-11176598.eml, derpart/it-12133879.eml, derpart/it-2444415.eml, derpart/it-2485039.eml, derpart/it-2787034.eml";

    public $reFrom = ["derpart.com", "@der.com"];
    public $reBody = [
        'de' => ['Reservierungsnummer', 'Flug'],
    ];
    public $detectEmailInPDF = [
        'www.derbusinesstravel.com',
        '@derpart.com',
    ];
    public $reSubject = [
        'Reisebestätigung für',
        'Reiseplan für',
    ];
    public $lang = '';
    public $date;
    public $pdf;
    public $pdfNamePattern = ".*pdf";
    public static $dict = [
        'de' => [
            'ff'          => 'Vielflieger',
            'pax'         => 'Reisedaten für',
            'tripNum'     => 'Reservierungsnummer',
            'pnr'         => ['Airlinebuchungscode', 'Airline-Buchungsnr.'],
            'Flight'      => 'Flug',
            'segmentsReg' => "#(Flug\s+Datum\s+Von)\s+#",
            'operated by' => 'durchgeführt von',
            'Entry'       => 'Buchung',
            'Date'        => 'Datum',
            'Duration'    => 'Flugdauer',
            'Class'       => 'Klasse',
            'Departure'   => 'Abflug',
            'Arrival'     => 'Ankunft',
            'Seat'        => 'Sitzplatz',
            'Aircraft'    => 'Flugzeug',
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getDate());

        $text = '';
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdfs) && count($pdfs) > 0) {
            $this->pdf = clone $this->http;

            foreach ($pdfs as $pdf) {
                if (($text .= \PDF::convertToText($parser->getAttachmentBody($pdf))) !== null) {
                } else {
                    return null;
                }
            }
        } else {
            return null;
        }

        $this->AssignLang($text);

        $its = $this->parseEmail($text);
        $a = explode('\\', __CLASS__);

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => $a[count($a) - 1] . ucfirst($this->lang),
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdf = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdf[0])) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf[0]));
            $flg = false;

            foreach ($this->detectEmailInPDF as $email) {
                if (stripos($text, $email) !== false) {
                    $flg = true;
                }
            }

            if ($flg) {
                return $this->AssignLang($text);
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        $flg = false;

        if (isset($headers['from'])) {
            foreach ($this->reFrom as $reFrom) {
                if (stripos($headers['from'], $reFrom) !== false) {
                    $flg = true;
                }
            }
        }

        if ($flg && isset($this->reSubject)) {
            foreach ($this->reSubject as $reSubject) {
                if (stripos($headers["subject"], $reSubject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->reFrom as $reFrom) {
            if (stripos($from, $reFrom) !== false) {
                return true;
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    public function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    protected function splitter($regular, $text)
    {
        $result = [];

        $array = preg_split($regular, $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        array_shift($array);

        for ($i = 0; $i < count($array) - 1; $i += 2) {
            $result[] = $array[$i] . $array[$i + 1];
        }

        return $result;
    }

    private function parseEmail($textPDF)
    {
        // it's important delete all extended info after reservations in file
        if (($pos = strpos($textPDF, "\n-------------")) !== false) {
            $textPDF = strstr($textPDF, "\n-------------", true);
        }

        if (($pos = strpos($textPDF, "\n*-------------")) !== false) {
            $textPDF = strstr($textPDF, "\n*-------------", true);
        }

        $resDate = $this->normalizeDate($this->re("#{$this->opt($this->detectEmailInPDF)}\s+{$this->opt($this->t('Date'))}:\s*([^\n]+)#", $textPDF));

        if ($resDate !== false) {
            $this->date = $resDate;
        }

        $acc = [];

        if (preg_match_all("#{$this->opt($this->t('ff'))}:\s*(.+?)\n#", $textPDF, $m)) {
            $acc = $m[1];
        }

        $pax = [];

        if (preg_match_all("#{$this->opt($this->t('pax'))}:\s*(.+?)(?:\s{2,}|\n)#", $textPDF, $m)) {
            $pax = $m[1];
        }

        $tripNum = $this->re("#{$this->opt($this->t('tripNum'))}:\s*([A-Z\d]{5,})#", $textPDF);

        $airlinesPNR = [];
        $pnrText = $this->re("#{$this->opt($this->t('pnr'))}:\s*(.+?)\s+{$this->opt($this->t('Flight'))}#s", $textPDF);

        if (preg_match_all("#([A-Z\d]{2})\s*\/\s*([A-Z\d]{5,})#", $pnrText, $m, PREG_SET_ORDER)) {
            foreach ($m as $v) {
                $airlinesPNR[$v[1]] = $v[2];
            }
        }
        $ticketsPNR = [];

        if (preg_match_all("#TICKET\s+([A-Z\d]{2}\s+[^\n]+)#i", $textPDF, $m)) {
            $str = implode(', ', $m[1]);

            if (preg_match_all("#([A-Z\d]{2})\s+([\d ]+)#", $str, $m, PREG_SET_ORDER)) {
                foreach ($m as $v) {
                    $ticketsPNR[$v[1]][] = $v[2];
                }
            }
        }

        $airs = [];
        $its = [];
        $tickets = [];
        $nodes = $this->splitter($this->t('segmentsReg'), $textPDF);

        foreach ($nodes as $sText) {
            $table = $this->re("#\s+Abflug\s+Ankunft[^\n]*\n(.+)#ms", $sText);
            $table = $this->splitCols($table, $this->colsPos($table, 10));

            if (count($table) < 6) {
                $this->http->Log("other format");

                return null;
            }
            $airline = $this->re("#^\s+([A-Z\d]{2})\s*\d+#", $table[0]);

            if (isset($airlinesPNR[$airline])) {
                $airs[$airlinesPNR[$airline]][] = $sText;

                if (isset($ticketsPNR[$airline])) {
                    $tickets[$airlinesPNR[$airline]][] = $ticketsPNR[$airline];
                }
            } else {
                $airs[CONFNO_UNKNOWN] = $sText;
                $tickets[CONFNO_UNKNOWN] = array_merge($tickets, $ticketsPNR[$airline]);
            }
        }

        foreach ($airs as $rl => $sTexts) {
            /** @var \AwardWallet\ItineraryArrays\AirTrip $it */
            $it = ['Kind' => 'T', 'TripSegments' => []];
            $it['RecordLocator'] = $rl;
            $it['Passengers'] = $pax;
            $it['AccountNumbers'] = $acc;
            $it['TripNumber'] = $tripNum;
            $it['ReservationDate'] = $resDate;

            if (isset($tickets[$rl])) {
                $ticket = '';

                foreach ($tickets[$rl] as $t) {
                    $ticket .= ',' . implode(",", $t);
                }
                $tickets[$rl] = explode(',', trim($ticket, ','));
                $it['TicketNumbers'] = array_values(array_unique(array_filter($tickets[$rl])));
            }

            foreach ($sTexts as $sText) {
                /** @var \AwardWallet\ItineraryArrays\AirTripSegment $seg */
                $seg = [];

                $table = $this->re("#\s+{$this->opt($this->t('Departure'))}\s+{$this->opt($this->t('Arrival'))}[^\n]*\n(.+)#ms", $sText);

                $table = $this->splitCols($table, $this->colsPos($table, 10));

                if (preg_match("#{$this->opt($this->t('Class'))}:\s*([A-Z]{1,2})\s*-\s*(.+?)(?:,\s*([^\n]*)|\s*$)#", $sText, $m)) {
                    $seg['BookingClass'] = $m[1];
                    $seg['Cabin'] = $m[2];

                    if (isset($m[3]) && !empty($m[3])) {
                        $it['Status'] = $m[3];
                    }
                }

                if (!empty($s = $this->re("#{$this->opt($this->t('Seat'))}:\s*(\d+\w)#", $sText))) {
                    $seg['Seats'][] = $s;
                }

                if (!empty($s = $this->re("#{$this->opt($this->t('An Bord'))}:\s*([^\n]+)#", $sText))) {
                    $seg['Meal'] = $s;
                }

                $seg['Aircraft'] = $this->re("#{$this->opt($this->t('Aircraft'))}:\s*([^\n]+)#", $sText);

                if (preg_match("#([A-Z\d]{2})\s*(\d+)#", $table[0], $m)) {
                    $seg['AirlineName'] = $m[1];
                    $seg['FlightNumber'] = $m[2];
                }
                $seg['Operator'] = $this->re("#{$this->opt($this->t('operated by'))}\s+([^\n]+)#", $table[0]);

                if (!empty($stops = $this->re("#Stops\s+(.+?)\s+(?:Freigepäck|Info)#si", $sText))) {
                    if (preg_match("#(\n)#", $stops, $m)) {
                        $seg['Stops'] = count($m) - 1;
                    }
                }

                $depDate = null;
                $arrDate = null;

                if (preg_match("#(\w+,\s+\d+[^\n]+)\n(?:\s*\-\s+(\w+,\s+\d+[^\n]+))?\s+{$this->opt($this->t('Entry'))}#u", $table[1], $m)) {
                    $depDate = $this->normalizeDate($m[1]);

                    if (isset($m[2])) {
                        $arrDate = $this->normalizeDate($m[2]);
                    } else {
                        $arrDate = $depDate;
                    }
                }

                $depInfo = preg_replace("#\s+#", ' ', $this->re("#^(.+?)(?:[^\n]+?:|\s{2,})#s", $table[2]));

                if (preg_match("#(.+?)\s*(?:Terminal\s+(.+)|$)#i", $depInfo, $m)) {
                    $seg['DepName'] = $m[1];

                    if (isset($m[2])) {
                        $seg['DepartureTerminal'] = $m[2];
                    }
                }

                $arrInfo = preg_replace("#\s+#", ' ', $this->re("#^(.+?)(?:[^\n]+?:|\s{2,})#s", $table[3]));

                if (empty(trim($arrInfo))) {
                    $arrInfo = preg_replace("#\s+#", ' ', $this->re("#^(.+?)(?:[^\n]+?:|\s{2,})#s", $table[4]));
                }

                if (preg_match("#(.+?)\s*(?:Terminal\s+(.+)|$)#iu", $arrInfo, $m)) {
                    $seg['ArrName'] = trim($m[1]);

                    if (isset($m[2])) {
                        $seg['ArrivalTerminal'] = $m[2];
                    }
                }

                $depTime = $this->re('#^\s*(\d+:\d+(?:\s*[ap]m)?)\s*#i', $table[4]);
                $i = 0;

                if (empty($depTime)) {
                    $depTime = $this->re('#^\s*(\d+:\d+(?:\s*[ap]m)?)\s*#i', $table[5]);
                    $i++;
                }

                $arrTime = $this->re('#^\s*(\d+:\d+(?:\s*[ap]m)?)\s*#i', $table[5 + $i]);

                $seg['DepDate'] = strtotime($depTime, $depDate);

                $seg['ArrDate'] = strtotime($arrTime, $arrDate);

                $seg['Duration'] = $this->re("#{$this->opt($this->t('Duration'))}:\s*([^\n]+)#i", $table[5 + $i]);

                if (!empty($seg['FlightNumber']) && !empty($seg['DepDate']) && !empty($seg['ArrDate'])) {
                    $seg['DepCode'] = $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
                }

                $it['TripSegments'][] = $seg;
            }
            $its[] = $it;
        }

        //		HOTEL
        $textHotel = strstr(stristr($textPDF, 'Hotel'), $this->t('tripNum'), true);

        if (false !== $textHotel) {
            $nodes = $this->splitter('/(Hotel\s+Datum\s+Beschreibung)/', $textHotel);

            foreach ($nodes as $node) {
                $table = $this->re("/\s+Datum\s+Beschreibung[^\n]*\n(.+)\s+Info/ms", $node);
                $table = $this->SplitCols($table, $this->ColsPos($table, 10));
                /** @var \AwardWallet\ItineraryArrays\Hotel $it */
                $it = ['Kind' => 'R'];

                if (3 !== count($table)) {
                    $this->logger->info("Hotel did not found");

                    return null;
                }

                if (preg_match('/(\w+,\s+\d{1,2}[.\s]*\w+)\s*\-\s*(\w+,\s+\d{1,2}[.\s]*\w+)/', $table[0], $m)) {
                    $it['CheckInDate'] = $this->normalizeDate($m[1]);
                    $it['CheckOutDate'] = $this->normalizeDate($m[2]);
                }

                $data = explode("\n", $table[2]);

                foreach (explode("\n", $table[1]) as $i => $row) {
                    $data[$i] = $row . ' ' . $data[$i];
                }

                $data = implode("\n", $data);

                if (preg_match('/Hotelname\s+(?<HotelName>.+)\s+Adresse\s+(?<Address>.+)\s+Tel:\s+(?<Tel>.+)\s+Fax:\s+(?<Fax>.+)\s+Leistung\s+(?<Status>\w+)\s+Bestätigungsnummer\s+(?<Number>\d+)\s+.+\s+Preis\s+(?<Cur>[A-Z]{3})\s+(?<Total>[\d\.]+)/si', $data, $m)) {
                    $it['HotelName'] = $m['HotelName'];
                    $it['Address'] = preg_replace('/\s+/', ' ', $m['Address']);
                    $it['Phone'] = trim($m['Tel']);
                    $it['Fax'] = $m['Fax'];
                    $it['ConfirmationNumber'] = $m['Number'];
                    $it['Currency'] = $m['Cur'];
                    $it['Total'] = $m['Total'];
                    $it['Status'] = $m['Status'];
                }

                $it['GuestNames'] = $pax;

                if (preg_match('/Info\s+.+\s+STORNIERUNGSVORSCHRIFTEN\:\s+(.+\n.+)\n.+\n\s*ROOM,\s+(.+)/', $node, $m)) {
                    $it['CancellationPolicy'] = preg_replace('/\s+/', ' ', $m[1]);
                    $it['RoomType'] = $m[2];
                }
                $its[] = $it;
            }
        }

        return $its;
    }

    private function normalizeDate($date)
    {
        $year = date('Y', $this->date);
        $in = [
            //Do, 08. Mrz
            '#^(\w+),\s+(\d+)\.?\s+(\w+)$#u',
            //19.01.2018 14:54 Uhr
            '#^\s*(\d+)\.(\d+)\.(\d+)\s+(\d+:\d+)\s*Uhr\s*$#',
            //05.02.2015    Zeit: 14:53Uhr
            '#^\s*(\d+)\.(\d+)\.(\d+)\s+Zeit:\s*(\d+:\d+)\s*Uhr\s*$#',
        ];
        $out = [
            '$2 $3 ' . $year,
            '$3-$2-$1 $3',
            '$3-$2-$1 $3',
        ];
        $outWeek = [
            '$1',
            '',
            '',
        ];

        if (!empty($week = preg_replace($in, $outWeek, $date))) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($week, $this->lang));
            $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));
            $str = EmailDateHelper::parseDateUsingWeekDay($str, $weeknum);
        } else {
            $str = strtotime($this->dateStringToEnglish(preg_replace($in, $out, $date)));
        }

        return $str;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function AssignLang($body)
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return "(?:" . preg_quote($s) . ")";
        }, $field)) . ')';
    }

    private function SplitCols($text, $pos = false)
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->rowColsPos($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k => $p) {
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

    private function rowColsPos($row)
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

    private function ColsPos($table, $correct = 5)
    {
        $pos = [];
        $rows = explode("\n", $table);

        foreach ($rows as $row) {
            $pos = array_merge($pos, $this->rowColsPos($row));
        }
        $pos = array_unique($pos);
        sort($pos);
        $pos = array_merge([], $pos);

        foreach ($pos as $i => $p) {
            if (isset($pos[$i], $pos[$i - 1])) {
                if ($pos[$i] - $pos[$i - 1] < $correct) {
                    unset($pos[$i]);
                }
            }
        }
        sort($pos);
        $pos = array_merge([], $pos);

        return $pos;
    }
}
