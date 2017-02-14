<?php

namespace validators;

class CUnsafeValidator extends CValidator
{

	public $safe=false;

	protected function validateAttribute($object,$attribute)
	{
	}
}

