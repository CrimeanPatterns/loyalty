<?php

namespace AwardWallet\Engine\fandango\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class IsJunk extends \TAccountChecker
{
    public $mailFiles = "fandango/statements/it-100942011.eml";
    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'Fandango Media')]")->length > 0
            && $this->http->XPath->query("//a[contains(normalize-space(), 'FANDANGOVIP')]/preceding::text()[normalize-space()='JOIN']")->length > 0
            && $this->http->XPath->query("//a[contains(normalize-space(), 'FANDANGOVIP')]")->length > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        if ($this->detectEmailByBody($parser) == true) {
            $email->setIsJunk(true);
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }
}
