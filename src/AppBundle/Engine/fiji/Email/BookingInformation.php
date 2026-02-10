<?php

namespace AwardWallet\Engine\fiji\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class BookingInformation extends \TAccountChecker
{
    public $mailFiles = "fiji/it-41843984.eml, fiji/it-42441477.eml";

    public $reFrom = ["fijiairways.com"];
    public $reBody = [
        'en' => ['Fiji Airways carry-on Baggage Allowance', 'FIJI AIRWAYS CARRY-ON BAGGAGE ALLOWANCE'],
    ];
    public $reSubject = [
        'Fiji Airways Booking Information',
    ];
    public $flightsArray = [];
    public $pdfNamePattern = 'Eticket.*\.pdf';

    public $lang = '';
    public static $dict = [
        'en' => [
            'Depart'            => 'Depart',
            'Details'           => 'Details',
            'Booking REFERENCE' => ['Booking REFERENCE', 'BOOKING REFERENCE'],
            'Special Requests'  => ['Special Requests', 'SPECIAL REQUESTS'],
        ],
    ];
    private $keywordProv = 'Fiji Airways';

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);
        $text = '';

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));
        }

        if (!$this->assignLang($text)) {
            $this->logger->debug('can\'t determine a language');

            return $email;
        }

        if ($this->http->XPath->query("//text()[{$this->contains($this->t('Depart'))}]")->length === 0) {
            $this->parseEmailPDF($email, $text);
        } else {
            $this->parseEmail($email);
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function parseEmailPDF(Email $email, string $text)
    {
        $f = $email->add()->flight();

        $travellers = [];
        $confs = [];

        if (preg_match_all("/^[ ]{1,5}BOOKING REFERENCE:\s+PASSENGER DETAILS:\s+E-TICKET NUMBER:\n*((?:.+\n){1,5})^[ ]+Date issued:/mu", $text, $m)) {
            foreach ($m[1] as $headText) {
                if (preg_match("/^[ ]{1,10}(?<conf>[\dA-Z]{6})\s+(?<pax>[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])\s+(?<ticketNumber>\d{12,})\s*$/", $headText, $m)) {
                    $pax = preg_replace("/^(?:MRS|MR|MS)/", "", $m['pax']);
                    $f->addTicketNumber($m['ticketNumber'], false, $pax);
                    $travellers[] = $pax;
                    $confs[] = $m['conf'];
                }
            }
        }

        if (preg_match("/PRICE BREAKDOWN\nBase Fare\s*Taxes & Fees\s*Amount Paid\n+(?<cost>[\d\.\,]+)\s*(?<currency>[A-Z]{3})\s*(?<tax>[\d\.\,]+)\D+(?<total>[\d\.\,]+)\D*\n/", $text, $m)) {
            $f->price()
                ->tax(PriceHelper::parse($m['tax'], $m['currency']))
                ->cost(PriceHelper::parse($m['cost'], $m['currency']))
                ->total(PriceHelper::parse($m['total'], $m['currency']))
                ->currency($m['currency']);
        }

        if (preg_match_all("/^\s*ITINERARY DETAILS\n*(?<flights>\s*Depart\s*Arrive\s*Details\n*(?:.+\n){2,7})\n/mu", $text, $m)) {
            foreach ($m['flights'] as $flightText) {
                $s = $f->addSegment();

                $flightTable = $this->splitCols($flightText);

                if (preg_match("/Depart\n*(?<depName>.+)\s*\((?<depCode>[A-Z]{3})\)\n*(?<depDate>.+\d{4})\n*(?<depTime>\d+\:\d+)\s*\(/", $flightTable[0], $m)) {
                    if (empty($this->flightsArray)) {
                        $this->flightsArray[] = $m['depCode'] . '-' . $m['depDate'];
                    } elseif (in_array($m['depCode'] . '-' . $m['depDate'], $this->flightsArray) !== false) {
                        $f->removeSegment($s);
                    }

                    $s->departure()
                        ->name($m['depName'])
                        ->code($m['depCode'])
                        ->date(strtotime($m['depDate'] . ', ' . $m['depTime']));
                }

                if (preg_match("/Arrive\n*(?<arrName>.+)\s*\((?<arrCode>[A-Z]{3})\)\n*(?<arrDate>.+\d{4})\n*(?<arrTime>\d+\:\d+)\s*\(/", $flightTable[1], $m)) {
                    $s->arrival()
                        ->name($m['arrName'])
                        ->code($m['arrCode'])
                        ->date(strtotime($m['arrDate'] . ', ' . $m['arrTime']));
                }

                if (preg_match("/Details\n*(?<aName>(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]))\s*(?<fNumber>\d{1,4})\s*(?<status>(?:Confirmed))/", $flightTable[2], $m)) {
                    $s->airline()
                        ->name($m['aName'])
                        ->number($m['fNumber']);

                    $s->setStatus($m['status']);
                }
            }
        }

        foreach (array_unique($confs) as $conf) {
            $f->general()
                ->confirmation($conf)
                ->travellers(array_unique($travellers))
                ->date(strtotime($this->re("/Date issued:\s*(.+\d{4})/", $text)));
        }
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//img[contains(@alt,'Fiji Airways') or contains(@src,'.fijiairways.com')] | //a[contains(@href,'.fijiairways.com')]")->length > 0) {
            foreach ($this->reBody as $reBody) {
                if ($this->http->XPath->query("//*[{$this->contains($reBody)}]")->length > 0) {
                    return true;
                }
            }
        }

        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (strpos($text, "www.fijiairways.com") !== false
                && strpos($text, 'This document provides important information related to your purchased fare') !== false
                && strpos($text, 'ITINERARY DETAILS') !== false
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

    public function detectEmailByHeaders(array $headers)
    {
        $fromProv = true;

        if (!isset($headers['from']) || self::detectEmailFromProvider($headers['from']) === false) {
            $fromProv = false;
        }

        if (isset($headers["subject"])) {
            foreach ($this->reSubject as $reSubject) {
                if (($fromProv || stripos($headers["subject"], $this->keywordProv) !== false)
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
        return count(self::$dict);
    }

    private function parseEmail(Email $email)
    {
        $r = $email->add()->flight();

        $seats = $accounts = $passengers = [];
        $text = implode("\n",
            $this->http->FindNodes("//text()[{$this->eq($this->t('Special Requests'))}]/ancestor::tr[1]/following-sibling::tr/descendant::text()[normalize-space()!='']"));
        $str = $this->strstrArr($text, $this->t('Fiji Airways carry-on Baggage Allowance'), true);

        if (!empty($str)) {
            $text = $str;
        }
        $flights = $this->splitter("#\n([^\n]+\([A-Z]{3}\)\n[^\n]+\([A-Z]{3}\)\n)#", "CtrlStr\n" . $text);

        foreach ($flights as $flight) {
            if (preg_match("#^[^\n]+\(([A-Z]{3})\)\n[^\n]+\(([A-Z]{3})\)\n#", $flight, $m)) {
                $dep = $m[1];
                $arr = $m[2];

                if (preg_match_all("#{$this->t('Seat')}:\s*(\d+[A-z])#", $flight, $m)) {
                    $seats[$dep . '-' . $arr] = $m[1];
                }

                if (preg_match_all("#{$this->opt($this->t('Membership'))}:\s*([\w\-]+)#", $flight, $m)) {
                    $accounts = array_merge($accounts, $m[1]);
                }

                if (preg_match_all("#(.+)\n.*{$this->t('Seat')}:\s*(\d+[A-z])#", $flight, $m)) {
                    $passengers = array_merge($passengers, $m[1]);
                } elseif (preg_match_all("#(.+)\n.*No special service requests#", $flight, $m)) {
                    $passengers = array_merge($passengers, $m[1]);
                }
            }
        }
        $r->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking REFERENCE'))}]/ following::text()[normalize-space()!=''][1]"));
        // passengers
        $passengers = array_unique($passengers);
        $r->general()
            ->travellers($passengers, true);
        // accounts
        $accounts = array_unique($accounts);

        if (!empty($accounts)) {
            $r->program()
                ->accounts($accounts, preg_match("#XXXX#", $accounts[0]) === 0);
        }

        $ruleTime = "contains(translate(normalize-space(.),'0123456789','dddddddddd--'),'d:dd')";
        $xpath = "//text()[{$this->eq($this->t('Depart'))}]/ancestor::tr[1][{$this->contains($this->t('Arrive'))}]/following-sibling::tr[{$ruleTime}]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $s = $r->addSegment();
            $node = $this->http->FindSingleNode("./preceding-sibling::tr[1]/td[1]", $root);

            if (preg_match("#^(.+)\s*\(([A-Z]{3})\)$#", $node, $m)) {
                $s->departure()
                    ->name($m[1])
                    ->code($m[2]);
            }
            $node = $this->http->FindSingleNode("./preceding-sibling::tr[1]/td[2]", $root);

            if (preg_match("#^(.+)\s*\(([A-Z]{3})\)$#", $node, $m)) {
                $s->arrival()
                    ->name($m[1])
                    ->code($m[2]);
            }

            if ($s->getDepCode() && $s->getArrCode() && isset($seats[$s->getDepCode() . '-' . $s->getArrCode()])) {
                $s->extra()->seats($seats[$s->getDepCode() . '-' . $s->getArrCode()]);
            }
            $node = $this->http->FindSingleNode("./preceding-sibling::tr[1]/td[3]", $root);

            if (preg_match("#^([A-Z\d][A-Z]|[A-Z][A-Z\d])\s*(\d+)$#", $node, $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2]);
            }
            $date = strtotime($this->http->FindSingleNode("./td[1]/descendant::text()[normalize-space()!=''][1]",
                $root));
            $time = $this->http->FindSingleNode("./td[1]/descendant::text()[normalize-space()!=''][2]", $root, false,
                "#^(\d+:\d+)\s*\(#");
            $terminal = $this->http->FindSingleNode("./td[1]/descendant::text()[normalize-space()!=''][3]", $root,
                false, "#{$this->opt($this->t('Terminal'))}\s+(.+)#");
            $s->departure()
                ->date(strtotime($time, $date))
                ->terminal($terminal, false, true);

            $date = strtotime($this->http->FindSingleNode("./td[2]/descendant::text()[normalize-space()!=''][1]",
                $root));
            $time = $this->http->FindSingleNode("./td[2]/descendant::text()[normalize-space()!=''][2]", $root, false,
                "#^(\d+:\d+)\s*\(#");
            $terminal = $this->http->FindSingleNode("./td[2]/descendant::text()[normalize-space()!=''][3]", $root,
                false, "#{$this->opt($this->t('Terminal'))}\s+(.+)#");
            $s->arrival()
                ->date(strtotime($time, $date))
                ->terminal($terminal, false, true);

            $s->extra()
                ->status($this->http->FindSingleNode("./td[3]/descendant::text()[normalize-space()!=''][1]", $root))
                ->cabin($this->http->FindSingleNode("./td[3]/descendant::text()[normalize-space()!=''][2]", $root))
                ->aircraft($this->http->FindSingleNode("./td[3]/descendant::text()[normalize-space()!=''][3]", $root));
        }

        return true;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang(string $text)
    {
        if (!empty($text)) {
            foreach (self::$dict as $lang => $words) {
                if (isset($words['Depart'], $words['Details'])) {
                    if (stripos($text, $words['Depart']) !== false
                        && stripos($text, $words['Details']) !== false) {
                        $this->lang = $lang;

                        return true;
                    }
                }
            }
        }

        foreach (self::$dict as $lang => $words) {
            if (isset($words['Depart'], $words['Details'])) {
                if ($this->http->XPath->query("//*[{$this->contains($words['Depart'])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($words['Details'])}]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function rowColsPos(?string $row): array
    {
        $pos = [];
        $head = preg_split('/\s{2,}/', $row, -1, PREG_SPLIT_NO_EMPTY);
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }

    private function splitCols(?string $text, ?array $pos = null): array
    {
        $cols = [];

        if ($text === null) {
            return $cols;
        }
        $rows = explode("\n", $text);

        if ($pos === null || count($pos) === 0) {
            $pos = $this->rowColsPos($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k => $p) {
                $cols[$k][] = rtrim(mb_substr($row, $p, null, 'UTF-8'));
                $row = mb_substr($row, 0, $p, 'UTF-8');
            }
        }
        ksort($cols);

        foreach ($cols as &$col) {
            $col = implode("\n", $col);
        }

        return $cols;
    }
}
