<?php

namespace AwardWallet\Engine\ctrip\Email;

use AwardWallet\Schema\Parser\Email\Email;

class BookingAtHotel extends \TAccountChecker
{
    public $mailFiles = "ctrip/it-13522219.eml, ctrip/it-173966800.eml, ctrip/it-174897332.eml, ctrip/it-35237713.eml, ctrip/it-51496390.eml, ctrip/it-8085695.eml";
    public $reFrom = ["@ctrip.com", "@trip.com"];
    public $reSubject = [
        "it" => "Prenotazione presso lo",
        "de" => "Buchung im", "hat Ihre Hotelbestätigungsnummer bereitgestellt",
        "zh" => "訂單已確認 -",
        "en" => "Ctrip booking at",
        "pt" => "Reserva em",
        "th" => "การจองโรงแรมของคุณได้รับการยืนยันแล้ว",
        "fr" => "Votre réservation est confirmée !",
    ];

    public $reBody2 = [
        'pt'   => 'Sua reserva foi confirmada',
        'pt2'  => 'Sua reserva foi cancelada',
        'pt3'  => 'Sua reserva está aguardando confirmação do hotel',
        'pt4'  => 'Houve falha no pagamento da sua reserva',
        'pt5'  => 'Seu número de confirmação do hotel',
        'it'   => 'La tua prenotazione è stata confermata',
        'de'   => 'Ihre Buchung wurde bestätigt',
        'de2'  => 'Ihre Hotelbestätigungsnummer',
        'zh'   => '您的訂單已確認',
        'zh2'  => '房间已经为您预留',
        'zh3'  => '酒店确认号',
        'th'   => 'การจองโรงแรมของคุณได้รับการยืนยันแล้ว',
        'fr'   => 'Votre réservation est confirmée',
        'en'   => 'Your booking has been confirmed',
        'en2'  => 'Check-in',
        // english language is always the last!
    ];

