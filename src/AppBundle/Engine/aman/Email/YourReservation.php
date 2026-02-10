<?php

namespace AwardWallet\Engine\aman\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class YourReservation extends \TAccountChecker
{
    public $mailFiles = "aman/it-109594620.eml, aman/it-190210591.eml, aman/it-94704385.eml, aman/it-650610991.eml";
    public $subjects = [
        '/\s*Your reservation\s*\d+\s*at\s*\D+/',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'statusPhrases'               => ['is now'],
            'statusVariants'              => ['confirmed'],
            'YOUR JOURNEY BEGINS'         => ['Your journey begins', 'YOUR JOURNEY BEGINS', 'PLAN YOUR JOURNEY'],
            'We are delighted to confirm' => ['We are delighted to confirm', 'RESERVATION DETAILS'],
            'confirmationNumber'          => ['Confirmation number', 'confirmation number'],
            'welcoming you to our'        => ['welcoming you to our on', 'welcoming you to our'],
            'checkInTimeStart'            => ['Check-in is from', 'Check in is from', 'Check in at', 'Check-in:'],
            'checkOutTimeStart'           => ['check-out is', 'check out is', 'check out at', 'Check-out:'],
            'Stay dates'                  => ['Stay dates', 'Arrival date'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@aman.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (preg_match($subject, $headers['subject'])) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'Aman Group')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('RESERVATION DETAILS'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('We are delighted to confirm'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]aman\.com$/', $from) > 0;
    }

    public function ParseHotel(Email $email): void
    {
        $patterns = [
            'time'  => '\d{1,2}(?:[:：]\d{2})?(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.    |    3pm
            'phone' => '[+(\d][-+. \d)(]{5,}[\d)]', // +377 (93) 15 48 52    |    (+351) 21 342 09 07    |    713.680.2992
        ];

        $h = $email->add()->hotel();

        $confirmation = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->starts($this->t('confirmationNumber'))}] ]/*[normalize-space()][2]", null, true, '/^[-A-Z\d]{5,}$/');

        if ($confirmation) {
            $confirmationTitle = $this->http->FindSingleNode("//tr[count(*[normalize-space()])=2]/*[normalize-space()][1][{$this->starts($this->t('confirmationNumber'))}]", null, true, '/^(.+?)[\s:：]*$/u');
            $h->general()->confirmation($confirmation, $confirmationTitle);
        } elseif (preg_match("/(?<description>{$this->opt($this->t('confirmationNumber'))})[:\s]+(?<number>[-A-Z\d]{5,})\b/", implode("\n", $this->http->FindNodes("//text()[{$this->contains($this->t('confirmationNumber'))}]")), $m)) {
            // it-94704385.eml
            $h->general()->confirmation($m['number'], $m['description']);
        }

        $travellers = str_replace(['Mr. & Mrs.', 'Mr.', 'Mrs.', 'Ms. '], "", $this->http->FindNodes("//text()[{$this->contains($this->t('Guest name:'))}]/ancestor::tr[1]/descendant::td[string-length()>5][2]/descendant::text()[normalize-space()]"));

        if (!empty($travellers)) {
            $h->general()
                ->travellers($travellers, true);
        } elseif (empty($traveller)) {
            $traveller = str_replace(['Mr. & Mrs.', 'Mr.', 'Mrs.', 'Ms. '], "", $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Dear')]", null, true, "/{$this->opt($this->t('Dear'))}\s*(.+)\,/"));
            $h->general()
                ->traveller($traveller, true);
        }

        $cancellation = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'BOOKING AND CANCELLATION POLICIES')]/following::text()[contains(normalize-space(), 'Cancel ') or contains(normalize-space(), 'Cancellation')]");

        if (empty($cancellation)) {
            $cancellation = $this->http->FindSingleNode("//text()[normalize-space() = 'Cancellation:']/ancestor::tr[1]/descendant::td[string-length()>5][2]");
        }

        if (!empty($cancellation)) {
            $h->general()
                ->cancellation($cancellation);
        }

        $cancellationNumber = $this->http->FindSingleNode("//text()[normalize-space()='Cancellation number:']/following::text()[string-length()>3][1]");

        if (!empty($cancellationNumber)) {
            // it-190210591.eml
            $h->general()
                ->status('cancelled')
                ->cancellationNumber($cancellationNumber)
                ->cancelled();
        }

        $hotelName = $this->http->FindSingleNode("//text()[starts-with(normalize-space(),'We look forward to welcoming you to an')]", null, true, "/{$this->opt($this->t('We look forward to welcoming you to an'))}\s*(\D+)\s*destination in the future\./")
            ?? $this->http->FindSingleNode("//text()[starts-with(normalize-space(),'We look forward to welcoming you to')]", null, true, "/{$this->opt($this->t('We look forward to welcoming you to'))}\s*(\D+)\./")
            ?? $this->http->FindSingleNode("//text()[contains(normalize-space(),'Your booking at')]", null, true, "/{$this->opt($this->t('Your booking at'))}(?: our)?\s*(\D+?)\s*{$this->opt($this->t('is now'))} /")
            ?? $this->http->FindSingleNode("//text()[starts-with(normalize-space(),'General Manager at')]", null, true, "/{$this->opt($this->t('General Manager at'))}\s*(.+)/")
        ;

        $h->hotel()->name($hotelName);

        $gettingHere = $this->re("/{$this->opt($this->t('Getting here'))}\n+(.{3,})/s", implode("\n", $this->http->FindNodes("//text()[{$this->eq($this->t('Getting here'))}]/ancestor::*[ descendant::text()[normalize-space()][2] ][1]/descendant::text()[normalize-space()]")));

        if (!empty($h->getHotelName()) && preg_match("/^\s*{$this->opt($h->getHotelName())}\s+(?<address>.{3,}?)\s+(?:{$this->opt($this->t('Phone:'))}|{$this->opt($this->t('Email:'))})/s", $gettingHere, $m)) {
            // it-650610991.eml
            $h->hotel()->address(preg_replace('/\s+/', ' ', $m['address']));
        }

        if (preg_match("/^{$this->opt($this->t('Phone:'))}[:\s]*(?<phone>{$patterns['phone']})$/m", $gettingHere, $m)) {
            $h->hotel()->phone($m['phone']);
        }

        if (empty($h->getAddress()) && !empty($h->getHotelName())) {
            // it-94704385.eml
            $hotelInfo = implode(' ', $this->http->FindNodes("//text()[starts-with(normalize-space(),'We look forward to welcoming you to')]/following::text()[string-length()>2]"));

            if (preg_match("/\s*{$this->opt($h->getHotelName())}\s+(?<address>.{3,}?)\s+(?<phone>{$patterns['phone']})\s*\S+@\S+\.\S+\sEXPLORE/", $hotelInfo, $m)) {
                $h->hotel()->address($m['address'])->phone($m['phone']);
            }
        }

        if (empty($h->getAddress())
            && !empty($address = $this->http->FindSingleNode("//text()[contains(normalize-space(),'welcoming you to our')]", null, true, "/{$this->opt($this->t('welcoming you to our'))}\s+(.{3,}?)\s+on/"))
        ) {
            // it-109594620.eml
            if (preg_match("/^\s*home\s*$/i", $address)) {
                $h->hotel()->noAddress();
            } else {
                $h->hotel()->address($address);
            }
        }

        if (!empty($h->getHotelName())) {
            $statusTexts = array_filter($this->http->FindNodes("//text()[{$this->contains($this->t('statusPhrases'))}]", null, "/{$this->opt($h->getHotelName())}\s+{$this->opt($this->t('statusPhrases'))}[:\s]+({$this->opt($this->t('statusVariants'))})(?:\s*[,.;:!?]|$)/i"));

            if (count(array_unique(array_map('mb_strtolower', $statusTexts))) === 1) {
                $status = array_shift($statusTexts);
                $h->general()->status($status);
            }
        }

        $guestsVal = $this->http->FindSingleNode("//text()[normalize-space()='Number of guests:']/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Number of guests:'))}\s*(.+)/");

        if (preg_match('/^(\d{1,3})$/', $guestsVal, $m)
            || preg_match("/\b(\d{1,3})\s*{$this->opt($this->t('adult'))}/", $guestsVal, $m)
        ) {
            $h->booked()->guests($m[1]);
        }

        if (preg_match("/\b(\d{1,3})\s*{$this->opt($this->t('children'))}/", $guestsVal, $m)) {
            $h->booked()->kids($m[1]);
        }

        $dateCheckIn = $dateCheckOut = null;
        $dateInfo = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->starts($this->t('Stay dates'))}] ]/*[normalize-space()][2]");

        if (preg_match("/^(?<date1>.+?)\s+{$this->opt($this->t('to'))}\s+(?<date2>.+)$/", $dateInfo, $m)) {
            $dateCheckIn = strtotime($m['date1']);
            $dateCheckOut = strtotime($m['date2']);
        }

        $timeInfo = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->starts($this->t('Check in / out time'))}] ]/*[normalize-space()][2]")
            ?? $this->http->FindSingleNode("//text()[{$this->starts($this->t('Check-in is from'))}]")
        ;

        if ($dateCheckIn && preg_match("/{$this->opt($this->t('checkInTimeStart'))}\s*(?:After\s+)?(?<time>{$patterns['time']})/i", $timeInfo, $m)) {
            $dateCheckIn = strtotime($m['time'], $dateCheckIn);
        }

        if ($dateCheckOut && preg_match("/{$this->opt($this->t('checkOutTimeStart'))}\s*(?:Before\s+)?(?<time>{$patterns['time']})/i", $timeInfo, $m)) {
            $dateCheckOut = strtotime($m['time'], $dateCheckOut);
        }

        $h->booked()->checkIn($dateCheckIn)->checkOut($dateCheckOut);

        $roomDescription = $this->http->FindSingleNode("//text()[normalize-space()='ACCOMMODATION DETAILS']/following::text()[string-length()>2][1]");

        if (!empty($roomDescription) && preg_match("/^(\D+)\:\s*(.+)/u", $roomDescription, $m)) {
            $room = $h->addRoom();
            $room->setDescription($m[2]);
            $room->setType($m[1]);
        } elseif (!empty($roomType = $this->http->FindSingleNode("//text()[normalize-space() = 'Accommodation details:']/ancestor::tr[1]/descendant::td[string-length()>5][2]"))) {
            $room = $h->addRoom();

            if (strlen($roomType) < 250) {
                $room->setType($roomType);
            }

            $roomRate = $this->http->FindSingleNode("//text()[normalize-space() = 'Room rate:']/ancestor::tr[1]/descendant::td[string-length()>5][2]");

            if (!empty($roomRate)) {
                $room->setRate($roomRate . (preg_match("/night/i", $roomRate) > 0 ? '' : ' / night'));
            }

            $rateType = $this->http->FindSingleNode("//text()[normalize-space() = 'Rate, Exclusive Offer:']/ancestor::tr[1]/descendant::td[string-length()>5][2]");

            if (!empty($rateType)) {
                $room->setRateType($rateType);
            }
        }

        $total = $this->http->FindSingleNode("//text()[normalize-space()='Grand total:']/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Grand total:'))}\s*[A-Z]{3}\s*([\d\,\.]+)/");
        $currency = $this->http->FindSingleNode("//text()[normalize-space()='Grand total:']/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Grand total:'))}\s*([A-Z]{3})\s*[\d\,\.]+/");

        if (!empty($total) && !empty($currency)) {
            $h->price()
                ->total(str_replace(',', '', $total))
                ->currency($currency);

            $cost = $this->http->FindSingleNode("//text()[normalize-space()='Room rate total:']/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Room rate total:'))}\s*[A-Z]{3}\s*([\d\,\.]+)/");

            if (!empty($cost)) {
                $h->price()
                    ->cost(str_replace(',', '', $cost));
            }

            $tax = $this->http->FindSingleNode("//text()[normalize-space()='Taxes and service charge:']/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Taxes and service charge:'))}\s*[A-Z]{3}\s*([\d\,\.]+)/");

            if (!empty($tax)) {
                $h->price()
                    ->tax(str_replace(',', '', $tax));
            }
        }
        $this->detectDeadLine($h);
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->ParseHotel($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
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
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }

    private function re($re, $str, $c = 1): ?string
    {
        if (preg_match($re, $str, $m) && array_key_exists($c, $m)) {
            return $m[$c];
        }

        return null;
    }

    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h): void
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (preg_match('/Cancel (\d+ days) prior to arrival to avoid 100% room night penalty\./u', $cancellationText, $m)
            || preg_match('/Cancellations or any changes made less than (\d+ days) prior to arrival, no refund\./u', $cancellationText, $m)
            || preg_match('/Cancel (\d+ days) prior to arrival to avoid 2 nights/u', $cancellationText, $m)
            || preg_match('/Cancellation,shortening of stay or any date change within (\d+ days) will incur 100% penalty/u', $cancellationText, $m)
        ) {
            $h->booked()->deadlineRelative($m[1]);
        }

        if (preg_match('/Cancel by (?<hours>\d+) noon\, (?<days>\d+) days prior to arrival to avoid/u', $cancellationText, $m)) {
            $h->booked()->deadlineRelative($m['days'] . ' days', $m['hours'] . ' hours');
        }
    }
}
