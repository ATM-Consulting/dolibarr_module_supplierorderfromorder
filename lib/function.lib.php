<?php

dol_include_once('/supplierorderfromorder/class/sofo.class.php');

function getDayFromAvailabilityCode($av_code) {
	return TSOFO::getDayFromAvailabilityCode($av_code);
}
function getMinAvailability($fk_product, $qty) {
	global $db,$form;
	return TSOFO::getMinAvailability($fk_product, $qty);
}


function _load_stats_commande_fournisseur($fk_product, $date,$stocktobuy=1,$filtrestatut='3') {
    global $conf,$user,$db;

    $nb_day = (int)getMinAvailability($fk_product,$stocktobuy);
    $date = date('Y-m-d', strtotime('-'.$nb_day.'day',  strtotime($date)));

    $sql = "SELECT SUM(cd.qty) as qty";
    $sql.= " FROM ".$db->prefix()."commande_fournisseurdet as cd";
    $sql.= ", ".$db->prefix()."commande_fournisseur as c";
    $sql.= ", ".$db->prefix()."societe as s";
    $sql.= " WHERE c.rowid = cd.fk_commande";
    $sql.= " AND c.fk_soc = s.rowid";
    $sql.= " AND c.entity = ".$conf->entity;
    $sql.= " AND cd.fk_product = ".$fk_product;
    $sql.= " AND (c.delivery_date IS NULL OR c.delivery_date <= '".$date."') ";
    if ($filtrestatut != '') $sql.= " AND c.fk_statut in (".$filtrestatut.")";

    $result =$db->query($sql);
    if ( $result )
    {
            $obj = $db->fetch_object($result);
            return (float)$obj->qty;
    }
    else
    {

        return 0;
    }
}

function _load_stats_commande_date($fk_product, $date,$filtrestatut='1,2') {
        global $conf,$user,$db;

        $sql = "SELECT SUM(cd.qty) as qty";
        $sql.= " FROM ".$db->prefix()."commandedet as cd";
        $sql.= ", ".$db->prefix()."commande as c";
        $sql.= ", ".$db->prefix()."societe as s";
        $sql.= " WHERE c.rowid = cd.fk_commande";
        $sql.= " AND c.fk_soc = s.rowid";
        $sql.= " AND c.entity = ".$conf->entity;
        $sql.= " AND cd.fk_product = ".$fk_product;
        $sql.= " AND (c.delivery_date IS NULL OR c.delivery_date <='".$date."') ";
        if ($filtrestatut <> '') $sql.= " AND c.fk_statut in (".$filtrestatut.")";

        $result =$db->query($sql);
        if ( $result )
        {
                $obj = $db->fetch_object($result);
                return (float)$obj->qty;
        }
        else
        {

            return 0;
        }
}

function getExpedie($fk_product) {
    global $conf, $db;

    $sql = "SELECT SUM(ed.qty) as qty";
    $sql.= " FROM ".$db->prefix()."expeditiondet as ed";
    $sql.= " LEFT JOIN ".$db->prefix()."expedition as e ON (e.rowid=ed.fk_expedition)";
	if ((float) DOL_VERSION < 20) $sql.= " LEFT JOIN ".$db->prefix()."commandedet as cd ON (ed.fk_origin_line=cd.rowid)";
    else $sql.= " LEFT JOIN ".$db->prefix()."commandedet as cd ON (ed.fk_elementdet=cd.rowid)";
    $sql.= " WHERE 1";
    $sql.= " AND e.entity = ".$conf->entity;
    $sql.= " AND cd.fk_product = ".$fk_product;
    $sql.= " AND e.fk_statut in (1)";

    $result =$db->query($sql);
    if ( $result )
    {
            $obj = $db->fetch_object($result);
            return (float)$obj->qty;
    }
    else
    {

        return 0;
    }

}

function getPaiementCode($id) {

	global $db;

	if(empty($id)) return '';

	$sql = 'SELECT code FROM '.$db->prefix().'c_paiement WHERE id = '.$id;
	$resql = $db->query($sql);
	$res = $db->fetch_object($resql);

	return $res->code;
}


function getPaymentTermCode($id) {

	global $db;

	if(empty($id)) return '';

	$sql = 'SELECT code FROM '.$db->prefix().'c_payment_term WHERE rowid = '.$id;
	$resql = $db->query($sql);
	$res = $db->fetch_object($resql);

	return $res->code;
}


