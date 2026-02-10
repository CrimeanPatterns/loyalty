<?php

namespace AwardWallet\Engine\expedia\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class HotelCancellation extends \TAccountChecker
{
    public $mailFiles = "expedia/it-10969460.eml, expedia/it-14.eml, expedia/it-1558602.eml, expedia/it-1746052.eml, expedia/it-1746054.eml, expedia/it-1904313.eml, expedia/it-2144989.eml, expedia/it-2145387.eml, expedia/it-2191019.eml, expedia/it-2304890.eml, expedia/it-3327636.eml, expedia/it-49.eml, expedia/it-54.eml, expedia/it-143361816.eml, expedia/it-56043393.eml, expedia/it-263881556-fr.eml"; // +1 bcdtravel(html)[fi]

    public static $headers = [
        'orbitz' => [
            'from' => ['orbitz.com'],
            'subj' => [
                'en' => 'Orbitz Hotel Cancellation Confirmation',
                'Orbitz Hotel Room Cancellation Notice',
                'es' => 'Orbitz Confirmación de cancelación del hotel',
            ],
        ],
        'lastminute' => [
            'from' => ['email.lastminute.com.au'],
            'subj' => [
                'en' => 'lastminute.com.au Hotel Cancellation Confirmation',
                'lastminute.com.au Hotel Room Cancellation Notice',
            ],
        ],
        'chase' => [
            'from' => ['chasetravelbyexpedia@'],
            'subj' => [],
        ],
        'travelocity' => [
            'from' => ['e.travelocity.com'],
            'subj' => [
                'en' => 'Travelocity Hotel Cancellation Confirmation',
                'Travelocity Hotel Room Cancellation Notice',
            ],
        ],
        'ebookers' => [
            'from' => [
                'mailer.ebookers.',
            ],
            'subj' => [
                'en' => 'ebookers Hotel Cancellation Confirmation',
                'ebookers Hotel Room Cancellation Notice',
                ' ebookers Hotel-Stornierungsbestätigung ',
            ],
        ],
        'cheaptickets' => [
            'from' => ['cheaptickets.com'],
            'subj' => [
                'en' => 'CheapTickets Hotel Cancellation Confirmation',
                'CheapTickets Hotel Room Cancellation Notice',
            ],
        ],
        'hotels' => [
            'from' => ['@support-hotels.com'],
            'subj' => [
                'en' => 'Hotels.com Hotel Cancellation Confirmation',
                'Hotels.com Hotel Room Cancellation Notice',
                // ja
                'Hotels.com 宿泊施設のキャンセルの確認',
                // ko
                'Hotels.com 호텔 취소 확인',
                // fr
                'Hotels.com Confirmation d\'annulation d\'hôtel -',
                // no
                'Hotels.com Avbestillingsbekreftelse for hotell -',
                // sv
                'Hotels.com Avbokningsbekräftelse för hotell -',
                // es
                'Hoteles.com Confirmación de cancelación del hotel',
            ],
        ],
        'hotwire' => [
            'from' => ['noreply@Hotwire.com'],
            'subj' => [
                'en' => 'Hotwire Hotel Cancellation Confirmation',
                'Hotwire Hotel Room Cancellation Notice',
            ],
        ],
        'expedia' => [ // always last
            'from' => ['expediamail.com', 'expedia@ca.expediamail.com'],
            'subj' => [
                'en' => "Expedia Hotel Cancellation Confirmation",
                'Expedia Hotel Room Cancellation Notice',
                'pt' => "Expedia Confirmação de cancelamento do hotel",
                'ja' => "エクスペディア ホテル キャンセルの確認",
                "エクスペディア 宿泊施設のキャンセルの確認 - ",
                'zh' => "Expedia 飯店取消確認",
                'es' => 'Expedia Confirmación de cancelación del hotel',
                'it' => 'Expedia Conferma cancellazione hotel',
                'fr' => "Expedia Confirmation d'annulation d'hôtel",
                'Expedia Confirmation d’annulation d’hôtel',
                // es
                'Expedia Confirmación de cancelación del hotel',
                'Expedia - Confirmación de cancelación del hotel',
            ],
        ],
    ];

