<?php

namespace AwardWallet\Engine\airbnb\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Common\Hotel;
use AwardWallet\Schema\Parser\Email\Email;

class YourReceipt extends \TAccountChecker
{
    public $mailFiles = "airbnb/it-24351542.eml, airbnb/it-24632511.eml, airbnb/it-24969230.eml, airbnb/it-25904936.eml, airbnb/it-26070012.eml, airbnb/it-26821781.eml, airbnb/it-26935157.eml, airbnb/it-42943950.eml, airbnb/it-45666032.eml, airbnb/it-46148379.eml, airbnb/it-46253267.eml, airbnb/it-46300404.eml, airbnb/it-71093514.eml, airbnb/it-73937846.eml, airbnb/it-94935910.eml";
    public static $dict = [
        'id' => [
            'nights in ' => ["malam di"],
            //			'Hosted by' => "",
            'Confirmation code:' => "Kode konfirmasi:",
            //			'Go to listing' => "",
            'Traveler:'                                          => ["Traveler:", "Travelers:"],
            'Cancellation policy:'                               => ["Kebijakan pembatalan"],
            "service fee is non-refundable for this reservation" => ["service fee is non-refundable for this reservation", "The rest of the reservation is non-refundable."],
            'Price breakdown'                                    => "Detail harga",
            //			'Total' => "",
            //			'Adjusted total' => ["Adjusted total", "New total"],
            //            'Reservation change:' => '',
            //            'cancellation' => '',
            //            'Go to itinerary' => '', // button
        ],
        'en' => [
            'nights in ' => ["nights in ", "night in ", "nights in "],
            //			'Hosted by' => "",
            //			'Confirmation code:' => "",
            //			'Go to listing' => "",
            'Traveler:'                                          => ["Traveler:", "Travelers:"],
            'Cancellation policy:'                               => ["Cancellation policy:", "Non-refundable reservation", "Cancellation policy"],
            "service fee is non-refundable for this reservation" => ["service fee is non-refundable for this reservation", "The rest of the reservation is non-refundable.",
                "This reservation is non-refundable.",
            ],
            'Price breakdown'                                    => "Price breakdown",
            //			'Total' => "",
            'Adjusted total' => ["Adjusted total", "New total"],
            //            'Reservation change:' => '',
            //            'cancellation' => '',
        ],
        'cs' => [
            'nights in ' => ["noci/í ve městě "],
            //			'Hosted by' => "",
            'Confirmation code:' => ["Potvrzovací kód:", "Confirmation code:"],
            'Go to listing'      => "Přejít na nabídku",
            //			'Traveler:' => [""],
            'Cancellation policy:' => ["Storno podmínky"],
            //			"service fee is non-refundable for this reservation" => ["service fee is non-refundable for this reservation", "The rest of the reservation is non-refundable."],
            'Price breakdown' => "Cenový rozpis",
            'Total'           => "Celkem",
            //			'Adjusted total' => ["Adjusted total", "New total"],
            //            'Reservation change:' => '',
            //            'cancellation' => '',
        ],
        'da' => [
            'nights in ' => [" nætter i ", ' nat i '],
            //			'Hosted by' => "",
            'Confirmation code:' => ["Bekræftelseskode:", "Confirmation code:"],
            'Go to listing'      => "Gå til opslag",
            //			'Traveler:' => [""],
            'Cancellation policy:' => "Annulleringspolitik",
            //			"service fee is non-refundable for this reservation" => ["service fee is non-refundable for this reservation", "The rest of the reservation is non-refundable."],
            'Price breakdown' => ["Prisoversigt"],
            'Total'           => "I alt",
            //			'Adjusted total' => ["Adjusted total", "New total"],
            //            'Reservation change:' => '',
            //            'cancellation' => '',
        ],
        'zh' => [
            'nights in ' => ["晚"],
            //			'Hosted by' => "",
            'Confirmation code:' => ["确认码：", "確認碼："],
            'Go to listing'      => "前往房源",
            //			'Traveler:' => [""],
            'Cancellation policy:'                               => ["取消政策", '《退訂政策》'],
            "service fee is non-refundable for this reservation" => [
                "這筆預訂不可退款。", ],
            'Price breakdown'     => ["价格明细", "價格明細"],
            'Total'               => ["已退还金额", "总价", '總價'],
            'Adjusted total'      => ["Total adjustment", '新總計'],
            'Reservation change:' => ['预订更改：', '預訂變更：', '預訂更改：'],
            'cancellation'        => ['取消预订', '退訂'],
        ],
        'fr' => [
            'nights in '           => ["night in ", "nights in ", "nuits à"],
            'Hosted by'            => "Proposé par",
            'Confirmation code:'   => "Code de confirmation :",
            'Go to listing'        => "Accéder à l'annonce",
            'Traveler:'            => ["Traveler:", "Travelers:"],
            'Cancellation policy:' => ["Conditions d'annulation :", "Conditions d'annulation"],
            //			"service fee is non-refundable for this reservation" => "",
            'Price breakdown' => "Détail du prix",
            'Total'           => "Total",
            'Adjusted total'  => ["Total régularisé", "Nouveau total"],
            //            'Reservation change:' => '',
            //            'cancellation' => '',
        ],
        'it' => [
            'nights in '                                         => ["notti a ", "notte a "],
            'Hosted by'                                          => "Ospitato da ",
            'Confirmation code:'                                 => "Codice di conferma:",
            'Go to listing'                                      => "Vai all'annuncio",
            'Traveler:'                                          => ["Viaggiatore:", "Viaggiatori:"],
            'Cancellation policy:'                               => ["Termini di cancellazione:", 'Termini di cancellazione'],
            "service fee is non-refundable for this reservation" => "I costi del servizio di Airbnb non sono rimborsabili per questa prenotazione",
            'Price breakdown'                                    => "Dettaglio sui prezzi",
            'Total'                                              => "Totale",
            'Adjusted total'                                     => ["Totale modificato", " Nuovo totale"],
            'Reservation change:'                                => 'Modifica della prenotazione:',
            'cancellation'                                       => 'cancellazione',
        ],
        'es' => [
            'nights in '           => ["noches en ", "noche en "],
            'Hosted by'            => "Anfitrión:",
            'Confirmation code:'   => "Código de confirmación:",
            'Go to listing'        => "Ir al anuncio",
            'Traveler:'            => ["Viajero:", "Viajeros:"],
            'Cancellation policy:' => ["Política de cancelación:", 'Política de cancelación', "Política de cancelación"],
            //			"service fee is non-refundable for this reservation" => "",
            'Price breakdown'     => "Desglose del precio",
            'Total'               => "Total",
            'Adjusted total'      => ["Total adjustment", 'Nuevo total'],
            'Reservation change:' => 'Cambio en la reserva:',
            'cancellation'        => 'cancelación',
        ],
        'de' => [
            'nights in '           => ["Nächte in ", "Nächt in ", "Nacht in "],
            'Hosted by'            => "Gastgeber:",
            'Confirmation code:'   => "Bestätigungscode:",
            'Go to listing'        => ["Go to listing", "Zum Inserat"],
            'Traveler:'            => ["Reisende:", "Reisender:"],
            'Cancellation policy:' => ["Stornierungsbedingungen:", "Stornierungsbedingungen"],
            //			"service fee is non-refundable for this reservation" => "",
            'Price breakdown'     => "Preisübersicht",
            'Total'               => "Gesamtbetrag",
            'Adjusted total'      => "Neuer Gesamtbetrag",
            'Reservation change:' => 'Buchungsänderung: ',
            'cancellation'        => 'Stornierung',
        ],
        'tr' => [
            'nights in ' => [" bölgesinde "],
            //			'Hosted by' => "",
            'Confirmation code:' => "Onay kodu:",
            'Go to listing'      => ["Kayda git"],
            //			'Traveler:' => [],
            'Cancellation policy:' => "İptal politikası:",
            //			"service fee is non-refundable for this reservation" => "",
            'Price breakdown' => "Fiyat dökümü",
            'Total'           => "Toplam",
            //			'Adjusted total' => "",
            //            'Reservation change:' => '',
            //            'cancellation' => '',
        ],
        'pt' => [
            'nights in ' => ["noites em ", "noite em "],
            //			'Hosted by' => "",
            'Confirmation code:' => "Código de confirmação:",
            'Go to listing'      => ["Acesse o anúncio", "Ir para o anúncio", "Vá para o anúncio"],
            //			'Traveler:' => [],
            'Cancellation policy:' => ["Política de cancelamento:", "Política de cancelamento"],
            //			"service fee is non-refundable for this reservation" => "",
            'Price breakdown'     => ["Detalhamento do preço", "Descrição de preço"],
            'Total'               => "Total",
            'Adjusted total'      => ["Total do ajuste", "Novo valor total"],
            'Reservation change:' => 'Alteração na reserva:',
            'cancellation'        => 'cancelamento',
        ],
        'ru' => [
            'nights in ' => ["ночь в ", "Ночей в "],
            //			'Hosted by' => "",
            'Confirmation code:' => "Код подтверждения:",
            'Go to listing'      => ["К объявлению"],
            //			'Traveler:' => [],
            'Cancellation policy:' => ["Правила отмены:", "Правила отмены"],
            //			"service fee is non-refundable for this reservation" => "",
            'Price breakdown' => "Детализация цены",
            'Total'           => "Итого",
            //			'Adjusted total' => "",
            //            'Reservation change:' => '',
            //            'cancellation' => '',
        ],
        'nl' => [
            'nights in ' => ["nachten in ", "nacht in "],
            //			'Hosted by' => "",
            'Confirmation code:' => "Bevestigingscode:",
            'Go to listing'      => ["Ga naar advertentie"],
            //			'Traveler:' => [],
            'Cancellation policy:' => ["Annuleringsvoorwaarden"],
            //			"service fee is non-refundable for this reservation" => "",
            'Price breakdown'     => "Prijsopbouw",
            'Total'               => "Totaal",
            'Adjusted total'      => ["Totale aanpassing", "Nieuw totaalbedrag"],
            'Reservation change:' => 'Reserveringswijziging:',
            //            'cancellation' => '',
        ],
        'el' => [
            'nights in ' => ["διανυκτερεύσεις στην/στο ", "διανυκτέρευση στην/στο "],
            //			'Hosted by' => "",
            'Confirmation code:' => "Κωδικός επιβεβαίωσης:",
            'Go to listing'      => ["Μετάβαση στην καταχώρηση"],
            //			'Traveler:' => [],
            'Cancellation policy:' => ["Πολιτική ακύρωσης"],
            //			"service fee is non-refundable for this reservation" => "",
            'Price breakdown' => "Ανάλυση τιμής",
            'Total'           => "Σύνολο",
            //			'Adjusted total' => "",
            //            'Reservation change:' => '',
            //            'cancellation' => '',
        ],
        'lv' => [
            'nights in ' => ["nakts/naktis:"],
            //			'Hosted by' => "",
            'Confirmation code:' => "Apstiprinājuma kods:",
            'Go to listing'      => ["Doties uz sludinājumu"],
            //			'Traveler:' => [],
            'Cancellation policy:' => ["Atcelšanas politika"],
            //			"service fee is non-refundable for this reservation" => "",
            'Price breakdown' => "Cenu sadalījums",
            'Total'           => "Kopā",
            //			'Adjusted total' => "",
            //            'Reservation change:' => '',
            //            'cancellation' => '',
        ],
        'no' => [
            'nights in ' => ["netter i", 'natt i'],
            //			'Hosted by' => "",
            'Confirmation code:' => "Bekreftelseskode:",
            'Go to listing'      => ["Gå til utleiestedet"],
            //			'Traveler:' => [],
            'Cancellation policy:' => ["Kanselleringsvilkår"],
            //			"service fee is non-refundable for this reservation" => "",
            'Price breakdown'     => "Prisoversikt",
            'Total'               => "Total",
            'Adjusted total'      => "Ny total",
            'Reservation change:' => 'Reservasjonsendring:',
            'cancellation'        => 'kansellering',
        ],
        'ca' => [
            'nights in ' => ["nits a", ' nit a '],
            //'Hosted by' => "",
            'Confirmation code:' => "Codi de confirmació:",
            'Go to listing'      => "Accedeix a l'anunci",
            //'Traveler:' => [""],
            'Cancellation policy:' => "Política de cancel·lació",
            //"service fee is non-refundable for this reservation" => "",
            'Price breakdown' => "Desglossament del preu",
            'Total'           => "Total",
            //'Adjusted total' => "",
            //            'Reservation change:' => '',
            //            'cancellation' => '',
        ],
        'pl' => [
            'nights in ' => ["dni w:"],
            //'Hosted by' => "",
            'Confirmation code:' => "Kod potwierdzenia:",
            'Go to listing'      => "Przejdź do oferty",
            //'Traveler:' => [""],
            'Cancellation policy:'                               => "Zasady anulowania",
            "service fee is non-refundable for this reservation" => "Ta rezerwacja nie podlega zwrotowi.", // nonrefundable
            'Price breakdown'                                    => "Kalkulacja",
            'Total'                                              => "Łącznie",
            'Adjusted total'                                     => "Nowa suma",
            'Reservation change:'                                => 'Zmiana rezerwacji:',
            'cancellation'                                       => 'anulowanie',
        ],
        'he' => [
            'nights in ' => ["לילות בעיר", 'לילה בעיר'],
            //'Hosted by' => "",
            'Confirmation code:' => "קוד אישור:",
            'Go to listing'      => "לצפייה בדף הנכס",
            //'Traveler:' => [""],
            'Cancellation policy:' => "מדיניות ביטולים",
            //            "service fee is non-refundable for this reservation" => "Ta rezerwacja nie podlega zwrotowi.", // nonrefundable
            'Price breakdown'     => "פירוט המחיר",
            'Total'               => "סה\"כ ",
            'Adjusted total'      => "סכום כולל חדש",
            'Reservation change:' => 'שינוי הזמנה:',
            'cancellation'        => 'ביטול',
        ],
        'sv' => [
            'nights in ' => ["natt i ", 'nätter i '],
            //'Hosted by' => "",
            'Confirmation code:' => "Bekräftelsekod:",
            'Go to listing'      => "Gå till annons",
            //'Traveler:' => [""],
            'Cancellation policy:' => "Avbokningspolicy",
            //            "service fee is non-refundable for this reservation" => "Ta rezerwacja nie podlega zwrotowi.", // nonrefundable
            'Price breakdown'     => "Prisspecifikation",
            'Total'               => "Totalt",
            'Adjusted total'      => "Nytt totalbelopp",
            'Reservation change:' => 'Bokningsändring:',
            'cancellation'        => 'annullering',
        ],
        'sk' => [
            'nights in ' => ["noc v meste", ' v destinácii'], // Počet nocí 3 v destináciiŽilina.
            //'Hosted by' => "",
            'Confirmation code:' => "Potvrdzovací kód:",
            'Go to listing'      => "Prejsť na ponuku",
            //'Traveler:' => [""],
            'Cancellation policy:'                               => "Storno podmienky",
            "service fee is non-refundable for this reservation" => "Za túto rezerváciu vám nebudú vrátené peniaze.", // nonrefundable
            'Price breakdown'                                    => "Rozpis ceny",
            'Total'                                              => "Spolu",
            'Adjusted total'                                     => "Nová celková suma",
            'Reservation change:'                                => 'Zmena rezervácie:',
            'cancellation'                                       => 'storno',
        ],
        'fi' => [
            'nights in ' => ["yö kaupungissa", 'yötä kaupungissa'],
            //'Hosted by' => "",
            'Confirmation code:' => "Varauskoodi:",
            'Go to listing'      => "Mene ilmoitukseen",
            //'Traveler:' => [""],
            'Cancellation policy:'                               => "Peruutusehto",
            "service fee is non-refundable for this reservation" => "Tästä varauksesta ei makseta hyvitystä.", // nonrefundable
            'Price breakdown'                                    => "Hintaerittely",
            'Total'                                              => "Yhteensä",
            'Adjusted total'                                     => "Uusi kokonaishinta",
            'Reservation change:'                                => 'Varausmuutos:',
            'cancellation'                                       => 'peruutus',
        ],
        'hu' => [
            'nights in ' => ['városában'],
            //'Hosted by' => "",
            'Confirmation code:' => "Visszaigazolási kód:",
            'Go to listing'      => "Ugrás a hirdetésre",
            //'Traveler:' => [""],
            'Cancellation policy:'                               => "Lemondási feltételek",
            "service fee is non-refundable for this reservation" => "Ennek a foglalásnak az ára nem téríthető vissza.", // nonrefundable
            'Price breakdown'                                    => "Ár részletezése",
            'Total'                                              => "Összesen",
            // 'Adjusted total'                                     => "Uusi kokonaishinta",
            // 'Reservation change:'                                => 'Varausmuutos:',
            // 'cancellation'                                       => 'peruutus',
        ],
        'uk' => [
            'nights in ' => ['Кількість ночей у місті', 'ніч у'],
            //'Hosted by' => "",
            'Confirmation code:' => "Код підтвердження:",
            'Go to listing'      => "Перейти до оголошення",
            //'Traveler:' => [""],
            'Cancellation policy:'                               => "Правила скасування бронювання",
            // "service fee is non-refundable for this reservation" => "Ennek a foglalásnak az ára nem téríthető vissza.", // nonrefundable
            'Price breakdown'                                    => "Структура ціни",
            'Total'                                              => "Усього",
            'Adjusted total'                                     => "Нова загальна сума",
            'Reservation change:'                                => 'Зміна бронювання:',
            'cancellation'                                       => 'скасування',
        ],
        'ro' => [
            'nights in ' => ['nopți în', 'noapte în'],
            //'Hosted by' => "",
            'Confirmation code:' => "Cod de confirmare:",
            'Go to listing'      => "Accesează anunțul",
            //'Traveler:' => [""],
            'Cancellation policy:'                               => "Politică de anulare",
            // "service fee is non-refundable for this reservation" => "Ennek a foglalásnak az ára nem téríthető vissza.", // nonrefundable
            'Price breakdown'                                    => "Defalcarea prețului",
            'Total'                                              => "Total",
            // 'Adjusted total'                                     => "Нова загальна сума",
            // 'Reservation change:'                                => 'Зміна бронювання:',
            // 'cancellation'                                       => 'скасування',
        ],
    ];

