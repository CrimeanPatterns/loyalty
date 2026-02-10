<?php

namespace AwardWallet\Engine\disneyresort\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ThemeParkJunk extends \TAccountChecker
{
    public $mailFiles = "disneyresort/it-667583913.eml";
    public $subjects = [
        'Disneyland Theme Park Reservation Confirmation',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'View My Theme Park Reservations' => ['View My Theme Park Reservations', 'View My Park Reservations'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@disneyonline.com') !== false) {
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
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'Your Theme Park Reservation is Confirmed!')]")->length > 0
            && $this->http->XPath->query("//a[{$this->contains($this->t('View My Theme Park Reservations'))}]")->length > 0
            && $this->http->XPath->query("//a[contains(@href, 'disney')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Park Reservation Date'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Selected Park'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Your Party'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]disneyonline\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        if ($this->detectEmailByBody($parser) === true) {
            $email->setIsJunk(true);
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
        return count(self::$dictionary);
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
