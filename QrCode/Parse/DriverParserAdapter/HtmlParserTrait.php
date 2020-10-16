<?php

namespace App\Services\QrCode\Parse\DriverParserAdapter;

trait HtmlParserTrait {

    /** @var \phpQueryObject */
    protected $pq;

    /** @var string */
    protected $content;

    public function initParser() {
        $this->pq = \phpQuery::newDocument($this->content);
    }

    public function clearData() {
        parent::clearData();
        $this->pq = null;
    }

}