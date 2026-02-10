<?php

namespace AwardWallet\Engine\amoma\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class ConfirmPayment extends \TAccountChecker
{
    public $mailFiles = "amoma/it-35787959.eml, amoma/it-36222687.eml, amoma/it-37221243.eml, amoma/it-42601921.eml, amoma/it-42758120.eml";

    public $reFrom = ['noreply@amoma.com', 'bookings@amoma.com'];
    public $reBody = [
        'es' => ['Solo queda un paso para completar el proceso', 'Precio por noche'],
        'en' => ['Just one more step for you to complete', 'Price per night'],
        'it' => ['Soltanto un altro passaggio per completare il processo di pagamento della', 'Prezzo per notte'],
        'ko' => ['한 단계만 더 거치면', '하룻밤 가격'],
        'fr' => ['Il ne vous reste qu\'une étape', 'Prix par nuit'],
        'pt' => ['Confirme o pagamento de sua reserva', 'Preço por noite'],
    ];
    public $reSubject = [
        'es' => 'Confirma el pago de tu reserva',
        'en' => 'Confirm the payment of your booking',
        'it' => 'Conferma il pagamento della sua prenotazione',
        'ko' => '지금 예약 의 지불 을 확인하십시오',
        'fr' => 'Confirmez le paiement de votre réservation',
        'pt' => 'Confirme o pagamento de sua reserva',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
            'needPayment'   => 'need complete the payment',
            'pleaseContact' => 'If the link has expired, please contact us',
            'Capacity'      => 'Capacity',
            'Room(s)'       => 'Room(s)',
        ],
        'es' => [
            'Just one more step for you to complete' => 'Solo queda un paso para completar el proceso',
            'needPayment'                            => 'necesita completar el pago',
            'pleaseContact'                          => 'Si el enlace ha expirado, por favor contacta con nosotros',
            'Booking'                                => 'Reserva',
            'Check-in'                               => 'Llegada',
            'Check-out'                              => 'Salida',
            'Capacity'                               => 'Capacidad',
            'Room(s)'                                => 'Habitación(es)',
            'Adult(s)'                               => 'Adulto',
            'Children'                               => 'Niño',
            'Total price'                            => 'Precio total',
            'Price per night'                        => 'Precio por noche',
        ],
        'it' => [
            'Just one more step for you to complete' => 'Soltanto un altro passaggio per completare il processo di pagamento della',
            'needPayment'                            => 'Completare il pagamento',
            'pleaseContact'                          => 'Se il link è scaduto, ti preghiamo di contattarci all',
            'Booking'                                => 'Prenotazione',
            'Check-in'                               => 'Arrivo',
            'Check-out'                              => 'Check-out',
            'Capacity'                               => 'Capacità',
            'Room(s)'                                => 'Stanza(e) ',
            'Adult(s)'                               => 'Adulto',
            'Children'                               => 'Bambino',
            'Total price'                            => 'Prezzo totale',
            'Price per night'                        => 'Prezzo per notte',
        ],
        'ko' => [
            'Just one more step for you to complete' => '한 단계만 더 거치면',
            'needPayment'                            => '결제 완료하기',
            'pleaseContact'                          => '링크가 만료되었다면',
            'Booking'                                => '예약',
            'Check-in'                               => '체크인',
            'Check-out'                              => '체크아웃',
            'Capacity'                               => '투숙 가능 인원수',
            'Room(s)'                                => '객실',
            'Adult(s)'                               => '성인',
            'Children'                               => '아이',
            'Total price'                            => '총 가격',
            'Price per night'                        => '하룻밤 가격',
        ],
        'fr' => [
            'Just one more step for you to complete' => 'Il ne vous reste qu\'une étape',
            //    'needPayment' => '결제 완료하기',
            'pleaseContact'   => 'Si le lien a expiré, merci de nous contacter',
            'Booking'         => 'Réservation',
            'Check-in'        => 'Arrivée',
            'Check-out'       => 'Date de départ',
            'Capacity'        => 'Capacité',
            'Room(s)'         => 'Chambre(s)',
            'Adult(s)'        => 'Adulte(s)',
            'Children'        => 'Enfant',
            'Total price'     => 'Prix total à payer',
            'Price per night' => 'Prix par nuit',
        ],
        'pt' => [
            'Just one more step for you to complete' => 'Só mais um passo para que complete',
            //    'needPayment' => '결제 완료하기',
            'pleaseContact'   => 'Se a hiperligação expirou, por favor contacte-nos',
            'Booking'         => 'Reserva',
            'Check-in'        => 'Check-in',
            'Check-out'       => 'Check-out',
            'Capacity'        => 'Capacidade',
            'Room(s)'         => 'Quarto(s)',
            'Adult(s)'        => 'Adulto(s)',
            'Children'        => 'Crianças',
            'Total price'     => 'Preço total a ser pago',
            'Price per night' => 'Preço por noite',
        ],
    ];
    private $keywordProv = 'Amoma';

    private $enDatesInverted = false;

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug('can\'t determine a language');

            return $email;
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $this->parseEmail($email);

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href,'.amoma.com')] | //img[contains(@src,'.amoma.com')]")->length > 0) {
            if ($this->detectBody()) {
                return $this->assignLang();
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

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true && !preg_match("/\b{$this->keywordProv}\b/i", $headers['subject'])) {
            return false;
        }

        if (isset($headers["subject"])) {
            foreach ($this->reSubject as $reSubject) {
                if (stripos($headers['subject'], $reSubject) !== false) {
                    return true;
                }
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

    private function parseEmail(Email $email)
    {
        $r = $email->add()->hotel();
        $pax = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Just one more step for you to complete'))}]/preceding::text()[normalize-space()][3][contains(.,',')]", null, true, '/^([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])[,.!]*$/u');

        if ($pax) {
            $r->general()
                ->traveller($pax)
                ->status($this->t('needPayment'));
        } else {
            $this->logger->debug('looks like other format');

            return false;
        }

        $r->program()
            ->phone($this->http->FindSingleNode("//text()[{$this->starts($this->t('pleaseContact'))}]/following::text()[normalize-space()][position()<3][starts-with(normalize-space(),'+')]", null, true, '/^[+(\d][-. \d)(]{5,}[\d)]$/'));

        $r->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->starts($this->t('Booking'))}]", null, false,
                "#{$this->opt($this->t('Booking'))}\s+(\d+)\s*$#"), $this->t('Booking'));

        $r->hotel()
            ->name($this->http->FindSingleNode("//text()[{$this->starts($this->t('Just one more step for you to complete'))}]/preceding::text()[normalize-space()!=''][2]"))
            ->address($this->http->FindSingleNode("//text()[{$this->starts($this->t('Just one more step for you to complete'))}]/preceding::text()[normalize-space()!=''][1]"));

        $r->booked()
            ->checkIn2($this->normalizeDate($this->nextText('Check-in')))
            ->checkOut2($this->normalizeDate($this->nextText('Check-out')))
            ->guests($this->nextText('Capacity', null, "#\b(\d{1,3})\s+{$this->opt($this->t('Adult(s)'))}#"))
            ->kids($this->nextText('Capacity', null, "#\b(\d{1,3})\s+{$this->opt($this->t('Children'))}#"));

        $total = $this->getTotalCurrency($this->nextText('Total price', null, null, 'starts'));
        $r->price()
            ->total($total['Total'])
            ->currency($total['Currency']);

        $pricePerNight = $this->nextText('Price per night');

        $roomsHtml = $this->http->FindHTMLByXpath("//text()[{$this->starts($this->t('Room(s)'))}]/ancestor::node()[ following-sibling::node()[normalize-space()] ][1]");
        $roomsText = $this->htmlToText($roomsHtml);

        if (preg_match_all("/^[ ]*(?:{$this->opt($this->t('Room(s)'))}[: ]+)?(?<count>\d{1,3})[ ]*x[ ]*(?<type>.+?)(?:[ ]+-[ ]+(?<desc>.+))?[ ]*$/m", $roomsText, $matches, PREG_SET_ORDER)) {
            $roomsCount = 0;

            foreach ($matches as $m) {
                for ($i = 0; $i < (int) $m['count']; $i++) {
                    $room = $r->addRoom();
                    $room->setRate($pricePerNight);
                    $room->setType($m['type']);

                    if (!empty($m['desc'])) {
                        $room->setDescription($m['desc']);
                    }
                    $roomsCount++;
                }
            }

            if ($roomsCount) {
                $r->booked()->rooms($roomsCount);
            }
        }

        $nodes = $this->http->XPath->query("//text()[{$this->starts($this->t('Room(s)'))}]/ancestor::td[1]/node()[normalize-space()!='']");
        $cancel = [];

        foreach ($nodes as $i => $root) {
            if ($i === 0) {
                $node = $this->http->FindSingleNode("./descendant::text()[normalize-space()!=''][2]", $root);

                continue;
            }
            $cancel[] = trim($this->http->FindSingleNode(".", $root), ';.');
        }

        if (!empty($cancel = array_filter($cancel))) {
            $r->general()->cancellation(implode('; ', $cancel));
        }
        $this->detectDeadLine($r);

        return true;
    }

    private function nextText(string $field, $root = null, $regexp = null, $rule = 'eq')
    {
        switch ($rule) {
            case 'starts':
                $ruleText = $this->starts($this->t($field));

                break;

            case 'contains':
                $ruleText = $this->contains($this->t($field));

                break;

            default:
                $ruleText = $this->eq($this->t($field));

                break;
        }

        return $this->http->FindSingleNode("descendant::text()[{$ruleText}][1]/following::text()[normalize-space()!=''][1]",
            $root, false, $regexp);
    }

    /**
     * Here we describe various variations in the definition of dates deadLine.
     */
    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h)
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (preg_match("#0 ₪ if you cancel or modify your booking between .+? to (?<date>.+? \d{4})#ui",
            $cancellationText, $m)
        ) {
            $h->booked()
                ->deadline(strtotime($m['date'] . ', 23:59'));
        }
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function detectBody()
    {
        $body = $this->http->Response['body'];

        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function assignLang()
    {
        foreach (self::$dict as $lang => $words) {
            if (isset($words["Capacity"], $words["Room(s)"])) {
                if ($this->http->XPath->query("//*[{$this->contains($words['Capacity'])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($words['Room(s)'])}]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function getTotalCurrency($node)
    {
        $tot = '';
        $cur = '';
        $node = str_replace("€", "EUR", $node);
        $node = str_replace("$", "USD", $node);
        $node = str_replace("₪", "ILS", $node);
        $node = str_replace("£", "GBP", $node);
        $node = str_replace("₩", "KRW", $node);

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)
            || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m)
        ) {
            $cur = $m['c'];
            $tot = PriceHelper::cost($m['t']);
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function htmlToText($s, $brConvert = true): string
    {
        if (!is_string($s) || $s === '') {
            return '';
        }

        if ($brConvert) {
            $s = str_replace("\n", '', $s);
            $s = preg_replace('/<[Bb][Rr]\b.*?\/?>/', "\n", $s); // only <br> tags
        }
        $s = preg_replace('/<[A-z]+\b.*?\/?>/', '', $s); // opening tags
        $s = preg_replace('/<\/[A-z]+\b[ ]*>/', '', $s); // closing tags
        $s = html_entity_decode($s);
        $s = str_replace(chr(194) . chr(160), ' ', $s); // NBSP to SPACE

        return trim($s);
    }

    private function normalizeDate($text)
    {
        if (!is_string($text) || empty($text)) {
            return '';
        }
        $in = [
            // 31/08/2019
            '/^(\d{1,2})[ ]*\/[ ]*(\d{1,2})[ ]*\/[ ]*(\d{4})$/u', //es
            // 01/ago/2019
            '#^(\d+)/([[:alpha:]]+)/(\d{4})$#u', //it
            // 2019. 9. 11.
            '/^(\d{4})\. (\d+)\. (\d+)\.$/', //ko
        ];
        $out = [
            '$1.$2.$3',
            '$1 $2 $3',
            '$2/$3/$1',
        ];

        $str = preg_replace($in, $out, $text);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return $str;
    }
}
