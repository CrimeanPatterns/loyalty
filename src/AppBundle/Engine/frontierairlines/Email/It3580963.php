<?php

namespace AwardWallet\Engine\frontierairlines\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class It3580963 extends \TAccountCheckerExtended
{
    public $mailFiles = "frontierairlines/it-3580963.eml, frontierairlines/it-3580966.eml, frontierairlines/it-75157128.eml";
    public $provKeyword = 'Frontier Airlines';
    public $reBody = ["This important notification is being delivered from"];
    public $reSubject = ["There has been a change to your upcoming flight"];
    public $reFrom = "info@reservation.flyfrontier.com";

    private static $dict = [
        'en' => [
            'Confirmation Code'       => ['Confirmation Code', 'RESERVATION CODE', 'Trip Confirmation Number'],
            'There has been a change' => 'There has been a change',
        ],
    ];
    private $lang;

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (strpos($body, $this->provKeyword) === false) {
            return false;
        }

        foreach ($this->reBody as $reBody) {
            if (strpos($body, $reBody) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers["from"]) === false) {
            return false;
        }

        foreach ($this->reSubject as $reSubject) {
            if (strpos($headers["subject"], $reSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->http->FilterHTML = false;

        $body = $parser->getHTMLBody();

        if (empty($body)) {
            $body = $parser->getPlainBody();
            $this->http->SetBody($body);
        }

        if (!$this->assignLang()) {
            $this->logger->debug('can\'t determine a language');

            return $email;
        }

        $this->parseEmail($email);

        return $email;
    }

    private function parseEmail(Email $email)
    {
        $r = $email->add()->flight();

        if ($phone = $this->http->FindSingleNode("//p[{$this->contains('contact Frontier Airlines')}]/strong")) {
            $r->program()->phone($phone, 'contact Frontier Airlines');
        }
        $confNo = $this->http->FindSingleNode("//p[{$this->starts($this->t('Confirmation Code'))}]", null, false,
            "/{$this->opt($this->t('Confirmation Code'))}\s*:\s*([\w-]+)\s*$/i");

        if (empty($confNo)) {
            $confNo = $this->http->FindSingleNode(
                "(//text()[{$this->contains($this->t('Confirmation Code'))}])[last()]/ancestor::p[1]",
                null,
                false,
                "/{$this->opt($this->t('Confirmation Code'))}\s*([\w-]+)\.?\s*$/i"
            );
        }
        $r->general()
            ->confirmation($confNo)
            ->travellers($this->http->FindNodes("//p[{$this->contains($this->t('There has been a change'))}]//strong"),
                true);

        $xpath = "//*[normalize-space(text())='Flight']/ancestor::tr[1]/following-sibling::tr[./td[2]]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length == 0) {
            $this->http->Log("segments root not found: $xpath", LOG_LEVEL_NORMAL);
        }

        $year = $this->http->FindSingleNode("//*[contains(text(),'BOOKING') and contains(text(),'DETAILS')]/ancestor::table[1]/following-sibling::table[3]//td[2]",
            null, true, "#(\d+)$#");

        foreach ($nodes as $root) {
            $s = $r->addSegment();
            $s->airline()
                ->name($this->http->FindSingleNode("./td[2]", $root, true, "#([A-Z\d][A-Z]|[A-Z][A-Z\d])\s+\d+#"))
                ->number($this->http->FindSingleNode("./td[2]", $root, true,
                    "#(?:[A-Z\d][A-Z]|[A-Z][A-Z\d])\s+(\d+)#"));

            $s->departure()
                ->code($this->http->FindSingleNode("./td[3]", $root, true, "#\(([A-Z]{3})\)#"))
                ->date2($this->http->FindSingleNode("(./td[3]//text()[normalize-space(.)!=''])[2]", $root));

            $s->arrival()
                ->code($this->http->FindSingleNode("./td[4]", $root, true, "#\(([A-Z]{3})\)#"))
                ->date2($this->http->FindSingleNode("(./td[4]//text()[normalize-space(.)!=''])[2]", $root));

            $s->extra()
                ->aircraft($this->http->FindSingleNode("./td[6]", $root), true)
                ->stops($this->http->FindSingleNode("./td[5]", $root), true);
        }
    }

    private function starts($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function contains($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }

    private function t($phrase)
    {
        if (!isset(self::$dict[$this->lang]) || !isset(self::$dict[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dict[$this->lang][$phrase];
    }

    private function assignLang()
    {
        foreach (self::$dict as $lang => $words) {
            if (isset($words["Confirmation Code"], $words["There has been a change"])) {
                if ($this->http->XPath->query("//*[{$this->contains($words['Confirmation Code'])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($words['There has been a change'])}]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }
}
