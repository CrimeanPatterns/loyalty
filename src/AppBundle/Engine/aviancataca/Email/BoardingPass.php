<?php

namespace AwardWallet\Engine\aviancataca\Email;

use AwardWallet\Engine\MonthTranslate;

// parsers with similar PDF-formats: airtransat/BoardingPass, asiana/BoardingPassPdf, aviancataca/TicketDetails, czech/BoardingPass, lotpair/BoardingPass, sata/BoardingPass, tamair/BoardingPassPDF(object), tapportugal/AirTicket, luxair/YourBoardingPassNonPdf, saudisrabianairlin/BoardingPass

class BoardingPass extends \TAccountChecker
{
    public $mailFiles = "aviancataca/it-6181783.eml, aviancataca/it-8988766.eml, aviancataca/it-9014344.eml, aviancataca/it-9037856.eml";

    public $reFrom = "nao-responder@avianca.com.br";
    public $reProvider = "@avianca.com.br";
    public $reSubject = [
        "en" => "Your Boarding Pass Confirmation",
        "pt" => "Seu Cartao de embarque Avianca",
    ];
    public $reBody = 'avianca.com.br';
    public $reBody2 = [
        "en" => "Thank you for using online check-in service",
        "pt" => "Obrigado por utilizar o serviço de check-in online",
    ];
    public $reBodyPdf = "Avianca";
    public $reBody2Pdf = [
        "en" => ["Boarding Pass", "DEPARTURE", "ARRIVAL"],
        "pt" => ["Cartão de Embarque", "Partida", "Chegada"],
    ];
    public $pdf;
    public $pdfNamePattern = "cartaoembarque.pdf";

    public static $dictionary = [
        "en" => [
            //html
            //			'Passenger:' => '',
            //			'Flight Boarding:' => '',
            //			'Flight:' => '',
            //			'From:' => '',
            //			'To:' => '',
            'dateRe' => '\d{2}\s*\w{3,10}\s*\d{4}\s*-\s*\d{2}:\d{2}',
            //			//pdf - all use in regexp
            'Boarding Pass'     => '(?:Cartão de Embarque \| )?Boarding Pass',
            'BOOKING REFERENCE' => '(?:LOCALIZADOR \| BOOKING REF\.|Reservation Code:)',
            'FROM'              => '(?:DE \| )?FROM',
            //			'ETKT' => 'ETKT',
            'FREQUENT FLYER' => 'FQTV',
            'FLIGHT'         => '(?:VOO \| )?FLIGHT',
            'SEAT'           => '(?:ASSENTO \| )?SEAT',
            'TAKE-OFF'       => '(?:DECOLAGEM \| )?DEPARTURE',
            'LANDING'        => '(?:POUSO \| )?ARRIVAL',
            'CLASS'          => '(?:CLASSE \| )?CLASS OF TRAVEL',
        ],
        "pt" => [
            //html
            //			'Passenger:' => 'Passageiro:',
            //			'Flight Boarding:' => 'Código da Reserva:',
            //			'Flight:' => 'Voo:',
            //			'From:' => 'Origem:',
            //			'To:' => 'Destino:',
            'dateRe' => '\d{2}\s*\w{3,10}\s*\d{4}\s*-\s*\d{2}:\d{2}',
            //			//pdf
            'Boarding Pass'     => 'Cartão de Embarque',
            'BOOKING REFERENCE' => 'Código da Reserva:',
            'FROM'              => 'DE',
            //			'ETKT' => 'ETKT',
            'FREQUENT FLYER' => 'FQTV',
            'FLIGHT'         => 'VOO',
            'SEAT'           => 'ASSENTO',
            'TAKE-OFF'       => 'Partida',
            'LANDING'        => 'Chegada',
            'CLASS'          => 'CLASSE',
        ],
    ];

    public $lang = '';
    // private $dateFirstFlight; // for aviancataca

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

