<?php

namespace AwardWallet\Engine\bedsonline\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BookingConfirmation extends \TAccountChecker
{
    public $mailFiles = "bedsonline/it-36870017.eml, bedsonline/it-37897815_error.eml, bedsonline/it-38385599.eml, bedsonline/it-42615889.eml";

    public $dateFormatMDY;
    public $lang = "en";
    public static $dictionary = [
        "en" => [],
    ];

    private $detectFrom = ["bedsonline.com"];
    private $detectSubject = [
        "en" => "Booking confirmation Bedsonline", // Booking confirmation Bedsonline 75-1372588
    ];

    private $detectCompany = ['with Bedsonline'];
    private $detectBody = [
        "en" => "Below you will find your booking details",
    ];

    public function detectEmailFromProvider($from)
    {
        return $this->striposAll($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers["subject"])) {
            return false;
        }
//        if ($this->striposAll($headers["from"], $this->detectFrom)===false)
//            return false;

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers["subject"], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $parser->getPlainBody();

        if (empty($body)) {
            $body = $parser->getPlainBody();
        }

        if ($this->striposAll($body, $this->detectCompany) === false) {
            return false;
        }

        foreach ($this->detectBody as $dBody) {
            if (strpos($body, $dBody) !== false) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $body = $parser->getPlainBody();

        if (empty($body)) {
            $body = $parser->getHTMLBody();
        }

        $this->detectDateFormat($body);

        $this->parsePlain($email, $body);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function parsePlain(Email $email, string $text)
    {
        $traveller = $this->re("#\n\s*Name:\s*(.+)\s+#", $text);
        $serviceName = trim(preg_replace("#\s+#", ' ', $this->re("#\n\s*Service description:[ ]*(.+?)\n\s*Occupancy:#s", $text)));
        $occupancy = trim($this->re("#\n\s*Occupancy:[ ]*(.+)\s+#", $text));
        $dates = trim($this->re("#\n\s*Booking dates:[ ]*(.+)\s+#", $text));

        if (empty($serviceName) || empty($occupancy) || empty($dates)) {
            return $email;
        }
        $cancellation = preg_replace("#\s+#", ' ', trim($this->re("#\n\s*CANCELLATION CHARGES\s*\n\s*([\s\S]+?)\n\s*\n#", $text)));

        if (preg_match("#^\s*(.+ - .+, .+) \((.+ - .+)\)\s*$#", $serviceName, $m)) {
            // Transfer
            $it = $email->add()->transfer();

            $s = $it->addSegment();

            $routes = explode(" - ", $m[2]);

            if (count($routes) == 2) {
                $s->departure()->name($routes[0]);
                $s->arrival()->name($routes[1]);
            } elseif (preg_match("#(.+ Airport) - (.+)#", $m[2], $mat)) {
                $s->departure()->name($mat[1]);
                $s->arrival()->name($mat[2]);
            }

            $s->departure()->noDate();
            $s->arrival()->noDate();

            $s->extra()->type(trim($m[1]));
        } elseif (preg_match("#^\s*(.+) / \\1\s*$#", $serviceName, $m) && preg_match("#^\s*\d+ x \d+.+#", $occupancy, $mat)) {
            // Hotel
            $it = $email->add()->hotel();

            $it->hotel()
                ->name($m[1])
                ->noAddress();

            $times = explode(" - ", $dates);

            if (count($times) == 2) {
                $it->booked()->checkIn($this->normalizeDate($times[0]));
                $it->booked()->checkOut($this->normalizeDate($times[1]));
            }

            $it->booked()
                ->rooms($this->re("#^\s*(\d+) x #", $occupancy))
                ->guests($this->re("#\b(\d+) Adult#", $occupancy))
                ->kids($this->re("#\b(\d+) Child#", $occupancy), true, true)
            ;
        } else {
            // Event
            $it = $email->add()->event();

            $it->place()
                ->name($serviceName)
//                ->address('???')
                ->type(\AwardWallet\Schema\Parser\Common\Event::TYPE_EVENT);

            $times = explode(" - ", $dates);

            if (count($times) == 2) {
                $it->booked()->start($this->normalizeDate($times[0]));
                $it->booked()->noEnd();
            }

            $it->booked()
                ->guests($this->re("#\b(\d+) Adult#", $occupancy));
        }

        $it->general()
            ->noConfirmation()
            ->traveller($traveller)
            ->cancellation($cancellation)
        ;

        if ($it->getType() === 'event') {
            // no address event -> junk
            $email->removeItinerary($it);
            $email->setIsJunk(true);
        } else {
            // Travel Agency
            $email->ota()
                ->confirmation(str_replace(' ', '', $this->re("#\n\s*Booking reference:\s*([\d\- ]{6,})\s+#", $text)), 'Booking reference');
        }

        return $email;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function detectDateFormat($text)
    {
        if (preg_match_all("#\s(\d{2})/(\d{2})/20\d{2}(?:\s|$)#", $text, $m)) {
            foreach ($m[1] as $key => $v) {
                if ($m[1][$key] > 31 || $m[2][$key] > 31) {
                    continue;
                }

                if ($m[1][$key] > 12 && $m[1][$key] < 32 && $m[2][$key] < 13) {
                    if ($this->dateFormatMDY === true) {
                        $this->dateFormatMDY = null;

                        return null;
                    }
                    $this->dateFormatMDY = false;
                }

                if ($m[2][$key] > 12 && $m[2][$key] < 32 && $m[1][$key] < 13) {
                    if ($this->dateFormatMDY === false) {
                        $this->dateFormatMDY = null;

                        return null;
                    }
                    $this->dateFormatMDY = true;
                }
            }
        }

        return null;
    }

    private function detectDateFormatByDates($dateIn, $dateOut)
    {
        if (preg_match("#^\s*(\d{2})/(\d{2})/(20\d{2})\s*$#", $dateIn, $m1)
                && preg_match("#^\s*(\d{2})/(\d{2})/(20\d{2})\s*$#", $dateOut, $m2)) {
            if ($m1[1] > 31 || $m1[2] > 31 || $m2[1] > 31 || $m2[2] > 31) {
                return null;
            }

            if (($m1[1] > 12 && $m1[1] < 32 && $m1[2] < 13 && $m2[2] < 13)
                    || ($m2[1] > 12 && $m2[1] < 32 && $m2[2] < 13 && $m1[2] < 13)) {
                $this->dateFormatMDY = false;

                return null;
            }

            if (($m1[2] > 12 && $m1[2] < 32 && $m1[1] < 13 && $m2[1] < 13)
                    || ($m2[2] > 12 && $m2[2] < 32 && $m2[1] < 13 && $m1[1] < 13)) {
                $this->dateFormatMDY = true;

                return null;
            }
            $diff1 = strtotime($m1[1] . '.' . $m1[2] . '.' . $m1[3]) - strtotime($m1[1] . '.' . $m1[2] . '.' . $m1[3]);
            $diff2 = strtotime($m1[2] . '.' . $m1[1] . '.' . $m1[3]) - strtotime($m1[2] . '.' . $m1[1] . '.' . $m1[3]);

            if ($diff1 < $diff2) {
                $this->dateFormatMDY = false;
            } elseif ($diff1 < $diff2) {
                $this->dateFormatMDY = true;
            }
        }

        return null;
    }

    private function normalizeDate($str)
    {
        $in = [
            "#^\s*(\d{2})/([^\W\d]+)/(\d{4})\s*$#", // 14/Oct/2019
            "#^\s*(\d{2})/(\d{2})/(\d{4})\s*$#", // 04/05/2019
        ];

        if ($this->dateFormatMDY === false) {
            $out = [
                '$1 $2 $3',
                '$1.$2.$3',
            ];
        } else {
            $out = [
                '$1 $2 $3',
                '$2.$1.$3',
            ];
        }

        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function split($re, $text)
    {
        $r = preg_split($re, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        $ret = [];

        if (count($r) > 1) {
            array_shift($r);

            for ($i = 0; $i < count($r) - 1; $i += 2) {
                $ret[] = $r[$i] . $r[$i + 1];
            }
        } elseif (count($r) == 1) {
            $ret[] = reset($r);
        }

        return $ret;
    }

    private function rowColsPos($row)
    {
        $head = array_filter(array_map('trim', explode("|", preg_replace("#\s{2,}#", "|", $row))));
        $pos = [];
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }

    private function ColsPos($table, $correct = 5)
    {
        $pos = [];
        $rows = explode("\n", $table);

        foreach ($rows as $row) {
            $pos = array_merge($pos, $this->rowColsPos($row));
        }
        $pos = array_unique($pos);
        sort($pos);
        $pos = array_merge([], $pos);

        foreach ($pos as $i=> $p) {
            for ($j = $i - 1; $j >= 0; $j = $j - 1) {
                if (isset($pos[$j])) {
                    if (isset($pos[$i])) {
                        if ($pos[$i] - $pos[$j] < $correct) {
                            unset($pos[$i]);
                        }
                    }

                    break;
                }
            }
        }

        sort($pos);
        $pos = array_merge([], $pos);

        return $pos;
    }

    private function SplitCols($text, $pos = false)
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->rowColsPos($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k=>$p) {
                $cols[$k][] = trim(mb_substr($row, $p, null, 'UTF-8'));
                $row = mb_substr($row, 0, $p, 'UTF-8');
            }
        }
        ksort($cols);

        foreach ($cols as &$col) {
            $col = implode("\n", $col);
        }

        return $cols;
    }

    private function opt($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
    }

    private function amount($s)
    {
        if (($s = $this->re("#([\d\,\.]+)#", $s)) === null) {
            return null;
        }

        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $s));
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

    private function striposAll($text, $needle): bool
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

    private function inOneRow($text)
    {
        $textRows = array_filter(explode("\n", $text));

        if (empty($textRows)) {
            return '';
        }
        $pos = [];
        $length = [];

        foreach ($textRows as $key => $row) {
            $length[] = mb_strlen($row);
        }
        $length = max($length);
        $oneRow = '';

        for ($l = 0; $l < $length; $l++) {
            $notspace = false;

            foreach ($textRows as $key => $row) {
                $sym = mb_substr($row, $l, 1);

                if ($sym !== false && trim($sym) !== '') {
                    $notspace = true;
                    $oneRow[$l] = 'a';
                }
            }

            if ($notspace == false) {
                $oneRow[$l] = ' ';
            }
        }

        return $oneRow;
    }
}
