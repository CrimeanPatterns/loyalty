<?php

namespace AwardWallet\Engine\autoslash\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class CancellationDetails extends \TAccountChecker
{
    public $mailFiles = "autoslash/it-273384883.eml, autoslash/it-283874578.eml, autoslash/it-97239144.eml";
    public $subjects = [
        'Cancellation details for your AutoSlash reservation at',
        'Car Rental in',
    ];

    public $detectBody = [
        'We have successfully canceled your',
        'email is to confirm the cancellation of your rental car',
        'Your prepaid car rental is confirmed.',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'cancelledText' => [
                'We have successfully canceled your',
                'email is to confirm the cancellation of your rental car',
                'Cancellation details for your AutoSlash reservation',
            ],
        ],
    ];

    private $rentalProviders = [
        'alamo' => [
            'Alamo',
        ],
        'hertz' => [
            'Hertz',
        ],
        'national' => [
            'National',
        ],
        'thrifty' => [
            'Thrifty',
        ],
        'rentacar' => [
            'Enterprise',
        ],
        'dollar' => [
            'Dollar',
        ],
        'avis' => [
            'Avis',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@autoslash.com') !== false) {
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
        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'AutoSlash')]")->length === 0) {
            return false;
        }

        foreach ($this->detectBody as $detectBody) {
            if ($this->http->XPath->query("//text()[{$this->contains($detectBody)}]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]autoslash\.com$/', $from) > 0;
    }

    public function ParseCar(Email $email)
    {
        $email->obtainTravelAgency();

        $r = $email->add()->rental();

        $conf = $this->http->FindSingleNode("//td[{$this->eq('AutoSlash Trip ID:')}]/following-sibling::td[normalize-space()][1]");

        if (!empty($conf)) {
            $email->ota()
                ->confirmation($conf);
        }

        if (!empty($this->http->FindSingleNode("(//*[{$this->contains($this->t('cancelledText'))}])[1]"))) {
            $r->general()
                ->status('cancelled')
                ->cancelled();
        }

        $traveller = $this->http->FindSingleNode("//td[{$this->eq('Driver name:')}]/following-sibling::td[normalize-space()][1]",
            null, true, "/^\s*(.+?)\s*(?:\(|$)/");

        if (empty($traveller)) {
            $traveller = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Hi')]", null, true, "/{$this->opt($this->t('Hi'))}\s*(\w+)/");
        }
        $r->general()
            ->traveller($traveller);

        $rentalInfo = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'under Trip ID')]");

        if (preg_match("/^We have successfully canceled your\s+(?<company>.+)\s+reservation at\s+(?<location>.+)\s+on\s*(?<date>[\d\/]+)\s+under Trip ID\s+(?<conf>\d+)\.$/", $rentalInfo, $m)) {
            $email->ota()
                ->confirmation($m['conf']);

            $r->setCompany($m['company']);

            $r->pickup()
                ->location($m['location'])
                ->date(strtotime($m['date']));
        }

        $rentalCompany = $this->http->FindSingleNode("//td[{$this->eq('Rental company:')}]/following-sibling::td[normalize-space()][1]");

        if (!empty($rentalCompany)) {
            $r->extra()->company($rentalCompany);

            foreach ($this->rentalProviders as $code => $detects) {
                foreach ($detects as $detect) {
                    if (stripos($rentalCompany, $detect) === 0) {
                        $r->setProviderCode($code);

                        break 2;
                    }
                }
            }
        }

        $pickUp = implode("\n", $this->http->FindNodes("//td[{$this->eq('Pick-up:')}]/following-sibling::td[normalize-space()][1]//text()[normalize-space()]"));

        if (preg_match("/^(?<date>.+)\n(?<address>[\s\S]+)\s+Phone:\s*(?<phone>[\d \(\)\-\.]+)$/", $pickUp, $m)) {
            $date = strtotime($m['date']);

            if (!empty($date)) {
                $r->pickup()
                    ->date($date);
            }
            $r->pickup()
                ->location(preg_replace("/\s+/", ' ', trim($m['address'])))
                ->phone(trim($m['phone']));
        }

        $dropOff = implode("\n", $this->http->FindNodes("//td[{$this->eq('Drop-off:')}]/following-sibling::td[normalize-space()][1]//text()[normalize-space()]"));

        if (preg_match("/^(?<date>.+)\n(?<address>[\s\S]+)\s+Phone:\s*(?<phone>[\d \(\)\-\.]+)$/", $dropOff, $m)) {
            $date = strtotime($m['date']);

            if (!empty($date)) {
                $r->dropoff()
                    ->date($date);
            }
            $r->dropoff()
                ->location(preg_replace("/\s+/", ' ', trim($m['address'])))
                ->phone(trim($m['phone']));
        }

        $conf = $this->http->FindSingleNode("//text()[{$this->contains(' confirmation#:')}]/ancestor::td[1]/following-sibling::td[normalize-space()][1]");

        if (!empty($conf)) {
            $r->general()
                ->confirmation($conf);
        }
        $carDesc = implode("\n", $this->http->FindNodes("//td[{$this->eq('Vehicle:')}]/following-sibling::td[normalize-space()][1]//text()[normalize-space()]"));

        if (preg_match("/^(.+) or similar/", $carDesc, $m)) {
            $r->car()
                ->model($m[1]);
        } elseif (preg_match("/^\s*(.+)\n(.+) or similar/", $carDesc, $m)) {
            $r->car()
                ->type($m[1])
                ->model($m[2])
            ;
        }

        if (!$r->getCancelled()) {
            $url = $this->http->FindSingleNode("//a[{$this->eq($this->t('View Payment Receipt'))}]/@href",
                null, true, "/.*pay\.stripe\.com.{1,5}receipts.*/");

            if (!empty($url)) {
                $http1 = clone $this->http;
                $http1->GetURL($url);

                $total = $http1->FindSingleNode("//td[{$this->eq($this->t('Amount charged'))}]/following-sibling::td[normalize-space() and string-length(normalize-space()) > 1][1]");

                if (preg_match('/^(?<amount>\d[,.\'\d]*)[ ]*(?<currency>[^\d)(]+)$/', $total, $m)
                    || preg_match('/^(?<currency>[^\d)(]+)[ ]*(?<amount>\d[,.\'\d]*)$/', $total, $m)) {
                    $r->price()
                        ->total(PriceHelper::parse($m['amount'], $m['currency']))
                        ->currency($m['currency']);
                }
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getHeader('date'));
        $this->ParseCar($email);

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
        return 3;
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

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
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
