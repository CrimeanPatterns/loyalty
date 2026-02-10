<?php

namespace AwardWallet\Engine\capitalcards\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class HotelReservation extends \TAccountChecker
{
    public $mailFiles = "capitalcards/it-433286014.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'checkIn'        => ['Check-in:', 'Check-in :', 'Checkin:', 'Checkin :'],
            'checkOut'       => ['Check-out:', 'Check-out :', 'Checkout:', 'Checkout :'],
            'yourStay'       => ['Your stay:', 'Your stay :'],
            'statusPhrases'  => 'your hotel is',
            'statusVariants' => 'confirmed',
        ],
    ];

    private $subjects = [
        'en' => ['Your hotel reservation is confirmed'],
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[.@]capitalone\.com$/i', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider(rtrim($headers['from'], '> ')) !== true) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (is_string($phrase) && stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,".capitalone.com/") or contains(@href,"notification.capitalone.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"Products and services are offered by Capital One")] | //text()[starts-with(normalize-space(),"©") and contains(normalize-space(),"Capital One")]')->length === 0
        ) {
            return false;
        }

        return $this->assignLang($this);
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang($this);

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }
        $email->setType('HotelReservation' . ucfirst($this->lang));

        $patterns = [
            'time'  => '\d{1,2}(?:[:：]\d{2})?(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.    |    3pm
            'phone' => '[+(\d][-+. \d)(]{5,}[\d)]', // +377 (93) 15 48 52    |    (+351) 21 342 09 07    |    713.680.2992
        ];

        $h = $email->add()->hotel();

        /* because this credit card number!
        $account = $this->http->FindSingleNode("//tr/*[not(.//tr) and {$this->starts($this->t('Account ending in'))}]", null, true, "/^{$this->opt($this->t('Account ending in'))}\s+(\d+)$/");

        if ($account !== null) {
            $h->ota()->account('****' . $account, true);
        }
        */

        $xpathConfirmation = "//tr[ count(*[normalize-space()])=2 and *[normalize-space()][2]/descendant-or-self::*[ *[2] ][1]/*[1][{$this->eq('Capital One Travel')}] ]";

        $confirmation = $this->http->FindSingleNode($xpathConfirmation . "/*[normalize-space()][1]/descendant-or-self::*[ *[2] ][1]/*[2]", null, true, '/^[-A-z\d]{5,}$/');

        if ($confirmation) {
            $confirmationTitle = $this->http->FindSingleNode($xpathConfirmation . "/*[normalize-space()][1]/descendant-or-self::*[ *[2] ][1]/*[1]", null, true, '/^(.+?)[\s:：]*$/u');
            $h->general()->confirmation($confirmation, $confirmationTitle);
        }

        $otaConfirmation = $this->http->FindSingleNode($xpathConfirmation . "/*[normalize-space()][2]/descendant-or-self::*[ *[2] ][1]/*[2]", null, true, '/^[-A-z\d]{5,}$/');

        if ($otaConfirmation) {
            $otaConfirmationTitle = $this->http->FindSingleNode($xpathConfirmation . "/*[normalize-space()][2]/descendant-or-self::*[ *[2] ][1]/*[1]", null, true, '/^(.+?)[\s:：]*$/u');
            $h->ota()->confirmation($otaConfirmation, $otaConfirmationTitle);
        }

        $statusTexts = array_filter($this->http->FindNodes("//text()[{$this->contains($this->t('statusPhrases'))}]", null, "/{$this->opt($this->t('statusPhrases'))}[:\s]+({$this->opt($this->t('statusVariants'))})(?:\s*[,.;:!?]|$)/i"));

        if (count(array_unique($statusTexts)) === 1) {
            $status = array_shift($statusTexts);
            $h->general()->status($status);
        }

        $hotelName = $address = $phone = null;
        $hotelRows = $this->http->FindNodes("//tr[{$this->eq($this->t('Reservation details'))}]/preceding::*[ *[normalize-space()][2] ][1]/*[normalize-space()]");

        if (count($hotelRows) > 2 && preg_match("/^{$patterns['phone']}$/", $hotelRows[2])) {
            $hotelName = $hotelRows[0];
            $address = $hotelRows[1];
            $phone = $hotelRows[2];
        } elseif (count($hotelRows) > 1 && preg_match("/^{$patterns['phone']}$/", $hotelRows[1])) {
            $hotelName = $hotelRows[0];
            $address = $hotelRows[1];
        }

        $h->hotel()->name($hotelName)->address($address)->phone($phone, false, true);

        $traveller = $this->http->FindSingleNode("//tr[ preceding::tr[{$this->eq($this->t('Reservation details'))}] and following::tr[count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('checkIn'))}]] ]", null, true, "/^[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]$/u");
        $h->general()->traveller($traveller, true);

        $checkInVal = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('checkIn'))}] ]/*[normalize-space()][2]", null, true, "/^.*\d.*$/");
        $checkOutVal = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('checkOut'))}] ]/*[normalize-space()][2]", null, true, "/^.*\d.*$/");

        if (preg_match($pattern = "/^(?<date>.*?\b\d{4}\b.*?)[,\s]+{$this->opt($this->t('at'))}\s+(?<time>{$patterns['time']})/", $checkInVal, $m)) {
            $h->booked()->checkIn(strtotime($m['time'], strtotime($m['date'])));
        }

        if (preg_match($pattern, $checkOutVal, $m)) {
            $h->booked()->checkOut(strtotime($m['time'], strtotime($m['date'])));
        }

        $rooms = $this->http->XPath->query("//tr[{$this->eq($this->t('Room type'))}]/following-sibling::tr[{$this->starts($this->t('Room #'))}]");

        foreach ($rooms as $rRoot) {
            $room = $h->addRoom();

            $roomType = $this->http->FindSingleNode(".", $rRoot, true, "/^{$this->opt($this->t('Room #'))}\s*\d{1,3}\s*[:]+\s*(.{2,})$/");
            $room->setType($roomType, false, true);
        }

        $cancellation = $this->http->FindSingleNode("//h2[{$this->eq($this->t('Cancellation policy'))}]/following-sibling::*[normalize-space()][1]");
        $h->general()->cancellation($cancellation, false, true);

        if (preg_match("/^Free cancell?ation until\s+(?<date>.*?\b\d{4}\b.*?)[,\s]+{$this->opt($this->t('at'))}\s+(?<time>{$patterns['time']})$/i", $cancellation, $m)
        ) {
            $h->booked()->deadline(strtotime($m['time'], strtotime($m['date'])));
        }

        $totalPrice = $this->http->FindSingleNode("//h2[{$this->eq($this->t('Booking info'))}]/following::tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Trip total:'))}] ]/*[normalize-space()][2]", null, true, '/^.*\d.*$/');

        if (preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $totalPrice, $matches)) {
            // $798.35
            $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
            $email->price()->currency($matches['currency'])->total(PriceHelper::parse($matches['amount'], $currencyCode));

            $cost = $this->http->FindSingleNode("//tr[ preceding-sibling::tr[normalize-space()][1]/descendant::*[../self::tr][1][{$this->eq($this->t('Paid now:'))}] and following-sibling::tr[normalize-space()][1]/descendant::*[../self::tr][1][{$this->eq($this->t('Taxes and fees:'))}] and count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Room:'))}] ]/*[normalize-space()][2]", null, true, '/^.*\d.*$/');

            if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $cost, $m)) {
                $email->price()->cost(PriceHelper::parse($m['amount'], $currencyCode));
            }

            $feesNodes = $this->http->XPath->query("//h2[{$this->eq($this->t('Booking info'))}]/following::tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Taxes and fees:'))}] ]");

            if ($feesNodes->length === 1) {
                $feeRoot = $feesNodes->item(0);
                $feeName = $this->http->FindSingleNode("*[normalize-space()][1]", $feeRoot, true, '/^(.+?)[\s:：]*$/u');
                $feeCharge = $this->http->FindSingleNode("*[normalize-space()][2]", $feeRoot, true, '/^.*\d.*$/');

                if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $feeCharge, $m)) {
                    $email->price()->fee($feeName, PriceHelper::parse($m['amount'], $currencyCode));
                }
            }
        }

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

    public static function assignLang($checker): bool
    {
        // used in parser capitalcards/statement/WithBalance

        if (!isset(self::$dictionary, $checker->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['checkIn']) || empty($phrases['yourStay'])) {
                continue;
            }

            if ($checker->http->XPath->query("//tr/*[{$checker->eq($phrases['checkIn'])}]")->length > 0
                && $checker->http->XPath->query("//tr/*[{$checker->eq($phrases['yourStay'])}]")->length > 0
            ) {
                $checker->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function t(string $phrase, string $lang = '')
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return $phrase;
        }

        if ($lang === '') {
            $lang = $this->lang;
        }

        if (empty(self::$dictionary[$lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$lang][$phrase];
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
