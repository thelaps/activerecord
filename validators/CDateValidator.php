<?php

namespace validators;

class CDateValidator extends CValidator
{

	public $format='MM/dd/yyyy';

	public $allowEmpty=true;

	public $timestampAttribute;

	protected function validateAttribute($object,$attribute)
	{
		$value=$object->$attribute;
		if($this->allowEmpty && $this->isEmpty($value))
			return;

		$valid=false;

		// reason of array checking is explained here: https://github.com/yiisoft/yii/issues/1955
		if(!is_array($value))
		{
			$formats=is_string($this->format) ? array($this->format) : $this->format;
			foreach($formats as $format)
			{
				$timestamp=CDateTimeParser::parse($value,$format,array('month'=>1,'day'=>1,'hour'=>0,'minute'=>0,'second'=>0));
				if($timestamp!==false)
				{
					$valid=true;
					if($this->timestampAttribute!==null)
						$object->{$this->timestampAttribute}=$timestamp;
					break;
				}
			}
		}

		if(!$valid)
		{
			$message=$this->message!==null?$this->message : Yii::t('yii','The format of {attribute} is invalid.');
			$this->addError($object,$attribute,$message);
		}
	}
}

