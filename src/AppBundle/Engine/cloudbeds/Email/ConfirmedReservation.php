<?php

namespace AwardWallet\Engine\cloudbeds\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
// use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ConfirmedReservation extends \TAccountChecker
{
    public $mailFiles = "cloudbeds/it-277417512.eml, cloudbeds/it-278610789.eml, cloudbeds/it-97899859.eml, cloudbeds/it-98466118.eml, cloudbeds/it-99116873.eml, cloudbeds/it-651685125.eml";

    private $subjects = [
        'en' => ['Confirmed Reservation - ', ', Thank you for your reservation - Confirmation', ' - Reservation Received, Pending Confirmation'],
    ];

    private $dateFormatDMY;
    private $emailSubject;
    private $lang = '';
    private $night;

    private static $dictionary = [
        "en" => [
            'hotelNameStart' => 'Thank you for choosing',
            'hotelNameEnd'   => ', located at',
            'tableHeaders'   => 'Arrival - Departure Adults Children Nights Total',
            'Res Id'         => ['Res Id', 'RES ID'],
            // 'Accommodations' => '',
            // 'is located at:' => '',
            // 'Phone:' => '',
            //            'Guest:' => '',
            'Arrival - Departure' => 'Arrival - Departure',
            //            'Adults' => '',
            //            'Children' => '',
            'Deposit' => 'Deposit',
            //            'Amount Paid' => '',
            //            'Grand Total' => '',
            'priceDelimiterThousands' => ',',
            'priceDelimiterDecimals'  => '.',

            //            'Check-In:' => '',
            //            'Check-Out:' => '',
            'cancellationStart' => ['Cancellation Policies:', 'Cancelation Policies:'],
            'cancellationEnd'   => 'Terms & Conditions:',
        ],
        "es" => [
            // 'hotelNameStart' => '',
            // 'hotelNameEnd' => '',
            'tableHeaders'            => 'Llegada - Salida Adultos Niños Noches Total',
            'Res Id'                  => ['NÚMERO DE RESERVA', 'Número de reserva'],
            // 'Accommodations' => '',
            // 'is located at:' => '',
            // 'Phone:' => '',
            'Guest:'                  => 'Llegadas:',
            'Arrival - Departure'     => 'Llegada - Salida',
            'Adults'                  => 'Adultos',
            'Children'                => 'Niños',
            'Deposit'                 => 'Depósito',
            'Amount Paid'             => 'Importe pagado',
            'Grand Total'             => 'TOTAL GENERAL',
            'priceDelimiterThousands' => ' ',
            'priceDelimiterDecimals'  => ',',

            'Check-In:'              => 'Entrada:',
            'Check-Out:'             => 'Salida:',
            'cancellationStart'      => 'Política de Cancelación:',
            'cancellationEnd'        => 'Términos y Condiciones:',
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        $this->emailSubject = $parser->getSubject();
        $this->parseHtml($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[.@]cloudbeds\.com$/i', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider(rtrim($headers['from'], '> ')) !== true && strpos($headers['subject'], 'GoToGate Team') === false) {
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

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->assignLang() !== true) {
            return false;
        }

        $detectCompany = [
            'cloudbeds.com', 'sayulindahotel.com', '@hotelsecreto.com', 'acasadelmundo.com',
        ];

        return $this->detectEmailFromProvider(rtrim($parser->getHeader('from'), '> '))
            || $this->http->XPath->query("//a[{$this->contains($detectCompany, '@href')}] | //*[{$this->contains($detectCompany)}]")->length > 0
            || $this->http->XPath->query("//tr[{$this->eq($this->t('tableHeaders'))}]") // any other providers (it-99116873.eml)
        ;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function parseHtml(Email $email): void
    {
        $patterns = [
            'phone'         => '[+(\d][-+. \d)(]{5,}[\d)]', // +377 (93) 15 48 52    |    (+351) 21 342 09 07    |    713.680.2992
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        ];

        $h = $email->add()->hotel();

        // General
        $confs = array_unique($this->getFields($this->t('Res Id'), "/^\s*(\d{5,}(?:\-\d{1,2})?)\s*$/"));

        foreach ($confs as $conf) {
            $h->general()
                ->confirmation($conf);
        }

        $h->general()
            ->travellers(array_unique($this->http->FindNodes("//text()[" . $this->starts($this->t('Guest:')) . "]", null, "/:\s*({$patterns['travellerName']})(?:\s*[+]\s*\d+)*$/u")), true);

        $cancellation = $this->http->FindSingleNode("//*[normalize-space() and preceding-sibling::*[{$this->eq($this->t('cancellationStart'))}] and following-sibling::*[{$this->eq($this->t('cancellationEnd'))}]]")
            ?? $this->http->FindSingleNode("//*[{$this->eq($this->t('cancellationStart'))}]/following-sibling::*[normalize-space()][1]");
        $h->general()->cancellation($cancellation, false, true);

        // Hotel
        $name = $address = $phone = null;

        if (preg_match("/Confirmed Reservation -\s*(.{3,75}?)\s*- Confirmation/", $this->emailSubject, $m)) {
            $name = $m[1];
        } else {
            $name = $this->http->FindSingleNode("//text()[{$this->starts($this->t('hotelNameStart'))}]", null, true, "/{$this->preg_implode($this->t('hotelNameStart'))}\s+(.{3,75}?)\s*{$this->preg_implode($this->t('hotelNameEnd'))}/i")
                ?? $this->http->FindSingleNode("//text()[{$this->starts($this->t('hotelNameStart'))}]", null, true, "/{$this->preg_implode($this->t('hotelNameStart'))}\s+([^,.;?!]{3,75}?)(?:\s*[,.;?!]|$)/i")
            ;
        }

        if (empty($name) && preg_match("/Reservation (Cancell?ed) -\s*(.{3,75}?)\s*- Cancell?ation\s*#\s*(\d+)$/i", $this->emailSubject, $m)) {
            $name = $m[2];

            $h->general()
                ->cancelled()
                ->cancellationNumber($m[3])
                ->status(strtolower($m[1]));
        }

        if (empty($name) && preg_match("/Confirmation of Your Reservation at\s+(.{3,75}?)\s*!/", $this->emailSubject, $m)) {
            $name = $m[1];
        }

        if (empty($name) && preg_match("/(?:^|:\s*)([^:]{3,75}?)\s*-\s*Reservation Received\s*,/", $this->emailSubject, $m)) {
            $name = $m[1];
        }

        if (!empty($name)) {
            $name = preg_replace(['/\b(Fwd|FW)\s*:\s*/i', '/^the\b\s*/'], '', $name);
        }

        if (!empty($name)) {
            $nameVariants = [$name];
            $nameVariants[] = preg_replace("/^([[:alpha:]]+)([[:alpha:]])\b/u", "$1'$2", $name); // Bananas  ->  Banana's
            $nameVariants[] = preg_replace("/^([[:alpha:]]+)'([[:alpha:]])\b/u", '$1$2', $name); // Banana's  ->  Bananas
            $nameVariants = array_unique($nameVariants);

            $hotelInfo = implode("\n", $this->http->FindNodes("(//text()[{$this->starts($this->t('Res Id'))}])[1]/preceding::text()[{$this->eq($nameVariants)}][1]/ancestor::*[not({$this->eq($nameVariants)})][1]/descendant::text()[normalize-space()]"));

            if (empty($hotelInfo)) {
                $hotelInfo = implode("\n", $this->http->FindNodes("//text()[{$this->eq($this->t('Accommodations'))}]/preceding::text()[{$this->contains($nameVariants)}][1]/ancestor::div[1]/descendant::text()[normalize-space()]"));
            }

            if (empty($hotelInfo)) {
                // it-651685125.eml
                $hotelInfo = implode("\n", $this->http->FindNodes("//text()[{$this->eq($this->t('Accommodations'))}]/preceding::text()[{$this->contains($this->t('is located at:'))}][1]/ancestor::*[ descendant::text()[normalize-space()][2] ][1]/descendant::text()[normalize-space()]"));
            }

            if (preg_match("/(?:^|\n)(?:{$this->preg_implode($nameVariants)}.{0,25}\n)+(?<address>[\s\S]+?)\n(?<phone>{$patterns['phone']})(?:\s*;|\s*\bor\b|\n|$)/i", $hotelInfo, $m)
                || preg_match("/(?:^|\n)(?:{$this->preg_implode($nameVariants)}.{0,25}\n)+(?<address>(?:.+\n){1,2})(?:\S+[@]|\s*(?<phone>(?:[+][\d \(\)\-\+\.]{5,}|\d+)))/iu", $hotelInfo, $m)
                || preg_match("/ {$this->preg_implode($this->t('is located at:'))}\n(?<address>(?:.+\n){1,3}){$this->preg_implode($this->t('Phone:'))}\s*(?<phone>{$patterns['phone']})(?:\s*;|\s*\bor\b|\n|$)/i", $hotelInfo, $m) // it-651685125.eml
            ) {
                $address = preg_replace(['/\n+/', '/(\s*,\s*)+/'], ', ', trim($m['address'], ",; \n"));

                if (!empty($m['phone'])) {
                    $phone = $m['phone'];
                }
            }
        }

        $h->hotel()->name($name)->address($address)->phone($phone, false, true);

        // Booked

        $xpath = "//tr[*[1][" . $this->eq($this->t('Arrival - Departure')) . "]]/following-sibling::tr[count(*) > 2]";
        $this->night = $this->http->FindSingleNode($xpath . "/*[4]", null, true, "/^(\d+)$/");
        $this->detectDateFormat($this->http->FindSingleNode('(' . $xpath . "/*[1])[1]"));

        $checkins = array_unique(array_filter($this->http->FindNodes($xpath . "/*[1]", null, "/^\s*([\d\/\.]+)\s*-/")));

        if (count($checkins) == 1) {
            $h->booked()
                ->checkIn($this->normalizeDate(array_shift($checkins)));
        } elseif (count($checkins) > 1) {
            /*$cis = array_filter(array_map(['this', 'normalizeDate'], $checkins));
            if (count($checkins) == count($cis)) {
                $h->booked()
                    ->checkIn(min($cis));
            }*/

            $h->booked()
                ->checkIn($this->normalizeDate($checkins[0]));
        }
        $time = $this->getField($this->t('Check-In:'), "/^\s*(\d{1,2}:\d{2}(?:\s*[ap]m)?)\s*$/i");

        if (!empty($h->getCheckInDate()) && !empty($time)) {
            $h->booked()
                ->checkIn(strtotime($time, $h->getCheckInDate()));
        }

        $checkouts = array_unique(array_filter($this->http->FindNodes($xpath . "/*[1]", null, "/^\s*.+?\s*-\s*([\d\/\.]+)\s*$/")));

        if (count($checkouts) == 1) {
            $h->booked()
                ->checkOut($this->normalizeDate(array_shift($checkouts)));
        } elseif (count($checkouts) > 1) {
            /*$cos = array_filter($checkouts);

            if (count($checkouts) == count($cos)) {
                $h->booked()
                    ->checkOut(min($cos));
            }*/

            $h->booked()
                ->checkOut($this->normalizeDate($checkouts[count($checkouts) - 1]));
        }
        $time = $this->getField($this->t('Check-Out:'), "/^\s*(\d{1,2}:\d{2}(?:\s*[ap]m)?)\s*$/i");

        if (!empty($h->getCheckOutDate()) && !empty($time)) {
            $h->booked()
                ->checkOut(strtotime($time, $h->getCheckOutDate()));
        }

        $adults = array_sum($this->http->FindNodes("//text()[{$this->starts($this->t('Arrival - Departure'))}]/ancestor::tr[1][{$this->contains($this->t('Adults'))}]/following::tr[contains(normalize-space(), '/') or contains(normalize-space(), '.')][1]/descendant::td[normalize-space()][2]",
        null, "/^\s*(\d+)\s*$/"));
        $kids = array_sum($this->http->FindNodes("//text()[{$this->starts($this->t('Arrival - Departure'))}]/ancestor::tr[1][{$this->contains($this->t('Children'))}]/following::tr[contains(normalize-space(), '/') or contains(normalize-space(), '.')][1]/descendant::td[normalize-space()][3]",
            null, "/^\s*(\d+)\s*$/"));

        $h->booked()
            ->guests($adults)
            ->kids($kids);

        // Rooms
        $types = $this->http->FindNodes("//text()[" . $this->eq($this->t('Arrival - Departure')) . "]/preceding::text()[normalize-space(.)][1]");

        foreach ($types as $type) {
            $h->addRoom()
                ->setType($type);
        }

        // Price
        $taxes = $this->http->XPath->query("//tr[td[1][" . $this->eq($this->t('Deposit')) . "]]/following-sibling::tr[td[1][" . $this->eq($this->t('Amount Paid')) . "]]/ancestor::*[1]/*");
        $isTax = false;

        foreach ($taxes as $root) {
            $name = $this->http->FindSingleNode("*[1]", $root);
            $value = $this->http->FindSingleNode("*[2]", $root);

            if (preg_match("/^\s*" . $this->preg_implode($this->t('Deposit')) . "\s*$/u", $name)) {
                $isTax = true;

                continue;
            }

            if (preg_match("/^\s*" . $this->preg_implode($this->t('Amount Paid')) . "\s*$/u", $name)) {
                break;
            }

            if ($isTax && preg_match("#^\s*([^\s\d]{1,5})\s*(\d[\d.,]*)\s*$#", $value, $m)) {
                $h->price()
                    ->fee($name, PriceHelper::parse($m[2], $this->t('priceDelimiterThousands'), $this->t('priceDelimiterDecimals')));
            }
        }
        $total = $this->getField($this->t('Grand Total'));

        if (empty($total)) {
            $total = $this->getField($this->t('Total General'));
        }

        if (preg_match("#^\s*([^\s\d]{1,5})\s*(\d[\d.,]*)\s*$#", $total, $m)) {
            $h->price()
                ->total(PriceHelper::parse($m[2], $this->t('priceDelimiterThousands'), $this->t('priceDelimiterDecimals')))
                ->currency($m[1])
            ;
        }

        $this->detectDeadLine($h);
    }

    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h): void
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (preg_match("/to cancel your reservation, please do so at least (\d+ days?) prior to your arrival date to avoid a cancelation fee which/ui",
                $cancellationText, $m)
            || preg_match("/Any reservation cancelled (\d+ days?) prior to your arrival date there’s NO CHARGE\./ui", $cancellationText, $m)
            || preg_match("/The guest can cancel free of charge until (\d+ days?) before arrival/ui", $cancellationText, $m)
            || preg_match("/If reservation is canceled prior to (\d+ days?) of arrival/ui", $cancellationText, $m)
        ) {
            $h->booked()
                ->deadlineRelative($m[1], "00:00");
        }

        if (preg_match("/Full Refund if cancelled \d+ days \((\d+ hours?)\) prior to arrival date/ui", $cancellationText, $m)
           || preg_match("/Cancellations require (\d+ hour) advance notice. /ui", $cancellationText, $m)
           || preg_match("/if you need to change or cancel your reservation please notify us by email or telephone within (\d+ hours?) so we have an opportunity to rent the room to another traveler/ui", $cancellationText, $m)
        ) {
            $h->booked()
                ->deadlineRelative($m[1]);
        }
    }

    private function getField($field, $regexp = null, $n = 1)
    {
        return $this->http->FindSingleNode("(//text()[{$this->eq($field)}]/following::text()[normalize-space(.)][1])[{$n}]", null, true, $regexp);
    }

    private function getFields($field, $regexp = null)
    {
        return $this->http->FindNodes("//text()[{$this->eq($field)}]/following::text()[normalize-space(.)][1]", null, $regexp);
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['Res Id']) || empty($phrases['Arrival - Departure'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[{$this->contains($phrases['Res Id'])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->contains($phrases['Arrival - Departure'])}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($str)
    {
        /*if (is_null($this->dateFormatDMY)) {
            return null;
        }*/

        if ($this->dateFormatDMY === true && preg_match("/^\s*(\d{1,2})\/(\d{1,2})\/(\d{4})\s*$/u", $str)) {
            $str = str_replace('/', '.', $str);
        }
        $in = [
            //            "#^[\w|\D]+\s+(\d+)\s+(\D+)\s+(\d{4})$#",
        ];
        $out = [
            //            "$1 $2 $3",
        ];
        $str = preg_replace($in, $out, $str);
//        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
//            if ($en = MonthTranslate::translate($m[1], $this->lang))
//                $str = str_replace($m[1], $en, $str);
//        }

        return strtotime($str);
    }

    private function detectDateFormat($str)
    {
        $dates = explode(" - ", $str);

        if (count($dates) == 2) {
            if (preg_match("/^\s*(\d{1,2})\/(\d{1,2})\/(\d{4})\s*$/u", trim($dates[0]), $m1)
            && preg_match("/^\s*(\d{1,2})\/(\d{1,2})\/(\d{4})\s*$/u", trim($dates[1]), $m2)) {
                if ((int) $m1[1] > 12 || (int) $m2[1] > 12) {
                    $this->dateFormatDMY = true;

                    return true;
                }

                if ($m1[2] > 12 || $m2[2] > 12) {
                    $this->dateFormatDMY = false;

                    return true;
                }

                $dx1 = strtotime($dates[1]) - strtotime($dates[0]);
                $dx2 = strtotime(str_replace("/", ".", $dates[1])) - strtotime(str_replace("/", ".", $dates[0]));

                if ($dx1 > 60 * 60 * 24 * 29 && $dx2 < 60 * 60 * 24 * 29) {
                    $this->dateFormatDMY = true;

                    return true;
                }

                if ($dx2 > 60 * 60 * 24 * 29 && $dx1 < 60 * 60 * 24 * 29) {
                    $this->dateFormatDMY = false;

                    return true;
                }

                if (!empty($this->night) && $dx2 / 60 / 60 / 24 == $this->night) {
                    $this->dateFormatDMY = true;

                    return true;
                }
            }
        }

        return false;
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

    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) { return 'contains(' . $text . ',"' . $s . '")'; }, $field)) . ')';
    }

    private function preg_implode($field): string
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '/'); }, $field)) . ')';
    }
}
