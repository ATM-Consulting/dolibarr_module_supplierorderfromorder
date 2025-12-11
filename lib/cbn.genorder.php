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

//orders creation
//FIXME: could go in the lib
if ($action == 'order' && isset($_POST['valid'])) {
	global $db;
	$linecount = GETPOST('linecount', 'int');
	$box = false;
	unset($_POST['linecount']);
	if ($linecount > 0) {
		$suppliers = array();
		//var_dump($linecount);exit;
		for ($i = 0; $i < $linecount; $i++) {
			if (GETPOST('check'.$i, 'alpha') === 'on' && (GETPOST('fourn' . $i, 'int') > 0 || GETPOST('fourn_free' . $i, 'int') > 0)) { //one line
				//echo GETPOST('tobuy_free'.$i, 'none').'<br>';
				//Lignes de produit
				if (!GETPOST('tobuy_free'.$i, 'none')) {
					$box = $i;
					$supplierpriceid = GETPOST('fourn'.$i, 'int');
					//get all the parameters needed to create a line
					$qty = GETPOST('tobuy'.$i, 'int');
					$desc = GETPOST('desc'.$i, 'alpha');

					$sql = 'SELECT fk_product, fk_soc, ref_fourn';
					$sql .= ', tva_tx, unitprice, remise_percent FROM ';
					$sql .= $db->prefix() . 'product_fournisseur_price';
					$sql .= ' WHERE rowid = ' . $supplierpriceid;

					$resql = $db->query($sql);

					if ($resql && $db->num_rows($resql) > 0) {
						//might need some value checks
						$obj = $db->fetch_object($resql);
						$line = new CommandeFournisseurLigne($db);
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
						// FIXME: Ugly hack to get the right purchase price since supplier references can collide
						// (eg. same supplier ref for multiple suppliers with different prices).
						$line->fk_prod_fourn_price = $supplierpriceid;

						if (!empty($_REQUEST['tobuy'.$i])) {
							$suppliers[$obj->fk_soc]['lines'][] = $line;
						}
					} else {
						$error=$db->lasterror();
						dol_print_error($db);
						dol_syslog('replenish.php: '.$error, LOG_ERR);
					}
					$db->free($resql);
					unset($_POST['fourn' . $i]);
				} else {
					//Lignes libres
					//echo 'ok<br>';
					$box = $i;
					$qty = GETPOST('tobuy_free'.$i, 'int');
					$desc = GETPOST('desc'.$i, 'alpha');
					$product_type = GETPOST('product_type'.$i, 'int');
					$price = price2num(GETPOST('price_free'.$i, 'none'));
					$lineid = GETPOST('lineid_free'.$i, 'int');
					$fournid = GETPOST('fourn_free'.$i, 'int');
					$commandeline = new OrderLine($db);
					$commandeline->fetch($lineid);

					$line = new CommandeFournisseurLigne($db);
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

					if (!empty($_REQUEST['tobuy_free'.$i])) {
						$suppliers[$fournid]['lines'][] = $line;
					}
					unset($_POST['fourn_free' . $i]);
				}
			}
			unset($_POST[$i]);
		}

		//we now know how many orders we need and what lines they have
		$i = 0;
		$nb_orders_created = 0;
		$orders = array();
		$suppliersid = array_keys($suppliers);
		$projectid = GETPOST('projectid', 'int');
		foreach ($suppliers as $idsupplier => $supplier) {
			$sql2 = 'SELECT rowid, ref';
			$sql2 .= ' FROM ' . $db->prefix(). 'commande_fournisseur';
			$sql2 .= ' WHERE fk_soc = '.$idsupplier;
			$sql2 .= ' AND fk_statut = 0'; // 0 = DRAFT (Brouillon)
			if (getDolGlobalString('SOFO_DISTINCT_ORDER_BY_PROJECT') && !empty($projectid)) {
				$sql2 .= ' AND fk_projet = '.$projectid;
			}

			$sql2 .= ' AND entity IN('.getEntity('commande_fournisseur').')';
			$sql2 .= ' ORDER BY rowid DESC';
			$sql2 .= ' LIMIT 1';

			$res = $db->query($sql2);
			$obj = $db->fetch_object($res);

			$commandeClient = new Commande($db);
			$commandeClient->fetch($_REQUEST['id']);

			// Test recupération contact livraison
			if ( getDolGlobalString('SUPPLIERORDER_FROM_ORDER_CONTACT_DELIVERY')) {
				$contact_ship = $commandeClient->getIdContact('external', 'SHIPPING');
				$contact_ship=$contact_ship[0];
			} else {$contact_ship=null;}
			//Si une commande au statut brouillon existe déjà et que l'option SOFO_CREATE_NEW_SUPPLIER_ODER_ANY_TIME
			if ($obj && empty(getDolGlobalString('SOFO_CREATE_NEW_SUPPLIER_ODER_ANY_TIME')) ) {
				$order = new CommandeFournisseur($db);
				$order->fetch($obj->rowid);
				$order->socid = $idsupplier;
				//              var_dump($obj,$order);exit;
				if (!empty($projectid)) {
					$order->fk_project = GETPOST('projectid', 'int');
				}
				// On vérifie qu'il n'existe pas déjà un lien entre la commande client et la commande fournisseur dans la table element_element.
				// S'il n'y en a pas, on l'ajoute, sinon, on ne l'ajoute pas
				$order->fetchObjectLinked('', 'commande', $order->id, 'order_supplier');

				//if(count($order->linkedObjects) == 0) {

					$order->add_object_linked('commande', $_REQUEST['id']);

				//}
				if ( getDolGlobalString('SOFO_GET_INFOS_FROM_ORDER')) {
					$order->mode_reglement_code = $commandeClient->mode_reglement_code;
					$order->mode_reglement_id = $commandeClient->mode_reglement_id;
					$order->cond_reglement_id = $commandeClient->cond_reglement_id;
					$order->cond_reglement_code = $commandeClient->cond_reglement_code;
					$order->delivery_date = $commandeClient->delivery_date;
				}
				$id++; //$id doit être renseigné dans tous les cas pour que s'affiche le message 'Vos commandes ont été générées'
				$newCommande = false;
			} else {
								/*echo '<pre>';
				print_r($commandeClient);exit;*/

				$order = new CommandeFournisseur($db);
				$order->socid = $idsupplier;
				if (!empty($projectid)) {
					$order->fk_project = $projectid;
				}
				if ( getDolGlobalString('SOFO_GET_INFOS_FROM_ORDER')) {
					$order->mode_reglement_code = $commandeClient->mode_reglement_code;
					$order->mode_reglement_id = $commandeClient->mode_reglement_id;
					$order->cond_reglement_id = $commandeClient->cond_reglement_id;
					$order->cond_reglement_code = $commandeClient->cond_reglement_code;
					$order->delivery_date = $commandeClient->delivery_date;
				}

				$id = $order->create($user);
				if ($contact_ship && getDolGlobalString('SUPPLIERORDER_FROM_ORDER_CONTACT_DELIVERY')) $order->add_contact($contact_ship, 'SHIPPING');
				$order->add_object_linked('commande', $_REQUEST['id']);
				$newCommande = true;

				$nb_orders_created++;
			}
			$order_id = $order->id;
			//trick to know which orders have been generated this way
			$order->source = 42;

			foreach ($supplier['lines'] as $line) {
				$done = false;

				$prodfourn = new ProductFournisseur($db);
				$prodfourn->fetch_product_fournisseur_price($_REQUEST['fourn'.$i]);

				foreach ($order->lines as $lineOrderFetched) {
					if ($line->fk_product == $lineOrderFetched->fk_product) {
						$remise_percent = $lineOrderFetched->remise_percent;
						if ($line->remise_percent > $remise_percent)$remise_percent = $line->remise_percent;
						//var_dump($line);
						$order->updateline(
							$lineOrderFetched->id,
							$lineOrderFetched->desc,
							// FIXME: The current existing line may very well not be at the same purchase price
							$lineOrderFetched->pu_ht,
							$lineOrderFetched->qty + $line->qty,
							$remise_percent,
							$lineOrderFetched->tva_tx
						);
						  // add link to element_element between order line and supplier order line if not exist

						  $linkendline = getlinkedobject($lineOrderFetched->id, $linkendline->element, 'commande_commandedet');
						if ($linkendline == 0) {
							   $lineOrderFetched->add_object_linked($line->id, 'commande_commandedet');
						}

						$done = true;
						break;
					}
				}

				// On ajoute une ligne seulement si un "updateline()" n'a pas été fait et si la quantité souhaitée est supérieure à zéro

				if (!$done) {
					$newlineid = $order->addline(
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
						$line->info_bits
					);

					 // add link to element_element between order line and supplier order line
					 $newline = new CommandeFournisseurLigne($db);
					 $result = $newline->fetch($newlineid);
					if ($result == 1) {
						  $newline->add_object_linked($line->id, 'commandedet');
					}
				}
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
						$link = dol_buildpath('/fourn/commande/card.php?id='.$order_id, 1);
						header('Location:'.$link);
					}
				}
			}
			$i++;
		}

		/*if($newCommande) {

			setEventMessage("Commande fournisseur créée avec succès !", 'errors');

		} else {

			setEventMessage("Produits ajoutés à la commande en cours !", 'errors');

		}*/

		/*if (!$fail && $id) {
			setEventMessage($langs->trans('OrderCreated'), 'mesgs');
			//header('Location: '.DOL_URL_ROOT.'/commande/fiche.php?id='.$_REQUEST['id'].'');
		} else {
			setEventMessage('coucou', 'mesgs');
		}*/
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
				$sql.= " FROM ".$db->prefix()."product_fournisseur_price";
				$sql.= " WHERE fk_soc = ".$idSupplier;
				$sql.= " AND fk_product = ".$line[$j]->fk_product;
				$sql.= " ORDER BY quantity ASC";
				$sql.= " LIMIT 1";
				$resql = $db->query($sql);
				if ($resql) {
					$resql = $db->fetch_object($resql);

					//echo $j;

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

				/*echo "<pre>";
				print_r($rates);
				echo "</pre>";
				echo "<pre>";
				print_r($ajoutes);
				echo "</pre>";*/
				$j++;
			}
		}
		$mess = "";
		// FIXME: declare $ajoutes somewhere. It's unclear if it should be reinitialized or not in the interlocking loops.
		if ($ajoutes) {
			foreach ($ajoutes as $nomFournisseur => $nomProd) {
				$mess.= "Produit ' ".$nomProd." ' ajouté à la commande du fournisseur ' ".$nomFournisseur." '<br />";
			}
		}
		// FIXME: same as $ajoutes.
		if ($rates) {
			foreach ($rates as $nomFournisseur => $nomProd) {
				$mess.= "Quantité insuffisante de ' ".$nomProd." ' pour le fournisseur ' ".$nomFournisseur." '<br />";
			}
		}
		if ($rates) {
			setEventMessage($mess, 'warnings');
		} else {
			setEventMessage($mess, 'mesgs');
		}
	}
}
