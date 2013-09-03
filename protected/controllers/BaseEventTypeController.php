<?php
/**
 * OpenEyes
 *
 * (C) Moorfields Eye Hospital NHS Foundation Trust, 2008-2011
 * (C) OpenEyes Foundation, 2011-2013
 * This file is part of OpenEyes.
 * OpenEyes is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
 * OpenEyes is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License along with OpenEyes in a file titled COPYING. If not, see <http://www.gnu.org/licenses/>.
 *
 * @package OpenEyes
 * @link http://www.openeyes.org.uk
 * @author OpenEyes <info@openeyes.org.uk>
 * @copyright Copyright (c) 2008-2011, Moorfields Eye Hospital NHS Foundation Trust
 * @copyright Copyright (c) 2011-2013, OpenEyes Foundation
 * @license http://www.gnu.org/licenses/gpl-3.0.html The GNU General Public License V3.0
 */

class BaseEventTypeController extends BaseController
{
	public $model;

	public $site;
	public $editable = true;
	public $editing;
	public $event;
	public $event_type;
	public $title;
	public $assetPath;
	public $episode;
	public $event_tabs = array();
	public $event_actions = array();
	public $print_css = true;
	public $successUri = 'default/view/';
	public $eventIssueCreate = false;
	public $extraViewProperties = array();
	public $jsVars = array();

	protected $_firm;
	protected $_patient;

	/**
	 * uses the selectedFirmId getter to determine the firm (thereby relying on session information)
	 *
	 * @return Firm|null
	 */
	public function getFirm()
	{
		if (!$this->_firm) {
			$this->_firm = Firm::model()->findByPk($this->getSelectedFirmId());
		}
		return $this->_firm;
	}

	/**
	 * reset the firm property which relies on selected firm id
	 *
	 * @see parent::resetSiteAndFirm()
	 */
	public function resetSiteAndFirm()
	{
		$this->_firm = null;
		parent::resetSiteAndFirm();
	}

	/**
	 * uses the patientId to determine the patient (relying on session information)
	 *
	 * @return Patient|null
	 */
	public function getPatient()
	{
		if (!$this->_patient) {
			$this->_patient = Patient::model()->findByPk($this->getPatientId());
		}
		return $this->_patient;
	}

	/**
	 * reset the patient property
	 *
	 * @param int $patient_id
	 * @see parent::resetSessionPatient($patient_id)
	 */
	public function resetSessionPatient($patient_id)
	{
		$this->_patient = null;
		parent::resetSessionPatient($patient_id);
	}

	/**
	 * Checks to see if current user can create an event type
	 * @param EventType $event_type
	 */
	public function checkEventAccess($event_type)
	{
		$firm = $this->getFirm();
		if (!$firm->service_subspecialty_assignment_id) {
			if (!$event_type->support_services) {
				return false;
			}
		}

		if (BaseController::checkUserLevel(5)) {
			return true;
		}
		if (BaseController::checkUserLevel(4) && $event_type->class_name != 'OphDrPrescription') {
			return true;
		}
		return false;
	}

	/**
	 * standard access rules for events
	 *
	 * @return array
	 *
	 */
	public function accessRules()
	{
		return array(
			// Level 2 can't change anything
			array('allow',
				'actions' => array('view'),
				'expression' => 'BaseController::checkUserLevel(2)',
			),
			// Level 3 or above can do anything
			array('allow',
				'expression' => 'BaseController::checkUserLevel(4)',
			),
			array('deny'),
		);
	}

	/**
	 * Whether the current user is allowed to call print actions
	 * @return boolean
	 */
	public function canPrint()
	{
		return BaseController::checkUserLevel(3);
	}

	/**
	 * renders event metadata
	 */
	public function renderEventMetadata()
	{
		$this->renderPartial('//patient/event_metadata');
	}

	/**
	 * index action
	 */
	public function actionIndex()
	{
		$this->render('index');
	}

	/**
	 * @see parent::printActions()
	 */
	public function printActions()
	{
		return array('print');
	}

