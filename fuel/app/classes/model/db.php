<?php 

namespace Model;

class Db extends \Model
{
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
    public static function get_useraccount($account_id=null, $type=null)
    {
        try{
            return $query = \DB::select('id', 'screen_name')->from('useraccount')->where(array(
                'account_id' => $account_id,
                'type' => $type,
                'delete_flg' => 0
            ))->execute()->as_array();
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

    public static function update_votes($select=null)
    {
        /*
        * 選択肢Aを＋１⇒$select='vote_a'
        * 選択肢Bを＋１⇒$select='vote_b'
        * 選択肢Cを＋１⇒$select='vote_c'
        * 選択肢Dを＋１⇒$select='vote_d'
        */
        switch($select) {
            case 'vote_a':
                $query = \DB::query('UPDATE vote SET vote_a=vote_a+1');
                $query->execute();
            break;
            case 'vote_b':
                $query = \DB::query('UPDATE vote SET vote_b=vote_b+1');
                $query->execute();
            break;
            case 'vote_c':
                $query = \DB::query('UPDATE vote SET vote_c=vote_c+1');
                $query->execute();
            break;
            case 'vote_d':
                $query = \DB::query('UPDATE vote SET vote_d=vote_d+1');
                $query->execute();
            break;
            default:
            break;
        }

         
    }

    public static function clear_votes()
    {
         $query = \DB::update('vote')->set(array(
             'vote_a' => 0,
             'vote_b' => 0,
             'vote_c' => 0,
             'vote_d' => 0
         ))->execute();
    }

}
