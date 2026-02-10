<?php

namespace AwardWallet\Engine\airasia\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class BookingNo extends \TAccountChecker
{
    public $mailFiles = "airasia/it-48550247.eml, airasia/it-48774051.eml";

    public $lang = '';
    public $date;

    public static $dict = [
        'en' => [],
        'id' => [
            'Booking no.' => 'Nomor pemesanan',
            'Guests'      => 'Penumpang',
            'Flight'      => 'Penerbangan',
            'Last paid'   => 'Pembayaran terakhir',
            'hours'       => 'jam',
            'minutes'     => 'menit',
        ],
        'zh' => [
            'Booking no.' => '預訂號碼',
            'Guests'      => '旅客',
            'Flight'      => '航班',
            'Last paid'   => '上次已付款項',
            'hours'       => '小時',
            'minutes'     => '分鐘',
        ],
    ];
    private $from = [
        '.airasia.com',
    ];
    private $subject = [
        ' (Booking No: ',
    ];
    private $body = [
        'en' => ['Remember to check that your passport validity meets the entry requirements', ' your booking is confirmed.'],
        'id' => ['Penerbangan'],
        'zh' => ['航班'],
    ];

    public function detectEmailFromProvider($from)
    {
        return $this->stripos($from, $this->from) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        return $this->stripos($headers['subject'], $this->subject) !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[{$this->contains($this->from)}]")->length === 0
            && $this->http->XPath->query("//a[contains(@href,'.airasia.com')] | //img[contains(@alt,'AirAsia')]")->length === 0
        ) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->alert("Can't determine a language!");

