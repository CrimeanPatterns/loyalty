<?php

namespace AwardWallet\Engine\barcelo\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class Booking2 extends \TAccountChecker
{
    public $mailFiles = "barcelo/it-378721075.eml, barcelo/it-388678565-es.eml";

    public $lang = '';

    public static $dictionary = [
        'es' => [
            'confNumber'          => ['Localizador:', 'Localizador :'],
            'checkIn'             => ['Fecha de entrada:', 'Fecha de entrada :'],
            'checkOut'            => ['Fecha de salida:', 'Fecha de salida :'],
            'Customer details'    => 'Datos del cliente',
            'Name:'               => 'Nombre:',
            'Booking information' => 'Datos de la reserva',
            // 'Hotel:' => '',
            'Address:'                          => 'Dirección:',
            'Telephone:'                        => 'Teléfono:',
            'Nights:'                           => 'Noches:',
            'From:'                             => 'Desde las:',
            'Before:'                           => 'Antes de las:',
            'Room'                              => 'Habitación',
            'statusVariants'                    => ['confirmada'],
            'adult'                             => 'adulto',
            'children'                          => 'niño',
            'Total room amount'                 => 'Importe total habitación',
            'Cancellation and no-show policies' => 'Políticas de cancelación y No Show',
            '- Cancellation:'                   => '- De cancelación:',
        ],
        'en' => [
            'confNumber'          => ['Locator:', 'Locator :', 'Booking reference:', 'Booking reference :'],
            'checkIn'             => ['Arrival date:', 'Arrival date :'],
            'checkOut'            => ['Departure date:', 'Departure date :'],
            'Booking information' => ['Booking information', 'Booking details'],
            'statusVariants'      => ['confirmed'],
        ],
    ];

    private $subjects = [
        'es' => [', aquí tiene su confirmación de reserva en'],
        'en' => [', here is your booking confirmation for'],
    ];

