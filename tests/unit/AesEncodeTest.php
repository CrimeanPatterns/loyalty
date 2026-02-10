<?php

namespace Tests\Unit;

/**
 * @backupGlobals disabled
 */
class AesEncodeTest extends BaseTestClass
{

    public function _before()
    {
        parent::_before();
        $this->container->get("aw.old_loader");
    }

    public function testMcrypt()
    {
        $data = "some data " . bin2hex(random_bytes(10));
        $key = "SomeCodeKey";

        $encoded = $this->OldAESEncode($data, $key);
        $this->assertEquals($data, $this->oldAESDecode($encoded, $key));

        $this->assertEquals($data, AESDecode($encoded, $key));
    }


    function oldAESEncode($source, $key)
    {
        $algoritmh = MCRYPT_RIJNDAEL_256;
        $s = "";
        $td = mcrypt_module_open($algoritmh, '', MCRYPT_MODE_ECB, '');
        $iv_size = mcrypt_enc_get_iv_size($td);
        $key = substr($key, 0, mcrypt_enc_get_key_size($td));
        $iv = mcrypt_create_iv($iv_size, MCRYPT_RAND);

        if (mcrypt_generic_init($td, $key, $iv) != -1) {
            $s = mcrypt_generic($td, $source);
            mcrypt_generic_deinit($td);
            mcrypt_module_close($td);
        }

        return $s;
    }

    function oldAESDecode($source, $key) {
        $algoritmh = MCRYPT_RIJNDAEL_256;
    	$s = "";
    	$td = mcrypt_module_open($algoritmh, '', MCRYPT_MODE_ECB, '');
    	$iv_size = mcrypt_enc_get_iv_size($td);
    	$key = substr($key, 0, mcrypt_enc_get_key_size($td));
    	$iv = mcrypt_create_iv($iv_size, MCRYPT_RAND);

    	if (mcrypt_generic_init($td, $key, $iv) != -1) {
    		$s = mdecrypt_generic($td, $source);
    		mcrypt_generic_deinit($td);
    		mcrypt_module_close($td);
    	}

    	return trim($s);
    }

}