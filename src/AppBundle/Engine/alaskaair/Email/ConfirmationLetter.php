<?php

namespace AwardWallet\Engine\alaskaair\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parsing\Html;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class ConfirmationLetter extends \TAccountChecker
{
    public $mailFiles = "alaskaair/it-11673499.eml, alaskaair/it-16.eml, alaskaair/it-17.eml, alaskaair/it-1928374.eml, alaskaair/it-2289952.eml, alaskaair/it-2290059.eml, alaskaair/it-3088388.eml, alaskaair/it-3171780.eml, alaskaair/it-3490540.eml, alaskaair/it-6.eml";

    public $lang = '';
    public $date;

    public static $dict = [
        'en' => [
            'confCode'                     => ['Confirmation code:', 'Confirmation Code:', 'confirmation code:'],
            'Summary of airfare charges'   => ['Summary of airfare charges', 'Summary of Airfare Charges'],
            'Total charges for air travel' => ['Total charges for air travel', 'Amount Due For Air Travel'],
            'programNames'                 => ['Mileage Plan', 'Delta SkyMiles Elite Gold Member', 'American Executive Platinum Member'],
            'Ticket'                       => ['Ticket', 'New Ticket'],
            'Taxes and Other Fees'         => ['Taxes and Other Fees', 'Partner Award Booking Fee'],
        ],
    ];

    private $subjects = [
        'en' => ['Confirmation Letter -', 'Confirmation for '],
    ];

    private $langDetectors = [
        'en' => ['Confirmation code:', 'Confirmation Code:', 'confirmation code:'],
    ];

    // alaska email with subject: Confirmation Letter - <code date> - from Alaska Airlines
    // has line 'Below is your booking confirmation.' and pretty table with flight info
    // it-6, it-16, it-17

