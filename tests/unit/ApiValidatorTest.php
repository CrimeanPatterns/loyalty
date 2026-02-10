<?php

namespace Tests\Unit;


use AppBundle\Model\Resources\V2\CheckAccountResponse as V2Response;
use AppBundle\Service\ApiValidator;
use Psr\Log\LoggerInterface;

class ApiValidatorTest extends BaseTestClass
{

    /** @var ApiValidator */
    private $validator;

    public function _before()
    {
        parent::_before();
        $this->validator = new ApiValidator(
            $this->container->getParameter('kernel.cache_dir'),
            $this->getCustomMock(LoggerInterface::class)
        );
    }

    public function _after()
    {
        $this->validator = null;
        parent::_after();
    }

    public function testValid()
    {
        $json = file_get_contents(__DIR__ . "/../_data/v2ResponseExample.json");
        $responseArray = json_decode($json, true);
        $responseArray['itineraries'][0]['segments'][0]['marketingCarrier']['airline'] = [
            "name" => "Gol",
            "iata" => "G3",
            "icao" => "GLO"
        ];

        $definitionName = basename(str_replace('\\', '/', V2Response::class));
        $errors = $this->validator->validate(json_decode(json_encode($responseArray), false), $definitionName, 2);
        $this->assertEmpty($errors);
    }

    public function testInvalid()
    {
        $json = file_get_contents(__DIR__ . "/../_data/v2ResponseExample.json");
        $definitionName = basename(str_replace('\\', '/', V2Response::class));
        $errors = $this->validator->validate(json_decode($json, false), $definitionName, 2);
        $this->assertTrue(!empty($errors));
    }

}