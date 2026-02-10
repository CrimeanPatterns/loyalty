<?php

namespace AwardWallet\Engine\dhotels\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class It3124268 extends \TAccountChecker
{
    public $mailFiles = "dhotels/it-3124268.eml, dhotels/it-27073220.eml"; // +3 bcdtravel(html)[en]

    private $from = '#[@\.]destinationhotels\.#i';

    private $provider = 'destinationhotels';

    private $lang = 'en';

    private $detects = [
        'en' => [
            'Thank you for selecting',
            'Thank you for your reservation at',
            'On behalf of everyone at',
            'We are pleased to welcome you back to',
            'On behalf of the staff at',
            'Reservation Confirmation for',
            'Thank you for choosing to stay at Destination',
        ],
    ];

    private $xpathFragmentBold = '(self::b or self::strong)';

    private static $dict = [
        'en' => [
            'Your confirmation number is:' => ['Your confirmation number is:', 'Confirmation Number:', 'Your confirmation number is:'],
            'Check-in Time:'               => ['Check-in Time:', 'Check-in Time :'],
            'Check-out Time:'              => ['Check-out Time:', 'Check-out Time :'],
            'Adults:'                      => ['Adults:', 'Adults :'],
            'Children:'                    => ['Children:', 'Children :'],
            'Cancellation Policy:'         => ['Cancellation Policy:', 'Cancellation Fee:', 'Cancellation Fees & Policies:'],
            'Room Type Requested'          => ['Room Type Requested', 'Room Type', 'Room Description'],
            'Daily Average Rate:'          => ['Daily Average Rate:', 'Daily Average Rate :', 'Average Daily Rate', 'Average Nightly Rate', 'Nightly Rate:'],
            'Total Stay Amount'            => ['Total Stay Amount', 'Reservation Total Amount'],
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();
        $email->setType('HotelReservation' . ucfirst($this->lang));
        $this->parseEmail($email);

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match($this->from, $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        return stripos($headers['subject'], 'Confirmation for') !== false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if (false === stripos($parser->getHTMLBody(), $this->provider)) {
            return false;
        }

        return $this->assignLang();
    }

    private function parseEmail(Email $email): void
    {
        $h = $email->add()->hotel();

        $conf = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Your confirmation number is:'))}][1]", null, true, "/{$this->opt($this->t('Your confirmation number is:'))}\s*(\w+)/");

        if (empty($conf)) {
            $conf = $this->getNode($this->t('Your confirmation number is:'));
        }

        if (!empty($conf)) {
            $h->general()
                ->confirmation($conf);
        }

        if ($hName = $this->orval(
            $this->http->FindSingleNode("//text()[{$this->starts('Thank you for selecting')}][1]", null, true, '/Thank you for selecting (.+?) for your future stay/'),
            $this->http->FindSingleNode("//text()[{$this->starts('We are pleased to welcome you back to')}][1]", null, true, '/We are pleased to welcome you back to (.+?) as you attend/'),
            $this->http->FindSingleNode("//text()[{$this->starts('The Paradise Point Reservations Team')}][1]", null, true, '/The (Paradise Point Reservations) Team/'),
            $this->http->FindSingleNode("//text()[{$this->starts('Skamania Lodge is close to nature')}][1]", null, true, '/(Skamania Lodge) is close to nature/'),
            $this->http->FindSingleNode("//text()[{$this->starts('Thank you for your reservation at')}][1]", null, true, '/Thank you for your reservation at[ ]*([-\w\' ]+?)[ ]*,/'),
            $this->http->FindSingleNode("//text()[{$this->starts('On behalf of everyone at')}][1]", null, true, '/On behalf of everyone at[ ]*(.+),[ ]*we look forward to welcoming you/'),
            $this->http->FindSingleNode("//text()[{$this->starts('On behalf of the staff at')}][1]", null, true, '/On behalf of the staff at (.+?), thank/'),
            $this->http->FindSingleNode("//text()[{$this->starts('We are delighted you chose')}][1]", null, true, '/We are delighted you chose (.+?) for your/'),
            $this->http->FindSingleNode("//text()[{$this->starts('We are so pleased to welcome you to')}][1]", null, true, '/We are so pleased to welcome you to (.+?)\./'),
            $this->http->FindSingleNode("//text()[{$this->starts('Mahalo for choosing to stay at')}][1]", null, true, '/Mahalo for choosing to stay at (.+?)\s*by/')
        )) {
            $h->hotel()
                ->name($hName);
        }

        $cdate = $this->getNode('Arrival Date', '/(.+?\s*\d{4})/');
        $time = $this->orval(
            $this->getNode('Our check in time is', '/(\d+:\d+ (?:PM|AM)?)/'),
            $this->getNode('Check-in Time:', '/(\d+:\d+[ap].?m.?)/'),
            $this->http->FindSingleNode('//text()[' . $this->contains($this->t('Check-in Time:')) . ']/following::text()[normalize-space(.)][1][not(./ancestor::*[' . $this->xpathFragmentBold . '])]', null, true, '/\b(\d{1,2}:\d{2}(?:\s*[AaPp].?[Mm].?)?)/')
        );

        $dt = strtotime($cdate);

        if ($time) {
            $dt = strtotime(str_replace(".", "", $time), $dt);
        }

        if (!empty($dt)) {
            $h->booked()
                ->checkIn($dt);
        }

        $ddate = $this->getNode('Departure Date', '/(.+?\s*\d{4})/');
        $dtime = $this->orval(
            $this->getNode('check out time is', '/(\d+:\d+ (?:PM|AM)?)/'),
            $this->getNode('Check-out Time:', '/(\d+:\d+[ap]m)/'),
            $this->http->FindSingleNode('//text()[' . $this->contains($this->t('Check-out Time:')) . ']/following::text()[normalize-space(.)][1][not(./ancestor::*[' . $this->xpathFragmentBold . '])]', null, true, '/\b(\d{1,2}:\d{2}(?:\s*[AaPp][Mm])?)/')
        );

        $dt = strtotime($ddate);

        if ($dtime) {
            $dt = strtotime($dtime, $dt);
        }

        if (!empty($dt)) {
            $h->booked()
                ->checkOut($dt);
        }

        $addr = $this->orval(
            $this->http->FindSingleNode('//*[' . $this->contains(['Business:', 'PHONE:'], 'text()') . ']/preceding::span[1]'),
            $this->http->FindSingleNode('//a[contains(normalize-space(.),"1404 Vacation Road")]'),
            $this->http->FindSingleNode('//a[contains(normalize-space(),"Visit our Website")]/following::*[string-length(normalize-space())>2][1]/following::*[string-length(normalize-space())>2][1]')
        );

        if (!empty($addr)) {
            $h->hotel()
                ->address($addr);
        }

        $phone = $this->orval(
            $this->getNode(['PHONE', 'Business'], '/([\d.-]+)/'), // Business: 509.427.7700
            $this->http->FindSingleNode('//a[contains(normalize-space(.),"1404 Vacation Road")]/preceding::a[normalize-space(.)][1]', null, true, '/^([+]?[-.\d\s)(]{7,})$/'), // 858-240-4913
            $this->http->FindSingleNode('//a[contains(normalize-space(),"Visit our Website")]/following::*[string-length(normalize-space())>2][1]', null, true, '/^[\|\s]*([\d\-]+)$/') // 1-802-253-3560
        );

        if (!empty($phone)) {
            $h->hotel()
                ->phone(preg_replace('/(\d)[ ]*\.[ ]*(\d)/', '$1-$2', $phone));
        }

        $fax = $this->getNode('Fax', '/([\d.-]+)/'); // Fax: 509.427.2547

        if (!empty($fax)) {
            $h->hotel()
                ->fax($fax);
        }

        $name = $this->http->FindSingleNode("//text()[contains(normalize-space(),'Guest Name')]/following::text()[normalize-space()][1]");

        if (empty($name)) {
            $name = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.), 'Dear')]", null, true, '/Dear (.+?) ,/');
        }

