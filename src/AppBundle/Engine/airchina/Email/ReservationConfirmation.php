<?php

namespace AwardWallet\Engine\airchina\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class ReservationConfirmation extends \TAccountChecker
{
    public $mailFiles = "airchina/it-2859587.eml";

    public $reFrom = ["airchina.com"];
    public $reBody = [
        'en' => ['Air Itinerary Details', 'Booking Confirmation'],
        'zh' => ['航班行程明细', '订单确认'],
    ];
    public $reSubject = [
        'Reservation Confirmation',
        '预定确认 Reservation Confirmation',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
        ],
        'zh' => [
            'Reservation Code:'     => '订单号：',
            'Booking Confirmation'  => '订单确认',
            'Email:'                => '电子邮件：',
            'Issue Date:'           => '预订日期：',
            'TOTAL AIR FARE'        => '航程费用总额',
            'Membership No'         => '会员号',
            'Ticket Number'         => '票号',
            'Passengers'            => '乘客信息：',
            'Seat'                  => '座位',
            'Air Itinerary Details' => '航班行程明细',
            'Fare'                  => '票价类型',
            'Stop'                  => '中途停靠',
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug('can\'t determine a language');

            return $email;
        }

        $a = explode('\\', __CLASS__);
        $class = end($a);
        $email->setType($class . ucfirst($this->lang));

