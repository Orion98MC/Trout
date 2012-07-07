<?php 

/*

	>>~O°>

	TROUT is a Tiny ROUTer in PHP.
	Use TROUT to route HTTP requests to your PHP controller code.


	Copyright (C) 2012 Thierry Passeron
	
	MIT License

	Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated 
	documentation files (the "Software"), to deal in the Software without restriction, including without limitation 
	the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, 
	and to permit persons to whom the Software is furnished to do so, subject to the following conditions:

	The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.

	THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED 
	TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL 
	THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF 
	CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER 
	DEALINGS IN THE SOFTWARE.

*/


	class Trout {
		protected $_routes = array(
			'*any*' 	=> array(),
			'get' 		=> array(),
			'post' 		=> array(),
			'put' 		=> array(),
			'delete' 	=> array()
		);

		protected $_resource_actions = array(
			"index" 	=> array("get", 	"/?", 				"list"),
			"form" 		=> array("get", 	"/new", 			"form"),
			"show"		=> array("get", 	"/([^/]+)", 		"show"),
			"edit"		=> array("get", 	"/([^/]+)/edit", 	"edit"),
			"update"	=> array("put", 	"/([^/]+)", 		"update"),
			"create"	=> array("post", 	"/?", 				"create"),
			"delete"	=> array("delete", 	"/([^/]+)", 		"delete")
		);

		protected $_flushFn = null;
		protected $_stopSwimming = false;

		public $out_buffer = null; 
		public $method = null;
		public $uri = null;
		public $error = null;
				
		public function __construct() {	
			$this->out_buffer = array();
			$self = $this;
			$this->flush(function () use ($self) { echo implode("", $self->out_buffer); }); /* default flushing function */
		}

		/* set the current rule as the last rule */
		public function lastRule() { $this->_stopSwimming = true; }

		public function flush($fn = null) {
			if ($fn) $this->_flushFn = $fn;
			else return call_user_func($this->_flushFn);
		}

		// Is it the last rule ? By default the first rule to output something is considered the last rule
		protected function isLastRule() { return count($this->out_buffer) > 0; }

		public function any($regexp, $callback, $options = null) 	{ return $this->addRoute('*any*', $regexp, $callback, $options); 	}
		public function get($regexp, $callback, $options = null) 	{ return $this->addRoute('get', $regexp, $callback, $options); 		}
		public function post($regexp, $callback, $options = null) 	{ return $this->addRoute('post', $regexp, $callback, $options); 	}
		public function put($regexp, $callback, $options = null) 	{ return $this->addRoute('put', $regexp, $callback, $options); 		}
		public function delete($regexp, $callback, $options = null) { return $this->addRoute('delete', $regexp, $callback, $options); 	}

		// Declare a RESTful resource at path.
		// You may nest the resource path (i.e. include regexp groups in the path). 
		// Nested params could be processed by providing the options hash a 'nested_params_handler' function which takes 2 arguments: the controller instance and the nested params array.
		// Example: $trout->resource('/apps/([^/]+)/stores', "StoresController", function ($storeCtlr, $params) { $storeCtlr->appId = $params[0]; });
		public function resource($path, $class_name, $options = null) { 
			if (isset($options)) {
				if (is_callable($options)) {
					$options = array('nested_params_handler' => $options); 
				} else if (gettype($options) == "string") $options = array('hint' => $options);
			} else $options = array();
			
			// Cleanup class name for dump output
			$ctrl_name = preg_replace("/controller/i", "", $class_name);

			// Cleanup $path (must not have a trailing slash)
			$path = preg_replace("@/$@", "", $path);

			// Check the nesting of the resource
			if ($nested_level = preg_match_all('/\([^\)]+\)/', $path, $inexisting_var)) $options['nested_level'] = $nested_level;
			
			$implemented_methods = get_class_methods($class_name);
			foreach ($this->_resource_actions as $action => $config) {
				if (in_array($action, $implemented_methods)) {
					$method = $config[0];
					$regexp = $config[1];
					$options['hint'] = $ctrl_name. " " . $config[2];
					if ($nested_level) $options['hint'] .= " (nested: $nested_level)";

					$this->addRoute($method, $path . $regexp, function ($args = null) use ($class_name, $action, $options) {
						$controller = new $class_name;
						if (isset($options['nested_level'])) {
							$nested_params = array_splice($args, 0, $options['nested_level']);
							if (isset($options['nested_params_handler'])) call_user_func($options['nested_params_handler'], $controller, $nested_params);
						}
						if ($args) {
							if (gettype($args) == "array") call_user_func_array(array($controller, $action), $args);
							else call_user_func(array($controller, $action), $args);
						} else call_user_func(array($controller, $action));
					}, $options);
				}
			}
		}

		protected function addRoute($method, $regexp, $callback, $options = null) {
			if (gettype($options) == "string") {
				$options = array("hint" => $options);
			} else if (gettype($options) != "array") {
				$options = null;
			}
			$this->_routes[strtolower($method)][] = array($regexp, $callback, $options);
			return $this;
		}
		

		public function dump() {
			foreach ($this->_routes as $method => $routes) {
				foreach ($routes as $route) {
					$options = $route[2];
					$hint = !is_null($options) && isset($options['hint']) ? $options['hint'] : null;
					if ($hint) {
						printf("%6s %-30s %20s\n", strtoupper($method), $route[0], $hint); 
					} else {
						printf("%6s %s\n", strtoupper($method), $route[0]); 
					}
				}
			}
		}


		public function swim($method = null, $uri = null, $verbose = false) {
			$this->out_buffer = array(); // Reset out_buffer for each swim
			$this->_stopSwimming = false;

			if (is_null($method)) {
				$method = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : null;
				$method = (isset($_POST['_method']) && ($_method = strtoupper($_POST['_method'])) && in_array($_method, array('PUT','DELETE'))) ? $_method : $method;
			}

			if (is_null($uri)) {
				$uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : null;
				
				// Cleanup $uri from the $_SERVER['QUERY_STRING']
				if (isset($_SERVER['QUERY_STRING']) && strlen($_SERVER['QUERY_STRING'])) {
					$pos = strpos($uri, $_SERVER['QUERY_STRING']);
					if ($pos !== false) { $uri = substr($uri, 0, $pos - 1); }
				}
			}

			if ($verbose) { echo "SWIM: ". $method . " " . $uri . "\n"; }

			if ($method && $uri) {
				$finished = false;
				$this->method = $method;
				$this->uri = $uri;

				foreach (array_merge($this->_routes['*any*'], $this->_routes[strtolower($method)]) as $route) {
					
					$matches = null;
					if (preg_match('@^'. $route[0]. '$@i', $uri, $matches)) {

						$options = $route[2];
						$hint = !is_null($options) && isset($options['hint']) ? $options['hint'] : "No hint";

						if ($matches) array_shift($matches); // Remove the first element

						if ($verbose) echo "MATCHED ". $route[0]. "   (". $hint. ") [#params: ". count($matches)."]\n";
						
						ob_start();
							if (count($matches)) {
								if (isset($options['nested_level'])) call_user_func($route[1], $matches); // call it with a array of matches
								else call_user_func_array($route[1], $matches);
							}
							else call_user_func($route[1]);
							
							$out = ob_get_contents();
							if (strlen($out)) array_push($this->out_buffer, $out);
						ob_end_clean();

						if ($this->_stopSwimming || $this->isLastRule()) {
							$finished = true;
							break;
						}

					}

				}

				if ($finished) {
					if ($verbose) echo "FINISHED ROUTING\n";
					if (count($this->out_buffer) > 0) $this->flush();
					$this->out_buffer = array(); // Cleanup the out buffer to release memory
				} else {
					if ($verbose) echo "WARNING: No final rule\n";
					$this->error = array('message' => 'No final rule', 'code' => 'no_final_rule');
				}
				return $finished;

			} else {
				if ($verbose) echo "WARNING: Nothing to route\n";
				$this->error = array('message' => 'Nothing to route', 'code' => 'nothing_to_route');
				return false;
			}			
		}
	}

?>