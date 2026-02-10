<?php

namespace AwardWallet\Engine\eva\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class EMileageStatement extends \TAccountChecker
{
    public $mailFiles = "eva/it-633493990.eml, eva/it-633562590.eml, eva/it-635101991.eml, eva/it-635442754.eml, eva/statements/it-66874008.eml, eva/statements/it-66954208.eml, eva/statements/it-74113591.eml";

    public $lang = '';

    public static $dictionary = [
        "en" => [
            'Dear '             => 'Dear ',
            'Mileage Statement' => 'Mileage Statement',
            'miles'             => 'miles',

            // Type 2024
            'Self Award Miles Balance' => 'Self Award Miles Balance',
            'Membership Number '       => 'Membership Number',
            'Card Tier'                => 'Card Tier',
            'Upcoming Expiring Date'   => 'Upcoming Expiring Date',
            'Own Earned Miles'         => 'Own Earned Miles',

            // Type 2020
            'Your remaining self Award Miles in your account' => 'Your remaining self Award Miles in your account',
            'Award Miles'                                     => 'Award Miles',
            'Your membership status is'                       => 'Your membership status is',
        ],
        "zh" => [
            'Dear '             => '親愛的',
            'Mileage Statement' => '份哩程核對表',
            'miles'             => '哩',

            // Type 2024
            'Self Award Miles Balance' => '您的帳戶內自有獎勵哩程',
            'Membership Number'        => '會員卡號',
            'Card Tier'                => '會員卡籍',
            'Upcoming Expiring Date'   => '最快到期年月',
            'Own Earned Miles'         => '剩餘自有賺取哩程',

            // Type 2020
            'Your remaining self Award Miles in your account' => '您的會員卡號',
            'Award Miles'                                     => '獎勵哩程',
            'Your membership status is'                       => '您是本公司',
        ],
    ];

    private $detectSubject = [
        // en
        'EVA Air Infinity MileageLands E-Mileage Statement',
        // zh
        '長榮會員無限萬哩遊個人哩程核對表',
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[.@]evaair\.com\b/i', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers["subject"]) || empty($headers["from"])) {
            return false;
        }