            return $email;
        }
        $text = $this->htmlToText(!empty($parser->getHTMLBody()) ? $parser->getHTMLBody() : $parser->getPlainBody(),
            true);
        $text = substr($text, 0, 10000);
        $this->parseFlight($email, $text);
        $a = explode('\\', __CLASS__);
        $email->setType($a[count($a) - 1] . ucfirst($this->lang));

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

    /**
     * <pre>
     * Example:
     * abc SPLIT text\n text1\n acv SPLIT text\n text1
     * /(SPLIT)/
     * [0] => SPLIT text text1 acv
     * [1] => SPLIT text text1
     * <pre>.
     */
    protected function splitter($regular, $text)
    {
        $result = [];

        $array = preg_split($regular, $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        array_shift($array);

        for ($i = 0; $i < count($array) - 1; $i += 2) {
            $result[] = $array[$i] . $array[$i + 1];
        }

        return $result;
    }

    protected function normalizePrice($string)
    {
        $string = preg_replace('/\s+/', '', $string);			// 11 507.00	->	11507.00
        $string = preg_replace('/[,.](\d{3})/', '$1', $string);	// 2,790		->	2790		or	4.100,00	->	4100,00
        $string = preg_replace('/,(\d{2})$/', '.$1', $string);	// 18800,00		->	18800.00

        return $string;
    }

    private function parseFlight(Email $email, string $text)
    {
        $f = $email->add()->flight();
        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->starts($this->t('Booking no.'))}]/ancestor::td[1]",
                null,
                false, '/\s*([A-Z\d]{5,6})/'), $this->t('Booking no.'));

        $xpath = "//*[{$this->eq($this->t('Guests'))}]/following::*[contains(@style,'color:#067E41') or contains(@style,'color:#067e41') or contains(@style,'color:rgb(6,126,65)')]";
        $guests = $this->http->XPath->query($xpath);
        $seats = $travellers = [];

        if ($guests->length > 0) {
            foreach ($guests as $node) {
                $travellers[] = $node->nodeValue;
                $airline = $this->http->FindSingleNode(
                    "(./ancestor::tr[2]/preceding-sibling::tr[1]//td[//img[contains(@src,'flight-icons')]]/following-sibling::td[normalize-space()!=''])[1]",
                    $node, false, '/(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*\d{1,4}/');

                if ($seat = $this->http->FindSingleNode("./ancestor::tr[1]/following-sibling::tr[1]//*[contains(text(), 'Seat')]",
                    $node, false, '/Seat.+?([A-Z\d]{2,3})/')) {
                    $seats[str_replace(' ', '', $airline)][] = $seat;
                }
            }
        } else {
            $nodes = $this->splitter('/\s*(' . $this->t('Guests') . '\s*\n)/', $text);

            foreach ($nodes as $node) {
                $nodes = $this->splitter('/((?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\s+\d{1,4})/s', $node);

                foreach ($nodes as $node) {
                    if (preg_match_all('/\n+\s*(M[\w.\s]+)\s*\n\s*Seat.+?[A-Z\d]{2,3}/', $node, $m)) {
                        $travellers = array_merge($travellers, $m[1]);
                    }

                    if (preg_match('/(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\s+\d{1,4}/s', $node, $m)) {
                        $airline = $m[0];
                    }

                    if (preg_match_all('/Seat.+?([A-Z\d]{2,3})/s', $node, $m)) {
                        $seat = $m;
                    }

                    if (isset($airline, $seat[1])) {
                        $seats[str_replace(' ', '', $airline)] = $seat[1];
                    }
                }
            }
        }

        if (!empty($travellers)) {
            $f->general()->travellers(array_unique($travellers), true);
        }

        // Flight 1 Tue, 19 Nov 2019
        $nodes = $this->splitter('/(' . $this->t('Flight') . '\s+\d{1,2}\s*\n*\s*\w{3,8},\s+\d+ \w+ \d{4})/', $text);

        if (empty($nodes)) {
            $nodes = $this->splitter('/(' . $this->t('Flight') . '\s+\d{1,2}\s*\n*\s*\D+,\s+\d{4}\D+\d{1,2}\D+\d{1,2}\D+\s{4,})/', $text);
        }

        foreach ($nodes as $node) {
//            $this->logger->debug($node);
//            $this->logger->debug('================================');
            if (preg_match('/' . $this->t('Flight') . '\s+\d{1,2}\s+(\w{3,8},\s+\d+ \w+ \d{4})/s', $node, $m)) { // if date en format first
                $this->date = ($m[1]);
            } elseif (preg_match('/' . $this->t('Flight') . '\s+\d{1,2}\s+.+?(\w{3,8},\s+\d+ \w+ \d{4})/s', $node, $m)) { // if date en format second
                $this->date = ($m[1]);
            } elseif (preg_match('/' . $this->t('Flight') . '\s+\d{1,2}\s+(\D+[,]\s+\d{4}\D\d{1,2}\D\d{1,2}\D)/su', $node, $m)) { // if date cn only
                $this->date = ($m[1]);
            }
            /*
             06: 40 Bangkok - Don Mueang (DMK) T1
             07:55 Udon Thani (UTH) T4
             FD 3362

             22: 20 Singapore (SIN) T4
             Mon, 23 Dec 2019
             Arrives next day
             02:15 Cebu (CEB) Z2 7237
             3 hours 55 minutes
            */
            if (preg_match_all(
                '/(?<depTime>\d+:\s*\d+)\s+(?<depPlusDay>\+\d+)?(?<depName>.+?)\s*\((?<depCode>[A-Z]{3})\)(?<depTer>\s+[A-Z\d]{1,5})?\s+'
                . '(?:\w{3},\s+\d+ \w+ \d{4}\s+Arrives next day\s+)?'
                . '(?<arrTime>\d+:\s*\d+)[\s\n]+(?<arrPlusDay>\+\d+)?(?<arrName>.+?)\s*\((?<arrCode>[A-Z]{3})\)(?<arrTer>\s+[A-Z\d]{1,5})?\s+'
                . '(?<arName>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<arNum>\d{1,4})\s+'
                . '(?<Duration>\d+\s+' . $this->t('hours') . '\s+\d+\s+' . $this->t('minutes') . ')/s', $node, $m, PREG_SET_ORDER)) {
                foreach ($m as $item) {
                    $s = $f->addSegment();
                    $s->airline()->name($item['arName']);
                    $s->airline()->number($item['arNum']);

                    $s->departure()->name($item['depName']);
                    $s->arrival()->name($item['arrName']);

                    $s->departure()->code($item['depCode']);
                    $s->arrival()->code($item['arrCode']);

                    $s->departure()->terminal(empty($item['depTer']) ? null : $item['depTer'], false, true);
                    $s->arrival()->terminal(empty($item['arrTer']) ? null : $item['arrTer'], false, true);

                    if (!empty($item['depTime'])) {
                        $s->departure()->date2($this->normalizeDate("{$this->date}, {$item['depTime']}"));

                        if (!empty($item['depPlusDay'])) {
                            $s->departure()->date(strtotime("{$item['depPlusDay']} day", $s->getDepDate()));
                        }
                    }

                    if (!empty($item['arrTime'])) {
                        $s->arrival()->date2($this->normalizeDate("{$this->date}, {$item['arrTime']}"));

                        if (!empty($item['arrPlusDay'])) {
                            $s->arrival()->date(strtotime("{$item['arrPlusDay']} day", $s->getArrDate()));
                        }
                    }

                    if (isset($seats["{$item['arName']}{$item['arNum']}"])) {
                        $s->extra()->seats($seats["{$item['arName']}{$item['arNum']}"]);
                    }
                    $s->extra()->duration($item['Duration']);
                }
            }
        }

        // Last paid
        // via Visa
        // 251.66 USD
        if (preg_match('/' . $this->t('Last paid') . '.+?([\d.,]+)\s*([A-Z]{3})/s', $text, $m)) {
            $f->price()->total($this->normalizePrice($m[1]));
            $f->price()->currency($m[2]);
        }
    }

    private function assignLang(): bool
    {
        foreach ($this->body as $lang => $phrase) {
            if ($this->http->XPath->query("//*[{$this->contains($phrase)}]")->length > 0) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function normalizeDate($str)
    {
        $in = [
            // Tue, 19 Nov 2019, 06: 40
            '/(\w+, \d+ \w+ \d{4}), (\d+):\s*(\d+)/s',
            //Minggu, 22 Des 2019
            '/\w+, (\d+ \w+ \d{4})/s',
            //星期三, 2020年2月05日, 10: 35
            '/\D+(\d{4})\D(\d{1,2})\D(\d{1,2})\D[,]\s+(\d+)[:]\s+(\d+)/u',
            //星期六, 2020年7月18日, 13:15
            '/\D+(\d{4})\D(\d{1,2})\D(\d{1,2})\D[,]\s+(\d+[:]\d+)/u',
        ];
        $out = [
            "$1, $2:$3",
            "$1",
            "$1-$2-$3, $4:$5",
            "$1-$2-$3, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return $str;
    }

    private function t(string $phrase)
    {
        if (!isset(self::$dict, $this->lang) || empty(self::$dict[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dict[$this->lang][$phrase];
    }

    private function htmlToText($string, $view = false)
    {
        $NBSP = chr(194) . chr(160);
        $string = str_replace($NBSP, ' ', html_entity_decode($string));
        // Multiple spaces and newlines are replaced with a single space.
        $string = trim(preg_replace('/\s\s+/', ' ', $string));
        $text = preg_replace('/<[^>]+>/', "\n", $string);

        if ($view) {
            return preg_replace('/\n{2,}/', "\n", $text);
        }

        return $text;
    }

    private function currency($s)
    {
        $sym = [
            '€' => 'EUR',
            '£' => 'GBP',
        ];

        foreach ($sym as $f => $r) {
            if ($s == $f) {
                return $r;
            }
        }

        return null;
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

    private function contains($field, $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
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

    private function stripos($haystack, $arrayNeedle)
    {
        $arrayNeedle = (array) $arrayNeedle;

        foreach ($arrayNeedle as $needle) {
            if (stripos($haystack, $needle) !== false) {
                return true;
            }
        }

        return false;
    }
}
