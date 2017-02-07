<?php
/**
 * Created by PhpStorm.
 * User: Viktor G
 * Date: 03.02.2017
 * Time: 10:41
 */

namespace component\CDb;


class CDbComponent
{

    private static $db;
    private static $instances = array();
    protected function __construct() {}
    protected function __clone() {}
    public function __wakeup()
    {
        throw new \Exception("Cannot unserialize CDbComponent");
    }

    public static function getInstance()
    {
        $cls = get_called_class(); // late-static-bound class name
        if (!isset(self::$instances[$cls])) {
            self::$instances[$cls] = new static;
        }
        return self::$instances[$cls];
    }

    public static function createComponent($config)
    {
        if (is_string($config)) {
            $type=$config;
            $config=array();
        } elseif(isset($config['class'])) {
            $type=$config['class'];
            unset($config['class']);
        } else {
            throw new \Exception('Object configuration must be an array containing a "class" element.');
        }
        if (!class_exists($type,false)) {
            //print_r([$type, $config]);
            //die;
            //$type=Yii::import($type,true);
        }
        if (($n=func_num_args())>1) {
            $args=func_get_args();
            if($n===2)
                $object=new $type($args[1]);
            elseif($n===3)
                $object=new $type($args[1],$args[2]);
            elseif($n===4)
                $object=new $type($args[1],$args[2],$args[3]);
            else
            {
                unset($args[0]);
                $class=new \ReflectionClass($type);
                // Note: ReflectionClass::newInstanceArgs() is available for PHP 5.1.3+
                // $object=$class->newInstanceArgs($args);
                $object=call_user_func_array(array($class,'newInstance'),$args);
            }
        }
        else
            $object=new $type;
        foreach($config as $key=>$value)
            $object->$key=$value;
        return $object;
    }

    public static function getDb()
    {
        return self::$db;
    }

    public static function setDb($dsn, $user, $password)
    {
        self::$db = new CDbConnection($dsn, $user, $password);
    }
}