	/**
	 * Automatically include js and css asset files for a module
	 *
	 * @param CAction $action
	 * @return bool
	 * @throws CHttpException
	 */
	protected function beforeAction($action)
	{
		// Set asset path
		if (file_exists(Yii::getPathOfAlias('application.modules.'.$this->getModule()->name.'.assets'))) {
			$this->assetPath = Yii::app()->getAssetManager()->publish(Yii::getPathOfAlias('application.modules.'.$this->getModule()->name.'.assets'), false, -1, YII_DEBUG);
		}

		// Automatic file inclusion unless it's an ajax call
		if ($this->assetPath && !Yii::app()->getRequest()->getIsAjaxRequest()) {

			if (in_array($action->id,$this->printActions())) {
				// Register print css
				if (file_exists(Yii::getPathOfAlias('application.modules.'.$this->getModule()->name.'.assets.css').'/print.css')) {
					$this->registerCssFile('module-print.css', $this->assetPath.'/css/print.css');
				}

			} else {
				// Register js
				if (file_exists(Yii::getPathOfAlias('application.modules.'.$this->getModule()->name.'.assets.js').'/module.js')) {
					Yii::app()->clientScript->registerScriptFile($this->assetPath.'/js/module.js');
				}
				if (file_exists(Yii::getPathOfAlias('application.modules.'.$this->getModule()->name.'.assets.js').'/'.get_class($this).'.js')) {
					Yii::app()->clientScript->registerScriptFile($this->assetPath.'/js/'.get_class($this).'.js');
				}

				// Register css
				if (file_exists(Yii::getPathOfAlias('application.modules.'.$this->getModule()->name.'.assets.css').'/module.css')) {
					$this->registerCssFile('module.css',$this->assetPath.'/css/module.css',10);
				}
				if (file_exists(Yii::getPathOfAlias('application.modules.'.$this->getModule()->name.'.assets.css').'/css/'.get_class($this).'.css')) {
					$this->registerCssFile(get_class($this).'.css',$this->assetPath.'/css/'.get_class($this).'.css',10);
				}
			}
		}

		if (!$this->getFirm()) {
			// No firm selected, reject
			throw new CHttpException(403, 'You are not authorised to view this page without selecting a firm.');
		}

		return parent::beforeAction($action);
	}

	/**
	 * get the saved elements for the given event
	 *
	 * @param Event $event
	 * @return BaseEventTypeElement[]
	 */
	protected function getSavedElements($event)
	{
		$elements = array();
		$criteria = array('order' => 'display_order');
		$criteria['condition'] = 'event_type_id = :event_type_id AND parent_element_type_id is NULL';
		$criteria['params'] = array(':event_type_id' => $event->event_type_id);

		// go through all elements for this event type, and check for instances for this event
		foreach (ElementType::model()->findAll($criteria) as $element_type) {
			$element_class = $element_type->class_name;
			if ($element = $element_class::model()->find('event_id = ?', array($event->id))) {
				$elements[] = $element;
			}
		}
		return $elements;
	}

	/**
	 * return the standard set of elements for the event
	 * (note this is abstracted to allow override for event types that allow configurable clean sets of elements
	 * @param integer $event_type_id
	 * @return array
	 */
	protected function getCleanDefaultElements($event_type_id)
	{
		$criteria = new CDbCriteria;
		$criteria->compare('event_type_id',$event_type_id);
		$criteria->order = 'display_order asc';
		$criteria->compare('`default`',1);

		$elements = array();
		foreach (ElementType::model()->findAll($criteria) as $element_type) {
			if (!$element_type->parent_element_type_id) {
				$elements[] = new $element_type->class_name;
			}
		}

		return $elements;
	}

	/**
	 * Use this for any many to many relations defined on your elements. This is called prior to validation
	 * so should set values without actually touching the database.
	 *
	 * @param BaseEventTypeElement $element
	 */
	protected function setPostedElementManyToMany($element)
	{
		// stub method
	}

	/**
	 * Uses the $_POST to determine the elements that have been submitted, and instantiates them
	 * as appropriate
	 *
	 * NOTE: works on the assumption that there can only be one element of any given class
	 *
	 * @param Event $event
	 * @return BaseEventTypeElement[]
	 */
	protected function getPostedElements($event)
	{
		foreach ($_POST as $key => $value) {
			if (preg_match('/^Element|^OEElement/',$key)) {
				if ($element_type = ElementType::model()->find('class_name=?',array($key))) {
					$element_class = $element_type->class_name;

					if ((is_null($event) || $event->getIsNewRecord())
						|| !($element = $element_class::model()->find('event_id = ?',array($event->id)))
					) {
						$element= new $element_class;
					}
					$element->attributes = Helper::convertNHS2MySQL($_POST[$key]);
					$this->setPostedElementManyToMany($element);

					$elements[] = $element;
				}
			}
		}

		return $elements;
	}
	/**
	 * Get all the elements that are required for the current action, based on the event type and submitted values in
	 * $_POST. Note it does not set attributes on the elements from $_POST.
	 *
	 * @param string $action
	 * @param int $event_type_id
	 * @param Event $event
	 * @return BaseEventTypeElement[]
	 */
	public function getDefaultElements($action, $event_type_id = null, $event = null)
	{
		if (!$event && isset($this->event)) {
			$event = $this->event;
		}

		if (isset($event->event_type_id)) {
			$event_type = EventType::model()->find('id = ?',array($event->event_type_id));
		} elseif ($event_type_id) {
			$event_type = EventType::model()->find('id = ?',array($event_type_id));
		} else {
			$event_type = EventType::model()->find('class_name = ?',array($this->getModule()->name));
		}

		$elements = null;

		if (empty($_POST)) {
			if (!$event || $event->getIsNewRecord()) {
				$elements = $this->getCleanDefaultElements($event_type->id);
			}
			else {
				$elements = $this->getSavedElements($event);
			}
		} else {
			$elements = $this->getPostedElements($event);
		}

		return $elements;
	}

