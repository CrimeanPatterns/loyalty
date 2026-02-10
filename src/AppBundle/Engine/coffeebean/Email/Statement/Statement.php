<?php

namespace AwardWallet\Engine\coffeebean\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Statement extends \TAccountChecker
{
    public $mailFiles = "coffeebean/statements/it-82141080.eml, coffeebean/statements/it-82707808.eml";
    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'detectBody' => [
                'Valid with the Purchase of any Regular or Larger Sized',
                'Gift Card for yourself',
                'Offer valid only at',
            ],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        return
            $this->http->XPath->query("//text()[contains(normalize-space(), 'The Coffee Bean')]")->length > 0
            && (
                $this->http->XPath->query("//img[contains(@alt, 'The Coffee Bean')]")->length > 0
                || $this->http->XPath->query("//text()[{$this->contains($this->t('detectBody'))}]")->length > 0
                || $this->http->XPath->query("//img[contains(@src, 'Email_Rewards_Footer_Icons_Location') or contains(@alt, 'location')]")->length > 0
            )
        ;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]coffeebean.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        if ($this->detectEmailByBody($parser) == true) {
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