    private $detectFrom = "@airbnb.com";
    private $detectSubject = [
        'en'  => "Your receipt from Airbnb",
        'fr'  => "Votre reçu d'Airbnb",
        'it'  => "La tua ricevuta di Airbnb",
        'es'  => "Tu recibo de Airbnb",
        'de'  => "Dein Rechnungsbeleg von Airbnb",
        'tr'  => "Airbnb'den gelen faturanız",
        'pt'  => "Seu recibo do Airbnb",
        'ru'  => "Ваша квитанция от Airbnb",
        'zh'  => "您的爱彼迎收据",
        'zh2' => "你在 Airbnb 的收據",
        'nl'  => "Je factuur van Airbnb",
        'el'  => "Η απόδειξή σας από την Airbnb",
        'da'  => "Din kvittering fra Airbnb",
        'lv'  => "Rēķins no Airbnb",
        'no'  => "Din kvittering fra Airbnb",
        'ca'  => 'El teu rebut d\'Airbnb',
        'id'  => 'Tanda terima Anda dari Airbnb',
        'cs'  => 'Tvůj doklad o zaplacení z Airbnb',
        'pl'  => 'Twój rachunek z Airbnb',
        'he'  => 'הקבלה שלך מ-Airbnb',
        'sv'  => 'Ditt kvitto från Airbnb',
        'sk'  => 'Vaše potvrdenie o zaplatení od Airbnb',
        'fi'  => 'Airbnb-kuittisi',
        // 'hu'  => 'Airbnb-kuittisi',
        'uk'  => 'Ваша квитанція від Airbnb',
        'ro'  => 'Chitanța ta de la Airbnb',
    ];

