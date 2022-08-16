<?
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();
/** @var CBitrixComponent $this */
/** @var array $arParams */
/** @var array $arResult */
/** @var string $componentPath */
/** @var string $componentName */
/** @var string $componentTemplate */
/** @global CDatabase $DB */
/** @global CUser $USER */
/** @global CMain $APPLICATION */

/** @global CIntranetToolbar $INTRANET_TOOLBAR */
global $INTRANET_TOOLBAR;

use Bitrix\Main\Context,
	Bitrix\Main\Type\DateTime,
	Bitrix\Main\Loader,
	Bitrix\Iblock;

CPageOption::SetOptionString("main", "nav_page_in_session", "N");

if (!include_once("classes/class.php")) {
	ShowError("Отсутствует файл class.php");
}

$NLH = new newsListHandler;

if (!isset($arParams["CACHE_TIME"]))
	$arParams["CACHE_TIME"] = 36000000;

$arParams["IBLOCK_TYPE"] = trim($arParams["IBLOCK_TYPE"]);

$arParams = $NLH->getIBlock($arParams);
$NLH->inputValidation($arParams);

if (!is_array($arParams["FIELD_CODE"])) 
	$arParams["FIELD_CODE"] = array();
foreach ($arParams["FIELD_CODE"] as $key => $val)
	if (!$val)
		unset($arParams["FIELD_CODE"][$key]);

if (empty($arParams["PROPERTY_CODE"]) || !is_array($arParams["PROPERTY_CODE"]))
	$arParams["PROPERTY_CODE"] = array();
foreach ($arParams["PROPERTY_CODE"] as $key => $val)
	if ($val === "")
		unset($arParams["PROPERTY_CODE"][$key]);

$arParams["DETAIL_URL"] = trim($arParams["DETAIL_URL"]);

$arParams["NEWS_COUNT"] = intval($arParams["NEWS_COUNT"]);
if ($arParams["NEWS_COUNT"] <= 0)
	$arParams["NEWS_COUNT"] = 20;

$arParams["CACHE_FILTER"] = $arParams["CACHE_FILTER"] == "Y";
if (!$arParams["CACHE_FILTER"] && count($arrFilter) > 0)
	$arParams["CACHE_TIME"] = 0;

if ($arParams["DISPLAY_TOP_PAGER"] || $arParams["DISPLAY_BOTTOM_PAGER"]) {
	$arNavParams = array(
		"nPageSize" => $arParams["NEWS_COUNT"],
		"bDescPageNumbering" => $arParams["PAGER_DESC_NUMBERING"],
		"bShowAll" => $arParams["PAGER_SHOW_ALL"],
	);
	$arNavigation = CDBResult::GetNavParams($arNavParams);
	if ($arNavigation["PAGEN"] == 0 && $arParams["PAGER_DESC_NUMBERING_CACHE_TIME"] > 0)
		$arParams["CACHE_TIME"] = $arParams["PAGER_DESC_NUMBERING_CACHE_TIME"];
} else {
	$arNavParams = array(
		"nTopCount" => $arParams["NEWS_COUNT"],
		"bDescPageNumbering" => $arParams["PAGER_DESC_NUMBERING"],
	);
	$arNavigation = false;
}

if (empty($arParams["PAGER_PARAMS_NAME"]) || !preg_match("/^[A-Za-z_][A-Za-z01-9_]*$/", $arParams["PAGER_PARAMS_NAME"])) {
	$pagerParameters = array();
} else {
	$pagerParameters = $GLOBALS[$arParams["PAGER_PARAMS_NAME"]];
	if (!is_array($pagerParameters))
		$pagerParameters = array();
}

$arParams["USE_PERMISSIONS"] = ($arParams["USE_PERMISSIONS"] ?? '') == "Y";
if (!is_array(($arParams["GROUP_PERMISSIONS"] ?? null)))
	$arParams["GROUP_PERMISSIONS"] = array(1);

