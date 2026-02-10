<?php

namespace AwardWallet\Engine\choice\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class ReservationConfirmationV5 extends \TAccountChecker
{
    public $mailFiles = "choice/it-175973027.eml, choice/it-44205016.eml, choice/it-56559538.eml, choice/it-66367003.eml";
    public $reBody = 'Choice';

    public static $dictionary = [
        'en' => [
            'status'            => [', your reservation is ', ', your stay is right around the corner!', ', your reservation has been '],
            'cancelledVariants' => ['cancelled', 'canceled'],
            'confirmation'      => ['Confirmation Number:'],
            'account'           => ['Account:'],
            'roomType'          => ['Room Type:'],
            'roomRate'          => ['Daily Rates:'],
            'roomCount'         => ['Number of Rooms:'],
            'guests'            => ['Number of Occupants:'],
            'tax'               => ['Estimated Tax and Other Charges:'],
            'total'             => ['Estimated Total:'],
            'cancellation'      => ['Cancellation Policy:'],
        ],

        'es' => [
            'status'            => [', su reserva está '],
            //'cancelledVariants' => [],
            'confirmation'      => ['Número de confirmación:'],
            'account'           => ['membresía:'],
            'roomType'          => ['Tipo de habitación:'],
            'roomRate'          => ['Tarifas diarias:'],
            'roomCount'         => ['Número de habitaciones:'],
            'guests'            => ['Cantidad de ocupantes:'],
            'tax'               => ['Impuestos calculados y otros cargos:'],
            'total'             => ['Total estimado:'],
            'cancellation'      => ['Política de cancelación:'],
            'Check-in:'         => 'Fecha de llegada:',
            'Check-out:'        => 'Fecha de salida:',
        ],
    ];

    public $lang = 'en';

    private $subject = [
        'en' => ['Your Reservation at the ', 'Your Stay With Us Is Just Days Away', 'Your Reservation Has Been Cancelled'],
        'es' => ['Su reserva en'],
    ];
    private $reBody2 = [
        'en' => [
            ', your reservation is confirmed.',
            'stay is right around the corner!',
            ', your reservation has been cancelled.',
            ', your reward reservation is confirmed',
            'shared their travel plans with you',
            'your reward night reservation is confirmed',
            'Your reward stay is right around the corner',
        ],
        'es' => [
            ', su reserva está confirmada',
        ],
    ];

    private $patterns = [
        'time' => '\d{1,2}(?:[:]\d{2})?(?:\s*[AaPp]\.?[Mm]\.?)?',
    ];

