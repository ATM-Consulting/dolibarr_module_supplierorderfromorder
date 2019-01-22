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
    $sql.= " FROM ".MAIN_DB_PREFIX."commande_fournisseurdet as cd";
    $sql.= ", ".MAIN_DB_PREFIX."commande_fournisseur as c";
    $sql.= ", ".MAIN_DB_PREFIX."societe as s";
    $sql.= " WHERE c.rowid = cd.fk_commande";
    $sql.= " AND c.fk_soc = s.rowid";
    $sql.= " AND c.entity = ".$conf->entity;
    $sql.= " AND cd.fk_product = ".$fk_product;
    $sql.= " AND (c.date_livraison IS NULL OR c.date_livraison<='".$date."') ";
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
        $sql.= " FROM ".MAIN_DB_PREFIX."commandedet as cd";
        $sql.= ", ".MAIN_DB_PREFIX."commande as c";
        $sql.= ", ".MAIN_DB_PREFIX."societe as s";
        $sql.= " WHERE c.rowid = cd.fk_commande";
        $sql.= " AND c.fk_soc = s.rowid";
        $sql.= " AND c.entity = ".$conf->entity;
        $sql.= " AND cd.fk_product = ".$fk_product;
        $sql.= " AND (c.date_livraison IS NULL OR c.date_livraison<='".$date."') ";
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
    $sql.= " FROM ".MAIN_DB_PREFIX."expeditiondet as ed";
    $sql.= " LEFT JOIN ".MAIN_DB_PREFIX."expedition as e ON (e.rowid=ed.fk_expedition)";
    $sql.= " LEFT JOIN ".MAIN_DB_PREFIX."commandedet as cd ON (ed.fk_origin_line=cd.rowid)";
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
	
	$sql = 'SELECT code FROM '.MAIN_DB_PREFIX.'c_paiement WHERE id = '.$id;
	$resql = $db->query($sql);
	$res = $db->fetch_object($resql);
	
	return $res->code;
}


function getPaymentTermCode($id) {
	
	global $db;
	
	if(empty($id)) return '';
	
	$sql = 'SELECT code FROM '.MAIN_DB_PREFIX.'c_payment_term WHERE rowid = '.$id;
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



function getSupplierOrderAvailable($supplierSocId,$shippingContactId=0,$array_options=array())
{
    global $db, $conf;
    $shippingContactId = intval($shippingContactId);
    $status = intval($status);
    
    $Torder = array();
    
    $sql = 'SELECT cf.rowid ';
    $sql .= ' FROM ' . MAIN_DB_PREFIX . 'commande_fournisseur cf ';
    $sql .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'commande_fournisseur_extrafields cfext ON (cfext.fk_object = cf.rowid) ';
    
    if(!empty($shippingContactId))
    {
        $sql .= ' JOIN  ' . MAIN_DB_PREFIX . 'element_contact ec ON (ec.element_id = fk_target AND ec.fk_socpeople = '.$shippingContactId.') ';
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
            $Torder[] = $obj->rowid;
        }
        
        return $Torder;
    }
    
    return -1;
    
}

function getLinkedSupplierOrderFromOrder($sourceCommandeId,$supplierSocId,$shippingContactId=0,$status=-1,$array_options=array())
{
    global $db, $conf;
    $shippingContactId = intval($shippingContactId);
    $status = intval($status);
    
    $Torder = array();
    
    $sql = 'SELECT ee.fk_target ';
    $sql .= ' FROM ' . MAIN_DB_PREFIX . 'element_element ee';
    $sql .= ' JOIN ' . MAIN_DB_PREFIX . 'commande_fournisseur cf ON (ee.fk_target = cf.rowid) ';
    $sql .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'commande_fournisseur_extrafields cfext ON (cfext.fk_object = cf.rowid) ';
    
    if(!empty($shippingContactId))
    {
        $sql .= ' JOIN  ' . MAIN_DB_PREFIX . 'element_contact ec ON (ec.element_id = fk_target AND ec.fk_socpeople = '.$shippingContactId.') ';
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

function getLinkedSupplierOrderLineFromElementLine($sourceCommandeLineId, $sourcetype = 'commandedet')
{
    global $db;
    
    $sql = 'SELECT fk_target ';
    $sql .= ' FROM ' . MAIN_DB_PREFIX . 'element_element ee';
    $sql .= ' WHERE ee.fk_source = '.intval($sourceCommandeLineId).' ';
    $sql .= ' AND ee.sourcetype = \''.$db->escape($sourcetype).'\' ';
    $sql .= ' AND ee.targettype = \'commande_fournisseurdet\' ';
    
    $resql=$db->query($sql);
    if ($resql && $obj = $db->fetch_object($resql))
    {
        return $obj->fk_target;
    }
    return 0;
    
}

function getLinkedOrderLineFromSupplierOrderLine($sourceCommandeLineId)
{
    global $db;
    
    $sql = 'SELECT fk_source ';
    $sql .= ' FROM ' . MAIN_DB_PREFIX . 'element_element ee';
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

function getUnitLabel($fk_unit, $return = 'code')
{
    global $db, $langs;
    
    $sql = 'SELECT label, code from '.MAIN_DB_PREFIX.'c_units';
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
