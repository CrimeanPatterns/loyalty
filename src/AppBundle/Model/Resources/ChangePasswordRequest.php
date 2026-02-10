<?php
namespace AppBundle\Model\Resources;

use AppBundle\Model\Resources\Interfaces\LoyaltyRequestInterface;
use JMS\Serializer\Annotation\Type;

class ChangePasswordRequest extends BaseCheckRequest
{
    use LoginFields;

    /**
     * @var string
     * @Type("string")
     */
    private $newPassword;

    /**
     * @return string
     */
    public function getNewPassword(): string
    {
        return $this->newPassword;
    }

    /**
     * @param string $newPassword
     * @return $this
     */
    public function setNewPassword(string $newPassword)
    {
        $this->newPassword = $newPassword;
        return $this;
    }
}
