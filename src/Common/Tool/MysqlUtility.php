<?php
namespace Common\Tool;

class MysqlUtility
{
    public static function convertWildcardSearch($searchString)
    {
        return str_replace("*", "%", $searchString);
    }
}