	/**
	 * Get the optional elements for the current module's event type
	 * This will be overriden by the module
	 *
	 * @return array
	 */
	public function getOptionalElements($action)
	{
		switch ($action) {
			case 'create':
			case 'view':
			case 'print':
				return array();
			case 'update':
				$event_type = EventType::model()->findByPk($this->event->event_type_id);

				$criteria = new CDbCriteria;
				$criteria->compare('event_type_id',$event_type->id);
				$criteria->compare('`default`',1);
				$criteria->order = 'display_order asc';

				$elements = array();
				$element_classes = array();

				foreach (ElementType::model()->findAll($criteria) as $element_type) {
					$element_class = $element_type->class_name;
					if (!$element_class::model()->find('event_id = ?',array($this->event->id))) {
						$elements[] = new $element_class;
					}
				}

				return $elements;
		}
	}

	/**
	 * Firm changing sanity
	 *
	 */
	protected function checkFirmChange()
	{
		if (!empty($_POST) && !empty($_POST['firm_id']) && $_POST['firm_id'] != $this->firm->id) {
			// The firm id in the firm is not the same as the session firm id, e.g. they've changed
			// firms in a different tab. Set the session firm id to the provided firm id.

			$firms = $session['firms'];
			$firmId = intval($_POST['firm_id']);

			if ($firms[$firmId]) {
				$session['selected_firm_id'] = $firmId;
				$this->resetSiteAndFirm();
			} else {
				// They've supplied a firm id in the post to which they are not entitled??
				throw new Exception('Invalid firm id on attempting to create event.');
			}
		}
	}

	/**
	 * checks if form has been cancelled
	 *
	 * @return bool - true if cancelled
	 */
	protected function checkIsCancelled()
	{
		if (!empty($_POST) && isset($_POST['cancel'])) {
			$this->redirect(array('/patient/view/'.$this->patient->id));
			return true;
		}
		return false;
	}

	/**
	 * Action for creating an event. Will render a form, or process a submitted form (rendering validation errors
	 * or redirecting to the view of the succcessfully created event)
	 *
	 * @return bool|string
	 * @throws CHttpException
	 * @throws Exception
	 */
	public function actionCreate()
	{
		$this->event_type = EventType::model()->find('class_name=?', array($this->getModule()->name));

		if (!$patient = Patient::model()->findByPk($_REQUEST['patient_id'])) {
			throw new CHttpException(403, 'Invalid patient_id.');
		}

		$this->setSessionPatient($patient);

		if (is_array(Yii::app()->params['modules_disabled']) && in_array($this->event_type->class_name,Yii::app()->params['modules_disabled'])) {
			$this->redirect(array('/patient/episodes/'.$this->patient->id));
			return;
		}

		$this->episode = $this->getEpisode($this->firm, $this->patient->id);

		if (!$this->event_type->support_services && !$this->firm->serviceSubspecialtyAssignment) {
			throw new Exception("Can't create a non-support service event for a support-service firm");
		}

		if (!$episode = $this->patient->getEpisodeForCurrentSubspecialty()) {
			throw new Exception("There is no open episode for the currently selected firm's subspecialty");
		}

		$this->checkFirmChange();

		$this->checkIsCancelled() && Yii::app()->end;

		$elements = $this->getDefaultElements('create', $this->event_type->id);

		if (empty($_POST) && !count($elements)) {
			// this is a module setup error
			throw new CHttpException(403, 'Gadzooks!	I got me no elements!');
		}

		if (!empty($_POST) && !count($elements)) {
			$errors['Event'][] = 'No elements selected';
		} elseif (!empty($_POST)) {

			// validation
			$errors = $this->validateElements($elements);

			// creation
			if (empty($errors)) {

				try {
					$event_id = $this->createEventFromElements($this->event_type, $elements);


					$this->logActivity('created event.');
					OELog::log("Updated event {$event_id}");
					Yii::app()->user->setFlash('success', "{$this->event_type->name} created.");
					$this->redirect(array($this->successUri.$event_id));
					Yii::app()->end();
				}
				catch (Exception $e) {
					$errors['Event'][] = 'An unexpected error has occurred';
				}

			}
		}

		$this->editable = false;
		$this->title = 'Create';
		$this->event_tabs = array(
				array(
						'label' => 'Create',
						'active' => true,
				),
		);

		$cancel_url = ($this->episode) ? '/patient/episode/'.$this->episode->id : '/patient/episodes/'.$this->patient->id;
		$this->event_actions = array(
				EventAction::link('Cancel',
						Yii::app()->createUrl($cancel_url),
						array('colour' => 'red', 'level' => 'secondary')
				)
		);

		$this->processJsVars();
		$this->renderPartial(
			'create',
			array('elements' => $elements, 'eventId' => null, 'errors' => @$errors),
			// processOutput is true so that the css/javascript from the event_header.php are processed when rendering the view
			false, true
		);

	}

