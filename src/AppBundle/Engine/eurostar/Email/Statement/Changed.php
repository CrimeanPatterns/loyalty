<?php

namespace AwardWallet\Engine\eurostar\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Changed extends \TAccountChecker
{
    public $mailFiles = "eurostar/statements/it-65913719.eml, eurostar/statements/it-631453661.eml";

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider(rtrim($headers['from'], '> ')) !== true && strpos($headers['subject'], 'Eurostar') === false) {
            return false;
        }

        return preg_match('/(?:^|:\s*)(?:Reset your password|Your password has been changed|Welcome to your Eurostar Account[\s!]*)$/i', $headers['subject']) > 0;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//a[contains(@href,".eurostar.com/") or contains(@href,"login.eurostar.com") or contains(@href,"e.eurostar.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"Eurostar password") or contains(normalize-space(),"Welcome to Eurostar")]')->length === 0
        ) {
            return false;
        }

        return $this->isMembership();
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]eurostar\.com$/i', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        $yourEmail = $this->http->FindSingleNode("//text()[contains(normalize-space(),'log in using your email address')]/following::text()[normalize-space()][1]", null, true, '/^\S+@\S+$/');

        if ($yourEmail) {
            // it-631453661.eml
            $st->setLogin($yourEmail);

            if ($this->http->XPath->query("//text()[contains(.,'Point') or contains(.,'point')]")->length === 0) {
                $st->setNoBalance(true);
            }
        } elseif ($this->isMembership()) {
            // it-65913719.eml
            $st->setMembership(true);
        }

        return $email;
    }

    public static function getEmailLanguages()
    {
        return ['en'];
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function isMembership(): bool
    {
        $phrases = [
            'Reset Your Password',
            'Your password has been changed',
            'log in using your email',
            "Your account's all set up and ready to go",
            'To set a new password, click the button below',
            'We received a request to reset your Eurostar password',
        ];

        return $this->http->XPath->query("//*[{$this->contains($phrases)}]")->length > 0;
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
