<?php
namespace AppBundle\Model\Resources;

use AppBundle\Model\Resources\Interfaces\LoyaltyResponseInterface;
use JMS\Serializer\Annotation\Type;

class PostCheckPackageResponse implements LoyaltyResponseInterface
{
    /**
     * @var PostCheckResponse[]
     * @Type("array<AppBundle\Model\Resources\PostCheckResponse>")
     */
    private $package;

    /**
     * @var PostCheckErrorResponse[]
     * @Type("array<AppBundle\Model\Resources\PostCheckErrorResponse>")
     */
    private $errors;

    /**
     * @param array
     *
     * @return $this
     */
    public function setPackage($package)
    {
        $this->package = $package;

        return $this;
    }

    /**
     * @return array
     */
    public function getPackage()
    {
        return $this->package;
    }

    public function getErrors(): ?array
    {
        return $this->errors;
    }

    public function setErrors(array $errors): self
    {
        $this->errors = $errors;
        return $this;
    }
}
