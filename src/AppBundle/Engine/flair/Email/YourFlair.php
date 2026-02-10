<?php

namespace AwardWallet\Engine\flair\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class YourFlair extends \TAccountChecker
{
    public $mailFiles = "flair/it-186004049.eml, flair/it-189062678.eml, flair/it-349079967.eml, flair/it-352900911.eml, flair/it-354872875.eml, flair/it-381100008.eml, flair/it-397001272.eml";
    public $subjects = [
        'Your Flair Booking -',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'manage booking' => ['manage booking', 'manage my booking'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@flyflair.com') !== false) {
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
        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'Flair Airlines')]")->length > 0) {
            return ($this->http->XPath->query("//text()[{$this->contains($this->t('please arrive at the airport'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('manage booking'))}]")->length > 0)
                || ($this->http->XPath->query("//text()[{$this->contains($this->t('You’ve successfully cancelled your booking!'))}]")->length > 0
                    && $this->http->XPath->query("//text()[{$this->contains($this->t('reservation number'))}]")->length > 0);
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]flyflair\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->ParseFlight($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function ParseFlight(Email $email)
    {
        $f = $email->add()->flight();

        $confirmation = $this->http->FindSingleNode("//img[contains(@src, 'flair-email-assets/images/Ellipse-201.png')]/preceding::text()[normalize-space()='reservation number']/ancestor::div[2]", null, true, "/{$this->opt($this->t('reservation number'))}\s*([A-Z\d]{6,})/");

        if (empty($confirmation)) {
            $confirmation = $this->http->FindSingleNode("//text()[normalize-space()='manage my booking']/following::text()[normalize-space()='reservation number'][1]/ancestor::div[1]", null, true, "/{$this->opt($this->t('reservation number'))}\s*([A-Z\d]{6,})/u");
        }

        if (empty($confirmation)) {
            $confirmation = $this->http->FindSingleNode("//text()[normalize-space()='reservation number']/ancestor::div[2]", null, true, "/^{$this->opt($this->t('reservation number'))}\s*([A-Z\d]{6,})$/u");
        }
        $f->general()
            ->confirmation($confirmation);

        $travellers = array_unique($this->http->FindNodes("//img[contains(@src, 'flair-email-assets/images/Icon-ionic-md-person.png')]/following::text()[normalize-space()][1]"));

        if (count($travellers) > 0) {
            $f->general()
                ->travellers($travellers, true);
        }
        $infants = array_unique($this->http->FindNodes("//img[contains(@src, 'flair-email-assets/images/infant_icon.png')]/following::text()[normalize-space()][1]"));

        if (count($infants) > 0) {
            $f->general()
                ->infants($infants, true);
        }

        if ($this->http->XPath->query("//text()[normalize-space()='You’ve successfully cancelled your booking!']")->length > 0) {
            $f->general()
                ->cancelled()
                ->status('cancelled');
        }

        $dateRes = strtotime($this->http->FindSingleNode("//text()[contains(normalize-space(), 'Paid on')]", null, true, "/{$this->opt($this->t('Paid on'))}\s*(.+)/"));

        if (!empty($dateRes)) {
            $f->general()
                ->date($dateRes);
        }

        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'successfully updated your reservation')]")->length > 0) {
            $f->general()
                ->status('updated');
        }
        // not an account number
        // $accounts = $this->http->FindNodes("//text()[contains(normalize-space(), 'GST #')]", null, "/{$this->opt($this->t('GST #'))}([A-Z\d]{15,})/");
        //
        // if (count($accounts) > 0) {
        // $f->setAccountNumbers($accounts, false);
        // }

        $xpath = "//img[contains(@src, 'flair-email-assets/images/Ellipse-201.png')]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length == 0) {
            $nodes = $this->http->XPath->query("//img[contains(@src, 'Ellipse_201')]");
        }

        if ($nodes->length == 0) {
            $nodes = $this->http->XPath->query("//img[contains(@src, 'confirmation3/circle_M2')]");
        }

        foreach ($nodes as $key => $root) {
            $s = $f->addSegment();

            $segmentText = implode(' ', $this->http->FindNodes("./following::text()[contains(normalize-space(), 'departs')][1]/ancestor::*[2]/descendant::text()[normalize-space()]", $root));

            if (preg_match("/^departs\s*\d+\:\d+/", $segmentText)) {
                $segmentText = implode(' ', $this->http->FindNodes("./following::text()[contains(normalize-space(), 'departs')][1]/ancestor::table[2]/descendant::text()[normalize-space()]", $root));
            }

            //depart | october 22, 2022 departs 1:30 pm | arrives 3:30 pm YYZ toronto nonstop F8176 LAS las vegas
            if (preg_match("/^\D+\|\s*(?<depDate>\w+\s*\d+\,\s*\d{4})\s*departs\s*(?<depTime>[\d\:]+\s*(?:\s*[aApP][mM])?)[\s\|]+arrives\s*(?:-\s*(?<arrDate2>\w+\s*\d+\,\s*\d{4}\s*))?(?<arrTime>[\d\:]+(?:\s*[aApP][mM])?)\s*(?:\-\s*(?<arrDate>\w+\s*\d+\,\s*\d{4}))?\s*(?<depCode>[A-Z]{3}).+\s(?<fName>[A-Z\d]{2})(?<fNumber>\d{2,4})\s*(?<arrCode>[A-Z]{3}).+$/u", $segmentText, $m)
            || preg_match("/^\D+\|\s*(?<depDate>\w+\s*\d+\,\s*\d{4})\s*departs\s*(?<depTime>[\d\:]+\s*(?:\s*[aApP][mM])?)[\s\|]+arrives\s*(?:-\s*(?<arrDate2>\w+\s*\d+\,\s*\d{4}\s*))?(?<arrTime>[\d\:]+\s*(?:\s*[aApP][mM])?)\s*(?:\-\s*(?<arrDate>\w+\s*\d+\,\s*\d{4}))?\s*(?<depCode>[A-Z]{3}).+\s+(?<arrCode>[A-Z]{3})\s.+$/u", $segmentText, $m)) {
                if (isset($m['fName']) && !empty($m['fName'])) {
                    $s->airline()
                        ->name($m['fName'])
                        ->number($m['fNumber']);
                } else {
                    $s->airline()
                        ->name('F8')
                        ->noNumber();
                }

                $depDate = $this->normalizeDate($m['depDate'] . ', ' . $m['depTime']);

                $s->departure()
                    ->code($m['depCode'])
                    ->date($depDate);

                $s->arrival()
                    ->code($m['arrCode']);

                $aDate = $m['arrDate'];

                if (empty($aDate)) {
                    $aDate = $m['arrDate2'];
                }

                if (empty($aDate)) {
                    $aDate = $m['depDate'];
                }

                $arrDate = $this->normalizeDate($aDate . ', ' . $m['arrTime']);

                $s->arrival()
                    ->date($arrDate);

                $key++;

                foreach ($travellers as $traveller) {
                    $seat = $this->http->FindSingleNode("//text()[normalize-space()='all flight times are local']/following::text()[{$this->eq($traveller)}][$key]/ancestor::div[1]/following::div[normalize-space()][1]", null, true, "/{$this->opt($this->t('Seat'))}\s*(\d+[A-Z])/u");

                    if (!empty($seat)) {
                        $s->extra()
                            ->seat($seat);
                    }
                }
            }
        }

        $total = $this->http->FindSingleNode("//text()[normalize-space()='total']/following::text()[normalize-space()][1]/ancestor::*[1]");

        if (preg_match("/^(?<currency>\D)(?<total>[\d\.]+)$/us", $total, $m)
        || preg_match("/^\D\s*(?<total>[\d\.]+)\s*(?<currency>[A-Z]{3})$/us", $total, $m)) {
            $f->price()
                ->currency('CAD') // it's more likely than usd; see flair/ReservationPdf
                ->total($m['total']);

            $tax = $this->http->FindSingleNode("//text()[normalize-space()='taxes']/ancestor::div[1]/following-sibling::div[1]", null, true, "/^\D*([\d\.\,]+)$/");

            if (!empty($tax)) {
                $f->price()
                    ->tax(PriceHelper::parse($tax, $m['currency']));
            }

            $feeNodes = $this->http->XPath->query("//text()[normalize-space()='total']/ancestor::div[2]/preceding-sibling::div[not(contains(normalize-space(), 'taxes'))]");

            foreach ($feeNodes as $feeRoot) {
                $feeName = $this->http->FindSingleNode("./descendant::div[1]", $feeRoot);
                $feeSum = $this->http->FindSingleNode("./descendant::div[2]", $feeRoot, true, "/^\D*([\d\.\,]+)$/");

                if ($feeName == 'fare') {
                    $f->price()
                        ->cost($feeSum);
                } elseif (!empty($feeName)) {
                    $f->price()
                        ->fee($feeName, $feeSum);
                }
            }
        }
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

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
    }

    private function normalizeDate($str)
    {
        // $this->logger->debug('$str = ' . print_r($str, true));

        $in = [
            //december 1, 2021, 08:00 PM
            "#^\s*(\w+)\s+(\d+)\,\s*(\d{4})\,\s*((\d|0\d|10|11|12):\d+(?:\s*[AP]M)?)\s*$#ui",
            //december 1, 2021, 15:43 PM
            "#^\s*(\w+)\s+(\d+)\,\s*(\d{4})\,\s*((1[3-9]|2\d):\d+)(?:\s*[AP]M)?\s*$#ui",
        ];
        $out = [
            "$2 $1 $3, $4",
            "$2 $1 $3, $4",
        ];
        $str = preg_replace($in, $out, $str);
        // $this->logger->debug('$str = '.print_r( $str,true));

        // if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
        //     if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
        //         $str = str_replace($m[1], $en, $str);
        //     }
        // }

        return strtotime($str);
    }
}
