<?php

namespace AwardWallet\Engine\aeromexico\Email;

class It3344043 extends \TAccountCheckerExtended
{
    public $mailFiles = 'aeromexico/it-3344043.eml, aeromexico/it-5673755.eml';

    protected $lang = null;

    protected $langDetectors = [
        'pt' => [
            'Informação de voo',
        ],
        'es' => [
            'Información sobre el vuelo',
        ],
    ];

    protected $dict = [
        'O seu número de pedido de compra' => [
            'es' => 'Su número de solicitud de compra',
        ],
        'Passageiros' => [
            'es' => 'Pasajeros',
        ],
        'Vôo' => [
            'es' => 'Vuelo',
        ],
    ];

    protected $regexps = [
        'passenger' => [
            'pt' => '/Nome\s*:\s*(.*?)\s*Sobrenome\s*:\s*(.+)/u',
            'es' => '/Nombre\s*:\s*(.*?)\s*Apellido\s*:\s*(.+)/u',
        ],
    ];

    // Standard Methods

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['from'], 'no-reply@aeromexico.com') !== false
            || stripos($headers['from'], 'reservations@aeromexico.com') !== false
            || stripos($headers['from'], 'confirmaciones@aeromexico.com') !== false
            || stripos($headers['subject'], 'Aeromexico.com - Pedido de compra de vôo') !== false
            || stripos($headers['subject'], 'Aeromexico.com - Solicitud de compra de vuelo') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        foreach ($this->langDetectors as $lang => $lines) {
            foreach ($lines as $line) {
                if ($this->http->XPath->query('//node()[contains(.,"' . $line . '")]')->length > 0 && $this->http->XPath->query('//node()[contains(.,"AeroMexico equipe") or contains(.,"Equipo de AeroMexico")]')->length > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@aeromexico.com') !== false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        foreach ($this->langDetectors as $lang => $lines) {
            foreach ($lines as $line) {
                if ($this->http->XPath->query('//node()[contains(.,"' . $line . '")]')->length > 0) {
                    $this->lang = $lang;
                }
            }
        }
        $it = $this->ParseEmail();

        return [
            'emailType'  => 'Flight',
            'parsedData' => [
                'Itineraries' => [$it],
            ],
        ];
    }

    public static function getEmailLanguages()
    {
        return ['pt', 'es'];
    }

    public static function getEmailTypesCount()
    {
        return 2;
    }

    protected function translate($s)
    {
        if (isset($this->lang) && isset($this->dict[$s][$this->lang])) {
            return $this->dict[$s][$this->lang];
        } else {
            return $s;
        }
    }

    protected function ParseEmail()
    {
        $text = text($this->http->Response['body']);

        $it = [];
        $it['Kind'] = 'T';
        $it['RecordLocator'] = $this->http->FindSingleNode('//text()[starts-with(normalize-space(.),"' . $this->translate('O seu número de pedido de compra') . '")]/following::text()[normalize-space(.)!=""][1]', null, true, '/([A-Z\d]+)/');
        $it['Passengers'] = array_map(function ($s) { return preg_replace($this->regexps['passenger'][$this->lang], "$1 $2", $s); }, $this->http->FindNodes('//*[normalize-space(text())="' . $this->translate('Passageiros') . '"]/following-sibling::table[1]//tr/td[2]'));
        $it['TotalCharge'] = cost(preg_replace('/[.,](\d{3})/', "$1", $this->http->FindSingleNode('//*[normalize-space(text())="Total"]/ancestor-or-self::td[1]/following-sibling::td[1]')));
        $it['Currency'] = currency($this->http->FindSingleNode('//*[normalize-space(text())="Total"]/ancestor-or-self::td[1]/following-sibling::td[1]'));
        $xpath = '//img[contains(@src,"/common/airlines/25x25")]/ancestor::tr[1]/..';
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length === 0) {
            $this->http->Log("segments root not found: $xpath", LOG_LEVEL_NORMAL);
        }

        //		$year = $this->http->FindSingleNode("//*[contains(text(),'BOOKING') and contains(text(),'DETAILS')]/ancestor::table[1]/following-sibling::table[3]//td[2]", null, true, "#(\d+)$#");

        foreach ($nodes as $root) {
            $seg = [];
            $date = en($this->http->FindSingleNode('./tr[2]', $root, true, '/\d+\s+\w+\s+\d{4}/'));
            $seg['AirlineName'] = $this->http->FindSingleNode('./tr[3]', $root);
            $seg['FlightNumber'] = $this->http->FindSingleNode('./tr[4]', $root, true, '/' . $this->translate('Vôo') . '\s*:\s*(\d+)/u');
            $seg['Cabin'] = $this->http->FindSingleNode('./tr[4]', $root, true, '/' . $this->translate('Vôo') . '\s*:\s*\d+\s*-\s*(.+)/u');
            $seg['Duration'] = $this->http->FindSingleNode('./tr[5]', $root, true, '/\d+h\s+\d+m/');

            if ($this->lang === 'pt') {
                $seg['DepName'] = $this->http->FindSingleNode('./tr[contains(.,":") and contains(.,"Aeroporto") and not(.//tr)][1]', $root, true, '/\s*:\s*Aeroporto\s+(.+)/u');
                $timeDep = $this->http->FindSingleNode('./tr[starts-with(normalize-space(.),"Sai de às") and not(.//tr)]', $root, true, '/(\d{2}:\d{2})/');
                $seg['ArrName'] = $this->http->FindSingleNode('./tr[contains(.,":") and contains(.,"Aeroporto") and not(.//tr)][last()]', $root, true, '/\s*:\s*Aeroporto\s+(.+)/u');
                $timeArr = $this->http->FindSingleNode('./tr[starts-with(normalize-space(.),"Chega em às") and not(.//tr)]', $root, true, '/(\d{2}:\d{2})/');
            }

            if ($this->lang === 'es') {
                $departure = $this->http->FindSingleNode('./tr[starts-with(normalize-space(.),"Sale de") and contains(.,":") and not(.//tr)]', $root);

                if (preg_match('/Sale\s+de\s+(.+?)\s+a\s+las\s+(\d{2}:\d{2})hs/iu', $departure, $matches)) {
                    $seg['DepName'] = $matches[1];
                    $timeDep = $matches[2];
                }
                $arrival = $this->http->FindSingleNode('./tr[starts-with(normalize-space(.),"Llega a") and contains(.,":") and not(.//tr)]', $root);

                if (preg_match('/Llega\s+a\s+(.+?)\s+a\s+las\s+(\d{2}:\d{2})hs/iu', $arrival, $matches)) {
                    $seg['ArrName'] = $matches[1];
                    $timeArr = $matches[2];
                }
            }

            if ($date && $timeDep && $timeArr) {
                $seg['DepDate'] = strtotime($date . ', ' . $timeDep);
                $seg['ArrDate'] = strtotime($date . ', ' . $timeArr);
            }
            $seg['DepCode'] = TRIP_CODE_UNKNOWN;
            $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
            $it['TripSegments'][] = $seg;
        }

        return $it;
    }
}
