<?php

namespace component\CDb\ar;

use component\CComponent;
use component\CDb\CDbConnection;
use component\CDb\CDbComponent;
use component\CDb\schema\CDbCriteria;
use component\CModel\CModel;

abstract class CActiveRecord extends CModel
{
	const BELONGS_TO='CBelongsToRelation';
	const HAS_ONE='CHasOneRelation';
	const HAS_MANY='CHasManyRelation';
	const MANY_MANY='CManyManyRelation';
	const STAT='CStatRelation';

	public static $db;

	private static $_models=array();			// class name => model
	private static $_md=array();				// class name => meta data

	private $_new=false;						// whether this instance is new or not
	private $_attributes=array();				// attribute name => attribute value
	private $_related=array();					// attribute name => related objects
	private $_c;								// query criteria (used by finder only)
	private $_pk;								// old primary key value
	private $_alias='t';						// the table alias being used for query


	public function __construct($scenario='insert')
	{
		if($scenario===null) // internally used by populateRecord() and model()
			return;
		$this->setScenario($scenario);
		$this->setIsNewRecord(true);
		$this->_attributes=$this->getMetaData()->attributeDefaults;

		$this->init();

		$this->attachBehaviors($this->behaviors());
		$this->afterConstruct();
	}

	public function init()
	{
	}

	public function cache($duration, $dependency=null, $queryCount=1)
	{
		$this->getDbConnection()->cache($duration, $dependency, $queryCount);
		return $this;
	}

	public function __sleep()
	{
		return array_keys((array)$this);
	}

	public function __get($name)
	{
		if(isset($this->_attributes[$name]))
			return $this->_attributes[$name];
		elseif(isset($this->getMetaData()->columns[$name]))
			return null;
		elseif(isset($this->_related[$name]))
			return $this->_related[$name];
		elseif(isset($this->getMetaData()->relations[$name]))
			return $this->getRelated($name);
		else
			return parent::__get($name);
	}

	public function __set($name,$value)
	{
		if($this->setAttribute($name,$value)===false)
		{
			if(isset($this->getMetaData()->relations[$name]))
				$this->_related[$name]=$value;
			else
				parent::__set($name,$value);
		}
	}

	public function __isset($name)
	{
		if(isset($this->_attributes[$name]))
			return true;
		elseif(isset($this->getMetaData()->columns[$name]))
			return false;
		elseif(isset($this->_related[$name]))
			return true;
		elseif(isset($this->getMetaData()->relations[$name]))
			return $this->getRelated($name)!==null;
		else
			return parent::__isset($name);
	}

	public function __unset($name)
	{
		if(isset($this->getMetaData()->columns[$name]))
			unset($this->_attributes[$name]);
		elseif(isset($this->getMetaData()->relations[$name]))
			unset($this->_related[$name]);
		else
			parent::__unset($name);
	}

	public function __call($name,$parameters)
	{
		if(isset($this->getMetaData()->relations[$name]))
		{
			if(empty($parameters))
				return $this->getRelated($name,false);
			else
				return $this->getRelated($name,false,$parameters[0]);
		}

		$scopes=$this->scopes();
		if(isset($scopes[$name]))
		{
			$this->getDbCriteria()->mergeWith($scopes[$name]);
			return $this;
		}

		return parent::__call($name,$parameters);
	}

	public function getRelated($name,$refresh=false,$params=array())
	{
		if(!$refresh && $params===array() && (isset($this->_related[$name]) || array_key_exists($name,$this->_related)))
			return $this->_related[$name];

		$md=$this->getMetaData();
		if(!isset($md->relations[$name]))
			throw new \Exception(''.get_class($this).' does not have relation "'.$name.'".');

		$relation=$md->relations[$name];
		if($this->getIsNewRecord() && !$refresh && ($relation instanceof CHasOneRelation || $relation instanceof CHasManyRelation))
			return $relation instanceof CHasOneRelation ? null : array();

		if($params!==array()) // dynamic query
		{
			$exists=isset($this->_related[$name]) || array_key_exists($name,$this->_related);
			if($exists)
				$save=$this->_related[$name];

			if($params instanceof CDbCriteria)
				$params = $params->toArray();

			$r=array($name=>$params);
		}
		else
			$r=$name;
		unset($this->_related[$name]);

		$finder=$this->getActiveFinder($r);
		$finder->lazyFind($this);

		if(!isset($this->_related[$name]))
		{
			if($relation instanceof CHasManyRelation)
				$this->_related[$name]=array();
			elseif($relation instanceof CStatRelation)
				$this->_related[$name]=$relation->defaultValue;
			else
				$this->_related[$name]=null;
		}

		if($params!==array())
		{
			$results=$this->_related[$name];
			if($exists)
				$this->_related[$name]=$save;
			else
				unset($this->_related[$name]);
			return $results;
		}
		else
			return $this->_related[$name];
	}

