<?php

namespace AwardWallet\Engine\amexserve\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class Membership extends \TAccountChecker
{
    public $mailFiles = "amexserve/statements/it-108248400.eml";

    public $lang = 'en';
    public static $dictionary = [
        'en' => [],
    ];

    private $detectFrom = '@app.serve.com';
    private $detectSubjectWithProviderName = [
        // en
        'You activated your American Express Serve Card',
    ];

    private $detectBody = [
        'en' => [
            "We're just confirming that you activated your American Express Serve® Card",
            'just set up a Serve Subaccount for you.',
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->detectSubjectWithProviderName as $dSubject) {
            if (stripos($headers["subject"], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[{$this->contains(['www.americanexpress.com/serve/'], '@href')}]")->length === 0) {
            return false;
        }

        return $this->isMembership();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        $name = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Hi,'))}]", null, true, "/{$this->opt($this->t('Hi,'))}\s*([[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])(?:\s*[,;:!?]|$)/u");

        if (!empty($name)) {
            $st->addProperty('Name', $name);
        }

        if ($name) {
            $st->setNoBalance(true);
        } elseif ($this->isMembership() === true) {
            $st->setMembership(true);
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

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

    private function isMembership(): bool
    {
        foreach ($this->detectBody as $detectBody) {
            if ($this->http->XPath->query("//*[{$this->contains($detectBody)}]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    private function t($s)
    {
        if (!isset($this->lang, self::$dictionary[$this->lang], self::$dictionary[$this->lang][$s])) {
            return $s;
        }

        return self::$dictionary[$this->lang][$s];
    }

    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) {
            return 'contains(' . $text . ',"' . $s . '")';
        }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function opt($field, $delimiter = '/')
    {
        $field = (array) $field;

        if (empty($field)) {
            $field = ['false'];
        }

        return '(?:' . implode("|", array_map(function ($s) use ($delimiter) {
            return str_replace(' ', '\s+', preg_quote($s, $delimiter));
        }, $field)) . ')';
    }
}
