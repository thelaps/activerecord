<?php

namespace validators;

class CEmailValidator extends CValidator
{

	public $pattern='/^[a-zA-Z0-9!#$%&\'*+\\/=?^_`{|}~-]+(?:\.[a-zA-Z0-9!#$%&\'*+\\/=?^_`{|}~-]+)*@(?:[a-zA-Z0-9](?:[a-zA-Z0-9-]*[a-zA-Z0-9])?\.)+[a-zA-Z0-9](?:[a-zA-Z0-9-]*[a-zA-Z0-9])?$/';

	public $fullPattern='/^[^@]*<[a-zA-Z0-9!#$%&\'*+\\/=?^_`{|}~-]+(?:\.[a-zA-Z0-9!#$%&\'*+\\/=?^_`{|}~-]+)*@(?:[a-zA-Z0-9](?:[a-zA-Z0-9-]*[a-zA-Z0-9])?\.)+[a-zA-Z0-9](?:[a-zA-Z0-9-]*[a-zA-Z0-9])?>$/';

	public $allowName=false;

	public $checkMX=false;

	public $checkPort=false;

	public $allowEmpty=true;

	public $validateIDN=false;

	protected function validateAttribute($object,$attribute)
	{
		$value=$object->$attribute;
		if($this->allowEmpty && $this->isEmpty($value))
			return;

		if(!$this->validateValue($value))
		{
			$message=$this->message!==null?$this->message:Yii::t('yii','{attribute} is not a valid email address.');
			$this->addError($object,$attribute,$message);
		}
	}

	public function validateValue($value)
	{
		if(is_string($value) && $this->validateIDN)
			$value=$this->encodeIDN($value);
		// make sure string length is limited to avoid DOS attacks
		$valid=is_string($value) && strlen($value)<=254 && (preg_match($this->pattern,$value) || $this->allowName && preg_match($this->fullPattern,$value));
		if($valid)
			$domain=rtrim(substr($value,strpos($value,'@')+1),'>');
		if($valid && $this->checkMX && function_exists('checkdnsrr'))
			$valid=checkdnsrr($domain,'MX');
		if($valid && $this->checkPort && function_exists('fsockopen') && function_exists('dns_get_record'))
			$valid=$this->checkMxPorts($domain);
		return $valid;
	}

	public function clientValidateAttribute($object,$attribute)
	{
		if($this->validateIDN)
		{
			Yii::app()->getClientScript()->registerCoreScript('punycode');
			// punycode.js works only with the domains - so we have to extract it before punycoding
			$validateIDN='
var info = value.match(/^(.[^@]+)@(.+)$/);
if (info)
	value = info[1] + "@" + punycode.toASCII(info[2]);
';
		}
		else
			$validateIDN='';

		$message=$this->message!==null ? $this->message : Yii::t('yii','{attribute} is not a valid email address.');
		$message=strtr($message, array(
			'{attribute}'=>$object->getAttributeLabel($attribute),
		));

		$condition="!value.match({$this->pattern})";
		if($this->allowName)
			$condition.=" && !value.match({$this->fullPattern})";

		return "
$validateIDN
if(".($this->allowEmpty ? "jQuery.trim(value)!='' && " : '').$condition.") {
	messages.push(".json_encode($message, JSON_UNESCAPED_UNICODE).");
}
";
	}

	protected function checkMxPorts($domain)
	{
		$records=dns_get_record($domain, DNS_MX);
		if($records===false || empty($records))
			return false;
		usort($records,array($this,'mxSort'));
		foreach($records as $record)
		{
			$handle=@fsockopen($record['target'],25);
			if($handle!==false)
			{
				fclose($handle);
				return true;
			}
		}
		return false;
	}

	protected function mxSort($a, $b)
	{
		return $a['pri']-$b['pri'];
	}

	private function encodeIDN($value)
	{
		if(preg_match_all('/^(.*)@(.*)$/',$value,$matches))
		{
			if(function_exists('idn_to_ascii'))
				$value=$matches[1][0].'@'.idn_to_ascii($matches[2][0]);
			else
			{
				require_once(Yii::getPathOfAlias('system.vendors.Net_IDNA2.Net').DIRECTORY_SEPARATOR.'IDNA2.php');
				$idna=new Net_IDNA2();
				$value=$matches[1][0].'@'.@$idna->encode($matches[2][0]);
			}
		}
		return $value;
	}
}
