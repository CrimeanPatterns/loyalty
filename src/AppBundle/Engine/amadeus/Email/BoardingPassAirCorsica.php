<?php

namespace AwardWallet\Engine\amadeus\Email;

use AwardWallet\Engine\MonthTranslate;

class BoardingPassAirCorsica extends \TAccountChecker
{
    public $mailFiles = "amadeus/it-7629053.eml, amadeus/it-7729958.eml";

    public $reFrom = "@amadeus.com";
    public $reSubject = [
        "Votre carte d'embarquement Air Corsica",
    ];

    public $reBody = 'Air Corsica';

    /** @var \HttpBrowser */
    public $pdf;

    public $pdfNamePattern = ".*BoardingPass.*pdf";

    private $provs = [
        'amadeus' => [
            'link'    => 'amadeus.com',
            'detects' => [
                "Merci d'avoir utilisé l'enregistrement en ligne d' Air Corsica",
            ],
        ],
        'lotpair' => [
            'link'    => 'lot.com',
            'detects' => [
                'LOT Polish Airlines',
            ],
        ],
    ];

    private static $detectBody = [
        'fr' => [
            "Merci d'avoir utilisé l'enregistrement en ligne d' Air Corsica",
        ],
        'pl' => [
            'Informacje dodatkowe',
        ],
    ];

    private $lang = 'fr';

    private $dict = [
        'fr' => [
            //			'Numéro de réservation' => '',
            //			'Passager' => '',
            //			'Vol:' => '',
            //			'De:' => '',
            //			'Vers:' => '',
            //			'Numéro E-Ticket' => '',
            //PDF
            'N° de RéservationPdf' => 'N(?:°| ) de Réservation',
            //			"Document à conserver jusqu'à la fin de votre voyage" => '',
        ],
        'pl' => [
            'N° de RéservationPdf'                                 => 'Numer rezerwacji',
            'Document à conserver jusqu\'à la fin de votre voyage' => 'Karta pokładowa',
            // html
            'Numéro de réservation' => 'Numer rezerwacji:',
            'Passager'              => 'Pasażer:',
            'Vol:'                  => 'Lot:',
            'De:'                   => 'Od:',
            'Vers:'                 => 'Do:',
            'Numéro E-Ticket'       => '',
        ],
    ];

    public static function getEmailProviders()
    {
        return ['amadeus', 'lotpair'];
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $it = [];
        $this->detectBody($parser);
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (count($pdfs) === 0) {
            $this->logger->info('Pdfs not found');
        }

        if (isset($pdfs) && count($pdfs) > 0) {
            $this->pdf = clone $this->http;

            foreach ($pdfs as $pdf) {
                if (!empty($text = text(\PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_SIMPLE)))) {
                    $it = $this->parseEmailPDF($text);
                    //					$this->logger->info('pdf');
                }
            }
        }

        if (count($pdfs) === 0 || empty($it)) {
            $it = $this->parseEmailHtml();
            //			$this->logger->info('html');
        }

        $classParts = explode('\\', __CLASS__);

        $res = [
            'emailType'  => end($classParts) . ucfirst($this->lang),
            'parsedData' => ['Itineraries' => [$it]],
        ];

        if (($prov = $this->detectProv($parser->getHTMLBody())) && !empty($prov)) {
            $res = array_merge($res, ['providerCode' => $prov]);
        }

