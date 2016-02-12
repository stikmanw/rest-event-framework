<?php
namespace Common\Tool;

use Symfony\Component\Filesystem\Filesystem;

class FileSystemUtility
{
    /**
     * Remove a directory and create it
     * @param $directory
     * @param int $mode
     * @throws \InvalidArgumentException
     */
    public static function removeAndCreate($directory, $mode = 0777)
    {
        static::removeDir($directory);
        $filesystem = new Filesystem();
        $filesystem->mkdir($directory, $mode);
    }

    /**
     * Create anew directory in the system temporary directory
     * @param $path
     * @param null $tmpDir
     * @return string
     */
    public static function removeAndCreateTemp($path, $tmpDir = null)
    {
        $temp = sys_get_temp_dir();
        if ($tmpDir && static::pathPermission($tmpDir)) {
            $temp = $tmpDir;
        }

        if ($path{0} === "/") {
            $path = $temp . $path;
        } else {
            $path = $temp . "/{$path}";
        }

        static::removeAndCreate($path);
        return $path;
    }

    public static function removeTemp($path, $tmpDir = null)
    {
        $temp = sys_get_temp_dir();
        if ($tmpDir && static::pathPermission($tmpDir)) {
            $temp = $tmpDir;
        }

        if ($path{0} === "/") {
            $path = $temp . $path;
        } else {
            $path = $temp . "/{$path}";
        }

        $filesystem = new Filesystem();
        $filesystem->remove($path);
    }

    public static function removeDir($directory)
    {
        // it's already gone brother, snap into a slim jim
        if (!is_dir($directory)) {
            return;
        }

        $filesystem = new Filesystem();
        if (preg_match("#^/+$#", $directory)) {
            throw new \InvalidArgumentException(
                "The directory you are attempting to remove/create is not allowed."
            );
        }

        $filesystem->remove($directory);
    }

    /**
     * Get the permission on the file 777/755 etc.
     * @link  http://php.net/manual/en/function.fileperms.php
     * @param $file
     * @return string
     */
    public static function pathPermission($file)
    {
        $length = strlen(decoct(fileperms($file))) - 3;
        return substr(decoct(fileperms($file)), $length);
    }

    /**
     * Fixes pesky double slashes.
     * @param $path
     * @return mixed
     */
    public static function fixPath($path)
    {
        return str_replace('//', "/", $path);
    }
}