<?php

// bcdtravel + screenshot

namespace AwardWallet\Engine\booking\Email;

class ConfirmationHtml2017 extends \TAccountChecker
{
    public static $dict = [
        //'en' => [],
        'de' => [
        ],
    ];

    protected $lang = '';
    protected $subject = [
        //'en' => [],
        'de' => ['Buchung bestätigt für die Unterkunft Novum '],
    ];
    protected $body = [
        //'en' => [],
        'de' => ['vornehmen oder der Unterkunft eine Frage stellen'],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['subject']) && isset($headers['from'])
                && stripos($headers['from'], '@booking.com') !== false && $this->detect($headers['subject'], $this->subject);
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return stripos($parser->getHTMLBody(), 'booking.com') !== false && $this->detect($parser->getHTMLBody(), $this->body);
    }

    public function detectEmailFromProvider($from)
    {
        return (bool) preg_match("/[@\.]booking\./", $from);
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $its = [];

        if ($this->lang = $this->detect($parser->getHTMLBody(), $this->body)) {
            $its[] = $this->parseEmail();
        }

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => 'ConfirmationHtml2017' . ucfirst($this->lang),
        ];
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    protected function t($s)
    {
        if (isset($this->lang) && isset(self::$dict[$this->lang][$s])) {
            return self::$dict[$this->lang][$s];
        }

        return $s;
    }

    /**
     * Are case sensitive. Example:
     * <pre>
     * var $reSubject = [
     * 'en' => ['Reservation Modify'],
     * ];
     * </pre>.
     *
     * @param type $haystack
     * @param type $arrayNeedle
     *
     * @return type
     */
    protected function detect($haystack, $arrayNeedle)
    {
        foreach ($arrayNeedle as $lang => $needles) {
            if (is_array($needles)) {
                foreach ($needles as $needle) {
                    if (stripos($haystack, $needle) !== false) {
                        return $lang;
                    }
                }
            } elseif (stripos($haystack, $needles) !== false) {
                return $lang;
            }
        }
    }

    protected function parseEmail()
    {
        $result = ['Kind' => 'R'];

        $result['ConfirmationNumber'] = $this->http->FindSingleNode('//text()[starts-with(.,"Buchungsnummer:")]/following-sibling::strong[1]');
        $hotel = join("\n", $this->http->FindNodes('(//text()[.="Telefon:"])[1]/ancestor::td[1]//text()'));

        if (preg_match('/^\n*\s*(.+?)\n.*?Telefon:\s+([+\d\s()-]+)/us', $hotel, $matches)) {
            $result['HotelName'] = $matches[1];
            $result['Phone'] = trim($matches[2]);
        }
        $addr = array_filter($this->http->FindNodes('//text()[.="Wegbeschreibung anzeigen"]/ancestor::td[1]//text()'));

        if (array_search('Wegbeschreibung anzeigen', $addr)) {
            array_pop($addr);
            $result['Address'] = join(', ', $addr);
        }
        $dateIn = $this->http->FindSingleNode('//text()[.="Anreise"]/ancestor::td[1]');

        if (preg_match('/(\d+\.? [[:alpha:]]+ \d+).*?(\d+:\d+)/u', $dateIn, $matches)) {
            $result['CheckInDate'] = strtotime($matches[1] . ', ' . $matches[2]);
        }
        $dateOut = $this->http->FindSingleNode('//text()[.="Abreise"]/ancestor::td[1]');

        if (preg_match('/(\d+\.? [[:alpha:]]+ \d+).*?(\d+:\d+)/u', $dateOut, $matches)) {
            $result['CheckOutDate'] = strtotime($matches[1] . ', ' . $matches[2], false);
        }

        // It is possible that there will be several
        $result['GuestNames'] = $this->http->FindSingleNode('//text()[.="Name des Gastes"]/ancestor::td[1]/following-sibling::td[1]', null, false, '/^(.+?)\s+Name des Gastes bearbeiten/');
        $result['Guests'] = $this->http->FindSingleNode('//text()[.="Anzahl der Gäste"]/ancestor::td[1]/following-sibling::td[1]', null, false, '/(\d+)\s+Person/');

        $result['CancellationPolicy'] = $this->http->FindSingleNode('//text()[.="Stornierungsbedingungen"]/ancestor::td[1]/following-sibling::td[1]');

        if ($total = $this->http->FindSingleNode('//text()[normalize-space(.)="Gesamtpreis"]/ancestor::td[1]/following-sibling::td[1]')) {
            $result['Total'] = preg_replace('/[^\d.]+/', '', $total);
            $result['Currency'] = preg_replace(['/[\d.,\s]+/', '/€/', '/^\$$/'], ['', 'EUR', 'USD'], $total);
            $result['Taxes'] = preg_replace('/[^\d.]+/', '', $this->http->FindSingleNode('//text()[contains(., "Mehrwertsteuer ist inbegriffen")]/ancestor::td[1]/following-sibling::td[1]'));
        }

        return $result;
    }
}
