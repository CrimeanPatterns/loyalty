<?php

namespace AwardWallet\Engine\fourchette\Email;

// TODO: delete what not use
use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class Confirmation2024 extends \TAccountChecker
{
    public $mailFiles = "fourchette/it-682591926.eml, fourchette/it-682820582.eml, fourchette/it-682905576.eml, fourchette/it-683032644.eml, fourchette/it-683566005.eml, fourchette/it-685326542.eml";

    private $detectFrom = "reservation@restaurant-information.com";
    private $detectSubject = [
        // en
        'Confirmation of your booking at',
        'Confirmation of your reservation at',
        'has been declined', // Your booking at SKYKITCHEN has been declined
        'is awaiting confirmation',
        'Reconfirmation request: A response is required for your booking at',
        'Reconfirmation request: A response is required for your reservation at',
        'Finalize your reservation at',
        'Your waiting list request for',
        'Update regarding your reservation at',
        'Cancellation of your reservation at',
        'Cancellation of your booking at',
        'Reservation confirmation:',
        // it
        'Conferma della prenotazione presso',
        ' è stata rifiutata',
        ' è in attesa di conferma',
        'Richiesta di riconferma: La risposta è obbligatoria per la prenotazione presso',
        'Cancellazione della prenotazione presso',
        // pt
        'Confirmação da sua reserva no restaurante',
        'Pedido de nova confirmação: a sua reserva no',
        'Cancelamento da sua reserva no restaurante',
        // es
        'está pendiente de respuesta', // Solicitud de reconfirmación: tu reserva en Besta Barcelona está pendiente de respuesta
        'Cancelación de tu reserva en',
        'Confirmación de tu reserva en',
        'Cancelació de la vostra reserva a ',
        // fr
        'Annulation de votre réservation au restaurant',
        'Confirmation de votre réservation au restaurant',
        'Demande de reconfirmation : votre réservation au restaurant',
        'Votre réservation au restaurant',
        'Information au sujet de votre réservation au restaurant',
        'Terminez votre réservation chez',
        // nl
        'Annulering van je reservering bij',
        // de
        'Bitte bestätigen Sie Ihre Reservierung! Lassen Sie uns wissen, ob Sie Ihre Reservierung bei',
        'Bestätigung Ihrer Reservierung für das Restaurant',
        // sv
        'Bekräftelse av din bokning på',
    ];
    private $detectBody = [
        'en' => [
            'Booking recorded',
            'Reservation recorded',
            'Reservation on the waiting list',
            'Group request declined',
            'Group request pending',
            'One more step',
            'Final confirmation of your booking at',
            'Reservation updated',
            'Reservation canceled',
            'Reservation request pending',
            'Booking on the waiting list',
            'Reservation cancelled',
        ],
        'it' => [
            'Prenotazione registrata',
            'Richiesta di gruppo rifiutata',
            'Richiesta di gruppo in sospeso',
            'Un ultimo passaggio',
            'Prenotazione cancellata',
        ],
        'pt' => [
            'Reserva registada',
            'Mais um passo',
            'A sua reserva no nosso restaurante foi cancelada',
        ],
        'es' => [
            'Un paso más',
            'Reserva cancelada',
            'Reserva registrada',
        ],
        'fr' => [
            'Réservation annulée',
            'Réservation enregistrée',
            'Dernière étape',
            'Réservation modifiée',
        ],
        'nl' => [
            'Reservering geannuleerd',
        ],
        'de' => [
            'Nur noch ein letzter Schritt',
            'Aufgenommene Reservierung',
        ],
        'sv' => [
            'Bokningen har registrerats',
        ],
    ];

    private $date;
    private $lang = '';
    private static $dictionary = [
        'en' => [
            'Dear '                           => ['Dear ', 'Hi ', 'Bonjour ', 'Dear Madam, Dear Sir'],
            'Reservation on the waiting list' => ['Reservation on the waiting list', 'Booking on the waiting list', 'You have been added to our waiting list'],
            'CancelledReservation'            => ['Group request declined', 'Reservation canceled', 'Your reservation at our restaurant has been canceled',
                'Reservation cancelled', 'Your booking at our restaurant has been cancelled', ],
            'PendingReservation' => ['Group request pending', 'we are still awaiting confirmation from the restaurant', 'Reservation request pending',
                'Your reservation has been registered but it needs confirmation from the restaurant', ],
            // 'Get directions' => '',
            // 'Date' => '',
            // 'Hour' => '',
            // 'People' => '',
            'Your table will be booked for'                    => ['Your table will be booked for', 'Your table will be reserved for'],
            'For more information, feel free to contact us on' => ['For more information, feel free to contact us on',
                'For more information, feel free to contact us at', ],
        ],
        'it' => [
            'Dear ' => ['Gentile ', 'Ciao '],
            // 'Reservation on the waiting list' => '',
            'CancelledReservation' => ['Richiesta di gruppo rifiutata', 'La tua prenotazione di gruppo è stata rifiutata.',
                'Prenotazione cancellata', 'La tua prenotazione presso il nostro ristorante è stata cancellata', ],
            'PendingReservation'                               => ['Richiesta di gruppo in sospeso', 'La tua prenotazione è stata registrata ma siamo ancora in attesa di conferma dal ristorante'],
            'Get directions'                                   => 'Come raggiungerci',
            'Date'                                             => 'Data',
            'Hour'                                             => 'Ora',
            'People'                                           => 'Persone',
            'Your table will be booked for'                    => 'Il tavolo sarà riservato per',
            'For more information, feel free to contact us on' => 'Per maggiori informazioni, non esitare a contattarci al numero',
        ],
        'pt' => [
            'Dear ' => ['Olá,', 'Sr./Sra.,'],
            // 'Reservation on the waiting list' => '',
            'CancelledReservation' => [' Reserva cancelada', 'A sua reserva no nosso restaurante foi cancelada'],
            // 'PendingReservation' => ['', ''],
            'Get directions'                                   => 'Obter direções',
            'Date'                                             => 'Data',
            'Hour'                                             => 'Hora',
            'People'                                           => 'Pessoas',
            'Your table will be booked for'                    => 'A sua mesa ficará reservada durante',
            'For more information, feel free to contact us on' => [
                'Para obter mais informações acerca do cancelamento, ou se o mesmo tiver sido realizado por engano, não hesite em contactar-nos através do número',
                'Para mais informações, não hesite em contactar-nos através do número',
            ],
        ],
        'es' => [
            'Dear ' => ['Buenos días,', 'Benvolgut/da,', 'Estimado/a'],
            // 'Reservation on the waiting list' => '',
            'CancelledReservation' => ['Reserva cancelada', 'Tu reserva en nuestro restaurante se ha cancelado'],
            // 'PendingReservation' => ['', ''],
            'Get directions'                => 'Ver indicaciones',
            'Date'                          => 'Fecha',
            'Hour'                          => 'Hora',
            'People'                        => 'Personas',
            'Your table will be booked for' => 'La mesa estará reservada durante',
            // 'For more information, feel free to contact us on' => '',
        ],
        'fr' => [
            'Dear ' => ['Bonjour ', 'Bonjour M.'],
            // 'Reservation on the waiting list' => '',
            'CancelledReservation' => ['Réservation annulée', 'Votre réservation dans notre restaurant a été annulée'],
            // 'PendingReservation' => ['', ''],
            'Get directions'                                   => 'Obtenir l\'itinéraire',
            'Date'                                             => 'Date',
            'Hour'                                             => 'Heure',
            'People'                                           => 'Personnes',
            'Your table will be booked for'                    => 'Votre table sera réservée pendant',
            'For more information, feel free to contact us on' => [
                'Pour en savoir plus sur cette annulation, ou s\'il s\'agit d\'une erreur, contactez-nous au',
                'Pour davantage d\'informations, n\'hésitez pas à nous appeler au ',
            ],
        ],
        'nl' => [
            'Dear ' => 'Beste ',
            // 'Reservation on the waiting list' => '',
            'CancelledReservation' => ['Reservering geannuleerd', 'Je reservering bij ons restaurant is geannuleerd'],
            // 'PendingReservation' => ['', ''],
            'Get directions' => 'Routebeschrijving bekijken',
            'Date'           => 'Datum',
            'Hour'           => 'Tijd',
            'People'         => 'Personen',
            // 'Your table will be booked for' => 'La mesa estará reservada durante',
            'For more information, feel free to contact us on' => 'Voor meer informatie over je annulering, of als dit een fout was, kun je contact met ons opnemen via',
        ],
        'de' => [
            'Dear ' => ['Hallo ', 'Sehr geehrte(r) Frau/Herr '],
            // 'Reservation on the waiting list' => '',
            // 'CancelledReservation' => ['Reservering geannuleerd', 'Je reservering bij ons restaurant is geannuleerd'],
            // 'PendingReservation' => ['', ''],
            'Get directions' => 'Wegbeschreibung',
            'Date'           => 'Datum',
            'Hour'           => 'Uhrzeit',
            'People'         => 'Personen',
            // 'Your table will be booked for' => 'La mesa estará reservada durante',
            // 'For more information, feel free to contact us on' => 'Voor meer informatie over je annulering, of als dit een fout was, kun je contact met ons opnemen via',
        ],
        'sv' => [
            'Dear ' => 'Hej ',
            // 'Reservation on the waiting list' => '',
            // 'CancelledReservation' => ['Reservering geannuleerd', 'Je reservering bij ons restaurant is geannuleerd'],
            // 'PendingReservation' => ['', ''],
            'Get directions'                                   => 'Hämta vägbeskrivning',
            'Date'                                             => 'Datum',
            'Hour'                                             => 'Timme',
            'People'                                           => 'Personer',
            'Your table will be booked for'                    => 'Ditt bord bokas för',
            'For more information, feel free to contact us on' => 'Kontakta oss gärna på',
        ],
    ];

    // Main Detects Methods
    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers["subject"], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[{$this->contains(['.lafourchette.com', '.thefork.', '.restaurant-information.com'], '@href')}]")->length === 0) {
            return false;
        }

        foreach ($this->detectBody as $lang => $detectBody) {
            if ($this->http->XPath->query("//*[{$this->contains($detectBody)}]")->length > 0) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if ($this->assignLang() !== true && $this->detectEmailByBody($parser) !== true) {
            $this->logger->debug("can't determine a language");

            return $email;
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $this->date = strtotime($parser->getDate());

        $this->parseEmailHtml($email);

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

    private function parseEmailHtml(Email $email): void
    {
        $event = $email->add()->event();

        $event->type()->restaurant();

        // General
        $event->general()
            ->noConfirmation();

        $traveller = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Date'))}]/preceding::text()[" . $this->starts($this->t("Dear ")) . "]", null,
                true, "/^\s*" . $this->preg_implode($this->t("Dear ")) . "\s*([[:alpha:]][[:alpha:] \-]+?)[.,: ]*\s*$/u");

        if (preg_match("/^\s*(M|Mr|Mrs)\s*$/i", $traveller) || !empty($this->http->FindSingleNode("//text()[{$this->eq($this->t('Date'))}]/preceding::text()[" . $this->starts($this->t("Dear ")) . "]", null,
                true, "/^\s*" . $this->preg_implode($this->t("Dear ")) . "\s*[.,: ]*\s*$/u"))) {
        } else {
            $event->general()
                ->traveller($traveller, false);
        }

        if ($this->http->XPath->query("//text()[{$this->eq($this->t('Reservation on the waiting list'))}]")->length > 0) {
            $event->general()
                ->status('Waitlisted');
        }

        if ($this->http->XPath->query("//text()[{$this->contains($this->t('CancelledReservation'))}]")->length > 0) {
            $event->general()
                ->status('Cancelled')
                ->cancelled()
            ;
        }

        if ($this->http->XPath->query("//text()[{$this->contains($this->t('PendingReservation'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->eq($this->t('Get directions'))}]/following::text()[normalize-space()][1][{$this->eq($this->t('Date'))}]/ancestor::tr[1]"
                . "[.//node()[{$this->eq($this->t('Date'))}]][.//node()[{$this->eq($this->t('Hour'))}]][.//node()[{$this->eq($this->t('People'))}]]")->length > 0
        ) {
            $event->general()
                ->status('Pending')
            ;

            if ($this->http->XPath->query("//text()[{$this->eq($this->t('Get directions'))}]/following::text()[normalize-space()][1][{$this->eq($this->t('Date'))}]/ancestor::tr[1]"
                    . "[.//node()[{$this->eq($this->t('Date'))}]][.//node()[{$this->eq($this->t('Hour'))}]][.//node()[{$this->eq($this->t('People'))}]]")->length > 0
            ) {
                $email->removeItinerary($event);
                $email->setIsJunk(true, 'Reservation pending confirmation.');

                return;
            }
        }

        // Place
        $placeXpath = "//text()[{$this->eq($this->t('Get directions'))}]/ancestor::tr[1][count(.//text()[normalize-space()]) = 3]";

        if ($this->http->XPath->query("//node()[{$this->contains($this->t('Get directions'))}]")->length === 0) {
            $placeXpath = "//text()[{$this->eq($this->t('Date'))}]/preceding::text()[normalize-space()][1]/ancestor::tr[1][count(.//text()[normalize-space()]) = 2]";
        }
        $name = $this->http->FindSingleNode($placeXpath . "/descendant::text()[normalize-space()][1]");
        $address = $this->http->FindSingleNode($placeXpath . "/descendant::text()[normalize-space()][2]");

        $event->place()
            ->name($name)
            ->address($address);
        $phone = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("For more information, feel free to contact us on")) . "]",
            null, true, "/{$this->preg_implode($this->t("For more information, feel free to contact us on"))}\s*([-+\(\) \d]+\d+[-+\(\) \d]+)(?:\.?\s*$|\s+)/");

        if (empty($phone) && !empty($address)) {
            $phone = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Get directions'))}]/following::text()[" . $this->eq($address) . "]/following::text()[normalize-space()][1]",
                null, true, "/^\s*([-+\(\) \d]+\d+[-+\(\) \d]+)\s*$/");
        }
        $event->place()
            ->phone($phone, true, true);

        // Booked
        $event->booked()
            ->start($this->normalizeDate($this->http->FindSingleNode("//td[count(.//text()[normalize-space()]) = 2][descendant::text()[normalize-space()][1][{$this->eq($this->t("Date"))}]]/descendant::text()[normalize-space()][2]")
                . ", " . $this->http->FindSingleNode("//td[count(.//text()[normalize-space()]) = 2][descendant::text()[normalize-space()][1][{$this->eq($this->t("Hour"))}]]/descendant::text()[normalize-space()][2]")))
            ->noEnd()
            ->guests($this->http->FindSingleNode("//td[count(.//text()[normalize-space()]) = 2][descendant::text()[normalize-space()][1][{$this->eq($this->t("People"))}]]/descendant::text()[normalize-space()][2]",
                null, true, "/^\s*(\d+) \D+/u"));
        $endTime = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Your table will be booked for'))}]/ancestor::tr[1]",
            null, true, "/ \d{1,2}:\d{2}\D+ (\d{1,2}:\d{2}\d{0,5})/");

        if (!empty($endTime) && !empty($event->getStartDate())) {
            $date = strtotime($endTime, $event->getStartDate());

            if ($date < $event->getStartDate()) {
                $date = strtotime("+1 day", $date);
            }

            if ($date > $event->getStartDate()) {
                $event->booked()
                    ->end($date);
            }
        } else {
            $event->booked()
                ->noEnd();
        }
    }

    private function assignLang(): bool
    {
        foreach ($this->detectBody as $lang => $detectBody) {
            if ($this->http->XPath->query("//*[{$this->contains($detectBody)}]")->length > 0) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function t($s)
    {
        if (!isset($this->lang, self::$dictionary[$this->lang], self::$dictionary[$this->lang][$s])) {
            return $s;
        }

        return self::$dictionary[$this->lang][$s];
    }

    // additional methods
    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) {
            return 'contains(' . $text . ',"' . $s . '")';
        }, $field)) . ')';
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

    private function normalizeDate($date)
    {
        // $this->logger->debug('$date = ' . print_r($date, true));
        $year = date('Y', $this->date);

        if (empty($date) || empty($this->date)) {
            return null;
        }
        $in = [
            // Samstag 23 Dez. 2023, 12:30
            '/^\s*[[:alpha:]\-]{2,}\s*[.,\s]\s*(\d{1,2})[.]?\s+(?:de\s+)?([[:alpha:]]{3,})[.]?\s+(\d{4})\s*,\s+(\d{1,2}:\d{2}(?:\s*[ap]m)?)\s*$/iu',
            // Mittwoch, 1. November, 21:00
            // Sexta-feira, 9 de set, 13:00
            '/^\s*([[:alpha:]\-]{2,})\s*[.,\s]\s*(\d{1,2})[.]?\s+(?:de\s+)?([[:alpha:]]{3,})[.]?\s*,\s+(\d{1,2}:\d{2}(?:\s*[ap]m)?)\s*$/iu',
            // Monday, October 30, 20:30
            '/^\s*([[:alpha:]\-]{2,})\s*[.,\s]\s*([[:alpha:]]{3,})[.]?\s+(\d{1,2})\s*,\s+(\d{1,2}:\d{2}(?:\s*[ap]m)?)\s*$/iu',
        ];
        $out = [
            '$1 $2 $3, $4',
            '$1, $2 $3 ' . $year . ', $4',
            '$1, $3 $2 ' . $year . ', $4',
        ];
        $date = preg_replace($in, $out, $date);

        if (preg_match("#^\s*(?:[\w\-]+,)?\s*\d+\s+([[:alpha:]]{3,})\s+\d{4}#u", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        // $this->logger->debug('$date = ' . print_r($date, true));

        if (preg_match("#^(?<week>[[:alpha:]\-]{2,}), (?<date>\d+ [[:alpha:]]{3,} .+)#u", $date, $m)) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($m['week'], $this->lang));
            $date = EmailDateHelper::parseDateUsingWeekDay($m['date'], $weeknum);
        } elseif (preg_match("#\b\d{4}\b#", $date)) {
            $date = strtotime($date);
        } else {
            $date = null;
        }

        return $date;
    }

    private function preg_implode($field)
    {
        $field = (array) $field;

        if (empty($field)) {
            $field = ['false'];
        }

        return '(?:' . implode("|", array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
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
