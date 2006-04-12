<?

if (!defined('DTP')) define('DTP','');

class DBlite_dsql  {
    var $db;

    var $my=array(null,null);
    var $saved=array(null,null);
    /*
     * Array containing arguments
     */
    var $args;
    var $debug;

    function __call($function,$args){
        /*
         * This call wrapper implements the following function:
         * do_select, do_insert, do_etc
         */
        if(substr($function,0,3)=='do_'){
            // do_select, do_insert combine functionality of generating insert/select and executing it
            $fnname = substr($function,3);
            $query = call_user_func_array(array($this,$fnname),$args);
            if(substr($function,3,3)=='get'){
                // we need those values saved, before we run our query
                $this->db->fatal("I do not know how to handle this function call :((((");
            }
            return $this->query($query);
        }
        if(substr($function,0,3)=='get'){
            $tmp=array($this->db->cursor,$this->db->found_rows);
            $this->db->cursor=$this->cursor;
            $this->db->found_rows=$this->found_rows;
            $result = call_user_func_array(array($this->db,$function),$args);
            list($this->db->cursor,$this->db->found_rows)=$tmp;
            return $result;
        }
        return $this->db->fatal("Call to undefined DQ::$function");
    }

    function fatal($msg,$lev=null){
        $this->db->fatal($msg,$lev+4);
    }

    function foundRows(){
        return $this->my[1];
    }
    ////// Speed-access functions 
    function s(){
        /*
         * We are willing to preserve existing state of associated database.
         * That's why we save some of it's data in a local valiable, and
         * restore those after execution.
         */
        $this->saved=array($this->db->cursor,$this->db->found_rows,$this->db->calc_found_rows);
        list($this->db->cursor,$this->db->found_rows,$this->db->calc_found_rows) = $this->my;
    }
    function l($a=null){
        $this->my=array($this->db->cursor,$this->db->found_rows,$this->db->calc_found_rows);
        list($this->db->cursor,$this->db->found_rows,$this->db->calc_found_rows) = $this->saved;
        return $a;
    }
    function do_getHash($f=null){ 
        $this->s(); 
        return $this->l($this->db->getHash($this->select(),$f)); 
    }
    function do_getAll($f=null){ 
        $this->s(); 
        return $this->l($this->db->getAll($this->select(),$f)); 
    }
    function do_getAllHash($f=null){ 
        $this->s(); 
        return $this->l($this->db->getAllHash($this->select(),$f)); 
    }
    function do_getRow($f=null){ 
        $this->s(); 
        return $this->l($this->db->getRow($this->select(),$f)); 
    }
    function do_getOne(){ 
        $this->s(); 
        return $this->l($this->db->getOne($this->select())); 
    }
    function do_getAssoc(){ 
        $this->s(); 
        return $this->l($this->db->getAssoc($this->select())); 
    }
    function do_select(){ 
        $this->s(); 
        $r=$this->db->query($this->select());
        return $this->l($this);
    }
    /*
     * temporary disabled, those do not work as they should
    function do_select(){ return $this->query($this->select()); }
    function do_delete(){ return $this->query($this->select()); }
    */
    function do_insert(){ $this->__call('do_insert',array()); return $this->db->lastID(); }
    //function do_select(){ $this->__call('do_select',array()); return $this; }
    function query($str){
        $this->s();
        return $this->l($this->db->query($str));
    }
    function do_fetchRow($l=null){ 
        $this->s(); 
        return $this->l($this->db->fetchRow($l)); 
    }
    function do_fetchHash($l=null){ 
        $this->s(); 
        return $this->l($this->db->fetchHash($l)); 
    }

    function debug(){
        $this->debug=1;
        return $this;
    }
    /*
    function query($q,$param1=null){
        $this->db->query($q,$param1);
        $this->cursor=$this->db->cursor;
        $this->found_rows=$this->db->found_rows;
    }
    */

