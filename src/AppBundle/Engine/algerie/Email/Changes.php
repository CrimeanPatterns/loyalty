<?php

namespace AwardWallet\Engine\algerie\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;

class Changes extends \TAccountChecker
{
    public $mailFiles = "algerie/it-12231953.eml, algerie/it-12231956.eml";

    public $reFrom = "@airalgerie.dz";
    public $reBody = [
        'fr'  => ['Référence Réservation', 'Classe'],
        'fr2' => ['REFERENCE RESERVATION', 'Classe'],
    ];
    public $reBodyPDF = [
        'en' => ['e-Ticket Receipt', 'PASSENGER AND TICKET DETAILS'],
    ];
    public $reSubject = [
        'Modifications de votre itinéraire Air Algérie',
        'Confirmation de réservation',
    ];
    public $pdfNamePattern = ".*E\-?Ticket.*pdf";
    public $lang = '';
    public static $dict = [
        'fr' => [
            'Référence Réservation' => ['Référence Réservation', 'REFERENCE RESERVATION'],
        ],
        'en' => [
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $its = [];
        $type = "";
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdfs) && count($pdfs) > 0) {
            foreach ($pdfs as $pdf) {
                if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) !== null) {
                    foreach ($this->reBodyPDF as $lang => $reBody) {
                        if (stripos($text, $reBody[0]) !== false && stripos($text, $reBody[1]) !== false) {
                            $this->lang = $lang;
                            $type = "Pdf";
                            $its[] = $this->parseEmail_PDF($text);

                            break;
                        }
                    }
                }
            }
            $its = $this->mergeItineraries($its, true);
        } else {
            $this->AssignLang();

            if ($this->http->XPath->query("//text()[{$this->eq($this->t('Vol'))}]/ancestor::tr[1][{$this->contains($this->t('Départ'))}][{$this->contains($this->t('Avion'))}]")->length > 0) {//Confirmation
                $its = $this->parseEmail_1();
                $type = "1";
            } else {//Changes
                $its = $this->parseEmail_2();
                $type = "2";
            }
        }
        $a = explode('\\', __CLASS__);

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => $a[count($a) - 1] . ucfirst($this->lang) . $type,
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdfs) && count($pdfs) > 0) {
            foreach ($pdfs as $pdf) {
                if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) !== null) {
                    foreach ($this->reBodyPDF as $lang => $reBody) {
                        if (stripos($text, $reBody[0]) !== false && stripos($text, $reBody[1]) !== false) {
                            return true;
                        }
                    }
                }
            }
        }

        if ($this->http->XPath->query("//a[contains(@href,'airalgerie.dz')] | //text()[contains(.,'Air Algérie')]")->length > 0) {
            return $this->AssignLang();
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], $this->reFrom) !== false && isset($this->reSubject)) {
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
        return stripos($from, $this->reFrom) !== false;
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

    protected function mergeItineraries($its, $sumTotal = false)
    {
        $delSums = false;
        $its2 = $its;

        foreach ($its2 as $i => $it) {
            if ($i && ($j = $this->findRL($i, $it['RecordLocator'], $its)) != -1) {
                foreach ($its[$j]['TripSegments'] as $flJ => $tsJ) {
                    foreach ($its[$i]['TripSegments'] as $flI => $tsI) {
                        if (isset($tsI['FlightNumber']) && isset($tsJ['FlightNumber']) && ((int) $tsJ['FlightNumber'] === (int) $tsI['FlightNumber'])
                            && (isset($tsJ['Seats']) || isset($tsI['Seats']))
                        ) {
                            $new = [];

                            if (isset($tsJ['Seats'])) {
                                $new = array_merge($new, (array) $tsJ['Seats']);
                            }

                            if (isset($tsI['Seats'])) {
                                $new = array_merge($new, (array) $tsI['Seats']);
                            }
                            $its[$j]['TripSegments'][$flJ]['Seats'] = array_values(array_filter(array_unique($new)));
                            $its[$i]['TripSegments'][$flI]['Seats'] = array_values(array_filter(array_unique($new)));
                        }
                    }
                }

                $its[$j]['TripSegments'] = array_merge($its[$j]['TripSegments'], $its[$i]['TripSegments']);
                $its[$j]['TripSegments'] = array_map('unserialize',
                    array_unique(array_map('serialize', $its[$j]['TripSegments'])));

                $mergeFields = ['Passengers', 'AccountNumbers', 'TicketNumbers'];

                foreach ($mergeFields as $mergeField) {
                    if (isset($its[$j][$mergeField]) || isset($its[$i][$mergeField])) {
                        $new = [];

                        if (isset($its[$j][$mergeField])) {
                            $new = array_merge($new, $its[$j][$mergeField]);
                        }

                        if (isset($its[$i][$mergeField])) {
                            $new = array_merge($new, $its[$i][$mergeField]);
                        }
                        $new = array_values(array_filter(array_unique(array_map("trim", $new))));
                        $its[$j][$mergeField] = $new;
                    }
                }

                if ($sumTotal) {
                    $sumFields = ['TotalCharge', 'BaseFare', 'Tax'];

                    foreach ($sumFields as $sumField) {
                        if ($sumTotal && (isset($its[$j][$sumField]) || isset($its[$i][$sumField]))) {
                            if (isset($its[$j]['Currency'], $its[$i]['Currency']) && !empty($its[$j]['Currency']) && !empty($its[$i]['Currency']) && $its[$j]['Currency'] !== $its[$i]['Currency']) {
                                $delSums = true;
                            } else {
                                $new = 0.0;

                                if (isset($its[$j][$sumField])) {
                                    $new += $its[$j][$sumField];
                                }

                                if (isset($its[$i][$sumField])) {
                                    $new += $its[$i][$sumField];
                                }
                                $its[$j][$sumField] = $new;
                            }
                        }
                    }
                }
                unset($its[$i]);
            }
        }

        if ($delSums) {
            $its2 = $its;

            foreach ($its2 as $i => $it) {
                $delElements = ['TotalCharge', 'BaseFare', 'Tax', 'Currency'];

                foreach ($delElements as $delElement) {
                    if (isset($it[$delElement])) {
                        unset($its[$i][$delElement]);
                    }
                }
            }
        }

        return $its;
    }

    protected function findRL($g_i, $rl, $its)
    {
        foreach ($its as $i => $it) {
            if ($g_i != $i && $it['RecordLocator'] === $rl) {
                return $i;
            }
        }

        return -1;
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

    private function parseEmail_1()
    {
        /** @var \AwardWallet\ItineraryArrays\AirTrip $it */
        $it = ['Kind' => 'T', 'TripSegments' => []];
        $it['RecordLocator'] = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Référence Réservation'))}]/following::text()[string-length(normalize-space(.))>1][1]");
        $it['Passengers'] = array_values(array_filter(array_unique($this->http->FindNodes("//text()[{$this->eq($this->t('Passager'))}]/ancestor::table[1]/descendant::tr[position()>1]/td[1]",
            null, "#\d+\.?\s+(.+)#"))));

        $tot = $this->getTotalCurrency($this->http->FindSingleNode("//text()[{$this->contains($this->t('Prix total TTC'))}]/preceding::text()[normalize-space(.)!=''][1]"));

        if (!empty($tot['Total'])) {
            $it['TotalCharge'] = $tot['Total'];
            $it['Currency'] = $tot['Currency'];
        }

        $xpath = "//text()[{$this->eq($this->t('Vol'))}]/ancestor::tr[1][{$this->contains($this->t('Départ'))}]/ancestor::table[1]/descendant::tr[position()>1 and count(./td)>4]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            /** @var \AwardWallet\ItineraryArrays\AirTripSegment $seg */
            $seg = [];
            $node = $this->http->FindSingleNode("./td[1]", $root);

            if (preg_match("#^([A-Z\d]{2})\s*(\d+)$#", $node, $m)) {
                $seg['AirlineName'] = $m[1];
                $seg['FlightNumber'] = $m[2];
            }
            $node = $this->http->FindSingleNode("./td[2]", $root);

            if (preg_match("#^(.+?)\s*\(\s*([A-Z]{3})\s*\)\s*(\d+:\d+)\s*\|\s*(.+)$#", $node, $m)) {
                $seg['DepName'] = $m[1];
                $seg['DepCode'] = $m[2];
                $time = $m[3];
                $date = $this->normalizeDate($m[4]);
                $seg['DepDate'] = strtotime($time, $date);
            }

            $node = $this->http->FindSingleNode("./td[3]", $root);

            if (preg_match("#^(.+?)\s*\(\s*([A-Z]{3})\s*\)\s*(\d+:\d+)\s*\|\s*(.+)$#", $node, $m)) {
                $seg['ArrName'] = $m[1];
                $seg['ArrCode'] = $m[2];
                $time = $m[3];
                $date = $this->normalizeDate($m[4]);
                $seg['ArrDate'] = strtotime($time, $date);
            }

            $seg['Cabin'] = $this->http->FindSingleNode("./td[6]", $root);
            $seg['Aircraft'] = $this->http->FindSingleNode("./td[5]", $root);
            $seg['Stops'] = $this->http->FindSingleNode("./td[4]", $root, true,
                "#(\d+)\s+{$this->opt($this->t('Escales'))}#");

            $it['TripSegments'][] = $seg;
        }

        return [$it];
    }

    private function parseEmail_2()
    {
        /** @var \AwardWallet\ItineraryArrays\AirTrip $it */
        $it = ['Kind' => 'T', 'TripSegments' => []];
        $it['RecordLocator'] = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Référence Réservation'))}]/following::text()[string-length(normalize-space(.))>1][1]");
        $it['Passengers'] = array_values(array_filter(array_unique($this->http->FindNodes("//text()[{$this->eq($this->t('Vol'))}]/ancestor::tr[1][{$this->contains($this->t('Départ'))}]/preceding::table[count(descendant::table)=0 and string-length(normalize-space(.))>1][1]/descendant::tr/td[string-length(normalize-space(.))>2][1]",
            null, "#\d+\.?\s+(.+)#"))));
        $it['AccountNumbers'] = array_values(array_filter(array_unique($this->http->FindNodes("//text()[{$this->eq($this->t('Vol'))}]/ancestor::tr[1][{$this->contains($this->t('Départ'))}]/preceding::table[count(descendant::table)=0 and string-length(normalize-space(.))>1][1]/descendant::tr/td[string-length(normalize-space(.))>2][2][{$this->starts($this->t('Numéro voyageur Fidèle'))}]/following-sibling::td[1]",
            null, "#([A-Z\d]{5,})#"))));

        $xpath = "//text()[{$this->eq($this->t('Vol'))}]/ancestor::tr[1][{$this->contains($this->t('Départ'))}]/following-sibling::tr[count(./td)>5]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            /** @var \AwardWallet\ItineraryArrays\AirTripSegment $seg */
            $seg = [];
            $node = $this->http->FindSingleNode("./td[1]", $root);

            if (preg_match("#^([A-Z\d]{2})\s*(\d+)$#", $node, $m)) {
                $seg['AirlineName'] = $m[1];
                $seg['FlightNumber'] = $m[2];
            }
            $node = $this->http->FindSingleNode("./td[2]", $root);

            if (preg_match("#^(.+?)\s*\(\s*([A-Z]{3})\s*\)\s*(\d+:\d+)\s*\|\s*(.+)$#", $node, $m)) {
                $seg['DepName'] = $m[1];
                $seg['DepCode'] = $m[2];
                $time = $m[3];
                $date = $this->normalizeDate($m[4]);
                $seg['DepDate'] = strtotime($time, $date);
            }

            $node = $this->http->FindSingleNode("./td[3]", $root);

            if (preg_match("#^(.+?)\s*\(\s*([A-Z]{3})\s*\)\s*(\d+:\d+)\s*\|\s*(.+)$#", $node, $m)) {
                $seg['ArrName'] = $m[1];
                $seg['ArrCode'] = $m[2];
                $time = $m[3];
                $date = $this->normalizeDate($m[4]);
                $seg['ArrDate'] = strtotime($time, $date);
            }

            $seg['Cabin'] = $this->http->FindSingleNode("./td[4]", $root);
            $seg['Seats'][] = $this->http->FindSingleNode("./td[5]", $root, null, "#(\d+[a-z])#i");
            $seg['Meal'] = $this->http->FindSingleNode("./td[5]", $root);

            $it['TripSegments'][] = $seg;
        }

        return [$it];
    }

    private function parseEmail_PDF($textPDF)
    {
        /** @var \AwardWallet\ItineraryArrays\AirTrip $it */
        $it = ['Kind' => 'T', 'TripSegments' => []];
        $it['RecordLocator'] = $this->re("#{$this->opt($this->t('BOOKING REFERENCE'))}\s+([A-Z\d]{5,})#", $textPDF);
        $it['Passengers'][] = $this->re("#{$this->opt($this->t('PASSENGER NAME'))}\s+(.+)#", $textPDF);
        $it['AccountNumbers'][] = $this->re("#{$this->opt($this->t('IDENTIFICATION'))}\s+([A-Z\d ]{5,})#", $textPDF);
        $it['TicketNumbers'][] = $this->re("#{$this->opt($this->t('E-TICKET NUMBER'))}\s+([\d ]{5,})#", $textPDF);
        $tot = $this->getTotalCurrency($this->re("#^\s*{$this->opt($this->t('FARE'))}\s+(?!AND)(.+)#m", $textPDF));

        if (!empty($tot['Total'])) {
            $it['BaseFare'] = $tot['Total'];
            $it['Currency'] = $tot['Currency'];
        }
        $tot = $this->getTotalCurrency($this->re("#{$this->opt($this->t('TOTAL'))}\s+(.+)#", $textPDF));

        if (!empty($tot['Total'])) {
            $it['TotalCharge'] = $tot['Total'];
            $it['Currency'] = $tot['Currency'];
        }

        $text = strstr($textPDF, "FARE AND PAYMENT DETAILS", true);

        if (empty($text)) {
            $text = $textPDF;
        }
        $nodes = $this->splitter("#(^\s*{$this->opt($this->t('FLIGHT'))}\s+{$this->opt($this->t('DEPART/ARRIVE'))})#m",
            $text);

        foreach ($nodes as $root) {
            $table = $this->SplitCols($this->re("#(^[^\n]*{$this->opt($this->t('FLIGHT'))}\s+{$this->opt($this->t('DEPART/ARRIVE'))}.+)#ms",
                $root));

            if (count($table) !== 6) {
                $this->http->Log('incorrect format table');

                return null;
            }
            /** @var \AwardWallet\ItineraryArrays\AirTripSegment $seg */
            $seg = [];

            if (preg_match("#{$this->opt($this->t('FLIGHT'))}\s+([A-Z\d]{2})\s*(\d+)#", $table[0], $m)) {
                $seg['AirlineName'] = $m[1];
                $seg['FlightNumber'] = $m[2];
            }

            if (preg_match("#{$this->opt($this->t('DEPART/ARRIVE'))}\s+(\d+\s+\w+\s+\d+\s+[^\n]+)\s+(\d+\s+\w+\s+\d+\s+[^\n]+)#",
                $table[1], $m)) {
                $seg['DepDate'] = $this->normalizeDate($m[1]);
                $seg['ArrDate'] = $this->normalizeDate($m[2]);
            }

            if (preg_match("#{$this->opt($this->t('AIRPORT/TERMINAL'))}\s+(.+?)\s*\(([A-Z]{3})\).*(?:\s+TERMINAL\s+(\w+\s*[^\n]*?)(?:\s*\d[^\n]*|))?\s+(.+?)\s*\(([A-Z]{3})\).*(?:\s+TERMINAL\s+(\w+\s*[^\n]*))?#",
                $table[2], $m)) {
                $seg['DepName'] = $m[1];
                $seg['DepCode'] = $m[2];

                if (isset($m[3]) && !empty($m[3])) {
                    $seg['DepartureTerminal'] = $m[3];
                }
                $seg['ArrName'] = $m[4];
                $seg['ArrCode'] = $m[5];

                if (isset($m[6]) && !empty($m[6])) {
                    $seg['ArrivalTerminal'] = $m[6];
                }
            }

            if (preg_match("#{$this->opt($this->t('CLASS'))}\s+(.+)#", $table[4], $m)) {
                $seg['Cabin'] = $m[1];
            }
            $it['TripSegments'][] = $seg;
        }

        return $it;
    }

    private function normalizeDate($date)
    {
        //		$year = date('Y', $this->date);
        $in = [
            //sam., 26 nov. 16
            '#^\w+\.?,\s+(\d+)[\-\s]+(\w+)\.?[\-\s]+(\d{2})$#u',
            //26 NOV 16 1740
            '#^\s*(\d+)\s+(\w+)\s+(\d{2})\s+(\d{2})(\d{2})\s*$#',
        ];
        $out = [
            '$1 $2 20$3',
            '$1 $2 20$3, $4:$5',
        ];
        $outWeek = [
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

    private function AssignLang()
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if ($this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody[0]}')]")->length > 0
                    && $this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody[1]}')]")->length > 0
                ) {
                    $this->lang = substr($lang, 0, 2);

                    return true;
                }
            }
        }

        return false;
    }

    private function getTotalCurrency($node)
    {
        $node = str_replace("€", "EUR", $node);
        $node = str_replace("$", "USD", $node);
        $node = str_replace("£", "GBP", $node);
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node,
                $m) || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node,
                $m) || preg_match("#(?<c>\-*?)(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)
        ) {
            $m['t'] = preg_replace('/\s+/', '', $m['t']);            // 11 507.00	->	11507.00
            $m['t'] = preg_replace('/[,.](\d{3})/', '$1', $m['t']);    // 2,790		->	2790		or	4.100,00	->	4100,00
            $m['t'] = preg_replace('/^(.+),$/', '$1', $m['t']);    // 18800,		->	18800
            $m['t'] = preg_replace('/,(\d{2})$/', '.$1', $m['t']);    // 18800,00		->	18800.00
            $tot = $m['t'];
            $cur = $m['c'];
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }
}
