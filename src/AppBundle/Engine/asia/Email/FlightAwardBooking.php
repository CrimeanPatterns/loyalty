<?php

namespace AwardWallet\Engine\asia\Email;

// use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class FlightAwardBooking extends \TAccountChecker
{
    public $mailFiles = "asia/it-31751801.eml, asia/it-31787161.eml, asia/it-208024953.eml";

    public $detectFrom = [
        "cathaypacific.com",
        "@asiamiles.com",
    ];
    public $detectSubject = [
        "en" => "Flight award booking",
        "zh" => "飛行獎勵預訂",
    ];
    public $detectCompany = [
        '%2Fwww.cathaypacific.com%2F',
        '/www.cathaypacific.com/',
        // en
        "choosing Cathay Pacific",
        "choosing Asia Miles",
        // zh
        '感謝你選擇國泰',
    ];
    public $detectBody = [
        "en" => "Flight awards",
        "zh" => ["飛行獎勵", '飞行奖励'],
    ];

    public $lang = "en";
    public static $dictionary = [
        "en" => [
            // 'Dear' => '',
            // 'Booking reference:' => '',
            // 'Flight' => '',
            // 'Depart' => '',
            // 'Arrive' => '',
            // 'Terminal' => '',
            // 'Duration' => '',
            // 'Cabin class' => '',
            // 'Seat' => '',
            // 'Meal' => '',
            // 'Passenger(s)' => '',
            'travellerTitle' => ["Adult", "Child", "Infant"],
            'accountName'    => ['Asia Miles', 'Marco Polo Club', 'Cathay'],
            // 'Miles, taxes and surcharges' => '',
            // 'Carrier surcharges' => '',
            // 'Taxes/fees/charges' => '',
            'totalPrice' => ['Total:', 'Total amount:'],
        ],
        "zh" => [
            'Dear'                        => '親愛的',
            'Booking reference:'          => ['預訂號碼:', '預訂號碼：', '预订号码：'],
            'Flight'                      => '航班',
            'Depart'                      => ['出發時間', '出发时间'],
            'Arrive'                      => ['抵達時間', '抵达时间'],
            'Terminal'                    => ['航空大樓', '航站楼'],
            'Duration'                    => ['航行時間', '航行时间'],
            'Cabin class'                 => ['客艙級別', '客舱级别'],
            'Seat'                        => '座位',
            'Meal'                        => ['餐膳', '餐食'],
            'Passenger(s)'                => '乘客',
            'travellerTitle'              => ["成人", "兒童"],
            'accountName'                 => ['Asia Miles', 'Marco Polo Club', 'Cathay', '國泰'],
            'Miles, taxes and surcharges' => '里數、稅項和附加費',
            'Carrier surcharges'          => '航空公司附加費',
            'Taxes/fees/charges'          => '稅項／費用 / 收費',
            'totalPrice'                  => ['總計：'],
        ],
    ];

    public function flight(Email $email): void
    {
        $f = $email->add()->flight();

        // General
        $f->general()
            ->confirmation($this->nextText($this->t('Booking reference:')),
                $this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking reference:'))}]", null, true, "/^\s*(.+?)\s*:\s*$/"));

        $travellers = array_unique(array_map(function ($v) { return preg_replace("#^\s*(Mr|Miss|Mrs|Ms|Dr|Mstr|Master)[.]?\s+#i", '', $v); },
                $this->http->FindNodes("//text()[{$this->eq($this->t('Passenger(s)'))}]/ancestor::tr[1]/following-sibling::tr/descendant::text()[" . $this->eq($this->t('travellerTitle')) . "]/following::text()[normalize-space()][1]")));
        $areNamesFull = true;

        if (empty($travellers)) {
            $travellerNames = array_filter($this->http->FindNodes("//text()[{$this->starts($this->t('Dear'))}]", null, "/^{$this->opt($this->t('Dear'))}[,\s]+([[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])(?:\s*[,;:!?]|$)/u"));

            if (count(array_unique($travellerNames)) === 1) {
                $traveller = array_shift($travellerNames);
                $travellers = [$traveller];
                $areNamesFull = null;
            }
        }

        $f->general()->travellers($travellers, $areNamesFull);

        // Program
        $accounts = array_unique(array_filter($this->http->FindNodes("//text()[" . $this->eq($this->t('accountName')) . "]", null, "#{$this->opt($this->t('accountName'))}\s*([\d\*]{6,})\b#")));

        if (empty($accounts)) {
            $accounts = array_unique(array_filter($this->http->FindNodes("//text()[" . $this->eq($this->t('accountName')) . "]/following::text()[normalize-space()][1]", null, "#^\s*([\d\*]{6,})\b#")));
        }

        foreach ($accounts as $account) {
            if (preg_match("/^\d+$/", $account)) {
                $f->program()
                    ->account($account, false);
            } elseif (preg_match("/^\d*\D+\d*$/", $account)) {
                $f->program()
                    ->account($account, true);
            }
        }

        // Price
        $total = $this->http->FindSingleNode("(//text()[{$this->eq($this->t('Miles, taxes and surcharges'))}]/following::text()[{$this->eq($this->t('totalPrice'))}])[1]/ancestor::tr[1]");

        if ($total !== null && preg_match("/{$this->opt($this->t('totalPrice'))}\s*(\d[\d,]*)\s*[+]\s*([A-Z]{3})\s*(\d[\d,]*)\s*$/", $total, $m)) {
            $f->price()
                ->spentAwards($m[1])
                ->currency($m[2])
                ->total(str_replace(',', '', $m[3]))
            ;

            $tax1 = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Carrier surcharges'))}]/ancestor::td[1]/following-sibling::td[normalize-space()][2]", null, true, "#^\s*[A-Z]{3}\s*(\d[\d,]*)\s*$#");

            if ($tax1 !== null) {
                $f->price()
                    ->fee($this->http->FindSingleNode("//text()[{$this->eq($this->t('Carrier surcharges'))}]"), str_replace(',', '', $tax1));
            }

            $tax2 = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Taxes/fees/charges'))}]/ancestor::td[1]/following-sibling::td[normalize-space()][2]", null, true, "#^\s*[A-Z]{3}\s*(\d[\d,]*)\s*$#");

            if ($tax2 !== null) {
                $f->price()
                    ->fee($this->http->FindSingleNode("//text()[{$this->eq($this->t('Taxes/fees/charges'))}]"), str_replace(',', '', $tax2));
            }
        }

        // Segments
        $xpath = "//td[{$this->starts($this->t('Depart'))}]/following::td[1][{$this->starts($this->t('Arrive'))}]/ancestor::tr[1]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $date = $this->normalizeDate($this->http->FindSingleNode("preceding::tr[normalize-space()][1]/descendant::text()[normalize-space()][1]", $root));

            // Airline
            $node = implode("\n", $this->http->FindNodes("*[{$this->starts($this->t('Flight'))}]/descendant::text()[normalize-space()]", $root));

            if (preg_match("/{$this->opt($this->t('Flight'))}\s*(?<al>[A-Z\d][A-Z]|[A-Z][A-Z\d])[ ]*(?<fn>\d+)\b/", $node, $m)) {
                $s->airline()->name($m['al'])->number($m['fn']);

                if (preg_match("/{$this->opt($this->t('Flight'))}\s*(?:[A-Z\d][A-Z]|[A-Z][A-Z\d])[ ]*\d+\s*\n+.{2,}\n+(.{2,}?)\s*$/", $node, $mat)) {
                    $s->extra()->aircraft($mat[1]);
                }
            }

            // Departure
            $node = implode("\n", $this->http->FindNodes("*[{$this->starts($this->t('Depart'))}]/descendant::text()[normalize-space()]", $root));

            if (preg_match("/{$this->opt($this->t('Depart'))}\s+(?<time>\d{1,2}:\d{2}.*)\s+(?<name>[\s\S]+?)\s*\(\s*(?<code>[A-Z]{3})\s*\)(?<term>\s+.*{$this->opt($this->t('Terminal'))}.*)?/iu", $node, $m)) {
                $s->departure()
                    ->code($m['code'])
                    ->name($m['name'])
                    ->date((!empty($date)) ? strtotime($m['time'], $date) : false)
                ;

                if (!empty($m['term'])) {
                    $s->departure()
                        ->terminal(trim(preg_replace("#\s*{$this->opt($this->t('Terminal'))}\s*#iu", ' ', $m['term'])));
                }
            }

            // Arrival
            $node = implode("\n", $this->http->FindNodes("*[{$this->starts($this->t('Arrive'))}]/descendant::text()[normalize-space()]", $root));

            if (preg_match("/{$this->opt($this->t('Arrive'))}\s+(?<time>\d{1,2}:\d{2}.*)(?<overnight>\s*[+\-]\d+)?\s+(?<name>[\s\S]+?)\s*\(\s*(?<code>[A-Z]{3})\s*\)(?<term>\s+.*{$this->opt($this->t('Terminal'))}.*)?/iu", $node, $m)) {
                $s->arrival()
                    ->code($m['code'])
                    ->name($m['name'])
                    ->date((!empty($date)) ? strtotime($m['time'], $date) : false)
                ;

                if (!empty($m['overnight']) && !empty($s->getArrDate())) {
                    $s->arrival()
                        ->date(strtotime($m['overnight'] . ' day', $s->getArrDate()));
                }

                if (!empty($m['term'])) {
                    $s->arrival()
                        ->terminal(trim(preg_replace("#\s*{$this->opt($this->t('Terminal'))}\s*#iu", ' ', $m['term'])));
                }
            }

            // Extra
            $s->extra()->duration($this->http->FindSingleNode("*[{$this->starts($this->t('Duration'))}]", $root, true, "/^{$this->opt($this->t('Duration'))}\s*(\d.*)/i")
                ?? $this->http->FindSingleNode("following-sibling::tr[normalize-space()][1]/*[{$this->starts($this->t('Duration'))}]", $root, true, "/^{$this->opt($this->t('Duration'))}\s*(\d.*)/i")
            );
            $cabin = implode("\n", $this->http->FindNodes("following-sibling::tr[normalize-space()][1]/*[{$this->starts($this->t('Cabin class'))}]/descendant::text()[normalize-space()]", $root));

            if (preg_match("/{$this->opt($this->t('Cabin class'))}\n+(.+)\n+.+\s*$/", $cabin, $m)) {
                $s->extra()
                    ->cabin($m[1]);
            } elseif (preg_match("/{$this->opt($this->t('Cabin class'))}\s*(\w.*)$/s", $cabin, $m)) {
                $s->extra()
                    ->cabin(preg_replace("/\s*\n\s*/", '', $m[1]));
            }

            $seats = array_filter($this->http->FindNodes("following-sibling::tr[normalize-space()][1]/*[{$this->starts($this->t('Seat'))}]/descendant::text()[normalize-space()]", $root, '/^\s*(\d{1,5}[A-Z])\s*$/'));

            if (!empty($seats)) {
                $s->extra()->seats($seats);
            }

            $s->extra()->meal($this->http->FindSingleNode("following-sibling::tr[normalize-space()][2]/*[{$this->starts($this->t('Meal'))}]", $root, true, "/^{$this->opt($this->t('Meal'))}\s*(.{2,})/i"), false, true);

            if (!empty($this->http->FindSingleNode("preceding::tr[normalize-space()][1]/descendant::text()[normalize-space()][last()][contains(., 'Waitlisted')]", $root))) {
                $s->extra()->status('Not confirmed');
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $body = html_entity_decode($this->http->Response["body"]);

        foreach ($this->detectBody as $lang => $dBody) {
            if ($this->http->XPath->query("//node()[{$this->starts($dBody)}]")->length > 0) {
                $this->lang = $lang;

                break;
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $this->flight($email);

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->detectFrom as $dFrom) {
            if (strpos($from, $dFrom) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        $finded = false;

        foreach ($this->detectFrom as $dFrom) {
            if (strpos($headers["from"], $dFrom) !== false) {
                $finded = true;

                break;
            }
        }

        if (!$finded) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers["subject"], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();
        $finded = false;

        foreach ($this->detectCompany as $dCompany) {
            if (strpos($body, $dCompany) !== false || $this->http->XPath->query("//a[contains(@href, '" . $dCompany . "')]")->length > 0) {
                $finded = true;

                break;
            }
        }

        if (!$finded) {
            return false;
        }

        foreach ($this->detectBody as $detectBody) {
            if ($this->http->XPath->query("//node()[{$this->starts($detectBody)}]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function normalizeDate($date)
    {
        // $this->logger->debug($date);
        $in = [
            // 2023年11月18日(星期六)
            "/^\s*(\d{4})\s*年\s*(\d{1,2})\s*月\s*(\d{1,2})\s*日\s*\(\D*\)\s*$/i",
        ];
        $out = [
            "$1-$2-$3",
        ];
        $date = preg_replace($in, $out, $date);
        // $this->logger->debug($date);
        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        return strtotime($date);
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function nextText($field, $root = null)
    {
        $rule = $this->eq($field);

        return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::text()[normalize-space(.)!=''][1]", $root);
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'normalize-space(' . $node . ')=' . $s;
        }, $field)) . ')';
    }

    private function starts($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'starts-with(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }
}
