<?php

namespace AwardWallet\Engine\capitalcards\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

// TODO: merge with parser hopper/BookingConfirmation (in favor of hopper/BookingConfirmation)

class VehicleDetails extends \TAccountChecker
{
    public $mailFiles = "capitalcards/it-121695036.eml, capitalcards/it-149893120.eml, capitalcards/it-589330136.eml";
    public $subjects = [
        'View your driver and vehicle details for your trip', 'Your rental car reservation details',
        "You've cancelled your rental car reservation", "You've canceled your rental car reservation",
    ];

    public $lang = 'en';
    public $currentDate;

    public static $dictionary = [
        "en" => [
            'btnText'          => ['Manage Your Trip', 'View Trip'],
            'statusVariants'   => ['Confirmed', 'CONFIRMED', 'Cancelled', 'CANCELLED', 'Canceled', 'CANCELED'],
            'cancelledPhrases' => ['You canceled your rental car reservation.'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@capitalonebooking.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (stripos($headers['subject'], $subject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[contains(normalize-space(),'Capital One')]")->length > 0) {
            return $this->http->XPath->query("//text()[{$this->contains($this->t('Pick-up'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Drop-off'))}]")->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]capitalonebooking.com$/', $from) > 0;
    }

    public function ParseEmail(Email $email): void
    {
        $patterns = [
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        ];

        $xpathBold = '(self::b or self::strong or ancestor-or-self::*[contains(@style,"bold") or contains(@style,"600")])';

        $r = $email->add()->rental();

        if ($this->http->XPath->query("//*[{$this->contains($this->t('cancelledPhrases'))}]")->length > 0) {
            // it-149893120.eml
            $r->general()->cancelled();

            $xpathHeadTable = "//tr/*[ count(*)=4 and *[4]/descendant::text()[normalize-space()][1]/ancestor::*[{$xpathBold}] ]/*[3]";

            $driverName = $this->http->FindSingleNode($xpathHeadTable . "/following-sibling::*[normalize-space()][1]/descendant::text()[normalize-space()][1]/ancestor::*[{$xpathBold}]", null, true, "/^{$patterns['travellerName']}$/u");
            $r->general()->traveller($driverName, true);

            $carType = $this->http->FindSingleNode($xpathHeadTable . "/following-sibling::*[normalize-space()][1]/descendant::tr[not(.//tr) and {$this->starts($this->t('Booking Date:'))}]/following-sibling::tr[{$this->contains($this->t('Car'))}][1]", null, true, "/^(.{2,}?)\s+{$this->opt($this->t('Car'))}$/");
        } else {
            $xpathHeadTable = "//tr[ following-sibling::tr/descendant::text()[normalize-space()][1][ancestor::a and ({$this->eq($this->t('btnText'))} or ancestor::*[{$this->contains(['#0276B1', '#0276b1'], '@style')}])] ]/*[count(*)=3]/*[normalize-space()][last()]";

            $r->general()->travellers($this->http->FindNodes("//text()[normalize-space()='Driver Details']/following::text()[starts-with(normalize-space(),'Age:')]/preceding::text()[normalize-space()][1]"), true);

            $carType = $this->http->FindSingleNode("//text()[normalize-space()='or Similar']/preceding::text()[normalize-space()][2]");
        }

        if (empty($carType) && $r->getCancelled() === true) {
            $carType = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Booking Date:')]/ancestor::tr[1]/following::text()[normalize-space()][1]");
        }

        $status = $this->http->FindSingleNode($xpathHeadTable . "/descendant::text()[normalize-space()][1]", null, true, "/^{$this->opt($this->t('statusVariants'))}$/");

        if ($status) {
            $r->general()->status($status);
        }

        $confNodes = $this->http->XPath->query($xpathHeadTable . "/descendant::tr[descendant::text()[normalize-space()][2] and not(.//tr)]");

        foreach ($confNodes as $cofRoot) {
            $r->general()
                ->confirmation(
                    $this->http->FindSingleNode("./descendant::text()[normalize-space()][2]", $cofRoot),
                    $this->http->FindSingleNode("./descendant::text()[normalize-space()][1]", $cofRoot)
                );
        }

        $spentAwards = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Rewards Applied from Capital One Venture')]/following::text()[normalize-space()][1]");

        if ($spentAwards !== null) {
            $r->price()
                ->spentAwards($spentAwards);
        }

        $price = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Card Payment from Capital One Venture')]/following::text()[normalize-space()][1]");

        if (empty($price)) {
            $price = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Due at Pick-up')]/following::text()[normalize-space()][1]");
        }

        if (preg_match("/^(?<currency>[^\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*)$/", $price, $matches)) {
            // $391.92
            $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
            $r->price()->currency($matches['currency'])->total(PriceHelper::parse($matches['amount'], $currencyCode));
        }

        $r->car()
            ->type($carType)
            ->model($this->http->FindSingleNode("//text()[normalize-space()='or Similar']/preceding::text()[normalize-space()][1]"), false, $r->getCancelled() ?? false)
        ;

        $r->pickup()
            ->location($this->http->FindSingleNode("//text()[normalize-space()='Pick-up']/following::text()[normalize-space()][1]"))
            ->date($this->normalizeDate($this->http->FindSingleNode("//text()[normalize-space()='Pick-up']/following::text()[normalize-space()][2]")));

        $r->dropoff()
            ->location($this->http->FindSingleNode("//text()[normalize-space()='Drop-off']/following::text()[normalize-space()][1]"))
            ->date($this->normalizeDate($this->http->FindSingleNode("//text()[normalize-space()='Drop-off']/following::text()[normalize-space()][2]")));

        $pickUpYear = date("Y", $r->getPickUpDateTime());
        $dropOffYear = date("Y", $r->getDropOffDateTime());

        if ($r->getPickUpDateTime() > $r->getDropOffDateTime() && ($pickUpYear === $dropOffYear || $pickUpYear > $dropOffYear)) {
            $currentYear = $this->http->FindSingleNode("//text()[contains(normalize-space(), '©')]", null, true, "/(\d+) Capital One/");
            $r->pickup()
                ->date($this->normalizeDate($this->http->FindSingleNode("//text()[normalize-space()='Pick-up']/following::text()[normalize-space()][2]") . ' ' . $currentYear));
        }

        $cancellation = $this->http->FindSingleNode("//tr[{$this->eq($this->t('Cancellation Policy'))}]/following-sibling::tr[normalize-space()][1][not(.//tr)]");

        if ($cancellation) {
            $r->general()->cancellation($cancellation);
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $otaConf = $this->http->FindSingleNode("//text()[normalize-space()='Capital One Travel']/following::text()[normalize-space()][1]");

        if (!empty($otaConf)) {
            $email->ota()
                ->confirmation($otaConf);
        }

        $this->currentDate = strtotime($parser->getDate());

        if ($this->detectEmailByHeaders($parser->getHeaders()) !== true) {
            $year = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Capital One.')]", null, true,
                "/(\d{4})\s*{$this->opt($this->t('Capital One.'))}/");

            if ($year === date("Y", $this->currentDate) || $year === date("Y", strtotime("-1 month", $this->currentDate))) {
                $this->currentDate = strtotime("-1 month", $this->currentDate);
            } else {
                $this->currentDate = null;
            }
        }

        $this->ParseEmail($email);

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
        return count(self::$dictionary);
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

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function normalizeDate($date)
    {
        // $this->logger->debug('$date in = '.print_r( $date,true));
        $year = date("Y", $this->currentDate);

        $in = [
            //Nov 21, 12:00 PM
            '#^(\w+)\s*(\d+)\,\s*(\d{1,2}:\d{2}\s*[AP]M)\s*$#',
            //Nov 21, 12:00 PM 2023
            '#^(\w+)\s*(\d+)\,\s*(\d{1,2}:\d{2}\s*[AP]M)\s*(\d{4})$#',
        ];
        // $year - for date without year and with week
        // %year% - for date without year and without week
        $out = [
            '$2 $1 %year%, $3',
            '$2 $1 $4, $3',
        ];
        $date = preg_replace($in, $out, $date);
        // $this->logger->debug('$date out = '.print_r( $date,true));

        if (preg_match("#\d+\s+([^\d\s]+)\s+(?:\d{4}|%year%)#", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        if (!empty($this->currentDate) && $this->currentDate > strtotime('01.01.2000') && strpos($date, '%year%') !== false
            && preg_match('/^\s*(?<date>\d+ \w+) %year%(?:\s*,\s(?<time>\d{1,2}:\d{2}.*))?$/', $date, $m)) {
            // $this->logger->debug('$date (no week, no year) = '.print_r( $m['date'],true));
            $date = EmailDateHelper::parseDateRelative($m['date'], $this->currentDate);

            if (!empty($date) && !empty($m['time'])) {
                return strtotime($m['time'], $date);
            }

            return $date;
        } elseif ($year > 2000 && preg_match("/^(?<week>\w+), (?<date>\d+ \w+ .+)/u", $date, $m)) {
            // $this->logger->debug('$date (week no year) = '.print_r( $string,true));
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($m['week'], $this->lang));

            return EmailDateHelper::parseDateUsingWeekDay($m['date'], $weeknum);
        } elseif (preg_match("/^\d+ [[:alpha:]]+ \d{4}(,\s*\d{1,2}:\d{2}(?: ?[ap]m)?)?$/ui", $date)) {
            // $this->logger->debug('$date (year) = '.print_r( $str,true));
            return strtotime($date);
        } else {
            return null;
        }

        return null;
    }
}
