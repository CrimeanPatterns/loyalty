<?php

namespace AwardWallet\Engine\ebrite\Email;

use AwardWallet\Schema\Parser\Common\Event;
use AwardWallet\Schema\Parser\Email\Email;

class OrderConfirmation2018 extends \TAccountChecker
{
    public $mailFiles = "ebrite/it-69404535.eml";

    public static $dictionary = [
        'en' => [],
    ];

    private $subjects = [
        'en' => ['Your Tickets for'],
    ];

    private $detectors = [
        'en' => ['is your order confirmation for', 'Order Summary', 'About this event'],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@eventbrite.com') !== false;
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
        if ($this->http->XPath->query('//a[contains(@href,".eventbrite.com/") or contains(@href,"www.eventbrite.com")]')->length === 0
            && $this->http->XPath->query('//node()[contains(normalize-space(),"Eventbrite. All rights reserved") or contains(.,"@eventbrite.com")]')->length === 0
        ) {
            return false;
        }

        return $this->detectBody();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $email->setType('OrderConfirmation2018');
        $this->parseEvent($email);

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

    private function parseEvent(Email $email): void
    {
        $patterns['time'] = '\d{1,2}(?:[:：]\d{2})?(?:\s*[AaPp]\.?[Mm]\.?)?';

        $event = $email->add()->event();

        $eventName = $this->http->FindSingleNode("//h2[{$this->contains('your order confirmation for')}]", null, true, "/{$this->opt('your order confirmation for')}\s*(.{3,})/");

        $xpathOrder = "//text()[{$this->eq('Order Summary')}]";

        $orderNo = $this->http->FindSingleNode($xpathOrder . "/following::text()[{$this->starts('Order #')}]");

        if (preg_match("/^({$this->opt('Order #')})[: ]+([-A-Z\d]{5,})$/", $orderNo, $m)) {
            $event->general()->confirmation($m[2], $m[1]);
        }

        $travellers = [];

        $orderRecords = $this->http->XPath->query($xpathOrder . "/following::tr[ *[1][{$this->eq('Name')}] and *[2][{$this->eq('Type')}] ]/../following-sibling::*/descendant::tr[ *[1][normalize-space()] and *[2][normalize-space()] and *[4] ]");

        foreach ($orderRecords as $row) {
            $traveller = $this->http->FindSingleNode("*[1]", $row, true, '/^[[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]]$/u');

            if ($traveller) {
                $travellers[] = $traveller;
            }
        }

        if (count($travellers)) {
            $event->general()->travellers(array_unique($travellers));
        }

        $totalPrice = $this->http->FindSingleNode($xpathOrder . "/following::td[{$this->eq('TOTAL')}]/following-sibling::td[normalize-space()][last()]");

        if (preg_match('/^(?<currency>[^\d)(]+?)[ ]*(?<amount>\d[,.\'\d]*)$/', $totalPrice, $m)) {
            // $40.00
            $event->price()
                ->total($this->normalizeAmount($m['amount']))
                ->currency($m['currency']);
        }

        $cancellation = $this->http->FindSingleNode($xpathOrder . "/following::div[ descendant::text()[normalize-space()][1][{$this->starts('Refund Policy')}] ]", null, true, "/{$this->opt('Refund Policy')}[ ]*[:]+[ ]*(.+)/");
        $event->general()->cancellation($cancellation);

        $xpathAbout = "//text()[{$this->eq('About this event')}]";

        $date = $this->http->FindSingleNode($xpathAbout . "/following::tr[ count(*)=2 and *[1]/descendant::img[contains(@src,'/date-')] ]/*[2]");

        if (preg_match("/^(?<date>.{6,}?)\s+from\s+(?<time1>{$patterns['time']})\s+to\s+(?<time2>{$patterns['time']})/i", $date, $m)) {
            // Wednesday, 31 January 2018 from 6:00 pm to 8:00 pm (AEDT)
            $event->booked()
                ->start2($m['date'] . ' ' . $m['time1'])
                ->end2($m['date'] . ' ' . $m['time2']);
        }

        $address = $this->http->FindSingleNode($xpathAbout . "/following::tr[ count(*)=2 and *[1]/descendant::img[contains(@src,'/location-')] ]/*[2]");

        $event->place()
            ->name($eventName)
            ->address($address)
            ->type(Event::TYPE_EVENT);
    }

    private function detectBody(): bool
    {
        if (!isset($this->detectors)) {
            return false;
        }

        foreach ($this->detectors as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (!is_string($phrase)) {
                    continue;
                }

                if ($this->http->XPath->query("//node()[{$this->contains($phrase)}]")->length > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Formatting over 3 steps:
     * 11 507.00  ->  11507.00
     * 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
     * 18800,00   ->  18800.00  |  2777,0    ->  2777.0.
     *
     * @param string|null $s Unformatted string with amount
     * @param string|null $decimals Symbols floating-point when non-standard decimals (example: 1,258.943)
     */
    private function normalizeAmount(?string $s, ?string $decimals = null): ?float
    {
        if (!empty($decimals) && preg_match_all('/(?:\d+|' . preg_quote($decimals, '/') . ')/', $s, $m)) {
            $s = implode('', $m[0]);

            if ($decimals !== '.') {
                $s = str_replace($decimals, '.', $s);
            }
        } else {
            $s = preg_replace('/\s+/', '', $s);
            $s = preg_replace('/[,.\'](\d{3})/', '$1', $s);
            $s = preg_replace('/,(\d{1,2})$/', '.$1', $s);
        }

        return is_numeric($s) ? (float) $s : null;
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
