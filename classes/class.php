<?

class newsListHandler {

   public function inputValidation($arParams)
   {
      foreach ($arParams['IBLOCK_ID'] as $IBlockID) {
         $res = CIBlock::GetByID($IBlockID);
         if (!($res->GetNext()))
         ShowError("Заданных инфоблоков не существует");
      }
   }

public function getIBlock($arParams) {
   if ($arParams["IBLOCK_TYPE"] !== '-') { // если тип инфоблока выбран, то делается выборка всех ID инфоблоков этого типа
      $res = CIBlock::GetList(
         array(),
         array(
            'TYPE' => $arParams['IBLOCK_TYPE'],
            'ACTIVE' => 'Y',
            "CNT_ACTIVE" => "Y",
         ),
         true
      );
      $arParams["IBLOCK_ID"] = [];
      while ($ar_res = $res->Fetch()) {
         $arParams["IBLOCK_ID"][] = $ar_res['ID'];
      }
   } else {
      foreach($arParams["IBLOCK_ID"] as &$IBlock) {
         $IBlock += 1;
      }
   }
   return $arParams;
}



public function itemGroup($items) {
   $itemGroups = [];										// разделение элементов на группы
	foreach ($items as &$item) {
		$itemGroups[$item['IBLOCK_ID']][] = $item;
	}
	return $itemGroups;
}

}
