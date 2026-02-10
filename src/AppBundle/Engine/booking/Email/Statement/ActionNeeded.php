<?php

namespace AwardWallet\Engine\booking\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ActionNeeded extends \TAccountChecker
{
    public $mailFiles = "booking/statements/it-78470610.eml";
    public $subjects = [
        '/Your Reward \- Action needed \–\– Booking\.com/',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'Booking.com Rewards' => ['Referral programme', 'Booking.com Rewards', 'Cash reward'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@booking.com') !== false) {
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
        return $this->http->XPath->query("//text()[{$this->contains($this->t('Booking.com Rewards'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Action needed'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Status:'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]booking\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        if ($this->detectEmailByBody($parser) == true) {
            $st = $email->add()->statement();

            $name = $this->http->FindSingleNode("//text()[normalize-space()='Booking.com']/preceding::img[contains(@alt, 'Booking.com')]/following::a[normalize-space()][1]");

            if (!empty($name)) {
                $st->addProperty('Name', $name);
            }

            $st->setNoBalance(true);
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

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }
}
