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
/*echo "<form name=\"formCreateSupplierOrder\" method=\"post\" action=\"cbn.php\">";*/

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
$page = GETPOST('page','int');

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

if (isset($_POST['button_removefilter']) || isset($_POST['valid'])) {
    $sref = '';
    $snom = '';
    $sal = '';
    $salert = '';
}

/*echo "<pre>";
print_r($_REQUEST);
echo "</pre>";
exit;*/


require 'lib/cbn.genorder.php';

/*
 * View
 */
$title = $langs->trans('ProductsToOrder');

$db->query("SET sql_mode=''");

$sql = 'SELECT p.rowid, p.ref, p.label, cd.description, p.price, SUM(cd.qty) as qty';
$sql .= ', p.price_ttc, p.price_base_type,p.fk_product_type';
$sql .= ', p.tms as datem, p.duration, p.tobuy, p.seuil_stock_alerte, p.finished, cd.rang,';
$sql .= ' ( SELECT SUM(s.reel) FROM ' . MAIN_DB_PREFIX . 'product_stock s WHERE s.fk_product=p.rowid ) as stock_physique';
$sql .= $dolibarr_version35 ? ', p.desiredstock' : "";
$sql .= ' FROM ' . MAIN_DB_PREFIX . 'product as p';
$sql .= ' LEFT OUTER JOIN ' . MAIN_DB_PREFIX . 'commandedet as cd ON (p.rowid = cd.fk_product)';

//$sql .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'product_stock as s ON (p.rowid = s.fk_product)';
$sql .= ' WHERE p.fk_product_type IN (0,1) AND p.entity IN (' . getEntity("product", 1) . ')';

$fk_commande = GETPOST('id','int');

if($fk_commande > 0) $sql .= ' AND cd.fk_commande = '.$fk_commande;

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

$finished = GETPOST('finished', 'none');
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

if(isset($_REQUEST['DEBUG']) || $resql===false) {print $sql; var_dump($db);exit;}

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
    $head[0][0] = dol_buildpath('/supplierorderfromorder/cbn.php?id='.$_REQUEST['id'],2);
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
	        		'cbn.php',
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
        		'cbn.php',
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
         '<input type="hidden" name="action" value="order">'.
         '<input type="hidden" name="fk_commande" value="' . GETPOST('fk_commande','int'). '">'.
         '<input type="hidden" name="show_stock_no_need" value="' . GETPOST('show_stock_no_need', 'none'). '">'.

         '<div style="text-align:right"><a href="'.$_SERVER["PHP_SELF"].'?'.$_SERVER["QUERY_STRING"].'&show_stock_no_need=yes">'.$langs->trans('ShowLineEvenIfStockIsSuffisant').'</a></div>'.
         '<table class="liste" width="100%">';

    if($conf->global->SOFO_USE_DELIVERY_TIME) {
            $week_to_replenish = (int)GETPOST('week_to_replenish','int');

            $colspan = empty($conf->global->FOURN_PRODUCT_AVAILABILITY) ? 7 : 8;

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
    		'cbn.php',
    		'p.ref',
    		$param,
    		'id='.$_REQUEST['id'],
    		'',
    		$sortfield,
    		$sortorder
    );
    print_liste_field_titre(
    		$langs->trans('Label'),
    		'cbn.php',
    		'p.label',
    		$param,
    		'id='.$_REQUEST['id'],
    		'',
    		$sortfield,
    		$sortorder
    );
    print_liste_field_titre(
    		$langs->trans('Nature'),
    		'cbn.php',
    		'p.label',
    		$param,
    		'id='.$_REQUEST['id'],
    		'',
    		$sortfield,
    		$sortorder
    );
    if (!empty($conf->service->enabled) && $type == 1)
    {
    	print_liste_field_titre(
    			$langs->trans('Duration'),
    			'cbn.php',
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
	    		'cbn.php',
	    		'p.desiredstock',
	    		$param,
	    		'id='.$_REQUEST['id'],
	    		'align="right"',
	    		$sortfield,
	    		$sortorder
	    );
	}

    if ($conf->global->USE_VIRTUAL_STOCK)
    {
        $stocklabel = $langs->trans('VirtualStock');
    }
    else
    {
        $stocklabel = $langs->trans('PhysicalStock');
    }
    print_liste_field_titre(
    		$stocklabel,
    		'cbn.php',
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
	    		'cbn.php',
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
    		'cbn.php',
    		'',
    		$param,
    		'id='.$_REQUEST['id'],
    		'align="right"',
    		$sortfield,
    		$sortorder
    );
    print_liste_field_titre(
    		$langs->trans('StockToBuy'),
    		'cbn.php',
    		'',
    		$param,
    		'id='.$_REQUEST['id'],
    		'align="right"',
    		$sortfield,
    		$sortorder
    );

	print '<td></td>';

   	if (!empty($conf->global->FOURN_PRODUCT_AVAILABILITY)) print_liste_field_titre($langs->trans("Availability"));

    print_liste_field_titre(
    		$langs->trans('Supplier'),
    		'cbn.php',
    		'',
    		$param,
    		'id='.$_REQUEST['id'],
    		'align="right"',
    		$sortfield,
    		$sortorder
    );
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
	$liste_titre.= '<td class="liste_titre">'.$form->selectarray('finished',$statutarray,(!isset($_REQUEST['button_search_x']) && $conf->global->SOFO_DEFAUT_FILTER != -1) ? $conf->global->SOFO_DEFAUT_FILTER : GETPOST('finished', 'none'),1).'</td>';
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

    while ($i < min($num, $limit)) {
    	$objp = $db->fetch_object($resql);
//if($objp->rowid == 4666) { var_dump($objp); }

        if ($conf->global->STOCK_SUPPORTS_SERVICES
           || $objp->fk_product_type == 0) {
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
				$help_stock.=', '.$langs->trans('OF').' : '.(float)($stock_of_needed - $stock_of_tomake);
			}

			$help_stock.=', '.$langs->trans('DesiredStock').' : '.(float)$objp->desiredstock;


			if($stocktobuy < 0) $stocktobuy = 0;

			if($stocktobuy == 0 && GETPOST('show_stock_no_need', 'none')!='yes') {
				$i++;
				continue;
			}

			$var =! $var;
            print '<tr ' . $bc[$var] . '>'.
                 '<td><input type="checkbox" class="check" name="check' . $i . '"' . $disabled . '></td>'.
                 '<td style="height:35px;" class="nowrap">'.
                 (!empty($TDemandes) ? $form->textwithpicto($prod->getNomUrl(1), 'Demande(s) de prix en cours :<br />'.implode(', ', $TDemandes), 1, 'help') : $prod->getNomUrl(1)).
                 '</td>'.
                 '<td>' . $objp->label . '</td>';

	        print '<td>'.$statutarray[$objp->finished].'</td>';

				if(!empty($conf->global->SUPPORDERFROMORDER_USE_ORDER_DESC)) {
					print '<input type="hidden" name="desc' . $i . '" value="' . $objp->description . '" >';
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
                 $warning . $stock.
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

					$champs.= '<td>'.($nb_day == 0 ? $langs->trans('Unknown') : $nb_day.' '.$langs->trans('Days')).'</td>';

				}



                 $champs.='<td align="right">'.
                 $form->select_product_fourn_price($prod->id, 'fourn'.$i, 1).
                 '</td>';
				print $champs;

       if($conf->of->enabled && $user->rights->of->of->write) {
		print '<td><a href="'.dol_buildpath('/of/fiche_of.php',1).'?action=new&fk_product='.$prod->id.'" class="butAction">Fabriquer</a></td>';
	   }
	   else {
	    	print '<td>&nbsp</td>';
	   }
           print '</tr>';
        }

