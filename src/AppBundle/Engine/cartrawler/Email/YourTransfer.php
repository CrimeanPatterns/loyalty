<?php

namespace AwardWallet\Engine\cartrawler\Email;

use AwardWallet\Schema\Parser\Email\Email;

class YourTransfer extends \TAccountChecker
{
    public $mailFiles = "cartrawler/it-42578676.eml, cartrawler/it-45178669.eml";

    public static $detectProvider = [
        'skywards' => [
            'from' => 'Cartrawler, in association with Emirates',
            //            'bodyLink' => [''],
            'body'       => ['Thank you for booking your transfer with Cartrawler, in association with Emirates'],
            'imgLogoAlt' => 'emirates',
        ],
        'ryanair' => [
            'from' => '.ryanair@cartrawler.com',
            //            'bodyLink' => [''],
            //            'body' => [''],
            'imgLogoAlt' => 'ryanair',
        ],

        'cartrawler' => [ // must be last in providers
            'from'     => '@cartrawler.com',
            'bodyLink' => ['.cartrawler.com'],
            //            'body' => [''],
            //            'imgLogoAlt' => '',
        ],
        // other companies, not provider
        [
            'from' => 'Cabforce',
            //            'bodyLink' => [''],
            'body'       => ['Thank you for booking your transfer with Cabforce'],
            'imgLogoAlt' => 'cabforce',
        ],
    ];

