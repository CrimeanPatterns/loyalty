<?php

namespace AwardWallet\Engine\eurobonus\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class BookingHtml2012 extends \TAccountChecker
{
    public $mailFiles = "eurobonus/it-1.eml, eurobonus/it-12.eml, eurobonus/it-1416481.eml, eurobonus/it-15.eml, eurobonus/it-1585239.eml, eurobonus/it-1585777.eml, eurobonus/it-1652531.eml, eurobonus/it-1676560.eml, eurobonus/it-1676561.eml, eurobonus/it-2010316.eml, eurobonus/it-2276204.eml, eurobonus/it-2338807.eml, eurobonus/it-2338809.eml, eurobonus/it-2378296.eml, eurobonus/it-2523048.eml, eurobonus/it-2678086.eml, eurobonus/it-2788702.eml, eurobonus/it-3129159.eml, eurobonus/it-3131660.eml, eurobonus/it-3131661.eml, eurobonus/it-4835808.eml, eurobonus/it-4863281.eml, eurobonus/it-4951313.eml, eurobonus/it-4970340.eml, eurobonus/it-5006802.eml, eurobonus/it-5060891.eml, eurobonus/it-5159034.eml, eurobonus/it-5175638.eml, eurobonus/it-5178841.eml, eurobonus/it-5203854.eml, eurobonus/it-5627340.eml, eurobonus/it-5677694.eml";

    protected $subjects = [
        'fr' => ['Votre vol SAS'],
        'es' => ['Tu vuelo de SAS'],
        'ru' => ['Ваш рейс SAS'],
        'it' => ['Il tuo volo SAS'],
        //        'fi' => [''],
        'sv' => ['Din resa'],
        'de' => ['Ihr SAS Flug'],
        'da' => ['Din rejse'],
        'no' => ['Din reise'],
        'en' => ['Your SAS flight'],
        'ja' => ['SAS予約便'],
        'zh' => ['北欧航空 航班'],
    ];

    protected $langDetectors = [
        'fr' => ['Référence de la réservation:', 'Référence de la réservation :'],
        'es' => ['Referencia de reserva:', 'Referencia de reserva :'],
        'ru' => ['Номер бронирования'],
        'it' => ['Riferimento di prenotazione:', 'Riferimento di prenotazione :'],
        'fi' => ['Varausnumero'],
        'sv' => ['Bokningsreferens:', 'Bokningsreferens :'],
        'de' => ['Buchungsnummer:', 'Buchungsnummer :'],
        'da' => ['Bookingnummer:', 'Bookingnummer :'],
        'no' => ['Bestillingsreferanse'],
        'zh' => ['感谢您预订北欧航空机票！'],
        'ja' => ['SASをご利用いただきありがとうございます'],
        'en' => ['Booking reference:', 'Booking reference :'],
    ];

    protected $lang = '';

