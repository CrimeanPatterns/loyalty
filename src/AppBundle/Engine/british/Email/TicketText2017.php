<?php

namespace AwardWallet\Engine\british\Email;

use AwardWallet\Engine\MonthTranslate;

class TicketText2017 extends \TAccountChecker
{
    public $mailFiles = "british/it-6409333.eml, british/it-6538088.eml, british/it-8562661.eml";

    private $lang = '';
    private $langDetectors = [
        'es' => ['Su itinerario'],
        'en' => ['Your Itinerary'],
        'de' => ['Reiseplan'],
    ];

    private static $dict = [
        'es' => [
            'Itineraries End' => 'Atentamente,',
            'Segments Start'  => "Su itinerario\n",
            'Segments End'    => "Lista de pasajeros\n",
            'Record Locator'  => 'Referencia de la reserva',
            'Total Charge'    => 'Pago total',
            'Ticket Numbers'  => 'Número(s) del/de los billete(s):',
            'Departure'       => 'Salida',
            'Arrival'         => 'Llegada',
            //			'Seats' => '',
        ],
        'en' => [
            'Itineraries End' => 'Yours sincerely,',
            'Segments Start'  => "Your Itinerary\n",
            'Segments End'    => "Passenger list\n",
            'Record Locator'  => 'booking reference',
            'Total Charge'    => 'Payment Total',
            'Ticket Numbers'  => 'Ticket Number(s):',
            'Departure'       => 'Depart',
            'Arrival'         => 'Arrive',
            'Seats'           => 'Seat selection Seat',
        ],
        'de' => [
            'Itineraries End' => 'Mit freundlichen Grüßen,',
            'Segments Start'  => "Reiseplan\n",
            'Segments End'    => "Passagierliste\n",
            'Record Locator'  => 'Buchungsreferenz',
            'Total Charge'    => 'Gesamtbetrag',
            'Ticket Numbers'  => 'Ticketnummer(n):',
            'Departure'       => 'Abflug',
            'Arrival'         => 'Ankunft',
            //			'Seats' => '',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['from'], 'ticket@email.ba.com') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (empty($body)) {
            $body = $parser->getPlainBody();
        }
        $phrases = [
            'British Airways Customer',
            'British Airways Plc',
            'by British Airways',
            'with British Airways',
            'http://ba.com',
        ];

        return $this->arrikey($body, $phrases) !== false && $this->arrikey($body, $this->langDetectors) !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '.ba.com') !== false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (empty($body)) {
            $body = $parser->getPlainBody();
        }
        $its = [];

