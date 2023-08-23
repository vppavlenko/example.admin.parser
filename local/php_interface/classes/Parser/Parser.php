<?php

namespace WS\Parser;

use Bitrix\Main\Application;
use Bitrix\Main\ArgumentException;
use Bitrix\Main\DB\SqlQueryException;
use Bitrix\Main\SystemException;
use Bitrix\Main\Type\DateTime;
use CAgent;
use WS\DataBase\PriceListTable;
use WS\Tools\Module;

class Parser {

    const LINE_START = 2;
    const STEP_LINES = 5;

    /**@var Logger $logger */
    private $logger;
    /** @var Progress */
    private $progress;

    private $filePath;
    private $fileError;
    private $spreadsheet;

    private $elements;
    private $sections;
    private $subSections;

    private $countRecords;
    private $stepRecord;

    /** @var string[] */
    private array $doubleName = [];
    /** @var string[] */
    private array $doubleSeoUrl = [];
    /** @var BackgroundProcess */
    private $backgroundProcess;

    public function __construct() {
        $this->filePath = $_SERVER['DOCUMENT_ROOT'] . "/upload/import_xls/file/import.xlsx";
        $this->spreadsheet = $this->initSpreadSheet();
        $this->logger = Logger::getInstance();
        $this->progress = Module::getInstance()->getService('parserProgress');
        $this->backgroundProcess = $this->getBackgroundProcess();
    }

    public function run() {
        $result['result'] = 'success';
        $this->logger->logStart();
        $this->progressInit();
        $this->logger->logCheckFile();

        try {
            if (!$this->checkFile()) {
                $this->progressStop();
                return;
            }
            $this->stepInit();
            $this->logger->logClearTable();
            $this->clearTable();
            $this->logger->logCreateTable();
            $this->createTable();
            $this->logger->logStartImport();
            $this->process();
            $this->logger->logStopImport();
            $this->logger->logStartDeactivate();
            $this->deactivateElements();
            $this->deactivateSections();
            $this->logger->logStopDeactivate();
            $this->logger->logStop();
            $this->progressStop();
            $this->updateDiscount();
        } catch (\Exception $exception) {
            $ex = $exception->getMessage();
            $this->logger->logException($ex);
            $this->progress->status = $this->progress::STATUS_FATAL;
            $this->progress->save();
            $this->backgroundProcess->kill();
        }
    }

    private function initSpreadSheet() {
        $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
        return $reader->load($this->filePath);
    }

    private function progressInit() {
        $this->progress->status = $this->progress::STATUS_START;

        $this->progress->resultCheckFile = 0;

        $this->progress->recordsCount = 0;
        $this->progress->recordsRun = 0;
        $this->progress->recordsRunCorrect = 0;
        $this->progress->recordsRunFail = 0;

        $this->progress->sectionsAdd = 0;

        $this->progress->elementsAdd = 0;
        $this->progress->elementsUpdate = 0;

        $this->progress->elementsDeactivate = 0;
        $this->progress->sectionsDeactivate = 0;
        $this->progress->save();
        sleep(1);
    }

    private function progressStop() {
        $this->progress->status = $this->progress::STATUS_STOP;
        $this->progress->save();
    }

    private function checkFile() {
        $this->progress->status = $this->progress::STATUS_CHECK_FILE;
        $this->progress->save();
        sleep(1);
        if (!$this->checkFormatFile()) {
            $this->progress->resultCheckFile = $this->progress::RESULT_CHECK_FILE_FAIL;
            $this->progress->save();
            $this->backgroundProcess->kill();
            return false;
        }
        $this->progress->resultCheckFile = $this->progress::RESULT_CHECK_FILE_SUCCESS;
        $this->progress->save();
        return true;
    }

    private function checkFormatFile() {
        $maxCell = $this->spreadsheet->getActiveSheet()->getHighestRowAndColumn();
        $arRecords = $this->spreadsheet->getActiveSheet()->rangeToArray(Product::LITER_NAME . '1:' . $maxCell['column'] . '1', null, true, true, true);
        if (($arRecords[1][Product::LITER_NAME] == 'Наименование:') && ($arRecords[1][Product::LITER_REASON] == 'Повод')) {
            return true;
        }
        return false;
    }

    private function stepInit() {
        $maxCell = $this->spreadsheet->getActiveSheet()->getHighestRowAndColumn();
        $count = 0;
        $arRecords = $this->spreadsheet->getActiveSheet()->rangeToArray(Product::LITER_NAME . '2:' . $maxCell['column'] . $maxCell['row'], null, true, true, true);
        foreach ($arRecords as $key => $record) {
            if (!$this->emptyRecord($record)) {
                $count++;;
            } else {
                break;
            }
        }
        $this->setCountRecords($count);
        $this->setStepRecord(1);
    }

    private function setCountRecords($count) {
        $this->countRecords = $count;
    }

    private function getCountRecords() {
        return $this->countRecords;
    }

    private function setStepRecord($line) {
        $this->stepRecord = $line;
    }

    private function getStepRecord() {
        return $this->stepRecord;
    }