	/**
	 * view the event specified by the id
	 *
	 * @param $id
	 * @throws CHttpException
	 */
	public function actionView($id)
	{
		if (!$this->event = Event::model()->findByPk($id)) {
			throw new CHttpException(403, 'Invalid event id.');
		}

		$this->setSessionPatient($this->event->episode->patient);

		$this->event_type = EventType::model()->findByPk($this->event->event_type_id);
		$this->episode = $this->event->episode;

		$elements = $this->getDefaultElements('view');

		// Decide whether to display the 'edit' button in the template
		if ($this->editable) {
			if (!BaseController::checkUserLevel(4) || (!$this->event->episode->firm && !$this->event->episode->support_services)) {
				$this->editable = false;
			} else {
				if ($this->firm->serviceSubspecialtyAssignment) {
					if ($this->event->episode->firm && $this->firm->serviceSubspecialtyAssignment->subspecialty_id != $this->event->episode->firm->serviceSubspecialtyAssignment->subspecialty_id) {
						$this->editable = false;
					}
				} else {
					if ($this->event->episode->firm !== null) {
						$this->editable = false;
					}
				}
			}
		}
		// Allow elements to override the editable status
		if ($this->editable) {
			foreach ($elements as $element) {
				if (!$element->isEditable()) {
					$this->editable = false;
					break;
				}
			}
		}

		$this->logActivity('viewed event');

		$this->event->audit('event','view',false);

		$this->title = $this->event_type->name;
		$this->event_tabs = array(
				array(
						'label' => 'View',
						'active' => true,
				)
		);
		if ($this->editable) {
			$this->event_tabs[] = array(
					'label' => 'Edit',
					'href' => Yii::app()->createUrl($this->event->eventType->class_name.'/default/update/'.$this->event->id),
			);
		}
		if ($this->event->canDelete()) {
			$this->event_actions = array(
					EventAction::link('Delete',
							Yii::app()->createUrl($this->event->eventType->class_name.'/default/delete/'.$this->event->id),
							array('colour' => 'red', 'level' => 'secondary'),
							array('class' => 'trash')
					)
			);
		}

		$this->processJsVars();
		$this->renderPartial(
			'view', array_merge(array(
			'elements' => $elements,
			'eventId' => $id,
			), $this->extraViewProperties), false, true);
	}

	/**
	 * update the event specified by the id - process the form submission, or create the form for the edit
	 *
	 * @param $id
	 * @throws CHttpException
	 * @throws SystemException
	 * @throws Exception
	 */
	public function actionUpdate($id)
	{
		if (!$this->event = Event::model()->findByPk($id)) {
			throw new CHttpException(403, 'Invalid event id.');
		}

		// Check the user's firm is of the correct subspecialty to have the
		// rights to update this event
		if ($this->firm->serviceSubspecialtyAssignment
			&& $this->firm->serviceSubspecialtyAssignment->subspecialty_id != $this->event->episode->firm->serviceSubspecialtyAssignment->subspecialty_id
		) {
			throw new CHttpException(403, 'The firm you are using is not associated with the subspecialty for this event.');
		} elseif (!$this->firm->serviceSubspecialtyAssignment && $this->event->episode->firm !== null) {
			throw new CHttpException(403, 'The firm you are using is not a support services firm.');
		}

		$this->event_type = EventType::model()->findByPk($this->event->event_type_id);
		$this->setSessionPatient($this->event->episode->patient);
		$this->episode = $this->event->episode;

		$this->checkFirmChange();

		$this->checkIsCancelled() && Yii::app()->end();

		$elements = $this->getDefaultElements($this->action->id);

		if (empty($_POST) && !count($elements)) {
			throw new CHttpException(403, 'Gadzooks!	I got me no elements!');
		}

		if (!empty($_POST) && !count($elements)) {
			$errors['Event'][] = 'No elements selected';
		} elseif (!empty($_POST)) {


			// validation
			$errors = $this->validateElements($elements);

			// update
			if (empty($errors)) {

				try {
					$this->updateEventFromElements($this->event, $elements);
					$this->logActivity('updated event');
					OELog::log("Updated event {$this->event->id}");
					$this->redirect(array('default/view/'.$this->event->id));
					Yii::app()->end();
				}
				catch (Exception $e) {
					$errors['Event'][] = 'An unexpected error has occurred';
				}
			}
		}

		// set up buttons
		$this->editing = true;
		$this->title = 'Update';
		$this->event_tabs = array(
				array(
						'label' => 'View',
						'href' => Yii::app()->createUrl($this->event->eventType->class_name.'/default/view/'.$this->event->id),
				),
				array(
						'label' => 'Edit',
						'active' => true,
				),
		);

		$this->event_actions = array(
				EventAction::link('Cancel',
						Yii::app()->createUrl($this->event->eventType->class_name.'/default/view/'.$this->event->id),
						array('colour' => 'red', 'level' => 'secondary')
				)
		);

		$this->processJsVars();

		// render
		$this->renderPartial(
			$this->action->id,
			array(
				'elements' => $elements,
				'errors' => @$errors
			),
			// processOutput is true so that the css/javascript from the event_header.php are processed when rendering the view
			false, true
		);
	}

	/**
	 * Validates elements and processes any errors raised
	 *
	 * @param BaseEventTypeElement[] $elements
	 * @return array $errors - indexed by the class name of the elements that have errors
	 */
	protected function validateElements($elements)
	{
		$errors = array();
		foreach ($elements as $element) {
			if (!$element->validate()) {
				$elementName = $element->getElementType()->name;
				foreach ($element->getErrors() as $errormsgs) {
					foreach ($errormsgs as $error) {
						$errors[$elementName][] = $error;
					}
				}
			}
		}

		return $errors;
	}

