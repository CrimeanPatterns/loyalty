<?php


namespace AppBundle\Extension\Test;


use AwardWallet\Common\Geo\Google\DirectionParameters;
use AwardWallet\Common\Geo\Google\DirectionResponse;
use AwardWallet\Common\Geo\Google\GeoCodeParameters;
use AwardWallet\Common\Geo\Google\GeoCodeResponse;
use AwardWallet\Common\Geo\Google\GoogleApi;
use AwardWallet\Common\Geo\Google\GoogleResponse;
use AwardWallet\Common\Geo\Google\PlaceAutocompleteParameters;
use AwardWallet\Common\Geo\Google\PlaceAutocompleteResponse;
use AwardWallet\Common\Geo\Google\PlaceDetailsParameters;
use AwardWallet\Common\Geo\Google\PlaceDetailsResponse;
use AwardWallet\Common\Geo\Google\PlaceSearchResponse;
use AwardWallet\Common\Geo\Google\PlaceTextSearchParameters;
use AwardWallet\Common\Geo\Google\ReverseGeoCodeParameters;
use AwardWallet\Common\Geo\Google\TimeZoneParameters;
use AwardWallet\Common\Geo\Google\TimeZoneResponse;

class MissGoogleApi extends GoogleApi
{
    public function placeTextSearch(PlaceTextSearchParameters $parameters)
    {
        return new PlaceSearchResponse(GoogleResponse::STATUS_ZERO_RESULTS);
    }

    public function placeDetails(PlaceDetailsParameters $parameters)
    {
        return new PlaceDetailsResponse(GoogleResponse::STATUS_ZERO_RESULTS);
    }

    public function placeAutocomplete(PlaceAutocompleteParameters $parameters)
    {
        return new PlaceAutocompleteResponse(GoogleResponse::STATUS_ZERO_RESULTS);
    }

    public function timeZone(TimeZoneParameters $parameters)
    {
        return new TimeZoneResponse(GoogleResponse::STATUS_ZERO_RESULTS);
    }

    public function geoCode(GeoCodeParameters $parameters)
    {
        return new GeoCodeResponse(GoogleResponse::STATUS_ZERO_RESULTS);
    }

    public function reverseGeoCode(ReverseGeoCodeParameters $parameters)
    {
        return new GeoCodeResponse(GoogleResponse::STATUS_ZERO_RESULTS);
    }

    public function directionSearch(DirectionParameters $parameters)
    {
        return new DirectionResponse(GoogleResponse::STATUS_ZERO_RESULTS);
    }


}