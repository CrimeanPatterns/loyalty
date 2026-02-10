<?php

namespace AwardWallet\Engine\airbnb\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class Respond extends \TAccountChecker
{
    public $mailFiles = "airbnb/it-112631661.eml, airbnb/it-113492621.eml, airbnb/it-132369981.eml";

    private $detectFrom = "express@airbnb.com";
    private $detectBody = [
        'en' => [
            ['Respond to ', 'by replying directly to this email.'], // Respond to Maria Laura by replying directly to this email.
        ],
        'pt' => [
            ['Dê um retorno para ', 'respondendo diretamente a este email.'],
            ['Responda a ', 'para responder diretamente a este email.'],
        ],
        'es' => [
            ['Responde este correo electrónico para enviar un mensaje a', '.'],
            ['Para contestarle a ', ', simplemente responde a este correo electrónico.'],
            ['Responde al mensaje de', '.'],
            ['Respondele a', 'este correo electrónico directamente.'],
        ],
        'fr' => [
            ['Envoyez votre réponse à ', 'en répondant directement à cet e-mail.'],
            ['Envoyez votre réponse à ', 'en répondant directement à cette adresse courriel.'],
        ],
        'de' => [
            ['Antworte direkt auf diese E-Mail, um ', 'eine Nachricht zu senden'],
        ],
        'ru' => [
            ['Ответьте на это письмо, чтобы отправить сообщение пользователю ', '.'],
        ],
        'it' => [
            ['Rispondi a questa email per inviare un messaggio a ', ''],
        ],
        'nl' => [
            ['Reageer op ', ' door rechtstreeks op deze e-mail te antwoorden.'],
        ],
        'no' => [
            ['Svar på denne e-posten for å sende melding til ', ''],
        ],
        'zh' => [
            ['直接回复此邮件以答复', '。'],
            ['直接回覆此郵件回信給', '。'],
        ],
        'hr' => [
            ['', ' će primiti vaš odgovor ako izravno odgovorite na ovaj e-mail.'],
        ],
        'pl' => [
            ['Odpowiedz drogą mailową do użytkownika', '.'],
        ],
        'ko' => [
            ['', '님께 메시지를 보내려면 본 이메일에 회신하세요.'],
        ],
        'sv' => [
            ['Svara på detta e-postmeddelande för att kontakta', '.'],
        ],
        'tr' => [
            ['Doğrudan bu e-postayı yanıtlayarak', 'adlı kişiye yanıt gönderin'],
        ],
    ];
    private $detectSubject = [
        // en
        "Reservation at", //Reservation at Cozy, Stylish, by the Water: New Beginnings for Sep 16 - 25, 2021
        "Inquiry for",
        "Pre-approval at",
        "Enquiry at",
        // pt
        "Pedido de reserva para",
        "Reserva para ",
        "Pré-aprovação para ",
        "Pedido de reserva para ",
        // es
        "Reservación en ",
        "Reserva en ",
        "Preaprobación de tu reserva en",
        // fr
        "Demande d'information sur ",
        "Réservation à ",
        // de
        'Buchung der Unterkunft ',
        'Vorabbestätigung der Unterkunft ',
        'Buchungsanfrage für ',
        // ru
        'Бронирование:',
        'Запрос о',
        'Запрос на бронирование:',
        // it
        'Prenotazione per ',
        // nl
        "Reservering bij ",
        // no
        'Reservasjon på ',
        // zh
        ' 的预订',
        ' 的预订申请',
        // hr
        'Rezervacija u smještaju',
        // ko
        '숙소 예약 관련',
        // pl
        'Rezerwacja oferty',
        // sv
        'Bokning av',
        // tr
        'lı kayıtlı yerde rezervasyon',
    ];

    private $subject;
    private $lang = '';

