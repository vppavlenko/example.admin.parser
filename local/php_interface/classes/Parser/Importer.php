<?php

namespace WS\Parser;

use Bitrix\Catalog\Model\Price;
use Bitrix\Iblock\InheritedProperty\ElementValues;
use Bitrix\Main\Loader;
use Bitrix\Main\Type\DateTime;
use CIBlock;
use CIBlockElement;
use CIBlockSection;
use CPrice;
use CUtil;
use WS\DataBase\PriceListTable;
use WS\Tools\Module;

class Importer {

    /**@var Logger $logger */
    private $logger;
    /** @var Progress */
    private $progress;
    /** @var Reference */
    private $reference;
    /** @var string[] */
    private array $errors;

    private $catalogId;
    private $rootSectionId;

    public function __construct() {
        Loader::includeModule('iblock');
        Loader::includeModule('catalog');

        $this->logger = Logger::getInstance();
        $this->progress = Module::getInstance()->getService('parserProgress');
        $this->reference = Module::getInstance()->getService('reference');
        $this->errors = $this->getErrorMessages();
        $this->catalogId = $this->getCatalogId();
        $this->rootSectionId = $this->getRootSectionId();
    }

    public function run(Product $product) {

        if (!$sectionId = $this->importSection($product)) {
            return false;
        }
        if (!$elementId = $this->importElement($product, $sectionId)) {
            return false;
        }
        return true;
    }

    private function importElement(Product $product, $sectionId) {

        $references = [
            'colorId' => $this->importColor($product),
            'whomId' => $this->importWhom($product),
            'reasonId' => $this->importReason($product),
            'tagsIds' => $this->importTags($product)
        ];

        if (!$element = $this->getElementByCode($product->getSeoUrl())) {
            if (!$elementId = $this->addElement($product, $sectionId, $references)) {
                $this->logger->logError($product->getLine(), $this->errors['addElement']);
                return false;
            }

            $this->addProduct($elementId, $product);
            $this->progress->elementsAdd++;
            $this->progress->save();
            $this->saveToHistory($elementId);
            return $elementId;
        }

        if (!$this->needUpdate($element, $product, $references)) {
            return $element['ID'];
        }

        if (!$this->updateElement($element, $product, $references)) {
            $this->logger->logError($product->getLine(), $this->errors['updateElement']);
            return false;
        }

        $this->updateProduct($element['ID'], $product);
        $this->updatePrice($element['ID'], $product);

        $this->progress->elementsUpdate++;
        $this->progress->save();
        return $element['ID'];
    }

    private function importSection(Product $product) {
        if (!$parentSectionId = $this->getSectionByName($product->getSection(), 2)) {
            if (!$parentSectionId = $this->addSection($product->getSection(), $this->rootSectionId)) {
                $this->logger->logError($product->getLine(), $this->errors['addSection'] . $product->getSection());
                return false;
            }
            $this->progress->sectionsAdd++;
        }

        if (!$sectionId = $this->getSectionByName($product->getSubSection(), 3)) {
            if (!$sectionId = $this->addSection($product->getSubSection(), $parentSectionId)) {
                $this->logger->logError($product->getLine(), $this->errors['addSubSection'] . $product->getSubSection());
                return false;
            }
            $this->progress->sectionsAdd++;
        }
        $this->progress->save();

        return $sectionId;
    }

    private function getCatalogId() {
        $res = CIBlock::GetList(
            [],
            [
                'TYPE' => 'catalog',
                "=CODE" => 'catalog'
            ], false
        );
        if ($arRes = $res->Fetch()) {
            return $arRes['ID'];
        }
        return false;
    }

    private function getRootSectionId() {
        $filter = ['=CODE' => 'catalog', 'IBLOCK_ID' => $this->catalogId];
        $res = CIBlockSection::GetList([], $filter, false, ['ID']);
        if ($arSect = $res->GetNext()) {
            return $arSect['ID'];
        }
        return false;
    }

