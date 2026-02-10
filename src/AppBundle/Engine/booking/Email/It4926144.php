<?php

namespace AwardWallet\Engine\booking\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class It4926144 extends \TAccountChecker
{
    public $mailFiles = "booking/it-10197178.eml, booking/it-11210775.eml, booking/it-12767454.eml, booking/it-2818787.eml, booking/it-3032413.eml, booking/it-3161785.eml, booking/it-34161504.eml, booking/it-35453117.eml, booking/it-4952508.eml"; // +1 bcdtravel(html)[pt]

    public $reSubject = [
        "de"  => "Ihr Aufenthalt in",
        "sv"  => "Din vistelse i",
        "es"  => "¡Falta poco para la fecha de tu reserva en",
        "pt"  => "sua estadia está se aproximando",
        "en"  => "Your booking in",
        "fr"  => "Votre séjour à",
        "he"  => "מועד ההזמנה שלכם",
        "da"  => "Din reservation i",
        "nl"  => "haal zoveel mogelijk uit uw zakenreis naar",
        "nl2" => "Je reservering in",
        "zh"  => "您即將入住",
        "it"  => "Manca poco al tuo soggiorno a",
        "ru"  => "Приближается время вашего бронирования в",
        "ro"  => "Mai este puţin până la sejurul dumneavoastră din",
    ];

    public $reBody = 'Booking.com';

    public $reBody2 = [
        'fr'  => 'Vos informations de réservation',
        'de'  => 'Ihre Buchungsinformationen',
        'pl'  => 'Informacje o rezerwacji',
        'sv'  => 'Din bokningsinformation',
        'es'  => 'Información de tu reserva',
        'pt'  => 'Dados da reserva',
        'pt2' => 'Informações da sua reserva',
        'en'  => 'Your booking information',
        'he'  => 'פרטי הזמנתכם',
        'da'  => 'Dine bookingoplysninger',
        'ja'  => '予約内容',
        'nl'  => 'Uw reserveringsgegevens',
        'nl2' => 'Je reserveringsgegevens',
        'zh'  => '預訂資訊',
        'it'  => 'Informazioni sulla tua prenotazione',
        'ru'  => 'Информация о Вашем бронировании',
        'ro'  => 'Detalii ale rezervării dumneavoastră',
    ];

