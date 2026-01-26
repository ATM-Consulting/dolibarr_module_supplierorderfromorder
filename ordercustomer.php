<?php
/*
 * Copyright (C) 2013   Cédric Salvador    <csalvador@gpcsolutions.fr>
 * Copyright (C) 2014-2015   ATM Consulting   <support@atm-consulting.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

/**
 *  \file       htdocs/product/stock/replenish.php
 *  \ingroup    produit
 *  \brief      Page to list stocks to replenish
 */

require 'config.php';

ini_set('memory_limit', '1024M');
set_time_limit(0);

//ini_set('display_errors', 1);
//error_reporting(E_ALL);

dol_include_once('/product/class/product.class.php');
dol_include_once('/core/class/html.formother.class.php');
dol_include_once('/core/class/html.form.class.php');
dol_include_once('/fourn/class/fournisseur.commande.class.php');
dol_include_once("/core/lib/admin.lib.php");
dol_include_once("/fourn/class/fournisseur.class.php");
dol_include_once('/supplierorderfromorder/lib/function.lib.php');
dol_include_once("/commande/class/commande.class.php");
dol_include_once("/supplier_proposal/class/supplier_proposal.class.php");
dol_include_once('/suppplierorderfromorder/class/sofo.class.php');
if (isModEnabled('categorie')) {
	dol_include_once('/categories/class/categorie.class.php');
}
global $bc, $conf, $db, $langs, $user;

$prod = new Product($db);

$langs->load("products");
$langs->load("stocks");
$langs->load("orders");
$langs->load("supplierorderfromorder@supplierorderfromorder");
$hookmanager->initHooks(array('ordercustomer')); // Note that conf->hooks_modules contains array

$week_to_replenish = 0;

// Security check
if (!empty($user->societe_id)) {
	$socid = $user->societe_id;
}
$result = restrictedArea($user, 'produit|service&supplierorderfromorder');

//checks if a product has been ordered

$action = GETPOST('action', 'alpha');
$sref = GETPOST('sref', 'alpha');
$snom = GETPOST('snom', 'alpha');
$search_all = GETPOST('search_all', 'alpha');
$canvas = GETPOST('canvas', 'alpha');
$type = GETPOSTINT('type');
$tobuy = GETPOSTINT('tobuy');
$salert = GETPOST('salert', 'alpha');
$fourn_id = GETPOST('fourn_id', 'intcomma');
$sortfield = GETPOST('sortfield', 'alpha');
$sortorder = GETPOST('sortorder', 'alpha');
$page = GETPOSTINT('page');
$page = intval($page);
$selectedSupplier = GETPOSTINT('useSameSupplier');
$groupLinesByProduct = GETPOSTISSET('group_lines_by_product', 'int') ? GETPOSTINT('group_lines_by_product') : (getDolGlobalInt('SOFO_GROUP_LINES_BY_PRODUCT'));
$id = GETPOSTINT('id');
$origin_page = 'ordercustomer';


if (!$sortfield) {
	$sortfield = 'cd.rang';
}

if (!$sortorder) {
	$sortorder = 'ASC';
}
$conf->liste_limit = 1000; // Pas de pagination sur cet écran
$limit = $conf->liste_limit;
$offset = $limit * $page;


$TCategories = array();

if (isModEnabled('categorie')) {
	if (!isset($_REQUEST['categorie']) && getDolGlobalString('SOFO_DEFAULT_PRODUCT_CATEGORY_FILTER')) {
		$TCategories = unserialize(getDolGlobalString('SOFO_DEFAULT_PRODUCT_CATEGORY_FILTER'));
	} else {
		$categories = GETPOST('categorie', 'none');

		if (is_array($categories)) {
			if (in_array(-1, $categories) && count($categories) > 1) {
				unset($categories[array_search(-1, $categories)]);
			}
			$TCategories = array_map('intval', $categories);
		} elseif ($categories > 0) {
			$TCategories = array(intval($categories));
		} else {
			$TCategories = array(-1);
		}
	}
}

$TCategoriesQuery = $TCategories;
if (!empty($TCategoriesQuery) && is_array($TCategoriesQuery)) {
	foreach ($TCategories as $categID) {
		if ($categID <= 0)
			continue;

		$cat = new Categorie($db);
		$cat->fetch($categID);

		$TSubCat = get_categs_enfants($cat);
		foreach ($TSubCat as $subCatID) {
			if (!in_array($subCatID, $TCategories)) {
				$TCategoriesQuery[] = $subCatID;
			}
		}
	}
}

if (is_array($TCategoriesQuery) && count($TCategoriesQuery) == 1 && in_array(-1, $TCategoriesQuery)) {
	$TCategoriesQuery = array();
}


/*
 * Actions
 */
$parameters = array('id' => $id);
$reshook = $hookmanager->executeHooks('doActions', $parameters, $object, $action); // Note that $action and $object may have been modified by some hooks
if ($reshook < 0) setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');