    protected static $dict = [
        'fr' => [
            'Booking reference' => 'Référence de la réservation',
            //            'RouteNames' => '', // if not ends with ':' or '：'
            'Operated by:'  => 'Vol opéré par',
            'Aircraft'      => 'Type d’appareil',
            'Booking class' => 'Classe de réservation',
            //            'Seat:' => '',
            //            'Terminal' => '',
            'Passengers'                => 'Passagers',
            'Adult'                     => 'Adulte',
            'This booking was created:' => 'Cette réservation a été créée',
            'Ticket number'             => 'Numéro de ticket',
            'Total price'               => 'Prix total',
            //            'Used point' => '',
            'Taxes and carrier-imposed fees' => 'Taxes et frais imposés par le transporteur',
        ],
        'es' => [
            'Booking reference' => 'Referencia de reserva',
            //            'RouteNames' => '', // if not ends with ':' or '：'
            'Operated by:'  => 'Operado por:',
            'Aircraft'      => 'Avión',
            'Booking class' => 'Clase de tarifa',
            //            'Seat:' => '',
            //            'Terminal' => '',
            'Passengers'                => 'Pasajeros',
            'Adult'                     => 'Adulto',
            'This booking was created:' => 'Esta reserva fue creada',
            'Ticket number'             => 'Ticket number',
            'Total price'               => 'Precio total',
            //            'Used point' => '',
            'Taxes and carrier-imposed fees' => 'Tasas y recargos',
        ],
        'ru' => [
            'Booking reference' => 'Номер бронирования',
            'RouteNames'        => 'Рейс', // if not ends with ':' or '：'
            'Operated by:'      => 'Выполняется',
            'Aircraft'          => 'Самолет',
            'Booking class'     => 'Класс тарифа',
            //            'Seat:' => '',
            'Terminal'                  => 'Терминал',
            'Passengers'                => 'Пассажиры',
            'Adult'                     => 'Взрослый',
            'This booking was created:' => 'Это бронирование было создано:',
            'Ticket number'             => 'Номер билета',
            'Total price'               => 'Общая стоимость',
            //            'Used point' => '',
            'Taxes and carrier-imposed fees' => 'Аэропортовые таксы и взимаемые перевозчиком сборы',
        ],
        'it' => [
            'Booking reference' => 'Riferimento di prenotazione',
            //            'RouteNames' => '', // if not ends with ':' or '：'
            'Operated by:'  => 'Gestito da:',
            'Aircraft'      => 'Aeromobile',
            'Booking class' => 'Classe di viaggio',
            //            'Seat:' => '',
            //            'Terminal' => '',
            'Passengers'                => 'Passeggeri',
            'Adult'                     => 'Adulto',
            'This booking was created:' => 'Questa prenotazione è stata creata',
            'Ticket number'             => 'Numero biglietto',
            'Total price'               => 'Prezzo totale',
            //            'Used point' => '',
            'Taxes and carrier-imposed fees' => 'Tasse e costi di emissione',
        ],
        'fi' => [
            'Booking reference' => 'Varausnumero',
            //            'RouteNames' => '', // if not ends with ':' or '：'
            'Operated by:'  => 'Liikennöivä yhtiö:',
            'Aircraft'      => 'Konetyyppi',
            'Booking class' => 'Varausluokka',
            //            'Seat:' => '',
            //            'Terminal' => '',
            'Passengers'                => 'Matkustajat',
            'Adult'                     => 'Nuoriso',
            'This booking was created:' => 'Tämä varaus tehtiin:',
            'Ticket number'             => 'Lipun numero',
            'Total price'               => 'Kokonaishinta:',
            //            'Used point' => '',
            'Taxes and carrier-imposed fees' => 'Verot ja muut maksut',
        ],
        'sv' => [
            'Booking reference' => 'Bokningsreferens',
            //            'RouteNames' => '', // if not ends with ':' or '：'
            'Operated by:'  => 'Trafikeras av:',
            'Aircraft'      => 'Flygplanstyp:',
            'Booking class' => 'Bokningsklass',
            //            'Seat:' => '',
            //            'Terminal' => '',
            'Passengers'                     => 'Passagerare',
            'Adult'                          => ['Vuxen', 'Ungdom'],
            'This booking was created:'      => 'Bokningen gjordes:',
            'Ticket number'                  => 'Biljettnummer',
            'Total price'                    => 'Totalpris:',
            'Used point'                     => 'Använda poäng',
            'Taxes and carrier-imposed fees' => 'Skatter, avgifter och tillägg:',
        ],
        'de' => [
            'Booking reference' => 'Buchungsnummer',
            //            'RouteNames' => '', // if not ends with ':' or '：'
            //                        'Terminal' => '',
            'Operated by:'              => 'Durchgeführt von:',
            'Aircraft'                  => 'Flugzeug',
            'Booking class'             => 'Buchungsklasse',
            'Seat:'                     => 'Platz:',
            'Passengers'                => 'Passagiere',
            'Adult'                     => 'Erwachsener',
            'This booking was created:' => 'Diese Buchung wurde erstellt:',
            'Ticket number'             => 'Ticketnummer',
            'Total price'               => 'Gesamtpreis',
            //            'Used point' => '',
            'Taxes and carrier-imposed fees' => ['Welches folgende Steuern, Gebühren & SAS Serviceentgelt beinhaltet', 'Steuern, Gebühren & SAS Serviceentgelt'],
        ],
        'da' => [
            'Booking reference' => 'Bookingnummer',
            //            'RouteNames' => '', // if not ends with ':' or '：'
            'Operated by:'  => 'Opereres af:',
            'Aircraft'      => 'Flytype',
            'Booking class' => 'Prisklasse',
            //            'Seat:' => '',
            //            'Terminal' => '',
            'Passengers'                     => 'Rejsende',
            'Adult'                          => 'Voksen',
            'This booking was created:'      => 'Denne bestilling blev foretaget',
            'Ticket number'                  => 'Billetnummer',
            'Total price'                    => 'Totalpris',
            'Used point'                     => 'Anvendte point',
            'Taxes and carrier-imposed fees' => 'Skatter, afgifter og tillæg:',
        ],
        'no' => [
            'Booking reference' => 'Bestillingsreferanse',
            //            'RouteNames' => '', // if not ends with ':' or '：'
            'Terminal'      => 'Terminal',
            'Operated by:'  => 'Flys av:',
            'Aircraft'      => 'Flytype',
            'Booking class' => 'Pristype',
            'Seat:'         => 'Sete:',

            'Passengers'                => 'Reisende',
            'Adult'                     => ['Voksen', 'Barn', 'Ungdom'],

            'Total price'                    => 'Totalpris:',
            'Used point'                     => 'Poeng som blir belastet:',
            'Taxes and carrier-imposed fees' => 'Skatter, avgifter og tillegg:',

            'This booking was created:' => 'Denne bestillingen ble gjort',
            'Ticket number'             => 'Billettnummer',
        ],
        'en' => [
            //            'Booking reference' => '',
            //            'RouteNames' => '', // if not ends with ':' or '：'
            //            'Terminal' => '',
            //            'Operated by:' => '',
            //            'Aircraft' => '',
            //            'Booking class' => '',
            //            'Seat:' => '',

            //            'Passengers'                => '',
            //            'Adult'                     => '',

            //            'Used point' => '', // to translate
            //            'Total price' => '',
            'Taxes and carrier-imposed fees' => ['Taxes and carrier-imposed fees', 'Of which includes taxes and carrier-imposed fees:'],

            //            'This booking was created:' => '',
            //            'Ticket number'             => '',
        ],
        'zh' => [
            'Booking reference' => '机票参考号：',
            //            'RouteNames' => '', // if not ends with ':' or '：'
            'Terminal'      => '候机楼',
            'Operated by:'  => '运营商：',
            'Aircraft'      => '飞机：',
            'Booking class' => '舱位',
            //            'Seat:' => '',

            'Passengers'                => '乘客',
            'Adult'                     => '成人',

            //            'Used point' => '',
            'Total price'                    => '总价格',
            'Taxes and carrier-imposed fees' => ['税金，燃油附加费，手续费'],

            'This booking was created:' => '本次机票预订已被创建',
            'Ticket number'             => '票号',
        ],
        'ja' => [
            'Booking reference' => '予約番号：',
            //            'RouteNames' => '', // if not ends with ':' or '：'
            'Terminal'      => 'ターミナル',
            'Operated by:'  => '運航会社：',
            'Aircraft'      => '機種：',
            'Booking class' => '予約クラス：',
            //            'Seat:' => '',

            'Passengers'                => '旅客',
            'Adult'                     => '大人',

            //            'Used point' => '',
            'Total price'                    => '総額',
            'Taxes and carrier-imposed fees' => ['税, サーチャージ、手数料'],

            //            'This booking was created:' => '',
            'Ticket number'             => '航空券番号:',
        ],
    ];

