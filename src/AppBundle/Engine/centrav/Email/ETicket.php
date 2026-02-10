<?php

namespace AwardWallet\Engine\centrav\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class ETicket extends \TAccountChecker
{
    public $mailFiles = "centrav/it-27807453.eml, centrav/it-27869019.eml, centrav/it-37914638.eml, centrav/it-37933895.eml";
    private $subjects = [
        'en' => [
            'E-Ticket Confirmation for Trip',
            'Booking Confirmation for Trip',
            'Schedule Change Notice for Trip',
        ],
    ];
    private $subject;
    private $langDetectors = [
        'en' => ['Validating Airline', 'Primary Airline'],
    ];
    private $lang = 'en';
    private static $dict = [
        'en' => [
            'Reference'     => ['Reference', 'Centrav Reference'],
            'Date Reserved' => ['Date Reserved', 'Date Ticketed'],
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@centrav.com') !== false || stripos($from, '@managedtrip.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true && strpos($headers['subject'], 'Centrav') === false) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ($phrases as $phrase) {
                if (stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        if (preg_match('/.+ - Booking Confirmation - [A-Z\d]{5,7}$/', $headers['subject'])) {
            return true;
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,".centrav.com/") or contains(@href,"managedtrip.com/")]')->length === 0
            && $this->http->XPath->query('//node()[contains(normalize-space(.),"Thank you for booking with Centrav") or contains(.,"@centrav.com")]')->length === 0
            && $this->http->XPath->query('//img[contains(@src,".centrav.com/") or contains(@src,"/managedtrip/")]')->length === 0
            && $this->http->XPath->query('//*[normalize-space()="Centrav Reference"]')->length === 0
        ) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->subject = $parser->getSubject();

        $this->assignLang();
        $this->parseEmail($email);
        $email->setType('ETicket' . ucfirst($this->lang));

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

    private function parseEmail(Email $email): void
    {
        $email->ota(); // because Centrav is not airline

        $otaConfirmation = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Reference'))}]/following::text()[normalize-space()][1]", null, true, '/^[A-Z\d]{5,}$/');
        $otaConfirmationTitle = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Reference'))}]");

        if (!$otaConfirmation
            && preg_match("/(?:Confirmation for Trip|Booking Confirmation -|Schedule Change Notice for Trip)\s*([A-Z\d]{5,})\b/", $this->subject, $m)
        ) {
            $otaConfirmation = $m[1];
            $otaConfirmationTitle = null;
        }

        $f = $email->add()->flight();

        // confirmation number
        if ($otaConfirmation) {
            $f->general()->noConfirmation();
        }

        // status
        $status = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Status'))}]/ancestor::td[1]/following-sibling::td[normalize-space(.)][1]");

        if (!empty($status)) {
            $f->general()->status($status);
        }

        // reservationDate
        $date = $this->http->FindSingleNode("(//text()[{$this->eq($this->t('Date Reserved'))}])[1]/ancestor::td[1]/following-sibling::td[normalize-space(.)][1]");

        if (!empty($date)) {
            $f->general()->date2($date);
        }

        // travellers
        // ticketNumbers
        // accountNumbers
        $tickets = $accounts = [];
        $travellerRows = $this->http->XPath->query("//table[ ./descendant::text()[normalize-space(.)][1][{$this->eq($this->t('Travelers'))}] ]/descendant::tr[not(.//tr) and {$this->starts($this->t('Traveler'))} and contains(.,'•') and ./following-sibling::tr[normalize-space(.)]]");

        foreach ($travellerRows as $travellerRow) {
            $passengerName = $this->http->FindSingleNode("following-sibling::tr[normalize-space()][1]", $travellerRow, true, '/^[[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]]$/u');
            $f->general()->traveller($passengerName, true);

            $ticketNumber = $this->http->FindSingleNode("following-sibling::tr[normalize-space()][2]", $travellerRow, true, "/^{$this->opt($this->t('Ticket Number'))}[:\s]+(\d{3}(?: | ?- ?)?\d{5,}(?: | ?- ?)?\d{1,3})$/i");

            if ($ticketNumber && !in_array($ticketNumber, $tickets)) {
                $f->issued()->ticket($ticketNumber, false, $passengerName);
                $tickets[] = $ticketNumber;
            }

            $ffNumberValues = $this->http->FindNodes("following-sibling::tr[normalize-space()][position()>1 and position()<4][{$this->contains($this->t('Frequent Traveler Number'))}]", $travellerRow);

            foreach ($ffNumberValues as $ffNumberVal) {
                if (preg_match("/^({$this->opt($this->t('Frequent Traveler Number'))})[:\s]+([A-Z\d]*[- ]*[.A-Z\d]{7,})$/", $ffNumberVal, $m)
                    && !in_array($m[2], $accounts)
                ) {
                    // Frequent Traveler Number    UA HT852614.2
                    $f->program()->account(preg_replace('/[.]+(\d)/', '-$1', $m[2]), false, $passengerName, $m[1]);
                    $accounts[] = $m[2];
                }
            }
        }

        // p.total
        // p.currencyCode
        $payment = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Grand Total Price'))}]/ancestor::td[1]/following-sibling::td[normalize-space(.)][1]");

        if (preg_match('/^(?<currency>[^\d)(]+)\s*(?<amount>\d[,.\'\d]*)$/', $payment, $matches)) {
            // $1,265.26
            $currency = $this->normalizeCurrency($matches['currency']);
            $currencyCode = preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null;
            $f->price()->currency($currency)->total(PriceHelper::parse($matches['amount'], $currencyCode));
        }

        $segments = $this->http->XPath->query("//text()[{$this->eq($this->t('Depart'))}]/ancestor::tr[ ./following-sibling::tr[normalize-space(.)][1][ ./descendant::text()[normalize-space(.)][1][{$this->eq($this->t('Arrive'))}] ] ]");

        $confNumbersFromSegments = [];

        foreach ($segments as $segment) {
            $s = $f->addSegment();

            $xpathFragmentFlight = "./preceding-sibling::tr[descendant::img[contains(@src, 'airlineLogos')] or descendant::img][normalize-space(.)][1]/descendant::tr[not(.//tr) and ./*[3] ]";

            // flightNumber
            // bookingCode
            // cabin
            $airlineName = '';
            $flightHead = $this->http->FindSingleNode($xpathFragmentFlight . "/*[2]", $segment);

            // Alaska Airlines 54 • Class R
            // American Airlines 6156  •  Class W  •  Economy
            if (preg_match("/^(?<airName>.+?)\s*•?\s*(?<fNumber>\d{1,4})\s*•\s*{$this->opt($this->t('Class'))}\s+(?<bookCode>[A-Z]{1,2})\s*•?\s*(?<cabin>.+?)?$/ui", $flightHead, $matches)
            || preg_match("/^(?<airName>.+?)\s*•?\s*(?<fNumber>\d{1,4})\s*•\s*(?:(?<cabin>\D+))?\s*•\s*{$this->opt($this->t('Class'))}\s+(?<bookCode>[A-Z]{1,2})\s*•?\s*(?:\d+\s*Bags? Included)?$/ui", $flightHead, $matches)) {
                $airlineName = $matches['airName'];
                $s->airline()->number($matches['fNumber']);
                $s->extra()->bookingCode($matches['bookCode']);
                $s->extra()->cabin($matches['cabin'], false, true);
            }

            // airlineName
            $airlineCode = $this->http->FindSingleNode($xpathFragmentFlight . "/*[1]//img/@src", $segment, true, '/\/([A-Z][A-Z\d]|[A-Z\d][A-Z])\.\w{3,}$/');

            $s->airline()->name($airlineCode ? $airlineCode : $airlineName);

            // duration
            $duration = $this->http->FindSingleNode($xpathFragmentFlight . "/*[3]", $segment, true, '/^\d[\d hrm]+$/i');
            $s->extra()->duration($duration);

            // depDate
            $dateDep = $this->http->FindSingleNode('./*[normalize-space(.)][2]', $segment);
            $timeDep = $this->http->FindSingleNode('./*[normalize-space(.)][3]', $segment);
            $s->departure()->date2($dateDep . ' ' . $timeDep);

            // depName
            // depTerminal
            // depCode
            $s->departure()
                ->name($this->http->FindSingleNode('./*[normalize-space(.)][4]', $segment))
                ->terminal($this->http->FindSingleNode('./*[normalize-space(.)][5]', $segment, true, '/ Terminal\s+([A-Z\d]+)$/i'), true, true)
                ->code($this->http->FindSingleNode('./*[normalize-space(.)][last()]', $segment, true, '/^[A-Z]{3}$/'));

            $xpathFragmentArr = "./following-sibling::tr[normalize-space(.)][1]";

            // arrDate
            $dateArr = $this->http->FindSingleNode($xpathFragmentArr . '/*[normalize-space(.)][2]', $segment);
            $timeArr = $this->http->FindSingleNode($xpathFragmentArr . '/*[normalize-space(.)][3]', $segment);
            $s->arrival()->date2($dateArr . ' ' . $timeArr);

            // arrName
            // arrTerminal
            // arrCode
            $s->arrival()
                ->name($this->http->FindSingleNode($xpathFragmentArr . '/*[normalize-space(.)][4]', $segment))
                ->terminal($this->http->FindSingleNode($xpathFragmentArr . '/*[normalize-space(.)][5]', $segment, true, '/ Terminal\s+([A-Z\d]+)$/i'), true, true)
                ->code($this->http->FindSingleNode($xpathFragmentArr . '/*[normalize-space(.)][last()]', $segment, true, '/^[A-Z]{3}$/'));

            // seats
            // confirmation
            // miles
            // aircraft
            $flightFooter = $this->http->FindSingleNode("./following-sibling::tr[normalize-space(.)][2]", $segment);

            if (preg_match("/^(?:{$this->opt($this->t('Seats'))}\s+(\d{1,5}[A-Z][, A-Z\d]*)\s*•\s*)?{$this->opt($this->t('Airline Reference'))}\s+([A-Z\d]{5,})\s*•\s*{$this->opt($this->t('Miles'))}\s+(\d+)\s*•\s*([^•]+)$/", $flightFooter, $matches)) {
                // Seats 18E, 18D • Airline Reference G7USDK • Miles 4119 • Boeing 767-400
                if (!empty($matches[1])) {
                    $s->extra()->seats(preg_split('/\s*,\s*/', $matches[1]));
                }
                $s->airline()->confirmation($matches[2]);
                $confNumbersFromSegments[] = $matches[2];
                $s->extra()
                    ->miles($matches[3])
                    ->aircraft($matches[4]);
            }

            // operatedBy
            $operator = $this->http->FindSingleNode("./following-sibling::tr[normalize-space(.)][position()<4][{$this->contains($this->t('is operated by'))}]", $segment, true, "/{$this->opt($this->t('is operated by'))}\s+(.+)$/");

            if (preg_match('/-\s*([A-Za-z\d]{2})\s*(\d+)/', $operator, $m)) {
                // airline maybe in lower case
                $s->airline()
                    ->carrierName($m[1])
                    ->carrierNumber($m[2]);
            } else {
                $s->airline()->operator($operator, false, true);
            }
        }

        // if (count(array_unique($confNumbersFromSegments)) > 1
        //     || count(array_unique($confNumbersFromSegments)) === 1 && $confNumbersFromSegments[0] !== $otaConfirmation // it-27869019.eml
        // ) {
        $email->ota()->confirmation($otaConfirmation, $otaConfirmationTitle);
        // }
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
                if ($this->http->XPath->query("//*[{$this->contains($phrase)}]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }
}
