<?php

namespace AwardWallet\Engine\fourchette\Email;

// TODO: delete what not use
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class Confirmation extends \TAccountChecker
{
    public $mailFiles = "fourchette/it-105599618.eml, fourchette/it-105904006.eml, fourchette/it-105995107.eml, fourchette/it-106685558.eml, fourchette/it-106685835.eml, fourchette/it-106685846.eml, fourchette/it-106802628.eml, fourchette/it-122481301.eml, fourchette/it-134165716-es.eml, fourchette/it-145463308.eml";

    private $detectFrom = "@thefork.";
    private $detectSubject = [
        // fr
        'Confirmation de votre réservation au restaurant',
        'Annulation de votre réservation au restaurant',
        'votre réservation au',
        'ne peut pas accepter votre réservation',
        'Détails de votre réservation + un bonus',
        // sv
        'Bekräftelse av din bokning på restaurang',
        // it
        'Conferma della prenotazione presso il ristorante',
        'ha cancellato la prenotazione al ristorante',
        'la tua prenotazione al ristorante',
        'Cancellazione della prenotazione presso il ristorante',
        // en
        'Confirmation of your booked table at',
        // es
        'Confirmación de tu reserva',
        'Cancelación de tu reserva en:',
        // nl
        //        '',
        // pt
        'Sua reserva no ',
        'A sua reserva no restaurante',
        //de
        'Bestätigung Deiner Tischreservierung im',
    ];
    private $detectBody = [
        'fr' => [
            'Merci pour votre réservation',
            'Nous vous confirmons l\'annulation de votre réservation',
            'Juste au cas où vous auriez oublié',
            'ne pourra malheureusement pas honorer votre réservation',
            'Malheureusement, le restaurant vient de nous informer qu’il ne pouvait pas vous recevoir faute de place.',
            'votre réservation n\'a pas encore été confirmée par le restaurant car il doit vérifier',
        ],
        'sv' => [
            'Tack för din bokning, som också ger',
        ],
        'it' => [
            'Grazie per la prenotazione. Buon appetito',
            'ha cancellato la prenotazione al ristorante',
            'ti ricordiamo la tua prenotazione per il ristorante',
            'La tua richiesta di cancellazione',
            'Grazie per la prenotazione. Hai ottenuto',
            'la tua prenotazione non è ancora confermata. Dobbiamo verificare la disponibilità del ristorante',
        ],
        'en' => [
            'Thank you for booking with us. We hope you enjoy your meal',
            'Thank you for booking with us. For this reservation from thefork',
            'We hope you\'ll enjoy your meal',
            'Thank you for booking with us. For this reservation you will earn',
            'Your booking was cancelled.',
            'Your booking has been cancelled.',
            'Just in case you have forgotten',
            'Just in case you forgot...',
            'your booking is not yet confirmed as the restaurant needs to check',
            'your booking is not confirmed yet as the restaurant has to check',
            'Unfortunately, the restaurant has just informed us that they have no availability to accept your booking',
        ],
        'es' => [
            '¡Gracias por reservar',
            'Acabas de cancelar tu reserva, por tanto los',
            '¡Ha sido una buena elección!',
        ],
        'nl' => [
            'Hartelijk dank voor je reservering.',
            'Je reservering staat genoteerd',
        ],
        'pt' => [
            'Agradecemos sua reserva. Aproveite sua refeição',
            'a sua reserva ainda não está confirmada porque o restaurante tem de verificar',
            'Desfrute da sua refeição e pondere deixar a sua opinião sobre o restaurante após a visita.',
            'Confirmamos o cancelamento da sua reserva.',
            'Sua reserva foi cancelada.',
            'Sua reserva ainda não está confirmada, precisaremos verificar a disponibilidade',
        ],
        'de' => [
            'Für diese Reservierung erhältst Du im Rahmen des Treueprogramms von TheFork',
            'Vielen Dank, dass Du mit uns reserviert hast.',
        ],
    ];

    private $lang = '';
    private static $dictionary = [
        'fr' => [ // it-106685558.eml, it-106685835.eml, it-106685846.eml
            //            'Bonjour ' => '',
            //            ', qui vous rapporte' => '',
            'Réservation confirmée'           => ['Réservation confirmée', 'Réservation annulée'],
            'cancelled text'                  => ['Réservation annulée', 'confirmons l\'annulation de votre réservation', 'ne pourra malheureusement pas honorer votre réservation'],
            'not confirmed text'              => ['votre réservation n\'a pas encore été confirmée par le restaurant car il doit vérifier'],
            'Reserva pendente de confirmação' => ['Réservation en attente de confirmation'],
            //            'personnes' => '',
            //            'Numéro de réservation :' => '',
            //            'Restaurant :' => '',
        ],
        'sv' => [ // it-105995107.eml
            'Bonjour '              => 'Hej ',
            ', qui vous rapporte'   => ', som också ger dig',
            'Réservation confirmée' => 'Bokningen har bekräftats',
            //            'cancelled text' => [''],
            //            'not confirmed text' => [''],
            //            'Reserva pendente de confirmação' => [''],
            'personnes'               => 'personer',
            'Numéro de réservation :' => 'Bokningsnummer:',
            'Restaurant :'            => 'Restaurang:',
        ],
        'it' => [ // it-105599618.eml, it-105904006.eml, it-106802628.eml
            'Bonjour '                        => 'Ciao ',
            ', qui vous rapporte'             => 'Hai ottenuto',
            'Réservation confirmée'           => ['Prenotazione confermata', 'Prenotazione cancellata'],
            'cancelled text'                  => ['ha cancellato la prenotazione al ristorante', 'Prenotazione cancellata'],
            'not confirmed text'              => ['la tua prenotazione non è ancora confermata.'],
            'Reserva pendente de confirmação' => ['Prenotazione in attesa di conferma'],
            'personnes'                       => 'persone',
            'Numéro de réservation :'         => 'Numero prenotazione:',
            'Restaurant :'                    => 'Ristorante:',
        ],
        'en' => [
            'Bonjour '                        => 'Dear ',
            ', qui vous rapporte'             => 'you will earn',
            'Réservation confirmée'           => ['Booking confirmed'],
            'cancelled text'                  => ['Your booking was cancelled.'],
            'not confirmed text'              => ['your booking is not yet confirmed as', 'your booking is not confirmed yet as'],
            'Reserva pendente de confirmação' => ['Waiting for your booking confirmation'],
            'personnes'                       => ['people', 'person'],
            'Numéro de réservation :'         => ['Booking number :', 'Booking number:'],
            'Restaurant :'                    => ['Restaurant:', 'Restaurant :'],
        ],
        'es' => [ // it-134165716-es.eml
            'Bonjour '              => 'Hola ',
            ', qui vous rapporte'   => 'Acabas de ganar',
            'Réservation confirmée' => ['Reserva confirmada', 'Reserva cancelada'],
            'cancelled text'        => ['Reserva cancelada', 'Acabas de cancelar tu reserva'],
            //            'not confirmed text' => [''],
            //            'Reserva pendente de confirmação' => [''],
            'personnes'               => 'persona',
            'Numéro de réservation :' => 'Número de reserva:',
            'Restaurant :'            => ['En el Restaurante:'],
        ],
        'nl' => [
            'Bonjour ' => 'Beste ',
            //            ', qui vous rapporte' => '',
            'Réservation confirmée' => ['Reservering bevestigd'],
            //            'cancelled text' => [''],
            //            'not confirmed text' => [''],
            //            'Reserva pendente de confirmação' => [''],
            'personnes'               => ['personen', 'persoon'],
            'Numéro de réservation :' => ['Reserveringsnummer:'],
            'Restaurant :'            => ['Restaurant:'],
        ],
        'pt' => [ // it-122481301.eml
            'Bonjour '                        => ['Prezado(a) ', 'Olá '],
            ', qui vous rapporte'             => 'Acaba de ganhar',
            'Réservation confirmée'           => ['Reserva confirmada'],
            'cancelled text'                  => ['Confirmamos o cancelamento da sua reserva'],
            'not confirmed text'              => ['a sua reserva ainda não está confirmada porque o restaurante',
                'Sua reserva ainda não está confirmada, precisaremos verificar a disponibilidade', ],
            'Reserva pendente de confirmação' => ['Reserva pendente de confirmação', 'Aguardando a confirmação da sua reserva'],
            'personnes'                       => ['pessoa/s', 'pessoas'],
            'Numéro de réservation :'         => ['Número da reserva :', 'Número da reserva:'],
            'Restaurant :'                    => ['Restaurante :', "Restaurante:"],
        ],
        'de' => [
            'Bonjour '              => ['Hallo '],
            // ', qui vous rapporte'   => '',
            'Réservation confirmée' => ['Reservierung bestätigt'],
            // 'cancelled text' => ['Confirmamos o cancelamento da sua reserva'],
            // 'not confirmed text'              => ['a sua reserva ainda não está confirmada porque o restaurante'],
            // 'Reserva pendente de confirmação' => ['Reserva pendente de confirmação'],
            'personnes'                       => ['Personen'],
            'Numéro de réservation :'         => ['Reservierungsnummer:'],
            'Restaurant :'                    => ['Restaurant:'],
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
        if ($this->http->XPath->query("//a[{$this->contains(['.lafourchette.com', 'www.thefork.co'], '@href')}]")->length === 0) {
            return false;
        }

        if ($this->assignLang()) {
            return true;
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
        if ($this->detectEmailByBody($parser) !== true) {
            $this->logger->debug("can't determine a language");

            return $email;
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        if ($this->checkIsJunk($email) === true) {
            return $email;
        }

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

    private function checkIsJunk(Email $email): bool
    {
        if (empty($this->http->FindSingleNode("(//node()[" . $this->contains($this->t("not confirmed text")) . "])[1] | img[contains(@src, '/icon-wait.png')]"))
        && empty($this->http->FindSingleNode("//text()[" . $this->starts($this->t("Numéro de réservation :")) . "]"))) {
            return false;
        }

        if (!empty($this->http->FindSingleNode("//tr[ td[1][{$this->starts($this->t("Reserva pendente de confirmação"))} and {$this->contains($this->t("personnes"))}] and td[normalize-space()][2][{$this->starts($this->t("Restaurant :"))}] ]"))) {
            // it-122481301.eml
            $email->setIsJunk(true, 'Reservation pending confirmation.');

            return true;
        }

        return false;
    }

    private function parseEmailHtml(Email $email): void
    {
        $r = $email->add()->event();

        // General
        if ($this->http->FindSingleNode("//*[@itemprop = 'reservationStatus']/@href[contains(., 'Cancelled')]") || $this->http->FindSingleNode("(//node()[" . $this->contains($this->t("cancelled text")) . "])[1]")) {
            $r->general()
                ->status('Cancelled')
                ->cancelled()
            ;
        }
        $conf = $this->http->FindSingleNode("//*[@itemprop = 'reservationNumber']/@content", null, true,
                "/^\s*(\d{5,})\s*$/u");

        if (empty($conf)) {
            $conf = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Numéro de réservation :")) . "]", null, true,
                "/:\s*(\d{5,})\s*$/u");
        }

        if (empty($conf)) {
            $conf = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Numéro de réservation :")) . "]/ancestor::tr[1]", null, true,
                "/:\s*(\d{5,})\s*$/u");
        }

        if (empty($conf) && $r->getCancelled() === true) {
            $r->general()->noConfirmation();
        } else {
            $r->general()
                ->confirmation($conf);
        }
        $traveller = $this->http->FindSingleNode("//*[@itemprop = 'underName']//*[@itemprop = 'name']/@content", null, true,
            "/^\s*([[:alpha:] \-\.]+)\s*$/u");

        if (!empty($traveller)) {
            $r->general()
                ->traveller($traveller, true);
        }

        if (empty($traveller)) {
            $r->general()
                // can include numbers: 'Dear Steve1,'
                ->traveller($traveller ?? trim($this->http->FindSingleNode("//text()[" . $this->starts($this->t("Bonjour ")) . "]", null, true,
                    "/" . $this->preg_implode($this->t("Bonjour ")) . "\s*([\w \-]+)\s*[,!]\s*$/u")), false)
            ;
        }

        // Place
        $address = implode(", ", $this->http->FindNodes("//*[@itemprop = 'reservationFor']//*[@itemprop = 'address']//*/@content"));

        if (empty($address)) {
            $address = implode(", ", $this->http->FindNodes("//tr[" . $this->eq($this->t("Restaurant :")) . "]/following::tr[not(.//tr) and not(.//img)][1]/descendant::text()[normalize-space()][position() > 1]"));
        }
        $r->place()
            ->name($this->http->FindSingleNode("//*[@itemprop = 'reservationFor']//*[@itemprop = 'name']/@content")
                ?? $this->http->FindSingleNode("//tr[" . $this->eq($this->t("Restaurant :")) . "]/following::tr[not(.//tr) and not(.//img)][1]/descendant::text()[normalize-space()][1]"))
            ->address($address)
            ->phone($this->http->FindSingleNode("//tr[" . $this->eq($this->t("Restaurant :")) . "]/following::tr[not(.//tr)][2][.//img[contains(@src, 'icon-phone')]]",
                null, true, "/^[-+\(\) \d]+\d+[-+\(\) \d]+$/"), true, true)
            ->type(EVENT_RESTAURANT);

        // Booked
        $r->booked()
            ->start($this->normalizeDate($this->http->FindSingleNode("//*[@itemprop = 'startTime']/@content")
                ?? $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Réservation confirmée")) . "]/following::text()[string-length(normalize-space())>1][1]")))
            ->noEnd()
            ->guests($this->http->FindSingleNode("//*[@itemprop = 'partySize']/@content")
                ?? $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Réservation confirmée")) . "]/following::text()[string-length(normalize-space())>1][{$this->contains($this->t('personnes'))}]",
                null, true, "/^\s*(\d+) " . $this->preg_implode($this->t("personnes")) . "/u"))
        ;

        // Program
        $yums = $this->http->FindSingleNode("//text()[" . $this->contains($this->t(", qui vous rapporte")) . "]/following::text()[normalize-space()][1]", null, true,
            "/^\s*\d+\s*(?:Yums|loyalty points)\s*$/u");

        if (empty($yums)) {
            $yums = $this->http->FindSingleNode("//text()[" . $this->contains($this->t(", qui vous rapporte")) . "]",
                null, true,
                "/" . $this->preg_implode($this->t(", qui vous rapporte")) . "\s+(\d+\s*(?:Yums|loyalty points))\b/iu");
        }

        if (!empty($yums)) {
            $r->program()
                ->earnedAwards($yums);
        }
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $dict) {
            if (!is_string($lang) || empty($dict['Réservation confirmée']) || empty($dict['Restaurant :'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[{$this->contains($dict['Réservation confirmée'])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->contains($dict['Restaurant :'])}]")->length > 0
            ) {
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
    private function contains($field, $text = 'normalize-space(.)'): string
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) {
            return 'contains(' . $text . ',"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field): string
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field): string
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function normalizeDate(?string $date): ?int
    {
//        $this->logger->debug('date begin = ' . print_r( $date, true));
        if (empty($date)) {
            return null;
        }

        $in = [
            // Le mardi 27 juillet 2021 à 20:00
            // Den lördag 10 juli 2021 kl. 19:30
            // Saturday, 21 August 2021 at 19:30
            // terça-feira, 4 de fevereiro de 2020 às 20:30
            // miércoles, 19 de enero de 2022 a las 20:00
            '/^\s*[- [:alpha:]]+[,\s]+(\d{1,2})(?:\s+de)?\s+([[:alpha:]]+)\s+(?:de\s+)?(\d{4})\s+[[:alpha:] .]+\s+(\d{1,2}:\d{2}(?:\s*[ap]m)?)\s*$/ui',
            // Sunday, September 5, 2021 at 8:00 PM
            '/^\s*[ [:alpha:]]+,\s*([[:alpha:]]+)\s+(\d{1,2}),\s*(\d{4})\s+[[:alpha:]]+\s+(\d{1,2}:\d{2}(\s*[ap]m)?)\s*$/ui',
            //2021-07-31T20:00:00+01:00
            '/^\s*([\d\-]+T[\d:]+)[\-+][\d:]+\s*$/ui',
        ];
        $out = [
            '$1 $2 $3, $4',
            '$2 $1 $3, $4',
            '$1',
        ];

        $date = preg_replace($in, $out, $date);
        $date = $this->dateTranslate($date);
//        $this->logger->debug('date end = ' . print_r( $date, true));

        return strtotime($date);
    }

    private function dateTranslate($date)
    {
        if (preg_match('/[[:alpha:]]+/iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("/$monthNameOriginal/i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function preg_implode($field): string
    {
        $field = (array) $field;

        if (empty($field)) {
            $field = ['false'];
        }

        return '(?:' . implode("|", array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }
}
