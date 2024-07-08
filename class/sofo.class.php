<?php

class TSOFO {

	static function getDayFromAvailabilityCode($av_code) {

		if($av_code == 'AV_NOW') return 0;
		else if(preg_match('/AV_([0-9]+)([W,D,M]+)/',$av_code,$reg)) {

			$nb = (int)$reg[1];

			if($reg[2] == 'D') return $nb;
			else if($reg[2] == 'W') return $nb * 7;
			else if($reg[2] == 'M') return $nb * 31;

			return 0;

		}
		else{
			return 0;
		}

	}
	static function getMinAvailability($fk_product, $qty, $only_with_delai = false ,$fk_soc=0) {
	global $db,$form;

		$sql = "SELECT fk_availability".((float)DOL_VERSION>5 ? ',delivery_time_days' : '')."
				FROM ".MAIN_DB_PREFIX."product_fournisseur_price
				WHERE fk_product=". intval($fk_product) ." AND quantity <= ".$qty;


		if(!empty($fk_soc))
		{
			$sql .=  ' AND fk_soc='. intval($fk_soc)  ;
		}

		$res_av = $db->query($sql);

		$min = false;

		if(empty($form))$form=new Form($db);
		if(empty($form->cache_availability)){
			$form->load_cache_availability();
		}

		while($obj_availability = $db->fetch_object($res_av)) {

			if(!empty($obj_availability->delivery_time_days)) $nb_day = $obj_availability->delivery_time_days;
			else {
                if (!empty($obj_availability->fk_availability)) {
                    $av_code = $form->cache_availability[$obj_availability->fk_availability] ;
                    $nb_day = self::getDayFromAvailabilityCode($av_code['code']);
                }
			}
			if( ($min === false ||  (!empty($nb_day) && $nb_day <$min))
				&& (!$only_with_delai || $nb_day>0)) $min = $nb_day;

		}

		return $min;

	}


	/**
	 *	Return list of suppliers prices for a product
	 *
	 *  @param	    int		$productid       	Id of product
	 *  @param      string	$htmlname        	Name of HTML field
	 *  @param      int		$selected_supplier  Pre-selected supplier if more than 1 result
	 *  @return	    void
	 */
	public static function select_product_fourn_price($productid, $htmlname = 'productfournpriceid', $selected_supplier = '', $selected_price_ht = 0)
	{
		global $db,$langs,$conf;

		$langs->load('stocks');

		$sql = "SELECT p.rowid, p.label, p.ref, p.price, p.duration, pfp.fk_soc,";
		$sql.= " pfp.ref_fourn, pfp.rowid as idprodfournprice, pfp.price as fprice, pfp.remise_percent, pfp.quantity, pfp.unitprice,";
		$sql.= " pfp.fk_supplier_price_expression, pfp.fk_product, pfp.tva_tx, s.nom as name";
		$sql.= " FROM ".MAIN_DB_PREFIX."product as p";
		$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."product_fournisseur_price as pfp ON p.rowid = pfp.fk_product";
		$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."societe as s ON pfp.fk_soc = s.rowid";
		$sql.= " WHERE pfp.entity IN (".getEntity('productsupplierprice').")";
		$sql.= " AND p.tobuy = 1";
		$sql.= " AND s.fournisseur = 1";
		$sql.= " AND p.rowid = ".$productid;
		$sql.= " ORDER BY s.nom, pfp.ref_fourn DESC";

		dol_syslog(get_class()."::select_product_fourn_price", LOG_DEBUG);
		$result=$db->query($sql);

		if ($result)
		{
			$num = $db->num_rows($result);

			$form = '<select class="flat" id="select_'.$htmlname.'" name="'.$htmlname.'">';

			if (! $num)
			{
				$form.= '<option value="0">-- '.$langs->trans("NoSupplierPriceDefinedForThisProduct").' --</option>';
			}
			else
			{
				require_once DOL_DOCUMENT_ROOT.'/product/dynamic_price/class/price_parser.class.php';
				$form.= '<option value="0">&nbsp;</option>';

				$i = 0;
				while ($i < $num)
				{
					$objp = $db->fetch_object($result);

					$opt = '<option value="'.$objp->idprodfournprice.'"';
					//if there is only one supplier, preselect it
					if (
							$num == 1
						||  ($selected_supplier > 0 && $objp->fk_soc == $selected_supplier)
						||  (
								getDolGlobalString('SOFO_PRESELECT_SUPPLIER_PRICE_FROM_LINE_BUY_PRICE')
							&&	$selected_supplier <= 0
							&&	$selected_price_ht > 0
							&&	$selected_price_ht == $objp->unitprice * (1 - $objp->remise_percent / 100)
						)
					) {
						$opt .= ' selected';
					}
					$opt.= '>'.$objp->name.' - '.$objp->ref_fourn.' - ';

					if (isModEnabled('dynamicprices') && !empty($objp->fk_supplier_price_expression)) {
						$prod_supplier = new ProductFournisseur($db);
						$prod_supplier->product_fourn_price_id = $objp->idprodfournprice;
						$prod_supplier->id = $productid;
						$prod_supplier->fourn_qty = $objp->quantity;
						$prod_supplier->fourn_tva_tx = $objp->tva_tx;
						$prod_supplier->fk_supplier_price_expression = $objp->fk_supplier_price_expression;
						$priceparser = new PriceParser($db);
						$price_result = $priceparser->parseProductSupplier($prod_supplier);
						if ($price_result >= 0) {
							$objp->fprice = $price_result;
							if ($objp->quantity >= 1)
							{
								$objp->unitprice = $objp->fprice / $objp->quantity;
							}
						}
					}
					if ($objp->quantity == 1)
					{
						$opt.= price($objp->fprice * (1 - $objp->remise_percent / 100), 1, $langs, 0, 0, -1, $conf->currency)."/";
					}

					$opt.= $objp->quantity.' ';

					if ($objp->quantity == 1)
					{
						$opt.= $langs->trans("Unit");
					}
					else
					{
						$opt.= $langs->trans("Units");
					}
					if ($objp->quantity > 1)
					{
						$opt.=" - ";
						$opt.= price($objp->unitprice * (1 - $objp->remise_percent / 100), 1, $langs, 0, 0, -1, $conf->currency)."/".$langs->trans("Unit");
					}
					if ($objp->duration) $opt .= " - ".$objp->duration;
					$opt .= "</option>\n";

					$form.= $opt;
					$i++;
				}
			}

			$form.= '</select>';
			$db->free($result);
			return $form;
		}
		else
		{
			dol_print_error($db);
		}
	}