function getCatMultiselect($htmlname, $TCategories)
{
	global $form, $langs;

	$maxlength=64;
	$excludeafterid=0;
	$outputmode=1;
	$array=$form->select_all_categories('product', $TCategories, $htmlname, $maxlength, $excludeafterid, $outputmode);
	$array[-1] = '('.$langs->trans('NoFilter').')';

	$key_in_label=0;
	$value_as_key=0;
	$morecss='';
	$translate=0;
	$width='80%';
	$moreattrib='';
	$elemtype='';

	return $form->multiselectarray($htmlname, $array, $TCategories, $key_in_label, $value_as_key, $morecss, $translate, $width, $moreattrib,$elemtype);
}



function getSupplierOrderAvailable($supplierSocId,$shippingContactId=0,$array_options=array(),$restrictToCustomerOrder = 0)
{
    global $db, $conf;
    $shippingContactId = intval($shippingContactId);
    $status = intval($status);

    $Torder = array();

    $sql = 'SELECT cf.rowid ';
    $sql .= ' FROM ' . $db->prefix() . 'commande_fournisseur cf ';
    $sql .= ' LEFT JOIN ' . $db->prefix() . 'commande_fournisseur_extrafields cfext ON (cfext.fk_object = cf.rowid) ';

    if(!empty($shippingContactId))
    {
        $sql .= ' JOIN  ' . $db->prefix() . 'element_contact ec ON (ec.element_id = fk_target AND ec.fk_socpeople = '.$shippingContactId.') ';
    }

    $sql .= ' WHERE cf.fk_soc = '.intval($supplierSocId).' ';

    $sql .= ' AND cf.fk_statut = 0 ';
    $sql .= ' AND cf.ref LIKE "(PROV%" ';


    if(!empty($array_options))
    {
        foreach ($array_options as $col => $value)
        {
            $sql .= ' AND cfext.`'.$col.'` = \''.$value.'\' ';
        }
    }
    //print $sql;
    $resql=$db->query($sql);
    if ($resql)
    {
        while ($obj = $db->fetch_object($resql))
        {
            $restriction = false;

            if($restrictToCustomerOrder>0){
                // recherche des commandes client liées
                $TLinkedObject = getLinkedObject($obj->rowid,'order_supplier','commande');
                if(!empty($TLinkedObject) && is_array($TLinkedObject)){
                    foreach($TLinkedObject as $commandeId){
                        // comparaison avec la commande recherchée
                        if((int)$commandeId != (int)$restrictToCustomerOrder){
                            $restriction = true;
                            break;
                        }
                    }
                }
                else{
                    $restriction = true;
                }
            }

            if(!$restriction){
                $Torder[] = $obj->rowid;
            }
        }



        return $Torder;
    }

    return -1;

}

/**
 * Création ou mise à jour de la commande fournisseur selon la conf
 * getDolGlobalString('SOFO_CREATE_NEW_SUPPLIER_ODER_ANY_TIME')
 *
 * @param OrderLine $line
 * @param int $supplierSocId
 * @param int $shippingContactId
 * @param int $supplierOrderStatus
 * @param bool $createCommande
 * @param bool $fetchCommande
 * @return CommandeFournisseur
 */
