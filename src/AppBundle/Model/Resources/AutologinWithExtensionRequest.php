<?php
namespace AppBundle\Model\Resources;

use AppBundle\Worker\CheckExecutor\BrowserExtensionRequestInterface;
use Doctrine\Common\Collections\Collection;
use JMS\Serializer\Annotation\Type;
use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;

/** @MongoDB\EmbeddedDocument */
class AutologinWithExtensionRequest extends BaseCheckRequest implements BrowserExtensionRequestInterface
{
    use LoginFields, BrowserExtensionRequestFields;

    /**
     * @MongoDB\EmbedMany(targetDocument="AppBundle\Model\Resources\Answer")
     * @var Answer[]|Collection
     * @Type("array<AppBundle\Model\Resources\Answer>")
     */
    private $answers;

    /**
     * @return Answer[]|Collection
     */
    public function getAnswers()
    {
        return $this->answers;
    }

    /**
     * @param Answer[]|Collection $answers
     */
    public function setAnswers($answers): void
    {
        $this->answers = $answers;
    }

}
