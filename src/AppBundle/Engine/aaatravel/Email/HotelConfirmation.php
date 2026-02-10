<?php

namespace AwardWallet\Engine\aaatravel\Email;

use AwardWallet\Schema\Parser\Email\Email;

class HotelConfirmation extends \TAccountChecker
{
    public $mailFiles = "aaatravel/it-85676265.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'confNumber'     => ['Confirmation #'],
            'Reference #'    => ['Reference #'],
            'checkIn'        => ['Check-In'],
            'checkOut'       => ['Check-Out'],
            'statusVariants' => ['Confirmed', 'CONFIRMED'],
            'feeNames'       => ['Taxes and Fees', 'Taxes & Fees'],
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@aaatravelsupport.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['subject'], 'AAA Travel Hotel Confirmation #') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//*[contains(normalize-space(),"Thank you for booking with AAA") or contains(.,"@aaatravelsupport.com") or contains(.,"@aaaohio.com")]')->length === 0) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }
        $email->setType('HotelConfirmation' . ucfirst($this->lang));

        $hotels = $this->http->XPath->query("//tr[{$this->starts($this->t('checkIn'))}]/following-sibling::tr[{$this->starts($this->t('checkOut'))}]/ancestor::table[1]");

        foreach ($hotels as $hRoot) {
            $this->parseHotel($email, $hRoot);
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

    private function parseHotel(Email $email, \DOMNode $root): void
    {
        $h = $email->add()->hotel();

        $xpathHeader = "descendant::tr[ *[{$this->eq($this->t('confNumber'))}] ]/preceding-sibling::tr[normalize-space()]";

        if ($this->http->XPath->query($xpathHeader)->length === 0) {
            $xpathHeader = "descendant::tr[ *[{$this->eq($this->t('Reference #'))}] ]/preceding-sibling::tr[normalize-space()]";
        }

        $hotelName = $this->http->FindSingleNode($xpathHeader . "/descendant-or-self::tr[ *[2] ]/*[normalize-space()][1]", $root);

        $status = $this->http->FindSingleNode($xpathHeader . "/descendant-or-self::tr[ *[2] ]/*[normalize-space()][2][ {$this->eq($this->t('statusVariants'))} or descendant-or-self::*[contains(@style,'green')] ]", $root);
        $h->general()->status($status);

        $confirmation = $this->http->FindSingleNode("descendant::tr/*[{$this->eq($this->t('confNumber'))}]/following-sibling::*[normalize-space()][last()]", $root, true, '/^[-A-Z\d]{5,}$/');
        $reference = $this->http->FindSingleNode("descendant::tr/*[{$this->eq($this->t('Reference #'))}]/following-sibling::*[normalize-space()][last()]", $root, true, '/^[-A-Z\d]{5,}$/');

        if ($confirmation) {
            $confirmationTitle = $this->http->FindSingleNode("descendant::tr/*[{$this->eq($this->t('confNumber'))}]", $root, true, '/^(.+?)[\s:：]*$/u');
            $h->general()->confirmation($confirmation, $confirmationTitle, $reference !== null);
        }

        if ($reference) {
            $referenceTitle = $this->http->FindSingleNode("descendant::tr/*[{$this->eq($this->t('Reference #'))}]", $root, true, '/^(.+?)[\s:：]*$/u');
            $h->general()->confirmation($reference, $referenceTitle);
        }

        $checkIn = $this->http->FindSingleNode("descendant::tr/*[{$this->eq($this->t('checkIn'))}]/following-sibling::*[normalize-space()][last()]", $root, true, '/^.*\d.*$/');
        $h->booked()->checkIn2($checkIn);

        $checkOut = $this->http->FindSingleNode("descendant::tr/*[{$this->eq($this->t('checkOut'))}]/following-sibling::*[normalize-space()][last()]", $root, true, '/^.*\d.*$/');
        $h->booked()->checkOut2($checkOut);

        $travellers = [];
        $guestsRows = $this->http->FindNodes("descendant::tr/*[{$this->eq($this->t('Guests'))}]/following-sibling::*[normalize-space()][last()]/descendant::text()[normalize-space()][not({$this->eq($this->t('Membership Number'))}) and not(preceding::text()[{$this->eq($this->t('Membership Number'))}])]", $root);

        foreach ($guestsRows as $gRow) {
            if (preg_match("/\b(\d{1,3})\s*{$this->opt($this->t('Adult'))}.+\b(\d{1,3})\s*{$this->opt($this->t('Child'))}/", $gRow, $m)) {
                // 2 Adults, 1 Child (11 year(s) old)
                $h->booked()->guests($m[1])->kids($m[2]);
            } elseif (preg_match("/\b(\d{1,3})\s*{$this->opt($this->t('Adult'))}/", $gRow, $m)) {
                // 2 Adults
                $h->booked()->guests($m[1]);
            } elseif (preg_match("/\b(\d{1,3})\s*{$this->opt($this->t('Child'))}/", $gRow, $m)) {
                // 1 Child
                $h->booked()->kids($m[1]);
            } elseif (preg_match("/^[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]$/u", $gRow)) {
                $travellers[] = $gRow;
            }
        }

        if (count($travellers)) {
            $h->general()->travellers($travellers);
        }

        $membershipNumber = $this->http->FindSingleNode("descendant::tr/*[{$this->eq($this->t('Membership Number'))}]/following-sibling::*[normalize-space()][last()]", $root, true, '/^[-A-Z\d]{5,}$/');

        if ($membershipNumber) {
            $h->program()->account($membershipNumber, false);
        }

        $room = $h->addRoom();
        $roomType = $this->http->FindSingleNode("descendant::tr/*[{$this->eq($this->t('Room'))}]/following-sibling::*[normalize-space()][last()]", $root);
        $room->setType($roomType);

        $address = $this->http->FindSingleNode("descendant::tr/*[{$this->eq($this->t('Address'))}]/following-sibling::*[normalize-space()][last()]", $root);
        $phone = $this->http->FindSingleNode("descendant::tr/*[{$this->eq($this->t('Phone'))}]/following-sibling::*[normalize-space()][last()]", $root, true, "/^[+(\d][-. \d)(]{5,}[\d)]$/");
        $h->hotel()
            ->name($hotelName)
            ->address($address)
            ->phone($phone)
        ;

        $xpathPayment = "//text()[{$this->eq($this->t('Payment Summary'))}]";
        $totalPrice = $this->http->FindSingleNode($xpathPayment . "/following::tr/*[{$this->eq($this->t('Total'))}]/following-sibling::*[normalize-space()][last()]");

        if (preg_match('/^(?<currency>[^\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*)$/', $totalPrice, $matches)) {
            // $370.75
            $h->price()
                ->total($this->normalizeAmount($matches['amount']))
                ->currency($matches['currency']);

            if ($roomType) {
                $baseFare = $this->http->FindSingleNode($xpathPayment . "/following::tr/*[{$this->eq($roomType)}]/following-sibling::*[normalize-space()][last()]");

                if ($baseFare === null) {
                    $baseFareTitle = $this->http->FindSingleNode($xpathPayment . "/following::tr[count(*[normalize-space()])=2][1]/*[normalize-space()][1]");

                    if (stripos($roomType, $baseFareTitle) !== false) {
                        $baseFare = $this->http->FindSingleNode($xpathPayment . "/following::tr[count(*[normalize-space()])=2][1]/*[normalize-space()][2]");
                    }
                }

                if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.\'\d ]*?)$/', $baseFare, $m)) {
                    $h->price()->cost($this->normalizeAmount($m['amount']));
                }
            }

            $feeRows = $this->http->XPath->query($xpathPayment . "/following::tr[ *[1][{$this->eq($this->t('feeNames'))}] and following::tr[*[1][{$this->eq($this->t('Total'))}]] and *[2][normalize-space()] ]");

            foreach ($feeRows as $feeRow) {
                $feeCharge = $this->http->FindSingleNode('*[2]', $feeRow);

                if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.\'\d ]*)$/', $feeCharge, $m)) {
                    $feeName = $this->http->FindSingleNode('*[1]', $feeRow);
                    $h->price()->fee($feeName, $this->normalizeAmount($m['amount']));
                }
            }
        }
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang)) {
                continue;
            }

            if ((!empty($phrases['confNumber']) || !empty($phrases['Reference #']))
                && $this->http->XPath->query("//*[{$this->contains($phrases['confNumber'])} or {$this->contains($phrases['Reference #'])}]")->length > 0
                && !empty($phrases['checkIn'])
                && $this->http->XPath->query("//*[{$this->contains($phrases['checkIn'])}]")->length > 0
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
}