	public function hasRelated($name)
	{
		return isset($this->_related[$name]) || array_key_exists($name,$this->_related);
	}

	public function getDbCriteria($createIfNull=true)
	{
		if($this->_c===null)
		{
			if(($c=$this->defaultScope())!==array() || $createIfNull)
				$this->_c=new CDbCriteria($c);
		}
		return $this->_c;
	}

	public function setDbCriteria($criteria)
	{
		$this->_c=$criteria;
	}

	public function defaultScope()
	{
		return array();
	}

	public function resetScope($resetDefault=true)
	{
		if($resetDefault)
			$this->_c=new CDbCriteria();
		else
			$this->_c=null;

		return $this;
	}

	public static function model($className=__CLASS__)
	{
		if(isset(self::$_models[$className]))
			return self::$_models[$className];
		else
		{
			$model=self::$_models[$className]=new $className(null);
			return $model;
		}
	}

	public function getMetaData()
	{
		$className=get_class($this);
		if(!array_key_exists($className,self::$_md))
		{
			self::$_md[$className]=null; // preventing recursive invokes of {@link getMetaData()} via {@link __get()}
			self::$_md[$className]=new CActiveRecordMetaData($this);
		}
		return self::$_md[$className];
	}

	public function refreshMetaData()
	{
		$className=get_class($this);
		if(array_key_exists($className,self::$_md))
			unset(self::$_md[$className]);
	}

	public function tableName()
	{
		$tableName = get_class($this);
		if(($pos=strrpos($tableName,'\\')) !== false)
			return substr($tableName,$pos+1);
		return $tableName;
	}

	public function primaryKey() {}

	public function relations()
	{
		return array();
	}

	public function scopes()
	{
		return array();
	}

	public function attributeNames()
	{
		return array_keys($this->getMetaData()->columns);
	}

	public function getAttributeLabel($attribute)
	{
		$labels=$this->attributeLabels();
		if(isset($labels[$attribute]))
			return $labels[$attribute];
		elseif(strpos($attribute,'.')!==false)
		{
			$segs=explode('.',$attribute);
			$name=array_pop($segs);
			$model=$this;
			foreach($segs as $seg)
			{
				$relations=$model->getMetaData()->relations;
				if(isset($relations[$seg]))
					$model=CActiveRecord::model($relations[$seg]->className);
				else
					break;
			}
			return $model->getAttributeLabel($name);
		}
		else
			return $this->generateAttributeLabel($attribute);
	}

	public function getDbConnection()
	{
		if(self::$db!==null)
			return self::$db;
		else
		{
			self::$db=CDbComponent::getDb();
			if(self::$db instanceof CDbConnection)
				return self::$db;
			else
				throw new \Exception('Active Record requires a "db" CDbConnection application component.');
		}
	}

	public function getActiveRelation($name)
	{
		return isset($this->getMetaData()->relations[$name]) ? $this->getMetaData()->relations[$name] : null;
	}

	public function getTableSchema()
	{
		return $this->getMetaData()->tableSchema;
	}

	public function getCommandBuilder()
	{
		return $this->getDbConnection()->getSchema()->getCommandBuilder();
	}

	public function hasAttribute($name)
	{
		return isset($this->getMetaData()->columns[$name]);
	}

	public function getAttribute($name)
	{
		if(property_exists($this,$name))
			return $this->$name;
		elseif(isset($this->_attributes[$name]))
			return $this->_attributes[$name];
	}

	public function setAttribute($name,$value)
	{
		if(property_exists($this,$name))
			$this->$name=$value;
		elseif(isset($this->getMetaData()->columns[$name]))
			$this->_attributes[$name]=$value;
		else
			return false;
		return true;
	}

