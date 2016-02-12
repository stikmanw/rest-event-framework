<?php
namespace Common\Storage\Credential;

/**
 * This class is used to create / fetch credentials for a mysql database
 * using the encrypted secret key.
 *
 * @package Common\Storage\Credential
 */
use Common\Encryption\AesEncryptor;
use Symfony\Component\HttpFoundation\File\Exception\FileException;

class MysqlEncryption
{
    private $secretKey;

    private $host;

    private $user;

    private $password;

    private $hashAlgorithm;

    public function __construct($secretKey)
    {
        $this->secretKey = $secretKey;
    }

    public function setHashAlgorithm(callable $algorithm)
    {
        $this->hashAlgorithm = $algorithm;
    }

    public function getHashAlgorithm()
    {
        if(is_callable($this->hashAlgorithm)) {
            return $this->hashAlgorithm;
        } else {
            return function($secretKey) {
               return hash('ripemd128', $secretKey);
            };
        }
    }

    public function setHost($host)
    {
        $this->host = $host;
        return $this;
    }

    public function setUser($user)
    {
        $this->user = $user;
        return $this;
    }

    public function setPassword($password)
    {
        $this->password = $password;
        return $this;
    }

    public function getUser()
    {
        return $this->user;
    }

    public function getPassword()
    {
        return $this->password;
    }

    public function getHost()
    {
        return $this->host;
    }

    public function getKey()
    {
        $hash = $this->getHashAlgorithm();
        return $hash($this->secretKey);
    }

    public function encrypt()
    {
        if(!isset($this->user) || !isset($this->password) || !isset($this->host)) {
            throw new \RuntimeException("User,Password,Host must be set in order to encrypt the connection details");
        }

        $key = $this->getKey();
        $details = $this->toArray();
        $details = serialize($details);

        $encryptor = new AesEncryptor($key);
        $encDetails = $encryptor->encrypt($details);

        return $encDetails;
    }

    public function decrypt($encDetails)
    {
        $key = $this->getKey();
        $encryptor = new AesEncryptor($key);
        $details = $encryptor->decrypt($encDetails);

        $result = unserialize($details);
        if(!isset($result["user"]) || !isset($result["password"]) || !isset($result["host"])) {
            throw new \RuntimeException("User,Password,Host must be set in order to encrypt the connection details");
        }

        $this->setHost($result["host"]);
        $this->setUser($result["user"]);
        $this->setPassword($result["password"]);
    }

    public function encryptToFile($file)
    {
        $result = $this->encrypt();
        if(file_put_contents($file, $result) === false) {
            throw new FileException("Failed to write encryption data to file");
        }
    }

    public function decryptFromFile($file)
    {
        if(($result = file_get_contents($file)) === false) {
            throw new FileException("Failed to read file passed in");
        }

        $this->decrypt($result);
    }

    public function toArray()
    {
        return array(
            "user" => $this->getUser(),
            "password" => $this->getPassword(),
            "host" => $this->getHost()
        );
    }

    public function fromArray(array $data)
    {
        $this->user = $data["user"];
        $this->password = $data["password"];
        $this->host = $data["host"];
    }

    public function transfer(&$mixed)
    {
        if(is_array($mixed)) {
            $mixed["user"] = $this->getUser();
            $mixed["password"] = $this->getPassword();
            $mixed["host"] = $this->getHost();
        }

        if(is_object($mixed)) {
            $mixed->user = $this->getUser();
            $mixed->password = $this->getPassword();
            $mixed->host = $this->getHost();
        }

    }


}