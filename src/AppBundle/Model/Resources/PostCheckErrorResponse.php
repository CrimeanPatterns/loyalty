<?php

namespace AppBundle\Model\Resources;


use JMS\Serializer\Annotation\Type;

class PostCheckErrorResponse
{

    /**
     * @var string
     * @Type("string")
     */
    private $message;

    /**
     * @var string|null
     * @Type("string")
     */
    private $userData;

    public function __construct(string $message, ?string $userData)
    {
        $this->message = $message;
        $this->userData = $userData;
    }
}