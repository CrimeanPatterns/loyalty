<?php

namespace AwardWallet\Engine\canvas\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class YourReservation extends \TAccountChecker
{
    public $mailFiles = "canvas/it-274575930.eml";
    public $subjects = [
        'Your Reservation at',
    ];

    public $lang = 'en';
    public $subject;
    public $providerCode;

    public static $detectProviders = [
        'canvas' => [
            'from'    => '@undercanvas.com',
            'subject' => [
                'Your Reservation at',
            ],
            'detectBody' => [
                '@undercanvas.com',
                'reservations@undercanvas.com',
            ],
        ],
        'yotel' => [
            'from'    => '@yotel.com',
            'subject' => [
                'Your booking number for your reservation',
            ],
            'detectBody' => [
                'www.yotel.com',
                'customer@yotel.com',
            ],
        ],
        'slh' => [
            'from'    => 'reservations@ulumresorts.com',
            'subject' => [
                'Your Reservation at ',
            ],
            'detectBody' => [
                'https://ulumresorts.com',
                '@ulumresorts.com',
            ],
        ],
    ];
    public static $dictionary = [
        "en" => [
            'Arrival Date:'  => 'Arrival Date:',
            'Infants:'       => 'Infants:',
            'cost'           => ['Total Stay Cost:', 'Stay Cost:'],
            'taxes'          => ['Taxes & Fees:', 'Taxes:'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (!isset($headers['from']) || !isset($headers['subject'])) {
            return false;
        }

        foreach (self::$detectProviders as $code => $detectProv) {
            if (empty($detectProv['subject']) || empty($detectProv['from'])
                || stripos($headers['from'], $detectProv['from']) === false
            ) {
                continue;
            }

            foreach ($detectProv['subject'] as $subject) {
                if (stripos($headers['subject'], $subject) !== false) {
                    $this->providerCode = $code;

                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        foreach (self::$detectProviders as $code => $detectProv) {
            if (!empty($detectProv['detectBody'])
                && $this->http->XPath->query("//node()[{$this->contains($detectProv['detectBody'])}]")->length > 0
            ) {
                $this->providerCode = $code;

                foreach (self::$dictionary as $dict) {
                    if (!empty($dict['Arrival Date:']) && $this->http->XPath->query("//node()[{$this->eq($dict['Arrival Date:'])}]")->length > 0
                        && !empty($dict['Infants:']) && $this->http->XPath->query("//node()[{$this->eq($dict['Infants:'])}]")->length > 0
                    ) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]undercanvas\.com/i', $from) > 0;
    }

    public function ParseHotel(Email $email): void
    {
        $xpathBold = '(self::b or self::strong or contains(translate(@style," ",""),"font-weight:bold"))';

        $patterns = [
            'phone'         => '[+(\d][-+. \d)(]{5,}[\d)]', // +377 (93) 15 48 52    |    (+351) 21 342 09 07    |    713.680.2992
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        ];

        $h = $email->add()->hotel();

        // General
        $confirmation = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Confirmation Number:'))}]");

        if (preg_match("/({$this->opt($this->t('Confirmation Number:'))})[:\s]*([-A-Z\d]{5,})$/", $confirmation, $m)) {
            $h->general()->confirmation($m[2], rtrim($m[1], ': '));
        }

        $cancellation = $this->http->FindSingleNode("//text()[normalize-space()='Cancellation Policy Description:']/following::text()[normalize-space()][1]"); // it-274575930.eml

        if (!$cancellation) {
            $cancellationText = [];
            $cancellationRows = $this->http->XPath->query("//text()[ preceding::text()[{$this->eq($this->t('Cancelation Policy'))}] and following::text()[{$this->eq($this->t('Deposit Rules'))}] ]");

            foreach ($cancellationRows as $cancellRow) {
                if ($this->http->XPath->query("ancestor::*[{$xpathBold}]", $cancellRow)->length > 0) {
                    $cancellationText = [];

                    break;
                }
                $cancellationText[] = $this->http->FindSingleNode('.', $cancellRow);
            }

            if (count($cancellationText) > 0) {
                $cancellation = implode(' ', array_unique($cancellationText));
            }
        }
        $cancellation = preg_replace("/^.{0,25}:\s*$/", '', $cancellation);

        $h->general()->cancellation($cancellation, true);

        $traveller = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->starts($this->t('Arrival Date:'))}] ][1]/ancestor::tr[ following-sibling::tr[normalize-space()] ][1]/following-sibling::tr[normalize-space()][1]/descendant::text()[normalize-space()][1][ancestor::*[{$xpathBold}]]", null, true, "/^{$patterns['travellerName']}$/u");
        $isNameFull = true;

        if (!$traveller) {
            $travellerNames = array_filter($this->http->FindNodes("//text()[{$this->starts($this->t('Dear'))}]", null, "/^{$this->opt($this->t('Dear'))}[,\s]+({$patterns['travellerName']})(?:\s*[,;:!?]|$)/u"));

            if (count(array_unique($travellerNames)) === 1) {
                $traveller = array_shift($travellerNames);
                $isNameFull = null;
            }
        }

        $h->general()->traveller($traveller, $isNameFull);

        $date = $this->http->FindSingleNode("//text()[normalize-space()='Arrival Date:']/preceding::text()[normalize-space()][1]", null, true, "/^[[:alpha:]]+\s*\d{1,2}\s*,\s*\d{4}$/u");

        if (!empty($date)) {
            $h->general()
                ->date(strtotime($date));
        }

        if (preg_match("/Your Reservation at\s*(?<hotelName>.+)\s+is\s*(?<status>\w+)\!/", $this->subject, $m)
            // Your booking number for your reservation #404720 at YOTELAIR Amsterdam Schiphol
            || preg_match("/Your booking number for your reservation #\d+ at\s*(?<hotelName>.+)/", $this->subject, $m)
        ) {
            if (!empty($m['status'])) {
                $h->general()
                    ->status($m['status']);
            }

            $h->hotel()
                ->name($m['hotelName']);

            $hotelInfo = implode("\n", $this->http->FindNodes("//img[contains(@src,'call') or contains(@name,'phone')]/ancestor::table[2]/descendant::text()[normalize-space()]"));

            if (preg_match("/{$m['hotelName']}\s*(?<address>.{3,100}?)\n+(?<phone>{$patterns['phone']})\n/", $hotelInfo, $m)) {
                $h->hotel()
                    ->address($m['address'])
                    ->phone($m['phone']);
            }
        }
        $checkIn = strtotime($this->http->FindSingleNode("//text()[normalize-space()='Arrival Date:']/ancestor::tr[1]/descendant::td[2]", null, true, "/^[[:alpha:]]+\s+\d{1,2}\s+\d{4}$/"));

        $checkInTime = $this->http->FindSingleNode("//text()[normalize-space()='Arrival Time:']/ancestor::tr[1]/descendant::td[2]", null, true, "/^\s*(\d{1,2}:\d{2}\s*[ap]m)\s*$/i");

        if (empty($checkInTime)) {
            $checkInTime = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'You may check in any time after')]", null, true, "/{$this->opt($this->t('You may check in any time after'))}\s*(\d+\s*[ap]m)\b/i");
        }

        if ($checkIn && $checkInTime) {
            $checkIn = strtotime($checkInTime, $checkIn);
        }

        $checkOut = strtotime($this->http->FindSingleNode("//text()[normalize-space()='Departure Date:']/ancestor::tr[1]/descendant::td[2]", null, true, "/^[[:alpha:]]+\s+\d{1,2}\s+\d{4}$/"));
        $checkOutTime = $this->http->FindSingleNode("//text()[normalize-space()='Departure Time:']/ancestor::tr[1]/descendant::td[2]", null, true, "/^\s*(\d{1,2}:\d{2}\s*[ap]m)\s*$/i");

        if (empty($checkOutTime)) {
            $checkOutTime = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Check-out is at')]",
                null, true, "/{$this->opt($this->t('Check-out is at'))}\s*(\d+\s*[ap]m)\b/i");
        }

        if ($checkOut && $checkOutTime) {
            $checkOut = strtotime($checkOutTime, $checkOut);
        }

        $h->booked()
            ->checkIn($checkIn)
            ->checkOut($checkOut)
            ->guests($this->http->FindSingleNode("//text()[normalize-space()='Adults:']/ancestor::tr[1]/descendant::td[2]", null, true, "/^(\d+)$/"));

        $children = $this->http->FindSingleNode("//text()[normalize-space()='Children:']/ancestor::tr[1]/descendant::td[2]", null, true, "/^(\d+)$/");
        $infants = $this->http->FindSingleNode("//text()[normalize-space()='Infants:']/ancestor::tr[1]/descendant::td[2]", null, true, "/^(\d+)$/");

        if ($children !== null || $infants !== null) {
            $h->booked()
                ->kids($children ?? 0 + $infants ?? 0);
        }

        $priceText = $this->http->FindSingleNode("//text()[normalize-space()='Amount Paid:']/ancestor::tr[1]/descendant::td[2]");

        if (preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $priceText, $matches)) {
            // $1,732.10
            $currency = $this->normalizeCurrency($matches['currency']);
            $h->price()->currency($currency)->total(PriceHelper::parse($matches['amount'], $currency));

            $cost = $this->http->FindSingleNode("//text()[{$this->eq($this->t('cost'))}]/ancestor::tr[1]/descendant::td[2]", null, true, "/^.*\d.*$/");

            if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.‘\'\d ]*?)$/u', $cost, $m)) {
                $h->price()->cost(PriceHelper::parse($m['amount'], $currency));
            }

