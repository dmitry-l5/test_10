<?php

class User {
    public static function get_user($id = 0){
        $id = intval($id);
        $q = DB::query("SELECT * FROM users WHERE user_id = '".$id."' LIMIT 1;") or die(DB::error());
        $row =  DB::fetch_row($q);
        return $row?$row:[
                'user_id'=>0,
                'first_name' => '',
                'last_name' => '',
                'phone' => '',
                'email' => '',
                'plot_id' => '',
            ];
    }
    // GENERAL
    public static function user_info($d) {
        // vars
        $user_id = isset($d['user_id']) && is_numeric($d['user_id']) ? $d['user_id'] : 0;
        $phone = isset($d['phone']) ? preg_replace('~\D+~', '', $d['phone']) : 0;
        // where
        if ($user_id) $where = "user_id='".$user_id."'";
        else if ($phone) $where = "phone='".$phone."'";
        else return [];
        // info
        $q = DB::query("SELECT user_id, phone, access FROM users WHERE ".$where." LIMIT 1;") or die (DB::error());
        if ($row = DB::fetch_row($q)) {
            return [
                'id' => (int) $row['user_id'],
                'access' => (int) $row['access']
            ];
        } else {
            return [
                'id' => 0,
                'access' => 0
            ];
        }
    }
    public static function users_list_plots($number) {
        // vars
        $items = [];
        // info
        $q = DB::query("SELECT user_id, plot_id, first_name, email, phone
            FROM users WHERE plot_id LIKE '%".$number."%' ORDER BY user_id;") or die (DB::error());
        while ($row = DB::fetch_row($q)) {
            $plot_ids = explode(',', $row['plot_id']);
            $val = false;
            foreach($plot_ids as $plot_id) if ($plot_id == $number) $val = true;
            if ($val) $items[] = [
                'id' => (int) $row['user_id'],
                'first_name' => $row['first_name'],
                'email' => $row['email'],
                'phone_str' => phone_formatting($row['phone'])
            ];
        }
        // output
        return $items;
    }

    public static function users_fetch($d = []) {
        $info = User::users_list($d);
        HTML::assign('users', $info['items']);
        return ['html' => HTML::fetch('./partials/users_table.html'), 'paginator' => $info['paginator']];
    }

    public static function users_list($d = []){
        // vars
        $search = isset($d['search']) && trim($d['search']) ? $d['search'] : '';
        $offset = isset($d['offset']) && is_numeric($d['offset']) ? $d['offset'] : 0;
        $limit = 20;
        $items = [];
        // where
        if ($search) {
            $orWhere[] = "phone LIKE '%".$search."%'";
            $orWhere[] = "first_name LIKE '%".$search."%'";
            $orWhere[] = "last_name LIKE  '%".$search."%'"; 
            $orWhere[] = "email LIKE '%".$search."%'";
        }
        $where = isset($orWhere)?"WHERE ".implode(" OR ", $orWhere):"oppa";
        $q = DB::query("SELECT * FROM users ".$where." LIMIT ".$offset.", ".$limit.";")  or die (DB::error());
        while($row = DB::fetch_row($q)){


            $items[]=[
                'user_id'=>$row['user_id'],
                'plot_id'=>$row['plot_id'],
                'first_name'=>$row['first_name'],
                'last_name'=>$row['last_name'],
                'email'=>$row['email'],
                'phone'=>$row['phone'],
                'last_login'=>date(DATE_ATOM, $row['last_login']),
            ];
        }
        $q = DB::query("SELECT count(*) FROM users ".$where.";");
        $count = ($row = DB::fetch_row($q)) ? $row['count(*)'] : 0;
        $url = 'users?';
        paginator($count, $offset, $limit, $url, $paginator);
        return ['items' => $items, 'paginator' => $paginator];
    }
    

    static public function delete_window($d){
        $user_id = isset($d['user_id']) && is_numeric($d['user_id']) ? $d['user_id'] : 0;
        HTML::assign('user', User::get_user($user_id));
        // HTML::assign('user', User::user_info($user_id));
        return [
            'html'  => HTML::fetch('./partials/user_delete.html'),
            'debug' => '',
        ];
    }
    static public function delete_user($d){
        $user_id = isset($d['user_id']) && is_numeric($d['user_id']) ? $d['user_id'] : 0;
        $offset = isset($d['offset'])?$d['offset']:0;
        if($user_id){
            DB::query("DELETE FROM users WHERE user_id='".$user_id."';") or die (DB::error());
        }
        return User::users_fetch(['offset'=>$offset]);
    }
    static public function user_edit_window($d){
        $user_id = isset($d['user_id']) && is_numeric($d['user_id']) ? $d['user_id'] : 0;
        HTML::assign('user', User::get_user($user_id));
        // HTML::assign('user', User::user_info($user_id));
        return [
            'html'  => HTML::fetch('./partials/user_edit.html'),
            'debug' => '',
        ];
    }
    static public function user_edit_update($d){
        $check_arr = [];

        $user_id = isset($d['user_id']) && is_numeric($d['user_id']) ? $d['user_id'] : 0;
        $first_name = isset($d['first_name'])&&!empty($d['first_name'])?$d['first_name']:(array_push($check_arr,'first_name'));
        $last_name  = isset($d['last_name'])&&!empty($d['last_name'])?$d['last_name']:$check_arr[]='last_name';
        $phone = intval(isset($d['phone'])?preg_replace('~\D+~', '', $d['phone']):0);
        $phone = !empty($phone)?$phone:array_push($check_arr, 'phone');
        $email = isset($d['email'])&&!empty($d['email'])?strtolower($d['email']):$check_arr[]='email';
        if(count($check_arr) > 0) return ['input_err'=>$check_arr];


        
        $plots = isset($d['plots'])?preg_replace('~\s+~', '', $d['plots']):'';
        $plots_arr = array_filter(explode(',', $plots) ?? [], function($item){
            //... may be check is plot exist
            return intval($item)?true:false;
        });
        $plots = implode(',',$plots_arr);
        // dd($plots, $plots_arr);
        $offset = isset($d['offset'])?$d['offset']:0;
        if($user_id){
            $set = [];
            $set[] = "first_name='".$first_name."'";
            $set[] = "last_name='".$last_name."'";
            $set[] = "phone='".$phone."'";
            $set[] = "email='".$email."'";
            $set[] = "plot_id='".$plots."'";
            $set = implode(", ", $set);
            DB::query("UPDATE users SET ".$set." WHERE user_id='".$user_id."' LIMIT 1;") or die (DB::error());

        }else{
            DB::query("INSERT INTO users (
                first_name,
                last_name,
                phone,
                email,
                plot_id
            ) VALUES (
                '".$first_name."',
                '".$last_name."',
                '".$phone."',
                '".$email."',
                '".$plots."'
            );") or die (DB::error());

        }
        return User::users_fetch(['offset'=>$offset]);
    }
}