<?php 

namespace Model;

class Db extends \Model
{
    ////======パスワードリマインダー==========/////

    //emailアドレスの登録があるか確認する
    public static function chk_emailExist($email=null)
    {
        try{
            return $query = \DB::count_records('users')->where(array(
                'email' => $email,
            ));
        }catch(Exception $e) {
            return false;
        }        
    }
    //usernameの登録があるか確認する
    public static function chk_usernameExist($username=null)
    {
        try{
            return $query = \DB::count_records('users')->where(array(
                'username' => $username,
            ));
        }catch(Exception $e) {
            return false;
        }        
    }

    // スクリーンネームを取得する
    public static function get_screenName($u_id=null)
    {
        try{
            return $query = \DB::select('screen_name')->from('myaccount')->where(array(
                'user_id' => $u_id,
                'delete_flg' => 0
            ))->execute()->as_array();
        }catch(Exception $e) {
            return false;
        }
        
        
    }

    public static function delete_account($u_id, $screen_name)
    {
         return $query = \DB::update('myaccount')->value('delete_flg', 1)->where(array(
             'user_id' => $u_id,
             'screen_name' => $screen_name
         ))->execute();
    }

    //
    public static function get_userInfo($u_id=null, $screen_name=null)
    {
        try{
            return $query = \DB::select('id', 'screen_name', 'access_token', 'access_token_secret')->from('myaccount')->where(array(
                'user_id' => $u_id,
                'screen_name' => $screen_name,
                'delete_flg' => 0
            ))->execute()->as_array();
        }catch(Exception $e) {
            return false;
        }       
        
    }

    //
    public static function get_tweetschedule($account_id=null)
    {
        try{
            return $query = \DB::select('id', 'date', 'text', 'done_flg')->from('schedule')->where(array(
                'account_id' => $account_id,
                'delete_flg' => 0
            ))->execute()->as_array();
        }catch(Exception $e) {
            return false;
        }

    }

    public static function set_scheduleDone($schedule_id=null)
    {
        try{
            return $query = \DB::update('schedule')->value('done_flg', 1)->where(array(
             'id' => $schedule_id,
            ))->execute();
        }catch(Exception $e) {
            return false;
        }

    }

    public static function delete_tweetschedule($word_id)
    {
         return $query = \DB::update('schedule')->value('delete_flg', 1)->where(array(
             'id' => $word_id,
         ))->execute();
    }

    //キーワードを取得する
    public static function get_keyword($account_id=null, $type=null) //type 0:フォロワーサーチキーワード　1:いいねキーワード

    {
        try{
            return $query = \DB::select('id', 'word', 'logic')->from('keyword')->where(array(
                'account_id' => $account_id,
                'type' => $type,
                'delete_flg' => 0
            ))->execute()->as_array();
        }catch(Exception $e) {
            return false;
        }
    }

    //キーワードを削除する
    public static function delete_keyword($word_id)
    {
         return $query = \DB::update('keyword')->value('delete_flg', 1)->where(array(
             'id' => $word_id
         ))->execute();
    }



    //いいねしないキーワードを取得する
    public static function get_notlikeword($account_id=null)
    {
        try{
            return $query = \DB::select('id', 'word')->from('keyword')->where(array(
                'account_id' => $account_id,
                'logic' => '2',
                'delete_flg' => 0
            ))->execute()->as_array();
        }catch(Exception $e) {
            return false;
        }
    }

    /* =========================================================================================
    # ユーザーアカウント登録　(0:ターゲットアカウント 1:フォロー済アカウント　2:アンフォローアカウント)
    ==========================================================================================*/

    //ユーザーアカウントを取得する
    public static function get_useraccount($account_id, $type, $datefilter=false, $flag=null)
    {
        try{
            if($datefilter !== false && $flag !== null){                
                
                if($flag){
                    //$datefilter日以内のデータを取得
                    return $query = \DB::select('id', 'screen_name')->from('useraccount')->where(array(
                        'account_id' => $account_id,
                        'type' => $type,
                        'delete_flg' => 0
                    ))->and_where('created_at','>',$datefilter)->execute()->as_array();

                }else{
                    //$datefilter日経過したデータを取得
                    return $query = \DB::select('id', 'screen_name')->from('useraccount')->where(array(
                        'account_id' => $account_id,
                        'type' => $type,
                        'delete_flg' => 0
                    ))->and_where('created_at','<',$datefilter)->execute()->as_array();
                }
                
            }else{
                //日付が新しいもの順に取得する
                return $query = \DB::select('id', 'screen_name', 'text', 'created_at')->from('useraccount')->where(array(
                    'account_id' => $account_id,
                    'type' => $type,
                    'delete_flg' => 0
                ))->order_by('created_at', 'desc')->execute()->as_array();
            }
        }catch(Exception $e) {
            return false;
        }
    }
    //ユーザーアカウントを取得する
    //$account_idユーザーにおいてaccountテーブルに登録された$screen_nameアカウントのtypeを$typeに変更する
    public static function change_useraccountType($account_id, $screen_name, $type)
    {
        try{
            return $query = \DB::update('useraccount')->value('type', $type)->where(array(
                'screen_name' => $screen_name
            ))->execute();
            
        }catch(Exception $e) {
            return false;
        }
    }

    //ユーザーアカウントを削除する
    public static function delete_useraccount($word_id)
    {
         return $query = \DB::delete('useraccount')->where(array(
             'id' => $word_id
         ))->execute();
    }    

    public static function get_votes()
    {
         return $query = \DB::select('vote_a', 'vote_b','vote_c','vote_d')->from('vote')->execute()->as_array();         
    }

}