$bUSER_HAVE_ACCESS = !$arParams["USE_PERMISSIONS"];
if ($arParams["USE_PERMISSIONS"] && isset($GLOBALS["USER"]) && is_object($GLOBALS["USER"])) {
	$arUserGroupArray = $USER->GetUserGroupArray();
	foreach ($arParams["GROUP_PERMISSIONS"] as $PERM) {
		if (in_array($PERM, $arUserGroupArray)) {
			$bUSER_HAVE_ACCESS = true;
			break;
		}
	}
}

if ($this->startResultCache(false, array(($arParams["CACHE_GROUPS"] === "N" ? false : $USER->GetGroups()), $bUSER_HAVE_ACCESS, $arNavigation, $arrFilter, $pagerParameters))) {
	if (!Loader::includeModule("iblock")) {
		$this->abortResultCache();
		ShowError(GetMessage("IBLOCK_MODULE_NOT_INSTALLED"));
		return;
	}

	$rsIBlock = $rsIBlock = CIBlock::GetList(array(), array(
		"ACTIVE" => "Y",
		array("ID" => $arParams["IBLOCK_ID"]),
	));

	$arResult = $rsIBlock->GetNext();
	if (!$arResult) {
		$this->abortResultCache();
		Iblock\Component\Tools::process404(
			trim($arParams["MESSAGE_404"]) ?: GetMessage("T_NEWS_NEWS_NA"),
			true,
			$arParams["SET_STATUS_404"] === "Y",
			$arParams["SHOW_404"] === "Y",
			$arParams["FILE_404"]
		);
		return;
	}

	$arResult["USER_HAVE_ACCESS"] = $bUSER_HAVE_ACCESS;
	//SELECT
	$arSelect = array_merge($arParams["FIELD_CODE"], array(
		"ID",
		"IBLOCK_ID",
		"IBLOCK_SECTION_ID",
		"NAME",
		"ACTIVE_FROM",
		"TIMESTAMP_X",
		"DETAIL_PAGE_URL",
		"LIST_PAGE_URL",
		"DETAIL_TEXT",
		"DETAIL_TEXT_TYPE",
		"PREVIEW_TEXT",
		"PREVIEW_TEXT_TYPE",
		"PREVIEW_PICTURE",
	));
	$bGetProperty = !empty($arParams["PROPERTY_CODE"]);
	//WHERE
	$arFilter = array(
		"IBLOCK_LID" => SITE_ID,
		"ACTIVE" => "Y",
		"CHECK_PERMISSIONS" => $arParams['CHECK_PERMISSIONS'] ? "Y" : "N",
	);

	if ($arParams["CHECK_DATES"])
		$arFilter["ACTIVE_DATE"] = "Y";

	$PARENT_SECTION = CIBlockFindTools::GetSectionID(
		$arParams["PARENT_SECTION"],
		$arParams["PARENT_SECTION_CODE"],
		array(
			"GLOBAL_ACTIVE" => "Y",
			"IBLOCK_ID" => $arResult["ID"],
		)
	);


	if (
		$arParams["STRICT_SECTION_CHECK"]
		&& ($arParams["PARENT_SECTION"] > 0
			|| $arParams["PARENT_SECTION_CODE"] <> ''
		)
	) {
		if ($PARENT_SECTION <= 0) {
			$this->abortResultCache();
			Iblock\Component\Tools::process404(
				trim($arParams["MESSAGE_404"]) ?: GetMessage("T_NEWS_NEWS_NA"),
				true,
				$arParams["SET_STATUS_404"] === "Y",
				$arParams["SHOW_404"] === "Y",
				$arParams["FILE_404"]
			);
			return;
		}
	}

	$arParams["PARENT_SECTION"] = $PARENT_SECTION;


	//ORDER BY
	$arSort = array(
		$arParams["SORT_BY1"] => $arParams["SORT_ORDER1"],
		$arParams["SORT_BY2"] => $arParams["SORT_ORDER2"],
	);
	if (!array_key_exists("ID", $arSort))
		$arSort["ID"] = "DESC";

	$shortSelect = array('ID', 'IBLOCK_ID');
	foreach (array_keys($arSort) as $index) {
		if (!in_array($index, $shortSelect)) {
			$shortSelect[] = $index;
		}
	}

	$listPageUrl = '';
	$arResult["ITEMS"] = array();
	$arResult["ELEMENTS"] = array();
	$rsElement = CIBlockElement::GetList(
		$arSort,
		array("IBLOCK_ID" => $arParams['IBLOCK_ID']),
		array("IBLOCK_ID"),
		$arNavParams,
		$shortSelect
	);

	while ($row = $rsElement->Fetch()) {
		$id = (int)$row['ID'];
		$arResult["ITEMS"][$id] = $row;
		$arResult["ELEMENTS"][] = $id;
	}




	unset($row);

	if (!empty($arResult['ITEMS'])) {
		$elementFilter = array(
			"IBLOCK_ID" => $arParams["IBLOCK_ID"],
			"IBLOCK_LID" => SITE_ID,
		);
		if (isset($arrFilter['SHOW_NEW'])) {
			$elementFilter['SHOW_NEW'] = $arrFilter['SHOW_NEW'];
		}

		$obParser = new CTextParser;
		$iterator = CIBlockElement::GetList(array(), $elementFilter, false, false, $arSelect);
		$iterator->SetUrlTemplates($arParams["DETAIL_URL"], '', ($arParams["IBLOCK_URL"] ?? ''));
		while ($arItem = $iterator->GetNext()) {
			$arButtons = CIBlock::GetPanelButtons(
				$arItem["IBLOCK_ID"],
				$arItem["ID"],
				0,
				array("SECTION_BUTTONS" => false, "SESSID" => false)
			);
			$arItem["EDIT_LINK"] = $arButtons["edit"]["edit_element"]["ACTION_URL"];
			$arItem["DELETE_LINK"] = $arButtons["edit"]["delete_element"]["ACTION_URL"];

			if ($arParams["PREVIEW_TRUNCATE_LEN"] > 0)
				$arItem["PREVIEW_TEXT"] = $obParser->html_cut($arItem["PREVIEW_TEXT"], $arParams["PREVIEW_TRUNCATE_LEN"]);

			if ($arItem["ACTIVE_FROM"] <> '')
				$arItem["DISPLAY_ACTIVE_FROM"] = CIBlockFormatProperties::DateFormat($arParams["ACTIVE_DATE_FORMAT"], MakeTimeStamp($arItem["ACTIVE_FROM"], CSite::GetDateFormat()));
			else
				$arItem["DISPLAY_ACTIVE_FROM"] = "";

			Iblock\InheritedProperty\ElementValues::queue($arItem["IBLOCK_ID"], $arItem["ID"]);

			$arItem["FIELDS"] = array();

			if ($bGetProperty) {
				$arItem["PROPERTIES"] = array();
			}
			$arItem["DISPLAY_PROPERTIES"] = array();

			if ($arParams["SET_LAST_MODIFIED"]) {
				$time = DateTime::createFromUserTime($arItem["TIMESTAMP_X"]);
				if (
					!isset($arResult["ITEMS_TIMESTAMP_X"])
					|| $time->getTimestamp() > $arResult["ITEMS_TIMESTAMP_X"]->getTimestamp()
				)
					$arResult["ITEMS_TIMESTAMP_X"] = $time;
			}

			if ($listPageUrl === '' && isset($arItem['~LIST_PAGE_URL'])) {
				$listPageUrl = $arItem['~LIST_PAGE_URL'];
			}

			$id = (int)$arItem["ID"];
			$arResult["ITEMS"][$id] = $arItem;
		}
		unset($obElement);
		unset($iterator);

		if ($bGetProperty) {
			unset($elementFilter['IBLOCK_LID']);
			CIBlockElement::GetPropertyValuesArray(
				$arResult["ITEMS"],
				$arResult["ID"],
				$elementFilter
			);
		}
	}

	$arResult['ITEMS'] = array_values($arResult['ITEMS']);

	foreach ($arResult["ITEMS"] as &$arItem) {
		if ($bGetProperty) {
			foreach ($arParams["PROPERTY_CODE"] as $pid) {
				$prop = &$arItem["PROPERTIES"][$pid];
				if (
					(is_array($prop["VALUE"]) && count($prop["VALUE"]) > 0)
					|| (!is_array($prop["VALUE"]) && $prop["VALUE"] <> '')
				) {
					$arItem["DISPLAY_PROPERTIES"][$pid] = CIBlockFormatProperties::GetDisplayValue($arItem, $prop, "news_out");
				}
			}
		}

		$ipropValues = new Iblock\InheritedProperty\ElementValues($arItem["IBLOCK_ID"], $arItem["ID"]);
		$arItem["IPROPERTY_VALUES"] = $ipropValues->getValues();
		Iblock\Component\Tools::getFieldImageData(
			$arItem,
			array('PREVIEW_PICTURE', 'DETAIL_PICTURE'),
			Iblock\Component\Tools::IPROPERTY_ENTITY_ELEMENT,
			'IPROPERTY_VALUES'
		);

		foreach ($arParams["FIELD_CODE"] as $code)
			if (array_key_exists($code, $arItem))
				$arItem["FIELDS"][$code] = $arItem[$code];
	}
	
	$arResult["ITEMS"] = $NLH->itemGroup($arResult["ITEMS"]);

	echo "<pre>";
	print_r($arResult["ITEMS"]);
	echo "</pre>";
	unset($arItem);

	$navComponentParameters = array();
	if ($arParams["PAGER_BASE_LINK_ENABLE"] === "Y") {
		$pagerBaseLink = trim($arParams["PAGER_BASE_LINK"]);
		if ($pagerBaseLink === "") {
			if (
				$arResult["SECTION"]
				&& $arResult["SECTION"]["PATH"]
				&& $arResult["SECTION"]["PATH"][0]
				&& $arResult["SECTION"]["PATH"][0]["~SECTION_PAGE_URL"]
			) {
				$pagerBaseLink = $arResult["SECTION"]["PATH"][0]["~SECTION_PAGE_URL"];
			} elseif (
				$listPageUrl !== ''
			) {
				$pagerBaseLink = $listPageUrl;
			}
		}

		if ($pagerParameters && isset($pagerParameters["BASE_LINK"])) {
			$pagerBaseLink = $pagerParameters["BASE_LINK"];
			unset($pagerParameters["BASE_LINK"]);
		}

		$navComponentParameters["BASE_LINK"] = CHTTP::urlAddParams($pagerBaseLink, $pagerParameters, array("encode" => true));
	}

	$arResult["NAV_STRING"] = $rsElement->GetPageNavStringEx(
		$navComponentObject,
		$arParams["PAGER_TITLE"],
		$arParams["PAGER_TEMPLATE"],
		$arParams["PAGER_SHOW_ALWAYS"],
		$this,
		$navComponentParameters
	);
	$arResult["NAV_CACHED_DATA"] = null;
	$arResult["NAV_RESULT"] = $rsElement;
	$arResult["NAV_PARAM"] = $navComponentParameters;

	$this->setResultCacheKeys(array(
		"ID",
		"IBLOCK_TYPE_ID",
		"LIST_PAGE_URL",
		"NAV_CACHED_DATA",
		"NAME",
		"SECTION",
		"ELEMENTS",
		"IPROPERTY_VALUES",
		"ITEMS_TIMESTAMP_X",
	));
	$this->includeComponentTemplate();
}