    public static $dictionary = [
        "en" => [
            "Booking no."                => ["Booking no.", "Booking No.:", "Order No.", "Booking No.", 'Order No'],
            'Hotel Confirmation Number:' => ['Hotel Confirmation Number:', 'hotel confirmation number:', 'Confirmation No.'],
            //			'Check-in & check-out:' => '',
            'Check-in'  => ['Check in', 'Check-in'],
            'Check-out' => ['Check out', 'Check-out'],
            'until'     => 'Until',
            'localTime' => "Hotel's local time",
            //			'Address:' => '',
            'Guest' => ['Guest', 'Guest Names'],
            //			'Your Booking' => '',
            //			'Phone:' => '',
            'room'  => ['room', 'Room'],
            'night' => ['night', 'nights'],
            //			'Room Rate' => '',
            //			'Taxes & Fees' => '',
            'Room Type'            => ['Room Type', 'Room type', 'type'],
            'Cancellation policy:' => ['Cancellation policy:', 'Cancellation Policy'],
            //			'Total price' => '', // for 50%width table
            'Total'         => ['Pay amount', 'Total'],
            'meal'          => ['meal', 'Meal'],
            'Tel'           => ['Telephone:', 'Tel'],
            'cancelledText' => ['Your booking has been cancelled', 'Your booking has been canceled'],
        ],
        "pt" => [
            "Booking no." => ["Nº da reserva:", 'Nº da reserva'],
            //			'Hotel Confirmation Number:' => '',
            //			'Check-in' => '',
            //			'Check-out' => '',
            'until'                 => 'Até',
            'localTime'             => 'Horário local do hotel',
            'Check-in & check-out:' => 'Check-in e check-out',
            //			'Address:' => '',
            //			'Phone:' => '',
            'Guest'                => ['Nome dos hóspedes'],
            'Your Booking'         => 'Sua reserva',
            'room'                 => 'quarto',
            'night'                => 'noites',
            'Room Rate'            => 'Tarifa do quarto',
            'Taxes & Fees'         => 'Impostos e taxas',
            'Room Type'            => 'Tipo de quarto',
            'Cancellation policy:' => ['Política de cancelamento'],
            //			'Total price' => '', // for 50%width table
            'Total'         => 'Total',
            'Discount'      => 'Desconto',
            'cancelledText' => ['Sua reserva foi cancelada', 'Reserva cancelada'],
        ],
        "it" => [
            "Booking no." => ["Prenotazione n.:"],
            //			'Hotel Confirmation Number:' => '',
            //			'Check-in' => '',
            //			'Check-out' => '',
            // 'until' => '',
            // 'localTime' => '',
            'Check-in & check-out:' => 'Check-in e check-out',
            //			'Address:' => '',
            //			'Phone:' => '',
            'Guest'                => ['Nomi degli ospiti'],
            'Your Booking'         => 'La tua prenotazione',
            'room'                 => 'camere',
            'night'                => 'notte',
            'Room Rate'            => 'Tariffa camera',
            'Taxes & Fees'         => 'Costi aggiuntivi (da pagare in hotel)',
            'Room Type'            => 'Tipo di camera',
            'Cancellation policy:' => ['Regolamento e condizioni per la cancellazione'],
            //			'Total price' => '', // for 50%width table
            'Total' => 'Totale',
            // 'cancelledText' => '',
        ],
        "de" => [
            "Booking no."                => ["Buchungsnr.:", "Buchungsnummer."],
            'Hotel Confirmation Number:' => 'Hotelbestätigungsnummer bereitgestellt:',
            //			'Check-in' => '',
            //			'Check-out' => '',
            // 'until' => '',
            // 'localTime' => '',
            'Check-in & check-out:' => 'Check-in und Check-out',
            //			'Address:' => '',
            //			'Phone:' => '',
            'Guest'        => ['Gäste'],
            'Your Booking' => 'Ihre Buchung',
            'room'         => 'Zimmer',
            //			'night' => '',
            'Room Rate' => 'Zimmerpreis',
            //			'Taxes & Fees' => '',
            'Room Type'            => 'Zimmerkategorie',
            'Cancellation policy:' => ['Stornierungsrichtlinien'],
            //			'Total price' => '', // for 50%width table
            'Total' => 'Gesamtbetrag',
            // 'cancelledText' => '',
        ],
        "zh" => [
            "Booking no."                => ["訂單編號:", "訂單編號", "Order No."],
            'Hotel Confirmation Number:' => ['酒店確認編號：', 'Confirmation No.'],
            'Check-in'                   => ['入住時間', '入住日期'],
            'Check-in time'              => '入住时间：',
            'Check-out'                  => ['退房時間', '离店日期'],
            'Check-out time'             => '入住时间：',
            'until'                      => '之前',
            'localTime'                  => '酒店當地時間',
            //			'Check-in & check-out:' => '',
            'Address:' => '地址',
            //			'Phone:' => '',
            'Guest'                => ['住客姓名', '入住人'],
            'Your Booking'         => ['您的訂單'],
            'room'                 => ['間', '间'],
            'night'                => '晚',
            'meal'                 => ['餐食情况'],
            'Room Rate'            => '房價',
            'Taxes & Fees'         => '稅項及附加費',
            'Room Type'            => '房型',
            'Cancellation policy:' => ['取消政策'],
            //			'Total price' => '', // for 50%width table
            'Total' => ['總價', '支付金额', '订单金额'],
            // 'cancelledText' => '',
        ],
        "th" => [
            "Booking no."                => ["หมายเลขการจอง:", 'หมายเลขการจอง'],
            //            'Hotel Confirmation Number:' => 'Hotelbestätigungsnummer bereitgestellt:',
            'Check-in'  => 'เช็คอิน',
            'Check-out' => 'เช็คเอาท์',
            'until'     => 'ก่อน',
            'localTime' => 'ตามเวลาท้องถิ่น',
            //            'Check-in & check-out:' => 'Check-in und Check-out',
            //			'Address:' => '',
            //			'Phone:' => '',
            'Guest'                => ['ชื่อผู้เข้าพัก'],
            'Your Booking'         => ['การจองของคุณ'],
            'room'                 => ['ห้อง'],
            'night'                => 'คืน',
            'Room Rate'            => 'ค่าห้อง',
            'Taxes & Fees'         => 'ภาษีและค่าธรรมเนียม',
            'Discount'             => 'ส่วนลด',
            'Room Type'            => 'ประเภทห้องพัก',
            'Cancellation policy:' => ['นโยบายยกเลิก'],
            //			'Total price' => '', // for 50%width table
            'Total'     => 'ทั้งหมด',
            "Price For" => "ราคาสำหรับ",
            'adul'      => 'คน',
            // 'cancelledText' => '',
        ],
        "fr" => [
            "Booking no." => ["N° réservation"],
            //			'Hotel Confirmation Number:' => '',
            'Check-in'  => 'Enregistrement',
            'Check-out' => "Départ de l'hôtel",
            // 'until' => '',
            // 'localTime' => '',
            //            'Check-in & check-out:' => 'Check-in e check-out',
            //			'Address:' => '',
            //			'Phone:' => '',
            'Guest'                => ['Noms des clients'],
            'Your Booking'         => 'Votre réservation',
            'room'                 => 'chambre',
            'night'                => 'nuit',
            //            'Room Rate'            => 'Tariffa camera',
            //            'Taxes & Fees'         => 'Costi aggiuntivi (da pagare in hotel)',
            'Room Type'            => 'Type de chambre',
            'Cancellation policy:' => ['Politique d\'annulation'],
            //			'Total price' => '', // for 50%width table
            'Total' => 'Total général',
            // 'cancelledText' => '',
        ],
    ];