    public $lang = 'en';
    public static $dictionary = [
        'en' => [ // it-10969460.eml, it-143361816.eml, it-2145387.eml, it-2191019.eml, it-2304890.eml, it-3327636.eml, it-49.eml, it-54.eml
            //			"Itinerary number:" => "",
            //			"Hotel reservation number:" => "",
            "cancelledPhrases" => ["Your hotel room has been cancelled", "Sorry, the hotel has cancelled your booking", "All rooms in this reservation have been cancelled",
                "You have cancelled with", ],
            "cancellationPhrases" => "You can still cancel this booking without a fee until",
            // "nonRefundable" => "",
            //			"Reserved for" => "",
            //			"adult" => "",
            "child" => ["child", "infant"],
            "Room"  => ["Room", "Guest room"],
        ],
        'de' => [ // it-1558602.eml
            "Itinerary number:"         => [" Reiseplannummer:", "Expedia-Reiseplannummer:", "Hotels.com-Reiseplannummer:"],
            "Hotel reservation number:" => "Hotel-Reservierungsnummer:",
            "cancelledPhrases"          => ["Ihr Hotelzimmer wurde storniert", "Das Hotel hat Ihre Buchung leider storniert", 'Sie haben storniert und erhalten die Anzahlung vom Hotel komplett zurück'],
            // "cancellationPhrases" => "",
            // "nonRefundable" => "",
            "Reserved for"              => "Reserviert für",
            "adult"                     => "Erwachsen",
            "child"                     => "Kind",
            "Room"                      => "Zimmer",
        ],
        'pt' => [ // it-14.eml, it-1904313.eml
            "Itinerary number:"         => ["Número de Itinerário da", "Número do itinerário da Expedia:", 'Número do itinerário da Hoteis.com:'],
            "Hotel reservation number:" => "Número de reserva do hotel:",
            "cancelledPhrases"          => ["Seu quarto de hotel foi cancelado", "Você cancelou com reembolso completo do depósito pelo hotel."],
            // "cancellationPhrases" => "",
            // "nonRefundable" => "",
            "Reserved for"              => "Reservado para",
            "adult"                     => "adulto",
            //			"child" => "",
            "Room" => "Quarto",
        ],
        'ja' => [ // it-56043393.eml
            "Itinerary number:"         => ["エクスペディア旅程番号 :", "Hotels.com旅程番号 :"],
            // "Hotel reservation number:" => "",
            "cancelledPhrases"          => ["ホテルの部屋がキャンセルされました", "このキャンセルにより、ホテルからデポジットの全額が返金されます"],
            // "cancellationPhrases" => "",
            "nonRefundable"             => "により次の金額のキャンセル料が請求されます",
            "Reserved for"              => "ご予約者名",
            "adult"                     => "大人",
            // "child" => "",
            "Room" => "部屋",
        ],
        'zh' => [ // it-1746052.eml, it-1746054.eml
            "Itinerary number:"         => "行程編號：",
            "Hotel reservation number:" => "飯店預訂編號:",
            "cancelledPhrases"          => ["已經取消您的飯店客房", "您已經取消訂房"],
            // "cancellationPhrases" => "",
            // "nonRefundable" => "",
            "Reserved for"              => ["已保留給", "旅客："],
            "adult"                     => "位成人",
            //			"child" => "",
            "Room" => "客房",
        ],
        'es' => [
            "Itinerary number:" => "Número de itinerario de ",
            //			"Hotel reservation number:" => "",
            "cancelledPhrases" => ["Tu habitación de hotel ha sido cancelada", "Cancelaste con un reembolso total del", 'Tu habitación de hotel se canceló',
                'Has cancelado la reserva con derecho al reembolso', 'Has cancelado tu reserva con ', ],
            // "cancellationPhrases" => "",
            // "nonRefundable" => "",
            "Reserved for"     => ["Reservado para", "Para"],
            "adult"            => "adulto",
            //			"child" => "",
            "Room" => "Habitación",
        ],
        'it' => [ // it-2144989.eml
            "Itinerary number:" => "Numero itinerario ",
            //			"Hotel reservation number:" => "",
            "cancelledPhrases" => ["La camera hotel è stata cancellata", "La tua prenotazione è cancellata"],
            // "cancellationPhrases" => "",
            // "nonRefundable" => "",
            "Reserved for"     => ["Prenotata per", "Prenotazione per"],
            "adult"            => "adult",
            //			"child" => "",
            "Room" => "Camera",
        ],
        'ko' => [
            "Itinerary number:" => ["익스피디아 일정 번호:", "Hotels.com 일정 번호:"],
            //			"Hotel reservation number:" => "",
            "cancelledPhrases" => ["호텔 예약이 취소되었습니다"],
            // "cancellationPhrases" => "",
            // "nonRefundable" => "",
            "Reserved for"     => ["예약자"],
            "adult"            => "성인",
            "child"            => "아동 ",
            "Room"             => "객실",
        ],
        'fi' => [
            "Itinerary number:" => ["Hotels.comin matkasuunnitelman numero:"],
            //			"Hotel reservation number:" => "",
            "cancelledPhrases" => ["Huonevaraus on peruutettu"],
            // "cancellationPhrases" => "",
            // "nonRefundable" => "",
            "Reserved for"     => ["Varaaja:"],
            "adult"            => "aikuinen",
            //            			"child" => " ",
            "Room" => "Huone",
        ],
        'fr' => [
            "Itinerary number:" => ["Numéro de voyage Hotels.com :", "Numéro d’itinéraire "],
            //			"Hotel reservation number:" => "",
            "cancelledPhrases" => [
                "Votre chambre d'hôtel a été annulée", "Votre chambre d’hôtel a été annulée",
                "Vous avez annulé avec plein remboursement de l'acompte par l'hôtel", "Vous avez annulé avec plein remboursement de l’acompte par l’hôtel",
            ],
            // "cancellationPhrases" => "",
            // "nonRefundable" => "",
            "Reserved for"     => ["Réservation pour", "Réservé pour"],
            "adult"            => "adulte",
            "child"            => "enfant",
            "Room"             => "Chambre",
        ],
        'no' => [
            "Itinerary number:" => ["Reiserutenummer hos Hotels.com:"],
            //			"Hotel reservation number:" => "",
            "cancelledPhrases" => ["Hotellrommet er avbestilt"],
            // "cancellationPhrases" => "",
            // "nonRefundable" => "",
            "Reserved for"     => ["Bestilt for"],
            "adult"            => "voksne",
            //            			"child" => " ",
            "Room" => "Rom",
        ],
        'nl' => [
            "Itinerary number:" => ["Hotels.com-reisplannummer:"],
            //			"Hotel reservation number:" => "",
            "cancelledPhrases" => ["Je hebt geannuleerd met een volledige restitutie van de aanbetaling"],
            // "cancellationPhrases" => "",
            // "nonRefundable" => "",
            "Reserved for"     => ["Geboekt voor"],
            "adult"            => "volwassenen",
            //            			"child" => " ",
            "Room" => "Kamer",
        ],
        'sv' => [
            "Itinerary number:" => ["Hotels.com Resplansnummer:"],
            //			"Hotel reservation number:" => "",
            "cancelledPhrases" => ["Du har avbokat med full återbetalning"],
            // "cancellationPhrases" => "",
            // "nonRefundable" => "",
            "Reserved for"     => ["Bokat för"],
            "adult"            => ["vuxen", "vuxna"],
            //            			"child" => " ",
            "Room" => "Rum",
        ],
    ];

