<?php
/* Copyright (C) 2025 ATM Consulting
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * Class TSOFO
 *
 * Helper methods for SupplierOrderFromOrder module.
 */
class TSOFO
{
	/**
	 * Get number of days from an availability code.
	 *
	 * Examples:
	 * - AV_NOW => 0
	 * - AV_3D  => 3 days
	 * - AV_2W  => 14 days
	 * - AV_1M  => 31 days (approx)
	 *
	 * @param string $av_code Availability code.
	 *
	 * @return int Number of days (0 if unknown).
	 */
	public static function getDayFromAvailabilityCode($av_code)
	{

		if ($av_code == 'AV_NOW') return 0;
		elseif (preg_match('/AV_([0-9]+)([W,D,M]+)/', $av_code, $reg)) {
			$nb = (int) $reg[1];

			if ($reg[2] == 'D') return $nb;
			elseif ($reg[2] == 'W') return $nb * 7;
			elseif ($reg[2] == 'M') return $nb * 31;

			return 0;
		} else {
			return 0;
		}
	}

	/**
	 * Get minimal availability (in days) for a product based on supplier prices.
	 *
	 * @param int  $fk_product       Product id.
	 * @param int  $qty              Quantity.
	 * @param bool $only_with_delai  If true, ignore entries with no delay.
	 * @param int  $fk_soc           Optional supplier id filter.
	 *
	 * @return int|false Minimal number of days, or false if none found.
	 */
	public static function getMinAvailability($fk_product, $qty, $only_with_delai = false, $fk_soc = 0)
	{
		global $db,$form;

		$sql = "SELECT fk_availability".((float) DOL_VERSION>5 ? ',delivery_time_days' : '')."
				FROM ".$db->prefix()."product_fournisseur_price
				WHERE fk_product=". intval($fk_product) ." AND quantity <= ".$qty;


		if (!empty($fk_soc)) {
			$sql .=  ' AND fk_soc='. intval($fk_soc);
		}

		$res_av = $db->query($sql);

		$min = false;

		if (empty($form))$form=new Form($db);
		if (empty($form->cache_availability)) {
			$form->load_cache_availability();
		}

		while ($obj_availability = $db->fetch_object($res_av)) {
			if (!empty($obj_availability->delivery_time_days)) $nb_day = $obj_availability->delivery_time_days;
			else {
				if (!empty($obj_availability->fk_availability)) {
					$av_code = $form->cache_availability[$obj_availability->fk_availability] ;
					$nb_day = self::getDayFromAvailabilityCode($av_code['code']);
				}
			}
			if ( ($min === false ||  (!empty($nb_day) && $nb_day <$min))
				&& (!$only_with_delai || $nb_day>0)) $min = $nb_day;
		}

		return $min;
	}


