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

    private function __construct()
    {

        self::$db = mysql_connect(self::$host,self::$login,self::$pass);
        mysql_select_db(self::$base,self::$db);
        mysql_query('SET NAMES UTF8');

    }

    public static function setParams($host,$login,$pass,$base)
    {
        self::$host = $host;
        self::$login = $login;
        self::$pass = $pass;
        self::$base = $base;
    }

    public static function getConnexion() {
        if(false == mysql_ping(self::$db)){
            self::$instance = new Model();
        }
        return self::$db;
    }
/
    public function __toString()
    {
        return $this->table;
    }

    public function init($table)
    {
        $this->table = $table;
        if(!isset(self::$instance))
        {
            return self::$instance = new Model();

        }

    }

    public function add(array $tab)
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
        $result = mysql_query($request,self::getConnexion());
        if(!$result)
        {
            throw new \Exception($request.mysql_error(self::getConnexion());
        }
        return mysql_insert_id(self::getConnexion());
    }

    public function e($value){
        return mysql_real_escape_string(trim($value),self::getConnexion());
    }

    public function getRow($cond)
    {
        $where = '';
        $sep = '';
        foreach($cond as $key => $value)
        {
            $where .= $sep.' `'.$key.'` = \''.$this->e($value).'\' ';
            $sep = 'AND';
        }

        $query = 'SELECT * FROM `'.$this->table.'` WHERE '.$where;
        $result = mysql_query($query,self::getConnexion());
        if(!$result)
        {
            throw new \Exception($query.mysql_error(self::getConnexion()));
        }
        $row = mysql_fetch_assoc($result);
        return $row;
    }

    public function getRows($cond,$orderBy = array())
    {
        $where = 'WHERE';
        $orderBy = $this->getOrderCondition($orderBy);
        if(count($cond)== 0) $where = '';
        $query = sprintf("SELECT * FROM `{$this->table}` %s %s %s",$where,$this->getConditionsQuery($cond),$orderBy);
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

    public function getRowFromQuery($query)
    {
        $result = mysql_query($query,self::getConnexion());
        if(!$result)
        {
            throw new \Exception($query.mysql_error(self::getConnexion()));
        }
        return mysql_fetch_assoc($result);
    }

    public function getRowsFromQuery($query)
    {
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

    public function IN($tab)
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


    public function update($values,$conditions)
    {
        $set ='';
        $separatorSet = '';
        foreach($values as $key => $value)
        {
            $set .= $separatorSet. ' `'.$key.'` = \''.$this->e($value).'\' ';
            $separatorSet = ',';
        }
        $where = $this->getConditionsQuery($conditions);
        $query = 'UPDATE `'.$this->table.'` SET '.$set.'WHERE '.$where;
        mysql_query($query,self::getConnexion());
    }

    public function delete($cond)
    {
        $query = sprintf("DELETE FROM `{$this->table}` WHERE %s",$this->getConditionsQuery($cond));
        mysql_query($query,self::getConnexion());
    }

    protected function getConditionsQuery($conditions)
    {
        $where = '';
        $separatorWhere = '';

        foreach($conditions as $key => $value){
            $where .= $separatorWhere.' `'.$key.'` = \''.$this->e($value).'\' ';
            $separatorWhere = 'AND';
        }

        return $where;
    }

    protected function getOrderCondition(array $cond){

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

    public function query($query,$return = true)
    {
        $result = mysql_query($query,self::getConnexion());
        if(!$result)
        {
            throw new \Exception($query.mysql_error(self::getConnexion()));
        }
        $ret = null;
        if($return) $ret=mysql_fetch_assoc($result);
        return $ret;
    }

    public function queryAll($query) 
    {
        $result = mysql_query($query,self::getConnexion();
        if(!$result) {
            throw new \Exception($query.mysql_error(self::getConnexion()));
        }
        $return = array();
        while($row = mysql_fetch_assoc($result)) {
            $return[]= $row;
        }
        return $return;
    }


}
