<?php

namespace AwardWallet\Engine\ctrip\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Common\Flight;
use AwardWallet\Schema\Parser\Email\Email;

class AirConfirmation extends \TAccountChecker
{
    public $mailFiles = "ctrip/it-23027325.eml, ctrip/it-23027429.eml, ctrip/it-24665875.eml, ctrip/it-58218952.eml, ctrip/it-8187273.eml";

    private $subjects = [
        'zh' => ['机票成交确认单'],
        'en' => ['Air-Ticket'],
    ];

    private $langDetectors = [
        'zh' => ['订单号：'],
        'en' => ['Order No:'],
    ];
    private $lang = '';
    private static $dict = [
        'zh' => [
            'Dear guest' => '尊敬的商旅客人',
            'Account:'   => '卡号：',
            'Order No:'  => '订单号：',
            //            'Type' => '',
            //            'class' => '',
            //            'Duration:' => '',
            'Ticket NO' => '票号',
            //            'Booking reference' => '',
            'Passenger'        => '乘机人',
            'Total price:'     => '金额总计',
            'Payment details:' => '付款明细',
            'Airfare'          => '机票费',
            //            'Tax' => '',
            'feeNames' => ['民航基金', '服务费', '保险费'],
            //            'Conditions for refund:' => '',
            'sHeaderStarts' => ['单程'],
            'orderNumber'   => '预订编号：',
        ],
        'en' => [
            'feeNames'      => ['Service fee', 'Civil Aviation Development Fund'],
            'sHeaderStarts' => ['segment', 'One-way'],
            //            'orderNumber' => '',
        ],
    ];

    private $patterns = [
        'confNumber' => '[A-Z\d]{5,}', // 11986371476    |    M5GPQK
        'time'       => '\d{1,2}(?::\d{2})?(?:\s*[AaPp]\.?[Mm]\.?|\s*noon)?', // 4:19PM    |    2:00 p.m.    |    3pm    |    12 noon
    ];

    // Standard Methods

