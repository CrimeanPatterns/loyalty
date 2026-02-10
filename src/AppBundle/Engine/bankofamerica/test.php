<?php

require __DIR__ . "/../../kernel/public.php";
/*
 function skwEncrypt(data) {
     return Crypto.AES.encrypt(data, Crypto.charenc.UTF8.stringToBytes(skwSettings.skwEncryptKey),
                                 { mode: new Crypto.mode.ECB(Crypto.pad.NoPadding) });
 }

 */
$key = 'E75B02113569A3C6';
//$key = "\xE7\x5B\x02\x11\x35\x69\xA3\xC6";

echo "expected: " . urldecode("GHnTwFpMH%2BVc%2FjAe4T6vgg%3D%3D") . "<br><br>";

echo "mcrypt:<br>";
var_dump(base64_encode(mcrypt_encrypt(MCRYPT_RIJNDAEL_256, $key, "hello", MCRYPT_MODE_ECB)));
echo "<br>openssl:<br>";
var_dump(base64_encode(openssl_encrypt('hello', 'aes-256-ecb', $key, true)));
echo "<br>user function:<br>";
var_dump(base64_encode(encrypt('hello', $key)));
echo "<br>";

function encrypt($plain, $key)
{
    $crypted = mcrypt_encrypt(MCRYPT_RIJNDAEL_128, $key, $plain, MCRYPT_MODE_ECB);
    var_dump($crypted);

    return $crypted;
}

function decrypt($crypted, $key)
{
    $crypted = base64_decode($crypted);
    $iv = substr($crypted, 0, 16);
    $key = PBKDF2($key, $iv, 1, 32);
    $crypted = substr($crypted, 16);

    return mcrypt_decrypt(MCRYPT_RIJNDAEL_128, $key, $crypted, MCRYPT_MODE_CBC, $iv);
}

/**
 * PHP PBKDF2 Implementation.
 *
 * For more information see: http://www.ietf.org/rfc/rfc2898.txt
 *
 * @param string $p             password
 * @param string $s             salt
 * @param int $c            iteration count (use 1000 or higher)
 * @param int $dkl  derived key length
 * @param string $algo  hash algorithm
 *
 * @return string                       derived key of correct length
 */
function PBKDF2($p, $s, $c, $dkl, $algo = 'sha1')
{
    $kb = ceil($dkl / strlen(hash($algo, null, true)));
    $dk = '';

    for ($block = 1; $block <= $kb; ++$block) {
        $ib = $b = hash_hmac($algo, $s . pack('N', $block), $p, true);

        for ($i = 1; $i < $c; ++$i) {
            $ib ^= ($b = hash_hmac($algo, $b, $p, true));
        }
        $dk .= $ib;
    }

    return substr($dk, 0, $dkl);
}
