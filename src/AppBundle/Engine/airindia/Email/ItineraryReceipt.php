<?php

namespace AwardWallet\Engine\airindia\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Common\Flight;
use AwardWallet\Schema\Parser\Email\Email;

class ItineraryReceipt extends \TAccountChecker
{
    public $mailFiles = "airindia/it-1.eml, airindia/it-10969607.eml, airindia/it-11753511.eml, airindia/it-11971824.eml, airindia/it-12025846.eml, airindia/it-12071627.eml, airindia/it-12076278.eml, airindia/it-13204114.eml, airindia/it-13491331.eml, airindia/it-14379587.eml, airindia/it-1919175.eml, airindia/it-2.eml, airindia/it-3.eml, airindia/it-3015509.eml, airindia/it-4.eml, airindia/it-4794064.eml, airindia/it-4839358.eml, airindia/it-4839450.eml, airindia/it-5469516.eml, airindia/it-6331919.eml";
    //ru - is extra language. en is enough
    public $reBody = [
        'en' => ['ITINERARY RECEIPT', 'FLIGHT'],
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
            'FF:'                    => ['FF:', 'FF NO:'],
            'NAME:'                  => ['NAME:', 'NAME OF PASSENGER:'],
            'BASE FARE'              => ['BASE FARE', 'FARE: BASE'],
            'EQUIVALENT AMOUNT PAID' => ['EQUIVALENT AMOUNT PAID', 'EQUIV PAID'],
            'TAX/FEE/CHARGE'         => ['TAX/FEE/CHARGE', 'TAX/FEE/CHARGE TOTAL'],
            'TICKET TOTAL'           => ['TICKET TOTAL', 'TOTAL'],
        ],
    ];

    private $code;
    private $relativeDate;
    private static $bodies = [
        'sjair' => [
            '//img[@alt = "Sriwijaya Air" or contains(@src,"sriwijayaair.co.id")]',
            '//a[contains(@href,"sriwijayaair.co.id")]',
            'Sriwijaya Air',
        ],
        's7' => [
            '//img[@alt = "S7 Siberia Airlines" or contains(@src,"s7.ru")]',
            '//a[contains(@href,"s7.ru")]',
            'S7 Siberia Airlines',
            'S7 Airlines',
        ],
        'panorama' => [
            '//img[@alt = "Ukraine International Airlines" or contains(@src,"flyuia.com")]',
            '//a[contains(@href,"flyUIA.com") or contains(@href,"flyuia.com")]',
            'Ukraine International Airlines',
            'UIA Contact Centre',
            'www.flyUIA.com',
        ],
        'malaysia' => [
            '//img[@alt = "Malaysia Airlines" or contains(@src,"malaysiaairlines.com")]',
            '//a[contains(@href,"www.malaysiaairlines.com")]',
            'Malaysia Airlines',
            'www.malaysiaairlines.com',
        ],
        'airindia' => [
            '//img[@alt = "Air India" or contains(@src,"airindia.in")]',
            '//a[contains(@href,"www.airindia.in")]',
            'www.airindia.in',
            'Air India',
        ],
        'winair' => [
            '//img[@alt = "Winair" or contains(@src,"fly-winair.com")]',
            '//a[contains(@href,"fly-winair.com")]',
            'Winair',
        ],
        'uzair' => [
            '//img[@alt = "Uzbekistan Airways" or contains(@src,"uzairways.com")]',
            '//a[contains(@href,"uzairways.com")]',
            'Uzbekistan Airways',
        ],
    ];
    private static $headers = [
        'sjair' => [
            'from' => ['sriwijayaair.co.id'],
            'subj' => [
                'Itinerary Receipt',
            ],
        ],
        's7' => [
            'from' => ['@s7.ru'],
            'subj' => [
                'Itinerary Receipt',
            ],
        ],
        'panorama' => [
            'from' => ['@ps.kiev.ua', '@flyuia.com'],
            'subj' => [
                'Itinerary Receipt',
            ],
        ],
        'malaysia' => [
            'from' => ['@malaysiaairlines.com'],
            'subj' => [
                'Itinerary Receipt',
            ],
        ],
        'airindia' => [
            'from' => ['@airindia.in'],
            'subj' => [
                'Itinerary Receipt',
            ],
        ],
        'winair' => [
            'from' => ['@fly-winair.com'],
            'subj' => [
                'Itinerary Receipt',
            ],
        ],
        'uzair' => [
            'from' => ['@uzairways.com'],
            'subj' => [
                'Itinerary Receipt',
            ],
        ],
    ];
    private $airlineIataByCode = [
        'sjair'    => 'SJ',
        's7'       => 'S7',
        'panorama' => 'PS',
        'malaysia' => 'MH',
        'airindia' => 'AI',
        'winair'   => 'WM',
        'uzair'    => 'HY',
    ];
    private $text;

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $body = $parser->getPlainBody();
        if (empty($body) || strpos($body, '<pre') !== false) {
            $body = $this->clearHTML($parser->getHTMLBody());
        } else {
            $body = preg_replace("/^> /m", '', $body);
        }
        if (empty($body)) {
            $body = $this->clearHTML($this->http->Response['body']);
        }

        if (!$this->assignLang($body)) {
            $this->logger->debug('can\'t determine a language');

            return $email;
        }

        $this->code = $this->getProvider($parser);
        if ($this->code !== null) {
            $email->setProviderCode($this->code);
        } else {
            $this->logger->debug('can\'t determine providerCode');

            return $email;
        }
        $this->text = strstr($body, 'ITINERARY RECEIPT');

        $type = '';
        $f = $email->add()->flight();

        $this->relativeDate = strtotime($this->http->FindSingleNode("(//text()[{$this->contains($this->t('DATE OF ISSUE'))}])[1]",
            null, true, "#" . $this->t('DATE OF ISSUE') . "[\s:]*(\w+)#"));

        if ($this->http->FindSingleNode("(//tr/*[{$this->eq($this->t("FLIGHT"))}]/following-sibling::*[normalize-space()][1][{$this->eq($this->t("DEPARTURE"))}])[1]")) {
            $this->parseHtmlSegments($f);
            $type = 'Html';
        } else {
            $this->http->SetBody($body);
            $type = 'Text';
            $this->parseTextSegments($f);
        }
        $this->http->SetBody($body);
        $this->parseEmail($f);

        $a = explode('\\', __CLASS__);
        $class = end($a);
        $email->setType($class . $type . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        foreach (self::$headers as $code => $arr) {
            foreach ($arr['from'] as $f) {
                if (stripos($from, $f) !== false) {
                    return true;
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

        foreach (self::$headers as $code => $arr) {
            $byFrom = $bySubj = false;

            foreach ($arr['from'] as $f) {
                if (stripos($headers['from'], $f) !== false) {
                    $byFrom = true;
                }
            }

            foreach ($arr['subj'] as $subj) {
                if (stripos($headers['subject'], $subj) !== false) {
                    $bySubj = true;
                }
            }

            if ($byFrom && $bySubj) {
                $this->code = $code;

                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $this->clearHTML($this->http->Response['body']);
//        $this->http->SetBody($body);

        if (null !== $this->getProviderByBody()) {
            return $this->assignLang($body);
        }

        return false;
    }

    public static function getEmailProviders()
    {
        return array_keys(array_merge(self::$bodies, self::$headers));
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function getProvider(\PlancakeEmailParser $parser)
    {
        $this->detectEmailByHeaders(['from' => $parser->getCleanFrom(), 'subject' => $parser->getSubject()]);

        if (isset($this->code)) {
            return $this->code;
        }

        return $this->getProviderByBody();
    }

    private function getProviderByBody()
    {
        foreach (self::$bodies as $code => $criteria) {
            if (count($criteria) > 0) {
                foreach ($criteria as $search) {
                    if ((stripos($search, '//') === 0 && $this->http->XPath->query($search)->length > 0)
                        || (stripos($search, '//') === false && stripos($this->http->Response['body'],
                                $search) !== false)
                    ) {
                        return $code;
                    }
                }
            }
        }

        return null;
    }

    private function parseEmail(Flight $f)
    {
        $body = $this->http->Response['body'];

        if (!$this->airlineIataByCode[$this->code]) {
            $this->logger->debug('other format');

            return false;
        }

        $header = strstr($body, 'DEPARTURE', true);
        if (preg_match_all("/\s+RLOC[:\-\s]+([A-Z\d \-\\/]+)(?:\n|\s{2,})/", $header, $m)) {
            $confs = [];
            foreach ($m[1] as $pnrRow) {
                $pnrs = explode("/", $pnrRow);
                foreach ($pnrs as $pnr) {
                    if (preg_match("/^\s*([A-Z\d][A-Z]|[A-Z][A-Z\d])[\s\-]+([A-Z\d]{5,7})\s*$/", $pnr, $m) && !isset($confs[$m[2]])) {
                        $confs[$m[2]] = $m[1];
                    }
                }
            }

            foreach ($confs as $conf => $name) {
                $f->general()
                    ->confirmation($conf, $name);
            }
        } elseif (!preg_match("/\bRLOC\b/", $header) && isset($this->airlineIataByCode[$this->code]) && preg_match("/\s+({$this->airlineIataByCode[$this->code]}) - ([A-Z\d]{5,7})\s+/", $header, $m)) {
            $f->general()
                ->confirmation($m[2], $m[1]);
        }

        if (preg_match_all("#{$this->opt($this->t('NAME:'))}\s*(?:\d\)\. *)?([[:alpha:]][[:alpha:]\-]+(?: [[:alpha:]\-]+)*(?:\s*\\/\s*[[:alpha:]][[:alpha:]\-]+(?: [[:alpha:]\-]+)*)?)\n#", $header, $m)) {
            $f->general()
                ->travellers(preg_replace("/^\s*(.+?)\s*\\/\s*(.+?)(?: MR| MRS| MISS| MS)?\s*$/", '$2 $1', $m[1]));
        }

        if (preg_match_all("#{$this->opt($this->t('E-TICKET NUMBER:'))}\s*(\d[\d\\\/ ,\-]{8,})\s+#", $header, $m)) {
            $t = array_map('trim', explode(',', implode(',', $m[1])));
            $f->issued()
                ->tickets($t, false);
        }

        if (preg_match_all("#{$this->opt($this->t('FF:'))}\s*(.+?)\s*(?:ISSUED|DATE|\n)#", $header, $m)) {
            $f->program()
                ->accounts($m[1], false);
        }

        $this->parseSums($f);

        if (preg_match("#" . $this->opt($this->t('DATE OF ISSUE')) . "[\s:]*(\w+)\s+#", $header, $m)) {
            $f->general()->date(strtotime($m[1]));
        }

        return true;
    }

    private function parseHtmlSegments(Flight $f)
    {
        $xpath = "//tr[*[{$this->eq($this->t("FLIGHT"))}]/following-sibling::*[normalize-space()][1][{$this->eq($this->t("DEPARTURE"))}]]/following-sibling::*";
//        $this->logger->debug('$xpath = '.print_r( $xpath,true));
        $segments = $this->http->XPath->query($xpath);
        foreach ($segments as $root) {
            $date = null;

            $s = $f->addSegment();

            $col = implode("\n", $this->http->FindNodes("*[1]//text()[normalize-space()]", $root));
            if (preg_match("/^\s*(?:\w{5,7}\s+)?([A-Z\d][A-Z]|[A-Z][A-Z\d])\s+OPEN/", $col, $m)) {
                $f->removeSegment($s);
                continue;
            }
            if (preg_match("/^\s*(\w{5,7})\s+([A-Z\d][A-Z]|[A-Z][A-Z\d])\s+(\d{1,5})\b/", $col, $m)) {

                $date = $this->normalizeDate($m[1]);

                $s->airline()
                    ->name($m[2])
                    ->number($m[3]);
            }

            $s->departure()
                ->code($this->http->FindSingleNode("*[2]/descendant::text()[normalize-space()][1]", $root, true, "/^\s*([A-Z]{3})\s*-/"))
                ->name($this->http->FindSingleNode("*[2]/descendant::text()[normalize-space()][1]", $root, true, "/^\s*[A-Z]{3}\s*-\s*(.+)/"))
                ->terminal(trim(preg_replace("/\s*\bterminal\b\s*/i", ' ',
                    $this->http->FindSingleNode("*[2]/descendant::text()[normalize-space()][2]", $root, true, "/.*\bterminal\b.*/i"))), true, true);

            $time = preg_replace("/^\s*(\d{1,2})(\d{2})\s*$/", "$1:$2", $this->http->FindSingleNode("*[normalize-space()][3]", $root));
            if (!empty($date) && !empty($time)) {
                $s->departure()
                    ->date(strtotime($time, $date));
            }

            $s->arrival()
                ->code($this->http->FindSingleNode("*[4]/descendant::text()[normalize-space()][1]", $root, true, "/^\s*([A-Z]{3})\s*-/"))
                ->name($this->http->FindSingleNode("*[4]/descendant::text()[normalize-space()][1]", $root, true, "/^\s*[A-Z]{3}\s*-\s*(.+)/"))
                ->terminal(trim(preg_replace("/\s*\bterminal\b\s*/i", ' ',
                    $this->http->FindSingleNode("*[4]/descendant::text()[normalize-space()][2]", $root, true, "/.*\bterminal\b.*/i"))), true, true);

            $time = trim(implode(" ", $this->http->FindNodes("*[5]//text()[normalize-space()]", $root)));
            if (preg_match("/^\s*(\d{3,4})\s+(\w{5,7})\s*$/", $time, $m)) {
                $date = $this->normalizeDate($m[2]);
                $time = $m[1];
            }
            $time = preg_replace("/^\s*(\d{1,2})(\d{2})\s*$/", "$1:$2", $time);
            if (!empty($date) && !empty($time)) {
                $s->arrival()
                    ->date(strtotime($time, $date));
            } elseif (!empty($date) && empty($time)) {
                $s->arrival()
                    ->noDate();
            }

            $col = implode("\n", $this->http->FindNodes("*[6]//text()[normalize-space()]", $root));
            if (preg_match("/^\s*(?<BookingClass>[A-Z]{1,2})\s*\-(?<Status>\w+)\s+/", $col, $m)) {
                $s->extra()
                    ->status($m['Status'])
                    ->bookingCode($m['BookingClass']);
            }
        }

        return true;
    }

    private function parseTextSegments(Flight $f)
    {
        $body = $this->http->Response['body'];

        $start = strpos($body, $this->t('DEPARTURE'));
        $end = strpos($body, $this->t('RESTRICTIONS:'));
        $fs = substr($body, $start, $end - $start + 1);

        $pattern = '(?<DateFly>\d{2}\w+)\s+(?<AirlineName>[A-Z\d]{2})\s*(?<FlightNumber>\d+)\s+.*?\s+';
        $pattern .= '(?<DepCode>[A-Z]{3})\-\s*(?<DepName>.*?)\s+(?<DepTime>\d{4})\s+';
        $pattern .= '(?<ArrCode>[A-Z]{3})\s*\-?\s*(?<ArrName>.*?(?:\s+.*)?)\s*(?<BookingClass>[A-Z]{1,2})\s*\-(?<Status>\w+)\s+[\dA-Z]+\s*';

        $segments = $this->splitter("#(\d{2}\w+\s+[A-Z\d]{2}\s*\d+\s+[A-Z]{3}\s*\-?\s*.*?\d{4}\s+[A-Z]{3}\s*\-?).#s",
            $fs);

        foreach ($segments as $fs) {
            if (preg_match("#{$pattern}.+?" . $this->t('ARRIVAL') . "\:\s*(?<ArrTime>\d{4})#s", $fs,
                    $flight) || preg_match("#{$pattern}#", $fs, $flight)
            ) {
                $s = $f->addSegment();

                $date = $this->normalizeDate($flight['DateFly']);
                if (!empty($date)) {
                    $s->departure()
                        ->date(strtotime($flight['DepTime'], $date));
                }

                $s->extra()
                    ->status($flight['Status'])
                    ->bookingCode($flight['BookingClass']);

                $s->airline()
                    ->name($flight['AirlineName'])
                    ->number($flight['FlightNumber']);

                $s->departure()
                    ->code($flight['DepCode']);

                if (!empty($flight['DepName'])) {
                    if (preg_match("#(.+?)\s*(?:TERMINAL\s+(.+)|)$#", $flight['DepName'], $m)) {
                        $s->departure()
                            ->name($m[1]);

                        if (isset($m[2]) && !empty($m[2])) {
                            $s->departure()
                                ->terminal($m[2]);
                        }
                    }
                }

                if (!empty($flight['ArrTime'])) {
                    $arrTime = $flight['ArrTime'];

                }
                $s->arrival()
                    ->code($flight['ArrCode']);

                if (!empty($flight['ArrName'])) {
                    if (preg_match("#(?<name>.+?)\s*(?:TERMINAL\s+(?<terminal>\w+)|)\s*(?:(?<time>\d{4})(?:\s+(?<dateD>\d{1,2})(?<dateM>\w+))?|)\s*$#", $flight['ArrName'], $m)) {
                        $s->arrival()
                            ->name($m['name']);

                        if (isset($m['terminal']) && !empty($m['terminal'])) {
                            $s->arrival()
                                ->terminal($m['terminal']);
                        }

                        if (isset($m['time']) && !empty($m['time'])) {
                            $arrTime = $m['time'];
                        }

                        if (!empty($m['dateD']) && !empty($m['dateM']) && !empty($date)) {
                            $date = $m['dateD'] . ' ' . $m['dateM'] . ' ' . date("Y", strtotime($date));
                        }
                    }
                }

                if (!empty($arrTime)) {
                    if (!empty($date)) {
                        $s->arrival()
                            ->date(strtotime($arrTime, $date));
                    }
                } else {
                    $s->arrival()->noDate();
                }

                if (preg_match("#TERMINAL\s+(\w+)\s+TERMINAL\s+(\w+)#", $fs, $m)) {
                    $s->departure()->terminal($m[1]);
                    $s->arrival()->terminal($m[2]);
                }
            }
        }

        return true;
    }

    private function parseSums(Flight $f)
    {
        $body = $this->http->Response['body'];

        if (preg_match("#[\S\s]+\n\s*{$this->opt($this->t('TICKET TOTAL'))} +([A-Z]{3}) +([\d\.\,]+)(\s+\D*)?\s*\n#", $body, $m)) {
            $currency = $m[1];
            $f->price()
                ->total(PriceHelper::parse($m[2], $currency))
                ->currency($currency);
        }

        if (preg_match("#\n\s*{$this->opt($this->t('EQUIVALENT AMOUNT PAID'))} +([A-Z]{3}) +([\d\.\,]+)\s*\n#", $body, $m)) {
            $currency = $m[1];
            $f->price()
                ->cost(PriceHelper::parse($m[2], $currency))
                ->currency($currency);
        } elseif (preg_match("#\n\s*{$this->opt($this->t('BASE FARE'))} +([A-Z]{3}) +([\d\.\,]+)#", $body, $m)
            && (empty($currency) || $currency == $m[1])
        ) {
            $currency = $m[1];
            $f->price()
                ->cost(PriceHelper::parse($m[2], $currency))
                ->currency($currency);
        }

        if (preg_match("#\n\s*{$this->opt($this->t('TAX/FEE/CHARGE'))} +([A-Z]{3}) +([\d\.\,A-Z/]+)\s*\n#", $body, $m)
            && (empty($currency) || $currency == $m[1])
        ) {
            $currency = $m[1];
            $taxes = explode('/', $m[2]);
            foreach ($taxes as $tax) {
                if (preg_match("/^\s*(\d[\d.,]*)\s*([A-Z\d][A-Z]|[A-Z][A-Z\d])\s*$/", $tax, $mat)) {
                    $f->price()
                      ->fee($mat[2], PriceHelper::parse($mat[1], $currency));
                } elseif (count($taxes) === 1 && preg_match("/^\s*(\d[\d.,]*)\s*$/", $tax, $mat)) {
                    $f->price()
                      ->tax(PriceHelper::parse($mat[1], $currency));
                }
            }
        }

        $fees = ['OTHERS', 'EXTRA COVER INSURANCE'];

        if (preg_match_all("#\n\s*({$this->opt($fees)}) +([A-Z]{3}) +([\d\.\,]+)\s*\n#", $body, $m)
            && (empty($currency) || (count(array_unique($m[2])) && $currency == $m[2][0]))
        ) {
            $currency = $m[2][0];
            foreach ($m[1] as $i => $v) {
                $f->price()
                    ->fee($v, PriceHelper::parse($m[3][$i], $currency))
                    ->currency($currency);
            }
        }
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang($body)
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function clearHTML($body)
    {
        $NBSP = chr(194) . chr(160);
        $body = preg_replace("#<br[^>]*>#", "\n", $body);
//        $body = strip_tags(preg_replace("#(<td)#", '  $1', $body));
        $body = preg_replace("#(<tr)#", "\n".'$1', $body);
        $body = preg_replace("#(<pre)#", "\n".'$1', $body);
        $body = strip_tags($body);
        $ht = html_entity_decode($body);

        return str_replace($NBSP, ' ', $ht);
    }

    private function getTotalCurrency($node)
    {
        $node = str_replace("€", "EUR", $node);
        $node = str_replace("$", "USD", $node);
        $node = str_replace("£", "GBP", $node);
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node,
                $m) || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node,
                $m) || preg_match("#(?<c>\-*?)(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)
        ) {
            $m['t'] = preg_replace('/\s+/', '', $m['t']);            // 11 507.00	->	11507.00
            $m['t'] = preg_replace('/[,.](\d{3})/', '$1', $m['t']);    // 2,790		->	2790		or	4.100,00	->	4100,00
            $m['t'] = preg_replace('/^(.+),$/', '$1', $m['t']);    // 18800,		->	18800
            $m['t'] = preg_replace('/,(\d{2})$/', '.$1', $m['t']);    // 18800,00		->	18800.00
            $tot = $m['t'];
            $cur = $m['c'];
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }

    private function contains($field, $node = 'normalize-space(.)'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
                return 'contains(' . $node . ",'" . $s . "')";
            }, $field)) . ')';
    }

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
                return 'normalize-space(' . $node . ")='" . $s . "'";
            }, $field)) . ')';
    }

    private function starts($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
                return 'starts-with(normalize-space(' . $node . '),"' . $s . '")';
            }, $field)) . ')';
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function splitter($regular, $text)
    {
        $result = [];

        $array = preg_split($regular, $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        array_shift($array);

        for ($i = 0; $i < count($array) - 1; $i += 2) {
            $result[] = $array[$i] . $array[$i + 1];
        }

        return $result;
    }

    private function normalizeDate($date)
    {
//        $this->logger->debug('$date = '.print_r( $date,true));

        if (preg_match("/^\s*(\d{1,2})([A-Z]+)(\d{2})(,\s*.+?)?\s*$/", $date, $m)) {
            return strtotime($m[1] . ' ' . $m[2] . ' 20' . $m[3] . ($m[4] ?? ''));
        }
        if (preg_match("/^\s*(\d{1,2})([A-Z]+)(?:,\s*(.+?))?\s*$/", $date, $m)) {
            if (!empty($this->relativeDate)) {
                $year = date('Y', $this->relativeDate);
                $date2 = EmailDateHelper::parseDateRelative($m[1] . ' ' . $m[2] . ' ' . $year, $this->relativeDate, true);
                if (!empty($m[3])) {
                    $date2 = strtotime($m[3], $date2);
                }
                return $date2;
            } else {
                return null;
            }
        }

        return null;
    }
}
