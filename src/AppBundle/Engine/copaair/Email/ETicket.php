<?php

namespace AwardWallet\Engine\copaair\Email;

use AwardWallet\Engine\MonthTranslate;

class ETicket extends \TAccountChecker
{
    public $mailFiles = "copaair/it-2188634.eml, copaair/it-2202743.eml, copaair/it-3852300.eml, copaair/it-4416064.eml, copaair/it-5187126.eml, copaair/it-5236820.eml, copaair/it-5256574.eml, copaair/it-5256614.eml, copaair/it-5270094.eml";

    private $subjects = [
        'es' => ['Asunto: Recibo de boleto electrónico'],
        'pt' => ['Assunto: Recibo de bilhete eletrônico'],
        'en' => ['E-ticket receipt'],
    ];

    private $langDetectors = [
        'es' => ['Información de vuelo'],
        'pt' => ['Informações sobre o vôo'],
        'en' => ['Flight information'],
    ];

    private $lang = '';

    private static $dict = [
        'es' => [
            'Reservation number'     => 'Número de reserva',
            'Thank you for choosing' => 'Gracias por preferir',
            'Ticket number'          => 'Número de boleto',
            'Total Paid :'           => 'Total Pagado :',
            'Ticket'                 => 'Boleto',
            'Departs'                => 'Salida',
            'Arrives'                => 'Llegada',
            'Flight'                 => 'Vuelo',
            'Frequent Flyer Miles'   => 'Millas de Viajero Frecuente',
            'Details'                => 'Detalles',
            'Operated by'            => 'Operado por',
        ],
        'pt' => [
            'Reservation number'     => 'Número de reserva',
            'Thank you for choosing' => 'Obrigado por escolher a',
            'Ticket number'          => 'Número de boleto',
            'Total Paid :'           => 'Total Pago :',
            'Ticket'                 => 'Bilhete',
            'Departs'                => 'Saída',
            'Arrives'                => 'Chegada',
            'Flight'                 => 'Vôo',
            'Frequent Flyer Miles'   => 'Passageiro frequente Milhas',
            'Details'                => 'Detalhes',
            //            'Operated by' => '',
        ],
        'en' => [],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        if ($this->assignLang() === false) {
            return null;
        }

        $its = $this->parseEmail();

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => 'ETicket' . ucfirst($this->lang),
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ($phrases as $phrase) {
                if (stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, 'Copa Airline') !== false
            || stripos($from, '@copaair.com') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $condition1 = $this->http->XPath->query('//node()[contains(.,"www.copaair.com") or contains(.,"@copaair.com")]')->length === 0;
        $condition2 = $this->http->XPath->query('//a[contains(@href,"//www.copaair.com")]')->length === 0;

        if ($condition1 && $condition2) {
            return false;
        }

        return $this->assignLang();
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    protected function parseEmail()
    {
        /** @var \AwardWallet\ItineraryArrays\AirTrip $it */
        $it = [];
        $it['Kind'] = 'T';
        $it['RecordLocator'] = $this->http->FindSingleNode('(//text()[' . $this->contains($this->t('Reservation number')) . '])[1]', null, true, '/(\w+)$/');
        $it['Passengers'] = array_filter($this->http->FindNodes("//text()[" . $this->contains($this->t('Thank you for choosing')) . " and (contains(normalize-space(./ancestor::*[1]),'copaair.com') or contains(normalize-space(./following::*[1]),'copaair.com'))]/preceding::text()[normalize-space(.)][1]", null, '/(.+,.+)$/'));

        foreach ($it['Passengers'] as $key => $value) {
            $it['Passengers'][$key] = str_replace(",", "", $value);
        }
        $it['TicketNumbers'] = array_filter($this->http->FindNodes('//text()[' . $this->contains($this->t('Ticket number')) . ']', null, '/([\d]+)\s*$/'));

        $xpathFragmentTd = '(self::td or self::th)';
        $total = $this->http->FindSingleNode('(//div[' . $this->contains($this->t('Total Paid :')) . ']/ancestor::*[' . $xpathFragmentTd . ']/following-sibling::*[' . $xpathFragmentTd . '])[1]');

        if (preg_match('/\b(\D{3}) +(\d[,.\'\d]*)/', $total, $var)) {
            $it['Currency'] = strstr($var[1], ',') ? str_replace(',', '.', $var[1]) : $var[1];
            $it['TotalCharge'] = $var[2];
        }
        $it['BaseFare'] = $this->http->FindSingleNode('(//*[' . $this->contains($this->t('Ticket')) . ']/../../following-sibling::tr[1]/*[1])[1]', null, true, '/\D+\s+([.\d]+)$/');
        $xpath = '//text()[' . $this->eq($this->t('Departs')) . ']/ancestor::table[1]';
        $roots = $this->http->XPath->query($xpath);

        if ($roots->length === 0) {
            $this->logger->debug('Segments not found!');

            return false;
        }
        $it['TripSegments'] = [];
        $uniq = [];

        foreach ($roots as $root) {
            /** @var \AwardWallet\ItineraryArrays\AirTripSegment $seg */
            $seg = [];

            $pattern = '/(?<Time>\d+:\d+(?: *[ap]m)?)\s*(?<Name>.+)\s+\((?<Code>[A-Z]{3})\)/i';
            $departure = $this->http->FindSingleNode('./descendant::text()[' . $this->eq($this->t('Departs')) . ']/ancestor::tr[1]/following-sibling::tr[2]/*[1]', $root);

            if (preg_match($pattern, $departure, $matches)) {
                $timeDep = $matches['Time'];
                $seg['DepName'] = $matches['Name'];
                $seg['DepCode'] = $matches['Code'];
            }
            $arrival = $this->http->FindSingleNode('./descendant::text()[' . $this->eq($this->t('Arrives')) . ']/ancestor::tr[1]/following-sibling::tr[2]/*[2]', $root);

            if (preg_match($pattern, $arrival, $matches)) {
                $timeArr = $matches['Time'];
                $seg['ArrName'] = $matches['Name'];
                $seg['ArrCode'] = $matches['Code'];
            }

            // AirlineName
            // FlightNumber
            $flight = $this->http->FindSingleNode('./descendant::text()[' . $this->eq($this->t('Flight')) . ']/ancestor::tr[1]/following-sibling::tr[2]/*[3]', $root);

            if (preg_match('/([A-Z][A-Z\d]|[A-Z\d][A-Z])\*?\s+(\d+)/', $flight, $matches)) {
                $seg['AirlineName'] = $matches[1];
                $seg['FlightNumber'] = $matches[2];

                if (isset($uniq[$seg['FlightNumber']])) {
                    continue;
                }
                $uniq[$seg['FlightNumber']] = 1;
            }

            // TraveledMiles
            $miles = $this->http->FindSingleNode('./descendant::text()[' . $this->contains($this->t('Frequent Flyer Miles')) . ']/ancestor::tr[1]/following-sibling::tr[2]/*[4]', $root, true, '/^\d+$/');

            if ($miles !== null) {
                $seg['TraveledMiles'] = $miles;
            }

            // DepDate
            // ArrDate
            $dateText = $this->http->FindSingleNode('./descendant::tr[2]', $root);
            $dateNormal = $dateText !== null ? $this->normalizeDate($dateText) : false;

            if ($dateNormal) {
                if (isset($timeDep)) {
                    $seg['DepDate'] = strtotime($dateNormal . ' ' . $timeDep);
                }

                if (isset($timeArr)) {
                    $seg['ArrDate'] = strtotime($dateNormal . ' ' . $timeArr);
                }
            }

            $node = $this->http->FindSingleNode('./descendant::text()[' . $this->eq($this->t('Details')) . ']/ancestor::tr[1]/following-sibling::tr[2]/*[5]', $root);

            if (preg_match('/Clase: *\((?<BookingClass>[A-Z]{1,2})\)\s?Duración: *(.+)/i', $node, $m)) {
                $seg['BookingClass'] = $m['BookingClass'];
                $seg['Duration'] = $m[2];
            }
            $seg['Operator'] = $this->http->FindSingleNode('./descendant::text()[' . $this->starts($this->t('Operated by')) . ']', $root, null, '/[^:]+:\s*(\S.+)/');
            $it['TripSegments'][] = $seg;
        }

        return [$it];
    }

    private function t($phrase)
    {
        if (!isset(self::$dict[$this->lang]) || !isset(self::$dict[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dict[$this->lang][$phrase];
    }

    private function assignLang(): bool
    {
        foreach ($this->langDetectors as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if ($this->http->XPath->query('//node()[contains(normalize-space(.),"' . $phrase . '")]')->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'normalize-space(' . $node . ')="' . $s . '"'; }, $field)) . ')';
    }

    private function starts($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'starts-with(normalize-space(' . $node . '),"' . $s . '")'; }, $field)) . ')';
    }

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'contains(normalize-space(' . $node . '),"' . $s . '")'; }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) { return preg_quote($s, '/'); }, $field)) . ')';
    }

    private function normalizeDate(string $string)
    {
        if (preg_match('/\b([^\d\W]{3,})\s+(\d{1,2})\s*,\s*(\d{4})$/u', $string, $matches)) { // Octubre 22, 2018
            $month = $matches[1];
            $day = $matches[2];
            $year = $matches[3];
        }

        if (isset($day) && isset($month) && isset($year)) {
            if (preg_match('/^\s*(\d{1,2})\s*$/', $month, $m)) {
                return $m[1] . '/' . $day . ($year ? '/' . $year : '');
            }

            if ($this->lang !== 'th') {
                $month = str_replace('.', '', $month);
            }

            if (($monthNew = MonthTranslate::translate($month, $this->lang)) !== false) {
                $month = $monthNew;
            }

            return $day . ' ' . $month . ($year ? ' ' . $year : '');
        }

        return false;
    }
}