    protected $code = null;

    protected $bodies = [
        'lastminute' => [
            '//img[contains(@alt,"lastminute.com")]',
            '//a[contains(.,"lastminute.com")]/parent::*[contains(.,"Collected by")]',
        ],
        'chase' => [
            '//img[normalize-space(@itemprop)="image" and (contains(@src,".chase.com/") or contains(@src,"travel.chase.com"))]',
            'Chase Travel',
            'Chase Ultimate',
        ],
        'cheaptickets' => [
            '//img[contains(@src,"cheaptickets.com")]',
            'cheaptickets.com',
            'Call CheapTickets customer',
        ],
        'ebookers' => [
            '//img[contains(@alt,"ebookers.com")]',
            'Collected by ebookers',
            'Maksun veloittaa ebookers',
        ],
        'hotels' => [
            '//img[contains(@src,"Hotels.com")]',
            "Hotels.com",
        ],
        'hotwire' => [
            '//img[contains(@alt,"Hotwire.com")]',
            'Or call Hotwire at',
        ],
        'mrjet' => [
            '//img[contains(@src,"MrJet.se")]',
            'MrJet.se',
        ],
        'orbitz' => [
            '//img[contains(@alt,"Orbitz.com")]',
            'This Orbitz Itinerary was sent from',
            'Call Orbitz customer care',
        ],
        'rbcbank' => [
            '//img[contains(@src,"rbcrewards.com")]',
            'rbcrewards.com',
        ],
        'travelocity' => [
            '//img[contains(@src,"travelocity.com")]',
            'travelocity.com',
            'Collected by Travelocity',
        ],
        'expedia' => [
            '//img[contains(@alt,"expedia.com")]',
            '//img[contains(@src,"expedia.com")]',
            'expedia.com',
        ],
    ];

