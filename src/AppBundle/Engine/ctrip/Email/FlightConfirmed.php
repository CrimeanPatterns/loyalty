<?php

namespace AwardWallet\Engine\ctrip\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class FlightConfirmed extends \TAccountChecker
{
    public $mailFiles = "ctrip/it-56815935.eml, ctrip/it-60029230.eml";
    public $reFrom = '@ctrip.com';
    public $reSubject = [
        'zh' => '机票行程确认单',
    ];
    public $lang = 'zh';
    public $reBody = 'ctrip';
    public $reBody2 = [
        'zh' => [
            '携程旅游网',
        ],
    ];

    public static $dictionary = [
        'zh' => [
            "Order No."          => "订单编号：",
            "Flight information" => "航班信息",
            "Terminal"           => "航站",
            "Airport"            => "机场",
            "Passenger Name"     => "乘客姓名",
            "Booking date"       => "预订日期",
            "Amount breakdown"   => "金额明细",
            "Cost"               => "机票",
            "Tax"                => ["税", "民航发展基金"],
            "Total"              => "总计",
            //            "operated by" => "",
            "Change" => ["发生行程变动的新航班", "已改签的新航班"],
        ],
    ];

    public function parseHtml(Email $email)
    {
        $flight = $email->add()->flight();

        $bookingDate = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Booking date'))}]", null, true, '/([\d\-]+)/');
        $travellers = $this->http->FindNodes("//text()[{$this->starts($this->t('Passenger Name'))}]/ancestor::table[1]/descendant::tr[not(" . $this->contains($this->t('Passenger Name')) . ")]/td[1]");
        $ticketNumbers = $this->http->FindNodes("//text()[{$this->starts($this->t('Passenger Name'))}]/ancestor::table[1]/descendant::tr[not(" . $this->contains($this->t('Passenger Name')) . ")]/td[4]", null, '/([\d\-]+)\s+\//');
        $airlineConfirmation = array_values(array_filter($this->http->FindNodes("//text()[{$this->starts($this->t('Passenger Name'))}]/ancestor::table[1]/descendant::tr[not(" . $this->contains($this->t('Passenger Name')) . ")][1]/td[4]/descendant::text()[normalize-space()]", null, '/\/\s+([A-Z\d?]+)/')));
        $confNumber = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Order No.'))}]/ancestor::tr[1]/descendant::text()[normalize-space()][2]", null, true, '/(\d{9,})/');
        $descrConfNumber = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Order No.'))}]/ancestor::tr[1]/descendant::text()[normalize-space()][1]");

        $flight->general()
            ->date(strtotime($this->normalizeDate($bookingDate)));

        if (count($travellers) > 0) {
            $flight->general()
                ->travellers($travellers, true);
        }

        if (count($travellers) === 0) {
            $traveller = $this->http->FindSingleNode("//text()[contains(normalize-space(), '当前为最新航班信息')]", null, true, "/^(\D+)\,当前为最新航班信息$/");
            $flight->general()
                ->traveller($traveller);
        }

        if (isset($ticketNumbers[0])) {
            if (!empty(trim($ticketNumbers[0], '-'))) {
                $flight->setTicketNumbers($ticketNumbers, false);
            }
        }

        if (!empty($confNumber)) {
            $flight->general()
                ->confirmation($confNumber, $descrConfNumber);
        }

        $cost = $this->http->FindSingleNode("//td[{$this->starts($this->t('Amount breakdown'))}]/ancestor::table[1]/descendant::text()[{$this->eq($this->t('Cost'))}]/ancestor::td[1]/following-sibling::td[1]", null, true, '/([\d\.]+)/');
        $tax = $this->http->FindSingleNode("//td[{$this->starts($this->t('Amount breakdown'))}]/ancestor::table[1]/descendant::text()[{$this->eq($this->t('Tax'))}]/ancestor::td[1]/following-sibling::td[1]", null, true, '/([\d\.]+)/');
        $total = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Amount breakdown'))}]/following::text()[{$this->starts($this->t('Total'))}]/ancestor::tr[1]/descendant::td[1]/descendant::text()[normalize-space()][3]");

        if (empty($total)) {
            $total = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Amount breakdown'))}]/following::text()[{$this->starts($this->t('Total'))}]/following::text()[normalize-space()][2]");
        }
        $currency = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Amount breakdown'))}]/following::text()[{$this->starts($this->t('Total'))}]/ancestor::tr[1]/descendant::td[1]/descendant::text()[normalize-space()][2]");

        if (empty($currency)) {
            $currency = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Amount breakdown'))}]/following::text()[{$this->starts($this->t('Total'))}]/following::text()[normalize-space()][1]");
        }

        $flight->price()
            ->currency($this->normalizeCurrency($currency))
            ->total($total);

        if (!empty($cost)) {
            $flight->price()
                ->cost($cost);
        }

        if (!empty($tax)) {
            $flight->price()
                ->tax($tax);
        }

        $date = $this->normalizeDate($this->http->FindSingleNode("//td[{$this->starts($this->t('Flight information'))}]/ancestor::table[1]/descendant::text()[{$this->starts($this->t('Flight information'))}]/following::text()[normalize-space()][1]"));

        $xpath = "//text()[{$this->starts($this->t('Change'))}]/following::tr[starts-with(normalize-space(), 'Time') and contains(normalize-space(), 'Flight')]/ancestor::table[1]/following::table[1]/descendant::table";

        if (count($this->http->XPath->query($xpath)) === 0) {
            $xpath = "//text()[{$this->starts($this->t('Change'))}]/following::text()[starts-with(normalize-space(), '航班起降时间均为当地时间')]/ancestor::*[1]/following::table[1]/descendant::table";
        }

        if (count($this->http->XPath->query($xpath)) === 0) {
            $xpath = "//tr[starts-with(normalize-space(), 'Time') and contains(normalize-space(), 'Flight')]/ancestor::table[1]/following::table[1]/descendant::table";
        }

        $segments = $this->http->XPath->query($xpath);
        $segmentOrderNumber = 0;

        foreach ($segments as $root) {
            $segment = $flight->addSegment();

            $depTime = $this->http->FindSingleNode("./descendant::tr[normalize-space()][1]/descendant::tr[normalize-space()][1]/descendant::td[1]", $root, true, '/(\d{1,2}\:\d{1,2})/');

            if (empty($depTime)) {
                $depTime = $this->http->FindSingleNode("./descendant::tr[normalize-space()][1]/descendant::td[1]", $root, true, '/(\d{1,2}\:\d{1,2})/');
            }

            if (empty($depTime)) {
                $depTime = $this->http->FindSingleNode("./descendant::td[normalize-space()][1]", $root, true, '/(\d{1,2}\:\d{1,2})/');
            }

            $depDate = $this->normalizeDate($this->http->FindSingleNode("./descendant::tr[normalize-space()][1]/descendant::td[1]", $root, true, '/(\d{1,2}\-\d{1,2})/'));

            if (empty($depDate)) {
                $depDate = $this->normalizeDate($this->http->FindSingleNode("./descendant::tr[normalize-space()][1]/descendant::td[1]/preceding::text()[starts-with(normalize-space(), '航班起降时间均为当地时间')][1]/preceding::text()[normalize-space()][3]", $root, true, "/^(\d+月\d+日)/"));
            }

            if (empty($depDate)) {
                $depDate = $this->normalizeDate($this->http->FindSingleNode("./descendant::tr[normalize-space()][1]/descendant::td[1]/preceding::text()[starts-with(normalize-space(), '航班起降时间均为当地时间')][1]/preceding::text()[normalize-space()][4]", $root, true, "/^(\d+\-\d+)/"));
            }

            $depDate = strtotime($depDate . ', ' . $depTime);

            $arrTime = $this->http->FindSingleNode("./descendant::tr[normalize-space()][2]/descendant::td[1]", $root, true, '/(\d{1,2}\:\d{1,2})/');
            $arrDate = $this->normalizeDate($this->http->FindSingleNode("./descendant::tr[normalize-space()][2]/descendant::td[1]", $root, true, '/(\d{1,2}\-\d{1,2})/'));

            if (empty($arrDate)) {
                $arrDate = $date;
            }
            $arrDate = strtotime($arrDate . ', ' . $arrTime);

            $segment->departure()
                ->code($this->http->FindSingleNode("./descendant::tr[normalize-space()][1]", $root, true, "/\s+([A-Z]{3})\s+/"))
                ->date($depDate);

            $depTerminal = $this->http->FindSingleNode("./descendant::tr[normalize-space()][1]", $root, true, "/\s+(\D?\d?){$this->opt($this->t('Terminal'))}/u");

            if (empty($depTerminal)) {
                $depTerminal = $this->http->FindSingleNode("./descendant::tr[normalize-space()][1]", $root, true, "/\s+(\w+){$this->opt($this->t('Terminal'))}/u");
            }

            if (empty($depTerminal)) {
                $depTerminal = $this->http->FindSingleNode("./descendant::tr[normalize-space()][1]", $root, true, "/{$this->opt($this->t('Airport'))}\s+([A-Z]{1}\d{1})/u");
            }

            if (!empty($depTerminal)) {
                $segment->departure()
                    ->terminal($depTerminal);
            }

            $segment->arrival()
                ->code($this->http->FindSingleNode("./descendant::tr[normalize-space()][2]", $root, true, "/\s+([A-Z]{3})\s+/"))
                ->date($arrDate);

            $arrTerminal = $this->http->FindSingleNode("./descendant::tr[normalize-space()][2]", $root, true, "/\s+(?:(\D?\d?)|(\w+)){$this->opt($this->t('Terminal'))}/");

            if (empty($arrTerminal)) {
                $arrTerminal = $this->http->FindSingleNode("./descendant::tr[normalize-space()][2]", $root, true, "/\s+(?:(\D?\d?)|(\w+)){$this->opt($this->t('Terminal'))}/");
            }

            if (empty($arrTerminal)) {
                $arrTerminal = $this->http->FindSingleNode("./descendant::tr[normalize-space()][2]", $root, true, "/{$this->opt($this->t('Airport'))}\s+([A-Z]{1}\d{1})/");
            }

            if (!empty($arrTerminal)) {
                $segment->arrival()
                    ->terminal($arrTerminal);
            }

            $node = $this->http->FindSingleNode("./descendant::tr[normalize-space()][1]/descendant::td[4]", $root);

            if (preg_match('/^(?<operator>\D+)(?<flightName>[A-Z]{2})(?<flightNumber>\d{2,4})\s+\D+[(](?<cabin>\D+)[)]\s+\S+\s+(?<aircraft>.+)$/us', $node, $m)) {
                $segment->airline()
                    ->operator($m['operator'])
                    ->name($m['flightName'])
                    ->number($m['flightNumber'])
                    ->confirmation($airlineConfirmation[$segmentOrderNumber]);

                $segment->extra()
                    ->cabin($m['cabin'])
                    ->aircraft($m['aircraft']);
            } elseif (preg_match('/^(?<operator>\D+)?(?<flightName>[A-Z\d]{2})(?<flightNumber>\d{3,4})\s+\D+[(](?<cabin>\D+)[)](?:\s+\S+\s+(?<aircraft>.+))?$/us', $node, $m)) {
                if (isset($m['operator']) && !empty($m['operator'])) {
                    $segment->airline()
                    ->operator($m['operator']);
                }

                $segment->airline()
                    ->name($m['flightName'])
                    ->number($m['flightNumber']);

                if (!empty($airlineConfirmation[$segmentOrderNumber])) {
                    $segment->airline()
                        ->confirmation($airlineConfirmation[$segmentOrderNumber]);
                }

                $segment->extra()
                    ->cabin($m['cabin']);

                if (isset($m['aircraft']) && !empty($m['aircraft'])) {
                    $segment->extra()
                        ->aircraft($m['aircraft']);
                }
            }
            $segmentOrderNumber++;
        }
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers['from'], $this->reFrom) === false) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            if (strpos($headers['subject'], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (stripos($body, $this->reBody) === false) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if (is_string($re) && strpos($body, $re) !== false) {
                return true;
            } elseif (is_array($re)) {
                foreach ($re as $r) {
                    if (stripos($body, $r) !== false) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getHeader('date'));
        $this->http->FilterHTML = false;

        $body = $parser->getHTMLBody();

        foreach ($this->reBody2 as $lang => $re) {
            if (is_string($re) && strpos($body, $re) !== false) {
                $this->lang = $lang;

                break;
            } elseif (is_array($re)) {
                foreach ($re as $r) {
                    if (stripos($body, $r) !== false) {
                        $this->lang = $lang;

                        break;
                    }
                }
            }
        }

        $this->parseHtml($email);

        return $email;
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) {
            return "starts-with(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($str)
    {
        $year = date("Y", $this->date);
        $in = [
            '/^[^\d\s]+\s+(\d+\s+[^\d\s]+\s+\d{4},\s+\d+:\d+)$/', // Tue 22 Nov 2016, 19:55
            '/^(\d+)\s+([^\d\s]+)\.\s+(\d{4}),\s+(\d+:\d+)$/', // 10 dic. 2016, 12:35
            '/^(\d{4})年\s*(\d{1,2})月\s*(\d{1,2})日,?\s+(\d{1,2}:\d{2})$/', // 1997年 4月 30日, 12:35
            '/^(\d{1,2})月\s*(\d{1,2})日$/', //04月14日
            '/^(\d{1,2})\-(\d{1,2})$/', //04-15
        ];
        $out = [
            '$1',
            '$1 $2 $3, $4',
            '$1-$2-$3, $4',
            "$year-$1-$2",
            "$year-$1-$2",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match('/\d+\s+([^\d\s]+)\s+\d{4}/', $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return $str;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) {
            return "normalize-space(.)=\"{$s}\"";
        }, $field));
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function normalizeCurrency($string)
    {
        $string = trim($string);
        $currencies = [
            'GBP' => ['£'],
            'EUR' => ['€'],
            'USD' => ['$'],
            'CNY' => ['¥'],
        ];

        foreach ($currencies as $code => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $code;
                }
            }
        }

        return null;
    }
}
