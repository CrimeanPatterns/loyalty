<?php

namespace AwardWallet\Engine\carlson\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class ConfirmationPdf extends \TAccountChecker
{
    public $mailFiles = "carlson/it-143056726.eml, carlson/it-39243219.eml, carlson/it-409225408.eml, carlson/it-53503421.eml, carlson/it-6205785.eml, carlson/it-6205786.eml, carlson/it-6205787.eml, carlson/it-67241789.eml"; // +2 bcdtravel(pdf)[de] +1 bcdtravel(pdf)[en]

    public $reFrom = "@radissonblu.com";
    public $reSubject = [
        "de"  => "Reservierungsbestätigung",
        "en"  => "Confirmation", "visit to",
    ];
    public $langDetectorsPdf = [
        "fr" => ["Date d'Arrivée :", "N° de Confirmation :"],
        "sv" => ["Avresedatum:", "BOKNINGSBEKRÄFTELSE"],
        "de" => ["Anreise:", "RESERVIERUNGSBESTÄTIGUNG"],
        "en" => ["Arrival Date:", "RESERVATION CONFIRMATION", 'Thank you for choosing'],
        "fi" => ["Lähtöpäivä", "Varausnumero"],
    ];

    public static $dictionary = [
        "fr" => [
            "Reservation Number"                                    => "N° de Confirmation",
            "hotelNameStart"                                        => "Merci de choisir le",
            // "hotelNameEnd" => "",
            "Arrival Date"                                          => "Date d'Arrivée",
            "Check-in time is guaranteed from (\d+\s+[ap]m|12noon)" => "L'arrivée peut se faire à partir de (\d+h\d+)",
            "Departure Date"                                        => "Date de Départ",
            "check-out is (\d+\s+[ap]m|12noon)"                     => "et le départ au plus tard à (\d+h\d+)",
            "Tel:"                                                  => "Tel. :",
            "Fax:"                                                  => "Fax.: ",
            "Guest Name"                                            => "Nom",
            "Number of Adults/Children"                             => "Nombre d'Adulte(s) et d'Enfant(s) :",
            "Number and type of room"                               => "Nombre et Type de Chambre",
            //"Rate"                                                  => "",
            "Total rate:"                                           => "Prix Confirmé",
            //			"Number of rooms" => "",
        ],
        "fi" => [
            "Reservation Number"                                    => "Varausnumero",
            "hotelNameStart"                                        => "Kiitos kun valitsitte",
            "hotelNameEnd"                                          => "hotellin.",
            "Arrival Date"                                          => "Saapumispäivä",
            "Check-in time is guaranteed from (\d+\s+[ap]m|12noon)" => "Hotellihuone on käytettävissänne tulopäivänä kello\s*([\d\.]+)",
            "Departure Date"                                        => "Lähtöpäivä",
            "check-out is (\d+\s+[ap]m|12noon)"                     => "alkaen lähtöpäivään kello\s*([\d\.]+)",
            "Tel:"                                                  => "Puhelin:",
            "Fax:"                                                  => ["Fax.:", "Fax:"],
            "Guest Name"                                            => "Nimi",
            "Number of Adults/Children"                             => "Henkilömäärä",
            "Number and type of room"                               => "Huoneita / huonetyyppi",
            //"Rate"                                                  => "",
            "Total rate:"                                           => "Hinta yhteensä",
            //			"Number of rooms" => "",
        ],
        "sv" => [
            "Reservation Number"                                    => "Reservationsnummer",
            "hotelNameStart"                                        => "Tack för att du har valt",
            // "hotelNameEnd" => "",
            "Arrival Date"                                          => "Ankomstdatum",
            "Check-in time is guaranteed from (\d+\s+[ap]m|12noon)" => "från klockan (\d+:\d+)",
            "Departure Date"                                        => "Avresedatum",
            "check-out is (\d+\s+[ap]m|12noon)"                     => "utcheckning (?:senast|är) klockan (\d+:\d+)",
            "Tel:"                                                  => "Tel:",
            "Fax:"                                                  => "Fax:",
            "Guest Name"                                            => "Gästnamn",
            "Number of Adults/Children"                             => "Antal vuxna/barn",
            "Number and type of room"                               => "Rumstyp",
            "Rate"                                                  => "Pris",
            "Total rate:"                                           => "Totalpris",
            //			"Number of rooms" => "",
        ],
        "de" => [
            "Reservation Number"                                    => ["Reservierungsnummer", "Bestätigungsnummer"],
            "hotelNameStart"                                        => ["Dank für Ihre Buchung im", "danken Ihnen für das Interesse am"],
            // "hotelNameEnd" => "",
            "Arrival Date"                                          => "Anreise",
            "Check-in time is guaranteed from (\d+\s+[ap]m|12noon)" => "Das Zimmer steht für Sie am Anreisetag ab\s*(\d{1,2}:\d{2})\s*Uhr zur Verfügung",
            "Departure Date"                                        => "Abreise",
            "check-out is (\d+\s+[ap]m|12noon)"                     => "Abreise bis\s*(\d{1,2}:\d{2})\s*Uhr genutzt werden.",
            "Tel:"                                                  => "T:",
            "Fax:"                                                  => "F:",
            "Guest Name"                                            => ["Gast Name", "Gastname"],
            "Number of Adults/Children"                             => "Personen",
            "Number and type of room"                               => ["Zimmer", "Zimmerkategorie"],
            "Rate"                                                  => ["Preis pro Zimmer pro Nacht", "Zimmerpreis pro Tag"],
            "Total rate:"                                           => "Totalpris:",
            //			"Number of rooms" => "",
        ],
        "en" => [
            "Reservation Number"     => ["Reservation Number", "Confirmation No.", "Booking Reference", 'Reservation number', 'Confirmation #:'],
            "hotelNameStart"         => [
                "Thank you for choosing to stay at",
                "Thank you for choosing",
                "thank you very much for your kind interest to",
                "Thank you for your booking at",
                "confirm the following reservation at",
                "Welcome to",
                "Greetings from the",
                "Thank you for choosing the",
            ],
            "hotelNameEnd" => [
                "as your preferred choice of accommodation",
                "where the finest personal service",
            ],
            "Check-in time is guaranteed from (\d+\s+[ap]m|12noon)" => "(?:Check-in time is guaranteed from|Check in Time:|Your room will be available from|we guarantee access to the room after)[ ]*(\d+(?::\d+)?\s*[ap]m|12noon)",
            "check-out is (\d+\s+[ap]m|12noon)"                     => "(?:(?i)check-out is|Check out Time:|and check out time is|check-out time is)[ ]*(\d+(?::\d+)?\s*(?:[ap]m|noon|M)|12noon)",
            "Guest Name"                                            => ["Guest Name", "Guest Name(s)", "Guest Name (s):"],
            "Number and type of room"                               => ["Number and type of room", "Room Type", "Number & Type of Room", "Number of Rooms / Type", "Rooms Reserved"],
            "Rate"                                                  => ["Rate", "Room Rate per night", 'Daily Rate', 'Room Rate', "Nightly Room Rate", "Room rate", "Total cost per night"],
            "Number of Adults/Children"                             => [
                "Number of Adults / Children",
                "Number of Adults/Children",
                "Number of Adults",
                'Number of Adult/Child',
                'Number of Person',
                'Number of Persons',
                'Number of adults',
                'Adult / Children',
                'No. Adults/Children',
                'No. of Guests',
                'Number of adult(s)/child(ren):',
                'Adult:',
            ],
            "Tel:"              => ["Tel:", "T:", 'Tel :', 'Phone:', 'Telephone:', 't:', 'D ', 'P:'],
            "Fax:"              => ["Fax:", "F:", 'Fax :', 'f:', 'M ', 'M:'],
            'Arrival Date'      => ['Arrival Date', 'Arrival date', 'Arrival'],
            'Departure Date'    => ['Departure Date', 'Departure date', 'Departure'],
            "Total rate:"       => ["Total rate:", "Total Room Cost", "Total Rate:", "Total Rate"],
            "Confirmation Date" => ["Confirmation Date", "Date"],
        ],
    ];