    public static $dictionary = [
        'de' => [
            "Your reservation" => ["Ihre Buchung", "Ihre buchung"],
            "Check-in"         => "Check-in",
            "Check-out"        => "Check-out",
            "room"             => "Zimmer",
            'week away'        => 'In weniger als einer Woche beginnt',
            //			"Cancellation" => "",
            "Confirmation Number:" => "Buchungsnummer:",
        ],
        'pl' => [
            "Your reservation" => 'Twoja rezerwacja',
            "Check-in"         => "Zameldowanie",
            "Check-out"        => "Wymeldowanie",
            "room"             => "pokój",
            'week away'        => 'Został już niecały tydzień',
            //			"Cancellation" => "",
            //			"Confirmation Number:" => "",
        ],
        'sv' => [
            "Your reservation"     => 'Din reservation',
            "Check-in"             => "Incheckning",
            "Check-out"            => "Utcheckning",
            "room"                 => "rum",
            'week away'            => 'om mindre än en vecka',
            "Cancellation"         => "avboka",
            "Confirmation Number:" => "Bekräftelsenummer:",
        ],
        'es' => [
            "Your reservation"     => 'Tu reserva',
            "Check-in"             => "Entrada",
            "Check-out"            => "Salida",
            "room"                 => "habitación",
            "Cancellation"         => "cancelación",
            "Confirmation Number:" => "Número de confirmación:",
        ],
        'fr' => [
            "Your reservation"     => 'Votre réservation',
            "Check-in"             => "Enregistrement",
            "Check-out"            => "Départ",
            "room"                 => "chambre",
            "Cancellation"         => ["cancelación", "annuler"],
            "Confirmation Number:" => "Número de confirmación:",
        ],
        'pt' => [
            "Your reservation"     => ['Sua reserva', 'A sua reserva'],
            "Check-in"             => "Check-in",
            "Check-out"            => "Check-out",
            "room"                 => ["diárias", "Quarto"],
            "week away"            => "menos de 1 semana",
            "Cancellation"         => "cancelamento",
            "Confirmation Number:" => "Número de confirmação:",
        ],
        'en' => [
            "Your reservation"     => ["Your reservation", "Your Reservation"],
            "Cancellation"         => "ancellation",
            "Confirmation Number:" => ["Confirmation Number:", "Confirmation number:"],
        ],
        'he' => [
            "Your reservation"     => ["הזמנתכם"],
            "Check-in"             => "צ'ק-אין",
            "Check-out"            => "צ'ק-אאוט",
            "room"                 => "חדר",
            'week away'            => 'הוא בעוד פחות משבוע',
            "Cancellation"         => "לבטל",
            "Confirmation Number:" => "מספר אישור הזמנה:",
        ],
        'da' => [
            "Your reservation"     => ['Din reservation'],
            "Check-in"             => "Indtjekning",
            "Check-out"            => "Udtjekning",
            "room"                 => ["værelse"],
            "week away"            => "Der er under en uge til du skal til",
            "Cancellation"         => "afbestille",
            "Confirmation Number:" => "Bekræftelsesnummer:",
        ],
        'ja' => [
            "Your reservation"     => ['概要'],
            "Check-in"             => "チェックイン",
            "Check-out"            => "チェックアウト",
            "room"                 => ["部屋"],
            "week away"            => "週間を切りました！",
            "Cancellation"         => "キャンセル",
            "Confirmation Number:" => "予約番号：",
        ],
        'nl' => [
            "Your reservation"     => ['Uw reservering', 'Je reservering'],
            "Check-in"             => "Check-in",
            "Check-out"            => "Check-out",
            "room"                 => ["kamer"],
            "week away"            => "is over minder dan een week",
            "Cancellation"         => "annuleren",
            "Confirmation Number:" => "Bevestigingsnummer:",
        ],
        'zh' => [
            "Your reservation"     => ['你的預訂'],
            "Check-in"             => "入住",
            "Check-out"            => "退房",
            "room"                 => ["間客房"],
            "week away"            => "再過幾天就要出發去",
            "Cancellation"         => "免費取消",
            "Confirmation Number:" => "確認函編號：",
        ],
        'it' => [
            "Your reservation" => 'La tua prenotazione',
            "Check-in"         => "Check-in",
            "Check-out"        => "Check-out",
            "room"             => "camera",
            //            'week away' => '',
            //            "Cancellation" => "",
            "Confirmation Number:" => "Numero di conferma:",
        ],
        'ru' => [
            "Your reservation" => 'Ваше бронирование',
            "Check-in"         => "Дата заезда",
            "Check-out"        => "Дата отъезда",
            "room"             => "номер",
            'week away'        => 'осталось меньше недели',
            //            "Cancellation" => "",
            "Confirmation Number:" => "Номер бронирования:",
        ],
        'ro' => [
            "Your reservation" => 'Rezervarea dumneavoastră',
            "Check-in"         => "Check-in",
            "Check-out"        => "Check-out",
            "room"             => "cameră",
            'week away'        => 'va avea loc în mai puţin de o săptămână.',
            //            "Cancellation" => "",
            "Confirmation Number:" => "Numărul confirmării:",
        ],
    ];

    public $lang = '';

    private $patterns = [
        'time' => '\d{1,2}(?:[:.]\d{2})?(?:\s*[AaPp]\.?[Mm]\.?|\s*noon|\s*午[前後])?', // 4:19PM    |    2:00 p.m.    |    3pm    |    12 noon    |    3:10 午後    |    09.00
    ];

    /**
     * @return array|Email
     *
     * @throws \AwardWallet\Schema\Parser\Component\InvalidDataException
     */
    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $year = date('Y', strtotime($parser->getDate()));

        foreach ($this->reBody2 as $lang => $re) {
            if (stripos($this->http->Response["body"], $re) !== false) {
                $this->lang = substr($lang, 0, 2);

                break;
            }
        }
        $email->setType('BookingInformation' . ucfirst($this->lang));
        $email->obtainTravelAgency();

        $h = $email->add()->hotel();

        if ($conf = $this->nextText($this->t("Confirmation Number:"))) {
            $email->ota()->confirmation($conf, trim($this->http->FindSingleNode("(//text()[" . $this->eq($this->t("Confirmation Number:")) . "])[1]"), ':：'));
        }