//	if($prod->ref=='A0000753') exit;

        flush();

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
                 '<td>' . $objp->description . '</td>';

			$picto = img_picto('', './img/no', '', 1);

			//pre($conf->global,1);
			//if(!empty($conf->global->SUPPORDERFROMORDER_USE_ORDER_DESC)) {
				//var_dump('toto');
				print '<input type="hidden" name="desc' . $i . '" value="' . $objp->description . '" />';
				print '<input type="hidden" name="product_type' . $i . '" value="' . $objp->product_type . '" >';
		//	}

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
			print '<td colspan=2></td>';
			print '<td align="right">'.$picto.'</td>';
			print '<td align="right">'.$picto.'</td>';
		    print '<td align="right">'.
                 '<input type="text" name="tobuy_free' . $i .
                 '" value="' . $objp->qty . '">'.
                 '</td>';

			print '<input type="hidden" name="lineid_free' . $i . '" value="' . $objp->rowid . '" >';

			print '<td align="right">
						<input type="text" name="price_free'.$i.'" value="'.(empty($conf->global->SOFO_COST_PRICE_AS_BUYING)?$objp->price:price($objp->buy_price_ht)).'" size="5" style="text-align:right">€
						'.$form->select_company((empty($socid)?'':$socid),'fourn_free'.$i,'s.fournisseur = 1',1, 0, 0, array(), 0, 'minwidth100 maxwidth300').'
				   </td>';
			print '<td></td>';
	        print '</tr>';
	        $i++; $j++;
	    }
    }

    $value = $langs->trans("GenerateSupplierOrder");
    print '</table>'.
         '<table width="100%" style="margin-top:15px;">'.
         '<tr><td align="right">'.
         '<input class="butAction" type="submit" name="valid" value="' . $value . '">'.
         '</td></tr></table>'.
         '</form>';


    $db->free($resql);
print ' <script type="text/javascript">
     function toggle(source)
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

$db->close();

