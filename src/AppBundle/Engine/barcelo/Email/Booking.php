<?php

namespace AwardWallet\Engine\barcelo\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class Booking extends \TAccountChecker
{
    public $mailFiles = "barcelo/it-191069130-es.eml, barcelo/it-192162135.eml, barcelo/it-624015713.eml";

    public $lang = '';

    public static $dictionary = [
        'es' => [
            'checkIn'            => ['Fecha de entrada'],
            'checkOut'           => ['Fecha de salida'],
            'Dear'               => 'Estimado/a',
            'statusPhrases'      => ['Reserva'],
            'statusVariants'     => ['confirmada'],
            'Locator:'           => 'Localizador:',
            'From'               => 'Desde las',
            'Before'             => 'Antes de las',
            'Room'               => 'Habitación',
            'adults'             => ['adulto', 'adultos'],
            'child'              => ['niño', 'niños'],
            'totalAmount'        => 'Importe total (Impuestos Incluidos)',
            'cancellationHeader' => 'Políticas de cancelación y No Show',
        ],
        'fr' => [
            'checkIn'            => ['Date d’arrivée'],
            'checkOut'           => ['Date de départ'],
            'Dear'               => 'Cher/Chère',
            'statusPhrases'      => ['Réservation'],
            'statusVariants'     => ['confirmée'],
            'Locator:'           => 'Référence:',
            'From'               => 'À partir de',
            'Before'             => 'Avant',
            'Room'               => 'Chambre',
            'adults'             => ['adultes'],
            'child'              => ['enfants'],
            'totalAmount'        => 'Montant total (taxes incluses)*',
            'cancellationHeader' => 'Politiques d’annulation et de non-présentation',
        ],
        'en' => [
            'checkIn'            => ['Date of arrival'],
            'checkOut'           => ['Date of departure'],
            'statusPhrases'      => ['Booking'],
            'statusVariants'     => ['confirmed'],
            'adults'             => ['adult', 'adults'],
            'child'              => ['children'],
            'totalAmount'        => 'Total amount (taxes included)',
            'cancellationHeader' => 'Cancellation and no-show policies',
        ],
    ];

