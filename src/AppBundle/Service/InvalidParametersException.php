<?php


namespace AppBundle\Service;

use Symfony\Component\HttpFoundation\Exception\RequestExceptionInterface;
use Symfony\Component\HttpKernel\Exception\HttpException;

class InvalidParametersException extends HttpException
{

    /** @var array */
    private $errors;

    public function __construct(string $message, array $errors, $code = 400)
    {
        parent::__construct($code, $message);
        $this->errors = $errors;
    }

    public function getValidationErrors(): array
    {
        return $this->errors;
    }

}