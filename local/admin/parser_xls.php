<?php

use Bitrix\Main\Application;
use Bitrix\Main\Loader;
use WS\DataBase\PriceListTable;
use WS\Parser\BackgroundProcess;
use WS\Parser\Parser;
use WS\Parser\Progress;
use WS\Tools\Module;

require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_before.php");

global $APPLICATION, $USER;
$APPLICATION->SetTitle('Парсер');

if (!$USER->IsAdmin()) {
    $APPLICATION->ShowAuthForm('Access denied');
}

Loader::includeModule('iblock');

$step = 1;
if (isset($_GET['step']) && (int)$_GET['step'] > 0) {
    $step = $_GET['step'];
}

$uploadDir = $_SERVER["DOCUMENT_ROOT"] . "/upload/import_xls/file/";
$uploadFile = $uploadDir . 'import.xlsx';

$request = Application::getInstance()->getContext()->getRequest();

/** @var Progress $progress*/
$progress = Module::getInstance()->getService('parserProgress');

if (!$request->isAjaxRequest()) {

    $progress->clear();
}

if ($request->isAjaxRequest()) {

    $action = $request->get('action');

    if ($action && ($action == 'run')) {
        $progress->clear();
        sleep(1);

        $file = $request->getFile('xlsfile');

        $prevCheckFile = true;
        $checkProgress = false;
        $mess = '';
        if (empty($file['tmp_name'])) {
            $prevCheckFile = false;
            $mess = 'Ошибка проверки файла: Файл не загружен.';
        }

        $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
        if (!$reader->canRead($file['tmp_name'])) {
            $prevCheckFile = false;
            $mess = 'Ошибка проверки файла: Файл не может быть прочитан.';
        }

        if ($file['size'] > 200000) {
            $prevCheckFile = false;
            $mess = 'Ошибка проверки файла: Файл не должен превышать 200Кб.';
        }

        $backgroundProcess = new BackgroundProcess();
        if ($backgroundProcess->isRunning()) {
            $checkProgress = true;
            $mess = 'Ошибка запуска процесса. Процесс уже запущен, дождитесь завершения.';
        }

        if (!$checkProgress) {
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, BX_DIR_PERMISSIONS, true);
            }
            if (file_exists($uploadFile)) {
                unlink($uploadFile);
            }
            if (!move_uploaded_file($file['tmp_name'], $uploadFile)) {
                $prevCheckFile = false;
                $mess = 'Ошибка проверки файла: Файл не может быть загружен на сервер.';
            }
        }

        if ($prevCheckFile && !$checkProgress) {
            $result = $backgroundProcess->run();
        } else {
            $result['result'] = 'error';
            $result['message'] = $mess;
        }
    }

    if ($action && ($action == 'progress')) {
        $result['success'] = true;
        /** @var \WS\Parser\Progress $progress */
        $progress = Module::getInstance()->getService('parserProgress');
        $result = $progress->getInfo();

        $importProgress = new CAdminMessage(array(
            'TYPE' => 'PROGRESS',
            'MESSAGE' => 'Обработано строк',
            'DETAILS' => '#PROGRESS_BAR#',
            'PROGRESS_TOTAL' => $result['recordsCount'],
            'PROGRESS_VALUE' => $result['recordsRun'],
            'PROGRESS_TEMPLATE' => "#PROGRESS_VALUE# из #PROGRESS_TOTAL# (#PROGRESS_PERCENT#)",
        ));
        $importProgressMessage = $importProgress->Show();
        $result['progressBar'] = $importProgressMessage;

    }

    $APPLICATION->RestartBuffer();
    echo json_encode(array(
        'result' => $result
    ));
    die();
}

$res = CIBlock::GetList(
    [],
    [
        'TYPE' => 'catalog',
        "=CODE" => 'catalog'
    ], false
);
if ($arRes = $res->Fetch()) {
    $iblockId = $arRes['ID'];
}

require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_after.php");

