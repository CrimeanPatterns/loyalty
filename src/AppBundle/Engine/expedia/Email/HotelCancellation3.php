<?php

namespace AwardWallet\Engine\expedia\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class HotelCancellation3 extends \TAccountChecker
{
    public $mailFiles = "expedia/it-41222466.eml, expedia/it-41562054.eml, expedia/it-55051123.eml, expedia/it-262097262-fr.eml";

    public $reFrom = ['@expediamail.com', '@ca.expediamail.com', '@au.expediamail.com'];
    private $keywordProv = 'Expedia';
    private $provCode;
    private $reBody = [
        'en' => [
            'your hotel reservation has been cancelled',
            'your hotel reservation was cancelled',
            'your reservation was canceled',
            'your reservation was cancelled',
            'your reservation has been cancelled',
            'your reservation has been canceled',
        ],
        'ja' => ['ご予約がキャンセルされまし'],
        'nl' => ['je reservering is geannuleerd', 'je boeking is geannuleerd'],
        'de' => ['Ihre Buchung wurde storniert', 'deine Buchung wurde storniert'],
        'pt' => ['Sua reserva foi cancelada', 'A sua reserva foi cancelada', 'a sua reserva foi cancelada'],
        'es' => ['tu reservación se canceló', 'se ha cancelado tu reserva'],
        'fr' => ['votre réservation a été annulée'],
        'tr' => ['rezervasyonunuz iptal edildi'],
        'no' => ['Oppholdet ditt er avbestilt'],
        'zh' => ['您的預訂已取消', '您的订单已被取消'],
        'sv' => ['din bokning har avbokats.'],
        'da' => ['din reservation er blevet afbestilt'],
        'it' => ['la tua prenotazione è stata cancellata '],
    ];
    private $reSubject = [
        // en
        'Confirmed: Expedia Hotel Cancellation',
        'Expedia Hotel Cancellation Confirmation',
        'Hotels.com hotel cancellation confirmation',
        // nl
        'Bevestigd: Annulering van hotel op Expedia',
        'Bevestiging annulering hotelboeking Hotels.com',
        // de
        'Bestätigt: Expedia-Hotelstornierung',
        'Bestätigung der Hotelstornierung bei Hotels.com',
        // pt
        'Confirmação: cancelamento de hotel da Expedia',
        'Confirmação de cancelamento de hotel da Expedia',
        'Confirmação do cancelamento da reserva de hotel feita com a Hoteis.com -',
        'Confirmação do cancelamento da sua reserva de hotel no Hoteis.com',

        // es
        'Confirmación de cancelación de hotel de Expedia',
        'Confirmación de cancelación del hotel de Hoteles.com',
        'Confirmación de la cancelación de hotel con Expedia:',
        // fr
        "Confirmation d'annulation d'hôtel Expedia",
        'Confirmation d’annulation d’hôtel Expedia',
        'Confirmation d’annulation d’hôtel Hotels.com',
        // tr
        'Hotels.com Otel İptal Onayı',
        // no
        'Avbestillingsbekreftelse fra Hotels.com',
        // zh
        'Hotels.com 飯店預訂取消確認：',
        'Expedia 酒店取消确认 -',
        // ja
        'Hotels.com ホテル予約のキャンセルの確認通知',
        // sv
        'Bekräftelse på hotellavbokning från Hotels.com –',
        // da
        'Bekræftet afbestilling af hotel hos Hotels.com –',
        // it
        'Conferma della cancellazione dell\'hotel prenotato su Hotels.com - ',
    ];
    private $lang = '';
    private static $dict = [
        'en' => [
            'Your reservation with' => ['Your reservation with', 'was cancelled'],
            'was cancelled'         => ['Your reservation with', 'was cancelled', 'was canceled', 'has been cancelled', 'has been canceled'],
            'Hello '                => ['Hello ', 'Hi '],
            'reservation has been'  => ['reservation has been', 'reservation was'],
            'statusVariants'        => ['cancelled', 'cancelled with a full deposit refund', 'canceled'],
            'Itinerary #'           => ['Itinerary #', 'Itinerary:', 'Itinerary no.'],
            // 'at' => '',
            // 'from' => '',
            // 'to' => '',
        ],
        'nl' => [
            'Your reservation with' => ['Je reservering bij', 'Je boeking bij'],
            'was cancelled'         => ['is geannuleerd'],
            'Hello '                => 'Hallo ',
            'reservation has been'  => ['je reservering is', 'je boeking is'],
            'statusVariants'        => ['geannuleerd'],
            'Itinerary #'           => ['Reisplan:', 'Reisplannummer'],
            'at'                    => ['aan', 'at'],
            'from'                  => 'van',
            'to'                    => ['tot en met', 'tot'],
        ],
        'de' => [
            'Your reservation with' => ['Ihre Buchung bei', 'Deine Buchung bei'],
            'was cancelled'         => ['wurde storniert'],
            'Hello '                => 'Hallo ',
            'reservation has been'  => ['Ihre Buchung wurde', 'deine Buchung wurde'],
            'statusVariants'        => ['storniert'],
            'Itinerary #'           => ['Reiseplannr.:'],
            'at'                    => ['in', 'at'],
            'from'                  => 'vom',
            'to'                    => ['bis zum', 'bis'],
        ],
        'pt' => [
            'Your reservation with' => ['Sua reserva com', 'Sua reserva em', 'A sua reserva no', 'A sua reserva em'],
            'was cancelled'         => [', foi cancelada', ' foi cancelada'],
            'Hello '                => 'Olá,',
            'reservation has been'  => ['Sua reserva foi', 'A sua reserva foi', 'a sua reserva foi'],
            'statusVariants'        => ['cancelada'],
            'Itinerary #'           => ['Itinerário:', 'Nº do itinerário:', 'Itinerário n.º', 'Itinerário nº'],
            'at'                    => [', no endereço', ', em', 'em'],
            'from'                  => ', de',
            'to'                    => 'a',
        ],
        'es' => [
            'Your reservation with' => ['Se canceló tu reservación en', 'Se ha cancelado tu reserva con'],
            'was cancelled'         => ['Se canceló ', 'Se ha cancelado'],
            'Hello '                => ['Olá,', 'Hola,'],
            'reservation has been'  => ['tu reservación se', 'tu reserva'],
            'statusVariants'        => ['canceló', 'cancelado'],
            'Itinerary #'           => ['Itinerario:', 'Itinerario no.', 'N.º de itinerario:'],
            // 'at' => '',
            'from'                  => 'del',
            'to'                    => 'al',
        ],
        'fr' => [
            'Your reservation with' => ["Votre réservation auprès de l'établissement",
                'Votre réservation auprès de l’établissement', 'Votre réservation à l’hébergement',
                'Votre réservation à l’hôtel', ],
            'was cancelled'         => 'a été annulée',
            'Hello '                => 'Bonjour ',
            'reservation has been'  => 'réservation a été',
            'statusVariants'        => ['annulée'],
            'Itinerary #'           => ['Itinéraire :', 'Voyage n°', 'voyage n°', 'Numéro d’itinéraire :'],
            'at'                    => ["à l'adresse", 'à l’adresse', ', situé'], // %hotelName% at %address%
            'from'                  => 'du',
            'to'                    => 'au',
        ],
        'tr' => [
            'Your reservation with' => ['rezervasyonunuz iptal edildi.'],
            'was cancelled'         => 'iptal edildi',
            'Hello '                => 'Değerli ',
            'reservation has been'  => 'rezervasyonunuz iptal',
            'statusVariants'        => ['edildi'],
            'Itinerary #'           => ['Seyahat program numarası:'],
            // 'at'                  => [], // %hotelName% at %address%
            'from'                  => 'konumunda,',
            'to'                    => '-',
        ],
        'no' => [
            'Your reservation with' => ['Bestillingen din hos'],
            'was cancelled'         => 'er blitt avbestilt',
            'Hello '                => 'Hei, ',
            'reservation has been'  => 'Oppholdet ditt er',
            'statusVariants'        => ['avbestilt'],
            'Itinerary #'           => ['Reiserutenummer:'],
            'at'                    => 'på', // %hotelName% at %address%
            'from'                  => 'fra',
            'to'                    => 'til',
        ],
        'zh' => [
            'Your reservation with' => ['您預訂的', '您预订的'],
            'was cancelled'         => ['已經取消。', '） 的订单已被取消。'],
            'Hello '                => ['，您的預訂已取消', ' 您好,'],
            'reservation has been'  => ['您的預訂', '您的订单已被'],
            'statusVariants'        => ['已取消'],
            'Itinerary #'           => ['行程編號：', '行程编号：'],
            'at'                    => ['(地址：', '（地址为：'], // %hotelName% at %address%
            'from'                  => ['，日期：', '，时间为'],
            'to'                    => ['-', '至'],
        ],
        'ja' => [
            'Your reservation with' => ['のご予約'],
            'was cancelled'         => 'がキャンセルされました。',
            'Hello '                => '様の',
            'reservation has been'  => 'ご予約がキャ',
            'statusVariants'        => ['ンセルされました'],
            'Itinerary #'           => ['旅程番号 :'],
            // 'at'                  => '(地址：', // %hotelName% at %address%
            // 'from'                  => '，日期：',
            // 'to'                    => '-',
        ],
        'sv' => [
            'Your reservation with' => ['Din bokning på'],
            'was cancelled'         => ['har avbokats'],
            'Hello '                => 'Hej ',
            'reservation has been'  => ['din bokning har'],
            'statusVariants'        => ['avbokats'],
            'Itinerary #'           => ['Resplansnummer'],
            'at'                    => 'på',
            'from'                  => 'från',
            'to'                    => 'till',
        ],
        'da' => [
            'Your reservation with' => ['Din reservation hos'],
            'was cancelled'         => ['er blevet afbestilt'],
            'Hello '                => 'Hej ',
            'reservation has been'  => ['din reservation er blevet'],
            'statusVariants'        => ['afbestilt'],
            'Itinerary #'           => ['Rejseplansnummer:'],
            'at'                    => 'på',
            'from'                  => 'fra',
            'to'                    => 'til',
        ],
    ];
    private $date;
    private $subject;
    private $type = '';

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->subject = $parser->getSubject();
        $this->date = strtotime($parser->getDate());

