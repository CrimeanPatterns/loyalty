<?php

namespace AwardWallet\Engine\edreams\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Booking extends \TAccountChecker
{
    public $mailFiles = "edreams/it-12471392.eml, edreams/it-562543319.eml, edreams/it-564243631.eml, edreams/it-565125890.eml, edreams/it-626090432.eml, edreams/it-626398318.eml, edreams/it-626522095.eml, edreams/it-626620652.eml, edreams/it-641425691.eml, edreams/it-661130610.eml, edreams/it-678070424.eml, edreams/it-701775758.eml";
    public $subjects = [
        'Booking successful: eDreams Ref. #',
    ];

    public $lang = 'en';
    public $year;
    public $lastDate;
    public $confs = [];

    public static $dictionary = [
        "en" => [
            'Success! We got your booking' => [
                'Success! We got your booking',
                'We got your booking!',
                'The airline has informed us that',
                'your booking has been processed successfully',
            ],
            'Traveller'                    => 'PASSENGER',
            'Total'                        => ['Total price:', 'The total cost of your reservation is:'],
            'Departure'                    => ['Departure', 'Flight 1'],
            'Return'                       => ['Return', 'Flight 2'],
        ],

        "zh" => [
            'Success! We got your booking' => ['部分航空公司會透過我們的應用程式提供提前報到', '預訂成功'],
            'Departure'                    => '航班',
            "Who's going?"                 => '乘客身分？',
            'Age:'                         => '年齡：',
            'booking reference:'           => '預訂參考編號：',
            //'Connection:' => '',
            //'Operated by' => '',
            'Airline reference' => '航空公司參考編號',
            'Traveller'         => '旅客',
            'Terminal '         => '航廈',
            'Total'             => '您的預訂費用總計為:',
        ],

        "es" => [
            'Success! We got your booking' => ['Reserva confirmada', 'Reserva realizada correctamente', '¡Todo listo! Tenemos tu reserva'],
            'Departure'                    => ['Ida', 'Salida'],
            'Return'                       => 'Vuelta',
            "Who's going?"                 => ['Pasajeros', '¿Quiénes viajan?'],
            'Age:'                         => 'Edad:',
            'booking reference:'           => ['Localizador de la reserva de eDreams:', 'Referencia de la reserva de'],
            'Connection:'                  => 'Conexión:',
            //'Operated by' => '',
            'Airline reference' => ['Localizador de la aerolínea', 'Referencia de aerolínea'],
            'Traveller'         => 'PASAJERO',
            'Terminal '         => 'Terminal',
            'Total'             => 'Coste total de tu reserva:',
            'Customer details'  => '¿Qué más necesitas?',
        ],

        "nl" => [
            'Success! We got your booking' => ['je boeking is succesvol afgerond', 'Gelukt! We hebben je boeking'],
            'Departure'                    => 'Vertrek',
            'Return'                       => 'Terugreis',
            "Who's going?"                 => 'Wie gaat er mee?',
            'Age:'                         => 'Leeftijd:',
            'booking reference:'           => 'eDreams-reserveringsnummer:',
            //'Connection:' => '',
            //'Operated by' => '',
            'Airline reference' => 'Referentie van luchtvaartmaatschappij',
            'Traveller'         => 'PASSAGIER',
            'Terminal '         => 'Terminal',
            'Total'             => 'De totale prijs van uw reservering is:',
            'Customer details'  => 'Heb je nog iets anders nodig?',
        ],

        "fr" => [
            'Success! We got your booking' => ['Réservation confirmée', 'Bravo ! Nous avons bien reçu votre réservation', 'Votre nouvel itinéraire'],
            'Departure'                    => ['Aller', 'Départ'],
            'Return'                       => 'Retour',
            "Who's going?"                 => 'Votre voyage est prêt ?',
            'Age:'                         => 'Âge :',
            'booking reference:'           => ['Référence de réservation eDreams :', 'Référence de réservation Opodo :'],
            'Connection:'                  => 'Correspondance :',
            //'Operated by' => '',
            'Airline reference' => 'Nº de référence de la compagnie aérienne',
            'Traveller'         => ['PASSAGER', 'Passagers'],
            'Terminal '         => 'Terminal',
            'Total'             => 'Le coût total de votre réservation est de :',
            'Customer details'  => "Besoin d'aide ?",
        ],

        "de" => [
            'Success! We got your booking' => ['Buchung bestätigt'],
            'Departure'                    => ['Hinflug', '1. Flug'],
            'Return'                       => ['Rückflug', '2. Flug'],
            "Who's going?"                 => 'Reisende',
            'Age:'                         => 'Alter:',
            'booking reference:'           => '-Buchungsnummer:',
            'Connection:'                  => 'Verbindung:',
            'Connection'                   => 'Verbindung',
            //'Operated by' => '',
            'Airline reference' => ['Buchungsnummer der Fluggesellschaft'],
            'Traveller'         => 'REISENDER',
            'Terminal '         => 'Terminal',
            'Total'             => 'Der Gesamtpreis Ihrer Buchung ist:',
            'Customer details'  => "Zahlungsinformationen",
            'discount'          => 'angewandt:',
        ],

        "pt" => [
            'Success! We got your booking' => ['Fantástico! Temos a tua reserva', 'A tua reserva está confirmada', 'A tua reserva está parcialmente confirmada'],
            'Departure'                    => 'Partida',
            'Return'                       => 'Regresso',
            "Who's going?"                 => 'Quem vai?',
            'Age:'                         => 'Idade:',
            'booking reference:'           => 'Referência da reserva da eDreams:',
            'Connection:'                  => 'Conexão:',
            'Operated by'                  => 'Operado por',
            //'Airline reference' => '',
            'Traveller'         => 'PASSAGEIRO',
            'Terminal '         => 'Terminal',
            'Total'             => 'O custo total da tua reserva é de:',
            //'Customer details'  => "",
            //'discount'          => '',
        ],

        "it" => [
            'Success! We got your booking' => ['Ottimo! Abbiamo ricevuto la tua prenotazione',
                'La tua prenotazione è confermata', 'è stata apportata una piccola modifica alla tua prenotazione', ],
            'Departure'                    => ['Andata', 'Partenza'],
            'Return'                       => 'Ritorno',
            "Who's going?"                 => ['Chi viaggia?', 'Tutto pronto per il viaggio?'],
            'Age:'                         => 'Età:',
            'booking reference:'           => 'Numero di prenotazione eDreams:',
            'Connection:'                  => 'Scalo:',
            //'Operated by'                  => '',
            'Airline reference' => 'Nº di prenotazione della compagnia aerea',
            'Traveller'         => 'PASSEGGERO',
            'Terminal '         => 'Terminal',
            'Total'             => 'Il costo totale della tua prenotazione è:',
            //'Customer details'  => "",
            //'discount'          => '',
        ],
    ];