    private $detectBody = [
        'fr' => ["Votre reçu d'Airbnb", "Votre reçu d&#39;Airbnb"],
        'it' => ['La tua ricevuta di Airbnb'],
        'es' => ['Tu recibo de Airbnb'],
        'de' => ['Dein Rechnungsbeleg von Airbnb', 'Deine Quittung von'],
        'tr' => ["Airbnb'den gelen faturanız"],
        'pt' => ["Seu recibo do Airbnb", "O seu recibo do Airbnb"],
        'ru' => ["Ваша квитанция от Airbnb"],
        'cs' => ["Your receipt from Airbnb", "Tvůj doklad o zaplacení z Airbnb"],
        'zh' => ["您的爱彼迎收据", "您的 Airbnb 收據", '你在 Airbnb 的收據'],
        'da' => ["Your receipt from Airbnb", "Din kvittering fra Airbnb"],
        'nl' => ["Je factuur van Airbnb"],
        'el' => ["Η απόδειξή σας από την Airbnb"],
        'lv' => ["Rēķins no Airbnb"],
        'no' => ["Din kvittering fra Airbnb"],
        'ca' => ["El teu rebut d"],
        'id' => ["ID Tanda Terima"],
        'pl' => ["Twój rachunek z Airbnb"],
        'he' => ["הקבלה שלך מ-Airbnb"],
        'sv' => ["Ditt kvitto från Airbnb"],
        'sk' => ["Vaše potvrdenie o zaplatení od Airbnb"],
        'fi' => ["Airbnb-kuittisi"],
        'uk' => ["Ваша квитанція від Airbnb"],
        'ro' => ["Chitanța ta de la Airbnb"],
        // should be the last
        'en' => ['Your receipt from Airbnb'],
    ];

