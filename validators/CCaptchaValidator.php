<?php

namespace validators;

class CCaptchaValidator extends CValidator
{

	public $caseSensitive=false;

	public $captchaAction='captcha';

	public $allowEmpty=false;

	protected function validateAttribute($object,$attribute)
	{
		$value=$object->$attribute;
		if($this->allowEmpty && $this->isEmpty($value))
			return;
		$captcha=$this->getCaptchaAction();
		// reason of array checking is explained here: https://github.com/yiisoft/yii/issues/1955
		if(is_array($value) || !$captcha->validate($value,$this->caseSensitive))
		{
			$message=$this->message!==null?$this->message:Yii::t('yii','The verification code is incorrect.');
			$this->addError($object,$attribute,$message);
		}
	}

	protected function getCaptchaAction()
	{
		if(($captcha=Yii::app()->getController()->createAction($this->captchaAction))===null)
		{
			if(strpos($this->captchaAction,'/')!==false) // contains controller or module
			{
				if(($ca=Yii::app()->createController($this->captchaAction))!==null)
				{
					list($controller,$actionID)=$ca;
					$captcha=$controller->createAction($actionID);
				}
			}
			if($captcha===null)
				throw new \Exception('CCaptchaValidator.action "'.$this->captchaAction.'" is invalid. Unable to find such an action in the current controller.');
		}
		return $captcha;
	}

	public function clientValidateAttribute($object,$attribute)
	{
		$captcha=$this->getCaptchaAction();
		$message=$this->message!==null ? $this->message : Yii::t('yii','The verification code is incorrect.');
		$message=strtr($message, array(
			'{attribute}'=>$object->getAttributeLabel($attribute),
		));
		$code=$captcha->getVerifyCode(false);
		$hash=$captcha->generateValidationHash($this->caseSensitive ? $code : strtolower($code));
		$js="
var hash = jQuery('body').data('{$this->captchaAction}.hash');
if (hash == null)
	hash = $hash;
else
	hash = hash[".($this->caseSensitive ? 0 : 1)."];
for(var i=value.length-1, h=0; i >= 0; --i) h+=value.".($this->caseSensitive ? '' : 'toLowerCase().')."charCodeAt(i);
if(h != hash) {
	messages.push(".json_encode($message, JSON_UNESCAPED_UNICODE).");
}
";

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
}

