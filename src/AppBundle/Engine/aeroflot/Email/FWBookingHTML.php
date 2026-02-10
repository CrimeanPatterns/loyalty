<?php

namespace AwardWallet\Engine\aeroflot\Email;

class FWBookingHTML extends \TAccountChecker
{
    public $mailFiles = "aeroflot/it-5022274.eml, aeroflot/it-5660558.eml";

    public $monthNames = [
        'en' => ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'],
        //		'ru' => ['январь','февраль','март','апрель','май','июнь','июль','август','сентябрь','октябрь','ноябрь','декабрь'],
        //		'ru2' => ['января','февраля','марта','апреля','мая','июня','июля','августа','сентября','октября','ноября','декабря'],
    ];

    public $reBody = [
        ['©\s+Aeroflot\s+\d+', 'Subject: ✈ Payment'],
    ];
    public $reLang = [
        'en' => ['Departure'],
    ];
    public $reSubject = [
        'booking on Aeroflot airlines website ✈',
    ];
    public $lang = 'en';
    public static $dict = [
        'en' => [
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $body = $parser->getPlainBody();

        if (!$body) {
            $body = text($parser->getHTMLBody());
        }
        $this->AssignLang($body);

        $its = $this->parseEmail();

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => "FWBookingHTML",
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,"//m.aeroflot.ru/b/info/booking")]')->length > 0) {
            $body = html_entity_decode($parser->getHTMLBody());
            $text = substr($body, stripos($body, "©"));

            foreach ($this->reBody as $value) {
                if (preg_match('/' . $value[0] . '/ui', $text)) {
                    return true;
                }
            }
        } else {
            return false;
        }
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($this->reSubject)) {
            foreach ($this->reSubject as $reSubject) {
                if (stripos($headers["subject"], $reSubject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, "aeroflot.ru") !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    protected function getDate($nodeForDate)
    {
        $month = $this->monthNames['en'];
        $monthLang = $this->monthNames[$this->lang];
        $done = false;
        preg_match("#(?<day>[\d]+)\s+(?<month>.+)\s+(?<year>\d{4})\s+(?<time>.+)#", $nodeForDate, $chek);
        $res = $nodeForDate;

        for ($i = 0; $i < 12; $i++) {
            if (mb_strtolower($monthLang[$i]) == mb_strtolower(trim($chek['month']))) {
                $res = $chek['day'] . ' ' . $month[$i] . ' ' . $chek['year'] . ' ' . $chek['time'];
                $done = true;

                break;
            }
        }

        if (!$done && isset($this->monthNames[$this->lang . '2'])) {
            $monthLang = $this->monthNames[$this->lang . '2'];

            for ($i = 0; $i < 12; $i++) {
                if (mb_strtolower($monthLang[$i]) == mb_strtolower(trim($chek['month']))) {
                    $res = $chek['day'] . ' ' . $month[$i] . ' ' . $chek['year'] . ' ' . $chek['time'];

                    break;
                }
            }
        }

        return $res;
    }

    private function parseEmail()
    {
        // echo $this->lang;
        $it = ['Kind' => 'T', 'TripSegments' => []];

        $it['RecordLocator'] = $this->http->FindSingleNode("//text()[contains(.,'Booking code')]", null, true, "#\s+[A-Z\d]{5,6}#");

        $it['TicketNumbers'] = array_unique($this->http->FindNodes("//text()[contains(.,'Passengers')]/ancestor::tr[1]/following-sibling::tr/td[2]//text()[normalize-space(.)]"));
        $it['Passengers'] = array_unique($this->http->FindNodes("//text()[contains(.,'Passengers')]/ancestor::tr[1]/following-sibling::tr/td[1]/descendant::text()[normalize-space(.)][1]"));

        $xpath = "//text()[contains(.,'Flight')]/ancestor::tr[1]";
        $roots = $this->http->XPath->query($xpath);

        foreach ($roots as $root) {
            $seg = [];
            $node = $this->http->FindSingleNode("./td[1]", $root);

            if (preg_match("#([A-Z\d]{2})\s*(\d+)#", $node, $m)) {
                $seg['AirlineName'] = $m[1];
                $seg['FlightNumber'] = $m[2];
            }
            $node = $this->http->FindSingleNode("./td[2]", $root);

            if (preg_match("#(\d+:\d{2})\s*(\d+\s+\S+\s+\d+)\s*(.+?)\s*([A-Z]{3})\s*(?:Terminal\:?\s*(.+))?#", $node, $m)) {
                $seg['DepName'] = $m[3];
                $seg['DepCode'] = $m[4];
                $seg['DepDate'] = strtotime($this->getDate($m[2] . ' ' . $m[1]));

                if (isset($m[5]) && !empty($m[5])) {
                    $seg['DepartureTerminal'] = $m[5];
                }
            }

            $node = $this->http->FindSingleNode("./td[3]", $root);

            if (preg_match("#(\d+:\d{2})\s*(\d+\s+\S+\s+\d+)\s*(.+?)\s*([A-Z]{3})\s*(?:Terminal\:?\s*(.+))?#", $node, $m)) {
                $seg['ArrName'] = $m[3];
                $seg['ArrCode'] = $m[4];
                $seg['ArrDate'] = strtotime($this->getDate($m[2] . ' ' . $m[1]));

                if (isset($m[5]) && !empty($m[5])) {
                    $seg['ArrivalTerminal'] = $m[5];
                }
            }
            $node = $this->http->FindSingleNode("./td[4]//text()[contains(.,'Booking class')]/following::text()[1]", $root);

            if (preg_match("#(.+?)\s*\/\s*([A-Z]{1,2})#", $node, $m)) {
                $seg['Cabin'] = $m[1];
                $seg['BookingClass'] = $m[2];
            }
            $it['Status'] = $this->http->FindSingleNode("./td[4]//text()[contains(.,'Status')]/following::text()[1]", $root);
            $seg['Aircraft'] = $this->http->FindSingleNode("./td[4]//text()[contains(.,'Airplane')]/following::text()[1]", $root);
            $seg['Meal'] = $this->http->FindSingleNode("./td[4]//text()[contains(.,'Meal')]/following::text()[1]", $root);
            $seg['Duration'] = $this->http->FindSingleNode("./td[4]//text()[contains(.,'Flight')]/following::text()[1]", $root);

            $seg = array_filter($seg);
            $it['TripSegments'][] = $seg;
        }

        return [$it];
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function AssignLang($body)
    {
        if (isset($this->reLang)) {
            foreach ($this->reLang as $lang => $reBody) {
                if (stripos($body, $reBody[0]) !== false) {
                    $this->lang = substr($lang, 0, 2);

                    break;
                }
            }
        }

        return true;
    }
}
