<?php

namespace AwardWallet\Engine\delta\Email\Statement;

class SavedActivityPage extends \TAccountChecker
{
    public $mailFiles = "delta/statements/st-777.eml, delta/statements/it-67134607.eml";

    private $lang = '';
    private static $dict = [
        'en' => [
            'ACCOUNT ACTIVITY' => 'ACCOUNT ACTIVITY',
            'MILES'            => 'MILES',
        ],
        'de' => [
            'ACCOUNT ACTIVITY'        => 'KONTOBEWEGUNGEN',
            'MILES'                   => 'MEILEN',
            'TOTAL AVAILABLE MILES'   => 'VERFÜGBARE MEILEN GESAMT',
            'BONUS MILES'             => 'BONUSMEILEN',
            'Account Activity Details'=> 'Kontobewegungen',
            'MQSs'                    => 'MQS',
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        if (!$this->assignLang($this->http->Response['body'])) {
            $this->lang = 'en'; //default
        }
        $this->logger->debug('[LANG]: ' . $this->lang);
        $result = $this->ParseEmail($parser);

        return [
            'parsedData' => $result,
            'emailType'  => 'SavedActivityPage',
        ];
    }

    public function detectEmailFromProvider($from)
    {
        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query('//a[contains(., "MEDALLION QUALIFICATION")]')->length > 0
            || $this->http->XPath->query('//a[contains(., "MEDALLION-QUALIFIZIERUNGSSEGMENTE")]')->length > 0;
    }

    protected function ParseEmail(\PlancakeEmailParser $parser)
    {
        $props = [];
        $activity = [];
        $result = ['Properties' => &$props, 'Activity' => &$activity];

        $script = $this->http->FindSingleNode('//script[contains(., "var loginData")]', null, true, '/loginData\s*=\s*(\{[^}]+\})/');

        if (isset($script) && $json = json_decode($script)) {
            if (isset($json->smBalance)) {
                $props['Balance'] = $json->smBalance;
            }

            if (isset($json->smNumber)) {
                $props['Number'] = $props['Login'] = $json->smNumber;
            }
        }

        if (!isset($props['Balance'])) {
            $root = $this->http->XPath->query('//*[contains(normalize-space(text()), "' . $this->t('TOTAL AVAILABLE MILES') . '")]');
            $i = 0;

            while (!isset($props['Balance']) && $i < 5 && $root->length > 0) {
                $props['Balance'] = $this->http->FindSingleNode('.', $root->item(0), true, '/' . $this->t('TOTAL AVAILABLE MILES') . '\D*([\d\,\.]+)$/');
                $i++;
                $root = $this->http->XPath->query('parent::*', $root->item(0));
            }

            if (isset($props['Balance'])) {
                $props['Balance'] = str_replace(['.', ','], '', $props['Balance']);
            }
        }

        $cells = $this->http->XPath->query('//*[normalize-space(.) = "MQMs"]');
        $found = false;
        $xpath = '//*[normalize-space(.) = "MQMs"]';

        for ($j = 0; $j < min(10, $cells->length) && !$found; $j++) {
            $cell = $cells->item($j);
            $valid = false;
            $parent = null;
            $i = 0;

            while (!$valid && $i < 10) {
                if (isset($parent)) {
                    $cell = $parent;
                }
                $valid = $this->checkItem($cell);
                $parent = $this->http->XPath->query('parent::*', $cell)->item(0);
                $i++;
            }

            if ($valid) {
                $found = true;
                $xpath = $xpath . str_repeat('/parent::*', $i - 1);
            }
        }

        if (!$found) {
            $this->http->Log('history not found');

            return $result;
        }
        $items = $this->http->XPath->query($xpath);

        foreach ($items as $item) {
            /* @var \DOMNode $item */
            if (!$this->checkItem($item)) {
                continue;
            }
            $text = CleanXMLValue(implode(' ', $this->http->FindNodes('.//text()', $item)));

            if (preg_match('/' . $this->t('Account Activity Detail') . '\s*(?<m>\w{3,4})\.?\s*(?<d>\d{1,2})\s*(?<y>20\d{2})\s*(?<desc>.+)\s*MQMs\s*(?<mqm>--.+|[\d\,\.]+)\s*MQSs?\s*(?<mqs>--.+|[\d\,\.]+)\s*MQDs\s*(?<mqd>--.+|[\$\s\d\,\.]+)\s*BASE MILES\s*(?<base>--.+|[-\d\,\.]+)\s*' . $this->t('BONUS MILES') . '\s*(?<bonus>--.+|[-\d\,\.]+)\s*(?<total>\b0\b|[+-][\s\d\,\.]+)\s*(TOTAL MILES|MILES REDEEMED|GESAMTMEILEN)/iu',
                $text, $m)) {
                $date = $this->dateStringToEnglish(sprintf('%s %s %s', $m['d'], $m['m'], $m['y']));
                $activity[] = [
                    'Posting Date' => strtotime($date),
                    'Description'  => $m['desc'],
                    'Miles'        => stripos($m['base'], '--') === false ? trim($m['base']) : '--',
                    'Bonus Miles'  => stripos($m['bonus'], '--') === false ? trim($m['bonus']) : '--',
                    'Total Miles'  => str_replace(['+', ',', ' '], '', $m['total']),
                    'MQM Earned'   => stripos($m['mqm'], '--') === false ? trim($m['mqm']) : '--',
                    'MQD Earned'   => stripos($m['mqd'], '--') === false ? trim($m['mqd']) : '--',
                    'MQS Earned'   => stripos($m['mqs'], '--') === false ? trim($m['mqs']) : '--',
                ];
            }
        }

        return $result;
    }

    protected function checkItem(\DOMNode $cell)
    {
        $text = CleanXMLValue($cell->nodeValue);
        $found = true;
        $new[] = $text;

        foreach ([
            $this->t('Account Activity Detail') => 1,
            $this->t('MQMs') => 0,
            $this->t('MQSs') => 1,
            $this->t('MQDs') => 1,
            $this->t('BASE MILES') => 0,
            $this->t('BONUS MILES') => 0,
        ] as $needle => $num) {
            $found = $found && ($num === 1 && substr_count($text, $needle) === 1
                    || $num === 0 && substr_count($text, $needle) > 0);
        }
        $found = $found && (
            substr_count($text, 'TOTAL MILES') === 1
            || substr_count($text, 'Total Miles') === 1
            || substr_count($text, 'GESAMTMEILEN') === 1
            || substr_count($text, 'MILES REDEEMED') === 1);

        return $found;
    }

    private function assignLang($body)
    {
        foreach (self::$dict as $lang => $words) {
            if (isset($words["ACCOUNT ACTIVITY"], $words["MILES"])) {
                if (stripos($body, $words["ACCOUNT ACTIVITY"]) !== false && stripos($body, $words["MILES"]) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function dateStringToEnglish($date)
    {
        if (preg_match('/[[:alpha:]]+/iu', $date, $m)) {
            $monthNameOriginal = $m[0];
            //kostyl
            if (preg_match("/^m.r$/iu", $monthNameOriginal)) {
                return preg_replace("#$monthNameOriginal#i", 'MAR', $date);
            }

            if (preg_match("/^okt$/iu", $monthNameOriginal)) {
                return preg_replace("#$monthNameOriginal#i", 'OCT', $date);
            }

            if ($translatedMonthName = \AwardWallet\Engine\MonthTranslate::translate($monthNameOriginal, 'fr')) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }

            if ($translatedMonthName = \AwardWallet\Engine\MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }
}
