<?php

namespace AwardWallet\Engine\finnair\Email;

class YourConfirmationPDF extends \TAccountChecker
{
    public $mailFiles = "finnair/it-5028605.eml";

    public $reBody = [
        'fi' => ['From:\s+Finnair\s+noreply@Finnair.com', 'Subject:\s+Vahvistus\s+varaustunnukselle'],
        'en' => ['Carry on\-baggage on flights operated by Finnair', 'YOUR BOOKING REFERENCE'],
    ];

    public $reLang = [
        'fi' => ['VARAUS VAHVISTETTU', 'KIITOS VARAUKSESTASI'],
        'en' => ['THANK YOU FOR YOUR BOOKING!', 'please contact Finnair'],
    ];

    public $lang = '';

    /** @var \HttpBrowser */
    public $pdf;

    public static $dict = [
        'fi' => [],
        'en' => [
            'VARAUSTUNNUS:'  => 'YOUR BOOKING REFERENCE:',
            'Matkustaja'     => 'Passenger',
            'MENO'           => 'DEPARTURE',
            'PALUU'          => 'RETURN',
            'Kokonaiskesto:' => 'Total duration',
            'KOKONAISHINTA'  => 'TOTAL PRICE',
        ],
    ];

    private $monthNames = [
        'en' => ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'],
        'fi' => ['tammikuuta', 'helmikuuta', 'maaliskuuta', 'huhtikuuta', 'toukokuuta', 'kesäkuuta', 'heinäkuuta', 'elokuuta', 'syyskuuta', 'lokakuuta', 'marraskuuta', 'joulukuuta'],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        if (isset($pdfs) && count($pdfs) > 0) {
            $this->pdf = clone $this->http;
            $html = '';

            foreach ($pdfs as $pdf) {
                if (($html .= \PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_COMPLEX)) !== null) {
                } else {
                    return null;
                }
            }
            $NBSP = chr(194) . chr(160);
            $html = str_replace($NBSP, ' ', html_entity_decode($html));
            $this->pdf->SetBody($html);
        } else {
            return null;
        }

        $this->assignLang($html);

        $its = $this->parseEmail();

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => 'YourConfirmationPDF' . ucfirst($this->lang),
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdf = $parser->searchAttachmentByName('.*pdf');

