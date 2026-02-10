<?php

namespace AwardWallet\Engine\bookeo\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Common\Event;
use AwardWallet\Schema\Parser\Email\Email;

class Booking extends \TAccountChecker
{
    public $mailFiles = "bookeo/it-253820067.eml, bookeo/it-257007813.eml, bookeo/it-364407580.eml, bookeo/it-374422321-it.eml, bookeo/it-375180989-v2.eml";

    public $lang = '';

    public static $dictionary = [
        'it' => [
            'statusPhrases'  => ['La tua prenotazione è'],
            'statusVariants' => ['confermata'],
            // 'altEventTitles' => '',
            'view map'       => 'visualizza mappa',
            'Phone:'         => 'Telefono:',
            'bookingDetails' => 'Dettagli prenotazione',
            'confNumber'     => ['Numero prenotazione'],
            'Message'        => 'Messaggio',
            // 'Participants' => '',
            // 'adult' => '',
            // 'traveller' => '',
            // 'child' => '',
            // 'meetingPoint' => '',
            'Total price'         => 'Prezzo totale',
            'Cancellation policy' => ['Politica di cancellazione', 'Politica di cancellazione:'],
        ],
        'en' => [
            'statusPhrases'       => ['Your booking is', 'Your booking has been', 'Your payment has been'],
            'statusVariants'      => ['confirmed', 'updated', 'received', 'accepted'],
            'altEventTitles'      => ['Tour:', 'Game:', 'Expedition:', 'Experience:'],
            'Phone:'              => ['Phone:', 'Phone :'],
            'bookingDetails'      => 'Booking details',
            'confNumber'          => ['Booking number'],
            'traveller'           => ['traveller', 'traveler', 'visitor', 'guest', 'player', 'people'],
            'meetingPoint'        => ['Meeting Point and Maps:', 'Meeting Point and Maps :', 'Directions to the departure point:', 'Directions to the departure point :'],
            'Cancellation policy' => ['Cancellation policy', 'Cancelation policy'],
        ],
    ];

    private $subjects = [
        'it' => ['Prenotazione confermata -'],
        'en' => ['Booking confirmed -', 'Booking updated -'],
    ];

    private $xpath = [
        'bold' => '(self::b or self::strong or contains(translate(@style," ",""),"font-weight:bold"))',
    ];

