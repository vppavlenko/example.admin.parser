<?php

namespace WS\Parser;

use Bitrix\Main\Loader;
use CIBlock;
use CIBlockElement;
use WS\Base;

class Reference extends Base {

    const COLOR_IBLOCK_CODE = 'ref_color';
    const WHOM_IBLOCK_CODE = 'ref_whom';
    const REASON_IBLOCK_CODE = 'ref_reason';
    const TAGS_IBLOCK_CODE = 'tags';
    /**
     * @var false|mixed
     */
    private $ibColorId;
    /**
     * @var false|mixed
     */
    private $ibWhomId;
    /**
     * @var false|mixed
     */
    private $ibReasonId;
    /**
     * @var false|mixed
     */
    private $ibTagsId;


    public function __construct() {
        Loader::includeModule('iblock');
        $this->ibColorId = $this->getIblockColorId();
        $this->ibWhomId = $this->getIblockWhomId();
        $this->ibReasonId = $this->getIblockReasonId();
        $this->ibTagsId = $this->getIblockTagsId();
    }

    private function getIblockColorId() {
        return $this->getIblockIdByCode(self::COLOR_IBLOCK_CODE);
    }

    private function getIblockWhomId() {
        return $this->getIblockIdByCode(self::WHOM_IBLOCK_CODE);
    }

    private function getIblockReasonId() {
        return $this->getIblockIdByCode(self::REASON_IBLOCK_CODE);
    }

    private function getIblockTagsId() {
        return $this->getIblockIdByCode(self::TAGS_IBLOCK_CODE);
    }

    private function getIblockIdByCode($code) {
        $res = CIBlock::GetList(
            [],
            [
                'TYPE' => 'catalog',
                "=CODE" => $code
            ], false
        );
        if ($arRes = $res->Fetch()) {
            return $arRes['ID'];
        }
        return false;
    }

    public function getColorId($name) {
        return $this->getElementIdByName($name, $this->ibColorId);
    }

    public function getWhomId($name) {
        return $this->getElementIdByName($name, $this->ibWhomId);
    }

    public function getReasonId($name) {
        return $this->getElementIdByName($name, $this->ibReasonId);
    }

    public function getTagId($name) {
        return $this->getElementIdByName($name, $this->ibTagsId);
    }

    private function getElementIdByName($name, $iblockId) {
        $res = CIBlockElement::GetList([], ['IBLOCK_ID' => $iblockId, '=NAME' => $name], false, false, ['ID']);
        if ($arFields = $res->Fetch()) {
            return $arFields['ID'];
        }
        return false;
    }

    public function addColor($name) {
        return $this->addElement($name, $this->ibColorId);
    }

    public function addWhom($name) {
        return $this->addElement($name, $this->ibWhomId);
    }

    public function addReason($name) {
        return $this->addElement($name, $this->ibReasonId);
    }

    public function addTag($name) {
        return $this->addElement($name, $this->ibTagsId);
    }

    private function addElement($name, $iblockId) {
        $el = new CIBlockElement;

        $fields = [
            'IBLOCK_ID' => $iblockId,
            'ACTIVE' => 'Y',
            'NAME' => $name,
        ];

        if ($id = $el->Add($fields)) {
            return $id;
        }
        return false;
    }
}