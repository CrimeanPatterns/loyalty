<?php

namespace AwardWallet\Engine\extrabux\Email\Statement;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class MonthlyBalance extends \TAccountChecker
{
    public $mailFiles = "extrabux/statements/st-71080251.eml, extrabux/statements/it-120668973.eml, extrabux/statements/it-120669251.eml";

    public $detectBody = [
        'en' => [
            'Your Monthly Balance Statement',
        ],
    ];

    public $lang = 'en';
    public static $dictionary = [
        "en" => [],
    ];

    private $detectFrom = ['deals@edm.extrabux.net'];

    private $detectSubject = [
        'Extrabux - Your Monthly Balance Statement',
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        $balanceText = $this->htmlToText($this->http->FindHTMLByXpath("//*[ tr[2][{$this->eq($this->t('Available Earnings'))}] ]/tr[1]"));

        if (preg_match('/^[ ]*(?<currency>USD)[ ]*(?<amount>\d[,.\'\d ]*?)[ ]*$/m', $balanceText, $matches)
            || preg_match('/^[ ]*(?<currency>[A-Z]{3})[ ]*(?<amount>\d[,.\'\d ]*?)[ ]*(?:\n|$)/', $balanceText, $matches)
        ) {
            // USD 530.43
            $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
            $st->setBalance(PriceHelper::parse($matches['amount'], $currencyCode));
        }

        $name = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Hi '))}]", null,
            false, "/^{$this->opt($this->t('Hi '))}\s*([[:alpha:]\. \-]+)[,]$/u");

        if (empty($name)) {
            // examples: stev_jang, christopherbullock39553
            $name = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Hi '))}]", null,
                false, "/^{$this->opt($this->t('Hi '))}\s*(.+)[,]$/u");
        }

        if (empty($name) && $this->http->FindSingleNode("//text()[{$this->eq($this->t('Monthly Desc Email hi'))}]") !== null) {
        } else {
            $st->addProperty('Name', $name);
        }

        $email->setType('MonthlyBalance' . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return $this->striposAll($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers['subject'], $dSubject) !== false
                && (stripos($headers['subject'], 'Extrabux') !== false || $this->striposAll($headers['from'], $this->detectFrom) !== false)) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,"www.extrabux.com")]')->length === 0) {
            return false;
        }

        foreach ($this->detectBody as $detectBody) {
            if ($this->http->XPath->query('//*[' . $this->contains($detectBody) . ']')->length > 0) {
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
        return 0;
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) {
            return "starts-with(normalize-space(.), '{$s}')";
        }, $field)) . ')';
    }

    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) use ($text) {
            return "contains(" . $text . ", '{$s}')";
        }, $field)) . ')';
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) {
            return "normalize-space(.)='{$s}'";
        }, $field)) . ')';
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function opt($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
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

    private function htmlToText(?string $s, bool $brConvert = true): string
    {
        if (!is_string($s) || $s === '') {
            return '';
        }
        $s = str_replace("\r", '', $s);
        $s = preg_replace('/<!--.*?-->/s', '', $s); // comments

        if ($brConvert) {
            $s = preg_replace('/\s+/', ' ', $s);
            $s = preg_replace('/<[Bb][Rr]\b.*?\/?>/', "\n", $s); // only <br> tags
        }
        $s = preg_replace('/<[A-z][A-z\d:]*\b.*?\/?>/', '', $s); // opening tags
        $s = preg_replace('/<\/[A-z][A-z\d:]*\b[ ]*>/', '', $s); // closing tags
        $s = html_entity_decode($s);
        $s = str_replace(chr(194) . chr(160), ' ', $s); // NBSP to SPACE

        return trim($s);
    }
}
