# ChangeLog

## [Unreleased]



## 2.4 - 26/01/2022
- FIX : add exit to header location function on SUPPLIERORDER_FROM_ORDER_HEADER_SUPPLIER_ORDER 2.4.3 - *01/02/2022*
- FIX : Pgsql query - 2.4.2 *09/02/2022*
- FIX : Don't redirect on order list when it's proposal validation - 2.4.1 - *25/01/2022*
- NEW : conf SOFO_QTY_LINES_COMES_FROM_ORIGIN_ORDER_ONLY, when it's on : columns "ordered" and "to order" are only filled with origin cmd lines quantities - 2.4.0 - *10/11/2021*
- NEW : conf SOFO_GROUP_LINES_BY_PRODUCT, when it's on : each product reference is grouped on one and only line - 2.4.0 - *10/11/2021*

## 2.3
- FIX : change params passed to find_min_price_product_fournisseur ($productid instaed of $line->fk_product) - 2.3.2 - 06/01/2022
- FIX : change SQL query aliases for list: `p` is now `prod` - 2.3.1 - 01/10/2021
- New - add doAction hook 2.3.0 - *30/09/2021*
- New - add redirection button to command fourn 2.2.0- *28/09/2021*
- New - restrict input number on product 
- New - add Qty product column and link to cmd fourn if exit.


- Fix : bad if conditions - 2.1.0 - *16/08/2021*
- Fix : refacto de batard - 2.1.0 - *16/08/2021*
- Fix : treatment after error validation - 2.1.0 - *16/08/2021*
- New : Add sub-nomenclature view (+ conf) - 2.1.0 - *16/08/2021*
- Fix : link nomenclature lines to supplier order line - 2.1.0 - *16/08/2021*

## 2.0

- FIX: Gestion de la remise relative du fournisseur - 2.0.7 - *03/08/2021*
- FIX: v14 compatibility - setDateLivraison -> setDeliveryDate - 2.0.6 - *27/07/2021*
- FIX: Dispatch to supplier soc detection - 2.0.5 - *12/07/2021*
- FIX: DA020591 : Erreur de calcul sur la qté à commander sur écran de réappro lorsque plusieurs fois le même produit dans la même commande + FIX informations infobulle - 2.0.4 - *02/07/2021*
- FIX: v14 compatibility - NOCSRFCHECK + setDateLivraison - 2.0.3 - *29/06/2021*
- FIX: Add conf to take card of aproved supplier orders for virtual stocks - 2.0.2 - *28/04/2021*
- FIX: Compatibility V13 - add token renowal - 2.0.2 - *17.05.2021*
- FIX: warning when clicking “Create Supplier Order” - 2.0.1 - *23/04/2021*
- No changelog up to this point

## 1.6
- No changelog up to this point

## 1.0
- No changelog up to this point

