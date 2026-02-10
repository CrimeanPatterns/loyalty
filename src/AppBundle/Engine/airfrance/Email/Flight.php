<?php

namespace AwardWallet\Engine\airfrance\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Flight extends \TAccountChecker
{
    public $mailFiles = "airfrance/it-32779544.eml, airfrance/it-32949248.eml, airfrance/it-33571716.eml";

    private $from = 'service-airfrance.com';
    private $detectSubject = [
        'en' => 'Check in for your flight to',
        'Airport check-in - Your trip to',
        'es' => 'Realice el check-in para su viaje a',
        'it' => 'Effettui il check-in per il suo viaggio a',
        'fr' => "Enregistrement à l'aéroport - Voyage de votre enfant le",
        'ru' => "Зарегистрируйтесь на рейс в",
        'pt' => "Efectue o check-in para a sua viagem com destino a ",
        'Faça o check-in para a sua viagem com destino a',
    ];

    private $prov = 'Air France';
    private $detects = [
        'es'  => 'SU TARJETA DE EMBARQUE',
        'fr'  => 'Ce document est indispensable pour voyager',
        'fr2' => 'Veuillez compléter en ligne et imprimer les formulaires de voyage pour enfants non accompagnés',
        'fr3' => 'Avec l\'enregistrement en ligne',
        'de'  => 'Dieses Dokument ist unerlässlich für Ihre Reise',
        "de2" => "Checken Sie online ein",
        'nl'  => 'Dit document is noodzakelijk om te reizen',
        'en'  => 'This document is required for travel',
        'en2' => 'You can check in online now and choose your seat in just a few clicks',
        'en3' => 'We hope you have a pleasant trip',
        'es2' => 'Le deseamos un feliz viaje',
        'it'  => 'LA SUA CARTA D\'IMBARCO',
        'ru'  => 'ВАШ ПОСАДОЧНЫЙ ТАЛОН',
        'pt'  => 'obter seu cartão de embarque',
        'pt2'  => 'SEU CARTÃO DE EMBARQUE',
    ];

    private $lang = 'en';
    private static $dict = [
        'en' => [
            'Booking reference no.' => ['Booking reference no.', 'Booking reference'],
            'Check-In Deadine'      => ['Check-In Deadline', 'Check-In Deadine'],
            'Provided by'           => ['Provided by', 'Operated by'],
        ],
        'es' => [
            'Check-In Deadine'      => 'Hora de cierre del check-in',
            'Booking reference no.' => 'Referencia de la reserva',
            'Provided by'           => 'Operado por',
        ],
        'fr' => [
            'Check-In Deadine'      => 'Heure de fin d\'enregistrement',
            'Booking reference no.' => 'Référence de réservation',
            'Provided by'           => 'Effectué par',
        ],
        'de' => [
            'Check-In Deadine'      => ['Meldeschlusszeit', 'Boardingzeit'],
            'Booking reference no.' => 'Buchungs code',
            'Provided by'           => 'Durchgeführt von',
        ],
        'nl' => [
            'Check-In Deadine'      => 'Eindtijd voor inchecken',
            'Booking reference no.' => 'Boekingsreferentie r',
            'Provided by'           => 'Uitgevoerd door',
        ],
        'it' => [
            //            'Check-In Deadine' => '',
            'Booking reference no.' => 'Codice di rprenotazione',
            'Provided by'           => 'Operato da',
        ],
        'ru' => [
            'Check-In Deadine'      => 'Время окончания регистрации',
            'Booking reference no.' => 'Номер бронирования',
            'Provided by'           => 'Выполняется',
            'terminal'              => 'терминал',
        ],
        'pt' => [
            'Check-In Deadine'      => ['Hora de fim de embarque:', 'Horário de encerramento do embarque:'],
            'Booking reference no.' => ['Referência de reserva', 'Referência da reserva'],
            'Provided by'           => ['Operado por', 'Efetuado pela'],
            'terminal'              => 'terminal',
        ],
    ];

    /**
     * @return array|Email
     *
     * @throws \AwardWallet\Schema\Parser\Component\InvalidDataException
     */
    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug('can\'t determine a language');