    public function detectEmailFromProvider($from)
    {
        return strpos($from, 'Ctrip Corporate Travel') !== false
            || strpos($from, '携程商旅预订部') !== false
            || stripos($from, '@ctrip.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true && strpos($headers['subject'], '携程') === false) {
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
        if ($this->http->XPath->query('//node()[contains(normalize-space(),"Thank you for choosing Ctrip") or contains(normalize-space(),"please contact Ctrip Corporate Travel at")]')->length === 0
            && $this->http->XPath->query('//a[contains(@href,".ctrip.com/") or contains(@href,"www.ctrip.com") or contains(@href,"ct.ctrip.com")]')->length === 0
        ) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }
        $email->setType('AirConfirmation' . ucfirst($this->lang));
        $this->parseEmail($email);

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseSegments1(Flight $f, $segments): void
    {
        $pattern = '/'
            . '^[ ]*(?<airportCode>[A-Z]{3})[ ]*$' // HKG
            . '(?:\s+^[ ]*(?<terminal>[A-Z\d]+)[ ]*$)?' // T1
            . '\s+^[ ]*(?<airportName>.+)[ ]*$' // Hong Kong
            . '\s+^[ ]*(?<date>.*?)?[ ]*;?[ ]*(?<time>' . $this->patterns['time'] . ')[ ]*$' // 11-15 08:05 AM    |    23 Mar ;21:00 PM    |    10:20 AM
            . '(?:\s+^[ ]*[+][ ]*(?<overnight>\d{1,3}))?' // +1day
            . '/m';

        foreach ($segments as $segment) {
            $s = $f->addSegment();

            $date = 0;
            // 2017-12-09
            $dateText = $this->http->FindSingleNode('./ancestor::tr[ ./preceding-sibling::*[normalize-space(.)] ][1]/preceding-sibling::*[normalize-space(.)][1]', $segment, true, '/\b(\d{2,4}-\d{1,2}-\d{1,2})\b/');

            if ($dateText) {
                $date = strtotime($dateText);
            }

            // depCode
            // depTerminal
            // depName
            // depDate
            $departureTexts = $this->http->FindNodes('./preceding-sibling::*[normalize-space(.)][1]/descendant::text()[normalize-space(.)]', $segment);
            $departureText = implode("\n", $departureTexts);

            if (preg_match($pattern, $departureText, $matches)) {
                $matches['time'] = $this->normalizeTime($matches['time']);
                $s->departure()
                    ->code($matches['airportCode'])
                    ->name($matches['airportName']);

                if (!empty($matches['terminal'])) {
                    $s->departure()->terminal($matches['terminal']);
                }

                if (empty($matches['date']) && $date) {
                    $s->departure()->date(strtotime($matches['time'], $date));
                } else {
                    $dateDepNormal = $this->normalizeDate($matches['date']);

                    if ($dateDepNormal && preg_match('/.+\d{4}$/', $dateDepNormal)) {
                        $s->departure()->date(strtotime($dateDepNormal . ', ' . $matches['time']));
                    } elseif ($dateDepNormal && $date) {
                        $dateDepFormat = preg_match('/^\d{1,2}\/\d{1,2}$/', $dateDepNormal) ? '%D%/%Y%' : '%D% %Y%';
                        $dateDep = EmailDateHelper::parseDateRelative($dateDepNormal, $date, true, $dateDepFormat);
                        $s->departure()->date(strtotime($matches['time'], $dateDep));
                    }
                }

                if (!empty($matches['overnight']) && !empty($s->getDepDate())) {
                    $s->departure()->date(strtotime("+{$matches['overnight']} days", $s->getDepDate()));
                }
            }

            // arrCode
            // arrTerminal
            // arrName
            // arrDate
            $arrivalTexts = $this->http->FindNodes('following-sibling::*[normalize-space()][1]/descendant::text()[normalize-space()]', $segment);
            $arrivalText = implode("\n", $arrivalTexts);

            if (preg_match($pattern, $arrivalText, $matches)) {
                $matches['time'] = $this->normalizeTime($matches['time']);
                $s->arrival()
                    ->code($matches['airportCode'])
                    ->name($matches['airportName']);

                if (!empty($matches['terminal'])) {
                    $s->arrival()->terminal($matches['terminal']);
                }

                if (empty($matches['date']) && $date) {
                    $s->arrival()->date(strtotime($matches['time'], $date));
                } else {
                    $dateArrNormal = $this->normalizeDate($matches['date']);

                    if ($dateArrNormal && preg_match('/.+\d{4}$/', $dateArrNormal)) {
                        $s->arrival()->date(strtotime($dateArrNormal . ', ' . $matches['time']));
                    } elseif ($dateArrNormal && $date) {
                        $dateArrFormat = preg_match('/^\d{1,2}\/\d{1,2}$/', $dateDepNormal) ? '%D%/%Y%' : '%D% %Y%';
                        $dateArr = EmailDateHelper::parseDateRelative($dateArrNormal, $date, true, $dateArrFormat);
                        $s->arrival()->date(strtotime($matches['time'], $dateArr));
                    }
                }

                if (!empty($matches['overnight']) && !empty($s->getArrDate())) {
                    $s->arrival()->date(strtotime("+{$matches['overnight']} days", $s->getArrDate()));
                }
            }

            // Air France AF8404  Type:74E  Economy(T)    |    China Eastern Airlines MU2285  Economy class  19.00% off
            $flightInfo = $this->http->FindSingleNode('ancestor::tr[ following-sibling::*[normalize-space()] ][1]/following-sibling::*[normalize-space()][1]', $segment);

            // airlineName
            // flightNumber
            if (preg_match('/^.*?(?<airline>[A-Z][A-Z\d]|[A-Z\d][A-Z])?\s*(?<flightNumber>\d+)/', $flightInfo, $matches)) {
                // AF8404
                $s->airline()
                    ->name($matches['airline'])
                    ->number($matches['flightNumber']);
            }

            // aircraft
            // cabin
            // bookingCode
            if (preg_match("/{$this->opt($this->t('Type'))}\s*:\s*(?<aircraft>[A-z\d]+)\s+(?<cabin>[^\d\W]{3,})(?:\s+{$this->opt($this->t('class'))})?\s*\(\s*(?<bookingCode>[A-Z]{1,2})\s*\)/i", $flightInfo, $matches)) {
                // Type: 77W    Economy class ( Q )
                $s->extra()
                    ->aircraft($matches['aircraft'])
                    ->cabin($matches['cabin'])
                    ->bookingCode($matches['bookingCode']);
            }

            // duration
            $duration = $this->http->FindSingleNode('./ancestor::table[1]/ancestor::td[ ./following-sibling::*[normalize-space(.)] ][1]/following-sibling::*[normalize-space(.)][1]/descendant::text()[' . $this->contains($this->t('Duration:')) . ']', $segment, true, '/' . $this->opt($this->t('Duration:')) . '\s*(\d[hm\d\s]+)$/i');
            $s->extra()->duration($duration, false, true); // Duration:1h35m
        }
    }

    private function parseSegments2(Flight $f, $segments): void
    {
        /*
            13:00
            咸阳机场
            T3
        */
        $pattern = "/^"
            . "(?<time>{$this->patterns['time']})\n+"
            . "(?<airport>.+?)"
            . "(?:\s*T(?<terminal>[A-Z\d ]+))?"
            . "$/";

        foreach ($segments as $segment) {
            $s = $f->addSegment();

            // 单程 西安 － 上海 2020-4-17    |    单程 银川 － 合肥 2020-4-17 预订编号： VNPMQD
            $header = implode(' ', $this->http->FindNodes("ancestor::tr[1]/preceding-sibling::tr[{$this->contains($this->t('sHeaderStarts'))}][1]/descendant::text()[normalize-space()]", $segment));

            $date = 0;

            if (preg_match('/(?:^|\s)(\d{4}-\d{1,2}-\d{1,2})(?:\s|$)/', $header, $m)) {
                $date = strtotime($this->normalizeDate($m[1]));
            }

            if (preg_match("/{$this->opt($this->t('orderNumber'))}\s*({$this->patterns['confNumber']})\b/", $header, $m)) {
                $s->airline()->confirmation($m[1]);
            }

            $flight = implode("\n", $this->http->FindNodes("preceding-sibling::*[normalize-space()][2]/descendant::text()[normalize-space()]", $segment));

            if (preg_match('/^(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<number>\d+)$/m', $flight, $m)) {
                $s->airline()
                    ->name($m['name'])
                    ->number($m['number']);
            }

            $departure = implode("\n", $this->http->FindNodes("preceding-sibling::*[normalize-space()][1]/descendant::text()[normalize-space()]", $segment));

            if (preg_match($pattern, $departure, $m)) {
                if ($date) {
                    $s->departure()->date(strtotime($m['time'], $date));
                }
                $s->departure()
                    ->name($m['airport'])
                    ->noCode();

                if (!empty($m['terminal'])) {
                    $s->departure()->terminal($m['terminal']);
                }
            }

            $arrival = implode("\n", $this->http->FindNodes("following-sibling::*[normalize-space()][1]/descendant::text()[normalize-space()]", $segment));

            if (preg_match($pattern, $arrival, $m)) {
                if ($date) {
                    $s->arrival()->date(strtotime($m['time'], $date));
                }
                $s->arrival()
                    ->name($m['airport'])
                    ->noCode();

                if (!empty($m['terminal'])) {
                    $s->arrival()->terminal($m['terminal']);
                }
            }
        }
    }

    private function parseEmail(Email $email): void
    {
        $xpathFragmentCell = '(self::td or self::th)';

        $email->ota(); // because Ctrip is not airline

        // ta.accountNumbers
        $accountNumber = $this->http->FindSingleNode("//td[not(.//td) and {$this->contains($this->t('Dear guest'))}]", null, true, "/{$this->opt($this->t('Account:'))}\s*(\d[*\d]{5,}\*)/i");

        if ($accountNumber) {
            $email->ota()->account($accountNumber, true);
        }

        // ta.confirmationNumbers
        $orderNoTitle = $this->http->FindSingleNode('//text()[' . $this->contains($this->t('Order No:')) . ']');
        $orderNo = $this->http->FindSingleNode('//text()[' . $this->contains($this->t('Order No:')) . ']/following::text()[normalize-space(.)][1]', null, true, '/^(' . $this->patterns['confNumber'] . ')/');
        $email->ota()->confirmation($orderNo, preg_replace('/\s*[:：]$/u', '', $orderNoTitle));

        $f = $email->add()->flight();

        $segments = $this->http->XPath->query('//td[ count(preceding-sibling::*[normalize-space()])=1 and descendant::img and count(following-sibling::*[normalize-space()])=1 ]');

        if ($segments->length > 0) {
            $this->logger->debug('Segments found: type-1');
            $this->parseSegments1($f, $segments);
        }

        if ($segments->length === 0) {
            $segments = $this->http->XPath->query('//td[ count(preceding-sibling::*[normalize-space()])=2 and descendant::img and count(following-sibling::*[normalize-space()])>0 ]');

            if ($segments->length > 0) {
                // it-58218952.eml
                $this->logger->debug('Segments found: type-2');
                $this->parseSegments2($f, $segments);
            }
        }

        // travellers
        // ticketNumbers
        // confirmationNumbers
        $ticketNumbers = [];
        $bookingReferenceList = [];
        $ticketNoPos = $this->http->XPath->query('//text()[' . $this->eq($this->t('Ticket NO')) . ']/ancestor::*[ ./preceding-sibling::*[' . $xpathFragmentCell . '] ][1]/preceding-sibling::*')->length;
        $bookingReferencePos = $this->http->XPath->query('//text()[' . $this->eq($this->t('Booking reference')) . ']/ancestor::*[ ./preceding-sibling::*[' . $xpathFragmentCell . '] ][1]/preceding-sibling::*')->length;
        $passengerRows = $this->http->XPath->query('//text()[' . $this->eq($this->t('Passenger')) . ']/ancestor::tr[ ./following-sibling::*[normalize-space(.)] ][1]/following-sibling::*[normalize-space(.)]');

        foreach ($passengerRows as $passengerRow) {
            $f->addTraveller($this->http->FindSingleNode('./*[1]', $passengerRow));

            $ticketNoTexts = $this->http->FindNodes("./*[{$ticketNoPos}+1]/descendant::text()[normalize-space(.)]", $passengerRow, '/^(\d[-\d]{5,}\d)$/');
            $ticketNoValues = array_values(array_filter($ticketNoTexts));

            if (!empty($ticketNoValues[0])) {
                $ticketNumbers = array_merge($ticketNumbers, $ticketNoValues);
            }

            $bookingReference = $this->http->FindSingleNode("./*[{$bookingReferencePos}+1]", $passengerRow, true, '/^(' . $this->patterns['confNumber'] . ')$/');

            if ($bookingReference) {
                $bookingReferenceList[] = $bookingReference;
            }
        }

        if (count($ticketNumbers)) {
            $f->setTicketNumbers($ticketNumbers, false);
        }

        if (count($bookingReferenceList)) {
            foreach (array_unique($bookingReferenceList) as $value) {
                $f->general()->confirmation($value, $this->t('Booking reference'));
            }
        } else {
            $f->general()->noConfirmation();
        }

        // p.currencyCode
        // p.total
        $payment = $this->http->FindSingleNode('//text()[' . $this->eq($this->t('Total price:')) . ']/ancestor::*[' . $xpathFragmentCell . '][1]/following-sibling::*[normalize-space(.)][1]');

        if (preg_match('/^(?<currency>[^\d)(]+?)\s*(?<amount>\d[,.\'\d]*)$/', $payment, $matches)) {
            // CNY8048    |    ￥497.00
            $f->price()
                ->currency($matches['currency'])
                ->total($this->normalizeAmount($matches['amount']));

            $paymentDetails = $this->http->FindSingleNode('//text()[' . $this->eq($this->t('Payment details:')) . ']/ancestor::*[' . $xpathFragmentCell . '][1]/following-sibling::*[normalize-space(.)][1]');

            if ($paymentDetails !== null) {
                $paymentDetails = preg_replace('/(' . preg_quote($matches['currency'], '/') . ')[ ]*\1+/', '$1', $paymentDetails);
            }

            // p.cost
            if (preg_match('/(?:^|\s+)' . $this->opt($this->t('Airfare')) . '\s+' . preg_quote($matches['currency'], '/') . '(?<amount>\d[,.\'\d]*)(?:\s*;|[[:alpha:]]|$)/iu', $paymentDetails, $m)) {
                $f->price()->cost($this->normalizeAmount($m['amount']));
            }

            // p.taxes
            if (preg_match('/(?:^|\s+)' . $this->opt($this->t('Tax')) . '\s+' . preg_quote($matches['currency'], '/') . '(?<amount>\d[,.\'\d]*)(?:\s*;|[[:alpha:]]|$)/iu', $paymentDetails, $m)) {
                $f->price()->tax($this->normalizeAmount($m['amount']));
            }

            // p.fees
            foreach ((array) $this->t('feeNames') as $feeName) {
                if (preg_match('/(?:^|[^[:alpha:](]\s*)(?<name>' . preg_quote($feeName, '/') . ')\s*' . preg_quote($matches['currency'], '/') . '(?<amount>\d[,.\'\d]*)(?:\s*[;；(]|[[:alpha:]]|$)/iu', $paymentDetails, $m)) {
                    $f->price()->fee($m['name'], $this->normalizeAmount($m['amount']));
                }
            }
        }

        // cancellation
        $refundTexts = $this->http->FindNodes('//text()[' . $this->eq($this->t('Conditions for refund:')) . ']/ancestor::*[' . $xpathFragmentCell . '][1]/following-sibling::*[normalize-space(.)][1]/descendant::text()[normalize-space(.)]');
        $refundText = implode(' ', $refundTexts);

        if ($refundText) {
            $f->general()->cancellation($refundText);
        }
    }

    private function normalizeDate(string $string)
    {
        if (preg_match('/^(\d{1,2})-(\d{1,2})$/', $string, $matches)) { // 11-15
            $month = $matches[1];
            $day = $matches[2];
            $year = '';
        } elseif (preg_match('/^(\d{1,2})\s*([^\d\W]{3,})$/u', $string, $matches)) { // 23 Mar
            $day = $matches[1];
            $month = $matches[2];
            $year = '';
        } elseif (preg_match('/^(\d{4})\s*-\s*(\d{1,2})\s*-\s*(\d{1,2})$/', $string, $matches)) {
            // 2020-4-17
            $year = $matches[1];
            $month = $matches[2];
            $day = $matches[3];
        }

        if (isset($day, $month, $year)) {
            if (preg_match('/^\s*(\d{1,2})\s*$/', $month, $m)) {
                return $m[1] . '/' . $day . ($year ? '/' . $year : '');
            }

            if (($monthNew = MonthTranslate::translate($month, $this->lang)) !== false) {
                $month = $monthNew;
            }

            return $day . ' ' . $month . ($year ? ' ' . $year : '');
        }

        return false;
    }

    private function normalizeTime(string $string): string
    {
        if (preg_match('/^(?:12)?\s*noon$/i', $string)) {
            return '12:00';
        }

        if (preg_match('/^((\d{1,2})[ ]*:[ ]*\d{2})\s*[AaPp][Mm]$/', $string, $m) && (int) $m[2] > 12) {
            $string = $m[1];
        } // 21:51 PM    ->    21:51
        $string = preg_replace('/^(0{1,2}[ ]*:[ ]*\d{2})\s*[AaPp][Mm]$/', '$1', $string); // 00:25 AM    ->    00:25
        $string = preg_replace('/(\d)[ ]*-[ ]*(\d)/', '$1:$2', $string); // 01-55 PM    ->    01:55 PM
        $string = str_replace(['午前', '午後'], ['AM', 'PM'], $string); // 10:36 午前    ->    10:36 AM

        return $string;
    }

    /**
     * Formatting over 3 steps:
     * 11 507.00  ->  11507.00
     * 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
     * 18800,00   ->  18800.00  |  2777,0    ->  2777.0.
     *
     * @param string $s Unformatted string with amount
     * @param string|null $decimals Symbols floating-point when non-standard decimals (example: 1,258.943)
     */
    private function normalizeAmount(string $s, ?string $decimals = null): ?float
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

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ')="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'starts-with(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
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

    private function t(string $phrase)
    {
        if (!isset(self::$dict, $this->lang) || empty(self::$dict[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dict[$this->lang][$phrase];
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
}