if (empty($reshook)) {
	if ($action == 'valid-propal') $getNomUrlConcat = '';
	if (isset($_POST['button_removefilter']) || in_array($action, array('valid-propal', 'valid-order'))) {
		$sref = '';
		$snom = '';
		$sal = '';
		$salert = '';
		$TCategoriesQuery = array();
		$TCategories = array(-1);
	}


	//orders creation
	//FIXME: could go in the lib
	if (in_array($action, array('valid-propal', 'valid-order'))) {
		$actionTarget = 'order';
		if ($action == 'valid-propal') {
			$actionTarget = 'propal';
		}

		$linecount = GETPOSTINT('linecount');
		$box = false;
		unset($_POST['linecount']);

		if ($linecount > 0) {
			$suppliers = array();
			for ($i = 0; $i < $linecount; $i++) {
				if (GETPOST('check' . $i, 'alpha') === 'on' && (GETPOSTINT('fourn' . $i) > 0 || GETPOSTINT('fourn_free' . $i) > 0)) { //one line
					_prepareLine($i, $actionTarget);
				}
				unset($_POST[$i]);
			}

			//we now know how many orders we need and what lines they have
			$i = 0;
			$id = 0;
			$nb_orders_created = 0;
			$orders = array();
			$suppliersid = array_keys($suppliers);
			$projectid = GETPOSTINT('projectid');

			foreach ($suppliers as $idsupplier => $supplier) {
				if ($actionTarget == 'propal') {
					$order = new SupplierProposal($db);
					$obj = _getSupplierProposalInfos($idsupplier, $projectid);
				} else {
					$order = new CommandeFournisseur($db);
					$obj = _getSupplierOrderInfos($idsupplier, $projectid);
				}

				$commandeClient = new Commande($db);
				$commandeClient->fetch(GETPOSTINT('id'));

				// Test recupération contact livraison
				if (getDolGlobalString('SUPPLIERORDER_FROM_ORDER_CONTACT_DELIVERY')) {
					$contact_ship = $commandeClient->getIdContact('external', 'SHIPPING');
					$contact_ship = $contact_ship[0] ?? '';
				} else {
					$contact_ship = null;
				}

				//Si une commande au statut brouillon existe déjà et que l'option SOFO_CREATE_NEW_SUPPLIER_ODER_ANY_TIME
				if ($obj && !getDolGlobalString('SOFO_CREATE_NEW_SUPPLIER_ODER_ANY_TIME')) {
					$order->fetch($obj->rowid);
					$order->socid = $idsupplier;

					if (!empty($projectid)) {
						$order->fk_project = GETPOSTINT('projectid');
					}

					// On vérifie qu'il n'existe pas déjà un lien entre la commande client et la commande fournisseur dans la table element_element.
					// S'il n'y en a pas, on l'ajoute, sinon, on ne l'ajoute pas
					$order->fetchObjectLinked('', 'commande', $order->id, 'order_supplier');
					$order->add_object_linked('commande', GETPOSTINT('id'));

					// cond reglement, mode reglement, delivery date
					_appliCond($order, $commandeClient);

					$id++; //$id doit être renseigné dans tous les cas pour que s'affiche le message 'Vos commandes ont été générées'
					$newCommande = false;
				} else {
					$order->socid = $idsupplier;
					if (!empty($projectid)) {
						$order->fk_project = GETPOSTINT('projectid');
					}
					// cond reglement, mode reglement, delivery date
					_appliCond($order, $commandeClient);

					$id = $order->create($user);

					if (getDolGlobalInt('SUPPLIERORDER_FROM_ORDER_NOTES_PUBLIC')) {
						$publicNote = $commandeClient->note_public;
						$order->update_note($publicNote, '_public');
					}

					if (getDolGlobalInt('SUPPLIERORDER_FROM_ORDER_NOTES_PRIVATE')) {
						$privateNote = $commandeClient->note_private;
						$order->update_note($privateNote, '_private');
					}

					if ($contact_ship && getDolGlobalString('SUPPLIERORDER_FROM_ORDER_CONTACT_DELIVERY'))
						$order->add_contact($contact_ship, 'SHIPPING');
					$order->add_object_linked('commande', GETPOSTINT('id'));
					$newCommande = true;

					$nb_orders_created++;
				}

				$order_id = $order->id;
				if (!empty($order_id) && $action == 'valid-propal') $getNomUrlConcat .= ' ' . $order->getNomUrl();
				//trick to know which orders have been generated this way
				$order->source = 42;
				$MaxAvailability = 0;

				foreach ($supplier['lines'] as $line) {
					$done = false;
					if (empty($line->array_options)) {
						$line->array_options = array();
					}

					if (getDolGlobalInt('SOFO_ENABLE_LINKED_EXTRAFIELDS') && (!isset($line->product_type) || (int) $line->product_type === 0)) {
						if (empty($commandeClient->thirdparty)) {
							$commandeClient->fetch_thirdparty();
						}
						$linkOptions = array(
							'options_SOFO_linked_order' => $commandeClient->id,
						);
						if (!empty($commandeClient->thirdparty)) {
							$linkOptions['options_SOFO_linked_thirdparty'] = $commandeClient->thirdparty->id;
						}
						$line->array_options = array_merge($line->array_options, $linkOptions);
						$linkOptionsForUpdate = $linkOptions;
					} else {
						$linkOptionsForUpdate = array();
					}


					$prodfourn = new ProductFournisseur($db);
					$prodfourn->fetch_product_fournisseur_price($_REQUEST['fourn' . $i]);

					foreach ($order->lines as $lineOrderFetched) {
						$q = 'SELECT ee.rowid
						 		FROM ' . $db->prefix() . 'element_element ee
								WHERE ee.sourcetype="commandedet"
								AND ee.targettype = "commande_fournisseurdet"
								AND ee.fk_source = ' . ((int) $line->id) . '
								AND ee.fk_target = ' . ((int) $lineOrderFetched->id);
						$resultquery = $db->query($q);

						$id_line_element_element = 0;
						if (!empty($resultquery)) {
							$res = $db->fetch_object($resultquery);
							$id_line_element_element = $res->rowid;
						}

						if (!empty($id_line_element_element)) {
							$remise_percent = $lineOrderFetched->remise_percent;
							if ($line->remise_percent > $remise_percent)
								$remise_percent = $line->remise_percent;

							$arrayOptionsUpdate = (array) $lineOrderFetched->array_options;
							if (!empty($linkOptions)) {
								$arrayOptionsUpdate = array_merge($arrayOptionsUpdate, $linkOptions);
							}
							if ($order->element == 'order_supplier') {
								$order->updateline(
									$lineOrderFetched->id,
									$lineOrderFetched->desc,
									// FIXME: The current existing line may very well not be at the same purchase price
									$lineOrderFetched->pu_ht,
									$lineOrderFetched->qty + $line->qty,
									$remise_percent,
									$lineOrderFetched->tva_tx,
									0,
									0,
									'HT',
									0,
									(int) $lineOrderFetched->product_type,
									0,
									0,
									0,
									$arrayOptionsUpdate,
									$lineOrderFetched->fk_unit
								);
							} elseif ($order->element == 'supplier_proposal') {
								$order->updateline(
									$lineOrderFetched->id,
									$prodfourn->fourn_unitprice, //$lineOrderFetched->pu_ht is empty,
									$lineOrderFetched->qty + $line->qty,
									$remise_percent,
									$lineOrderFetched->tva_tx,
									0, //$txlocaltax1=0,
									0, //$txlocaltax2=0,
									$lineOrderFetched->desc,
									'HT',
									0,
									(int) $lineOrderFetched->product_type,
									0,
									0,
									0,
									$arrayOptionsUpdate,
									$lineOrderFetched->fk_unit
								);
							}

							$done = true;
							break;
						}
					}

					// On ajoute une ligne seulement si un "updateline()" n'a pas été fait et si la quantité souhaitée est supérieure à zéro
					if (!$done) {
						if ($order->element == 'order_supplier') {
							$cf_line_id = $order->addline(
								$line->desc,
								$line->subprice,
								$line->qty,
								$line->tva_tx,
								null,
								null,
								$line->fk_product,
								// We need to pass fk_prod_fourn_price to get the right price.
								$line->fk_prod_fourn_price,
								$line->ref_fourn,
								$line->remise_percent,
								 'HT',
								 0,
								 $line->product_type,
								 $line->info_bits,
								 false, // $notrigger
								 null, // $date_start
								 null, // $date_end
								 $line->array_options,
								 null,
								 0,
								 $line->origin,
								 $line->origin_id
							);

							// Création d'un lien entre ligne de commande client et ligne de commande fournisseur
							$cf_line = new CommandeFournisseurLigne($db);
							$cf_line->element = 'commande_fournisseurdet';
							$cf_line->id = $cf_line_id;
							$cf_line->add_object_linked('commandedet', $line->origin_id);
						} elseif ($order->element == 'supplier_proposal') {
							$order->addline(
								$line->desc,
								$line->subprice,
								$line->qty,
								$line->tva_tx,
								null,
								null,
								$line->fk_product,
								$line->remise_percent,
								'HT',
								0, //$pu_ttc=0,
								$line->info_bits, //$info_bits=0,
								$line->product_type, //$type=0,
								-1, //$rang=-1,
								0, //$special_code=0, ,
								0, //$fk_parent_line=0, ,
								$line->fk_prod_fourn_price, //$fk_fournprice=0, ,
								0, //$pa_ht=0, ,
								'', //$label='',,
								$line->array_options, //$array_option=0, ,
								$line->ref_fourn, //$ref_fourn='', ,
								'', //$fk_unit='', ,
								$line->origin, //$origin='', ,
								$line->origin_id//$origin_id=0
							);
						}
					}

					$nb_day = (int) TSOFO::getMinAvailability($line->fk_product, $line->qty, 1, $prodfourn->fourn_id);
					if ($MaxAvailability < $nb_day) {
						$MaxAvailability = $nb_day;
					}
				}

				if (getDolGlobalString('SOFO_USE_MAX_DELIVERY_DATE')) {
					$order->delivery_date = dol_now() + $MaxAvailability * 86400;
					$order->setDeliveryDate($user, $order->delivery_date);
				}

				$order->cond_reglement_id = 0;
				$order->mode_reglement_id = 0;

				if ($id < 0) {
					$fail++; // FIXME: declare somewhere and use, or get rid of it!
					$msg = $langs->trans('OrderFail') . "&nbsp;:&nbsp;";
					$msg .= $order->error;
					setEventMessage($msg, 'errors');
				} else {
					// CODE de redirection s'il y a un seul fournisseur (évite de le laisser sur la page sans comprendre)
					if (getDolGlobalString('SUPPLIERORDER_FROM_ORDER_HEADER_SUPPLIER_ORDER')) {
						if (count($suppliersid) == 1) {
							if ($action === 'valid-order') {
								$link = dol_buildpath('/fourn/commande/card.php?id=' . $order_id, 1);
							} else {
								$link = dol_buildpath('/supplier_proposal/card.php?id=' . $order_id, 1);
							}
							header('Location:' . $link);
							exit();
						}
					}
				}
				$i++;
			}

			$id = GETPOSTINT('id');
			$origin_page = 'ordercustomer';
			if ($action == 'valid-order') header("Location: " . DOL_URL_ROOT . "/fourn/commande/list.php?id=" . $id . '&origin_page=' . $origin_page);
			elseif ($action == 'valid-propal' && !empty($getNomUrlConcat)) setEventMessage($langs->trans('SupplierProposalSuccessfullyCreated') . $getNomUrlConcat, 'mesgs');
		}

		if ($nb_orders_created > 0) {
			setEventMessages($langs->trans('supplierorderfromorder_nb_orders_created', $nb_orders_created), array());
		}

		if ($box === false) {
			setEventMessage($langs->trans('SelectProduct'), 'warnings');
		} else {
			foreach ($suppliers as $idSupplier => $lines) {
				$j = 0;
				foreach ($lines as $line) {
					$sql = "SELECT quantity";
					$sql .= " FROM " . $db->prefix() . "product_fournisseur_price";
					$sql .= " WHERE fk_soc = " . $idSupplier;
					$sql .= " AND fk_product = " . $line[$j]->fk_product;
					$sql .= " ORDER BY quantity ASC";
					$sql .= " LIMIT 1";
					$resql = $db->query($sql);
					if ($resql) {
						$resql = $db->fetch_object($resql);
						if ($line[$j]->qty < $resql->quantity) {
							$p = new Product($db);
							$p->fetch($line[$j]->fk_product);
							$f = new Fournisseur($db);
							$f->fetch($idSupplier);
							$rates[$f->name] = $p->label;
						} else {
							$p = new Product($db);
							$p->fetch($line[$j]->fk_product);
							$f = new Fournisseur($db);
							$f->fetch($idSupplier);
							$ajoutes[$f->name] = $p->label;
						}
					}
					$j++;
				}
			}
			$mess = "";
			// FIXME: declare $ajoutes somewhere. It's unclear if it should be reinitialized or not in the interlocking loops.
			if (!empty($ajoutes)) {
				foreach ($ajoutes as $nomFournisseur => $nomProd) {
					if ($actionTarget == 'propal') {
						$mess .= $langs->trans('ProductAddToSupplierQuotation', $nomProd, $nomFournisseur) . '<br />';
					} else {
						$mess .= $langs->trans('ProductAddToSupplierOrder', $nomProd, $nomFournisseur) . '<br />';
					}
				}
			}
			// FIXME: same as $ajoutes.
			if (!empty($rates)) {
				foreach ($rates as $nomFournisseur => $nomProd) {
					$mess .= "Quantité insuffisante de ' " . $nomProd . " ' pour le fournisseur ' " . $nomFournisseur . " '<br />";
				}
			}
			if (!empty($rates)) {
				setEventMessage($mess, 'warnings');
			} else {
				setEventMessage($mess, 'mesgs');
			}
		}
	}

	if (in_array($action, array('view-valid-order'))) {
		header("Location: " . DOL_URL_ROOT . "/fourn/commande/list.php?id=" . $id . '&origin_page=' . $origin_page);
	}
}

/*
 * View
 */
