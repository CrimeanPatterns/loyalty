<?php

namespace AwardWallet\Engine\asiana\Email;

use AwardWallet\Engine\MonthTranslate;

// parsers with similar PDF-formats: airtransat/BoardingPass, aviancataca/BoardingPass, aviancataca/TicketDetails, czech/BoardingPass, lotpair/BoardingPass, sata/BoardingPass, tamair/BoardingPassPDF(object), tapportugal/AirTicket, luxair/YourBoardingPassNonPdf, saudisrabianairlin/BoardingPass

class BoardingPassPdf extends \TAccountChecker
{
    public $mailFiles = "asiana/it-67677460.eml, asiana/it-8202868.eml, asiana/it-8206374.eml, asiana/it-123724954.eml";

    public $reFrom = "noreply@flyasiana.com";
    public $reProvider = "@flyasiana.com";
    public $reSubject = [
        "en" => "Check-in Confirmation",
        "ko" => "님의 탑승권",
    ];
    //	public $reBody = '';
    public $emailSubject;
    public $reBody2 = [
        "en" => "Thank you for flying Asiana airlines",
        "ko" => "아시아나항공 인터넷 체크인을 이용해주셔서 감사합니다",
    ];
    public $reBody2Pdf = [
        "en"  => ["Boarding Pass", "TAKE-OFF", "LANDING"],
        "en2" => ["Boarding Pass", "Departure", "Arrival"],
        "en3" => ["Confirmation Document", "Departure", "Arrival"],
        "ko"  => ["항공편명", "좌석", "예약번호"],
    ];
    /** @var \HttpBrowser */
    public $pdf;
    public $pdfNamePattern = "(Boarding\s*Pass|Confirmation).*\.pdf";

    public static $dictionary = [
        "en" => [
            //html
            //			'Passenger:' => '',
            //			'Booking Reference:' => '',
            //			'Flight:' => '',
            //			'From:' => '',
            //			'To:' => '',
            //			//pdf
            'Boarding Pass' => ['Boarding Pass', 'Confirmation Document'],
            //			'BOOKING REFERENCE' => '',
            //			'FROM' => '',
            //			'ETKT' => '',
            //			'FREQUENT FLYER' => '',
            //			'FLIGHT' => '',
            //			'SEAT' => '',
            'TAKE-OFF' => ['TAKE-OFF', 'Departure'],
            'LANDING'  => ['LANDING', 'Arrival'],
            'CLASS'    => '(CLASS( OF TRAVEL)?)',
        ],

        "ko" => [
            //html
            'Passenger:'         => '승객이름',
            'Booking Reference:' => '예약번호',
            'Flight:'            => '편명:',
            'From:'              => '출발:',
            'To:'                => '도착',
            //			//pdf
            'Boarding Pass'     => 'Boarding Pass Exchange Coupon',
            'BOOKING REFERENCE' => '예약번호',
            //			'FROM' => '',
            //			'ETKT' => '',
            //			'FREQUENT FLYER' => '',
            'FLIGHT'        => '항공편명',
            'SEAT'          => '좌석',
            'TAKE-OFF'      => '출발',
            'LANDING'       => '도착',
            'CLASS'         => '탑승 클래스',
            'BOARDING TIME' => '탑승시각',
            'GATE'          => '탑승구',
            'AccountNumber' => '회원번호',
        ],
    ];
    public $lang = "";

    private $providerCode = '';

