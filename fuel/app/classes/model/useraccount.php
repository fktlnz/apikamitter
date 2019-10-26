<?php 
class Model_Useraccount extends \Orm\Model {
    protected static $_connection = 'mysqli';
    protected static $_table_name = 'useraccount';
    protected static $_properties = array(
        'id',
        'account_id' => array(
            'data_type'  => 'int',
            'label'      => 'アカウントID',
        ),
        'screen_name' => array(
            'data_type'  => 'varchar',
            'label'      => 'ターゲットアカウントID',
        ),
        'text' => array(
            'data_type'  => 'varchar',
            'label'      => 'プロフィール内容',
        ),
        'type' => array(
            'data_type'  => 'int',
            'label'      => 'ユーザーアカウントタイプ（0:ターゲットアカウント 1:フォロー済アカウント　2:アンフォローアカウント）',
        ),
        'created_at' => array(
            'data_type' => 'datetime',
            'label'     => '作成日',
        ),
        'delete_flg' => array(
            'data_type' => 'tinyint', 
            'label'     => 'delete flag',
        ),
        
        
    );
}