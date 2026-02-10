<?php


namespace AppBundle\Service;


use Doctrine\DBAL\Connection;

class CryptPasswordService
{

    /** @var Connection */
    private $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function crypt(?string $pass, string $partner): ?string
    {
        if (empty($pass) || trim($pass) === '') {
            return $pass;
        }

        $sql = <<<SQL
            SELECT RequestPrivateKey FROM Partner
            WHERE Login = :LOGIN
SQL;
        $privateKey =  $this->connection->executeQuery($sql, [':LOGIN' => $partner])->fetchColumn();

        if (!empty($privateKey)) {
            $s = base64_decode($pass);
            //$key = openssl_pkey_get_private(SSLDecrypt($privateKey)); ??? copy from WsdlService.php
            $key = openssl_pkey_get_private(SSLDecrypt($privateKey));
            if (!openssl_private_decrypt($s, $pass, $key)) {
                throw new CryptPasswordServiceException("Can't decrypt password");
            }
            openssl_free_key($key);
        }

        return CryptPassword($pass);
    }

}