    public $detectLang = [
        "en" => ['Departure'],
        "zh" => ['航班'],
        "pt" => ['Partida'],
        "es" => ['Tu itinerario', 'Reserva realizada correctamente', 'Reserva confirmada', 'Referencia de aerolínea', 'Referencia de la reserva de'],
        "nl" => ['Vertrek'],
        "fr" => ['Réservation confirmée', 'Départ'],
        "de" => ['Buchung bestätigt'],
        "it" => ['Andata', 'Tutto pronto per il viaggio'],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@mailer.edreams.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (stripos($headers['subject'], $subject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $this->assignLang();

        return $this->http->XPath->query("//text()[contains(normalize-space(), 'eDreams')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Success! We got your booking'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Departure'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]mailer\.edreams\.com$/', $from) > 0;
    }

    public function ParseFlight(Email $email)
    {
        $f = $email->add()->flight();

        $travellers = $this->http->FindNodes("//text()[" . $this->eq($this->t("Who's going?")) . "]/following::table[" . $this->contains($this->t("Age:")) . "][1]//text()[" . $this->eq($this->t("Age:")) . "]/preceding::text()[normalize-space()][1]/ancestor::td[1]");

        if (count($travellers) === 0) {
            $travellers = $this->http->FindNodes("//text()[" . $this->contains($this->t("Traveller")) . "]/following::text()[normalize-space()][1]");
        }

        if ($travellers) {
            $f->general()->travellers($travellers, true);
        }

        $nodes = $this->http->XPath->query("//text()[{$this->eq($this->t('Departure'))} or {$this->eq($this->t('Return'))} or {$this->eq($this->t('Connection:'))}]");

        if ($nodes->length === 0) {
            $nodes = $this->http->XPath->query("//img[contains(@src, 'icon-flight')]/ancestor::tr[1]/descendant::text()[contains(normalize-space(), '航班')][1]");
        }

        foreach ($nodes as $root) {
            if ($this->http->XPath->query("./ancestor::table[2]/descendant::text()[{$this->contains($this->t('Connection'))}]", $root)->length === 0) {
                $this->ParseSegment1($f, $root);
            }

            if ($this->http->XPath->query("./ancestor::table[2]/descendant::text()[{$this->contains($this->t('Connection'))}]", $root)->length > 0) {
                $this->ParseSegment2($f, $root);
            }
        }

        if (count($this->confs) > 0) {
            foreach (array_unique($this->confs) as $conf) {
                $f->general()
                    ->confirmation($conf);
            }
        } elseif ($this->http->XPath->query("//text()[{$this->contains($this->t('Success! We got your booking'))}]")->length > 0) {
            $f->general()
                ->noConfirmation();
        } elseif (count($this->confs) === 0 && $this->http->XPath->query("//text()[{$this->contains($this->t('Airline reference'))}]")->length === 0) {
            $f->general()
                ->noConfirmation();
        }

        $price = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Total'))}]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Total'))}\s*(\D*\s*[\d\.\,]+\D*)/");

        if (preg_match("/^(?<currency>\D{1,3})\s*(?<total>[\d\.\,]+)$/", $price, $m)
        || preg_match("/^(?<total>[\d\.\,]+)\s*(?<currency>\D{1,3})$/", $price, $m)) {
            $currency = $this->normalizeCurrency($m['currency']);
            $f->price()
                ->total(PriceHelper::parse($m['total'], $currency))
                ->currency($currency);

            $discount = $this->http->FindSingleNode("//text()[{$this->contains($this->t('discount'))}]", null, true, "/\s*\-([\d\.\,]+)/");

            if (!empty($discount)) {
                $f->price()
                    ->discount(PriceHelper::parse($discount, $currency));
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();
        $date = strtotime($parser->getDate());
        $this->year = date('Y', $date);

        $email->obtainTravelAgency(); // because eDreams is travel agency

        $confirmationTitle = $this->http->FindSingleNode("//text()[{$this->contains($this->t('booking reference:'))}][1]");

        if (empty($confirmationTitle)) {
            $confirmationTitle = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Airline reference'))}]/preceding::text()[{$this->contains($this->t('booking reference:'))}][1]");
        }
        $confirmation = array_filter($this->http->FindNodes("//text()[{$this->contains($this->t('booking reference:'))}]/following::text()[normalize-space(.)][1]", null, '/^(\d{7,})(?:\s.*)?$/'));
        $confirmation = array_shift($confirmation);
        $email->ota()->confirmation($confirmation, preg_replace('/\s*:\s*$/', '', $confirmationTitle));

        $this->ParseFlight($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function ParseSegment1(\AwardWallet\Schema\Parser\Common\Flight $f, \DOMNode $root)
    {
        $this->logger->debug(__METHOD__);
        $s = $f->addSegment();

        $this->confs = array_filter($this->http->FindNodes("//text()[{$this->contains($this->t('Airline reference'))}]/following::text()[normalize-space()][1]", null, "/\s*([A-Z\d]{5,6})\s*$/"));

        $text = implode("\n", $this->http->FindNodes("./following::text()[normalize-space()]", $root));

        if (stripos($text, $this->t('Customer details')) !== false && !preg_match("/{$this->opt($this->t('Airline reference'))}/", $text)) {
            $f->removeSegment($s);
        }

        $duration = $this->http->FindSingleNode("./following::img[contains(@src, 'time')][1]/ancestor::td[1]", $root);

        if (!empty($duration)) {
            $s->setDuration($duration);

            $depDate = $this->http->FindSingleNode("./preceding::text()[string-length()>3][1]", $root);

            if (preg_match("/\s+\d{1,2}\s+/", $depDate)) {
                $this->lastDate = $depDate;
            } else {
                $depDate = $this->lastDate;
            }

            $depText = implode("\n", $this->http->FindNodes("./ancestor::table[1]/following::table[1]/descendant::text()[{$this->eq($duration)}][1]/preceding::text()[contains(normalize-space(), ':')][1]/ancestor::tr[1]/descendant::text()[normalize-space()]", $root));
            $changeOrder = false;

            if (empty($depText)) {
                $duration = strtoupper($duration);
                $changeOrder = true;
                $depText = implode("\n", $this->http->FindNodes("./ancestor::table[1]/following::table[1]/descendant::text()[{$this->eq($duration)}][1]/preceding::text()[contains(normalize-space(), ':')][1]/ancestor::tr[1]/descendant::text()[normalize-space()]", $root));
            }

            if (empty($depText)) {
                $depText = implode("\n", $this->http->FindNodes("./ancestor::table[1]/following::table[1]/descendant::text()[contains(normalize-space(), 'H ') or contains(normalize-space(), 'h ')][1]/preceding::text()[contains(normalize-space(), ':')][1]/ancestor::tr[1]/descendant::text()[normalize-space()]", $root));
            }

            if (preg_match("/\s+[·]\s+(?<aName>(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]))\s*(?<fNumber>\d{1,4})\n/u", $depText, $m)) {
                $s->airline()
                    ->name($m['aName'])
                    ->number($m['fNumber']);

                if ($this->http->XPath->query("./ancestor::table[1]/following::table[1]/descendant::text()[contains(normalize-space(), 'H ') or contains(normalize-space(), 'h ')][1]/ancestor::table[2][{$this->contains($m['aName'] . ' ' . $m['fNumber'])}]/preceding::text()[normalize-space()][1][normalize-space()='Recusada']", $root)->length > 0) {
                    $f->removeSegment($s);

                    return;
                }
            }

            $depTerminal = $this->re("/[·]\n*{$this->t('Terminal ')}\s*(?<terminal>.+)\n.*[·]\s+(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*\d{1,4}/u", $depText);

            if (!empty($depTerminal)) {
                $s->departure()
                    ->terminal(str_replace($this->t('Terminal '), '', $depTerminal));
            }

            if (preg_match("/^(?<depTime>[\d\:]+)\n(?<depName>(?:.+\n){1,2})(?<depCode>[A-Z]{3})/", $depText, $m)) {
                $s->departure()
                    ->name(str_replace("\n", " ", $m['depName']))
                    ->date($this->normalizeDate($depDate . ' ' . $this->year . ', ' . $m['depTime']))
                    ->code($m['depCode']);
            }

            $cabin = $this->re("/$duration\n(?<cabin>.+)$/", $depText);

            if (!empty($cabin)) {
                $s->setCabin($cabin);
            }

            $arrText = implode("\n", $this->http->FindNodes("./ancestor::table[1]/following::table[1]/descendant::text()[{$this->eq($duration)}][1]/following::text()[contains(normalize-space(), ':')][2]/ancestor::tr[1]/descendant::text()[normalize-space()]", $root));

            if ($changeOrder === true) {
                $arrText = implode("\n", $this->http->FindNodes("./ancestor::table[1]/following::table[1]/descendant::text()[{$this->eq($duration)}][1]/following::text()[contains(normalize-space(), ':')][1]/ancestor::tr[1]/descendant::text()[normalize-space()]", $root));
            }

            if (empty($arrText)) {
                $arrText = implode("\n", $this->http->FindNodes("./ancestor::table[1]/following::table[1]/descendant::text()[contains(normalize-space(), 'H ') or contains(normalize-space(), 'h ')][1]/following::text()[contains(normalize-space(), ':')][2]/ancestor::tr[1]/descendant::text()[normalize-space()]", $root));
            }

            if (preg_match("/^(?<arrTime>[\d\:]+)\n(?<aName>.*\))\s*(?<arrCode>[A-Z]{3})/su", $arrText, $m)
            || preg_match("/^(?<arrTime>[\d\:]+)\n(?<aName>.*)(?<arrCode>[A-Z]{3})/su", $arrText, $m)) {
                $s->arrival()
                    ->name(str_replace("\n", " ", $m['aName']))
                    ->date($this->normalizeDate($depDate . ' ' . $this->year . ', ' . $m['arrTime']));

                if ($s->getDepCode() !== $m['arrCode']) {
                    $s->arrival()
                        ->code($m['arrCode']);
                } else {
                    $s->arrival()
                        ->noCode();
                }
            }

            $arrTerminal = $this->re("/\n*[·]\n*{$this->t('Terminal ')}\s*(?<arrTerminal>.+)$/u", $arrText);

            if (!empty($arrTerminal)) {
                $s->arrival()
                    ->terminal($arrTerminal);
            }
        }

        if (empty($s->getDepCode()) && empty($s->getAirlineName()) && empty($s->getAirlineName()) && empty($s->getArrCode()) && empty($s->getDepDate()) && empty($s->getArrDate())) {
            $f->removeSegment($s);
        }
    }

    public function ParseSegment2(\AwardWallet\Schema\Parser\Common\Flight $f, \DOMNode $root)
    {
        $this->logger->debug(__METHOD__);
        $s = $f->addSegment();

        $depDate = $this->http->FindSingleNode("./preceding::text()[normalize-space()][1]", $root, true, "/^(\w+\s+\d+\s+\w+)$/");

        if (!empty($depDate)) {
            $this->lastDate = $depDate;
        } else {
            $depDate = $this->lastDate;
        }

        $depText = implode("\n", $this->http->FindNodes("./following::text()[contains(normalize-space(), '·')][1]/ancestor::tr[3]/descendant::text()[normalize-space()]", $root));

        if (!preg_match("/{$this->opt($this->t('Airline reference'))}/", $depText)) {
            $depText = implode("\n", $this->http->FindNodes("./following::text()[contains(normalize-space(), '·')][1]/ancestor::tr[4]/descendant::text()[normalize-space()]", $root));
        }

        $conf = $this->re("/{$this->opt($this->t('Airline reference'))}\s*([A-Z\d]{5,6})/su", $depText);

        if (!empty($conf)) {
            $this->confs[] = $conf;
        }

        if (preg_match("/\s+[·]\s+(?<aName>(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]))\s*(?<fNumber>\d{1,4})(?:\n|\s)/u", $depText, $m)) {
            $s->airline()
                ->name($m['aName'])
                ->number($m['fNumber']);

            if ($this->http->XPath->query("./ancestor::table[1]/following::table[1]/descendant::text()[contains(normalize-space(), 'H ') or contains(normalize-space(), 'h ')][1]/ancestor::table[2][{$this->contains($m['aName'] . ' ' . $m['fNumber'])}]/preceding::text()[normalize-space()][1][normalize-space()='Recusada']", $root)->length > 0) {
                $f->removeSegment($s);

                return;
            }

            $operator = $this->re("/{$this->opt($this->t('Operated by'))}\s*(.+)/", $depText);

            if (!empty($operator)) {
                $s->airline()
                    ->operator($operator);
            }
        }

        $depTerminal = $this->re("/[·]\n(?<terminal>.+)\n.*[·]\s+(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*\d{1,4}/", $depText);

        if (!empty($depTerminal)) {
            $s->departure()
                ->terminal(str_replace('Terminal ', '', $depTerminal));
        }

        if (preg_match("/^(?:\d+\:\d+)?\s*(?<depTime>\d+\:\d+)\n*(?<depName>.+){1,2}\n(?<depCode>[A-Z]{3})\n/su", $depText, $m)) {
            $s->departure()
                ->date($this->normalizeDate($depDate . ' ' . $this->year . ', ' . $m['depTime']))
                ->name(str_replace("\n", ", ", $m['depName']))
                ->code($m['depCode']);
        }

        if (preg_match("/(?<duration>\d+h.*)\n(?<cabin>.+)$/", $depText, $m)) {
            $s->extra()
                ->cabin($m['cabin'])
                ->duration($m['duration']);
        }

        $arrText = implode("\n", $this->http->FindNodes("./following::text()[contains(normalize-space(), '·')][1]/ancestor::tr[3]/ancestor::table[1]/following::table[1]/descendant::text()[contains(normalize-space(), ':')][2]/ancestor::tr[1]/descendant::text()[normalize-space()]", $root));
        $arrDate = '';

        if (empty($arrText)) {
            $arrText = implode("\n", $this->http->FindNodes("./following::text()[contains(normalize-space(), '·')][1]/ancestor::tr[3]/ancestor::table[1]/following::table[3]/descendant::text()[contains(normalize-space(), ':')][2]/ancestor::tr[1]/descendant::text()[normalize-space()]", $root));
            $arrDate = $this->http->FindSingleNode("./following::text()[contains(normalize-space(), '·')][1]/ancestor::tr[3]/ancestor::table[1]/following::table[2]", $root, true, "/^(\w+\s+\d+\s+\w+)$/");
        }

        if (empty($arrText)) {
            $arrText = implode("\n", $this->http->FindNodes("./following::text()[contains(normalize-space(), '·')][1]/ancestor::tr[3]/ancestor::table[1]/following::text()[contains(normalize-space(), ':')][1]/ancestor::table[1]/descendant::text()[normalize-space()]", $root));
            $arrDate = $this->http->FindSingleNode("./following::text()[contains(normalize-space(), '·')][1]/ancestor::tr[3]/ancestor::table[1]/following::table[2]", $root, true, "/^(\w+\s+\d+\s+\w+)$/");
        }

        if (!empty($arrDate)) {
            $this->lastDate = $arrDate;
        } else {
            $arrDate = $this->lastDate;
        }

        if (preg_match("/^(?<arrTime>[\d\:]+)\n(?<arrName>.+)\n(?<arrCode>[A-Z]{3})/su", $arrText, $m)) {
            $s->arrival()
                ->date($this->normalizeDate($arrDate . ' ' . $this->year . ', ' . $m['arrTime']))
                ->name(str_replace("\n", ", ", $m['arrName']));

            if ($s->getDepCode() !== $m['arrCode']) {
                $s->arrival()
                    ->code($m['arrCode']);
            } else {
                $s->arrival()
                    ->noCode();
            }
        }

        $arrTerminal = $this->re("/\n[·]\n(?<arrTerminal>.+)$/u", $arrText);

        if (!empty($arrTerminal)) {
            $s->arrival()
                ->terminal(str_replace('Terminal ', '', $arrTerminal));
        }
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
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

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'normalize-space(' . $node . ')="' . $s . '"'; }, $field)) . ')';
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

        return implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function normalizeDate($str)
    {
        $in = [
            //WEDNESDAY 17 JANUARY 2023, 15:05
            "#^(\D*)\s+(\d+)\s*(\w+)\s*(\d{4})\,\s*([\d\:]+)$#iu",
            //星期五 29 十二月 2023, 13:30
            "#^\w*\s+(\d+)\s*(\w+)\s*(\d{4})\,\s*([\d\:]+)$#u",
        ];
        $out = [
            "$1, $2 $3 $4, $5",
            "$1 $2 $3, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#(.*\d+\s+)([^\d\s]+)(\s+\d{4}.*)#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[2], $this->lang)) {
                $str = $m[1] . $en . $m[3];
            }
        }

        if (preg_match("/^(?<week>\D+), (?<date>\d+ \w+ .+)/u", $str, $m)) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($m['week'], $this->lang));
            $str = EmailDateHelper::parseDateUsingWeekDay($m['date'], $weeknum);
        } elseif (preg_match("/\b\d{4}\b/", $str)) {
            $str = strtotime($str);
        } else {
            $str = null;
        }

        return $str;
    }

    private function assignLang()
    {
        foreach ($this->detectLang as $lang => $words) {
            foreach ($words as $word) {
                if ($this->http->XPath->query("//text()[{$this->contains($word)}]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }
    }

    private function normalizeCurrency($string)
    {
        $string = trim($string);
        $currencies = [
            'TWD' => ['NT$'],
            'GBP' => ['£'],
            'AUD' => ['A$', 'AU$'],
            'EUR' => ['€', 'Euro'],
            'USD' => ['US Dollar'],
        ];

        foreach ($currencies as $code => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $code;
                }
            }
        }

        return $string;
    }
}
