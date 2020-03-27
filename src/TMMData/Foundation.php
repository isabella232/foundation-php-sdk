<?php namespace TMMData;
/**
 * @author Wesley Boyd <wes.boyd@tmmdata.com>
 * @package TMMData Foundation SDK
 * @version 1.2.3
 *
 * Package to interface with TMMData's V3 API
 */
class Foundation {
		/**
	 * auth
	 *
	 * @var assoc array
	 */
	protected $_auth         = [];

	/**
	 * host
	 *
	 * @var string
	 */
	protected $_host         = 'my.tmmlog.in';

	/**
	 * api
	 *
	 * @var string
	 */
	protected $_api          = 'https://my.tmmlog.in/api/3/';

	/**
	 * resources
	 *
	 * @var assoc array
	 */
	protected $_resources    = [];

	/**
	 * message
	 *
	 * @var string
	 */
	protected $_message      = '';

	/**
	 * resource
	 *
	 * @var string
	 */
	protected $_resource     = FALSE;

	/**
	 * id
	 *
	 * @var ing
	 */
	protected $_id           = FALSE;

	/**
	 * transaction
	 *
	 * @var bool
	 */
	protected $_multi_action = FALSE;

	/**
	 * meta
	 *
	 * @var bool
	 */
	protected $_meta = TRUE;

	/**
	 * params
	 *
	 * @var assoc array
	 */
	protected $_params       = [];

	/**
	 * Construct
	 *
	 * @param assoc array @args
	 *  host over ride default host
	 *  apikey valid apikey provided for authentication
	 *
	 * @return void
	 */
	function __construct($args) {
		if (isset($args['host'])) {
			$this->_host = $args['host'];
			unset($args['host']);
		}
		$this->_api = "https://".$this->_host."/api/3/";
		$this->_auth = $args;
		if($this->_resource === FALSE) {
			$this->defineResources();
		}
	}

	function __destruct() {
	}

	/**
	 * Alias for getResource
	 *
	 * @param string $class_name name of the requested Resource
	 * @param string $id identifier of the requested Resource. Optional, defaults to FALSE.
	 *
	 * @return obj. Resource Object on success, FALSE on Error
	 */
	public function getEndpoint($class_name,$id = FALSE) {
		return $this->getResource($class_name,$id);
	}

	/**
	 * Get Resource Object of the requested Resource, build if necessary
	 *
	 * @param string $class_name name of the requested Resource
	 * @param string $id identifier of the requested Resource. Optional, defaults to FALSE.
	 *
	 * @return obj. Resource Object on success
	 */
	public function getResource($class_name,$id = FALSE) {
		if (!class_exists('TMMData\\'.$class_name)) {
			if (empty($this->_resources)) {
				$this->defineResources();
			}
			if (!isset($this->_resources[$class_name])) {
				throw new \Exception($class_name." is not a valid Resource");
			}
			$class = $this->buildResource($class_name);
			eval($class);
		}
		$args = $this->_auth;
		$args['host'] = $this->_host;
		$class_name = '\\TMMData\\'.$class_name;
		$obj = new $class_name($args);
		if ($id) {
			$obj->setResourceID($id);
		}
		return $obj;
	}

	/**
	 * Get the last _message. _message is set on error.
	 *
	 * @return string _message
	 */
	public function getMessage() {
		return $this->_message;
	}


	/**
	 * Get Associated array of Resources. Define list if necessary
	 *
	 * @return array _resources
	 */
	public function getResources() {
		if (empty($this->_resources)) {
			$this->defineResources();
		}
		return $this->_resources;
	}

	/**
	 * Sets the current Resources identifier to $id
	 *
	 * @param string $id the identifier of the current resource
	 *
	 * @return bool
	 */
	public function setResourceID($id) {
		$this->_id = "/".$id;
		return TRUE;
	}

