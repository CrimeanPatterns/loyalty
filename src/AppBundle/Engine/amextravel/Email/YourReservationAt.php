<?php

namespace AwardWallet\Engine\amextravel\Email;

use AwardWallet\Schema\Parser\Email\Email;

class YourReservationAt extends \TAccountChecker
{
    public $mailFiles = "amextravel/it-138597970.eml, amextravel/it-189954142.eml, amextravel/it-194075529.eml, amextravel/it-194899790.eml, amextravel/it-40174982.eml, amextravel/it-40344611.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'statusPhrases'                 => ['Your reservation is'],
            'statusVariants'                => ['confirmed'],
            'CONFIRMATION NUMBER'           => ['CONFIRMATION NUMBER'],
            'ROOM DETAILS'                  => ['ROOM DETAILS'],
            'Traveler Information'          => ['Traveler Information', 'TRAVELER INFORMATION'],
            'Room'                          => ['Room', 'ROOM'],
            'Requests'                      => ['Requests', 'REQUESTS'],
            'Cost and Billing Information'  => ['Cost and Billing Information', 'COST AND BILLING INFORMATION', 'Cost Information'],
            'totalPayment'                  => ['Total Due at Hotel', 'Cost', 'Due at Hotel'],
            'totalPayment2'                 => ['Total Due at Hotel', 'Due at Hotel', 'Dollars Used'],
        ],
    ];

    private $detectors = [
        'en' => ['HOTEL DETAILS', 'CONFIRMATION NUMBER'],
    ];
    private $emailSubject;

    public function detectEmailFromProvider($from)
    {
        return strpos($from, 'American Express Travel') !== false
            || stripos($from, '@amextravel.com') !== false
            || stripos($from, 'customerservice@mytrips.americanexpress.com') !== false
        ;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        return preg_match("/Your Reservation at .+ Trip ID\s*:/i", $headers['subject']) > 0
            || stripos($headers['subject'], 'My American Express Travel Itinerary') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,"amextravel.com/") or contains(@href,"americanexpress.com/")]')->length === 0
            && $this->http->XPath->query('//node()[contains(normalize-space(),"you book with American Express") or contains(normalize-space(),"American Express. All rights reserved") or contains(.,"@amextravel.com") or contains(.,"amextravel.com") or contains(.,"americanexpress.com")]')->length === 0
        ) {
            return false;
        }

        return $this->detectBody() && $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        $this->emailSubject = $parser->getSubject();
        $this->parseHotel($email);
        $email->setType('YourReservationAt' . ucfirst($this->lang));

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

    private function parseHotel(Email $email): void
    {
        $xpathCell = '(self::td or self::th)';
        $xpathP = '(self::p or self::div)';

        $tripId = $this->http->FindSingleNode("//text()[{$this->starts($this->t('AMEX TRAVEL TRIP ID'))}]/following::text()[normalize-space()][1]", null, true, '/^[-A-Z\d]{5,}$/');
        $tripIdTitle = $this->http->FindSingleNode("//text()[{$this->starts($this->t('AMEX TRAVEL TRIP ID'))}]", null, true, '/^(.+?)[\s:]*$/');

        if (!$tripId) {
            $tripId = $this->http->FindSingleNode("//img[{$this->contains($this->t('AMEX Travel Trip ID'), '@alt')}]/following::text()[normalize-space()][1]", null, true, '/^[-A-Z\d]{5,}$/');
            $tripIdTitle = $this->http->FindSingleNode("//img[{$this->contains($this->t('AMEX Travel Trip ID'), '@alt')}]/@alt");
        }

        if (!$tripId && preg_match("/\.\s+(Trip ID):\s*(\d{4}-\d{4})\s*$/", $this->emailSubject, $m)) {
            $tripId = $m[2];
            $tripIdTitle = $m[1];
        }

        $email->ota()->confirmation($tripId, $tripIdTitle);

        $h = $email->add()->hotel();

        $status = $this->http->FindSingleNode("//text()[{$this->contains($this->t('statusPhrases'))}]", null, true, "/{$this->opt($this->t('statusPhrases'))}\s+({$this->opt($this->t('statusVariants'))})(?:\s*[,.:;!?]|$)/");

        if ($status) {
            $h->general()->status($status);
        }

        $confirmation = $this->http->FindSingleNode("//text()[{$this->eq($this->t('CONFIRMATION NUMBER'))}]/following::text()[normalize-space()][1]", null, true, '/^[-A-Z\d]{5,}$/');
        $confirmationTitle = $this->http->FindSingleNode("//text()[{$this->eq($this->t('CONFIRMATION NUMBER'))}]", null, true, '/^(.+?)[\s:]*$/');
        $h->general()->confirmation($confirmation, $confirmationTitle);

        if (!empty($this->http->FindSingleNode("//text()[{$this->eq($this->t('CHECK-IN'))}]/following::text()[normalize-space()][position() < 20][{$this->eq($this->t('CHECK-OUT'))}]"))) {
            $this->logger->debug('type 2');
            // Type 2 - with text "CHECK-IN" and "CHECK-OUT"
            $xpathLeft = "//text()[{$this->eq($this->t('ROOM DETAILS'))}]/preceding::text()[normalize-space()][1]/ancestor::tr[*[1][not(normalize-space()) and .//img] and count(*[2]//text()[normalize-space()][not(contains(., ':'))]) = 2]/*[2]";

            $hotelName = $this->http->FindSingleNode($xpathLeft . "/descendant::text()[normalize-space()][1]");
            $address = $this->http->FindSingleNode($xpathLeft . "/descendant::text()[normalize-space()][2]");

            if (empty($hotelName) && empty($address)) {
                $xpathLeft = "//text()[{$this->eq($this->t('ROOM DETAILS'))}]/preceding::text()[normalize-space()][1]/ancestor::tr[*[1][not(normalize-space()) and .//img] and count(*[2]//p[normalize-space()][not(contains(., ':'))][not({$this->eq($this->t('RESERVED RATES'))})]) = 2]/*[2]";
                $hotelName = $this->http->FindSingleNode($xpathLeft . "/descendant::p[normalize-space()][not({$this->eq($this->t('RESERVED RATES'))})][1]");
                $address = $this->http->FindSingleNode($xpathLeft . "/descendant::p[normalize-space()][not({$this->eq($this->t('RESERVED RATES'))})][2]");
            }

            if (empty($hotelName) && empty($address)) {
                //  The Ritz-Carlton, Istanbul
                // Suzer Plaza, Askerocagi Caddesi, No:6, Elmadag/Sisli, Istanbul, TR
                $xpathLeft = "//text()[{$this->eq($this->t('ROOM DETAILS'))}]/preceding::text()[normalize-space()][1]/ancestor::tr[*[1][not(normalize-space()) and .//img] and count(*[2]//p[normalize-space()]) = 2]/*[2]";
                $hotelName = $this->http->FindSingleNode($xpathLeft . "/descendant::p[normalize-space()][1]");
                $address = $this->http->FindSingleNode($xpathLeft . "/descendant::p[normalize-space()][2]");

                if (substr_count($address, ':') !== 1 || stripos($address, ', No:') === false) {
                    $hotelName = $address = null;
                }
            }

            $h->hotel()
                ->name($hotelName)
                ->address($address);

            $checkInXpath = "//text()[{$this->eq($this->t('CHECK-IN'))}]/ancestor::*[(self::td or self::th) and not({$this->eq($this->t('CHECK-IN'))})][1][count(.//text()[normalize-space()]) = 3]";
            $date = strtotime($this->http->FindSingleNode($checkInXpath . '/descendant::text()[normalize-space()][2]'));
            $time = $this->normalizeTime(preg_replace("/^([^-]+)-[^-]+$/", '$1', $this->http->FindSingleNode($checkInXpath . '/descendant::text()[normalize-space()][3]')));

            if (!empty($date) && !empty($time)) {
                $h->booked()->checkIn(strtotime($time, $date));
            }

            $checkOutXpath = "//text()[{$this->eq($this->t('CHECK-OUT'))}]/ancestor::*[(self::td or self::th) and not({$this->eq($this->t('CHECK-OUT'))})][1][count(.//text()[normalize-space()]) = 3]";
            $date = strtotime($this->http->FindSingleNode($checkOutXpath . '/descendant::text()[normalize-space()][2]'));
            $time = $this->normalizeTime(preg_replace("/^([^-]+)-[^-]+$/", '$1', $this->http->FindSingleNode($checkOutXpath . '/descendant::text()[normalize-space()][3]')));

            if (!empty($date) && !empty($time)) {
                $h->booked()->checkOut(strtotime($time, $date));
            }

            $roomsCount = $this->http->FindSingleNode("//text()[{$this->eq($this->t('ROOM DETAILS'))}]/following::text()[normalize-space()][1]", null, true, "/\b(\d{1,3})\s*{$this->opt($this->t('Room'))}/i");
            $h->booked()
                ->rooms($roomsCount)
            ;
            $roomsXpath = "//text()[{$this->eq($this->t('ROOM DETAILS'))}]/ancestor::*[(self::td or self::th) and not({$this->eq($this->t('ROOM DETAILS'))})][1][count(.//text()[normalize-space()]) = " . ($roomsCount + 2) . "]";

            for ($i = 1; $i <= $roomsCount; $i++) {
                $type = $this->http->FindSingleNode($roomsXpath . "/descendant::text()[normalize-space()][" . ($i + 2) . "]");
                $h->addRoom()->setType($type);
            }

            $h->booked()
                ->guests($this->http->FindSingleNode("//text()[{$this->eq($this->t('TRAVELER DETAILS'))}]/ancestor::*[(self::td or self::th) and not({$this->eq($this->t('TRAVELER DETAILS'))})][1]//text()[{$this->contains($this->t("Adults"))}]", null, true, "/\b(\d+) *{$this->opt($this->t("Adults"))}/"))
                ->kids($this->http->FindSingleNode("//text()[{$this->eq($this->t('TRAVELER DETAILS'))}]/ancestor::*[(self::td or self::th) and not({$this->eq($this->t('TRAVELER DETAILS'))})][1]//text()[{$this->contains($this->t("Children"))}]", null, true, "/\b(\d+) *{$this->opt($this->t("Children"))}/"))
            ;
        } else {
            // Type 1 - without text "CHECK-IN" and "CHECK-OUT"
            $this->logger->debug('type 1');
            $xpathLeft = "//*[{$this->eq($this->t('ROOM DETAILS'))}]/ancestor::*[ {$xpathCell} and preceding-sibling::*[normalize-space()] ][1]/preceding-sibling::*[normalize-space()]";

            $hotelName = $this->http->FindSingleNode($xpathLeft . "/preceding::tr[ count(*)=2 and *[1][descendant::img] and *[2][normalize-space()] ][1]/*[2]/descendant::*[ {$xpathP} and normalize-space() and following-sibling::*[{$xpathP} and normalize-space()] ][1]");
            $address = $this->http->FindSingleNode($xpathLeft . "/preceding::tr[ count(*)=2 and *[1][descendant::img] and *[2][normalize-space()] ][1]/*[2]/descendant::*[ {$xpathP} and normalize-space() and preceding-sibling::*[{$xpathP} and normalize-space()] ][1]");

            if (empty($hotelName) && empty($address)) {
                $row = $this->http->FindNodes("//img[contains(@src,'global_calendar_in.png')]/preceding::text()[normalize-space()][position() < 5]");

                if (preg_match("/^\s*" . $this->opt($this->t("CONFIRMATION NUMBER")) . "\s*\n\s*\d{5,}\n(?<name>.+)\n(?<address>.+)$/",
                        implode("\n", $row), $m)
                    && stripos($this->emailSubject, $m['name']) !== false) {
                    $hotelName = $m['name'];
                    $address = $m['address'];
                }
            }
            $h->hotel()
                ->name($hotelName)
                ->address($address);

            $checkIn = $this->http->FindSingleNode($xpathLeft . "/descendant::tr[ count(*)=2 and *[1][descendant::img[contains(@src,'_in.')]] and *[2][normalize-space()] ]/*[2]") ?? $this->http->FindSingleNode($xpathLeft . "/descendant::tr[ count(*)=2 and *[1][descendant::img[not(@src)]] and *[2][normalize-space()] ][1]/*[2]");

            if (empty($checkIn)) {
                $checkIn = $this->http->FindSingleNode("//img[contains(@src,'/global_calendar_in.png')]/following::text()[normalize-space()][1]");
            }

            if (empty($checkIn)) {
                $checkIn = $this->http->FindSingleNode("//*[(normalize-space()='ROOM DETAILS')]/ancestor::*[ (self::td or self::th) and preceding-sibling::*[normalize-space()] ][1]/preceding::text()[string-length()>3][3]");
            }
            $h->booked()->checkIn2($checkIn);

            $checkOut = $this->http->FindSingleNode($xpathLeft . "/descendant::tr[ count(*)=2 and *[1][descendant::img[contains(@src,'_out.')]] and *[2][normalize-space()] ]/*[2]") ?? $this->http->FindSingleNode($xpathLeft . "/descendant::tr[ count(*)=2 and *[1][descendant::img[not(@src)]] and *[2][normalize-space()] ][2]/*[2]");

            if (empty($checkOut)) {
                $checkOut = $this->http->FindSingleNode("//img[contains(@src,'/global_calendar_out.png')]/following::text()[normalize-space()][1]");
            }

            if (empty($checkOut)) {
                $checkOut = $this->http->FindSingleNode("//*[(normalize-space()='ROOM DETAILS')]/ancestor::*[ (self::td or self::th) and preceding-sibling::*[normalize-space()] ][1]/preceding::text()[string-length()>3][2]");
            }
            $h->booked()->checkOut2($checkOut);

            $roomsCount = $this->http->FindSingleNode($xpathLeft . "/descendant::tr[ count(*)=2 and *[1][descendant::img[contains(@src,'_bed.')]] and *[2][normalize-space()] ]/*[2]",
                    null, true,
                    "/\b(\d{1,3})\s*{$this->opt($this->t('Room'))}/i") ?? $this->http->FindSingleNode($xpathLeft . "/descendant::tr[ count(*)=2 and *[1][descendant::img[not(@src)]] and *[2][normalize-space()] ][3]/*[2]",
                    null, true, "/\b(\d{1,3})\s*{$this->opt($this->t('Room'))}/i");

            if (empty($roomsCount)) {
                $roomsCount = $this->http->FindSingleNode("//img[contains(@src,'/global_hotel_bed.png')]/following::text()[normalize-space()][1]",
                    null, true, "/\b(\d{1,3})\s*{$this->opt($this->t('Room'))}/");
            }

            if (empty($roomsCount)) {
                $roomsCount = $this->http->FindSingleNode("//*[(normalize-space()='ROOM DETAILS')]/ancestor::*[ (self::td or self::th) and preceding-sibling::*[normalize-space()] ][1]/preceding::text()[string-length()>3][1]",
                    null, true, "/\b(\d{1,3})\s*{$this->opt($this->t('Room'))}/");
            }
            $h->booked()->rooms($roomsCount);

            $room = $h->addRoom();

            $roomDetailsHtml = $this->http->FindHTMLByXpath("//*[{$this->eq($this->t('ROOM DETAILS'))}]/following-sibling::node()[normalize-space()][1]");
            $roomDetails = $this->htmlToText($roomDetailsHtml);
            $room->setType($roomDetails);
        }

        $cancellation = $this->http->FindSingleNode("//*[{$this->eq($this->t('ROOM RESTRICTIONS AND CANCELLATION POLICY'))}]/following-sibling::node()[normalize-space()][1]");
        $h->general()->cancellation($cancellation);

        if (preg_match('/This reservation is non-refundable and cannot be cancelled or changed\./i', $cancellation)) {
            $h->booked()->nonRefundable();
        } elseif (preg_match('/Cancellations or changes made after (?<time>\d{1,2}(?::\d{2})?(?:\s*[AaPp]\.?[Mm]\.?)?)(?: \([^)(]+\))? on (?<date>.{2,}\d{4}) or no-shows are subject to a property fee equal to (?:the first nights rate plus taxes and fees|100% of the total amount paid for the reservation)\./i', $cancellation, $m)) {
            $h->booked()->deadline2($m['date'] . ' ' . $m['time']);
        }

        $travellers = $this->http->FindNodes("//*[{$this->eq($this->t('Traveler Information'))}]/following::p[ preceding-sibling::*[normalize-space()][1][{$this->starts($this->t('Room'))}] and following::text()[normalize-space()][1][{$this->eq($this->t('Requests'))}] ]", null, '/^[[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]]$/u');

        if (!$travellers) {
            $travellers = $this->http->FindNodes("//img[{$this->eq($this->t('Traveler Information'), '@alt')}]/following::p[ preceding-sibling::*[normalize-space()][1][{$this->starts($this->t('Room'))}] and following::text()[normalize-space()][1][{$this->eq($this->t('Requests'))}] ]",
                null, '/^[[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]]$/u');
        }

        if (!$travellers) {
            $travellers = $this->http->FindNodes("//text()[contains(normalize-space(), 'main guest')]/following::text()[normalize-space()][1]");
        }

        $travellers = array_filter($travellers);
        $h->general()->travellers($travellers);

        $xpathCost = "//*[{$this->eq($this->t('Cost and Billing Information'))} or {$this->eq($this->t('Cost and Billing Information'), '@alt')}]";

        if ($this->http->XPath->query("//text()[normalize-space()='Dollars Used']")->length > 0) {
            $total = $this->http->FindSingleNode($xpathCost . "/following::*[{$xpathCell} and {$this->eq($this->t('totalPayment2'))}]/following-sibling::*[{$xpathCell} and normalize-space()]");

            if (empty($total)) {
                $total = array_sum(str_replace(',', '', $this->http->FindNodes($xpathCost . "/following::*[{$xpathCell} and {$this->eq($this->t('totalPayment2'))}]/following-sibling::*[{$xpathCell} and normalize-space()]", null, "/([\d\.\,]+)/")));

                if (!empty($total)) {
                    $h->price()
                        ->total($total);
                }

                $currency = array_unique($this->http->FindNodes($xpathCost . "/following::*[{$xpathCell} and {$this->eq($this->t('totalPayment2'))}]/following-sibling::*[{$xpathCell} and normalize-space()]", null, "/(\D)\s*([\d\.\,]+)/"));

                if (!empty($currency[0])) {
                    $h->price()
                        ->currency($currency[0]);
                }
            }

            if (preg_match('/^(?<currency>[^\d)(]+) ?(?<amount>\d[,.\'\d]*)$/', $total, $m)) {
                // $2,370.33
                $h->price()
                    ->total($this->normalizeAmount($m['amount']))
                    ->currency($this->normalizeCurrency($m['currency']));
            }
        } else {
            $total = $this->http->FindSingleNode($xpathCost . "/following::*[{$xpathCell} and {$this->eq($this->t('totalPayment'))}]/following-sibling::*[{$xpathCell} and normalize-space()]");

            if (empty($total)) {
                $total = array_sum(str_replace(',', '', $this->http->FindNodes($xpathCost . "/following::*[{$xpathCell} and {$this->eq($this->t('totalPayment'))}]/following-sibling::*[{$xpathCell} and normalize-space()]", null, "/([\d\.\,]+)/")));

                if (!empty($total)) {
                    $h->price()
                        ->total($total);
                }

                $currency = array_unique($this->http->FindNodes($xpathCost . "/following::*[{$xpathCell} and {$this->eq($this->t('totalPayment'))}]/following-sibling::*[{$xpathCell} and normalize-space()]", null, "/(\D)\s*([\d\.\,]+)/"));

                if (!empty($currency[0])) {
                    $h->price()
                        ->currency($currency[0]);
                }
            }

            if (preg_match('/^(?<currency>[^\d)(]+) ?(?<amount>\d[,.\'\d]*)$/', $total, $m)) {
                // $2,370.33
                $h->price()
                    ->total($this->normalizeAmount($m['amount']))
                    ->currency($this->normalizeCurrency($m['currency']));
            }
        }

        $taxes = $this->http->FindSingleNode("(" . $xpathCost . "/following::text()[{$this->starts($this->t('Estimated Taxes and Fees'))}]/ancestor::*[ {$xpathCell} and following-sibling::*[{$xpathCell} and normalize-space()] ][1]/following-sibling::*[{$xpathCell} and normalize-space()])[1]");

        if (preg_match('/\D+(?<amount>\d[,.\'\d]*)$/', $taxes, $matches)) {
            $h->price()->tax($this->normalizeAmount($matches['amount']));
        }
        $cost = $this->http->FindSingleNode("(" . $xpathCost . "/following::text()[{$this->contains($this->t('Room'))} and contains(.,'x') and {$this->contains($this->t('Night'))}]/ancestor::*[ {$xpathCell} and following-sibling::*[{$xpathCell} and normalize-space()] ][1]/following-sibling::*[{$xpathCell} and normalize-space()])[1]");

        if (preg_match('/\D+(?<amount>\d[,.\'\d]*)$/', $cost, $matches)) {
            $h->price()->cost($this->normalizeAmount($matches['amount']));
        }
        /*$fee1 = $this->http->FindSingleNode("(" . $xpathCost . "/following::text()[{$this->eq($this->t('Due at Hotel'))}]/ancestor::*[ {$xpathCell} and following-sibling::*[{$xpathCell} and normalize-space()] ][1]/following-sibling::*[{$xpathCell} and normalize-space()])[1]");

        if (preg_match('/\D+(?<amount>\d[,.\'\d]*)$/', $fee1, $matches)) {
            $feeName = $this->http->FindSingleNode("(" . $xpathCost . "/following::text()[{$this->eq($this->t('Due at Hotel'))}])[1]");
            $h->price()->fee($feeName, $this->normalizeAmount($matches['amount']));
        }*/

        $spentAwards = $this->http->FindSingleNode("//text()[normalize-space()='Points Used']/following::text()[normalize-space()][1]");

        if (!empty($spentAwards)) {
            $h->price()
                ->spentAwards($spentAwards);
        }
    }

    private function detectBody(): bool
    {
        if (!isset($this->detectors)) {
            return false;
        }

        foreach ($this->detectors as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (!is_string($phrase)) {
                    continue;
                }

                if ($this->http->XPath->query("//node()[{$this->contains($phrase)}]")->length > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['CONFIRMATION NUMBER']) || empty($phrases['ROOM DETAILS'])) {
                continue;
            }

            if ($this->http->XPath->query("//node()[{$this->contains($phrases['CONFIRMATION NUMBER'])}]")->length > 0
                && $this->http->XPath->query("//node()[{$this->contains($phrases['ROOM DETAILS'])}]")->length > 0
            ) {
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

    private function htmlToText(?string $s, bool $brConvert = true): string
    {
        if (!is_string($s) || $s === '') {
            return '';
        }
        $s = str_replace("\r", '', $s);
        $s = preg_replace('/<!--.*?-->/s', '', $s); // comments

        if ($brConvert) {
            $s = preg_replace('/\s+/', ' ', $s);
            $s = preg_replace('/<[Bb][Rr]\b.*?\/?>/', "\n", $s); // only <br> tags
        }
        $s = preg_replace('/<[A-z][A-z\d:]*\b.*?\/?>/', '', $s); // opening tags
        $s = preg_replace('/<\/[A-z][A-z\d:]*\b[ ]*>/', '', $s); // closing tags
        $s = html_entity_decode($s);
        $s = str_replace(chr(194) . chr(160), ' ', $s); // NBSP to SPACE

        return trim($s);
    }

    /**
     * Formatting over 3 steps:
     * 11 507.00  ->  11507.00
     * 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
     * 18800,00   ->  18800.00  |  2777,0    ->  2777.0.
     *
     * @param string|null $s Unformatted string with amount
     * @param string|null $decimals Symbols floating-point when non-standard decimals (example: 1,258.943)
     */
    private function normalizeAmount(?string $s, ?string $decimals = null): ?float
    {
        if (!empty($decimals) && preg_match_all('/(?:\d+|' . preg_quote($decimals, '/') . ')/', $s, $m)) {
            $s = implode('', $m[0]);

            if ($decimals !== '.') {
                $s = str_replace($decimals, '.', $s);
            }
        } else {
            $s = preg_replace('/\s+/', '', $s);
            $s = preg_replace('/[,.\'](\d{3})/', '$1', $s);
            $s = preg_replace('/,(\d{1,2})$/', '.$1', $s);
        }

        return is_numeric($s) ? (float) $s : null;
    }

    /**
     * @param string $string Unformatted string with currency
     */
    private function normalizeCurrency(string $string): string
    {
        $string = trim($string);
        $currencies = [
            // do not add unused currency!
            'USD' => ['US$'],
            'EUR' => ['€'],
            'GBP' => ['£'],
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

    private function normalizeTime(string $time): string
    {
        $in = [
            // 11AM
            '#^\s*(\d{1,2})\s*([AP]M)\s*$#ui',
            // noon
            '#^\s*noon\s*$#ui',
        ];
        $out = [
            '$1:00 $2',
            '12:00',
        ];
        $time = preg_replace($in, $out, $time);

        return $time;
    }
}
