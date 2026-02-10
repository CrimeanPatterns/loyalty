<?php

namespace AwardWallet\Engine\expedia\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Common\Event;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BigItineraryHtml extends \TAccountCheckerExtended
{
    public $mailFiles = "expedia/it-1.eml, expedia/it-10.eml, expedia/it-121.eml, expedia/it-12182904.eml, expedia/it-13.eml, expedia/it-131.eml, expedia/it-14596991.eml, expedia/it-1462114.eml, expedia/it-1484558.eml, expedia/it-151.eml, expedia/it-1542084.eml, expedia/it-1582570.eml, expedia/it-1584490.eml, expedia/it-1584924.eml, expedia/it-16.eml, expedia/it-1600319.eml, expedia/it-1605441.eml, expedia/it-1625581.eml, expedia/it-1632447.eml, expedia/it-1637683.eml, expedia/it-1679931.eml, expedia/it-1679933.eml, expedia/it-1682282.eml, expedia/it-1682384.eml, expedia/it-1690968.eml, expedia/it-17.eml, expedia/it-1785627.eml, expedia/it-18.eml, expedia/it-1803781.eml, expedia/it-1808690.eml, expedia/it-1827609.eml, expedia/it-1847487.eml, expedia/it-1847968.eml, expedia/it-1852239.eml, expedia/it-1881178.eml, expedia/it-1881371.eml, expedia/it-1891645.eml, expedia/it-1895294.eml, expedia/it-19.eml, expedia/it-1981397.eml, expedia/it-20.eml, expedia/it-2018034.eml, expedia/it-2056300.eml, expedia/it-2068500.eml, expedia/it-21.eml, expedia/it-2108981.eml, expedia/it-2109106.eml, expedia/it-2109211.eml, expedia/it-2110416.eml, expedia/it-2145002.eml, expedia/it-2145152.eml, expedia/it-2145294.eml, expedia/it-2145391.eml, expedia/it-2145741.eml, expedia/it-2145836.eml, expedia/it-2146118.eml, expedia/it-2146198.eml, expedia/it-2146309.eml, expedia/it-2146713.eml, expedia/it-2148255.eml, expedia/it-2148711.eml, expedia/it-2169073.eml, expedia/it-2191240.eml, expedia/it-2193662.eml, expedia/it-2193761.eml, expedia/it-2197378.eml, expedia/it-2200930.eml, expedia/it-2211630.eml, expedia/it-2231981.eml, expedia/it-2250053.eml, expedia/it-2313856.eml, expedia/it-243093754.eml, expedia/it-2438068.eml, expedia/it-2442408.eml, expedia/it-2455158.eml, expedia/it-2461042.eml, expedia/it-2557449.eml, expedia/it-2565999.eml, expedia/it-26.eml, expedia/it-2614124.eml, expedia/it-2614189.eml, expedia/it-2616448.eml, expedia/it-27.eml, expedia/it-2775974.eml, expedia/it-2787660.eml, expedia/it-2791037.eml, expedia/it-28.eml, expedia/it-2828058.eml, expedia/it-2841385.eml, expedia/it-2863049.eml, expedia/it-2863051.eml, expedia/it-2865522.eml, expedia/it-2875359.eml, expedia/it-29.eml, expedia/it-29131460.eml, expedia/it-2917399.eml, expedia/it-2922495.eml, expedia/it-2932922.eml, expedia/it-2956374.eml, expedia/it-2998823.eml, expedia/it-30.eml, expedia/it-3067892.eml, expedia/it-3067898.eml, expedia/it-31.eml, expedia/it-3129165.eml, expedia/it-3129476.eml, expedia/it-3129477.eml, expedia/it-3130425.eml, expedia/it-3131783.eml, expedia/it-3133937.eml, expedia/it-314095267.eml, expedia/it-3181267.eml, expedia/it-32.eml, expedia/it-3236749.eml, expedia/it-3285426.eml, expedia/it-33.eml, expedia/it-3300343.eml, expedia/it-3388201.eml, expedia/it-3452071.eml, expedia/it-3461847.eml, expedia/it-3517117.eml, expedia/it-3532410.eml, expedia/it-3557217.eml, expedia/it-3595178.eml, expedia/it-3775288.eml, expedia/it-3779073.eml, expedia/it-3802137.eml, expedia/it-3802148.eml, expedia/it-3893644.eml, expedia/it-3997794.eml, expedia/it-4030408.eml, expedia/it-4030419.eml, expedia/it-4070396.eml, expedia/it-41.eml, expedia/it-4116292.eml, expedia/it-4126646.eml, expedia/it-4136314.eml, expedia/it-4136324.eml, expedia/it-4191447.eml, expedia/it-4192596.eml, expedia/it-4209212.eml, expedia/it-4222359.eml, expedia/it-4231556.eml, expedia/it-4260578.eml, expedia/it-4271569.eml, expedia/it-4284527.eml, expedia/it-43.eml, expedia/it-4301899.eml, expedia/it-4306432.eml, expedia/it-4317441.eml, expedia/it-4324441.eml, expedia/it-4330322.eml, expedia/it-4352000.eml, expedia/it-4356569.eml, expedia/it-4363742.eml, expedia/it-4484379.eml, expedia/it-4500140.eml, expedia/it-4576414.eml, expedia/it-4806216.eml, expedia/it-4896952.eml, expedia/it-4899837.eml, expedia/it-4899841.eml, expedia/it-4919681.eml, expedia/it-4920711.eml, expedia/it-4938213.eml, expedia/it-5.eml, expedia/it-5072814.eml, expedia/it-5105579.eml, expedia/it-5121464.eml, expedia/it-5166181.eml, expedia/it-55.eml, expedia/it-5523945.eml, expedia/it-56.eml, expedia/it-5682083.eml, expedia/it-5762657.eml, expedia/it-58.eml, expedia/it-5995828.eml, expedia/it-6.eml, expedia/it-60.eml, expedia/it-6079170.eml, expedia/it-6124100.eml, expedia/it-62.eml, expedia/it-68.eml, expedia/it-69.eml, expedia/it-89827224.eml, expedia/it-9970615.eml, expedia/it-355662927.eml";

