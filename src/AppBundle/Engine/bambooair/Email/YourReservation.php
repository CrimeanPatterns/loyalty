<?php

namespace AwardWallet\Engine\bambooair\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class YourReservation extends \TAccountChecker
{
    public $mailFiles = "bambooair/it-148613561.eml, bambooair/it-163618809-sunex.eml, bambooair/it-166356019-flybe.eml, bambooair/it-198489405-sunex.eml, bambooair/it-210317751.eml";
    public $subjects = [
        'Your Reservation Details',
    ];
    public $lang = '';

    public $detectLang = [
        "en" => ['Passengers', 'Itinerary'],
        "vi" => ['Hành khách'],
    ];

    public static $dictionary = [
        "en" => [
            'flightRoute'  => ['Outbound Flight', 'Outbound flight', 'Inbound Flight', 'Inbound flight'],
            'confNumber'   => ['Booking number:', 'Booking Reference:'],
            'flightNumber' => ['Flight No:', 'Flight No.:'],
            'eTicket'      => ['E-ticket No.', 'Ticket No'],
            'seat'         => ['Seat:', 'Seat :', 'Seats:', 'Seats :'],
            'totalPrice'   => ['Amount', 'Grand Total', 'Total amount'],
        ],

        "vi" => [
            "Thank you for choosing Bamboo Airways" => ["Cảm ơn Quý khách đã lựa chọn Bamboo Airways"],
            'Itinerary'                             => ['Thông tin hành trình'],
            'flightRoute'                           => ['Khởi hành'],
            'confNumber'                            => ['Mã đặt chỗ:'],
            'flightNumber'                          => ['Số hiệu chuyến bay:'],
            'eTicket'                               => ['Số vé điện tử'],
            //'seat'         => ['Seat:', 'Seat :', 'Seats:', 'Seats :'],
            'totalPrice'    => ['Tổng cộng'],
            'Date of birth' => ['Ngày sinh'],
            'Booking date:' => ['Ngày đặt chỗ:'],
            'Fare'          => ['Giá vé'],
        ],
    ];

