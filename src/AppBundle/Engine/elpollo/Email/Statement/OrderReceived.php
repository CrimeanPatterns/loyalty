<?php

namespace AwardWallet\Engine\elpollo\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class OrderReceived extends \TAccountChecker
{
    public $mailFiles = "elpollo/statements/it-66002574.eml, elpollo/statements/it-66209349.eml";

    private $detectors = [
        'en' => [
            'You have received this email because you registered at elpolloloco.com',
            'Thank you for ordering with us.',
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@loco-rewards.com') !== false
            || stripos($from, 'El Pollo Loco Rewards') !== false
            || stripos($from, 'El Pollo Loco Online Ordering') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['subject'], 'El Pollo Loco Order Received') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if (self::detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//a[contains(@href,".loco-rewards.com/") or contains(@href,"email.loco-rewards.com")]')->length === 0
            && $this->http->XPath->query('//node()[contains(normalize-space(),"El Pollo Loco, Inc. All rights reserved") or contains(.,"elpolloloco.noreply@olo.com") or contains(.,"@loco-rewards.com")]')->length === 0
        ) {
            return false;
        }

        return $this->detectBody();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if ($this->detectBody() === false) {
            return $email;
        }

        $patterns = [
            'travellerName' => '[[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]]',
            'email'         => '\S+@[-.A-z\d]+\b',
        ];

        $st = $email->add()->statement();

        $name = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][starts-with(normalize-space(),'Customer Name')] ]/*[normalize-space()][2]", null, true, "/^{$patterns['travellerName']}$/u");

        if (!$name && preg_match("/(?:^|:\s*)([[:upper:]]{$patterns['travellerName']})[ ]*,[ ]*your/u", $parser->getSubject(), $m)) {
            // it-66002574.eml
            $name = $m[1];
        }

        if ($name) {
            $st->addProperty('Name', $name);
        }

        $login = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][starts-with(normalize-space(),'Customer Email')] ]/*[normalize-space()][2]", null, true, "/^{$patterns['email']}$/");

        if (!$login) {
            // it-66002574.eml
            $login = $this->http->FindSingleNode("descendant::*[not(.//tr) and contains(normalize-space(),'This email was sent to')][1]", null, true, "/^This email was sent to[:\s]+({$patterns['email']})(?:[.,;!]+ |$)/m");
        }

        if ($login) {
            $st->setLogin($login);
        }

        if ($name || $login) {
            $st->setNoBalance(true);
        }

        return $email;
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function detectBody(): bool
    {
        if (!isset($this->detectors)) {
            return false;
        }

        foreach ($this->detectors as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (!is_string($phrase)) {
                    continue;
                }

                if ($this->http->XPath->query("//node()[{$this->contains($phrase)}]")->length > 0) {
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
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }
}
