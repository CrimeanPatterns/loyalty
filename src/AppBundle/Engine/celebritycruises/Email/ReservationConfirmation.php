<?php

namespace AwardWallet\Engine\celebritycruises\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ReservationConfirmation extends \TAccountChecker
{
    public $mailFiles = "celebritycruises/it-77232035.eml";
    public $subjects = [
        '/^Your Celebrity Cruises Reservation Confirmation$/',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@email.celebritycruises.com') !== false) {
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
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'Celebrity Cruises')]")->count() > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('CUSTOMIZE YOUR CRUISE'))}]")->count() > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('BEFORE YOU CRUISE'))}]")->count() > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]email\.celebritycruises\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $cr = $email->add()->cruise();

        $cr->general()
            ->confirmation($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'BOOKING NUMBER:')]/following::text()[normalize-space()][1]"), 'BOOKING NUMBER')
            ->traveller($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Hello')]", null, true, "/{$this->opt($this->t('Hello'))}\s*(\D+)$/"), false);

        $cr->setShip($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'SHIP:')]/following::text()[normalize-space()][1]"));

        $s = $cr->addSegment();

        $s->setName($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'DEPARTING FROM:')]/following::text()[normalize-space()][1]"));
        $s->setAboard(strtotime($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'SAIL DATE:')]/following::text()[normalize-space()][1]")));

        $s = $cr->addSegment();

        $s->setName($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'DEPARTING FROM:')]/following::text()[normalize-space()][1]"));
        $s->setAshore(strtotime($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'RETURN DATE:')]/following::text()[normalize-space()][1]")));

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