	public function addRelatedRecord($name,$record,$index)
	{
		if($index!==false)
		{
			if(!isset($this->_related[$name]))
				$this->_related[$name]=array();
			if($record instanceof CActiveRecord)
			{
				if($index===true)
					$this->_related[$name][]=$record;
				else
					$this->_related[$name][$index]=$record;
			}
		}
		elseif(!isset($this->_related[$name]))
			$this->_related[$name]=$record;
	}

	public function getAttributes($names=true)
	{
		$attributes=$this->_attributes;
		foreach($this->getMetaData()->columns as $name=>$column)
		{
			if(property_exists($this,$name))
				$attributes[$name]=$this->$name;
			elseif($names===true && !isset($attributes[$name]))
				$attributes[$name]=null;
		}
		if(is_array($names))
		{
			$attrs=array();
			foreach($names as $name)
			{
				if(property_exists($this,$name))
					$attrs[$name]=$this->$name;
				else
					$attrs[$name]=isset($attributes[$name])?$attributes[$name]:null;
			}
			return $attrs;
		}
		else
			return $attributes;
	}

	public function save($runValidation=true,$attributes=null)
	{
		if(!$runValidation || $this->validate($attributes))
			return $this->getIsNewRecord() ? $this->insert($attributes) : $this->update($attributes);
		else
			return false;
	}

	public function getIsNewRecord()
	{
		return $this->_new;
	}

	public function setIsNewRecord($value)
	{
		$this->_new=$value;
	}

	public function onBeforeSave($event)
	{
		$this->raiseEvent('onBeforeSave',$event);
	}

	public function onAfterSave($event)
	{
		$this->raiseEvent('onAfterSave',$event);
	}

	public function onBeforeDelete($event)
	{
		$this->raiseEvent('onBeforeDelete',$event);
	}

	public function onAfterDelete($event)
	{
		$this->raiseEvent('onAfterDelete',$event);
	}

	public function onBeforeFind($event)
	{
		$this->raiseEvent('onBeforeFind',$event);
	}

	public function onAfterFind($event)
	{
		$this->raiseEvent('onAfterFind',$event);
	}

	public function getActiveFinder($with)
	{
		return new CActiveFinder($this,$with);
	}

	public function onBeforeCount($event)
	{
		$this->raiseEvent('onBeforeCount',$event);
	}

	protected function beforeSave()
	{
		if($this->hasEventHandler('onBeforeSave'))
		{
			$event=new CModelEvent($this);
			$this->onBeforeSave($event);
			return $event->isValid;
		}
		else
			return true;
	}

	protected function afterSave()
	{
		if($this->hasEventHandler('onAfterSave'))
			$this->onAfterSave(new CModelEvent($this));
	}

	protected function beforeDelete()
	{
		if($this->hasEventHandler('onBeforeDelete'))
		{
			$event=new CModelEvent($this);
			$this->onBeforeDelete($event);
			return $event->isValid;
		}
		else
			return true;
	}

	protected function afterDelete()
	{
		if($this->hasEventHandler('onAfterDelete'))
			$this->onAfterDelete(new CModelEvent($this));
	}

	protected function beforeFind()
	{
		if($this->hasEventHandler('onBeforeFind'))
		{
			$event=new CModelEvent($this);
			$this->onBeforeFind($event);
		}
	}

	protected function beforeCount()
	{
		if($this->hasEventHandler('onBeforeCount'))
			$this->onBeforeCount(new CModelEvent($this));
	}

	protected function afterFind()
	{
		if($this->hasEventHandler('onAfterFind'))
			$this->onAfterFind(new CModelEvent($this));
	}

	public function beforeFindInternal()
	{
		$this->beforeFind();
	}

	public function afterFindInternal()
	{
		$this->afterFind();
	}

	public function insert($attributes=null)
	{
		if(!$this->getIsNewRecord())
			throw new \Exception('The active record cannot be inserted to database because it is not new.');
		if($this->beforeSave())
		{
			$builder=$this->getCommandBuilder();
			$table=$this->getTableSchema();
			$command=$builder->createInsertCommand($table,$this->getAttributes($attributes));
			if($command->execute())
			{
				$primaryKey=$table->primaryKey;
				if($table->sequenceName!==null)
				{
					if(is_string($primaryKey) && $this->$primaryKey===null)
						$this->$primaryKey=$builder->getLastInsertID($table);
					elseif(is_array($primaryKey))
					{
						foreach($primaryKey as $pk)
						{
							if($this->$pk===null)
							{
								$this->$pk=$builder->getLastInsertID($table);
								break;
							}
						}
					}
				}
				$this->_pk=$this->getPrimaryKey();
				$this->afterSave();
				$this->setIsNewRecord(false);
				$this->setScenario('update');
				return true;
			}
		}
		return false;
	}

