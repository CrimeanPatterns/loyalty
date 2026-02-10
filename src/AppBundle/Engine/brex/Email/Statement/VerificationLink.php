<?php

namespace AwardWallet\Engine\brex\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class VerificationLink extends \TAccountChecker
{
    public $mailFiles = "brex/statements/it-107643677.eml, brex/statements/it-176118858.eml";
    public $subjects = [
        'New sign-in to Brex',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'sign in to Brex from'       => ['sign in to Brex from', 'sign-in detected from your Brex account'],
            'Your security verification' => ['Your security verification', 'The security of your account'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@brex.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (stripos($headers['subject'], $subject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[{$this->contains($this->t('sign in to Brex from'))}]")->length > 0) {
            return $this->http->XPath->query("//text()[{$this->contains($this->t('Your security verification'))}]")->length > 0
                && $this->http->XPath->query("//text()[normalize-space()='Trust this device:']")->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        if (stripos($from, '/[@.]brex\.com$/') !== false) {
            return true;
        }

        return false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        $code = $this->http->FindSingleNode("//text()[normalize-space()='Trust this device:']/following::text()[normalize-space()][1]");

        /*Trust this device:
        https://accounts.brex.com/login/email-link/e834757aaea54adcabbf4e3ca4179c3c/6abdb4e4-f7b5-11eb-8030-82dc50e5d8a3?remember=true*/

        if (!empty($code)) {
            $otc = $email->add()->oneTimeCode();
            $otc->setCodeAttr("/https:\/\/accounts\.brex\.com\/login\/email\-link\/[A-z\d\.]+\/[a-z\-\d]+[?]remember[=]true(?:\&[a-z]{4,5}\=[a-z]{4,5}\_[a-z\d]+)?$/ui", 3000);
            $otc->setCode($code);
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

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }
}