function getSupplierOrderToUpdate(OrderLine $line, int $supplierSocId, int $shippingContactId, int $supplierOrderStatus, bool $createCommande = false, bool $fetchCommande = false) :CommandeFournisseur
{
	dol_include_once('fourn/class/fournisseur.commande.class.php');

	global $db, $user, $langs;

	$array_options = array();
	$CommandeFournisseur = new CommandeFournisseur($db);

	$societe = new Societe($db);
	$res = $societe->fetch($supplierSocId);

	if ($res < 0){
		setEventMessage('NoCreateSupplierOrderMissingSociete', 'errors');
		return $CommandeFournisseur; // pas de société retourne la commande null
	}

	// search and get draft supplier order linked
	$TSearchSupplierOrder = getLinkedSupplierOrderFromOrder($line->fk_commande, $supplierSocId, $shippingContactId, $supplierOrderStatus);
	if(empty($TSearchSupplierOrder)) {
		$restrictToCustomerOrder = 0; // search draft supplier order with same critera
		if(getDolGlobalString('SOFO_USE_RESTRICTION_TO_CUSTOMER_ORDER')){
			$restrictToCustomerOrder = $line->fk_commande;
		}
		$TSearchSupplierOrder = getSupplierOrderAvailable($supplierSocId, $shippingContactId, $array_options, $restrictToCustomerOrder);
	}
	if (!is_array($TSearchSupplierOrder)) {
		setEventMessage('NoCreateSupplierOrderErrorSearch', 'errors');
		return $CommandeFournisseur; // pas de $TSearchSupplierOrder retourne la commande null
	}
	//======================================================================================================
	// Section concernant la Conf "Créer une commande fournisseur brouillon pour chaque commande client"

	/**
	 * Création de commande fournisseur si :
	 * SI Aucune commande contenue dans $TSearchSupplierOrder
	 * OU mon parametre de fonction $createCommande est à true et $fetchCommande à false
	 * OU si ma conf de module SOFO_CREATE_NEW_SUPPLIER_ODER_ANY_TIME "Créer une commande fournisseur brouillon pour chaque commande client" est a TRUE ET que $fetchCommande est à false
	 */
	if ((($createCommande && !$fetchCommande ) || empty($TSearchSupplierOrder))
		|| (!$fetchCommande && getDolGlobalString('SOFO_CREATE_NEW_SUPPLIER_ODER_ANY_TIME'))) {
		$CommandeFournisseur->socid = $supplierSocId;
		$CommandeFournisseur->mode_reglement_id = $societe->mode_reglement_supplier_id;
		$CommandeFournisseur->cond_reglement_id = $societe->cond_reglement_supplier_id;
		$res = $CommandeFournisseur->create($user);
		if ($res){
			setEventMessage($langs->trans('supplierOrderCreated', $CommandeFournisseur->ref));
		}
	}
	/**
	 * On fetch la dernière commande fournisseur de mon tableau $TSearchSupplierOrder si :
	 * Si ma conf de module SOFO_CREATE_NEW_SUPPLIER_ODER_ANY_TIME "Créer une commande fournisseur brouillon pour chaque commande client" est à FALSE
	 * OU mon parametre de fonction $fetchCommande est à true ET SOFO_CREATE_NEW_SUPPLIER_ODER_ANY_TIME est true
	 */
	if (!getDolGlobalString('SOFO_CREATE_NEW_SUPPLIER_ODER_ANY_TIME') || ($fetchCommande && getDolGlobalString('SOFO_CREATE_NEW_SUPPLIER_ODER_ANY_TIME'))){
		$lastValue = end($TSearchSupplierOrder);
		$res = $CommandeFournisseur->fetch($lastValue);
	}
	if ($res) {
		$CommandeFournisseur->add_object_linked('commande', $line->fk_commande);
	}else{
		setEventMessage($langs->trans('supplierOrderNotCreated', $line->product_ref ));
		dol_syslog(get_class($line)."::getSupplierOrderToUpdate ".$line->error, LOG_ERR);
	}
	//======================================================================================================
	return $CommandeFournisseur;

}

/**
 * @param $CommandeFournisseur
 * @param $line
 * @param $productid
 * @param $price
 * @param $qty
 * @param $supplierSocId
 * @return array
 */