	public function update($attributes=null)
	{
		if($this->getIsNewRecord())
			throw new \Exception('The active record cannot be updated because it is new.');
		if($this->beforeSave())
		{
			if($this->_pk===null)
				$this->_pk=$this->getPrimaryKey();
			$this->updateByPk($this->getOldPrimaryKey(),$this->getAttributes($attributes));
			$this->_pk=$this->getPrimaryKey();
			$this->afterSave();
			return true;
		}
		else
			return false;
	}

	public function saveAttributes($attributes)
	{
		if(!$this->getIsNewRecord())
		{
			$values=array();
			foreach($attributes as $name=>$value)
			{
				if(is_integer($name))
					$values[$value]=$this->$value;
				else
					$values[$name]=$this->$name=$value;
			}
			if($this->_pk===null)
				$this->_pk=$this->getPrimaryKey();
			if($this->updateByPk($this->getOldPrimaryKey(),$values)>0)
			{
				$this->_pk=$this->getPrimaryKey();
				return true;
			}
			else
				return false;
		}
		else
			throw new \Exception('The active record cannot be updated because it is new.');
	}

	public function saveCounters($counters)
	{
		$builder=$this->getCommandBuilder();
		$table=$this->getTableSchema();
		$criteria=$builder->createPkCriteria($table,$this->getOldPrimaryKey());
		$command=$builder->createUpdateCounterCommand($this->getTableSchema(),$counters,$criteria);
		if($command->execute())
		{
			foreach($counters as $name=>$value)
				$this->$name=$this->$name+$value;
			return true;
		}
		else
			return false;
	}

	public function delete()
	{
		if(!$this->getIsNewRecord())
		{
			if($this->beforeDelete())
			{
				$result=$this->deleteByPk($this->getPrimaryKey())>0;
				$this->afterDelete();
				return $result;
			}
			else
				return false;
		}
		else
			throw new \Exception('The active record cannot be deleted because it is new.');
	}

	public function refresh()
	{
		if(($record=$this->findByPk($this->getPrimaryKey()))!==null)
		{
			$this->_attributes=array();
			$this->_related=array();
			foreach($this->getMetaData()->columns as $name=>$column)
			{
				if(property_exists($this,$name))
					$this->$name=$record->$name;
				else
					$this->_attributes[$name]=$record->$name;
			}
			return true;
		}
		else
			return false;
	}

	public function equals($record)
	{
		return $this->tableName()===$record->tableName() && $this->getPrimaryKey()===$record->getPrimaryKey();
	}

	public function getPrimaryKey()
	{
		$table=$this->getTableSchema();
		if(is_string($table->primaryKey))
			return $this->{$table->primaryKey};
		elseif(is_array($table->primaryKey))
		{
			$values=array();
			foreach($table->primaryKey as $name)
				$values[$name]=$this->$name;
			return $values;
		}
		else
			return null;
	}

	public function setPrimaryKey($value)
	{
		$this->_pk=$this->getPrimaryKey();
		$table=$this->getTableSchema();
		if(is_string($table->primaryKey))
			$this->{$table->primaryKey}=$value;
		elseif(is_array($table->primaryKey))
		{
			foreach($table->primaryKey as $name)
				$this->$name=$value[$name];
		}
	}

	public function getOldPrimaryKey()
	{
		return $this->_pk;
	}

	public function setOldPrimaryKey($value)
	{
		$this->_pk=$value;
	}

	protected function query($criteria,$all=false)
	{
		$this->beforeFind();
		$this->applyScopes($criteria);

		if(empty($criteria->with))
		{
			if(!$all)
				$criteria->limit=1;
			$command=$this->getCommandBuilder()->createFindCommand($this->getTableSchema(),$criteria);
			return $all ? $this->populateRecords($command->queryAll(), true, $criteria->index) : $this->populateRecord($command->queryRow());
		}
		else
		{
			$finder=$this->getActiveFinder($criteria->with);
			return $finder->query($criteria,$all);
		}
	}

