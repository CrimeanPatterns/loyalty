<?php

namespace AwardWallet\Engine\airtransat\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ElDocumentPDF extends \TAccountChecker
{
    public $mailFiles = "airtransat/it-454480494.eml, airtransat/it-468973130.eml, airtransat/it-587076125.eml, airtransat/it-6133330.eml, airtransat/it-6133332.eml";

    public $reFrom = "";
    public $reBody = [
        'en' => ['Passenger Information', 'Itinerary'],
    ];
    public $reSubject = [
        '',
    ];
    public $lang = '';
    public $type = '';
    public $pdf;
    public static $dict = [
        'en' => [
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName(".*pdf");

        if (isset($pdfs) && count($pdfs) > 0) {
            $this->pdf = clone $this->http;
            $html = '';

            foreach ($pdfs as $pdf) {
                if (($html = \PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_COMPLEX)) !== null) {
                    $NBSP = chr(194) . chr(160);
                    $html = str_replace($NBSP, ' ', html_entity_decode($html));
                    $this->pdf->SetBody($html);
                    $body = text(\PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_SIMPLE));
                    $body = str_replace("&nbsp;", " ", $body);
                    $this->AssignLang($body);

                    if ($this->pdf->XPath->query("//text()[contains(.,'Outbound/') or contains(.,'Inbound/')]/ancestor::p[1]")->length > 0) {
                        $this->parseEmail_A($body, $email);

                        if (strpos($this->type, 'A') === false) {
                            $this->type .= 'A';
                        }
                    } elseif (preg_match("/\-\s*Passenger\s*Information/iu", $body)/*$this->pdf->XPath->query("//*[contains(.,'Operated')]/ancestor::p[1]/preceding::p[1]")->length > 0*/) {
                        $this->parseEmail_C($body, $email);

                        if (strpos($this->type, 'С') === false) {
                            $this->type .= 'С';
                        }
                    } else {
                        $this->parseEmail_B($body, $email);

                        if (strpos($this->type, 'B') === false) {
                            $this->type .= 'B';
                        }
                    }
                } else {
                    return false;
                }
            }
        } else {
            return false;
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang) . $this->type);

        return $email;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdf = $parser->searchAttachmentByName('.*pdf');

        if (isset($pdf[0])) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf[0]));

            return $this->AssignLang($text);
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($this->reSubject)) {
            foreach ($this->reSubject as $reSubject) {
                if (stripos($headers["subject"], $reSubject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function findСutSection($input, $searchStart, $searchFinish)
    {
        $inputResult = null;
        $left = mb_strstr($input, $searchStart);

        if (is_array($searchFinish)) {
            $i = 0;

            while (empty($inputResult)) {
                if (isset($searchFinish[$i])) {
                    $inputResult = mb_strstr($left, $searchFinish[$i], true);
                } else {
                    return false;
                }
                $i++;
            }
        } elseif (!empty($searchFinish)) {
            $inputResult = mb_strstr($left, $searchFinish, true);
        } else {
            $inputResult = $left;
        }

        return mb_substr($inputResult, mb_strlen($searchStart));
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->reFrom) !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    public function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = \AwardWallet\Engine\MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    public function normalizeTravellers($travellers)
    {
        return preg_replace("/(?:MRS|MS|MR)$/i", "", $travellers);
    }

    private function parseEmail_A($plainText, Email $email)
    {
        $textTop = $this->findСutSection($plainText, $this->t('Passenger Information /'), $this->t('Itinerary /'));

        $f = $email->add()->flight();

        $f->general()
            ->confirmation($this->re("#Booking Reference.+?\n\s*([A-Z\d]+)#", $textTop))
            ->date(strtotime($this->normalizeDate($this->re("#Issue Date.+?\n\s*(.+)#i", $textTop))));

        if (preg_match_all("#^\d\.\s+(.*)#m", $textTop, $m)) {
            $f->general()
                ->travellers($this->normalizeTravellers($m[1]));
        }

        $xpath = "//text()[contains(.,'Outbound/') or contains(.,'Inbound/')]/ancestor::p[1]";
        $nodes = $this->pdf->XPath->query($xpath);

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $node = implode(" ", $this->pdf->FindNodes("./following-sibling::p[string-length(normalize-space(.))>2][1]//text()[string-length(normalize-space(.))>1]", $root));

            if (preg_match("#(.+?)\s*(?:(T-\d+)|$)#s", $node, $m)) {
                $s->departure()
                    ->name($m[1]);

                if (isset($m[2]) && !empty($m[2])) {
                    $s->departure()
                        ->terminal($m[2]);
                }
            }

            $node = implode(" ", $this->pdf->FindNodes("./following-sibling::p[string-length(normalize-space(.))>2][2]//text()[string-length(normalize-space(.))>1]", $root));

            if (preg_match("#(.+?)\s*(?:(T-\d+)|$)#s", $node, $m)) {
                $s->arrival()
                    ->name($m[1]);

                if (isset($m[2]) && !empty($m[2])) {
                    $s->arrival()
                        ->terminal($m[2]);
                }
            }

            $node = $this->pdf->FindSingleNode("./following-sibling::p[string-length(normalize-space(.))>2][3]", $root);

            if (preg_match("#([A-Z\d]{2})\s*(\d+)#", $node, $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2])
                    ->operator($this->pdf->FindSingleNode("./following-sibling::p[string-length(normalize-space(.))>2][position()<11][contains(.,'Operated by')]", $root, true, "#Operated by\s+(.+)#"));
            }

            $date = strtotime($this->normalizeDate($this->pdf->FindSingleNode("./following-sibling::p[string-length(normalize-space(.))>2][4]", $root)));

            $s->departure()
                ->noCode()
                ->date(strtotime($this->pdf->FindSingleNode("./following-sibling::p[string-length(normalize-space(.))>2][7]", $root), $date));

            $s->arrival()
                ->noCode()
                ->date(strtotime($this->pdf->FindSingleNode("./following-sibling::p[string-length(normalize-space(.))>2][8]", $root), $date));

            $s->extra()
                ->cabin($this->pdf->FindSingleNode("./following-sibling::p[string-length(normalize-space(.))>2][5]", $root));
        }
    }

    private function parseEmail_B($plainText, Email $email)
    {
        $textTop = $this->findСutSection($plainText, 'Passenger Information /', 'Itinerary /');

        $f = $email->add()->flight();

        $f->general()
            ->confirmation($this->re("#File number.+?\n\s*([A-Z\d]+)#", $textTop))
            ->date(strtotime($this->normalizeDate($this->re("#Issue Date.+?\n\s*(.+)#i", $textTop))));

        if (preg_match_all("#^\d\.\s+(.*)#m", $textTop, $m)) {
            $f->general()
                ->travellers($this->normalizeTravellers($m[1]));
        }

        $xpath = "//text()[translate(normalize-space(.),'0123456789','')=':']/ancestor::p[1]/following-sibling::p[1][translate(normalize-space(.),'0123456789','')=':']/preceding-sibling::p[string-length(normalize-space(.))>2][6]";
        $nodes = $this->pdf->XPath->query($xpath);

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $node = implode(" ", $this->pdf->FindNodes(".", $root));

            if (preg_match("#(.+?)\s*(?:(T-\d+)|$)#s", $node, $m)) {
                $s->departure()
                    ->name($m[1]);

                if (isset($m[2]) && !empty($m[2])) {
                    $s->departure()
                        ->terminal($m[2]);
                }
            }

            $node = implode(" ", $this->pdf->FindNodes("./following-sibling::p[string-length(normalize-space(.))>2][1]//text()[string-length(normalize-space(.))>1]", $root));

            if (preg_match("#(.+?)\s*(?:(T-\d+)|$)#s", $node, $m)) {
                $s->arrival()
                    ->name($m[1]);

                if (isset($m[2]) && !empty($m[2])) {
                    $s->arrival()
                        ->terminal($m[2]);
                }
            }

            $node = $this->pdf->FindSingleNode("./following-sibling::p[string-length(normalize-space(.))>2][2]", $root);

            if (preg_match("#([A-Z\d]{2})\s*(\d+)#", $node, $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2])
                    ->operator($this->pdf->FindSingleNode("./following-sibling::p[string-length(normalize-space(.))>2][position()<11][contains(.,'Operated by')]", $root, true, "#Operated by\s+(.+)#"));
            }
            $date = strtotime($this->normalizeDate($this->pdf->FindSingleNode("./following-sibling::p[string-length(normalize-space(.))>2][3]", $root)));

            $s->departure()
                ->noCode()
                ->date(strtotime($this->pdf->FindSingleNode("./following-sibling::p[string-length(normalize-space(.))>2][5]", $root), $date));

            $s->arrival()
                ->noCode()
                ->date(strtotime($this->pdf->FindSingleNode("./following-sibling::p[string-length(normalize-space(.))>2][6]", $root), $date));

            $s->extra()
                ->cabin($this->pdf->FindSingleNode("./following-sibling::p[string-length(normalize-space(.))>2][4]", $root));
        }
    }

    private function parseEmail_C($plainText, Email $email)
    {
        $this->logger->debug(__METHOD__);
        $textTop = $this->findСutSection($plainText, $this->t('-  Passenger Information'), $this->t('-  Itinerary'));

        $f = $email->add()->flight();

        $f->general()
            ->confirmation($this->re("#Confirmation number.*\n\s*([A-Z\d]+)#", $textTop))
            ->date(strtotime($this->normalizeDate($this->re("#Issue Date.+?\n\s*(.+)#i", $textTop))));

        if (preg_match_all("#^\s*\d\.\s+(.*)#m", $textTop, $m)) {
            $f->general()
                ->travellers($this->normalizeTravellers($m[1]));
        }

        $xpath = "//*[contains(.,'Operated')]/ancestor::p[1]/preceding::p[10]";
        $nodes = $this->pdf->XPath->query($xpath);

        //it-468973130.eml
        if ($nodes->length === 0) {
            $xpath = "//text()[contains(.,':')]/ancestor::p[1]/following::p[1][contains(., ':')]/preceding::p[normalize-space()][8][following::text()[contains(normalize-space(), 'Total Cost')]]";
            $nodes = $this->pdf->XPath->query($xpath);
        }

        // $this->logger->debug('$xpath = '.print_r( $xpath,true));

        foreach ($nodes as $root) {
            if (preg_match("/(^\s*\d+\s*$|\bStop|Operated by)/", $this->pdf->FindSingleNode(".", $root))) {
                $root = $this->pdf->XPath->query("following::p[normalize-space()][1]", $root)->item(0);
            }

            if (preg_match("/(^Arrêt\(s\)|\d+\:\d+)/", $this->pdf->FindSingleNode(".", $root))) {
                $root = $this->pdf->XPath->query("following::p[normalize-space()][2]", $root)->item(0);
            }
            $s = $f->addSegment();

            $node = implode(" ", $this->pdf->FindNodes(".", $root));

            if (preg_match("#(.+?)\s*(?:TERMINAL\s*(\d+)|$)#s", $node, $m)) {
                $s->departure()
                    ->name($m[1]);

                if (!empty($m[2])) {
                    $s->departure()
                        ->terminal($m[2]);
                }
            }

            $node = $this->pdf->FindSingleNode("./following::p[1]", $root);

            if (preg_match("#(.+?)\s*(?:TERMINAL\s*(\d+)|$)#s", $node, $m)) {
                $s->arrival()
                    ->name($m[1]);

                if (isset($m[2]) && !empty($m[2])) {
                    $s->arrival()
                        ->terminal($m[2]);
                }
            }

            $node = $this->pdf->FindSingleNode("./following::p[2]", $root);

            if (preg_match("#([A-Z\d]{2})\s*(\d+)#", $node, $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2]);
            }
            $date = strtotime($this->normalizeDate($this->pdf->FindSingleNode("./following::p[3]", $root)));

            $s->departure()
                ->noCode()
                ->date(strtotime($this->pdf->FindSingleNode("./following-sibling::p[string-length(normalize-space(.))>2][contains(.,':')][1]", $root), $date));

            $s->arrival()
                ->noCode()
                ->date(strtotime($this->pdf->FindSingleNode("./following-sibling::p[string-length(normalize-space(.))>2][contains(.,':')][2]", $root), $date));

            $s->extra()
                ->cabin($this->pdf->FindSingleNode("./following-sibling::p[string-length(normalize-space(.))>2][4]", $root));

            $operator = $this->pdf->FindSingleNode("./following::p[contains(.,'Operated')][1]", $root, true, "/Operated by\s+(.+)/");

            if (!empty($operator)) {
                $s->airline()
                    ->operator($operator);
            }
        }

        // Price
        $start = $this->pdf->XPath->query("(//text()[contains(., 'Air fare')])[1]/preceding::text()[normalize-space()]")->length;
        $end = $this->pdf->XPath->query("(//text()[contains(., 'Total Cost')])[last()]/preceding::text()[normalize-space()]")->length;

        if (!empty($end) && !empty($start) && $end > $start) {
            $count = $end + 3 - $start;
            $rows = $this->pdf->FindNodes("(//text()[contains(., 'Air fare')])[1]/preceding::text()[normalize-space()][1]/following::text()[normalize-space()][position() < {$count}]");
            $isPriceRow = false;
            $currency = null;
            $cost = 0.0;
            $total = 0.0;
            $fees = [];

            for ($i = 0; $i < count($rows); $i++) {
                if (preg_match('/(?:^|\/)\s*Air fare\s*$/', $rows[$i])) {
                    $isPriceRow = true;
                    $i++;
                    $currency = $this->re("/^[^[:alpha:]]*([A-Z]{3})[^[:alpha:]]*$/", $rows[$i]);
                    $value = PriceHelper::parse($this->re("/^\D*(\d[\d,. ]*)\D*$/", $rows[$i]), $currency);

                    if (is_numeric($cost) && is_numeric($value)) {
                        $cost += $value;
                    } else {
                        $cost = null;
                    }
                } elseif ($isPriceRow === true && preg_match('/Total\s+Cost/ui', $rows[$i])) {
                    $isPriceRow = false;
                    $i++;
                    $currency = $this->re("/^[^[:alpha:]]*([A-Z]{3})[^[:alpha:]]*$/", $rows[$i]);
                    $value = PriceHelper::parse($this->re("/^\D*(\d[\d,. ]*)\D*$/", $rows[$i]), $currency);

                    if (is_numeric($total) && is_numeric($value)) {
                        $total += $value;
                    } else {
                        $total = null;
                    }

                    continue;
                } elseif ($isPriceRow === false) {
                } elseif ($isPriceRow === true) {
                    $name = $rows[$i];
                    $i++;
                    $currency = $this->re("/^[^[:alpha:]]*([A-Z]{3})[^[:alpha:]]*$/", $rows[$i]);
                    $value = PriceHelper::parse($this->re("/^\D*(\d[\d,. ]*)\D*$/", $rows[$i]), $currency);

                    if (empty($currency) || empty($value)) {
                        $currency = $this->re("/^[^[:alpha:]]*([A-Z]{3})[^[:alpha:]]*$/", $rows[$i + 1]);
                        $value = PriceHelper::parse($this->re("/^\D*(\d[\d,. ]*)\D*$/", $rows[$i + 1]), $currency);

                        if (!empty($currency) && !empty($value)) {
                            $name = $rows[$i];
                            $i++;
                        }
                    }

                    if (is_numeric($value)) {
                        if (isset($fees[$name])) {
                            $fees[$name] += $value;
                        } else {
                            $fees[$name] = $value;
                        }
                    }
                }
            }

            $f->price()
                ->currency($currency);

            if (!empty($cost)) {
                $f->price()
                    ->cost($cost);
            }

            if (!empty($total)) {
                $f->price()
                    ->total($total);
            }

            foreach ($fees as $name => $value) {
                $f->price()
                    ->fee($name, $value);
            }
        }
    }

    private function normalizeDate($date)
    {
        $in = [
            '#(\d+)[\.\/]*(\d+)[\.\/]*(\d{4})#',
            '#\w+,\s+(\d+\s+\w+\s+\d+)#u',
        ];
        $out = [
            '$3-$2-$1',
            '$1',
        ];

        return preg_replace($in, $out, $date);
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function AssignLang($body)
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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }
}
