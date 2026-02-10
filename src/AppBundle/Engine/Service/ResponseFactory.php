<?php


namespace AppBundle\Service;


use AppBundle\Model\Resources\Interfaces\LoyaltyResponseInterface;
use JMS\Serializer\SerializerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;

class ResponseFactory
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

    public function buildResponse(LoyaltyResponseInterface $response, int $apiVersion): Response
    {
        $cls = basename(str_replace('\\', '/', get_class($response)));

        $serializedResponse = $this->serializer->serialize($response, 'json');
        $responseStd = json_decode($serializedResponse, false);

        $errors = $this->validator->validate($responseStd, $cls, $apiVersion);
        if (!empty($errors)) {
            $this->logger->notice(
                'Response incompatible with operation schema',
                ['errors' => $errors, 'source' => 'ResponseFactory']
            );
            // throw new InvalidParametersException('Parameters incompatible with operation schema', $errors);
        }

        return new Response($this->serializer->serialize($response, 'json'), 200, ['Content-Type' => 'application/json']);
    }

    public function buildNoSwaggerResponse($data)
    {
        return new Response($this->serializer->serialize($data, 'json'), 200, ['Content-Type' => 'application/json']);
    }
}