$listParam = (isset($type) ? '&type=' . $type : '');
$TCachedProductId =& $_SESSION['TCachedProductId'];
if (empty($TCachedProductId))
	$TCachedProductId = array();
if (GETPOST('purge_cached_product', 'none') == 'yes')
	$TCachedProductId = array();

//Do we want include shared sotck to kwon what order
if (!getDolGlobalString('SOFO_CHECK_STOCK_ON_SHARED_STOCK')) {
	$entityToTest = $conf->entity;
} else {
	$entityToTest = getEntity('stock');
}

$title = $langs->trans('ProductsToOrder');
$db->query("SET SQL_MODE=''");

$fk_commande = GETPOSTINT('id');
$sql = sofoBuildOrderCustomerQuery($db, $entityToTest, $TCategoriesQuery, $fk_commande, $search_all, $type, $sref, $snom, $canvas, $salert, $groupLinesByProduct);

if ($salert == 'on') {
	$alertchecked = 'checked="checked"';
}

$sql2 = '';
//On prend les lignes libre
if (GETPOSTINT('id') && getDolGlobalString('SOFO_ADD_FREE_LINES')) {
	$sql2 .= 'SELECT cd.rowid, cd.description, cd.qty as qty, cd.product_type, cd.price, cd.buy_price_ht
			 FROM ' . $db->prefix() . 'commandedet as cd
			 LEFT JOIN ' . $db->prefix() . 'commande as c ON (cd.fk_commande = c.rowid)
			 WHERE c.rowid = ' . GETPOSTINT('id') . ' AND cd.product_type IN(0,1) AND fk_product IS NULL';
	if (getDolGlobalString('SUPPORDERFROMORDER_USE_ORDER_DESC')) {
		$sql2 .= ' GROUP BY cd.description';
	}
}
$sql .= $db->order($sortfield, $sortorder);

if (getDolGlobalString('SOFO_USE_DELIVERY_TIME'))
	$sql .= $db->plimit($limit + 1, $offset);
$resql = $db->query($sql);

if (isset($_REQUEST['DEBUG']) || $resql === false) {
	dol_print_error($db);
	exit;
}

if ($sql2 && $fk_commande > 0) {
	$sql2 .= $db->order($sortfield, $sortorder);
	$sql2 .= $db->plimit($limit + 1, $offset);
	$resql2 = $db->query($sql2);
}
$form = new Form($db);