	/**
	 * Use this for any many to many relations defined on your elements. This is called prior to validation
	 * so should set values without actually touching the database. To do that, the createElements and updateElements
	 * methods should be extended to handle the POST values.
	 *
	 * @param BaseEventTypeElement $element
	 * @deprecated since 1.5 - use setPostedElementManyToMany($element) instead
	 */
	protected function setPOSTManyToMany($element)
	{
		// placeholder function
	}

	/**
	 * Uses the POST values to define elements and their field values without hitting the db, and then performs validation
	 * Validates elements and processes any errors raised
	 *
	 * @param BaseEventTypeElement[] $elements
	 * @deprecated since 1.5 - use validateElements($elements) instead
	 */
	protected function validatePOSTElements($elements)
	{
		$errors = array();
		foreach ($elements as $element) {
			$elementClassName = get_class($element);
			$element->attributes = Helper::convertNHS2MySQL($_POST[$elementClassName]);
			$this->setPOSTManyToMany($element);
			if (!$element->validate()) {
				$elementName = $element->getElementType()->name;
				foreach ($element->getErrors() as $errormsgs) {
					foreach ($errormsgs as $error) {
						$errors[$elementName][] = $error;
					}
				}
			}
		}

		return $errors;
	}

	public function renderDefaultElements($action, $form=false, $data=false)
	{
		foreach ($this->getDefaultElements($action) as $element) {
			if ($action == 'create' && empty($_POST)) {
				$element->setDefaultOptions();
			}

			$view = ($element->{$action.'_view'}) ? $element->{$action.'_view'} : $element->getDefaultView();
			$this->renderPartial(
				$action . '_' . $view,
				array('element' => $element, 'data' => $data, 'form' => $form),
				false, false
			);
		}
	}

	public function renderOptionalElements($action, $form=false,$data=false)
	{
		foreach ($this->getOptionalElements($action) as $element) {
			if ($action == 'create' && empty($_POST)) {
				$element->setDefaultOptions();
			}

			$view = ($element->{$action.'_view'}) ? $element->{$action.'_view'} : $element->getDefaultView();
			$this->renderPartial(
				$action . '_' . $view,
				array('element' => $element, 'data' => $data, 'form' => $form),
				false, false
			);
		}
	}

	/**
	 * render the header
	 *
	 * @param boolean $editable
	 */
	public function header($editable=null)
	{
		$episodes = $this->patient->episodes;
		$ordered_episodes = $this->patient->getOrderedEpisodes();

		$legacyepisodes = $this->patient->legacyepisodes;
		$supportserviceepisodes = $this->patient->supportserviceepisodes;

		if ($editable === null) {
			if (isset($this->event)) {
				$editable = $this->event->editable;
			} else {
				$editable = false;
			}
		}

		$this->renderPartial('//patient/event_header',array(
			'ordered_episodes'=>$ordered_episodes,
			'legacyepisodes'=>$legacyepisodes,
			'supportserviceepisodes'=>$supportserviceepisodes,
			'eventTypes'=>EventType::model()->getEventTypeModules(),
			'model'=>$this->patient,
			'editable'=>$editable,
		));
	}

	/**
	 * render the footer
	 *
	 */
	public function footer()
	{
		$episodes = $this->patient->episodes;
		$legacyepisodes = $this->patient->legacyepisodes;
		$supportserviceepisodes = $this->patient->supportserviceepisodes;

		$this->renderPartial('//patient/event_footer',array(
			'episodes'=>$episodes,
			'legacyepisodes'=>$legacyepisodes,
			'supportserviceepisodes'=>$supportserviceepisodes,
			'eventTypes'=>EventType::model()->getEventTypeModules()
		));
	}

	/**
	 * create an event of the given type, with the given elements
	 *
	 * This function should only be called with validated elements
	 *
	 * @param EventType $event_type
	 * @param BaseEventTypeElement[] $elements
	 *
	 * @throws Exception
	 * @return string $event_id
	 */
	protected function createEventFromElements($event_type, $elements)
	{
		// start a transaction
		$transaction = Yii::app()->getDb()->beginTransaction();

		try {
			$audit_data = array();

			/**
			 * Create the event. First check to see if there is currently an episode for this
			 * subspecialty for this patient. If so, add the new event to it. If not, create an
			 * episode and add it to that.
			 */
			error_log($this->getPatientId());
			$episode = $this->getOrCreateEpisode($this->firm, $this->patientId);

			// need to put the info text together
			$info_text = '';
			foreach ($elements as $element) {
				if ($element->infotext) {
					$info_text .= $element->infotext;
				}
			}

			$event = new Event();
			$event->episode_id = $episode->id;
			$event->event_type_id = $event_type->id;
			$event->info = $info_text;

			if ($this->eventIssueCreate) {
				$event->addIssue($this->eventIssueCreate);
			}

			if (!$event->save()) {
				throw new SystemException('Unable to create new event for episode_id=$episode->id, event_type_id=$eventTypeId');
			}

			OELog::log("Created new event for episode_id=$episode->id, event_type_id=" . $event_type->id);

			foreach ($elements as $element) {
				$element_class = get_class($element);

				$element->event_id = $event->id;

				if (!$element->save()) {
					OELog::log("Unable to save element: $element->id ($element_class): ".print_r($element->getErrors(),true));
					throw new SystemException('Unable to save element: '.print_r($element->getErrors(),true));
				}
				$audit_data[$element_class] = $element->getAuditAttributes();
			}

			$audit_data['event'] = $event->getAuditAttributes();
			$event->audit('event','update',serialize($audit_data));

			$transaction->commit();

			return $event->id;
		}
		catch (Exception $e) {
			$transaction->rollback();
			throw $e;
		}

	}

