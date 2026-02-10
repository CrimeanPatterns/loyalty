<?php

namespace AwardWallet\Engine\british\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class RewardsClaim extends \TAccountChecker
{
    public $mailFiles = "british/statements/it-61577429.eml, british/statements/it-61876784.eml";
    public $headers = [
        '/^Received - Executive Club claim for/',
        '/^Executive Club Bestellung eingegangen$/',
    ];

    public $lang = '';

    public static $dictionary = [
        "en" => [
            //'Membership number' => '',
            'My Avios' => 'My Avios',
            //'My Tier Points' => '',
            //'My Lifetime Tier Points' => '',
            'Dear' => ['Dear Mr', 'Dear Mrs'],
        ],

        "de" => [
            'Membership number'       => 'Mitgliedsnummer',
            'My Avios'                => 'Meine Avios',
            'My Tier Points'          => 'Meine Statuspunkte',
            'My Lifetime Tier Points' => 'Meine Lifetime-Statuspunkte',
            'Dear'                    => 'Sehr geehrter',
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
        if ($this->detectLang() === true) {
            return $this->http->XPath->query("//text()[{$this->contains($this->t('British Airways'))}]")->count() > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Membership number'))}]")->count() > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('My Avios'))}]")->count() > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]my\.ba\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->detectLang();

        $st = $email->add()->statement();

        $name = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Dear'))}]", null, true, "/^{$this->opt($this->t('Dear'))}\s+(\D+)$/");

        if (!empty($name)) {
            $st->addProperty('Name', trim($name, ','));
        }

        $headers = $this->http->FindNodes("//text()[{$this->starts($this->t('Membership number'))}]/ancestor::tr[1]/td");
        $values = $this->http->FindNodes("//text()[{$this->starts($this->t('Membership number'))}]/ancestor::tr[1]/following::tr[1]/td");
        $params = [];

        if (count($headers) === count($values)) {
            $headers = array_filter($headers);

            foreach ($headers as $i => $h) {
                $params[$h] = $values[$i];
            }
        }

        if (empty($params)) {
            return $email;
        }

        foreach ((array) $this->t('Membership number') as $name) {
            if (isset($params[$name])) {
                $st->setNumber($params[$name]);
            }
        }

        foreach ((array) $this->t('My Avios') as $name) {
            if (isset($params[$name])) {
                $st->setBalance(str_replace([',', '.'], '', $params[$name]));
            }
        }

        foreach ((array) $this->t('My Tier Points') as $name) {
            if (isset($params[$name])) {
                $st->addProperty('TierPoints', $params[$name]);
            }
        }

        foreach ((array) $this->t('My Lifetime Tier Points') as $name) {
            if (isset($params[$name])) {
                $st->addProperty('LifetimeTierPoints', $params[$name]);
            }
        }

        $level = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Membership number'))}]/preceding::img[1][contains(@src, 'www.britishairways.com')]/@src", null, true,
            "/\/(blue|bronze|silver|gold)-logo-vsg.png/u");

        if (!empty($level)) {
            $st->addProperty('Level', ucfirst($level));
        }
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

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }

    private function detectLang()
    {
        $body = $this->http->Response['body'];

        foreach (self::$dictionary as $lang => $detects) {
            foreach ($detects as $detect) {
                if (is_array($detect)) {
                    foreach ($detect as $word) {
                        if (stripos($body, $detect[0]) !== false) {
                            $this->lang = $lang;

                            return true;
                        }
                    }
                } elseif (is_string($detect) && stripos($body, $detect) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }
}
