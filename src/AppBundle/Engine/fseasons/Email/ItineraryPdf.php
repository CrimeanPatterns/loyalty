<?php

namespace AwardWallet\Engine\fseasons\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class ItineraryPdf extends \TAccountChecker
{
    public $mailFiles = "fseasons/it-155008104.eml, fseasons/it-398080953.eml, fseasons/it-41974722.eml, fseasons/it-42118999.eml, fseasons/it-631494812.eml, fseasons/it-65389712.eml, fseasons/it-79528547.eml, fseasons/it-652068587-dorchester.eml";

    public $reFrom = ["fourseasons.com"];
    public $reBody = [
        'en' => [
            'Breakfast and lunch are included in your tour', 'Itinerary for', 'Confirmation for',
            'Transportation reservation for you', 'to confirm the following arrangements for',
            'We are so excited that you have already begun planning your experiences with us',
            'your current itinerary our Pre-Arrival',
        ],
    ];
    public $lang = '';
    public $pdfNamePattern = ".*pdf";
    public $pdfFull;
    public static $dict = [
        'en' => [
            'Date:'                     => 'Date:',
            'Time:'                     => 'Time:',
            'Date and Time'             => ['Date and Time', 'Date & Time', 'Pick Up Date & Time'],
            'Dear'                      => ['Dear', 'Itinerary for', 'Confirmation for'],
            'Number of Guests:'         => ['Number of Guests:', 'Number of Adults:', 'Adults', '# People', 'Number of Guests', 'Number of People', 'Number of Passengers'],
            'Description:'              => ['Description:', 'Service'],
            'Pick-up Location:'         => ['Pick-up Location:', 'Pick-up Location', 'Pick-up Address', 'Pick Up Location'],
            'Drop-off Location:'        => ['Drop-off Location:', 'Destination', 'Drop-off Address'],
            'Vehicle Selected:'         => ['Vehicle Selected:', 'Car Type'],
            'Vendor Name:'              => ['Vendor Name:', 'Vendor', 'Venue'],
            'Vendor Address:'           => ['Vendor Address:', 'Address'],
            'Phone:'                    => ['Phone:', 'Phone'],
            // 'Confirmation #'         => ['Confirmation #'],
        ],
    ];
    private $providerCode = '';
    private static $detectProviders = [
        'fseasons'   => ['www.fourseasons.com', 'Four Seasons Hotel'],
        'auberge'    => ['provided by Auberge', 'Chileno Bay Resort and Residences', ' to Mauna Lani', 'Tel 808-885-6622'],
        'wynnlv'     => ['Desk at (888) 320-7122', 'Wynn Tower Suites'],
        'rosewood'   => ['and holding a “Rosewood” sign', 'Tel 52 984 875 80 34', 'Rosewood Mayakoba', 'Rosewood Little Dix Bay'],
        'dorchester' => ['@dorchestercollection.com'],
    ];
    private $pax;

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdfs) && count($pdfs) > 0) {
            foreach ($pdfs as $pdf) {
                if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) !== null) {
                    if ($this->assignLang($text) && $this->detectBody($text, $parser->getHeaders())) {
                        $this->parseEmailPdf($text, $email);
                    }
                }
            }
        }

        $email->setProviderCode($this->providerCode);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if ($this->assignLang($text)
                && $this->detectBody($text, $parser->getHeaders())
            ) {
                return true;
            }
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

    public static function getEmailProviders()
    {
        return array_keys(self::$detectProviders);
    }

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        $types = 2; //transfer | events
        $cnt = $types * count(self::$dict);

        return $cnt;
    }

    private function parseEmailPdf($textPDF, Email $email): void
    {
        $this->pax = $this->re("#^[ ]*{$this->opt($this->t('Dear'))}:?\s*(.+?)(?:,|-|$)#m", $textPDF);

        if (empty($this->pax)) {
            $this->pax = $this->re("#^[ ]*Aloha ([A-Z][[:alpha:] ]+?) Fowler 'Ohana #m", $textPDF);
        }
        $this->pax = preg_replace("/^\s*(Mr\. and Mrs\.|Mr\.|Mrs\.|Ms\.)\s*/", '', $this->pax);
        $this->pdfFull = $textPDF;

        $segments = $this->splitter("#\n([ ]*{$this->t('Offering:')}.+)#", $textPDF);

        if (empty($segments)) {
            $segments = $this->splitter("#\n([ ]*{$this->t('Date:')}[^\n]+\n[ ]*{$this->t('Time:')})#", $textPDF);
        }

        if (empty($segments)) {
            $segments = $this->splitter("#\n([ ]*{$this->t('Date:')}[^\n]+\n[ ]*{$this->t('Request Type:')})#", $textPDF);
        }

        if (empty($segments)) {
            $segments = $this->splitter("#\n([ ]*{$this->opt($this->t('Date and Time'))}\s+)#", $textPDF);
        }

        foreach ($segments as $key => $segment) {
            $type = $this->re("/{$this->opt($this->t('Offering:'))}[ ]+(.{2,})/", $segment)
                ?? $this->re("/{$this->opt($this->t('Request Type:'))}[ ]+(.{2,})/", $segment)
                ?? $this->re("/{$this->opt($this->t('Description:'))}[ ]+(.{2,})/", $segment)
            ;

            $type = trim($type);
            $this->logger->debug("Segment-{$key} request type value: " . $type);

            switch (true) {
                case $type === 'Arrival Transportation':
                case $type === 'Arrival-Transportation':
                case $type === 'Airport Transportation Service':
                case $type === 'Departure Transportation':
                case $type === 'Transportation Services':
                case strpos($type, 'LIR Departure') === 0:
                case strpos($type, 'Transfer for') === 0:
                    if (!$this->parseTransfer($segment, $email)) {
                        return;
                    }

                    break;

                case $type === 'Golf':
                case $type === 'Tours':
                case $type === 'Massage':
                case $type === 'Spa':
                case strpos($type, 'Restaurant') !== false: // case $type === 'Restaurant Reservation':
                case strpos($type, 'Marine Adventure') !== false:
                case $type === 'Tennis':
                case $type === 'Activity':
                case $type === 'Dining Reservation':
                case $type === 'Beach Drops':
                    if (!$this->parseEvent($segment, $email)) {
                        return;
                    }

                    break;

                case strpos($type, ' Transportation') !== false:
                    if (!$this->parseTransfer($segment, $email)) {
                        return;
                    }

                    break;

                default:
                    if (preg_match("/\n {0,10}Activity {3,}/", $segment)) {
                        if (!$this->parseEvent($segment, $email)) {
                            return;
                        }
                    } else {
                        $this->logger->debug('Unknown Request Type');

                        return;
                    }
            }
        }
        // $this->logger->debug($textPDF);
    }

    private function parseTransfer($textPDF, Email $email): bool
    {
        // examples: it-155008104.eml, it-42118999.eml, it-65389712.eml

        $r = $email->add()->transfer();

        $conf = $this->re("/{$this->opt($this->t('Confirmation #'))}[ ]+([A-Z\d]{5,})(?: \d)\n/", $textPDF);

        if (!empty($conf)) {
            $r->general()
                ->confirmation($conf);
        } else {
            $r->general()
                ->noConfirmation();
        }
        $r->general()
            ->traveller($this->pax);

        $cancellation = trim(preg_replace("#\s+#", ' ', $this->re("#Cancellation Policy:[ ]*(.+?)\n\n#s", $textPDF)));

        if (!empty($cancellation)) {
            $r->general()
                ->cancellation($cancellation);
        }

        $dateStr = $this->re("#{$this->t('Date:')}[ ]+(.+)#",
                $textPDF) . ', ' . $this->re("#{$this->t('Time:')}[ ]+(.+)#", $textPDF);

        if (strlen(trim($dateStr)) === 1) {
            $dateStr = $this->re("#{$this->opt($this->t('Date and Time'))}[ ]+(.+)#", $textPDF);
        }
        $date = $this->normalizeDate($dateStr);
        $s = $r->addSegment();
        $depAddr = $this->re("#\n *{$this->opt($this->t('Pick-up Location:'))}[ ]+(.+)#", $textPDF);
        $arrAddr = $this->re("#\n *{$this->opt($this->t('Drop-off Location:'))}[ ]+(.+)#", $textPDF);

        $address = $this->normalizeAddr($depAddr, $arrAddr);

        if (preg_match('/^\s*[A-Z]{3}\s*$/', $address)) {
            $s->departure()
                ->code($address);
        } else {
            $s->departure()
                ->address($address);
        }
        $s->departure()
            ->date($date);

        $address = $this->normalizeAddr($arrAddr, $depAddr);

        if (preg_match('/^\s*[A-Z]{3}\s*$/', $address)) {
            $s->arrival()
                ->code($address);
        } else {
            $s->arrival()
                ->address($address);
        }

        $s->arrival()
            ->noDate();
        $type = $this->re("/{$this->opt($this->t('Vehicle Selected:'))}[ ]*(.{2,})/", $textPDF)
                ?? $this->re("/{$this->opt($this->t('Description:'))}[ ]+.*(\bSUV\b).*/", $textPDF);

        if (!empty($type)) {
            $s->extra()
                ->type($type);
        }
        $s->extra()
            ->adults($this->re("/{$this->opt($this->t('Number of Guests:'))}[ ]*(\d{1,3})(?: adults?)?[ ]*\n/i", $textPDF))
            ->kids($this->re("/{$this->opt($this->t('Number of Children:'))}[ ]*(\d{1,3})[ ]*\n/", $textPDF), true, true)
        ;

        $total = $this->re("#{$this->t('Total Cost:')}[ ]*\*?(.+?)(?: SUV)?(?:\n|$)#", $textPDF);

        if ($total !== null) {
            $total = $this->getTotalCurrency($total);

            if ($total['Total'] !== '') {
                $r->price()
                    ->total($total['Total'])
                    ->currency($total['Currency']);
            }
        }

        return true;
    }

    private function normalizeAddr(?string $target, ?string $source): ?string
    {
        if ($target == 'DIA' && stripos($source, 'Denver') !== false) {
            return 'DIA, Denver CO USA';
        }

        if (preg_match("/^Four Seasons(?: Hotel)?$/i", $target) > 0
            && preg_match("/Page\s*1\s*of\s*\d+\s*(Four\s*Seasons\D+?)(?:[ ]{2}|[ ]+[+]|\n)/i", $this->pdfFull, $m)
        ) {
            $target = $m[1];
        }

        return $target;
    }

    private function parseEvent($textPDF, Email $email): bool
    {
        // examples: it-41974722.eml

        $eventName = $this->re("/(?:^|\n) *{$this->opt($this->t('Vendor Name:'))}[ ]{2,}(\S.{2,})/", $textPDF)
            ?? $this->re("/{$this->opt($this->t('Description:'))}[ ]+(.{2,}? Restaurant) With /i", $textPDF)
            ?? $this->re("/{$this->opt($this->t('Description:'))}[ ]+(.{2,})\n+[ ]{0,15}\S.+/", $textPDF)
        ;

        $address = $this->re("/{$this->opt($this->t('Vendor Address:'))}[ ]+(.{3,})/", $textPDF)
            ?? $this->re("/{$this->t('Departure Location:')}[ ]+(.{3,})/", $textPDF)
            ?? $eventName
        ;

        $pattern = "/^\s*{$this->opt($this->t('Date:'))}.*"
            . "\n+[ ]*{$this->opt($this->t('Time:'))}.*"
            . "\n+[ ]*{$this->opt($this->t('Request Type:'))}.*"
            . "(?:\n+[ ]*{$this->opt($this->t('Activity/Tour Start Time:'))}.*)?"
            . "(?:\n+[ ]*{$this->opt($this->t('Duration:'))}.*)?"
            . "\n+[ ]*{$this->opt($this->t('Number of Guests:'))}.*"
            . "(?:\n+[ ]*{$this->opt($this->t('Total Cost:'))}.*)?"
            . "\n+[ ]*{$this->opt($this->t('Cancellation Policy:'))}.*"
            . "/";

        if (empty($eventName) && empty($address) && preg_match($pattern, $textPDF)) {
            $this->logger->debug('Wrong event format! Skipped.');

            return true;
        }

        $r = $email->add()->event();

        if (preg_match("/\n *({$this->opt($this->t('Request Type:'))}|{$this->opt($this->t('Description:'))})[ ]+(Restaurant Reservation)/", $textPDF)) {
            $r->type()
                ->restaurant();
        } else {
            $r->type()
                ->event();
        }

        $conf = $this->re("/{$this->opt($this->t('Confirmation #'))}[ ]+([A-Z\d]{5,})\n/", $textPDF);

        if (!empty($conf)) {
            $r->general()
                ->confirmation($conf);
        } else {
            $r->general()
                ->noConfirmation();
        }
        $r->general()
            ->traveller($this->pax);

        if ($cancellation = trim(preg_replace("#\s+#", ' ', $this->re("#Cancellation Policy:[ ]+(.+?)\n\n#s", $textPDF)))) {
            $r->general()
                ->cancellation($cancellation);
        }

        $dateStr = $this->re("#{$this->t('Date:')}[ ]+(.+)#",
                $textPDF) . ', ' . $this->re("#{$this->t('Time:')}[ ]+(.+)#", $textPDF);

        if (strlen(trim($dateStr)) === 1) {
            $dateStr = $this->re("#{$this->opt($this->t('Date and Time'))}[ ]+(.+)#", $textPDF);
        }
        $date = $this->normalizeDate($dateStr);
        $r->booked()
            ->start($date)
            ->noEnd();

        $guests = $this->re("/\n *{$this->opt($this->t('Number of Guests:'))}[ ]*(\d{1,3})( adults?)?[ ]*\n/i", $textPDF);

        if (!empty($guests)) {
            $r->booked()
                ->guests($guests);
        } else {
            if (preg_match_all("/Vendor Name:\s*{$r->getName()}.*\n.+\nGender Preference:\s*(\w+)\n/", $this->pdfFull, $m)) {
                $r->booked()
                    ->guests(count($m[1]));
            }
        }

        $phone = $this->re("/{$this->opt($this->t('Phone:'))}[ ]+(.+)/", $textPDF);

        $r->place()->name($eventName)->address($address)->phone($phone, false, true);

        $total = $this->re("#{$this->t('Total Cost:')}[ ]*(.+)#", $textPDF);

        if ($total !== null) {
            $total = $this->getTotalCurrency($total);

            if ($total['Total'] !== '') {
                $r->price()
                    ->total($total['Total'])
                    ->currency($total['Currency']);
            }
        }

        return true;
    }

    private function normalizeDate($date)
    {
        //$this->logger->notice($date);
        $in = [
            //Monday, October 21, 2019, 10:00 AM
            '#^\w+,\s+(\w+)\s+(\d+),\s+(\d{4}),\s+(\d+:\d+(?:\s*[ap]m)?)$#ui',
        ];
        $out = [
            '$2 $1 $3, $4',
        ];
        $outWeek = [
            '',
        ];

        if (!empty($week = preg_replace($in, $outWeek, $date))) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($week, $this->lang));
            $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));
            $str = EmailDateHelper::parseDateUsingWeekDay($str, $weeknum);
        } else {
            $str = strtotime($this->dateStringToEnglish(preg_replace($in, $out, $date)));
        }

        return $str;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function detectBody($body, $headers): bool
    {
        $detectProvider = false;

        foreach (self::$detectProviders as $code => $phrases) {
            if ($this->stripos($body, $phrases)) {
                $detectProvider = true;
                $this->providerCode = $code;

                break;
            }
        }

        if (!$detectProvider) {
            if (preg_match('/[.@]dorchestercollection\.com$/i', rtrim($headers['from'], '> ')) > 0) {
                $detectProvider = true;
                $this->providerCode = 'dorchester';
            }
        }

        if ($detectProvider === true && isset($this->reBody)) {
            foreach ($this->reBody as $reBody) {
                if ($this->stripos($body, $reBody)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function assignLang($body): bool
    {
        foreach (self::$dict as $lang => $words) {
            if (isset($words['Date:'], $words['Time:'])) {
                if (stripos($body, $words['Date:']) !== false && stripos($body, $words['Time:']) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }

            if (isset($words['Date and Time'])) {
                if ($this->containsText($body, $words['Date and Time']) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function containsText($text, $needle): bool
    {
        if (empty($text) || empty($needle)) {
            return false;
        }

        if (is_array($needle)) {
            foreach ($needle as $n) {
                if (stripos($text, $n) !== false) {
                    return true;
                }
            }
        } elseif (is_string($needle) && stripos($text, $needle) !== false) {
            return true;
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
        $node = str_replace(["€", "£", "$", "₹"], ["EUR", "GBP", "USD", "INR"], $node);
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)
            || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m)
        ) {
            $cur = $m['c'];
            $currencyCode = preg_match('/^[A-Z]{3}$/', $cur) ? $cur : null;
            $tot = PriceHelper::parse($m['t'], $currencyCode);
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }

    private function re($re, $str, $c = 1): ?string
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function splitter($regular, $text): array
    {
        $result = [];

        $array = preg_split($regular, $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        array_shift($array);

        for ($i = 0; $i < count($array) - 1; $i += 2) {
            $result[] = $array[$i] . $array[$i + 1];
        }

        return $result;
    }

    private function stripos($haystack, $arrayNeedle): bool
    {
        $arrayNeedle = (array) $arrayNeedle;

        foreach ($arrayNeedle as $needle) {
            if (stripos($haystack, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    private function opt($field): string
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }
}