function updateOrAddlineToSupplierOrder($CommandeFournisseur, $line, $productid, $price, $qty, $supplierSocId)
{
	global $db, $conf;

	$ret = array(
		'return' => 0,
		'mode' => 'add'
	);

	if (empty($productid)) $productid = $line->fk_product;

	// Get subprice from product
	if(!empty($productid)){
		$ProductFournisseur = new ProductFournisseur($db);
		if($ProductFournisseur->find_min_price_product_fournisseur($productid, $qty, $supplierSocId) > 0){
			$price = floatval($ProductFournisseur->fourn_unitprice); // floatval is used to remove non used zero
			$tva_tx = $ProductFournisseur->tva_tx;
			$fk_prod_fourn_price = $ProductFournisseur->product_fourn_price_id;
			$remise_percent = $ProductFournisseur->fourn_remise_percent;
			$ref_supplier= $ProductFournisseur->ref_supplier;
		}
	}

	//récupération du prix d'achat de la line si pas de prix fournisseur
	if(empty($price) && !empty($line->pa_ht) ){
		$price = $line->pa_ht;
	}

	// SEARCH in supplier order if same product exist
	$supplierLineRowidExist = 0 ;
	if(!empty($CommandeFournisseur->lines) && getDolGlobalInt('SOFO_ADD_QUANTITY_RATHER_THAN_CREATE_LINES') )
	{
		foreach ($CommandeFournisseur->lines as $li => $fournLine)
		{
			if(
				$fournLine->ref_supplier == $ref_supplier
				&& $fournLine->fk_product == $productid
			)
			{
				$supplierLineRowidExist = $fournLine->id;
				$fournLine->fetch_product();
				break;
			}
		}
	}

	// UPDATE SUPPLIER LINE
	if($supplierLineRowidExist>0)
	{
		$ret['mode'] = 'update';
		$ret['return'] = $CommandeFournisseur->updateline(
			$fournLine->id,
			$fournLine->desc,
			$fournLine->subprice,
			$fournLine->qty + $qty,
			$fournLine->remise_percent,
			$fournLine->tva_tx,
			$fournLine->localtax1_tx,
			$fournLine->localtax2_tx,
			'HT',
			0,
			$fournLine->product->type,
			0, // $notrigger
			'',
			'',
			$fournLine->array_options,
			$fournLine->product->fk_unit,
			0, // $pu_ht_devise
			$fournLine->ref_supplier
		);

		if ($ret['return'] >= 0) $ret['return'] = $fournLine->id;// yes $CommandeFournisseur->updateline can return 0 on success

	}
	else
	{
		// les object sont passés par référence par défaut
		// l'object line est la ligne de commande initiale
		// nous sommes en train de modifier cette ligne si nous ne clonons pas celle-ci
		$lineClone = clone $line;
		if($lineClone->fk_product != $productid) $lineClone->fk_product = $productid;
		$res = $lineClone->fetch_product();
		if($res > 0) {
			$fk_unit = $lineClone->product->fk_unit;
			$product_type = $lineClone->product->type;
		} else {
			$fk_unit = $lineClone->fk_unit;
			$product_type = $lineClone->product_type;
		}
		// ADD LINE
		$ret['return'] = $CommandeFournisseur->addline(
			$lineClone->desc,
			$price,
			$qty,
			$lineClone->tva_tx,
			$txlocaltax1=0.0,
			$txlocaltax2=0.0,
			$productid,
			$fk_prod_fourn_price,
			$ref_supplier,
			$remise_percent,
			'HT',
			0, //$pu_ttc=0.0,
			$product_type,
			$lineClone->info_bits,
			false, //$notrigger=false,
			null, //$date_start=null,
			null, //$date_end=null,
			$lineClone->array_options, //$array_options=0,
			$fk_unit,
			0,//$pu_ht_devise=0,
			'commandedet', //$origin= // peut être un jour ça sera géré...
			$lineClone->id //$origin_id=0 // peut être un jour ça sera géré...
		);
	}

	return $ret;
}

/**
 * @param $sourceCommandeId
 * @param $supplierSocId
 * @param int $shippingContactId
 * @param int $status
 * @param array $array_options
 * @return array|int
 */
function getLinkedSupplierOrderFromOrder($sourceCommandeId,$supplierSocId,$shippingContactId=0,$status=-1,$array_options=array())
{
    global $db, $conf;
    $shippingContactId = intval($shippingContactId);
    $status = intval($status);

    $Torder = array();

    $sql = 'SELECT ee.fk_target ';
    $sql .= ' FROM ' . $db->prefix() . 'element_element ee';
    $sql .= ' JOIN ' . $db->prefix() . 'commande_fournisseur cf ON (ee.fk_target = cf.rowid) ';
    $sql .= ' LEFT JOIN ' . $db->prefix() . 'commande_fournisseur_extrafields cfext ON (cfext.fk_object = cf.rowid) ';

    if(!empty($shippingContactId))
    {
        $sql .= ' JOIN  ' . $db->prefix() . 'element_contact ec ON (ec.element_id = fk_target AND ec.fk_socpeople = '.$shippingContactId.') ';
    }

    $sql .= ' WHERE ee.fk_source = '.intval($sourceCommandeId).' ';
    $sql .= ' AND ee.sourcetype = \'commande\' ';
    $sql .= ' AND cf.fk_soc =  '.intval($supplierSocId).' ';
    $sql .= ' AND ee.targettype = \'order_supplier\' ';

    if($status>=0)
    {
        $sql .= ' AND cf.fk_statut = '.$status.' ';
    }

    if(!empty($array_options))
    {
        foreach ($array_options as $col => $value)
        {
            $sql .= ' AND cfext.`'.$col.'` = \''.$value.'\' ';
        }
    }

    $resql=$db->query($sql);
    if ($resql)
    {
        while ($obj = $db->fetch_object($resql))
        {
            $Torder[] = $obj->fk_target;
        }

        return $Torder;
    }

    return -1;

}

/**
 * @param null $sourceid
 * @param string $sourcetype
 * @param string $targettype
 * @return array|int
 */
