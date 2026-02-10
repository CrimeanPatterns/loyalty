<?php

namespace AppBundle\Extension;

use AppBundle\Model\Resources\VndError;
use AppBundle\Service\InvalidParametersException;
use JMS\Serializer\SerializerInterface;
use Monolog\Logger;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

class SwaggerErrorListener
{
    /** @var Logger */
    private $logger;
    /** @var SerializerInterface */
    private $serializer;

    const DEFAULT_MESSAGE = 'Input Validation Failure';

    /**
     * SwaggerErrorListener constructor.
     * @param Logger $logger
     */
    public function __construct(Logger $logger, SerializerInterface $serializer)
    {
        $this->logger = $logger;
        $this->serializer = $serializer;
    }

    /**
     * @param ExceptionEvent $event
     *
     * @throws \Exception
     */
    public function onKernelException(ExceptionEvent $event)
    {
        $headers = ['Content-Type' => 'application/vnd.error+json'];
        $logRef = uniqid();
        $vndError = (new VndError())->setLogref($logRef);

        try {
            $exception = $event->getThrowable();
            $code = $exception->getCode();

            if ($exception instanceof InvalidParametersException) {
                $statusCode = $exception->getStatusCode();
                $vndError->setMessage(empty($exception->getMessage()) ? self::DEFAULT_MESSAGE : $exception->getMessage())
                         ->setErrors($this->buildErrors($exception));
            } else {
                if ($exception instanceof NotFoundHttpException) {
                    $statusCode = Response::HTTP_NOT_FOUND;
                } else {
                    if ($exception instanceof MethodNotAllowedHttpException) {
                        $statusCode = Response::HTTP_METHOD_NOT_ALLOWED;
                    } elseif (
                        $exception instanceof AuthenticationException
                        ||
                        ($exception instanceof HttpException && $exception->getStatusCode() === Response::HTTP_UNAUTHORIZED)
                    ) {
                        $statusCode = Response::HTTP_UNAUTHORIZED;
                    } elseif ($exception instanceof AccessDeniedHttpException) {
                        $statusCode = Response::HTTP_FORBIDDEN;
                    } else {
                        $is3Digits = strlen($code) === 3;
                        $class     = (int)substr($code, 0, 1);
                        if (!$is3Digits) {
                            $statusCode = Response::HTTP_INTERNAL_SERVER_ERROR;
                        } else {
                            switch ($class) {
                                case 4:
                                    $statusCode = Response::HTTP_BAD_REQUEST;
                                    break;
                                case 5:
                                    $statusCode = Response::HTTP_INTERNAL_SERVER_ERROR;
                                    break;
                                default:
                                    $statusCode = Response::HTTP_INTERNAL_SERVER_ERROR;
                            }
                        }
                    }
                }
                $message  = Response::$statusTexts[$statusCode];
                $vndError->setMessage($message);
            }

            $responseMessage = "{$vndError->getMessage()}: $exception";
            if ($statusCode === Response::HTTP_INTERNAL_SERVER_ERROR) {
                $this->logger->critical($responseMessage, ['logref' => $logRef]);
            } else {
                $this->logger->notice($responseMessage, ['logref' => $logRef]);
            }
            $event->setResponse(
                new Response($this->serializer->serialize($vndError, 'json'), $statusCode, $headers)
            );
        } catch (\Exception $e) {
            $message = "Internal Server Error";
            $vndError = (new VndError())->setMessage($message)
                                        ->setLogref($logRef);

            $this->logger->critical("$message: $e", ['logref' => $logRef]);
            $event->setResponse(
                new Response($this->serializer->serialize($vndError, 'json'), Response::HTTP_INTERNAL_SERVER_ERROR, $headers)
            );
        }
    }

    /**
     * @param InvalidParametersException $exception
     *
     * @return array
     */
    public function buildErrors(InvalidParametersException $exception)
    {
        $errors = [];
        foreach ($exception->getValidationErrors() as $errorSpec) {
            if (!$errorSpec['property']) {
                $errorSpec['property'] = preg_replace('/the property (.*) is required/', '\\1', $errorSpec['message']);
            }

            $normalizedPropertyName = str_replace('request.', '',
                preg_replace('/\[\d+\]/', '', $errorSpec['property']));
            $errors[] = sprintf('[%s] %s', $normalizedPropertyName, $errorSpec['message']);
        }

        return !empty($errors) ? $errors : null;
    }
}