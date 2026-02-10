<?php

namespace AwardWallet\Engine\airmilesca\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class CashReward extends \TAccountChecker
{
    public $mailFiles = "airmilesca/statements/it-70995225.eml, airmilesca/statements/it-79812425.eml, airmilesca/statements/it-79687779.eml, airmilesca/statements/it-79640567.eml";
    public $subjects = [
        '/^Congratulations on your AIR MILES Cash reward$/',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'hello'  => ['Yay', 'Hey', 'Hi'],
            'number' => ['Your Collector Number is', 'AIR MILES Collector Number:'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@email.airmiles.ca') !== false) {
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
        if ($this->detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//a[contains(@href,".airmiles.ca/") or contains(@href,"email.airmiles.ca")]')->length === 0
            && $this->http->XPath->query('//*[contains(.,"www.airmiles.ca") or contains(.,"@airmiles.ca")]')->length === 0
        ) {
            return false;
        }

        return $this->isMembership();
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]email\.airmiles\.ca$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $patterns = [
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]',
        ];

        $st = $email->add()->statement();

        $name = $number = $balance = null;

        $name = $this->http->FindSingleNode("//text()[{$this->starts($this->t('hello'))}]", null, true, "/^{$this->opt($this->t('hello'))}\s+({$patterns['travellerName']})(?:[ ]*[,;:!?]|$)/u");

        if (!empty($name)) {
            $st->addProperty('Name', $name);
        }

        $number = $this->http->FindSingleNode("//text()[{$this->starts($this->t('number'))}]", null, true, "/{$this->opt($this->t('number'))}[: ]+([-A-Z\d]{5,})$/");

        if (!$number) {
            $number = $this->http->FindSingleNode("//text()[{$this->starts($this->t('number'))}]/following::text()[normalize-space()][1]", null, true, "/^[-A-Z\d]{5,}$/");
        }

        if ($number) {
            $st->setNumber($number)
                ->setLogin($number);
        }

        if (empty($name) && $number) {
            // it-79812425.eml
            $name = $this->http->FindSingleNode("//text()[{$this->contains($this->t('For your records'))}]/following::tr[ count(*)=2 ]/*[2]/descendant-or-self::*[ count(*[normalize-space()])=2 and *[normalize-space()][2][normalize-space()='{$number}'] ]/*[normalize-space()][1]", null, true, "/^{$patterns['travellerName']}$/u");

            if ($name) {
                $st->addProperty('Name', $name);
            }
        }

        $balanceText = $this->http->FindSingleNode("//text()[{$this->contains($this->t('your balance is now'))}]");

        if (preg_match("/your balance is now (?<amount>\d[,.\'\d ]*)Miles as of (?<date>\d{1,2}\/\d{1,2}\/\d{2,4})/i", $balanceText, $m)) {
            $st->setBalance($this->normalizeAmount($m['amount']))
                ->parseBalanceDate($this->normalizeDate($m['date']));
        } elseif (($balance = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Your new Cash Account balance is'))}]/following::text()[normalize-space()][1]", null, true, "/^:\s+(\d[,.\'\d ]*)\D*$/")) !== null) {
            $st->setBalance($this->normalizeAmount($balance));
            $balanceDate = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Date:')]/following::text()[normalize-space()][1]");
            $st->setBalanceDate(strtotime(str_replace("/", ".", $balanceDate)));
        } elseif ($name || $number) {
            $st->setNoBalance(true);
        } elseif ($this->isMembership()) {
            $st->setMembership(true);
        }

        return $email;
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function isMembership(): bool
    {
        $phrases = [
            'Your new Cash Account balance is:',
            "Let's reset your PIN", 'Time to verify your email and set your PIN',
            'You changed your Cash/Dream preference',
            'Your Collector Number is',
            'your balance is now',
            'You can access them at any time in My Orders.',
        ];

        return $this->http->XPath->query("//*[{$this->contains($phrases)}]")->length > 0;
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

    /**
     * Formatting over 3 steps:
     * 11 507.00  ->  11507.00
     * 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
     * 18800,00   ->  18800.00  |  2777,0    ->  2777.0.
     *
     * @param string|null $s Unformatted string with amount
     * @param string|null $decimals Symbols floating-point when non-standard decimals (example: 1,258.943)
     */
    private function normalizeAmount(?string $s, ?string $decimals = null): ?float
    {
        if (!empty($decimals) && preg_match_all('/(?:\d+|' . preg_quote($decimals, '/') . ')/', $s, $m)) {
            $s = implode('', $m[0]);

            if ($decimals !== '.') {
                $s = str_replace($decimals, '.', $s);
            }
        } else {
            $s = preg_replace('/\s+/', '', $s);
            $s = preg_replace('/[,.\'](\d{3})/', '$1', $s);
            $s = preg_replace('/,(\d{1,2})$/', '.$1', $s);
        }

        return is_numeric($s) ? (float) $s : null;
    }

    /**
     * @param string|null $text Unformatted string with date
     */
    private function normalizeDate(?string $text): string
    {
        if (!is_string($text) || empty($text)) {
            return '';
        }
        $in = [
            // 15/11/2020
            '/^(\d{1,2})\/(\d{1,2})\/(\d{2,4})$/',
        ];
        $out = [
            '$1.$2.$3',
        ];

        return preg_replace($in, $out, $text);
    }
}
