<?php

namespace AwardWallet\Engine\booking\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Booking extends \TAccountChecker
{
    public $mailFiles = "booking/it-107617116.eml, booking/it-19550189.eml, booking/it-19783174.eml, booking/it-21127213.eml, booking/it-35585535.eml, booking/it-464545549.eml, booking/it-4942550.eml, booking/it-671788639.eml"; // +1 bcdtravel(html)[ja]

    private $reBody = [
        'pt'     => ['Já falta pouco para a sua viagem', 'Todos os direitos reservados'],
        'pt2'    => ['tudo pronto para sua viagem', 'Data de check-in:'],
        'ja'     => ['Booking.com', 'チェックアウト：'],
        'ja2'    => ['もう少しで出発！', '予約番号：'],
        'en'     => ['Are you ready for your upcoming trip', 'Take your confirmation with you'],
        'en2'    => ['Are you ready for your upcoming trip', 'It’s almost time for your trip!'],
        'en3'    => ['Your trip is now just ', 'Check-in Date'],
        'en4'    => ['We are writing in response to your message', 'Check-in Date'],
        'en5'    => ['Your upcoming trip to', 'Check-in'],
        'es'     => ['Estás listo para irte de viaje', 'Fecha de check-in'],
        'es2'    => ['Queda poco para tu viaje', 'Fecha de check-in'],
        'fr'     => ['Avez-vous tout préparé pour votre voyage', "Date d'arrivée"],
        'ru'     => ['Вы готовы к своей поездке через', "Дата заезда"],
        'it'     => ['È tutto pronto per il tuo viaggio', "Data del check-in"],
        'it2'    => ['Numero di conferma', "Partenza"],
        'nl'     => ['Bent u klaar voor uw aankomende reis', "Incheckdatum"],
        'nl2'    => ['Ben je klaar voor je aankomende reis', "Incheckdatum"],
        'he'     => ['האם אתם מוכנים לקראת הטיול שלכם', "הטיול שלכם קרב ובא!"],
        'he2'    => ['אחרי זה תצטרכו לשלם דמי ביטול', "הנסיעה שלכם מתקרבת!"],
        'de'     => ['Sind Sie bereit für Ihre bevorstehende Reise', "Anreisedatum:"],
        'zh'     => ['就快出發去旅行！', "你準備好了嗎？"],
        'zh2'    => ['就快出发啦！', "你准备好了吗？"],
        'pl'     => ['Zbliża się Twój wyjazd! ', 'Data zameldowania:'],
        'ro'     => ['Sunteți gata pentru sejurul care', 'Data check-in:'],
        'lt'     => ['Beveik metas jūsų kelionei', 'Įsiregistravimo data'],
        'cs'     => ['Váš pobyt se blíží', 'Elektronická verze potvrzení'],
        'el'     => ['Έφτασε σχεδόν η ώρα για το ταξίδι σας', 'Επιβεβαίωση χωρίς εκτύπωση'],
        'da'     => ['Så er der ikke længe til din rejse!', 'Derefter er der et gebyr for afbestilling'],
        'sk'     => ['Váš pobyt začína o ', 'Začali ste už s prípravami?'],
    ];
    private $reSubject = [
        'pt'  => 'Um lembrete sobre a sua viagem',
        'ja'  => '旅行の日程が近づいています',
        'ru'  => 'Напоминание о вашей поездке',
        'it'  => 'Promemoria per il tuo viaggio',
        'en'  => 'ready to travel',
        'en2' => 'Ready for your upcoming trip?',
        'es'  => 'Un recordatorio sobre tu viaje',
        'ro'  => 'Un memento despre călătoria dumneavoastră',
        'fr'  => 'Petit rappel au sujet de votre séjour',
        'nl'  => 'Herinnering in verband met uw reis',
        'he'  => 'תזכורת לגבי הטיול שלכם',
        'תזכורת לגבי הנסיעה שלכם',
        'de'  => 'Erinnerung an Ihre Reise',
        'zh'  => '小提醒：關於即將入住訂單',
        'pl'  => 'Przypomnienie o Twojej podróży',
        'lt'  => 'Priminimas apie jūsų kelionę',
        'cs'  => 'Připomínka týkající se Vašeho pobytu',
        'el'  => 'Μια υπενθύμιση για το ταξίδι σας',
        'da'  => 'En påmindelse angående dit ophold',
        'sk'  => 'Pripomienka týkajúca sa vášho pobytu',
    ];
    private $lang = '';

