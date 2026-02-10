<?php

namespace AwardWallet\Engine\egencia\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class CancellationNotification extends \TAccountChecker
{
    public $mailFiles = "egencia/it-29337792.eml, egencia/it-29385339.eml, egencia/it-29477430.eml, egencia/it-29532826.eml, egencia/it-29533499.eml, egencia/it-29544369.eml, egencia/it-29559360.eml, egencia/it-29620551.eml";

    public $reFrom = ["egencia."]; //egencia.fi, egencia.dk, egencia.fr, egencia.es etc...
    public $reBody = [
        'en' => ['Cancellation Notification', 'Hotel Cancelled'],
        'pl' => ['Powiadomienie o anulowaniu rezerwacji', 'Rezerwacja anulowana'],
        'sv' => ['Avbokningsmeddelande', 'Hotell avbokat'],
        'fr' => ['Notification d’annulation', 'Hôtel annulé'],
        'de' => ['Stornierungsbenachrichtigung', 'Hotel storniert'],
        'it' => ['Notifica di cancellazione', 'Prenotazione hotel cancellata'],
        'es' => ['Notificación de cancelación', 'Hotel cancelado'],
        'nl' => ['Notificatie over annulering', 'Hotel geannuleerd'],
        'no' => ['Bekreftelse på avbestilling', 'Hotell avbestilt'],
        'da' => ['Meddelelse om afbestilling', 'Hotelreservation afbestilt'],
    ];
    public $reSubject = [
        'Hotel - CANCELLED',
        'Hotel - ANULOWANE',
        'hotell - AVBOKAD',
        'Hôtel - ANNULÉ',
        'Hotel - STORNIERT',
        'Hotel - CANCELLATO',
        'Hotel - CANCELADO',
        'Hotel - GEANNULEERD',
        'Hotell - KANSELLERT',
        'Hotel - AFBESTILT',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [],
        'pl' => [
            'Check-in'            => 'Zameldowanie',
            'CANCELLED'           => 'ANULOWANE',
            'Itinerary'           => 'Plan podróży',
            'Cancellation Policy' => 'Zasady anulowania rezerwacji',
            'Confirmation'        => 'Potwierdzenie',
        ],
        'sv' => [
            'Check-in'            => 'Incheckning',
            'CANCELLED'           => 'AVBOKAD',
            'Itinerary'           => 'Resplan',
            'Cancellation Policy' => 'Avbokningspolicy',
            'Confirmation'        => 'Bekräftelse',
        ],
        'fr' => [
            'Check-in'            => 'Arrivée',
            'CANCELLED'           => 'ANNULÉ',
            'Itinerary'           => 'Voyage',
            'Cancellation Policy' => 'Politique d’annulation',
            'Confirmation'        => 'Confirmation',
        ],
        'de' => [
            'Check-in'            => 'Anreise',
            'CANCELLED'           => 'STORNIERT',
            'Itinerary'           => 'Reiseplan',
            'Cancellation Policy' => 'Stornierungsbedingungen',
            'Confirmation'        => 'Bestätigung',
        ],
        'it' => [
            'Check-in'            => 'Arrivo',
            'CANCELLED'           => 'CANCELLATO',
            'Itinerary'           => 'Itinerario',
            'Cancellation Policy' => 'Condizioni di cancellazione',
            'Confirmation'        => 'Conferma',
        ],
        'es' => [
            'Check-in'            => 'Entrada',
            'CANCELLED'           => 'CANCELADO',
            'Itinerary'           => 'Itinerario',
            'Cancellation Policy' => 'Política de cancelación',
            'Confirmation'        => 'Confirmación',
        ],
        'nl' => [
            'Check-in'            => 'Inchecken',
            'CANCELLED'           => 'GEANNULEERD',
            'Itinerary'           => 'Reisplan',
            'Cancellation Policy' => 'Annuleringsbeleid',
            'Confirmation'        => 'Bevestiging',
        ],
        'no' => [
            'Check-in'            => 'Innsjekking',
            'CANCELLED'           => 'CANCELLED',
            'Itinerary'           => 'Reiserute',
            'Cancellation Policy' => 'Avbestillingsvilkår',
            'Confirmation'        => 'Bekreftelse',
        ],
        'da' => [
            'Check-in'            => 'Ankomst',
            'CANCELLED'           => 'AFBESTILT',
            'Itinerary'           => 'Rejseplan',
            'Cancellation Policy' => 'Afbestillingspolitik',
            'Confirmation'        => 'Bekræftelse',
        ],
    ];
    private $date;

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getDate());

        if (!$this->assignLang()) {
            $this->logger->debug('can\'t determine a language');

            return $email;
        }

        $a = explode('\\', __CLASS__);
        $class = end($a);
        $email->setType($class . ucfirst($this->lang));

        $this->parseEmail($email);

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href,'egencia.com')] | //img[contains(@src,'egencia.com')]")->length > 0) {
            return $this->assignLang();
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && isset($headers["subject"])) {
            $flag = false;

            foreach ($this->reFrom as $reFrom) {
                if (stripos($headers['from'], $reFrom) !== false) {
                    $flag = true;
                }
            }

            if ($flag) {
                foreach ($this->reSubject as $reSubject) {
                    if (stripos($headers["subject"], $reSubject) === 0) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->reFrom as $reFrom) {
            if (stripos($from, $reFrom) !== false) {
                return true;
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    public function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function parseHotelCancelled(Email $email)
    {
        $xpath = "//img[contains(@src,'hotelIcon')]/ancestor::table[{$this->contains($this->t('Check-in'))}][1][{$this->starts($this->t('CANCELLED'))}]";
        $this->logger->debug($xpath);
        $nodesRes = $this->http->XPath->query($xpath);

        foreach ($nodesRes as $rootRes) {
            $email->ota()->confirmation($this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Itinerary'))}]/following::text()[normalize-space()!=''][2]",
                $rootRes));
            $h = $email->add()->hotel();
            $h->hotel()
                ->name($this->http->FindSingleNode("./descendant::text()[normalize-space(.)!=''][2]", $rootRes))
                ->noAddress();

            $h->general()
                ->cancelled()
                ->status($this->t('CANCELLED'))
                ->cancellation($this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Cancellation Policy'))}]/ancestor::tr[1]/following-sibling::tr[1]",
                    $rootRes));
            $confNo = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Confirmation'))}]/following::text()[normalize-space()!=''][2]",
                $rootRes);

            if (!preg_match("/^[\w\-]+$/", $confNo) && preg_match("/\bfee\b/i", $confNo)) {
                // CXL FEE APPLIES
                $h->general()
                    ->noConfirmation();
            } else {
                $h->general()
                    ->confirmation($confNo);
            }

            $h->booked()
                ->checkIn($this->normalizeDate($this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Check-in'))}]/ancestor::tr[1]/following-sibling::tr[1]/td[1]",
                    $rootRes)))
                ->checkOut($this->normalizeDate($this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Check-in'))}]/ancestor::tr[1]/following-sibling::tr[1]/td[2]",
                    $rootRes)));

            if (!empty($node = $h->getCancellation())) {
                $this->detectDeadLine($h, $node);
            }
        }

        return true;
    }

    /**
     * Here we describe various variations in the definition of dates deadLine.
     */
    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h, string $cancellationText)
    {
        if (preg_match("/^Das Hotel berechnet keine Gebühr für Stornierungen vor dem (?<time>\d+:\d+|\d+\s*[ap]m) Uhr Ortszeit am (?<date>\d+\/\d+\/\d{4})\./i",
                $cancellationText, $m)
            || preg_match("/^Hotellet tar inte ut straffavgift för avbokningar som utförs innan (?<time>\d+:\d+|\d+\s*[ap]m) hotellets lokaltid den (?<date>\d+\/\d+\/\d{4})\./i",
                $cancellationText, $m)
            || preg_match("/^Nie obowiązują opłaty za anulowanie przed (?<time>\d+:\d+|\d+\s*[ap]m) czasu lokalnego hotelu dnia (?<date>\d+\/\d+\/\d{4})\./i",
                $cancellationText, $m)
            || preg_match("/^There is no Hotel penalty for cancellations made before (?<time>\d+:\d+|\d+\s*[ap]m) local hotel time on (?<date>\d+\/\d+\/\d{4})\./i",
                $cancellationText, $m)
            || preg_match("/^No hay ninguna penalización por parte del hotel para las cancelaciones que se realicen antes de la siguiente fecha: (?<time>\d+:\d+|\d+\s*[ap]m) \(hora local del hotel\) del (?<date>\d+\/\d+\/\d{4})\./iu",
                $cancellationText, $m)
            || preg_match("/^Cancel Cancel by (?<time>\d+:\d+|\d+\s*[ap]m) (?<date>\d+ \w+ \d+|\d{2}\.\d{2}\.\d{2})$/i",
                $cancellationText, $m)
        ) {
            $h->booked()
                ->deadline(strtotime($m['time'], $this->normalizeDate($m['date'])));
        } elseif (preg_match("/^Annulation Sans Frais Jusquau Jour De Larrivee (\d{2})(\d{2}) Heure Locale Au Dela Lhotel Facture La Premiere Nuit/i",
            $cancellationText, $m)) {
            $h->booked()
                ->deadlineRelative('0 days', $m[1] . ":" . $m[2]);
        } elseif (preg_match("/^(\d+\s*[ap]m]) on Day of Arrival$/i", $cancellationText, $m)) {
            $h->booked()
                ->deadlineRelative('0 days', $m[1]);
        }
    }

    private function parseEmail(Email $email)
    {
        //if it will other type cancellation...

        if ($this->http->XPath->query("//img[contains(@src,'hotelIcon')]")->length > 0) {
            if (!$this->parseHotelCancelled($email)) {
                return false;
            }
        }

        return true;
    }

    private function normalizeDate($date)
    {
        $this->logger->debug($date);
        $year = date('Y', $this->date);
        $in = [
            // without year
            // Thu 20 Dec at 04:45; on 22 aug. i 06:40; ma 06 elokuuta, 21:50, fr 02 nov på 16:45
            '#^([^\d\s.,]+)\s+(\d+)\s+([^\d\s,.]+)[.,\s]+(?:at|i|på|)\s*(\d+:\d+(?:\s*[ap]m)?)$#ui',
            // Tue 25 Sep
            '#^\s*([^\d\s.,]+)\s+(\d+)\s+([^\d\s.,]+)[.\s]*$#ui',

            // with year and time
            // 11-Nov-2018 at 15:25; 13 nov. 2018 à 17:00; 3-dic-2018 alle 16.10; 14-nov-2018 a las 19:30; 01.des.2018 på 18:15
            '#^\s*(\d{1,2})[\-\s.]+([^\d\s.,]+)[\-\s.,]+(\d{4})\s+(?:at|à|alle|a las|på|om)\s+(\d+)[:.](\d+(?:\s*[ap]m)?)\s*$#ui',
            // 2018-nov-23 kl. 14:50
            '#^\s*(\d{4})[\-\s.]+([^\d\s.,]+)[\-\s.,]+(\d{1,2})\s*(?:kl.|\s)\s*(\d+)[:.](\d+(?:\s*[ap]m)?)\s*$#ui',
            // Jan 8, 2019 at 8:40 am
            '#^\s*([^\d\s.,]+)[\-\s.]+(\d{1,2})[,\-\s.]+(\d{4})\s*(?:at|\s)\s*(\d+)[:.](\d+(?:\s*[ap]m)?)\s*$#ui',
            // 1.2.2019, 20:20; 22.11.2018 um 19:45; 19.11.2018 v 18:10
            '/^\s*(\d{1,2})[.\-](\d{1,2})[.\-](\d{4})[.,]?\s*(?:um|i|v|\s)\s*(\d{1,2}:\d{2})\s*$/',
            // 2018-12-12o godzinie17:55
            '#^\s*(\d{4})[\-\s.]+(\d{1,2})[\-\s.,]+(\d{1,2})\s*(?:\s|o godzinie)\s*(\d+)[:.](\d+(?:\s*[ap]m)?)\s*$#ui',
            //17/01/2019 at 10:35 pm
            '/^\s*(\d+)[\/]+(\d+)[\/](\d{4})\s*\w+\s+(\d+:\d+(?:\s*[ap]m)?)$/iu',
            //17/jan/2019 at 10:35 pm
            '/^\s*(\d+)[\/]+(\w+)[\/](\d{4})\s*\w+\s+(\d+:\d+(?:\s*[ap]m)?)$/iu',

            // with year and without time
            // 20-Nov-2018; 14 nov. 2018
            '/^\s*(\d{1,2})[\- .]+([^\d\s]+)[\- \.](\d{4})\s*$/',
            // 2018-nov-20
            '/^\s*(\d{4})[\- .]+([^\d\s]+)[\- \.](\d{1,2})\s*$/',
            // 22.11.2018; 21-11-2018
            '/^\s*(\d{1,2})[\-.](\d{1,2})[\-.](\d{4})\s*$/',
            // 2018-12-12
            '/^\s*(\d{4})[\-.](\d{1,2})[\-.](\d{1,2})\s*$/',
            // Jan 8, 2019
            '#^\s*([^\d\s.,]+)[\-\s.]+(\d{1,2})[,\-\s.]+(\d{4})\s*$#ui',
            //17/01/2019
            '/^\s*(\d+)[\/]+(\d+)[\/](\d{4})\s*$/',
            //17/jan/2019
            '/^\s*(\d+)[\/]+(\w+)[\/](\d{4})\s*$/',
            //17 \w+ 19
            '/^\s*(\d+) (\w+) (\d{2})\s*$/',
            //04.12.18
            '/^\s*(\d{2})\.(\d{2})\.(\d{2})\s*$/',
        ];
        $out = [
            '$2 $3 ' . $year . ' $4',
            '$2 $3 ' . $year,

            '$1 $2 $3, $4:$5',
            '$3 $2 $1, $4:$5',
            '$2 $1 $3, $4:$5',
            '$1.$2.$3, $4',
            '$3.$2.$1, $4:$5',
            '$1.$2.$3, $4',
            '$1 $2 $3, $4',

            '$1.$2.$3',
            '$3.$2.$1',
            '$1.$2.$3',
            '$3.$2.$1',
            '$2 $1 $4',
            '$1.$2.$3',
            '$1 $2 $3',
            '$1 $2 20$3',
            '20$3-$2-$1',
        ];
        $outWeek = [
            '$1',
            '$1',

            '',
            '',
            '',
            '',
            '',
            '',
            '',

            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
        ];

        if (!empty($week = preg_replace($in, $outWeek, $date))) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($week, $this->lang));
            $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));
            $str = EmailDateHelper::parseDateUsingWeekDay($str, $weeknum);
        } else {
            $str = strtotime($this->dateStringToEnglish(preg_replace($in, $out, $date)));
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

    private function assignLang()
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if ($this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody[0]}')]")->length > 0
                    && $this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody[1]}')]")->length > 0
                ) {
                    $this->lang = substr($lang, 0, 2);

                    return true;
                }
            }
        }

        return false;
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field));
    }
}
