<?php

namespace AwardWallet\Engine\expedia\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class TravelConfirmationCruise extends \TAccountChecker
{
    public $mailFiles = "expedia/it-680935313.eml, expedia/it-682325830.eml";
    public $subjects = [
        'Expedia travel confirmation - ',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@eg.expedia.com') !== false) {
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
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'Expedia Group')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Cruise Details'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Cruise ship:'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Embarkation:'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]eg\.expedia\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $otaConf = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Itinerary #')]", null, true, "/[#]\s*(\d{10,})$/");

        if (!empty($otaConf)) {
            $email->ota()
                ->confirmation($otaConf);
        }

        $this->parseCruise($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function parseCruise(Email $email)
    {
        $c = $email->add()->cruise();

        $c->general()
            ->confirmation($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Cruise line itinerary number')]/following::text()[normalize-space()][1]", null, true, "/^([A-Z\d]+)$/"))
            ->travellers(preg_replace("/\, Citizen of.+/", "", $this->http->FindNodes("//text()[normalize-space()='Traveler details']/following::p[1]/descendant::text()[normalize-space()]")));

        $price = $this->http->FindSingleNode("//text()[normalize-space()='Total']/following::text()[normalize-space()][1]");

        if (preg_match("/^(?<currency>\D{1,3})\s*(?<total>[\d\.\,\']+)$/", $price, $m)) {
            $currency = $m['currency'];

            if ($this->http->XPath->query("//text()[{$this->contains('Rates are quoted in US Dollars')}]")->length > 0) {
                $currency = 'USA';
            }

            $c->price()
                ->total(PriceHelper::parse($m['total'], $currency))
                ->currency($currency);

            $cost = $this->http->FindSingleNode("//text()[normalize-space()='Cruise fare']/following::text()[normalize-space()][1]", null, true, "/^\D{1,3}([\d\.\,\']+)$/");

            if (!empty($cost)) {
                $c->price()
                    ->cost(PriceHelper::parse($cost, $currency));
            }

            $tax = $this->http->FindSingleNode("//text()[normalize-space()='Taxes & fees']/following::text()[normalize-space()][1]", null, true, "/^\D{1,3}([\d\.\,\']+)$/");

            if (!empty($tax)) {
                $c->price()
                    ->tax(PriceHelper::parse($tax, $currency));
            }
        }

        $c->setShip($this->http->FindSingleNode("//text()[contains(normalize-space(), 'Cruise ship:')]/following::text()[normalize-space()][1]"));
        $c->setClass($this->http->FindSingleNode("//text()[contains(normalize-space(), 'Cabin:')]/following::text()[normalize-space()][1]"));
        $c->setDeck($this->http->FindSingleNode("//text()[contains(normalize-space(), 'Cabin number:')]/following::text()[normalize-space()][1]"));

        $depDate = '';
        $arrDate = '';
        $dateText = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Travel dates:')]/following::text()[normalize-space()][1]");

        if (preg_match("/^(?<depName>.+\s\d{4})\s+\-\s+(?<arrName>.+\d{4})$/", $dateText, $m)) {
            $depDate = $m['depName'];
            $arrDate = $m['arrName'];
        }

        $cruisePoints = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Cruise from') and contains(normalize-space(), ' to ')]");

        if (empty($cruisePoints)) {
            $cruisePoints = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Cruise from') and contains(normalize-space(), 'Roundtrip')]");
        }

        $depTime = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Embarkation:')]/following::text()[normalize-space()][1]", null, true, "/^([\d\:]+\s*a?p?m?)$/");
        $arrTime = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Disembarkation:')]/following::text()[normalize-space()][1]", null, true, "/^([\d\:]+\s*a?p?m?)$/");

        if (preg_match("/from\s+(?<depName>.+)\s+to\s+(?<arrName>.+)/u", $cruisePoints, $m)) {
            $s = $c->addSegment();
            $s->setName($m['depName']);
            $s->setAboard(strtotime($depDate . ', ' . $depTime));

            $s = $c->addSegment();
            $s->setName($m['arrName']);
            $s->setAshore(strtotime($arrDate . ', ' . $arrTime));
        }

        if (preg_match("/from\s+(?<point>.+)\s+\(Roundtrip\)/", $cruisePoints, $m)) {
            $s = $c->addSegment();

            $s->setName($m['point']);
            $s->setAboard(strtotime($depDate . ' ' . str_replace("12:00am", '12:00', $depTime)));

            $s = $c->addSegment();

            $s->setName($m['point']);
            $s->setAshore(strtotime($arrDate . ' ' . str_replace("12:00am", '12:00', $arrTime)));
        }
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
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }
}
