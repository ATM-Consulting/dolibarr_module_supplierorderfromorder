<?php
/* <one line to give the program's name and a brief idea of what it does.>
 * Copyright (C) 2015 ATM Consulting <support@atm-consulting.fr>
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
 * 	\file		admin/supplierorderfromorder.php
 * 	\ingroup	supplierorderfromorder
 * 	\brief		This file is an example module setup page
 * 				Put some comments here
 */
// Dolibarr environment
$res = @include("../../main.inc.php"); // From htdocs directory
if (! $res) {
    $res = @include("../../../main.inc.php"); // From "custom" directory
}

// Libraries
require_once DOL_DOCUMENT_ROOT . "/core/lib/admin.lib.php";
require_once '../lib/function.lib.php';
dol_include_once('abricot/includes/lib/admin.lib.php');

// Translations
$langs->load("admin");
$langs->load('supplierorderfromorder@supplierorderfromorder');

// Access control
if (! $user->admin) {
    accessforbidden();
}

// Parameters
$action = GETPOST('action', 'alpha');

/*
 * Actions
 */
if (preg_match('/set_(.*)/',$action,$reg))
{
    $code=$reg[1];
    if (dolibarr_set_const($db, $code, GETPOST($code, 'none'), 'chaine', 0, '', $conf->entity) > 0)
    {
        header("Location: ".$_SERVER["PHP_SELF"]);
        exit;
    }
    else
    {
        dol_print_error($db);
    }
}

if (preg_match('/del_(.*)/',$action,$reg))
{
    $code=$reg[1];
    if (dolibarr_del_const($db, $code, 0) > 0)
    {
        Header("Location: ".$_SERVER["PHP_SELF"]);
        exit;
    }
    else
    {
        dol_print_error($db);
    }
}

/*
 * View
 */
$page_name = "supplierorderfromorderSetup";
llxHeader('', $langs->trans($page_name));

    // Configuration header
    $head = supplierorderfromorderAdminPrepareHead();
    dol_fiche_head(
        $head,
        'nomenclature',
        $langs->trans("Module104130Name"),
        0,
        "supplierorderfromorder@supplierorderfromorder"
        );



    $linkback='<a href="'.DOL_URL_ROOT.'/admin/modules.php">'.$langs->trans("BackToModuleList").'</a>';
    print_fiche_titre($langs->trans("SupplierOrderFromOrder"),$linkback,'supplierorderfromorder@supplierorderfromorder');


    dol_fiche_end();


    // Setup page goes here
    $form=new Form($db);
    $var=false;
    print '<table class="noborder" width="100%">';


    if(!function_exists('setup_print_title')){
        print '<div class="error" >'.$langs->trans('AbricotNeedUpdate').' : <a href="http://wiki.atm-consulting.fr/index.php/Accueil#Abricot" target="_blank"><i class="fa fa-info"></i> Wiki</a></div>';
        exit;
    }

    setup_print_title("Parameters");

    // USE Nomenclature tab
    setup_print_on_off('SOFO_USE_NOMENCLATURE',false, '', 'SOFO_USE_NOMENCLATURE_HELP');


    setup_print_title("ParametersNeedSOFO_USE_NOMENCLATURE");

    // Fill qty for nomenclature
    setup_print_on_off('SOFO_FILL_QTY_NOMENCLATURE',false);

    // Disable product order if nomenclature
    setup_print_on_off('SOFO_DISABLE_ORDER_POSIBILITY_TO_PRODUCT_WITH_NOMENCLATURE');


    // USE DELIVERY CONTACT
    setup_print_on_off('SOFO_USE_DELIVERY_CONTACT',false, '', 'DeliveryHelp');


    // USE RESTRICTION CONTACT
    setup_print_on_off('SOFO_USE_RESTRICTION_TO_CUSTOMER_ORDER');

    setup_print_on_off('SOFO_ADD_QUANTITY_RATHER_THAN_CREATE_LINES');

    setup_print_on_off('SOFO_VIEW_SUBNOMENCLATURE8LINES');


    // Example with imput
    //setup_print_input_form_part('CONSTNAME', 'ParamLabel');

    // Example with color
   // setup_print_input_form_part('CONSTNAME', 'ParamLabel', 'ParamDesc', array('type'=>'color'),'input','ParamHelp');

    // Example with placeholder
    //setup_print_input_form_part('CONSTNAME','ParamLabel','ParamDesc',array('placeholder'=>'http://'),'input','ParamHelp');

    // Example with textarea
    //setup_print_input_form_part('CONSTNAME','ParamLabel','ParamDesc',array(),'textarea');


    print '</table>';

    llxFooter();

    $db->close();
