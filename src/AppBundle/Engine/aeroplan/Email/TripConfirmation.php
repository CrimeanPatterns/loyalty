<?php

namespace AwardWallet\Engine\aeroplan\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Common\Hotel;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class TripConfirmation extends \TAccountChecker
{
    public $mailFiles = "aeroplan/it-240737924.eml, aeroplan/it-242814253.eml, aeroplan/it-245811503.eml";
    public $subjects = [
        'Aeroplan Member trip confirmation',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@aeroplan.cxloyalty.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (stripos($headers['subject'], $subject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'Aeroplan Member')]")->length > 0) {
            return $this->http->XPath->query("//text()[{$this->contains($this->t('Please carefully review your itinerary below to verify that all of the information is correct.'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Payment Summary'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Important Information'))}]")->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]aeroplan.cxloyalty.com$/', $from) > 0;
    }

    public function Hotel(Email $email, \DOMNode $root)
    {
        $h = $email->add()->hotel();

        $h->general()
            ->confirmation($this->http->FindSingleNode(".", $root, true, "/{$this->opt($this->t('Confirmation Number:'))}\s*([A-Z\d\-]{5,})\s*$/"), 'Confirmation Number')
            ->status($this->http->FindSingleNode("./preceding::text()[normalize-space()][1]", $root))
            ->traveller($this->http->FindSingleNode("./following::text()[starts-with(normalize-space(), 'Booked for:')][1]/following::text()[normalize-space()][1]", $root), true)
            ->date(strtotime($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Booked on ')]", null, true, "/{$this->opt($this->t('Booked on '))}\s*(.+)/")));

        $cancellation = $this->http->FindSingleNode("./following::text()[{$this->starts($this->t('Cancellations made after'))}][1]/ancestor::*[1]", $root);

        if (empty($cancellation)) {
            $cancellation = $this->http->FindSingleNode("./following::text()[{$this->starts($this->t('Bookings with free cancellation'))}][1]/ancestor::*[1]", $root);
        }

        if (!empty($cancellation)) {
            $h->general()
                ->cancellation($cancellation);
        }

        $h->hotel()
            ->name($this->http->FindSingleNode("./preceding::text()[normalize-space()][not(contains(normalize-space(), '|'))][2]", $root))
            ->address($this->http->FindSingleNode("./following::text()[starts-with(normalize-space(), 'Guests:')][1]/following::text()[starts-with(normalize-space(), 'Address:')][1]/following::text()[normalize-space()][1]", $root));

        $h->booked()
            ->checkIn(strtotime($this->http->FindSingleNode("./following::text()[starts-with(normalize-space(), 'Check-in:')][1]/following::text()[normalize-space()][1]", $root)))
            ->checkOut(strtotime($this->http->FindSingleNode("./following::text()[starts-with(normalize-space(), 'Check-out:')][1]/following::text()[normalize-space()][1]", $root)))
            ->guests($this->http->FindSingleNode("./following::text()[starts-with(normalize-space(), 'Guests:')][1]/following::text()[normalize-space()][1]", $root, true, "/^(\d+)$/"));

        $roomType = $this->http->FindSingleNode("./following::text()[starts-with(normalize-space(), 'Room Type:')][1]/following::text()[normalize-space()][1]", $root);

        if (!empty($roomType)) {
            $h->addRoom()->setType($roomType);
        }

        $priceText = $this->http->FindSingleNode("./following::text()[normalize-space()='Refundable (fees may apply)'][1]/preceding::text()[normalize-space()][1]/ancestor::td[1]", $root);

        if (preg_match("/^(?<currency>\D+)(?<total>[\d\.\,]+)$/u", $priceText, $m)) {
            $currency = $this->normalizeCurrency($m['currency']);
            $h->price()
                ->total(PriceHelper::parse($m['total'], $currency))
                ->currency($currency);

            $earned = $this->http->FindSingleNode("./following::text()[normalize-space()='Refundable (fees may apply)'][1]/following::text()[normalize-space()][1]/ancestor::td[1]", $root);

            if (preg_match("/earn\s*(?<earned>[\d\.\,]+\s*points)/iu", $earned, $m)) {
                $h->setEarnedAwards($m['earned']);
            }
        } elseif (preg_match("/^(?<points>[\d\.\,]+\s*points)/iu", $priceText, $m)) {
            $h->price()
                ->spentAwards($m['points']);
        }

        $this->detectDeadLine($h);
    }

    public function Car(Email $email, \DOMNode $root)
    {
        $r = $email->add()->rental();

        $r->general()
            ->confirmation($this->http->FindSingleNode(".", $root, true, "/{$this->opt($this->t('Confirmation Number:'))}\s*([A-Z\d\-]{5,})\s*$/"), 'Confirmation Number')
            ->traveller($this->http->FindSingleNode("./following::text()[starts-with(normalize-space(), 'Driver:')][1]/following::text()[normalize-space()][1]", $root), true)
            ->status($this->http->FindSingleNode("./preceding::text()[normalize-space()][1]", $root))
            ->date(strtotime($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Booked on ')]", null, true, "/{$this->opt($this->t('Booked on '))}\s*(.+)/")));

        $company = $this->http->FindSingleNode("./preceding::text()[normalize-space()][1]/preceding::text()[normalize-space()][1]", $root);

        if (!empty($company)) {
            $r->setCompany($company);
        }

        $r->car()
            ->type($this->http->FindSingleNode("./following::text()[starts-with(normalize-space(), 'Car Type:')][1]/following::text()[normalize-space()][1]", $root, true, "/^(.+)\s\-/"))
            ->model($this->http->FindSingleNode("./following::text()[starts-with(normalize-space(), 'Car Type:')][1]/following::text()[normalize-space()][1]", $root, true, "/\-\s*(.+)$/"));

        $pickUpText = implode("\n", $this->http->FindNodes("./following::text()[starts-with(normalize-space(), 'Pick-up:')][1]/following::text()[normalize-space()][1]/ancestor::td[1]/descendant::text()[normalize-space()]", $root));

        if (preg_match("/^.+\n(?<date>.+a?p?m)\n(?<location>.+\n*.*)\nPhone\:\s*(?<phone>[\d\(\)\+\s\-]+)/", $pickUpText, $m)) {
            $r->pickup()
                ->location(str_replace("\n", " ", $m['location']))
                ->date($this->normalizeDate($m['date']))
                ->phone($m['phone']);
        }

        $dropOffText = implode("\n", $this->http->FindNodes("./following::text()[starts-with(normalize-space(), 'Drop-off:')][1]/following::text()[normalize-space()][1]/ancestor::td[1]/descendant::text()[normalize-space()]", $root));

        if (stripos($dropOffText, 'Same As Pick Up') !== false) {
            if (preg_match("/^.+\n(?<date>.+a?p?m)\n*Same As Pick Up/", $dropOffText, $m)) {
                $r->dropoff()
                    ->same()
                    ->date($this->normalizeDate($m['date']));
            }
        } else {
            if (preg_match("/^.+\n(?<date>.+a?p?m)\n(?<location>.+\n*.*)\nPhone\:\s*(?<phone>[\d\(\)\+\s\-]+)/", $dropOffText, $m)) {
                $r->dropoff()
                    ->location(str_replace("\n", " ", $m['location']))
                    ->date($this->normalizeDate($m['date']))
                    ->phone($m['phone']);
            }
        }

        $priceText = $this->http->FindSingleNode("./following::text()[normalize-space()='Refundable (fees may apply)'][1]/preceding::text()[normalize-space()][1]/ancestor::td[1]", $root);

        if (preg_match("/^(?<currency>\D+)(?<total>[\d\.\,]+)$/u", $priceText, $m)) {
            $currency = $this->normalizeCurrency($m['currency']);
            $r->price()
                ->total(PriceHelper::parse($m['total'], $currency))
                ->currency($currency);

            $earned = $this->http->FindSingleNode("./following::text()[normalize-space()='Refundable (fees may apply)'][1]/following::text()[normalize-space()][1]/ancestor::td[1]", $root);

            if (preg_match("/earn\s*(?<earned>[\d\.\,]+\s*points)/iu", $earned, $m)) {
                $r->setEarnedAwards($m['earned']);
            }
        } elseif (preg_match("/^(?<points>[\d\.\,]+\s*points)/iu", $priceText, $m)) {
            $r->price()
                ->spentAwards($m['points']);
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $tripID = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Trip ID:')]", null, true, "/{$this->opt($this->t('Trip ID:'))}\s*(\d{5,})$/");

        if (!empty($tripID)) {
            $email->ota()->confirmation($tripID, 'Trip ID');
        }

        $priceText = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Total Cash Payment:')]/following::text()[normalize-space()][1]/ancestor::td[1]");

        if (preg_match("/^(?<currency>\D+)(?<total>[\d\.\,]+)$/", $priceText, $m)) {
            $currency = $this->normalizeCurrency($m['currency']);
            $email->price()
                ->total(PriceHelper::parse($m['total'], $currency))
                ->currency($currency);

            $spentAwards = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Total Points:')]/following::text()[normalize-space()][1]/ancestor::td[1]", null, true, "/^([\d\.\,]+)/");

            if (!empty($spentAwards)) {
                $email->price()
                    ->spentAwards($spentAwards);
            }
        }

        $nodes = $this->http->XPath->query("//text()[{$this->contains($this->t('Check-in:'))}]/preceding::text()[{$this->starts($this->t('Confirmation Number:'))}][1]");

        foreach ($nodes as $root) {
            $this->Hotel($email, $root);
        }
        $nodes = $this->http->XPath->query("//text()[{$this->contains($this->t('Car Type:'))}]/preceding::text()[{$this->starts($this->t('Confirmation Number:'))}][1]");

        foreach ($nodes as $root) {
            $this->Car($email, $root);
        }

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

    private function normalizeCurrency(string $string): string
    {
        $string = trim($string);
        $currencies = [
            'GBP' => ['£'],
            'EUR' => ['€'],
            'USD' => ['US$'],
            'SGD' => ['SG$'],
            'ZAR' => ['R'],
            'CAD' => ['CA $'],
        ];

        foreach ($currencies as $currencyCode => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $currencyCode;
                }
            }
        }

        return $string;
    }

    private function detectDeadLine(Hotel $h)
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (preg_match("/Cancellations made after\s*(?<dateTime>.{15,30})\s+\(property local time\)/su", $cancellationText, $m)) {
            $h->booked()
                ->deadline(strtotime($m['dateTime']));
        }
    }

    private function normalizeDate($str)
    {
        $in = [
            "#^\w+\,\s+(\w+)\s*(\d+)\,\s*(\d{4})[\s\-]+([\d\:]+\s*a?p?m)$#ui", //Thursday, July 28, 2022 - 1:00pm
        ];
        $out = [
            "$2 $1 $3, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }
}
