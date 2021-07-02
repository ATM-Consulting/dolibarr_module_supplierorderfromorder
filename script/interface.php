<?php
if (!defined("NOCSRFCHECK")) define('NOCSRFCHECK', 1);

if (!defined('NOTOKENRENEWAL')) define('NOTOKENRENEWAL', 1); // Disables token renewal

require('../config.php');

dol_include_once('/product/class/product.class.php');
dol_include_once('/product/class/product.class.php');
dol_include_once('/fourn/class/fournisseur.commande.class.php');
dol_include_once("/fourn/class/fournisseur.class.php");
dol_include_once("/fourn/class/fournisseur.product.class.php");
dol_include_once("/commande/class/commande.class.php");
dol_include_once('/product/stock/class/entrepot.class.php');
dol_include_once('/expedition/class/expedition.class.php');
dol_include_once('/supplierorderfromorder/class/sofo.class.php');
dol_include_once('/core/class/html.form.class.php');


$get = GETPOST('get', 'none');

if($get=='availability')
{
	_getAvailability();
}
elseif($get=='stock-details')
{
	_stockDetails();
}



function _getAvailability()
{
	global $db,$langs;


	$qty= GETPOST('stocktobuy', 'none');
	$fk_product= GETPOST('fk_product','int');
	$fk_price= GETPOST('fk_price','int');
	$fk_fourn = 0;

	if($fk_product)
	{
		$productFournisseur= new ProductFournisseur($db);
		if($productFournisseur->fetch($fk_product)>0)
		{
			if($productFournisseur->fetch_product_fournisseur_price($fk_price, 1)>0) //Ignore the math expression when getting the price
			{
				$fk_fourn = $productFournisseur->fourn_id;
			}
		}

		$nb_day = (int)TSOFO::getMinAvailability($fk_product, $qty,1,$fk_fourn);
		print ($nb_day == 0 ? $langs->trans('Unknown') : $nb_day.' '.$langs->trans('Days'));
	}
	exit();
}