        if (!empty($name)) {
            $h->addTraveller($name);
        }

        $h->booked()
            ->guests($this->http->FindSingleNode('//text()[' . $this->contains($this->t('Adults:')) . ']/following::text()[normalize-space(.)][1][not(./ancestor::*[' . $this->xpathFragmentBold . '])]', null, true, '/^(\d{1,3})\b/'), true, true);

        $h->booked()
            ->kids($this->http->FindSingleNode('//text()[' . $this->contains($this->t('Children:')) . ']/following::text()[normalize-space(.)][1][not(./ancestor::*[' . $this->xpathFragmentBold . '])]', null, true, '/^(\d{1,3})\b/'), true, true);

        $cancellationHtml = $this->http->FindHTMLByXpath("//text()[{$this->contains($this->t('Cancellation Policy:'))}]/ancestor::*[count(descendant::text()[normalize-space()])>1][1]");
        $cancellation = preg_match("/{$this->opt($this->t('Cancellation Policy:'))}:*\s*(.+)/", $this->htmlToText($cancellationHtml), $m)
            ? $m[1] : null;

        $cancel = $this->orval(
            $cancellation,
            $this->getNode(['Cancellation Policy', 'Cancellation'], '/(.+?) (?:Early Departure Fee:|Check-in Time:)/'),
            $this->http->FindSingleNode("//text()[{$this->contains('Reservations must be cancelled')}][1]"),
            $this->http->FindSingleNode("(//span[{$this->contains('Cancellation:')}]/following-sibling::text())[1]")
        );