	public function applyScopes(&$criteria)
	{
		if(!empty($criteria->scopes))
		{
			$scs=$this->scopes();
			$c=$this->getDbCriteria();
			foreach((array)$criteria->scopes as $k=>$v)
			{
				if(is_integer($k))
				{
					if(is_string($v))
					{
						if(isset($scs[$v]))
						{
							$c->mergeWith($scs[$v],true);
							continue;
						}
						$scope=$v;
						$params=array();
					}
					elseif(is_array($v))
					{
						$scope=key($v);
						$params=current($v);
					}
				}
				elseif(is_string($k))
				{
					$scope=$k;
					$params=$v;
				}

				call_user_func_array(array($this,$scope),(array)$params);
			}
		}

		if(isset($c) || ($c=$this->getDbCriteria(false))!==null)
		{
			$c->mergeWith($criteria);
			$criteria=$c;
			$this->resetScope(false);
		}
	}

	public function getTableAlias($quote=false, $checkScopes=true)
	{
		if($checkScopes && ($criteria=$this->getDbCriteria(false))!==null && $criteria->alias!='')
			$alias=$criteria->alias;
		else
			$alias=$this->_alias;
		return $quote ? $this->getDbConnection()->getSchema()->quoteTableName($alias) : $alias;
	}

	public function setTableAlias($alias)
	{
		$this->_alias=$alias;
	}

	public function find($condition='',$params=array())
	{
		$criteria=$this->getCommandBuilder()->createCriteria($condition,$params);
		return $this->query($criteria);
	}

	public function findAll($condition='',$params=array())
	{
		$criteria=$this->getCommandBuilder()->createCriteria($condition,$params);
		return $this->query($criteria,true);
	}

	public function findByPk($pk,$condition='',$params=array())
	{
		$prefix=$this->getTableAlias(true).'.';
		$criteria=$this->getCommandBuilder()->createPkCriteria($this->getTableSchema(),$pk,$condition,$params,$prefix);
		return $this->query($criteria);
	}

	public function findAllByPk($pk,$condition='',$params=array())
	{
		$prefix=$this->getTableAlias(true).'.';
		$criteria=$this->getCommandBuilder()->createPkCriteria($this->getTableSchema(),$pk,$condition,$params,$prefix);
		return $this->query($criteria,true);
	}

	public function findByAttributes($attributes,$condition='',$params=array())
	{
		$prefix=$this->getTableAlias(true).'.';
		$criteria=$this->getCommandBuilder()->createColumnCriteria($this->getTableSchema(),$attributes,$condition,$params,$prefix);
		return $this->query($criteria);
	}

	public function findAllByAttributes($attributes,$condition='',$params=array())
	{
		$prefix=$this->getTableAlias(true).'.';
		$criteria=$this->getCommandBuilder()->createColumnCriteria($this->getTableSchema(),$attributes,$condition,$params,$prefix);
		return $this->query($criteria,true);
	}

	public function findBySql($sql,$params=array())
	{
		$this->beforeFind();
		if(($criteria=$this->getDbCriteria(false))!==null && !empty($criteria->with))
		{
			$this->resetScope(false);
			$finder=$this->getActiveFinder($criteria->with);
			return $finder->findBySql($sql,$params);
		}
		else
		{
			$command=$this->getCommandBuilder()->createSqlCommand($sql,$params);
			return $this->populateRecord($command->queryRow());
		}
	}

	public function findAllBySql($sql,$params=array())
	{
		$this->beforeFind();
		if(($criteria=$this->getDbCriteria(false))!==null && !empty($criteria->with))
		{
			$this->resetScope(false);
			$finder=$this->getActiveFinder($criteria->with);
			return $finder->findAllBySql($sql,$params);
		}
		else
		{
			$command=$this->getCommandBuilder()->createSqlCommand($sql,$params);
			return $this->populateRecords($command->queryAll());
		}
	}
	public function count($condition='',$params=array())
	{
		$this->beforeCount();
		$builder=$this->getCommandBuilder();
		$criteria=$builder->createCriteria($condition,$params);
		$this->applyScopes($criteria);

		if(empty($criteria->with))
			return $builder->createCountCommand($this->getTableSchema(),$criteria)->queryScalar();
		else
		{
			$finder=$this->getActiveFinder($criteria->with);
			return $finder->count($criteria);
		}
	}
	public function countByAttributes($attributes,$condition='',$params=array())
	{
		$prefix=$this->getTableAlias(true).'.';
		$builder=$this->getCommandBuilder();
		$this->beforeCount();
		$criteria=$builder->createColumnCriteria($this->getTableSchema(),$attributes,$condition,$params,$prefix);
		$this->applyScopes($criteria);

		if(empty($criteria->with))
			return $builder->createCountCommand($this->getTableSchema(),$criteria)->queryScalar();
		else
		{
			$finder=$this->getActiveFinder($criteria->with);
			return $finder->count($criteria);
		}
	}

