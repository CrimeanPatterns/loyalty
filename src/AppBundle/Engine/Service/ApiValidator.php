<?php


namespace AppBundle\Service;


use JsonSchema\SchemaStorage;
use JsonSchema\Uri\UriRetriever;
use JsonSchema\Validator;
use Psr\Log\LoggerInterface;

class ApiValidator
{

    /** @var string */
    private $swaggerDist;

    /** @var SchemaStorage */
    private $schemaStorage;

    /** @var array */
    private $addedSchemas = [];

    /** @var LoggerInterface */
    private $logger;

    public function __construct(string $swaggerDist, LoggerInterface $logger)
    {
        $this->swaggerDist = $swaggerDist;
        $this->schemaStorage = new SchemaStorage();
        $this->logger = $logger;
    }

    /**
     * @param \stdClass $response
     * @param string $definitionName
     * @param int $apiVersion
     * @return array $errors List of json-schema validation errors
     *      $errors = [
     *          0 => [
     *              'property' => 'itineraries[0].itineraryType',
     *              'message' => 'The property itineraryType is required',
     *              'constraint' => 'required'
     *          ],
     *          1 => [
     *              'property' => 'itineraries[0].type',
     *              'message' => 'Does not have a value in the enumeration ["cancelled"]',
     *              'constraint' => 'enum'
     *          ],
     *      ]
     */
    public function validate(\stdClass $response, string $definitionName, int $apiVersion, bool $isHiddenVersion = false, ?bool $onlyAW = false): array
    {
        $document = $this->getDocument($apiVersion, $isHiddenVersion, $onlyAW);
        if (!property_exists($document->definitions, $definitionName)) {
            $this->logger->critical('Unknown Validator schema', ['schema' => $document->id, 'definition' => $definitionName]);
            return [];
        }

        $schema = $document->definitions->$definitionName;
        $errors = [];

        $validator = new Validator();
        $validator->validate($response, $schema);

        if (!$validator->isValid()) {
            $errors = $validator->getErrors();
        }

        return $errors;
    }

    public function getUnresolvedSchema(int $apiVersion, bool $isHiddenVersion = false, ?bool $onlyAW = false): \stdClass
    {
        if ($isHiddenVersion) {
            $fileName = $onlyAW ? SchemaBuilder::HIDDEN_AW_SCHEMA_FILENAME : SchemaBuilder::HIDDEN_SCHEMA_FILENAME;
        } else {
            $fileName = SchemaBuilder::schemaFileName($apiVersion);
        }
        return (new UriRetriever())
            ->retrieve(
                sprintf('file://%s/%s', $this->swaggerDist, $fileName)
            );
    }

    private function getDocument(int $apiVersion, bool $isHiddenVersion, bool $onlyAW): \stdClass
    {
        $schemaId = $isHiddenVersion ? 'hidden' : $apiVersion;
        if (!in_array($schemaId, $this->addedSchemas, true)) {
            $this->schemaStorage->addSchema($schemaId, $this->getUnresolvedSchema($apiVersion, $isHiddenVersion, $onlyAW));
            $this->addedSchemas[] = $schemaId;
        }

        return $this->schemaStorage->getSchema($schemaId);
    }

}