    private $enDatesInverted = false;

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@choicehotels.com') !== false || stripos($from, '@your.choicehotels.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->subject as $re) {
            foreach ($re as $phrase) {
                if (strpos($headers["subject"], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//*[{$this->contains($this->reBody)}]")->length > 0) {
            foreach ($this->reBody2 as $re) {
                if ($this->http->XPath->query("//*[{$this->contains($re)}]")->length > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->http->FilterHTML = false;

        foreach ($this->reBody2 as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if ($this->http->XPath->query("//*[{$this->contains($phrase)}]")->length > 0) {
                    $this->lang = $lang;

                    break;
                }
            }
        }

        $this->parseHtml($email);
        $this->parseStatement($email);

        $a = explode('\\', __CLASS__);
        $email->setType($a[count($a) - 1] . ucfirst($this->lang));

        return $email;
    }

    private function parseHtml(Email $email)
    {
        $r = $email->add()->hotel();

        $status = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Reservation Status:'))}]/following::text()[normalize-space()][1]", null, true, '/^\w+$/u');

        if (empty($status)) {
            $status = beautifulName($this->http->FindSingleNode("(//text()[{$this->contains($this->t('status'))}])[1]", null, false, "/{$this->opt($this->t('status'))}(\w+)\./"));
        }

        if (!empty($status)) {
            $r->general()->status($status);
        }

        if (preg_match("/^{$this->opt($this->t('cancelledVariants'))}$/i", $r->getStatus())) {
            $r->general()->cancelled();
        }

        $cancellationNumber = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Cancellation Number:'))}]/following::text()[normalize-space()][1]", null, true, '/^[-A-Z\d]{5,}$/');
        $r->general()->cancellationNumber($cancellationNumber, false, true);

        $confirmation = $this->http->FindSingleNode("//text()[{$this->eq($this->t('confirmation'))}]/ancestor::tr[1]");

        if (empty($confirmation)) {
            $confirmation = $this->http->FindSingleNode("(//text()[{$this->contains($this->t('confirmation'))}])[1]");
        }

        if (preg_match("/^(.+?):\s*(.+)/", $confirmation, $matches)) {
            $r->general()
                ->confirmation($matches[2], $matches[1]);

            $traveller = $this->http->FindSingleNode("(//text()[{$this->contains($this->t('status'))}])[1]", null,
                false, "/^([\w\s]+){$this->opt($this->t('status'))}/");
        }

        if (empty($traveller)) {
            $traveller = $this->http->FindSingleNode("(//text()[{$this->contains($this->t(',your stay is right around the corner!'))}])[1]",
                null, false, "/^([\w\s]+){$this->opt($this->t(',your stay is right around the corner!'))}/");
        }

        if (empty($traveller)) {
            $traveller = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Guest Name:')]", null, true, "/{$this->opt($this->t('Guest Name:'))}\s*(.+)/");
        }

        if (!empty($traveller) && strlen($traveller) >= 3) {
            $r->general()->traveller($traveller, false);
        }

        if ($account = $this->http->FindSingleNode("(//text()[{$this->contains($this->t('account'))}])[1]/following-sibling::*[1]",
            null, false, '/#\s*(.+)/')) {
            $r->program()->account($account, false);
        }

        $xpathHotel = "//img[@height='15' or (contains(@src,'/pinpoint.png') or contains(@src,'/pinpoint_black.png'))]/ancestor::tr[1][count(../ancestor::table[1]//img[@height='15'])=3]";
        $r->hotel()
            ->name($this->http->FindSingleNode("({$xpathHotel}/preceding-sibling::tr/td)[1]"))
            ->address($this->http->FindSingleNode("({$xpathHotel}/descendant::td[1])[1]"))
            ->phone($this->http->FindSingleNode("({$xpathHotel}/following-sibling::tr/td)[1]"));

        $checkIn = implode(' ',
            $this->http->FindNodes("//tr[{$this->eq($this->t('Check-in:'))}]/following-sibling::tr[normalize-space()][last()]/descendant::text()[normalize-space()]"));

        if ($checkIn) {
            $r->booked()->checkIn2($this->normalizeDate($checkIn));
        }

        $checkOut = implode(' ',
            $this->http->FindNodes("//tr[{$this->eq($this->t('Check-out:'))}]/following-sibling::tr[normalize-space()][last()]/descendant::text()[normalize-space()]"));

        if ($checkOut) {
            $r->booked()->checkOut2($this->normalizeDate($checkOut));
        }

        $roomType = $this->http->FindNodes("//td[{$this->eq($this->t('roomType'))}]/following-sibling::td");
        $roomRate = $this->http->FindNodes("//td[{$this->eq($this->t('roomRate'))}]/following-sibling::td");
        $guests = $this->http->FindNodes("//td[{$this->eq($this->t('guests'))}]/following-sibling::td");
        $rooms = $this->http->FindNodes("//td[{$this->eq($this->t('roomCount'))}]/following-sibling::td");

        if (count($roomType) == count($roomRate)) {
            for ($i = 0; $i < count($roomType); $i++) {
                $room = $r->addRoom();
                $room
                    ->setType($roomType[$i]);

                $rate = implode('; ', $this->http->FindNodes("(//td[{$this->eq($this->t('roomRate'))}])[" . ($i + 1) . "]/following-sibling::td/descendant::text()[normalize-space()]"));

                if (strlen($rate) < 400) {
                    $room->setRate($rate);
                }
            }
            $r->booked()
                ->rooms(array_sum($rooms))
                ->guests(array_sum($guests))
            ;
        }

        $tax = $this->getTotalCurrency($this->getField("tax"));
        $total = $this->getTotalCurrency($this->getField("total"));

        if (empty($total['Total']) && !empty($total['Currency'])) {
            $total['Total'] = PriceHelper::parse($this->http->FindSingleNode("//td[" . $this->eq($this->t("total")) . "]/following-sibling::td[normalize-space()][1]", null, true,
                "/\s([\d\.\,]+)/"), $total['Currency']);
        }

        $currencyStr = $this->http->FindSingleNode("//td[" . $this->eq($this->t("total")) . "]/following-sibling::td[normalize-space()][1]", null, true,
            "/\((.+)\)/");
        $currencyStr = str_replace(['Canadian Dollar', 'US Dollar'], ['CAD', 'USD'], $currencyStr);
        $currency = null;

        if (preg_match("/^[A-Z]{3}$/", $currencyStr)) {
            $currency = $currencyStr;
        }

        if ($tax['Total'] !== null) {
            $r->price()
                ->total($total['Total'])
                ->currency($currency ?? $total['Currency'])
                ->tax($tax['Total']);
        }

        if ($cancellation = join("\n\n",
            $this->http->FindNodes("(//strong|//b)[{$this->contains($this->t('cancellation'))}]/following-sibling::node()"))) {
            $r->general()->cancellation($cancellation);
            $this->detectDeadLine($r);
        }
    }

    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h): void
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (
               preg_match("/cancel this reservation, you may do so up until (?<date>.+?) before (?<time>{$this->patterns['time']}) hotel time/i", $cancellationText, $m)
            || preg_match("/Free Cancellation until (?<date>.+?) at (?<time>\d{1,2}:\d{2}:\d{2}(?: [AP]M)?) local hotel time\./", $cancellationText, $m)
        ) {
            $h->booked()->deadline2($this->normalizeDate($m['date'] . ' ' . $m['time']));

            return;
        }

        if (preg_match("/This reservation cannot be cancelled\./", $cancellationText, $m)
        ) {
            $h->booked()->nonRefundable();

            return;
        }
    }

    private function parseStatement(Email $email)
    {
        if ($account = $this->http->FindSingleNode("(//text()[{$this->contains($this->t('account'))}])[1]/following-sibling::*[1]",
            null, false, '/#\s*(.+)/')) {
            $st = $email->add()->statement();

            $st->setNumber($account);
            $st->setNoBalance(true);

            $name = $this->http->FindSingleNode("(//text()[{$this->contains($this->t('account'))}])[1]",
                null, false, "/^\s*([[:alpha:] \-\.]+)\'s {$this->opt($this->t('account'))}/");

            if (!empty($name) && strlen($name) >= 3) {
                $st->addProperty('Name', $name);
            }

            $status = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Membership Level:'))}]/following::text()[normalize-space()][1]");

            if (!empty($status)) {
                $st->addProperty('ChoicePrivileges', $status);
            }
        }

        return $email;
    }

    private function getField($field)
    {
        return $this->http->FindSingleNode("//text()[{$this->eq($this->t($field))}]/following::text()[normalize-space(.)!=''][1]");
    }

    private function t(string $phrase)
    {
        if (!isset(self::$dictionary, $this->lang) || empty(self::$dictionary[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$this->lang][$phrase];
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ',
                array_map(function ($s) use ($node) {
                    return 'normalize-space(' . $node . ')="' . $s . '"';
                }, $field)) . ')';
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function normalizeDate(?string $text): string
    {
        if (!is_string($text) || empty($text)) {
            return '';
        }

        if (preg_match('/\b(\d{1,2})\/\d{1,2}\/\d{4}\b/', $text, $m)) {
            if ($m[1] > 12) {
                $this->enDatesInverted = true;
            }
        }
        $in = [
            // Tue, Sep 17, 2019 3:00 PM
            "/^[[:alpha:]]{2,}\s*,\s*([[:alpha:]]{3,})\s+(\d{1,2})\s*,\s*(\d{2,4})\s+({$this->patterns['time']})$/u",
            // Sat, 23/11/2019 2:00 PM
            "/^[[:alpha:]]{2,}\s*,\s*(\d{1,2})\/(\d{1,2})\/(\d{2,4})\s+({$this->patterns['time']})$/u",
            // Sat, 23/11/2019 2:00 PM
            "/^(\d+)\/(\d+)\/(\d+)\s*(\d+\:\d+)\:\d+\s*(A?P?M)$/u",
        ];
        $out[0] = '$2 $1 $3, $4';
        $out[1] = '$1.$2.$3, $4';
        $out[2] = '$1.$2.$3, $4 $5';

        return preg_replace($in, $out, $text);
    }

    private function getTotalCurrency($node): array
    {
        $node = str_replace(["€", "£", "$", "₹", "kr "], ["EUR", "GBP", "USD", "INR", "NOK"], $node);
        $tot = null;
        $cur = null;

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)
            || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m)
        ) {
            if (strpos($m['t'], ".") === false) {
                $m['t'] = str_replace(",", ".", $m['t']);
            } else {
            }
            $cur = $m['c'];
            $tot = PriceHelper::cost($m['t']);
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }
}
