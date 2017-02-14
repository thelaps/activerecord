<?php

namespace validators;

class CUrlValidator extends CValidator
{

	public $pattern='/^{schemes}:\/\/(([A-Z0-9][A-Z0-9_-]*)(\.[A-Z0-9][A-Z0-9_-]*)+)/i';

	public $validSchemes=array('http','https');

	public $defaultScheme;

	public $allowEmpty=true;

	public $validateIDN=false;

	protected function validateAttribute($object,$attribute)
	{
		$value=$object->$attribute;
		if($this->allowEmpty && $this->isEmpty($value))
			return;
		if(($value=$this->validateValue($value))!==false)
			$object->$attribute=$value;
		else
		{
			$message=$this->message!==null?$this->message:Yii::t('yii','{attribute} is not a valid URL.');
			$this->addError($object,$attribute,$message);
		}
	}

	public function validateValue($value)
	{
		if(is_string($value) && strlen($value)<2000)  // make sure the length is limited to avoid DOS attacks
		{
			if($this->defaultScheme!==null && strpos($value,'://')===false)
				$value=$this->defaultScheme.'://'.$value;

			if($this->validateIDN)
				$value=$this->encodeIDN($value);

			if(strpos($this->pattern,'{schemes}')!==false)
				$pattern=str_replace('{schemes}','('.implode('|',$this->validSchemes).')',$this->pattern);
			else
				$pattern=$this->pattern;

			if(preg_match($pattern,$value))
				return $this->validateIDN ? $this->decodeIDN($value) : $value;
		}
		return false;
	}

	public function clientValidateAttribute($object,$attribute)
	{
		if($this->validateIDN)
		{
			Yii::app()->getClientScript()->registerCoreScript('punycode');
			// punycode.js works only with the domains - so we have to extract it before punycoding
			$validateIDN='
var info = value.match(/^(.+:\/\/|)([^/]+)/);
if (info)
	value = info[1] + punycode.toASCII(info[2]);
';
		}
		else
			$validateIDN='';

		$message=$this->message!==null ? $this->message : Yii::t('yii','{attribute} is not a valid URL.');
		$message=strtr($message, array(
			'{attribute}'=>$object->getAttributeLabel($attribute),
		));

		if(strpos($this->pattern,'{schemes}')!==false)
			$pattern=str_replace('{schemes}','('.implode('|',$this->validSchemes).')',$this->pattern);
		else
			$pattern=$this->pattern;

		$js="
$validateIDN
if(!value.match($pattern)) {
	messages.push(".json_encode($message, JSON_UNESCAPED_UNICODE).");
}
";
		if($this->defaultScheme!==null)
		{
			$js="
if(!value.match(/:\\/\\//)) {
	value=".json_encode($this->defaultScheme, JSON_UNESCAPED_UNICODE)."+'://'+value;
}
$js
";
		}

		if($this->allowEmpty)
		{
			$js="
if(jQuery.trim(value)!='') {
	$js
}
";
		}

		return $js;
	}

	private function encodeIDN($value)
	{
		if(preg_match_all('/^(.*):\/\/([^\/]+)(.*)$/',$value,$matches))
		{
			if(function_exists('idn_to_ascii'))
				$value=$matches[1][0].'://'.idn_to_ascii($matches[2][0]).$matches[3][0];
			else
			{
				require_once(Yii::getPathOfAlias('system.vendors.Net_IDNA2.Net').DIRECTORY_SEPARATOR.'IDNA2.php');
				$idna=new Net_IDNA2();
				$value=$matches[1][0].'://'.@$idna->encode($matches[2][0]).$matches[3][0];
			}
		}
		return $value;
	}

	private function decodeIDN($value)
	{
		if(preg_match_all('/^(.*):\/\/([^\/]+)(.*)$/',$value,$matches))
		{
			if(function_exists('idn_to_utf8'))
				$value=$matches[1][0].'://'.idn_to_utf8($matches[2][0]).$matches[3][0];
			else
			{
				require_once(Yii::getPathOfAlias('system.vendors.Net_IDNA2.Net').DIRECTORY_SEPARATOR.'IDNA2.php');
				$idna=new Net_IDNA2();
				$value=$matches[1][0].'://'.@$idna->decode($matches[2][0]).$matches[3][0];
			}
		}
		return $value;
	}
}
