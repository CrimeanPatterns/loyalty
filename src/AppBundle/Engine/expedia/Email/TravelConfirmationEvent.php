<?php

namespace AwardWallet\Engine\expedia\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class TravelConfirmationEvent extends \TAccountChecker
{
    public $mailFiles = "expedia/it-678471443.eml, expedia/it-681370043.eml";
    public $subjects = [
        'Activity reservation',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'Redeem voucher'                   => ['Redeem voucher', 'Supplier reference:', 'Itinerary #', 'Itinerary no.'],
            'Check-in/redemption instructions' => ['Check-in/redemption instructions', 'See reservation for more details', 'Print activity vouchers'],

            'Expedia itinerary:'  => ['Expedia itinerary:', 'Itinerary #', 'Itinerary no.'],
            'traveler'            => ['traveler', 'passenger', 'traveller'],
            'View full Itinerary' => ['View full Itinerary', 'View full itinerary'],
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
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'Expedia')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Redeem voucher'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Check-in/redemption instructions'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]eg\.expedia\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $otaConf = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Expedia itinerary:'))}]", null, true, "/{$this->opt($this->t('Expedia itinerary:'))}\s*(\d{10,})$/");

        if (empty($otaConf)) {
            $otaConf = $this->http->FindSingleNode("//text()[{$this->eq($this->t('View full Itinerary'))}]/following::text()[{$this->starts($this->t('Expedia itinerary:'))}][1]/ancestor::p[1]", null, true, "/{$this->opt($this->t('Expedia itinerary:'))}\s*(\d{10,})(?:$|[A-Z])/");
        }

        $this->logger->debug("//text()[{$this->eq($this->t('View full Itinerary'))}]/following::text()[{$this->starts($this->t('Expedia itinerary:'))}][1]/ancestor::p[1]");

        $email->ota()
            ->confirmation($otaConf);

        $this->ParseEvent($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function ParseEvent(Email $email)
    {
        $e = $email->add()->event();

        $e->setEventType(EVENT_EVENT);

        $travellerText = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'voucher,') or contains(normalize-space(), 'vouchers,')]", null, true, "/^(\D+)\,\s*\d+\s*{$this->opt($this->t('voucher'))}/");

        if (empty($travellerText)) {
            $travellerText = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'passenger')]", null, true, "/^(\D+)\,\s*\d+\s*{$this->opt($this->t('passenger'))}/");
        }

        $travellers = explode("\,", $travellerText);

        $e->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->starts($this->t('Supplier reference'))}]/ancestor::p[1]", null, true, "/{$this->opt($this->t('Supplier reference'))}\:?\s*(?:https\:\/\/[a-z\.]+\/\s*)?([\dA-z\-]{5,})$/su"))
            ->travellers($travellers);

        $address = '';
        $name = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Expedia itinerary:')]/preceding::text()[normalize-space()][1]");

        if (empty($name)) {
            $name = $this->http->FindSingleNode("//img[contains(@src, 'icon__lob_activities')]/following::text()[normalize-space()][1]");

            if (!empty($name)) {
                $address = $this->http->FindSingleNode("//text()[normalize-space()='Contact information']/following::text()[normalize-space()][2][starts-with(normalize-space(), 'Daily:')]/following::text()[normalize-space()][1]");
            }
        }

        $e->setName($name);

        if (empty($address)) {
            $address = $this->http->FindSingleNode("//img[contains(@src, 'icon__place_color__neutral')]/ancestor::table[1]");
        }

        if (empty($address)) {
            $address = $this->http->FindSingleNode("//text()[normalize-space()='Where to meet']/following::text()[normalize-space()][1]");
        }

        $e->setAddress($address);

        $date = $this->http->FindSingleNode("//text()[normalize-space()='Check-in/redemption instructions']/following::text()[normalize-space()][1]", null, true, "/^(\w+\s*\d+\,\s*\d{4})$/");
        $time = $this->re("/\:\s*([\d\:]+\s*A?P?M?)\,/", $e->getName());

        $e->setGuestCount($this->http->FindSingleNode("//text()[{$this->contains($this->t('traveler'))}]", null, true, "/\s*(\d+)\s*{$this->opt($this->t('traveler'))}/"));

        $e->setStartDate(strtotime($date . ', ' . $time))
            ->setNoEndDate(true);

        $earned = $this->http->FindSingleNode('//text()[starts-with(normalize-space(), "You\'ll earn")]', null, true, "/\s+(\D*[\d\.\,]+)\s+in\s*OneKeyCash/");

        if (!empty($earned)) {
            $e->setEarnedAwards($earned);
        }

        $total = $this->http->FindSingleNode("//text()[normalize-space()='Total']/following::text()[normalize-space()][1]");

        if (preg_match("/^(?<currency>\D*)\s*(?<total>[\d\.\,]+)$/", $total, $m)) {
            $currency = $this->normalizeCurrency($m['currency']);
            $e->price()
                ->total(PriceHelper::parse($m['total'], $currency))
                ->currency($currency);

            $spentAwards = $this->http->FindSingleNode("//text()[normalize-space()='OneKeyCash used']/following::text()[normalize-space()][1]");

            if (!empty($spentAwards)) {
                $e->price()
                    ->spentAwards($spentAwards);
            }

            $cost = $this->http->FindSingleNode("//text()[normalize-space()='Price summary']/ancestor::div[1]/following::div[1]/descendant::text()[normalize-space()][last()]", null, true, "/^\D*\s*([\d\.\,]+)$/");

            if (!empty($cost)) {
                $e->price()
                    ->cost(PriceHelper::parse($cost, $m['currency']));
            }
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
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function normalizeCurrency($string)
    {
        $string = trim($string);
        $currencies = [
            'GBP' => ['£'],
            'AUD' => ['A$', 'AU$'],
            'EUR' => ['€', 'Euro'],
            'USD' => ['US Dollar'],
            'CAD' => ['CA $'],
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
