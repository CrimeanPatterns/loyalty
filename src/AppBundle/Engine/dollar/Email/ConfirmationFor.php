<?php

namespace AwardWallet\Engine\dollar\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Common\Rental;
use AwardWallet\Schema\Parser\Email\Email;

class ConfirmationFor extends \TAccountChecker
{
    public $mailFiles = "dollar/it-32425972.eml, dollar/it-64234246.eml, dollar/it-64541789.eml, dollar/it-84212865.eml, dollar/it-84436192.eml, dollar/it-88034986.eml";

    public $reFrom = ["emails.dollar.com"];

    public $reBody = [
        'en'  => ["You're good to go", 'Reservation Confirmation'],
        'en2' => ['We never forget whose dollar it is', 'Confirmation #'],
        'en3' => ["we're excited to see you", 'Confirmation Number'],
    ];
    public $reSubject = [
        'Confirmation for', 'Your rental for',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
            'Confirmation #'                   => ['Confirmation #', 'Confirmation Number'],
            'Pick-up Location'                 => ['Pick-up Location', 'Pick-Up Location', 'Location:'],
            'Pick-up Date'                     => ['Pick-up Date', 'Pick-Up Date', 'Date/Time'],
            'Drop-off Date'                    => ['Drop-off Date', 'Drop-Off Date'],
            'Vehicle Type'                     => ['Vehicle Type', 'Car Type'],
            'Dollar Express Renter Rewards'    => ['Dollar Express Renter Rewards', 'Frequent Flyer Program / American Airlines AAdvantage® /', 'Corporate Discount Number'],
            'Approximate Fees and Surcharges:' => ['Approximate Fees and Surcharges:', 'Approximate Total:'],
        ],
    ];

    private $patterns = [
        'phone' => '[+(\d][-. \d)(]{5,}[\d)]',
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug('can\'t determine a language');

            return $email;
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        if (!$this->parseEmail($email)) {
            return null;
        }

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href,'click.emails.dollar.com')] | //img[contains(@alt,'Dollar') or contains(@src,'image.emails.dollar.com')]")->length > 0) {
            return $this->assignLang();
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->reFrom as $reFrom) {
            if (stripos($from, $reFrom) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true && strpos($headers['subject'], 'Dollar Car Rental') === false) {
            return false;
        }

        if (isset($headers["subject"])) {
            foreach ($this->reSubject as $reSubject) {
                if (stripos($headers['subject'], $reSubject) !== false) {
                    return true;
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

    private function parseEmail(Email $email): bool
    {
        $r = $email->add()->rental();

        $confNoHtml = $this->http->FindHTMLByXpath("descendant::td[not(.//td) and {$this->starts($this->t('Confirmation #'))}][1]");
        $confNoText = $this->htmlToText($confNoHtml);

        if (preg_match("/^\s*({$this->opt($this->t('Confirmation #'))})[#:\s]+([-A-Z\d]{5,})\s*$/", $confNoText, $m)) {
            $r->general()->confirmation($m[2], $m[1]);
        }

        $travellerHtml = $this->http->FindHTMLByXpath("descendant::td[not(.//td) and {$this->starts($this->t('Name'))}][1]");
        $travellerText = $this->htmlToText($travellerHtml);

        if (preg_match("/^\s*{$this->opt($this->t('Name'))}[:\s]+([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])\s*$/u", $travellerText, $m)) {
            $r->general()->traveller($m[1]);
        } elseif ($traveller = $this->http->FindSingleNode("//text()[{$this->contains($this->t('good to go'))}]", null, true, "/{$this->opt($this->t('good to go'))}\,\s*(\D+)/")) {
            $r->general()->traveller(trim($traveller, '.'));
        }

        $accs = $this->http->FindNodes("//text()[{$this->contains($this->t('Dollar Express Renter Rewards'))}]",
            null, "/{$this->opt($this->t('Dollar Express Renter Rewards'))}[\s\/]+([\dA-Z]+)$/");

        if (!empty(array_filter($accs))) {
            $r->program()
                ->account(array_shift($accs), false);
        }

        if (0 === $this->http->XPath->query("//tr[contains(normalize-space(.), 'Return Information') and not(.//tr)]/ancestor-or-self::tr[following-sibling::tr][1]/following-sibling::tr[normalize-space(.)][1][contains(normalize-space(.), 'Location')]")->length) {
            $this->parseEmailType1($r);
        } elseif (
            0 < $this->http->XPath->query("//tr[contains(normalize-space(.), 'Return Information') and not(.//tr)]/ancestor-or-self::tr[following-sibling::tr][1]/following-sibling::tr[normalize-space(.)][1][contains(normalize-space(.), 'Location')]")->length
            && 0 < $this->http->XPath->query("//tr[contains(normalize-space(.), 'good to go')]")->length
        ) {
            $this->parseEmailType2($r);
        }

        $nodes = $this->http->XPath->query("//text()[{$this->eq($this->t('Approximate Fees and Surcharges:'))}]/ancestor::tr[1]/following-sibling::tr[count(./td)=2][normalize-space()][position()!=last()]");

        if ($nodes->length == 0) {
            $nodes = $this->http->XPath->query("//text()[{$this->eq($this->t('Taxes, Fees and Surcharges'))}]/ancestor::tr[1]/following-sibling::tr[count(./td)=2][normalize-space()][position()!=last()]");
        }

        foreach ($nodes as $root) {
            $sum = $this->getTotalCurrency($this->http->FindSingleNode("./td[2]", $root));

            if ($sum['Total'] !== null) {
                $r->price()
                    ->fee($this->http->FindSingleNode('td[1]', $root, true, '/^(.+?)[\s:：]*$/u'), $sum['Total']);
            }
        }

        $total = $this->getTotalCurrency($this->http->FindSingleNode("//text()[{$this->eq($this->t('Approximate Fees and Surcharges:'))}]/ancestor::tr[1]/following-sibling::tr[count(./td)=2][position()=last()]"));

        if (empty($total['Total'])) {
            $total = $this->getTotalCurrency($this->http->FindSingleNode("//text()[{$this->starts($this->t('Approx. Total Charges for Your Rental:'))}]", null, true, "/{$this->opt($this->t('Approx. Total Charges for Your Rental:'))}\s*(.+)/"));
        }

        if (empty($total['Total'])) {
            $total = $this->getTotalCurrency($this->http->FindSingleNode("//text()[{$this->starts($this->t('Approximate Total:'))}]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Approximate Total:'))}\s*(.+)/"));
        }

        if ($total['Total'] !== null) {
            $r->price()
                ->total($total['Total'])
                ->currency($total['Currency']);
        }
        $currency = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Currency:'))}]/following::text()[normalize-space()!=''][1]");

        if ($currency == 'U.S. DOLLAR') {
            $r->price()->currency('USD');
        }

        if ($currency == 'CANADIAN DOLLAR') {
            $r->price()->currency('CAD');
        }

        if ($currency == 'EURO') {
            $r->price()->currency('EUR');
        }
        $discount = $this->getTotalCurrency($this->http->FindSingleNode("//text()[{$this->eq($this->t('Discount:'))}]/following::text()[normalize-space()!=''][1]"));

        if ($discount['Total'] !== null) {
            $r->price()
                ->discount($discount['Total']);
        }

        return true;
    }

    private function parseEmailType1(Rental $r): void
    {
        $this->logger->debug(__FUNCTION__);

        $phone = $location = null;
        $locationHtml = $this->http->FindHTMLByXpath("descendant::td[not(.//td) and {$this->starts($this->t('Pick-up Location'))}][1]");
        $locationText = $this->htmlToText($locationHtml);

        if (preg_match("/^\s*{$this->opt($this->t('Pick-up Location'))}[:\s]+([\s\S]+?)[ ]*(?:\n+[ ]*({$this->patterns['phone']}))?\s*$/", $locationText, $m)) {
            $location = $m[1];

            if (!empty($m[2])) {
                $phone = $m[2];
            }
        }

        if (empty($phone)) {
            $phone = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Phone:'))}]/following::text()[normalize-space()][1]", null, true, "/^{$this->patterns['phone']}$/");
        }

        $datePickUp = null;
        $datePickUpHtml = $this->http->FindHTMLByXpath("descendant::td[not(.//td) and {$this->starts($this->t('Pick-up Date'))}][1]");
        $datePickUpText = $this->htmlToText($datePickUpHtml);

        if (preg_match("/^\s*{$this->opt($this->t('Pick-up Date'))}[:\s]+([\s\S]+?)\s*$/", $datePickUpText, $m)) {
            $datePickUp = preg_replace('/\s+/', ' ', $m[1]);
        }

        $r->pickup()
            ->location(preg_replace('/\s+/', ' ', $location))
            ->phone($phone, false, true)
            ->date($this->normalizeDate($datePickUp));

        $carTypeHtml = $this->http->FindHTMLByXpath("descendant::td[not(.//td) and {$this->starts($this->t('Vehicle Type'))}][1]");
        $carTypeText = $this->htmlToText($carTypeHtml);

        if (preg_match("/^\s*{$this->opt($this->t('Vehicle Type'))}[:\s]+(.+)\s*\-\s*(.+)\s*$/", $carTypeText, $m)) {
            $r->car()
                ->type($m[1])
                ->model($m[2]);
        }

        $dateDropOff = null;
        $dateDropTextOff = $this->http->FindHTMLByXpath("descendant::td[not(.//td) and {$this->starts($this->t('Drop-off Date'))}][1]");
        $dateDropOffText = $this->htmlToText($dateDropTextOff);

        if (preg_match("/^\s*{$this->opt($this->t('Drop-off Date'))}[:\s]+([\s\S]+?)\s*$/", $dateDropOffText, $m)) {
            $dateDropOff = preg_replace('/\s+/', ' ', $m[1]);
        } elseif (preg_match("/Return by (.+?) to the same location you picked up your rental vehicle/i",
            $this->http->FindSingleNode("//text()[{$this->eq($this->t('Return Information'))}]/following::text()[normalize-space()][1]"), $m)
        ) {
            $dateDropOff = $m[1];
        }

        if ($dateDropOff) {
            $r->dropoff()
                ->date($this->normalizeDate($dateDropOff))
                ->same();
        }

        if (empty($dateDropOffText)) {
            $dateDropOffText = $this->http->FindSingleNode("//text()[normalize-space()='Return Information']/following::text()[normalize-space()][1]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Return Information'))}\s*(.+)/u");

            if (!empty($dateDropOffText) && !preg_match("/[\d\:]+/", $dateDropOffText, $m)) {
                $r->dropoff()
                    ->location($dateDropOffText);

                $dropOffDate = $r->getPickUpDateTime();
                $rates = explode('. ', $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Rate Details')]/following::text()[normalize-space()][1]"));

                foreach ($rates as $rate) {
                    $parsed = false;

                    if (preg_match("/^(\d+) (?:[[:alpha:]]+ )?Days? /i", $rate, $m)) {
                        $dropOffDate = strtotime("+{$m[1]} days", $dropOffDate);
                        $parsed = true;
                    }

                    if (preg_match("/^(\d+) (?:[[:alpha:]]+ )?Weeks? /i", $rate, $m)) {
                        $dropOffDate = strtotime("+{$m[1]} weeks", $dropOffDate);
                        $parsed = true;
                    }

                    if (preg_match("/^(\d+) (?:[[:alpha:]]+ )?Hours? /i", $rate, $m)) {
                        $parsed = true;

                        continue;
                    }

                    if ($parsed !== true && preg_match("/^\s*(\d+ .+?) at .*\d+.* per /", $rate, $m)) {
                        $dropOffDate == null;

                        break;
                    }
                }

                if ($dropOffDate !== $r->getPickUpDateTime()) {
                    $r->dropoff()
                        ->date($dropOffDate);
                }
            }
        }
    }

    private function parseEmailType2(Rental $r): void
    {
        $this->logger->debug(__FUNCTION__);
        //$pickupXpath = "//tr[contains(normalize-space(.), 'Vehicle Pick-up Information') and not(.//tr)]/ancestor-or-self::tr[following-sibling::tr][1]/following-sibling::tr[normalize-space(.)][1][contains(normalize-space(.), 'Location')]";
        $pickupXpath = "//text()[normalize-space()='Pick-up Location:']/following::text()[normalize-space()][1]/ancestor::tr[1]";

        if ($pickupLoc = implode("\n", $this->http->FindNodes($pickupXpath . "/descendant::text()[normalize-space()]"))) {
            if (preg_match("/{$this->opt($this->t('Pick-up Location'))}\:?\s*(.+)\s([\d\-\s]+)/u", $pickupLoc, $m)) {
                $r->pickup()
                    ->location($m[1])
                    ->date(strtotime(str_replace(' @', ',', $this->http->FindSingleNode($pickupXpath . '/following-sibling::tr[1]', null, true, '/Date\/Time\:[ ]+(.+)/i'))))
                    ->phone($m[2]);
            } else {
                $r->pickup()
                    ->location($pickupLoc)
                    ->date(strtotime(str_replace(' @', ',', $this->http->FindSingleNode($pickupXpath . '/following-sibling::tr[1]', null, true, '/Date\/Time\:[ ]+(.+)/i'))))
                    ->phone($this->http->FindSingleNode($pickupXpath . '/following-sibling::tr[2]', null, true, '/Phone\:[ ]+(.+)/'));
            }
        }

        $dropoffXpath = "//tr[contains(normalize-space(.), 'Return Information') and not(.//tr)]/ancestor-or-self::tr[following-sibling::tr][1]/following-sibling::tr[normalize-space(.)][1][{$this->contains($this->t('Location'))}]";

        if ($dropoffLoc = $this->http->FindSingleNode($dropoffXpath, null, true, "/{$this->opt($this->t('Location'))}\:?\s*(.+)/")) {
            $r->dropoff()
                ->location($dropoffLoc)
                ->date(strtotime(str_replace(' @', ',', $this->http->FindSingleNode($dropoffXpath . '/following-sibling::tr[1]', null, true, '/Date\/Time\:[ ]+(.+)/i'))))
                ->phone($this->http->FindSingleNode($dropoffXpath . '/following-sibling::tr[2]', null, true, '/Phone\:[ ]+(.+)/i'))
            ;
        }

        $node = $this->http->FindSingleNode($pickupXpath . '/following-sibling::tr[3]');

        if (preg_match('/Car Type\:[ ]+(.+)[ ]+\w+[ ]*\-[ ]*(.+) or similar/i', $node, $m)) {
            $r->car()
                ->type($m[1])
                ->model($m[2]);
        } elseif ($node = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Vehicle Type:'))}]/following::text()[normalize-space()][1]")) {
            if (preg_match("/(.+)\s*\-\s*(.+)/u", $node, $m)) {
                $r->car()
                    ->type($m[1])
                    ->model($m[2]);
            }
        }
    }

    private function normalizeDate($date)
    {
//        $this->logger->debug($date);
        $in = [
            //Sunday, March 03, 2019 @ 10:30 PM
            '#^(\w+),\s+(\w+)\s+(\d+),\s+(\d{4})[\s@]+(\d+:\d+(?:\s*[ap]m)?)$#iu',
        ];
        $out = [
            '$3 $2 $4, $5',
        ];
        $str = strtotime($this->dateStringToEnglish(preg_replace($in, $out, $date)));

        return $str;
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
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if ($this->http->XPath->query("//*[contains(normalize-space(.),\"" . $reBody[0] . "\")]")->length > 0
                    && $this->http->XPath->query("//*[contains(normalize-space(.),\"" . $reBody[1] . "\")]")->length > 0
                ) {
                    $this->lang = substr($lang, 0, 2);

                    return true;
                }
            }
        }

        return false;
    }

    private function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function getTotalCurrency($node): array
    {
        $node = str_replace("€", "EUR", $node);
        $node = str_replace("$", "USD", $node);
        $node = str_replace("£", "GBP", $node);
        $tot = null;
        $cur = null;

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#u", $node, $m)
            || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#u", $node, $m)
        ) {
            $cur = $m['c'];
            $tot = PriceHelper::parse($m['t'], $m['c']);
        }

        return ['Total' => $tot, 'Currency' => $cur];
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
}