function getLinkedObject($sourceid=null,$sourcetype='',$targettype='')
{
    global $db;
    $TElement=array();

    $sql = 'SELECT fk_target ';
    $sql .= ' FROM ' . $db->prefix() . 'element_element ee';
    $sql .= ' WHERE ee.fk_source = '.intval($sourceid).' ';
    $sql .= ' AND ee.sourcetype = \''.$db->escape($sourcetype).'\' ';
    if(!empty($targettype)){
        $sql .= ' AND ee.targettype = \''.$db->escape($targettype).'\' ';
    }

    $resql=$db->query($sql);
    if ($resql)
    {
        while($obj = $db->fetch_object($resql))
        {
            $TElement[] = $obj->fk_target;
        }
    }

    // search for opposite

    $sql = 'SELECT fk_target ';
    $sql .= ' FROM ' . $db->prefix() . 'element_element ee';
    $sql .= ' WHERE ee.fk_target = '.intval($sourceid).' ';
    $sql .= ' AND ee.targettype = \''.$db->escape($sourcetype).'\' ';
    if(!empty($targettype)){
        $sql .= ' AND ee.sourcetype = \''.$db->escape($targettype).'\' ';
    }

    $resql=$db->query($sql);
    if ($resql)
    {
        while($obj = $db->fetch_object($resql))
        {
            $TElement[] = $obj->fk_source;
        }
    }


    return !empty($TElement)?$TElement:0;

}

/**
 * @param $sourceCommandeLineId
 * @param string $sourcetype
 * @return int
 */
function getLinkedSupplierOrderLineFromElementLine($sourceCommandeLineId, $sourcetype = 'commandedet')
{
    $TElement = getLinkedSupplierOrdersLinesFromElementLine($sourceCommandeLineId, $sourcetype);
    if (!empty($TElement))
    {
        return (int)$TElement[0];
    }
    return 0;
}

/**
 * @param $sourceCommandeLineId
 * @param string $sourcetype
 * @return array|int
 */
function getLinkedSupplierOrdersLinesFromElementLine($sourceCommandeLineId, $sourcetype = 'commandedet')
{
    global $db;

    $sql = 'SELECT fk_target ';
    $sql .= ' FROM ' . $db->prefix() . 'element_element ee';
    $sql .= ' WHERE ee.fk_source = '.intval($sourceCommandeLineId).' ';
    $sql .= ' AND ee.sourcetype = \''.$db->escape($sourcetype).'\' ';
    $sql .= ' AND ee.targettype = \'commande_fournisseurdet\' ';

    $TElement=array();

    $resql=$db->query($sql);
    if ($resql)
    {
        while($obj = $db->fetch_object($resql))
        {
            $TElement[] = $obj->fk_target;
        }

        return $TElement;
    }

    return 0;

}

/**
 * @param $sourceCommandeLineId
 * @return int
 */
function getLinkedOrderLineFromSupplierOrderLine($sourceCommandeLineId)
{
    global $db;

    $sql = 'SELECT fk_source ';
    $sql .= ' FROM ' . $db->prefix() . 'element_element ee';
    $sql .= ' WHERE ee.fk_target = '.intval($sourceCommandeLineId).' ';
    $sql .= ' AND ee.sourcetype = \'commandedet\' ';
    $sql .= ' AND ee.targettype = \'commande_fournisseurdet\' ';

    $resql=$db->query($sql);
    if ($resql && $obj = $db->fetch_object($resql))
    {
        return $obj->fk_source;
    }
    return 0;

}

/**
 * @param $fk_unit
 * @param string $return
 * @return int|string
 */
function getUnitLabel($fk_unit, $return = 'code')
{
    global $db, $langs;

    $sql = 'SELECT label, code from '.$db->prefix().'c_units';
    $sql.= ' WHERE rowid = '.intval($fk_unit);

    $resql=$db->query($sql);
    if ($resql && $obj = $db->fetch_object($resql))
    {
        if($return == 'label'){
            return $langs->trans('unit'.$obj->code);
        }else{
            return $obj->code;
        }

    }
    return '';
}

/**
 * @param $fk_element
 * @param $element
 * @param $fk_product
 * @param int $qty
 * @param int $deep
 * @param int $maxDeep
 * @return array|false
 */
