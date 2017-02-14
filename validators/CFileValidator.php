<?php

namespace validators;

class CFileValidator extends CValidator
{

	public $allowEmpty=false;

	public $types;

	public $mimeTypes;

	public $minSize;

	public $maxSize;

	public $tooLarge;

	public $tooSmall;

	public $wrongType;

	public $wrongMimeType;

	public $maxFiles=1;

	public $tooMany;

	protected function validateAttribute($object, $attribute)
	{
		$files=$object->$attribute;
		if($this->maxFiles > 1)
		{
			if(!is_array($files) || !isset($files[0]) || !$files[0] instanceof CUploadedFile)
				$files = CUploadedFile::getInstances($object, $attribute);
			if(array()===$files)
				return $this->emptyAttribute($object, $attribute);
			if(count($files) > $this->maxFiles)
			{
				$message=$this->tooMany!==null?$this->tooMany : Yii::t('yii', '{attribute} cannot accept more than {limit} files.');
				$this->addError($object, $attribute, $message, array('{attribute}'=>$attribute, '{limit}'=>$this->maxFiles));
			}
			else
				foreach($files as $file)
					$this->validateFile($object, $attribute, $file);
		}
		else
		{
			if (is_array($files))
			{
				if (count($files) > 1)
				{
					$message=$this->tooMany!==null?$this->tooMany : Yii::t('yii', '{attribute} cannot accept more than {limit} files.');
					$this->addError($object, $attribute, $message, array('{attribute}'=>$attribute, '{limit}'=>$this->maxFiles));
					return;
				}
				else
					$file = empty($files) ? null : reset($files);
			}
			else
				$file = $files;
			if(!$file instanceof CUploadedFile)
			{
				$file = CUploadedFile::getInstance($object, $attribute);
				if(null===$file)
					return $this->emptyAttribute($object, $attribute);
			}
			$this->validateFile($object, $attribute, $file);
		}
	}

	/**
	 * Internally validates a file object.
	 * @param CModel $object the object being validated
	 * @param string $attribute the attribute being validated
	 * @param CUploadedFile $file uploaded file passed to check against a set of rules
	 * @throws CException if failed to upload the file
	 */
	protected function validateFile($object, $attribute, $file)
	{
		$error=(null===$file ? null : $file->getError());
		if($error==UPLOAD_ERR_INI_SIZE || $error==UPLOAD_ERR_FORM_SIZE || $this->maxSize!==null && $file->getSize()>$this->maxSize)
		{
			$message=$this->tooLarge!==null?$this->tooLarge : Yii::t('yii','The file "{file}" is too large. Its size cannot exceed {limit} bytes.');
			$this->addError($object,$attribute,$message,array('{file}'=>CHtml::encode($file->getName()), '{limit}'=>$this->getSizeLimit()));
			if($error!==UPLOAD_ERR_OK)
				return;
		}
		elseif($error!==UPLOAD_ERR_OK)
		{
			if($error==UPLOAD_ERR_NO_FILE)
				return $this->emptyAttribute($object, $attribute);
			elseif($error==UPLOAD_ERR_PARTIAL)
				throw new CException(Yii::t('yii','The file "{file}" was only partially uploaded.',array('{file}'=>CHtml::encode($file->getName()))));
			elseif($error==UPLOAD_ERR_NO_TMP_DIR)
				throw new CException(Yii::t('yii','Missing the temporary folder to store the uploaded file "{file}".',array('{file}'=>CHtml::encode($file->getName()))));
			elseif($error==UPLOAD_ERR_CANT_WRITE)
				throw new CException(Yii::t('yii','Failed to write the uploaded file "{file}" to disk.',array('{file}'=>CHtml::encode($file->getName()))));
			elseif(defined('UPLOAD_ERR_EXTENSION') && $error==UPLOAD_ERR_EXTENSION)  // available for PHP 5.2.0 or above
				throw new CException(Yii::t('yii','A PHP extension stopped the file upload.'));
			else
				throw new CException(Yii::t('yii','Unable to upload the file "{file}" because of an unrecognized error.',array('{file}'=>CHtml::encode($file->getName()))));
		}

		if($this->minSize!==null && $file->getSize()<$this->minSize)
		{
			$message=$this->tooSmall!==null?$this->tooSmall : Yii::t('yii','The file "{file}" is too small. Its size cannot be smaller than {limit} bytes.');
			$this->addError($object,$attribute,$message,array('{file}'=>CHtml::encode($file->getName()), '{limit}'=>$this->minSize));
		}

		if($this->types!==null)
		{
			if(is_string($this->types))
				$types=preg_split('/[\s,]+/',strtolower($this->types),-1,PREG_SPLIT_NO_EMPTY);
			else
				$types=$this->types;
			if(!in_array(strtolower($file->getExtensionName()),$types))
			{
				$message=$this->wrongType!==null?$this->wrongType : Yii::t('yii','The file "{file}" cannot be uploaded. Only files with these extensions are allowed: {extensions}.');
				$this->addError($object,$attribute,$message,array('{file}'=>CHtml::encode($file->getName()), '{extensions}'=>implode(', ',$types)));
			}
		}

		if($this->mimeTypes!==null && !empty($file->tempName))
		{
			if(function_exists('finfo_open'))
			{
				$mimeType=false;
				if($info=finfo_open(defined('FILEINFO_MIME_TYPE') ? FILEINFO_MIME_TYPE : FILEINFO_MIME))
					$mimeType=finfo_file($info,$file->getTempName());
			}
			elseif(function_exists('mime_content_type'))
				$mimeType=mime_content_type($file->getTempName());
			else
				throw new CException(Yii::t('yii','In order to use MIME-type validation provided by CFileValidator fileinfo PECL extension should be installed.'));

			if(is_string($this->mimeTypes))
				$mimeTypes=preg_split('/[\s,]+/',strtolower($this->mimeTypes),-1,PREG_SPLIT_NO_EMPTY);
			else
				$mimeTypes=$this->mimeTypes;

			if($mimeType===false || !in_array(strtolower($mimeType),$mimeTypes))
			{
				$message=$this->wrongMimeType!==null?$this->wrongMimeType : Yii::t('yii','The file "{file}" cannot be uploaded. Only files of these MIME-types are allowed: {mimeTypes}.');
				$this->addError($object,$attribute,$message,array('{file}'=>CHtml::encode($file->getName()), '{mimeTypes}'=>implode(', ',$mimeTypes)));
			}
		}
	}

