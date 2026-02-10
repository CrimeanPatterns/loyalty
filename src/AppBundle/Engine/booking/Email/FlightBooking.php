<?php

namespace AwardWallet\Engine\booking\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class FlightBooking extends \TAccountChecker
{
    public $subjects = [
        '/\w+\s*flight booking confirmed$/',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@booking.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (preg_match($subject, $headers['subject'])) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'Booking.com')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t(', you\'re flying to'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Customer reference'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]booking\.com$/', $from) > 0;
    }

    public function ParseFlight(Email $email)
    {
        $f = $email->add()->flight();

        $f->general()
            ->traveller($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Hi')]", null, true, "/{$this->opt($this->t('Hi'))}\s*(\w+)\,/"));

        $url = $this->http->FindSingleNode("//a[contains(normalize-space(), 'Go to booking details')]/@href", null, true, "/(https\:\/\/flights\.booking\.com\/.+)/");
        $this->logger->error($url);

        $nodes = $this->http->XPath->query("//text()[starts-with(normalize-space(), 'Customer reference')]/following::text()[contains(normalize-space(), ' to ')][not(contains(normalize-space(), 'booking'))]/ancestor::tr[3]");

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $conf = $this->http->FindSingleNode("./descendant::text()[starts-with(normalize-space(), 'Booking reference:')]", $root, true, "/{$this->opt($this->t('Booking reference:'))}\s*([\dA-Z]+)/");

            if (!empty($conf)) {
                $f->general()
                    ->confirmation($conf);
            }

            $s->airline()
                ->noName()
                ->noNumber();

            $flightInfo = $this->http->FindSingleNode("./descendant::text()[contains(normalize-space(), ' to ')]", $root);

            if (preg_match("/^(?<depName>.+)\s+\((?<depCode>[A-Z]{3})\)\s+to\s+(?<arrName>.+)\s+\((?<arrCode>[A-Z]{3})\)\s*$/", $flightInfo, $m)) {
                $s->departure()
                    ->name($m['depName'])
                    ->code($m['depCode']);

                $s->arrival()
                    ->name($m['arrName'])
                    ->code($m['arrCode']);
            }

            $url = "https://flights.booking.com/booking/order-details/524c2212700b5bbe061aad6d0cdca9e86422fa42da5e1966b322cdf90915d00246bc801adfe10a562addd162685523c87b08e359807abb30b7497a741b870a638a4e25c9569018fc688efcbfa039?locale=en-us&utm_source=flights_confirmation_email";

            if (!empty($url)) {
                $http2 = clone $this->http;
                $http2->setCookie("Cookie", "fasc=ae3f80d1-3a79-4e73-984f-a31985f265ec; fsc=s%3Acd1c59661386efcda914d3d633144ce7.wNBmXyJZuJk1UbKW26c09jjcng9XEuCPUmPtbzzRMww; bkng_sso_auth=CAIQsOnuTRpmCbtnsI7cobo4bXm3GrzrdoO/5zSCQyHDHi9AY/iKILfOGXF/y2tRQZ2n7UL3t9vk+4rBhQrZW2VYIKHWCp83GJMIi8hgeIEu6XrorqW6KIsfelP0unT39L3STZLtY80LSB2PHv5V; pcm_consent=analytical%3Dfalse%26countryCode%3DRU%26consentId%3Df5983c3c-c1bb-4848-860e-488e104a5c80%26consentedAt%3D2023-12-19T08%3A05%3A49.036Z%26expiresAt%3D2024-06-16T08%3A05%3A49.036Z%26implicit%3Dtrue%26marketing%3Dfalse%26regionCode%3DAD%26regulation%3Dgdpr%26legacyRegulation%3Dgdpr; fsc=s%3Acd1c59661386efcda914d3d633144ce7.wNBmXyJZuJk1UbKW26c09jjcng9XEuCPUmPtbzzRMww; OptanonAlertBoxClosed=2023-12-19T08:33:48.182Z; OptanonConsent=isGpcEnabled=0&datestamp=Tue+Dec+19+2023+11%3A33%3A48+GMT%2B0300+(%D0%9C%D0%BE%D1%81%D0%BA%D0%B2%D0%B0%2C+%D1%81%D1%82%D0%B0%D0%BD%D0%B4%D0%B0%D1%80%D1%82%D0%BD%D0%BE%D0%B5+%D0%B2%D1%80%D0%B5%D0%BC%D1%8F)&version=202308.1.0&browserGpcFlag=0&isIABGlobal=false&hosts=&consentId=d35b1f4d-7959-4225-ad6f-0360ba660d69&interactionCount=0&landingPath=NotLandingPage&groups=C0001%3A1%2CC0002%3A1%2CC0004%3A1&backfilled_at=1702974828191&backfilled_seed=1; OptanonAlertBoxClosed=2023-12-19T08:33:48.182Z; _gcl_au=1.1.691157875.1702974829; OptanonConsent=isGpcEnabled=0&datestamp=Tue+Dec+19+2023+11%3A55%3A02+GMT%2B0300+(%D0%9C%D0%BE%D1%81%D0%BA%D0%B2%D0%B0%2C+%D1%81%D1%82%D0%B0%D0%BD%D0%B4%D0%B0%D1%80%D1%82%D0%BD%D0%BE%D0%B5+%D0%B2%D1%80%D0%B5%D0%BC%D1%8F)&version=202308.1.0&browserGpcFlag=0&isIABGlobal=false&hosts=&consentId=d35b1f4d-7959-4225-ad6f-0360ba660d69&interactionCount=0&landingPath=NotLandingPage&groups=C0001%3A1%2CC0002%3A1%2CC0004%3A1&backfilled_at=1702974828191&backfilled_seed=1; _uetsid=54fac9809e4911ee93812524b3ac2dee; _uetvid=a46608b0b8e411edb66bb9608e12b8c0; bkng=11UmFuZG9tSVYkc2RlIyh9Yaa29%2F3xUOLbwcLxQQ4VaCqQBT%2F%2Bve%2B9ahyefmFEgJ2m2AJz63Us79tEMkS3aDt9%2BwsUOUofwjszAqvPHXTgL%2FJdGQ%2BtCTCZyzVAXY1OoQyjoL2v%2FLTjCuN%2FZnu4tjtHcNhfhXXBs3wCxQxN5ibIOojev2Bi82v7JePVPxGNgqVAWgI9Dnj8%2BaY%3D");
                $http2->setUserAgent("Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/119.0.0.0 Safari/537.36");
                $http2->getUrl($url);
                $text = implode("\n", $http2->FindNodes("//text()[normalize-space()]"));
                $this->logger->debug($text);
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $email->ota()
            ->confirmation($this->http->FindSingleNode("//text()[normalize-space()='Customer reference']/following::text()[normalize-space()][1]", null, true, "/(\d+\-\d{5,})/"));

        $this->ParseFlight($email);

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
}