    private $subjects = [
        'fr' => ['Nous vous remercions pour votre réservation'],
        'es' => [', aquí tiene su confirmación de reserva en'],
        'en' => [', here is your booking confirmation for '],
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
        $email->setType('Booking' . ucfirst($this->lang));

        $hotelRoots = $this->http->XPath->query("//text()[{$this->starts($this->t('Locator:'))}]/ancestor::*[ descendant::tr[{$this->eq($this->t('cancellationHeader'))}] ][1]");

        if ($hotelRoots->length < 2) {
            $this->parseHotel($email);
        } else {
            foreach ($hotelRoots as $hRoot) {
                $this->parseHotel($email, $hRoot);
            }
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

    /**
     * Dependencies `use AwardWallet\Engine\MonthTranslate`.
     *
     * @param string|null $text Unformatted string with date
     * @param string $lang String with document language
     */
    public static function normalizeDate(?string $text, string $lang): ?string // used in barcelo/Booking2
    {
        if (preg_match('/^(\d{1,2})[-.\s]+([[:alpha:]]{3,})[-.\s]+(\d{4})$/', $text, $m)) {
            // 07 September 2022
            $day = $m[1];
            $month = $m[2];
            $year = $m[3];
        } elseif (preg_match('/^[-[:alpha:]]{2,}[,.\s]+([[:alpha:]]{3,})\s+(\d{1,2})[,.\s]+(\d{4})$/', $text, $m)) {
            // Monday, November 21, 2022
            $month = $m[1];
            $day = $m[2];
            $year = $m[3];
        } elseif (preg_match('/^[-[:alpha:]]{2,}[,.\s]+(\d{1,2})(?:\s+de)?\s+([[:alpha:]]{3,})\s+(?:de\s+)?(\d{4})$/u', $text, $m)) {
            // martes, 30 de agosto de 2022
            $day = $m[1];
            $month = $m[2];
            $year = $m[3];
        }

        if (isset($day, $month, $year)) {
            if (preg_match('/^\s*(\d{1,2})\s*$/', $month, $m)) {
                return $m[1] . '/' . $day . ($year ? '/' . $year : '');
            }

            if (($monthNew = MonthTranslate::translate($month, $lang)) !== false) {
                $month = $monthNew;
            }

            return $day . ' ' . $month . ($year ? ' ' . $year : '');
        }

        return null;
    }

    private function parseHotel(Email $email, ?\DOMNode $root = null): void
    {
        $xpathNoEmpty = 'string-length(normalize-space())>1';

        $patterns = [
            'travellerName' => '[[:alpha:](][-)(.\'’[:alpha:]\d ]*[[:alpha:].)\d]', // Marta marta.cirelli96    |    Taylor Vincent (Sjolund)    |    Yolanda M.
        ];

        $h = $email->add()->hotel();

        $traveller = null;
        $travellerNames = array_filter($this->http->FindNodes("descendant::text()[{$this->starts($this->t('Dear'))}]", $root, "/^{$this->opt($this->t('Dear'))}[,\s]+({$patterns['travellerName']})(?:\s*[,;:!?]|$)/u"));

        if (count(array_unique($travellerNames)) === 1) {
            $traveller = array_shift($travellerNames);
        }
        $h->general()->traveller($traveller, true);

        $xpathHotel = "descendant::tr[ *[1][normalize-space()='']/descendant::img[contains(@src,'/direccion.') or contains(@alt,'dirección')] and *[3][normalize-space()] ]/ancestor-or-self::tr[ preceding-sibling::tr[normalize-space()] or following-sibling::tr[normalize-space()] ][1]";

        if ($this->http->XPath->query($xpathHotel, $root)->length !== 1) {
            $xpathHotel = "descendant::*[ *[normalize-space()][1][not(descendant::img)] and count(*[normalize-space()])=4 and count(*[count(descendant::img)=1 and normalize-space()])=3 and *[normalize-space()][3][contains(.,'@')] ]/*[normalize-space()][2]";
        }

        $hotelName = $this->http->FindSingleNode($xpathHotel . "/preceding-sibling::tr[normalize-space()]", $root);
        $address = implode(', ', $this->http->FindNodes($xpathHotel . "/descendant::text()[normalize-space()]", $root));
        $phone = null;
        $phoneTexts = array_filter($this->http->FindNodes($xpathHotel . "/following-sibling::tr[normalize-space()]", $root, '/^[+(\d][-+. \d)(]{5,}[\d)]$/'));

        if (count(array_unique($phoneTexts)) === 1) {
            $phone = array_shift($phoneTexts);
        }
        $h->hotel()->name($hotelName)->address($address)->phone($phone, false, true);

        $status = null;
        $statusTexts = array_filter($this->http->FindNodes("descendant::text()[{$this->contains($this->t('statusPhrases'))}]", $root, "/^{$this->opt($this->t('statusPhrases'))}[:\s]+({$this->opt($this->t('statusVariants'))})(?:\s*[,;:!?]|$)/"));

        if (count(array_unique($statusTexts)) === 1) {
            $status = array_shift($statusTexts);
            $h->general()->status($status);
        }

        $confirmation = $this->http->FindSingleNode($xpathHotel . "/ancestor::*[ descendant::text()[{$this->starts($this->t('Locator:'))}] ][1]/descendant::text()[{$this->starts($this->t('Locator:'))}]/following::text()[normalize-space()][1]", $root, true, '/^[A-Z\d]{5,}$/');

        if ($confirmation) {
            $confirmationTitle = $this->http->FindSingleNode($xpathHotel . "/ancestor::*[ descendant::text()[{$this->starts($this->t('Locator:'))}] ][1]/descendant::text()[{$this->starts($this->t('Locator:'))}]", $root, true, '/^(.+?)[\s:：]*$/u');
            $h->general()->confirmation($confirmation, $confirmationTitle);
        }

        $xpathCheckIn = "descendant::*[ *[normalize-space()][1][{$this->eq($this->t('checkIn'))}] and *[normalize-space()][2] ]";
        $xpathCheckOut = "descendant::*[ *[normalize-space()][1][{$this->eq($this->t('checkOut'))}] and *[normalize-space()][2] ]";

        $dateCheckIn = strtotime($this->normalizeDate($this->http->FindSingleNode($xpathCheckIn . "/*[normalize-space()][2]", $root, true, '/^.*\d.*$/'), $this->lang));
        $dateCheckOut = strtotime($this->normalizeDate($this->http->FindSingleNode($xpathCheckOut . "/*[normalize-space()][2]", $root, true, '/^.*\d.*$/'), $this->lang));

        $patterns['time'] = '\d{1,2}(?:[:：]\d{2})?(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?'; // 4:19PM    |    2:00 p. m.    |    3pm

        $timeCheckIn = $this->http->FindSingleNode($xpathCheckIn . "/*[normalize-space()][3]", $root, true, "/^(?:{$this->opt($this->t('From'))}\s+)?({$patterns['time']})/");
        $timeCheckOut = $this->http->FindSingleNode($xpathCheckOut . "/*[normalize-space()][3]", $root, true, "/^(?:{$this->opt($this->t('Before'))}\s+)?({$patterns['time']})/");

        $h->booked()->checkIn(strtotime($timeCheckIn, $dateCheckIn))->checkOut(strtotime($timeCheckOut, $dateCheckOut));

        $xpathRoom = "descendant::tr[ *[not(.//tr) and normalize-space()][1][{$this->eq($this->t('Room'))}] ]/following::tr[normalize-space()][position()<6]/*[not(.//tr) and descendant::img[contains(@src,'/habitacion.')] and normalize-space()='']/following-sibling::*[{$xpathNoEmpty}][1]";

        if ($this->http->XPath->query($xpathRoom, $root)->length !== 1) {
            $xpathRoom = "descendant::tr[ *[normalize-space()][1][{$this->eq($this->t('Room'))}] ]/following::tr[ count(*[count(descendant::img)=1 and normalize-space()=''])=3 and count(*[not(descendant::img) and {$xpathNoEmpty}])=3 and *[{$xpathNoEmpty}][2][{$this->contains($this->t('adults'))}] and *[{$xpathNoEmpty}][3][{$this->contains($this->t('child'))}] ]/*[{$xpathNoEmpty}][1]";
        }

        $roomType = $this->http->FindSingleNode($xpathRoom, $root);
        $adults = $this->http->FindSingleNode($xpathRoom . "/following-sibling::*[{$xpathNoEmpty}][ {$this->contains($this->t('adults'))} or preceding-sibling::*[descendant::img and normalize-space()=''][1]/descendant::img[contains(@src,'/adultos.')] ]", $root, true, "/^(\d{1,3})\s*{$this->opt($this->t('adults'))}$/");
        $kids = $this->http->FindSingleNode($xpathRoom . "/following-sibling::*[{$xpathNoEmpty}][ {$this->contains($this->t('child'))} or preceding-sibling::*[descendant::img and normalize-space()=''][1]/descendant::img[contains(@src,'/ninos.')] ]", $root, true, "/^(\d{1,3})(?:\s*\(\s*\d[-,\s\d]*\s*\))?\s*{$this->opt($this->t('child'))}$/");

        $room = $h->addRoom();
        $room->setType($roomType);
        $roomCount = count($this->http->FindNodes("//text()[{$this->eq($this->t('Room'))}]"));

        $h->booked()->guests($adults)->kids($kids)->rooms($roomCount);

        $totalPrice = $this->http->FindSingleNode("descendant::tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->starts($this->t('totalAmount'))}] ]/*[normalize-space()][2]", $root, true, '/^.*\d.*$/');

        if (preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*)$/', $totalPrice, $matches)) {
            // USD 2,036.23
            $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
            $h->price()->currency($matches['currency'])->total(PriceHelper::parse($matches['amount'], $currencyCode));
        }

        $cancellation = implode(' ', $this->http->FindNodes("descendant::tr[{$this->eq($this->t('cancellationHeader'))}]/following-sibling::tr[normalize-space()][1]/descendant::text()[normalize-space()]", $root));
        $h->general()->cancellation($cancellation);

        if (preg_match("/when making your (?:reservation|booking), your stay does not permit cancell?ation(?:\s*[.;!?]|$)/i", $cancellation) // en
            || preg_match("/al hacer su reserva que su estancia no admite cancell?ación(?:\s*[.;!?]|$)/iu", $cancellation) // es
        ) {
            $h->booked()->nonRefundable();
        }

        if (preg_match("/modifier ou annuler gratuitement votre réservation jusqu’à\s*(\d+)\s*jours avant votre arrivée\s*\(jusqu’à\s+(\d+)h\)/i", $cancellation, $m)
        ) {
            $h->booked()->deadlineRelative($m[1] . ' days', $m[2] . ':00');
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
}
