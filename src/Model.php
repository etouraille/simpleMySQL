<?php
namespace simpleMySQL;

class Model
{
    protected static $db;
    protected static $instance;
    protected $table;
    protected static $host;
    protected static $login;
    protected static $pass;
    protected static $base;

    protected static $isPDO;

    protected $actions = array();

    private function __construct()
    {

        if(self::$isPDO) {
            echo 'construct with pdo';
            $this->constructWithPDO();
        } else {
            $this->constructClassical();
        }
    }


    private function constructWithPDO() {

        $dsn = sprintf(
                'mysql:host=%s;dbname=%s', 
                self::$host, 
                self::$base
            );
        
        try {

           self::$db = new \PDO( 
                $dsn,
                self::$login, 
                self::$pass 
            );

           self::$db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        
        } catch( Exception $e ) {
          
          die('Erreur : '.$e->getMessage());
        
        }

    }

    private function constructClassical() {
        self::$db = mysql_connect(self::$host,self::$login,self::$pass);
        mysql_select_db(self::$base,self::$db);
        mysql_query('SET NAMES UTF8');

    }  

    
    private function killPDO () {
        self::$db = null;
    }

    private function killClassical() {
       mysql_close( self::$db ); 
    }

    public function kill() {
       if(self::$isPDO) {
            $this->killPDO();
       } else {
            $this->killClassical();
       }
    }
    

    public function log(){
	$ret = '';
	foreach($this->actions as $log) {
		$ret .= $log."\n";
	}
	return $ret;
    }

    public static function setParams($host,$login,$pass,$base, $isPDO = true )
    {
        self::$host = $host;
        self::$login = $login;
        self::$pass = $pass;
        self::$base = $base;
        self::$isPDO = $isPDO;
    }

    private function getConnexionPDO() {
        if( is_null( self::$db ) ) self::$instance = new Model();            // Don't catch exception here, so that re-connect fail will throw exception
        try {
            self::$db->query('SELECT 1');
        } catch (\PDOException $e) {
        
        }
        return self::$db;
    }


    private static function getConnexionClassical() {
	   if( false == @mysql_ping( self::$db ) ) {
	       self::$instance = new Model();
       }
        return self::$db;
    }

    public function getConnexion() {
        if( self::$isPDO ) {
            return 
                $this->getConnexionPDO();
        } else {
            return 
                $this->getConnexionClassical();
        }
    }

    public function __toString()
    {
        return $this->table;
    }

    public function init( $table )
    {
        $this->table = $table;
        
        if( !isset( self::$instance ) ) {
            self::$instance = new Model();
        }
        return 
            self::$instance;

    }

    private function addClassical( array $tab )
    {
        $fields = '';
        $sep = '';
        $values = '';
        foreach($tab as $field => $value)
        {
            $fields .= $sep. ' `'.$field.'` ';
            $values .= $sep.' \''.$this->e($value).'\' ';
            $sep = ',';

        }
        $request = 'INSERT INTO `'.$this->table.'` ('.$fields.') VALUES ('.$values.');';
        $this->actions[] = $request;
	    $result = mysql_query($request,self::getConnexion());
        if(!$result)
        {
            throw new \Exception($request.mysql_error(self::getConnexion()));
        }
        return mysql_insert_id(self::getConnexion());
    }

    private function addPDO( array $tab ) {

        $fields = '';
        $sep ='';
        $values = '';
        foreach( $tab as $field => $value ) {
            
            $fields .= $sep. ' .$field. ';
            $values .= $sep.' :.$field. ';
            $sep = ',';

        }
        
        $request = "INSERT INTO `".$this->table."` (".$fields.") VALUES (".$values.");";
        
        $con = self::getConnexion();

        $stmt = $con
            ->prepare( $request )
        ;
        foreach( $tab as $field => $value ) {
            $stmt
                ->bindParam(':'.$field , $value )
            ;
        }
        $stmt
            ->execute()
        ;

        return 
            $con::lastInsertId()
        ;
    }

