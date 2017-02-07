<?php

namespace component\CModel;

class CModelEvent
{
	/**
	 * @var boolean whether the model is in valid status and should continue its normal method execution cycles. Defaults to true.
	 * For example, when this event is raised in a {@link CFormModel} object that is executing {@link CModel::beforeValidate},
	 * if this property is set false by the event handler, the {@link CModel::validate} method will quit after handling this event.
	 * If true, the normal execution cycles will continue, including performing the real validations and calling
	 * {@link CModel::afterValidate}.
	 */
	public $isValid=true;
}
