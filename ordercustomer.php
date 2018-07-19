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

ini_set('memory_limit','1024M');
set_time_limit(0);

ini_set('display_errors', 1);
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


global $bc, $conf, $db, $langs, $user;

$prod = new Product($db);

$langs->load("products");
$langs->load("stocks");
$langs->load("orders");
$langs->load("supplierorderfromorder@supplierorderfromorder");

$dolibarr_version35 = false;

if((float)DOL_VERSION >= 3.5){
	$dolibarr_version35 = true;
}
/*echo "<form name=\"formCreateSupplierOrder\" method=\"post\" action=\"ordercustomer.php\">";*/

// Security check
if ($user->societe_id) {
    $socid = $user->societe_id;
}
$result=restrictedArea($user,'produit&supplierorderfromorder');

//checks if a product has been ordered

$action = GETPOST('action','alpha');
$sref = GETPOST('sref', 'alpha');
$snom = GETPOST('snom', 'alpha');
$sall = GETPOST('sall', 'alpha');
$type = GETPOST('type','int');
$tobuy = GETPOST('tobuy', 'int');
$salert = GETPOST('salert', 'alpha');

$sortfield = GETPOST('sortfield','alpha');
$sortorder = GETPOST('sortorder','alpha');
$page = (int) GETPOST('page','int');
$selectedSupplier = GETPOST('useSameSupplier', 'int');

if (!$sortfield) {
    $sortfield = 'cd.rang';
}

if (!$sortorder) {
    $sortorder = 'ASC';
}
$conf->liste_limit = 1000; // Pas de pagination sur cet écran
$limit = $conf->liste_limit;
$offset = $limit * $page ;



/*
 * Actions
 */



if (isset($_POST['button_removefilter']) || in_array($action, array('valid-propal', 'valid-order') ) ) {
    $sref = '';
    $snom = '';
    $sal = '';
    $salert = '';
}

/*echo "<pre>";
print_r($_REQUEST);
echo "</pre>";
exit;*/



