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
 * 	\file		admin/supplierorderfromorder_about.php
 * 	\ingroup	supplierorderfromorder
 * 	\brief		This file is an example about page
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

// Translations
$langs->load("supplierorderfromorder@supplierorderfromorder");

// Access control
if (! $user->admin) {
    accessforbidden();
}

/*
 * View
 */
$page_name = "supplierorderfromorderAbout";
llxHeader('', $langs->trans($page_name));

// Subheader
$linkback = '<a href="' . DOL_URL_ROOT . '/admin/modules.php">'
    . $langs->trans("BackToModuleList") . '</a>';
print_fiche_titre($langs->trans($page_name), $linkback);

// Configuration header
$head = supplierorderfromorderAdminPrepareHead();
dol_fiche_head(
    $head,
    'about',
    $langs->trans("Module104130Name"),
    0,
    'supplierorderfromorder@supplierorderfromorder'
);

// About page goes here
require_once __DIR__ . '/../class/techatm.class.php';
$techATM = new \supplierorderfromorder\TechATM($db);

require_once __DIR__ . '/../core/modules/modSupplierorderfromorder.class.php';
$moduleDescriptor = new modSupplierorderfromorder($db);

print $techATM->getAboutPage($moduleDescriptor);

// Page end
print dol_get_fiche_end();

llxFooter();

$db->close();
