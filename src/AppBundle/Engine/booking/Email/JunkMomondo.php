<?php

namespace AwardWallet\Engine\booking\Email;

use AwardWallet\Schema\Parser\Email\Email;

class JunkMomondo extends \TAccountChecker
{
    public $mailFiles = "booking/it-60263768.eml";

    public function detectEmailFromProvider($from)
    {
        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[normalize-space()][1][{$this->eq("momondo flight summary")}]")->length > 0
            && $this->http->XPath->query("//a[{$this->starts("Book now from ")} and contains(@href, '.momondo.') and contains(@href, '/flight-search/')]")->length > 0
        ) {
            return true;
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if ($this->detectEmailByBody($parser)) {
            $email->setIsJunk(true);
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class));

        return $email;
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.) = "' . $s . '"';
        }, $field)) . ')';
    }
}