	public function countBySql($sql,$params=array())
	{
		$this->beforeCount();
		return $this->getCommandBuilder()->createSqlCommand($sql,$params)->queryScalar();
	}

	public function exists($condition='',$params=array())
	{
		$builder=$this->getCommandBuilder();
		$criteria=$builder->createCriteria($condition,$params);
		$table=$this->getTableSchema();
		$criteria->select='1';
		$criteria->limit=1;
		$this->applyScopes($criteria);

		if(empty($criteria->with))
			return $builder->createFindCommand($table,$criteria,$this->getTableAlias(false, false))->queryRow()!==false;
		else
		{
			$criteria->select='*';
			$finder=$this->getActiveFinder($criteria->with);
			return $finder->count($criteria)>0;
		}
	}

	public function with()
	{
		if(func_num_args()>0)
		{
			$with=func_get_args();
			if(is_array($with[0]))  // the parameter is given as an array
				$with=$with[0];
			if(!empty($with))
				$this->getDbCriteria()->mergeWith(array('with'=>$with));
		}
		return $this;
	}

	public function together()
	{
		$this->getDbCriteria()->together=true;
		return $this;
	}

	public function updateByPk($pk,$attributes,$condition='',$params=array())
	{
		$builder=$this->getCommandBuilder();
		$table=$this->getTableSchema();
		$criteria=$builder->createPkCriteria($table,$pk,$condition,$params);
		$command=$builder->createUpdateCommand($table,$attributes,$criteria);
		return $command->execute();
	}

	public function updateAll($attributes,$condition='',$params=array())
	{
		$builder=$this->getCommandBuilder();
		$criteria=$builder->createCriteria($condition,$params);
		$command=$builder->createUpdateCommand($this->getTableSchema(),$attributes,$criteria);
		return $command->execute();
	}

	public function updateCounters($counters,$condition='',$params=array())
	{
		$builder=$this->getCommandBuilder();
		$criteria=$builder->createCriteria($condition,$params);
		$command=$builder->createUpdateCounterCommand($this->getTableSchema(),$counters,$criteria);
		return $command->execute();
	}

	public function deleteByPk($pk,$condition='',$params=array())
	{
		$builder=$this->getCommandBuilder();
		$criteria=$builder->createPkCriteria($this->getTableSchema(),$pk,$condition,$params);
		$command=$builder->createDeleteCommand($this->getTableSchema(),$criteria);
		return $command->execute();
	}

	public function deleteAll($condition='',$params=array())
	{
		$builder=$this->getCommandBuilder();
		$criteria=$builder->createCriteria($condition,$params);
		$command=$builder->createDeleteCommand($this->getTableSchema(),$criteria);
		return $command->execute();
	}

	public function deleteAllByAttributes($attributes,$condition='',$params=array())
	{
		$builder=$this->getCommandBuilder();
		$table=$this->getTableSchema();
		$criteria=$builder->createColumnCriteria($table,$attributes,$condition,$params);
		$command=$builder->createDeleteCommand($table,$criteria);
		return $command->execute();
	}

	public function populateRecord($attributes,$callAfterFind=true)
	{
		if($attributes!==false)
		{
			$record=$this->instantiate($attributes);
			$record->setScenario('update');
			$record->init();
			$md=$record->getMetaData();
			foreach($attributes as $name=>$value)
			{
				if(property_exists($record,$name))
					$record->$name=$value;
				elseif(isset($md->columns[$name]))
					$record->_attributes[$name]=$value;
			}
			$record->_pk=$record->getPrimaryKey();
			$record->attachBehaviors($record->behaviors());
			if($callAfterFind)
				$record->afterFind();
			return $record;
		}
		else
			return null;
	}

	public function populateRecords($data,$callAfterFind=true,$index=null)
	{
		$records=array();
		foreach($data as $attributes)
		{
			if(($record=$this->populateRecord($attributes,$callAfterFind))!==null)
			{
				if($index===null)
					$records[]=$record;
				else
					$records[$record->$index]=$record;
			}
		}
		return $records;
	}