    protected $lang = '';
    private $hotelHeaderRule = [
        'expedia' => "@bgcolor='#003368' or contains(@style,'background:#003368') or
	                  contains(@style,'background:#003467') or @bgcolor='#002060' or
	                  contains(@style,'background-color: rgb(0, 51, 104)') or 
	                  ((@bgcolor or contains(@style,'background')) and @colspan=3)",
        'travelocity' => "@bgcolor='#003368' or contains(@style, 'background:#003368') or
						@bgcolor='#003467' or contains(@style, 'background:#003467') or
						@bgcolor='#002060' or contains(@style, 'background:#002060') or
						@bgcolor='#072B61' or contains(@style, 'background:#072B61') or
						contains(@style, 'rgb(7, 43, 97)') or
						contains(@class, 'blueGradientFlight') or 
	                  ((@bgcolor or contains(@style,'background')) and @colspan=3)",
        'orbitz' => "@bgcolor='#00253C' or contains(@style, 'background:#00253C;') or 
	                  ((@bgcolor or contains(@style,'background')) and @colspan=3)",
        'hotwire' => "contains(@bgcolor, '#FFFFFF') or contains(@style, 'background:#FFFFFF') or contains(@style, 'background: #ffffff') or 
	                  ((@bgcolor or contains(@style,'background')) and @colspan=3)",
        'ebookers' => "@bgcolor='#0D3880' or
                      (@bgcolor or contains(@style,'background')) and @colspan=3", //95% universal
        'hotels' => "@bgcolor='#D41200' or
                      (@bgcolor or contains(@style,'background')) and @colspan=3", //95% universal
        'cheaptickets' => "@bgcolor='#454545' or
                      (@bgcolor or contains(@style,'background')) and @colspan=3", //95% universal
        'lastminute' => "@bgcolor='#454545' or
                      (@bgcolor or contains(@style,'background')) and @colspan=3", //95% universal
        'alaskaair' => "@bgcolor='#454545' or
                      (@bgcolor or contains(@style,'background')) and @colspan=3", //95% universal
        'chase' => "@bgcolor='#454545' or
                      (@bgcolor or contains(@style,'background')) and @colspan=3", //95% universal
        'thomascook' => "@bgcolor='#003368' or
                      (@bgcolor or contains(@style,'background')) and @colspan=3",
        'mrjet' => "@bgcolor='#24324F' or
                      (@bgcolor or contains(@style,'background')) and @colspan=3",
        'riu' => "@bgcolor='#003368' or contains(@style,'background:#003368') or
                      (@bgcolor or contains(@style,'background')) and @colspan=3",
        'marriott' => "@bgcolor='#003368' or contains(@style,'background:#003368') or
                      (@bgcolor or contains(@style,'background')) and @colspan=3",
        'hawaiian' => "@bgcolor='#003368' or contains(@style,'background:#003368') or
                      (@bgcolor or contains(@style,'background')) and @colspan=3",
        'rbcbank' => "@bgcolor='#2E51A1' or contains(@style,'background:#2E51A1') or
                      (@bgcolor or contains(@style,'background')) and @colspan=3",
    ];
    private $text;
    private $code;
    private $bodies = [//expedia should be last
        'travelocity' => [
            'Travelocity',
        ],
        'orbitz' => [
            'Orbitz',
        ],
        'hotwire' => [
            'Hotwire',
        ],
        'ebookers' => [
            'ebookers',
        ],
        'hotels' => [
            'Hotels.com',
        ],
        'cheaptickets' => [
            'CheapTickets',
        ],
        'lastminute' => [
            'lastminute',
        ],
        'alaskaair' => [
            'Alaska Air',
        ],
        'chase' => [
            'Chase Travel',
        ],
        'thomascook' => [
            'Thomas Cook',
        ],
        'mrjet' => [
            'MrJet',
        ],
        'riu' => [
            'Vacations by Riu',
        ],
        'marriott' => [
            'Vacations by Marriott',
        ],
        'hawaiian' => [
            'with Hawaiian Airlines',
        ],
        'rbcbank' => [
            'on RBCRewards.com',
            'travel.rbcrewards.com',
            'Avion Rewards Travel',
        ],
        'expedia' => [ // the last
            'Expedia', 'expedia.', 'expediataap.com',
        ],
    ];
    private $rentalProviders = [
        'jumbo' => [
            'Jumbo Car',
        ],
        'rentacar' => [
            'Enterprise',
        ],
        'dollar' => [
            'Dollar',
        ],
        'hertz' => [
            'Hertz',
        ],
        'sixt' => [
            'Sixt',
        ],
        'alamo' => [
            'Alamo',
        ],
        'perfectdrive' => [
            'Budget',
        ],
        'payless' => [
            'Payless',
        ],
        'thrifty' => [
            'Thrifty Car Rental',
        ],
    ];
    private $detectLang = [
        'expedia' => [
            "es" => [
                "Gracias por reservar con Expedia!",
                "Gracias por realizar tu reserva con Expedia!",
                "Este itinerario de Expedia se ha enviado desde",
                "Este itinerario de Expedia fue enviado por",
                "de la reserva de Expedia.es",
                "Hemos modificado tu reservación",
            ],
            "nl" => [
                "Bedankt voor je boeking bij Expedia!",
                "Dit Expedia-reisplan werd verstuurd door",
                'voor je boeking bij Expedia',
                'Hoewel Expedia geen kosten berekent als je de boeking wilt wijzigen of annuleren',
            ],
            "pt" => [
                "Obrigado por reservar com a Expedia!",
                "Este Itinerário da Expedia foi enviado por",
                'Sua reserva foi realizada. Não é necessário ligar para confirmar a reserva novamente',
                'Fizemos alterações em sua reserva, conforme solicitado.Seu itinerário atualizado está a seguir',
                'Sua reserva foi confirmada pela propriedade.',
            ],
            "en" => [
                "for booking with Expedia",
                "All rights reserved. Expedia",
                "This flight is not booked. Book now to guarantee price and availability.",
                'Your activity is booked. Please check your voucher for details',
                'See live updates to your itinerary, anywhere and anytime',
                "This Expedia Itinerary was sent from",
                "Thank you for choosing Expedia for your travel reservations",
                "Expedia travel confirmation",
                "Although Expedia",
                "Expedia.co.th",
                'Thank you for booking with Expedia',
                'Your reservation is booked. No need to call us to reconfirm this reservation.',
                'Your reservation is booked and confirmed. There is no need to call us to reconfirm this reservation',
                'We have modified your booking, according to your request. Your updated itinerary is outlined below',
                'this transactional email based on a recent booking or account-related update on Expedia',
                'Expedia.com.au',
            ],
            "de" => [
                "Ihre Buchung über Expedia!",
                "Ihre Buchung war erfolgreich",
                "Greifen Sie von unterwegs auf Ihren Reiseplan zu!",
                "Vielen Dank für Ihre Buchung über Expedia! Ihre Buchung ist bestätigt.",
                "Expedia-Reisebestätigung",
            ],
            "no" => [
                "Takk for at du bestilte med Expedia!",
                "Denne Expedia-reiseruten ble sendt fra",
                "Takk for at du bestiller med Expedia",
            ],
            "fr" => [
                "votre voyage avec Expedia",
                "des marques déposées d’Expedia",
                "Ce voyage Expedia a été envoyé par",
                "Merci d'avoir réservé votre voyage avec l'Agence voyages-sncf.com",
                "Référence de réservation Expedia.fr",
                "Merci d’avoir réservé votre voyage avec l'Agence voyages-sncf.com",
                "Nous vous remercions d’avoir réservé avec",
            ],
            "sv" => [
                "Tack för att du bokade med Expedia!",
            ],
            "it" => [
                "Tutti i diritti riservati. Expedia",
                "diritti riservati. Expedia",
                "Grazie per aver scelto di prenotare con Expedia",
            ],
            "da" => [
                "Tak, fordi du har reserveret hos Expedia!",
                "Tak, fordi du reserverer hos Expedia",
            ],
            "ko" => [
                "익스피디아에서 예약해 주셔서 감사합니다!",
            ],
            "ja" => [
                "エクスペディア でご予約いただき、ありがとうございます",
                "ホテルの部屋がキャンセルされました",
                "エクスペディアでご予約いただき、ありがとうございます",
            ],
            "az" => [
            ],
            "zh" => [
                "感謝您使用 Expedia 智遊網 預訂行程",
                '多謝您選用 Expedia 智遊網 預訂！正在處理機票',
                '感谢您通过 Expedia 智游网 进行预订',
            ],
            "fi" => [
                "Tämän Expedia-matkasuunnitelman lähetti sinulle",
                "Expedia kiittää varauksestasi!",
            ],
        ],
        'travelocity' => [
            "es" => [
                "Gracias por reservar con Travelocity!",
                "Gracias por realizar tu reserva con Travelocity!",
            ],
            "nl" => [
                "Bedankt voor je boeking bij Travelocity!",
                "Dit Travelocity-reisplan werd verstuurd door",
            ],
            "pt" => [
                "Obrigado por reservar com a Travelocity!",
                "Este Itinerário da Travelocity foi enviado por",
            ],
            "en" => [
                "Thank you for booking with Travelocity!",
                "All rights reserved. Travelocity",
            ],
            "de" => [
                "Ihre Buchung über Travelocity!",
                "Greifen Sie von unterwegs auf Ihren Reiseplan zu!",
                "Vielen Dank für Ihre Buchung über Travelocity! Ihre Buchung ist bestätigt.",
            ],
            "no" => [
                "Takk for at du bestilte med Travelocity!",
            ],
            "fr" => [
                "Merci d’avoir réservé votre voyage avec Travelocity",
                "des marques déposées d’Travelocity",
            ],
            "sv" => [
                "Tack för att du bokade med Travelocity!",
            ],
            "it" => [
                "Tutti i diritti riservati. Travelocity",
            ],
            "da" => [
                "Tak, fordi du har reserveret hos Travelocity!",
            ],
        ],
        'orbitz' => [
            "es" => [
                "Gracias por reservar con Orbitz!",
                "Gracias por realizar tu reserva con Orbitz!",
            ],
            "nl" => [
                "Bedankt voor je boeking bij Orbitz!",
                "Dit Orbitz-reisplan werd verstuurd door",
            ],
            "pt" => [
                "Obrigado por reservar com a Orbitz!",
                "Este Itinerário da Orbitz foi enviado por",
            ],
            "en" => [
                "Thank you for booking with Orbitz!",
                "All rights reserved. Orbitz",
            ],
            "de" => [
                "Ihre Buchung über Orbitz!",
                "Greifen Sie von unterwegs auf Ihren Reiseplan zu!",
                "Vielen Dank für Ihre Buchung über Orbitz! Ihre Buchung ist bestätigt.",
            ],
            "no" => [
                "Takk for at du bestilte med Orbitz!",
            ],
            "fr" => [
                "Merci d’avoir réservé votre voyage avec Orbitz",
                "des marques déposées d’Orbitz",
            ],
            "sv" => [
                "Tack för att du bokade med Orbitz!",
            ],
            "it" => [
                "Tutti i diritti riservati. Orbitz",
            ],
            "da" => [
                "Tak, fordi du har reserveret hos Orbitz!",
            ],
            "ko" => [
                "익스피디아에서 예약해 주셔서 감사합니다!",
            ],
        ],
        'hotwire' => [
            "es" => [
                "Gracias por reservar con Hotwire!",
                "Gracias por realizar tu reserva con Hotwire!",
            ],
            "nl" => [
                "Bedankt voor je boeking bij Hotwire!",
                "Dit Hotwire-reisplan werd verstuurd door",
            ],
            "pt" => [
                "Obrigado por reservar com a Hotwire!",
            ],
            "en" => [
                "Thank you for booking with Hotwire!",
                "All rights reserved. Hotwire",
                "This Hotwire Itinerary was sent from",
            ],
            "de" => [
                "Ihre Buchung über Hotwire!",
                "Greifen Sie von unterwegs auf Ihren Reiseplan zu!",
                "Vielen Dank für Ihre Buchung über Hotwire! Ihre Buchung ist bestätigt.",
            ],
            "no" => [
                "Takk for at du bestilte med Hotwire!",
            ],
            "fr" => [
                "Merci d’avoir réservé votre voyage avec Hotwire",
                "des marques déposées d’Hotwire",
            ],
            "sv" => [
                "Tack för att du bokade med Hotwire!",
            ],
            "it" => [
                "Tutti i diritti riservati. Hotwire",
            ],
            "da" => [
                "Tak, fordi du har reserveret hos Hotwire!",
            ],
            "ko" => [
                "익스피디아에서 예약해 주셔서 감사합니다!",
            ],
        ],
        'hotels' => [
            "en" => [
                "Thank you for booking with Hotels.com!",
                "This Hotels.com Itinerary was sent",
            ],
            "pt" => [
                "Obrigado por reservar com Hoteis.com!",
            ],
        ],
        'hawaiian' => [
            "en" => [
                "Thank you for booking with Hawaiian Airlines!",
            ],
        ],
        'cheaptickets' => [
            "en" => [
                "Thank you for booking with CheapTickets!",
                "This CheapTickets Itinerary was sent",
                "Call CheapTickets customer care",
            ],
        ],
        'ebookers' => [
            "fr" => [
                "Ce voyage ebookers a été envoyé",
            ],
            "de" => [
                "Absender dieses ebookers",
                "ebookers-Reisebestätigung",
            ],
            "en" => [
                "This ebookers Itinerary was sent",
                "Thank you for booking with ebookers",
            ],
        ],
        'lastminute' => [
            "en" => [
                "Thank you for booking with lastminute",
            ],
        ],
        'alaskaair' => [
            "en" => [
                "Thank you for booking with Alaska Air Trips",
            ],
        ],
        'chase' => [
            "en" => [
                "We have modified your booking, according to your request",
            ],
        ],
        'thomascook' => [
            "fr" => [
                "Merci d’avoir réservé votre voyage avec Thomas Cook",
            ],
        ],
        'mrjet' => [
            "sv" => [
                "Den här MrJet-resplanen skickades från",
                "Tack för att du bokade med MrJet",
            ],
        ],
        'riu' => [
            "en" => [
                "Thank you for booking with Vacations by Riu",
            ],
        ],
        'marriott' => [
            "en" => [
                "Thank you for booking with Vacations by Marriott",
            ],
        ],
        'rbcbank' => [
            "en" => [
                "Royal Bank of Canada Website",
            ],
        ],
    ];
    private static $headers = [//expedia should be last
        'travelocity' => [
            'from' => ['travelocity'],
            'subj' => [
                "es" => [
                    "Confirmación de viaje de Travelocity",
                ],
                "nl" => [
                    "Reisbevestiging van Travelocity",
                ],
                "pt" => [
                    "Confirmação de viagem da Travelocity",
                ],
                "en" => [
                    "Travelocity travel confirmation",
                ],
                "de" => [
                    "Travelocity-Reisebestätigung",
                ],
                "no" => [
                    "Travelocity-reisebekreftelse",
                ],
                "fr" => [
                    "Votre confirmation de voyage Travelocity",
                ],
                "sv" => [
                    "Resebekräftelse från Travelocity",
                ],
                "it" => [
                    "Travelocity",
                ],
                "da" => [
                    "Travelocity",
                ],
            ],
        ],
        'chase' => [
            'from' => ['chasetravelbyexpedia@link.expediamail.com'],
            'subj' => [
                "en" => [
                    "Updated Itinerary",
                    "Travel Confirmation",
                ],
            ],
        ],
        'orbitz' => [
            'from' => ['orbitz'],
            'subj' => [
                "es" => [
                    "Confirmación de viaje de Orbitz",
                ],
                "nl" => [
                    "Reisbevestiging van Orbitz",
                ],
                "pt" => [
                    "Confirmação de viagem da Orbitz",
                ],
                "en" => [
                    "Orbitz travel confirmation",
                ],
                "de" => [
                    "Orbitz-Reisebestätigung",
                ],
                "no" => [
                    "Orbitz-reisebekreftelse",
                ],
                "fr" => [
                    "Votre confirmation de voyage Orbitz",
                ],
                "sv" => [
                    "Resebekräftelse från Orbitz",
                ],
                "it" => [
                    "Orbitz",
                ],
                "da" => [
                    "Orbitz",
                ],
                "ko" => [
                    "NOTTRANSLATED",
                ],
            ],
        ],
        'hotwire' => [
            'from' => ['hotwire.com'],
            'subj' => [
                "es" => [
                    "Confirmación de viaje de Hotwire",
                ],
                "nl" => [
                    "Reisbevestiging van Hotwire",
                ],
                "pt" => [
                    "Confirmação de viagem da Hotwire",
                ],
                "en" => [
                    "Hotwire travel confirmation",
                ],
                "de" => [
                    "Hotwire-Reisebestätigung",
                ],
                "no" => [
                    "Hotwire-reisebekreftelse",
                ],
                "fr" => [
                    "Votre confirmation de voyage Hotwire",
                ],
                "sv" => [
                    "Resebekräftelse från Hotwire",
                ],
                "it" => [
                    "Hotwire",
                ],
                "da" => [
                    "Hotwire",
                ],
                "ko" => [
                    "익스피디아 여행 확인",
                ],
            ],
        ],
        'hotels' => [
            'from' => ['@support-hotels.com'],
            'subj' => [
                'en' => [
                    'Hotels.com travel confirmation',
                ],
            ],
        ],
        'cheaptickets' => [
            'from' => ['@mailer.cheaptickets.com'],
            'subj' => [
                'en' => [
                    '(Itinerary#',
                ],
            ],
        ],
        'ebookers' => [
            'from' => ['ebookers.'],
            'subj' => [
                'en' => [
                    'Itinerary#',
                ],
                'de' => [
                    'ebookers-Reisebestätigung',
                ],
                'fr' => [
                    'Billet électronique',
                    'Reiseplannummer',
                ],
            ],
        ],
        'lastminute' => [
            'from' => ['@email.lastminute.com'],
            'subj' => [
                'en' => [
                    'Itin#',
                ],
            ],
        ],
        'alaskaair' => [
            'from' => ['@notify.alaskatrips.poweredbygps.com'],
            'subj' => [
                'en' => [
                    'Alaska Air Trips travel confirmation',
                ],
            ],
        ],
        'thomascook' => [
            'from' => ['@Notify.ThomasCook.com'],
            'subj' => [
                'fr' => [
                    'Votre confirmation de voyage Thomas Cook',
                ],
            ],
        ],
        'mrjet' => [
            'from' => ['@Notify.ThomasCook.com'],
            'subj' => [
                'sv' => [
                    'E-biljett',
                    'Resplansnummer',
                    'Resebekräftelse från MrJet/e-biljett',
                ],
            ],
        ],
        'riu' => [
            'from' => ['@support@notify.riuvacations.poweredbygps.com'],
            'subj' => [
                'en' => [
                    'Vacations by Riu travel confirmation',
                ],
            ],
        ],
        'marriott' => [
            'from' => ['support@notify.vacationsbymarriott.poweredbygps.com'],
            'subj' => [
                'en' => [
                    'Vacations by Marriott travel confirmation',
                ],
            ],
        ],
        'rbcbank' => [
            'from' => ['RBCRewardsTravel@rbcrewards.com'],
            'subj' => [
                'en' => [
                    'RBC Travel travel confirmation',
                ],
            ],
        ],
        'expedia' => [ // always last!
            'from' => ['expedia'],
            'subj' => [
                "es" => [
                    "Confirmación de viaje de Expedia",
                ],
                "nl" => [
                    "Reisbevestiging van Expedia",
                ],
                "pt" => [
                    "Confirmação de viagem da Expedia",
                ],
                "en" => [
                    "Expedia travel confirmation",
                ],
                "de" => [
                    "Expedia-Reisebestätigung",
                ],
                "no" => [
                    "Expedia-reisebekreftelse",
                ],
                "fr" => [
                    "Votre confirmation de voyage Expedia",
                    "Confirmation de voyage Agence voyages-sncf.com",
                ],
                "sv" => [
                    "Resebekräftelse från Expedia",
                ],
                "ko" => [
                    "익스피디아 여행 확인",
                ],
                "ja" => [
                    "エクスペディアの旅行確認通知",
                ],
                "az" => [
                    "Expedia 智遊網 行程確認",
                ],
                "zh" => [
                    "Expedia 智遊網 行程確認",
                ],
                "it" => [
                    "Conferma di viaggio Expedia/Biglietto elettronico",
                    "Expedia",
                ],
                "da" => [
                    "Expedia",
                ],
                "fi" => [
                    "Expedia",
                ],
            ],
        ],
    ];
    private static $dictionary = [
        "es" => [
            //Flight
            "Duration"   => "Tiempo total de viaje",
            "Passengers" => ["Información del pasajero", "Información del viajero"],
            "Stop"       => "Escala:",
            "Tickets"    => ["No. de boleto", "Número de billete"],
            "Flight"     => "Vuelo",
            //            "Operated by" => "",
            "Taxes & Fees" => ["impuestos y cargos", "Tasas e impuestos de la línea aérea"],
            "Terminal"     => ["Terminal"],
            //Hotels
            "Details"            => ["Ver detalles del hotel", "Ver los detalles del hotel"],
            "Itinerary"          => ["Itinerario", "de itinerario"],
            "Phone"              => "(?:Tel\.?|Teléfono)",
            "Fax"                => "Fax",
            "Reserved"           => ["Para", "Reservado para"],
            "Guests"             => "adult",
            "Kids"               => "kids",
            "CancellationPolicy" => ["importante sobre el hotel", "importante del hotel"],
            "Cancel"             => "CANCEL",
            "Cancelled"          => "NOTTRANSLATED",
            "Room"               => "Habitaci",
            "Rooms"              => "\b(\d{1,3})\s+habitación.{0,2}\s*\|",
            "RoomType"           => "Peticiones especiales",
            "Check-in time"      => "El check-in comienza a las",
            "Check-out time"     => "NOTTRANSLATED",
            //Car
            "Pickup"       => "NOTTRANSLATED",
            "Dropoff"      => "NOTTRANSLATED",
            "carCancelled" => "NOTTRANSLATED",
            //Other
            "Points"                          => "(?:Puntos\s+de\s+|)\d+(?:\s+puntos|)",
            "Total"                           => ["Total:", "Precio de la habitación", "Total"],
            "Currency"                        => "NOTTRANSLATED",
            "Status"                          => ["CONFIRMADA", "SE HA RESERVADO", "EMISIÓN DE BOLETOS EN CURSO"],
            "Confirmed"                       => ["CONFIRMADA", "SE HA RESERVADO"],
            "Need help with your reservation" => "Necesitas ayuda con tu reservación",
            "Call us at"                      => "Llámanos al",
            //Transfer
            "Transfer"                  => "NOTTRANSLATED",
            "Reserved for"              => "NOTTRANSLATED",
            "Supplier Reference Number" => "NOTTRANSLATED",
            "Arrival Date / Time"       => "NOTTRANSLATED",
            "Departure Date / Time"     => "NOTTRANSLATED",
        ],
        "nl" => [
            //Flight
            "Duration"   => "Totale reistijd",
            "Passengers" => "Reizigersinformatie",
            "Stop"       => "Reisonderbreking:",
            "Tickets"    => ["Ticketnummer"],
            "Flight"     => "Vlucht",
            //            "Operated by" => "",
            "Taxes & Fees" => "Belastingen & toeslagen airline",
            "Terminal"     => ["Terminal"],
            //Hotels
            "Details"            => "Hotelinformatie weergeven",
            "Itinerary"          => ["Reisplan", "Bevestigingsnr.", 'Reisplannummer'],
            "Phone"              => "Tel\.",
            "Fax"                => "Fax",
            "Reserved"           => "Geboekt voor",
            "Guests"             => "volwassene",
            "Kids"               => "kind",
            "CancellationPolicy" => "Belangrijke hotelinformatie",
            "Cancel"             => "annul",
            "Cancelled"          => "NOTTRANSLATED",
            "Room"               => "Kamer",
            "Rooms"              => "\b(\d{1,3})\s+kamer.{0,1}\s*\|",
            "RoomType"           => "Inclusief:",
            "Check-in time"      => "Inchecken vanaf",
            "Check-out time"     => "NOTTRANSLATED",
            //Car
            "Pickup"       => "NOTTRANSLATED",
            "Dropoff"      => "NOTTRANSLATED",
            "carCancelled" => "NOTTRANSLATED",
            //Other
            "Points"                          => "\d+\s+punten",
            "Total"                           => ["Totaalprijs", "Totaal"],
            "Currency"                        => "NOTTRANSLATED",
            "Status"                          => ["BEVESTIGD", "GEBOEKT"],
            "Confirmed"                       => "GEBOEKT",
            "Need help with your reservation" => "Hulp nodig bij je boeking",
            "Call us at"                      => "Bel ons op",
            //Transfer
            "Transfer"                  => "NOTTRANSLATED",
            "Reserved for"              => "NOTTRANSLATED",
            "Supplier Reference Number" => "NOTTRANSLATED",
            "Arrival Date / Time"       => "NOTTRANSLATED",
            "Departure Date / Time"     => "NOTTRANSLATED",
        ],
        "pt" => [
            //Flight
            "Duration"   => "Tempo de viagem",
            "Passengers" => "Informações do viajante",
            "Stop"       => "NOTTRANSLATED",
            "Tickets"    => "Nº da passagem",
            "Flight"     => "Voo",
            //            "Operated by" => "",
            "Taxes & Fees" => ["Impostos e Taxas da companhia aérea", "Impostos e taxas"],
            "Terminal"     => ["Terminal"],
            //Hotels
            "Details"            => "Ver detalhes do hotel",
            "Itinerary"          => "Itinerário",
            "Phone"              => "Tel:",
            "Fax"                => "Fax:",
            "Reserved"           => "Reservado para",
            "Guests"             => "adult",
            "Kids"               => "NOTTRANSLATED",
            "CancellationPolicy" => "Informações importantes sobre o hotel",
            "Cancel"             => "CANCELAR",
            "Cancelled"          => "CANCELADO",
            "Room"               => "Quarto",
            "Rooms"              => "\b(\d{1,3})\s+quarto.{0,2}\s*\|",
            "RoomType"           => "NOTTRANSLATED",
            "Check-in time"      => ["Horário de início do check-in", 'Horário inicial do check-in'],
            //            'Check-out time'     => '',
            //Car
            "Pickup"       => "NOTTRANSLATED",
            "Dropoff"      => "NOTTRANSLATED",
            "carCancelled" => "NOTTRANSLATED",
            //Other
            "Points"                          => "\d+\s+pontos",
            "Total"                           => ["Total"],
            "Currency"                        => "NOTTRANSLATED",
            "Status"                          => ["CONFIRMADO", "RESERVADA", "CONFIRMADA", "CONFIRMED"],
            "Confirmed"                       => "CONFIRMADO",
            "Need help with your reservation" => "Precisa de ajuda com sua reserva",
            "Call us at"                      => "Ligue para nós através do número",
            //Transfer
            "Transfer"                  => "NOTTRANSLATED",
            "Reserved for"              => "NOTTRANSLATED",
            "Supplier Reference Number" => "NOTTRANSLATED",
            "Arrival Date / Time"       => "NOTTRANSLATED",
            "Departure Date / Time"     => "NOTTRANSLATED",
        ],
        "en" => [
            //Flight
            "Duration"   => "Total travel time",
            "Passengers" => ["Traveler Information", "Traveller Information", "Passenger Information"],
            "Stop"       => "Layover:",
            "Tickets"    => ["Ticket #", "Ticket No."],
            "Flight"     => "Flight",
            //            "Operated by" => "",
            "Taxes & Fees" => ["Taxes & Fees", "Taxes & Airline Fees", "taxes and fees"],
            "Terminal"     => ["Terminal"],
            //Hotels
            "Details"            => ["View hotel details", "Checking in", "All rooms in this reservation have been cancelled", "Check-in time starts at "],
            "Itinerary"          => ["Itinerary", "Confirmation"],
            "Phone"              => "Tel",
            "Fax"                => "Fax",
            "Reserved"           => "Reserved for",
            "Guests"             => "adult",
            "Kids"               => ["child", "infant"],
            "CancellationPolicy" => "Important Hotel Information",
            "Cancel"             => "CANCEL",
            "Cancelled"          => ["CANCELLED", "Cancelled"],
            "Room"               => "Room",
            "Rooms"              => "\b(\d{1,3})\s+rooms?\s*\|",
            "RoomType"           => "Includes:",
            "Check-in time"      => "Check-in time starts at",
            "Check-out time"     => "NOTTRANSLATED",
            //Car
            "Pickup"       => ["Pick up", 'Pick-up'],
            "Dropoff"      => ["Drop off", 'Drop-off'],
            "carCancelled" => ["reservation has been cancelled", "reservation has been fully cancelled"],

            //Other
            "Points"    => "(?:\d+\s+points|\\$[\d\.]+\s+in\s+CheapCash)",
            "Total"     => ["Total Price", "Total:", "Room Price", "Total"],
            "Currency"  => "All\s+prices\s+quoted\s+in\s+(.*?)\.",
            "Status"    => ["Confirmed", "CONFIRMED", "BOOKED", "Booked", "NOT BOOKED", "TICKETING IN PROGRESS", "CANCELLED", "Cancelled"],
            "Confirmed" => ["Confirmed", "CONFIRMED", "Booked", "BOOKED"],
            //            "Need help with your reservation" => "",
            "Call us at" => [
                "Call us at",
                "Call Travelocity customer care at",
                "Or call Hotwire at",
                "Call CheapTickets customer care at",
                "page or call a Chase Travel Center Specialist at",
            ],
            //Transfer
            "Transfer"                  => ["Shared Transfers", "Hotel Pickup"],
            "Reserved for"              => "Reserved for",
            "Supplier Reference Number" => "Supplier Reference Number",
            "Arrival Date / Time"       => "Arrival Date / Time",
            "Departure Date / Time"     => "Departure Date / Time",
        ],
        "de" => [
            //Flight
            "Duration"   => "Gesamte Reisezeit",
            "Passengers" => "Angaben zum Reisenden",
            "Stop"       => "Aufenthalt:",
            "Tickets"    => ["Ticketnr."],
            "Flight"     => "Flug",
            //            "Operated by" => "",
            "Taxes & Fees" => "Steuern & Fluggebühren",
            "Terminal"     => ["Terminal"],
            //Hotels
            "Details"            => "Hoteldetails anzeigen",
            "Itinerary"          => ["Bestätigungsnummer", "Reiseplan", 'Reiseplannummer'],
            "Phone"              => "Tel\.?",
            "Fax"                => "Fax",
            "Reserved"           => "Reserviert für",
            "Guests"             => "Erwachsene",
            "Kids"               => ["Kinder", "Kind"],
            "CancellationPolicy" => "Wichtige Hotelinformationen",
            "Cancel"             => "STORNIERUNGEN",
            "Cancel2"            => "storniert",
            "Cancelled"          => "NOTTRANSLATED",
            "Room"               => "Zimmer",
            "Rooms"              => "\b(\d{1,3})\s*Zimmer\s*\|",
            "RoomType"           => "Inbegriffen:",
            "Check-in time"      => "Check-in ab",
            'Check-out time'     => 'Der Check-in endet um',
            //Car
            "Pickup"       => "Abholung",
            "Dropoff"      => "Rückgabe",
            "carCancelled" => "NOTTRANSLATED",
            //Other
            "Points"                          => "\d+\s+",
            "Total"                           => ["Gesamt", "Gesamtpreis", "Zimmerpreis"],
            "Currency"                        => "Alle Preise werden in (.*?) angezeigt",
            "Status"                          => ["GEBUCHT", "BESTÄTIGT", "Confirmed", "Bestätigt", 'TICKET WIRD AUSGESTELLT'],
            "Confirmed"                       => ["Confirmed", "Bestätigt"],
            "Need help with your reservation" => "Haben Sie Fragen zur Buchung",
            "Call us at"                      => "Sie erreichen uns unter der Nummer",
            //Transfer
            "Transfer"                  => "NOTTRANSLATED",
            "Reserved for"              => "NOTTRANSLATED",
            "Supplier Reference Number" => "NOTTRANSLATED",
            "Arrival Date / Time"       => "NOTTRANSLATED",
            "Departure Date / Time"     => "NOTTRANSLATED",
        ],
        "no" => [
            //Flight
            "Duration"   => "Total reisetid",
            "Passengers" => "Informasjon om reisende",
            "Stop"       => "Mellomlanding",
            "Tickets"    => "Billettnr.",
            "Flight"     => "NOTTRANSLATED",
            //            "Operated by" => "",
            "Taxes & Fees" => "NOTTRANSLATED",
            "Terminal"     => ["Terminal"],
            //Hotels
            "Details"            => "Vis hotellinformasjon",
            "Itinerary"          => ["Reiserute", 'Bekreftelsesnr.', 'Reiserutenr.'],
            "Phone"              => "Tlf",
            "Fax"                => "Faks",
            "Reserved"           => "Reservert for",
            "Guests"             => "voks",
            "Kids"               => "NOTTRANSLATED",
            "CancellationPolicy" => "Viktig hotellinformasjon",
            "Cancel"             => "AVBESTILL",
            "Cancelled"          => "NOTTRANSLATED",
            "Room"               => "Rom",
            "Rooms"              => "\b(\d{1,3})\s*rom.{1,2}\s*\|",
            "RoomType"           => "Forespørsler",
            "Check-in time"      => "Innsjekking slutter kl.",
            "Check-out time"     => "NOTTRANSLATED",
            //Car
            "Pickup"       => "NOTTRANSLATED",
            "Dropoff"      => "NOTTRANSLATED",
            "carCancelled" => "NOTTRANSLATED",
            //Other
            "Points"    => "\d+\s+poeng",
            "Total"     => ["Rompris", "Sum:"],
            "Currency"  => "Alle priser er oppgitt i\s+(.*?)\.",
            "Status"    => ["BEKREFTET", "BESTILT"],
            "Confirmed" => "NOTTRANSLATED",
            //            "Need help with your reservation" => "",
            //            "Call us at" => "",
            //Transfer
            "Transfer"                  => "NOTTRANSLATED",
            "Reserved for"              => "NOTTRANSLATED",
            "Supplier Reference Number" => "NOTTRANSLATED",
            "Arrival Date / Time"       => "NOTTRANSLATED",
            "Departure Date / Time"     => "NOTTRANSLATED",
        ],
        "fr" => [
            //Flight
            "Duration"   => "Durée totale",
            "Passengers" => ["Informations sur le voyageur", "Information voyageur"],
            "Stop"       => "Attente:",
            "Tickets"    => ["Billet n°", "Nº de billet"],
            "Flight"     => "Vol",
            //            "Operated by" => "",
            "Taxes & Fees" => ["Taxes et frais", "Taxes et frais de compagnie aérienne", "Taxes"],
            "Terminal"     => ["Terminal", "Aérogare"],
            //Hotels
            "Details"            => ["Voir les détails de l’hôtel", "Afficher les détails de l"],
            "Itinerary"          => ["Itinéraire", "Voyage", "N° de voyage", 'N° de confirmation'],
            "Phone"              => "Tél\.",
            "Fax"                => ["Téléc.", "Fax "],
            "Reserved"           => ["Réservé pour", "Réservée pour", "Réservation pour"],
            "Guests"             => "adult",
            "Kids"               => "NOTTRANSLATED",
            "CancellationPolicy" => ["Renseignements importants sur l’hôtel", "Informations importantes sur l’hôtel"],
            "Cancel"             => "ANNUL",
            "Cancelled"          => "NOTTRANSLATED",
            "Room"               => "Chambre",
            "Rooms"              => "\b(\d{1,3})\s+chambres?\s+\|",
            "RoomType"           => "Inclut",
            "Check-in time"      => "Arrivées à partir de",
            "Check-out time"     => "NOTTRANSLATED",
            //Car
            "Pickup"       => "Prise en charge",
            "Dropoff"      => "Restitution",
            "carCancelled" => "NOTTRANSLATED",
            //Other
            "Points"                          => "\d+\s+points",
            "Total"                           => ["Total", "Prix total"],
            "Currency"                        => "NOTTRANSLATED",
            "Status"                          => ["CONFIRMÉ", "RÉSERVÉ", 'Confirmé'],
            "Confirmed"                       => "NOTTRANSLATED",
            "Need help with your reservation" => "Besoin d’aide pour votre réservation",
            "Call us at"                      => ["Call us at", "Nos conseillers sont à votre disposition au"],
            //Transfer
            "Transfer"                  => "NOTTRANSLATED",
            "Reserved for"              => "NOTTRANSLATED",
            "Supplier Reference Number" => "NOTTRANSLATED",
            "Arrival Date / Time"       => "NOTTRANSLATED",
            "Departure Date / Time"     => "NOTTRANSLATED",
        ],
        "sv" => [
            //Flight
            "Duration"   => "Total restid",
            "Passengers" => "Resenärsinfo",
            "Stop"       => "NOTTRANSLATED",
            "Tickets"    => ["Biljettnr"],
            "Flight"     => "Flyg",
            //            "Operated by" => "",
            "Taxes & Fees" => "Skatter och flygbolagsavgifter",
            "Terminal"     => ["Terminal"],
            //Hotels
            "Details"            => "Se hotellinformation",
            "Itinerary"          => ["Bekräftelsenr", "Resplan", "Resplansnummer"],
            "Phone"              => "Tel:",
            "Fax"                => "Fax:",
            "Reserved"           => "Bokat för",
            "Guests"             => "vuxen",
            "Kids"               => "NOTTRANSLATED",
            "CancellationPolicy" => "Viktig hotellinformation",
            "Cancel"             => "AVBOKAR",
            "Cancelled"          => "NOTTRANSLATED",
            "Room"               => "Rum",
            "Rooms"              => "\b(\d{1,3})\s+rum.{0,2}\s*\|",
            "RoomType"           => "Inkluderar:",
            "Check-in time"      => ["Incheckningstiden börjar kl", "Incheckningstiden börjar"],
            "Check-out time"     => "NOTTRANSLATED",
            //Car
            "Pickup"       => "NOTTRANSLATED",
            "Dropoff"      => "NOTTRANSLATED",
            "carCancelled" => "NOTTRANSLATED",
            //Other
            "Points"                          => "NOTTRANSLATED",
            "Total"                           => ["Totalpris", "Summa", "Totalt"],
            "Currency"                        => "Alla priser anges i\s+(.*?)\.",
            "Status"                          => ["BEKRÄFTAT", "BOKAT"],
            "Confirmed"                       => "BEKRÄFTAT",
            "Need help with your reservation" => "Vill du ha hjälp med din bokning",
            "Call us at"                      => "Ring oss på",
            //Transfer
            "Transfer"                  => "NOTTRANSLATED",
            "Reserved for"              => "NOTTRANSLATED",
            "Supplier Reference Number" => "NOTTRANSLATED",
            "Arrival Date / Time"       => "NOTTRANSLATED",
            "Departure Date / Time"     => "NOTTRANSLATED",
        ],
        "it" => [
            //Flight
            "Duration"     => "Durata totale del viaggio",
            "Passengers"   => "Informazioni sul viaggiatore",
            "Stop"         => "Sosta:",
            "Tickets"      => ["Nº da passagem", "N° biglietto"],
            "Flight"       => "Volo",
            "Operated by"  => "Operato da",
            "Taxes & Fees" => "Tasse e tariffe della compagnia aerea",
            "Terminal"     => ["Terminal"],
            //Hotels
            "Details"            => "Mostra dettagli hotel",
            "Itinerary"          => "Itinerario",
            "Phone"              => "Tel",
            "Fax"                => "Numero di fax",
            "Reserved"           => "Prenotazione per",
            "Guests"             => "adult",
            "Kids"               => "NOTTRANSLATED",
            "CancellationPolicy" => "Informazioni importanti sull’hotel",
            "Cancel"             => "cancellazione",
            "Cancelled"          => "NOTTRANSLATED",
            "Room"               => "Camera",
            "Rooms"              => "\b(\d{1,3})\s+cameras?\s*\|",
            "RoomType"           => "Richieste",
            "Check-in time"      => "Il check-in inizia alle ore",
            "Check-out time"     => "NOTTRANSLATED",
            //Car
            "Pickup"       => "NOTTRANSLATED",
            "Dropoff"      => "NOTTRANSLATED",
            "carCancelled" => "NOTTRANSLATED",
            //Other
            "Points"                          => "NOTTRANSLATED",
            "Total"                           => "Totale",
            "Currency"                        => "NOTTRANSLATED",
            "Status"                          => ["CONFERMATA", "PRENOTAZIONE CONFERMATA"],
            "Confirmed"                       => "NOTTRANSLATED",
            "Need help with your reservation" => "Hai bisogno di aiuto con la prenotazione",
            "Call us at"                      => "Chiamaci al numero",
            //Transfer
            "Transfer"                  => "NOTTRANSLATED",
            "Reserved for"              => "NOTTRANSLATED",
            "Supplier Reference Number" => "NOTTRANSLATED",
            "Arrival Date / Time"       => "NOTTRANSLATED",
            "Departure Date / Time"     => "NOTTRANSLATED",
        ],
        "da" => [
            //Flight
            "Duration"   => "Samlet rejsetid",
            "Passengers" => "Oplysninger om rejsende",
            "Stop"       => "NOTTRANSLATED",
            "Tickets"    => ["Billetnr."],
            "Flight"     => "Flyrejse",
            //            "Operated by" => "",
            "Taxes & Fees" => "Skatter og flyselskabsgebyrer",
            "Terminal"     => ["Terminal"],
            //Hotels
            "Details"            => "NOTTRANSLATED",
            "Itinerary"          => "Rejseplan",
            "Phone"              => "NOTTRANSLATED",
            "Fax"                => "NOTTRANSLATED",
            "Reserved"           => "NOTTRANSLATED",
            "Guests"             => "NOTTRANSLATED",
            "Kids"               => "NOTTRANSLATED",
            "CancellationPolicy" => "NOTTRANSLATED",
            "Cancel"             => "NOTTRANSLATED",
            "Cancelled"          => "NOTTRANSLATED",
            "Room"               => "NOTTRANSLATED",
            "Rooms"              => "NOTTRANSLATED",
            "RoomType"           => "NOTTRANSLATED",
            "Check-in time"      => "NOTTRANSLATED",
            "Check-out time"     => "NOTTRANSLATED",
            //Car
            "Pickup"       => "NOTTRANSLATED",
            "Dropoff"      => "NOTTRANSLATED",
            "carCancelled" => "NOTTRANSLATED",
            //Other
            "Points"                          => "NOTTRANSLATED",
            "Total"                           => "I alt:",
            "Currency"                        => "NOTTRANSLATED",
            "Status"                          => "BEKRÆFTET",
            "Confirmed"                       => "NOTTRANSLATED",
            "Need help with your reservation" => "Har du brug for hjælp med din reservation",
            "Call us at"                      => "Ring til os på",
            //Transfer
            "Transfer"                  => "NOTTRANSLATED",
            "Reserved for"              => "NOTTRANSLATED",
            "Supplier Reference Number" => "NOTTRANSLATED",
            "Arrival Date / Time"       => "NOTTRANSLATED",
            "Departure Date / Time"     => "NOTTRANSLATED",
        ],
        "ko" => [
            //Flight
            "Duration"   => "NOTTRANSLATED",
            "Passengers" => "NOTTRANSLATED",
            "Stop"       => "NOTTRANSLATED",
            "Tickets"    => ["NOTTRANSLATED"],
            "Flight"     => "NOTTRANSLATED",
            //            "Operated by" => "",
            "Taxes & Fees" => "NOTTRANSLATED",
            "Terminal"     => ["Terminal"],
            //Hotels
            "Details"            => "호텔 세부 정보 보기",
            "Itinerary"          => "일정",
            "Phone"              => "전화",
            "Fax"                => "NOTTRANSLATED",
            "Reserved"           => "예약자",
            "Guests"             => "명",
            "Kids"               => "NOTTRANSLATED",
            "CancellationPolicy" => "중요 호텔 정보",
            "Cancel"             => "취소 또는",
            "Cancelled"          => "NOTTRANSLATED",
            "Room"               => "객실",
            "Rooms"              => "객실\s+(\d{1,3})개\|",
            "RoomType"           => "NOTTRANSLATED",
            "Check-in time"      => "NOTTRANSLATED",
            "Check-out time"     => "NOTTRANSLATED",
            //Car
            "Pickup"       => "NOTTRANSLATED",
            "Dropoff"      => "NOTTRANSLATED",
            "carCancelled" => "NOTTRANSLATED",
            //Other
            "Points"    => "\d+포인트",
            "Total"     => "합계",
            "Currency"  => "모든 가격은\s+(.*?)\.",
            "Status"    => "예약됨",
            "Confirmed" => "NOTTRANSLATED",
            //            "Need help with your reservation" => "",
            //            "Call us at" => "",
            //Transfer
            "Transfer"                  => "NOTTRANSLATED",
            "Reserved for"              => "NOTTRANSLATED",
            "Supplier Reference Number" => "NOTTRANSLATED",
            "Arrival Date / Time"       => "NOTTRANSLATED",
            "Departure Date / Time"     => "NOTTRANSLATED",
        ],
        "ja" => [
            //Flight
            "Duration"   => "合計所要時間",
            "Passengers" => ["旅行者情報"],
            "Stop"       => "乗り継ぎ:",
            "Tickets"    => ["航空券番号"],
            "Flight"     => "航空券",
            //            "Operated by" => "",
            "Taxes & Fees" => "税およびサービス料",
            "Terminal"     => ["Terminal", "ターミナル"],
            //Hotels
            "Details"            => "ホテルの詳細を表示する",
            "Itinerary"          => ["旅程", "旅程番号"],
            "Phone"              => "電話 :",
            "Fax"                => "FAX :",
            "Reserved"           => "ご予約者名",
            "Guests"             => "大人",
            "Kids"               => "NOTTRANSLATED",
            "CancellationPolicy" => "重要なホテル情報",
            "Cancel"             => "を過ぎてから行われたキャンセルや変更、",
            "Cancelled"          => "NOTTRANSLATED",
            "Room"               => "部屋",
            "Rooms"              => "\b(\d{1,3})\s+室?\s*\|",
            "RoomType"           => "含まれているもの :",
            "Check-in time"      => "チェックイン開始時刻",
            "Check-out time"     => "NOTTRANSLATED",
            //Car
            "Pickup"       => "NOTTRANSLATED",
            "Dropoff"      => "NOTTRANSLATED",
            "carCancelled" => "NOTTRANSLATED",
            //Other
            "Points"                          => "\d+\s+ポイント",
            "Total"                           => "合計",
            "Currency"                        => "--<__]",
            "Status"                          => ["確定済み"],
            "Confirmed"                       => ["確定済み"],
            "Need help with your reservation" => "ご予約に関するお問い合わせ",
            "Call us at"                      => "問い合わせ先",
            //Transfer
            "Transfer"                  => "NOTTRANSLATED",
            "Reserved for"              => "NOTTRANSLATED",
            "Supplier Reference Number" => "NOTTRANSLATED",
            "Arrival Date / Time"       => "NOTTRANSLATED",
            "Departure Date / Time"     => "NOTTRANSLATED",
        ],
        "az" => [
            //Flight
            "Duration"   => "NOTTRANSLATED", //["總飛行時間", '中途停留'],
            "Passengers" => "旅客資訊",
            "Stop"       => "NOTTRANSLATED",
            "Tickets"    => ["NOTTRANSLATED"],
            "Flight"     => "NOTTRANSLATED",
            //            "Operated by" => "",
            "Taxes & Fees" => "NOTTRANSLATED",
            "Terminal"     => ["Terminal"],
            //Hotels
            "Details"            => "NOTTRANSLATED",
            "Itinerary"          => "NOTTRANSLATED",
            "Phone"              => "NOTTRANSLATED",
            "Fax"                => "NOTTRANSLATED",
            "Reserved"           => "NOTTRANSLATED",
            "Guests"             => "NOTTRANSLATED",
            "Kids"               => "NOTTRANSLATED",
            "CancellationPolicy" => "NOTTRANSLATED",
            "Cancel"             => "NOTTRANSLATED",
            "Cancelled"          => "NOTTRANSLATED",
            "Room"               => "NOTTRANSLATED",
            "Rooms"              => "NOTTRANSLATED",
            "RoomType"           => "NOTTRANSLATED",
            "Check-in time"      => "NOTTRANSLATED",
            "Check-out time"     => "NOTTRANSLATED",
            //Car
            "Pickup"       => "NOTTRANSLATED",
            "Dropoff"      => "NOTTRANSLATED",
            "carCancelled" => "NOTTRANSLATED",
            //Other
            "Points"    => "NOTTRANSLATED",
            "Total"     => "合共",
            "Currency"  => "NOTTRANSLATED",
            "Status"    => "購票手續進行中",
            "Confirmed" => "NOTTRANSLATED",
            //Transfer
            "Transfer"                  => "NOTTRANSLATED",
            "Reserved for"              => "NOTTRANSLATED",
            "Supplier Reference Number" => "NOTTRANSLATED",
            "Arrival Date / Time"       => "NOTTRANSLATED",
            "Departure Date / Time"     => "NOTTRANSLATED",
        ],
        "zh" => [
            //Flight
            "Duration"     => ["總飛行時間", "中途停留"],
            "Passengers"   => "旅客資訊",
            "Stop"         => "NOTTRANSLATED",
            "Tickets"      => ["NOTTRANSLATED"],
            "Flight"       => "機票",
            "Operated by"  => "运营商",
            "Taxes & Fees" => "稅項及附加費",
            "Terminal"     => ["Terminal"],
            //Hotels
            "Details"            => ["查看飯店詳細資料", '查看酒店详细信息'],
            "Itinerary"          => ["行程表", "行程", "行程編號", '确认号'],
            "Phone"              => "(?:電話：|电话：)",
            "Fax"                => ["傳真：", '传真：'],
            "Reserved"           => ["預訂人", "预留给"],
            "Guests"             => "位成人",
            "Kids"               => "NOTTRANSLATED",
            "CancellationPolicy" => "重要飯店資訊",
            "Cancel"             => "取消",
            "Cancelled"          => "取消",
            "Room"               => "客房",
            "Rooms"              => "\b(\d{1,3})\s+間客房?\s*\|",
            "RoomType"           => "包含：",
            "Check-in time"      => "入住时间开始于",
            "Check-out time"     => "NOTTRANSLATED",
            //Car
            "Pickup"       => "NOTTRANSLATED",
            "Dropoff"      => "NOTTRANSLATED",
            "carCancelled" => "NOTTRANSLATED",
            //Other
            "(?:[\d,]+\s+个积分)",
            "Total"     => ["總計", "合共", '总价'],
            "Currency"  => "NOTTRANSLATED",
            "Status"    => "正在处理票务",
            "Confirmed" => "已确认",
            //            "Need help with your reservation" => "",
            //            "Call us at" => "",
            //Transfer
            "Transfer"                  => "NOTTRANSLATED",
            "Reserved for"              => "NOTTRANSLATED",
            "Supplier Reference Number" => "NOTTRANSLATED",
            "Arrival Date / Time"       => "NOTTRANSLATED",
            "Departure Date / Time"     => "NOTTRANSLATED",
        ],
        "fi" => [
            //Flight
            "Duration"   => "Matkustusaika",
            "Passengers" => "Matkustajan tiedot",
            "Stop"       => "NOTTRANSLATED",
            "Tickets"    => "Lipun numero",
            "Flight"     => "Lento",
            //            "Operated by" => "",
            "Taxes & Fees" => "Verot ja maksut",
            "Terminal"     => ["Terminal", "Terminaali"],
            //Hotels
            "Details"            => "Näytä hotellin tiedot",
            "Itinerary"          => ["Vahvistusnumero", "Matkasuunnitelma"],
            "Phone"              => "Puh",
            "Fax"                => "Faksi",
            "Reserved"           => "Varaaja",
            "Guests"             => "aikuista",
            "Kids"               => "NOTTRANSLATED",
            "CancellationPolicy" => "Tärkeää tietoa hotellista",
            "Cancel"             => "PERUUTTAA",
            "Cancelled"          => "NOTTRANSLATED",
            "Room"               => "Huone",
            "Rooms"              => "NOTTRANSLATED",
            "RoomType"           => "Sisältää:",
            "Check-in time"      => "Tuloaika alkaa",
            "Check-out time"     => "NOTTRANSLATED",
            //Car
            "Pickup"       => "NOTTRANSLATED",
            "Dropoff"      => "NOTTRANSLATED",
            "carCancelled" => "NOTTRANSLATED",
            //Other
            "Points"    => "NOTTRANSLATED",
            "Total"     => "Kokonaishinta",
            "Currency"  => "ja ne on ilmoitettu valuutassa\s+(.*?)\.",
            "Status"    => "VAHVISTETTU",
            "Confirmed" => "VAHVISTETTU",
            //            "Need help with your reservation" => "",
            //            "Call us at" => "",
            //Transfer
            "Transfer"                  => "NOTTRANSLATED",
            "Reserved for"              => "NOTTRANSLATED",
            "Supplier Reference Number" => "NOTTRANSLATED",
            "Arrival Date / Time"       => "NOTTRANSLATED",
            "Departure Date / Time"     => "NOTTRANSLATED",
        ],
    ];
    private $ProgramName = [
        'travelocity' => [
            'Travelocity',
        ],
        'orbitz' => [
            'Orbitz',
        ],
        'hotwire' => [
            'Hotwire',
        ],
        'ebookers' => [
            'ebookers',
        ],
        'hotels' => [
            'Hotels.com',
        ],
        'expedia' => [
            'Expedia',
        ],
        'cheaptickets' => [
            'CheapCash',
            'CheapTickets',
        ],
        'lastminute' => [
            'Lastminute',
        ],
        'alaskaair' => [
            'Alaska Air',
        ],
        'chase' => [
            'Chase Travel',
        ],
    ];