    public function add( array $tab )
    {
        if (self::$isPDO ) {
            return $this->addPDO( $tab );
        } else {
            return $this->addClassical( $tab );
        }
    }

    public function e( $value ) {
        if( self:: $isPDO ) return $value;
        return mysql_real_escape_string(trim($value),self::getConnexion());
    }

    private function getRowClassical( $cond ) {
        $where = '';
        $sep = '';
        foreach($cond as $key => $value)
        {
            $where .= $sep.' `'.$key.'` = \''.$this->e($value).'\' ';
            $sep = 'AND';
        }

        $query = 'SELECT * FROM `'.$this->table.'` WHERE '.$where;
        
        $this->actions[] = $query;
        
        $result = mysql_query($query,self::getConnexion());
        
        if(!$result)
        {
            throw new \Exception($query.mysql_error(self::getConnexion()));
        }
        $row = mysql_fetch_assoc($result);
        return $row;
    }

    private function getRowPDO( $cond ) {
        $where = '';
        $sep = '';
        foreach($cond as $key => $value)
        {
            $where .= $sep.' '.$key.' = :'.$key.' ';
            $sep = 'AND';
        }

        $query = 'SELECT * FROM `'.$this->table.'` WHERE '.$where;
        $this->actions[] = $query;

        $con = self::getConnexion();

        $stmt = $con
            ->prepare( $query )
        ;
        foreach( $cond as $field => $value ) {
            $stmt
                ->bindParam(':'.$field , $value )
            ;
        }
        $stmt
            ->execute()
        ;
        $row = $stmt->fetch();
        return $row;
    }

    public function getRow( $cond ) {
        if( self::$isPDO ) {
            return $this->getRowPDO( $cond );
        } else {
            return $this->getRowClassical( $cond );
        }
    }

    private function getRowsClassical( $cond, $orderBy = array() ) {
        
        $where = 'WHERE';
        $orderBy = $this->getOrderCondition($orderBy);
        if(count($cond)== 0) $where = '';
        $query = sprintf("SELECT * FROM `{$this->table}` %s %s %s",$where,$this->getConditionsQuery($cond),$orderBy);
        $this->actions[] = $query;
        $result = mysql_query($query,self::getConnexion());
        if(!$result)
        {
            throw new \Exception($query.' '.mysql_error(self::getConnexion()));
        }
        $return =array();
        while($row = mysql_fetch_assoc($result))
        {
            $return[]= $row;
        }
        return $return;
    }

    private function getRowsPDO( $cond, $orderBy = array() ) {
        
        $where = 'WHERE';
        $orderBy = $this->getOrderCondition( $orderBy );
        if(count($cond)== 0) $where = '';
        $query = sprintf("SELECT * FROM `{$this->table}` %s %s %s", $where, $this->getConditionsQuery( $cond ), $orderBy);
        $this->actions[] = $query;

        $con = self::getConnexion();

        $stmt = $con
            ->prepare( $query )
        ;
        foreach( $cond as $field => $value ) {
            $stmt
                ->bindParam(':'.$field , $value )
            ;
        }
        $stmt
            ->execute()
        ;
        $rows = $stmt->fetchAll();
        return $rows;
    }

    public function getRows( $cond, $orderBy = array() ) {
        if( self::$isPDO ) {
            return $this->getRowsPDO( $cond, $orderBy );
        } else {
            return $this->getRowsClassical( $cond, $orderBy );
        }
    }

    private function getRowFromQueryClassical($query) {
        $this->actions[] = $query;
        $result = mysql_query($query,self::getConnexion());
        if(!$result)
        {
            throw new \Exception($query.mysql_error(self::getConnexion()));
        }
        return mysql_fetch_assoc($result);
    }