    private $patterns = [
        'time'          => '\d{1,2}(?:[.:：]\d{2})?(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    18.00    |    2:00 p. m.    |    3pm
        'phone'         => '[+(\d][-+. \d)(]{5,}[\d)]', // +377 (93) 15 48 52    |    (+351) 21 342 09 07    |    713.680.2992
        'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@barcelo.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (is_string($phrase) && stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//a[contains(@href,".barcelo.com/") or contains(@href,"www.barcelo.com") or contains(@href,"booking.barcelo.com")]')->length === 0
        ) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }
        $email->setType('Booking2' . ucfirst($this->lang));

        $locator = $this->http->FindSingleNode("//text()[{$this->eq($this->t('confNumber'))}]/ancestor::tr[1]");

        if (preg_match("/^({$this->opt($this->t('confNumber'))})[:\s]*([-A-z\d]{5,})$/", $locator, $m)) {
            $email->ota()->confirmation($m[2], trim($m[1], ': '));
        }

        $customerName = $this->http->FindSingleNode("//*[{$this->eq($this->t('Customer details'))}]/following::tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Name:'))}] ]/*[normalize-space()][2]", null, true, "/^{$this->patterns['travellerName']}$/u");

        $hotelName = $this->http->FindSingleNode("//*[{$this->eq($this->t('Booking information'))}]/following::tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Hotel:'))}] ]/*[normalize-space()][2]");
        $address = $this->http->FindSingleNode("//*[{$this->eq($this->t('Booking information'))}]/following::tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Address:'))}] ]/*[normalize-space()][2]");
        $phone = $this->http->FindSingleNode("//*[{$this->eq($this->t('Booking information'))}]/following::tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Telephone:'))}] ]/*[normalize-space()][2]", null, true, "/^{$this->patterns['phone']}$/");

        $hotels = $this->http->XPath->query("//*[ tr[normalize-space()][1][{$this->starts($this->t('checkIn'))}] and tr[normalize-space()][2][{$this->starts($this->t('checkOut'))}] and tr[normalize-space()][3][{$this->starts($this->t('Nights:'))}] ]");

        foreach ($hotels as $hRoot) {
            $h = $email->add()->hotel();
            $h->general()->noConfirmation();
            $h->general()->traveller($customerName, true);
            $h->hotel()->name($hotelName)->address($address)->phone($phone);
            $this->parseHotel($h, $hRoot);
        }

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

    private function parseHotel(\AwardWallet\Schema\Parser\Common\Hotel $h, \DOMNode $root): void
    {
        $dateCheckIn = strtotime(Booking::normalizeDate($this->http->FindSingleNode("descendant::tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('checkIn'))}] ]/*[normalize-space()][2]", $root, true, "/^.*\d.*$/"), $this->lang));
        $dateCheckOut = strtotime(Booking::normalizeDate($this->http->FindSingleNode("descendant::tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('checkOut'))}] ]/*[normalize-space()][2]", $root, true, "/^.*\d.*$/"), $this->lang));
        $timeCheckIn = $this->normalizeTime($this->http->FindSingleNode("descendant::tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('From:'))}] ]/*[normalize-space()][2]", $root, true, "/^{$this->patterns['time']}/"));
        $timeCheckOut = $this->normalizeTime($this->http->FindSingleNode("descendant::tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Before:'))}] ]/*[normalize-space()][2]", $root, true, "/^{$this->patterns['time']}/"));

        if ($dateCheckIn && $timeCheckIn) {
            $h->booked()->checkIn(strtotime($timeCheckIn, $dateCheckIn));
        }

        if ($dateCheckOut && $timeCheckOut) {
            $h->booked()->checkOut(strtotime($timeCheckOut, $dateCheckOut));
        }

        $roomStatus = $this->http->FindSingleNode("preceding::tr[not(.//tr) and normalize-space()][position()<3][{$this->starts($this->t('Room'))}]", $root, true, "/^{$this->opt($this->t('Room'))}[:\s]+({$this->opt($this->t('statusVariants'))})$/");
        $h->general()->status($roomStatus);

        $room = $h->addRoom();

        $roomType = $this->http->FindSingleNode("following::tr[not(.//tr) and normalize-space()][position()<3]/*[ normalize-space() and following-sibling::*[{$this->contains($this->t('adult'))}] and following-sibling::*[{$this->contains($this->t('children'))}] ]", $root);
        $room->setType($roomType);

        $adults = $this->http->FindSingleNode("following::tr[not(.//tr) and normalize-space()][position()<3]/*[{$this->contains($this->t('adult'))}]", $root, true, "/^(\d{1,3})(?:[ ]*\([ ]*[-,\d ]+[ ]*\))?[ ]*{$this->opt($this->t('adult'))}/");
        $h->booked()->guests($adults);

        $kids = $this->http->FindSingleNode("following::tr[not(.//tr) and normalize-space()][position()<3]/*[{$this->contains($this->t('children'))}]", $root, true, "/^(\d{1,3})(?:[ ]*\([ ]*[-,\d ]+[ ]*\))?[ ]*{$this->opt($this->t('children'))}/");
        $h->booked()->kids($kids);

        $totalPrice = $this->http->FindSingleNode("following::tr[not(.//tr) and normalize-space()][position()<20][{$this->eq($this->t('Total room amount'))}]/following::tr[not(.//tr) and normalize-space()][1]", $root, true, '/^.*\d.*$/');

        if (preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $totalPrice, $matches)) {
            // USD 1,110.61
            $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
            $h->price()->currency($matches['currency'])->total(PriceHelper::parse($matches['amount'], $currencyCode));
        }

        $cancellation = $this->http->FindSingleNode("following::tr[not(.//tr) and normalize-space()][position()<25][{$this->eq($this->t('Cancellation and no-show policies'))}]/following::tr[not(.//tr) and normalize-space()][position()<5][{$this->eq($this->t('- Cancellation:'))}]/following::tr[not(.//tr) and normalize-space()][1]", $root);
        $h->general()->cancellation($cancellation, false, true);

        if (preg_match("/You (?i)can make any change or cancell?ation to your reservation up to\s+(?<prior>\d{1,3} days?)\s+before your arrival\s*\(\s*only until\s+(?<hour>{$this->patterns['time']})\s*\)\s*(?:[.;!]|$)/", $cancellation, $m) // en
        ) {
            $h->booked()->deadlineRelative($m['prior'], $m['hour']);
        } elseif (preg_match("/You (?i)can make any change or cancell?ation to your booking free of charge up until the day you arrive at the hotel\s*\(\s*only until\s+(?<hour>{$this->patterns['time']})\s*\)\s*(?:[.;!]|$)/", $cancellation, $m) // en
        ) {
            $h->booked()->deadlineRelative('1 days', $m['hour']);
        } elseif (preg_match("/^Recuerde (?i)al hacer su reserva que su estancia no admite cancelación\./", $cancellation) // es
        ) {
            $h->booked()->nonRefundable();
        }
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['checkIn']) || empty($phrases['checkOut'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[{$this->contains($phrases['checkIn'])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->contains($phrases['checkOut'])}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function t(string $phrase, string $lang = '')
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return $phrase;
        }

        if ($lang === '') {
            $lang = $this->lang;
        }

        if (empty(self::$dictionary[$lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$lang][$phrase];
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'contains(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'normalize-space(' . $node . ')=' . $s;
        }, $field)) . ')';
    }

    private function starts($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'starts-with(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
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

    private function normalizeTime(?string $s): string
    {
        $s = preg_replace('/(\d)[ ]*[.][ ]*(\d)/', '$1:$2', $s); // 01-55 PM    ->    01:55 PM

        return $s;
    }
}
