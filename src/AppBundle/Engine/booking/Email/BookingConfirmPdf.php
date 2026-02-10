<?php

namespace AwardWallet\Engine\booking\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BookingConfirmPdf extends \TAccountChecker
{
    public $mailFiles = "booking/it-37965039.eml, booking/it-92083459.eml"; // +1 bcdtravel(pdf)[en]
    public static $dictionary = [
        "en" => [
            //			"CONFIRMATION NUMBER:" => "",
            //			"Address:" => "",
            //			"Phone:" => "",
            "CHECK-IN"  => ["CHECK-IN", "CHECK‑IN", "CHECK-IN"],
            "CHECK-OUT" => ["CHECK-OUT", "CHECK‑OUT", "CHECK-OUT"],
            //			"Guest name:" => "",
            //            "Number of guests:" => "",
            //            "adult" => "",
            //            "child" => "",
            //            "Number of guests:" => "",
            //			"Prepayment" => "",
            //			"Meal plan:" => "",
            "ROOMS" => ["ROOMS", "UNITS"],
            //			"NIGHTS" => "",
            //			"Cancellation cost" => "",
            "Cancellation costEND" => ["Booking confirmation", "Benefits", "Special Requests", "About Us", "Booking Confirmation", "Changing the dates of your stay"],
            //            "Star rating:" => "",
            //            "Changing the dates of your stay is not possible." => "",
        ],
        "it" => [
            "CONFIRMATION NUMBER:" => "NUMERO DI CONFERMA:",
            "Address:"             => "Indirizzo:",
            "Phone:"               => "Telefono:",
            "CHECK-IN"             => "ARRIVO",
            "CHECK-OUT"            => "PARTENZA",
            "Guest name:"          => "Nome dell'ospite:",
            //            "Number of guests:" => "",
            //            "adult" => "",
            //            "child" => "",
            "Prepayment"           => "\n\s*.{0,5}\s*[\d,. ]{2,}.{0,5}\n",
            //			"Meal plan:" => "",
            "ROOMS"                => "CAMERE",
            "NIGHTS"               => "NOTTI",
            "Cancellation cost"    => "Costi di cancellazione",
            "Cancellation costEND" => ["Conferma della prenotazione", "\n-\n", "Non è possibile cambiare le date del tuo sog"],
            //            "Star rating:" => "",
            //            "Changing the dates of your stay is not possible." => "",
        ],
        "de" => [
            "CONFIRMATION NUMBER:"                             => ["BUCHUNGSNUMMER:", "Buchungsnummer:"],
            "Address:"                                         => "Adresse:",
            "Phone:"                                           => "Telefon:",
            "CHECK-IN"                                         => "ANREISE",
            "CHECK-OUT"                                        => "ABREISE",
            "Guest name:"                                      => "Name des Gastes:",
            //            "Number of guests:" => "",
            //            "adult" => "",
            //            "child" => "",
            "Prepayment"                                       => ["Stornierungsgebühren", "Vorauszahlung", "Zahlungsinformationen"],
            "Meal plan:"                                       => "Verpflegung:",
            "ROOMS"                                            => "ZIMMER",
            "NIGHTS"                                           => "NÄCHTE",
            "Cancellation policy"                              => "Stornierungsbedingungen",
            "Cancellation cost"                                => "Stornierungsgebühren",
            "Cancellation costEND"                             => ["Wichtige Information", "Buchungsbestätigung", "ANREISE"],
            "Star rating:"                                     => "Sterne:",
            "Changing the dates of your stay is not possible." => "Leider können Sie die Reisedaten für Ihren Aufenthalt nicht ändern.",
        ],
        "es" => [
            "CONFIRMATION NUMBER:" => ["NÚMERO DE CONFIRMACIÓN:"],
            "Address:"             => "Dirección:",
            "Phone:"               => "Teléfono:",
            "CHECK-IN"             => "ENTRADA",
            "CHECK-OUT"            => "SALIDA",
            "Guest name:"          => ["Nombre del cliente:", "Nombre del huésped:"],
            "adult"                => "adulto",
            "child"                => "menor",
            "Number of guests:"    => ["Número de personas:", "Cantidad de huéspedes:"],
            "Prepayment"           => ["Precio"],
            "Meal plan:"           => "Régimen de comidas:",
            "ROOMS"                => ["HABITACIONES", "UNIDADES"],
            "NIGHTS"               => "NOCHES",
            //            "Cancellation policy" => "",
            "Cancellation cost"    => "Cargos de cancelación",
            "Cancellation costEND" => ["Información adicional", "Confirmación de la reserva"],
            //            "Star rating:" => "",
            //            "Changing the dates of your stay is not possible." => "",
        ],
        "ro" => [
            "CONFIRMATION NUMBER:" => "NUMĂRUL CONFIRMĂRII:",
            "Address:"             => "Adresă:",
            "Phone:"               => "Telefon:",
            "CHECK-IN"             => "CHECK-IN",
            "CHECK-OUT"            => "CHECK-OUT",
            "Guest name:"          => "Numele clientului:",
            //            "Number of guests:" => "",
            //            "adult" => "",
            //            "child" => "",
            // "Prepayment" => "Stornierungsgebühren",
            // "Meal plan:" => "Verpflegung:",
            "ROOMS"                => "CAMERE",
            "NIGHTS"               => "NOPȚI",
            "Cancellation cost"    => "Taxă de anulare",
            "Cancellation costEND" => ["Modificarea datelor"],
            //            "Star rating:" => "",
            //            "Changing the dates of your stay is not possible." => "",
        ],
        "nl" => [
            "CONFIRMATION NUMBER:" => "BEVESTIGINGSNUMMER:",
            "Address:"             => "Adres:",
            "Phone:"               => "Telefoon:",
            "CHECK-IN"             => "INCHECKEN",
            "CHECK-OUT"            => "UITCHECKEN",
            //			"Guest name:" => "",
            //            "Number of guests:" => "",
            //            "adult" => "",
            //            "child" => "",
            // "Prepayment" => "Stornierungsgebühren",
            // "Meal plan:" => "Verpflegung:",
            "ROOMS"  => "KAMERS",
            "NIGHTS" => "NACHTEN",
            //			"Cancellation cost" => "",
            //			"Cancellation costEND" => [],
            //            "Star rating:" => "",
            //            "Changing the dates of your stay is not possible." => "",
        ],
        "pl" => [
            "CONFIRMATION NUMBER:" => "NUMER POTWIERDZENIA:",
            "Address:"             => "Adres:",
            "Phone:"               => "Telefon:",
            "CHECK-IN"             => "ZAMELDOWANIE",
            "CHECK-OUT"            => "WYMELDOWANIE",
            //			"Guest name:" => "",
            //            "Number of guests:" => "",
            //            "adult" => "",
            //            "child" => "",
            // "Prepayment" => "Stornierungsgebühren",
            // "Meal plan:" => "Verpflegung:",
            "ROOMS"  => "POKOJE",
            "NIGHTS" => "NOCE",
            //			"Cancellation cost" => "",
            //			"Cancellation costEND" => [],
            //            "Star rating:" => "",
            //            "Changing the dates of your stay is not possible." => "",
        ],
        "pt" => [
            "CONFIRMATION NUMBER:" => ["NÚMERO DE CONFIRMAÇÃO", "NÚMERO DE CONFIRMAÇÃO:"],
            "Address:"             => ["Endereço:", "Morada:"],
            "Phone:"               => "Telefone:",
            "CHECK-IN"             => ["ENTRADA", "CHECK-IN", "CHECK‑IN", "CHECK-IN"],
            "CHECK-OUT"            => ["SAÍDA", "CHECK-OUT", "CHECK‑OUT", "CHECK-OUT"],
            "Guest name:"          => "Nome do hóspede:",
            "Number of guests:"    => "Número de hóspedes:",
            //            "adult" => "",
            //            "child" => "",
            // "Prepayment" => "Stornierungsgebühren",
            "Meal plan:"           => "Plano de Refeições:",
            "ROOMS"                => "QUARTOS",
            "NIGHTS"               => ["DIÁRIAS", "NOITES"],
            "Cancellation cost"    => "Custos de cancelamento:",
            "Cancellation costEND" => ["Informações importantes"],
            //            "Star rating:" => "",
            //            "Changing the dates of your stay is not possible." => "",
            'Preço final'                                      => ['Preço', 'Preço final'],
            'aprox'                                            => ['cerca de', 'aprox'],
            'Changing the dates of your stay is not possible.' => 'Não é possível alterar as datas da sua reserva.',
        ],
        "ru" => [
            "CONFIRMATION NUMBER:"                             => "НОМЕР ПОДТВЕРЖДЕНИЯ:",
            "Address:"                                         => "Адрес:",
            "Phone:"                                           => "Телефон:",
            "CHECK-IN"                                         => "ЗАЕЗД",
            "CHECK-OUT"                                        => "ОТЪЕЗД",
            "Guest name:"                                      => "Имя гостя:",
            //            "Number of guests:" => "",
            //            "adult" => "",
            //            "child" => "",
            "Prepayment"                                       => "Предоплата",
            "Meal plan:"                                       => "Питание:",
            "ROOMS"                                            => "НОМЕРА",
            "NIGHTS"                                           => "НОЧИ",
            "Cancellation cost"                                => "Стоимость отмены бронирования:",
            "Cancellation costEND"                             => ["Важная информация"],
            "Star rating:"                                     => "Количество звезд:",
            "Changing the dates of your stay is not possible." => "Изменить даты проживания невозможно.",
        ],
        "fr" => [
            "CONFIRMATION NUMBER:"                             => "NUMÉRO DE CONFIRMATION :",
            "Address:"                                         => "Adresse:",
            "Phone:"                                           => "Téléphone :",
            "CHECK-IN"                                         => "ARRIVÉE",
            "CHECK-OUT"                                        => "DÉPART",
            "Guest name:"                                      => "Nom du client:",
            "adult"                                            => "adult",
            "child"                                            => "enfant",
            "Number of guests:"                                => "Nombre de personnes :",
            "Prepayment"                                       => "Prépaiement",
            "Meal plan:"                                       => "Repas:",
            "ROOMS"                                            => "HÉBERGEMENTS",
            "NIGHTS"                                           => "NUITS",
            "Cancellation cost"                                => "Frais d'annulation:",
            "Cancellation costEND"                             => ["Informations importantes"],
            "Star rating:"                                     => "Étoiles :",
            'Preço final'                                      => ['Montant final'],
            'aprox'                                            => ['environ'],
            "Changing the dates of your stay is not possible." => "Vous ne pouvez pas modifier les dates de votre séjour.",
        ],
    ];

