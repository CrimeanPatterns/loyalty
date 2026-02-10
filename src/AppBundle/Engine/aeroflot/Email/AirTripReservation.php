<?php

namespace AwardWallet\Engine\aeroflot\Email;

class AirTripReservation extends \TAccountChecker
{
    use \DateTimeTools;

    public $mailFiles = "aeroflot/it-10425418.eml, aeroflot/it-3861254.eml, aeroflot/it-3953296.eml, aeroflot/it-4425287.eml, aeroflot/it-4530237.eml, aeroflot/it-4952656.eml, aeroflot/it-5010320.eml, aeroflot/it-8772115.eml";

    protected $l = 'ru';
    protected static $t = [
        'ru' => [
            'from'        => '[A-Z\d]{5,6}\s+на\s+сайте\s+авиакомпании ПАО «Аэрофлот» ✈|Оплата бронирования [A-Z\d]+|Информация для оплаты электронного билета',
            'body'        => '©\s*[0-9]+\s+Аэрофлот',
            'locator'     => ['Код бронирования', 'Код вашего бронирования'],
            'passengers'  => 'Пассажиры',
            'departure'   => 'Отправление',
            'arrival'     => 'Прибытие',
            'total'       => 'Общая стоимость',
            'addcalendar' => 'Добавить перелет в Календарь',
        ],
        'fr' => [
            'from'        => '[A-Z\d]{5,6}\s+Paiement de réservation sur le site web d’Aeroflot',
            'body'        => '©\s*[0-9]+\s+Aeroflot',
            'locator'     => 'Code de réservation',
            'passengers'  => 'Passagers',
            'departure'   => 'Départ',
            'arrival'     => 'Arrivée',
            'total'       => 'Prix total',
            'addcalendar' => 'Ajouter un vol au calendrier',
        ],
        'en' => [
            'from'        => '(Payment\s+of\s+[A-Z\d]{5,6}\s+booking|Booking\s+[A-Z\d]{5,6})\s+on\s+Aeroflot\s+airlines\s+website',
            'body'        => '©\s*[0-9]+\s+Aeroflot',
            'locator'     => ['Booking reference', 'Booking code'],
            'passengers'  => 'Passengers',
            'departure'   => 'Departure',
            'arrival'     => 'Arrival',
            'total'       => ['Total Price', 'Total price'],
            'addcalendar' => 'Add flight to Calendar',
        ],
    ];

    // Standard Methods

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@aeroflot.ru') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['subject'])) {
            foreach (self::$t as $key => $value) {
                if (preg_match('/' . $value['from'] . '/ui', $headers['subject'])) {
                    $this->l = $key;

                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,"//m.aeroflot.ru/b/info/booking") or contains(@href,"//pay.aeroflot.ru/aeropayment")]')->length > 0) {
            $text = html_entity_decode($parser->getHTMLBody());
            $text = substr($text, stripos($text, "©"));

            foreach (self::$t as $key => $value) {
                if (preg_match('/' . $value['body'] . '/ui', $text)) {
                    $this->l = $key;

                    return true;
                }
            }
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $ss = $parser->getSubject();

        foreach (self::$t as $key => $value) {
            if (preg_match('/' . $value['from'] . '/ui', $ss)) {
                $this->l = $key;

                break;
            }
        }

        $it = $this->ParseEmail();

