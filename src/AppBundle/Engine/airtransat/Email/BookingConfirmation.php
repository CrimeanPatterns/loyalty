<?php

namespace AwardWallet\Engine\airtransat\Email;

// TODO: delete what not use
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class BookingConfirmation extends \TAccountChecker
{
    public $mailFiles = "";

    public $lang;
    public static $dictionary = [
        'en' => [
            'Booking number' => 'Booking number',
            //            'Booking date' => '',
            'Download your electronic documents.' => 'Download your electronic documents.',

            'Hotel details' => 'Hotel details',
            //            'adult' => '',
            //            'child' => '',
            //            'night(s)' => '',

            'Flight Details' => 'Flight Details',
            //            'flight' => '',
            //            'Class' => '',
            //            'Departure:' => '',
            //            'Arrival:' => '',
            'Duration:' => 'Duration:',

            //            'Seat' => '',

            //            'Passengers information' => '',
            //            'Total price' => '',
            //            'Taxes & fees' => '',
        ],
        'fr' => [
            'Booking number'                      => 'Numéro de réservation',
            'Booking date'                        => 'Date de réservation',
            'Download your electronic documents.' => 'Téléchargez vos documents électroniques.',

            'Hotel details' => "Détails de l'hôtel",
            'adult'         => 'adult',
            'child'         => 'enfant',
            'night(s)'      => 'nuit(s)',

            'Flight Details' => 'Détails du vol',
            'flight'         => 'vol',
            'Class'          => 'Classe',
            'Departure:'     => 'Départ :',
            'Arrival:'       => 'Arrivée :',
            'Duration:'      => 'Durée :',

            'Seat' => 'Siège',

            'Passengers information' => 'Renseignements sur les voyageurs',
            'Total price'            => 'Prix total',
            'Taxes & fees'           => 'Taxes et frais',
        ],
    ];

    private $detectFrom = "confirmation@transat.com";
    private $detectSubject = [
        // en
        'Transat- Booking Confirmation',
        // fr
        'Transat- confirmation de réservation',
    ];

    // Main Detects Methods
    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers["subject"], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        // detect Provider
        if (
            $this->http->XPath->query("//a[{$this->contains(['airtransat.com'], '@href')}]")->length === 0
            || $this->http->XPath->query("//*[{$this->contains(['Transat A.T. inc'])}]")->length === 0
        ) {
            return false;
        }

        // detect Format
        foreach (self::$dictionary as $dict) {
            if (!empty($dict['Download your electronic documents.']) && !empty($dict['Hotel details']) && !empty($dict['Flight Details'])
                && $this->http->XPath->query("//*[{$this->contains($dict['Download your electronic documents.'])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->contains($dict['Hotel details'])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->contains($dict['Flight Details'])}]")->length > 0
            ) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("can't determine a language");

            return $email;
        }
        $this->parseEmailHtml($email);

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

    private function parseEmailHtml(Email $email)
    {
        // Travel Agency
        $email->ota()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking number'))}]/following::text()[normalize-space()][1]"));

        $travellersText = implode(" ", $this->http->FindNodes("//tr[{$this->eq($this->t('Passengers information'))}]/following-sibling::tr//text()[normalize-space()]"));
        $travellers = array_filter(preg_split("/\s*●\s*\w+[.,]?\s*\w+[.,]?\s*\d{4}\b/u", $travellersText));
        $travellers = preg_replace("/^\s*[[:alpha:]]+\s+((Mr|Mrs|Ms|Miss|Mstr)\.?\s+)?/u", '', $travellers);

        $bookingDate = $this->normalizeDate($this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking date'))}]/following::text()[normalize-space()][1]"));

        // Price
        $total = $this->getTotal($this->http->FindSingleNode("//text()[{$this->eq($this->t('Total price'))}]/following::text()[normalize-space()][1]"));
        $email->price()
            ->total($total['amount'])
            ->currency($total['currency']);

        $taxesRow = $this->http->FindNodes("//text()[{$this->eq($this->t('Taxes & fees'))}]/following::text()[normalize-space()][1]");
        $tax = 0.0;

        foreach ($taxesRow as $row) {
            $f = $this->getTotal($row)['amount'];

            if ($f !== null) {
                $tax += $f;
            } else {
                $tax = null;

                break;
            }
        }
        $email->price()
            ->tax($tax);

        // FLIGHT
        $f = $email->add()->flight();

        // General
        $f->general()
            ->noConfirmation()
            ->travellers($travellers, true)
            ->date($bookingDate)
        ;

        $xpath = "//text()[{$this->eq($this->t('Departure:'))}]/ancestor::*[{$this->contains($this->t('flight'))}][1]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $i => $root) {
            $s = $f->addSegment();

            // Airline
            $flight = $this->http->FindSingleNode(".//text()[{$this->starts($this->t('flight'))}]", $root);

            if (preg_match("/^\s*{$this->opt($this->t('flight'))}\s*(?<al>[A-Z][A-Z\d]|[A-Z\d][A-Z]) ?(?<fn>\d{1,5})\s*-\s*$/u", $flight, $m)) {
                // flight TS426 -
                $s->airline()
                    ->name($m['al'])
                    ->number($m['fn']);
                $s->extra()
                    ->aircraft($this->http->FindSingleNode(".//text()[{$this->starts($this->t('flight'))}]/following::text()[normalize-space()][1]", $root));
            }

            // Departure, Arrival
            $route = $this->http->FindSingleNode(".//text()[{$this->contains($this->t('>'))}]/ancestor::tr[1]", $root);

            if (preg_match("/^\s*(?<dName>\S.+?)\s*\((?<dCode>[A-Z]{3})\)\s*>\s*(?<aName>\S.+?)\s*\((?<aCode>[A-Z]{3})\)\s*$/", $route, $m)) {
                $s->departure()
                    ->name($m['dName'])
                    ->code($m['dCode']);
                $s->arrival()
                    ->name($m['aName'])
                    ->code($m['aCode']);

                $seats = $this->http->FindNodes("//text()[{$this->starts($this->t('Seat'))}][preceding::text()[normalize-space()][1][contains(normalize-space(), '" . $s->getDepName() . ' > ' . $s->getArrName() . "')]]",
                    null, "/{$this->opt($this->t('Seat'))}\s*(\d{1,3}[A-Z])\s*$/");

                if (!empty($seats)) {
                    $s->extra()
                        ->seats($seats);
                }
            }
            $date = $this->http->FindSingleNode(".//text()[{$this->contains($this->t('>'))}]/ancestor::tr[1]/preceding::text()[normalize-space()][1]/ancestor::tr[1]",
                $root, true, '/:\s*(.+)/');

            $dTime = $this->http->FindSingleNode(".//text()[{$this->starts($this->t('flight'))}]/following::text()[{$this->eq($this->t('Departure:'))}][1]/following::text()[normalize-space()][1]", $root);

            if (!empty($date) && !empty($dTime)) {
                $s->departure()
                    ->date($this->normalizeDate($date . ', ' . $dTime));

                if ($i == $nodes->length - 1) {
                    $hotelEnd = $s->getDepDate();
                }
            }

            $aTime = $this->http->FindSingleNode(".//text()[{$this->eq($this->t('Arrival:'))}]/following::text()[normalize-space()][1]", $root);

            if (!empty($date) && !empty($aTime)) {
                $s->arrival()
                    ->date($this->normalizeDate($date . ', ' . $aTime));

                if ($i == 0) {
                    $hotelStart = $s->getArrDate();
                }
            }

            // Extra
            $s->extra()
                ->cabin($this->http->FindSingleNode(".//text()[{$this->starts($this->t('flight'))}]/following::text()[{$this->eq($this->t('Departure:'))}][1]/preceding::text()[normalize-space()][1][{$this->contains($this->t('Class'))}]", $root))
                ->duration($this->http->FindSingleNode(".//text()[{$this->eq($this->t('Duration:'))}]/following::text()[normalize-space()][1]", $root))
            ;
        }

        // HOTEL
        $h = $email->add()->hotel();

        // General
        $h->general()
            ->noConfirmation()
            ->travellers($travellers)
            ->date($bookingDate)
        ;

        $rows = $this->http->FindNodes("//text()[{$this->eq($this->t('Hotel details'))}]/following::text()[normalize-space()][1]/ancestor::*[preceding-sibling::*[not(normalize-space()) and count(.//img) = 1]][1]//div");

        if (!empty($hotelStart) && !empty($hotelEnd)
            && preg_match("/(\d+) ?{$this->opt($this->t('night(s)'))}/u", $rows[4], $m)
        ) {
            $duration = $m[1];
            $hotelStart = strtotime('00:00', $hotelStart);
            $hotelEnd = strtotime('00:00', $hotelEnd);
            $realNights = date_diff(
                new \DateTime(date("j F Y", $hotelStart)),
                new \DateTime(date("j F Y", $hotelEnd))
            )->format('%a');

            if ($duration === $realNights) {
                $h->booked()
                    ->checkIn($hotelStart)
                    ->checkOut($hotelEnd)
                ;
            }

            $h->hotel()
                ->name($rows[0])
                ->address($rows[2]);

            $h->addRoom()
                ->setType($rows[3]);

            $h->booked()
                ->guests($this->re("/(\d+) ?{$this->opt($this->t('adult'))}/ui", $rows[4]))
                ->kids($this->re("/(\d+) ?{$this->opt($this->t('child'))}/ui", $rows[4]), true, true)
            ;
        }

        return true;
    }

    private function assignLang()
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (isset($dict["Booking number"], $dict["Duration:"])) {
                if ($this->http->XPath->query("//*[{$this->contains($dict['Booking number'])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($dict['Duration:'])}]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function t($s)
    {
        if (!isset($this->lang, self::$dictionary[$this->lang], self::$dictionary[$this->lang][$s])) {
            return $s;
        }

        return self::$dictionary[$this->lang][$s];
    }

    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) {
            return 'contains(' . $text . ',"' . $s . '")';
        }, $field)) . ')';
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

    private function normalizeDate(?string $date)
    {
//        $this->logger->debug('date begin = ' . print_r( $date, true));

        $in = [
            // Thu, Mar 23, 2023, 10:50 AM
            '/^\s*[\w\-]+\s+(\w+)\s+(\d+),\s*(\d{4})\s*,\s*(\d{1,2}:\d{2}(\s*[ap]m)?)\s*$/ui',
            // mar., 27 déc., 2022, 06:00
            '/^\s*[\w\-]+[.]?[\s,]\s*(\d+)\s+([[:alpha:]]+)[.]?,\s*(\d{4})\s*,\s*(\d{1,2}:\d{2}(\s*[ap]m)?)\s*$/ui',
        ];
        $out = [
            '$2 $1 $3, $4',
            '$1 $2 $3, $4',
        ];

        $date = preg_replace($in, $out, $date);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }
//        $this->logger->debug('date end = ' . print_r( $date, true));

        return strtotime($date);
    }

    private function opt($field, $delimiter = '/')
    {
        $field = (array) $field;

        if (empty($field)) {
            $field = ['false'];
        }

        return '(?:' . implode("|", array_map(function ($s) use ($delimiter) {
            return str_replace(' ', '\s+', preg_quote($s, $delimiter));
        }, $field)) . ')';
    }

    private function getTotal($text)
    {
        $text = trim($text);
        $result = ['amount' => null, 'currency' => null];

        if (preg_match('/^\s*\d[\d\.\, ]*\s*$/', $text)) {
            $text .= ' USD';
        }

        if (preg_match("#^\s*(?<currency>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $text, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d\s]{1,5})\s*$#", $text, $m)
            // $232.83 USD
            || preg_match("#^\s*\D{1,5}(?<amount>\d[\d\., ]*)\s*(?<currency>[A-Z]{3})\s*$#", $text, $m)
        ) {
            $m['currency'] = $this->currency($m['currency']);
            $m['amount'] = PriceHelper::parse($m['amount']);

            if (is_numeric($m['amount'])) {
                $m['amount'] = (float) $m['amount'];
            } else {
                $m['amount'] = null;
            }
            $result = ['amount' => $m['amount'], 'currency' => $m['currency']];
        }

        return $result;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function currency($s)
    {
        if ($code = $this->re("#^\s*([A-Z]{3})\s*$#", $s)) {
            return $code;
        }
        $sym = [
            '$' => 'USD',
        ];

        foreach ($sym as $f => $r) {
            if ($s == $f) {
                return $r;
            }
        }

        return null;
    }
}