    private function getErrorMessages() {
        return [
            'addSection' => 'Ошибка добавления Категории ',
            'addSubSection' => 'Ошибка добавления Подкатегории ',
            'addColor' => 'Ошибка добавления Цвета ',
            'addWhom' => 'Ошибка добавления Кому ',
            'addReason' => 'Ошибка добавления Повод ',
            'addTag' => 'Ошибка добавления Тега ',
            'addElement' => 'Ошибка добавления элемента',
            'updateElement' => 'Ошибка обновления элемента',
        ];
    }

    private function getSectionByName($sectionName, $depthLevel) {
        $filter = ['=NAME' => $sectionName, 'IBLOCK_ID' => $this->catalogId, 'DEPTH_LEVEL' => $depthLevel];
        $res = CIBlockSection::GetList([], $filter, false, ['ID']);
        if ($arSect = $res->GetNext()) {
            return $arSect['ID'];
        }
        return false;
    }

    private function addSection($sectionName, $parentSectionId) {
        $bs = new CIBlockSection;
        $arFields = [
            "ACTIVE" => 'Y',
            "IBLOCK_SECTION_ID" => $parentSectionId,
            "IBLOCK_ID" => $this->catalogId,
            "NAME" => $sectionName,
            "UF_IMPORT_SECTION" => '1',
        ];

        $arFields["CODE"] = CUtil::translit(
            $sectionName,
            "ru",
            $this->getParamsTranslit()
        );

        if ($id = $bs->Add($arFields)) {
            return $id;
        }
        return false;
    }

    private function getElementByCode($getSeoUrl) {
        $arSelect = [
            'ID', 'IBLOCK_ID', 'ACTIVE', 'NAME', 'CODE', 'DETAIL_TEXT', 'TAGS', 'CATALOG_GROUP_1'
        ];
        $arFilter = [
            'IBLOCK_ID' => $this->catalogId,
            '=CODE' => $getSeoUrl
        ];

        $res = CIBlockElement::GetList([], $arFilter, false, false, $arSelect);
        if ($ob = $res->GetNextElement()) {
            $arFields = $ob->GetFields();
            $arFields['PROPERTIES'] = $ob->GetProperties();
            $ipropElementValues = new ElementValues($arFields['IBLOCK_ID'], $arFields['ID']);
            $ipropElementValues->clearValues();
            $iPropValues = new ElementValues($arFields['IBLOCK_ID'], $arFields['ID']);
            $arFields['META_PROPERTIES'] = $iPropValues->getValues();
            return $arFields;
        } else {
            return false;
        }
    }

    private function getElementById($id) {
        $arSelect = [
            'ID', 'IBLOCK_ID', 'IBLOCK_SECTION_ID', 'NAME', 'CODE', 'DETAIL_PAGE_URL'
        ];
        $arFilter = [
            'IBLOCK_ID' => $this->catalogId,
            'ID' => $id
        ];

        $res = CIBlockElement::GetList([], $arFilter, false, false, $arSelect);
        if ($ob = $res->GetNextElement()) {
            return $ob->GetFields();
        } else {
            return false;
        }
    }

    private function importColor(Product $product) {
        if (empty($product->getColor())) {
            return false;
        }
        if (!$colorId = $this->reference->getColorId($product->getColor())) {
            if (!$colorId = $this->reference->addColor($product->getColor())) {
                $this->logger->logError($product->getLine(), $this->errors['addColor'] . $product->getColor());
                return false;
            }
        }
        return $colorId;
    }

    private function importWhom(Product $product) {
        if (empty($product->getWhom())) {
            return false;
        }
        if (!$whomId = $this->reference->getWhomId($product->getWhom())) {
            if (!$whomId = $this->reference->addWhom($product->getWhom())) {
                $this->logger->logError($product->getLine(), $this->errors['addWhom'] . $product->getWhom());
                return false;
            }
        }
        return $whomId;
    }

    private function importReason(Product $product) {
        if (empty($product->getReason())) {
            return false;
        }
        if (!$reasonId = $this->reference->getReasonId($product->getReason())) {
            if (!$reasonId = $this->reference->addReason($product->getReason())) {
                $this->logger->logError($product->getLine(), $this->errors['addReason'] . $product->getReason());
                return false;
            }
        }
        return $reasonId;
    }

