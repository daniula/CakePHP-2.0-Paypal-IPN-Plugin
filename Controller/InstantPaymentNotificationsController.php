<?php
class InstantPaymentNotificationsController extends PaypalIpnAppController {

	var $name = 'InstantPaymentNotifications';
	var $helpers = array('Html', 'Form');
	var $components = array('Email');

/**
 * beforeFilter makes sure the process is allowed by auth
 *  since paypal will need direct access to it.
 */
	public function beforeFilter(){
		parent::beforeFilter();
		foreach (array_keys($this->components) as $component) {
			if (is_a($this->{$component}, 'AuthComponent')) {
				$this->{$component}->allow('process');
			}
		}
		if(isset($this->Security) && $this->action == 'process'){
			$this->Security->validatePost = false;
		}
	}

/**
 * Paypal IPN processing action..
 * This action is the intake for a paypal_ipn callback performed by paypal itself.
 * This action will take the paypal callback, verify it (so trickery) and save the transaction into your database for later review
 *
 * @access public
 * @author Nick Baker
 */
	public function process($id = null){
		$debugging = (Configure::read('debug') && !is_null($id));
		if ($debugging) {

			if (is_numeric($id)) {
				$this->loadModel('Transaction');
				$transaction = $this->Transaction->findById($id);
			}

			if (!empty($transaction)) {
				$ipn = $this->InstantPaymentNotification->findByPayKey($transaction['Transaction']['payment_id']);
			} else {
				$ipn = $this->InstantPaymentNotification->findByPayKey($id);
			}

			$raw = $ipn['InstantPaymentNotification']['raw'];
		} else {
			$raw = file_get_contents("php://input");
		}

		if (!empty($raw)) {
			$data = $this->InstantPaymentNotification->parseRaw($raw);
		  $data['valid'] = $this->InstantPaymentNotification->is_valid($raw);
		  $data['ip'] = remote_ip();
		  $data['raw'] = $raw;

		  $result = $data['valid'] ? 'Valid' : 'Invalid';

	    $notification = $this->InstantPaymentNotification->buildAssociationsFromIPN($data);

	    if ($debugging) {
	    	$this->InstantPaymentNotification->id = $id;
	    	$notification['InstantPaymentNotification']['id'] = $id;
	    }

	    $this->InstantPaymentNotification->saveAll($notification);
	    $this->__processTransaction($this->InstantPaymentNotification->id);
		} else {
			$result = 'empty';
		}

		exit($result);
	}

/**
 * __processTransaction is a private callback function used to log a verified transaction
 * @access private
 * @param String $txnId is the string paypal ID and the id used in your database.
 */
	private function __processTransaction($txnId){
		$this->log("Processing Trasaction: $txnId",'paypal');
		//Put the afterPaypalNotification($txnId) into your app_controller.php
		$this->afterPaypalNotification($txnId);
	}

/**
 * Admin Only Functions... all baked
 */

/**
 * Admin Index
 */
	public function admin_index() {
		$this->InstantPaymentNotification->recursive = 0;
		$this->set('instantPaymentNotifications', $this->paginate());
	}

/**
 * Admin View
 * @param String ID of the transaction to view
 */
	public function admin_view($id = null) {
		if (!$id) {
			$this->Session->setFlash(__('Invalid InstantPaymentNotification.', true));
			$this->redirect(array('action'=>'index'));
		}
		$this->set('instantPaymentNotification', $this->InstantPaymentNotification->read(null, $id));
	}

/**
 * Admin Add
 */
	public function admin_add(){
		$this->redirect(array('admin' => true, 'action' => 'edit'));
	}

/**
 * Admin Edit
 * @param String ID of the transaction to edit
 */
	public function admin_edit($id = null) {
		if (!empty($this->data)) {
			if ($this->InstantPaymentNotification->save($this->data)) {
				$this->Session->setFlash(__('The InstantPaymentNotification has been saved', true));
				$this->redirect(array('action'=>'index'));
			} else {
				$this->Session->setFlash(__('The InstantPaymentNotification could not be saved. Please, try again.', true));
			}
		}
		if ($id && empty($this->data)) {
			$this->data = $this->InstantPaymentNotification->read(null, $id);
		}
	}

/**
 * Admin Delete
 * @param String ID of the transaction to delete
 */
	public function admin_delete($id = null) {
		if (!$id) {
			$this->Session->setFlash(__('Invalid id for InstantPaymentNotification', true));
			$this->redirect(array('action'=>'index'));
		}
		if ($this->InstantPaymentNotification->delete($id)) {
			$this->Session->setFlash(__('InstantPaymentNotification deleted', true));
			$this->redirect(array('action'=>'index'));
		}
	}
}

if (!function_exists('remote_ip')) {
	function remote_ip() {
	  if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
	  	return $_SERVER['HTTP_CF_CONNECTING_IP'];
	  }

	  if (!empty($_SERVER['REMOTE_ADDR'])) {
	  	return $_SERVER['REMOTE_ADDR'];
	  }

	  return false;
	}
}