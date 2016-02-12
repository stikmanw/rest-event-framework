<?php
namespace Common\Encryption;

interface EncryptionInterface
{
    /**
     * @param $data
     * @return mixed
     */
    public function encrypt($data);

    /**
     * @param $data
     * @return mixed
     */
    public function decrypt($data);
}