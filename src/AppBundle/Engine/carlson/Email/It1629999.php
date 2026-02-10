<?php

namespace AwardWallet\Engine\carlson\Email;

class It1629999 extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#(?:From|De)\s*:[^\n]*?carlsonhotels\.com|carlsonhotels.com[\s\)]+wrote#i', 'us', '3000'],
    ];
    public $reHtml = [
        ['#carlsonhotels[.]com#i', 'us', '/1'],
    ];
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = [
        ['#carlsonhotels\.com#i', 'us', ''],
    ];
    public $reProvider = [
        ['#carlsonhotels\.com#i', 'us', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en, fr";
    public $typesCount = "1";
    public $isAggregator = "";
    public $caseReference = "6912";
    public $upDate = "04.06.2015, 10:17";
    public $crDate = "";
    public $xPath = "";
    public $mailFiles = "carlson/it-1629999.eml, carlson/it-1981386.eml, carlson/it-2039470.eml, carlson/it-2071015.eml, carlson/it-2214539.eml, carlson/it-2529783.eml, carlson/it-2756958.eml, carlson/it-2757840.eml, carlson/it-2775490.eml, carlson/it-3129730.eml, carlson/it-3131474.eml, carlson/it-3131475.eml, carlson/it-3319722.eml";
    public $re_catcher = "#.*?#";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        return re_white('
							(?:
								Your confirmation number is |
								Votre numéro de confirmation est |
								The cancellation number is |
								Bekreftelsesnummeret ditt er
							)
							(\w+)
						');
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        return orval(
                            //node('(//font[@face="Arial, Helvetica, Geneva, SunSans-Regular, sans-serif" and @size="3"])[1]'),
                            node("(//img[contains(@src, '/images/') or contains(@alt, 'By Carlson')]
								/ancestor-or-self::td[1]/preceding-sibling::td//*[self::strong or self::b][1])[1]"),
                            node("//*[contains(text(), 'We are pleased to confirm your reservation')]/following::a[1]"),
                            node("//*[contains(text(), 'Det gleder oss å bekrefte din reservasjon hos')]/following::a[1]")
                        );
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $date = cell([
                            "Arrival Date",
                            "Date d'arrivée",
                            "Ankomstdato",
                        ], +1);
                        $time = cell([
                            'Check-In Time',
                            "Heure d'arrivée",
                            "Innsjekkingstid",
                        ], +1);

                        if (!totime(en($date))) { // utf bug fix
                            $date = mb_strtolower($date, 'UTF-8');
                        }

                        $dt = nice(en("$date $time"));

                        return totime($dt);
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        $date = cell([
                            "Departure Date",
                            "Date de départ",
                            "Avreisedato",
                        ], +1);
                        $time = cell([
                            "Check-Out Time",
                            "Heure de départ",
                            "Utsjekkingstid",
                        ], +1);

                        if (!totime(en($date))) { // utf bug fix
                            $date = mb_strtolower($date, 'UTF-8');
                        }

                        $dt = nice(en("$date $time"));

                        return totime($dt);
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        $addr = orval(
                            text(xpath("//img[contains(@src, '/images/') or contains(@alt, 'By Carlson')]
							/ancestor-or-self::td[1]/preceding-sibling::td//a[not(contains(.,'@'))][1]/ancestor::tr[1]/following-sibling::tr[1]")),
                            text(xpath("//*[contains(text(), 'We are pleased to confirm your reservation')]/following::a[1]/following::tr[1]"))
                        );

                        return [
                            'Address' => nice(glue(re("#^(.*?)\n\s*([+\-\d \(\)]{5,})$#ims", $addr))),
                            'Phone'   => re(2),
                        ];
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        $parts = nodes("
							//*[
								contains(text(), 'Titre') or
								contains(text(), 'Title') or
								contains(text(), 'Tittel')
							]
							/ancestor::table[1]//td[2]
						");
                        $name = implode(' ', $parts);
                        $name = clear('/[\d\s]*$/', $name);

                        return nice($name);
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        return re_white("
							(?:
								of Adults |
								Nb d'adultes |
								Antall voksne
							):
							(\d+)
						");
                    },

                    "Kids" => function ($text = '', $node = null, $it = null) {
                        return re_white("
							(?:
								of Children |
								Nb d'enfants |
								Antall barn
							):
							(\d+)
						");
                    },

                    "Rate" => function ($text = '', $node = null, $it = null) {
                        $rate = re_white('
							(?:
								Total Rate |
								Prix total |
								Total pris
							):
							(.+?) \*
						');

                        return preg_replace("#\s+#", " ", $rate);
                    },

                    "RateType" => function ($text = '', $node = null, $it = null) {
                        return re("#(?:Rate Type|Pristype):\s*(.*?)\n#ims");
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        $policy = node('
							//*[
								contains(text(), "Cancellation Policy") or
								contains(text(), "Politique d\'annulation") or
								contains(text(), "Avbestillingsregler")
							]
							/following::tr[1]
						');

                        return nice($policy);
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        $info = node("
							//*[
								contains(text(), 'Rate Type') or
								contains(text(), 'Catégorie de prix') or
								contains(text(), 'Pristype')
							]
							/preceding::tr[1]
						");

                        $q = white('
							(?P<RoomType> .+?)  (?: , | - | $)
							(?P<RoomTypeDescription> .+)?
						');
                        $res = re2dict($q, $info);

                        return $res;
                    },

                    "Taxes" => function ($text = '', $node = null, $it = null) {
                        $x = re_white('
							(?:
								Estimated Taxes |
								Estimation des taxes |
								Beregnede avgifter
							).*?
							([\d.,]+)
						');

                        return cost($x);
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        $x = re_white('
							(?:
								Estimated Total Price |
								Estimation du prix total |
								Beregnet total pris
							).*?
							([\d.,]+ \w+)
						');

                        return total($x, 'Total');
                    },

                    "SpentAwards" => function ($text = '', $node = null, $it = null) {
                        return preg_replace("#(\d)[,\.](\d{3})#", "$1$2", node("//*[contains(text(), 'Redemption Points') or contains(text(), 'Innløsningspoeng')]/ancestor::td[1]/following-sibling::td[1]", null, false, '/[\d,\.]+/ims'));
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        if (re_white('We are pleased to confirm | Nous avons le plaisir de vous confirmer | Det gleder oss å bekrefte')) {
                            return 'confirmed';
                        } elseif (rew('Your booking has been cancelled')) {
                            return 'cancelled';
                        }
                    },

                    "Cancelled" => function ($text = '', $node = null, $it = null) {
                        if (rew('Your booking has been cancelled')) {
                            return true;
                        }
                    },
                ],
            ],
        ];
    }

    public static function getEmailTypesCount()
    {
        return 1;
    }

    public static function getEmailLanguages()
    {
        return ["en", "fr", "no"];
    }

    public function IsEmailAggregator()
    {
        return false;
    }
}
