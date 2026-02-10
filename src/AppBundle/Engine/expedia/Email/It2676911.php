<?php

namespace AwardWallet\Engine\expedia\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;

// TODO: rewrite on objects

class It2676911 extends \TAccountChecker
{
    public $mailFiles = "expedia/it-2676911.eml, expedia/it-2676913.eml, expedia/it-164991616.eml, expedia/it-164064080.eml";

    private $subjects = [
        'en' => ['travel confirmation', 'Insurance Coverage for your'],
    ];

    public function detectEmailFromProvider($from)
    {
        return strpos($from, 'Expedia Travel Confirmation') !== false
            || stripos($from, '@ExpediaConfirm.com') !== false
            || preg_match('/[.@]expedia\.com/i', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true
            && strpos($headers['subject'], 'Expedia') === false
        ) {
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
        $detectProvider = false;

        $textPlain = $parser->getPlainBody();

        if (stripos($textPlain, 'Thank you for booking with Expedia') !== false
            || stripos($textPlain, 'Expedia Customer Support Team') !== false
            || stripos($textPlain, 'Call us at 1300 EXPEDIA') !== false
            || $this->http->XPath->query('//a[contains(@href,".expedia.com/") or contains(@href,".expedia.com.au/") or contains(@href,"www.expedia.com")]')->length > 0
            || $this->http->XPath->query('//*[contains(normalize-space(),"Expedia Customer Support Team")]')->length > 0
        ) {
            $detectProvider = true;
        }

        if ($detectProvider
            && stripos($textPlain, 'Reserved for') !== false
        ) {
            return true;
        }

        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (!$textPdf) {
                continue;
            }

            if (strpos($textPdf, 'Pick up') !== false && strpos($textPdf, 'Reserved for') !== false) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $textPlain = $parser->getPlainBody();

        if (preg_match('/<[Bb][Rr]\b.*?\/?>/', $textPlain) > 0) {
            $textPlain = text($textPlain);
        }

        if (strpos($textPlain, 'Reserved for') !== false) {
            $its = $this->parseIts($textPlain);

            return [
                'emailType'  => 'CarRentalPlain',
                'parsedData' => [
                    'Itineraries' => $its,
                ],
            ];
        }

        $itineraries = [];

        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_COMPLEX);

            if (!$textPdf) {
                continue;
            }

            if (strpos($textPdf, 'Reserved for') !== false) {
                $its = $this->parseIts(text($textPdf));
                $itineraries = array_merge($itineraries, $its);
            }
        }

        if (count($itineraries) === 0) {
            return null;
        }

        return [
            'emailType'  => 'CarRentalPdf', // it-164064080.eml
            'parsedData' => [
                'Itineraries' => $itineraries,
            ],
        ];
    }

    public static function getEmailTypesCount()
    {
        return 1;
    }

    public static function getEmailLanguages()
    {
        return ["en"];
    }

    private function parseCar(?string $text): array
    {
        $it = [];
        $it['Kind'] = 'L';

        $it['TripNumber'] = $this->re("/for your itinerary[ ]+([A-Z\d]{5,})[ ]*[,.;!]/", $text) ?? reni('Itinerary \# (\w+)', $text);
        $it['Number'] = reni('Confirmation \# (\w+)', $text);

        $it['PickupLocation'] = reni('Pick up
            .+? \d{4}
            (.+?)
            Open \s+
        ', $text);

        $date = uberDate($text, 1);
        $time = reni('Pick up (\d+ : \d+ (?: am | pm )?)', $text);
        $it['PickupDatetime'] = strtotime($time, strtotime($date));

        $it['DropoffLocation'] = reni('Drop off
            .+? \d{4}
            (.+?)
            Open \s+
        ', $text);

        $date = uberDate($text, 2);
        $time = reni('Drop off (\d+ : \d+ (?: am | pm )?)', $text);
        $it['DropoffDatetime'] = strtotime($time, strtotime($date));

        $q = white('Drop off
            .+?
            Open \s+ .+? \n
            (?P<CarType> .+?) \n
            (?P<CarModel> .+?) \n
        ');
        $it = array_merge($it, re2dict($q, $text));

        $it['RenterName'] = reni('Reserved for (\w.+?) \n', $text);

        $totalPrice = reni('\d+ Total Price ([^\s].+?) \n', $text);

        if (preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*)$/', $totalPrice, $matches)) {
            // AU$875.76
            $currency = $this->normalizeCurrency($matches['currency']);
            $currencyCode = preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null;
            $it['Currency'] = $currency;
            $it['TotalCharge'] = PriceHelper::parse($matches['amount'], $currencyCode);
        }

        $x = reni('Taxes & Fees ([^\s].+?) \n', $text);
        $it['TotalTaxAmount'] = cost($x);

        if (rew('Your reservation is booked and confirmed', $text)) {
            $it['Status'] = 'booked and confirmed';
        } elseif (rew('Your reservation is booked', $text)) {
            $it['Status'] = 'booked';
        }

        return $it;
    }

    private function parseIts(?string $text): array
    {
        $its = [];
        $itineraries = $this->splitText($text, "/^([> ]*Car (?:rental|hire) in .+)/m", true);

        foreach ($itineraries as $itText) {
            if (rew('Hotel | Flight', $itText)) {
                continue;
            }
            $its[] = $this->parseCar($itText);
        }

        return $its;
    }

    private function splitText(?string $textSource, string $pattern, bool $saveDelimiter = false): array
    {
        $result = [];

        if ($saveDelimiter) {
            if (!preg_match($pattern, $textSource)) {
                return [$textSource];
            }
            $textFragments = preg_split($pattern, $textSource, -1, PREG_SPLIT_DELIM_CAPTURE);
            array_shift($textFragments);

            for ($i = 0; $i < count($textFragments) - 1; $i += 2) {
                $result[] = $textFragments[$i] . $textFragments[$i + 1];
            }
        } else {
            $result = preg_split($pattern, $textSource);
            array_shift($result);
        }

        return $result;
    }

    /**
     * @param string $string Unformatted string with currency
     */
    private function normalizeCurrency(string $string): string
    {
        $string = trim($string);
        $currencies = [
            // do not add unused currency!
            'USD' => ['US$'],
            'AUD' => ['AU$'],
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

    private function re($re, $str, $c = 1): ?string
    {
        if (preg_match($re, $str, $m) && array_key_exists($c, $m)) {
            return $m[$c];
        }

        return null;
    }
}
