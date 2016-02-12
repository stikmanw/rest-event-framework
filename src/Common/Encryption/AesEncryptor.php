<?php
namespace Common\Encryption;

/**
 * Class AesEncryptor
 * For encryption
 * @package Rope\Common\Encryption
 */
class AesEncryptor implements EncryptionInterface
{
    /**
     * @var string Encryption key
     */
    private $key;

    /**
     * @var int Mcrypt initialization vector size
     */
    private $initializationVectorSize;

    /**
     * @var resource mcrypt module resource
     */
    private $mcryptModule;

    /**
     * @var int encryption block size
     */
    private $blockSize;

    /**
     * @param string $key encryption key should be 16, 24 or 32 characters long form 128, 192, 256 bit encryption
     */
    public function __construct($key)
    {
        $this->mcryptModule = mcrypt_module_open('rijndael-256', '', 'cbc', '');
        if ($this->mcryptModule === false) {
            throw new \InvalidArgumentException("Unknown algorithm/mode.");
        }

        $keyLength = strlen($key);
        if ($keyLength > ($keyMaxLength = mcrypt_enc_get_key_size($this->mcryptModule))) {
            throw new \InvalidArgumentException("The key length must be less or equal than $keyMaxLength.");
        }
        if (!in_array($keyLength, array(16, 24, 32))) {
            throw new \InvalidArgumentException("Key length must be 16, 24 or 32 bytes for 128, 192, 256 bit encryption.");
        }

        $this->key = $key;

        $this->initializationVectorSize = mcrypt_enc_get_iv_size($this->mcryptModule);
        $this->blockSize = mcrypt_enc_get_block_size($this->mcryptModule);
    }

    /**
     * @param $data
     * @return string
     */
    public function encrypt($data)
    {
        $iv = mcrypt_create_iv($this->initializationVectorSize, MCRYPT_DEV_URANDOM);
        mcrypt_generic_init($this->mcryptModule, $this->key, $iv);
        $encrypted = mcrypt_generic($this->mcryptModule, $this->pad($data));
        mcrypt_generic_deinit($this->mcryptModule);
        return $iv. $encrypted;
    }

    /**
     * @param $encryptedData
     * @return string
     */
    public function decrypt($encryptedData)
    {
        $initializationVector = substr($encryptedData, 0, $this->initializationVectorSize);
        mcrypt_generic_init($this->mcryptModule, $this->key, $initializationVector);
        $decryptedData = mdecrypt_generic($this->mcryptModule, substr($encryptedData, $this->initializationVectorSize));
        mcrypt_generic_deinit($this->mcryptModule);
        return $this->unpad($decryptedData);
    }

    private function pad($data)
    {
        $pad = $this->blockSize - (strlen($data) % $this->blockSize);
        return $data . str_repeat(chr($pad), $pad);
    }

    private function unpad($data)
    {
        $pad = ord($data[strlen($data) - 1]);
        return substr($data, 0, -$pad);
    }

    public function __destruct()
    {
        mcrypt_module_close($this->mcryptModule);
    }

}