$tabs = array(
    array(
        "DIV" => "edit1",
        "TAB" => "Парсер",
        "ICON" => "iblock",
        "TITLE" => 'Импорт данных',
    ),
    array(
        "DIV" => "edit2",
        "TAB" => "Результат",
        "ICON" => "iblock",
        "TITLE" => 'Добавленные элементы',
    ),
);
?>
<?=BeginNote();?>
    В данном блоке выводится информация о ходе процесса импорта данных. <br>
    Для начала импорта загрузите файл в формате xlsx и воспользуетесь кнопкой - <strong>"Начать"</strong>.<br>
    После начала дождитесь обновления информации о ходе импорта (процесс может занять некоторое время).<br>
    <strong>
        Внимание! В файле все обязательные поля должны быть заполнены. Ниже список обязательных полей:
    </strong><br>
    Наименование, Цена, SEO URL, Категория, Подкатегория
<?=EndNote();?>

    <div id="parser-error-message" class="adm-info-message-wrap adm-info-message-red">
        <div class="adm-info-message">
            <div class="adm-info-message-title"></div>
            <div class="adm-info-message-icon"></div>
        </div>
    </div>

    <div id="parser-success-message" class="adm-info-message-wrap adm-info-message-green">
        <div class="adm-info-message">
            <div class="adm-info-message-title"></div>
            <div class="adm-info-message-icon"></div>
        </div>
    </div>

<?php
$tabControl = new CAdminTabControl("tabControl", $tabs, false, true);
$tabControl->Begin();

$tabControl->BeginNextTab();
?>
    <tr class="heading">
        <td colspan="2">Загрузка файла:</td>
    </tr>
    <tr>
        <td colspan="2">
            <form id="xlsParserForm" enctype="multipart/form-data" action="<? echo $APPLICATION->GetCurPage(); ?>" method="POST">
                <input type="hidden" name="MAX_FILE_SIZE" value="100000"/>
                Файл для загрузки (*.xlsx): <input name="xlsfile" type="file"/>
            </form>
        </td>
    </tr>
    <tr class="heading">
        <td colspan="2">Прогресс:</td>
    </tr>
    <tr>
        <td id="resblock" class="parser-result-wrap">
            <?
            $importProgress = new CAdminMessage(array(
                'TYPE' => 'PROGRESS',
                'MESSAGE' => 'Обработано строк',
                'DETAILS' => '#PROGRESS_BAR#',
                'PROGRESS_TOTAL' => 0,
                'PROGRESS_VALUE' => 0,
                'PROGRESS_TEMPLATE' => "#PROGRESS_VALUE# из #PROGRESS_TOTAL# (#PROGRESS_PERCENT#)",
            ));
            $importProgressMessage = $importProgress->Show();
            ?>
            <table width="100%">
                <tr>

                    <td>
                        <div class="detail_status" id="parser_result_block">
                            <?echo CAdminMessage::ShowMessage(array(
                                "TYPE" => "PROGRESS",
                                "MESSAGE" => 'Процесс:',
                                "DETAILS" =>
                                    '<div id="step-start" class="adm-info-message-title title-step">1. Начало работы</div>'
                                    .'<div id="step-checkFile" class="adm-info-message-title title-step">2. Проверка файла</div>'
                                    .'<div id="step-import" class="adm-info-message-title title-step">3. Импорт</div>'
                                    .'<div id="step-deactivateElements" class="adm-info-message-title title-step">4. Деактивация элементов</div>'
                                    .'<div id="step-deactivateSections" class="adm-info-message-title title-step">5. Деактивация разделов</div>'
                                    .'<div id="step-stop" class="adm-info-message-title title-step">6. Завершение работы</div>',
                                "HTML" => true,
                            ))?>
                        </div>
                    </td>

                    <td>
                        <div id="progress-bar">
                        <?=$importProgressMessage;?>
                        </div>
                    </td>

                    <td>
                        <div class="detail_status" id="parser_result_block">
                            <?echo CAdminMessage::ShowMessage(array(
                                    "TYPE" => "PROGRESS",
                                    "MESSAGE" => 'Результаты:',
                                    "DETAILS" =>
                                        '<div class="parser-result-wrap-block">'
                                        .'<span>Всего обработано строк: <b class="run_value" id="total_line">0</b></span>'
                                        .'<span>Из них полностью корректных: <b class="run_value" id="correct_line">0</b></span>'
                                        .'<span>С ошибками: <b class="run_value" id="error_line">0</b></span>'
                                        .'</div>'
                                        .'<div class="parser-result-wrap-block">'
                                        .'<span class="parser-result-wrap-item-green">Добавлено разделов: <b class="run_value" id="section_added_line">0</b></span>'
                                        .'</div>'
                                        .'<div class="parser-result-wrap-block">'
                                        .'<span class="parser-result-wrap-item-green">Добавлено элементов: <b class="run_value" id="element_added_line">0</b></span>'
                                        .'<span>Обновлено элементов: <b class="run_value" id="element_updated_line">0</b></span>'
                                        .'</div>'
                                        .'<div class="parser-result-wrap-block">'
                                        .'<span class="parser-result-wrap-item-green">Деактивировано элементов: <b class="run_value" id="element_deactivate_line">0</b></span>'
                                        .'<span>Деактивировано разделов: <b class="run_value" id="sections_deactivate_line">0</b></span>'
                                        .'</div>'
                                        .'<b><a href="'.$APPLICATION->GetCurPage().'?step=2" id="parser_result_link" style="display: none">Посмотреть результат</a></b>',
                                    "HTML" => true,
                                ))?>
                        </div>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