    private static $dict = [
        'pt' => [
            'Booking number:' => ['Número da confirmação:', 'Número de confirmação:'],
            'Cancellation'    => 'cancelamento',
            'from'            => ['das', 'a partir das'],
            'to'              => ['às', 'até às', 'Até às'],
            'Check-in Date'   => 'Data de check-in:',
            'Check-out Date'  => 'Data de check-out:',
        ],
        'ja' => [
            'Booking number:' => '予約番号：',
            'Cancellation'    => 'キャンセル',
            'from'            => '',
            'to'              => '～',
            'Check-in Date'   => 'チェックイン：',
            'Check-out Date'  => 'チェックアウト：',
        ],
        'en' => [
            'Booking number:' => ['Booking number:', 'Confirmation Number:', 'Confirmation number:', 'Confirmation number'],
            'Cancellation'    => ['cancellation', 'Cancellation'],
            'to'              => ['to', 'until'],
            'Check-in Date'   => ['Check-in Date', 'Check-in'],
            'Check-out Date'  => ['Check-out Date', 'Check-out'],
            'junk'            => ['ATT00', 'Make your trip even more special', 'See if you can get a better room'],
        ],
        'es' => [
            'Booking number:' => 'Número de confirmación:',
            'Cancellation'    => 'cancelación',
            'from'            => ['de', 'desde las'],
            'to'              => ['a', 'hasta las'],
            'Check-in Date'   => 'Fecha de check-in:',
            'Check-out Date'  => 'Fecha de check-out',
        ],
        'ru' => [
            'Booking number:' => 'Номер бронирования:',
            'Cancellation'    => 'отмена',
            'from'            => 'с',
            'to'              => 'до',
            'Check-in Date'   => 'Дата заезда:',
            //            'Check-out Date' => '',
        ],
        'it' => [
            'Booking number:'         => ['Numero di conferma:', 'Numero di conferma'],
            'Cancellation'            => ['cancellazione', 'Gestisci la prenotazione'],
            'from'                    => 'dalle',
            'to'                      => ['fino alle', 'alle'],
            'Check-in Date'           => ['Data del check-in:', 'Arrivo'],
            'Check-out Date'          => ['Data del check-out:', 'Partenza'],
            'Paperless confirmation'  => ['Apri la conferma digitale'],
            'Paperless confirmation2' => ['vedi se puoi ottenere una camera migliore'],
        ],
        'fr' => [
            'Booking number:' => 'Numéro de réservation :',
            'Cancellation'    => 'annuler',
            'from'            => ['de', 'à partir de'],
            'to'              => ['à', "jusqu'à"],
            'Check-in Date'   => "Date d'arrivée :",
            'Check-out Date'  => 'Date de départ :',
        ],
        'nl' => [
            'Booking number:' => 'Bevestigingsnummer:',
            'Cancellation'    => 'annuleren',
            'from'            => ['tussen', 'vanaf'],
            'to'              => ['en', "tot"],
            'Check-in Date'   => "Incheckdatum:",
            'Check-out Date'  => 'Uitcheckdatum:',
        ],
        'he' => [
            'Booking number:' => 'מספר אישור הזמנה:',
            'Cancellation'    => 'לבטל',
            'from'            => ['מ-'],
            'to'              => ['עד'],
            'Check-in Date'   => "תאריך צ'ק-אין:",
            //            'Check-out Date' => '',
        ],
        'de' => [
            'Booking number:' => 'Buchungsnummer:',
            'Cancellation'    => 'Stornierung',
            'from'            => ['von', 'ab'],
            'to'              => ['bis'],
            'Check-in Date'   => "Anreisedatum:",
            //            'Check-out Date' => '',
        ],
        'zh' => [
            'Booking number:' => ['確認函編號：', '确认订单号：'],
            'Cancellation'    => ['免費取消', '取消'],
            'from'            => ['自', ''],
            'to'              => ['到', '～'],
            'Check-in Date'   => "入住日期：",
            'Check-out Date'  => '退房日期：',
        ],
        'pl' => [
            'Booking number:' => 'Potwierdzenie rezerwacji nr:',
            'Cancellation'    => 'odwołać rezerwację',
            'from'            => ['od'],
            'to'              => ['do'],
            'Check-in Date'   => "Data zameldowania:",
            'Check-out Date'  => 'Data wymeldowania:',
        ],
        'ro' => [
            'Booking number:' => 'Numărul confirmării:',
            'Cancellation'    => 'Anularea',
            'from'            => ['de la'],
            'to'              => ['la', 'până la'],
            'Check-in Date'   => "Data check-in:",
            'Check-out Date'  => 'Data check-out:',
        ],
        'lt' => [
            'Booking number:' => 'Užsakymo numeris:',
            'Cancellation'    => 'atšaukti',
            'from'            => ['nuo'],
            'to'              => ['iki'],
            'Check-in Date'   => "Įsiregistravimo data:",
            'Check-out Date'  => 'Išsiregistravimo data:',
        ],
        'cs' => [
            'Booking number:' => 'Číslo rezervace:',
            'Cancellation'    => 'Zrušení',
            'from'            => ['od'],
            'to'              => ['do'],
            'Check-in Date'   => "Check-in:",
            'Check-out Date'  => 'Check-out:',
        ],
        'el' => [
            'Booking number:' => 'Αριθμός επιβεβαίωσης:',
            'Cancellation'    => 'ακύρωση',
            'from'            => ['από'],
            'to'              => ['έως'],
            'Check-in Date'   => "Ημερομηνία check-in:",
            'Check-out Date'  => 'Ημερομηνία check-out:',
        ],
        'da' => [
            'Booking number:' => 'Bekræftelsesnummer:',
            'Cancellation'    => 'afbestille',
            'from'            => ['fra'],
            'to'              => ['til', 'indtil'],
            'Check-in Date'   => "Indtjekningsdato:",
            'Check-out Date'  => 'Udtjekningsdato:',
        ],
        'sk' => [
            'Booking number:' => 'Číslo rezervácie:',
            'Cancellation'    => 'Rezerváciu je možné zrušiť',
            'from'            => ['od'],
            'to'              => ['do'],
            'Check-in Date'   => "Check-in:",
            'Check-out Date'  => 'Check-out:',
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $this->parseEmail($email);

        return $email;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,".booking.com/") or contains(@href,"www.booking.com") or contains(@href,"secure.booking.com")]')->length === 0) {
            return false;
        }

        return $this->assignLang();
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->reSubject as $reSubject) {
            if (stripos($headers["subject"], $reSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, 'Booking.com') !== false
            || stripos($from, '@booking.com') !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseEmail(Email $email): void
    {
        $patterns = [
            'time'  => '\d{1,2}(?::\d{2})?(?:\s*[AaPp]\.?[Mm]\.?|\s*noon)?(?:まで|から)?', // 4:19PM    |    2:00 p.m.    |    3pm    |    12 noon
            'phone' => '[+(\d][-+. \d)(]{5,}[\d)]', // +377 (93) 15 48 52    |    (+351) 21 342 09 07    |    713.680.2992
        ];
        $patterns['times'] = "#^(?<date>.+?)(?:{$this->opt($this->t('from'))}\s*(?:kl\.\s*)?(?<time1>{$patterns['time']}))?(?:\s*{$this->opt($this->t('to'))}\s*(?:kl\.\s*)?(?<time2>{$patterns['time']}))?\s*$#u";

        $h = $email->add()->hotel();
        $confNo = $this->nextText($this->t("Booking number:"));
        $h->general()
            ->confirmation($confNo);

        $xpathFragment1 = '//a[contains(@href,"pbsource=email_map") or contains(@href,"pbsource%3Demail_map") or contains(@href,"pbsource-3Demail-5Fmap")]';

        $hotelName = $this->http->FindSingleNode($xpathFragment1 . '/preceding::text()[normalize-space(.)][1]');

        if (empty($hotelName)) {
            $hotelName = $this->http->FindSingleNode("//a[{$this->contains($this->t('Paperless confirmation'))}]/following::text()[normalize-space()][not({$this->contains($this->t('junk'))})][1]");
        }

        if (empty($hotelName)) {
            $hotelName = $this->http->FindSingleNode("//a[{$this->contains($this->t('Paperless confirmation2'))}]/following::text()[normalize-space()][not({$this->contains($this->t('junk'))})][1]");
        }

        if (empty($hotelName)) {
            //text()[]
            $rulePhone = "//text()[starts-with(translate(translate(normalize-space(),' +()-',''),'0123456789','ddddddddddd'),'dddddddddd')]";
            $hotelName = $this->http->FindSingleNode("//text()[{$rulePhone}][not(contains(normalize-space(),'{$confNo}'))][./following::text()[normalize-space()!=''][1][{$this->contains($this->t('Booking number:'))}]]/preceding::text()[normalize-space()!=''][1]");
            $phone = $this->http->FindSingleNode("//text()[{$rulePhone}][not(contains(normalize-space(),'{$confNo}'))][./following::text()[normalize-space()!=''][1][{$this->contains($this->t('Booking number:'))}]]", null, true, '/^(' . $patterns['phone'] . ')$/');
            $h->hotel()
                ->name($hotelName)
                ->noAddress()
                ->phone($phone, true, true);
        } elseif ($this->http->XPath->query("//text()[normalize-space()='Numero di conferma']/ancestor::tr[2]/preceding::text()[normalize-space()][3]")->length > 0) {
            $h->hotel()
                ->name($hotelName)
                ->address($this->http->FindSingleNode("//text()[normalize-space()='Numero di conferma']/ancestor::tr[2]/preceding::text()[normalize-space()][2]"))
                ->phone($this->http->FindSingleNode("//text()[normalize-space()='Numero di conferma']/ancestor::tr[2]/preceding::text()[normalize-space()][1]", null, true, "/^([+][\d\s\(\)\-]+)$/"));
        } elseif ($this->http->XPath->query("//a[{$this->contains($this->t('Paperless confirmation'))}]/following::text()[{$this->contains($hotelName)}]")->length > 0) {
            $h->hotel()
                ->name($hotelName)
                ->address($this->http->FindSingleNode("//a[{$this->contains($this->t('Paperless confirmation'))}]/following::text()[starts-with(translate(translate(normalize-space(),' +()-',''),'0123456789','ddddddddddd'),'dddddddddd')][1]/preceding::text()[normalize-space()][1]"))
                ->phone($this->http->FindSingleNode("//a[{$this->contains($this->t('Paperless confirmation'))}]/following::text()[starts-with(translate(translate(normalize-space(),' +()-',''),'0123456789','ddddddddddd'),'dddddddddd')][1]"));
        } else {
            $h->hotel()
            ->name($hotelName)
            ->address($this->http->FindSingleNode($xpathFragment1))
            ->phone($this->http->FindSingleNode($xpathFragment1 . '/following::text()[normalize-space(.)][1]', null, true, '/^(' . $patterns['phone'] . ')$/'), true, true)
        ;
        }

        $dateCheckIn = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Check-in Date'))}]/ancestor::tr[1]/following-sibling::tr[1]/td[1]");

        if (!$dateCheckIn) {
            $dateCheckIn = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Check-in Date'))}]/ancestor::td[1]", null, false, "#{$this->opt($this->t('Check-in Date'))}[:\s]*(.+)#");
        }

        if (!$dateCheckIn) {
            $dateCheckIn = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Check-in Date'))}]/ancestor::tr[1]/following::tr[1]/ancestor::td[1]", null, false, "#{$this->opt($this->t('Check-in Date'))}[:\s]*(.+)#");
        }

        if ($dateCheckIn) {
            $dateCheckIn = trim(str_replace(['（', '）'], '', $dateCheckIn));

            if (preg_match($patterns['times'], $dateCheckIn, $m) && !empty($m['date'])) {
                if (!empty($m['time1'])) {
                    $dateCheckInNormal = $this->normalizeDate($m['date'] . $m['time1']);
                } else {
                    $dateCheckInNormal = $this->normalizeDate($m['date']);
                }
            }
            $h->booked()
                ->checkIn(strtotime($dateCheckInNormal));
        }

        $dateCheckOut = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Check-in Date'))}]/ancestor::tr[1]/following-sibling::tr[1]/td[2]");

        if (!$dateCheckOut) {
            $dateCheckOut = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Check-out Date'))}]/ancestor::td[1]", null, false, "#{$this->opt($this->t('Check-out Date'))}[:\s]*(.+)#");
        }

        if (!$dateCheckOut) {
            $dateCheckOut = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Check-out'))}]/ancestor::tr[1]/following::tr[1]/ancestor::td[1]", null, false, "#{$this->opt($this->t('Check-out Date'))}[:\s]*(.+)#");
        }

        if (!$dateCheckOut) {
            $dateCheckOut = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Check-out Date'))}]/ancestor::tr[1]/following::tr[1]/ancestor::td[1]", null, false, "#{$this->opt($this->t('Check-out Date'))}[:\s]*(.+)#");
        }

        if ($dateCheckOut) {
            $dateCheckOut = trim(str_replace(['（', '）'], '', $dateCheckOut));

            if (preg_match($patterns['times'], $dateCheckOut, $m) && !empty($m['date'])) {
                if (!empty($m['time2'])) {
                    $dateCheckOutNormal = $this->normalizeDate($m['date'] . $m['time2']);
                } elseif (empty(array_filter((array) $this->t('from'))) && !empty($m['time1']) && !isset($m['time2'])) {
                    $dateCheckOutNormal = $this->normalizeDate($m['date'] . $m['time1']);
                } else {
                    $dateCheckOutNormal = $this->normalizeDate($m['date']);
                }
            }
            $h->booked()
                ->checkOut(strtotime($dateCheckOutNormal));
        }

        $cancellation = $this->http->FindSingleNode("//a[" . $this->eq($this->t('Cancellation')) . "]/ancestor::td[1]");

        if (empty($cancellation)) {
            $cancellation = $this->http->FindSingleNode("//a[" . $this->contains($this->t('Cancellation')) . "]/ancestor::td[1]");
        }

        if (empty($cancellation)) {
            $cancellation = $this->http->FindSingleNode("//text()[" . $this->contains($this->t('Manage your booking')) . "]/following::text()[normalize-space()][1]");
        }

        if (empty($cancellation)) {
            $cancellation = $this->http->FindSingleNode("//text()[" . $this->contains($this->t('Cancellation')) . "]/following::text()[normalize-space()][1]");
        }

        if ($cancellation) {
            $h->general()->cancellation($cancellation);
            $this->detectDeadLine($h, $cancellation);
        }
    }

    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h, string $cancellationText): void
    {
        if (!empty($h->getCheckOutDate()) && (
            preg_match("#Your FREE cancellation is still available before (?<time>\d+:\d+(?: ?[ap]m)?) on (?:(?<day1>\d+) (?<month1>\w+)|(?<month2>\w+) (?<day2>\d+)),\s*\D+#ui", $cancellationText, $m)
            || preg_match("#FREE Cancellation (?:for this reservation is available|is exclusively available for this reservation) until (?:(?<day1>\d+) (?<month1>\w+)|(?<month2>\w+) (?<day2>\d+))[,.]\s*\D+#ui", $cancellationText, $m)
            || preg_match("#You can still view, modify your booking or cancel for free until\s*(?<time>[\d\:]+)\s*on\s*(?<day1>\d+)\s*(?<month1>\w+)\.#ui", $cancellationText, $m)
            || preg_match("#You can still view\, modify\, or cancel your booking for free until\s*(?<time>[\d\:]+\s*A?P?M?)\s*on\s*(?<month1>\w+)\s*(?<day1>\d+)\.#ui", $cancellationText, $m)
            || preg_match("#La cancelación GRATIS está disponible hasta las (?<time>\d+:\d+(?: ?[ap]m)?) del (?<day1>\d+) de (?<month1>\w+),\s*\D+#ui", $cancellationText, $m)
            || preg_match("#Vous pouvez encore annuler GRATUITEMENT avant le (?<day1>\d+) (?<month1>\w+) à (?<time>\d+:\d+(?: ?[ap]m)?),\s*\D+#ui", $cancellationText, $m)
            || preg_match("#БЕСПЛАТНАЯ отмена возможна до (?<time>\d+:\d+(?: ?[ap]m)?) (?<day1>\d+) (?<month1>\w+) \(#ui", $cancellationText, $m)
            || preg_match("#La cancellazione GRATUITA è ancora disponibile fino alle (?<time>\d+:\d+(?: ?[ap]m)?) del (?<day1>\d+) (?<month1>\w+) \(#ui", $cancellationText, $m)
            || preg_match("#^\s*(?<month1>\w+)月(?<day1>\d+)日 (?<time>\d+:\d+(?: ?[ap]m)?)\（#ui", $cancellationText, $m) // ja
            || preg_match("#Ihre KOSTENLOSE Stornierung wird bis (?<time>\d+:\d+(?: ?[ap]m)?) am (?<day1>\d+).? (?<month1>\w+) \(#ui", $cancellationText, $m) //de
            || preg_match("#Je kunt nog GRATIS annuleren vóór (?<time>\d+:\d+(?: ?[ap]m)?) op (?<day1>\d+) (?<month1>\w+), lokale tijd#ui", $cancellationText, $m) //nl
            || preg_match("#Możesz BEZPŁATNIE odwołać rezerwację przed godz\. (?<time>\d+:\d+) \([^\)]+\) w dniu (?<day1>\d+) (?<month1>\w+)\.#ui", $cancellationText, $m) //pl
            || preg_match("#Seu cancelamento GRATUITO ainda está disponível antes das (?<time>\d+:\d+) do dia (?<day1>\d+) de (?<month1>\w+), horário de#ui", $cancellationText, $m) //pt
            || preg_match("#Zrušení ZDARMA je k dispozici \w+ (?<day1>\d+)\. (?<month1>\w+), (?<time>\d+:\d+)\s*\(#ui", $cancellationText, $m) //cs
            || preg_match("#Du kan stadig afbestille GRATIS inden kl. (?<time>\d+.\d+) d. (?<day1>\d+). (?<month1>\w+) \(#ui", $cancellationText, $m) //da
            || preg_match("#Puoi ancora vedere, modificare o cancellare la tua prenotazione gratuitamente fino alle\s*(?<time>[\d\:]+)\s*del\s*(?<day1>\d+)\s*(?<month1>\w+)\.#ui", $cancellationText, $m) //da
        )) {
            $day = ($m['day1'] ?? '') . ($m['day2'] ?? '');
            $month = ($m['month1'] ?? '') . ($m['month2'] ?? '');

            if (is_numeric($month)) {
                $d = '.';
            } else {
                if ($en = MonthTranslate::translate($month, $this->lang)) {
                    $month = $en;
                }
                $d = ' ';
            }

            $deadlineDateText = $day . $d . $month . $d . date("Y", $h->getCheckOutDate());
            $deadlineDateText .= (!empty($m['time'])) ? ', ' . str_replace('.', ':', $m['time']) : '';

            $deadlineDate = strtotime($deadlineDateText);

            if (!empty($deadlineDate)) {
                $h->booked()
                    ->deadline($this->correctDate($deadlineDate, $h->getCheckOutDate()));
            }

            return;
        }

        if (preg_match("#^Free Cancellation for this reservation is available until (?<date>.+?) (?<time>\d+:\d+) [A-Z]{3,}\.#",
            $cancellationText, $m)) {
            $h->booked()
                ->deadline(strtotime($this->normalizeDate($m['date'] . ', ' . $m['time'])));
        }
    }

    private function correctDate($deadlineDate, $checkinDate)
    {
        if (!empty($deadlineDate) && !empty($checkinDate)) {
            if ($deadlineDate > $checkinDate) {
                $deadlineDate = strtotime("-1 year", $deadlineDate);
            }

            return $deadlineDate;
        }

        return false;
    }

    private function normalizeDate($str)
    {
        //		$year = date("Y", $this->date);
        $str = preg_replace("#(.+-) （(\d+:\d+)まで\s*#u", '$1 $2', $str);
        //$this->logger->info($str);
        $in = [
            // sab 8 ago 2020 - dalle 15:00 alle 19:00
            "#^[\D]+\s+(\d+)\s+([\D]+)\s+(\d{4})\s*-\s*dalle\s*\s*(\d+:\d+).+?$#", // it
            '/^(\d{4}) ?年 ?(\d{1,2}) ?月 ?(\d{1,2}) ?日 ?\D+(\d+:\d+)\D*$/u', // 2018年8月13日(土) - 23:00
            '/^\w+,\s+(\w+)\s+(\d{1,2}),\s+(\d{2,4})\s+\-\s+(?:until|hasta las)\s+(\d+:\d+)$/',
            '/^[^\d]+[\.,]*\s+(\d{1,2})\s+(?:de\s+)?(\w+)[.]?\s+(?:de\s+)?(\d{2,4})\s*\-\s*(\d+:\d+)$/u',
            "#^[^\d\s]+\s+(\d+)\s+([^\d\s]+)\s+(\d{4})\s*(\d+:\d+)$#",
            "#^[^\d\s]+,?\s+([^\d\s]+)\s+(\d+)[,.]\s+(\d{4})\s+(\d+:\d+)$#",

            "#^\s*\D+\s+(\d+)[,.\s]+([^\d\s\.\,]+)[\s.,]+(\d{4})[\s\-]+(\d+:\d+)\s*$#", // Fr., 17. Mai. 2019 - 15:00; tor. d. 19. aug. 2021 - 15:00
            "#^[^\d\s]+,?\s+([^\d\s]+)\s+(\d+),\s+(\d{4})\s+-\s*(\d+:\d+)$#",
            "#^[^\d\s]+[,\s]+(\d+)\s+([^\d\s\.\,]+)[,.\s]+(\d{4})\s*-\s*(?:until|hasta las)?\s*(\d+:\d+)$#",
            "#^[^\d\s]+,\s+(\d+)\.\s+([^\d\s]+)\s+(\d{4})$#",
            "#^[^\d\s]+\s+(\d+)\s+([^\d\s]+)\s+(\d{4})$#",
            // 2021 m. bir 4 d., pn - 12:00
            "#^\s*(\d{4})\s*m\.\s*([^\d\s\.\,]+)\s+(\d+)\s*d\.[,. ]+[^\d\s]{1,3}[\s\-]+(\d+:\d+)\s*$#",
        ];
        $out = [
            '$1 $2 $3, $4',
            '$2/$3/$1, $4',
            '$2 $1 $3, $4',
            '$1 $2 $3, $4',
            "$1 $2 $3, $4",
            "$2 $1 $3, $4",

            "$1 $2 $3, $4",
            "$2 $1 $3, $4",
            "$1 $2 $3, $4",
            "$1 $2 $3",
            "$1 $2 $3",
            "$3 $2 $1, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

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
        foreach ($this->reBody as $lang => $reBody) {
            if ($this->http->XPath->query("//*[{$this->contains($reBody[0])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->contains($reBody[1])}]")->length > 0
            ) {
                $this->lang = substr($lang, 0, 2);

                return true;
            }
        }

        return false;
    }

    private function nextText($field, $root = null)
    {
        $rule = $this->eq($field);

        return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::text()[normalize-space(.)][1]", $root);
    }

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'normalize-space(' . $node . ')="' . $s . '"'; }, $field)) . ')';
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'contains(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) { return preg_quote($s, '/'); }, $field)) . ')';
    }
}
