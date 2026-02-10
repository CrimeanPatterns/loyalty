<?php

namespace AwardWallet\Engine\booking\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class JunkUpdateFlight extends \TAccountChecker
{
    public $mailFiles = "booking/it-172717569.eml, booking/it-173325435.eml";
    public $subjects = [
        'Update to your flight ',
        'Mise à jour de votre vol',
    ];

    public $lang = '';
    public $date;

    public $detectLand = [
        "fr" => ['Vol pour'],
        "en" => ['Flight to'],
    ];

    public static $dictionary = [
        "en" => [
            'Male' => ['Male', 'Female'],
        ],
        "fr" => [
            'Male'                                => ['Femme'],
            'Your flight reservation was updated' => 'Votre vol a été mis à jour',
            'Flight to'                           => 'Vol pour',
            'Traveler details'                    => 'Identité du passager',
            'Paid'                                => 'Payé',
            'Flight'                              => 'Vol',
            'Selected seats'                      => 'Sièges sélectionnés',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@booking.com') !== false) {
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
        $this->assignLang();

        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'Booking.com')]")->length > 0) {
            return $this->http->XPath->query("//text()[{$this->contains($this->t('Your flight reservation was updated'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Flight to'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Traveler details'))}]")->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]booking.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if ($this->detectEmailByBody($parser) == true) {
            $xpath = "//img[contains(@src, 'takeoff')]";
            $nodes = $this->http->XPath->query($xpath);

            foreach ($nodes as $root) {
                $depText = implode("\n", $this->http->FindNodes("./following::text()[contains(normalize-space(), '·')][1]/ancestor::table[1]/descendant::text()[normalize-space()]", $root));

                if (preg_match("/\s[A-Z]{3}\s*\n(?:\d+\s*\w+\.?\s*[·]|\w+\s*\d+\s*[·])\s*\d+.+\s*\n.+$/u", $depText)) {
                    $email->setIsJunk(true);
                }
            }
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

    private function assignLang()
    {
        foreach ($this->detectLand as $lang => $words) {
            foreach ($words as $word) {
                if (($this->http->XPath->query("//text()[{$this->contains($this->t($word))}]")->length > 0)) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }
}