        $h->general()->noConfirmation();

        $xpathFragments['p'] = "(self::p or self::div)";

        // hotelName
        $hotelName = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Your reservation'))}]/ancestor::*[{$xpathFragments['p']}]/preceding-sibling::h2[normalize-space(.)]");

        if (empty($hotelName)) {
            $hotelName = $this->http->FindSingleNode("//img[contains(@src, '/img/icons/stars')]/preceding::text()[string-length(normalize-space(.))>1][1]");
        }

        if (empty($hotelName)) {
            $hotelName = $this->http->FindSingleNode("//text()[contains(., 'Your reservation')]/preceding::a[2]");
        }
        $h->hotel()->name($hotelName);

        // address
        // phone
        $address = '';
        $phone = '';
        $contactTexts = $this->http->FindNodes("//text()[{$this->eq($this->t('Your reservation'))}]/ancestor::*[{$xpathFragments['p']}]/preceding-sibling::*[{$xpathFragments['p']} and ./preceding-sibling::h2[normalize-space(.)]]/descendant::text()[normalize-space(.)]");
        $contactText = implode("\n", $contactTexts);

        if (preg_match("/^(?<address>.+?)\s+(?<phone>[+)(\d][\-.\s\d)(]{5,}[\d)(])/s", $contactText, $m)) {
            $address = preg_replace('/\s+/', ' ', $m['address']);
            $phone = $m['phone'];
        }

        if (empty($phone) && !empty($hotelName)) {
            $phone = $this->http->FindSingleNode("//text()[normalize-space(.)='{$hotelName}']/following::text()[string-length(normalize-space(.))>1 and ./following::text()[{$this->eq($this->t('Your reservation'))}]][contains(., '+')]");
        }

        if (empty($address) && !empty($phone)) {
            $address = $this->http->FindSingleNode($q = "//*[ {$xpathFragments['p']} and not(.//*[{$xpathFragments['p']}]) and ./preceding::text()[normalize-space(.)][1][./ancestor::h2] and ./following::text()[normalize-space(.)][1][contains(normalize-space(.),'{$phone}')] ]");
            $this->logger->critical($q);

            if (empty(trim($address))) {
                $address = $this->http->FindSingleNode("//h2[ not(.//*[{$xpathFragments['p']}]) and ./preceding::text()[normalize-space(.)!=''][1][./ancestor::h1] and ./following::text()[normalize-space(.)!=''][1][contains(normalize-space(.),'{$phone}')] ]");
            }
        }

        if ($address === $hotelName) {
            $h->hotel()
                ->noAddress()
                ->phone($phone);
        } else {
            $h->hotel()
                ->address($address)
                ->phone($phone);
        }

        $checkin = $this->normalizeDate(preg_replace("/\(({$this->patterns['time']})\s+-\s+({$this->patterns['time']})\)/u", "$1", $this->nextText($this->t("Check-in"))));

        if (empty($checkin)) {
            $checkin = $this->normalizeDate(preg_replace("/\(({$this->patterns['time']})\s+-\s+({$this->patterns['time']})\)/u", "$1", $this->nextText2($this->t("Check-in"))));
        }
        $checkout = $this->normalizeDate(preg_replace("/\(({$this->patterns['time']})\s+-\s+({$this->patterns['time']})\)/u", "$2", $this->nextText($this->t("Check-out"))), false);

        if (empty($checkout)) {
            $checkout = $this->normalizeDate(preg_replace("/\(({$this->patterns['time']})\s+-\s+({$this->patterns['time']})\)/u", "$2", $this->nextText2($this->t("Check-out"))));
        }
        $h->booked()
            ->checkIn2($checkin)
            ->checkOut2($checkout);

        $h->booked()
            ->rooms($this->re("#(\d+)\s+" . $this->opt($this->t("room")) . "#", $this->nextText($this->t("Your reservation"))), true, true);

        $cancel = $this->http->FindSingleNode("//a[" . $this->contains($this->t("Cancellation")) . "]/ancestor::td[1]");
        $h->general()
            ->cancellation($cancel, false, true);