	protected function instantiate($attributes)
	{
		$class=get_class($this);
		$model=new $class(null);
		return $model;
	}

	public function offsetExists($offset)
	{
		return $this->__isset($offset);
	}
}

class CBaseActiveRelation extends CComponent
{
	public $name;

	public $className;

	public $foreignKey;

	public $select='*';

	public $condition='';

	public $params=array();

	public $group='';

	public $join='';

	public $joinOptions='';

	public $having='';

	public $order='';

	public function __construct($name,$className,$foreignKey,$options=array())
	{
		$this->name=$name;
		$this->className=$className;
		$this->foreignKey=$foreignKey;
		foreach($options as $name=>$value)
			$this->$name=$value;
	}

	public function mergeWith($criteria,$fromScope=false)
	{
		if($criteria instanceof CDbCriteria)
			$criteria=$criteria->toArray();
		if(isset($criteria['select']) && $this->select!==$criteria['select'])
		{
			if($this->select==='*'||$this->select===false)
				$this->select=$criteria['select'];
			elseif($criteria['select']===false)
				$this->select=false;
			elseif($criteria['select']!=='*')
			{
				$select1=is_string($this->select)?preg_split('/\s*,\s*/',trim($this->select),-1,PREG_SPLIT_NO_EMPTY):$this->select;
				$select2=is_string($criteria['select'])?preg_split('/\s*,\s*/',trim($criteria['select']),-1,PREG_SPLIT_NO_EMPTY):$criteria['select'];
				$this->select=array_merge($select1,array_diff($select2,$select1));
			}
		}

		if(isset($criteria['condition']) && $this->condition!==$criteria['condition'])
		{
			if($this->condition==='')
				$this->condition=$criteria['condition'];
			elseif($criteria['condition']!=='')
				$this->condition="({$this->condition}) AND ({$criteria['condition']})";
		}

		if(isset($criteria['params']) && $this->params!==$criteria['params'])
			$this->params=array_merge($this->params,$criteria['params']);

		if(isset($criteria['order']) && $this->order!==$criteria['order'])
		{
			if($this->order==='')
				$this->order=$criteria['order'];
			elseif($criteria['order']!=='')
				$this->order=$criteria['order'].', '.$this->order;
		}

		if(isset($criteria['group']) && $this->group!==$criteria['group'])
		{
			if($this->group==='')
				$this->group=$criteria['group'];
			elseif($criteria['group']!=='')
				$this->group.=', '.$criteria['group'];
		}

		if(isset($criteria['join']) && $this->join!==$criteria['join'])
		{
			if($this->join==='')
				$this->join=$criteria['join'];
			elseif($criteria['join']!=='')
				$this->join.=' '.$criteria['join'];
		}

		if(isset($criteria['having']) && $this->having!==$criteria['having'])
		{
			if($this->having==='')
				$this->having=$criteria['having'];
			elseif($criteria['having']!=='')
				$this->having="({$this->having}) AND ({$criteria['having']})";
		}
	}
}

class CStatRelation extends CBaseActiveRelation
{
	public $select='COUNT(*)';

	public $defaultValue=0;

	public $scopes;

	public function mergeWith($criteria,$fromScope=false)
	{
		if($criteria instanceof CDbCriteria)
			$criteria=$criteria->toArray();
		parent::mergeWith($criteria,$fromScope);

		if(isset($criteria['defaultValue']))
			$this->defaultValue=$criteria['defaultValue'];
	}
}

class CActiveRelation extends CBaseActiveRelation
{
	public $joinType='LEFT OUTER JOIN';

	public $on='';

	public $alias;

	public $with=array();

	public $together;

	 public $scopes;

	public $through;

	public function mergeWith($criteria,$fromScope=false)
	{
		if($criteria instanceof CDbCriteria)
			$criteria=$criteria->toArray();
		if($fromScope)
		{
			if(isset($criteria['condition']) && $this->on!==$criteria['condition'])
			{
				if($this->on==='')
					$this->on=$criteria['condition'];
				elseif($criteria['condition']!=='')
					$this->on="({$this->on}) AND ({$criteria['condition']})";
			}
			unset($criteria['condition']);
		}

		parent::mergeWith($criteria);

		if(isset($criteria['joinType']))
			$this->joinType=$criteria['joinType'];

		if(isset($criteria['on']) && $this->on!==$criteria['on'])
		{
			if($this->on==='')
				$this->on=$criteria['on'];
			elseif($criteria['on']!=='')
				$this->on="({$this->on}) AND ({$criteria['on']})";
		}

		if(isset($criteria['with']))
			$this->with=$criteria['with'];

		if(isset($criteria['alias']))
			$this->alias=$criteria['alias'];

		if(isset($criteria['together']))
			$this->together=$criteria['together'];
	}
}

