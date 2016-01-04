<?php
/*********************************
	Drupal Z-Ray Extension
	Version: 1.00
**********************************/
$drupalExt = new ZRayExtensionDrupal();
$zre = new \ZRayExtension('drupal');

$zre->setMetadata(array(
	'logo' => __DIR__ . DIRECTORY_SEPARATOR . 'logo.png',
));

$zre->setEnabledAfter('drupal_bootstrap');

$zre->traceFunction('module_invoke', function(){}, array($drupalExt, 'ModuleInvoke'));
$zre->traceFunction('drupal_load', function(){}, array($drupalExt, 'DrupalLoad'));
$zre->traceFunction('call_user_func', function(){}, array($drupalExt, 'Events'));
$zre->traceFunction('menu_execute_active_handler', function(){}, array($drupalExt, 'ActiveHandlers'));
$zre->traceFunction('drupal_retrieve_form', function(){}, array($drupalExt, 'RetrieveForm'));

// tracing locks
$zre->traceFunction('lock_acquire', function(){}, array($drupalExt, 'LockAcquire'));
$zre->traceFunction('lock_release', function(){}, array($drupalExt, 'LockRelease'));
$zre->traceFunction('lock_wait', function(){}, array($drupalExt, 'LockWait'));
	
class ZRayExtensionDrupal {
	public function ModuleInvoke($context, & $storage) {
		$module = $context['functionArgs'][0];
		$action = $context['functionArgs'][1];
		$hook = isset($context['functionArgs'][2]) ? $context['functionArgs'][2] : '';
		
		$storage['moduleInvoke'][$module] = array('module' => $module, 'action' => $action, 'hook' => $hook);
	}
	
	public function DrupalLoad($context, & $storage) {
		$module = $context['functionArgs'][1];
		$storage['LoadedModules'][$module] = array('module' => $module);
	}
	
	public function Events($context, & $storage) {
		$called = $context['functionArgs'][0];
		$parameter = isset($context['functionArgs'][1]) ? $context['functionArgs'][1] : '';
		$blob = isset($context['functionArgs'][2]) ? json_encode($context['functionArgs'][2]) : '';
		
		if (! $this->is_closure($called) && ! $this->is_closure($parameter) && ! $this->is_closure($blob) && ! is_array($called) && ! is_object($called) ) {
			$storage['CalledFunctions'][$called] = array('called' => $called, 'parameter' => $parameter, 'info' => $blob);
		}
	}
	
	public function ActiveHandlers($context, & $storage) {
		global $user;
	
		$stateUser = (array)$user;
		$stateUserKeys = array_keys($stateUser);
		
		$stateUser['roles'] = array_reduce($stateUser['roles'], function($reduced, $item){
			if ($reduced == '') {
				$reduced = $item;
			} else {
				$reduced .= ", $item";
			}
			return $reduced;
		});
		
		$stateUserValues = array_map(function($key) use ($stateUser) {
			return array('property' => $key, 'value' => $stateUser[$key]);
		}, $stateUserKeys);
		
		$storage['userProperties'] = $stateUserValues;
	}
	
	public function LockAcquire($context, & $storage) {
		$storage['locks'][] = array('action' => 'Acquire', 'name' => $context['functionArgs'][0]);
	}
	
	public function LockRelease($context, & $storage) {
		$storage['locks'][] = array('action' => 'Release', 'name' => $context['functionArgs'][0]);
	}
	
	public function LockWait($context, & $storage) {
		$storage['locks'][] = array('action' => 'Wait', 'name' => $context['functionArgs'][0]);
	}
	
	public function RetrieveForm($context, & $storage) {
		$formName = $context['functionArgs'][0];
	 	$formId = isset($context['functionArgs'][1]['build_info']['form_id']) ? $context['functionArgs'][1]['build_info']['form_id'] : '';
	 	$baseFormId = isset($context['functionArgs'][1]['build_info']['base_form_id']) ? $context['functionArgs'][1]['build_info']['base_form_id'] : '';
	 	$cache = $context['functionArgs'][1]['cache'];
	 	$method = $context['functionArgs'][1]['method'];
	 	$activity = 'Display';
	 	
	 	if ($context['functionArgs'][1]['executed']) {
	 		$activity = 'Executed';
	 	} elseif ($context['functionArgs'][1]['programmed']) {
	 		$activity = 'Programmed';
	 	} elseif ($context['functionArgs'][1]['rebuild']) {
	 		$activity = 'Rebuilt';
	 	} elseif ($context['functionArgs'][1]['rebuild']) {
	 		$activity = 'Rebuilt';
	 	}
	 	
	 	$storage['RetrievedForms'][] = array(
	 			'form Name' => $formName, 'form Id' => $formId, 'base Form Id' => $baseFormId,
	 			'cache' => $cache, 'method' => $method, 'activity' => $activity
	 	);
	}
	
	private function is_closure($t) {
		return is_object($t) && ($t instanceof Closure);
	}
}
