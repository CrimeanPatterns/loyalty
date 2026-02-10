<?php

namespace AwardWallet\Engine\avis\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class Membership extends \TAccountChecker
{
    public $mailFiles = "avis/statements/it-123544336.eml, avis/statements/it-123545538.eml";

    private $detectSubjects = [
        'Change Password Request',
        'Your Account Has Been Locked',
    ];
    private $detectBody = [
        'Need password help? It\'s easy',
        'For the security of your information, your account has been locked out',
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'avis@e.avis.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers['from'], 'avis@e.avis.com') === false) {
            return false;
        }
        foreach ($this->detectSubjects as $dSubject) {
            if (stripos($headers['subject'], $dSubject) !== false) {
                return true;
            }
        }
        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if (!empty($this->http->FindSingleNode("(//a[contains(@href, 'https://click.e.avis.com')]/@href)[1]"))
            && !empty($this->http->FindSingleNode("//text()[".$this->contains($this->detectBody)."]"))
        ) {
            return true;
        }
        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if ($this->detectEmailByHeaders($parser->getHeaders()) == true
            && !empty($this->detectEmailByBody($parser) === true)
        ) {
            $st = $email->add()->statement();
            $st->setMembership(true);
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class));

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

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'contains(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }
}