	/**
	 * Sets whether or not to remove the meta data from the API return
	 *
	 * @param bool $meta 
	 *
	 * @return bool
	 */
	public function setMeta($meta) {
		$this->_meta = $meta;
		return TRUE;
	}

	/**
	 * Starts a Multi Action API Call. This causes no endpoints to be called immediately,
	 * instead it builds 1 large api call to be sent upon commitTransaction to allow for
	 * complex tasks
	 *
	 * @return bool
	 */
	public function startMultiAction() {
		$this->_params = $this->_auth;
		$this->_multi_action = 1;
		return TRUE;
	}

	/**
	 * Resets all variables ready for other tasks.
	 * Does not execute built api call.
	 *
	 * @return bool
	 */
	public function rollbackMultiAction() {
		$this->_multi_action = FALSE;
		$this->_params      = [];
		return TRUE;
	}

	/**
	 * Executes built Multi Action api call. Returns output.
	 *
	 * @return array. the output of the built api call
	 */
	public function commitMultiAction() {
		return $this->runCall();
	}

	/**
	 * Retrives Resoruce definitions from API stores for later use.
	 *
	 * @return bool
	 */
	protected function defineResources() {
		$result = $this->sendRequest($this->_api,$this->_auth);
		if (isset($result['data'])) {
			foreach ($result['data'] as $resource) {
				$this->_resources[$resource['type']] = $resource;
			}
			return TRUE;
		} elseif (isset($result['errors'])) {
			throw new \Exception($result['errors']['title'].' '.$result['errors']['detail'],$result['errors']['status']);
		} else {
			throw new \Exception("Encountered an Unknown Error");
		}
	}

	/**
	 * Build api call.
	 * Append if in transaction and return current action count.
	 * If not in transaction, Execute and return output.
	 *
	 * @param string $function name of the action to be called
	 * @param string $args_name Indicates whether the action has one or multimple params
	 * @param array $args the parameters to pass to the action.
	 *
	 * @return array The output of the executed call.
	 * If in Transaction, the current action count.
	 */
	protected function buildCall($function,$args_name = 'arg',$args = []) {
		if ($this->_resource === FALSE) {
			$this->_message = "Not an instantiated resource";
			return FALSE;
		}
		if (!$this->_multi_action) {
			$this->_params = $this->_auth;
		}
		$this->_params['action'.$this->_multi_action] = $function;
		if ($args_name && !empty($args)) {
			if(!is_string($args)) {
				foreach ($args as $param => $value) {
					if ($value instanceof \CURLFile) {
						$this->_params[$param] = $value;
						unset($args[$param]);
					}
				}
				$args = json_encode($args);
			}
			$this->_params[$args_name.$this->_multi_action] = $args;
		}
		if ($this->_multi_action) {
			return $this->_multi_action++;
		} else {
			return $this->runCall($function);
		}
	}

	/**
	 * Executes api call built in "buildCall"
	 *
	 * @param string $function name of the action called, used to assist formatting response.
	 * Optional, defaults to FALSE
	 *
	 * @return array The output of the executed call. FALSE on Error
	 */
	protected function runCall($function = FALSE){
		$result = $this->sendRequest(
			$this->_api.$this->_resource.$this->_id,
			$this->_params
		);
		$this->_multi_action = FALSE;
		$this->_params      = [];
		if(is_string($result) || $this->_meta) {
			return $result;
		}

		if (isset($result['data'])) {
			if ($function) {
				$tmp = $this->_resource.ucfirst($function).'Response';
				if (isset($result['data']['type']) && $result['data']['type'] == $tmp) {
					return $result['data']['attributes']['result'];
				}
			}
			return $result['data'];
		} elseif (isset($result['error'])) {
			$this->_message = $result['error'];
			return FALSE;
		} else {
			return FALSE;
		}
	}

