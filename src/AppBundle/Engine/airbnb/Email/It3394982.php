<?php

namespace AwardWallet\Engine\airbnb\Email;

use AwardWallet\Engine\MonthTranslate;

class It3394982 extends \TAccountChecker
{
    public $mailFiles = "airbnb/it-3394982.eml, airbnb/it-5012493.eml, airbnb/it-6430097.eml, airbnb/it-8557916.eml"; // +2 emails from bcdtravel
    public $reBody = [
        'en' => ['and added you as a guest', 'and shared the itinerary with you'],
        'de' => ['gebucht und den Reiseplan mit Dir geteilt', 'gebucht und Dich als Gast hinzugefügt'],
        'pt' => ['e adicionou-o como hóspede', 'e compartilhou o itinerário com você'],
        'es' => ['ha compartido el itinerario contigo', 'y te ha añadido a la lista de huéspedes'],
        'nl' => ['en heeft jou als gast toegevoegd', 'en voegde jou toe als gast'],
        'pl' => ['dodaje Cię jako gościa'],
        'fr' => ['indiqué que vous faisiez partie de ce voyage'],
        'da' => ['tilføjet dig som gæst'],
        'it' => ["ha condiviso l'itinerario con te"],
    ];

    private $lang = '';

    private static $dictionary = [
        'en' => [],
        'de' => [
            'Confirmation code:'            => 'Bestätigungscode:',
            'Check In'                      => ['Check-in', 'Check-In'],
            'Check Out'                     => ['Check-out', 'Check-Out'],
            'booked'                        => 'hat',
            'shared the itinerary with you' => 'gebucht und den Reiseplan mit Dir geteilt',
            'added you as a guest'          => 'gebucht und Dich als Gast hinzugefügt',
            'Hi '                           => 'Hallo ',
            'a place in'                    => 'eine Unterkunft in',
        ],
        'pt' => [
            'Confirmation code:'            => 'Código de confirmação:',
            'Check In'                      => 'Check-in',
            'Check Out'                     => 'Checkout',
            'booked'                        => 'reservou',
            'shared the itinerary with you' => 'compartilhou o itinerário com você',
            'added you as a guest'          => 'adicionou-o como hóspede',
            'Hi '                           => 'Olá, ',
            'a place in'                    => 'um espaço em',
        ],
        'es' => [
            'Confirmation code:'            => 'Código de confirmación:',
            'Check In'                      => 'Llegada',
            'Check Out'                     => 'Salida',
            'booked'                        => ['ha reservado', 'reservado'],
            'shared the itinerary with you' => 'ha compartido el itinerario contigo',
            'added you as a guest'          => 'te ha añadido a la lista de huéspedes',
            'Hi '                           => 'Hola, ',
            'a place in'                    => 'un alojamiento en',
        ],
        'nl' => [
            'Confirmation code:' => 'Bevestigingscode:',
            'Check In'           => 'Aankomst',
            'Check Out'          => 'Vertrek',
            'booked'             => ['heeft', 'boekte'],
            //			'shared the itinerary with you' => '',
            'added you as a guest' => ['heeft jou als gast toegevoegd', 'en voegde jou toe als gast'],
            'Hi '                  => ['Hallo ', 'Hoi '],
            'a place in'           => ['een accommodatie gereserveerd in', 'een plek in'],
        ],
        'pl' => [
            'Confirmation code:' => 'Kod potwierdzenia:',
            'Check In'           => 'Przyjazd',
            'Check Out'          => 'Wyjazd',
            'booked'             => 'rezerwuje',
            //			'shared the itinerary with you' => '',
            'added you as a guest' => 'dodaje Cię jako gościa',
            'Hi '                  => 'Witaj ',
            'a place in'           => 'miejsce pobytu w:',
        ],
        'fr' => [
            'Confirmation code:' => 'Code de confirmation :',
            'Check In'           => 'Arrivée',
            'Check Out'          => 'Départ',
            'booked'             => 'a réservé',
            //			'shared the itinerary with you' => '',
            'added you as a guest' => 'indiqué que vous faisiez partie de ce voyage',
            'Hi '                  => 'Bonjour ',
            'a place in'           => ['un logement à', 'un lit à'],
        ],
        'da' => [
            'Confirmation code:' => 'Bekræftelseskode:',
            'Check In'           => 'Indtjekning',
            'Check Out'          => 'Udtjekning',
            'booked'             => 'har booket',
            //			'shared the itinerary with you' => '',
            'added you as a guest' => 'og tilføjet dig som gæst',
            'Hi '                  => 'Hej ',
            'a place in'           => 'en bolig i',
        ],
        'it' => [
            'Confirmation code:'            => 'Codice di conferma:',
            'Check In'                      => 'Check-in',
            'Check Out'                     => 'Check-out',
            'booked'                        => 'ha prenotato',
            'shared the itinerary with you' => "ha condiviso l'itinerario con te",
            //			'added you as a guest' => '',
            //			'Hi ' => '',
            'a place in' => 'un posto a',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers['from'], '@airbnb.com') !== false || stripos($headers['from'], 'invitation@airbnb.com') !== false) {
            return true;
        }
        $condition1 = stripos($headers['from'], '@airbnb.com') !== false;
        $condition2 = stripos($headers['subject'], 'Reservation Itinerary') !== false || stripos($headers['subject'], 'trip invitation') !== false;

