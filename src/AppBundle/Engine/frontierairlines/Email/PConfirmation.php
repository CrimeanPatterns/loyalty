<?php

namespace AwardWallet\Engine\frontierairlines\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class PConfirmation extends \TAccountChecker
{
    public $mailFiles = "frontierairlines/it-137530300.eml, frontierairlines/it-37374473.eml, frontierairlines/it-59347339.eml";

    public $reFrom = ["@emails.flyfrontier.com"];
    public $reBody = [
        'en'  => ['Thank you for your purchase with us!', 'DEPARTING FLIGHT'],
        'en2' => ['BOOKING CANCELLATION', 'PURCHASE SUMMARY'],
        'en3' => ['FLIGHTS', 'PURCHASE SUMMARY'],
    ];
    public $reSubject = [
        '#Your Flight Confirmation Code [A-Z\d]{5,}$#',
        '#Your Booking Has Been Cancelled. Reference Code [A-Z\d]{5,}$#',
    ];
    public $lang = '';
    public $subject;
    public static $dict = [
        'en' => [
            'Depart'                            => 'Depart',
            'Arrive'                            => 'Arrive',
            'typePax'                           => ['ADULT', 'INFANT'],
            'fees'                              => ['Options', 'Memberships', 'Taxes and Carrier-Imposed Fees'],
            'Your booking has been cancelled'   => 'Your booking has been cancelled',
            'Your flight confirmation code is:' => ['Your flight confirmation code is:', 'For booking reference code:'],
        ],
    ];
    private $keywordProv = 'Frontier Airlines';

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug('can\'t determine a language');

