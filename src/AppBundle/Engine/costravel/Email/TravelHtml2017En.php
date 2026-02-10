<?php

namespace AwardWallet\Engine\costravel\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class TravelHtml2017En extends \TAccountChecker
{
    public $mailFiles = "costravel/it-5674039.eml";

    public $lang = 'en';

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $xpath = "//tr[normalize-space()='ADDITIONAL INFORMATION']/following-sibling::tr[normalize-space()][1]";

        foreach ($this->http->XPath->query($xpath) as $root) {
            $this->parseCar($root, $email, $parser->getCleanFrom());
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], '@costcotravel.com') !== false
            && isset($headers['subject']) && (
                stripos($headers['subject'], 'Costco Travel: Confirmation # ') !== false
            );
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return strpos($parser->getHTMLBody(), 'RENTAL INFORMATION') !== false
            && strpos($parser->getHTMLBody(), 'Costco Travel') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@costcotravel.com') !== false;
    }

    public static function getEmailLanguages()
    {
        return ['en'];
    }

    protected function parseCar($root, Email $email, $from): void
    {
        $r = $email->add()->rental();

        $r->general()
            ->confirmation($this->http->FindSingleNode("//td[normalize-space(.)='Costco Travel Confirmation Number:']/../following-sibling::tr[1]/td[2]", null, false, '/\d+/'))
            ->traveller($this->http->FindSingleNode("//td[normalize-space(.)='Renter Name:']/following-sibling::td[1]"));

        $membershipNumber = $this->http->FindSingleNode("//text()[normalize-space()='Costco Membership #:']/following::text()[normalize-space()][1]", null, true, "/^[-A-Z\d]{5,}$/");

        if (!empty($membershipNumber)) {
            $r->ota()->account($membershipNumber, false);

            if ($from === 'customercare@costcotravel.com') {
                $st = $email->add()->statement();
                $st->addProperty('Login', $membershipNumber)
                    ->setNoBalance(true);
            }
        }

        $otaConfirmation = $this->http->FindSingleNode("//tr[ count(*)=2 and *[1][starts-with(normalize-space(),'Costco Travel Confirmation Number')] ]/*[2]", null, true, '/^[-A-Z\d]{5,}$/');

        if ($otaConfirmation) {
            $otaConfirmationTitle = $this->http->FindSingleNode("//tr[count(*)=2]/*[1][starts-with(normalize-space(),'Costco Travel Confirmation Number')]", null, true, '/^(.+?)[\s:：]*$/u');
            $r->ota()->confirmation($otaConfirmation, $otaConfirmationTitle);
        }

        $type = $this->http->FindSingleNode("//td[normalize-space(.)='Car Type:']/following-sibling::td[1]");

        if (preg_match('/^(.+?)\s+-\s+(.+?)$/', $type, $matches)) {
            $r->extra()
                ->company($matches[1]);
            $r->car()
                ->type($matches[2]);
        }

        // $66.87
        $total = $this->http->FindSingleNode("//td[normalize-space(.)='Total Rental Price']/following-sibling::td[1]");

        if (preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d]{1,5}?)\s*$#", $total, $m)
            || preg_match("#^\s*(?<currency>[^\d]{1,5}?)\s*(?<amount>\d[\d\., ]*)\s*$#", $total, $m)
        ) {
            $currency = $this->currency($m['currency']);
            $r->price()
                ->total(PriceHelper::parse($m['amount']), $currency)
                ->currency($currency)
            ;
        }

        $tax = $this->http->FindSingleNode("//td[normalize-space(.)='Taxes and fees']/following-sibling::td[1]");

        if (preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d]{1,5}?)\s*$#", $tax, $m)
            || preg_match("#^\s*(?<currency>[^\d]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $tax, $m)
        ) {
            $currency = $this->currency($m['currency']);
            $r->price()
                ->tax(PriceHelper::parse($m['amount']), $currency)
            ;
        }

        $r->car()
            ->model($this->http->FindSingleNode(".//td[normalize-space()='Pick-up:']/ancestor::table[1]/preceding-sibling::ul/li[normalize-space()][1]",
                $root, true, '/(.+) or similar/i'));

        $r->pickup()
            ->date(strtotime($this->normalizeDate(join($this->http->FindNodes(".//td[normalize-space(.)='Pick-up:']/following-sibling::td", $root)))));

        $r->dropoff()
            ->date(strtotime($this->normalizeDate(join($this->http->FindNodes(".//td[normalize-space(.)='Drop-off:']/following-sibling::td", $root)))));

        $pickup = implode(" ", $this->http->FindNodes(".//td[normalize-space(.)='Pick-Up Location']/../following-sibling::tr[1]/td[1]//text()", $root));
        // Albuquerque, NM 87106, US Ph: 505-724-4500 06:00 AM - Midnight
        // Orlando Intl Arpt Jeff Fuqua Boulevard Orlando, FL 32827, US  Ph: 844-370-3164 24 Hours
        if (preg_match('/^\s*(.+?)\s+Ph\s*:\s*([\d\-\+ ]+)\s+((?:\d+:|\d+\s+Hours|Midnight|Noon).*)$/s', $pickup, $matches)
            || preg_match('/^\s*(.+?)\s+(?:Ph|US\s*Ph):\s*([\d\-\+ ]+)\s*((?:\d+:|\d+\s+Hours|Midnight).*)?$/s', $pickup, $matches)) {
            $this->logger->error($matches[1]);
            $r->pickup()
                ->location($matches[1])
                ->phone($matches[2]);

            if (isset($matches[3]) && !empty($matches[3])) {
                $r->pickup()
                    ->openingHours($matches[3]);
            }
        }

        $dropoff = implode(" ", $this->http->FindNodes("./descendant::td[contains(normalize-space(), 'Drop-Off Location')]/ancestor::tr[1]/following-sibling::tr[1]/descendant::td[2]", $root));

        if (preg_match('/^\s*(.+?)\s*Ph:\s*([\d\-\+ ]+)\s+((?:\d+:|\d+\s+Hours|Midnight|Noon).*)$/s', $dropoff, $matches)
            || preg_match('/^\s*(.+?)\s+(?:Ph|US\s*Ph):\s*([\d\-\+ ]+)\s*((?:\d+:|\d+\s+Hours|Midnight|Noon).*)?$/s', $dropoff, $matches)) {
            $r->dropoff()
                ->location($matches[1])
                ->phone($matches[2]);

            if (isset($matches[3]) && !empty($matches[3])) {
                $r->dropoff()
                    ->openingHours($matches[3]);
            }
        }
    }

    protected function normalizeDate($string)
    {
        return preg_replace(
        // Fri., Feb. 03, 2017 Albuquerque Intl Arpt Time: 06:00 AM
            ['/^.*?(\w{3})\.? (\d+), (\d{4}).*?Time:\s*(\d+:\d+\s*[AMP]*)/'], ['$2 $1 $3, $4'], $string);
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function amount($price)
    {
        $price = str_replace([',', ' '], '', $price);

        if (is_numeric($price)) {
            return (float) $price;
        }

        return null;
    }

    private function currency($s)
    {
        $s = trim($s);

        if ($code = $this->re("#^\s*([A-Z]{3})\s*$#", $s)) {
            return $code;
        }
        $sym = [
            'CA $' => 'CAD',
            'US $' => 'USD',
            '€'    => 'EUR',
            '£'    => 'GBP',
        ];

        foreach ($sym as $f => $r) {
            if ($s == $f) {
                return $r;
            }
        }

        return $s;
    }
}
