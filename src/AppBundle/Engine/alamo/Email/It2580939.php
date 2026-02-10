<?php

namespace AwardWallet\Engine\alamo\Email;

class It2580939 extends \TAccountCheckerExtended
{
    public $reFrom = [
        ['#[@.]alamo[.]#i', 'us', ''],
    ];

    public $mailFiles = "alamo/it-2580939.eml, alamo/it-2694008.eml";

    private $detects = [
        'Alamo Reservierung',
        'Alamo Rent a Car',
        'Alamo Rent a Car (Germany) Buchungsbestätigung',
    ];

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        foreach ($this->detects as $detect) {
            if (stripos($body, $detect) !== false) {
                return true;
            }
        }

        return false;
    }

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "L";
                    },

                    "Number" => function ($text = '', $node = null, $it = null) {
                        return orval(
                            reni('Ihre Reservierungsnummer ist: (\w+)'),
                            reni('Ihr Reservierungsnummer: (\w+)'),
                            reni('Your reservation number is: (\w+)')
                        );
                    },

                    "PickupDatetime" => function ($text = '', $node = null, $it = null) {
                        $q = white('(?: \) | \d+)
							(?:Anmietung|Pick Up)    .*?  (?P<PickupDatetime> \d+ .+? \d+:\d+)
							(?P<PickupLocation> .+?)
							(?:Telefon|Phone) : (?P<PickupPhone> \d+)
						');
                        $res = re2dict($q, $text);

                        $res['PickupDatetime'] = totime(en(arrayVal($res, 'PickupDatetime')));

                        return $res;
                    },

                    "DropoffDatetime" => function ($text = '', $node = null, $it = null) {
                        $q = white('
							(?:Abgabe|Drop Off) :    .*?  (?P<DropoffDatetime> \d+ .+? \d+:\d+)
							(?P<DropoffLocation> .+?)
							(?:Telefon|Phone) : (?P<DropoffPhone> \d+)
						');
                        $res = re2dict($q, $text);
                        $res['DropoffDatetime'] = totime(en(arrayVal($res, 'DropoffDatetime')));

                        return $res;
                    },

                    "CarType" => function ($text = '', $node = null, $it = null) {
                        $info = node("//*[text() = 'Buchungsdetails' or text() = 'Reservation Details:']/following::span[1]");
                        $q = white('
							(?P<CarType> .+?) ,
							(?P<CarModel> .+)
						');
                        $res = re2dict($q, $info);

                        return $res;
                    },

                    "RenterName" => function ($text = '', $node = null, $it = null) {
                        return nice(node("//*[text() = 'Fahrer' or text() = 'Driver:']/following::*[name() = 'p' or name() = 'td'][not(.//td)][1]"));
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        $x = rew('(?: Gesamt | Total ) : (. [\d.,]+) \n');

                        return total($x);
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        if (rew('Vielen Dank für Ihre Alamo Reservierung')) {
                            return 'confirmed';
                        } elseif (rew('Vielen Dank für Ihre Alamo Buchung!')) {
                            return 'confirmed';
                        } elseif (rew('Thank you again for using Alamo.co.uk to make your reservation.')) {
                            return 'confirmed';
                        }
                    },
                ],
            ],
        ];
    }

    public static function getEmailTypesCount()
    {
        return 2;
    }

    public static function getEmailLanguages()
    {
        return ["de", "en"];
    }
}