    private static $dict = [
        'en' => [
            'reservationSubject' => ['Reservation at'], // темы писем у подтвержденных резерваций
            // common
            //            'hosted by' => '',
            //            'Guests' => '',
            // type 1
            'Reservation details' => 'Reservation details',
            'Total payment'       => 'Total payment',
            'Check-In'            => ['Check-In', 'Check-in'],
            'Check-out'           => ['Check-out', 'Checkout'],
            // type 2
            'Total earnings' => ['Total earnings', 'Total payment'],
        ],
        'pt' => [
            'reservationSubject' => ['Reserva para'],
            // common
            'hosted by' => 'hospedado por',
            'Guests'    => 'Hóspedes',
            // type 1
            'Reservation details' => ['Informações da reserva', 'Detalhes da reserva'],
            'Total payment'       => 'Pagamento total',
            'Check-In'            => 'Check-in',
            'Check-out'           => 'Checkout',
            // type 2
            'Total earnings' => ['Ganhos totais', 'Pagamento total', 'Rendimentos totais'],
        ],
        'es' => [
            'reservationSubject' => ['Reservación en', 'Reserva en'],
            // common
            'hosted by' => ['- Anfitrión:', '· Anfitrión:', ' cuyo anfitrión es'],
            // type 1
            'Reservation details' => ['Datos de la reservación', 'Detalles de la reservación', 'Detalles de la reserva',
                'Información de la reserva', ],
            'Guests'        => 'Huéspedes',
            'Total payment' => 'Pago total',
            'Check-In'      => 'Llegada',
            'Check-out'     => 'Salida',
            // type 2
            'Total earnings' => ['Pago total'],
        ],
        'fr' => [
            'reservationSubject' => ['Réservation à'],
            // common
            'hosted by' => ['- Hôte :', 'hébergé par'],
            'Guests'    => 'Voyageurs',
            // type 1
            'Reservation details' => 'Détails de la réservation',
            'Total payment'       => 'Paiement total',
            'Check-In'            => 'Arrivée',
            'Check-out'           => 'Départ',
            // type 2
            'Total earnings' => ['Paiement total', 'Total des revenus'],
        ],
        'de' => [
            'reservationSubject' => ['Buchung der Unterkunft'],
            // common
            'hosted by' => ['vermietet von', 'Gastgeber:in ist'],
            'Guests'    => 'Gäste',
            // type 1
            'Reservation details' => 'Buchungsdetails',
            'Total payment'       => 'Gesamtbetrag',
            'Check-In'            => 'Check-in',
            'Check-out'           => 'Check-out',
            // type 2
            //            'Total earnings' => [''],
        ],
        'ru' => [
            'reservationSubject' => ['Бронирование:'],
            // common
            'hosted by' => 'у хозяина',
            'Guests'    => 'Гости',
            // type 1
            'Reservation details' => 'Детали бронирования',
            'Total payment'       => 'Общий платеж',
            'Check-In'            => 'Прибытие',
            'Check-out'           => ['Выезд'],
            // type 2
            'Total earnings' => 'Общий заработок',
        ],
        'it' => [
            'reservationSubject' => ['Prenotazione per '], // темы писем у подтвержденных резерваций
            // common
            'hosted by' => 'da ',
            'Guests'    => 'Ospiti',
            // type 1
            'Reservation details' => 'Dettagli della prenotazione',
            'Total payment'       => 'Pagamento totale',
            'Check-In'            => 'Check-in',
            'Check-out'           => ['Check-out'],
            // type 2
            //            'Total earnings' => [''],
        ],
        'nl' => [
            'reservationSubject' => ['Reservering bij '], // темы писем у подтвержденных резерваций
            // common
            'hosted by' => 'verhuurd door',
            'Guests'    => 'Gasten',
            // type 1
            'Reservation details' => 'Reserveringsgegevens',
            'Total payment'       => 'Total payment',
            'Check-In'            => 'Inchecken',
            'Check-out'           => ['Uitchecken'],
            // type 2
            //            'Total earnings' => [''],
        ],
        'no' => [
            'reservationSubject' => ['Reservasjon på '], // темы писем у подтвержденных резерваций
            // common
            'hosted by' => 'har',
            'Guests'    => 'Gjester',
            // type 1
            'Reservation details' => 'Reservasjonsopplysninger',
            'Total payment'       => 'Totalt',
            'Check-In'            => 'Innsjekking',
            'Check-out'           => ['Utsjekking'],
            // type 2
            //            'Total earnings' => [''],
        ],
        'zh' => [
            'reservationSubject' => [' 的预订'], // темы писем у подтвержденных резерваций
            //            // common
            'hosted by' => '出租的',
            'Guests'    => ['房客', '房客人數'],
            // type 1
            'Reservation details' => ['订单详情', '預訂詳情'],
            'Total payment'       => ['应付总额', '付款總額'],
            'Check-In'            => '入住',
            'Check-out'           => ['退房'],
            // type 2
            //            'Total earnings' => [''],
        ],
        'hr' => [
            'reservationSubject' => ['Rezervacija u smještaju '], // темы писем у подтвержденных резерваций
            // common
            'hosted by' => ', domaćin je',
            'Guests'    => 'Gosti',
            // type 1
            //            'Reservation details' => '',
            //            'Total payment' => '',
            //            'Check-In' => '',
            //            'Check-out' => [''],
            // type 2
            'Total earnings' => ['Ukupna zarada'],
        ],
        'ko' => [
            'reservationSubject' => ['숙소 예약 관련'], // темы писем у подтвержденных резерваций
            // common
            'hosted by' => '호스팅하는',
            'Guests'    => '인원',
            // type 1
            'Reservation details' => '예약 세부정보',
            'Total payment'       => '총 결제액',
            'Check-In'            => '체크인',
            'Check-out'           => ['체크아웃'],
            // type 2
            //            'Total earnings' => [''],
        ],
        'pl' => [
            //            'reservationSubject' => ['Rezervacija u smještaju '], // темы писем у подтвержденных резерваций
            // common
            'hosted by' => 'miejsce gospodarza',
            'Guests'    => 'Goście',
            // type 1
            'Reservation details' => 'Szczegóły rezerwacji',
            //            'Total payment' => '',
            'Check-In'  => 'Zameldowanie',
            'Check-out' => ['Wymeldowanie'],
            // type 2
            //            'Total earnings' => [''],
        ],
        'sv' => [
            //            'reservationSubject' => ['Rezervacija u smještaju '], // темы писем у подтвержденных резерваций
            // common
            'hosted by' => 'hos värden',
            'Guests'    => 'Gäster',
            // type 1
            'Reservation details' => 'Bokningsuppgifter',
            //            'Total payment' => '',
            'Check-In'  => 'Incheckning',
            'Check-out' => ['Utcheckning'],
            // type 2
            //            'Total earnings' => [''],
        ],
        'tr' => [
            'reservationSubject' => ['adlı kayıtlı yerde rezervasyon'], // темы писем у подтвержденных резерваций
            // common
            'hosted by' => 'adlı kişinin yaptığı',
            'Guests'    => 'Misafirler',
            // type 1
            'Reservation details' => 'Rezervasyon ayrıntıları',
            //            'Total payment' => '',
            'Check-In'  => 'Giriş',
            'Check-out' => ['Çıkış'],
            // type 2
            //            'Total earnings' => [''],
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        foreach ($this->detectBody as $lang => $detectBody) {
            foreach ($detectBody as $dBody) {
                if (is_array($dBody) && count($dBody) == 2
                    && $this->http->XPath->query("//text()[" . $this->starts($dBody[0]) . " and " . $this->contains($dBody[1]) . "]")->length > 0
                ) {
                    $this->lang = $lang;

                    break;
                }
            }
        }

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        $this->subject = $parser->getSubject();

        $type = '';

        if (
            $this->http->XPath->query("//tr[not(.//tr)][" . $this->starts($this->t('Total payment')) . "]/following::tr[not(.//tr)][" . $this->starts($this->t('Check-In')) . "]")->length > 0
            || $this->http->XPath->query("//tr[not(.//tr)][" . $this->starts($this->t('Guests')) . "]/following::tr[not(.//tr)][" . $this->starts($this->t('Check-In')) . "]")->length > 0
        ) {
            $this->parseEmail1($email);
            $type = '1';
        // it-113492621.eml

            // Check-In                 Check-out
            // Thursday                 Saturday
            // September 16, 2021       September 25, 2021
        } elseif ($this->http->XPath->query("//tr[count(*)=3 and *[2][not(normalize-space())]//img[contains(@src, 'slash')]]")->length > 0) {
            $this->parseEmail2($email);
            $type = '2';

            // it-112631661.eml
            // quinta-feira                     /                       domingo
            // 1‌4‌ ‌d‌e‌ ‌o‌u‌t‌u‌b‌r‌o‌ ‌d‌e‌ ‌2‌0‌2‌1‌            /         1‌7‌ ‌d‌e‌ ‌o‌u‌t‌u‌b‌r‌o‌ ‌d‌e‌ 2021
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang) . $type);

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href,'.airbnb.')] | //text()[contains(.,'♥') and contains(.,'Airbnb')]")->length === 0) {
            return false;
        }

        foreach ($this->detectBody as $detectBody) {
            foreach ($detectBody as $dBody) {
                if (is_array($dBody) && count($dBody) == 2
                    && $this->http->XPath->query("//text()[" . $this->starts($dBody[0]) . " and " . $this->contains($dBody[1]) . "]")->length > 0
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && isset($headers["subject"])) {
            if (stripos($headers['from'], $this->detectFrom) === false) {
                return false;
            }

            foreach ($this->detectSubject as $reSubject) {
                if (stripos($headers["subject"], $reSubject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseEmail1(Email $email): void
    {
        $h = $email->add()->hotel();

        // Hotel
        $hotelText = implode("\n", $this->http->FindNodes("//text()[" . $this->eq($this->t("Reservation details")) . "]/following::tr[normalize-space()][1]//text()[normalize-space()]"));

        if ((in_array($this->lang, ['zh', 'ko', 'tr']) && preg_match("/^(?<name>[\s\S]+?)\n+\s*.+?" . $this->preg_implode($this->t("hosted by")) . "\s*(?<type>.+? [\-\-] .+)/u", $hotelText, $m))
            || (!in_array($this->lang, ['zh', 'ko']) && preg_match("/^(?<name>[\s\S]+?)(?<type>.+? - .+?)[\s,]\s*" . $this->preg_implode($this->t("hosted by")) . "/u", $hotelText, $m))) {
            $h->hotel()
                ->name($m['name'])
                ->house()
                ->noAddress()
            ;

            $r = $h->addRoom();
            $r->setType($m['type']);
        }

        // Booked
        $checkIn = $this->re("/^\s*" . $this->preg_implode($this->t("Check-In")) . "\s+(.+)/u", implode(" ", $this->http->FindNodes("//text()[" . $this->eq($this->t("Check-In")) . "]/ancestor::td[not(" . $this->eq($this->t("Check-In")) . ") and " . $this->starts($this->t("Check-In")) . "][1]//text()[normalize-space()]")));

        $checkOut = $this->re("/^\s*" . $this->preg_implode($this->t("Check-out")) . "\s+(.+)/u", implode(" ", $this->http->FindNodes("//text()[" . $this->eq($this->t("Check-out")) . "]/ancestor::td[not(" . $this->eq($this->t("Check-out")) . ") and " . $this->starts($this->t("Check-out")) . "][1]//text()[normalize-space()]")));

        $h->booked()
            ->checkIn($this->normalizeDate($checkIn))
            ->checkOut($this->normalizeDate($checkOut))
            ->guests($this->http->FindSingleNode("//text()[" . $this->eq($this->t('Guests')) . "]/ancestor::td[1]", null, true,
                "/" . $this->preg_implode($this->t("Guests")) . "\s*(\d+)/u"))
        ;

        // Price
        $total = $this->http->FindSingleNode("//text()[" . $this->eq($this->t('Total payment')) . "]");

        if (count(array_filter($h->toArray())) == 8
            && (!empty($total) || $this->http->XPath->query("//tr[not(.//tr)][" . $this->starts($this->t('Guests')) . "]/following::tr[not(.//tr)][" . $this->starts($this->t('Check-In')) . "]")->length > 0)
        ) {
            if (preg_match("/" . $this->preg_implode($this->t("reservationSubject")) . "/", $this->subject)) {
                $h->general()
                    ->noConfirmation();
            } else {
                $email->removeItinerary($h);
                $email->setIsJunk(true);
            }
        }
    }

    private function parseEmail2(Email $email): void
    {
        $h = $email->add()->hotel();

        // Hotel
        $hotelText = implode("\n", $this->http->FindNodes("//tr[count(*)=3 and *[2][not(normalize-space()) and .//img[contains(@src, 'slash')]]]/preceding::text()[normalize-space()][1]/ancestor::tr[normalize-space()][1]//text()[normalize-space()]"));
//        $this->logger->debug('$hotelText = '.print_r( $hotelText,true));
        if (preg_match("#^(?<name>[\s\S]+?)(?<type>.+? - .+?)\s+" . $this->preg_implode($this->t("hosted by")) . "#u", $hotelText, $m)) {
            $h->hotel()
                ->name($m['name'])
                ->house()
                ->noAddress()
            ;

            $r = $h->addRoom();
            $r->setType($m['type']);
        }

        // Booked
        $checkIn = $this->http->FindSingleNode("//tr[count(*)=3 and *[2][not(normalize-space()) and .//img[contains(@src, 'slash')]]]/*[1]");
        $checkOut = $this->http->FindSingleNode("//tr[count(*)=3 and *[2][not(normalize-space()) and .//img[contains(@src, 'slash')]]]/*[3]");

        $h->booked()
            ->checkIn($this->normalizeDate($checkIn))
            ->checkOut($this->normalizeDate($checkOut))
            ->guests($this->http->FindSingleNode("//text()[" . $this->eq($this->t('Guests')) . "]/ancestor::tr[1]", null, true,
                "/" . $this->preg_implode($this->t("Guests")) . "\s*(\d+)/u"))
        ;

        // Price
        $total = $this->http->FindSingleNode("//text()[" . $this->eq($this->t('Total earnings')) . "][1]");

        if (!empty($total) && count(array_filter($h->toArray())) == 8) {
            if (preg_match("/" . $this->preg_implode($this->t("reservationSubject")) . "/", $this->subject)) {
                $h->general()
                    ->noConfirmation();
            } else {
                $email->removeItinerary($h);
                $email->setIsJunk(true);
            }
        }
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        if (isset(self::$dict['en'][$s])) {
            $mixed = array_unique(array_merge((array) self::$dict[$this->lang][$s], (array) self::$dict['en'][$s]));

            return $mixed;
        }

        return self::$dict[$this->lang][$s];
    }

    private function normalizeDate($date)
    {
        //$this->logger->debug('$date = '.print_r( $date,true));
        $in = [
            //            // 1: quarta-feira 10 de novembro de 2021; mercredi 5 janvier 2022; Sonntag 24. Oktober 2021; понедельник 27 сентября 2021 г.
            '/^\s*[^\d\s]+[,\s]+(\d+)[.]?\s+(?:de\s+)?([[:alpha:]]+)\s+(?:de\s+)?(\d{4})(?:\s*г\.)?\s*$/u',
            // 2: 2021년 11월 2일
            '/^\s*[^\d\s]*\s*(\d{4}) ?(?:年|년) ?(\d{1,2}) ?(?:月|월) ?(\d{1,2}) ?(?:日|일)\s*$/u',
        ];
        $out = [
            '$1 $2 $3', //1
            '$1-$2-$3', //2
        ];

        $date = preg_replace($in, $out, $date);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        return strtotime($date);
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
    }

    private function contains($field, string $node = 'normalize-space()', $operation = 'or'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }
        $operation = trim($operation);

        if ($operation !== 'and') {
            $operation = 'or';
        }
        $operation = ' ' . $operation . ' ';

        return '(' . implode($operation, array_map(function ($s) use ($node) {
            return 'contains(' . $node . ', "' . $s . '")';
        }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
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
