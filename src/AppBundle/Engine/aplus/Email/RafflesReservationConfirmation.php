<?php

namespace AwardWallet\Engine\aplus\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class RafflesReservationConfirmation extends \TAccountChecker
{
    public $mailFiles = "aplus/it-40643605.eml";

    public $reSubject = [
        'en' => 'Reservation Confirmation:',
        'Your Reservation Confirmation -',
    ];

    public static $dictionary = [
        'en' => [
            'Your reservation number is:' => ['Your reservation number is:', 'Confirmation Number:'],
            'Arriving on'                 => ['Arriving on', 'Arrival Date:'],
            'Departing on'                => ['Departing on', 'Departure Date:'],
            'Cancel Policy:'              => ['Cancel Policy:', 'Cancellation Policy:'],
        ],
    ];

    public $providerCode = '';
    public $lang = '';

    public function parseHtml(Email $email)
    {
        $h = $email->add()->hotel();

        $confirmation = $this->nextText($this->t("Your reservation number is:"));

        if ($confirmation) {
            $confirmationTitle = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Your reservation number is:'))}]", null, true, '/^(.+?)[\s:]*$/');
            $h->general()->confirmation($confirmation, $confirmationTitle);
        }

        $hotelName = $this->http->FindSingleNode("//a[normalize-space()='Destinations']/ancestor::td[1]/descendant::text()[normalize-space()][1]");

        if (!$hotelName) {
            // it-40643605.eml
            $hotelName_temp = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Thank you for choosing the'))}]", null, true, "/{$this->opt($this->t('Thank you for choosing the'))}\s+(.{3,})\s+{$this->opt($this->t('for your stay'))}/");

            if ($this->http->XPath->query("//text()[{$this->contains($hotelName_temp)}]")->length > 1) {
                $hotelName = $hotelName_temp;
            }
        }

        $address = $this->http->FindSingleNode("//text()[" . $this->starts("T ") . "]/preceding::text()[normalize-space(.)][1]");

        $patterns['phone'] = '[+(\d][-. \d)(]{5,}[\d)]';
        $phone = $this->http->FindSingleNode("//text()[{$this->starts("T ")}]", null, true, "#T ({$patterns['phone']})(?:\s+|$)#");
        $fax = $this->http->FindSingleNode("//text()[{$this->contains(" F ")}]", null, true, "#F ({$patterns['phone']})(?:\s+|$)#");

        $h->hotel()
            ->name($hotelName)
            ->address($address)
            ->phone($phone)
            ->fax($fax, false, true)
        ;

        $patterns['time'] = '\d{1,2}(?::\d{2})?(?:\s*[AaPp]\.?[Mm]\.?)?';

        $h->booked()->checkIn2($this->normalizeDate($this->nextText($this->t("Arriving on"))));
        $timeCheckIn = $this->nextText($this->t("Check In:"), null, "/^{$patterns['time']}$/");

        if (!empty($h->getCheckInDate()) && $timeCheckIn) {
            $h->booked()->checkIn(strtotime($timeCheckIn, $h->getCheckInDate()));
        }

        $h->booked()->checkOut2($this->normalizeDate($this->nextText($this->t("Departing on"))));
        $timeCheckOut = $this->nextText($this->t("Check Out:"), null, "/^{$patterns['time']}$/");

        if (!empty($h->getCheckOutDate()) && $timeCheckOut) {
            $h->booked()->checkOut(strtotime($timeCheckOut, $h->getCheckOutDate()));
        }

        $guestName = $this->nextText($this->t("Guest Name:"), null, '/^[[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]]$/u');

        if (!$guestName) {
            $guestName = $this->http->FindSingleNode("//text()[{$this->starts('Dear ')}]", null, true, "/{$this->opt('Dear ')}\s*([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])\s*(?:[,!]+|$)/u");
        }

        if ($guestName) {
            $h->general()->traveller($guestName);
        }

        $adults = $this->re("#\b(\d{1,3})\s+Adult#i", $this->nextText("Number of Guests:"));
        $child = $this->re("#\b(\d{1,3})\s+Child#i", $this->nextText("Number of Guests:"));
        $h->booked()
            ->guests($adults)
            ->kids($child, false, true)
        ;

        $room = $h->addRoom();

        $room
            ->setRate($this->nextText("Room Rate:"))
            ->setType($this->nextText("Room Type:"))
        ;

        $cancellation = $this->nextText($this->t("Cancel Policy:"));
        $h->general()->cancellation($cancellation);

        if (preg_match("/There will be no charge for the cancellation of this booking if it is carried out before\s*(?<hour>{$patterns['time']})\s*\([^)(]+\) of the day prior to arrival\./i", $cancellation, $m)) {
            $h->booked()->deadlineRelative('1 day', $m['hour']);
        }
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'noreply@raffles.com') !== false
            || preg_match('/[.@]mp-network\.com/i', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers['from']) || self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            if (stripos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,".mp-network.com") or contains(@href,"www.villamagna.es") or contains(@href,"@villamagna.es")]')->length === 0
            && $this->http->XPath->query('//node()[contains(.,"raffles.com") or contains(normalize-space(),"Thank you for choosing the Villa Magna") or contains(.,"@villamagna.es")]')->length === 0
        ) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getHeader('date'));
        $this->http->FilterHTML = true;

        $this->assignProvider();
        $this->assignLang();

        $this->parseHtml($email);
        $email->setType('RafflesReservationConfirmation' . ucfirst($this->lang));
        $email->setProviderCode($this->providerCode);

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

    public static function getEmailProviders()
    {
        return ['aplus', 'leadinghotels'];
    }

    private function assignProvider(): bool
    {
        if ($this->http->XPath->query('//node()[contains(.,"raffles.com")]')->length > 0) {
            $this->providerCode = 'aplus';

            return true;
        }

        if ($this->http->XPath->query('//a[contains(@href,".mp-network.com") or contains(@href,"www.villamagna.es") or contains(@href,"@villamagna.es")]')->length > 0
            || $this->http->XPath->query('//node()[contains(normalize-space(),"Thank you for choosing the Villa Magna") or contains(.,"@villamagna.es")]')->length > 0
        ) {
            $this->providerCode = 'leadinghotels';

            return true;
        }

        return false;
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['Arriving on'])) {
                continue;
            }

            if ($this->http->XPath->query("//node()[{$this->contains($phrases['Arriving on'])}]")->length > 0) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function t(string $phrase)
    {
        if (!isset(self::$dictionary, $this->lang) || empty(self::$dictionary[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$this->lang][$phrase];
    }

    private function normalizeDate($str)
    {
        $year = date("Y", $this->date);
        $in = [
            "#^(\d+:\d+)\s*\|\s*[^\d\s]+,(\d+)-([^\d\s]+)-(\d{2})$#", //09:25| Tue,30-Dec-14
        ];
        $out = [
            "$2 $3 $4, $1",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return $str;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function nextText($field, $root = null, $re = null)
    {
        $rule = $this->eq($field);

        return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::text()[normalize-space(.)][1]", $root, true, $re);
    }

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ')="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'starts-with(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }
}
