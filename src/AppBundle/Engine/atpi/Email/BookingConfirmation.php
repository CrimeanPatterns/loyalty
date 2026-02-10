<?php

namespace AwardWallet\Engine\atpi\Email;

use AwardWallet\Schema\Parser\Email\Email;

class BookingConfirmation extends \TAccountChecker
{
    public $mailFiles = "atpi/it-29609166.eml, atpi/it-29609168.eml";

    public static $detectProviders = [
        'atpi' => [
            'from' => ['@atpi.com'],
            'body' => ['ATPI, ATPI Lowestoft', 'www.atpi.com'],
        ],
        'ctraveller' => [
            'from' => ['@corptraveller.'],
            'body' => ['Corporate Traveller', 'www.corptraveller.co.uk'],
        ],
        'fcmtravel' => [
            'from' => ['.fcm.travel.', 'fcmtravel.'],
            'body' => ['FCm Travel Solutions'],
        ],
    ];

    private $detectSubjectRegex = "#\d{4} \+ \d{1,2} nts @#";
    private $langDetectors = [
        'en' => ['Booking Confirmation - Reference', 'Amendment Confirmation - Reference'],
    ];
    private $lang = 'en';
    private static $dict = [
        'en' => [
            'referenceNo' => ['Booking Confirmation - Reference', 'Amendment Confirmation - Reference'],
        ],
    ];
    private $providerCode;

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->parseEmail($email);

