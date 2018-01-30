<?php

class TSOFO {
	
	static function getDayFromAvailabilityCode($av_code) {
	
		if($av_code == 'AV_NOW') return 0;
		else if(preg_match('/AV_([0-9]+)([W,D,M]+)/',$av_code,$reg)) {
			
			$nb = (int)$reg[1];
			
			if($reg[2] == 'D') return $nb;
			else if($reg[2] == 'W') return $nb * 7;
			else if($reg[2] == 'M') return $nb * 31;
			
			return 0;
			
		}
		else{
			return 0;
		}
		
	}
	static function getMinAvailability($fk_product, $qty, $only_with_delai = false ,$fk_soc=0) {
	global $db,$form;
		
		$sql = "SELECT fk_availability".((float)DOL_VERSION>5 ? ',delivery_time_days' : '')." 
				FROM ".MAIN_DB_PREFIX."product_fournisseur_price
				WHERE fk_product=". intval($fk_product) ." AND quantity <= ".$qty;
		
		
		if(!empty($fk_soc))
		{
			$sql .=  ' AND fk_soc='. intval($fk_soc)  ;
		}
		
		$res_av = $db->query($sql);

		$min = false;
		
		if(empty($form))$form=new Form($db);
		if(empty($form->cache_availability)){
			$form->load_cache_availability();	
		}
		
		while($obj_availability = $db->fetch_object($res_av)) {
			
			if(!empty($obj_availability->delivery_time_days))$nb_day = $obj_availability->delivery_time_days;
			else {
				$av_code = $form->cache_availability[$obj_availability->fk_availability] ; 
				$nb_day = self::getDayFromAvailabilityCode($av_code['code']);
			}
			if(($min === false || $nb_day<$min )
				&& (!$only_with_delai || $nb_day>0)) $min = $nb_day;
			
		}
		
		return $min;
		
	}
	
}
