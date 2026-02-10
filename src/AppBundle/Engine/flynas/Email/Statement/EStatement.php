<?php

namespace AwardWallet\Engine\flynas\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class EStatement extends \TAccountChecker
{
    public $mailFiles = "flynas/statements/it-602968442.eml";

    private $subjects = [
        'en' => ['E-Statement'],
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[.@]flynas\.com$/i', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider(rtrim($headers['from'], '> ')) !== true) {
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
        if ($this->detectEmailFromProvider(rtrim($parser->getHeader('from'), '> ')) !== true
            && $this->http->XPath->query('//a[contains(@href,".flynas.com/") or contains(@href,"nasmiles.flynas.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"Thank you for choosing flynas")]')->length === 0
        ) {
            return false;
        }

        return $this->findRoot()->length > 0;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $email->setType('EStatement');

        $roots = $this->findRoot();

        if ($roots->length !== 1) {
            $this->logger->debug('Root-node not found!');

            return $email;
        }

        $root = $roots->item(0);

        $st = $email->add()->statement();

        $nasmilesID = $this->http->FindSingleNode("*[normalize-space()][1]", $root, true, "/^nasmiles ID\s*(\d{7,13})$/i");
        $balance = $this->http->FindSingleNode("*[normalize-space()][3]", $root, true, "/^SMILE Points Balance\s*(\d[,.‘\'\d ]*)$/iu");
        $balanceExpiring = $this->http->FindSingleNode("*[normalize-space()][4]", $root, true, "/^SMILE Points Expiring\s*(\d[,.‘\'\d ]*)$/iu");

        $st->setNumber($nasmilesID)->setBalance($balance)->addProperty('ExpiringBalance', $balanceExpiring);

        $names = array_filter($this->http->FindNodes("//text()[starts-with(normalize-space(),'Dear')]", null, "/^Dear[,\s]+([[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])(?:\s*[,;:!?]|$)/u"));

        if (count(array_unique($names)) === 1) {
            $name = array_shift($names);
            $st->addProperty('Name', $name);
        }

        return $email;
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function findRoot(): \DOMNodeList
    {
        return $this->http->XPath->query("//*[ count(*[normalize-space()])=4 and *[normalize-space()][1][starts-with(normalize-space(),'nasmiles ID')] and *[normalize-space()][3][starts-with(normalize-space(),'SMILE Points Balance')] ]");
    }
}
