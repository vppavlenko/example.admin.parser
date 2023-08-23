<?php

namespace WS\Parser;


class Logger {

    private static $instance;
    private $fileName;
    private $dirName;

    public static function getInstance() {
        if (!self::$instance) {
            self::$instance = new static();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->fileName = "log.txt";
        $this->dirName = $_SERVER["DOCUMENT_ROOT"] . "/upload/import_xls/log/";
    }

    public function save($errorText) {
        $filePath = $this->dirName . $this->fileName;

        if(!file_exists($this->dirName)) {
            mkdir($this->dirName, BX_DIR_PERMISSIONS, true);
        }

        if (file_exists($filePath)) {
            file_put_contents($filePath, $errorText, FILE_APPEND);
        } else {
            file_put_contents($filePath, $errorText);
        }
    }

    public function logStart() {
        $str = '#####' . "\r\n";
        $str .= date('d.m.Y H:i:s') . ': Начало работы парсера' . "\r\n";
        $this->save($str);
    }

    public function logStop() {
        $str = date('d.m.Y H:i:s') . ': Окончание работы парсера' . "\r\n";
        $this->save($str);
    }

    public function logCheckFile() {
        $str = date('d.m.Y H:i:s') . ': Проверка файла' . "\r\n";
        $this->save($str);
    }

    public function logError($line, $message) {
        $str = date('d.m.Y H:i:s') . ': Ошибка импорта!: Строка №' . $line . ': ' . $message . "\r\n";
        $this->save($str);
    }

    public function logStartImport() {
        $str = date('d.m.Y H:i:s') . ': Начало импорта' . "\r\n";
        $this->save($str);
    }

    public function logStopImport() {
        $str = date('d.m.Y H:i:s') . ': Окончание импорта' . "\r\n";
        $this->save($str);
    }

    public function logStartDeactivate() {
        $str = date('d.m.Y H:i:s') . ': Начало деактивации' . "\r\n";
        $this->save($str);
    }

    public function logStopDeactivate() {
        $str = date('d.m.Y H:i:s') . ': Окончание деактивации' . "\r\n";
        $this->save($str);
    }

    public function logClearTable() {
        $str = date('d.m.Y H:i:s') . ': Удаление таблицы результатов' . "\r\n";
        $this->save($str);
    }

    public function logCreateTable() {
        $str = date('d.m.Y H:i:s') . ': Создание таблицы результатов' . "\r\n";
        $this->save($str);
    }

    public function logInsert($id) {
        $str = date('d.m.Y H:i:s') . ': Ошибка записи в таблицу результатов!: ID элемента' . $id . "\r\n";
        $this->save($str);
    }

    public function logDeactivateElement($id) {
        $str = date('d.m.Y H:i:s') . ': Ошибка деактивации элемента!: ID элемента' . $id . "\r\n";
        $this->save($str);
    }

    public function logDeactivateSection($id) {
        $str = date('d.m.Y H:i:s') . ': Ошибка деактивации раздела!: ID раздела' . $id . "\r\n";
        $this->save($str);
    }

    public function logException($message) {
        $str = date('d.m.Y H:i:s') . ': Исключение: ' . $message . "\r\n";
        $this->save($str);
    }
}