    public $reFrom = ["@cartrawler.com"];
    public $detectSubjects = [
        'en' => ['Booking Confirmation For Your Transfer'],
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
            // 'Dear' => '',
            'Your booking reference number is' => ['Your booking reference number is', 'Your Booking Reference Number -'],
            'Pick-up location:'                => ['Pick-up location:'],
            'Pick-up time:'                    => ['Pick-up time:', 'pick-up time:'],
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (empty($parser->getHTMLBody())) {
            $this->http->SetEmailBody($parser->getPlainBody());
        }

        // Detecting Provider
        foreach (self::$detectProvider as $code => $params) {
            if (!empty($params['body']) && $this->http->XPath->query('//*[' . $this->contains($params['body']) . ']')->length > 0) {
                $providerCode = $code;

                break;
            }

            if (!empty($params['bodyLink']) && $this->http->XPath->query('//*[' . $this->contains($params['bodyLink'], '@href') . ']')->length > 0) {
                $providerCode = $code;

                break;
            }

            if (!empty($params['from']) && $this->striposAll($parser->getCleanFrom(), $params['from']) !== false) {
                $providerCode = $code;

                break;
            }

            if (!empty($params['imgLogoAlt']) && $this->http->XPath->query('//img[' . $this->eq($params['imgLogoAlt'], '@alt') . ']')->length > 0) {
                $providerCode = $code;

                break;
            }
        }

        if (is_numeric($providerCode)) {
            $providerCode = null;
        }

        if (!empty($providerCode)) {
            $email->setProviderCode($providerCode);
        }

        // Detecting Language
        $this->assignLang();

        $this->parseEmail($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,".cartrawler.com")]')->length > 0
            || $this->http->XPath->query('//node()[contains(.,"@cartrawler.com")]')->length > 0
        ) {
            return $this->assignLang();
        }

        foreach (self::$detectProvider as $code => $params) {
            if (!empty($params['bodyLink']) && $this->http->XPath->query('//*[' . $this->contains($params['bodyLink'],
                        '@href') . ']')->length === 0) {
                return $this->assignLang();
            }

            if (!empty($params['body']) && $this->http->XPath->query('//*[' . $this->contains($params['body']) . ']')->length === 0) {
                return $this->assignLang();
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers['from']) || empty($headers["subject"])) {
            return false;
        }

        foreach (self::$detectProvider as $code => $params) {
            if (!empty($params['from']) && $this->striposAll($headers['from'], $params['from']) === false) {
                continue;
            }

            foreach ($this->detectSubjects as $dSubjects) {
                if ($this->striposAll($headers['subject'], $dSubjects) !== false) {
                    return true;
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

    public static function getEmailProviders()
    {
        return array_filter(array_keys(self::$detectProvider), function ($v) {
            return (is_numeric($v)) ? false : true;
        });
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

    private function parseEmail(Email $email)
    {
        $t = $email->add()->transfer();

        // General
        $t->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->starts($this->t('Your booking reference number is'))}]",
                null, true, "#{$this->opt($this->t('Your booking reference number is'))}[\s:]+([\w\-]{5,})#"))
            ->traveller($this->http->FindSingleNode("//text()[{$this->starts($this->t('Dear'))}]",
                null, true, "#{$this->opt($this->t('Dear'))}\s+(.+?)(?:,|\.|$)#"))
        ;

        $tot = $this->getTotalCurrency($this->http->FindSingleNode("//text()[{$this->starts($this->t('Your payment card has been charged for'))}]",
            null, true, "#{$this->opt($this->t('Your payment card has been charged for'))}[\s:]+(.+?)[,.;!?]*$#"));

        if ($tot['Total'] !== '') {
            $t->price()
                ->total($tot['Total'])
                ->currency($tot['Currency'])
            ;
        }
        $xpath = "//text()[{$this->eq($this->t('Pick-up location:'))}]/ancestor::table[{$this->contains($this->t('Drop-off location:'))}][1]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $s = $t->addSegment();

            $node = $this->http->FindSingleNode("./descendant::text()[{$this->contains($this->t('Pick-up location:'))}]/ancestor::td[1]/following-sibling::td[normalize-space(.)!=''][1]",
                $root);

            if (preg_match("#(.+?)\s*(?:\(([A-Z]{3})\)|$)#", $node, $m)) {
                $s->departure()
                    ->name($m[1]);

                if (!empty($m[2])) {
                    $s->departure()
                        ->code($m[2]);
                }
            }
            $s->departure()
                ->date($this->normalizeDate($this->http->FindSingleNode("./descendant::text()[{$this->contains($this->t('Pick-up time:'))}]/ancestor::td[1]/following-sibling::td[normalize-space(.)!=''][1]",
                $root)));
            //in fact it does not have ArrDate. it's just a guess
            $arrDate = $this->normalizeDate($this->http->FindSingleNode("./descendant::text()[{$this->contains($this->t('Drop-off time:'))}]/ancestor::td[1]/following-sibling::td[normalize-space(.)!=''][1]",
                $root));

            if (!empty($arrDate)) {
                $s->arrival()
                    ->date($arrDate);
            } else {
                $s->arrival()->noDate();
            }

            $node = $this->http->FindSingleNode("./descendant::text()[{$this->contains($this->t('Drop-off location:'))}]/ancestor::td[1]/following-sibling::td[normalize-space(.)!=''][1]",
                $root);

            if (preg_match("#(.+?)\s*(?:\(([A-Z]{3})\)|$)#", $node, $m)) {
                $s->arrival()
                    ->name($m[1]);

                if (!empty($m[2])) {
                    $s->arrival()
                        ->code($m[2]);
                }
            }
        }

        return $email;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dict, $this->lang)) {
            return false;
        }

        foreach (self::$dict as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['Pick-up location:']) || empty($phrases['Pick-up time:'])) {
                continue;
            }

            if ($this->http->XPath->query("//node()[{$this->contains($phrases['Pick-up location:'])}]")->length > 0
                && $this->http->XPath->query("//node()[{$this->contains($phrases['Pick-up time:'])}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
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
            $m['t'] = preg_replace('/,(\d{1,2})$/', '.$1', $m['t']);    // 18800,00		->	18800.00
            $tot = $m['t'];
            $cur = $m['c'];
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function normalizeDate($date)
    {
        // $this->logger->debug('$date = '.print_r( $date,true));
        $in = [
            //21:35 Wednesday, April 18, 2018
            '#^\s*(\d+:\d+)\s+\w+,\s+(\w+)\s+(\d+),\s+(\d{4})\s*$#u',
            // 11:10 2023 Sep 2, Sat
            '#^\s*(\d+:\d+)\s+(\d{4})\s+(\w+)\s+(\d{1,2})\s*,\s*\w+\s*$#u',
        ];
        $out = [
            '$3 $2 $4, $1',
            '$4 $3 $2, $1',
        ];
        $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));
        // $this->logger->debug('$date = '.print_r( $date,true));

        return strtotime(trim($str));
    }

    private function striposAll($text, $needle): bool
    {
        if (empty($text) || empty($needle)) {
            return false;
        }

        if (is_array($needle)) {
            foreach ($needle as $n) {
                if (stripos($text, $n) !== false) {
                    return true;
                }
            }
        } elseif (is_string($needle) && stripos($text, $needle) !== false) {
            return true;
        }

        return false;
    }
}
