<?php

namespace AwardWallet\Engine\ctrip\Email;

use AwardWallet\Engine\MonthTranslate;

class FlightReservation extends \TAccountChecker
{
    public $mailFiles = "ctrip/it-2975332.eml, ctrip/it-3238648.eml, ctrip/it-3238659.eml, ctrip/it-3238664.eml, ctrip/it-3238670.eml, ctrip/it-3238694.eml, ctrip/it-5014656.eml, ctrip/it-5536645.eml, ctrip/it-5553329.eml, ctrip/it-6212213.eml, ctrip/it-6258618.eml, ctrip/it-6308079.eml, ctrip/it-6319736.eml, ctrip/it-6370652.eml, ctrip/it-8084946.eml, ctrip/it-13242230.eml";

    public $reFrom = '@ctrip.com';
    public $reSubject = [
        'en'  => 'Payment Successful',
        'en2' => 'Flight reservation confirmation',
    ];
    public $reBody = 'ctrip';
    public $reBody2 = [
        'en' => [
            'Thank you for choosing Ctrip',
            'Your ticket(s) have been issued.',
        ],
        'fr' => [
            'Département des réservations de vols internationaux de Ctrip',
            'Merci de choisir Ctrip',
        ],
        'es' => 'Gracias por elegir Ctrip.',
        'zh' => [
            '感謝您選擇Ctrip',
            '感謝您使用 Ctrip',
        ],
    ];

    public static $dictionary = [
        'en' => [
            "Order No."            => ["Order No.", "Booking no.", "Booking No."],
            "(Surname/Givennames)" => ["(Surname/Givennames)", "(Last/First Mid)"],
        ],
        'fr' => [
            "Order No."            => "Réservation n°",
            "Booking date"         => "Date de réservation",
            "(Surname/Givennames)" => "(Nom de famille/Prénom-deuxième prénom)",
            "Nationality"          => "Nationalité",
            "Total amount"         => "Montant total",
            "operated by"          => "opéré par",
        ],
        'es' => [
            "Order No."            => "No. de reserva",
            "Booking date"         => "Fecha de reserva",
            "(Surname/Givennames)" => "NOTTRANSLATED",
            "Nationality"          => "Pasaporte",
            "Total amount"         => "Total Refund Amount",
            //            "operated by" => "",
        ],
        'zh' => [
            "Order No."            => "訂單編號",
            "Booking date"         => "預訂日期",
            "(Surname/Givennames)" => "(姓/名)",
            "Nationality"          => "國家或地區",
            "Total amount"         => "訂單總額",
            //            "operated by" => "",
        ],
    ];

    public $typeSegments = '';
    public $lang = 'en';