    private $patterns = [
        'time'  => '\d{1,2}(?:[:：]\d{2})?(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.    |    3pm
        'phone' => '[+(\d][-+. \d)(]{5,}[\d)]', // +377 (93) 15 48 52    |    (+351) 21 342 09 07    |    713.680.2992
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@bookeo.com') !== false;
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
            && $this->http->XPath->query('//a[contains(@href,"//bookeo.com/") or contains(@href,"www.bookeo.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"powered by Bookeo")]')->length === 0
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

        $ev = $email->add()->event();
        $ev->place()->type(Event::TYPE_EVENT);

        $dateRow = $this->findDateRow();

        if ($dateRow->length === 1) {
            $this->parseEventV2($ev, $dateRow->item(0));
        } else {
            $this->parseEventV1($ev);
        }

        $statusTexts = array_filter($this->http->FindNodes("//text()[{$this->contains($this->t('statusPhrases'))}]", null, "/{$this->opt($this->t('statusPhrases'))}[:\s]+({$this->opt($this->t('statusVariants'))})(?:\s*[,.;:!?]|$)/i"));

        if (count(array_unique($statusTexts)) === 1) {
            $status = array_shift($statusTexts);
            $ev->general()->status($status);
        }

        $confirmation = $this->http->FindSingleNode("//*[{$this->eq($this->t('bookingDetails'))}]/following::tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->starts($this->t('confNumber'))}] ]/*[normalize-space()][2]", null, true, '/^[A-Z\d]{5,}$/');

        if ($confirmation) {
            $confirmationTitle = $this->http->FindSingleNode("//*[{$this->eq($this->t('bookingDetails'))}]/following::tr[count(*[normalize-space()])=2]/*[normalize-space()][1][{$this->starts($this->t('confNumber'))}]", null, true, '/^(.+?)[\s:：]*$/u');
            $ev->general()->confirmation($confirmation, $confirmationTitle);
        }

        $email->setType('Booking' . ucfirst($this->lang));

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary) * 2;
    }

    private function parseEventV1(Event $ev): void
    {
        // examples: it-253820067.eml, it-257007813.eml, it-364407580.eml, it-374422321-it.eml

        $this->xpath['message'] = "//table[{$this->eq($this->t('Message'))}]/following-sibling::*[normalize-space()][1]";

        $address = $phone = null;

        $headerText = $this->htmlToText($this->http->FindHTMLByXpath("//img[{$this->eq(['address', 'Address', 'ADDRESS', 'call', 'Call', 'CALL', 'email', 'Email', 'EMAIL', 'web', 'Web', 'WEB'], '@alt')}]/ancestor::*[descendant::text()[normalize-space()][2] and descendant::br][1]"));
        $this->logger->debug("Header text:\n" . $headerText);

        $address = $this->getAddress($headerText);

        if (preg_match("/\([ ]*{$this->opt($this->t('view map'))}[ ]*\)[ ]*\n+[ ]*(?:{$this->opt($this->t('Phone:'))}[ ]*)?({$this->patterns['phone']})[ ]*$/m", $headerText, $m)) {
            $phone = $m[1];
        } else {
            $phone = $this->http->FindSingleNode("//img[{$this->eq(['call', 'Call', 'CALL'], '@alt')}]/following::text()[normalize-space() and not({$this->eq($this->t('Phone:'))})][1]", null, true, "/^(?:{$this->opt($this->t('Phone:'))}\s*)?({$this->patterns['phone']})$/");
        }

        $ev->place()->address($address)->phone($phone, false, true);

        $xpath = "descendant::*[ *[normalize-space()][2] and preceding::text()[{$this->contains($this->t('statusPhrases'))}] and following::text()[{$this->eq($this->t('bookingDetails'))}] ][1]";

        $name = $this->http->FindSingleNode($xpath . "/*[normalize-space()][1]");
        $ev->place()->name($name);

        $dateText = $this->htmlToText($this->http->FindHTMLByXpath($xpath . "/*[normalize-space()][2]"));

        if (preg_match("/^\s*(?<date>.*\d.*?)[ ]*\n+[ ]*(?<time1>{$this->patterns['time']})[ ]+-[ ]+(?<time2>{$this->patterns['time']})[ ]*(?:\(|\n|$)/u", $dateText, $m)
            || preg_match("/^\s*(?<date>.*\d.*?)[ ]*\n+[ ]*(?<time1>{$this->patterns['time']})$/u", $dateText, $m)
        ) {
            $date = strtotime($this->normalizeDate($m['date']));
            $ev->booked()->start(strtotime($m['time1'], $date));

            if (empty($m['time2'])) {
                $ev->booked()->noEnd();
            } else {
                $ev->booked()->end(strtotime($m['time2'], $date));
            }

            if (!empty($ev->getStartDate()) && !empty($ev->getEndDate()) && $ev->getStartDate() > $ev->getEndDate()) {
                $ev->booked()->end(strtotime('+1 days', $ev->getEndDate()));
            }
        }

        // 5 adults , 1 child
        $participants = $this->http->FindSingleNode("//*[{$this->eq($this->t('bookingDetails'))}]/following::tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->starts($this->t('Participants'))}] ]/*[normalize-space()][2]");

        $guestCount = [];

        if (preg_match("/\b(\d{1,3})\s*{$this->opt($this->t('adult'))}/i", $participants, $m)
            || preg_match("/\b(\d{1,3})\s*{$this->opt($this->t('traveller'))}/i", $participants, $m)
        ) {
            $guestCount[] = $m[1];
        }

        if (preg_match("/\b(\d{1,3})\s*{$this->opt($this->t('child'))}/i", $participants, $m)) {
            $guestCount[] = $m[1];
        }

        if (count($guestCount) > 0) {
            $ev->booked()->guests(array_sum($guestCount));
        }

        $totalPrice = $this->http->FindSingleNode("//*[{$this->eq($this->t('bookingDetails'))}]/following::tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->starts($this->t('Total price'))}] ]/*[normalize-space()][2]", null, true, '/^(.*?\d.*?)(?:\s*,\s*\d+\s+credito prepagato|$)/i');

        if (preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.‘\'\d ]*)(?:\s*(?<currencyCode>[A-Z]{3}))?$/u', $totalPrice, $matches)
            || preg_match('/^(?<amount>\d[,.‘\'\d ]*?)[ ]*(?<currency>[^\-\d)(]+)$/u', $totalPrice, $matches)
        ) {
            // $24.12    |    $250 USD    |    170,40 €
            $currency = empty($matches['currencyCode']) ? $matches['currency'] : $matches['currencyCode'];
            $currencyCode = preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null;
            $ev->price()->currency($currency)->total(PriceHelper::parse($matches['amount'], $currencyCode));
        }

        $travellers = array_filter($this->http->FindNodes("//table[{$this->eq($this->t('Participants'))}]/following-sibling::*[normalize-space()][1]/descendant::text()[normalize-space()]", null, "/.+\d\s*[:]+\s*([[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])$/u"));

        if (count($travellers) > 0) {
            $ev->general()->travellers($travellers, true);
        }

        $cancellation = implode('; ', $this->http->FindNodes("//table[{$this->eq($this->t('Cancellation policy'))}]/following-sibling::*[normalize-space()][1]/descendant::li[normalize-space()]", null, "/^(.+?)\s*[,.:;!?]*$/"));

        if (!$cancellation) {
            $cancellation = implode('; ', $this->http->FindNodes("//table[{$this->eq($this->t('Cancellation policy'))}]/following-sibling::*[normalize-space()][1]/descendant::text()[normalize-space()]", null, "/^(.+?)\s*[,.:;!?]*$/"));
        }

        if (!$cancellation) {
            $cancellation = $this->http->FindSingleNode("//p[{$this->eq($this->t('Cancellation policy'))}]/following::p[normalize-space()][1]")
                ?? $this->http->FindSingleNode("//div[{$this->eq($this->t('Cancellation policy'))}]/following::div[normalize-space()][1]")
            ;
        }

        if ($cancellation) {
            $ev->general()->cancellation($cancellation);
        }
    }

    private function parseEventV2(Event $ev, \DOMNode $dateRow): void
    {
        // examples: it-375180989-v2.eml

        $address = $phone = null;

        $headerText = $this->htmlToText($this->http->FindHTMLByXpath("//text()[{$this->eq($this->t('view map'))}]/ancestor::*[descendant::text()[normalize-space()][2] and descendant::br][1]"));

        if (empty($headerText)) {
            $headerText = $this->htmlToText($this->http->FindHTMLByXpath("//text()[{$this->contains($this->t('statusPhrases'))}]/preceding::text()[normalize-space()][position()<10][{$this->starts($this->t('Phone:'))}]/ancestor::*[descendant::text()[normalize-space()][2] and descendant::br][1]"));
        }

        $this->logger->debug("Header text:\n" . $headerText);

        $address = $this->getAddress($headerText);

        if (preg_match("/\([ ]*{$this->opt($this->t('view map'))}[ ]*\)[ ]*\n+[ ]*(?:{$this->opt($this->t('Phone:'))}[ ]*)?({$this->patterns['phone']})[ ]*$/m", $headerText, $m)) {
            $phone = $m[1];
        }

        $ev->place()->address($address)->phone($phone, false, true);

        $date = strtotime($this->normalizeDate($this->http->FindSingleNode("*[normalize-space()][2]", $dateRow)));
        $time = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Time:'))}] ]/*[normalize-space()][2]");

        if (preg_match("/^(?<time1>{$this->patterns['time']})\s+[-–]+\s+(?<time2>{$this->patterns['time']})(?:\s*\(|$)/", $time, $m)) {
            // 2:00 PM - 5:00 PM
            $ev->booked()->start(strtotime($m['time1'], $date))->end(strtotime($m['time2'], $date));
        } elseif (preg_match("/^({$this->patterns['time']})(?:\s*\(|$)/", $time, $m)) {
            // 8:00 AM
            $ev->booked()->start(strtotime($m[1], $date))->noEnd();
        }

        $eventName = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Event:'))}] ]/*[normalize-space()][2]")
            ?? $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('altEventTitles'))}] ]")
        ;
        $ev->place()->name($eventName);

        $participants = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Participants:'))}] ]/*[normalize-space()][2]");

        $guestCount = [];

        if (preg_match("/\b(\d{1,3})\s*{$this->opt($this->t('adult'))}/i", $participants, $m)
            || preg_match("/\b(\d{1,3})\s*{$this->opt($this->t('traveller'))}/i", $participants, $m)
        ) {
            $guestCount[] = $m[1];
        }

        if (preg_match("/\b(\d{1,3})\s*{$this->opt($this->t('child'))}/i", $participants, $m)) {
            $guestCount[] = $m[1];
        }

        if (count($guestCount) > 0) {
            $ev->booked()->guests(array_sum($guestCount));
        }

        $totalPrice = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Total price:'))}] ]/*[normalize-space()][2]", null, true, '/^.*\d.*$/');

        if (preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $totalPrice, $matches)) {
            // $102.60
            $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
            $ev->price()->currency($matches['currency'])->total(PriceHelper::parse($matches['amount'], $currencyCode));
        }

        $travellers = array_filter($this->http->FindNodes("//*[{$this->eq($this->t('Participants'))}]/following::text()[normalize-space() and not(preceding::h3[{$this->eq($this->t('Options'))} or {$this->eq($this->t('Price'))} or {$this->eq($this->t('Payments'))} or {$this->eq($this->t('Message'))} or {$this->eq($this->t('Cancellation policy'))}])]", null, "/.+\d\s*[:]+\s*([[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])$/u"));

        if (count($travellers) > 0) {
            $ev->general()->travellers($travellers, true);
        }

        $cancellation = $this->http->FindSingleNode("//*[{$this->eq($this->t('Cancellation policy'))}]/following-sibling::*[normalize-space()][1][not(descendant-or-self::h3)]");
        $ev->general()->cancellation($cancellation);
    }

    private function findDateRow(): \DOMNodeList
    {
        // it-375180989-v2.eml
        return $this->http->XPath->query("//*[{$this->eq($this->t('bookingDetails'))}]/following-sibling::*[normalize-space()][1]/descendant::tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Date:'))}] and following-sibling::tr[normalize-space()][1]/*[normalize-space()][1][{$this->eq($this->t('Time:'))}] ]");
    }

    private function getAddress(string $headerText): ?string
    {
        $address = null;

        if (preg_match("/^[ ]*(.{3,}?)[ ]*\([ ]*{$this->opt($this->t('view map'))}[ ]*\)[ ]*$/m", $headerText, $m)) {
            // it-253820067.eml
            $address = $m[1];
        }

        if (!$address) {
            // it-364407580.eml
            $address = $this->http->FindSingleNode("//p[{$this->eq($this->t('meetingPoint'))}]/following::p[normalize-space()][1]", null, true, $pattern = "/^(?:{$this->opt($this->t('The meeting point is'))}\s+)?(.{3,})$/")
                ?? $this->http->FindSingleNode("//div[{$this->eq($this->t('meetingPoint'))}]/following::div[normalize-space()][1]", null, true, $pattern)
            ;
        }

        if (!$address) {
            $address = $this->http->FindSingleNode($this->xpath['message'] . "/descendant::text()[normalize-space()='MEETING POINT:']/following::text()[normalize-space()][1]/ancestor::*[{$this->xpath['bold']}]", null, true, "/^(.{3,}?)(?:\s*,\s*{$this->opt($this->t('outside the'))}|$)/");
        }

        if (!$address) {
            $address = $this->http->FindSingleNode($this->xpath['message'] . "/descendant::text()[contains(normalize-space(),'venue located on')]/following::text()[normalize-space()][1]/ancestor::*[{$this->xpath['bold']}]");
        }

        if (!$address && preg_match("/^\s*(.{3,}?)[ ]*\n+[ ]*(?:(?:{$this->opt($this->t('Phone:'))}[ ]*)?{$this->patterns['phone']}|\S+@\S+|https?:\/\/.+)[ ]*(?:\n|$)/i", $headerText, $m)) {
            $address = $m[1];
        }

        return $address;
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['bookingDetails']) || empty($phrases['confNumber'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[{$this->contains($phrases['bookingDetails'])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->contains($phrases['confNumber'])}]")->length > 0
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

    private function htmlToText(?string $s, bool $brConvert = true): string
    {
        if (!is_string($s) || $s === '') {
            return '';
        }
        $s = str_replace("\r", '', $s);
        $s = preg_replace('/<!--.*?-->/s', '', $s); // comments

        if ($brConvert) {
            $s = preg_replace('/\s+/', ' ', $s);
            $s = preg_replace('/<[Bb][Rr]\b.*?\/?>/', "\n", $s); // only <br> tags
        }
        $s = preg_replace('/<[A-z][A-z\d:]*\b.*?\/?>/', '', $s); // opening tags
        $s = preg_replace('/<\/[A-z][A-z\d:]*\b[ ]*>/', '', $s); // closing tags
        $s = html_entity_decode($s);
        $s = str_replace(chr(194) . chr(160), ' ', $s); // NBSP to SPACE

        return trim($s);
    }

    /**
     * Dependencies `use AwardWallet\Engine\MonthTranslate` and `$this->lang`.
     *
     * @param string|null $text Unformatted string with date
     */
    private function normalizeDate(?string $text): ?string
    {
        if (preg_match('/^[-[:alpha:]]{2,}[,.\s]+(\d{1,2})[,.\s]+([[:alpha:]]{3,})[,.\s]+(\d{4})[.\s]*$/u', $text, $m)) {
            // giovedì, 11 maggio 2023
            $day = $m[1];
            $month = $m[2];
            $year = $m[3];
        } elseif (preg_match('/^[-[:alpha:]]{2,}[,.\s]+([[:alpha:]]{3,})[,.\s]+(\d{1,2})[,.\s]+(\d{4})[.\s]*$/u', $text, $m)) {
            // Wednesday, January 11, 2023
            $month = $m[1];
            $day = $m[2];
            $year = $m[3];
        }

        if (isset($day, $month, $year)) {
            if (preg_match('/^\s*(\d{1,2})\s*$/', $month, $m)) {
                return $m[1] . '/' . $day . ($year ? '/' . $year : '');
            }

            if (($monthNew = MonthTranslate::translate($month, $this->lang)) !== false) {
                $month = $monthNew;
            }

            return $day . ' ' . $month . ($year ? ' ' . $year : '');
        }

        return null;
    }
}
