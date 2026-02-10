<?php

namespace AwardWallet\Engine\aeroflot\Email;

class RegistrationPDF extends \TAccountChecker
{
    public $mailFiles = "aeroflot/it-4892011.eml";

    public $monthNames = [
        'en'  => ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'],
        'ru'  => ['январь', 'февраль', 'март', 'апрель', 'май', 'июнь', 'июль', 'август', 'сентябрь', 'октябрь', 'ноябрь', 'декабрь'],
        'ru2' => ['января', 'февраля', 'марта', 'апреля', 'мая', 'июня', 'июля', 'августа', 'сентября', 'октября', 'ноября', 'декабря'],
    ];

    public $reBody = [
        ['©\s+Аэрофлот\s+\d+', 'Информация о вашем бронировании'],
    ];
    public $reLang = [
        'ru' => ['Уважаемый пассажир'],
    ];
    public $reSubject = [
        'Открыта регистрация на рейс для бронирования',
    ];
    public $lang = 'ru';
    public $pdf;
    public static $dict = [
        'ru' => [
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName(".*pdf");

        if (isset($pdfs) && count($pdfs) > 0) {
            $this->pdf = clone $this->http;
            $html = '';

            foreach ($pdfs as $pdf) {
                if (($html .= \PDF::convertToHTML($parser->getAttachmentBody($pdf), \PDF::MODE_COMPLEX)) !== null) {
                } else {
                    return null;
                }
            }
            $NBSP = chr(194) . chr(160);
            $html = str_replace($NBSP, ' ', html_entity_decode($html));
            $this->pdf->SetEmailBody($html);
        } else {
            return null;
        }

        $body = $parser->getPlainBody();

        if (!$body) {
            $body = text($parser->getHTMLBody());
        }
        $this->AssignLang($body);

        $its = $this->parseEmail();

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => "RegistrationPDF",
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
        $it = ['Kind' => 'T', 'TripSegments' => []];
        $recLoc = array_unique($this->pdf->FindNodes("//p[contains(.,'Код бронирования') and contains(.,'перевозчика:')]/following::p[1]"));

        if (isset($recLoc[0])) {
            $it['RecordLocator'] = $recLoc[0];
        }

        $it['TicketNumbers'] = array_unique($this->pdf->FindNodes("//p[contains(.,'Номер(а) билета(ов)')]/following::p[1]"));
        $it['Passengers'] = array_unique($this->pdf->FindNodes("//p[contains(.,'Подготовлено для')]/following::p[1]"));
        $nodes = array_unique($this->pdf->FindNodes("//p[contains(.,'Дата выдачи билета')]/following::p[1]"));

        if (isset($nodes[0])) {
            $it['ReservationDate'] = strtotime(str_replace('/', '.', $nodes[0]));
        }
        $it['Currency'] = $this->pdf->FindSingleNode("(//p[contains(.,'Общая стоимость')]/following::p[1])[1]", null, true, "#[A-Z]{3}#");
        $it['TotalCharge'] = $this->pdf->FindSingleNode("(//p[contains(.,'Общая стоимость')]/following::p[1])[1]", null, true, "#[\d\,\.\s]+#");
        $it['Tax'] = $this->pdf->FindSingleNode("(//p[contains(.,'Включая НДС')]/following::p[1])[1]", null, true, "#[\d\,\.\s]+#");
        $flights = array_unique($this->pdf->FindNodes("//p[contains(translate(.,' ',' '),'Примечания')]/following::p[2]"));
        $addflights = array_unique($this->pdf->FindNodes("//p[contains(.,'Статус билета:')]/following::p[position()=2 and not(contains(.,'Код'))]"));

        foreach ($addflights as $add) {
            $flights[] = $add;
        }

        foreach ($flights as $flight) {
            $seg = [];

            if (preg_match("#([A-Z\d]{2})\s*(\d+)#", $flight, $m)) {
                $seg['AirlineName'] = $m[1];
                $seg['FlightNumber'] = $m[2];
            }
            $addI = 0;
            $node = $this->pdf->FindSingleNode("(//p[contains(.,'{$flight}')]/following::p[1])[1]", null, true, "#\d+:\d+#");

            if (empty($node)) {
                $addI = 1;
                $num = 1 + $addI;
                $node = $this->pdf->FindSingleNode("(//p[contains(.,'{$flight}')]/following::p[{$num}])[1]", null, true, "#\d+:\d+#");
            }

            if (preg_match("#(\d+:\d+)#", $node, $m)) {
                $num = 2 + $addI;
                $date = $this->pdf->FindSingleNode("(//p[contains(.,'{$flight}')]/following::p[{$num}])[1]");
                $seg['DepDate'] = strtotime($this->getDate($date . ' ' . $m[1]));
            }

            $num = 3 + $addI;
            $seg['DepName'] = $this->pdf->FindSingleNode("(//p[contains(.,'{$flight}')]/following::p[{$num}])[1]");
            $num = 4 + $addI;
            $seg['DepCode'] = $this->pdf->FindSingleNode("(//p[contains(.,'{$flight}')]/following::p[{$num}])[1]", null, true, "#[A-Z]{3}#");
            $num = 5 + $addI;

            if ($this->pdf->FindSingleNode("(//p[contains(.,'{$flight}')]/following::p[{$num}])[1]") === "Терминал:") {
                $num = 6 + $addI;
                $seg['DepartureTerminal'] = $this->pdf->FindSingleNode("(//p[contains(.,'{$flight}')]/following::p[{$num}])[1]");
                $addI += 2;
            }
            $num = 5 + $addI;
            $node = $this->pdf->FindSingleNode("(//p[contains(.,'{$flight}')]/following::p[{$num}])[1]");

            if (preg_match("#(\d+:\d+)#", $node, $m)) {
                $num = 6 + $addI;
                $date = $this->pdf->FindSingleNode("(//p[contains(.,'{$flight}')]/following::p[{$num}])[1]");
                $seg['ArrDate'] = strtotime($this->getDate($date . ' ' . $m[1]));
            }
            $num = 7 + $addI;
            $seg['ArrName'] = $this->pdf->FindSingleNode("(//p[contains(.,'{$flight}')]/following::p[{$num}])[1]");
            $num = 8 + $addI;
            $seg['ArrCode'] = $this->pdf->FindSingleNode("(//p[contains(.,'{$flight}')]/following::p[{$num}])[1]", null, true, "#[A-Z]{3}#");
            $num = 9 + $addI;

            if ($this->pdf->FindSingleNode("(//p[contains(.,'{$flight}')]/following::p[{$num}])[1]") === "Терминал:") {
                $num = 10 + $addI;
                $seg['ArrivalTerminal'] = $this->pdf->FindSingleNode("(//p[contains(.,'{$flight}')]/following::p[{$num}])[1]");
                $addI += 2;
            }
            $num = 12 + $addI;

            if (trim($this->pdf->FindSingleNode("(//p[contains(.,'{$flight}')]/following::p[{$num}])[1]")) === "Класс:") {
                $num = 13 + $addI;
                $node = $this->pdf->FindSingleNode("(//p[contains(.,'{$flight}')]/following::p[{$num}])[1]");

                if (preg_match("#(.+?)\s*\/\s*([A-Z]{1,2})#", $node, $m)) {
                    $seg['Cabin'] = $m[1];
                    $seg['BookingClass'] = $m[2];
                }
            }
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
