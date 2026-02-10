<?php

namespace AwardWallet\Engine\ctrip\Email;

use AwardWallet\Schema\Parser\Email\Email;

class Hotel extends \TAccountChecker
{
    public $mailFiles = "ctrip/it-45310496.eml, ctrip/it-57405154.eml, ctrip/it-57412222.eml, ctrip/it-57432303.eml"; // +1 bcdtravel(html)[en]

    public $reSubject = [
        'zh' => '您预订的',
        'en' => 'Hotel booking Cnfm',
    ];

    public $langDetectors = [
        'zh' => ['酒店确认号', '入住日期'],
        'en' => ['Check-out'],
    ];

    private $lang = '';

    private static $dict = [
        'zh' => [
            '地址：'   => ['地址：', '地址'], //Address
            '电话：'   => ['电话：', '电话'], //Phone
            '酒店确认号' => ['酒店确认号', '订单号', '订单号:', '订单号'], //Order No.
            '总价'    => ['总价', '原价', '总价:'], //total price
            '入住人'   => ['入住人', '入住人:'], //guests
            '房型'    => ['房型', '房型:'], //room type
            '入住日期'  => ['入住日期', '入住日期:'], //check-in
            '离店日期'  => ['离店日期', '离店日期:'], //check-out
            '退款'    => ['退款', '已满房。'], //cancelled
        ],
        'en' => [
            '酒店确认号' => ['Booking no.', 'Order No.'],
            '地址：'   => 'Add:',
            '电话：'   => 'Tel:',
            '总价'    => 'Total price',
            '房型'    => 'Room type',
            '入住人'   => 'Guest',
            '入住日期'  => 'Check-in',
            '离店日期'  => 'Check-out',
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        $email->setType('Hotel' . ucfirst($this->lang));
        $this->parseEmail($email);

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $condition1 = $this->http->XPath->query('//node()[contains(normalize-space(.),"Thank you for choosing Ctrip") or contains(normalize-space(.),"Corporate travel information") or contains(.,"@ctrip.com") or contains(.,"携程")]')->length === 0;
        $condition2 = $this->http->XPath->query('//a[contains(@href,".ctrip.com/")]')->length === 0;

        if ($condition1 && $condition2) {
            return false;
        }

        return $this->assignLang();
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->reSubject as $reSubject) {
            if (stripos($headers['subject'], $reSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, 'Ctrip Corporate Travel') !== false
            || stripos($from, '@ctrip.com') !== false;
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
        $patterns = [
            'time'  => '\d{1,2}(?::\d{2})?(?:\s*[AaPp]\.?[Mm]\.?|\s*noon)?', // 4:19PM    |    2:00 p.m.    |    3pm    |    12 noon
            'phone' => '[+)(\d][-.\s\d)(]{5,}[\d)(]', // +377 (93) 15 48 52    |    713.680.2992
        ];

        $h = $email->add()->hotel();

        // accountNumbers
        $accountNumber = $this->http->FindSingleNode("//text()[{$this->contains('Dear guest')}]", null, true, '/Account:\s*(\d[*\d]{5,}\*)/i');

        if ($accountNumber) {
            $h->program()->account($accountNumber, true);
        }

        //status cancelled
        if (!empty($this->http->FindNodes("//text()[{$this->contains($this->t('退款'))}]"))) {
            $h->general()
                ->status('退款')
                ->cancelled();
        }
        // confirmationNumbers
        $confNo = $this->getField($this->t('酒店确认号'));
        $h->general()->confirmation($confNo);

        // hotelName
        $hotelName = $this->http->FindSingleNode("//text()[{$this->contains($this->t('地址：'))}]/preceding::a[1]");

        if (empty($hotelName)) {
            $hotelName = $this->http->FindSingleNode("//text()[contains(normalize-space(), '退款通知')]", null, true, '/\D+[(](\D+)[)]退款/u');
        }

        if (empty($hotelName)) {
            $hotelName = $this->http->FindSingleNode("//text()[contains(normalize-space(), ']已满房。')]", null, true, '/\D+[(](\D+)[)]]已满房。/u');
        }

        if (empty($hotelName)) {
            $hotelName = $this->http->FindSingleNode("//text()[contains(normalize-space(), ' Hotel ')]", null, true, '/[(](\D+?Hotel\D+)[)]/u');
        }
        $h->hotel()->name($hotelName);

        // address
        $address = $this->http->FindSingleNode("//text()[{$this->contains($this->t('地址：'))}][not(contains(normalize-space(.), 'Address'))]", null, true, "/{$this->opt($this->t('地址：'))}\s*(.+)/");

        if (empty($address)) {
            $address = $this->http->FindSingleNode("//td[{$this->contains($this->t('地址：'))}][not(.//td)]/following-sibling::td[normalize-space(.)][1]", null, true, '/.*\([ ]*(.+)[ ]*\).*/');
        }

        if (empty($h->getAddress()) && !empty($address)) {
            $h->hotel()->address($address);
        } else {
            $h->hotel()->noAddress();
        }

        // phone
        $phone = $this->http->FindSingleNode("//text()[{$this->contains($this->t('电话：'))}][not(contains(normalize-space(.), 'Tel'))]", null, true, "/{$this->opt($this->t('电话：'))}\s*({$patterns['phone']})/");

        if (empty($phone)) {
            $phone = $this->http->FindSingleNode("//td[{$this->contains($this->t('电话：'))}]/following-sibling::td[normalize-space(.)][1]");
        }

        if (!empty($phone)) {
            $h->hotel()->phone($phone);
        }

        $r = $h->addRoom();

        // r.type
        $type = $this->http->FindSingleNode("//td[contains(normalize-space(.), 'Room type') and not(.//td)]/following-sibling::td[normalize-space(.)][1]/descendant::text()[normalize-space(.)][not(starts-with(normalize-space(), '('))][last()]");
        if (empty($type)) {
            $type = $this->getField($this->t('房型'));
        }
        $r->setType($type);

        //r.description
        $roomDescription = $this->getField($this->t('餐食情况:'));

        if (!empty($roomDescription)) {
            $r->setDescription($roomDescription);
        }

        // r.rate
        $rateText = '';
        $rateRows = $this->http->XPath->query("//text()[{$this->eq('Daily price')}]/ancestor::tr[1]/following::tr[normalize-space(.)][1]/descendant::td[not(.//td) and normalize-space(.)]");

        foreach ($rateRows as $rateRow) {
            $rowDate = $this->http->FindSingleNode('./descendant::text()[normalize-space(.)][1]', $rateRow);
            $rowPayment = $this->http->FindSingleNode('./descendant::text()[normalize-space(.)][2]', $rateRow);
            $rateText .= "\n" . $rowPayment . ' from ' . $rowDate;
        }
        $rateRange = $this->parseRateRange($rateText);

        if ($rateRange !== null) {
            $r->setRate($rateRange);
        }

        // p.total
        // p.currencyCode
        $tot = $this->getTotalCurrency($this->getField($this->t('总价')));

        if (empty($tot['Total'])) {
            $tot = $this->getTotalCurrency($this->http->FindSingleNode("//text()[{$this->starts($this->t('总价'))}]/following::text()[normalize-space()][1]"));
        }
        $h->price()
            ->total($tot['Total'])
            ->currency($tot['Currency']);

        // travellers
        $h->general()->travellers(explode(',', $this->getField($this->t('入住人'))), true);

        // cancellation
        $cancellationText = $this->http->FindSingleNode("//text()[normalize-space(.)='取消政策']/ancestor::td[1]", null, true, "#取消政策\s*(.+)#"); // zh

        if (!$cancellationText) {
            $hotelPolicy = $this->http->FindSingleNode("//text()[normalize-space(.)='Hotel policy']/following::text()[normalize-space(.)][1]");

            if ($hotelPolicy) {
                $hotelPolicy = preg_replace('/[.]*\s*[|]+\s*/', '. ', $hotelPolicy); // ..stay alone. || Buffet breakfast..    ->    ..stay alone. Buffet breakfast..
                $hotelPolicyParts = preg_split('/[.]+\s*\b/', $hotelPolicy);
                $hotelPolicyParts = array_filter($hotelPolicyParts, function ($item) {
                    return stripos($item, 'cancel') !== false;
                });
                $cancellationText = implode('. ', $hotelPolicyParts);
            }
        }

        if (!empty($cancellationText)) {
            $h->general()->cancellation($cancellationText, true);
        }

        // checkInDate
        // checkOutDate
        $dateCheckIn = $this->getField($this->t('入住日期'));

        if (preg_match("/(\d{4})-\d+-\d+\((\d+)-(\d+)\s+(\d+:\d+)[\s-]+(\d+)-(\d+)\s+(\d+:\d+)\)/", $dateCheckIn, $m)) {
            // 2017-03-07(03-07 14:00--03-09 12:00)
            $h->booked()->checkIn(strtotime($m[3] . '.' . $m[2] . '.' . $m[1] . ', ' . $m[4]));
            $h->booked()->checkOut(strtotime($m[6] . '.' . $m[5] . '.' . $m[1] . ', ' . $m[7]));
        } elseif (preg_match("/(\d{4})\-\d{2}\-\d{1,2}[(]\D+(\d+)\-(\d+)\s+([\d+\:]+)\D+[)]/", $dateCheckIn, $m)) {
            // 2020-01-11(最早01-11 15:00入住)
            $h->booked()->checkIn(strtotime($m[3] . '.' . $m[2] . '.' . $m[1] . ', ' . $m[4]));
        } elseif (preg_match("/(\d{4})-(\d{1,2})-(\d{1,2})\s*\(\s*({$patterns['time']})/", $dateCheckIn, $m)) {
            // 2018-09-11(13:00-19:00)
            $h->booked()->checkIn(strtotime($m[3] . '.' . $m[2] . '.' . $m[1] . ', ' . $m[4]));
        } elseif (preg_match("/(\d{4})-(\d+)-(\d+)/", $dateCheckIn, $m)) {
            // 2018-09-11
            $h->booked()->checkIn(strtotime($m[3] . '.' . $m[2] . '.' . $m[1]));

            if (isset($hotelPolicy) && !empty($hotelPolicy) && !empty($h->getCheckInDate()) && preg_match("/Check in from\s*({$patterns['time']})/", $hotelPolicy, $m)) {
                $h->booked()->checkIn(strtotime($m[1], $h->getCheckInDate()));
            }
        }
        $checkInTime = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.), '入住时间：')]/following-sibling::node()[normalize-space(.)][1]", null, true, '/(\d{1,2}:\d{2})/');
        $checkOutTime = $this->http->FindSingleNode("//text()[contains(normalize-space(.), '离店时间：')]/following-sibling::node()[normalize-space(.)][1]", null, true, '/(\d{1,2}:\d{2})/');

