<?php

namespace AwardWallet\Engine\aeromexico\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ReservationConfirmation extends \TAccountChecker
{
    public $mailFiles = "aeromexico/it-189045673.eml, aeromexico/it-192482879.eml, aeromexico/it-194352197.eml";
    public $detectSubject = [
        // en
        'Reservation confirmation-',
        // es
        'Confirmación de Reservación-',
        'Concluye tu solicitud de reembolsos',
    ];

    public $lang = '';
    public $relativeDate;

    public $detectProvider = [
        // en
        'Club Premier - All rights reserved',
        // es
        'Club Premier - Todos los Derechos Reservados',
    ];

    public $detectBody = [
        'en' => [
            'Your Award Ticket is already insured.',
        ],
        'es' => [
            'Tu Boleto Premio ya está asegurado.',
            'A continuación encontrarás el detalle de la solicitud de reembolso de tu reservación',
        ],
    ];

    public static $dictionary = [
        "en" => [
//            'Your reservation number' => '',
//            'Booked' => '',
//            'Passenger name' => '',
//            'Ticket number' => '',
//            'Club Premier No.' => '',
//            'Flight payment' => '',
//            'Total payment' => '',
//            'Points' => '',
//            'Flight' => '',
//            'Operated by' => '',
//            'Departure' => '',
//            'Traveling time' => '',
//            'Refund Email' => '', // to translate
//            'Passengers' => '', // for refund email // to translate
        ],
        "es" => [
            'Your reservation number' => ['Tu clave de reservación', 'Clave de reservación', 'Tu número de reservación'],
            'Booked' => 'Reservado el',
            'Passenger name' => 'Nombre de pasajero',
            'Ticket number' => 'Número de boleto',
            'Club Premier No.' => 'No. Cuenta Club Premier',
            'Flight payment' => 'Pago del vuelo',
            'Total payment' => 'Pago total',
            'Points' => 'Puntos',
            'Flight' => 'Vuelo',
            'Operated by' => 'Operado por',
            'Departure' => ['Salida:', 'Salida'],
            'Traveling time' => 'Tiempo de viaje:',
            'Refund Email' => 'A continuación encontrarás el detalle de la solicitud de reembolso de tu reservación:',
            'Passengers' => 'Pasajeros', // for refund email
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers['from']) || empty($headers['subject'])) {
            return false;
        }
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }
        foreach ($this->detectSubject as $dSubject) {
            if (strpos($headers['subject'], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[{$this->contains($this->detectProvider)}] | //img[contains(@src, '.clubpremier.')]")->length == 0) {
            return false;
        }
        foreach ($this->detectBody as $dBody) {
            if ($this->http->XPath->query("//text()[{$this->contains($dBody)}]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'vuelos@clubpremier.com') !== false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        foreach ($this->detectBody as $lang => $dBody) {
            if ($this->http->XPath->query("//text()[{$this->contains($dBody)}]")->length > 0) {
                $this->lang = $lang;
                break;
            }
        }

        $this->relativeDate = strtotime("-10, day", strtotime($parser->getDate()));
        $type = '';
        if (!empty($this->http->FindSingleNode("(//*[{$this->contains($this->t("Refund Email"))}])[1]"))) {
            $type = 'Refund';
            $this->parseRefundFlights($email);
        } else {
            $this->parseFlights($email);
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . $type . ucfirst($this->lang));

        return $email;
    }

    public function parseFlights( Email $email)
    {
        $f = $email->add()->flight();

        // General
        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Your reservation number'))}]/following::text()[normalize-space()][1]",
                null, true, "/^\s*([A-Z\d]{5,7})\s*$/"))
            ->date(strtotime($this->http->FindSingleNode("//text()[{$this->eq($this->t('Booked'))}]/following::text()[normalize-space()][1]")))
            ->travellers($this->http->FindNodes("//text()[{$this->eq($this->t('Passenger name'))}]/following::text()[normalize-space()][1][following::text()[{$this->eq($this->t('Departure'))}]]"))
        ;
        $this->relativeDate = $f->getReservationDate();

        // Program
        $accounts = array_unique(array_filter($this->http->FindNodes("//text()[{$this->eq($this->t('Club Premier No.'))}]/following::text()[normalize-space()][1]",
            null, "/^\s*(\d{5,})\s*$/")));
        if (!empty($accounts)) {
            $f->program()
                ->accounts($accounts, false);
        }

        // Issued
        $tickets = array_unique(array_filter($this->http->FindNodes("//text()[{$this->eq($this->t('Ticket number'))}]/following::text()[normalize-space()][1]",
            null, "/^\s*(\d{5,})\s*$/")));
        if (!empty($tickets)) {
            $f->issued()
                ->tickets($tickets, false);
        }

        // Segments
        $xpath = "//text()[{$this->starts($this->t('Flight'))} and contains(., '/')]/following::tr[normalize-space()][1]";
//        $this->logger->debug('$xpath = '.print_r( $xpath,true));
        $nodes = $this->http->XPath->query($xpath);
        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $date = $this->normalizeDate(
                $this->http->FindSingleNode(".//text()[{$this->eq($this->t("Departure"))}]/following::text()[normalize-space()][1]", $root));

            //column 1
            $column = implode("\n", $this->http->FindNodes("td[1]/descendant::tr[not(.//tr)]/td[1]", $root));
            if (preg_match("/^\s*(?<time>\d{1,2}:\d{2})\s+(?<code>[A-Z]{3})\s+(?<name>[\s\S]+?)\s+{$this->opt($this->t('Operated by'))}\s*(?<airline>.+)\s*$/", $column, $m)) {
                $s->airline()
                    ->name($m['airline']);

                $s->departure()
                    ->code($m['code'])
                    ->name(preg_replace("/\s*\n\s*/", ' ', trim($m['name'])))
                    ->date((!empty($date))? strtotime($m['time'], $date) : null)
                ;
            }

            //column 2
            $column = implode("\n", $this->http->FindNodes("td[1]/descendant::tr[not(.//tr)]/td[2]", $root));
            if (preg_match("/^\s*(?<time>\d{1,2}:\d{2})\s+(?<code>[A-Z]{3})\s+(?<name>[\s\S]+?)\s*\n\s*(?<flight>\d{1,5})\s*$/", $column, $m)) {
                $s->airline()
                    ->number($m['flight']);

                $s->arrival()
                    ->code($m['code'])
                    ->name(preg_replace("/\s*\n\s*/", ' ', trim($m['name'])))
                    ->date((!empty($date))? strtotime($m['time'], $date) : null)
                ;
            }

            $s->extra()
                ->duration($this->http->FindSingleNode(".//text()[{$this->eq($this->t("Traveling time"))}]/following::text()[normalize-space()][1]",
                    $root, true, "/^\s*((\d+ *[hsmin]+\s*)+)\s*$/"));
        }

        // Price
        $totals = implode("\n", $this->http->FindNodes("//td[not(.//td)][{$this->eq($this->t("Total payment"))}]/following-sibling::*[normalize-space()]"));
        if (preg_match("/^\s*(\d[\d,. ]*? *{$this->opt($this->t("Points"))}\b)/", $totals, $m)) {
            $f->price()
                ->spentAwards($m[1]);
        }
        if (preg_match("/\+\s*\\$?\s*(\d[\d\., ]*)\s*([A-Z]{3})\b/", $totals, $m)) {
            $f->price()
                ->total(PriceHelper::parse($m[1], $m[2]))
                ->currency($m[2])
            ;
        } else {
            // for error, price must be
            $f->price()
                ->total(null);
        }

        $cost = $this->http->FindSingleNode("//td[not(.//td)][{$this->eq($this->t("Flight payment"))}]/following-sibling::*[normalize-space()][not({$this->contains($this->t('Points'))})][last()]");
        if (preg_match("/^\s*\\$?\s*(\d[\d\., ]*)\s*([A-Z]{3})\b/", $cost, $m)
            && $f->getPrice()->getCurrencyCode() === $m[2]
        ) {
            $f->price()
                ->cost(PriceHelper::parse($m[1], $m[2]));
        }

        $feesNodes = $this->http->XPath->query("//td[not(.//td)][{$this->eq($this->t("Total payment"))}]/ancestor::tr[1]/ancestor::*[1][.//*[{$this->eq($this->t("Flight payment"))}]]/tr[not(.//*[{$this->eq($this->t("Total payment"))} or {$this->eq($this->t("Flight payment"))}])]");
        $fees = [];
        foreach ($feesNodes as $froot) {
            $amount = $this->http->FindSingleNode("*[normalize-space()][not({$this->contains($this->t('Points'))})][position() > 1]", $froot, true, "/.*\d+.*/");
            if (empty($amount)) {
                continue;
            }
            if (preg_match("/^\s*\\$?\s*(\d[\d\., ]*)\s*([A-Z]{3})\b/", $amount, $m)
                && $f->getPrice()->getCurrencyCode() === $m[2]
            ) {
                $fees[] = ['name' => $this->http->FindSingleNode("*[normalize-space()][1]", $froot), 'amount' => PriceHelper::parse($m[1], $m[2])];
            } else {
                $fees = [];
                break;
            }
        }
        foreach ($fees as $fee) {
            $f->price()
                ->fee($fee['name'], $fee['amount']);
        }
    }

    public function parseRefundFlights( Email $email)
    {
        $f = $email->add()->flight();

        // General
        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Your reservation number'))}]/following::text()[normalize-space()][1]",
                null, true, "/^\s*([A-Z\d]{5,7})\s*$/"))
            ->travellers($this->http->FindNodes("//text()[{$this->eq($this->t('Passengers'))}]/following::text()[normalize-space()][1]/ancestor::*[not({$this->contains($this->t('Passengers'))})][last()]//td[not(.//td)][normalize-space()][1]",
                null, "/^\s*([A-Z\- ]+?)\\/\s*[[:alpha:]]+\s*$/"));
        ;


        // Segments
        $xpath = "//text()[{$this->starts($this->t('Flight'))} and contains(., '/')]/following::tr[normalize-space()][1]/descendant-or-self::tr[count(*[normalize-space()]) = 2][1]";
