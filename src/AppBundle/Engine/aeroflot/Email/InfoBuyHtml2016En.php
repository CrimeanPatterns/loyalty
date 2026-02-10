<?php

namespace AwardWallet\Engine\aeroflot\Email;

class InfoBuyHtml2016En extends \TAccountChecker
{
    public $mailFiles = "aeroflot/it-10078612.eml, aeroflot/it-10141227.eml, aeroflot/it-4689971.eml";

    private $result = [];

    private static $dict = [
        'ru' => [],
        'en' => [
            "Код Вашего бронирования" => "Your reservation number is",
            "Время:"                  => "Time:",
            "Терминал"                => "Terminal",
            "Пассажир(ы):"            => "Passenger(s):",
            "Сумма к оплате:"         => "Total amount:",
        ],
        'fr' => [
            "Код Вашего бронирования" => "Votre numéro de réservation est :",
            "Время:"                  => "Heure :",
            "Терминал"                => "Terminal",
            "Пассажир(ы):"            => "Passager(s) :",
            "Сумма к оплате:"         => "Montant total :",
        ],
    ];

    private $date;
    private $lang;

    private $detectLang = [
        "ru" => ["Код Вашего бронирования", "Отправление"],
        "en" => ["Your reservation number is", "Departure"],
        "fr" => ["Votre numéro de réservation est", "Départ"],
    ];

    public function increaseDate($dateLetter, $dateSegment, $depTime, $arrTime)
    {
        $date = strtotime($dateSegment, $dateLetter);
        $depDate = strtotime($depTime, $date);

        if ($dateLetter > $depDate) {
            $depDate = strtotime('+1 year', $depDate);
        }

        return [
            'DepDate' => $depDate,
            'ArrDate' => strtotime($arrTime, $depDate),
        ];
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getDate());
        $this->AssignLang();
        $this->result['Kind'] = 'T';
        $this->result['RecordLocator'] = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Код Вашего бронирования'))}]/ancestor::*[1]", null, false, '/:\s*([A-Z\d]{5,6})/');
        $this->result['Passengers'] = $this->http->FindNodes("//text()[{$this->contains($this->t('Пассажир(ы):'))}]/following-sibling::ul[1]/li");
        $this->result += total($this->http->FindSingleNode("//text()[{$this->contains($this->t('Сумма к оплате:'))}]/ancestor::*[1]", null, false, '/:\s*(.*)/'));
        $this->parseSegments();

        return [
            'emailType'  => 'InfoBuyHtml2016En',
            'parsedData' => ['Itineraries' => [$this->result]],
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], '@aeroflot.ru') !== false && (
                isset($headers['subject']) && stripos($headers['subject'], 'Information to purchase an e-ticket') !== false
                || isset($headers['subject']) && stripos($headers['subject'], "Informations d'achat d'un billet électronique") !== false
                || isset($headers['subject']) && stripos($headers['subject'], 'Информация для оплаты электронного билета') !== false);
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return (strpos($parser->getHTMLBody(), 'Уважаемый клиент!') !== false || strpos($parser->getHTMLBody(), 'Dear Customer!') !== false
                || strpos($parser->getHTMLBody(), 'Cher client !') !== false) && (
                strpos($parser->getHTMLBody(), 'Щелкните здесь, чтобы перейти на страницу оплаты заказа') !== false
                || strpos($parser->getHTMLBody(), 'Cliquez ici pour vous rendre à la page Paiement') !== false
                || strpos($parser->getHTMLBody(), 'Click here to proceed to Payment page') !== false);
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@aeroflot.ru') !== false;
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

    private function parseSegments()
    {
        foreach ($this->http->XPath->query("//text()[{$this->eq($this->t("Время:"))}]/ancestor::tr[1]") as $current) {
            $this->result['TripSegments'][] = $this->parseSegment($current);
        }
    }

    private function parseSegment(\DOMElement $element)
    {
        $segment = [];
        $dateSegment = $this->dateStringToEnglish($this->http->FindSingleNode('td[1]', $element));

        if (preg_match('/([A-Z\d]{2})?\s*(\d+)/', $this->http->FindSingleNode('td[2]', $element), $matches)) {
            $segment['AirlineName'] = $matches[1];
            $segment['FlightNumber'] = $matches[2];
        }

        if (preg_match("/(.*?){$this->opt($this->t('Время:'))}\s*(\d+:\d+)/", $this->http->FindSingleNode('td[3]', $element), $matches)) {
            $segment['DepName'] = trim($matches[1]);
            $depTime = $matches[2];
        }

        if (isset($depTime) && preg_match("/(.*?){$this->opt($this->t('Время:'))}\s*(\d+:\d+)/", $this->http->FindSingleNode('td[4]', $element), $matches)) {
            $segment['ArrName'] = trim($matches[1]);
            $segment += $this->increaseDate($this->date, $dateSegment, $depTime, $matches[2]);
        }

        if (preg_match("/{$this->opt($this->t('Терминал'))}[:\s]+(.*)/", $this->http->FindSingleNode('td[3]', $element), $matches)) {
            $segment['DepartureTerminal'] = $matches[1];
        }

        if (preg_match("/{$this->opt($this->t('Терминал'))}[:\s]+(.*)/", $this->http->FindSingleNode('td[4]', $element), $matches)) {
            $segment['ArrivalTerminal'] = $matches[1];
        }

        $segment['ArrCode'] = $segment['DepCode'] = TRIP_CODE_UNKNOWN;

        return $segment;
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

        return '(?:' . implode("|", array_map(function ($s) { return "(?:" . preg_quote($s) . ")"; }, $field)) . ')';
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
        foreach ($this->detectLang as $lang => $reBody) {
            if ($this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody[0]}')]")->length > 0
                && $this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody[1]}')]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }
}