if ($resql || $resql2) {
	$num = $db->num_rows($resql);

	//pour chaque produit de la commande client on récupère ses sous-produits

	$TProducts = array(); //on rassemble produit et sous-produit dans ce tableau
	$i = 0;

	while ($i < min($num, $limit)) {
		//fetch le produit
		$objp = $db->fetch_object($resql);

		array_push($TProducts, $objp);

		$product = new Product($db);
		$product->fetch($objp->rowid);

		if (getDolGlobalString('PRODUIT_SOUSPRODUITS') && getDolGlobalString('SOFO_VIRTUAL_PRODUCTS')) {
			//récupération des sous-produits
			$product->get_sousproduits_arbo();
			$prods_arbo = $product->get_arbo_each_prod();

			if (!empty($prods_arbo)) {
				$TProductToHaveQtys = array();        //tableau des dernières quantités à commander par niveau

				foreach ($prods_arbo as $key => $value) {
					$level = (int) $value['level'];

					//si on est au premier niveau, on réinitialise
					if ($level === 1) {
						$TProductToHaveQtys[$level] = isset($objp->qty) ? (float) $objp->qty : 0;
						$qtyParentToHave = $TProductToHaveQtys[$level];
					}

					//si on est au niveau supérieur à 1, alors on récupère la quantité de produit parent à avoir
					if ($level > 1) {
						$qtyParentToHave = isset($TProductToHaveQtys[$level - 1]) ? $TProductToHaveQtys[$level - 1] : 0;
					}


					//on définit l'objet sous produit

					$objsp = new stdClass();

					$sousproduit = new Product($db);
					$sousproduit->fetch($value['id']);

					$objsp->rowid = $sousproduit->id;
					$objsp->ref = $sousproduit->ref;
					$objsp->label = $sousproduit->label;
					$objsp->price = $sousproduit->price;
					$objsp->price_ttc = $sousproduit->price_ttc;
					$objsp->price_base_type = $sousproduit->price_base_type;
					$objsp->fk_product_type = $sousproduit->type;
					$objsp->datem = $sousproduit->date_modification;
					$objsp->duration = $sousproduit->duration_value;
					$objsp->tobuy = $sousproduit->status_buy;
					$objsp->seuil_stock_alert = $sousproduit->seuil_stock_alerte;
					$objsp->stock_physique = $sousproduit->stock_reel;
					$childNeeded = !empty($value['nb']) ? (float) $value['nb'] : 0;
					$objsp->qty = $qtyParentToHave * $childNeeded;            // qty du produit = quantité du produit parent commandé * nombre du sous-produit nécessaire pour le produit parent
					$objsp->desiredstock = $sousproduit->desiredstock;
					$objsp->fk_parent = $value['id_parent'];
					$objsp->level = $level;

					//Sauvegarde du dernier stock commandé pour le niveau du sous-produit
					$TProductToHaveQtys[$level] = $objsp->qty;

					//ajout du sous-produit dans le tableau
					array_push($TProducts, $objsp);
				}
			}
		}

		$i++;
	}

	$i = 0;
	$num = count($TProducts);
	$num2 = $sql2 ? $db->num_rows($resql2) : 0;

	$helpurl = 'EN:Module_Stocks_En|FR:Module_Stock|';
	$helpurl .= 'ES:M&oacute;dulo_Stocks';
	llxHeader('', $title, $helpurl, $title);

	$includeProduct = '';
	if (getDolGlobalInt('INCLUDE_PRODUCT_LINES_WITH_ADEQUATE_STOCK') == 1) {
		$includeProduct = '&show_stock_no_need=yes';
		$listParam .= '&show_stock_no_need=yes';
	}

	$head = array();
	$head[0][0] = dol_buildpath('/supplierorderfromorder/ordercustomer.php?id=' . GETPOSTINT('id') . '&origin_page=' . $origin_page . $includeProduct, 2);
	$head[0][1] = $title;
	$head[0][2] = 'supplierorderfromorder';


	if (getDolGlobalString('SOFO_USE_NOMENCLATURE')) {
		$head[1][0] = dol_buildpath('/supplierorderfromorder/dispatch_to_supplier_order.php?from=commande&fromid=' . GETPOSTINT('id'), 2);
		$head[1][1] = $langs->trans('ProductsAssetsToOrder');
		$head[1][2] = 'supplierorderfromorder_dispatch';
	}

	/*$head[1][0] = DOL_URL_ROOT.'/product/stock/replenishorders.php';
	$head[1][1] = $langs->trans("ReplenishmentOrders");
	$head[1][2] = 'replenishorders';*/
	dol_fiche_head($head, 'supplierorderfromorder', $langs->trans('Replenishment'), -1, 'stock');

	$origin = new Commande($db);
	$id = GETPOSTINT('id');
	$res = $origin->fetch($id);

	if ($res > 0) {
		$morehtmlref = '<div class="refidno">';
		$morehtmlref .= $langs->trans('InitialCommande') . $origin->getNomUrl();
		$morehtmlref .= '</div>';
		dol_banner_tab($origin, 'ref', '', 0, 'ref', 'ref', $morehtmlref);
	}


	if ($sref || $snom || $search_all || $salert || GETPOST('search', 'alpha')) {
		$filters = '&sref=' . $sref . '&snom=' . $snom;
		$filters .= '&search_all=' . $search_all;
		$filters .= '&salert=' . $salert;

		if (!getDolGlobalInt('SOFO_USE_DELIVERY_TIME')) {
			print_barre_liste(
				$title,
				$page,
				'ordercustomer.php',
				$filters,
				$sortfield,
				$sortorder,
				'',
				$num);
		}
	} else {
		$filters = '&sref=' . $sref . '&snom=' . $snom;
		$filters .= '&fourn_id=' . $fourn_id;
		$filters .= (isset($type) ? '&type=' . $type : '');
		$filters .= '&salert=' . $salert;

		if (getDolGlobalString('SOFO_USE_DELIVERY_TIME')) {
			print_barre_liste(
				$title,
				$page,
				'ordercustomer.php',
				$filters,
				$sortfield,
				$sortorder,
				'',
				$num
			);
		}
	}


	if (getDolGlobalString('SOFO_QTY_LINES_COMES_FROM_ORIGIN_ORDER_ONLY')) {
		print '<br>' . img_warning() . '&nbsp;<STRONG><span style="color:red">' . $langs->trans('SOFO_QTY_LINES_COMES_FROM_ORIGIN_ORDER_ONLY') . '</span></STRONG><br>';
	}

	$yesno = getDolGlobalString('INCLUDE_PRODUCT_LINES_WITH_ADEQUATE_STOCK') ? '&show_stock_no_need=yes' : '';

	print'</div>';
	print '<form action="' . $_SERVER['PHP_SELF'] . '?id=' . GETPOSTINT('id') . '&projectid=' . GETPOSTINT('projectid') . $yesno . '" method="post" name="formulaire">' .
		'<input type="hidden" name="id" value="' . GETPOSTINT('id') . '">' .
		'<input type="hidden" name="token" value="' . $_SESSION['newtoken'] . '">' .
		'<input type="hidden" name="sortfield" value="' . $sortfield . '">' .
		'<input type="hidden" name="sortorder" value="' . $sortorder . '">' .
		'<input type="hidden" name="type" value="' . $type . '">' .
		'<input type="hidden" name="linecount" value="' . ($num + $num2) . '">' .
		'<input type="hidden" name="group_lines_by_product" value="' . $groupLinesByProduct . '">' .
		'<input type="hidden" name="fk_commande" value="' . GETPOSTINT('fk_commande') . '">' .
		'<input type="hidden" name="show_stock_no_need" value="' . GETPOST('show_stock_no_need', 'none') . '">';

	if (getDolGlobalInt('INCLUDE_PRODUCT_LINES_WITH_ADEQUATE_STOCK') == 0) {
		echo '<div style="text-align:right"><a href="' . $_SERVER["PHP_SELF"] . '?' . $_SERVER["QUERY_STRING"] . '&show_stock_no_need=yes">' . $langs->trans('ShowLineEvenIfStockIsSuffisant') . '</a></div><br>';
	}


	if (!empty($TCachedProductId)) {
		echo '<a style="color:red; font-weight:bold;" href="' . $_SERVER["PHP_SELF"] . '?' . $_SERVER["QUERY_STRING"] . '&purge_cached_product=yes">' . $langs->trans('PurgeSessionForCachedProduct') . '</a><br>';
	}

	if (!empty($groupLinesByProduct)) {
		print '<STRONG>' . $langs->trans('SOFO_GROUP_LINES_BY_PRODUCT') . '</STRONG> / <a href="' . $_SERVER['PHP_SELF'] . '?id=' . $id . '&group_lines_by_product=0' . $listParam . '">' . $langs->trans('DontGroupByProduct') . '</a>' . img_help(1, $langs->trans('GroupByProductHelp')) . '<br><br>';
	} else {
		print '<a href="' . $_SERVER['PHP_SELF'] . '?id=' . $id . '&group_lines_by_product=1' . $listParam . '">' . $langs->trans('SOFO_GROUP_LINES_BY_PRODUCT') . '</a> / <STRONG>' . $langs->trans('DontGroupByProduct') . '</STRONG>' . img_help(1, $langs->trans('GroupByProductHelp')) . '<br><br>';
	}

	print '<div style="text-align:right">	  </div>' .
		'<table class="liste" width="100%">';


	$colspan = 10; // desiredstock column always available on supported versions
	if (getDolGlobalString('FOURN_PRODUCT_AVAILABILITY'))
		$colspan++;
	if (getDolGlobalString('SOFO_USE_DELIVERY_TIME')) {
		$colspan++;
	}
	if (isModEnabled('categorie') && getDolGlobalString('SOFO_DISPLAY_CAT_COLUMN')) {
		$colspan++;
	}
	if (isModEnabled('service') && $type == 1) {
		$colspan++;
	}
	$colspan++;
	$parameters = array();
	$reshook = $hookmanager->executeHooks('printFieldPreListTitle', $parameters, $object, $action); // Note that $action and $object may have been modified by hook
	if (!empty($reshook)) {
		print $hookmanager->resPrint;
	}
	if (getDolGlobalString('SOFO_USE_DELIVERY_TIME')) {
		$week_to_replenish = GETPOSTINT('week_to_replenish');


		print '<tr class="liste_titre">' .
			'<td colspan="' . $colspan . '">' . $langs->trans('NbWeekToReplenish') . '<input type="text" name="week_to_replenish" value="' . $week_to_replenish . '" size="2"> '
			. '<input type="submit" value="' . $langs->trans('ReCalculate') . '" /></td>';

		print '</tr>';
	}

	if (isModEnabled('categorie')) {
		print '<tr class="liste_titre_filter">';
		print '<td colspan="2" >';
		print $langs->trans("Categories");
		print '</td>';
		print '<td colspan="' . ($colspan - 1) . '" >';
		print getCatMultiselect('categorie', $TCategories);
		print '<a id="clearfilter" href="javascript:;">' . $langs->trans('DeleteFilter') . '</a>';
		?>
		<script type="text/javascript">
			$('a#clearfilter').click(function () {
				$('option:selected', $('select#categorie')).prop('selected', false);
				$('option[value=-1]', $('select#categorie')).prop('selected', true);
				$('form[name=formulaire]').submit();
				return false;
			})
		</script>
		<?php
		print '</td>';
		print '</tr>';
	}


	$listParam .= '&fourn_id=' . $fourn_id . '&snom=' . $snom . '&salert=' . $salert;
	$listParam .= '&sref=' . $sref;
	$listParam .= '&group_lines_by_product=' . $groupLinesByProduct;

	// Lignes des titres
	print '<tr class="liste_titre_filter">' .
		'<th class="liste_titre"><input type="checkbox" onClick="toggle(this)" /></th>';
	print_liste_field_titre(
		$langs->trans('Ref'),
		'ordercustomer.php',
		'prod.ref',
		$listParam,
		'id=' . GETPOSTINT('id'),
		'',
		$sortfield,
		$sortorder
	);
	print_liste_field_titre(
		$langs->trans('Label'),
		'ordercustomer.php',
		'prod.label',
		$listParam,
		'id=' . GETPOSTINT('id'),
		'',
		$sortfield,
		$sortorder
	);
	if (isModEnabled('categorie') && getDolGlobalString('SOFO_DISPLAY_CAT_COLUMN')) {
		print_liste_field_titre(
			$langs->trans("Categories"),
			'ordercustomer.php',
			'cp.fk_categorie',
			$listParam,
			'id=' . GETPOSTINT('id'),
			'',
			$sortfield,
			$sortorder
		);
	}
	if (isModEnabled('service') && $type == 1) {
		print_liste_field_titre(
			$langs->trans('Duration'),
			'ordercustomer.php',
			'prod.duration',
			$listParam,
			'id=' . GETPOSTINT('id'),
			'align="center"',
			$sortfield,
			$sortorder
		);
	}

	print_liste_field_titre(
		$langs->trans('DesiredStock'),
		'ordercustomer.php',
		'prod.desiredstock',
		$listParam,
		'id=' . GETPOSTINT('id'),
		'align="right"',
		$sortfield,
		$sortorder
	);

	if (($week_to_replenish > 0 || getDolGlobalString('USE_VIRTUAL_STOCK') || getDolGlobalString('SOFO_USE_VIRTUAL_ORDER_STOCK') || !getDolGlobalString('SOFO_DO_NOT_USE_CUSTOMER_ORDER'))) {
		$stocklabel = $langs->trans('VirtualStock');
	} else {
		$stocklabel = $langs->trans('PhysicalStock');
	}
	print_liste_field_titre(
		$stocklabel,
		'ordercustomer.php',
		'stock_physique',
		$listParam,
		'id=' . GETPOSTINT('id'),
		'align="right"',
		$sortfield,
		$sortorder
	);

	print_liste_field_titre(
		$langs->trans('Diff'),
		'ordercustomer.php',
		'',
		$listParam,
		'',
		'align="right"',
		$sortfield,
		$sortorder
	);

	print_liste_field_titre(
		$langs->trans('Ordered'),
		'ordercustomer.php',
		'',
		$listParam,
		'id=' . GETPOSTINT('id'),
		'align="right"',
		$sortfield,
		$sortorder
	);
	if (getDolGlobalInt('SOFO_QTY_LINES_COMES_FROM_ORIGIN_ORDER_ONLY')) {
		print_liste_field_titre(
			$langs->trans('AlreadyShipped'),
			'ordercustomer.php',
			'',
			$listParam,
			'id=' . GETPOSTINT('id'),
			'align="right"',
			$sortfield,
			$sortorder
		);
	}
	print_liste_field_titre(
		$langs->trans('StockToBuy'),
		'ordercustomer.php',
		'',
		$listParam,
		'id=' . GETPOSTINT('id'),
		'align="right"',
		$sortfield,
		$sortorder
	);

	if (getDolGlobalString('FOURN_PRODUCT_AVAILABILITY'))
		print_liste_field_titre($langs->trans("Availability"));

	print_liste_field_titre(
		$langs->trans('Supplier'),
		'ordercustomer.php',
		'',
		$listParam,
		'id=' . GETPOSTINT('id'),
		'align="right"',
		$sortfield,
		$sortorder
	);

	print '<th class="liste_titre" >&nbsp;</th>';

	print '</tr>' .
		// Lignes des champs de filtre
		'<tr class="liste_titre_filter">' .
		'<td class="liste_titre">&nbsp;</td>' .
		'<td class="liste_titre">' .
		'<input class="flat" type="text" name="sref" value="' . $sref . '">' .
		'</td>' .
		'<td class="liste_titre">' .
		'<input class="flat" type="text" name="snom" value="' . $snom . '">';
	'</td>';

	if (isModEnabled('service') && $type == 1) {
		print '<td class="liste_titre">' .
			'&nbsp;' .
			'</td>';
	}

	$liste_titre = "";
	if (isModEnabled('categorie') && getDolGlobalString('SOFO_DISPLAY_CAT_COLUMN')) {
		$liste_titre .= '<td class="liste_titre">';
		$liste_titre .= '</td>';
	}

	$liste_titre .= '<td class="liste_titre">&nbsp;</td>';
	if (empty($alertchecked)) $alertchecked = '';
	$liste_titre .= '<td class="liste_titre" align="right">' . $langs->trans('AlertOnly') . '&nbsp;<input type="checkbox" name="salert" ' . $alertchecked . '></td>';

	$liste_titre .= '<td class="liste_titre" align="right"></td>';

	$liste_titre .= '<td class="liste_titre" align="right">&nbsp;</td>' .
		'<td class="liste_titre">&nbsp;</td>' .
		'<td class="liste_titre" ' . (getDolGlobalString('SOFO_USE_DELIVERY_TIME') ? 'colspan="2"' : '') . '>&nbsp;</td>' .
		'<td class="liste_titre" align="right">' .
		'<input type="image" class="liste_titre" name="button_search"' .
		'src="' . DOL_URL_ROOT . '/theme/' . $conf->theme . '/img/search.png" alt="' . $langs->trans("Search") . '">' .
		'<input type="image" class="liste_titre" name="button_removefilter"
          src="' . DOL_URL_ROOT . '/theme/' . $conf->theme . '/img/searchclear.png" value="' . dol_escape_htmltag($langs->trans("Search")) . '" title="' . dol_escape_htmltag($langs->trans("Search")) . '">' .
		'</td>' .
		'<td class="liste_titre" align="right">&nbsp;</td>' .
		'<td class="liste_titre" align="right">&nbsp;</td>' .
		'</tr>';

	print $liste_titre;

	$prod = new Product($db);

	$var = true;

	if (getDolGlobalString('SOFO_USE_DELIVERY_TIME')) {
		$form->load_cache_availability();
		$limit = 999999;
	}

	$TSupplier = array();
	$TProductIDAlreadyChecked = array();
	//On regroupe les quantités pas produit si la conf est active
	if (!empty($groupLinesByProduct) && !empty($TProducts)) {
		$TProductQtyChecked = array();
		$TProductSubLevel = array();
		foreach ($TProducts as $key => $objp) {
			if (empty($objp->level)) {
				if (array_key_exists($objp->rowid, $TProductQtyChecked)) {
					$TProductQtyChecked[$objp->rowid]->qty += $objp->qty;
				} else $TProductQtyChecked[$objp->rowid] = $objp;
			} else $TProductSubLevel[] = $objp;
		}

		$TProducts = array_merge($TProductQtyChecked, $TProductSubLevel);
	}

	foreach ($TProducts as $objp) {
		// Cas où on a plusieurs fois le même produit dans la même commande : dédoublonnage (les sous produits ne sont pas concernés)
		if (!empty($groupLinesByProduct) && empty($objp->level)) {
			if (in_array($objp->rowid, $TProductIDAlreadyChecked)) continue;
			else $TProductIDAlreadyChecked[$objp->rowid] = $objp->rowid;
		}

		if (getDolGlobalString('SOFO_DISPLAY_SERVICES') || $objp->fk_product_type == 0) {
			// Multilangs
			if (getDolGlobalString('MAIN_MULTILANGS')) {
				$sql = 'SELECT label';
				$sql .= ' FROM ' . $db->prefix() . 'product_lang';
				$sql .= ' WHERE fk_product = ' . $objp->rowid;
				$sql .= ' AND lang = "' . $langs->getDefaultLang() . '"';
				$sql .= ' LIMIT 1';

				$result = $db->query($sql);
				if ($result) {
					$objtp = $db->fetch_object($result);
					if (!empty($objtp->label)) {
						$objp->label = $objtp->label;
					}
				}
			}

			$prod->ref = $objp->ref;
			$prod->id = $objp->rowid;
			$prod->type = $objp->fk_product_type;
			$help_stock = $langs->trans('PhysicalStock') . ' : ' . (float) $objp->stock_physique;

			$stock_commande_client = 0;
			$stock_commande_fournisseur = 0;

			if ($week_to_replenish > 0) {
				/* là ça déconne pas, on s'en fout, on dépote ! */
				if (!getDolGlobalString('SOFO_DO_NOT_USE_CUSTOMER_ORDER')) {
					$stock_commande_client = loadStatsCommandeDate($prod->id, date('Y-m-d', strtotime('+' . $week_to_replenish . 'week')));
					$help_stock .= ', ' . $langs->trans('Orders') . ' : ' . (float) $stock_commande_client;
				}

				$stock_commande_fournisseur = loadStatsCommandeFournisseur($prod->id, date('Y-m-d', strtotime('+' . $week_to_replenish . 'week')), $objp->stock_physique - $stock_commande_client);
				$help_stock .= ', ' . $langs->trans('SupplierOrders') . ' : ' . (float) $stock_commande_fournisseur;

				$stock = $objp->stock_physique - $stock_commande_client + $stock_commande_fournisseur;
			} elseif (getDolGlobalString('USE_VIRTUAL_STOCK') || getDolGlobalString('SOFO_USE_VIRTUAL_ORDER_STOCK')) {
				//compute virtual stockshow_stock_no_need
				$prod->fetch($prod->id);
				if ((!getDolGlobalString('STOCK_CALCULATE_ON_VALIDATE_ORDER') || getDolGlobalString('SOFO_USE_VIRTUAL_ORDER_STOCK'))
					&& !getDolGlobalString('SOFO_DO_NOT_USE_CUSTOMER_ORDER')) {
					$result = $prod->load_stats_commande(0, '1,2');
					if ($result < 0) {
						dol_print_error($db, $prod->error);
					}
					$stock_commande_client = $prod->stats_commande['qty'];

					//si c'est un sous-produit, on ajoute la quantité à commander calculée plus tôt en plus
					if (!empty($objp->level)) $stock_commande_client = $stock_commande_client + $objp->qty;
				} else {
					$stock_commande_client = 0;
				}

				if (!getDolGlobalString('STOCK_CALCULATE_ON_SUPPLIER_VALIDATE_ORDER') || getDolGlobalInt('SOFO_USE_VIRTUAL_ORDER_STOCK')) {
					if (getDolGlobalString('SUPPLIER_ORDER_STATUS_FOR_VIRTUAL_STOCK')) {
						$result = $prod->load_stats_commande_fournisseur(0, getDolGlobalInt('SUPPLIER_ORDER_STATUS_FOR_VIRTUAL_STOCK', 1));
					} else {
						$result = $prod->load_stats_commande_fournisseur(0, '1,2,3,4', 1);
					}
					if ($result < 0) {
						dol_print_error($db, $prod->error);
					}

					//Requête qui récupère la somme des qty ventilés pour les cmd reçu partiellement
					$sqlQ = "SELECT SUM(rec.qty) as qty";
					if ((float) DOL_VERSION < 20) {
						$sqlQ .= " FROM " . $db->prefix() . "commande_fournisseur_dispatch as rec";
						$sqlQ .= " INNER JOIN " . $db->prefix() . "commande_fournisseur cf ON (cf.rowid = rec.fk_commande) AND cf.entity IN (" . getEntity('commande_fournisseur') . ")";
					} else {
						$sqlQ .= " FROM " . $db->prefix() . "receptiondet_batch as rec";
						$sqlQ .= " INNER JOIN " . $db->prefix() . "commande_fournisseur cf ON (cf.rowid = rec.fk_elementdet) AND cf.entity IN (" . getEntity('commande_fournisseur') . ")";
						$sqlQ .= " AND rec.element_type = 'supplier_order' ";
					}
					$sqlQ .= " LEFT JOIN " . $db->prefix() . 'entrepot as e ON rec.fk_entrepot = e.rowid AND e.entity IN (' . $entityToTest . ')';
					$sqlQ .= " WHERE cf.fk_statut = 4";
					$sqlQ .= " AND rec.fk_product = " . $prod->id;
					$sqlQ .= " ORDER BY rec.rowid ASC";
					$resqlQ = $db->query($sqlQ);

					$stock_commande_fournisseur = $prod->stats_commande_fournisseur['qty'];
					if ($resqlQ) {
						$row = $db->fetch_object($resqlQ);
						$stock_commande_fournisseur -= $row->qty;
					}
				} else {
					$stock_commande_fournisseur = 0;
				}

				if (isModEnabled('expedition')
					&& (getDolGlobalString('STOCK_CALCULATE_ON_SHIPMENT') || getDolGlobalString('STOCK_CALCULATE_ON_SHIPMENT_CLOSE'))) {
					require_once DOL_DOCUMENT_ROOT . '/expedition/class/expedition.class.php';
					$filterShipmentStatus = '';
					if (getDolGlobalString('STOCK_CALCULATE_ON_SHIPMENT')) {
						$filterShipmentStatus = Expedition::STATUS_VALIDATED . ',' . Expedition::STATUS_CLOSED;
					} elseif (getDolGlobalString('STOCK_CALCULATE_ON_SHIPMENT_CLOSE')) {
						$filterShipmentStatus = Expedition::STATUS_CLOSED;
					}
					$result = $prod->load_stats_sending(0, '1,2', 1, $filterShipmentStatus);
					if ($result < 0) dol_print_error($this->db, $this->error);
					$stock_sending_client = $prod->stats_expedition['qty'];
					$help_stock .= ', ' . $langs->trans('Expeditions') . ' : ' . (float) $stock_sending_client;
				} else $stock_sending_client = 0;

				if ($stock_commande_client > 0) {
					$help_stock .= ', ' . $langs->trans('Orders') . ' : ' . (float) $stock_commande_client;
				}

				$help_stock .= ', ' . $langs->trans('SupplierOrders') . ' : ' . (float) $stock_commande_fournisseur;

				$stock = $objp->stock_physique - $stock_commande_client + $stock_commande_fournisseur + $stock_sending_client;
			} else {
				if (!getDolGlobalString('SOFO_DO_NOT_USE_CUSTOMER_ORDER')) {
					$stock_commande_client = $objp->qty;
					$help_stock .= ', ' . $langs->trans('Orders') . ' : ' . (float) $stock_commande_client;
				}

				$stock = $objp->stock_physique - $stock_commande_client;
			}

			$ordered = $stock_commande_client;

			$warning = '';
			if (!empty($objp->seuil_stock_alerte)
				&& ($stock < $objp->seuil_stock_alerte)) {
				$warning = img_warning($langs->trans('StockTooLow')) . ' ';
			}

			// On regarde s'il existe une demande de prix en cours pour ce produit
			$TDemandes = array();


			if (isModEnabled('supplier_proposal')) {
				$q = 'SELECT a.ref
											FROM ' . $db->prefix() . 'supplier_proposal a
											INNER JOIN ' . $db->prefix() . 'supplier_proposaldet d on (d.fk_supplier_proposal=a.rowid)
											WHERE a.fk_statut = 1
											AND d.fk_product = ' . $prod->id;

				$qres = $db->query($q);

				while ($res = $db->fetch_object($qres))
					$TDemandes[] = $res->ref;
			}

			// La quantité à commander correspond au stock désiré sur le produit additionné à la quantité souhaitée dans la commande :
			$stocktobuy = $objp->desiredstock - $stock;

			$help_stock .= ', ' . $langs->trans('DesiredStock') . ' : ' . (float) $objp->desiredstock;

			if ($stocktobuy < 0) {
				$stocktobuy = 0;
				$objnottobuy = $objp->rowid;
			}

			//si le produit parent n'a pas besoin d'être commandé, alors les produits fils non plus
			if (empty($objp->fk_parent)) $objp->fk_parent = 0;
			if (!empty($objnottobuy) && !empty($objp->fk_parent) && $objnottobuy == $objp->fk_parent) {
				$stocktobuy = 0;
			}

			if ((empty($prod->type) && $stocktobuy == 0 && GETPOST('show_stock_no_need', 'none') != 'yes')
				|| ($prod->type == 1 && $stocktobuy == 0 && GETPOST('show_stock_no_need', 'none') != 'yes' && getDolGlobalString('STOCK_SUPPORTS_SERVICES'))) {
				$i++;
				continue;
			}

			// on load les commandes fournisseur liées
			$id = GETPOSTINT('id');
			if (!empty($objp->lineid)) {
				$objLineNewQty = TSOFO::getAvailableQty($objp->lineid, !empty($groupLinesByProduct) ? $ordered : $objp->qty);
			}

			$var = !$var;

			/**
			 *  passage en conf si demande client
			 *
			 *  $checked = ($objLineNewQty->qty > 0) ? ' checked' : '';
			 *    $disabled = ($objLineNewQty->qty  ==  0)  ? 'disabled' : '';
			 */
			$checked = '';
			$disabled = '';
			if (getDolGlobalString('SOFO_QTY_LINES_COMES_FROM_ORIGIN_ORDER_ONLY')) {
				$checked = (!empty($objLineNewQty->qty) && $objLineNewQty->qty > 0) ? ' checked' : '';
				$disabled = (!empty($objLineNewQty->qty) && $objLineNewQty->qty == 0) ? 'disabled' : '';
			}

			print '<tr ' . $bc[$var] . ' data-productid="' . $objp->rowid . '"  data-i="' . $i . '"   >
						<td>
							<input type="checkbox" class="check" name="check' . $i . '" ' . $disabled . ' ' . $checked . '>';

			$lineid = '';

			if (!empty($objp->lineid) && strpos($objp->lineid, '@') === false) { // Une seule ligne d'origine
				$lineid = $objp->lineid;
			}

			print '<input type="hidden" name="lineid' . $i . '" value="' . $lineid . '" />';
			if (getDolGlobalString('SUPPORDERFROMORDER_USE_ORDER_DESC')) {
				print '<input type="hidden" name="desc' . $i . '" value="' . (isset($objp->description) ? htmlentities($objp->description, ENT_QUOTES) : '') . '" >';
			}

			if (getDolGlobalString('SOFO_CREATE_NEW_SUPPLIER_ODER_WITH_PRODUCT_DESC')) {
				$produit = new Product($db);
				$produit->fetch($objp->rowid);

				$description = $produit->description;
				if ($description) print '<input type="hidden" name="desc' . $i . '" value="' . $description . '" >';
			}
			print '</td>';

			print '<td  style="height:35px;" class="nowrap">';
			//affichage des indentations suivant le niveau de sous-produit
			if (!empty($objp->level)) {
				$k = 0;
				while ($k < $objp->level) {
					print img_picto("Auto fill", 'rightarrow');
					$k++;
				}
			}
			if (!empty($TDemandes)) {
				print $form->textwithpicto($prod->getNomUrl(1), 'Demande(s) de prix en cours :<br />' . implode(', ', $TDemandes), 1, 'help');
			} else {
				print $prod->getNomUrl(1);
			}
			print '</td>';

			// on check si une cmd fourn existe pour ce produit et on affiche la ref avec link
			$TcurrentCmdFourn = TSOFO::getCmdFournFromCmdCustomer($objp->lineid ?? null, $objp->rowid);
			$r = '';

			if (!empty($TcurrentCmdFourn)) {
				foreach ($TcurrentCmdFourn as $currentCmdFourn) {
					$r .= '<br>' . $currentCmdFourn->getNomUrl(1);
				}
			}

			print '<td>' . $objp->label . $r . '</td>';

			if (isModEnabled('categorie') && getDolGlobalString('SOFO_DISPLAY_CAT_COLUMN')) {
				print '<td >';
				$categorie = new Categorie($db);
				$Tcategories = $categorie->containing($objp->rowid, 'product', 'label');
				print implode(', ', $Tcategories);
				print '</td>';
			}

			if (isModEnabled('service') && $type == 1) {
				if (preg_match('/([0-9]+)y/i', $objp->duration, $regs)) {
					$duration = $regs[1] . ' ' . $langs->trans('DurationYear');
				} elseif (preg_match('/([0-9]+)m/i', $objp->duration, $regs)) {
					$duration = $regs[1] . ' ' . $langs->trans('DurationMonth');
				} elseif (preg_match('/([0-9]+)d/i', $objp->duration, $regs)) {
					$duration = $regs[1] . ' ' . $langs->trans('DurationDay');
				} else {
					$duration = $objp->duration;
				}

				print '<td align="center">' .
					$duration .
					'</td>';
			}

			$champs = "";
			$champs .= '<td align="right">' . $objp->desiredstock . '</td>';
			$prod->load_stock();
			$champs .= '<td align="right" >' .
				$warning . (((getDolGlobalString('STOCK_SUPPORTS_SERVICES') && $prod->type == 1) || empty($prod->type)) ? $prod->stock_theorique : img_picto('', './img/no', '', 1)) . //$stocktobuy
				'</td>';

			// déjà present
			$champs .= '<td align="right">' .
				($objLineNewQty->oldQty ?? '') .
				'</td>';
			//Commandé
			$champs .= '<td align="right">';
			$champs .= $objp->qty;
			$champs .= '</td>';
			if (getDolGlobalString('SOFO_QTY_LINES_COMES_FROM_ORIGIN_ORDER_ONLY')) {
				// Déja expédié
				$qty_shipped_to_print = $objp->qty_shipped > 0 ? $objp->qty_shipped : '0';
				$champs .= '<td align="right">' . $qty_shipped_to_print . '</td>';
			}
			// A commander
			$stocktobuy = ($objp->qty - $objp->qty_shipped) > 0 ? ($objp->qty - $objp->qty_shipped) : 0;
			$champs .= '<td align="right">' .
				'<input type="text" name="tobuy' . $i .
				'" value="' . (getDolGlobalString('SOFO_QTY_LINES_COMES_FROM_ORIGIN_ORDER_ONLY') ? $stocktobuy : (empty($groupLinesByProduct) ? $objp->qty : $objLineNewQty->qty ?? 0)) . '" ' . $disabled . ' size="3"> </td>';
			$champs .= '<td align="center">' .
				'<span class="stock_details" prod-id="' . $prod->id . '" week-to-replenish="' . $week_to_replenish . '">' . img_help(1, $help_stock) . '</span>';
			$champs .= '</td>';

			if (getDolGlobalString('SOFO_USE_DELIVERY_TIME')) {
				$nb_day = (int) getMinAvailability($objp->rowid, $stocktobuy);

				$champs .= '<td data-info="availability" >' . ($nb_day == 0 ? $langs->trans('Unknown') : $nb_day . ' ' . $langs->trans('Days')) . '</td>';
			}

			$selectedPrice = !empty($objp->buy_price_ht) && $objp->buy_price_ht > 0 ? $objp->buy_price_ht : 0;

			$champs .= '<td align="right" data-info="fourn-price" >' .
				TSOFO::selectProductFournPrice($prod->id, 'fourn' . $i, $selectedSupplier, $selectedPrice) .
				'</td>';
			print $champs;

			if (empty($TSupplier))
				$TSupplier = $prod->list_suppliers();
			else $TSupplier = array_intersect($prod->list_suppliers(), $TSupplier);

			print '<td>&nbsp</td>';

			print '</tr>';

			if (empty($fk_commande))
				$TCachedProductId[] = $prod->id; //mise en cache
		}
		$i++;
	}

	//Lignes libre
	$j = $j ?? 0;
	if (!empty($resql2)) {
		while ($j < min($num2, $limit)) {
			$objp = $db->fetch_object($resql2);
			if ($objp->product_type == 0)
				$picto = img_object($langs->trans("ShowProduct"), 'product');
			if ($objp->product_type == 1)
				$picto = img_object($langs->trans("ShowService"), 'service');

			print '<tr ' . $bc[$var] . '>' .
				'<td><input type="checkbox" class="check" name="check' . $i . '"' . ($disabled ?? '') . '></td>' .
				'<td>' .
				$picto . " " . $objp->description .
				'</td>' .
				'<td>' . $objp->description;
			$picto = img_picto('', './img/no', '', 1);

			print '<input type="hidden" name="desc' . $i . '" value="' . $objp->description . '" />';
			print '<input type="hidden" name="product_type' . $i . '" value="' . $objp->product_type . '" >';

			print '</td>';

			print '<td></td>'; // Nature
			if (isModEnabled('categorie'))
				print '<td></td>'; // Categories

			if (isModEnabled('service') && $type == 1) {
				if (preg_match('/([0-9]+)y/i', $objp->duration, $regs)) {
					$duration = $regs[1] . ' ' . $langs->trans('DurationYear');
				} elseif (preg_match('/([0-9]+)m/i', $objp->duration, $regs)) {
					$duration = $regs[1] . ' ' . $langs->trans('DurationMonth');
				} elseif (preg_match('/([0-9]+)d/i', $objp->duration, $regs)) {
					$duration = $regs[1] . ' ' . $langs->trans('DurationDay');
				} else {
					$duration = $objp->duration;
				}
				print '<td align="center">' .
					$duration .
					'</td>';
			}

			print '<td align="right">' . $picto . '</td>'; // Desired stock
			print '<td align="right">' . $picto . '</td>'; // Physical/virtual stock

			print '<td align="right">
						<input type="text" name="tobuy_free' . $i . '" value="' . $objp->qty . '">
						<input type="hidden" name="lineid_free' . $i . '" value="' . $objp->rowid . '" >
					</td>'; // Ordered
			print '<td align="right" id="test">
							<input type="text" name="tobuy_free' . $i . '" value="' . $objp->qty_shipped . '">
							<input type="hidden" name="lineid_free' . $i . '" value="' . $objp->rowid . '" >
						</td>'; // OrderShipped
			print '<td align="right">
						<input type="text" name="price_free' . $i . '" value="' . (!getDolGlobalString('SOFO_COST_PRICE_AS_BUYING') ? $objp->price : price($objp->buy_price_ht)) . '" size="5" style="text-align:right">€
						' . $form->select_company((empty($socid) ? '' : $socid), 'fourn_free' . $i, 's.fournisseur = 1', 1, 0, 0, array(), 0, 'minwidth100 maxwidth300') . '
				   </td>'; // Supplier
			print '<td></td>'; // Action
			print '</tr>';
			$i++;
			$j++;
		}
	}

	// Formatage du tableau
	$TCommonSupplier = array();
	foreach ($TSupplier as $fk_fourn) {
		if (!isset($TCommonSupplier[0]))
			$TCommonSupplier[0] = '';

		$fourn = new Fournisseur($db);
		$fourn->fetch($fk_fourn);

		$TCommonSupplier[$fk_fourn] = $fourn->name;
	}

	print '</table>' .
		'<table width="100%" style="margin-top:15px;">';
	print '<tr>';
	print '<td align="right">';

	print $langs->trans('SelectSameSupplier') . ' :&nbsp;';
	if (empty($TCommonSupplier)) {
		print '<a class="butActionRefused" href="javascript:" title="' . $langs->trans('NoCommonSupplier') . '">' . $langs->trans('Apply') . '</a>';
	} else {
		print $form->selectarray('useSameSupplier', $TCommonSupplier);
		print '<button type="submit" class="butAction">' . $langs->trans('Apply') . '</button>';
	}

	print '</td>';
	print '</tr>';
	print '<tr><td>&nbsp;</td></tr>';
	print '<tr><td align="right">' .
		'<button class="butAction" type="submit" name="action" value="valid-propal">' . $langs->trans("GenerateSupplierPropal") . '</button>' .
		'<button class="butAction" type="submit" name="action" value="valid-order">' . $langs->trans("GenerateSupplierOrder") . '</button>' .
		'<button class="butAction" type="submit" name="action" value="view-valid-order">' . $langs->trans("ViewSupplierOrderGenerated") . '</button>' .
		'</td></tr></table>' .
		'</form>';


	$db->free($resql);
	print ' <script type="text/javascript">';


	if (getDolGlobalInt('SOFO_USE_DELIVERY_TIME')) {
		print '
	$( document ).ready(function() {
		//console.log( "ready!" );

		$("[data-info=\'fourn-price\'] select").on("change", function() {
		    var productid = $(this).closest( "tr[data-productid]" ).attr( "data-productid" );
		    var rowi = $(this).closest( "tr[data-productid]" ).attr( "data-i" );
			if ( productid.length ) {
				var fk_price = $(this).val();
				var stocktobuy = $("[name=\'tobuy" + rowi +"\']" ).val();

				var targetUrl = "' . dol_buildpath('/supplierorderfromorder/script/interface.php', 2) . '?get=availability&stocktobuy=" + stocktobuy + "&fk_product=" + productid + "&fk_price=" + fk_price ;

				$.get( targetUrl, function( data ) {
				  	$("tr[data-productid=\'" + productid + "\'] [data-info=\'availability\']").html( data );
				});


			}
		});

	});
	';
	}

	print ' function toggle(source)
     {
       var checkboxes = document.getElementsByClassName("check");
       for (var i=0; i < checkboxes.length;i++) {
         if (!checkboxes[i].disabled) {
            checkboxes[i].checked = source.checked;
        }
       }
     } </script>';


	dol_fiche_end();
} else {
	dol_print_error($db);
}

