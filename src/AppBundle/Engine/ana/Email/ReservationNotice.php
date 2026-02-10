<?php

namespace AwardWallet\Engine\ana\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class ReservationNotice extends \TAccountChecker
{
    public $mailFiles = "ana/it-145408668.eml, ana/it-146650058.eml, ana/it-20497872.eml, ana/it-21878807.eml, ana/it-23415741.eml, ana/it-6804540.eml";
    public $reFrom = "@121.ana.co.jp";
    public $reSubject = [
        'en' => ['Reservation Notice', 'Reservation Information', 'Payment Confirmation'],
        'ja' => ['搭乗前日のお知らせ'],
    ];
    public $reBody = 'ANA';
    public $reBody2 = [
        "en"  => "Thank you very much for flying with ANA",
        "en2" => "Thank you for using ANA Domestic Reservation",
        "en3" => "Thank you very much for using ANA＠desk",
        "ja"  => "ＡＮＡインターネット国内線予約",
        "ja2" => "いつもANAをご利用いただきありがとうございます。",
        "ja3" => "いつもANA",
    ];

    public static $dictionary = [
        "en" => [
            "Passenger" => ["Passenger", "Name"],
            //			"Seat Number" => "", // seat with flight order number
            //			"Seat No." => "", // seat without flight order number
            "seatEnd"     => ['Fare Amount', 'Request for cooperation', 'Fare', '----'],
            "Fare Amount" => ["Fare Amount", "Fare"],
            //			"Flight Information" => "",
            "dateFormat"         => "[^\d\s]+\s+\d+(?:\s+\d+)?\([^\d\s]+\)",
            "cabinVariants"      => ['Economy'],
            "Reservation Number" => ['Reservation Number', 'Reservation No.'],
//            "[After Change]"      => '',
        ],
        "ja" => [
            "Passenger"          => ["ご?搭乗者", "搭乗者"],
            "Seat Number"        => "座席番号", // seat with flight order number
            "Seat No."           => "座席番号", // seat without flight order number
            "seatEnd"            => ['運賃額', '※', '金額'],
            "Fare Amount"        => ["運賃額", "運賃額", "定時出発へのご協力のお", '金額'],
            "Flight Information" => "便情報",
            "dateFormat"         => "(?:\d{4}年\s*)?\d+\s*月\s*\d+\s*日\s*\([^\d\s]+\)",
            "cabinVariants"      => ['プレミアムクラス'],
            "Reservation Number" => '予約番号',
            "[After Change]"      => '【変更後】',
        ],
    ];