    private $patterns = [
        'time'          => '\d{1,2}(?:[:：]\d{2})?(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.    |    3pm
        'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
    ];

    public function detectEmailFromProvider($from)
    {
        foreach (self::$headers as $arr) {
            foreach ($arr['from'] as $f) {
                if (stripos($from, $f) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers['from']) || empty($headers['subject'])) {
            return false;
        }

        foreach (self::$headers as $code => $arr) {
            $byFrom = $bySubj = false;

            foreach ($arr['from'] as $f) {
                if (stripos($headers['from'], $f) !== false) {
                    $byFrom = true;

                    break;
                }
            }

            foreach ($arr['subj'] as $arrSubj) {
                $arrSubj = (array) $arrSubj;

                foreach ($arrSubj as $subj) {
                    if (stripos($headers['subject'], $subj) !== false) {
                        $bySubj = true;

                        break 2;
                    }
                }
            }

            if ($byFrom && $bySubj) {
                $this->code = $code;

                return true;
            }
        }

        return false;
    }

    /**
     * @return array|Email
     *
     * @throws \AwardWallet\Schema\Parser\Component\InvalidDataException
     */
    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->http->FilterHTML = false;

        $this->code = $this->getProvider($parser);

        if (empty($this->code)) {
            $this->logger->debug("Can't determine a providerCode!");

            return $email;
        } else {
            $this->logger->debug('[providerCode]:' . $this->code);
        }

        if (!$this->assignLang()) {
            $this->code = 'expedia';

            if (!$this->assignLang()) {
                $this->logger->debug("Can't determine a language!");

                return $email;
            } else {
                $this->logger->debug('[providerCode]:' . $this->code);
                $this->logger->debug('[language]:' . $this->lang);
            }
        } else {
            $this->logger->debug('[language]:' . $this->lang);
        }

        $this->text = text($this->http->Response['body']);

        $email->ota()->code($this->code);
        $email->setProviderCode($this->code);

        if (preg_match("#({$this->opt($this->t('Itinerary'))}\s+(?:\#\s+|))([-\w_]*\d[-\w_]{3,})#i", $this->text, $m)) {
            $email->ota()->confirmation($m[2], $m[1], true);
        }

        $node = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Call us at'))}]/ancestor::li[1]");

        if (!empty($node)) {
            $descr = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Call us at'))}]/ancestor::tr[1]/preceding::text()[normalize-space(.)!=''][1]");
        } else {
            $node = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Need help with your reservation'))}]/following::li[2]");
            $descr = $this->t('Need help with your reservation');
        }

        if (preg_match_all("/(^.*?|\.\s*.+?|\(|)\s*([+(\d][-. A-Z\d)(]{5,}[\d)])/", $node, $m, PREG_SET_ORDER)) {
            $addedPhones = [];

            foreach ($m as $v) {
                $num = preg_replace(["#\s*\(\s*#", "#\s*\)\s*#"], ['(', ')'], trim($v[2], " ("));

                if (preg_match("#{$this->opt($this->t('Call us at'))}#", $v[1]) || $v[1] == '(' || empty($v[1])) {
                    if (!in_array($num, $addedPhones)) {
                        $email->ota()->phone($num, $descr);
                        $addedPhones[] = $num;
                    }
                } else {
                    if (!in_array($num, $addedPhones)) {
                        $email->ota()->phone($num, trim($v[1], " ."));
                        $addedPhones[] = $num;
                    }
                }
            }
        }

        $a = explode('\\', __CLASS__);
        $class = end($a);
        $email->setType($class . ucfirst($this->lang));

        $this->parseEmail($email);

        return $email;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if (!empty($this->code = $this->getProviderByBody())) {
            $flag = $this->assignLang();

            if (!$flag) {
                $this->code = 'expedia';

                return $this->assignLang();
            }

            return true;
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        $types = 5; // flight | hotel | car | transfer | event
        $cnt = $types * count(self::$dictionary);

        return $cnt;
    }

    public static function getEmailProviders()
    {
        return array_keys(self::$headers);
    }

    private function getProviderByBody(): ?string
    {
        foreach ($this->bodies as $code => $criteria) {
            if (count($criteria) > 0) {
                foreach ($criteria as $search) {
                    if (($this->http->XPath->query("//text()[contains(.,'{$search}')]")->length > 0)
                        || (strpos($search,
                                '.') !== false && $this->http->XPath->query("//a[contains(@href,'{$search}')]")->length > 0)
                    ) {
                        return $code;
                    }
                }
            }
        }

        return null;
    }

    private function getProvider(PlancakeEmailParser $parser)
    {
        $this->detectEmailByHeaders(['from' => $parser->getCleanFrom(), 'subject' => $parser->getSubject()]);

        if (isset($this->code)) {
            return $this->code;
        }

        return $this->getProviderByBody();
    }

    private function getAirlineNode($root, $AirlineQuery = 'false()'): array
    {
        $airline = null;

        if (!empty($node = $this->http->FindSingleNode("(./following-sibling::tr//*[{$AirlineQuery}])[1]",
            $root, true, "#.*?(?:\s+|\?)(\d+)#"))
        ) {
            $airline = $this->http->FindSingleNode("(./following-sibling::tr//*[{$AirlineQuery}])[1]",
                $root, true, "#(.*?)(?:\s+|\?)\d+#");
        } elseif (!empty($node = $this->http->FindSingleNode("./following-sibling::tr[2][not(.//*[contains(@style, 'red')])]",
            $root, true, "#.*?(?:\s+|\?)(\d+)#"))
        ) {
            $airline = $this->http->FindSingleNode("./following-sibling::tr[2][not(.//*[contains(@style, 'red')])]",
                $root, true, "#(.*?)(?:\s+|\?)\d+#");
        } elseif (!empty($node = $this->http->FindSingleNode("./following-sibling::tr[3][not(.//*[contains(@style, 'red')])]",
            $root, true, "#.*?(?:\s+|\?)(\d+)#"))
        ) {
            $airline = $this->http->FindSingleNode("./following-sibling::tr[3][not(.//*[contains(@style, 'red')])]",
                $root, true, "#(.*?)(?:\s+|\?)\d+#");
        } elseif (!empty($node = $this->http->FindSingleNode("./following-sibling::tr[4][not(.//*[contains(@style, 'red')])]",
            $root, true, "#.*?(?:\s+|\?)(\d+)#"))
        ) {
            $airline = $this->http->FindSingleNode("./following-sibling::tr[4][not(.//*[contains(@style, 'red')])]",
                $root, true, "#(.*?)(?:\s+|\?)\d+#");
        }

        return [$airline, $node];
    }

    /**
     * @throws \AwardWallet\Schema\Parser\Component\InvalidDataException
     */
    private function flight(Email $email): void
    {
        $RecordLocators = [];
        $xpath = "//*[{$this->starts($this->t("Status"))}]/ancestor::tr[1]/following-sibling::tr";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length == 0) {
            $this->logger->debug("record locators roots not found: $xpath");
        }

        $this->logger->debug('SEGMENTS found by: ' . $xpath);

        foreach ($nodes as $root) {
            $RecordLocators[$this->http->FindSingleNode("./td[1]", $root)] = $this->http->FindSingleNode("./td[2]",
                $root);
        }

        $RecordLocators = array_filter($RecordLocators);

        $xpath = "//*[contains(@alt, 'custom air icon') or (@width='11%' and (@rowspan='4' or @rowspan='5'))]";
        $nodes = $this->http->XPath->query($xpath . '/ancestor::tr[1]/following-sibling::tr');

        if ($nodes->length > 0) {
            $nodes = $this->http->XPath->query($xpath . '/ancestor::tr[1]');
            $xpathUsed = $xpath . '/ancestor::tr[1]';
        } else {
            $nodes = $this->http->XPath->query($xpath . '/ancestor::tr[2]'); // it-5523945.eml
            $xpathUsed = $xpath . '/ancestor::tr[2]';
        }

        if ($nodes->length === 0) {
            $this->logger->debug("segments root not found: $xpathUsed");
        } else {
            $this->logger->debug("segments root found by: $xpathUsed");
        }

        if (count($RecordLocators) > 0) {
            $AirlineQuery = $this->contains($RecordLocators);
        } else {
            $AirlineQuery = "false";
        }

        $airs = [];

        foreach ($nodes as $root) {
            $rl = CONFNO_UNKNOWN;

            if ($bn = $this->http->FindSingleNode("./ancestor::tr[./preceding-sibling::tr[1][contains(., '→')]][1]/preceding-sibling::tr[1]//text()[contains(., 'Booking ID')]/following::text()[normalize-space(.)!=''][1]",
                $root, true, "#([A-Z\d]{5,})#")
            ) {
                $rl = $bn;
            } elseif ($bn = $this->http->FindSingleNode("./ancestor::tr[./preceding-sibling::tr[1][contains(., '→')]][1]/preceding-sibling::tr[1]//text()[contains(., 'Booking ID')]/ancestor::td[1]/following::td[normalize-space(.)!=''][1]",
                $root, true, "#([A-Z\d]{5,})#")
            ) {
                $rl = $bn;
            } elseif (($pnrs = $this->http->XPath->query("./ancestor::tr[./preceding-sibling::tr[1][contains(., '→')]][1]/preceding-sibling::tr[1]/descendant::table[last()]/descendant::tr[1][{$this->starts($this->t("Status"))}]/following-sibling::tr",
                    $root))->length > 0
            ) {
                [$airline, $node] = $this->getAirlineNode($root, $AirlineQuery);

                foreach ($pnrs as $r) {
                    $texts = $this->http->FindNodes("./td[normalize-space()!='']", $r);

                    if ((count($texts) === 2) && strtolower($texts[0]) === strtolower($airline)) {
                        $rl = $texts[1];

                        break;
                    }
                }
            }
            $airs[$rl][] = $root;
        }

//            if ($confno = $this->http->FindSingleNode("./preceding::td[{$this->hotelHeaderRule}][2]", $root, true, "#{$this->opt($this->t('Itinerary'))}\s+(?:\#\s+|)([-\w_]*\d[-\w_]*)#")) {
//                $rl = $confno;
//            } elseif($confno = $this->http->FindSingleNode("./preceding::td[{$this->hotelHeaderRule}][3]", $root, true, "#{$this->opt($this->t('Itinerary'))}\s+(?:\#\s+|)([-\w_]*\d[-\w_]*)#")) {
//                $rl = $confno;
//            }

        // Passengers
        $passengers = [];
        $passengerCells = $this->http->XPath->query("//text()[{$this->starts($this->t('Passengers'))}]/ancestor::tr[1]/following-sibling::tr[1]/td/table//tr/td[1]");

        foreach ($passengerCells as $pCell) {
            $pCellHtml = $this->http->FindHTMLByXpath('.', null, $pCell);

            if (preg_match("/^\s*({$this->patterns['travellerName']})[ ]*(?:\n|$)/u", $this->htmlToText($pCellHtml), $m)
                && strtolower($m[1]) !== 'adult' && strtolower($m[1]) !== 'child'
            ) {
                $passengers[] = $m[1];
            }
        }

        foreach ($airs as $rl => $roots) {
            $f = $email->add()->flight();

            if (CONFNO_UNKNOWN !== $rl) {
                $f->general()->confirmation($rl);
            } else {
                $f->general()->noConfirmation();
            }

            if (!empty($passengers[0])) {
                $f->general()->travellers(array_unique($passengers));
            }

            $accountNumbers = [];

            // TicketNumbers
            $ticketNumbers = [];

            foreach ($roots as $root) {
                $ticketNumbers = array_merge($ticketNumbers,
                    $this->http->FindNodes("./ancestor::table[{$this->contains($this->t('Tickets'))}][1]//*[{$this->starts($this->t('Tickets'))}]",
                        $root, "#{$this->opt($this->t('Tickets'))}\s+(\d+)#"));
            }

            $ticketNumberValues = array_values(array_filter(array_unique($ticketNumbers)));

            foreach ($ticketNumberValues as $ticketNumberValue) {
                $this->logger->error($ticketNumberValue);
                $pax = $this->http->FindSingleNode("//text()[{$this->contains($ticketNumberValue)}]/ancestor::tr[1]/descendant::text()[normalize-space()][1]");

                if (!empty($pax)) {
                    $f->issued()
                        ->ticket($ticketNumberValue, false, $pax);
                } else {
                    $f->issued()
                        ->ticket($ticketNumberValue, false);
                }
            }

            /*if (!empty($ticketNumberValues[0])) {
                $f->issued()->tickets($ticketNumberValues, false);
            }*/

            // Cancelled
            if ($this->http->FindSingleNode("./preceding::td[{$this->hotelHeaderRule[$this->code]}][1]", null, true,
                "#{$this->opt($this->t('Cancelled'))}#")
            ) {
                $f->general()->cancelled();
            }

            if ($this->http->FindSingleNode($q = "./preceding::td[{$this->hotelHeaderRule[$this->code]}][1]//*[{$this->starts($this->t('Confirmed'))}]",
                null)
            ) {
                $f->general()->status('confirmed');
            }

            foreach ($roots as $root) {
                $date = null;

                $dateStr = $this->http->FindSingleNode("(./ancestor::tr[1]/preceding-sibling::tr[1]/descendant::text()[contains(.,'/')])[1]",
                    $root, true, '/\d+\/\d+\/\d+/'); // it-4500140.eml

                if (!$dateStr) {
                    // it-4938213.eml
                    $dateTexts = $this->http->FindNodes("./ancestor::tr[1]/preceding-sibling::tr[normalize-space(.)!=''][1]/descendant::td[normalize-space(.) and not(.//td)][1]/descendant::*[self::strong or self::b][normalize-space(.)!=''][ not(./descendant::*[self::strong or self::b][normalize-space(.)!='']) ]",
                        $root);
                    $dateText = implode(' ', $dateTexts);

                    if (preg_match('/^(.*\d.*)$/', $dateText)) {
                        $dateStr = $dateText;
                    }
                }

                if (!$dateStr) {
                    $dateStr = $this->http->FindSingleNode("./preceding-sibling::tr[1]/descendant::text()[normalize-space(.)!=''][1]",
                        $root, true, '/^(.*\d.*)$/');
                }

                if (!$dateStr) {
                    $dateTexts = $this->http->FindNodes("./preceding::td[normalize-space(.) and not(.//td)][position() < 3]/descendant::*[self::strong or self::b][normalize-space(.)!=''][ not(./descendant::*[self::strong or self::b][normalize-space(.)!='']) ]",
                        $root);
                    $dateText = implode(' ', $dateTexts);

                    if (preg_match('/^(.*\b20\d{2}\b.*)$/', $dateText)) {
                        $dateStr = $dateText;
                    }
                }

                if ($dateStr) {
                    $date = strtotime($this->normalizeDate($dateStr));
                }

                $s = $f->addSegment();

                [$airline, $node] = $this->getAirlineNode($root, $AirlineQuery);

                // else $node = FLIGHT_NUMBER_UNKNOWN;
                if (!empty($node) && isset($airline)) {
                    $s->airline()
                        ->number($node)
                        ->name($airline);
                    $pnr = $this->http->FindSingleNode("./preceding::tr[not(.//tr) and ({$this->starts($this->t('Status'))})][1]/following-sibling::tr[contains(.,'{$airline}')]/descendant::text()[normalize-space(.)!=''][2]",
                        $root, true, "#^([A-Z\d]{5,})$#");

                    if (!empty($pnr)) {
                        $s->airline()->confirmation($pnr);
                    }
                    $operator = $this->http->FindSingleNode("following-sibling::tr[normalize-space()][position()<4]/descendant::text()[{$this->contains($this->t('Operated by'))}]", $root, true, "#{$this->opt($this->t('Operated by'))}\s*(.+)$#");
                    $s->airline()->operator($operator, false, true);
                }

                // DepCode
                $s->departure()->code($this->http->FindSingleNode("./following-sibling::tr[1]/td[1]", $root, true,
                    "#([A-Z]{3})#"));

                // DepartureTerminal
                $terminalDep = $this->http->FindSingleNode('./following-sibling::tr[1]/td[1]', $root, true,
                    "#{$this->opt($this->t('Terminal'))}\s*(\w+)#iu");

                if ($terminalDep) {
                    $s->departure()->terminal($terminalDep);
                }

                // DepDate
                $timeDep = $this->http->FindSingleNode('./following-sibling::tr[1]/td[1]', $root, true,
                    '/\d{1,2}\s*[h:.]\s*\d{2}(?:\s*[AaPp](?:[Mm])?\b)?/u');

                if ($timeDep && $date) {
                    $s->departure()->date(strtotime($this->normalizeTime($timeDep), $date));
//                    $s->departure()->date(strtotime(str_replace(['.', ' h ', 'h'], ':', $timeDep), $date));
                }

                // ArrCode
                $s->arrival()->code($this->http->FindSingleNode("./following-sibling::tr[1]/td[normalize-space(.)][2]", $root, true,
                    "#([A-Z]{3})#"));

                // ArrivalTerminal
                $terminalArr = $this->http->FindSingleNode("./following-sibling::tr[1]/td[normalize-space(.)][2]", $root, true,
                    "#{$this->opt($this->t('Terminal'))}\s*(\w+)#iu");

                if ($terminalArr) {
                    $s->arrival()->terminal($terminalArr);
                }

                // ArrDate
                $timeArr = $this->http->FindSingleNode("./following-sibling::tr[1]/td[normalize-space(.)][2]", $root, true,
                    '/\d{1,2}\s*[h:.]\s*\d{2}(?:\s*[AaPp](?:[Mm])?\b)?/');

                if ($timeArr && $date) {
                    $timeArr = preg_replace("/(\D*$)/", "", $timeArr);
                    $s->arrival()->date(strtotime($this->normalizeTime($timeArr), $date));
                    $nextDay = $this->http->FindSingleNode("./following-sibling::tr[1]/td[normalize-space(.)][2]", $root, true,
                        '/\d{1,2}\s*[h:.]\s*\d{2}(?:\s*[AaPp](?:[Mm])?)?\s*\+(\d+)\s*\w+/u');

                    if (!empty($nextDay) && !empty($s->getArrDate())) {
                        $s->arrival()->date(strtotime("+" . $nextDay . " days", $s->getArrDate()));
                    }

                    if (isset($airline) && preg_match("/^\s*(\w+)/", $airline, $m)) {
                        $nums = array_filter($this->http->FindNodes("//*[{$this->starts($this->t('Ticket'))}]/ancestor-or-self::td[1]/preceding-sibling::td[1]//text()[contains(normalize-space(),'{$m[1]}')]/ancestor::td[1]", null, "/^{$m[1]}.*?(?:[ ]+INVLD NAME FOR ACCT NO)?(?-i)((?:[ ]+(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]))?[ ]+[A-Z\d]{5,})$/i"), function ($item) {
                            return preg_match('/\d/', $item) > 0;
                        });

                        if (count($nums) > 0) {
                            $accountNumbers = array_merge($accountNumbers, $nums);
                        }
                    }

                    // TraveledMiles
                    $node = $this->http->FindSingleNode("./td[4]", $root, true,
                        "#[\d\,\.]+\s*(?:km|miles|mi)$#");

                    if (!empty($node)) {
                        $s->setMiles($node);
                    }

                    $extraRows = $this->http->FindNodes("following-sibling::tr[normalize-space()][position()>2 and position()<6]", $root);
                    $extra = implode("\n", $extraRows);

                    // Cabin
                    if (preg_match("#^[ ]*(?:Class\S*[ ]+|)(\S*?)(?:[ ]*/[ ]*\w+|[ ]+Class|)[ ]+\([ ]*[A-Z]{1,2}[ ]*\)#m", $extra, $m)) {
                        $s->setCabin($m[1]);
                    }

                    // BookingClass
                    if (preg_match("#^[ ]*(?:Class\S*[ ]+|)\S*?(?:[ ]*/[ ]*\w+|[ ]+Class|)[ ]+\([ ]*([A-Z]{1,2})[ ]*\)#m", $extra, $m)) {
                        $s->setBookingCode($m[1]);
                    }

                    // Seats
                    if (preg_match("#\s+(\d{2}(?:(?!\d)\w)[\s,]+.*?)\s*\|#", $extra, $m)) {
                        $s->setSeats(array_unique(preg_split('/\s*,\s*/', $m[1])));
                    }

                    // Duration
                    $node = $this->http->FindSingleNode("./td[4]", $root, true,
                        "#\d+\s+[^\d\s]+\s+\d+\s+[^\d\s]+#");

                    if (!empty($node)) {
                        $s->setDuration($node);
                    }
                }
            }

            if (count($accountNumbers)) {
                $f->program()->accounts(array_unique($accountNumbers), false);
            }
        }
    }

