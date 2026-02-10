<?php

namespace AwardWallet\Engine\ctrip\Email;

use AwardWallet\Schema\Parser\Email\Email;

class TripTicketConfirmation2018 extends \TAccountChecker
{
    public $mailFiles = "ctrip/it-13028968.eml, ctrip/it-13412515.eml, ctrip/it-57039102.eml";

    private $langDetectors = [
        'zh' => ['出发/到达'],
    ];
    private $lang = '';
    private static $dict = [
        'zh' => [],
    ];

    // Standard Methods

    public function detectEmailFromProvider($from)
    {
        return strpos($from, '携程旅行网国际机票预订部') !== false
            || stripos($from, '@ctrip.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        return strpos($headers['subject'], '机票行程确认单') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $condition1 = $this->http->XPath->query('//node()[contains(normalize-space(.),"欢迎使用携程旅行网网上预订系统") or contains(normalize-space(.),"携程旅行网国际机票预订部")]')->length === 0;
        $condition2 = $this->http->XPath->query('//a[contains(@href,"//t.ctrip.cn") or contains(@href,"//www.ctrip.com") or contains(@href,"//app.ctrip.com")]')->length === 0;

        if ($condition1 && $condition2) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if ($this->assignLang() === false) {
            return false;
        }

        $this->parseEmail($email);
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseEmail(Email $email)
    {
        $patterns = [
            'terminal'        => '/^([A-Z\d]+)$/',
            'terminalReplace' => '/^T([A-Z\d]+)$/', // T2D
        ];

        $flight = $email->add()->flight();

        // TripNumber

        $confNumber = $this->http->FindSingleNode('//text()[starts-with(normalize-space(.),"订单号")]/ancestor::div[1]', null, true, '/^[^:：]+[:：]\s*(\d{5,})$/u');

        if (!empty($confNumber)) {
            $flight->general()
                ->confirmation($confNumber);
        } else {
            $flight->general()
                ->noConfirmation();
        }

        // Passengers
        // TicketNumbers
        $passengers = [];
        $ticketNumbers = [];
        $passengerRows = $this->http->XPath->query('/descendant::tr[not(.//tr) and starts-with(normalize-space(.),"乘客姓名")][1]/ancestor::table[1]/descendant::tr[not(.//tr) and normalize-space(.)][position()>1][ ./td[3] ]');

        foreach ($passengerRows as $passengerRow) {
            $passengerText = $this->http->FindSingleNode('./td[1]', $passengerRow);

            if ($passengerText) {
                $passengers[] = $passengerText;
            }
            $ticketNumberTexts = $this->http->FindNodes('./td[2]/descendant::text()[normalize-space(.)]', $passengerRow, '/^(\d[-\d\s]{5,}\d)$/');
            $ticketNumberValues = array_values(array_filter($ticketNumberTexts));

            if (!empty($ticketNumberValues[0])) {
                $ticketNumbers += $ticketNumberValues;
            }
        }

        if (count($passengers)) {
            $flight->general()
                ->travellers(array_unique($passengers));
        }

        if (count($ticketNumbers)) {
            $flight->setTicketNumbers(array_unique($ticketNumbers), false);
        }

        // Currency
        // TotalCharge
        $payment = $this->http->FindSingleNode('//text()[contains(normalize-space(.),"金额明细")]/following::text()[starts-with(normalize-space(.),"总计")]');

        if (preg_match('/\b([A-Z]{3})\s*(\d[,.\d]*)$/', $payment, $matches)) { // RMB 1656.00
            $flight->price()
                ->currency($matches[1])
                ->total($this->normalizePrice($matches[2]));
        } else {
            $payment = $this->http->FindSingleNode('//text()[starts-with(normalize-space(.),"总计")]/ancestor::td[1]');
            // 总计 RMB 7887.00
            if (preg_match('/总计\s+([A-Z]{3})\s*(\d[,.\d]+)$/u', $payment, $matches)) {
                $flight->price()
                    ->currency($matches[1])
                    ->total($this->normalizePrice($matches[2]));
            }
        }

        $tax = $this->http->FindSingleNode('//text()[contains(normalize-space(.),"金额明细")]/following::tr[contains(normalize-space(.), "机票税") and not(.//tr)][1]/following::tr[string-length(normalize-space(.)) > 2][1]/td[normalize-space(.)][last()]', null, true, '/^[ ]*[A-Z]{3} ([\d\.]+)[ ]*$/');

        if (!empty($tax)) {
            $flight->price()
                ->tax($tax);
        }

        $segments = $this->http->XPath->query('/descendant::tr[not(.//tr) and starts-with(normalize-space(.),"出发/到达")][1]/ancestor::table[1]/descendant::tr[not(.//tr) and normalize-space(.)][position()>1][ ./td[5] ]');

        if ($segments->count() === 0) {
            $segments = $this->http->XPath->query("//text()[starts-with(normalize-space(.),'出发/到达')][1]/ancestor::th[1]/ancestor::table[1]/descendant::tr[not(contains(normalize-space(), 'Takeoff') or contains(normalize-space(), 'Departure') or contains(normalize-space(), 'Takeoff'))]");
        }

        foreach ($segments as $segment) {
            if (empty($dateDep = $this->http->FindSingleNode('./td[4]', $segment))) {
                continue;
            }

            $s = $flight->addSegment();

            // DepCode
            // ArrCode
            $route = $this->http->FindSingleNode('./td[1]', $segment);

            if (preg_match('/^.*?(?:\(|\?)\s*([A-Z]{3})\s*\).*?-.*?(?:\(|\?)\s*([A-Z]{3})\s*\)/', $route, $matches)) {
                $s->departure()
                    ->code($matches[1]);
                $s->arrival()
                    ->code($matches[2]);
            }

            // AirlineName
            // FlightNumber
            $flights = $this->http->FindSingleNode('./td[2]', $segment);

            if (preg_match('/^(?<airline>[A-Z][A-Z\d]|[A-Z\d][A-Z])?\s*(?<flightNumber>\d+)(?:$|[ ]+实际承运\:[ ]*(?<operator>[A-Z\d]{2}))?/', $flights, $matches)) {
                if (!empty($matches['airline'])) {
                    $s->airline()
                        ->name($matches['airline']);
                }
                $s->airline()
                    ->number($matches['flightNumber']);

                if (!empty($matches['operator'])) {
                    $s->airline()
                        ->operator($matches['operator']);
                }
            }

            // Cabin
            $class = preg_replace('/.+?\?\?br\>/', '', $this->http->FindSingleNode('./td[3]/descendant::text()[normalize-space(.)][1]', $segment));

            if ($class) {
                $s->extra()
                    ->cabin($this->re('/^([^\d\W]{3,})$/u', $class));
            }

            // DepDate
            $dateDep = $this->http->FindSingleNode('./td[4]', $segment);

            if ($dateDep) {
                $s->departure()
                    ->date(strtotime($dateDep));
            }

            // ArrDate
            $dateArr = $this->http->FindSingleNode('./td[5]', $segment);

            if ($dateArr) {
                $s->arrival()
                    ->date(strtotime($dateArr));
            }

            // DepartureTerminal
            $terminalDep = $this->http->FindSingleNode('./td[6]', $segment);

            if (preg_match($patterns['terminal'], $terminalDep, $matches)) {
                $s->departure()
                    ->terminal(preg_replace($patterns['terminalReplace'], '$1', $matches[1]));
            }

            // ArrivalTerminal
            $terminalArr = $this->http->FindSingleNode('./td[7]', $segment);

            if (preg_match($patterns['terminal'], $terminalArr, $matches)) {
                $s->arrival()->terminal(preg_replace($patterns['terminalReplace'], '$1', $matches[1]));
            }
        }

        return true;
    }

    private function normalizePrice($string = '')
    {
        if (empty($string)) {
            return $string;
        }
        $string = preg_replace('/\s+/', '', $string);           // 11 507.00    ->    11507.00
        $string = preg_replace('/[,.](\d{3})/', '$1', $string); // 2,790        ->    2790    |    4.100,00    ->    4100,00
        $string = preg_replace('/,(\d{2})$/', '.$1', $string);  // 18800,00     ->    18800.00

        return $string;
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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }
}
