<?php

namespace AwardWallet\Engine\british\Email;

class YourDeparture extends \TAccountChecker
{
    public $mailFiles = "british/it-9770589.eml, british/it-9814114.eml, british/it-9856477.eml, british/it-9872472.eml";

    public $reFrom = ".ba.com";

    public $reBody = [
        'en'  => ['We look forward', 'Booking reference'],
        'es'  => ['Esperamos darle la bienvenida', 'Referencia de la reserva'],
        'fr'  => ['Nous espérons avoir le plaisir', 'Référence de réservation'],
        'de'  => ['Wir freuen uns', 'Buchungsreferenz'],
        'de2' => ['Informationen über Leistungen vor dem Abflug', 'Buchungsreferenz'],
    ];

    public $reSubject = [
        '#Your\s+Departure\s+(?:(?-i)[A-Z\d]{5,}[:\s]+([A-Z]{3}[\s\-]+[A-Z]{3})?)\s+\d+\s+\w+\s+\d+\s+\d+:\d+\s*$#i',
        '#Votre\s+départ\s+(?:(?-i)[A-Z\d]{5,}[:\s]+([A-Z]{3}[\s\-]+[A-Z]{3})?)\s+\d+\s+\w+\s+\d+\s+\d+:\d+\s*$#i',
        '#Ihr\s+Abflug\s+(?:(?-i)[A-Z\d]{5,}[:\s]+([A-Z]{3}[\s\-]+[A-Z]{3})?)\s+\d+\s+\w+\s+\d+\s+\d+:\d+\s*$#i',
    ];

    public $lang = '';

    public $subject = '';

    public static $dict = [
        'en' => [
        ],
        'es' => [
            'Dear'             => 'Estimado/a',
            'Your\s+Departure' => 'Su\s+salida',
            'We look forward'  => 'Esperamos darle la bienvenida',
            'flight'           => 'vuelo',
            'from'             => 'con salida de',
            'on'               => 'el',
            'at'               => 'a las',
        ],
        'fr' => [
            'Dear'             => 'Cher(e)',
            'Your\s+Departure' => 'Votre\s+départ',
            'We look forward'  => 'Nous espérons avoir le plaisir',
            'flight'           => 'vol',
            'from'             => 'au départ de',
            'on'               => 'le',
            'at'               => 'à',
        ],
        'de' => [
            'Dear'             => 'Sehr geehrte(r)',
            'Your\s+Departure' => 'Ihr\s+Abflug',
            'We look forward'  => 'Wir freuen uns',
            'flight'           => 'Fluges',
            'from'             => 'von',
            'on'               => 'am',
            'at'               => 'um',
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->subject = $parser->getSubject();

        if (empty($this->subject)) {
            return null;
        }
        $this->AssignLang();

        $its = $this->parseEmail();

        $a = explode('\\', __CLASS__);

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => $a[count($a) - 1] . ucfirst($this->lang),
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//img[starts-with(@alt,'British Airways') or contains(@src,'www.britishairways.com')] | //a[contains(@href,'www.britishairways.com')]")->length > 0) {
            return $this->AssignLang();
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], $this->reFrom) !== false && isset($this->reSubject)) {
            foreach ($this->reSubject as $reSubject) {
                if (preg_match($reSubject, $headers["subject"])) {
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
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    public function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = \AwardWallet\Engine\MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function parseEmail()
    {
        $it = ['Kind' => 'T', 'TripSegments' => []];
        $it['Passengers'][] = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Dear'))}]", null, true, "#{$this->opt($this->t('Dear'))}\s+(.+?)(?:,|:|$)#");

        $seg = [];

        if (preg_match("#{$this->opt($this->t('Your\s+Departure'))}\s+(?<rl>(?-i)[A-Z\d]{5,})[:\s]+((?<dCode>(?-i)[A-Z]{3})[\s\-]+(?<aCode>(?-i)[A-Z]{3})\s+)?(?<date>\d+\s+\w+\s+\d+\s+\d+:\d+(?:\s*[ap]m)?)\s*$#i", $this->subject, $m)) {
            $it['RecordLocator'] = $m['rl'];
            $seg['DepCode'] = (!empty($m['dCode'])) ? $m['dCode'] : TRIP_CODE_UNKNOWN;
            $seg['ArrCode'] = (!empty($m['aCode'])) ? $m['aCode'] : TRIP_CODE_UNKNOWN;
            $seg['DepDate'] = strtotime($this->normalizeDate($m['date']));
            $seg['ArrDate'] = MISSING_DATE;
        }
        $node = $this->http->FindSingleNode("//text()[{$this->starts($this->t('We look forward'))} and {$this->contains($this->t('flight'))}]");

        if (preg_match("#{$this->opt($this->t('flight'))}\s+([A-Z\d]{2})\s*(\d+)\s+{$this->opt($this->t('from'))}\s+(.+?)(?:\s*Terminal\s+(\w+))?\s+{$this->opt($this->t('on'))}\s+(\d+\s+\w+\s+\d+.+\d+:\d+(?:\s*[ap]m)?)#i", $node, $m)) {
            $seg['AirlineName'] = $m[1];
            $seg['FlightNumber'] = $m[2];
            $seg['DepName'] = $m[3];

            if (isset($m[4]) && !empty($m[4])) {
                $seg['DepartureTerminal'] = $m[4];
            }
        }
        $it['TripSegments'][] = $seg;

        return [$it];
    }

    private function normalizeDate($date)
    {
        $in = [
            '#^(\d+)\s+(\w+)\s+(\d+)\s+' . $this->t('at') . '\s+(\d+:\d+)$#', //9 November 2017 at 21:40
        ];
        $out = [
            '$1 $2 $3 $4',
        ];
        $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));

        return $str;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function AssignLang()
    {
        $body = $this->http->Response['body'];

        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                    $this->lang = substr($lang, 0, 2);

                    return true;
                }
            }
        }

        return false;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . str_replace('/', '\/', str_replace('(', '\(', str_replace(')', '\)', implode("|", $field)))) . ')';
    }
}
