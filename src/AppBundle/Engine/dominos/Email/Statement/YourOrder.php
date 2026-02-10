<?php

namespace AwardWallet\Engine\dominos\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class YourOrder extends \TAccountChecker
{
    public $mailFiles = "dominos/statements/it-66522786.eml";

    private $detectors = [
        'en' => ['Visit your Pizza Profile to track your points.'],
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[.@]dominos\.[^.]+$/i', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return strpos($headers['subject'], "Your Domino's Order") !== false
            || strpos($headers['subject'], "Your Domino's Pizza Order") !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if (self::detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//a[contains(@href,".dominos.ca/") or contains(@href,"order.dominos.ca") or contains(@href,".dominos.com/") or contains(@href,"www.dominos.com")]')->length === 0
            && $this->http->XPath->query('//node()[contains(.,"@confirmation.dominos.com") or contains(.,"@dominos.ca") or contains(.,"www.dominos.com") or contains(.,"www.dominos.ca")]')->length === 0
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

        $st = $email->add()->statement();

        $name = $this->http->FindSingleNode("//text()[starts-with(normalize-space(),'Name on Order')]/following::text()[normalize-space()][1]", null, true, '/^[[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]]$/u');
        $st->addProperty('Name', $name);

        if ($name) {
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
