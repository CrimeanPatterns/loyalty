<?php

namespace AwardWallet\Engine\aeroplan\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class FlightPassJunk extends \TAccountChecker
{
    public $mailFiles = "aeroplan/it-522651778.eml";
    public $subjects = [
        'Air Canada - Electronic Ticket Itinerary/Receipt',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@aircanada.ca') !== false) {
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
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'Thank you for choosing Air Canada')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Flight Pass'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('View my flight pass'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Booking Information'))}]")->length == 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Flight Itinerary'))}]")->length == 0
            ;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]aircanada\.ca$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        if ($this->detectEmailByBody($parser) == true) {
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