    ///////////////////// Dynamic SQL functions ////////////////
    function table($table){
        /*
         * Specify table for a query
         */
        $this->args['table']=DTP.$table;
        return $this;
    }
    function field($field,$table=null) {
        /*
         * Add new field to a query
         */
        if(isset($table)){
            $field=DTP.$table.'.'.$field;
        }
        if(is_array($field)){
            $this->args['fields']=safe_array_merge($this->args['fields'],$field);
        }else{
            $this->args['fields'][]=$field;
        }
        return $this;
    }
    function set($set,$val=array()) {
        /*
         * Set value for update. You can use this function in a several ways. First
         * of all you can just simply call:
         *  $this->set($field, $value);
         * which will result update of the field in a next query. 2nd form is
         * when you call
         *  $this->set($hash);
         * in which case all keys of hash will be set to apropritate values.
         *
         * Value will be quoted, if you want to avoid that - use one-argument form.
         *
         * You can use this with array too like:
         *  set(array(
         *   'a'=>'213',
         *   'b'=>'foobar',
         *   'password=password("foo")'     // one-argument way
         *   ));
         */
        if(is_array($set)){
            foreach($set as $_key=>$_val){
                if(is_numeric($_key)){
                    $this->set($_val);
                }elseif(is_null($_val)){
                    continue;
                }else{
                    $this->set($_key,$_val);
                }
            }
        }else{
            if($val===array()){
                // if 1 argument is specified and is not array, then use it
                // as-is
                $this->args['set'][]=$set;
            }elseif(is_null($val)){
                $this->args['set'][$set]="NULL";
            }else{
                $this->args['set'][$set]="'".addslashes($val)."'";
            }
        }
        return $this;
    }
    function setDate($field='ts',$value=null){
        if(is_null($value))$value=time();
        return $this->set($field,date('Y-m-d H:i:s',$value));
    }
    function where($where,$equals=false){
        if(!is_array($where)){
            if($equals!==false){
                if(is_null($equals)){
                    $where.=" is null";
                }else{
                    if(substr($where,-1,1)==' ')$where=substr($where,0,-1);
                    // let's see if there is a sign, so we don't put there anything
                    $c=substr($where,-1,1);
                    if($c=='<' || $c=='>' || $c=='='){
                        // no need to add sign, it's already there
                        $where.=" '".addslashes($equals)."'";
                    }elseif(substr($where,-5,5)==' like'){
                        $where.=" '".addslashes($equals)."'";
                    }else{
                        $where.=" = '".addslashes($equals)."'";
                    }
                }
            }
            $where = array($where);
        }
        $this->args['where'] = safe_array_merge($this->args['where'], $where);
        return $this;
    }
    function having($having,$equals=false){
        if(!is_array($having)){
            if($equals!==false){
                if(is_null($equals)){
                    $having.=" is null";
                }else{
                    if(substr($having,-1,1)==' ')$having=substr($having,0,-1);
                    // let's see if there is a sign, so we don't put there anything
                    $c=substr($having,-1,1);
                    if($c=='<' || $c=='>' || $c=='='){
                        // no need to add sign, it's already there
                        $having.=" '".addslashes($equals)."'";
                    }elseif(substr($having,-5,5)==' like'){
                        $having.=" '".addslashes($equals)."'";
                    }else{
                        $having.=" = '".addslashes($equals)."'";
                    }
                }
            }
            $having = array($having);
        }
        $this->args['having'] = safe_array_merge($this->args['having'], $having);
        return $this;
    }
    function join ($table,$on,$type='inner'){
        $this->args['join'][]="$type join ".DTP.$table." on $on";
        return $this;
    }
    function order($order,$desc=null,$prepend=null){
        if($desc)$order.=" desc";
        if($prepend){
            array_unshift($this->args['order'], $order);
        }else{
            $this->args['order'][]=$order;
        }
        return $this;
    }
    function limit($cnt,$shift=0){
        $this->args['limit']=array(
                'cnt'=>$cnt,
                'shift'=>$shift
                );
        return $this;
    }
    function group($group,$prepend=null) {
        /*
         * Set group
         */
        if($prepend){
            array_unshift($this->args['group'], $group);
        }else{
            $this->args['group'][]=$group;
        }
        return $this;
    }
    function select(){
        return $this->parseTemplate("select [options] [field] from [table] [join] [where] [group] [having] [order] [limit]");
    }
    function update(){
        return $this->parseTemplate("update [table] set [set] [where]");
    }
    function insert(){
        return $this->parseTemplate("insert [options] into [table] ([set_field]) values ([set_value])");
    }
    function delete(){
        return $this->parseTemplate("delete from [table] [where]");
    }
    function getArgs($required){
        /*
         * This function generates actual value for the arguments, which
         * will be placed into template
         */
        if(isset($required['field'])) {
            // comma separated fields, such as for select
            $fields=array();
            if(!is_array($this->args['fields'])){
                $this->fatal('Before generating query you should call $dq->field() several times, otherwise I do not know what fields you need',2);
            }
            foreach($this->args['fields'] as $field) {
                $fields[]=$field;
            }
            $args['field']=join(', ', $fields);
        }
        if(isset($required['options'])&&$this->args['options']){
            $args['options']=join(' ',$this->args['options']);
        }

        if(isset($required['set'])) {
            $set = array();
            if(!$this->args['set']) {
                return $this->fatal('You should call $dq->set() before requesting update');
            }
            foreach($this->args['set'] as $key=>$val) {
                if(is_int($key)) {
                    $set[]="$val";
                }else{
                    $set[]="`$key`=$val";
                }
            }
            $args['set']=join(', ', $set);
        }

        if(isset($required['set_field']) || isset($required['set_value'])) {
            $sf = $sv = array();
            if(!$this->args['set']) {
                return $this->fatal('You should call $dq->set() before requesting update',2);
            }
            foreach($this->args['set'] as $key=>$val) {
                if(is_numeric($key)){
                    list($sf[],$sv[])=split('=',$val,2);
                    continue;
                }
                $sf[]="`$key`";
                $sv[]=$val;
            }
            $args['set_field']=join(', ', $sf);
            $args['set_value']=join(', ', $sv);
        }

        if(isset($required['table'])) {
            $args['table']=$this->args['table'];
        }

        if(isset($required['join'])) {
            if(isset($this->args['join'])) {
                $args['join']=join(' ', $this->args['join']);
            }
        }

        if(isset($required['where'])) {
            if($this->args['where']) {
                $args['where'] = "where (".join(') and (', $this->args['where']).")";
            }
        }

        if(isset($required['having'])) {
            if($this->args['having']) {
                $args['having'] = "having (".join(') and (', $this->args['having']).")";
            }
        }

        if(isset($required['order'])) {
            if($this->args['order']) {
                $args['order'] = "order by ".join(', ', $this->args['order']);
            }
        }

        if(isset($required['group'])) {
            if($this->args['group']) {
                $args['group'] = "group by ".join(',',$this->args['group']);
            }
        }

        if(isset($required['limit'])) {
            if($this->args['limit']) {
                $args['limit'] = "limit ".$this->args['limit']['shift'].", ".$this->args['limit']['cnt'];
            }
        }


        return $args;
    }
    function parseTemplate($template) {
        /*
         * When given query template, this method will get required arguments
         * for it and place them inside returning ready to use query.
         */
        $parts = split('\[', $template);
        $required = array();

        // 1st part is not a variable
        $result = array(array_shift($parts));
        foreach($parts as $part) {
            list($keyword, $rest)=split('\]', $part);
            $result[] = array($keyword); $required[$keyword]=true;
            $result[] = $rest;
        }
        // now parts array contains strings and array of string, let's request
        // for required arguments

        $args = $this->getArgs($required);

        // now when we know all data, let's assemble resulting string
        foreach($result as $key => $part) {
            if(is_array($part)) {
                $result[$key]=$args[$part[0]];
            }
        }
        if($this->debug){
            echo '<font color=blue>'.join('',$result).'</font>';
        }
        return join('', $result);
    }
    function calc_found_rows(){
        $this->option("SQL_CALC_FOUND_ROWS");
        $this->my[2]=true;
    }
    function option($option){
        if(!is_array($option))$option=array($option);
        $this->args['options']=safe_array_merge($this->args['options'],$option);
    }
}
if(!function_exists('safe_array_merge')){
    // array_merge gives us an error when one of arguments is null. This function
    // acts the same as array_merge, but without warnings
    function safe_array_merge($a,$b=null){
        if(is_null($a)){
            $a=$b;
            $b=null;
        }
        if(is_null($b))return $a;
        return array_merge($a,$b);
    }
}
