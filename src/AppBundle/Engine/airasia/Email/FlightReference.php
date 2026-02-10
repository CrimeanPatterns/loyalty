<?php

namespace AwardWallet\Engine\airasia\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Common\Flight;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class FlightReference extends \TAccountChecker
{
    public $mailFiles = "airasia/it-190664377.eml, airasia/it-191762375.eml, airasia/it-247512719.eml, airasia/it-302365123.eml, airasia/it-314825291.eml, airasia/it-522798651.eml, airasia/it-525525352.eml, airasia/it-525684542.eml, airasia/it-708045796.eml";
    public $subjects = [
        // en, th, ko, ja
        '(CONFIRMED) Reference No:',
        '(CONFIRMED) Booking No:',
    ];

    public $detectBody = [
        'en' => ['Yay! Pack up because your booking is confirmed!'],
        'th' => ['โฮ่ โฮ่ โฮ่! เตรียมแพ็กกระเป๋าได้เลย บุ๊คกิ้งของคุณได้รับการยืนยันแล้ว!', 'เย้! แพ็คเพราะการจองของคุณได้รับการยืนยัน!', 'เย้! การจองได้รับการยืนยันแล้ว จัดกระเป๋าเลย!'],
        'zh' => ['预订已确认'],
        'ko' => ['예! 예약이 확인되었으므로 포장하십시오!'],
        'ja' => ['わーい！予約が確認されているため、梱包してください！'],
        'ms' => ['Yay! Kemas kerana tempahan anda telah disahkan!'],
        'vi' => ['Yay! Đóng gói vì đặt phòng của bạn được xác nhận!'],
        'id' => ['Yay! Kemasi karena pemesanan Anda dikonfirmasi!'],
    ];
    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'Reference no.' => ['Reference no.', 'Booking number', 'Booking no.'],
            'Guest details' => 'Guest details',
            //            'E-ticket no.' => '',
            'routeName'       => ['Depart:', 'Return:'],
            //            'Operated By:' => '',
            //            'Base fare' => '',
            //            'Total amount paid' => '',
            //'Seat' => '',
            // 'Terminal' => '',
            'durationRe' => '[\dhm\s\:]+',
        ],
        "th" => [
            'Reference no.' => ['หมายเลขอ้างอิง', 'หมายเลขการสำรองที่นั่ง'],
            'Guest details' => 'รายละเอียดผู้โดยสาร',
            //            'E-ticket no.' => '',
            'routeName'       => ['ขาไป:', 'ขากลับ:'],
            //            'Operated By:' => '',
            'Base fare'         => 'ค่าโดยสาร',
            'Total amount paid' => 'จำนวนเงินทั้งหมด',
            //'Route' => '',
            //'Seat' => '',
            'Terminal'   => 'อาคาร',
            'durationRe' => '\d+ชม\.\s*\d+นาที',
        ],
        "id" => [
            'Reference no.' => ['Nomor pemesanan'],
            'Guest details' => 'Rincian Tamu',
            //            'E-ticket no.' => '',
            'routeName'       => ['Keberangkatan:', 'Kembali:'],
            //            'Operated By:' => '',
            //'Base fare'         => '',
            'Total amount paid' => 'Jumlah total',
            //'Route' => '',
            //'Seat' => '',
            //'Terminal' => '',
            'durationRe' => '\d+jam\s*[\dmenit]*',
        ],
        "zh" => [
            'Reference no.' => ['预订编号', '預訂號碼'],
            'Guest details' => ['乘客', '乘客資料'],
            //            'E-ticket no.' => '',
            'routeName'       => ['出发:', '出發:', '回程:'],
            //            'Operated By:' => '',
            'Base fare'         => '基础费',
            'Total amount paid' => ['支付总额', '總金額'],
            //'Route' => '',
            'Seat'       => '座位',
            'Terminal'   => ['航廈', '航站楼'],
            'durationRe' => '\d+小时\s*\d+分钟|\d+小時\s+\d+分鐘|\d+:\d+',
        ],
        "ko" => [
            'Reference no.' => ['예약번호'],
            'Guest details' => '고객 정보',
            //            'E-ticket no.' => '',
            'routeName'       => ['가는 날:'],
            //            'Operated By:' => '',
            // 'Base fare'         => '',
            'Total amount paid' => '총액',
            //'Route' => '',
            'Seat'       => '좌석',
            'Terminal'   => '터미널',
            'durationRe' => '[\d시 분]+',
        ],
        "ja" => [
            'Reference no.' => ['予約番号'],
            'Guest details' => 'ゲスト情報',
            //            'E-ticket no.' => '',
            'routeName'       => ['往路:', '復路:'],
            //            'Operated By:' => '',
            // 'Base fare'         => '',
            'Total amount paid' => '合計金額',
            //'Route' => '',
            'Seat'       => '座席',
            'Terminal'   => 'ターミナル',
            'durationRe' => '[\d 時間分]+',
        ],
        "ms" => [
            'Reference no.' => ['Nombor tempahan'],
            'Guest details' => 'Butiran Tetamu',
            //            'E-ticket no.' => '',
            'routeName'       => ['Keberangkatan:'],
            //            'Operated By:' => '',
            // 'Base fare'         => '',
            'Total amount paid' => 'Jumlah keseluruhan',
            //'Route' => '',
            // 'Seat' => '座席',
            'Terminal'   => 'Terminal',
            'durationRe' => '\d+j\s*\d+m',
        ],
        "vi" => [
            'Reference no.' => ['Mã số đặt vé'],
            'Guest details' => 'Thông Tin Hành Khách',
            //            'E-ticket no.' => '',
            'routeName'       => ['Khởi hành:'],
            //            'Operated By:' => '',
            // 'Base fare'         => '',
            //'Route' => '',
            // 'Seat' => '座席',
            'Terminal'          => 'Nhà ga',
            'durationRe'        => '\d+giờ\s*\d+phút',
            'Total amount paid' => 'Tổng số tiền',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@booking.airasia.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (stripos($headers['subject'], $subject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'AirAsia Com Travel')] | //a[contains(@href, '.airasia.com')]")->length === 0) {
            return false;
        }

        foreach ($this->detectBody as $dBody) {
            if ($this->http->XPath->query("//text()[{$this->contains($dBody)}]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]airasia\.com$/', $from) > 0;
    }

    public function ParseFlight(Email $email)
    {
        $f = $email->add()->flight();

        $conf = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Reference no.'))}]/following::text()[normalize-space()][1]/ancestor::h3[1]", null, true, "/^(\d{10,}|[A-Z\d]{5,7})$/");

        if (empty($conf)) {
            $conf = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Reference no.'))}]/following::text()[normalize-space()][1]", null, true, "/^(\d{10,}|[A-Z\d]{5,7})$/");
        }

        $f->general()
            ->confirmation($conf)
            ->travellers($this->http->FindNodes("//text()[{$this->eq($this->t('Guest details'))}]/ancestor::table[1]/descendant::text()[contains(normalize-space(), '(')]/preceding::text()[normalize-space()][1]"), true);

        $tickets = array_filter($this->http->FindNodes("//text()[{$this->eq($this->t('Guest details'))}]/ancestor::table[1]/descendant::text()[{$this->eq($this->t('E-ticket no.'))}]/following::text()[normalize-space()][1]", null, "/^([\d\, ]{8,})$/u"));
        $ticketArray = [];

        foreach ($tickets as $ticket) {
            if (stripos($ticket, ',') !== false) {
                $array = explode(',', $ticket);
                $ticketArray = array_merge($ticketArray, $array);
            } else {
                $ticketArray[] = $ticket;
            }
        }

        $f->setTicketNumbers(str_replace(' ', '', array_unique($ticketArray)), false);

        $xpath = "//text()[{$this->starts($this->t('routeName'))}]/following::img[contains(@src, 'baseline_local_airport_black_24dp.png')]";
        //$this->logger->debug('$xpath = ' . print_r($xpath, true));
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length > 0) {
            $this->ParseSegment1($nodes, $f);
        } else {
            $xpath = "//text()[{$this->starts($this->t('routeName'))}]/following::img[contains(@src, 'airline.png')]";
            //$this->logger->debug('$xpath = ' . print_r($xpath, true));
            $nodes = $this->http->XPath->query($xpath);
            $this->ParseSegment2($nodes, $f);
        }

        $tickets = array_filter(array_unique($this->http->FindNodes("//tr[contains(normalize-space(), 'eTicket no.') and contains(normalize-space(), 'Route')]/following::table[1]/descendant::tr/descendant::td[normalize-space()][3]", null, "/^([\d\-]{10,})$/")));

        if (count($tickets) == 0) {
            $tickets = array_filter(array_unique($this->http->FindNodes("//tr[contains(normalize-space(), 'eTicket no.') and contains(normalize-space(), 'Route')]/following::table[1]/descendant::tr/descendant::td[normalize-space()][2]", null, "/^([\d\-]{10,})$/")));
        }

        if (count($tickets) > 0) {
            $f->setTicketNumbers($tickets, false);
        }

        $priceText = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Total amount paid'))}]/ancestor::tr[1]/descendant::td[2]");

        if (preg_match("/^\s*(?<currency>[A-Z]{3})\s*(?<total>[\d\.\,]+)\s*$/su", $priceText, $m)) {
            $f->price()
                ->total(PriceHelper::parse($m['total'], $m['currency']))
                ->currency($m['currency']);

            $cost = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Base fare'))}]/ancestor::tr[1]/descendant::td[2]", null, true, "/^\s*[A-Z]{3}\s*([\d\.\,]+)/");

            if (!empty($cost)) {
                $f->price()
                    ->cost($cost);
            }

            $feeNodes = $this->http->XPath->query("//text()[{$this->eq($this->t('Base fare'))}]/ancestor::tr[1]/following-sibling::tr[normalize-space()][not({$this->contains($this->t('Total amount paid'))})]");

            foreach ($feeNodes as $feeRoot) {
                $feeName = $this->http->FindSingleNode("./descendant::text()[normalize-space()][1]", $feeRoot);
                $feeSum = $this->http->FindSingleNode("./descendant::td[normalize-space()][2]", $feeRoot, true, "/^\s*[A-Z]{3}\s*([\d\.\,]+)/");
                $f->price()
                    ->fee($feeName, $feeSum);
            }
        }
    }

    public function ParseSegment1(\DOMNodeList $nodes, Flight $f)
    {
        $this->logger->debug(__METHOD__);

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $airlineInfo = $this->http->FindSingleNode("./ancestor::tr[1]/following::tr[1]", $root);

            if (preg_match("/^(?<duration>{$this->t('durationRe')})\s+.+\,\s+(?<airName>[A-Z\d]{2})\s+(?<flightNumber>\d{1,5})\s*(?<cabin>\D+?)(?:\s*{$this->opt($this->t('Operated By:'))}\s*(?<operator>.+))?\s*$/u", $airlineInfo, $m)) {
                $s->airline()
                    ->name($m['airName'])
                    ->number($m['flightNumber']);

                if (!empty($m['operator'])) {
                    if (preg_match("/.*,\s*(?<al>[A-Z\d][A-Z]|[A-Z][A-Z\d])\s*(?<fn>\d{1,5})\s*$/", $m['operator'], $mat)) {
                        // Scoot, TR 281
                        $s->airline()
                            ->carrierName($mat['al'])
                            ->carrierNumber($mat['fn']);
                    }
                }

                $s->extra()
                    ->cabin($m['cabin'])
                    ->duration($m['duration']);
            }

            $date = $this->http->FindSingleNode("./preceding::text()[{$this->starts($this->t('routeName'))}][1]", $root, true, "/{$this->opt($this->t('routeName'))}\s*(.+)/u");
            $depTime = $this->http->FindSingleNode("./ancestor::tr[1]/descendant::td[1]", $root, true, "/^\s*([\d\:]+)\s*/");

            if (!empty($date) && !empty($depTime)) {
                $s->departure()
                    ->date($this->normalizeDate($date . ', ' . $depTime));
            }

            $code = null;

            if (!empty($depTime)) {
                $code = $this->http->FindSingleNode("./preceding::text()[starts-with(normalize-space(), '{$depTime}')][2]/ancestor::td[1]",
                    $root, true, "/{$depTime}\s*([A-Z]{3})/");
            }

            if (empty($code)) {
                $depName = $this->http->FindSingleNode("./ancestor::tr[1]/descendant::td[normalize-space()][2]/descendant::text()[normalize-space()][1]", $root);

                if (!empty($depName)) {
                    $s->departure()
                        ->noCode()
                        ->name($depName);
                }
            } else {
                $s->departure()
                    ->code($code);
            }

            $arrTime = $this->http->FindSingleNode("./following::img[contains(@src, 'baseline_trip_origin_black_24') or contains(@src, 'baseline_place_black_24dp.png')][1]/ancestor::tr[1]/descendant::td[1]", $root, true, "/^\s*([\d\:]+)\s*/");

            if (!empty($date) && !empty($arrTime)) {
                $s->arrival()
                    ->date($this->normalizeDate($date . ', ' . $arrTime));
            }

            $arrCode = null;

            if (!empty($arrTime)) {
                $arrCode = $this->http->FindSingleNode("./preceding::text()[starts-with(normalize-space(), '{$arrTime}')][1]/ancestor::td[1]", $root, true, "/{$arrTime}\s*([A-Z]{3})/");
            }

            if (empty($arrCode)) {
                $arrName = $this->http->FindSingleNode("./following::img[contains(@src, 'baseline_trip_origin_black_24') or contains(@src, 'baseline_place_black_24dp.png')][1]/ancestor::tr[1]/descendant::td[normalize-space()][2]/descendant::text()[normalize-space()][1]", $root);

                $s->arrival()
                    ->noCode()
                    ->name($arrName);
            } else {
                $s->arrival()
                    ->code($arrCode);
            }
        }
    }

    public function ParseSegment2(\DOMNodeList $nodes, Flight $f)
    {
        $this->logger->debug(__METHOD__);

        $seats = '';
        $seatsNodes = $this->http->XPath->query("//text()[{$this->starts($this->t('Seat'))}]/ancestor::div[2]/preceding-sibling::div[contains(., ' to ')]");

        if ($seatsNodes->length === 0) {
            $seatsNodes = $this->http->XPath->query("//text()[{$this->starts($this->t('Seat'))}]/ancestor::ul[1]/preceding::text()[contains(., ' to ')][1]");
        }

        foreach ($seatsNodes as $sRoot) {
            $route = $this->http->FindSingleNode(".", $sRoot);
            $routeSeats = implode(', ', array_filter($this->http->FindNodes("following-sibling::div[1]//text()[{$this->starts($this->t('Seat'))}]",
                $sRoot, "/^\s*{$this->opt($this->t('Seat'))}\s+(\d{1,3}[A-Z])\s*$/")));

            if (preg_match("/^[\s\,]*$/", $routeSeats)) {
                $routeSeats = implode(', ', array_filter($this->http->FindNodes("./following::ul[1]//text()[{$this->starts($this->t('Seat'))}]",
                $sRoot, "/^\s*{$this->opt($this->t('Seat'))}\s+(\d{1,3}[A-Z])\s*$/su")));
            }
            $seats .= "\n" . $route . ':(' . $routeSeats . ')';
        }

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $airlineInfo = $this->http->FindSingleNode("./ancestor::tr[2]", $root);

            if (preg_match("/^(?<duration>{$this->t('durationRe')})\s+.+\,\s*(?<airName>[A-Z\d]{2})\s+(?<flightNumber>\d{1,5})\s*(?<cabin>\D+?)(?:\s*{$this->opt($this->t('Operated By:'))}\s*(?<operator>.+))?\s*$/u", $airlineInfo, $m)) {
                $s->airline()
                    ->name($m['airName'])
                    ->number($m['flightNumber']);

                if (!empty($m['operator'])) {
                    if (preg_match("/.*,\s*(?<al>[A-Z\d][A-Z]|[A-Z][A-Z\d])\s*(?<fn>\d{1,5})\s*$/", $m['operator'], $mat)) {
                        // Scoot, TR 281
                        $s->airline()
                            ->carrierName($mat['al'])
                            ->carrierNumber($mat['fn']);
                    } elseif (preg_match("/^(\D+)$/", $m['operator'], $mat)) {
                        //Batik Air
                        $s->airline()
                            ->operator($mat[1]);
                    }
                }

                $s->extra()
                    ->cabin($m['cabin'])
                    ->duration($m['duration']);
            }

            $date = str_replace(',', '', $this->http->FindSingleNode("./preceding::text()[{$this->starts($this->t('routeName'))}][contains(normalize-space(), ',')][1]", $root, true, "/{$this->opt($this->t('routeName'))}\s*(.*\d{4}.*)/u"));
            $depTime = $this->http->FindSingleNode("./preceding::tr[1]/descendant::td[1]/descendant::text()[normalize-space()][1]", $root, true, "/^\s*([\d\:]+)\s*/");

            if (!empty($date) && !empty($depTime)) {
                $s->departure()
                    ->date($this->normalizeDate($date . ', ' . $depTime));
            }

            $code = null;

            if (!empty($depTime)) {
                $code = $this->http->FindSingleNode("./preceding::text()[starts-with(normalize-space(), '{$depTime}')][2]/ancestor::td[1]",
                    $root, true, "/{$depTime}\s*([A-Z]{3})/");
            }

            if (empty($code)) {
                $s->departure()
                    ->noCode();
            } else {
                $s->departure()
                    ->code($code);
            }

            $depName = implode(', ', $this->http->FindNodes("./preceding::tr[normalize-space()][2]/descendant::p[position() = 3 or position() = 4]", $root));

            if (preg_match("/^(?:\d+\s*\w+)?[\s\,]+$/", $depName)) {
                $depName = implode(', ', $this->http->FindNodes("./preceding::tr[normalize-space()][2]/descendant::p[not(starts-with(translate(normalize-space(.),'0123456789','dddddddddd--'),'d'))][normalize-space()]", $root));
            }

            if (preg_match("/^\s*(.+?)\s*,\s*([^,]*\b{$this->opt($this->t('Terminal'))}[^,]*)$/ui", $depName, $m)) {
                $depName = $m[1];
                $s->departure()
                    ->terminal(preg_replace("/\s*{$this->opt($this->t('Terminal'))}\s*/iu", '', $m[2]));
            }

            if (!empty($depName)) {
                $s->departure()
                    ->name($depName);
            }

            $arrTime = $this->http->FindSingleNode("./following::text()[contains(normalize-space(), ':')][not({$this->contains($this->t('Operated By:'))})][1]", $root, true, "/^\s*([\d\:]+)\s*/");

            if (!empty($date) && !empty($arrTime)) {
                $s->arrival()
                    ->date($this->normalizeDate($date . ', ' . $arrTime));
            }

            $arrCode = null;

            if (!empty($arrTime)) {
                $arrCode = $this->http->FindSingleNode("./preceding::text()[starts-with(normalize-space(), '{$arrTime}')][1]/ancestor::td[1]", $root, true, "/{$arrTime}\s*([A-Z]{3})/");
            }

            if (empty($arrCode)) {
                $s->arrival()
                    ->noCode();
            } else {
                $s->arrival()
                    ->code($arrCode);
            }

            $arrName = implode(', ', $this->http->FindNodes("./following::tr[normalize-space()][2]/descendant::p[position() = 3 or position() = 4]", $root));

            if (preg_match("/^(?:\d+\s*\w+)?[\s\,]+$/", $arrName)) {
                $arrName = implode(', ', $this->http->FindNodes("./following::tr[normalize-space()][2]/descendant::p[not(starts-with(translate(normalize-space(.),'0123456789','dddddddddd--'),'d'))][normalize-space()]", $root));
            }

            if (preg_match("/^\s*(.+?), ([^,]*{$this->opt($this->t('Terminal'))}[^,]*)$/i", $arrName, $m)) {
                $arrName = $m[1];
                $s->arrival()
                    ->terminal(preg_replace("/\s*{$this->opt($this->t('Terminal'))}\s*/i", '', $m[2]));
            }

            if (!empty($arrName)) {
                $s->arrival()
                    ->name($arrName);
            }

            if (!empty($seats) && !empty($s->getDepName()) && !empty($s->getArrDate())) {
                if (
                    preg_match("/^\s*" . strstr($s->getDepName(), ',', true) . "(?: - .+?| City)? to " . strstr($s->getArrName(), ' - ', true) . "(?: - .+?| City)? *:\((.+)\)/m", $seats, $m)
                    || preg_match("/^\s*" . strstr($s->getDepName(), ',', true) . "(?: - .+?| City)? to " . strstr($s->getArrName(), ',', true) . "(?: - .+?| City)? *:\((.+)\)/m", $seats, $m)
                ) {
                    $seatsArray = explode(',', $m[1]);

                    foreach ($seatsArray as $seat) {
                        $pax = $this->http->FindSingleNode("//text()[{$this->contains($seat)}]/preceding::text()[string-length()>2][not(contains(normalize-space(), 'baggage'))][1]");

                        if (!empty($pax)) {
                            $s->extra()
                                ->seat($seat, false, false, $pax);
                        } else {
                            $s->extra()
                                ->seat($seat);
                        }
                    }
                }
            }

            if (empty($s->getDepCode()) && empty($s->getArrCode()) && empty($s->getAirlineName()) && empty($s->getFlightNumber())) {
                $f->removeSegment($s);
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict['Guest details']) && $this->http->XPath->query("//text()[{$this->contains($dict['Guest details'])}]")->length > 0) {
                $this->lang = $lang;

                break;
            }
        }

        $this->ParseFlight($email);

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

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "starts-with(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) { return "normalize-space(.)=\"{$s}\""; }, $field));
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function normalizeDate($date)
    {
        // $this->logger->debug('$date = '.print_r( $date,true));

        $in = [
            // 18 ม.ค. 2023, 21:50
            // 星期四 28 九月 2023, 16:55
            // วันพฤหัสบดี 19 ตุลาคม 2023, 10:50
            // Thứ sáu 1 tháng mười hai 2023, 13:20
            '/^\s*(?:\D+[,.\s]+)?(\d+)[, ]+([[:alpha:]\p{Thai}.]+|tháng [[:alpha:] ]+?),? (\d{4})[, ]+(\d+:\d+(?:\s*[ap]m)?)\s*$/ui',
            // 목요일 28 9월 2023, 13:20
            // 火曜日 29 8月 2023, 14:15
            '/^\s*\w+\s+(\d{1,2})\s+(\d{1,2})\s*[월月]\s*(\d{4})[, ]+(\d+:\d+(?:\s*[ap]m)?)\s*$/ui',
        ];
        $out = [
            '$1 $2 $3, $4',
            '$3-$2-$1, $4',
        ];
        $date = preg_replace($in, $out, $date);
        // $this->logger->debug('$date = '.print_r( $date,true));
        if (preg_match('#\d\s+([[:alpha:]\.\p{Thai}]+|tháng [[:alpha:] ]+?)\s+\d{4}#iu', $date, $m)) {
            $monthNameOriginal = $m[1];

            if ($translatedMonthName = \AwardWallet\Engine\MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                $date = preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return strtotime($date);
    }
}