        if (empty($h->getCheckOutDate())) {
            $dateCheckOut = $this->getField($this->t('离店日期'));

            if (preg_match("/(\d{4})\-(\d{2})\-(\d{1,2})[(]\D+([\d+\:]+)\D+[)]/u", $dateCheckOut, $m)) {
                $h->booked()->checkOut(strtotime($m[3] . '.' . $m[2] . '.' . $m[1] . ', ' . $m[4]));

                if (isset($hotelPolicy) && !empty($hotelPolicy) && !empty($h->getCheckOutDate()) && preg_match("/Check out until\s*({$patterns['time']})/", $hotelPolicy, $m)) {
                    $h->booked()->checkOut(strtotime($m[1], $h->getCheckOutDate()));
                }
            } elseif (preg_match("/(\d{4})-(\d+)-(\d+)/", $dateCheckOut, $m)) {
                $h->booked()->checkOut(strtotime($m[3] . '.' . $m[2] . '.' . $m[1]));

                if (isset($hotelPolicy) && !empty($hotelPolicy) && !empty($h->getCheckOutDate()) && preg_match("/Check out until\s*({$patterns['time']})/", $hotelPolicy, $m)) {
                    $h->booked()->checkOut(strtotime($m[1], $h->getCheckOutDate()));
                }
            }
        }

