<?php

namespace AwardWallet\Engine\fourchette\Email;

// TODO: delete what not use
use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class Confirmation2023 extends \TAccountChecker
{
    public $mailFiles = "fourchette/it-559002913.eml, fourchette/it-570973792.eml, fourchette/it-571038815.eml";

    private $detectFrom = "info@email.thefork.";
    private $detectSubject = [
        // en
        'Your reservation confirmation for',
        'Your booking confirmation for',
        'Reminder: You have a booking tomorrow at',
        // fr
        'Votre confirmation de réservation auprès de',
        // it
        'Conferma della prenotazione per',
        // es
        'Tu confirmación de reserva en',
        // de
        'Deine Reservierungsbestätigung für',
        // pt
        'Confirmação da tua reserva no',
    ];
    private $detectBody = [
        'en' => [
            ', your table is confirmed',
            ', get ready for your booking tomorrow',
            ', get ready for your reservation tomorrow',
        ],
        'fr' => [
            ', votre table est confirmée',
        ],
        'it' => [
            ', il tuo tavolo è confermato',
            ', preparati per la prenotazione di domani',
        ],
        'es' => [
            ', tu mesa está confirmada',
        ],
        'de' => [
            ', dein Tisch ist bestätigt.',
        ],
        'pt' => [
            ', a tua reserva está confirmada',
        ],
    ];

    private $date;
    private $lang = '';
    private static $dictionary = [
        'en' => [
            // 'Hello ' => '',
            // "Your table will be booked for" => '', // Your table will be booked for 4h, between 20:30 and 00:30
            "You'll earn" => ['You\'ll earn', 'Don’t forget, you’ll receive'],
            // 'Cancel your reservation or contact the restaurant (' => '',
            'Reservation number:' => ['Booking number:', 'Reservation number:'],
        ],
        'fr' => [
            'Hello '                                              => 'Bonjour ',
            "Your table will be booked for"                       => 'Votre table sera réservée pour', // Votre table sera réservée pour 1h, entre 13:00 et 14:00
            "You'll earn"                                         => 'Après le repas, vous gagnerez',
            'Cancel your reservation or contact the restaurant (' => 'Si vous êtes en retard, annulez votre réservation ou contactez le restaurant (',
            'Reservation number:'                                 => 'Numéro de réservation :',
        ],
        'it' => [
            'Hello '                                              => 'Ciao ',
            "Your table will be booked for"                       => 'Il tavolo sarà prenotato per', // Il tavolo sarà prenotato per 1h30min, tra le ore 21:30 e le ore 23:00
            "You'll earn"                                         => ['Dopo il pasto guadagnerai', 'Non dimenticare che riceverai'],
            'Cancel your reservation or contact the restaurant (' => 'Cancella la prenotazione oppure contatta il ristorante (',
            'Reservation number:'                                 => 'Numero di prenotazione:',
        ],
        'es' => [
            'Hello '                                              => 'Hola ',
            "Your table will be booked for"                       => 'Tu mesa se reservará durante', // Tu mesa se reservará durante 1h30min, entre las 14:00 y las 15:30
            "You'll earn"                                         => 'Ganarás',
            'Cancel your reservation or contact the restaurant (' => 'Cancela tu reserva o ponte en contacto con el restaurante (',
            'Reservation number:'                                 => 'Número de reserva:',
        ],
        'de' => [
            'Hello '                                              => 'Hallo ',
            "Your table will be booked for"                       => 'Der Tisch ist für', // Your table will be booked for 4h, between 20:30 and 00:30
            "You'll earn"                                         => 'Nach dem Essen erhältst du',
            'Cancel your reservation or contact the restaurant (' => 'Storniere die Reservierung oder kontaktiere das Restaurant (',
            'Reservation number:'                                 => 'Reservierungsnummer:',
        ],
        'pt' => [
            'Hello '                                              => 'Olá ',
            "Your table will be booked for"                       => 'A sua mesa ficará reservada durante', // Your table will be booked for 4h, between 20:30 and 00:30
            "You'll earn"                                         => 'Vais ganhar',
            'Cancel your reservation or contact the restaurant (' => 'Cancela a tua reserva ou contacta o restaurante (',
            'Reservation number:'                                 => 'Número da reserva:',
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
        if ($this->http->XPath->query("//a[{$this->contains(['.lafourchette.com', '.thefork.'], '@href')}]")->length === 0) {
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

        $jsonText = $this->http->FindSingleNode("//script[contains(., 'schema.org')]");
        $json = json_decode($jsonText, true);

        if (!empty($json['reservationNumber'])) {
            // $this->logger->debug('$json = '.print_r( $json,true));

            // General
            $event->general()
                ->confirmation($json['reservationNumber'])
                ->traveller($json['underName']['name'], true)
                ->date(strtotime($this->re("/^(.+?)[\-+]\d{1,2}:\d{2}\s*$/", $json['bookingTime'])))
            ;

            // Place
            $event->place()
                ->name($json['reservationFor']['name'])
                ->address(implode(',', array_diff_key($json['reservationFor']['address'], ['@type' => 1])))
            ;

            // Booking
            $event->booked()
                ->start(strtotime($this->re("/^(.+?)[\-+]\d{1,2}:\d{2}\s*$/", $json['startTime'])))
                ->guests($json['partySize'])
            ;

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
        } else {
            // General
            $conf = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Reservation number:")) . "]/ancestor::tr[1]",
                null, true,
                "/:\s*(\d{5,})\s*$/u");

            $event->general()
                ->confirmation($conf);

            $event->general()
                ->traveller($this->http->FindSingleNode("//text()[" . $this->starts($this->t("Hello ")) . "]", null,
                    true,
                    "/^\s*" . $this->preg_implode($this->t("Hello ")) . "\s*([\w \-]+)[.]?\s*, /u"), false);

            // Place
            $name = $this->http->FindSingleNode("//img[contains(@src, 'images/calendar')]/preceding::text()[normalize-space()][1]/ancestor::tr[1]");
            $address = $this->http->FindSingleNode("//img[contains(@src, 'images/pin')]/ancestor::tr[1]");
            $event->place()
                ->name($name)
                ->address($address);

            // Booked

            $event->booked()
                ->start($this->normalizeDate($this->http->FindSingleNode("//img[contains(@src, 'images/calendar')]/ancestor::tr[1]")
                    . ", " . $this->http->FindSingleNode("//img[contains(@src, 'images/clock')]/ancestor::tr[1]")))
                ->guests($this->http->FindSingleNode("//img[contains(@src, 'images/group')]/ancestor::tr[1]",
                    null, true, "/^\s*(\d+) \D+/u"));
        }

        $event->place()
            ->phone($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Cancel your reservation or contact the restaurant (")) . "]/ancestor::tr[1]",
                null, true, "/{$this->preg_implode($this->t("Cancel your reservation or contact the restaurant ("))}\s*([-+\(\) \d]+\d+[-+\(\) \d]+)\)/"), true, true);

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

        // Program
        $yums = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("You'll earn")) . "]/following::text()[normalize-space()][1]", null, true,
            "/^\s*\d+\s*(?:Yums|loyalty points|punti fedeltà Yums)\s*$/u");

        if (!empty($yums)) {
            $event->program()
                ->earnedAwards($yums);
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
            // Mittwoch, 1. November, 21:00
            '/^\s*([[:alpha:]\-]{2,})\s*[.,\s]\s*(\d{1,2})[.]?\s+(?:de\s+)?([[:alpha:]]{3,})[.]?\s*,\s+(\d{1,2}:\d{2}(?: ?[ap]m)?)\s*$/iu',
            // Monday, October 30, 20:30
            '/^\s*([[:alpha:]\-]{2,})\s*[.,\s]\s*([[:alpha:]]{3,})[.]?\s+(\d{1,2})\s*,\s+(\d{1,2}:\d{2}(?: ?[ap]m)?)\s*$/iu',
        ];
        $out = [
            '$1, $2 $3 ' . $year . ', $4',
            '$1, $3 $2 ' . $year . ', $4',
        ];
        $date = preg_replace($in, $out, $date);

        if (preg_match("#\w+,\s*\d+\s+([[:alpha:]]{3,})\s+\d{4}#u", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        // $this->logger->debug('$date = ' . print_r($date, true));

        if (preg_match("#^(?<week>[[:alpha:]]{2,}), (?<date>\d+ [[:alpha:]]{3,} .+)#u", $date, $m)) {
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
