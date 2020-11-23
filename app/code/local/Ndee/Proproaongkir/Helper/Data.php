<?php
/**
 * Indonesia Shipping Carriers
 * @copyright   Copyright (c) 2016 Ndee Proaongkir
 * @email		-
 * @build_date  July 2016   
 * @license     http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

class Ndee_Proproaongkir_Helper_Data extends Mage_Core_Helper_Abstract
{
	const ALLOWED_HOST = 'magentoblanja.kslin.web,kslin.web,kemanastaging.com,localhost,127.0.0.1'; 
	
	public function validateLicense()
	{
		
		return true;
		$found = false;
		$host = $_SERVER['HTTP_HOST'];
		
		$list_allowed = explode(",",self::ALLOWED_HOST);
		
		//print_r($list_allowed);
		
		
		if(in_array($host,$list_allowed))
		return true;
		else
		return false;
	}
	
	public function cekDomain($url)
	{
		if (preg_match('/^((.+)\.)?([A-Za-z][0-9A-Za-z\-]{1,63})\.([A-Za-z]{3})(\/.*)?$/',$url,$matches)) {
			#print 'Domain is: '.$matches[3].'.'.$matches[4].'<br>'."\n";
			return true;
		 } else {
			#print 'Domain not found in '.$url.'<br>'."\n";
			return false;
		 }
	}
	
	public function setLog($message,$filename='proaongkir.log')
	{
		if(!$this->validateLicense())
		return false;
	
		Mage::log($message,null,$filename);
	}
	
	public function getConfig($config)
	{
		return $this->config($config);
	}
	
	public function config($config)
	{
		return Mage::getStoreConfig('carriers/ongkir/'.$config);
	}
	
	
	public function getApiKey()
	{
		return $this->config('apikey');
	}
	
	public function getApiUrl()
	{
		return $this->config('apiurl');
	}
	
	public function getActiveCarriers()
	{
			return explode(',',strtolower($this->config('kurir')));
	}
	
	public function isDisabledSavedShippingRates()
	{
		return $this->config('disablecached');
	}
	
	public function getDisabledServices()
	{
			return $this->config('disablesvr');
	}
	
	public function getSavedRates($origin,$dest,$weight,$kurir)
	{
		
		if($this->isDisabledSavedShippingRates())
		{
			return $this->getRates($origin,$dest,$weight,$kurir); 
		};
		
		
		$disabled_servis = $this->getDisabledServices();
		
		$disabled_servis = explode(",",$disabled_servis);
		
		$concat_kurir_servis = '';
		
		$array_rates = array();
		$sql = "SELECT distinct dari,ke, harga, kurir, servis,text FROM ".Mage::getConfig()->getTablePrefix()."proaongkir_save_rates 
				where dari='$origin' and ke='$dest' and kurir='$kurir' ";
				
		$sql = $this->fetchSql($sql);
		$count = 0;
		foreach($sql as $datax)
		{
			$count++;
			
			$concat_kurir_servis = $datax['kurir'].'|'.$datax['servis'];
			
			
			if(!in_array($concat_kurir_servis,$disabled_servis)):
			$array_rates[] = array(
			
					'text'=> $datax['text'].' - ',
					'cost'=> $datax['harga'] * $weight
			);
			
			endif;
		};
		
		if($count)
		{
				return $array_rates;
		}else{
			return $this->getRates($origin,$dest,$weight,$kurir);
		};
	}
	
	public function grabAllRates($refreshAll = 0)
	{
		//$this->createAdditionalTable();
		$origin = $this->config('origin');
		
		if($refreshAll):
			$sql = "delete from ".Mage::getConfig()->getTablePrefix()."proaongkir_save_rates";
			
			try{
				$this->sql($sql);
				echo 'clear rates sukses'.'<br>';
			}catch(Exception $xx)
			{
				$this->setLog('Erorr Sql : '.$xx->getMessage());
				echo 'clear rates GAGAL'.'<br>';
			};  
		endif;
		
		$sql = "select distinct subdistrict_id from ".Mage::getConfig()->getTablePrefix()."daftar_alamat where subdistrict_id not in (select distinct ke from proaongkir_save_rates where dari='$origin'  order by rand() ) ";   
		$sql = $this->fetchSql($sql);
		$kurir_list = $this->getActiveCarriers();
		foreach($sql as $dats)
		{
			foreach($kurir_list as $kurir)
			{
				try{
					$this->getRates($origin,$dats['subdistrict_id'],1,$kurir);
					echo 'sukses grab origin : '.$origin.' subdistrict_id id :'.$dats['subdistrict_id'].' kurir:'.$kurir.'<br>';
				}catch(Exception $xx)
				{
					$this->setLog('Erorr Sql : '.$xx->getMessage());
					echo 'GAGAL  grab origin : '.$origin.' subdistrict_id id :'.$dats['subdistrict_id'].' kurir:'.$kurir.'<br>';
					
				};
			};
		}; 
		
	} 
	
	public function saveRate($origin,$dest,$harga,$kurir,$servis,$text)
	{
		if($this->isDisabledSavedShippingRates())
		{
			return true;
		};
		
		$this->createAdditionalTable();
		
		$sql = "insert into ".Mage::getConfig()->getTablePrefix()."proaongkir_save_rates(dari,ke,harga,kurir,servis,text,lup)  
		values('$origin','$dest','$harga','$kurir','$servis','$text',now()) ";
		
		try{
			$this->sql($sql);
		}catch(Exception $xx)
		{
			$this->setLog('Erorr Sql : '.$xx->getMessage());
			return false;
		};
	}
	
	public function getRates($origin,$dest,$weight,$kurir)
	{
		
		$disabled_servis = $this->getDisabledServices();
		
		$disabled_servis = explode(",",$disabled_servis);
		
		$concat_kurir_servis = '';
		
		
		
		$ori_weight = $weight;
		$weight = $weight * 1000;
		
		//origin=2112&originType=subdistrict&destination=2128&destinationType=subdistrict&weight=1700&courier=rpx
		
		//$post_fields = "origin=$origin&destination=$dest&weight=$weight&courier=$kurir";
		$post_fields = "origin=$origin&originType=subdistrict&destination=$dest&destinationType=subdistrict&weight=$weight&courier=$kurir";
		
		
		$post = $this->requestPost('/cost',$post_fields);
		
		$array_rates = array();
		
		if($post['rajaongkir']['status']['code'] != '200')
		{
			return false;
		};
		
		$name_kurir = strtoupper($post['rajaongkir']['results'][0]['code']);
		
		foreach($post['rajaongkir']['results'][0]['costs'] as $listrates)
		{
			$text = $name_kurir.' '.'('.$listrates['service'].') '.$listrates['description'];
			
			
			foreach($listrates['cost'] as $main_rates):
			
			$concat_kurir_servis = $name_kurir.'|'.$listrates['service'];
			
			
			if(!in_array($concat_kurir_servis,$disabled_servis)):
				$array_rates[] = array(
				
						'text'=> $text.' '.$main_rates['note']. ' Est '.$main_rates['etd'].' Hari',
						'cost'=> $main_rates['value'],
						'etd'=> $main_rates['etd'],
				);
			endif;
			
			
			$harga_perkilo = round($main_rates['value']/$ori_weight,0); 
			$this->saveRate($origin,$dest,$harga_perkilo,$name_kurir,$listrates['service'],$text);
			endforeach;
		}
		
		
		return $array_rates;
		
	}
	
	public function requestPost($method,$postfield)
	{
		
		if(!$this->validateLicense())
		return false;
	
	
		$this->setLog('POST FIELDS : '.$postfield);
		$this->setLog('URL : '.$this->getApiUrl().$method);
		
		$curl = curl_init();


		curl_setopt_array($curl, array(
		  CURLOPT_URL => $this->getApiUrl().$method,
		  CURLOPT_RETURNTRANSFER => true,
		  CURLOPT_ENCODING => "",
		  CURLOPT_MAXREDIRS => 10,
		  CURLOPT_TIMEOUT => 30,
		  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
		  CURLOPT_CUSTOMREQUEST => "POST",
		  //CURLOPT_POSTFIELDS => "origin=501&destination=114&weight=1700&courier=jne",
		  CURLOPT_POSTFIELDS => $postfield,
		  CURLOPT_HTTPHEADER => array(
			"key: ".$this->getApiKey()
		  ),
		));
		
		$response = curl_exec($curl);
		/*echo '<pre>';
		print_r(json_decode($response,true));
		
		$err = curl_error($curl);*/
		$json = json_decode($response,true);
		$array = print_r($json,true);
		
		curl_close($curl);
		
		$this->setLog('API LOG REQ : '.$array);
		
		/* array */
		return $json;
		
	}
	
	
	public function request($method)
	{
		if(!$this->validateLicense())
		return false;
	
	
		$curl = curl_init();

		curl_setopt_array($curl, array(
		  //CURLOPT_URL => "http://rajproaongkir.com/api/starter/city",
		  CURLOPT_URL => $this->getApiUrl().$method,
		  CURLOPT_RETURNTRANSFER => true,
		  CURLOPT_ENCODING => "",
		  CURLOPT_MAXREDIRS => 10,
		  CURLOPT_TIMEOUT => 30,
		  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
		  CURLOPT_CUSTOMREQUEST => "GET",
		  CURLOPT_HTTPHEADER => array(
			"key: ".$this->getApiKey()
		  ),
		));
		
		$response = curl_exec($curl);
		/*echo '<pre>';
		print_r(json_decode($response,true));
		
		$err = curl_error($curl);*/
		//$this->setLog('API LOG REQ : '.print_r(json_decode($response,true),true));
		curl_close($curl);
		
		/* array */
		return json_decode($response,true);
		
	}
	
	public function getAllCity()
	{
		return $this->request('/city');
	}
	
	public function getAllProvince()
	{
		return $this->request('/province');
	}
	
	public function getCityProvince($provinceId)
	{
		return $this->request('/city?province='.$provinceId);
	}
	
	public function getKecamatan($cityId)
	{
		return $this->request('/subdistrict?city='.$cityId);
	}
	
	public function isCityExist($city_id)
	{
		$model = Mage::getModel('proaongkir/area')->getCollection()
				->addFieldToFilter('city_id',$city_id)
				->getFirstItem();
		return $model->getId();
	}
	
	public function isDistrictExist($distictId)
	{
		$model = Mage::getModel('proaongkir/area')->getCollection()
				->addFieldToFilter('subdistrict_id',$distictId)
				->getFirstItem();
		return $model->getId();
	}
	
	
	
	public function fetchSql($sql)
	{
		try{
			$resource = Mage::getSingleton('core/resource');
			$writeConnection = $resource->getConnection('core_read');
			return  $writeConnection->fetchAll($sql);

		}catch(Exception $xx)
		{
			$this->setLog('Erorr Sql : '.$xx->getMessage());
			return false;
		}
	}
	
	public function sql($sql)
	{
		try{
			$resource = Mage::getSingleton('core/resource');
			$writeConnection = $resource->getConnection('core_write');
			return  $writeConnection->query($sql);

		}catch(Exception $xx)
		{
			$this->setLog('Erorr Sql : '.$xx->getMessage());
			return false;
		}
	}
	
	public function getPrefixTable()
	{
		return  Mage::getConfig()->getTablePrefix();
	}
	
	public function saveAreaToDb()
	{
		$data = $this->getAllProvince();
		
		$model = Mage::getModel('proaongkir/area');
		
		$sql_delete_region = "
			DELETE FROM ".Mage::getConfig()->getTablePrefix()."directory_country_region WHERE country_id = 'ID' 
		";
		
		
		$this->sql($sql_delete_region);
		
		foreach($data['rajaongkir']['results'] as $prov)
		{
			/*$data_to_save = array();
			$data_to_save = array(
			
					'city_id' => $api_data['city_id']
			);
			*/
			
			$code_prov = $prov['province_id'];
			$name_prov = $prov['province'];
			
			
			$sql_insert_province = "
			INSERT INTO  ".Mage::getConfig()->getTablePrefix()."directory_country_region (country_id,code,default_name)
			VALUES ('ID','$code_prov','$name_prov')
			";
			
			$this->sql($sql_insert_province);
			
			$data_city = $this->getCityProvince($prov['province_id']);
			foreach($data_city['rajaongkir']['results'] as $api_data):
				try{
					/*
					$check_id = $this->isCityExist($api_data['city_id']); 
					//$api_data['city_id'];
					if(!$check_id)
					{
						$model->setData($api_data)
						->save();
					}else
					{
						$model->load($check_id)
							  ->addData($api_data)
							  ->setId($check_id)
							  ->save();
					}*/
					
					$data_district = $this->getKecamatan($api_data['city_id']);
					foreach($data_district['rajaongkir']['results'] as $api_data_district)
					{
						$check_id = $this->isDistrictExist($api_data['subdistrict_id']); 
						
						$this->setLog(print_r($api_data_district,true));
						//$api_data['city_id'];
						if(!$check_id)
						{
							$model->setData($api_data_district)
							->save();
						}else
						{
							$model->load($check_id)
								  ->addData($api_data_district)
								  ->setId($check_id)
								  ->save();
						}
					}
					
				}catch(Exception $xx)
				{
					$this->setLog('insert data area failed '.$xx->getMessage());
				}
			endforeach;
		}
		
		
	}
	
	public function getJsCity()
	{
		$model = Mage::getModel('proaongkir/area');
		$provice = $model->getCollection()
					->distinct(true)
					->addFieldToSelect('province_id')
					->addFieldToSelect('province')
					
					->load()
					;
		
		$string = '';
		
		foreach($provice as $data)
		{
			$string .= "
			var provice_".$data->getProvinceId()." = [ ";
			
			
			$city = $model->getCollection()
					->distinct(true)
					->addFieldToFilter('province_id',$data->getProvinceId())
					->addFieldToSelect('city_id')
					->addFieldToSelect('city_name')
					->addFieldToSelect('city')
					->addFieldToSelect('subdistrict_id')
					->addFieldToSelect('subdistrict_name')
					
					->addFieldToSelect('type')
					
					->setOrder('city','ASC')
					->setOrder('subdistrict_name','ASC')
					
					->load()
					;
			foreach($city as $data_city)
			{
				$string .= "
			{ value: '".$data_city->getType().' '.$data_city->getCity()." Kecamatan '".$data_city->getSubdistrictName().", data: '".$data_city->getCityId()."' },";
			}
			
			$string .="];
			
			";
			
		}
		
		return $string;
	}
	
	public function createAdditionalTable()
	{
		
		return false;
			$sql = '
			CREATE TABLE IF NOT EXISTS `proaongkir_save_rates` (
		  `idx` int(6) unsigned NOT NULL AUTO_INCREMENT,
		  `dari` varchar(255) NOT NULL,
		  `ke` varchar(30) NOT NULL,
		  `harga` decimal(19,4) DEFAULT NULL,
		  `lup` datetime DEFAULT NULL,
		  `kurir` varchar(255) NOT NULL,
		  `servis` varchar(255) NOT NULL,
		  `text` text NOT NULL,
		  PRIMARY KEY (`idx`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8;
		
		
		
			';
			
		
		try{
			$this->sql($sql);

		}catch(Exception $xx)
		{
			$this->setLog('Erorr Sql : '.$xx->getMessage());
			return false;
		}
	}
	
}
	 