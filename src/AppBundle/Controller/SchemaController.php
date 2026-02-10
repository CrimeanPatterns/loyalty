<?php


namespace AppBundle\Controller;

use AppBundle\Service\ApiValidator;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class SchemaController
{

    private const CURRENT_SCHEMA_VERSION = 2;

    /** @var ApiValidator */
    private $validator;

    public function __construct(ApiValidator $validator)
    {
        $this->validator = $validator;
    }

    /**
     * @Route("/swagger-schema", name="full_swagger_schema")
     */
    public function specAction(Request $request)
    {
        $schema = $this->validator->getUnresolvedSchema(self::CURRENT_SCHEMA_VERSION);
        unset($schema->imports);
        return new JsonResponse($schema, 200, ['Content-Type' => 'text/plain']);
    }


}