    protected $dateStr = '';

    public function parseEmail(Email $email)
    {
        $f = $email->add()->flight();

        // General
        $f->general()
            ->confirmation(strtoupper($this->http->FindSingleNode("//text()[" . $this->starts($this->t("Booking reference")) . "]/following::text()[normalize-space()][1]",
                null, true, "/^\s*(\w{5,7})\s*$/")))
            ->travellers($this->http->FindNodes('//tr[ ./descendant::td[string-length(normalize-space(.))>1][1][' . $this->contains($this->t('Passengers')) . '] and not(.//tr) ]/following::tr[ not(.//tr) ]/td[string-length(normalize-space(.))>1][1][' . $this->contains($this->t('Adult')) . ']/descendant::*[name()="b" or name()="strong"]'), true)
        ;

        $bookingDate = $this->normalizeDate($this->http->FindSingleNode('//td[' . $this->contains($this->t('This booking was created:')) . ' and not(.//td)]', null, true, '/^' . $this->opt($this->t('This booking was created:')) . '[\s:]*(.+)$/'));

        if (!empty($bookingDate)) {
            $f->general()
                ->date($bookingDate);
        }

        // Issued
        $ticketsText = $this->http->FindSingleNode('//td[' . $this->contains($this->t('Ticket number')) . ' and not(.//td)]', null, true, '/^' . $this->opt($this->t('Ticket number')) . '\s*(.+)$/');
        $tickets = array_filter(array_map('trim', explode(',', $ticketsText)));

        if (!empty($tickets)) {
            $f->issued()->tickets($tickets, false);
        }

        // Program
        $accounts = array_filter($this->http->FindNodes("//tr[" . $this->starts($this->t('Passengers')) . "]/following::td[not(.//td)][starts-with(normalize-space(), 'EuroBonus ')]",
            null, "/^\s*EuroBonus\s+([A-Z]{3}\d{5,})\s*$/"));

        if (!empty($accounts)) {
            $f->program()
                ->accounts($accounts, false);
        }

        $xpath = "//text()[" . $this->contains($this->t("Operated by:")) . "]/ancestor::tr[1]/preceding-sibling::tr[1]";
//        $this->logger->debug('$xpath = '.print_r( $xpath,true));
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $flightText = $this->http->FindSingleNode("following-sibling::tr[1]", $root);

            $regex = '([A-Z]{2})?\s*(\d+)\s*' . $this->opt($this->t("Operated by:")) . '\s*:?\s*(.+?)\s+\|\s+' . $this->opt($this->t("Aircraft")) . '\s*:?\s*(.+)';

            if (preg_match("/$regex/u", $flightText, $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2])
                    ->operator($m[3])
                ;
                $s->extra()
                    ->aircraft($m[4]);
            }