        if (!empty($this->providerCode)) {
            $email->setProviderCode($this->providerCode);
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        foreach (self::$detectProviders as $code => $arr) {
            if (isset($arr['from'])) {
                foreach ($arr['from'] as $f) {
                    if (stripos($from, $f) !== false) {
                        $this->providerCode = $code;

                        return true;
                    }
                }
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers['from']) || empty($headers['subject'])) {
            return false;
        }

        foreach (self::$detectProviders as $code => $arr) {
            if (isset($arr['from'])) {
                foreach ($arr['from'] as $f) {
                    if (stripos($headers['from'], $f) !== false
                            && preg_match($this->detectSubjectRegex, $headers['subject'])) {
                        $this->providerCode = $code;

                        return true;
                    }
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        foreach (self::$detectProviders as $code => $arr) {
            if (isset($arr['body'])) {
                foreach ($arr['body'] as $b) {
                    if ($this->http->XPath->query('//node()[contains(normalize-space(.),"' . $b . '")]')->length > 0) {
                        $this->providerCode = $code;

                        return $this->assignLang();
                    }
                }
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    public static function getEmailProviders()
    {
        return array_keys(self::$detectProviders);
    }

    private function parseEmail(Email $email)
    {
        $xpathFragmentStrong = '(self::b or self::strong)';

        $email->obtainTravelAgency();

        $reference = $this->http->FindSingleNode("//text()[{$this->starts($this->t('referenceNo'))}]");

        if (preg_match("/({$this->opt($this->t('referenceNo'))})[:\s]+(.{7,})/", $reference, $m)) {
            $email->ota()->confirmation($m[2], $m[1]);
        }

        $h = $email->add()->hotel();

        // hotelName
        // address
        // phone
        $hotelInfoNodes = $this->http->XPath->query("//text()[{$this->contains($this->t('View Map:'))}]/ancestor::td[./preceding-sibling::*][1]");

        if ($hotelInfoNodes->length > 0) {
            $hotelInfo = $hotelInfoNodes->item(0);

            $hotelName = $this->http->FindSingleNode("./descendant::text()[normalize-space(.)][1]/ancestor::*[self::b or self::strong][1]", $hotelInfo);
            $h->hotel()->name($hotelName);

            if ($hotelName) {
                $hotelInfoHtml = $hotelInfo->ownerDocument->saveHTML($hotelInfo);
                $hotelInfoText = $this->htmlToText($hotelInfoHtml);
                $pattern = "/"
                    . "^[ ]*" . preg_quote($hotelName) . "[ ]*$" // Harte & Garter Hotel Clarion Collection
                    . "\s+(?<address>.{3,}?)" // High Street, Windsor, Berkshire SL4 1PH
                    . "(?:\s+^[ ]*{$this->opt($this->t('Tel:'))}[ ]*(?<phone>[+)(\d][-.\s\d)(]{5,}[\d)(])[ ]*$)?" // Tel: 01753 863426
                    . "\s+^[ ]*{$this->opt($this->t('View Map:'))}" // View Map: click here
                    . "/ms";

                if (preg_match($pattern, $hotelInfoText, $m)) {
                    $h->hotel()
                        ->address(preg_replace(['/\s*\n\s*/', '/[ ,]*,[ ,]*/'], ', ', $m['address']))
                        ->phone($m['phone'], false, true)
                    ;
                }
            }
        }

        // travellers
        $traveller = $this->http->FindSingleNode("//tr[ ./*[1][{$this->eq($this->t('Name'))}] ]/following::tr[normalize-space(.)][1]/*[1]", null, true, '/^([[:alpha:]][-.\'[:alpha:]\s]*[[:alpha:]])$/u');
        $h->general()->traveller($traveller);

        // checkInDate
        $dateArrival = $this->http->FindSingleNode("//tr[ ./*[2][{$this->eq($this->t('Arrival'))}] ]/following::tr[normalize-space(.)][1]/*[2]");
        $h->booked()->checkIn2($dateArrival);

        // checkOutDate
        $nights = $this->http->FindSingleNode("//tr[ ./*[3][{$this->eq($this->t('Nights'))}] ]/following::tr[normalize-space(.)][1]/*[3]", null, true, '/^(\d{1,3})$/');

        if ($nights !== null && !empty($h->getCheckInDate())) {
            $h->booked()->checkOut(strtotime("+$nights days", $h->getCheckInDate()));
        }

        $r = $h->addRoom();

        // r.type
        $roomType = $this->http->FindSingleNode("//tr[ ./*[4][{$this->eq($this->t('Room Type'))}] ]/following::tr[normalize-space(.)][1]/*[4]");
        $r->setType($roomType);

        // roomsCount
        $roomsCount = $this->http->FindSingleNode("//tr[ ./*[5][{$this->eq($this->t('Rooms'))}] ]/following::tr[normalize-space(.)][1]/*[5]", null, true, '/^(\d{1,3})$/');
        $h->booked()->rooms($roomsCount);

        // confirmation number
        $hotelRef = $this->http->FindSingleNode("//tr[ ./*[6][{$this->eq($this->t('Hotel Ref'))}] ]/following::tr[normalize-space(.)][1]/*[6]");
        $hotelRefTitle = $this->http->FindSingleNode("//tr/*[6][{$this->eq($this->t('Hotel Ref'))}]");
        $h->general()->confirmation($hotelRef, $hotelRefTitle);

        // p.cost
        // p.currencyCode
        $payment = $this->http->FindSingleNode("//td[not(.//td) and {$this->eq($this->t('Cost (Some hotels may display rates exclusive of VAT):'))}]/following-sibling::td[normalize-space(.)][last()]");

        if (preg_match('/^(?<amount>\d[,.\'\d]*)\s*(?<currency>[A-Z]{3})\b/', $payment, $matches)) {
            // 512.00 GBP
            $h->price()
                ->cost($this->normalizeAmount($matches['amount']))
                ->currency($matches['currency'])
            ;
        }

        // r.rateType
        $rateInfo = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Rate Information:'))}]/ancestor::*[{$xpathFragmentStrong}]/following::text()[normalize-space(.)][1][not(./ancestor::*[{$xpathFragmentStrong}])]");

        if (preg_match("/^([\w\s]+)\s*:\s*(.{3,})$/", $rateInfo, $m)) {
            $r
                ->setRateType($m[1])
                ->setDescription(trim($m[2], ':, '))
            ;
        } elseif ($rateInfo) {
            $r->setDescription(trim($rateInfo, ':, '));
        }

        // cancellation
        $cancellation = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Cancellation Policy:'))}]/ancestor::*[{$xpathFragmentStrong}]/following::text()[normalize-space(.)][1][not(./ancestor::*[{$xpathFragmentStrong}])]/ancestor::*[self::p or self::div][1]");
        $cancellation = preg_replace("/^\s*{$this->opt($this->t('The cancellation policy for this hotel is:'))}\s*(.+)/s", '$1', $cancellation);
        $h->general()->cancellation($cancellation);

        $patterns['time'] = '\d{1,2}(?::\d{2})?(?:\s*[AaPp]\.?[Mm]\.?)?'; // 18:00    |    12AM

        // deadline
        if (
            preg_match("/Cancellation free until\s*(?<date>.{6,})\s+(?<time>{$patterns['time']})\s*hotel local time/i", $cancellation, $m) // en
            || preg_match("/CANCEL BEFORE\s*(?<time>{$patterns['time']})\s+(?<date>.{6,}?)\s*(?:All booking|$)/i", $cancellation, $m) // en
        ) {
            $h->booked()->deadline(strtotime($m['time'] . ' -1 minute', strtotime($m['date'])));
        } elseif (
            preg_match("/^(\d{1,2})([ap]m) one day prior to arrival /i", $cancellation, $m) // en
        ) {
            $h->booked()->deadlineRelative('1 day', $m[1] . ':00' . $m[2]);
        }

        // r.rate
        $rateText = '';
        $rateRows = $this->http->FindNodes("//text()[{$this->eq($this->t('Rate breakdown:'))}]/ancestor::*[{$xpathFragmentStrong}]/following::text()[normalize-space(.)][1][not(./ancestor::*[{$xpathFragmentStrong}])]/ancestor::*[self::p or self::div][1]/descendant::text()[normalize-space(.)]");

        foreach ($rateRows as $rateRow) {
            if (preg_match('/^(?<date>.{6,})\s+-\s+(?<payment>[^-]*\d[^-]*)$/', $rateRow, $m)) {
                // 29/11/2018 - 128.00 GBP
                $rateText .= "\n" . $m['payment'] . ' from ' . $m['date'];
            }
        }
        $rateRange = $this->parseRateRange($rateText);

        if ($rateRange !== null) {
            $r->setRate($rateRange);
        }
    }

    private function parseRateRange($string = '')
    {
        if (
            preg_match_all('/(?:^\s*|\b\s+)(?<amount>\d[,.\'\d ]*)[ ]*(?<currency>[A-Z]{3})[ ]+from[ ]+\b/', $string, $rateMatches) // 128.00 GBP from 29/11/2018
            || preg_match_all('/(?:^\s*|\b\s+)(?<currency>[^\d\s]\D{0,2}?)[ ]*(?<amount>\d[,.\'\d ]*)[ ]+from[ ]+\b/', $string, $rateMatches) // $239.20 from August 15
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

    private function htmlToText($string = ''): string
    {
        $string = str_replace("\n", '', $string);
        $string = preg_replace('/<br\b[ ]*\/?>/i', "\n", $string); // only <br> tags
        $string = preg_replace('/<[A-z]+\b.*?\/?>/', '', $string); // opening tags
        $string = preg_replace('/<\/[A-z]+\b[ ]*>/', '', $string); // closing tags
        $string = htmlspecialchars_decode($string);

        return trim($string);
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

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'normalize-space(' . $node . ")='" . $s . "'"; }, $field)) . ')';
    }

    private function starts($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'starts-with(normalize-space(' . $node . "),'" . $s . "')"; }, $field)) . ')';
    }

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'contains(normalize-space(' . $node . "),'" . $s . "')"; }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) { return preg_quote($s, '/'); }, $field)) . ')';
    }

    private function t($phrase)
    {
        if (!isset(self::$dict[$this->lang]) || !isset(self::$dict[$this->lang][$phrase])) {
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
