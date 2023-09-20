<?php

namespace Mp\Core\Abstract;

use Exception;
use Mp\Box;
use Mp\Core\Database;
use ReflectionClass;

abstract class Module
{

    protected $box;

    public function setBox(Box $box)
    {

        $this->box = $box;
    }

    final public function getDatabase()
    {

        $file = $this->__getIndex();
        return new Database($file);
    }


    private function __getChildFile()
    {
        $reflection = new ReflectionClass($this::class);
        return $reflection->getFileName();
    }

    public function __getIndex()
    {
        $file = $this->__getChildFile();

        $parts = explode((PHP_OS == "WINNT" ? "\\" : "/"), $file);

        foreach ($parts as $key => $part) {
            if (strtolower($part) == "module") {
                $modName = $parts[$key + 1];
                $file = implode("/", array_slice($parts, 0, $key + 1)) . "/" . $modName;
                break;
            }
        }
        return $file;
    }

    public function __call($name, $arguments)
    {

        $index = $this->__getIndex();

        $baseClass = substr($this::class, 0, strpos($this::class, basename($index)) + strlen(basename($index)));
        $target    = implode("/", array_map("ucfirst", explode("/", $name)));
        $instance  = "{$baseClass}\\{$target}";

        if (!class_exists($instance)) {
            throw new Exception("Module $target not found");
        }

        return $this->box->$instance($arguments);
    }
}