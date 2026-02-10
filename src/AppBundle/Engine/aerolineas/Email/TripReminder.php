<?php

namespace AwardWallet\Engine\aerolineas\Email;

class TripReminder extends \TAccountChecker
{
    public $mailFiles = "aerolineas/it-4049659.eml, aerolineas/it-4168940.eml";

    private $lang = '';

    private $detects = [
        'es' => ['FECHA', 'SALIDA'],
    ];

    private static $dict = [
        'es' => [
            'FECHA'             => ['FECHA', 'Fecha'],
            'SALIDA'            => ['SALIDA', 'Salida'],
            'Código de Reserva' => ['Código de Reserva', 'CÓDIGO DE RESERVACIÓN'],
        ],
    ];

    private $year;

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], 'no-reply@aerolineas.com') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        return $this->http->XPath->query('//a[contains(@href, "aerolineas")] | //img[contains(@src, "aerolineas")]')->length > 0
            && (
                false !== stripos($body, 'Informamos el estado de su vuelo')
                || false !== stripos($body, 'Esperamos volver a verte a bordo')
            );
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@aerolineas.com') !== false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->assignLang();
        $this->year = getdate(strtotime($parser->getHeader('date')))['year'];
        $html = $parser->getHTMLBody();
        $nbsp = chr(194) . chr(160);
        $html = str_replace([$nbsp, '&nbsp;'], [' ', ' '], $html);
        $this->http->SetEmailBody($html);
        $it = $this->parseEmail();

        return [
            'parsedData' => [
                'Itineraries' => [$it],
            ],
            'emailType' => 'TripReminder' . $this->lang,
        ];
    }

    public static function getEmailLanguages()
    {
        return ['es'];
    }

    public static function getEmailTypesCount()
    {
        return 1;
    }

    private function parseEmail()
    {
        $it = ['Kind' => 'T'];

        $it['RecordLocator'] = $this->http->FindSingleNode("//table[{$this->starts($this->t('Código de Reserva'))} and not(.//table)]", null, true, "/{$this->opt($this->t('Código de Reserva'))}:\s*([A-Z\d]{6})/i");

        $it['Passengers'] = [];

        $it['TripSegments'] = [];

        $rows = $this->http->XPath->query("//tr[{$this->starts($this->t('FECHA'))} and {$this->contains($this->t('SALIDA'))} and not(.//tr)]/following-sibling::tr");

        foreach ($rows as $row) {
            $passengers = $this->http->FindSingleNode('./td[6]', $row);
            $it['Passengers'] = array_merge($it['Passengers'], explode(',', $passengers));

            if ($this->http->XPath->query('./td[contains(.,":")]', $row)->length > 0) {
                $seg = [];
                $date = $this->http->FindSingleNode('./td[1]', $row, true, '/[^\s\d]{3},\s+((?:\d{1,2}\s+[^\s\d]{3}|[^\s\d]{3}\s+\d{1,2})(?:\s+\d{2,4}|))/');
                $timeDep = $this->http->FindSingleNode('./td[2]', $row, true, '/(\d{2}:\d{2})/');
                $timeArr = $this->http->FindSingleNode('(./td[3]//text())[1]', $row, true, '/(\d{2}:\d{2})/');

                if ($date && $timeDep && $timeArr) {
                    $date = str_ireplace(
                        ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'],
                        ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'],
                    $date);

                    if (!preg_match('/ \d{4}$/', $date)) {
                        $date .= ' ' . $this->year;
                    }
                    $seg['DepDate'] = strtotime($timeDep, strtotime($date));
                    $plusOneDay = $this->http->XPath->query('./td[3]//text()[contains(.,"+1 día")]', $row)->length > 0 ? ', +1 days' : ' ';
                    $seg['ArrDate'] = strtotime($timeArr . $plusOneDay, strtotime($date));
                }
                $flight = $this->http->FindSingleNode('./td[4]', $row);

                if (preg_match('/^\s*([A-Z\d][A-Z]|[A-Z][A-Z\d])\s*(\d{1,5})\b/', $flight, $matches)) {
                    $seg['AirlineName'] = $matches[1];
                    $seg['FlightNumber'] = $matches[2];
                } elseif (preg_match('/^\s*(\d{1,5})\b/', $flight, $matches)) {
                    $seg['AirlineName'] = AIRLINE_UNKNOWN;
                    $seg['FlightNumber'] = $matches[1];
                }
                $airports = $this->http->FindSingleNode('./td[5]', $row);

                if (preg_match('/([A-Z]{3}) (?:to|Hacia) ([A-Z]{3})/i', $airports, $matches)) {
                    $seg['DepCode'] = $matches[1];
                    $seg['ArrCode'] = $matches[2];
                }
                $seg['Seats'] = $this->http->FindSingleNode('./td[7]', $row);

                $seg['DepartureTerminal'] = $this->http->FindSingleNode('./td[8]', $row, true, '/Terminal\s*([A-Z\d]{1,5})/i');

                $it['TripSegments'][] = $seg;
            }
        }
        $it['Passengers'] = array_unique($it['Passengers']);

        return $it;
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "starts-with(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function t($word)
    {
        if (!isset(self::$dict[$this->lang]) || !isset(self::$dict[$this->lang][$word])) {
            return $word;
        }

        return self::$dict[$this->lang][$word];
    }

    private function assignLang()
    {
        if (isset($this->detects)) {
            $body = $this->http->Response['body'];

            foreach ($this->detects as $lang => $reBody) {
                if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) { return preg_quote($s, '/'); }, $field)) . ')';
    }
}
