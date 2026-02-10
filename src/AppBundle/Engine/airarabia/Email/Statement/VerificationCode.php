<?php

namespace AwardWallet\Engine\airarabia\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class VerificationCode extends \TAccountChecker
{
    public $mailFiles = "airarabia/statements/it-718362605.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'verificationCodeIs' => ['verification code is'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        return array_key_exists('subject', $headers) && stripos($headers['subject'], 'AirRewards Verification Code') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,".airarabia.com/") or contains(@href,"www.airarabia.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"Sincerely, Air Arabia")]')->length === 0
        ) {
            return false;
        }

        return $this->assignLang();
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'reservation@airarabia.com') !== false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        $verificationCode = $this->http->FindSingleNode("//text()[{$this->contains($this->t('verificationCodeIs'))}]/following::text()[normalize-space()][1]", null, true, "/^\d{3,}$/");

        if ($verificationCode !== null) {
            $code = $email->add()->oneTimeCode();
            $code->setCode($verificationCode);
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

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['verificationCodeIs'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[{$this->contains($phrases['verificationCodeIs'])}]")->length > 0) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function t(string $phrase, string $lang = '')
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return $phrase;
        }

        if ($lang === '') {
            $lang = $this->lang;
        }

        if (empty(self::$dictionary[$lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$lang][$phrase];
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
}