        if ($checkInTime && $checkOutTime) {
            $h->booked()
                ->checkIn(strtotime($checkInTime, $h->getCheckInDate()))
                ->checkOut(strtotime($checkOutTime, $h->getCheckOutDate()))
            ;
        }
    }

    private function parseRateRange($string = '')
    {
        if (
            preg_match_all('/(?:^|\b\s+)(?<currency>[^\d\s]\D{0,2}?)\s*(?<amount>\d[,.\d\s]*)\s+from\s+\b/', $string, $rateMatches) // $239.20 from August 15
        ) {
            if (count(array_unique($rateMatches['currency'])) === 1) {
                $rateMatches['amount'] = array_map(function ($item) {
                    return (float) $this->normalizeAmount($item);
                }, $rateMatches['amount']);

                $rateMin = min($rateMatches['amount']);
                $rateMax = max($rateMatches['amount']);

                if ($rateMin === $rateMax) {
                    return number_format($rateMatches['amount'][0], 2, '.', '') . ' ' . $rateMatches['currency'][0] . ' / night';
                } else {
                    return number_format($rateMin, 2, '.', '') . '-' . number_format($rateMax, 2, '.', '') . ' ' . $rateMatches['currency'][0] . ' / night';
                }
            }
        }

        return null;
    }

    /**
     * @param string $string Unformatted string with amount
     */
    private function normalizeAmount(string $s): string
    {
        $s = preg_replace('/\s+/', '', $s);             // 11 507.00  ->  11507.00
        $s = preg_replace('/[,.\'](\d{3})/', '$1', $s); // 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
        $s = preg_replace('/,(\d{2})$/', '.$1', $s);    // 18800,00   ->  18800.00

        return $s;
    }

    private function getField($field, $root = null)
    {
        $rule = $this->eq($field);

        return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/ancestor::td[1]/following-sibling::td[1]", $root);
    }

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'normalize-space(' . $node . ')="' . $s . '"'; }, $field)) . ')';
    }

    private function starts($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'starts-with(normalize-space(' . $node . '),"' . $s . '")'; }, $field)) . ')';
    }

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'contains(normalize-space(' . $node . '),"' . $s . '")'; }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) { return preg_quote($s, '/'); }, $field)) . ')';
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
        foreach ($this->langDetectors as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if ($this->http->XPath->query('//node()[contains(normalize-space(.),"' . $phrase . '")]')->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function getTotalCurrency($node)
    {
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m) || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m)) {
            if (strpos($m['t'], ',') !== false && strpos($m['t'], '.') !== false) {
                if (strpos($m['t'], ',') < strpos($m['t'], '.')) {
                    $m['t'] = str_replace(',', '', $m['t']);
                } else {
                    $m['t'] = str_replace('.', '', $m['t']);
                    $m['t'] = str_replace(',', '.', $m['t']);
                }
            }
            $tot = str_replace(",", ".", str_replace(' ', '', $m['t']));
            $cur = $m['c'];
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }
}
