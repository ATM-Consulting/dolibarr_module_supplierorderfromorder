<?php


// load conf
include_once __DIR__ . '/config.php';
dol_include_once('subtotal/class/subtotal.class.php');
dol_include_once('fourn/class/fournisseur.commande.class.php');
dol_include_once('fourn/class/fournisseur.product.class.php');
dol_include_once('commande/class/commande.class.php');
dol_include_once('supplierorderfromorder/lib/load.lib.php');

if(empty($user->rights->fournisseur->commande->creer)) accessforbidden();

$langs->load('supplierorderfromorder@supplierorderfromorder');
$langs->loadLangs(array('admin','orders','sendings','companies','bills','supplier_proposal','deliveries','products'));


$action = GETPOST('action');
$fromid = GETPOST('fromid', 'int');
$TDispatch = array();

if(empty($action)){
    $action = 'prepare';
}

$form = new Form($db);



if(empty($fromid) ){
    exit();
}

$origin = New Commande($db);
if($origin->fetch($fromid) <= 0)
{
    exit($langs->trans('NothingToView'));
}

$TChecked  = GETPOST('checked','array');
$TfournUnitPrice = GETPOST('fournUnitPrice','array');
//$TfournUnitPrice = array_map('intval', $TfournUnitPrice);

$TShipping  = GETPOST('shipping','array');
$Tproductfournpriceid = GETPOST('productfournpriceid','array');
$Tproductfournpriceid = array_map('intval', $Tproductfournpriceid);
$Tqty = GETPOST('qty','array');