	/**
	 * update the given event with the provided elements (remove any elements currently on the event
	 * that are not part of the given set of elements)
	 *
	 * This function should only be called with validated elements
	 *
	 * @param Event $event
	 * @param BaseEventTypeElement[] $elements
	 *
	 * @throws Exception
	 * @return int $event_id
	 */
	protected function updateEventFromElements($event, $elements)
	{
		// start a transaction
		$transaction = Yii::app()->getDb()->beginTransaction();

		try {
			$audit_data = array();

			$event_type = $event->eventType;
			$updating_elements = array();
			$info_text = '';
			// iterate through all the given elements, tracking them so we know what has been saved (and grabbing info text)
			foreach ($elements as $element) {
				$element_class = get_class($element);
				$updating_elements[] = $element_class;

				// if its new, we need to set the event id
				if (!isset($element->event_id)) {
					$element->event_id = $event->id;
				}

				// grab the info text
				if ($element->infotext) {
					$info_text .= $element->infotext;
				}

				if (!$element->save()) {
					OELog::log("Unable to save element: $element->id ($element_class): ".print_r($element->getErrors(),true));
					throw new SystemException('Unable to save element: '.print_r($element->getErrors(),true));
				}
				$audit_data[$element_class] = $element->getAuditAttributes();
			}
			// iterate through all possible elements for the event type
			// if not saved, delete it
			foreach ($event_type->elementTypes as $element_type) {
				$element_class = $element_type->class_name;
				if (!in_array($element_class, $updating_elements)) {
					if ($to_delete = $element_class::model()->find('event_id = ?', array($event->id))) {
						if (!$to_delete->delete()) {
							OELog::log("Unable to delete element: $to_delete->id ($element_class): " . print_r($to_delete->getErrors(), true));
							throw new SystemException('Unable to delete element: ' . print_r($to_delete->getErrors(), true));
						}
					}
				}
			}
			// update the event with the info text and save it

			$event->user = Yii::app()->user->id;
			$event->info = $info_text;

			$audit_data['event'] = $event->getAuditAttributes();
			$event->audit('event','update',serialize($audit_data));

			if (!$this->event->save()) {
				throw new SystemException('Unable to update event: '.print_r($this->event->getErrors(),true));
			}

			$transaction->commit();

			return $event->id;
		}
		catch (Exception $e) {
			$transaction->rollback();
			throw $e;
		}

	}

	/**
	 * @param $elements
	 * @param $data
	 * @param $firm
	 * @param $patientId
	 * @param $userId
	 * @param $eventTypeId
	 * @return bool|string
	 * @throws Exception
	 *
	 * @deprecated since 1.5 - use createEventFromElements($event_type, $elements) instead
	 */
	public function createElements($elements, $data, $firm, $patientId, $userId, $eventTypeId)
	{
		$valid = true;
		$elementsToProcess = array();

		// Go through the array of elements to see which the user is attempting to
		// create, which are required and whether they pass validation.
		foreach ($elements as $element) {
			$elementClassName = get_class($element);

			if ($element->required || isset($data[$elementClassName])) {
				if (isset($data[$elementClassName])) {
					$keys = array_keys($data[$elementClassName]);

					if (is_array($data[$elementClassName][$keys[0]])) {
						for ($i=0; $i<count($data[$elementClassName][$keys[0]]); $i++) {
							$element = new $elementClassName;

							foreach ($keys as $key) {
								if ($key != '_element_id') {
									$element->{$key} = $data[$elementClassName][$key][$i];
								}
							}

							$this->setPOSTManyToMany($element);

							if (!$element->validate()) {
								$valid = false;
							} else {
								$elementsToProcess[] = $element;
							}
						}
					} else {
						$element->attributes = Helper::convertNHS2MySQL($data[$elementClassName]);

						$this->setPOSTManyToMany($element);

						if (!$element->validate()) {
							$valid = false;
						} else {
							$elementsToProcess[] = $element;
						}
					}
				}
			}
		}

		if (!$valid) {
			return false;
		}

		/**
		 * Create the event. First check to see if there is currently an episode for this
		 * subspecialty for this patient. If so, add the new event to it. If not, create an
		 * episode and add it to that.
		 */
		$episode = $this->getOrCreateEpisode($firm, $patientId);
		$event = $this->createEvent($episode, $userId, $eventTypeId, $elementsToProcess);

		// Create elements for the event
		foreach ($elementsToProcess as $element) {
			$element->event_id = $event->id;

			// No need to validate as it has already been validated and the event id was just generated.
			if (!$element->save(false)) {
				throw new Exception('Unable to save element ' . get_class($element) . '.');
			}
		}

		$this->afterCreateElements($event);

		return $event->id;
	}