    protected $reBody2 = [
        'en'    => 'Your hotel room has been cancelled',
        'en2'   => 'You have cancelled with full refund of deposit by hotel',
        'en3'   => 'Sorry, the hotel has cancelled your booking',
        'en4'   => 'All rooms in this reservation have been cancelled',
        'en5'   => 'Your room has been cancelled.',
        'en6'   => 'This part of your booking has been cancelled',
        'en7'   => ', free cancellation is ending soon',
        'de'    => 'Ihr Hotelzimmer wurde storniert',
        'de2'   => 'Das Hotel hat Ihre Buchung leider storniert',
        'de3'   => 'Sie haben storniert und erhalten die',
        'ja'    => 'ホテルの部屋がキャンセルされました',
        'ja2'   => 'このキャンセルにより、ホテルからデポジットの全額が返金されます',
        'zh'    => '您已經取消訂房',
        'zh2'   => '已經取消您的飯店客房',
        'zh3'   => '您已經取消預訂',
        'pt'    => 'Seu quarto de hotel foi cancelado',
        'pt2'   => 'Você cancelou com reembolso completo do depósito pelo hotel.',
        'es'    => 'Tu habitación de hotel ha sido cancelada',
        'es2'   => 'Cancelaste con un reembolso total',
        'es3'   => 'Tu habitación de hotel se canceló',
        'es4'   => 'Has cancelado la reserva con derecho al reembolso',
        'es5'   => 'Has cancelado tu reserva con',
        'it'    => 'La camera hotel è stata cancellata',
        'it2'   => 'La tua prenotazione è cancellata',
        'ko'    => '호텔 예약이 취소되었습니다',
        'fi'    => 'Huonevaraus on peruutettu',
        'fr'    => "Votre chambre d'hôtel a été annulée",
        'fr2'   => 'Votre chambre d’hôtel a été annulée',
        'fr3'   => "Vous avez annulé avec plein remboursement de l'acompte par l'hôtel",
        'fr4'   => 'Vous avez annulé avec plein remboursement de l’acompte par l’hôtel',
        'no'    => 'Hotellrommet er avbestilt.',
        'nl'    => 'Je hebt geannuleerd met een volledige restitutie van de aanbetaling',
        'sv'    => 'Du har avbokat med full återbetalning',
    ];

    private $reBody = [
        'CheapTickets',
        'ebookers',
        'Hotels.com',
        'Hotwire',
        'MrJet',
        'Orbitz',
        'RBC Travel',
        'Travelocity',
        'Expedia',
        'lastminute',
        'chase.com',
    ];

