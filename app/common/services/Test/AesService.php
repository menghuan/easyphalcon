<?php
namespace Common\Services\SmsPlatForm;

/**
 * AES加密
 * @author 苏云雷 <suyunlei@qiaodata.com>
 * @date 2018-3-6 14:00:00
 */
class AesService extends \Common\Services\BaseService
{
    private $hex_iv = '00000000000000000000000000000000'; # converted JAVA byte code in to HEX and placed it here
    private $key = ""; #Same as in JAVA

    function __construct($key = '', $algorithm = 'sha256')
    {
        $this->key = hash($algorithm, $key, true);
    }

    /**
     * AES加密
     * @author 董光明 <bright87@163.com>
     * @param string $str 明文字符串，要加密的字符串。
     * @date 2016-10-25 09:33:48
     * @return string
     */
    public function encrypt($str)
    {
        $td = mcrypt_module_open(MCRYPT_RIJNDAEL_128, "", MCRYPT_MODE_CBC, "");
        mcrypt_generic_init($td, $this->key, $this->hexToStr($this->hex_iv));
        $block = mcrypt_get_block_size(MCRYPT_RIJNDAEL_128, MCRYPT_MODE_CBC);
        $pad = $block - (strlen($str) % $block);
        $str .= str_repeat(chr($pad), $pad);
        $encrypted = mcrypt_generic($td, $str);
        mcrypt_generic_deinit($td);
        mcrypt_module_close($td);
        return base64_encode($encrypted);
    }

    /**
     * 解密字符串
     * @author 董光明 <bright87@163.com>
     * @date 2016-11-07 21:10
     * @param type $code 密文字符串，要解密的字符串。
     * @return string
     */
    public function decrypt($code)
    {
        $td = mcrypt_module_open(MCRYPT_RIJNDAEL_128, "", MCRYPT_MODE_CBC, "");
        mcrypt_generic_init($td, $this->key, $this->hexToStr($this->hex_iv));
        $str = mdecrypt_generic($td, base64_decode($code));
        $block = mcrypt_get_block_size(MCRYPT_RIJNDAEL_128, MCRYPT_MODE_CBC);
        mcrypt_generic_deinit($td);
        mcrypt_module_close($td);
        return $this->strippadding($str);
    }

    /*
      For PKCS7 padding
     */
    private function addpadding($string, $blocksize = 16)
    {
        $len = strlen($string);
        $pad = $blocksize - ($len % $blocksize);
        $string .= str_repeat(chr($pad), $pad);
        return $string;
    }

    /**
     * 字符串填充
     * @author 董光明 <bright87@163.com>
     * @param type $string
     * @return boolean
     */
    private function strippadding($string)
    {
        $slast = ord(substr($string, -1));
        $slastc = chr($slast);
        $pcheck = substr($string, -$slast);
        if (preg_match("/$slastc{" . $slast . "}/", $string)) {
            $string = substr($string, 0, strlen($string) - $slast);
            return $string;
        } else {
            return false;
        }
    }

    private function hexToStr($hex)
    {
        $string = "";
        for ($i = 0; $i < strlen($hex) - 1; $i += 2) {
            $string .= chr(hexdec($hex[$i] . $hex[$i + 1]));
        }
        return $string;
    }
}
