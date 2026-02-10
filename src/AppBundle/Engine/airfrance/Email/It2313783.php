<?php

namespace AwardWallet\Engine\airfrance\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class It2313783 extends \TAccountCheckerExtended
{
    public $mailFiles = "airfrance/it-2313783.eml, airfrance/it-4041254.eml, airfrance/it-4271556.eml, airfrance/it-5443579.eml, airfrance/it-56603536.eml";

    public $reFrom = "@airfrance.com";
    public $reSubject = [
        "en"=> "Flying Blue booking confirmation email",
        "nl"=> "Bevestigende e-mail van Flying Blue",
        "de"=> "Flying Blue Bestätigungsmail",
        "es"=> "Mensaje electrónico de confirmación Flying Blue",
    ];
    public $reBody = 'airfrance';
    public $reBody2 = [
        "en"=> "Flights",
        "nl"=> "Gekozen vluchten",
        "de"=> "Ausgewählte Flüge",
        "es"=> "Vuelos elegidos",
        "it"=> "Voli scelti",
        "jp"=> "エールフランスのサイトでご予約を承りました。有難うございます",
    ];

    private static $dictionary = [
        'en' => [
        ],
        'nl' => [
            "ConfNumber"   => "Boekingsnummer",
            "Status"       => "Status",
            "Passengers"   => "Passagiers",
            "Ticketnumber" => "Ticketnummer",
            "Total online" => "Totaal online betaalde bedrag",
            "Taxes"        => "Taksen",
            "Cabin"        => "Reisklass",
            "Duration"     => "Duur",
            "Meal"         => "Maaltijd(en) aan boord",
            "Operated By"  => "Uitgevoerd op",
            "Terminal"     => "Terminal",
            //            "Account Number" => ""
            "Spent Awards" => "Prijs van uw ticket",
        ],
        'de' => [
            "ConfNumber"     => "Buchungscode",
            "Status"         => "Buchungsstatus",
            "Passengers"     => "Passagiere",
            "Ticketnumber"   => "Ticketnummer(n)",
            "Total online"   => "Online gezahlter Betrag",
            "Taxes"          => "Steuern",
            "Cabin"          => "Reiseklasse",
            "Duration"       => "Flugzeit",
            "Meal"           => "Mahlzeit(en) an Bord",
            "Operated By"    => "Durchgeführt von",
            "Terminal"       => "Terminal",
            "Account Number" => "Nummer Ihrer Vielfliegerkarte",
            "Spent Awards"   => "Erforderlicher Meilenbetrag",
        ],
        'es' => [
            "ConfNumber"     => "Referencia de su expediente de reserva",
            "Status"         => "Estado",
            "Passengers"     => "Pasajeros",
            "Ticketnumber"   => "Número(s) de billete(s)",
            "Total online"   => "Importe total pagado online",
            "Taxes"          => "Tasas",
            "Cabin"          => "Clase",
            "Duration"       => "Duración del vuelo",
            "Meal"           => "Comida(s) servida(s) a bordo",
            "Operated By"    => "Operado por",
            "Terminal"       => "Terminal",
            "Account Number" => "Número de la tarjeta de fidelidad",
            "Spent Awards"   => "Precio del billete",
        ],
        'it' => [
            "ConfNumber"     => "Codice del dossier di prenotazione",
            "Status"         => "Stato",
            "Passengers"     => "Passeggeri",
            "Ticketnumber"   => "N° di biglietto",
            "Total online"   => "Dettagli del prezzo",
            "Taxes"          => "Le modifiche richieste implicano un pagamento aggiuntivo di",
            "Cabin"          => "Classe",
            "Duration"       => "Durata del volo",
            "Meal"           => "Pasto/i servito/i a bordo",
            "Operated By"    => "Operato da",
            "Terminal"       => "Terminal",
            "Account Number" => "Numero della sua carta di fedeltà",
            //            "SpentAwards" => ""
        ],
        'jp' => [
            "ConfNumber"     => "お客様のご予約番号",
            "Status"         => "状況",
            "Passengers"     => "搭乗者",
            "Ticketnumber"   => "航空券番号",
            "Total online"   => "オンラインでの合計支払金額",
            "Taxes"          => "税およびサーチャージ",
            "Cabin"          => "クラス",
            "Duration"       => "飛行時間",
            "Meal"           => "機内食",
            "Operated By"    => "運航航空会社",
            "Terminal"       => "ターミナル",
            "Account Number" => "カード番号",
            "Spent Awards"   => "マイル支払額",
        ],
    ];

