<?php

namespace WS\Parser;

use WS\Base;
use WS\Storage\FileStorage;
use WS\Tools\Module;

/**
 * @property  $status
 * @property  $resultCheckFile
 *
 * @property  $recordsCheck
 * @property  $recordsCheckCorrect
 * @property  $recordsCheckFail
 *
 * @property  $recordsCount
 * @property  $recordsRun
 * @property  $recordsRunCorrect
 * @property  $recordsRunFail
 *
 * @property $sectionsAdd
 *
 * @property $elementsAdd
 * @property $elementsUpdate
 *
 * @property $elementsDeactivate
 * @property $sectionsDeactivate
 */
class Progress extends Base {

    const STATUS_START = 'start';
    const STATUS_CHECK_FILE = 'checkFile';
    const STATUS_IMPORT = 'import';
    const STATUS_DEACTIVATE_ELEMENTS = 'deactivateElements';
    const STATUS_DEACTIVATE_SECTIONS = 'deactivateSections';
    const STATUS_STOP = 'stop';
    const STATUS_FATAL = 'fatal';

    const RESULT_CHECK_FILE_SUCCESS = 'success';
    const RESULT_CHECK_FILE_FAIL = 'fail';

    private $info;
    /** @var  FileStorage */
    private $storage;

    public function __construct() {
        $this->storage = Module::getInstance()->getService('fileStorage');
    }

    public function getInfo() {
        if ($this->info) {
            return $this->info;
        }
        $this->load();
        return $this->info;
    }

    public function setInfo($info) {
        $this->info = $info;
    }

    public function load() {
        $this->info = $this->storage->getData();
    }

    public function save() {
        $this->storage->saveData($this->info);
    }

    public function __get($name) {
        $info = $this->getInfo();
        return $info[$name];
    }

    public function __set($name, $value) {
        $this->info = $this->getInfo();
        $this->info[$name] = $value;
    }

    public function clear() {
        $this->status = '';

        $this->resultCheckFile = 0;

        $this->recordsCount = 0;
        $this->recordsRun = 0;
        $this->recordsRunCorrect = 0;
        $this->recordsRunFail = 0;

        $this->sectionsAdd = 0;

        $this->elementsAdd = 0;
        $this->elementsUpdate = 0;

        $this->elementsDeactivate = 0;
        $this->sectionsDeactivate = 0;

        $this->save();
    }
}