    private function importTags(Product $product) {
        if (count($product->getTags()) < 1) {
            return false;
        }
        $ids = [];
        foreach ($product->getTags() as $tag) {
            if (!$tagId = $this->reference->getTagId($tag)) {
                if (!$tagId = $this->reference->addTag($tag)) {
                    $this->logger->logError($product->getLine(), $this->errors['addTag'] . $tag);
                    return false;
                }
            }
            $ids[] = $tagId;
        }
        return (count($ids) > 0) ? $ids : false;
    }

    private function addElement(Product $product, $sectionId, $references) {
        $el = new CIBlockElement;

        $properties = [
            'importProduct' => '1',
            'minCountBuy' => $product->getBuyQuantity(),
            'additionalText' => $product->getAddText(),
            'composition' => ['VALUE' => ['TEXT' => $product->getComposition(), 'TYPE' => 'text']],
            'color' => $references['colorId'],
            'whom' => $references['whomId'],
            'reason' => $references['reasonId'],
            'tagsProductRef' => $references['tagsIds']
        ];

        $iProperties = [
            'ELEMENT_META_TITLE' => $product->getTagTitle(),
            'ELEMENT_META_KEYWORDS' => $product->getMetaKeywords(),
            'ELEMENT_META_DESCRIPTION' => $product->getMetaDesc(),
            'ELEMENT_PAGE_TITLE' => $product->getTagH1(),
        ];

        $fields = [
            'IBLOCK_ID' => $this->catalogId,
            'IBLOCK_SECTION_ID' => $sectionId,
            'ACTIVE' => 'Y',
            'NAME' => $product->getName(),
            'CODE' => $product->getSeoUrl(),
            'DETAIL_TEXT' => $product->getDescription(),
            'DETAIL_TEXT_TYPE' => 'text',
            'TAGS' => implode(',', $product->getTags()),
            'PROPERTY_VALUES' => $properties,
            'IPROPERTY_TEMPLATES' => $iProperties
        ];

        if ($id = $el->Add($fields)) {
            return $id;
        }
        return false;
    }

    private function addProduct($elementId, Product $product) {
        \Bitrix\Catalog\Model\Product::add(
            [
                "ID" => $elementId,
                "AVAILABLE" => "Y",
                "QUANTITY" => $product->getStorageQuantity(),
            ]
        );

        $this->addPrice($elementId, $product);
    }

    private function needUpdate($element, Product $product, $references) {
        $currentStr = htmlspecialchars_decode($element['NAME']) . htmlspecialchars_decode($element['DETAIL_TEXT']) . htmlspecialchars_decode($element['META_PROPERTIES']['ELEMENT_META_TITLE'])
            . htmlspecialchars_decode($element['META_PROPERTIES']['ELEMENT_PAGE_TITLE']) . htmlspecialchars_decode($element['META_PROPERTIES']['ELEMENT_META_DESCRIPTION'])
            . htmlspecialchars_decode($element['META_PROPERTIES']['ELEMENT_META_KEYWORDS']) . htmlspecialchars_decode($element['TAGS'])
            . implode(',', $element['PROPERTIES']["tagsProductRef"]["VALUE"]) . htmlspecialchars_decode($element['PROPERTIES']["additionalText"]["VALUE"])
            . floatval($element['CATALOG_PRICE_1']) . intval($element['CATALOG_QUANTITY']) . $element['CODE']
            . htmlspecialchars_decode($element['PROPERTIES']["composition"]["VALUE"]["TEXT"]) . $element['PROPERTIES']["color"]["VALUE"]
            . $element['PROPERTIES']["whom"]["VALUE"] . $element['PROPERTIES']["reason"]["VALUE"] . $element['ACTIVE'];

        $newStr = $product->getName() . $product->getDescription() . $product->getTagTitle()
            . $product->getTagH1() . $product->getMetaDesc()
            . $product->getMetaKeywords() . implode(', ', $product->getTags())
            . implode(',', $references['tagsIds']) . $product->getAddText()
            . $product->getBasePrice() . $product->getStorageQuantity() . $product->getSeoUrl()
            . $product->getComposition() . $references['colorId'] . $references['whomId'] . $references['reasonId'] . 'Y';

        if (md5($currentStr) != md5($newStr)) {
            return true;
        }
        return false;
    }

