<?php

namespace AppBundle\Document;

use DateTime;
use AppBundle\Repository\RaAccountRepository;
use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;

/**
 * @MongoDB\Document(repositoryClass=RaAccountRepository::class)
 * @MongoDB\Indexes({
 *     @MongoDB\UniqueIndex(keys={"provider" = "asc", "login" = "asc"}),
 *     @MongoDB\UniqueIndex(keys={"provider" = "asc", "email" = "asc"}),
 *     @MongoDB\Index(keys={"provider" = "asc", "lastUseDate" = "asc"})
 * })
 */

class RaAccount
{

    public const STATE_ENABLED = 1;
    public const STATE_DISABLED = 0;
    public const STATE_RESERVE = 2;
    public const STATE_DEBUG = -1;
    public const STATE_LOCKED = -2;
    public const STATE_INACTIVE = -3;

    public const STATES = [
        self::STATE_ENABLED,
        self::STATE_DISABLED,
        self::STATE_RESERVE,
        self::STATE_DEBUG,
        self::STATE_LOCKED,
        self::STATE_INACTIVE
 ];

    public const STATES_LABEL = [
        self::STATE_ENABLED => 'enabled',
        self::STATE_DISABLED => 'disabled',
        self::STATE_RESERVE => 'reserved',
        self::STATE_DEBUG => 'debug',
        self::STATE_LOCKED => 'locked',
        self::STATE_INACTIVE => 'inactive'
    ];

    public const PARSE_LOCK = 1;
    public const PARSE_UNLOCK = 0;

    public const WARMUP_LOCK = -1;
    public const WARMUP_NONE = 0;
    public const WARMUP_DONE = 1;

    /** @MongoDB\Id */
    protected $id;

    /** @MongoDB\Field(type="string") */
    protected $provider;

    /** @MongoDB\Field(type="string") */
    protected $login;

    /** @MongoDB\Field(type="string") */
    protected $login2;

    /** @MongoDB\Field(type="string") */
    protected $login3;

    /** @MongoDB\Field(type="string") */
    protected $pass;

    /** @MongoDB\Field(type="string") */
    protected $question;

    /** @MongoDB\EmbedMany(
     *     strategy="setArray",
     *     targetDocument="RaAccountAnswer"
     * )
     */
    protected $answers = [];

    /** @MongoDB\Field(type="string") */
    protected $email;

    /** @MongoDB\Field(type="date") */
    protected $createDate;

    /** @MongoDB\Field(type="date") */
    protected $updateDate;

    /** @MongoDB\Field(type="date") */
    protected $lastUseDate;

    /** @MongoDB\Field(type="int") */
    protected $errorCode = 0;

    /** @MongoDB\Field(type="int") */
    protected $state = self::STATE_ENABLED;

    /** @MongoDB\Field(type="int") */
    protected $warmedUp = self::WARMUP_NONE;

    /** @MongoDB\Field(type="int") */
    protected $lockState = self::PARSE_UNLOCK;

    /** @MongoDB\EmbedMany(
     *     strategy="setArray",
     *     targetDocument="RaAccountRegisterInfo"
     * )
     */
    protected $registerInfo = [];

    public function __construct($provider = null, $login = null, $pass = null, $email = null)
    {
        $this->provider = $provider;
        $this->login = $login;
        $this->pass = $pass;
        $this->email = strtolower($email);
        $this->createDate = new DateTime();
        $this->updateDate = new DateTime();
        $this->lastUseDate = new DateTime();
    }

    /**
     * @param string $id
     * @return RaAccount
     */
    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @return string
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getProvider()
    {
        return $this->provider;
    }

    /**
     * @param string $provider
     * @return RaAccount
     */
    public function setProvider($provider)
    {
        $this->provider = $provider;
        return $this;
    }

    /**
     * @return string
     */
    public function getLogin()
    {
        return $this->login;
    }

    /**
     * @param string $login
     * @return RaAccount
     */
    public function setLogin($login)
    {
        $this->login = $login;
        return $this;
    }

    /**
     * @return string
     */
    public function getLogin2()
    {
        return $this->login2;
    }

    /**
     * @param string $login2
     * @return RaAccount
     */
    public function setLogin2($login2)
    {
        $this->login2 = $login2;
        return $this;
    }