    public function parseEmail(Email $email, $headers)
    {
        if (preg_match("#(\d+/\d+/\d+)#i", $headers['subject'], $m)) {
            $this->date = strtotime($m[1]);
        }
        $http = $this->http;
        $xpath = $http->XPath;
        $xpath->registerNamespace('php', 'http://php.net/xpath');
        $xpath->registerPhpFunctions(['stripos', 'CleanXMLValue']);

        $f = $email->add()->flight();

        // confirmation number
        $confirmationNumberTitle = $this->http->FindSingleNode("(//text()[{$this->contains($this->t('confCode'))}])[1]");
        $confirmationNumber = $this->http->FindSingleNode("(//text()[{$this->contains($this->t('confCode'))}])[1]/following::text()[string-length(normalize-space(.))>1][1]", null, true, '/^([A-Z\d]{5,})$/');
        $f->general()->confirmation($confirmationNumber, preg_replace('/\s*:\s*$/', '', $confirmationNumberTitle));

        // Price
        if (empty($this->http->FindSingleNode("(//text()[normalize-space()='New Ticket Value'])[1]"))) {
            $payment = $this->http->FindSingleNode("(//text()[normalize-space(.)='Total charges for air travel'])[1]/following::text()[string-length(normalize-space(.))>1][1]");

            if ($payment !== null) {
                $f->price()
                    ->currency($this->currency($payment))
                    ->total($this->amount($payment));
            }
            $costsText = $this->http->FindNodes("//td[not(.//td)][normalize-space(.)='Base Fare and Surcharges'][not(following::text()[normalize-space()='Summary of airfare charges'])]/following-sibling::td[string-length(normalize-space(.))>1][1]");

            foreach ($costsText as $text) {
                $cost = isset($cost) ? ($this->amount($text) + $cost) : $this->amount($text);
            }

            if (isset($cost)) {
                $f->price()
                    ->cost($cost);
            }

            $taxXpath = "//td[not(.//td)][" . $this->starts($this->t("Taxes and Other Fees")) . "][not(following::text()[normalize-space()='Summary of airfare charges'])]/following-sibling::td[string-length(normalize-space(.))>1][1]";

            foreach ($this->http->XPath->query($taxXpath) as $root) {
                $f->price()
                    ->fee($this->http->FindSingleNode("preceding-sibling::td[string-length(normalize-space(.))>1][1]", $root),
                        $this->amount($root->nodeValue));
            }

            $spentAwards = $this->http->FindSingleNode("//text()[contains(., 'miles have been redeemed from Mileage Plan')]",
                null, true, "/^\s*(\d[\d,]* miles)/");

            if (!empty($spentAwards)) {
                $f->price()
                    ->spentAwards($spentAwards);
            }
        }

        $headingRowNode = $xpath->query('//td[contains(string(), "Flight") and not(.//td) and following-sibling::td[contains(string(), "Departs")]]/ancestor::tr[1]');

        if ($headingRowNode->length > 0) {
            $headingRowNode = $headingRowNode->item(0);

            $passengers = [];

            // each non-empty row
            $flightRowNodes = $xpath->query('following-sibling::tr[string-length(normalize-space(.)) > 3 and not(contains(., "Confirmation code")) and not(contains(., "Check in with"))]', $headingRowNode);

            foreach ($flightRowNodes as $rowNode) {
                $segments = $f->getSegments();
                $segmentsCount = count($segments);

                if (
                    isset($segments[$segmentsCount - 1]) && isset($s)
                    && preg_match('/Operated by\s+(.+)\s+as\s+(.+)\. Check in with/ims', Html::cleanXMLValue($rowNode->nodeValue), $matches)
                ) {
                    // set/change AirlineName for last segment
                    $s->setAirlineName($matches[1]);

                    continue;
                }

                if (false !== stripos($rowNode->nodeValue, 'Operated by')) {
                    continue;
                }

                $s = $f->addSegment();

                // flight
                if ($flightNode = $this->getCellNodeByColumnName('Flight', $rowNode, $headingRowNode)) {
                    $flightHtml = $this->http->FindHTMLByXpath('.', null, $flightNode);
                    $flightText = $this->htmlToText($flightHtml);
                    $flightTexts = preg_split('/\s*\n+\s*/', $flightText);

                    if (count($flightTexts) === 2) {
                        $flight = $flightTexts[0];
                        $s->extra()->aircraft($flightTexts[1]);
                    } elseif ($this->http->XPath->query('descendant::img[normalize-space(@title)]', $flightNode)->length === 1) {
                        // there is a pic with title, that contains flight
                        $flight = $this->http->FindSingleNode('descendant::img[normalize-space(@title)]/@title', $flightNode);
                    } elseif (count($flightTexts) === 1) {
                        $flight = $flightTexts[0];
                    } else {
                        $flight = null;
                    }

                    if (preg_match("/^(\w[\w ]*\w)[ ]+(\d+)$/", $flight, $m)) {
                        $s->airline()
                            ->name($m[1])
                            ->number($m[2])
                        ;
                    } elseif (preg_match("/^(.+)\s+(\d{2,4})\s*\(/", $flight, $m)) {
                        $s->airline()
                            ->name($m[1])
                            ->number($m[2])
                        ;
                    }
                }
                // depart data
                if ($tripPointNode = $this->getCellNodeByColumnName('Departs', $rowNode, $headingRowNode)) {
                    $tripPointData = join("\n", $http->FindNodes('.//text()[normalize-space(.)]', $tripPointNode));

                    if (!empty($tripPointData)) {
                        if (preg_match('/^(.+?)(?:\s+\(\s*([A-Z]{3})\))?\s+(\w+,\s*\w+\s*\d+.+)/ims', $tripPointData, $matches)) {
                            // Washington, DC-Dulles (IAD)   Tue, Feb 13   9:05 am
                            // Dallas-Ft. Worth, TX   Sun, Sep 27   5:40 pm
                            $s->departure()
                                ->name($matches[1]);

                            if (!empty($matches[2])) {
                                $s->departure()->code($matches[2]);
                            } else {
                                $s->departure()->noCode();
                            }
                            $s->departure()->date($this->normalizeDate($matches[3]));
                        } elseif (preg_match("/^(?<name>.{3,}?)\s*\(\s*(?<code>[A-Z]{3})\s*\)$/", $tripPointData, $matches)) {
                            // Seatle (SEA)
                            $s->departure()
                                ->name($matches['name'])
                                ->code($matches['code'])
                                ->noDate();
                        }
                    }
                }
                // arrive data
                if ($tripPointNode = $this->getCellNodeByColumnName('Arrives', $rowNode, $headingRowNode)) {
                    $tripPointData = join("\n", $http->FindNodes('.//text()[normalize-space(.)]', $tripPointNode));

                    if (!empty($tripPointData)) {
                        if (preg_match('/^(.+?)(?:\s+\(\s*([A-Z]{3})\))?\s+(\w+,\s*\w+\s*\d+.+)/ims', $tripPointData, $matches)) {
                            // Salt Lake City (SLC)    Tue, Feb 2    8:54 am
                            $s->arrival()
                                ->name($matches[1]);

                            if (!empty($matches[2])) {
                                $s->arrival()->code($matches[2]);
                            } else {
                                $s->arrival()->noCode();
                            }
                            $s->arrival()->date($this->normalizeDate($matches[3]));
                        } elseif (preg_match("/^(?<name>.{3,}?)\s*\(\s*(?<code>[A-Z]{3})\s*\)$/", $tripPointData, $matches)) {
                            // Seatle (SEA)
                            $s->arrival()
                                ->name($matches['name'])
                                ->code($matches['code'])
                                ->noDate();
                        }
                    }
                }
                // class & cabin
                if ($classNode = $this->getCellNodeByColumnName('Class', $rowNode, $headingRowNode)) {
                    if (count($classData = $http->FindNodes('.//text()[following-sibling::br or preceding-sibling::br]', $classNode)) == 2) {
                        $s->extra()
                            ->bookingCode($classData[0])
                            ->cabin(str_replace(['(', ')'], '', $classData[1]))
                        ;
                    }
                }
                // passengers
                if ($passengerNode = $this->getCellNodeByColumnName('Traveler', $rowNode, $headingRowNode)) {
                    foreach ($http->FindNodes('.//text()[string-length(normalize-space(.))>1]', $passengerNode) as $passengerName) {
                        $passengers[] = beautifulName($passengerName);
                    }
                }

                // seats
                if ($seatsNode = $this->getCellNodeByColumnName('Seat', $rowNode, $headingRowNode)) {
                    $seats = array_filter($http->FindNodes('./descendant::text()[normalize-space(.)]', $seatsNode, '/^(\d{1,5}[A-Z])(?:[★\*])?$/u'));

                    if (!empty($seats)) {
                        $s->extra()->seats($seats);
                    }
                }

                // confirmation
                $confirmationCode = $this->http->FindSingleNode("following-sibling::tr[normalize-space()][1]", $rowNode, true, "/{$this->opt($this->t('Confirmation code:'))}\s*([A-Z\d]{5,})$/");

                if ($confirmationCode) {
                    $s->airline()->confirmation($confirmationCode);
                }
            }

            // filter data
            $passengers = array_unique(array_filter($passengers, 'strlen'));

            if (!empty($passengers)) {
                $f->general()->travellers($passengers);
            }

            $totalChargeData = $http->FindSingleNode('//td[contains(string(), "Amount Due") and not(.//td) and contains(php:functionString("CleanXMLValue", string()), "Amount Due For Air Travel")]/following-sibling::td[1]', null, false);

            if ($totalChargeData) {
                // USD $119.00
                if (preg_match('/^((.+)\s+?)?(\S?)(\d+.\d+|\d+)/ims', $totalChargeData, $matches)) {
                    if (empty($matches[1])) {
                        if ($matches[3] == '$') {
                            $f->price()->currency('USD');
                        }
                    } else {
                        $f->price()->currency($matches[2]);
                    }
                    $f->price()->total(str_ireplace(",", "", $matches[4]));
                }
            }

            $xpathFragment1 = "//tr[ ./preceding-sibling::tr[{$this->contains($this->t('Summary of airfare charges'))}] and ./following-sibling::tr[{$this->contains($this->t('Total charges for air travel'))}] ]";

            // accountNumbers
            $accountNumbers = $this->http->FindNodes($xpathFragment1 . "/descendant::text()[{$this->starts($this->t('programNames'))}]", null, "/#\s*(\*[*\d]{5,}\d)$/");
            $accountNumbers = array_values(array_unique(array_filter($accountNumbers)));

            if (!empty($accountNumbers[0])) {
                $f->program()->accounts($accountNumbers, true);
            }

            // ticketNumbers
            $ticketNumbers = $this->http->FindNodes($xpathFragment1 . "/descendant::text()[{$this->contains($this->t('Ticket'))}]", null, "/{$this->opt($this->t('Ticket'))}\s*(\d{3}[-\s]*\d{4,})$/");
            $ticketNumbers = array_values(array_unique(array_filter($ticketNumbers)));

            if (!empty($ticketNumbers[0])) {
                $f->setTicketNumbers($ticketNumbers, false);
            }
        }
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
//        $body = $parser->getHTMLBody();
//        // this charset in <meta> tag messes up html
//        $body = str_ireplace("charset=utf-16", "", $body);
//        $this->http->SetEmailBody($body);

        $this->date = strtotime($parser->getDate());
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }
        $this->parseEmail($email, $parser->getHeaders());
        $email->setType('ConfirmationLetter' . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, 'Alaska Airline') !== false
            || preg_match('/[.@]alaskaair\.com/i', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true && strpos($headers['subject'], 'from Alaska Airlines') === false) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ($phrases as $phrase) {
                if (stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $condition1 = $this->http->XPath->query('//node()[contains(normalize-space(.),"Thank you for booking with Alaska") or contains(normalize-space(.),"Alaska Airlines. All rights reserved") or contains(.,"www.alaskaair.com") or contains(.,"@alaskaair.com")]')->length === 0;
        $condition2 = $this->http->XPath->query('//a[contains(@href,".alaskaair.com/")]')->length === 0;

        if ($condition1 && $condition2) {
            return false;
        }

        return $this->assignLang();
    }

    public function getCellNodeByColumnName($columnName, $rootNode, $headNode)
    {
        $previousCellsCount = $this->http->XPath->query('td[contains(., "' . $columnName . '")]/preceding-sibling::td', $headNode)->length + 1;

        return $this->http->XPath->query($q = "td[{$previousCellsCount}]", $rootNode)->item(0);
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function amount($s)
    {
        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $this->re("#([\d\,\.]+)#", $s)));
    }

    private function currency($s)
    {
        $sym = [
            '€'=> 'EUR',
            '$'=> 'USD',
            '£'=> 'GBP',
            '₹'=> 'INR',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }
        $s = $this->re("#([^\d\,\.]+)#", $s);

        foreach ($sym as $f=> $r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        return null;
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

    private function normalizeDate($date)
    {
        $year = date("Y", $this->date);
//        $this->logger->debug('$date = '.print_r( $date,true));
        $in = [
            // Fri , Sep 11 8:45 am
            "#^\s*(\w+)[,\s]+(\w+)\s+(\d+)\s*(\d{1,2}:\d{2}\s*[ap]m)\s*$#",
        ];
        $out = [
            "$1, $3 $2 $year, $4",
        ];
        $date = preg_replace($in, $out, $date);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        if (preg_match("#^(?<week>\w+), (?<date>\d+ \w+ .+)#u", $date, $m)) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($m['week'], $this->lang));
            $date = EmailDateHelper::parseDateUsingWeekDay($m['date'], $weeknum);
        } elseif (preg_match("#\b\d{4}\b#", $date)) {
            $date = strtotime($date);
        } else {
            $date = null;
        }

        return $date;
    }
}