    private function process() {
        $countRecords = $this->getCountRecords();
        $this->progress->status = $this->progress::STATUS_IMPORT;
        $this->progress->recordsCount = $countRecords;
        $this->progress->save();
        sleep(1);
        $currentLine = $this->getStepRecord();
        $maxCell = $this->spreadsheet->getActiveSheet()->getHighestRowAndColumn();

        while ($currentLine < $countRecords) {
            $startLine = $currentLine + 1;
            $endLine = $currentLine + self::STEP_LINES;
            $arRecords = $this->spreadsheet->getActiveSheet()->rangeToArray(Product::LITER_NAME . $startLine . ':' . $maxCell['column'] . $endLine, null, true, true, true);
            if ($records = $this->prepareRecords($arRecords)) {
                unset($arRecords);
                $this->importStep($records);
                $currentLine = $endLine;
            } else {
                $currentLine = $countRecords;
            }
        }

        unset($this->doubleName);
        unset($this->doubleSeoUrl);
    }

    private function prepareRecords($records) {
        $objects = [];
        if (count($records) < 1) {
            return false;
        }

        foreach ($records as $key => $record) {
            if ($this->emptyRecord($record)) {
                continue;
            }
            $record['line'] = $key;
            $objects[] = new Product($record);
        }
        return $objects;
    }

    private function emptyRecord($record) {
        if (count(array_filter($record)) > 0) {
            return false;
        }
        return true;
    }

    private function importStep($records) {
        foreach ($records as $record) {
            $this->import($record);
        }
    }

    private function import(Product $record) {
        $isCheck = true;
        $this->progress->recordsRun++;

        if (!$this->checkEmptyFields($record)) {
            $this->logger->logError($record->getLine(), 'Не заполнено одно или несколько обязательных полей');
            $isCheck = false;
        }
        if (!$this->checkDoubleFields($record)) {
            $this->logger->logError($record->getLine(), 'Повторяющиеся обязательные поля');
            $isCheck = false;
        }
        if (!$isCheck) {
            $this->progress->recordsRunFail++;
            $this->progress->save();
            return;
        }

        $this->saveProcessedRecord($record);

        $importer = new Importer();
        if ($importer->run($record)) {
            $this->progress->recordsRunCorrect++;
        } else {
            $this->progress->recordsRunFail++;
        }

        $this->progress->save();
    }

    private function checkEmptyFields(Product $product) {
        if (empty($product->getName())) {
            return false;
        }
        if (empty($product->getBasePrice())) {
            return false;
        }
        if (empty($product->getSeoUrl())) {
            return false;
        }
        if (empty($product->getSection())) {
            return false;
        }
        if (empty($product->getSubSection())) {
            return false;
        }
        return true;
    }

    private function checkDoubleFields(Product $product) {
        if (in_array($product->getName(), $this->doubleName)) {
            return false;
        } else {
            $this->doubleName[] = $product->getName();
        }

        if (in_array($product->getSeoUrl(), $this->doubleSeoUrl)) {
            return false;
        } else {
            $this->doubleSeoUrl[] = $product->getSeoUrl();
        }
        return true;
    }

    private function saveProcessedRecord(Product $product) {
        if (!in_array($product->getSeoUrl(), $this->elements)) {
            $this->elements[] = $product->getSeoUrl();
        }
        if (!in_array($product->getSection(), $this->sections)) {
            $this->sections[] = $product->getSection();
        }
        if (!in_array($product->getSubSection(), $this->subSections)) {
            $this->subSections[] = $product->getSubSection();
        }
    }

    private function clearTable() {
        $connection = Application::getConnection();
        if ($connection->isTableExists(PriceListTable::getTableName())) {
            try {
                Application::getConnection()->dropTable(PriceListTable::getTableName());
            } catch (SqlQueryException $e) {
                return false;
            }
        }
        return true;
    }

    private function createTable() {
        try {
            PriceListTable::getEntity()->createDbTable();
        } catch (ArgumentException $e) {
            return false;
        } catch (SystemException $e) {
            return false;
        }
        return true;
    }

    private function deactivateElements() {
        $this->progress->status = $this->progress::STATUS_DEACTIVATE_ELEMENTS;
        $this->progress->save();
        sleep(1);

        $deactivator = new Deactivator();
        $deactivator->offElements($this->elements);
    }

    private function deactivateSections() {
        $this->progress->status = $this->progress::STATUS_DEACTIVATE_SECTIONS;
        $this->progress->save();
        sleep(1);

        $deactivator = new Deactivator();
        $deactivator->onFullSections();
        $deactivator->offSubSections($this->subSections);
        $deactivator->offSections($this->sections);
        $deactivator->offEmptySections();
    }

    private function getBackgroundProcess() {
        return new BackgroundProcess();
    }

    private function updateDiscount() {
        $time = new DateTime();
        CAgent::AddAgent(
            "\WS\Agents\RecalculateOptimalPriceSimple::run();",
            "",
            "N",
            0,
            "",
            "Y",
            $time->format('d.m.Y H:i:s')
        );
    }
}