<?php
$tabControl->EndTab();
$tabControl->BeginNextTab();
?>
<?php
$items = [];
$connection = Application::getConnection();
if ($connection->isTableExists(PriceListTable::getTableName())) {
    $parameters = [
        'select' => ['*']
    ];
    $rsData = PriceListTable::getList($parameters);
    while ($arRes = $rsData->fetch()) {
        $items[] = $arRes;
    }
}

?>
<?if (count($items) > 0):?>
    <tr style="padding: 10px;">
        <td colspan="2" style="text-align: left; padding-left: 10px;">
            <table class="adm-list-table" width="100%">
                <thead>
                <tr class="adm-list-table-header">
                    <td class="adm-list-table-cell">
                        <div class="adm-list-table-cell-inner">Название</div>
                    </td>
                    <td class="adm-list-table-cell">
                        <div class="adm-list-table-cell-inner">Ссылка на сайте</div>
                    </td>
                    <td class="adm-list-table-cell">
                        <div class="adm-list-table-cell-inner">Ссылка в админке</div>
                    </td>
                </tr>
                </thead>
                <tbody>
                <?php foreach($items as $item) : ?>
                <?
                    $adminLink = '/bitrix/admin/iblock_element_edit.php?IBLOCK_ID='.$iblockId.'&type=catalog&lang=ru&ID='.$item['ELEMENT_ID'].'&find_section_section='.$item['SECTION_ID'];
                    ?>
                    <tr class="adm-list-table-row">
                        <td class="adm-list-table-cell"><?= $item['NAME'] ?></td>
                        <td class="adm-list-table-cell">
                            <a target="_blank" href="<?= $item['DETAIL_PAGE_URL'] ?>"><?= $item['NAME'] ?></a>
                        </td>
                        <td class="adm-list-table-cell">
                            <a target="_blank" href="<?= $adminLink ?>"><?= $item['NAME'] ?></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </td>
    </tr>
<?else:?>
    <div>Новые элементы не добавлены</div>
<?endif?>
<?php
$tabControl->EndTab();
$tabControl->Buttons();
?>
    <input type="hidden" name="STEP" value="1">
    <input type="submit" id="beginButton" name="beginButton" value="Начать" disabled class="adm-btn-save">
<?php
$tabControl->End();

CJSCore::Init('jquery');
?>

<style type="text/css">
    #parser-error-message, #parser-success-message {display: none;};
    /*.parser-result-wrap .detail_status {float: right;}*/
    #resblock.parser-result-wrap .detail_status .adm-info-message {display: block;}
    .parser-result-wrap td {vertical-align: top;}
    .bx-core-adm-dialog div.esol-ix-settings-margin {margin: 0px 0px 10px 0px; text-align: center; position: relative;}
    .adm-info-message .parser-result-wrap-block{display: block;}
    .parser-result-wrap-block {padding: 7px 0px;}
    .parser-result-wrap-block:empty {padding: 0px;}
    .parser-result-wrap-block span {display: block;}
    .parser-result-wrap-block span.parser-result-wrap-item-green.parser-result-wrap-item-full {color: #00c703;}
    .parser-result-wrap-block span.parser-result-wrap-item-red.parser-result-wrap-item-full {color: #ff0000;}
    #bx-admin-prefix .adm-info-message-gray .title-step {
        font-size: 18px;
        margin-bottom: 16px;
    }
    #bx-admin-prefix .adm-info-message-gray .title-step.active {
        font-weight: bold;
        color: green;
    }
