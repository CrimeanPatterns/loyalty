<?php

namespace AwardWallet\Engine\chase\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class CruiseJunk extends \TAccountChecker
{
    public $mailFiles = "chase/it-542207134.eml";
    public $subjects = [
        'Travel Reservation Center Trip ID #',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'INVOICE DETAILS' => ['INVOICE DETAILS', 'Fare Break-down for the full itinerary:'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@urtravel.chase.com') !== false) {
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
        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'Chase Privacy Operations')]")->length > 0
        || $this->http->XPath->query("//text()[contains(normalize-space(), 'JPMorgan Chase & Co')]")->length > 0) {
            return $this->http->XPath->query("//text()[{$this->contains($this->t('Cruise'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('INVOICE DETAILS'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('ITINERARY INFORMATION:'))}]")->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]urtravel\.chase\.com$/', $from) > 0;
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
