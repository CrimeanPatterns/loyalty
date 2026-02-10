<?php

namespace AwardWallet\Engine\aa\Email\Statement;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class ESummary extends \TAccountChecker
{
    public $mailFiles = "aa/statements/it-62086268.eml, aa/statements/it-62090397.eml, aa/statements/it-62186471.eml, aa/statements/it-62467559.eml, aa/statements/it-69218981.eml, aa/statements/it-70171955.eml, aa/statements/it-95070018.eml";

    public static $dictionary = [
        'en' => [
            'Hello,' => ['Hello ', 'Hello,'],
            //            'as of' => '',
            'Award miles'    => ['Award miles', 'Award Miles'],
            'Miles expire*:' => 'Miles expire*:',
            //            'Your Progress to' => '',
            //            'Miles (EQM)' => '',
            //            'Segments (EQS)' => '',
        ],
        'pt' => [
            'Hello,'           => 'Olá,',
            'as of'            => 'Data do extrato',
            'Award miles'      => 'Milhas prêmio',
            'Miles expire*:'   => 'Milhas expirar*:',
            'Your Progress to' => 'Seu progresso para',
            'Miles (EQM)'      => 'Milhas (EQM)',
            'Segments (EQS)'   => 'Trechos (EQS)',
        ],
        'es' => [
            'Hello,'           => ['Hola ', 'Hello,', 'Estimado(a)', 'Hola,'],
            'as of'            => ['A partir del', 'al '],
            'Award miles'      => ['Millas premio', 'Millas de premio'],
            'Miles expire*:'   => 'Expiración de millas*:',
            'Your Progress to' => 'Su progreso a ',
            'Miles (EQM)'      => 'Millas (EQM)',
            'Segments (EQS)'   => 'Segmentos (EQS)',
        ],
        'fr' => [
            'Hello,'         => 'Bonjour ',
            'as of'          => '',
            'Award miles'    => 'Miles',
            'Miles expire*:' => 'Expiration*:',
            //            'Your Progress to' => '',
            //            'Miles (EQM)' => '',
            //            'Segments (EQS)' => '',
        ],
        'ko' => [
            'Hello,'         => '안녕하세요 ',
            'as of'          => '기준',
            'Award miles'    => '보유 마일',
            'Miles expire*:' => '마일 소멸 예정일*:',
            //            'Your Progress to' => '',
            //            'Miles (EQM)' => '',
            //            'Segments (EQS)' => '',
        ],
        'zh' => [
            'Hello,'         => '尊敬的 ',
            'as of'          => '账户摘要 - as of',
            'Award miles'    => '奖励里程',
            'Miles expire*:' => '里程到期日*:',
            //            'Your Progress to' => '',
            //            'Miles (EQM)' => '',
            //            'Segments (EQS)' => '',
        ],
        'ja' => [
            'Hello,'         => 'こんにちは ',
            'as of'          => '',
            'Award miles'    => '付与マイル数',
            'Miles expire*:' => 'マイル 有効期限*:',
            //            'Your Progress to' => '',
            //            'Miles (EQM)' => '',
            //            'Segments (EQS)' => '',
        ],
    ];

    private $lang = '';