//orders creation
//FIXME: could go in the lib
if (in_array($action, array('valid-propal', 'valid-order') )) {
	

	$actionTarget = 'order';
	if($action=='valid-propal')
	{
		$actionTarget= 'propal';
	}
	
	
    $linecount = GETPOST('linecount', 'int');
    $box = false;
    unset($_POST['linecount']);
    if ($linecount > 0) {

        $suppliers = array();

        for ($i = 0; $i < $linecount; $i++) {

            if(GETPOST('check'.$i, 'alpha') === 'on' && (GETPOST('fourn' . $i, 'int') > 0 || GETPOST('fourn_free' . $i, 'int') > 0)) { //one line
            	_prepareLine($i,$actionTarget);
            }
            unset($_POST[$i]);
            
        }
        

        
        //we now know how many orders we need and what lines they have
        $i = 0;
		$nb_orders_created = 0;
        $orders = array();
        $suppliersid = array_keys($suppliers);
		$projectid = GETPOST('projectid');

        foreach ($suppliers as $idsupplier => $supplier) {

			
			
			if($actionTarget=='propal')
			{
				$order = new SupplierProposal($db);
				$obj =_getSupplierProposalInfos($idsupplier, $projectid);
			}
			else
			{
				$order = new CommandeFournisseur($db);
				$obj =_getSupplierOrderInfos($idsupplier, $projectid);
			}
			
			$commandeClient = new Commande($db);
			$commandeClient->fetch($_REQUEST['id']);
			
			// Test recupération contact livraison
			if($conf->global->SUPPLIERORDER_FROM_ORDER_CONTACT_DELIVERY)
			{
				$contact_ship = $commandeClient->getIdContact('external', 'SHIPPING');
				$contact_ship=$contact_ship[0];
			}else{$contact_ship=null;}
			
			
		
			
			//Si une commande au statut brouillon existe déjà et que l'option SOFO_CREATE_NEW_SUPPLIER_ODER_ANY_TIME
			if($obj && !$conf->global->SOFO_CREATE_NEW_SUPPLIER_ODER_ANY_TIME) {

				$order->fetch($obj->rowid);
				$order->socid = $idsupplier;
				
				if(!empty($projectid)){
					$order->fk_project = GETPOST('projectid');
				}
				
				// On vérifie qu'il n'existe pas déjà un lien entre la commande client et la commande fournisseur dans la table element_element.
				// S'il n'y en a pas, on l'ajoute, sinon, on ne l'ajoute pas
				$order->fetchObjectLinked('', 'commande', $order->id, 'order_supplier');
				$order->add_object_linked('commande', $_REQUEST['id']);
					
				// cond reglement, mode reglement, delivery date
				_appliCond($order, $commandeClient);
				
				
				
				$id++; //$id doit être renseigné dans tous les cas pour que s'affiche le message 'Vos commandes ont été générées'
				$newCommande = false;
				
			} else {

				$order->socid = $idsupplier;
				if(!empty($projectid)){
					$order->fk_project = GETPOST('projectid');
				}
				
				// cond reglement, mode reglement, delivery date
				_appliCond($order, $commandeClient);
				
				$id = $order->create($user);
				if($contact_ship && $conf->global->SUPPLIERORDER_FROM_ORDER_CONTACT_DELIVERY) $order->add_contact($contact_ship, 'SHIPPING');
				$order->add_object_linked('commande', $_REQUEST['id']);
				$newCommande = true;

				$nb_orders_created++;
			}
			
			
			$order_id = $order->id;
            //trick to know which orders have been generated this way
            $order->source = 42;
            $MaxAvailability = 0;
            
            foreach ($supplier['lines'] as $line) {

	            $done = false;

				$prodfourn = new ProductFournisseur($db);
				$prodfourn->fetch_product_fournisseur_price($_REQUEST['fourn'.$i]);

            	foreach($order->lines as $lineOrderFetched) {

            		if($line->fk_product == $lineOrderFetched->fk_product) {

                        $remise_percent = $lineOrderFetched->remise_percent;
                        if($line->remise_percent > $remise_percent)$remise_percent = $line->remise_percent;
//var_dump($line);

                        
                        if($order->element == 'order_supplier')
                        {
                        	$order->updateline(
                        			$lineOrderFetched->id,
                        			$lineOrderFetched->desc,
                        			// FIXME: The current existing line may very well not be at the same purchase price
                        			$lineOrderFetched->pu_ht,
                        			$lineOrderFetched->qty + $line->qty,
                        			$remise_percent,
                        			$lineOrderFetched->tva_tx
                        			);
                        }
                        else if($order->element == 'supplier_proposal')
                        {

                        	$order->updateline(
                        			$lineOrderFetched->id, 
                        			$prodfourn->fourn_unitprice, //$lineOrderFetched->pu_ht is empty,
            						$lineOrderFetched->qty + $line->qty, 
            						$remise_percent, 
            						$lineOrderFetched->tva_tx, 
            						0, //$txlocaltax1=0, 
            						0, //$txlocaltax2=0, 
            						$lineOrderFetched->desc
	            					//$price_base_type='HT', 
	            					//$info_bits=0, 
	            					//$special_code=0, 
	            					//$fk_parent_line=0, 
	            					//$skip_update_total=0, 
	            					//$fk_fournprice=0, 
	            					//$pa_ht=0, 
	            					//$label='', 
	            					//$type=0, 
	            					//$array_option=0, 
	            					//$ref_fourn='', 
	            					//$fk_unit=''
            				);
                        }
            			
						$done = true;
						break;

            		}

            	}

				// On ajoute une ligne seulement si un "updateline()" n'a pas été fait et si la quantité souhaitée est supérieure à zéro

				if(!$done) {

					if($order->element == 'order_supplier')
					{

						$order->addline(
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
								$line->remise_percent
								,'HT'
								,0
								,$line->product_type
								,$line->info_bits
								,FALSE // $notrigger
								,NULL // $date_start
								,NULL // $date_end
								,$line->array_options
						);
					}
					else if($order->element == 'supplier_proposal')
					{
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
								'', //$origin='', ,
								0 //$origin_id=0
							);
						
					
					}

				}
				
				$nb_day = (int)TSOFO::getMinAvailability($line->fk_product, $line->qty,1,$prodfourn->fourn_id);
				if($MaxAvailability<$nb_day)
				{
					$MaxAvailability = $nb_day;
				}
				
				

            }

            if(!empty($conf->global->SOFO_USE_MAX_DELIVERY_DATE))
            {
            	$order->date_livraison = dol_now() + $MaxAvailability*86400;
            	$order->set_date_livraison($user,$order->date_livraison);
            }
            
            $order->cond_reglement_id = 0;
            $order->mode_reglement_id = 0;

            if ($id < 0) {
                $fail++; // FIXME: declare somewhere and use, or get rid of it!
                $msg = $langs->trans('OrderFail') . "&nbsp;:&nbsp;";
                $msg .= $order->error;
                setEventMessage($msg, 'errors');
            }else{
            	// CODE de redirection s'il y a un seul fournisseur (évite de le laisser sur la page sans comprendre)
            	if($conf->global->SUPPLIERORDER_FROM_ORDER_HEADER_SUPPLIER_ORDER)
				{
	            	if(count($suppliersid) == 1)
					{
						$link = dol_buildpath('/fourn/commande/card.php?id='.$order_id, 1);
						header('Location:'.$link);
					}
				}
            }
            $i++;
        }


    }

	if ($nb_orders_created > 0)
	{
		setEventMessages($langs->trans('supplierorderfromorder_nb_orders_created', $nb_orders_created), array());
	}

    if ($box === false) {
        setEventMessage($langs->trans('SelectProduct'), 'warnings');
    } else {

    	foreach($suppliers as $idSupplier => $lines) {
    		$j = 0;
    		foreach($lines as $line) {
		    	$sql = "SELECT quantity";
				$sql.= " FROM ".MAIN_DB_PREFIX."product_fournisseur_price";
				$sql.= " WHERE fk_soc = ".$idSupplier;
				$sql.= " AND fk_product = ".$line[$j]->fk_product;
				$sql.= " ORDER BY quantity ASC";
				$sql.= " LIMIT 1";
				$resql = $db->query($sql);
				if($resql){
					$resql = $db->fetch_object($resql);

					//echo $j;

					if($line[$j]->qty < $resql->quantity) {
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
		if($ajoutes) {
			foreach($ajoutes as $nomFournisseur => $nomProd) {
				
				if($actionTarget=='propal')
				{
					$mess.= $langs->trans('ProductAddToSupplierQuotation', $nomProd,$nomFournisseur).'<br />';
				}
				else
				{
					$mess.= $langs->trans('ProductAddToSupplierOrder', $nomProd,$nomFournisseur).'<br />';
				}
				
			}
		}
	    // FIXME: same as $ajoutes.
		if($rates) {
			foreach($rates as $nomFournisseur => $nomProd) {
				$mess.= "Quantité insuffisante de ' ".$nomProd." ' pour le fournisseur ' ".$nomFournisseur." '<br />";
			}
		}
		if($rates) {
			setEventMessage($mess, 'warnings');
		} else {
			setEventMessage($mess, 'mesgs');
		}
    }
}

/*
 * View
 */

$TCachedProductId =& $_SESSION['TCachedProductId'];
if(empty($TCachedProductId)) $TCachedProductId=array();
if(GETPOST('purge_cached_product')=='yes') $TCachedProductId =array();

$title = $langs->trans('ProductsToOrder');
$db->query("SET SQL_MODE=''");
$sql = 'SELECT p.rowid, p.ref, p.label, cd.description, p.price, SUM(cd.qty) as qty';
$sql .= ', p.price_ttc, p.price_base_type,p.fk_product_type';
$sql .= ', p.tms as datem, p.duration, p.tobuy, p.seuil_stock_alerte, p.finished, cd.rang,';
$sql .= ' GROUP_CONCAT(cd.rowid SEPARATOR "@") as lineid,';
$sql .= ' ( SELECT SUM(s.reel) FROM ' . MAIN_DB_PREFIX . 'product_stock s WHERE s.fk_product=p.rowid ) as stock_physique';
$sql .= $dolibarr_version35 ? ', p.desiredstock' : "";
$sql .= ' FROM ' . MAIN_DB_PREFIX . 'product as p';
$sql .= ' LEFT OUTER JOIN ' . MAIN_DB_PREFIX . 'commandedet as cd ON (p.rowid = cd.fk_product)';

if (!empty($conf->categorie->enabled))
{
	$sql .= ' LEFT OUTER JOIN ' . MAIN_DB_PREFIX . 'categorie_product as cp ON (p.rowid = cp.fk_product)';
}

//$sql .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'product_stock as s ON (p.rowid = s.fk_product)';
$sql .= ' WHERE p.fk_product_type IN (0,1) AND p.entity IN (' . getEntity("product", 1) . ')';

$fk_commande = GETPOST('id','int');

if($fk_commande > 0) $sql .= ' AND cd.fk_commande = '.$fk_commande;

if (!empty($conf->categorie->enabled))
{
	$fk_categorie = GETPOST('categorie','int');
	if($fk_categorie > 0) $sql .= ' AND cp.fk_categorie = '.intval($fk_categorie);
}

if ($sall) {
    $sql .= ' AND (p.ref LIKE "%'.$db->escape($sall).'%" ';
    $sql .= 'OR p.label LIKE "%'.$db->escape($sall).'%" ';
    $sql .= 'OR p.description LIKE "%'.$db->escape($sall).'%" ';
    $sql .= 'OR p.note LIKE "%'.$db->escape($sall).'%")';
}
// if the type is not 1, we show all products (type = 0,2,3)
if (dol_strlen($type)) {
    if ($type == 1) {
        $sql .= ' AND p.fk_product_type = 1';
    } else {
        $sql .= ' AND p.fk_product_type != 1';
    }
}
if ($sref) {
    //natural search
    $scrit = explode(' ', $sref);
    foreach ($scrit as $crit) {
        $sql .= ' AND p.ref LIKE "%' . $crit . '%"';
    }
}
if ($snom) {
    //natural search
    $scrit = explode(' ', $snom);
    foreach ($scrit as $crit) {
        $sql .= ' AND p.label LIKE "%' . $db->escape($crit) . '%"';
    }
}

$sql .= ' AND p.tobuy = 1';

$finished = GETPOST('finished');
if($finished != '' && $finished != '-1') $sql .= ' AND p.finished = '.$finished;
elseif(!isset($_REQUEST['button_search_x']) && isset($conf->global->SOFO_DEFAUT_FILTER) && $conf->global->SOFO_DEFAUT_FILTER >= 0) $sql .= ' AND p.finished = '.$conf->global->SOFO_DEFAUT_FILTER;

if (!empty($canvas)) {
    $sql .= ' AND p.canvas = "' . $db->escape($canvas) . '"';
}
$sql .= ' GROUP BY p.rowid, p.ref, p.label, p.price';
$sql .= ', p.price_ttc, p.price_base_type,p.fk_product_type, p.tms';
$sql .= ', p.duration, p.tobuy, p.seuil_stock_alerte';
//$sql .= ', p.desiredstock';
//$sql .= ', s.fk_product';

if(!empty($conf->global->SUPPORDERFROMORDER_USE_ORDER_DESC)) {
	$sql.= ', cd.description';
}
//$sql .= ' HAVING p.desiredstock > SUM(COALESCE(s.reel, 0))';
//$sql .= ' HAVING p.desiredstock > 0';
if ($salert == 'on') {
    $sql .= ' HAVING SUM(COALESCE(stock_physique, 0)) < p.seuil_stock_alerte AND p.seuil_stock_alerte is not NULL';
    $alertchecked = 'checked="checked"';
}

$sql2 = '';
//On prend les lignes libre
if($_REQUEST['id'] && $conf->global->SOFO_ADD_FREE_LINES){
	$sql2 .= 'SELECT cd.rowid, cd.description, cd.qty as qty, cd.product_type, cd.price, cd.buy_price_ht
			 FROM '.MAIN_DB_PREFIX.'commandedet as cd
			 	LEFT JOIN '.MAIN_DB_PREFIX.'commande as c ON (cd.fk_commande = c.rowid)
			 WHERE c.rowid = '.$_REQUEST['id'].' AND cd.product_type IN(0,1) AND fk_product IS NULL';
	if(!empty($conf->global->SUPPORDERFROMORDER_USE_ORDER_DESC)) {
		$sql2 .= ' GROUP BY cd.description';
	}
	//echo $sql2;
}
$sql .= $db->order($sortfield,$sortorder);
if(!$conf->global->SOFO_USE_DELIVERY_TIME) $sql .= $db->plimit($limit + 1, $offset);
$resql = $db->query($sql);

if(isset($_REQUEST['DEBUG']) || $resql===false) {print $sql;exit;}

if($sql2 && $fk_commande > 0){
	$sql2 .= $db->order($sortfield,$sortorder);
	$sql2 .= $db->plimit($limit + 1, $offset);
	$resql2 = $db->query($sql2);
}

$justOFforNeededProduct = !empty($conf->global->SOFO_USE_ONLY_OF_FOR_NEEDED_PRODUCT) && empty($fk_commande);
$statutarray=array('1' => $langs->trans("Finished"), '0' => $langs->trans("RowMaterial"));
$form = new Form($db);

if ($resql || $resql2) {
    $num = $db->num_rows($resql);
	$num2 = $db->num_rows($resql2);
    $i = 0;

    $helpurl = 'EN:Module_Stocks_En|FR:Module_Stock|';
    $helpurl .= 'ES:M&oacute;dulo_Stocks';
    llxHeader('', $title, $helpurl, $title);
    $head = array();
    $head[0][0] = dol_buildpath('/supplierorderfromorder/ordercustomer.php?id='.$_REQUEST['id'],2);
    $head[0][1] = $title;
    $head[0][2] = 'supplierorderfromorder';
	/*$head[1][0] = DOL_URL_ROOT.'/product/stock/replenishorders.php';
	$head[1][1] = $langs->trans("ReplenishmentOrders");
	$head[1][2] = 'replenishorders';*/
    dol_fiche_head($head, 'supplierorderfromorder', $langs->trans('Replenishment'), 0, 'stock');



	if ($sref || $snom || $sall || $salert || GETPOST('search', 'alpha')) {
        $filters = '&sref=' . $sref . '&snom=' . $snom;
        $filters .= '&sall=' . $sall;
        $filters .= '&salert=' . $salert;

		if(!$conf->global->SOFO_USE_DELIVERY_TIME ) {

			 print_barre_liste(
	        		$texte,
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
        $filters .= (isset($type)?'&type=' . $type:'');
        $filters .=  '&salert=' . $salert;

        if(!$conf->global->SOFO_USE_DELIVERY_TIME ) {

        print_barre_liste(
        		$texte,
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

    print '<form action="'.$_SERVER['PHP_SELF'].'?id='.$_REQUEST['id'].'&projectid='.$_REQUEST['projectid'].'" method="post" name="formulaire">'.
         '<input type="hidden" name="id" value="' .$_REQUEST['id'] . '">'.
         '<input type="hidden" name="token" value="' .$_SESSION['newtoken'] . '">'.
         '<input type="hidden" name="sortfield" value="' . $sortfield . '">'.
         '<input type="hidden" name="sortorder" value="' . $sortorder . '">'.
         '<input type="hidden" name="type" value="' . $type . '">'.
         '<input type="hidden" name="linecount" value="' . ($num+$num2) . '">'.
         '<input type="hidden" name="fk_commande" value="' . GETPOST('fk_commande','int'). '">'.
         '<input type="hidden" name="show_stock_no_need" value="' . GETPOST('show_stock_no_need'). '">'.

         '<div style="text-align:right">
			<a href="'.$_SERVER["PHP_SELF"].'?'.$_SERVER["QUERY_STRING"].'&show_stock_no_need=yes">'.$langs->trans('ShowLineEvenIfStockIsSuffisant').'</a>';

	if(!empty($TCachedProductId)) {
		echo '<a style="color:red; font-weight:bold;" href="'.$_SERVER["PHP_SELF"].'?'.$_SERVER["QUERY_STRING"].'&purge_cached_product=yes">'.$langs->trans('PurgeSessionForCachedProduct').'</a>';
	}

print '	  </div>'.
         '<table class="liste" width="100%">';

    $colspan = 7;
    
    if($conf->global->SOFO_USE_DELIVERY_TIME) {
            $week_to_replenish = (int)GETPOST('week_to_replenish','int');

            $colspan = empty($conf->global->FOURN_PRODUCT_AVAILABILITY) ? 7 : 8;

            if (!empty($conf->of->enabled) && !empty($conf->global->OF_USE_DESTOCKAGE_PARTIEL)){$colspan ++;}
            
            if (!empty( $conf->global->SOFO_USE_DELIVERY_TIME)){$colspan ++;}
           
            if (!empty($conf->categorie->enabled)){$colspan ++;}
           
            if (!empty($conf->service->enabled) && $type == 1){$colspan ++;}
            
            if($dolibarr_version35){$colspan ++;}
            
            
        print '<tr class="liste_titre">'.
            '<td colspan="'.$colspan.'">'.$langs->trans('NbWeekToReplenish').'<input type="text" name="week_to_replenish" value="'.$week_to_replenish.'" size="2"> '
            .'<input type="submit" value="'.$langs->trans('ReCalculate').'" /></td><td></td>';

        if ($conf->of->enabled && !empty($conf->global->OF_USE_DESTOCKAGE_PARTIEL)) print '<td></td>';

			print '</tr>';


    }


    
    $param = (isset($type)? '&type=' . $type : '');
    $param .= '&fourn_id=' . $fourn_id . '&snom='. $snom . '&salert=' . $salert;
    $param .= '&sref=' . $sref;

    // Lignes des titres
    print '<tr class="liste_titre">'.
         '<td><input type="checkbox" onClick="toggle(this)" /></td>';
    print_liste_field_titre(
    		$langs->trans('Ref'),
    		'ordercustomer.php',
    		'p.ref',
    		$param,
    		'id='.$_REQUEST['id'],
    		'',
    		$sortfield,
    		$sortorder
    );
    print_liste_field_titre(
    		$langs->trans('Label'),
    		'ordercustomer.php',
    		'p.label',
    		$param,
    		'id='.$_REQUEST['id'],
    		'',
    		$sortfield,
    		$sortorder
    		);
    print_liste_field_titre(
    		$langs->trans('Nature'),
    		'ordercustomer.php',
    		'p.label',
    		$param,
    		'id='.$_REQUEST['id'],
    		'',
    		$sortfield,
    		$sortorder
    		);
    if (!empty($conf->categorie->enabled))
    {
	    print_liste_field_titre(
	    		$langs->trans("Categories"),
	    		'ordercustomer.php',
	    		'cp.fk_categorie',
	    		$param,
	    		'id='.$_REQUEST['id'],
	    		'',
	    		$sortfield,
	    		$sortorder
	    		);
    }
    if (!empty($conf->service->enabled) && $type == 1)
    {
    	print_liste_field_titre(
    			$langs->trans('Duration'),
    			'ordercustomer.php',
    			'p.duration',
    			$param,
    			'id='.$_REQUEST['id'],
    			'align="center"',
    			$sortfield,
    			$sortorder
    	);
    }

	if($dolibarr_version35) {
	    print_liste_field_titre(
	    		$langs->trans('DesiredStock'),
	    		'ordercustomer.php',
	    		'p.desiredstock',
	    		$param,
	    		'id='.$_REQUEST['id'],
	    		'align="right"',
	    		$sortfield,
	    		$sortorder
	    );
	}

	/* On n'affiche "Stock Physique" que lorsque c'est effectivement le cas :
	 * - Si on est dans le cas d'un OF avec les produits nécessaires
	 * - Si on utilise les stocks virtuels (soit avec la conf globale Dolibarr, soit celle du module) ou qu'on utilise une plage de temps pour le besoin ou qu'on ne prend pas en compte les commandes clients
	 */
	if (empty($justOFforNeededProduct) && ($week_to_replenish > 0 || ! empty($conf->global->USE_VIRTUAL_STOCK) || ! empty($conf->global->SOFO_USE_VIRTUAL_ORDER_STOCK) || empty($conf->global->SOFO_DO_NOT_USE_CUSTOMER_ORDER)))
    {
        $stocklabel = $langs->trans('VirtualStock');
    }
    else
    {
        $stocklabel = $langs->trans('PhysicalStock');
    }
    print_liste_field_titre(
    		$stocklabel,
    		'ordercustomer.php',
    		'stock_physique',
    		$param,
    		'id='.$_REQUEST['id'],
    		'align="right"',
    		$sortfield,
    		$sortorder
    );

	if ($conf->of->enabled && !empty($conf->global->OF_USE_DESTOCKAGE_PARTIEL))
	{
		dol_include_once('/of/lib/of.lib.php');
		print_liste_field_titre(
	    		'Stock théo - OF',
	    		'ordercustomer.php',
	    		'stock_theo_of',
	    		$param,
	    		'id='.$_REQUEST['id'],
	    		'align="right"',
	    		$sortfield,
	    		$sortorder
	    );
	}

    print_liste_field_titre(
    		$langs->trans('Ordered'),
    		'ordercustomer.php',
    		'',
    		$param,
    		'id='.$_REQUEST['id'],
    		'align="right"',
    		$sortfield,
    		$sortorder
    );
    print_liste_field_titre(
    		$langs->trans('StockToBuy'),
    		'ordercustomer.php',
    		'',
    		$param,
    		'id='.$_REQUEST['id'],
    		'align="right"',
    		$sortfield,
    		$sortorder
    );

	//print '<td class="liste_titre" >fghf</td>';

   	if (!empty($conf->global->FOURN_PRODUCT_AVAILABILITY)) print_liste_field_titre($langs->trans("Availability"));

    print_liste_field_titre(
    		$langs->trans('Supplier'),
    		'ordercustomer.php',
    		'',
    		$param,
    		'id='.$_REQUEST['id'],
    		'align="right"',
    		$sortfield,
    		$sortorder
    );
    
    print '<th class="liste_titre" >&nbsp;</th>';
    
    print '</tr>'.
	    // Lignes des champs de filtre
         '<tr class="liste_titre">'.
         	'<td class="liste_titre">&nbsp;</td>'.
         	'<td class="liste_titre">'.
         	'<input class="flat" type="text" name="sref" value="' . $sref . '">'.
         '</td>'.
         '<td class="liste_titre">'.
         	'<input class="flat" type="text" name="snom" value="' . $snom . '">'.
         '</td>';
    
    if (!empty($conf->service->enabled) && $type == 1)
    {
        print '<td class="liste_titre">'.
             '&nbsp;'.
             '</td>';
    }

	$liste_titre = "";
	$liste_titre.= '<td class="liste_titre">'.$form->selectarray('finished',$statutarray,(!isset($_REQUEST['button_search_x']) && $conf->global->SOFO_DEFAUT_FILTER != -1) ? $conf->global->SOFO_DEFAUT_FILTER : GETPOST('finished'),1).'</td>';
    
	if (!empty($conf->categorie->enabled))
	{
		$liste_titre.= '<td class="liste_titre">';
		$liste_titre.= $form->select_all_categories('product',GETPOST('categorie'), 'categorie');
		$liste_titre.= '</td>';
	}
	
	$liste_titre.= $dolibarr_version35 ? '<td class="liste_titre">&nbsp;</td>' : '';
    $liste_titre.= '<td class="liste_titre" align="right">' . $langs->trans('AlertOnly') . '&nbsp;<input type="checkbox" name="salert" ' . $alertchecked . '></td>';

	if ($conf->of->enabled && !empty($conf->global->OF_USE_DESTOCKAGE_PARTIEL))
	{
		$liste_titre.= '<td class="liste_titre" align="right"></td>';
	}

    $liste_titre.= '<td class="liste_titre" align="right">&nbsp;</td>'.
         '<td class="liste_titre">&nbsp;</td>'.
         '<td class="liste_titre" '.($conf->global->SOFO_USE_DELIVERY_TIME ? 'colspan="2"' : '').'>&nbsp;</td>'.
         '<td class="liste_titre" align="right">'.
         '<input type="image" class="liste_titre" name="button_search"'.
         'src="' . DOL_URL_ROOT . '/theme/' . $conf->theme . '/img/search.png" alt="' . $langs->trans("Search") . '">'.
         '<input type="image" class="liste_titre" name="button_removefilter"
          src="'.DOL_URL_ROOT.'/theme/'.$conf->theme.'/img/searchclear.png" value="'.dol_escape_htmltag($langs->trans("Search")).'" title="'.dol_escape_htmltag($langs->trans("Search")).'">'.
         '</td>'.
         '</tr>';

	print $liste_titre;

    $prod = new Product($db);

    $var = True;

	if($conf->global->SOFO_USE_DELIVERY_TIME) {
		$form->load_cache_availability();
		$limit = 999999;
	}

	$TSupplier = array();
    while ($i < min($num, $limit)) {
    	$objp = $db->fetch_object($resql);
//if($objp->rowid == 4666) { var_dump($objp); }
		
    	if ($conf->global->SOFO_DISPLAY_SERVICES || $objp->fk_product_type == 0) {
    	
            // Multilangs
            if (! empty($conf->global->MAIN_MULTILANGS)) {
                $sql = 'SELECT label';
                $sql .= ' FROM ' . MAIN_DB_PREFIX . 'product_lang';
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
            //$ordered = ordered($prod->id);

            $help_stock =  $langs->trans('PhysicalStock').' : '.(float)$objp->stock_physique;

           $stock_commande_client = 0;
           $stock_commande_fournisseur = 0;

           if(!$justOFforNeededProduct) {

                if($week_to_replenish>0) {
                	/* là ça déconne pas, on s'en fout, on dépote ! */
                    if(empty($conf->global->SOFO_DO_NOT_USE_CUSTOMER_ORDER)) {
                        $stock_commande_client = _load_stats_commande_date($prod->id, date('Y-m-d',strtotime('+'.$week_to_replenish.'week') ) );
                        $help_stock.=', '.$langs->trans('Orders').' : '.(float)$stock_commande_client;
                    }

    				$stock_commande_fournisseur = _load_stats_commande_fournisseur($prod->id, date('Y-m-d',strtotime('+'.$week_to_replenish.'week')), $objp->stock_physique-$stock_commande_client);
    				$help_stock.=', '.$langs->trans('SupplierOrders').' : '.(float)$stock_commande_fournisseur;


    				$stock = $objp->stock_physique - $stock_commande_client + $stock_commande_fournisseur;
                }
    			else if ($conf->global->USE_VIRTUAL_STOCK || $conf->global->SOFO_USE_VIRTUAL_ORDER_STOCK) {
                    //compute virtual stockshow_stock_no_need
                    $prod->fetch($prod->id);
    				if((!$conf->global->STOCK_CALCULATE_ON_VALIDATE_ORDER || $conf->global->SOFO_USE_VIRTUAL_ORDER_STOCK)
                            && empty($conf->global->SOFO_DO_NOT_USE_CUSTOMER_ORDER)) {
    	                $result=$prod->load_stats_commande(0, '1,2');
    	                if ($result < 0) {
    	                    dol_print_error($db, $prod->error);
    	                }
    	                $stock_commande_client = $prod->stats_commande['qty'];
    				}
    				else{
    					$stock_commande_client = 0;
    				}

    				if(!$conf->global->STOCK_CALCULATE_ON_SUPPLIER_VALIDATE_ORDER || $conf->global->SOFO_USE_VIRTUAL_ORDER_STOCK) {
    	                $result=$prod->load_stats_commande_fournisseur(0, '3,4');
    	                if ($result < 0) {
    	                    dol_print_error($db,$prod->error);
    	                }

						//Requête qui récupère la somme des qty ventilés pour les cmd reçu partiellement
						$sqlQ = "SELECT SUM(cfd.qty) as qty";
						$sqlQ.= " FROM ".MAIN_DB_PREFIX."commande_fournisseur_dispatch as cfd";
						$sqlQ.= " INNER JOIN ".MAIN_DB_PREFIX."commande_fournisseur cf ON (cf.rowid = cfd.fk_commande)";
						$sqlQ.= " LEFT JOIN ".MAIN_DB_PREFIX."entrepot as e ON cfd.fk_entrepot = e.rowid";
						$sqlQ.= " WHERE cf.fk_statut = 4";
						$sqlQ.= " AND cfd.fk_product = ".$prod->id;
						$sqlQ.= " ORDER BY cfd.rowid ASC";
						$resqlQ = $db->query($sqlQ);

    					$stock_commande_fournisseur = $prod->stats_commande_fournisseur['qty'];

						if ($row = $db->fetch_object($resqlQ)) $stock_commande_fournisseur -= $row->qty;

    				}
    				else{
    					$stock_commande_fournisseur = 0;

					}

                    if($stock_commande_client>0) {
                        $help_stock.=', '.$langs->trans('Orders').' : '.(float)$stock_commande_client;
                    }

                	$help_stock.=', '.$langs->trans('SupplierOrders').' : '.(float)$stock_commande_fournisseur;

                    	$stock = $objp->stock_physique - $stock_commande_client + $stock_commande_fournisseur;
				 } else {

                    if(empty($conf->global->SOFO_DO_NOT_USE_CUSTOMER_ORDER)) {
                        $stock_commande_client = $objp->qty;
                        $help_stock.=', '.$langs->trans('Orders').' : '.(float)$stock_commande_client;
                    }

                    $stock = $objp->stock_physique - $stock_commande_client;



                }
            }
            else {
    	        $stock = $objp->stock_physique;
                $help_stock.='(Juste OF) ';
    		}

			$ordered = $stock_commande_client;



        	//if($objp->rowid == 14978)	{print "$stock >= {$objp->qty} - $stock_expedie_client + {$objp->desiredstock}";exit;}
            /*if($stock >= (float)$objp->qty - (float)$stock_expedie_client + (float)$objp->desiredstock) {
    			$i++;
    			continue; // le stock est suffisant on passe
    		}*/


            $warning='';
            if ($objp->seuil_stock_alerte
                && ($stock < $objp->seuil_stock_alerte)) {
                    $warning = img_warning($langs->trans('StockTooLow')) . ' ';
            }

			// On regarde s'il existe une demande de prix en cours pour ce produit
			$TDemandes = array();

			if(DOL_VERSION>=6) {

				if(!empty($conf->supplier_proposal->enabled)) {

                                $q = 'SELECT a.ref
                                                FROM '.MAIN_DB_PREFIX.'supplier_proposal a
                                                INNER JOIN '.MAIN_DB_PREFIX.'supplier_proposaldet d on (d.fk_supplier_proposal=a.rowid)
                                                WHERE a.fk_statut = 1
                                                AND d.fk_product = '.$prod->id;

                                $qres = $db->query($q);

                                while($res = $db->fetch_object($qres)) $TDemandes[] = $res->ref;
                        
                        	}


			}
			else {

			if($conf->askpricesupplier->enabled) {

				$q = 'SELECT a.ref
						FROM '.MAIN_DB_PREFIX.'askpricesupplier a
						INNER JOIN '.MAIN_DB_PREFIX.'askpricesupplierdet d on (d.fk_askpricesupplier = a.rowid)
						WHERE a.fk_statut = 1
						AND fk_product = '.$prod->id;

				$qres = $db->query($q);

				while($res = $db->fetch_object($qres)) $TDemandes[] = $res->ref;
			
          		}
			}

          	// La quantité à commander correspond au stock désiré sur le produit additionné à la quantité souhaitée dans la commande :

          	if(!$justOFforNeededProduct) {
          	     $stock_expedie_client = getExpedie($prod->id);
			     $stocktobuy = $objp->desiredstock - ($stock - $stock_expedie_client);
			     $help_stock.=', ' .$langs->trans('Expeditions').' : '.(float)$stock_expedie_client;
            }
            else{
                 $stocktobuy = $objp->desiredstock - $stock ;
            }


/*			if($stocktobuy<=0 && $prod->ref!='A0000753') {
    			$i++;
    			continue; // le stock est suffisant on passe
	    		}*/

			if($conf->of->enabled) {

				/* Si j'ai des OF je veux savoir combien cela me coûte */

				define('INC_FROM_DOLIBARR', true);
				dol_include_once('/of/config.php');
				dol_include_once('/of/class/ordre_fabrication_asset.class.php');

//$_REQUEST['DEBUG']=true;
				if($week_to_replenish>0) {
				$stock_of_needed = TAssetOF::getProductNeededQty($prod->id, false, true, date('Y-m-d',strtotime('+'.$week_to_replenish.'week') ));
				$stock_of_tomake = TAssetOF::getProductNeededQty($prod->id, false, true, date('Y-m-d',strtotime('+'.$week_to_replenish.'week') ), 'TO_MAKE');

				}
				else {
				$stock_of_needed = TAssetOF::getProductNeededQty($prod->id, false, true, '');
				$stock_of_tomake = TAssetOF::getProductNeededQty($prod->id, false, true, '', 'TO_MAKE');

				}

				$stocktobuy += $stock_of_needed - $stock_of_tomake;
				
				if(!$conf->global->STOCK_CALCULATE_ON_VALIDATE_ORDER || $conf->global->SOFO_USE_VIRTUAL_ORDER_STOCK)
				{
					$stock -= $stock_of_needed - $stock_of_tomake;
				}
				
				$help_stock.=', '.$langs->trans('OF').' : '.(float)($stock_of_needed - $stock_of_tomake);
			}

			$help_stock.=', '.$langs->trans('DesiredStock').' : '.(float)$objp->desiredstock;


			if($stocktobuy < 0) $stocktobuy = 0;

			if((empty($prod->type) && $stocktobuy == 0 && GETPOST('show_stock_no_need')!='yes') || ($prod->type == 1 && $stocktobuy == 0 && GETPOST('show_stock_no_need')!='yes' && !empty($conf->global->STOCK_SUPPORTS_SERVICES))) {
				$i++;
				continue;
			}

			$var =! $var;
			print '<tr ' . $bc[$var] . ' data-productid="'.$objp->rowid.'"  data-i="'.$i.'"   >
						<td>
							<input type="checkbox" class="check" name="check' . $i . '"' . $disabled . '>';

			$lineid = '';

			if(strpos($objp->lineid, '@') === false) { // Une seule ligne d'origine
				$lineid = $objp->lineid;
			}

			print '<input type="hidden" name="lineid' . $i . '" value="' . $lineid .'" />';

			if(!empty($conf->global->SUPPORDERFROMORDER_USE_ORDER_DESC)) {
				print '<input type="hidden" name="desc' . $i . '" value="' . $objp->description . '" >';
			}

			print '</td>
                 <td style="height:35px;" class="nowrap">'.
                 (!empty($TDemandes) ? $form->textwithpicto($prod->getNomUrl(1), 'Demande(s) de prix en cours :<br />'.implode(', ', $TDemandes), 1, 'help') : $prod->getNomUrl(1)).
                 '</td>'.
                 '<td>' . $objp->label . '</td>';

	        print '<td>'.(empty($prod->type) ? $statutarray[$objp->finished] : '').'</td>';

				
			if (!empty($conf->categorie->enabled))
			{
				print '<td >';
				$categorie = new Categorie($db);
				$Tcategories = $categorie->containing($objp->rowid, 'product', 'label');
				print implode(', ', $Tcategories);
				print '</td>';
			}
			
            if (!empty($conf->service->enabled) && $type == 1) {
                if (preg_match('/([0-9]+)y/i', $objp->duration, $regs)) {
                    $duration =  $regs[1] . ' ' . $langs->trans('DurationYear');
                } elseif (preg_match('/([0-9]+)m/i', $objp->duration, $regs)) {
                    $duration =  $regs[1] . ' ' . $langs->trans('DurationMonth');
                } elseif (preg_match('/([0-9]+)d/i', $objp->duration, $regs)) {
                    $duration =  $regs[1] . ' ' . $langs->trans('DurationDay');
                } else {
                    $duration = $objp->duration;
                }

                print '<td align="center">'.
                     $duration.
                     '</td>';
            }





            //print $dolibarr_version35 ? '<td align="right">' . $objp->desiredstock . '</td>' : "".
            
            	$champs = "";
            	$champs .= $dolibarr_version35 ? '<td align="right">' . $objp->desiredstock . '</td>' : '';
                $champs.= '<td align="right">'.
                  $warning . ((($conf->global->STOCK_SUPPORTS_SERVICES && $prod->type == 1) || empty($prod->type)) ? $stock : img_picto('', './img/no', '', 1)). //$stocktobuy
                 '</td>';
				if ($conf->of->enabled && !empty($conf->global->OF_USE_DESTOCKAGE_PARTIEL))
				{
/*					dol_include_once('/of/lib/of.lib.php');
					$prod->load_stock();
					list($qty_to_make, $qty_needed) = _calcQtyOfProductInOf($db, $conf, $prod);
					$qty = $prod->stock_theorique + $qty_to_make - $qty_needed;
*/					$prod->load_stock();
					$qty_of = $stock_of_needed - $stock_of_tomake;
					$qty=$prod->stock_theorique - $qty_of;
					$champs.= '<td align="right">'.$qty.'</td>';
				}

                 $champs.= '<td align="right">'.

                 $ordered  . $picto.
                 '</td>'.
                 '<td align="right">'.
                 '<input type="text" name="tobuy' . $i .
                 '" value="' . $stocktobuy . '" ' . $disabled . ' size="4">
                 <span class="stock_details" prod-id="'.$prod->id.'" week-to-replenish="'.$week_to_replenish.'">'.img_help(1, $help_stock).'</span></td>';

				 if($conf->global->SOFO_USE_DELIVERY_TIME) {

					$nb_day = (int)getMinAvailability($objp->rowid,$stocktobuy);

					$champs.= '<td data-info="availability" >'.($nb_day == 0 ? $langs->trans('Unknown') : $nb_day.' '.$langs->trans('Days')).'</td>';

				}



                 $champs.='<td align="right" data-info="fourn-price" >'.
                 $form->select_product_fourn_price($prod->id, 'fourn'.$i, (! empty($selectedSupplier) ? $selectedSupplier : '')).
                 '</td>';
				print $champs;

				if(empty($TSupplier)) $TSupplier = $prod->list_suppliers();
				else $TSupplier = array_intersect($prod->list_suppliers(), $TSupplier);

				if($conf->of->enabled && $user->rights->of->of->write && empty($conf->global->SOFO_REMOVE_MAKE_BTN)) {
		print '<td><a href="'.dol_buildpath('/of/fiche_of.php',1).'?action=new&fk_product='.$prod->id.'" class="butAction">Fabriquer</a></td>';
	   }
	   else {
	    	print '<td>&nbsp</td>';
	   }
           print '</tr>';

	  if(empty($fk_commande)) $TCachedProductId[] = $prod->id; //mise en cache

    }
    	
//	if($prod->ref=='A0000753') exit;

        $i++;
    }

	//Lignes libre
	if($resql2){
		while ($j< min($num2, $limit)) {
	        $objp = $db->fetch_object($resql2);
			//var_dump($sql2,$resql2, $objp);
			if ($objp->product_type == 0) $picto = img_object($langs->trans("ShowProduct"),'product');
			if ($objp->product_type == 1) $picto = img_object($langs->trans("ShowService"),'service');

            print '<tr ' . $bc[$var] . '>'.
                 '<td><input type="checkbox" class="check" name="check' . $i . '"' . $disabled . '></td>'.
                 '<td>'.
                 $picto." ".$objp->description.
                 '</td>'.
                 '<td>' . $objp->description;

			$picto = img_picto('', './img/no', '', 1);

			//pre($conf->global,1);
			//if(!empty($conf->global->SUPPORDERFROMORDER_USE_ORDER_DESC)) {
				//var_dump('toto');
				print '<input type="hidden" name="desc' . $i . '" value="' . $objp->description . '" />';
				print '<input type="hidden" name="product_type' . $i . '" value="' . $objp->product_type . '" >';
		//	}

			print '</td>';

			print '<td></td>'; // Nature
			if (!empty($conf->categorie->enabled)) print '<td></td>'; // Categories

            if (!empty($conf->service->enabled) && $type == 1) {
                if (preg_match('/([0-9]+)y/i', $objp->duration, $regs)) {
                    $duration =  $regs[1] . ' ' . $langs->trans('DurationYear');
                } elseif (preg_match('/([0-9]+)m/i', $objp->duration, $regs)) {
                    $duration =  $regs[1] . ' ' . $langs->trans('DurationMonth');
                } elseif (preg_match('/([0-9]+)d/i', $objp->duration, $regs)) {
                    $duration =  $regs[1] . ' ' . $langs->trans('DurationDay');
                } else {
                    $duration = $objp->duration;
                }
                print '<td align="center">'.
                     $duration.
                     '</td>';
            }

            if($dolibarr_version35) print '<td align="right">'.$picto.'</td>'; // Desired stock
			print '<td align="right">'.$picto.'</td>'; // Physical/virtual stock
			if ($conf->of->enabled && !empty($conf->global->OF_USE_DESTOCKAGE_PARTIEL)) print '<td align="right">'.$picto.'</td>'; // Stock théorique OF

		    print '<td align="right">
						<input type="text" name="tobuy_free' . $i . '" value="' . $objp->qty . '">
						<input type="hidden" name="lineid_free' . $i . '" value="' . $objp->rowid . '" >
					</td>'; // Ordered

			print '<td align="right">
						<input type="text" name="price_free'.$i.'" value="'.(empty($conf->global->SOFO_COST_PRICE_AS_BUYING)?$objp->price:price($objp->buy_price_ht)).'" size="5" style="text-align:right">€
						'.$form->select_company((empty($socid)?'':$socid),'fourn_free'.$i,'s.fournisseur = 1',1, 0, 0, array(), 0, 'minwidth100 maxwidth300').'
				   </td>'; // Supplier
			print '<td></td>'; // Action
	        print '</tr>';
	        $i++; $j++;
	    }
    }

	// Formatage du tableau
	$TCommonSupplier = array();
	foreach($TSupplier as $fk_fourn) {
		if(!isset($TCommonSupplier[0])) $TCommonSupplier[0] = '';

		$fourn = new Fournisseur($db);
		$fourn->fetch($fk_fourn);

		$TCommonSupplier[$fk_fourn] = $fourn->name;
	}

    print '</table>'.
         '<table width="100%" style="margin-top:15px;">';
    print '<tr>';
	print '<td align="right">';

	print $langs->trans('SelectSameSupplier').' :&nbsp;';
	if(empty($TCommonSupplier)) {
		print '<a class="butActionRefused" href="javascript:" title="'.$langs->trans('NoCommonSupplier').'">'.$langs->trans('Apply').'</a>';
	}
	else {
		print $form->selectarray('useSameSupplier', $TCommonSupplier);
		print '<button type="submit" class="butAction">'.$langs->trans('Apply').'</button>';
	}

	print '</td>';
	print '</tr>';
	print '<tr><td>&nbsp;</td></tr>';
	print '<tr><td align="right">'.
         '<button class="butAction" type="submit" name="action" value="valid-propal">'.$langs->trans("GenerateSupplierPropal").'</button>'.
         '<button class="butAction" type="submit" name="action" value="valid-order">'.$langs->trans("GenerateSupplierOrder").'</button>'.
         '</td></tr></table>'.
         '</form>';


    $db->free($resql);
print ' <script type="text/javascript">';


if($conf->global->SOFO_USE_DELIVERY_TIME) {

	print '
	$( document ).ready(function() {
		//console.log( "ready!" );

		$("[data-info=\'fourn-price\'] select").on("change", function() {
		    var productid = $(this).closest( "tr[data-productid]" ).attr( "data-productid" );
		    var rowi = $(this).closest( "tr[data-productid]" ).attr( "data-i" );
			if ( productid.length ) {
				var fk_price = $(this).val();
				var stocktobuy = $("[name=\'tobuy" + rowi +"\']" ).val();

				var targetUrl = "'.dol_buildpath('/supplierorderfromorder/script/interface.php', 2).'?get=availability&stocktobuy=" + stocktobuy + "&fk_product=" + productid + "&fk_price=" + fk_price ;

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

    $(document).ready(function() {

        $('body').append('<div id="pop-stock" style="border:2px orange solid; position: absolute;width:300px;display:none;padding:10px;background-color:#fff;"></div>');

        $(document).mousemove( function(e) {
           mouseX = e.pageX;
           mouseY = e.pageY;
        });

       $('span.stock_details').each(function(i, item) {

           var prodid = $(this).attr('prod-id');
           var nbweek = $(this).attr('week-to-replenish');

           $(this).mouseover(function() {
               $('#pop-stock').html('Chargement...');
               $('#pop-stock').css({'top':mouseY+20,'left':mouseX-320}).show();

               $.ajax({
                  url : "<?php echo dol_buildpath('/supplierorderfromorder/script/interface.php',1) ?>"
                  ,data:{
                      get : 'stock-details'
                      ,idprod : prodid
                      ,nbweek : nbweek
                  }
               }).done(function(data) {
                    $('#pop-stock').html(data);
               });

           });

           $(this).mouseout(function(){
              $('#pop-stock').hide();
           });

       });

    });


</script>
<?php

llxFooter();

function _prepareLine($i,$actionTarget = 'order')
{
	global $db,$suppliers,$box;
	
	if($actionTarget=='propal')
	{
		$line = new SupplierProposalLine($db);
	}
	else
	{
		$line = new CommandeFournisseurLigne($db); //$actionTarget = 'order'
	}

	//Lignes de produit
	if(!GETPOST('tobuy_free'.$i)){
		$box = $i;
		$supplierpriceid = GETPOST('fourn'.$i, 'int');
		//get all the parameters needed to create a line
		$qty = GETPOST('tobuy'.$i, 'int');
		$desc = GETPOST('desc'.$i, 'alpha');
		$lineid = GETPOST('lineid'.$i, 'int');

		$array_options = array();

		if(! empty($lineid)) {
			$commandeline = new OrderLine($db);
			$commandeline->fetch($lineid);
			if(empty($desc))$desc = $commandeline->desc;
			if(empty($commandeline->id) && ! empty($commandeline->rowid)) {
				$commandeline->id = $commandeline->rowid; // Pas positionné par OrderLine::fetch() donc le fetch_optionals() foire...
			}

			if(empty($commandeline->array_options) && method_exists($commandeline, 'fetch_optionals')) {
				$commandeline->fetch_optionals();
			}

			$array_options = $commandeline->array_options;
		}

		$obj = _getSupplierPriceInfos($supplierpriceid);
		
		if ($obj) {
			
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
			$line->array_options = $array_options;
			
			if(!empty($_REQUEST['tobuy'.$i])) {
				$suppliers[$obj->fk_soc]['lines'][] = $line;
			}
			
		} else {
			$error=$db->lasterror();
			dol_print_error($db);
			dol_syslog('replenish.php: '.$error, LOG_ERR);
		}
		$db->free($resql);
		unset($_POST['fourn' . $i]);
	}
	//Lignes libres
	else
	{

		$box = $i;
		$qty = GETPOST('tobuy_free'.$i, 'int');
		$desc = GETPOST('desc'.$i, 'alpha');
		$product_type = GETPOST('product_type'.$i, 'int');
		$price = price2num(GETPOST('price_free'.$i));
		$lineid = GETPOST('lineid_free'.$i, 'int');
		$fournid = GETPOST('fourn_free'.$i, 'int');
		$commandeline = new OrderLine($db);
		$commandeline->fetch($lineid);
		if(empty($desc))$desc = $commandeline->desc;

		if(empty($commandeline->id) && ! empty($commandeline->rowid)) {
			$commandeline->id = $commandeline->rowid; // Pas positionné par OrderLine::fetch() donc le fetch_optionals() foire...
		}

		if(empty($commandeline->array_options) && method_exists($commandeline, 'fetch_optionals')) {
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
		
		if(!empty($_REQUEST['tobuy_free'.$i])) {
			$suppliers[$fournid]['lines'][] = $line;
		}
	}

}


function _getSupplierPriceInfos($supplierpriceid)
{
	global $db;
	$sql = 'SELECT fk_product, fk_soc, ref_fourn';
	$sql .= ', tva_tx, unitprice, remise_percent FROM ';
	$sql .= MAIN_DB_PREFIX . 'product_fournisseur_price';
	$sql .= ' WHERE rowid = ' . $supplierpriceid;
	
	$resql = $db->query($sql);
	
	if ($resql && $db->num_rows($resql) > 0) {
		//might need some value checks
		return $db->fetch_object($resql);
	}
	
	return false;
}


function _getSupplierOrderInfos($idsupplier, $projectid='')
{
	global $db,$conf;
	
	$sql = 'SELECT rowid, ref';
	$sql .= ' FROM ' . MAIN_DB_PREFIX . 'commande_fournisseur';
	$sql .= ' WHERE fk_soc = '.$idsupplier;
	$sql .= ' AND fk_statut = 0'; // 0 = DRAFT (Brouillon)
	
	if(!empty($conf->global->SOFO_DISTINCT_ORDER_BY_PROJECT) && !empty($projectid)){
		$sql .= ' AND fk_projet = '.$projectid;
	}
	
	$sql .= ' AND entity IN('.getEntity('commande_fournisseur').')';
	$sql .= ' ORDER BY rowid DESC';
	$sql .= ' LIMIT 1';
	
	$resql = $db->query($sql);
	
	if ($resql && $db->num_rows($resql) > 0) {
		//might need some value checks
		return $db->fetch_object($resql);
	}
	
	return false;
}


function _getSupplierProposalInfos($idsupplier, $projectid='')
{
	global $db,$conf;
	
	$sql = 'SELECT rowid, ref';
	$sql .= ' FROM ' . MAIN_DB_PREFIX . 'supplier_proposal';
	$sql .= ' WHERE fk_soc = '.$idsupplier;
	$sql .= ' AND fk_statut = 0'; // 0 = DRAFT (Brouillon)
	
	if(!empty($conf->global->SOFO_DISTINCT_ORDER_BY_PROJECT) && !empty($projectid)){
		$sql .= ' AND fk_projet = '.$projectid;
	}
	
	$sql .= ' AND entity IN('.getEntity('supplier_proposal').')';
	$sql .= ' ORDER BY rowid DESC';
	$sql .= ' LIMIT 1';
	
	$resql = $db->query($sql);
	
	if ($resql && $db->num_rows($resql) > 0) {
		//might need some value checks
		return $db->fetch_object($resql);
	}
	
	return false;
}

function _appliCond($order, $commandeClient){
	global $db, $conf;
	
	if(!empty($conf->global->SOFO_GET_INFOS_FROM_FOURN))
	{
		$fourn = new Fournisseur($db);
		if($fourn->fetch($order->socid) > 0)
		{
			$order->mode_reglement_id = $fourn->mode_reglement_supplier_id;
			$order->mode_reglement_code = getPaiementCode($order->mode_reglement_id);
			
			$order->cond_reglement_id = $fourn->cond_reglement_supplier_id;
			$order->cond_reglement_code = getPaymentTermCode($order->cond_reglement_id);
		}
	}
	
	if($conf->global->SOFO_GET_INFOS_FROM_ORDER){
		$order->mode_reglement_code = $commandeClient->mode_reglement_code;
		$order->mode_reglement_id = $commandeClient->mode_reglement_id;
		$order->cond_reglement_id = $commandeClient->cond_reglement_id;
		$order->cond_reglement_code = $commandeClient->cond_reglement_code;
		$order->date_livraison = $commandeClient->date_livraison;
	}
}

$db->close();