    public $lang = '';

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
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            if (stripos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,".ctrip.com/") or contains(@href,".trip.com/")]')->length == 0
            && $this->http->XPath->query('//node()[' . $this->contains(["Thank you for choosing Ctrip", "Ctrip All rights reserved", "Ctrip Hotel Reservation Department", "@ctrip.com"]) . ']')->length == 0
            && $this->http->XPath->query('//node()[' . $this->contains(["Thank you for choosing Trip.com", "Trip.com All rights reserved", "@trip.com"]) . ']')->length == 0
        ) {
            return false;
        }

        $body = $parser->getHTMLBody();

        foreach ($this->reBody2 as $re) {
            if (strpos($body, $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->http->FilterHTML = true;

        foreach ($this->reBody2 as $lang=>$re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = substr($lang, 0, 2);
                $this->logger->debug($this->lang);

                break;
            }
        }

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        $this->parseHtml($email);
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

    private function parseHtml(Email $email): void
    {
        $patterns = [
            'date' => '(?:.{4,}\b\d{4}|\d{4}\D+.{3,})', // Sep 8, 2017    |    2019年04月30日
            'time' => '\d{1,2}:\d{2}(?:\s*[AaPp]\.?[Mm]\.?)?', // 4:19PM    |    2:00 p.m.
        ];

        $xpathEmpty = '(normalize-space()="" or normalize-space()=" ")';
        $xpathNoEmpty = '(normalize-space() and normalize-space()!=" ")';

        $r = $email->add()->hotel();

        // TripNumber
        $bookingNo = $this->http->FindSingleNode("//text()[{$this->starts($this->t("Booking no."))}]", null, true, "#{$this->opt($this->t("Booking no."))}\s*([A-Z\d]{5,})$#");

        if (!$bookingNo) {
            $bookingNo = $this->http->FindSingleNode("//text()[{$this->eq($this->t("Booking no."))}]/following::text()[normalize-space()][1]", null, true, "#^[:：]*\s*([A-Z\d]{5,})$#");
        }

        if (!$bookingNo) {
            $bookingNo = $this->http->FindSingleNode("//text()[{$this->contains($this->t("Booking no."))}]/following-sibling::a[1]", null, true, "#^\s*([A-Z\d]{5,})$#");
        }

        if (!$bookingNo) {
            $bookingNo = $this->http->FindSingleNode("//text()[{$this->contains($this->t("Booking no."))}]//ancestor::*[1]", null, true, "#{$this->opt($this->t("Booking no."))}\s*([A-Z\d]{5,})#");
        }

        $r->ota()->confirmation($bookingNo);

        // ConfirmationNumbers
        // ConfirmationNumber
        $confNumbersText = $this->http->FindSingleNode("descendant::p[{$this->contains($this->t('Hotel Confirmation Number:'))}][1]", null, true, "/{$this->opt($this->t('Hotel Confirmation Number:'))}\s*([A-Z\d][A-Z\d, ]{3,}[A-Z\d])\b/u");
        $confNumbers = array_filter(array_unique(preg_split('/\s*,\s*/', $confNumbersText)));

        $confNumbers = array_diff($confNumbers, [$bookingNo]);

        if (count($confNumbers) > 0) {
            foreach ($confNumbers as $confNumber) {
                $r->general()->confirmation(str_replace(" ", "", $confNumber));
            }
        } else {
            $r->general()->noConfirmation();
        }

        if (!empty($this->http->FindSingleNode('(//text()[' . $this->contains($this->t('cancelledText')) . '])[1]'))) {
            $r->general()
                ->status('Cancelled')
                ->cancelled()
            ;
        }
        $xpathHotelLink = $this->contains(['/hotels/detail?hotelid', '_hotels_detail-3Fhotelid', 'hotels.ctrip.com/hotel/'], '@href')
            . 'or ' . $this->contains(['/hotels/detail?hotelid', '_hotels_detail-3Fhotelid', 'hotels.ctrip.com/hotel/'], '@originalsrc');

        // Hotel Name
        $hotelName = $this->http->FindSingleNode("//a[{$xpathHotelLink}]");

        if (empty($hotelName)) {
            $hotelName = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Address:'))}]/ancestor::tr[1]/descendant::td[2]");
        }
        $r->hotel()->name($hotelName);

        // CheckInDate
        $xpathCheckIn = "//tr[ count(*[normalize-space()])=2 and *[not(.//tr) and normalize-space()][1][{$this->starts($this->t('Check-in'))}] ]/*[normalize-space()][2]/descendant::tr[count(*[normalize-space()])>1][1]";

        $checkinDate = $this->http->FindSingleNode($xpathCheckIn . "/*[normalize-space()][1]", null, true, "/^{$patterns['date']}$/u");

        if (!$checkinDate) {
            $checkinDate = $this->http->FindSingleNode("//td[{$this->starts($this->t('Check-in'))}]/following-sibling::td[1]/descendant::text()[normalize-space()][1]");
        }

        if (!$checkinDate) {
            $checkinDate = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Check-in'))}]/following::text()[normalize-space()][1]");
        }

        if (!$checkinDate) {
            $checkinDate = $this->nextText($this->t("Check-in"));
        }

        if ($checkinDate == 'Check-out') {
            $checkinDate = $this->http->FindSingleNode("//text()[normalize-space()='Check-in']/ancestor::table[1]/descendant::text()[normalize-space()][last()]");
        }

        // \x{0E40}-\x{0E4E} - дополнительные символы тайского языка
        $checkinTime = $this->re("#" . $this->opt($this->t('Check-in')) . "\s*:?\s*[[:alpha:]\x{0E40}-\x{0E4E} ]*\s+({$patterns['time']})#ui", $this->nextText($this->t("Check-in & check-out:"))); // it-8085695.eml

        if (!$checkinTime) {
            $checkinTime = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Check-in time'))}]/following::text()[normalize-space()][1]");
        }

        if (!$checkinTime) {
            // it-13522219.eml
            $checkinTime = $this->http->FindSingleNode($xpathCheckIn . "/*[normalize-space()][2]", null, true, "/{$patterns['time']}/");
        }

        if (!$checkinTime) {
            $checkinTime = $this->http->FindSingleNode("//text()[normalize-space()='Check-in']/ancestor::table[1]/descendant::text()[contains(normalize-space(), ':')][1]", null, true, "/^(\d+\:\d+)\s*\-/u");
        }

        if ($checkinDate && $checkinTime) {
            $r->booked()->checkIn(strtotime($this->normalizeDate($checkinDate . ', ' . $checkinTime)));
        } elseif (preg_match("/\d+\:\d+/u", $checkinDate, $m)) {
            $r->booked()->checkIn(strtotime($this->normalizeDate($checkinDate)));
        }

        // CheckOutDate
        $xpathCheckOut = "//tr[ count(*[normalize-space()])=2 and *[not(.//tr) and normalize-space()][1][{$this->starts($this->t('Check-out'))}] ]/*[normalize-space()][2]/descendant::tr[count(*[normalize-space()])>1][1]";

        $checkoutDate = $this->http->FindSingleNode($xpathCheckOut . "/*[normalize-space()][1]", null, true, "/^{$patterns['date']}$/u");

        if (!$checkoutDate) {
            $checkoutDate = $this->http->FindSingleNode("//td[{$this->starts($this->t('Check-out'))}]/following-sibling::td[1]/descendant::text()[normalize-space()][1]");
        }

        if (!$checkoutDate) {
            $checkoutDate = $this->nextText($this->t("Check-out"));
        }

        if ($checkoutDate == 'Nights') {
            $checkoutDate = $this->http->FindSingleNode("//text()[normalize-space()='Check-out']/ancestor::table[1]/descendant::text()[normalize-space()][last()]");
        }

        $checkoutTime = $this->re("#" . $this->opt($this->t('Check-out')) . "\s*:?\s*[[:alpha:]\x{0E40}-\x{0E4E} ]*\s+({$patterns['time']})#ui", $this->nextText($this->t("Check-in & check-out:")));

        if (!$checkoutTime) {
            $checkoutTime = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Check-out time'))}]/following::text()[normalize-space()][1]");
        }

        if (!$checkoutTime) {
            $checkoutTime = $this->http->FindSingleNode($xpathCheckOut . "/*[normalize-space()][2]", null, true, "/({$patterns['time']})[\s)]*(?:\s*{$this->opt($this->t('until'))})?[\s)]*(?:\s*{$this->opt($this->t('localTime'))})?$/iu");
        }

        if (!$checkoutTime) {
            $checkoutTime = $this->http->FindSingleNode("//text()[normalize-space()='Check-out']/ancestor::table[1]/descendant::text()[contains(normalize-space(), ':')][1]", null, true, "/(?:\s|\-)(\d+\:\d+)$/u");
        }

        if ($checkoutDate && $checkoutTime) {
            $r->booked()->checkOut(strtotime($this->normalizeDate($checkoutDate . ', ' . $checkoutTime)));
        } elseif (preg_match("/\d+\:\d+/u", $checkoutDate, $m)) {
            $r->booked()->checkOut(strtotime($this->normalizeDate($checkoutDate)));
        }

        // Address
        $address = $this->nextText($this->t("Address:"));

        if (empty($address)) {
            $address = $this->http->FindSingleNode("//a[{$xpathHotelLink}]/ancestor::tr[1]/following-sibling::tr[normalize-space() and descendant::img][1]", null, true, '/^.*[[:alpha:]].*$/u');
        }

        if (empty($address)) {
            $address = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Address'))}][1]/following::text()[normalize-space(.)][1]");
        }
        $r->hotel()->address($address);

        // Phone
        $phone = $this->nextText("Phone:");

        if (empty($phone)) {
            $phone = $this->http->FindSingleNode("//img[contains(@src,'mail-tel') or contains(@alt,'mail-tel')]/following::text()[normalize-space()!=''][1]", null, true, '/^[+(\d][-. \d)(]{5,}[\d)]/');
        }

        if (empty($phone)) {
            $phone = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Tel'))}]", null, true, "/{$this->opt($this->t('Tel'))}\s*(.+)/");
        }

        if (empty($phone)) {
            $phone = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Tel'))}][1]/following::text()[normalize-space(.)][1]");
        }

        $r->hotel()->phone($phone);

        // GuestNames

        $travellers = array_map('trim', preg_split('/\s*,\s*/', $this->nextText($this->t("Guest"))));

        if (empty($travellers) || $travellers[0] === 'Guest') {
            $travellers = explode(',', $this->http->FindSingleNode("//text()[{$this->contains($this->t('Guest'))}][1]/ancestor::tr[1]/descendant::td[2]"));
        }
        $r->general()->travellers($travellers, true);

        if ($guests = $this->http->FindPreg("/(\d+) {$this->opt($this->t('adul'))}/", false, $this->nextText($this->t("Price For")))) {
            $r->booked()->guests($guests);
        }

        $yourBooking = $this->http->FindSingleNode("//td[{$this->eq($this->t("Your Booking"))}]/following-sibling::td[normalize-space()][1]");

        // Rooms
        $rooms = $this->re("#\(\s*(\d{1,3})\s*{$this->opt($this->t('room'))}#iu", $this->nextText($this->t("Room Type"))); // it-8085695.eml

        if (empty($rooms)) {
            $rooms = $this->re("#\b(\d{1,3})\s*{$this->opt($this->t('room'))}#iu", $yourBooking);
        } // it-13522219.eml

        if (empty($rooms)) {
            $rooms = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Rooms'))}]/following::text()[normalize-space()][1]", null, true, "/^(\d)$/");
        } // it-13522219.eml

        if (!empty($rooms)) {
            $r->booked()->rooms($rooms);
        }

        $room = $r->addRoom();
        // Rate
        $roomRate = $this->nextText($this->t('Room Rate'));

        if ($roomRate && preg_match("#\b(\d{1,3}\s*{$this->opt($this->t('night'))})\b#iu", $yourBooking, $m)) {
            $room->setRate($roomRate . ' / ' . $m[1]);
        }
        $meal = $this->http->FindSingleNode("//text()[{$this->starts($this->t('meal'))}]/ancestor::tr[1]/descendant::td[2]");

        if (!empty($meal)) {
            $room->setDescription($meal);
        }

        // CancellationPolicy
        $cancellationPolicy = $this->http->FindSingleNode("//td[{$this->eq($this->t("Cancellation policy:"))}]/following-sibling::td[normalize-space()][1]");

        if (!$cancellationPolicy) {
            $cancellationPolicy = $this->nextText($this->t("Cancellation policy:"));
        }

        if ($cancellationPolicy) {
            $r->general()->cancellation($cancellationPolicy);
        }

        // RoomType
        $roomType = $this->re("#(.*?)\s*\(#", $this->nextText($this->t("Room Type"))); // it-8085695.eml

        if (empty($roomType)) {
            $roomType = $this->nextText($this->t('Room Type'));
        } // it-13522219.eml
        $room->setType(str_replace(['<', '>'], ['(', ')'], $roomType));

        // Currency
        // Total
        $totalPayment = $this->nextText($this->t("Total price"));

        if (!$totalPayment) {
            $totalPaymentTexts = $this->http->FindNodes("//td[{$this->eq($this->t("Total"))}]/following-sibling::td[normalize-space()][1]/descendant::text()[$xpathNoEmpty]");
            $totalPayment = implode(' ', $totalPaymentTexts);
        }

        if (!$totalPayment) {
            $totalPayment = $this->http->FindSingleNode("//td[{$this->starts($this->t('Total'))}]/ancestor::tr[1]/descendant::td[2]");
        }
        $totalPayment = str_replace(['€'], ['EUR'], $totalPayment);

        if (preg_match('/^(?<currency>[A-Z]{3})[ ]*(?<amount>\d[,.\'\d ]*)$/', $totalPayment, $m)
            || preg_match('/^(?<amount>\d[,.\'\d ]*?)[ ]*(?<currency>[A-Z]{3})$/', $totalPayment, $m)
            || preg_match('/^(?<currency>[^\s\d]{1,5}) ?(?<amount>\d[,.\'\d ]*)$/', $totalPayment, $m)
            || preg_match('/^\s*(?<amount>\d[,.\'\d ]*?) ?(?<currency>[^\s\d]{1,5})\s*$/u', $totalPayment, $m)
        ) {
            // HKD 1,575.92
            $currency = $this->currency($m['currency']);
            $r->price()
                ->currency($currency)
                ->total($this->amount($m['amount']));
            // Taxes
            $tax = $this->nextText($this->t('Taxes & Fees'));

            if (preg_match('/^(?:' . preg_quote($m['currency'], '/') . '|' . preg_quote($currency, '/') . ')[ ]*(?<amount>\d[,.\'\d ]*)$/', $tax, $matches)) {
                $r->price()->tax($this->amount($matches['amount']));
            }
            // Discount
            $discount = $this->nextText($this->t('Discount'));

            if (preg_match('/^\-\s*(?:' . preg_quote($m['currency'], '/') . '|' . preg_quote($currency, '/') . ')[ ]*(?<amount>\d[,.\'\d ]*)$/', $discount, $matches)) {
                $r->price()->discount($this->amount($matches['amount']));
            }
        }
        $this->detectDeadLine($r);
    }

    /**
     * Here we describe various variations in the definition of dates deadLine.
     */
    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h): void
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (preg_match("/You may cancel or change for free before (?<time>\d+:\d+), (?<date>.+? \d{4}) \(hotel's local time\)/i",
                $cancellationText, $m)
            || preg_match("/Você pode cancelar ou alterar gratuitamente até (?<date>.+? \d{4}), (?<time>\d+:\d+) \(hotel's local time\)/i",
                $cancellationText, $m)
            || preg_match("/You can cancel or modify your booking for free before (?<time>\d+:\d+), (?<date>.+? \d{4}) \(hotel's local time\)/i",
                $cancellationText, $m)
            || preg_match("/You can cancel or modify your booking for free before (?<date>.+? \d{4}), (?<time>\d+:\d+) \(hotel's local time\)/i",
                $cancellationText, $m)
            || preg_match("/ยกเลิกหรือแก้ไขการจองได้ฟรีก่อน (?<date>.+? \d{4}) เวลา (?<time>\d+:\d+) น\./i",
                $cancellationText, $m)
        ) {
            $h->booked()->deadline(strtotime($m['time'], strtotime($this->normalizeDate($m['date']))));
        }

        $h->booked()
            ->parseNonRefundable("/Once your booking has been confirmed, it cannot be canceled or changed./")
            ->parseNonRefundable("/訂單確認後不可取消或更改。/u")
            ->parseNonRefundable("/Una volta che la prenotazione è stata confermata non può più essere cambiata o cancellata./")
            ->parseNonRefundable("/เมื่อยืนยันการจองแล้ว คุณจะไม่สามารถยกเลิกหรือเปลี่ยนแปลงได้/")// th
            ->parseNonRefundable("/This order cannot be cancelled and modified after it is confirmed/")
        ;
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
//        $this->logger->debug('$date = ' . print_r($str, true));
        $in = [
            "#^([^\d\s]+)\s+(\d+),\s+(\d{4}),\s+(\d+:\d+)$#", //Sep 8, 2017, 15:00
            "#^\s*(\d+)[. ]+([^\d\s]+?)[,. ]+(\d{4})\s*,\s+(\d+:\d+)\s*$#u", //8. September 2018 , 15:00 | 7 Gen. 2020, 15:00 | 28 Jun, 2021, 14:00
            "#^(\d{4})年(\d{1,2})月(\d{1,2})日\s*,\s*(\d+:\d+)$#u", // 2019年04月27日, 15:00
            "#^(\d{1,2})\s+(?:de\s+)?(\w+)\s+(?:de\s+)?(\d{4})\s*$#u", // 12 Jun 2018
            "#^(\d{4})\-(\d+)\-(\d+)\s*\((?:after|before)\s*([\d\:]+)[\.\)]*$#", //2022-08-06 (after 15:00
        ];
        $out = [
            "$2 $1 $3, $4",
            "$1 $2 $3, $4",
            "$2/$3/$1, $4",
            "$1 $2 $3",
            "$1/$2/$3, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

//        $this->logger->debug('$date = ' . print_r($str, true));
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

    private function amount($s)
    {
        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $this->re("#([\d\,\.]+)#", $s)));
    }

    private function currency($s)
    {
        $sym = [
            '€'   => 'EUR',
            '$'   => 'USD',
            '£'   => 'GBP',
            'บาท' => 'THB',
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

    private function nextText($field, $root = null)
    {
        $rule = $this->eq($field);

        return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::text()[normalize-space(.)][1]", $root);
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) { return "normalize-space(.)=\"{$s}\""; }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) { return "starts-with(normalize-space(.), \"{$s}\")"; }, $field));
    }

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", $field) . ')';
    }
}
