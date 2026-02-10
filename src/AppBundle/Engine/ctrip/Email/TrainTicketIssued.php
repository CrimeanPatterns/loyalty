<?php

namespace AwardWallet\Engine\ctrip\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class TrainTicketIssued extends \TAccountChecker
{
    public $mailFiles = "ctrip/it-43211526.eml";

    public $reFrom = ["trip.com"];
    public $reBody = [
        'en' => ['Ticket Issued', 'Reservation Submitted'],
    ];
    public $reSubject = [
        'Ticket Issued', 'Reservation Submitted', // en
    ];
    public $lang = '';
    public $pdf;
    public $pdfNamePattern = ".*pdf";
    public static $dict = [
        'de' => [
            // HTML
            'Booking No.'     => 'Buchungsnr.',
            'Itinerary'       => 'Reiseroute',
            'Passengers'      => 'Fahrgäste',
            'Passport'        => 'Reisepass',
            // 'Ticket Pickup Info' => '',
            // 'Carriage' => '',
            'Payment Details' => 'Zahlungsdetails',
            // 'Adult' => '',
            'Booking fee'     => 'Buchungsgebühr',
            'Total'           => 'Gesamt',

            // PDF
            // 'Booked On:' => '',
            // 'Departure Time' => '',
            // 'Arrival Time' => '',
            // 'Train Number' => '',
        ],
        'en' => [
            // HTML
            'Itinerary'       => 'Itinerary',
            'Passengers'      => 'Passengers',
            'Payment Details' => 'Payment Details',

            // PDF
            // '' => '',
        ],
    ];
    private $keywordProv = ['trip.com', 'Trip.com'];
    private $date;

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getDate());
        $type = '';

        if ($this->assignLang()) {
            $type = 'html';
            $this->parseHtml($email);
        }

        if (count($email->getItineraries()) === 0) {
            $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

            if (isset($pdfs) && count($pdfs) > 0) {
                foreach ($pdfs as $pdf) {
                    if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) !== null) {
                        $html = \PDF::convertToHtml($parser->getAttachmentBody($pdf), 2);

                        if ($this->detectBody($text) && $this->assignLang($text)) {
                            $type = 'pdf';
                            $this->parsePdf($text, $email, $html);
                        }
                    }
                }
            }
        }
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($type) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href,'.trip.com')]")->length > 0) {
            foreach ($this->reBody as $reBody) {
                if ($this->http->XPath->query("//*[{$this->contains($reBody)}]")->length > 0) {
                    return $this->assignLang();
                }
            }
        }
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));
            // !!! short pdf is part of detect -  don't delete
            if (strlen($text) < 1000 && ($this->detectBody($text)) && $this->assignLang($text)) {
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

    public function detectEmailByHeaders(array $headers)
    {
        $fromProv = true;

        if (!isset($headers['from']) || $this->detectEmailFromProvider($headers['from']) === false) {
            $fromProv = false;
        }

        if (isset($headers["subject"])) {
            foreach ($this->reSubject as $reSubject) {
                if (($fromProv || preg_match("#\b{$this->opt($this->keywordProv)}\b#i", $headers["subject"]) > 0)
                    && stripos($headers["subject"], $reSubject) !== false
                ) {
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
        $types = 2; // html + pdf;
        $cnt = $types * count(self::$dict);

        return $cnt;
    }

    private function parsePdf($textPDF, Email $email, $html): void
    {
        $this->logger->debug(__FUNCTION__);

        $httpComplex = clone $this->http;
        $httpComplex->SetEmailBody($html);

        $confNo = $this->http->FindPreg("#^{$this->opt($this->t('Booking No.'))}[ ]*(\d{7,})#", false, $textPDF);

        $mainBlock = $this->strstrArr($textPDF, $this->t('Itinerary'));
        $mainBlock = $this->strstrArr($mainBlock, $this->t('Payment Details'), true);

        $paymentBlock = $this->strstrArr($textPDF, $this->t('Payment Details'));

        if (empty($paymentBlock) || empty($mainBlock) || empty($confNo)) {
            $this->logger->debug('other format pdf');

            return;
        }
        $r = $email->add()->train();

        $dateRes = $this->normalizeDate($this->http->FindPreg("#{$this->opt($this->t('Booked On:'))}[ ]*(.+)#", false,
            $textPDF));

        $pax = array_unique(explode('/', $httpComplex->FindSingleNode("//*[" . $this->contains($this->t('Passengers')) . "]/following-sibling::p[1]")));

        $r->general()
            ->confirmation($confNo, $this->t('Booking No.'))
            ->date($dateRes)
            ->travellers($pax, true)
        ;

        $segments = $this->splitter("#(.+\s+{$this->opt($this->t('Departure Time'))}:)#", $mainBlock);

        foreach ($segments as $segment) {
            $s = $r->addSegment();

            if (preg_match("#^(?<points>.+?)\s+\({$this->opt($this->t('Train Number'))}:\s*(?<number>\w+)\)\s+{$this->opt($this->t('Departure Time'))}:[ ]+(?<dep>.+)\s+{$this->opt($this->t('Arrival Time'))}:[ ]+(?<arr>.+)#",
                $segment, $m)) {
                $points = explode('-', $m['points']);

                if (count($points) === 2) {
                    $s->departure()->name($points[0]);
                    $s->arrival()->name($points[1]);
                }
                $s->extra()->number($m['number']);
                $s->departure()->date($this->normalizeDate($m['dep']));
                $s->arrival()->date($this->normalizeDate($m['arr']));
            }
        }

        $costVal = $this->http->FindPreg("#^[ ]*{$this->opt($this->t('Adult'))} ?[Xx] ?\d{1,3}[ ]{3,}(\S.*)\n+[ ]*{$this->opt($this->t('Booking fee'))}#m", false, $paymentBlock);
        $cost = $this->getTotalCurrency($costVal);
        $r->price()->cost($cost['Total']);
        $feeVal = $this->http->FindPreg("#\n[ ]*{$this->opt($this->t('Booking fee'))}[ ]{3,}(\S.*)#", false, $paymentBlock);
        $fee = $this->getTotalCurrency($feeVal);
        $r->price()->fee($this->t('Booking fee'), $fee['Total']);
        $totalVal = $this->http->FindPreg("#\n[ ]*{$this->opt($this->t('Total'))}[ ]{3,}(\S.*)#", false, $paymentBlock);
        $total = $this->getTotalCurrency($totalVal);
        $r->price()->currency($total['Currency'])->total($total['Total']);
    }

    private function parseHtml(Email $email): void
    {
        $this->logger->debug(__FUNCTION__);

        $xpath = "//text()[{$this->eq($this->t('Itinerary'))}]/ancestor::tr[./following-sibling::tr[{$this->starts($this->t('Passengers'))}]][1]/following-sibling::tr[1]/descendant::table[count(./descendant::tr[normalize-space()])=5]";
        $this->logger->debug("[XPATH]: " . $xpath);
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length === 0) {
            $xpath = "//text()[{$this->eq($this->t('Itinerary'))}]/ancestor::tr/following::table[descendant::img[contains(@src, 'order/train')]]";
            $this->logger->debug("[XPATH]: " . $xpath);
            $nodes = $this->http->XPath->query($xpath);
        }

        if ($nodes->length === 0) {
            $this->logger->debug('not fount segments in html');

            return;
        }
        $r = $email->add()->train();
        $conf = $this->http->FindSingleNode("(//text()[{$this->contains($this->t('Booking No.'))}])[1]",
            null, true, "/{$this->opt($this->t('Booking No.'))}\s+(\d{7,})\b/u");

        if (empty($conf)) {
            $conf = $this->http->FindSingleNode("(//text()[{$this->contains($this->t('Booking No.'))}]/following::text()[normalize-space()!=''][1])[1]");
        }
        $r->general()
            ->confirmation($conf,
                $this->t('Booking No.'));

        $pax = $this->http->FindNodes("//text()[{$this->eq($this->t('Passengers'))}]/ancestor::tr/following-sibling::tr[1]/descendant::table/descendant::tr[normalize-space()!='']/descendant::text()[normalize-space()!=''][1]");

        if (empty($pax)) {
            foreach ($nodes as $nod) {
                $pax = array_merge($pax, $this->http->FindNodes(".//td[descendant::text()[2][{$this->starts($this->t('Passport'))}]]/descendant::text()[normalize-space()][1]", $nod, "/^(.{2,}?)\s+{$this->opt($this->t('Passport'))}/"));
            }
        }
        $pax = array_unique($pax);

        if (empty($pax)) {
            $pax = array_unique($this->http->FindNodes("//td[descendant::text()[2][" . $this->starts($this->t('Passport')) . "]]/descendant::text()[1]"));
        }

        if (!empty($pax)) {
            $r->general()->travellers(array_unique($pax), true);
        }

        $ticketsNo = $this->http->FindNodes("//text()[{$this->eq($this->t('Ticket Pickup Info'))}]/following::tr[3]/td[text()[normalize-space()!='']]");

        if (empty($ticketsNo)) {
            $ticketsNo = $this->http->FindNodes("//text()[{$this->eq($this->t('Booking No.'))}]/following::tr[3]/td[text()[normalize-space()!='']]");
        }

        if (!empty($ticketsNo)) {
            $r->setTicketNumbers($ticketsNo, false);
        }

        foreach ($nodes as $i => $root) {
            $s = $r->addSegment();
            $date = $this->normalizeDate($this->http->FindSingleNode("./descendant::tr[normalize-space()!=''][1]",
                $root));
            $dep = $this->http->FindNodes("./descendant::tr[normalize-space()!=''][2]/descendant::text()[normalize-space()!='']",
                $root);

            if (count($dep) == 2) {
                $s->departure()
                    ->name($dep[1])
                    ->date(strtotime($dep[0], $date));
            }
            $s->extra()->duration($this->http->FindSingleNode("./descendant::tr[normalize-space()!=''][3]", $root));
            $arr = $this->http->FindNodes("./descendant::tr[normalize-space()!=''][4]/descendant::text()[normalize-space()!='']",
                $root);

            if (count($arr) == 2) {
                $s->arrival()
                    ->name($arr[1])
                    ->date(strtotime($arr[0], $date));
            }

            if ($s->getDepDate() && $s->getArrDate() && $s->getDepDate() > $s->getArrDate()) {
                $s->arrival()->date(strtotime("+1 day", $s->getArrDate()));
            }
            $place = $this->http->FindSingleNode("./descendant::tr[normalize-space()!=''][5]", $root);
            $s->extra()
                ->number($this->http->FindPreg("#^(\w+)\b#", false, $place))
                ->cabin($this->http->FindPreg("#^\w+\b\s+(.+)#", false, $place));

            $num = $i + 1;
            $seats = implode("\n",
                $this->http->FindNodes("./ancestor::tr[1]/following-sibling::tr[{$this->eq($this->t('Passengers'))}]/following-sibling::tr[1]/descendant::table[normalize-space()!=''][{$num}]/descendant::tr[normalize-space()!='']/descendant::text()[normalize-space()!=''][last()]",
                    $root));

            if (empty($seats)) {
                $seats = implode("\n",
                    $this->http->FindNodes('./descendant::tr[' . $this->contains($this->t('Passport')) . ']', $root));
            }

            if (preg_match_all("#{$this->opt($this->t('Carriage'))}\s+(\d+),\D+? (\d+\w*)\b#", $seats, $m,
                PREG_SET_ORDER)) {
                foreach ($m as $v) {
                    $s->extra()->car($v[1])->seat($v[2]);
                }
            }
        }
        $costVal = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Payment Details'))}]/ancestor::tr/following-sibling::tr[normalize-space()][1]/descendant::table/descendant::tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->starts($this->t('Adult'))}] ][1]/*[normalize-space()][2]");
        $cost = $this->getTotalCurrency($costVal);
        $r->price()->cost($cost['Total']);
        $fee = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Payment Details'))}]/ancestor::tr/following-sibling::tr/descendant::table/descendant::tr[{$this->starts($this->t('Booking fee'))}]/descendant::text()[normalize-space()!=''][last()]");
        $fee = $this->getTotalCurrency($fee);
        $r->price()
            ->fee($this->t('Booking fee'), $fee['Total']);
        $total = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Payment Details'))}]/ancestor::tr/following-sibling::tr/descendant::table/descendant::tr[{$this->starts($this->t('Total'))}]/descendant::text()[normalize-space()!=''][last()]");
        $total = $this->getTotalCurrency($total);
        $r->price()
            ->total($total['Total'])
            ->currency($total['Currency']);
    }

    private function normalizeDate($date)
    {
        $year = date('Y', $this->date);
        $in = [
            // Tue, Aug 20
            '/^([-[:alpha:]]+)[,.\s]+([[:alpha:]]+)[.\s]+(\d{1,2})$/u',
            // 13:08, Aug 28, 2019
            '/^(\d{1,2}:\d{2})[,\s]+([[:alpha:]]+)[.\s]+(\d{1,2})[.,\s]+(\d{4})$/u',
            // Jul 24, 2019
            '/^([[:alpha:]]+)[.\s]+(\d{1,2})[.,\s]+(\d{4})$/u',
            // Fr., 31. März
            '/^([-[:alpha:]]+)[,.\s]+(\d{1,2})[.\s]+([[:alpha:]]+)$/u',
        ];
        $out = [
            '$3 $2 ' . $year,
            '$3 $2 $4, $1',
            '$2 $1 $3',
            '$2 $3 ' . $year,
        ];
        $outWeek = [
            '$1',
            '',
            '',
            '$1',
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

    private function detectBody($body): bool
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $reBody) {
                if ($this->striposArr($body, $reBody)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function assignLang($body = null): bool
    {
        if (!isset($body)) {
            // by html
            foreach (self::$dict as $lang => $words) {
                if (isset($words['Payment Details'], $words['Itinerary'])) {
                    if ($this->http->XPath->query("//*[{$this->contains($words['Payment Details'])}]")->length > 0
                        && $this->http->XPath->query("//*[{$this->contains($words['Itinerary'])}]")->length > 0
                    ) {
                        $this->lang = $lang;

                        return true;
                    }
                }
            }
        } else {
            // by text
            foreach (self::$dict as $lang => $words) {
                if (isset($words['Payment Details'], $words['Itinerary'])) {
                    if ($this->striposArr($body, $words['Payment Details'])
                        && $this->striposArr($body, $words['Itinerary'])
                    ) {
                        $this->lang = $lang;

                        return true;
                    }
                }
            }
        }

        return false;
    }

    private function dateStringToEnglish(string $date): string
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
        $tot = '';
        $cur = '';

        if (preg_match("/^(?<c>[^\-\d)(]+?)\s*(?<t>\d[,.‘\'\d ]*)$/u", $node, $matches)
            || preg_match("/^(?<t>\d[,.‘\'\d ]*?)\s*(?<c>[^\-\d)(]+)$/u", $node, $matches)
        ) {
            // SGD216.71    |    45,32 €
            $cur = str_replace(['HK$', '€', '£', '₹'], ['HKD', 'EUR', 'GBP', 'INR'], $matches['c']);
            $currencyCode = preg_match('/^[A-Z]{3}$/', $cur) ? $cur : null;
            $tot = PriceHelper::parse($matches['t'], $currencyCode);
        }

        return ['Total' => $tot, 'Currency' => $cur];
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

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function opt($field): string
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function striposArr($haystack, $arrayNeedle): bool
    {
        $arrayNeedle = (array) $arrayNeedle;

        foreach ($arrayNeedle as $needle) {
            if (stripos($haystack, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    private function strstrArr(string $haystack, $needle, bool $before_needle = false): ?string
    {
        $needles = (array) $needle;

        foreach ($needles as $needle) {
            $str = strstr($haystack, $needle, $before_needle);

            if (!empty($str)) {
                return $str;
            }
        }

        return null;
    }
}
