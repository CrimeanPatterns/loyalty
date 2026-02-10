<?php

namespace AwardWallet\Engine\avis\Email;

use AwardWallet\Schema\Parser\Email\Email;

class It2072412 extends \TAccountChecker
{
    public $mailFiles = "avis/it-139077173.eml, avis/it-2072412.eml, avis/it-2072816.eml, avis/it-2131170.eml";

    public $reBody2 = [
        'en' => ['Pick up Information', 'This message is sent by Avis Rent A Car System'],
    ];

    private $providerCode = '';

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        // Detecting Provider
        $this->assignProvider($parser->getHeaders());

        $this->parseCar($email);
        $email->setType('EReminderEn');
        $email->setProviderCode($this->providerCode);

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@avis.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return preg_match("/E-Reminder for (?:Avis|Budget) Reservation\s*#\s*[A-Z\d]/i", $headers['subject']) > 0;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        // Detecting Provider
        if ($this->assignProvider($parser->getHeaders()) === false) {
            return false;
        }

        // Detecting Format
        foreach ($this->reBody2 as $re) {
            foreach ($re as $word) {
                if ($this->http->XPath->query("//node()[{$this->contains($word)}]")->length > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    public static function getEmailProviders()
    {
        return ['avis', 'perfectdrive'];
    }

    public static function getEmailTypesCount()
    {
        return 1;
    }

    public static function getEmailLanguages()
    {
        return ["en"];
    }

    public function IsEmailAggregator()
    {
        return false;
    }

    private function parseCar(Email $email): void
    {
        $car = $email->add()->rental();

        $confirmation = $this->http->FindSingleNode("//tr[not(.//tr) and {$this->starts('Your Confirmation Number:')}]");

        if (preg_match("/({$this->opt('Your Confirmation Number:')})\s*([-A-Z\d]{5,})$/", $confirmation, $m)) {
            $car->general()->confirmation($m[2], rtrim($m[1], ' :'));
        }

        $renterName = $this->http->FindSingleNode("//tr[not(.//tr) and {$this->starts(['Name:', 'Name :'])}]", null, true, "/{$this->opt(['Name:', 'Name :'])}\s*([[:alpha:]][-,.\'[:alpha:] ]*[[:alpha:]])$/u");
        $car->general()->traveller($renterName);

        /*
            February 17, 2020 at 7:00:00 PM
            10801 AIRPORT BOULEVARD
            AMARILLO INTERNATIONAL AIRPORT,AMARILLO,TX,US,79111
            806-335-2222
            SUN, SAT 8:00 AM - 12:00 AM, MON-FRI 7:00 AM - 12:00 AM
         */
        $pattern = '/^'
            . '(?<date>.{6,}?)[ ]+at[ ]+(?<time>\d{1,2}(?:[:]\d{2})*(?:\s*[AaPp]\.?[Mm]\.?)?)[ ]*\n?'
            . '[ ]*(?<location>[\s\S]{3,}?)[ ]*\n?'
            . '[ ]*(?<phone>[+(\d][-. \d)(]{5,}[\d)])[ ]*\n?'
            . '[ ]*(?<hours>.+)'
            . '$/';
        $pattern2 = '/^\w+\,\s*(?<date>\w+\s*\w+\,\s*\d{4})\s*(?<time>[\d\:]+\s*A?P?M)\s*(?<location>\D+)$/u';
        $pickUpInfoHtml = $this->http->FindHTMLByXpath("//tr[not(.//tr) and {$this->eq('Pick up Information')}]/following::tr[normalize-space()][1]");

        if (empty($pickUpInfoHtml)) {
            $pickUpInfoHtml = $this->http->FindHTMLByXpath("//text()[not(.//tr) and {$this->eq('PICK UP')}]/following::tr[normalize-space()][1]/following::tr[normalize-space()][string-length()>3][1]/ancestor::table[1]/descendant::text()[normalize-space()][not(contains(normalize-space(), 'PICK UP'))]");
        }
        $pickUpInfo = $this->htmlToText($pickUpInfoHtml);

        if (preg_match("/^\w+\s*\d+\,\s*\d{4}\s*at\s*[\d\:]+\s*A?P?M$/u", $pickUpInfo)) {
            $pickUpInfoHtml = $this->http->FindHTMLByXpath("//tr[not(.//tr) and {$this->eq('Pick up Information')}]/following::tr[normalize-space()][string-length()>3][1]/ancestor::table[1]/descendant::text()[normalize-space()][not(contains(normalize-space(), 'Pick up Information'))]");
            $pickUpInfo = $this->htmlToText($pickUpInfoHtml);
        }

        if (preg_match($pattern, $pickUpInfo, $m) || preg_match($pattern2, $pickUpInfo, $m)) {
            $car->pickup()
                ->date($this->normalizeDate($m['date'] . ' ' . $m['time']))
                ->location(preg_replace('/\s+/', ' ', $m['location']));

            if (!empty($m['phone'])) {
                $car->pickup()
                    ->phone($m['phone']);
            }

            if (!empty($m['hours'])) {
                $car->pickup()
                ->openingHours($m['hours']);
            }
        }

        $dropOffInfo = $this->http->FindHTMLByXpath("//tr[not(.//tr) and {$this->eq('Return Information')}]/following::tr[normalize-space()][1]");

        if (empty($dropOffInfo)) {
            $dropOffInfo = $this->http->FindHTMLByXpath("//text()[not(.//tr) and {$this->eq('DROP OFF')}]/following::tr[normalize-space()][1]/following::tr[normalize-space()][string-length()>3][1]/ancestor::table[1]/descendant::text()[normalize-space()][not(contains(normalize-space(), 'DROP OFF'))]");
        }
        $dropOffInfo = $this->htmlToText($dropOffInfo);

        if (preg_match("/^\w+\s*\d+\,\s*\d{4}\s*at\s*[\d\:]+\s*A?P?M$/u", $dropOffInfo)) {
            $dropOffInfo = $this->http->FindHTMLByXpath("//tr[not(.//tr) and {$this->eq('Return Information')}]/following::tr[normalize-space()][string-length()>3][1]/ancestor::table[1]/descendant::text()[normalize-space()][not(contains(normalize-space(), 'Return Information'))]");
            $dropOffInfo = $this->htmlToText($dropOffInfo);
        }

        if (preg_match($pattern, $dropOffInfo, $m) || preg_match($pattern2, $dropOffInfo, $m)) {
            $car->dropoff()
                ->date($this->normalizeDate($m['date'] . ' ' . $m['time']))
                ->location(preg_replace('/\s+/', ' ', $m['location']));

            if (!empty($m['phone'])) {
                $car->dropoff()
                    ->phone($m['phone']);
            }

            if (!empty($m['hours'])) {
                $car->dropoff()
                    ->openingHours($m['hours']);
            }
        }
    }

    private function assignProvider($headers): bool
    {
        if (stripos($headers['from'], '@avis.com') !== false || strpos($headers['subject'], 'for Avis Reservation') !== false
            || $this->http->XPath->query('//node()[contains(normalize-space(),"sent by Avis Rent A Car") or contains(normalize-space(),"E-Reminder for Avis Reservation") or contains(.,"@avis.com")]')->length > 0
            || $this->http->XPath->query('//a[contains(@href,".carrental.com/") and contains(@href,"vendor=Avis")]')->length > 0
        ) {
            $this->providerCode = 'avis';

            return true;
        }

        if (stripos($headers['from'], '@budget.com') !== false || strpos($headers['subject'], 'for Budget Reservation') !== false
            || $this->http->XPath->query('//node()[contains(normalize-space(),"sent by Budget Rent A Car") or contains(normalize-space(),"E-Reminder for Budget Reservation") or contains(.,"@budget.com")]')->length > 0
            || $this->http->XPath->query('//a[contains(@href,".carrental.com/") and contains(@href,"vendor=Budget")]')->length > 0
        ) {
            $this->providerCode = 'perfectdrive';

            return true;
        }

        return false;
    }

    private function normalizeDate($text)
    {
        if (!is_string($text) || empty($text)) {
            return '';
        }
        $in = [
            // January 22, 2018
            '/^([[:alpha:]]{3,})\s+(\d{1,2})\s*,\s*(\d{4})$/u',
            //February 05, 2022 12:00 AM
            "/^(\w+)\s*(\d+)\,\s*(\d{4})\s*(\d+)\:(\d+)\s*(a?p?m)$/iu",
        ];
        $out = [
            '$2 $1 $3',
            "$2 $1 $3, $4:$5",
        ];

        $date = preg_replace($in, $out, $text);

        return strtotime($date);
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

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ')="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'starts-with(normalize-space(' . $node . '),"' . $s . '")';
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