</style>

<script>
    <?if ($step == 1):?>
        tabControl.SelectTab("edit1");
        tabControl.DisableTab("edit2");
    <?endif;?>
    <?if ($step == 2):?>
        tabControl.SelectTab("edit2");
        tabControl.ShowTab("edit2");
    <?endif;?>
</script>

<script>
    (function () {

        var inputFile = $('input[name=xlsfile]');
        var beginButton = $('input[name=beginButton]');
        var resultLink = $('#parser_result_link');

        function showError(message) {
            var errorBox = $('#parser-error-message'),
                errorTitle = errorBox.find('.adm-info-message-title');
            errorTitle.text(message);
            errorBox.show();
        }

        function hideError() {
            var errorBox = $('#parser-error-message');
            errorBox.hide();
        }

        function showSuccess(message) {
            var successBox = $('#parser-success-message'),
                successTitle = successBox.find('.adm-info-message-title');
            successTitle.text(message);
            successBox.show();
        }

        function hideSuccess() {
            var successBox = $('#parser-success-message');
            successBox.hide();
        }

        function clearProgress() {
            var bar = $('body').find('.adm-progress-bar-outer');
            resultLink.hide();
            $('.title-step').removeClass('active');
            $('.run_value').text('0');
            bar.html('<div class="adm-progress-bar-inner" style="width: 0;"><div class="adm-progress-bar-inner-text" style="width: 500px;">0 из 0 (0%)</div></div>0 из 0 (0%)');
        }

        function refreshProgress(waitime){
            var handle = setInterval(function () {
                var form = $('#xlsParserForm');

                $.ajax({
                    'url' : form.attr('action'),
                    'data' : {
                        action: 'progress'
                    },
                    'type': 'POST',
                    'success' : function (response) {
                        var result = JSON.parse(response);
                        var progress = result.result;

                        if (progress) {
                            if (progress.status === 'start') {
                                hideError();
                                hideSuccess();
                            }
                            if ((progress.status === 'stop') && (progress.resultCheckFile === 'fail')) {
                                showError('Ошибка проверки файла. Файл не соответствует формату.');
                                clearInterval(handle);
                            }
                            if (progress.status === 'fatal') {
                                showError('Ошибка работы парсера. Подробнее в файле лога.');
                                clearInterval(handle);
                            }

                            $('#step-' + progress.status).addClass('active');
                            $('#total_line').text(progress.recordsRun);
                            $('#correct_line').text(progress.recordsRunCorrect);
                            $('#error_line').text(progress.recordsRunFail);
                            $('#section_added_line').text(progress.sectionsAdd);
                            $('#element_added_line').text(progress.elementsAdd);
                            $('#element_updated_line').text(progress.elementsUpdate);
                            $('#element_deactivate_line').text(progress.elementsDeactivate);
                            $('#sections_deactivate_line').text(progress.sectionsDeactivate);
                            $('#progress-bar').html(progress.progressBar);

                            if ((progress.status === 'stop') && (progress.resultCheckFile === 'success')) {
                                clearInterval(handle);
                                resultLink.show();
                            }
                        }
                    }
                });

            }, waitime);
            return handle;
        }

        inputFile.change(function(){
            beginButton.removeAttr('disabled');
            hideError();
            hideSuccess();
            clearProgress();
        });

        beginButton.on('click', function (e) {
            e.preventDefault();
            var file = inputFile[0].files[0];
            beginButton.prop("disabled", true);
            tabControl.DisableTab("edit2");

            BX.showWait('edit1');
            var data = new FormData();
            data.append('xlsfile', file);
            data.append('action', 'run');

            var form = $('#xlsParserForm');

            $.ajax({
                'url' : form.attr('action'),
                'data' : data,
                'cache': false,
                'dataType': 'json',
                'processData': false,
                'contentType': false,
                'type': 'POST',
                'success' : function (response) {
                    BX.closeWait('edit1');
                    var result = response.result;
                    if (result.result === 'success') {
                        showSuccess(result.message);
                        var handle = refreshProgress(500);
                    }
                    if (result.result === 'error') {
                        showError(result.message);
                    }
                }
            });

        });
    })();
</script>

<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_admin.php");