            $date = $this->http->FindSingleNode("preceding::tr[not(.//tr) and count(td[normalize-space()]) = 2][td[normalize-space()][1][{$this->ends([':', '：'])}] and td[normalize-space()][2][contains(translate(., '0123456789', '##########'), '####')]][1]",
                $root, true, "/^\D+[:：]\s*(.+)/u");

            if (empty($date)) {
                $date = $this->http->FindSingleNode("preceding::tr[not(.//tr) and count(td[normalize-space()]) = 2][td[normalize-space()][1][{$this->eq($this->t("RouteNames"))}] and td[normalize-space()][2][contains(translate(., '0123456789', '##########'), '####')]][1]",
                    $root, true, "/^{$this->opt($this->t("RouteNames"))}[:：\s]*(.+)/u");
            }
            $date = $this->normalizeDate($date);

            $regex = '/(?<dTime>\d+[:：]\d+)\s+-\s+(?<aTime>\d+[:：]\d+)\s*\n\s*(?<dName>.+?)\s+-\s+(?<aName>.+)/u';
            $route = implode("\n", $this->http->FindNodes(".//td[not(.//td)]", $root));

            if (preg_match($regex, $route, $m)) {
                $reTerminal = '/(.{2,}?)\s*\(([^)(]*' . $this->t('Terminal') . '[^)(]*)\)/u'; // Stockholm, Arlanda (Terminal 5)

                if (preg_match($reTerminal, $m['dName'], $matches)) {
                    $m['dName'] = $matches[1];
                    $m['dTerminal'] = preg_replace('/\s*' . $this->t('Terminal') . '\s* /i', '', $matches[2]);
                }

                if (preg_match($reTerminal, $m['aName'], $matches)) {
                    $m['aName'] = $matches[1];
                    $m['aTerminal'] = preg_replace('/\s*' . $this->t('Terminal') . '\s* /i', '', $matches[2]);
                }

                $s->departure()
                    ->noCode()
                    ->name($m['dName'])
                    ->date(($date) ? strtotime(str_replace('：', ':', $m['dTime']), $date) : null)
                    ->terminal($m['dTerminal'] ?? null, false, true);

                $s->arrival()
                    ->noCode()
                    ->name($m['aName'])
                    ->date(($date) ? strtotime(str_replace('：', ':', $m['aTime']), $date) : null)
                    ->terminal($m['aTerminal'] ?? null, false, true);
            }

            $cabin = $this->http->FindSingleNode("./following-sibling::tr[normalize-space()][2][" . $this->contains($this->t("Booking class")) . "]",
                $root, true, "/{$this->opt($this->t("Booking class"))}[\s:]+(.+?)\s+\|/");

