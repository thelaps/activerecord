<?php

namespace validators;

class CRegularExpressionValidator extends CValidator
{

	public $pattern;

	public $allowEmpty=true;

 	public $not=false;

	protected function validateAttribute($object,$attribute)
	{
		$value=$object->$attribute;
		if($this->allowEmpty && $this->isEmpty($value))
			return;
		if($this->pattern===null)
			throw new \Exception('The "pattern" property must be specified with a valid regular expression.');
		// reason of array checking explained here: https://github.com/yiisoft/yii/issues/1955
		if(is_array($value) ||
			(!$this->not && !preg_match($this->pattern,$value)) ||
			($this->not && preg_match($this->pattern,$value)))
		{
			$message=$this->message!==null?$this->message:Yii::t('yii','{attribute} is invalid.');
			$this->addError($object,$attribute,$message);
		}
	}

	public function clientValidateAttribute($object,$attribute)
	{
		if($this->pattern===null)
			throw new \Exception('The "pattern" property must be specified with a valid regular expression.');

		$message=$this->message!==null ? $this->message : Yii::t('yii','{attribute} is invalid.');
		$message=strtr($message, array(
			'{attribute}'=>$object->getAttributeLabel($attribute),
		));

		$pattern=$this->pattern;
		$pattern=preg_replace('/\\\\x\{?([0-9a-fA-F]+)\}?/', '\u$1', $pattern);
		$delim=substr($pattern, 0, 1);
		$endpos=strrpos($pattern, $delim, 1);
		$flag=substr($pattern, $endpos + 1);
		if ($delim!=='/')
			$pattern='/' . str_replace('/', '\\/', substr($pattern, 1, $endpos - 1)) . '/';
		else
			$pattern = substr($pattern, 0, $endpos + 1);
		if (!empty($flag))
			$pattern .= preg_replace('/[^igm]/', '', $flag);

		return "
if(".($this->allowEmpty ? "jQuery.trim(value)!='' && " : '').($this->not ? '' : '!')."value.match($pattern)) {
	messages.push(".json_encode($message, JSON_UNESCAPED_UNICODE).");
}
";
	}
}