function  sofo_nomenclatureProductDeepCrawl($fk_element, $element, $fk_product,$qty = 1, $deep = 0, $maxDeep = 0){
    global $db,$conf;

    $maxDeepConf = floatval( getDolGlobalString('NOMENCLATURE_MAX_NESTED_LEVEL','50'));
    $maxDeep = !empty($maxDeep)?$maxDeep:$maxDeepConf ;

    if($deep>$maxDeep){ return array(); }

    dol_include_once('/nomenclature/class/nomenclature.class.php');

    if(!class_exists('TNomenclature')){
        return false;
    }

    $nomenclature = new TNomenclature($db);
    $PDOdb = new TPDOdb($db);

    $nomenclature->loadByObjectId($PDOdb,$fk_element, $element, false, $fk_product, $qty); //get lines of nomenclature

    $Tlines= array();

    $i=0;
    if(!empty($nomenclature->TNomenclatureDet)){
        $detailsNomenclature=$nomenclature->getDetails($qty);
        // PARCOURS DE LA NOMENCLATURE
        foreach ($nomenclature->TNomenclatureDet as &$det)
        {
            $i++;

            $Tlines[$i] = array(
                'element' => 'nomenclaturedet',
                'id'      =>  !empty($det->id)?$det->id:$det->rowid,
                'fk_product'=>$det->fk_product,
                'infos'   => array(
                    'label' => '',
                    'desc' => '',
                    'qty' => $qty * $det->qty,
                    //'object' => $det,
                ),
            );

            $childs = sofo_nomenclatureProductDeepCrawl($det->fk_product, 'product', $det->fk_product,$qty * $det->qty, $deep+1, $maxDeep);

            if(!empty($childs))
            {
                $Tlines[$i]['children'] = $childs;
            }

        }

    }

    return $Tlines;
}

/**
 * @param $fk_product
 * @return int
 */
function sofo_getFournMinPrice($fk_product)
{
    global $db;

    $ProductFournisseur = new ProductFournisseur($db);
    $TfournPrices = $ProductFournisseur->list_product_fournisseur_price($fk_product, '', '', 1);


    $minFournPrice = 0;
    $minFournPriceId = 0;
    if(!empty($TfournPrices))
    {
        foreach ($TfournPrices as $fournPrices){

            if(empty($minFournPrice)){
                $minFournPrice = $fournPrices->fourn_unitprice;
                $minFournPriceId = $fournPrices->fourn_id;
            }

            if(!empty($fournPrices->fourn_unitprice) && $fournPrices->fourn_unitprice < $minFournPrice && !empty($minFournPriceId) )
            {
                $minFournPrice = $fournPrices->fourn_unitprice;
                $minFournPriceId = $fournPrices->fourn_id;
            }
        }
    }

    return $minFournPriceId;
}

/**
 * @return array
 */
function supplierorderfromorderAdminPrepareHead()
{
    global $langs, $conf;

    $langs->load("supplierorderfromorder@supplierorderfromorder");

    $h = 0;
    $head = array();

    $head[$h][0] = dol_buildpath("/supplierorderfromorder/admin/supplierorderfromorder_setup.php", 1);
    $head[$h][1] = $langs->trans("Parameters");
    $head[$h][2] = 'settings';
    $h++;

    if (isModEnabled('nomenclature')){
        $head[$h][0] = dol_buildpath("/supplierorderfromorder/admin/dispatch_to_supplier_order_setup.php", 1);
        $head[$h][1] = $langs->trans("Nomenclature");
        $head[$h][2] = 'nomenclature';
        $h++;
    }

	$head[$h][0] = dol_buildpath("/supplierorderfromorder/admin/supplierorderfromorder_about.php", 1);
	$head[$h][1] = $langs->trans("About");
	$head[$h][2] = 'about';
	$h++;

    // Show more tabs from modules
    // Entries must be declared in modules descriptor with line
    //$this->tabs = array(&
    //	'entity:+tabname:Title:@supplierorderfromorder:/supplierorderfromorder/mypage.php?id=__ID__'
    //); // to add new tab
    //$this->tabs = array(
    //	'entity:-tabname:Title:@supplierorderfromorder:/supplierorderfromorder/mypage.php?id=__ID__'
    //); // to remove a tab
    complete_head_from_modules($conf, $langs, new stdClass(), $head, $h, 'supplierorderfromorderadmin');

    return $head;
}

/**
 * Build SQL query for ordercustomer grouped view (one line per product).
 *
 * @param DoliDB $db
 * @param int|string $entityToTest
 * @param array $TCategoriesQuery
 * @param int $fk_commande
 * @param string $search_all
 * @param int|string $type
 * @param string $sref
 * @param string $snom
 * @param string $canvas
 * @param string $salert
 * @return string
 */
