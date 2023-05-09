<?php
require_once 'Autoloader.php';
Autoloader::register();
new Api();

class Api
{
	private static $db;

	public static function getDb()
	{
		return self::$db;
	}

	public function __construct()
	{
		self::$db = (new Database())->init();
    
		$uri = strtolower(trim((string)$_SERVER['PATH_INFO'], '/')); // updated
		
		$httpVerb = isset($_SERVER['REQUEST_METHOD']) ? strtolower($_SERVER['REQUEST_METHOD']) : 'cli';
		

		$wildcards = [
			':any' => '[^/]+',
			':num' => '[0-9]+',
			':delete' => 'delete/(\d+)',
			':update' => 'update/(\d+)'
		];
		$routes = [
			'get constructionStages' => [
				'class' => 'ConstructionStages',
				'method' => 'getAll',
			],
			'get constructionStages/(:num)' => [
				'class' => 'ConstructionStages',
				'method' => 'getSingle',
			],

			'delete constructionStages/:delete' => [
				'class' => 'ConstructionStages',
				'method' => 'delete',
			],

			'patch constructionStages/:update' => [
				'class' => 'ConstructionStages',
				'method' => 'update',
			],

			
			'post constructionStages' => [
				'class' => 'ConstructionStages',
				'method' => 'post',
				'bodyType' => 'ConstructionStagesCreate'
			],
		];

		$response = [
			'error' => 'No such route',
		];

		if ($uri) {
			foreach ($routes as $pattern => $target) {
				$pattern = str_replace(array_keys($wildcards), array_values($wildcards), $pattern);
				if (preg_match('#^'.$pattern.'$#i', "{$httpVerb} {$uri}", $matches)) {
					$params = [];
					array_shift($matches);
					if ($httpVerb === 'post') {
						$data = json_decode(file_get_contents('php://input'));
						$params = [new $target['bodyType']($data)];
						$params[0]->method = 'post';
						
					}
					if($httpVerb == 'patch' || $httpVerb =="delete"){
							$data = json_decode(file_get_contents('php://input'));
							if(empty($data) || is_null($data)){
								die('data is null or error');
							}
							else {
								$data->method = $httpVerb;
								$data->id = $matches[0];
								$params[] = $data;
							}
						
					}
					$params = array_merge($params, $matches);
					$response = call_user_func_array([new $target['class'], $target['method']], $params);
					
					
				}
			}

			echo json_encode($response, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
		}
	}
}