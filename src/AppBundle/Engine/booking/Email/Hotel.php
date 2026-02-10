<?php

namespace AwardWallet\Engine\booking\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Hotel extends \TAccountChecker
{
    public $mailFiles = "booking/it-27461450.eml, booking/it-28192577.eml, booking/it-35937834.eml, booking/it-57685967.eml, booking/it-57686004.eml, booking/it-57900268.eml, booking/it-59076903.eml, booking/it-60989074.eml";

    private $lang = 'en';

    private $detectSubject = [
        'en' => 'Your bookings from',
        'es' => 'Tus reservas del',
        'it' => 'Le tue prenotazioni dal',
        'ru' => 'Ваши бронирования с',
        'pt' =>'Suas reservas de',
        'As suas reservas de',
        'nl' => 'Je boekingen van',
        'zh' => '您在',
        'hu' => 'közötti foglalásai',
        'pl' => 'Twoje rezerwacje od',
        'sv' => 'Dina bokningar från',
        'fr' => 'Vos réservations du',
        'de' => 'Ihre Buchungen vom',
        'da' => 'Dine bookinger fra',
        'he' => 'ההזמנות שלכם',
        'cs' => 'Vaše rezervace od',
    ];

    private $detectBody = [
        'en' => ['Your confirmed booking at'],
        'es' => ['Tu reserva confirmada en'],
        'it' => ['La tua prenotazione confermata presso'],
        'ru' => ['Ваше подтвержденное бронирование в'],
        'pt' => [
            'Sua reserva confirmada em',
            'A sua reserva confirmada em',
        ],
        'nl' => ['Je bevestigde boeking bij'],
        'zh' => ['您已確認的住宿訂單'],
        'hu' => ['Az Ön'],
        'pl' => ['Twoja potwierdzona rezerwacja'],
        'sv' => ['Din bekräftade bokning på'],
        'fr' => ['Votre réservation confirmée à'],
        'de' => ['Ihre bestätigte Buchung in der'],
        'da' => ['Din bekræftede booking på'],
        'he' => ['אישור הזמנתכם במקום האירוח'],
        'cs' => [': Vaše potvrzená rezervace'],
    ];

    private $from = '/[@\.]booking\.com/';