?>
	<script type="text/javascript">
		var mouseX;
		var mouseY;

		$(document).ready(function () {

			$('body').append('<div id="pop-stock" style="border:2px orange solid; position: absolute;width:300px;display:none;padding:10px;background-color:#fff;"></div>');

			$(document).mousemove(function (e) {
				mouseX = e.pageX;
				mouseY = e.pageY;
			});

			$('span.stock_details').each(function (i, item) {

				var prodid = $(this).attr('prod-id');
				var nbweek = $(this).attr('week-to-replenish');

				$(this).mouseover(function () {
					$('#pop-stock').html('Chargement...');
					$('#pop-stock').css({'top': mouseY + 20, 'left': mouseX - 320}).show();

					$.ajax({
						url: "<?php echo dol_buildpath('/supplierorderfromorder/script/interface.php', 1) ?>"
						, data: {
							get: 'stock-details'
							, idprod: prodid
							, nbweek: nbweek
						}
					}).done(function (data) {
						$('#pop-stock').html(data);
					});

				});

				$(this).mouseout(function () {
					$('#pop-stock').hide();
				});

			});

		});
	</script>
<?php
//Debugbar is making page loading non stop
if ($user->hasRight('debugbar', 'read')) {
	$saveRight = $user->hasRight('debugbar', 'read');
	$user->rights->debugbar->read = 0;
}
llxFooter();
if (!empty($saveRight)) $user->rights->debugbar->read = $saveRight;