    public $lang = "";

    private $code;
    private static $providers = [
        'carlson' => [
            'from' => ['@radissonblu.com'],
            'subj' => [
                'en' => 'Confirmation',
                'de' => 'Reservierungsbestätigung',
            ],
            'body' => [
                'radisson',
                'parkinn',
            ],
        ],
        'hhonors' => [
            'from' => ['@hilton.com'],
            'subj' => [
                'en' => 'Confirmation',
                'de' => 'Reservierungsbestätigung',
            ],
            'body' => [
                'hilton',
            ],
        ],
        'ichotelsgroup' => [
            'from' => ['ihg.com', "crowneplaza"],
            'subj' => [
                'en' => 'visit to',
            ],
            'body' => [
                'crowneplaza',
            ],
        ],
        'marriott' => [
            'from' => [],
            'subj' => [],
            'body' => [
                'ritzcarlton',
            ],
        ],
    ];
    private $text;

    public function detectEmailFromProvider($from)
    {
        if (!empty(self::$providers['carlson'])) {
            foreach (self::$providers['carlson']['from'] as $f) {
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

        foreach (self::$providers as $code => $arr) {
            $byFrom = $bySubj = false;

            foreach ($arr['from'] as $f) {
                if (stripos($headers['from'], $f) !== false) {
                    $byFrom = true;
                }
            }

            foreach ($arr['subj'] as $subj) {
                if (stripos($headers['subject'], $subj) !== false) {
                    $bySubj = true;
                }
            }

            if ($byFrom && $bySubj) {
                $this->code = $code;

                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        if (!isset($pdfs[0])) {
            return false;
        }
        $pdf = $pdfs[0];

        if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
            return false;
        }

        if (null === $this->getProvider($parser, $text)) {
            return false;
        }

        return $this->assignLangPdf($text);
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->http->FilterHTML = false;

        $pdfs = $parser->searchAttachmentByName('.*pdf');

        if (!isset($pdfs[0])) {
            return false;
        }

        foreach ($pdfs as $pdf) {
            if (($textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf))) !== null) {
                // $this->text = preg_replace('/(\b[[:upper:]][\'’]*[[:alpha:]]+-) ([[:alpha:]]+[\'’]*[[:alpha:]]+\b)/', '$1$2', $textPdf);
                $this->text = str_replace('Ritz- Carlton', 'Ritz-Carlton', $textPdf); // hard-code
                $this->assignLangPdf($this->text);
                $this->parsePdf($email);
                $code = $this->getProvider($parser, $this->text);
            }
        }
        $email->setType('ConfirmationPdf' . ucfirst($this->lang));

        if (isset($code)) {
            $email->setProviderCode($code);
        }

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

    private function parsePdf(Email $email): void
    {
        $patterns = [
            'time' => '\d{1,2}(?:[:：]\d{2})?(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?|[ ]*noon)?', // 4:19PM    |    2:00 p. m.    |    3pm    |    12 noon
        ];

        $text = $this->text;

        $r = $email->add()->hotel();

        // ConfirmationNumber
        $confirmationNumber = $this->re("/{$this->opt($this->t("Reservation Number"))}[\s\:]*(\d{6,}(?: {0,2}\\/ {0,2}\d{6,})*?)(?:[ ]{2}|$)/m");

        if (empty($confirmationNumber) && ($conf = $this->re('/Confirmation Numbers?[\s\:]*(\d{6,})$/m'))) {
            $confirmationNumber = $conf;
        }
        $confs = array_unique(array_filter(preg_split("/\s*\\/\s*/", $confirmationNumber)));

        foreach ($confs as $conf) {
            $r->general()->confirmation($conf);
        }

        if ($revDate = $this->re("#{$this->opt($this->t("Confirmation Date"))}[\s\:]*(\S.+?)(?: {3,}|\n)#")) {
            $r->general()->date(strtotime($this->normalizeDate($revDate)));
        }

        // CheckInDate
        // CheckOutDate
        $dateCheckIn = strtotime($this->normalizeDate($this->re("/{$this->opt($this->t("Arrival Date"))}[\s\:]*(.+?)(?:[ ]{2,}|\n)/")));
        $dateCheckOut = strtotime($this->normalizeDate($this->re("/{$this->opt($this->t("Departure Date"))}[\s\:]*(.+?)(?:[ ]{2,}|\n)/")));

        $timeCheckIn = $timeCheckOut = $this->re('/Our Hotel Check in\/Checkout Time[\s\:]*(\d{1,2}:\d{2})[ ]*noon/i');

        if (preg_match("/Hotel check-in time is from\s+(?<in>{$patterns['time']})\s+and check-out time is by\s+(?<out>{$patterns['time']})/i", $this->text, $times)
            || preg_match("/Check-in time is\s+(?<in>{$patterns['time']})\s+and check-out time is\s+(?<out>{$patterns['time']})/i", $this->text, $times)
        ) {
            // Hotel check-in time is from 15:00 and check-out time is by 12:00
            $timeCheckIn = $times['in'];
            $timeCheckOut = $times['out'];
        }

        if (!$timeCheckIn) {
            $timeCheckIn = $this->re("#" . $this->t("Check-in time is guaranteed from (\d+\s+[ap]m|12noon)") . "#iu");
        }

        if (!$timeCheckOut) {
            $timeCheckOut = str_replace("\n", "", $this->re("#" . $this->t("check-out is (\d+\s+[ap]m|12noon)") . "#ui"));
        }

        if ($timeCheckIn) {
            $dateCheckIn = strtotime($this->normalizeTime($timeCheckIn), $dateCheckIn);
        }

        if ($timeCheckOut) {
            $dateCheckOut = strtotime($this->normalizeTime($timeCheckOut), $dateCheckOut);
        }

        $r->booked()->checkIn($dateCheckIn)->checkOut($dateCheckOut);

        // Hotel Name
        // Address
        // Phone
        // Fax
        $hotelName = preg_replace("/\s+/", ' ',
            $this->re("/{$this->opt($this->t("hotelNameStart"))}\s+(.*?(?:\n.*?)?)[ ]*{$this->opt($this->t("hotelNameEnd"))}/u")
            ?? $this->re("/{$this->opt($this->t("hotelNameStart"))}\s+(.*?(?:\n.*?)?)[.:!,]/u")
        );

        if (empty($hotelName)) {
            $hotelName = $this->re("/{$this->opt($this->t('Thank you for making a reservation at'))}\s*(.+?)\s*\,/u", $text);
        }

        if (empty($hotelName)) {
            $hotelName = $this->re("/stay with us at\s*(.+)\s+as follows\:/", $text);
        }

        if (!empty($hotelName)) {
            $hotelName = trim(preg_replace('/^\s*The\s+/i', '', $hotelName));
            $firstWord = preg_split('/\s+/', preg_split('/\s+in\s+/', trim($hotelName))[0])[0];

            $textAddr = $this->re("/{$this->opt($this->t('Reservations Department'))}\s+{$firstWord}[^\n]*?[ ]+-[ ]+(.+)/s")
                ?? $this->re("/{$this->opt($this->t('Reservations Department'))}\s+({$firstWord}[^\n]*?.+\n.*)/s")
            ;

            if (!empty($textAddr) && (preg_match("#(.+[ ]{3,})(?:Toll-free reservation|Bank:|Account no:)#", $textAddr,
                    $m))
            ) {
                /* del garbage. FE:
Radisson Blu Polar Hotel Spitsbergen
                                                                                  Toll-free reservation 800 160091
  P.O. Box 548
  N-9171 Longyearbyen
                                                                                  Bank: DNB NOR ASA
  Tel: +47 79 02 34 50                                                            Account no: : 5081 08 65242
  Fax: +47 79 02 34 51                                                            IBAN: NO83 5081 08 65242
  reception.longyearbyen@radissonblu.com                                          SWIFT: DNBANOKK
                                                                                  Org.no: NO 951 291 579
                                                                                  Hurtigruten Svalbard AS
  www.radissonblu.com/hotel-spitsbergen
                 * */
                $rows = explode("\n", $textAddr);
                $len = mb_strlen($m[1]);

                foreach ($rows as &$row) {
                    $row = substr($row, 0, $len);
                }
                $textAddr = implode("\n", $rows);
            }

            if (empty($textAddr)) {
                $textAddr = $this->re("#^[ ]*{$firstWord}[^.\n]*\n+([ ]*.*?)\s*\n+[ ]*{$this->opt($this->t("Tel:"))}#ms");

                if (preg_match("/\n.{30} {5,}{$this->opt($this->t("Tel:"))}/", $this->text)) {
                    $textAddr = preg_replace('/^ {0,10}(\S ?)+/m', ' ', $textAddr);
                    $textAddr = preg_replace('/^.{30,} {5,}/m', ' ', $textAddr);
                }
                $textAddr = preg_replace(['/\s+/', '/CONFIRMATION/i'], [' ', ''], $textAddr);

                if ($textAddr) {
                    $flagBeforeTel = true;
                }
            }

            if (empty($textAddr)) {
                /*
                       Park Inn by Radisson Antwerp Berchem Borsbeeksebrug 34, 2600 Berchem, Antwerp, Belgium
                                      t: 32 3 432 77 00 f: 32 3 432 77 02 info.berchem@parkinn.com
                 */
                $textAddr = $this->re("#^[ ]*{$hotelName}\s+(.+(?:\n *\S.+)?)\s*\n+[ ]*{$this->opt($this->t("Tel:"))}#ms");

                if ($textAddr) {
                    $flagBeforeTel = true;
                }
            }

            if (empty($textAddr)) {
                $textAddr = $this->text;
            }

            $textAddr = preg_replace("/.*(cancel|refund|\bCheck\b).*/i", '', $textAddr);

            $address = preg_replace('/[ ]*\|/', ',',
                trim($this->re("/[ ]*{$firstWord}[^.\n]*\n+[ ]*{$this->opt($this->t('Tel:'))}.+{$this->opt($this->t('Fax:'))}.+?\n(.*?)\s*Email/msiu",
                    $textAddr)));

            if (!empty($hotelName) && empty($address)
                && (preg_match("#{$this->opt($hotelName)}\s*(?<address>(?:.+\n){1,2})\n*D\s*(?<phone>[+][\d\s\.]+)\n*M\s*(?<fax>[+][\d\s\.]+)#u", $text, $m)
                    || preg_match("#{$this->opt($hotelName)}\n*\s*(?<address>(?:.+\n){1,2})\n*{$this->opt($this->t('Tel:'))}\s*(?<phone>[+][\d\s\.\(\)]+)\n*{$this->opt($this->t('Fax:'))}\s*(?<fax>[+][\d\s\.\(\)]+)#u", $text, $m))
            ) {
                $address = preg_replace("/\s+/", ' ', $m['address']);
                $phone = $m['phone'];
                $fax = $m['fax'];
            }

            if (empty($address)) {
                if ((preg_match("/(.+)\s+{$firstWord}[^\n]*\s*{$this->opt($this->t("Tel:"))}[ ]*([+)(\d][-. \d)(]{5,}[\d)(])\s*[\|]*[ ]*{$this->opt($this->t("Fax:"))}[ ]*([+)(\d][-. \d)(]{5,}[\d)(])/", $textAddr, $m)
                    || preg_match("/{$firstWord}[^\n]*\s*(.{1,150}?)\s+{$this->opt($this->t("Tel:"))}[ ]*([+)(\d][-. \d)(]{5,}[\d)(])\s*[\|]*[ ]*{$this->opt($this->t("Fax:"))}[ ]*([+)(\d][-. \d)(]{5,}[\d)(])/s", $textAddr, $m))
                    && mb_strlen($m[1]) > 2 && preg_match("/\d/", $m[1]) && preg_match("/[[:alpha:]]/u", $m[1])
                ) {
                    $address = preg_replace("/\s+/", ' ', $m[1]);
                    $phone = trim($m[2]);
                    $fax = trim($m[3]);
                }
            }

            if (empty($address) && isset($flagBeforeTel)) {
                $address = $textAddr;
            }

            if (!isset($phone, $fax)) {
                $phone = $this->re("#{$this->opt($this->t("Tel:"))}[ ]*([+)(\d][-. \d)(]{5,}[\d)(])\s*[\|]*[ ]*{$this->opt($this->t("Fax:"))}#",
                    $textAddr);
                $fax = $this->re("#{$this->opt($this->t("Fax:"))}[ ]*([+)(\d][-. \d)(]{5,}[\d)(])#", $textAddr);
            }
        }

        if (!empty($hotelName) && empty($address) && isset($firstWord)
            && preg_match("#^\s*{$firstWord}[^\n]+((\n[^\n]+){1,4}?)\n[ ]*{$this->opt($this->t("Tel:"))}#", $text,
                $m)) {// address on top pdf
            $address = preg_replace("#\n[ ]{3,}#", ' ', $m[1]);

            if (preg_match("#\s{2,}#", $address)) {
                $this->logger->notice('clear address. check format');
                $address = '';
            }
        }

        if (empty($hotelName) && empty($address)
            && preg_match("#\n{3,}[ ]{10,}(?<name>.*Radisson.*)\n(?<addr>(?:.+\n){1,3})[ ]+T:[\d\+\- ]{5,}#", $text,
                $m)) {
            $hotelName = $m['name'];
            $address = preg_replace("#\s*\n\s*#", ', ', trim($m['addr']));
        }

        if (!empty($hotelName) && empty($address) && preg_match("#" . $hotelName . "(?:.+A\.(.+?))$#s", $text, $m)) {
            $address = $m[1];
        }

        if (!empty($hotelName) && empty($address) && preg_match("#Page Layout mode.(.+?)Tel:#s", $text, $m)) {
            $address = preg_replace("#\s*\n\s*#", ', ', trim($m[1]));
        }

        if (!empty($hotelName) && !empty($address)) {
            $r->hotel()
                ->name($hotelName)
                ->address($address);
        }

        if (!isset($phone, $fax)) {
            $phone = $this->re("#{$this->opt($this->t("Tel:"))}[ ]*([+)(\d][-. \d)(]{5,}[\d)(])\s*[\|]*[ ]*{$this->opt($this->t("Fax:"))}#");
            $fax = $this->re("#{$this->opt($this->t("Fax:"))}[ ]*([+)(\d][-. \d)(]{5,}[\d)(])#");
        }

        if (!isset($phone, $fax)) {
            $phone = $this->re("#\s+{$this->opt($this->t("Tel:"))}[ ]*([+)(\d][-. \d)(]{5,}[\d)(])\n.*[ ]+{$this->opt($this->t("Fax:"))}#");
            $fax = $this->re("#{$this->opt($this->t("Fax:"))}[ ]*([+)(\d][-. \d)(]{5,}[\d)(])#");
        }

        if (!isset($phone, $fax) && $hotelName) {
            if (preg_match("#" . $hotelName . "(?:.+T\.(.+?))\s[A-Z]\.#s", $text, $m)) {
                $phone = $m[1];
            }

            if (preg_match("#" . $hotelName . "(?:.+F\.(.+?))\s[A-Z]\.#s", $text, $m)) {
                $fax = $m[1];
            }
        }

        if (!isset($phone, $fax)) {
            if (preg_match("#T\:\s+([\d\+\s])+\n\s+E\:#", $text, $m)) {
                $phone = $m[1];
            }
        }

        if (!empty($phone)) {
            $r->hotel()
                ->phone($phone);
        }

        if (!empty($fax)) {
            $r->hotel()
                ->fax($fax);
        }

        $guestName = $this->re("/{$this->opt($this->t("Guest Name"))}[\s\:]*([[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])$/imu");

        if (empty($guestName)) {
            $guestName = $this->re("/(?:Dear)\s*(\w+)\,/u", $text);
        }

        if ($guestName) {
            $guestName = preg_replace('/^(?:Mr|Ms)[.\s]+(.{2,})$/', '$1', $guestName);
        }

        if (!empty($guestName)) {
            $r->general()->traveller($guestName, true);
        }

        // Guests
        // Kids
        $guestsRow = $this->re("#{$this->opt($this->t("Number of Adults/Children"))}[\s\:]*(.+)#");

        if (preg_match("/^(\d{1,3})[ ]*\/[ ]*(\d{1,3})\b/", $guestsRow, $m)) {
            $guests = $m[1];
            $kids = $m[2];
        } elseif (preg_match("/\b(\d{1,3})[ ]*Erwachsenen/i", $guestsRow, $m)) { // de
            $guests = $m[1];
        } elseif (preg_match('/Adults[ ]*\:[ ]*(\d{1,2})[ ]*Child[ ]*:[ ]*(\d{1,2})/', $guestsRow, $m)) {
            $guests = $m[1];
            $kids = $m[2];
        } elseif (preg_match('/(\d+) Adult\/s and (\d+) Child\/ren/i', $guestsRow, $m)) {
            $guests = $m[1];
            $kids = $m[2];
        } elseif (preg_match('/^(\d+)\s+Adult\(s\)\s+\/\s+(\d+)\s+Child\(ren\)$/i', $guestsRow, $m)) {
            $guests = $m[1];
            $kids = $m[2];
        } elseif (preg_match('/Adults\s+(\d{1,3})\s*$/i', $guestsRow, $m)) {
            $guests = $m[1];
        } elseif (preg_match('/^\s*(\d{1,3})\s*$/', $guestsRow, $m)) {
            $guests = $m[1];
        }

        if (isset($guests)) {
            $r->booked()->guests($guests);
        }

        if (isset($kids)) {
            $r->booked()->kids($kids);
        }

        $room = $r->addRoom();

        // Rate
        $room->setRate($this->re("#\n[ ]*(?:{$this->opt($this->t("Rate"))}[ ]*:+[ ]*|Daily Rate[ ]+)(.+)#", $text), false, true);
        // Rooms
        // RoomType
        $roomInfo = $this->re("#{$this->opt($this->t("Number and type of room"))}[\s\:]*(.+)#i");

        if (preg_match("#^(\d+)\s+[x]?(.+)$#", $roomInfo, $m)) {
            $rooms = $m[1];
            $room->setType(str_replace('/', '', $m[2]));
        } else {
            $room->setType($roomInfo);
        }

        if (empty($rooms) && ($nr = $this->re("#{$this->opt($this->t("Number of rooms"))}[ ]*:+[ ]*(\d+)\s*\n#i"))) {
            $rooms = $nr;
        }

        if (!empty($rooms)) {
            $r->booked()->rooms($rooms);
        }

        // Currency
        // Total
        $totalRate = $this->re("/{$this->opt($this->t("Total rate:"))}[\s\:]*(.+)/");

        if (preg_match('/^(?<currency>[^\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*)$/', $totalRate, $matches)
            || preg_match('/^(?<currency2>[^\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*?)[ ]*(?<currency>[A-Z]{3})$/', $totalRate, $matches)
            || preg_match('/^\s*(?<amount>\d[,.\'\d ]*?)[ ]*(?<currency>[^\d)(]+?)$/', $totalRate, $matches)
            || preg_match('/^(?<currency>[^\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*)\D+$/', $totalRate, $matches)
        ) {
            // € 120.00    |    SEK 1,000.00    |    $7,403.01 USD    |    1,000.00 kr
            $currency = $this->normalizeCurrency($matches['currency']);
            $currencyCode = preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null;
            $r->price()->currency($currency)->total(PriceHelper::parse($matches['amount'], $currencyCode));
        // if (empty($matches['currency2'])) {
            //     $matches['currency2'] = $matches['currency'];
            // }
        } else {
            $cost = $this->re("/{$this->opt($this->t("Package Price:"))}[ ]*[:]*(.+)/");

            if (preg_match('/^(?<currency>[^\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*)$/', $cost, $matches)
                || preg_match('/^(?<currency2>[^\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*?)[ ]*(?<currency>[A-Z]{3})$/', $cost, $matches)
                || preg_match('/^(?<amount>\d[,.\'\d ]*?)[ ]*(?<currency>[^\d)(]+?)$/', $cost, $matches)
            ) {
                $currency = $this->normalizeCurrency($matches['currency']);
                $currencyCode = preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null;
                $r->price()->currency($currency)->cost(PriceHelper::parse($matches['amount'], $currencyCode));
                // if (empty($matches['currency2'])) {
                //     $matches['currency2'] = $matches['currency'];
                // }
            }
        }

        // Cancellation Policy
        $cancel = $this->re('/No Show, Guarantee and Cancellation Policy:\s*[ ]*([\s\S]+?)\n\n/')
            ?? $this->re('/(Once reservation is confirmed, changes or cancellations[ ]*[\s\S]+?)\n\n/')
            ?? $this->re('/(The reservation can be cancelled free of charge until[ ]*[\s\S]+?)\n\n/')
            ?? $this->re('/(In case of cancellation after[ ]*[\s\S]+?)\n\n/')
            ?? $this->re('/(You are able to cancel your reservation free of charge before [ ]*[\s\S]+?)\n\n/')
            ?? $this->re('/Cancellation\sPolicy\s+(If you need to change.+before arrival\.)/u')
            ?? $this->re('/(Bokningen kan avbokas kostnadsfritt fram till klockan[ ]*[\s\S]+?)\n\n/')
            ?? $this->re('/(In the event of a cancellation, please[ ]*[\s\S]+?)(?:\s*Check-in time is|\n\n)/')
            ?? $this->re('/(En cas.+sera demandé)/s')
        ;

        if (!empty($can = $this->re('/(In case you wish to amend or cancel your reservation[ ]*[\s\S]+?)\n/'))) {
            $cancel = $can . "." . $this->re('/(This reservation is held on guaranteed[ ]*[\s\S]+?)[\n]+.+Please be advised/');
        }
        $cancellationPolicy = preg_replace("#\s+#", ' ', $cancel);

        if (!empty($cancellationPolicy)) {
            $r->general()->cancellation($cancellationPolicy);
        }

        $this->detectDeadLine($r);

        if ($acc = $this->re('/Account Number[ ]*\:[ ]*(\d+)/')) {
            $r->program()->account($acc, false);
        }
    }

    /**
     * Here we describe various variations in the definition of dates deadLine.
     */
    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h): void
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (preg_match("/Once (?i)reservation is confirmed, changes or cancell?ations must be notified (?<prior>\d{1,3} hours?) prior to day of arrival/",
            $cancellationText, $m)
            || preg_match("/^In the event of a cancell?ation, please notify us (?<prior>\d{1,3} days?) prior to arrival to avoid a cancell?ation charge/i", $cancellationText, $m)
        ) {
            $h->booked()->deadlineRelative($m['prior'], '00:00');
        } elseif (preg_match("/^Bokningen kan avbokas kostnadsfritt fram till klockan (?<time>\d+:\d+|\d+\s*[ap]m) ankomstdagen/i",
                $cancellationText, $m)
            || preg_match("/^The reservation can be cancelled free of charge until (?<time>\d+:\d+|\d+\s*[ap]m) on the day of arrival, otherwise charges for the first night applies/i",
                $cancellationText, $m)
            || preg_match("/^If you need to change or cancel your reservation please inform the hotel by (?<time>\d+:\d+|\d+\s*[ap]m) day before arrival/i",
                $cancellationText, $m)
        ) {
            $h->booked()
                ->deadlineRelative('0 days', $m['time']);
        } elseif (preg_match("/^You are able to cancel your reservation free of charge before (?<time>\d+:\d+|\d+\s*[ap]m), two days before date of arrival/i",
            $cancellationText, $m)
        ) {
            $h->booked()
                ->deadlineRelative('2 days', $m['time']);
        }

        if (preg_match("#reservation can be cancelled without charge at (\d+[\d:\.pam ]*) on the arrival day\.#i",
            $cancellationText, $m) // en
        ) {
            $h->booked()
                ->deadlineRelative("0 day", $this->normalizeTime($m[1]));
        } elseif (preg_match("#may be cancelled without charge up to (\d+[\d:\.pam ]*) the day prior to arrival.#i",
            $cancellationText, $m) // en
        ) {
            $h->booked()
                ->deadlineRelative("1 day", $this->normalizeTime($m[1]));
        } elseif (preg_match("# the hotel should be notified by (\d+[\d:\.pam ]*) one day#i",
            $cancellationText, $m) // en
        ) {
            $h->booked()
                ->deadlineRelative("1 day", $this->normalizeTime($m[1]));
        } elseif (preg_match("#In case of cancellation after (\d+[\d:\.pam ]*PM) on the date of arrival#i",
            $cancellationText, $m) // en
        ) {
            $h->booked()
                ->deadlineRelative("0 day", $this->normalizeTime($m[1]));
        } elseif (preg_match("#En cas d'annulation de réservation individuelle faite au plus tard (\d+)h avant la date d'arrivée prévue, aucun frais ne sera demandé#i",
            $cancellationText, $m) // en
        ) {
            $h->booked()
                ->deadlineRelative($m[1]);
        }
    }