if (isset($arResult["ID"])) {
	$arTitleOptions = null;
	if ($USER->IsAuthorized()) {
		if (
			$APPLICATION->GetShowIncludeAreas()
			|| (is_object($GLOBALS["INTRANET_TOOLBAR"]) && $arParams["INTRANET_TOOLBAR"] !== "N")
			|| $arParams["SET_TITLE"]
		) {
			if (Loader::includeModule("iblock")) {
				$arButtons = CIBlock::GetPanelButtons(
					$arResult["ID"],
					0,
					$arParams["PARENT_SECTION"],
					array("SECTION_BUTTONS" => false)
				);

				if ($APPLICATION->GetShowIncludeAreas())
					$this->addIncludeAreaIcons(CIBlock::GetComponentMenu($APPLICATION->GetPublicShowMode(), $arButtons));

				if (
					is_array($arButtons["intranet"])
					&& is_object($INTRANET_TOOLBAR)
					&& $arParams["INTRANET_TOOLBAR"] !== "N"
				) {
					$APPLICATION->AddHeadScript('/bitrix/js/main/utils.js');
					foreach ($arButtons["intranet"] as $arButton)
						$INTRANET_TOOLBAR->AddButton($arButton);
				}

				if ($arParams["SET_TITLE"]) {
					$arTitleOptions = array(
						'ADMIN_EDIT_LINK' => $arButtons["submenu"]["edit_iblock"]["ACTION"],
						'PUBLIC_EDIT_LINK' => "",
						'COMPONENT_NAME' => $this->getName(),
					);
				}
			}
		}
	}

	$this->setTemplateCachedData($arResult["NAV_CACHED_DATA"]);

	$ipropertyExists = (!empty($arResult["IPROPERTY_VALUES"]) && is_array($arResult["IPROPERTY_VALUES"]));
	$iproperty = ($ipropertyExists ? $arResult["IPROPERTY_VALUES"] : array());

	if ($arParams["SET_TITLE"]) {
		if ($ipropertyExists && $iproperty["SECTION_PAGE_TITLE"] != "")
			$APPLICATION->SetTitle($iproperty["SECTION_PAGE_TITLE"], $arTitleOptions);
		elseif (isset($arResult["NAME"]))
			$APPLICATION->SetTitle($arResult["NAME"], $arTitleOptions);
	}

	if ($ipropertyExists) {
		if ($arParams["SET_BROWSER_TITLE"] === 'Y' && $iproperty["SECTION_META_TITLE"] != "")
			$APPLICATION->SetPageProperty("title", $iproperty["SECTION_META_TITLE"], $arTitleOptions);

		if ($arParams["SET_META_KEYWORDS"] === 'Y' && $iproperty["SECTION_META_KEYWORDS"] != "")
			$APPLICATION->SetPageProperty("keywords", $iproperty["SECTION_META_KEYWORDS"], $arTitleOptions);

		if ($arParams["SET_META_DESCRIPTION"] === 'Y' && $iproperty["SECTION_META_DESCRIPTION"] != "")
			$APPLICATION->SetPageProperty("description", $iproperty["SECTION_META_DESCRIPTION"], $arTitleOptions);
	}

	if ($arParams["INCLUDE_IBLOCK_INTO_CHAIN"] && isset($arResult["NAME"])) {
		if ($arParams["ADD_SECTIONS_CHAIN"] && is_array($arResult["SECTION"]))
			$APPLICATION->AddChainItem(
				$arResult["NAME"],
				$arParams["IBLOCK_URL"] <> '' ? $arParams["IBLOCK_URL"] : $arResult["LIST_PAGE_URL"]
			);
		else
			$APPLICATION->AddChainItem($arResult["NAME"]);
	}

	if ($arParams["ADD_SECTIONS_CHAIN"] && is_array($arResult["SECTION"])) {
		foreach ($arResult["SECTION"]["PATH"] as $arPath) {
			if ($arPath["IPROPERTY_VALUES"]["SECTION_PAGE_TITLE"] != "")
				$APPLICATION->AddChainItem($arPath["IPROPERTY_VALUES"]["SECTION_PAGE_TITLE"], $arPath["~SECTION_PAGE_URL"]);
			else
				$APPLICATION->AddChainItem($arPath["NAME"], $arPath["~SECTION_PAGE_URL"]);
		}
	}



	if ($arParams["SET_LAST_MODIFIED"] && $arResult["ITEMS_TIMESTAMP_X"]) {
		Context::getCurrent()->getResponse()->setLastModified($arResult["ITEMS_TIMESTAMP_X"]);
	}

	unset($iproperty);
	unset($ipropertyExists);

	return $arResult["ELEMENTS"];
}