            if (!empty($cabin)) {
                $s->extra()
                    ->cabin($cabin);
            }

            $seats = $this->http->FindSingleNode("./following-sibling::tr[normalize-space()][2][" . $this->contains($this->t("Seat:")) . "][1]",
                $root, true, "/{$this->opt($this->t("Seat:"))}[:\s*]*(.+?)(?:\||$)/");

            if (!empty($seats) && preg_match_all('/(\b\d{1,3}[A-Z]\b)/', $seats, $ms)) {
                $s->extra()
                    ->seats($ms[1]);
            }
        }

        // Price
        $total = $this->http->FindSingleNode("//td[not(.//td)][" . $this->eq($this->t("Total price")) . "]/following-sibling::td[normalize-space()][1]");

        if (preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[A-Z]{3})\s*$#", $total, $m)) {
            $f->price()
                ->total(PriceHelper::parse($m['amount'], $m['currency']))
                ->currency($m['currency'])
            ;
        }
        $tax = $this->http->FindSingleNode("//td[not(.//td)][" . $this->eq($this->t("Taxes and carrier-imposed fees")) . "]/following-sibling::td[normalize-space()][1]");

        if (preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[A-Z]{3})\s*$#", $tax, $m)) {
            $f->price()
                ->tax(PriceHelper::parse($m['amount'], $m['currency']))
            ;
        }

        $spentAwards = $this->http->FindSingleNode("//td[not(.//td)][" . $this->eq($this->t("Used point")) . "]/following-sibling::td[normalize-space()][1]");

        if (!empty($spentAwards)) {
            $f->price()
                ->spentAwards($spentAwards);
        }
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@flysas.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ($phrases as $phrase) {
                if (stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $condition1 = $this->http->XPath->query('//node()[contains(normalize-space(.),"Scandinavian Airlines ©") or contains(.,"@flysas.com")]')->length === 0;
        $condition2 = $this->http->XPath->query('//a[contains(@href,"//www.flysas.com") or contains(@href,"//www.sas.") or contains(@href,"//flysas.ru") or contains(@href,"@flysas.com")]')->length === 0;

        if ($condition1 && $condition2) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        if ($this->assignLang() === false) {
            return false;
        }
        $result = parent::ParsePlanEmail($parser);
        $result['emailType'] = 'BookingHtml2012' . ucfirst($this->lang);

        return $result;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if ($this->assignLang() === false) {
            return false;
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $this->parseEmail($email);

        return $email;
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    protected function starts($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'starts-with(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    protected function contains($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'contains(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    protected function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) { return preg_quote($s, '/'); }, $field)) . ')';
    }

    protected function t($phrase)
    {
        if (!isset(self::$dict[$this->lang]) || !isset(self::$dict[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dict[$this->lang][$phrase];
    }

    protected function assignLang(): bool
    {
        foreach ($this->langDetectors as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if ($this->http->XPath->query('//node()[contains(normalize-space(.),"' . $phrase . '")]')->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function normalizeDate($date)
    {
//        $this->logger->debug('$date = '.print_r( $date,true));
        $in = [
            // ma 24 feb 2014
            "/^\s*\S+[\s,]+(\d{1,2})\s*([[:alpha:]]+)[\.]?\s*(\d{4})\s*$/u",
            //  09 desember 2013, 21:00 GMT
            "/^\s*(\d{1,2})\s+([[:alpha:]]+)[\.]?\s+(\d{4})\s*,?\s+(\d{1,2}:\d{2})(?:\s*[A-Z]{3,4})?\s*$/u",
        ];
        $out = [
            '$1 $2 $3',
            '$1 $2 $3, $4',
        ];
        $date = preg_replace($in, $out, $date);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        return strtotime($date);
    }

    private function ends($field, $source = 'normalize-space()')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }
        $rules = [];

        foreach ($field as $f) {
            $len = mb_strlen($f);

            if ($len > 0) {
                $rule = "substring({$source},string-length({$source})+1-{$len},{$len})='{$f}'";
                $rules[] = $rule;
            }
        }

        if (count($rules) == 0) {
            return 'false()';
        }

        return implode(' or ', $rules);
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) { return "normalize-space(.)=\"{$s}\""; }, $field));
    }
}
