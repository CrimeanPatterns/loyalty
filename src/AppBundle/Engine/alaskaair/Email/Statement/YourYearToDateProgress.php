<?php

namespace AwardWallet\Engine\alaskaair\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class YourYearToDateProgress extends \TAccountChecker
{
    public $mailFiles = "alaskaair/statements/it-580929576.eml, alaskaair/statements/it-582957420.eml, alaskaair/statements/it-656603510.eml";
    public $detectFrom = 'mileage.plan@ifly.alaskaair.com';

    public $detectSubject = [
        'Statement!',
        'Statement just landed!',
    ];
    public $detectBody = [
        'en' => [
            'Your year-to-date progress towards elite status',
            'Your progress towards elite status in 2025:',
            'Your year-to-date progress towards elite status:',
        ],
    ];
    public $lang = '';
    public static $dictionary = [
        'en' => [
            'Your progress towards elite status in 2025:' => ['Your progress towards elite status in 2025:', 'Your year-to-date progress towards elite status:'],
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        $st = $email->add()->statement();

        $date = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Summary as of'))}]/ancestor::*[not({$this->eq($this->t('Summary as of'))})][1]",
            null, true, "/{$this->opt($this->t('Summary as of'))}\s*(\S.+)/");
        $st->setBalanceDate(strtotime($date));

        $name = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Mileage Plan Number:'))}]/preceding::text()[normalize-space()][1]",
            null, true, "/^\s*\D{2,}\s*$/");
        $st->addProperty('Name', $name);

        $number = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Mileage Plan Number:'))}]/ancestor::*[not({$this->eq($this->t('Mileage Plan Number:'))})][1]",
            null, true, "/{$this->opt($this->t('Mileage Plan Number:'))}\s*(.+?)\s*$/");
        $this->logger->debug('$number = ' . print_r($number, true));

        if (preg_match("/^x{2,}(\d{3,})$/", $number, $m)) {
            $st
                ->setLogin($m[1])
                ->masked();
            $st
                ->setNumber($m[1])
                ->masked();
        } else {
            $st->setNumber(null);
        }

        $balance = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Miles balance:'))}]/ancestor::*[not({$this->eq($this->t('Miles balance:'))})][1]",
            null, true, "/^\s*{$this->opt($this->t('Miles balance:'))}\s*(\d[\d,]*)\s*$/");
        $st->setBalance(str_replace(',', '', $balance));

        $eMiles = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Elite-qualifying miles:'))}]/ancestor::*[not({$this->eq($this->t('Elite-qualifying miles:'))})][1]",
            null, true, "/^\s*{$this->opt($this->t('Elite-qualifying miles:'))}\s*(\d[\d,]*)\s*$/");
        $eSegs = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Elite-qualifying segments:'))}]/ancestor::*[not({$this->eq($this->t('Elite-qualifying segments:'))})][1]",
            null, true, "/^\s*{$this->opt($this->t('Elite-qualifying segments:'))}\s*(\d[\d,]*)\s*$/");

        if (empty($this->http->FindSingleNode("(//*[{$this->eq($this->t('Your progress towards elite status in 2025:'))}])[1]"))) {
            $st->addProperty('Miles', $eMiles);
            $st->addProperty('Segments', $eSegs);
        } else {
            $eMiles = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Elite-qualifying miles (EQMs):'))}]/ancestor::*[not({$this->eq($this->t('Elite-qualifying miles (EQMs):'))})][1]",
                null, true, "/^\s*{$this->opt($this->t('Elite-qualifying miles (EQMs):'))}\s*(\d[\d,]*)\s*$/");
            $st->addProperty('Miles', $eMiles);
        }

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@\.]alaskaair\.com$/", $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href, '.alaskaair.com')]")->length === 0) {
            return false;
        }

        if ($this->assignLang()) {
            return true;
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function assignLang()
    {
        foreach ($this->detectBody as $lang => $dBody) {
            if ($this->http->XPath->query("//node()[{$this->eq($dBody)}]")->length > 0) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
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

    private function starts($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'starts-with(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ')="' . $s . '"';
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

    private function containsText($text, $needle): bool
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
