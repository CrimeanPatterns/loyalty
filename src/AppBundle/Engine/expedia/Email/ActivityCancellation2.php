<?php

namespace AwardWallet\Engine\expedia\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ActivityCancellation2 extends \TAccountChecker
{
    public $mailFiles = "expedia/it-649773148.eml";
    public $subjects = [
        '/Activity Booking Canceled/',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'TAAP Itinerary number:' => ['TAAP Itinerary number:', 'Expedia Itinerary number:'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@expediataap.com') !== false) {
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
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'Expedia')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('TAAP Itinerary number:'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Reserved for'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]expediataap\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $e = $email->add()->event();

        $confirmation = $this->http->FindSingleNode("//text()[{$this->starts($this->t('TAAP Itinerary number:'))}]", null, true, "/{$this->opt($this->t('TAAP Itinerary number:'))}\s*(\d{10,})/");
        $e->general()
            ->confirmation($confirmation)
            ->traveller($this->http->FindSingleNode("//text()[normalize-space()='Reserved for']/ancestor::tr[1]/descendant::td[2]"));

        if ($this->http->XPath->query("//text()[normalize-space()='Your reservation has been canceled']")->length > 0) {
            $e->general()
                ->cancelled()
                ->cancellationNumber($confirmation);
        }

        $e->setEventType(EVENT_EVENT);

        $e->setName($this->http->FindSingleNode("//text()[normalize-space()='Your reservation has been canceled']/following::text()[contains(normalize-space(), ',')][1]/following::text()[normalize-space()][1]"));
        $e->setStartDate(strtotime($this->http->FindSingleNode("//text()[normalize-space()='Your reservation has been canceled']/following::text()[contains(normalize-space(), ',')][1]")));

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