    /**
     * @throws \AwardWallet\Schema\Parser\Component\InvalidDataException
     */
    private function hotel(Email $email): void
    {
        $xpath = "//text()[{$this->starts($this->t('Details'))}]/ancestor::table[{$this->contains($this->t('Room'))}][2]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length === 0) {
            $xpath = "//text()[{$this->starts($this->t('Details'))}]/ancestor::table[2]";
            $nodes = $this->http->XPath->query($xpath);
        }

        if ($nodes->length === 0) {
            $xpath = "//text()[{$this->starts($this->t('Details'))}]/ancestor::*[1]/ancestor::*[last()]";
            $nodes = $this->http->XPath->query($xpath);
        }

        if ($nodes->length > 0) {
            $this->logger->debug('Segments for hotel found by: ' . $xpath);
        } else {
            $this->logger->debug('Segments for hotel not found: ' . $xpath);
        }

        foreach ($nodes as $root) {
            $h = $email->add()->hotel();

            // ConfirmationNumber
            $node = implode("\n",
                $this->http->FindNodes("./preceding::td[{$this->hotelHeaderRule[$this->code]}][1]//text()[normalize-space(.)!='']",
                    $root));

            if (preg_match_all("#({$this->opt($this->t('Itinerary'))}\s+(?:\#\s+|))([\w_\- ]+\d*[\w_ ]*)#", $node, $confNoMatches, PREG_SET_ORDER)) {
                foreach ($confNoMatches as $v) {
                    $h->general()->confirmation(str_replace(' ', '-', trim($v[2])), trim($v[1]));
                }
            } else {
                $node = text($this->http->FindSingleNode(".", $root));

                if (preg_match("#({$this->opt($this->t('Itinerary'))})\s+(?:\#\s*[:]*\s*|)([\w_]+\d[\w_]*)#", $node,
                    $m)) {
                    $h->general()->confirmation($m[2], $m[1]);
                } else {
                    $h->general()->noConfirmation();
                }
            }

            // HotelName
            $node = $this->http->FindSingleNode("(./preceding::td[{$this->hotelHeaderRule[$this->code]}][1]/descendant::text()[string-length(normalize-space(.))>1])[1][not(contains(., '→'))]",
                $root);

            if (empty($node)) {
                $node = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Status'))}]/ancestor::*[1]/preceding::text()[normalize-space(.)!=''][1][{$this->contains($this->t('nights'))}]/preceding::text()[normalize-space(.)!=''][1]");
            }

            if (empty($node)) {
                $node = $this->http->FindSingleNode("./preceding::text()[{$this->starts($this->t('Status'))}][1]/ancestor::*[1]/preceding::text()[string-length(normalize-space(.))>1][2]",
                    $root);
            }

            if (empty($node)) {
                $node = $this->http->FindSingleNode("(//text()[{$this->starts($this->t('Status'))}][1])[last()]/ancestor::td[preceding-sibling::td][1]/preceding-sibling::td[string-length(normalize-space(.))>1][1]/descendant::tr[1]");
            }
            $h->hotel()->name($node);

            // CheckInDate
            $node = $this->http->FindSingleNode("(./preceding::td[{$this->hotelHeaderRule[$this->code]}][1]/descendant::tr[string-length(normalize-space(.))>1 and not(.//tr)])[2]",
                $root, true, "#^(.*?)\s*-#");

            if (empty($node)) {
                $node = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Status'))}]/ancestor::*[1]/preceding::text()[normalize-space(.)!=''][1][{$this->contains($this->t('nights'))}]",
                    null, true, "#^(.*?)\s*-#");
            }

