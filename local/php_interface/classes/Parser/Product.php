<?php
namespace WS\Parser;


class Product {

    const LITER_NAME = 'B';
    const LITER_DESCRIPTION = 'C';
    const LITER_TAG_TITLE = 'D';
    const LITER_TAG_H1 = 'E';
    const LITER_META_DESC = 'F';
    const LITER_META_KEYWORDS = 'G';
    const LITER_TAGS = 'H';
    const LITER_MODEL = 'I';
    const LITER_ADD_TEXT = 'J';
    const LITER_BASE_PRICE = 'K';
    const LITER_STORAGE_QUANTITY = 'L';
    const LITER_BUY_QUANTITY = 'M';
    const LITER_SEO_URL = 'N';
    const LITER_COMPOSITION = 'O';
    const LITER_SECTION = 'P';
    const LITER_SUBSECTION = 'Q';
    const LITER_COLOR = 'R';
    const LITER_WHOM = 'S';
    const LITER_REASON = 'T';

    /** @var integer */
    private $line;
    /** @var string */
    private $name;
    /** @var string */
    private $description;
    /** @var string */
    private $tagTitle;
    /** @var string */
    private $tagH1;
    /** @var string */
    private $metaDesc;
    /** @var string */
    private $metaKeywords;
    /** @var array */
    private $tags;
    /** @var string */
    private $model;
    /** @var string */
    private $addText;
    /** @var float */
    private $basePrice;
    /** @var integer */
    private $storageQuantity;
    /** @var integer */
    private $buyQuantity;
    /** @var string */
    private $seoUrl;
    /** @var string */
    private $composition;
    /** @var string */
    private $section;
    /** @var string */
    private $subSection;
    /** @var string */
    private $color;
    /** @var string */
    private $whom;
    /** @var string */
    private $reason;

    /**
     * Product constructor.
     * @param $arProduct
     */
    public function __construct($arProduct) {
        $this->line = $arProduct['line'];
        $this->name = trim($arProduct[self::LITER_NAME]);
        $this->description = $this->prepareDescription($arProduct[self::LITER_DESCRIPTION]);
        $this->tagTitle = trim($arProduct[self::LITER_TAG_TITLE]);
        $this->tagH1 = trim($arProduct[self::LITER_TAG_H1]);
        $this->metaDesc = trim($arProduct[self::LITER_META_DESC]);
        $this->metaKeywords = trim($arProduct[self::LITER_META_KEYWORDS]);
        $this->tags = $this->prepareTags($arProduct[self::LITER_TAGS]);
        $this->model = trim($arProduct[self::LITER_MODEL]);
        $this->addText = trim($arProduct[self::LITER_ADD_TEXT]);
        $this->basePrice = floatval(trim($arProduct[self::LITER_BASE_PRICE]));
        $this->storageQuantity = intval(trim($arProduct[self::LITER_STORAGE_QUANTITY]));
        $this->buyQuantity = $this->prepareBuyQ(trim($arProduct[self::LITER_BUY_QUANTITY]));
        $this->seoUrl = trim($arProduct[self::LITER_SEO_URL]);
        $this->composition = trim($arProduct[self::LITER_COMPOSITION]);
        $this->section = trim($arProduct[self::LITER_SECTION]);
        $this->subSection = trim($arProduct[self::LITER_SUBSECTION]);
        $this->color = $this->getUcfirst(trim($arProduct[self::LITER_COLOR]));
        $this->whom = $this->getUcfirst(trim($arProduct[self::LITER_WHOM]));
        $this->reason = $this->getUcfirst(trim($arProduct[self::LITER_REASON]));
    }

    public function getLine() {
        return $this->line;
    }

    public function getName() {
        return $this->name;
    }

    public function getDescription() {
        return $this->description;
    }

    public function getTagTitle() {
        return $this->tagTitle;
    }

    public function getTagH1() {
        return $this->tagH1;
    }

    public function getMetaDesc() {
        return $this->metaDesc;
    }

    public function getMetaKeywords() {
        return $this->metaKeywords;
    }

    public function getTags() {
        return $this->tags;
    }

    public function getModel() {
        return $this->model;
    }

    public function getAddText() {
        return $this->addText;
    }

    public function getBasePrice() {
        return $this->basePrice;
    }

    public function getStorageQuantity() {
        return $this->storageQuantity;
    }

    public function getBuyQuantity() {
        return $this->buyQuantity;
    }

    public function getSeoUrl() {
        return $this->seoUrl;
    }

    public function getComposition() {
        return $this->composition;
    }

    public function getSection() {
        return $this->section;
    }

    public function getSubSection() {
        return $this->subSection;
    }

    public function getColor() {
        return $this->color;
    }

    public function getWhom() {
        return $this->whom;
    }

    public function getReason() {
        return $this->reason;
    }

    private function prepareDescription($description) {
        $description = trim($description);
        $description = $this->getUcfirst($description);
        if (mb_stripos($description, 'Описание') === 0) {
            $str = preg_replace('/Описание/', '', $description, 1);
            return trim($str);
        }
        return $description;
    }

    private function prepareTags($tags) {
        $preparedTags = [];
        $tags = trim($tags);
        $arTags = explode(',', $tags);
        foreach ($arTags as $tag) {
            $tag = $this->getLcfirst(trim($tag));
            $preparedTags[] = $tag;
        }
        return $preparedTags;
    }

    private function prepareBuyQ($quantity) {
        if (intval($quantity) < 1) {
            $quantity = 1;
        }
        return $quantity;
    }

    private function getUcfirst($str) {
        $fc = mb_strtoupper(mb_substr($str, 0, 1));
        return $fc.mb_substr($str, 1);
    }

    private function getLcfirst($str) {
        $fc = mb_strtolower(mb_substr($str, 0, 1));
        return $fc.mb_substr($str, 1);
    }
}