        if ($this->lang = $this->arrikey($body, $this->langDetectors)) {
            $text = $this->htmlToText($this->strCut($body, null, $this->t('Itineraries End')), false);
            $segments = html_entity_decode($this->strCut($text, $this->t('Segments Start'), $this->t('Segments End')));

            if (!empty($segments)) {
                $its[] = $this->parseAir($text, $segments);
            }
        }

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => 'TicketText2017' . ucfirst($this->lang),
        ];
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function t($s)
    {
        if (isset($this->lang) && isset(self::$dict[$this->lang][$s])) {
            return self::$dict[$this->lang][$s];
        }

        return $s;
    }

    /**
     * TODO: In php problems with "Type declarations", so i did so.
     * Are case sensitive. Example:
     * <pre>
     * var $reBody = ['en' => ['Reservation Modify'],];
     * var $reSubject = ['Reservation Modify']
     * </pre>.
     *
     * @param string $haystack
     *
     * @return int, string, false
     */
    private function arrikey($haystack, array $arrayNeedle)
    {
        foreach ($arrayNeedle as $key => $needles) {
            if (is_array($needles)) {
                foreach ($needles as $needle) {
                    if (stripos($haystack, $needle) !== false) {
                        return $key;
                    }
                }
            } elseif (stripos($haystack, $needles) !== false) {
                return $key;
            }
        }

        return false;
    }

    private function parseAir($text, $segments)
    {
        $result = ['Kind' => 'T', 'Passengers' => []];

        $result['RecordLocator'] = $this->match('/' . $this->t('Record Locator') . '[^:]*:\s*([A-Z\d]{5,7})/u', $text);

        if (preg_match('/' . $this->t('Total Charge') . ':\s*([A-Z]{3})\s*([\d.,]+)\b/', $text, $matches)) {
            $result['Currency'] = $matches[1];
            $result['TotalCharge'] = (float) $matches[2];
        }

        if (preg_match_all('/([-\d]+)\s*\(([A-Z\s]{3,})\)/', $this->strCut($text, $this->t('Ticket Numbers'), "\n"), $matches)) {
            $result['TicketNumbers'] = $matches[1];
            $result['Passengers'] = $matches[2];
        }

        // Segments
        foreach ($this->splitter('/(\b[A-Z\d]{2}\s*\d+:.+? \|)/', $segments) as $value) {
            $i = ['DepCode' => TRIP_CODE_UNKNOWN, 'ArrCode' => TRIP_CODE_UNKNOWN];
            $i += $this->matchSubpattern('/(?<AirlineName>[A-Z\d]{2})\s*(?<FlightNumber>\d+):/', $value);
            $i += $this->matchSubpattern('/' . $this->t('Departure') . ':(?<DepDate>.+?) - (?<DepName>.+?)(?:\s*- Terminal (?<DepartureTerminal>\w+))?\n/', $value);
            $i += $this->matchSubpattern('/' . $this->t('Arrival') . ':(?<ArrDate>.+?) - (?<ArrName>.+?)(?:\s*- Terminal (?<ArrivalTerminal>\w+))?\n/', $value);

            if (isset($i['DepDate']) && isset($i['ArrDate'])) {
                $i['DepDate'] = strtotime($this->normalizeDate($i['DepDate']), false);
                $i['ArrDate'] = strtotime($this->normalizeDate($i['ArrDate']), false);
            }

            if (preg_match_all('/' . $this->t('Seats') . ' (\w+)\b/', $value, $matches)) {
                $i['Seats'] = implode(', ', $matches[1]);
            }

            $result['TripSegments'][] = $i;
        }

        return $result;
    }

    //========================================
    // Auxiliary methods
    //========================================

    /**
     * TODO: The experimental method.
     * If several groupings need to be used
     * Named subpatterns not accept the syntax (?<Name>) and (?'Name').
     *
     * @version v0.1
     *
     * @param type $pattern
     * @param type $text
     *
     * @return type
     */
    private function matchSubpattern($pattern, $text)
    {
        if (preg_match($pattern, $text, $matches)) {
            foreach ($matches as $key => $value) {
                if (is_int($key)) {
                    unset($matches[$key]);
                }
            }

            if (!empty($matches)) {
                return array_map([$this, 'normalizeText'], $matches);
            }
        }

        return [];
    }

    private function match($pattern, $text, $allMatches = false)
    {
        if (preg_match($pattern, $text, $matches)) {
            if ($allMatches) {
                array_shift($matches);

                return array_map([$this, 'normalizeText'], $matches);
            } else {
                return $this->normalizeText(count($matches) > 1 ? $matches[1] : $matches[0]);
            }
        } elseif ($allMatches) {
            return [];
        }
    }

    private function normalizeDate($string)
    {
        if (preg_match('/^(\d{1,2})\s+([^\d\s]{3,})\s+(\d{4})\s+(\d{1,2}:\d{2})$/', $string, $matches)) {
            $day = $matches[1];
            $month = $matches[2];
            $year = $matches[3];
            $time = $matches[4];
        }

        if ($day && $month && $year && $time) {
            if ($this->lang !== 'th') {
                $month = str_replace('.', '', $month);
            }

            if (($monthNew = MonthTranslate::translate($month, $this->lang)) !== false) {
                $month = $monthNew;
            }

            return $day . ' ' . $month . ' ' . $year . ', ' . $time;
        }

        return false;
    }

    private function normalizeText($string)
    {
        return trim(preg_replace('/\s{2,}/', ' ', str_replace("\n", " ", $string)));
    }

    private function htmlToText($string, $view = false)
    {
        $text = preg_replace('/<[^>]+>/', "\n", html_entity_decode($string));

        if ($view) {
            return preg_replace('/\n{2,}/', "\n", $text);
        }

        return $text;
    }

    /**
     * Quick change preg_match. 1000 iterations takes 0.0008 seconds,
     * while similar <i>preg_match('/LEFT\n(.*?)RIGHT')</i> 0.300 sec.
     * <p>
     * Example:
     * <b>LEFT</b> <i>cut text</i> <b>RIGHT</b>
     * </p>
     * <pre>
     * findСutSection($plainText, 'LEFT', 'RIGHT')
     * findСutSection($plainText, 'LEFT', ['RIGHT', PHP_EOL])
     * </pre>.
     */
    private function strCut($input, $searchStart, $searchFinish = null)
    {
        $inputResult = null;

        if (!empty($searchStart)) {
            $input = mb_strstr($input, $searchStart);
        }

        if (is_array($searchFinish)) {
            $i = 0;

            while (empty($inputResult)) {
                if (isset($searchFinish[$i])) {
                    $inputResult = mb_strstr($input, $searchFinish[$i], true);
                } else {
                    return false;
                }
                $i++;
            }
        } elseif (!empty($searchFinish)) {
            $inputResult = mb_strstr($input, $searchFinish, true);
        } else {
            $inputResult = $input;
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
    private function splitter($pattern, $text)
    {
        $result = [];

        $array = preg_split($pattern, $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        array_shift($array);

        for ($i = 0; $i < count($array) - 1; $i += 2) {
            $result[] = $array[$i] . $array[$i + 1];
        }

        return $result;
    }
}