    private function getRowsFromQueryClassical($query)
    {
        $this->actions[] = $query;
	$result = mysql_query($query,self::getConnexion());
        if(!$result)
        {
            throw new \Exception($query.mysql_error(self::getConnexion()));
        }
        $return = array();
        while($row = mysql_fetch_assoc($result))
        {
            $return[]=$row;
        }
        return $return;
    }

    private function getRowFromQueryPDO( $query ) {
        
        $this->actions[] = $query;
        
        $con = self::getConnexion();

        $stmt = $con
            ->prepare( $query )
        ;
        foreach( $cond as $field => $value ) {
            $stmt
                ->bindParam(':'.$field , $value )
            ;
        }
        $stmt
            ->execute()
        ;
        $row = $stmt->fetch();
        return $row;

    }

    private function getRowsFromQueryPDO( $query ) {
        
        $this->actions[] = $query;
        
        $con = self::getConnexion();

        $stmt = $con
            ->prepare( $query )
        ;
        foreach( $cond as $field => $value ) {
            $stmt
                ->bindParam(':'.$field , $value )
            ;
        }
        $stmt
            ->execute()
        ;
        $rows = $stmt->fetchAll();
        return $rows;

    }

    public function getRowFromQuery( $query ) {
        if ( self::$isPDO ) {
            return 
                $this->getRowFromQueryPDO( $query )
            ;
        } else {
            return 
                $this->getRowFromQueryClassical( $query )
            ;
        }
    }

    public function getRowsFromQuery( $query ) {
        if ( self::$isPDO ) {
            return 
                $this->getRowsFromQueryPDO( $query )
            ;
        } else {
            return 
                $this->getRowsFromQueryClassical( $query )
            ;
        }
    }


    public function createUpdate($tab,$cond)
    {
        $row = $this->getRow($cond);
        if($row)
        {
            $this->update($tab,$cond);
            return false;
        }else
        {
            return $this->add($tab);

        }
    }

    public function IN( $tab )
    {
        $return = "(";
        $sep = '';
        foreach($tab as $id)
        {
            $return.= $sep." '".$this->e($id)."' ";
            $sep=',';
        }
        return $return.= ')';
    }


    private function updateClassical( $values, $conditions ) {
        $set ='';
        $separatorSet = '';
        foreach($values as $key => $value)
        {
            $set .= $separatorSet. ' `'.$key.'` = \''.$this->e($value).'\' ';
            $separatorSet = ',';
        }
        $where = $this->getConditionsQuery($conditions);
        $query = 'UPDATE `'.$this->table.'` SET '.$set.'WHERE '.$where;
        $this->actions[] = $query;
        mysql_query($query,self::getConnexion());
    }

    private function updatePDO( $values, $conditions ) {
        
        $set ='';
        $separatorSet = '';
        foreach($values as $key => $value)
        {
            $set .= $separatorSet. ' `'.$key.'` = :'.$key.' ';
            $separatorSet = ',';
        }
        $where = $this->getConditionsQuery( $conditions );
        

        $query = 'UPDATE `'.$this->table.'` SET '.$set.'WHERE '.$where;
        
        $con = self::getConnexion();

        $stmt = $con
            ->prepare( $query )
        ;

        $bindable = array_merge( $values , $conditions );

        foreach( $bindable as $field => $value ) {
            $stmt
                ->bindParam(':'.$field , $value )
            ;
        }
        $stmt
            ->execute()
        ;
        
        $this->actions[] = $query;
        
    }

    public function update( $values, $conditions ) {
        
        if ( self::$isPDO ) {
            
            return 
                $this
                    ->updatePDO( $values, $conditions );
        } else {
            
            return 
                $this
                    ->updateClassical( $value, $onditions );
        }

    }

    private function deleteClassical ( $cond ) {

        $query = sprintf("DELETE FROM `{$this->table}` WHERE %s",$this->getConditionsQuery($cond));
        
        $this->actions[] = $query;
        
        mysql_query($query,self::getConnexion());
    
    }

