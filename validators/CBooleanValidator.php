<?php

namespace validators;

class CBooleanValidator extends CValidator
{

	public $trueValue='1';

	public $falseValue='0';

	public $strict=false;

	public $allowEmpty=true;

	protected function validateAttribute($object,$attribute)
	{
		$value=$object->$attribute;
		if($this->allowEmpty && $this->isEmpty($value))
			return;

		if(!$this->validateValue($value))
		{
			$message=$this->message!==null?$this->message:Yii::t('yii','{attribute} must be either {true} or {false}.');
			$this->addError($object,$attribute,$message,array(
				'{true}'=>$this->trueValue,
				'{false}'=>$this->falseValue,
			));
		}
	}

	public function validateValue($value)
	{
		if ($this->strict)
			return $value===$this->trueValue || $value===$this->falseValue;
		else
			return $value==$this->trueValue || $value==$this->falseValue;
	}

	public function clientValidateAttribute($object,$attribute)
	{
		$message=$this->message!==null ? $this->message : Yii::t('yii','{attribute} must be either {true} or {false}.');
		$message=strtr($message, array(
			'{attribute}'=>$object->getAttributeLabel($attribute),
			'{true}'=>$this->trueValue,
			'{false}'=>$this->falseValue,
		));
		return "
if(".($this->allowEmpty ? "jQuery.trim(value)!='' && " : '')."value!=".json_encode($this->trueValue, JSON_UNESCAPED_UNICODE)." && value!=".json_encode($this->falseValue, JSON_UNESCAPED_UNICODE).") {
	messages.push(".json_encode($message, JSON_UNESCAPED_UNICODE).");
}
";
	}
}
