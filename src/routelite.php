<?php
	
	namespace Router;

	class Routelite
	{
		private static $instance = null;
		private $errors = [];
		private $routes = [];
		private $data = [];
		private $groupMiddlewares = [];
		private $globalMiddlewares = [];
		private $urlFilter = [];

		private $status = 404;
		private $prefix = '';
		private $language = null;

		private $url;

		private function __construct () 
		{
			$this->url = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH),'/');
		}

		public function get (string $route, string $controller) 
		{
			$this->parseRouter($route, $controller, 'GET');
			return $this;
		}

		public function post (string $route, string $controller) 
		{
			$this->parseRouter($route, $controller, 'POST');
			return $this;
		}

		public function middleware (array $middlewares)
		{
			if (array_keys($middlewares) !== range(0, count($middlewares) - 1)) {
		        throw new \InvalidArgumentException(
		            "middleware() parameter must be a sequential array with keys starting from 0 and no gaps."
		        );
			}

		    $this->data['middlewares'] = $middlewares;
			return $this;
		}

		public function middlewareGlobal (array $middlewares)
		{
		    if (array_keys($middlewares) !== range(0, count($middlewares) - 1)) {
		        throw new \InvalidArgumentException(
		            "globalMiddleware() parameter must be a sequential array with keys starting from 0 and no gaps."
		        );
		    }

		    $this->globalMiddlewares = $middlewares;
		}		

		public function middlewareGroup (array $middlewares, \Closure $routes) 
		{
			if (array_keys($middlewares) !== range(0, count($middlewares) - 1)) {
		        throw new \InvalidArgumentException(
		            "group() first parameter must be a sequential array with keys starting from 0 and no gaps."
		        );
			}

			$this->groupMiddlewares['group'] = $middlewares;
			$routes($this);
			$this->groupMiddlewares = [];
		}

		public function params (array $params) 
		{
			if (array_keys($params) !== range(0, count($params) - 1)) {
		        throw new \InvalidArgumentException(
		            "params() first parameter must be a sequential array with keys starting from 0 and no gaps."
		        );
			}

			$this->data['params'] = $params;
			return $this;
		}

		public function setLanguage (array $default) 
		{
			$languages = $default;

			$lang = $this->url;
			$lang = trim(str_replace($this->urlFilter, '', $lang), '/');
			$lang = explode('/', $lang);
			$lang = current($lang);

			if (!in_array($lang, $languages)) {
				array_push($this->errors, "Undefined url language: ({$lang})");
			} else {
				$this->language = $lang.'/';
			}
		}		

		public function prefix (string $name) 
		{
			$this->data['prefix'] = trim($name, '/').'/';
			return $this;
		}

		public function prefixGroup (string $name, \Closure $routes) 
		{
			$this->prefix = trim($name, '/').'/';
			$routes($this);
			$this->prefix = '';
		}

		public function where (string $param, $regex) 
		{
			$this->data['conditions'][$param] = $regex;
			return $this;
		}

		public function remove (string $filter) 
		{
			$this->urlFilter[] = $filter;
		}

		private function parseRouter (string $route, string $controller, string $method) 
		{
			$route = trim($route, '/');
			
			$class = explode('@', $controller);

			$this->data['route']       = $route;
			$this->data['controller']  = $class[0];
			$this->data['action']      = $class[1];
			$this->data['method']      = $method;
			$this->data['params']      = [];
			$this->data['middlewares'] = [];
			$this->data['groupMiddlewares'] = $this->groupMiddlewares['group'] ?? [];
			$this->data['conditions']  = [];
			$this->data['prefix']      = $this->prefix ?? '';
		}

		public function add ()
		{
			$this->routes[] = $this->data;
			$this->data = [];
		}

		public function getErrors () 
		{
			return $this->errors;
		}

		public function render () 
		{
			$currentURL = $this->url;

			if (!empty($this->urlFilter)) 
			{
			    $currentURL = trim(str_replace($this->urlFilter, '', $currentURL), '/');
			}

			foreach ($this->routes as $key => $data) 
			{
				$route = trim($data['prefix'].$data['route'], '/');

				if ($this->language !== null) {
					$route = $this->language.$route;
					$route = trim($route, '/');
				}

				if (!empty($data['params'])) {
					$urlWithNoParams = '';

					if ($currentURL !== $route) 
					{
						$urlWithNoParams = explode('/', $currentURL);
						$urlParams = array_slice($urlWithNoParams, -count($data['params']));
						$urlWithNoParams = array_slice($urlWithNoParams, 0, -count($data['params']));
						$urlWithNoParams = trim(implode('/', $urlWithNoParams), '/');
					}

					if ($route === $urlWithNoParams && $_SERVER['REQUEST_METHOD'] === $data['method']) 
					{
						if (!empty($urlParams[0]) && count($urlParams) === count($data['params'])) 
						{
							$params = array_combine($data['params'], $urlParams);
							$this->paramsConditions($params, $data['conditions']);
							$this->status = 200;
							$this->renderMiddleware($data['middlewares'], $data['groupMiddlewares']);
							$this->call($data['controller'], $data['action'], $params);
							return;
						}
					}
				} else {
					if ($route === $currentURL && $data['method'] === $_SERVER['REQUEST_METHOD']) {
						$this->status = 200;
						$this->renderMiddleware($data['middlewares'], $data['groupMiddlewares']);
						$this->call($data['controller'], $data['action']);
						return;
					}
				}
			}
		}

		private function call (string $controller, string $action, array $params = [])
		{
		    // if (!preg_match('/^[a-zA-Z0-9_]+$/', $controller) || !preg_match('/^[a-zA-Z0-9_]+$/', $action)) {
		    //     throw new \Exception("Invalid Controller or Action Name");
		    // }

			if (substr($action, 0, 2) === '__') {
			    throw new \Exception("Magic methods are not allowed");
			}

			//$controller = '\\App\\Controllers\\'.$controller;

			if (!class_exists($controller)) {
				throw new \Exception("Controller Not Found ({$controller})", 1);
				exit();
			} elseif (!method_exists($controller, $action)) {
				throw new \Exception("Controller Method Not Found ({$action})", 1);
				exit();
			} else {
				$execute = new $controller();
				echo call_user_func_array([$execute, $action], $params ?: []);
			}
		}

		private function renderMiddleware (array $routeMiddlewares, array $groupMiddlewares)
		{
			$middlewares = array_unique(array_merge(
			    $this->globalMiddlewares,
			    $groupMiddlewares,
			    $routeMiddlewares
			));

		    foreach ($middlewares as $middleware)
		    {
		        if (!preg_match('/^[a-zA-Z0-9_\\\\]+$/', $middleware)) {
		            throw new \Exception("Invalid middleware name: {$middleware}");
		        }

		        $class = '\\App\\Middlewares\\' . $middleware;

		        if (!class_exists($class)) {
		            throw new \Exception("Middleware not found: {$class}");
		        }

		        $instance = new $class();

		        if (!method_exists($instance, 'handle')) {
		            throw new \Exception("Middleware {$middleware} must contain handle() method.");
		        }

		        $result = $instance->handle();

		        if ($result === false) {
		            exit;
		        }
		    }
		}

		private function paramsConditions (array $params, array $conditions) 
		{
			foreach ($params as $key => $value) {
				if (isset($conditions[$key])) {
					if (!preg_match($conditions[$key], $params[$key])) {
						array_push($this->errors, "{$key} has invalid value");
						throw new \Exception("{$key} has invalid value", 1);
					}
				}
			}
		}

		public function notFound ($callback) 
		{
			if ($this->status === 404 || !empty($this->errors)) {
				$callback();
			}
		}

		public function getRoutes () 
		{
			return $this->routes;
		}

	    public static function collect ()
	    {
	        if (self::$instance === null) {
	            self::$instance = new self();
	        }
	        return self::$instance;
	    }
	}
?>