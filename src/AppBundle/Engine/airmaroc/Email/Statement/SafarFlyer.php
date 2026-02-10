<?php

namespace AwardWallet\Engine\airmaroc\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class SafarFlyer extends \TAccountChecker
{
    public $mailFiles = "airmaroc/statements/it-64185712.eml, airmaroc/statements/it-64282836.eml, airmaroc/statements/it-64313212.eml, airmaroc/statements/it-64530834.eml, airmaroc/statements/it-64605945.eml";

    public static $dictionary = [
        'en' => [
            'number' => ['Your Safar Flyer Card number', 'Your Safar Flyer number', 'Your Safar Flyer ID', 'your membership number'],
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@royalairmaroc.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true && strpos($headers['subject'], 'Safar Flyer') === false) {
            return false;
        }

        foreach (['Welcome to', 'PIN code update'] as $phrases) {
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
        if (self::detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//a[contains(@href,"facebook.com/safarflyer")]')->length === 0
            && $this->http->XPath->query('//node()[contains(normalize-space(),"Best Regards, SAFAR FLYER TEAM") or contains(.,"@royalairmaroc.com")]')->length === 0
        ) {
            return false;
        }

        return $this->http->XPath->query('//node()[' . $this->contains(self::$dictionary['en']['number']) . ' or contains(normalize-space(),"We are glad to count you among our frequent flyer program members") or contains(normalize-space(),"your PIN code has been updated")]')->length > 0;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        $patterns['travellerName'] = '[[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]]';

        $name = null;

        $welcomeText = $this->http->FindSingleNode("//text()[starts-with(normalize-space(),'Dear')]");

        if (preg_match("/Dear\s+({$patterns['travellerName']})(?:\s*[,;:!?]|$)/iu", $welcomeText, $m)) {
            // Dear Mr Su,
            $name = $m[1];
        }

        if (!$name && preg_match("/({$patterns['travellerName']})[ ]*,[ ]*need a break already/i", $parser->getSubject(), $m)) {
            // Steven, need a break already?
            $name = $m[1];
        }

        $number = $this->http->FindSingleNode("//text()[{$this->contains(self::$dictionary['en']['number'])}]", null, true, "/{$this->opt(self::$dictionary['en']['number'])}[:\s]+(\d{5,})$/");

        if (!$number) {
            $number = $this->http->FindSingleNode("//text()[{$this->starts(self::$dictionary['en']['number'])}]/following::text()[normalize-space()][1]", null, true, '/^\d{5,}$/');
        }

        if (!$number && preg_match('/\(\s*(\d{5,})\s*\)/', $welcomeText, $m)) {
            // Dear Mrs Pichugina, (862828654)
            $number = $m[1];
        }

        $st->addProperty('Name', $name)
            ->setNumber($number)
            ->setLogin($number);

        if ($name || $number) {
            $st->setNoBalance(true);
        }

        return $email;
    }

    public static function getEmailLanguages()
    {
        return [];
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function starts($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'starts-with(normalize-space(' . $node . '),"' . $s . '")';
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
}