            return $email;
        }

        $this->subject = $parser->getSubject();

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $this->parseEmail($email);

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[contains(normalize-space(),'your flight on Frontier Airlines') or contains(normalize-space(),'FRONTIER Miles')] | //a[contains(@href,'emails.flyfrontier.com')]")->length > 0
            && $this->detectBody($this->http->Response['body'])
        ) {
            return $this->assignLang();
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
                if (($fromProv || strpos($headers['subject'], $this->keywordProv) !== false)
                    && stripos($headers['subject'], $reSubject) !== false
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
        $r = $email->add()->flight();

        // passengers
        // recordLocator
        $confNo = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Your flight confirmation code is:'))}]/following::text()[normalize-space()!=''][1]");

        if (empty($confNo)) {
            if (preg_match("/Your Flight Confirmation Code\s*([\dA-Z]+)/", $this->subject, $m)) {
                $confNo = $m[1];
            }
        }

        $r->general()
            ->confirmation($confNo, $this->t('flight confirmation code'));

        if (!empty($this->http->FindSingleNode("//text()[" . $this->contains($this->t('Your booking has been cancelled')) . "]"))) {
            $r->general()
                ->status('Cancelled')
                ->cancelled()
            ;

            return;
        }
        $pax = array_filter($this->http->FindNodes("//text()[{$this->eq($this->t('PASSENGERS'))}]/ancestor::table[1]/following::table[1]/descendant::text()[{$this->contains($this->t('typePax'))}]/ancestor::table[1]/descendant::tr[1]/following-sibling::tr/descendant::p[normalize-space()!='']",
            null, "#^\s*\d+\s*\-\s*([[:alpha:]\- ]+)\s*$#"));

        if (empty($pax)) {
            $pax = $this->http->FindNodes("//text()[{$this->eq($this->t('PASSENGERS'))}]/ancestor::table[1]/following::table[1]/descendant::text()[{$this->contains($this->t('typePax'))}]/ancestor::table[1]/descendant::tr[1]/following-sibling::tr/descendant::text()[normalize-space()!=''][1]",
                null, "#(?:\d+\s*\-\s*)?(.+)#");
        }
        $r->general()
            ->travellers($pax, true);

        // accountNumbers
        $acc = array_filter($this->http->FindNodes("//text()[{$this->eq($this->t('PASSENGERS'))}]/ancestor::table[1]/following::table[1]/descendant::text()[{$this->starts($this->t('FRONTIER Miles'))}]/ancestor::tr[1]",
            null, "#\#: (\d+)#"));
        $r->program()
            ->accounts($acc, false);

        //spentAwards
        $spent = $this->http->FindSingleNode("//text()[normalize-space()='PAYMENT: Miles']/ancestor::tr[1]/following::tr[1]/descendant::text()[normalize-space()='Total']/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Total'))}\s*(\d+)$/");

        if (!empty($spent)) {
            $r->price()
                ->spentAwards($spent);
        }

        // Sums
        $rootTotal = $this->http->XPath->query("//text()[{$this->eq($this->t('PURCHASE TOTAL'))}]/following::table[1]");

        if ($rootTotal->length === 1) {
            $rootTotal = $rootTotal->item(0);

            $fees = (array) $this->t('fees');

            foreach ($fees as $fee) {
                $node = $this->nextTd($fee, $rootTotal);

                if (!empty($node)) {
                    $sum = $this->getTotalCurrency($node);
                    $r->price()
                        ->fee($fee, $sum['Total']);
                }
            }

            $node = $this->nextTd($this->t('Airfare'), $rootTotal);
            $sum = $this->getTotalCurrency($node);
            $r->price()
                ->cost($sum['Total']);

            $node = $this->nextTd($this->t('Total Discounts'), $rootTotal);

            if (!empty($node)) {
                $sum = $this->getTotalCurrency($node);
                $r->price()
                    ->discount($sum['Total']);
            }

            $node = $this->nextTd($this->t('Grand Total'), $rootTotal);
            $sum = $this->getTotalCurrency($node);
            $r->price()
                ->total($sum['Total'])
                ->currency($sum['Currency']);
        } else {
            $this->logger->debug('check collecting sums. other format');

            //return false;
        }

        // Segments
        $xpath = "//text()[({$this->starts($this->t('Depart'))}) and ({$this->contains($this->t('Arrive'))})]/ancestor::table[./preceding::text()[normalize-space()][1][contains(.,'FLIGHT')]][1]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $flights = [];
            $node = $this->http->FindSingleNode("./preceding::text()[normalize-space()!=''][1][{$this->contains($this->t('FLIGHT'))}]", $root);

            if (preg_match_all("#\b(\d+)\b#", $node, $m)) {
                $flights = $m[1];
            }
            $rootSegs = $this->http->XPath->query("./descendant::text()[({$this->starts($this->t('Depart'))}) and ({$this->contains($this->t('Arrive'))})]",
                $root);

            if ($rootSegs->length > 1) {
                $rootSegs = $this->http->XPath->query("./descendant::text()[({$this->starts($this->t('Depart'))}) and ({$this->contains($this->t('Arrive'))})][position()>1]",
                    $root);
            }
            $n = $this->http->FindSingleNode('descendant::tr[normalize-space(.)][1]', $root);
            $dir = '';

            if (preg_match('/.+\(([A-Z]{3})\) to .+ \(([A-Z]{3})\)/', $n, $m)) {
                $dir = $m[1] . ' ' . $this->t('to') . ' ' . $m[2];
            }
            $countSegs = $rootSegs->length;

            foreach ($rootSegs as $i => $rootSeg) {
                $s = $r->addSegment();
                $node = $this->http->FindSingleNode(".", $rootSeg);

                if (preg_match("#{$this->opt($this->t('Depart'))}:\s*(.+)\s+\|\s+{$this->opt($this->t('Arrive'))}:\s*(.+)#",
                    $node, $m)) {
                    $s->departure()->date($this->normalizeDate($m[1]));
                    $s->arrival()->date($this->normalizeDate($m[2]));
                }
                $node = $this->http->FindSingleNode("./preceding::text()[normalize-space()!=''][1]", $rootSeg);

                if (preg_match("#\b([A-Z]{3})\b.*\s+{$this->t('to')}\s+.*\b([A-Z]{3})\b#", $node, $m)) {
                    $s->departure()->code($m[1]);
                    $s->arrival()->code($m[2]);
                    $direction = $m[1] . ' ' . $this->t('to') . ' ' . $m[2];

                    $seats = array_filter($this->http->FindNodes("//text()[{$this->eq($this->t('SEATS'))}]/following::table[1]/descendant::text()[{$this->starts($this->t('SEATS'))}]/ancestor::table[1][{$this->contains($direction)}]/descendant::text()[{$this->starts($this->t('Seat Assignment'))}]",
                        null, "#{$this->opt($this->t('Seat Assignment'))}:\s+(\d+[A-z])#"));
                    // dirty hack
                    if (empty($seats) && !empty($dir) && 1 === count($flights) && 1 === $this->http->XPath->query('descendant::text()[contains(normalize-space(.), "Stop")][1]', $root)->length) {
                        $seats = array_filter($this->http->FindNodes("//text()[{$this->eq($this->t('SEATS'))}]/following::table[1]/descendant::text()[{$this->starts($this->t('SEATS'))}]/ancestor::table[1][{$this->contains($dir)}]/descendant::text()[{$this->starts($this->t('Seat Assignment'))}]", null, "#{$this->opt($this->t('Seat Assignment'))}:\s+(\d+[A-z])#"));
                    }

                    if (!empty($seats)) {
                        $s->extra()->seats($seats);
                    }
                }

                if (!isset($flights[$i]) && $countSegs > 1
                    && $this->http->XPath->query("descendant::text()[{$this->contains($this->t('Stop'))}][1]", $root)->length === 1
                ) {
                    if (count($flights) === 1) {
                        $flights[$i] = $flights[0];
                    } elseif (count($flights) > 1) {
                        $flights[$i] = $flights[count($flights) - 1];
                    }
                }

                if (isset($flights[$i])) {
                    $s->airline()
                        ->number($flights[$i]);

                    if ($this->http->XPath->query("//a[contains(@href,'flyfrontier.com')]")->length > 0) {
                        $s->airline()
                            ->name('F9');
                    }// Frontier Airlines https://www.iata.org/publications/pages/code-search.aspx
                    else {
                        $s->airline()
                            ->noName();
                    }
                }

                $node = $this->http->FindSingleNode("./following::text()[normalize-space()!=''][1][{$this->starts($this->t('Total Duration'))}]",
                    $rootSeg, false, "#{$this->opt($this->t('Total Duration'))}:\s+(.+)#");

                if (!empty($node)) {
                    $s->extra()->duration($node);
                }
            }
        }
    }

    private function nextTd($field, $root = null): ?string
    {
        return $this->http->FindSingleNode(
            "./descendant::text()[{$this->eq($field)}]/ancestor::td[1]/following-sibling::td[normalize-space()!=''][1]",
            $root
        );
    }

    private function normalizeDate($date)
    {
        $in = [
            //  6/14/2019 4:37 PM
            '#^\s*(\d+)\/(\d+)\/(\d{4})\s+(\d+:\d+(?:\s*[ap]m)?)\s*$#ui',
        ];
        $out = [
            '$3-$1-$2 $4',
        ];
        $str = strtotime($this->dateStringToEnglish(preg_replace($in, $out, $date)));

        return $str;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function detectBody($body): bool
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                    $this->lang = substr($lang, 0, 2);

                    return true;
                }
            }
        }

        return false;
    }

    private function assignLang(): bool
    {
        foreach (self::$dict as $lang => $words) {
            if (isset($words['Depart'], $words['Arrive'])) {
                if ($this->http->XPath->query("//*[{$this->contains($words['Depart'])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($words['Arrive'])}]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }

            if (isset($words['Your booking has been cancelled']) && $this->http->XPath->query("//*[{$this->contains($words['Your booking has been cancelled'])}]")->length > 0) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function getTotalCurrency($node): array
    {
        $node = str_replace("€", "EUR", $node);
        $node = str_replace("$", "USD", $node);
        $node = str_replace("£", "GBP", $node);
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)
            || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m)
        ) {
            $cur = $m['c'];
            $currencyCode = preg_match('/^[A-Z]{3}$/', $cur) ? $cur : null;
            $tot = PriceHelper::parse($m['t'], $currencyCode);
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
}