    private static $providers = [
        'booking' => [
            'from' => '/[@\.]booking\.com/',
            'body' => [
                // keywords only
                'booking.com',
            ],
        ],
        'check' => [
            'from' => '/[@\.]check24\.de/',
            'body' => [
                '.check24.de',
            ],
        ],
    ];
    private $rePDF = [
        "en"  => 'Address:',
        "it"  => 'Pagherai nella valuta locale',
        "de"  => 'Buchungsbestätigung',
        "de1" => "Buchungsnummer:",
        "ro"  => "Prețul final afișat este suma pe care o veți plăti la proprietate.",
        "it1" => "Il prezzo totale indicato è l'importo che pagherai alla struttura.",
        "nl"  => "Reserveringsbevestiging",
        'pl'  => 'Potwierdzenie rezerwacji',
        'pt'  => 'Confirmação da reserva',
        'pt1' => 'Custos de cancelamento',
        'ru'  => 'НОМЕР ПОДТВЕРЖДЕНИЯ',
        'es'  => 'NÚMERO DE CONFIRMACIÓN',
        'fr'  => 'NUMÉRO DE CONFIRMATION',
    ];

    private $date;

    /** @var \HttpBrowser */
    private $pdf;
    private $lang = '';
    private $pdfLangs = [];
    private $pdfPattern = '.+\.pdf';
    private $filename;

