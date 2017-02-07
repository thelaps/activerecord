<?php
class CAutoload
{
    public static function autoload($className, $classMapOnly=false)
    {
        if(isset(self::$classMap[$className])) {
            include_once(self::$classMap[$className]);
        } elseif(isset(self::$_coreClasses[$className])) {
            include_once(self::$_coreClasses[$className]);
        } elseif($classMapOnly) {
            return false;
        } else {
            if (strpos($className,'\\')===false) {
                if (self::$enableIncludePath===false) {
                    foreach (self::$_includePaths as $path) {
                        $classFile=$path.DIRECTORY_SEPARATOR.$className.'.php';
                        if (is_file($classFile)) {
                            include_once($classFile);
                            if (basename(realpath($classFile))!==$className.'.php') {
                                throw new Exception('Class name "' . $className . '" does not match class file "' . $classFile . '"');
                            }
                            break;
                        }
                    }
                } else {
                    include_once($className . '.php');
                }
            } else {
                $namespace=str_replace('\\','/',ltrim($className,'\\'));
                if (is_file($namespace.'.php')) {
                    include_once($namespace . '.php');
                } else {
                    return false;
                }
            }
            return class_exists($className,false) || interface_exists($className,false);
        }
        return true;
    }

    public static function getPathOfAlias($alias)
    {
        if(isset(self::$_aliases[$alias]))
            return self::$_aliases[$alias];
        elseif(($pos=strpos($alias,'.'))!==false)
        {
            $rootAlias=substr($alias,0,$pos);
            if(isset(self::$_aliases[$rootAlias]))
                return self::$_aliases[$alias]=rtrim(self::$_aliases[$rootAlias].DIRECTORY_SEPARATOR.str_replace('.',DIRECTORY_SEPARATOR,substr($alias,$pos+1)),'*'.DIRECTORY_SEPARATOR);
            /*elseif(self::$_app instanceof CWebApplication)
            {
                if(self::$_app->findModule($rootAlias)!==null)
                    return self::getPathOfAlias($alias);
            }*/
        }
        return false;
    }

    private static $_includePaths;

    public static $enableIncludePath = true;

    public static $classMap = array();

    private static $_aliases=array();

    private static $_coreClasses = array(
        'CComponent' => 'component/CComponent.php',
        'CApplicationComponent' => 'component/CApplicationComponent.php',
        'CDbCommand' => 'component/CDb/CDbCommand.php',
        'CDbConnection' => 'component/CDb/CDbConnection.php',
        'CDbComponent' => 'component/CDb/CDbComponent.php',
        'CDbDataReader' => 'component/CDb/CDbDataReader.php',
        'CDbException' => 'component/CDb/CDbException.php',
        'CDbMigration' => 'component/CDb/CDbMigration.php',
        'CDbTransaction' => 'component/CDb/CDbTransaction.php',
        'CActiveFinder' => 'component/CDb/ar/CActiveFinder.php',
        'CActiveRecord' => 'component/CDb/ar/CActiveRecord.php',
        'CActiveRecordBehavior' => 'component/CDb/ar/CActiveRecordBehavior.php',
        'CDbColumnSchema' => 'component/CDb/schema/CDbColumnSchema.php',
        'CDbCommandBuilder' => 'component/CDb/schema/CDbCommandBuilder.php',
        'CDbCriteria' => 'component/CDb/schema/CDbCriteria.php',
        'CDbExpression' => 'component/CDb/schema/CDbExpression.php',
        'CDbSchema' => 'component/CDb/schema/CDbSchema.php',
        'CDbTableSchema' => 'component/CDb/schema/CDbTableSchema.php',
        'CCubridColumnSchema' => 'component/CDb/schema/cubrid/CCubridColumnSchema.php',
        'CCubridSchema' => 'component/CDb/schema/cubrid/CCubridSchema.php',
        'CCubridTableSchema' => 'component/CDb/schema/cubrid/CCubridTableSchema.php',
        'CMssqlColumnSchema' => 'component/CDb/schema/mssql/CMssqlColumnSchema.php',
        'CMssqlCommandBuilder' => 'component/CDb/schema/mssql/CMssqlCommandBuilder.php',
        'CMssqlPdoAdapter' => 'component/CDb/schema/mssql/CMssqlPdoAdapter.php',
        'CMssqlSchema' => 'component/CDb/schema/mssql/CMssqlSchema.php',
        'CMssqlSqlsrvPdoAdapter' => 'component/CDb/schema/mssql/CMssqlSqlsrvPdoAdapter.php',
        'CMssqlTableSchema' => 'component/CDb/schema/mssql/CMssqlTableSchema.php',
        'CMysqlColumnSchema' => 'component/CDb/schema/mysql/CMysqlColumnSchema.php',
        'CMysqlCommandBuilder' => 'component/CDb/schema/mysql/CMysqlCommandBuilder.php',
        'CMysqlSchema' => 'component/CDb/schema/mysql/CMysqlSchema.php',
        'CMysqlTableSchema' => 'component/CDb/schema/mysql/CMysqlTableSchema.php',
        'COciColumnSchema' => 'component/CDb/schema/oci/COciColumnSchema.php',
        'COciCommandBuilder' => 'component/CDb/schema/oci/COciCommandBuilder.php',
        'COciSchema' => 'component/CDb/schema/oci/COciSchema.php',
        'COciTableSchema' => 'component/CDb/schema/oci/COciTableSchema.php',
        'CPgsqlColumnSchema' => 'component/CDb/schema/pgsql/CPgsqlColumnSchema.php',
        'CPgsqlCommandBuilder' => 'component/CDb/schema/pgsql/CPgsqlCommandBuilder.php',
        'CPgsqlSchema' => 'component/CDb/schema/pgsql/CPgsqlSchema.php',
        'CPgsqlTableSchema' => 'component/CDb/schema/pgsql/CPgsqlTableSchema.php',
        'CSqliteColumnSchema' => 'component/CDb/schema/sqlite/CSqliteColumnSchema.php',
        'CSqliteCommandBuilder' => 'component/CDb/schema/sqlite/CSqliteCommandBuilder.php',
        'CSqliteSchema' => 'component/CDb/schema/sqlite/CSqliteSchema.php',
    );
}

spl_autoload_register(array('CAutoload','autoload'));
?>