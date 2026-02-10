<?php

namespace AwardWallet\Engine\british\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class LoyaltyMembers extends \TAccountChecker
{
    public $mailFiles = "british/statements/it-61840955.eml, british/statements/it-61853265.eml";
    public $headers = [
        '/^A new Executive Club$/',
        '/^New benefits for Blue members of the Executive Club.$/',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            "Membership No" => ["Membership No", "Membership no"],
            "Tier points"   => ["Tier points", "Tier Points"],
            "Avios points"  => ["Avios points", "BA Miles:"],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@my.ba.com') !== false) {
            foreach ($this->headers as $header) {
                if (preg_match($header, $headers['subject'])) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'British Airways')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Membership No'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->eq($this->t('Avios points'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]my\.ba\.com$/', $from) > 0;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        $name = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Dear'))}]", null, true, "/^Dear\s*Mrs?\s+(\D+)\,?$/");

        if (!empty($name)) {
            $st->addProperty('Name', trim($name, ','));
        }

        $number = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Membership No'))}]/following::text()[normalize-space()][1]", null, true, "/^(\d{7,})$/");

        if (!empty($number)) {
            $st->addProperty('Number', $number);
        }

        $balance = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Avios points'))}]/following::text()[normalize-space()][1]");
        $balance = str_replace([',', '.'], '', $balance);

        if (!empty($balance)) {
            $st->setBalance($balance);
        } elseif ($balance == '0') {
            $st->setBalance(0);
        }

        $tierPoints = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Tier points'))}]/following::text()[normalize-space()][1]");

        if (!empty($tierPoints)) {
            $st->addProperty('TierPoints', $tierPoints);
        } elseif ($tierPoints == 0) {
            $st->addProperty('TierPoints', $tierPoints);
        }
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

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) {
            return "normalize-space(.)=\"{$s}\"";
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
