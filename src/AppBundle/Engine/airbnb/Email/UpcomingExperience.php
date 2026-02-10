<?php

namespace AwardWallet\Engine\airbnb\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Common\Event;
use AwardWallet\Schema\Parser\Email\Email;

class UpcomingExperience extends \TAccountChecker
{
    public $mailFiles = "airbnb/it-37372367.eml, airbnb/it-37378497.eml";
    public static $dictionary = [
        "en" => [
            //            "guest" => "",
            //            "Change or cancel reservation" => "",
            //            "When" => "",
            //            "Where to meet" => "",
            //            "Your reservation code:" => "",
        ],
        "cs" => [
            "guest"                        => "host",
            "Change or cancel reservation" => "Změnit nebo zrušit rezervaci",
            "When"                         => "Kdy",
            "Where to meet"                => "Kde se sejít",
            "Your reservation code:"       => "Kód tvé rezervace:",
        ],
        "ko" => [
            "guest"                        => "게스트",
            "Change or cancel reservation" => "예약 변경 또는 취소",
            "When"                         => "체험 날짜",
            "Where to meet"                => "만날 장소",
            "Your reservation code:"       => "예약 코드:",
        ],
        "es" => [
            "guest"                        => "huéspede",
            "Change or cancel reservation" => "Modifica o cancela tu reserva",
            "When"                         => "Cuándo",
            "Where to meet"                => "Punto de encuentro",
            "Your reservation code:"       => "Tu código de reserva:",
        ],
        "pt" => [
            "guest"                        => "hóspede",
            "Change or cancel reservation" => "Alterar ou cancelar",
            "When"                         => "Quando",
            "Where to meet"                => "Onde se encontrar",
            "Your reservation code:"       => "Seu código de reserva:",
        ],
        "it" => [
            "guest"                        => "ospit",
            "Change or cancel reservation" => "Modifica o cancella",
            "When"                         => "Quando",
            "Where to meet"                => "Dove ci si incontra",
            "Your reservation code:"       => "Il tuo codice di prenotazione:",
        ],
        "de" => [
            "guest"                        => ["Gäste", 'Gast'],
            "Change or cancel reservation" => "Ändern oder stornieren",
            "When"                         => "Wann",
            "Where to meet"                => "Treffpunkt",
            "Your reservation code:"       => "Dein Buchungscode:",
        ],
        "nl" => [
            "guest"                        => "gast",
            "Change or cancel reservation" => "Wijzigen of annuleren",
            "When"                         => "Wanneer",
            "Where to meet"                => "Waar we elkaar ontmoeten",
            "Your reservation code:"       => "Je boekingscode:",
        ],
        "zh" => [
            "guest"                        => "位參加者",
            "Change or cancel reservation" => "更改或取消",
            "When"                         => "時間",
            "Where to meet"                => "會面地點",
            "Your reservation code:"       => "你的預訂代碼：",
        ],
        "fr" => [
            "guest"                        => "voyageur",
            "Change or cancel reservation" => "Modifier ou annuler",
            "When"                         => "Quand",
            "Where to meet"                => ["Point de rendez-vous", "Lieu de rendez-vous"],
            "Your reservation code:"       => "Votre code de réservation :",
        ],
    ];

    private $detectFrom = "@airbnb.com";
    private $detectSubject = [
        "en" => "Reservation confirmed: ", // Reservation confirmed: Discover Arches National Park on May 29
        "New start time:",
        "Rescheduled:",
        "cs" => "Rezervace potvrzena: ", // Rezervace potvrzena: Great wall Experience & Family lunch dne 13 Kvě
        "ko" => "예약 확정: ", // 예약 확정: Fátima, Nazaré and Óbidos Daytrip, 11월 1일
        "es" => "Reserva confirmada:",
        "pt" => "Reserva confirmada:",
        "it" => "Riprogrammata:",
        "Prenotazione confermata:",
        "de" => "Buchung bestätigt:",
        "nl" => "Reservering bevestigd:",
        "zh" => "預訂已確認：",
        "fr" => "Réservation confirmée",
    ];
    private $detectCompany = 'Airbnb';
    private $detectBody = [
        "en"   => "Get ready for your upcoming experience",
        "en2"  => "invited you to join an experience",
        "en3"  => "You rescheduled your experience",
        "en4"  => "Your meeting place has changed",
        "en5"  => "Your start time has changed",
        "cs"   => "Připrav se na nadcházející zážitek",
        "ko"   => "체험 예약이 확정되었습니다",
        "es"   => "Prepárate para tu próxima experiencia",
        "pt"   => "Prepare-se para sua próxima experiência",
        "it"   => "Hai riprogrammato la tua esperienza",
        "it2"  => "Preparati per la tua prossima esperienza",
        "de"   => "Mach dich bereit für deine anstehende Entdeckung",
        "nl"   => "Bereid je voor op je ervaring",
        "zh"   => "體驗即將開始，快作好準備吧",
        "fr"   => "Préparez-vous pour votre prochaine expérience",
    ];
    private $detectLang = [
        "en" => ["Where to meet"],
        "cs" => ["Kde se sejít"],
        "ko" => ["만날 장소"],
        "es" => ["Punto de encuentro"],
        "pt" => ["Onde se encontrar"],
        "it" => ["Dove ci si incontra"],
        "de" => ["Treffpunkt"],
        "nl" => ["Waar we elkaar ontmoeten"],
        "zh" => ["會面地點"],
        "fr" => ["Point de rendez-vous", 'Lieu de rendez-vous'],
    ];

    private $lang = "en";