/*
 * Actions
 */


	// do action from GETPOST ... 
	if($action == 'dispatch')
	{

	    
	    $saveconf_SUPPLIER_ORDER_WITH_NOPRICEDEFINED = !empty($conf->global->SUPPLIER_ORDER_WITH_NOPRICEDEFINED)?$conf->global->SUPPLIER_ORDER_WITH_NOPRICEDEFINED:0 ;
	    $conf->global->SUPPLIER_ORDER_WITH_NOPRICEDEFINED = 1;
	    
	    if(!empty($TChecked) && !empty($origin->lines))
	    {
	        foreach ($origin->lines as $i => $line)
	        {
	            
	            if(in_array($line->id, $TChecked))
	            {
	               
	                
	                
	                
	                $array_options = array();
	                if(!empty($TShipping[$line->id])) $TShipping = array_map('intval', $TShipping);
	                

	                
	                $supplierSocId = GETPOST('fk_soc_fourn_'.$line->id, 'int');
	                
	                // Get fourn from supplier price
	                if(isset($Tproductfournpriceid[$line->id])){
	                    $prod_supplier = new ProductFournisseur($db);
	                    if($prod_supplier->fetch_product_fournisseur_price($Tproductfournpriceid[$line->id]) < 1){
	                        // ERROR
	                        // sauvegarde des infos pour l'affichage du resultat
	                        $TDispatch[$line->id] = array(
	                            'status' => -1,
	                            'msg' => $langs->trans('ErrorPriceDoesNotExist').' : '.$Tproductfournpriceid[$line->id]
	                        );
	                        
	                        continue;
	                    }
	                    
	                    if(!empty($prod_supplier->fourn_id))
	                    {
	                        $supplierSocId = $prod_supplier->fourn_id;
	                    }
	                    
	                }
	                
	                
	                
	                if(empty($supplierSocId) && ( empty($TDispatch[$line->id]['status']) || $TDispatch[$line->id]['status'] < 0) ){
	                    $TDispatch[$line->id] = array(
	                        'status' => -1,
	                        'msg' => $langs->trans('ErrorFournDoesNotExist').' : '.$supplierSocId
	                    );
	                    
	                    continue;
	                }
	                
	                
	                
	                
	                
	                // vérification si la ligne fait déjà l'objet d'une commande fournisseur
	                $searchSupplierOrderLine = getLinkedSupplierOrderLineFromElementLine($line->id);
	                
	                if(empty($searchSupplierOrderLine))
	                {
	                    $createNewOrder = true;
	                    
	                    $shippingContactId = 0;
	                    if(!empty($TShipping[$line->id])){
	                        $shippingContactId = $TShipping[$line->id];
	                    }
	                    
	                    $societe = new Societe($db);
	                    $societe->fetch($supplierSocId);
	                    
	                    
	                    // search and get draft supplier order linked
	                    $searchSupplierOrder = getLinkedSupplierOrderFromOrder($line->fk_commande,$supplierSocId,$shippingContactId,CommandeFournisseur::STATUS_DRAFT,$array_options);
	                    if(empty($searchSupplierOrder))
	                    {
	                        // search draft supplier order with same critera 
	                        $searchSupplierOrder = getSupplierOrderAvailable($supplierSocId,$shippingContactId,$array_options);
	                    }
	                    
	                    
	                    $CommandeFournisseur = new CommandeFournisseur($db);
	                    
	                    if(!empty($searchSupplierOrder))
	                    {
	                        $CommandeFournisseur->fetch($searchSupplierOrder[0]);
	                    }
	                    else
	                    {
	                        $CommandeFournisseur->socid = $supplierSocId;
	                        $CommandeFournisseur->mode_reglement_id = $societe->mode_reglement_supplier_id;
	                        $CommandeFournisseur->cond_reglement_id = $societe->cond_reglement_supplier_id;
	                        $id = $CommandeFournisseur->create($user);
	                        if($id>0)
	                        {
	                            // Add shipping contact
	                            if(!empty($TShipping[$line->id])){
	                                $CommandeFournisseur->add_contact($TShipping[$line->id], 'SHIPPING');
	                            }
	                            
	                            // add order in linked element
	                            $CommandeFournisseur->add_object_linked('commande', $origin->id);
	                            
	                        }
	                    }
	                    
	                    
	                    
	                    // Vérification de la commande
	                    if(empty($CommandeFournisseur->id))
	                    {
    	                    // sauvegarde des infos pour l'affichage du resultat
    	                    $TDispatch[$line->id] = array(
    	                        'status' => -1,
    	                        'msg' => $langs->trans('ErrorOrderDoesNotExist')
    	                    );
	                    
	                       continue;
	                    }
	                    
	                   
	                    // GET PRICE
	                    $fk_prod_fourn_price =0;
	                    $ref_supplier=$line->ref_fourn;
	                    $remise_percent=0.0;
	                    $price = 0;
	                    $qty = isset($Tqty[$line->id])?$Tqty[$line->id]:$line->qty;
	                    $tva_tx = $line->tva_tx;
	                    
	                    if(!empty($TfournUnitPrice[$line->id])){
	                        $price = price2num($TfournUnitPrice[$line->id]);
	                    }
	                    
	                    // Get subprice from product
	                    if(!empty($line->fk_product)){
	                        $ProductFournisseur = new ProductFournisseur($db);
	                        if($ProductFournisseur->find_min_price_product_fournisseur($line->fk_product, $qty, $supplierSocId)>0){
	                            $price = floatval($ProductFournisseur->fourn_price); // floatval is used to remove non used zero
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
	                   
	                    
	                    // ADD LINE
	                    $addRes = $CommandeFournisseur->addline(
	                        $line->desc,
	                        $price,
	                        $qty,
	                        $tva_tx,
	                        $txlocaltax1=0.0, 
	                        $txlocaltax2=0.0, 
	                        $line->fk_product, 
	                        $fk_prod_fourn_price, 
	                        $ref_supplier, 
	                        $remise_percent, 
	                        'HT', 
	                        0, //$pu_ttc=0.0, 
	                        $line->product_type, 
	                        $line->info_bits, 
	                        false, //$notrigger=false, 
	                        null, //$date_start=null, 
	                        null, //$date_end=null, 
	                        0, //$array_options=0, 
	                        $line->fk_unit, 
	                        0,//$pu_ht_devise=0, 
	                        'commandedet', //$origin= // peut être un jour ça sera géré...
	                        $line->id //$origin_id=0 // peut être un jour ça sera géré...
	                        );
	                   
	                    
	                    if($addRes>0)
	                    {
	                        
	                        // add order line in linked element
	                        $commandeFournisseurLigne = new CommandeFournisseurLigne($db);
	                        $commandeFournisseurLigne->fetch($addRes);
	                        $commandeFournisseurLigne->add_object_linked('commandedet', $line->id);
	                        
	                        // sauvegarde des infos pour l'affichage du resultat
	                        $TDispatch[$line->id] = array(
	                            'status' => 1,
	                            'id' => $CommandeFournisseur->id,
	                            'msg' => $CommandeFournisseur->getNomUrl(1)
	                        );
	                    }
	                    else {
	                        // sauvegarde des infos pour l'affichage du resultat
	                        $TDispatch[$line->id] = array(
	                            'status' => -1,
	                            'id' => $CommandeFournisseur->id,
	                            'msg' => $CommandeFournisseur->getNomUrl(1).' '.$langs->trans('ErrorAddSupplierLine').' : '.$commandeFournisseurLigne->error
	                        );
	                    }
	                    
	                    
	                    
	                    
	                    
	                }
	                else
	                {
	                    // récupération de la commande correspondante
	                    $commandeFournisseurLigne = new CommandeFournisseurLigne($db);
	                    $commandeFournisseurLigne->fetch($searchSupplierOrderLine);
	                    
	                    $existingFournOrder = New CommandeFournisseur($db);
	                    $existingFournOrder->fetch($commandeFournisseurLigne->fk_commande);
	                    
	                    // sauvegarde des infos pour l'affichage du resultat
	                    $TDispatch[$line->id] = array(
	                        'status' => 0,
	                        'msg' => $existingFournOrder->getNomUrl(1).' : '.$langs->trans('AllreadyImported')
	                    );
	                    
	                    continue;
	                }
	            }
	        }
	        
	        $conf->global->SUPPLIER_ORDER_WITH_NOPRICEDEFINED = $saveconf_SUPPLIER_ORDER_WITH_NOPRICEDEFINED;
	    }
	    $action = 'showdispatchresult';
	}
	


/*
 * View
 */

llxHeader('',$langs->trans('Dispath'),'','');



$productDefault = new Product($db);

$thisUrlStart = dol_buildpath('supplierorderfromorder/dispatch_to_suppliers_order.php',2). '?fromid='.$fromid;

if( ($action === 'prepare' || $action == 'showdispatchresult')  && !empty($origin->lines)){
    
    $origin->fetch_optionals();
    //$TlistContact = $origin->liste_contact(-1,'external',0,'SHIPPING');
    
    
    $TFournLines = array();
    
    $countErrors = 0;
    foreach ( $TDispatch as $lineId => $infos)
    {
        if($infos['status'] < 0) $countErrors ++;
    }
    
    if($countErrors>0)
    {
        setEventMessage('Il y a des erreurs...', 'errors');
    }

    ?>
    
    
    
    <table width="100%" border="0" class="notopnoleftnoright" style="margin-bottom: 6px;">
        <tbody>
            <tr>
                <td class="nobordernopadding valignmiddle">
                    <img src="<?php echo dol_buildpath('theme/eldy/img/title_generic.png',2); ?>" alt="" class="hideonsmartphone valignmiddle" id="pictotitle">
                    <div class="titre inline-block"><?php  print $langs->trans('PrepareFournDispatch'); ?> <?php  print $origin->getNomUrl(); ?></div>
                </td>
           </tr>
       </tbody>
    </table>
    <?php 
    
    print '<div class="div-table-responsive">';
    print '<form id="crea_commande" name="crea_commande" action="'.$thisUrlStart.'" method="POST">';
    print '<table width="100%" class="noborder noshadow" >';
    
    print '<thead>';
    print '   <tr class="liste_titre">';
    print '       <th class="liste_titre" >' . $langs->trans('Description') . '</th>';
    print '       <th class="liste_titre" >' . $langs->trans('Supplier') . '</th>';
    print '       <th class="liste_titre center" >' . $langs->trans('Commande client') . '</th>';
    print '       <th class="liste_titre center" >' . $langs->trans('Stock_reel') . '</th>';
    print '       <th class="liste_titre center" >' . $langs->trans('Stock_theorique') . '</th>';
    print '       <th class="liste_titre" >' . $form->textwithtooltip($langs->trans('QtyToOrder'), $langs->trans('QtyToOrderHelp'),2,1,img_help(1,'')) . '</th>';
//    print '       <th class="liste_titre" >' . $form->textwithtooltip($langs->trans('Delivery'), $langs->trans('DeliveryHelp'),2,1,img_help(1,'')) . '<br/><small style="cursor:pointer;" id="emptydelivery" ><i class="fa fa-truck" ></i>Vider</small></th>';
    //print '       <th class="liste_titre" >' . $form->textwithtooltip($langs->trans('Price'), $langs->trans('dispatch_Price_Help'),2,1,img_help(1,'')) . '</th>';
    print '       <th class="liste_titre" style="text-align:center;" ><input id="checkToggle" type="checkbox" name="togglecheckall" value="0"   ></th>';
    print '   </tr>';
    print '</thead>';
    
    if(!empty($origin->lines)){
        print '<tbody>';
        
        
        $last_title_line_i = -1; // init last title i
        $totalNbCols = 0;
        
        foreach($origin->lines as $i => $line){ 

            // do not import services
            if($line->product_type === 1)
            {
                continue;
            }
            
            $errors = 0;
            $disable = 0;
            
            $line->fournUnitPrice = ''; // leave empty to use database fourn price
            $line->fournPrice = ''; // leave empty to use database fourn price * qty
            $line->fk_soc_fourn = 0; // soc id for supplier order
            $line->fk_soc_dest = 0;  // soc id for supplier order delivery contact
            
            // Load Product
            if(!empty($line->fk_product))
            {
                $product = new Product($db);
                $product->fetch($line->fk_product);
                $line->product = $product;
                $line->label = $product->label;
                $product->load_stock('');
            }
            
            // TODO : Detect subtotal lines without subtotal
            $line->isModSubtotalLine = 0;
            if(class_exists(TSubtotal) && TSubtotal::isModSubtotalLine($line)){
                $line->isModSubtotalLine = 1;
                
                // Use to know line's title
                if(TSubtotal::isTitle($line)){
                    $last_title_line_i = $i; // set last title $i
                }elseif( TSubtotal::isSubtotal($line)){
                    $last_title_line_i = -1; // reset last title $i
                }
                
            }
            
            
            // Add background color to line
            if(!empty($line->isModSubtotalLine))
            {
                $lineStyleColor = '#eeffee';
            }
            elseif(!empty($TDispatch[$line->id]))
            {
                if($TDispatch[$line->id]['status'] < 0){
                    $lineStyleColor ='#ecb1b1';
                }
                else
                {
                    $lineStyleColor ='#b8ecb1';
                }
            }
            
            $lineStyle = '';
            if(!empty($lineStyleColor))
            {
                $lineStyle .= 'background:'.$lineStyleColor.';';
            }
            
            
            
            // START NEW LINE
            print '<tr class="oddeven" data-lineid="'.$line->id.'" style="'.$lineStyle.'" >';
            
            
            // COL DESC
            print '<td class="col-desc" >';
            
            if(!empty($line->product)){
                print '<strong>'. $line->product->getNomUrl(1).'</strong> ';
            }
            
            if($line->isModSubtotalLine){
                print '<strong>'. $line->label.'</strong> ';
            }
            else {
                print empty($line->label)?$line->desc:$line->label;
            }
            
            
            if(!empty($TDispatch[$line->id]) &&  $TDispatch[$line->id]['status'] < 0)
            {
                print '<p>'.$TDispatch[$line->id]['msg'].'</p>';
            }
            
            print '</td>';
            
            // Check if this line is allready dispatched
            $searchSupplierOrderLine = getLinkedSupplierOrderLineFromElementLine($line->id);
            
            
            
            // 
            if(!$line->isModSubtotalLine  && empty($searchSupplierOrderLine))
            {
                
                print '<td >';
                
                $line->fk_soc_dest = $origin->socid;
                
                // récupération du contact de livraison
               /* if(!empty($TlistContact))
                {
                    $select_shipping_dest_filter = $TlistContact[0]['id'];
                    $line->fk_soc_dest = $TlistContact[0]['socid'];
                }*/
                
                
                
                /*
                 * SELECTION FOURNISSEUR
                 */
                if(!empty($line->fk_product))
                {
                
                    $ProductFournisseur = new ProductFournisseur($db);
                    $TfournPrices = $ProductFournisseur->list_product_fournisseur_price($line->fk_product, '', '', 1);
                   
                    
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
                    
                    if(!empty($Tproductfournpriceid[$line->id])){
                        $minFournPriceId = $Tproductfournpriceid[$line->id];
                    }
                    
                    print $form->select_product_fourn_price($line->fk_product, 'productfournpriceid['.$line->id.']', $minFournPriceId);
                    
                }
                else
                {
                    // In case of a free line
                    
                    print $form->select_company(GETPOST('fk_soc_fourn_'.$line->id,'int'),'fk_soc_fourn_'.$line->id, '',1,'supplier');
                    print ' &nbsp;&nbsp;&nbsp; <input class="unitPriceField" type="number" name="fournUnitPrice['.$line->id.']" value="'.price2num($line->pa_ht).'" min="0" step="any" placeholder="Prix unitaire" >&euro; ';
                    //print $form->selectUnits($line->fk_unit, 'units['.$line->id.']', 1);
                    
                    $productDefault->fk_unit = $line->fk_unit;
                    print $productDefault->getLabelOfUnit();
                }
                
                
                print '</td>';
                
                
                // QTY
                print '<td class="center" ><strong title="Cliquer pour remplacer la quantité à commander" class="handpointer addvalue2target classfortooltip" data-value="'.$line->qty.'" data-target="#qty-'.$line->id.'"  >'.$line->qty.'</strong></td>';
                
                // STOCK REEL
                print '<td  class="center col-realstock">';
                if(!empty($line->fk_product) && $line->product_type == Product::TYPE_PRODUCT)
                {
                    print $product->stock_reel;
                }
                print '</td>';
                
                // STOCK THEORIQUE
                print '<td  class="center col-theoreticalstock">';
                if(!empty($line->fk_product) && $line->product_type == Product::TYPE_PRODUCT)
                {
                    print $product->stock_theorique;
                }
                print '</td>';
                
                if(!empty($line->fk_product))
                {
                    $stocktheoBeforeOrder = $product->stock_theorique + $line->qty;
                }
                else {
                    $stocktheoBeforeOrder = 0;
                }
                
                
                $qty2Order = $line->qty;
                
                if($line->product_type == Product::TYPE_PRODUCT)
                {
                    
                    if( $stocktheoBeforeOrder - $line->qty >= 0){
                        $qty2Order = 0;
                    }
                    elseif($stocktheoBeforeOrder > 0)
                    {
                        $qty2Order = abs($stocktheoBeforeOrder - $line->qty) ;
                    }
                    else
                    {
                        $qty2Order =  $line->qty ;
                    }
                    
                    if($qty2Order>$line->qty){
                        $qty2Order = $line->qty;
                    }
                }
                
                
                // QTY
                if(!empty($Tqty[$line->id])){
                    $qty2Order = $Tqty[$line->id];
                }
                print '<td ><input id="qty-'.$line->id.'" class="qtyform" data-lineid="'.$line->id.'" type="number" name="qty['.$line->id.']" value="'.$qty2Order.'" min="0"  ></td>';
                
                
   
                
                
                
                
                
                
                /*
                 * SELECTION CONTACT DE LIVRAISON
                 */
                
                
                /*print '<td>';
                
                
                if(isset($TShipping[$line->id])){
                    $select_shipping_dest_filter = $TShipping[$line->id];
                }
                
                $selecContactRes = $form->select_contacts($line->fk_soc_dest,$select_shipping_dest_filter,'shipping['.$line->id.']',1);
                if(empty($selecContactRes))
                {
                    $socDest = new Societe($db);
                    $socDest->fetch($line->fk_soc_dest);
                    
                    print '<br/>'.$socDest->getNomUrl(1); //.' : <span class="error" >'.$langs->trans('ProductionFactoryShippingNotDefined').'</span>';
                }
                print '</td>';*/
                
                
                
                
                
                // CHECKBOX
                print '<td  style="text-align:center;"  >';
                if(!$disable)
                {
                    $check = true;
                    if(!empty($TChecked[$line->id])){
                        $check = true;
                    }
                    elseif(empty($qty2Order)||empty($line->fk_product)){
                        $check = false;
                    }
                    
                    print '<input id="linecheckbox'.$line->id.'" class="checkboxToggle" type="checkbox" '.($check?'checked':'').' name="checked['.$line->id.']" value="'.$line->id.'">';
                }
                else
                {
                    print $langs->trans('CheckErrorsBeforeInport');
                }
                
                print '</td>';
            
            }
            else {
                print '<td colspan="7" >';
                if(!empty($searchSupplierOrderLine))
                {
                    // récupération de la commande correspondante
                    $commandeFournisseurLigne = new CommandeFournisseurLigne($db);
                    $commandeFournisseurLigne->fetch($searchSupplierOrderLine);
                    
                    $existingFournOrder = New CommandeFournisseur($db);
                    $existingFournOrder->fetch($commandeFournisseurLigne->fk_commande);
                    
                    print $existingFournOrder->getNomUrl(1);
                    
                    $existingFournOrder->fetch_thirdparty();
                    print ' '.$existingFournOrder->thirdparty->getNomUrl(1,'supplier');
                    
                    if(GETPOST('forcedeletelinked','int')){
                        $line->deleteObjectLinked();
                    }
                    print ' : '.$langs->trans('AllreadyImported');
                }
                elseif(!$line->isModSubtotalLine && $line->product_type === 1)
                {
                    print $langs->trans('servicesAreNotDispatch');
                }
                elseif(!$line->isModSubtotalLine && empty($line->fk_product))
                {
                    print 'Produit à commander manuellement';
                }
                print '</td>';

            }
            
            print '</tr>';
        }
        
        print '</tbody>';
    } 
    
    print '</table>';
    
    print '<div style="clear:both; text-align: left; display:none;" ><input id="bypassjstests" type="checkbox" name="bypassjstests" value="1"> <label for="bypassjstests" >Forcer la ventilation.</label></div>';
    
    print '<div style="text-align: right;" ><button class="butAction" type="submit" name="action" value="dispatch" >'.$langs->trans('Dispatch').' <i class="fa fa-arrow-right"></i></button></div>';
    
    print '</form>';
    print '</div>';
    
    // Inclusion du fichier script de pré-validation des formulaires
    print '<script type="text/javascript" src="'.dol_buildpath('supplierorderfromorder/js/dispatch_to_supplier_order.js.php',1).'"></script>';
    
}
elseif($action == 'showdispatchresult') // Finaly not used... TODO: remove this part if really not used
{
    ?>
    <table width="100%" border="0" class="notopnoleftnoright" style="margin-bottom: 6px;">
        <tbody>
            <tr>
                <td class="nobordernopadding valignmiddle">
                    <img src="<?php echo dol_buildpath('theme/eldy/img/title_generic.png',2); ?>" alt="" class="hideonsmartphone valignmiddle" id="pictotitle">
                    <div class="titre inline-block"><?php  print $langs->trans('FournDispatchResult'); ?>  <?php  print $origin->getNomUrl(); ?></div>
                </td>
           </tr>
       </tbody>
    </table>
    <?php 
    
    print '<div class="div-table-responsive">';
    print '<form name="crea_commande" action="'.$thisUrlStart.'" method="POST">';
    print '<table width="100%" class="noborder noshadow" >';
    
    print '<thead>';
    print '   <tr class="liste_titre">';
    print '       <th class="liste_titre" >' . $langs->trans('Ligne') . '</th>';
    print '       <th class="liste_titre" >' . $langs->trans('Commande') . '</th>';
    print '   </tr>';
    print '</thead>';
    
    if(!empty($origin->lines)){
        print '<tbody>';
        
        foreach($origin->lines as $i => $line){
            
            $line->isModSubtotalLine = 0;
            if(TSubtotal::isModSubtotalLine($line)){
                $line->isModSubtotalLine = 1;
            }
            
            if(!empty($line->fk_product))
            {
                $product = new Product($db);
                $product->fetch($line->fk_product);
                $line->label = $product->getNomUrl().' '.$product->label;
            }
            
            $lineStyle =(!empty($line->isModSubtotalLine)?' style="background:#eeffee;" ':'');
            
            if(!empty($TDispatch[$line->id]))
            {
                if($TDispatch[$line->id]['status'] < 0){
                    $lineStyle =' style="background:#ecb1b1;" ';
                }
                else 
                {
                    $lineStyle =' style="background:#b8ecb1;" ';
                }
            }
            
            
            print '<tr class="oddeven" data-lineid="'.$line->id.'" '.$lineStyle.' >';
            
            print '<td >';
            
            
            if($line->isModSubtotalLine){
                print '<strong>'. $line->label.'</strong> ';
            }
            else {
                print empty($line->label)?$line->desc:$line->label;
            }
            
            print '</td>';
            

            print '<td >';
            if(!empty($TDispatch[$line->id]))
            {
                print $TDispatch[$line->id]['msg'];
            }
            print '</td>';
            
            
            
            print '</tr>';
        }
        
        print '</tbody>';
    }
    
    print '</table>';
}





llxFooter('');