	/**
	 * Update elements based on arrays passed over from $_POST data
	 *
	 * @param BaseEventTypeElement[] $elements
	 * @param array $data $_POST data to update
	 * @param Event $event the associated event
	 *
	 * @throws SystemException
	 * @return bool true if all elements succeeded, false otherwise
	 * @deprecated since 1.5
	 */
	public function updateElements($elements, $data, $event)
	{
		$success = true;
		$toDelete = array();
		$toSave = array();

		foreach ($elements as $element) {
			$elementClassName = get_class($element);
			$needsValidation = false;

			if (isset($data[$elementClassName])) {
				$keys = array_keys($data[$elementClassName]);

				if (is_array($data[$elementClassName][$keys[0]])) {
					if (!$element->id || in_array($element->id,$data[$elementClassName]['_element_id'])) {
						$i = array_search($element->id,$data[$elementClassName]['_element_id']);

						$properties = array();
						foreach ($data[$elementClassName] as $key => $values) {
							$properties[$key] = $values[$i];
						}
						$element->attributes = Helper::convertNHS2MySQL($properties);

						$toSave[] = $element;
						$needsValidation = true;
					} else {
						$toDelete[] = $element;
					}
				} else {
					$element->attributes = Helper::convertNHS2MySQL($data[$elementClassName]);
					$toSave[] = $element;
					$needsValidation = true;
				}
			} elseif ($element->required) {
				// The form has failed to provide an array of data for a required element.
				// This isn't supposed to happen - a required element should at least have the
				// $data[$elementClassName] present, even if there's nothing in it.
				$success = false;
			} elseif ($element->event_id) {
				// This element already exists, isn't required and has had its data deleted.
				// Therefore it needs to be deleted.
				$toDelete[] = $element;
			}

			if ($needsValidation) {
				$this->setPOSTManyToMany($element);
				if (!$element->validate()) {
					$success = false;
				}
			}
		}

		if (!$success) {
			// An element failed validation or a required element didn't have an
			// array of data provided for it.
			return false;
		}

		foreach ($toSave as $element) {
			if (!isset($element->event_id)) {
				$element->event_id = $event->id;
			}

			if (!$element->save()) {
				OELog::log("Unable to save element: $element->id ($elementClassName): ".print_r($element->getErrors(),true));
				throw new SystemException('Unable to save element: '.print_r($element->getErrors(),true));
			}
		}

		foreach ($toDelete as $element) {
			$element->delete();
		}

		$this->afterUpdateElements($event);

		return true;
	}

	/**
	 * Called after event (and elements) has been updated
	 * @param Event $event
	 *
	 * @deprecated since 1.5
	 */
	protected function afterUpdateElements($event)
	{
	}

	/**
	 * Called after event (and elements) have been created
	 * @param Event $event
	 *
	 * @deprecated since 1.5
	 */
	protected function afterCreateElements($event)
	{
	}

	/**
	 * @param Firm $firm
	 * @param integer $patientId
	 * @return Episode
	 */
	public function getEpisode($firm, $patientId)
	{
		if ($firm->service_subspecialty_assignment_id) {
			$subspecialtyId = $firm->serviceSubspecialtyAssignment->subspecialty->id;
			return Episode::model()->getBySubspecialtyAndPatient($subspecialtyId, $patientId);
		}
		return Episode::model()->find('patient_id=? and support_services=?',array($patientId,1));
	}

	public function getOrCreateEpisode($firm, $patientId)
	{
		if (!$episode = $this->getEpisode($firm, $patientId)) {
			$episode = Patient::model()->findByPk($patientId)->addEpisode($firm);
		}

		return $episode;
	}

	/**
	 * @param $episode
	 * @param $userId
	 * @param $eventTypeId
	 * @param $elementsToProcess
	 * @return Event
	 * @throws Exception
	 *
	 * @deprecated since 1.5 - use createEventFromElements($event_type, $elements) instead
	 */
	public function createEvent($episode, $userId, $eventTypeId, $elementsToProcess)
	{
		$info_text = '';

		foreach ($elementsToProcess as $element) {
			if ($element->infotext) {
				$info_text .= $element->infotext;
			}
		}

		$event = new Event();
		$event->episode_id = $episode->id;
		$event->event_type_id = $eventTypeId;
		$event->info = $info_text;

		if (!$event->save()) {
			OELog::log("Failed to creat new event for episode_id=$episode->id, event_type_id=$eventTypeId");
			throw new Exception('Unable to save event.');
		}

		OELog::log("Created new event for episode_id=$episode->id, event_type_id=$eventTypeId");

		return $event;
	}