//        $this->logger->debug('$xpath = '.print_r( $xpath,true));
        $nodes = $this->http->XPath->query($xpath);
        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $date = $this->normalizeDate(
                $this->http->FindSingleNode(".//text()[{$this->eq($this->t("Departure"))}]/following::text()[normalize-space()][1]", $root),
                $f->getReservationDate()
            );

            //column 1
            $column = implode("\n", $this->http->FindNodes("td[1]/descendant::tr[not(.//tr)]/td[1]", $root));
            if (preg_match("/^\s*(?<time>\d{1,2}:\d{2})\s+(?<code>[A-Z]{3})\s+(?<name>[\s\S]+?)\s+{$this->opt($this->t('Operated by'))}\s*(?<airline>.+)\s*$/", $column, $m)) {
                $s->airline()
                    ->name($m['airline']);

                $s->departure()
                    ->code($m['code'])
                    ->name(preg_replace("/\s*\n\s*/", ' ', trim($m['name'])))
                    ->date((!empty($date))? strtotime($m['time'], $date) : null)
                ;
            }

            //column 2
            $column = implode("\n", $this->http->FindNodes("td[1]/descendant::tr[not(.//tr)]/td[2]", $root));
            if (preg_match("/^\s*(?<time>\d{1,2}:\d{2})\s+(?<code>[A-Z]{3})\s+(?<name>[\s\S]+?)\s*$/", $column, $m)) {
                $s->airline()
                    ->noNumber();

                $s->arrival()
                    ->code($m['code'])
                    ->name(preg_replace("/\s*\n\s*/", ' ', trim($m['name'])))
                    ->date((!empty($date))? strtotime($m['time'], $date) : null)
                ;
            }

            $s->extra()
                ->duration($this->http->FindSingleNode(".//text()[{$this->eq($this->t("Traveling time"))}]/following::text()[normalize-space()][1]",
                    $root, true, "/^\s*((\d+ *[hsmin]+\s*)+)\s*$/"));
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

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) {
            return "starts-with(normalize-space(.), \"{$s}\")";
        }, $field)) . ')';
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(.), \"{$s}\")";
        }, $field)) . ')';
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

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) {
            return "normalize-space(.)=\"{$s}\"";
        }, $field)) . ')';
    }

    private function normalizeDate($str)
    {
        if (empty($this->relativeDate)) {
            return false;
        }
        $year = date("Y", $this->relativeDate);
        $in = [
            // Sunday 20 November
            "/^\s*([[:alpha:]]+)\s+(\d{1,2})\s+(?:de\s+)?([[:alpha:]]+)$/u",
        ];
        $out = [
            "$1, $2 $3 $year",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        if (preg_match("/^(?<week>[[:alpha:]]+), (?<date>\d+ [[:alpha:]]+ \d+)\s*$/u", $str, $m)) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($m['week'], $this->lang));
            $str = EmailDateHelper::parseDateUsingWeekDay($m['date'], $weeknum);
        } elseif (preg_match("#\b\d{4}\b#", $str)) {
            $str = strtotime($str);
        } else {
            $str = null;
        }

        return $str;
    }
}
