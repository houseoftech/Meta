<?php
namespace Meta\Controller\Component;

use Cake\Controller\Component;
use Cake\Event\Event;
use Cake\ORM\TableRegistry;
use Cake\Core\Configure;

class MetaComponent extends Component
{
	public $controllerName = null;
	public $actionName = null;
	public $passArray = null;
	public $passString = null;

	public function initialize(array $config)
	{
		parent::initialize($config);
		// Local app config file
		$appConfig = (array)Configure::read('Meta');
		
		// Default Plugin config
		Configure::load('Meta.meta_plugin');
		$defaultConf = (array)Configure::read('Meta');
		
		// Merge configs such that Plugin config is default,
		// which can be overwritten by app config,
		// which can be overwritten by loadComponent config
		$config = array_merge($defaultConf, $appConfig, $config);
		
		Configure::write('Meta', $config);
	}
	
	public function beforeRender(Event $event)
	{
		$controller = $event->subject();
		
		if ($controller->name == 'CakeError') {
			return;
		}
		$this->Controller = $controller;
		
		// don't do anything if request is requested (not going to render a page)
		if ($this->Controller->request->is('requested')) {
			return;
		}
		
		$this->controllerName = $this->Controller->request->params['controller'];
		$this->actionName = $this->Controller->request->params['action'];
		$this->passArray = $this->Controller->request->params['pass'];
		
		$data = $this->_lookup();
		$this->Controller->set('metaPluginData', $data);
		$this->Controller->set('defaultTitle', Configure::read('Meta.defaultTitle'));
		$this->Controller->set('defaultDescription', Configure::read('Meta.defaultDescription'));
		$this->Controller->set('defaultKeywords', Configure::read('Meta.defaultKeywords'));
	}
	
    private function _lookup()
	{
		$meta = TableRegistry::get('Meta.Meta');
		$conditions = [];
		$conditions['Meta.controller'] = $this->controllerName;
		$conditions['Meta.action'] = $this->actionName;
		
		// look for deepest level templates first
		$conditions['template'] = 1;
		if (isset($this->passArray) && !empty($this->passArray)) {
			$this->passString = implode('/', $this->passArray);
			$passArray = array_reverse($this->passArray);
			
			foreach($passArray as $passPart) {
				$conditions['Meta.pass'] = str_replace($passPart, '*', $this->passString);
				$data = $meta->find('all', ['conditions' => $conditions])->first();
				if ($data && count($data)) {
					return $data;
				}
			}
		}
		
		// no specific templates found. search for single record
		unset($conditions['template']);
		if (isset($this->passString) && !empty($this->passString)) {
			$conditions['Meta.pass'] = $this->passString;
		}
		$data = $meta->find('all', ['conditions' => $conditions])->first();
		if (count($data)) {
			return $data;
		}
		
		// search for general template
		$conditions['template'] = 1;
		unset($conditions['Meta.pass']);
		$data = $meta->find('all', ['conditions' => $conditions])->first();
		if (count($data)) {
			return $data;
		}
		
		// search for base record without pass
		unset($conditions['template']);
		unset($conditions['Meta.pass']);
		$data = $meta->find('all', ['conditions' => $conditions])->first();
		
		return $data;
    }
}