    private $patterns = [
        'time'          => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.
        'travellerName' => '[[:alpha:]]+(?: [[:alpha:]]+)*[ ]*\/[ ]*(?:[[:alpha:]]+ )*[[:alpha:]]+', // KOH / KIM LENG MR
    ];

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reProvider) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (strpos($headers["from"], $this->reFrom) === false) {
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
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (count($pdfs) > 0) {
            $text = '';

            foreach ($pdfs as $pdf) {
                if (($text .= \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
                    continue;
                }
            }

            foreach ($this->reBody2Pdf as $re) {
                if (strpos($text, $re[0]) !== false && strpos($text, $re[1]) !== false && strpos($text, $re[2]) !== false) {
                    return true;
                }
            }
        } else {
            $text = $parser->getHTMLBody();

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
        $its = [];
        $type = '';

        $this->emailSubject = $parser->getSubject();
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdfs) && count($pdfs) > 0) {
            $this->pdf = clone $this->http;

            foreach ($pdfs as $pdf) {
                if (($text2 = \PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_COMPLEX)) !== null) {
                    $this->pdf->SetEmailBody($text2);
                    $text = strip_tags($this->sortNodes());

                    // Detect Language
                    foreach ($this->reBody2Pdf as $lang => $re) {
                        if (strpos($text, $re[0]) !== false && strpos($text, $re[1]) !== false && strpos($text, $re[2]) !== false) {
                            $this->lang = substr($lang, 0, 2);

                            break;
                        }
                    }

                    if (empty($this->lang)) {
                        return null;
                    }

                    // Detect Provider
                    $this->assignProvider($parser->getHeaders(), $text);

                    $type = 'Pdf';
                    $its = array_merge($its, $this->parseEmailPDF($text));
                } else {
                    continue;
                }
            }
        } else {
            // Detect Language
            foreach ($this->reBody2 as $lang => $re) {
                if (strpos($this->http->Response['body'], $re) !== false) {
                    $this->lang = substr($lang, 0, 2);

                    break;
                }
            }

            if (empty($this->lang)) {
                return null;
            }

            // Detect Provider
            $this->assignProvider($parser->getHeaders(), $this->http->Response['body']);

            $type = 'Html';
            $its = $this->parseEmailHtml();
        }

        foreach ($its as $key => $it) {
            foreach ($it['TripSegments'] as $i => $value) {
                if (isset($its[$key]['Passengers'])) {
                    if (isset($its[$key]['Passengers'])) {
                        $its[$key]['Passengers'] = array_unique($its[$key]['Passengers']);
                    }

                    if (isset($its[$key]['TicketNumbers'])) {
                        $its[$key]['TicketNumbers'] = array_unique($its[$key]['TicketNumbers']);
                    }

                    if (isset($its[$key]['AccountNumbers'])) {
                        $its[$key]['AccountNumbers'] = array_unique($its[$key]['AccountNumbers']);
                    }
                }
            }
        }

        return [
            'providerCode' => $this->providerCode,
            'emailType'    => 'BoardingPass' . $type . ucfirst($this->lang),
            'parsedData'   => ['Itineraries' => $its],
        ];
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    public static function getEmailProviders()
    {
        return ['cape', 'asiana'];
    }

    public function splitText($pattern, $text)
    {
        if (empty($text)) {
            return [$text];
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

    private function assignProvider($headers, $text): bool
    {
        if (strpos($headers['subject'], 'Cape Air Boarding Pass') !== false
            || $this->http->XPath->query('//*[contains(normalize-space(),"Thank you for choosing Cape Air")]')->length > 0
            || stripos($text, 'www.capeair.com') !== false
        ) {
            // it-123724954.eml
            $this->providerCode = 'cape';

            return true;
        }

        if (strpos($headers['from'], '@flyasiana.com') !== false
            || $this->http->XPath->query('//a[contains(@href,"//flyasiana.com/")]')->length > 0
            || $this->http->XPath->query('//*[contains(normalize-space(),"Thank you for flying Asiana") or contains(normalize-space(),"Asiana Airlines Inc. All Rights Reserved")]')->length > 0
        ) {
            $this->providerCode = 'asiana';

            return true;
        }

        return false;
    }

    private function parseEmailHtml()
    {
        $its = [];
        $flightCount = count($this->http->FindNodes('//span[contains(., "' . $this->t('Passenger:') . '")]/following-sibling::span[1]'));

        for ($i = 1; $i <= $flightCount; $i++) {
            $seg = [];
            $RecordLocator = $this->http->FindSingleNode('(//span[contains(., "' . $this->t('Booking Reference:') . '")])[' . $i . ']/following-sibling::span[1]', null, true, "#[A-Z\d]{5,6}#");
            $Passengers = $this->http->FindSingleNode('(//span[contains(., "' . $this->t('Passenger:') . '")])[' . $i . ']/following-sibling::span[1]');
            $flight = $this->http->FindSingleNode('(//span[contains(., "' . $this->t('Flight:') . '")])[' . $i . ']/following-sibling::span[1]');

            if (preg_match('#([A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(\d+)#', $flight, $m)) {
                $seg['AirlineName'] = $m[1];
                $seg['FlightNumber'] = $m[2];
                $seg['Cabin'] = $this->http->FindSingleNode('(//span[contains(., "' . $this->t('Flight:') . '")])[' . $i . ']/following-sibling::span[3]');
            }

            $departAr = $this->http->FindNodes('(//span[normalize-space(.)="' . $this->t('From:') . '"])[' . $i . ']/ancestor::td[1]//span');
            $depart = implode("\n", $departAr);

            if (preg_match('#:\n([\w., ]+)\n(Terminal\s*([\w]*)\n)?(\d{2}/\d{2}/\d{4}\s*-\s*\d{2}:\d{2})#i', $depart, $m)) {
                $seg['DepName'] = $m[1];
                $seg['DepDate'] = strtotime($this->normalizeDate($m[4]));

                if (!empty($m[3])) {
                    $seg['DepartureTerminal'] = $m[3];
                }

                if ($flightCount == 1 && preg_match("/\(([A-Z]{3})-[A-Z]{3}\/\w+\)\s*$/u", $this->emailSubject, $mat)) {
                    // Ha Jongwoo 님의 탑승권 (ICN-TAS/5월27일)
                    // Boarding pass for Sara Chen Tan (CJU-GMP/27MAY)
                    $seg['DepCode'] = $mat[1];
                } else {
                    $seg['DepCode'] = TRIP_CODE_UNKNOWN;
                }
            }

            $arriveAr = $this->http->FindNodes('(//span[normalize-space(.)="' . $this->t('To:') . '"])[' . $i . ']/ancestor::td[1]//span');
            $arrive = implode("\n", $arriveAr);

            if (preg_match("#{$this->opt($this->t('To:'))}\s*\n([\w., ]+)\n(Terminal\s*([\w]*)\n)?(\d{2}/\d{2}/\d{4}\s*-\s*\d{2}:\d{2})#i", $arrive, $m)) {
                $seg['ArrName'] = $m[1];
                $seg['ArrDate'] = strtotime($this->normalizeDate($m[4]));

                if (!empty($m[3])) {
                    $seg['ArrivalTerminal'] = $m[3];
                }

                if ($flightCount == 1 && preg_match("/\([A-Z]{3}-([A-Z]{3})\/\w+\)\s*$/u", $this->emailSubject, $mat)) {
                    // Ha Jongwoo 님의 탑승권 (ICN-TAS/5월27일)
                    // Boarding pass for Sara Chen Tan (CJU-GMP/27MAY)
                    $seg['ArrCode'] = $mat[1];
                } else {
                    $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
                }
            }

            $finded = false;

            foreach ($its as $key => $it) {
                if (isset($RecordLocator) && $it['RecordLocator'] == $RecordLocator) {
                    if (isset($Passengers)) {
                        $its[$key]['Passengers'][] = $Passengers;
                    }
                    $finded2 = false;

                    foreach ($it['TripSegments'] as $key2 => $value) {
                        if (isset($seg['AirlineName']) && $seg['AirlineName'] == $value['AirlineName']
                                && isset($seg['FlightNumber']) && $seg['FlightNumber'] == $value['FlightNumber']
                                && isset($seg['DepDate']) && $seg['DepDate'] == $value['DepDate']) {
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

                if (isset($RecordLocator)) {
                    $it['RecordLocator'] = $RecordLocator;
                }

                if (isset($Passengers)) {
                    $it['Passengers'][] = $Passengers;
                }
                $it['TripSegments'][] = $seg;
                $its[] = $it;
            }
        }

        return $its;
    }

    private function parseEmailPDF($text)
    {
        $its = [];
        $segTexts = $this->splitText("/(?:^|\n)\s*({$this->opt($this->t('Boarding Pass'))}.*\n)/", $text);

        foreach ($segTexts as $stext) {
            $seg = [];

            if (preg_match("#" . $this->t('BOOKING REFERENCE') . "\s*(?:.*\n){0,5}\s*([A-Z\d]{5,6})\s*\n#Uu", $stext, $m)) {
                $RecordLocator = $m[1];
            }

            if (preg_match("/{$this->opt($this->t('Boarding Pass'))}.*\n(?:.+\n+)?[ ]*({$this->patterns['travellerName']})\s+{$this->opt($this->t('FROM'))}/u", $stext, $m)) {
                $Passengers = str_replace('/ ', '', $m[1]);
            }

            if (preg_match("#" . $this->t("ETKT") . "\s*(?:.*\n){0,5}\s*(\d{8,25})\s*\n#u", $stext, $m)) {
                $TicketNumbers = $m[1];
            }

            if (preg_match("#" . $this->t("FREQUENT FLYER") . "\s*(?:.*\n){0,5}\s*([A-Z]*\d{5,})\s*\n#u", $stext, $m)) {
                $AccountNumbers = $m[1];
            }

            if (preg_match("#(" . $this->t("AccountNumber") . ")\n.+\n(?<accountNumber>[A-Z]{2}\d+)\n(?<level>\w+)\s[A-Z]{2}#u", $stext, $m)) {
                $AccountNumbers = $m['accountNumber'];
                $ServiceLevel = $m['level'];
            }
            $seg = $this->parseEmailSegment($stext);

            if ($seg === null) {
                continue;
            }

            $finded = false;

            foreach ($its as $key => $it) {
                if (isset($RecordLocator) && $it['RecordLocator'] == $RecordLocator) {
                    if (isset($Passengers)) {
                        $its[$key]['Passengers'][] = $Passengers;
                    }

                    if (isset($TicketNumbers)) {
                        $its[$key]['TicketNumbers'][] = $TicketNumbers;
                    }

                    if (isset($AccountNumbers)) {
                        $its[$key]['AccountNumbers'][] = $AccountNumbers;
                    }

                    if (isset($ServiceLevel)) {
                        $its[$key]['ServiceLevel'][] = $ServiceLevel;
                    }
                    $finded2 = false;

                    foreach ($it['TripSegments'] as $key2 => $value) {
                        if (isset($seg['AirlineName']) && $seg['AirlineName'] == $value['AirlineName']
                                && isset($seg['FlightNumber']) && $seg['FlightNumber'] == $value['FlightNumber']
                                && isset($seg['DepDate']) && $seg['DepDate'] == $value['DepDate']) {
                            $its[$key]['TripSegments'][$key2]['Seats'] = array_unique(array_filter(array_merge($value['Seats'], $seg['Seats'])));
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

                if (isset($RecordLocator)) {
                    $it['RecordLocator'] = $RecordLocator;
                }

                if (isset($Passengers)) {
                    $it['Passengers'][] = $Passengers;
                }

                if (isset($TicketNumbers)) {
                    $it['TicketNumbers'][] = $TicketNumbers;
                }

                if (isset($AccountNumbers)) {
                    $it['AccountNumbers'][] = $AccountNumbers;
                }
                $it['TripSegments'][] = $seg;
                $its[] = $it;
            }
            unset($RecordLocator);
            unset($Passengers);
            unset($TicketNumbers);
            unset($AccountNumbers);
        }

        return $its;
    }

    private function parseEmailSegment($text)
    {
        $segment = [];

        if (preg_match("/{$this->opt($this->t('FLIGHT'))}\s+{$this->opt($this->t('SEAT'))}\s+{$this->opt($this->t('CABIN'))}\s+{$this->opt($this->t('GROUP'))}(\n.*){0,5}(?<AirlineName>[A-Z][A-Z\d]|[A-Z\d][A-Z])[ ]*(?<FlightNumber>\d+)\n+(?<Seats>\d+[A-Z])\n+(?<bookingCode>[A-Z]{1,2})\n/", $text, $m)
            || preg_match("/{$this->opt($this->t('FLIGHT'))}\s+.*\s*{$this->opt($this->t('SEAT'))}(\s|\n){$this->opt($this->t('BOARDING TIME'))}(\s|\n){$this->opt($this->t('GATE'))}(\s|\n)(?<Seats>\d{1,3}[A-Z])(.*\n){0,5}\s*(?<AirlineName>[A-Z][A-Z\d]|[A-Z\d][A-Z])[ ]*(?<FlightNumber>\d+)\n/", $text, $m)
            || preg_match("/{$this->opt($this->t('FLIGHT'))}\s+.*\s*{$this->opt($this->t('SEAT'))}(\s|\n)(.*\n){0,5}\s*(?<AirlineName>[A-Z][A-Z\d]|[A-Z\d][A-Z])[ ]*(?<FlightNumber>\d+)\s*(?<Seats>\d+[A-Z])\n/", $text, $m)
        ) {
            $segment['AirlineName'] = $m['AirlineName'];
            $segment['FlightNumber'] = $m['FlightNumber'];
            $segment['Seats'][] = $m['Seats'];

            if (!empty($m['bookingCode'])) {
                $segment['BookingClass'] = $m['bookingCode'];
            }
        }

        if (preg_match("/{$this->opt($this->t('TAKE-OFF'))}\s+{$this->opt($this->t('LANDING'))}\s+(?<DepTime>{$this->patterns['time']})\s+(?<DepCode>[A-Z]{3})\s+(?<ArrCode>[A-Z]{3})\s+(?<ArrTime>{$this->patterns['time']})\s+(?<DepDate>\d{1,2}\s*[[:alpha:]]+\s*\d{4})\s+(?<ArrDate>\d{1,2}\s*[[:alpha:]]+\s*\d{4})\s+(?<DepName>.{3,})\s+(?<ArrName>.{3,})(?<Terminal>\s+Terminal\s*(?<DepartureTerminal>.*))?(\s+Terminal\s*(?<ArrivalTerminal>.*))?\s+{$this->opt($this->t('FLIGHT'))}/u", $text, $m) // it-123724954.eml
            || preg_match("/{$this->opt($this->t('TAKE-OFF'))}\s+(?<DepCode>[A-Z]{3})\s+(?<ArrCode>[A-Z]{3})\s+{$this->opt($this->t('LANDING'))}\s+(?<DepTime>{$this->patterns['time']})\s+(?<ArrTime>{$this->patterns['time']})\s+(?<DepDate>\d{1,2}\s*[[:alpha:]]+\s*\d{4})\s+(?<ArrDate>\d{1,2}\s*[[:alpha:]]+\s*\d{4})(?<Terminal>\s+Terminal *(?<DepartureTerminal>.*))?(\s+Terminal\s*(?<ArrivalTerminal>.*))?\s*(?<DepName>.{3,})\s+(?<ArrName>.{3,})/u", $text, $m)
            || preg_match("/{$this->opt($this->t('TAKE-OFF'))}\s+(?<DepCode>[A-Z]{3})\s+(?<ArrCode>[A-Z]{3})\s+{$this->opt($this->t('LANDING'))}\s+(?<DepTime>{$this->patterns['time']})\s+(?<ArrTime>{$this->patterns['time']})\s+(?<DepDate>\d{4}\D\d+\D\d+\D)\s+(?<ArrDate>\d{4}\D\d+\D\d+\D)(?<Terminal>\s+Terminal *\:?\s*(?<DepartureTerminal>.*))?(\s+Terminal\s*\:?\s*(?<ArrivalTerminal>.*))?\s*(?<DepName>.+)\s+(?<ArrName>.+)/u", $text, $m) // lang = ko
        ) {
            $segment['DepCode'] = $m['DepCode'];
            $segment['ArrCode'] = $m['ArrCode'];
            $segment['DepDate'] = strtotime($m['DepDate'] . ' ' . $m['DepTime']);

            if (empty($segment['DepDate'])) {
                $segment['DepDate'] = strtotime($this->normalizeDate($m['DepDate'] . ' ' . $m['DepTime']));
            }
            $segment['ArrDate'] = strtotime($m['ArrDate'] . ' ' . $m['ArrTime']);

            if (empty($segment['ArrDate'])) {
                $segment['ArrDate'] = strtotime($this->normalizeDate($m['ArrDate'] . ' ' . $m['ArrTime']));
            }
            $segment['DepName'] = $m['DepName'];
            $segment['ArrName'] = $m['ArrName'];

            if (!empty($m['DepartureTerminal']) && !empty($m['ArrivalTerminal'])) {
                $segment['DepartureTerminal'] = $m['DepartureTerminal'];
                $segment['ArrivalTerminal'] = $m['ArrivalTerminal'];
            } elseif (!empty($m['DepartureTerminal'])) {
                $leftTerm = $this->pdf->FindSingleNode("//p[{$this->contains(trim($m['Terminal']))} and {$this->contains([$segment['AirlineName'] . $segment['FlightNumber'], $segment['AirlineName'] . ' ' . $segment['FlightNumber']], 'ancestor::div')}]/@style", null, true, "/left:(\d+)px;/");
                $leftArr = $this->pdf->FindSingleNode("//p[{$this->contains($segment['ArrName'])} and {$this->contains([$segment['AirlineName'] . $segment['FlightNumber'], $segment['AirlineName'] . ' ' . $segment['FlightNumber']], 'ancestor::div')}]/@style", null, true, "/left:(\d+)px;/");

                if (abs($leftArr - $leftTerm) < 50) {
                    $segment['ArrivalTerminal'] = $m['DepartureTerminal'];
                } else {
                    $segment['DepartureTerminal'] = $m['DepartureTerminal'];
                }
            } elseif (!empty($m['ArrivalTerminal'])) {
                $segment['ArrivalTerminal'] = $m['ArrivalTerminal'];
            }
        }

        if (preg_match("#(" . $this->t("CLASS") . ")\n(.*[a-z].*\n){0,5}(?<cabin>[A-Z ]+)\s*\n#", $text, $m)) {
            $segment['Cabin'] = $m['cabin'];
        }

        return $segment;
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
        //$this->logger->error($str);
        $in = [
            "#^\s*(\d{2})/(\d{2})/(\d{4})\s*-\s*(\d+:\d+)$#", //25/05/2016 - 20:50
            "#^(\d{4})\D(\d+)\D(\d+)\D\s*([\d\:]+)$#u", //2020년10월11일 20:40
        ];
        $out = [
            "$1.$2.$3 $4",
            "$3.$2.$1 $4",
        ];
        $str = preg_replace($in, $out, $str);

        //$this->logger->error($str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return $str;
    }

    private function sortNodes()
    {
        $pages = $this->pdf->XPath->query("//div[starts-with(@id, 'page')]");
        $html = '';

        foreach ($pages as $page) {
            $nodes = $this->pdf->XPath->query(".//p", $page);
            $rowArray = [];

            foreach ($nodes as $node) {
                $text = $this->pdf->FindSingleNode(".", $node);
                $top = $this->pdf->FindSingleNode("./@style", $node, true, "#top:(\d+)px;#");
                $left = $this->pdf->FindSingleNode("./@style", $node, true, "#left:(\d+)px;#");
                $rowArray[intval($top / 10) * 10000 + $left] = $text;
            }
            ksort($rowArray);
            $html .= implode("\n", $rowArray);
        }

        return $html;
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'contains(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }
}
