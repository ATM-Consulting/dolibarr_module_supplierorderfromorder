<?php
/*
 * Copyright (C) 2013   Cédric Salvador    <csalvador@gpcsolutions.fr>
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
 
include("../../main.inc.php");
//require '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formother.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.commande.class.php';
require_once './lib/replenishment.lib.php';

$langs->load("products");
$langs->load("stocks");
$langs->load("orders");

// Security check
$action=__get('action');
switch ($action) {
	case 'view':
		
		break;
}

$action = $_REQUEST['action'];
$numCommande = $_REQUEST['id'];

print "<table>";
print "<tr>";
print "<td>coucou !</td>";
print "</tr>";
print "</table>";


$sql = 'SELECT p.rowid, p.ref, p.label, p.price';
$sql .= ', p.price_ttc, p.price_base_type,p.fk_product_type';
$sql .= ', p.tms as datem, p.duration, p.tobuy, p.seuil_stock_alerte,';
$sql .= ' SUM(COALESCE(s.reel, 0)) as stock_physique';
$sql .= ', p.desiredstock';
$sql .= ' FROM ' . MAIN_DB_PREFIX . 'product as p';
$sql .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'product_stock as s';
$sql .= ' ON p.rowid = s.fk_product';
$sql.= ' WHERE p.entity IN (' . getEntity("product", 1) . ')';



llxFooter();

$db->close();
?>
