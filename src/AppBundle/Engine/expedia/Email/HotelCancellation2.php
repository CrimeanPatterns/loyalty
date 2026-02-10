<?php

namespace AwardWallet\Engine\expedia\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class HotelCancellation2 extends \TAccountChecker
{
    public $mailFiles = "expedia/it-11558915.eml";

    public $reSubject = [
        'en' => 'Expedia Hotel Cancellation',
    ];
    public $lang = 'en';
    public $date;
    public static $dict = [
        'en' => [
            'Hello'                    => ['Hello'],
            'Hotel Reservation Number' => ['Hotel Reservation Number', 'Itinerary #'],
            'adult'                    => ['adult', 'adults'],
            'children'                 => ['children', 'child'],
        ],
    ];
    private $subj = '';

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getDate());
        $this->assignLang();
        $this->subj = $parser->getSubject();

        $this->parseEmail($email);
        $a = explode('\\', __CLASS__);
        $email->setType($a[count($a) - 1] . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[{$this->contains([
            'CANCELLED', ])} and contains(@href,'expediamail.com')]")->length > 0
        ) {
            return $this->assignLang();
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->reSubject as $reSubject) {
            if (stripos($headers['subject'], $reSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'expediamail.com') !== false;
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
        $r = $email->add()->hotel();

        if ($cnf = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Expedia Itinerary Number'))}]/following::text()[normalize-space(.)!=''][1]", null, true, "#([A-Z\-\d]{5,})#")) {
            $r->ota()->confirmation($cnf);
        }

        $cnf = $this->http->FindSingleNode("(//text()[{$this->starts($this->t('Hotel Reservation Number'))}]/following::text()[normalize-space(.)!=''])[1]", null, true, "#([A-Z\-\d]{5,})#");

        if (empty($cnf)) {
            $cnf = $this->http->FindSingleNode("(//text()[{$this->starts($this->t('Hotel Reservation Number'))}])[1]", null, true, "#([A-Z\-\d]{5,})#");
        }

        if (empty($cnf) && preg_match('/Itinerary \#[ ]+(\d+)/', $this->subj, $m)) {
            $cnf = $m[1];
        }

        $r->general()->confirmation($cnf);

        if ($this->http->XPath->query("//a[contains(.,'CANCELLED')and contains(@href,'expediamail.com')]")->length > 0) {
            $r->general()->status('cancelled');
            $r->general()->cancelled();
        }

        $r->hotel()->name($this->http->FindSingleNode("//text()[{$this->starts($this->t('Check-in'))}]/preceding::table[normalize-space(.)!=''][1]/descendant::tr[1]"));
        $r->hotel()->address(implode(' ', $this->http->FindNodes("//text()[{$this->starts($this->t('Check-in'))}]/preceding::table[normalize-space(.)!=''][1]/descendant::tr[1]/following-sibling::tr")));

        $checkInDate = $this->normalizeDate($this->http->FindSingleNode("(//text()[{$this->starts($this->t('Check-in'))}]/following::text()[string-length(normalize-space(.))>2])[1]"));
        $r->booked()->checkIn($checkInDate);

        $checkOutDate = $this->normalizeDate($this->http->FindSingleNode("//text()[{$this->starts($this->t('Check-out'))}]/following::text()[string-length(normalize-space(.))>2][1]"));
        $r->booked()->checkOut($checkOutDate);

        $r->booked()->rooms($this->http->FindSingleNode("//text()[{$this->starts($this->t('# of Rooms'))}]/following::text()[normalize-space(.)!=''][1]"), false, true);

        $room = $r->addRoom();
        $room->setRate($this->http->FindSingleNode("//td[{$this->starts('Room Price')}]/following-sibling::td[1]/descendant::tr[contains(normalize-space(.), 'night')][1]"), false, true);
        $room->setType($this->http->FindSingleNode("//text()[{$this->starts($this->t('Room type'))}]/following::text()[normalize-space(.)!=''][1]"));
        $guestNames = $this->http->FindNodes("//text()[{$this->starts($this->t('Hello'))}]", null, "#{$this->opt($this->t('Hello'))}\s+([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])(?:\s*[,;!?]|$)#");
        $r->general()->travellers(array_filter($guestNames));

        $guests = array_filter($this->http->FindNodes("//text()[{$this->starts($this->t('Reserved for'))}]/ancestor::tr[1]", null, "#\b(\d{1,3})\s+{$this->opt($this->t('adult'))}#"));

        if (count($guests) !== 0) {
            $r->booked()->guests(array_sum($guests));
        }
        $kids = array_filter($this->http->FindNodes("//text()[{$this->starts($this->t('Reserved for'))}]/ancestor::tr[1]", null, "#\b(\d{1,3})\s+{$this->opt($this->t('children'))}#"));

        if (count($kids) !== 0) {
            $r->booked()->kids(array_sum($kids));
        }
    }

    private function normalizeDate($date)
    {
        //		$this->logger->debug($date);
        $year = date('Y', $this->date);
        $in = [
            //Sat, Feb 24
            '#^(\w+),\s+(\D+)\s+(\d+)$#u',
            //dom, 14 jun
            '#^(\w+),\s+(\d+)\s+(\D+)$#u',
            //2/21/2018
            '#^(\d+)\/(\d+)\/(\d{4})$#u',
            //Dec 1, 2018
            '#^(\w+)\s+(\d+),\s+(\d{4})$#u',
            // sáb. 12 de oct    |    Mon, 21 Oct
            '/\w{2,}[ ]*[,.]+[ ]+(\d{1,2})(?: de)?[ ]+(\w{3,})/u',
        ];
        $out = [
            '$3 $2 ' . $year,
            '$2 $3 ' . $year,
            '$3-$1-$2',
            '$2 $1 $3',
            '$1 $2 ' . $year,
        ];
        $outWeek = [
            '$1',
            '$1',
            '',
            '',
            '',
        ];

        if (!empty($week = preg_replace($in, $outWeek, $date))) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($week, $this->lang));
            $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));
            $str = EmailDateHelper::parseDateUsingWeekDay($str, $weeknum);
        } else {
            $str = strtotime($this->dateStringToEnglish(preg_replace($in, $out, $date)));
        }

        return $str;
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
        if (!isset(self::$dict, $this->lang)) {
            return false;
        }

        foreach (self::$dict as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['Hello']) || empty($phrases['Hotel Reservation Number'])) {
                continue;
            }

            if ($this->http->XPath->query("//node()[{$this->contains($phrases['Hello'])}]")->length > 0
                && $this->http->XPath->query("//node()[{$this->contains($phrases['Hotel Reservation Number'])}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
            } elseif ($this->http->XPath->query("//node()[{$this->contains($phrases['Hello'])}]")->length > 0
                && $this->http->XPath->query("//node()[{$this->contains('You scored a great price with Expedia')}]")->length > 0) {
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

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ')="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'starts-with(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return "(?:" . preg_quote($s) . ")";
        }, $field)) . ')';
    }

    /**
     * Formatting over 3 steps:
     * 11 507.00  ->  11507.00
     * 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
     * 18800,00   ->  18800.00  |  2777,0    ->  2777.0.
     *
     * @param string $s Unformatted string with amount
     */
    private function normalizeAmount(string $s): string
    {
        $s = preg_replace('/\s+/', '', $s);
        $s = preg_replace('/[,.\'](\d{3})/', '$1', $s);
        $s = preg_replace('/,(\d{1,2})$/', '.$1', $s);

        return $s;
    }

    /**
     * @param string $string Unformatted string with currency
     */
    private function normalizeCurrency(string $string): string
    {
        $string = trim($string);
        $currences = [
            'MXN' => ['MXN$'],
            'HKD' => ['HK$'],
            'AUD' => ['A$'],
            'GBP' => ['£'],
            'ILS' => ['₪'],
            'EUR' => ['€'],
            'JPY' => ['¥'],
            'BRL' => ['R$'],
            //            'USD' => ['$'],
        ];

        foreach ($currences as $currencyCode => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $currencyCode;
                }
            }
        }

        return $string;
    }
}