    private $prov = 'Booking.com';
    private static $dict = [
        'en' => [
            'Check-in'               => 'Check-in',
            'Check-out'              => 'Check-out',
            'Your confirmed booking' => ['Your confirmed booking', 'Your Confirmed Booking at'],
            //            'Booking number' => '',
            'Total price' => ['Total price', 'Total Price'],
            //            'Guest name' => '',
            //            'Number of guests' => '',
            //            'Cancellation cost' => '',
            //            'Room costs' => '',
            //            'Cost you\'ll pay if you don\'t cancel' => '',
            //            'until' => '',
            //            'non-refundable' => '', // to translate
            'kids' => 'child', // to check
        ],
        'es' => [
            'Check-in'                              => 'Entrada',
            'Check-out'                             => 'Salida',
            'Your confirmed booking'                => ['Tu reserva confirmada en el', 'Tu reserva confirmada en'],
            'Booking number'                        => 'Número de reserva',
            'Total price'                           => 'Precio total',
            'Guest name'                            => 'Nombre del huésped',
            'Number of guests'                      => 'Número de huéspedes',
            'Cancellation cost'                     => 'Cargos de cancelación',
            'Room costs'                            => 'Precio de la habitación',
            'Cost you\'ll pay if you don\'t cancel' => 'Precio que pagarás si no cancelas',
            'until'                                 => 'Hasta el',
            //            'non-refundable' => '',
                        'kids' => 'niño',
        ],
        'it' => [
            'Check-in'                              => 'Arrivo',
            'Check-out'                             => 'Partenza',
            'Your confirmed booking'                => ['La tua prenotazione confermata presso'],
            'Booking number'                        => 'Numero di prenotazione',
            'Total price'                           => ['Importo totale'],
            'Guest name'                            => 'Nome dell\'ospite',
            'Number of guests'                      => 'Numero ospiti',
            'Cancellation cost'                     => 'Costi di cancellazione',
            'Room costs'                            => 'Costo dell’appartamento',
            'Cost you\'ll pay if you don\'t cancel' => 'Prezzo che pagherai se non cancelli',
            'until'                                 => 'alle',
            //            'non-refundable' => '',
            'kids' => 'bambin',
        ],
        'ru' => [
            'Check-in'                              => 'Регистрация заезда',
            'Check-out'                             => 'Регистрация отъезда',
            'Your confirmed booking'                => ['Ваше подтвержденное бронирование в'],
            'Booking number'                        => 'Номер бронирования',
            'Total price'                           => ['Общая стоимость'],
            'Guest name'                            => 'Имя гостя',
            'Number of guests'                      => 'Число гостей',
            'Cancellation cost'                     => 'Стоимость отмены бронирования',
            'Room costs'                            => 'Стоимость номера',
            'Cost you\'ll pay if you don\'t cancel' => 'Взимаемая плата, если вы не отмените',
            'until'                                 => 'до',
            'non-refundable'                        => 'Это бронирование не может быть изменено без штрафа',
            'kids'                                  => 'ребенок',
        ],
        'pt' => [
            'Check-in'                              => ['Entrada', 'Check-in'],
            'Check-out'                             => ['Saída', 'Check-out'],
            'Your confirmed booking'                => ['Sua reserva confirmada em', 'A sua reserva confirmada em'],
            'Booking number'                        => ['Número da reserva', 'Número de reserva'],
            'Total price'                           => ['Preço total'],
            'Guest name'                            => 'Nome do hóspede',
            'Number of guests'                      => 'Número de hóspedes',
            'Cancellation cost'                     => ['Custos de cancelamento', 'Custos de Cancelamento'],
            'Room costs'                            => ['Custo do quarto', 'Custos do quarto'],
            'Cost you\'ll pay if you don\'t cancel' => ['Valor que você deverá pagar se não cancelar', 'Quanto irá pagar se não cancelar'],
            'until'                                 => 'até',
            //            'non-refundable' => '',
            //            'kids' => '',
        ],
        'nl' => [
            'Check-in'                              => 'Inchecken',
            'Check-out'                             => 'Uitchecken',
            'Your confirmed booking'                => 'Je bevestigde boeking bij',
            'Booking number'                        => 'Boekingsnummer:',
            'Total price'                           => 'Totaalprijs',
            'Guest name'                            => 'Naam reiziger',
            'Number of guests'                      => 'Aantal gasten',
            'Cancellation cost'                     => 'Annuleringskosten',
            'Room costs'                            => 'Kosten kamer',
            'Cost you\'ll pay if you don\'t cancel' => 'Te betalen kosten als u niet annuleert',
            'until'                                 => 'tot',
            //            'non-refundable' => '',
                        'kids' => 'kind',
        ],
        'zh' => [
            'Check-in'                              => '入住時間',
            'Check-out'                             => '退房時間',
            'Your confirmed booking'                => ['您已確認的住宿訂單'],
            'Booking number'                        => '訂單編號',
            'Total price'                           => ['總價'],
            'Guest name'                            => '住客姓名',
            'Number of guests'                      => '客人人數',
            'Cancellation cost'                     => '取消費',
            'Room costs'                            => '客房價格',
            'Cost you\'ll pay if you don\'t cancel' => '如未取消任何訂單則必須支付',
            'until'                                 => '到',
            //'non-refundable' => '',
            'kids' => '位孩童',
        ],
        'hu' => [
            'Check-in'                              => 'Bejelentkezés',
            'Check-out'                             => 'Kijelentkezés',
            'Your confirmed booking'                => ['Az Ön'],
            'Booking number'                        => 'Foglalási szám',
            'Total price'                           => ['Teljes ár'],
            'Guest name'                            => 'Vendég neve',
            'Number of guests'                      => 'Vendégek száma',
            'Cancellation cost'                     => 'Lemondási díj',
            'Room costs'                            => 'A ház ára',
            'Cost you\'ll pay if you don\'t cancel' => 'Ennyit fizet, ha nem mondja le',
            'until'                                 => 'a következő dátumig:',
            //'non-refundable' => '',
            //            'kids' => '',
        ],
        'pl' => [
            'Check-in'               => 'Zameldowanie',
            'Check-out'              => 'Wymeldowanie',
            'Your confirmed booking' => ['Twoja potwierdzona rezerwacja w obiekcie'],
            'Booking number'         => 'Numer rezerwacji',
            'Total price'            => ['Całkowity koszt'],
            'Guest name'             => 'Nazwisko Gościa',
            'Number of guests'       => 'Liczba Gości',
            'Cancellation cost'      => 'Koszt anulowania rezerwacji',
            'Room costs'             => 'Całkowita cena pokoju',
            //            'Cost you\'ll pay if you don\'t cancel' => '',
            'until' => 'do',
            //'non-refundable' => '',
            'kids' => 'dziecko',
        ],
        'sv' => [
            'Check-in'                              => 'Incheckning',
            'Check-out'                             => 'Utcheckning',
            'Your confirmed booking'                => ['Din bekräftade bokning på'],
            'Booking number'                        => 'Bokningsnummer',
            'Total price'                           => ['Totalkostnad'],
            'Guest name'                            => 'Gästens namn',
            'Number of guests'                      => 'Liczba Gości',
            'Cancellation cost'                     => 'Avbokningskostnad',
            'Room costs'                            => 'Pris för rummet',
            'Cost you\'ll pay if you don\'t cancel' => 'Belopp att betala om du inte avbokar',
            'until'                                 => 'till och med',
            //'non-refundable' => '',
            'kids' => 'barn',
        ],
        'fr' => [
            'Check-in'                              => 'Arrivée',
            'Check-out'                             => 'Départ',
            'Your confirmed booking'                => ['Votre réservation confirmée à l\'établissement'],
            'Booking number'                        => 'Numéro de réservation',
            'Total price'                           => ['Montant total'],
            'Guest name'                            => 'Clients',
            'Number of guests'                      => 'Nombre de clients',
            'Cancellation cost'                     => 'Frais d\'annulation',
            'Room costs'                            => 'Coût de la chambre',
            'Cost you\'ll pay if you don\'t cancel' => 'Montant que vous devrez payer si vous n\'annulez pas',
            'until'                                 => 'jusqu\'au',
            //'non-refundable' => '',
            'kids' => 'enfant',
        ],
        'de' => [
            'Check-in'                              => 'Anreise',
            'Check-out'                             => 'Abreise',
            'Your confirmed booking'                => ['Ihre bestätigte Buchung in der Unterkunft'],
            'Booking number'                        => 'Buchungsnummer',
            'Total price'                           => ['Gesamtpreis'],
            'Guest name'                            => 'Name des Gastes',
            'Number of guests'                      => 'Anzahl der Gäste',
            'Cancellation cost'                     => 'Stornierungsgebühren',
            'Room costs'                            => 'Apartmentpreis',
            'Cost you\'ll pay if you don\'t cancel' => 'Das müssen Sie zahlen, wenn Sie nicht stornieren',
            'until'                                 => 'bis',
            //'non-refundable' => '',
            'kids' => 'Kinder',
        ],
        'da' => [
            'Check-in'                              => 'Indtjekning',
            'Check-out'                             => 'Udtjekning',
            'Your confirmed booking'                => ['Din bekræftede booking på'],
            'Booking number'                        => 'Reservationsnummer',
            'Total price'                           => ['Samlet pris'],
            'Guest name'                            => 'Gæster',
            'Number of guests'                      => 'Antal gæster',
            'Cancellation cost'                     => 'Pris for afbestilling',
            'Room costs'                            => 'Værelsespris',
            'Cost you\'ll pay if you don\'t cancel' => 'Hvis du ikke afbestiller, skal du betale',
            'until'                                 => 'frem til',
            //'non-refundable' => '',
            'kids' => 'børn',
        ],
        'he' => [
            'Check-in'                              => 'צ\'ק-אין',
            'Check-out'                             => 'צ\'ק-אאוט',
            'Your confirmed booking'                => ['אישור הזמנתכם במקום האירוח'],
            'Booking number'                        => 'מספר הזמנה',
            'Total price'                           => ['מחיר כולל'],
            'Guest name'                            => 'שם האורח',
            'Number of guests'                      => 'מספר האורחים',
            'Cancellation cost'                     => 'עלות הביטול',
            'Room costs'                            => 'עלויות החדר',
            'Cost you\'ll pay if you don\'t cancel' => 'הסכום שתשלמו אם לא תבטלו',
            'until'                                 => 'עד',
            //'non-refundable' => '',
            'kids' => 'ילדים',
        ],

        'cs' => [
            'Check-in'                              => 'Příjezd',
            'Check-out'                             => 'Odjezd',
            'Your confirmed booking'                => [': Vaše potvrzená rezervace'],
            'Booking number'                        => 'Číslo rezervace',
            'Total price'                           => ['Celková cena'],
            'Guest name'                            => 'Jméno hosta',
            'Number of guests'                      => 'Počet hostů',
            'Cancellation cost'                     => 'Poplatek za zrušení rezervace',
            'Room costs'                            => 'Cena pokoje',
            'Cost you\'ll pay if you don\'t cancel' => 'Částka, kterou zaplatíte, pokud rezervaci nezrušíte',
            'until'                                 => 'do',
            //'non-refundable' => '',
            'kids' => 'børn',
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

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers['from']) || empty($headers['subject'])) {
            return false;
        }