            $tax = $this->http->FindSingleNode("//text()[{$this->eq($this->t('taxes'))}]/ancestor::tr[1]/descendant::td[2]", null, true, "/^.*\d.*$/");

            if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.‘\'\d ]*?)$/u', $tax, $m)) {
                $h->price()->tax(PriceHelper::parse($m['amount'], $currency));
            }

            $fee = $this->http->FindSingleNode("//text()[normalize-space()='Resort Fee:']/ancestor::tr[1]/descendant::td[2]", null, true, "/^.*\d.*$/");

            if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.‘\'\d ]*?)$/u', $fee, $m)) {
                $h->price()->fee('Resort Fee', PriceHelper::parse($m['amount'], $currency));
            }
        }

        $roomType = $this->http->FindSingleNode("//text()[normalize-space()='Room Type:']/ancestor::tr[1]/descendant::td[2]");

        if (!empty($roomType)) {
            $h->addRoom()->setType($roomType);
        }

        if (preg_match("/^You have up until\s+(?<prior>\d{1,3} days?)\s+prior to your arrival date to cancell? for a 100% refund\./i", $cancellation, $m)
        ) {
            $h->booked()->deadlineRelative($m['prior'], '00:00');
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->subject = $parser->getSubject();
        $this->ParseHotel($email);

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

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "starts-with(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(.), \"{$s}\")";
        }, $field));
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

    private function opt($field): string
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }

    private function normalizeCurrency(string $string): string
    {
        $string = trim($string);
        $currencies = [
            'GBP' => ['£'],
            'EUR' => ['€'],
            'USD' => ['US$'],
            'SGD' => ['SG$'],
            'ZAR' => ['R'],
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
}