        if (!empty($cancel)
                && (
                    preg_match('/\b(?<month>\d{1,2})月(?<day>\d{1,2})日[ ]+(?<time>\d{1,2}:\d{2})（[\w ]+）までなら、無料で キャンセルできます/ui', $cancel, $m) //ja
                 || preg_match('/Du kan avboka GRATIS fram till kl\.\s+(?<time>\d{1,2}:\d{1,2})[ ]*\([\w ]+\) \w+ (?<day>\d{1,2}) (?<month>\w+)\./ui', $cancel, $m) //sv
                 || preg_match('/Your FREE cancellation is still available before (?<time>\d{1,2}:\d{1,2}(?:[ ]*[aApP][mM])?) on (?<month>\w+) (?<day>\d{1,2}), [\w ]+ time\./ui', $cancel, $m) //en
                 || preg_match('/Your FREE cancellation is still available before (?<time>\d{1,2}:\d{1,2}(?:[ ]*[aApP][mM])?) on (?<day>\d{1,2}) (?<month>\w+), [\w ]+ time\./ui', $cancel, $m) //en
                 || preg_match('/אתם יכולים לבטל בחינם עד לפני השעה (?<time>\d{1,2}:\d{2}) \w+-(?<day>\d{1,2}) (?<month>\w+), שעון ספיר\./ui', $cancel, $m) //he
                 || preg_match('/U kunt nog GRATIS annuleren vóór (?<time>\d{1,2}:\d{1,2}(?:[ ]*[aApP][mM])?) op (?<day>\d{1,2}) (?<month>\w+), lokale tijd in [\w ]+\./ui', $cancel, $m) //nl
                 || preg_match('/Vous pouvez encore annuler GRATUITEMENT avant le (?<day>\d{1,2}) (?<month>\w+) à (?<time>\d{1,2}:\d{1,2}(?:[ ]*[aApP][mM])?), heure de [\w ]+\./ui', $cancel, $m) //fr
                 || preg_match('/您仍可在[\w ]+時間 (?<month>\d{1,2}) 月 (?<day>\d{1,2}) 日 上午(?<time>\d{1,2}:\d{2}) 前 免費取消。/ui', $cancel, $m) //zh
                ) && (!empty($year) || !empty($year = date('Y', $h->getCheckInDate())))) {
            $d = (is_numeric($m['month'])) ? '.' : ' ';

            if (!is_numeric($m['month']) && $en = MonthTranslate::translate($m['month'], $this->lang)) {
                $m['month'] = $en;
            }
            $h->booked()
                ->deadline(strtotime($m['day'] . $d . $m['month'] . $d . $year . ', ' . $m['time']));
        }