    private $date;

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers["from"]) || empty($headers["subject"])) {
            return false;
        }

        if (stripos($headers["from"], $this->detectFrom) === false) {
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
        $body = $parser->getHTMLBody();

        if (stripos($body, $this->detectCompany) === false) {
            return false;
        }

        foreach ($this->detectBody as $dBody) {
            if (strpos($body, $dBody) !== false) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getDate());

        foreach ($this->detectLang as $lang => $detectLang) {
            if ($this->http->XPath->query("//node()[{$this->contains($detectLang)}]")->length > 0) {
                $this->lang = $lang;

                break;
            }
        }

        $this->parseHtml($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

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

    private function parseHtml(Email $email)
    {
        $ev = $email->add()->event();

        // General
        $reservationCode = $this->http->FindSingleNode("//text()[{$this->starts($this->t("Your reservation code:"))}]");

        if (preg_match("/({$this->preg_implode($this->t("Your reservation code:"))})\s*([-A-Z\d]{5,})$/", $reservationCode, $m)) {
            $ev->general()->confirmation($m[2], rtrim($m[1], ': '));
        }

        // Place
        $info = implode("\n", $this->http->FindNodes("//text()[" . $this->eq($this->t("Change or cancel reservation")) . "]/preceding::tr[1][count(*) = 2 and not(./*[1]//img) and ./*[2]//img]/*[1]//text()[normalize-space()]"));

        if (empty($info)) {
            $info = implode("\n", $this->http->FindNodes("//text()[" . $this->eq($this->t("When")) . "]/preceding::tr[position()<5][count(*) = 2 and not(./*[1]//img) and ./*[2]//img]/*[1]//text()[normalize-space()]"));
        }

        if ((!in_array($this->lang, ['ko']) && preg_match("#([\s\S]+?)\n\s*.+\n\s*(\d+)[ ]?" . $this->preg_implode($this->t("guest")) . ".*$#su", $info, $m))
            || (in_array($this->lang, ['ko']) && preg_match("#([\s\S]+?)\n\s*.+\n\s*" . $this->preg_implode($this->t("guest")) . " ?(\d+).*$#u", $info, $m))
        ) {
            $ev->place()
                ->name(str_replace('>', '', $m[1]));
            $ev->booked()
                ->guests($m[2]);
        }
        $ev->place()
            ->address($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Where to meet")) . "]/following::text()[normalize-space()][1]"))
            ->type(Event::TYPE_EVENT)
        ;
        // Booked
        $dates = implode("\n", $this->http->FindNodes("//text()[" . $this->eq($this->t("When")) . "]/ancestor::*[self::th or self::td][1]//text()[normalize-space()]"));

        if (preg_match("/{$this->preg_implode($this->t("When"))}\s*\n\s*(?<date>.{6,})\n\s*(?<time1>.+?)\s*[\-\–]\s*(?<time2>.+?)[ ]*(?:\(|[[:alpha:]]+\/[[:alpha:]]+|[A-Z]{3,4}\b).*$/su", $dates, $m)) {
            /*
                When
                Wednesday, Jan 1
                12:00 PM (noon) – 1:00 PM (Paris time)
            */
            // supported time zones: AEST|CEST|AST|CST|JST|MST|CDT|PDT|EDT|EST|EAT|GMT

            $dateStart = strtotime($this->normalizeTime($m['time1']), $this->normalizeDate($m['date']));
            $endStart = strtotime($this->normalizeTime($m['time2']), $this->normalizeDate($m['date']));

            if ($endStart < $dateStart) {
                $endStart = strtotime("+1 day", $endStart);
            }
            $ev->booked()
                ->start($dateStart)
                ->end($endStart);
        }

        return $email;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($date)
    {
        $year = date('Y', $this->date);
        $in = [
            '/^([-[:alpha:]]+)\s*,\s*([[:alpha:]]+)[.]?\s+(\d{1,2})$/u', // Wednesday, May 29
            '/^([-[:alpha:]]+)\s*,\s*(\d{1,2})\.?(?:\s+de)?\s+([[:alpha:]]+)[.]?\s*$/u', // Pondělí, 13 Kvě; Sonntag, 21. Mai
            '/^([-[:alpha:]]+)\s*,\s*(\d{1,2})\s?[월月]\s?(\d{1,2})\s?[일日]\s*$/u', //  월요일, 11월 1일
        ];
        $out = [
            '$1, $3 $2 ' . $year,
            '$1, $2 $3 ' . $year,
            '$1, $3.$2.' . $year,
        ];
        $date = preg_replace($in, $out, $date);

        if (preg_match("#^(\D*\d{1,2})\.(\d{1,2})\.(\d{4})\s*$#u", $date, $m)) {
            $date = $m[1] . ' ' . date("F", mktime(0, 0, 0, $m[2], 1, 2011)) . ' ' . $m[3];
        }

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#u", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        if (preg_match("#^(?<week>[\w\-]+), (?<date>\d+ \w+ .+)#u", $date, $m)) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($m['week'], $this->lang));
            $date = EmailDateHelper::parseDateUsingWeekDay($m['date'], $weeknum);
        } elseif (preg_match("#\b\d{4}\b#", $date)) {
            $date = strtotime($date);
        } else {
            $date = null;
        }

        return $date;
    }

    private function normalizeTime(?string $s): string
    {
        $s = preg_replace("/^(\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?).*/", '$1', $s);

        return $s;
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) { return "normalize-space(.)=\"{$s}\""; }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) { return "starts-with(normalize-space(.), \"{$s}\")"; }, $field)) . ')';
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) { return "contains(normalize-space(.), \"{$s}\")"; }, $field)) . ')';
    }

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
    }
}
