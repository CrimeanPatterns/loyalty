<?php

namespace AwardWallet\Engine\expedia\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class TravelConfirmationRental extends \TAccountChecker
{
    public $mailFiles = "expedia/it-305720129.eml, expedia/it-364657875.eml, expedia/it-667275046.eml, expedia/it-672825790.eml, expedia/it-672828262.eml";
    private $detectLang = [
        'en' => ['Traveler Details', 'Refund Summary', 'Price details'],
    ];
    private $subjects = [
        // en
        'Expedia car rental confirmation - ',
    ];
    private $lang = '';
    private $date;

    private static $dictionary = [
        'en' => [
            'confNoInBody'     => ['Itinerary #', 'Expedia itinerary:'],
            'confNoInSubject'  => ['Itinerary'],
            'confNoByCompany'  => [' confirmation:'],
            'Traveler Details' => ['Traveler Details'],
            'Pick up'          => ['Pick up', 'Pick-up'],
            'Drop off'         => ['Drop off', 'Drop-off'],
            'Taxes & fees'     => ['Taxes & fees', 'Taxes and fees'],
            'Price summary'    => ['Price summary', 'Price details'],
            'All set,'         => ['All set,', 'Thank you,'],
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getDate());

        foreach ($this->detectLang as $lang => $dict) {
            if ($this->http->XPath->query("//text()[{$this->contains($dict)}]")->length > 0
            ) {
                $this->lang = $lang;

                break;
            }
        }

        $this->detectProv();

        if (!empty($this->provCode)) {
            $email->setProviderCode($this->provCode);
        }

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        $a = explode('\\', __CLASS__);
        $class = end($a);
        $email->setType($class . ucfirst($this->lang));

        $carRoots = $this->http->XPath->query("//text()[{$this->eq($this->t('Pick up'))}]/ancestor::*[ descendant::text()[{$this->starts($this->t('confNoInBody'))}] ][1]");

        if ($carRoots->length > 1) {
            // it-364657875.eml
            foreach ($carRoots as $root) {
                $this->parseCar($email, $root, $parser->getHeader('subject'));
            }
        } else {
            $this->parseCar($email, null, $parser->getHeader('subject'));
        }

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//img[contains(@alt,'Expedia') or contains(@alt,'expedia')] | //a[contains(@href,'expedia')]")->length === 0
            && $this->http->XPath->query("//*[contains(normalize-space(),'Expedia, Inc. All rights reserved') or contains(.,'@expediamail.com') or contains(.,'expedia.com')]")->length === 0
            && $this->detectProv() == false
        ) {
            return false;
        }

        if ($this->http->XPath->query("//img[contains(@src,'Icon_hotels') or (contains(@src,'flight') and contains(@src,'icon'))]")->length > 0) {
            // maybe go FlightItinerary
            return false;
        }

        foreach (self::$dictionary as $dict) {
            if (!empty($dict['Traveler Details']) && !empty($dict['Pick up']) && !empty($dict['Drop off'])
                && $this->http->XPath->query("//div[.//text()[normalize-space()][1][{$this->eq($dict['Traveler Details'])}]]"
                    . "/following-sibling::div[.//text()[normalize-space()][1][{$this->eq($dict['Pick up'])}]]"
                    . "/following-sibling::div[.//text()[normalize-space()][1][{$this->eq($dict['Drop off'])}]]")->length > 0
            ) {
                return true;
            }
        }

        if ($this->http->XPath->query("//text()[normalize-space()='View full itinerary']/following::text()[normalize-space()='Pick-up and drop-off']")->length > 0) {
            return true;
        }