class CBelongsToRelation extends CActiveRelation
{
}

class CHasOneRelation extends CActiveRelation
{
}

class CHasManyRelation extends CActiveRelation
{
	public $limit=-1;

	public $offset=-1;

	public $index;

	public function mergeWith($criteria,$fromScope=false)
	{
		if($criteria instanceof CDbCriteria)
			$criteria=$criteria->toArray();
		parent::mergeWith($criteria,$fromScope);
		if(isset($criteria['limit']) && $criteria['limit']>0)
			$this->limit=$criteria['limit'];

		if(isset($criteria['offset']) && $criteria['offset']>=0)
			$this->offset=$criteria['offset'];

		if(isset($criteria['index']))
			$this->index=$criteria['index'];
	}
}

class CManyManyRelation extends CHasManyRelation
{
	private $_junctionTableName=null;

	private $_junctionForeignKeys=null;

	public function getJunctionTableName()
	{
		if ($this->_junctionTableName===null)
			$this->initJunctionData();
		return $this->_junctionTableName;
	}

	public function getJunctionForeignKeys()
	{
		if ($this->_junctionForeignKeys===null)
			$this->initJunctionData();
		return $this->_junctionForeignKeys;
	}

	private function initJunctionData()
	{
		if(!preg_match('/^\s*(.*?)\((.*)\)\s*$/',$this->foreignKey,$matches))
			throw new \Exception('The relation "'.$this->name.'" in active record class "'.$this->className.'" is specified with an invalid foreign key. 
			The format of the foreign key must be "joinTable(fk1,fk2,...)".');
		$this->_junctionTableName=$matches[1];
		$this->_junctionForeignKeys=preg_split('/\s*,\s*/',$matches[2],-1,PREG_SPLIT_NO_EMPTY);
	}
}

class CActiveRecordMetaData
{
	/**
	 * @var CDbTableSchema the table schema information
	 */
	public $tableSchema;
	/**
	 * @var array table columns
	 */
	public $columns;
	/**
	 * @var array list of relations
	 */
	public $relations=array();
	/**
	 * @var array attribute default values
	 */
	public $attributeDefaults=array();

	private $_modelClassName;

	public function __construct($model)
	{
		$this->_modelClassName=get_class($model);

		$tableName=$model->tableName();
		if(($table=$model->getDbConnection()->getSchema()->getTable($tableName))===null)
			throw new \Exception('The table "'.$tableName.'" for active record class "'.$this->_modelClassName.'" cannot be found in the database.');

		if(($modelPk=$model->primaryKey())!==null || $table->primaryKey===null)
		{
			$table->primaryKey=$modelPk;
			if(is_string($table->primaryKey) && isset($table->columns[$table->primaryKey]))
				$table->columns[$table->primaryKey]->isPrimaryKey=true;
			elseif(is_array($table->primaryKey))
			{
				foreach($table->primaryKey as $name)
				{
					if(isset($table->columns[$name]))
						$table->columns[$name]->isPrimaryKey=true;
				}
			}
		}
		$this->tableSchema=$table;
		$this->columns=$table->columns;

		foreach($table->columns as $name=>$column)
		{
			if(!$column->isPrimaryKey && $column->defaultValue!==null)
				$this->attributeDefaults[$name]=$column->defaultValue;
		}

		foreach($model->relations() as $name=>$config)
		{
			$this->addRelation($name,$config);
		}
	}

	public function addRelation($name,$config)
	{
		if(isset($config[0],$config[1],$config[2])) {  // relation class, AR class, FK
            $_fabricClass = 'component\\CDb\\ar\\' . $config[0];
            $this->relations[$name] = new $_fabricClass($name, $config[1], $config[2], array_slice($config, 3));
        } else {
            throw new \Exception('Active record "' . $this->_modelClassName . '" has an invalid configuration for relation "' . $name . '".');
        }
	}

	public function hasRelation($name)
	{
		return isset($this->relations[$name]);
	}

	public function removeRelation($name)
	{
		unset($this->relations[$name]);
	}
}