function sofoBuildGroupedQuery($db, $entityToTest, $TCategoriesQuery, $fk_commande, $search_all, $type, $sref, $snom, $canvas, $salert)
{
	$sql = 'SELECT prod.rowid, prod.ref, prod.label, cd.description, prod.price, cd.qty as qty, COALESCE(SUM(ed.qty), 0) as qty_shipped, cd.buy_price_ht';
	$sql .= ', prod.price_ttc, prod.price_base_type,prod.fk_product_type';
	$sql .= ', prod.tms as datem, prod.duration, prod.tobuy, prod.seuil_stock_alerte, cd.rang,';

	if (in_array($db->type, array('pgsql'))) {
		$sql .= ' string_agg(DISTINCT cd.rowid::character varying, \'@\') as lineid,';
	} else {
		$sql .= ' GROUP_CONCAT(cd.rowid SEPARATOR "@") as lineid,';
	}

	$sql .= ' ( SELECT SUM(s.reel) FROM ' . $db->prefix() . 'product_stock s';
	$sql .= ' INNER JOIN ' . $db->prefix() . 'entrepot as entre ON entre.rowid=s.fk_entrepot';
	$sql .= ' WHERE s.fk_product=prod.rowid AND entre.entity IN (' . $entityToTest . ')) as stock_physique';

	$sql .= ', prod.desiredstock';
	$sql .= ' FROM ' . $db->prefix() . 'product as prod';

	$sql .= ' LEFT OUTER JOIN (';
	$sql .= ' SELECT fk_product, fk_commande, SUM(qty) as qty, description, MAX(buy_price_ht) as buy_price_ht, MAX(rang) as rang, GROUP_CONCAT(rowid SEPARATOR "@") as rowid';
	$sql .= ' FROM ' . $db->prefix() . 'commandedet';
	$sql .= ' GROUP BY fk_product, fk_commande';
	$sql .= ') as cd ON prod.rowid = cd.fk_product';

	if ((float)DOL_VERSION >= 20.0) {
		$sql .= ' LEFT JOIN ' . $db->prefix() . 'expeditiondet as ed ON (cd.rowid = ed.fk_elementdet)';
	} else {
		$sql .= ' LEFT JOIN ' . $db->prefix() . 'expeditiondet as ed ON (cd.rowid = ed.fk_origin_line)';
	}

	if (!empty($TCategoriesQuery)) {
		$sql .= ' LEFT OUTER JOIN ' . $db->prefix() . 'categorie_product as cp ON (prod.rowid = cp.fk_product)';
	}

	$sql .= ' WHERE prod.fk_product_type IN (0,1) AND prod.entity IN (' . getEntity("product", 1) . ')';

	if ((int)$fk_commande > 0) {
		$sql .= ' AND cd.fk_commande = ' . ((int)$fk_commande);
	}

	if (!empty($TCategoriesQuery)) {
		$sql .= ' AND cp.fk_categorie IN ( ' . implode(',', $TCategoriesQuery) . ' ) ';
	}

	if ($search_all) {
		$sql .= ' AND (prod.ref LIKE "%' . $db->escape($search_all) . '%" ';
		$sql .= 'OR prod.label LIKE "%' . $db->escape($search_all) . '%" ';
		$sql .= 'OR prod.description LIKE "%' . $db->escape($search_all) . '%" ';
		$sql .= 'OR prod.note LIKE "%' . $db->escape($search_all) . '%")';
	}

	if (dol_strlen($type)) {
		if ($type == 1) {
			$sql .= ' AND prod.fk_product_type = 1';
		} else {
			$sql .= ' AND prod.fk_product_type != 1';
		}
	}

	if ($sref) {
		$scrit = explode(' ', $sref);
		foreach ($scrit as $crit) {
			$sql .= ' AND prod.ref LIKE "%' . $db->escape($crit) . '%"';
		}
	}

	if ($snom) {
		$scrit = explode(' ', $snom);
		foreach ($scrit as $crit) {
			$sql .= ' AND prod.label LIKE "%' . $db->escape($crit) . '%"';
		}
	}

	$sql .= ' AND prod.tobuy = 1';

	if (!empty($canvas)) {
		$sql .= ' AND prod.canvas = "' . $db->escape($canvas) . '"';
	}

	if ($salert == 'on') {
		$sql .= " AND prod.seuil_stock_alerte is not NULL ";
	}

	$sql .= ' GROUP BY prod.rowid, prod.ref, prod.label, prod.price';
	$sql .= ', prod.price_ttc, prod.price_base_type,prod.fk_product_type, prod.tms';
	$sql .= ', prod.duration, prod.tobuy, prod.seuil_stock_alerte';

	if ($salert == 'on') {
		$sql .= ' HAVING stock_physique < prod.seuil_stock_alerte ';
	}

	return $sql;
}

