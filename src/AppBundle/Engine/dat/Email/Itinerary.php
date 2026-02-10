<?php

namespace AwardWallet\Engine\dat\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Itinerary extends \TAccountChecker
{
    public $mailFiles = "dat/it-10806810.eml, dat/it-10914988.eml, dat/it-11076683.eml, dat/it-11171784.eml, dat/it-8557254.eml, dat/it-92059076.eml";

    protected $reFrom = '@dat.dk';
    protected $reSubject = [
        'DAT – Billet og kvittering',
        'DAT – Kvittering og rejseplan',
        'DAT – Receipt and itinerary',
        'Elektronisk billet og kvittering fra Danish Air Transport',
        'DAT – Rejseplan og kvittering – ref.',
        'DAT – Conferma Prenotazione – ref',
    ];

    protected $reBody = '.dat.dk';

    protected $reBodyPdf = [
        'en' => ['itinerary' => 'Travel Information', 'receipt' => 'Payment details'],
        'it' => ['itinerary' => 'Itinerario', 'receipt' => 'Riepilogo del pagamento'],
        'da' => ['itinerary' => 'Billet', 'receipt' => 'Kvittering'],
    ];

    protected $reBodyHtml = [
        'da'  => ['FRA', 'TIL'],
        'da2' => ['Rute', 'Tid'],
        'en'  => ['Routing', 'Time'],
        'it'  => ['FA', 'PER'],
    ];

    protected $reservationDate;
    protected $total;