    /**
     * @return string
     */
    public function getLogin3()
    {
        return $this->login3;
    }

    /**
     * @param string $login3
     * @return RaAccount
     */
    public function setLogin3($login3)
    {
        $this->login3 = $login3;
        return $this;
    }

    /**
     * @return string
     */
    public function getPass()
    {
        return $this->pass;
    }

    /**
     * @param string $pass
     * @return RaAccount
     */
    public function setPass($pass)
    {
        $this->pass = $pass;
        return $this;
    }

    /**
     * @return string
     */
    public function getQuestion()
    {
        return $this->question;
    }

    /**
     * @param string $question
     * @return RaAccount
     */
    public function setQuestion($question)
    {
        $this->question = $question;
        return $this;
    }

    /**
     * @param RaAccountAnswer[] $answers
     * @return RaAccount
     */
    public function setAnswers(array $answers)
    {
        $this->answers = $answers;
        return $this;
    }

    /**
     * @param RaAccountAnswer $answer
     * @return RaAccount
     */
    public function addAnswer(RaAccountAnswer $answer)
    {
        $this->answers[] = $answer;
        return $this;
    }

    /**
     * @return RaAccountAnswer[]
     */
    public function getAnswers()
    {
        return $this->answers;
    }

    /**
     * @return string
     */
    public function getEmail()
    {
        return $this->email;
    }

    /**
     * @param string $email
     * @return RaAccount
     */
    public function setEmail($email)
    {
        $this->email = $email;
        return $this;
    }

    /**
     * @return DateTime
     */
    public function getCreateDate()
    {
        return $this->createDate;
    }

    /**
     * @param DateTime $createDate
     * @return RaAccount
     */
    public function setCreateDate($createDate)
    {
        $this->createDate = $createDate;
        return $this;
    }

    /**
     * @return DateTime
     */
    public function getUpdateDate()
    {
        return $this->updateDate;
    }

    /**
     * @param DateTime $updateDate
     * @return RaAccount
     */
    public function setUpdateDate($updateDate)
    {
        $this->updateDate = $updateDate;
        return $this;
    }

    /**
     * @return DateTime
     */
    public function getLastUseDate()
    {
        return $this->lastUseDate;
    }

    /**
     * @param DateTime $lastUseDate
     * @return RaAccount
     */
    public function setLastUseDate($lastUseDate)
    {
        $this->lastUseDate = $lastUseDate;
        return $this;
    }

    /**
     * @return int
     */
    public function getErrorCode()
    {
        return $this->errorCode;
    }

    /**
     * @param int $errorCode
     * @return RaAccount
     */
    public function setErrorCode($errorCode)
    {
        $this->errorCode = $errorCode;
        return $this;
    }

    /**
     * @return int
     */
    public function getState()
    {
        return $this->state;
    }

    /**
     * @param int $state
     * @return RaAccount
     */
    public function setState($state)
    {
        $this->state = $state;
        return $this;
    }

    /**
     * @return int
     */
    public function getWarmedUp(): int
    {
        return $this->warmedUp;
    }

    /**
     * @param int $warmedUp
     * @return RaAccount
     */
    public function setWarmedUp(int $warmedUp): RaAccount
    {
        $this->warmedUp = $warmedUp;
        return $this;
    }

    /**
     * @return int
     */
    public function getLockState(): int
    {
        return $this->lockState;
    }

    /**
     * @param int $lockState
     * @return RaAccount
     */
    public function setLockState(int $lockState): RaAccount
    {
        $this->lockState = $lockState;
        return $this;
    }

    /**
     * @param RaAccountRegisterInfo[] $registerInfo
     * @return RaAccount
     */
    public function setRegisterInfo(array $registerInfo): RaAccount
    {
        $this->registerInfo = $registerInfo;
        return $this;
    }

    /**
     * @param RaAccountRegisterInfo $data
     * @return RaAccount
     */
    public function addInfo(RaAccountRegisterInfo $data): RaAccount
    {
        $this->registerInfo[] = $data;
        return $this;
    }

    /**
     * @return RaAccountRegisterInfo[]
     */
    public function getRegisterInfo()
    {
        return $this->registerInfo;
    }

}