<?php

namespace AwardWallet\Engine\butler\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ReservationConfirmation extends \TAccountChecker
{
    public $mailFiles = "butler/it-640353187.eml";
    public $subjects = [
        '/^\s*Reservation Confirmation\s*\d+\s*for Your Trip at\D*/',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@bjcvip.com') !== false) {
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
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'Airport Butler service')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('RESERVATION DETAILS:'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('PRIMARY TRAVELER DETAILS:'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]bjcvip\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $e = $email->add()->event();

        $e->setEventType(EVENT_EVENT);

        $e->general()
            ->travellers($this->http->FindNodes("//text()[contains(normalize-space(), 'Age:')]/preceding::text()[normalize-space()][not(contains(normalize-space(), '-') or contains(normalize-space(), '@'))][1]"))
            ->confirmation($this->http->FindSingleNode("//text()[normalize-space()='Reservation Number:']/following::text()[normalize-space()][1]", null, true, "/^(\d+)$/"));

        $e->setName('Your Airport Concierge');
        $e->setAddress($this->re("/for Your Trip at\s*(.+)/", $parser->getSubject()));
        $e->setGuestCount($this->http->FindSingleNode("//text()[normalize-space()='Number of Passengers:']/following::text()[normalize-space()][1]", null, true, "/^(\d+)$/"));

        $e->setStartDate(strtotime($this->http->FindSingleNode("//text()[normalize-space()='Flight Time:']/following::text()[normalize-space()][1]")));
        $e->setNoEndDate(true);

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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }
}