        $this->parseEmail($email);

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href,'airchina.com')]/@href | //img[contains(@src,'airchina.com') or contains(@alt,'Air China')]")->length > 0) {
            return $this->assignLang();
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && isset($headers["subject"])) {
            $flag = false;

            foreach ($this->reFrom as $reFrom) {
                if (stripos($headers['from'], $reFrom) !== false) {
                    $flag = true;
                }
            }

            if ($flag) {
                foreach ($this->reSubject as $reSubject) {
                    if (stripos($headers["subject"], $reSubject) !== false) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->reFrom as $reFrom) {
            if (stripos($from, $reFrom) !== false) {
                return true;
            }
        }

        return false;
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

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function parseEmail(Email $email)
    {
        $email->ota()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Reservation Code:'))}]/following::text()[normalize-space(.)!=''][1]",
                null, true, "#^\s*([\w\-]{5,})\s*$#"));

        $email->setUserEmail($this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking Confirmation'))}]/following::table[1]/descendant::text()[{$this->eq($this->t('Email:'))}]/ancestor::td[1]/following-sibling::td[1]"));

        $f = $email->add()->flight();
        $f->general()
            ->noConfirmation()
            ->date($this->normalizeDate($this->http->FindSingleNode("//text()[{$this->eq($this->t('Issue Date:'))}]/following::text()[normalize-space(.)!=''][1]")))
            ->travellers($this->http->FindNodes("//text()[{$this->eq($this->t('Passengers'))}]/following::table[1]//td[1][normalize-space(.)!='']"));

        $tot = $this->getTotalCurrency($this->http->FindSingleNode("//text()[{$this->contains($this->t('TOTAL AIR FARE'))}]"));

        if (!empty($tot['Total'])) {
            $f->price()
                ->total($tot['Total'])
                ->currency($tot['Currency']);
        }
        $acc = array_filter(array_unique($this->http->FindNodes("//text()[{$this->starts($this->t('Membership No'))}]/ancestor::td[1]/following-sibling::td[1]",
            null, "#\b([A-Z\d]{5,})$#")));

        if (count($acc) > 0) {
            $f->program()
                ->accounts($acc, false);
        }

        $tickets = array_filter(array_unique($this->http->FindNodes("//text()[{$this->starts($this->t('Ticket Number'))}]/ancestor::td[1]/following-sibling::td[1]",
            null, "#\b([\-\d]{5,})$#")));

        if (count($tickets) > 0) {
            $f->issued()
                ->tickets($tickets, false);
        }

        //TODO: need correct parse seats, more examples
        $seatsPaxes = $this->http->FindNodes("//text()[{$this->eq($this->t('Passengers'))}]/following::table[1]/descendant::text()[{$this->eq($this->t('Seat'))}]/ancestor::td[1]/following-sibling::td[1]");
        $flightSeats = [];

        foreach ($seatsPaxes as $seatsPax) {
            $seats = array_map("trim", explode(',', $seatsPax));

            foreach ($seats as $i => $seat) {
                $flightSeats[$i][] = $seat;
            }
        }
        $flightSeats = array_map(function ($s) {
            if (is_array($s)) {
                $new = [];

                foreach ($s as $v) {
                    if (preg_match("#\b(\d+[A-z])$#", $v, $m)) {
                        $new[] = $m[1];
                    } else {
                        $new[] = null;
                    }
                }

                return $new;
            } else {
                return null;
            }
        }, $flightSeats);

        $ruleTime = "contains(translate(normalize-space(.),'0123456789','dddddddddd--'),'d:dd')";
        $xpath = "//text()[{$this->contains($this->t('Air Itinerary Details'))}]/following::tr[count(td[normalize-space(.)!=''])=4 and td[{$ruleTime}]]";
        $nodes = $this->http->XPath->query($xpath);

        if (count($flightSeats) !== $nodes->length) {
            $this->logger->debug('maybe need correct parse seats');
            $flightSeats = [];
        }

        foreach ($nodes as $i => $root) {
            $s = $f->addSegment();

            if (isset($flightSeats[$i]) && !empty($seats = array_filter($flightSeats[$i]))) {
                $s->extra()
                    ->seats($seats);
            }

            $node = $this->http->FindNodes("./td[1]//text()[normalize-space(.)!='']", $root);

            if (count($node) === 3) {
                $s->departure()
                    ->noCode()
                    ->name(trim($node[0], ' ,'))
                    ->date(strtotime($node[2], $this->normalizeDate($node[1])));
            }

            $node = $this->http->FindNodes("./td[2]//text()[normalize-space(.)!='']", $root);

            if (count($node) === 3) {
                $s->arrival()
                    ->noCode()
                    ->name(trim($node[0], ' ,'))
                    ->date(strtotime($node[2], $this->normalizeDate($node[1])));
            }
            $node = $this->http->FindSingleNode("./td[3]/descendant::text()[normalize-space(.)!=''][1]", $root);

            if (preg_match("#([A-Z\d][A-Z]|[A-Z][A-Z\d])\s*(\d+)#", $node, $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2]);
            }
            $node = $this->http->FindSingleNode("./td[4]/descendant::text()[normalize-space(.)!=''][1][{$this->starts($this->t('Fare'))}]",
                $root, false, "#[:：] *(.+)#u");

            if (!empty($node)) {
                $s->extra()->cabin($node);
            }
            $node = $this->http->FindSingleNode("./td[4]/descendant::text()[{$this->starts($this->t('Stop'))}]", $root,
                false, "#[:：] *(\d+)#");

            if (!empty($node)) {
                $s->extra()->stops($node);
            }
        }

        return true;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang()
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if ($this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody[0]}')]")->length > 0
                    && $this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody[1]}')]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function getTotalCurrency($node)
    {
        $node = str_replace("€", "EUR", $node);
        $node = str_replace("$", "USD", $node);
        $node = str_replace("£", "GBP", $node);
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node,
                $m) || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node,
                $m) || preg_match("#(?<c>\-*?)(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)
        ) {
            $m['t'] = preg_replace('/\s+/', '', $m['t']);            // 11 507.00	->	11507.00
            $m['t'] = preg_replace('/[,.](\d{3})/', '$1', $m['t']);    // 2,790		->	2790		or	4.100,00	->	4100,00
            $m['t'] = preg_replace('/^(.+),$/', '$1', $m['t']);    // 18800,		->	18800
            $m['t'] = preg_replace('/,(\d{2})$/', '.$1', $m['t']);    // 18800,00		->	18800.00
            $tot = $m['t'];
            $cur = $m['c'];
        }

        return ['Total' => $tot, 'Currency' => $cur];
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

    private function normalizeDate($date)
    {
        $in = [
            //Mon, 02 Jul 2018,
            '#^.+,\s+(\d+)\s+(\w+)\s+(\d{4}),? *$#u',
            //星期三, 2015年7月22日,
            '#^.+,\s+(\d{4})年(\d+)月(\d+)日,? *$#u',
        ];
        $out = [
            '$1 $2 $3',
            '$1-$2-$3',
        ];
        $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));

        return strtotime($str);
    }
}
