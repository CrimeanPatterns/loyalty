<?php


namespace AppBundle\Service;


use AppBundle\Model\Resources\Interfaces\LoyaltyRequestInterface;
use AppBundle\Model\Resources\RewardAvailability\RaHotel\RaHotelRequest;
use AppBundle\Model\Resources\RewardAvailability\RewardAvailabilityRequest;
use JMS\Serializer\SerializerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;

class RequestFactory
{

    /** @var LoggerInterface */
    private $logger;

    /** @var ApiValidator */
    private $validator;

    /** @var SerializerInterface */
    private $serializer;

    public function __construct(LoggerInterface $logger, ApiValidator $validator, SerializerInterface $serializer)
    {
        $this->logger = $logger;
        $this->validator = $validator;
        $this->serializer = $serializer;
    }

    public function buildRequest(
        Request $request,
        string $type,
        int $apiVersion,
        bool $validateViaSwaggerSchema = true
    ): LoyaltyRequestInterface {
        $cls = basename(str_replace('\\', '/', $type));
        $requestStd = json_decode($request->getContent(), false);

        if (!$requestStd instanceof \stdClass) {
            throw new InvalidParametersException('Invalid JSON format', []);
        }

        if ($validateViaSwaggerSchema) {
            $isHiddenVersion = ($type === RewardAvailabilityRequest::class || $type === RaHotelRequest::class);
            $onlyAW = ($type === RaHotelRequest::class);
            $errors = $this->validator->validate($requestStd, $cls, $apiVersion, $isHiddenVersion, $onlyAW);
            if (!empty($errors)) {
                throw new InvalidParametersException('Parameters incompatible with operation schema', $errors);
            }
        }

        return $this->serializer->deserialize($request->getContent(), $type, 'json');
    }


}