    public $lang = "en";

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true
            && strpos($headers['subject'], 'ANA Domestic') === false
            && strpos($headers['subject'], 'ana.co.jp Domestic') === false
            && strpos($headers['subject'], 'ANA国内線') === false
        ) {
            return false;
        }

        foreach ($this->reSubject as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (is_string($phrase) && stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getPlainBody();

        if (strpos($body, $this->reBody) === false) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if (strpos($body, $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getHeader('date'));

        $this->http->SetEmailBody($parser->getPlainBody());

        foreach ($this->reBody2 as $lang => $re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = substr($lang, 0, 2);

                break;
            }
        }
        $email->setType('ReservationNotice' . ucfirst($this->lang));

        $this->parsePlain($email);

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

    private function parsePlain(Email $email): void
    {
        $text = $this->http->Response['body'];
        $text = str_replace('>', '', $text);
        $text = str_replace("　", ' ', $text); //&#12288;

        $f = $email->add()->flight();

        $passengers = array_map("trim",
            explode("\n", $this->re("/\n[^\w\s]*\s*{$this->opt($this->t("Passenger"))}\s*\n\s*(.*?)\n\s*\n/us", $text))
        );

        if (empty($passengers[0])) {
            $passengers = array_map("trim",
                explode("\n", $this->re("/\n*\s*{$this->opt($this->t("Passenger"))}\s*\n\s*(.*?)\n\s*\n/us", $text))
            );
        }
        $f->general()->travellers($passengers);

        $seatsText = $this->cutText($this->t('Seat Number'), $this->t('seatEnd'), $text);
        $seats = [];
        $seatsOneFlight = [];
        preg_match_all('/\[(\d+)\]\D*(\d+[A-Z]\b)/u', $seatsText, $m);

        if (0 < count($m[1])) {
            foreach ($m[1] as $i => $value) {
                $seats[$value][] = $m[2][$i];
            }
        }

        if (empty($seatsText)) {
            $seatsText = $this->cutText($this->t('Seat No.'), $this->t('seatEnd'), $text);
            preg_match_all('/ \D*(\d{1,3}[A-Z])\s*\n/', $seatsText, $m);

            if (0 < count($m[1])) {
                $seatsOneFlight[] = $m[1];
            }
        }

        if (preg_match('/' . $this->opt($this->t('Fare Amount')) . '\s+(?<curr>[A-Z]{3})\s*(?<amount>\d[\d,]*)/', $text,
                $m)
            || preg_match('/' . $this->opt($this->t('Fare Amount')) . '\s+\s*(?<amount>\d[\d,]*)\s*(?<currsign>[^\d\s]{1,3})/',
                $text, $m)) {
            $f->price()->total(str_replace(',', '', $m['amount']));

            if (!empty($m['curr'])) {
                $f->price()->currency($m['curr']);
            }

            if (!empty($m['currsign'])) {
                $currences = [
                    '円'   => 'JPY',
                    'Yen' => 'JPY',
                ];

                foreach ($currences as $currencyFormat => $currencyCode) {
                    if (strpos($m['currsign'], $currencyFormat) !== false) {
                        $m['currsign'] = $currencyCode;

                        break;
                    }
                }
                $f->price()->currency($this->re('/^([A-Z]{3})$/', trim($m['currsign'])));
            }
        }

        $posEnd = false;

        if (is_array($this->t("Fare Amount"))) {
            foreach ($this->t("Fare Amount") as $value) {
                $posEnd = strpos($text, $value);

                if (!empty($posEnd)) {
                    break;
                }
            }
        } else {
            $posEnd = strpos($text, $this->t("Fare Amount"));
        }

        $n = strpos($text, $this->t("[After Change]"));
        $posStart = strpos($text, $this->t("Flight Information"), !empty($n)? $n : 0);
        $flights = substr($text, $posStart, $posEnd - $posStart);

        $segments = $this->split("/(\[\d+\]\s+" . $this->t("dateFormat") . "\s+.*?\s*\d+)/", $flights);
        $noConfirmation = 0;
        foreach ($segments as $i => $stext) {
            $s = $f->addSegment();

            $date = strtotime($this->normalizeDate($this->re("/\[\d+\]\s+(" . $this->t("dateFormat") . ")/", $stext)));

            if (preg_match("/\[\d+\]\s+" . $this->t("dateFormat") . "\s+(?<name>.*?)\s*(?<number>\d+)/", $stext, $m)) {
                $s->airline()
                    ->name($m['name'])
                    ->number($m['number']);

                if (preg_match("/" . $m['name'] . "\s*" . $m['number'] . "\s+(?<DepName>.*?)[ ]*\((?<DepTime>\d+[:：]\d+)\)\s+-\s+(?<ArrName>.*?)[ ]*\((?<ArrTime>\d+[:：]\d+)\)/", $stext, $matches)) {
                    $s->departure()
                        ->name($matches['DepName'])
                        ->date(strtotime($matches['DepTime'], $date))
                        ->noCode();
                    $s->arrival()
                        ->name($matches['ArrName'])
                        ->date(strtotime($matches['ArrTime'], $date))
                        ->noCode();
                }
            }

            $cabin = $this->re("/\d{1,2}[:：]\d{1,2}.*\n+[> ]*({$this->opt($this->t('cabinVariants'))})(?:[ ]{2}|\s*$)/mu", $stext);
            $s->extra()->cabin($cabin, false, true);

            if (preg_match("/(?:^\s*|[ ]{2})({$this->opt($this->t('Reservation Number'))})[: ]*([A-Z\d]{5,}|\d{4,})\s*$/m", $stext, $m)) {
                if (preg_match('/^[A-Z\d]{5,}$/', $m[2])) {
                    // 3OF42S
                    $s->airline()->confirmation($m[2]);
                } elseif (count($segments) === 1) {
                    // 0115
                    $f->general()->confirmation($m[2], $m[1]);
                } elseif ($s->getFlightNumber()) {
                    $f->general()->confirmation($m[2], $m[1] . ' (flight ' . $s->getFlightNumber() . ')');
                }
            } elseif (!preg_match("/^(?:.*\n){2,4}\W([A-Z\d]{5,}|\d{4,})\s*(?:\n|$)/", $stext, $m)) {
                $noConfirmation++;
            }


            $fnum = $this->re("/\[(\d+)\]\s+(" . $this->t("dateFormat") . ")/", $stext);

            if (!empty($fnum) && isset($seats[(int) $fnum])) {
                $s->extra()->seats($seats[(int) $fnum]);
            }
        }

        if (empty($f->getConfirmationNumbers()) && count($f->getSegments()) === $noConfirmation) {
            $f->general()
                ->noConfirmation();
        }

        if (!empty($seatsOneFlight) && count($f->getSegments()) === 1 && empty($f->getSegments()[0]->getSeats())) {
            $f->getSegments()[0]->extra()->seats($seatsOneFlight);
        }
    }

    private function cutText(string $start = '', array $ends = [], string $text = '')
    {
        if (!empty($start) && 0 < count($ends) && !empty($text)) {
            foreach ($ends as $end) {
                if (($cuttedText = stristr(stristr($text, $start), $end,
                        true)) && is_string($cuttedText) && 0 < strlen($cuttedText)) {
                    break;
                }
            }

            return substr($cuttedText, 0);
        }

        return null;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($str)
    {
        $year = date("Y", $this->date);
        $in = [
            "#^[^\d\s]+,\s+(\d+)\s+([^\d\s]+)\s*\(\w+\)$#u", //Friday, 20 May
            "#^[^\d\s]+,\s+(\d+)\s+([^\d\s]+)\s+-\s+[^\d\s]+,\s+\d+\s+[^\d\s]+$#", //Friday, 20 May - Saturday, 21 May
            "#^([^\d\s]+)\s+(\d+)\s+(\d{4})\s*\(\w+\)$#", //Aug 10 2018
            "#^\s*(\d+)\s*月\s*(\d+)\s*日\s*\([^\d\s]+\)\s*$#", //1月 3日(日)
            "#^\s*(\d{4})年\s*(\d+)\s*月\s*(\d+)\s*日\s*\([^\d\s]+\)\s*$#", //2018年7月11日(水)
            "#^\s*(\w+)\s+(\d+)\s*\([^\d\s]+\)\s*$#u", //Aug 2(Wed)
        ];
        $out = [
            "$1 $2 $year",
            "$1 $2 $year",
            "$2 $1 $3",
            "$2.$1.$year",
            "$3.$2.$1",
            "$2 $1 $year",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return $str;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
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
}
