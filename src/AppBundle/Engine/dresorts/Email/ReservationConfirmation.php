<?php

namespace AwardWallet\Engine\dresorts\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ReservationConfirmation extends \TAccountChecker
{
    public $mailFiles = "dresorts/it-3092033.eml, dresorts/it-3092041.eml, dresorts/it-42028060.eml, dresorts/it-88569967.eml";

    public $lang = '';
    public static $dictionary = [
        'en' => [
            'Phone'                                 => ['Phone', 'Tel'],
            'Check-in Date:'                        => ['Check-in Date:', 'Arrival Date:'],
            'Check-out Date:'                       => ['Check-out Date:', 'Depart Date:', 'Departure Date:'],
            'No. of Guests:'                        => ['No. of Guests:', 'Number of Guests:'],
            'Points Used:'                          => ['Points Used:', 'Resort Points Used:'],
            'Cancellation/Non Arrival/Late Arrival' => ['Cancellation/Non Arrival/Late Arrival', 'Reservation Cancellation Policy'],
        ],
    ];

    private $detectSubject = [
        'Reservation Confirmation',
    ];

    private static $detectProvider = [
        'hiltongvc' => [
            'from' => ['ContactUs@hgv.com'],
            'body' => ['theclub.hiltongrandvacations.com', '@hgv.com'],
        ],
        'dresorts' => [
            'from' => ['@DiamondResorts.com'],
            'body' => ['Diamond Resorts Int', '@diamondresorts.com'],
        ],
    ];
    private $providerCode;