    public function detectEmailFromProvider($from)
    {
        foreach (self::$providers as $value) {
            if (isset($value['from']) && preg_match($value['from'], $from) > 0) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            $flagProv = false;

            foreach (self::$providers as $value) {
                if (isset($value['body'])) {
                    if ($this->stripos($textPdf, $value['body'])) {
                        $flagProv = true;

                        break;
                    }

                    if (!empty($text = $parser->getHTMLBody())
                        && $this->stripos($text, $value['body'])
                    ) {
                        $flagProv = true;

                        break;
                    }
                }
            }

            if (!$flagProv) {
                continue;
            }

            foreach ($this->rePDF as $rePDF) {
                if (strpos($textPdf, $rePDF) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getHeader('date'));

        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        foreach ($pdfs as $pdf) {
            $body = str_replace("&#160;", " ", \PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_COMPLEX));

            $this->filename = $this->getAttachmentName($parser, $pdf);

            if ($body === null) {
                continue;
            }

            foreach ($this->rePDF as $lang => $rePDF) {
                if (strpos($body, $rePDF) !== false) {
                    $this->lang = substr($lang, 0, 2);

                    break;
                }
            }

            $this->pdf = clone $this->http;
            $this->pdf->SetEmailBody($body);
            $this->parsePdf($email);
        }

        if (null !== ($code = $this->getProvider($parser)) && $code !== 'booking') {
            $email->setProviderCode($code);
        }

        $email->setType('BookingConfirmPdf' . ucfirst($this->lang));

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

    public static function getEmailProviders()
    {
        return array_keys(self::$providers);
    }

    private function parsePdf(Email $email)
    {
        $patterns = [
            'date' => [
                "en"    => '\n*\s*(?<day>\d{1,2})\n*\s*(?<month>\w+)\n*\s*(?<weekDay>\w+)\n*\s*(?:[\w\s]+\s+)?(?<time>\d+\n*\:\n*\d+(?:\s*[ap]m)?)[\-\s]*\n*(?:(?<time2>\d+\n*\:\n*\d+(?:\s*[ap]m)?)|UNITS|CHECK-OUT)?',
                "it"    => '\s+(?<day>\d{1,2})\s+(?<month>[^-,.\d\s]{3,})\s+[^-,.\d\s]{2,}\s+(?:[\w ]+[ ]+)?(?<time>\d{1,2}:\d{2})(?:[ ]*-[ ]*\d{2})?',
                "it1"   => '\s+(?<day>\d{1,2})\s+(?<month>[^-,.\d\s]{3,})\s+[^-,.\d\s]{2,}\s+(?:[\w ]+\s+)?(?<time>\d{1,2}[ei:]\d{2})',
                "de"    => '\s+(?<day>\d{1,2})\s+(?<month>[^-,.\d\s]{3,})\s+[^-,.\d]{2,}?\s+(?:[\w ]+\s+)?(?<time>\d{1,2}\s*[eif:gj ]\s*\d{2})',
                'de1'   => '\s+(?<day>\d{1,2})\s+(?<month>[^-,.\d\s]{3,})\s+[^-,.\d]{2,}?\s+(?:[\w ]+\s+)?(?<time>\d{1,2}\s*[eif:gj ]\s*\d{2})\s*\-\s*(?<time2>\d{1,2}\s*[eif:gj ]\s*\d{2})',
                "ro"    => '\s+(?<day>\d{1,2})\s+(?<month>[^-,.\d\s]{3,})\s+[^-,.\d\s]{2,}\s+(?:[\w ]+\s+)?(?<time>\d{1,2}[ei:]\d{2})',
                "nl"    => '\s+(?<day>\d{1,2})\s+(?<month>[^-,.\d\s]{3,})\s+[^-,.\d\s]{2,}\s+(?:[\w ]+\s+)?(?<time>\d{1,2}[ei:k]\d{2})(?:[ ]*-[ ]*(?<time2>\d{1,2}[ei:k]\d{2}))?',
                "pl"    => '\s+(?<day>\d{1,2})\s+(?<month>[^-,.\d\s]{3,})\s+[^-,.\d\s]{2,}\s+(?:[\w ]+\s+)?(?<time>\d{1,2}[ei:k]\d{2})(?:[ ]*-[ ]*(?<time2>\d{1,2}[ei:k]\d{2}))?',
                "pt"    => '\s+(?<day>\d{1,2})\s+(?<month>[^-,.\d\s]{3,})\s+[^,.\d\s]{2,}\s+(?:[\w ]+\s+)?(?<time>\d{1,2}\s*[:p ]\s*\d{2})(?:[ ]*-[ ]*(?<time2>\d{1,2}\s*[:p ]\s*\d{2}))?',
                "es"    => '\s+(?<day>\d{1,2})\s+(?<month>[[:alpha:]]{3,})\s+[-[:alpha:]]{2,}\s+(?:[-[:alpha:] ]+\s+)?(?<time>\d{1,2}[:m ]\d{2})(?:[ ]*[-a][ ]*\s*(?<time2>\d{1,2}[:m ]\d{2}))?',
                "ru"    => '\s+(?<day>\d{1,2})\s+(?<month>[^-,.\d\s]{3,})\s+[^,.\d\s]{2,}\s+(?:[\w ]+\s+)?(?<time>\d{1,2}[:]\d{2})(?:[ ]*-[ ]*(?<time2>\d{1,2}[:]\d{2}))?',
                "fr"    => '\s+(?<day>\d{1,2})\s+(?<month>[^-,.\d\s]{3,})\s+[^,.\d\s]{2,}\s+(?:[\w \']+\s+)?(?<time>\d{1,2}[:]\d{2})(?:[ ]*-[ ]*(?<time2>\d{1,2}[:]\d{2}))?',
            ],
        ];

        $text = implode("\n\n", $this->pdf->FindNodes('//p/descendant::text()'));
        $text = str_replace("‑", '-', $text);
//        $this->logger->debug('$text = '.print_r( $text,true));

        $h = $email->add()->hotel();

        $this->pdfLangs[] = $this->lang; // if 2 identical pdf, but in different languages
        $it = [];

        // ConfirmationNumber
        if (preg_match('/' . $this->preg_implode($this->t('CONFIRMATION NUMBER:')) . '\s+([.\d]+)/', $text, $m)) {
            $h->general()->confirmation(str_replace('.', '', $m[1]));
        } else {
            $conf = $this->re("/Booking\s*[#]\s*(\d{5,})/", $this->filename);

            if (!empty($conf)) {
                $h->general()
                    ->confirmation($conf);
            }
        }
        // TripNumber
        // ConfirmationNumbers

        // Hotel Name
        // Address
        // Phone
        if (preg_match('/^(?:(?:.*\n+){0,3}.*\.[bB]ooking\.com.*\n+|)(?:\s*MTA\s*-\s*Mobile[ ]+Travel[ ]+Agents\s*\n)?(?:\d\/\d\n+)?((?:.+\n+){1,4})\s+' . $this->opt($this->t('Address:')) . '\s*([\s\S]{1,500}?)(?:\n\s*' . $this->t("Star rating:") . '[\s\S]*)?' . $this->t('Phone:') . '\s*(.+)/', $text, $m)) {
            $h->hotel()
                ->name(preg_replace("#\s+#", ' ', trim($m[1])))
                ->address(preg_replace("#\s+#", ' ', trim($m[2])));

            if (preg_match("/^\s*[\(\)\+]*\d[\d\(\)\-\+ \.]{5,}(?:$|\n)/", $m[3])) {
                $h->hotel()->phone(trim($m[3]));
            }
        } elseif (preg_match('/(.+\s*(?:\([^\(]+\))?)\s+' . $this->preg_implode($this->t('Address:')) . '\s*([\s\S]{1,500}?)(?:\n\s*' . $this->preg_implode($this->t("Star rating:")) . '[\s\S]*)?' . $this->preg_implode($this->t('Phone:')) . '\s*(.+)/', $text, $m)) {
            $h->hotel()
                ->name(preg_replace("#\s+#", ' ', trim($m[1])))
                ->address(preg_replace("#\s+#", ' ', trim($m[2])));

            if (preg_match("/^\s*[\(\)\+]*\d[\d\(\)\-\+ \.]{5,}(?:$|\n)/", $m[3])) {
                $h->hotel()->phone(trim($m[3]));
            }
        }

        // CheckInDate
        if ((isset($patterns['date'][$this->lang]) && preg_match('/' . $this->preg_implode($this->t('CHECK-IN')) . $patterns['date'][$this->lang] . '/uis', $text, $m))) {
            if (isset($m['weekDay']) && !empty($m['weekDay'])) {
                $it['CheckInDate'] = $this->normalizeDate($m['weekDay'] . ', ' . $m['day'] . ' ' . $m['month'] . ' ' . date('Y', $this->date) . ', ' . preg_replace('/\s+/', '', $m['time']));
            } else {
                $it['CheckInDate'] = $this->normalizeDate($m['day'] . ' ' . $m['month'] . ' ' . date('Y', $this->date) . ', ' . preg_replace('/\s+/', '', $m['time']));
            }

            if ($it['CheckInDate'] < $this->date) {
                $it['CheckInDate'] = strtotime('+1 years', $it['CheckInDate']);
            }

            if (!empty($it['CheckInDate'])) {
                $h->booked()->checkIn($it['CheckInDate']);
            }
        }

        // CheckOutDate
        if ((isset($patterns['date'][$this->lang . '1']) && preg_match('/' . $this->preg_implode($this->t('CHECK-OUT')) . $patterns['date'][$this->lang . '1'] . '/uis', $text, $m))
            || (isset($patterns['date'][$this->lang]) && preg_match('/' . $this->preg_implode($this->t('CHECK-OUT')) . $patterns['date'][$this->lang] . '/uis', $text, $m))
        ) {
            if (!empty($m['time2'])) {
                $it['CheckOutDate'] = $this->normalizeDate($m['day'] . ' ' . $m['month'] . ' ' . date('Y', $this->date) . ', ' . preg_replace('/\s+/', '', $m['time2']));
            } else {
                $it['CheckOutDate'] = $this->normalizeDate($m['day'] . ' ' . $m['month'] . ' ' . date('Y', $this->date) . ', ' . preg_replace('/\s+/', '', $m['time']));
            }

            if ($it['CheckOutDate'] < $this->date) {
                $it['CheckOutDate'] = strtotime('+1 years', $it['CheckOutDate']);
            }
            $h->booked()->checkOut($it['CheckOutDate']);
        }

        // for2 identical pdf in different languages
        foreach ($email->getItineraries() as $key => $iter) {
            if ($h == $it) {
                continue;
            }

            /** @var \AwardWallet\Schema\Parser\Common\Hotel $iter */
            if (!empty($iter->getHotelName()) && !empty($h->getHotelName())
                    && !empty($h->getCheckInDate()) && !empty($h->getCheckOutDate())
                    && ($iter->getHotelName() == $h->getHotelName()
                            || strncasecmp($iter->getHotelName(), $h->getHotelName(), strlen($iter->getHotelName())) === 0
                            || strncasecmp($iter->getHotelName(), $h->getHotelName(), strlen($h->getHotelName())) === 0)
                    && $iter->getCheckInDate() == $h->getCheckInDate()
                    && $iter->getCheckOutDate() == $h->getCheckOutDate()
                    && $this->pdfLangs[$key] !== $this->lang) {
                $email->removeItinerary($h);

                return;
            }
        }

        // GuestNames
        if (preg_match_all("/(?:{$this->opt($this->t('Guest name:'))}|Imię i nazwisko Gościa:)\s+(.*?)(?:\s{2,}|\n|\s+\/)/", $text, $guestNameMatches)) {
            foreach (array_unique($guestNameMatches[1]) as $pax) {
                $h->addTraveller($pax);
            }
        }

        // Rooms
        if (preg_match('/' . $this->opt($this->t('ROOMS')) . '\s+(\d+)\s*\/\s*' . $this->opt($this->t('NIGHTS')) . '/', $text, $m)) {
            $it['Rooms'] = $m[1];
        } elseif (preg_match('/(\d+) rooms/', $text, $m)) {
            $it['Rooms'] = $m[1];
        }

        if (!empty($it['Rooms'])) {
            $h->booked()
                ->rooms($it['Rooms']);
        }

        // RoomType
        // RoomTypeDescription
        if ($h->getRoomsCount() > 1) {
            if ($this->lang === 'en' || $this->lang === 'es' || $this->lang === 'fr' || $this->lang === 'de') {
                if (preg_match_all("/\n[ ]*([[:upper:]](?:.+[^\n,.:;!?]\n+){1,2})[ ]*{$this->opt($this->t('Guest name:'))}/u", $text, $m, PREG_SET_ORDER)) {
                    foreach ($m as $v) {
                        $room = $h->addRoom();
                        $room->setType(trim($v[1]));
                    }
                }
            } else {
                $this->logger->debug("try to parse rooms info");
                // broke parsing
                $email->add()->hotel();

                return;
            }
        } else {
            $room = $h->addRoom();

            if ($this->lang == "en") {
                if (preg_match("#(.+)\s+" . $this->t('Guest name:') . "\s*([\w\s]+).*\n((?:.*\n){1,20})" . $this->preg_implode($this->t('Prepayment')) . "#",
                    $text, $m)) {
                    $it['RoomType'] = trim($m[1]);
                    $it['RoomTypeDescription'] = trim(str_replace("\n\n\n", "\n", preg_replace("/[\s\S]*\sMeal Plan:.+/", '', $m[3])));
                } elseif (preg_match("#\n(.+)\n+" . $this->t('Guest name:') . "\s*([\w\s]+) / (?:for max. (\d+) person|for (\d) Adult#",
                    $text, $m)) {
                    $it['RoomType'] = trim($m[1]);
                    $it['Guests'] = trim($m[3] ? $m[3] : $m[4]);
                } elseif (preg_match("#\n([^:\n]{5,})\n+\s*" . $this->t('Guest name:') . "\s*.+#",
                    $text, $m)) {
                    $it['RoomType'] = trim($m[1]);
                } elseif (preg_match("#PRICE\s+(.+)\s+[A-Z]{3}\s+[\d,]+\s+Final price\s+(?:\(for\s+(\d+)\s+guest\))?#",
                    $text, $m)) {
                    $it['RoomType'] = trim($m[1]);

                    if (isset($m[2]) && !empty($m[2])) {
                        $it['Guests'] = trim($m[2]);
                    }
                } elseif (preg_match('/Cena\s+\(za\s+(\d+)\s+Gości\)/iu', $text, $m)) {
                    $it['Guests'] = $m[1];
                } elseif (preg_match("#below\, as this may contain important details not mentioned here\.\n+(.+)\n+Meal\s*Plan\:#",
                    $text, $m)) {
                    $it['RoomType'] = trim($m[1]);
                }

                if (preg_match('/Price\s+\(for (\d+) guests\)/', $text, $m)) {
                    $it['Guests'] = $m[1];
                }
            }

            if ($this->lang == "it") {
                if (preg_match_all("#(.+)\s+" . $this->t('Guest name:') . "\s*(.+)\n(?:.*\n){0,5}Pasti:.*\n((?:.*\n){1,20})" . $this->preg_implode($this->t('Prepayment')) . "#U", $text, $roomMatches)) {
                    foreach ($roomMatches[0] as $key => $value) {
                        $it['RoomType'][] = trim($roomMatches[1][$key]);
                        $it['RoomTypeDescription'] = trim(str_replace("\n\n\n", "\n", $roomMatches[3][$key]));
                    }
                    $it['RoomType'] = implode(", ", array_unique(array_filter($it['RoomType'])));
                }
            }

            if ($this->lang == "de") {
                if (preg_match_all("#" . $this->t('Meal plan:') . ".*\n((?:.*\n+){1,30})" . $this->preg_implode($this->t('Prepayment')) . "#U", $text, $roomMatches)) {
                    foreach ($roomMatches[0] as $key => $value) {
                        if (preg_match("#Zimmer \d+:\s+(.+)#u", $roomMatches[1][$key], $v)) {
                            $it['RoomType'] = trim($v[1], ":");
                            $node = preg_replace("#Zimmer \d+:\s+.+#u", '', $roomMatches[1][$key]);
                        } else {
                            $node = $roomMatches[1][$key];
                        }
                        $node = trim(str_replace("\n\n", "\n", $node));

                        if (count($checkFormat = explode("\n\n", $node)) == 2) {
                            $node = $checkFormat[0];

                            if (preg_match("#^\S+ \S+$#u", trim($checkFormat[1]))) {
                                $h->addTraveller(trim($checkFormat[1]));
                            }
                        }
                        $it['RoomTypeDescription'][] = $node;
                    }

                    if (isset($it['RoomTypeDescription']) && is_array($it['RoomTypeDescription'])) {
                        $it['RoomTypeDescription'] = implode(", ",
                            array_unique(array_filter($it['RoomTypeDescription'])));
                    }
                    $it['RoomTypeDescription'] = implode(" ",
                        array_slice(explode("\n", $it['RoomTypeDescription']), 0, 2));
                }

                if (!isset($it['RoomType']) && preg_match("#(.+)\s+" . $this->t('Guest name:') . "#u", $text, $m)) {
                    $it['RoomType'] = $m[1];
                }

                if (empty($it['RoomType']) && preg_match_all("#\n(.+)\n\s*Anreise:\s+.+#", $text, $m)) {
                    $it['RoomType'] = $m[1];
                }
            }

            if ($this->lang == 'ro') {
                if (preg_match_all("#\n(Camera [^\n]+.*?)\n\s*Nome dell'ospite:#ms", $text, $roomMatches)) {
                    $it['RoomType'] = array_map(function ($s) {
                        return trim(preg_replace("#\s+#ms", " ", $s));
                    }, $roomMatches[1]);
                }
            }

            if ($this->lang == 'it') {
                if (preg_match_all("#\n(Camera [^\n]+.*?)\n\s*Numele clientului:#ms", $text, $roomMatches)) {
                    $it['RoomType'] = array_map(function ($s) {
                        return trim(preg_replace("#\s+#ms", " ", $s));
                    }, $roomMatches[1]);
                }
            }

            if ($this->lang == 'nl') {
                if (preg_match_all("#\n(.*kamer.*)(.\n){1,2}\s*Maaltijden:#", $text, $roomMatches)) {
                    $it['RoomType'] = array_unique(array_map(function ($s) {
                        return trim(preg_replace("#\s+#ms", " ", $s));
                    }, $roomMatches[1]));
                }
            }

            if ($this->lang == "ru") {
                if (preg_match_all("#" . $this->t('Meal plan:') . ".*\n((?:.*\n+){1,20})" . $this->preg_implode($this->t('Prepayment')) . "#U", $text, $roomMatches)) {
                    foreach ($roomMatches[0] as $key => $value) {
                        $it['RoomTypeDescription'][] = trim(str_replace("\n\n", "\n", $roomMatches[1][$key]));
                    }

                    if (!empty($it['RoomTypeDescription'])) {
                        $it['RoomTypeDescription'] = implode(", ",
                            array_unique(array_filter($it['RoomTypeDescription'])));
                    }
                    $it['RoomTypeDescription'] = implode(" ",
                        array_slice(explode("\n", $it['RoomTypeDescription']), 0, 2));
                }

                if (preg_match("#(.+)\s+" . $this->t('Guest name:') . "#u", $text, $m)) {
                    $it['RoomType'] = $m[1];
                }
            }

            if ($this->lang == "pt") {
                if (preg_match("#\n([A-Z](?:.+\n){1,2})\s*" . $this->t('Guest name:') . "#u", $text, $m)) {
                    $it['RoomType'] = $m[1];
                }
            }

            if (preg_match("/\n([^:\n\/]{2,})\n+[ ]*{$this->opt($this->t('Guest name:'))}.+\n+[ ]*(?:{$this->opt($this->t('Number of guests:'))}|{$this->opt($this->t('Meal plan:'))})/u", $text, $m)
                || preg_match("/\n([^:\n\/]{2,})\n+[ ]*{$this->opt($this->t('Meal plan:'))}/", $text, $m)
            ) {
                $it['RoomType'] = $m[1];
            }

            if (!empty($it['RoomType'])) {
                $room
                    ->setType($it['RoomType'])
                ;
            }

            if (!empty($it['RoomTypeDescription'])) {
                $room
                    ->setDescription($it['RoomTypeDescription'])
                ;
            }
        }

        // Guests
        if (preg_match_all("/\s+{$this->opt($this->t('Number of guests:'))} *(\d{1,2}) {$this->opt($this->t('adult'))}/u", $text, $m)
            || preg_match('/YOUR\s+GROUP\s*(\d{1,3})\s*adult/i', $text, $m) // en
            || preg_match('/IL\s*TUO\s*GRUPPO\s*(\d{1,3})\s*adulti/i', $text, $m) // it
            || $this->lang === 'en' && preg_match('/Price\s+\(for\s+(\d{1,3})\s+guests?\)/i', $text, $m)
            || $this->lang === 'de' && preg_match_all('/Anzahl\s+der\s+Gäste:\s+(\d{1,3})\s+Erwachsene/u', $text, $m)
            || $this->lang === 'de' && preg_match('/IHRE\s+GRUPPE\s*.*(\d{1,3})\s*Erwachsene/', $text, $m)
            || $this->lang === 'de' && preg_match('/Preis\s*\(für *(\d{1,3}) *Gäste\)/', $text, $m)
            || $this->lang === 'nl' && preg_match('/UW\s+GROEP\s*.*(\d{1,3})\s*volwassenen/', $text, $m)
            || $this->lang === 'pl' && preg_match('/Cena\s+\(za\s+(\d{1,3})\s+Gości\)/iu', $text, $m)
            || $this->lang === 'es' && preg_match('/TU\s*GRUPO\s*(\d{1,3})\s*adulto/i', $text, $m)
            || $this->lang === 'es' && preg_match('/Precio\s+\(para\s+(\d{1,3})\s+(?:personas|huéspedes)\)/i', $text, $m)
            || $this->lang === 'fr' && preg_match('/Tarif\s+\(pour\s+(\d{1,3})\s+personnes\)/i', $text, $m)
            || $this->lang === 'pt' && preg_match('/SEU\s+GRUPO\s+(\d{1,3})\s+adultos/i', $text, $m)
            || $this->lang === 'pt' && preg_match('/Preço\s\n\(para\s(\d{1,3})\s(?:pessoas|hóspede)\)/iu', $text, $m)
            || $this->lang === 'pt' && preg_match('/Tarif\s\n\(pour\s(\d{1,3})\spersonnes\)/iu', $text, $m)
        ) {
            $it['Guests'] = is_array($m[1]) ? array_sum($m[1]) : $m[1];
        }

        if (!empty($it['Guests'])) {
            $h->booked()
                ->guests($it['Guests']);
        }

        // Kids
        if (preg_match_all("/\s+{$this->opt($this->t('Number of guests:'))}.*\D(\d{1,3})\s+{$this->opt($this->t('child'))}/u", $text, $m)
            || $this->lang === 'en' && preg_match('/YOUR\s+GROUP\s*.*\b(\d{1,3})\s*children/i', $text, $m)
            || $this->lang === 'nl' && preg_match('/UW\s+GROEP\s*.*\b(\d{1,3})\s*kinderen/i', $text, $m)
        ) {
            $it['Kids'] = is_array($m[1]) ? array_sum($m[1]) : $m[1];
        }

        if (!empty($it['Kids'])) {
            $h->booked()
                ->kids($it['Kids']);
        }

        // Rate
        // RateType
        // CancellationPolicy

        $posBegin = mb_strpos($text, $this->t('Cancellation cost'));

        if ($posBegin !== false) {
            $posBegin = $posBegin + mb_strlen($this->t('Cancellation cost'));
            $posEnd = [];

            foreach ($this->t('Cancellation costEND') as $value) {
                $posEnd[] = mb_strpos($text, $value, $posBegin);
            }
            $posEnd = array_values(array_filter($posEnd));

            if (!empty($posEnd[0])) {
                $posEnd = min($posEnd);
                $it['CancellationPolicy'] = trim(str_replace(["\n", '   ', '  '], ' ', trim(mb_substr($text, $posBegin, $posEnd - $posBegin), ' :')));

                if (($pos = mb_stripos($it['CancellationPolicy'], $this->t('Changing the dates of your stay is not possible.'))) !== false) {
                    $it['CancellationPolicy'] = trim(mb_substr($it['CancellationPolicy'], 0, $pos + mb_strlen($this->t('Changing the dates of your stay is not possible.'))));
                }
                $h->general()->cancellation($it['CancellationPolicy']);

                if (false !== stripos($it['CancellationPolicy'], 'If you choose to cancel you will not be refunded')) {
                    $h->booked()->nonRefundable();
                }
            }
        } else {
            if (preg_match("#{$this->preg_implode($this->t('Cancellation policy'))}\n\n(.+)#", $text, $m)) {
                $h->general()
                    ->cancellation($m[1]);
            } elseif (preg_match("#{$this->preg_implode($this->t('Cancellation policy:'))}(.+){$this->preg_implode($this->t('Refund schedule:'))}#s", $text, $m)) {
                $h->general()
                    ->cancellation(str_replace("\n", "", $m[1]));

                if (stripos($h->getCancellation(), 'the fee will bethe total price of the reservation') !== false) {
                    $h->setNonRefundable(true);
                }
            } elseif (preg_match("#{$this->t('Custos de Cancelamento:')}[\n\s]+(até\s\d{1,2}\sde\s[A-z]+\sde\s\d{4}\s\d{1,2}:\d{1,2}.+:.+de\s\d{1,2}\sde\s[A-z]+\sde\s\d{4}\s\d{1,2}:\d{1,2}.+\d+[\d,.])\s-#us", $text, $m)) {
                $h->general()
                        ->cancellation($m[1]);
            }
        }
        // Cost
        // Taxes
        // Total
        // Currency
        $it['Total'] = null;
        $text = str_replace(['¥', '€', '£'], ['YEN', 'EUR', 'GBP'], $text);

        if ($this->lang == "en") {
            if (preg_match('/Price with all charges included[ ]*:[ ]*(?:approx\.)?[ ]*(?<currency>[^\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*)/', $text, $m)
                || preg_match('/(?:Final price|Price).+?\s+approx\.*?\n*(?<currency>[A-Z]{3})[ ]*(?<amount>\d[,.\'\d ]*)/s', $text, $m)
                || preg_match('/\n[ ]*(?<currency>[A-Z]{3})[ ]*(?<amount>\d[,.\'\d ]*?)\s+The final price shown/s', $text, $m)
                || preg_match("/You will pay in the local currency (?<currency>[^\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*)/", $text, $m)
                || preg_match("/Payment amount\s+(?<currency>[^\d)(]{1,5}?)[ ]*(?<amount>\d[,.\'\d ]*)/", $text, $m)
                || preg_match("/\s+You'll\s+pay\s+\s+(?<amount>\d[,.\'\d ]*?)\s+in\s+(?<currency>[A-Z]{3})\s+\.\s*\n/", $text, $m)
            ) {
                // You'll pay 1,034.60 in EUR.
                $currency = str_replace("US$", "USD", $m['currency']);
                $currencyCode = preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null;
                $it['Currency'] = $currency;
                $it['Total'] = PriceHelper::parse($m['amount'], $currencyCode);
            }
        }

        if ($this->lang == "it") {
            if (preg_match('/PREZZO\s*(?:circa)?\s*(?<currency>[^\d)(]+?)[ ]*([,.\d ]+)/', $text, $m)) {
                $currency = trim(str_replace(['€'], ['EUR'], $m['currency']));
                $currencyCode = preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null;
                $it['Currency'] = $currency;
                $it['Total'] = PriceHelper::parse($m[2], $currencyCode);
            }
        }

        if ($this->lang == "de") {
            if (preg_match('/(?:PREIS|Endpreis)\s+(?<currency>[^\d\s]{1,5}?)\s+(\d[,.\d ]*)\s/', $text, $m)
                || preg_match('/\nPreis\s+\([^\)]+\)\s+(?:ca\.\s+)?(?<currency>[^\d\s]{1,5}?)\s+(\d[,.\d ]*)\s/', $text, $m)
                || preg_match('/\nGesamtpreis:\s+(?<currency>[^\d\s]{1,5}?)\s+(\d[,.\d ]*)\s/', $text, $m)
            ) {
                $currency = $this->currency($m['currency']);
                $currencyCode = preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null;
                $it['Currency'] = $currency;
                $it['Total'] = PriceHelper::parse($m[2], $currencyCode);
            }
        }

        if ($this->lang == "ro") {
            if (preg_match('/Preț\s+\([^\)]+\)\s+(?<currency>[^\d)(]+?)[ ]*([\d., ]+)/', $text, $m)) {
                $currency = $this->currency($m['currency']);
                $currencyCode = preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null;
                $it['Currency'] = $currency;
                $it['Total'] = PriceHelper::parse($m[2], $currencyCode);
            }
        }

        if ($this->lang == "it") {
            if (preg_match('/Prezzo\s+\([^\)]+\)\s+(?<currency>[^\d)(]+?)[ ]*([\d., ]+)/ms', $text, $m)) {
                $currency = $this->currency($m['currency']);
                $currencyCode = preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null;
                $it['Currency'] = $currency;
                $it['Total'] = PriceHelper::parse($m[2], $currencyCode);
            }
        }

        if ($this->lang == "nl") {
            if (preg_match('/Eindprijs\s+\([^\)]+\)\s+(?<currency>[^\d)(]+?)[ ]*([\d., ]+)/', $text, $m)) {
                $currency = $this->currency($m['currency']);
                $currencyCode = preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null;
                $it['Currency'] = $currency;
                $it['Total'] = PriceHelper::parse($m[2], $currencyCode);
            }
        }

        if ($this->lang == "pl") {
            if (preg_match('/Cena\s+\([^\)]+\)\s+([\d.,]+)\s+(?<currency>\w+)/ui', $text, $m)) {
                $currency = $this->currency($m['currency']);
                $currencyCode = preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null;
                $it['Currency'] = $currency;
                $it['Total'] = PriceHelper::parse($m[1], $currencyCode);
            }
        }

        if ($this->lang == "pt") {
            if (preg_match('/' . $this->opt($this->t('Preço final')) . '\s+\([^\)]+\)\s+(?:' . $this->opt($this->t('aprox')) . '\.\s+)?(?<currency>[^\s\d]+?)\s+([\d.,]+)/ui', $text, $m)
                || preg_match('/' . $this->opt($this->t('Preço final')) . '\s+\([^\)]+\)\s+(?:' . $this->opt($this->t('aprox')) . ')(?:[\s]+(?<currency>[A-Z]{1}.|[A-Z]{3}))\s([\d+[\d,.]+)/ui', $text, $m)
            ) {
                $currency = $this->currency($m['currency']);
                $currencyCode = preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null;
                $it['Currency'] = $currency;
                $it['Total'] = PriceHelper::parse($m[2], $currencyCode);
            }
        }

        if ($this->lang == "es") {
            if (preg_match('/Precio\s+\([^)]+\)\s+(?:aprox\.\s+)?(?<currency>[^\s\d]+?)\s+([\d.,]+)/ui', $text, $m)) {
                $currency = $this->currency($m['currency']);
                $currencyCode = preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null;
                $it['Currency'] = $currency;
                $it['Total'] = PriceHelper::parse($m[2], $currencyCode);
            }
        }

        if ($this->lang == "ru") {
            if (preg_match('/\n\s*Итоговая цена\s+\([^)]+\)\s+(?:о(?:к|ĸ)оло\s+)?(?<currency>[^\s\d]{1,5}?)\s+(\d[\d., ]*)\s+/ui', $text, $m)
                || preg_match('/\n\s*Цена\s+\([^)]+\)\s+(?:о(?:к|ĸ)оло\s+)?(?<currency>[^\s\d]{1,5}?)\s+(\d[\d., ]*)\s+/ui', $text, $m)
            ) {
                $currency = $this->currency($m['currency']);
                $currencyCode = preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null;
                $it['Currency'] = $currency;
                $it['Total'] = PriceHelper::parse($m[2], $currencyCode);
            }
        }

        if ($this->lang == "fr") {
            if (preg_match('/\n\s*Montant final\s+\([^)]+\)\s+(?<currency>[^\s\d]{1,5}?)\s+(\d[\d., ]*)\s+/i', $text, $m)
                || preg_match('/\n\s*Tarif\s+\([^)]+\)\s+(?:environ\s+)?(?<currency>[^\s\d]{1,5}?)\s+(\d[\d., ]*)\s+/ui', $text, $m)
            ) {
                $currency = $this->currency($m['currency']);
                $currencyCode = preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null;
                $it['Currency'] = $currency;
                $it['Total'] = PriceHelper::parse($m[2], $currencyCode);
            }
        }

        $roomsCount = $h->getRoomsCount() ?? '\d{1,3}';
        $costValue = $this->re("/\n[ ]*PRICE\n+[ ]*{$roomsCount}[ ]+rooms?\n+(.*\d.*)/i", $text) // en
            ?? $this->re("/\n[ ]*PRECIO\n+[ ]*{$roomsCount}[ ]+habitación\n+(.*\d.*)/i", $text) // es
        ;

        if ($it['Total'] !== null && !empty($it['Currency'])) {
            $h->price()
                ->currency($it['Currency'])
                ->total(PriceHelper::parse($it['Total'], $it['Currency']));

            if ($currencyCode && !empty($m['currency'])
                && preg_match('/^(?:' . preg_quote($m['currency'], '/') . ')?[ ]*(?<amount>\d[,.\'\d ]*)$/', $costValue, $m)
            ) {
                $h->price()->cost(PriceHelper::parse($m['amount'], $currencyCode));
            }
        } elseif (preg_match('/^(?<currency>[^\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*)$/', $costValue, $matches)) {
            $currency = $this->currency($matches['currency']);
            $currencyCode = preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null;
            $h->price()->currency($currency)->cost(PriceHelper::parse($matches['amount'], $currencyCode));
        }

        if (!empty($node = $h->getCancellation())) {
            $this->detectDeadLine($h, $node);
        }
    }

    /**
     * Here we describe various variations in the definition of dates deadLine.
     */
    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h, string $cancellationText): void
    {
        if (preg_match("#^until (?<date>\d{1,2} \w+ \d{4} \d+:\d+|\w+ \d+, \d{4} \d+:\d+(?:\s*[ap]m)?) \[[A-Z]{3,4}\] : [^\d\s]{1,5}[ ]?0 from (?:\d{1,2} \w+ \d{4} \d+:\d+|\w+ \d+, \d{4} \d+:\d+(?:\s*[ap]m)?)#i", $cancellationText, $m) // en
            || preg_match("#^bis (?<date>\d{1,2}[.]? [^\d\s]+ \d{4} \d+[f:]\d+) \[[A-Z]{3,4}\] : [^\d\s]{1,5}[ ]?0 ab \d{1,2}\b#ui", $cancellationText, $m) // de - bis 17. Februar 2019 23:59 [MSK] : RUB 0     |    bis 5. November 2019 23f59 [CET] : € 0
            || preg_match("#^до (?<date>\d{1,2} [^\d\s]+ \d{4}(?:\s*г\.)? \d+:\d+) \[[A-Z]{3,4}\] : 0[ ]?[^\d\s]{1,5} с \d{1,2}\b#ui", $cancellationText, $m) // ru - до 17 февраля 2019 г. 23:59 [MSK] : 0 руб. с 18 февраля
            || preg_match("#^Diese Buchung ist bis zum (?<date>\d+\.\d+\.\d{4} um \d+:\d+ Uhr) \(.+\) kostenlos stornierbar\.#ui", $cancellationText, $m) // de - Diese Buchung ist bis zum 31.07.2019 um 15:00 Uhr (Hotel-Ortszeit) kostenlos stornierbar.
            || preg_match("#^Hasta el (?<date>\d+ de \w+ de \d{4} \d+[m:]\d+) \[[A-Z]{3,4}\] : (?:(?:\D )?0[ ]?[^\d\s]{1,5}|[^\d\s]{1,5}[ ]?0[ ]+)#ui", $cancellationText, $m) // es - Hasta el 4 de agosto de 2019 23m59 [EEST] : € 0 desde 5 de agosto de 2019 0m00
            || preg_match("#^até às (?<date>\d+ de \w+ de \d{4} \d+[: ]\d+), \[[A-Z]{3,4}\] : [^\d\s]{1,5}[ ]?0[ ]+a partir#ui", $cancellationText, $m) // pt - até às 14 de fevereiro de 2021 23 59, [EST] : MXN 0 a partir de 15
            || preg_match("#^until\s*(?<date>\w+\s+\d+\,\s*\d{4}\s*[\d\:]+\s*A?P?M?)\s*\[CEST\]\:\s*\D\s+0#ui", $cancellationText, $m) // en
            || preg_match("#^until\s*(?<date>\d+\s*\w+?\s*\d{4}\s*[\d\:]+\s*A?P?M?)\s*\[CEST\]\:\s*\D\s+0#ui", $cancellationText, $m) // en
//            || preg_match("#^até\s(?<date>\d{1,2}\sde\s[A-z]+\sde\s\d{4}\s\d{1,2}:\d{1,2})#ui", $cancellationText, $m)
            // fr - jusqu'au 23 octobre 2021 23:59 [CEST] : € 0
            || preg_match("#^jusqu'au\s+(?<date>\d{1,2}\s[[:alpha:]]+\s\d{4}\s\d{1,2}:\d{1,2}) \[[A-Z]{3,4}\] : (?:[^\d\s]{1,5}[ ]?0|(?:\D )?0[ ]?[^\d\s]{1,5})[ ]+#ui", $cancellationText, $m)
        ) {
            $h->booked()
                ->deadline($this->normalizeDate($m['date']));

            return;
        }
        $h->booked()
            ->parseNonRefundable("#Dies ist eine Buchung mit niedrigem Preis – kein Geld zurück#i")
            ->parseNonRefundable("#This reservation cannot be canceled free of charge#i");
    }

    private function getProvider(PlancakeEmailParser $parser)
    {
        foreach (self::$providers as $code=>$value) {
            if (isset($value['from']) && preg_match($value['from'], $parser->getCleanFrom()) > 0) {
                return $code;
            }
        }

        foreach (self::$providers as $code=>$value) {
            if (isset($value['body'])) {
                if (isset($this->pdf) && !empty($text = $this->pdf->Response['body'])
                && $this->stripos($text, $value['body'])) {
                    return $code;
                }

                if (!empty($text = $parser->getHTMLBody())
                    && $this->stripos($text, $value['body'])) {
                    return $code;
                }
            }
        }

        return null;
    }

    private function stripos($haystack, $arrayNeedle)
    {
        $arrayNeedle = (array) $arrayNeedle;

        foreach ($arrayNeedle as $needle) {
            if (stripos($haystack, $needle) !== false) {
                return true;
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

    private function normalizeDate($str)
    {
        //$this->logger->debug('date in: ' . print_r($str, true));
        //		$year = date("Y", $this->date);
        $in = [
            "#^(\d+)[.]?\s+([^\d\s]+)\s+(\d{4})(?:\s*г\.)?[,]?\s*(\d+)[eiksgpfjm: ](\d+(?:\s*[ap]m)?)$#ui", // 23 OKTOBER 2017, 14e00, 17. Februar 2019 23:59, 17 февраля 2019 г. 23:59
            "#^(\d+)\.(\d+)\.(\d{4}) um (\d+:\d+) Uhr$#",
            "#^(\d+) de (\w+) de (\d{4}) (\d+)[:m ](\d+)$#", //4 de agosto de 2019 23m59
            "#(\d{1,2})\sde\s([A-z]+)\sde\s(\d{4})\s(\d{1,2}:\d{1,2})#",
        ];
        $out = [
            "$1 $2 $3, $4:$5",
            "$3-$2-$1 $4",
            "$1 $2 $3 $4:$5",
            "$1 $2 $3 $4",
        ];
        $date = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        if (preg_match("#^(?<week>[[:alpha:]]{2,}), (?<date>\d+ [[:alpha:]]{3,} \d{4}\,?\s*[\d\:]*a?p?m?)\s*$#u", $date, $m)) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($m['week'], $this->lang));
            $date = EmailDateHelper::parseDateUsingWeekDay($m['date'], $weeknum);
        } elseif (preg_match("#\b\d{4}\b#", $date)) {
            $date = strtotime($date);
        } else {
            $date = null;
        }

        return $date;
        //$this->logger->debug('date out: '.print_r( $str,true));
    }

    private function currency($s)
    {
        $sym = [
            '€'  => 'EUR',
            'R$' => 'BRL',
            '$'  => 'USD',
            '£'  => 'GBP',
            'zł' => 'PLN',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }

        foreach ($sym as $f=>$r) {
            if (strpos($s, $f) !== false) {
                return $r;
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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }

    private function getAttachmentName(PlancakeEmailParser $parser, $pdf)
    {
        $header = $parser->getAttachmentHeader($pdf, 'Content-Type');

        if (preg_match('/name=[\"\']*(.+\.pdf)[\'\"]*/i', $header, $matches)) {
            return $matches[1];
        }

        return false;
    }
}
