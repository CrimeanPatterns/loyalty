<?php

namespace AwardWallet\Engine\amtrak\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

// TODO: merge with parsers amtrak/EStatement (in favor of amtrak/CompanionStatement)

class CompanionStatement extends \TAccountChecker
{
    public $mailFiles = "amtrak/statements/it-63522262.eml, amtrak/statements/it-116517938.eml";

    private $reSubject = [
        'Your code',
        'Get on board with a free companion fare, plus your own private room',
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $roots = $this->findRoot();

        if ($roots->length !== 1) {
            return $email;
        }
        $root = $roots->item(0);

        $patterns['travellerName'] = '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]';

        $st = $email->add()->statement();

        $name = $balance = $number = null;

        $rootText = $this->htmlToText($this->http->FindHTMLByXpath('.', null, $root));

        /*
            Lynn | 28,200 points
            # 8324496291
            My Account
        */
        if (preg_match("/^\s*(?<name>{$patterns['travellerName']})\s*\|\s*(?<points>\d[,.\'\d ]*?)\s*points\s+#\s*(?<number>\d{5,})(?:[ ]*\||[ ]{2}|\n|$)/i", $rootText, $m)) {
            $name = $m['name'];
            $balance = $m['points'];
            $number = $m['number'];
        }

        /*
            Alexi Vereschaga
            # 7015375376  |  My Account
        */
        if (preg_match("/^\s*(?<name>{$patterns['travellerName']})\s+#\s*(?<number>\d{5,})(?:[ ]*\||[ ]{2}|\n|$)/", $rootText, $m)) {
            $name = $m['name'];
            $number = $m['number'];
        }

        $st->addProperty('Name', $name);
        $st->setNumber($number)->setLogin($number);

        if ($balance !== null) {
            $st->setBalance(str_replace(',', '', $balance));
        } elseif ($name || $number) {
            $st->setNoBalance(true);
        }

        $verificationCode = $this->http->FindSingleNode("//text()[contains(normalize-space(),'Use the code below to complete the verification request')]/following::text()[normalize-space()][1]", null, true, "/^\d{3,}$/");

        if ($verificationCode !== null) {
            // it-116517938.eml
            $otc = $email->add()->oneTimeCode();
            $otc->setCode($verificationCode);
        }

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@e-mail.amtrak.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        return $this->arrikey($headers['subject'], $this->reSubject) !== false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,".amtrak.com/") or contains(@href,"www.amtrak.com") or contains(@href,"e-mail.amtrak.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"Amtrak, Marketing Department") or contains(normalize-space(),"Amtrak Guest Rewards")]')->length === 0
        ) {
            return false;
        }

        return $this->findRoot()->length === 1;
    }

    public static function getEmailLanguages()
    {
        return [];
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function findRoot(): \DOMNodeList
    {
        return $this->http->XPath->query("//a[contains(normalize-space(),'My Account')]/ancestor::td[1]");
    }

    private function htmlToText(?string $s, bool $brConvert = true): string
    {
        if (!is_string($s) || $s === '') {
            return '';
        }
        $s = str_replace("\r", '', $s);
        $s = preg_replace('/<!--.*?-->/s', '', $s); // comments

        if ($brConvert) {
            $s = preg_replace('/\s+/', ' ', $s);
            $s = preg_replace('/<[Bb][Rr]\b.*?\/?>/', "\n", $s); // only <br> tags
        }
        $s = preg_replace('/<[A-z][A-z\d:]*\b.*?\/?>/', '', $s); // opening tags
        $s = preg_replace('/<\/[A-z][A-z\d:]*\b[ ]*>/', '', $s); // closing tags
        $s = html_entity_decode($s);
        $s = str_replace(chr(194) . chr(160), ' ', $s); // NBSP to SPACE

        return trim($s);
    }

    private function arrikey($haystack, array $arrayNeedle)
    {
        foreach ($arrayNeedle as $key => $needles) {
            if (is_array($needles)) {
                foreach ($needles as $needle) {
                    if (stripos($haystack, $needle) !== false) {
                        return $key;
                    }
                }
            } else {
                if (stripos($haystack, $needles) !== false) {
                    return $key;
                }
            }
        }

        return false;
    }
}
