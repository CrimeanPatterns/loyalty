<?php

namespace AwardWallet\Engine\ctmanagement\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class FlightItinerary extends \TAccountChecker
{
    public $mailFiles = "ctmanagement/it-60599753.eml, ctmanagement/it-60662514.eml, ctmanagement/it-60836688.eml, ctmanagement/it-61958502.eml, ctmanagement/it-62165918.eml, ctmanagement/it-62251250.eml, ctmanagement/it-62252205.eml";

    public $reFrom = "@travelctm.com";
    public $reSubject = [
        // LI MAN--19OCT--26OCT--PEKORDATWORDPEK
        // CHENG ZHI YUAN--28OCT--01NOV--TSNICNTSN
        'en' => '/--\d+[A-Z]{3}--/',
        'zh' => '/\D+\d+\/\d+\w+\s+\D+/',
    ];
    public $reBody = 'travelctm.com';
    public $reBody2 = [
        'en' => ['ISSUING AGENT:'],
        'zh' => ['出票代理人'],
    ];

    public static $dictionary = [
        'en' => [
            //            'confirmation' => ['Train Confirmation:'],
            //            'traveller' => ['Passenger:'],
        ],
        'zh' => [
            'Record locator' => '订座记录编号',
            'ITINERARY'      => '电子客票行程单',
            'AIRLINE PNR:'   => '航空公司记录编号',
            'DATE OF ISSUE:' => '出票时间：',
            'NAME:'          => '旅客姓名：',
            'ID NUMBER:'     => '身份识别代码：',
            'traveller'      => '旅客姓名：',
            'ticket'         => '票号',
            'flight'         => '航班',
        ],
    ];

