<?php

namespace AwardWallet\Engine\ana\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class It3820135 extends \TAccountChecker
{
    public $mailFiles = "ana/it-10154023.eml, ana/it-13771904.eml, ana/it-13866178.eml, ana/it-13892425.eml, ana/it-13912149.eml, ana/it-1596625.eml, ana/it-1758867.eml, ana/it-18868415.eml, ana/it-1908500.eml, ana/it-1928854.eml, ana/it-20072845.eml, ana/it-2010792.eml, ana/it-2023902.eml, ana/it-20277717.eml, ana/it-21878794.eml, ana/it-23632344.eml, ana/it-3912718.eml, ana/it-4530721.eml, ana/it-4546645.eml, ana/it-4563827.eml, ana/it-4726720.eml, ana/it-56871512.eml, ana/it-57046715.eml, ana/it-57071899.eml, ana/it-57388125.eml, ana/it-6504063.eml, ana/it-67022760.eml, ana/it-7962728.eml, ana/it-9975971.eml";

    public $langDetectors = [
        "fr" => ["ANA vous remercie de votre confiance."],
        "ja" => ["ANA予約番号", "いつもANAをご利用いただきありがとうございます。", "ANAインターネット予約サービスをご利用いただきありがとうございます"],
        "zh" => ["衷心感谢您使用ANA"],
        "de" => ["Vielen Dank, dass Sie mit ANA fliegen"],
        "th" => ["ขอขอบพระคุณอย่างสูงที่ท่านเลือกบินกับ ANA"],
        "en" => ["Thank you so much for flying with ANA", "Thank you for your continued support of ANA", "Thank you for using ANA"],
        "id" => ["memilih terbang bersama ANA"],
    ];

