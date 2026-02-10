<?php

namespace AwardWallet\Engine\capitalcards\Email\Statement;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\capitalcards\Email\HotelReservation;
use AwardWallet\Schema\Parser\Email\Email;

class WithBalance extends \TAccountChecker
{
    public $mailFiles = "capitalcards/statements/it-62087716.eml, capitalcards/statements/it-62088142.eml";

    public $headers = [
        'Your card statement is ready',
        'Your rewards credit is on its way',
        'Your SwiftID profile is updated',
        'Your new card has shipped',
    ];

    public $lang = 'en';

    public static $dictionary = [
        'en' => [
            'accountEndingIn'  => ['Account ending in', 'account ending in', 'Card ending in', 'card ending in'],
            'statementBalance' => ['Statement balance:', 'statement balance is'],
            'welcome'          => ['Dear', 'Hi', 'Seriously,', 'Hey', 'Hello'],
        ],
    ];

    private $detectors = [
        'en' => [
            'A new card is on the way for',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider(rtrim($headers['from'], '> ')) !== true) {
            return false;
        }

        foreach ($this->headers as $header) {
            if (strpos($headers['subject'], $header) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->detectEmailFromProvider(rtrim($parser->getHeader('from'), '> ')) !== true
            && $this->http->XPath->query('//a[contains(@href,".capitalone.com/") or contains(@href,"www.capitalone.com") or contains(@href,"notification.capitalone.com")]')->length === 0
            && $this->http->XPath->query("//*[contains(normalize-space(),'Thank you for being a Capital One') or contains(.,'www.capitalone.com') or contains(.,'@notification.capitalone.com')]")->length === 0
        ) {
            return false;
        }

        if (HotelReservation::assignLang($this)) {
            return false;
        }

        return $this->detectBody()
            || $this->http->XPath->query("//text()[{$this->contains($this->t('accountEndingIn'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[.@]capitalone\.com$/i', $from) > 0;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $patterns['travellerName'] = '[[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]]';

        $st = $email->add()->statement();

        /* because this credit card number!
        $numberEnd = $this->http->FindSingleNode("//text()[{$this->contains($this->t('accountEndingIn'))}]", null, true, "/{$this->opt($this->t('accountEndingIn'))}\s+(?-i)([A-Z\d]{4,})[,;:?!\s]*$/i");

        if ($numberEnd !== null) {
            $st->setNumber($numberEnd)->masked();
        }
        */

        $name = $this->http->FindSingleNode("//text()[{$this->starts($this->t('welcome'))}]", null, true, "/^{$this->opt($this->t('welcome'))}\s+({$patterns['travellerName']})[,;:?!]*$/iu");

        if (empty($name)) {
            $name = $this->http->FindSingleNode("//text()[{$this->contains($this->t('accountEndingIn'))}]/following::text()[{$this->contains(',')}][1]", null, true, "/^({$patterns['travellerName']})[,;:?!]*$/u");
        }

        if (empty($name) && preg_match("/^{$this->opt($this->t('welcome'))}\s*({$patterns['travellerName']})(?:\s*[,;:?!]|$)/iu", $parser->getSubject(), $m)) {
            $name = $m[1];
        }

        if (preg_match("/^\s*there\s*$/i", $name)) {
            $name = null;
        }

        if (!empty($name)) {
            $st->addProperty('Name', $name);
        }

        $login = $this->http->FindSingleNode("//text()[{$this->starts($this->t('This email was sent'))}]/following::text()[normalize-space()][1]", null, true, "/^\S+@\S+\.\S+$/");

        if (empty($login)) {
            $login = $this->http->FindSingleNode("//text()[{$this->starts($this->t('This email was sent'))}]", null, true, "/^{$this->starts($this->t('This email was sent'))}(?: to)?\s+(\S+@\S+\.\S+)\b/");
        }

        if (!empty($login)) {
            $st->setLogin($login);
        }

        $balance = $this->http->FindSingleNode("//tr[ *[1][{$this->starts($this->t('statementBalance'))}] ]/*[2]", null, true, '/\d[,.\'\d]*/');

        if ($balance === null) {
            $balance = $this->http->FindSingleNode("//text()[{$this->contains($this->t('accountEndingIn'))}]/following::text()[{$this->contains($this->t('balance'))}][1]", null, true, '/\s+[^\d\s)(]+(\d[,.\'\d]*)/');
        }

        if ($balance !== null) {
            $st->setBalance(PriceHelper::parse(rtrim($balance, ', ')));
        } elseif ($name || $login) {
            $st->setNoBalance(true);
        }

        $currency = $this->http->FindSingleNode("//text()[{$this->starts($this->t('statementBalance'))}]/following::text()[normalize-space()][1]", null, true, '/^(\S{1})[\d\,\.]+/');

        if (empty($currency)) {
            $currency = $this->http->FindSingleNode("//text()[{$this->contains($this->t('accountEndingIn'))}]/following::text()[{$this->contains($this->t('balance'))}][1]", null, true, '/\s+([^\d\s)(]+)\d/');
        }

        if (!empty($currency)) {
            $st->addProperty('Currency', $currency);
        }
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    public function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'normalize-space(' . $node . ')=' . $s;
        }, $field)) . ')';
    }

    private function detectBody(): bool
    {
        if ($this->http->XPath->query("//span[starts-with(normalize-space(),'This email was sent') and contains(normalize-space(),'contains information directly related to your account')]")->length === 1) {
            return true;
        }

        if (!isset($this->detectors)) {
            return false;
        }

        foreach ($this->detectors as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (!is_string($phrase)) {
                    continue;
                }

                if ($this->http->XPath->query("//*[{$this->contains($phrase)}]")->length > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'contains(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function starts($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'starts-with(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }
}
