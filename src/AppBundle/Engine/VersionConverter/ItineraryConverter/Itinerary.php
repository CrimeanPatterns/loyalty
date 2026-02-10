<?php


namespace AppBundle\VersionConverter\ItineraryConverter;


use AwardWallet\Common\Itineraries\Cancelled;
use AwardWallet\Common\Itineraries\FeesCollection;
use AwardWallet\Common\Itineraries\ProviderDetails;
use AwardWallet\Common\Itineraries\TotalPrice;
use AwardWallet\Common\Parsing\Solver\Extra\Extra;
use AwardWallet\Common\Itineraries\Itinerary as OutputItinerary;
use AwardWallet\Schema\Parser\Common\Itinerary as ParsedItinerary;
use Psr\Log\LoggerInterface;

abstract class Itinerary implements ConverterInterface
{

    /**
     * @var LoggerInterface $logger
     */
    protected $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function convert(ParsedItinerary $parsed, Extra $extra)
    {
        if ($parsed->getCancelled())
            return $this->convertCancelled($parsed);
		$r = $this->initItinerary($parsed, $extra);
		$this->convertCommon($parsed, $r, $extra);
		$this->convertItinerary($parsed, $r, $extra);
		return $r;
	}

    protected abstract function initItinerary(ParsedItinerary $parsed, Extra $extra): OutputItinerary;

    protected abstract function convertItinerary(ParsedItinerary $parsed, OutputItinerary $it, Extra $extra): OutputItinerary;

    protected function convertCommon(ParsedItinerary $parsed, OutputItinerary $it, Extra $extra): OutputItinerary
    {
	    $it->providerDetails = $this->convertProviderDetails($parsed, $extra);
	    $it->totalPrice = $this->convertPrice($parsed);
		return $it;
	}

    /**
     * @param ParsedItinerary $parsed
     * @param Extra $extra
     * @return ProviderDetails
     */
	protected function convertProviderDetails(ParsedItinerary $parsed, Extra $extra)
    {
        $r = new ProviderDetails($this->logger);
        if ($parsed->getTravelAgency() && $parsed->getTravelAgency()->getProviderCode())
            $code = $parsed->getTravelAgency()->getProviderCode();
        else
            $code = $parsed->getProviderCode();
        if ($code && $provider = $extra->data->getProvider($code)) {
            $r->name = $provider->name;
            $r->code = $provider->code;
        }
        $primary = null;
        $secondary = [];
        foreach($parsed->getConfirmationNumbers() as $pair) {
            if (null === $primary)
                $primary = $pair[0];
            elseif ($parsed->isConfirmationNumberPrimary($pair[0])) {
                if (isset($primary) && !in_array($primary, $secondary))
                    $secondary[] = $primary;
                $primary = $pair[0];
            }
            else
                $secondary[] = $pair[0];
        }
        if ($primary)
            $r->confirmationNumber = $primary;
        if (count($secondary) > 0)
            $r->confirmationNumbers = $secondary;
        $r->reservationDate = Util::date($parsed->getReservationDate());
        if ($parsed->getTravelAgency() && count($arr = $parsed->getTravelAgency()->getConfirmationNumbers()) > 0)
            $r->tripNumber = array_shift($arr)[0];
        $arr = null;
        if (count($parsed->getAccountNumbers()) > 0)
            $arr = $parsed->getAccountNumbers();
        elseif ($parsed->getTravelAgency() && count($parsed->getTravelAgency()->getAccountNumbers()) > 0)
            $arr = $parsed->getTravelAgency()->getAccountNumbers();
        if ($arr) {
            $accountNumbers = array_map(function($pair){return $pair[0];}, $arr);
            $r->accountNumbers = implode(", ", $accountNumbers);
        }
        $r->status = $parsed->getStatus();
        $r->earnedAwards = $parsed->getEarnedAwards();
        return $r;
    }

    /**
     * @param ParsedItinerary $parsed
     * @return TotalPrice|null
     */
    protected function convertPrice(ParsedItinerary $parsed)
    {
        if (!($price = $parsed->getPrice()))
            return null;
        $r = new TotalPrice($this->logger);
        $r->cost = (null !== $price->getCost()) ? floatval($price->getCost()) : null;
        $r->total = (null !== $price->getTotal()) ? floatval($price->getTotal()) : null;
        $r->currencyCode = $price->getCurrencyCode();
        $r->discount = (null !== $price->getDiscount()) ? floatval($price->getDiscount()) : null;
        $r->spentAwards = $price->getSpentAwards();
        foreach($price->getFees() as $pair) {
            $pair[1] = floatval($pair[1]);
            if ($pair[0] === 'Tax' && empty($r->tax))
                $r->tax = floatval($pair[1]);
            else {
                if (!isset($r->fees))
                    $r->fees = new FeesCollection($this->logger);
                $fee = $r->fees->add();
                $fee->name = $pair[0];
                $fee->charge = $pair[1];
            }
        }
        return $r;
    }

	protected function convertCancelled(ParsedItinerary $parsed)
    {
	    $r = new Cancelled();
	    if (count($arr = $parsed->getConfirmationNumbers()) > 0)
	        $r->confirmationNumber = array_shift($arr)[0];
	    $r->itineraryType = Util::getCancelledType($parsed);
	    return $r;
    }

}