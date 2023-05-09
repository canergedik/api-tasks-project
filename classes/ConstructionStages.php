<?php

/***
 * 
 * @author CANER GEDİK <canergediik28@gmail.com>
 * 
 */

class ConstructionStages
{
	private $db;
	private $data = [];
	private static $statusField = array('NEW','PLANNED','DELETED');
    private static $dataField = array('id','name','startDate','endDate','duration','durationUnit','color','externalId','status');
	public function __construct()
	{
		$this->db = Api::getDb();
	}

	public function getAll()
	{
		$stmt = $this->db->prepare("
			SELECT
				ID as id,
				name, 
				strftime('%Y-%m-%dT%H:%M:%SZ', start_date) as startDate,
				strftime('%Y-%m-%dT%H:%M:%SZ', end_date) as endDate,
				duration,
				durationUnit,
				color,
				externalId,
				status
			FROM construction_stages
		");
		$stmt->execute();
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
	}
	/**
	 * @param string $id  it's construction stages data id to get
	 * @return array 
	 *
	 */
	public function getSingle($id)
	{
		$stmt = $this->db->prepare("
			SELECT
				ID as id,
				name, 
				strftime('%Y-%m-%dT%H:%M:%SZ', start_date) as startDate,
				strftime('%Y-%m-%dT%H:%M:%SZ', end_date) as endDate,
				duration,
				durationUnit,
				color,
				externalId,
				status
			FROM construction_stages
			WHERE ID = :id
		");
		
		$stmt->execute(['id' => $id]);
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	/** 
	 * @param object $data it's Construction stages data, data type is object
	 * @return  array
	 * 
	*/
		public function post(ConstructionStagesCreate $data)
	{   
	    $data = $this->checkVariable($data);
	    if(!isset($this->data['error'])){
			$stmt = $this->db->prepare("
			INSERT INTO construction_stages
			    (name, start_date, end_date, duration, durationUnit, color, externalId, status)
			    VALUES (:name, :start_date, :end_date, :duration, :durationUnit, :color, :externalId, :status)
			");
		$stmt->execute([
			'name' => $data['name'],
			'start_date' => $data['startDate'],
			'end_date' => $data['endDate'],
			'duration' => $data['duration'],
			'durationUnit' =>  $data['durationUnit'],
			'color' => $data['color'],
			'externalId' =>  $data['externalId'],
			'status' => $data['status']
		]);
		$this->data = $this->getSingle($this->db->lastInsertId());
		}
		return $this->data;
	}
	
	/**
	 * 
	 * @param object $data it's Construction stages data, data type is object includes status and id paramters in data
	 * @return  array
	 */

    public function delete($data){
		$data = is_object($data) ? (array) $data : $data;
		$this->statusCheck($data['method'],$data['status']);
		if(!isset($this->data['error'])){
		if(count($this->getSingle($data['id']))){
			if($this->getSingle($data['id'])[0]['status'] == 'DELETED'){
				$this->data['success'] = false;
				$this->data['message'] = 'status already DELETED';
			}
			else{
				$stmt = $this->db->prepare("
				UPDATE  construction_stages SET  status=\"DELETED\" WHERE ID = :id");
				$stmt->execute(['id' => $data['id']]);
				$this->data = $this->getSingle($data['id']);
			}
		}
		else{
			$this->data['success'] = false;
			$this->data['error'] = 'data is not found';
		}
	}
		return $this->data;
}    
/**
 * 
 * @param object $data it's Construction stages data, data type is object
 * @return array
 */
   public function update($data){
	   
	   $data = $this->checkVariable($data);
	   $id = $data['id'];
	   unset($data['id']);
	   if(!isset($this->data['error']) && count($data) > 0){
			$query  = " UPDATE  construction_stages SET ";
			foreach($data as $key => $value){
					$query.= $key .= '="'.$value.'"' . ($key != array_key_last($data) ? ', ' : '') ;
			}
			$query .= '  WHERE ID = :id';
			$query = str_replace(array('startDate','endDate'),array('start_date','end_date'),$query);
			$stmt = $this->db->prepare($query);
			$stmt->execute(['id'=>$id]);
			$this->data =  $this->getSingle($id);
	   }
	   return $this->data;
   }
    /**
	 * @param mixed $data it's Construction stages data, data type is array or object
	 * @return array
	 * 
	 */
    public function checkVariable($data){
	    $data = is_object($data) ? (array) $data : $data;
	    if($data['method'] == 'post' && !$data['name']) $this->data['error']['name']  = 'name is a required field';
			
        if( isset($data['name']) && strlen($data['name']) > 255 ){
            $this->data['error']['name'] = 'maximum number of characters limited to 255';
        }
		if($data['method'] == "post" &&  !$data['startDate'] ) $this->data['error']['startDate'] = 'startDate is a required field';
		
        if(isset($data['startDate'])){
			$this->dateCheckValidate($data['startDate']) == false ? $this->data['error']['startDate'] = 'invalid date format' : '';
        }
		if(isset($data['endDate'])){
			$this->dateCheckValidate($data['endDate']) == false ? $this->data['error']['endDate'] = 'invalid date format' : '';
        }
		if(isset($data['endDate']) && isset($data['startDate'])){
		   if($this->dateCheckValidate($data['endDate']) == true  && $this->dateCheckValidate($data['startDate'])){
                if(strtotime($data['endDate']) <= strtotime($data['startDate'])){
                    $this->data['error']['endDate'] = "End date must be greater than start date";
					$data['duration'] = null;
				} 
				else{
				    isset($data['durationUnit']) ? (in_array($data['durationUnit'],array('HOURS','WEEKS','DAYS')) ? $data['durationUnit'] : $this->data['error']['durationUnit'] = 'durationUnit must be HOURS WEEKS OR DAYS'  )  : $data['durationUnit'] = 'DAYS';
					if(!isset($this->data['error'])) $data['duration'] = $this->durationTime($data['startDate'],$data['endDate'],$data['durationUnit']);
				}
		   }
        }
		else{
			if($data['method'] == "post") $data['duration'] = null;
		}
		if($data['method'] == "post" && !$data['status']) $data['status'] = "NEW";
        
		if(isset($data['status']) && in_array($data['status'],self::$statusField)){
			  $this->statusCheck($data['method'],$data['status']) ;
        }
		else if(isset($data['status']) && !in_array($data['status'],self::$statusField)) $this->data['error']['status'] = 'status field must be NEW or PLANNED';
		
		if(isset($data['externalId']) && strlen($data['externalId']) > 255){
            $this->data['error']['externalId'] = 'maximum number of characters limited to 255';
        }
        if(isset($data['color'])){
		  $hexcodeRegex ="/(#(?:[0-9a-f]{2}){2,4}|#[0-9a-f]{3}|(?:rgba?|hsla?)\((?:\d+%?(?:deg|rad|grad|turn)?(?:,|\s)+){2,3}[\s\/]*[\d\.]+%?\))$/i";
          if(!preg_match($hexcodeRegex,$data['color']) || !strlen($data['color'])==6) {
			$this->data['error']['color'] = 'invalid hexcode';
          }
        }
		$data = array_filter($data,function($key){  
			return in_array($key,self::$dataField);
		},ARRAY_FILTER_USE_KEY);

        return $data;
     }

	 /**
	  * @param string $date Can be start date or end date. Date is format ISO 8601
	  * @return  bool
 	  * 
	  */

	 public function dateCheckValidate($date){
		{
			if (preg_match('/^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2}):(\d{2})Z$/', $date, $parts) == true) {
				$time = gmmktime($parts[4], $parts[5], $parts[6], $parts[2], $parts[3], $parts[1]);
				$input_time = strtotime($date);
				return $input_time == $time;
			} else {
				return false;
			}
		}
	}

	 /**
	  * @param string $method it includes http request methods.It can be POST,GET, PATCH, DELETE etc.
	  * @param string $status it includes status field methods. It can be NEW DELETE or PLANNED
	  * @return string
 	  * 
	  */
	public function statusCheck($method="post",$status="NEW"){
		if(in_array($method,['post','patch']) && $status == "DELETED" ){
			$this->data['error']['status'] = 'You must use NEW or PLANNED';
	   	}
		else if($method == "delete"  && $status != "DELETED"){
			  $this->data['error']['status'] = 'you must be use status is DELETED';
		}
		return $status;
	}

	/**
	 * 
	 * @param string $startDate  Start date is format ISO 8601
	 * @param string $endDate    End date is format ISO 8601
	 * @param string $durationUnit it can be WEEKS DAYS OR HOURS values. default value DAYS
	 */

	public function durationTime($startDate,$endDate,$durationUnit="DAYS"){  
		$startTime = strtotime($startDate); 
		$endTime = strtotime($endDate);
		$duration = abs($endTime-$startTime);
		if($durationUnit == "HOURS"){
		  $hours =  60 * 60;
		  $time = $duration/$hours;
		}
		else if($durationUnit == "DAYS"){
		  $days =  60 * 60 * 24;
		  $time = $duration/$days;
		}
		else if($durationUnit == "WEEKS"){
		$weeks =  60 * 60 * 24 * 7;
		  $time = $duration/$weeks;
		}
		$time =  floor($time);
		return  $time;
	 }

}