	/**
	 * Raises an error to inform end user about blank attribute.
	 * Sets the owner attribute to null to prevent setting arbitrary values.
	 * @param CModel $object the object being validated
	 * @param string $attribute the attribute being validated
	 */
	protected function emptyAttribute($object, $attribute)
	{
		if($this->safe) 
			$object->$attribute=null;

		if(!$this->allowEmpty)
		{
			$message=$this->message!==null?$this->message : Yii::t('yii','{attribute} cannot be blank.');
			$this->addError($object,$attribute,$message);
		}
	}

	/**
	 * Returns the maximum size allowed for uploaded files.
	 * This is determined based on three factors:
	 * <ul>
	 * <li>'upload_max_filesize' in php.ini</li>
	 * <li>'MAX_FILE_SIZE' hidden field</li>
	 * <li>{@link maxSize}</li>
	 * </ul>
	 *
	 * @return integer the size limit for uploaded files.
	 */
	protected function getSizeLimit()
	{
		$limit=ini_get('upload_max_filesize');
		$limit=$this->sizeToBytes($limit);
		if($this->maxSize!==null && $limit>0 && $this->maxSize<$limit)
			$limit=$this->maxSize;
		if(isset($_POST['MAX_FILE_SIZE']) && $_POST['MAX_FILE_SIZE']>0 && $_POST['MAX_FILE_SIZE']<$limit)
			$limit=$_POST['MAX_FILE_SIZE'];
		return $limit;
	}

	/**
	 * Converts php.ini style size to bytes.
	 *
	 * Examples of size strings are: 150, 1g, 500k, 5M (size suffix
	 * is case insensitive). If you pass here the number with a fractional part, then everything after
	 * the decimal point will be ignored (php.ini values common behavior). For example 1.5G value would be
	 * treated as 1G and 1073741824 number will be returned as a result. This method is public
	 * (was private before) since 1.1.11.
	 *
	 * @param string $sizeStr the size string to convert.
	 * @return integer the byte count in the given size string.
	 * @since 1.1.11
	 */
	public function sizeToBytes($sizeStr)
	{
		// get the latest character
		switch (strtolower(substr($sizeStr, -1)))
		{
			case 'm': return (int)$sizeStr * 1048576; // 1024 * 1024
			case 'k': return (int)$sizeStr * 1024; // 1024
			case 'g': return (int)$sizeStr * 1073741824; // 1024 * 1024 * 1024
			default: return (int)$sizeStr; // do nothing
		}
	}
}
