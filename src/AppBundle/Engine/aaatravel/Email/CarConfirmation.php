<?php

namespace AwardWallet\Engine\aaatravel\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class CarConfirmation extends \TAccountChecker
{
    public $mailFiles = "aaatravel/it-42562170.eml";

    public $reFrom = ["@aaaclubpartners.com", "@tstllc.net", "@aaatravelsupport.com"];
    public $reBody = [
        'en' => ['Thank you for booking with AAA Travel'],
    ];
    public $reSubject = [
        'AAA Travel Car Confirmation',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
            'Pickup:'        => 'Pickup:',
            'Drop-Off:'      => 'Drop-Off:',
            'Traveler'       => ['Traveler', 'Traveller'],
            'Hours:'         => ['Hours:', 'Hours :'],
            'Base Cost Rate' => ['Base Cost Rate', 'Daily Rate'],
        ],
    ];
    private $keywordProv = 'AAA Travel';
    private $keywords = [
        'dollar' => [
            'Dollar',
        ],
        'hertz' => [
            'Hertz',
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug('can\'t determine a language');

            return $email;
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $this->parseEmail($email);

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//img[contains(@src,'.aaa.com') or contains(@src,'.tstlls.net')] | //a[contains(@href,'.aaa.com') or contains(@href,'.tstlls.net') or contains(@href,'aaatravelsupport.com')]")->length > 0) {
            foreach ($this->reBody as $lang => $reBody) {
                if ($this->http->XPath->query("//*[{$this->contains($reBody)}]")->length > 0) {
                    return $this->assignLang();
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->reFrom as $reFrom) {
            if (stripos($from, $reFrom) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        $fromProv = true;

        if (!isset($headers['from']) || self::detectEmailFromProvider($headers['from']) === false) {
            $fromProv = false;
        }

        if (isset($headers["subject"])) {
            foreach ($this->reSubject as $reSubject) {
                if (($fromProv || preg_match("#\b{$this->opt($this->keywordProv)}\b#i", $headers["subject"]) > 0)
                    && stripos($headers["subject"], $reSubject) !== false
                ) {
                    return true;
                }
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

    private function parseEmail(Email $email)
    {
        $xpathHeader = "//text()[{$this->starts($this->t('Status'))}]/ancestor::tr[1]/following-sibling::tr[1][{$this->starts($this->t('Confirmation'))}]";
        $nodesHeader = $this->http->XPath->query($xpathHeader);

        $xpathDetails = "//text()[{$this->eq($this->t('Pickup:'))}]/ancestor::table[{$this->contains($this->t('Drop-Off:'))}][1]";
        $nodesDetails = $this->http->XPath->query($xpathDetails);
        // check format
        if ($nodesHeader->length !== $nodesDetails->length) {
            $this->logger->debug("other format");

            return false;
        }
        // parsing
        $phoneOta = $this->http->FindSingleNode("//text()[{$this->contains($this->t('please contact Customer Support'))}]/ancestor::*[contains(.,'or ')][1]",
            null, false, "#\b[\d\+\-\(\) ]+\.?\s*$#");

        foreach ($nodesDetails as $root) {
            $rootHead = null;
            $r = $email->add()->rental();
            $confNo = $this->http->FindSingleNode("./following-sibling::*[normalize-space()!=''][1]/descendant::text()[{$this->contains($this->t('Confirmation Number'))}]/following::text()[normalize-space()!=''][1]",
                $root);

            foreach ($nodesHeader as $rr) {
                if ($confNo === $this->http->FindSingleNode("./td[2]", $rr)) {
                    $rootHead = $rr;

                    break;
                }
            }

            if (!isset($rootHead)) {
                $this->logger->debug("check format Header & Details");

                return false;
            }

            $r->general()
                ->status($this->http->FindSingleNode("./preceding-sibling::tr[1]/descendant::td[2]", $rootHead))
                ->confirmation($this->http->FindSingleNode("./td[2]", $rootHead), $this->t('Confirmation'))
                ->travellers($this->http->FindNodes("./following-sibling::table[./preceding::text()[normalize-space()!=''][{$this->contains($this->t('Traveler'))}]]/descendant::text()[{$this->starts($this->t('Name'))}]/ancestor::td[1]/following-sibling::td[1]",
                    $root), true);

            $confNoOta = $this->http->FindSingleNode("./following-sibling::tr[1][{$this->starts($this->t('Booking Reference'))}]/descendant::td[2]",
                $rootHead);

            if (!empty($confNoOta)) {
                $r->ota()
                    ->confirmation($confNoOta, $this->t('Booking Reference'));
            }
            $r->ota()
                ->phone($phoneOta, $this->t('please contact Customer Support'));

            $r->pickup()
                ->date(strtotime($this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Date:'))}][1]/ancestor::td[1]/following-sibling::td[1]",
                    $root)))
                ->location($this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Location:'))}][1]/ancestor::td[1]/following-sibling::td[1]",
                    $root))
                ->phone($this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Phone:'))}][1]/ancestor::td[1]/following-sibling::td[1]",
                    $root))
                ->openingHours($this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Hours:'))}][1]/ancestor::td[1]/following-sibling::td[1]",
                    $root));
            $r->dropoff()
                ->date(strtotime($this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Date:'))}][2]/ancestor::td[1]/following-sibling::td[1]",
                    $root)));
            $dropoff = $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Location:'))}][2]/ancestor::td[1]/following-sibling::td[1]",
                $root);

            if (preg_match("#^{$this->opt($this->t('Same as Pickup'))}$#i", $dropoff)) {
                $r->dropoff()->same();
            }
            $r->car()
                ->image($this->http->FindSingleNode(".//img[1]/@src", $root))
                ->type($this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Drop-Off:'))}]/ancestor::tr[./following-sibling::tr][2]/following-sibling::tr",
                    $root))
                ->model($this->http->FindSingleNode("./preceding::text()[normalize-space()!=''][1]", $root));

            $keyword = trim($this->http->FindSingleNode("./preceding::text()[normalize-space()!=''][2]", $root), ":");
            $provider = $this->getRentalProviderByKeyword($keyword);

            if (null !== $provider) {
                $r->setProviderCode($provider);
            } elseif (!empty($keyword)) {
                $r->setProviderKeyword($keyword);
            }
        }
        // collect sums
        $sum = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Payment Summary'))}]/following::text()[normalize-space()!=''][1]");
        $tax = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Taxes & Fees'))}]/following::text()[normalize-space()!=''][1]");
        $cost = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Base Cost Rate'))}]/ancestor::*[self::td or self::th][1]/following-sibling::*[self::td or self::th][1]/descendant::text()[normalize-space()!=''][last()]");

        if (!empty($sum)) {
            $sum = $this->getTotalCurrency($sum);
            $tax = $this->getTotalCurrency($tax);
            $cost = $this->getTotalCurrency($cost);

            if (count($email->getItineraries()) === 1 && isset($r)) {
                $r->price()
                    ->total($sum['Total'])
                    ->currency($sum['Currency']);

                if (!empty($cost['Total'])) {
                    $r->price()->cost($cost['Total']);
                }

                if (!empty($tax['Total'])) {
                    $r->price()->tax($tax['Total']);
                }
            }
            $email->price()
                ->total($sum['Total'])
                ->currency($sum['Currency']);

            if (!empty($cost['Total'])) {
                $email->price()->cost($cost['Total']);
            }

            if (!empty($tax['Total'])) {
                $email->price()->tax($tax['Total']);
            }
        }

        return true;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang()
    {
        foreach (self::$dict as $lang => $words) {
            if (isset($words['Drop-Off:'], $words['Pickup:'])) {
                if ($this->http->XPath->query("//*[{$this->contains($words['Drop-Off:'])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($words['Pickup:'])}]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function getTotalCurrency($node)
    {
        $node = str_replace(["€", "£", "$", "₹"], ["EUR", "GBP", "USD", "INR"], $node);
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)
            || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m)
        ) {
            $cur = $m['c'];
            $tot = PriceHelper::cost($m['t']);
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function getRentalProviderByKeyword(string $keyword)
    {
        if (!empty($keyword)) {
            foreach ($this->keywords as $code => $kws) {
                if (in_array($keyword, $kws)) {
                    return $code;
                } else {
                    foreach ($kws as $kw) {
                        if (strpos($keyword, $kw) !== false) {
                            return $code;
                        }
                    }
                }
            }
        }

        return null;
    }
}
