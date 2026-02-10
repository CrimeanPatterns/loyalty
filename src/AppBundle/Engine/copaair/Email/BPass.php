<?php

namespace AwardWallet\Engine\copaair\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BPass extends \TAccountChecker
{
    public $mailFiles = "copaair/it-109983961.eml, copaair/it-33814307.eml, copaair/it-33858351.eml, copaair/it-76616757.eml, copaair/it-77072384.eml, copaair/it-87673645.eml, copaair/it-88367724.eml";

    private $year = null;

    private $pdfName = '';

    private $date;

    private $detects = [
        'en'  => 'You have been successfully checked in for the following flights',
        'en2' => 'time to check in for your trip',
        'es'  => 'Tu Check-In ha sido exitoso para los siguientes vuelos',
        'es2' => 'Es hora de realizar Check-In para tu viaje',
        'pt'  => 'hora de fazer o check-in da sua viagem',
        'pt2' => 'check-in foi realizado para ',
    ];

    private $from = '/[@\.]copaair\.com/';

    private $lang = '';

    /** @var \HttpBrowser */
    private $pdf = null;

    private static $dict = [
        'en' => [
        ],
        'es' => [
            'E-TICKET NUMBER' => 'BOLETO ELECTRÓNICO',
            'SEAT'            => 'ASIENTO',
            //            'TERMINAL' => '',
            'GROUP'                 => 'GRUPO',
            'TRAVELERS INFORMATION' => ['INFORMACIÓN DE LOS PASAJEROS', 'INFORMACIÓN DEL PASAJERO', 'INFORMACIÓN DE PASAJEROS'],
            'RESERVATION CODE'      => 'CÓDIGO DE RESERVACIÓN',
            'Flight'                => 'Vuelo',
            'Duration'              => 'Duración',
            //'Check in Now' => ''
        ],
        'pt' => [
            //'E-TICKET NUMBER' => '',
            'SEAT' => 'ASSENTO',
            //            'TERMINAL' => '',
            //'GROUP' => '',
            'TRAVELERS INFORMATION' => ['INFORMAÇÃOS DE PASSAGEIROS', 'INFORMAÇÃO DO VIAJANTE'],
            'RESERVATION CODE'      => ['NÚMERO DE RESERVA', 'CÓDIGO DE RESERVA'],
            'Flight'                => 'Vôo',
            'Duration'              => 'Duração',
            'Check in Now'          => 'Check-In agora',
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();
        $this->date = strtotime($parser->getHeader('date'));
        $this->year = date('y', strtotime($parser->getDate()));
        $pdf = $parser->searchAttachmentByName('.*pdf');

        if (0 < count($pdf)) {
            $this->pdf = \PDF::convertToText($parser->getAttachmentBody(array_shift($pdf)), false);
        }
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));
        $body = $parser->getHTMLBody();
        $em = chr(226) . chr(128) . chr(131);
        $body = str_replace($em, ' ', $body);
        $this->http->SetEmailBody($body);
        $this->parseEmail($email);

        return $email;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return !empty($headers['from']) && preg_match($this->from, $headers['from']) > 0;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ((0 === $this->http->XPath->query("//img[contains(@src, 'checkin.copaair.com')]")->length
                && 0 === $this->http->XPath->query("//a[contains(@href, 'cns.copaair.com')]")->length
            )
            && 0 === $this->http->XPath->query("//a[contains(@href, 'copaair.com')]")->length
        ) {
            return false;
        }

