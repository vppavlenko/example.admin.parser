<?php


namespace WS\Parser;


use Bitrix\Main\Type\DateTime;
use CAgent;

class BackgroundProcess {


    private $uploadFile;

    public function __construct() {
        $this->uploadFile = $_SERVER['DOCUMENT_ROOT'] . "/upload/import_xls/file/import.xlsx";
    }

    public function isRunning() {
        $agents = [];
        $res = CAgent::GetList(["ID" => "DESC"], ["=NAME" => "\WS\Agents\ImportGoods::run();"]);
        while ($arRes = $res->Fetch()) {
            $agents[] = $arRes['ID'];
        }
        if (count($agents) > 0) {
            return true;
        }
        return false;
    }

    public function run() {
        if ($this->addAgent()) {
            $result['result'] = 'success';
            $result['message'] = 'Файл загружен успешно. Идет подготовка к запуску процесса. Это может занять 1 мин.';
        } else {
            $result['result'] = 'error';
            $result['message'] = 'Ошибка запуска процесса. Процесс уже запущен, дождитесь завершения.';
        }
        return $result;
    }

    private function addAgent() {
        $time = new DateTime();
        return CAgent::AddAgent(
            "\WS\Agents\ImportGoods::run();",
            "",
            "N",
            0,
            "",
            "Y",
            $time->format('d.m.Y H:i:s')
        );
    }

    public function kill() {
        CAgent::RemoveAgent("\WS\Agents\ImportGoods::run();", "");
    }
}