    private function deletePDO ( $cond ) {

        $query = sprintf("DELETE FROM `{$this->table}` WHERE %s",$this->getConditionsQuery($cond));
        
        $this->actions[] = $query;
        
        $con = self::getConnexion();

        $stmt = $con
            ->prepare( $query )
        ;

        $bindable = $cond;

        foreach( $bindable as $field => $value ) {
            $stmt
                ->bindParam(':'.$field , $value )
            ;
        }
        $stmt
            ->execute()
        ;
    
    }

    public function delete ( $cond ) {

        if (self::$isPDO ) {
            return 
                $this
                    ->deletePDO( $cond )
            ;
        } else {
            return 
                $this
                    ->deleteClassical( $cond)
            ;
        }

    }

    protected function getConditionsQuery( $conditions ) {
        
        $where = '';
        
        $separatorWhere = '';

        if(self::$isPDO) {

            foreach($conditions as $key => $value){
                $where .= $separatorWhere.' '.$key.' = :'.$key.' ';
                $separatorWhere = 'AND';
            }

        } else {

            foreach($conditions as $key => $value){
                $where .= $separatorWhere.' `'.$key.'` = \''.$this->e($value).'\' ';
                $separatorWhere = 'AND';
            }
        }

        return $where;
    }

    protected function getOrderCondition( array $cond ) {

        if(count($cond) == 0)
        {
            return '';
        }
        $string = " ORDER BY ";
        $sep = '';
        foreach($cond as $field=>$keyWord)
        {
            $string .= $sep.' `'.$field.'` '.$keyWord.'';
            $sep = ',';
        }
        return $string;
    }

    private function queryClassical ( $query, $return = true ) {
        
        $this->actions[] = $query;

        $result = mysql_query($query,self::getConnexion());
        
        if(!$result)
        {
            throw new \Exception($query.mysql_error(self::getConnexion()));
        }
        
        $ret = null;
        
        if($return) $ret=mysql_fetch_assoc($result);
        
        return $ret;
    }

    private function queryPDO( $query, $return = true ) {
        
        $this->actions[] = $query;

        $con = self::getConnexion();

        $stmt = $con
            ->prepare( $query )
        ;

        $bindable = [];

        foreach( $bindable as $field => $value ) {
            $stmt
                ->bindParam(':'.$field , $value )
            ;
        }
        $stmt
            ->execute()
        ;

        $ret = null;
        
        if( $return ) $ret=$stmt->fetch();
        
        return $ret;
    }

    public function query ( $cond ) {

        if ( self::$isPDO ) {
            return 
                $this
                    ->queryPDO( $cond )
            ;
        } else {
            return 
                $this
                    ->queryClassical( $cond)
            ;
        }

    }

    private function queryAllClassical( $query ) {
        
        $this->actions[] = $query;

        $result = mysql_query($query,self::getConnexion());
        
        if(!$result) {
            throw new \Exception($query.mysql_error(self::getConnexion()));
        }
        
        $return = array();
        
        while($row = mysql_fetch_assoc($result)) {
            $return[]= $row;
        }
        
        return $return;
    }

    private function queryAllPDO( $query ) {
        
        $this->actions[] = $query;

        $con = self::getConnexion();

        $stmt = $con
            ->prepare( $query )
        ;

        $bindable = [];

        foreach( $bindable as $field => $value ) {
            $stmt
                ->bindParam(':'.$field , $value )
            ;
        }
        $stmt
            ->execute()
        ;

        $ret = [];
        
        while( $row = $stmt->fetch() ) {
            $ret[] = $row;
        }
        
        return $ret;
    }

    public function queryAll ( $cond ) {

        if ( self::$isPDO ) {
            return 
                $this
                    ->queryAllPDO( $cond )
            ;
        } else {
            return 
                $this
                    ->queryAllClassical( $cond)
            ;
        }

    }

    

}