/**
 * Build SQL query for ordercustomer ungrouped view (one line per order line).
 *
 * @param DoliDB $db
 * @param int|string $entityToTest
 * @param array $TCategoriesQuery
 * @param int $fk_commande
 * @param string $search_all
 * @param int|string $type
 * @param string $sref
 * @param string $snom
 * @param string $canvas
 * @param string $salert
 * @return string
 */
function sofoBuildUngroupedQuery($db, $entityToTest, $TCategoriesQuery, $fk_commande, $search_all, $type, $sref, $snom, $canvas, $salert)
{
	$sql = 'SELECT prod.rowid, prod.ref, prod.label, cd.description, prod.price, cd.qty as qty, COALESCE(SUM(ed.qty), 0) as qty_shipped, cd.buy_price_ht';
	$sql .= ', prod.price_ttc, prod.price_base_type,prod.fk_product_type';
	$sql .= ', prod.tms as datem, prod.duration, prod.tobuy, prod.seuil_stock_alerte, cd.rang, cd.rowid as lineid,';

	$sql .= ' ( SELECT SUM(s.reel) FROM ' . $db->prefix() . 'product_stock s';
	$sql .= ' INNER JOIN ' . $db->prefix() . 'entrepot as entre ON entre.rowid=s.fk_entrepot';
	$sql .= ' WHERE s.fk_product=prod.rowid AND entre.entity IN (' . $entityToTest . ')) as stock_physique';

	$sql .= ', prod.desiredstock';
	$sql .= ' FROM ' . $db->prefix() . 'commandedet as cd';
	$sql .= ' INNER JOIN ' . $db->prefix() . 'product as prod ON prod.rowid = cd.fk_product';

	if ((float)DOL_VERSION >= 20.0) {
		$sql .= ' LEFT JOIN ' . $db->prefix() . 'expeditiondet as ed ON (cd.rowid = ed.fk_elementdet)';
	} else {
		$sql .= ' LEFT JOIN ' . $db->prefix() . 'expeditiondet as ed ON (cd.rowid = ed.fk_origin_line)';
	}

	if (!empty($TCategoriesQuery)) {
		$sql .= ' LEFT OUTER JOIN ' . $db->prefix() . 'categorie_product as cp ON (prod.rowid = cp.fk_product)';
	}

	$sql .= ' WHERE prod.fk_product_type IN (0,1) AND prod.entity IN (' . getEntity("product", 1) . ')';

	if ((int)$fk_commande > 0) {
		$sql .= ' AND cd.fk_commande = ' . ((int)$fk_commande);
	}

	if (!empty($TCategoriesQuery)) {
		$sql .= ' AND cp.fk_categorie IN ( ' . implode(',', $TCategoriesQuery) . ' ) ';
	}

	if ($search_all) {
		$sql .= ' AND (prod.ref LIKE "%' . $db->escape($search_all) . '%" ';
		$sql .= 'OR prod.label LIKE "%' . $db->escape($search_all) . '%" ';
		$sql .= 'OR prod.description LIKE "%' . $db->escape($search_all) . '%" ';
		$sql .= 'OR prod.note LIKE "%' . $db->escape($search_all) . '%")';
	}

	if (dol_strlen($type)) {
		if ($type == 1) {
			$sql .= ' AND prod.fk_product_type = 1';
		} else {
			$sql .= ' AND prod.fk_product_type != 1';
		}
	}

	if ($sref) {
		$scrit = explode(' ', $sref);
		foreach ($scrit as $crit) {
			$sql .= ' AND prod.ref LIKE "%' . $db->escape($crit) . '%"';
		}
	}

	if ($snom) {
		$scrit = explode(' ', $snom);
		foreach ($scrit as $crit) {
			$sql .= ' AND prod.label LIKE "%' . $db->escape($crit) . '%"';
		}
	}

	$sql .= ' AND prod.tobuy = 1';

	if (!empty($canvas)) {
		$sql .= ' AND prod.canvas = "' . $db->escape($canvas) . '"';
	}

	if ($salert == 'on') {
		$sql .= " AND prod.seuil_stock_alerte is not NULL ";
	}

	$sql .= ' GROUP BY prod.rowid, prod.ref, prod.label, prod.price';
	$sql .= ', cd.description, cd.qty, cd.buy_price_ht, prod.price_ttc, prod.price_base_type,prod.fk_product_type, prod.tms';
	$sql .= ', prod.duration, prod.tobuy, prod.seuil_stock_alerte, cd.rang, cd.rowid';

	if ($salert == 'on') {
		$sql .= ' HAVING stock_physique < prod.seuil_stock_alerte ';
	}

	return $sql;
}
