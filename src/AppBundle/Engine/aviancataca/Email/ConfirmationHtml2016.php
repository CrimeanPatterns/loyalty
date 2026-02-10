<?php

namespace AwardWallet\Engine\aviancataca\Email;

use AwardWallet\Engine\MonthTranslate;

class ConfirmationHtml2016 extends \TAccountChecker
{
    use \DateTimeTools;

    public $mailFiles = "aviancataca/it-4841235.eml, aviancataca/it-4890611.eml, aviancataca/it-6321210.eml, aviancataca/it-6356901.eml, aviancataca/it-6378685.eml, aviancataca/it-6472143.eml, aviancataca/it-6548180.eml, aviancataca/it-6593602.eml";

    private $result = [];
    private $lang = '';
    private $subject = [
        'en' => 'Purchase Status',
        'es' => 'Estado de compra',
        'pt' => 'Estado de compra',
    ];
    private $dict = [
        'en' => [
            'booking'           => 'Booking code:',
            'passenger'         => 'Passenger Details',
            'passengerContacts' => 'contains(.,"Details") or contains(.,"information")',
            'total'             => 'Total for all the passengers',
            'leg'               => 'Leg:',
            'departure'         => 'Departure:',
            'arrival'           => 'Arrival:',
            'airline'           => 'Airline:',
            'operated'          => 'Operated by:',
            'plane'             => 'Plane:',
            'class'             => 'Class:',
        ],
        'es' => [
            'booking'           => 'Código de reserva:',
            'passenger'         => 'Detalles del Pasajero',
            'passengerContacts' => 'contains(.,"Datos") or contains(.,"información")',
            'total'             => 'Total para todos los Pasajeros',
            'leg'               => 'Trayecto:',
            'departure'         => 'Salida:',
            'arrival'           => 'Llegada:',
            'airline'           => 'Aerolínea:',
            'operated'          => 'Operado por:',
            'plane'             => 'Avión:',
            'class'             => 'Clase:',
        ],
        'pt' => [
            'booking'           => 'Código de reserva:',
            'passenger'         => 'DETALHES DO PASSAGEIRO',
            'passengerContacts' => 'contains(.,"Dados") or contains(.,"informação")',
            'total'             => 'Total para todos os Passageiros',
            'leg'               => 'Trecho:',
            'departure'         => 'Saída:',
            'arrival'           => 'Chegada:',
            'airline'           => 'Linha Aérea:',
            'operated'          => 'Operado por:',
            'plane'             => 'Avião:',
            'class'             => 'Classe:',
        ],
    ];
    private static $detectLang = [
        'en' => [
            ['Booking code', 'Arrival'],
        ],
        'pt' => [
            ['Código de reserva', 'Chegada'],
        ],
        'es' => [
            ['C&oacute;digo de reserva', 'Llegada'],
            ['Código de reserva', 'Llegada'],
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->detectLang();
        $this->result['Kind'] = 'T';
        $this->result['RecordLocator'] = $this->http->FindSingleNode("//*[contains(text(),'{$this->t('booking')}')]/ancestor::dt/following-sibling::dd[1]", null, false, '/[A-Z\d]{5,7}/');
        $this->result['Passengers'] = $this->http->FindNodes('//*[normalize-space(.)="' . $this->t('passenger') . '" or normalize-space(.)="' . strtoupper($this->t('passenger')) . '"]/ancestor::*[(name()="table" and position()=1) or (name()="div" and position()=2)]/following::table[string-length(normalize-space(.))>1][1]//tr[./following-sibling::tr[' . $this->t('passengerContacts') . ']]');
        $payment = $this->http->FindSingleNode('(//td[contains(.,"' . $this->t('total') . '") and not(.//td)]/descendant::*[normalize-space(.)][last()])[1]');

        if (preg_match('/([,.\d]+)\s*([A-Z]{3})\s*$/', $payment, $matches)) {
            $this->result['TotalCharge'] = $this->normalizePrice($matches[1]);
            $this->result['Currency'] = $matches[2];
        }
        $this->parseSegments();

        return [
            'emailType'  => 'TripConfirmation_' . $this->lang,
            'parsedData' => ['Itineraries' => [$this->result]],
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers['from'], 'aviancaonline@avianca.com') !== false) {
            return true;
        }

        if (stripos($headers['subject'], 'avianca.com') === false) {
            return false;
        }

        foreach ($this->subject as $text) {
            if (stripos($headers['subject'], $text) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $condition1 = count($this->http->FindNodes('//node()[contains(.,"avianca.com") or contains(.,"Administrative Center Avianca") or contains(.,"Centro Administrativo Avianca")]'));
        $condition2 = count($this->http->FindNodes('//a[contains(@href,"//www.avianca.com")]'));

        return ($condition1 || $condition2) && $this->detectLang();
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@avianca.com') !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$detectLang);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$detectLang);
    }

    protected function normalizePrice($string)
    {
        $string = preg_replace('/\s+/', '', $string);			// 11 507.00	->	11507.00
        $string = preg_replace('/[,.](\d{3})/', '$1', $string);	// 2,790		->	2790		or	4.100,00	->	4100,00
        $string = preg_replace('/,(\d{2})$/', '.$1', $string);	// 18800,00		->	18800.00

        return $string;
    }

    protected function normalizeDate($text)
    {
        if (preg_match('/(\w{3,})\s+(\d{1,2})[,\s]+(\d{2,4})\s*$/', $text, $matches)) { // Thursday, February 16, 2017
            $month = $matches[1];
            $day = $matches[2];
            $year = $matches[3];
        } elseif (preg_match('/(\d{1,2})\s+de\s+(\w{3,})\s+de\s+(\d{2,4})\s*$/', $text, $matches)) { // viernes, 09 de septiembre de 2016
            $day = $matches[1];
            $month = $matches[2];
            $year = $matches[3];
        }

        if (isset($day, $month, $year)) {
            return $day . ' ' . ($this->lang === 'en' ? $month : MonthTranslate::translate($month, $this->lang)) . ' ' . $year;
        }

        return null;
    }

    protected function parseSegments()
    {
        $segments = $this->http->XPath->query("//text()[contains(.,'{$this->t('leg')}')]/ancestor::tbody[1]");

        foreach ($segments as $segment) {
            $this->result['TripSegments'][] = $this->parseSegment($segment);
        }
    }

    protected function parseSegment($root)
    {
        $patterns = [
            'timeAirportTerminal' => '/^\s*(\d{1,2}:\d{2})\s+(.+)\s+-\s+([^,]+)(?:[,\s]+([^,]+))?\s*$/u',
        ];

        $segment = [];

        $dateValue = $this->http->FindSingleNode('./tr[.//text()[normalize-space(.)="' . $this->t('departure') . '"]]/preceding-sibling::tr[contains(.,",") and string-length(normalize-space(.))>7 and not(.//tr)][1]', $root);

        if ($dateValue) {
            $date = $this->normalizeDate($dateValue);
        }

        $departureTexts = $this->http->FindNodes('.//td[./descendant::text()[normalize-space(.)="' . $this->t('departure') . '"] and not(.//td)]/descendant::text()[normalize-space(.)!="' . $this->t('departure') . '"]', $root);
        $departureValue = implode(' ', $departureTexts);

        if (preg_match($patterns['timeAirportTerminal'], $departureValue, $matches)) {
            $timeDep = $matches[1];
            $segment['DepName'] = $matches[3] . ' (' . $matches[2] . ')';

            if (!empty($matches[4])) {
                $segment['DepartureTerminal'] = $matches[4];
            }
        }

        $arrivalTexts = $this->http->FindNodes('.//td[./descendant::text()[normalize-space(.)="' . $this->t('arrival') . '"] and not(.//td)]/descendant::text()[normalize-space(.)!="' . $this->t('arrival') . '"]', $root);
        $arrivalValue = implode(' ', $arrivalTexts);

        if (preg_match($patterns['timeAirportTerminal'], $arrivalValue, $matches)) {
            $timeArr = $matches[1];
            $segment['ArrName'] = $matches[3] . ' (' . $matches[2] . ')';

            if (!empty($matches[4])) {
                $segment['ArrivalTerminal'] = $matches[4];
            }
        }

        if (isset($date, $timeDep, $timeArr) && $date && $timeDep && $timeArr) {
            $segment['DepDate'] = strtotime($date . ', ' . $timeDep);
            $segment['ArrDate'] = strtotime($date . ', ' . $timeArr);
        }

        $flight = $this->http->FindSingleNode('.//*[(name()="th" or name()="td") and normalize-space(.)="' . $this->t('airline') . '" and not(.//*[name()="th" or name()="td"])]/following-sibling::td[1]', $root);

        if (preg_match('/([A-Z\d]{2})\s*(\d+)\s*$/', $flight, $matches)) {
            $segment['AirlineName'] = $matches[1];
            $segment['FlightNumber'] = $matches[2];
        }

        $operator = $this->http->FindSingleNode('.//*[(name()="th" or name()="td") and normalize-space(.)="' . $this->t('operated') . '" and not(.//*[name()="th" or name()="td"])]/following-sibling::td[1]', $root);

        if ($operator) {
            $segment['Operator'] = trim(str_replace('*', '', $operator));
        }

        $aircraft = $this->http->FindSingleNode('.//*[(name()="th" or name()="td") and normalize-space(.)="' . $this->t('plane') . '" and not(.//*[name()="th" or name()="td"])]/following-sibling::td[1]/descendant::text()[normalize-space()][1]', $root);

        if ($aircraft) {
            $segment['Aircraft'] = $aircraft;
        }

        $cabin = $this->http->FindSingleNode('.//*[(name()="th" or name()="td") and normalize-space(.)="' . $this->t('class') . '" and not(.//*[name()="th" or name()="td"])]/following-sibling::td[1]', $root, true, '/[\w ]+/u');

        if ($cabin) {
            $segment['Cabin'] = $cabin;
        }

        $segment['ArrCode'] = $segment['DepCode'] = TRIP_CODE_UNKNOWN;

        return $segment;
    }

    private function detectLang()
    {
        $body = $this->http->Response['body'];

        foreach (self::$detectLang as $lang => $dLang) {
            foreach ($dLang as $detect) {
                if (is_array($detect) && count($detect) === 2) {
                    if (stripos($body, $detect[0]) !== false && stripos($body, $detect[1]) !== false) {
                        $this->lang = $lang;

                        return true;
                    }
                } elseif (is_string($detect) && stripos($body, $detect) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function t($str)
    {
        if (!isset($this->dict) || !isset($this->dict[$this->lang][$str])) {
            return $str;
        }

        return $this->dict[$this->lang][$str];
    }
}
