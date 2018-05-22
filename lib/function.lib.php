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