    private $lang = '';

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $body = html_entity_decode($this->http->Response['body']);

        foreach ($this->detectBody as $lang => $detectBody) {
            foreach ($detectBody as $dBody) {
                if (strpos($body, $dBody) !== false) {
                    if ((isset(self::$dict[$lang]["nights in "]) && $this->http->XPath->query("//text()[" . $this->contains(self::$dict[$lang]["nights in "]) . "]")->length > 0)
                        || (isset(self::$dict[$lang]['Price breakdown']) && $this->http->XPath->query("//text()[" . $this->contains(self::$dict[$lang]['Price breakdown']) . "]")->length > 0)
                    ) {
                        $this->lang = $lang;

                        break 2;
                    }
                }
            }
        }
        $this->logger->debug('LANG-' . $this->lang);
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $this->parseEmail($email);

        if ($email->getItineraries()) {
            $it = $email->getItineraries()[0];

            if ($it->getType() === 'event' && !$it->getCancelled()
                && $this->detectEmailByHeaders($parser->getHeaders()) === true
                && $this->detectEmailByBody($parser) === true
            ) {
                // no address in event
                $email->removeItinerary($it);
                $email->setIsJunk(true);
            }
        }

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        foreach ($this->detectBody as $lang => $detectBody) {
            if ($this->http->XPath->query("//node()[{$this->contains($detectBody)}]")->length > 0) {
                return true;
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

            $headers["subject"] = str_replace(chr(194) . chr(160), ' ', $headers["subject"]);

            foreach ($this->detectSubject as $reSubject) {
                if (mb_stripos($headers["subject"], $reSubject) !== false) {
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

    private function parseEmail(Email $email)
    {
        $type = '';
        $eventXpath = "//*[count(.//text()[normalize-space()]) = 1][.//a[contains(@href, 'airbnb') and contains(@href, 'receipt')]]/following-sibling::*[count(.//text()[normalize-space()]) = 1][.//a[contains(@href, 'airbnb') and contains(@href, 'experience') and contains(@href, 'reservation')]]";

        if ($this->http->XPath->query($eventXpath)->length > 0) {
            $type = 'event';
        }
        $hotelXpath = "//*[{$this->contains($this->t('nights in '))}]";

        if ($this->http->XPath->query($hotelXpath)->length > 0) {
            $type = 'hotel';
        }

        $this->logger->debug('$type = ' . print_r($type, true));

        if (empty($type)) {
            $this->logger->debug('not detect type');

            return false;
        }

        if ($type === 'event') {
            $h = $email->add()->event();

            $h->type()->event();
        } else {
            $h = $email->add()->hotel();
        }

        // General
        $h->general()
            ->confirmation($this->http->FindSingleNode("//text()[" . $this->starts($this->t("Confirmation code:")) . "]", null, true, "#{$this->preg_implode($this->t("Confirmation code:"))}\s*([A-Z\d]{5,})#u"));

        $travellers = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Traveler:")) . "]", null, true, "#:\s*(.+)#");

        if (!empty($travellers)) {
            $travellers = array_filter(array_map('trim', explode(",", $travellers)));
            $h->general()->travellers($travellers);
        }

        $status = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Reservation change:'))}]", null, false, "#{$this->preg_implode($this->t('Reservation change:'))}\s*(" . $this->preg_implode($this->t("cancellation")) . ")#u");

        if (!empty($status)) {
            $h->general()->status($status);
            $h->general()->cancelled();
        }

        $cancellation = $this->http->FindSingleNode("//text()[{$this->starts($this->t("Cancellation policy:"))}]/following::text()[normalize-space()!=''][1][not({$this->contains($this->t('Price breakdown'))})]");

        if (empty($cancellation)) {
            foreach (self::$dict as $lang => $value) {
                if ($cancellation = $this->http->FindSingleNode("//text()[{$this->starts($value['Cancellation policy:'])}]/following::text()[normalize-space()!=''][1][not({$this->contains($value['Price breakdown'])})]")) {
                    break;
                }
            }
        }

        if (!empty($cancellation)) {
            $h->general()->cancellation($cancellation);
        }

        // Hotel
        $hotelText = implode("\n", $this->http->FindNodes("//text()[contains(.,' · ')]/ancestor::*[self::th or self::td][1][not(preceding-sibling::*) and following-sibling::*//img]//text()[normalize-space()]"));

        if (empty($hotelText)) {
            $hotelText = implode("\n", $this->http->FindNodes("//text()[contains(.,' · ')]/ancestor::*[self::th or self::td and not(.//th) and not(.//td)][1][" . $this->contains($this->t("Go to listing")) . "]//text()[normalize-space()]"));
        }

        if (preg_match("#(?<type>.+?) · \d+[ ]*[\w\-]+ · (?<guests>\d+)[ ]*[\w\-]+\s*\n\s*(?<addr>[\s\S]+?)\s+" . $this->preg_implode($this->t("Hosted by")) . "#u", $hotelText, $m)) {
            $h->hotel()
                ->name($m['type'])
                ->house()
                ->address(str_replace("\n", ', ', $m['addr']))
            ;
            $h->booked()->guests($m['guests']);
            $r = $h->addRoom();
            $r->setType($m['type']);
        }

        $info = implode("\n", $this->http->FindNodes("//text()[" . $this->starts($this->t("Confirmation code:")) . "]/ancestor::*[not(" . $this->starts($this->t("Confirmation code:")) . ")][1]//text()[normalize-space()]"));

        if (
            preg_match("#(?<type>.+?) · (?:\w+[ ]*:[ ]*)?(?<descr>[\w\-\/ :]{3,}) · (?<guests>\d+)[ ]*[\w\-/]+\s*\n\s*" . $this->preg_implode($this->t("Confirmation code:")) . "#u", $info, $m)
            || preg_match("#(?<type>.+?) · (?:\w+[ ]*:[ ]*)?(?<descr>[\w\-\/ :]{3,}) · [\w\-]+(?: \w+)?[ ]*:[ ]*(?<guests>\d+)\s*\n\s*" . $this->preg_implode($this->t("Confirmation code:")) . "#u", $info, $m)
            || preg_match("#[^·]+\n\s*(?<type>.+?) · (?<guests>\d+)[ ]*[\w\-/]+\s*\n\s*" . $this->preg_implode($this->t("Confirmation code:")) . "#u", $info, $m)
            || preg_match("#[^·]+\n\s*(?<type>.+?) · (?<guests>\d+)[ ]*[\w\-/]+\s* · (?<animal>\d+)[ ]*[\w \-/]+\s*\n\s*" . $this->preg_implode($this->t("Confirmation code:")) . "#u", $info, $m)
        ) {
            $addrText = $this->http->FindSingleNode("//text()[" . $this->contains($m['type']) . "]/preceding::text()[" . $this->contains($this->t("nights in ")) . "]");

            if ($this->lang == 'tr') {
                $addr = $this->re("#(.+)" . $this->preg_implode($this->t("nights in ")) . "#u", $addrText);
            } elseif ($this->lang == 'zh') {
                $addr = $this->re("#.+" . $this->preg_implode($this->t("nights in ")) . "\s*(.+?)(?:住宿|$)#u", $addrText);

                if (empty($addr)) {
                    $addr = $this->re("#^在(.+?)(?:住宿|的)\d" . $this->preg_implode($this->t("nights in ")) . "#u", $addrText);
                }
                // } elseif ($this->lang == 'uk') {
            //     Кількість ночей у місті Santa Cruz de Tenerife: 4
                // $addr = $this->re("#^\s*" . $this->preg_implode($this->t("nights in ")) . "\s*(.+?)\d*:\s*\d+#u", $addrText);
                // if (empty($addr)) {
                //     1 ніч у місті Pollença
                    // $addr = $this->re("#^\s*" . $this->preg_implode($this->t("nights in ")) . "\s*(.+?)\s*:#u", $addrText);
                // }
            } else {
                $addr = $this->re("#.+" . $this->preg_implode($this->t("nights in ")) . "\s*(.+)#u", $addrText);
            }

            if (empty($addr) && in_array($this->lang, ['ru', 'uk'])) {
                $addr = $this->re("#^\s*" . $this->preg_implode($this->t("nights in ")) . "\s*(.+?)\s*:#u", $addrText);
            }
            $addr = trim($addr, '.');

            $noAddress = false;

            if (empty($addr)) {
                $title = preg_replace('/(\s*$|^\s*)/', '', $this->t("nights in "));
                $addrText = $this->http->FindSingleNode("//text()[" . $this->contains($m['type']) . "]/preceding::text()[" . $this->contains($title) . "][1]");

                if (preg_match("/^\s*(?:\d\s*{$this->preg_implode($title)}|{$this->preg_implode($title)}\s*\d+)\s*$/", $addrText)
                    && !empty($this->http->FindSingleNode("//text()[" . $this->eq($addrText) . "]/preceding::text()[normalize-space()][1]", null, true, "/(?:^|\D*)20\d{2}(?:\D*|$)/"))
                    && !empty($this->http->FindSingleNode("//text()[" . $this->eq($addrText) . "]/following::text()[normalize-space()][1]", null, true, "/(?:^|\D*)20\d{2}(?:\D*|$)/"))
                    && !empty($this->http->FindSingleNode("//text()[" . $this->eq($addrText) . "]/following::text()[normalize-space()][2]", null, true, "/(?:^|\D*)20\d{2}(?:\D*|$)/"))
                ) {
                    $noAddress = true;
                }
            }

            $h->hotel()
                ->name($m['type'])
                ->house()
            ;

            if ($noAddress === true) {
                $h->hotel()
                    ->noAddress();
            } else {
                $h->hotel()
                    ->address($addr);
            }
            $h->booked()->guests($m['guests']);
            $r = $h->addRoom();
            $r->setType($m['type'])->setDescription($m['descr'], true, true);
        }

        $eventName = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Confirmation code:")) . "]/ancestor::tr[1][count(preceding::hr) = 2]/preceding::*[self::td or self::th][normalize-space()][1][count(preceding::hr) = 1]");

        if ($type === 'event' && !empty($eventName)) {
            $h->place()
                ->name($eventName);
        }
        $xpathYear = "contains(translate(normalize-space(),'0123456789','dddddddddd'),'dddd')";

        // Booked
        $chechIn = $this->http->FindSingleNode("//img[contains(@src,'arrow-next')]/preceding::text()[normalize-space()!=''][1]");

        if (empty($chechIn)) {
            $chechIn = $this->http->FindSingleNode("//text()[contains(.,' · ')]/ancestor::*[self::th or self::td][1][not(preceding-sibling::*) and following-sibling::*//img]//text()[normalize-space()]/preceding::tr[normalize-space()][1][not(.//tr) and count(*) = 3 and *[2]//img]/*[1]");
        }

        if (empty($chechIn)) {
            $chechIn = $this->http->FindSingleNode("//*[ not(.//tr) and node()[1][{$xpathYear}] and node()[2][.//img and not(normalize-space())] and node()[3][{$xpathYear}] ]/node()[1]");
        }

        if (empty($chechIn)) {
            $chechIn = $this->http->FindSingleNode("//img[./following::text()[normalize-space()!=''][1][{$xpathYear}] and ./preceding::text()[normalize-space()!=''][1][{$xpathYear}]]/ancestor::*[1][count(./descendant::text()[normalize-space()!=''])=2]/descendant::text()[normalize-space()!=''][1]");
        }
        $checkOut = $this->http->FindSingleNode("//img[contains(@src,'arrow-next')]/following::text()[normalize-space()][1]");

        if (empty($checkOut)) {
            $checkOut = $this->http->FindSingleNode("//text()[contains(.,' · ')]/ancestor::*[self::th or self::td][1][not(preceding-sibling::*) and following-sibling::*//img]//text()[normalize-space()]/preceding::tr[normalize-space()][1][not(.//tr) and count(*) = 3 and *[2]//img]/*[3]");
        }

        if (empty($checkOut)) {
            $checkOut = $this->http->FindSingleNode("//*[ not(.//tr) and node()[1][{$xpathYear}] and node()[2][.//img and not(normalize-space())] and node()[3][{$xpathYear}] ]/node()[3]");
        }

        if (empty($checkOut)) {
            $checkOut = $this->http->FindSingleNode("//img[./following::text()[normalize-space()!=''][1][{$xpathYear}] and ./preceding::text()[normalize-space()!=''][1][{$xpathYear}]]/ancestor::*[1][count(./descendant::text()[normalize-space()!=''])=2]/descendant::text()[normalize-space()!=''][2]");
        }

        if ($h->getType() === 'hotel') {
            $h->booked()
                ->checkIn($this->normalizeDate($chechIn))
                ->checkOut($this->normalizeDate($checkOut))
            ;
        }

        if ($h->getType() === 'hotel') {
            $this->detectDeadLine($h);
        }

        // Price
        if ($this->http->FindSingleNode("(//text()[{$this->eq($this->t('Price breakdown'))}]/following::text()[" . $this->contains($this->t("Adjusted total")) . "][1])")) {
            $totals = $this->http->FindNodes("(//text()[{$this->eq($this->t('Price breakdown'))}]/following::tr[not(.//tr) and " . $this->starts($this->t("Adjusted total")) . "][1]/*[normalize-space()])");

            if (count($totals) == 2) {
                if (preg_match("#^\s*" . $this->preg_implode($this->t("Adjusted total")) . "\s*\(([A-Z]{3})\)#u", $totals[0], $m) && $this->normalizePrice($totals[1]) > 0) {
                    $h->price()
                        ->total($this->normalizePrice($totals[1]))
                        ->currency($m[1]);
                }
            }
        } elseif ($this->http->FindSingleNode("(//text()[{$this->eq($this->t('Price breakdown'))}]/following::text()[" . $this->starts($this->t("Total")) . "][1])")) {
            $xpath = "//text()[{$this->eq($this->t('Price breakdown'))}]/ancestor::tr[1]/following::tr[not(.//tr)  and normalize-space()][position()<15]";
            //			$this->logger->debug($xpath);
            $nodes = $this->http->XPath->query($xpath);
            $discount = 0.0;

            foreach ($nodes as $key => $root) {
                $name = $this->http->FindSingleNode("*[normalize-space()][1]", $root);
                $amount = $this->normalizePrice($this->http->FindSingleNode("*[normalize-space()][2]", $root));

                if (empty($name) || $amount === null) {
                    break;
                }

                if ($key == 0) {
                    $symbol = 'x';

                    if ($this->lang === 'he') {
                        $symbol = '\*';
                    }

                    if (preg_match("#^\s*(\D{0,5}\d[\d\,\. ]*\D{0,5})\s+{$symbol}\s+\d+\s*\w+#u", $name, $m)) {
                        if ($h->getType() === 'hotel') {
                            if (isset($r)) {
                                $r->setRate(trim($m[1]));
                            } else {
                                $r = $h->addRoom();
                                $r->setRate(trim($m[1]));
                            }
                        }
                        $h->price()->cost($amount);
                    }

                    continue;
                }

                if (preg_match("#^\s*\([A-Z]{3}\)\s*$#u", $name)) {
                    continue;
                }

                if (preg_match("#^\s*" . $this->preg_implode($this->t("Total")) . "\s*\(([A-Z]{3})\)\s*#u", $name, $m)) {
                    $h->price()
                        ->total($amount)
                        ->currency($m[1]);

                    break;
                }

                if ($amount >= 0) {
                    $h->price()->fee($name, $amount);
                } elseif ($amount < 0) {
                    $discount += $amount;
                } else {
                    break;
                }
            }

            if ($discount < 0) {
                $h->price()->discount(abs($discount));
            }
        }

        return $email;
    }

    private function detectDeadLine(Hotel $h): void
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (preg_match("#" . $this->preg_implode($this->t("service fee is non-refundable for this reservation")) . "#", $cancellationText)) {
            $h->booked()->nonRefundable();
        }

        $deadlineDaysRE = [
            '/Annuller op til (?<days>\d+) dage før indtjekning, og få alle pengene tilbage./',
            '/Cancela hasta (<days>\d+) días antes de la fecha de llegada y obtén un reembolso total./',
        ];

        foreach ($deadlineDaysRE as $re) {
            if (preg_match($re, $cancellationText, $m) && !empty($m['days'])) {
                $h->booked()->deadlineRelative($m[1] . ' days');
            }
        }

        $deadlineDateTimeRE = [
            // en
            '/Cancel before (?<time>\d{1,2}:\d{2} [AP]M) on (?<date>\w+ \d{1,2}) and get a full refund\./',
            '/Free cancellation before (?<time>\d{1,2}:\d{2} [AP]M) on (?<date>\w+ \d{1,2})\./',
            // el
            '/Αν ακυρώσετε πριν τις (?<date>\d{1,2} \w+), ώρα (?<time>\d{1,2}:\d{2} [AP]M), θα σας επιστραφούν πλήρως τα χρήματά σας\./u',
            // fr
            '/Annulez avant (?<time>\d{1,2}:\d{2} [AP]M) le (?<date>\d{1,2} \w+) et obtenez un remboursement total\./u',
            // da
            '/Hvis du annullerer inden kl\. (?<time>\d{1,2}:\d{2} [AP]M) den (?<date>\d{1,2}\.? \w+\.?), får du refunderet hele beløbet\./u',
            '/Gratis annullering inden kl\. (?<time>\d{1,2}\.\d{2}) den (?<date>\d{1,2}\.? \w+\.?)\./u',
            // lv
            '/Atceļot līdz (?<time>\d{1,2}:\d{2} [AP]M) (?<date>\d{1,2}\.? \w+\.?), saņemiet atmaksu pilnā mērā./u',
            // no
            '/Kanseller før (?<time>\d{1,2}:\d{2} [AP]M) på (?<date>\d{1,2}\.? \w+\.?) og få en full refusjon\./u',
            '/Kansellering uten kostnad før kl\. (?<time>\d{1,2}:\d{2}) den (?<date>\d{1,2}\.? \w+)/u',
            // nl
            '/Annuleer vóór (?<time>\d{1,2}:\d{2} [AP]M) op (?<date>\d{1,2} \w+\.?) en krijg een volledige restitutie\./u',
            // pl
            '/Bezpłatne anulowanie przed (?<time>\d{1,2}:\d{2}) (?<date>\d{1,2} \w+)\./u',
            // de
            '/Kostenlose Stornierung vor (?<time>\d{1,2}:\d{2}) Uhr am (?<date>\d{1,2}\. \w+)\./u',
            // it
            '/Cancellazione gratuita entro le ore (?<time>\d{1,2}:\d{2}) del giorno (?<date>\d{1,2} \w+)\./u',
            // es
            '/Cancelación gratuita antes del (?<date>\d{1,2} \w+)\. a las (?<time>\d{1,2}:\d{2})\./u',
            // zh
            '/^(?<date>\d{1,2}月\d{1,2}日)(?<time>(?:下午|上午)\d{1,2}:\d{2}) 前可以免費取消在/u',
            // sv
            '/Gratis avbokning innan kl. (?<time>\d{1,2}:\d{2}) den (?<date>\d{1,2} \w+)\./u',
            // pt
            '/Cancelamento gratuito antes das (?<time>\d{1,2}:\d{2}) do dia (?<date>\d{1,2} de \w+)\./u',
            '/Cancelamento gratuito até às (?<time>\d{1,2}:\d{2}) em (?<date>\d{1,2}\\/\d{1,2})\./u',
            // sk
            '/Bezplatné storno do (?<date>\d{1,2}\. \d{1,2}\.) do (?<time>\d{1,2}:\d{2})\./u',
            // fi
            '/Ilmainen peruutus (?<date>\d{1,2}\. [[:alpha:]]+)\. klo (?<time>\d{1,2}.\d{2}) saakka\./u',
            // ca
            '/Cancel·lació gratuïta abans de les (?<time>\d{1,2}:\d{2}) del dia (?<date>\d{1,2} de \w+)\./u',
            // uk
            '/Безкоштовне скасування бронювання до (?<time>\d{1,2}:\d{2}) (?<date>\d{1,2} \w+)\.\./u',
        ];

        foreach ($deadlineDateTimeRE as $re) {
            if (preg_match($re, $cancellationText, $m) && !empty($m['date']) && !empty($m['time'])
            ) {
                $m['time'] = preg_replace("/^\s*(\d{1,2})\.(\d{2})\s*$/", '$1:$2', $m['time']);
                $h->booked()->deadline($this->normalizeDateDeadLine($m['date'] . ', ' . $m['time'], $h));
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
        $in = [
            //1: Wed, Oct 31, 2018
            '#^\s*[^\d\s]+[,\s]+([^\d\s\.]+)\s+(\d+),\s*(\d{4})\s*$#u',
            //2: Do, 25. Okt 2018; ср, 1 июл. 2020 г.
            // יום א׳, 21 במאי 2023
            '#^\s*[^\d]+[,\s]+(\d+)[\s.]+(?:d’)?([^\d\s\.,]+?)[\s.,׳]+(\d{4})(?:\s*г\.)?\s*$#u',
            //3: Vie, 14 de Sep de 2018; dl., 22 de maig 2023
            '#^\s*[^\d\s]+[,\s]+(\d+)\s+de\s+([^\d\s\.]+)[\.\s]+(?:de\s+)?(\d{4})\s*$#u',
            //4: 17 May 2019 Cum
            '#^\s*(\d+)[\s.]+([^\d\s\.,]+)[\s.,]+(\d{4})\s+[^\d\s]+\s*$#u',
            //5: 2019年9月30日周一
            '#^\s*(\d{4})年(\d+)月(\d+)日.+\s*$#u',
            //6: trešd., 2020. g. 12. aug.
            '#^\s*[^\d\s]+[\.,\s]+(\d{4})\.\s+g\.\s*(\d+)\.\s+([^\d\s\.]+)[\.]\s*$#u',
            //7: sábado, 9/10/2021
            '#^\s*[^\d\s]+[\.,\s]+(\d{1,2})/(\d{2})/(\d{4})\s*$#u',
            //8: čt 28. 10. 2021; so 5. 8. 2023
            '#^\s*[^\d\s]+[\.,\s]+(\d{1,2})\.\s*(\d{1,2})\.\s*(\d{4})\s*$#u',
        ];
        $out = [
            '$2 $1 $3', //1
            '$1 $2 $3', //2
            '$1 $2 $3', //3
            '$1 $2 $3', //4
            '$1-$2-$3', //5
            '$2 $3 $1', //6
            '$1.$2.$3', //7
            '$3-$2-$1', //8
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

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        if (stripos(implode('', $field), '"') !== false) {
            // lang he
            return '(' . implode(' or ', array_map(function ($s) {
                return "starts-with(normalize-space(.),'" . $s . "')";
            }, $field)) . ')';
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

    private function normalizePrice($price)
    {
        $price = trim($price);

        if (preg_match("#^\s*(\-?)\D{0,5}?(\d[\d., ]*)\D{0,5}\s*$#u", $price, $m)) {
            $amount = $m[1] . $m[2];
            // PriceHelper not work. diff examples for one lang
            $amount = preg_replace('/\s+/', '', $amount);			// 11 507.00	->	11507.00
            $amount = preg_replace('/[,.](\d{3})/', '$1', $amount);	// 2,790		->	2790		or	4.100,00	->	4100,00
            $amount = preg_replace('/^(.+),$/', '$1', $amount);	// 18800,		->	18800
            $amount = preg_replace('/,(\d{2})$/', '.$1', $amount);	// 18800,00		->	18800.00

            if (is_numeric($amount)) {
                return (float) $amount;
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

    private function normalizeDateDeadLine(string $str, Hotel $h)
    {
        $year = date('Y', $h->getCheckInDate());
        // $this->logger->debug($str);
        // 7月3日, 下午4:00 -> 7月3日, 4:00 PM
        $str = preg_replace(['/(\s*,\s*)下午(\d+:\d+)\s*$/', '/(\s*,\s*)上午(\d+:\d+)\s*$/'], ['$1$2 PM', '$1$2 AM'], $str);
        $in = [
            // Jul 1, 4:00 PM
            '/^([[:alpha:]]+) (\d+),\s+(\d{1,2}:\d{2}(?: [AP]M)?)$/iu',
            // 1 Jul, 4:00 PM; 19. aug., 4:00 PM
            // 18 de mai, 14:00
            '/^(\d+)[.]? (?:de )?([[:alpha:]]+)[.]?,\s+(\d{1,2}:\d{2}(?: [AP]M)?)$/iu',
            // 7月3日, 4:00 PM
            '/^(\d+)月(\d+)日,\s*(\d{1,2}:\d{2}(?: [AP]M)?)$/iu',
            // 8/06, 12:00
            '/^(\d+)\\/(\d+),\s*(\d{1,2}:\d{2})$/iu',
            // 24. 7., 12:00
            '/^\s*(\d{1,2})\.\s*(\d{1,2})\.,\s*(\d{1,2}:\d{2})$/iu',
        ];
        $out = [
            "$2 $1 {$year}, $3",
            "$1 $2 {$year}, $3",
            "$2.$1.{$year}, $3",
            "$1.$2.{$year}, $3",
            "{$year}-$2-$1, $3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return EmailDateHelper::parseDateRelative($str, $h->getCheckInDate(), false);

        return strtotime($str, false);
    }
}
