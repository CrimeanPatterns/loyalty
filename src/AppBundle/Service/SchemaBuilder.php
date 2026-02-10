<?php


namespace AppBundle\Service;


use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;
use Symfony\Component\Yaml\Yaml;

class SchemaBuilder implements CacheWarmerInterface
{

    /** @var string */
    private $swaggerDist;

    public const HIDDEN_SCHEMA_FILENAME = 'swagger-schema-hidden.json';
    public const HIDDEN_AW_SCHEMA_FILENAME = 'swagger-schema-hidden-aw.json';

    public static function schemaFileName(int $apiVersion)
    {
        return sprintf('swagger-schema-v%s.json', $apiVersion);
    }

    public function __construct(string $swaggerDist)
    {
        $this->swaggerDist = $swaggerDist;
    }

    /**
     * Warms up the cache.
     *
     * @param string $cacheDir The cache directory
     */
    public function warmUp($cacheDir)
    {
        foreach ([1, 2] as $apiVersion) {
            $uri = $this->swaggerDist . "/swagger_v{$apiVersion}.yml";
            $document = $this->parseYamlDoc($uri);
            file_put_contents($cacheDir . '/' . self::schemaFileName($apiVersion), json_encode($document));
        }

        file_put_contents(
            $cacheDir . '/' . self::HIDDEN_SCHEMA_FILENAME,
            json_encode(
                $this->parseYamlDoc($this->swaggerDist . "/swagger_hidden.yml")
            )
        );
        file_put_contents(
            $cacheDir . '/' . self::HIDDEN_AW_SCHEMA_FILENAME,
            json_encode(
                $this->parseYamlDoc($this->swaggerDist . "/swagger_hidden_aw.yml")
            )
        );
    }

    private function parseYamlDoc($source)
    {
        $path = pathinfo($source);
        $result = Yaml::parse(file_get_contents($source), Yaml::PARSE_OBJECT);
        if (isset($result['imports']) && !empty($result['imports'])) {
            foreach ($result['imports'] as $import) {
                if ($import['resource'][0] === "/") {
                    $importPath = $import['resource'];
                } else {
                    $importPath = $path['dirname'] . "/" . $import['resource'];
                }
                $parsedImport = $this->parseYamlDoc($importPath);
                $result = array_merge_recursive($result, $parsedImport);
            }
        }

        return $result;
    }

    /**
     * @return bool true if the warmer is optional, false otherwise
     */
    public function isOptional()
    {
        return false;
    }

}
