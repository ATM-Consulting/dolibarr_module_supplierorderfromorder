# ChangeLog

## [Unreleased]

## RELEASES 2.11
- FIX : DA027559 - Desc empty - *14/01/2026* - 2.11.1
- NEW : ADD extrafields Order + Thirdparty on SupplierOrderFromOrder Card and reception card - *23/12/2025* - 2.11.0

## RELEASES 2.10
- FIX : Currently, decimals are not taken into account in quantities when creating supplier orders. - *19/12/2025* - 2.10.1
- NEW : Add conf SOFO_GET_REF_SUPPLIER_FROM_ORDER - *09/12/2025* - 2.10.0
  + FIX : group lines by product, alls group product when conf SOFO_GROUP_LINES_BY_PRODUCT was set to no
  
## RELEASES 2.9
- FIX : Warning activation module- **07/07/2025* - 2.9.5
- FIX : COMPAT V22 - **07/07/2025* - 2.9.4
- FIX : COMPAT V21 - **11/12/2024** - 2.9.2
- FIX : Depuis la V20, le champ "fk_origin_line" dans la table llx_expeditiondet a été renommé "fk_elementdet" ce qui causait des erreurs - *22/10/2024* - 2.9.1
- NEW : new coloumn on supplier order creation form : quantities already shipped - *03/09/2024* - 2.9.0

## RELEASES 2.8

- FIX : DA025395 - Retour de ticket  - *30/08/2024* - 2.8.3
- FIX : DA025395 - Gestion de creation commande fournisseur "Créer une commande fournisseur brouillon pour chaque commande client" - *27/08/2024* - 2.8.2
- FIX : DA025395 - Ajout de la conf "Créer une commande fournisseur brouillon pour chaque commande client" sur la page dispatch_to_supplier_order.php - *27/08/2024* - 2.8.1
- NEW : DA025170 - Ajout de la description des produits lors de la création d'une facture fournisseur (soumis a conf) - *15/07/2024* - 2.8.0
- NEW : Compat V20 - *02/07/2024* - 2.8.0
- NEW : Options to import notes on orders - *15/01/2024* - 2.8.0

## RELEASES 2.7
- FIX : DA025148 - Correction chemin vers main.inc et verif que les commandes fournisseurs soient activées - *29/05/2024* - 2.7.3
- FIX : DA024990 - Suppression de isfile inutiles - *29/05/2024* - 2.7.2
- FIX : warnings - *29/02/2024* - 2.7.1  
- NEW : compatv19 - *04/12/2023* - 2.7.0  

## RELEASES 2.6
- FIX : add missing returns on hooks - *29/01/2026* - 2.6.8
- FIX : remove warning error display - *30/11/2023* - 2.6.7
- FIX : remove overwritting initial orderline product in cmdfourn create - *05/10/2023* - 2.6.6  
- FIX : FIX: missing CSRF token in form - *28/09/2023* - 2.6.5
- FIX : Warnings DA02684 - *11/09/2023* - 2.6.4
- FIX : Warnings (accessing global confs without checking for existence) - *03/08/2023* - 2.6.3
- FIX : DA023344 - wrong virtual stock - *26/05/2023* - 2.6.2
- FIX : debugbar - *30/03/2023* - 2.6.1
- NEW : It's now possible to group by product or dissociate on ordercustomer.php page - *20/03/2023* - 2.6.0

## RELEASES 2.5 
- FIX : filter of column "finished" doesn't work - *22/03/2023* - 2.5.9
- FIX : bad return for printCommonFooter hook - *03/03/2023* - 2.5.8
- FIX : remove ordersupplierButton on presen action  - *15/02/2023* - 2.5.7  
- FIX : DA022726 keep unit from component  *07/02/2023* 2.5.6
- FIX : when SOFO_QTY_LINES_COMES_FROM_ORIGIN_ORDER_ONLY is activated column to order was sometimes different from column ordered  *31/01/2023* 2.5.5
- FIX : add step any on dispatch page *11/01/2023* 2.5.4
- FIX : Handle multihook with group by  *02/11/2022* 2.5.3
- FIX : Missing icon  *19/10/2022* 2.5.1
- NEW : Ajout de la class TechATM pour l'affichage de la page "A propos" *11/05/2022* 2.5.0

