<?php

namespace AwardWallet\Engine\delta\RewardAvailability;

use AwardWallet\Common\Selenium\FingerprintFactory;
use AwardWallet\Common\Selenium\FingerprintRequest;
use AwardWallet\Engine\ProxyList;
use AwardWallet\Engine\Settings;
use Facebook\WebDriver\Exception\WebDriverException;
use Symfony\Component\HttpClient\Exception\TransportException;

class Parser extends \TAccountChecker
{
    use \SeleniumCheckerHelper;
    use ProxyList;
    public const ATTEMPTS_CNT = 5;

    private const CONFIGS = [
        'firefox-84' => [
            'browser-family'  => \SeleniumFinderRequest::BROWSER_FIREFOX,
            'browser-version' => \SeleniumFinderRequest::FIREFOX_84,
        ],
        'chrome-95' => [
            'browser-family'  => \SeleniumFinderRequest::BROWSER_CHROME,
            'browser-version' => \SeleniumFinderRequest::CHROME_95,
        ],
        'chrome-puppeteer-100' => [
            'browser-family'  => \SeleniumFinderRequest::BROWSER_CHROME_PUPPETEER,
            'browser-version' => \SeleniumFinderRequest::CHROME_PUPPETEER_100,
        ],
        'chrome-puppeteer-103' => [
            'browser-family'  => \SeleniumFinderRequest::BROWSER_CHROME_PUPPETEER,
            'browser-version' => \SeleniumFinderRequest::CHROME_PUPPETEER_103,
        ],
        //        // mac - если сильно будет падать рейт
        'chrome-94-mac' => [
            'browser-family'  => \SeleniumFinderRequest::BROWSER_CHROME,
            'browser-version' => \SeleniumFinderRequest::CHROME_94,
        ],
    ];

    private $bookingCodes;
    private $routeNotChecked;
    private $cacheKey;

    private $depFullName;
    private $arrFullName;
    private $depCountryCode;
    private $arrCountryCode;
    private $config;
    private $newSession;
    private $payload;
    private $fingerprint;

    public static function getRASearchLinks(): array
    {
        return ['https://www.delta.com/'=>'search page'];
    }

    public function InitBrowser()
    {
        parent::InitBrowser();

        $this->KeepState = false;
        $this->keepCookies(false); // bug pup103

        if ($this->attempt % 2 == 0) {
            $array = ['us', 'fi', 'uk', 'il'];
            $targeting = $array[array_rand($array)];
            $this->setProxyBrightData(null, Settings::RA_ZONE_STATIC, $targeting);
        } else {
            $array = ['us', 'jp', 'gb', 'de'];
            $targeting = $array[array_rand($array)];

            if ($targeting === 'us' && $this->AccountFields['ParseMode'] === 'awardwallet') {
                $this->setProxyMount();
            } else {
                $this->setProxyGoProxies(null, $targeting, null, null, 'https://www.delta.com');
            }
        }

        $this->http->setHttp2(true);
        $this->setConfig();

        $this->http->setUserAgent('Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/15.6.1 Safari/605.1.15');

        $request = (self::CONFIGS[$this->config]['browser-family'] === 'firefox')
            ? FingerprintRequest::firefox()
            : FingerprintRequest::chrome();

        $request->browserVersionMin = 100;
        $request->platform = (random_int(0, 1)) ? 'MacIntel' : 'Win32';
        $this->fingerprint = $this->services->get(FingerprintFactory::class)->getOne([$request]);
    }

    public function LoadLoginForm()
    {
        return true;
    }

    public function Login()
    {
        return true;
    }

    public function getRewardAvailabilitySettings()
    {
        return [
            'supportedCurrencies'      => ['USD'],
            'supportedDateFlexibility' => 0,
            'defaultCurrency'          => 'USD',
        ];
    }

    public function ParseRewardAvailability(array $fields)
    {
        $this->logger->info("Parse Reward Availability", ['Header' => 2]);
        $this->logger->debug("params: " . var_export($fields, true));

        $this->http->RetryCount = 0;

        if ($fields['DepDate'] > strtotime('+331 day')) {
            $this->SetWarning('too late');

            return [];
        }

        if (!$this->validRoute($fields)) {
            return ['routes' => []];
        }

        $this->http->RetryCount = 2;

        if ($fields['Currencies'][0] !== 'USD') {
            $fields['Currencies'][0] = $this->getRewardAvailabilitySettings()['defaultCurrency'];
            $this->logger->notice("parse with defaultCurrency: " . $fields['Currencies'][0]);
        }
        $this->bookingCodes = $this->getBookingCodes();
        $this->http->JsonLog(json_encode($this->bookingCodes));

        if ($this->http->Error === 'Network error 56 - Unexpected EOF') {
            $this->logger->debug('ignored previous error. it\'s work');
        }
        $cabinList = $this->GetCabinFields(false);
        $fields['brandId'] = $cabinList[$fields['Cabin']]['brandID'];

        $fields['DepDate'] = date("Y-m-d", $fields['DepDate']);

        try {
            $responses = $this->selenium($fields);
        } catch (WebDriverException $e) {
            $this->logger->error($e->getMessage());
            $this->logger->error($e->getTraceAsString());

            throw new \CheckRetryNeededException(5, 0);
        } catch (\Exception $e) {
            $this->logger->error('Exception: ' . $e->getMessage());

            if (strpos($e->getMessage(), 'session not created') !== false) {
                throw new \CheckRetryNeededException(5, 0);
            }

            throw $e;
        }

        if (!empty($this->ErrorMessage) && $responses === []) {
            return ["routes" => []];
        }

        return ["routes" => $this->ParseReward($fields, $responses)];
    }

    public function getUuid()
    {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            // 32 bits for "time_low"
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),

            // 16 bits for "time_mid"
            mt_rand(0, 0xffff),

            // 16 bits for "time_hi_and_version",
            // four most significant bits holds version number 4
            mt_rand(0, 0x0fff) | 0x4000,

            // 16 bits, 8 bits for "clk_seq_hi_res",
            // 8 bits for "clk_seq_low",
            // two most significant bits holds zero and one for variant DCE1.1
            mt_rand(0, 0x3fff) | 0x8000,