    public static $dictionary = [
        "en" => [
            //            "ANA\s+Reservation\s+Number" => "",
            // "Reservation number" => "",
            //            "Sehr geehrte(r)" => "", //not regexp
            "Passenger" => "(?:Passenger Name|Passenger)",
            // "Flight Information" => "",
            //			"Departure terminal:" => "",
            //            "DEP\." => "",
            //            "ARR\." => "",
            //            "Seat\s+number" => "",
            "OK"               => "(?:OK|Waitlisted)",
            "Required mileage" => "Required\s*mileage\s+(\d[\d,.]*\s*miles)", //with value
            //            "Total\s+price" => "",
            "Request" => "We are sending this email to you on behalf of a request made by",
        ],
        "fr" => [
            "ANA\s+Reservation\s+Number"=> "Numéro de réservation ANA",
            // "Reservation number" => "",
            //            "Sehr geehrte(r)" => "", //not regexp
            "Passenger"          => "Nom du passager",
            "Flight Information" => "Informations sur le vol",
            //			"Departure terminal:" => "",
            "DEP\."        => "Dép\.",
            "ARR\."        => "Arr\.",
            "Seat\s+number"=> "Numéro de siège",
            "OK"           => "OK",
            //			"Required mileage"=>"",
            "Total\s+price"=> "Prix total",
        ],
        "ja" => [
            "ANA\s+Reservation\s+Number"=> "ANA予約番号",
            "Reservation number"        => "予約番号",
            //            "Sehr geehrte(r)" => "", //not regexp
            "Passenger"           => ["搭乗者名", "搭乗者", "ご搭乗者名"],
            "Flight Information"  => ["フライト", "便情報"],
            "Departure terminal:" => "出発ターミナル：",
            "DEP\."               => ["発", "出发"],
            "ARR\."               => ["着", "到达"],
            "Seat\s+number"       => "座席番号",
            "OK"                  => "OK",
            "Total\s+price"       => ["お支払い総額", "運賃額", '支付总额'],
            "Required mileage"    => "(?:必要マイル数|使用マイル数)\s+([\d\,\.]+マイル)",
            "Flight time"         => "飛行時間",
            "Change reservation"  => "変更後のご予約内容は以下の通りです",
            "No confirmed"        => "予約の確認、座席指定はこちらからお手続きください。",
        ],
        "zh" => [
            "ANA\s+Reservation\s+Number"=> "ANA预约编号",
            // "Reservation number" => "",
            //            "Sehr geehrte(r)" => "", //not regexp
            "Passenger"          => ["搭乗者名", "搭乗者", "搭乘者姓名"],
            "Flight Information" => "航班",
            //            "Departure terminal:" => "",
            "DEP\."            => ["発", "出发"],
            "ARR\."            => ["着", "到达"],
            "Seat\s+number"    => "座席番号",
            "OK"               => "OK",
            "Total\s+price"    => ["お支払い総額", "運賃額", '支付总额'],
            "Required mileage" => "(?:必要マイル数|使用マイル数)\s+([\d\,\.]+マイル)",
            "Flight time"      => "飛行時間",
            //            "Change reservation" => "",
            //            "No confirmed" => "",
        ],
        "de" => [
            "ANA\s+Reservation\s+Number"=> "ANA-Buchungsnummer",
            // "Reservation number" => "",
            //            "Sehr geehrte(r)" => "", //not regexp
            "Passenger"          => "Passagiername",
            "Flight Information" => "Fluginformation",
            //			"Departure terminal:" => "",
            "DEP\."        => "Abflug",
            "ARR\."        => "Ankunft",
            "Seat\s+number"=> "Sitzplatz",
            "OK"           => "OK",
            //			"Required mileage"=>"",
            "Total\s+price"=> "Gesamtpreis",
        ],
        "th" => [
            "ANA\s+Reservation\s+Number"=> " หมายเลขการสำรองที่นั่งของ ANA",
            // "Reservation number" => "",
            //            "Sehr geehrte(r)" => "", //not regexp
            "Passenger"          => "ชื่อผู้โดยสาร",
            "Flight Information" => "ข้อมูลเที่ยวบิน",
            //			"Departure terminal:" => "",
            "DEP\."        => "ออกเดินทาง",
            "ARR\."        => "ถึงปลายทาง",
            "Seat\s+number"=> "หมายเลขที่นั่ง",
            "OK"           => "OK",
            //			"Required mileage"=>"",
            "Total\s+price"    => "ราคารวม",
            "Required mileage" => "[\d\,]+マイル",
        ],
        "id" => [
            "ANA\s+Reservation\s+Number"=> "Nomor\s+Reservasi\s+ANA",
            // "Reservation number" => "",
            //            "Sehr geehrte(r)" => "", //not regexp
            //            "Passenger" => "",
            "Flight Information" => "Informasi Penerbangan",
            //			"Departure terminal:" => "",
            "DEP\."        => "KEB\.",
            "ARR\."        => "KED\.",
            "Seat\s+number"=> "Nomor\s+kursi",
            "OK"           => "OK",
            //			"Required mileage"=>"",
            //			"Total\s+price"=>"",
        ],
    ];

    public $lang = "";
    private $date;