        if (!isset($pdfs[0])) {
            $text = '';

            foreach ($pdfs as $pdf) {
                if (($text .= \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
                    continue;
                }
            }

            if (strpos($text, $this->reBodyPdf) == false) {
                return false;
            }

            foreach ($this->reBody2Pdf as $re) {
                if (strpos($text, $re[0]) !== false && strpos($text, $re[1]) !== false && strpos($text, $re[2]) !== false) {
                    return true;
                }
            }
        } else {
            $text = $parser->getHTMLBody();

            if (strpos($text, $this->reBody) == false) {
                return false;
            }

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
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdfs) && count($pdfs) > 0) {
            $this->pdf = clone $this->http;

            foreach ($pdfs as $pdf) {
                if (($text2 = \PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_COMPLEX)) !== null) {
                    $this->pdf->SetEmailBody($text2);
                    $text = strip_tags($this->sortNodes());

                    foreach ($this->reBody2Pdf as $lang => $re) {
                        if (strpos($text, $re[0]) !== false && strpos($text, $re[1]) !== false && strpos($text, $re[2]) !== false) {
                            $this->lang = $lang;

                            break;
                        }
                    }

                    if (empty($this->lang)) {
                        return null;
                    }
                    $type = 'Pdf';
                    $its = array_merge($its, $this->parseEmailPDF($text));
                } else {
                    continue;
                }
            }
        } else {
            foreach ($this->reBody2 as $lang => $re) {
                if (strpos($this->http->Response['body'], $re) !== false) {
                    $this->lang = $lang;

                    break;
                }
            }

            if (empty($this->lang)) {
                return null;
            }
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
        $result = [
            'emailType'  => 'BoardingPass' . $type . '_' . $this->lang,
            'parsedData' => ['Itineraries' => $its],
        ];

        // if (isset($this->dateFirstFlight) && $this->dateFirstFlight >= strtotime(('2019-07-01'))) {
        //     $result['providerCode'] = 'aviancataca';
        // }

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

    private function parseEmailHtml(): array
    {
        $its = [];
        $flightCount = count($this->http->FindNodes('//span[contains(., "' . $this->t('Passenger:') . '")]/following-sibling::span[1]'));

        for ($i = 1; $i <= $flightCount; $i++) {
            $seg = [];
            $RecordLocator = $this->http->FindSingleNode('(//span[contains(., "' . $this->t('Flight Boarding:') . '")])[' . $i . ']/following-sibling::span[1]', null, true, "#[A-Z\d]{5,6}#");
            $Passengers = $this->http->FindSingleNode('(//span[contains(., "' . $this->t('Passenger:') . '")])[' . $i . ']/following-sibling::span[1]');

            $flight = $this->http->FindSingleNode('(//span[contains(., "' . $this->t('Flight:') . '")])[' . $i . ']/following-sibling::span[1]');

            if (preg_match('#([\dA-Z]{2})(\d{2,5})#', $flight, $m)) {
                $seg['AirlineName'] = $m[1];
                $seg['FlightNumber'] = $m[2];
                $seg['Cabin'] = $this->http->FindSingleNode('(//span[contains(., "' . $this->t('Flight:') . '")])[' . $i . ']/following-sibling::span[3]');
            }

            $departAr = $this->http->FindNodes('(//span[normalize-space(.)="' . $this->t('From:') . '"])[' . $i . ']/ancestor::td[1]//span');
            $depart = implode("\n", $departAr);

            if (preg_match('#:\n([\w., ]+)\n(Terminal\s*([\w]*)\n)?(' . $this->t('dateRe') . ')#i', $depart, $m)) {
                $seg['DepName'] = $m[1];
                $seg['DepDate'] = strtotime($this->normalizeDate($m[4]));

                if (!empty($m[3])) {
                    $seg['DepartureTerminal'] = $m[3];
                }
                $seg['DepCode'] = TRIP_CODE_UNKNOWN;
            }

            $arriveAr = $this->http->FindNodes('(//span[normalize-space(.)="' . $this->t('To:') . '"])[' . $i . ']/ancestor::td[1]//span');
            $arrive = implode("\n", $arriveAr);

            if (preg_match('#:\n([\w., ]+)\n(Terminal\s*([\w]*)\n)?(' . $this->t('dateRe') . ')#i', $arrive, $m)) {
                $seg['ArrName'] = $m[1];
                $seg['ArrDate'] = strtotime($this->normalizeDate($m[4]));

                if (!empty($m[3])) {
                    $seg['ArrivalTerminal'] = $m[3];
                }
                $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
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

            // if (isset($seg['DepDate']) && !empty($seg['DepDate']) && !isset($this->dateFirstFlight)) {
            //     $this->dateFirstFlight = $seg['DepDate'];
            // }
        }

        return $its;
    }

    private function parseEmailPDF($text): array
    {
        $its = [];
        $segTexts = $this->splitText("#(?:^|\n)\s*(" . $this->t('Boarding Pass') . "\s*\n)#", $text);

        foreach ($segTexts as $stext) {
            $seg = [];

            if (preg_match("#" . $this->t('BOOKING REFERENCE') . "\s*(?:.*\n){0,5}\s*([A-Z\d]{5,6})\s*\n#Uu", $stext, $m)) {
                $RecordLocator = $m[1];
            }

            if (preg_match("#" . $this->t("Boarding Pass") . "[^\n]*\n\s*(.*)\s+" . $this->t("FROM") . "#u", $stext, $m)) {
                $Passengers = trim(str_replace('/ ', '', $m[1]));
            }

            if (preg_match("#" . $this->t("ETKT") . "\s*(?:.*\n){0,5}\s*(\d{8,25})\s*\n#u", $stext, $m)) {
                $TicketNumbers = $m[1];
            }

            if (preg_match("#" . $this->t("FREQUENT FLYER") . "\s*(?:.*\n){0,5}\s*([A-Z\d ]{8,})\s*\n#u", $stext, $m)) {
                $AccountNumbers = $m[1];
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

            // if (isset($seg['DepDate']) && !empty($seg['DepDate']) && !isset($this->dateFirstFlight)) {
            //     $this->dateFirstFlight = $seg['DepDate'];
            // }
        }

        return $its;
    }

    private function parseEmailSegment($text)
    {
        $segment = [];

        if (preg_match("#" . $this->t("FLIGHT") . "\s+.*\s*" . $this->t("SEAT") . "(\s|\n)(.*\n){0,5}\s*(?<AirlineName>[\dA-Z]{2})(?<FlightNumber>\d{1,5})\s*(?<Seats>\d{1,3}[A-Z])#", $text, $m)) {
            $segment['AirlineName'] = $m['AirlineName'];
            $segment['FlightNumber'] = $m['FlightNumber'];
            $segment['Seats'][] = $m['Seats'];
        }

        if (preg_match("#" . $this->t("TAKE-OFF") . "\s+" . $this->t("LANDING") . "\s+(?<DepTime>\d{2}:\d{2})\s+(?<DepCode>[A-Z]{3})\s+(?<ArrCode>[A-Z]{3})\s+(?<ArrTime>\d{2}:\d{2})\s+(?<DepDate>\d+\s*[\w]+\s*\d{4})\s+(?<ArrDate>\d+\s*[\w]+\s*\d{4})"
                . "(?<Terminal>\s+Terminal *(?<DepartureTerminal>.*))?(\s+Terminal\s*(?<ArrivalTerminal>.*))?\s*(?<DepName>.+)\s+(?<ArrName>.+)#u", $text, $m)) {
            $segment['DepCode'] = $m['DepCode'];
            $segment['ArrCode'] = $m['ArrCode'];
            $segment['DepDate'] = strtotime($m['DepDate'] . ' ' . $m['DepTime']);

            if (empty($segment['DepDate'])) {
                $segment['DepDate'] = strtotime($m['DepDate'] . ' ' . $m['DepTime']);
            }
            $segment['ArrDate'] = strtotime($m['ArrDate'] . ' ' . $m['ArrTime']);

            if (empty($segment['ArrDate'])) {
                $segment['ArrDate'] = strtotime($m['ArrDate'] . ' ' . $m['ArrTime']);
            }
            $segment['DepName'] = $m['DepName'];
            $segment['ArrName'] = $m['ArrName'];

            if (!empty($m['DepartureTerminal']) && !empty($m['ArrivalTerminal'])) {
                $segment['DepartureTerminal'] = $m['DepartureTerminal'];
                $segment['ArrivalTerminal'] = $m['ArrivalTerminal'];
            } elseif (!empty($m['DepartureTerminal'])) {
                $leftTerm = $this->pdf->FindSingleNode("(//p[contains(.,'" . trim($m['Terminal']) . "') and contains(./ancestor::div,'" . $segment['AirlineName'] . $segment['FlightNumber'] . "')])[1]/@style", null, true, "#left:(\d+)px;#");
                $leftArr = $this->pdf->FindSingleNode("(//p[contains(.,'" . $segment['ArrName'] . "') and contains(./ancestor::div,'" . $segment['AirlineName'] . $segment['FlightNumber'] . "')])[1]/@style", null, true, "#left:(\d+)px;#");

                if (!empty($leftTerm) && !empty($leftArr)) {
                    if (abs($leftArr - $leftTerm) < 50) {
                        $segment['ArrivalTerminal'] = $m['DepartureTerminal'];
                    } else {
                        $segment['DepartureTerminal'] = $m['DepartureTerminal'];
                    }
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
        $in = [
            "#^(\d{2})\s*(\w{3,10})\s*(\d{4})\s*-\s*(\d{2}:\d{2})$#", //02 Nov 2014 - 10:44
        ];
        $out = [
            "$1.$2.$3 $4",
        ];
        $str = preg_replace($in, $out, $str);

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
}