    public function parseHtml(&$itineraries)
    {
        $it = [];
        $it['Kind'] = 'T';

        // TripNumber
        $it['TripNumber'] = $this->nextText($this->t("Order No."));

        // RecordLocator
        if (!empty($it['TripNumber'])) {
            $it['RecordLocator'] = CONFNO_UNKNOWN;
        }

        // ReservationDate
        $bookingDate = $this->nextText($this->t("Booking date"));

        if ($bookingDate) {
            $it['ReservationDate'] = strtotime($this->normalizeDate($bookingDate));
        }

        // Passengers
        $it['Passengers'] = $this->http->FindNodes("//text()[" . $this->eq($this->t("(Surname/Givennames)")) . "]/preceding::text()[normalize-space(.)][1]");

        if (count($it['Passengers']) == 0) {
            $it['Passengers'] = $this->http->FindNodes("//text()[" . $this->eq($this->t("Nationality")) . "]/ancestor::tr[1]/preceding-sibling::tr[1]//text()[normalize-space(.) and not(contains(.,')'))]");
        }

        if (count($it['Passengers']) === 0) {
            $it['Passengers'] = $this->http->FindNodes("//tr[contains(., 'number') and not(descendant::tr)]/preceding-sibling::tr[2 and contains(., '/')]");
        }

        // TotalCharge
        $it['TotalCharge'] = $this->amount($this->http->FindSingleNode("//td[" . $this->eq($this->t("Total amount")) . "]/following-sibling::td[1]"));

        // Currency
        $it['Currency'] = $this->http->FindSingleNode("//td[" . $this->eq($this->t("Total amount")) . "]/following-sibling::td[1]", null, true, "#^([A-Z]{3})#");

        $xpath = "//img[contains(@src, '/flight_arrow')]/ancestor::tr[1]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length > 0) {
            $this->typeSegments = '1';
            $it['TripSegments'] = $this->parseSegmentsFormat1($nodes);
        } else {
            $xpath = "//img[contains(@src, '/logo/pubFlights')]/ancestor::tr[1]|//img[contains(@src, '/arrow.png')]/ancestor::tr[1]/../tr[2]";
            $nodes = $this->http->XPath->query($xpath);

            if ($nodes->length > 0) {
                $this->typeSegments = '2';
                $it['TripSegments'] = $this->parseSegmentsFormat2($nodes);
            } else {
                $this->http->Log("segments root not found: $xpath", LOG_LEVEL_NORMAL);
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
        if (stripos($headers['from'], $this->reFrom) === false) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            if (strpos($headers['subject'], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (stripos($body, $this->reBody) === false) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if (is_string($re) && strpos($body, $re) !== false) {
                return true;
            } elseif (is_array($re)) {
                foreach ($re as $r) {
                    if (stripos($body, $r) !== false) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getHeader('date'));
        $this->http->FilterHTML = false;

        $body = $parser->getHTMLBody();

        foreach ($this->reBody2 as $lang => $re) {
            if (is_string($re) && strpos($body, $re) !== false) {
                $this->lang = $lang;

                break;
            } elseif (is_array($re)) {
                foreach ($re as $r) {
                    if (stripos($body, $r) !== false) {
                        $this->lang = $lang;

                        break;
                    }
                }
            }
        }

        $itineraries = [];
        $this->parseHtml($itineraries);

        $result = [
            'emailType'  => $a[count($a = explode('\\', __CLASS__)) - 1] . $this->typeSegments . ucfirst($this->lang),
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

    private function parseSegmentsFormat1($nodes)
    {
        $ts = [];

        foreach ($nodes as $root) {
            $itsegment = [];

            // FlightNumber
            $itsegment['FlightNumber'] = $this->http->FindSingleNode("./preceding-sibling::tr[1]/descendant::text()[normalize-space(.)][2]", $root, true, "#^\w{2}(\d+)$#");

            $node = implode(" ", $this->http->FindNodes("./td[1]//text()[normalize-space(.)]", $root));

            if (preg_match("#(.+)\s+([A-Z]{3})\s+(.+?)\s*(T[\dA-Z\-]+)?$#", $node, $m)) {
                // DepCode
                $itsegment['DepCode'] = $m[2];

                // DepName
                $itsegment['DepName'] = $m[3];

                // DepartureTerminal
                if (isset($m[4]) && !empty($m[4])) {
                    $itsegment['DepartureTerminal'] = $m[4];
                }

                // DepDate
                $itsegment['DepDate'] = strtotime($this->normalizeDate($m[1]));
            }

            $node = implode(" ", $this->http->FindNodes("./td[3]//text()[normalize-space(.)]", $root));

            if (preg_match("#(.+)\s+([A-Z]{3})\s+(.+?)\s*(T[\dA-Z\-]+)?$#", $node, $m)) {
                // ArrCode
                $itsegment['ArrCode'] = $m[2];

                // ArrName
                $itsegment['ArrName'] = $m[3];

                // ArrivalTerminal
                if (isset($m[4]) && !empty($m[4])) {
                    $itsegment['ArrivalTerminal'] = $m[4];
                }

                // ArrDate
                $itsegment['ArrDate'] = strtotime($this->normalizeDate($m[1]));
            }

            // AirlineName
            $itsegment['AirlineName'] = $this->http->FindSingleNode("./preceding-sibling::tr[1]/descendant::text()[normalize-space(.)][2]", $root, true, "#^(\w{2})\d+$#");

            // Cabin
            $itsegment['Cabin'] = $this->http->FindSingleNode("./preceding-sibling::tr[1]/descendant::text()[normalize-space(.)][3]", $root);

            // Duration
            $itsegment['Duration'] = $this->http->FindSingleNode("./td[2]", $root, true, '#:\s+(.+)#');

            $ts[] = $itsegment;
        }

        return $ts;
    }

    private function parseSegmentsFormat2($nodes)
    {
        $patterns = [
            'nameTerminal' => '/^(.+?)\s+T([A-Z\d]+)$/',
        ];

        $ts = [];

        foreach ($nodes as $root) {
            $itsegment = [];

            $xpathFragment1 = "not({$this->contains($this->t('operated by'))} or contains(.,'Stop'))";

            // AirlineName
            // FlightNumber
            $flight = $this->http->FindSingleNode('./td[2]/descendant::text()[normalize-space(.)][2]', $root);

            if (preg_match('/^(?<airline>[A-Z][A-Z\d]|[A-Z\d][A-Z])?\s*(?<flightNumber>\d+)$/', $flight, $matches)) {
                if (!empty($matches['airline'])) {
                    $itsegment['AirlineName'] = $matches['airline'];
                }
                $itsegment['FlightNumber'] = $matches['flightNumber'];
            }

            // DepCode
            $itsegment['DepCode'] = $this->http->FindSingleNode("./following-sibling::tr[$xpathFragment1][1]/td[normalize-space(.)][2]", $root, true, "#^([A-Z]{3})$#");

            // DepName
            // DepartureTerminal
            $airportDep = $this->http->FindSingleNode("./following-sibling::tr[$xpathFragment1][2]/td[normalize-space(.)][2]", $root);

            if (preg_match($patterns['nameTerminal'], $airportDep, $matches)) {
                $itsegment['DepName'] = $matches[1];
                $itsegment['DepartureTerminal'] = $matches[2];
            } elseif ($airportDep) {
                $itsegment['DepName'] = $airportDep;
            }

            // DepDate
            $itsegment['DepDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("./following-sibling::tr[$xpathFragment1][2]/td[normalize-space(.)][1]", $root) . ', ' . $this->http->FindSingleNode("./following-sibling::tr[$xpathFragment1][1]/td[normalize-space(.)][1]", $root)));

            // ArrCode
            $itsegment['ArrCode'] = $this->http->FindSingleNode("./following-sibling::tr[$xpathFragment1][3]/td[normalize-space(.)][2]", $root, true, "#^([A-Z]{3})$#");

            // ArrName
            // ArrivalTerminal
            $airportArr = $this->http->FindSingleNode("./following-sibling::tr[$xpathFragment1][4]/td[normalize-space(.)][2]", $root);

            if (preg_match($patterns['nameTerminal'], $airportArr, $matches)) {
                $itsegment['ArrName'] = $matches[1];
                $itsegment['ArrivalTerminal'] = $matches[2];
            } elseif ($airportArr) {
                $itsegment['ArrName'] = $airportArr;
            }

            // ArrDate
            $itsegment['ArrDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("./following-sibling::tr[$xpathFragment1][4]/td[normalize-space(.)][1]", $root) . ', ' . $this->http->FindSingleNode("./following-sibling::tr[$xpathFragment1][3]/td[normalize-space(.)][1]", $root)));

            // Cabin
            $itsegment['Cabin'] = $this->http->FindSingleNode("./td[2]/descendant::text()[normalize-space(.)][3]", $root);

            // Duration
            $itsegment['Duration'] = $this->http->FindSingleNode("./td[3]", $root);

            $ts[] = $itsegment;
        }

        return $ts;
    }

    private function nextText($field, $root = null, $n = 1)
    {
        $rule = $this->eq($field);

        return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::text()[normalize-space(.)][{$n}]", $root);
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
        $year = date("Y", $this->date);
        $in = [
            '/^[^\d\s]+\s+(\d+\s+[^\d\s]+\s+\d{4},\s+\d+:\d+)$/', // Tue 22 Nov 2016, 19:55
            '/^(\d+)\s+([^\d\s]+)\.\s+(\d{4}),\s+(\d+:\d+)$/', // 10 dic. 2016, 12:35
            '/^(\d{4})年\s*(\d{1,2})月\s*(\d{1,2})日,?\s+(\d{1,2}:\d{2})$/', // 1997年 4月 30日, 12:35
        ];
        $out = [
            '$1',
            '$1 $2 $3, $4',
            '$1-$2-$3, $4',
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match('/\d+\s+([^\d\s]+)\s+\d{4}/', $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
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

    private function amount($s)
    {
        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $this->re("#([\d\,\.]+)#", $s)));
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) {
            return "normalize-space(.)=\"{$s}\"";
        }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) {
            return "starts-with(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(.), \"{$s}\")";
        }, $field));
    }
}