    public $lang = '';
    public $segNumber = 2;
    public $date;

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getHeader('date'));

        foreach ($this->reBody2 as $lang => $re) {
            if ($this->http->XPath->query("//*[{$this->contains($re)}]")->length > 0) {
                $this->lang = $lang;

                break;
            }
        }

        /*// Travel Agency
        $email->obtainTravelAgency();
        // Record locator
        $text = $this->htmlToText($parser->getHTMLBody());
        if (preg_match("/{$this->opt($this->t('Record locator'))}\s+([A-Z\d]{5,6})/s", $text, $m)) {
            $email->ota()->confirmation($m[1], 'Record locator');
        }
        // Total Charge
        if (preg_match("/Total Charge:\s+([\d.,\s]+)\s*([A-Z]{3})/", $text, $m)) {
            $email->price()
                ->total($m[1])
                ->currency($m[2]);
        }*/

        $xpath = "//text()[{$this->contains($this->t('ITINERARY'))}]/ancestor::table[1]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->count() === 0) {
            $xpath = "//text()[{$this->contains($this->t('ITINERARY'))}]/ancestor::b[1]/ancestor::table[2]";
            $nodes = $this->http->XPath->query($xpath);
        }

        foreach ($nodes as $root) {
            $this->parseFlight($email, $root);
            $this->segNumber = $this->segNumber + 1;
        }
        $a = explode('\\', __CLASS__);
        $email->setType($a[count($a) - 1] . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (!isset($headers["from"], $headers["subject"])) {
            return false;
        }

        if (strpos($headers["from"], $this->reFrom) === false) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            if (preg_match($re, $headers["subject"])) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//*[{$this->contains($this->reBody)}]")->length > 0) {
            foreach ($this->reBody2 as $re) {
                if ($this->http->XPath->query("//*[{$this->contains($re)}]")->length > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    protected function htmlToText($string)
    {
        $NBSP = chr(194) . chr(160);
        $string = str_replace($NBSP, ' ', html_entity_decode($string));
        $string = str_replace('-->', '', html_entity_decode($string));
        $string = preg_replace('/<[^>]+>/', "\n", $string);
        $string = preg_replace(['/\n{2,}\s{2,}/'], "\n", $string);

        return $string;
    }

    private function parseFlight(Email $email, $root)
    {
//        $this->logger->debug($root->nodeValue);
//        $this->logger->debug('==================');

        $f = $email->add()->flight();
        $flightNumber = $this->http->FindSingleNode(".//following::table[{$this->contains($this->t('flight'))}][1]/descendant::tr[4]/td[2]", $root, true, '/([A-Z\d]{6})/');

        $recordLocator = $this->http->FindSingleNode("./following::text()[{$this->starts($this->t('Record locator'))}][1]/following::text()[normalize-space()][not(contains(normalize-space(), ':'))][1]", $root);
        $recordLocator = trim($recordLocator, '(');

        $this->logger->warning($recordLocator);
        $confirmation = $this->http->FindSingleNode(".//*[{$this->contains($this->t('AIRLINE PNR:'))}]/following-sibling::span[1]", $root, true, '/([A-Z\d]{6})/');

        if (empty($confirmation)) {
            $confirmation = $this->http->FindSingleNode("./following::text()[{$this->starts($this->t('AIRLINE PNR:'))}][1]/following::text()[normalize-space()][not(contains(normalize-space(), ':'))][1]", $root, true, '/([A-Z\d]{6})/');
        }

        if (!empty($confirmation)) {
            $f->general()
                ->confirmation($confirmation, $this->t('AIRLINE PNR:'));
        } else {
            $f->general()->noConfirmation();
        }

        $dateReserv = $this->http->FindSingleNode(".//*[{$this->contains($this->t('DATE OF ISSUE:'))}]/following-sibling::span[1]", $root);

        if (empty($dateReserv)) {
            $dateReserv = $this->http->FindSingleNode("./following::text()[{$this->starts($this->t('DATE OF ISSUE:'))}][1]/following::text()[normalize-space()][1]", $root);
        }
        $f->general()
            ->date($this->normalizeDate($dateReserv));

        $traveller = $this->http->FindSingleNode(".//*[{$this->contains($this->t('NAME:'))}]/following-sibling::span[1]", $root);

        if (empty($traveller)) {
            $traveller = $this->http->FindSingleNode("./following::text()[{$this->starts($this->t('NAME:'))}][1]/following::text()[normalize-space()][1]", $root);
        }
        $f->general()
            ->traveller($traveller, true);

        $account = $this->http->FindSingleNode(".//*[{$this->contains($this->t('ID NUMBER:'))}]/following-sibling::span[1]", $root);

        if (empty($account)) {
            $account = $this->http->FindSingleNode("./following::text()[{$this->starts($this->t('ID NUMBER:'))}][1]/following::text()[normalize-space()][1]", $root);
        }

        if (!empty($account)) {
            $f->program()
                ->account($account, false);
        }

        $xpath = "//text()[contains(normalize-space(.),'票号')]/ancestor::p[1]/preceding::p[contains(normalize-space(.),'{$recordLocator}') and contains(normalize-space(.),'1.')]/following::p[contains(normalize-space(), '{$flightNumber}')][1][not(contains(normalize-space(), '重新出票'))]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->count() == 0) {
            $xpath = "//text()[contains(.,'ORIGIN/DES')]/ancestor::tr[1]/following-sibling::tr";
            $nodes = $this->http->XPath->query($xpath);
        }

        if ($nodes->count() == 0) {
            $xpath = "//text()[contains(normalize-space(.),'票号')]/ancestor::p[1]/preceding::text()[starts-with(normalize-space(), 'Dear')]/following::p[contains(normalize-space(), '{$flightNumber}')][1][not(contains(normalize-space(), '重新出票'))]";
            $nodes = $this->http->XPath->query($xpath);
        }
        $items = [];

        for ($i = 0; $i < $nodes->length; $i++) {
            $item = trim($nodes->item($i)->nodeValue);
            //$this->logger->debug($item);
            //$this->logger->debug('=============');

            // PVG--PUDONG,SHANGHAI   FM885   N   10NOV   0910   1450   10NOV9/10NOV9   OK   1PC   T1   1
            // KUL--KLIA   FM886   N   15NOV   1610   2130   15NOV9/15NOV9   OK   1PC   1    T1
            // PVG--PUDONG,SHANGHAI
            if (preg_match('/^\s*(?<depCode>[A-Z]{3})--(?<depName>[\w,\s\']+)\s{2,}\s+(?<airName>[A-Z]{2})\s*(?<airCode>\d{2,4})'
                    . '\s+(?<cabin>[A-Z])\s+(?<date>\d+[A-Z]{3})\s+(?<depTime>\d{4})'
                    . '\s+(?:(?<arrTime>\d{4})(?<plusDay>\+\d+)?)?'
                    . '.+?(?:(?<depTerm>[A-Z\d]{1,2})\s+(?<arrTerm>[A-Z\d]{1,2}))?$/', $item, $m)
                // PVG--PUDONG,SHANGHAI
                || preg_match('/^\s*(?<arrCode>[A-Z]{3})--(?<arrName>[\w,\s\']+)\s*$/', $item, $m)

                //1.胡效苏 KM60D5
                //2.  ZH8977 L   MO22JUN  SZXKMG 深圳T3一昆明  0920 1140  经济舱CNY500+50TAX+16服务费=CNY566
                //3.  ZH8978 K   WE24JUN  KMGSZX 昆明一深圳T3  1245 1500  经济舱CNY380+50TAX+16服务费=CNY446
                || preg_match("/^\s*\d+\.\s*(?<airName>[A-Z]{2})(?<airCode>[\d]{2,4})\s*(?<bookingCode>[A-Z]{1})\s*(?<date>\w+)\s+(?<depCode>[A-Z]{3})(?<arrCode>[A-Z]{3})\s*.+?(?:T(?<depTerm>\w))?(?:\一|-).+?(?:T(?<arrTerm>\w))?\s*(?<depTime>\d{4})\s*(?<arrTime>\d{4})\s*(?<cabin>\w+)\：?\s*[A-Z]{3}.+\=(?<currency>[A-Z]{3})(?<total>[\d\.\,]+)\s*(?:\w+\:)?(?<tNumber>[\d\-]+)?$/su", $item, $m)
                || preg_match("/^\s*\d+\.\s*\*?(?<airName>[A-Z]{2})(?<airCode>[\d]{2,4})\s*(?<bookingCode>[A-Z]{1})\s*(?<date>\w+)\s+(?<depCode>[A-Z]{3})(?<arrCode>[A-Z]{3})\s*.+?(?:T(?<depTerm>\w))?(?:\一|-).+?(?:T(?<arrTerm>\w))?\s*(?<depTime>\d{4})\s*(?<arrTime>\d{4})\s*\w+(?<airName2>[A-Z]{2})(?<airCode2>[\d]{2,4})\,?\w+\s*(?<cabin>\w+)\：?\s*[A-Z]{3}.+\=(?<currency>[A-Z]{3})(?<total>[\d\.\,]+)\s*(?:\w+\:)?(?<tNumber>[\d\-]+)?$/su", $item, $m)
                || preg_match("/^\w+\s*\d+\.?\s*\*?\w+\s*(?<airName>[A-Z]{2})(?<airCode>[\d]{2,4})\s*(?<depCode>[A-Z]{3})(?<arrCode>[A-Z]{3})\s*.+?(?:T(?<depTerm>\w))?(?:\一|-|一)\s*.+?(?:T(?<arrTerm>\w))?\s*(?<depTime>\d{4})\-?\s*(?<arrTime>\d{4})\s*\s*(?<cabin>\w+)\：?\s*[A-Z]{3}.+\=(?<currency>[A-Z]{3})(?<total>[\d\.\,]+)\s*(?:\w+\:)?(?<tNumber>[\d\-]+)?$/su", $item, $m)
            ) {
                if (count($m) > 5) {
                    $items[$i] = $m;
                }

                if (isset($items[$i - 1])) {
                    $items[$i - 1]['arrCode'] = $m[1];
                    $items[$i - 1]['arrName'] = $m[2];
                }
            }
        }

        //$this->logger->debug(var_export($items, true));

        if (count($items) > 1 && current($items)['depCode'] != end($items)['arrCode']) {
            $this->logger->alert('Check segments something is wrong with sequence');

            return;
        }

        foreach ($items as $item) {
            $s = $f->addSegment();

            if (!isset($item['airName2'])) {
                $s->airline()
                    ->name($item['airName'])
                    ->number($item['airCode']);
            } else {
                $s->airline()
                    ->name($item['airName2'])
                    ->number($item['airCode2']);
            }

            $s->extra()
                ->cabin($item['cabin']);

            if (isset($item['bookingCode']) && !empty($item['bookingCode'])) {
                $s->extra()
                    ->bookingCode($item['bookingCode']);
            }

            //Departure
            if (isset($item['depName']) && !empty($item['depName'])) {
                $s->departure()
                    ->name($item['depName']);
            }

            $s->departure()
                ->code($item['depCode']);

            if (isset($item['depTerm']) && !empty($item['depTerm'])) {
                $s->departure()
                    ->terminal($item['depTerm'], false, true);
            }

            if (!isset($item['date'])) {
                $item['date'] = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), '电子客票行程单')]/following::text()[contains(normalize-space(), '{$flightNumber}')][1]/following::text()[normalize-space()][2]");
            }

            if (isset($item['depTime']) && !empty($item['depTime'])) {
                $s->departure()
                    ->date($this->normalizeDate("{$item['date']}, {$item['depTime']}"));
            } else {
                $s->departure()
                    ->date2($this->normalizeDate("{$item['date']}"));
            }

            //Arrival
            if (isset($item['arrName']) && !empty($item['arrName'])) {
                $s->arrival()
                    ->name($item['arrName']);
            }

            $s->arrival()
                ->code($item['arrCode']);

            if (isset($item['arrTerm']) && !empty($item['arrTerm'])) {
                $s->arrival()
                    ->terminal($item['arrTerm'], false, true);
            }

            // 26OCT   1520   1805+1
            if (!empty($item['date']) && !empty($item['arrTime'])) {
                $arrDate = $this->normalizeDate("{$item['date']}, {$item['arrTime']}");

                if (!empty($item['plusDay'])) {
                    $arrDate = strtotime("{$item['plusDay']} day", $arrDate);
                }
                $s->arrival()->date($arrDate);
            } else {
                // 26OCT   1135
                $s->arrival()->noDate();
                $s->arrival()->day2($item['date']);
            }

            if (isset($item['currency']) && !empty($item['currency'])) {
                $f->price()
                    ->currency($item['currency']);
            }

            if (isset($item['total']) && !empty($item['total'])) {
                $f->price()
                    ->total($item['total']);
            }

            if (!isset($item['tNumber'])) {
                $item['tNumber'] = $this->http->FindSingleNode("./following::text()[starts-with(normalize-space(), '票号')][1]/following::text()[normalize-space()][1]", $root, true, "/^(\d+)$/");
            }

            if (isset($item['tNumber']) && !empty($item['tNumber'])) {
                $f->issued()
                    ->ticket($item['tNumber'], false);
            }
        }
    }

    /**
     * <pre>
     * Example:
     * abc SPLIT text\n text1\n acv SPLIT text\n text1
     * /(SPLIT)/
     * [0] => SPLIT text text1 acv
     * [1] => SPLIT text text1
     * <pre>.
     */
    private function splitter($pattern, $text)
    {
        $result = [];

        $array = preg_split($pattern, $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        array_shift($array);

        for ($i = 0; $i < count($array) - 1; $i += 2) {
            $result[] = $array[$i] . $array[$i + 1];
        }

        return $result;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return null;
        }

        return self::$dictionary[$this->lang][$word];
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

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ',
                array_map(function ($s) use ($node) {
                    return 'normalize-space(' . $node . ')="' . $s . '"';
                }, $field)) . ')';
    }

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ',
                array_map(function ($s) use ($node) {
                    return 'contains(normalize-space(' . $node . '),"' . $s . '")';
                }, $field))
            . ')';
    }

    private function normalizeDate($str)
    {
        $year = date("Y", $this->date);

        $in = [
            "#^(\w+)\s+(\d+),\s+(\d{4})$#",
            "#^(\w+)\s+(\d+),\s+(\d{4}),\s+(\d+:\d+\s*[ap]m)$#i",
            "#^(\d+)(\w+)(\d{2})$#u", //18JUN20
            "#^[A-Z]{2}(\d{2})(\D+)\,\s*(\d{2})(\d{2})$#", //MO22JUN, 0920
        ];
        $out = [
            "$2 $1 $3",
            "$2 $1 $3, $4",
            "$1 $2 20$3",
            "$1 $2 $year, $3:$4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }
}