    private function getProvider(\PlancakeEmailParser $parser, $textBody)
    {
        $this->detectEmailByHeaders(['from' => $parser->getCleanFrom(), 'subject' => $parser->getSubject()]);

        if (isset($this->code)) {
            return $this->code;
        }

        foreach (self::$providers as $code => $arr) {
            $criteria = $arr['body'];

            if (count($criteria) > 0) {
                foreach ($criteria as $search) {
                    if (strpos($textBody, $search) !== false) {
                        return $code;
                    }
                }
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

    private function assignLangPdf($text): bool
    {
        foreach ($this->langDetectorsPdf as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if (stripos($text, $phrase) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function normalizeDate($str)
    {
//        $this->logger->debug($str);
        $str = preg_replace("#\s+(\d+:\d+)\s*noon#i", ' $1', $str);
        $in = [
            '/^(\d{1,2})[-.\/](\d{1,2})[-.\/](\d{4})[, ]*$/', // 31-03-2017    |    31.03.2017    |    31/03/2017
            '/^(\d{1,2})[-.\/](\d{1,2})[-.\/](\d{2})[, ]*$/', // 31-03-17    |    31.03.17    |    31/03/17
            '/\b(\d{1,2})[,.\s]+([[:alpha:]]+)[,.\s]+(\d{4})\s*$/u', //Hamburg, 16. Februar 2017
            '/^(\d{1,2})-([[:alpha:]]+)-(\d{4})$/u', // 07-OKT-2019
        ];
        $out = [
            '$1.$2.$3',
            '$1.$2.20$3',
            '$1 $2 $3',
            '$1 $2 $3',
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("/\d+\s+([[:alpha:]]+)\s+\d{4}/u", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return $str;
    }

    private function normalizeTime(?string $time): string
    {
        $in = [
            "/^(\d{1,2})[Hh](\d{2})$/", // 8h00
            "/^(\d{1,2})\s*([AaPp])(?:\.[ ]*)?([Mm])\.?\s*$/", // 6PM    |    6 p.m.
            "/^(\d{1,2}:\d{2})\s*([AaPp])(?:\.[ ]*)?([Mm])\.?\s*$/", // 6:00PM    |    6:00 p.m.
            "/^(?:12noon|12:00\s*M)$/i", // 12noon
        ];
        $out = [
            "$1:$2",
            "$1:00$2$3",
            "$1$2$3",
            "12:00",
        ];
        $time = preg_replace($in, $out, $time);

        if (preg_match("/^(\d+):(\d+)\s*pm$/i", $time, $m) && $m[1] > 12) {
            $time = preg_replace("/^(\d+:\d+)\s*pm$/i", '$1', $time);
        }

        return $time;
    }

    private function re($re, $str = false, $c = 1)
    {
        if ($str === false) {
            $str = $this->text;
        }
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    /**
     * @param string $string Unformatted string with currency
     */
    private function normalizeCurrency(string $string): string
    {
        $string = trim($string);
        $currencies = [
            // do not add unused currency!
            'EUR' => ['€'],
            'SEK' => ['kr'],
        ];

        foreach ($currencies as $currencyCode => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $currencyCode;
                }
            }
        }

        return $string;
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }
}