        // travellers
        $travellerText = $this->http->FindSingleNode("//text()[contains(normalize-space(.),'{$this->t('week away')}')]/ancestor::*[contains(.,'!')][1]");
        $node = $this->http->FindSingleNode("//h3[contains(normalize-space(.), 'ありがとうございます')][1]"); // for ja
        // Hello, Nei M!    |    Hello Barbara!
        if ((preg_match('/,\s*(\w[-.\'\w\s]*[.\w])\s*!/u', $travellerText, $matches) || preg_match('/\s(\w+)\s*!/u', $travellerText, $matches))
                && strlen($matches[1]) > 1) {
            $h->general()
                ->traveller($matches[1], false);
        } elseif (preg_match('/(.+)、ありがとうございます/u', $node, $m)) { // ja
            $h->addTraveller($m[1]);
        }

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, 'Booking.com') !== false
            || stripos($from, '@booking.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            if (strpos($headers['subject'], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (stripos($body, $this->reBody) === false) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if (stripos($body, $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function nextText($field, $root = null, $n = 1)
    {
        if (!is_array($field)) {
            $field = [$field];
        }
        $rule = implode(" or ", array_map(function ($s) {
            return "normalize-space(.)=\"{$s}\"";
        }, $field));

        return $this->http->FindSingleNode("(.//text()[{$rule}])[{$n}]/following::text()[normalize-space(.)][1]", $root);
    }

    private function nextText2($field, $root = null, $n = 1)
    {
        if (!is_array($field)) {
            $field = [$field];
        }
        $rule = implode(" or ", array_map(function ($s) {
            return "normalize-space(.)=\"{$s}\"";
        }, $field));

        return $this->http->FindSingleNode("(.//p[{$rule}])[{$n}]/following-sibling::p[normalize-space(.)][1]", $root);
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($str, $firstTime = true)
    {
        //		$year = date("Y", $this->date);
//        $this->logger->info($str);
        $str = preg_replace("#\b(\d+)h(\d+)\b#", '$1:$2', $str); // 10h00 --> 10:00
        $res = '';
        $in = [
            //jueves, 8 de febrero de 2018
            "#^[^\d\s]+\s+(?<day>\d+)\s+de\s+(?<month>[^\d\s]+)\s+de\s+(?<year>\d{4})$#",
            //sábado, 2 de marzo de 2019 14:00
            "#^[^\d\s]+\s+(?<day>\d+)\s+de\s+(?<month>[^\d\s]+)\s+de\s+(?<year>\d{4})\s+(?<time>{$this->patterns['time']})$#",
            "#^[^\d]+\s+(?<day>\d+)\s+(?<month>[^\d\s]+)\s+(?<year>\d{4})\s+(?<time>{$this->patterns['time']})$#",
            "#^[^\d\s]+,\s+(?<month>[^\d\s]+)\s+(?<day>\d+),\s+(?<year>\d{4})\s+(?<time>{$this->patterns['time']})$#",
            "#^[^\d\s]+,\s+(?<day>\d+)\.\s+(?<month>[^\d\s]+)\s+(?<year>\d{4})\s+(?<time>{$this->patterns['time']})$#",
            "#^[^\d]+?[,]?\s+(?<day>\d+)\.?\s+(?<month>[^\d\s]+)\s+(?<year>\d{4})$#",
            "#^[^\d\s]+,\s+(?<month>[^\d\s]+)\s+(?<day>\d+),\s+(?<year>\d{4})$#", //Freitag, 1. März 2019
            '/^(?<year>\d{4})\s*\年\s*(?<month>\d{1,2})\s*月\s*(?<day>\d{1,2})\s*日\s*.+[ ]+(?<time>\d{1,2}:\d{2})$/ui', // 2019年3月31日(日) 14:00
            '/^(?<year>\d{4})\s*年\s*(?<month>\d{1,2})\s*月\s*(?<day>\d{1,2})\s*日\s*\（\w+\）\s*$/ui', // 2019年3月31日(日) 14:00
        ];

        if ($firstTime) {
            //sábado, 2 de marzo de 2019 (14:00 - 14:00)
            $in[] = "#^[^\d\s]+\s+(?<day>\d+)\s+de\s+(?<month>[^\d\s]+)\s+de\s+(?<year>\d{4})\s*\(\s*(?<time>{$this->patterns['time']})\s*\-\s*{$this->patterns['time']}\)$#";
            //lundi 25 février 2019 (0:00 - 10:00)
            $in[] = "#^[^\d\s]+\s+(?<day>\d+)\s+(?<month>[^\d\s]+)\s+(?<year>\d{4})\s*\(\s*(?<time>{$this->patterns['time']})\s*\-\s*{$this->patterns['time']}\)$#";
        } else {
            $in[] = "#^[^\d\s]+\s+(?<day>\d+)\s+de\s+(?<month>[^\d\s]+)\s+de\s+(?<year>\d{4})\s*\(\s*{$this->patterns['time']}\s*\-\s*(?<time>{$this->patterns['time']})\)$#";
            $in[] = "#^[^\d\s]+\s+(?<day>\d+)\s+(?<month>[^\d\s]+)\s+(?<year>\d{4})\s*\(\s*{$this->patterns['time']}\s*\-\s*(?<time>{$this->patterns['time']})\)$#";
        }

        foreach ($in as $re) {
            if (preg_match($re, $str, $m)) {
                if ('en' !== $this->lang && preg_match('/[^\d\s]{3,10}/iu', $m['month'])) {//Freitag, 1. März 2019
                    $res = $m['day'] . ' ' . MonthTranslate::translate($m['month'], $this->lang) . ' ' . $m['year'];
                } elseif (preg_match('/[^\d\s]{3,10}/iu', $m['month'])) {
                    $res = $m['day'] . ' ' . $m['month'] . ' ' . $m['year'];
                } else {
                    $res = $m['year'] . '-' . $m['month'] . '-' . $m['day'];
                }

                if (!empty($m['time'])) {
                    $res .= ', ' . $m['time'];
                }
            }
        }

        return $res;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'normalize-space(' . $node . ")='" . $s . "'"; }, $field)) . ')';
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
