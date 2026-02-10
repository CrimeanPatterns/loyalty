<?php

namespace AwardWallet\Engine\british\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class YourMembership extends \TAccountChecker
{
    public $mailFiles = "british/statements/it-63135542.eml, british/statements/it-73191632.eml";
    public $subjects = [
        '/^Your Executive Club membership$/',
        "/^You've collected \d[,.\'\d ]* Avios$/",
        "/^We've removed \d[,.\'\d ]* Avios$/",
        '/^Your \d[,.\'\d ]* Avios are on the way$/',
    ];
    public $lang = 'en';

    public static $dictionary = [
        'en' => [
            'Dear' => ['Dear', 'Hello'],
        ],
    ];

    private $http2; // for remote html-content

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $email->setType('YourMembership');

        $st = $email->add()->statement();

        // Name
        $name = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Dear'))}]", null, true, "/{$this->opt($this->t('Dear'))}\s+([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])(?:[ ]*[,:;!?]|$)/u");

        if ($name) {
            $st->addProperty('Name', $name);
        }

        // Number
        $number = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Your Executive'))} and {$this->contains($this->t('Club membership number is:'))}]", null, true, "/{$this->opt($this->t('Club membership number is:'))}\s*([-A-Z\d]{5,})(?:[ ]*[,.:;!?]|$)/");

        if ($number === null) {
            // it-73191632.eml
            $number = $this->http->FindSingleNode("//*[{$this->starts($this->t('This email is intended for'))} and {$this->contains($this->t('membership number'))}]", null, true, "/{$this->opt($this->t('membership number'))}\s*([-A-Z\d]{5,})(?:[ ]*[,.:;!?]|$)/");
        }

        if ($number !== null) {
            $st->setLogin($number)
                ->addProperty('Number', $number);
        }

        // Balance
        if ($name || $number !== null) {
            $st->setNoBalance(true);
        }

        $activateLink = $this->http->FindSingleNode("//a[normalize-space()='Activate your account' and normalize-space(@href)]/@href");

        if ($activateLink) {
            // it-63135542.eml
            $otc = $email->add()->oneTimeCode();
            $otc->setCodeAttr('/^https?:\/\/ba\.com\/[_A-z\d][^\s]+$/i', 3000);
            $otc->setCode($activateLink);
        }

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'British Airways Avios eStore') !== false
            || preg_match('/[@.]email\.ba\.com\b/', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) === true) {
            foreach ($this->subjects as $subject) {
                if (preg_match($subject, $headers['subject'])) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//a[contains(@href,".britishairways.com/") or contains(@href,"www.britishairways.com") or contains(@href,".ba.com/") or contains(@href,"//ba.com/") or contains(@href,"shopping.ba.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"Welcome to the British Airways Executive Club") or contains(normalize-space(),"Download the British Airways Executive Club Rewards App") or contains(normalize-space(),"Your Executive Club Team") or contains(.,"//ba.com/yourquestions")]')->length === 0
        ) {
            return false;
        }

        return $this->http->XPath->query("//h1[normalize-space()='Activate your account']"
                . "| //text()[starts-with(normalize-space(),'Thank you for recently shopping with')]")->length > 0
            && $this->http->XPath->query("//text()[starts-with(normalize-space(),'Your Executive') and contains(normalize-space(),'Club membership number is:')]"
                . " | //*[starts-with(normalize-space(),'This email is intended for') and contains(normalize-space(),'membership number')]")->length > 0;
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

        return implode(" or ", array_map(function ($s) {
            return "starts-with(normalize-space(.), \"{$s}\")";
        }, $field));
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
}