	public function displayErrors($errors)
	{
		$this->renderPartial('//elements/form_errors',array('errors'=>$errors));
	}

	/**
	 * Print action
	 * @param integer $id event id
	 */
	public function actionPrint($id)
	{
		$this->printInit($id);
		$elements = $this->getDefaultElements('print');
		$pdf = (isset($_GET['pdf']) && $_GET['pdf']);
		$this->printLog($id, $pdf);
		if ($pdf) {
			$this->printPDF($id, $elements);
		} else {
			$this->printHTML($id, $elements);
		}
	}

	/**
	 * Initialise print action
	 * @param integer $id event id
	 * @throws CHttpException
	 */
	protected function printInit($id)
	{
		if (!$this->event = Event::model()->findByPk($id)) {
			throw new CHttpException(403, 'Invalid event id.');
		}
		$this->patient = $this->event->episode->patient;
		$this->event_type = $this->event->eventType;
		$this->site = Site::model()->findByPk(Yii::app()->session['selected_site_id']);
		$this->title = $this->event_type->name;
	}

	/**
	 * Render HTML
	 * @param integer $id event id
	 * @param array $elements
	 */
	protected function printHTML($id, $elements, $template='print')
	{
		$this->layout = '//layouts/print';
		$this->render($template, array(
			'elements' => $elements,
			'eventId' => $id,
		));
	}

	/**
	 * Render PDF
	 * @param integer $id event id
	 * @param array $elements
	 */
	protected function printPDF($id, $elements, $template='print', $params=array())
	{
		// Remove any existing css
		Yii::app()->getClientScript()->reset();

		$this->layout = '//layouts/pdf';
		$pdf_print = new OEPDFPrint('Openeyes', 'PDF', 'PDF');
		$oeletter = new OELetter();
		$oeletter->setBarcode('E:'.$id);
		$body = $this->render($template, array_merge($params,array(
			'elements' => $elements,
			'eventId' => $id,
		)), true);
		$oeletter->addBody($body);
		$pdf_print->addLetter($oeletter);
		$pdf_print->output();
	}

	/**
	 * Log print action
	 * @param integer $id event id
	 * @param boolean $pdf
	 */
	protected function printLog($id, $pdf)
	{
		$this->logActivity("printed event (pdf=$pdf)");
		$this->event->audit('event','print',false);
	}

	public function actionDelete($id)
	{
		if (!$this->event = Event::model()->findByPk($id)) {
			throw new CHttpException(403, 'Invalid event id.');
		}

		// Only the event creator can delete the event, and only 24 hours after its initial creation
		if (!$this->event->canDelete()) {
			$this->redirect(array('default/view/'.$this->event->id));
			return false;
		}

		if (!empty($_POST)) {
			$this->event->deleted = 1;
			if (!$this->event->save()) {
				throw new Exception("Unable to mark event deleted: ".print_r($this->event->getErrors(),true));
			}

			$this->event->audit('event','delete',false);

			if (Event::model()->count('episode_id=?',array($this->event->episode_id)) == 0) {
				$this->event->episode->deleted = 1;
				if (!$this->event->episode->save()) {
					throw new Exception("Unable to save episode: ".print_r($this->event->episode->getErrors(),true));
				}

				$this->event->episode->audit('episode','delete',false);

				header('Location: '.Yii::app()->createUrl('/patient/episodes/'.$this->event->episode->patient->id));
				return true;
			}

			Yii::app()->user->setFlash('success', "An event was deleted, please ensure the episode status is still correct.");

			header('Location: '.Yii::app()->createUrl('/patient/episode/'.$this->event->episode_id));
			return true;
		}

		$this->patient = $this->event->episode->patient;

		$this->event_type = EventType::model()->findByPk($this->event->event_type_id);

		$this->title = "Delete ".$this->event_type->name;
		$this->event_tabs = array(
				array(
						'label' => 'View',
						'active' => true,
				)
		);
		if ($this->editable) {
			$this->event_tabs[] = array(
					'label' => 'Edit',
					'href' => Yii::app()->createUrl($this->event->eventType->class_name.'/default/update/'.$this->event->id),
			);
		}

		$this->processJsVars();
		$this->renderPartial(
			'delete', array(
			'eventId' => $id,
			), false, true);

		return false;
	}

	public function processJsVars()
	{
		if ($this->patient) {
			$this->jsVars['OE_patient_id'] = $this->patient->id;
		}
		if ($this->event) {
			$this->jsVars['OE_event_id'] = $this->event->id;
			$this->jsVars['OE_print_url'] = Yii::app()->createUrl($this->getModule()->name."/default/print/".$this->event->id);
		}
		$this->jsVars['OE_asset_path'] = $this->assetPath;
		$firm = Firm::model()->findByPk(Yii::app()->session['selected_firm_id']);
		$subspecialty_id = $firm->serviceSubspecialtyAssignment ? $firm->serviceSubspecialtyAssignment->subspecialty_id : null;
		$this->jsVars['OE_subspecialty_id'] = $subspecialty_id;

		parent::processJsVars();
	}
}
