<?php
namespace App\Services\QrCode\Parse\DriverParserAdapter;

trait JsonTrait {

	protected $jsonData;

	public function parseJson($content) {
		$this->jsonData = json_decode($content, true);
		if (is_null($this->jsonData)) {
            throw new \Exception('JSON parse error: ' . json_last_error_msg() . PHP_EOL . 'JSON: ' . $content);
		}
	}

	public function getJsonData($path, $arr = null, $default = null) {
		if (is_null($arr)) {
			$arr = $this->jsonData;
		}
		foreach (explode('.', $path) as $key) {
			if (!isset($arr[$key])) {
				return $default;
			}
			$arr = $arr[$key];
		}
		return $arr;
	}

	public function getJsonDataArray($path, $valuePath, $keyPath = null, $arr = null) {
		$result = [];
		$array = $this->getJsonData($path, $arr);
		if (!is_array($array)) {
			return $result;
		}
		foreach ($array as $key => $data) {
			$value = $this->getJsonData($valuePath, $data);
			if (is_null($keyPath)) {
				$result[] = $value;
			}
			else {
				$key = $this->getJsonData($keyPath, $data);
				if (is_null($key)) {
					continue;
				}
				$result[$key] = $value;
			}
		}
		return $result;
	}
}
