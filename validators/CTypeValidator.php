<?php

namespace validators;

class CTypeValidator extends CValidator
{

	public $type='string';

	public $dateFormat='MM/dd/yyyy';

	public $timeFormat='hh:mm';

	public $datetimeFormat='MM/dd/yyyy hh:mm';

	public $allowEmpty=true;

	public $strict=false;

	protected function validateAttribute($object,$attribute)
	{
		$value=$object->$attribute;
		if($this->allowEmpty && $this->isEmpty($value))
			return;

		if(!$this->validateValue($value))
		{
			$message=$this->message!==null?$this->message : Yii::t('yii','{attribute} must be {type}.');
			$this->addError($object,$attribute,$message,array('{type}'=>$this->type));
		}
	}

	public function validateValue($value)
	{
		$type=$this->type==='float' ? 'double' : $this->type;
		if($type===gettype($value))
			return true;
		elseif($this->strict || is_array($value) || is_object($value) || is_resource($value) || is_bool($value))
			return false;

		if($type==='integer')
			return (boolean)preg_match('/^[-+]?[0-9]+$/',trim($value));
		elseif($type==='double')
			return (boolean)preg_match('/^[-+]?([0-9]*\.)?[0-9]+([eE][-+]?[0-9]+)?$/',trim($value));
		elseif($type==='date')
			return CDateTimeParser::parse($value,$this->dateFormat,array('month'=>1,'day'=>1,'hour'=>0,'minute'=>0,'second'=>0))!==false;
		elseif($type==='time')
			return CDateTimeParser::parse($value,$this->timeFormat)!==false;
		elseif($type==='datetime')
			return CDateTimeParser::parse($value,$this->datetimeFormat, array('month'=>1,'day'=>1,'hour'=>0,'minute'=>0,'second'=>0))!==false;

		return false;
	}
}