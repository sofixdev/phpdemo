<?php
namespace App\Services\QrCode\Parse\DriverParserAdapter;

trait JsonParserTrait {

	use JsonTrait;

	public function clearData() {
		parent::clearData();
		$this->jsonData = null;
	}

	public function initParser() {
		$this->parseJson($this->content);
		$this->data = [];
	}

	protected function prepareSource($source) {
		return html_entity_decode(strip_tags($source));
	}

}