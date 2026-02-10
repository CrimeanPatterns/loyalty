<?php

namespace AwardWallet\Engine\aerlingus\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Flight extends \TAccountChecker
{
    public $mailFiles = "aerlingus/it-348679578.eml, aerlingus/it-5230759.eml, aerlingus/it-5275777.eml";
    public $subjects = [
        '#Aer Lingus Confirmation#i',
        '#Aer Lingus Travel Advisory#i',
        '#Aer Lingus Schedule Change Notification#i',
        '#Aer\s+Lingus\s+AerMail\s+-\s+Booking Ref#i',
        '#Aer Lingus Select Seats - Booking Ref#i',
        '/Aer\s+Lingus\s+Deposit\s+Confirmation\s+-\s+Booking\s+Ref/i',
    ];

    public $lang = 'en';
    public $textEmail;

    public static $dictionary = [
        "en" => [
            'Departure:'                 => ['Departure:', 'Departs:'],
            'Arrival:'                   => ['Arrives:', 'Arrival:'],
            'Status/Class:'              => ['Status/Class:', 'Status:'],
            'Booking reference:'         => ['Booking reference:', 'Booking Reference:'],
            'Booking confirmation date:' => ['Booking confirmation date:', 'Date:'],
            'Operated by:'               => ['Operated by:', 'Operated By:', 'Airline:'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@aerlingus.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (preg_match($subject, $headers['subject'])) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $text = str_replace('>', '', $parser->getPlainBody());

        if (stripos($text, 'Thank you for booking your flight with Aer Lingus') !== false
        && (stripos($text, 'Passenger(s)') !== false || stripos($text, 'PASSENGER(S)') !== false)
        && stripos($text, 'Total Amount') !== false
        && $this->http->XPath->query("//img[contains(@src,'aerlingus.com')]")->length === 0
        ) {
            return true;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]aerlingus.com$/', $from) > 0;
    }

    public function parseFlight(Email $email, string $text)
    {
        $f = $email->add()->flight();

        $date = $this->re("/{$this->opt($this->t('Booking confirmation date:'))}\s*(.+)/", $text);

        $f->general()
            ->confirmation($this->re("/{$this->opt($this->t('Booking reference:'))}\s*([A-Z\d]{6})/su", $text))
            ->date(strtotime($date));

        if (preg_match_all("/PASSENGER\(S\)\n*((?:.+\n+){1,5})\s*FLIGHT/iu", $text, $m)) {
            $pax = preg_replace("/(\n+)/", "\n", $m[1]);
            $f->general()
                ->travellers(array_filter(array_unique($pax)), true);
        }

        $ticketArray = [];

        if (preg_match_all("/Ticket Numbers\s*\n*Issue Date\s*\n*([\d\-]+)/ui", $text, $result)) {
            foreach ($result[1] as $ticket) {
                $ticketArray = array_merge($ticketArray, explode('-', $ticket));
            }
            $f->setTicketNumbers(array_unique($ticketArray), false);
        }

        $result['BaseFare'] = 0;
        $result['Tax'] = 0;

        $currency = $this->re("#\n*\s*Total Amount\s*(\w{3})\s*[\d.]+#i", $text);
        $total = $this->re("#\n*\s*TOTAL\s*\w{3}\s*([\d.]+)#i", $text);

        $adults = $this->re("#Total\s*(\d+)\s*Adult\(s\):((?:\s*[A-Z]{3}\s*\d[\d\.]*)+)#msi", $text);
        $values = preg_split("/\s{2,}/", $this->re("#Total\s+\d+\s*Adult\(s\):((?:\s+[A-Z]{3}\s*\d[\d\.]*)+)#msi", $text));
        $values = array_values(array_filter(array_map("trim", $values)));

        if (count($values) == 0) {
            $values = $this->re("/Total\s*\d+\s*Adult\(s\):((?:\s*[A-Z]{3}\s*\d[\d\.]*)+)/ui", $text);

            if (preg_match_all("/([A-Z]{3}\s*[\d\.\,]+)/", $values, $m)) {
                $values = $m[1];
            }
        }

        $result['BaseFare'] = $adults * cost($values[0] ?? null);
        $result['Tax'] = $adults * cost($values[1]);

        $children = $this->re("#Total\s+\d+\s*Adult\(s\):(?:(?:\s+[A-Z]{3}\s*\d[\d\.]*)+)\s+(\d+)\s*Child\(ren\):((?:\s+[A-Z]{3}\s*\d[\d\.]*)+)#msi", $text);

        if ($children) {
            $values = preg_split("/\s{2,}/",
                $this->re("#Total\s+\d+\s*Adult\(s\):(?:(?:\s+[A-Z]{3}\s*\d[\d\.]*)+)\s+\d+\s*Child\(ren\):((?:\s+[A-Z]{3}\s*\d[\d\.]*)+)#msi", $text));
            $values = array_filter(array_map("trim", $values));

            if (isset($values[1])) {
                $result['BaseFare'] += $children * cost($values[2] ?? null);
            }

            if (isset($values[2])) {
                $result['Tax'] += $children * cost($values[3] ?? null);
            }
        }
        $f->price()
            ->total(PriceHelper::parse($total, $currency))
            ->currency($currency)
            ->cost($result['BaseFare'])
            ->tax($result['Tax']);

        $feesText = $this->re("/Total Amount\s*\n*.*\n*((?:.*\n){4,})TOTAL/iu", $text);

        if (empty($feesText)) {
            $feesText = $this->re("/Total Amount\s*[A-Z]{3}\s*[\d\.\,]+(.+)TOTAL/", $text);
        }

        if (preg_match_all("/^([A-z\s\d]+\:\s*\n*[A-Z]{3}\s*[\d\.\,]+)$/mu", $feesText, $result)
        || preg_match_all("/([A-z\d\s]+\:\s*\n*[A-Z]{3}\s*[\d\.\,]+)/u", $feesText, $result)) {
            foreach ($result[1] as $feeText) {
                if (preg_match("/^(?<name>.+\:)\s*\n*[A-Z]{3}\s*(?<summ>[\d\.\,]+)$/", $feeText, $m)) {
                    $f->price()
                        ->fee($m['name'], $m['summ']);
                }
            }
        }

        if (preg_match_all("/(Flight\n*.+\n*Departs:\n*.+\n*Arrives:\n*.+\n*Status:\n*.+\n*Operated By:\n*.+\n*Seats:\s*.+)/ui", $text, $match)) {
            foreach ($match[1] as $seg) {
                $s = $f->addSegment();

                if (preg_match("/Flight\n*(?<flightInfo>.+)\n*Departs\:\n*(?<depInfo>.+)\n*Arrives\:\n*(?<arrInfo>.+)\n*Status\:\n*(?<status>.+)\n*Operated By\:\n*(?<operator>.+)\n*Seats:\s*(?<seats>.+)/ui", $seg, $m)) {
                    if (preg_match("/^\s*(?<airline>[A-Z][A-Z\d]|[A-Z\d][A-Z])(?<number>\d{2,4})\s*\-\s*(?<date>.+)$/", $m['flightInfo'], $result)) {
                        $s->airline()
                            ->name($result['airline'])
                            ->number($result['number']);

                        $date = $result['date'];
                    }

                    if (
                        preg_match("/^(?<name>.+)(\-.*Terminal\s*)(?<terminal>.+)\s+\((?<code>[A-Z]{3})\)\s+(?<time>[\d\:]+\s*A?P?M?)/", $m['depInfo'], $result) //Newark New Jersey-Terminal B (EWR) 15:50
                        || preg_match("/^(?<name>.+)\s+\((?<code>[A-Z]{3})\)\s+(?<time>[\d\:]+\s*A?P?M?)/", $m['depInfo'], $result) //Newark New Jersey (EWR) 18:00
                    ) {
                        $s->departure()
                            ->name($result['name'])
                            ->code($result['code'])
                            ->date(strtotime($date . ', ' . $result['time']));

                        if (isset($result['terminal']) && !empty($result['terminal'])) {
                            $s->departure()
                                ->terminal($result['terminal']);
                        }
                    }

                    if (
                        preg_match("/^(?<name>.+)(\-.*Terminal\s*)(?<terminal>.+)\s+\((?<code>[A-Z]{3})\)\s+(?<time>[\d\:]+\s*A?P?M?)/u", $m['arrInfo'], $result) //Newark New Jersey-Terminal B (EWR) 15:50
                        || preg_match("/^(?<name>.+)\s+\((?<code>[A-Z]{3})\)\s+(?<time>[\d\:]+\s*A?P?M?)/u", $m['arrInfo'], $result) //Newark New Jersey (EWR) 18:00
                    ) {
                        $s->arrival()
                            ->name($result['name'])
                            ->code($result['code'])
                            ->date(strtotime($date . ', ' . $result['time']));

                        if (isset($result['terminal']) && !empty($result['terminal'])) {
                            $s->arrival()
                                ->terminal($result['terminal']);
                        }
                    }

                    $s->airline()
                        ->operator(str_replace("Operated By", "", $m['operator']));

                    if (preg_match("/^\s*(?<bookingCode>[A-Z]{1,2})\/(?<cabin>.+) Class\s*(?<status>\w+)$/u", $m['status'], $result)) {
                        $s->extra()
                            ->bookingCode($result['bookingCode'])
                            ->cabin($result['cabin'])
                            ->status($result['status']);
                    }

                    if (preg_match_all("/(\d+[A-Z])/", $m['seats'], $m)) {
                        $s->extra()
                            ->seats($m[1]);
                    }
                }
            }
        }

        //$this->logger->debug($text);
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $text = $parser->getPlainBody();
        $text = str_replace(['Â', ';-;', '>'], ['', '-', ''], $text);

        $this->parseFlight($email, $text);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    protected function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }
}