        return $this->assignLang();
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match($this->from, $from) > 0;
    }

    private function parseEmail(Email $email): void
    {
        $f = $email->add()->flight();

        if ($this->pdf && preg_match_all("/(?:\n|^).{30,} {3,}{$this->t('E-TICKET NUMBER')} +.+\n+(?:.*\n+){0,2}.{30,} {3,}(\d{10,}) {3,}[A-Z\d]{5,7}\s*\n/u", $this->pdf, $m)) {
            $f->issued()
                ->tickets($m[1], false);
        }

        $paxs = $this->http->FindNodes("//p[{$this->eq($this->t('TRAVELERS INFORMATION'))}]/following-sibling::span[normalize-space(.)]");

        if (empty($paxs)) {
            $paxs = $this->http->FindNodes("//text()[{$this->starts($this->t('TRAVELERS INFORMATION'))}]/ancestor::tr[1]/following-sibling::tr[normalize-space(.)]", null, "/([A-Z\s]+)\b/");
        }

        $f->general()
            ->travellers($paxs);

        $accounts = $this->http->FindNodes("//text()[{$this->starts($this->t('TRAVELERS INFORMATION'))}]/ancestor::tr[1]/following-sibling::tr[normalize-space(.)]", null, "/[#]([A-Z\d]+)/");

        if (count($accounts) > 0) {
            $f->program()
                ->accounts(array_unique(array_filter($accounts)), false);
        } elseif (count($accounts) == 0) {
            $accounts = $this->http->FindNodes("//text()[starts-with(normalize-space(), 'TRAVELERS INFORMATION')]/following::text()[starts-with(normalize-space(), 'ConnectMiles')]", null, "/[*]+\s*(\d+)/");
            $f->program()
                ->accounts(array_unique(array_filter($accounts)), true);
        }

        $confNo = $this->http->FindSingleNode("//p[{$this->eq($this->t('RESERVATION CODE'))}]/following-sibling::p[1]");

        if (empty($confNo)) {
            $confNo = $this->http->FindSingleNode("//text()[{$this->eq($this->t('RESERVATION CODE'))}]/following::text()[normalize-space()][1]");
        }
        $f->general()
            ->confirmation($confNo, $this->http->FindSingleNode("//text()[{$this->eq($this->t('RESERVATION CODE'))}]"));

        $xpath = "//img[contains(@src, 'avion')][1]/ancestor::tr[1]";
        $roots = $this->http->XPath->query($xpath);

        if (0 === $roots->length) {
            $xpath = "//text()[starts-with(normalize-space(), '(') and contains(normalize-space(), 'h ')]/preceding::img[1]";
            $roots = $this->http->XPath->query($xpath);
        }

        if (0 === $roots->length) {
            $this->logger->debug("Segments were not found by xpath: {$xpath}");
        }

        foreach ($roots as $i => $root) {
            $s = $f->addSegment();
            $date = null;

            $flight = $this->http->FindSingleNode("preceding::tr[contains(., '{$this->t('Flight')}')][1]", $root);

            if (empty($flight)) {
                $flight = $this->http->FindSingleNode("preceding::text()[contains(normalize-space(), ' · ')][1]", $root);
            }

            if (preg_match("/(\d{1,2} \w+).*{$this->t('Flight')} (\d+)/", $flight, $m)) {
                $date = $this->normalizeDate($m[1] . ' ' . $this->year);
                $s->airline()
                    ->number($m[2])
                    ->noName();
            } elseif (preg_match("/{$this->t('Flight')}\s*(\d+)/", $flight, $m)) {
                $s->airline()
                    ->number($m[1])
                    ->noName();
            } elseif (preg_match("/[·]\s*([A-Z\d]{2})\s*(\d{2,4})/", $flight, $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2]);
            }

            $re = '/(.+)\s*([A-Za-z]{3})\s*(\d{1,2}:\d{2}[ap]m)/';
            $dep = $this->http->FindSingleNode('td[1]', $root);

            if (preg_match($re, $dep, $m)) {
                $s->departure()
                    ->name($m[1])
                    ->code($m[2])
                    ->date(strtotime($m[3], $date));
            }

            if (empty($dep)) {
                $date = $this->http->FindSingleNode("preceding::text()[{$this->starts($this->t('Flight'))}][1]/preceding::text()[normalize-space()][1]", $root);

                if (empty($date)) {
                    $date = $this->http->FindSingleNode("preceding::text()[contains(normalize-space(), ' · ')][1]", $root, true, ("/^\w+\,\s*(\d+\s*\w+)\s*[·]/u"));
                }
                $time = $this->http->FindSingleNode("preceding::text()[normalize-space()][1]", $root);
                $s->departure()
                    ->code($this->http->FindSingleNode("preceding::text()[normalize-space()][2]", $root))
                    ->date($this->normalizeDate($date . ' ' . $time));
            }

            $arr = $this->http->FindSingleNode('td[last()]', $root);

            if (preg_match($re, $arr, $m)) {
                $s->arrival()
                    ->name($m[1])
                    ->code($m[2])
                    ->date(strtotime($m[3], $date));
            }

            if (empty($arr)) {
                $date = $this->http->FindSingleNode("preceding::text()[{$this->starts($this->t('Flight'))}][1]/preceding::text()[normalize-space()][1]", $root);

                if (empty($date)) {
                    $date = $this->http->FindSingleNode("preceding::text()[contains(normalize-space(), ' · ')][1]", $root, true, ("/^\w+\,\s*(\d+\s*\w+)\s*[·]/u"));
                }
                $time = $this->http->FindSingleNode("following::text()[normalize-space()][4]", $root);
                $s->arrival()
                    ->code($this->http->FindSingleNode("following::text()[normalize-space()][3]", $root))
                    ->date($this->normalizeDate($date . ' ' . $time));
            }

            $dur = $this->http->FindSingleNode("td[2]/p[contains(., '{$this->t('Duration')}')]", $root, true, "/{$this->t('Duration')}[ ]*\:[ ]*(.+)/");

            if (empty($dur)) {
                $dur = $this->http->FindSingleNode("following::text()[normalize-space()][1]", $root, true, "/(?:{$this->t('Duration')})?[ ]*\:?[ ]*(.+)/");
            }
            $s->extra()
                ->duration(trim($dur, '()'));

            if ($this->pdf && !empty($s->getFlightNumber())) {
                if (preg_match_all("/\n.+ {2,}{$this->t('SEAT')}(?: {3,}.*)?\n+(?: {40,}.*\n+){0,2} *(\S ?)+ {2,}(?<al>" . ($s->getAirlineName() ?? '[A-Z\d]{2}') . ") ?{$s->getFlightNumber()} {2,}(?<seat>\d{1,3}[A-Z])(?: {3,}|\n)/u", $this->pdf, $m)) {
                    $s->extra()
                        ->seats($m['seat'], false);

                    if (empty($s->getAirlineName())) {
                        $s->airline()
                            ->name($m['al'][0]);
                    }
                }

                if (preg_match("/\n *.+ {2,}(?:" . ($s->getAirlineName() ?? '[A-Z\d]{2}') . ") ?{$s->getFlightNumber()} {2,}.*[\s\S]+\n( +) {2}{$this->t('TERMINAL')} {2,}.+\n+(?: {40,}.*\n+){0,2}\\1 {0,5}(?<terminal>(\S ?)+)/u", $this->pdf, $m)
                ) {
                    $s->departure()
                        ->terminal($m['terminal']);
                }
            }
        }
    }

    private function assignLang()
    {
        if (isset($this->detects)) {
            foreach ($this->detects as $lang => $reBody) {
                if (
                    $this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody}')]")->length > 0
                ) {
                    $this->lang = substr($lang, 0, 2);

                    return true;
                }
            }
        }

        return false;
    }

    private function getAttachmentName(PlancakeEmailParser $parser, $pdf): ?string
    {
        $header = $parser->getAttachmentHeader($pdf, 'Content-Type');

        if (preg_match('/name=[\"\']*(.+\.pdf)[\'\"]*/i', $header, $matches)) {
            return $matches[1];
        }

        return null;
    }

    private function t($word)
    {
        if (!isset(self::$dict[$this->lang]) || !isset(self::$dict[$this->lang][$word])) {
            return $word;
        }

        return self::$dict[$this->lang][$word];
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
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function normalizeDate($str)
    {
        //$this->logger->error('IN-' . $str);
        $year = date("Y", $this->date);
        $in = [
            "#^(\d+\s*\w+)\s*([\d\:]+\s*a?p?m)$#iu",
            "#^(\d+)\s*(\w+)\s*(\d{2})$#",
        ];
        $out = [
            "$1 $year, $2",
            "$1 $2 20$3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }
        //$this->logger->error('OUT-' . $str);

        return strtotime($str);
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
}
