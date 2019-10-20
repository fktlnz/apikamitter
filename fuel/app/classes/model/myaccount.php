<?php 
class Model_Myaccount extends \Orm\Model {
    protected static $_connection = 'mysqli';
    protected static $_table_name = 'myaccount';
    protected static $_properties = array(
        'id',
        'screen_name' => array(
            'data_type'  => 'varchar',
            'label'      => 'スクリーンネーム',
        ),
        'user_id' => array(
            'data_type'  => 'int',
            'label'      => 'ユーザーID',
        ),
        'access_token' => array(
            'data_type'  => 'varchar',
            'label'      => 'アクセストークン',
        ),        
        'access_token_secret' => array(
            'data_type' => 'varchar',
            'label'     => 'アクセストークンシークレット',
        ),
        'create_date' => array(
            'data_type' => 'datetime',
            'label'     => '作成日',
        ),
        'delete_flg' => array(
            'data_type' => 'tinyint', 
            'label'     => 'delete flag',
        )
    );
}