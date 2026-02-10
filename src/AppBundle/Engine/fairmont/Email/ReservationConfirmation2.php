<?php

namespace AwardWallet\Engine\fairmont\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class ReservationConfirmation2 extends \TAccountChecker
{
    public $mailFiles = "fairmont/it-189177360.eml, fairmont/it-193612914.eml, fairmont/it-1970943.eml, fairmont/it-1971994.eml, fairmont/it-1975726.eml, fairmont/it-41643425.eml";

    public $reBody = "Fairmont";
    public $reBody2 = [
        // en
        "The Fairmont Copley Plaza Boston Team",
        "The Fairmont Monte Carlo Team",
        "The Fairmont Waterfront Team",
        "Fairmont Reservations",
        "Thank you",
        // ja
        "オンラインでのご予約ありがとうございました。",
        // fr
        'Merci d\'avoir réservé en ligne',
    ];
    public $detectLang = [
        'ja' => [
            'ご到着日：',
            'ご予約番号：',
        ],
        'ja2' => [
            'ご到着日は',
            'ご出発日は',
        ],
        'en' => [
            ['Arriving', 'Check-In'],
            'Your reservation number',
        ],
        'en2' => [
            'Arriving',
            'Departing',
        ],
        'en3' => [
            'Reservation',
            'Cancellation',
        ],
        'fr' => [
            'Arrivée',
            'Nombre de clients',
        ],
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
            'Arriving'                   => ['Arriving', 'Check-In'],
            'Departing'                  => ['Departing', 'Check-Out'],
            'Check in'                   => ['Check in', 'Check-In Time'],
            'Check out'                  => ['Check out', 'Checkout Time'],
            'Room Type'                  => ['Room Type', 'Your room', 'Room Type:'],
            'Your reservation number is' => ['Your reservation number is', 'Reservation N°', 'Your reservation number', 'RESERVATION NO.'],
            'Total:'                     => ['Total:', 'Total'],
            'Cancel Policy:'             => ['Cancel Policy:', 'Cancel Policy'],
        ],
        'ja' => [
            'Your reservation number is' => ['ご予約番号：', '予約番号'],
            'click here'                 => 'こちらをクリック',
            'Tel'                        => 'TEL',
            'Fax'                        => 'FAX',
            'E-mail'                     => ['Eメール', 'E メール'],
            'Cancel Policy:'             => ['キャンセルポリシー：', 'キャンセル期限'],
            'Cancel By:'                 => 'キャンセル期限：',
            //            'Check out'=>'',
            //            'Check in'=>'',
            'Arriving'         => ['ご到着日', 'ご到着日は'],
            'Departing'        => 'ご出発日',
            'Dear'             => ['様', "様,"],
            'Number of Guests' => 'ご宿泊人数',
            'Adult'            => '大人',
            'Children'         => 'お子様',
            'Room Type'        => ['客室タイプ', '客房'],
            //            'Room Description'=>'',
            'Rate Description' => '料金詳細',
            'Member Number'    => '会員番号',
            //            'Room Rate'=>'',
            'Total:' => '合計：',
        ],
        'fr' => [
            'Your reservation number is' => ['Réservation N°'],
            'click here'                 => 'cliquez ici',
            'Tel'                        => 'Tél.',
            'Fax'                        => 'Fax',
            'E-mail'                     => ['Courriel'],
            'Cancel Policy:'             => ['Politique d\'annulation'],
            'Cancel By:'                 => 'Délai d\'annulation',
            //            'Check out'=>'',
            //            'Check in'=>'',
            'Arriving'         => ['Arrivée'],
            'Departing'        => 'Départ',
            'Dear'             => ['Chère/cher'],
            'Number of Guests' => 'Nombre de clients',
            'Adult'            => 'Adulte',
            'Children'         => 'Enfant',
            'Room Type'        => 'Votre chambre',
            //            'Room Description'=>'',
            'Rate Description' => 'Description du tarif',
            // 'Member Number'    => '会員番号',
            //            'Room Rate'=>'',
            'Total:' => 'Total',
            'on'     => 'le',
        ],
    ];
    private $headers = [
        'gcampaigns' => [
            'from' => ['pkghlrss.com'],
            'subj' => [
                "/Reservation Confirmation/i",
                "/Reservation Update Confirmation/i",
                "/ご予約の確認メール/u",
            ],
        ],
        'fairmont' => [
            'from' => ['fairmont.com'],
            'subj' => [
                "/Reservation Confirmation/i",
                "/Reservation Update Confirmation/i",
                "/Reservation Cancellation/i",
                "/ご予約の確認メール/u",
                "/ご予約の確認メール:/u",
                "/Courriel de confirmation de réservation:/u",
            ],
        ],
    ];

    private static $bodies = [
        'gcampaigns' => [
            'groupcampaigns@pkghlrss.com',
            '//a[contains(@href,"passkey.com")]',
        ],
        'fairmont' => [
            '//a[contains(@href,"fairmont.com")]',
        ],
    ];

    private $code;

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        $this->parseEmail($email);

        $a = explode('\\', __CLASS__);
        $email->setType($a[count($a) - 1] . ucfirst($this->lang));

        if (null !== ($prov = $this->getProvider($parser))) {
            $email->setProviderCode($prov);
        }

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->headers as $code => $arr) {
            foreach ($arr['from'] as $f) {
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

        foreach ($this->headers as $code => $arr) {
            $byFrom = $bySubj = false;

            foreach ($arr['from'] as $f) {
                if (stripos($headers['from'], $f) !== false) {
                    $byFrom = true;
                }
            }

            foreach ($arr['subj'] as $subj) {
                if (preg_match($subj, $headers['subject'])) {
                    $bySubj = true;
                }
            }

            if ($byFrom && $bySubj) {
                $this->code = $code;
            }

            if ($bySubj) {
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
            if (strpos($body, $re) !== false) {
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
        return array_keys(self::$bodies);
    }

    private function getProvider(\PlancakeEmailParser $parser)
    {
        $this->detectEmailByHeaders(['from' => $parser->getCleanFrom(), 'subject' => $parser->getSubject()]);

        if (isset($this->code)) {
            if ($this->code === 'fairmont') {
                return null;
            } else {
                return $this->code;
            }
        }

        foreach (self::$bodies as $code => $criteria) {
            if (count($criteria) > 0) {
                foreach ($criteria as $search) {
                    if ((stripos($search, '//') === 0 && $this->http->XPath->query($search)->length > 0)
                        || (stripos($search, '//') === false && strpos($this->http->Response['body'],
                                $search) !== false)
                    ) {
                        return $code;
                    }
                }
            }
        }

        return null;
    }

    private function nice($str)
    {
        return trim(preg_replace("#\s+#", ' ', $str));
    }

    private function parseEmail(Email $email)
    {
        $patterns = [
            'phone' => '[\+\)\(\d][\- \d\)\(]{5,}[\d\)\(]', // +377 (93) 15 48 52
        ];

        $xpathBold = '(self::b or self::strong or ancestor-or-self::*[contains(@style,"bold")])';
        $xpathFragment1 = "[not(ancestor::*[{$xpathBold}]) and not(contains(.,':'))]";

        $r = $email->add()->hotel();

        $cancellationNumber = $this->http->FindSingleNode("//text()[normalize-space()='Cancellation N°']/following::text()[normalize-space()][1]");

        if (!empty($cancellationNumber)) {
            $r->general()
                ->cancelled()
                ->status('cancelled')
                ->cancellationNumber($cancellationNumber);
        }

        $r->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->starts($this->t('Your reservation number is'))}]/following::text()[normalize-space(.)!=''][1]",
                null, true, "#([\w\-]+)#"));

        $account = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Member Number'))}]/following::text()[normalize-space()][1]" . $xpathFragment1, null, true, "/^[-A-Z\d]{5,}$/");

        if ($account && !preg_match("/^{$this->opt($this->t('None'))}$/i", $account)) {
            $r->program()->account($account, false);
        }

        $name = $this->http->FindSingleNode("(//text()[{$this->contains($this->t('click here'))}][1]/ancestor::tr[1][{$this->contains($this->t('E-mail'))}]/descendant::text()[normalize-space(.)!=''])[1]");

        if (!empty($name)) {
            $node = implode(" ",
                $this->http->FindNodes("//text()[{$this->contains($this->t('click here'))}][1]/ancestor::tr[1][{$this->contains($this->t('E-mail'))}]/descendant::text()[normalize-space(.)!='']"));

            $address = $this->nice($this->re("#$name(.+?)\s+{$this->t('Tel')}#isu", $node));
            $phone = $this->re("/\b{$this->t('Tel')}\s*({$patterns['phone']})\s*{$this->t('Fax')}/i", $node);
            $fax = $this->re("/\b{$this->t('Fax')}\s*({$patterns['phone']})\s*{$this->opt($this->t('E-mail'))}/i",
                $node);
            $r->hotel()
                ->name($name)
                ->address($address)
                ->phone($phone)
                ->fax($fax, true, true);

            $node = implode("\n",
                $this->http->FindNodes("//text()[{$this->contains($this->t('Cancel Policy:'))}]/ancestor::tr[1]//text()[normalize-space(.)!='']"));
            $cancelPolicy = $this->nice($this->re("#{$this->opt($this->t('Cancel Policy:'))}\s+(.+?)\s+(?:{$name}|{$this->t('Cancel By:')})#isu",
                $node));

            if (empty($cancelPolicy)) {
                $cancelPolicy = $this->nice($this->re("/{$this->opt($this->t('Cancel Policy:'))}\s+(.*\bCXL\b.*)/",
                    $node));
            }

            if (!empty($cancelPolicy)) {
                $r->general()
                    ->cancellation($cancelPolicy);
            }
        } elseif (preg_match("#{$this->opt($this->t('Check out'))}:\s+\d+:?\d*\n*(Fairmont.+?)\n(.+)\nTel\s+({$patterns['phone']})\nFax\s+({$patterns['phone']})#is",
            $this->http->FindSingleNode("//text()[{$this->starts($this->t('Check out'))}]/ancestor::table[1]"),
            $m)
        ) {
            $canc = $this->nice(re("/{$this->opt($this->t('Cancel Policy:'))}\s+(.+?)\n/s",
                $this->http->FindSingleNode("//text()[{$this->starts($this->t('Check out'))}]/ancestor::table[1]")));
            $r->hotel()
                ->name($m[1])
                ->address($this->nice($m[2]))
                ->phone($this->nice($m[3]))
                ->fax($this->nice($m[4]));
            $r->general()
                ->cancellation($canc);
        }

        $checkInDate = $this->normalizeDate($this->http->FindSingleNode("//text()[{$this->starts($this->t('Arriving'))}]/following::text()[string-length(normalize-space())>1][1]", null, true, "/^(?:{$this->opt($this->t('on'))})?\s*(.*\d.*)$/"));

        if (empty($checkInDate)) {
            $checkInDate = $this->normalizeDate($this->http->FindSingleNode("//text()[{$this->starts($this->t('Your reservation number is'))}]/following::text()[{$this->starts($this->t('Arriving'))}][1]/following::text()[string-length(normalize-space())>1][1]",
                null, true, "/^(?:{$this->opt($this->t('on'))})?\s*(.*\d.*)$/"));
        }

        if (!empty($time = $this->normalizeTime($this->http->FindSingleNode("//text()[{$this->starts($this->t('Check in'))}]", null, true,
            "#{$this->opt($this->t('Check in'))}\s+(\d+:\d+(\s*[ap]m)?|\d+\s*[ap]m)\b#i")))
        ) {
            $checkInDate = strtotime($time, $checkInDate);
        }
        $checkOutDate = $this->normalizeDate($this->http->FindSingleNode("//text()[{$this->starts($this->t('Departing'))}]/following::text()[string-length(normalize-space())>1][1]", null, true, "/^(?:{$this->opt($this->t('on'))})?\s*(.*\d.*)$/"));

        if (!empty($time = $this->normalizeTime($this->http->FindSingleNode("//text()[{$this->starts($this->t('Check out'))}]", null, true,
            "#{$this->opt($this->t('Check out'))}\s+(\d+:\d+|\d+\s*[ap]m)#")))
        ) {
            $checkOutDate = strtotime($time, $checkOutDate);
        }

        if (!empty($checkInDate) && !empty($checkOutDate)) {
            $r->booked()
                ->checkIn($checkInDate)
                ->checkOut($checkOutDate);
        }

        $guestName = $this->http->FindSingleNode("(//text()[{$this->starts($this->t('Dear'))}])[1]", null, true,
            "#{$this->opt($this->t('Dear'))}\s+(.+)#");

        if (in_array($this->lang, ['ja'])) {
            if (empty($guestName)) {
                $guestName = $this->http->FindSingleNode("(//text()[{$this->ends($this->t('Dear'))}])[1]", null, true,
                    "#(.+?)\s*{$this->opt($this->t('Dear'))}$#");
            }

            if (empty($guestName)) {
                $guestName = $this->http->FindSingleNode("(//text()[{$this->ends($this->t('Dear'))}])[1]/preceding::text()[normalize-space()!=''][1]");
            }
        }
        $guestName = trim($guestName, ', ');
        $r->general()
            ->traveller(preg_replace('/^(?:Mrs|Mr|Ms)[.\s]+(.{2,})$/i', '$1', $guestName));
        // Guests
        // Kids
        $guestsInfo = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Number of Guests'))}]/following::text()[normalize-space(.)!=''][1]");

        $guests = $this->re("/^\s*(\d{1,3})\s*(?:,|{$this->opt($this->t('Adult'))}|$)/iu", $guestsInfo);

        if (!empty($guests)) {
            $r->booked()
                ->guests($guests);
        }

        $kids = $this->re("/,\s*(\d{1,3})\s*(?:{$this->opt($this->t('Children'))}|$)/iu", $guestsInfo);

        if (!isset($kids)) {
            $kids = $this->re("/\b{$this->opt($this->t('Children'))}\s*(\d{1,3})\s*/iu", $guestsInfo);
        }

        if (is_numeric($kids)) {
            $r->booked()->kids($kids);
        }

        // RoomType
        $roomType = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Room Type'))}]/following::text()[normalize-space() and not(ancestor::style)][1]");

        if (empty($roomType)) {
            $roomType = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Room Type'))}]/following::text()[normalize-space() and not(ancestor::style)][1][not({$this->contains($this->t('Room Type'))})]");
        }

        if (!empty($roomType)) {
            $room = $r->addRoom();
            $room
                ->setType($roomType);

            // RoomTypeDescription
            $roomDescription = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Room Description'))}]/following::text()[normalize-space(.)!=''][1]" . $xpathFragment1);

            if ($roomDescription) {
                $room->setDescription(str_replace(["\n", '  '], ' ', $roomDescription));
            }

            // RateType
            $rateDescription = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Rate Description'))}]/following::text()[normalize-space() and not(ancestor::style)][1]" . $xpathFragment1);

            if ($rateDescription) {
                $room->setRateType($rateDescription);
            }

            // Rate
            $roomRate = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Room Rate'))}]/following::text()[normalize-space(.)][1]" . $xpathFragment1);

            if ($roomRate) {
                $room->setRate($roomRate);
            }
        }

        // Total
        // Currency
        $tot = $this->getTotalCurrency($this->http->FindSingleNode("(//text()[{$this->eq($this->t('Total:'))}])[last()]/following::text()[normalize-space(.)!=''][1]"));

        if (!empty($tot['Total'])) {
            $r->price()->total($tot['Total']);

            if (!empty($tot['Currency'])) {
                $r->price()->currency($tot['Currency']);
            }
        }
        $this->detectDeadLine($r);
    }

    private function normalizeTime(?string $time): string
    {
        $in = [
            // 11AM
            '#^\s*(\d{1,2})\s*([AP]M)\s*$#ui',
        ];
        $out = [
            '$1:00 $2',
        ];
        $time = preg_replace($in, $out, $time);

        return $time;
    }

    private function normalizeDate($date)
    {
        //$this->logger->debug($date);
        $in = [
            '/^(\d{1,2})-([[:alpha:]]+)-(\d{4})$/u', // 25-十二月-2012
            '/^([[:alpha:]]+)\s+(\d{1,2}),\s*(\d{4})\s*$/', // Sep 27, 2018
            '/^[-[:alpha:]]+\s*,\s*([[:alpha:]]+)\s+(\d{1,2})[,\s]+(\d{4})$/u', // Fri, Oct 22 2021
            '/^(?:：)?(\d{1,2})-(\d{1,2})-(\d{4})$/u', // ：09-10-2019
            '/^(\d{1,2})\/(\d{1,2})\/(\d{2}) (\d+)\s*([ap]m)$/i', // 10/31/19 4PM
            '/^(\d{1,2})-([[:alpha:]]+)-(\d{2}) (\d+)\s*([ap]m)$/iu', // 24-DEC-16 4PM
            '/^(\d{4})\S(\d+)\S(\d+)\S\s+\(\S+\)$/u', //2022年9月11日 (日)
            '/^[-[:alpha:]]+\s*[.\s,]\s*(\d{1,2})\s+([[:alpha:]]+)[.]?\s+(\d{4})$/u', // dim. 12 nov. 2023
        ];
        $out = [
            '$1 $2 $3',
            '$2 $1 $3',
            '$2 $1 $3',
            '$3-$2-$1',
            '20$3-$1-$2, $4:00$5',
            '$1 $2 20$3, $4:00$5',
            '$3.$2.$1',
            '$1 $2 $3',
        ];
        $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));

        return strtotime($str);
    }

    /**
     * Here we describe various variations in the definition of dates deadLine.
     */
    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h)
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (preg_match("/NON Cancellable Booking FULL AMT DUE for No Show/", $cancellationText)) {
            $h->booked()
                ->nonRefundable();
        }

        if (preg_match("/^CXL BY (?<date>\d{1,2}\/\d{1,2}\/\d{2} \d+[ap]m)$/i", $cancellationText, $m)
            || preg_match("/^CXL BY (?<date>\d{1,2}\-\w+\-\d{2} \d+[ap]m)$/i", $cancellationText, $m)
            || preg_match("/^A full non-refundable deposit will be taken (?<days>\d+) days prior to arrival\. Checkout time is (?<time>\d+)(?<apm>[apm]{2})\./i",
                $cancellationText, $m)
        ) {
            if (!empty($m['date'])) {
                $h->booked()
                    ->deadline($this->normalizeDate($m['date']));
            }

            if (!empty($m['days'])) {
                $h->parseDeadlineRelative($m['days'] . " day", $m['time'] . " " . $m['apm']);
            }
        }
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
        if (isset($this->detectLang)) {
            foreach ($this->detectLang as $lang => $reBody) {
                if ($this->http->XPath->query("//*[{$this->contains($reBody[0])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($reBody[1])}]")->length > 0
                ) {
                    $this->lang = substr($lang, 0, 2);

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

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, 'zh')) {// it-1970943.eml
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function getTotalCurrency($node)
    {
        $dollar = preg_quote('$'); // $744.99 CAD

        if (preg_match("#{$dollar}[\s\d\.,]+\b[A-Z]{3}\b#", $node)) {
            $node = str_replace('$', '', $node);
        }
        $node = str_replace("€", "EUR", $node);
        $node = str_replace("$", "USD", $node);
        $node = str_replace("£", "GBP", $node);
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node,
                $m) || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node,
                $m) || preg_match("#(?<c>\-*?)(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)
        ) {
            $m['t'] = preg_replace('/\s+/', '', $m['t']);            // 11 507.00	->	11507.00
            $m['t'] = preg_replace('/[,.](\d{3})/', '$1', $m['t']);    // 2,790		->	2790		or	4.100,00	->	4100,00
            $m['t'] = preg_replace('/^(.+),$/', '$1', $m['t']);    // 18800,		->	18800
            $m['t'] = preg_replace('/,(\d{2})$/', '.$1', $m['t']);    // 18800,00		->	18800.00
            $tot = $m['t'];
            $cur = $m['c'];
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
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

    private function ends($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }
        $rules = [];

        foreach ($field as $f) {
            $len = mb_strlen($f);

            if ($len > 0) {
                $rule = "substring(normalize-space(),string-length(normalize-space())+1-{$len},{$len})='{$f}'";
                $rules[] = $rule;
            }
        }

        if (count($rules) == 0) {
            return 'false()';
        }

        return implode(' or ', $rules);
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }
}