        return $res;
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

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->detectBody($parser)) {
            return true;
        }
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdfs) && count($pdfs) > 0) {
            foreach ($pdfs as $pdf) {
                $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

                if (stripos($text, "CARTE D'EMBARQUEMENT") && stripos($text, "aircorsica")) {
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
        return array_keys(self::$detectBody);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$detectBody);
    }

    public function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            } elseif ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, 'fr')) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    public function splitText($pattern, $text)
    {
        if (empty($text)) {
            return [];
        }

        $r = preg_split($pattern, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
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

    protected function match($pattern, $text, $allMatches = false)
    {
        if (preg_match($pattern, $text, $matches)) {
            if ($allMatches) {
                array_shift($matches);

                return array_map([$this, 'normalizeText'], $matches);
            } else {
                return $this->normalizeText(count($matches) > 1 ? $matches[1] : $matches[0]);
            }
        }

        return false;
    }

    protected function normalizeText($string)
    {
        return trim(preg_replace('/\s{2,}/', ' ', str_replace("\n", " ", $string)));
    }

    private function parseEmailHtml()
    {
        $it = [];
        $it['Kind'] = 'T';

        $it['RecordLocator'] = $this->http->FindSingleNode('(//span[contains(., "' . $this->t('Numéro de réservation') . '")]/following-sibling::span[1])[1]');

        if (empty($it['RecordLocator'])) {
            $it['RecordLocator'] = CONFNO_UNKNOWN;
        }

        $it['TicketNumbers'] = $this->http->FindNodes('(//span[contains(., "' . $this->t('Numéro E-Ticket:') . '")]/following-sibling::span[1])[1]', null, '/\d{10,30}$/');

        $passengers = $this->http->FindNodes('(//span[contains(., "' . $this->t('Passager') . '")]/following-sibling::span[1])[1]');
        $it['Passengers'] = array_unique($passengers);
        $flights = $this->http->FindNodes('//span[contains(., "' . $this->t('Vol:') . '")]');

        if (count($flights) === 0) {
            $this->logger->info('segments not found');
        }
        $flightCount = count($flights);

        for ($i = 1; $i <= $flightCount; $i++) {
            $segment = [];

            $flight = $this->http->FindSingleNode('(//span[contains(., "' . $this->t('Vol:') . '")])[' . $i . ']/following-sibling::span[1]');

            if (preg_match('#([\dA-Z]{2})(\d{2,5})#', $flight, $m)) {
                $segment['AirlineName'] = $m[1];
                $segment['FlightNumber'] = $m[2];
            }

            $departAr = $this->http->FindNodes('(//span[normalize-space(.)="' . $this->t('De:') . '"])[' . $i . ']/ancestor::td[1]//span');
            $depart = implode("\n", $departAr);

            if (preg_match('#:\n([\w., ]+)\n(Terminal\s*([\w]*)\n)?(\d{2}\s[a-z]{3}\s\d{4}\s*-\s*\d{2}:\d{2})#i', $depart, $m)) {
                $segment['DepName'] = $m[1];
                $segment['DepDate'] = $this->normalizeDate($m[4]);

                if (!empty($m[3])) {
                    $segment['DepartureTerminal'] = $m[3];
                }
                $segment['DepCode'] = TRIP_CODE_UNKNOWN;
            }

            $arriveAr = $this->http->FindNodes('(//span[normalize-space(.)="' . $this->t('Vers:') . '"])[' . $i . ']/ancestor::td[1]//span');
            $arrive = implode("\n", $arriveAr);

            if (preg_match('#:\n([\w., ]+)\n(Terminal\s*([\w]*)\n)?(\d{2}\s[a-z]{3}\s\d{4}\s*-\s*\d{2}:\d{2})#i', $arrive, $m)) {
                $segment['ArrName'] = $m[1];
                $segment['ArrDate'] = $this->normalizeDate($m[4]);

                if (!empty($m[3])) {
                    $segment['ArrivalTerminal'] = $m[3];
                }
                $segment['ArrCode'] = TRIP_CODE_UNKNOWN;
            }

            $it['TripSegments'][] = $segment;
        }

        return $it;
    }

    private function parseEmailPDF($text)
    {
        $it['Kind'] = 'T';
        $it['TripSegments'] = [];

        if (preg_match("#" . $this->t('N° de RéservationPdf') . "\s+(.*\n){0,5}\s*([A-Z\d]{5,6})\s#", $text, $m)) {
            $it['RecordLocator'] = $m[2];
        }

        if (preg_match_all("#" . $this->t("Document à conserver jusqu'à la fin de votre voyage") . "\s+(.*)\s+(?:De|Z)#i", $text, $m)) {
            foreach ($m[1] as $key => $value) {
                $it['Passengers'][] = trim(str_replace('/ ', '', $m[1][$key]));
            }
        }

        if (isset($it['Passengers'])) {
            $it['Passengers'] = array_unique($it['Passengers']);
        }

        if (preg_match_all("#(Billet|Bilet)\s+(.*\n){0,5}\s*(\d{8,25})#", $text, $m)) {
            foreach ($m[3] as $key => $value) {
                $it['TicketNumbers'][] = $m[3][$key];
            }
        }

        if (isset($it['TicketNumbers'])) {
            $it['TicketNumbers'] = array_unique($it['TicketNumbers']);
        }

        preg_match_all('/Frequent Flyer\s+[\S\s]+\s*\b[A-Z\d]+\b\nkarty/', $text, $m);

        if (isset($m[1]) && count($m[1]) > 0) {
            $it['AccountNumbers'] = $m[1];
        }

        $segTexts = $this->splitText("#(CARTE D'EMBARQUEMENT\n|Karta pokładowa\n)#", $text);

        foreach ($segTexts as $stext) {
            $same = false;
            $seg = $this->parseEmailSegment($stext);

            if ($seg === null || empty($seg['AirlineName']) || empty($seg['FlightNumber'])) {
                continue;
            }

            foreach ($it['TripSegments'] as $key => $flight) {
                if ($seg["AirlineName"] == $flight["AirlineName"] && $seg["FlightNumber"] == $flight["FlightNumber"] && $seg["DepDate"] == $flight["DepDate"]) {
                    if (!empty($seg["Seats"])) {
                        if (isset($it['TripSegments'][$key]["Seats"])) {
                            $it['TripSegments'][$key]["Seats"] = array_merge($it['TripSegments'][$key]["Seats"], $seg["Seats"]);
                        } else {
                            $it['TripSegments'][$key]["Seats"] = $seg["Seats"];
                        }
                    }
                    $same = true;
                }
            }

            if ($same === false) {
                $it['TripSegments'][] = $seg;
            }
        }

        return $it;
    }

    private function parseEmailSegment($text)
    {
        $segment = [];

        $re = "/(?<dtime>\d{2}:\d{2})\s+(?<atime>\d{2}:\d{2})\s+(?<ddate>\d+\s+[\w]+\s+\d{4})\s+(?<dcode>[A-Z]{3})\s+(?<acode>[A-Z]{3})\s+(?<adate>\d+\s+[\w]+\s+\d{4})(\s+Terminal *(?<dterm>.*))?(\s+Terminal\s*(?<aterm>.*))?\s*(?<dname>.+)\s+(?<aname>.+)/";
        $re2 = '/\s*(?<dtime>\d+:\d+)\s+(?<dcode>[A-Z]{3})\s+(?<acode>[A-Z]{3})\s+(?<atime>\d+:\d+)\s+(?<ddate>\d+ \w+ \d+)\s+(?<adate>\d+ \w+ \d+)(?:\s+Terminal *(?<dterm>.*))?(?:\s+Terminal\s*(?<aterm>.*))?\n\s*(?<dname>.+\n)\s*(?<aname>.+\n)/';

        if (preg_match($re, $text, $m) || preg_match($re2, $text, $m)) {
            $segment['DepCode'] = $m['dcode'];
            $segment['ArrCode'] = $m['acode'];
            $segment['DepDate'] = strtotime($m['dtime'] . $m['ddate']);

            if (empty($segment['DepDate'])) {
                $segment['DepDate'] = strtotime($this->dateStringToEnglish($m['dtime'] . $m['ddate']));
            }
            $segment['ArrDate'] = strtotime($m['atime'] . $m['adate']);

            if (empty($segment['ArrDate'])) {
                $segment['ArrDate'] = strtotime($this->dateStringToEnglish($m['atime'] . $m['adate']));
            }
            $segment['DepName'] = trim($m['dname']);
            $segment['ArrName'] = trim($m['aname']);

            if (!empty($m['dterm']) && !empty($m['aterm'])) {
                $segment['DepartureTerminal'] = $m['dterm'];
                $segment['ArrivalTerminal'] = $m['aterm'];
            }
        }

        if (preg_match("#Vol\s+.*\s*Siège(\s|\n)(.*\n){0,5}\s*([\dA-Z]{2})(\d{1,5})(?:\/([\dA-Z]{2})(\d{1,5}))?\s*(\d{1,3}[A-Z])#", $text, $m)) {
            if (!empty($m[5])) {
                $segment['AirlineName'] = $m[5];
                $segment['FlightNumber'] = $m[6];
            } else {
                $segment['AirlineName'] = $m[3];
                $segment['FlightNumber'] = $m[4];
            }
            $segment['Seats'][] = $m[7];
        } elseif (preg_match('/\s*Lot\n\s*[\S\s]+Zone[\s\S]+\b([A-Z\d]{2})\s*(\d+)\s+([A-Z\d]{1,3})\s+(?:Informacja na\s+|Informacja\s*)?\b([A-Z])\b/', $text, $m)) {
            $segment['AirlineName'] = $m[1];
            $segment['FlightNumber'] = $m[2];
            $segment['Seats'][] = $m[3];
            $segment['BookingClass'] = $m[4];
        }

        if ((empty($segment['DepartureTerminal']) || empty($segment['ArrivalTerminal'])) && isset($segment['AirlineName']) && isset($segment['FlightNumber'])) {
            $depName = strtoupper($segment['DepName']);
            $term = orval(
                $this->http->FindSingleNode("//tr[contains(.,'" . $segment['AirlineName'] . $segment['FlightNumber'] . "')][1]//following-sibling::tr[" . $this->containsAllLang("De:'") . "][1]", null, true, "#Terminal\s*(.*)\s*\d{2}\s*[A-Z]{3}#i"),
                $this->http->FindSingleNode("//span[contains(., '{$depName}')]/following-sibling::span[1]", null, true, "#Terminal\s*(\b[A-Z\d]{1,3}\b)#i")
            );

            if (!empty($term)) {
                $segment['DepartureTerminal'] = $term;
            }
            $arrName = strtoupper($segment['ArrName']);
            $term = orval(
                $this->http->FindSingleNode("//tr[contains(.,'" . $segment['AirlineName'] . $segment['FlightNumber'] . "')][1]//following-sibling::tr[" . $this->containsAllLang("Vers:") . "][1]", null, true, "#Terminal\s*(.*)\s*\d{2}\s*[A-Z]{3}#i"),
                $this->http->FindSingleNode("//span[contains(., '{$arrName}')]/following-sibling::span[1]", null, true, "#Terminal\s*(\b[A-Z\d]{1,3}\b)#i")
            );

            if (!empty($term)) {
                $segment['ArrivalTerminal'] = $term;
            }
        }

        if (preg_match('#(Classe de Transport)\n(.*[a-z].*\n){0,5}\s*([A-Z]{1,2})\s+#', $text, $m)) {
            $segment['Cabin'] = $m[3];
        }

        return $segment;
    }

    //========================================
    // Auxiliary methods
    //========================================

    private function detectBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        if (count($pdfs) > 0) {
            $body .= \PDF::convertToText($parser->getAttachmentBody(array_shift($pdfs)));
        }

        foreach (self::$detectBody as $lang => $detect) {
            if (is_array($detect)) {
                foreach ($detect as $item) {
                    if (stripos($body, $item) !== false) {
                        $this->lang = $lang;

                        return true;
                    }
                }
            } else {
                if (stripos($body, $detect) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function detectProv($text)
    {
        $res = '';

        foreach ($this->provs as $prov => $detects) {
            foreach ($detects['detects'] as $detect) {
                if (stripos($text, $detect) !== false && stripos($text, $detects['link']) !== false) {
                    $res = $prov;

                    break 2;
                }
            }
        }

        return $res;
    }

    private function normalizeDate($date)
    {
        $in = [
            //19 JUN 2017 - 19:25
            '#^\s*(\d{2})\s+(\w+)\s+(\d{4})\s*-\s*(\d{2}:\d{2})$#u',
        ];
        $out = [
            '$1 $2 $3, $4',
        ];
        $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));

        return strtotime($str);
    }

    private function t($s)
    {
        if (empty($this->dict[$this->lang]) || empty($this->dict[$this->lang][$s])) {
            return $s;
        }

        return $this->dict[$this->lang][$s];
    }

    private function containsAllLang($field)
    {
        $fields = [$field];

        foreach ($this->dict as $key => $value) {
            if (isset($value[$field])) {
                $fields[] = $value[$field];
            }
        }

        if (count($fields) == 0) {
            return '';
        }

        return implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(.), \"{$s}\")";
        }, $fields));
    }
}
