<?php
namespace Common\Model;

/**
 * Type of model that can be hashed to make it easy to compare and find changes.
 *
 * We are going to want to persist our hash as an index one way or another
 * @Storage\Index(name="hash",columns={"hash"},unique=1);
 *
 */
use Common\Annotation\Type as Type;
use Common\Annotation\Storage as Storage;

class HashableModel extends BaseModel
{
    /**
     * @Type\String(length="32", fixed="1")
     * @var string
     */
    public $hash;

    /**
     * Here is a list of locally reserved words that we should not hash.
     * @var array
     */
    protected $reservedHash = array("_self");

    /**
     * Generate the hash for our model.
     * Which will take all the values
     * and hash them together into one string. Then md5 the result.
     *
     * It's important to note that we do not include in the hash the (hash itself its not data specific content)
     *
     * @return string
     */
    public function generateHash()
    {
        $hash = '';
        $dontHash = $this->dontHash();

        $vars = get_object_vars($this);
        foreach ($vars as $name => $member) {

            if (in_array($name, $dontHash) || in_array($name, $this->reservedHash)) {
                continue;
            }

            if ($member instanceof HashableModel) {
                $hash .= $member->generateHash();
            } elseif (is_scalar($member)) {
                $hash .= (string)$member;
            } elseif (is_null($member)) {
                continue;
            } else {
                $hash .= serialize($member);
            }
        }

        return md5($hash);
    }

    /**
     * List of items to return that we should not hash in our hashing algorithm
     *
     * @return array
     */
    protected function dontHash()
    {
        return array('hash', 'dateAdded', 'dateTimeAdded', 'lastUpdated');
    }
}
