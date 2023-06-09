<?php
namespace Core;

use Core\Request;
use Core\Response;

require __DIR__ . "/Request.php";
require __DIR__ . "/Controller.php";
require __DIR__ . "/Floor.php";
require __DIR__ . "/Response.php";
require __DIR__ . "/AutoLoader.php";

class Route{
    static private string $requestPath;
    static private int $count = 0;

    static public function match(array $info): void
    {
        self::$count++;
        self::checkInfo($info);
        $regexPath = self::setRegexPath($info["path"]);
        if(
            preg_match($regexPath, self::$requestPath) === 1 && 
            (
                $info["method"] === $_SERVER["REQUEST_METHOD"] ||
                $info["method"] === "*"
            )
        ){
            $class = self::autoLoadController($info["controller"]);

            $request = new Request(self::$requestPath, $info, $regexPath);
            $response = new Response();

            try{
                new $class($request, $response);
            }
            catch(\Throwable $th){
                $data = [
                    "info" => "Internal server error.",
                    "message" => $th->getMessage(),
                    "file" => $th->getFile(),
                    "line" => $th->getLine(),
                ];

                $response->code(500)->info("ERROR.INTERNAL_SERVER")->send($data);
            }

            $response->code(503)->info("ERROR.NO_SEND_RESPONSE")->send();
        }
    }

    static private function setRegexPath(string $path)
    {
        preg_match_all('/({[^\/]*})/', $path, $groups);
        $groups = $groups[1];

        foreach($groups as $group){
            $path = str_replace($group, "([^/]*)", $path);
        }

        $path = str_replace("/", "\\/", $path);

        return "/^" . $path  . "$/";
    }

    static function checkInfo($info)
    {
        if(isset($info["method"]) === false)
        {
            die("Route " . self::$count . " needs methods");
        }
        else if(isset($info["path"]) === false)
        {
            die("Route " . self::$count . " needs path");
        }
        else if(isset($info["controller"]) === false)
        {
            die("Route " . self::$count . " need controller.");
        }
    }

    static function autoLoadController(string $controller)
    {
        $class = "controller/{$controller}";
        $class = str_replace("/", "\\", $class);
        $class = str_replace("\\\\", "\\", $class);
        $class = rtrim($class, "\\");

        if(class_exists($class) === false){
            $path = explode("/", rtrim($controller, "/"));
            array_pop($path);
            $path = __DIR__ . "/../Controller/" . implode("/", $path) . ".php";

            if(file_exists($path) === false)
            {
                die("File '" . $path . "' not exist.");
            }

            include $path;

            if(class_exists($class) === false)
            {
                die("Class '" . $class . "' not exist.");
            }
        }

        return $class;
    }

    static function initRoute()
    {
        $uri = explode("?", $_SERVER["REQUEST_URI"]);
        self::$requestPath = $uri[0] === "/" ? "/" : rtrim($uri[0], "/");
    }
}

Route::initRoute();

function callController(string $controller){
    $controllerClass = Route::autoLoadController($controller);
    return new $controllerClass(Request::getCurrentRequest(), Response::getCurrentResponse());
}