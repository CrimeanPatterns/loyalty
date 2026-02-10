<?php

namespace AwardWallet\Engine\aaatravel\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class CarConfirmation2 extends \TAccountChecker
{
    public $mailFiles = "aaatravel/it-61484550.eml, aaatravel/it-62870624.eml, aaatravel/it-86681022.eml, aaatravel/it-94649337.eml";

    public $reFrom = ["@tstllc.net"];
    public $reBody = [
        'en' => [
            'Thank you for booking with AAA',
            'Your booking has been cancelled',
            'The AAA Digital Tourbook has the same',
        ],
    ];
    public $reSubject = [
        'AAA Travel Car Confirmation #',
        'AAA Travel Car Booking Cancelled Confirmation #',
    ];
    public $lang = 'en';
    public static $dict = [
        'en' => [
            'Pickup:'          => ['Pickup:', 'Pick-up Location'],
            'Drop-Off:'        => ['Drop-Off:', 'Drop-off Location'],
            'Car: Dollar:'     => ['Car: Dollar:', 'Car: Thrifty:', 'Car: Enterprise:', 'Car: Hertz:'],
            'cancelledPhrases' => ['Your booking has been cancelled', 'Your booking has been canceled'],
        ],
    ];
    private $keywordProv = 'AAA Travel';
    private $keywords = [
        'dollar' => [
            'Dollar',
        ],
        'rentacar' => [
            'Enterprise',
        ],
        'hertz' => [
            'Hertz',
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
//        if (!$this->assignLang()) {
//            $this->logger->debug('can\'t determine a language');
//            return $email;
//        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $this->parseEmail($email);

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//img[contains(@src,'.aaa.com') or contains(@src,'.tstlls.net') or contains(@src,'wss-4CAAA')] 
        | //a[contains(@href,'.aaa.com') or contains(@href,'.tstlls.net') or contains(@href,'aaatravelsupport.com')]")->length > 0) {
            foreach ($this->reBody as $reBody) {
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

        if (!isset($headers['from']) || $this->detectEmailFromProvider($headers['from']) !== true) {
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

    private function parseEmail(Email $email): void
    {
        $patterns = [
            'phone' => '[+(\d][-. \d)(]{5,}[\d)]', // +377 (93) 15 48 52    |    713.680.2992
        ];

        $xpath = "//text()[{$this->starts($this->t('Car: Dollar:'))}]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->count() > 1) {
            $total = $this->getTotalCurrency($this->http->FindSingleNode("//text()[{$this->eq($this->t('Total'))}]/following::text()[normalize-space()!=''][1]"));

            if ($total['Total'] !== null) {
                $email->price()
                    ->total($total['Total'])
                    ->currency($total['Currency']);
            }
        }

        $segNumber = 1;

        foreach ($nodes as $root) {
            $otaConfirmation = $this->http->FindSingleNode("./following::text()[{$this->eq($this->t('Reference #'))}][1]/following::text()[normalize-space()][1]", $root);

            if (!empty($otaConfirmation)) {
                $email->ota()
                    ->confirmation($otaConfirmation);
            }

            $r = $email->add()->rental();

            // it-94649337.eml
            if ($this->http->XPath->query("//h1[{$this->eq($this->t('cancelledPhrases'))}]")->length > 0) {
                $r->general()->cancelled();
            }

            // General
            $confirmation = $this->http->FindSingleNode("./following::text()[{$this->eq($this->t('Confirmation #'))}][1]/following::text()[normalize-space()][1]", $root);

            if (!empty($confirmation)) {
                $r->general()
                    ->confirmation($confirmation);
            } else {
                $r->general()
                    ->noConfirmation();
            }

            $status = $this->http->FindSingleNode("./following::tr[*[1][{$this->eq($this->t('Confirmation #'))}]][1]/preceding-sibling::tr[1]/descendant-or-self::tr[not(.//tr)][1]/*[2]", $root);

            if (empty($status)) {
                $status = $this->http->FindSingleNode("./ancestor::tr[1]/descendant::td[2]", $root);
            }

            if (!empty($status)) {
                $r->general()
                    ->status($status);
            }

            $r->general()
                ->traveller($this->http->FindSingleNode("./following::text()[{$this->eq($this->t('Driver'))}][1]/following::text()[normalize-space()][1]", $root), true);

            // Phone: 866-434-2226 Open 24 Hours
            $patterns['phoneHours'] = "/Phone[:\s]+(?<phone>{$patterns['phone']})\s*(?<hours>.{2,})$/";

            $pickUpPhone = $pickUpHours = null;
            $pickUpInfo = $this->http->FindSingleNode("(following::tr/*[{$this->eq($this->t('Location Info'))}])[1]/following-sibling::*[normalize-space()]", $root);

            if (preg_match($patterns['phoneHours'], $pickUpInfo, $m)) {
                $pickUpPhone = $m['phone'];
                $pickUpHours = $m['hours'];
            }

            $r->pickup()
                ->date(strtotime($this->http->FindSingleNode("./following::text()[{$this->eq($this->t('Pick-up Time'))}][1]/following::text()[normalize-space()][1]", $root)))
                ->location($this->http->FindSingleNode("./following::text()[{$this->eq($this->t('Pick-up Location'))}][1]/following::text()[normalize-space()][1]", $root))
                ->phone($pickUpPhone)
                ->openingHours($pickUpHours)
            ;

            $r->dropoff()
                ->date(strtotime($this->http->FindSingleNode("./following::text()[{$this->eq($this->t('Drop-off Time'))}][1]/following::text()[normalize-space()][1]", $root)));

            $dropoff = $this->http->FindSingleNode("./following::text()[{$this->eq($this->t('Drop-off Location'))}][1]/following::text()[normalize-space()][1]", $root);

            if (preg_match("#^{$this->opt($this->t('Same as pickup'))}$#i", $dropoff)) {
                $r->dropoff()->same();
            } else {
                $dropOffPhone = $dropOffHours = null;
                $dropOffInfo = $this->http->FindSingleNode("(following::tr/*[{$this->eq($this->t('Drop-off Info'))}])[1]/following-sibling::*[normalize-space()]", $root);

                if (preg_match($patterns['phoneHours'], $dropOffInfo, $m)) {
                    $dropOffPhone = $m['phone'];
                    $dropOffHours = $m['hours'];
                }
                $r->dropoff()
                    ->date(strtotime($this->http->FindSingleNode("./following::text()[{$this->eq($this->t('Drop-off Time'))}][1]/following::text()[normalize-space()][1]", $root)))
                    ->location($this->http->FindSingleNode("./following::text()[{$this->eq($this->t('Drop-Off:'))}][1]/following::text()[normalize-space()][1]", $root))
                    ->phone($dropOffPhone)
                    ->openingHours($dropOffHours)
                ;
            }

            $r->car()
                ->type($this->http->FindSingleNode("./following::text()[{$this->eq($this->t('Car Features'))}][1]/following::text()[normalize-space()][1]", $root));

            $keyword = $this->http->FindSingleNode("./following::tr[*[1][{$this->eq($this->t('Confirmation #'))}]]/preceding-sibling::tr[1]/descendant-or-self::tr[not(.//tr)][1]/*[1]", $root, true, "#Car:\s*(.+?):#");
            $provider = $this->getRentalProviderByKeyword($keyword);

            if (null !== $provider) {
                $r->setProviderCode($provider);
            } elseif (!empty($keyword)) {
                $r->extra()->company($keyword);
            }

            // collect sums
            $total = $this->getTotalCurrency($this->http->FindSingleNode("./following::text()[{$this->starts($this->t('Balance due'))}][$segNumber]/following::text()[normalize-space()!=''][1]", $root));

            if ($total['Total'] !== null) {
                $r->price()
                    ->total($total['Total'])
                    ->currency($total['Currency']);
            }

            $tax = $this->getTotalCurrency($this->http->FindSingleNode("./following::text()[{$this->eq($this->t('Taxes & Fees'))}][{$segNumber}]/following::text()[normalize-space()!=''][1]", $root));

            if ($tax['Total'] !== null) {
                $r->price()
                    ->tax($tax['Total']);
            }
            $cost = $this->getTotalCurrency($this->http->FindSingleNode("./following::text()[{$this->eq($this->t('Base Cost Rate'))}][{$segNumber}]/ancestor::*[self::td or self::th][1]/following-sibling::*[self::td or self::th][1]/descendant::text()[normalize-space()!=''][last()]", $root));

            if ($cost['Total'] !== null) {
                $r->price()
                    ->cost($cost['Total']);
            }

            $segNumber++;
        }

        $mebershipNumber = $this->http->FindSingleNode("//tr[starts-with(normalize-space(), 'Membership Number')]/descendant::td[1]", null, true, "/^(\d{15,})$/");

        if (!empty($mebershipNumber)) {
            $r->program()->account($mebershipNumber, false);
        }
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang(): bool
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

    private function getTotalCurrency($node): array
    {
        $node = str_replace(["€", "£", "$", "₹"], ["EUR", "GBP", "USD", "INR"], $node);
        $tot = null;
        $cur = null;

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

        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function getRentalProviderByKeyword(?string $keyword): ?string
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