        if (!preg_match($this->from, $headers['from'])) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers['subject'], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (false === strpos($body, $this->prov)) {
            return false;
        }

        foreach ($this->detectBody as $detectBody) {
            foreach ($detectBody as $detect) {
                if (false !== stripos($body, $detect)) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match($this->from, $from) > 0;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang()
    {
        foreach (self::$dict as $lang => $words) {
            if (isset($words['Check-in'], $words['Check-out'])) {
                if ($this->http->XPath->query("//*[{$this->contains($words['Check-in'])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($words['Check-out'])}]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function parseEmail(Email $email): void
    {
        $checkin = $this->http->FindSingleNode("//td[({$this->starts($this->t('Check-in'))}) and not(.//td)]/following-sibling::td[normalize-space(.)!=''][1]");

        $checkout = $this->http->FindSingleNode("//td[({$this->starts($this->t('Check-out'))}) and not(.//td)]/following-sibling::td[normalize-space(.)!=''][1]");

        $xpath = "//tr[({$this->contains($this->t('Your confirmed booking'))}) and not(.//tr)]/following-sibling::tr[2]";
        $roots = $this->http->XPath->query($xpath);

        if (0 === $roots->length) {
            $this->logger->debug("Segments did not found by xpath: {$xpath}");
        }

        $total = $this->http->FindSingleNode("descendant::td[({$this->starts($this->t('Cost you\'ll pay if you don\'t cancel'))}) and not(.//td)][1]/following-sibling::td[normalize-space()!=''][1]");

        if (preg_match('/^(?<currency>[^\d)(]+)\s*(?<amount>\d[,.\'\d ]*)/', $total, $matches)) {
            // € 1,073.08    |    US$1,116.99
            $currency = $this->normalizeCurrency($matches['currency']);
            $email->price()
                ->currency($currency)
                ->total($this->normalizeAmount(PriceHelper::parse($matches['amount'], $currency)));
        }

        foreach ($roots as $root) {
            $h = $email->add()->hotel();

            if ($conf = $this->http->FindSingleNode("./preceding::text()[{$this->contains($this->t('Booking number'))}][1]/following-sibling::node()[normalize-space(.)!=''][1]", $root, true, '/(\d+)/')) {
                $h->general()
                    ->confirmation($conf);
            }

            $xpathFragmentHotel = "./descendant::a[{$this->contains(['booking.com/myreservations', 'booking.com/mybooking', 'secure.bookaahotels.com/mybooking.html'], '@href')}]/text()[normalize-space(.)][1]";

            if ($hName = $this->http->FindSingleNode("({$xpathFragmentHotel})[1]", $root)) {
                $h->hotel()
                    ->name($hName);
            }

            if ($address = $this->http->FindSingleNode("({$xpathFragmentHotel})[1]/ancestor::tr[1]/following-sibling::tr[1]", $root)) {
                $h->hotel()
                    ->address($address);
            }

            $total = $this->http->FindSingleNode("descendant::td[({$this->starts($this->t('Total price'))}) and not(.//td)][1]", $root, true, "/{$this->preg_implode($this->t('Total price'))}[:\s]+(.+)/");

            if (preg_match('/^(?<currency>[^\d)(]+)\s*(?<amount>\d[,.\'\d ]*)/', $total, $matches)) {
                // € 1,073.08    |    US$1,116.99
                $currency = $this->normalizeCurrency($matches['currency']);
                $h->price()
                    ->currency($currency)
                    ->total($this->normalizeAmount(PriceHelper::parse($matches['amount'], $currency)));
            }

            if ($renters = $this->getNodes($root, 'Guest name')) {
                $h->general()->travellers(array_unique($renters));
            }

            if ($guests = array_sum($this->getNodes($root, 'Number of guests', '/(\d+)/'))) {
                $h->booked()
                    ->guests($guests);
            }

            if ($guests = array_sum(array_filter($this->getNodes($root, 'Number of guests', '/(\d+)\s*' . $this->preg_implode($this->t('kids')) . '/')))) {
                $h->booked()
                    ->kids($guests);
            }

            if ($checkin) {
                $h->booked()
                    ->checkIn($this->normalizeDate($checkin));
            }

            if ($checkout) {
                $h->booked()
                    ->checkOut($this->normalizeDate($checkout));
            }

            $cancellation = array_values(array_filter($this->getNodes($root, 'Cancellation cost')));

            if (isset($cancellation[0]) && preg_match("#(\]\s*:\s*\d[\d., ]*)\s+#", $cancellation[0])) {
                $cancellation = preg_replace("#(\]\s*:\s*\d[\d., ]*\s*[^\s\d]{1,5})\s+#", '$1.  ', $cancellation);
            } else {
                $cancellation = preg_replace("#(\]\s*:\s*\D{1,5}\s*\d[\d., ]*)\s+#", '$1.  ', $cancellation);
            }

            $h->general()
                ->cancellation(implode("\n", array_unique($cancellation)));

            $this->detectDeadLine($h, $cancellation);

            $count = count($this->getNodes($root, 'Guest name'));

            if ($count == 1) {
                $r = $h->addRoom();

                if ($type = $this->http->FindSingleNode("descendant::td[({$this->starts($this->t('Total price'))}) and not(.//td)][1]/following::table[normalize-space()!=''][1]/descendant::tr[normalize-space()!='' and not (.//tr)][1][not({$this->contains($this->t('Guest name'))})]",
                    $root)
                ) {
                    $r->setType($type);
                }

                if ($descr = $this->http->FindSingleNode("descendant::td[({$this->starts($this->t('Total price'))}) and not(.//td)][1]/following::table[normalize-space()!=''][1]/descendant::tr[normalize-space()!='' and not (.//tr)][1][not({$this->contains($this->t('Guest name'))})]/following-sibling::tr[1]",
                    $root)
                ) {
                    $r->setDescription($descr);
                }
            } else {
                $xpathRoom = "descendant::td[({$this->starts($this->t('Guest name'))}) and not(.//td)]/following-sibling::td[normalize-space(.)!=''][1]/ancestor::*[count(.//text()[{$this->starts($this->t('Guest name'))}]) > 1][1]/*[{$this->contains($this->t('Guest name'))}]";
                $types = $this->http->FindNodes($xpathRoom . "/descendant::tr[normalize-space()!='' and not (.//tr)][1][not({$this->contains($this->t('Guest name'))})]",
                    $root);
                $descrs = $this->http->FindNodes($xpathRoom . "/descendant::tr[normalize-space()!='' and not (.//tr)][1][not({$this->contains($this->t('Guest name'))})]/following-sibling::tr[1]",
                    $root);

                for ($i = 0; $i < $count; $i++) {
                    $r = $h->addRoom();

                    if ($count == count($types)) {
                        $r->setType($types[$i]);
                    }

                    if ($count == count($descrs)) {
                        $r->setDescription($descrs[$i]);
                    }
                }
            }
        }
    }

    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h, array $cancellationTexts)
    {
        $deadlineDates = [];

        foreach ($cancellationTexts as $cancellationText) {
            if (
                // en: until 27 November 2018 23:59 [New York]: US$0
                // até 3 de abril de 2020 23:59, às [Campo Grande]: R$ 0
                preg_match("/\b{$this->t('until')}\s+([^\[]+\d{1,2}:\d{2}[^\[]*?)(?:,\D*)?\s*\[[^\]]+\]\:\s*[^\d]{1,5}\s*0(\s|\.|$)/u",
                    $cancellationText, $m)
            || preg_match("/\b{$this->t('until')}\s+([^\[]+\d{1,2}:\d{2}[^\[]*?)(?:,\D*)?\s*\[[^\]]+\]\:\s*0\s*[^\d]{1,5}(\s|\.|$)/u",
                    $cancellationText, $m)
            ) {
                $deadlineDates[] = $this->normalizeDate($m[1]);
            }

            if (
                preg_match("#" . $this->preg_implode($this->t('non-refundable')) . "#u", $cancellationText, $m)
            ) {
                $h->booked()
                    ->nonRefundable();

                return true;
            }
        }

        if (!empty($deadlineDates)) {
            $h->booked()
                ->deadline(min($deadlineDates));
        }

        return false;
    }

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'contains(normalize-space(' . $node . '),"' . $s . '")'; }, $field)) . ')';
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

    private function getNodes(\DOMNode $root, string $s, ?string $re = null)
    {
        return $this->http->FindNodes("descendant::td[({$this->starts($this->t($s))}) and not(.//td)]/following-sibling::td[normalize-space(.)!=''][1]", $root, $re);
    }

    private function normalizeDate($date)
    {
//        $this->logger->debug('$date 1 = ' . print_r($date, true));
        $in = [
            // Monday 8 July 2019
            // martes, 9 de abril de 2019
            // mandag d. 9. maj 2022
            //  יום שבת, 4 בספטמבר 2021
            '#^\s*[\w\- ]+(?:\s*d\.\s*)?,?\s+(\d+)[.]?\s+(?:de\s+)?([[:alpha:]+]+)\s+(?:de\s+)?(\d{4})\s*$#u',
            // 8 July 2019
            // 7 de abril de 2019
            '#^\s*(\d+)\s+(?:de\s+)?([^\W\d]+)\s+(?:de\s+)?(\d{4})\s*$#u',
            // 27 November 2018 23:59
            // 7 de abril de 2019 23:59
            // 14 ноября 2020 г. 23:59
            '#^\s*(\d+)\s+(?:de\s+)?([^\W\d]+)\s+(?:de\s+)?(\d{4})(?:\s*г\.)?\s+(\d{1,2}:\d{2})\s*$#u',
            // February 7, 2020 11:59 PM
            '#^\s*([^\W\d]+)\s*(\d+)\s*,\s*(\d{4})\s+(\d{1,2}:\d{2}(?:[ap]m)?)\s*$#iu',
            //2020 年 9 月 26 日（星期六）
            '/^(\d{4})\s\S\s(\d+)\s\S\s(\d+)\D+$/u',

            //2020年9月21日 下午11:59
            '/^(\d{4})\s*\S\s*(\d+)\s*\S\s*(\d+)\D+([\d\:]+)/u',
            // 2021. június 20. (V)
            "/^\s*(\d{4})[.]?\s+([^\d\s\.\,]+)[.]?\s+(\d{1,2})[.]?\D*\s*$/ui",
            // 2021. május 20. 23:59
            "/^\s*(\d{4})[.]?\s+([^\d\s\.\,]+)[.]?\s+(\d{1,2})[.]?\s*(\d{1,2}:\d{2})\s*$/ui",
        ];
        $out = [
            '$1 $2 $3',
            '$1 $2 $3',
            '$1 $2 $3, $4',
            '$2 $1 $3, $4',
            '$3.$2.$1',

            '$3.$2.$1, $4',
            '$3 $2 $1',
            '$3 $2 $1, $4',
        ];
        $str = preg_replace($in, $out, $date);
//        $this->logger->debug('$date 2 = '.print_r( $date,true));
        $str = strtotime($this->dateStringToEnglish($str));

        return $str;
    }

    private function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function normalizeAmount($s): ?float
    {
        return is_numeric($s) ? (float) $s : null;
    }

    /**
     * @param string $string Unformatted string with currency
     */
    private function normalizeCurrency(string $string): string
    {
        $string = trim($string);
        $currences = [
            'GBP' => ['£'],
            'EUR' => ['€'],
            'USD' => ['$', 'US$'],
            'BRL' => ['R$'],
            'IDR' => ['Rp'],
            'SGD' => ['S$'],
            'PLN' => ['zł'],
        ];

        foreach ($currences as $currencyCode => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $currencyCode;
                }
            }
        }

        return $string;
    }

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
    }
}
