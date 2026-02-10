<?php

namespace AwardWallet\Engine\amazongift\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class NoStatement extends \TAccountChecker
{
    public $mailFiles = "amazongift/statements/it-65578798.eml, amazongift/statements/it-65658946.eml, amazongift/statements/it-98814490.eml";
    public $subjects = [
        '/(^|:\s*)Revision to Your Amazon\.com Account$/i',
        '/(^|:\s*)Notification of a change to the Amazon Rewards Visa Card in your Amazon/i',
        '/(^|:\s*)Your Amazon verification code/i',
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) === true) {
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
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'Amazon')]")->count() > 0
            && $this->isMembership();
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]amazon\.com/i', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $verificationCode = $this->http->FindSingleNode("//text()[contains(normalize-space(),'please enter the following code') or contains(normalize-space(),'verification code, please enter') or contains(normalize-space(),'use the following code')]/following::text()[normalize-space()][1]", null, true, "/^\d{3,}$/");

        if ($verificationCode !== null) {
            // it-98814490.eml
            $code = $email->add()->oneTimeCode();
            $code->setCode($verificationCode);
        }

        if ($this->isMembership()) {
            $st = $email->add()->statement();
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
            'Per your request, we have successfully changed your password',
            'Congratulations! The Amazon Rewards Visa Signature Card',
            'We noticed that there was an attempt to sign in to your Amazon account', // it-98814490.eml
            'To complete your sign in, please enter the following code',
            'If you did not ask for, or were not prompted for a verification code, please change your password immediately by visiting your account',
        ];

        foreach ($phrases as $phrase) {
            if ($this->http->XPath->query("//text()[{$this->contains($phrase)}]")->length > 0) {
                return true;
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
}
