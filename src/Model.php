<?php
namespace SimpleMySQL;

class Model
{
    protected static $db;
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

    public function init($table)
    {
        if(!isset(self::$db))
        {
            self::$db = new Model();

        }
        $this->table = $table;
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
        mysql_query($request,self::$db);
        return mysql_insert_id(self::$db);
    }

    public function e($value){
        return mysql_real_escape_string($value,self::$db);
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
        $result = mysql_query($query,self::$db);
        if(!$result)
        {
            throw new \Exception($query.mysql_error(self::$db));
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
        $result = mysql_query($query,self::$db);
        if(!$result)
        {
            throw new \Exception($query.' '.mysql_error(self::$db));
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
        $result = mysql_query($query,self::$db);
        if(!$result)
        {
            throw new \Exception($query.mysql_error(self::$db));
        }
        return mysql_fetch_assoc($result);
    }

    public function getRowsFromQuery($query)
    {
        $result = mysql_query($query,self::$db);
        if(!$result)
        {
            throw new \Exception($query.mysql_error(self::$db));
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
            return $this->create($tab);

        }
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
        mysql_query($query,self::$db);
    }

    public function delete($cond)
    {
        $query = sprintf("DELETE FROM `{$this->table}` WHERE %s",$this->getConditionsQuery($cond));
        mysql_query($query,self::$db);
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
            $string .= $sep.' `'.$field.'` '.strtoupper($keyWord);
            $sep = ',';
        }
        return $string;
    }


}