            if (empty($node)) {
                $node = $this->http->FindSingleNode("(//text()[{$this->starts($this->t('Status'))}][1])[last()]/ancestor::td[preceding-sibling::td][1]/preceding-sibling::td[1][normalize-space(.)!=''][1]/descendant::tr[2]",
                    null, true, "#^(.*?)\s*-#");
            }

            $dateCheckIn = strtotime($this->normalizeDate($node));

            if (!empty($dateCheckIn)) {
                $time = $this->normalizeTime($this->http->FindSingleNode("//text()[{$this->contains($this->t('Check-in time'))}]"));

                if (!empty($time)) {
                    $dateCheckIn = strtotime($time, $dateCheckIn);
                }
            }
            $h->booked()->checkIn($dateCheckIn);

            // CheckOutDate
            $node = $this->http->FindSingleNode("(./preceding::td[{$this->hotelHeaderRule[$this->code]}][1]/descendant::tr[string-length(normalize-space(.))>1 and not(.//tr)])[2]",
                $root, true, "#^.*?\s*-\s*(.*?\d{4}.*?)\s*(?:,|$)#");
            $node = preg_replace(['/,?\s*\d{1,3}\s*rooms?/i', '/\s*\|\s*\d+ nights?/i'], '', $node);

            if (empty($node)) {
                $node = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Status'))}]/ancestor::*[1]/preceding::text()[normalize-space(.)!=''][1][{$this->contains($this->t('nights'))}]",
                    null, true, "/^.*?\s*-\s*(.*?)\s*(?:[|]|$)/");
            }

            if (empty($node)) {
                $node = $this->http->FindSingleNode("(//text()[{$this->starts($this->t('Status'))}][1])[last()]/ancestor::td[preceding-sibling::td][1]/preceding-sibling::td[1][normalize-space(.)!=''][1]/descendant::tr[2]",
                    null, true, "/^.*?\s*-\s*(.*?)\s*(?:[|]|$)/");
            }
            $dateCheckOut = strtotime($this->normalizeDate($node));
            $h->booked()->checkOut($dateCheckOut);

            if (!empty($h->getCheckOutDate()) && ($time = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Check-out time'))}]", null, true, '/\:[ ]*(.+)/'))) {
                $time = str_replace('meia-noite', '24:00', $time);
                $checkOut = strtotime($time, $h->getCheckOutDate());

                if (false !== $checkOut) {
                    $h->booked()
                        ->checkOut($checkOut);
                }
            }

            if (!empty($h->getCheckOutDate()) && ($time = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Check-out time'))}]"))) {
                $checkOut = strtotime($this->normalizeTime($time), $h->getCheckOutDate());
                $h->booked()
                    ->checkOut($checkOut);
            }

            // Address
            $node = $this->http->FindSingleNode(".//*[{$this->starts($this->t('Details'))}]/ancestor::h2/following-sibling::*[1]",
                $root, true, "#(.*?)\s*(?:{$this->t('Phone')}|$)#u");

            if (empty($node)) {
                $node = $this->http->FindSingleNode(".//*[{$this->starts($this->t('Details'))}]/preceding::*[normalize-space()][position()<5][" . $this->starts($this->t("Phone")) . "]/preceding::text()[normalize-space()][1]/ancestor::a[1]",
                    $root, true, "#(.*?)\s*(?:{$this->t('Phone')}|$)#u");
            }

            if (empty($node)) {
                $node = $this->http->FindSingleNode("(.//*[{$this->starts($this->t('Details'))}]/following::text()[normalize-space()][1]/ancestor::a)[1]",
                    $root);
            }

            if (empty($node)) {
                $node = $this->http->FindSingleNode("(.//img)[1]/ancestor::td[not(normalize-space())][1]/following-sibling::td[normalize-space()][1]",
                    $root, true, "#(.*?)\s*{$this->t('Phone')}#u");
            }

            $h->hotel()->address($node);

            // Phone
            $node =
                trim($this->http->FindSingleNode(".//*[{$this->starts($this->t('Details'))}]/ancestor::h2/following-sibling::*[2]",
                    $root, true, "#{$this->t('Phone')}\s*:?\s*([+\d\s\(\)-]+)#u"));

            if (empty($node)) {
                $node =
                    trim($this->http->FindSingleNode(".//*[{$this->starts($this->t('Details'))}]/following::a[1]/..",
                        $root, true, "#{$this->t('Phone')}\s*:?\s*([+\d\s\(\)-]+)#u"));
            }

            if (empty($node)) {
                $node =
                    trim($this->http->FindSingleNode($q = ".//*[{$this->starts($this->t('Details'))}]/ancestor::h2/following-sibling::*[1]",
                        $root, true, "#{$this->t('Phone')}\s*:?\s*([+\d\s\(\)-]+)#u"));
            }

            if (empty($node)) {
                $node = $this->http->FindSingleNode("(.//img)[1]/ancestor::td[not(normalize-space())][1]/following-sibling::td[normalize-space()][1]",
                    $root, true, "#{$this->t('Phone')}\s*:?\s*([+\d\s\(\)-]+)#u");
            }

            if (strlen($node) >= 5) {
                $h->hotel()->phone($node);
            }

            // Fax
            $node = trim($this->http->FindSingleNode(".//*[{$this->starts($this->t('Details'))}]/ancestor::h2/following-sibling::*[2]",
                $root, true, "#{$this->opt($this->t('Fax'))}\s*:?\s*([+\d\s\(\)-]+)#"));

            if (empty($node)) {
                $node = trim($this->http->FindSingleNode(".//*[{$this->starts($this->t('Details'))}]/following::a[1]/..",
                    $root, true, "#{$this->opt($this->t('Fax'))}\s*:?\s*([+\d\s\(\)-]+)#"));
            }

            if (empty($node)) {
                $node = trim($this->http->FindSingleNode($q = ".//*[{$this->starts($this->t('Details'))}]/ancestor::h2/following-sibling::*[1]",
                    $root, true, "#{$this->opt($this->t('Fax'))}\s*:?\s*([+\d\s\(\)-]+)#"));
            }

            if (!empty($node) && strlen($node) > 6) {
                $h->hotel()->fax($node, true);
            }

            // GuestNames
            $guestNames = $this->http->FindNodes(".//*[{$this->starts($this->t('Reserved'))}]/ancestor::td[1]/following-sibling::td[last()]/descendant::*[normalize-space(.)!=''][name()='div' or name()='p' or name()='span'][1]",
                $root, '/^(\D.+)/');

            if (empty($guestNames)) {
                $guestNames = $this->http->FindNodes(".//*[{$this->starts($this->t('Reserved'))}]/ancestor::tr[1]/following-sibling::tr[1]/td[1]/*[1]",
                    $root);
            }

            if (is_array($guestNames)) {
                $guestNameValues = array_values(array_filter($guestNames));

                if (!empty($guestNameValues[0])) {
                    $h->general()->travellers(array_unique($guestNameValues));
                }
            }

            // Guests
            if ($count = array_sum($this->http->FindNodes(".//*[{$this->starts($this->t('Reserved'))}]/ancestor::td[1]/following-sibling::td[last()]/descendant::text()[contains(normalize-space(.),'{$this->t('Guests')}')]", $root, "#(\d+)\s+{$this->opt($this->t('Guests'))}#"))) {
                $guests = $count;
            }

            if (isset($count) && 0 === $count && ($count = array_sum($this->http->FindNodes(".//*[{$this->starts($this->t('Reserved'))}]/ancestor::tr[1]/following-sibling::tr[1]", $root, "#(\d+)\s+{$this->opt($this->t('Guests'))}#")))) {
                $guests = $count;
            }

            if (!empty($guests)) {
                $h->booked()->guests($guests);
            }

            // Kids
            if ($count = array_sum($this->http->FindNodes(".//*[{$this->starts($this->t('Reserved'))}]/ancestor::td[1]/following-sibling::td[last()]/descendant::text()[{$this->contains($this->t('Kids'))}][last()]",
                $root, "#(\d+)\s+{$this->opt($this->t('Kids'))}#"))
            ) {
                $kids = $count;
            }

            if ($count = array_sum($this->http->FindNodes(".//*[{$this->starts($this->t('Reserved'))}]/ancestor::tr[1]/following-sibling::tr[1]",
                $root, "#(\d+)\s+{$this->opt($this->t('Kids'))}#"))
            ) {
                $kids = $count;
            }

            if (!empty($kids)) {
                $h->booked()->kids($kids);
            }

            // Rooms
            $node = $this->re("/{$this->t('Rooms')}/i", $this->text);

            if (!empty($node)) {
                $h->booked()->rooms($node);
            }

            // CancellationPolicy
            $node =
                implode(' ',
                    $this->http->FindNodes("//text()[{$this->contains($this->t('CancellationPolicy'))}]/following::ul[1]/*[contains(translate(., '{$this->t('Cancel')}', '" . strtolower($this->t('Cancel')) . "'), '" . strtolower($this->t('Cancel')) . "')]",
                        $root));

            if (empty($node)) {
                $node = $this->http->FindSingleNode("//text()[{$this->contains($this->t('CancellationPolicy'))}]/following::ul[1]/preceding-sibling::p[1][contains(translate(., '{$this->t('Cancel')}', '" . strtolower($this->t('Cancel')) . "'), '" . strtolower($this->t('Cancel')) . "')]");
            }

            if (empty($node)) {
                $node = implode(' ',
                    $this->http->FindNodes("//text()[{$this->contains($this->t('CancellationPolicy'))}]/ancestor-or-self::h3[1]/following-sibling::*[contains(translate(., '{$this->t('Cancel')}', '" . strtolower($this->t('Cancel')) . "'), '" . strtolower($this->t('Cancel')) . "')]",
                        $root));
            }

            if (empty($node)) {//6124100.eml
                $node = implode(' ',
                    $this->http->FindNodes("//text()[{$this->contains($this->t('CancellationPolicy'))}]/following::text()[normalize-space(.)!=''][1][contains(translate(., '{$this->t('Cancel2')}', '" . strtolower($this->t('Cancel2')) . "'), '" . strtolower($this->t('Cancel2')) . "')]",
                        $root));
            }

            if (!empty($node)) {
                $h->general()
                    ->cancellation($node);

                if (preg_match('/This reservation is non-refundable and cannot be cancell?ed or changed/i', $node) // en
                    || stripos($node, 'Cette réservation n’est pas remboursable et ne peut pas être annulée ou modifiée') !== false // fr
                ) {
                    $h->booked()
                        ->nonRefundable();
                }

                if (preg_match("/Bei (?i)Änderungen oder Stornierungen nach dem\s+(?<date>\d{1,2}[.\s]+[[:alpha:]]+[.\s]+\d{2,4})[\s(]+(?<time>{$this->patterns['time']})/u", $node, $m)
                    || preg_match("/Bei (?i)Änderungen oder Stornierungen nach\s+(?<time>{$this->patterns['time']}).{0,50}\s+am\s+(?<date>\d{1,2}[.\s]+[[:alpha:]]+[.\s]+\d{2,4})/u", $node, $m)
                    || preg_match("/Las cancelaciones o los cambios efectuados después de las (?<time>{$this->patterns['time']}).{0,50}\s+del\s+(?<date>[[:alpha:]]+[.\s]+\d{1,2}\,\s*\d{2,4}) o las/u", $node, $m)
                ) {
                    $h->booked()->deadline(strtotime($m['time'], strtotime($this->normalizeDate($m['date']))));
                }

                if (preg_match("/Free cancellation until\s*(?<month>\w+)\s*(?<day>\d+)\s*at\s*(?<time>[\d\:]+\s*A?P?M)\s*\(/u", $node, $m)
                ) {
                    $year = date('Y', $h->getCheckInDate());
                    $h->booked()->deadline(strtotime($m['day'] . ' ' . $m['month'] . ' ' . $year . ', ' . $m['time']));
                }

                if (preg_match("/Free cancellation until (?<day>\d+)\s*(?<month>\w+)\s*(?<year>\d{4}) at (?<time>\d+\:\d+\s*a?p?)/u", $node, $m)
                ) {
                    $year = date('Y', $h->getCheckInDate());
                    $h->booked()->deadline(strtotime($m['day'] . ' ' . $m['month'] . ' ' . $year . ', ' . $m['time'] . 'm'));
                }
            }

            // RoomType
            $roomType = array_filter($this->http->FindNodes(".//text()[{$this->starts($this->t('Room'))}]/ancestor::td[1]/following-sibling::td[last()][not(contains(., '$') or contains(., '₩') or contains(., 'kr')) and not({$this->contains($this->t('RoomType'))})]",
                $root)); //, "#(\w.*?)(?:,|$)#"

            if (empty($roomType)) {
                $roomType = array_filter($this->http->FindNodes(".//text()[({$this->starts($this->t('Room'))})]/ancestor::tr[1]/following-sibling::tr[1][not(contains(., '$') or contains(., '₩') or contains(., ':')) or contains(., 'kr')]",
                    $root)); //, "#(\w.*?)(?:,|$)#"
            }

            // RoomTypeDescription
            $roomTypeDesc = $this->http->FindNodes("descendant::text()[{$this->starts($this->t('Room'))}]/ancestor::tr[1]/following-sibling::tr[normalize-space()][1][ *[1][string-length(normalize-space())<2] and *[2] ]/*[last()][not(contains(.,'$'))]", $root);

            if (empty($roomTypeDesc)) {
                $roomTypeDesc = $this->http->FindNodes("./descendant::text()[{$this->starts($this->t('RoomType'))}]/ancestor::tr[1]/following-sibling::tr[1]",
                    $root);
            }

            if (empty($roomTypeDesc)) {
                $roomTypeDesc = $this->http->FindNodes("(./descendant::text()[{$this->starts($this->t('Room'))}]/ancestor::tr[1]/following-sibling::tr[normalize-space(.)!=''])[last()]",
                    $root, "#\w.+#");
            }

            if (empty($roomTypeDesc) && empty($roomType)) {
                $roomType = $this->http->FindNodes("./following::text()[{$this->contains($this->t('Important Hotel Information'))}]/following::table[1]/descendant::text()[{$this->starts($this->t('Room'))}]/ancestor::td[1]/following-sibling::td[string-length(normalize-space(.))>3][1]",
                    $root, "#^(.+?),#");
                $roomTypeDesc = $this->http->FindNodes("./following::text()[{$this->contains($this->t('Important Hotel Information'))}]/following::table[1]/descendant::text()[{$this->starts($this->t('Room'))}]/ancestor::td[1]/following-sibling::td[string-length(normalize-space(.))>3][1]",
                    $root, "#^.+?,\s*(.+)#");
            }

            if (count($roomType) === count($roomTypeDesc)) {
                foreach ($roomType as $i => $rt) {
                    $r = $h->addRoom();
                    $r->setType($rt);
                    $r->setDescription($roomTypeDesc[$i]);
                }
            }

            // Status
            if ($this->http->FindSingleNode("(./preceding::td[{$this->hotelHeaderRule[$this->code]}][1]//*[{$this->starts($this->t('Confirmed'))}])[1]",
                $root)
            ) {
                $h->general()->status('confirmed');
            }

            // Cancelled
            if ($this->http->FindSingleNode("./preceding::td[{$this->hotelHeaderRule[$this->code]}][1]", $root, true,
                "#{$this->opt($this->t('Cancelled'))}#")
            ) {
                $h->general()->cancelled();
            }
        }
    }

    /**
     * @throws \AwardWallet\Schema\Parser\Component\InvalidDataException
     */
    private function car(Email $email): void
    {
        $xpath = "//tr[ *[1]/descendant::tr[not(.//tr) and normalize-space()][1][{$this->eq($this->t('Pickup'))}] and *[2]/descendant::tr[not(.//tr) and normalize-space()][1][{$this->eq($this->t('Dropoff'))}] ]/ancestor::table[3]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length === 0) {
            $this->logger->debug("segments root (rental) not found: $xpath");
        } elseif ($nodes->length > 0) {
            $this->logger->debug('Segments for rental found by: ' . $xpath);
        }

        foreach ($nodes as $root) {
            $r = $email->add()->rental();

            // Number
            if (!empty($node =
                $this->http->FindSingleNode("descendant::tr[1]/descendant::tr[{$this->starts($this->t('Itinerary'))}]",
                    $root))
            ) {
                if (preg_match("#({$this->opt($this->t('Itinerary'))}\s*\#?)\s+(\w+|[\-A-Z\d]+)\s*$#", $node, $m)) {
                    $r->general()->confirmation($m[2], $m[1]);
                }
//                $this->http->FindSingleNode("./tr[1]|./tbody/tr[1]", $root, true, "#\#\s+(\w+)#");
            } elseif (!empty($node = $this->http->FindSingleNode("preceding::td[{$this->hotelHeaderRule[$this->code]}][1]", $root))
                && preg_match("#({$this->opt($this->t('Itinerary'))}\s*(?:\#|))\s+([\w_]*\d[\w_]*)#", $node, $m)
            ) {
                $r->general()->confirmation($m[2], $m[1]);
            } elseif (preg_match("#({$this->opt($this->t('Itinerary'))}\s*(?:\#|))\s+([\w_]*\d[\w_]*)#",
                text($this->http->FindSingleNode(".", $root)), $m)) {
                $r->general()->confirmation($m[2], $m[1]);
            } else {
                $r->general()->noConfirmation();
            }
//            Reserviert für

            // PickupDatetime
            $pickupDatetime = strtotime(
                $this->normalizeDate($this->http->FindSingleNode(".//text()[{$this->eq($this->t('Pickup'))}]/ancestor::tr[1]/following-sibling::tr[2]",
                    $root)) . ', ' .
                $this->normalizeDate($this->http->FindSingleNode(".//text()[{$this->eq($this->t('Pickup'))}]/ancestor::tr[1]/following-sibling::tr[1]",
                    $root))
            );

            // PickupLocation
            $pickupLocation = $this->http->FindSingleNode(".//text()[{$this->eq($this->t('Pickup'))}]/ancestor::tr[1]/following-sibling::tr[3]",
                $root);

            // PickupPhone
            // PickupFax
            // PickupHours
            $pickupHours = $this->http->FindSingleNode(".//text()[{$this->eq($this->t('Pickup'))}]/ancestor::tr[1]/following-sibling::tr[position()>3 and position()=last()]",
                $root);

            $r->pickup()
                ->date($pickupDatetime)
                ->location($pickupLocation)
                ->openingHours($pickupHours, false, true);

            // DropoffDatetime
            $dropOffDatetime = strtotime(
                $this->normalizeDate($this->http->FindSingleNode(".//text()[{$this->eq($this->t('Dropoff'))}]/ancestor::tr[1]/following-sibling::tr[2]",
                    $root)) . ', ' .
                $this->normalizeDate($this->http->FindSingleNode(".//text()[{$this->eq($this->t('Dropoff'))}]/ancestor::tr[1]/following-sibling::tr[1]",
                    $root))
            );

            // DropoffLocation
            $dropOffLocation = $this->http->FindSingleNode(".//text()[{$this->eq($this->t('Dropoff'))}]/ancestor::tr[1]/following-sibling::tr[3]",
                $root);

            // DropoffPhone
            // DropoffFax
            // DropoffHours
            $dropOffHours = $this->http->FindSingleNode(".//text()[{$this->eq($this->t('Dropoff'))}]/ancestor::tr[1]/following-sibling::tr[position()>3 and position()=last()]",
                $root);

            $r->dropoff()
                ->date($dropOffDatetime)
                ->location($dropOffLocation)
                ->openingHours($dropOffHours, false, true);

            // CarType
            $carType = $this->http->FindSingleNode(".//img[contains(@src, 'cars/logos')]/ancestor::td[1]/following-sibling::td[1]//tr[1]",
                $root);

            if (!$carType) {
                $carType = $this->http->FindSingleNode(".//tr[contains(normalize-space(.), 'Reserved for') and count(descendant::td)=2]/preceding-sibling::tr[1]/descendant::td[1]/following-sibling::td[1]//tr[1]", $root);
            }

            // CarModel
            $carModel = $this->http->FindSingleNode(".//img[contains(@src, 'cars/logos')]/ancestor::td[1]/following-sibling::td[1]//tr[2]",
                $root);

            if (!$carModel) {
                $carModel = $this->http->FindSingleNode(".//tr[contains(normalize-space(.), 'Reserved for') and count(descendant::td)=2]/preceding-sibling::tr[1]/descendant::td[1]/following-sibling::td[1]//tr[2]", $root);
            }

            $r->car()
                ->model($carModel)
                ->type($carType);

            // CarImageUrl
            // RenterName
            $r->general()->traveller($this->http->FindSingleNode(".//text()[{$this->starts($this->t('Reserved'))}]/ancestor-or-self::td[1]/following-sibling::td[1]",
                $root));

            // RentalCompany
            // Status
            // Cancelled
            $rentalCompany = $this->http->FindSingleNode(".//text()[{$this->starts($this->t('Status'))}]/ancestor::tr[2]/descendant::text()[normalize-space(.)!=''][1]",
                $root);

            if (!empty($this->http->FindSingleNode("//text()[{$this->contains($this->t('carCancelled'))}]"))) {
                $r->general()->cancelled();
                $r->general()->status('Cancelled');
            }

            if (!empty($rentalCompany)) {
                $r->extra()->company($rentalCompany);

                foreach ($this->rentalProviders as $code => $detects) {
                    foreach ($detects as $detect) {
                        if (false !== stripos($rentalCompany, $detect)) {
                            $r->program()->code($code);
                            $flagCode = true;

                            break 2;
                        }
                    }
                }

                if (!isset($flagCode)) {
                    //$r->program()->keyword($rentalCompany);
                }
            }

            $node = $this->http->FindSingleNode(".//text()[{$this->starts($this->t('For specific rental questions, contact the car agency at'))}]/following::text()[normalize-space(.)!=''][1]",
                $root);

            if (preg_match_all("/([+(\d][-. \d)(\/]{5,}[\d)])\s*\((.+?)\)/", $node, $m, PREG_SET_ORDER)) {
                // (33)0825352352 (reservation)    |    0825/81 00 81 (direct)
                $addedPhones = [];

                foreach ($m as $v) {
                    $num = preg_replace(["#\s*\(\s*#", "#\s*\)\s*#"], ['(', ')'], $v[1]);

                    if (!in_array($num, $addedPhones)) {
                        $r->program()->phone($num, $v[2]);
                        $addedPhones[] = $num;
                    }
                }
            }
        }
    }

    private function transfer(Email $email): void
    {
        // examples: it-89827224.eml, it-355662927.eml

        $xpath = "//*[(self::p or self::tr) and {$this->eq($this->t('Ride Details'))}]/following::text()[ {$this->eq($this->t('Pickup'))} and following::text()[{$this->eq($this->t('Dropoff'))}] ]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length === 0) {
            $this->logger->debug("segments root (transfer) not found: $xpath");

            return;
        } elseif ($nodes->length > 0) {
            $this->logger->debug('Segments for transfer found by: ' . $xpath);
            $t = $email->add()->transfer();
            $t->general()->noConfirmation();
        }

        $dateStartValue = $this->http->FindSingleNode("//tr[{$this->starts($this->t('Itinerary #'))}]/following::tr[normalize-space()][1]", null, true, "/^(.{6,}?)[ ]+-[ ]+.{6,}$/")
            ?? $this->http->FindSingleNode("//text()[{$this->eq($this->t('Itinerary #'))}]/following::text()[normalize-space()][2][contains(.,'-')]", null, true, "/^(.{6,}?)[ ]+-[ ]+.{6,}$/")
        ;
        $dateStart = strtotime($dateStartValue);

        $passengerCount = null;
        $reservedFor = $this->http->FindSingleNode("//tr[{$this->eq($this->t('Reserved for'))}]/following::tr[normalize-space()][1]")
            ?? $this->http->FindSingleNode("//p[{$this->eq($this->t('Traveler Details'))}]/following::p[normalize-space()][1]")
        ;

        if (preg_match("/^({$this->patterns['travellerName']})[ ]*,[ ]*(\d{1,3})[ ]*(?i){$this->opt($this->t('passenger'))}/u", $reservedFor, $m)) {
            // Yang Du, 4 passengers
            $t->general()->traveller($m[1]);
            $passengerCount = $m[2];
        }

        foreach ($nodes as $key => $root) {
            $s = $t->addSegment();

            $transferText = $this->http->FindSingleNode('.', $root);

            $followNodes = $this->http->XPath->query("following::text()[string-length(normalize-space())>2 and following::text()[{$this->eq($this->t('Your ride'))}]][normalize-space()]", $root);

            foreach ($followNodes as $fNode) {
                if (
//                    !empty($nodes[$key + 1]) && $nodes[$key + 1] === $fNode
//                    || $this->http->XPath->query("self::node()[{$this->eq($this->t('Flight'))}]", $fNode)->length > 0
//                    ||
                $this->http->XPath->query("self::node()[{$this->eq($this->t('Pick-up'))}]", $fNode)->length > 0
                ) {
                    break;
                }
                $transferText .= "\n" . $this->http->FindSingleNode('.', $fNode);
            }

            /*
                Mon, May 31
                Hyatt Regency Waikiki Beach Resort & Spa
            */
            $pattern = "/^(?<date>.{0,20}\d.{0,15}?)(?:\n+|[ ]+[-–]+[ ]+)(?<address>.{3,})$/";

            /*
                Hyatt Regency Waikiki Beach Resort & Spa
                Mon, May 31
            */
            $pattern2 = "/^(?<address>.{3,}?)(?:\n+|[ ]+[-–]+[ ]+)(?<date>.{0,20}\d.{0,15})$/";

            /*
                Pick-up
                Sat, May 22
                Honolulu, HI, United States of America (HNL-Daniel K. Inouye Intl.)
                Drop-off
                Hyatt Regency Waikiki Beach Resort & Spa
            */

            $fDate = $dTime = $aTime = null;
            $transferText = str_replace("[Pick-up Date]", '', $transferText);

            if (preg_match("/(.+?)(\nFlight\n.+)/s", $transferText, $m)) {
                $transferText = $m[1];
                /*
                    Flight
                    DL 644, Delta - Wed, Nov 16
                    Flight Arrival
                    10:30am arrival
                */
                if (preg_match("/\nFlight\n.*\d.*?[ ]+-[ ]+(.+)/", $m[2], $mf)
                    || preg_match("/\nFlight\n[ ]*{$this->patterns['time']}.*\n.*\d.*?[ ]+-[ ]+(.+)/", $m[2], $mf)
                ) {
                    $fDate = $mf[1];
                }

                if (preg_match("/\n\s*({$this->patterns['time']})\s+{$this->opt($this->t('arrival'))}/", $m[2], $mf)) {
                    $dTime = $mf[1];
                } elseif (preg_match("/\n\s*({$this->patterns['time']})\s+{$this->opt($this->t('departure'))}/", $m[2], $mf)) {
                    $aTime = $mf[1];
                }
            }

            if (preg_match("/^{$this->opt($this->t('Pickup'))}\n+(?<pickUp>[\s\S]+)\n+{$this->opt($this->t('Dropoff'))}\n+(?<dropOff>[\s\S]+)$/", $transferText, $m)) {
                if (preg_match($pattern, $m['pickUp'], $m2) || preg_match($pattern2, $m['pickUp'], $m2)) {
                    $dateDep = null;

                    if (!preg_match("/\d{4}$/", $m2['date']) && $dateStart) {
                        $dateDep = EmailDateHelper::parseDateRelative($m2['date'], $dateStart, true, '%D% %Y%');
                    } else {
                        $dateDep = strtotime($m2['date']);
                    }

                    if (empty($dateDep) && !empty($fDate)) {
                        $dateDep = EmailDateHelper::parseDateRelative($fDate, $dateStart, true, '%D% %Y%');
                    }

                    if (!empty($dTime) && !empty($dateDep)) {
                        $s->departure()->date(strtotime($dTime, $dateDep));
                    } elseif (empty($dTime)) {
                        $s->departure()
                            ->noDate();
                    }

                    if (preg_match("/^.+\([ ]*([A-Z]{3})[-–]+.+\)\s*$/", $m2['address'], $code)) {
                        $s->departure()
                            ->code($code[1])
                            ->name($m2['address'])
                        ;
                    } elseif (preg_match("/\b(?:Resort|Hotel)\b/", $m2['address']) || !preg_match("/\d+/", $m2['address'])) {
                        $s->departure()
                            ->name($m2['address']);
                    } else {
                        $s->departure()->address($m2['address']);
                    }
                } else {
                    if (preg_match("/^.+\(([A-Z]{3})-.+\)\s*$/", $m['pickUp'], $code)) {
                        $s->departure()
                            ->code($code[1])
                            ->name($m['pickUp'])
                        ;
                    } elseif (preg_match("/\b(?:Resort|Hotel)\b/", $m['pickUp']) || !preg_match("/\d+/", $m['pickUp'])) {
                        $s->departure()
                            ->name($m['pickUp']);
                    } else {
                        $s->departure()->address($m['pickUp']);
                    }

                    $s->departure()
                        ->noDate();
                }

                if (preg_match($pattern, $m['dropOff'], $m2) || preg_match($pattern2, $m['dropOff'], $m2)) {
                    if (!preg_match("/\d{4}$/", $m2['date']) && $dateStart) {
                        $dateArr = EmailDateHelper::parseDateRelative($m2['date'], $dateStart, true, '%D% %Y%');
                        $s->arrival()->date($dateArr);
                    } else {
                        $s->arrival()->date2($m2['date']);
                    }

                    if (!empty($aTime) && !empty($s->getArrDate())) {
                        $s->arrival()->date(strtotime($aTime, $s->getArrDate()));
                    }

                    if (preg_match("/^.+\(([A-Z]{3})-.+\)\s*$/", $m2['address'], $code)) {
                        $s->arrival()
                            ->code($code[1])
                            ->name($m2['address'])
                        ;
                    } elseif (preg_match("/\b(?:Resort|Hotel)\b/", $m2['address']) || !preg_match("/\d+/", $m2['address'])) {
                        $s->arrival()
                            ->name($m2['address']);
                    } else {
                        $s->arrival()->address($m2['address']);
                    }
                } else {
                    if (preg_match("/^.+\(([A-Z]{3})-.+\)\s*$/", $m['dropOff'], $code)) {
                        $s->arrival()
                            ->code($code[1])
                            ->name($m['dropOff'])
                        ;
                    } elseif (preg_match("/\b(?:Resort|Hotel)\b/", $m['dropOff']) || !preg_match("/\d+/", $m['dropOff'])) {
                        $s->arrival()
                            ->name($m['dropOff']);
                    } else {
                        $s->arrival()->address($m['dropOff']);
                    }

                    if (!empty($aTime) && !empty($fDate) && $dateStart) {
                        $dateArr = EmailDateHelper::parseDateRelative($fDate, $dateStart, true, '%D% %Y%');
                        $s->arrival()->date(strtotime($aTime, $dateArr));
                    } else {
                        $s->arrival()
                            ->noDate();
                    }
                }
            }

            if ($passengerCount !== null) {
                $s->extra()->adults($passengerCount);
            }
        }

        /* Junk by this, so not remake on objects
####################
###   TRANSFER   ###
####################

$xpath = "//*[".$this->rule("Transfer")."]/ancestor::table[1]/..";
$nodes = $this->http->XPath->query($xpath);
if($nodes->length == 0){
    $this->http->Log("segments root not found: $xpath", LOG_LEVEL_NORMAL);
}

foreach($nodes as $root){
    $it = [];

    $it['Kind'] = "T";
    // RecordLocator
    $it['RecordLocator'] = $this->orval(
        $this->http->FindSingleNode("//*[".$this->rule("Supplier Reference Number")."]/ancestor-or-self::td[1]/following::td[1]"),
        reni("(?:".$this->opts('Itinerary').")\s+(?:\#\s+|)([\w_]*\d[\w_]*)", $text)
    );
    if(!$it['RecordLocator'])
        continue;

    // TripNumber
    $it['TripNumber'] = reni("(?:".$this->opts('Itinerary').")\s+(?:\#\s+|)([\w_]*\d[\w_]*)", $text);

    // Passengers
    $it['Passengers'] = $this->orval(
        $this->http->FindNodes(".//*[".$this->rule('Reserved')."]/ancestor::td[1]/following-sibling::td[last()]/descendant::text()[normalize-space(.)][1]", $root),
        $this->http->FindNodes(".//*[".$this->rule('Reserved')."]/ancestor::tr[1]/following-sibling::tr[1]/td[1]/*[1]", $root)
    );
    if (!empty($it['Passengers'])) {
        $it['Passengers'] = array_unique($it['Passengers']);
    }


    $it['TripCategory'] = TRIP_CATEGORY_TRANSFER;


    if($date = $this->http->FindSingleNode("./ancestor::tr[1]/preceding-sibling::tr[1]", $root, true, "#Valid Date\s*:\s*(.*?),#")){
        $itsegment = [];

        // DepCode
        $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;

        // DepName
        $str = $this->http->FindSingleNode("./ancestor::tr[1]/preceding-sibling::tr[1]", $root, true, "#^.*?:\s*(.*?)(?:\:|\-)#");
        if (preg_match("#(.+)\s+\(([A-Z]{3})\)#", $str, $m)) {
            $itsegment['DepName'] = $m[1];
            $itsegment['DepCode'] = $m[2];
        } else
            $itsegment['DepName'] = $this->http->FindSingleNode("./ancestor::tr[1]/preceding-sibling::tr[1]", $root, true, "#^.*?:\s*(.*?)(?:\:|\-)#");

        // DepDate
        $itsegment['DepDate'] = strtotime($date);

        // ArrCode
        $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;

        // ArrName
        $itsegment['ArrName'] = $this->http->FindSingleNode("./ancestor::tr[1]/preceding-sibling::tr[1]//tr[2]/preceding-sibling::tr[1]/td[1]", $root, true, "#Roundtrip for\s+(.+)#");
        if (empty($itsegment['ArrName']))
            $itsegment['ArrName'] = $this->http->FindSingleNode("./ancestor::tr[1]/preceding-sibling::tr[1]//tr[2]/preceding-sibling::tr[1]/td[1]", $root, true, "#to\s+(.+)#");

        // ArrDate
        $itsegment['ArrDate'] = MISSING_DATE;

        $it['TripSegments'][] = $itsegment;

    }

    if($date = $this->http->FindSingleNode("//*[".$this->rule("Arrival Date / Time")."]/following-sibling::td[1]")){
        $itsegment = [];

        // DepCode
        $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;

        // DepName
        $itsegment['DepName'] = re("#^(.*?)\s+-\s+.*?$#", $this->http->FindSingleNode("(//*[".$this->rule("Transfer")."]/ancestor::td[1]//text()[normalize-space(.)])[last()]"));

        // DepDate
        $itsegment['DepDate'] = strtotime($date);

        // ArrCode
        $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;

        // ArrName
        $itsegment['ArrName'] = re("#^.*?\s+-\s+(.*?)$#", $this->http->FindSingleNode("(//*[".$this->rule("Transfer")."]/ancestor::td[1]//text()[normalize-space(.)])[last()]"));

        // ArrDate
        $itsegment['ArrDate'] = MISSING_DATE;

        $it['TripSegments'][] = $itsegment;
    }
    if($date = $this->http->FindSingleNode("//td[".$this->rule("Departure Date / Time")."]/following-sibling::td[1]")){
        $itsegment = [];

        // DepCode
        $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;

        // DepName
        $itsegment['DepName'] = re("#^(.*?)\s+-\s+.*?$#", $this->http->FindSingleNode("(//*[".$this->rule("Transfer")."]/ancestor::td[1]//text()[normalize-space(.)])[last()]"));

        // DepDate
        $itsegment['DepDate'] = MISSING_DATE;

        // ArrCode
        $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;

        // ArrName
        $itsegment['ArrName'] = re("#^.*?\s+-\s+(.*?)$#", $this->http->FindSingleNode("(//*[".$this->rule("Transfer")."]/ancestor::td[1]//text()[normalize-space(.)])[last()]"));

        // ArrDate
        $itsegment['ArrDate'] = strtotime($date);

        $it['TripSegments'][] = $itsegment;
    }

    $itineraries[] = $it;
}
*/
    }

    /**
     * @throws \AwardWallet\Schema\Parser\Component\InvalidDataException
     */
    private function event(Email $email): void
    {
        $xpath = "//*[{$this->eq(['View/print vouchers', 'View/print vouchers.', 'Contact Information'])}]/ancestor::table[1][ not({$this->contains('Hotel Pickup')}) or descendant::tr[{$this->eq($this->t('Where to meet'))}] ]";

        $this->logger->warning($xpath);
        $this->logger->warning("./preceding::td[{$this->hotelHeaderRule[$this->code]}][1]/descendant::text()[normalize-space(.)!=''][1]");

        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length === 0) {
            $this->logger->debug("segments root not found: $xpath");
        }

        foreach ($nodes as $root) {
            $cancelled = false;
            $time = '';
            // Name
            // Address

            $name = $this->http->FindSingleNode("./preceding::td[{$this->hotelHeaderRule[$this->code]}][1]/descendant::text()[normalize-space(.)!=''][1]",
                    $root);

            $DateXpath = "[{$this->hotelHeaderRule[$this->code]}][1]//text()[normalize-space(.)='Valid Date:' or normalize-space(.)='Valid Dates:']/following::text()[normalize-space(.)][1]";

            $date = strtotime($this->normalizeDate($this->http->FindSingleNode("./preceding::td{$DateXpath}", $root,
                true, "#^(\w+\s+\d+\s*,\s*\d{4}|\d+\s+\w+\s*[,\.]?\s*\d{4})#")));

            if (empty($date)) {
                $date = strtotime($this->normalizeDate($this->http->FindSingleNode("./following::td{$DateXpath}", $root,
                    true, "#^(\w+\s+\d+\s*,\s*\d{4})#")));

                if (empty($date)) {
                    $date = strtotime($this->normalizeDate($this->http->FindSingleNode("./preceding::tr[1]", $root,
                        true, "#{$this->opt($this->t('Valid Date:'))}\s*(\w+\s*\d+\,\s*\d{4})#s")));

                    $time = $this->http->FindSingleNode("./preceding::tr[1][{$this->contains($this->t('Valid Date:'))}]/preceding::tr[1]", $root, true, "/\s+([\d\:]+\s*A?P?M)\,/");
                }

                $name2 = $this->http->FindSingleNode("./following::td[{$this->hotelHeaderRule[$this->code]}][1]/descendant::text()[normalize-space(.)!=''][1]",
                    $root, true, '/(.+)\s*:\s*\d+/');
            }

            if (isset($name2) && !empty($name2)) {
                $name = $name2;
            }

            if (preg_match('/\:[ ]*(\d{1,2}:\d{2} [AP]M)/', $name, $m)) {
                $date = strtotime($m[1], $date);
            } elseif (!empty($time)) {
                $date = strtotime($time, $date);
            }

            if ($this->http->XPath->query("//text()[contains(normalize-space(), 'This reservation has been fully cancelled.')]")->length > 0) {
                $cancelled = true;
            } elseif (empty($date)) {
                $this->logger->debug("Skip Event: no start date");

                continue;
            }

            $e = $email->add()->event();
            $e->general()->noConfirmation();

            if ($cancelled == true) {
                $e->general()->cancelled();
            }

            $address = $this->http->FindSingleNode("descendant::tr[{$this->eq($this->t('Where to meet'))}]/following-sibling::tr[normalize-space()][1][not(descendant::*[self::b or self::strong or self::h3])]", $root) ?? $name; //??? not sure, that it's good

            $e->place()
                ->name($name)
                ->address($address);

            if (!empty($date)) {
                $e->booked()->start($date);
            } elseif ($cancelled == true && empty($date)) {
                $e->booked()->noStart();
            }

            // EndDate
            if ($date = $this->http->FindSingleNode("./preceding::td[{$this->hotelHeaderRule[$this->code]}][1]//text()[normalize-space(.)='Valid Dates:']/following::text()[normalize-space(.)!=''][1]",
                $root, true, "#^\w+\s+\d+\s*,\s*\d{4}\s*-\s*(\w+\s+\d+\s*,\s*\d{4})#")
            ) {
                $e->booked()->end(strtotime($this->normalizeDate($date)));
            } else {
                $e->booked()->noEnd();
            }
            $e->place()->type(Event::TYPE_EVENT); //??? TODO xz

            // Phone
            // DinerName
            $pax = $this->http->FindSingleNode(".//text()[{$this->starts($this->t('Reserved'))}]/ancestor::td[1]/following-sibling::td[1]/descendant::text()[normalize-space(.)!=''][1]", $root);

            if (!empty($pax)) {
                $e->general()->traveller($pax);
            }

            // Guests
            $counts = $this->http->FindSingleNode(".//text()[{$this->starts($this->t('Reserved'))}]/ancestor::td[1]/following-sibling::td[1]/descendant::text()[normalize-space(.)!=''][2]",
                $root, true, "#\d+#");

            if (!empty($counts)) {
                $e->setGuestCount($counts);
            }
            // TotalCharge
            // Currency
            $totalPrice = $this->http->FindSingleNode("parent::td/following-sibling::td[1]/descendant::text()[{$this->starts($this->t('Total'))}]/following::text()[normalize-space()][1]", $root);

            if ($totalPrice !== null && !empty($tot = $this->getTotalCurrency($totalPrice))) {
                $e->price()
                    ->total($tot['Total'])
                    ->currency($tot['Currency']);
            }

            $node = $this->http->FindSingleNode("./preceding::td[{$this->hotelHeaderRule[$this->code]}][1]//text()[{$this->starts($this->t('Status'))}]",
                $root);

            if (empty($node)) {
                $node = $this->http->FindSingleNode("./following::td[{$this->hotelHeaderRule[$this->code]}][1]//text()[{$this->starts($this->t('Status'))}]",
                    $root);
            }

            if (!empty($node)) {
                $e->general()->status($node);

                if ($node == 'CANCELLED') {
                    $e->general()
                        ->cancelled();
                }
            }

            $cancellation = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Cancellations and Changes:'))}]/following::text()[normalize-space()][1][not(ancestor::*[self::b or self::strong or self::h3])]", $root);

            if ($cancellation) {
                $e->general()->cancellation($cancellation);
            }
        }
    }

    /**
     * @throws \AwardWallet\Schema\Parser\Component\InvalidDataException
     */
    private function parseEmail(Email $email): void
    {
        if (count($this->http->FindNodes("//text()[{$this->contains($this->t("Duration"))}]")) > 0) {
            $this->flight($email);
        }
        $this->hotel($email);
        $this->car($email);
        $this->event($email);
        $this->transfer($email);
        //TODO for orbitz&travelocity - no examples
        switch ($this->code) {
            case 'expedia':
                $earnedAwards = $this->http->FindSingleNode("//*[
					normalize-space(text())='Expedia+' or
					normalize-space(text())='Expedia +rewards' or
					normalize-space(text())='Expedia+ rewards' or
					normalize-space(text())='Expedia Rewards'
				]/ancestor::tr[1]/following-sibling::tr[1]", null, true, "#{$this->t('Points')}#");

                if (empty($earnedAwards)) {
                    $earnedAwards = $this->http->FindSingleNode("//text()
                        [contains(.,'For this trip')]
                        [ancestor::td[1]/following-sibling::td[.//img[contains(@src,'/rewards/') or contains(@alt,'Expedia')]]]
                    /preceding::text()[normalize-space()!=''][1]", null, true, "#{$this->t('Points')}#");
                }

                break;

            case 'cheaptickets':
                $earnedAwards = $this->http->FindSingleNode("//*[
					normalize-space(text())='CheapCash'
				]/ancestor::tr[1]/following-sibling::tr[1]", null, true, "#{$this->t('Points')}#");

                break;

            default:
                $earnedAwards = null;
        }

        $email->ota()->earnedAwards($earnedAwards, false, true);

        $payment = $this->http->FindSingleNode("(//tr/*[{$this->starts($this->t('Total'))} and not({$this->contains($this->t('Duration'))})]/following-sibling::*//text()[string-length(normalize-space())>1][1])[1]");

        if (empty($payment)) {
            $payment = $this->http->FindSingleNode("(//text()[({$this->starts($this->t('Total'))}) and not({$this->contains($this->t('Duration'))})]/ancestor::td[1])[1]", null, false, '/^.*\d.*$/');
        }

        if (empty($payment)) {
            $payment = $this->http->FindSingleNode("(//text()[({$this->starts($this->t('Total'))}) and not({$this->contains($this->t('Duration'))})]/ancestor::td[1])[2]", null, false, '/^.*\d.*$/');
        }

        if (empty($payment)) {
            $payment = $this->http->FindSingleNode("(//text()[({$this->starts($this->t('Total'))}) and not({$this->contains($this->t('Duration'))})]/following::text()[normalize-space()][1])[1]", null, true, '/^.*\d.*$/');
        }

        if (empty($payment)) {
            $payment = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Total'))}]/following::text()[normalize-space()][1]", null, true, '/^.*\d.*$/');
        }

        if (preg_match("#:\s*(.+)#", $payment, $m) || preg_match("#{$this->opt($this->t('Total'))}\b[\s:：]*(.+)#u", $payment, $m)) {
            $payment = $m[1];
        }

        if ($payment !== null) {
            $spent = $this->http->FindSingleNode("//tr/*[{$this->contains($this->t('points used'))} and count(following-sibling::*[normalize-space()])=1]", null, true, "/^\d[,.‘\'\d ]*[ ]*Expedia Rewards points?/iu"); // it-89827224.eml

            if (preg_match("/\s*(\d[,.‘\'\d ]*PTS)\s+(?:and\s+)?(.+)/iu", $payment, $m)) {
                // Total: 12,573 PTS and $0.00
                $spent = $m[1];
                $payment = $m[2];
            }
            $tot = $this->getTotalCurrency($payment);

            if ($tot['Total'] !== null) {
                $cost = (float) $tot["Total"];
                $currency = $tot['Currency'];
            }

            if (isset($cost) && !empty($currency)) {
                if (count($email->getItineraries()) === 1) {
                    $p = explode('\\', get_class($email->getItineraries()[0]));
                    $type = strtolower(array_pop($p));

                    if ($type === 'flight') {
                        if (count($email->getItineraries()[0]->getTravellers()) === 1) {
                            $tot = $this->getTotalCurrency($this->http->FindSingleNode("//text()[{$this->eq($this->t('Flight'))}]/following::text()[string-length(normalize-space(.))>2][1]"));

                            if ($tot['Total'] !== null) {
                                $email->getItineraries()[0]->price()
                                    ->cost($tot['Total']);
                            }
                            $tot = $this->getTotalCurrency($this->http->FindSingleNode("//text()[{$this->starts($this->t('Taxes & Fees'))}]/following::text()[string-length(normalize-space(.))>2][1]"));

                            if ($tot['Total'] !== null) {
                                $email->getItineraries()[0]->price()
                                    ->tax($tot['Total']);
                            }
                        }
                    }
                    $email->getItineraries()[0]->price()
                        ->total($cost)
                        ->currency($currency);

                    if (isset($spent)) {
                        $email->getItineraries()[0]->price()->spentAwards($spent);
                    }
                }

                if (empty($email->getItineraries()[0]) || empty($email->getItineraries()[0]->getPrice())
                    || !($email->getItineraries()[0]->getPrice()->getTotal() === $cost && $email->getItineraries()[0]->getPrice()->getCurrencyCode() === $currency)
                ) {
                    $email->price()
                        ->total($cost)
                        ->currency($currency);
                }

                if (isset($spent)) {
                    $email->price()->spentAwards($spent);
                }

                $tot = $this->getTotalCurrency($this->http->FindSingleNode("//text()[({$this->starts($this->ProgramName[$this->code])}) and ({$this->contains($this->t('Booking Fee'))})]/following::text()[string-length(normalize-space(.))>2][1]"));

                if (!empty((float) $tot['Total'])) {
                    $email->price()
                        ->fee($this->http->FindSingleNode("//text()[({$this->starts($this->ProgramName[$this->code])}) and ({$this->contains($this->t('Booking Fee'))})]"),
                            $tot['Total']);
                }
            }
        }
    }

    private function assignLang(): bool
    {
        if (isset($this->detectLang) && !empty($this->code) && isset($this->detectLang[$this->code])) {
            $detectLang = $this->detectLang[$this->code];
            $body = $this->http->Response['body'];

            foreach ($detectLang as $lang => $reBody) {
                $reBody = (array) $reBody;

                foreach ($reBody as $re) {
                    if (strpos($body, $re) !== false || $this->http->XPath->query("//*[contains(normalize-space(),\"" . $re . "\")]")->length > 0) {
                        $this->lang = $lang;

                        return true;
                    }
                }
            }
        }

        return false;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dictionary[$this->lang][$s])) {
            return $s;
        }

        return self::$dictionary[$this->lang][$s];
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function normalizeDate($str): string
    {
//        $this->logger->debug("DATE: {$str}");
        $str = str_replace("​", "", $str);

        if (preg_match("#^(\d{4})[\/-](\d+)[\/-](\d+)$#", $str)) {
            return str_replace("/", "-", $str);
        } elseif (preg_match("#(?:^|\D)(\d+[./-]\d+[./-]\d+)(?:$|\D)#", $str, $m)) {
            $str = $this->normalizeDecDate($m[1]);
            $str = preg_replace("#^(\d{1,2})\.(\d{1,2})\.(\d{2})$#", '$1.$2.20$3', $str);
        } else {
            $in = [
                "#^.*?([^\d\W]+)\/(\d+)\/(\d+)$#",
                //1
                "#^.*?(\d+)/([^\d\W]+)/(\d{4})$#",
                //2
                "#^.*?(\d+)/([^\d\W]+)/(\d{4}).*?$#",
                //3
                '/^\S{2,4}\s+(\d{4})\/([^\d\W]{3,})\/(\d+).*/us',
                //4    må 2014/apr/21
                '/^(?:[^\d\W]{2,}\s+)?(\d{1,2})\.?\s+([^\d\W]{3,})\.?\s+(\d{4}).*/us',
                //7    6. Mär. 2015    |    5 Mar 2018    |    Sun 9 Nov 2014
                '/^(\d{4})\/([^\W\d]+)\/(\d{1,2})\s+[^\W\d]+.*/us',
                //8    2013/Nov/10 Sun
                "#^(\d{4})년\s+(\d+)월\s+(\d+)일$#",
                //9
                "#^(\d+)\s+h\s+(\d+)$#",
                //10
                '/^\s*(\d{4})\s*年\s*(\d+)\s*月\s*(\d+)\s*日.*/s',
                //11    2017年1月21日
                "#^[^\d\s]+\.\s+(\d+)/([^\d\s]+)\./(\d{4})$#",
                //12
                "#^​(\w+)\s+(\d+)[^\d\w]+​,[^\d\w]+(\d{3})\s+​(\d)[^\d\w]*​#",
                //13
                '/^\w{2,}\s+(\w{3,})\/(\d+)\/(\d{4}).*/s',
                //14    Sun Nov/10/2013
                "#^[^\d\s]+\.\s+(\d+)/([^\d\s]+)/(\d{4})$#",
                //15
                '#^\w{3}\s+(\w+)/(\d+)/(\d{4})$#',
                // 16    Tue Aprel/1/2014
                '/^(\d{4})년\s+(\d{1,2})월\s+(\d{1,2})일.*/s',
                // 17    2015년 12월 29일
                '#^(\d+)\s+de\s+([^\d\s]+)\s+de\s+(\d{4})$#',
                // 18    22 de maio de 2014
                '/^(\d{1,2})\s+([^\d\W]{3,})[,.]+\s+(\d{4}).*/s',
                // 19    8 juil. 2016    |    8 juil., 2016    |    8 juil, 2016
                '#^([^\d\s]+)\s+(\d+),\s+(\d{4})(?:\s*,.+)?$#',
                // 20    Sep 5​, 201​6​
                '#^(\d+)\.\s+([^\d\s]+)\s+(\d{4})$#',
                //22    1. Okt 2016
                '/^(\d{1,2}) (\w+),\s*(\d{2,4})$/',
                //23    23 Jul,2018
                '/^(\d{1,2}:\d{2})[ ]*Uhr$/i',
                // 24 20:00 Uhr
                "/^([[:alpha:]]+)[.\s]+(\d{1,2})\,\s*(\d{4})$/ui",
            ];
            $out = [
                "$2 $1 $3", //1
                "$1 $2 $3", //2
                "$1 $2 $3", //3
                "$3 $2 $1", //4
                "$1 $2 $3", //7
                "$3 $2 $1", //8
                "$3.$2.$1", //9
                "$1:$2", //10
                "$3.$2.$1", //11
                "$1 $2 $3", //12
                "$2 $1 $3$4", //13
                "$2 $1 $3", //14
                "$1 $2 $3", //15
                "$2 $1 $3", //16
                "$3.$2.$1", //17
                "$1 $2 $3", //18
                "$1 $2 $3", //19
                "$2 $1 $3", //20
                "$1 $2 $3", //22
                '$1 $2 $3', //23
                '$1', //24
                '$2 $1 $3',
            ];

            $str = preg_replace($in, $out, trim($str));

            if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
                if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                    $str = str_replace($m[1], $en, $str);
                }
            }
        }

        return $str;
    }

    private function normalizeDecDate($str)
    {
        if (!isset($this->decPattern)) {
            $cur = explode('|', str_replace(['.', '/', '-'], ['|', '|', '|'], $str));

            // check count dates in this text
            preg_match_all("#(?:^|\D)(\d+[./-]\d+[./-]\d+)(?:$|\D)#",
                $this->http->FindSingleNode("(//text()[contains(., '{$str}')])[1]"), $m);

            if (count($m[1]) == 2) {
                $next = $m[1][1];
            } else {
                // find next decDate text
                $transfrom = '1234567890./-';
                $transto = 'dddddddddd|||';
                $next = $this->http->FindSingleNode("(//text()[contains(., '{$str}')])[1]/following::text()[not(contains(translate(normalize-space(.), './-', '|||'), '{$cur[0]}|{$cur[1]}'))][
					contains(translate(normalize-space(.), '{$transfrom}', '{$transto}'), 'd|d|dd') or
					contains(translate(normalize-space(.), '{$transfrom}', '{$transto}'), 'd|dd|dd')
				][1]", null, true, "#(?:^|\D)(\d+[./-]\d+[./-]\d+)(?:$|\D)#");
            }

            if (empty($next)) {
                return $str;
            }
            $next = explode('|', str_replace(['.', '/', '-'], ['|', '|', '|'], $next));

            // compare dates
            $diff = [];

            for ($i = 0; $i < 3; $i++) {
                // if this number len 4 then year
                if (strlen($cur[$i]) == 4) {
                    $year = $i;

                    continue;
                }
                $diff[str_pad(abs($cur[$i] - $next[$i]), 2, '0', STR_PAD_LEFT) . (3 - $i)] = $i;
            }
            krsort($diff);

            // set pattern by diff
            $day = current($diff);
            $month = next($diff);
            // if exact year not found
            if (!isset($year) && count($diff) == 3) {
                $year = next($diff);
            }

            $this->decPattern = [$day, $month, $year];
        }

        return $this->dd2p($str);
    }

    private function dd2p($str)
    {
        $arr = explode('|', str_replace(['.', '/', '-'], ['|', '|', '|'], $str));
        $date = [];

        foreach ($this->decPattern as $key) {
            $date[] = $arr[$key];
        }

        return implode(".", $date);
    }

    private function normalizeTime($time)
    {
        $in = [
            "#^.*?\b(\d{1,2}(?::\d+)?)\s*([AP])(?:M)?\s*$#iu", //3 PM |3:00 PM | 1:35p
            "#^.*?\b(\d{1,2})\s*h\s*(\d{2})\s*$#u", //15h00,14 h 00
            "#^.*?\b(\d{1,2}:\d{2})\s*(h\s*)?$#u", //16:00, 14:00 h
            "#^.*?\b(\d{1,2})[\.:]*(\d{2})\s*(Uhr|uur)\s*$#u", //14.00 Uhr, 14.00 uur
            "#^.*?\b(\d{1,2})\.(\d{2})\s*$#u", //06.00
        ];
        $out = [
            "$1:00 $2m",
            "$1:$2",
            "$1",
            "$1:$2",
            "$1:$2",
        ];
        $time = preg_replace($in, $out, $time, -1, $count);

        if ($count > 0) {
            return $time;
        }

        return '';
    }

    private function htmlToText(?string $s, bool $brConvert = true): string
    {
        if (!is_string($s) || $s === '') {
            return '';
        }
        $s = str_replace("\r", '', $s);
        $s = preg_replace('/<!--.*?-->/s', '', $s); // comments

        if ($brConvert) {
            $s = preg_replace('/\s+/', ' ', $s);
            $s = preg_replace('/<[Bb][Rr]\b.*?\/?>/', "\n", $s); // only <br> tags
        }
        $s = preg_replace('/<[A-z][A-z\d:]*\b.*?\/?>/', '', $s); // opening tags
        $s = preg_replace('/<\/[A-z][A-z\d:]*\b[ ]*>/', '', $s); // closing tags
        $s = html_entity_decode($s);
        $s = str_replace(chr(194) . chr(160), ' ', $s); // NBSP to SPACE

        return trim($s);
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'normalize-space(' . $node . ')=' . $s;
        }, $field)) . ')';
    }

    private function starts($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'starts-with(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'contains(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function opt($field)
    {
        $field = (array) $field;

        return str_replace("#", "\\#", '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')');
    }

    private function getTotalCurrency($node): array
    {
        $tot = null;
        $cur = null;

        if (preg_match("/^\s*(?<currency>[^\d\s]\D{0,4}?)\s*(?<amount>\d[,.‘\'\d ]*)\s*$/u", $node, $m)
            || preg_match("/^\s*(?<amount>\d[,.‘\'\d ]*)\s*(?<currency>[^\d\s]\D{0,4}?)\s*$/u", $node, $m)
        ) {
            $currency = $this->currency($m['currency']);
            $currencyCode = preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null;
            $m['amount'] = str_replace(['‘', '’'], "'", $m['amount']); // 5‘422.79  ->  5'422.79
            $tot = PriceHelper::parse($m['amount'], $currencyCode);
            $cur = $currencyCode;
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }

    private function currency($s)
    {
        if ($code = $this->re("#^\s*([A-Z]{3})\s*$#", $s)) {
            return $code;
        }

        if (preg_match("#{$this->t('Currency')}#", $this->text, $m) && !empty($m[1])) {
            $c = $this->re("#^([A-Z]{3})$#", trim($m[1]));

            if (!empty($c)) {
                return $c;
            }
            $symLong = [
                'Norske kroner'     => 'NOK',
                'Svenska kronor'    => 'SEK',
                'Dollars canadiens' => 'CAD',
                'US dollars'        => 'USD',
                'Canadian dollars'  => 'CAD',
            ];

            foreach ($symLong as $f => $r) {
                if (trim($m[1]) == $f) {
                    return $r;
                }
            }
        }

        if ($this->http->XPath->query("//text()[contains(normalize-space(.),'Tous les prix affichés en')]/ancestor::tr[1][contains(normalize-space(.),'Dollars canadiens')]")->length > 0) {
            return 'CAD';
        }

        if (mb_strpos($this->text, '#料金の通貨単位はすべて日本円となります') !== false) {
            return 'JPY';
        }
        $sym = [
            'US$'   => 'USD',
            'MXN$'  => 'MXN',
            'HK$'   => 'HKD',
            'NT$'   => 'TWD',
            'AU$'   => 'AUD',
            'AU $'  => 'AUD',
            'R$'    => 'BRL',
            '円'     => 'JPY',
            '$C'    => 'CAD',
            'C$'    => 'CAD',
            '€'     => 'EUR',
            '$'     => 'USD',
            '£'     => 'GBP',
            '₩'     => 'KRW',
        ];

        foreach ($sym as $f => $r) {
            if ($s == $f) {
                return $r;
            }
        }

        if ($s == 'kr' && $this->lang == 'sv') {
            return 'SEK';
        }

        return null;
    }
}