        if (!empty($this->provCode)) {
            $email->setProviderCode($this->provCode);
        }

        if (!$this->assignLang()) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        $this->parseEmail($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . $this->type . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->detectProv() === true) {
            foreach ($this->reBody as $reBody) {
                if ($this->http->XPath->query("//*[{$this->contains($reBody)}]")->length > 0) {
                    return $this->assignLang();
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

    public function detectProv()
    {
        if ($this->http->XPath->query("//img[contains(@alt,'Expedia') or contains(@src,'.expediamail.com')] | //a[contains(@href,'.expediamail.com')] | //a[contains(@href,'.expedia.com')]")->length > 0) {
            $this->provCode = 'expedia';

            return true;
        } elseif ($this->http->XPath->query("//text()[contains(normalize-space(), 'Hotels.com') or contains(normalize-space(), 'Hoteles.com') or contains(normalize-space(), 'Hoteis.com')]")->length > 0) {
            $this->provCode = 'hotels';

            return true;
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true
            && stripos($headers['subject'], $this->keywordProv) === false
        ) {
            return false;
        }

        foreach ($this->reSubject as $reSubject) {
            if (stripos($headers['subject'], $reSubject) !== false) {
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

    public static function getEmailProviders()
    {
        return ["expedia", "hotels"];
    }

    private function parseEmail(Email $email): void
    {
        $r = $email->add()->hotel();

        $r->general()
            ->noConfirmation()
            ->cancelled()
            ->status($this->http->FindSingleNode("//text()[{$this->contains($this->t('Hello '))} and {$this->contains($this->t('reservation has been'))}]", null, false,
                "#{$this->opt($this->t('reservation has been'))}\s*({$this->opt($this->t('statusVariants'))})\s*[.;!?]*$#iu"));

        $traveller = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Hello '))} and {$this->contains($this->t('reservation has been'))}]", null, false,
            "#^{$this->opt($this->t('Hello '))}\s*([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])\s*[,!]#u");

        if (in_array($this->lang, ['zh', 'ja'])) {
            $traveller = $this->http->FindSingleNode("//text()[{$this->contains($this->t('reservation has been'))}]", null, false,
                "#^\s*([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])\s*[，,!]#u");
        }

        if (!empty($traveller) && !preg_match("/^travell?er$/", $traveller)) {
            $r->general()
                ->traveller($traveller);
        }

        $confNo = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Itinerary #'))}]/following::text()[normalize-space()][1]");

        if (!$confNo) {
            $confNo = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Itinerary #'))}]", null, true, "/^\s*{$this->opt($this->t('Itinerary #'))}\s*(\d{7,})\s*$/");
        }

        if (!$confNo) {
            $confNo = $this->http->FindPreg("/{$this->opt($this->t('Itinerary #'))}\s*(\d{7,})(?:\D|$)/u", false, $this->subject);
        }
        $r->ota()
            ->confirmation($confNo, 'Itinerary #');

        $total = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Refund total'))}]/following::text()[normalize-space()!=''][1]");

        if ($total) {
            $total = $this->getTotalCurrency($total);
            $r->price()
                ->total($total['Total'])
                ->currency($total['Currency']);
        }

        $foundFormat = false;
        $text = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Your reservation with'))}]/following::text()[normalize-space()][1]");

        if (preg_match("/(.+) in (.+) {$this->opt($this->t('from'))} (.+) {$this->opt($this->t('to'))} (.+)/", $text, $m)) {
            $foundFormat = true;
            // The Oxbow Hotel in Eau Claire from Fri, Jul 12 to Sat, Jul 13 (examples: it-41222466.eml, it-41562054.eml)
            $this->type = '1';
            $r->hotel()
                ->name($m[1])
                ->address($m[2]);
            $r->booked()
                ->checkIn($this->normalizeDate($m[3]))
                ->checkOut($this->normalizeDate($m[4]));
        }

        if (empty($text)) {
            $text = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Hello '))}]/following::tr[not(.//tr) and {$this->contains($this->t('was cancelled'))}]");
        }

        if (empty($text)) {
            $text = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Hello '))}]/following::text()[{$this->starts($this->t('Your reservation with'))} and {$this->contains($this->t('was cancelled'))}]");
        }

        if (empty($text) && in_array($this->lang, ['zh'])) {
            $text = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Hello '))}]/following::text()[{$this->starts($this->t('Your reservation with'))} and {$this->contains($this->t('was cancelled'))}]");
        }

        if ($foundFormat === false) {
            if (preg_match("/^\s*(?:{$this->opt($this->t('Your reservation with'))}\s+)?(.{3,}?)\s*(?:\s+at|{$this->opt($this->spaceBefore($this->t('at')))})\s*\b(.{3,}?)\s*{$this->opt($this->spaceBefore($this->t('from')))}\s*\b(.{3,}?)\s+{$this->opt($this->t('to'))}\s+(.{3,}?)[,\)]?\s*{$this->opt($this->spaceBefore($this->t('was cancelled')))}/u",
                $text, $m)) {
                $foundFormat = true;
                // The Hotel Belleclaire at 2175 Broadway New York, NY 10024 United States of America from Thu, Mar 12 to Fri, Mar 13 was cancelled
                // (examples: it-55051123.eml)
                $this->type = '2';
                $r->hotel()
                    ->name($m[1])
                    ->address($m[2]);
                $r->booked()
                    ->checkIn($this->normalizeDate($m[3]))
                    ->checkOut($this->normalizeDate($m[4]));
            } elseif (preg_match("/^\s*(?:{$this->opt($this->t('Your reservation with'))}\s+)?([^(]{3,}?)\s*\((.+)\)\s+{$this->opt($this->t('from'))}\s+(.{3,}?)\s+{$this->opt($this->t('to'))}\s+(.{3,}?)\s+{$this->opt($this->t('was cancelled'))}/",
                $text, $m)
            ) {
                $foundFormat = true;
                // Your reservation with Hilton Garden Inn Austin University Capitol District at 301 West 17th Street, Austin, TX, 78701 United States of America from Wed, Mar 20 to Fri, Mar 22 was canceled.
                // Je reservering bij GuestHouse Hotel Kaatsheuvel (Gasthuisstraat 118, Kaatsheuvel 5171GJ Nederland) van di 25 aug. tot en met wo 26 aug. is geannuleerd.
                $this->type = '3';
                $r->hotel()
                    ->name($m[1])
                    ->address($m[2]);
                $r->booked()
                    ->checkIn($this->normalizeDate($m[3]))
                    ->checkOut($this->normalizeDate($m[4]));
            }
        }

        if ($foundFormat === false) {
            if (
                $this->lang == 'es' && preg_match("/^\s*Se canceló tu reservación en (?<name>.+?) del (?<ci>.+?) al (?<co>.+?), ubicado en (?<address>.+?)\./u", $text, $m)
                | $this->lang == 'es' && preg_match("/^\s*Se canceló tu reservación en (?<name>.+?), en (?<address>.+?), del (?<ci>.+?) al (?<co>.+?)\./u", $text, $m)
                | $this->lang == 'es' && preg_match("/^\s*Se ha cancelado tu reserva con (?<name>.+?) en (?<address>.+?) del (?<ci>.+?) al (?<co>.+?)\./u", $text, $m)
                || $this->lang == 'tr' && preg_match("/^\s*(?<address>.+?) konumunda, (?<ci>.+?) - (?<co>.+?) tarihlerini kapsayan (?<name>.+?) rezervasyonunuz iptal edildi\./u", $text, $m)
                || $this->lang == 'ja' && preg_match("/^\s*(?<name>.+?)のご予約 \((?<address>.+?)、(?<ci>.+?) ～ (?<co>.+?)\) がキャンセルされました。/u", $text, $m)
            ) {
                // es: Se canceló tu reservación en The Old No. 77 Hotel & Chandlery del jue., sep 30 al mar., oct 5, ubicado en 535 Tchoupitoulas Street, New Orleans, LA 70130 Estados Unidos.
                // tr: Tsifliki, Elounda, Agios Nikolaos, Crete Island, 72053 Yunanistan konumunda, 16 Haziran Paz - 20 Haziran Per tarihlerini kapsayan Domes of Elounda, Autograph Collection rezervasyonunuz iptal edildi.
                $foundFormat = true;
                $this->type = '4';
                $r->hotel()
                    ->name($m['name'])
                    ->address($m['address']);
                $r->booked()
                    ->checkIn($this->normalizeDate($m['ci']))
                    ->checkOut($this->normalizeDate($m['co']));
            }
        }
    }

    private function normalizeDate($date)
    {
        // $this->logger->debug('$date = '.print_r( $date,true));
        $year = date('Y', $this->date);
        $in = [
            // Saturday, 25 July 2020
            '#^\w+[.,]*\s+(\d+)\s+(\w+)\s+(\d{4})\s*$#u',
            //Fri, Jul 12
            '#^(\w+)[.,]?,\s+(\w+)\s+(\d+)$#u',
            // wo 26 aug.; Mo., 15. Juni; qua, 15 de dez;  fre. d. 16. feb.
            '#^(\w+)[.,]*(?: d\.)?\s+(\d+)[.]?(?:\s+de)?\s+(\w+)[.]?$#u',
            // 20 Haziran Per
            '#^\s*(\d{1,2})\s+([[:alpha:]]+)\s+([[:alpha:]]+)\s*$#u',
            // 2 月 10 日 (六)
            '#^\s*(\d{1,2})\s*月\s*(\d{1,2})\s*日\s*\(\s*([[:alpha:]]+)\s*\)\s*\s*$#u',
        ];
        $out = [
            '$1 $2 $3',
            '$3 $2 ' . $year,
            '$2 $3 ' . $year,
            '$1 $2 ' . $year,
            $year . '-$1-$2',
        ];
        $outWeek = [
            '',
            '$1',
            '$1',
            '$3',
            '$3',
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

    private function assignLang(): bool
    {
        foreach (self::$dict as $lang => $words) {
            if (isset($words['Your reservation with'])) {
                if ($this->http->XPath->query("//*[{$this->contains($words['Your reservation with'])}]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
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

    private function getTotalCurrency($node)
    {
        $node = str_replace(["€", "£", "$", "₹"], ["EUR", "GBP", "USD", "INR"], $node);
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)
            || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m)
        ) {
            $cur = $m['c'];
            $currencyCode = preg_match('/^[A-Z]{3}$/', $cur) ? $cur : null;
            $tot = PriceHelper::parse($m['t'], $currencyCode);
        }

        return ['Total' => $tot, 'Currency' => $cur];
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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }

    private function spaceBefore($field)
    {
        $field = (array) $field;

        return preg_replace('/^(\w)/u', ' $1', $field);
    }
}
