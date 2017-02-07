<?php

class CActiveRecordBehavior extends CModelBehavior
{

	public function events()
	{
		return array_merge(parent::events(), array(
			'onBeforeSave'=>'beforeSave',
			'onAfterSave'=>'afterSave',
			'onBeforeDelete'=>'beforeDelete',
			'onAfterDelete'=>'afterDelete',
			'onBeforeFind'=>'beforeFind',
			'onAfterFind'=>'afterFind',
			'onBeforeCount'=>'beforeCount',
		));
	}

	protected function beforeSave($event)
	{
	}

	protected function afterSave($event)
	{
	}

	protected function beforeDelete($event)
	{
	}

	protected function afterDelete($event)
	{
	}

	protected function beforeFind($event)
	{
	}

	protected function afterFind($event)
	{
	}

	protected function beforeCount($event)
	{
	}
}