    private $providerCode = '';

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@bambooairways.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (preg_match($headers['subject'], $subject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $this->detectLang();

        if ($this->assignProvider($parser->getHeaders())) {
            return $this->http->XPath->query("//tr[{$this->eq($this->t('Itinerary'))}]")->length > 0
                && $this->http->XPath->query("//tr[{$this->eq($this->t('flightRoute'))}]")->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]bambooairways\.com$/', $from) > 0;
    }

    public function ParseFlight(Email $email): void
    {
        $xpathBold = '(self::b or self::strong or ancestor-or-self::*[contains(@style,"bold")])';
        $xpathTime = '(starts-with(translate(normalize-space(),"0123456789：.Hh","∆∆∆∆∆∆∆∆∆∆::::"),"∆:∆∆") or starts-with(translate(normalize-space(),"0123456789：.Hh","∆∆∆∆∆∆∆∆∆∆::::"),"∆∆:∆∆"))';

        $f = $email->add()->flight();

        $imgAltTextFull = implode("\n", $this->http->FindNodes("//img[normalize-space(@alt)]/@alt"));

        $patterns['travellerName'] = '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]\.?';

        $isNameFull = true;
        $travellers = array_filter($this->http->FindNodes("//text()[{$this->eq($this->t('Date of birth'))}]/preceding::text()[normalize-space()][1]", null, "/^{$patterns['travellerName']}$/u"));

        if (count($travellers) === 0) {
            // it-198489405-sunex.eml
            $travellerNames = array_filter($this->http->FindNodes("//*[not(.//tr) and {$this->starts($this->t('Dear'))}]", null, "/^{$this->opt($this->t('Dear'))}[,\s]+({$patterns['travellerName']})(?:\s*[,;:!?]|$)/u"));

            if (count(array_unique($travellerNames)) === 1) {
                $travellers = [array_shift($travellerNames)];
                $isNameFull = null;
            }
        }

        $f->general()->travellers(preg_replace('/^(?:MISS|MRS|MR|MS)[.\s]+(.{2,})$/i', '$1', $travellers), $isNameFull);

        // it-198489405-sunex.eml
        $isReady = $this->http->XPath->query("//*[not(.//tr) and {$this->contains($this->t('Your flight'))} and {$this->contains($this->t('is ready for check-in'))}]")->length > 0;

        $confirmation = $this->http->FindSingleNode("//text()[{$this->starts($this->t('confNumber'))}]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('confNumber'))}\s*([A-Z\d]+)$/")
            ?? $this->re("/{$this->opt($this->t('confNumber'))}\s*([A-Z\d]{5,})\s*(?:{$this->opt($this->t('Issued by'))}|$)/i", $imgAltTextFull);

        if (!$isReady || $isReady && $confirmation) {
            $f->general()->confirmation($confirmation);
        } elseif ($isReady && !$confirmation) {
            $f->general()->noConfirmation();
        }

        $bookingDate = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Booking date:'))}]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Booking date:'))}\s*(.*\d.*?)[\sHh]*$/")
            ?? $this->re("/{$this->opt($this->t('Booking date:'))}\s*(.*?\d.*?)[\sHh]*(?:{$this->opt($this->t('confNumber'))}|$)/i", $imgAltTextFull);

        if (!$isReady || $isReady && $bookingDate) {
            $f->general()->date($this->normalizeDate($bookingDate));
        }

        $priceText = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('totalPrice'))}] ]/*[normalize-space()][2]", null, true, '/^.*\d.*$/');

        if (preg_match("/^(?<amount>\d[,.\'\d ]*?)[ ]*(?<currency>[^\-\d)(]+)$/", $priceText, $matches)) {
            // 5.270,04 TRY
            $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
            $f->price()->total(PriceHelper::parse($matches['amount'], $currencyCode))->currency($matches['currency']);

            $cost = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Fare'))}] ]/*[normalize-space()][2]", null, true, '/^.*\d.*$/');

            if (preg_match('/^(?<amount>\d[,.\'\d ]*?)[ ]*(?:' . preg_quote($matches['currency'], '/') . ')?$/', $cost, $m)) {
                $f->price()->cost(PriceHelper::parse($m['amount'], $currencyCode));
            }

            $feeNodes = $this->http->XPath->query("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Fare'))}] ]/following-sibling::tr[count(*[normalize-space()])=2]");

            foreach ($feeNodes as $feeRoot) {
                if ($this->http->XPath->query("*[normalize-space()][1]/descendant::text()[normalize-space()][1]/ancestor::*[{$xpathBold}]", $feeRoot)->length > 0) {
                    continue;
                }

                $feeName = $this->http->FindSingleNode('*[normalize-space()][1]', $feeRoot);
                $feeSum = $this->http->FindSingleNode('*[normalize-space()][2]', $feeRoot, true, '/^.*\d.*$/');

                if (!empty($feeName) && preg_match('/^(?<amount>\d[,.\'\d ]*?)[ ]*(?:' . preg_quote($matches['currency'], '/') . ')?$/', $feeSum, $m)) {
                    $f->price()->fee($feeName, PriceHelper::parse($m['amount'], $currencyCode));
                }
            }
        }

        $tickets = array_filter($this->http->FindNodes("//text()[{$this->eq($this->t('eTicket'))}]/following::text()[normalize-space()][1]", null, '/^\d{3}(?: | ?- ?)?\d{5,}[\s\/]*(?: | ?- ?)?\d{1,}$/'));

        if (count($tickets) > 0) {
            $f->setTicketNumbers(explode(" / ", implode(" / ", $tickets)), false);
        }

        $nodes = $this->http->XPath->query("//tr[ *[normalize-space()][1][{$xpathTime}] and preceding-sibling::tr[normalize-space()] ]/following-sibling::tr[normalize-space()][1][ *[normalize-space()][1][{$xpathTime}] and following-sibling::tr[normalize-space()] ]/ancestor::table[1]");

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $date = $this->http->FindSingleNode("descendant::text()[normalize-space()][2]", $root, true, "/^.*\d{4}.*$/u");

            if (empty($date)) {
                $date = $this->http->FindSingleNode("descendant::text()[normalize-space()][2]/preceding::text()[contains(normalize-space(), ',')][1]", $root, true, "/^.*\d{4}.*$/u");
            }

            $flight = $this->http->FindSingleNode("descendant::text()[{$this->starts($this->t('flightNumber'))}]", $root)
                ?? $this->http->FindSingleNode("descendant::tr[ preceding-sibling::tr/*[normalize-space()][1][{$xpathTime}] and *[normalize-space()][1][{$xpathTime}] ]/following-sibling::tr[normalize-space() and not({$this->starts($this->t('Operator'))})][1]", $root)
            ;

            if (preg_match("/{$this->opt($this->t('flightNumber'))}\s*(?-i)(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<number>\d+)$/iu", $flight, $m) // Flight No: QH191
                || preg_match("/^.{2,}?\s+(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])(?<number>\d+)$/", $flight, $m) // SunExpress XQ881
            ) {
                $s->airline()->name($m['name'])->number($m['number']);
            }

            $depTime = $this->http->FindSingleNode("./descendant::text()[contains(normalize-space(), ':')][1]", $root, true, "/^([\d\:]+)$/");
            $s->departure()
                ->code($this->http->FindSingleNode("./descendant::text()[contains(normalize-space(), '(')][1]", $root, true, "/\((.+)\)/"))
                ->date((!empty($date) && !empty($depTime)) ? $this->normalizeDate($date . ', ' . $depTime) : null)
                ->terminal($this->http->FindSingleNode("descendant::text()[starts-with(normalize-space(),'Departure from Terminal')]", $root, true, "/^Departure from Terminal[-\s]+([A-z\d][A-z\d ]*)$/"), false, true)
            ;

            $arrTime = $this->http->FindSingleNode("./descendant::text()[contains(normalize-space(), ':')][2]", $root, true, "/^([\d\:]+)$/");
            $s->arrival()
                ->code($this->http->FindSingleNode("./descendant::text()[contains(normalize-space(), '(')][2]", $root, true, "/\((.+)\)/"))
                ->date((!empty($date) && !empty($arrTime)) ? $this->normalizeDate($date . ', ' . $arrTime) : null);

            $seat = $this->http->FindSingleNode("descendant::text()[{$this->starts($this->t('seat'))}]", $root, true, "/{$this->opt($this->t('seat'))}[:\s]*(\d[A-Z\d, ]+)$/");

            if ($seat) {
                $s->extra()->seats(preg_split('/\s*[,]+\s*/', $seat));
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->detectLang();

        $this->assignProvider($parser->getHeaders());

        $this->ParseFlight($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));
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
        return ['flybe', 'bambooair'];
    }

    public function detectLang(): bool
    {
        foreach ($this->detectLang as $lang => $detect) {
            if ($this->http->XPath->query("//text()[{$this->contains($detect)}]")->length > 0) {
                $this->lang = substr($lang, 0, 2);

                return true;
            }
        }

        return false;
    }

    private function assignProvider($headers): bool
    {
        if (stripos($headers['from'], '@flybe.com') !== false
            || $this->http->XPath->query('//a[contains(@href,".flybe.com/") or contains(@href,"www.flybe.com")]')->length > 0
            || $this->http->XPath->query('//*[contains(normalize-space(),"Thank you for choosing flybe")]')->length > 0
        ) {
            $this->providerCode = 'flybe';

            return true;
        }

        if (stripos($headers['from'], '@sunexpress.com') !== false
            || $this->http->XPath->query('//a[contains(@href,".sunexpress.com/") or contains(@href,"www.sunexpress.com")]')->length > 0
            || $this->http->XPath->query('//*[contains(normalize-space(),"Thank you for choosing SunExpress")]')->length > 0
        ) {
            $this->providerCode = 'bambooair';

            return true;
        }

        // last always!
        if (stripos($headers['from'], '@bambooairways.com') !== false
            || $this->http->XPath->query('//a[contains(@href,".bambooairways.com/") or contains(@href,"www.bambooairways.com")]')->length > 0
            || $this->http->XPath->query("//*[{$this->contains($this->t('Thank you for choosing Bamboo Airways'))}]")->length > 0
            // and unknown provider Air Albania
            || stripos($headers['from'], '@airalbania.com.al') !== false
            || $this->http->XPath->query('//a[contains(@href,".airalbania.com.al/") or contains(@href,"www.airalbania.com.al")]')->length > 0
            || $this->http->XPath->query('//*[contains(normalize-space(),"Thank you for choosing Air Albania")]')->length > 0
        ) {
            $this->providerCode = 'bambooair';

            return true;
        }

        return false;
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

    private function re($re, $str, $c = 1): ?string
    {
        if (preg_match($re, $str, $m) && array_key_exists($c, $m)) {
            return $m[$c];
        }

        return null;
    }

    private function normalizeDate($str)
    {
        $this->logger->debug('$str = ' . print_r($str, true));

        $in = [
            "#^\s*\D+\,\s*(\d+)\.*\s*([[:alpha:]]+)\s*(\d{4})\s*,\s*(\d{1,2}:\d{2})\s*$#", //22:25, Tuesday, 19 July 2022
            // 18 Tháng Mười 2022 2:30
            // Thứ Bảy, 04 Tháng Hai 2023, 18:30
            "#^\s*(?:\D+,)?\s*(\d+)\s*([[:alpha:]]+(?: [[:alpha:]]+)?)\s+(\d{4})[\s,]+(\d{1,2}:\d{2})$#u",
        ];
        $out = [
            "$1 $2 $3, $4",
            "$1 $2 $3, $4",
        ];
        $str = preg_replace($in, $out, $str);
        $this->logger->debug('$str = ' . print_r($str, true));

        if (preg_match("#\d+\s+(\D+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }
}
