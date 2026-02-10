<?php

namespace AwardWallet\Engine\curb\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class RideReceipt extends \TAccountChecker
{
    public $mailFiles = "curb/it-35486695.eml, curb/it-35497884.eml, curb/it-35517688.eml, curb/it-35519757.eml, curb/it-35528752.eml, curb/it-35529993.eml";

    public $reFrom = ["@gocurb.com"];
    public $reBody = [
        'en' => ['SERVICED BY', 'Pickup'],
    ];
    public $reSubject = [
        'Your Curb Ride Receipt',
    ];
    public $lang = '';
    public $pdfNamePattern = ".*pdf";
    public static $dict = [
        'en' => [
            'Pickup'    => 'Pickup',
            'Subtotal'  => 'Subtotal',
            'Your Ride' => ['Your Ride', 'YOUR RIDE'],
        ],
    ];
    private $keywordProv = 'Curb';
    private $date;

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getDate());
        $class = explode('\\', __CLASS__);

        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdfs) && count($pdfs) > 0) {
            foreach ($pdfs as $i => $pdf) {
                if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) !== null) {
                    if ($this->detectBody($text)) {
                        if (!$this->assignLang($text)) {
                            $this->logger->debug('can\'t determine a language (pdf-' . $i . ')');

                            continue;
                        }
                        $type = 'Pdf';
                        $email->setType(end($class) . ucfirst($type) . ucfirst($this->lang));

                        if ($this->parseEmailPdf($text, $email) === false) {
                            return null;
                        }
                    }
                }
            }
        }

        if (count($email->getItineraries()) === 0 && !$email->getIsJunk()) {
            if (!$this->assignLang($parser->getHTMLBody())) {
                $this->logger->debug('can\'t determine a language (html)');

                return $email;
            }

            if (!$this->parseEmail($email)) {
                return null;
            }
            $type = 'Html';
            $email->setType(end($class) . ucfirst($type) . ucfirst($this->lang));
        }

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href,'.gocurb.')] | //img[@alt='Curb' or contains(@src,'.gocurb.')]")->length > 0) {
            if ($this->detectBody($parser->getHTMLBody()) && $this->assignLang($parser->getHTMLBody())) {
                return true;
            }
        }
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if ($this->detectBody($text) && $this->assignLang($text)) {
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
                if (($fromProv && stripos($headers["subject"], $reSubject) !== false)
                    || strpos($headers["subject"], $this->keywordProv) !== false
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
        $formats = 2; // pdf | html
        $cnt = $formats * count(self::$dict);

        return $cnt;
    }

    private function parsePrices(\AwardWallet\Schema\Parser\Common\Transfer $r, string $text)
    {
        if (!empty($str = strstr($text, $this->t('Subtotal'), true))) {
            $feeText = $this->re("#\n[ ]*{$this->opt($this->t('Rate #'))}[^\n]+\n+(.+)#s", $str);
        }

        if (empty($feeText)) {
            $feeText = $this->re("#^[ ]*{$this->t('Fare')}[ ]{2,}[^\n]+\n+(.+)#sm", $str);
        }

        if (empty($feeText)) {
            return false;
        }

        $feeArr = array_filter(array_map("trim", explode("\n", $feeText)));

        foreach ($feeArr as $fee) {
            if (preg_match("#(.+?)[ ]{3,}(.+)#", $fee, $m)) {
                $sum = $this->getTotalCurrency($m[2]);

                if (!empty($sum['Total'])) {
                    $r->price()
                        ->fee($m[1], $sum['Total']);
                }
            }
        }
        $feeCurb = $this->getTotalCurrency($this->re("#\n[ ]*{$this->t('Curb Credits')}[ ]{2,}(.+)#", $text));

        if (!empty($feeCurb['Total'])) {
            $r->price()
                ->fee($this->t('Curb Credits'), $feeCurb['Total']);
        }

        $total = $this->getTotalCurrency($this->re("#\n[ ]*{$this->t('Total')}[ ]{2,}(.+)#", $text));

        if (!empty($total['Total'])) {
            $r->price()
                ->total($total['Total'])
                ->currency($total['Currency']);
        }

        $cost = $this->getTotalCurrency($this->re("#\n[ ]*{$this->t('Fare')}[ ]{2,}(.+)#", $text));

        if (!empty($cost['Total'])) {
            $r->price()
                ->cost($cost['Total']);
        }

        return true;
    }

    private function parseEmailPdf($textPDF, Email $email): bool
    {
        $charged = $this->normalizeDate($this->re("#{$this->t('Charged on')}[ ]+(\S.+?)(?:[ ]{3,}|\n)#", $textPDF));

        if ($charged) {
            $this->date = $charged;
        }

        $date = $this->normalizeDate($this->re("#{$this->t('RECEIPT')}\s+(.+)#", $textPDF));

        $r = $email->add()->transfer();

        if ($this->parsePrices($r, $textPDF) == false) {
            $email->removeItinerary($r);
            $this->logger->debug('go parse by body');

            return true;
        }

        $traveller = $this->re("#\n(.+?)[ ]{3,}\S+?@\S+[ ]{3,}Confirmation \#\s*[\w\-]+\s*$#", $textPDF);

        if (!empty($traveller)) {
            $r->general()
                ->traveller($traveller, true);
        }
        $confNo = $this->re("#{$this->opt($this->t('Confirmation #'))}\s*([A-Z\d]+)#", $textPDF);
        $r->general()
            ->confirmation($confNo, 'Confirmation #', true)
            ->date($date);
        $tripNo = $this->re("#{$this->opt($this->t('Trip #'))}\s*(\d{3,})#", $textPDF);

        if ($tripNo !== null) {
            $r->general()->confirmation($tripNo, 'Trip #');
        }

        $s = $r->addSegment();

        if (preg_match_all("#{$this->opt($this->t('Rate #'))}\s*\d+[^\n]+?\s*\-\s*(.+?)[ ]{2,}#", $textPDF, $m,
            PREG_SET_ORDER)) {
            if (count($m) === 1) {
                $s->setMiles($m[0][1]);
            }
        }
        $vehNum = $this->re("#{$this->opt($this->t('Vehicle #'))}\s*([\w\-]+)#", $textPDF);

        if (empty($vehNum)) {
            $str = trim($this->t('Vehicle #'), '# ');
            $vehNum = $this->re("#{$this->opt($str)}\s*[^\n]+\s+\#\s*([\w\-]+)\n#", $textPDF);
        }
        $s->setCarType($vehNum);

        if (preg_match("#{$this->opt($this->t('Pickup'))}\s*(.+)\s+{$this->opt($this->t('Dropoff'))}\s*(.+)\s+{$this->t('Serviced by')}#s",
            $textPDF, $m)) {
            if (preg_match("#(.*)\s*{$this->t('at')}\s*(\d+:.+)#", $this->nice($m[1]), $v)) {
                $s->departure()
                    ->date(strtotime($v[2], $date));

                if (!empty($v[1])) {
                    $locationDep = $v[1];
                } else {
                    //try search address in body. otherwise isJunk
                    $pu = $this->http->FindSingleNode("(//text()[{$this->contains($confNo)}])[1]/ancestor::table[{$this->contains($this->t('Pickup'))}][1]/descendant::text()[{$this->eq($this->t('Pickup'))}]/ancestor::td[1]/following-sibling::td");

                    if (preg_match("#(.*)\s*{$this->t('at')}\s*(\d+:.+)#", $pu, $v) && !empty($v[1])) {
                        $locationDep = $v[1];
                    } else {
                        $email->setIsJunk(true);
                        $email->removeItinerary($r);

                        return false;
                    }
                }

                if ($this->isAddress($locationDep)) {
                    $s->departure()->address($locationDep);
                } else {
                    $s->departure()->name($locationDep);
                }
            }

            if (preg_match("#(.*)\s*{$this->t('at')}\s*(\d+:.+)#", $this->nice($m[2]), $v)) {
                $s->arrival()
                    ->date(strtotime($v[2], $date));

                if (!empty($v[1])) {
                    $locationArr = $v[1];
                } else {
                    //try search address in body. otherwise isJunk
                    $pu = $this->http->FindSingleNode("(//text()[{$this->contains($confNo)}])[1]/ancestor::table[{$this->contains($this->t('Dropoff'))}][1]/descendant::text()[{$this->eq($this->t('Dropoff'))}]/ancestor::td[1]/following-sibling::td");

                    if (preg_match("#(.*)\s*{$this->t('at')}\s*(\d+:.+)#", $pu, $v) && !empty($v[1])) {
                        $locationArr = $v[1];
                    } else {
                        $email->setIsJunk(true);
                        $email->removeItinerary($r);

                        return false;
                    }
                }

                if ($this->isAddress($locationArr)) {
                    $s->arrival()->address($locationArr);
                } else {
                    $s->arrival()->name($locationArr);
                }
            }
        }

        return true;
    }

    private function parseEmail(Email $email): bool
    {
        $r = $email->add()->transfer();
        $date = $this->normalizeDate($this->http->FindSingleNode("//text()[{$this->eq($this->t('Your Ride'))}]/ancestor::td[1]/following-sibling::td[normalize-space()!=''][1]"));
        $r->general()
            ->date($date)
            ->confirmation(
                $this->http->FindSingleNode("(//text()[{$this->starts($this->t('Confirmation #'))}])[1]", null, false,
                    "#{$this->opt($this->t('Confirmation #'))}\s*([\w\-]+)#"),
                $this->t('Confirmation #'),
                true
            )
            ->confirmation(
                $this->http->FindSingleNode("(//text()[{$this->starts($this->t('Trip #'))}])[1]", null, false,
                    "#{$this->opt($this->t('Trip #'))}\s*([\w\-]+)#"),
                $this->t('Trip #')
            )
            ->traveller(
                $this->http->FindSingleNode("//text()[{$this->starts($this->t('Hey '))}]", null, false,
                    "#{$this->opt($this->t('Hey '))}\s*(.+?),#"),
                false
            );

        $textSum = [];
        $nodes = $this->http->XPath->query("//text()[{$this->eq($this->t('Fare'))}]/ancestor::table[1]/descendant::tr[1]/../tr[normalize-space()!='']");

        foreach ($nodes as $root) {
            $textSum[] = implode("    ", $this->http->FindNodes("./td", $root));
        }

        if ($this->parsePrices($r, implode("\n", $textSum)) == false) {
            $this->logger->debug('other format');

            return false;
        }

        $s = $r->addSegment();
        $nodes = $this->http->FindNodes("//text()[{$this->starts($this->t('Rate #'))}]", null,
            "#{$this->opt($this->t('Rate #'))}\s*\d+[^\n]+?\s*\-\s*(.+)#");

        if (count($nodes) === 1) {
            $s->setMiles($nodes[0]);
        }
        $vehNum = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Vehicle #'))}]", null, false,
            "#{$this->opt($this->t('Vehicle #'))}\s*([\w\-]+)#");
        $s->setCarType($vehNum);

        $pu = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Pickup'))}]/ancestor::td[1]/following-sibling::td");

        if (preg_match("#(.*)\s*{$this->t('at')}\s*(\d+:.+)#", $pu, $v) && !empty($v[1])) {
            $locationDep = $v[1];
            $s->departure()->date(strtotime($v[2], $date));
        } else {
            $email->setIsJunk(true);
            $email->removeItinerary($r);

            return false;
        }

        if ($this->isAddress($locationDep)) {
            $s->departure()->address($locationDep);
        } else {
            $s->departure()->name($locationDep);
        }

        $do = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Dropoff'))}]/ancestor::td[1]/following-sibling::td");

        if (preg_match("#(.*)\s*{$this->t('at')}\s*(\d+:.+)#", $do, $v) && !empty($v[1])) {
            $locationArr = $v[1];
            $s->arrival()->date(strtotime($v[2], $date));
        } else {
            $email->setIsJunk(true);
            $email->removeItinerary($r);

            return false;
        }

        if ($this->isAddress($locationArr)) {
            $s->arrival()->address($locationArr);
        } else {
            $s->arrival()->name($locationArr);
        }

        return true;
    }

    private function isAddress(string $s): bool
    {
        return preg_match('/(,.*\d|\d.*,)/s', $s) > 0;
    }

    private function normalizeDate($date)
    {
        $year = date('Y', $this->date);
        $in = [
            //March 26 at 12:26 PM
            '#^(\w+)\s+(\d+)\s+at\s*(\d+:\d+(?:\s*[ap]m)?)$#iu',
            //03/26/19
            '#^\s*(\d+)\/(\d+)\/(\d{2})\/\s*$#iu',
        ];
        $out = [
            '$2 $1 ' . $year . ' $3',
            '$3-$1-$2',
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

    private function detectBody($body)
    {
        if (isset($this->reBody) && stripos($body, $this->keywordProv) !== false) {
            foreach ($this->reBody as $lang => $reBody) {
                if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function assignLang($body)
    {
        foreach (self::$dict as $lang => $words) {
            if (isset($words["Pickup"], $words["Subtotal"])) {
                if (stripos($body, $words["Pickup"]) !== false && stripos($body, $words["Subtotal"]) !== false) {
                    $this->lang = $lang;

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

    private function getTotalCurrency($node)
    {
        $node = str_replace("€", "EUR", $node);
        $node = str_replace("$", "USD", $node);
        $node = str_replace("£", "GBP", $node);
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)
            || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m)
        ) {
            $cur = $m['c'];
            $tot = PriceHelper::cost($m['t']);
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s, '#'));
        }, $field)) . ')';
    }

    private function nice($str)
    {
        return trim(preg_replace("#\s+#", ' ', $str));
    }
}
