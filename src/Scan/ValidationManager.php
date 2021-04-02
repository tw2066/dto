<?php

namespace Hyperf\DTO\Scan;

class ValidationManager
{
    /**
     * @var array
     */
    protected static array $content = [];

    public static function setRule($className,$fieldName,$rule)
    {
        $className = trim($className,'\\');
        static::$content[$className]['rule'][$fieldName] = $rule;
    }

    public static function setMessages($className,$key,$messages)
    {
        $className = trim($className,'\\');
        static::$content[$className]['messages'][$key] = $messages;
    }

    public static function getRule($className){
        $className = trim($className,'\\');
        if(!isset(static::$content[$className]['rule'])){
            return [];
        }
        return static::$content[$className]['rule'];
    }

    public static function getMessages($className){
        $className = trim($className,'\\');
        if(!isset(static::$content[$className]['messages'])){
            return [];
        }
        return static::$content[$className]['messages'];
    }

}