            // 48 bits for "node"
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }

    private function getPayloadCalendar(array $fields): array
    {
        $customers = [];

        for ($i = 1; $i <= (int) $fields['Adults']; $i++) {
            $customers[] = ['passengerTypeCode' => "ADT", 'passengerId' => (string) $i];
        }

        return [
            'query'     => $this->getQueryStringCalendar(),
            'variables' => [
                'offerSearchCriteria' => [
                    'productGroups' => [
                        'productCategoryCode' => 'FLIGHTS',
                    ],
                    'customers'      => $customers,
                    'offersCriteria' => [
                        'resultsPageNum'        => 4,
                        'pricingCriteria'       => ['priceableIn' => ['MILES']],
                        'preferences'           => ['nonStopOnly' => false, 'refundableOnly' => false],
                        'flightRequestCriteria' => [
                            'sortByBrandId'           => $fields['brandId'], //'BE',
                            'searchOriginDestination' => [
                                [
                                    'departureLocalTs'    => $fields['DepDate'] . 'T00:00:00',
                                    'destinations'        => [['airportCode' => $fields['ArrCode']]],
                                    'origins'             => [['airportCode' => $fields['DepCode']]],
                                    'calenderDateRequest' => ['daysBeforeCnt' => 3, 'daysAfterCnt' => 3],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    private function getQueryStringCalendar(): string
    {
        return 'query ($offerSearchCriteria: OfferSearchCriteriaInput!) {
  gqlSearchOffers(offerSearchCriteria: $offerSearchCriteria) {
    offerResponseId
    gqlOffersSets {
      offers {
        offerId
        additionalOfferProperties {
          offered
          soldOut
          lowestFare
          totalTripStopCnt
        }
        offerPricing {
          discountsApplied {
            code
            pct
          }
          originalTotalAmt {
            currencyEquivalentPrice {
              currencyAmt
              roundedCurrencyAmt
            }
            milesEquivalentPrice {
              mileCnt
            }
          }
          totalAmt {
            currencyEquivalentPrice {
              currencyAmt
              roundedCurrencyAmt
            }
            milesEquivalentPrice {
              mileCnt
            }
          }
          promotionalPrices {
            code
            pct
            price {
              milesEquivalentPrice {
                mileCnt
              }
              currencyEquivalentPrice {
                currencyAmt
                roundedCurrencyAmt
              }
            }
          }
        }
      }
      itineraryDepartureDate
    }
    offerDataList {
      pricingOptions {
        pricingOptionDetail {
          currencyCode
        }
      }
      flexReturnDates
      responseProperties {
        tripTypeText
        discountInfo {
          discountPct
          discountTypeCode
          nonDiscountedOffersAvailable
        }
        promotionsInfo {
          promotionalCode
          promotionalPct
        }
      }
    }
  }
}';
    }

    private function getPayloadMain(array $fields, int $pageNum, int $requestNum = 200): array
    {
        $customers = [];

        for ($i = 1; $i <= (int) $fields['Adults']; $i++) {
            $customers[] = ['passengerTypeCode' => "ADT", 'passengerId' => (string) $i];
        }

        return [
            'variables' => [
                'offerSearchCriteria' => [
                    'customers'      => $customers,
                    'offersCriteria' => [
                        'flightRequestCriteria' => [
                            'currentTripIndexId'      => '0',
                            'searchOriginDestination' => [
                                [
                                    'departureLocalTs' => $fields['DepDate'] . 'T00:00:00',
                                    'destinations'     => [['airportCode' => $fields['ArrCode']]],
                                    'origins'          => [['airportCode' => $fields['DepCode']]],
                                ],
                            ],
                            'selectedOfferId'  => '',
                            'sortableOptionId' => null,
                            'sortByBrandId'    => $fields['brandId'], //'BE'
                        ],
                        'preferences' => [
                            'nonStopOnly'                          => false,
                            'refundableOnly'                       => false,
                            'showGlobalRegionalUpgradeCertificate' => true,
                        ],
                        'pricingCriteria'      => ['priceableIn' => ['MILES']],
                        'resultsPageNum'       => $pageNum,
                        'resultsPerRequestNum' => $requestNum, // site has 20
                        'showContentLinks'     => false,
                    ],
                    'productGroups' => [['productCategoryCode' => 'FLIGHTS']],
                ],
            ],
            'query' => $this->getQueryStringMain(),
        ];
    }

    private function getQueryStringMain(): string
    {
        return 'query ($offerSearchCriteria: OfferSearchCriteriaInput!) {\n  gqlSearchOffers(offerSearchCriteria: $offerSearchCriteria) {\n    offerResponseId\n    gqlOffersSets {\n      trips {\n        tripId\n        scheduledDepartureLocalTs\n        scheduledArrivalLocalTs\n        originAirportCode\n        destinationAirportCode\n        stopCnt\n        flightSegment {\n          aircraftTypeCode\n          dayChange\n          destinationAirportCode\n          flightLeg {\n            legId\n            dayChange\n            destinationAirportCode\n            feeRestricted\n            scheduledArrivalLocalTs\n            scheduledDepartureLocalTs\n            layover {\n              destinationAirportCode\n              layoverAirportCode\n              layoverDuration {\n                hourCnt\n                minuteCnt\n              }\n              departureFlightNum\n              equipmentChange\n              originAirportCode\n              scheduledArrivalLocalTs\n              scheduledDepartureLocalTs\n            }\n            operatedByOwnerCarrier\n            redEye\n            operatingCarrier {\n              carrierCode\n              carrierName\n            }\n            marketingCarrier {\n              carrierCode\n              carrierName\n            }\n            earnLoyaltyMiles\n            loyaltyMemberBenefits\n            dominantLeg\n            duration {\n              dayCnt\n              hourCnt\n              minuteCnt\n            }\n            originAirport {\n              airportTerminals {\n                terminalId\n              }\n            }\n            destinationAirport {\n              airportTerminals {\n                terminalId\n              }\n            }\n            originAirportCode\n            aircraft {\n              fleetTypeCode\n              subFleetTypeCode\n              newSubFleetType\n            }\n            carrierCode\n            distance {\n              unitOfMeasure\n              unitOfMeasureCnt\n            }\n          }\n          layover {\n            destinationAirportCode\n            layoverAirportCode\n            layoverDuration {\n              hourCnt\n              minuteCnt\n            }\n            departureFlightNum\n            equipmentChange\n            originAirportCode\n            scheduledArrivalLocalTs\n            scheduledDepartureLocalTs\n          }\n          marketingCarrier {\n            carrierCode\n            carrierNum\n          }\n          operatingCarrier {\n            carrierCode\n            carrierNum\n            carrierName\n          }\n          pendingGovtApproval\n          destinationCityCode\n          flightSegmentNum\n          originAirportCode\n          originCityCode\n          scheduledArrivalLocalTs\n          scheduledDepartureLocalTs\n          aircraft {\n            fleetTypeCode\n            subFleetTypeCode\n            newSubFleetType\n          }\n        }\n        totalTripTime {\n          dayCnt\n          hourCnt\n          minuteCnt\n        }\n        summarizedProductId\n      }\n      additionalOfferSetProperties {\n        globalUpgradeCertificateTripStatus {\n          brandId\n          upgradeAvailableStatusProductId\n        }\n        regionalUpgradeCertificateTripStatus {\n          brandId\n          upgradeAvailableStatusProductId\n        }\n        offerSetId\n        seatReferenceId\n        discountInfo {\n          discountPct\n          discountTypeCode\n          nonDiscountedOffersAvailable\n        }\n        promotionsInfo {\n          promotionalCode\n          promotionalPct\n        }\n        discountInEligibilityList {\n          code\n          reason\n        }\n      }\n      offerSetBadges {\n        brandId\n      }\n      offers {\n        offerId\n        additionalOfferProperties {\n          offered\n          fareType\n          dominantSegmentBrandId\n          priorityNum\n          soldOut\n          unavailableForSale\n          refundable\n          offerBadges {\n            brandId\n          }\n          payWithMilesEligible\n          discountAvailable\n          travelPolicyStatus\n        }\n        soldOut\n        offerItems {\n          retailItems {\n            retailItemMetaData {\n              fareInformation {\n                brandByFlightLegs {\n                  brandId\n                  cosCode\n                  tripId\n                  product {\n                    brandId\n                    typeCode\n                  }\n                  globalUpgradeCertificateLegStatus {\n                    upgradeAvailableStatusProductId\n                  }\n                  regionalUpgradeCertificateLegStatus {\n                    upgradeAvailableStatusProductId\n                  }\n                  flightSegmentNum\n                  flightLegNum\n                }\n                discountInEligibilityList {\n                  code\n                  reason\n                }\n                availableSeatCnt\n                farePrice {\n                  discountsApplied {\n                    pct\n                    code\n                    description\n                    reason\n                    amount {\n                      currencyEquivalentPrice {\n                        currencyAmt\n                      }\n                      milesEquivalentPrice {\n                        mileCnt\n                        discountMileCnt\n                      }\n                    }\n                  }\n                  totalFarePrice {\n                    currencyEquivalentPrice {\n                      roundedCurrencyAmt\n                      formattedCurrencyAmt\n                    }\n                    milesEquivalentPrice {\n                      mileCnt\n                      cashPlusMilesCnt\n                      cashPlusMiles\n                    }\n                  }\n                  originalTotalPrice {\n                    currencyEquivalentPrice {\n                      roundedCurrencyAmt\n                      formattedCurrencyAmt\n                    }\n                    milesEquivalentPrice {\n                      mileCnt\n                      cashPlusMilesCnt\n                      cashPlusMiles\n                    }\n                  }\n                  promotionalPrices {\n                    price {\n                      currencyEquivalentPrice {\n                        roundedCurrencyAmt\n                        formattedCurrencyAmt\n                      }\n                      milesEquivalentPrice {\n                        mileCnt\n                        cashPlusMilesCnt\n                        cashPlusMiles\n                      }\n                    }\n                  }\n                }\n              }\n            }\n          }\n        }\n      }\n    }\n    offerDataList {\n      responseProperties {\n        discountInfo {\n          discountPct\n          discountTypeCode\n          nonDiscountedOffersAvailable\n        }\n        promotionsInfo {\n          promotionalCode\n          promotionalPct\n        }\n        discountInEligibilityList {\n          code\n          reason\n        }\n        resultsPerRequestNum\n        pageResultCnt\n        resultsPageNum\n        sortOptionsList {\n          sortableOptionDesc\n          sortableOptionId\n        }\n        tripTypeText\n      }\n      offerPreferences {\n        stopCnt\n        destinationAirportCode\n        connectionTimeRange {\n          maximumNum\n          minimumNum\n        }\n        originAirportCode\n        flightDurationRange {\n          maximumNum\n          minimumNum\n        }\n        layoverAirportCode\n        totalMilesRange {\n          maximumNum\n          minimumNum\n        }\n        totalPriceRange {\n          maximumNum\n          minimumNum\n        }\n      }\n      retailItemDefinitionList {\n        brandType\n        retailItemBrandId\n        refundable\n        retailItemPriorityText\n      }\n      pricingOptions {\n        pricingOptionDetail {\n          currencyCode\n        }\n      }\n    }\n    gqlSelectedOfferSets {\n      trips {\n        tripId\n        scheduledDepartureLocalTs\n        scheduledArrivalLocalTs\n        originAirportCode\n        destinationAirportCode\n        stopCnt\n        flightSegment {\n          destinationAirportCode\n          marketingCarrier {\n            carrierCode\n            carrierNum\n          }\n          operatingCarrier {\n            carrierCode\n            carrierNum\n          }\n          flightSegmentNum\n          originAirportCode\n          scheduledArrivalLocalTs\n          scheduledDepartureLocalTs\n          aircraft {\n            fleetTypeCode\n            subFleetTypeCode\n            newSubFleetType\n          }\n          flightLeg {\n            destinationAirportCode\n            feeRestricted\n            layover {\n              destinationAirportCode\n              layoverAirportCode\n              layoverDuration {\n                hourCnt\n                minuteCnt\n              }\n              departureFlightNum\n              equipmentChange\n              originAirportCode\n              scheduledArrivalLocalTs\n              scheduledDepartureLocalTs\n            }\n            operatedByOwnerCarrier\n            redEye\n            operatingCarrier {\n              carrierCode\n              carrierName\n            }\n            marketingCarrier {\n              carrierCode\n              carrierName\n            }\n            earnLoyaltyMiles\n            loyaltyMemberBenefits\n            dominantLeg\n            duration {\n              dayCnt\n              hourCnt\n              minuteCnt\n            }\n            originAirport {\n              airportTerminals {\n                terminalId\n              }\n            }\n            destinationAirport {\n              airportTerminals {\n                terminalId\n              }\n            }\n            originAirportCode\n            aircraft {\n              fleetTypeCode\n              subFleetTypeCode\n              newSubFleetType\n            }\n            carrierCode\n            distance {\n              unitOfMeasure\n              unitOfMeasureCnt\n            }\n            scheduledArrivalLocalTs\n            scheduledDepartureLocalTs\n            dayChange\n          }\n        }\n        totalTripTime {\n          dayCnt\n          hourCnt\n          minuteCnt\n        }\n      }\n      offers {\n        additionalOfferProperties {\n          dominantSegmentBrandId\n          fareType\n        }\n        soldOut\n        offerItems {\n          retailItems {\n            retailItemMetaData {\n              fareInformation {\n                brandByFlightLegs {\n                  tripId\n                  brandId\n                  cosCode\n                }\n              }\n            }\n          }\n        }\n      }\n      additionalOfferSetProperties {\n        seatReferenceId\n      }\n    }\n    contentLinks {\n      type\n      variables {\n        brandProductMapping\n      }\n    }\n  }\n}';

        return 'query ($offerSearchCriteria: OfferSearchCriteriaInput!) {  gqlSearchOffers(offerSearchCriteria: $offerSearchCriteria) {    offerResponseId    gqlOffersSets {      trips {        tripId        scheduledDepartureLocalTs        scheduledArrivalLocalTs        originAirportCode        destinationAirportCode        stopCnt        flightSegment {          aircraftTypeCode          dayChange          destinationAirportCode          flightLeg {            legId            dayChange            destinationAirportCode            feeRestricted            scheduledArrivalLocalTs            scheduledDepartureLocalTs            layover {              destinationAirportCode              layoverAirportCode              layoverDuration {                hourCnt                minuteCnt              }              departureFlightNum              equipmentChange              originAirportCode              scheduledArrivalLocalTs              scheduledDepartureLocalTs            }            operatedByOwnerCarrier            redEye            operatingCarrier {              carrierCode              carrierName            }            marketingCarrier {              carrierCode              carrierName            }            earnLoyaltyMiles            loyaltyMemberBenefits            dominantLeg            duration {              dayCnt              hourCnt              minuteCnt            }            originAirport {              airportTerminals {                terminalId              }            }            destinationAirport {              airportTerminals {                terminalId              }            }            originAirportCode            aircraft {              fleetTypeCode              subFleetTypeCode              newSubFleetType            }            carrierCode            distance {              unitOfMeasure              unitOfMeasureCnt            }          }          layover {            destinationAirportCode            layoverAirportCode            layoverDuration {              hourCnt              minuteCnt            }            departureFlightNum            equipmentChange            originAirportCode            scheduledArrivalLocalTs            scheduledDepartureLocalTs          }          marketingCarrier {            carrierCode            carrierNum          }          operatingCarrier {            carrierCode            carrierNum            carrierName          }          pendingGovtApproval          destinationCityCode          flightSegmentNum          originAirportCode          originCityCode          scheduledArrivalLocalTs          scheduledDepartureLocalTs          aircraft {            fleetTypeCode            subFleetTypeCode            newSubFleetType          }        }        totalTripTime {          dayCnt          hourCnt          minuteCnt        }        summarizedProductId      }      additionalOfferSetProperties {        globalUpgradeCertificateTripStatus {          brandId          upgradeAvailableStatusProductId        }        regionalUpgradeCertificateTripStatus {          brandId          upgradeAvailableStatusProductId        }        offerSetId        seatReferenceId        discountInfo {          discountPct          discountTypeCode          nonDiscountedOffersAvailable        }        promotionsInfo {          promotionalCode          promotionalPct        }        discountInEligibilityList {          code          reason        }      }      offerSetBadges {        brandId      }      offers {        offerId        additionalOfferProperties {          offered          fareType          dominantSegmentBrandId          priorityNum          soldOut          unavailableForSale          refundable          offerBadges {            brandId          }          payWithMilesEligible          discountAvailable          travelPolicyStatus        }        soldOut        offerItems {          retailItems {            retailItemMetaData {              fareInformation {                brandByFlightLegs {                  brandId                  cosCode                  tripId                  product {                    brandId                    typeCode                  }                  globalUpgradeCertificateLegStatus {                    upgradeAvailableStatusProductId                  }                  regionalUpgradeCertificateLegStatus {                    upgradeAvailableStatusProductId                  }                  flightSegmentNum                  flightLegNum                }                discountInEligibilityList {                  code                  reason                }                availableSeatCnt                farePrice {                  discountsApplied {                    pct                    code                    description                    reason                    amount {                      currencyEquivalentPrice {                        currencyAmt                      }                      milesEquivalentPrice {                        mileCnt                        discountMileCnt                      }                    }                  }                  totalFarePrice {                    currencyEquivalentPrice {                      roundedCurrencyAmt                      formattedCurrencyAmt                    }                    milesEquivalentPrice {                      mileCnt                      cashPlusMilesCnt                      cashPlusMiles                    }                  }                  originalTotalPrice {                    currencyEquivalentPrice {                      roundedCurrencyAmt                      formattedCurrencyAmt                    }                    milesEquivalentPrice {                      mileCnt                      cashPlusMilesCnt                      cashPlusMiles                    }                  }                  promotionalPrices {                    price {                      currencyEquivalentPrice {                        roundedCurrencyAmt                        formattedCurrencyAmt                      }                      milesEquivalentPrice {                        mileCnt                        cashPlusMilesCnt                        cashPlusMiles                      }                    }                  }                }              }            }          }        }      }    }    offerDataList {      responseProperties {        discountInfo {          discountPct          discountTypeCode          nonDiscountedOffersAvailable        }        promotionsInfo {          promotionalCode          promotionalPct        }        discountInEligibilityList {          code          reason        }        resultsPerRequestNum        pageResultCnt        resultsPageNum        sortOptionsList {          sortableOptionDesc          sortableOptionId        }        tripTypeText      }      offerPreferences {        stopCnt        destinationAirportCode        connectionTimeRange {          maximumNum          minimumNum        }        originAirportCode        flightDurationRange {          maximumNum          minimumNum        }        layoverAirportCode        totalMilesRange {          maximumNum          minimumNum        }        totalPriceRange {          maximumNum          minimumNum        }      }      retailItemDefinitionList {        brandType        retailItemBrandId        refundable        retailItemPriorityText      }      pricingOptions {        pricingOptionDetail {          currencyCode        }      }    }    gqlSelectedOfferSets {      trips {        tripId        scheduledDepartureLocalTs        scheduledArrivalLocalTs        originAirportCode        destinationAirportCode        stopCnt        flightSegment {          destinationAirportCode          marketingCarrier {            carrierCode            carrierNum          }          operatingCarrier {            carrierCode            carrierNum          }          flightSegmentNum          originAirportCode          scheduledArrivalLocalTs          scheduledDepartureLocalTs          aircraft {            fleetTypeCode            subFleetTypeCode            newSubFleetType          }          flightLeg {            destinationAirportCode            feeRestricted            layover {              destinationAirportCode              layoverAirportCode              layoverDuration {                hourCnt                minuteCnt              }              departureFlightNum              equipmentChange              originAirportCode              scheduledArrivalLocalTs              scheduledDepartureLocalTs            }            operatedByOwnerCarrier            redEye            operatingCarrier {              carrierCode              carrierName            }            marketingCarrier {              carrierCode              carrierName            }            earnLoyaltyMiles            loyaltyMemberBenefits            dominantLeg            duration {              dayCnt              hourCnt              minuteCnt            }            originAirport {              airportTerminals {                terminalId              }            }            destinationAirport {              airportTerminals {                terminalId              }            }            originAirportCode            aircraft {              fleetTypeCode              subFleetTypeCode              newSubFleetType            }            carrierCode            distance {              unitOfMeasure              unitOfMeasureCnt            }            scheduledArrivalLocalTs            scheduledDepartureLocalTs            dayChange          }        }        totalTripTime {          dayCnt          hourCnt          minuteCnt        }      }      offers {        additionalOfferProperties {          dominantSegmentBrandId          fareType        }        soldOut        offerItems {          retailItems {            retailItemMetaData {              fareInformation {                brandByFlightLegs {                  tripId                  brandId                  cosCode                }              }            }          }        }      }      additionalOfferSetProperties {        seatReferenceId      }    }    contentLinks {      type      variables {        brandProductMapping      }    }  }}';
    }

    private function getPostData(array $fields, $selenium): array
    {
        return [
            "tripType"           => "ONE_WAY",
            "shopType"           => "MILES",
            "priceType"          => "Award",
            "nonstopFlightsOnly" => "false",
            "bookingPostVerify"  => "RTR_YES",
            "bundled"            => "off",
            "segments"           => [
                [
                    "origin"                 => $fields['DepCode'],
                    "destination"            => $fields['ArrCode'],
                    "originCountryCode"      => $this->depCountryCode ?? $this->getCountryCode($fields['DepCode'], $selenium) ?? "US",
                    "destinationCountryCode" => $this->arrCountryCode ?? $this->getCountryCode($fields['ArrCode'], $selenium) ?? "US",
                    "departureDate"          => $fields['DepDate'],
                    "connectionAirportCode"  => null,
                ],
            ],
            "destinationAirportRadius" => ["measure" => 100, "unit" => "MI"],
            "originAirportRadius"      => ["measure" => 100, "unit" => "MI"],
            "flexAirport"              => false,
            "flexDate"                 => true,
            "flexDaysWeeks"            => "FLEX_DAYS",
            "passengers"               => [["count" => "2", "type" => "ADT"]],
            "meetingEventCode"         => "",
            "bestFare"                 => $fields['brandId'], //"BE",
            "searchByCabin"            => true,
            "cabinFareClass"           => null,
            "refundableFlightsOnly"    => false,
            "deltaOnlySearch"          => "false",
            "initialSearchBy"          => [
                "fareFamily"       => $fields['brandId'], //"BE",
                "cabinFareClass"   => null,
                "meetingEventCode" => "",
                "refundable"       => false,
                "flexAirport"      => false,
                "flexDate"         => true,
                "flexDaysWeeks"    => "FLEX_DAYS",
            ],
            "searchType"        => "simple",
            "searchByFareClass" => null,
            "pageName"          => "FLEX_DATE",
            "requestPageNum"    => "",
            "action"            => "findFlights",
            "actionType"        => "",
            "priceSchedule"     => "AWARD",
            "schedulePrice"     => "miles",
            "shopWithMiles"     => "on",
            "awardTravel"       => "true",
            "datesFlexible"     => true,
            "flexCalendar"      => false,
            "upgradeRequest"    => false,
            "is_Flex_Search"    => true,
        ];
    }

    private function setConfig()
    {
        $configs = self::CONFIGS;

        if (ConfigValue(CONFIG_SITE_STATE) === SITE_STATE_DEBUG) {
            unset($configs['chrome-94-mac']);
        }

        $successfulConfigs = array_filter(array_keys($configs), function (string $key) {
            return \Cache::getInstance()->get('delta_config_' . $key) === 1;
        });

        $neutralConfigs = array_filter(array_keys($configs), function (string $key) {
            return \Cache::getInstance()->get('delta_config_' . $key) !== 0;
        });

        if (count($successfulConfigs) > 0) {
            $this->config = $successfulConfigs[array_rand($successfulConfigs)];
            $this->logger->info("found " . count($successfulConfigs) . " successful configs");
        } elseif (count($neutralConfigs) > 0) {
            $this->config = $neutralConfigs[array_rand($neutralConfigs)];
            $this->logger->info("found " . count($neutralConfigs) . " neutral configs");
        } else {
            $this->config = array_rand($configs);
        }

        $this->logger->info("selected config $this->config");
    }

    private function GetCabinFields($onlyKeys = true): array
    {
        // если брать BE - то светит всё.. по другим от и выше
        $array = [
            'economy' => [
                // for requests
                'brandID'     => 'BE',
                'listBrandID' => ['BE', 'MAIN'],
                //just info
                'award' => ['Basic Economy' => ['brandID' => 'BE'], 'Main Cabin' => ['brandID' => 'MAIN']],
            ],
            'premiumEconomy' => [
                'brandID'     => 'BE',
                //'brandID'     => 'MAIN', // если надо будет всё ж отсекать, то лучше так
                'listBrandID' => ['DCP', 'DPPS'],
                'award'       => ['Delta Comfort+' => ['brandID' => 'DCP'], 'Premium Select' => ['brandID' => 'DPPS']],
            ],
            'firstClass' => [// выбор любого показывает 2 результата
                'brandID'     => 'BE',
                //'brandID'     => 'DPPS',
                'listBrandID' => ['FIRST', 'D1'],
                'award'       => ['First Class' => ['brandID' => 'FIRST'], 'Delta One' => ['brandID' => 'D1']],
            ],
            'business' => [// выбор любого показывает 2 результата
                'brandID'     => 'BE',
                //'brandID'     => 'DPPS',
                'listBrandID' => ['FIRST', 'D1'],
                'award'       => [],
            ],
        ];

        if ($onlyKeys) {
            return array_keys($array);
        }

        return $array;
    }

    private function ParseReward(array $fields, $responses): array
    {
        $this->logger->notice(__METHOD__);
        $this->logger->info("ParseReward [" . $fields['DepDate'] . "-" . $fields['DepCode'] . "-" . $fields['ArrCode'] . "]",
            ['Header' => 2]);

        return $this->parseRewardFlights($responses);
    }

    private function parseRewardFlights($responses): array
    {
        $this->logger->notice(__METHOD__);

        $routes = [];

        foreach ($responses as $data) {
            if (!isset($data->data->gqlSearchOffers->gqlOffersSets) && isset($data->errors)
                && strpos(json_encode($data->errors), '"message":"RetailOfferError"') !== false
            ) {
                $this->SetWarning('Not Available');

                return [];
            }

            if (!isset($data->data->gqlSearchOffers->gqlOffersSets) || !is_array($data->data->gqlSearchOffers->gqlOffersSets)) {
                throw new \CheckException('gqlOffersSets not found. other format json', ACCOUNT_ENGINE_ERROR);
            }
            $itineraries = $data->data->gqlSearchOffers->gqlOffersSets;
            $currencyCode = $data->data->gqlSearchOffers->offerDataList->pricingOptions[0]->pricingOptionDetail->currencyCode;
            $this->logger->debug("Found " . count($itineraries) . " routes");

            foreach ($itineraries as $numRoot => $it) {
                $result = ['connections' => []];

                if (count($it->trips) !== 1) {
                    $this->logger->error("check itinerary $numRoot");
                }
                $trip = $it->trips[0];
                $this->logger->notice("route " . $numRoot);
                // for debug
                $this->http->JsonLog(json_encode($it), 1);

                $itOffers = [];

                foreach ($it->offers as $num => $offer) {
                    if ($offer->soldOut === false && $offer->additionalOfferProperties->offered === true) {
                        $itOffers[$num] = [
                            'dominantSegmentBrandId'=> $offer->additionalOfferProperties->dominantSegmentBrandId,
                            'brandByFlightLegs'     => $offer->offerItems[0]->retailItems[0]->retailItemMetaData->fareInformation[0]->brandByFlightLegs,
                            'availableSeatCnt'      => $offer->offerItems[0]->retailItems[0]->retailItemMetaData->fareInformation[0]->availableSeatCnt ?? null,
                            'farePrice'             => $offer->offerItems[0]->retailItems[0]->retailItemMetaData->fareInformation[0]->farePrice[0],
                        ];
                    }
                }

                if (empty($itOffers) && !empty($it->offers)) {
                    $allSoldOut = true;
                }

                foreach ($itOffers as $numOffer => $itOffer) {
                    if (empty($itOffer['farePrice']->totalFarePrice->milesEquivalentPrice->mileCnt)) {
                        throw new \CheckRetryNeededException(5, 0);
                    }
                    $segDominantSegmentBrandId = $itOffer['dominantSegmentBrandId'];
                    $award_type = $this->brandID2Award($segDominantSegmentBrandId);

                    $headData = [
                        'distance'  => null,
                        'num_stops' => $trip->stopCnt,
                        'times'     => [
                            'flight'  => null,
                            'layover' => null,
                        ],
                        'redemptions' => [
                            'miles'   => $itOffer['farePrice']->totalFarePrice->milesEquivalentPrice->mileCnt,
                            'program' => $this->AccountFields['ProviderCode'],
                        ],
                        'payments' => [
                            'currency' => $currencyCode,
                            'taxes'    => $itOffer['farePrice']->totalFarePrice->currencyEquivalentPrice->roundedCurrencyAmt, // as on site (real price formattedCurrencyAmt)
                            'fees'     => null,
                        ],
                    ];
                    $result['connections'] = [];

                    foreach ($trip->flightSegment as $flightSegment) {
                        $flightSegmentId = $flightSegment->flightSegmentNum;

                        foreach ($flightSegment->flightLeg as $flightLeg) {
                            $flightLegId = $flightLeg->legId;

                            $seg = [
                                'departure' => [
                                    'date'      => date('Y-m-d H:i', strtotime(str_replace('T', ' ', $flightLeg->scheduledDepartureLocalTs))),
                                    'dateTime'  => strtotime(str_replace('T', ' ', $flightLeg->scheduledDepartureLocalTs)),
                                    'airport'   => $flightLeg->originAirportCode,
                                    'terminal'  => $flightLeg->originAirport->airportTerminals[0]->terminalId ?? null,
                                ],
                                'arrival' => [
                                    'date'      => date('Y-m-d H:i', strtotime(str_replace('T', ' ', $flightLeg->scheduledArrivalLocalTs))),
                                    'dateTime'  => strtotime(str_replace('T', ' ', $flightLeg->scheduledArrivalLocalTs)),
                                    'airport'   => $flightLeg->destinationAirportCode,
                                    'terminal'  => $flightLeg->destinationAirport->airportTerminals[0]->terminalId ?? null,
                                ],
                                'meal'       => null,
                                'cabin'      => null,
                                'fare_class' => null,
                                'distance'   => $flightLeg->distance->unitOfMeasureCnt . $flightLeg->distance->unitOfMeasure,
                                'aircraft'   => $flightSegment->aircraftTypeCode,
                                'flight'     => [$flightSegment->marketingCarrier->carrierCode . $flightSegment->marketingCarrier->carrierNum],
                                'airline'    => $flightSegment->marketingCarrier->carrierCode,
                                'operator'   => $flightSegment->operatingCarrier->carrierCode,
                                'times'      => [
                                    'flight'  => null,
                                    'layover' => null,
                                ],
                            ];

                            $brandIdFlightLeg = null;

                            foreach ($itOffer['brandByFlightLegs'] as $info) {
                                if (isset($info->flightSegmentNum, $info->flightLegNum)
                                    && $flightLegId === $info->flightLegNum && $flightSegmentId === $info->flightSegmentNum
                                ) {
                                    $brandIdFlightLeg = $info->brandId;
                                    $seg['cabin'] = $this->jmLogicWithBookingCodes($brandIdFlightLeg);
                                    $seg['fare_class'] = $info->cosCode;

                                    break;
                                }
                            }

                            if (isset($brandIdFlightLeg)) {
                                $brandName = $this->brandID2Award($brandIdFlightLeg);

                                if (null === $brandName) {
                                    $this->logger->error("skip connection. unknown brandId");

                                    continue 3;
                                }
                                $seg['classOfService'] = $this->clearCOS($brandName);
                            }

                            $result['connections'][] = $seg;
                        }
                    }
                    $res = array_merge($headData, $result);
                    $res['tickets'] = $itOffer['availableSeatCnt'];
                    $res['classOfService'] = $this->clearCOS($award_type);
                    $this->logger->debug(var_export($res, true), ['pre' => true]);
                    $routes[] = $res;
                }
            }
        }

        if (empty($routes) && isset($allSoldOut)) {
            $this->SetWarning('Not Available');
        }

        return $routes;
    }

    private function getBookingCodes()
    {
        $this->logger->debug(__METHOD__);
        $bookingCodes = \Cache::getInstance()->get('ra_dl_airline_fare_classes');

        if ($bookingCodes === false) {
            $bookingCodes = [];

            $result = $this->db->getFareClassesByAirlineCode('DL');

            foreach ($result as $row) {
                $bookingCodes[trim($row['ClassOfService'])][] = $row['FareClass'];
            }

            if (isset($bookingCodes['Basic Economy'], $bookingCodes['Economy'])) {
                $bookingCodes['Economy'] = array_unique(array_merge($bookingCodes['Economy'],
                    $bookingCodes['Basic Economy']));
                unset($bookingCodes['Basic Economy']);
            }

            if (isset($bookingCodes['Economy Plus'], $bookingCodes['Premium Economy'])) {
                $bookingCodes['Premium Economy'] = array_unique(array_merge($bookingCodes['Premium Economy'],
                    $bookingCodes['Economy Plus']));
                unset($bookingCodes['Economy Plus']);
            }

            if (count($bookingCodes) !== 4 || !isset($bookingCodes['Economy'], $bookingCodes['Premium Economy'], $bookingCodes['First'], $bookingCodes['Business'])) {
                $this->logger->debug(var_export($bookingCodes, true));
                $this->sendNotification('check AirlineFareClass for DL // ZM');
            }
            $bookingCodes = [
                'economy'        => $bookingCodes['Economy'] ?? [],
                'premiumEconomy' => $bookingCodes['Premium Economy'] ?? [],
                'firstClass'     => $bookingCodes['First'] ?? [],
                'business'       => $bookingCodes['Business'] ?? [],
            ];

            if (!empty($bookingCodes)) {
                \Cache::getInstance()->set('ra_dl_airline_fare_classes', $bookingCodes, 60 * 60 * 24);
            }
        }

        return $bookingCodes;
    }

    private function clearCOS(string $cos): string
    {
        if (preg_match("/^(.+\w+) (?:cabin|class|standard|reward)$/i", $cos, $m)) {
            $cos = $m[1];
        }

        if (preg_match("/^(.+\w+) (?:train)$/i", $cos, $m)) {
            $cos = $m[1];
        }

        return $cos;
    }

    private function jmLogicWithBookingCodes($brandId): ?string
    {
        $cabin = null;
        // see ref: 20045#note-49
        switch ($brandId) {
            case 'BE':
                $cabin = 'economy';

                break;

            case 'MAIN':
                $cabin = 'economy';

                break;

            case 'DPPS':// Premium Select
                $cabin = 'premiumEconomy';

                break;

            case 'DCP'://Delta Comfort+
                $cabin = 'economy';

                break;

            case 'D1':
                $cabin = 'business';

                break;

            case 'FIRST':
                $cabin = 'firstClass';

                break;
        }

        if (!$cabin) {
            return $this->brandIdToCabin($brandId);
        }

        return $cabin;
    }

    private function brandIdToCabin($brandId): ?string
    {
        // extended jmLogicWithBookingCodes
        $cabin = null;

        switch ($brandId) {
            case 'BE':
            case 'MAIN':
            case 'DCP':     // 'Delta Comfort+'
            case 'AMCL':    // 'Classic'
            case 'VSCL':    // 'Main'
            case 'E':       // 'Economy'
            case 'LAPL':    // 'Economy'
            case 'KLEC':    // 'Economy'
            case 'WSEC':    // 'Economy'
            case 'VAEC':    // 'Economy'
            case 'AZEC':    // 'Economy'
            case 'KEEC':    // 'Main'
            case 'KLEE':    // 'Europe Economy'
            case 'KEPC':    // 'Economy'
            case 'AFST':    // 'Economy'
            case 'ETN':     // 'Economy Train'
                $cabin = 'economy';

                break;

            case 'KLPC':    // 'Premium Comfort+'
            case 'DPPS':    // 'Premium Select'
            case 'VSPE':    // 'Premium Select'
            case 'AFPE':    // 'Premium Select'
            case 'LAPE':    // 'Premium Economy'
            case 'PE':      // 'Premium Economy'
                $cabin = 'premiumEconomy';

                break;

            case 'D1S':     // 'Delta One'
            case 'D1':      // 'Delta One'
            case 'BU':      // 'Delta One'
            case 'VSUP':    // 'Upper Class'
            case 'KLEBU':   // 'Europe Business'
            case 'KLBU':    // 'Business'
            case 'AFEBU':   // 'Europe Business'
            case 'AFBU':    // 'Business'
            case 'AZBU':    // 'Business'
            case 'LATP':    // 'Top Business'
            case 'BUTN':    // 'Business Train'
                $cabin = 'business';

                break;

            case 'FIRST':
            case 'AMPR':
            case 'AMPO':
                $cabin = 'firstClass';

                break;
        }

        return $cabin;
    }

    private function brandID2Award(string $brandID): ?string
    {
        $array = [
            'BE'            => 'Basic Economy',
            'MAIN'          => 'Main',
            'VSCL'          => 'Main',
            'AMCL'          => 'Classic',
            'E'             => 'Economy',
            'LAPL'          => 'Economy',
            'KLEC'          => 'Main',
            'KEEC'          => 'Main',
            'ETN'           => 'Economy Train',
            'KLEE'          => 'Economy',
            'KEPC'          => 'Economy',
            'WSEC'          => 'Economy',
            'VAEC'          => 'Economy',
            'AZEC'          => 'Economy',
            'AFST'          => 'Economy',
            'DCP'           => 'Delta Comfort+',
            'KLPC'          => 'Premium Comfort',
            'FIRST'         => 'First Class',
            'AMPR'          => 'Premier',
            'AMPO'          => 'Premier One',
            'LAPE'          => 'Premium Economy',
            'PE'            => 'Premium Economy',
            'DPPS'          => 'Premium Select',
            'VSPE'          => 'Premium Select',
            'AFPE'          => 'Premium Select',
            'D1S'           => 'Delta One',
            'D1'            => 'Delta One',
            'BU'            => 'Delta One',
            'VSUP'          => 'Upper Class',
            'KLEBU'         => 'Europe Business',
            'KLBU'          => 'Business',
            'AFBU'          => 'Business',
            'AZBU'          => 'Business',
            'LATP'          => 'Top Business',
            'AFEBU'         => 'Europe Business',
            'BUTN'          => 'Business Train',
        ];

        if (!isset($array[$brandID])) {
            $this->sendNotification('check brandId: ' . $brandID);
        }

        return $array[$brandID] ?? null;
    }

    private function validRoute($fields)
    {
        $this->http->GetURL("https://www.delta.com/predictivetext/getPredictiveCities?code=" . $fields['DepCode'], [], 20);

        $this->http->setCookie('DELTA_ENSIGHTEN_PRIVACY_BANNER_VIEWED', '1', '.delta.com');
        $this->http->setCookie('DELTA_ENSIGHTEN_PRIVACY_MODAL_VAL', '1', '.delta.com');
        $this->http->setCookie('DELTA_ENSIGHTEN_PRIVACY_Advertising', '1', '.delta.com');
        $this->http->setCookie('DELTA_ENSIGHTEN_PRIVACY_Required', '1', '.delta.com');

        if ($this->http->currentUrl() === 'https://www.delta.com/content/www/en_US/system-unavailable1.html'
            || $this->http->Response['code'] == 403) {
            // it's work
            $this->http->GetURL("https://www.delta.com/predictivetext/getPredictiveCities?code=" . $fields['DepCode'], [], 20);
        }
        $data = $this->http->JsonLog(null, 1, false, 'listOfCities');

        if (strpos($this->http->Error, 'Network error 56 - Received HTTP code 407 from proxy after CONNECT') !== false
            || strpos($this->http->Error, 'Network error 56 - Received HTTP code 503 from proxy after CONNECT') !== false
            || strpos($this->http->Error, 'Network error 56 - Received HTTP code 400 from proxy after CONNECT') !== false
            || strpos($this->http->Error, 'Network error 56 - Received HTTP code 403 from proxy after CONNECT') !== false
            || strpos($this->http->Error, 'Network error 28 - Operation timed out after ') !== false
            || strpos($this->http->Error, 'Network error 28 - Connection timed out after') !== false
            || strpos($this->http->Error, 'Network error 7 - Failed to connect to') !== false
            || $this->http->Response['code'] == 403
            || $this->http->currentUrl() === 'https://www.delta.com/content/www/en_US/system-unavailable1.html'
        ) {
            $this->markProxyAsInvalid();

            throw new \CheckRetryNeededException(self::ATTEMPTS_CNT, 0);
        }

        if (!isset($data->listOfCities) || !is_array($data->listOfCities)) {
            // try main request anyway
            $this->routeNotChecked = true;

            return true;
        }

        if (empty($data->listOfCities)) {
            $this->SetWarning('no flights from ' . $fields['DepCode']);

            return false;
        }
        $noCode = true;

        foreach ($data->listOfCities as $city) {
            if ($city->airportCode === $fields['DepCode']) {
                $noCode = false;
                $this->depFullName = $this->http->FindPreg("/^(.+?)\s*\({$fields['DepCode']}\)$/", false, $city->airportFullName);
                $this->depCountryCode = $city->countryCode;
            }
        }

        if ($noCode) {
            $this->SetWarning('no flights from ' . $fields['DepCode']);

            return false;
        }
        //$this->http->removeCookies();
        $this->http->GetURL("https://www.delta.com/predictivetext/getPredictiveCities?code=" . $fields['ArrCode']);
        $data = $this->http->JsonLog(null, 1, false, 'listOfCities');

        if (!isset($data->listOfCities) || !is_array($data->listOfCities)) {
            // try main request anyway
            $this->routeNotChecked = true;

            return true;
        }

        if (empty($data->listOfCities)) {
            $this->SetWarning('no flights to ' . $fields['ArrCode']);

            return false;
        }
        $noCode = true;

        if (is_array($data->listOfCities)) {
            foreach ($data->listOfCities as $city) {
                if ($city->airportCode === $fields['ArrCode']) {
                    $noCode = false;
                    $this->arrFullName = $this->http->FindPreg("/^(.+?)\s*\({$fields['ArrCode']}\)$/", false, $city->airportFullName);
                    $this->arrCountryCode = $city->countryCode;
                }
            }
        }

        if ($noCode) {
            $this->SetWarning('no flights to ' . $fields['ArrCode']);

            return false;
        }

        return true;
    }

    private function getCountryCode(string $airCode, $selenium): ?string
    {
        $data = \Cache::getInstance()->get('ra_delta_cities');

        if (!is_array($data)) {
            try {
                $script = '
                    var xhttp = new XMLHttpRequest();
                    xhttp.open("GET", "https://www.delta.com/content/dam/delta-com/refdata/deltaCities.json", false);
                    xhttp.setRequestHeader("Accept", "application/json, text/plain, */*");
                    var resData = null;
                    xhttp.onreadystatechange = function() {
                        resData = this.responseText;
                    };
                    xhttp.send();
                    return resData;';
                $res = $selenium->driver->executeScript($script);
            } catch (\UnexpectedJavascriptException $e) {
                $this->logger->error('UnexpectedJavascriptException: ' . $e->getMessage());
                $res = null;
            }
            $data = $this->http->JsonLog($res, 0, true);

            if (is_array($data) && isset($data['JFK'])) {
                \Cache::getInstance()->set('ra_delta_cities', $data, 60 * 60 * 24);
            }
        }
        // строго говоря и с левым countryCode запрос отработал (видимо не обязательное поле)
        // - вытаскивание сохраняю, т.к на горячих на долгой сессии что только боком не выходит

        return $data[$airCode]['countryCode'] ?? null;
    }

    private function selenium($fields)
    {
        $this->logger->notice(__METHOD__);
        $selenium = clone $this;
        $this->http->brotherBrowser($selenium->http);
        $responses = null;

        $started = false;

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();

            $selenium->seleniumRequest->request(self::CONFIGS[$this->config]['browser-family'], self::CONFIGS[$this->config]['browser-version']);

            $selenium->seleniumOptions->addHideSeleniumExtension = false;
            $selenium->usePacFile(false);

            if ($this->config == 'chrome-94-mac') {
                $selenium->http->setUserAgent(null);
                $this->seleniumOptions->userAgent = null;
                $selenium->seleniumRequest->setOs("mac");
            } elseif (isset($this->fingerprint)) {
                $selenium->http->setUserAgent($this->fingerprint->getUseragent());
                $selenium->seleniumOptions->userAgent = $this->fingerprint->getUseragent();
            }

            $resolutions = [
                [1152, 864],
                [1280, 720],
                [1280, 768],
                [1280, 800],
                [1360, 768],
                [1366, 768],
            ];
            $chosenResolution = $resolutions[array_rand($resolutions)];
            $selenium->setScreenResolution($chosenResolution);

            if (!$this->AccountFields['DebugState']) {
                $selenium->seleniumRequest->setHotSessionPool(self::class, $this->AccountFields['ProviderCode']);
            }

            //            for debug
            $selenium->http->saveScreenshots = true;

            try {
                $selenium->http->start();
                $selenium->Start();
            } catch (\Facebook\WebDriver\Exception\UnknownErrorException $e) {
                $this->logger->notice("[UnknownErrorException]");
                $this->logger->error("exception: " . $e->getMessage());
                $this->DebugInfo = "exception";
                $started = true; // for not marking mac as bad
                $retry = true;

                return [];
            } catch (\Exception $e) {
                if (strpos($e->getMessage(), 'New session attempts retry count exceeded') !== false) {
                    $this->DebugInfo = "New session attempts retry count exceeded";
                    $started = true; // for not marking mac as bad
                    $retry = true;

                    return [];
                }

                throw $e;
            }
            $started = true;
            /** @var \SeleniumDriver $seleniumDriver */
            $seleniumDriver = $selenium->http->driver;
            $this->newSession = $seleniumDriver->isNewSession();

            $proxyParams = $selenium->http->getProxyParams();

            if (isset($proxyParams['proxyPassword'])) {
                unset($proxyParams['proxyPassword']);
            }
            $this->logger->warning(var_export($proxyParams, true), ['pre' => true]);

            $responses = $this->getDataAjax($selenium, $fields);

            if (empty($responses)) {
                throw new \CheckRetryNeededException(5, 0);
            }

            // save page to logs
            $this->saveResponse2($selenium);
            sleep(1);
            $cookies = $selenium->driver->manage()->getCookies();

            if (empty($cookies)) {
                $retry = true;
            }

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'],
                    $cookie['expiry'] ?? null);
            }
            $this->http->SetBody($selenium->driver->executeScript('return document.documentElement.innerHTML'));
            $this->http->SaveResponse();
        } catch (\ScriptTimeoutException $e) {
            $this->logger->error("ScriptTimeoutException exception: " . $e->getMessage());
            $this->DebugInfo = "ScriptTimeoutException";
        } catch (TransportException $e) {
            $this->logger->error("TransportException: " . $e->getMessage());
            $this->DebugInfo = "TransportException";
            $retry = true;
        } catch (\UnknownServerException | \SessionNotCreatedException | \WebDriverCurlException | \WebDriverException | \Facebook\WebDriver\Exception\WebDriverCurlException $e) {
            $this->logger->error("exception: " . $e->getMessage());
            $this->DebugInfo = "exception";
            $retry = true;
        } catch (\ErrorException $e) {
            if (strpos($e->getMessage(), 'Array to string conversion') !== false
                || strpos($e->getMessage(), 'strlen() expects parameter 1 to be string, array given') !== false
            ) {
                // TODO бага селениума
                throw new \CheckRetryNeededException(5, 0);
            }

            throw $e;
        } finally {
            // close Selenium browser

            // next 30 minutes not use mac
            if (!$started) {
                $this->logger->info("marking config {$this->config} as bad");
                \Cache::getInstance()->set('delta_config_' . $this->config, 0);
            }
            $selenium->http->cleanup();
            // retries
            if (isset($retry) && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                $this->logger->debug("[attempt]: {$this->attempt}");

                throw new \CheckRetryNeededException(self::ATTEMPTS_CNT, 0);
            }
        }

        return $responses;
    }

    private function saveResponse2($selenium)
    {
        if (!$selenium) {
            $selenium = $this;
        }
        $res = $selenium->saveResponse();

        if (is_string($res)
            && (strpos($res, 'invalid session id') !== false
                || strpos($res, 'JSON decoding of remote response failed') !== false
                || strpos($res, 'Failed to connect to') !== false
            )
        ) {
            throw new \CheckRetryNeededException(5, 0);
        }

        return $res;
    }

    private function openBookPage($selenium)
    {
        $this->logger->notice(__METHOD__);

        try {
            try {
                $selenium->http->GetURL("https://www.delta.com/us/en");
            } catch (\UnexpectedAlertOpenException $e) {
                $this->logger->error("exception: " . $e->getMessage());
                $selenium->http->GetURL("https://www.delta.com/us/en");
            }

            if ($selenium->http->FindPreg('/(?:page isn’t working|There is no Internet connection|This site can’t be reached|Access Denied|No internet)/ims')) {
                throw new \CheckRetryNeededException(self::ATTEMPTS_CNT, 0);
            }
            $startTime = time();

            $logoImg = $selenium->waitForElement(\WebDriverBy::xpath("//img[@alt='Delta Air Lines']"), 20, false);

            if ((time() - $startTime) > 40) {
                $this->logger->warning('page hangs up');

                throw new \CheckRetryNeededException(self::ATTEMPTS_CNT, 0);
            }

            if (!$logoImg) {
                if ($message = $selenium->http->FindPreg("/An error occurred while processing your request\.<p>/")) {
                    $this->logger->error($message);

                    throw new \CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
                }
                $this->logger->notice("start one more time");
                $this->logger->info("marking config {$this->config} as bad");
                $this->logger->info("below will change if it will be success");
                \Cache::getInstance()->set('delta_config_' . $this->config, 0);
                $selenium->driver->executeScript('window.stop();');
                $selenium->http->GetURL("https://www.delta.com/us/en");
            }

            if ($selenium->waitForElement(\WebDriverBy::id("gdpr-banner-blurred-background"), 0)) {
                $selenium->driver->executeScript("document.querySelector('#gdpr-banner-blurred-background').style.display = 'none'");
            }
        } catch (\ScriptTimeoutException | \TimeOutException $e) {
            $this->logger->error("ScriptTimeoutException: " . $e->getMessage());

            // retries
            if (ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                $this->logger->debug("[attempt]: {$this->attempt}");

                throw new \CheckRetryNeededException(self::ATTEMPTS_CNT, 0);
            }
        }

        $selenium->driver->manage()->window()->maximize();
    }

    private function savePageToLogs($selenium)
    {
        $this->logger->notice(__METHOD__);
        // save page to logs
        $selenium->http->SaveResponse();
        // save page to logs
        $this->http->SetBody($selenium->driver->executeScript('return document.documentElement.innerHTML'));
        $this->http->SaveResponse();
    }

    private function clickElements($selenium, $fields)
    {
        $this->logger->notice(__METHOD__);
        // save page to logs
        $this->savePageToLogs($selenium);
        // retries
        if ($this->http->FindPreg('/(?:page isn’t working|There is no Internet connection|This site can’t be reached|Access Denied|No internet|Your connection was interrupted)/ims')) {
            throw new \CheckRetryNeededException(self::ATTEMPTS_CNT, 0);
        }

        $inputs = [
            'oneway'     => $selenium->waitForElement(\WebDriverBy::id('selectTripType-val'), 5),
            'points'     => $selenium->waitForElement(\WebDriverBy::xpath('//label[@for="shopWithMiles"]'), 0),
            'date'       => $selenium->waitForElement(\WebDriverBy::id('input_departureDate_1'), 0),
            'passengers' => $selenium->waitForElement(\WebDriverBy::id('passengers-val'), 0),
        ];

        $cnt = random_int(2, count($inputs));

        $this->logger->info('Inputs count to click: ' . $cnt);

        while ($cnt) {
            $key = array_keys($inputs)[random_int(0, count($inputs) - 1)];
            $input = $inputs[$key];

            if ($input) {
                $input->click();
                $this->someSleep();
            }
            $cnt--;
            unset($inputs[$key]);
        }

        switch (random_int(0, 2)) {
            case 0:
                $this->clickModalMenu(
                    $selenium->waitForElement(\WebDriverBy::id('fromAirportName'), 5),
                    $selenium
                );

                break;

            case 1:
                $this->clickModalMenu(
                    $selenium->waitForElement(\WebDriverBy::id('toAirportName'), 5),
                    $selenium
                );

                break;
        }
    }

    private function getDataAjax($selenium, $fields, $isRetry = false)
    {
        $this->openBookPage($selenium);

        $this->clickElements($selenium, $fields);

        $pageNum = 1;
        $responses = [];

        do {
            if (!empty($responses)) {
                sleep(2);
            }
            $response = null;
            $this->cacheKey = $this->getUuid();
            $payload = json_encode($this->getPayloadMain($fields, $pageNum));
            $postData = json_encode($this->getPostData($fields, $selenium));

            $script = "
                localStorage.clear();
                sessionStorage.clear();
                localStorage.setItem('cacheKeySuffix', '{$this->cacheKey}');
                localStorage.setItem('postData{$this->cacheKey}', '{$postData}');
                ";
            $this->logger->debug("[run script]");
            $this->logger->debug($script, ['pre' => true]);
            $selenium->driver->executeScript('window.stop();');
            $selenium->driver->executeScript($script);

            try {
                $selenium->driver->executeScript("
                        var cl=document.querySelector('button.cookie-close-icon'); if (cl) cl.click();
                    ");
            } catch (\UnexpectedJavascriptException $e) {
                $this->logger->error($e->getMessage());
            }
            $btnSubmit = $selenium->waitForElement(\WebDriverBy::id("btn-book-submit"), 10);

            if ($btnSubmit) {
                $btnSubmit->click();
            }

            $scriptAjax = '
                    var xhttp = new XMLHttpRequest();
                    xhttp.withCredentials = true;
                    xhttp.open("POST", "https://offer-api-prd.delta.com/prd/rm-offer-gql", false);
                    xhttp.setRequestHeader("Accept", "application/json, text/plain, */*");
                    xhttp.setRequestHeader("Content-type", "application/json");
                    xhttp.setRequestHeader("Applicationid", "DC");
                    xhttp.setRequestHeader("Airline", "DL");
                    xhttp.setRequestHeader("Channelid", "DCOM");
                    xhttp.setRequestHeader("Authorization", "GUEST");
                    xhttp.setRequestHeader("accept-language", "en");
                    xhttp.setRequestHeader("Transactionid", "' . $this->cacheKey . '_' . time() . date("B") . '");
                    var resData = null;
                    xhttp.onreadystatechange = function() {
                        resData = this.responseText;
                    };
                    xhttp.send(\'' . $payload . '\');
                    return resData;
                ';
            $this->logger->debug("[run script]");
            $this->logger->debug($scriptAjax, ['pre' => true]);
            sleep(2);

            try {
                $response = $selenium->driver->executeScript($scriptAjax);
            } catch (\UnexpectedJavascriptException | \XPathLookupException $e) {
                $this->logger->error($e->getMessage());
                $response = '';
            } catch (\WebDriverException $e) {
                $this->logger->error($e->getMessage());

                throw new \CheckRetryNeededException(5, 0, $e->getMessage());
            }

            if (!is_string($response)) {
                // TODO это бага селениума. в ответе массив с типом ошибки
                throw new \CheckRetryNeededException(5, 0);
            }
            $responseData = trim($response);
            $response = $this->http->JsonLog($responseData, 1);

            if ($this->newSession) {
                if (empty($response)) {
                    $this->logger->info("marking config {$this->config} as bad");
                    \Cache::getInstance()->set('delta_config_' . $this->config, 0);
                } else {
                    $this->logger->info("marking config {$this->config} as successful");
                    \Cache::getInstance()->set('delta_config_' . $this->config, 1);
                }
            }

            if (empty($response)) {
                $this->http->SetBody($responseData);
                $this->http->SaveResponse();
            }

            if ($this->http->FindSingleNode("//h1[contains(.,'ALERT: SYSTEM UNAVAILABLE')]")
                || $this->http->FindSingleNode("//h1[contains(.,'Access Denied')]")
                || (!empty($responseData)
                    && (strpos("You don't have permission to access", $responseData) !== false
                        || stripos('Alert: System Unavailable', $responseData) !== false)
                )
            ) {
                $this->logger->error('Alert: System Unavailable/ Access Denied');

                if ($pageNum == 1) {
                    throw new \CheckRetryNeededException(5, 0);
                }
                $this->SetWarning('Not all flights');
            }

            if (strpos($responseData, 'scheduledDepartureLocalTs') !== false) {
                // check result - for exclude wrong response
                $checkDate = $this->http->FindPreg('/"gqlOffersSets":.*?"scheduledDepartureLocalTs":"([^"]+)"/', false,
                    $responseData);

                if (!empty($checkDate)) {
                    $checkDate = substr($checkDate, 0, 10);
                    $this->logger->emergency($checkDate);
                    $this->logger->info($fields['DepDate']);
                    $this->logger->info(date("Y-m-d", strtotime("+1day", strtotime($checkDate))));

                    if ($fields['DepDate'] !== $checkDate
                        && $fields['DepDate'] !== date("Y-m-d", strtotime("+1day", strtotime($checkDate)))
                    ) {
                        $this->http->SetBody($responseData);
                        $this->http->SaveResponse();

                        throw new \CheckRetryNeededException(5, 0);
                    }
                }
            } else {
                $this->logger->error('departureDate not found in responseData');
            }

            if (empty($response) && !is_array($response)) {
                $response = null;
            } else {
                $this->logger->notice('Data ok, saving session');
                $selenium->keepSession(true);
            }

            if (!is_null($response)) {
                $responses[] = $response;
            }
            $pageNum++;
            //for debug
            if (isset($response->offerDataList)) {
                $this->logger->debug(var_export($response->offerDataList, true), ['pre' => true]);
            }
        } while (isset($response->offerDataList, $response->offerDataList->responseProperties)
        && $response->offerDataList->responseProperties->pageResultCnt != $response->offerDataList->responseProperties->resultsPageNum);

        return $responses;
    }

    private function someSleep()
    {
        usleep(random_int(7, 25) * 100000);
    }

    private function clickModalMenu($input, $selenium)
    {
        if (!$input) {
            throw new \CheckRetryNeededException(5, 0);
        }
        $input->click();
        $selenium->saveResponse();
        $btn = $selenium->waitForElement(\WebDriverBy::xpath('//button[contains(@class,"search-flyout-close")]'), 5);

        if (!$btn) {
            throw new \CheckRetryNeededException(5, 0);
        }
        $btn->click();
        $this->someSleep();
    }
}