        if ($this->http->XPath->query("//text()[{$this->contains('your car reservation was canceled')}]")->length > 0
        && $this->http->XPath->query("//text()[{$this->contains('View canceled reservation')}]")->length > 0) {
            return true;
        }

        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'Expedia')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('View full itinerary'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Free cancellation up until pick-up'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Price details'))}]")->length > 0) {
            return true;
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->subjects as $dsubject) {
            if (strpos($headers['subject'], $dsubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '.expedia.com') !== false;
    }

    public function detectProv()
    {
        if ($this->http->XPath->query("//*[contains(normalize-space(),'Hotels.com') and contains(normalize-space(), 'app')]")->length > 0) {
            $this->provCode = 'hotels';

            return true;
        } elseif ($this->http->XPath->query("//img[contains(@alt,'Expedia') or contains(@alt,'expedia')] | //a[contains(@href,'expedia')]")->length > 0
            || $this->http->XPath->query("//*[contains(normalize-space(),'Expedia, Inc. All rights reserved') or contains(.,'@expediamail.com')]")->length > 0) {
            $this->provCode = 'expedia';

            return true;
        }

        return false;
    }

    public static function getEmailProviders()
    {
        return ['expedia', 'orbitz', 'hotels'];
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function parseCar(Email $email, ?\DOMNode $root = null, $subject): void
    {
        // Rental
        $r = $email->add()->rental();

        // Travel Agency
        $confNo = null;
        $confNoTitle = 'Itinerary #';

        if (preg_match("/({$this->opt($this->t('confNoInBody'))})[ ]*(\d+)$/",
            $this->http->FindSingleNode("descendant::text()[{$this->starts($this->t('confNoInBody'))}][1]", $root), $m)
        ) {
            $confNo = $m[2];
            $confNoTitle = $m[1];
        }

        if (empty($confNo)) {
            $confNo = $this->http->FindSingleNode("descendant::text()[{$this->starts($this->t('confNoInBody'))}][1]/following::text()[normalize-space()][1]", $root, false, '/^\d{5,}$/');
            $confNoTitle = $this->http->FindSingleNode("descendant::text()[{$this->starts($this->t('confNoInBody'))}][1]", $root, true, '/^(.+?)[\s:]*$/');
        }

        if (empty($confNo)
            && preg_match("/({$this->opt($this->t('confNoInSubject'))}\s*[#.:]*)[-:\s]*([-A-Z\d]{5,})\b/", $subject, $m)
        ) {
            $confNo = $m[2];
            $confNoTitle = rtrim($m[1], ': ');
        }

        if (!empty($confNo)) {
            $r->ota()->confirmation($confNo, $confNoTitle);
        }

        $nodes = $this->http->FindNodes("descendant::text()[{$this->starts($this->t('You earned'))}][1]", $root);
        $earned = [];

        foreach ($nodes as $node) {
            if (preg_match("#{$this->opt($this->t('You earned'))} (\d+ {$this->opt($this->t('Expedia Rewards points'))})#", $node, $m)) {
                $earned[] = $m[1];
            }
        }

        if (count($earned) == 1) {
            $r->ota()->earnedAwards($earned[0]);
        }

        if ($this->http->XPath->query("//text()[{$this->contains('your car reservation was canceled')}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains('View canceled reservation')}]")->length > 0) {
            $r->general()
                ->cancelled();

            return;
        }

        // General
        if (preg_match("/({$this->opt($this->t('confNoByCompany'))})[ ]*([\d\-]+)$/",
            $this->http->FindSingleNode("descendant::text()[{$this->contains($this->t('confNoByCompany'))}][1]", $root), $m)
        ) {
            $r->general()->confirmation($m[2], $m[1]);
        } else {
            $r->general()
                ->noConfirmation();
        }

        $traveller = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Traveler Details'))}]/following::text()[normalize-space()][1]", $root);

        if (empty($traveller)) {
            $traveller = $this->http->FindSingleNode("//text()[{$this->starts($this->t('All set,'))}]", null, true, "/{$this->opt($this->t('All set,'))}\s*([[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])(?:\.|\!)\s/");
        }

        if (!empty($traveller)) {
            $r->general()->traveller($traveller);
        }

        if ($this->http->XPath->query("descendant::text()[contains(normalize-space(), 'Pick-up and drop-off')]", $root)->length > 0) {
            $dateRange = explode(' - ', $this->http->FindSingleNode("descendant::text()[normalize-space()='Pick-up and drop-off']/following::text()[normalize-space()][1][contains(normalize-space(), 'am') or contains(normalize-space(), 'pm')]", $root));
            $r->pickup()
                ->location($this->http->FindSingleNode("descendant::text()[normalize-space()='Pick-up and drop-off']/following::text()[normalize-space()][2]", $root))
                ->date($this->normalizeDate($dateRange[0]));

            $r->dropoff()
                ->date($this->normalizeDate($dateRange[1]))
                ->same();
        } else {
            $re = "/^\s*.+\n(?<address>[\s\S]+?)\n\s*{$this->opt($this->t('Hours of operation:'))}\s*(?<hours>.+)\n+(?<date>.+,\s*\d{1,2}:\d{2}.*)\s*$/s";
            $re2 = "/^.+\n(?<date>.+a?p?m)\n(?<address>.+)$/s";
            // PickUp
            $text = implode("\n", $this->http->FindNodes("descendant::text()[{$this->eq($this->t('Pick up'))}]/ancestor::*[not({$this->eq($this->t('Pick up'))})][1]//text()[normalize-space()]", $root));

            if (preg_match("/(?<pickUp>{$this->opt($this->t('Pick up'))}\s*(?:.+\n){1,3}){$this->opt($this->t('Drop off'))}/", $text, $m)) {
                $text = $m['pickUp'];
            }

            if (preg_match($re, $text, $m) || preg_match($re2, $text, $m)) {
                $r->pickup()
                    ->location(str_replace("\n", " ", $m['address']))
                    ->date($this->normalizeDate($m['date']));

                if (isset($m['hours']) && !empty($m['hours'])) {
                    $r->pickup()
                        ->openingHours($m['hours']);
                }
            }

            // DropOff
            $text = implode("\n", $this->http->FindNodes("descendant::text()[{$this->eq($this->t('Drop off'))}]/ancestor::*[not({$this->eq($this->t('Drop off'))})][1]//text()[normalize-space()]", $root));

            if (preg_match("/(?<dropOff>{$this->opt($this->t('Drop off'))}\s*(?:.+\n){1,4}){$this->opt($this->t('Free cancellation up until pick-up'))}/", $text, $m)) {
                $text = $m['dropOff'];
            }

            if (preg_match($re, $text, $m) || preg_match($re2, $text, $m)) {
                $r->dropoff()
                    ->location(str_replace("\n", " ", $m['address']))
                    ->date($this->normalizeDate($m['date']));

                if (isset($m['hours']) && !empty($m['hours'])) {
                    $r->dropoff()
                        ->openingHours($m['hours']);
                }
            }
        }

        $company = '';

        if ($r->getNoConfirmationNumber() === true) {
            $company = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Pick up'))}]/preceding::text()[normalize-space()][1]", $root);
        } else {
            $company = $this->http->FindSingleNode("descendant::text()[{$this->starts($this->t('confNoInBody'))}]/preceding::text()[normalize-space()][1]");
        }

        if (!empty($company)) {
            $r->setCompany($company);
        }

        // Car
        $model = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Car details'))}]/following::text()[normalize-space()][1]", $root);

        if (empty($model)) {
            $model = $this->http->FindSingleNode("descendant::img[contains(@src, 'car_color')]/following::text()[normalize-space()][2][contains(normalize-space(), 'or similar')]");
        }

        if (empty($model)) {
            $model = $this->http->FindSingleNode("descendant::text()[contains(normalize-space(), 'or similar')]", $root);
        }

        $r->car()
            ->model($model);

        $typeCar = $this->http->FindSingleNode("descendant::img[contains(@src, 'car_color')]/following::text()[normalize-space()][1]");

        if (empty($typeCar)) {
            $typeCar = $this->http->FindSingleNode("descendant::text()[contains(normalize-space(), 'or similar')]/preceding::text()[string-length()>2][1]", $root);
        }

        if (trim($typeCar) === 'Car details' || $r->getCompany() === trim($typeCar)) {
            $typeCar = $this->http->FindSingleNode("descendant::text()[contains(normalize-space(), 'or similar')]/following::text()[string-length()>2][1]", $root);
        }

        if (!empty($typeCar)) {
            $r->car()
                ->type($typeCar);
        }

        if (!empty($r->getCompany()) && $r->getNoConfirmationNumber() !== true) {
            $img = $this->http->FindSingleNode("descendant::text()[{$this->eq($r->getCompany())}]/preceding::*[contains(@src, 'https')][1]/@src", $root);

            if (!empty($img)) {
                $r->setCarImageUrl($img);
            }
        }

        // Price
        $cost = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Price summary'))}]/following::text()[normalize-space()][position()<12][{$this->eq($this->t('Base price'))}]/following::text()[normalize-space()][1]", $root);

        if (preg_match("#^\s*(?<currency>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $cost, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d\s]{1,5})\s*$#", $cost, $m)) {
            $r->price()
                ->cost(PriceHelper::parse($m['amount'], $this->normalizeCurrency($m['currency'])))
            ;
        }

        $taxes = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Price summary'))}]/following::text()[normalize-space()][position()<13][{$this->eq($this->t('Taxes & fees'))}]/following::text()[normalize-space()][1]", $root);

        if (preg_match("#^\s*(?<currency>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $taxes, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d\s]{1,5})\s*$#", $taxes, $m)) {
            $r->price()
                ->tax(PriceHelper::parse($m['amount'], $this->normalizeCurrency($m['currency'])))
            ;
        }

        $total = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Price summary'))}]/following::text()[normalize-space()][position()<15][{$this->eq($this->t('Total'))}]/following::text()[normalize-space()][1]", $root);

        if (preg_match("#^\s*(?<currency>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $total, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d\s]{1,5})\s*$#", $total, $m)
        ) {
            $currency = $this->normalizeCurrency($m['currency']);
            $r->price()->currency($currency)->total(PriceHelper::parse($m['amount'], $currency));
        }
    }

    private function normalizeDate($date)
    {
//        $this->logger->debug('$date = '.print_r( $date,true));

        if (empty($date)) {
            return null;
        }
        $year = date('Y', $this->date);

        if ($year < 2010) {
            $year = '';
        }
        $res = [
            // Thu, Apr 20, 12:15pm
            '/^\s*(?<week>[-[:alpha:]]+)\.?,\s*(?<month>[[:alpha:]]{3,})[.\s]+(?<day>\d{1,2})\s*,\s*(?<time>\d{1,2}:\d{2}(?:\s*[ap]m)?)$/ui',
        ];

        $resultDate = null;

        foreach ($res as $re) {
            if (preg_match($re, $date, $m)) {
                if (!empty($m['month'])) {
                    $m['month'] = MonthTranslate::translate($m['month'], $this->lang);
                }

                if (!empty($m['week'])) {
                    $m['week'] = WeekTranslate::translate($m['week'], $this->lang);
                }

                if (!empty($m['week']) && !empty($m['month']) && !empty($m['day']) && !empty($year)) {
                    $d = $m['day'] . ' ' . $m['month'] . ' ' . $year;
                    $weeknum = WeekTranslate::number1($m['week']);
                    $resultDate = EmailDateHelper::parseDateUsingWeekDay($d, $weeknum);
                }

                if (!empty($m['time']) && !empty($resultDate)) {
                    $resultDate = strtotime($m['time'], $resultDate);
                }

                break;
            }
        }

        return $resultDate;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dictionary[$this->lang][$s])) {
            return $s;
        }

        return self::$dictionary[$this->lang][$s];
    }

    private function normalizeCurrency(string $string): string
    {
        $string = trim($string);
        $currencies = [
            // do not add unused currency!
            'CAD' => ['$ CA', 'CA $'],
            'BRL' => ['R$'],
            'AUD' => ['AU$'],
            'MXN' => ['MXN$'],
            'NZD' => ['NZ$'],
            'KRW' => ['₩'],
        ];

        foreach ($currencies as $currencyCode => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $currencyCode;
                }
            }
        }

        return $string;
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'normalize-space(' . $node . ')=' . $s;
        }, $field)) . ')';
    }

    private function starts($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'starts-with(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'contains(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }
}