    public static function getEmailProviders()
    {
        return array_keys(self::$headers);
    }

    public function detectEmailFromProvider($from)
    {
        foreach (self::$headers as $arr) {
            foreach ($arr['from'] as $f) {
                if (stripos($from, $f) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers['from']) || empty($headers['subject'])) {
            return false;
        }

        foreach (self::$headers as $code => $arr) {
            $byFrom = $bySubj = false;

            foreach ($arr['from'] as $f) {
                if (stripos($headers['from'], $f) !== false) {
                    $byFrom = true;

                    break;
                }
            }

            foreach ($arr['subj'] as $subj) {
                if (stripos($headers['subject'], $subj) !== false) {
                    $bySubj = true;

                    break;
                }
            }

            if (($byFrom || $bySubj) && $this->code === null) {
                $this->code = $code;
            }

            if ($bySubj) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        foreach ($this->reBody as $s) {
            if (stripos($body, $s) === false) {
                $first = true;

                break;
            }
        }

        if (empty($first)) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if (strpos($body, $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        foreach ($this->reBody2 as $lang => $re) {
            if (stripos($this->http->Response['body'], $re) !== false) {
                $this->lang = trim($lang, '1234567890');

                break;
            }
        }

        if ($code = $this->getProvider($parser)) {
            $email->setProviderCode($code);
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
            'time'  => '\d{1,2}(?:[:：]\d{2})?(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?',
            'phone' => '[+(\d][-+. \d)(]{5,}[\d)]',
        ];

        // Travel Agency
        $email->obtainTravelAgency();

        $taConfirmation = $taConfirmationTitle = null;
        $taConfirmation = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t("Itinerary #"))}] ]/*[normalize-space()][2]", null, true, "/^[\dA-Z]{5,}$/");

        if ($taConfirmation) {
            // it-143361816.eml
            $taConfirmationTitle = $this->http->FindSingleNode("//tr[count(*[normalize-space()])=2]/*[normalize-space()][1][{$this->eq($this->t("Itinerary #"))}]");
        } elseif (preg_match("/^(.{2,}?)\s*[:：]\s*([\dA-Z]{5,})\b/", $this->http->FindSingleNode("descendant::text()[{$this->contains($this->t("Itinerary number:"))}][1]"), $m)) {
            $taConfirmationTitle = $m[1];
            $taConfirmation = $m[2];
        }

        if (!empty($taConfirmation)) {
            $email->ota()->confirmation($taConfirmation, $taConfirmationTitle);
        }

        $h = $email->add()->hotel();

        // General
        $conf = $this->http->FindSingleNode("(//text()[" . $this->contains($this->t("Hotel reservation number:")) . "])[1]", null, true, "#[:：]\s*([\dA-z\-_]{5,}|Confirmed)\b#");

        if ((empty($conf) && empty($this->http->FindSingleNode("(//text()[" . $this->contains($this->t("Hotel reservation number:")) . "])[1]")))
            || $conf === 'Confirmed'
        ) {
            $h->general()->noConfirmation();
        } else {
            $h->general()->confirmation($conf);
        }

        if ($this->http->XPath->query("//text()[{$this->contains($this->t("cancelledPhrases"))}]")->length > 0) {
            $h->general()
                ->status('Cancelled')
                ->cancelled();

            $cancellationNumber = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Número do itinerário da TAAP:')]", null, true, "/Número do itinerário da TAAP\:\s*(\d{4,})$/u");

            if (!empty($cancellationNumber)) {
                $h->setCancellationNumber($cancellationNumber);
            }
        }
        $guests = implode("\n", $this->http->FindNodes("//text()[" . $this->eq($this->t('Reserved for')) . "]/ancestor::td[1]/following-sibling::td[last()]//text()[normalize-space(.)]"));
        $h->general()->traveller($this->re("#^\s*([^\d\n]+)#", $guests));

        // Hotel
        $hotelInfoNodes = $this->http->FindNodes("//text()[{$this->contains($this->t("Reserved for"))}]/ancestor::table[2]/descendant::tr[ *[1][descendant::img and normalize-space()=''] and count(*[normalize-space()])=1 ][1]/*[normalize-space()][1]/descendant::text()[string-length(normalize-space())>1]");

        if (!empty($hotelInfoNodes[1])) {
            $h->hotel()
                ->name($hotelInfoNodes[0])
                ->address($hotelInfoNodes[1]);
        }
        $hotelInfoText = implode("\n", $hotelInfoNodes);

        if (preg_match("/{$this->preg_implode($this->t("Tel"))}[:\s]+({$patterns['phone']})/", $hotelInfoText, $m)) {
            // Tel: +1 (212) 581-7000
            $h->hotel()->phone($m[1]);
        }

        if (preg_match("/{$this->preg_implode($this->t("Fax"))}[:\s]+({$patterns['phone']})/", $hotelInfoText, $m)) {
            // Fax: +1 (212) 581-7000
            $h->hotel()->fax($m[1]);
        }

        // Booked
        if (!empty($h->getHotelName())) {
            $h->booked()
                ->checkIn($this->normalizeDate($this->http->FindSingleNode("(//text()[{$this->eq($h->getHotelName())}])[1]/preceding::tr[normalize-space()][1]/td[1]", null, true, "#^(.*?)\s+-#")))
                ->checkOut($this->normalizeDate($this->http->FindSingleNode("(//text()[{$this->eq($h->getHotelName())}])[1]/preceding::tr[normalize-space()][1]/td[1]", null, true, "#^.*?\s+-\s+(.+)#")))
            ;
        }

        $h->booked()
            ->guests($this->re("#\b(\d{1,3}) {$this->preg_implode($this->t("adult"))}#", $guests) ?? $this->re("#{$this->preg_implode($this->t("adult"))} (\d{1,3})\b#", $guests), false, true)
            ->kids($this->re("#\b(\d{1,3}) {$this->preg_implode($this->t("child"))}#", $guests) ?? $this->re("#{$this->preg_implode($this->t("child"))} (\d{1,3})\b#", $guests), false, true)
        ;

        // Rooms
        $rooms = $this->http->FindNodes('//text()[' . $this->eq($this->t("Room")) . ']/ancestor::td[1]/following-sibling::td[last()]//text()[normalize-space(.)]');

        if (count($rooms) == 2) {
            $h->addRoom()
                ->setType($rooms[0])
                ->setDescription($rooms[1]);
        } elseif (count($rooms) == 1) {
            $h->addRoom()
                ->setType($rooms[0]);
        }

        $cancellation = implode(' ', $this->http->FindNodes("//p[{$this->contains($this->t("cancellationPhrases"))}]/descendant::text()[normalize-space()]"));

        if (preg_match("/You (?i)can still cancell? this booking without a fee until\s+(.{3,}?\d{4}[,\s]+{$patterns['time']})/u", $cancellation, $m)) {
            $h->booked()->deadline2($m[1]);
        } elseif ($this->http->XPath->query("//*[{$this->contains($this->t("nonRefundable"))}]")->length > 0) {
            $h->booked()->nonRefundable();
        }
    }

    private function getProvider(PlancakeEmailParser $parser): ?string
    {
        $this->detectEmailByHeaders(['from' => $parser->getCleanFrom(), 'subject' => $parser->getSubject()]);

        if (isset($this->code)) {
            if ($this->code === 'expedia') {
                return null;
            } else {
                return $this->code;
            }
        }

        foreach ($this->bodies as $code => $criteria) {
            if (count($criteria) > 0) {
                foreach ($criteria as $search) {
                    if (!(stripos($search, '//') === 0 && $this->http->XPath->query($search)->length > 0)
                        && !(stripos($search, '//') === false && strpos($this->http->Response['body'], $search) !== false)
                    ) {
                        continue 2;
                    }
                }

                return $code;
            }
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
//        $this->logger->debug('$str = '.print_r( $str,true));
        $in = [
            "#^\s*([[:alpha:]]+)\s+(\d{1,2}),\s+(\d{4})\s*$#u", // ago 1, 2018
            "#^\s*[-[:alpha:]]+\s+(\d{1,2})\/(\d{1,2})\/(\d{4})\s*$#u", // Seg 31/03/2014
            "#^\s*[-[:alpha:]]+\s+(\d{1,2})\/([^,.\d\s]{3,})\/(\d{4})\s*$#u", // Di 5/Nov/2013
            "#^\s*[-[:alpha:]]+\s+([[:alpha:]]{3,})\/(\d{1,2})\/(\d{4})\s*$#u", // Thu Dec/25/2014
            // 2014年/05月/31日 (星期六)    |    2020 年 3 月 18 日; 2022년 11월 8일
            "#^\s*(\d{4})[年\/\s년]+(\d{1,2})[月\/\s월]+(\d{1,2})[日\s일]*(?:\(.*|$)#u",

            "#^\s*(\d{1,2})[.]?\s+([[:alpha:]]{3,})[.]?\s+(\d{4})\s*$#u", // Di 5/Nov/2013
            "#^\s*(\d{1,2})(?:\s+de)?\s+([[:alpha:]]+)(?:\s+de)?\s+(\d{4})\s*$#u", // 27 de nov de 2021
        ];
        $out = [
            "$2 $1 $3",
            "$1.$2.$3",
            "$1 $2 $3",
            "$2 $1 $3",
            "$3.$2.$1",

            "$1 $2 $3",
            "$1 $2 $3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([[:alpha:]]+)\s+(?:\d{4}|%Y%)#u", $str, $m) or preg_match("#\d+\s+([[:alpha:]]+)$#u", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ')="' . $s . '"';
        }, $field)) . ')';
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function currency($s, $location = '')
    {
//        $this->logger->debug('currency = '.print_r( $s,true));
        if (preg_match("#¤#", $s)) {
            $s = $this->defaultCurrency();
        }
        $sym = [
            '$C'        => 'CAD',
            '€'         => 'EUR',
            'R$'        => 'BRL',
            'C$'        => 'CAD',
            'CA$'       => 'CAD',
            'SG$'       => 'SGD',
            'HK$'       => 'HKD',
            'AU$'       => 'AUD',
            'A$'        => 'AUD',
            '$'         => 'USD',
            'US$'       => 'USD',
            'US dollars'=> 'USD',
            '£'         => 'GBP',
            //			'kr'=>'NOK', NOK or SEK
            'RM'             => 'MYR',
            '฿'              => 'THB',
            'MXN$'           => 'MXN',
            'MX$'            => 'MXN',
            'Euro'           => 'EUR',
            'Euros'          => 'EUR',
            'Real brasileiro'=> 'BRL',
            '円'              => 'JPY',
        ];

        if ($code = $this->re("#(?:^|\s|\d)([A-Z]{3})(?:$|\s|\d)#", $s)) {
            return $code;
        }
        $s = preg_replace("#([,.\d :]+)#", '', $s);

        foreach ($sym as $f=> $r) {
            if ($s == $f) {
                return $r;
            }
        }

        if ($s == '¥' && stripos($location, 'Japan') !== false) {
            return 'JPY';
        }

        if ($s == 'kr' && $this->lang == 'sv') {
            return 'SEK';
        }

        if ($s == 'kr' && $this->lang == 'no') {
            return 'NOK';
        }

        return null;
    }

    private function defaultCurrency()
    {
        $totalText = implode(" ", $this->http->FindNodes("//text()[" . $this->contains($this->t("Total")) . "][1]/ancestor::table[2]//text()[normalize-space(.)]"));
        $reCurrency = (array) $this->t("#defaultCurrency#");

        foreach ($reCurrency as $re) {
            if (preg_match($re, $totalText, $m) && !empty($m[1])) {
                return $this->currency($m[1]);
            }
        }

        return null;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
    }
}
