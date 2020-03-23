<?php
require_once __DIR__."/ISocketServer.php";

abstract class SocketServer
{
    public static function factory($type, $address)
    {
        $className = ucfirst($type)."SocketServer";
        if (!class_exists($className)) {
            $classPath = __DIR__."/".$className.".php";
            if (!file_exists($classPath)) {
                throw new SocketServerException("Socket File Server Not Found");
            } 
            require_once $classPath;
        }

        return new $className($address);
    }
}

class SocketServerException extends Exception
{}