	/**
	 * @param $cmd_line_id id line customer cmd
	 * @return array|void
	 */
	public static  function getCmdFournFromCmdCustomer($cmd_line_id , $idproduct = null){
		global $db;
		$Tfourn = array();
		$TfournProduct = array();

		$sqlCmdFour = " SELECT cf.rowid FROM ".MAIN_DB_PREFIX."commande_fournisseur cf
						INNER JOIN ".MAIN_DB_PREFIX."commande_fournisseurdet AS cfd ON cfd.fk_commande = cf.rowid
		 				INNER JOIN ".MAIN_DB_PREFIX."element_element AS e ON (cfd.rowid = e.fk_target)
						WHERE sourcetype = 'commandedet'
						AND targettype = 'commande_fournisseurdet'
						AND cf.entity IN(1) AND e.fk_source = ".((int)$cmd_line_id);

		$result = $db->query($sqlCmdFour);
		while ($obj = $db->fetch_object($result)){

			if (!empty($obj->rowid)) {
				//
				$cmdF = new CommandeFournisseur($db);
				$res  = $cmdF->fetch($obj->rowid);
				if ($res > 0){
					$Tfourn[] = $cmdF;
				}
			}
		}
		// we want to return, if param activated, the ref cmd Fourn where is located the current product
		if ($idproduct > 0 ){

			if (!empty($Tfourn)){
				foreach ($Tfourn as $key => $val){
					foreach ($val->lines as $k => $currentLine){
						// le produit est present dans une ligne de la commande fournisseur ?
						if ($currentLine->fk_product == $idproduct){
							$TfournProduct[$val->id] = $val;

						}
					}
				}
				return $TfournProduct;

			}

		}

		return $Tfourn ;
	}

	/**
	 * @param $lineid
	 */
	public static function getAvailableQty($orderlineid, $qtyDesired){
		global $db;
		$find = false;
		$cmdRef = '';

		 $obj = new stdClass();

		$q = 'SELECT cfd.qty
				FROM '.MAIN_DB_PREFIX.'commande_fournisseurdet cfd
				INNER JOIN '.MAIN_DB_PREFIX.'element_element ee ON (ee.fk_target = cfd.rowid)
				WHERE ee.sourcetype="commandedet"
				AND ee.targettype = "commande_fournisseurdet"
				AND ee.fk_source = '.((int)$orderlineid);

		$resql = $db->query($q);
		if(!empty($resql)) {
			while($res = $db->fetch_object($resql)) {

				// le produit est present dans une ligne de la commande fournisseur ?
				// on à trouvé le produit dans une ligne de cette commande fournisseur on la flag
				$find = true;
				if(empty($obj->qtyAllFourn)) $obj->qtyAllFourn = 0;
				if(empty($obj->oldQty)) $obj->oldQty = 0;
				$obj->qtyAllFourn += $res->qty;

				//qty possible max
				$obj->qty = $qtyDesired - $obj->qtyAllFourn;

				// si le chiffre est negatif on l'initialise à zéro
				if (abs($obj->qty) != $obj->qty) $obj->qty = 0;

				$obj->oldQty += $res->qty;

			}
		}

		// le produit n'est pas dans une ligne de commande fournisseur
		// on retour la qty desirée
		if (!$find){
			$obj = new stdClass();
			$obj->qty = $qtyDesired;
			$obj->oldQty = 0;
		}

		return $obj;
	}


}
