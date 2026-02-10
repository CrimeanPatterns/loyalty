<?php

namespace AwardWallet\Engine\etihad\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class MonthStatement extends \TAccountChecker
{
    public $mailFiles = "etihad/statements/it-63689391.eml";
    public $reFrom = '@choose.etihadguest.com';
    public $reSubject = '/^\w+\,\s+here\’s your Etihad Guest \w+ e\-Statement$/';
    public $lang = 'en';
    private static $dictionary = [
        'en' => [
            'As of'                          => ['As of', 'as of'],
            'View account details'           => ['View account details', 'View Account Details'],
            'Your monthly mileage statement' => [
                'Your monthly mileage statement',
                'Your Monthly Mileage Statement',
            ],
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        $name = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Guest No:'))}]/ancestor::tr[1]/preceding-sibling::tr[string-length(normalize-space())>=2][1]");

        $st->addProperty('Name', $name);

        $number = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Guest No:'))}]", null, true, "/^{$this->opt($this->t('Guest No:'))}\s*(\d+)$/");
        $st->setNumber($number)
            ->setLogin($number);

        $balance = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Guest Miles Balance:'))}]/ancestor::td[1]", null, true, "/{$this->opt($this->t('Guest Miles Balance:'))}\s*(\d+)/");
        $st->setBalance($balance);

        $tierMiles = $this->http->FindSingleNode("//a[{$this->starts($this->t('View account details'))}]/following::text()[{$this->starts($this->t('Tier miles'))}][1]/following::text()[normalize-space()][1]", null, true, "/^(\d+)$/");

        if (!empty($tierMiles)) {
            $st->addProperty('TierMiles', $tierMiles);
        }

        $tierSegments = $this->http->FindSingleNode("//a[{$this->starts($this->t('View account details'))}]/following::text()[{$this->starts($this->t('Tier segments'))}][1]/following::text()[normalize-space()][1]", null, true, "/^(\d+)$/");

        if (!empty($tierSegments)) {
            $st->addProperty('TierSegments', $tierSegments);
        }

        $dateOfBalance = $this->http->FindSingleNode("//text()[{$this->starts($this->t('As of'))}]/ancestor::td[1]", null, true, "/{$this->opt($this->t('As of'))}\s*(\d+\s+\w+\s+\d{4})/");

        if (!empty($dateOfBalance)) {
            $st->setBalanceDate(strtotime($dateOfBalance));
        }
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]choose\.etihadguest\.com$/', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], $this->reFrom) !== false) {
            if (preg_match($this->reSubject, $headers['subject'])) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query("//text()[{$this->contains($this->t('Etihad Guest'))}]")->count() > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Your monthly mileage statement'))}]")->count() > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Guest No:'))} ]")->count() > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Guest Miles Balance:'))}]")->count() > 0;
    }

    public static function getEmailLanguages()
    {
        return ['en'];
    }

    public static function getEmailTypesCount()
    {
        return 0;
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

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ")='" . $s . "'";
        }, $field)) . ')';
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