/**
 * Prepare supplier order/proposal lines from submitted form data.
 *
 * @param int    $i           Line index in form.
 * @param string $actionTarget Target type ('order' or 'propal').
 *
 * @return void
 */
function _prepareLine($i, $actionTarget = 'order')
{
	global $db, $suppliers, $box, $conf;

	if ($actionTarget == 'propal') {
		$line = new SupplierProposalLine($db);
	} else {
		$line = new CommandeFournisseurLigne($db); //$actionTarget = 'order'
	}

	//Lignes de produit
	if (!GETPOST('tobuy_free' . $i, 'none')) {
		$box = $i;
		$supplierpriceid = GETPOSTINT('fourn' . $i);

		// get all the parameters needed to create a line
		$qty    = price2num(GETPOST('tobuy' . $i, 'alphanohtml'));
		$desc   = GETPOST('desc' . $i, 'alpha');
		$lineid = GETPOSTINT('lineid' . $i);
		$array_options = array();

		if (!empty($lineid)) {
			$commandeline = new OrderLine($db);
			$commandeline->fetch($lineid);
			if (!getDolGlobalString('SOFO_DONT_ADD_LINEDESC_ON_SUPPLIERORDER_LINE'))
				$desc = $commandeline->desc;
			if (empty($commandeline->id) && !empty($commandeline->rowid)) {
				$commandeline->id = $commandeline->rowid; // Pas positionné par OrderLine::fetch() donc le fetch_optionals() foire...
			}

			if (empty($commandeline->array_options) && method_exists($commandeline, 'fetch_optionals')) {
				$commandeline->fetch_optionals();
			}

			$array_options = $commandeline->array_options;

			$line->origin = 'commande';
			$line->origin_id = $commandeline->id;
		}

		$obj = _getSupplierPriceInfos($supplierpriceid);

		if ($obj) {
			$line->id = $lineid;
			$line->qty = $qty;
			$line->desc = $desc;
			$line->fk_product = $obj->fk_product;
			$line->tva_tx = $obj->tva_tx;
			$line->subprice = $obj->unitprice;
			$line->total_ht = $obj->unitprice * $qty;
			$tva = $line->tva_tx / 100;
			$line->total_tva = $line->total_ht * $tva;
			$line->total_ttc = $line->total_ht + $line->total_tva;
			$line->ref_fourn = $obj->ref_fourn;
			$line->remise_percent = $obj->remise_percent;
			if (empty($line->remise_percent) && !empty($obj->remise_supplier)) $line->remise_percent = $obj->remise_supplier;
			// FIXME: Ugly hack to get the right purchase price since supplier references can collide
			// (eg. same supplier ref for multiple suppliers with different prices).
			$line->fk_prod_fourn_price = $supplierpriceid;
			$line->array_options = $array_options;

			// Ajout seulement si une qty a été saisie
			if (!empty($_REQUEST['tobuy' . $i]) && $qty > 0) {
				$suppliers[$obj->fk_soc]['lines'][] = $line;
			}
		} else {
			$error = $db->lasterror();
			dol_print_error($db);
			dol_syslog('replenish.php: ' . $error, LOG_ERR);
		}
		$db->free();
		unset($_POST['fourn' . $i]);
	} else { //Lignes libres
		$box = $i;
		$qty = price2num(GETPOST('tobuy_free' . $i, 'alphanohtml'));
		$desc = GETPOST('desc' . $i, 'alpha');
		$product_type = GETPOSTINT('product_type' . $i);
		$price = price2num(GETPOST('price_free' . $i, 'none'));
		$lineid = GETPOSTINT('lineid_free' . $i);
		$fournid = GETPOSTINT('fourn_free' . $i);
		$commandeline = new OrderLine($db);
		$commandeline->fetch($lineid);
		if (!getDolGlobalString('SOFO_DONT_ADD_LINEDESC_ON_SUPPLIERORDER_LINE'))
			$desc = $commandeline->desc;

		if (empty($commandeline->id) && !empty($commandeline->rowid)) {
			$commandeline->id = $commandeline->rowid; // Pas positionné par OrderLine::fetch() donc le fetch_optionals() foire...
		}

		if (empty($commandeline->array_options) && method_exists($commandeline, 'fetch_optionals')) {
			$commandeline->fetch_optionals();
		}

		$line->qty = $qty;
		$line->desc = $desc;
		$line->product_type = $product_type;
		$line->tva_tx = $commandeline->tva_tx;
		$line->subprice = $price;
		$line->total_ht = $price * $qty;
		$tva = $line->tva_tx / 100;
		$line->total_tva = $line->total_ht * $tva;
		$line->total_ttc = $line->total_ht + $line->total_tva;
		//$line->ref_fourn = $obj->ref_fourn;
		$line->remise_percent = $commandeline->remise_percent;
		$line->array_options = $array_options;


		unset($_POST['fourn_free' . $i]);

		if (!empty($_REQUEST['tobuy_free' . $i]) && $qty > 0) {
			$suppliers[$fournid]['lines'][] = $line;
		}
	}
}