        if (!empty($cancel)) {
            $h->general()
                ->cancellation($cancel);
        }

//        deadline
        if (!empty($h->getCancellation())) {
            $this->detectDeadLine($h);
        }

        $r = $h->addRoom();

        $r->setType($this->getNode($this->t('Room Type Requested')), true, true);

        $payment = $this->http->FindSingleNode('//text()[' . $this->contains($this->t('Daily Average Rate:')) . ']/following::text()[normalize-space(.)][1][not(./ancestor::*[' . $this->xpathFragmentBold . '])]');

        if (!empty($payment)) {
            $r->setRate($payment . ' / day');
        }

        $r->setRateType($this->getNode('Rate Description'), true, true);

        $total = $this->getNode($this->t('Total Stay Amount'));

        if (preg_match('/^(?<currency>[^\d)(]+) ?(?<amount>\d[,.\'\d]*)$/', $total, $m)) {
            // $935.46
            $h->price()
                ->total($this->normalizeAmount($m['amount']))
                ->currency($this->normalizeCurrency($m['currency']))
            ;
        }

//        $cost = $this->http->FindSingleNode('//text()[' . $this->contains(['Guest Room Subtotal:', 'Guest Room Subtotal :']) . ']/following::text()[normalize-space(.)][1][not(./ancestor::*[' . $this->xpathFragmentBold . '])]');
    }

    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h)
    {
        if (preg_match('/Reservations must be cancelled (\d{1,2} hours) prior to arrival date to avoid a penalty/', $h->getCancellation(), $m)) {
            $h->booked()->deadlineRelative($m[1]);
        } elseif (preg_match('/^\s*(\d+ days) prior to arrival\./', $h->getCancellation(), $m)) {
            $h->booked()->deadlineRelative($m[1]);
        } elseif (preg_match('/^(\d+ days) or more notification\:/', $h->getCancellation(), $m)) {
            $h->booked()->deadlineRelative($m[1]);
        }
    }

    private function orval(...$values)
    {
        foreach ($values as $value) {
            if (!empty($value)) {
                return $value;
            }
        }

        return null;
    }

    private function assignLang(): bool
    {
        foreach ($this->detects as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if ($this->http->XPath->query('//node()[contains(normalize-space(.),"' . $phrase . '")]')->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function getNode($s, ?string $re = null): ?string
    {
        return $this->http->FindSingleNode("(//text()[{$this->starts($s)}]/following::text()[normalize-space(.)!=''][1])[1]", null, true, $re);
    }

    private function starts($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'starts-with(normalize-space(' . $node . '),"' . $s . '")'; }, $field)) . ')';
    }

    private function t($phrase)
    {
        if (!isset(self::$dict[$this->lang]) || !isset(self::$dict[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dict[$this->lang][$phrase];
    }

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'contains(normalize-space(' . $node . '),"' . $s . '")'; }, $field)) . ')';
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

    /**
     * Formatting over 3 steps:
     * 11 507.00  ->  11507.00
     * 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
     * 18800,00   ->  18800.00.
     *
     * @param string $string Unformatted string with amount
     */
    private function normalizeAmount(string $s): string
    {
        $s = preg_replace('/\s+/', '', $s);
        $s = preg_replace('/[,.\'](\d{3})/', '$1', $s);
        $s = preg_replace('/,(\d{2})$/', '.$1', $s);

        return $s;
    }

    /**
     * @param string $string Unformatted string with currency
     */
    private function normalizeCurrency(string $string): string
    {
        $string = trim($string);
        $currences = [
            'GBP' => ['£'],
            'EUR' => ['€'],
            'USD' => ['$', 'US$'],
        ];

        foreach ($currences as $currencyCode => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $currencyCode;
                }
            }
        }

        return $string;
    }

    private function htmlToText($s, $brConvert = true): string
    {
        if (!is_string($s) || $s === '') {
            return '';
        }
        $s = preg_replace('/<!--.*?-->/s', '', $s); // comments

        if ($brConvert) {
            $s = str_replace("\n", '', $s);
            $s = preg_replace('/<[Bb][Rr]\b.*?\/?>/', "\n", $s); // only <br> tags
        }
        $s = preg_replace('/<[A-z]+\b.*?\/?>/', '', $s); // opening tags
        $s = preg_replace('/<\/[A-z]+\b[ ]*>/', '', $s); // closing tags
        $s = html_entity_decode($s);
        $s = str_replace(chr(194) . chr(160), ' ', $s); // NBSP to SPACE

        return trim($s);
    }
}