        return [
            'parsedData' => [
                'Itineraries' => [$it],
            ],
            'emailType' => 'AirTripReservation_' . $this->l,
        ];
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$t);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$t);
    }

    protected function ParseEmail()
    {
        $it = [];
        $it['Kind'] = 'T';
        $xpath = '//table[' . $this->contains(self::$t[$this->l]['locator']) . ']';
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length > 0) {
            $root = $nodes->item(0);
            $xpathLocator = './/*[' . $this->contains(self::$t[$this->l]['locator'], 'normalize-space(text())') . ']/ancestor::tr[1]';
            $it['RecordLocator'] = $this->http->FindSingleNode($xpathLocator, $root, true, '/([A-Z\d]{5,6})/');
            $it['Passengers'] = [];
            $passengers = $this->http->XPath->query('.//table[starts-with(normalize-space(.),"' . self::$t[$this->l]['passengers'] . '") and not(.//table)]/tbody/tr', $root);

            foreach ($passengers as $p) {
                $it['Passengers'][] = $this->http->FindSingleNode('./td[1]', $p);
            }
            $it['TripSegments'] = [];
            $xpath = './/img[contains(@alt,"' . self::$t[$this->l]['addcalendar'] . '")]/ancestor::tr[1]/following-sibling::tr[1]';
            $rows = $this->http->XPath->query($xpath, $root);

            if ($rows->length == 0) {
                $xpath = './/strong[translate(normalize-space(),"0123456789", "ddddddddddd") = "dd:dd"]/ancestor::tr[1]/following-sibling::tr[1]';
                $rows = $this->http->XPath->query($xpath, $root);
            }

            foreach ($rows as $row) {
                $preceding = $this->http->XPath->query('preceding-sibling::tr[1]', $row);
//              print_r($row);
                $seg = [];

                $node = $this->http->XPath->query('td[1]', $preceding->item(0));
                $string = null;

                if ($node->length > 0) {
                    $string = $this->innerElem($node->item(0));
                }

                if (preg_match('/\b([A-Z]{2}|\d[A-Z]|[A-Z]\d)\s*(\d{1,5})/', $string, $matches) || preg_match('/^(.+)\s+(\d{1,5})/', $string, $matches)) {
                    $seg['AirlineName'] = $matches[1];
                    $seg['FlightNumber'] = $matches[2];
                }

                if (preg_match("/(.*)\n(\d+.*\..*)\n(.*)\(([A-Z]{1})\)/u", $string, $matches)) {
                    $seg['Aircraft'] = $matches[1];
                    $seg['Duration'] = $matches[2];
                    $seg['Cabin'] = trim($matches[3]);
                    $seg['BookingClass'] = $matches[4];
                }

                $dateDep = $this->http->FindSingleNode('td[3]/strong[2]', $preceding->item(0));
                $dateArr = $this->http->FindSingleNode('td[5]/strong[2]', $preceding->item(0));
                $timeDep = $this->http->FindSingleNode('td[3]/strong[1]', $preceding->item(0));
                $timeArr = $this->http->FindSingleNode('td[5]/strong[1]', $preceding->item(0));

                if ($dateDep && $dateArr && $timeDep && $timeArr) {
                    $dateDep = mb_strtolower($dateDep);
                    $dateArr = mb_strtolower($dateArr);
                    $timeArr = preg_replace("#^.*\b(\d+:\d+).*$#", '\\1', $timeArr);
                    $seg['DepDate'] = strtotime($this->dateStringToEnglish($dateDep) . ' ' . $timeDep);
                    $seg['ArrDate'] = strtotime($this->dateStringToEnglish($dateArr) . ' ' . $timeArr);
                }
                $airportRegexp = '#^(.*)\s+([A-Z]{3})$#u';
                $airportDep = $this->http->FindSingleNode('td[1]/span[1]', $row);

                if (preg_match($airportRegexp, $airportDep, $matches)) {
                    $seg['DepName'] = $matches[1];
                    $seg['DepCode'] = $matches[2];
                    $terminalDep = $this->http->XPath->query('td[1]/span[2]', $row);

                    if ($terminalDep->length > 0) {
                        $seg['DepartureTerminal'] = $this->http->FindSingleNode('.', $terminalDep->item(0));
                    } else {
                        $seg['DepartureTerminal'] = $this->http->FindSingleNode('td[1]/span[1]/following-sibling::table[1]', $row);
                    }
                } else {
                    $airportDep = $this->http->FindSingleNode('td[1]/table[1]', $row);

                    if (preg_match($airportRegexp, $airportDep, $matches)) {
                        $seg['DepName'] = $matches[1];
                        $seg['DepCode'] = $matches[2];
                    }
                    $terminalDep = $this->http->XPath->query('td[1]/span[1]', $row);

                    if ($terminalDep->length > 0) {
                        $seg['DepartureTerminal'] = $this->http->FindSingleNode('.', $terminalDep->item(0));
                    }
                }
                $airportArr = $this->http->FindSingleNode('td[3]/span[1]', $row);

                if (preg_match($airportRegexp, $airportArr, $matches)) {
                    $seg['ArrName'] = $matches[1];
                    $seg['ArrCode'] = $matches[2];
                }
                $terminalArr = $this->http->XPath->query('td[3]/span[2]', $row);

                if ($terminalArr->length > 0) {
                    $seg['ArrivalTerminal'] = $this->http->FindSingleNode('.', $terminalArr->item(0));
                }
                $it['TripSegments'][] = $seg;
            }
            $nodes = $this->http->XPath->query('.//tr[(' . $this->startsWith(self::$t[$this->l]['total']) . ') and not(.//tr)]', $root);

            if ($nodes->length > 0) {
                $priceTotal = $this->http->FindSingleNode('.', $nodes->item(0));

                if (preg_match('/([.\d ]+)[\s]*([A-Z]{3})/', $priceTotal, $matches)) {
                    $it['TotalCharge'] = $this->normalizePrice($matches[1]);
                    $it['Currency'] = $matches[2];
                }
            }
        }

        return $it;
    }

    protected function innerElem(\DOMNode $element)
    {
        $array = [];

        foreach ($element->childNodes as $value) {
            // Link https://en.wikipedia.org/wiki/Non-breaking_space
            $value->nodeValue = trim(trim($value->nodeValue, chr(0xC2) . chr(0xA0)));

            if (empty($value->nodeValue) !== true) {
                $array[] = $value->nodeValue;
            }
        }

        return join("\n", $array);
    }

    protected function normalizePrice($cost)
    {
        if (empty($cost)) {
            return 0.0;
        }
        $cost = preg_replace('/\s+/', '', $cost);			// 11 507.00	->	11507.00
        $cost = preg_replace('/[,.](\d{3})/', '$1', $cost);	// 2,790		->	2790		or	4.100,00	->	4100,00
        $cost = preg_replace('/,(\d{2})$/', '.$1', $cost);	// 18800,00		->	18800.00

        return (float) $cost;
    }

    private function startsWith($field, $text = 'normalize-space(.)')
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) use ($text) { return "starts-with({$text}, \"{$s}\")"; }, $field));
    }

    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) use ($text) { return "contains({$text}, \"{$s}\")"; }, $field));
    }
}