        if (stripos($headers["from"], '.evaair.com') === false && stripos($headers["from"], 'EVA Air') === false) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers["subject"], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,".evaair.com/") or contains(@href,"www.evaair.com")]')->length === 0) {
            return false;
        }

        return $this->detectBody();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->detectBody();

        if (empty($this->lang)) {
            return $email;
        }
        $type = '';

        if ($this->http->XPath->query("//tr["
            . "*[normalize-space()][1][descendant::text()[normalize-space()][1][{$this->eq($this->t('Membership Number'))}]]"
            . " and *[normalize-space()][2][descendant::text()[normalize-space()][1][{$this->eq($this->t('Card Tier'))}]]" . ']')->length > 0
        ) {
            $type = 'Type2024';
            $this->parseType2024($email);
        }

        if ($this->http->XPath->query("//text()[{$this->starts($this->t('Your remaining self Award Miles in your account'))}]")->length > 0) {
            $type = 'Type2020';
            $this->parseType2020($email);
        }

        if ($this->http->XPath->query("//text()[{$this->starts($this->t('Your current card status is'))}]")->length > 0) {
            $type = 'Type2019';
            $this->parseType2019($email);
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . $type . ucfirst($this->lang));

        return $email;
    }

    public function parseType2024(Email $email)
    {
        $st = $email->add()->statement();

        $name = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Dear '))}]", null, true,
            "/{$this->opt($this->t('Dear '))}\s*([[:alpha:]][-'\.[:alpha:] ]*[[:alpha:]]),\s*$/u");

        $name = preg_replace(["/^\s*(Ms|Mr|Mrs)\.\s+/", '/\s+(先生 ?您好|女士 ?您好|您好)\s*$/'], '', $name);
        $st->addProperty('Name', $name);

        $balance = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Self Award Miles Balance'))}]/following::text()[normalize-space()][1]",
            null, true, "/^\s*(\d[,.\'\d ]*?)(?:\s*{$this->opt($this->t('miles'))})?\s*$/");

        $st->setBalance(preg_replace('/\W+/', '', $balance));

        $date = $this->normalizeDate($this->http->FindSingleNode("//text()[{$this->starts($this->t('Dear '))}]/following::text()[{$this->contains($this->t('Mileage Statement'))}][1]/ancestor::td[1]",
            null, true, "/,\s*([^,]+?)\s*{$this->opt($this->t('Mileage Statement'))}/"));
        $st->setBalanceDate($date);

        $tXpath = "//tr["
            . "*[normalize-space()][1][descendant::text()[normalize-space()][1][{$this->eq($this->t('Membership Number'))}]]"
            . " and *[normalize-space()][2][descendant::text()[normalize-space()][1][{$this->eq($this->t('Card Tier'))}]]" . ']';

        $number = $this->http->FindSingleNode($tXpath . "/*[1]", null, true, "/^\s*{$this->opt($this->t('Membership Number'))}\s*(\d+X{4}\d{3}[A-Z]{0,2})\s*$/");

        if (preg_match("/^(\d+)[Xx]+(\d+[A-Z]{0,2})$/", $number, $m)) {
            // 130XXXX771GC
            $numberMasked = $m[1] . '**' . $m[2];
            $st->setNumber($numberMasked)->masked('center')
                ->setLogin($numberMasked)->masked('center');
        } elseif (preg_match("/^\d+$/", $number)) {
            // 1309771365
            $st->setNumber($number)
                ->setLogin($number);
        } else {
            $st->setNumber(null);
        }

        // Status
        $status = $this->http->FindSingleNode($tXpath . "/*[normalize-space()][2]", null, true,
            "/^\s*{$this->opt($this->t('Card Tier'))}\s*(.+)\s*$/");
        $status = preg_replace('/(?:\s+Card|卡)\s*$/u', '', $status);
        $st->addProperty('Status', $status);

        $tXpath2 = "//tr["
            . "*[normalize-space()][1][descendant::text()[normalize-space()][1][{$this->eq($this->t('Upcoming Expiring Date'))}]]"
            . " and *[normalize-space()][2][descendant::text()[normalize-space()][1][{$this->eq($this->t('Own Earned Miles'))}]]" . ']';
        $exDate = $this->normalizeDate($this->http->FindSingleNode($tXpath2 . "/*[1]", null, true, "/^\s*{$this->opt($this->t('Upcoming Expiring Date'))}\s*(.+)\s*$/"));
        $st->setExpirationDate($exDate);

        $exBalance = $this->http->FindNodes($tXpath2 . "/*[position() > 1][normalize-space()]", null, "/^\s*[[:alpha:] ]+\s*(\d+)\s*$/u");
        $st->addProperty('ExpiringBalance', array_sum($exBalance));
    }

    public function parseType2020(Email $email)
    {
        $st = $email->add()->statement();

        $date = $this->normalizeDate($this->http->FindSingleNode("//text()[{$this->starts($this->t('Dear '))}]/preceding::text()[{$this->contains($this->t('Mileage Statement'))}][1]/ancestor::td[1]",
            null, true, "/^\s*([^,]+?)\s*{$this->opt($this->t('Mileage Statement'))}/"));
        $st->setBalanceDate($date);

        // Name
        $name = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Dear '))}]", null, true,
            "/{$this->opt($this->t('Dear '))}\s*([[:alpha:]][-'\.[:alpha:] ]*[[:alpha:]])\s*[:]\s*$/u");

        $name = preg_replace(["/^\s*(Ms|Mr|Mrs)\.\s+/", '/\s+(先生 ?您好|女士 ?您好|您好)\s*$/'], '', $name);
        $st->addProperty('Name', $name);

        $number = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Your remaining self Award Miles in your account'))}]",
            null, true, "/{$this->opt($this->t('Your remaining self Award Miles in your account'))}[:\s]*([A-Z\d]{5,})(?: |from|至|$)/");

        if (preg_match("/^(\d+)[Xx]+(\d+[A-Z]{0,2})$/", $number, $m)) {
            // 130XXXX771GC
            $numberMasked = $m[1] . '**' . $m[2];
            $st->setNumber($numberMasked)->masked('center')
                ->setLogin($numberMasked)->masked('center');
        } elseif (preg_match("/^\d+$/", $number)) {
            // 1309771365
            $st->setNumber($number)
                ->setLogin($number);
        } else {
            $st->setNumber(null);
        }

        // Balance
        $balance = $this->http->FindSingleNode("//tr[{$this->eq($this->t('Award Miles'))}]/following-sibling::tr[normalize-space()][1]", null, true, "/^\s*(\d+)\s*(?:{$this->opt($this->t('miles'))})?\s*$/i");
        $st->setBalance($balance);

        // Status
        $status = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Your membership status is'))}][last()]/ancestor-or-self::node()[not({$this->eq($this->t('Your membership status is'))})][1]",
            null, true, "/{$this->opt($this->t('Your membership status is'))}\s*([-[:alpha:]]+)(?:\s+Card|卡)/u");
        $st->addProperty('Status', $status);
    }

    public function parseType2019(Email $email)
    {
        $st = $email->add()->statement();

        // Name
        $name = $this->http->FindSingleNode("//text()[{$this->starts('Card Number')}]/preceding::text()[normalize-space()][1]",
            null, true, "/^(?:Mr\.|Ms\.)\s*([[:alpha:]][-'\.[:alpha:] ]*[[:alpha:]])$/u");

        $st->addProperty('Name', $name);

        // Number
        $number = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Card Number'))}]",
            null, true, "/^{$this->opt($this->t('Card Number'))}[:\s]+([A-Z\d]{5,})\s*$/");

        if (preg_match("/^(\d+)[Xx]+(\d+[A-Z]{0,2})$/", $number, $m)) {
            // 130XXXX771GC
            $numberMasked = $m[1] . '**' . $m[2];
            $st->setNumber($numberMasked)->masked('center')
                ->setLogin($numberMasked)->masked('center');
        } elseif (preg_match("/^\d+$/", $number)) {
            // 1309771365
            $st->setNumber($number)
                ->setLogin($number);
        } else {
            $st->setNumber(null);
        }

        $date = $this->normalizeDate($this->http->FindSingleNode("//text()[{$this->starts($this->t('Mileage Posted Period'))}][1]/ancestor::td[1]",
            null, true, "/～\s*([\d\/]{6,})\s*$/"));

        if (!empty($date)) {
            $date = strtotime('+ 1 day', $date);
        }
        $st->setBalanceDate($date);

        // Status
        $status = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Your current card status is'))}]/following::text()[normalize-space()][1]", null, true, "/^\s*(.+?)\s*(?:Card|卡)?\s*$/");
        $st->addProperty('Status', $status);

        // Balance
        $balance = $this->http->FindSingleNode("//table/descendant::text()[normalize-space()][1][{$this->eq($this->t('Mileage Balance'))}]/following::text()[normalize-space()][1]", null, true, "/^\d[,.\'\d ]*$/");
        $balance = preg_replace('/\W+/', '', $balance);
        $st->setBalance($balance);
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function detectBody(): bool
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict['Mileage Statement']) && $this->http->XPath->query("//node()[{$this->contains($dict['Mileage Statement'])}]")->length > 0) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function normalizeDate($date)
    {
        // $this->logger->debug('$date = '.print_r( $date,true));
        $in = [
            "/^\s*(\d{4})\s*\/\s*(\d{1,2})\s*$/", // 2024/1
            "/^\s*(\d{4})\s*年\s*(\d{1,2})\s*月\s*$/",
        ];
        $out = [
            "$1-$2-1",
            "$1-$2-1",
        ];
        $date = preg_replace($in, $out, $date);

        // if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $date, $m)) {
        //     if ($en = MonthTranslate::translate($m[1], $this->lang)) {
        //         $date = str_replace($m[1], $en, $date);
        //     }
        // }

        return strtotime($date);
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'contains(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
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

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }
}