function _stockDetails()
{
	global $db,$langs,$conf;
	$prod = new Product($db);
	$prod->fetch(GETPOST('idprod', 'int'));


	if($prod->id<=0)exit;

	$prod->load_stock();

	$r ='';
	foreach($prod->stock_warehouse as $fk_warehouse=>$obj) {

		$e=new Entrepot($db);
		$e->fetch($fk_warehouse);
		$r .='<br />'.$e->getNomUrl(1).' x '.$obj->real;

	}
	if(!empty($r)) {
		print '<p>';
		print '<strong>Stock physique</strong>';
		print $r;
		print '</p>';


	}

	if(empty($conf->global->SOFO_USE_ONLY_OF_FOR_NEEDED_PRODUCT)) {
		$sql = "SELECT DISTINCT c.rowid, SUM(cd.qty) as qty"; //cas où on a plusieurs fois le même produit dans la même commande
		$sql.= " FROM ".MAIN_DB_PREFIX."commandedet as cd";
		$sql.= ", ".MAIN_DB_PREFIX."commande as c";
		$sql.= ", ".MAIN_DB_PREFIX."societe as s";
		$sql.= " WHERE c.rowid = cd.fk_commande";
		$sql.= " AND c.fk_soc = s.rowid";
		$sql.= " AND c.entity = ".$conf->entity;
		$sql.= " AND cd.fk_product = ".$prod->id;
		$sql.= " AND c.fk_statut in (1,2)";
		$sql.= " GROUP BY c.rowid";

		$r ='';
		$result =$db->query($sql);
		while($obj = $db->fetch_object($result)) {

			$c=new Commande($db);
			$c->fetch($obj->rowid);

			$r.='<br />'.$c->getNomUrl(1).' x '.$obj->qty.'';

		}

		if(!empty($r)) {
			print '<p>';
			print '<strong>Commande client</strong>';
			print $r;
			print '</p>';


		}


		$sql = "SELECT cd.qty, c.rowid";
		$sql.= " FROM ".MAIN_DB_PREFIX."commande_fournisseurdet as cd";
		$sql.= ", ".MAIN_DB_PREFIX."commande_fournisseur as c";
		$sql.= ", ".MAIN_DB_PREFIX."societe as s";
		$sql.= " WHERE c.rowid = cd.fk_commande";
		$sql.= " AND c.fk_soc = s.rowid";
		$sql.= " AND c.entity = ".$conf->entity;
		$sql.= " AND cd.fk_product = ".$prod->id;
		$sql.= " AND c.fk_statut in (3)";

		$r ='';
		$result =$db->query($sql);
		while($obj = $db->fetch_object($result)) {

			$c=new CommandeFournisseur($db);
			$c->fetch($obj->rowid);

			$r.='<br />'.$c->getNomUrl(1).' x '.$obj->qty.'';

		}

		if(!empty($r)) {
			print '<p>';
			print '<strong>Commande fournisseur</strong>';
			print $r;
			print '</p>';


		}



	}





	if(!empty($conf->of->enabled)) {


		define('INC_FROM_DOLIBARR', true);
		dol_include_once('/of/config.php');
		dol_include_once('/of/class/ordre_fabrication_asset.class.php');
		$PDOdb=new TPDOdb;
		$sql="SELECT ofe.rowid, ofe.numero, ofe.date_lancement , ofe.date_besoin, ofel.qty, ofel.qty_needed
        , ofe.status, ofe.fk_user, ofe.total_cost
          FROM ".MAIN_DB_PREFIX."assetOf as ofe
          LEFT JOIN ".MAIN_DB_PREFIX."assetOf_line ofel ON (ofel.fk_assetOf=ofe.rowid AND ofel.type = 'TO_MAKE')
          WHERE ofe.entity=".$conf->entity." AND ofel.fk_product=".$prod->id." AND ofe.status IN ('VALID','OPEN')
          ORDER BY date_besoin ASC"
          		;


          		$PDOdb->Execute($sql);
          		$res = '';
          		while($obj = $PDOdb->Get_line()) {
          			$res.= '<br /><a href="'.dol_buildpath('/of/fiche_of.php?id='.$obj->rowid,1).'">'.img_picto('','object_list.png','',0).' '.$obj->numero.'</a> x '.($obj->qty ? $obj->qty : $obj->qty_needed );
          		}

          		if(!empty($res)) {
          			print '<p>';
          			print '<strong>Fabriqué par OF</strong>';
          			print $res;
          			print '</p>';


          		}

          		$sql="SELECT ofe.rowid, ofe.numero, ofe.date_lancement , ofe.date_besoin, ofel.qty, ofel.qty_needed
        , ofe.status, ofe.fk_user, ofe.total_cost
          FROM ".MAIN_DB_PREFIX."assetOf as ofe
          LEFT JOIN ".MAIN_DB_PREFIX."assetOf_line ofel ON (ofel.fk_assetOf=ofe.rowid AND ofel.type = 'NEEDED')
          WHERE ofe.entity=".$conf->entity." AND ofel.fk_product=".$prod->id." AND ofe.status IN ('VALID','OPEN')
          ORDER BY date_besoin ASC"
          		;


          		$PDOdb->Execute($sql);
          		$res = '';
          		while($obj = $PDOdb->Get_line()) {
          			$res.= '<br /><a href="'.dol_buildpath('/of/fiche_of.php?id='.$obj->rowid,1).'">'.img_picto('','object_list.png','',0).' '.$obj->numero.'</a> x '.($obj->qty ? $obj->qty : $obj->qty_needed );
          		}

          		if(!empty($res)) {
          			print '<p>';
          			print '<strong>Besoin dans OF</strong>';
          			print $res;
          			print '</p>';


          		}
	}
    $filterShipmentStatus = Expedition::STATUS_VALIDATED;
    if(! empty($conf->global->STOCK_CALCULATE_ON_SHIPMENT)) {
        $filterShipmentStatus = Expedition::STATUS_VALIDATED.','.Expedition::STATUS_CLOSED;
    }
    else if(! empty($conf->global->STOCK_CALCULATE_ON_SHIPMENT_CLOSE)) {
        $filterShipmentStatus = Expedition::STATUS_CLOSED;
    }

	$sql = "SELECT DISTINCT e.rowid, ed.qty";
	$sql.= " FROM ".MAIN_DB_PREFIX."expeditiondet as ed";
	$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."expedition as e ON (e.rowid=ed.fk_expedition)";
	$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."commandedet as cd ON (ed.fk_origin_line=cd.rowid)";
	$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."commande as c ON (cd.fk_commande=c.rowid)";
	$sql.= " WHERE 1";
	$sql.= " AND e.entity = ".$conf->entity;
	$sql.= " AND cd.fk_product = ".$prod->id;
	$sql.= " AND c.fk_statut in (".Commande::STATUS_VALIDATED.','.Commande::STATUS_ACCEPTED.")"; //récup du load_stats d'un produit
	$sql.= " AND e.fk_statut in ($filterShipmentStatus)";

	$r ='';
	$result =$db->query($sql);
	//var_dump($db);
	while($obj = $db->fetch_object($result)) {

		$e=new Expedition($db);
		$e->fetch($obj->rowid);

		$r.='<br />'.$e->getNomUrl(1).' x '.$obj->qty.'';

	}


	if(!empty($r)) {
		print '<p>';
		print '<strong>Expéditions</strong>';
		print $r;
		print '</p>';


	}

}
