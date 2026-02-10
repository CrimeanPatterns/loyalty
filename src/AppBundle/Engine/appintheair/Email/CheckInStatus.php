<?php

namespace AwardWallet\Engine\appintheair\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

// parsers similar: engine/fluege/Email/CheckIn.php, engine/edreams/Email/BoardingPass.php
class CheckInStatus extends \TAccountChecker
{
    public $mailFiles = "appintheair/it-21233993.eml, appintheair/it-21363003.eml, appintheair/it-51845123.eml";

    public static $dictionary = [
        "en" => [
            //			'Booking reference:' => '',
            //			'Flight' => '',
            //			'Dear' => '',
            //			'Departure' => '',
        ],
    ];

    private $detectFrom = '@appintheair.mobi';

    private $reSubject = [
        "en" => "Check-in status",
    ];

    private $reBody2 = [
        "en" => "Flight",
    ];

    private $lang = "en";
    private $providerCode = '';

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        // Detecting Provider
        $this->assignProvider($parser->getHeaders());

        // Detecting Language
        foreach ($this->reBody2 as $lang => $re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));
        $email->setProviderCode($this->providerCode);

        $tripNumber = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Booking reference:")) . "]/following::text()[normalize-space(.)][1]", null, true, "#^\s*([A-Z\d]{5,})\s*$#");

        if (!empty($tripNumber)) {
            $email->ota()->confirmation($tripNumber);
        }

        $this->flight($parser, $email);

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers["from"], $this->detectFrom) === false) {
            return false;
        }

        foreach ($this->reSubject as $reSubject) {
            if (stripos($headers["subject"], $reSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        // Detecting Provider
        if ($this->assignProvider($parser->getHeaders()) === false) {
            return false;
        }

        $body = html_entity_decode($parser->getHTMLBody());

        // Detecting Language/Format
        foreach ($this->reBody2 as $reBody) {
            if (stripos($body, $reBody) !== false) {
                return true;
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

    public static function getEmailProviders()
    {
        return ['trip', 'appintheair'];
    }

    private function flight(\PlancakeEmailParser $parser, Email $email)
    {
        $patterns['time'] = '\d{1,2}(?:[:]\d{2})?(?:\s*[AaPp]\.?[Mm]\.?)?';

        $f = $email->add()->flight();
        $f->general()->noConfirmation();

        $passengers = array_unique(array_filter($this->http->FindNodes("//text()[" . $this->contains($this->t("Flight")) . "]/ancestor::tr[1]/following-sibling::tr[(count(./td) = 2) and contains(td[2]/@style,'color:')]/td[1]")));

        if (!empty($passengers)) {
            $f->general()->travellers($passengers, true);
        }

        if (empty($passengers)) {
            $passengers = array_unique(array_filter($this->http->FindNodes("//text()[" . $this->contains($this->t("Flight")) . "]/ancestor::tr[1]/following-sibling::tr[count(*)=1]/*[(count(./p) = 2) and contains(p[2]/@style,'color:')]/p[1]")));

            if (!empty($passengers)) {
                $f->general()->travellers($passengers, false);
            }
        }

        if (empty($passengers)) {
            $passengers = $this->http->FindNodes("//text()[" . $this->eq($this->t("Dear")) . "]/following::text()[normalize-space(.)][1]", null, "#(.+),$#");

            if (!empty($passengers)) {
                $f->general()->travellers($passengers, false);
            }
        }

        $xpath = "//img[contains(@src, '/planeHi.png')]/ancestor::tr[1]";
        $segments = $this->http->XPath->query($xpath);

        if ($segments->length === 0) {
            $this->logger->debug('segments root not found: ' . $xpath);
        }

        foreach ($segments as $root) {
            $s = $f->addSegment();

            $flight = $this->http->FindSingleNode("preceding-sibling::tr[normalize-space()][1]/descendant::text()[normalize-space()][last()]", $root);

            if (preg_match("/\s+(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<number>\d+)(?:$|\s*{$this->t('Salida')})/", $flight, $m)) {
                $s->airline()
                    ->name($m['name'])
                    ->number($m['number']);
            }

            /*
                Departure
                Los Angeles International Airport (LAX)
                20 Dec 10:12
            */
            $patterns['flight'] = "/^(?:Departure|Arrival)\s*(?<name>[\s\S]{3,}?)\s*\(\s*(?<code>[A-Z]{3})\s*\)\s*(?<date>[\s\S]{6,})$/";

            // 20 Dec 10:12    |    Dec 20 10:12
            $patterns['dateTime'] = "/^(?<date>.{3,}?)\s*(?<time>{$patterns['time']})$/";

            // Departure
            $departure = implode("\n", $this->http->FindNodes("td[2]/descendant::text()[normalize-space()]", $root));

            if (preg_match($patterns['flight'], $departure, $m)) {
                $s->departure()
                    ->code($m['code'])
                    ->name(preg_replace('/\s+/', ' ', $m['name']));

                if (preg_match($patterns['dateTime'], $m['date'], $matches)) {
                    $dateDep = EmailDateHelper::calculateDateRelative($this->normalizeDate($matches['date']), $this, $parser, '%D% %Y%');
                    $s->departure()->date(strtotime($matches['time'], $dateDep));
                }
            }

            // Arrival
            $arrival = implode("\n", $this->http->FindNodes("td[4]/descendant::text()[normalize-space()]", $root));

            if (preg_match($patterns['flight'], $arrival, $m)) {
                $s->arrival()
                    ->code($m['code'])
                    ->name(preg_replace('/\s+/', ' ', $m['name']));

                if (preg_match($patterns['dateTime'], $m['date'], $matches)) {
                    $dateArr = EmailDateHelper::calculateDateRelative($this->normalizeDate($matches['date']), $this, $parser, '%D% %Y%');
                    $s->arrival()->date(strtotime($matches['time'], $dateArr));
                }
            }
        }

        return $email;
    }

    private function assignProvider($headers): bool
    {
        if (stripos($headers['from'], '@mytrip.com') !== false
            || $this->http->XPath->query('//node()[contains(.,"@mytrip.com") or contains(.,"www.mytrip.com")]')->length > 0
            || $this->http->XPath->query('//a[contains(@href,"www.mytrip.com")]')->length > 0
        ) {
            $this->providerCode = 'trip';

            return true;
        }

        if (stripos($headers['from'], '@appintheair.mobi') !== false
            || $this->http->XPath->query('//node()[contains(.,"@appintheair.mobi") or contains(.,"www.appintheair.mobi") or contains(normalize-space(),"App in the Air")]')->length > 0
            || $this->http->XPath->query('//a[contains(@href,"www.appintheair.mobi")]')->length > 0
        ) {
            $this->providerCode = 'appintheair';

            return true;
        }

        return false;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    /**
     * Dependencies `use AwardWallet\Engine\MonthTranslate` and `$this->lang`.
     */
    private function normalizeDate(?string $text): ?string
    {
        if (preg_match('/^(?<day>\d{1,2})\s+(?<month>[[:alpha:]]{3,})$/u', $text, $m)
            || preg_match('/^(?<month>[[:alpha:]]{3,})\s+(?<day>\d{1,2})$/u', $text, $m)
        ) {
            // 20 Dec    |    Dec 20
            $day = $m['day'];
            $month = $m['month'];
            $year = '';
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

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) { return "normalize-space(.)=\"{$s}\""; }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) { return "starts-with(normalize-space(.), \"{$s}\")"; }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) { return "contains(normalize-space(.), \"{$s}\")"; }, $field));
    }
}