    public function parseEmail(Email $email): void
    {
        $patterns = [
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        ];

        $text = strip_tags($this->http->Response["body"]);
        $text = str_replace(['&#12288;', '　'], ' ', $text);

        $flight = $email->add()->flight();

        $confirmation = $this->re("#{$this->t("ANA\s+Reservation\s+Number")}\s+([A-Z\d]{5,7})\b#", $text)
            ?? $this->re("#\S\&REC_LOC=(?-i)([A-Z\d]{5,6})[\n\r]+#i", $text)
            ?? $this->re("#^[□\s]*{$this->opt($this->t("Reservation number"))}[:\s]+(?-i)([A-Z\d]{5,7})\s*$#im", $text);

        if (!empty($confirmation)) {
            $flight->general()
                ->confirmation($confirmation);
        }

        //it-4530721.eml
        if (empty($confirmation) & !empty($this->re("/({$this->t('Change reservation')})/", $text))) {
            $flight->general()->noConfirmation();
        }
        //it-4546645.eml
        if (empty($confirmation) & !empty($this->re("/({$this->t('Request')})/", $text))) {
            $flight->general()->noConfirmation();
        }
        //it-4726720.eml
        if (empty($confirmation) & !empty($this->re("/({$this->t('No confirmed')})/", $text))) {
            $flight->general()->noConfirmation();
        }

        if (empty($confirmation) && preg_match("/\n\s*- *({$this->opt($this->t("Flight Information"))})\n/", $text)
            && !preg_match("/\n- *.+\s*\n *([A-Z\d]{5,7})\n[\s\S]+?\n\s*- *{$this->opt($this->t("Flight Information"))}\n/", $text)
        ) {
            $flight->general()->noConfirmation();
        }

        // TotalCharge
        $total = $this->re("#" . $this->opt($this->t("Total\s+price")) . "\s+\D*(\d[\d,. ]+)#", $text);

        if (!empty($total)) {
            switch ($this->lang) {
                case 'ja':
                case 'zh':
                case 'fr':
                case 'en':
                case 'th':
                    $total = str_replace(',', '', $total);

                    break;
            }

            if (is_numeric($total)) {
                $flight->price()
                    ->total((float) $total);
            }
        }

        // Currency
        $currency = $this->re("#" . $this->opt($this->t("Total\s+price")) . "(?:[ ]*\(after change\))?\s+(?:\d[\d,. ]+)?[ ]*([^\d\s]+)#", $text);

        if (!empty($currency)) {
            $sym = [
                '円'          => 'JPY',
                '€'          => 'EUR',
                'เงินบาทไทย' => 'THB',
                '人民币'        => 'CNY',
            ];

            foreach ($sym as $f=>$r) {
                if (strpos($currency, $f) !== false) {
                    $currency = $r;

                    break;
                }
            }
            $flight->price()
                ->currency($this->re("#^([A-Z]{3})$#", trim($currency)));
        }

        // SpentAwards
        $spentAwards = $this->re("/{$this->t("Required mileage")}/u", $text);

        if (!empty($spentAwards)) {
            $flight->price()
                ->spentAwards($spentAwards);
        }

        preg_match_all("#^\s*(\[(\d+)\].*?)\n\s*\n#msi", $text, $segments, PREG_PATTERN_ORDER);

        if (empty($segments[1])) {
            $this->logger->info("empty segments");

            return;
        }

        if (strpos($segments[1][0], '[2]') !== false & in_array($this->lang, ['ja', 'zh'])) {
            preg_match_all("#^\s*(\[(\d+)\].*?)\n\s*\n?#msi", $text, $segments, PREG_PATTERN_ORDER);
        }

        if (strpos($segments[1][0], '[2]') !== false & !in_array($this->lang, ['ja', 'zh'])) {
            preg_match_all("#(\[(\d+)\].+\n.+\n.+\n)(?:!\[(\d+)\])?#u", $text, $segments, PREG_PATTERN_ORDER);
        }

        foreach ($segments[1] as $key => $segment) {
            if (
                strpos($segment, "Travel using other methods of transportation") !== false
                || mb_strpos($segment, "別の交通手段で移動") !== false
            ) {
                continue;
            }
            $date = $this->normalizeDate($this->re("#\[\d+\]\s*(.*?)\s+[A-Z\d]{2}\d+(?:.+)?[\n\r]+#", $segment));

            $seg = $flight->addSegment();

            // Airline
            $name = $this->re("#\[\d+\].*\s+([A-Z][A-Z\d]|[A-Z\d][A-Z])\d+(?:\(.\))?[\n\r]+#", $segment);
            $number = $this->re("#\[\d+\].*\s+(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])(\d+)(?:\(.\))?[\n\r]+#", $segment);

            if (!empty($name) & !empty($number)) {
                $seg->airline()
                    ->number($number)
                    ->name($name);
            }

            $operatedBy = $this->re("#{$this->opt($this->t('Code share'))}\s*\-\s*(.+)#", $segment);

            if (!empty($operatedBy)) {
                $seg->airline()
                    ->operator($operatedBy);
            }

            // Departure
            $depName = $this->re("#\[\d+\].*\s*[\n\r]+\s*(.*?)\s*-\s*(.*?)\s*(?:[\n\r]+|$)#", $segment);

            if (!empty($depName) & !empty($depCode = $this->re('/\(([A-Z]{3})\)/', $depName))) {
                $seg->departure()
                    ->name($this->re('/(\D+)\(/', $depName))
                    ->code($depCode);
            } elseif (!empty($depName)) {
                $seg->departure()
                    ->name($depName)
                    ->noCode();
            }

            $dt = $this->re("#" . $this->t("Departure terminal:") . "[ ]*(.*)#", $segment);

            if (!empty($dt)) {
                $seg->departure()
                    ->terminal($dt);
            }

            $depTime = $this->re("#" . $this->opt($this->t("DEP\.")) . "\s+(\d+:\d+)#", $segment);

            if (in_array($this->lang, ['ja', 'zh'])) {
                $depTime = $this->re("#(\d+:\d+)" . $this->opt($this->t("DEP\.")) . "#", $segment);
            }

            if (!empty($depTime)) {
                $seg->departure()
                    ->date(strtotime($depTime, $date));
            }

            if (empty($depDate) && !empty($date) && empty($this->re("#\D(\d{1,2}:\d{2})(?:\D|$)#", $segment))) {
                $seg->departure()
                    ->day($date)
                    ->noDate()
                ;
            }

            //Arrival
            $arrName = $this->re("#\[\d+\].*\s*[\n\r]+\s*(.*?)\s*-\s*(.*?)\s*(?:[\n\r]+|$)#", $segment, 2);

            if (!empty($arrName) & !empty($arrCode = $this->re('/\(([A-Z]{3})\)/', $arrName))) {
                $seg->arrival()
                    ->name($this->re('/(\D+)\(/', $arrName))
                    ->code($arrCode);
            } elseif (!empty($arrName)) {
                $seg->arrival()
                    ->name($arrName)
                    ->noCode();
            }

            $arrTime = $this->re("#" . $this->opt($this->t("ARR\.")) . "(?:\s+)?(\d+:\d+)#", $segment);

            if (in_array($this->lang, ['ja', 'zh'])) {
                $arrTime = $this->re("#(\d+:\d+)" . $this->opt($this->t("ARR\.")) . "#", $segment);
            }

            if (!empty($arrTime)) {
                $arrDate = strtotime($arrTime, $date);

                if (preg_match("#\(\s*([+-]\d+)\D#", $segment, $d)) {
                    $arrDate = strtotime($d[1] . " day", $arrDate);
                }

                if (in_array($this->lang, ['ja', 'zh'])) {
                    if (preg_match("#翌日#", $segment)) {
                        $arrDate = strtotime("+1 day", $arrDate);
                    }
                }
                $seg->arrival()
                    ->date($arrDate);
            }

            if (empty($depDate) && empty($arrDate) && !empty($date) && empty($this->re("#\D(\d{1,2}:\d{2})(?:\D|$)#", $segment))) {
                $seg->arrival()
                    ->noDate()
                ;
            }

            //Duration
            $duration = $this->re("/{$this->t('Flight time')}\s*\:\s(.+)\./", $segment);

            if (empty($duration)) {
                $duration = $this->re("/{$this->t('Flight time')}\s?\s?(?:[:：]+)?\s?(.+)\.?/", $segment);
            }

            if (!empty($duration)) {
                $seg->extra()
                    ->duration($duration);
            }

            // Cabin
            // BookingClass
            if (preg_match("/[\n\r]+\s*(\w+)(?:[:：]+(\w{1,2}))?\s*\/\s*{$this->t("OK")}/u", $segment, $matches)) {
                $seg->extra()
                    ->cabin($matches[1]);

                if (!empty($matches[2])) {
                    $seg->extra()
                        ->bookingCode($matches[2]);
                }
            }

            if (preg_match("/[\n\r]+\s*(\w+)(?:ー：(\w)\w+)?(?:[:：]+(\w{1,2}))?\s*\/\s*{$this->t("OK")}/u", $segment, $matches)) {
                $seg->extra()
                    ->cabin($matches[1]);

                if (!empty($matches[2])) {
                    $seg->extra()
                        ->bookingCode($matches[2]);
                }
            }

            if (preg_match("/[\n\r]+\s*([\w\s?]+)\:?([A-Z]?)(?:(\sclass))?(?:\(([A-Z\d]+)\))?\s*\-\s+{$this->t("OK")}/u", $segment, $matches)) {
                if (!empty($matches[3])) {
                    $seg->extra()
                        ->cabin($matches[1] . $matches[3])
                        ->bookingCode($matches[2]);
                } else {
                    $seg->extra()
                        ->cabin($matches[1]);
                }
                /*if (!empty($matches[4]))
                    $seg->airline()->confirmation($matches[4]);*/
            }

            // Seats
            $seg->extra()
                ->seats(array_filter(explode(",", $this->re("#" . $this->t("Seat\s+number") . "\s*\W\s*([^\n]*?)\s*[\n\r]+#u", $segment))));

            // if lang "ja" + segment single line.
            if (preg_match("#^\[\d+\]\s*(?<date>.*?)\s+(?<flightName>[A-Z]+)\s+(?<flightNumber>\d+)\s+(?<depName>.+)\((?<depTime>[\d\:]+)\)\s+\-\s+(?<arrName>.+)\((?<arrTime>[\d\:]+)\)\s+#u", $segment, $m)) {
                $seg->airline()
                    ->name($m['flightName'])
                    ->number($m['flightNumber']);

                $seg->departure()
                    ->date($this->normalizeDate($m['date'] . ' ' . $m['depTime']))
                    ->name($m['depName'])
                    ->noCode();

                $seg->arrival()
                    ->date($this->normalizeDate($m['date'] . ' ' . $m['arrTime']))
                    ->name($m['arrName'])
                    ->noCode();
            }
        }

        // Passengers

        $passengers = [];
        $passengersText = $this->re("/{$this->opt($this->t("Passenger"))}(?:\s*\n+\s*)+([\s\S]{2,}?)(?:\s*\n+\s*)+{$this->opt($this->t("Flight Information"))}/", $text);
        $passengersRows = preg_split("/([ ]*\n+[ ]*)+/", $passengersText);

        foreach ($passengersRows as $pRow) {
            if (preg_match("/^{$patterns['travellerName']}$/u", $pRow)) {
                $passengers[] = $pRow;
            } else {
                $passengers = [];
            }
        }

        if (count($passengers) === 0
            && preg_match_all("#^\s*([A-Z][[:alpha:]\- ]+? (?:" . implode("|", ["MR", "MS", "MISS", "DR", "MSTR"]) . "))\s*$#um", $text, $pass)
            && !empty($pass[1])
        ) {
            $passengers = array_map("trim", $pass[1]);
        }

        if (empty($passengers)) {
            preg_match_all("#\n *(?:" . implode("|", ["MR\.", "MR ", "MS\.", "MISS\.", "DR\.", "MSTR\."]) . ") ?([\D\n\r]+)\n#u", $text, $pass);

            if (!empty($pass[1])) {
                $passengers = array_map("trim", $pass[1]);
            }
        }

        if (empty($passengers)) {
            $passengers = array_filter(array_map("trim", explode("\n", $this->re("#\n[^\w\s]*\s*" . $this->opt($this->t("Passenger")) . "\s*\n\s*(.*?)\n\s*\n#msu", $text))));
        }

        if (empty($passengers)) {
            $passText = $this->re("/[A-Z\d]{5,7}\s+(.+)\s+\[1\]/us", $text);

            if (!empty($passText) & preg_match_all("/\w+ \w+( [ -]\w+)?/u", $passText, $travellerMatches)) {
                if (count($travellerMatches[0]) === count($flight->getSegments()[0]->getSeats())) {
                    $flight->general()->travellers($travellerMatches[0]);
                }
            }
        }

        if (empty($passengers)) {
            preg_match_all("#^\s*([A-Z][A-Z\- ]+? ?(?:" . implode("|", ["MR", "MS", "MISS", "DR", "MSTR"]) . "))\s*$#um", $text, $pass);
            $passengers = $pass[1];
        }

        $passengers = preg_replace('/\s+(MR|MS|MRS|MISS|MSTR|DR)\s*$/i', '', $passengers);

        if (!empty($passengers)) {
            $flight->general()
                ->travellers($passengers);
        }
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $textBody = $parser->getPlainBody();

        if (strpos($textBody, 'ANA') === false) {
            return false;
        }

        if (!empty($this->assignLang($textBody))) {
            return true;
        }

        if (!empty($textBody)) {
            $text2 = iconv('utf-8', 'windows-1251//IGNORE', $textBody);
            $text2 = iconv("UTF-8", "UTF-8//IGNORE", $text2);

            if (!empty($this->assignLang($text2))) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getHeader('date'));
        $this->http->FilterHTML = false;

        $textBody = $parser->getPlainBody();
        $this->lang = $this->assignLang($textBody);

        if (empty($this->lang)) {
            $textBody = iconv('utf-8', 'windows-1251//IGNORE', $textBody);
            $textBody = iconv("UTF-8", "UTF-8//IGNORE", $textBody);
            $this->lang = $this->assignLang($textBody);
        }
        $textBody = preg_replace(['/^([>]+ )+/m', '/[<]+(\w[-,.!?\[\])(\w ]{0,55}\w)[>]+/u'], ['', '$1'], $textBody);
        $this->http->SetEmailBody($textBody);

        $this->parseEmail($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    private function normalizeDate($str)
    {
        $year = date("Y", $this->date);
        $in = [
            "#^\s*([^\s\d.,]+)\.\,?\s*([^\s\d.,]+)\.?\,?\s+(\d+),$#u", //THU., JUN. 30,
            "#^\s*([^\s,.]+)\.?, (\d+) ([^\s\d.,]+)\.?$#u", //Mer., 02 Août
            "#^\s*(\d+)\s*月\s*(\d+)\s*日\s*\(\s*([^\s\d.,]+)\s*\)\s*$#u", //2月18日(日)
            "#^\s*([^\s\d]+) (\d+) ([^\s\d]+)$#u", //อา. 16 ก.ย.
            "#^\w+\.\s*(\w+)\.\,\s*(\d+)\,\s+(\d{4})$#u", //FRI. DEC., 27, 2013
            "#^(\d+)月(\d+)日\(([^\s\d.]+)\)\s+([\d\:]+)$#u", // 8月10日(水) 17:25 //ja
        ];
        $out = [
            "$1, $3 $2 $year",
            "$1, $2 $3 $year",
            "$3, $year-$1-$2",
            "$1, $2 $3 $year",
            "$2 $1 $3",
            "$3, $year-$1-$2 $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        if (preg_match("#^(?<week>[\w\.]+), (?<date>\d+ \w+ .+|\d+-\d+-.+)#u", $str, $m)) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($m['week'], $this->lang));
            $str = EmailDateHelper::parseDateUsingWeekDay($m['date'], $weeknum);
        } elseif (preg_match("#\b\d{4}\b#", $str)) {
            $str = strtotime($str);
        } else {
            $str = null;
        }

        return $str;
    }

    private function assignLang($text = '')
    {
        foreach ($this->langDetectors as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if ($this->http->XPath->query('//node()[contains(normalize-space(.),"' . $phrase . '")]')->length > 0 || strpos($text, $phrase) !== false) {
                    return $lang;
                }
            }
        }

        return false;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function re($re, $str, $c = 1): ?string
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

        return '(?:' . implode("|", $field) . ')';
    }
}