    protected $lang = '';
    protected static $dict = [
        'it' => [
            // Html
            'Passager' => ['passeggero', 'PASSEGGERO'],
            'FRA'      => 'FA',
            'TIL'      => 'PER',
            //            			'Rute' => '',
            'Tid' => 'Orario',

            // PDF
            'itinerary'          => 'itinerario',
            'Itinerary'          => ['Itinerario', 'Biglietto Elettronico'],
            'Passenger name'     => 'Nome del passeggero',
            'Travel Information' => 'Itinerario',
            'Payment details'    => 'Riepilogo del pagamento',
            'Total'              => 'Totale pagato',
            'Fare Price'         => 'Tariffa',
            'Farebasis'          => 'Descrizione della tariffa',
        ],
        'da' => [
            // Html
            'Passager' => ['Passager', 'PASSAGER'],
            //			'FRA' => '',
            //			'TIL' => '',
            //			'Rute' => '',
            //			'Tid' => '',

            // PDF
            'itinerary'             => 'itinerario',
            'Itinerary'             => ['Billet'],
            'Passenger name'        => 'Passager',
            'Travel Information'    => 'Rejseplan',
            'Payment details'       => 'Betaling',
            'Total'                 => 'Total',
            'Fare Price'            => 'Fare Price',
            'Restrictions'          => 'Billetregler',
        ],
        'en' => [
            // Html
            'Passager' => ['Passenger'],
            //			'FRA' => '',
            //			'TIL' => '',
            'Rute' => 'Routing',
            'Tid'  => 'Time',

            // PDF
            //            'itinerary'          => '',
            //            'Itinerary'          => [''],
            //            'Passenger name'     => '',
            'Travel Information' => 'Travel Information',
            //            'Payment details'    => '',
            //            'Total'              => '',
            //            'Fare Price'         => '',
            //            'Restrictions'          => '',
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $type = '';
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        if (count($pdfs) > 0) {
            foreach ($pdfs as $pdf) {
                $pdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

                foreach ($this->reBodyPdf as $lang => $reBody) {
                    if (isset($reBody['itinerary']) && stripos($pdf, $reBody['itinerary']) !== false
                        && isset(self::$dict[$lang]['Travel Information']) && stripos($pdf, self::$dict[$lang]['Travel Information']) !== false
                    ) {
                        $this->lang = $lang;
                        $this->parsePdfItinerary($email, $pdf, $its);
                        $type = 'Pdf';

                        continue 2;
                    }

                    if (isset($reBody['receipt']) && stripos($pdf, $reBody['receipt']) !== false) {
                        $this->lang = $lang;
                        $this->parsePdfReceipt($pdf);
                        $type = 'Pdf';

                        continue 2;
                    }
                }
            }
        } else {
            $type = 'Html';
            $this->parseHtml($email);
        }

        if (!empty($this->total)) {
            // TotalCharge
            if (isset($this->total['Total'])) {
                $email->price()
                    ->total($this->total['Total']);
            }
            // BaseFare
            if (isset($this->total['Fare'])) {
                $email->price()
                    ->cost($this->total['Fare']);
            }
            // Currency
            if (isset($this->total['Currency'])) {
                $email->price()
                    ->currency($this->total['Currency']);
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . $type . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->reSubject as $re) {
            if (stripos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (stripos($textPdf, $this->reBody) === false) {
                continue;
            }

            foreach ($this->reBodyPdf as $reBody) {
                foreach ($reBody as $re) {
                    if (stripos($textPdf, $re) !== false) {
                        return true;
                    }
                }
            }
        }

        if ($this->http->XPath->query("//a[contains(@href,'.dat.com')]")->length > 0) {
            foreach ($this->reBodyHtml as $lang => $value) {
                if ($this->http->XPath->query("//text()[normalize-space() = '{$value[0]}']/ancestor::tr[1][contains(normalize-space(.),'{$value[1]}')]")->length > 0) {
                    $this->lang = substr($lang, 0, 2);
                }
            }

            return true;
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return 3 * count(self::$dict); //pdf, table 1, table 2
    }

    public function findAirportCodes($flight)
    {
        if (empty($flight)) {
            return null;
        }

        $row = $this->http->FindNodes("//tr[*[{$this->eq($flight)}]]/*");

        if ($this->http->XPath->query("//tr/*[{$this->eq($this->t('Rute'))}]")->length > 0) {
            $column = $this->http->XPath->query("//tr/*[{$this->eq($this->t('Rute'))}]/preceding-sibling::*")->length;

            if (preg_match("/^\s*([A-Z]{3})\s*-\s*([A-Z]{3})\s*$/", $row[$column] ?? '', $m)) {
                $codes = [$m[1], $m[2]];

                return $codes;
            }
        }

        if ($this->http->XPath->query("//tr[*[{$this->eq($this->t('FRA'))}] and *[{$this->eq($this->t('TIL'))}]]")->length > 0) {
            $codes = [];
            $column1 = $this->http->XPath->query("//tr/*[{$this->eq($this->t('FRA'))}]/preceding-sibling::*")->length;

            if (preg_match("/^\s*([A-Z]{3})\s*$/", $row[$column1] ?? '', $m)) {
                $codes[] = $m[1];
            }
            $column2 = $this->http->XPath->query("//tr/*[{$this->eq($this->t('TIL'))}]/preceding-sibling::*")->length;

            if (preg_match("/^\s*([A-Z]{3})\s*$/", $row[$column2] ?? '', $m)) {
                $codes[] = $m[1];
            }

            if (count($codes) == 2) {
                return $codes;
            }
        }

        return null;
    }

    protected function parsePdfItinerary(Email $email, $text, &$its)
    {
        $f = $email->add()->flight();

        // RecordLocator
        if (preg_match("#(?:Booking reference|Record locator|Cod. Prenotazione)\:\s+([A-Z\d]{5,7})#u", $text, $m)) {
            $f->general()
                ->confirmation($m[1]);
        }

        $passPosBegin = strpos($text, "{$this->t('Passenger name')}");
        $passPosEnd = strpos($text, "{$this->t('Travel Information')}");

        if (empty($passPosEnd)) {
            $passPosEnd = strpos($text, "{$this->t('Travel information')}");
        }

        if (!empty($passPosBegin) && !empty($passPosEnd)) {
            $pass = substr($text, $passPosBegin, $passPosEnd - $passPosBegin);

            if (preg_match_all('#\n\s*(\S[\w\- \.]+?)\s{5,}([\d\-]{5,})#u', $pass, $m)) {
                $Passengers = array_map('trim', $m[1]);
                $TicketNumbers = $m[2];
            }
        }

        if (isset($RecordLocator) && !empty($RecordLocator)) {
            $f->general()
                ->confirmation($RecordLocator);
        }

        if (isset($Passengers)) {
            $f->general()
                ->travellers($Passengers, true);
        }

        if (isset($TicketNumbers)) {
            $f->setTicketNumbers($TicketNumbers, false);
        }

        if (preg_match("#{$this->opt($this->t('Itinerary'))}\s+(?:Issue date|Date|Data di emissione|Bestilt dato)?\s*:\s+(.+)#u", $text, $m)) {
            $this->reservationDate = strtotime($this->normalizeDate($m[1]));
        }

        if (empty($this->reservationDate)) {
            $this->logger->info("date not found");

            return [];
        }

        $flPosBegin = $passPosEnd;
        $flPosEnd = strpos($text, "{$this->t('Special Service Request')}");

        if (empty($flPosEnd)) {
            $flPosEnd = strpos($text, "{$this->t('Restrictions')}");
        }

        if (empty($flPosEnd)) {
            $flPosEnd = strpos($text, "{$this->t('Farebasis')}");
        }

        $flights = [];

        if (!empty($flPosBegin) && !empty($flPosEnd)) {
            $flightText = substr($text, $flPosBegin, $flPosEnd - $flPosBegin);
            preg_match_all('#\n\s*([A-Z\d]{2})(\d{1,5})\s*/\s*(\d{1,2}[^\d\s]{3})\s+(.+)\s*-\s*(.+)\s+(\d{1,2}:\d{2})\s+(\d{1,2}:\d{2})#', $flightText, $flights);
        }

        if (isset($flights[0]) && count($flights[0]) == 0) {
            $flightRows = $this->splitter("#\n\s*([A-Z\d]{2}\d{1,5}\s*[/])#u", $flightText);

            if (count($flightRows) == 0) {
                return null;
            }

            foreach ($flightRows as $flightRow) {
                if (stripos($flightRow, "Itinerario") !== false) {
                    continue;
                }
                $flightTable = $this->splitCols($flightRow);
                $s = $f->addSegment();

                $s->airline()
                    ->number($this->re("/^[A-Z\d]{2}(\d{2,4})/", $flightTable[0]))
                    ->name($this->re("/^([A-Z\d]{2})/", $flightTable[0]));

                $date = $this->re("#[/](\S+)#", $flightTable[0]);

                $s->departure()
                    ->name(trim(str_replace("\n", " ", $flightTable[1])))
                    ->date(strtotime($this->normalizeDate($date . ' ' . $flightTable[3])));

                $s->arrival()
                    ->name(trim(str_replace("\n", " ", $flightTable[2])))
                    ->date(strtotime($this->normalizeDate($date . ' ' . $flightTable[4])));

                $codes = $this->findAirportCodes($s->getAirlineName() . $s->getFlightNumber());

                if (!empty($codes[0])) {
                    $s->departure()
                        ->code($codes[0]);
                } else {
                    $s->departure()
                        ->noCode();
                }

                if (!empty($codes[1])) {
                    $s->arrival()
                        ->code($codes[1]);
                } else {
                    $s->arrival()
                        ->noCode();
                }
            }
        } elseif (isset($flights[0]) && count($flights[0]) > 0) {
            foreach ($flights[0] as $key => $flight) {
                $s = $f->addSegment();

                $date = $flights[3][$key];

                $s->airline()
                    ->number($flights[2][$key])
                    ->name($flights[1][$key]);

                $s->departure()
                    ->name(trim($flights[4][$key]));

                $depDate = strtotime($this->normalizeDate($date . ' ' . $flights[6][$key]));

                if ($depDate < $this->reservationDate) {
                    $depDate = strtotime("+1year", $depDate);
                }
                $s->departure()
                    ->date($depDate);

                $s->arrival()
                    ->name(trim($flights[5][$key]));

                $arrDate = strtotime($this->normalizeDate($date . ' ' . $flights[7][$key]));

                if ($arrDate < $this->reservationDate) {
                    $arrDate = strtotime("+1year", $arrDate);
                }
                $s->arrival()
                    ->date($arrDate);

                $codes = $this->findAirportCodes($s->getAirlineName() . $s->getFlightNumber());

                if (!empty($codes[0])) {
                    $s->departure()
                        ->code($codes[0]);
                } else {
                    $s->departure()
                        ->noCode();
                }

                if (!empty($codes[1])) {
                    $s->arrival()
                        ->code($codes[1]);
                } else {
                    $s->arrival()
                        ->noCode();
                }
            }
        }
    }

    protected function parsePdfReceipt($text)
    {
        if (preg_match("#{$this->t('Payment details')}\s+([A-Z]{3})#u", $text, $m)) {
            $this->total['Currency'] = $m[1];
        }

        if (preg_match("#{$this->t('Fare Price')}\s+(\d[\d., ]+)#", $text, $m)) {
            $this->total['Fare'] = $this->normalizePrice($m[1]);
        }

        if (preg_match("#{$this->t('Total')}:?\s+(\d[\d., ]+)#", $text, $m)) {
            $this->total['Total'] = $this->normalizePrice($m[1]);
        }
    }

    protected function parseHtml(Email $email)
    {
        foreach ($this->reBodyHtml as $lang => $value) {
            if ($this->http->XPath->query("//text()[normalize-space() = '{$value[0]}']/ancestor::tr[1][contains(normalize-space(.),'{$value[1]}')]")->length > 0) {
                $this->lang = substr($lang, 0, 2);
            }
        }

        $f = $email->add()->flight();

        $confirmation = $this->http->FindSingleNode("//text()[starts-with(normalize-space(),'Booking reference')][1]", null, true, "#:\s*([A-Z\d]{5,7})\b#");

        if (empty($confirmation)) {
            $confirmation = $this->http->FindSingleNode("(//text()[contains(normalize-space(),'Booking reference')])[1]/following::text()[normalize-space()][1]", null, true, "#\b([A-Z\d]{5,7})\b#");
        }
        $f->general()
            ->confirmation($confirmation);

        // Passengers
        $count = 1 + count($this->http->FindNodes("//text()[" . $this->eq($this->t('Passager')) . "]/ancestor::*[local-name()='td' or local-name()='th'][1]/preceding-sibling::*[local-name()='td' or local-name()='th']"));
        $pax = array_unique($this->http->FindNodes("//text()[" . $this->eq($this->t('Passager')) . "]/ancestor::tr[1]/following-sibling::tr/*[local-name()='td' or local-name()='th'][{$count}]"));
        $f->general()
            ->travellers($pax, true);

        //FLY 	DATO 	TID 	FRA 	TIL 	PASSAGER
        $xpath = "//text()[" . $this->eq($this->t('FRA')) . "]/ancestor::tr[1][" . $this->contains($this->t('TIL')) . "]/following-sibling::tr";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $s = $f->addSegment();
            $date = $this->http->FindSingleNode("./td[2]", $root);

            // Airline
            $node = $this->http->FindSingleNode("./td[1]", $root);

            if (preg_match("#([A-Z\d]{2})(\d{1,5})\b#", $node, $m)) {
                $s->airline()
                    ->number($m[2])
                    ->name($m[1]);
            }
            // Departure
            $s->departure()
                ->code($this->http->FindSingleNode("./td[4]", $root, true, "#^\s*([A-Z]{3})\s*$#"))
                ->date(strtotime($this->normalizeDate($date . ' ' . $this->http->FindSingleNode("./td[3]", $root, true, "#^\s*(\d{1,2}:\d{2})\s*-\s*#"))));

            // Arrival
            $s->arrival()
                ->code($this->http->FindSingleNode("./td[5]", $root, true, "#^\s*([A-Z]{3})\s*$#"))
                ->date(strtotime($this->normalizeDate($date . ' ' . $this->http->FindSingleNode("./td[3]", $root, true, "#\s*-\s*(\d{1,2}:\d{2})\s*$#"))));
        }

        //Dato 	Fly 	Rute 	Tid 	Passager
        $xpath = "//text()[" . $this->eq($this->t('Rute')) . "]/ancestor::tr[1][" . $this->contains($this->t('Tid')) . "]/following-sibling::tr";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $s = $f->addSegment();
            $date = $this->http->FindSingleNode("./td[1]", $root);

            // AirLine
            $node = $this->http->FindSingleNode("./td[2]", $root);

            if (preg_match("#([A-Z\d]{2})(\d{1,5})\b#", $node, $m)) {
                $s->airline()
                    ->number($m[2])
                    ->name($m[1]);
            }

            // Departure
            $s->departure()
                ->code($this->http->FindSingleNode("./td[3]", $root, true, "#^\s*([A-Z]{3})\s*-\s*#"))
                ->date(strtotime($this->normalizeDate($date . ' ' . $this->http->FindSingleNode("./td[4]", $root, true, "#^\s*(\d{1,2}:\d{2})\s*-\s*#"))));

            // Arrival
            $s->arrival()
                ->code($this->http->FindSingleNode("./td[3]", $root, true, "#\s*-\s*([A-Z]{3})\s*$#"))
                ->date(strtotime($this->normalizeDate($date . ' ' . $this->http->FindSingleNode("./td[4]", $root, true, "#\s*-\s*(\d{1,2}:\d{2})\s*$#"))));
        }
    }

    protected function normalizePrice($string = '')
    {
        if (empty($string)) {
            return $string;
        }
        $string = preg_replace('/\s+/', '', $string);			// 11 507.00	->	11507.00
        $string = preg_replace('/[,.](\d{3})/', '$1', $string);	// 2,790		->	2790		or	4.100,00	->	4100,00
        $string = preg_replace('/,(\d{2})$/', '.$1', $string);	// 18800,00		->	18800.00

        return (float) $string;
    }

    protected function t($phrase)
    {
        if (!isset(self::$dict[$this->lang]) || !isset(self::$dict[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dict[$this->lang][$phrase];
    }

    protected function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'starts-with(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    protected function contains($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'contains(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    protected function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
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

    private function normalizeDate($str)
    {
        //$this->logger->error('IN-'.$str);
        $year = date("Y", $this->reservationDate);
        $in = [
            "#^(\d+)\s*([^\d\s]+)\s*(\d{4})$#", //19OCT2015
            "#^(\d+)\s*([^\d\s]+)\s+(\d{1,2}:\d{2})$#", //19OCT 20:15
        ];
        $out = [
            "$1 $2 $3",
            "$1 $2 $year $3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }
        //$this->logger->error('OUT-'.$str);
        return $str;
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function splitCols($text, $pos = false)
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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }
}