	/**
	 *	Return list of suppliers prices for a product.
	 *
	 *  @param int    $productid         Id of product.
	 *  @param string $htmlname          Name of HTML field.
	 *  @param int    $selected_supplier Pre-selected supplier if more than 1 result.
	 *  @param float  $selected_price_ht Pre-selected unit price (HT) if any.
	 *
	 *  @return string HTML <select> element.
	 */
	public static function selectProductFournPrice($productid, $htmlname = 'productfournpriceid', $selected_supplier = '', $selected_price_ht = 0)
	{
		global $db,$langs,$conf;

		$langs->load('stocks');

		$sql = "SELECT p.rowid, p.label, p.ref, p.price, p.duration, pfp.fk_soc,";
		$sql.= " pfp.ref_fourn, pfp.rowid as idprodfournprice, pfp.price as fprice, pfp.remise_percent, pfp.quantity, pfp.unitprice,";
		$sql.= " pfp.fk_supplier_price_expression, pfp.fk_product, pfp.tva_tx, s.nom as name";
		$sql.= " FROM ".$db->prefix()."product as p";
		$sql.= " LEFT JOIN ".$db->prefix()."product_fournisseur_price as pfp ON p.rowid = pfp.fk_product";
		$sql.= " LEFT JOIN ".$db->prefix()."societe as s ON pfp.fk_soc = s.rowid";
		$sql.= " WHERE pfp.entity IN (".getEntity('productsupplierprice').")";
		$sql.= " AND p.tobuy = 1";
		$sql.= " AND s.fournisseur = 1";
		$sql.= " AND p.rowid = ".$productid;
		$sql.= " ORDER BY s.nom, pfp.ref_fourn DESC";

		dol_syslog(get_class()."::select_product_fourn_price", LOG_DEBUG);
		$result=$db->query($sql);

		if ($result) {
			$num = $db->num_rows($result);

			$form = '<select class="flat" id="select_'.$htmlname.'" name="'.$htmlname.'">';

			if (! $num) {
				$form.= '<option value="0">-- '.$langs->trans("NoSupplierPriceDefinedForThisProduct").' --</option>';
			} else {
				require_once DOL_DOCUMENT_ROOT.'/product/dynamic_price/class/price_parser.class.php';
				$form.= '<option value="0">&nbsp;</option>';

				$i = 0;
				while ($i < $num) {
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
							if ($objp->quantity >= 1) {
								$objp->unitprice = $objp->fprice / $objp->quantity;
							}
						}
					}
					if ($objp->quantity == 1) {
						$opt.= price($objp->fprice * (1 - $objp->remise_percent / 100), 1, $langs, 0, 0, -1, $conf->currency)."/";
					}

					$opt.= $objp->quantity.' ';

					if ($objp->quantity == 1) {
						$opt.= $langs->trans("Unit");
					} else {
						$opt.= $langs->trans("Units");
					}
					if ($objp->quantity > 1) {
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
		} else {
			dol_print_error($db);
		}
	}

	/**
	 * Get supplier orders from a customer order line.
	 *
	 * @param int      $cmd_line_id Id line customer order.
	 * @param int|null $idproduct   Product id used to filter supplier orders (optional).
	 *
	 * @return array Array of CommandeFournisseur objects.
	 */
	public static function getCmdFournFromCmdCustomer($cmd_line_id, $idproduct = null)
	{
		global $db;
		$Tfourn = array();
		$TfournProduct = array();

		$sqlCmdFour = " SELECT cf.rowid FROM ".$db->prefix()."commande_fournisseur cf
						INNER JOIN ".$db->prefix()."commande_fournisseurdet AS cfd ON cfd.fk_commande = cf.rowid
		 				INNER JOIN ".$db->prefix()."element_element AS e ON (cfd.rowid = e.fk_target)
						WHERE sourcetype = 'commandedet'
						AND targettype = 'commande_fournisseurdet'
						AND cf.entity IN(1) AND e.fk_source = ".((int) $cmd_line_id);

		$result = $db->query($sqlCmdFour);
		while ($obj = $db->fetch_object($result)) {
			if (!empty($obj->rowid)) {
				//
				$cmdF = new CommandeFournisseur($db);
				$res  = $cmdF->fetch($obj->rowid);
				if ($res > 0) {
					$Tfourn[] = $cmdF;
				}
			}
		}
		// we want to return, if param activated, the ref cmd Fourn where is located the current product
		if ($idproduct > 0 ) {
			if (!empty($Tfourn)) {
				foreach ($Tfourn as $key => $val) {
					foreach ($val->lines as $k => $currentLine) {
						// le produit est present dans une ligne de la commande fournisseur ?
						if ($currentLine->fk_product == $idproduct) {
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
	 * Get available quantity for a given customer order line,
	 * taking into account already linked supplier orders.
	 *
	 * @param int $orderlineid Customer order line id.
	 * @param int $qtyDesired  Desired quantity.
	 *
	 * @return stdClass Object with properties:
	 *                   - qty      : remaining qty that can be ordered
	 *                   - oldQty   : quantity already found on supplier orders
	 *                   - qtyAllFourn (optional) : total qty on supplier orders
	 */
	public static function getAvailableQty($orderlineid, $qtyDesired)
	{
		global $db;
		$find = false;
		$cmdRef = '';

		$obj = new stdClass();

		$q = 'SELECT cfd.qty
				FROM '.$db->prefix().'commande_fournisseurdet cfd
				INNER JOIN '.$db->prefix().'element_element ee ON (ee.fk_target = cfd.rowid)
				WHERE ee.sourcetype="commandedet"
				AND ee.targettype = "commande_fournisseurdet"
				AND ee.fk_source = '.((int) $orderlineid);

		$resql = $db->query($q);
		if (!empty($resql)) {
			while ($res = $db->fetch_object($resql)) {
				// le produit est present dans une ligne de la commande fournisseur ?
				// on à trouvé le produit dans une ligne de cette commande fournisseur on la flag
				$find = true;
				if (empty($obj->qtyAllFourn)) $obj->qtyAllFourn = 0;
				if (empty($obj->oldQty)) $obj->oldQty = 0;
				$obj->qtyAllFourn += $res->qty;

				//qty possible max
				$obj->qty = $qtyDesired - $obj->qtyAllFourn;

				// si le chiffre est negatif on l'initialise à zéro
				if (abs($obj->qty) != $obj->qty) $obj->qty = 0;

				$obj->oldQty += $res->qty;
			}
		}

		// le produit n'est pas dans une ligne de commande fournisseur
		// on retourne la qty desirée
		if (!$find) {
			$obj = new stdClass();
			$obj->qty = $qtyDesired;
			$obj->oldQty = 0;
		}

		return $obj;
	}
}
