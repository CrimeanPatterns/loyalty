<?php

namespace AwardWallet\Engine\barclaycard\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class YourStatement extends \TAccountChecker
{
    public $mailFiles = "barclaycard/statements/it-66136982.eml";

    private $subjects = [
        'en' => ['Your statement is now available to view online'],
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[.@](?:barclays|barclaysus)\.co/i', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
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
        if ($this->detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//a[contains(@href,"barclays.co.uk")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"Your Barclays Team") or contains(.,"@barclays.com") or contains(.,"@assure.barclays.co.uk")]')->length === 0
        ) {
            return false;
        }

        return $this->http->XPath->query("//*[contains(normalize-space(),'Your statement is now available online')]")->length > 0;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $patterns = [
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]',
        ];

        $st = $email->add()->statement();

        $name = null;

        $names = array_filter($this->http->FindNodes("//text()[starts-with(normalize-space(),'Dear')]", null, "/^Dear[,\s]+({$patterns['travellerName']})(?:\s*[,;:!?]|$)/u"));

        if (count(array_unique($names)) === 1) {
            $name = array_shift($names);
        }

        if (!empty($name)) {
            $st->addProperty('Name', $name);
        }

        $number = $this->http->FindSingleNode("//text()[contains(normalize-space(),'these are the last four digits of your account number')]", null, true, "/^(\d{4,})[:\s]+these are the last four digits of your account number/");

        if (!$number && preg_match("/Account number ending[:\s]+[*]*(\d{4,})\b/i", $parser->getSubject(), $m)) {
            // Account number ending ****6474
            $number = $m[1];
        }
        $st->setNumber($number)->masked();

        if ($name || $number) {
            $st->setNoBalance(true);
        }

        return $email;
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }
}