/**
 * Get supplier price informations for given supplier price id.
 *
 * @param int $supplierpriceid Supplier price rowid.
 *
 * @return stdClass|false
 */
function _getSupplierPriceInfos($supplierpriceid)
{
	global $db;
	$sql = 'SELECT pfp.fk_product, pfp.fk_soc, pfp.ref_fourn';
	$sql .= ', pfp.tva_tx, pfp.unitprice, pfp.remise_percent, soc.remise_supplier FROM ';
	$sql .= $db->prefix() . 'product_fournisseur_price pfp';
	$sql .= ' LEFT JOIN ' . $db->prefix() . 'societe soc ON (soc.rowid = pfp.fk_soc)';
	$sql .= ' WHERE pfp.rowid = ' . $supplierpriceid;
	$resql = $db->query($sql);

	if ($resql && $db->num_rows($resql) > 0) {
		//might need some value checks
		return $db->fetch_object($resql);
	}

	return false;
}

/**
 * Get last draft supplier order for a supplier (optionally filtered by project).
 *
 * @param int    $idsupplier Supplier thirdparty id.
 * @param string $projectid  Project id filter.
 *
 * @return stdClass|false
 */
function _getSupplierOrderInfos($idsupplier, $projectid = '')
{
	global $db, $conf;

	$sql = 'SELECT rowid, ref';
	$sql .= ' FROM ' . $db->prefix() . 'commande_fournisseur';
	$sql .= ' WHERE fk_soc = ' . $idsupplier;
	$sql .= ' AND fk_statut = 0'; // 0 = DRAFT (Brouillon)

	if (getDolGlobalString('SOFO_DISTINCT_ORDER_BY_PROJECT') && !empty($projectid)) {
		$sql .= ' AND fk_projet = ' . $projectid;
	}

	$sql .= ' AND entity IN(' . getEntity('commande_fournisseur') . ')';
	$sql .= ' ORDER BY rowid DESC';
	$sql .= ' LIMIT 1';

	$resql = $db->query($sql);

	if ($resql && $db->num_rows($resql) > 0) {
		//might need some value checks
		return $db->fetch_object($resql);
	}

	return false;
}