    private $lang;

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $flight = $email->add()->flight();

        $confNumber = $this->http->FindSingleNode("//text()[{$this->starts($this->t('ConfNumber'))}]/following::text()[normalize-space()][1]");
        $description = $this->http->FindSingleNode("//text()[{$this->starts($this->t('ConfNumber'))}]", null, true, '/(.+\S)\s?[:]/');
        $status = $this->http->FindSingleNode("//td[{$this->starts($this->t('ConfNumber'))}]/following-sibling::td[{$this->starts($this->t('Status'))}]/descendant::text()[normalize-space()][2]");
        $travellers = $this->http->FindNodes("//text()[{$this->starts($this->t('Ticketnumber'))}]/preceding::text()[normalize-space()][1]/ancestor::td[normalize-space()][1]");

        $flight->general()
            ->confirmation($confNumber, $description)
            ->status($status)
            ->travellers($travellers, true);

        $tickets = $this->http->FindNodes("//text()[{$this->starts($this->t('Ticketnumber'))}]/following::text()[normalize-space()][1]", null, '/(\d+)/');
        $flight->issued()
            ->tickets($tickets, false);

        $accountNumber = $this->http->FindNodes("//text()[{$this->starts($this->t('Account Number'))}]/ancestor::td[1]/following::td[1]", null, '/(\d+)/');

        if (count($accountNumber) > 0 & !empty($accountNumber[0])) {
            $flight->setAccountNumbers($accountNumber, false);
        }

        $taxes = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Taxes'))}]//following::text()[normalize-space()][1]");
        $total = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Total online'))}]//following::text()[normalize-space()][1]");
        $currency = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Total online'))}]//following::text()[normalize-space()][2]", null, true, '/([A-Z]{3})/');

        $flight->price()
            ->tax($this->normalizePrice($taxes))
            ->total($this->normalizePrice($total))
            ->currency($currency);

        $spentAwards = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Spent Awards'))}]/ancestor::td[1]/following::td[1]");

        if (!empty($spentAwards)) {
            $flight->price()->spentAwards($spentAwards);
        }

        $xpath = "//tr[{$this->starts($this->t('Cabin'))}]/preceding::tr[1]";
        $roots = $this->http->XPath->query($xpath);

