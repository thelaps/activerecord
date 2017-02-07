<?php

namespace component\CDb;

use component\CComponent;

class CDbTransaction extends CComponent
{
	private $_connection=null;
	private $_active;

	public function __construct(CDbConnection $connection)
	{
		$this->_connection=$connection;
		$this->_active=true;
	}

	public function commit()
	{
		if($this->_active && $this->_connection->getActive())
		{
			$this->_connection->getPdoInstance()->commit();
			$this->_active=false;
		}
		else
			throw new \Exception('CDbTransaction is inactive and cannot perform commit or roll back operations.');
	}

	public function rollback()
	{
		if($this->_active && $this->_connection->getActive())
		{
			$this->_connection->getPdoInstance()->rollBack();
			$this->_active=false;
		}
		else
			throw new \Exception('CDbTransaction is inactive and cannot perform commit or roll back operations.');
	}

	public function getConnection()
	{
		return $this->_connection;
	}

	public function getActive()
	{
		return $this->_active;
	}

	protected function setActive($value)
	{
		$this->_active=$value;
	}
}