        if (isset($pdf[0])) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf[0]));
            $NBSP = chr(194) . chr(160);
            $text = str_replace($NBSP, ' ', html_entity_decode($text));

            foreach ($this->reBody as $reBody) {
                if (preg_match('/' . $reBody[0] . '/ui', $text) && preg_match('/' . $reBody[1] . '/ui', $text)) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@finnair.com') !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    protected function getDate($nodeForDate)
    {
        $month = $this->monthNames['en'];
        $monthLang = $this->monthNames[$this->lang];

        preg_match('/(?<dayOfWeek>.+)\s+(?<day>[\d]+)\s+(?<month>.+)\s+(?<year>\d{4})/', $nodeForDate, $chek);

        for ($i = 0; $i < 12; $i++) {
            if ($monthLang[$i] == strtolower(trim($chek['month']))) {
                $chek['month'] = preg_replace('/[\w]+/i', $month[$i], $chek['month']);
                $res = strtotime($chek['month'] . ' ' . $chek['day'] . ' ' . $chek['year']);
            }
        }

        return $res;
    }

    private function parseEmail()
    {
        $it = ['Kind' => 'T', 'TripSegments' => []];

        $it['RecordLocator'] = $this->pdf->FindSingleNode("//p[contains(.,'{$this->t('VARAUSTUNNUS:')}')]", null, true, "#\s+[A-Z\d]+$#");

        $it['TotalCharge'] = $this->pdf->FindSingleNode("(//p[contains(normalize-space(.), '{$this->t('KOKONAISHINTA')}')]/following-sibling::p[string-length(normalize-space(.))>2][1])[1]", null, true, '/([\d\.\,]+) [A-Z]{3}/');

        $it['Currency'] = $this->pdf->FindSingleNode("(//p[contains(normalize-space(.), '{$this->t('KOKONAISHINTA')}')]/following-sibling::p[string-length(normalize-space(.))>2][1])[1]", null, true, '/[\d\.\,]+ ([A-Z]{3})/');

        $it['Passengers'] = $this->pdf->FindNodes("//p[contains(.,'{$this->t('Matkustaja')}')]/following::p[string-length(normalize-space(.))>2][1]");

        if (!empty($it['Passengers'][0]) && preg_match('/First name[ ]*\:[ ]*(.+)/', $it['Passengers'][0], $m)) {
            unset($it['Passengers']);
            $it['Passengers'][] = $m[1] . ' ' . $this->pdf->FindSingleNode("//p[contains(.,'{$this->t('Matkustaja')}')]/following::p[string-length(normalize-space(.))>2][2]", null, true, '/Family name[ ]*\:[ ]*(.+)/');
            $it['TicketNumbers'] = $this->pdf->FindNodes("//p[contains(.,'{$this->t('Matkustaja')}')]/following::p[string-length(normalize-space(.))>2][3]");
        } else {
            $it['TicketNumbers'] = $this->pdf->FindNodes("//p[contains(.,'{$this->t('Matkustaja')}')]/following::p[string-length(normalize-space(.))>2][2]");
        }

        $xpath = "//p[contains(.,'{$this->t('MENO')}') or contains(.,'{$this->t('PALUU')}')][not(preceding-sibling::p[position() = 1 or position() = 2][contains(., 'BENEFITS')])]";
        $roots = $this->pdf->XPath->query($xpath);

        foreach ($roots as $root) {
            $cnt1 = $this->pdf->XPath->query("./preceding-sibling::p", $root)->length;
            $cnt2 = $this->pdf->XPath->query("(following-sibling::p[contains(.,'{$this->t('Kokonaiskesto:')}')])[1]/preceding-sibling::p", $root)->length;
            $cnt3 = $cnt2 - $cnt1;
            $nodesText = implode("\n", $this->pdf->FindNodes("following-sibling::p[position() <= {$cnt3}]", $root));

            $node = $this->pdf->FindSingleNode("./following::p[1]", $root);

            if (preg_match("#(?<dName>.+?)\s*(?:\((?<dTerm>.+)\))?\s+-\s+(?<aName>.+?)\s*(?:\((?<aTerm>.+)\))?$#", $node, $m)) {
                $seg = [];

                if (isset($m['dTerm']) && !empty($m['dTerm'])) {
                    $seg['DepartureTerminal'] = $m['dTerm'];
                }

                if (isset($m['aTerm']) && !empty($m['aTerm'])) {
                    $seg['ArrivalTerminal'] = $m['aTerm'];
                }
                $seg['DepName'] = $m['dName'];
                $seg['ArrName'] = $m['aName'];
                $seg['DepCode'] = TRIP_CODE_UNKNOWN;
                $seg['ArrCode'] = TRIP_CODE_UNKNOWN;

                $date = $this->pdf->FindSingleNode("./following::p[2]", $root, true, "#\d+\.\d+\.\d+#");
                $node = $this->pdf->FindSingleNode("./following::p[3]", $root);

                if (preg_match("#([A-Z\d]{2})\s*(\d+)\s+([A-Z]{1,2})\s+(\d+:\d+)\s*-\s*(\d+:\d+)#", $node, $m)) {
                    $seg['AirlineName'] = $m[1];
                    $seg['FlightNumber'] = $m[2];
                    $seg['BookingClass'] = $m[3];
                    $seg['DepDate'] = strtotime($date . ', ' . $m[4]);
                    $seg['ArrDate'] = strtotime($date . ', ' . $m[5]);
                }

                $seg['Cabin'] = $this->pdf->FindSingleNode("./following::p[4]", $root);

                $seg['Duration'] = $this->pdf->FindSingleNode("./following::p[5]", $root, true, "#:\s+(.+)#");

                $it['TripSegments'][] = $seg;
            } elseif ($segs = $this->splitter('/([a-z ,\.]+?\s*(?:\(.+\))?\s+-\s+[a-z ,\.]+?\s*(?:\(.+\))?\n)/i', $nodesText)) {
                foreach ($segs as $segText) {
                    $seg = [];

                    if (preg_match('/(?<dName>[a-z ,\.]+?)\s*(?:\((?<dTerm>.+)\))?\s+-\s+(?<aName>[a-z ,\.]+?)\s*(?:\((?<aTerm>.+)\))?\n/i', $segText, $m)) {
                        if (isset($m['dTerm']) && !empty($m['dTerm'])) {
                            $seg['DepartureTerminal'] = str_ireplace('Terminal', '', $m['dTerm']);
                        }

                        if (isset($m['aTerm']) && !empty($m['aTerm'])) {
                            $seg['ArrivalTerminal'] = str_ireplace('Terminal', '', $m['aTerm']);
                        }
                        $seg['DepName'] = $m['dName'];
                        $seg['ArrName'] = $m['aName'];
                        $seg['DepCode'] = TRIP_CODE_UNKNOWN;
                        $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
                    }
                    $date = null;

                    if (preg_match('/(\w+ \d{1,2} \w+ \d{4})/', $segText, $m)) {
                        $date = $m[1];
                    }

                    if (preg_match("#([A-Z\d]{2})\s*(\d+)\s+([A-Z]{1,2})\s+(\d+:\d+)\s*-\s*(\d+:\d+)#", $segText, $m)) {
                        $seg['AirlineName'] = $m[1];
                        $seg['FlightNumber'] = $m[2];
                        $seg['BookingClass'] = $m[3];
                        $seg['DepDate'] = strtotime($date . ', ' . $m[4]);
                        $seg['ArrDate'] = strtotime($date . ', ' . $m[5]);
                        $seats = $this->pdf->FindSingleNode("//p[starts-with(normalize-space(.), '{$m[1]}{$m[2]}')][1]", null, true, "/\b{$m[1]}{$m[2]} ([A-Z\d\, ]+)\b/");
                        $seats = explode(', ', $seats);
                        $seats = array_map(function ($s) { return preg_match('/\b\d{1,2}[A-Z]{1,2}\b/', $s) ? $s : null; }, $seats);

                        if (!empty($seats[0])) {
                            $seg['Seats'] = $seats;
                        }
                    }

                    $it['TripSegments'][] = $seg;
                }
            }
        }

        return [$it];
    }

    private function splitter($regular, $text)
    {
        $result = [];

        $array = preg_split($regular, $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        array_shift($array);

        for ($i = 0; $i < count($array) - 1; $i += 2) {
            $result[] = $array[$i] . $array[$i + 1];
        }

        return $result;
    }

    private function normalizeDate($date)
    {
        $in = [
            '#[\S\s]*(\d{2})[\.\/]*(\d{2})[\.\/]*(\d{2})#',
            '#[\S\s]*(\d{2})-(\D{3,})-(\d{2})[.]*#',
        ];
        $out = [
            '$2/$1/$3',
            '$2 $1 $3',
        ];

        return preg_replace($in, $out, $date);
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang($body)
    {
        if (isset($this->reLang)) {
            foreach ($this->reLang as $lang => $reBody) {
                if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                    $this->lang = $lang;

                    break;
                }
            }
        }

        return true;
    }
}