        foreach ($roots as $root) {
            $segment = $flight->addSegment();

            $depDate = $this->http->FindSingleNode("./preceding::text()[normalize-space()][1]", $root);
            $depTime = str_replace(';', '', $this->http->FindSingleNode("./descendant::td[1]", $root, true, '/([\d\:\;?]+)/'));
            $depCode = $this->http->FindSingleNode("./descendant::td[2]", $root, true, '/[(]([A-Z]{3})[)]/');
            $arrTime = str_replace(';', '', $this->http->FindSingleNode("./descendant::td[4]", $root, true, '/([\d\:\;?]+)/'));
            $arrCode = $this->http->FindSingleNode("./descendant::td[5]", $root, true, '/[(]([A-Z]{3})[)]/');

            if ($arrTime < $depTime) {
                $arrDate = strtotime('+1 days', $this->normalizeDate($depDate . ', ' . $arrTime));
            } else {
                $arrDate = $this->normalizeDate($depDate . ', ' . $arrTime);
            }

            $depDate = $this->normalizeDate($depDate . ', ' . $depTime);

            $segment->departure()
                ->code($depCode)
                ->date($depDate);

            $segment->arrival()
                ->code($arrCode)
                ->date($arrDate);

            $name = $this->http->FindSingleNode("./descendant::td[6]", $root, true, '/([A-Z]{2})\d+/');
            $number = $this->http->FindSingleNode("./descendant::td[6]", $root, true, '/[A-Z]{2}(\d{2,4})/');
            $operator = $this->http->FindSingleNode("./following::tr[{$this->starts($this->t('Operated By'))}][1]/descendant::td[1]", $root, true, '/[:][;]?(\D+)/');

            $segment->airline()
                ->name($name)
                ->number($number)
                ->operator($operator);

            $duration = $this->http->FindSingleNode("./following::td[{$this->starts($this->t('Duration'))}][1]", $root, true, '/[:][;]?(.+)/');
            $cabin = $this->http->FindSingleNode("./following::tr[{$this->starts($this->t('Cabin'))}][1]/descendant::td[1]/descendant::text()[normalize-space()][2]", $root);
            $meal = $this->http->FindSingleNode("./following::td[{$this->starts($this->t('Meal'))}][1]", $root, true, '/[:][;]?(\D+)/');
            $aircraft = $this->http->FindSingleNode("./descendant::td[6]", $root, true, '/[(](.+)[)]/u');

            $segment->extra()
                ->duration($duration)
                ->cabin($cabin);

            if (!empty($aircraft)) {
                $segment->extra()
                    ->aircraft($aircraft);
            }

            if (!empty($meal)) {
                $segment->extra()
                    ->meal($meal);
            }

            $seats = $this->http->FindNodes("//tr[starts-with(normalize-space(), '" . $depCode . "')]/descendant::td[4]", null, '/(\d{1,2}\D)/');

            if (count($seats) > 0 & (!empty($seats[0]))) {
                $segment->extra()
                    ->seats($seats);
            }

            $terminal = $this->http->FindSingleNode("./following::td[{$this->starts($this->t('Terminal'))}][1]", $root, true, '/[:][;]?(.+)/');

            if (!empty($terminal)) {
                $segment->setArrTerminal($terminal);
            }
        }
    }

    /*function processors()
    {
        return array(


            "#.*?#" => array(

                "ItinerariesSplitter" => function($text = '', $node = null, $it = null){
                    $dict = [
                        "Aircraft" => ["Aircraft", "Vliegtuigtype", "Aeromobile", "Flugzeug", "Avión"],
                    ];
                    $checkFormat = "//text()[{$this->starts($dict["Aircraft"])}]/ancestor::td[1]";
                    if ($this->http->XPath->query($checkFormat)->length > 0) {
                        $this->logger->debug('format ConfirmationOfYourBooking');
                        return null;
                    }

                    return [$text];
                },

                "#.*?#" => array(

                    "Kind" => function($text = '', $node = null, $it = null){
                        return "T";
                    },

                    "RecordLocator" => function($text = '', $node = null, $it = null){
                        return orval(
                            reni('Boekingsnummer: : (\w+)'),
                            reni('Buchungscode : (\w+)'),
                            reni('Referencia de su expediente de reserva : (\w+)'),
                            reni('Codice del dossier di prenotazione : (\w+)'),
                            reni('Your reservation reference number : (\w+)')
                        );
                    },

                    "Passengers" => function($text = '', $node = null, $it = null){
                        $ppl = nodes("//*[
                            contains(text(), 'Ticketnummer') or
                            contains(text(), 'Número(s) de billete(s)') or
                            contains(text(), 'N° di biglietto') or
                            contains(text(), 'Ticket number')
                        ]/preceding::table[1]");
                        return nice($ppl);
                    },

                    "TotalCharge" => function($text = '', $node = null, $it = null){
                        $x = cell(['Totaal online', 'Online gezahlter Betrag', 'Importe total pagado online', 'Total amount paid online'], +1);
                        return total($x);
                    },

                    "Tax" => function($text = '', $node = null, $it = null){
                        $x = cell(['Taksen', 'Gebühren', 'Tasas', 'Taxes'], +1);
                        return cost($x);
                    },

                    "SpentAwards" => function($text = '', $node = null, $it = null){
                        $x = cell(['Prijs van uw ticket', 'Erforderlicher Meilenbetrag', 'Precio del billete', 'Miles debited'], +1);
                        return nice($x);
                    },

                    "Status" => function($text = '', $node = null, $it = null){
                        if (reni('Status  : bevestigd') || 	reni('Estado  : Confirmada') || reni('Stato  : Confirmé') || reni('Status  : Confirmed'))
                            return 'confirmed';
                    },

                    "SegmentsSplitter" => function($text = '', $node = null, $it = null){
                        $info = rew('
                            (?:Gekozen vluchten|Ausgewählte Flüge|Vuelos elegidos|Voli scelti|Flights information)
                            (.+?)
                            (?:Passagiers|Passagiere|Pasajeros|Passeggeri|Passengers)
                        ');
                        return splitter("#(\w+\s+\d+\s+\w+\s+\d{4})#", $info);
                    },

                    "TripSegments" => array(

                        "FlightNumber" => function($text = '', $node = null, $it = null){
                            $fl = reni('\s+([A-Z]{2}\d+)');
                            return uberAir($fl);
                        },

                        "DepCode" => function($text = '', $node = null, $it = null){
                            return reni('\( (\w+) \)');
                        },

                        "DepDate" => function($text = '', $node = null, $it = null){
                            $date = en(uberDate(1));
                            $date = totime($date);

                            $time1 = reni('(\d{2}:\d{2})');
                            $time2 = reni(':\d{2} .*? (\d{2}:\d{2})');

                            $dt1 = strtotime($time1, $date);
                            $dt2 = date_carry($time2, $dt1);
                            return [
                                'DepDate' => $dt1,
                                'ArrDate' => $dt2,
                            ];
                        },

                        "ArrCode" => function($text = '', $node = null, $it = null){
                            return reni('
                                \( (?:\w+) \) .*?
                                \( (\w+) \)
                            ');
                        },

                        "Cabin" => function($text = '', $node = null, $it = null){
                            return reni('(?:Reisklasse|Reiseklasse|Clase|Classe) : (\w+)');
                        },
                        "BookingClass" => function($text = '', $node = null, $it = null){
                            return reni('(?:Booking class) : (\w+)');
                        },

                        "Duration" => function($text = '', $node = null, $it = null){
                            return reni('(?:Duur|Flugzeit|Duración del vuelo|Durata del volo|Duration) : (\d+ h \d+)');
                        },

                        "Meal" => function($text = '', $node = null, $it = null){
                            return reni('(?:Maaltijd\(en\) aan boord|Mahlzeit\(en\) an Bord|Comida\(s\) servida\(s\) a bordo|Pasto/i servito/i a bordo|Meal\(s\)) : (\w+)');
                        },
                    ),
                ),
            ),

        );
    }*/

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (strpos($headers["from"], $this->reFrom) === false) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            if (strpos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (strpos($body, $this->reBody) === false) {
            return false;
        }

        foreach ($this->reBody2 as $lang => $re) {
            if (stripos($body, $re) !== false) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    public static function getEmailTypesCount()
    {
        return 4;
    }

    public static function getEmailLanguages()
    {
        return ["nl", "de", "es", "it"];
    }

    public function IsEmailAggregator()
    {
        return false;
    }

    protected function normalizePrice($string)
    {
        $string = preg_replace('/\s+/', '', $string);			// 11 507.00	->	11507.00
        $string = preg_replace('/[,.](\d{3})/', '$1', $string);	// 2,790		->	2790		or	4.100,00	->	4100,00
        $string = preg_replace('/,(\d{2})$/', '.$1', $string);	// 18800,00		->	18800.00

        return $string;
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "starts-with(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) { return "contains(normalize-space(.), \"{$s}\")"; }, $field));
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dictionary[$this->lang][$s])) {
            return $s;
        }

        return self::$dictionary[$this->lang][$s];
    }

    private function normalizeDate($str)
    {
        $in = [
            "#^\w+\s*(\d+)\s*(\w+)\s*(\d{4})[,]\s*([\d\:]+)$#u", // Donderdag 13 Maart 2014, 20:00
            "#^(\d{4})\D+(\d+)\D+(\d+)\D\s*\D+([\d\:]+)$#u", // 2019年1月27日 日曜日, 15:05
        ];
        $out = [
            "$1 $2 $3, $4",
            "$3-$2-$1, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }
}
