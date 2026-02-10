<?php
namespace AppBundle\Model\Resources;

use AppBundle\Model\Resources\Interfaces\LoyaltyRequestInterface;
use JMS\Serializer\Annotation\Type;

/**
 * Generated resource DTO for 'CheckConfirmationRequest'.
 */
class CheckConfirmationRequest extends BaseCheckRequest implements LoyaltyRequestInterface
{

    use BrowserExtensionRequestFields;
    /**
     * @var InputField[]
     * @Type("array<AppBundle\Model\Resources\InputField>")
     */
    private $fields;

    /**
     * @return array
     */
    public function getFields()
    {
        return $this->fields;
    }

    /**
     * @param array
     * @return $this
     */
    public function setFields($fields)
    {
        $this->fields = $fields;

        return $this;
    }

}