/**
 * Get last draft supplier proposal for a supplier (optionally filtered by project).
 *
 * @param int    $idsupplier Supplier thirdparty id.
 * @param string $projectid  Project id filter.
 *
 * @return stdClass|false
 */
function _getSupplierProposalInfos($idsupplier, $projectid = '')
{
	global $db, $conf;

	$sql = 'SELECT rowid, ref';
	$sql .= ' FROM ' . $db->prefix() . 'supplier_proposal';
	$sql .= ' WHERE fk_soc = ' . $idsupplier;
	$sql .= ' AND fk_statut = 0'; // 0 = DRAFT (Brouillon)

	if (getDolGlobalString('SOFO_DISTINCT_ORDER_BY_PROJECT') && !empty($projectid)) {
		$sql .= ' AND fk_projet = ' . $projectid;
	}

	$sql .= ' AND entity IN(' . getEntity('supplier_proposal') . ')';
	$sql .= ' ORDER BY rowid DESC';
	$sql .= ' LIMIT 1';

	$resql = $db->query($sql);

	if ($resql && $db->num_rows($resql) > 0) {
		//might need some value checks
		return $db->fetch_object($resql);
	}

	return false;
}

/**
 * Apply payment/term/multicurrency/extrafields/ref_supplier rules
 * from supplier and/or customer order to supplier order.
 *
 * @param CommandeFournisseur $order          Supplier order.
 * @param Commande            $commandeClient Customer order.
 *
 * @return void
 */
function _appliCond($order, $commandeClient)
{
	global $db, $conf;

	$fourn = new Fournisseur($db);
	if ($fourn->fetch($order->socid) > 0) {
		// Multidevise
		if (isModEnabled('multicurrency')) {
			require_once DOL_DOCUMENT_ROOT . '/multicurrency/class/multicurrency.class.php';

			if (!empty($fourn->multicurrency_code)) {
				$tmparray = MultiCurrency::getIdAndTxFromCode($db, $fourn->multicurrency_code, dol_now());
				$order->multicurrency_code = $fourn->multicurrency_code;
				$order->fk_multicurrency = $tmparray[0];
				$order->multicurrency_tx = $tmparray[1];
			}
		}
		if (getDolGlobalString('SOFO_GET_INFOS_FROM_FOURN')) {
			$order->mode_reglement_id = $fourn->mode_reglement_supplier_id;
			$order->mode_reglement_code = getPaiementCode($order->mode_reglement_id);

			$order->cond_reglement_id = $fourn->cond_reglement_supplier_id;
			$order->cond_reglement_code = getPaymentTermCode($order->cond_reglement_id);
		}
	}

	if (getDolGlobalString('SOFO_GET_INFOS_FROM_ORDER')) {
		$order->mode_reglement_code = $commandeClient->mode_reglement_code;
		$order->mode_reglement_id = $commandeClient->mode_reglement_id;
		$order->cond_reglement_id = $commandeClient->cond_reglement_id;
		$order->cond_reglement_code = $commandeClient->cond_reglement_code;
		$order->delivery_date = $commandeClient->delivery_date;
	}

	if (getDolGlobalString('SOFO_GET_EXTRAFIELDS_FROM_ORDER')) {
		require_once DOL_DOCUMENT_ROOT . '/core/class/extrafields.class.php';
		$extrafieldsOrderSupplier = new ExtraFields($db);
		$extrafieldsOrderSupplier->fetch_name_optionals_label($order->table_element);
		$extrafieldsCustomerOrder = new ExtraFields($db);
		$extrafieldsCustomerOrder->fetch_name_optionals_label($commandeClient->table_element);

		if (!empty($commandeClient->array_options) && !empty($extrafieldsOrderSupplier->attributes)
			&& array_key_exists($order->table_element, $extrafieldsOrderSupplier->attributes)
			&& array_key_exists('label', $extrafieldsOrderSupplier->attributes[$order->table_element])
			&& !empty($extrafieldsOrderSupplier->attributes[$order->table_element]['label'])) {
			foreach ($commandeClient->array_options as $key => $val) {
				$key = str_replace('options_', '', $key);
				if (array_key_exists($key, $extrafieldsOrderSupplier->attributes[$order->table_element]['type']) &&
					$extrafieldsOrderSupplier->attributes[$order->table_element]['type'][$key] == $extrafieldsCustomerOrder->attributes[$commandeClient->table_element]['type'][$key]) {
					$order->array_options['options_' . $key] = $commandeClient->array_options['options_' . $key];
				}
			}
		}
	}

	if (getDolGlobalString('SOFO_GET_REF_SUPPLIER_FROM_ORDER') && !empty($commandeClient->ref_client)) {
		$order->ref_supplier = $commandeClient->ref_client;
	}
}

$db->close();

/**
 * Get ids of all child categories of given category (recursive).
 *
 * @param Categorie $cat Category object (modified by recursion).
 *
 * @return array
 */
function get_categs_enfants(&$cat)
{

	$TCat = array();

	$filles = $cat->get_filles();
	if (!empty($filles)) {
		foreach ($filles as &$cat_fille) {
			$TCat[] = $cat_fille->id;

			get_categs_enfants($cat_fille);
		}
	}

	return $TCat;
}
