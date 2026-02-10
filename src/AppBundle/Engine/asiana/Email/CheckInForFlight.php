<?php

namespace AwardWallet\Engine\asiana\Email;

use AwardWallet\Schema\Parser\Email\Email;

class CheckInForFlight extends \TAccountChecker
{
    public $mailFiles = "asiana/it-108395256.eml, asiana/it-108869105.eml";

    public $lang = '';

    public static $dictionary = [
        'ko' => [ // it-108395256.eml
            'imgBtnAlt'     => '체크인 바로가기',
            'langDetectors' => '온라인 체크인이 가능합니다',
        ],
        'en' => [ // it-108869105.eml
            'imgBtnAlt'     => 'Proceed Check-in',
            'langDetectors' => 'Check-in for flight',
        ],
    ];

    private $subjects = [
        'ko' => ['온라인 체크인이 가능합니다'],
        'en' => ['Check-in for your upcoming flight is now available'],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@flyasiana.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true
            && strpos($headers['subject'], '[Asiana Airlines]') === false
            && strpos($headers['subject'], '[아시아나항공]') === false
        ) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (is_string($phrase) && stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,".flyasiana.com/") or contains(@href,"ozcheck.flyasiana.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"Unsubscribe from Asiana Airlines")]')->length === 0
        ) {
            return false;
        }

        return $this->assignLang() && $this->http->XPath->query("//img[{$this->contains($this->t('imgBtnAlt'), '@alt')}]")->length > 0;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }
        $email->setType('CheckInForFlight' . ucfirst($this->lang));

        $this->parseFlight($email);

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

    private function parseFlight(Email $email): void
    {
        $xpathTime = 'contains(translate(normalize-space(),"0123456789：","dddddddddd:"),"d:dd")';

        $f = $email->add()->flight();

        $segments = $this->http->XPath->query("//tr[ count(*[normalize-space()])=3 and *[normalize-space()][last()][{$xpathTime}] and following-sibling::tr[count(*[normalize-space()])=2 and *[normalize-space()][last()][{$xpathTime}]] ]");

        foreach ($segments as $root) {
            $s = $f->addSegment();

            $flight = $this->http->FindSingleNode('*[normalize-space()][1]', $root);

            if (preg_match('/^(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<number>\d+)$/', $flight, $m)) {
                $s->airline()->name($m['name'])->number($m['number']);
            }

            $nameDep = $this->http->FindSingleNode('*[normalize-space()][2]', $root);

            if (preg_match("/^(?<code>[A-Z]{3})\s+(?<name>.{3,})$/", $nameDep, $m)) {
                $s->departure()->code($m['code'])->name($m['name']);
            } elseif (preg_match("/^[A-Z]{3}$/", $nameDep)) {
                $s->departure()->code($nameDep);
            } else {
                $s->departure()->name($nameDep);
            }

            $dateTimeDep = implode(' ', $this->http->FindNodes('*[normalize-space()][3]/descendant::text()[normalize-space()]', $root));
            $s->departure()->date2($dateTimeDep);

            $nameArr = $this->http->FindSingleNode('following-sibling::tr[normalize-space()][1]/*[normalize-space()][1]', $root);

            if (preg_match("/^(?<code>[A-Z]{3})\s+(?<name>.{3,})$/", $nameArr, $m)) {
                $s->arrival()->code($m['code'])->name($m['name']);
            } elseif (preg_match("/^[A-Z]{3}$/", $nameArr)) {
                $s->arrival()->code($nameArr);
            } else {
                $s->arrival()->name($nameArr);
            }

            $dateTimeArr = implode(' ', $this->http->FindNodes('following-sibling::tr[normalize-space()][1]/*[normalize-space()][2]/descendant::text()[normalize-space()]', $root));
            $s->arrival()->date2($dateTimeArr);
        }

        $pnr = $this->http->FindSingleNode("(//a/@href[contains(., '.flyasiana.com') andcontains(., '.do?pnr=')])[1]",
            null, true, "/\?pnr=([A-Z\d]{5,7})\&/");

        if (!empty($pnr)) {
            $f->general()
                ->confirmation($pnr);
        } elseif ($segments->length > 0) {
            $f->general()->noConfirmation();
        }
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['langDetectors'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[{$this->contains($phrases['langDetectors'])}]")->length > 0) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function t(string $phrase)
    {
        if (!isset(self::$dictionary, $this->lang) || empty(self::$dictionary[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$this->lang][$phrase];
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
}