	/**
	 * Build and execute cURL call.
	 *
	 * @param string $url The endpoint to be called
	 * @param array $args Params to send via POST
	 *
	 * @return array json_decode'd return from endpoint. If not json, return raw respons.
	 * FALSE on error.
	 */
	protected function sendRequest($url,$args) {
		$c = curl_init();
		if (!$this->_meta){
			$args['donotincludemeta'] = 1;
		}
		curl_setopt($c, CURLOPT_HTTPHEADER, ['Accept: application/vnd.api+json']);
		curl_setopt($c, CURLOPT_VERBOSE, 0);
		curl_setopt($c, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($c, CURLOPT_URL, $url);
		curl_setopt($c, CURLOPT_POST, TRUE);
		curl_setopt($c, CURLOPT_POSTFIELDS, $args);
		curl_setopt($c, CURLOPT_FOLLOWLOCATION, TRUE);
		$response = curl_exec($c);
		if ($errno = curl_errno($c)) {
			$this->_message = "cURL error ({$errno}):\n ". curl_error($c)."\n";
			return FALSE;
		}
		curl_close($c);
		$json_response = json_decode($response,TRUE);
		return (($json_response === NULL || $json_response === FALSE)?$response:$json_response);
	}

	/**
	 * Build Resource Object
	 *
	 * @param string $class_name name of the requested Resource
	 *
	 * @return string. Class Definition of the requested Resource
	 */
	protected function buildResource($class_name) {
			$resource = $this->_resources[$class_name];
			
			$getters = $resource['meta']['getters'];
			$setters = $resource['meta']['setters'];
			$actions = $resource['meta']['actions'];

			$class = "namespace TMMData;\nclass {$class_name} extends Foundation {\n\tprotected \$_resource =  '"
				."{$class_name}';\n\tprotected \$_id = '';\n";

			$class.= "\n\t public function set(\$val=NULL){\n\t\treturn \$this->buildCall("
				."'set','arg',\$val);\n\t}\n";

			foreach ($getters as $key => $function) {
				$class.= $this->buildGetter($function);
			}

			foreach ($setters as $key => $function) {
				$class.= $this->buildSetter($function);
			}

			foreach ($actions as $function => $details) {
				$class.= $this->buildAction($function,$details);
			}

			$class.= "}";
			return $class;
	}

	/**
	 * Build Resource Object Getter
	 *
	 * @param string $function name of the Getter function to build
	 *
	 * @return string Definition of the requested function
	 */
	protected function buildGetter($function) {
		return "\n\t public function {$function}(){\n\t\treturn \$this->buildCall("
					."'{$function}',FALSE,NULL);\n\t}\n";
	}

	/**
	 * Build Resource Object Setter
	 *
	 * @param string $function name of the Setter function to build
	 *
	 * @return string Definition of the requested function
	 */
	protected function buildSetter($function) {
		return "\n\t public function {$function}(\$val=NULL){\n\t\treturn \$this->buildCall("
					."'{$function}','arg',\$val);\n\t}\n";
	}

	/**
	 * Build Resource Object Action
	 *
	 * @param string $function name of the Action to build
	 *
	 * @return string Definition of the requested function
	 */
	protected function buildAction($function,$details) {
		$action= "\n\t public function {$function}(";
		if (count($details['parameters']) < 1) {
			$action.= "){\n\t\treturn \$this->buildCall("
				."'{$function}',FALSE,NULL);\n";
		} elseif (count($details['parameters']) == 1) {
			$pname = "$".$details['parameters'][0]['name'];
			$action.= "{$pname}=NULL){\n\t\treturn \$this->buildCall("
				."'{$function}','arg',{$pname});\n";
		} elseif (count($details['parameters']) > 1) {
			$pname = [];
			$params = [];
			foreach ($details['parameters'] as $p) {
				$pname[] = "$".$p['name']."=NULL";
				$params[] = "$".$p['name'];
			}
			$pname = implode(',', $pname);
			$params = implode(',', $params);
			$action.= "{$pname}){\n\t\treturn \$this->buildCall("
				."'{$function}','args',[{$params}]);\n";
		}
		$action.="\t}\n";
		return $action;
	}
}
?>