    private $reProvider = ['American Airlines'];
    private $reSubject = [
        'Welcome to a world of mileage awards',
        'Welcome to a world of earning miles',
        'Don’t miss out: miles are ',
        'view your current eSummary',
        'Tell us about your flight',
    ];
    private $reBody = [
        'en' => [
            'because you recently enrolled in the AAdvantage Program',
            ' because you subscribe to AAdvantage',
            'We value your opinion',
            'Plus, earn up to 3 AAdvantage® miles',
            'Plus, earn up to 4 AAdvantage® miles',
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match('/@(?:loyalty|survey|info)\.(?:as|av|ms)\.aa\.com/i', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        return $this->arrikey($headers['subject'], $this->reSubject) !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[{$this->contains($this->reProvider)}]")->length === 0) {
            return false;
        }

        return $this->detectBody();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        foreach (self::$dictionary as $lang => $dict) {
            if ($this->http->XPath->query("//node()[" . $this->starts($dict['Miles expire*:']) . "]")->length > 0) {
                $this->lang = $lang;

                break;
            }
        }

        if (empty($this->lang)) {
            foreach (self::$dictionary as $lang => $dict) {
                if ($this->http->XPath->query("//node()[" . $this->starts($dict['Hello,']) . "]")->length > 0) {
                    $this->lang = $lang;

                    break;
                }
            }
        }

        if (empty($lang)) {
            return $email;
        }
        $this->logger->debug("Lang: {$this->lang}");

        $st = $email->add()->statement();

        $statuses = [
            "Member", "member", "Gold", "gold", "Platinum", "platinum", "Executive Platinum", "Platinum Pro",
        ];

        $xpathHeaderLine = "//tr[ not(.//tr) and *[1][{$this->starts($this->t('Hello,'))}] and *[3][{$this->starts($this->t('AAdvantage'))}] ]";

        // Status
        // Number
        $statusNumber = implode("\n", $this->http->FindNodes($xpathHeaderLine . '/*[3]/descendant::text()[normalize-space()]'));

        if (empty($statusNumber)) {
            $statusNumber = implode("\n", $this->http->FindNodes("//text()[{$this->starts($this->t('Hello,'))}]/following::text()[starts-with(normalize-space(), 'AAdvantage')][1]/ancestor::td[1]/descendant::text()[normalize-space()]"));
        }

        if (empty($statusNumber)) {
            $statusNumber = $this->http->FindSingleNode("//img[contains(@alt, 'member')]/@alt") . "\n" . $this->http->FindSingleNode("//img[contains(@alt, 'member')]/@alt/following::text()[normalize-space()][1]");
        }

        if (preg_match("/^{$this->opt($this->t('AAdvantage'))}[®\s]+(?<status>{$this->opt($statuses)})\n+(?<number>[A-Z\d]{5,12})$/", $statusNumber, $m)) {
            /*
                AAdvantage ® Member
                89HVJ64
            */
            $st->setNumber($m['number'])
                ->setLogin($m['number'])
                ->addProperty('Status', ucwords(preg_replace('/\s+/', ' ', $m['status'])));
        }

        // Name
        $name = $this->http->FindSingleNode($xpathHeaderLine . '/*[1]', null, true, "/{$this->opt($this->t('Hello,'))}[,\s]*([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])(?:\s*[,;:!?]|$)/u");

        if (empty($name)) {
            $name = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Hello,'))}]", null, true, "/{$this->opt($this->t('Hello,'))}[,\s]*([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])(?:\s*[,;:!?]|$)/u");
        }
        $st->addProperty('Name', $name);

        // Balance
        $balance = str_replace(',', '', $this->http->FindSingleNode("//td[" . $this->starts($this->t("Award miles")) . "][following-sibling::td[normalize-space()][1][" . $this->starts($this->t("Miles expire*:")) . "]]", null, true,
            "#" . $this->opt($this->t("Award miles")) . "\s*(\d[\d,]*)\s*$#"));

        if (empty($balance) && $balance !== "0") {
            $balance = str_replace(',', '', $this->http->FindSingleNode("//td[" . $this->eq($this->t("Award miles")) . "][following::td[normalize-space()][2][" . $this->starts($this->t("Miles expire*:")) . "]]/following::td[normalize-space()][1]", null, true,
                "#^\s*(\d[\d,]*)\s*$#"));
        }

        if (empty($balance) && $balance !== "0") {
            $balance = str_replace(',', '', $this->http->FindSingleNode("//td[" . $this->starts($this->t("Award miles")) . "][following-sibling::td[normalize-space()][1]]", null, true,
                "#" . $this->opt($this->t("Award miles")) . "\s*(\d[\d,]*)\s*#s"));
        }

        if (!empty($balance) || $balance === "0") {
            $st->setBalance($balance);

            $exDate = $this->http->FindSingleNode("(//td[not(.//td)][" . $this->starts($this->t("Miles expire*:")) . "])[1]", null, true,
                "#:\s*(\d{1,4}/(\w{1,3})/\d{1,4})\s*$#u");

            if (empty($exDate)) {
                $exDate = $this->http->FindSingleNode("//td[starts-with(normalize-space(),'Your AAdvantage eSummary')]/descendant::td[2]", null, true, "/^\s*as\s*of\s*([\d\/]+)$/");
            }

            if (!empty($exDate)) {
                $st->setExpirationDate($this->normalizeDate($exDate));
            }

            $bDate = $this->http->FindSingleNode("(//td[" . $this->starts($this->t("Award miles")) . "][following-sibling::td[normalize-space()][1][" . $this->starts($this->t("Miles expire*:")) . "]])[1]/preceding::text()[normalize-space()][1][" . $this->starts($this->t("as of")) . "]", null, true,
                "#" . $this->opt($this->t("as of")) . "\s*(\d{2}/\w{1,3}/\d{4})\s*$#");

            if (empty($bDate)) {
                $bDate = $this->http->FindSingleNode("(//td[" . $this->eq($this->t("Award miles")) . "][following::td[normalize-space()][2][" . $this->starts($this->t("Miles expire*:")) . "]])[1]/preceding::text()[normalize-space()][1][" . $this->contains($this->t("as of")) . "]", null, true,
                    "#" . $this->opt($this->t("as of")) . "\s*(\d{2}/\w{1,3}/\d{4})\s*$#");
            }

            if (empty($bDate)) {
                $bDate = $this->http->FindSingleNode("(//td[" . $this->starts($this->t("Award miles")) . "][following-sibling::td[normalize-space()][1][" . $this->starts($this->t("Miles expire*:")) . "]])[1]/preceding::text()[normalize-space()][1][" . $this->contains($this->t("as of")) . "]", null, true,
                    "#^\s*(\d{1,4}/\w{1,3}/\d{1,4})\s*" . $this->opt($this->t("as of")) . "#");
            }

            if (!empty($bDate)) {
                $st->setBalanceDate($this->normalizeDate($bDate));
            }
        } elseif (!empty($st->getNumber())) {
            $st->setNoBalance(true);
        }

        if (!empty($this->http->FindSingleNode("(//text()[" . $this->starts($this->t("Your Progress to")) . "])[1]"))) {
            $eqm = implode("\n", $this->http->FindNodes("//text()[" . $this->starts($this->t("Your Progress to")) . "]/following::tr[count(./td) = 5]/td[descendant::text()[" . $this->eq($this->t("Miles (EQM)")) . "]]"));

            if (preg_match("#" . $this->opt($this->t("Miles (EQM)")) . "\s+(\d[\d,]*)\s+\d[\d,]*\s*$#", $eqm, $m)) {
                $st->addProperty('EliteMiles', str_replace(',', '', $m[1]));
            }
            $eqs = implode("\n", $this->http->FindNodes("//text()[" . $this->starts($this->t("Your Progress to")) . "]/following::tr[count(./td) = 5]/td[descendant::text()[" . $this->eq($this->t("Segments (EQS)")) . "]]"));

            if (preg_match("#" . $this->opt($this->t("Segments (EQS)")) . "\s+(\d+)\s*\d+\s*$#", $eqs, $m)) {
                $st->addProperty('EliteSegments', $m[1]);
            }
        }

        return $email;
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
        if (!isset($this->reBody)) {
            return false;
        }

        foreach ($this->reBody as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (!is_string($phrase)) {
                    continue;
                }

                if ($this->http->XPath->query("//node()[{$this->contains($phrase)}]")->length > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    private function t(string $phrase)
    {
        if (!isset(self::$dictionary, $this->lang) || !isset(self::$dictionary[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$this->lang][$phrase];
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ')="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'starts-with(normalize-space(' . $node . '),"' . $s . '")';
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

    private function normalizeDate($str)
    {
        //        $this->logger->debug('$date1 = '.print_r( $str,true));
        if ($this->lang == 'en' && preg_match("#^\s*(\d{2})/(\d{2})/(\d{4})$#", $str, $m)) {
            $langCode = $this->http->FindSingleNode("//text()[contains(., 'US US')]", null, true, "#^US US$#");
//            $this->logger->notice($langCode);

            if (!empty($langCode) || $m[2] > 12) {
                $str = "{$m[2]}/{$m[1]}/{$m[3]}";
            }
        }
        $in = [
            "#^\s*(\d{2})/(\d{2})/(\d{4})\s*$#iu", // 09/10/2019
            "#^\s*(\d{1,2})/([^\d\s]{1,3})/(\d{4})\s*$#iu", // 9/Jan/2021
            "#^\s*(\d{4})/(\d{1,2})月?/(\d{1,2})\s*$#iu", // 2021/2月/22
        ];
        $out = [
            "$2/$1/$3",
            "$1 $2 $3",
            "$3.$2.$1",
        ];

        $str = preg_replace($in, $out, $str);
//        $this->logger->debug('$date1 = '.print_r( $str,true));

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            } elseif ($en = MonthTranslate::translate($m[1], 'en')) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function arrikey($haystack, array $arrayNeedle)
    {
        foreach ($arrayNeedle as $key => $needles) {
            if (is_array($needles)) {
                foreach ($needles as $needle) {
                    if (stripos($haystack, $needle) !== false) {
                        return $key;
                    }
                }
            } else {
                if (stripos($haystack, $needles) !== false) {
                    return $key;
                }
            }
        }

        return false;
    }
}