    private $detectBody = [
        'Thank you for your recent booking',
        'Thank you for booking your',
        'Thank you for choosing to stay at',
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $body = $this->http->Response['body'];

        if (empty($body) && stripos($parser->getPlainBody(), '<html') !== false) {
            $this->http->SetEmailBody($parser->getPlainBody());
//            $body = $this->http->Response['body'];
        }
//        foreach ($this->detectBody as $lang => $detectBody){
//            if (strpos($body, $detectBody) !== false) {
//                $this->lang = $lang;
//                break;
//            }
//        }
        $this->lang = 'en';

        $this->parseHotel($email);

        foreach (self::$detectProvider as $code => $detect) {
            if (!empty($detect['from'])
                && $this->striposAll($parser->getCleanFrom(), $detect['from']) === true
            ) {
                $this->providerCode = $code;

                break;
            }
        }

        if (empty($this->providerCode)) {
            foreach (self::$detectProvider as $code => $detect) {
                if (!empty($detect['body'])
                    && $this->http->XPath->query("//node()[{$this->contains($detect['body'])}]")->length > 0
                ) {
                    $this->providerCode = $code;

                    break;
                }
            }
        }

        $email->setProviderCode($this->providerCode);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@DiamondResorts.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers["from"]) || empty($headers["subject"])) {
            return false;
        }

        $detectedFrom = false;

        foreach (self::$detectProvider as $code => $detect) {
            if (!empty($detect['from'])
                && $this->striposAll($headers['from'], $detect['from']) === true
            ) {
                $detectedFrom = true;

                break;
            }
        }

        if ($detectedFrom == false) {
            return false;
        }

        foreach ($this->detectSubject as $re) {
            if (stripos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $this->http->Response['body'];

        if (empty($body) && stripos($parser->getPlainBody(), '<html') !== false) {
            $this->http->SetEmailBody($parser->getPlainBody());
            $body = $this->http->Response['body'];
        }

        $detectedProvider = false;

        foreach (self::$detectProvider as $code => $detect) {
            if (!empty($detect['body'])
                && $this->http->XPath->query("//node()[{$this->contains($detect['body'])}]")->length > 0
            ) {
                $detectedProvider = true;

                break;
            }
        }

        if ($detectedProvider == false) {
            return false;
        }

//        if ($this->striposAll($body, $this->detectCompany) === false) {
//            return false;
//        }

        foreach ($this->detectBody as $detectBody) {
            if (strpos($body, $detectBody) !== false) {
                return true;
            }
        }

        return false;
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
        return array_keys(self::$detectProvider);
    }

    private function parseHotel(Email $email)
    {
        $h = $email->add()->hotel();

        // General
        $confirmation = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Reservation Number:'))}]");

        if (preg_match("/({$this->preg_implode($this->t('Reservation Number:'))})\s*([-A-Z\d]{5,})$/", $confirmation, $m)) {
            $h->general()->confirmation($m[2], rtrim($m[1], ': '));
        } else {
            $h->general()->confirmation($this->nextText('Reservation Number:'), 'Reservation Number');
        }

        $guestNames = $this->http->FindNodes('//tr[starts-with(normalize-space(.),"Occupying Guest") and not(.//tr)]/following-sibling::tr/td[position()=1 and .//*[name()="b" or name()="strong"]]/*[normalize-space()]', null, '#^[[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]]$#u');
        $guestNames = array_filter($guestNames);

        if (count($guestNames) === 0) {
            // it-42028060.eml
            $dearGuests = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Check-in Date:'))}]/preceding::text()[{$this->starts($this->t('Dear'))}][1]", null, true, "/^{$this->preg_implode($this->t('Dear'))}\s+([[:alpha:]][-.&\'[:alpha:] ]*[[:alpha:]])[:!,.\s]*$/mu");
            $guestNames = preg_split("#\s+(?:&|and)\s+#i", $dearGuests);
            $guestNames = array_filter($guestNames, function ($item) {
                return preg_match('#[^.]$#', $item) > 0;
            });
        }
        $h->general()->travellers($guestNames);

        $xpathFragment1 = '//table//tr[position()=1 and .//img]/following-sibling::tr[normalize-space(.)!=""]';
        $dateReservation = $this->http->FindSingleNode('(' . $xpathFragment1 . '[2])[1]');

        if (preg_match('/CHECK-IN\.\s*(\w+\s*\d+\,\s*\d{4})/', $dateReservation, $matches) || preg_match('/([^\d]{3,}\s+\d{1,2}\s*,\s+\d{4})/', $dateReservation, $matches)) {
            $h->general()->date(strtotime($matches[1]));
        }

        // Hotel
        $phone = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Phone'))}]", null, true, "#:\s*([+(\d][-. \d)(]{5,}[\d)])\s*$#");

        if (empty($phone)) {
            $phone = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Phone'))}]/following::text()[normalize-space()][1]", null, true, "#([+(\d][-. \d)(]{5,}[\d)])\s*$#");
        }

        $hotelName = $this->http->FindSingleNode('(' . $xpathFragment1 . '[1])[1]');

        if (stripos($hotelName, 'Reservation') !== false) {
            $hotelName = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Phone'))}]/ancestor::td[1]", null, true, "#^\s*(.+){$this->opt($this->t('a Member-Only'))}#");
        }

        if (empty($hotelName)) {
            $hotelName = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Location:'))}]/following::text()[normalize-space()][1]");
        }

        $h->hotel()
            ->name($hotelName)
            ->phone($phone, false, true)
        ;

        if (!empty($h->getHotelName())) {
            $address = implode(', ', $this->http->FindNodes("//*[(self::b or self::strong) and {$this->eq($h->getHotelName())}]/ancestor::tr[1][{$this->eq($h->getHotelName())}]/following-sibling::tr[normalize-space()][position()<3][ td[2] ]/td[1]"));

            if (empty($address)) {
                $address = implode(', ', $this->http->FindNodes("//*[(self::b or self::strong) and {$this->eq(str_replace('®', '(TM)', $h->getHotelName()))}]/ancestor::tr[1][{$this->eq($h->getHotelName())}]/following-sibling::tr[normalize-space()][position()<3][ td[2] ]/td[1]"));
            }

            if ($address) {
                $h->hotel()->address($address);
            }

            // it-42028060.eml
            // 1775 Ala Moana Blvd., Honolulu, Oahu, HI 96815 Phone 1.808.943.5800 Fax 1.808.943.6405
            $contacts = $this->http->FindSingleNode("//*[(self::b or self::strong) and {$this->starts($h->getHotelName())}]/ancestor::p[1]/following-sibling::p[normalize-space()][1][{$this->contains($this->t('Phone'))} or {$this->contains($this->t('Fax'))}]");

            if (empty($contacts)) {
                $contacts = implode("\n", $this->http->FindNodes("//text()[{$this->starts($this->t('Phone'))}]/ancestor::td[1]/*"));
            }

            $contacts = preg_replace("/^\s*" . preg_quote($h->getHotelName()) . "\s*\n\s*/", '', $contacts);

            if (empty($h->getAddress()) && preg_match("#{$this->opt($this->t('Resort/Hotel'))}(.+)\s*{$this->preg_implode($this->t('Phone'))}#", $contacts, $m)) {
                $h->hotel()->address($m[1]);
            }

            if (empty($h->getAddress()) && preg_match("#^([\s\S]{3,}?)\s+{$this->preg_implode($this->t('Phone'))}#", $contacts, $m)) {
                $h->hotel()->address(preg_replace('/\s*\n\s*/', ', ', $m[1]));
            }

            if (empty($h->getPhone()) && preg_match("#^.{3,}?{$this->preg_implode($this->t('Phone'))}[:\s]*([+(\d][-. \d)(]{5,}[\d)])\s*(?:{$this->preg_implode($this->t('Fax'))}|$)#", $contacts, $m)) {
                $h->hotel()->phone($m[1]);
            }

            if (empty($h->getFax()) && preg_match("#^.{3,}?{$this->preg_implode($this->t('Fax'))}[:\s]*([+(\d][-. \d)(]{5,}[\d)])\s*(?:{$this->preg_implode($this->t('Phone'))}|$)#", $contacts, $m)) {
                $h->hotel()->fax($m[1]);
            }
        }

        // Booked
        $dateIn = $this->nextText('Check-in Date:', "/^[ :]*(.+)/");
        $dateOut = $this->nextText('Check-out Date:', "/^[ :]*(.+)/");

        $h->booked()
            ->checkIn(!empty($dateIn) ? strtotime($dateIn . ', ' . $this->nextText('Check-In Time:')) : null)
            ->checkOut(!empty($dateOut) ? strtotime($dateOut . ', ' . $this->nextText('Check-Out Time:')) : null)
            ->guests($this->nextText('No. of Guests:', '#\b(\d{1,3})\s*Adult#i'), true, true)
            ->kids($this->nextText('No. of Guests:', '#\b(\d{1,3})\s*Children#i'), true, true)
        ;

        // Rooms
        $type = $this->nextText('Unit Type:');

        if (!empty($type)) {
            $h->addRoom()
                ->setType($type);
        }

        // Price
        $h->price()->spentAwards($this->nextText('Points Used:'), true, true);
        $accommodationCost = $this->http->FindSingleNode("//p[{$this->starts($this->t('Accommodation Cost:'))}]", null, true, "#{$this->preg_implode($this->t('Accommodation Cost:'))}[:\s]*(.+)$#");

        if (preg_match('/^(?<currency>[^\d)(]+) ?(?<amount>\d[,.\'\d]*)/', $accommodationCost, $m)) {
            // $274.60
            $h->price()
                ->currency($this->normalizeCurrency($m['currency']))
                ->total($this->normalizeAmount($m['amount']));
        }

        // Program
        $account = $this->nextText('Membership Number:');

        if (!empty($account)) {
            $h->program()
                ->account($account, false);
        }

        // Cancellation
        // Deadline
        $cancellationText = implode("\n", $this->http->FindNodes("//text()[" . $this->eq($this->t("Cancellation/Non Arrival/Late Arrival")) . "]/following::text()[normalize-space()][1]/ancestor::table[1]//text()[normalize-space()]"));

        if (empty($cancellationText)) {
            $cancellationText = $this->http->FindSingleNode("//p[{$this->starts($this->t('Cancellation Policy:'))}]", null, true, "#{$this->preg_implode($this->t('Cancellation Policy:'))}[:\s]*(.+)$#");
        }

        if (!empty($cancellationText) && strlen($cancellationText) < 2000) {
            $h->general()->cancellation($cancellationText);
            $this->detectDeadLine($h, $cancellationText);
        }

        return $email;
    }

    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h, string $cancellationText)
    {
        if (preg_match("#\s+(?<prior>\d+)-\d+ day prior to arrival date [.]+0%\s+#i", $cancellationText, $m) // en
        ) {
            $h->booked()->deadlineRelative($m['prior'] . ' days', '00:00');
        } elseif (preg_match("#NON-REFUNDABLE - 100% of payment will be taken#i", $cancellationText) // en
        ) {
            $h->booked()->nonRefundable();
        }
    }

    private function t(string $phrase)
    {
        if (!isset(self::$dictionary, $this->lang) || empty(self::$dictionary[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$this->lang][$phrase];
    }

    /**
     * Formatting over 3 steps:
     * 11 507.00  ->  11507.00
     * 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
     * 18800,00   ->  18800.00  |  2777,0    ->  2777.0.
     *
     * @param string $string Unformatted string with amount
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
            'GBP' => ['£'],
            'EUR' => ['€'],
            'USD' => ['$', 'US$'],
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

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'starts-with(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'contains(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function nextText($field, $regexp = null, $root = null)
    {
        $result = $this->http->FindSingleNode('descendant::text()[' . $this->eq($this->t($field)) . '][1]/following::text()[normalize-space()][1]', $root, true, $regexp);

        if ($result === null) {
            $result = $this->http->FindSingleNode("//text()[{$this->eq(preg_replace('/\s*:\s*$/', '', $this->t($field)))}]/following::text()[normalize-space() and not(normalize-space() = ':')][1]", $root, true, $regexp);
        }

        return $result;
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function striposAll($text, $needle): bool
    {
        if (empty($text) || empty($needle)) {
            return false;
        }

        if (is_array($needle)) {
            foreach ($needle as $n) {
                if (stripos($text, $n) !== false) {
                    return true;
                }
            }
        } elseif (is_string($needle) && stripos($text, $needle) !== false) {
            return true;
        }

        return false;
    }
}
