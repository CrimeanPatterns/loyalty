<?php

namespace AwardWallet\Engine\disneycruise\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Booked extends \TAccountChecker
{
    public $mailFiles = "disneycruise/it-382805570.eml";
    public $subjects = [
        '/Bibbidi\, bobbidi\, booked\!/',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'Total Due' => ['Total Due', 'Grand Total'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@familyvacations-disneycruise.com') !== false) {
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
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'Disney')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Cruise Details'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Cruise Itinerary'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]familyvacations\-disneycruise\.com/', $from) > 0;
    }

    public function parseCruise(Email $email)
    {
        $c = $email->add()->cruise();

        $c->general()
            ->confirmation($this->http->FindSingleNode("//text()[normalize-space()='Reservation #']/ancestor::tr[1]/descendant::td[2]", null, true, "/^([A-Z\d]{8})$/"));
        $travellers = $this->http->FindNodes("//img[contains(@src, 'guest_information')]/ancestor::tr[1]");

        if (empty($travellers)) {
            $travellers = $this->http->FindNodes("//text()[normalize-space() = 'Citizenship']/preceding::text()[normalize-space()][1]/ancestor::h4[1]");
        }
        $c->general()
            ->travellers(preg_replace("/^(?:MRS|MS|MR|MSTR|MISS)\s+/", "", $travellers), true);

        $accounts = $this->http->FindNodes("//text()[normalize-space()='Castaway Club Number and Level']/ancestor::tr[1]/descendant::td[2]", null, "/^(\d+)/");

        if (count($accounts) > 0) {
            $c->setAccountNumbers($accounts, false);
        }

        $c->setDescription($this->http->FindSingleNode("//text()[normalize-space()='Reservation #']/following::tr[normalize-space()][position() < 3][td[1][normalize-space()='Cruise Itinerary']]/td[2]"));
        $c->setShip($this->http->FindSingleNode("//text()[normalize-space()='Ship']/ancestor::tr[1]/descendant::td[2]"));
        $deck = $this->http->FindSingleNode("//text()[normalize-space()='Deck']/ancestor::tr[1]/descendant::td[2]");

        if (!empty($deck)) {
            $c->setDeck($deck);
        }
        $c->setRoom($this->http->FindSingleNode("//text()[normalize-space()='Stateroom']/ancestor::tr[1]/descendant::td[2]"));
        $c->setClass($this->http->FindSingleNode("//text()[normalize-space()='Category']/ancestor::tr[1]/descendant::td[2]"));

        $currency = '';

        if ($this->http->XPath->query("//text()[normalize-space()='Currency']/ancestor::tr[1]/descendant::td[2][contains(normalize-space(), 'US Dollars')]")->length > 0) {
            $currency = 'USD';
        }

        $price = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Total Due'))}]/ancestor::tr[1]/descendant::td[2]");

        if (preg_match("/^(?<currency>\D{1,5})\s*(?<total>[\d\.\,]+)$/u", $price, $m)) {
            if (!empty($currency)) {
                $currency = $this->normalizeCurrency($currency);
                $c->price()
                    ->total(PriceHelper::parse($m['total'], $currency))
                    ->currency($currency);
            } else {
                $currency = $this->normalizeCurrency($m['currency']);
                $c->price()
                    ->total(PriceHelper::parse($m['total'], $currency))
                    ->currency($currency);
            }

            $tax = $this->http->FindSingleNode("//text()[normalize-space()='Taxes, Fees, and Port Expenses']/ancestor::tr[1]/descendant::td[2]", null, true, "/^\D+([\d\.\,]+)$/");

            if (!empty($tax)) {
                $c->price()
                    ->tax(PriceHelper::parse($tax, $currency));
            }
        } elseif (!preg_match("/^\s*As Agreed\s*$/", $price)) {
            $c->price()
                ->total(null);
        }

        $segmentsText = implode("\n", $this->http->FindNodes("//text()[normalize-space()='Onboard' or normalize-space()='Ashore']/ancestor::table[1]/descendant::tr[normalize-space()][not(contains(normalize-space(), 'IMPORTANT BOOKING INFORMATION'))]"));
        $segments = splitter("/^(Day.+\n)/mu", $segmentsText);

        foreach ($segments as $segment) {
            if (stripos($segment, 'AT SEA') !== false || stripos($segment, 'VIEWING') !== false || stripos($segment, 'INTERNATIONAL DATE LINE') !== false) {
                continue;
            }

            $s = $c->addSegment();

            if (stripos($segment, 'Onboard') !== false && stripos($segment, 'Ashore') == false) {
                if (preg_match("/Date\s*(?<date>\d+\-\w+\-\d{4})\nPort\s*(?<port>.+)\nOnboard\s*(?<time>[\d\:]+\s*A?P?M)/", $segment, $m)) {
                    $s->setAboard(strtotime($m['date'] . ', ' . $m['time']));
                    $s->setName($m['port']);
                }
            }

            if (stripos($segment, 'Ashore') !== false && stripos($segment, 'Onboard') == false) {
                if (preg_match("/Date\s*(?<date>\d+\-\w+\-\d{4})\nPort\s*(?<port>.+)\nAshore\s*(?<time>[\d\:]+\s*A?P?M)/", $segment, $m)) {
                    $s->setAshore(strtotime($m['date'] . ', ' . $m['time']));
                    $s->setName($m['port']);
                }
            }

            if (stripos($segment, 'Ashore') !== false && stripos($segment, 'Onboard') !== false) {
                if (preg_match("/Date\s*(?<date>\d+\-\w+\-\d{4})\nPort\s*(?<port>.+)\nAshore\s*(?<AshoreTime>[\d\:]+\s*A?P?M)\nOnboard\s*(?<AboardTime>[\d\:]+\s*A?P?M)/", $segment, $m)) {
                    $s->setName($m['port']);
                    $s->setAboard(strtotime($m['date'] . ', ' . $m['AboardTime']));
                    $s->setAshore(strtotime($m['date'] . ', ' . $m['AshoreTime']));
                }
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->parseCruise($email);

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

    protected function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
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

    private function normalizeCurrency(string $string)
    {
        $string = trim($string);
        $currencies = [
            'GBP' => ['£'],
            'EUR' => ['€'],
            '$'   => ['$'],
            'INR' => ['Rs.'],
            'USD' => ['US$'],
        ];

        foreach ($currencies as $code => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $code;
                }
            }
        }

        return $string;
    }
}