            return $email;
        }

        $this->parseEmail($email);
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (false === stripos($body, $this->prov)) {
            return false;
        }

        foreach ($this->detects as $detect) {
            if (0 < $this->http->XPath->query('//node()[contains(normalize-space(.), "' . $detect . '")]')->length
            && $this->assignLang()) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (!isset($headers['from']) && stripos($headers['from'], $this->from) === false) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (strpos($headers['subject'], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->from) !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    /**
     * @return Email
     *
     * @throws \AwardWallet\Schema\Parser\Component\InvalidDataException
     */
    private function parseEmail(Email $email)
    {
        $f = $email->add()->flight();

        $conf = $this->http->FindSingleNode("//tr[" . $this->contains($this->t('Booking reference no.')) . " and not(.//tr)]/following-sibling::tr[1]/td[1]");
        $f->general()->confirmation($conf);

        $ticket = $this->http->FindSingleNode("//a[contains(@href, 'wwws.airfrance.') and contains(@href, 'check-in?ticketnumber')]/@href", null, true,
            "/check-in\?ticketnumber=(\d{13})&/");

        if (!empty($ticket)) {
            $f->issued()->ticket($ticket, false);
        }
//        $xpath = "//table[(". $this->contains($this->t('Check-In Deadine')) ." or contains(translate(normalize-space(.), \"0123456789\", \"dddddddddd\"), \"dd/dd/dddd\")) and not(.//table)]/ancestor::table[1]/descendant::*[count(table)=2]/table[2]";
//        $xpath = "//table[({$this->contains($this->t('Check-In Deadine'))}) and (contains(translate(normalize-space(.), \"0123456789\", \"dddddddddd\"), \"dd/dd/dddd\")) and not(.//table)]/following::table[1]";
        $xpath = "//text()[{$this->contains($this->t('Provided by'))}]/ancestor::table[(contains(translate(normalize-space(.), \"0123456789\", \"dddddddddd\"), \"dd/dd/dddd\"))][1]/descendant::table[2]";
        $roots = $this->http->XPath->query($xpath);

        if (0 === $roots->length) {
            $this->logger->info("Segments didn't found by xpath: {$xpath}");
        }
//        $this->logger->alert($xpath);
        foreach ($roots as $root) {
            $s = $f->addSegment();

            $date = $this->normalizeDate($this->http->FindSingleNode('preceding::table[1]/descendant::td[1]', $root));

            $re = '/(.+)\s+\(([A-Z]{3})\)/';

            if (preg_match($re, $this->getNode($root), $m)) {
                $s->departure()
                    ->name($m[1])
                    ->code($m[2]);
            }

            if (preg_match($re, $this->getNode($root, 3), $m)) {
                $s->arrival()
                    ->name($m[1])
                    ->code($m[2]);
            }

            $node = $this->getNode($root, 1, 2);
            $airlineRe = "(?<al>[A-Z\d][A-Z]|[A-Z][A-Z\d])\s?(?<fn>\d{1,5})";
            $re = "/(?<time>\d{1,2}:\d{2})\s+(?<name>.+)\s+" . $airlineRe . "\s+\-\s+{$this->preg_implode($this->t('Provided by'))}\s+(?<operator>.+)/ui";

            if (preg_match($re, $node, $m)) {
                $s->departure()
                    ->date(strtotime($m['time'], $date))
                ;

                if (preg_match("/(.+)\s+\-\s+(?:terminal|{$this->preg_implode($this->t('terminal'))})\s*(.*)/u", $m['name'], $mat)) {
                    $m['name'] = $mat[1];
                    $s->departure()
                        ->terminal($mat[2] ? trim($mat[2]) : null, true, true);
                }

                if (!empty($s->getDepName())) {
                    $s->departure()
                        ->name($s->getDepName() . ', ' . $m['name']);
                } else {
                    $s->departure()
                        ->name($m['name']);
                }
                $s->airline()
                    ->name($m['al'])
                    ->number($m['fn'])
                    ->operator(preg_replace("/^(.+?) \(.*$/", '$1', $m['operator']));
            } else {
                if (preg_match('/(?<time>\d{1,2}:\d{2})\s*(?<name>.+)$/iu', $node, $m)) {
                    $s->departure()
                        ->date(strtotime($m['time'], $date))
                    ;

                    if (preg_match("/(.+)\s+\-\s+(?:terminal|{$this->preg_implode($this->t('terminal'))})\s*(.*)/", $m['name'], $mat)) {
                        $m['name'] = $mat[1];
                        $s->departure()
                            ->terminal($mat[2] ? trim($mat[2]) : null, true, true);
                    }

                    if (!empty($s->getDepName())) {
                        $s->departure()
                            ->name($s->getDepName() . ', ' . $m['name']);
                    } else {
                        $s->departure()
                            ->name($m['name']);
                    }
                }

                if (preg_match("/^\s*" . $airlineRe . "\s+\-\s+{$this->preg_implode($this->t('Provided by'))}\s+(?<operator>.+)/iu", $this->getNode($root, 2, 2), $m)) {
                    $s->airline()
                        ->name($m['al'])
                        ->number($m['fn'])
                        ->operator(preg_replace("/^(.+?) \(.*$/", '$1', $m['operator']));
                }
            }

            if (preg_match('/(?<time>\d{1,2}:\d{2})\s*(?<name>.+)$/iu', $this->getNode($root, 3, 2), $m)) {
                $s->arrival()
                    ->date(strtotime($m['time'], $date))
                ;

                if (preg_match("/(.+)\s+\-\s+(?:terminal|{$this->preg_implode($this->t('terminal'))})\s*(.*)/", $m['name'], $mat)) {
                    $m['name'] = $mat[1];
                    $s->arrival()
                        ->terminal($mat[2] ? trim($mat[2]) : null, true, true);
                }

                if (!empty($s->getArrName())) {
                    $s->arrival()
                        ->name($s->getArrName() . ', ' . $m['name']);
                } else {
                    $s->arrival()
                        ->name($m['name']);
                }
            }
        }

        return $email;
    }

    private function t($word)
    {
        if (!isset(self::$dict[$this->lang]) || !isset(self::$dict[$this->lang][$word])) {
            return $word;
        }

        return self::$dict[$this->lang][$word];
    }

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'contains(normalize-space(' . $node . "),'" . $s . "')"; }, $field)) . ')';
    }

    private function normalizeDate(?string $str)
    {
        $in = [
            '/^(\d{1,2})\/(\d{1,2})\/(\d{2,4})$/',
        ];
        $out = [
            "$2/$1/$3",
        ];
        $str = preg_replace($in, $out, $str);

        return strtotime($str);
    }

    private function getNode(\DOMNode $root, int $tr = 1, int $td = 1, string $re = ''): ?string
    {
        return $this->http->FindSingleNode("descendant::*[count(tr)=3]/tr[{$tr}]/td[{$td}]", $root, true, $re);
    }

    private function assignLang()
    {
        foreach (self::$dict as $lang => $words) {
            if (isset($words['Booking reference no.'], $words['Provided by'])) {
                if ($this->http->XPath->query("//*[{$this->contains($words['Booking reference no.'])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($words['Provided by'])}]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
    }
}