    private function saveToHistory($elementId) {
        if ($element = $this->getElementById($elementId)) {
            $data = [
                'ELEMENT_ID' => $element['ID'],
                'SECTION_ID' => $element['IBLOCK_SECTION_ID'],
                'NAME' => $element['NAME'],
                'DETAIL_PAGE_URL' => $element['DETAIL_PAGE_URL'],
                'DATE_CREATE' => new DateTime()
            ];

            try {
                $res = PriceListTable::add($data);
                if (!$res->isSuccess()) {
                    $this->logger->logInsert($element['ID']);
                }
            } catch (\Exception $e) {
                $this->logger->logInsert($element['ID']);
            }
        }
    }

    private function getParamsTranslit() {
        return [
            "max_len" => "100",
            "change_case" => "L",
            "replace_space" => "_",
            "replace_other" => "_",
            "delete_repeat_replace" => "true",
            "use_google" => "false",
        ];
    }

    private function updateElement($element, Product $product, $references) {
        $properties = [
            'importProduct' => '1',
            'minCountBuy' => $product->getBuyQuantity(),
            'additionalText' => $product->getAddText(),
            'composition' => ['VALUE' => ['TEXT' => $product->getComposition(), 'TYPE' => 'text']],
            'color' => $references['colorId'],
            'whom' => $references['whomId'],
            'reason' => $references['reasonId'],
            'tagsProductRef' => $references['tagsIds']
        ];

        $iProperties = [
            'ELEMENT_META_TITLE' => $product->getTagTitle(),
            'ELEMENT_META_KEYWORDS' => $product->getMetaKeywords(),
            'ELEMENT_META_DESCRIPTION' => $product->getMetaDesc(),
            'ELEMENT_PAGE_TITLE' => $product->getTagH1(),
        ];

        $fields = [
            'ACTIVE' => 'Y',
            'NAME' => $product->getName(),
            'CODE' => $product->getSeoUrl(),
            'DETAIL_TEXT' => $product->getDescription(),
            'DETAIL_TEXT_TYPE' => 'text',
            'TAGS' => implode(',', $product->getTags()),
            'IPROPERTY_TEMPLATES' => $iProperties
        ];
        $el = new CIBlockElement;
        if (!$el->Update($element['ID'], $fields)) {
            return false;
        }

        CIBlockElement::SetPropertyValuesEx($element['ID'], false, $properties);
        return true;
    }

    private function updateProduct($elementId, Product $product) {
        $iterator = \Bitrix\Catalog\Model\Product::getList([
            'select' => [
                'ID', 'QUANTITY', 'QUANTITY_RESERVED'
            ],
            'filter' => ['=ID' => $elementId]
        ]);
        $result = $iterator->fetch();
        unset($iterator);
        if (empty($result)) {
            return false;
        }

        $arFields = array(
            "QUANTITY" => $product->getStorageQuantity(),
        );

        $res = \Bitrix\Catalog\Model\Product::update(intval($elementId), $arFields);
        if ($res->isSuccess()) {
            return true;
        } else {
            return false;
        }
    }

    private function updatePrice($elementId, Product $product) {
        $this->addPrice($elementId, $product);
    }

    private function addPrice($elementId, Product $product) {
        $arFields = [
            "PRODUCT_ID" => $elementId,
            "CATALOG_GROUP_ID" => "1",
            "PRICE" => $product->getBasePrice(),
            "CURRENCY" => "RUB",
        ];
        $res = CPrice::GetList(
            [],
            [
                "PRODUCT_ID" => $elementId,
                "CATALOG_GROUP_ID" => "1"
            ]
        );

        if ($arr = $res->Fetch()) {
            Price::update($arr["ID"], $arFields);
        } else {
            Price::add($arFields);
        }
    }
}