<?php

namespace AwardWallet\Engine\edreams\Email;

class CarRental extends \TAccountChecker
{
    use \DateTimeTools;
    public $mailFiles = "edreams/it-4137829.eml";
    public $reBody = [
        'pt' => ['Detalhes da Reserva'],
    ];
    public $lang = '';
    public static $dict = [
        'pt' => [],
    ];

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $this->http->Response['body'];

        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if (stripos($body, $reBody[0]) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $body = $this->http->Response['body'];

        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if (stripos($body, $reBody[0]) !== false) {
                    $this->lang = $lang;

                    break;
                } else {
                    $this->lang = 'en';
                }
            }
        }
        $its = $this->parseEmail($parser);

        return [
            'parsedData' => ['Itineraries' => $its],
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], "@edreams.com") !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, "@edreams.com") !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseEmail()
    {
        $it = ['Kind' => 'L'];
        $it['Number'] = CONFNO_UNKNOWN;
        $total = $this->http->FindSingleNode("//text()[contains(., 'Total')]");

        if (preg_match("#\w+:\s+\w?(\D{1})([\d.,]*)#", $total, $m)) {
            $it['TotalCharge'] = preg_replace('#(\d+)\.(\d+\.\d+)#', '$1$2', str_replace(',', '.', $m[2]));
            $it['Currency'] = ($m[1] === '$') ? 'USD' : null;
        }
        $it['CarModel'] = implode(' ', $this->http->FindNodes("//img[contains(@src, 'rentalcars.com/images/car_images')]/following::tr[1]/descendant::text()[normalize-space(.)!='']"));
        $it['PickupLocation'] = $it['DropoffLocation'] = $this->http->FindSingleNode("//text()[contains(., 'Retirada')]/following::text()[normalize-space(.)!=''][1]");
        $it['PickupDatetime'] = strtotime($this->normalizeDate($this->http->FindSingleNode("//text()[contains(., 'Retirada')]/following::text()[normalize-space(.)!=''][2]")));
        $it['DropoffDatetime'] = strtotime($this->normalizeDate($this->http->FindSingleNode("//text()[contains(., 'Devolução')]/following::text()[normalize-space(.)!=''][2]")));

        return [$it];
    }

    private function normalizeDate($str)
    {
        $in = [
            '#(\w+)\s+(\d+)\s+(\d+) - (\d+:\d+)#',
        ];
        $out = [
            '$1 $2 $3 $4',
        ];

        return $this->dateStringToEnglish(preg_replace($in, $out, $str));
    }

    private function t($s)
    {
        if (!isset($this->lang) || empty(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }
}
