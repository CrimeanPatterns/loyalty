<?php

namespace AwardWallet\Engine\fcmtravel\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;

class ConfirmationForPdf extends \TAccountChecker
{
    public $mailFiles = "";

    public $reFrom = "fcm.travel";
    public $reBody = [
        'de' => ['Reisebestätigung', 'FCM Travel Solutions'],
    ];
    public $reSubject = [
        'Reisebestätigung für',
    ];
    public $lang = '';
    public $date;
    /* @var \HttpBrowser */
    public $pdf;
    public $pdfNamePattern = ".*Reisebestaetigung_fuer.*pdf";
    public static $dict = [
        'de' => [
            'segmentReg' => 'Flug\s+Datum\s+Von\s+Nach\s+Abflug\s+Ankunft',
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getDate());

        $its = [];

        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdfs) && count($pdfs) > 0) {
            $this->pdf = clone $this->http;

            foreach ($pdfs as $pdf) {
                if (($html = \PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_SIMPLE)) !== null) {
                    $text = text($html);
                    $this->AssignLang($text);

                    $html = \PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_COMPLEX);
                    $NBSP = chr(194) . chr(160);
                    $this->pdf->SetEmailBody(str_replace($NBSP, ' ', html_entity_decode($html)));

                    $flights = $this->parseEmail($text);

                    foreach ($flights as $flight) {
                        $its[] = $flight;
                    }
                } else {
                    return null;
                }
            }
        } else {
            return null;
        }

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

            return $this->AssignLang($text);
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

    public function findСutSection($input, $searchStart, $searchFinish)
    {
        $inputResult = null;

        if ($searchStart) {
            $left = mb_strstr($input, $searchStart);
        } else {
            $left = $input;
        }

        if (is_array($searchFinish)) {
            $i = 0;

            while (empty($inputResult)) {
                if (isset($searchFinish[$i])) {
                    $inputResult = mb_strstr($left, $searchFinish[$i], true);
                } else {
                    return false;
                }
                $i++;
            }
        } elseif (!empty($searchFinish)) {
            $inputResult = mb_strstr($left, $searchFinish, true);
        } else {
            $inputResult = $left;
        }

        return mb_substr($inputResult, mb_strlen($searchStart));
    }

    /**
     * <pre>
     * Example:
     * abc SPLIT text\n text1\n acv SPLIT text\n text1
     * /(SPLIT)/
     * [0] => SPLIT text text1 acv
     * [1] => SPLIT text text1
     * <pre>.
     */
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
        $its = [];
        $resDate = strtotime($this->re("#{$this->opt($this->t('Datum'))}[\s:]+(\d+\.\d+\.\d+\s+\d+:\d+(?:[ap]m)?)#i", $textPDF));
        $tripNum = $this->re("#{$this->opt($this->t('Reservierungsnummer'))}[\s:]+([A-Z\d]{5,})#", $textPDF);

        $recLocs = [];

        if (preg_match_all("#{$this->opt($this->t('Reservierungsnummer'))}[\s:]+([A-Z\d]{2})[\s\/]([A-Z\d]{5,})#", $textPDF, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $recLocs[$match[1]] = $match[2];
            }
        } else {
            $info = $this->findСutSection($textPDF, 'Airlinebuchungscode', ['Vielflieger', 'Datum']);

            if (preg_match_all("#^([A-Z\d]{2})[\s\/]+([A-Z\d]{5,})$#m", $info, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $recLocs[] = [$match[1] => $match[2]];
                }
            }
        }

        $tickects = [];
        $pax = [];

        if (preg_match_all("#(.+?)\s+{$this->opt($this->t('TICKET'))}\s+([A-Z\d]{2})\s+([\d ]+)#", $textPDF, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $tickects[$match[2]][] = $match[3];
                $pax[] = $match[1];
            }
        } else {
            $info = $this->findСutSection($textPDF, 'Reisedaten für', 'Reservierungsnummer');
            $mas = array_map("trim", explode("\n", $info));

            if ($mas[0] == ':') {
                array_shift($mas[0]);
            }
            $pax = $mas;
        }

        $info = $this->findСutSection($textPDF, 'Vielflieger', 'Datum');
        $accs = [];

        if (preg_match_all("#^([A-Z\d]{2})\s*(\d+)#m", $info, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $accs[$match[1]][] = $match[2];
            }
        }

        $airs = [];
        $tickectsByRL = [];
        $accsByRL = [];
        $nodes = $this->splitter("#({$this->t('segmentReg')}).*#", $textPDF);

        foreach ($nodes as $root) {
            $airline = $this->re("#{$this->t('segmentReg')}\s+([A-Z\d]{2})\s*\d+#", $root);

            if (!empty($airline) && isset($recLocs[$airline])) {
                $airs[$recLocs[$airline]][] = $root;

                if (isset($tickects[$airline])) {
                    $tickectsByRL[$recLocs[$airline]] = $tickects[$airline];
                }

                if (isset($accs[$airline])) {
                    $accsByRL[$recLocs[$airline]] = $accs[$airline];
                }
            } else {
                $airs[CONFNO_UNKNOWN][] = $root;
            }
        }

        foreach ($airs as $rl => $roots) {
            $it = ['Kind' => 'T', 'TripSegments' => []];

            $it['RecordLocator'] = $rl;

            $it['TripNumber'] = $tripNum;

            $it['ReservationDate'] = $resDate;

            $it['Passengers'] = $pax;

            if (isset($tickectsByRL[$rl])) {
                $it['TicketNumbers'] = $tickectsByRL[$rl];
            }

            if (isset($accsByRL[$rl])) {
                $it['AccountNumbers'] = $accsByRL[$rl];
            }

            foreach ($roots as $root) {
                $seg = [];

                $node = $this->re("#{$this->t('segmentReg')}\s+([A-Z\d]{2}\s*\d+)#", $root);

                if (preg_match("#([A-Z\d]{2})\s*(\d+)#", $node, $m)) {
                    $seg['AirlineName'] = $m[1];
                    $seg['FlightNumber'] = $m[2];

                    $nodes = $this->pdf->XPath->query("//p[contains(.,'{$m[1]}') and contains(.,'$m[2]') and ./preceding::p[1][contains(.,'Ankunft')]]");

                    if ($nodes->length == 1) {
                        $node = implode(" ", $this->pdf->FindNodes(".//text()", $nodes->item(0)));

                        if (preg_match("#{$this->opt($this->t('durchgeführt von'))}\s*(.+)#", $node, $m)) {
                            $seg['Operator'] = $m[1];
                        }

                        $depDate = strtotime($this->normalizeDate($this->pdf->FindSingleNode("./following::p[normalize-space(.)!=''][1]", $nodes->item(0))));

                        $node = $this->pdf->FindSingleNode("./following::p[normalize-space(.)!=''][2]", $nodes->item(0));

                        if (preg_match("#(.+?)\s*(?:Terminal\s+(.+)|$)#i", $node, $m)) {
                            $seg['DepName'] = $m[1];

                            if (isset($m[2]) && !empty($m[2])) {
                                $seg['DepartureTerminal'] = $m[2];
                            }
                        }

                        $node = $this->pdf->FindSingleNode("./following::p[normalize-space(.)!=''][3]", $nodes->item(0));

                        if (preg_match("#(.+?)\s*(?:Terminal\s+(.+)|$)#i", $node, $m)) {
                            $seg['ArrName'] = $m[1];

                            if (isset($m[2]) && !empty($m[2])) {
                                $seg['ArrivalTerminal'] = $m[2];
                            }
                        }

                        $node = $this->pdf->FindSingleNode("./following::p[normalize-space(.)!=''][4]", $nodes->item(0));

                        if (preg_match("#(\d+:\d+\s*(?:[ap]m)?).*#i", $node, $m)) {
                            $seg['DepDate'] = strtotime($m[1], $depDate);
                        }

                        $node = $this->pdf->FindSingleNode("./following::p[normalize-space(.)!=''][5]", $nodes->item(0));

                        if (preg_match("#(\d+:\d+\s*(?:[ap]m)?).*#i", $node, $m)) {
                            $seg['ArrDate'] = strtotime($m[1], $depDate);

                            if (isset($seg['DepDate']) && $seg['ArrDate'] < $seg['DepDate']) {
                                $seg['ArrDate'] = strtotime("+1 day", $seg['ArrDate']);
                            }
                        }

                        if (preg_match("#{$this->opt($this->t('Flugdauer'))}[\s:]*(\d.*)#i", $node, $m)) {
                            $seg['Duration'] = $m[1];
                        }
                    }//if ($nodes->length == 1)
                }//if (preg_match("#....

                $seg['DepCode'] = TRIP_CODE_UNKNOWN;

                $seg['ArrCode'] = TRIP_CODE_UNKNOWN;

                if (preg_match("#{$this->opt($this->t('Klasse'))}[\s:]+\b([A-Z]{1,2})?\b[\s\-]*(.+)#", $root, $m)) {
                    if (isset($m[1])) {
                        $seg['BookingClass'] = $m[1];
                    }
                    $seg['Cabin'] = $m[2];
                }

                $seg['Aircraft'] = $this->re("#{$this->opt($this->t('Flugzeug'))}[\s:]+(.+)#", $root);

                $seg['Meal'] = $this->re("#{$this->opt($this->t('An Bord'))}[\s:]+(.+)#", $root);

                $node = $this->re("#{$this->opt($this->t('Sitzplatz'))}[\s:]+(.+)\s+{$this->opt($this->t('Info'))}#s", $root);

                if (preg_match_all("#(\b\d+[A-Z]\b)#i", $node, $m)) {
                    $seg['Seats'] = $m[1];
                }

                $it['TripSegments'][] = $seg;
            }
            $its[] = $it;
        }

        return $its;
    }

    private function normalizeDate($date)
    {
        $year = date('Y', $this->date);
        $in = [
            '#^(\w+),\s+(\d+)\.?\s+(\w+)$#',
        ];
        $out = [
            '$2 $3 ' . $year,
        ];
        $outWeek = [
            '$1',
        ];
        $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));

        $weeknum = WeekTranslate::number1(WeekTranslate::translate(preg_replace($in, $outWeek, $date), $this->lang));
        $str = EmailDateHelper::parseDateUsingWeekDay($str, $weeknum);
        $str = date("Y-m-d", $str);

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

    private function getTotalCurrency($node)
    {
        $node = str_replace("€", "EUR", $node);
        $node = str_replace("$", "USD", $node);
        $node = str_replace("£", "GBP", $node);
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m) || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m) || preg_match("#(?<c>\-*?)(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)) {
            $m['t'] = preg_replace('/\s+/', '', $m['t']);            // 11 507.00	->	11507.00
            $m['t'] = preg_replace('/[,.](\d{3})/', '$1', $m['t']);    // 2,790		->	2790		or	4.100,00	->	4100,00
            $m['t'] = preg_replace('/,(\d{2})$/', '.$1', $m['t']);    // 18800,00		->	18800.00
            $tot = $m['t'];
            $cur = $m['c'];
        }

        return ['Total' => $tot, 'Currency' => $cur];
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

        return '(?:' . implode("|", $field) . ')';
    }
}
