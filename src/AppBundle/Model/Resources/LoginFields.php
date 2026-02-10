<?php
namespace AppBundle\Model\Resources;

use JMS\Serializer\Annotation\Type;
use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;

trait LoginFields
{
    /**
     * @MongoDB\Field
     * @var string
     * @Type("string")
     */
    private $login;

    /**
     * @MongoDB\Field
     * @var string
     * @Type("string")
     */
    private $login2;

    /**
     * @MongoDB\Field
     * @var string
     * @Type("string")
     */
    private $login3;

    /**
     * @MongoDB\Field
     * @var string
     * @Type("string")
     */
    private $password;

    /**
     * @MongoDB\Field
     * @var string
     * @Type("string")
     */
    private $browserState;

    /**
     * @return string
     */
    public function getLogin()
    {
        return $this->login;
    }

    /**
     * @param string $login
     * @return $this
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
     * @return $this
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
     * @return $this
     */
    public function setLogin3($login3)
    {
        $this->login3 = $login3;
        return $this;
    }

    /**
     * @return string
     */
    public function getPassword()
    {
        return $this->password;
    }

    /**
     * @param string $password
     * @return $this
     */
    public function setPassword($password)
    {
        $this->password = $password;
        return $this;
    }

    /**
     * @return string
     */
    public function getBrowserstate()
    {
        return $this->browserState;
    }

    /**
     * @param string
     *
     * @return $this
     */
    public function setBrowserstate($browserState)
    {
        $this->browserState = $browserState;

        return $this;
    }

    public function getMaskedFields()
    {
        return [
            'Login' => $this->login,
            'Login2' => $this->login2,
            'Login3' => $this->login3,
            'Pass' => $this->password,
        ];
    }

}