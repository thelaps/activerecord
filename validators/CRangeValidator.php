<?php

namespace validators;

class CRangeValidator extends CValidator
{

	public $range;

	public $strict=false;

	public $allowEmpty=true;

 	public $not=false;

	protected function validateAttribute($object,$attribute)
	{
		$value=$object->$attribute;
		if($this->allowEmpty && $this->isEmpty($value))
			return;
		if(!is_array($this->range))
			throw new \Exception('The "range" property must be specified with a list of values.');
		$result = false;
		if($this->strict)
			$result=in_array($value,$this->range,true);
		else
		{
			foreach($this->range as $r)
			{
				$result = $r === '' || $value === '' ? $r === $value : $r == $value;
				if($result)
					break;
			}
		}
		if(!$this->not && !$result)
		{
			$message=$this->message!==null?$this->message:Yii::t('yii','{attribute} is not in the list.');
			$this->addError($object,$attribute,$message);
		}
		elseif($this->not && $result)
		{
			$message=$this->message!==null?$this->message:Yii::t('yii','{attribute} is in the list.');
			$this->addError($object,$attribute,$message);
		}
	}

	public function clientValidateAttribute($object,$attribute)
	{
		if(!is_array($this->range))
			throw new \Exception('The "range" property must be specified with a list of values.');

		if(($message=$this->message)===null)
			$message=$this->not ? Yii::t('yii','{attribute} is in the list.') : Yii::t('yii','{attribute} is not in the list.');
		$message=strtr($message,array(
			'{attribute}'=>$object->getAttributeLabel($attribute),
		));

		$range=array();
		foreach($this->range as $value)
			$range[]=(string)$value;
		$range=json_encode($range, JSON_UNESCAPED_UNICODE);

		return "
if(".($this->allowEmpty ? "jQuery.trim(value)!='' && " : '').($this->not ? "jQuery.inArray(value, $range)>=0" : "jQuery.inArray(value, $range)<0").") {
	messages.push(".json_encode($message, JSON_UNESCAPED_UNICODE).");
}
";
	}
}
