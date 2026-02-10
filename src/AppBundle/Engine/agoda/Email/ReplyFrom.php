<?php

namespace AwardWallet\Engine\agoda\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class ReplyFrom extends \TAccountChecker
{
    public $mailFiles = "agoda/it-17479947.eml, agoda/it-18659218.eml, agoda/it-18827458.eml, agoda/it-19981625.eml, agoda/it-20411770.eml, agoda/it-20588325.eml, agoda/it-66912104.eml, agoda/it-70141587.eml, agoda/it-70257797.eml, agoda/it-70334943.eml, agoda/it-71435886.eml, agoda/it-74374701.eml, agoda/it-77292364.eml"; // +1 bcdtravel(html)[ja]

    public $reFrom = "agoda-messaging.com";

    public $reSubject = [
        '/Reply from .+? \([[:alpha:]]+ \d+-\d+, \d{4}|[[:alpha:]]+ \d+ - [[:alpha:]]+ \d+, \d{4}\)/u',
        '/Notification from .+? \([[:alpha:]]+ \d+-\d+, \d{4}|[[:alpha:]]+ \d+ - [[:alpha:]]+ \d+, \d{4}\)/u',
        // Inquiry sent to K&K Studio - Best sunrise view from city centre (Dec 23-25, 2020)
        '/Inquiry sent to .+? \([[:alpha:]]+ \d+-\d+, \d{4}\)/u',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
            'totalPrice' => 'Total Price',
            //            'room' => '',
            'adults'           => ['adults', 'adult'],
            'children'         => ['children', 'child'],
            'statusPhrases'    => ['Your booking is', 'Your booking has been'],
            'statusVariants'   => ['confirmed', 'cancelled', 'canceled'],
            'confNumber'       => ['Your booking ID is', 'Booking ID:', 'Booking ID :'],
            'Hello'            => 'Hi,',
            'cancelledPhrases' => [
                'Your booking has been cancelled at your request',
                'Your booking has been canceled at your request',
            ],
            'Book now' => 'Book now',
            //            'Here is the address:' => '',
            //            'or' => '',
        ],
        'es' => [
            'totalPrice'     => 'Precio final',
            'room'           => 'habitación',
            'adults'         => 'adultos',
            'children'       => 'niños',
            'statusPhrases'  => 'Tu reserva está',
            'statusVariants' => 'confirmada',
            'confNumber'     => 'Tu número de reserva es',
            'Hello'          => 'Hola',
            //            'cancelledPhrases' => '',
            //            'Book now' => '',
            //            'Here is the address:' => '',
            //            'or' => '',
        ],
        'nl' => [
            'totalPrice'     => 'Totaalprijs',
            'room'           => 'kamer',
            'adults'         => 'volwassenen',
            'children'       => 'kinderen',
            'statusPhrases'  => 'Uw boeking is',
            'statusVariants' => 'bevestigd',
            'confNumber'     => 'Uw boekings-ID is',
            'Hello'          => 'Hallo,',
            //            'cancelledPhrases' => '',
            //            'Book now' => '',
            //            'Here is the address:' => '',
            //            'or' => '',
        ],
        'zh' => [
            'totalPrice'       => ['总价', '總金額', '總價格'],
            'room'             => ['间客房', '間房', '間客房'],
            'adults'           => ['名大人', '位大人', '位成人'],
            'children'         => ['名儿童', '位兒童', '位小童'],
            'statusPhrases'    => ['您的预订已', '你的訂單已獲得', '你的預訂已'],
            'statusVariants'   => ['确认', '確認', '經成功取消囉'],
            'confNumber'       => ['预订编码是', '訂單編號為', '訂單編號:', '訂單編號 :', '預訂編號為', '預訂編號:'],
            'Hello'            => '您好',
            'cancelledPhrases' => '你的預訂已經成功取消囉',
            //            'Book now' => '',
            'Here is the address:' => '住宿地址如下：',
                        'or' => '或',
        ],
        'he' => [
            'totalPrice' => 'מחיר כולל',
            'room'       => ['חֶדֶר', 'חדר'],
            'adults'     => 'מבוגרים',
            'children'   => 'ילדים',
            //            'statusPhrases' => '',
            //            'statusVariants' => '',
            'confNumber' => 'מספר ההזמנה שלכם הוא',
            'Hello'      => 'היי',
            //            'cancelledPhrases' => '',
            //            'Book now' => '',
            //            'Here is the address:' => '',
            //            'or' => '',
        ],
        'ja' => [
            'totalPrice' => '合計金額',
            'room'       => '部屋',
            'adults'     => '大人',
            'children'   => '子ども',
            //            'statusPhrases' => '',
            //            'statusVariants' => '',
            'confNumber' => '予約ID',
            //            'Hello' => '',
            //            'cancelledPhrases' => '',
            //            'Book now' => '',
            //            'Here is the address:' => '',
            //            'or' => '',
        ],
        'de' => [
            'totalPrice'                     => 'Gesamtpreis',
            'room'                           => 'Zimmer',
            'adults'                         => 'Erwachsener',
            'children'                       => 'Kinder',
            'statusPhrases'                  => 'Ihre Buchung wurde',
            'statusVariants'                 => 'bestätigt',
            'confNumber'                     => 'Ihre Buchungs-ID lautet:',
            'The address of the property is' => 'Die Adresse der Unterkunft lautet',
            'Hello'                          => 'Hallo',
            //            'cancelledPhrases' => '',
            //            'Book now' => '',
            //            'Here is the address:' => '',
            //            'or' => '',
        ],
        'th' => [
            'totalPrice'     => 'ราคารวม',
            'room'           => 'ห้อง',
            'adults'         => 'ผู้ใหญ่',
            'children'       => 'เด็ก',
            'statusPhrases'  => 'การจองห้องพักของท่าน',
            'statusVariants' => 'ได้รับการยืนยัน',
            'confNumber'     => ['หมายเลขการจอง คือ', 'หมายเลขการจอง:', 'หมายเลขการจอง :'],
            //            'The address of the property is' => '',
            'Hello' => 'สวัสดีค่ะ คุณ',
            //            'cancelledPhrases' => '',
            //            'Book now' => '',
            //            'Here is the address:' => '',
            //            'or' => '',
        ],
        'pt' => [
            'totalPrice'     => 'Preço total',
            'room'           => 'quarto',
            'adults'         => ['adultos', 'adulto'],
            'children'       => ['crianças'],
            'statusPhrases'  => ['A sua reserva foi'],
            'statusVariants' => ['confirmada'],
            'confNumber'     => ['O seu número de reserva é'],
            'Hello'          => 'Olá,',
            //            'cancelledPhrases' => [
            //                '',
            //            ],
            //            'Book now' => '',
            'Here is the address:' => 'Eis o endereço:',
            'or'                   => 'ou',
        ],
        'id' => [
            'totalPrice'     => 'Harga Total',
            'room'           => 'kamar',
            'adults'         => ['dewasa'],
            'children'       => ['anak'],
            'statusPhrases'  => ['Pesanan Anda telah'],
            'statusVariants' => ['dikonfirmasi'],
            'confNumber'     => ['ID Pesanan Anda adalah', 'ID Pesanan:'],
            'Hello'          => 'Halo',
            //            'cancelledPhrases' => [
            //                '',
            //            ],
            //            'Book now' => '',
            'Here is the address:' => 'Berikut adalah alamat properti:',
            'or'                   => 'atau',
        ],
        'ko' => [
            'totalPrice'     => '총 결제 금액',
            'room'           => '객실',
            'adults'         => ['성인'],
            'children'       => ['아동'],
            'statusPhrases'  => ['고객님의 예약이'],
            'statusVariants' => ['확정되었습니다'],
            'confNumber'     => ['예약 번호:'],
            'Hello'          => '님, 안녕하세요.',
            //            'cancelledPhrases' => [
            //                '',
            //            ],
            'Book now' => '지금 예약하기',
            'Here is the address:' => '- 숙소 주소/위치 :',
            'or'                   => '/',
        ],
        'vi' => [
            'totalPrice'     => 'Tổng số tiền',
            'room'           => 'phòng',
            'adults'         => ['người lớn'],
            'children'       => ['trẻ em'],
            'statusPhrases'  => ['Yêu cầu của bạn đã được'],
            'statusVariants' => ['xác nhận'],
            'confNumber'     => ['Mã số đặt phòng của bạn là'],
            'Hello'          => 'Xin chào,',
            //            'cancelledPhrases' => [
            //                '',
            //            ],
            //            'Book now' => '',
            'Here is the address:' => 'Đây là địa chỉ:',
            'or'                   => ', hoặc',
        ],
        'fr' => [
            'totalPrice'     => 'Montant total',
            'room'           => 'chambre',
            'adults'         => ['adultes'],
            'children'       => ['enfants'],
            'statusPhrases'  => ['Votre réservation est'],
            'statusVariants' => ['confirmée'],
            'confNumber'     => ['Numéro de réservation:'],
            'Hello'          => 'Bonjour',
            //            'cancelledPhrases' => [
            //                '',
            //            ],
            //            'Book now' => '',
            //            'Here is the address:' => '',
            //            'or' => '',
        ],
        'it' => [
            'totalPrice'     => 'Prezzo Totale',
            'room'           => 'camera',
            'adults'         => ['adulti'],
            'children'       => ['bambini'],
            'statusPhrases'  => ['La prenotazione è'],
            'statusVariants' => ['confermata'],
            'confNumber'     => ['(booking ID) è'],
            'Hello'          => 'Ciao',
            //            'cancelledPhrases' => [
            //                '',
            //            ],
            //            'Book now' => '',
            //            'Here is the address:' => '',
            //            'or' => '',
        ],
        'el' => [
            'totalPrice'     => 'Συνολική τιμή',
            'room'           => 'δωμάτιο',
            'adults'         => ['ενήλικες'],
            'children'       => ['παιδιά'],
            'statusPhrases'  => ['Η κράτησή σας'],
            'statusVariants' => ['επιβεβαιώθηκε'],
            'confNumber'     => ['Ο αριθμός της κράτησής σας είναι'],
            'Hello'          => 'Γεια σας,',
            //            'cancelledPhrases' => [
            //                '',
            //            ],
            //            'Book now' => '',
            //            'Here is the address:' => '',
            //            'or' => '',
        ],
        'da' => [
            'totalPrice'     => 'Samlet pris',
            'room'           => 'værelse',
            'adults'         => ['voksne'],
            'children'       => ['børn'],
            'statusPhrases'  => ['Din reservation er blevet'],
            'statusVariants' => ['bekræftet'],
            'confNumber'     => ['Dit reservations-ID er'],
            'Hello'          => 'Hej',
            //            'cancelledPhrases' => [
            //                '',
            //            ],
            //            'Book now' => '',
            'Here is the address:' => 'Her er adressen:',
            'or' => 'eller',
        ],
    ];
    private $subject;
    private $year;

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->year = date('Y', strtotime($parser->getDate()));
        $this->subject = $parser->getSubject();

        if (!$this->assignLang()) {
            $this->logger->debug('can\'t determine a language');

            return $email;
        }

        $a = explode('\\', __CLASS__);
        $class = end($a);
        $email->setType($class . ucfirst($this->lang));

        if ($this->http->XPath->query("//a[{$this->eq($this->t('Book now'))}]")->length > 0) {
            $email->setIsJunk(true);
        } else {
            if ($this->parseEmail($email)) {
                return $email;
            }
        }

        return null;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//img[@alt='Agoda.com']")->length > 0) {
            return $this->assignLang();
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && isset($headers['subject']) && stripos($headers['from'],
                $this->reFrom) !== false && isset($this->reSubject)
        ) {
            foreach ($this->reSubject as $reSubject) {
                if (preg_match($reSubject, $headers["subject"])) {
                    return true;
                }
            }
        }

        return false;
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

    private function parseEmail(Email $email): bool
    {
        $roots = $this->http->XPath->query("//text()[{$this->eq($this->t('totalPrice'))}]/ancestor::table[.//img[contains(@src,'star') and contains(@src,'.png')]][1]");

        if ($roots->length !== 1) {
            // it-70257797.eml
            $xpathHotelImage = "starts-with(normalize-space(@width),'90') and starts-with(normalize-space(@height),'90') or contains(@src,'90x90')";
            $roots = $this->http->XPath->query("//tr[ *[normalize-space()='' and descendant::img/ancestor-or-self::*[{$xpathHotelImage}] and count(following-sibling::*[normalize-space()])=1] ]/ancestor::table[{$this->contains($this->t('adults'))} or {$this->contains($this->t('children'))}][1]");
        }

        if ($roots->length !== 1) {
            $this->logger->debug('other format');

            return false;
        }
        $root = $roots->item(0);
        $r = $email->add()->hotel();

        if ($this->http->XPath->query("//*[{$this->contains($this->t('cancelledPhrases'))}]")->length > 0) {
            // it-70257797.eml
            $r->general()->cancelled();
        }

        $status = $this->http->FindSingleNode("//text()[{$this->contains($this->t('statusPhrases'))}]", null, true, "/{$this->opt($this->t('statusPhrases'))}\s*({$this->opt($this->t('statusVariants'))})(?:\s*[，,.:;!?！]|\s*at you|\s*แล้ว|$)/iu");

        if ($status) {
            $r->general()->status($status);
        } else {
            if (!empty($this->http->FindSingleNode(".//text()[{$this->contains($this->t('adults'))}]/following::img[1][contains(@src, 'status_icon@2x/x-icon@2x.png')]/@src"))) {
                $r->general()->cancelled();
            }
        }

        $confirmation = $this->http->FindSingleNode("//text()[{$this->eq($this->t('totalPrice'))}]/following::text()[{$this->contains($this->t('confNumber'))}][1]", null, false, "/{$this->opt($this->t('confNumber'))}[: ]*[【】]*([A-Z\d]{5,})/")
            ?? $this->http->FindSingleNode('//*[@id="bookingId"]', null, false, "/{$this->opt($this->t('confNumber'))}[: ]*[【】]*([A-Z\d]{5,})/");
        $r->general()->confirmation($confirmation);

        if ($this->http->XPath->query("descendant::text()[{$this->eq($this->t('totalPrice'))}]", $root)->length > 0) {
            $currency = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('totalPrice'))}]/following::text()[normalize-space()][1]", $root, false, "#^[A-Z]{3}$#");
            $amount = $this->normalizeAmount($this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('totalPrice'))}]/following::text()[normalize-space()][2]", $root, true, "/^[\d\s.,]*\d[\d\s.,]*$/"));

            if (empty($currency) && empty($currency)) {
                $currency = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('totalPrice'))}]/preceding::text()[normalize-space()][2]", $root, false, "#^[A-Z]{3}$#");
                $amount = $this->normalizeAmount($this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('totalPrice'))}]/preceding::text()[normalize-space()][1]", $root, true, "/^([\d\s.,]*\d[\d\s.,]*)\*?$/"));
            }

            $r->price()
                ->currency($currency)
                ->total($amount);
        }

        $address = $this->http->FindSingleNode("//text()[{$this->starts($this->t('The address of the property is'))}]", null, true, '/\"(.+)\"/u');

        if (empty($address)) {
            $address = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Here is the address:'))}]", null,
                true,
                '/' . $this->opt($this->t("Here is the address:")) . '\s*(.+)' . $this->opt($this->t("or")) . '\s+' . preg_quote('https://www.google.com/maps', '/') . '/u');
        }

        if (empty($address)) {
            $address = $this->http->FindSingleNode("descendant::text()[normalize-space()][2]", $root);
        }
        $r->hotel()
            ->name($this->http->FindSingleNode("descendant::text()[normalize-space()][1]", $root))
            ->address($address);

        $patterns['travellerName'] = '[[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]]';

        $traveller = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Hello'))}]", null, true, "/^{$this->opt($this->t('Hello'))}\s+({$patterns['travellerName']}[.]{0,1})(?:\s*[，,:;!?]|$)/u")
            ?? $this->http->FindSingleNode("descendant::text()[{$this->contains($this->t('Hello'))}][1]", null, true, "/^({$patterns['travellerName']})\s*{$this->opt($this->t('Hello'))}/u");

        if (!empty($traveller)) {
            $r->general()->traveller($traveller);
        }

        $arr = array_filter(array_map("trim", explode(",", $address)));

        if (count($arr) === 2) {
            $d = $r->hotel()->detailed();
            $d->city($arr[0])
                ->country($arr[1]);
        }
        $node = $this->http->FindSingleNode("descendant::text()[{$this->contains($this->t('adults'))}]/preceding::text()[normalize-space()][1][contains(.,'|')]", $root);

        if (empty($node)) {
            // it-74374701.eml
            $node = $this->http->FindSingleNode("descendant::text()[{$this->contains($this->t('adults'))}][contains(.,'|')]", $root, true, "/^[^\|]*\|[^\|]*\|[^\|]*\|[^\|]*$/");
        }

        if (empty($node)) {
            // it-18827458.eml
            if (preg_match("#\( *(.+?\d{4}) *\)#", $this->subject, $mm)
                && preg_match("#(\w+) (\d+)\-(\d+), (\d{4})#u", $mm[1], $m)
            ) {
                $r->booked()
                    ->checkIn(strtotime($this->dateStringToEnglish($m[2] . ' ' . $m[1] . ' ' . $m[4])))
                    ->checkOut(strtotime($this->dateStringToEnglish($m[3] . ' ' . $m[1] . ' ' . $m[4])));
            }
        } else {
            // it-17479947.eml, it-18659218.eml, it-19981625.eml, it-20411770.eml, it-20588325.eml
            $arr = array_filter(array_map("trim", explode("|", $node)));
//            $this->logger->debug('$arr = '.print_r( $arr,true));

            if (count($arr) === 4) {
                // it-18827458.eml
                $cnt = $this->re("#(\d+) *{$this->opt($this->t('room'))}#", $arr[2]);

                if (!empty($cnt)) {
                    $a = [$arr[0], $arr[2], $arr[1]];
                    $arr = $a;
                }
            }

            if (count($arr) !== 3) {
                $this->logger->debug('other format description');
            }
            $cnt = $this->re("#(\d+) *{$this->opt($this->t('room'))}#", $arr[1]);

            if (!empty($cnt)) {
                $r->booked()->rooms($cnt);
            }
            $s = $r->addRoom();
            $s->setType($arr[0]);

            if (
                preg_match("/^(?<m1>[.[:alpha:]]{3,})\s+(?<d1>\d{1,2})\s*-\s*(?<d2>\d{1,2})\s*,\s*(?<y1>\d{2,4})$/u", $arr[2], $dates)
                || preg_match("/^(?<m1>[.[:alpha:]]{3,})\s+(?<d1>\d{1,2})\s*-\s*(?<m2>[.[:alpha:]]{3,})\s+(?<d2>\d{1,2})\s*,\s*(?<y1>\d{2,4})$/u", $arr[2], $dates)
                || preg_match("/^(.+?)\s+-\s+(.+)$/", $arr[2], $dates)
            ) {
                // 02-jul - 03-jul-2018    |    Nov 25-26, 2017    |    Dec 26 - Dec 27, 2019
                if (!empty($dates['d2'])) {
                    $dates[1] = $dates['d1'] . '-' . $dates['m1'] . '-' . $dates['y1'];
                    $dates[2] = $dates['d2'] . '-' . ($dates['m2'] ?? $dates['m1']) . '-' . $dates['y1'];
                } else {
                    if (!preg_match("/\d{4}/", $dates[1]) && preg_match("/.+?([^\d[:alpha:]]*\d{4})\s*$/u", $dates[2], $m)) {
                        if (preg_match("/(.+?)([^\d[:alpha:]]*)$/u", $dates[1], $dm1)
                            && preg_match("/^" . preg_quote($dm1[2], '/u') . "/", $m[1])
                        ) {
                            $dates[1] = $dm1[1];
                        }
                        $dates[1] .= $m[1];
                    } elseif (!preg_match("/\d{4}/", $dates[1]) && preg_match("/^\s*(\d{4}[^\d[:alpha:]]*年?).+/u", $dates[2], $m)) {
                        if (preg_match("/^([^\d[:alpha:]]*)(.+)/u", $dates[1], $dm1)
                            && preg_match("/.+?" . preg_quote($dm1[1], '$/u') . "/", $m[1])
                        ) {
                            $dates[1] = $dm1[2];
                        }
                        $dates[1] = $m[1] . $dates[1];
                    }
                }

                $this->year = $this->re("/\b(\d{4})\b/", $dates[2]);

                if ($this->lang === 'th' && $this->year > 2500) {
                    // it-70141587.eml
                    $incorrectYear = $this->year;
                    $this->year -= 543;
                    $dates[1] = preg_replace("/\b{$incorrectYear}\b/", $this->year, $dates[1]);
                    $dates[2] = preg_replace("/\b{$incorrectYear}\b/", $this->year, $dates[2]);
                }
                $r->booked()
                    ->checkIn($this->normalizeDate($dates[1]))
                    ->checkOut($this->normalizeDate($dates[2]));
            }
        }
        $guests = $this->http->FindSingleNode("descendant::text()[{$this->contains($this->t('adults'))}]", $root);

        $r->booked()
            ->guests($this->re("#\b(\d{1,3}) *{$this->opt($this->t('adults'))}#", $guests) ?? $this->re("#{$this->opt($this->t('adults'))} *(\d{1,3})\b#", $guests))
            ->kids($this->re("#\b(\d{1,3}) *{$this->opt($this->t('children'))}#", $guests) ?? $this->re("#{$this->opt($this->t('children'))} *(\d{1,3})\b#", $guests));

        return true;
    }

    private function normalizeDate($date)
    {
//        $this->logger->debug('date = '.$date);
        $in = [
            //29-Sep-2018
            '/^(\d{1,2})[-\s\/]+([.[:alpha:]]+),?[-\s\/.]+(\d{4})$/u',
            //18/07/2018; 29-11-2020
            '#^(\d{1,2})[\-\/](\d{1,2})[\-\/](\d{4})$#',
            //2018-1-21    |    2019/01/19    |     2020. 11. 27(korean)
            '#^(\d{4})(?:[-\/]|\. )(\d{1,2})(?:[-\/]|\. )(\d{1,2})$#',
            //2020年11月4日
            '#^\s*(\d{4})\s*年\s*(\d{1,2})\s*月\s*(\d{1,2})\s*日\s*$#u',
        ];
        $out = [
            '$1 $2 $3',
            '$3-$2-$1',
            '$1-$2-$3',
            '$1-$2-$3',
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

    private function assignLang(): bool
    {
        if (!isset(self::$dict, $this->lang)) {
            return false;
        }

        foreach (self::$dict as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['adults']) || empty($phrases['confNumber'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[{$this->contains($phrases['adults'])}]")->length > 0
                && ($this->http->XPath->query("//*[{$this->contains($phrases['confNumber'])}]")->length > 0
                    || (!empty($phrases['Book now']) && $this->http->XPath->query("//a[{$this->eq($phrases['Book now'])}]")->length > 0))
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function dateStringToEnglish($date)
    {
        if (preg_match('#[.[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if (!empty($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang))
                || $translatedMonthName = MonthTranslate::translate(trim($monthNameOriginal, '.'), $this->lang)
            ) {
                return preg_replace("#" . preg_quote($monthNameOriginal) . "#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    /**
     * Formatting over 3 steps:
     * 11 507.00  ->  11507.00
     * 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
     * 18800,00   ->  18800.00  |  2777,0    ->  2777.0.
     *
     * @param string|null $s Unformatted string with amount
     * @param string|null $decimals Symbols floating-point when non-standard decimals (example: 1,258.943)
     */
    private function normalizeAmount(?string $s, ?string $decimals = null): ?float
    {
        if (!empty($decimals) && preg_match_all('/(?:\d+|' . preg_quote($decimals, '/') . ')/', $s, $m)) {
            $s = implode('', $m[0]);

            if ($decimals !== '.') {
                $s = str_replace($decimals, '.', $s);
            }
        } else {
            $s = preg_replace('/\s+/', '', $s);
            $s = preg_replace('/[,.\'](\d{3})/', '$1', $s);
            $s = preg_replace('/,(\d{1,2})$/', '.$1', $s);
        }

        return is_numeric($s) ? (float) $s : null;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'normalize-space(' . $node . ')=' . $s;
        }, $field)) . ')';
    }

    private function starts($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'starts-with(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'contains(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }
}