        if ($condition1 && $condition2) {
            return true;
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (stripos($body, 'airbnb.com') === false) {
            return false;
        }

        foreach ($this->reBody as $rules) {
            foreach ($rules as $rule) {
                if (stripos($body, $rule) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@airbnb.com') !== false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $itineraries = [];
        $body = $parser->getHTMLBody();

        if (empty($body)) {
            $body = $parser->getPlainBody();
            $this->http->SetBody($body);
        }
        $this->ParseEmail($itineraries);

        return [
            'parsedData' => [
                'Itineraries' => $itineraries,
            ],
            'emailType' => 'ReservationItinerary' . ucfirst($this->lang),
        ];
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    protected function ParseEmail(&$itineraries)
    {
        $patterns = [
            'time' => '/(\d+:\d+(?:\s*[AP.]M)?)/i',
        ];

        $body = $this->http->Response['body'];

        foreach ($this->reBody as $lang => $rules) {
            foreach ($rules as $rule) {
                if (stripos($body, $rule) !== false) {
                    $this->lang = $lang;

                    break;
                }
            }
        }

        $it = [];
        $it['Kind'] = 'R';

        $guest = $this->http->FindSingleNode("//text()[({$this->contains($this->t('booked'))}) and contains(normalize-space(./ancestor::*[1]),'{$this->t('shared the itinerary with you')}')][1]", null, true, "#^(.+)\s+(" . $this->preg_implode($this->t('booked')) . ")#U");

        if (!empty($guest)) {
            $it['GuestNames'][] = $guest;
        } else {
            $guest = $this->http->FindSingleNode("//text()[({$this->contains($this->t('booked'))}) and ({$this->contains($this->t('added you as a guest'), 'normalize-space(./ancestor::*[1])')})][1]", null, true, "#^(.+)\s+(" . $this->preg_implode($this->t('booked')) . ")#U");

            if (!empty($guest)) {
                $it['GuestNames'][] = $guest;
                $it['GuestNames'][] = $this->http->FindSingleNode("//text()[{$this->startsWith($this->t('Hi '))}][1]", null, true, "#(?:" . $this->preg_implode($this->t('Hi ')) . ")([^,:]+)[,:]*\s*$#");
            }
        }

        if (isset($it['GuestNames'])) {
            $it['GuestNames'] = array_filter($it['GuestNames']);
        }

        $dateCheckIn = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Check In'))}]/following::text()[normalize-space(.)][1]");
        $timeCheckIn = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Check In'))}]/following::text()[normalize-space(.)][2]", null, true, $patterns['time']);

        if ($dateCheckIn) {
            $dateCheckIn = $this->normalizeDate($dateCheckIn, $this->lang);
            $timeCheckIn = str_replace(['Anytime after', 'Flexible'], '', $timeCheckIn);
            $it['CheckInDate'] = strtotime($dateCheckIn . ', ' . $timeCheckIn);
        }

        $dateCheckOut = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Check Out'))}]/following::text()[normalize-space(.)][1]");
        $timeCheckOut = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Check Out'))}]/following::text()[normalize-space(.)][2]", null, true, $patterns['time']);

        if ($dateCheckOut) {
            $dateCheckOut = $this->normalizeDate($dateCheckOut, $this->lang);
            $it['CheckOutDate'] = strtotime($dateCheckOut . ', ' . $timeCheckOut);
        }

        if (!$it['HotelName'] = trim($this->http->FindSingleNode('//img[string-length(@alt)>5 and contains(@src,"=large")]/@alt'))) {
            $it['HotelName'] = trim($this->http->FindSingleNode('//img[string-length(@alt)>5][./parent::a[contains(@href, "/rooms/")]]/@alt'));
        }

        if (!$it['Address'] = implode(', ', array_filter($this->http->FindNodes('//img[contains(@src,"maps.google")]/ancestor::td[1]/following-sibling::td[1]//text()')))) {
            if (!$it['Address'] = implode(', ', array_filter($this->http->FindNodes("//text()[" . $this->eq("Getting there") . "]/following::table[1]/descendant::text()[normalize-space(.)]")))) {
                $it['Address'] = $this->http->FindSingleNode("//text()[" . $this->contains($this->t("a place in")) . "]", null, true, "#(?:" . $this->preg_implode($this->t("a place in")) . ")\s+(.+?)\s*(?:\.|$)#");
            }
        }

        //		if (empty($it['Address'])) // don't do this
        //			$it['Address'] = $it['HotelName'];

        if (!empty($it['HotelName'])) {
            $it['RoomType'] = $this->http->FindSingleNode("//text()[" . $this->contains(array_filter(preg_split("#['\"]#", $it['HotelName'])), 'normalize-space(.)', ' and ') . "][1]/ancestor::div[1]//*[contains(@style, '#9ca299')]");
        }
        // Link is no longer available, example: it-5012493.eml

        $roomTypeDesc = $this->http->FindSingleNode('//text()[normalize-space(.)="' . $this->t('House Rules') . '"]/following::p[not(contains(.,"' . $this->t('Cancel') . '")) and not(contains(.,"' . strtolower($this->t('Cancel')) . '")) and position()=1]');

        if ($roomTypeDesc) {
            $it['RoomTypeDescription'] = $roomTypeDesc;
        }

        $cancelPolicyP1 = $this->http->FindSingleNode('//p[contains(.,"' . $this->t('Cancellation Policy:') . '")]', null, true, '/:\s*(.+)/');
        $cancelPolicyP2 = $this->http->FindSingleNode('//p[contains(.,"' . $this->t('Cancellation Policy:') . '")]/following-sibling::p[1]');

        if ($cancelPolicyP1 || $cancelPolicyP2) {
            $it['CancellationPolicy'] = trim($cancelPolicyP1 . "\n" . $cancelPolicyP2);
        }

        $it['ConfirmationNumber'] = $this->http->FindSingleNode("//text()[contains(.,'{$this->t('Confirmation code:')}')]", null, false, '/:\s*([A-Z\d]{5,})/');

        if (empty($this->http->FindSingleNode("//text()[contains(.,'{$this->t('Confirmation code:')}')]"))) {
            $it['ConfirmationNumber'] = CONFNO_UNKNOWN;
        }
        $itineraries[] = $it;
    }

    protected function normalizeDate($subject, $lang)
    {
        $pattern = [
            '/\w+, (\d{1,2})\. (\w+) (\d{2,4})/',	// Do, 29. Juni 2017
            '/\w+, (\d{1,2}) (\w+), (\d{2,4})/',	// Fri, 21 April, 2017
            '/\w+, (\d{1,2}) de (\w+) de (\d{2,4})/u',	// Qua, 15 de Março de 2017
        ];
        $replacement = [
            '$1 $2 $3',
            '$1 $2 $3',
            '$1 $2 $3',
        ];

        return $this->dateTranslate('/\d{1,2} ([[:alpha:]]+) \d{2,4}/u', preg_replace($pattern, $replacement, trim($subject)), $lang);
    }

    protected function dateTranslate($pattern, $string, $lang)
    {
        if (preg_match($pattern, $string, $matches)) {
            if ($en = MonthTranslate::translate($matches[1], $lang)) {
                return str_replace($matches[1], $en, $matches[0]);
            } else {
                return $matches[0];
            }
        } else {
            return $string;
        }
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function contains($field, $text = 'normalize-space(.)', $oper = ' or ')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode($oper, array_map(function ($s) use ($text) { return "contains(" . $text . ", \"{$s}\")"; }, $field));
    }

    private function startsWith($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) { return "starts-with(normalize-space(.),\"{$s}\")"; }, $field));
    }

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return implode("|", array_map(function ($s) { return "(?:" . preg_quote($s) . ")"; }, $field));
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) { return "normalize-space(.)=\"{$s}\""; }, $field));
    }
}