## RELEASES 2.4 - 26/01/2022
- FIX: Warnings (variables referenced prior to assignment) - *25/07/2023* - 2.4.10
- FIX : Création des commandes fournisseurs dans la devise du fournisseur - *06/01/2023* - 2.4.9
- FIX : PHP 8 - *03/08/2022* - 2.4.8
- FIX : Compatibility V16 - *06/06/2022* - 2.4.7
- FIX DA022202 : Error: Trigger InterfaceSupplierorderfromorder does not extends DolibarrTriggers - 2.4.6 - *20/07/2022*
- FIX DA021939 : SOFO_GROUP_LINES_BY_PRODUCT prend parfois que la 1re ligne s'il y a plusieurs lignes dans la meme commande 2.4.5 - *18/05/2022*
- FIX ticket DA021862 : SOFO_GROUP_LINES_BY_PRODUCT ne doit pas s'appliquer pour les sous-produits + la "Qté dans CF" doit afficher 0 pour les sous-produits (un chiffre sans aucun sens était affiché) - 2.4.4 - *04/05/2022*
- FIX : add exit to header location function on SUPPLIERORDER_FROM_ORDER_HEADER_SUPPLIER_ORDER 2.4.3 - *01/02/2022*
- FIX : Pgsql query - 2.4.2 *09/02/2022*
- FIX : Don't redirect on order list when it's proposal validation - 2.4.1 - *25/01/2022*
- NEW : conf SOFO_QTY_LINES_COMES_FROM_ORIGIN_ORDER_ONLY, when it's on : columns "ordered" and "to order" are only filled with origin cmd lines quantities - 2.4.0 - *10/11/2021*
- NEW : conf SOFO_GROUP_LINES_BY_PRODUCT, when it's on : each product reference is grouped on one and only line - 2.4.0 - *10/11/2021*

## RELEASES 2.3
FIX : changement du calcul de ligne pour prendre en compte le prix unitaire de la nomenclature  - *17/03/2022)* - 2.3.3  
- FIX : change params passed to find_min_price_product_fournisseur ($productid instaed of $line->fk_product) - 2.3.0 - *30/09/2021*
- FIX : change params passed to find_min_price_product_fournisseur ($productid instaed of $line->fk_product) - 2.3.2 - 06/01/2022
- FIX : change SQL query aliases for list: `p` is now `prod` - 2.3.1 - 01/10/2021
- New - add doAction hook 2.3.0 - *30/09/2021*
- New - add redirection button to command fourn 2.2.0- *28/09/2021*
- New - restrict input number on product 
- New - add Qty product column and link to cmd fourn if exit.


## RELEASES 2.1

- FIX : default val for $maxDeep - 2.1.2 - *20/12/2021*
- Fix : bad if conditions - 2.1.0 - *16/08/2021*
- Fix : refacto de batard - 2.1.0 - *16/08/2021*
- Fix : treatment after error validation - 2.1.0 - *16/08/2021*
- New : Add sub-nomenclature view (+ conf) - 2.1.0 - *16/08/2021*
- Fix : link nomenclature lines to supplier order line - 2.1.0 - *16/08/2021*

## RELEASES 2.0

- FIX: Gestion de la remise relative du fournisseur - 2.0.7 - *03/08/2021*
- FIX: v14 compatibility - setDateLivraison -> setDeliveryDate - 2.0.6 - *27/07/2021*
- FIX: Dispatch to supplier soc detection - 2.0.5 - *12/07/2021*
- FIX: DA020591 : Erreur de calcul sur la qté à commander sur écran de réappro lorsque plusieurs fois le même produit dans la même commande + FIX informations infobulle - 2.0.4 - *02/07/2021*
- FIX: v14 compatibility - NOCSRFCHECK + setDateLivraison - 2.0.3 - *29/06/2021*
- FIX: Add conf to take card of aproved supplier orders for virtual stocks - 2.0.2 - *28/04/2021*
- FIX: Compatibility V13 - add token renowal - 2.0.2 - *17.05.2021*
- FIX: warning when clicking “Create Supplier Order” - 2.0.1 - *23/04/2021*
- No changelog up to this point

## RELEASES 1.6
- No changelog up to this point

## RELEASES 1.0
- No changelog up to this point

